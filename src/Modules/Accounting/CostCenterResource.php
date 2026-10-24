<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Crud\Resource;

final class CostCenterResource extends Resource
{
    public const CODE = 'cost-centers';
    protected static string $table = 'cc_mstr';
    protected static array $key = ['cc_cc'];
    protected static array $search = ['cc_cc', 'cc_desc'];
    protected static array $list = ['cc_cc', 'cc_desc', 'cc_active'];
    protected static array $form = [
        'cc_cc'     => ['col' => 4, 'upper' => true],
        'cc_desc'   => ['col' => 8],
        'cc_active' => ['col' => 12],
    ];
    protected static array $usedBy = [
        'fa_mstr' => 'fa_cc',
        'gl_trhist' => 'gl_cc',
    ];
}
