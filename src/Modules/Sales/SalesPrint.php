<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\App;
use Phai\Core\BahtText;
use Phai\Core\View\View;
use Phai\Modules\Master\Money;

/** Builds the pieces of printed documents (views/doc/print.php) — sales, purchasing and accounting. */
final class SalesPrint
{
    /**
     * @param array $doc title, nbr, meta [[label, value]], party, lines {columns, rows},
     *                   totals [[label, value, bold]], words, notes, signs [labels]
     */
    public static function render(array $doc): string
    {
        $doc['company'] = self::company();
        // the page title becomes the PDF file name: PO2026100001.pdf
        return View::render('doc/print', ['title' => $doc['nbr'], 'docTitle' => $doc['nbr'], 'doc' => $doc], 'blank');
    }

    /** The company block every printed document and report starts with (views/doc/company.php). */
    public static function company(): array
    {
        $co = App::db()->one('SELECT * FROM co_mstr WHERE co_id = 1') ?? [];
        return [
            'name'    => $co['co_name'] ?? '',
            'address' => $co['co_address'] ?? '',
            'tax'     => !empty($co['co_tax_id']) ? t('sales.tax_id') . ' ' . $co['co_tax_id'] . ' ' . Sales::branch($co['co_branch'] ?? null) : '',
            'phone'   => implode(' · ', array_filter([$co['co_phone'] ?? '', $co['co_email'] ?? ''])),
            'logo'    => \Phai\Modules\System\Logo::printSource(),
        ];
    }

    /** A line under the line above it, across the table: what that line was for (views/doc/print.php). */
    public static function sub(string $text): array
    {
        return ['sub' => $text];
    }

    /** One item as a sub-line: "P-001 ชื่อ · 10 ชิ้น × 360.00 = 3,600.00" (a foreign currency says which). */
    public static function item(string $part, ?string $desc, $qty, ?string $um, $price, $amt, ?string $curr = null): string
    {
        $q = rtrim(rtrim(number_format((float) $qty, 5, '.', ','), '0'), '.');
        $c = $curr && $curr !== Money::base() ? " $curr" : '';
        return '– ' . trim($part . ' ' . $desc) . ' · ' . trim("$q " . ($um ?? '')) . ' × ' . number_format((float) $price, 2)
            . ' = ' . number_format((float) $amt, 2) . $c;
    }

    /** The lines of tax invoices as item texts: [invoice nbr => list<string>] (what a receipt was paid for). */
    public static function invoiceItems(array $invs): array
    {
        if (!$invs) {
            return [];
        }
        $out = [];
        $rows = App::db()->all(
            'SELECT d.ihd_inv_nbr, d.ihd_part, COALESCE(NULLIF(d.ihd_desc, \'\'), p.pt_desc1) AS name, d.ihd_qty_inv, d.ihd_um, d.ihd_price, d.ihd_line_amt, h.ih_curr
               FROM ihd_det d JOIN ih_mstr h ON h.ih_inv_nbr = d.ihd_inv_nbr LEFT JOIN pt_mstr p ON p.pt_part = d.ihd_part
              WHERE d.ihd_inv_nbr IN (' . implode(',', array_fill(0, count($invs), '?')) . ') ORDER BY d.ihd_inv_nbr, d.ihd_line',
            array_values($invs)
        );
        foreach ($rows as $r) {
            $out[$r['ihd_inv_nbr']][] = self::item($r['ihd_part'], $r['name'], $r['ihd_qty_inv'], $r['ihd_um'], $r['ihd_price'], $r['ihd_line_amt'], $r['ih_curr']);
        }
        return $out;
    }

    public static function date(?string $v): string
    {
        return $v ? implode('/', array_reverse(explode('-', substr($v, 0, 10)))) : '';
    }

