<?php
declare(strict_types=1);
namespace Phai\Modules\Accounting;

/** Book depreciation: full calendar month, beginning with the in-service month. */
final class AssetDepreciation
{
    public static function month(string $month): string
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) throw new \InvalidArgumentException(t('assets.err_month'));
        return $month;
    }
    public static function next(array $asset): string
    {
        return empty($asset['fa_last_month']) ? substr($asset['fa_start'], 0, 7)
            : (new \DateTimeImmutable($asset['fa_last_month'] . '-01'))->modify('+1 month')->format('Y-m');
    }
    public static function amount(array $asset, string $month): float
    {
        self::month($month);
        $start = new \DateTimeImmutable(substr($asset['fa_start'], 0, 7) . '-01');
        $date = new \DateTimeImmutable($month . '-01');
        if ($date < $start || $asset['fa_method'] === 'NONE') return 0.0;
        $cost = (float) $asset['fa_cost'];
        $remaining = max(0, round($cost - (float) $asset['fa_salvage'] - (float) $asset['fa_accum'], 2));
        $months = max(1, (int) $asset['fa_life_months']);
        $age = ((int) $date->format('Y') - (int) $start->format('Y')) * 12 + (int) $date->format('m') - (int) $start->format('m') + 1;
        if ($age >= $months) return $remaining; // final rounding/remaining value to salvage
        $value = $asset['fa_method'] === 'DB'
            ? ($cost - (float) $asset['fa_accum']) * (float) $asset['fa_rate'] / 1200
            : ($cost - (float) $asset['fa_salvage']) / $months;
        return min($remaining, max(0, round($value, 2)));
    }
    public static function schedule(array $asset): array
    {
        $rows = [];
        for ($i = 0; $i < 1200 && $asset['fa_method'] !== 'NONE'; $i++) {
            $month = self::next($asset);
            $amount = self::amount($asset, $month);
            if ($amount <= 0) break;
            $asset['fa_accum'] = round((float) $asset['fa_accum'] + $amount, 2);
            $asset['fa_last_month'] = $month;
            $rows[] = ['month' => $month, 'depreciation' => $amount, 'accumulated' => $asset['fa_accum'], 'book_value' => round((float) $asset['fa_cost'] - $asset['fa_accum'], 2)];
        }
        return $rows;
    }
}
