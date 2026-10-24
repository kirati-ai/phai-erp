<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Validation\Check;
use Phai\Modules\Production\Mrp;

/**
 * Scheduled orders (ใบสั่งแบบกำหนดส่ง) the way QAD runs Supplier Scheduling and Customer Scheduling: a purchase or
 * sales order flagged po_sched / so_sched is the agreement (items and prices); what to deliver when comes in schedule
 * releases (รอบตาราง, sch_mstr) — each one the whole schedule of every line (schd_det), dated instalments marked F firm
 * (up to the release's firm date: the shipping schedule, to deliver) or P planned (later: the planning schedule, a
 * forecast). A new release replaces the one in use and the old ones stay as the history.
 * Purchase side: we issue the releases to the supplier — set by hand or worked out from MRP (fromMrp). Sales side: the
 * customer sends them (a large customer by EDI; here keyed in) or we set them ourselves.
 * A line's ordered quantity is the sum of its instalments in the release in use, so receipts / shipments, MRP and the
 * order totals work as for any order; what has been received / shipped is taken from the earliest instalments first.
 */
final class Schedule
{
    /** The tables and columns of each kind of order. */
    public const KINDS = [
        'PO' => ['head' => 'po_mstr', 'nbr' => 'po_nbr', 'status' => 'po_status', 'open' => 'A', 'sched' => 'po_sched', 'date' => 'po_date',
                 'due' => 'po_due_date', 'tax' => 'po_tax_code', 'pre' => 'po', 'det' => 'pod_det', 'p' => 'pod', 'done' => 'pod_qty_rcvd',
                 'prog' => 'purchase-orders', 'page' => '/purchase-orders', 'sources' => ['MANUAL', 'MRP']],
        'SO' => ['head' => 'so_mstr', 'nbr' => 'so_nbr', 'status' => 'so_status', 'open' => 'O', 'sched' => 'so_sched', 'date' => 'so_date',
                 'due' => 'so_req_date', 'tax' => 'so_tax_code', 'pre' => 'so', 'det' => 'sod_det', 'p' => 'sod', 'done' => 'sod_qty_shp',
                 'prog' => 'sales-orders', 'page' => '/sales-orders', 'sources' => ['CUSTOMER', 'MANUAL']],
    ];
    /** Where a release came from: ORDER = made with the order (one instalment a line), replaced by the first real one. */
    public const SOURCES = ['ORDER', 'MANUAL', 'MRP', 'CUSTOMER'];
    /** Days after the release date that are firm when the release does not say. */
    public const FIRM_DAYS = 14;

    public static function kind(string $type): array
    {
        return self::KINDS[strtoupper($type)] ?? throw HttpException::notFound();
    }

    /** The release in use: the latest of the order (null = none yet). */
    public static function active(string $type, string $nbr): ?array
    {
        return App::db()->one('SELECT * FROM sch_mstr WHERE sch_type = ? AND sch_nbr = ? ORDER BY sch_rlse_id DESC LIMIT 1', [$type, $nbr]);
    }

    /** Every release of the order, the one in use first. */
    public static function releases(string $type, string $nbr): array
    {
        return App::db()->all('SELECT * FROM sch_mstr WHERE sch_type = ? AND sch_nbr = ? ORDER BY sch_rlse_id DESC', [$type, $nbr]);
    }

