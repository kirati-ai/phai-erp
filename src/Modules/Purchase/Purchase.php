<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\App;

/** Purchase settings. Money rules live in Master\Money. */
final class Purchase
{
    /** po_ctrl: po_pr_req (PO only from a PR), po_rcv_tol (% over-receipt allowed). */
    public static function settings(): array
    {
        return App::db()->one('SELECT * FROM po_ctrl WHERE ctrl_id = 1') ?? [];
    }
}
