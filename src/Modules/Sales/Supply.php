<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\App;
use Phai\Modules\Inventory\Stock;

/**
 * Where each open sales-order line will come from — one calculation for the shipment queue (ใบส่งของ) and the
 * production dashboard (ต้องผลิต), so both say the same. Lines go by the date the customer wants them: the stock at the
 * order's site goes to the earliest lines first, and what is still short is matched to open work orders and purchase
 * orders by their due date.
 */
final class Supply
{
    /**
     * Open lines, earliest wanted first, each with: onhand (stock still free before this line), from_stock (what the
     * stock gives this line), docs / supply (the open orders that cover the rest), ready_date (the due date of the last of
     * them; null when not all of it is on the way), short (what nothing covers yet), stock / stock_color (the stock badge).
     */
    public static function lines(): array
    {
        $db = App::db();
        $rows = $db->all(
            "SELECT s.so_nbr, s.so_date, s.so_cm_code, c.cm_name, CONCAT_WS(', ', NULLIF(c.cm_district, ''), NULLIF(c.cm_province, '')) AS ship_place,
                    d.sod_line, d.sod_part, d.sod_site, d.sod_um, p.pt_part_type,
                    COALESCE(NULLIF(d.sod_desc, ''), p.pt_desc1) AS part_desc, d.sod_qty_ord - d.sod_qty_shp AS open_qty,
                    COALESCE(p.pt_inventory, 0) AS stocked,
                    COALESCE(d.sod_req_date, s.so_req_date) AS want_date, COALESCE(d.sod_req_date, s.so_req_date) < ? AS late
               FROM so_mstr s JOIN sod_det d ON d.sod_nbr = s.so_nbr AND d.sod_status = 'O' AND d.sod_qty_ord > d.sod_qty_shp
               LEFT JOIN cm_mstr c ON c.cm_code = s.so_cm_code LEFT JOIN pt_mstr p ON p.pt_part = d.sod_part
              WHERE s.so_status = 'O'
              ORDER BY want_date IS NULL, want_date, s.so_nbr, d.sod_line LIMIT 300",
            [date('Y-m-d')]
        );
        $left = [];   // site|part => still free for the next line
        $coming = []; // site|part => open WO / PO lines still free, earliest due first: [[nbr, date, qty], ...]
        foreach ($rows as &$r) {
            $key = $r['sod_site'] . '|' . $r['sod_part'];
            $open = (float) $r['open_qty'];
            [$r['from_stock'], $r['docs'], $r['supply'], $r['ready_date'], $r['short']] = [null, [], null, null, 0.0];
            if (!(int) $r['stocked']) {
                $r['onhand'] = null;
                [$r['stock'], $r['stock_color']] = [t('sales.stock_service'), 'secondary'];
                continue;
            }
            $left[$key] ??= (float) $db->value('SELECT COALESCE(SUM(ld_qty_oh), 0) FROM ld_det WHERE ld_part = ? AND ld_site = ?', [$r['sod_part'], $r['sod_site']]);
            $r['onhand'] = $left[$key];
            $r['from_stock'] = min($open, max(0.0, $left[$key]));
            $short = $open - $left[$key];
            if ($short > 0.000001) {
                // the orders on the way that will cover what is short, given to earlier lines first
                $coming[$key] ??= $db->all(
                    "SELECT wo_nbr AS nbr, wo_due_date AS due, wo_qty_ord - wo_qty_comp - wo_qty_rjct AS qty FROM wo_mstr
                      WHERE wo_part = ? AND COALESCE(wo_site, ?) = ? AND wo_status IN ('F', 'R') AND wo_qty_ord > wo_qty_comp + wo_qty_rjct
                     UNION ALL
                     SELECT d.pod_nbr, COALESCE(d.pod_req_date, h.po_due_date, h.po_date), d.pod_qty_ord - d.pod_qty_rcvd FROM pod_det d JOIN po_mstr h ON h.po_nbr = d.pod_nbr
                      WHERE d.pod_part = ? AND COALESCE(d.pod_site, ?) = ? AND d.pod_status = 'O' AND h.po_status = 'A' AND d.pod_qty_ord > d.pod_qty_rcvd
                     ORDER BY due, nbr",
                    [$r['sod_part'], $r['sod_site'], $r['sod_site'], $r['sod_part'], $r['sod_site'], $r['sod_site']]
                );
                $docs = [];
                foreach ($coming[$key] as &$c) {
                    if ($short <= 0.000001) {
                        break;
                    }
                    if ($c['qty'] <= 0.000001) {
                        continue;
                    }
                    $take = min($short, (float) $c['qty']);
                    $c['qty'] -= $take;
                    $short -= $take;
                    $docs[$c['nbr']] = true;
                    $r['ready_date'] = max($r['ready_date'] ?? '', (string) $c['due']);
                }
                unset($c);
                $r['docs'] = array_keys($docs);
                $r['supply'] = $docs ? implode(', ', $r['docs']) : t('sales.supply_none');
                if ($short > 0.000001) {
                    $r['ready_date'] = null; // not all of it is on the way
                    $r['short'] = round($short, 5);
                }
            }
            if ($left[$key] >= $open - 0.000001) {
                [$r['stock'], $r['stock_color']] = [t('sales.stock_ok'), 'green'];
            } elseif ($left[$key] > 0) {
                [$r['stock'], $r['stock_color']] = [t('sales.stock_short', ['qty' => Stock::fmt($open - $left[$key])]), 'orange'];
            } else {
                [$r['stock'], $r['stock_color']] = [t('sales.stock_none'), 'red'];
            }
            $left[$key] = max(0.0, $left[$key] - $open);
        }
        return $rows;
    }
}
