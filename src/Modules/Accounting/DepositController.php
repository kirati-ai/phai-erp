<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\BahtText;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Master\Money;
use Phai\Modules\Sales\SalesPrint;
use Phai\Modules\Sales\SoController;

/**
 * ใบรับเงินมัดจำ / ใบกำกับภาษี (DP): money a customer pays on a sales order before the goods go. Receiving it is a VAT tax
 * point (ม.78), so the deposit carries its own output VAT: Dr bank / Cr เงินมัดจำรับ (before VAT) / Cr ภาษีขาย. The order's
 * tax invoices then deduct it (Deposit::apply in InvoiceController::fromShipment): their value and VAT are on what is left,
 * and Dr เงินมัดจำรับ clears it. Baht orders only. Cancel only while nothing of it is deducted.
 */
#[Program('deposits')]
final class DepositController
{
    #[Get('/deposits')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'   => 'deposits',
            'title'  => t('deposit.title'),
            'config' => [
                'api'     => '/api/deposits',
                'page'    => '/deposits',
                'canAdd'  => Auth::can('deposits', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'dp_nbr', 'label' => t('sales.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'dp_date', 'label' => t('sales.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'dp_so_nbr', 'label' => t('sales.so'), 'sort' => true],
                    ['key' => 'dp_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'dp_tot_amt', 'label' => t('sales.total'), 'type' => 'money', 'sort' => true],
                    ['key' => 'dp_left', 'label' => t('deposit.left'), 'type' => 'money'],
                    ['key' => 'dp_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'scripts' => ['js/doclist.js'],
        ]);
    }

    #[Get('/deposits/new'), Action('add')]
    public function newPage(Request $request): string
    {
        return View::render('accounting/deposit-new', [
            'help'    => 'deposits',
            'title'   => t('deposit.new'),
            'docNbr'  => Numbering::peek('DP'),
            'config'  => ['so' => (string) $request->input('so', ''), 'today' => date('Y-m-d'),
                          'banks' => App::db()->all('SELECT bk_bank, bk_desc FROM bk_mstr WHERE bk_active = 1 ORDER BY bk_bank')],
            'scripts' => ['js/pages/deposit.js'],
        ]);
    }

    #[Get('/deposits/{nbr}')]
    public function viewPage(string $nbr): string
    {
        return View::render('accounting/deposit-view', [
            'help'       => 'deposits',
            'title'      => $nbr,
            'ownToolbar' => true,
            'head'       => self::head($nbr),
            'uses'       => App::db()->all('SELECT * FROM dpa_det WHERE dpa_dp_nbr = ? ORDER BY created_at', [$nbr]),
            'status'     => self::statusText(),
            'canPrint'   => Auth::can('deposits', 'print'),
            'canCancel'  => Auth::can('deposits', 'delete') && !Gl::reconciled($nbr, 'CR'),
            'scripts'    => ['js/view.js'],
        ]);
    }

    #[Describe('Customer deposits received on sales orders (baht): total with VAT, what is still to be deducted on tax invoices', ['q' => 'deposit, order or customer', 'status' => 'O not fully deducted, C deducted, X cancelled'])]
    #[Get('/api/deposits')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($s = (string) $request->input('status', ''), ['O', 'C', 'X'], true)) {
            $where[] = 'd.dp_status = ?';
            $params[] = $s;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(d.dp_nbr LIKE ? OR d.dp_so_nbr LIKE ? OR d.dp_cm_code LIKE ? OR c.cm_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT d.dp_nbr, d.dp_date, d.dp_so_nbr, d.dp_cm_code, c.cm_name, d.dp_tot_amt, ROUND(d.dp_net_amt - d.dp_used_amt, 2) AS dp_left, d.dp_status',
            'FROM dp_mstr d LEFT JOIN cm_mstr c ON c.cm_code = d.dp_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'dp_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['dp_nbr' => 'd.dp_nbr', 'dp_date' => 'd.dp_date', 'dp_so_nbr' => 'd.dp_so_nbr', 'dp_cm_code' => 'd.dp_cm_code',
             'dp_tot_amt' => 'd.dp_tot_amt', 'dp_status' => 'd.dp_status'],
            'dp_nbr'
        );
    }

