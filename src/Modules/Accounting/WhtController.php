<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\BahtText;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;
use Phai\Modules\Sales\Sales;

/** Withholding tax certificates (หนังสือรับรองการหักภาษี ณ ที่จ่าย, 50 ทวิ), issued by payment vouchers. */
#[Program('wht')]
final class WhtController
{
    #[Get('/wht')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('accounting.wht_title'),
            'help'    => 'wht',
            'config'  => [
                'api'     => '/api/wht',
                'page'    => '/wht',
                'canAdd'  => false,
                'view'    => false, // the certificate is its printed form: a row and the print icon open it
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'whtc_nbr', 'label' => t('accounting.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'whtc_date', 'label' => t('accounting.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'whtc_vd_cm_code', 'label' => t('purchase.vendor'), 'sort' => true],
                    ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                    ['key' => 'whtc_form_type', 'label' => t('accounting.wht_form')],
                    ['key' => 'whtc_doc_ref', 'label' => t('accounting.ref')],
                    ['key' => 'whtc_base_amt', 'label' => t('accounting.wht_base'), 'type' => 'money', 'sort' => true],
                    ['key' => 'whtc_tax_amt', 'label' => t('accounting.wht_amt'), 'type' => 'money', 'sort' => true],
                    ['key' => 'whtc_status', 'label' => t('common.status'), 'type' => 'status'],
                ],
            ],
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** ?q= &status= &from= &to= */
    #[Describe('Withholding tax certificates (50 Tawi) issued to vendors', ['q' => 'number, vendor or payment', 'status' => 'A active, C cancelled'])]
    #[Get('/api/wht')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['A', 'C'], true)) {
            $where[] = 'w.whtc_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(w.whtc_nbr LIKE ? OR w.whtc_vd_cm_code LIKE ? OR v.vd_name LIKE ? OR w.whtc_doc_ref LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT w.whtc_nbr, w.whtc_date, w.whtc_vd_cm_code, v.vd_name, w.whtc_form_type, w.whtc_doc_ref,
                    w.whtc_base_amt, w.whtc_tax_amt, w.whtc_status',
            'FROM wht_cert w LEFT JOIN vd_mstr v ON v.vd_code = w.whtc_vd_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'whtc_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['whtc_nbr' => 'w.whtc_nbr', 'whtc_date' => 'w.whtc_date', 'whtc_vd_cm_code' => 'w.whtc_vd_cm_code',
             'whtc_base_amt' => 'w.whtc_base_amt', 'whtc_tax_amt' => 'w.whtc_tax_amt'],
            'whtc_nbr'
        );
    }

    /** The list opens the certificate itself, at its print path so the PDF button (?pdf=1) works there. */
    #[Get('/wht/{nbr}')]
    public function view(string $nbr, Request $request): Response
    {
        $query = $request->input('pdf') ? '?pdf=1' : '';
        return Response::redirect(url('/wht/' . rawurlencode($nbr) . '/print') . $query);
    }

    #[Get('/wht/{nbr}/print')]
    public function print(string $nbr): string
    {
        $w = App::db()->one(
            'SELECT w.*, v.*, m.wht_desc, m.wht_pct, m.wht_form_row FROM wht_cert w
               LEFT JOIN vd_mstr v ON v.vd_code = w.whtc_vd_cm_code
               LEFT JOIN wht_mstr m ON m.wht_code = w.whtc_type
              WHERE w.whtc_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
        $co = App::db()->one('SELECT * FROM co_mstr WHERE co_id = 1') ?? [];
        $address = trim(implode(' ', array_filter([
            $w['vd_line1'] ?? '', $w['vd_line2'] ?? '', $w['vd_sub_district'] ?? '', $w['vd_district'] ?? '', $w['vd_province'] ?? '', $w['vd_zip'] ?? '',
        ])));
        return View::render('accounting/wht-print', [
            'title' => $nbr,
            'docTitle' => $nbr,
            'w'     => $w,
            'payer' => [
                'name' => $co['co_name'] ?? '', 'tax' => $co['co_tax_id'] ?? '', 'branch' => Sales::branch($co['co_branch'] ?? null),
                'address' => $co['co_address'] ?? '',
            ],
            'payee' => [
                'name' => trim(($w['vd_name'] ?? $w['whtc_vd_cm_code']) . ' ' . ($w['vd_name2'] ?? '')), 'tax' => $w['vd_tax_id_num'] ?? '',
                'branch' => Sales::branch($w['vd_tax_branch'] ?? null), 'address' => $address,
            ],
            'words' => BahtText::of((float) $w['whtc_tax_amt']),
            // one line per rate withheld (e.g. services 3% and transport 1% on the same payment)
            'lines' => PaymentController::whtLines($nbr) ?: [[
                'code' => $w['whtc_type'], 'desc' => $w['wht_desc'], 'pct' => (float) $w['wht_pct'], 'row' => $w['wht_form_row'] ?? '5',
                'base' => (float) $w['whtc_base_amt'], 'tax' => (float) $w['whtc_tax_amt'],
            ]],
            // ลำดับที่ in the month's ภ.ง.ด. attachment, so the two can be matched
            'seq'   => $w['whtc_status'] === 'C' ? null : self::sequence($w),
            'payerTax' => preg_replace('/\D/', '', (string) ($co['co_tax_id'] ?? '')),
            'payeeTax' => preg_replace('/\D/', '', (string) ($w['vd_tax_id_num'] ?? '')),
        ], 'blank');
    }

    /** ลำดับที่ of the payee in its month's ภ.ง.ด.3 / 53 attachment (one per payee, see WhtReportController::report). */
    private static function sequence(array $w): ?int
    {
        if (!in_array($w['whtc_form_type'], WhtReportController::FORMS, true)) {
            return null;
        }
        foreach (WhtReportController::report($w['whtc_form_type'], substr($w['whtc_date'], 0, 7))['rows'] as $r) {
            if ($r['nbr'] === $w['whtc_nbr']) {
                return $r['seq'];
            }
        }
        return null;
    }

    /** 3.0000 → "3", 1.5 → "1.5" */
    public static function pct(float|string|null $pct): string
    {
        return rtrim(rtrim(number_format((float) $pct, 2), '0'), '.');
    }

    /** "ค่าขนส่ง 1%" → "ค่าขนส่ง อัตราร้อยละ 1": the rate is written once, from the rate itself. */
    public static function rateText(?string $desc, float|string|null $pct): string
    {
        return trim(preg_replace('/\s*\d+(\.\d+)?\s*%\s*$/u', '', (string) $desc) . ' ' . t('whtform.rate', ['pct' => self::pct($pct)]));
    }

    public static function statusText(): array
    {
        return [
            'A' => ['text' => t('accounting.wht_status_A'), 'color' => 'green'],
            'C' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }
}
