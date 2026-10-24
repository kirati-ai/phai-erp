<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Modules\Sales\SalesPrint;

/**
 * What a payable was for, as lines to print under it (ใบตั้งหนี้, ใบสำคัญจ่าย, ใบจ่ายชำระ): the goods of the
 * receipts it booked and of the returns it took off, and its typed expense lines — one source for all three.
 */
final class ApItems
{
    /** Goods behind one payable by the receipt or return they came on: [doc nbr => list<string>]. */
    public static function goods(string $ref): array
    {
        $db = App::db();
        $out = [];
        $received = $db->all(
            'SELECT r.rc_nbr, r.rc_curr, d.rcd_part, p.pt_desc1, d.rcd_qty_rcvd, d.rcd_um, d.rcd_price, d.rcd_line_amt
               FROM rc_mstr r JOIN rcd_det d ON d.rcd_nbr = r.rc_nbr LEFT JOIN pt_mstr p ON p.pt_part = d.rcd_part
              WHERE r.rc_ap_ref = ? ORDER BY r.rc_nbr, d.rcd_line',
            [$ref]
        );
        foreach ($received as $r) {
            $out[$r['rc_nbr']][] = SalesPrint::item($r['rcd_part'], $r['pt_desc1'], $r['rcd_qty_rcvd'], $r['rcd_um'], $r['rcd_price'], $r['rcd_line_amt'], $r['rc_curr']);
        }
        $returned = $db->all(
            'SELECT t.rt_nbr, d.rtd_part, p.pt_desc1, d.rtd_qty, d.rtd_um, d.rtd_price, d.rtd_amt
               FROM rt_mstr t JOIN rtd_det d ON d.rtd_nbr = t.rt_nbr LEFT JOIN pt_mstr p ON p.pt_part = d.rtd_part
              WHERE t.rt_ap_ref = ? ORDER BY t.rt_nbr, d.rtd_line',
            [$ref]
        );
        foreach ($returned as $r) {
            $out[$r['rt_nbr']][] = SalesPrint::item($r['rtd_part'], $r['pt_desc1'], -(float) $r['rtd_qty'], $r['rtd_um'], $r['rtd_price'], -(float) $r['rtd_amt']);
        }
        return $out;
    }

    /** The goods doc whose number an AP line's description carries (its receipt / return line), if any. */
    public static function docOf(string $desc, array $goods): ?string
    {
        foreach (array_keys($goods) as $doc) {
            if (str_contains($desc, (string) $doc)) {
                return (string) $doc;
            }
        }
        return null;
    }

    /** What each payable was for, in its line order: [ap_ref => list<string>] — goods, then the other lines' descriptions. */
    public static function of(array $refs): array
    {
        $refs = array_values(array_unique(array_filter($refs)));
        if (!$refs) {
            return [];
        }
        $lines = App::db()->all(
            'SELECT apd_ref, apd_desc, apd_amt FROM apd_det WHERE apd_ref IN (' . implode(',', array_fill(0, count($refs), '?')) . ')
                AND apd_acct <> ? ORDER BY apd_ref, apd_line',
            [...$refs, Gl::acct('vat_in')]
        );
        $out = [];
        $goods = [];
        $done = [];
        foreach ($lines as $l) {
            $ref = $l['apd_ref'];
            $goods[$ref] ??= self::goods($ref);
            $doc = self::docOf((string) $l['apd_desc'], $goods[$ref]);
            if ($doc !== null) {
                if (!isset($done["$ref~$doc"])) {
                    $done["$ref~$doc"] = true;
                    $out[$ref] = [...($out[$ref] ?? []), ...$goods[$ref][$doc]];
                }
                continue; // the receipt's own line (and its price difference) is the goods above
            }
            if (round((float) $l['apd_amt'], 2) != 0.0 && trim((string) $l['apd_desc']) !== '') {
                $out[$ref][] = '– ' . trim((string) $l['apd_desc']) . ' = ' . number_format((float) $l['apd_amt'], 2);
            }
        }
        return $out;
    }
}
