<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\Sales\Sales;
use Phai\Modules\System\Address;

/**
 * Monthly withholding tax attachment (ใบแนบ ภ.ง.ด.3 / ภ.ง.ด.53) from the 50 ทวิ certificates
 * issued by payments (wht_cert, not cancelled), one row per rate withheld (whtd_det), for filing by the 7th of the next month.
 */
#[Program('wht-report')]
final class WhtReportController
{
    public const FORMS = ['PND3', 'PND53'];

    #[Get('/wht-report')]
    public function page(): string
    {
        return View::render('accounting/wht-report', [
            'title'   => t('accounting.pnd_title'),
            'help'    => 'wht-report',
            'month'   => date('Y-m'),
            'scripts' => ['js/pages/wht-report.js'],
        ]);
    }

    /** ?form=PND3|PND53 &month=YYYY-MM */
    #[Describe('Withholding tax attachment of one month (PND3 = individuals, PND53 = companies): payee, tax id, address, payment date, income type, rate, amount paid, tax withheld', ['form' => 'PND3 or PND53', 'month' => 'YYYY-MM'])]
    #[Get('/api/wht-report')]
    public function rows(Request $request): array
    {
        return self::report((string) $request->input('form', 'PND53'), (string) $request->input('month', date('Y-m')));
    }

    /** Form and month are in the path so the PDF link (?pdf=1) keeps them. */
    #[Get('/wht-report/{form}/{month}/print'), Action('print')]
    public function print(string $form, string $month): string
    {
        $report = self::report($form, $month);
        return View::render('accounting/wht-report-print', [
            'title'    => t("accounting.form_$form") . ' ' . $month,
            'docTitle' => "$form-$month",
            'report'   => $report,
            'company'  => App::db()->one('SELECT co_name, co_tax_id, co_branch, co_address FROM co_mstr WHERE co_id = 1') ?? [],
            'address'  => Address::companyParts(),
        ], 'blank');
    }

    /** @return array{form: string, month: string, rows: list<array>, base: float, tax: float} */
    public static function report(string $form, string $month): array
    {
        $errors = [];
        if (!in_array($form, self::FORMS, true)) {
            $errors['form'] = t('validation.in');
        }
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            $errors['month'] = t('validation.date');
        }
        if ($errors) {
            throw HttpException::invalid($errors);
        }
        $from = $month . '-01';
        $rows = App::db()->all(
            "SELECT w.whtc_nbr AS nbr, w.whtc_date AS date, w.whtc_doc_ref AS ref, w.whtc_vd_cm_code AS code,
                    TRIM(CONCAT(COALESCE(v.vd_name, w.whtc_vd_cm_code), ' ', COALESCE(v.vd_name2, ''))) AS name,
                    v.vd_tax_id_num AS tax_id, v.vd_tax_branch AS branch,
                    TRIM(CONCAT_WS(' ', v.vd_line1, v.vd_line2, v.vd_sub_district, v.vd_district, v.vd_province, v.vd_zip)) AS address,
                    d.whtd_desc AS income, d.whtd_pct AS pct, d.whtd_base_amt AS base, d.whtd_tax_amt AS tax
               FROM wht_cert w
               JOIN whtd_det d ON d.whtd_nbr = w.whtc_nbr
               LEFT JOIN vd_mstr v ON v.vd_code = w.whtc_vd_cm_code
              WHERE w.whtc_status <> 'C' AND w.whtc_form_type = ? AND w.whtc_date BETWEEN ? AND ?
              ORDER BY w.whtc_date, w.whtc_nbr, d.whtd_line",
            [$form, $from, date('Y-m-t', strtotime($from))]
        );
        // ลำดับที่ is per payee, in the order of their first payment; their payments are listed under it
        $seqs = [];
        foreach ($rows as &$r) {
            $r['seq'] = $seqs[$r['code']] ??= count($seqs) + 1;
            $r['branch_no'] = str_pad(preg_replace('/\D/', '', (string) ($r['branch'] ?? '')), 5, '0', STR_PAD_LEFT);
            $r['branch'] = Sales::branch($r['branch'] ?? null);
            $r['pct'] = (float) $r['pct'];
            $r['base'] = round((float) $r['base'], 2);
            $r['tax'] = round((float) $r['tax'], 2);
        }
        unset($r);
        return [
            'form'  => $form,
            'month' => $month,
            'rows'  => $rows,
            'payees' => count($seqs),
            'base'  => round(array_sum(array_column($rows, 'base')), 2),
            'tax'   => round(array_sum(array_column($rows, 'tax')), 2),
        ];
    }
}
