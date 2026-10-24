<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;

final class PartGroupResource extends Resource
{
    public const CODE = 'part-groups';
    protected static string $table = 'ptg_mstr';
    protected static array $key = ['ptg_group'];
    protected static array $search = ['ptg_group', 'ptg_desc'];
    protected static array $list = ['ptg_group', 'ptg_desc', 'ptg_active'];
    protected static array $form = [
        'ptg_group'  => ['col' => 4, 'upper' => true],
        'ptg_desc'   => ['col' => 8],
        'ptg_active' => ['col' => 12],
    ];
    protected static array $usedBy = [
        'pt_mstr' => 'pt_item_group',
    ];
}
