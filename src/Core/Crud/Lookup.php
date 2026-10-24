<?php

declare(strict_types=1);

namespace Phai\Core\Crud;

use Phai\Core\App;
use Phai\Core\Db\Db;

/**
 * Dropdown sources. A resource field with 'lookup' => 'uoms' gets its
 * options from here, and saved values must be one of them.
 */
final class Lookup
{
    /** name => [table, value column, label column, active column or null] */
    private const SOURCES = [
        'fixed-asset-categories' => ['fa_category', 'fc_code', 'fc_desc', 'fc_active'],
        'fixed-assets'      => ['fa_mstr', 'fa_code', 'fa_desc', null],
        'uoms'              => ['um_mstr', 'um_um', 'um_desc', 'um_active'],
        'part-groups'       => ['ptg_mstr', 'ptg_group', 'ptg_desc', 'ptg_active'],
        'part-types'        => ['ptt_mstr', 'ptt_type', 'ptt_desc', 'ptt_active'],
        'product-lines'     => ['pl_mstr', 'pl_prod_line', 'pl_desc', 'pl_active'],
        'currencies'        => ['cu_mstr', 'cu_curr', 'cu_desc', 'cu_active'],
        'taxes'             => ['tx_mstr', 'tx_tax_code', 'tx_desc', 'tx_active'],
        'credit-terms'      => ['ct_mstr', 'ct_code', 'ct_desc', 'ct_active'],
        'sites'             => ['si_mstr', 'si_site', 'si_desc', 'si_status'],
        'accounts'          => ['ac_mstr', 'ac_acct', 'ac_desc_th', 'ac_active'],
        'inventory-statuses' => ['is_mstr', 'is_status', 'is_desc', null],
        'work-centers'      => ['wc_mstr', 'wc_wkctr', 'wc_desc', 'wc_active'],
        'employees'         => ['emp_mstr', 'emp_nbr', 'emp_name', 'emp_active'],
        'users'             => ['us_mstr', 'us_userid', 'us_name', 'us_active'],
        'banks'             => ['bk_mstr', 'bk_bank', 'bk_desc', 'bk_active'],
        'wht-codes'         => ['wht_mstr', 'wht_code', 'wht_desc', 'wht_active'],
        'cost-centers'      => ['cc_mstr', 'cc_cc', 'cc_desc', 'cc_active'],
    ];

    private static array $cache = [];

    /**
     * Dropdown shows active rows only; validation passes $activeOnly = false
     * so a record that points to a since-disabled value can still be saved.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(string $name, bool $activeOnly = true): array
    {
        $id = $name . ($activeOnly ? ':active' : ':all') . ':' . \Phai\Core\Lang::locale();
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }
        [$table, $value, $label, $active] = self::SOURCES[$name] ?? throw new \InvalidArgumentException("Unknown lookup $name");
        if ($label === 'ac_desc_th' && \Phai\Core\Lang::locale() !== 'th') {
            $label = 'ac_desc'; // accounts have an English name too
        }
        $sql = sprintf(
            'SELECT %1$s AS value, CONCAT(%1$s, \' · \', COALESCE(%2$s, \'\')) AS label FROM %3$s%4$s ORDER BY %1$s',
            Db::quote($value),
            Db::quote($label),
            Db::quote($table),
            $active && $activeOnly ? ' WHERE ' . Db::quote($active) . ' = 1' : ''
        );
        return self::$cache[$id] = App::db()->all($sql);
    }
}
