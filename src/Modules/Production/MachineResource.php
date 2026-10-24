<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\Crud\Resource;

final class MachineResource extends Resource
{
    public const CODE = 'machines';
    protected static string $table = 'mch_mstr';
    protected static array $key = ['mch_code'];
    protected static array $search = ['mch_code', 'mch_desc', 'mch_serial'];
    protected static array $list = ['mch_code', 'mch_desc', 'mch_wkctr', 'mch_asset', 'mch_brand', 'mch_active'];
    protected static array $form = [
        // Resource narrows each width by one: these fill the rows as 4+8, 6+6, 4+4+4, 12
        'mch_code'   => ['col' => 5, 'upper' => true],
        'mch_desc'   => ['col' => 9],
        'mch_wkctr'  => ['col' => 7, 'lookup' => 'work-centers'],
        'mch_asset'  => ['col' => 7, 'lookup' => 'fixed-assets'],
        'mch_brand'  => ['col' => 5],
        'mch_model'  => ['col' => 5],
        'mch_serial' => ['col' => 5],
        'mch_active' => ['col' => 13],
    ];
}
