<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\Inventory\Stock;
use Phai\Modules\Sales\Supply;
use Phai\Modules\System\Flow;

final class ProductionDashboardController
{
    #[Get('/production')]
    public function index(): string
    {
        $db = App::db();
        $today = date('Y-m-d');
        $month = date('Y-m-01');
        $can = fn (string $prog, string $action = 'view') => Auth::can($prog, $action);

        $kpis = [];
        $needs = [];
        $activeWos = [];
        $recentQc = [];

        if ($can('work-orders')) {
            $runningWoCount = (int) $db->value("SELECT COUNT(*) FROM wo_mstr WHERE wo_status = 'R'");
            $lateWoCount = (int) $db->value("SELECT COUNT(*) FROM wo_mstr WHERE wo_status = 'R' AND wo_due_date < ?", [$today]);
            $kpis[] = [
                'icon' => 'ti-building-factory-2',
                'color' => 'blue',
                'label' => t('overview.prod_kpi_running'),
                'value' => (float) $runningWoCount,
                'is_qty' => true,
                'link' => '/work-orders',
                'note' => $lateWoCount > 0 ? t('overview.prod_running_late', ['n' => $lateWoCount]) : t('overview.on_schedule'),
            ];

            $firmedWoCount = (int) $db->value("SELECT COUNT(*) FROM wo_mstr WHERE wo_status = 'F'");
            $kpis[] = [
                'icon' => 'ti-clipboard-list',
                'color' => 'indigo',
                'label' => t('overview.prod_kpi_firmed'),
                'value' => (float) $firmedWoCount,
                'is_qty' => true,
                'link' => '/work-orders',
                'note' => $firmedWoCount > 0 ? t('overview.prod_firmed_note') : t('overview.prod_firmed_none'),
            ];

            $compMonthCount = (int) $db->value("SELECT COUNT(*) FROM wo_mstr WHERE wo_status = 'C' AND updated_at >= ?", [$month]);
            $kpis[] = [
                'icon' => 'ti-circle-check',
                'color' => 'green',
                'label' => t('overview.prod_kpi_done'),
                'value' => (float) $compMonthCount,
                'is_qty' => true,
                'link' => '/work-orders',
                'note' => t('overview.prod_done_note'),
            ];

            // what to make: sales-order lines of items made here that the stock does not cover, matched to open work
            // orders the same way as the shipment queue (Supply::lines)
            foreach (Supply::lines() as $l) {
                if (!(int) $l['stocked'] || $l['pt_part_type'] !== 'M' || (float) $l['open_qty'] - (float) $l['from_stock'] <= 0.000001) {
                    continue;
                }
                if ($l['short'] > 0.000001) {
                    [$l['need'], $l['need_color']] = [t('overview.prod_need_open', ['qty' => Stock::fmt($l['short'])]), 'red'];
                } elseif (($l['want_date'] && $l['ready_date'] > $l['want_date']) || $l['ready_date'] < $today) {
                    [$l['need'], $l['need_color']] = [t('overview.prod_need_late'), 'orange'];
                } else {
                    [$l['need'], $l['need_color']] = [t('overview.prod_need_ok'), 'green'];
                }
                $needs[] = $l;
            }

            // Active WOs list
            $activeWos = $db->all(
                "SELECT w.wo_nbr, w.wo_part, p.pt_desc1, p.pt_um, w.wo_qty_ord, w.wo_qty_comp, w.wo_qty_rjct,
                        w.wo_rel_date, w.wo_due_date, w.wo_status
                   FROM wo_mstr w
                   LEFT JOIN pt_mstr p ON p.pt_part = w.wo_part
                  WHERE w.wo_status = 'R'
                  ORDER BY w.wo_due_date ASC, w.wo_nbr ASC LIMIT 8"
            );
        }

        if ($can('qc')) {
            $qcStats = $db->one(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(IF(qc_result = 'PASS', 1, 0)), 0) AS passed
                   FROM qc_hist
                  WHERE qc_date >= ?",
                [$month]
            );
            $totalQc = (int) ($qcStats['total'] ?? 0);
            $passedQc = (int) ($qcStats['passed'] ?? 0);
            $passRate = $totalQc > 0 ? round($passedQc / $totalQc * 100, 1) : 100.0;

            $kpis[] = [
                'icon' => 'ti-flask',
                'color' => $passRate >= 95 ? 'teal' : 'orange',
                'label' => t('overview.prod_kpi_qc'),
                'value' => $passRate,
                'is_pct' => true,
                'link' => '/qc',
                'note' => t('overview.prod_qc_note', ['total' => $totalQc, 'passed' => $passedQc]),
            ];

            $recentQc = $db->all(
                'SELECT q.qc_test_nbr, q.qc_ref_doc, q.qc_part, p.pt_desc1, q.qc_qty_inspect, q.qc_result, q.qc_date
                   FROM qc_hist q
                   LEFT JOIN pt_mstr p ON p.pt_part = q.qc_part
                  ORDER BY q.qc_date DESC, q.qc_test_nbr DESC LIMIT 5'
            );
        }


        return View::render('production/dashboard', [
            'flow'        => Flow::of('/production'),
            'scripts'     => ['js/flow.js'],
            'help'       => 'production-dashboard',
            'title'     => t('overview.prod_title'),
            'company'   => $db->one('SELECT co_name, co_base_curr FROM co_mstr WHERE co_id = 1'),
            'kpis'      => $kpis,
            'needs'     => $needs,
            'activeWos' => $activeWos,
            'recentQc'  => $recentQc,
        ]);
    }
}
