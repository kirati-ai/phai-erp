<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Db\Db;
use Phai\Modules\Inventory\Stock;
use Phai\Modules\Master\Schedule;

/**
 * MRP (Material Requirements Planning) the way the big ERP systems run it, all warehouses together.
 *
 * 1. MRP run (run(): the planner presses รัน MRP; regenerative — the whole plan again). Items are planned top-down by
 *    low-level code. Per item the gross requirements (open sales order lines, components still to issue on work orders,
 *    components of the planned work orders of the level above) are netted against the stock in nettable locations
 *    (keeping the safety stock) and the scheduled receipts (open purchase order lines, requisitions, work orders).
 *    A shortage that an open order covers later is not ordered again (that order gets a reschedule-in message); what is
 *    still short becomes a planned order sized by OrderSizing::lots(), due on the date of the shortage and released
 *    lead time (pt_lead_time days) earlier — a planned work order's components become requirements one level down.
 *    The planned orders are mrp_det; the time of the run is wo_ctrl.wo_mrp_run_at.
 * 2. Review (review()): the MRP record of each item by week (gross requirements, scheduled receipts, projected on hand,
 *    net requirements, planned order receipts / releases), the MRP detail behind it and the action messages — from the
 *    documents open now and the planned orders of the last run, so a planned order made into a document shows as that
 *    document straight away, while new demand waits for the next run.
 */
final class Mrp
{
    /** Rows of the MRP detail: requirements SO, WOC (component still to issue on a work order), PLC (component of a planned
     *  work order); scheduled receipts PO, PR, WO; PLAN = planned order of the last run. */
    public const KINDS = ['SO', 'WOC', 'PLC', 'PO', 'PR', 'WO', 'PLAN'];
    /** Action messages: RELEASE (its order date has come: make the document), IN (reschedule in / expedite: it arrives
     *  after the need), OUT (reschedule out: needed only later), CANCEL (nothing needs it). */
    public const MESSAGES = ['RELEASE', 'IN', 'OUT', 'CANCEL'];
    private const RECEIPTS = ['PO', 'PR', 'WO'];
    private const WEEKS = 26;
    private const EPS = 0.000001;

    /** The MRP run: plan again, write the planned orders (mrp_det) and when it ran. @return int planned orders */
    public static function run(): int
    {
        $rows = self::plan();
        App::db()->transaction(function (Db $db) use ($rows) {
            $now = date('Y-m-d H:i:s');
            $db->run('DELETE FROM mrp_det');
            foreach ($rows as $row) {
                // mrp_det is rebuilt on every run, so it has no updated_by
                $db->insert('mrp_det', $row + ['created_by' => Auth::userId(), 'created_at' => $now]);
            }
            $db->run('UPDATE wo_ctrl SET wo_mrp_run_at = ?, wo_mrp_run_by = ? WHERE ctrl_id = 1', [$now, Auth::userId()]);
        });
        return count($rows);
    }

