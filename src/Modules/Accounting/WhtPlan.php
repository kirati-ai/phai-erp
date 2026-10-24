<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;

/**
 * What a payment will withhold, from the withholding codes on the payable lines (apd_det.apd_wht_code).
 * Shown — not posted — on the payable, the voucher and their prints; the payment posts it (PaymentController)
 * and issues the 50 ทวิ. One calculation for all of them, so the figures agree along the way.
 */
final class WhtPlan
{
    /**
     * @param array<string, float> $parts payable ref => the part of it being vouchered / paid (its whole amount when unpaid)
     * @return list<array{code: string, desc: string, pct: float, base: float, tax: float}> one row per withholding code
     */
    public static function of(array $parts): array
    {
        $parts = array_filter($parts, fn ($v) => (float) $v != 0.0);
        if (!$parts) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($parts), '?'));
        $rows = App::db()->all(
            "SELECT d.apd_ref, d.apd_wht_code AS code, w.wht_desc, w.wht_pct, SUM(d.apd_amt) AS amt, p.ap_amt
               FROM apd_det d JOIN ap_mstr p ON p.ap_ref = d.apd_ref LEFT JOIN wht_mstr w ON w.wht_code = d.apd_wht_code
              WHERE d.apd_ref IN ($marks) AND d.apd_wht_code IS NOT NULL
              GROUP BY d.apd_ref, d.apd_wht_code, w.wht_desc, w.wht_pct, p.ap_amt",
            array_keys($parts)
        );
        $out = [];
        foreach ($rows as $r) {
            // the line's share of what is paid: a part payment withholds its part
            $base = (float) $r['amt'] * (float) $parts[$r['apd_ref']] / ((float) $r['ap_amt'] ?: 1);
            $out[$r['code']] ??= ['code' => $r['code'], 'desc' => (string) $r['wht_desc'], 'pct' => (float) $r['wht_pct'], 'base' => 0.0, 'tax' => 0.0];
            $out[$r['code']]['base'] += $base;
        }
        foreach ($out as &$o) {
            $o['base'] = round($o['base'], 2);
            $o['tax'] = round($o['base'] * $o['pct'] / 100, 2);
        }
        unset($o);
        usort($out, fn ($a, $b) => $b['pct'] <=> $a['pct']);
        return array_values(array_filter($out, fn ($o) => $o['tax'] != 0.0));
    }

    /** The plan of a saved voucher: its payables in the amounts it carries. */
    public static function ofVoucher(string $nbr): array
    {
        return self::of(array_column(
            App::db()->all('SELECT apvd_ap_ref, SUM(apvd_amt) AS amt FROM apvd_det WHERE apvd_nbr = ? GROUP BY apvd_ap_ref', [$nbr]),
            'amt',
            'apvd_ap_ref'
        ));
    }
}
