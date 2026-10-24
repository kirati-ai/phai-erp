<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;

/**
 * Vendor evaluation (การประเมินผู้ขาย) — the SRM side of purchasing: for each vendor over a period, from what actually
 * happened: purchase value, receipts on time (receipt date ≤ the line's required date, else the PO's due date), goods sent
 * back (returns ÷ received), average lead time (order → receipt). Score = 60% on time + 40% not returned → grade A/B/C.
 */
#[Program('vendor-rating')]
final class VendorRatingController
{
    #[Get('/vendor-rating')]
    public function page(): string
    {
        return View::render('purchase/vendor-rating', [
            'title'   => t('purchase.vr_title'),
            'help'    => 'vendor-rating',
            'config'  => ['from' => date('Y') . '-01-01', 'to' => date('Y-m-d')],
            'scripts' => ['js/pages/vendor-rating.js'],
        ]);
    }

    #[Describe('Vendor evaluation over a period: purchase value (baht), receipt lines, on-time %, returned %, average lead time in days, score and grade A/B/C', ['from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD'])]
    #[Get('/api/vendor-rating')]
    public function rating(Request $request): array
    {
        $ok = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
        $from = $ok($request->input('from')) ? $request->input('from') : date('Y') . '-01-01';
        $to = $ok($request->input('to')) ? $request->input('to') : date('Y-m-d');
        if ($from > $to) {
            throw HttpException::invalid(['to' => t('accounting.bad_range')]);
        }
        $rows = App::db()->all(
            "SELECT r.rc_vd_code AS vendor, v.vd_name,
                    COUNT(*) AS line_count, SUM(d.rcd_qty_rcvd) AS qty,
                    ROUND(SUM(d.rcd_line_amt * r.rc_ex_rate), 2) AS value,
                    SUM(r.rc_date <= COALESCE(pd.pod_req_date, p.po_due_date, r.rc_date)) AS on_time,
                    AVG(DATEDIFF(r.rc_date, p.po_date)) AS lead_days
               FROM rc_mstr r JOIN rcd_det d ON d.rcd_nbr = r.rc_nbr
               LEFT JOIN po_mstr p ON p.po_nbr = r.rc_po_nbr
               LEFT JOIN pod_det pd ON pd.pod_nbr = r.rc_po_nbr AND pd.pod_line = d.rcd_pod_line
               LEFT JOIN vd_mstr v ON v.vd_code = r.rc_vd_code
              WHERE r.rc_date BETWEEN ? AND ?
              GROUP BY r.rc_vd_code, v.vd_name",
            [$from, $to]
        );
        $returned = array_column(App::db()->all(
            "SELECT t.rt_vd_code AS vendor, SUM(x.rtd_qty) AS qty FROM rt_mstr t JOIN rtd_det x ON x.rtd_nbr = t.rt_nbr
              WHERE t.rt_date BETWEEN ? AND ? GROUP BY t.rt_vd_code",
            [$from, $to]
        ), 'qty', 'vendor');
        foreach ($rows as &$r) {
            $onTime = $r['line_count'] ? round(100 * (float) $r['on_time'] / (int) $r['line_count'], 1) : 0.0;
            $ret = (float) $r['qty'] ? round(100 * (float) ($returned[$r['vendor']] ?? 0) / (float) $r['qty'], 1) : 0.0;
            $score = round(0.6 * $onTime + 0.4 * max(0, 100 - $ret), 1);
            $r = [
                'vendor' => $r['vendor'], 'vd_name' => $r['vd_name'], 'lines' => (int) $r['line_count'], 'value' => (float) $r['value'],
                'on_time_pct' => $onTime, 'returned_pct' => $ret, 'lead_days' => $r['lead_days'] === null ? null : round((float) $r['lead_days'], 1),
                'score' => $score, 'grade' => $score >= 90 ? 'A' : ($score >= 75 ? 'B' : 'C'),
            ];
        }
        unset($r);
        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $b['value'] <=> $a['value']);
        return ['from' => $from, 'to' => $to, 'rows' => $rows];
    }
}