    /**
     * The netting of a run: the planned orders (mrp_det rows) worked out from what is open now; nothing is written.
     * $skip = [[nbr, part, after]]: leave out those scheduled purchase instalments after that date not yet started
     * (Schedule::fromMrp asks what MRP would want instead of them).
     */
    public static function plan(array $skip = []): array
    {
        $db = App::db();
        $today = date('Y-m-d');
        [$events, $onHand, $safety] = self::open($db, $skip);
        // the safety stock is kept back from today on
        foreach ($safety as $part => $qty) {
            $events[$part][] = [$today, -$qty, 'Safety Stock', 'SS'];
        }
        $level = Bom::lowLevelCodes();
        $planned = [];  // "part|due|type" => row
        $done = [];

        // Keep taking the unplanned item with the lowest level: planned work orders add
        // demand to their components, which always sit at a deeper level.
        while ($left = array_diff(array_keys($events), array_keys($done))) {
            usort($left, fn ($a, $b) => ($level[$a] ?? 0) <=> ($level[$b] ?? 0) ?: strcmp($a, $b));
            $part = $left[0];
            $done[$part] = true;
            $item = $db->one('SELECT * FROM pt_mstr WHERE pt_part = ?', [$part]);
            if (!$item || !$item['pt_inventory']) {
                continue; // free text or not kept in stock: nothing to plan
            }
            $list = $events[$part];
            // by date; on the same date supply comes before demand, then the older document first (SO000001 before SO000002),
            // so an order on hand goes to the earlier sales order and the shortage — the planned order — to the later one
            usort($list, fn ($a, $b) => strcmp($a[0], $b[0]) ?: (($b[1] > 0) <=> ($a[1] > 0)) ?: strcmp((string) $a[2], (string) $b[2]));

            $balance = (float) ($onHand[$part] ?? 0);
            $type = $item['pt_part_type'] === 'M' ? 'WO' : 'PO';
            $list = array_values($list);
            foreach ($list as $i => [$date, $qty, $ref]) {
                $qty = $list[$i][1]; // a later receipt may have been pulled in already
                $balance += $qty;
                if ($qty >= 0 || $balance >= -self::EPS) {
                    continue;
                }
                // An order already open (work order, PO, requisition) that comes later covers the shortage first —
                // it is late and should be hurried (reschedule in), not ordered a second time. Only what is still short is planned.
                for ($j = $i + 1; $j < count($list) && $balance < -self::EPS; $j++) {
                    if ($list[$j][1] > 0) {
                        $take = min($list[$j][1], -$balance);
                        $balance += $take;
                        $list[$j][1] -= $take;
                    }
                }
                if ($balance >= -self::EPS) {
                    continue;
                }
                $order = array_sum(OrderSizing::lots(-$balance, $item));
                $balance += $order;
                $release = date('Y-m-d', strtotime($date . ' -' . (int) $item['pt_lead_time'] . ' days'));
                $release = max($release, $today);
                $key = "$part|$date|$type";
                $planned[$key] ??= ['mrp_part' => $part, 'mrp_type' => $type, 'mrp_qty' => 0.0, 'mrp_rel_date' => $release, 'mrp_due_date' => $date, 'mrp_ref' => $ref];
                $planned[$key]['mrp_qty'] += $order;

                if ($type === 'WO') {
                    foreach (Bom::explode($part, $order, $release) as $comp) {
                        $events[$comp['part']][] = [$release, -$comp['qty'], $part, 'PLC'];
                    }
                }
            }
        }

        $site = (string) ($db->value('SELECT si_site FROM si_mstr WHERE si_status = 1 ORDER BY si_site LIMIT 1') ?? '');
        return array_values(array_map(fn (array $row) => ['mrp_site' => $site, 'mrp_qty' => round($row['mrp_qty'], 5)] + $row, $planned));
    }

    /**
     * What the MRP screen shows: weeks (the columns of the MRP record: 0 = past due, then each week from this Monday;
     * more = the last one also holds everything later), items in planning order (the MRP record grid, the detail rows
     * with the balance after each, totals and the item's planning data) and the action messages.
     */
    public static function review(): array
    {
        $db = App::db();
        $today = date('Y-m-d');
        [$events, $onHand, $safety] = self::open($db);
        $planned = $db->all('SELECT mrp_part, mrp_type, mrp_qty, mrp_rel_date, mrp_due_date, mrp_ref FROM mrp_det ORDER BY mrp_due_date');
        foreach ($planned as $p) {
            $events[$p['mrp_part']] ??= [];
            if ($p['mrp_type'] === 'WO') {
                foreach (Bom::explode($p['mrp_part'], (float) $p['mrp_qty'], $p['mrp_rel_date']) as $comp) {
                    $events[$comp['part']][] = [$p['mrp_rel_date'], -$comp['qty'], $p['mrp_part'], 'PLC'];
                }
            }
        }
        foreach (array_keys($safety) as $part) {
            $events[$part] ??= [];
        }

        // weekly buckets from the Monday of this week to the last date anything happens
        $monday = date('Y-m-d', strtotime($today . ' -' . ((int) date('N') - 1) . ' days'));
        $last = $monday;
        foreach ($events as $list) {
            foreach ($list as $e) {
                $last = max($last, $e[0]);
            }
        }
        foreach ($planned as $p) {
            $last = max($last, $p['mrp_due_date']);
        }
        $days = fn (string $from, string $to) => (int) (new \DateTimeImmutable($from))->diff(new \DateTimeImmutable($to))->days;
        $weeks = min(self::WEEKS, intdiv($days($monday, $last), 7) + 1);
        $bucket = fn (string $date) => $date < $monday ? 0 : min($weeks, intdiv($days($monday, $date), 7) + 1);
        $heads = [null];
        for ($w = 0; $w < $weeks; $w++) {
            $heads[] = date('Y-m-d', strtotime("$monday +" . (7 * $w) . ' days'));
        }

        $level = Bom::lowLevelCodes();
        $parts = array_keys($events);
        usort($parts, fn ($a, $b) => ($level[$a] ?? 0) <=> ($level[$b] ?? 0) ?: strcmp((string) $a, (string) $b));
        $items = [];
        $messages = [];
        foreach ($parts as $part) {
            $item = $db->one('SELECT * FROM pt_mstr WHERE pt_part = ?', [(string) $part]);
            if (!$item || !$item['pt_inventory']) {
                continue;
            }
            $mine = array_values(array_filter($planned, fn ($p) => $p['mrp_part'] === $item['pt_part']));
            $items[] = self::record($item, (float) ($onHand[$part] ?? 0), (float) ($safety[$part] ?? 0), $events[$part], $mine,
                (int) ($level[$part] ?? 0), $bucket, count($heads), $today, $messages);
        }
        usort($messages, fn ($a, $b) => array_search($a['type'], self::MESSAGES, true) <=> array_search($b['type'], self::MESSAGES, true)
            ?: strcmp($a['date'], $b['date']) ?: strcmp($a['part'], $b['part']));
        return ['weeks' => $heads, 'more' => $last >= date('Y-m-d', strtotime("$monday +" . (7 * $weeks) . ' days')), 'items' => $items, 'messages' => $messages];
    }

