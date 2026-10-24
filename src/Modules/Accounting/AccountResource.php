<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Crud\Resource;

final class AccountResource extends Resource
{
    public const CODE = 'accounts';
    protected static string $table = 'ac_mstr';
    protected static array $key = ['ac_acct'];
    protected static array $search = ['ac_acct', 'ac_desc', 'ac_desc_th'];
    protected static array $list = ['ac_acct', 'ac_desc_th', 'ac_desc', 'ac_type', 'ac_is_control', 'ac_active'];
    protected static array $form = [
        'ac_acct'       => ['col' => 4, 'upper' => true],
        'ac_type'       => [
            'col'     => 4,
            'options' => ['A', 'L', 'E', 'R', 'X'],
        ],
        'ac_active'     => ['col' => 4],
        'ac_desc_th'    => ['col' => 6],
        'ac_desc'       => ['col' => 6],
        'ac_is_control' => ['col' => 12],
    ];
    protected static array $usedBy = [
        'gl_trhist' => 'gl_acct',
        'bk_mstr'   => 'bk_acct',
        'wht_mstr'  => 'wht_acct',
    ];
    public static function destroy(array $key): void
    {
        $account = $key['ac_acct'];
        $db = \Phai\Core\App::db();
        if ($db->value('SELECT 1 FROM fa_category WHERE fc_asset_acct=? OR fc_accum_acct=? OR fc_expense_acct=? OR fc_gainloss_acct=? LIMIT 1', array_fill(0, 4, $account))
            || $db->value('SELECT 1 FROM fa_mstr WHERE fa_asset_acct=? OR fa_accum_acct=? OR fa_expense_acct=? OR fa_gainloss_acct=? LIMIT 1', array_fill(0, 4, $account))) {
            throw new \Phai\Core\Http\HttpException(409, t('assets.err_account_used'));
        }
        parent::destroy($key);
    }
}