    /**
     * Called after an order is saved: the first one gets a release made from the order (one instalment a line, dated
     * the line's date). In the release in use, a line keeps its instalments while it is the same item with the same
     * total; otherwise (new line, item or quantity changed on the order) it gets one instalment for it all. A normal
     * order has none.
     */
    public static function seed(string $type, string $nbr): void
    {
        $k = self::kind($type);
        $db = App::db();
        $h = $db->one("SELECT * FROM {$k['head']} WHERE {$k['nbr']} = ?", [$nbr]);
        if (!$h || !(int) $h[$k['sched']]) {
            $db->run('DELETE FROM schd_det WHERE schd_type = ? AND schd_nbr = ?', [$type, $nbr]);
            $db->run('DELETE FROM sch_mstr WHERE sch_type = ? AND sch_nbr = ?', [$type, $nbr]);
            return;
        }
        $user = Auth::userId();
        $rel = self::active($type, $nbr);
        if (!$rel) {
            $date = (string) $h[$k['date']];
            $rel = ['sch_rlse_id' => self::nextId($db, $type, $nbr, $date), 'sch_date' => $date, 'sch_firm_date' => self::firmDefault($date)];
            $db->insert('sch_mstr', $rel + ['sch_type' => $type, 'sch_nbr' => $nbr, 'sch_source' => 'ORDER', 'created_by' => $user, 'updated_by' => $user]);
        }
        $id = $rel['sch_rlse_id'];
        $p = $k['p'];
        $lines = $db->all("SELECT * FROM {$k['det']} WHERE {$p}_nbr = ?", [$nbr]);
        $db->run('DELETE FROM schd_det WHERE schd_type = ? AND schd_nbr = ? AND schd_rlse_id = ? AND schd_line NOT IN ('
            . implode(',', array_map('intval', array_column($lines, "{$p}_line")) ?: [0]) . ')', [$type, $nbr, $id]);
        foreach ($lines as $l) {
            $line = (int) $l["{$p}_line"];
            $rows = $db->all('SELECT schd_part, schd_qty FROM schd_det WHERE schd_type = ? AND schd_nbr = ? AND schd_rlse_id = ? AND schd_line = ?', [$type, $nbr, $id, $line]);
            $same = $rows && $rows[0]['schd_part'] === $l["{$p}_part"]
                && abs(array_sum(array_map('floatval', array_column($rows, 'schd_qty'))) - (float) $l["{$p}_qty_ord"]) < 0.000005;
            if ($same) {
                continue;
            }
            $db->run('DELETE FROM schd_det WHERE schd_type = ? AND schd_nbr = ? AND schd_rlse_id = ? AND schd_line = ?', [$type, $nbr, $id, $line]);
            $date = $l["{$p}_req_date"] ?: ($h[$k['due']] ?: $h[$k['date']]);
            $db->insert('schd_det', [
                'schd_type' => $type, 'schd_nbr' => $nbr, 'schd_rlse_id' => $id, 'schd_line' => $line, 'schd_seq' => 1, 'schd_part' => $l["{$p}_part"],
                'schd_date' => $date, 'schd_qty' => $l["{$p}_qty_ord"], 'schd_fpps' => $date <= $rel['sch_firm_date'] ? 'F' : 'P',
                'created_by' => $user, 'updated_by' => $user,
            ]);
        }
    }

