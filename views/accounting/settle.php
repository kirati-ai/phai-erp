<?php
/**
 * Receipt from a customer / payment to a vendor against open items. Driven by public/js/pages/settle.js.
 * @var array $config  nbr, api, page, party (customer|vendor), status, banks, whtCodes?, canPrint, canCancel, today
 */
$pay = $config['party'] === 'vendor';
$p = $pay ? 'vo' : 'cr';
$cfg = $config + ['text' => [
    'confirm' => t('purchase.cancel_confirm'),
    'none'    => t('accounting.no_open_items'),
    'full'    => t('accounting.full'),
]];
?>
<form id="settle" novalidate autocomplete="off">
    <div class="phai-toolbar phai-sticky">
<a class="btn btn-sm" href="<?= e(url($config['page'])) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
        <span class="badge d-none" id="st-status"></span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-ghost-danger" id="st-cancel" hidden><i class="ti ti-ban me-1"></i><?= e(t('purchase.cancel_doc')) ?></button>
            <a class="btn btn-sm" id="st-wht" target="_blank" hidden><i class="ti ti-receipt-tax me-1"></i><?= e(t('accounting.wht_cert')) ?></a>
            <a class="btn btn-sm btn-ghost-primary" id="st-xml" hidden><i class="ti ti-file-code me-1"></i><?= e(t('etax.xml')) ?></a>
            <a class="btn btn-sm" id="st-print" target="_blank" hidden><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?> / PDF</a>
            <button type="submit" class="btn btn-sm btn-primary" id="st-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-3">
            <div class="row g-2">
                <div class="col-sm-6 col-lg-1">
                    <label class="form-label" for="st_doc_nbr">
                        <?= e(t('common.doc_nbr')) ?>
                        <?php if (empty($config['nbr'])): ?>
                            <span class="text-secondary fw-normal small">(<?= e(t('common.editable_hint')) ?>)</span>
                        <?php endif ?>
                    </label>
                    <input type="text" class="form-control form-control-sm text-uppercase phai-doc-nbr-input" id="st_doc_nbr" name="doc_nbr"
                        value="<?= e($config['nbr'] ?? \Phai\Core\Numbering::peek($pay ? 'PV' : 'CR')) ?>"
                        autocomplete="off"
                        <?= empty($config['nbr']) ? 'title="' . e(t('common.editable_hint')) . '" placeholder="' . e(t('common.editable_hint')) . '"' : 'readonly data-locked' ?>>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label required" for="party"><?= e(t($pay ? 'purchase.vendor' : 'sales.customer')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="party" name="<?= $pay ? 'vendor' : 'customer' ?>" data-pick="party" autocomplete="off">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label required" for="date"><?= e(t('accounting.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="date" name="<?= $p ?>_date">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label required" for="bank"><?= e(t('accounting.bank')) ?></label>
                    <select class="form-select form-select-sm" id="bank" name="<?= $p ?>_bank">
                        <?php foreach ($config['banks'] as $o): ?>
                            <option value="<?= e($o['value']) ?>"><?= e($o['label']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="remarks"><?= e(t('sales.remarks')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="remarks" name="<?= $p ?>_remarks">
                </div>
                <?php if ($pay): ?>
                    <div class="col-sm-4 col-lg-1">
                        <label class="form-label" for="wht_form"><?= e(t('accounting.wht_form')) ?></label>
                        <select class="form-select form-select-sm" id="wht_form" name="vo_wht_form">
                            <option value="PND53"><?= e(t('accounting.form_PND53')) ?></option>
                            <option value="PND3"><?= e(t('accounting.form_PND3')) ?></option>
                        </select>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="st-lines">
                <thead>
                <tr>
                    <th><?= e(t('accounting.doc')) ?></th>
                    <th><?= e(t('accounting.date')) ?></th>
                    <th><?= e(t('sales.due_date')) ?></th>
                    <th class="text-end"><?= e(t('accounting.amount')) ?></th>
                    <th class="text-end"><?= e(t('sales.balance')) ?></th>
                    <th class="text-end" style="width:9rem"><?= e(t($pay ? 'accounting.pay_amt' : 'accounting.received')) ?></th>
                    <?php if (!$pay): ?>
                        <th class="text-end" style="width:8rem"><?= e(t('accounting.wht_withheld')) ?></th>
                    <?php endif ?>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <input type="hidden" name="lines">
    </div>

    <?php if ($pay): ?>
        <!-- tax withheld: one row per rate (e.g. services 3% and transport 1%), all on one 50 ทวิ -->
        <div class="card mt-3" id="st-wht-card">
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table mb-0" id="st-wht-rows">
                    <thead>
                    <tr>
                        <th><?= e(t('accounting.wht_code')) ?></th>
                        <th class="text-end" style="width:9rem"><?= e(t('accounting.wht_base')) ?></th>
                        <th class="text-end" style="width:5rem"><?= e(t('accounting.wht_rate')) ?></th>
                        <th class="text-end" style="width:9rem"><?= e(t('accounting.wht_amt')) ?></th>
                        <th style="width:2rem"></th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="px-2 py-1" id="st-wht-add-row">
                <button type="button" class="btn btn-sm btn-ghost-primary" id="st-wht-add"><i class="ti ti-plus me-1"></i><?= e(t('accounting.wht_add')) ?></button>
            </div>
        </div>
    <?php endif ?>

    <!-- the summary box, the same on every document (payables, vouchers, payments, receipts) -->
    <div class="d-flex justify-content-end mt-3">
        <table class="phai-summary">
            <!-- total (bold) → withholding (minus) → what actually moves through the bank (the highlighted bottom line) -->
            <?php if ($pay): ?>
                <tr class="fw-semibold"><td><?= e(t('sales.total')) ?></td><td class="num" id="st-total"></td></tr>
                <tr><td><?= e(t('accounting.wht_amt')) ?></td><td class="num" id="st-wht-total"></td></tr>
                <tr class="phai-summary-grand"><td><?= e(t('accounting.net_to_pay')) ?></td><td class="num" id="st-net"></td></tr>
            <?php else: ?>
                <tr class="fw-semibold"><td><?= e(t('accounting.settled')) ?></td><td class="num" id="st-settled"></td></tr>
                <tr><td><?= e(t('accounting.wht_withheld')) ?></td><td class="num" id="st-wht-total"></td></tr>
                <tr class="phai-summary-grand"><td><?= e(t('accounting.received')) ?></td><td class="num" id="st-total"></td></tr>
            <?php endif ?>
        </table>
    </div>
</form>
<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => $pay ? 'PV' : 'CR', 'nbr' => $config['nbr'] ?? '']) ?></div>

<script id="settle-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
