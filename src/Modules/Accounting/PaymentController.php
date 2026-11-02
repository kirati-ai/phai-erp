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
use Phai\Modules\Sales\SalesPrint;

/**
 * Payments (PV): money out to a vendor against its vouchers (VO), optionally withholding
 * tax (a 50 ทวิ certificate is issued). Status: P posted, C cancelled.
 * Dr vouchers payable / Cr bank, Cr withholding tax payable.
 */
#[Program('vouchers')]
final class PaymentController
{
    #[Get('/vouchers')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('accounting.pv_title'),
            'help'    => 'vouchers',
            'config'  => [
                'api'     => '/api/vouchers',
                'files'   => 'PV',
                'page'    => '/vouchers',
                'canAdd'  => Auth::can('vouchers', 'add'),
                'status'  => CashReceiptController::statusText(),
                'columns' => [
                    ['key' => 'vo_nbr', 'label' => t('accounting.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'vo_date', 'label' => t('accounting.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'vo_vd_code', 'label' => t('purchase.vendor'), 'sort' => true],
                    ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                    ['key' => 'vo_bank', 'label' => t('accounting.bank')],
                    ['key' => 'vo_amt', 'label' => t('accounting.amount'), 'type' => 'money', 'sort' => true],
                    ['key' => 'vo_wht_amt', 'label' => t('accounting.wht_amt'), 'type' => 'money'],
                    ['key' => 'vo_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Vouchers not fully paid above the list: one click opens a new payment for that voucher. */
    public static function queues(): array
    {
        return Auth::can('vouchers', 'add') ? [[
            'title'   => t('accounting.q_pay'),
            'api'     => '/api/vouchers/waiting',
            'key'     => 'apv_nbr',
            'urgent'  => 'overdue',
            'columns' => [
                ['key' => 'apv_nbr', 'label' => t('accounting.type_VO'), 'link' => '/ap-vouchers/{apv_nbr}'],
                ['key' => 'apv_date', 'label' => t('accounting.date'), 'type' => 'date'],
                ['key' => 'vendor', 'label' => t('purchase.vendor')],
                ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                ['key' => 'apv_pay_date', 'label' => t('accounting.apv_pay_date'), 'type' => 'date'],
                ['key' => 'balance', 'label' => t('accounting.balance'), 'type' => 'money'],
            ],
            'action'  => ['label' => t('accounting.q_pay_make'), 'icon' => 'ti ti-cash-banknote', 'href' => '/vouchers/new?vendor={vendor}&vo={apv_nbr}'],
        ]] : [];
    }

    #[Get('/vouchers/new')]
    public function newPage(): string
    {
        return $this->page(null);
    }

    #[Get('/vouchers/{nbr}')]
    public function viewPage(string $nbr): string
    {
        self::head($nbr);
        return $this->page($nbr);
    }

    #[Get('/vouchers/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $m = fn ($v) => number_format((float) $v, 2);
        $lines = self::lines($nbr);
        $net = (float) $h['vo_amt'] - (float) $h['vo_wht_amt'];
        return SalesPrint::render([
            'title' => t('accounting.pv_doc'),
            'nbr'   => $nbr,
            'meta'  => [[t('accounting.date'), SalesPrint::date($h['vo_date'])], [t('accounting.bank'), $h['bk_desc'] ?? $h['vo_bank']]],
            'party' => ['label' => t('accounting.pay_to')] + SalesPrint::vendor($h['vo_vd_code']),
            'lines' => [
                'columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('accounting.apv_title')], ['label' => t('accounting.vendor_inv')],
                    ['label' => t('accounting.amount'), 'align' => 'r', 'width' => '110px'],
                ],
                'rows' => self::rowsWithItems($lines, $m),
            ],
            'totals' => [
                'rows'  => [
                    [t('sales.total'), $m($h['vo_amt']), 'bold'],
                    ...array_map(fn ($w) => [t('accounting.wht_est', ['pct' => WhtController::pct($w['pct'])]), '-' . $m($w['tax']), false],
                        self::byRate($h['whtc_nbr'] ? self::whtLines($h['whtc_nbr']) : [])),
                    [t('accounting.net_to_pay'), $m($net), true],
                ],
                'words' => BahtText::of($net),
            ],
            'notes' => $h['vo_remarks'],
            'signs' => [t('accounting.sign_prepared'), SalesPrint::approve(t('accounting.sign_approved')), SalesPrint::outside(t('accounting.sign_receiver'))],
        ]);
    }

    #[Describe('Payments to vendors (baht) with tax withheld', ['q' => 'number or vendor', 'status' => 'P posted, C cancelled'])]

    #[Get('/api/vouchers')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['P', 'C'], true)) {
            $where[] = 'p.vo_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(p.vo_nbr LIKE ? OR p.vo_vd_code LIKE ? OR v.vd_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT p.vo_nbr, p.vo_date, p.vo_vd_code, v.vd_name, p.vo_bank, p.vo_amt, p.vo_wht_amt, p.vo_status',
            'FROM vo_mstr p LEFT JOIN vd_mstr v ON v.vd_code = p.vo_vd_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'vo_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['vo_nbr' => 'p.vo_nbr', 'vo_date' => 'p.vo_date', 'vo_vd_code' => 'p.vo_vd_code', 'vo_amt' => 'p.vo_amt', 'vo_status' => 'p.vo_status'],
            'vo_nbr'
        );
    }

