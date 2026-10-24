<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Accounting\Gl;
use Phai\Modules\Inventory\Stock;
use Phai\Modules\Inventory\StockLookupController;

/**
 * Receive goods against an approved PO. Stock comes in at the PO price (in baht),
 * the PO lines count what arrived, and the PO closes when everything is in.
 */
#[Program('receipts')]
final class ReceiptController
{
    #[Get('/receipts')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'    => 'receipts',
            'title'   => t('purchase.rc_title'),
            'config'  => [
                'api'     => '/api/receipts',
                'files'   => 'RC',
                'page'    => '/receipts',
                'canAdd'  => Auth::can('receipts', 'add'),
                'status'  => (object) [],
                'columns' => [
                    ['key' => 'rc_nbr', 'label' => t('purchase.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'rc_date', 'label' => t('purchase.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'rc_po_nbr', 'label' => t('purchase.po'), 'sort' => true],
                    ['key' => 'rc_vd_code', 'label' => t('purchase.vendor'), 'sort' => true],
                    ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                    ['key' => 'rc_inv_nbr', 'label' => t('purchase.inv_nbr')],
                    ['key' => 'rc_tot_amt', 'label' => t('purchase.total_thb'), 'type' => 'money', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Open purchase orders not fully received above the list: one click opens a new receipt for the order. */
    public static function queues(): array
    {
        return Auth::can('receipts', 'add') ? [[
            'title'   => t('purchase.queue_po'),
            'api'     => '/api/receipts/waiting',
            'key'     => 'po_nbr',
            'urgent'  => 'late',
            'columns' => [
                ['key' => 'po_nbr', 'label' => t('purchase.po'), 'link' => '/purchase-orders/{po_nbr}'],
                ['key' => 'po_vd_code', 'label' => t('purchase.vendor')],
                ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                ['key' => 'due', 'label' => t('purchase.due_date'), 'type' => 'date'],
                ['key' => 'open_qty', 'label' => t('purchase.open_qty'), 'type' => 'num'],
                ['key' => 'open_amt', 'label' => t('purchase.open_amt'), 'type' => 'money'],
                ['key' => 'po_curr', 'label' => t('purchase.currency')],
            ],
            'action'  => ['label' => t('purchase.queue_receive'), 'icon' => 'ti ti-truck-delivery', 'href' => '/receipts/new?po={po_nbr}'],
        ]] : [];
    }

    /** Open POs with something left to receive, earliest due first (no due date last). */
    #[Describe('Open purchase orders still waiting for goods, earliest due date first (late = due date passed; open_qty / open_amt = not received yet, amount in the PO currency)')]
    #[Get('/api/receipts/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            "SELECT p.po_nbr, p.po_vd_code, v.vd_name, p.po_curr, COALESCE(p.po_due_date, MIN(d.pod_req_date)) AS due,
                    COALESCE(p.po_due_date, MIN(d.pod_req_date)) < CURDATE() AS late,
                    SUM(d.pod_qty_ord - d.pod_qty_rcvd) AS open_qty,
                    ROUND(SUM(d.pod_line_amt * (d.pod_qty_ord - d.pod_qty_rcvd) / d.pod_qty_ord), 2) AS open_amt
               FROM po_mstr p JOIN pod_det d ON d.pod_nbr = p.po_nbr AND d.pod_status = 'O' AND d.pod_qty_ord > d.pod_qty_rcvd
               LEFT JOIN vd_mstr v ON v.vd_code = p.po_vd_code
              WHERE p.po_status = 'A'
              GROUP BY p.po_nbr, p.po_vd_code, v.vd_name, p.po_curr, p.po_due_date
              ORDER BY COALESCE(p.po_due_date, MIN(d.pod_req_date)) IS NULL, due, p.po_nbr LIMIT 200"
        );
    }

    #[Get('/receipts/new')]
    public function newPage(Request $request): string
    {
        return View::render('purchase/receipt', [
            'help'    => 'receipts',
            'docNbr' => Numbering::peek('RC'),
            'title'   => t('purchase.rc_new'),
            'nbr'     => null,
            'po'      => (string) $request->input('po', ''),
            'sites'   => StockLookupController::sites(),
            'scripts' => ['js/pages/receipt.js'],
        ]);
    }

    #[Get('/receipts/{nbr}')]
    public function viewPage(string $nbr): string
    {
        // a saved receipt is a read-only view like a purchase order (ux/ui.md "View pages")
        return View::render('purchase/receipt-view', [
            'help'    => 'receipts',
            'title'      => $nbr,
            'ownToolbar' => true,
            'head'       => self::head($nbr),
            'lines'      => $this->show($nbr)['lines'],
            'canPrint'   => \Phai\Core\Auth\Auth::can('receipts', 'print'),
            'canPayable' => \Phai\Core\Auth\Auth::can('payables', 'add'),
        ]);
    }

    #[Describe('Goods received against purchase orders (amounts in baht)', ['q' => 'receipt, PO, vendor or delivery note'])]

    #[Get('/api/receipts')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(r.rc_nbr LIKE ? OR r.rc_po_nbr LIKE ? OR r.rc_vd_code LIKE ? OR v.vd_name LIKE ? OR r.rc_inv_nbr LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT r.rc_nbr, r.rc_date, r.rc_po_nbr, r.rc_vd_code, v.vd_name, r.rc_inv_nbr, r.rc_tot_amt',
            'FROM rc_mstr r LEFT JOIN vd_mstr v ON v.vd_code = r.rc_vd_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'rc_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['rc_nbr' => 'r.rc_nbr', 'rc_date' => 'r.rc_date', 'rc_po_nbr' => 'r.rc_po_nbr', 'rc_vd_code' => 'r.rc_vd_code', 'rc_tot_amt' => 'r.rc_tot_amt'],
            'rc_nbr'
        );
    }

