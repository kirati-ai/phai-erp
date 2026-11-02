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
use Phai\Core\Crud\Lookup;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Sales\Sales;
use Phai\Modules\Sales\SalesPrint;

/**
 * Cash receipts: money in from a customer against open receivables (in baht).
 * Each line settles received + tax the customer withheld. Status: P posted, C cancelled.
 * Dr bank, Dr withholding tax receivable / Cr receivable.
 */
#[Program('cash-receipts')]
final class CashReceiptController
{
    #[Get('/cash-receipts')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('accounting.cr_title'),
            'help'    => 'cash-receipts',
            'config'  => [
                'api'     => '/api/cash-receipts',
                'files'   => 'CR',
                'page'    => '/cash-receipts',
                'canAdd'  => Auth::can('cash-receipts', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'cr_nbr', 'label' => t('accounting.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'cr_date', 'label' => t('accounting.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'cr_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'cr_bank', 'label' => t('accounting.bank')],
                    ['key' => 'cr_amt', 'label' => t('accounting.received'), 'type' => 'money', 'sort' => true],
                    ['key' => 'cr_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Unpaid invoices and debit notes above the list: one click opens a new receipt for that invoice. */
    public static function queues(): array
    {
        return Auth::can('cash-receipts', 'add') ? [[
            'title'   => t('accounting.q_cr'),
            'api'     => '/api/cash-receipts/waiting',
            'key'     => 'ar_nbr',
            'urgent'  => 'overdue',
            'columns' => [
                ['key' => 'ar_nbr', 'label' => t('accounting.nbr'), 'link' => '/{area}/{ar_nbr}'],
                ['key' => 'type_text', 'label' => t('accounting.ap_type')],
                ['key' => 'customer', 'label' => t('sales.customer')],
                ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                ['key' => 'ar_date', 'label' => t('accounting.date'), 'type' => 'date'],
                ['key' => 'ar_due_date', 'label' => t('sales.due_date'), 'type' => 'date'],
                ['key' => 'balance', 'label' => t('accounting.balance'), 'type' => 'money'],
            ],
            'action'  => ['label' => t('accounting.q_cr_make'), 'icon' => 'ti ti-cash', 'href' => '/cash-receipts/new?customer={customer}&inv={ar_nbr}'],
        ]] : [];
    }

    #[Get('/cash-receipts/new')]
    public function newPage(): string
    {
        return $this->page(null);
    }

    #[Get('/cash-receipts/{nbr}')]
    public function viewPage(string $nbr): string
    {
        self::head($nbr);
        return $this->page($nbr);
    }

    #[Get('/cash-receipts/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $m = fn ($v) => number_format((float) $v, 2);
        $lines = self::lines($nbr);
        $settled = array_sum(array_map(fn ($l) => (float) $l['crd_amt'] + (float) $l['crd_wht_amt'], $lines));
        // paying a service makes its VAT due: this receipt is then also the tax invoice (ม.86/4, ม.105 ทวิ)
        $vat = round(array_sum(array_column($lines, 'crd_vat_amt')), 2);
        $vatRows = $vat != 0.0 ? [[t('accounting.vat_due_net'), $m(array_sum(array_column($lines, 'crd_net_amt'))), false],
                                   [t('accounting.vat_due_tax'), $m($vat), false]] : [];
        return SalesPrint::render([
            'title' => $vat != 0.0 ? t('accounting.receipt_tax') : t('accounting.receipt'),
            'nbr'   => $nbr,
            'meta'  => [[t('accounting.date'), SalesPrint::date($h['cr_date'])], [t('accounting.bank'), $h['bk_desc'] ?? $h['cr_bank']]],
            'party' => SalesPrint::customer($h['cr_cm_code']),
            'lines' => [
                'columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('sales.invoice')],
                    ['label' => t('accounting.settled'), 'align' => 'r', 'width' => '110px'],
                    ['label' => t('accounting.wht_withheld'), 'align' => 'r', 'width' => '110px'],
                    ['label' => t('accounting.received'), 'align' => 'r', 'width' => '110px'],
                ],
                // under each invoice what it was for — on a receipt / tax invoice (services) the law asks for it (ม.86/4)
                'rows' => (function () use ($lines, $m) {
                    $items = SalesPrint::invoiceItems(array_column($lines, 'crd_ar_nbr'));
                    $rows = [];
                    foreach ($lines as $l) {
                        $rows[] = [$l['crd_line'], $l['crd_ar_nbr'], $m((float) $l['crd_amt'] + (float) $l['crd_wht_amt']), $m($l['crd_wht_amt']), $m($l['crd_amt'])];
                        array_push($rows, ...array_map([SalesPrint::class, 'sub'], $items[$l['crd_ar_nbr']] ?? []));
                    }
                    return $rows;
                })(),
            ],
            'totals' => [
                'rows'  => [
                    ...$vatRows,
                    [t('accounting.settled'), $m($settled), 'bold'],
                    [t('accounting.wht_withheld'), ((float) $h['cr_wht_amt'] ? '-' : '') . $m($h['cr_wht_amt']), false],
                    [t('accounting.received'), $m($h['cr_amt']), true],
                ],
                'words' => BahtText::of((float) $h['cr_amt']),
            ],
            'notes' => $h['cr_remarks'],
            'signs' => [SalesPrint::outside(t('accounting.sign_payer')), t('accounting.sign_collector')],
        ]);
    }

    #[Describe('Cash received from customers (baht)', ['q' => 'number or customer', 'status' => 'P posted, C cancelled'])]

    #[Get('/api/cash-receipts')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['P', 'C'], true)) {
            $where[] = 'r.cr_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(r.cr_nbr LIKE ? OR r.cr_cm_code LIKE ? OR c.cm_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT r.cr_nbr, r.cr_date, r.cr_cm_code, c.cm_name, r.cr_bank, r.cr_amt, r.cr_status',
            'FROM cr_mstr r LEFT JOIN cm_mstr c ON c.cm_code = r.cr_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'cr_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['cr_nbr' => 'r.cr_nbr', 'cr_date' => 'r.cr_date', 'cr_cm_code' => 'r.cr_cm_code', 'cr_amt' => 'r.cr_amt', 'cr_status' => 'r.cr_status'],
            'cr_nbr'
        );
    }

