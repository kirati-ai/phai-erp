<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Crud\Lookup;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;

/**
 * สูตรการผลิต: the materials (BOM, /api/boms) and the steps (routing, /api/routings) of one item on one screen,
 * with what one unit costs. Each part keeps its own permission (boms / routings).
 */
#[Program('boms', 'routings')]
final class ProductionSetController
{
    #[Get('/boms')]
    public function page(Request $request): string
    {
        $bom = ['view' => Auth::can('boms'), 'add' => Auth::can('boms', 'add'), 'edit' => Auth::can('boms', 'edit'), 'delete' => Auth::can('boms', 'delete')];
        $ro = ['view' => Auth::can('routings'), 'add' => Auth::can('routings', 'add'), 'edit' => Auth::can('routings', 'edit'), 'delete' => Auth::can('routings', 'delete')];
        return View::render('production/sets', [
            'title'   => t('production.set_title'),
            'help'    => 'boms',
            'config'  => [
                'bom' => $bom + ['columns' => [
                    ['name' => 'ps_comp', 'label' => t('production.component'), 'type' => 'part'],
                    ['name' => 'ps_qty_per', 'label' => t('production.qty_per'), 'type' => 'number', 'width' => '7rem'],
                    ['name' => 'pt_um', 'label' => t('production.um'), 'type' => 'static', 'width' => '4rem'],
                    ['name' => 'ps_scrap', 'label' => t('production.scrap_pct'), 'type' => 'number', 'width' => '6rem'],
                    ['name' => 'cost', 'label' => t('production.cost_per_unit'), 'type' => 'money', 'width' => '8rem'],
                ], 'more' => [
                    ['name' => 'ps_start', 'label' => t('production.start'), 'type' => 'date'],
                    ['name' => 'ps_end', 'label' => t('production.end'), 'type' => 'date'],
                    ['name' => 'ps_rmks', 'label' => t('production.remarks'), 'type' => 'text'],
                ]],
                'routing' => $ro + ['columns' => [
                    ['name' => 'ro_op', 'label' => t('production.op'), 'type' => 'number', 'width' => '5rem'],
                    ['name' => 'ro_wkctr', 'label' => t('production.work_center'), 'type' => 'select', 'width' => '13rem', 'options' => Lookup::options('work-centers')],
                    ['name' => 'ro_desc', 'label' => t('production.op_desc'), 'type' => 'text'],
                    ['name' => 'ro_setup_time', 'label' => t('production.setup_hours'), 'type' => 'number', 'width' => '7rem'],
                    ['name' => 'ro_run_time', 'label' => t('production.run_hours'), 'type' => 'number', 'width' => '7rem'],
                    ['name' => 'cost', 'label' => t('production.cost_per_unit'), 'type' => 'money', 'width' => '8rem'],
                ]],
                // cost of an hour at each work center: labour + machine + overhead
                'rates' => array_map('floatval', array_column(
                    App::db()->all('SELECT wc_wkctr, wc_lbr_rate + wc_mch_rate + wc_bdn_rate AS rate FROM wc_mstr'),
                    'rate',
                    'wc_wkctr'
                )),
                'tab' => $request->input('tab') === 'routing' && $ro['view'] ? 'routing' : ($bom['view'] ? 'bom' : 'routing'),
            ],
            'scripts' => ['js/pages/set-editor.js'],
        ]);
    }

    /** Items with materials or steps. ?q= */
    #[Get('/api/production-sets')]
    public function list(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            'SELECT s.parent, pt.pt_desc1 AS descr, SUM(s.b) AS bom_count, SUM(s.r) AS ro_count
               FROM (SELECT ps_par AS parent, 1 AS b, 0 AS r FROM ps_mstr UNION ALL SELECT ro_routing, 0, 1 FROM ro_mstr) s
               LEFT JOIN pt_mstr pt ON pt.pt_part = s.parent
              WHERE s.parent LIKE ? OR pt.pt_desc1 LIKE ?
              GROUP BY s.parent, pt.pt_desc1 ORDER BY s.parent LIMIT 200',
            [$like, $like]
        );
    }

    /** ?part= — unit and current unit cost of a material (pt_cost: the moving average for AVG items). */
    #[Get('/api/production-sets/part')]
    public function part(Request $request): array
    {
        return App::db()->one('SELECT pt_part, pt_desc1, pt_um, pt_cost FROM pt_mstr WHERE pt_part = ?', [(string) $request->input('part', '')])
            ?? throw HttpException::notFound();
    }
}