    /** Open requirements and scheduled receipts per part ([date, qty + / -, ref, kind]), nettable stock, safety stock. */
    private static function open(Db $db, array $skip = []): array
    {
        $events = [];
        $add = function (string $part, ?string $date, float $qty, string $ref, string $kind) use (&$events): void {
            if ($qty != 0) {
                $events[$part][] = [(string) $date, $qty, $ref, $kind];
            }
        };
        foreach ($db->all(
            "SELECT d.sod_part, d.sod_qty_ord - d.sod_qty_shp AS qty, COALESCE(d.sod_req_date, s.so_req_date, s.so_date) AS d, s.so_nbr
               FROM sod_det d JOIN so_mstr s ON s.so_nbr = d.sod_nbr
              WHERE s.so_status = 'O' AND s.so_sched = 0 AND d.sod_status = 'O' AND d.sod_qty_ord > d.sod_qty_shp"
        ) as $r) {
            $add($r['sod_part'], $r['d'], -(float) $r['qty'], $r['so_nbr'], 'SO');
        }
        foreach (Schedule::open('SO') as $r) {
            $add($r['part'], $r['date'], -$r['qty'], $r['nbr'], 'SO');
        }
        foreach ($db->all(
            "SELECT d.wod_part, d.wod_qty_req - d.wod_qty_iss AS qty, w.wo_rel_date AS d, w.wo_nbr
               FROM wod_det d JOIN wo_mstr w ON w.wo_nbr = d.wod_nbr
              WHERE w.wo_status IN ('F', 'R') AND d.wod_qty_req > d.wod_qty_iss"
        ) as $r) {
            $add($r['wod_part'], $r['d'], -(float) $r['qty'], $r['wo_nbr'], 'WOC');
        }
        foreach ($db->all(
            "SELECT d.pod_part, d.pod_qty_ord - d.pod_qty_rcvd AS qty, COALESCE(d.pod_req_date, p.po_due_date, p.po_date) AS d, p.po_nbr
               FROM pod_det d JOIN po_mstr p ON p.po_nbr = d.pod_nbr
              WHERE p.po_status = 'A' AND p.po_sched = 0 AND d.pod_status = 'O' AND d.pod_qty_ord > d.pod_qty_rcvd"
        ) as $r) {
            $add($r['pod_part'], $r['d'], (float) $r['qty'], $r['po_nbr'], 'PO');
        }
        foreach (Schedule::open('PO') as $r) {
            foreach ($skip as $s) {
                if ($r['nbr'] === $s['nbr'] && $r['part'] === $s['part'] && $r['date'] > $s['after'] && !$r['started']) {
                    continue 2;
                }
            }
            $add($r['part'], $r['date'], $r['qty'], $r['nbr'], 'PO');
        }
        // requisitions not yet ordered are on their way too, or MRP would suggest them again
        foreach ($db->all(
            "SELECT d.prd_part, d.prd_qty_req - d.prd_qty_ord AS qty, d.prd_req_date AS d, p.pr_nbr
               FROM prd_det d JOIN pr_mstr p ON p.pr_nbr = d.prd_nbr
              WHERE p.pr_status = 'A' AND d.prd_qty_req > d.prd_qty_ord"
        ) as $r) {
            $add($r['prd_part'], $r['d'], (float) $r['qty'], $r['pr_nbr'], 'PR');
        }
        foreach ($db->all(
            "SELECT wo_part, wo_qty_ord - wo_qty_comp AS qty, wo_due_date AS d, wo_nbr
               FROM wo_mstr WHERE wo_status IN ('F', 'R') AND wo_qty_ord > wo_qty_comp"
        ) as $r) {
            $add($r['wo_part'], $r['d'], (float) $r['qty'], $r['wo_nbr'], 'WO');
        }
        $onHand = array_column($db->all(
            "SELECT ld.ld_part, SUM(ld.ld_qty_oh) AS qty FROM ld_det ld
               JOIN loc_mstr l ON l.loc_site = ld.ld_site AND l.loc_loc = ld.ld_loc
              WHERE l.loc_nettable = 1 GROUP BY ld.ld_part"
        ), 'qty', 'ld_part');
        // warehouses are pooled: the safety stock is the sum of the active sites' buffers
        $safety = array_map('floatval', array_column($db->all(
            "SELECT ps.pt_part, SUM(ps.pt_safe_stk) AS qty FROM pt_site_mstr ps JOIN si_mstr si ON si.si_site = ps.pt_site
              WHERE ps.pt_status = 'ACTIVE' AND si.si_status = 1 GROUP BY ps.pt_part HAVING SUM(ps.pt_safe_stk) > 0"
        ), 'qty', 'pt_part'));
        return [$events, $onHand, $safety];
    }

