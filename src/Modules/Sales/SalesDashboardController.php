<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\System\Flow;

final class SalesDashboardController
{
    #[Get('/sales')]
    public function index(): string
    {
        $db = App::db();
        $today = date('Y-m-d');
        $month = date('Y-m-01');
        $can = fn (string $prog, string $action = 'view') => Auth::can($prog, $action);

        $kpis = [];
        $recentSos = [];
        $topParts = [];
        $topCustomers = [];

        if ($can('invoices')) {
            $sales = fn (string $from, string $to) => (float) $db->value(
                "SELECT COALESCE(SUM((ih_net_amt + ih_dp_amt) * ih_ex_rate), 0)
                   FROM ih_mstr
                  WHERE ih_status <> 'C' AND ih_inv_date >= ? AND ih_inv_date < ?",
                [$from, $to]
            );
            $now = $sales($month, date('Y-m-d', strtotime("$month +1 month")));
            $lastStart = date('Y-m-d', strtotime("$month -1 month"));
            $prev = $sales($lastStart, min($month, date('Y-m-d', strtotime($lastStart . ' +' . date('j') . ' days'))));

            $kpis[] = [
                'icon' => 'ti-trending-up',
                'color' => 'green',
                'label' => t('overview.sales_kpi_month'),
                'value' => $now,
                'link' => '/invoices',
                'sub' => $prev > 0 ? ['pct' => round(($now - $prev) / $prev * 100), 'text' => t('overview.compare_prev')] : null,
            ];

            // Top Customers this year
            $topCustomers = $db->all(
                "SELECT h.ih_cm_code AS code, c.cm_name AS name, SUM((h.ih_net_amt + h.ih_dp_amt) * h.ih_ex_rate) AS amt
                   FROM ih_mstr h
                   LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code
                  WHERE h.ih_status <> 'C' AND h.ih_inv_date >= ?
                  GROUP BY h.ih_cm_code, c.cm_name
                  ORDER BY amt DESC LIMIT 5",
                [date('Y-01-01')]
            );
        }

        if ($can('sales-orders')) {
            $openSoCount = (int) $db->value("SELECT COUNT(*) FROM so_mstr WHERE so_status = 'O'");
            $lateSoCount = (int) $db->value("SELECT COUNT(*) FROM so_mstr WHERE so_status = 'O' AND so_due_date < ?", [$today]);

            $kpis[] = [
                'icon' => 'ti-file-dollar',
                'color' => 'blue',
                'label' => t('overview.sales_kpi_open'),
                'value' => (float) $openSoCount,
                'is_qty' => true,
                'link' => '/sales-orders',
                'note' => $lateSoCount > 0 ? t('overview.sales_open_late', ['n' => $lateSoCount]) : t('overview.on_schedule'),
            ];

            $recentSos = $db->all(
                "SELECT s.so_nbr, s.so_cm_code, c.cm_name, s.so_date, s.so_due_date, s.so_status,
                        (s.so_tot_amt * s.so_ex_rate) AS amt
                   FROM so_mstr s
                   LEFT JOIN cm_mstr c ON c.cm_code = s.so_cm_code
                  ORDER BY s.so_date DESC, s.so_nbr DESC LIMIT 6"
            );

            // Top selling parts
            $topParts = $db->all(
                "SELECT d.sod_part AS part, p.pt_desc1 AS desc1, p.pt_um AS um,
                        SUM(d.sod_qty_ord) AS qty, SUM(d.sod_line_amt) AS amt
                   FROM sod_det d
                   JOIN so_mstr s ON s.so_nbr = d.sod_nbr
                   LEFT JOIN pt_mstr p ON p.pt_part = d.sod_part
                  WHERE s.so_status <> 'X' AND s.so_date >= ?
                  GROUP BY d.sod_part, p.pt_desc1, p.pt_um
                  ORDER BY amt DESC LIMIT 5",
                [date('Y-01-01')]
            );
        }

        if ($can('quotations')) {
            $openQtCount = (int) $db->value("SELECT COUNT(*) FROM qt_mstr WHERE qt_status = 'O'");
            $kpis[] = [
                'icon' => 'ti-file-text',
                'color' => 'indigo',
                'label' => t('overview.sales_kpi_qt'),
                'value' => (float) $openQtCount,
                'is_qty' => true,
                'link' => '/quotations',
                'note' => $openQtCount > 0 ? t('overview.sales_qt_note') : t('overview.none_pending'),
            ];
        }

        if ($can('shipments')) {
            $unbilledShipCount = (int) $db->value("SELECT COUNT(*) FROM sh_mstr WHERE sh_status = 'C'");
            $kpis[] = [
                'icon' => 'ti-truck-loading',
                'color' => $unbilledShipCount > 0 ? 'orange' : 'teal',
                'label' => t('overview.sales_kpi_unbilled'),
                'value' => (float) $unbilledShipCount,
                'is_qty' => true,
                'link' => '/invoices/new',
                'note' => $unbilledShipCount > 0 ? t('overview.sales_unbilled_note') : t('overview.sales_unbilled_none'),
            ];
        }


        return View::render('sales/dashboard', [
            'flow'        => Flow::of('/sales'),
            'scripts'     => ['js/flow.js'],
            'help'       => 'sales-dashboard',
            'title'        => t('overview.sales_title'),
            'company'      => $db->one('SELECT co_name, co_base_curr FROM co_mstr WHERE co_id = 1'),
            'kpis'         => $kpis,
            'recentSos'    => $recentSos,
            'topParts'     => $topParts,
            'topCustomers' => $topCustomers,
        ]);
    }
}