    /** ?customer= — open receivables, oldest due first. */
    #[Describe('Open receivables of one customer', ['customer' => 'customer code'])]
    #[Get('/api/cash-receipts/open')]
    public function open(Request $request): array
    {
        return self::openItems((string) $request->input('customer', ''));
    }

    #[Describe('Unpaid invoices and debit notes of all customers, earliest due first (baht; overdue = past due date)')]
    #[Get('/api/cash-receipts/waiting')]
    public function waiting(): array
    {
        $rows = App::db()->all(
            "SELECT a.ar_nbr, COALESCE(h.ih_type, 'IN') AS ar_type, a.ar_cm_code AS customer, c.cm_name, a.ar_date, a.ar_due_date,
                    ROUND(a.ar_amt - a.ar_paid_amt, 2) AS balance, (a.ar_due_date < CURDATE()) AS overdue,
                    IF(h.ih_sh_nbr IS NULL, 'ar-invoices', 'invoices') AS area
               FROM ar_mstr a LEFT JOIN ih_mstr h ON h.ih_inv_nbr = a.ar_nbr LEFT JOIN cm_mstr c ON c.cm_code = a.ar_cm_code
              WHERE a.ar_status = 'O' AND a.ar_amt > a.ar_paid_amt
              ORDER BY a.ar_due_date IS NULL, a.ar_due_date, a.ar_nbr LIMIT 200"
        );
        foreach ($rows as &$r) {
            $r['type_text'] = t('accounting.q_ar_type_' . ($r['ar_type'] === 'DN' ? 'DN' : 'IN'));
            $r['overdue'] = (bool) $r['overdue'];
        }
        return $rows;
    }

    #[Describe('One cash receipt with the invoices it settled')]

    #[Get('/api/cash-receipts/{nbr}')]
    public function show(string $nbr): array
    {
        $h = self::head($nbr);
        return [
            'head'  => ['party' => $h['cr_cm_code'], 'party_name' => $h['cm_name'], 'date' => $h['cr_date'], 'bank' => $h['cr_bank'],
                        'remarks' => $h['cr_remarks'], 'status' => $h['cr_status'], 'reconciled' => Gl::reconciled($nbr, 'CR')],
            'lines' => array_map(fn ($l) => [
                'doc' => $l['crd_ar_nbr'], 'link' => '/invoices/' . $l['crd_ar_nbr'], 'date' => $l['ar_date'], 'due' => $l['ar_due_date'], 'amount' => $l['ar_amt'],
                'applied' => $l['crd_amt'], 'wht' => $l['crd_wht_amt'],
            ], self::lines($nbr)),
        ];
    }

