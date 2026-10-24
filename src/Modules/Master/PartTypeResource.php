<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;

final class PartTypeResource extends Resource
{
    public const CODE = 'part-types';
    protected static string $table = 'ptt_mstr';
    protected static array $key = ['ptt_type'];
    protected static array $search = ['ptt_type', 'ptt_desc'];
    protected static array $list = ['ptt_type', 'ptt_desc', 'ptt_active'];
    protected static array $form = [
        'ptt_type'   => ['col' => 4, 'upper' => true],
        'ptt_desc'   => ['col' => 8],
        'ptt_active' => ['col' => 12],
    ];
    protected static array $usedBy = [
        'pt_mstr' => 'pt_item_type',
    ];
}
