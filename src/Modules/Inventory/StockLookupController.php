<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;

/** Small read-only lookups for stock screens: part search, locations, what is on hand. */
#[Program('stock', 'stock-moves', 'stock-counts', 'stock-history', 'stock-card', 'receipts', 'shipments', 'work-orders', 'purchase-requisitions', 'purchase-orders', 'quotations', 'sales-orders', 'boms', 'routings', 'mrp')]
final class StockLookupController
{
    public static function sites(): array
    {
        return App::db()->all('SELECT si_site, si_desc, si_default_loc FROM si_mstr WHERE si_status = 1 ORDER BY si_site');
    }

    /** ?q=text — up to 20 parts whose code or name contains it, exact code first; &sale=1 leaves out raw materials. */
    #[Get('/api/stock/parts')]
    public function parts(Request $request): array
    {
        $q = trim((string) $request->input('q', '')); // empty: the first items, for a list just opened
        $like = '%' . addcslashes($q, '%_\\') . '%';
        return App::db()->all(
            "SELECT pt_part, pt_desc1, pt_um, pt_lot_ser FROM pt_mstr
              WHERE pt_status <> 'OBSOLETE' AND (pt_part LIKE ? OR pt_desc1 LIKE ? OR pt_ad_code LIKE ?)"
            . ($request->input('sale') ? " AND NOT EXISTS (SELECT 1 FROM pl_mstr pl WHERE pl.pl_prod_line = pt_prod_line AND pl.pl_inventory_type = 'RM')" : '') . "
              ORDER BY pt_part = ? DESC, pt_part LIMIT 20",
            [$like, $like, $like, $q]
        );
    }

    /** ?site=MAIN */
    #[Get('/api/stock/locations')]
    public function locations(Request $request): array
    {
        return App::db()->all(
            "SELECT loc_loc, loc_desc FROM loc_mstr WHERE loc_site = ? AND loc_status <> 'HOLD' ORDER BY loc_loc",
            [(string) $request->input('site', '')]
        );
    }

    /** ?part=A&site=MAIN — on-hand of a part in one warehouse, by location and lot. */
    #[Get('/api/stock/onhand')]
    public function onhand(Request $request): array
    {
        return App::db()->all(
            "SELECT ld_loc AS loc, ld_lot AS lot, ld_qty_oh AS qty, ld_expire AS expire FROM ld_det
              WHERE ld_part = ? AND ld_site = ? ORDER BY ld_loc, ld_expire IS NULL, ld_expire, ld_lot",
            [(string) $request->input('part', ''), (string) $request->input('site', '')]
        );
    }
}