    /** Body: {customer, cr_date, cr_bank, cr_remarks, lines: {ar_nbr: {amt, wht}}} */
    #[Post('/api/cash-receipts')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $customer = $c->exists('customer', $b['customer'] ?? '', 'cm_mstr', 'cm_code');
        $date = $c->date('cr_date', $b['cr_date'] ?? '', true);
        if ($date && $date < Gl::periodStart()) {
            $c->fail('cr_date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $bank = $c->exists('cr_bank', $b['cr_bank'] ?? '', 'bk_mstr', 'bk_bank', true, 'bk_active = 1');
        $remarks = $c->text('cr_remarks', $b['cr_remarks'] ?? '', false, 2000, false);
        $c->done();

        $open = array_column(self::openItems((string) $customer), null, 'ar_nbr');
        $apply = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $ar => $l) {
            if (!is_array($l) || !isset($open[$ar])) {
                continue;
            }
            // a credit note has a negative balance: applying it takes a negative amount and no withholding
            $balance = (float) $open[$ar]['balance'];
            $credit = $balance < 0;
            $amt = $c->number("lines.$ar.amt", $l['amt'] ?? '', false, $credit ? null : 0, false, 2) ?? 0.0;
            $wht = $credit ? 0.0 : ($c->number("lines.$ar.wht", $l['wht'] ?? '', false, 0, false, 2) ?? 0.0);
            if ($amt + $wht == 0.0) {
                continue;
            }
            if (self::over($amt + $wht, $balance)) {
                $c->fail("lines.$ar.amt", t('accounting.over_balance', ['max' => number_format($balance, 2)]));
            }
            $apply[$ar] = [$amt, $wht];
        }
        if (!$apply && !$c->errors) {
            $c->fail('lines', t('accounting.nothing_applied'));
        }
        if ($apply && round(array_sum(array_map(fn ($x) => $x[0], $apply)), 2) < 0) {
            $c->fail('lines', t('accounting.credit_over_invoices'));
        }
        $c->done();

        $nbr = App::db()->transaction(function (Db $db) use ($customer, $date, $bank, $remarks, $apply) {
            // Lock the receivables and check the balance again: two cashiers may apply at once.
            $marks = implode(',', array_fill(0, count($apply), '?'));
            $now = array_column($db->all(
                "SELECT ar_nbr, ar_amt, ar_paid_amt, ar_status FROM ar_mstr WHERE ar_nbr IN ($marks) FOR UPDATE",
                array_keys($apply)
            ), null, 'ar_nbr');
            $nbr = Numbering::next('CR', $date);
            $user = Auth::userId();
            $sumAmt = $sumWht = $sumVat = 0.0;
            $n = 0;
            foreach ($apply as $ar => [$amt, $wht]) {
                $row = $now[$ar] ?? null;
                $left = $row ? round((float) $row['ar_amt'] - (float) $row['ar_paid_amt'], 2) : 0;
                if (!$row || $row['ar_status'] !== 'O' || self::over($amt + $wht, $left)) {
                    throw HttpException::invalid(["lines.$ar.amt" => t('accounting.over_balance', ['max' => number_format($left, 2)])]);
                }
                [$net, $vat] = self::vatDue($db, $ar, $amt + $wht, (float) $row['ar_amt'], $left);
                $db->insert('crd_det', [
                    'crd_nbr' => $nbr, 'crd_line' => ++$n, 'crd_ar_nbr' => $ar, 'crd_amt' => $amt, 'crd_wht_amt' => $wht,
                    'crd_net_amt' => $net, 'crd_vat_amt' => $vat, 'created_by' => $user, 'updated_by' => $user,
                ]);
                $sumVat += $vat;
                self::settle($ar, $amt + $wht);
                $sumAmt += $amt;
                $sumWht += $wht;
            }
            $db->insert('cr_mstr', [
                'cr_nbr' => $nbr, 'cr_bank' => $bank, 'cr_cm_code' => $customer, 'cr_date' => $date, 'cr_amt' => round($sumAmt, 2),
                'cr_wht_amt' => round($sumWht, 2), 'cr_ar_nbr' => array_key_first($apply), 'cr_status' => 'P', 'cr_remarks' => $remarks,
                'created_by' => $user, 'updated_by' => $user,
            ]);
            Gl::post($nbr, 'CR', $date, [
                [self::bankAcct($bank), $sumAmt],
                [Gl::acct('wht_ar'), $sumWht],
                [self::arAcct($customer), -($sumAmt + $sumWht)],
                // a service's output VAT falls due now: from "not yet due" to output VAT
                [Gl::acct('vat_defer'), round($sumVat, 2)],
                [Gl::acct('vat_out'), -round($sumVat, 2)],
            ], t('accounting.desc_receipt', ['nbr' => $nbr, 'cm' => $customer]));
            if (PettyCash::isPetty($bank)) {
                // received in cash: into the receiver's own petty cash
                PettyCash::fromDocument($db, $nbr, 'DI', $date, round($sumAmt, 2),
                    (string) ($db->value('SELECT cm_name FROM cm_mstr WHERE cm_code = ?', [$customer]) ?: $customer), 'cr_date');
            }
            return $nbr;
        });
        return ['nbr' => $nbr];
    }

