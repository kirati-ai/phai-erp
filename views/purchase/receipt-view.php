<?php
/**
 * A saved goods receipt, read-only, laid out like a purchase order view (ux/ui.md "View pages"):
 * Back + status, actions (create payable while waiting), Print / PDF; head fields as plain values; lines below.
 * Receipts are not edited after saving (stock and GRNI are already posted).
 * @var array $head   ReceiptController::head() row
 * @var array $lines  rcd_det rows + pt_desc1
 * @var bool  $canPrint
 * @var bool  $canPayable
 */
$num = fn ($v, int $dec = 2) => number_format((float) $v, $dec);
$qty = fn ($v) => \Phai\Modules\Inventory\Stock::fmt((float) $v);
$date = fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '';
$posted = (bool) $head['rc_ap_posted'];
$fields = [
    [t('common.doc_nbr'), $head['rc_nbr'], 'col-6 col-md-2'],
    [t('purchase.po'), $head['rc_po_nbr'], 'col-6 col-md-2'],
    [t('purchase.vendor'), $head['rc_vd_code'] . ($head['vd_name'] ? ' · ' . $head['vd_name'] : ''), 'col-12 col-md-4'],
    [t('purchase.date'), $date($head['rc_date']), 'col-6 col-md-2'],
    [t('purchase.site'), $head['rc_site'] . ($head['si_desc'] ? ' · ' . $head['si_desc'] : ''), 'col-6 col-md-2'],
    [t('purchase.inv_nbr'), $head['rc_inv_nbr'], 'col-6 col-md-2'],
    [t('purchase.inv_date'), $date($head['rc_inv_date']), 'col-6 col-md-2'],
    [t('purchase.currency'), $head['rc_curr'], 'col-6 col-md-1'],
    [t('purchase.rate'), $head['rc_ex_rate'] !== null ? rtrim(rtrim((string) $head['rc_ex_rate'], '0'), '.') : '', 'col-6 col-md-1', true],
    [t('purchase.terms'), $head['rc_cr_terms'], 'col-6 col-md-2'],
    [t('accounting.payable'), $head['rc_ap_ref'], 'col-6 col-md-2'],
    [t('purchase.remarks'), $head['rc_remarks'], 'col-12'],
];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/receipts')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge <?= $posted ? 'bg-green-lt' : 'bg-yellow-lt' ?>"><?= e(t($posted ? 'purchase.rc_ap_done' : 'purchase.rc_ap_waiting')) ?></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if (!$posted && $canPayable): ?>
            <a class="btn btn-sm btn-ghost-primary" href="<?= e(url('/payables/new?' . http_build_query(['vendor' => $head['rc_vd_code'], 'receipts' => $head['rc_nbr']]))) ?>"><i class="ti ti-file-invoice me-1"></i><?= e(t('accounting.ap_title')) ?></a>
        <?php endif ?>
        <?php if (\Phai\Core\Auth\Auth::can('purchase-returns', 'add')): /* goods sent back to the vendor (ใบส่งคืนสินค้า) */ ?>
            <a class="btn btn-sm btn-ghost-danger" href="<?= e(url('/purchase-returns/new?rc=' . rawurlencode($head['rc_nbr']))) ?>"><i class="ti ti-truck-return me-1"></i><?= e(t('purchase.rt_make')) ?></a>
        <?php endif ?>
        <?php if ($canPrint): ?>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/documents/receipts/' . rawurlencode($head['rc_nbr']) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
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
                <th><?= e(t('purchase.part')) ?></th>
                <th><?= e(t('purchase.desc')) ?></th>
                <th class="text-end"><?= e(t('purchase.qty')) ?></th>
                <th><?= e(t('purchase.um')) ?></th>
                <th><?= e(t('purchase.loc')) ?></th>
                <th><?= e(t('purchase.lot')) ?></th>
                <?php $cur = $head['rc_curr'] !== \Phai\Modules\Master\Money::base() ? ' (' . $head['rc_curr'] . ')' : ''; // lines are in the PO's currency ?>
                <th class="text-end"><?= e(t('purchase.price') . $cur) ?></th>
                <th class="text-end"><?= e(t('purchase.amount') . $cur) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td class="text-secondary"><?= (int) $l['rcd_line'] ?></td>
                    <td><?= e($l['rcd_part']) ?></td>
                    <td><?= e((string) ($l['pt_desc1'] ?? '')) ?></td>
                    <td class="num"><?= e($qty($l['rcd_qty_rcvd'])) ?></td>
                    <td class="text-secondary"><?= e((string) $l['rcd_um']) ?></td>
                    <td><?= e((string) $l['rcd_loc']) ?></td>
                    <td><?= e((string) $l['rcd_lot']) ?></td>
                    <td class="num"><?= e($num($l['rcd_price'])) ?></td>
                    <td class="num"><?= e($num($l['rcd_line_amt'])) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-end mt-3">
    <table class="phai-summary">
        <?php if ($cur !== ''): /* a foreign-currency receipt: its own total, the rate, then baht */ ?>
            <tr><td><?= e(t('purchase.subtotal') . $cur) ?></td><td class="num"><?= e($num(array_sum(array_column($lines, 'rcd_line_amt')))) ?></td></tr>
            <tr><td><?= e(t('purchase.rate')) ?></td><td class="num"><?= e(rtrim(rtrim((string) $head['rc_ex_rate'], '0'), '.')) ?></td></tr>
        <?php endif ?>
        <tr><td><?= e(t('purchase.subtotal') . ($cur !== '' ? ' (' . \Phai\Modules\Master\Money::base() . ')' : '')) ?></td><td class="num"><?= e($num($head['rc_net_amt'])) ?></td></tr>
        <tr><td><?= e(t('purchase.vat')) ?></td><td class="num"><?= e($num($head['rc_tax_amt'])) ?></td></tr>
        <tr class="phai-summary-grand"><td><?= e(t('purchase.total_thb')) ?></td><td class="num"><?= e($num($head['rc_tot_amt'])) ?></td></tr>
    </table>
</div>

<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'RC', 'nbr' => $head['rc_nbr']]) ?></div>
