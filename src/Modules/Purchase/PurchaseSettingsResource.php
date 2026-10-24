<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\Crud\Resource;

final class PurchaseSettingsResource extends Resource
{
    public const CODE = 'settings-purchase';
    public const PATH = '/settings/purchase';
    protected static string $table = 'po_ctrl';
    protected static array $key = ['ctrl_id'];
    protected static bool $single = true;
    protected static array $form = [
        'po_pr_req'  => ['col' => 12],
        'po_rcv_tol' => ['col' => 4],
    ];
}
