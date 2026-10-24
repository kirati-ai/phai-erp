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
use Phai\Modules\Sales\SalesPrint;

/**
 * Vouchers (ใบสำคัญจ่าย, VO): open payables of one vendor gathered for payment, printed and signed.
 * Saving moves them off the payable accounts: Dr each payable's account / Cr vouchers payable (gl_vo_acct),
 * and the payables count as settled. A payment (PV) then pays vouchers. Status O to pay, P paid, C cancelled.
 */
#[Program('ap-vouchers')]
final class ApVoucherController
{
    #[Get('/ap-vouchers')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('accounting.apv_title'),
            'help'    => 'ap-vouchers',
            'config'  => [
                'api'     => '/api/ap-vouchers',
                'page'    => '/ap-vouchers',
                'canAdd'  => Auth::can('ap-vouchers', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'apv_nbr', 'label' => t('accounting.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'apv_date', 'label' => t('accounting.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'apv_vd_code', 'label' => t('purchase.vendor'), 'sort' => true],
                    ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                    ['key' => 'apv_pay_date', 'label' => t('accounting.apv_pay_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'apv_amt', 'label' => t('accounting.amount'), 'type' => 'money', 'sort' => true],
                    ['key' => 'apv_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Open payables not in a voucher yet above the list: tick rows of one vendor to make one voucher. */
    public static function queues(): array
    {
        return Auth::can('ap-vouchers', 'add') ? [[
            'title'   => t('accounting.q_apv'),
            'api'     => '/api/ap-vouchers/waiting',
            'key'     => 'ap_ref',
            'urgent'  => 'overdue',
            'columns' => [
                ['key' => 'ap_ref', 'label' => t('accounting.nbr'), 'link' => '/payables/{ap_ref}'],
                ['key' => 'type_text', 'label' => t('accounting.ap_type')],
                ['key' => 'ap_vd_code', 'label' => t('purchase.vendor')],
                ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                ['key' => 'ap_inv_nbr', 'label' => t('accounting.vendor_inv')],
                ['key' => 'ap_due_date', 'label' => t('sales.due_date'), 'type' => 'date'],
                ['key' => 'balance', 'label' => t('accounting.balance'), 'type' => 'money'],
            ],
            'multi'   => ['label' => t('accounting.q_apv_make'), 'icon' => 'ti ti-file-stack', 'same' => 'ap_vd_code',
                          'sameText' => t('accounting.q_one_vendor'), 'href' => '/ap-vouchers/new?vendor={ap_vd_code}&refs={keys}'],
        ]] : [];
    }

    #[Get('/ap-vouchers/new'), Action('add')]
    public function newPage(): string
    {
        return $this->page(null);
    }

    #[Get('/ap-vouchers/{nbr}')]
    public function viewPage(string $nbr): string
    {
        self::head($nbr);
        return $this->page($nbr);
    }

    /** ?q= &status= */
    #[Describe('Vouchers: open payables of a vendor gathered for payment (baht)', ['q' => 'number or vendor', 'status' => 'O to pay, P paid, C cancelled'])]
    #[Get('/api/ap-vouchers')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['O', 'P', 'C'], true)) {
            $where[] = 'h.apv_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(h.apv_nbr LIKE ? OR h.apv_vd_code LIKE ? OR v.vd_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT h.apv_nbr, h.apv_date, h.apv_vd_code, v.vd_name, h.apv_pay_date, h.apv_amt, h.apv_status',
            'FROM apv_mstr h LEFT JOIN vd_mstr v ON v.vd_code = h.apv_vd_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'apv_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['apv_nbr' => 'h.apv_nbr', 'apv_date' => 'h.apv_date', 'apv_vd_code' => 'h.apv_vd_code', 'apv_pay_date' => 'h.apv_pay_date',
             'apv_amt' => 'h.apv_amt', 'apv_status' => 'h.apv_status'],
            'apv_nbr'
        );
    }

    /** ?vendor= — the vendor's open payables and credit notes that are on no voucher yet. */
    #[Get('/api/ap-vouchers/open')]
    public function open(Request $request): array
    {
        return self::openPayables((string) $request->input('vendor', ''));
    }

    #[Describe('Open payables and vendor credit notes (negative) not on a voucher yet, of all vendors, earliest due first (baht; overdue = past due date)')]
    #[Get('/api/ap-vouchers/waiting')]
    public function waiting(): array
    {
        $rows = App::db()->all(
            "SELECT a.ap_ref, a.ap_type, a.ap_vd_code, v.vd_name, a.ap_inv_nbr, a.ap_date, a.ap_due_date,
                    ROUND(a.ap_amt - a.ap_paid_amt, 2) AS balance, (a.ap_due_date < CURDATE()) AS overdue
               FROM ap_mstr a LEFT JOIN vd_mstr v ON v.vd_code = a.ap_vd_code
              WHERE a.ap_status = 'O' AND a.ap_amt <> a.ap_paid_amt
              ORDER BY a.ap_due_date IS NULL, a.ap_due_date, a.ap_ref LIMIT 200"
        );
        foreach ($rows as &$r) {
            $r['type_text'] = t('accounting.ap_type_' . ($r['ap_type'] === 'CM' ? 'CM' : 'IN'));
            $r['overdue'] = (bool) $r['overdue'];
        }
        return $rows;
    }

    #[Get('/api/ap-vouchers/{nbr}')]
    public function show(string $nbr): array
    {
        $head = self::head($nbr);
        $head['wht'] = WhtPlan::ofVoucher($nbr); // what paying it will withhold (shown, posted by the payment)
        return ['head' => $head, 'lines' => self::lines($nbr)];
    }

    /** Body: {vendor, apv_date, apv_pay_date, apv_remarks, refs: [ap_ref]} */
    #[Post('/api/ap-vouchers')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $vendor = $c->exists('vendor', $b['vendor'] ?? '', 'vd_mstr', 'vd_code');
        $h = [
            'apv_date'     => $c->date('apv_date', $b['apv_date'] ?? '', true),
            'apv_pay_date' => $c->date('apv_pay_date', $b['apv_pay_date'] ?? ''),
            'apv_remarks'  => $c->text('apv_remarks', $b['apv_remarks'] ?? '', false, 2000, false),
        ];
        if ($h['apv_date'] && $h['apv_date'] < Gl::periodStart()) {
            $c->fail('apv_date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $refs = array_values(array_unique(array_filter(is_array($b['refs'] ?? null) ? $b['refs'] : [], 'is_string')));
        if (!$refs) {
            $c->fail('lines', t('accounting.nothing_applied'));
        }
        $c->done();

        $nbr = App::db()->transaction(function (Db $db) use ($vendor, $h, $refs) {
            // Lock the payables and check them again: someone may have put one on another voucher.
            $marks = implode(',', array_fill(0, count($refs), '?'));
            $rows = array_column($db->all("SELECT * FROM ap_mstr WHERE ap_ref IN ($marks) FOR UPDATE", $refs), null, 'ap_ref');
            $lines = [];
            foreach ($refs as $ref) {
                $r = $rows[$ref] ?? null;
                $left = $r ? round((float) $r['ap_amt'] - (float) $r['ap_paid_amt'], 2) : 0.0;
                if (!$r || $r['ap_vd_code'] !== $vendor || $r['ap_status'] !== 'O' || $left == 0.0) {
                    throw HttpException::invalid(['lines' => t('accounting.apv_not_open', ['nbr' => $ref])]);
                }
                $lines[] = [$ref, $r['ap_acct'] ?: Gl::acct('ap'), $left];
            }
            $total = round(array_sum(array_column($lines, 2)), 2);
            if ($total <= 0) {
                throw HttpException::invalid(['lines' => t('accounting.credit_over_payables')]);
            }
            $nbr = Numbering::next('VO', $h['apv_date']);
            $user = Auth::userId();
            $db->insert('apv_mstr', $h + [
                'apv_nbr' => $nbr, 'apv_vd_code' => $vendor, 'apv_amt' => $total, 'apv_paid_amt' => 0, 'apv_status' => 'O',
                'created_by' => $user, 'updated_by' => $user,
            ]);
            $post = [];
            foreach ($lines as $i => [$ref, $acct, $amt]) {
                $db->insert('apvd_det', [
                    'apvd_nbr' => $nbr, 'apvd_line' => $i + 1, 'apvd_ap_ref' => $ref, 'apvd_acct' => $acct, 'apvd_amt' => $amt,
                    'created_by' => $user, 'updated_by' => $user,
                ]);
                // the payable is now owed through the voucher
                $db->run("UPDATE ap_mstr SET ap_paid_amt = ap_amt, ap_status = 'P', updated_by = ? WHERE ap_ref = ?", [$user, $ref]);
                $post[] = [$acct, $amt, t('accounting.desc_voucher_line', ['nbr' => $ref])];
            }
            $post[] = [Gl::acct('vo'), -$total];
            Gl::post($nbr, 'VO', $h['apv_date'], $post, t('accounting.desc_voucher', ['nbr' => $nbr, 'vd' => $vendor]));
            return $nbr;
        });
        return ['nbr' => $nbr];
    }

    /** Only vouchers not paid at all: the payables open again and the posting is reversed. */
    #[Post('/api/ap-vouchers/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        App::db()->transaction(function (Db $db) use ($nbr) {
            $h = $db->one('SELECT * FROM apv_mstr WHERE apv_nbr = ? FOR UPDATE', [$nbr]) ?? throw HttpException::notFound();
            if ($h['apv_status'] !== 'O' || (float) $h['apv_paid_amt'] != 0.0) {
                throw new HttpException(409, t('purchase.wrong_status'));
            }
            foreach ($db->all('SELECT apvd_ap_ref, apvd_amt FROM apvd_det WHERE apvd_nbr = ?', [$nbr]) as $l) {
                $db->run(
                    "UPDATE ap_mstr SET ap_paid_amt = ROUND(ap_paid_amt - ?, 2), ap_status = 'O', updated_by = ? WHERE ap_ref = ?",
                    [(float) $l['apvd_amt'], Auth::userId(), $l['apvd_ap_ref']]
                );
            }
            $db->update('apv_mstr', ['apv_status' => 'C', 'updated_by' => Auth::userId()], ['apv_nbr' => $nbr]);
            Gl::reverse($nbr, 'VO', max(date('Y-m-d'), Gl::periodStart()), t('accounting.desc_cancel', ['nbr' => $nbr]));
            Audit::updated('apv_mstr', "apv_nbr=$nbr", $h, ['apv_status' => 'C']);
        });
        return null;
    }

    /** Voucher on A4 for signing, on the same layout as every other document. */
    #[Get('/ap-vouchers/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $wht = WhtPlan::ofVoucher($nbr);
        $m = fn ($v) => number_format((float) $v, 2);
        $type = ['IN' => t('accounting.ap_type_IN'), 'CM' => t('accounting.ap_type_CM')];
        $total = (float) $h['apv_amt'];
        $net = $total - array_sum(array_column($wht, 'tax'));
        $rows = [[t('sales.total'), $m($total), $wht ? 'bold' : true]];
        foreach ($wht as $w) {
            $rows[] = [t('accounting.wht_est', ['pct' => WhtController::pct($w['pct'])]), '-' . $m($w['tax']), false];
        }
        if ($wht) {
            $rows[] = [t('accounting.net_to_pay'), $m($net), true]; // the bottom line is what is paid
        }
        $meta = [[t('accounting.date'), SalesPrint::date($h['apv_date'])], [t('accounting.apv_pay_date'), SalesPrint::date($h['apv_pay_date'])]];
        if ($h['apv_status'] === 'C') {
            $meta[] = [t('common.status'), t('purchase.status_X')];
        }
        return SalesPrint::render([
            'title' => t('accounting.apv_print_title'),
            'nbr'   => $nbr,
            'meta'  => $meta,
            'party' => SalesPrint::vendor($h['apv_vd_code']),
            'lines' => [
                'columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('accounting.nbr')], ['label' => t('accounting.ap_type')],
                    ['label' => t('accounting.vendor_inv')], ['label' => t('accounting.date'), 'width' => '80px'],
                    ['label' => t('accounting.ap_acct')], ['label' => t('accounting.amount'), 'align' => 'r', 'width' => '110px'],
                ],
                'rows' => self::rowsWithItems(self::lines($nbr), fn ($i, $l) => [$i + 1, $l['apvd_ap_ref'], $type[$l['ap_type']] ?? $type['IN'],
                    $l['ap_inv_nbr'] ?? '', SalesPrint::date($l['ap_date']), $l['apvd_acct'] . ' ' . ($l['ac_desc_th'] ?? ''), $m($l['apvd_amt'])]),
            ],
            'totals' => ['rows' => $rows, 'words' => BahtText::of($net)],
            'notes'  => $h['apv_remarks'] ?? '',
            'signs'  => [t('accounting.apv_sign_prepared'), t('accounting.apv_sign_checked'), t('accounting.apv_sign_approved'), t('accounting.apv_sign_received')],
        ]);
    }

    /** Each payable of a voucher as a printed row, with what it was for under it (ApItems). */
    public static function rowsWithItems(array $lines, callable $row): array
    {
        $items = ApItems::of(array_column($lines, 'apvd_ap_ref'));
        $rows = [];
        foreach ($lines as $i => $l) {
            $rows[] = $row($i, $l);
            array_push($rows, ...array_map([SalesPrint::class, 'sub'], $items[$l['apvd_ap_ref']] ?? []));
        }
        return $rows;
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT h.*, v.vd_name, v.vd_tax_id_num FROM apv_mstr h LEFT JOIN vd_mstr v ON v.vd_code = h.apv_vd_code WHERE h.apv_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, a.ap_type, a.ap_inv_nbr, a.ap_date, a.ap_due_date, a.ap_amt, ' . Gl::acctName('ac') . ' AS ac_desc_th
               FROM apvd_det d LEFT JOIN ap_mstr a ON a.ap_ref = d.apvd_ap_ref LEFT JOIN ac_mstr ac ON ac.ac_acct = d.apvd_acct
              WHERE d.apvd_nbr = ? ORDER BY d.apvd_line',
            [$nbr]
        );
    }

    /** Open payables (and credit notes, negative) of one vendor, oldest due first, with their payable account. */
    public static function openPayables(string $vendor): array
    {
        $rows = App::db()->all(
            "SELECT a.ap_ref, a.ap_type, a.ap_inv_nbr, a.ap_date, a.ap_due_date, a.ap_amt,
                    ROUND(a.ap_amt - a.ap_paid_amt, 2) AS balance, COALESCE(a.ap_acct, ?) AS ap_acct, " . Gl::acctName('ac') . " AS ac_desc_th
               FROM ap_mstr a LEFT JOIN ac_mstr ac ON ac.ac_acct = COALESCE(a.ap_acct, ?)
              WHERE a.ap_vd_code = ? AND a.ap_status = 'O' AND a.ap_amt <> a.ap_paid_amt
              ORDER BY a.ap_due_date, a.ap_ref",
            [Gl::acct('ap'), Gl::acct('ap'), $vendor]
        );
        foreach ($rows as &$r) {
            $r['wht'] = WhtPlan::of([$r['ap_ref'] => (float) $r['balance']]);
        }
        unset($r);
        return $rows;
    }

    public static function statusText(): array
    {
        return [
            'O' => ['text' => t('accounting.apv_status_O'), 'color' => 'yellow'],
            'P' => ['text' => t('sales.inv_status_P'), 'color' => 'green'],
            'C' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }

    private function page(?string $nbr): string
    {
        return View::render('accounting/ap-voucher', [
            'docNbr'  => $nbr === null ? Numbering::peek('VO') : null,
            'title'   => $nbr ?? t('accounting.apv_new'),
            'help'    => 'ap-vouchers',
            'config'  => [
                'nbr'       => $nbr,
                'status'    => self::statusText(),
                'canPrint'  => Auth::can('ap-vouchers', 'print'),
                'canCancel' => Auth::can('ap-vouchers', 'delete'),
                'canPay'    => Auth::can('vouchers', 'add'),
                'today'     => date('Y-m-d'),
            ],
            'scripts' => ['js/pages/ap-voucher.js'],
        ]);
    }
}
