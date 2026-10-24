<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\System\Flow;

final class AccountingDashboardController
{
    #[Get('/accounting')]
    public function index(): string
    {
        $db = App::db();
        $today = date('Y-m-d');
        $month = date('Y-m-01');
        $can = fn (string $prog, string $action = 'view') => Auth::can($prog, $action);

        $kpis = [];
        $overdueAr = [];
        $dueSoonAp = [];
        $banks = [];

        if ($can('receivables')) {
            $ar = $db->one(
                "SELECT COALESCE(SUM(ar_amt - ar_paid_amt), 0) AS open,
                        COALESCE(SUM(IF(ar_due_date < ?, ar_amt - ar_paid_amt, 0)), 0) AS late,
                        COUNT(IF(ar_due_date < ?, 1, NULL)) AS late_cnt
                   FROM ar_mstr WHERE ar_status = 'O'",
                [$today, $today]
            );
            $lateAmt = (float) ($ar['late'] ?? 0);
            $lateCnt = (int) ($ar['late_cnt'] ?? 0);
            $kpis[] = [
                'icon' => 'ti-cash',
                'color' => 'blue',
                'label' => t('overview.acct_kpi_ar'),
                'value' => (float) ($ar['open'] ?? 0),
                'link' => '/receivables',
                'note' => $lateAmt > 0 ? t('overview.acct_ar_late', ['amt' => number_format($lateAmt, 2), 'count' => $lateCnt]) : t('overview.acct_ar_none'),
            ];

            if ($lateCnt > 0) {
                $overdueAr = $db->all(
                    "SELECT a.ar_nbr, a.ar_cm_code, c.cm_name, a.ar_date, a.ar_due_date,
                            (a.ar_amt - a.ar_paid_amt) AS balance,
                            DATEDIFF(?, a.ar_due_date) AS days_late
                       FROM ar_mstr a
                       LEFT JOIN cm_mstr c ON c.cm_code = a.ar_cm_code
                      WHERE a.ar_status = 'O' AND a.ar_due_date < ?
                      ORDER BY balance DESC LIMIT 5",
                    [$today, $today]
                );
            }
        }

        if ($can('payables')) {
            $sevenDays = date('Y-m-d', strtotime('+7 days'));
            $ap = $db->one(
                "SELECT COALESCE(SUM(ap_amt - ap_paid_amt), 0) AS open,
                        COALESCE(SUM(IF(ap_due_date <= ?, ap_amt - ap_paid_amt, 0)), 0) AS soon,
                        COUNT(IF(ap_due_date <= ?, 1, NULL)) AS soon_cnt
                   FROM ap_mstr WHERE ap_status = 'O'",
                [$sevenDays, $sevenDays]
            );
            $soonAmt = (float) ($ap['soon'] ?? 0);
            $soonCnt = (int) ($ap['soon_cnt'] ?? 0);
            $kpis[] = [
                'icon' => 'ti-receipt-2',
                'color' => 'orange',
                'label' => t('overview.acct_kpi_ap'),
                'value' => (float) ($ap['open'] ?? 0),
                'link' => '/payables',
                'note' => $soonAmt > 0 ? t('overview.acct_ap_soon', ['amt' => number_format($soonAmt, 2), 'count' => $soonCnt]) : t('overview.acct_ap_none'),
            ];

            if ($soonCnt > 0) {
                $dueSoonAp = $db->all(
                    "SELECT a.ap_ref, a.ap_vd_code, v.vd_name, a.ap_date, a.ap_due_date,
                            (a.ap_amt - a.ap_paid_amt) AS balance
                       FROM ap_mstr a
                       LEFT JOIN vd_mstr v ON v.vd_code = a.ap_vd_code
                      WHERE a.ap_status = 'O' AND a.ap_due_date <= ?
                      ORDER BY a.ap_due_date ASC, balance DESC LIMIT 5",
                    [$sevenDays]
                );
            }
        }

        if ($can('vat-sales') || $can('vat-purchases')) {
            // Est. VAT this month: the ภ.พ.30 figures so far (the two VAT reports and last month's credit)
            $netVat = VatReturnController::compute(substr($month, 0, 7))['net'];
            $kpis[] = [
                'icon' => 'ti-percentage',
                'color' => 'teal',
                'label' => t('overview.acct_kpi_vat'),
                'value' => abs($netVat),
                'link' => '/vat-return',
                'note' => $netVat >= 0 ? t('overview.acct_vat_pay') : t('overview.acct_vat_refund'),
            ];
        }

        if ($can('banks')) {
            $banks = $db->all('SELECT bk_bank, bk_desc, bk_curr, bk_acct FROM bk_mstr WHERE bk_active = 1 AND bk_petty = 0 LIMIT 5');
        }


        return View::render('accounting/dashboard', [
            'flow'        => Flow::of('/accounting'),
            'scripts'     => ['js/flow.js'],
            'help'       => 'accounting-dashboard',
            'title'      => t('overview.acct_title'),
            'company'    => $db->one('SELECT co_name, co_base_curr FROM co_mstr WHERE co_id = 1'),
            'kpis'       => $kpis,
            'overdueAr'  => $overdueAr,
            'dueSoonAp'  => $dueSoonAp,
            'banks'      => $banks,
        ]);
    }
}