    /** ?vendor= — vouchers still to pay (see openItems). */
    #[Describe('Vouchers of one vendor still to pay', ['vendor' => 'vendor code'])]
    #[Get('/api/vouchers/open')]
    public function open(Request $request): array
    {
        return self::openItems((string) $request->input('vendor', ''));
    }

    #[Describe('Vouchers of all vendors not fully paid, by pay date (baht; overdue = pay date passed)')]
    #[Get('/api/vouchers/waiting')]
    public function waiting(): array
    {
        $rows = App::db()->all(
            "SELECT v.apv_nbr, v.apv_date, v.apv_vd_code AS vendor, d.vd_name, v.apv_pay_date,
                    ROUND(v.apv_amt - v.apv_paid_amt, 2) AS balance, (v.apv_pay_date < CURDATE()) AS overdue
               FROM apv_mstr v LEFT JOIN vd_mstr d ON d.vd_code = v.apv_vd_code
              WHERE v.apv_status = 'O' AND v.apv_amt > v.apv_paid_amt
              ORDER BY v.apv_pay_date IS NULL, v.apv_pay_date, v.apv_date, v.apv_nbr LIMIT 200"
        );
        foreach ($rows as &$r) {
            $r['overdue'] = (bool) $r['overdue'];
        }
        return $rows;
    }

    #[Describe('One payment with the vouchers it paid')]

    #[Get('/api/vouchers/{nbr}')]
    public function show(string $nbr): array
    {
        $h = self::head($nbr);
        return [
            'head'  => [
                'party' => $h['vo_vd_code'], 'party_name' => $h['vd_name'], 'date' => $h['vo_date'], 'bank' => $h['vo_bank'],
                'remarks' => $h['vo_remarks'], 'status' => $h['vo_status'], 'wht_code' => $h['vo_wht_code'], 'wht_form' => $h['vo_wht_form'],
                'wht_amt' => $h['vo_wht_amt'], 'wht_base' => $h['whtc_base_amt'], 'whtc_nbr' => $h['whtc_nbr'],
                'wht' => $h['whtc_nbr'] ? self::whtLines($h['whtc_nbr']) : [],
                'reconciled' => Gl::reconciled($nbr, 'PV'),
            ],
            'lines' => array_map(fn ($l) => [
                'doc' => $l['vod_ap_ref'], 'link' => '/ap-vouchers/' . $l['vod_ap_ref'], 'date' => $l['ap_date'], 'due' => $l['ap_due_date'],
                'amount' => $l['ap_amt'], 'applied' => $l['vod_amt'],
            ], self::lines($nbr)),
        ];
    }

