<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\System\Flow;

final class InventoryDashboardController
{
    #[Get('/inventory')]
    public function index(): string
    {
        $db = App::db();
        $today = date('Y-m-d');
        $can = fn (string $prog, string $action = 'view') => Auth::can($prog, $action);

        $kpis = [];
        $lowStockItems = [];
        $typeBreakdown = [];
        $recentMoves = [];

        if ($can('stock')) {
            // Total inventory valuation & part count
            $val = $db->one(
                'SELECT COALESCE(SUM(ld.ld_qty_oh * pt.pt_cost), 0) AS value,
                        COUNT(DISTINCT IF(ld.ld_qty_oh > 0, ld.ld_part, NULL)) AS in_stock_parts,
                        (SELECT COUNT(*) FROM pt_mstr) AS total_parts
                   FROM ld_det ld
                   JOIN pt_mstr pt ON pt.pt_part = ld.ld_part'
            );
            $kpis[] = [
                'icon' => 'ti-building-warehouse',
                'color' => 'purple',
                'label' => t('overview.inv_kpi_value'),
                'value' => (float) ($val['value'] ?? 0),
                'link' => '/stock',
                'note' => t('overview.inv_value_note', ['in_stock' => number_format((int) ($val['in_stock_parts'] ?? 0)), 'total' => number_format((int) ($val['total_parts'] ?? 0))]),
            ];

            // Low stock items: on hand (all sites) below the summed per-site safety stock (pt_site_mstr.pt_safe_stk)
            $lowSql = 'SELECT pt.pt_part, pt.pt_desc1, pt.pt_um, s.safe AS pt_sfty_stk, COALESCE(l.qty, 0) AS qty_oh
                         FROM (SELECT pt_part, SUM(pt_safe_stk) AS safe FROM pt_site_mstr WHERE pt_safe_stk > 0 GROUP BY pt_part) s
                         JOIN pt_mstr pt ON pt.pt_part = s.pt_part
                         LEFT JOIN (SELECT ld_part, SUM(ld_qty_oh) AS qty FROM ld_det GROUP BY ld_part) l ON l.ld_part = s.pt_part
                        WHERE COALESCE(l.qty, 0) < s.safe';
            $lowStock = $db->all($lowSql . ' ORDER BY (COALESCE(l.qty, 0) / s.safe) ASC LIMIT 5');
            $lowStockCount = (int) $db->value("SELECT COUNT(*) FROM ($lowSql) t");
            $kpis[] = [
                'icon' => 'ti-alert-circle',
                'color' => $lowStockCount > 0 ? 'red' : 'green',
                'label' => t('overview.inv_kpi_low'),
                'value' => (float) $lowStockCount,
                'is_qty' => true,
                'link' => '/stock',
                'note' => $lowStockCount > 0 ? t('overview.inv_low_note') : t('overview.inv_low_none'),
            ];
            $lowStockItems = $lowStock;

            // Valuation breakdown by inventory type (FG, RM, etc.)
            $typeBreakdown = $db->all(
                "SELECT COALESCE(pl.pl_inventory_type, 'OTHER') AS inv_type,
                        COALESCE(SUM(ld.ld_qty_oh * pt.pt_cost), 0) AS amt
                   FROM ld_det ld
                   JOIN pt_mstr pt ON pt.pt_part = ld.ld_part
                   LEFT JOIN pl_mstr pl ON pl.pl_prod_line = pt.pt_prod_line
                  GROUP BY inv_type
                  ORDER BY amt DESC"
            );
        }

        if ($can('stock')) {
            $expiringCount = (int) $db->value(
                "SELECT COUNT(*) FROM lot_mstr
                  WHERE lot_expire_date IS NOT NULL AND lot_expire_date <= DATE_ADD(?, INTERVAL 30 DAY) AND lot_expire_date >= ?",
                [$today, $today]
            );
            $kpis[] = [
                'icon' => 'ti-clock-exclamation',
                'color' => $expiringCount > 0 ? 'orange' : 'teal',
                'label' => t('overview.inv_kpi_expiring'),
                'value' => (float) $expiringCount,
                'is_qty' => true,
                'link' => '/stock',
                'note' => $expiringCount > 0 ? t('overview.inv_expiring_note') : t('overview.inv_expiring_none'),
            ];
        }

        if ($can('stock-counts')) {
            $openCounts = (int) $db->value("SELECT COUNT(*) FROM pi_mstr WHERE pi_status = 'O'");
            $kpis[] = [
                'icon' => 'ti-clipboard-check',
                'color' => 'blue',
                'label' => t('overview.inv_kpi_counts'),
                'value' => (float) $openCounts,
                'is_qty' => true,
                'link' => '/stock-counts',
                'note' => $openCounts > 0 ? t('overview.inv_counts_note') : t('overview.inv_counts_none'),
            ];
        }

        if ($can('stock-history')) {
            $recentMoves = $db->all(
                'SELECT t.tr_trnbr, t.tr_date, t.tr_type, t.tr_part, p.pt_desc1, t.tr_loc, t.tr_chg_qty AS tr_qty_chg, t.tr_um
                   FROM tr_hist t
                   LEFT JOIN pt_mstr p ON p.pt_part = t.tr_part
                  ORDER BY t.tr_trnbr DESC LIMIT 6'
            );
        }


        return View::render('inventory/dashboard', [
            'flow'        => Flow::of('/inventory'),
            'scripts'     => ['js/flow.js'],
            'help'       => 'inventory-dashboard',
            'title'         => t('overview.inv_title'),
            'company'       => $db->one('SELECT co_name, co_base_curr FROM co_mstr WHERE co_id = 1'),
            'kpis'          => $kpis,
            'lowStockItems' => $lowStockItems,
            'typeBreakdown' => $typeBreakdown,
            'recentMoves'   => $recentMoves,
        ]);
    }
}
