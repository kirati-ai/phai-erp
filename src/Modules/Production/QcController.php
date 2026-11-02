<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Routing\Get;
use Phai\Modules\Sales\SalesPrint;

/** The printed QC record (ใบตรวจคุณภาพสินค้า); the screen itself is QcResource, where a saved record cannot change. */
#[Program('qc')]
final class QcController
{
    #[Get('/qc/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $q = App::db()->one(
            'SELECT q.*, p.pt_desc1, p.pt_um FROM qc_hist q LEFT JOIN pt_mstr p ON p.pt_part = q.qc_part WHERE q.qc_test_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
        $label = array_column(QcResource::fields(), 'label', 'name'); // the same names as on the screen
        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 5, '.', ','), '0'), '.');
        return SalesPrint::render([
            'title'  => t('production.qc_print'),
            'nbr'    => $nbr,
            'meta'   => [
                [$label['qc_date'], SalesPrint::date($q['qc_date'])],
                [$label['qc_ref_doc'], (string) ($q['qc_ref_doc'] ?? '')],
                [$label['qc_result'], $q['qc_result'] ? QcResource::optionLabel('qc_result', $q['qc_result']) : ''],
                [$label['qc_inspector'], (string) ($q['qc_inspector'] ?? '')],
            ],
            'lines'  => ['columns' => [
                ['label' => '#', 'width' => '28px'],
                ['label' => t('stock.part') . ' / ' . t('stock.desc')],
                ['label' => $label['qc_lot']],
                ['label' => $label['qc_qty_inspect'], 'align' => 'r'],
                ['label' => $label['qc_qty_pass'], 'align' => 'r'],
                ['label' => $label['qc_qty_fail'], 'align' => 'r'],
                ['label' => t('production.um'), 'width' => '50px'],
            ], 'rows' => [[1, trim($q['qc_part'] . ' ' . ($q['pt_desc1'] ?? '')), (string) ($q['qc_lot'] ?? ''),
                $n($q['qc_qty_inspect']), $n($q['qc_qty_pass']), $n($q['qc_qty_fail']), (string) ($q['pt_um'] ?? '')]]],
            'totals' => [],
            'notes'  => (string) ($q['qc_remarks'] ?? ''),
            'signs'  => [t('production.qc_sign_inspector'), SalesPrint::approve(t('production.qc_sign_approve'))],
        ]);
    }
}
