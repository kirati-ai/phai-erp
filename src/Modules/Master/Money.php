<?php

declare(strict_types=1);

namespace Phai\Modules\Master;

use Phai\Core\App;

/** Currency, tax and amount rules shared by purchase and sales documents. Prices exclude VAT. */
final class Money
{
    public static function base(): string
    {
        return (string) (App::db()->value('SELECT co_base_curr FROM co_mstr WHERE co_id = 1') ?: 'THB');
    }

    /** Baht per 1 unit of $curr on $date (latest rate on or before it); 1 for the base currency, null if unknown. */
    public static function rate(string $curr, string $date): ?float
    {
        if ($curr === self::base()) {
            return 1.0;
        }
        $rate = App::db()->value(
            'SELECT ex_rate FROM ex_mstr WHERE ex_curr = ? AND ex_date <= ? ORDER BY ex_date DESC LIMIT 1',
            [$curr, $date]
        );
        return $rate === null ? null : (float) $rate;
    }

    public static function taxPct(?string $code): float
    {
        return $code ? (float) App::db()->value('SELECT tx_tax_pct FROM tx_mstr WHERE tx_tax_code = ?', [$code]) : 0.0;
    }

    /** qty x price less discount, rounded to satang. */
    public static function lineAmount(float $qty, float $price, float $discPct = 0.0): float
    {
        return round($qty * $price * (1 - $discPct / 100), 2);
    }

    /** @return array{net: float, tax: float, total: float} */
    public static function totals(array $lineAmounts, float $taxPct): array
    {
        $net = round(array_sum($lineAmounts), 2);
        $tax = round($net * $taxPct / 100, 2);
        return ['net' => $net, 'tax' => $tax, 'total' => round($net + $tax, 2)];
    }

    /** Due date from credit terms (ct_net_days). */
    public static function dueDate(string $date, ?string $terms): string
    {
        $days = $terms ? (int) App::db()->value('SELECT ct_net_days FROM ct_mstr WHERE ct_code = ?', [$terms]) : 0;
        return date('Y-m-d', strtotime("$date +$days days"));
    }

    /** Active tax codes with their rate, for dropdowns that compute totals in the browser. */
    public static function taxOptions(): array
    {
        return array_map(
            fn ($r) => ['value' => $r['tx_tax_code'], 'label' => $r['tx_tax_code'], 'pct' => (float) $r['tx_tax_pct']],
            App::db()->all('SELECT tx_tax_code, tx_tax_pct FROM tx_mstr WHERE tx_active = 1 ORDER BY tx_tax_code')
        );
    }
}
