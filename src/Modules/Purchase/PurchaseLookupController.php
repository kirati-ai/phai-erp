<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;

/** Read-only lookups for purchase screens. */
#[Program('purchase-requisitions', 'purchase-orders', 'receipts', 'landed-costs', 'payables', 'ap-vouchers', 'vouchers', 'petty-cash', 'fixed-assets', 'vendors', 'parts')]
final class PurchaseLookupController
{
    /** ?q= — active vendors by code or name, with their defaults. */
    #[Get('/api/purchase/vendors')]
    public function vendors(Request $request): array
    {
        $q = trim((string) $request->input('q', ''));
        $like = '%' . addcslashes($q, '%_\\') . '%';
        return App::db()->all(
            "SELECT vd_code, vd_name, vd_terms, vd_curr, vd_tax_code, vd_ap_acct, vd_wht_code FROM vd_mstr
              WHERE vd_status = 'ACTIVE' AND (vd_code LIKE ? OR vd_name LIKE ?)
              ORDER BY vd_code = ? DESC, vd_code LIMIT 20",
            [$like, $like, $q]
        );
    }

    /** ?part=&vendor= — last price paid to this vendor, else the part's cost. */
    #[Get('/api/purchase/last-price')]
    public function lastPrice(Request $request): array
    {
        $part = (string) $request->input('part', '');
        $price = App::db()->value(
            "SELECT d.pod_price FROM pod_det d JOIN po_mstr p ON p.po_nbr = d.pod_nbr
              WHERE d.pod_part = ? AND p.po_vd_code = ? AND p.po_status <> 'X'
              ORDER BY p.po_date DESC, d.pod_nbr DESC LIMIT 1",
            [$part, (string) $request->input('vendor', '')]
        ) ?? App::db()->value('SELECT pt_cost FROM pt_mstr WHERE pt_part = ?', [$part]);
        return ['price' => $price === null ? null : (float) $price];
    }
}
