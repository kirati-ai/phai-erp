<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;

/** Read-only lookups for sales screens. */
#[Program('quotations', 'sales-orders', 'shipments', 'invoices', 'billings', 'cash-receipts', 'customers', 'price-lists', 'parts')]
final class SalesLookupController
{
    /** ?q= — active customers by code or name, with their defaults. */
    #[Get('/api/sales/customers')]
    public function customers(Request $request): array
    {
        $q = trim((string) $request->input('q', ''));
        $like = '%' . addcslashes($q, '%_\\') . '%';
        return App::db()->all(
            "SELECT cm_code, cm_name, cm_terms, cm_curr, cm_tax_code, cm_salesperson FROM cm_mstr
              WHERE cm_status = 'ACTIVE' AND (cm_code LIKE ? OR cm_name LIKE ?)
              ORDER BY cm_code = ? DESC, cm_code LIMIT 20",
            [$like, $like, $q]
        );
    }

    /** ?part=&customer=&qty=&date= — {price, disc, source} */
    #[Get('/api/sales/price')]
    public function price(Request $request): array
    {
        $qty = (float) str_replace(',', '', (string) $request->input('qty', '1'));
        $date = (string) $request->input('date', '');
        return Sales::price(
            (string) $request->input('part', ''),
            (string) $request->input('customer', '') ?: null,
            $qty > 0 ? $qty : 1,
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d')
        );
    }
}
