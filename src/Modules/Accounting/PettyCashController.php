<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;
use Phai\Modules\Master\Money;
use Phai\Modules\Sales\SalesPrint;
use Phai\Modules\System\Attachment;

/**
 * เงินสดย่อย (/petty-cash): my box on top (cash in hand, the month it works in, what I can do), its entries by month with
 * a running balance, every box for those who may look. The rules are in PettyCash; this is the page, the API, the
 * printed ใบสำคัญจ่ายเงินสดย่อย and ทะเบียนเงินสดย่อย, and the files attached to an entry.
 */
#[Program('petty-cash')]
final class PettyCashController
{
    #[Get('/petty-cash')]
    public function page(Request $request): string
    {
        $db = App::db();
        return View::render('accounting/petty-cash', [
            'help'    => 'petty-cash',
            'title'   => t('pettycash.title'),
            'queues'  => [[
                'title'   => t('pettycash.q_count'),
                'api'     => '/api/petty-cash/waiting',
                'key'     => 'holder',
                'columns' => [
                    ['key' => 'name', 'label' => t('pettycash.holder')],
                    ['key' => 'month_text', 'label' => t('pettycash.month')],
                    ['key' => 'balance', 'label' => t('pettycash.balance'), 'type' => 'money'],
                ],
            ]],
            'config'  => [
                'me'       => Auth::userId(),
                'holder'   => (string) $request->input('holder', Auth::userId()),
                'month'    => (string) $request->input('month', ''),
                'today'    => date('Y-m-d'),
                'canAdd'   => Auth::can('petty-cash', 'add'),
                'canPrint' => Auth::can('petty-cash', 'print'),
                'banks'    => $db->all("SELECT bk_bank AS value, bk_desc AS label FROM bk_mstr WHERE bk_active = 1 AND bk_petty = 0 ORDER BY bk_bank"),
                'accounts' => $db->all("SELECT ac_acct AS value, " . Gl::acctName() . " AS label FROM ac_mstr
                                         WHERE ac_active = 1 AND ac_is_control = 0 AND ac_type = 'X' ORDER BY ac_acct"),
                'incomes'  => $db->all("SELECT ac_acct AS value, " . Gl::acctName() . " AS label FROM ac_mstr
                                         WHERE ac_active = 1 AND ac_is_control = 0 AND ac_type = 'R' ORDER BY ac_acct"),
                'income'   => '40901', // รายได้อื่น, the usual one
                'ccs'      => $db->all("SELECT cc_cc AS value, cc_desc AS label FROM cc_mstr WHERE cc_active = 1 ORDER BY cc_cc"),
                'whts'     => $db->all("SELECT wht_code AS value, wht_desc AS label, wht_pct AS pct
                                         FROM wht_mstr WHERE wht_active = 1 ORDER BY wht_code"),
                'taxes'    => Money::taxOptions(),
                'notes'    => PettyCash::DENOMINATIONS,
                'docNbr'   => Numbering::peek('PC'),
                'text'     => self::text(),
            ],
            'scripts' => ['js/pages/petty-cash.js'],
        ]);
    }

    #[Describe('Petty cash boxes: each holder (user) with cash in hand, the month the box works in and the last month counted')]
    #[Get('/api/petty-cash/boxes')]
    public function boxes(): array
    {
        $holders = array_column(App::db()->all('SELECT DISTINCT cbh_holder AS h FROM cbh_hist UNION SELECT cbc_holder FROM cbc_mstr'), 'h');
        if (!in_array(Auth::userId(), $holders, true)) {
            $holders[] = Auth::userId();
        }
        $out = [];
        foreach ($holders as $h) {
            $open = PettyCash::openMonth($h);
            $last = PettyCash::lastCounted($h);
            $out[] = ['holder' => $h, 'name' => PettyCash::name($h), 'balance' => PettyCash::balance($h),
                      'open' => $open, 'open_text' => $open ? PettyCash::monthText($open) : '', 'last' => $last, 'last_text' => $last ? PettyCash::monthText($last) : ''];
        }
        usort($out, fn ($a, $b) => [$a['holder'] !== Auth::userId(), $a['name']] <=> [$b['holder'] !== Auth::userId(), $b['name']]);
        return $out;
    }

    #[Describe('One petty cash box for a month: opening balance, entries (in/out, running balance; cancelled shown with zero) and the count', ['holder' => 'user id (default: me)', 'month' => 'yyyy-mm (default: the month the box works in)'])]
    #[Get('/api/petty-cash/box')]
    public function box(Request $request): array
    {
        return self::boxData((string) $request->input('holder', Auth::userId()), (string) $request->input('month', ''));
    }

    /** A box for a month (yyyy-mm, or the month it works in when empty or wrong). */
    public static function boxData(string $holder, string $month): array
    {
        $open = PettyCash::openMonth($holder);
        $last = PettyCash::lastCounted($holder);
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = $open ?? date('Y-m');
        }
        $start = "$month-01";
        $end = PettyCash::monthEnd($month);
        $opening = PettyCash::balance($holder, date('Y-m-d', strtotime("$start -1 day")));
        $rows = App::db()->all(
            "SELECT h.cb_nbr, h.cb_type, h.cb_date, h.cb_holder, h.cb_from, h.cb_payee, h.cb_bank, h.cb_remarks, h.cb_status,
                    COALESCE(r.cbh_amt, 0) AS amt, (SELECT COUNT(*) FROM att_det a WHERE a.att_doc = 'PC' AND a.att_nbr = h.cb_nbr) AS files
               FROM cb_mstr h LEFT JOIN cbh_hist r ON r.cbh_nbr = h.cb_nbr AND r.cbh_holder = ?
              WHERE h.cb_holder = ? AND h.cb_date BETWEEN ? AND ?
              ORDER BY h.cb_date, h.cb_nbr",
            [$holder, $holder, $start, $end]
        );
        $bal = $opening;
        foreach ($rows as &$r) {
            $r['amt'] = (float) $r['amt'];
            $bal = round($bal + $r['amt'], 2);
            $r['bal'] = $bal;
            $r['text'] = self::rowText($r, $holder);
        }
        unset($r);
        $count = App::db()->one('SELECT * FROM cbc_mstr WHERE cbc_holder = ? AND cbc_month = ?', [$holder, $month]);
        $mine = $holder === Auth::userId();
        return [
            'holder' => $holder, 'name' => PettyCash::name($holder), 'mine' => $mine,
            'balance' => PettyCash::balance($holder), 'open' => $open, 'open_text' => $open ? PettyCash::monthText($open) : '',
            'last' => $last, 'last_text' => $last ? PettyCash::monthText($last) : '',
            'month' => $month, 'month_text' => PettyCash::monthText($month), 'opening' => $opening, 'opening_from' => self::openingFrom($holder, date('Y-m', strtotime("$start -1 day")), $opening), 'closing' => $bal, 'rows' => $rows,
            'count' => $count ? $count + ['notes' => json_decode((string) $count['cbc_notes'], true) ?: [], 'counter' => PettyCash::name((string) $count['created_by'])] : null,
            // what the holder may do now, for the buttons
            'can_count'   => $mine && $open !== null && date('Y-m-d') >= PettyCash::monthEnd($open),
            'count_month' => $open, 'count_month_text' => $open ? PettyCash::monthText($open) : '',
            'count_book'  => $open ? PettyCash::balance($holder, PettyCash::monthEnd($open)) : 0.0,
            'can_uncount' => $mine && PettyCash::canUncount($holder), 'uncount_month' => $last, 'uncount_text' => $last ? PettyCash::monthText($last) : '',
        ];
    }

    /**
     * Every holder's petty cash for a month in one list (or one holder's): brought forward per holder and in all, each
     * register row with that holder's running balance (money passed on is two rows: the sender's out, the receiver's in;
     * a cancelled entry shows at nothing), what came in and went out and is left per holder and in all, and who has not
     * counted a month that is over.
     */
    #[Describe('Petty cash of every holder for a month: brought forward, entries with running balance per holder, totals, holders not yet counted', ['month' => 'yyyy-mm (default: this month)', 'holder' => 'one user id (default: everyone)'])]
    #[Get('/api/petty-cash/month')]
    public function month(Request $request): array
    {
        $month = (string) $request->input('month', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $only = (string) $request->input('holder', '');
        $start = "$month-01";
        $end = PettyCash::monthEnd($month);
        $db = App::db();
        $rows = $db->all(
            "SELECT r.cbh_holder AS holder, r.cbh_amt AS amt, h.*, (SELECT COUNT(*) FROM att_det a WHERE a.att_doc = 'PC' AND a.att_nbr = h.cb_nbr) AS files
               FROM cbh_hist r JOIN cb_mstr h ON h.cb_nbr = r.cbh_nbr
              WHERE r.cbh_date BETWEEN ? AND ?
             UNION ALL
             SELECT h.cb_holder, 0, h.*, (SELECT COUNT(*) FROM att_det a WHERE a.att_doc = 'PC' AND a.att_nbr = h.cb_nbr)
               FROM cb_mstr h WHERE h.cb_status = 'X' AND h.cb_date BETWEEN ? AND ?
              ORDER BY cb_date, cb_nbr, amt",
            [$start, $end, $start, $end]
        );
        // everyone with cash brought forward or an entry this month
        $who = array_column($db->all('SELECT DISTINCT cbh_holder AS h FROM cbh_hist WHERE cbh_date <= ?', [$end]), 'h');
        $who = array_values(array_unique(array_merge($who, array_column($rows, 'holder'))));
        if ($only !== '') {
            $who = array_values(array_intersect($who, [$only])) ?: [$only];
            $rows = array_values(array_filter($rows, fn ($r) => $r['holder'] === $only));
        }
        $holders = [];
        $prev = date('Y-m', strtotime("$start -1 day"));
        foreach ($who as $h) {
            $opening = PettyCash::balance($h, date('Y-m-d', strtotime("$start -1 day")));
            $count = $db->one('SELECT cbc_counted, cbc_diff FROM cbc_mstr WHERE cbc_holder = ? AND cbc_month = ?', [$h, $month]);
            $holders[$h] = ['holder' => $h, 'name' => PettyCash::name($h), 'opening' => $opening, 'in' => 0.0, 'out' => 0.0, 'closing' => $opening,
                            'counted' => $count ? (float) $count['cbc_counted'] : null, 'diff' => $count ? (float) $count['cbc_diff'] : null,
                            'from' => self::openingFrom($h, $prev, $opening),
                            'from_counted' => (bool) $db->value('SELECT 1 FROM cbc_mstr WHERE cbc_holder = ? AND cbc_month = ?', [$h, $prev])];
        }
        // a holder with nothing at all this month (no cash, no entries) is left out unless asked for
        $holders = array_filter($holders, fn ($h) => $h['opening'] != 0.0 || $h['holder'] === $only
            || in_array($h['holder'], array_column($rows, 'holder'), true));
        uasort($holders, fn ($a, $b) => strcmp($a['name'], $b['name']));
        foreach ($rows as &$r) {
            $h = &$holders[$r['holder']];
            $r['amt'] = (float) $r['amt'];
            $h['closing'] = round($h['closing'] + $r['amt'], 2);
            $r['amt'] > 0 ? $h['in'] += $r['amt'] : $h['out'] -= $r['amt'];
            $r['bal'] = $h['closing'];
            $r['holder_name'] = $h['name'];
            $r['text'] = self::rowText($r, $r['holder']);
            $r['bill'] = $r['cb_type'] === 'EX' ? round((float) $r['cb_net_amt'] + (float) $r['cb_vat_amt'], 2) : null;
            unset($h);
        }
        unset($r);
        $sum = fn (string $k) => round(array_sum(array_column($holders, $k)), 2);
        $uncounted = $end < date('Y-m-d') ? array_values(array_filter(PettyCash::uncounted($month), fn ($u) => $only === '' || $u['holder'] === $only)) : [];
        return [
            'month' => $month, 'month_text' => PettyCash::monthText($month), 'holders' => array_values($holders), 'rows' => $rows,
            'total' => ['opening' => $sum('opening'), 'in' => $sum('in'), 'out' => $sum('out'), 'closing' => $sum('closing')],
            'uncounted' => $uncounted,
        ];
    }

    /** Where a holder's brought-forward cash comes from: the count of last month, or last month's register not yet counted. */
    private static function openingFrom(string $holder, string $prev, float $opening): string
    {
        if ($opening == 0.0) {
            return '';
        }
        $month = PettyCash::monthText($prev);
        $counted = App::db()->one('SELECT cbc_counted, created_at FROM cbc_mstr WHERE cbc_holder = ? AND cbc_month = ?', [$holder, $prev]);
        return $counted ? t('pettycash.from_count', ['month' => $month, 'date' => PettyCash::dateText(substr((string) $counted['created_at'], 0, 10))])
                         : t('pettycash.from_book', ['month' => $month]);
    }

    /** Holders with money (for "รับเงินจาก"). */
    #[Get('/api/petty-cash/holders')]
    public function holders(): array
    {
        $out = [];
        foreach (App::db()->all('SELECT cbh_holder AS h, SUM(cbh_amt) AS bal FROM cbh_hist GROUP BY cbh_holder HAVING bal > 0') as $r) {
            if ($r['h'] !== Auth::userId()) {
                $out[] = ['value' => $r['h'], 'label' => PettyCash::name($r['h']) . ' · ' . number_format((float) $r['bal'], 2)];
            }
        }
        return $out;
    }

    #[Describe('Petty cash boxes whose month has ended and still have to be counted')]
    #[Get('/api/petty-cash/waiting')]
    public function waiting(): array
    {
        return PettyCash::uncounted(date('Y-m', strtotime(date('Y-m-01') . ' -1 day')));
    }

    #[Post('/api/petty-cash'), Action('add')]
    public function create(Request $request): array
    {
        return ['nbr' => PettyCash::create($request->body)];
    }

    #[Post('/api/petty-cash/count'), Action('add')]
    public function count(Request $request): array
    {
        $b = $request->body;
        return PettyCash::count((string) ($b['month'] ?? ''), is_array($b['notes'] ?? null) ? $b['notes'] : [], trim((string) ($b['remarks'] ?? '')) ?: null);
    }

    #[Post('/api/petty-cash/count/cancel'), Action('delete')]
    public function uncount(): null
    {
        PettyCash::uncount();
        return null;
    }

    #[Describe('One petty cash entry: head, expense lines, withholding certificate and attached files')]
    #[Get('/api/petty-cash/{nbr}')]
    public function show(string $nbr): array
    {
        $h = self::head($nbr);
        try {
            PettyCash::editable($nbr);
            $editable = true;
        } catch (HttpException) {
            $editable = false;
        }
        return [
            'head'  => $h + ['vd_label' => $h['cb_vd_code'] ? $h['cb_vd_code'] . ' ' . $h['vd_name'] : '', 'text' => self::rowText($h + ['amt' => 0], $h['cb_holder']), 'holder_name' => PettyCash::name($h['cb_holder']),
                             'from_name' => $h['cb_from'] ? PettyCash::name($h['cb_from']) : null],
            'lines' => App::db()->all(
                'SELECT d.*, ' . Gl::acctName('a') . ' AS ac_desc_th, w.wht_pct FROM cbd_det d LEFT JOIN ac_mstr a ON a.ac_acct = d.cbd_acct
                   LEFT JOIN wht_mstr w ON w.wht_code = d.cbd_wht_code WHERE d.cbd_nbr = ? ORDER BY d.cbd_line',
                [$nbr]
            ),
            'wht'   => App::db()->value("SELECT whtc_nbr FROM wht_cert WHERE whtc_doc_ref = ? AND whtc_status <> 'C'", [$nbr]) ?: null,
            'files' => Attachment::list('PC', $nbr),
            'can'   => [
                'edit'   => $editable && Auth::can('petty-cash', 'edit'),
                'cancel' => $editable && Auth::can('petty-cash', 'delete'),
                'print'  => Auth::can('petty-cash', 'print'),
            ],
        ];
    }

    /** Correct an entry (same body as a new one; its kind stays): by its maker while its month is not counted. */
    #[Put('/api/petty-cash/{nbr}'), Action('edit')]
    public function update(string $nbr, Request $request): array
    {
        PettyCash::update($nbr, $request->body);
        return ['nbr' => $nbr];
    }

    #[Post('/api/petty-cash/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        PettyCash::cancel($nbr);
        return null;
    }

    /** ใบสำคัญจ่ายเงินสดย่อย / ใบเบิก-คืนเงินสดย่อย on the shared A4 layout, signed by the holder and the payee. */
    #[Get('/petty-cash/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $m = fn ($v) => number_format((float) $v, 2);
        $meta = [[t('sales.date'), SalesPrint::date($h['cb_date'])], [t('pettycash.holder'), PettyCash::name($h['cb_holder'])]];
        if ($h['cb_type'] === 'EX') {
            $lines = App::db()->all('SELECT d.*, a.ac_desc_th FROM cbd_det d LEFT JOIN ac_mstr a ON a.ac_acct = d.cbd_acct WHERE d.cbd_nbr = ? ORDER BY d.cbd_line', [$nbr]);
            $rows = array_map(fn ($l) => [$l['cbd_line'], trim($l['cbd_acct'] . ' ' . $l['ac_desc_th']), (string) $l['cbd_desc'], $l['cbd_cc'] ?? '', $m($l['cbd_amt'])], $lines);
            $pct = $h['cb_tax_code'] ? Money::taxPct($h['cb_tax_code']) : 0;
            $totals = SalesPrint::totals($h['cb_net_amt'], $pct, $h['cb_vat_amt'], (float) $h['cb_net_amt'] + (float) $h['cb_vat_amt'], Money::base());
            if ((float) $h['cb_wht_amt'] != 0.0) {
                $totals['rows'][] = [t('pettycash.wht'), $m(-$h['cb_wht_amt']), false];
                $totals['rows'][] = [t('pettycash.paid'), $m($h['cb_amt']), true];
                $totals['words'] = \Phai\Core\BahtText::of((float) $h['cb_amt']);
            }
            if ($h['cb_inv_nbr']) {
                $meta[] = [t('pettycash.inv_nbr'), $h['cb_inv_nbr'] . ' · ' . SalesPrint::date($h['cb_inv_date'])];
            }
            return SalesPrint::render([
                'title' => t('pettycash.print_EX'), 'nbr' => $nbr, 'meta' => $meta,
                'party' => $h['cb_vd_code'] ? SalesPrint::vendor($h['cb_vd_code'])
                    : ['label' => t('pettycash.payee'), 'name' => (string) $h['cb_payee'], 'lines' => array_values(array_filter([$h['cb_tax_id'] ? t('sales.tax_id') . ' ' . $h['cb_tax_id'] : '']))],
                'lines' => ['columns' => [['label' => '#', 'width' => '28px'], ['label' => t('pettycash.acct')], ['label' => t('stock.desc')],
                                          ['label' => t('pettycash.cc'), 'width' => '60px'], ['label' => t('accounting.amount'), 'align' => 'r', 'width' => '110px']],
                            'rows' => $rows],
                'totals' => $totals,
                'notes'  => (string) $h['cb_remarks'],
                'signs'  => [t('pettycash.sign_payer'), t('pettycash.sign_payee'), t('pettycash.sign_approver')],
            ]);
        }
        $what = self::rowText($h + ['amt' => 0], $h['cb_holder']);
        return SalesPrint::render([
            'title' => t("pettycash.print_{$h['cb_type']}"), 'nbr' => $nbr, 'meta' => $meta,
            'party' => ['label' => t('pettycash.holder'), 'name' => PettyCash::name($h['cb_holder']), 'lines' => []],
            'lines' => ['columns' => [['label' => '#', 'width' => '28px'], ['label' => t('stock.desc')], ['label' => t('accounting.amount'), 'align' => 'r', 'width' => '120px']],
                        'rows' => [[1, trim($what . ' ' . (string) $h['cb_remarks']), $m(abs((float) $h['cb_amt']))]]],
            'totals' => ['rows' => [[t('sales.total'), $m(abs((float) $h['cb_amt'])), true]], 'words' => \Phai\Core\BahtText::of(abs((float) $h['cb_amt']))],
            'notes'  => '',
            'signs'  => match ($h['cb_type']) {
                'TF', 'TO' => [t('pettycash.sign_sender'), t('pettycash.sign_receiver')],
                'IN' => [t('pettycash.sign_payer'), t('pettycash.sign_holder')],
                default => [t('pettycash.sign_holder'), t('pettycash.sign_approver')],
            },
        ]);
    }

    /** ทะเบียนเงินสดย่อย of one box for a month: brought forward, entries, balance, the count — signed by the holder. */
    #[Get('/petty-cash/register/{month}/print'), Action('print')]
    public function register(string $month, Request $request): string
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw HttpException::notFound();
        }
        $box = self::boxData((string) $request->input('holder', Auth::userId()), $month);
        return View::render('accounting/petty-cash-print', [
            'title'    => t('pettycash.register'),
            'docTitle' => 'PettyCash-' . $box['holder'] . '-' . $month,
            'box'      => $box,
        ], 'blank');
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT h.*, k.bk_desc, v.vd_name FROM cb_mstr h LEFT JOIN bk_mstr k ON k.bk_bank = h.cb_bank LEFT JOIN vd_mstr v ON v.vd_code = h.cb_vd_code
              WHERE h.cb_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    /** What an entry is, seen from $holder's box: "เบิกจาก BANK1", "รับเงินจาก สมพร", "ส่งเงินให้ สมศรี", "ค่าน้ำมัน · ร้าน ก". */
    private static function rowText(array $r, string $holder): string
    {
        return match ($r['cb_type']) {
            'WD' => t('pettycash.text_WD', ['bank' => $r['cb_bank']]),
            'RT' => t('pettycash.text_RT', ['bank' => $r['cb_bank']]),
            'TF' => t('pettycash.text_in', ['who' => PettyCash::name((string) $r['cb_from'])]),
            'TO' => t('pettycash.text_out', ['who' => PettyCash::name((string) $r['cb_from'])]),
            'EX' => trim(t('pettycash.text_EX') . ' · ' . (string) $r['cb_payee']),
            'IN' => trim(t('pettycash.text_IN') . ' · ' . (string) $r['cb_payee']),
            'DO' => t('pettycash.text_DO', ['ref' => $r['cb_ref'], 'who' => (string) $r['cb_payee']]),
            'DI' => t('pettycash.text_DI', ['ref' => $r['cb_ref'], 'who' => (string) $r['cb_payee']]),
            'CT' => t('pettycash.text_CT'),
            default => '',
        };
    }

    /** Files are the maker's, and only while the entry's month is not counted. */
    /** Words the page's script needs: the whole pettycash group. */
    private static function text(): array
    {
        return \Phai\Core\Lang::group(\Phai\Core\Lang::locale(), 'pettycash') ?: \Phai\Core\Lang::group('th', 'pettycash');
    }
}