    /**
     * The order's lines with the instalments of one release (default: the one in use), each with what is already
     * received / shipped (earliest first) and F / P; with $prev (a release id) also what the same date had in it, and
     * the dates it had that this one dropped.
     * @return list<array{line: int, part: string, desc: ?string, um: string, done: float, rows: list<array>, removed: list<array>}>
     */
    public static function lines(string $type, string $nbr, ?string $rlse = null, ?string $prev = null): array
    {
        $k = self::kind($type);
        $p = $k['p'];
        $db = App::db();
        $rlse ??= self::active($type, $nbr)['sch_rlse_id'] ?? '';
        $rows = [];
        foreach ($db->all('SELECT * FROM schd_det WHERE schd_type = ? AND schd_nbr = ? AND schd_rlse_id = ? ORDER BY schd_line, schd_date, schd_seq', [$type, $nbr, $rlse]) as $r) {
            $rows[(int) $r['schd_line']][] = $r;
        }
        $before = [];
        if ($prev !== null) {
            foreach ($db->all('SELECT schd_line, schd_date, SUM(schd_qty) AS qty FROM schd_det WHERE schd_type = ? AND schd_nbr = ? AND schd_rlse_id = ?
                                GROUP BY schd_line, schd_date', [$type, $nbr, $prev]) as $r) {
                $before[(int) $r['schd_line']][$r['schd_date']] = (float) $r['qty'];
            }
        }
        $out = [];
        foreach ($db->all("SELECT * FROM {$k['det']} WHERE {$p}_nbr = ? ORDER BY {$p}_line", [$nbr]) as $l) {
            $line = (int) $l["{$p}_line"];
            $left = $done = (float) $l[$k['done']];
            $list = [];
            foreach ($rows[$line] ?? [] as $r) {
                $d = min((float) $r['schd_qty'], max(0.0, $left));
                $left -= $d;
                $list[] = ['date' => $r['schd_date'], 'qty' => (float) $r['schd_qty'], 'done' => round($d, 5), 'fpps' => $r['schd_fpps'],
                           'prev' => $prev === null ? null : ($before[$line][$r['schd_date']] ?? null)];
            }
            $dates = array_column($list, 'date');
            $removed = [];
            foreach ($before[$line] ?? [] as $date => $qty) {
                if (!in_array($date, $dates, true)) {
                    $removed[] = ['date' => $date, 'qty' => $qty];
                }
            }
            $out[] = ['line' => $line, 'part' => $l["{$p}_part"], 'desc' => $l["{$p}_desc"], 'um' => $l["{$p}_um"],
                      'price' => (float) $l["{$p}_price"], 'done' => $done, 'rows' => $list, 'removed' => $removed];
        }
        return $out;
    }

    /**
     * A new release. Body: {date, firm_date, source, ref, rmks, lines: {<line>: {rows: {<i>: {date, qty}}}}} — the whole
     * schedule of the lines sent (lines not sent keep their instalments). A line may not schedule less than it has
     * already received / shipped. Instalments up to firm_date are firm (F), later ones planned (P). The release made with
     * the order is replaced; any other stays as history. The line's ordered quantity, amount and status and the order's
     * totals follow. @return string the new release id
     */
    public static function save(string $type, string $nbr, array $body): string
    {
        $k = self::kind($type);
        $p = $k['p'];
        $db = App::db();
        $h = $db->one("SELECT * FROM {$k['head']} WHERE {$k['nbr']} = ?", [$nbr]) ?? throw HttpException::notFound();
        if (!(int) $h[$k['sched']]) {
            throw new HttpException(409, t('schedule.not_scheduled'));
        }
        if ($h[$k['status']] !== $k['open']) {
            throw new HttpException(409, t('schedule.closed'));
        }
        $active = self::active($type, $nbr);
        $replace = $active && $active['sch_source'] === 'ORDER';
        $c = new Check();
        $date = $c->date('date', ($body['date'] ?? '') ?: date('Y-m-d'), true);
        if ($date && $active && !$replace && $date < $active['sch_date']) {
            $c->fail('date', t('schedule.date_before', ['date' => date('d/m/Y', strtotime($active['sch_date']))]));
        }
        $firm = $c->date('firm_date', ($body['firm_date'] ?? '') ?: ($date ? self::firmDefault($date) : ''), true);
        if ($date && $firm && $firm < $date) {
            $c->fail('firm_date', t('schedule.firm_before'));
        }
        $source = (string) ($body['source'] ?? $k['sources'][0]);
        if (!in_array($source, $k['sources'], true)) {
            $c->fail('source', t('schedule.bad_source'));
        }
        $ref = $c->text('ref', $body['ref'] ?? '', false, 50, false);
        $rmks = $c->text('rmks', $body['rmks'] ?? '', false, 255, false);

        $lines = array_column($db->all("SELECT * FROM {$k['det']} WHERE {$p}_nbr = ?", [$nbr]), null, "{$p}_line");
        $set = [];
        foreach ((array) ($body['lines'] ?? []) as $line => $l) {
            if (!isset($lines[$line])) {
                continue;
            }
            $rows = [];
            foreach ((array) ($l['rows'] ?? []) as $i => $r) {
                $f = fn ($n) => "lines.$line.rows.$i.$n";
                if (trim((string) ($r['date'] ?? '')) === '' && trim((string) ($r['qty'] ?? '')) === '') {
                    continue; // an empty row
                }
                $rows[] = ['date' => $c->date($f('date'), $r['date'] ?? '', true), 'qty' => $c->number($f('qty'), $r['qty'] ?? '', true, 0, true)];
            }
            $total = round(array_sum(array_map(fn ($r) => (float) $r['qty'], $rows)), 5);
            $done = (float) $lines[$line][$k['done']];
            if (!$rows) {
                $c->fail("lines.$line.total", t('schedule.no_rows'));
            } elseif ($total < round($done, 5)) {
                $c->fail("lines.$line.total", t('schedule.below_done_' . $type, ['qty' => rtrim(rtrim(number_format($done, 5, '.', ','), '0'), '.')]));
            }
            usort($rows, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));
            $set[(int) $line] = ['rows' => $rows, 'total' => $total];
        }
        $c->done();

        return $db->transaction(function (Db $db) use ($type, $nbr, $k, $p, $h, $lines, $set, $active, $replace, $date, $firm, $source, $ref, $rmks) {
            $user = Auth::userId();
            // the lines not sent carry their instalments over from the release in use
            $keep = [];
            foreach ($active ? $db->all('SELECT * FROM schd_det WHERE schd_type = ? AND schd_nbr = ? AND schd_rlse_id = ? ORDER BY schd_line, schd_date, schd_seq',
                [$type, $nbr, $active['sch_rlse_id']]) : [] as $r) {
                $keep[(int) $r['schd_line']][] = ['date' => $r['schd_date'], 'qty' => (float) $r['schd_qty']];
            }
            if ($replace) {
                $db->run('DELETE FROM schd_det WHERE schd_type = ? AND schd_nbr = ? AND schd_rlse_id = ?', [$type, $nbr, $active['sch_rlse_id']]);
                $db->run('DELETE FROM sch_mstr WHERE sch_type = ? AND sch_nbr = ? AND sch_rlse_id = ?', [$type, $nbr, $active['sch_rlse_id']]);
            }
            $id = self::nextId($db, $type, $nbr, $date);
            $db->insert('sch_mstr', [
                'sch_type' => $type, 'sch_nbr' => $nbr, 'sch_rlse_id' => $id, 'sch_date' => $date, 'sch_firm_date' => $firm,
                'sch_source' => $source, 'sch_ref' => $ref ?: null, 'sch_rmks' => $rmks ?: null, 'created_by' => $user, 'updated_by' => $user,
            ]);
            foreach ($lines as $line => $l) {
                foreach ($set[$line]['rows'] ?? $keep[$line] ?? [] as $i => $r) {
                    $db->insert('schd_det', [
                        'schd_type' => $type, 'schd_nbr' => $nbr, 'schd_rlse_id' => $id, 'schd_line' => $line, 'schd_seq' => $i + 1,
                        'schd_part' => $l["{$p}_part"], 'schd_date' => $r['date'], 'schd_qty' => $r['qty'], 'schd_fpps' => $r['date'] <= $firm ? 'F' : 'P',
                        'created_by' => $user, 'updated_by' => $user,
                    ]);
                }
            }
            foreach ($set as $line => $s) {
                $l = $lines[$line];
                // more released reopens a line that had received / shipped all it had
                $db->update($k['det'], [
                    "{$p}_qty_ord" => $s['total'], "{$p}_req_date" => $s['rows'][0]['date'],
                    "{$p}_line_amt" => Money::lineAmount($s['total'], (float) $l["{$p}_price"], (float) $l["{$p}_disc_pct"]),
                    "{$p}_status" => $s['total'] > (float) $l[$k['done']] + 0.000005 ? 'O' : 'C', 'updated_by' => $user,
                ], ["{$p}_nbr" => $nbr, "{$p}_line" => $line]);
            }
            $pct = Money::taxPct($h[$k['tax']]);
            $tot = Money::totals(array_map('floatval', array_column($db->all("SELECT {$p}_line_amt FROM {$k['det']} WHERE {$p}_nbr = ?", [$nbr]), "{$p}_line_amt")), $pct);
            $db->update($k['head'], ["{$k['pre']}_tax_amt" => $tot['tax'], "{$k['pre']}_tot_amt" => $tot['total'], 'updated_by' => $user], [$k['nbr'] => $nbr]);
            return $id;
        });
    }

