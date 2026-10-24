<?php
/**
 * Landed costs of one receipt, as a view like a purchase order (ux/ui.md "View pages"):
 * Back + status, Allocate (costs waiting), Print, Edit; receipt head as plain values; the costs
 * (plain values, inputs on Edit — allocated ones stay locked) and each line's share below.
 * @var array $head   ReceiptController::head() row
 * @var array $config types {CODE: text}, canEdit, canAllocate, rc
 * @var bool  $canPrint
 */
$date = fn ($v) => $v ? date('d/m/Y', strtotime((string) $v)) : '';
$cfg = $config + ['text' => [
    'confirm'   => t('purchase.lc_allocate_confirm'),
    'done'      => t('purchase.lc_done', ['n' => ':n']),
    'allocated' => t('purchase.lc_allocated'),
    'waiting'   => t('purchase.lc_waiting'),
    'none'      => t('purchase.lc_none'),
    'billed'    => t('purchase.lc_billed_badge'),
    'remove'    => t('common.delete'),
]];
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url('/landed-costs')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="badge" id="lc-status"></span>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-sm btn-ghost-primary" id="lc-allocate" hidden><i class="ti ti-arrows-split me-1"></i><?= e(t('purchase.lc_allocate')) ?></button>
        <?php if ($canPrint): ?>
            <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url('/documents/landed-costs/' . rawurlencode($head['rc_nbr']) . '/print')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
        <?php endif ?>
        <button type="button" class="btn btn-sm btn-primary" id="lc-edit" hidden><i class="ti ti-pencil me-1"></i><?= e(t('common.edit')) ?></button>
        <button type="button" class="btn btn-sm" id="lc-undo" hidden><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
        <button type="submit" form="lc-form" class="btn btn-sm btn-primary" id="lc-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
    </div>
</div>

<?= \Phai\Core\View\View::partial('doc/view-fields', ['fields' => [
    [t('purchase.rc_title'), $head['rc_nbr'], 'col-6 col-md-2'],
    [t('purchase.date'), $date($head['rc_date']), 'col-6 col-md-2'],
    [t('purchase.po'), $head['rc_po_nbr'], 'col-6 col-md-2'],
    [t('purchase.vendor'), $head['rc_vd_code'] . ($head['vd_name'] ? ' · ' . $head['vd_name'] : ''), 'col-12 col-md-4'],
    [t('purchase.lc_value'), number_format((float) $head['rc_net_amt'], 2), 'col-6 col-md-2', true],
]]) ?>

<form class="card mb-3" id="lc-form" novalidate autocomplete="off">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0">
            <thead>
            <tr>
                <th style="width: 2.5rem">#</th>
                <th style="width: 15rem"><?= e(t('purchase.lc_type')) ?></th>
                <th style="width: 10rem"><?= e(t('purchase.lc_ref')) ?></th>
                <th style="width: 14rem"><?= e(t('purchase.lc_vendor')) ?></th>
                <th class="text-end" style="width: 10rem"><?= e(t('purchase.lc_amount')) ?></th>
                <th><?= e(t('purchase.lc_ap_ref')) ?></th>
                <th style="width: 2.5rem"></th>
            </tr>
            </thead>
            <tbody id="lc-costs"></tbody>
        </table>
    </div>
    <div class="card-footer py-2" id="lc-add-row" hidden>
        <button type="button" class="btn btn-sm btn-ghost-primary" id="lc-add"><i class="ti ti-plus me-1"></i><?= e(t('purchase.lc_add')) ?></button>
    </div>
</form>

<div class="d-flex justify-content-end mb-3">
    <table class="phai-summary">
        <tr class="phai-summary-grand"><td><?= e(t('purchase.lc_total')) ?></td><td class="num" id="lc-sum"></td></tr>
    </table>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0">
            <thead>
            <tr>
                <th><?= e(t('purchase.part')) ?></th>
                <th><?= e(t('purchase.desc')) ?></th>
                <th class="text-end"><?= e(t('purchase.qty')) ?></th>
                <th class="text-end"><?= e(t('purchase.lc_value')) ?></th>
                <th class="text-end"><?= e(t('purchase.lc_share')) ?></th>
            </tr>
            </thead>
            <tbody id="lc-lines"></tbody>
        </table>
    </div>
</div>

<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'LC', 'nbr' => $head['rc_nbr']]) ?></div>

<script id="lc-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
