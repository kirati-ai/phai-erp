<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Crud\Resource;

final class LocationResource extends Resource
{
    public const CODE = 'locations';
    protected static string $table = 'loc_mstr';
    protected static array $key = ['loc_site', 'loc_loc'];
    protected static array $search = ['loc_site', 'loc_loc', 'loc_desc'];
    protected static array $list = ['loc_site', 'loc_loc', 'loc_desc', 'loc_type', 'loc_status'];
    protected static array $form = [
        'loc_site'     => ['col' => 4, 'lookup' => 'sites'],
        'loc_loc'      => ['col' => 4, 'upper' => true],
        'loc_type'     => [
            'col'     => 4,
            'options' => ['M', 'S'],
        ],
        'loc_desc'     => ['col' => 12],
        'loc_status'   => [
            'col'     => 4,
            'options' => ['ACTIVE', 'HOLD', 'RESTRICT'],
        ],
        'loc_project'  => ['col' => 8],
        'loc_nettable' => ['col' => 6],
        'loc_mrp'      => ['col' => 6],
    ];
}