    /**
     * Supplier Schedule Update from MRP: a draft of the next release of a scheduled purchase order. Instalments up to
     * the firm date stay as they are (the supplier is already making them), and so do any partly received; after it,
     * the schedule becomes what MRP says must arrive — MRP is worked out without this order's later instalments, and
     * its planned purchases of the item after the firm date take their place.
     * @return array<int, list<array{date: string, qty: float}>> line => instalments
     */
    public static function fromMrp(string $nbr, string $firm): array
    {
        $lines = self::lines('PO', $nbr);
        $skip = array_map(fn ($l) => ['nbr' => $nbr, 'part' => $l['part'], 'after' => $firm], $lines);
        $planned = Mrp::plan($skip);
        $out = [];
        $taken = [];
        foreach ($lines as $l) {
            $rows = array_map(fn ($r) => ['date' => $r['date'], 'qty' => $r['qty']],
                array_values(array_filter($l['rows'], fn ($r) => $r['date'] <= $firm || $r['done'] > 0)));
            foreach ($planned as $i => $pl) {
                if ($pl['mrp_type'] === 'PO' && $pl['mrp_part'] === $l['part'] && $pl['mrp_due_date'] > $firm && !isset($taken[$i])) {
                    $rows[] = ['date' => $pl['mrp_due_date'], 'qty' => (float) $pl['mrp_qty']];
                    $taken[$i] = true; // a second line of the same item does not take it again
                }
            }
            usort($rows, fn ($a, $b) => strcmp($a['date'], $b['date']));
            $out[$l['line']] = $rows;
        }
        return $out;
    }

