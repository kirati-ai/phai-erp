<?php
declare(strict_types=1);
namespace Phai\Modules\Accounting;
use Phai\Core\Crud\Resource;
use Phai\Core\Http\HttpException;

final class AssetCategoryResource extends Resource
{
    public const CODE = 'fixed-asset-categories';
    protected static string $table = 'fa_category';
    protected static array $key = ['fc_code'];
    protected static array $list = ['fc_code', 'fc_desc', 'fc_method', 'fc_life_months', 'fc_active'];
    protected static array $form = [
        'fc_code' => ['col' => 4, 'upper' => true], 'fc_desc' => ['col' => 8],
        'fc_method' => ['options' => ['SL', 'DB', 'NONE'], 'col' => 4],
        'fc_life_months' => ['col' => 4], 'fc_rate' => ['col' => 4], 'fc_active' => [],
        'fc_asset_acct' => ['section' => 'accounts', 'lookup' => 'accounts'],
        'fc_accum_acct' => ['section' => 'accounts', 'lookup' => 'accounts'],
        'fc_expense_acct' => ['section' => 'accounts', 'lookup' => 'accounts'],
        'fc_gainloss_acct' => ['section' => 'accounts', 'lookup' => 'accounts'],
    ];
    protected static array $usedBy = ['fa_mstr' => 'fa_category'];
    public static function validate(array $data, bool $creating): array
    {
        $row = parent::validate($data, $creating);
        $method = $row['fc_method'] ?? 'SL';
        if (($method !== 'NONE' && ($row['fc_life_months'] ?? 0) < 1) || ($row['fc_life_months'] ?? 0) > 1200 || ($row['fc_rate'] ?? 0) < 0 || ($row['fc_rate'] ?? 0) > 100 || ($method === 'DB' && ($row['fc_rate'] ?? 0) <= 0)) {
            throw new HttpException(422, t('assets.err_cat_rates'));
        }
        $accounts = [];
        foreach (['asset', 'accum', 'expense', 'gainloss'] as $kind) {
            $account = $row['fc_' . $kind . '_acct'] ?? '';
            if ($account && !\Phai\Core\App::db()->value('SELECT 1 FROM ac_mstr WHERE ac_acct=? AND ac_active=1 AND ac_is_control=0', [$account])) throw new HttpException(422, t('assets.err_cat_account'));
            if ($account) $accounts[] = $account;
        }
        if (count(array_unique($accounts)) !== count($accounts)) throw new HttpException(422, t('assets.err_cat_same'));
        return $row;
    }
}
