<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\Sales\Sales;

/**
 * หน้าแรก: the owner's view of the company — cash, profit, what is coming in and going out, and what needs a decision.
 * A page to read (nothing links away), printable as PDF. Every part is shown only to users who may open the screen
 * behind it, so staff see their own part. Amounts are in the base currency (baht).
 */
final class HomeController
{
    #[Get('/')]
    public function index(): string
    {
        $db = App::db();
        $hour = (int) date('G');
        return View::render('home', self::common() + [
            'help'     => 'home',
            'title'    => t('common.home'),
            'greeting' => t($hour < 12 ? 'home.morning' : ($hour < 17 ? 'home.afternoon' : 'home.evening'), ['name' => Auth::user()['us_name'] ?? '']),
            'period'   => $db->one('SELECT gl_curr_year, gl_curr_per FROM gl_ctrl WHERE ctrl_id = 1'),
            'logo'     => Logo::url(),
            'empty'    => Auth::isAdmin() && !$db->value('SELECT 1 FROM pt_mstr LIMIT 1'),
        ]);
    }

    /** The same page on A4 (landscape) to print or save as PDF (?pdf=1); the date is the file name. */
    #[Get('/dashboard/{date}/print')]
    public function print(string $date): string
    {
        return View::render('home-print', self::common() + [
            'title'    => t('home.report_title'),
            'docTitle' => 'Dashboard-' . date('Y-m-d'),
            'logo'     => Logo::printSource(), // embedded: the PDF browser has no session
        ], 'blank');
    }

    private static function common(): array
    {
        return [
            'today'   => self::longDate(),
            'company' => App::db()->one('SELECT co_name, co_base_curr FROM co_mstr WHERE co_id = 1'),
            'f'       => self::figures(),
        ];
    }

