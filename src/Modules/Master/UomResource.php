<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;

final class UomResource extends Resource
{
    public const CODE = 'uoms';
    protected static string $table = 'um_mstr';
    protected static array $key = ['um_um'];
    protected static array $search = ['um_um', 'um_desc'];
    protected static array $list = ['um_um', 'um_desc', 'um_active'];
    protected static array $form = [
        'um_um'     => ['col' => 4, 'upper' => true],
        'um_desc'   => ['col' => 8],
        'um_active' => ['col' => 12],
    ];
    protected static array $usedBy = [
        'pt_mstr' => 'pt_um',
    ];
}
