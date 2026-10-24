<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\Validation\Check;
use Phai\Modules\Inventory\Stock;
use Phai\Modules\Master\Money;

/**
 * Lines of quotations (qtd_) and sales orders (sod_): part, desc, qty, price, discount.
 * $p is the column prefix, $qty the quantity column (qtd_qty / sod_qty_ord).
 */
final class SalesLines
{
    /** $negative: a credit note (ใบลดหนี้) — its quantities are keyed below zero, so its amounts are negative everywhere. */
    public static function check(Check $c, mixed $raw, string $p, string $qty, bool $reqDate = false, bool $negative = false): array
    {
        $lines = [];
        foreach (is_array($raw) ? $raw : [] as $i => $l) {
            if (!is_array($l) || (trim((string) ($l["{$p}_part"] ?? '')) === '' && trim((string) ($l[$qty] ?? '')) === '')) {
                continue;
            }
            $f = fn (string $n) => "lines.$i.$n";
            // a part from pt_mstr, or free text (a service, a one-off item) with its own description and unit
            [$part, $desc, $um] = Stock::lineItem($c, $f("{$p}_part"), $l["{$p}_part"] ?? '', $f("{$p}_desc"), $l["{$p}_desc"] ?? '', $f("{$p}_um"), $l["{$p}_um"] ?? '');
            // raw materials are not sold as they are: convert them into a finished good first (ISS-CO / RCT-CO)
            if ($part !== '' && Stock::isRawMaterial($part)) {
                $c->fail($f("{$p}_part"), t('sales.no_raw_material'));
            }
            $line = [
                "{$p}_part"     => $part,
                "{$p}_desc"     => $desc,
                "{$p}_um"       => $um,
                $qty            => $negative ? $c->number($f($qty), $l[$qty] ?? '') : $c->number($f($qty), $l[$qty] ?? '', true, 0, true),
                "{$p}_price"    => $c->number($f("{$p}_price"), $l["{$p}_price"] ?? '', true, 0),
                "{$p}_disc_pct" => $c->number($f("{$p}_disc_pct"), $l["{$p}_disc_pct"] ?? '', false, 0) ?? 0.0,
            ];
            if ($negative && $line[$qty] !== null && $line[$qty] >= 0) {
                $c->fail($f($qty), t('sales.credit_qty_negative'));
            }
            if ($line["{$p}_disc_pct"] > 100) {
                $c->fail($f("{$p}_disc_pct"), t('validation.max', ['max' => 100]));
            }
            if ($reqDate) {
                $line["{$p}_req_date"] = $c->date($f("{$p}_req_date"), $l["{$p}_req_date"] ?? '');
            }
            $lines[$i] = $line;
        }
        if (!$lines) {
            $c->fail('lines', t('stock.no_lines'));
        }
        return $lines;
    }

    /** Numbered rows ready to insert, with unit, description and line amount. */
    public static function rows(array $lines, string $p, string $qty): array
    {
        $rows = [];
        $n = 0;
        foreach ($lines as $l) {
            $part = Stock::part($l["{$p}_part"]);
            $row = $l;
            $row["{$p}_line"] = ++$n;
            $row["{$p}_desc"] = $l["{$p}_desc"] ?? $part['pt_desc1'] ?? null;
            $row["{$p}_um"] = $part['pt_um'] ?? ($l["{$p}_um"] ?? null) ?? 'EA';
            $row["{$p}_line_amt"] = Money::lineAmount($l[$qty], $l["{$p}_price"], $l["{$p}_disc_pct"]);
            $rows[] = $row;
        }
        return $rows;
    }

    /** Editor config (views/doc/edit.php) with the standard sales line columns added. */
    public static function editorConfig(string $h, string $p, string $qty, array $config, array $extraColumns = []): array
    {
        $priceParams = $config['priceParams'];
        unset($config['priceParams']);
        return $config + [
            'nbrField' => "{$h}_nbr",
            'lines'    => [
                'columns' => array_merge([
                    ['name' => "{$p}_part", 'label' => t('sales.part'), 'type' => 'part', 'width' => '11rem'],
                    ['name' => "{$p}_desc", 'label' => t('sales.desc'), 'type' => 'text'],
                    ['name' => $qty, 'label' => t('sales.qty'), 'type' => 'number', 'width' => '6.5rem'],
                    ['name' => "{$p}_um", 'label' => t('sales.um'), 'type' => 'um', 'width' => '5rem'],
                    ['name' => "{$p}_price", 'label' => t('sales.price'), 'type' => 'number', 'width' => '7.5rem'],
                    ['name' => "{$p}_disc_pct", 'label' => t('sales.disc'), 'type' => 'number', 'width' => '5rem'],
                    ['name' => "{$p}_line_amt", 'label' => t('sales.amount'), 'type' => 'amount', 'width' => '8rem'],
                ], $extraColumns),
                'part'   => [
                    'desc' => "{$p}_desc", 'um' => "{$p}_um", 'price' => "{$p}_price", 'disc' => "{$p}_disc_pct",
                    'priceApi' => '/api/sales/price', 'priceParams' => $priceParams, 'sale' => true,
                ],
                'amount' => ['qty' => $qty, 'price' => "{$p}_price", 'disc' => "{$p}_disc_pct", 'out' => "{$p}_line_amt"],
            ],
            'totals'   => true,
        ];
    }
}
