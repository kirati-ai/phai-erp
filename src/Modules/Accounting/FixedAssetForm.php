<?php
declare(strict_types=1);
namespace Phai\Modules\Accounting;
use Phai\Core\Crud\Resource;

/** Field metadata only; lifecycle writes are handled by FixedAsset, not generic CRUD. */
final class FixedAssetForm extends Resource
{
    public const CODE = 'fixed-assets';
    protected static string $table = 'fa_mstr';
    protected static array $key = ['fa_code'];
    protected static array $hidden = ['fa_asset_acct', 'fa_accum_acct', 'fa_expense_acct', 'fa_gainloss_acct'];
    protected static array $form = [
        'fa_code' => ['col' => 4, 'upper' => true], 'fa_desc' => ['col' => 8],
        'fa_category' => ['col' => 4, 'lookup' => 'fixed-asset-categories'],
        'fa_serial' => ['col' => 4], 'fa_source_ref' => ['col' => 4, 'pick' => 'fa-receipts', 'free' => true],
        'fa_site' => ['col' => 4, 'lookup' => 'sites'], 'fa_location' => ['col' => 4], 'fa_cc' => ['col' => 4, 'lookup' => 'cost-centers'],
        'fa_prod_line' => ['col' => 4, 'lookup' => 'product-lines'],
        'fa_custodian' => ['col' => 6, 'pick' => 'people', 'free' => true], 'fa_vendor' => ['col' => 6, 'pick' => 'vendors', 'exists' => ['vd_mstr', 'vd_code']],
        'fa_acquired' => ['col' => 4, 'default' => 'today'], 'fa_start' => ['col' => 4, 'default' => 'today'],
        'fa_method' => ['col' => 4, 'options' => ['SL', 'DB', 'NONE']],
        'fa_cost' => ['col' => 4], 'fa_salvage' => ['col' => 4], 'fa_life_months' => ['col' => 4],
        'fa_rate' => ['col' => 4], 'fa_opening_depr' => ['col' => 4], 'fa_opening_month' => ['col' => 4],
        'fa_notes' => ['col' => 12, 'type' => 'textarea'],
    ];
}
