<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;

final class TaxResource extends Resource
{
    public const CODE = 'taxes';
    protected static string $table = 'tx_mstr';
    protected static array $key = ['tx_tax_code'];
    protected static array $search = ['tx_tax_code', 'tx_desc'];
    protected static array $list = ['tx_tax_code', 'tx_desc', 'tx_tax_pct', 'tx_active'];
    protected static array $form = [
        'tx_tax_code' => ['col' => 4, 'upper' => true],
        'tx_desc'     => ['col' => 8],
        'tx_tax_pct'  => ['col' => 4],
        'tx_tax_acct' => ['col' => 8, 'lookup' => 'accounts'],
        'tx_active'   => ['col' => 12],
    ];
    protected static array $usedBy = [
        'cm_mstr' => 'cm_tax_code',
        'vd_mstr' => 'vd_tax_code',
        'pl_mstr' => 'pl_tax_code',
    ];
}
