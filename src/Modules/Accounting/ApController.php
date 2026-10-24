<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Crud\Lookup;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Master\Money;

/**
 * Payables: a vendor's invoice booked against goods received (clears GRNI) and/or
 * expense lines. Amounts in baht. Status: O open, P paid, C cancelled.
 * Dr GRNI / expenses, Dr input VAT / Cr payable.
 */
#[Program('payables')]
final class ApController
{
    #[Get('/payables')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('accounting.ap_title'),
            'help'    => 'payables',
            'config'  => [
                'api'     => '/api/payables',
                'files'   => 'AP',
                'page'    => '/payables',
                'canAdd'  => Auth::can('payables', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'ap_ref', 'label' => t('accounting.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'type_text', 'label' => t('accounting.ap_type')],
                    ['key' => 'ap_date', 'label' => t('accounting.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'ap_vd_code', 'label' => t('purchase.vendor'), 'sort' => true],
                    ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                    ['key' => 'ap_inv_nbr', 'label' => t('accounting.vendor_inv')],
                    ['key' => 'ap_due_date', 'label' => t('sales.due_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'ap_amt', 'label' => t('accounting.amount'), 'type' => 'money', 'sort' => true],
                    ['key' => 'ap_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Receipts and landed costs waiting for the vendor's invoice above the list: tick rows of one vendor to book one payable. */
    public static function queues(): array
    {
        return Auth::can('payables', 'add') ? [[
            'title'   => t('accounting.rc_waiting_title'),
            'api'     => '/api/payables/waiting',
            'key'     => 'rc_nbr',
            'columns' => [
                ['key' => 'src', 'label' => t('purchase.rc_title'), 'link' => '/receipts/{src}'],
                ['key' => 'kind', 'label' => t('accounting.ap_type')],
                ['key' => 'rc_date', 'label' => t('accounting.date'), 'type' => 'date'],
                ['key' => 'rc_vd_code', 'label' => t('purchase.vendor')],
                ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                ['key' => 'rc_po_nbr', 'label' => t('purchase.po')],
                ['key' => 'rc_inv_nbr', 'label' => t('accounting.vendor_inv')],
                ['key' => 'rc_tot_amt', 'label' => t('sales.total'), 'type' => 'money'],
            ],
            'multi'   => ['label' => t('accounting.rc_make_ap'), 'icon' => 'ti ti-file-invoice', 'same' => 'rc_vd_code',
                          'sameText' => t('accounting.rc_one_vendor'), 'href' => '/payables/new?vendor={rc_vd_code}&receipts={keys}'],
        ]] : [];
    }

    /** The receipts waiting for a payable now show above the payables list. */
    #[Get('/payables/receipts')]
    public function receiptsPage(): Response
    {
        return Response::redirect(url('/payables'));
    }

    #[Get('/payables/new')]
    public function newPage(): string
    {
        return $this->page(null);
    }

    #[Get('/payables/{ref}')]
    public function viewPage(string $ref): string
    {
        self::head($ref);
        return $this->page($ref);
    }

    #[Describe('Vendor invoices booked as payables (baht)', ['q' => 'number, vendor or vendor invoice', 'status' => 'O open, P paid, C cancelled'])]

    #[Get('/api/payables')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['O', 'P', 'C'], true)) {
            $where[] = 'a.ap_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(a.ap_ref LIKE ? OR a.ap_vd_code LIKE ? OR v.vd_name LIKE ? OR a.ap_inv_nbr LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $page = App::db()->page(
            'SELECT a.ap_ref, a.ap_type, a.ap_date, a.ap_vd_code, v.vd_name, a.ap_inv_nbr, a.ap_due_date, a.ap_amt, a.ap_status',
            'FROM ap_mstr a LEFT JOIN vd_mstr v ON v.vd_code = a.ap_vd_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'ap_ref', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['ap_ref' => 'a.ap_ref', 'ap_date' => 'a.ap_date', 'ap_vd_code' => 'a.ap_vd_code', 'ap_due_date' => 'a.ap_due_date',
             'ap_amt' => 'a.ap_amt', 'ap_status' => 'a.ap_status'],
            'ap_ref'
        );
        foreach ($page['rows'] as &$r) {
            $r['type_text'] = t('accounting.ap_type_' . ($r['ap_type'] === 'CM' ? 'CM' : 'IN'));
        }
        return $page;
    }

    /** ?vendor= — receipts not yet on a payable, with the vendor's terms. */
    #[Get('/api/payables/receipts')]
    public function receipts(Request $request): array
    {
        return self::uninvoiced((string) $request->input('vendor', ''));
    }

    #[Describe('Goods receipts and allocated landed costs (freight, duty, insurance) still waiting for the vendor invoice, oldest first (baht); rc_nbr of a landed cost is <receipt>~<cost type>')]
    #[Get('/api/payables/waiting')]
    public function waiting(): array
    {
        $rows = self::uninvoiced('');
        usort($rows, fn ($a, $b) => [$a['rc_date'], $a['rc_nbr']] <=> [$b['rc_date'], $b['rc_nbr']]);
        return array_slice($rows, 0, 200);
    }

    #[Describe('One payable with its account lines')]

    #[Get('/api/payables/{ref}')]
    public function show(string $ref): array
    {
        $head = self::head($ref);
        $head['_editable'] = self::editable($head) && Auth::can('payables', 'edit');
        return ['head' => $head, 'lines' => self::lines($ref)];
    }

    /**
     * Body: {vendor, ap_date, ap_inv_nbr, ap_due_date, ap_remarks, receipts: [rc_nbr], receipt_wht: {rc_nbr: wht code},
     *        tax_code, lines: {row: {acct, cc, desc, amt, wht}}}
     * A line's withholding code (apd_wht_code) is what the payment withholds on that line's amount.
     */
    #[Post('/api/payables')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $vendor = $c->exists('vendor', $b['vendor'] ?? '', 'vd_mstr', 'vd_code');
        $h = [
            'ap_date'     => $c->date('ap_date', $b['ap_date'] ?? '', true),
            'ap_inv_nbr'  => $c->text('ap_inv_nbr', $b['ap_inv_nbr'] ?? '', true, 50, false),
            'ap_due_date' => $c->date('ap_due_date', $b['ap_due_date'] ?? ''),
            'ap_remarks'  => $c->text('ap_remarks', $b['ap_remarks'] ?? '', false, 2000, false),
            // the payable account this vendor invoice is owed on; a voucher (VO) debits it when the payable is paid
            'ap_acct'     => $c->exists('ap_acct', $b['ap_acct'] ?? '', 'ac_mstr', 'ac_acct', false, 'ac_active = 1'),
        ];
        if ($h['ap_date'] && $h['ap_date'] < Gl::periodStart()) {
            $c->fail('ap_date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $taxCode = $c->exists('tax_code', $b['tax_code'] ?? '', 'tx_mstr', 'tx_tax_code', false);
        // a vendor credit note (CM) reduces an earlier payable of the same vendor; it takes expense lines only
        $type = ($b['ap_type'] ?? 'IN') === 'CM' ? 'CM' : 'IN';
        $h['ap_ref_ap'] = $c->exists('ap_ref_ap', $b['ap_ref_ap'] ?? '', 'ap_mstr', 'ap_ref', $type === 'CM',
            "ap_type = 'IN' AND ap_status <> 'C' AND ap_vd_code = " . App::db()->pdo->quote((string) ($b['vendor'] ?? '')));
        if ($type === 'IN') {
            $h['ap_ref_ap'] = null;
        }
        $expenses = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $i => $l) {
            if (!is_array($l) || (trim((string) ($l['acct'] ?? '')) === '' && trim((string) ($l['amt'] ?? '')) === '')) {
                continue;
            }
            $f = fn (string $n) => "lines.$i.$n";
            $expenses[] = [
                'acct' => $c->exists($f('acct'), $l['acct'] ?? '', 'ac_mstr', 'ac_acct', true, 'ac_active = 1'),
                'cc'   => $c->exists($f('cc'), $l['cc'] ?? '', 'cc_mstr', 'cc_cc', false),
                'desc' => $c->text($f('desc'), $l['desc'] ?? '', false, 255, false),
                'amt'  => $c->number($f('amt'), $l['amt'] ?? '', true, 0, true, 2),
                'wht'  => $c->exists($f('wht'), $l['wht'] ?? '', 'wht_mstr', 'wht_code', false),
            ];
        }
        $receipts = array_values(array_unique(array_filter(is_array($b['receipts'] ?? null) ? $b['receipts'] : [], 'is_string')));
        // landed costs (<receipt>~<cost type>) and purchase returns (<return>~RTV) of this vendor: what they put on GRNI
        $costs = array_values(array_filter($receipts, fn ($k) => str_contains($k, '~')));
        $receipts = array_values(array_diff($receipts, $costs));
        if ($type === 'CM') {
            // a credit note takes expense lines and the vendor's credit for goods sent back, nothing else
            $receipts = [];
            $costs = array_values(array_filter($costs, fn ($k) => str_ends_with($k, '~RTV')));
        }
        $receiptWht = [];
        foreach (is_array($b['receipt_wht'] ?? null) ? $b['receipt_wht'] : [] as $rc => $code) {
            $receiptWht[(string) $rc] = $c->exists("receipt_wht.$rc", $code ?? '', 'wht_mstr', 'wht_code', false);
        }
        if (!$receipts && !$costs && !$expenses) {
            $c->fail('lines', t('accounting.ap_empty'));
        }
        $c->done();
        if ($vendor && $h['ap_inv_nbr'] && App::db()->value(
            "SELECT ap_ref FROM ap_mstr WHERE ap_vd_code = ? AND ap_inv_nbr = ? AND ap_type = ? AND ap_status <> 'C'", [$vendor, $h['ap_inv_nbr'], $type]
        )) {
            throw HttpException::invalid(['ap_inv_nbr' => t('accounting.ap_duplicate')]);
        }

        $ref = App::db()->transaction(function (Db $db) use ($vendor, $h, $receipts, $costs, $receiptWht, $expenses, $taxCode, $type) {
            $vd = $db->one('SELECT vd_terms, vd_ap_acct, vd_tax_code FROM vd_mstr WHERE vd_code = ?', [$vendor]);
            $ref = Numbering::next('AP', $h['ap_date']);
            $user = Auth::userId();
            $lines = []; // [acct, amount, desc, cc, po, withholding code]

            if ($receipts) {
                $marks = implode(',', array_fill(0, count($receipts), '?'));
                $rows = array_column($db->all(
                    "SELECT * FROM rc_mstr WHERE rc_nbr IN ($marks) FOR UPDATE", $receipts
                ), null, 'rc_nbr');
                foreach ($receipts as $rc) {
                    $r = $rows[$rc] ?? null;
                    if (!$r || $r['rc_vd_code'] !== $vendor || $r['rc_ap_posted']) {
                        throw HttpException::invalid(['receipts' => t('accounting.rc_not_open', ['nbr' => $rc])]);
                    }
                    // Clear exactly what the receipt put on GRNI; any satang difference is a price variance.
                    $grni = -(float) $db->value(
                        "SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_ref = ? AND gl_type = 'IC' AND gl_acct = ?",
                        [$rc, Gl::acct('grni')]
                    );
                    $desc = t('accounting.desc_ap_receipt', ['nbr' => $rc, 'po' => $r['rc_po_nbr']]);
                    $wht = $receiptWht[$rc] ?? null;
                    $lines[] = [Gl::acct('grni'), $grni, $desc, null, $r['rc_po_nbr'], $wht];
                    $lines[] = [Gl::acct('adj'), round((float) $r['rc_net_amt'] - $grni, 2), $desc, null, $r['rc_po_nbr'], $wht];
                    $lines[] = [Gl::acct('vat_in'), (float) $r['rc_tax_amt'], t('accounting.vat_in') . " $rc", null, $r['rc_po_nbr'], null];
                    $db->update('rc_mstr', ['rc_ap_posted' => 1, 'rc_ap_ref' => $ref, 'updated_by' => $user], ['rc_nbr' => $rc]);
                }
            }
            foreach ($costs as $key) {
                [$rc, $costType] = explode('~', $key, 2);
                if ($costType === 'RTV') {
                    // goods sent back (ใบส่งคืนสินค้า): the vendor's credit clears what the return put on GRNI — a credit
                    // note takes it as it is (the note's lines are turned over below); on an invoice it is a deduction
                    $rt = $db->one('SELECT * FROM rt_mstr WHERE rt_nbr = ? FOR UPDATE', [$rc]);
                    if (!$rt || $rt['rt_vd_code'] !== $vendor || $rt['rt_status'] !== 'O') {
                        throw HttpException::invalid(['receipts' => t('accounting.rc_not_open', ['nbr' => $rc])]);
                    }
                    $sign = $type === 'CM' ? 1 : -1;
                    $po = $rt['rt_po_nbr'] ?: $rt['rt_rc_nbr'];
                    $desc = t('purchase.rt_title') . " $rc";
                    $lines[] = [Gl::acct('grni'), $sign * (float) $rt['rt_net_amt'], $desc, null, $po, $receiptWht[$key] ?? null];
                    $lines[] = [Gl::acct('vat_in'), $sign * (float) $rt['rt_tax_amt'], t('accounting.vat_in') . " $rc", null, $po, null];
                    $db->update('rt_mstr', ['rt_ap_ref' => $ref, 'rt_status' => 'C', 'updated_by' => $user], ['rt_nbr' => $rc]);
                    continue;
                }
                $lc = $db->one(
                    'SELECT l.*, r.rc_po_nbr FROM lc_mstr l JOIN rc_mstr r ON r.rc_nbr = l.lc_rc_nbr
                      WHERE l.lc_rc_nbr = ? AND l.lc_line = ? FOR UPDATE',
                    [$rc, (int) $costType]
                );
                if (!$lc || $lc['lc_vd_code'] !== $vendor || !$lc['lc_allocated'] || $lc['lc_ap_ref']) {
                    throw HttpException::invalid(['receipts' => t('accounting.rc_not_open', ['nbr' => $key])]);
                }
                // Dr GRNI (what the allocation credited) + input VAT on the vendor's invoice
                $amt = round((float) $lc['lc_amt'], 2);
                $desc = trim(t('purchase.lc_' . $lc['lc_cost_type']) . ' ' . $lc['lc_ref']) . " $rc";
                $po = $lc['rc_po_nbr'] ?: $rc; // marks it as a line from a receipt: its amount is kept on edit
                $lines[] = [Gl::acct('grni'), $amt, $desc, null, $po, $receiptWht[$key] ?? null];
                $vat = round($amt * Money::taxPct($taxCode ?: $vd['vd_tax_code']) / 100, 2);
                $lines[] = [Gl::acct('vat_in'), $vat, t('accounting.vat_in') . " $rc", null, $po, null];
                $db->update('lc_mstr', ['lc_ap_ref' => $ref, 'updated_by' => $user], ['lc_rc_nbr' => $rc, 'lc_line' => (int) $costType]);
            }
            array_push($lines, ...self::expenseLines($expenses, $taxCode, $h['ap_inv_nbr']));

            $lines = array_values(array_filter($lines, fn ($l) => round($l[1], 2) != 0.0));
            $total = round(array_sum(array_column($lines, 1)), 2);
            if ($total <= 0) {
                throw HttpException::invalid(['lines' => t('accounting.ap_empty')]);
            }
            if ($type === 'CM') {
                // the same lines the other way round: Dr payable / Cr expense / Cr input VAT
                $lines = array_map(fn ($l) => [$l[0], -$l[1], $l[2], $l[3], $l[4], $l[5]], $lines);
                $total = -$total;
            }
            self::writeLines($db, $ref, $lines);
            $h['ap_due_date'] = $h['ap_due_date'] ?: Money::dueDate($h['ap_date'], $vd['vd_terms']);
            $h['ap_acct'] = $h['ap_acct'] ?: (($vd['vd_ap_acct'] ?? '') ?: Gl::acct('ap'));
            $db->insert('ap_mstr', $h + [
                'ap_ref' => $ref, 'ap_vd_code' => $vendor, 'ap_po_nbr' => $lines[0][4] ?? null, 'ap_effdate' => $h['ap_date'],
                'ap_amt' => $total, 'ap_paid_amt' => 0, 'ap_type' => $type, 'ap_status' => 'O', 'ap_cr_terms' => $vd['vd_terms'],
                'created_by' => $user, 'updated_by' => $user,
            ]);
            $post = array_map(fn ($l) => [$l[0], $l[1], $l[2], $l[3]], $lines);
            $post[] = [$h['ap_acct'], -$total];
            Gl::post($ref, 'AP', $h['ap_date'], $post, t('accounting.desc_ap', ['nbr' => $h['ap_inv_nbr'], 'vd' => $vendor]));
            return $ref;
        });
        return ['nbr' => $ref];
    }

    /**
     * Change a payable still owed and on no voucher (nothing paid): the head, the expense lines (added, changed, removed)
     * and every line's withholding code. Lines from goods receipts keep their amounts (they clear what was received).
     * Body: as create, without vendor / type / receipts, plus kept: {apd_line: wht code} for the receipt lines.
     * The ledger gets only the difference, as a correction on the payable's date (today when that period is closed).
     */
    #[Put('/api/payables/{ref}')]
    public function update(string $ref, Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $h = [
            'ap_date'     => $c->date('ap_date', $b['ap_date'] ?? '', true),
            'ap_inv_nbr'  => $c->text('ap_inv_nbr', $b['ap_inv_nbr'] ?? '', true, 50, false),
            'ap_due_date' => $c->date('ap_due_date', $b['ap_due_date'] ?? ''),
            'ap_remarks'  => $c->text('ap_remarks', $b['ap_remarks'] ?? '', false, 2000, false),
            'ap_acct'     => $c->exists('ap_acct', $b['ap_acct'] ?? '', 'ac_mstr', 'ac_acct', false, 'ac_active = 1'),
        ];
        $taxCode = $c->exists('tax_code', $b['tax_code'] ?? '', 'tx_mstr', 'tx_tax_code', false);
        $expenses = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $i => $l) {
            if (!is_array($l) || (trim((string) ($l['acct'] ?? '')) === '' && trim((string) ($l['amt'] ?? '')) === '')) {
                continue;
            }
            $f = fn (string $n) => "lines.$i.$n";
            $expenses[] = [
                'acct' => $c->exists($f('acct'), $l['acct'] ?? '', 'ac_mstr', 'ac_acct', true, 'ac_active = 1'),
                'cc'   => $c->exists($f('cc'), $l['cc'] ?? '', 'cc_mstr', 'cc_cc', false),
                'desc' => $c->text($f('desc'), $l['desc'] ?? '', false, 255, false),
                'amt'  => $c->number($f('amt'), $l['amt'] ?? '', true, 0, true, 2),
                'wht'  => $c->exists($f('wht'), $l['wht'] ?? '', 'wht_mstr', 'wht_code', false),
            ];
        }
        $kept = [];
        foreach (is_array($b['kept'] ?? null) ? $b['kept'] : [] as $line => $code) {
            $kept[(int) $line] = $c->exists("kept.$line", $code ?? '', 'wht_mstr', 'wht_code', false);
        }
        $c->done();

        App::db()->transaction(function (Db $db) use ($ref, $h, $taxCode, $expenses, $kept) {
            $old = $db->one('SELECT * FROM ap_mstr WHERE ap_ref = ? FOR UPDATE', [$ref]) ?? throw HttpException::notFound();
            if (!self::editable($old)) {
                throw new HttpException(409, t('accounting.ap_not_editable'));
            }
            if ($h['ap_date'] !== $old['ap_date'] && $h['ap_date'] < Gl::periodStart()) {
                throw HttpException::invalid(['ap_date' => t('accounting.period_closed', ['date' => Gl::periodStart()])]);
            }
            if ($db->value("SELECT ap_ref FROM ap_mstr WHERE ap_vd_code = ? AND ap_inv_nbr = ? AND ap_type = ? AND ap_status <> 'C' AND ap_ref <> ?",
                [$old['ap_vd_code'], $h['ap_inv_nbr'], $old['ap_type'], $ref])) {
                throw HttpException::invalid(['ap_inv_nbr' => t('accounting.ap_duplicate')]);
            }
            $sign = $old['ap_type'] === 'CM' ? -1 : 1;
            $lines = [];
            // the goods receipt lines stay as they were booked; only their withholding code may change
            foreach ($db->all('SELECT * FROM apd_det WHERE apd_ref = ? AND apd_po_nbr IS NOT NULL ORDER BY apd_line', [$ref]) as $l) {
                $wht = $l['apd_acct'] === Gl::acct('vat_in') ? null : (array_key_exists((int) $l['apd_line'], $kept) ? $kept[(int) $l['apd_line']] : $l['apd_wht_code']);
                $lines[] = [$l['apd_acct'], (float) $l['apd_amt'], $l['apd_desc'], $l['apd_cc'], $l['apd_po_nbr'], $wht];
            }
            foreach (self::expenseLines($expenses, $taxCode, $h['ap_inv_nbr']) as $l) {
                $l[1] *= $sign;
                $lines[] = $l;
            }
            $lines = array_values(array_filter($lines, fn ($l) => round($l[1], 2) != 0.0));
            $total = round(array_sum(array_column($lines, 1)), 2);
            if ($sign * $total <= 0) {
                throw HttpException::invalid(['lines' => t('accounting.ap_empty')]);
            }
            $db->delete('apd_det', ['apd_ref' => $ref]);
            self::writeLines($db, $ref, $lines);
            $h['ap_acct'] = $h['ap_acct'] ?: $old['ap_acct'];
            $h['ap_due_date'] = $h['ap_due_date'] ?: Money::dueDate($h['ap_date'], $old['ap_cr_terms']);
            $db->update('ap_mstr', $h + ['ap_amt' => $total, 'ap_effdate' => $h['ap_date'], 'updated_by' => Auth::userId()], ['ap_ref' => $ref]);

            // the ledger: what the payable posts now, less what it has posted so far, as one correction
            $want = [];
            foreach ([...array_map(fn ($l) => [$l[0], $l[1], $l[3]], $lines), [$h['ap_acct'], -$total, null]] as [$acct, $amt, $cc]) {
                $want["$acct|$cc"] = ($want["$acct|$cc"] ?? 0) + $amt;
            }
            foreach ($db->all("SELECT gl_acct, gl_cc, SUM(gl_amt) AS amt FROM gl_trhist WHERE gl_ref = ? AND gl_type = 'AP' GROUP BY gl_acct, gl_cc", [$ref]) as $g) {
                $k = $g['gl_acct'] . '|' . $g['gl_cc'];
                $want[$k] = ($want[$k] ?? 0) - (float) $g['amt'];
            }
            $post = [];
            foreach ($want as $k => $amt) {
                [$acct, $cc] = explode('|', $k, 2);
                if (round($amt, 2) != 0.0) {
                    $post[] = [$acct, round($amt, 2), t('accounting.desc_ap_edit', ['nbr' => $ref]), $cc !== '' ? $cc : null];
                }
            }
            if ($post) {
                Gl::post($ref, 'AP', max($h['ap_date'], Gl::periodStart()), $post, t('accounting.desc_ap_edit', ['nbr' => $ref]));
            }
            Audit::updated('ap_mstr', "ap_ref=$ref", $old, $h + ['ap_amt' => $total]);
        });
        return $this->show($ref);
    }

    /** Owed, nothing paid, on no voucher that is still open or paid. */
    private static function editable(array $h): bool
    {
        return $h['ap_status'] === 'O' && (float) $h['ap_paid_amt'] == 0.0 && !App::db()->value(
            "SELECT 1 FROM apvd_det x JOIN apv_mstr v ON v.apv_nbr = x.apvd_nbr WHERE x.apvd_ap_ref = ? AND v.apv_status <> 'C' LIMIT 1",
            [$h['ap_ref']]
        );
    }

    /** Expense lines and their input VAT: [acct, amount, desc, cc, po, withholding code]. */
    private static function expenseLines(array $expenses, ?string $taxCode, string $invNbr): array
    {
        if (!$expenses) {
            return [];
        }
        $out = [];
        foreach ($expenses as $e) {
            $out[] = [$e['acct'], $e['amt'], $e['desc'] ?: $invNbr, $e['cc'], null, $e['wht'] ?? null];
        }
        $tot = Money::totals(array_column($expenses, 'amt'), Money::taxPct($taxCode));
        $out[] = [Gl::acct('vat_in'), $tot['tax'], t('accounting.vat_in') . ($taxCode ? " $taxCode" : ''), null, null, null];
        return $out;
    }

    private static function writeLines(Db $db, string $ref, array $lines): void
    {
        $user = Auth::userId();
        foreach (array_values($lines) as $i => [$acct, $amt, $desc, $cc, $po, $wht]) {
            $db->insert('apd_det', [
                'apd_ref' => $ref, 'apd_line' => $i + 1, 'apd_acct' => $acct, 'apd_cc' => $cc, 'apd_amt' => round($amt, 2),
                'apd_desc' => mb_substr((string) $desc, 0, 255), 'apd_po_nbr' => $po, 'apd_wht_code' => $wht,
                'created_by' => $user, 'updated_by' => $user,
            ]);
        }
    }

    /** Only unpaid payables. The receipts can be invoiced again. */
    #[Post('/api/payables/{ref}/cancel'), Action('delete')]
    public function cancel(string $ref): null
    {
        App::db()->transaction(function (Db $db) use ($ref) {
            $h = $db->one('SELECT * FROM ap_mstr WHERE ap_ref = ? FOR UPDATE', [$ref]) ?? throw HttpException::notFound();
            if ($h['ap_status'] !== 'O' || (float) $h['ap_paid_amt'] != 0.0) {
                throw new HttpException(409, t('purchase.wrong_status'));
            }
            $db->update('ap_mstr', ['ap_status' => 'C', 'updated_by' => Auth::userId()], ['ap_ref' => $ref]);
            $db->run('UPDATE rc_mstr SET rc_ap_posted = 0, rc_ap_ref = NULL, updated_by = ? WHERE rc_ap_ref = ?', [Auth::userId(), $ref]);
            $db->run('UPDATE lc_mstr SET lc_ap_ref = NULL, updated_by = ? WHERE lc_ap_ref = ?', [Auth::userId(), $ref]);
            $db->run("UPDATE rt_mstr SET rt_ap_ref = NULL, rt_status = 'O', updated_by = ? WHERE rt_ap_ref = ?", [Auth::userId(), $ref]);
            Gl::reverse($ref, 'AP', max(date('Y-m-d'), Gl::periodStart()), t('accounting.desc_cancel', ['nbr' => $ref]));
            Audit::updated('ap_mstr', "ap_ref=$ref", $h, ['ap_status' => 'C']);
        });
        return null;
    }

    /**
     * Receipts not on a payable yet, then allocated landed costs whose vendor (freight, customs, insurer) has not billed yet:
     * of one vendor, or of all vendors when $vendor is empty. A landed cost's key (rc_nbr) is <receipt>~<cost type>,
     * src is its receipt, and its VAT is estimated at the vendor's tax code.
     */
    private static function uninvoiced(string $vendor): array
    {
        $rows = App::db()->all(
            'SELECT r.rc_nbr, r.rc_nbr AS src, NULL AS kind, r.rc_date, r.rc_vd_code, v.vd_name, r.rc_po_nbr, r.rc_inv_nbr, r.rc_net_amt, r.rc_tax_amt, r.rc_tot_amt
               FROM rc_mstr r LEFT JOIN vd_mstr v ON v.vd_code = r.rc_vd_code
              WHERE r.rc_ap_posted = 0 AND (? = \'\' OR r.rc_vd_code = ?) ORDER BY r.rc_vd_code, r.rc_date, r.rc_nbr',
            [$vendor, $vendor]
        );
        foreach (App::db()->all(
            "SELECT l.lc_rc_nbr, l.lc_line, l.lc_cost_type, l.lc_ref, l.lc_vd_code, l.lc_amt, r.rc_date, r.rc_po_nbr, v.vd_name, v.vd_tax_code
               FROM lc_mstr l JOIN rc_mstr r ON r.rc_nbr = l.lc_rc_nbr LEFT JOIN vd_mstr v ON v.vd_code = l.lc_vd_code
              WHERE l.lc_allocated = 1 AND l.lc_ap_ref IS NULL AND l.lc_vd_code IS NOT NULL AND (? = '' OR l.lc_vd_code = ?)
              ORDER BY l.lc_vd_code, r.rc_date, l.lc_rc_nbr, l.lc_line",
            [$vendor, $vendor]
        ) as $l) {
            $net = round((float) $l['lc_amt'], 2);
            $vat = round($net * Money::taxPct($l['vd_tax_code']) / 100, 2);
            $rows[] = [
                'rc_nbr' => $l['lc_rc_nbr'] . '~' . $l['lc_line'], 'src' => $l['lc_rc_nbr'],
                'kind' => t('purchase.lc_' . $l['lc_cost_type']), 'rc_date' => $l['rc_date'], 'rc_vd_code' => $l['lc_vd_code'],
                'vd_name' => $l['vd_name'], 'rc_po_nbr' => $l['rc_po_nbr'], 'rc_inv_nbr' => $l['lc_ref'],
                'rc_net_amt' => $net, 'rc_tax_amt' => $vat, 'rc_tot_amt' => $net + $vat,
            ];
        }
        // goods sent back, waiting for the vendor's credit note: negative, and the only rows a credit note takes
        foreach (App::db()->all(
            "SELECT r.*, v.vd_name FROM rt_mstr r LEFT JOIN vd_mstr v ON v.vd_code = r.rt_vd_code
              WHERE r.rt_status = 'O' AND (? = '' OR r.rt_vd_code = ?) ORDER BY r.rt_vd_code, r.rt_date, r.rt_nbr",
            [$vendor, $vendor]
        ) as $r) {
            $rows[] = [
                'rc_nbr' => $r['rt_nbr'] . '~RTV', 'src' => $r['rt_rc_nbr'], 'kind' => t('purchase.rt_kind') . ' ' . $r['rt_nbr'], 'ret' => true,
                'rc_date' => $r['rt_date'], 'rc_vd_code' => $r['rt_vd_code'], 'vd_name' => $r['vd_name'], 'rc_po_nbr' => $r['rt_po_nbr'],
                'rc_inv_nbr' => null, 'rc_net_amt' => -(float) $r['rt_net_amt'], 'rc_tax_amt' => -(float) $r['rt_tax_amt'],
                'rc_tot_amt' => -(float) $r['rt_tot_amt'],
            ];
        }
        return $rows;
    }

    public static function head(string $ref): array
    {
        return App::db()->one(
            'SELECT a.*, v.vd_name FROM ap_mstr a LEFT JOIN vd_mstr v ON v.vd_code = a.ap_vd_code WHERE a.ap_ref = ?',
            [$ref]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $ref): array
    {
        return App::db()->all(
            'SELECT d.*, ' . Gl::acctName('ac') . ' AS ac_desc_th, w.wht_desc, w.wht_pct FROM apd_det d LEFT JOIN ac_mstr ac ON ac.ac_acct = d.apd_acct
               LEFT JOIN wht_mstr w ON w.wht_code = d.apd_wht_code WHERE d.apd_ref = ? ORDER BY d.apd_line',
            [$ref]
        );
    }

    private function page(?string $ref): string
    {
        return View::render('accounting/payable', [
            'docNbr' => $ref === null ? Numbering::peek('AP') : null,
            'title'   => $ref ?? t('accounting.ap_new'),
            'help'    => 'payables',
            'ownToolbar' => true,
            'config'  => [
                'nbr'       => $ref,
                'printUrl'  => $ref !== null && Auth::can('payables', 'print') ? '/documents/payables/' . rawurlencode($ref) . '/print' : null,
                'status'    => self::statusText(),
                'accounts'  => Lookup::options('accounts'),
                'costCenters' => Lookup::options('cost-centers'),
                'taxes'     => Money::taxOptions(),
                'vatAcct'   => Gl::acct('vat_in'),
                'apAcct'    => Gl::acct('ap'),
                'canCancel' => Auth::can('payables', 'delete'),
                'whtCodes'  => Lookup::options('wht-codes'), // a line's withholding code
                'whtPct'    => array_map('floatval', array_column(App::db()->all('SELECT wht_code, wht_pct FROM wht_mstr'), 'wht_pct', 'wht_code')),
                // credit term → days, so the due date follows the vendor's term and the invoice date
                'termDays'  => array_map('intval', array_column(App::db()->all('SELECT ct_code, ct_net_days FROM ct_mstr'), 'ct_net_days', 'ct_code')),
                'grniAcct'  => Gl::acct('grni'),
                'today'     => date('Y-m-d'),
            ],
            'scripts' => ['js/pages/payable.js'],
        ]);
    }

    public static function statusText(): array
    {
        return [
            'O' => ['text' => t('accounting.ap_status_O'), 'color' => 'yellow'],
            'P' => ['text' => t('sales.inv_status_P'), 'color' => 'green'],
            'C' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }
}