    /**
     * Body: {vendor, vo_date, vo_bank, vo_remarks, vo_wht_form, wht: [{code, base}], lines: {ap_ref: {amt}}}
     * — one payment may withhold at several rates (e.g. services 3% and transport 1%), all on one 50 ทวิ.
     * The older single rate (vo_wht_code + wht_base) is still taken.
     */
    #[Post('/api/vouchers')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $vendor = $c->exists('vendor', $b['vendor'] ?? '', 'vd_mstr', 'vd_code');
        $date = $c->date('vo_date', $b['vo_date'] ?? '', true);
        if ($date && $date < Gl::periodStart()) {
            $c->fail('vo_date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $bank = $c->exists('vo_bank', $b['vo_bank'] ?? '', 'bk_mstr', 'bk_bank', true, 'bk_active = 1');
        $remarks = $c->text('vo_remarks', $b['vo_remarks'] ?? '', false, 2000, false);
        $form = in_array($b['vo_wht_form'] ?? '', ['PND3', 'PND53'], true) ? $b['vo_wht_form'] : 'PND53';
        $whts = self::whtInput($c, $b);
        $c->done();

        $open = array_column(self::openItems((string) $vendor), null, 'doc');
        $apply = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $ref => $l) {
            if (!is_array($l) || !isset($open[$ref])) {
                continue;
            }
            // a vendor credit note has a negative balance: applying it takes a negative amount
            $balance = (float) $open[$ref]['balance'];
            $amt = $c->number("lines.$ref.amt", $l['amt'] ?? '', false, $balance < 0 ? null : 0, false, 2) ?? 0.0;
            if ($amt == 0.0) {
                continue;
            }
            if (self::over($amt, $balance)) {
                $c->fail("lines.$ref.amt", t('accounting.over_balance', ['max' => number_format($balance, 2)]));
            }
            $apply[$ref] = $amt;
        }
        if (!$apply && !$c->errors) {
            $c->fail('lines', t('accounting.nothing_applied'));
        }
        $total = round(array_sum($apply), 2);
        if ($apply && $total <= 0) {
            $c->fail('lines', t('accounting.credit_over_payables'));
        }
        if ($whts && round(array_sum(array_column($whts, 'base')) - $total, 2) > 0) {
            $c->fail(end($whts)['field'], t('accounting.base_over'));
        }
        $c->done();

        $nbr = App::db()->transaction(function (Db $db) use ($vendor, $date, $bank, $remarks, $whts, $form, $apply, $total) {
            $marks = implode(',', array_fill(0, count($apply), '?'));
            $now = array_column($db->all(
                "SELECT apv_nbr AS ap_ref, apv_amt AS ap_amt, apv_paid_amt AS ap_paid_amt, apv_status AS ap_status FROM apv_mstr WHERE apv_nbr IN ($marks) FOR UPDATE",
                array_keys($apply)
            ), null, 'ap_ref');
            // each rate as it stands today; the certificate keeps it, so a later change of the code does not alter it
            foreach ($whts as &$w) {
                $m = $db->one('SELECT wht_desc, wht_pct, wht_acct, wht_form_row FROM wht_mstr WHERE wht_code = ?', [$w['code']]);
                $w += $m;
                $w['tax'] = round($w['base'] * (float) $m['wht_pct'] / 100, 2);
            }
            unset($w);
            $whtAmt = round(array_sum(array_column($whts, 'tax')), 2);
            // withholding is shared over the invoices paid, not the credit notes set against them
            $paid = array_filter($apply, fn ($a) => $a > 0);
            $shares = $whtAmt ? Gl::fit(array_map(fn ($a) => $whtAmt * $a / array_sum($paid), $paid), $whtAmt) : [];

            $nbr = Numbering::next('PV', $date);
            $user = Auth::userId();
            $n = 0;
            foreach ($apply as $ref => $amt) {
                $row = $now[$ref] ?? null;
                $left = $row ? round((float) $row['ap_amt'] - (float) $row['ap_paid_amt'], 2) : 0;
                if (!$row || $row['ap_status'] !== 'O' || self::over($amt, $left)) {
                    throw HttpException::invalid(["lines.$ref.amt" => t('accounting.over_balance', ['max' => number_format($left, 2)])]);
                }
                $w = $shares[$ref] ?? 0.0;
                $db->insert('vod_det', [
                    'vod_nbr' => $nbr, 'vod_line' => ++$n, 'vod_ap_ref' => $ref, 'vod_amt' => $amt, 'vod_wht_amt' => $w,
                    'vod_net_amt' => round($amt - $w, 2), 'created_by' => $user, 'updated_by' => $user,
                ]);
                self::settle($ref, $amt);
            }
            $db->insert('vo_mstr', [
                'vo_nbr' => $nbr, 'vo_bank' => $bank, 'vo_vd_code' => $vendor, 'vo_date' => $date, 'vo_amt' => $total,
                'vo_wht_code' => $whts[0]['code'] ?? null, 'vo_wht_amt' => $whtAmt, 'vo_wht_form' => $whts ? $form : null,
                'vo_ap_ref' => array_key_first($apply), 'vo_status' => 'P', 'vo_remarks' => $remarks, 'created_by' => $user, 'updated_by' => $user,
            ]);
            if ($whtAmt > 0) {
                $cert = Numbering::next('WHT', $date);
                $db->insert('wht_cert', [
                    'whtc_nbr' => $cert, 'whtc_type' => $whts[0]['code'], 'whtc_doc_ref' => $nbr, 'whtc_vd_cm_code' => $vendor,
                    'whtc_date' => $date, 'whtc_form_type' => $form, 'whtc_base_amt' => round(array_sum(array_column($whts, 'base')), 2),
                    'whtc_tax_amt' => $whtAmt, 'whtc_status' => 'A', 'created_by' => $user, 'updated_by' => $user,
                ]);
                foreach (array_values($whts) as $i => $w) {
                    $db->insert('whtd_det', [
                        'whtd_nbr' => $cert, 'whtd_line' => $i + 1, 'whtd_code' => $w['code'], 'whtd_desc' => $w['wht_desc'],
                        'whtd_pct' => $w['wht_pct'], 'whtd_form_row' => $w['wht_form_row'], 'whtd_base_amt' => $w['base'],
                        'whtd_tax_amt' => $w['tax'], 'created_by' => $user, 'updated_by' => $user,
                    ]);
                }
            }
            $apAcct = Gl::acct('vo'); // vouchers payable: the voucher moved the payables there
            $post = [
                [$apAcct, $total],
                [CashReceiptController::bankAcct($bank), -round($total - $whtAmt, 2)],
            ];
            foreach ($whts as $w) {
                // the tax withheld is owed to the Revenue Department, never to the vendor: the code must name its account
                if (!$w['wht_acct']) {
                    throw HttpException::invalid(['wht' => t('accounting.wht_no_acct', ['code' => $w['code'] ?? ''])]);
                }
                $post[] = [$w['wht_acct'], -$w['tax']];
            }
            Gl::post($nbr, 'PV', $date, $post, t('accounting.desc_payment', ['nbr' => $nbr, 'vd' => $vendor]));
            if (PettyCash::isPetty($bank)) {
                // paid in cash: out of the payer's own petty cash
                PettyCash::fromDocument($db, $nbr, 'DO', $date, round($total - $whtAmt, 2),
                    (string) ($db->value('SELECT vd_name FROM vd_mstr WHERE vd_code = ?', [$vendor]) ?: $vendor), 'vo_date');
            }
            return $nbr;
        });
        return ['nbr' => $nbr];
    }