    /**
     * The output VAT a payment of a service invoice makes due (ม.78/1): its share of the invoice's VAT, and of its value,
     * in baht — the last payment takes what is left so the shares add up to the invoice. [0, 0] for goods.
     * @return array{0: float, 1: float} [value, VAT]
     */
    private static function vatDue(Db $db, string $ar, float $settled, float $arAmt, float $left): array
    {
        $ih = $db->one('SELECT ih_vat_on_rcpt, ih_net_amt, ih_tax_amt, ih_ex_rate FROM ih_mstr WHERE ih_inv_nbr = ?', [$ar]);
        if (!$ih || !$ih['ih_vat_on_rcpt'] || $arAmt == 0.0) {
            return [0.0, 0.0];
        }
        $rate = (float) $ih['ih_ex_rate'];
        $netAll = round((float) $ih['ih_net_amt'] * $rate, 2);
        $vatAll = round((float) $ih['ih_tax_amt'] * $rate, 2);
        if (round($settled - $left, 2) == 0.0) {
            $done = $db->one(
                "SELECT COALESCE(SUM(d.crd_net_amt), 0) AS net, COALESCE(SUM(d.crd_vat_amt), 0) AS vat FROM crd_det d
                   JOIN cr_mstr c ON c.cr_nbr = d.crd_nbr WHERE d.crd_ar_nbr = ? AND c.cr_status = 'P'",
                [$ar]
            );
            return [round($netAll - (float) $done['net'], 2), round($vatAll - (float) $done['vat'], 2)];
        }
        $share = $settled / $arAmt;
        return [round($netAll * $share, 2), round($vatAll * $share, 2)];
    }

    /** Undo a receipt: the receivables open again and the posting is reversed. */
    #[Post('/api/cash-receipts/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        $h = self::head($nbr);
        if ($h['cr_status'] !== 'P') {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->transaction(function (Db $db) use ($nbr, $h) {
            $db->one('SELECT cr_status FROM cr_mstr WHERE cr_nbr = ? FOR UPDATE', [$nbr]);
            foreach (self::lines($nbr) as $l) {
                $db->one('SELECT 1 FROM ar_mstr WHERE ar_nbr = ? FOR UPDATE', [$l['crd_ar_nbr']]);
                self::settle($l['crd_ar_nbr'], -((float) $l['crd_amt'] + (float) $l['crd_wht_amt']));
            }
            $db->update('cr_mstr', ['cr_status' => 'C', 'updated_by' => Auth::userId()], ['cr_nbr' => $nbr]);
            PettyCash::documentCancelled($db, $nbr);
            if (Gl::reconciled($nbr, 'CR')) {
                throw new HttpException(409, t('accounting.reconciled'));
            }
            Gl::reverse($nbr, 'CR', max(date('Y-m-d'), Gl::periodStart()), t('accounting.desc_cancel', ['nbr' => $nbr]));
            Audit::updated('cr_mstr', "cr_nbr=$nbr", $h, ['cr_status' => 'C']);
        });
        return null;
    }