    /** ?q= — approved POs that still have something to receive. */
    #[Describe('Approved purchase orders still waiting for goods', ['q' => 'PO number or vendor'])]
    #[Get('/api/receipts/open-pos')]
    public function openPos(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            "SELECT p.po_nbr, p.po_vd_code, v.vd_name, p.po_date FROM po_mstr p LEFT JOIN vd_mstr v ON v.vd_code = p.po_vd_code
              WHERE p.po_status = 'A' AND (p.po_nbr LIKE ? OR p.po_vd_code LIKE ? OR v.vd_name LIKE ?)
              ORDER BY p.po_nbr DESC LIMIT 20",
            [$like, $like, $like]
        );
    }

    /** The PO to receive: header and the lines still open, with how much is left. */
    #[Get('/api/receipts/po/{po}')]
    public function po(string $po): array
    {
        $head = PoController::head($po);
        if ($head['po_status'] !== 'A') {
            throw new HttpException(409, t('purchase.po_not_open'));
        }
        $tol = (float) (Purchase::settings()['po_rcv_tol'] ?? 0);
        $lines = array_values(array_filter(PoController::lines($po), fn ($l) => $l['pod_status'] === 'O'));
        foreach ($lines as &$l) {
            $l['open_qty'] = max(0, round((float) $l['pod_qty_ord'] - (float) $l['pod_qty_rcvd'], 5));
            $l['max_qty'] = max(0, round((float) $l['pod_qty_ord'] * (1 + $tol / 100) - (float) $l['pod_qty_rcvd'], 5));
        }
        return ['head' => $head, 'lines' => $lines];
    }

    #[Describe('One goods receipt with its lines')]

    #[Get('/api/receipts/{nbr}')]
    public function show(string $nbr): array
    {
        return [
            'head'  => self::head($nbr),
            'lines' => App::db()->all(
                'SELECT r.*, COALESCE(pd.pod_desc, pt.pt_desc1) AS pt_desc1 FROM rcd_det r JOIN rc_mstr h ON h.rc_nbr = r.rcd_nbr
                   LEFT JOIN pod_det pd ON pd.pod_nbr = h.rc_po_nbr AND pd.pod_line = r.rcd_pod_line
                   LEFT JOIN pt_mstr pt ON pt.pt_part = r.rcd_part WHERE r.rcd_nbr = ? ORDER BY r.rcd_line',
                [$nbr]
            ),
        ];
    }

    /**
     * Body: {po, rc_date, rc_site, rc_inv_nbr (the vendor's tax invoice or delivery note number), rc_inv_date, rc_remarks,
     *        lines: {pod_line: {qty, loc, lot, expire, pack}}}  (pack = qty per pack, 1 when empty)
     */
    #[Post('/api/receipts')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $po = $c->exists('po', $b['po'] ?? '', 'po_mstr', 'po_nbr', true, "po_status = 'A'");
        $h = [
            'rc_date'     => $c->date('rc_date', $b['rc_date'] ?? '', true),
            'rc_site'     => $c->exists('rc_site', $b['rc_site'] ?? '', 'si_mstr', 'si_site', true, 'si_status = 1'),
            'rc_inv_nbr'  => $c->text('rc_inv_nbr', $b['rc_inv_nbr'] ?? '', false, 50, false),
            'rc_inv_date' => $c->date('rc_inv_date', $b['rc_inv_date'] ?? ''),
            'rc_remarks'  => $c->text('rc_remarks', $b['rc_remarks'] ?? '', false, 2000, false),
        ];
        if ($h['rc_date'] && $h['rc_date'] > date('Y-m-d')) {
            $c->fail('rc_date', t('stock.future_date'));
        }
        $c->done();

        $open = array_column($this->po($po)['lines'], null, 'pod_line');
        $lines = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $podLine => $l) {
            if (!is_array($l) || !isset($open[$podLine])) {
                continue;
            }
            $f = fn (string $n) => "lines.$podLine.$n";
            $qty = $c->number($f('qty'), $l['qty'] ?? '', false, 0);
            if (!$qty) {
                continue; // nothing received on this line
            }
            $pod = $open[$podLine]; // more than ordered may be received: the answer warns (see below)
            // items not kept in stock (services, free text) need no location and never touch stock
            $stocked = Stock::stocked($pod['pod_part']);
            $loc = $stocked ? $c->exists($f('loc'), $l['loc'] ?? '', 'loc_mstr', 'loc_loc', true, 'loc_site = ' . App::db()->pdo->quote((string) $h['rc_site'])) : '';
            $needsLot = $stocked && in_array($pod['pt_lot_ser'], ['L', 'S'], true);
            // a lot left empty is the vendor's document number (tax invoice or delivery note); without it the error goes on
            // the head: the document number is what is missing, not the lot
            $lot = $c->text($f('lot'), $l['lot'] ?? '', false, 50, false) ?? $h['rc_inv_nbr'];
            if ($needsLot && $lot === null && $c->ok('rc_inv_nbr')) {
                $c->fail('rc_inv_nbr', t('purchase.lot_doc_required'));
            }
            $lines[$podLine] = ['pod' => $pod, 'stocked' => $stocked, 'qty' => $qty, 'loc' => $loc, 'lot' => $needsLot ? (string) $lot : '', 'expire' => $c->date($f('expire'), $l['expire'] ?? ''),
                'pack' => $stocked ? ($c->number($f('pack'), $l['pack'] ?? '', false, 0, true) ?? 1.0) : 1.0];
        }
        if (!$lines && !$c->errors) {
            $c->fail('lines', t('purchase.nothing_received'));
        }
        $c->done();

        $nbr = App::db()->transaction(function (Db $db) use ($po, $h, $lines) {
            // Lock the PO lines (someone may receive at the same time). Receiving more than ordered is allowed; beyond the
            // tolerance of the purchasing settings the answer carries a warning naming each such line.
            $tol = (float) (Purchase::settings()['po_rcv_tol'] ?? 0);
            $now = array_column($db->all('SELECT pod_line, pod_qty_ord, pod_qty_rcvd FROM pod_det WHERE pod_nbr = ? FOR UPDATE', [$po]), null, 'pod_line');
            $over = [];
            foreach ($lines as $podLine => $l) {
                $open = (float) $now[$podLine]['pod_qty_ord'] - (float) $now[$podLine]['pod_qty_rcvd'];
                if ($l['qty'] > round((float) $now[$podLine]['pod_qty_ord'] * (1 + $tol / 100) - (float) $now[$podLine]['pod_qty_rcvd'], 5)) {
                    $over[] = $l['pod']['pod_part'] . ' ' . Stock::fmt(round($l['qty'] - $open, 5)) . ' ' . $l['pod']['pod_um'];
                }
            }

            $poHead = PoController::head($po);
            $rate = (float) $poHead['po_ex_rate'];
            $nbr = Numbering::next('RC', $h['rc_date']);
            $user = Auth::userId();
            $net = 0.0;
            $n = 0;
            $rows = [];
            foreach ($lines as $podLine => $l) {
                $pod = $l['pod'];
                $unit = round((float) $pod['pod_price'] * (1 - (float) $pod['pod_disc_pct'] / 100), 5);
                $amt = round($l['qty'] * $unit, 2);
                $net += $amt;
                $rows[] = [
                    'rcd_line' => ++$n, 'rcd_pod_line' => $podLine, 'rcd_part' => $pod['pod_part'], 'rcd_qty_rcvd' => $l['qty'],
                    'rcd_um' => $pod['pod_um'], 'rcd_price' => $unit, 'rcd_line_amt' => $amt, 'rcd_tax_code' => $poHead['po_tax_code'],
                    'rcd_loc' => $l['loc'], 'rcd_lot' => $l['lot'], 'rcd_status' => 'ACTIVE',
                ];
                if ($l['stocked']) {
                    Stock::move([
                        'type' => 'RCT-PO', 'part' => $pod['pod_part'], 'site' => $h['rc_site'], 'loc' => $l['loc'], 'lot' => $l['lot'],
                        'qty' => $l['qty'], 'cost' => round($unit * $rate, 5), 'price' => $unit, 'expire' => $l['expire'], 'pack' => $l['pack'],
                        'date' => $h['rc_date'], 'nbr' => $nbr, 'line' => $n, 'remark' => $po, 'program' => 'receipts',
                    ]);
                } else {
                    // no stock: the cost is an expense now, against GRNI like a stocked receipt (payables clears it)
                    $base = round($amt * $rate, 2);
                    $expense = $pod['pod_acct'] ?: Gl::partAcct($pod['pod_part'], 'pur');
                    if ($base != 0) {
                        Gl::post($nbr, 'IC', $h['rc_date'], [[$expense, $base, $pod['pod_desc'] ?? $pod['pod_part']], [Gl::acct('grni'), -$base]]);
                    }
                }
                $db->run(
                    "UPDATE pod_det SET pod_qty_rcvd = pod_qty_rcvd + ?,
                            pod_status = IF(pod_qty_rcvd >= pod_qty_ord, 'C', 'O'), updated_by = ?
                      WHERE pod_nbr = ? AND pod_line = ?",
                    [$l['qty'], $user, $po, $podLine]
                );
            }

            $netBase = round($net * $rate, 2);
            $taxBase = round($netBase * (float) $poHead['po_tax_pct'] / 100, 2);
            $db->insert('rc_mstr', $h + [
                'rc_nbr' => $nbr, 'rc_po_nbr' => $po, 'rc_vd_code' => $poHead['po_vd_code'], 'rc_curr' => $poHead['po_curr'],
                'rc_ex_rate' => $rate, 'rc_cr_terms' => $poHead['po_cr_terms'], 'rc_net_amt' => $netBase, 'rc_tax_amt' => $taxBase,
                'rc_tot_amt' => round($netBase + $taxBase, 2), 'rc_receiver' => $user, 'created_by' => $user, 'updated_by' => $user,
            ]);
            foreach ($rows as $r) {
                $db->insert('rcd_det', $r + ['rcd_nbr' => $nbr, 'created_by' => $user, 'updated_by' => $user]);
            }
            if (!$db->value("SELECT 1 FROM pod_det WHERE pod_nbr = ? AND pod_status = 'O'", [$po]) && !$db->value('SELECT po_sched FROM po_mstr WHERE po_nbr = ?', [$po])) {
                $db->update('po_mstr', ['po_status' => 'C', 'updated_by' => $user], ['po_nbr' => $po]);
            }
            return [$nbr, $over];
        });
        [$nbr, $over] = $nbr;
        return ['nbr' => $nbr] + ($over ? ['notice' => t('purchase.over_received', ['lines' => implode(', ', $over)])] : []);
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT r.*, v.vd_name, si.si_desc FROM rc_mstr r
               LEFT JOIN vd_mstr v ON v.vd_code = r.rc_vd_code
               LEFT JOIN si_mstr si ON si.si_site = r.rc_site
              WHERE r.rc_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }
}
