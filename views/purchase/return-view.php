<?php
/**
 * A saved purchase return (ใบส่งคืนสินค้า), read-only (ux/ui.md "View pages"): Back + status, credit note, Print / PDF.
 * @var array $head   PurchaseReturnController::head()
 * @var array $lines  rtd_det + pt_desc1
 * @var array $status PurchaseReturnController::statusText()
 * @var bool  $canPrint
 */
$num = fn ($v) => number_format((float) $v, 2);
$qty = fn ($v) => \Phai\Modules\Inventory\Stock::fmt((float) $v);
$date = fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '';
$st = $status[$head['rt_status']] ?? ['text' => $head['rt_status'], 'color' => 'secondary'];
$fields = [
    [t('common.doc_nbr'), $head['rt_nbr'], 'col-6 col-md-2'],
    [t('purchase.rc_title'), $head['rt_rc_nbr'], 'col-6 col-md-2'],
    [t('purchase.vendor'), $head['rt_vd_code'] . ($head['vd_name'] ? ' · ' . $head['vd_name'] : ''), 'col-12 col-md-4'],
    [t('purchase.date'), $date($head['rt_date']), 'col-6 col-md-2'],
    [t('purchase.rt_ap_ref'), $head['rt_ap_ref'], 'col-6 col-md-2'],
    [t('purchase.rt_reason'), $head['rt_reason'], 'col-12'],
];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/purchase-returns')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge bg-<?= e($st['color']) ?>-lt"><?= e($st['text']) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($head['rt_status'] === 'O' && $head['rc_ap_ref'] && \Phai\Core\Auth\Auth::can('payables', 'add')): /* invoiced already: the vendor's credit note; else deduct it on the invoice */ ?>
            <a class="btn btn-sm btn-ghost-primary" href="<?= e(url('/payables/new?' . http_build_query(['vendor' => $head['rt_vd_code'], 'type' => 'CM', 'ref' => $head['rc_ap_ref'], 'receipts' => $head['rt_nbr'] . '~RTV']))) ?>"><i class="ti ti-file-minus me-1"></i><?= e(t('purchase.rt_credit')) ?></a>
        <?php endif ?>
        <?php if ($canPrint): ?>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/purchase-returns/' . rawurlencode($head['rt_nbr']) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
        <?php endif ?>
    </div>
</div>

<?= \Phai\Core\View\View::partial('doc/view-fields', ['fields' => $fields]) ?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0">
            <thead>
            <tr>
                <th class="w-1">#</th><th><?= e(t('purchase.part')) ?></th><th><?= e(t('purchase.desc')) ?></th>
                <th class="text-end"><?= e(t('purchase.qty')) ?></th><th><?= e(t('purchase.um')) ?></th><th><?= e(t('purchase.loc')) ?></th>
                <th><?= e(t('purchase.lot')) ?></th><th class="text-end"><?= e(t('purchase.price')) ?></th><th class="text-end"><?= e(t('purchase.amount')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="text-secondary"><?= (int) $l['rtd_line'] ?></td><td><?= e($l['rtd_part']) ?></td><td><?= e((string) ($l['pt_desc1'] ?? '')) ?></td>
                    <td class="num"><?= e($qty($l['rtd_qty'])) ?></td><td class="text-secondary"><?= e((string) $l['rtd_um']) ?></td>
                    <td><?= e((string) $l['rtd_loc']) ?></td><td><?= e((string) $l['rtd_lot']) ?></td>
                    <td class="num"><?= e($num($l['rtd_price'])) ?></td><td class="num"><?= e($num($l['rtd_amt'])) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-end mt-3">
    <table class="phai-summary">
        <tr><td><?= e(t('purchase.subtotal')) ?></td><td class="num"><?= e($num($head['rt_net_amt'])) ?></td></tr>
        <tr><td><?= e(t('purchase.vat')) ?></td><td class="num"><?= e($num($head['rt_tax_amt'])) ?></td></tr>
        <tr class="phai-summary-grand"><td><?= e(t('purchase.total_thb')) ?></td><td class="num"><?= e($num($head['rt_tot_amt'])) ?></td></tr>
    </table>
</div>

<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'RT', 'nbr' => $head['rt_nbr']]) ?></div>
