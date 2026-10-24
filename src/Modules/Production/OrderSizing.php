<?php
declare(strict_types=1);
namespace Phai\Modules\Production;

final class OrderSizing
{
    /** Split a net requirement into valid minimum/maximum/multiple lots. */
    public static function lots(float $required, array $item): array
    {
        if ($required <= 0) return [];
        // the order multiple when one is set (above 1), else the pack size (pt_pack_qty)
        $mult = (float) ($item['pt_mult_ord'] ?? 0);
        $step = max(0.00001, $mult > 1 ? $mult : (float) ($item['pt_pack_qty'] ?? 1));
        $min = max(1, (int) ceil(round((float) ($item['pt_min_ord'] ?? 0) / $step, 6)));
        $maxQty = (float) ($item['pt_max_ord'] ?? 0);
        $max = $maxQty > 0 ? (int) floor(round($maxQty / $step, 6)) : PHP_INT_MAX;
        if ($max < $min) throw new \InvalidArgumentException('Invalid order limits');
        $units = max($min, (int) ceil(round($required / $step, 6)));
        $count = (int) ceil($units / $max);
        if ($count > 10000) throw new \InvalidArgumentException('Too many planned lots');
        $units = max($units, $count * $min);
        $base = intdiv($units, $count);
        $extra = $units % $count;
        $lots = [];
        for ($i = 0; $i < $count; $i++) $lots[] = round(($base + ($i < $extra ? 1 : 0)) * $step, 5);
        return $lots;
    }
}
