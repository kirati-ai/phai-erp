<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Accounting\Gl;
use Phai\Modules\Inventory\Stock;
use Phai\Modules\Inventory\StockLookupController;
use Phai\Modules\Sales\Supply;

/**
 * Work orders. Status: F firm (can still change, components follow the BOM),
 * R released (issue materials, record labour, receive finished goods), C closed.
 *
 * Costing: what goes in (materials at issue cost + labour hours x rate) is work in
 * progress; each receipt of finished goods takes its share of it, so the item's
 * average cost reflects what production really used.
 */
#[Program('work-orders')]
final class WoController
{
    #[Get('/work-orders')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('production.wo_title'),
            'help'    => 'work-orders',
            'config'  => [
                'api'     => '/api/work-orders',
                'page'    => '/work-orders',
                'canAdd'  => Auth::can('work-orders', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'wo_nbr', 'label' => t('production.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'wo_part', 'label' => t('production.item'), 'sort' => true],
                    ['key' => 'pt_desc1', 'label' => t('production.desc')],
                    ['key' => 'wo_qty_ord', 'label' => t('production.qty_ord'), 'type' => 'money', 'sort' => true],
                    ['key' => 'wo_qty_comp', 'label' => t('production.qty_comp'), 'type' => 'money'],
                    ['key' => 'wo_rel_date', 'label' => t('production.rel_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'wo_due_date', 'label' => t('production.due_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'wo_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Work waiting above the list: MRP planned production without an order yet, and firm orders not released. */
    public static function queues(): array
    {
        $cols = [
            ['key' => 'part', 'label' => t('production.item')],
            ['key' => 'pt_desc1', 'label' => t('production.desc')],
            ['key' => 'qty', 'label' => t('production.qty'), 'type' => 'num'],
            ['key' => 'due', 'label' => t('production.due_date'), 'type' => 'date'],
            ['key' => 'for', 'label' => t('production.for')],
        ];
        $queues = [];
        if (Auth::can('work-orders', 'add')) {
            $queues[] = [
                'title' => t('production.queue_planned'), 'api' => '/api/work-orders/planned', 'key' => 'mrp_key',
                'columns' => $cols, 'urgent' => 'late',
                'action' => ['label' => t('production.queue_make'), 'icon' => 'ti ti-file-plus', 'post' => '/api/work-orders/from-plan',
                             'body' => ['part' => '{part}', 'site' => '{mrp_site}', 'due' => '{due}'], 'open' => '/work-orders/{nbr}'],
            ];
        }
        if (Auth::can('work-orders', 'edit')) {
            $queues[] = [
                'title' => t('production.queue_release'), 'api' => '/api/work-orders/waiting', 'key' => 'wo_nbr',
                'columns' => array_merge([['key' => 'wo_nbr', 'label' => t('production.nbr'), 'link' => '/work-orders/{wo_nbr}']], $cols),
                'urgent' => 'late',
                'action' => ['label' => t('production.release'), 'icon' => 'ti ti-player-play', 'post' => '/api/work-orders/release',
                             'body' => ['nbr' => '{wo_nbr}'], 'confirm' => t('production.release_confirm')],
            ];
        }
        return $queues;
    }

    #[Describe('Planned production from the last MRP run that has no work order yet, earliest need first (late = need date passed; for = the sales order and customer, the item it goes into, or safety stock)')]
    #[Get('/api/work-orders/planned')]
    public function planned(): array
    {
        $rows = App::db()->all(
            "SELECT CONCAT(m.mrp_part, '|', m.mrp_site, '|', m.mrp_due_date) AS mrp_key, m.mrp_part AS part, pt.pt_desc1, m.mrp_site,
                    m.mrp_qty AS qty, m.mrp_rel_date, m.mrp_due_date AS due, m.mrp_due_date < CURDATE() AS late, m.mrp_ref
               FROM mrp_det m LEFT JOIN pt_mstr pt ON pt.pt_part = m.mrp_part
              WHERE m.mrp_type = 'WO' ORDER BY m.mrp_due_date, m.mrp_part LIMIT 200"
        );
        foreach ($rows as &$r) {
            $r['for'] = self::forWhat($r['mrp_ref']);
        }
        return $rows;
    }

    /** What MRP planned an order for (mrp_ref): a sales order with its customer, the item it goes into, or safety stock. */
    private static function forWhat(?string $ref): string
    {
        if ($ref === null || $ref === '') {
            return '';
        }
        if ($ref === 'Safety Stock') {
            return t('production.for_safety');
        }
        $customer = App::db()->value(
            "SELECT COALESCE(c.cm_name, '') FROM so_mstr s LEFT JOIN cm_mstr c ON c.cm_code = s.so_cm_code WHERE s.so_nbr = ?",
            [$ref]
        );
        if ($customer !== null) {
            return trim("$ref · $customer", ' ·');
        }
        return App::db()->value('SELECT 1 FROM wo_mstr WHERE wo_nbr = ?', [$ref])
            ? t('production.for_wo', ['wo' => $ref])
            : t('production.for_part', ['part' => $ref]);
    }

    /** Body: {part, site, due} — one planned row of MRP becomes firm work order(s); nbr = the first one. */
    #[Post('/api/work-orders/from-plan')]
    public function fromPlan(Request $request): array
    {
        $b = $request->body;
        $row = App::db()->one(
            "SELECT * FROM mrp_det WHERE mrp_part = ? AND mrp_site = ? AND mrp_due_date = ? AND mrp_type = 'WO'",
            [(string) ($b['part'] ?? ''), (string) ($b['site'] ?? ''), (string) ($b['due'] ?? '')]
        ) ?? throw HttpException::notFound();
        $wos = App::db()->transaction(fn (Db $db) => MrpController::makeWos($db, [$row]));
        return ['nbr' => $wos[0] ?? null, 'wos' => $wos];
    }

    #[Describe('Firm work orders not released to production yet, earliest due first (late = due date passed; for = the sales orders and customers it is matched to)')]
    #[Get('/api/work-orders/waiting')]
    public function waiting(): array
    {
        $rows = App::db()->all(
            "SELECT w.wo_nbr, w.wo_part AS part, pt.pt_desc1, w.wo_qty_ord AS qty, w.wo_rel_date, w.wo_due_date AS due,
                    w.wo_due_date < CURDATE() AS late
               FROM wo_mstr w LEFT JOIN pt_mstr pt ON pt.pt_part = w.wo_part
              WHERE w.wo_status = 'F' ORDER BY w.wo_due_date, w.wo_nbr LIMIT 200"
        );
        // the same matching as the shipment queue and the production dashboard (Supply::lines)
        $for = [];
        foreach ($rows ? Supply::lines() : [] as $l) {
            foreach ($l['docs'] as $doc) {
                $for[$doc][$l['so_nbr']] = trim($l['so_nbr'] . ' · ' . ($l['cm_name'] ?? ''), ' ·');
            }
        }
        foreach ($rows as &$r) {
            $r['for'] = implode(', ', $for[$r['wo_nbr']] ?? []);
        }
        return $rows;
    }

    /** Body: {nbr} — release from the queue (same as /api/work-orders/{nbr}/release). */
    #[Post('/api/work-orders/release'), Action('edit')]
    public function releaseQueued(Request $request): null
    {
        return $this->release((string) ($request->body['nbr'] ?? ''));
    }

    #[Get('/work-orders/new')]
    public function newPage(): string
    {
        return $this->page(null);
    }

    #[Get('/work-orders/{nbr}')]
    public function viewPage(string $nbr): string
    {
        self::head($nbr);
        return $this->page($nbr);
    }

    #[Describe('Production work orders', ['q' => 'number or part', 'status' => 'F planned, R released, C closed'])]

    #[Get('/api/work-orders')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['F', 'R', 'C'], true)) {
            $where[] = 'w.wo_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(w.wo_nbr LIKE ? OR w.wo_part LIKE ? OR pt.pt_desc1 LIKE ? OR w.wo_lot LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT w.wo_nbr, w.wo_part, pt.pt_desc1, w.wo_qty_ord, w.wo_qty_comp, w.wo_rel_date, w.wo_due_date, w.wo_status',
            'FROM wo_mstr w LEFT JOIN pt_mstr pt ON pt.pt_part = w.wo_part WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'wo_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['wo_nbr' => 'w.wo_nbr', 'wo_part' => 'w.wo_part', 'wo_qty_ord' => 'w.wo_qty_ord', 'wo_rel_date' => 'w.wo_rel_date',
             'wo_due_date' => 'w.wo_due_date', 'wo_status' => 'w.wo_status'],
            'wo_nbr'
        );
    }

    /** Everything the work order page shows. */
    #[Describe('One work order: materials issued, labor, quantities received, cost')]
    #[Get('/api/work-orders/{nbr}')]
    public function show(string $nbr): array
    {
        $head = self::head($nbr);
        $head['_editable'] = $head['wo_status'] === 'F' && Auth::can('work-orders', 'edit');
        $db = App::db();
        $comps = $db->all(
            'SELECT d.*, pt.pt_desc1, pt.pt_um, pt.pt_lot_ser, GREATEST(0, d.wod_qty_req - d.wod_qty_iss) AS remaining
               FROM wod_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.wod_part WHERE d.wod_nbr = ? ORDER BY d.wod_part',
            [$nbr]
        );
        $labor = $db->all(
            'SELECT l.*, e.emp_name, ' . self::RATE . ' AS rate, ' . self::OVH . ' AS ovh FROM wo_labor l
               LEFT JOIN emp_mstr e ON e.emp_nbr = l.wol_emp_nbr LEFT JOIN wc_mstr wc ON wc.wc_wkctr = l.wol_wkctr
              WHERE l.wol_wo_nbr = ? ORDER BY l.wol_date, l.wol_trnbr',
            [$nbr]
        );
        $receipts = $db->all(
            "SELECT tr_date, tr_loc, tr_lot, tr_chg_qty, tr_cost, tr_userid FROM tr_hist WHERE tr_nbr = ? AND tr_type = 'RCT-WO' ORDER BY tr_trnbr",
            [$nbr]
        );
        // each receipt with its QC: good, scrap, who checked
        $qcs = $db->all(
            'SELECT qc_test_nbr, qc_date, qc_lot, qc_qty_pass, qc_qty_fail, qc_result, qc_inspector FROM qc_hist WHERE qc_ref_doc = ? ORDER BY qc_date, qc_test_nbr',
            [$nbr]
        );
        // issue / return slips, each with what it moved — what the order has used so far
        $slips = $db->all('SELECT * FROM wis_mstr WHERE wis_wo_nbr = ? ORDER BY wis_nbr', [$nbr]);
        $lines = self::slipLines(array_column($slips, 'wis_nbr'));
        foreach ($slips as &$s) {
            $s['lines'] = $lines[$s['wis_nbr']] ?? [];
        }
        unset($s);
        return [
            'head' => $head, 'comps' => $comps, 'ops' => RoutingController::ops($head['wo_part']),
            'labor' => $labor, 'receipts' => $receipts, 'qcs' => $qcs, 'slips' => $slips, 'cost' => self::cost($nbr),
        ];
    }

    /** Body: {wo_part, wo_qty_ord, wo_site, wo_rel_date, wo_due_date, wo_rmks} — the lot of what it makes is the order number */
    #[Post('/api/work-orders')]
    public function create(Request $request): array
    {
        $h = self::check($request->body);
        return ['nbr' => App::db()->transaction(fn () => self::write(null, $h))];
    }

    #[Put('/api/work-orders/{nbr}')]
    public function update(string $nbr, Request $request): array
    {
        if (self::head($nbr)['wo_status'] !== 'F') {
            throw new HttpException(409, t('purchase.not_editable'));
        }
        $h = self::check($request->body);
        return ['nbr' => App::db()->transaction(fn () => self::write($nbr, $h))];
    }

    #[Post('/api/work-orders/{nbr}/release'), Action('edit')]
    public function release(string $nbr): null
    {
        self::setStatus($nbr, 'F', 'R');
        return null;
    }

    #[Post('/api/work-orders/{nbr}/close'), Action('edit')]
    public function close(string $nbr): null
    {
        App::db()->transaction(function (Db $db) use ($nbr) {
            self::setStatus($nbr, 'R', 'C');
            // Whatever is left in work in progress for this order is its production variance (ผลต่างต้นทุนการผลิต).
            $wip = Gl::acct('wip');
            $left = round((float) $db->value('SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_ref = ? AND gl_acct = ?', [$nbr, $wip]), 2);
            if ($left != 0.0) {
                Gl::post($nbr, 'WO', max(date('Y-m-d'), Gl::periodStart()), [
                    [$wip, -$left], [Gl::acct('wo_var'), $left],
                ], t('accounting.desc_wo_variance', ['nbr' => $nbr]));
            }
        });
        return null;
    }

    /** A firm order never released: remove it. */
    #[Post('/api/work-orders/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        $h = self::head($nbr);
        if ($h['wo_status'] !== 'F') {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->transaction(function (Db $db) use ($nbr, $h) {
            $db->delete('wod_det', ['wod_nbr' => $nbr]);
            $db->delete('wo_mstr', ['wo_nbr' => $nbr]);
            Audit::deleted('wo_mstr', "wo_nbr=$nbr", $h);
        });
        return null;
    }

    /**
     * A material issue slip (ใบเบิกวัตถุดิบ, type I) or return slip (ใบคืนวัตถุดิบ, type R): one MI number, its lines are the
     * stock moves (tr_hist, tr_ref = the slip). Issuing past the bill of materials needs a reason; a return goes back
     * into a lot this order took from, no more than it took, at the cost it went out at.
     * Body: {date, type: I|R, reason, lines: {row: {part, qty, loc, lot}}} — several rows (lots / locations) per component
     */
    #[Post('/api/work-orders/{nbr}/issue'), Action('edit')]
    public function issue(string $nbr, Request $request): array
    {
        $h = self::released($nbr);
        $b = $request->body;
        $c = new Check();
        $date = self::date($c, $b['date'] ?? '');
        $type = ($b['type'] ?? 'I') === 'R' ? 'R' : 'I';
        $reason = $c->text('reason', $b['reason'] ?? '', false, 255, false);
        $comps = array_column(App::db()->all(
            'SELECT d.wod_part, d.wod_qty_req, d.wod_qty_iss, pt.pt_lot_ser, pt.pt_um FROM wod_det d JOIN pt_mstr pt ON pt.pt_part = d.wod_part WHERE d.wod_nbr = ?', [$nbr]
        ), null, 'wod_part');
        $taken = self::takenByLot($nbr); // part|lot => [qty, cost] still out on the order
        $lines = [];
        $perPart = [];
        $perLot = [];
        // the key is the screen's row; without part it is the part itself
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $key => $l) {
            $part = is_array($l) ? (string) ($l['part'] ?? $key) : '';
            if (!array_key_exists($part, $comps)) {
                continue;
            }
            $f = fn (string $n) => "lines.$key.$n";
            $qty = $c->number($f('qty'), $l['qty'] ?? '', false, 0);
            if (!$qty) {
                continue;
            }
            $loc = self::loc($c, $f('loc'), $l['loc'] ?? '', $h['wo_site']);
            $needsLot = in_array($comps[$part]['pt_lot_ser'], ['L', 'S'], true);
            $lot = $c->text($f('lot'), $l['lot'] ?? '', false, 50, false);
            if ($needsLot && $lot === null) {
                $c->fail($f('lot'), t('stock.lot_required'));
            }
            $lot = $needsLot ? (string) $lot : '';
            $lines[$key] = ['part' => $part, 'qty' => $qty, 'loc' => $loc, 'lot' => $lot];
            $perPart[$part] = ($perPart[$part] ?? 0) + $qty;
            $perLot["$part|$lot"] = ($perLot["$part|$lot"] ?? 0) + $qty;
            if ($type === 'R' && $perLot["$part|$lot"] > ($taken["$part|$lot"]['qty'] ?? 0) + 0.000001) {
                $c->fail($f('qty'), t('production.return_over', ['qty' => Stock::fmt($taken["$part|$lot"]['qty'] ?? 0) . ' ' . $comps[$part]['pt_um']]));
            }
        }
        if (!$lines && !$c->errors) {
            $c->fail('lines', t('production.nothing_issued'));
        }
        // more than the bill of materials still needs is a decision someone owns: say why
        if ($type === 'I' && $reason === null) {
            foreach ($perPart as $part => $qty) {
                if ((float) $comps[$part]['wod_qty_iss'] + $qty > (float) $comps[$part]['wod_qty_req'] + 0.000001) {
                    $c->fail('reason', t('production.over_issue', ['part' => $part]));
                    break;
                }
            }
        }
        $c->done();

        $slip = App::db()->transaction(function (Db $db) use ($nbr, $h, $date, $lines, $type, $reason, $taken) {
            $slip = Numbering::next('MI', $date);
            $sign = $type === 'R' ? 1 : -1;
            $n = (int) $db->value("SELECT COUNT(*) FROM tr_hist WHERE tr_nbr = ? AND tr_type = 'ISS-WO'", [$nbr]);
            foreach ($lines as $key => $l) {
                $part = $l['part'];
                Stock::move([
                    'type' => 'ISS-WO', 'part' => $part, 'site' => $h['wo_site'], 'loc' => $l['loc'], 'lot' => $l['lot'],
                    'qty' => $sign * $l['qty'], 'date' => $date, 'nbr' => $nbr, 'line' => ++$n, 'remark' => $h['wo_part'], 'ref' => $slip,
                    // back into stock at what it went out at, so work in progress loses exactly that
                    'cost' => $type === 'R' ? ($taken[$part . '|' . $l['lot']]['cost'] ?? null) : null,
                    'program' => 'work-orders', 'field' => "lines.$key.qty",
                ]);
                $db->run(
                    'UPDATE wod_det SET wod_qty_iss = wod_qty_iss + ?, wod_loc = ?, updated_by = ? WHERE wod_nbr = ? AND wod_part = ?',
                    [-$sign * $l['qty'], $l['loc'], Auth::userId(), $nbr, $part]
                );
            }
            $row = [
                'wis_nbr' => $slip, 'wis_wo_nbr' => $nbr, 'wis_type' => $type, 'wis_date' => $date, 'wis_reason' => $reason,
                'wis_amt' => round((float) $db->value('SELECT COALESCE(SUM(ABS(tr_chg_qty) * tr_cost), 0) FROM tr_hist WHERE tr_ref = ?', [$slip]), 2),
                'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
            ];
            $db->insert('wis_mstr', $row);
            Audit::created('wis_mstr', "wis_nbr=$slip", $row);
            return $slip;
        });
        return $this->show($nbr) + ['slip' => $slip];
    }

    /**
     * Cancel a slip while the order is still running: every line goes the other way (same lot, location and cost, the same
     * tr_ref so what the order holds nets out), the issued quantities follow, the slip stays with status X.
     */
    #[Post('/api/work-orders/issues/{slip}/cancel'), Action('edit')]
    public function cancelSlip(string $slip): array
    {
        $s = App::db()->one('SELECT * FROM wis_mstr WHERE wis_nbr = ?', [$slip]) ?? throw HttpException::notFound();
        $h = self::released($s['wis_wo_nbr']);
        if ($s['wis_status'] !== 'A') {
            throw new HttpException(409, t('production.slip_already_cancelled'));
        }
        App::db()->transaction(function (Db $db) use ($s, $h, $slip) {
            $db->one('SELECT wis_nbr FROM wis_mstr WHERE wis_nbr = ? FOR UPDATE', [$slip]);
            $date = max(date('Y-m-d'), Gl::periodStart());
            $n = (int) $db->value("SELECT COUNT(*) FROM tr_hist WHERE tr_nbr = ? AND tr_type = 'ISS-WO'", [$s['wis_wo_nbr']]);
            foreach ($db->all('SELECT * FROM tr_hist WHERE tr_ref = ? ORDER BY tr_trnbr', [$slip]) as $l) {
                $back = -(float) $l['tr_chg_qty'];
                Stock::move([
                    'type' => 'ISS-WO', 'part' => $l['tr_part'], 'site' => $l['tr_site'], 'loc' => $l['tr_loc'], 'lot' => $l['tr_lot'],
                    'qty' => $back, 'cost' => $back > 0 ? (float) $l['tr_cost'] : null, 'date' => $date, 'nbr' => $s['wis_wo_nbr'],
                    'line' => ++$n, 'remark' => t('production.slip_cancelled') . " $slip", 'ref' => $slip, 'program' => 'work-orders',
                ]);
                $db->run(
                    'UPDATE wod_det SET wod_qty_iss = wod_qty_iss + ?, updated_by = ? WHERE wod_nbr = ? AND wod_part = ?',
                    [(float) $l['tr_chg_qty'], Auth::userId(), $s['wis_wo_nbr'], $l['tr_part']]
                );
            }
            $db->update('wis_mstr', ['wis_status' => 'X', 'updated_by' => Auth::userId()], ['wis_nbr' => $slip]);
            Audit::updated('wis_mstr', "wis_nbr=$slip", $s, ['wis_status' => 'X']);
        });
        return $this->show($s['wis_wo_nbr']);
    }

    /** Material issue / return slip on A4: what was taken from (or given back to) which lot and location, for signing. */
    #[Get('/work-orders/issues/{nbr}/print'), Action('print')]
    public function printSlip(string $nbr): string
    {
        $s = App::db()->one(
            'SELECT s.*, w.wo_part, w.wo_qty_ord, w.wo_site, pt.pt_desc1, pt.pt_um FROM wis_mstr s JOIN wo_mstr w ON w.wo_nbr = s.wis_wo_nbr
               LEFT JOIN pt_mstr pt ON pt.pt_part = w.wo_part WHERE s.wis_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
        $lines = self::slipLines([$nbr])[$nbr] ?? [];
        $q = fn ($v) => Stock::fmt((float) $v);
        $m = fn ($v) => number_format((float) $v, 2);
        return \Phai\Modules\Sales\SalesPrint::render([
            'title' => t($s['wis_type'] === 'R' ? 'production.return_slip' : 'production.issue_slip'), 'nbr' => $nbr,
            'meta'  => [[t('production.date'), \Phai\Modules\Sales\SalesPrint::date($s['wis_date'])], [t('production.wo'), $s['wis_wo_nbr']],
                [t('production.item'), $s['wo_part'] . ' ' . ($s['pt_desc1'] ?? '') . ' × ' . $q($s['wo_qty_ord']) . ' ' . ($s['pt_um'] ?? '')],
                [t('production.site'), $s['wo_site']], [t('production.reason'), $s['wis_reason'] ?? ''],
                [t('common.status'), $s['wis_status'] === 'X' ? t('production.slip_cancelled') : '']],
            'lines' => ['columns' => [
                ['label' => '#', 'width' => '28px'], ['label' => t('production.component')], ['label' => t('production.loc')], ['label' => t('production.lot')],
                ['label' => t('production.qty'), 'align' => 'r'], ['label' => t('stock.um')], ['label' => t('production.unit_cost'), 'align' => 'r'],
                ['label' => t('accounting.amount'), 'align' => 'r', 'width' => '100px']],
                'rows' => array_map(fn ($i, $l) => [$i + 1, $l['tr_part'] . ' ' . ($l['pt_desc1'] ?? ''), $l['tr_loc'], $l['tr_lot'], $q(abs((float) $l['tr_chg_qty'])),
                    $l['tr_um'], number_format((float) $l['tr_cost'], 2), $m(abs((float) $l['tr_chg_qty']) * (float) $l['tr_cost'])], array_keys($lines), $lines)],
            'totals' => ['rows' => [[t('sales.total'), $m($s['wis_amt']), true]], 'words' => ''],
            'signs' => $s['wis_type'] === 'R'
                ? [t('production.sign_returner'), t('production.sign_storekeeper')]
                : [t('production.sign_requester'), t('production.sign_storekeeper'), \Phai\Modules\Sales\SalesPrint::approve(t('production.sign_approver'))],
        ]);
    }

    /** What the order still has out of each lot: part|lot => [qty, cost (what it went out at, per unit)]. */
    private static function takenByLot(string $nbr): array
    {
        $out = [];
        foreach (App::db()->all(
            "SELECT tr_part, tr_lot, SUM(-tr_chg_qty) AS qty, SUM(-tr_chg_qty * tr_cost) AS amt FROM tr_hist
              WHERE tr_nbr = ? AND tr_type = 'ISS-WO' GROUP BY tr_part, tr_lot",
            [$nbr]
        ) as $r) {
            $out[$r['tr_part'] . '|' . $r['tr_lot']] = ['qty' => (float) $r['qty'], 'cost' => (float) $r['qty'] > 0 ? round((float) $r['amt'] / (float) $r['qty'], 5) : 0.0];
        }
        return $out;
    }

    /** The stock moves of slips: slip => lines (what each slip moved; the lines that undid a cancelled slip are left out). */
    private static function slipLines(array $slips): array
    {
        if (!$slips) {
            return [];
        }
        $out = [];
        foreach (App::db()->all(
            'SELECT t.tr_ref, t.tr_part, pt.pt_desc1, t.tr_loc, t.tr_lot, t.tr_chg_qty, t.tr_um, t.tr_cost FROM tr_hist t
               JOIN wis_mstr s ON s.wis_nbr = t.tr_ref LEFT JOIN pt_mstr pt ON pt.pt_part = t.tr_part
              WHERE t.tr_ref IN (' . implode(',', array_fill(0, count($slips), '?')) . ') AND (t.tr_chg_qty < 0) = (s.wis_type = \'I\') ORDER BY t.tr_trnbr',
            array_values($slips)
        ) as $l) {
            $out[$l['tr_ref']][] = $l;
        }
        return $out;
    }

    /** Body: {date, qty (good), scrap, loc, lot, expire} */
    #[Post('/api/work-orders/{nbr}/receive'), Action('edit')]
    public function receive(string $nbr, Request $request): array
    {
        $h = self::released($nbr);
        $b = $request->body;
        $c = new Check();
        $date = self::date($c, $b['date'] ?? '');
        $qty = $c->number('qty', $b['qty'] ?? '', false, 0) ?? 0.0;
        $scrap = $c->number('scrap', $b['scrap'] ?? '', false, 0) ?? 0.0;
        if ($qty <= 0 && $scrap <= 0 && $c->ok('qty') && $c->ok('scrap')) {
            $c->fail('qty', t('validation.positive'));
        }
        $loc = $qty > 0 ? self::loc($c, 'loc', $b['loc'] ?? '', $h['wo_site']) : null;
        $lot = '';
        if (in_array($h['pt_lot_ser'], ['L', 'S'], true)) {
            $lot = $h['wo_lot'] ?: $nbr; // the order number (wo_lot follows a lot renamed later)
        }
        $expire = $c->date('expire', $b['expire'] ?? '');
        // who checked what came off the line: good goes into stock, scrap does not — both are on the QC record
        $inspector = $c->text('inspector', $b['inspector'] ?? '', false, 50, false) ?? Auth::personName();
        $c->done();

        App::db()->transaction(function (Db $db) use ($nbr, $date, $qty, $scrap, $loc, $lot, $expire, $inspector) {
            $w = $db->one('SELECT * FROM wo_mstr WHERE wo_nbr = ? FOR UPDATE', [$nbr]);
            // no more off the line than was ordered: the materials were issued for that many
            $left = round((float) $w['wo_qty_ord'] - (float) $w['wo_qty_comp'] - (float) $w['wo_qty_rjct'], 5);
            if ($qty + $scrap > $left + 0.000001) {
                throw HttpException::invalid([$qty > 0 ? 'qty' : 'scrap' => t('production.receive_over', ['left' => Stock::fmt(max(0, $left))])]);
            }
            self::backflush($nbr, $w, $qty + $scrap, $date);
            if ($qty > 0) {
                // Good units go in at the standard cost of one (bill of materials at what was issued + routing hours);
                // what the order spends beyond that stays in work in progress and is the variance when it closes.
                $std = self::standard($nbr)['total'];
                if ($std <= 0) { // nothing to measure against: this receipt's share of the work in progress
                    $cost = self::cost($nbr);
                    $expected = max((float) $w['wo_qty_ord'] - (float) $w['wo_qty_comp'], $qty);
                    $std = $cost['wip'] > 0 ? $cost['wip'] / $expected : (float) Stock::part($w['wo_part'])['pt_cost'];
                }
                $unit = round($std, 5);
                $line = (int) $db->value("SELECT COUNT(*) FROM tr_hist WHERE tr_nbr = ? AND tr_type = 'RCT-WO'", [$nbr]);
                Stock::move([
                    'type' => 'RCT-WO', 'part' => $w['wo_part'], 'site' => $w['wo_site'], 'loc' => $loc, 'lot' => $lot,
                    'qty' => $qty, 'cost' => $unit, 'expire' => $expire, 'date' => $date, 'nbr' => $nbr, 'line' => $line + 1,
                    'program' => 'work-orders',
                ]);
            }
            $db->run(
                'UPDATE wo_mstr SET wo_qty_comp = wo_qty_comp + ?, wo_qty_rjct = wo_qty_rjct + ?, wo_lot = COALESCE(NULLIF(wo_lot, \'\'), NULLIF(?, \'\')), updated_by = ?
                  WHERE wo_nbr = ?',
                [$qty, $scrap, $lot, Auth::userId(), $nbr]
            );
            $db->insert('qc_hist', [
                'qc_test_nbr' => Numbering::next('QC', $date), 'qc_ref_doc' => $nbr, 'qc_part' => $w['wo_part'], 'qc_lot' => $lot,
                'qc_qty_inspect' => $qty + $scrap, 'qc_qty_pass' => $qty, 'qc_qty_fail' => $scrap,
                'qc_result' => $scrap <= 0 ? 'PASS' : ($qty <= 0 ? 'FAIL' : 'PARTIAL'),
                'qc_inspector' => $inspector, 'qc_date' => $date, 'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
            ]);
        });
        return $this->show($nbr);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Direct labour and manufacturing overhead into work in progress (sign 1), or out again when a line is deleted (sign -1):
     * Dr WIP / Cr direct labour (hours x the labour rate) / Cr applied overhead (hours x the work centre's machine + burden rate),
     * so a work order costs material + labour + overhead, the same three parts as the product's standard cost.
     */
    private static function postLabor(string $nbr, array $row, int $id, int $sign, string $date): void
    {
        // a line booked by itself (backflush) has no employee: the work centre's rate
        $r = App::db()->one(
            'SELECT ' . self::RATE . ' AS rate, ' . self::OVH . ' AS ovh FROM wc_mstr wc LEFT JOIN emp_mstr e ON e.emp_nbr = ? WHERE wc.wc_wkctr = ?',
            [$row['wol_emp_nbr'], $row['wol_wkctr']]
        ) ?? ['rate' => 0, 'ovh' => 0];
        $labor = round((float) $row['wol_hours'] * (float) $r['rate'], 2) * $sign;
        $overhead = round((float) $row['wol_hours'] * (float) $r['ovh'], 2) * $sign;
        Gl::post($nbr, 'WO', $date, [
            [Gl::acct('wip'), $labor + $overhead], [Gl::acct('labor'), -$labor], [Gl::acct('ovh'), -$overhead],
        ], t('accounting.desc_labor', ['id' => $id]));
    }

    /**
     * Labour without anyone typing it: every routing step's standard hours for what came off the line (good + scrap),
     * its set-up time once (with the first receipt), at the work centre's rates (wol_auto = 1). Typed lines only adjust.
     */
    private static function backflush(string $nbr, array $w, float $made, string $date): void
    {
        if ($made <= 0) {
            return;
        }
        $db = App::db();
        $first = !$db->value('SELECT 1 FROM wo_labor WHERE wol_wo_nbr = ? AND wol_auto = 1 LIMIT 1', [$nbr]);
        foreach (RoutingController::ops($w['wo_part']) as $op) {
            $hours = round((float) $op['ro_run_time'] * $made + ($first ? (float) $op['ro_setup_time'] : 0), 4);
            if ($hours <= 0) {
                continue;
            }
            $row = ['wol_date' => $date, 'wol_op' => (int) $op['ro_op'], 'wol_emp_nbr' => '', 'wol_wkctr' => $op['ro_wkctr'], 'wol_hours' => $hours,
                'wol_qty_comp' => 0, 'wol_qty_rjct' => 0, 'wol_rjct_rsn' => null, 'wol_auto' => 1];
            $id = $db->insert('wo_labor', $row + ['wol_wo_nbr' => $nbr, 'created_by' => Auth::userId(), 'updated_by' => Auth::userId()]);
            self::postLabor($nbr, $row, (int) $id, 1, $date);
        }
    }

    /**
     * Standard cost of one unit: the bill of materials (per unit of the order) at what each component was issued at
     * (its current cost while none is issued) + routing hours (run + set-up spread over the order) at the work centres'
     * labour and overhead rates. @return array{material: float, labor: float, overhead: float, total: float}
     */
    public static function standard(string $nbr): array
    {
        $db = App::db();
        $w = self::head($nbr);
        $per = max((float) $w['wo_qty_ord'], 0.000001);
        $material = 0.0;
        foreach ($db->all(
            "SELECT d.wod_part, d.wod_qty_req, pt.pt_cost,
                    (SELECT SUM(-t.tr_chg_qty * t.tr_cost) / NULLIF(SUM(-t.tr_chg_qty), 0) FROM tr_hist t
                      WHERE t.tr_nbr = d.wod_nbr AND t.tr_part = d.wod_part AND t.tr_type = 'ISS-WO') AS issued_cost
               FROM wod_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.wod_part WHERE d.wod_nbr = ?",
            [$nbr]
        ) as $c) {
            $material += (float) $c['wod_qty_req'] / $per * (float) ($c['issued_cost'] ?? $c['pt_cost']);
        }
        $labor = 0.0;
        $overhead = 0.0;
        foreach ($db->all(
            'SELECT ro.ro_run_time, ro.ro_setup_time, COALESCE(wc.wc_lbr_rate, 0) AS rate, ' . self::OVH . ' AS ovh
               FROM ro_mstr ro LEFT JOIN wc_mstr wc ON wc.wc_wkctr = ro.ro_wkctr WHERE ro.ro_routing = ?',
            [$w['wo_part']]
        ) as $op) {
            $hours = (float) $op['ro_run_time'] + (float) $op['ro_setup_time'] / $per;
            $labor += $hours * (float) $op['rate'];
            $overhead += $hours * (float) $op['ovh'];
        }
        return ['material' => round($material, 5), 'labor' => round($labor, 5), 'overhead' => round($overhead, 5),
            'total' => round($material + $labor + $overhead, 5)];
    }

    /** Labour rate: the employee's own rate, else the work centre's. */
    private const RATE = 'COALESCE(NULLIF(e.emp_rate, 0), wc.wc_lbr_rate, 0)';
    /** Overhead per hour of the work centre: its machines and its burden. */
    private const OVH = '(COALESCE(wc.wc_mch_rate, 0) + COALESCE(wc.wc_bdn_rate, 0))';

    /** @return array{material: float, labor: float, overhead: float, received: float, wip: float} in baht */
    public static function cost(string $nbr): array
    {
        $db = App::db();
        $material = (float) $db->value("SELECT COALESCE(SUM(-tr_chg_qty * tr_cost), 0) FROM tr_hist WHERE tr_nbr = ? AND tr_type = 'ISS-WO'", [$nbr]);
        $received = (float) $db->value("SELECT COALESCE(SUM(tr_chg_qty * tr_cost), 0) FROM tr_hist WHERE tr_nbr = ? AND tr_type = 'RCT-WO'", [$nbr]);
        $hours = $db->one(
            'SELECT COALESCE(SUM(l.wol_hours * ' . self::RATE . '), 0) AS labor, COALESCE(SUM(l.wol_hours * ' . self::OVH . '), 0) AS overhead
               FROM wo_labor l LEFT JOIN emp_mstr e ON e.emp_nbr = l.wol_emp_nbr LEFT JOIN wc_mstr wc ON wc.wc_wkctr = l.wol_wkctr
              WHERE l.wol_wo_nbr = ?',
            [$nbr]
        );
        $labor = (float) $hours['labor'];
        $overhead = (float) $hours['overhead'];
        $wip = round($material + $labor + $overhead - $received, 2);
        // standard for what came out good, and the difference: what closing the order now books as production variance
        // (labour and overhead against their standard; the rest — material used beyond the bill, scrap, prices — is material)
        $std = self::standard($nbr);
        $done = (float) App::db()->value('SELECT wo_qty_comp FROM wo_mstr WHERE wo_nbr = ?', [$nbr]);
        $stdDone = ['material' => round($std['material'] * $done, 2), 'labor' => round($std['labor'] * $done, 2), 'overhead' => round($std['overhead'] * $done, 2)];
        $var = ['labor' => round($labor - $stdDone['labor'], 2), 'overhead' => round($overhead - $stdDone['overhead'], 2)];
        $var['material'] = round($wip - $var['labor'] - $var['overhead'], 2);
        return [
            'material' => round($material, 2), 'labor' => round($labor, 2), 'overhead' => round($overhead, 2), 'received' => round($received, 2),
            'wip' => $wip, 'std' => $std, 'std_done' => $stdDone, 'variance' => $var + ['total' => $wip], 'done' => $done,
        ];
    }

    private static function check(array $b): array
    {
        $c = new Check();
        $h = [
            'wo_part'     => $c->exists('wo_part', $b['wo_part'] ?? '', 'pt_mstr', 'pt_part'),
            'wo_qty_ord'  => $c->number('wo_qty_ord', $b['wo_qty_ord'] ?? '', true, 0, true),
            'wo_site'     => $c->exists('wo_site', $b['wo_site'] ?? '', 'si_mstr', 'si_site', true, 'si_status = 1'),
            'wo_rel_date' => $c->date('wo_rel_date', $b['wo_rel_date'] ?? '', true),
            'wo_due_date' => $c->date('wo_due_date', $b['wo_due_date'] ?? '', true),
            'wo_rmks'     => $c->text('wo_rmks', $b['wo_rmks'] ?? '', false, 2000, false),
        ];
        if ($h['wo_rel_date'] && $h['wo_due_date'] && $h['wo_due_date'] < $h['wo_rel_date']) {
            $c->fail('wo_due_date', t('production.due_before_release'));
        }
        $c->done();
        return $h;
    }

    /** Insert or replace a firm order; components follow the BOM for the quantity. */
    public static function write(?string $nbr, array $h): string
    {
        $db = App::db();
        $user = Auth::userId();
        $h['wo_bom_code'] = $db->value('SELECT 1 FROM ps_mstr WHERE ps_par = ? LIMIT 1', [$h['wo_part']]) ? $h['wo_part'] : null;
        $h['wo_routing'] = $db->value('SELECT 1 FROM ro_mstr WHERE ro_routing = ? LIMIT 1', [$h['wo_part']]) ? $h['wo_part'] : null;
        if ($nbr === null) {
            $nbr = Numbering::next('WO', $h['wo_rel_date'] ?? null);
            $db->insert('wo_mstr', ['wo_nbr' => $nbr, 'wo_lot' => $nbr, 'wo_status' => 'F', 'created_by' => $user, 'updated_by' => $user] + $h);
        } else {
            $db->update('wo_mstr', $h + ['updated_by' => $user], ['wo_nbr' => $nbr]);
            $db->delete('wod_det', ['wod_nbr' => $nbr]);
        }
        $loc = $db->value('SELECT si_default_loc FROM si_mstr WHERE si_site = ?', [$h['wo_site']]);
        foreach (Bom::explode($h['wo_part'], (float) $h['wo_qty_ord'], $h['wo_rel_date']) as $comp) {
            $db->insert('wod_det', [
                'wod_nbr' => $nbr, 'wod_part' => $comp['part'], 'wod_qty_req' => $comp['qty'], 'wod_qty_iss' => 0,
                'wod_loc' => $loc, 'created_by' => $user, 'updated_by' => $user,
            ]);
        }
        return $nbr;
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT w.*, pt.pt_desc1, pt.pt_um, pt.pt_lot_ser, si.si_desc FROM wo_mstr w
               LEFT JOIN pt_mstr pt ON pt.pt_part = w.wo_part LEFT JOIN si_mstr si ON si.si_site = w.wo_site
              WHERE w.wo_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    private static function released(string $nbr): array
    {
        $h = self::head($nbr);
        if ($h['wo_status'] !== 'R') {
            throw new HttpException(409, t('production.not_released'));
        }
        return $h;
    }

    private static function setStatus(string $nbr, string $from, string $to): void
    {
        $h = self::head($nbr);
        if ($h['wo_status'] !== $from) {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->update('wo_mstr', ['wo_status' => $to, 'updated_by' => Auth::userId()], ['wo_nbr' => $nbr]);
        Audit::updated('wo_mstr', "wo_nbr=$nbr", $h, ['wo_status' => $to]);
    }

    private static function date(Check $c, mixed $v, string $field = 'date'): ?string
    {
        $d = $c->date($field, $v, true);
        if ($d && $d > date('Y-m-d')) {
            $c->fail($field, t('stock.future_date'));
        }
        return $d;
    }

    private static function loc(Check $c, string $field, mixed $v, string $site): ?string
    {
        return $c->exists($field, $v, 'loc_mstr', 'loc_loc', true, 'loc_site = ' . App::db()->pdo->quote($site));
    }

    private function page(?string $nbr): string
    {
        return View::render('production/wo', [
            'docNbr' => $nbr === null ? Numbering::peek('WO') : null,
            'title'   => $nbr ?? t('production.wo_new'),
            'help'    => 'work-orders',
            'ownToolbar' => true,
            'config'  => [
                'nbr'       => $nbr,
                'status'    => self::statusText(),
                'canAdd'    => Auth::can('work-orders', 'add'),
                'canEdit'   => Auth::can('work-orders', 'edit'),
                'canDelete' => Auth::can('work-orders', 'delete'),
                'canPrint'  => Auth::can('work-orders', 'print'),
                'printUrl'  => $nbr !== null && Auth::can('work-orders', 'print') ? '/documents/work-orders/' . rawurlencode($nbr) . '/print' : null,
                'sites'     => StockLookupController::sites(),
                'meName'    => Auth::personName(),                   // receipts are checked by the one signed in
                'qcResults' => array_combine(QcResource::RESULTS, array_map(fn ($r) => t("fields.qc_result.$r"), QcResource::RESULTS)),
                'today'     => date('Y-m-d'),
            ],
            'scripts' => ['js/pages/wo.js'],
        ]);
    }

    public static function statusText(): array
    {
        return [
            'F' => ['text' => t('production.status_F'), 'color' => 'yellow'],
            'R' => ['text' => t('production.status_R'), 'color' => 'blue'],
            'C' => ['text' => t('production.status_C'), 'color' => 'green'],
        ];
    }
}