    /**
     * One item's MRP record: the detail rows by date (receipts first on the same day) with the projected on hand after
     * each, the weekly grid, totals, and its action messages (added to $messages).
     */
    private static function record(array $item, float $onhand, float $safety, array $events, array $planned, int $level,
        callable $bucket, int $columns, string $today, array &$messages): array
    {
        $rows = array_map(fn ($e) => ['date' => $e[0], 'qty' => round($e[1], 5), 'ref' => $e[2], 'kind' => $e[3]], $events);
        foreach ($planned as $p) {
            $rows[] = ['date' => $p['mrp_due_date'], 'qty' => round((float) $p['mrp_qty'], 5), 'ref' => $p['mrp_rel_date'], 'kind' => 'PLAN'];
        }
        usort($rows, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: (($b['qty'] > 0) <=> ($a['qty'] > 0)) ?: strcmp((string) $a['ref'], (string) $b['ref'])); // as plan()

        $zero = array_fill(0, $columns, 0.0);
        $grid = ['gross' => $zero, 'sched' => $zero, 'poh' => $zero, 'net' => $zero, 'receipt' => $zero, 'release' => $zero];
        $sum = ['need' => 0.0, 'coming' => 0.0, 'order' => 0.0];
        $balance = $onhand;
        foreach ($rows as &$r) {
            $balance = round($balance + $r['qty'], 5);
            $r['balance'] = $balance;
            $b = $bucket($r['date']);
            if ($r['kind'] === 'PLAN') {
                $grid['receipt'][$b] += $r['qty'];
                $grid['release'][$bucket($r['ref'])] += $r['qty'];
                $sum['order'] += $r['qty'];
            } elseif ($r['qty'] < 0) {
                $grid['gross'][$b] -= $r['qty'];
                $sum['need'] -= $r['qty'];
            } else {
                $grid['sched'][$b] += $r['qty'];
                $sum['coming'] += $r['qty'];
            }
        }
        unset($r);
        // projected on hand at the end of each week; net requirement = how much further below the safety stock (kept
        // from this week on) the week's requirements take it after the scheduled receipts — what planned orders must cover
        $short = fn (float $qty, float $keep) => max(0.0, $keep - $qty);
        $poh = $onhand;
        for ($c = 0; $c < $columns; $c++) {
            $keep = $c === 0 ? 0.0 : $safety;
            $before = $short($poh, $c <= 1 ? 0.0 : $safety);
            $grid['net'][$c] = round(max(0.0, $short($poh + $grid['sched'][$c] - $grid['gross'][$c], $keep) - $before), 5);
            $poh = round($poh + $grid['sched'][$c] + $grid['receipt'][$c] - $grid['gross'][$c], 5);
            $grid['poh'][$c] = $poh;
        }
        foreach ($grid as &$line) {
            $line = array_map(fn ($v) => round($v, 5), $line);
        }
        unset($line);

        // action messages
        $mine = [];
        $say = function (string $type, array $m) use (&$mine, $item): void {
            $mine[] = ['type' => $type, 'part' => $item['pt_part'], 'desc' => $item['pt_desc1'], 'um' => $item['pt_um']] + $m;
        };
        foreach ($planned as $p) {
            if ($p['mrp_rel_date'] <= $today) {
                $should = date('Y-m-d', strtotime($p['mrp_due_date'] . ' -' . (int) $item['pt_lead_time'] . ' days'));
                $say('RELEASE', ['kind' => $p['mrp_type'], 'ref' => '', 'qty' => (float) $p['mrp_qty'], 'date' => $should, 'to' => $p['mrp_due_date']]);
            }
        }
        $avail = array_map(fn ($r) => $r['balance'] - ($r['date'] >= $today ? $safety : 0.0), $rows);
        $since = null; // the date the available balance went below zero
        foreach ($rows as $k => $r) {
            if (in_array($r['kind'], self::RECEIPTS, true) && $since !== null && $r['date'] > $since) {
                $say('IN', ['kind' => $r['kind'], 'ref' => $r['ref'], 'qty' => $r['qty'], 'date' => $r['date'], 'to' => $since]);
            }
            $since = $avail[$k] < -self::EPS ? ($since ?? $r['date']) : null;
        }
        // from the last receipt back: not needed at all = cancel; needed only from a later week = reschedule out
        for ($k = count($rows) - 1; $k >= 0; $k--) {
            $r = $rows[$k];
            if (!in_array($r['kind'], self::RECEIPTS, true)) {
                continue;
            }
            $first = null;
            for ($j = $k; $j < count($rows) && $first === null; $j++) {
                if ($avail[$j] - $r['qty'] < -self::EPS) {
                    $first = $j;
                }
            }
            if ($first === null) {
                $say('CANCEL', ['kind' => $r['kind'], 'ref' => $r['ref'], 'qty' => $r['qty'], 'date' => $r['date'], 'to' => null]);
                for ($j = $k; $j < count($rows); $j++) {
                    $avail[$j] -= $r['qty'];
                }
            } elseif ($bucket($rows[$first]['date']) > $bucket($r['date'])) {
                $say('OUT', ['kind' => $r['kind'], 'ref' => $r['ref'], 'qty' => $r['qty'], 'date' => $r['date'], 'to' => $rows[$first]['date']]);
            }
        }
        array_push($messages, ...$mine);

        $mult = (float) $item['pt_mult_ord'];
        $step = $mult > 1 ? $mult : (float) $item['pt_pack_qty'];
        $lot = [t('production.mrp_lot_l4l')];
        if ($step > 1) {
            $lot[] = t('production.mrp_lot_step', ['qty' => Stock::fmt($step)]);
        }
        if ((float) $item['pt_min_ord'] > 0) {
            $lot[] = t('production.mrp_lot_min', ['qty' => Stock::fmt((float) $item['pt_min_ord'])]);
        }
        if ((float) $item['pt_max_ord'] > 0) {
            $lot[] = t('production.mrp_lot_max', ['qty' => Stock::fmt((float) $item['pt_max_ord'])]);
        }
        return [
            'part' => $item['pt_part'], 'desc' => $item['pt_desc1'], 'um' => $item['pt_um'], 'type' => $item['pt_part_type'] === 'M' ? 'WO' : 'PO',
            'level' => $level, 'lead' => (int) $item['pt_lead_time'], 'lot' => implode(', ', $lot), 'safety' => round($safety, 5),
            'onhand' => round($onhand, 5), 'need' => round($sum['need'], 5), 'coming' => round($sum['coming'], 5), 'order' => round($sum['order'], 5),
            'end' => $balance,
            'messages' => array_count_values(array_column($mine, 'type')),
            'grid' => $grid, 'rows' => $rows,
        ];
    }
}
