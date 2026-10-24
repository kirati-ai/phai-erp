<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Crud\Resource;

final class WhtCodeResource extends Resource
{
    public const CODE = 'wht-codes';
    protected static string $table = 'wht_mstr';
    protected static array $key = ['wht_code'];
    protected static array $search = ['wht_code', 'wht_desc'];
    protected static array $list = ['wht_code', 'wht_desc', 'wht_pct', 'wht_acct', 'wht_form_row', 'wht_active'];
    protected static array $form = [
        'wht_code'   => ['col' => 4, 'upper' => true],
        'wht_desc'   => ['col' => 8],
        'wht_pct'    => ['col' => 4],
        'wht_acct'   => ['col' => 8, 'lookup' => 'accounts', 'required' => true], // the payment credits it: owed to the Revenue Department
        // the income row the amount goes on in the 50 ทวิ form
        'wht_form_row' => ['col' => 12, 'options' => ['1', '2', '3', '4a', '4b', '5', '6']],
        'wht_active' => ['col' => 12],
    ];
    protected static array $usedBy = [
        'vo_mstr' => 'vo_wht_code',
    ];
}
