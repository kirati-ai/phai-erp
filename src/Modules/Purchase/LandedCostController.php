<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Accounting\Gl;
use Phai\Modules\Master\Money;

/**
 * Freight, customs clearance, duty, insurance and other costs of a receipt (lc_mstr, in baht): as many lines
 * as there are bills (Air Waybill, B/L, import entry, ...), each with its document number and the vendor who charges it.
 * Allocating spreads them over the receipt lines by value and adds them to the
 * average cost of the items still on hand. Allocated costs cannot be changed.
 */
#[Program('landed-costs')]
final class LandedCostController
{
    public const TYPES = ['FREIGHT', 'CLEAR', 'DUTY', 'INSURANCE', 'OTHER'];

    #[Get('/landed-costs')]
    public function page(): string
    {
        return View::render('purchase/landed', [
            'help'    => 'landed-costs',
            'title'   => t('purchase.lc_title'),
            'queues'  => self::queues(),
            'scripts' => ['js/pages/landed.js'],
        ]);
    }

    /** Receipts of foreign-currency orders with no landed cost yet, above the page: one click opens the receipt's landed costs. */
    public static function queues(): array
    {
        return Auth::can('landed-costs', 'edit') ? [[
            'title'   => t('purchase.queue_lc'),
            'api'     => '/api/landed-costs/waiting',
            'key'     => 'rc_nbr',
            'columns' => [
                ['key' => 'rc_nbr', 'label' => t('purchase.receipt'), 'link' => '/receipts/{rc_nbr}'],
                ['key' => 'rc_date', 'label' => t('purchase.date'), 'type' => 'date'],
                ['key' => 'rc_po_nbr', 'label' => t('purchase.po')],
                ['key' => 'rc_vd_code', 'label' => t('purchase.vendor')],
                ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                ['key' => 'po_curr', 'label' => t('purchase.currency')],
                ['key' => 'rc_net_amt', 'label' => t('purchase.total_thb'), 'type' => 'money'],
            ],
            'action'  => ['label' => t('purchase.queue_add_lc'), 'icon' => 'ti ti-coins', 'href' => '/landed-costs/{rc_nbr}'],
        ]] : [];
    }

    /** One receipt's landed costs as a view like a purchase order (ux/ui.md "View pages"). */
    #[Get('/landed-costs/{rc}')]
    public function view(string $rc): string
    {
        return View::render('purchase/landed-view', [
            'help'    => 'landed-costs',
            'title'      => t('purchase.lc_title') . ' ' . $rc,
            'ownToolbar' => true,
            'head'       => ReceiptController::head($rc),
            'canPrint'   => Auth::can('landed-costs', 'print'),
            'config'     => [
                'rc'          => $rc,
                'types'       => array_combine(self::TYPES, array_map(fn ($t) => t('purchase.lc_' . $t), self::TYPES)),
                'canEdit'     => Auth::can('landed-costs', 'edit'),
                'canAllocate' => Auth::can('landed-costs', 'edit'),
            ],
            'scripts'    => ['js/pages/landed-view.js'],
        ]);
    }

    /** Import receipts (POs in a foreign currency — a baht purchase has no import costs) with their landed cost total. ?q= &sort= &dir= &page= */
    #[Describe('Import goods receipts (foreign-currency purchase orders) with their landed costs (freight, customs clearance, duty, ...)', ['q' => 'receipt or PO'])]
    #[Get('/api/landed-costs')]
    public function list(Request $request): array
    {
        $where = ['r.rc_curr <> ?'];
        $params = [Money::base()];
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(r.rc_nbr LIKE ? OR r.rc_po_nbr LIKE ? OR r.rc_vd_code LIKE ? OR v.vd_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT r.rc_nbr, r.rc_date, r.rc_po_nbr, r.rc_vd_code, v.vd_name, r.rc_curr, r.rc_net_amt,
                    (SELECT COALESCE(SUM(lc_amt), 0) FROM lc_mstr l WHERE l.lc_rc_nbr = r.rc_nbr) AS lc_total,
                    (SELECT MIN(lc_allocated) FROM lc_mstr l WHERE l.lc_rc_nbr = r.rc_nbr) AS lc_allocated',
            'FROM rc_mstr r LEFT JOIN vd_mstr v ON v.vd_code = r.rc_vd_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'rc_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['rc_nbr' => 'r.rc_nbr', 'rc_date' => 'r.rc_date', 'rc_net_amt' => 'r.rc_net_amt'],
            'rc_nbr'
        );
    }

