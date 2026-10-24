<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\Crud\Resource;

final class GlSettingsResource extends Resource
{
    public const CODE = 'settings-gl';
    public const PATH = '/settings/gl';
    protected static string $table = 'gl_ctrl';
    protected static array $key = ['ctrl_id'];
    protected static bool $single = true;
    protected static array $form = [
        'gl_curr_year'     => ['col' => 3, 'readonly' => true, 'section' => 'general'],
        'gl_curr_per'      => ['col' => 3, 'readonly' => true, 'section' => 'general'],
        'gl_base_curr'     => ['col' => 6, 'lookup' => 'currencies', 'section' => 'general'],
        'gl_ret_earn_acct' => ['col' => 6, 'lookup' => 'accounts', 'section' => 'general'],
        'gl_ytd_inc_acct'  => ['col' => 6, 'lookup' => 'accounts', 'section' => 'general'],
        'gl_ar_acct'       => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_ap_acct'       => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_sales_acct'    => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_cogs_acct'     => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_inv_acct'      => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_wip_acct'      => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_vat_out_acct'  => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_vat_in_acct'   => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_vat_pay_acct'  => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_vat_cr_acct'   => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_vat_defer_acct' => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_dep_acct'      => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_grni_acct'     => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_labor_acct'    => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_ovh_acct'      => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_adj_acct'      => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_exp_acct'      => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_vo_acct'       => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_wht_ar_acct'   => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_pc_acct'       => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_cash_diff_acct' => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_pc_exp_acct'   => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
        'gl_wo_var_acct'   => ['col' => 6, 'lookup' => 'accounts', 'section' => 'accounts'],
    ];
}