    /** Every figure of the page, each null (or empty) when the user may not see the screen behind it. */
    public static function figures(): array
    {
        $db = App::db();
        $today = date('Y-m-d');
        $month = date('Y-m-01');
        $yearStart = date('Y-01-01');
        $can = fn (string ...$progs) => array_filter($progs, fn ($p) => Auth::can($p)) !== [];
        $monthNames = explode(',', t('home.months'));
        $f = ['cash' => null, 'petty' => 0.0, 'sales' => null, 'gross' => null, 'net' => null, 'backlog' => null, 'ar' => null, 'ap' => null, 'stock' => null,
              'trend' => null, 'flow' => null, 'aging' => null, 'alerts' => [], 'tax' => [], 'top' => []];

        // ---------------------------------------------------------------- money in the bank today
        if ($can('bank-rec', 'financial-statements')) {
            $f['cash'] = (float) $db->value(
                'SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_date <= ? AND gl_acct IN (SELECT bk_acct FROM bk_mstr WHERE bk_petty = 0)',
                [$today]
            );
            // and in the hands of the petty cash holders (one ledger account for all their boxes)
            $f['petty'] = (float) $db->value('SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_date <= ? AND gl_acct = ?', [$today, \Phai\Modules\Accounting\Gl::acct('pc')]);
        }

        // ---------------------------------------------------------------- sales (invoices, credit and debit notes; a deposit deducted on the invoice still counts)
        $sales = fn (string $from, string $to) => (float) $db->value(
            "SELECT COALESCE(SUM((ih_net_amt + ih_dp_amt) * ih_ex_rate), 0) FROM ih_mstr WHERE ih_status <> 'C' AND ih_inv_date >= ? AND ih_inv_date < ?",
            [$from, $to]
        );
        $first = date('Y-m-01', strtotime("$month -11 months")); // twelve months, this one last
        if ($can('invoices')) {
            $now = $sales($month, date('Y-m-d', strtotime("$month +1 month")));
            // the same days of last month, so the 3rd of a month is not compared with a whole month
            $lastStart = date('Y-m-d', strtotime("$month -1 month"));
            $prev = $sales($lastStart, min($month, date('Y-m-d', strtotime($lastStart . ' +' . date('j') . ' days'))));
            $f['sales'] = ['amt' => $now, 'pct' => $prev > 0 ? round(($now - $prev) / $prev * 100) : null];

            $byMonth = array_column($db->all(
                "SELECT DATE_FORMAT(ih_inv_date, '%Y-%m') AS ym, SUM((ih_net_amt + ih_dp_amt) * ih_ex_rate) AS amt
                   FROM ih_mstr WHERE ih_status <> 'C' AND ih_inv_date >= ? GROUP BY ym",
                [date('Y-m-d', strtotime("$first -1 year"))]
            ), 'amt', 'ym');
            $f['trend'] = [];
            for ($i = 11; $i >= 0; $i--) {
                $ts = strtotime("$month -$i months");
                $f['trend'][date('Y-m', $ts)] = [
                    'label' => $monthNames[(int) date('n', $ts) - 1] ?? date('M', $ts),
                    'sales' => (float) ($byMonth[date('Y-m', $ts)] ?? 0),
                    'last'  => (float) ($byMonth[date('Y-m', strtotime('-1 year', $ts))] ?? 0),
                    'gp'    => null,
                ];
            }

            // best customers of the twelve months, with what they leave after the cost of the goods shipped to them
            $f['top'] = $db->all(
                "SELECT h.ih_cm_code AS code, c.cm_name AS name, SUM((h.ih_net_amt + h.ih_dp_amt) * h.ih_ex_rate) AS sales
                   FROM ih_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code
                  WHERE h.ih_status <> 'C' AND h.ih_inv_date >= ?
                  GROUP BY h.ih_cm_code, c.cm_name ORDER BY sales DESC LIMIT 5",
                [$first]
            );
            if ($f['top'] && $can('financial-statements')) {
                $cost = array_column($db->all(
                    "SELECT s.sh_cm_code AS code, SUM(-t.tr_chg_qty * t.tr_cost) AS cost
                       FROM tr_hist t JOIN sh_mstr s ON s.sh_nbr = t.tr_nbr
                      WHERE t.tr_type = 'ISS-SO' AND t.tr_date >= ? GROUP BY s.sh_cm_code",
                    [$first]
                ), 'cost', 'code');
                foreach ($f['top'] as &$c) {
                    $c['gp'] = (float) $c['sales'] - (float) ($cost[$c['code']] ?? 0);
                }
                unset($c);
            }
        }

        // ---------------------------------------------------------------- profit, from the ledger (as the income statement)
        if ($can('financial-statements')) {
            $pl = fn (string $from, string $to) => $db->one(
                "SELECT COALESCE(-SUM(IF(a.ac_acct LIKE '401%', g.gl_amt, 0)), 0) AS rev,
                        COALESCE(SUM(IF(a.ac_acct LIKE '501%', g.gl_amt, 0)), 0) AS cogs,
                        COALESCE(-SUM(g.gl_amt), 0) AS net
                   FROM gl_trhist g JOIN ac_mstr a ON a.ac_acct = g.gl_acct
                  WHERE a.ac_type IN ('R', 'X') AND g.gl_date BETWEEN ? AND ?",
                [$from, $to]
            );
            $m = $pl($month, $today);
            $gross = (float) $m['rev'] - (float) $m['cogs'];
            $f['gross'] = ['amt' => $gross, 'pct' => (float) $m['rev'] != 0.0 ? round($gross / (float) $m['rev'] * 100, 1) : null];
            $f['net'] = (float) $pl($yearStart, $today)['net'];
            if ($f['trend'] !== null) {
                foreach ($db->all(
                    "SELECT DATE_FORMAT(g.gl_date, '%Y-%m') AS ym,
                            -SUM(IF(a.ac_acct LIKE '401%', g.gl_amt, 0)) AS rev, SUM(IF(a.ac_acct LIKE '501%', g.gl_amt, 0)) AS cogs
                       FROM gl_trhist g JOIN ac_mstr a ON a.ac_acct = g.gl_acct
                      WHERE a.ac_type IN ('R', 'X') AND g.gl_date >= ? GROUP BY ym",
                    [$first]
                ) as $r) {
                    if (isset($f['trend'][$r['ym']]) && (float) $r['rev'] > 0) {
                        $f['trend'][$r['ym']]['gp'] = round(((float) $r['rev'] - (float) $r['cogs']) / (float) $r['rev'] * 100, 1);
                    }
                }
            }
        }

        // ---------------------------------------------------------------- orders taken, not yet shipped (before VAT)
        if ($can('sales-orders')) {
            $f['backlog'] = $db->one(
                "SELECT COALESCE(SUM(GREATEST(0, d.sod_qty_ord - d.sod_qty_shp) * d.sod_price * (1 - d.sod_disc_pct / 100) * s.so_ex_rate), 0) AS amt,
                        COUNT(DISTINCT IF(d.sod_qty_ord > d.sod_qty_shp, s.so_nbr, NULL)) AS orders
                   FROM sod_det d JOIN so_mstr s ON s.so_nbr = d.sod_nbr
                  WHERE s.so_status = 'O' AND d.sod_status <> 'X'"
            );
        }

        // ---------------------------------------------------------------- what customers owe, what we owe
        if ($can('receivables')) {
            $open = $db->all(
                "SELECT ar_cm_code AS cm, ar_due_date AS due, ar_amt - ar_paid_amt AS bal FROM ar_mstr WHERE ar_status = 'O' AND ar_amt <> ar_paid_amt"
            );
            $aging = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd90' => 0.0, 'over' => 0.0];
            $late = [];
            foreach ($open as $r) {
                $days = $r['due'] ? (int) floor((strtotime($today) - strtotime($r['due'])) / 86400) : 0;
                $key = match (true) { $days <= 0 => 'current', $days <= 30 => 'd30', $days <= 60 => 'd60', $days <= 90 => 'd90', default => 'over' };
                $aging[$key] += (float) $r['bal'];
                if ($days > 0 && (float) $r['bal'] > 0) {
                    $late[$r['cm']] ??= ['amt' => 0.0, 'days' => 0];
                    $late[$r['cm']]['amt'] += (float) $r['bal'];
                    $late[$r['cm']]['days'] = max($late[$r['cm']]['days'], $days);
                }
            }
            $f['ar'] = ['open' => array_sum($aging), 'late' => array_sum($aging) - $aging['current']];
            $f['aging'] = $aging;
            uasort($late, fn ($a, $b) => $b['amt'] <=> $a['amt']);
            $names = self::names('cm_mstr', 'cm_code', 'cm_name', array_keys($late));
            $f['alerts']['ar_late'] = array_map(fn ($cm) => [$cm . ' ' . ($names[$cm] ?? ''), $late[$cm]['amt'], t('home.days_late', ['n' => $late[$cm]['days']])],
                array_slice(array_keys($late), 0, 5));
        }
        // payables still open, and payment vouchers (ใบสำคัญจ่าย) not paid yet: both are money to go out
        $apOpen = "SELECT ap_due_date AS due, ap_amt - ap_paid_amt AS bal FROM ap_mstr WHERE ap_status = 'O'
                   UNION ALL SELECT apv_pay_date, apv_amt - apv_paid_amt FROM apv_mstr WHERE apv_status = 'O'";
        if ($can('payables', 'ap-vouchers', 'vouchers')) {
            $ap = $db->one("SELECT COALESCE(SUM(bal), 0) AS open, COALESCE(SUM(IF(due <= ?, bal, 0)), 0) AS soon FROM ($apOpen) x", [date('Y-m-d', strtotime('+7 days'))]);
            $f['ap'] = ['open' => (float) $ap['open'], 'soon' => (float) $ap['soon']];
        }

        // ---------------------------------------------------------------- cash for the next eight weeks
        // starts from the bank today; what customers owe comes in on its due date and what we owe goes out on its own
        // (anything already late is counted in the first week)
        if ($f['ar'] !== null && $f['ap'] !== null) {
            $monday = strtotime('monday this week', strtotime($today));
            $week = fn (?string $due) => max(0, min(7, $due ? (int) floor((strtotime($due) - $monday) / (7 * 86400)) : 0));
            $weeks = [];
            for ($i = 0; $i < 8; $i++) {
                $weeks[$i] = ['label' => date('j', $monday + $i * 7 * 86400) . ' ' . ($monthNames[(int) date('n', $monday + $i * 7 * 86400) - 1] ?? ''), 'in' => 0.0, 'out' => 0.0, 'bal' => 0.0];
            }
            foreach ($open as $r) {
                $weeks[$week($r['due'])]['in'] += (float) $r['bal'];
            }
            foreach ($db->all($apOpen) as $r) {
                $weeks[$week($r['due'])]['out'] += (float) $r['bal'];
            }
            $bal = $f['cash'] ?? 0.0;
            foreach ($weeks as &$w) {
                $bal += $w['in'] - $w['out'];
                $w['bal'] = $bal;
            }
            unset($w);
            $f['flow'] = ['start' => $f['cash'], 'weeks' => $weeks];
        }

        // ---------------------------------------------------------------- stock
        if ($can('stock')) {
            $st = $db->one(
                'SELECT COALESCE(SUM(ld.ld_qty_oh * pt.pt_cost), 0) AS value, COUNT(DISTINCT IF(ld.ld_qty_oh > 0, ld.ld_part, NULL)) AS parts
                   FROM ld_det ld JOIN pt_mstr pt ON pt.pt_part = ld.ld_part'
            );
            $f['stock'] = ['value' => (float) $st['value'], 'parts' => (int) $st['parts']];
            // below the reorder point (or safety stock) of its warehouse
            $f['alerts']['low_stock'] = array_map(fn ($r) => [$r['part'] . ' ' . $r['name'] . ' · ' . $r['site'], null,
                t('home.low_stock_left', ['qty' => rtrim(rtrim(number_format((float) $r['oh'], 2), '0'), '.'), 'point' => rtrim(rtrim(number_format((float) $r['point'], 2), '0'), '.')])],
                $db->all(
                    "SELECT p.pt_site AS site, p.pt_part AS part, m.pt_desc1 AS name, GREATEST(p.pt_safe_stk, p.pt_rop) AS point,
                            COALESCE((SELECT SUM(ld_qty_oh) FROM ld_det WHERE ld_site = p.pt_site AND ld_part = p.pt_part), 0) AS oh
                       FROM pt_site_mstr p JOIN pt_mstr m ON m.pt_part = p.pt_part
                      WHERE GREATEST(p.pt_safe_stk, p.pt_rop) > 0
                     HAVING oh < point ORDER BY oh / point LIMIT 5"
                ));
        }

        // ---------------------------------------------------------------- customers over their credit limit
        if ($can('customers', 'sales-orders')) {
            $over = [];
            foreach ($db->all("SELECT cm_code, cm_name, cm_cr_limit FROM cm_mstr WHERE cm_cr_limit > 0 AND cm_status = 'ACTIVE'") as $c) {
                $used = Sales::exposure($c['cm_code']);
                if ($used > (float) $c['cm_cr_limit']) {
                    $over[] = [$c['cm_code'] . ' ' . $c['cm_name'], $used - (float) $c['cm_cr_limit'], t('home.over_limit_of', ['limit' => number_format((float) $c['cm_cr_limit'], 0)])];
                }
            }
            usort($over, fn ($a, $b) => $b[1] <=> $a[1]);
            $f['alerts']['over_limit'] = array_slice($over, 0, 5);
        }

        // ---------------------------------------------------------------- late: deliveries to customers, production, vendors
        $lateRows = fn (string $sql) => array_map(fn ($r) => [$r['nbr'] . ' ' . $r['who'], null, t('home.days_late', ['n' => (int) $r['days']])], $db->all($sql, [$today, $today]));
        if ($can('sales-orders')) {
            $f['alerts']['so_late'] = $lateRows(
                "SELECT s.so_nbr AS nbr, c.cm_name AS who, DATEDIFF(?, MIN(COALESCE(d.sod_req_date, s.so_due_date, s.so_req_date))) AS days
                   FROM so_mstr s JOIN sod_det d ON d.sod_nbr = s.so_nbr LEFT JOIN cm_mstr c ON c.cm_code = s.so_cm_code
                  WHERE s.so_status = 'O' AND d.sod_status <> 'X' AND d.sod_qty_shp < d.sod_qty_ord
                    AND COALESCE(d.sod_req_date, s.so_due_date, s.so_req_date) < ?
                  GROUP BY s.so_nbr, c.cm_name ORDER BY days DESC LIMIT 5"
            );
        }
        if ($can('work-orders')) {
            $f['alerts']['wo_late'] = $lateRows(
                "SELECT w.wo_nbr AS nbr, p.pt_desc1 AS who, DATEDIFF(?, w.wo_due_date) AS days
                   FROM wo_mstr w LEFT JOIN pt_mstr p ON p.pt_part = w.wo_part
                  WHERE w.wo_status IN ('F', 'R') AND w.wo_due_date < ? ORDER BY days DESC LIMIT 5"
            );
        }
        if ($can('purchase-orders')) {
            $f['alerts']['po_late'] = $lateRows(
                "SELECT o.po_nbr AS nbr, v.vd_name AS who, DATEDIFF(?, MIN(COALESCE(d.pod_req_date, o.po_due_date))) AS days
                   FROM po_mstr o JOIN pod_det d ON d.pod_nbr = o.po_nbr LEFT JOIN vd_mstr v ON v.vd_code = o.po_vd_code
                  WHERE o.po_status = 'A' AND d.pod_status <> 'X' AND d.pod_qty_rcvd < d.pod_qty_ord AND COALESCE(d.pod_req_date, o.po_due_date) < ?
                  GROUP BY o.po_nbr, v.vd_name ORDER BY days DESC LIMIT 5"
            );
        }
        $f['alerts'] = array_filter($f['alerts']);

        // ---------------------------------------------------------------- tax returns of last month, and the books
        $last = date('Y-m', strtotime("$month -1 month"));
        $lastName = ($monthNames[(int) substr($last, 5) - 1] ?? '') . ' ' . ((int) substr($last, 0, 4) + (int) t('js.year_offset'));
        $due = fn (int $day) => $day . ' ' . ($monthNames[(int) date('n') - 1] ?? '');
        if ($can('vat-return')) {
            $vr = $db->one('SELECT vr_net, vr_date FROM vr_mstr WHERE vr_month = ?', [$last]);
            $f['tax'][] = ['name' => t('home.tax_vat', ['month' => $lastName]), 'done' => (bool) $vr,
                'text' => $vr ? t('home.tax_filed', ['amount' => number_format((float) $vr['vr_net'], 2)]) : t('home.tax_due', ['paper' => $due(15), 'online' => $due(23)])];
        }
        if ($can('wht-report')) {
            $wht = (float) $db->value(
                "SELECT COALESCE(SUM(whtc_tax_amt), 0) FROM wht_cert WHERE whtc_status <> 'C' AND DATE_FORMAT(whtc_date, '%Y-%m') = ?",
                [$last]
            );
            $f['tax'][] = ['name' => t('home.tax_wht', ['month' => $lastName]), 'done' => $wht == 0.0,
                'text' => $wht == 0.0 ? t('home.tax_none') : t('home.tax_wht_due', ['amount' => number_format($wht, 2), 'paper' => $due(7), 'online' => $due(15)])];
        }
        if ($can('trial-balance')) {
            $p = $db->one('SELECT gl_curr_year, gl_curr_per FROM gl_ctrl WHERE ctrl_id = 1');
            $per = sprintf('%04d-%02d', $p['gl_curr_year'] ?? 0, $p['gl_curr_per'] ?? 0);
            $f['tax'][] = ['name' => t('home.books'), 'done' => $per >= date('Y-m', strtotime("$month -1 month")) && $per <= date('Y-m'),
                'text' => t('home.books_open', ['period' => sprintf('%02d/%04d', $p['gl_curr_per'] ?? 0, ($p['gl_curr_year'] ?? 0) + (int) t('js.year_offset'))])];
        }
        return $f;
    }

    /** code => name of the given codes. */
    private static function names(string $table, string $key, string $name, array $codes): array
    {
        if (!$codes) {
            return [];
        }
        return array_column(App::db()->all(
            "SELECT $key AS k, $name AS n FROM $table WHERE $key IN (" . implode(',', array_fill(0, count($codes), '?')) . ')',
            array_values($codes)
        ), 'n', 'k');
    }

    /** "วันเสาร์ที่ 4 ตุลาคม 2569" */
    private static function longDate(): string
    {
        $days = explode(',', t('home.days'));
        $months = explode(',', t('home.months_long'));
        return t('home.date_long', [
            'day' => $days[(int) date('w')] ?? date('l'), 'd' => date('j'), 'month' => $months[(int) date('n') - 1] ?? date('F'), 'y' => (int) date('Y') + (int) t('js.year_offset'),
        ]);
    }
}