    /**
     * Add $amount (baht, negative to undo) to a receivable's paid amount and carry it to the
     * invoice (in its own currency) and to any billing note the invoice is on.
     */
    private static function settle(string $ar, float $amount): void
    {
        $db = App::db();
        $user = Auth::userId();
        $db->run(
            "UPDATE ar_mstr SET ar_paid_amt = ROUND(ar_paid_amt + ?, 2),
                    ar_status = IF(ROUND(ABS(ar_paid_amt), 2) >= ROUND(ABS(ar_amt), 2), 'P', 'O'), updated_by = ?
              WHERE ar_nbr = ?",
            [$amount, $user, $ar]
        );
        $db->run(
            "UPDATE ih_mstr h JOIN ar_mstr a ON a.ar_nbr = h.ih_inv_nbr
                SET h.ih_paid_amt = IF(a.ar_status = 'P', h.ih_tot_amt, ROUND(h.ih_tot_amt * a.ar_paid_amt / NULLIF(a.ar_amt, 0), 2)),
                    h.ih_status = IF(a.ar_status = 'P', 'P', 'U'), h.updated_by = ?
              WHERE h.ih_inv_nbr = ? AND h.ih_status <> 'C'",
            [$user, $ar]
        );
        // Billing notes holding this invoice: paid when all their invoices are.
        foreach ($db->all("SELECT DISTINCT bld_nbr FROM bld_det d JOIN bl_mstr b ON b.bl_nbr = d.bld_nbr
                            WHERE d.bld_inv_nbr = ? AND b.bl_status IN ('O', 'P')", [$ar]) as $bl) {
            $db->run(
                "UPDATE bl_mstr b SET
                        b.bl_paid_amt = (SELECT COALESCE(SUM(IF(d.bld_bal_amt < 0, -- a credit note: negative balance, negative payments
                                               GREATEST(LEAST(h.ih_paid_amt - d.bld_paid_amt, 0), d.bld_bal_amt),
                                               LEAST(GREATEST(h.ih_paid_amt - d.bld_paid_amt, 0), d.bld_bal_amt))), 0)
                                           FROM bld_det d JOIN ih_mstr h ON h.ih_inv_nbr = d.bld_inv_nbr WHERE d.bld_nbr = b.bl_nbr),
                        b.bl_status = IF(EXISTS (SELECT 1 FROM bld_det d JOIN ih_mstr h ON h.ih_inv_nbr = d.bld_inv_nbr
                                                  WHERE d.bld_nbr = b.bl_nbr AND h.ih_status <> 'P'), 'O', 'P'),
                        b.updated_by = ?
                  WHERE b.bl_nbr = ?",
                [$user, $bl['bld_nbr']]
            );
        }
    }

    /** More than is left: for an invoice above its balance, for a credit note (negative balance) beyond it or positive. */
    private static function over(float $apply, float $balance): bool
    {
        return $balance < 0 ? (round($apply - $balance, 2) < 0 || $apply > 0) : round($apply - $balance, 2) > 0;
    }

    private static function openItems(string $customer): array
    {
        return App::db()->all(
            "SELECT a.ar_nbr, a.ar_nbr AS doc, CONCAT('/invoices/', a.ar_nbr) AS link, a.ar_date AS date, a.ar_due_date AS due, a.ar_amt AS amount,
                    ROUND(a.ar_amt - a.ar_paid_amt, 2) AS balance
               FROM ar_mstr a LEFT JOIN ih_mstr h ON h.ih_inv_nbr = a.ar_nbr
              WHERE a.ar_cm_code = ? AND a.ar_status = 'O' AND a.ar_amt <> a.ar_paid_amt
              ORDER BY a.ar_due_date, a.ar_nbr",
            [$customer]
        );
    }

    public static function bankAcct(string $bank): string
    {
        return (string) App::db()->value('SELECT bk_acct FROM bk_mstr WHERE bk_bank = ?', [$bank]);
    }

    private static function arAcct(string $customer): string
    {
        return (Sales::customer($customer)['cm_ar_acct'] ?? '') ?: Gl::acct('ar');
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT r.*, c.cm_name, k.bk_desc FROM cr_mstr r
               LEFT JOIN cm_mstr c ON c.cm_code = r.cr_cm_code LEFT JOIN bk_mstr k ON k.bk_bank = r.cr_bank
              WHERE r.cr_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, a.ar_date, a.ar_due_date, a.ar_amt FROM crd_det d LEFT JOIN ar_mstr a ON a.ar_nbr = d.crd_ar_nbr
              WHERE d.crd_nbr = ? ORDER BY d.crd_line',
            [$nbr]
        );
    }

    private function page(?string $nbr): string
    {
        return View::render('accounting/settle', [
            'docNbr' => $nbr === null ? Numbering::peek('CR') : null,
            'title'   => $nbr ?? t('accounting.cr_new'),
            'help'    => 'cash-receipts',
            'config'  => [
                'nbr'       => $nbr,
                'party'     => 'customer',
                'api'       => '/api/cash-receipts',
                'page'      => '/cash-receipts',
                'partyApi'  => '/api/sales/customers',
                'partyKey'  => 'cm_code',
                'partyName' => 'cm_name',
                'status'    => self::statusText(),
                'banks'     => Lookup::options('banks'),
                'canPrint'  => Auth::can('cash-receipts', 'print'),
                'canCancel' => Auth::can('cash-receipts', 'delete'),
                // the signed ใบรับ for the Revenue Department, once a signing certificate is set up
                'xml'       => is_file(Etax::certFile()) && Auth::can('etax', 'print') ? '/api/etax/receipts/{nbr}/xml' : null,
                'today'     => date('Y-m-d'),
            ],
            'scripts' => ['js/pages/settle.js'],
        ]);
    }

    public static function statusText(): array
    {
        return [
            'P' => ['text' => t('accounting.posted'), 'color' => 'green'],
            'C' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }
}