    /** Receipts of foreign-currency POs with no landed cost allocated yet, oldest first. */
    #[Describe('Goods receipts of purchase orders in a foreign currency with no landed cost (freight, duty, ...) allocated yet, oldest first (amount in baht)')]
    #[Get('/api/landed-costs/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            'SELECT r.rc_nbr, r.rc_date, r.rc_po_nbr, r.rc_vd_code, v.vd_name, p.po_curr, r.rc_net_amt
               FROM rc_mstr r JOIN po_mstr p ON p.po_nbr = r.rc_po_nbr LEFT JOIN vd_mstr v ON v.vd_code = r.rc_vd_code
              WHERE p.po_curr <> ? AND NOT EXISTS (SELECT 1 FROM lc_mstr l WHERE l.lc_rc_nbr = r.rc_nbr AND l.lc_allocated = 1)
              ORDER BY r.rc_date, r.rc_nbr LIMIT 200',
            [Money::base()]
        );
    }

    /** {head, lines: [{part, desc, qty, value}], costs: [{line, type, ref, vendor, amount, ap_ref, allocated}]} */
    #[Get('/api/landed-costs/{rc}')]
    public function show(string $rc): array
    {
        $head = ReceiptController::head($rc);
        $lines = App::db()->all(
            'SELECT d.rcd_line, d.rcd_part, pt.pt_desc1, d.rcd_qty_rcvd, ROUND(d.rcd_line_amt * ?, 2) AS value
               FROM rcd_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.rcd_part WHERE d.rcd_nbr = ? ORDER BY d.rcd_line',
            [(float) $head['rc_ex_rate'], $rc]
        );
        $costs = array_map(fn ($r) => [
            'line' => (int) $r['lc_line'], 'type' => $r['lc_cost_type'], 'ref' => $r['lc_ref'], 'vendor' => $r['lc_vd_code'],
            'amount' => (float) $r['lc_amt'], 'ap_ref' => $r['lc_ap_ref'], 'allocated' => (bool) $r['lc_allocated'],
        ], App::db()->all('SELECT * FROM lc_mstr WHERE lc_rc_nbr = ? ORDER BY lc_line', [$rc]));
        // once a vendor has billed any of the costs (a payable), the receipt's landed costs are closed
        $billed = (bool) array_filter(array_column($costs, 'ap_ref'));
        return ['head' => $head, 'lines' => $lines, 'costs' => $costs, 'billed' => $billed];
    }

    /**
     * Body: {lines: {<row>: {type, ref, vendor, amount}}} — the lines not allocated yet (allocated ones are locked
     * and left as they are); a line with no amount is dropped. Allocating credits GRNI; each line's vendor then
     * bills it as a payable (lc_ap_ref), which debits GRNI back.
     */
    #[Put('/api/landed-costs/{rc}')]
    public function save(string $rc, Request $request): array
    {
        self::import($rc);
        if (App::db()->value('SELECT 1 FROM lc_mstr WHERE lc_rc_nbr = ? AND lc_ap_ref IS NOT NULL', [$rc])) {
            throw new HttpException(422, t('purchase.lc_billed'));
        }
        $c = new Check();
        $set = [];
        foreach ((array) ($request->body['lines'] ?? []) as $row => $l) {
            $f = fn ($n) => "lines.$row.$n";
            $amount = $c->number($f('amount'), $l['amount'] ?? '', false, 0, true, 2);
            if (!$amount) {
                continue;
            }
            $type = (string) ($l['type'] ?? '');
            if (!in_array($type, self::TYPES, true)) {
                $c->fail($f('type'), t('validation.required'));
            }
            $set[] = [
                'type'   => $type,
                'ref'    => $c->text($f('ref'), $l['ref'] ?? '', false, 50),
                'vendor' => $c->exists($f('vendor'), $l['vendor'] ?? '', 'vd_mstr', 'vd_code'),
                'amount' => $amount,
            ];
        }
        $c->done();

        App::db()->transaction(function (Db $db) use ($rc, $set) {
            $user = Auth::userId();
            $db->run('DELETE FROM lc_mstr WHERE lc_rc_nbr = ? AND lc_allocated = 0', [$rc]);
            $line = (int) $db->value('SELECT COALESCE(MAX(lc_line), 0) FROM lc_mstr WHERE lc_rc_nbr = ?', [$rc]);
            foreach ($set as $v) {
                $db->insert('lc_mstr', [
                    'lc_rc_nbr' => $rc, 'lc_line' => ++$line, 'lc_cost_type' => $v['type'], 'lc_ref' => $v['ref'], 'lc_vd_code' => $v['vendor'],
                    'lc_amt' => $v['amount'], 'lc_ap_ref' => null, 'lc_allocated' => 0, 'created_by' => $user, 'updated_by' => $user,
                ]);
            }
        });
        return $this->show($rc);
    }

    /** The receipt, refused when it is not an import (bought in baht: nothing to land). */
    private static function import(string $rc): array
    {
        $head = ReceiptController::head($rc);
        if (($head['rc_curr'] ?? Money::base()) === Money::base()) {
            throw new HttpException(422, t('purchase.lc_not_import'));
        }
        return $head;
    }

    /** Spread the not-yet-allocated costs over the receipt lines by value and raise item costs. */
    #[Post('/api/landed-costs/{rc}/allocate'), Action('edit')]
    public function allocate(string $rc): array
    {
        $data = $this->show($rc);
        $amount = 0.0;
        foreach ($data['costs'] as $cost) {
            $amount += $cost['allocated'] ? 0 : $cost['amount'];
        }
        if ($amount <= 0) {
            throw new HttpException(422, t('purchase.lc_nothing'));
        }
        $total = array_sum(array_map(fn ($l) => (float) $l['value'], $data['lines']));
        if ($total <= 0) {
            throw new HttpException(422, t('purchase.lc_no_value'));
        }

        $changed = App::db()->transaction(function (Db $db) use ($rc, $data, $amount, $total) {
            $byPart = [];
            foreach ($data['lines'] as $l) {
                $byPart[$l['rcd_part']] = ($byPart[$l['rcd_part']] ?? 0) + $amount * (float) $l['value'] / $total;
            }
            $changed = 0;
            $shares = Gl::fit($byPart, $amount);
            $lines = [[Gl::acct('grni'), -round($amount, 2)]];
            foreach ($byPart as $part => $share) {
                $p = $db->one('SELECT pt_cost, pt_cost_method, pt_inventory FROM pt_mstr WHERE pt_part = ? FOR UPDATE', [$part]);
                if (!$p || !$p['pt_inventory']) {
                    $lines[] = [Gl::partAcct($part, 'pur'), $shares[$part]]; // not in stock: part of its expense
                    continue;
                }
                $onHand = (float) $db->value('SELECT COALESCE(SUM(ld_qty_oh), 0) FROM ld_det WHERE ld_part = ?', [$part]);
                if ($p['pt_cost_method'] !== 'AVG' || $onHand <= 0) {
                    $lines[] = [Gl::partAcct($part, 'cogs'), $shares[$part]]; // nothing left to carry it: cost of sales
                    continue;
                }
                $lines[] = [Gl::partAcct($part, 'inv'), $shares[$part]];
                $new = round((float) $p['pt_cost'] + $share / $onHand, 5);
                $db->update('pt_mstr', ['pt_cost' => $new, 'updated_by' => Auth::userId()], ['pt_part' => $part]);
                Audit::updated('pt_mstr', "pt_part=$part", ['pt_cost' => $p['pt_cost']], ['pt_cost' => $new]);
                $changed++;
            }
            $db->run('UPDATE lc_mstr SET lc_allocated = 1, updated_by = ? WHERE lc_rc_nbr = ?', [Auth::userId(), $rc]);
            Gl::post($rc, 'LC', max(date('Y-m-d'), Gl::periodStart()), $lines, t('accounting.desc_landed', ['nbr' => $rc]));
            return $changed;
        });
        return ['parts' => $changed];
    }
}
