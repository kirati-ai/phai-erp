<?php
/**
 * A saved deposit (ใบรับเงินมัดจำ / ใบกำกับภาษี), read-only: Back + status, Cancel (while nothing deducted), Print / PDF,
 * and the tax invoices that deducted it.
 * @var array $head   DepositController::head()
 * @var array $uses   dpa_det rows
 * @var array $status DepositController::statusText()
 * @var bool  $canPrint
 * @var bool  $canCancel
 */
$num = fn ($v) => number_format((float) $v, 2);
$date = fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '';
$st = $status[$head['dp_status']] ?? ['text' => $head['dp_status'], 'color' => 'secondary'];
$fields = [
    [t('common.doc_nbr'), $head['dp_nbr'], 'col-6 col-md-2'],
    [t('sales.so'), $head['dp_so_nbr'], 'col-6 col-md-2'],
    [t('sales.customer'), $head['dp_cm_code'] . ($head['cm_name'] ? ' · ' . $head['cm_name'] : ''), 'col-12 col-md-4'],
    [t('sales.date'), $date($head['dp_date']), 'col-6 col-md-2'],
    [t('accounting.bank'), $head['dp_bank'] . ($head['bk_desc'] ? ' · ' . $head['bk_desc'] : ''), 'col-6 col-md-2'],
    [t('sales.remarks'), $head['dp_remarks'], 'col-12'],
];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/deposits')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge bg-<?= e($st['color']) ?>-lt"><?= e($st['text']) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($canCancel && $head['dp_status'] !== 'X' && (float) $head['dp_used_amt'] == 0.0): ?>
            <button type="button" class="btn btn-sm btn-ghost-danger" data-post="/api/deposits/<?= e(rawurlencode($head['dp_nbr'])) ?>/cancel"
                data-confirm="<?= e(t('purchase.cancel_confirm')) ?>"><i class="ti ti-x me-1"></i><?= e(t('purchase.cancel_doc')) ?></button>
        <?php endif ?>
        <?php if ($canPrint && $head['dp_status'] !== 'X'): ?>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/deposits/' . rawurlencode($head['dp_nbr']) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
        <?php endif ?>
    </div>
</div>

<?= \Phai\Core\View\View::partial('doc/view-fields', ['fields' => $fields]) ?>

<div class="card">
    <div class="card-header py-2"><h3 class="card-title"><?= e(t('deposit.uses')) ?></h3></div>
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0">
            <thead><tr><th><?= e(t('sales.invoice')) ?></th><th class="text-end"><?= e(t('deposit.deducted')) ?></th></tr></thead>
            <tbody>
            <?php foreach ($uses as $u): ?>
                <tr><td><a href="<?= e(url('/invoices/' . rawurlencode($u['dpa_inv_nbr']))) ?>"><?= e($u['dpa_inv_nbr']) ?></a></td><td class="num"><?= e($num($u['dpa_amt'])) ?></td></tr>
            <?php endforeach ?>
            <?php if (!$uses): ?><tr><td colspan="2" class="text-center text-secondary py-3"><?= e(t('deposit.no_uses')) ?></td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-end mt-3">
    <table class="phai-summary">
        <tr><td><?= e(t('sales.subtotal')) ?></td><td class="num"><?= e($num($head['dp_net_amt'])) ?></td></tr>
        <tr><td><?= e(t('sales.vat')) ?></td><td class="num"><?= e($num($head['dp_tax_amt'])) ?></td></tr>
        <tr class="fw-semibold"><td><?= e(t('deposit.amount')) ?></td><td class="num"><?= e($num($head['dp_tot_amt'])) ?></td></tr>
        <tr><td><?= e(t('deposit.deducted')) ?></td><td class="num">-<?= e($num($head['dp_used_amt'])) ?></td></tr>
        <tr class="phai-summary-grand"><td><?= e(t('deposit.left')) ?></td><td class="num"><?= e($num($head['dp_left'])) ?></td></tr>
    </table>
</div>
