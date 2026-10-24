<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Crud\Resource;

final class SubAccountResource extends Resource
{
    public const CODE = 'sub-accounts';
    protected static string $table = 'sub_mstr';
    protected static array $key = ['sub_acct'];
    protected static array $search = ['sub_acct', 'sub_desc'];
    protected static array $list = ['sub_acct', 'sub_desc', 'sub_active'];
    protected static array $form = [
        'sub_acct'   => ['col' => 4, 'upper' => true],
        'sub_desc'   => ['col' => 8],
        'sub_active' => ['col' => 12],
    ];
}