    /**
     * What is still to come of the open scheduled orders in their release in use, instalment by instalment, for MRP
     * (started = part of it already received / shipped).
     * @return list<array{nbr: string, part: string, date: string, qty: float, started: bool}>
     */
    public static function open(string $type): array
    {
        $k = self::kind($type);
        $out = [];
        foreach (App::db()->all("SELECT {$k['nbr']} AS nbr FROM {$k['head']} WHERE {$k['sched']} = 1 AND {$k['status']} = ?", [$k['open']]) as $o) {
            foreach (self::lines($type, $o['nbr']) as $l) {
                foreach ($l['rows'] as $r) {
                    if ($r['qty'] - $r['done'] > 0.000005) {
                        $out[] = ['nbr' => $o['nbr'], 'part' => $l['part'], 'date' => $r['date'], 'qty' => round($r['qty'] - $r['done'], 5), 'started' => $r['done'] > 0];
                    }
                }
            }
        }
        return $out;
    }

    /** One text block for the printed order: the release in use and each line's instalments ("15/10/2026 250", forecast marked). */
    public static function notes(string $type, string $nbr): string
    {
        $rel = self::active($type, $nbr);
        if (!$rel) {
            return '';
        }
        $text = [];
        foreach (self::lines($type, $nbr) as $l) {
            $text[] = $l['line'] . '. ' . $l['part'] . ': ' . implode(', ', array_map(
                fn ($r) => date('d/m/Y', strtotime($r['date'])) . ' ' . rtrim(rtrim(number_format($r['qty'], 5, '.', ','), '0'), '.') . ' ' . $l['um']
                    . ($r['fpps'] === 'P' ? ' (' . t('schedule.fpps_P') . ')' : ''),
                $l['rows']
            ));
        }
        return t('schedule.title') . ' ' . t('schedule.rlse_of', ['id' => $rel['sch_rlse_id'], 'date' => date('d/m/Y', strtotime($rel['sch_firm_date']))])
            . "\n" . implode("\n", $text);
    }

    public static function firmDefault(string $date): string
    {
        return date('Y-m-d', strtotime($date . ' +' . self::FIRM_DAYS . ' days'));
    }

    /** The release date and the order's running number on that date: 20261011-01 (ids sort as the releases were made). */
    private static function nextId(Db $db, string $type, string $nbr, string $date): string
    {
        $prefix = str_replace('-', '', $date);
        $n = (int) $db->value('SELECT COUNT(*) FROM sch_mstr WHERE sch_type = ? AND sch_nbr = ? AND sch_rlse_id LIKE ?', [$type, $nbr, "$prefix-%"]);
        return sprintf('%s-%02d', $prefix, $n + 1);
    }
}
