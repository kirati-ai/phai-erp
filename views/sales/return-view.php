<?php
/**
 * A saved sales return (ใบรับคืนสินค้า), read-only (ux/ui.md "View pages"): Back + status, make the credit note, Print / PDF.
 * @var array $head   SalesReturnController::head()
 * @var array $lines  srd_det + pt_desc1
 * @var array $status SalesReturnController::statusText()
 * @var bool  $canPrint
 */
$num = fn ($v) => number_format((float) $v, 2);
$qty = fn ($v) => \Phai\Modules\Inventory\Stock::fmt((float) $v);
$date = fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '';
$st = $status[$head['sr_status']] ?? ['text' => $head['sr_status'], 'color' => 'secondary'];
$fields = [
    [t('common.doc_nbr'), $head['sr_nbr'], 'col-6 col-md-2'],
    [t('sales.shipment'), $head['sr_sh_nbr'], 'col-6 col-md-2'],
    [t('sales.customer'), $head['sr_cm_code'] . ($head['cm_name'] ? ' · ' . $head['cm_name'] : ''), 'col-12 col-md-4'],
    [t('sales.date'), $date($head['sr_date']), 'col-6 col-md-2'],
    [t('sales.invoice'), $head['sr_inv_nbr'], 'col-6 col-md-2'],
    [t('sales.credit_note'), $head['sr_cn_nbr'], 'col-6 col-md-2'],
    [t('sales.sr_reason'), $head['sr_reason'], 'col-12'],
];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/sales-returns')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge bg-<?= e($st['color']) ?>-lt"><?= e($st['text']) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($head['sr_status'] === 'O' && \Phai\Core\Auth\Auth::can('ar-invoices', 'add')): ?>
            <a class="btn btn-sm btn-ghost-primary" href="<?= e(url('/ar-invoices/new?return=' . rawurlencode($head['sr_nbr']))) ?>"><i class="ti ti-file-minus me-1"></i><?= e(t('sales.sr_make_cn')) ?></a>
        <?php endif ?>
        <?php if ($canPrint): ?>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/sales-returns/' . rawurlencode($head['sr_nbr']) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
        <?php endif ?>
    </div>
</div>

<?= \Phai\Core\View\View::partial('doc/view-fields', ['fields' => $fields]) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0">
            <thead>
            <tr>
                <th class="w-1">#</th><th><?= e(t('stock.part')) ?></th><th><?= e(t('stock.desc')) ?></th>
                <th class="text-end"><?= e(t('stock.qty')) ?></th><th><?= e(t('stock.um')) ?></th><th><?= e(t('stock.loc')) ?></th>
                <th><?= e(t('stock.lot')) ?></th><th class="text-end"><?= e(t('stock.unit_cost')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="text-secondary"><?= (int) $l['srd_line'] ?></td><td><?= e($l['srd_part']) ?></td><td><?= e((string) ($l['pt_desc1'] ?? '')) ?></td>
                    <td class="num"><?= e($qty($l['srd_qty'])) ?></td><td class="text-secondary"><?= e((string) $l['srd_um']) ?></td>
                    <td><?= e((string) $l['srd_loc']) ?></td><td><?= e((string) $l['srd_lot']) ?></td><td class="num"><?= e($num($l['srd_cost'])) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'SR', 'nbr' => $head['sr_nbr']]) ?></div>
