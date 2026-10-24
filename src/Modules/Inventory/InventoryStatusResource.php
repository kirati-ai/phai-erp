<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Crud\Resource;

final class InventoryStatusResource extends Resource
{
    public const CODE = 'inventory-statuses';
    protected static string $table = 'is_mstr';
    protected static array $key = ['is_status'];
    protected static array $search = ['is_status', 'is_desc'];
    protected static array $list = ['is_status', 'is_desc', 'is_avail', 'is_nettable'];
    protected static array $form = [
        'is_status'    => ['col' => 4, 'upper' => true],
        'is_desc'      => ['col' => 8],
        'is_avail'     => ['col' => 4],
        'is_nettable'  => ['col' => 4],
        'is_overissue' => ['col' => 4],
    ];
}
