<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\Crud\Resource;

final class WorkCenterResource extends Resource
{
    public const CODE = 'work-centers';
    protected static string $table = 'wc_mstr';
    protected static array $key = ['wc_wkctr'];
    protected static array $search = ['wc_wkctr', 'wc_desc', 'wc_dept'];
    protected static array $list = ['wc_wkctr', 'wc_desc', 'wc_dept', 'wc_lbr_rate', 'wc_mch_rate', 'wc_active'];
    protected static array $form = [
        'wc_wkctr'    => ['col' => 4, 'upper' => true],
        'wc_desc'     => ['col' => 8],
        'wc_dept'     => ['col' => 6],
        'wc_active'   => ['col' => 6],
        'wc_lbr_rate' => ['col' => 4],
        'wc_mch_rate' => ['col' => 4],
        'wc_bdn_rate' => ['col' => 4],
    ];
    protected static array $usedBy = [
        'ro_mstr'  => 'ro_wkctr',
        'mch_mstr' => 'mch_wkctr',
    ];
}
