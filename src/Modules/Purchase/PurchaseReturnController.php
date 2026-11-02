<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
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
use Phai\Modules\Sales\SalesPrint;

/**
 * ใบส่งคืนสินค้า (RT): goods sent back to the vendor from a goods receipt. The stock goes out (ISS-RTV) at its average cost
 * and the ledger takes back what the receipt put on GRNI at the purchase price: Dr GRNI (purchase value) / Cr inventory
 * (average cost) / the difference to the adjustment account. The vendor then sends a credit note (ใบลดหนี้), entered as
 * a payable credit note that picks up this return (key <rt>~RTV): Dr AP / Cr GRNI / Cr input VAT — status O → C.
 */
#[Program('purchase-returns')]
final class PurchaseReturnController
{
    #[Get('/purchase-returns')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'   => 'purchase-returns',
            'title'  => t('purchase.rt_title'),
            'config' => [
                'api'     => '/api/purchase-returns',
                'files'   => 'RT',
                'page'    => '/purchase-returns',
                'canAdd'  => false, // a return starts from its goods receipt
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'rt_nbr', 'label' => t('purchase.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'rt_date', 'label' => t('purchase.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'rt_rc_nbr', 'label' => t('purchase.rc_title'), 'sort' => true],
                    ['key' => 'rt_vd_code', 'label' => t('purchase.vendor'), 'sort' => true],
                    ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                    ['key' => 'rt_tot_amt', 'label' => t('purchase.total_thb'), 'type' => 'money', 'sort' => true],
                    ['key' => 'rt_ap_ref', 'label' => t('purchase.rt_ap_ref')],
                    ['key' => 'rt_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'scripts' => ['js/doclist.js'],
        ]);
    }

    #[Get('/purchase-returns/new'), Action('add')]
    public function newPage(Request $request): string
    {
        $rc = (string) $request->input('rc', '');
        return View::render('purchase/return-new', [
            'help'    => 'purchase-returns',
            'title'   => t('purchase.rt_new'),
            'docNbr'  => Numbering::peek('RT'),
            'config'  => ['rc' => $rc, 'today' => date('Y-m-d')],
            'scripts' => ['js/pages/purchase-return.js'],
        ]);
    }

    #[Get('/purchase-returns/{nbr}')]
    public function viewPage(string $nbr): string
    {
        return View::render('purchase/return-view', [
            'help'       => 'purchase-returns',
            'title'      => $nbr,
            'ownToolbar' => true,
            'head'       => self::head($nbr),
            'lines'      => self::lines($nbr),
            'status'     => self::statusText(),
            'canPrint'   => Auth::can('purchase-returns', 'print'),
        ]);
    }

    #[Describe('Purchase returns (goods sent back to vendors) in baht', ['q' => 'return, receipt or vendor', 'status' => 'O waiting for the vendor credit note, C credited'])]
    #[Get('/api/purchase-returns')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($s = (string) $request->input('status', ''), ['O', 'C'], true)) {
            $where[] = 'r.rt_status = ?';
            $params[] = $s;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(r.rt_nbr LIKE ? OR r.rt_rc_nbr LIKE ? OR r.rt_vd_code LIKE ? OR v.vd_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT r.rt_nbr, r.rt_date, r.rt_rc_nbr, r.rt_vd_code, v.vd_name, r.rt_tot_amt, r.rt_ap_ref, r.rt_status',
            'FROM rt_mstr r LEFT JOIN vd_mstr v ON v.vd_code = r.rt_vd_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'rt_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['rt_nbr' => 'r.rt_nbr', 'rt_date' => 'r.rt_date', 'rt_rc_nbr' => 'r.rt_rc_nbr', 'rt_vd_code' => 'r.rt_vd_code',
             'rt_tot_amt' => 'r.rt_tot_amt', 'rt_status' => 'r.rt_status'],
            'rt_nbr'
        );
    }

    /** ?q= — receipts with something left to send back, for the receipt picker. */
    #[Get('/api/purchase-returns/receipts'), Action('add')]
    public function receipts(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            'SELECT r.rc_nbr, r.rc_date, r.rc_vd_code, v.vd_name, r.rc_po_nbr FROM rc_mstr r LEFT JOIN vd_mstr v ON v.vd_code = r.rc_vd_code
              WHERE (r.rc_nbr LIKE ? OR r.rc_vd_code LIKE ? OR v.vd_name LIKE ? OR r.rc_po_nbr LIKE ? OR r.rc_inv_nbr LIKE ?)
                AND (SELECT SUM(d.rcd_qty_rcvd) FROM rcd_det d WHERE d.rcd_nbr = r.rc_nbr)
                  > COALESCE((SELECT SUM(x.rtd_qty) FROM rtd_det x JOIN rt_mstr t ON t.rt_nbr = x.rtd_nbr WHERE t.rt_rc_nbr = r.rc_nbr), 0)
              ORDER BY r.rc_nbr DESC LIMIT 20',
            [$like, $like, $like, $like, $like]
        );
    }

    /** The lines of a receipt with what can still be sent back (received − returned). */
    #[Get('/api/purchase-returns/receipt/{rc}'), Action('add')]
    public function receipt(string $rc): array
    {
        $head = \Phai\Modules\Purchase\ReceiptController::head($rc);
        $lines = App::db()->all(
            'SELECT d.rcd_line, d.rcd_part, pt.pt_desc1, d.rcd_qty_rcvd, d.rcd_um, d.rcd_loc, d.rcd_lot, ROUND(d.rcd_price * ?, 5) AS price,
                    d.rcd_qty_rcvd - COALESCE((SELECT SUM(x.rtd_qty) FROM rtd_det x JOIN rt_mstr r ON r.rt_nbr = x.rtd_nbr
                                                WHERE r.rt_rc_nbr = d.rcd_nbr AND x.rtd_rc_line = d.rcd_line), 0) AS returnable
               FROM rcd_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.rcd_part WHERE d.rcd_nbr = ? ORDER BY d.rcd_line',
            [(float) $head['rc_ex_rate'], $rc]
        );
        return ['head' => $head, 'lines' => $lines];
    }

    #[Describe('One purchase return with its lines')]
    #[Get('/api/purchase-returns/{nbr}')]
    public function show(string $nbr): array
    {
        return ['head' => self::head($nbr), 'lines' => self::lines($nbr)];
    }

    /** Body: {rc, rt_date, rt_reason, lines: {rcd_line: qty}}. */
    #[Post('/api/purchase-returns')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $rc = $c->exists('rc', $b['rc'] ?? '', 'rc_mstr', 'rc_nbr');
        $date = $c->date('rt_date', $b['rt_date'] ?? '', true);
        $reason = $c->text('rt_reason', $b['rt_reason'] ?? '', true, 255, false);
        $want = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $line => $qty) {
            if (trim((string) $qty) === '') {
                continue;
            }
            $q = $c->number("lines.$line.qty", $qty, true, 0, true, 5);
            if ($q) {
                $want[(int) $line] = $q;
            }
        }
        if (!$want && $c->ok('lines')) {
            $c->fail('lines', t('purchase.rt_nothing'));
        }
        if ($date && $date < Gl::periodStart()) {
            $c->fail('rt_date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $c->done();

        $nbr = App::db()->transaction(function (Db $db) use ($rc, $date, $reason, $want) {
            $r = $db->one('SELECT * FROM rc_mstr WHERE rc_nbr = ? FOR UPDATE', [$rc]);
            $lines = array_column($this->receipt($rc)['lines'], null, 'rcd_line');
            $vatRate = (float) $r['rc_net_amt'] ? (float) $r['rc_tax_amt'] / (float) $r['rc_net_amt'] : 0.0;
            $nbr = Numbering::next('RT', $date);
            $user = Auth::userId();
            $gl = [];
            $net = 0.0;
            $n = 0;
            foreach ($want as $line => $qty) {
                $l = $lines[$line] ?? throw HttpException::invalid(["lines.$line.qty" => t('validation.in')]);
                if ($qty > round((float) $l['returnable'], 5)) {
                    throw HttpException::invalid(["lines.$line.qty" => t('purchase.rt_over', ['max' => Stock::fmt((float) $l['returnable'])])]);
                }
                $amt = round($qty * (float) $l['price'], 2); // what the receipt put on GRNI for this quantity
                $net += $amt;
                $cost = 0.0;
                if (Stock::stocked($l['rcd_part'])) {
                    $cost = (float) $db->value('SELECT pt_cost FROM pt_mstr WHERE pt_part = ?', [$l['rcd_part']]);
                    Stock::move([
                        'type' => 'ISS-RTV', 'part' => $l['rcd_part'], 'site' => $r['rc_site'], 'loc' => $l['rcd_loc'], 'lot' => (string) $l['rcd_lot'],
                        'qty' => -$qty, 'date' => $date, 'nbr' => $nbr, 'line' => ++$n, 'program' => 'purchase-returns', 'remark' => $rc,
                        'field' => "lines.$line.qty",
                    ]);
                    $value = round($qty * $cost, 2);
                    $gl[] = [Gl::partAcct($l['rcd_part'], 'inv'), -$value, "$nbr {$l['rcd_part']}"];
                    $gl[] = [Gl::acct('adj'), round($value - $amt, 2), t('purchase.rt_variance', ['nbr' => $nbr])];
                } else {
                    $n++;
                    $gl[] = [Gl::partAcct($l['rcd_part'], 'pur'), -$amt, "$nbr {$l['rcd_part']}"]; // the expense taken at receipt comes back
                }
                $gl[] = [Gl::acct('grni'), $amt, "$nbr $rc"];
                $db->insert('rtd_det', [
                    'rtd_nbr' => $nbr, 'rtd_line' => $n, 'rtd_rc_line' => $line, 'rtd_part' => $l['rcd_part'], 'rtd_qty' => $qty,
                    'rtd_um' => $l['rcd_um'], 'rtd_loc' => $l['rcd_loc'], 'rtd_lot' => (string) $l['rcd_lot'], 'rtd_price' => $l['price'],
                    'rtd_amt' => $amt, 'rtd_cost' => $cost, 'created_by' => $user, 'updated_by' => $user,
                ]);
            }
            Gl::post($nbr, 'IC', $date, array_values(array_filter($gl, fn ($g) => round($g[1], 2) != 0.0)), t('purchase.rt_desc', ['nbr' => $nbr, 'rc' => $rc]));
            $net = round($net, 2);
            $tax = round($net * $vatRate, 2);
            $db->insert('rt_mstr', [
                'rt_nbr' => $nbr, 'rt_rc_nbr' => $rc, 'rt_po_nbr' => $r['rc_po_nbr'], 'rt_vd_code' => $r['rc_vd_code'], 'rt_site' => $r['rc_site'],
                'rt_date' => $date, 'rt_reason' => $reason, 'rt_net_amt' => $net, 'rt_tax_amt' => $tax, 'rt_tot_amt' => round($net + $tax, 2),
                'rt_status' => 'O', 'created_by' => $user, 'updated_by' => $user,
            ]);
            return $nbr;
        });
        return ['nbr' => $nbr];
    }

    #[Get('/purchase-returns/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 5, '.', ','), '0'), '.');
        $m = fn ($v) => number_format((float) $v, 2);
        return SalesPrint::render([
            'title' => t('purchase.rt_title'), 'nbr' => $nbr,
            'meta'  => [[t('purchase.date'), SalesPrint::date($h['rt_date'])], [t('purchase.rc_title'), $h['rt_rc_nbr']],
                        [t('purchase.po'), (string) $h['rt_po_nbr']], [t('purchase.rt_vendor_inv'), (string) $h['rc_inv_nbr']]],
            'party' => SalesPrint::vendor($h['rt_vd_code']),
            'lines' => ['columns' => [
                ['label' => '#', 'width' => '28px'], ['label' => t('stock.part') . ' / ' . t('stock.desc')], ['label' => t('stock.qty'), 'align' => 'r'],
                ['label' => t('stock.um')], ['label' => t('stock.lot')], ['label' => t('purchase.price'), 'align' => 'r'],
                ['label' => t('purchase.amount'), 'align' => 'r', 'width' => '110px']],
                'rows' => array_map(fn ($l) => [$l['rtd_line'], $l['rtd_part'] . ' ' . ($l['pt_desc1'] ?? ''), $q($l['rtd_qty']), $l['rtd_um'],
                    $l['rtd_lot'], $m($l['rtd_price']), $m($l['rtd_amt'])], self::lines($nbr))],
            'totals' => SalesPrint::totals($h['rt_net_amt'], null, $h['rt_tax_amt'], $h['rt_tot_amt'], \Phai\Modules\Master\Money::base()),
            'notes'  => t('purchase.rt_reason') . ': ' . $h['rt_reason'],
            'signs'  => [t('purchase.rt_sign_sender'), SalesPrint::outside(t('purchase.rt_sign_vendor'))],
        ]);
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT r.*, v.vd_name, rc.rc_inv_nbr, rc.rc_ap_ref FROM rt_mstr r LEFT JOIN vd_mstr v ON v.vd_code = r.rt_vd_code
               LEFT JOIN rc_mstr rc ON rc.rc_nbr = r.rt_rc_nbr WHERE r.rt_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, pt.pt_desc1 FROM rtd_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.rtd_part WHERE d.rtd_nbr = ? ORDER BY d.rtd_line',
            [$nbr]
        );
    }

    public static function statusText(): array
    {
        return ['O' => ['text' => t('purchase.rt_status_O'), 'color' => 'yellow'], 'C' => ['text' => t('purchase.rt_status_C'), 'color' => 'green']];
    }
}
