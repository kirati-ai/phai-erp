<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\System\Flow;

final class PurchaseDashboardController
{
    #[Get('/purchase')]
    public function index(): string
    {
        $db = App::db();
        $today = date('Y-m-d');
        $month = date('Y-m-01');
        $can = fn (string $prog, string $action = 'view') => Auth::can($prog, $action);

        $kpis = [];
        $overduePos = [];
        $recentPos = [];
        $topVendors = [];

        if ($can('purchase-orders')) {
            // PO total this month
            $poMonth = (float) $db->value(
                "SELECT COALESCE(SUM(po_tot_amt * po_ex_rate), 0)
                   FROM po_mstr
                  WHERE po_status <> 'X' AND po_date >= ? AND po_date < ?",
                [$month, date('Y-m-d', strtotime("$month +1 month"))]
            );
            $lastStart = date('Y-m-d', strtotime("$month -1 month"));
            $poLastMonth = (float) $db->value(
                "SELECT COALESCE(SUM(po_tot_amt * po_ex_rate), 0)
                   FROM po_mstr
                  WHERE po_status <> 'X' AND po_date >= ? AND po_date < ?",
                [$lastStart, min($month, date('Y-m-d', strtotime($lastStart . ' +' . date('j') . ' days')))]
            );
            $kpis[] = [
                'icon' => 'ti-shopping-cart',
                'color' => 'blue',
                'label' => t('overview.pur_kpi_month'),
                'value' => $poMonth,
                'link' => '/purchase-orders',
                'sub' => $poLastMonth > 0 ? ['pct' => round(($poMonth - $poLastMonth) / $poLastMonth * 100), 'text' => t('overview.compare_prev')] : null,
            ];

            // Open PO count
            $openPoCount = (int) $db->value("SELECT COUNT(*) FROM po_mstr WHERE po_status = 'A'");
            $overdueCount = (int) $db->value("SELECT COUNT(*) FROM po_mstr WHERE po_status = 'A' AND po_due_date < ?", [$today]);
            $kpis[] = [
                'icon' => 'ti-clock-hour-4',
                'color' => $overdueCount > 0 ? 'orange' : 'teal',
                'label' => t('overview.pur_kpi_open'),
                'value' => (float) $openPoCount,
                'is_qty' => true,
                'link' => '/purchase-orders',
                'note' => $overdueCount > 0 ? t('overview.pur_open_late', ['n' => $overdueCount]) : t('overview.on_schedule'),
            ];

            // Overdue POs list
            if ($overdueCount > 0) {
                $overduePos = $db->all(
                    "SELECT p.po_nbr, p.po_vd_code, v.vd_name, p.po_date, p.po_due_date, (p.po_tot_amt * p.po_ex_rate) AS amt
                       FROM po_mstr p
                       LEFT JOIN vd_mstr v ON v.vd_code = p.po_vd_code
                      WHERE p.po_status = 'A' AND p.po_due_date < ?
                      ORDER BY p.po_due_date ASC LIMIT 5",
                    [$today]
                );
            }

            // Recent 5 POs
            $recentPos = $db->all(
                "SELECT p.po_nbr, p.po_vd_code, v.vd_name, p.po_date, p.po_due_date, p.po_status, (p.po_tot_amt * p.po_ex_rate) AS amt
                   FROM po_mstr p
                   LEFT JOIN vd_mstr v ON v.vd_code = p.po_vd_code
                  ORDER BY p.po_date DESC, p.po_nbr DESC LIMIT 5"
            );

            // Top Vendors this year
            $topVendors = $db->all(
                "SELECT p.po_vd_code AS code, v.vd_name AS name, SUM(p.po_tot_amt * p.po_ex_rate) AS amt
                   FROM po_mstr p
                   LEFT JOIN vd_mstr v ON v.vd_code = p.po_vd_code
                  WHERE p.po_status <> 'X' AND p.po_date >= ?
                  GROUP BY p.po_vd_code, v.vd_name
                  ORDER BY amt DESC LIMIT 5",
                [date('Y-01-01')]
            );
        }

        if ($can('purchase-requisitions')) {
            $pendingPrCount = (int) $db->value("SELECT COUNT(*) FROM pr_mstr WHERE pr_status = 'A'");
            $kpis[] = [
                'icon' => 'ti-file-plus',
                'color' => 'indigo',
                'label' => t('overview.pur_kpi_pr'),
                'value' => (float) $pendingPrCount,
                'is_qty' => true,
                'link' => '/purchase-requisitions',
                'note' => $pendingPrCount > 0 ? t('overview.pur_pr_note') : t('overview.none_pending'),
            ];
        }

        if ($can('receipts')) {
            $monthRcCount = (int) $db->value("SELECT COUNT(*) FROM rc_mstr WHERE rc_date >= ?", [$month]);
            $kpis[] = [
                'icon' => 'ti-truck-delivery',
                'color' => 'green',
                'label' => t('overview.pur_kpi_rc'),
                'value' => (float) $monthRcCount,
                'is_qty' => true,
                'link' => '/receipts',
                'note' => t('overview.pur_rc_note'),
            ];
        }


        return View::render('purchase/dashboard', [
            'flow'        => Flow::of('/purchase'),
            'scripts'     => ['js/flow.js'],
            'help'       => 'purchase-dashboard',
            'title'       => t('overview.pur_title'),
            'company'     => $db->one('SELECT co_name, co_base_curr FROM co_mstr WHERE co_id = 1'),
            'kpis'        => $kpis,
            'overduePos'  => $overduePos,
            'recentPos'   => $recentPos,
            'topVendors'  => $topVendors,
        ]);
    }
}
