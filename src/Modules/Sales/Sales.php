<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\App;
use Phai\Modules\Master\Money;

/** Sales settings, selling prices and customer credit. */
final class Sales
{
    /** Price list every customer gets (promotions). */
    public const ALL = 'ALL';

    /** so_ctrl: so_cr_chk (check credit limit). */
    public static function settings(): array
    {
        return App::db()->one('SELECT * FROM so_ctrl WHERE ctrl_id = 1') ?? [];
    }

    public static function customer(string $code): ?array
    {
        return App::db()->one('SELECT * FROM cm_mstr WHERE cm_code = ?', [$code]);
    }

    /**
     * Selling price of $qty x $part for $customer on $date, in the customer's currency.
     * Looks at the customer's price list, then list ALL; the best matching row is the
     * one with the highest minimum quantity not above $qty. P rows give a fixed price,
     * D rows a discount off the item price. Otherwise the item price (pt_price, in baht).
     *
     * @return array{price: float, disc: float, source: string}
     */
    public static function price(string $part, ?string $customer, float $qty, string $date): array
    {
        $db = App::db();
        $base = (float) $db->value('SELECT pt_price FROM pt_mstr WHERE pt_part = ?', [$part]);
        $cust = $customer ? self::customer($customer) : null;
        $curr = $cust['cm_curr'] ?? Money::base();
        $rate = Money::rate($curr, $date) ?: 1.0;
        $itemPrice = round($base / $rate, 5);

        foreach (array_filter([$cust['cm_pr_list'] ?? null, self::ALL]) as $list) {
            $row = $db->one(
                'SELECT * FROM pc_mstr
                  WHERE pc_list = ? AND pc_part = ? AND pc_curr = ? AND pc_start <= ?
                    AND (pc_expire IS NULL OR pc_expire >= ?) AND pc_min_qty <= ?
                  ORDER BY pc_min_qty DESC, pc_start DESC LIMIT 1',
                [$list, $part, $curr, $date, $date, max($qty, 0)]
            );
            if ($row) {
                return $row['pc_amt_type'] === 'D'
                    ? ['price' => $itemPrice, 'disc' => (float) $row['pc_disc_pct'], 'source' => $list]
                    : ['price' => (float) $row['pc_price'], 'disc' => 0.0, 'source' => $list];
            }
        }
        return ['price' => $itemPrice, 'disc' => 0.0, 'source' => 'item'];
    }

    /**
     * What the customer owes or has on order, in baht: unpaid AR plus everything
     * ordered but not yet invoiced (with VAT). $exceptSo leaves one order out.
     */
    public static function exposure(string $customer, string $exceptSo = ''): float
    {
        $db = App::db();
        $ar = (float) $db->value(
            "SELECT COALESCE(SUM(ar_amt - ar_paid_amt), 0) FROM ar_mstr WHERE ar_cm_code = ? AND ar_status = 'O'",
            [$customer]
        );
        $orders = (float) $db->value(
            "SELECT COALESCE(SUM(GREATEST(0, d.sod_qty_ord - COALESCE((
                        SELECT SUM(i.ihd_qty_inv) FROM ihd_det i JOIN ih_mstr h ON h.ih_inv_nbr = i.ihd_inv_nbr
                         WHERE h.ih_status <> 'C' AND i.ihd_so_nbr = d.sod_nbr AND i.ihd_sod_line = d.sod_line), 0))
                    * d.sod_price * (1 - d.sod_disc_pct / 100) * (1 + s.so_tax_pct / 100) * s.so_ex_rate), 0)
               FROM sod_det d JOIN so_mstr s ON s.so_nbr = d.sod_nbr
              WHERE s.so_cm_code = ? AND s.so_status IN ('O', 'C') AND s.so_nbr <> ? AND d.sod_status <> 'X'",
            [$customer, $exceptSo]
        );
        return round($ar + $orders, 2);
    }

    /** Null when within the limit (or no limit), else the limit and what would be used. */
    public static function creditProblem(string $customer, float $orderBaht, string $exceptSo = ''): ?array
    {
        $limit = (float) (self::customer($customer)['cm_cr_limit'] ?? 0);
        if ($limit <= 0) {
            return null;
        }
        $used = self::exposure($customer, $exceptSo) + $orderBaht;
        return $used > $limit ? ['limit' => $limit, 'used' => $used] : null;
    }

    /** One-line address of a customer for printing. */
    public static function address(?array $c): string
    {
        if (!$c) {
            return '';
        }
        return trim(implode(' ', array_filter([
            $c['cm_line1'], $c['cm_line2'], $c['cm_sub_district'], $c['cm_district'], $c['cm_province'], $c['cm_zip'],
        ])));
    }

    /** "สำนักงานใหญ่" or "สาขาที่ 00012" for tax documents. */
    public static function branch(?string $code): string
    {
        $code = trim((string) $code);
        return $code === '' || $code === '00000' ? t('sales.head_office') : t('sales.branch_no', ['no' => $code]);
    }
}
