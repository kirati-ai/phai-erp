<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Crud\Resource;

final class ReasonCodeResource extends Resource
{
    public const CODE = 'reason-codes';
    protected static string $table = 'rs_mstr';
    protected static array $key = ['rs_code'];
    protected static array $search = ['rs_code', 'rs_desc'];
    protected static array $list = ['rs_code', 'rs_desc', 'rs_type', 'rs_acct', 'rs_active'];
    protected static array $form = [
        'rs_code'   => ['col' => 4, 'upper' => true],
        'rs_desc'   => ['col' => 8],
        'rs_type'   => [
            'col'     => 4,
            'options' => ['ISS', 'RCT'],
        ],
        'rs_acct'   => ['col' => 8, 'lookup' => 'accounts'],
        'rs_cc'     => ['col' => 4],
        'rs_active' => ['col' => 8],
    ];
}
