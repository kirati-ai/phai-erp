<?php
/**
 * A saved billing note, read-only, laid out like a purchase order view (ux/ui.md "View pages"):
 * Back + status, Cancel (while open), Print; head as plain values; the invoices it bills below.
 * @var array $head   BillingController::head() row
 * @var array $lines  bld_det rows
 * @var array $status BillingController::statusText()
 * @var bool  $canPrint
 * @var bool  $canCancel
 */
$num = fn ($v) => number_format((float) $v, 2);
$date = fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '';
$st = $status[$head['bl_status']] ?? ['text' => $head['bl_status'], 'color' => 'secondary'];
$fields = [
    [t('common.doc_nbr'), $head['bl_nbr'], 'col-6 col-md-2'],
    [t('sales.customer'), $head['bl_cm_code'] . ($head['cm_name'] ? ' · ' . $head['cm_name'] : ''), 'col-12 col-md-4'],
    [t('sales.date'), $date($head['bl_date']), 'col-6 col-md-2'],
    [t('sales.pay_date'), $date($head['bl_due_date']), 'col-6 col-md-2'],
    [t('sales.total'), $num($head['bl_tot_amt']), 'col-6 col-md-2', true],
    [t('sales.remarks'), $head['bl_remarks'], 'col-12'],
];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/billings')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge bg-<?= e($st['color']) ?>-lt"><?= e($st['text']) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($canCancel && $head['bl_status'] === 'O'): ?>
            <button type="button" class="btn btn-sm btn-ghost-danger" data-post="/api/billings/<?= e(rawurlencode($head['bl_nbr'])) ?>/cancel"
                data-confirm="<?= e(t('purchase.cancel_confirm')) ?>"><i class="ti ti-ban me-1"></i><?= e(t('purchase.cancel_doc')) ?></button>
        <?php endif ?>
        <?php if ($canPrint): ?>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/billings/' . rawurlencode($head['bl_nbr']) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?> / PDF</a>
        <?php endif ?>
    </div>
</div>

<?= \Phai\Core\View\View::partial('doc/view-fields', ['fields' => $fields]) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0">
            <thead>
            <tr>
                <th class="w-1">#</th>
                <th><?= e(t('sales.invoice')) ?></th>
                <th><?= e(t('sales.date')) ?></th>
                <th><?= e(t('sales.due_date')) ?></th>
                <th class="text-end"><?= e(t('sales.inv_amount')) ?></th>
                <th class="text-end"><?= e(t('sales.balance')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="text-secondary"><?= (int) $l['bld_line'] ?></td>
                    <td><a href="<?= e(url('/invoices/' . rawurlencode($l['bld_inv_nbr']))) ?>"><?= e($l['bld_inv_nbr']) ?></a></td>
                    <td><?= e($date($l['bld_inv_date'])) ?></td>
                    <td><?= e($date($l['bld_due_date'])) ?></td>
                    <td class="num"><?= e($num($l['bld_inv_amt'])) ?></td>
                    <td class="num"><?= e($num($l['bld_bal_amt'])) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-end mt-3">
    <table class="phai-summary">
        <?php $vat = array_sum(array_column($lines, 'bld_vat')) ?>
        <tr><td><?= e(t('sales.subtotal')) ?></td><td class="num"><?= e($num((float) $head['bl_tot_amt'] - $vat)) ?></td></tr>
        <tr><td><?= e(t('sales.vat')) ?></td><td class="num"><?= e($num($vat)) ?></td></tr>
        <tr class="phai-summary-grand"><td><?= e(t('sales.total')) ?></td><td class="num"><?= e($num($head['bl_tot_amt'])) ?></td></tr>
    </table>
</div>
