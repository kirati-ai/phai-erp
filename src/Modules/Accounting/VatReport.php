<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Http\HttpException;
use Phai\Core\View\View;
use Phai\Modules\Sales\Sales;

/**
 * Monthly VAT reports (รายงานภาษีขาย / รายงานภาษีซื้อ) in baht, for the ภ.พ.30 return.
 * Sales: tax invoices (ih_mstr; credit memos negative). A cancelled tax invoice stays on the report with zero amounts and
 * marked cancelled — the Revenue Department expects every number of the series to be accounted for.
 * Purchases: vendor invoices set up as payables (ap_mstr, not cancelled), VAT = their input VAT line.
 */
final class VatReport
{
    public const SIDES = ['sales', 'purchases'];

    /** @return array{month: string, rows: list<array>, net: float, tax: float} */
    public static function rows(string $side, string $month): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            throw HttpException::invalid(['month' => t('validation.date')]);
        }
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));
        $rows = $side === 'sales'
            ? App::db()->all(
                "SELECT h.ih_inv_date AS date, h.ih_inv_nbr AS nbr, c.cm_name AS name, c.cm_tax_id_num AS tax_id, c.cm_tax_branch AS branch,
                        IF(h.ih_status = 'C', 0, ROUND(h.ih_net_amt * h.ih_ex_rate, 2)) AS net,
                        IF(h.ih_status = 'C', 0, ROUND(h.ih_tax_amt * h.ih_ex_rate, 2)) AS tax,
                        h.ih_status = 'C' AS cancelled
                   FROM ih_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code
                  WHERE h.ih_inv_date BETWEEN ? AND ? AND h.ih_vat_on_rcpt = 0
                 UNION ALL
                 -- services: the VAT falls due on payment, the receipt is the tax invoice (ม.78/1)
                 SELECT r.cr_date, r.cr_nbr, c.cm_name, c.cm_tax_id_num, c.cm_tax_branch,
                        IF(r.cr_status = 'C', 0, SUM(d.crd_net_amt)), IF(r.cr_status = 'C', 0, SUM(d.crd_vat_amt)), r.cr_status = 'C'
                   FROM cr_mstr r JOIN crd_det d ON d.crd_nbr = r.cr_nbr LEFT JOIN cm_mstr c ON c.cm_code = r.cr_cm_code
                  WHERE r.cr_date BETWEEN ? AND ? AND d.crd_vat_amt <> 0
                  GROUP BY r.cr_nbr, r.cr_date, c.cm_name, c.cm_tax_id_num, c.cm_tax_branch, r.cr_status
                 UNION ALL
                 -- deposits: money received before the goods is a tax point too (ใบรับเงินมัดจำ / ใบกำกับภาษี)
                 SELECT p.dp_date, p.dp_nbr, c.cm_name, c.cm_tax_id_num, c.cm_tax_branch,
                        IF(p.dp_status = 'X', 0, p.dp_net_amt), IF(p.dp_status = 'X', 0, p.dp_tax_amt), p.dp_status = 'X'
                   FROM dp_mstr p LEFT JOIN cm_mstr c ON c.cm_code = p.dp_cm_code
                  WHERE p.dp_date BETWEEN ? AND ?
                  ORDER BY date, nbr",
                [$from, $to, $from, $to, $from, $to]
            )
            : App::db()->all(
                "SELECT a.ap_date AS date, a.ap_inv_nbr AS nbr, v.vd_name AS name, v.vd_tax_id_num AS tax_id, v.vd_tax_branch AS branch,
                        a.ap_amt - COALESCE(t.tax, 0) AS net, COALESCE(t.tax, 0) AS tax, a.ap_ref AS ref
                   FROM ap_mstr a
                   LEFT JOIN vd_mstr v ON v.vd_code = a.ap_vd_code
                   LEFT JOIN (SELECT apd_ref, SUM(apd_amt) AS tax FROM apd_det WHERE apd_acct = ? GROUP BY apd_ref) t ON t.apd_ref = a.ap_ref
                  WHERE a.ap_status <> 'C' AND a.ap_date BETWEEN ? AND ?
                 UNION ALL
                 -- petty cash paid against a full tax invoice (เงินสดย่อย)
                 SELECT c.cb_date, c.cb_inv_nbr, COALESCE(v.vd_name, c.cb_payee), c.cb_tax_id, c.cb_tax_branch, c.cb_net_amt, c.cb_vat_amt, c.cb_nbr
                   FROM cb_mstr c LEFT JOIN vd_mstr v ON v.vd_code = c.cb_vd_code
                  WHERE c.cb_type = 'EX' AND c.cb_status = 'A' AND c.cb_vat_amt <> 0 AND c.cb_date BETWEEN ? AND ?
                  ORDER BY date, ref",
                [Gl::acct('vat_in'), $from, $to, $from, $to]
            );
        foreach ($rows as &$r) {
            $r['branch'] = Sales::branch($r['branch'] ?? null);
            if (!empty($r['cancelled'])) {
                $r['name'] = trim(($r['name'] ?? '') . ' (' . t('accounting.vat_cancelled') . ')');
            }
            $r['net'] = (float) $r['net'];
            $r['tax'] = (float) $r['tax'];
        }
        unset($r);
        return [
            'month' => $month,
            'rows'  => $rows,
            'net'   => round(array_sum(array_column($rows, 'net')), 2),
            'tax'   => round(array_sum(array_column($rows, 'tax')), 2),
        ];
    }

    public static function page(string $side): string
    {
        return View::render('accounting/vat', [
            'help'    => 'vat-' . $side,
            'title'   => t("accounting.vat_{$side}_title"),
            'side'    => $side,
            'month'   => date('Y-m'),
            'scripts' => ['js/pages/vat.js'],
        ]);
    }

    public static function print(string $side, string $month): string
    {
        $report = self::rows($side, $month);
        return View::render('accounting/vat-print', [
            'title'    => t("accounting.vat_{$side}_title") . ' ' . $month,
            'docTitle' => "VAT-{$side}-{$month}",
            'side'     => $side,
            'report'   => $report,
            'company'  => App::db()->one('SELECT co_name, co_tax_id, co_branch, co_address FROM co_mstr WHERE co_id = 1') ?? [],
        ], 'blank');
    }
}
