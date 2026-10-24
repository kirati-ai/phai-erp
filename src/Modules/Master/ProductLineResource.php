<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\Crud\Resource;

final class ProductLineResource extends Resource
{
    public const CODE = 'product-lines';
    protected static string $table = 'pl_mstr';
    protected static array $key = ['pl_prod_line'];
    protected static array $search = ['pl_prod_line', 'pl_desc'];
    protected static array $list = ['pl_prod_line', 'pl_desc', 'pl_inventory_type', 'pl_fa_category', 'pl_tax_code', 'pl_active'];
    protected static array $form = [
        'pl_prod_line' => ['col' => 4, 'upper' => true],
        'pl_desc'      => ['col' => 8],
        'pl_inventory_type' => ['col' => 6, 'options' => ['FG', 'RM', 'FA']],
        'pl_fa_category' => ['col' => 6, 'lookup' => 'fixed-asset-categories'],
        'pl_tax_code'  => ['col' => 6, 'lookup' => 'taxes'],
        'pl_active'    => ['col' => 6],
        'pl_inv_acct'  => ['section' => 'accounts', 'lookup' => 'accounts'],
        'pl_sls_acct'  => ['section' => 'accounts', 'lookup' => 'accounts'],
        'pl_cogs_acct' => ['section' => 'accounts', 'lookup' => 'accounts'],
        'pl_pur_acct'  => ['section' => 'accounts', 'lookup' => 'accounts'],
        'pl_wip_acct'  => ['section' => 'accounts', 'lookup' => 'accounts'],
    ];
    protected static array $usedBy = [
        'pt_mstr' => 'pt_prod_line',
    ];

    public static function validate(array $data, bool $creating): array
    {
        $row = parent::validate($data, $creating);
        if ($creating && empty($row['pl_inventory_type'])) {
            throw \Phai\Core\Http\HttpException::invalid(['pl_inventory_type' => t('validation.required')]);
        }
        // a fixed asset line needs its asset category; other lines have none
        if (($row['pl_inventory_type'] ?? '') === 'FA' && empty($row['pl_fa_category'])) {
            throw \Phai\Core\Http\HttpException::invalid(['pl_fa_category' => t('validation.required')]);
        }
        if (isset($row['pl_inventory_type']) && $row['pl_inventory_type'] !== 'FA') {
            $row['pl_fa_category'] = null;
        }
        return $row;
    }
}
