<?php
/**
 * A saved shipment, read-only, laid out like a purchase order view (ux/ui.md "View pages"):
 * Back + status, Make invoice (while waiting) or the invoice it went into, Print; head as plain values; lines below.
 * @var array $head   ShipmentController::head() row
 * @var array $lines  ShipmentController::lines()
 * @var array $status ShipmentController::statusText()
 * @var bool  $canInvoice
 * @var bool  $canPrint
 */
$date = fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '';
$st = $status[$head['sh_status']] ?? ['text' => $head['sh_status'], 'color' => 'secondary'];
$fields = [
    [t('common.doc_nbr'), $head['sh_nbr'], 'col-6 col-md-2'],
    [t('sales.so'), $head['sh_so_nbr'], 'col-6 col-md-2'],
    [t('sales.customer'), $head['sh_cm_code'] . ($head['cm_name'] ? ' · ' . $head['cm_name'] : ''), 'col-12 col-md-4'],
    [t('sales.date'), $date($head['sh_date']), 'col-6 col-md-2'],
    [t('sales.po_ref'), $head['so_po_ref'], 'col-6 col-md-2'],
    [t('sales.ship_via'), $head['sh_ship_via'], 'col-6 col-md-2'],
    [t('sales.tracking'), $head['sh_tracking'], 'col-6 col-md-3'],
    [t('sales.invoice'), $head['sh_inv_nbr'], 'col-6 col-md-2'],
];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/shipments')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge bg-<?= e($st['color']) ?>-lt"><?= e($st['text']) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($head['sh_inv_nbr']): ?>
            <a class="btn btn-sm btn-ghost-primary" href="<?= e(url('/invoices/' . rawurlencode($head['sh_inv_nbr']))) ?>"><i class="ti ti-file-invoice me-1"></i><?= e(t('sales.invoice') . ' ' . $head['sh_inv_nbr']) ?></a>
        <?php elseif ($canInvoice): ?>
            <a class="btn btn-sm btn-ghost-primary" href="<?= e(url('/invoices/new?sh=' . rawurlencode($head['sh_nbr']))) ?>"><i class="ti ti-file-invoice me-1"></i><?= e(t('sales.make_invoice')) ?></a>
        <?php endif ?>
        <?php if (\Phai\Core\Auth\Auth::can('sales-returns', 'add')): /* the customer sends goods back (ใบรับคืนสินค้า) */ ?>
            <a class="btn btn-sm btn-ghost-danger" href="<?= e(url('/sales-returns/new?sh=' . rawurlencode($head['sh_nbr']))) ?>"><i class="ti ti-truck-return me-1"></i><?= e(t('sales.sr_make')) ?></a>
        <?php endif ?>
        <?php if ($canPrint): ?>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/shipments/' . rawurlencode($head['sh_nbr']) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?> / PDF</a>
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
                <th><?= e(t('sales.part')) ?></th>
                <th><?= e(t('sales.desc')) ?></th>
                <th class="text-end"><?= e(t('sales.qty')) ?></th>
                <th><?= e(t('sales.um')) ?></th>
                <th><?= e(t('sales.loc')) ?></th>
                <th><?= e(t('sales.lot')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="text-secondary"><?= (int) $l['shd_line'] ?></td>
                    <td><?= e($l['shd_part']) ?></td>
                    <td><?= e((string) ($l['pt_desc1'] ?? '')) ?></td>
                    <td class="num"><?= e(\Phai\Modules\Inventory\Stock::fmt((float) $l['shd_qty_shp'])) ?></td>
                    <td class="text-secondary"><?= e((string) $l['shd_um']) ?></td>
                    <td><?= e((string) $l['shd_loc']) ?></td>
                    <td><?= e((string) $l['shd_lot']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'SH', 'nbr' => $head['sh_nbr']]) ?></div>