    public static function vendor(string $code): array
    {
        $v = App::db()->one('SELECT * FROM vd_mstr WHERE vd_code = ?', [$code]) ?? [];
        $address = trim(implode(' ', array_filter([
            $v['vd_line1'] ?? '', $v['vd_line2'] ?? '', $v['vd_sub_district'] ?? '', $v['vd_district'] ?? '', $v['vd_province'] ?? '', $v['vd_zip'] ?? '',
        ])));
        return [
            'label' => t('purchase.vendor'),
            'name'  => trim($code . ' · ' . ($v['vd_name'] ?? '') . ' ' . ($v['vd_name2'] ?? '')),
            'lines' => array_values(array_filter([
                $address,
                !empty($v['vd_tax_id_num']) ? t('sales.tax_id') . ' ' . $v['vd_tax_id_num'] . ' ' . Sales::branch($v['vd_tax_branch'] ?? null) : '',
                $v['vd_phone'] ?? '',
            ])),
        ];
    }

    public static function customer(string $code): array
    {
        $c = Sales::customer($code);
        return [
            'label' => t('sales.customer'),
            'name'  => trim(($c['cm_name'] ?? $code) . ' ' . ($c['cm_name2'] ?? '')),
            'lines' => array_values(array_filter([
                Sales::address($c),
                !empty($c['cm_tax_id_num']) ? t('sales.tax_id') . ' ' . $c['cm_tax_id_num'] . ' ' . Sales::branch($c['cm_tax_branch'] ?? null) : '',
                $c['cm_phone'] ?? '',
            ])),
        ];
    }

    /** Lines with price, discount and amount. $qty is the quantity column, e.g. qtd_qty. */
    public static function priceLines(array $lines, string $p, string $qty): array
    {
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 5), '0'), '.');
        $m = fn ($v) => number_format((float) $v, 2);
        return [
            'columns' => [
                ['label' => '#', 'width' => '28px'],
                ['label' => t('sales.part') . ' / ' . t('sales.desc')],
                ['label' => t('sales.qty'), 'align' => 'r', 'width' => '70px'],
                ['label' => t('sales.um'), 'width' => '50px'],
                ['label' => t('sales.price'), 'align' => 'r', 'width' => '90px'],
                ['label' => t('sales.disc'), 'align' => 'r', 'width' => '60px'],
                ['label' => t('sales.amount'), 'align' => 'r', 'width' => '100px'],
            ],
            'rows' => array_map(fn ($l) => [
                $l["{$p}_line"],
                $l["{$p}_part"] . ' ' . ($l["{$p}_desc"] ?? $l['pt_desc1'] ?? ''),
                $q($l[$qty]),
                $l["{$p}_um"],
                $m($l["{$p}_price"]),
                (float) $l["{$p}_disc_pct"] ? $q($l["{$p}_disc_pct"]) : '',
                $m($l["{$p}_line_amt"]),
            ], $lines),
        ];
    }

    /** Subtotal, VAT and total rows, plus the total in Thai words for baht documents. */
    /**
     * $rate: on a tax invoice in a foreign currency, the exchange rate — the VAT and total must also be stated in baht
     * (ม.86/4), so they follow as rows and the amount in words is in baht.
     */
    public static function totals(mixed $net, mixed $pct, mixed $tax, mixed $total, string $curr, ?float $rate = null): array
    {
        $m = fn ($v) => number_format((float) $v, 2);
        $base = Money::base();
        $rows = [
            [t('sales.subtotal'), $m($net), false],
            [t('sales.vat') . ((float) $pct ? ' ' . rtrim(rtrim((string) $pct, '0'), '.') . '%' : ''), $m($tax), false],
            [t('sales.total') . ($curr !== $base ? " ($curr)" : ''), $m($total), $curr === $base || $rate === null ? true : 'bold'],
        ];
        if ($curr !== $base && $rate) {
            $rows[] = [t('sales.ex_rate'), rtrim(rtrim(number_format($rate, 6, '.', ','), '0'), '.'), false];
            $rows[] = [t('sales.vat') . " ($base)", $m(round((float) $tax * $rate, 2)), false];
            $rows[] = [t('sales.total') . " ($base)", $m(round((float) $total * $rate, 2)), true];
            return ['rows' => $rows, 'words' => BahtText::of(round((float) $total * $rate, 2))];
        }
        return ['rows' => $rows, 'words' => $curr === $base ? BahtText::of((float) $total) : ''];
    }
}