    #[Post('/api/vouchers/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        App::db()->transaction(function (Db $db) use ($nbr) {
            $h = $db->one('SELECT * FROM vo_mstr WHERE vo_nbr = ? FOR UPDATE', [$nbr]) ?? throw HttpException::notFound();
            if ($h['vo_status'] !== 'P') {
                throw new HttpException(409, t('purchase.wrong_status'));
            }
            foreach (self::lines($nbr) as $l) {
                $db->one('SELECT 1 FROM apv_mstr WHERE apv_nbr = ? FOR UPDATE', [$l['vod_ap_ref']]);
                self::settle($l['vod_ap_ref'], -(float) $l['vod_amt']);
            }
            $db->update('vo_mstr', ['vo_status' => 'C', 'updated_by' => Auth::userId()], ['vo_nbr' => $nbr]);
            PettyCash::documentCancelled($db, $nbr);
            $db->run("UPDATE wht_cert SET whtc_status = 'C', updated_by = ? WHERE whtc_doc_ref = ?", [Auth::userId(), $nbr]);
            if (Gl::reconciled($nbr, 'PV')) {
                throw new HttpException(409, t('accounting.reconciled'));
            }
            Gl::reverse($nbr, 'PV', max(date('Y-m-d'), Gl::periodStart()), t('accounting.desc_cancel', ['nbr' => $nbr]));
            Audit::updated('vo_mstr', "vo_nbr=$nbr", $h, ['vo_status' => 'C']);
        });
        return null;
    }

    /**
     * The rates withheld: [{code, base, field}] where field names the input for an error. wht: [{code, base}] from the
     * screen (an empty row is skipped, a code only once); the older vo_wht_code + wht_base gives one rate.
     */
    private static function whtInput(Check $c, array $b): array
    {
        $rows = is_array($b['wht'] ?? null)
            ? array_values($b['wht'])
            : [['code' => $b['vo_wht_code'] ?? '', 'base' => $b['wht_base'] ?? '', 'legacy' => true]];
        $out = [];
        foreach ($rows as $i => $r) {
            $r = is_array($r) ? $r : [];
            $legacy = !empty($r['legacy']);
            $fCode = $legacy ? 'vo_wht_code' : "wht.$i.code";
            $fBase = $legacy ? 'wht_base' : "wht.$i.base";
            if (trim((string) ($r['code'] ?? '')) === '') {
                if (!$legacy && trim((string) ($r['base'] ?? '')) !== '') {
                    $c->fail($fCode, t('validation.required'));
                }
                continue;
            }
            $code = $c->exists($fCode, $r['code'], 'wht_mstr', 'wht_code', true, 'wht_active = 1');
            $base = $c->number($fBase, $r['base'] ?? '', true, 0, true, 2);
            if ($code !== null && in_array($code, array_column($out, 'code'), true)) {
                $c->fail($fCode, t('accounting.wht_twice'));
                continue;
            }
            if ($code !== null && $base !== null) {
                $out[] = ['code' => $code, 'base' => $base, 'field' => $fBase];
            }
        }
        return $out;
    }

    /** The rates on one 50 ทวิ: code, desc, pct, form row, base, tax. */
    /** Withholding rows highest rate first, as on the payable and the voucher. */
    private static function byRate(array $rows): array
    {
        usort($rows, fn ($a, $b) => $b['pct'] <=> $a['pct']);
        return $rows;
    }

    public static function whtLines(string $cert): array
    {
        return array_map(fn ($l) => [
            'code' => $l['whtd_code'], 'desc' => $l['whtd_desc'], 'pct' => (float) $l['whtd_pct'], 'row' => $l['whtd_form_row'],
            'base' => round((float) $l['whtd_base_amt'], 2), 'tax' => round((float) $l['whtd_tax_amt'], 2),
        ], App::db()->all('SELECT * FROM whtd_det WHERE whtd_nbr = ? ORDER BY whtd_line', [$cert]));
    }

    /** Add $amount (negative to undo) to a voucher's paid amount; paid in full = status P. */
    private static function settle(string $ref, float $amount): void
    {
        App::db()->run(
            "UPDATE apv_mstr SET apv_paid_amt = ROUND(apv_paid_amt + ?, 2),
                    apv_status = IF(ROUND(apv_paid_amt, 2) >= ROUND(apv_amt, 2), 'P', 'O'), updated_by = ?
              WHERE apv_nbr = ?",
            [$amount, Auth::userId(), $ref]
        );
    }

    /** More than is left: for a payable above its balance, for a credit note (negative balance) beyond it or positive. */
    private static function over(float $apply, float $balance): bool
    {
        return $balance < 0 ? (round($apply - $balance, 2) < 0 || $apply > 0) : round($apply - $balance, 2) > 0;
    }

    /** Vouchers of one vendor still to pay, oldest pay date first; ratio = share before VAT (base for withholding). */
    private static function openItems(string $vendor): array
    {
        $rows = App::db()->all(
            "SELECT v.apv_nbr AS doc, CONCAT('/ap-vouchers/', v.apv_nbr) AS link, v.apv_date AS date, v.apv_pay_date AS due, v.apv_amt AS amount,
                    ROUND(v.apv_amt - v.apv_paid_amt, 2) AS balance,
                    (SELECT GROUP_CONCAT(p.ap_inv_nbr SEPARATOR ', ') FROM apvd_det x JOIN ap_mstr p ON p.ap_ref = x.apvd_ap_ref
                      WHERE x.apvd_nbr = v.apv_nbr) AS ap_inv_nbr,
                    COALESCE((SELECT SUM(x.apvd_amt * COALESCE((SELECT SUM(d.apd_amt) FROM apd_det d WHERE d.apd_ref = x.apvd_ap_ref AND d.apd_acct <> ?), p.ap_amt) / NULLIF(p.ap_amt, 0))
                                FROM apvd_det x JOIN ap_mstr p ON p.ap_ref = x.apvd_ap_ref WHERE x.apvd_nbr = v.apv_nbr), v.apv_amt)
                        / NULLIF(v.apv_amt, 0) AS ratio
               FROM apv_mstr v
              WHERE v.apv_vd_code = ? AND v.apv_status = 'O' AND v.apv_amt > v.apv_paid_amt
              ORDER BY COALESCE(v.apv_pay_date, v.apv_date), v.apv_nbr",
            [Gl::acct('vat_in'), $vendor]
        );
        // what the lines of each voucher's payables withhold: {code: base} for the whole voucher (WhtPlan, as shown on it)
        foreach ($rows as &$r) {
            $r['wht'] = (object) array_column(WhtPlan::ofVoucher($r['doc']), 'base', 'code');
        }
        unset($r);
        return $rows;
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            "SELECT p.*, v.vd_name, v.vd_tax_id_num, k.bk_desc, w.whtc_nbr, w.whtc_base_amt
               FROM vo_mstr p
               LEFT JOIN vd_mstr v ON v.vd_code = p.vo_vd_code
               LEFT JOIN bk_mstr k ON k.bk_bank = p.vo_bank
               LEFT JOIN wht_cert w ON w.whtc_doc_ref = p.vo_nbr
              WHERE p.vo_nbr = ?",
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    /** Each voucher paid as a printed row; under it its payables (vendor invoice) and what each was for (ApItems). */
    private static function rowsWithItems(array $lines, callable $m): array
    {
        $rows = [];
        foreach ($lines as $l) {
            $rows[] = [$l['vod_line'], $l['vod_ap_ref'], $l['ap_inv_nbr'] ?? '', $m($l['vod_amt'])];
            $payables = ApVoucherController::lines($l['vod_ap_ref']);
            $items = ApItems::of(array_column($payables, 'apvd_ap_ref'));
            foreach ($payables as $p) {
                $rows[] = SalesPrint::sub($p['apvd_ap_ref'] . ($p['ap_inv_nbr'] ? ' · ' . $p['ap_inv_nbr'] : '') . ' = ' . $m($p['apvd_amt']));
                array_push($rows, ...array_map(fn ($t) => SalesPrint::sub("\u{2003}$t"), $items[$p['apvd_ap_ref']] ?? []));
            }
        }
        return $rows;
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, a.apv_date AS ap_date, a.apv_pay_date AS ap_due_date, a.apv_amt AS ap_amt,
                    (SELECT GROUP_CONCAT(p.ap_inv_nbr SEPARATOR \', \') FROM apvd_det x JOIN ap_mstr p ON p.ap_ref = x.apvd_ap_ref
                      WHERE x.apvd_nbr = d.vod_ap_ref) AS ap_inv_nbr
               FROM vod_det d LEFT JOIN apv_mstr a ON a.apv_nbr = d.vod_ap_ref
              WHERE d.vod_nbr = ? ORDER BY d.vod_line',
            [$nbr]
        );
    }

    private function page(?string $nbr): string
    {
        return View::render('accounting/settle', [
            'docNbr' => $nbr === null ? Numbering::peek('PV') : null,
            'title'   => $nbr ?? t('accounting.pv_new'),
            'help'    => 'vouchers',
            'config'  => [
                'nbr'       => $nbr,
                'party'     => 'vendor',
                'api'       => '/api/vouchers',
                'page'      => '/vouchers',
                'partyApi'  => '/api/purchase/vendors',
                'partyKey'  => 'vd_code',
                'partyName' => 'vd_name',
                'status'    => CashReceiptController::statusText(),
                'banks'     => Lookup::options('banks'),
                'whtCodes'  => App::db()->all('SELECT wht_code, wht_desc, wht_pct FROM wht_mstr WHERE wht_active = 1 ORDER BY wht_code'),
                'canPrint'  => Auth::can('vouchers', 'print'),
                'canCancel' => Auth::can('vouchers', 'delete'),
                'today'     => date('Y-m-d'),
            ],
            'scripts' => ['js/pages/settle.js'],
        ]);
    }
}