    /** ?q= — open baht sales orders a deposit can be taken on, for the order picker. */
    #[Get('/api/deposits/open-sos'), Action('add')]
    public function openSos(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            "SELECT s.so_nbr, s.so_cm_code, c.cm_name, s.so_tot_amt FROM so_mstr s LEFT JOIN cm_mstr c ON c.cm_code = s.so_cm_code
              WHERE s.so_status = 'O' AND s.so_curr = ? AND (s.so_nbr LIKE ? OR s.so_cm_code LIKE ? OR c.cm_name LIKE ? OR s.so_po_ref LIKE ?)
              ORDER BY s.so_nbr DESC LIMIT 20",
            [Money::base(), $like, $like, $like, $like]
        );
    }

    /** An open baht sales order to take a deposit on: customer, totals, tax and deposits so far. */
    #[Get('/api/deposits/so/{so}'), Action('add')]
    public function so(string $so): array
    {
        $h = SoController::head($so);
        if ($h['so_status'] !== 'O' || $h['so_curr'] !== Money::base()) {
            throw HttpException::invalid(['so' => t('deposit.so_not_open')]);
        }
        return [
            'so' => $so, 'customer' => $h['so_cm_code'], 'cm_name' => $h['cm_name'] ?? null, 'tax_code' => $h['so_tax_code'],
            'tax_pct' => (float) $h['so_tax_pct'], 'total' => (float) $h['so_tot_amt'],
            'taken' => (float) App::db()->value("SELECT COALESCE(SUM(dp_tot_amt), 0) FROM dp_mstr WHERE dp_so_nbr = ? AND dp_status <> 'X'", [$so]),
        ];
    }

    #[Describe('One customer deposit with the tax invoices that deducted it')]
    #[Get('/api/deposits/{nbr}')]
    public function show(string $nbr): array
    {
        return ['head' => self::head($nbr), 'uses' => App::db()->all('SELECT * FROM dpa_det WHERE dpa_dp_nbr = ?', [$nbr])];
    }

    /** Body: {so, dp_date, dp_bank, amount (received, VAT included), dp_remarks}. */
    #[Post('/api/deposits')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $so = $c->exists('so', $b['so'] ?? '', 'so_mstr', 'so_nbr');
        $date = $c->date('dp_date', $b['dp_date'] ?? '', true);
        $bank = $c->exists('dp_bank', $b['dp_bank'] ?? '', 'bk_mstr', 'bk_bank', true, 'bk_active = 1');
        $amount = $c->number('amount', $b['amount'] ?? '', true, 0, true, 2);
        $remarks = $c->text('dp_remarks', $b['dp_remarks'] ?? '', false, 255, false);
        if ($date && $date < Gl::periodStart()) {
            $c->fail('dp_date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $c->done();
        $o = $this->so($so);
        if ($amount + $o['taken'] > $o['total'] + 0.005) {
            throw HttpException::invalid(['amount' => t('deposit.over', ['max' => number_format(max(0, $o['total'] - $o['taken']), 2)])]);
        }
        // the amount received includes VAT: split it the way a tax invoice would
        $net = round($amount * 100 / (100 + $o['tax_pct']), 2);
        $tax = round($amount - $net, 2);

        $nbr = App::db()->transaction(function (Db $db) use ($so, $o, $date, $bank, $amount, $net, $tax, $remarks) {
            $nbr = Numbering::next('DP', $date);
            $row = [
                'dp_nbr' => $nbr, 'dp_so_nbr' => $so, 'dp_cm_code' => $o['customer'], 'dp_date' => $date, 'dp_bank' => $bank,
                'dp_tax_code' => $o['tax_code'], 'dp_net_amt' => $net, 'dp_tax_amt' => $tax, 'dp_tot_amt' => $amount, 'dp_used_amt' => 0,
                'dp_status' => 'O', 'dp_remarks' => $remarks, 'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
            ];
            $db->insert('dp_mstr', $row);
            Gl::post($nbr, 'CR', $date, [
                [CashReceiptController::bankAcct($bank), $amount],
                [Gl::acct('dep'), -$net],
                [Gl::acct('vat_out'), -$tax],
            ], t('deposit.desc', ['nbr' => $nbr, 'so' => $so]));
            if (PettyCash::isPetty($bank)) {
                // received in cash: into the receiver's own petty cash
                PettyCash::fromDocument($db, $nbr, 'DI', $date, $amount,
                    (string) ($db->value('SELECT cm_name FROM cm_mstr WHERE cm_code = ?', [$o['customer']]) ?: $o['customer']), 'dp_date');
            }
            return $nbr;
        });
        return ['nbr' => $nbr];
    }

    /** Only while no tax invoice has deducted any of it; the posting is reversed today. */
    #[Post('/api/deposits/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        App::db()->transaction(function (Db $db) use ($nbr) {
            $h = $db->one('SELECT * FROM dp_mstr WHERE dp_nbr = ? FOR UPDATE', [$nbr]) ?? throw HttpException::notFound();
            if ($h['dp_status'] === 'X' || (float) $h['dp_used_amt'] != 0.0) {
                throw new HttpException(409, t('deposit.used'));
            }
            $db->update('dp_mstr', ['dp_status' => 'X', 'updated_by' => Auth::userId()], ['dp_nbr' => $nbr]);
            PettyCash::documentCancelled($db, $nbr);
            if (Gl::reconciled($nbr, 'CR')) {
                throw new HttpException(409, t('accounting.reconciled'));
            }
            Gl::reverse($nbr, 'CR', max(date('Y-m-d'), Gl::periodStart()), t('accounting.desc_cancel', ['nbr' => $nbr]));
            Audit::updated('dp_mstr', "dp_nbr=$nbr", $h, ['dp_status' => 'X']);
        });
        return null;
    }

    #[Get('/deposits/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $m = fn ($v) => number_format((float) $v, 2);
        $pct = (float) $h['dp_net_amt'] ? round((float) $h['dp_tax_amt'] / (float) $h['dp_net_amt'] * 100) : 0;
        return SalesPrint::render([
            'title' => t('deposit.print_title'), 'nbr' => $nbr,
            'meta'  => [[t('sales.date'), SalesPrint::date($h['dp_date'])], [t('sales.so'), $h['dp_so_nbr']], [t('accounting.bank'), $h['bk_desc'] ?? $h['dp_bank']]],
            'party' => SalesPrint::customer($h['dp_cm_code']),
            'lines' => ['columns' => [['label' => '#', 'width' => '28px'], ['label' => t('stock.desc')], ['label' => t('accounting.amount'), 'align' => 'r', 'width' => '120px']],
                        // what the deposit is for: the goods of the sales order under the line
                        'rows' => [[1, t('deposit.line', ['so' => $h['dp_so_nbr']]), $m($h['dp_net_amt'])], ...array_map(
                            fn ($l) => SalesPrint::sub(SalesPrint::item($l['sod_part'], $l['name'], $l['sod_qty_ord'], $l['sod_um'], $l['sod_price'], $l['sod_line_amt'])),
                            App::db()->all(
                                "SELECT d.sod_part, COALESCE(NULLIF(d.sod_desc, ''), p.pt_desc1) AS name, d.sod_qty_ord, d.sod_um, d.sod_price, d.sod_line_amt
                                   FROM sod_det d LEFT JOIN pt_mstr p ON p.pt_part = d.sod_part WHERE d.sod_nbr = ? ORDER BY d.sod_line",
                                [$h['dp_so_nbr']]
                            )
                        )]],
            'totals' => SalesPrint::totals($h['dp_net_amt'], $pct, $h['dp_tax_amt'], $h['dp_tot_amt'], Money::base()),
            'notes' => trim(t('deposit.note') . "\n" . (string) $h['dp_remarks']),
            'signs' => [SalesPrint::outside(t('accounting.sign_payer')), t('accounting.sign_collector')],
        ]);
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT d.*, c.cm_name, k.bk_desc, ROUND(d.dp_net_amt - d.dp_used_amt, 2) AS dp_left FROM dp_mstr d
               LEFT JOIN cm_mstr c ON c.cm_code = d.dp_cm_code LEFT JOIN bk_mstr k ON k.bk_bank = d.dp_bank WHERE d.dp_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function statusText(): array
    {
        return ['O' => ['text' => t('deposit.status_O'), 'color' => 'yellow'], 'C' => ['text' => t('deposit.status_C'), 'color' => 'green'],
                'X' => ['text' => t('deposit.status_X'), 'color' => 'secondary']];
    }

    /**
     * Deduct a sales order's open deposits from a tax invoice of it (oldest first), up to the invoice's value before VAT.
     * Call inside the invoice's transaction. @return float the value deducted (before VAT, baht)
     */
    public static function apply(Db $db, string $so, string $inv, float $value): float
    {
        $left = round($value, 2);
        $used = 0.0;
        foreach ($db->all("SELECT * FROM dp_mstr WHERE dp_so_nbr = ? AND dp_status = 'O' ORDER BY dp_date, dp_nbr FOR UPDATE", [$so]) as $d) {
            if ($left <= 0) {
                break;
            }
            $take = round(min($left, (float) $d['dp_net_amt'] - (float) $d['dp_used_amt']), 2);
            if ($take <= 0) {
                continue;
            }
            $db->insert('dpa_det', ['dpa_dp_nbr' => $d['dp_nbr'], 'dpa_inv_nbr' => $inv, 'dpa_amt' => $take,
                                    'created_by' => Auth::userId(), 'updated_by' => Auth::userId()]);
            $usedNow = round((float) $d['dp_used_amt'] + $take, 2);
            $db->update('dp_mstr', ['dp_used_amt' => $usedNow, 'dp_status' => $usedNow >= (float) $d['dp_net_amt'] ? 'C' : 'O',
                                    'updated_by' => Auth::userId()], ['dp_nbr' => $d['dp_nbr']]);
            $left = round($left - $take, 2);
            $used = round($used + $take, 2);
        }
        return $used;
    }

    /** A cancelled tax invoice gives its deductions back. */
    public static function release(Db $db, string $inv): void
    {
        foreach ($db->all('SELECT * FROM dpa_det WHERE dpa_inv_nbr = ?', [$inv]) as $a) {
            $db->run("UPDATE dp_mstr SET dp_used_amt = dp_used_amt - ?, dp_status = 'O', updated_by = ? WHERE dp_nbr = ?",
                [$a['dpa_amt'], Auth::userId(), $a['dpa_dp_nbr']]);
        }
        $db->run('DELETE FROM dpa_det WHERE dpa_inv_nbr = ?', [$inv]);
    }
}
