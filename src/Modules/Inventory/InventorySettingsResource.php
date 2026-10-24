<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Crud\Resource;

final class InventorySettingsResource extends Resource
{
    public const CODE = 'settings-inventory';
    public const PATH = '/settings/inventory';
    protected static string $table = 'ic_ctrl';
    protected static array $key = ['ctrl_id'];
    protected static bool $single = true;
    protected static array $form = [
        'ic_neg_qty'  => ['col' => 12],
        'ic_auto_loc' => ['col' => 6, 'upper' => true],
    ];
}
