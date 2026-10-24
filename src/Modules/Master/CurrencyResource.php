<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;

final class CurrencyResource extends Resource
{
    public const CODE = 'currencies';
    protected static string $table = 'cu_mstr';
    protected static array $key = ['cu_curr'];
    protected static array $search = ['cu_curr', 'cu_desc'];
    protected static array $list = ['cu_curr', 'cu_desc', 'cu_symbol', 'cu_active'];
    protected static array $form = [
        'cu_curr'   => ['col' => 4, 'upper' => true],
        'cu_desc'   => ['col' => 8],
        'cu_symbol' => ['col' => 4],
        'cu_active' => ['col' => 8, 'type' => 'bool'],
    ];
    protected static array $usedBy = [
        'cm_mstr' => 'cm_curr',
        'vd_mstr' => 'vd_curr',
        'ex_mstr' => 'ex_curr',
    ];
}
