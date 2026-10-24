<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\View\View;
use Phai\Modules\System\Attachment;

/**
 * ภ.พ.30 — the monthly VAT return. Output VAT (รายงานภาษีขาย) − input VAT (รายงานภาษีซื้อ) − credit carried from the
 * month before = VAT to pay (positive) or a credit carried to next month (negative). Filing a month closes both VAT
 * accounts into the result: Dr ภาษีขาย / Cr ภาษีซื้อ / Cr ภาษีมูลค่าเพิ่มค้างจ่าย (or Dr ภาษีชำระเกินยกไป). Paying the
 * Revenue Department is a journal (Dr ภาษีมูลค่าเพิ่มค้างจ่าย / Cr ธนาคาร). Only the latest filed month can be undone.
 */
#[Program('vat-return')]
final class VatReturnController
{
    #[Get('/vat-return')]
    public function page(): string
    {
        return View::render('accounting/vat-return', [
            'title'   => t('vatreturn.title'),
            'help'    => 'vat-return',
            'config'  => ['month' => date('Y-m', strtotime(date('Y-m-01') . ' -1 month')), 'canFile' => Auth::can('vat-return', 'add'),
                          'canUndo' => Auth::can('vat-return', 'delete'), 'canPrint' => Auth::can('vat-return', 'print')],
            'scripts' => ['js/pages/vat-return.js'],
        ]);
    }

    #[Describe('ภ.พ.30 VAT return of a month: output VAT, input VAT, credit carried from the month before, net (positive = to pay, negative = credit carried forward), and whether it is filed (baht)', ['month' => 'YYYY-MM'])]
    #[Get('/api/vat-return')]
    public function show(Request $request): array
    {
        return self::compute((string) $request->input('month', date('Y-m')));
    }

    #[Describe('Filed ภ.พ.30 returns, newest first (baht)')]
    #[Get('/api/vat-return/list')]
    public function list(): array
    {
        return App::db()->all('SELECT * FROM vr_mstr ORDER BY vr_month DESC LIMIT 36');
    }

    /** Body: {month, remarks}. */
    #[Post('/api/vat-return')]
    public function file(Request $request): array
    {
        $month = (string) ($request->body['month'] ?? '');
        $remarks = mb_substr(trim((string) ($request->body['remarks'] ?? '')), 0, 255);
        return App::db()->transaction(function (Db $db) use ($month, $remarks) {
            $db->one('SELECT ctrl_id FROM gl_ctrl WHERE ctrl_id = 1 FOR UPDATE'); // one filing at a time
            $r = self::compute($month);
            if ($r['filed']) {
                throw new HttpException(409, t('vatreturn.already', ['month' => $month]));
            }
            if ($month >= date('Y-m')) {
                throw new HttpException(409, t('vatreturn.not_over'));
            }
            $later = $db->value('SELECT vr_month FROM vr_mstr WHERE vr_month > ? LIMIT 1', [$month]);
            if ($later) {
                throw new HttpException(409, t('vatreturn.later', ['month' => $later]));
            }
            $date = max(date('Y-m-t', strtotime($month . '-01')), Gl::periodStart());
            $lines = [[Gl::acct('vat_out'), $r['output'], t('vatreturn.desc', ['month' => $month])], [Gl::acct('vat_in'), -$r['input']]];
            if ($r['carried'] != 0.0) {
                $lines[] = [Gl::acct('vat_cr'), -$r['carried']]; // last month's credit is used up
            }
            $lines[] = $r['net'] >= 0 ? [Gl::acct('vat_pay'), -$r['net']] : [Gl::acct('vat_cr'), -$r['net']];
            Gl::post($month, 'VR', $date, array_values(array_filter($lines, fn ($l) => round((float) $l[1], 2) != 0.0)), t('vatreturn.desc', ['month' => $month]));
            $row = ['vr_month' => $month, 'vr_output' => $r['output'], 'vr_input' => $r['input'], 'vr_carried' => $r['carried'],
                    'vr_net' => $r['net'], 'vr_date' => $date, 'vr_remarks' => $remarks ?: null,
                    'created_by' => Auth::userId(), 'updated_by' => Auth::userId()];
            $db->insert('vr_mstr', $row);
            Audit::created('vr_mstr', "vr_month=$month", $row);
            return self::compute($month);
        });
    }

    #[Delete('/api/vat-return/{month}')]
    public function undo(string $month): array
    {
        return App::db()->transaction(function (Db $db) use ($month) {
            $row = $db->one('SELECT * FROM vr_mstr WHERE vr_month = ? FOR UPDATE', [$month]) ?? throw HttpException::notFound();
            if ($db->value('SELECT 1 FROM vr_mstr WHERE vr_month > ? LIMIT 1', [$month])) {
                throw new HttpException(409, t('vatreturn.not_latest'));
            }
            Gl::reverse($month, 'VR', max(date('Y-m-d'), Gl::periodStart()), t('vatreturn.desc_undo', ['month' => $month]));
            $db->delete('vr_mstr', ['vr_month' => $month]);
            Audit::deleted('vr_mstr', "vr_month=$month", $row);
            Attachment::removeAll('VR', $month);
            return self::compute($month);
        });
    }

    #[Get('/vat-return/{month}/print'), Action('print')]
    public function print(string $month): string
    {
        return View::render('accounting/vat-return-print', [
            'title'    => t('vatreturn.title') . ' ' . $month,
            'docTitle' => "PP30-$month",
            'r'        => self::compute($month),
            'company'  => App::db()->one('SELECT co_name, co_tax_id, co_branch, co_address FROM co_mstr WHERE co_id = 1') ?? [],
        ], 'blank');
    }

    /** The return of a month: filed figures when filed, else what the two VAT reports say now. */
    public static function compute(string $month): array
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            throw HttpException::invalid(['month' => t('validation.date')]);
        }
        $filed = App::db()->one('SELECT * FROM vr_mstr WHERE vr_month = ?', [$month]);
        $sales = VatReport::rows('sales', $month);
        $purchases = VatReport::rows('purchases', $month);
        if ($filed) {
            [$output, $input, $carried] = [(float) $filed['vr_output'], (float) $filed['vr_input'], (float) $filed['vr_carried']];
        } else {
            $output = $sales['tax'];
            $input = $purchases['tax'];
            // the credit of the latest filed month before this one, if it ended in credit
            $prev = (float) App::db()->value('SELECT vr_net FROM vr_mstr WHERE vr_month < ? ORDER BY vr_month DESC LIMIT 1', [$month]);
            $carried = $prev < 0 ? -$prev : 0.0;
        }
        $net = round($output - $input - $carried, 2);
        return [
            'month' => $month, 'filed' => (bool) $filed, 'date' => $filed['vr_date'] ?? null, 'remarks' => $filed['vr_remarks'] ?? null,
            'sales_net' => $sales['net'], 'purchases_net' => $purchases['net'],
            'output' => round($output, 2), 'input' => round($input, 2), 'carried' => round($carried, 2), 'net' => $net,
            'due' => date('Y-m-15', strtotime($month . '-01 +1 month')),
        ];
    }
}
