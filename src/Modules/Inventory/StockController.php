<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\Master\PartResource;

/** On-hand by warehouse, location and lot — or totals per part. */
#[Program('stock')]
final class StockController
{
    /** ?type=FG (sales) or ?type=RM (purchasing, production) opens the page already filtered. */
    #[Get('/stock')]
    public function page(Request $request): string
    {
        $type = (string) $request->input('type', '');
        return View::render('inventory/stock', [
            'title'   => t(match ($type) { 'FG' => 'stock.fg_title', 'RM' => 'stock.rm_title', default => 'stock.balance_title' }),
            'sites'   => StockLookupController::sites(),
            'filters' => PartResource::filters(),
            'chosen'  => $request->query,
            'types'   => array_combine(Stock::TYPES, array_map(fn ($t) => t('stock.type_' . $t), Stock::TYPES)),
            'help'    => 'stock',
            'scripts' => ['js/stock-history-grid.js', 'js/pages/stock.js'],
        ]);
    }

    /** One stock line (ld_det row): where, how much, its value and the movements of that place and lot. */
    #[Get('/stock/view')]
    public function view(Request $request): string
    {
        $row = self::row((int) $request->input('id', 0));
        $row['isRm'] = Stock::isRawMaterial($row['ld_part']);
        $row['lotted'] = Stock::needsLot(Stock::part($row['ld_part']));
        $types = [];
        foreach (Stock::TYPES as $t) $types[$t] = t('stock.type_' . $t);
        return View::render('inventory/view', [
            'title'   => $row['ld_part'] . ' · ' . $row['pt_desc1'],
            'row'     => $row,
            'types'   => $types,
            'sites'   => StockLookupController::sites(),
            'help'    => 'stock-view',
            'scripts' => ['js/stock-history-grid.js', 'js/pages/stock-view.js'],
        ]);
    }

    #[Describe('One stock line by id: site, location, part, lot, on hand, allocated, unit cost and value')]
    #[Get('/api/stock/view/{id}')]
    public function show(int $id): array
    {
        $row = self::row($id);
        return $row + ['fields' => self::fields($row), 'status_text' => t('stock.ld_status_' . ($row['ld_status'] ?: 'ACTIVE')),
            'status_color' => self::statusColor($row['ld_status'])];
    }

    /** What a stock line shows, in order: [label, value, column classes, numeric] — the view page and the modal both use it. */
    public static function fields(array $row): array
    {
        $num = fn ($v) => number_format((float) $v, 2);
        $qty = fn ($v) => Stock::fmt((float) $v) . ' ' . $row['pt_um'];
        $date = fn ($v) => $v ? date('d/m/Y', strtotime($v)) : '';
        $stamp = fn ($by, $at) => trim(($by ?? '') . ($at ? ' · ' . date('d/m/Y H:i', strtotime($at)) : ''));
        return [
            [t('stock.part'), $row['ld_part'] . ' · ' . $row['pt_desc1'], 'col-12 col-md-6'],
            [t('stock.site'), $row['ld_site'] . ($row['si_desc'] ? ' · ' . $row['si_desc'] : ''), 'col-6 col-md-3'],
            [t('stock.loc'), $row['ld_loc'] . ($row['loc_desc'] ? ' · ' . $row['loc_desc'] : ''), 'col-6 col-md-3'],
            [t('stock.lot'), $row['ld_lot'], 'col-6 col-md-2'],
            [t('stock.vend_lot'), $row['lot_vend_lot'], 'col-6 col-md-2'],
            [t('stock.ref'), $row['ld_ref'], 'col-6 col-md-2'],
            [t('stock.pack_qty'), $qty($row['ld_pack_qty']), 'col-6 col-md-2', true],
            [t('stock.grade'), $row['ld_grade'], 'col-6 col-md-2'],
            [t('stock.mfg_date'), $date($row['ld_mfg_date']), 'col-6 col-md-2'],
            [t('stock.expire'), $date($row['ld_expire']), 'col-6 col-md-2'],
            [t('stock.onhand'), $qty($row['ld_qty_oh']), 'col-6 col-md-2', true],
            [t('stock.allocated'), $qty($row['ld_qty_all']), 'col-6 col-md-2', true],
            [t('stock.unit_cost'), $num($row['cost']), 'col-6 col-md-2', true],
            [t('stock.value'), $num($row['value']), 'col-6 col-md-2', true],
            [t('stock.rcv_cost'), $num($row['ld_cost']), 'col-6 col-md-2', true],
            [t('stock.lot_rmks'), $row['lot_rmks'], 'col-12 col-md-6'],
            [t('stock.created'), $stamp($row['created_by'], $row['created_at']), 'col-6 col-md-3'],
            [t('stock.updated'), $stamp($row['updated_by'], $row['updated_at']), 'col-6 col-md-3'],
        ];
    }

