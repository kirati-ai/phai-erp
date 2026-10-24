<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;

final class CreditTermResource extends Resource
{
    public const CODE = 'credit-terms';
    protected static string $table = 'ct_mstr';
    protected static array $key = ['ct_code'];
    protected static array $search = ['ct_code', 'ct_desc'];
    protected static array $list = ['ct_code', 'ct_desc', 'ct_net_days', 'ct_active'];
    protected static array $form = [
        'ct_code'      => ['col' => 4, 'upper' => true],
        'ct_desc'      => ['col' => 8],
        'ct_net_days'  => ['col' => 4],
        'ct_disc_days' => ['col' => 4],
        'ct_disc_pct'  => ['col' => 4],
        'ct_active'    => ['col' => 12],
    ];
    protected static array $usedBy = [
        'cm_mstr' => 'cm_terms',
        'vd_mstr' => 'vd_terms',
    ];
}