    public static function statusColor(?string $status): string
    {
        return ['ACTIVE' => 'green', 'HOLD' => 'yellow', 'EXPIRED' => 'red', 'QUARANTINE' => 'orange'][$status ?: 'ACTIVE'] ?? 'secondary';
    }

    public static function row(int $id): array
    {
        $r = App::db()->one(
            "SELECT ld.ld_id, ld.ld_site, si.si_desc, ld.ld_loc, lc.loc_desc, ld.ld_part, pt.pt_desc1, pt.pt_um, ld.ld_lot, lt.lot_vend_lot, lt.lot_rmks,
                    ld.ld_ref, ld.ld_qty_oh, ld.ld_qty_all, ld.ld_pack_qty, ld.ld_status, ld.ld_grade, ld.ld_expire, ld.ld_mfg_date, ld.ld_cost,
                    ld.created_by, ld.created_at, ld.updated_by, ld.updated_at,
                    IF(pt.pt_cost_method = 'AVG', pt.pt_cost, ld.ld_cost) AS cost, COALESCE(pl.pl_inventory_type, 'FG') AS type
               FROM ld_det ld
               JOIN pt_mstr pt ON pt.pt_part = ld.ld_part
               LEFT JOIN pl_mstr pl ON pl.pl_prod_line = pt.pt_prod_line
               LEFT JOIN si_mstr si ON si.si_site = ld.ld_site
               LEFT JOIN loc_mstr lc ON lc.loc_site = ld.ld_site AND lc.loc_loc = ld.ld_loc
               LEFT JOIN lot_mstr lt ON lt.lot_part = ld.ld_part AND lt.lot_lot = ld.ld_lot
              WHERE ld.ld_id = ?",
            [$id]
        ) ?? throw \Phai\Core\Http\HttpException::notFound();
        $r['value'] = round((float) $r['ld_qty_oh'] * (float) $r['cost'], 2);
        return $r;
    }

    /** ?part= (exact) &q= &site= &type=FG|RM &pl= (product line) &by=part (totals per part and warehouse) &sort= &dir= &page= */
    #[Describe('Stock on hand by site, location and lot', ['q' => 'part, description, lot or location', 'site' => 'site code', 'type' => 'FG = finished goods, RM = raw materials (from the product line)', 'pl' => 'product line code', 'by' => '"part" = one total per part and site'])]
    #[Get('/api/stock')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($site = (string) $request->input('site', '')) !== '') {
            $where[] = 'ld.ld_site = ?';
            $params[] = $site;
        }
        if (($part = (string) $request->input('part', '')) !== '') {
            $where[] = 'ld.ld_part = ?';
            $params[] = $part;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(ld.ld_part LIKE ? OR pt.pt_desc1 LIKE ? OR ld.ld_lot LIKE ? OR ld.ld_loc LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (($type = strtoupper((string) $request->input('type', ''))) !== '') {
            $where[] = "COALESCE(pl.pl_inventory_type, 'FG') = ?";
            $params[] = $type;
        }
        if (($line = (string) $request->input('pl', '')) !== '') {
            $where[] = 'pt.pt_prod_line = ?';
            $params[] = $line;
        }
        $from = 'FROM ld_det ld JOIN pt_mstr pt ON pt.pt_part = ld.ld_part
                 LEFT JOIN pl_mstr pl ON pl.pl_prod_line = pt.pt_prod_line WHERE ' . implode(' AND ', $where);

        if ($request->input('by') === 'part') {
            return App::db()->page(
                "SELECT NULL AS ld_id, ld.ld_site, '' AS ld_loc, ld.ld_part, pt.pt_desc1, '' AS ld_lot, NULL AS ld_expire,
                        SUM(ld.ld_qty_oh) AS ld_qty_oh, pt.pt_um",
                $from,
                $params,
                $request->query,
                ['ld_part' => 'ld.ld_part', 'pt_desc1' => 'pt.pt_desc1', 'ld_site' => 'ld.ld_site', 'ld_qty_oh' => 'SUM(ld.ld_qty_oh)'],
                'ld_part',
                'ld.ld_site, ld.ld_part, pt.pt_desc1, pt.pt_um'
            );
        }

        return App::db()->page(
            'SELECT ld.ld_id, ld.ld_site, ld.ld_loc, ld.ld_part, pt.pt_desc1, ld.ld_lot, ld.ld_expire, ld.ld_qty_oh, pt.pt_um',
            $from,
            $params,
            $request->query,
            [
                'ld_part' => 'ld.ld_part', 'pt_desc1' => 'pt.pt_desc1', 'ld_site' => 'ld.ld_site', 'ld_loc' => 'ld.ld_loc',
                'ld_lot' => 'ld.ld_lot', 'ld_expire' => 'ld.ld_expire', 'ld_qty_oh' => 'ld.ld_qty_oh',
            ],
            'ld_part'
        );
    }
}
