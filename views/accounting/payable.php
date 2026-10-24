<?php
/** @var array $config  nbr, status, accounts, costCenters, taxes, canCancel, today */
$cfg = $config + ['text' => [
    'confirm' => t('purchase.cancel_confirm'),
    'none'    => t('accounting.no_receipts'),
    'saved'   => t('js.saved'),
    'whtEst'  => t('accounting.wht_est'),
    'netToPay' => t('accounting.net_to_pay'),
]];
?>
<form id="payable" novalidate autocomplete="off">
    <div class="phai-toolbar phai-sticky">
<a class="btn btn-sm" href="<?= e(url('/payables')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
        <span class="badge d-none" id="ap-status"></span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-ghost-secondary" id="ap-gl" hidden><i class="ti ti-book me-1"></i><?= e(t('accounting.gl_entry')) ?></a>
            <button type="button" class="btn btn-sm btn-ghost-danger" id="ap-cancel" hidden><i class="ti ti-ban me-1"></i><?= e(t('purchase.cancel_doc')) ?></button>
            <button type="button" class="btn btn-sm" id="ap-edit" hidden><i class="ti ti-pencil me-1"></i><?= e(t('common.edit')) ?></button>
            <?php if (!empty($config['printUrl'])): ?>
                <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(url($config['printUrl'])) ?>"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
            <?php endif ?>
            <button type="button" class="btn btn-sm" id="ap-undo" hidden><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
            <button type="submit" class="btn btn-sm btn-primary" id="ap-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-3">
            <div class="row g-2">
                <div class="col-sm-6 col-lg-1">
                    <label class="form-label" for="ap_doc_nbr">
                        <?= e(t('common.doc_nbr')) ?>
                        <?php if (empty($config['nbr'])): ?>
                            <span class="text-secondary fw-normal small">(<?= e(t('common.editable_hint')) ?>)</span>
                        <?php endif ?>
                    </label>
                    <input type="text" class="form-control form-control-sm text-uppercase phai-doc-nbr-input" id="ap_doc_nbr" name="doc_nbr"
                        value="<?= e($config['nbr'] ?? \Phai\Core\Numbering::peek('AP')) ?>"
                        autocomplete="off"
                        <?= empty($config['nbr']) ? 'title="' . e(t('common.editable_hint')) . '" placeholder="' . e(t('common.editable_hint')) . '"' : 'readonly data-locked' ?>>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label required" for="vendor"><?= e(t('purchase.vendor')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="vendor" name="vendor" data-pick="vendors" autocomplete="off">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label" for="ap_type"><?= e(t('accounting.ap_type')) ?></label>
                    <select class="form-select form-select-sm" id="ap_type" name="ap_type">
                        <option value="IN"><?= e(t('accounting.ap_type_IN')) ?></option>
                        <option value="CM"><?= e(t('accounting.ap_type_CM')) ?></option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="ap_acct"><?= e(t('accounting.ap_acct')) ?></label>
                    <select class="form-select form-select-sm" id="ap_acct" name="ap_acct">
                        <?php foreach ($config['accounts'] as $o): ?>
                            <option value="<?= e($o['value']) ?>"><?= e($o['label']) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-sm-3 col-lg-1" id="ref-col" hidden>
                    <label class="form-label required" for="ap_ref_ap"><?= e(t('accounting.ap_ref_ap')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="ap_ref_ap" name="ap_ref_ap" data-pick="ap-refs" autocomplete="off">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label required" for="ap_inv_nbr"><?= e(t('accounting.vendor_inv')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="ap_inv_nbr" name="ap_inv_nbr">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label required" for="ap_date"><?= e(t('accounting.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="ap_date" name="ap_date">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label" for="ap_due_date"><?= e(t('sales.due_date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="ap_due_date" name="ap_due_date">
                </div>
                <div class="col-sm-9 col-lg-1">
                    <label class="form-label" for="ap_remarks"><?= e(t('sales.remarks')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="ap_remarks" name="ap_remarks">
                </div>
            </div>
        </div>
    </div>

    <!-- new: goods received waiting for this invoice -->
    <div class="card mb-3" id="rc-card">
        <div class="card-header py-2"><h3 class="card-title"><?= e(t('accounting.receipts')) ?></h3></div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="rc-lines">
                <thead>
                <tr>
                    <th class="w-1"></th>
                    <th><?= e(t('purchase.rc_title')) ?></th>
                    <th><?= e(t('accounting.date')) ?></th>
                    <th><?= e(t('purchase.po')) ?></th>
                    <th class="text-end"><?= e(t('accounting.net')) ?></th>
                    <th class="text-end"><?= e(t('accounting.vat')) ?></th>
                    <th class="text-end"><?= e(t('sales.total')) ?></th>
                    <th style="width:12rem"><?= e(t('accounting.wht_line')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <input type="hidden" name="receipts">
    </div>

    <!-- new: other charges -->
    <div class="card mb-3" id="ex-card">
        <div class="card-header py-2">
            <h3 class="card-title"><?= e(t('accounting.expenses')) ?></h3>
            <div class="ms-auto d-flex gap-2 align-items-center">
                <label class="small text-secondary" for="tax_code"><?= e(t('accounting.vat')) ?></label>
                <select class="form-select form-select-sm w-auto" id="tax_code" name="tax_code">
                    <option value="" data-pct="0">—</option>
                    <?php foreach ($config['taxes'] as $o): ?>
                        <option value="<?= e($o['value']) ?>" data-pct="<?= e($o['pct']) ?>"><?= e($o['label']) ?></option>
                    <?php endforeach ?>
                </select>
                <button type="button" class="btn btn-sm" id="ex-add"><i class="ti ti-plus me-1"></i><?= e(t('accounting.add_line')) ?></button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="ex-lines">
                <thead>
                <tr>
                    <th style="min-width:14rem"><?= e(t('accounting.account')) ?></th>
                    <th style="min-width:8rem"><?= e(t('accounting.cc')) ?></th>
                    <th><?= e(t('accounting.desc')) ?></th>
                    <th class="text-end" style="width:9rem"><?= e(t('accounting.amount')) ?></th>
                    <th style="width:12rem"><?= e(t('accounting.wht_line')) ?></th>
                    <th class="w-1"></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <input type="hidden" name="lines">
    </div>

    <!-- saved: the lines as booked -->
    <div class="card mb-3 d-none" id="ap-card">
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="ap-lines">
                <thead>
                <tr>
                    <th><?= e(t('accounting.account')) ?></th>
                    <th><?= e(t('accounting.desc')) ?></th>
                    <th><?= e(t('accounting.cc')) ?></th>
                    <th class="text-end"><?= e(t('accounting.amount')) ?></th>
                    <th style="width:12rem"><?= e(t('accounting.wht_line')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end">
        <table class="phai-summary">
            <tr><td class="text-secondary"><?= e(t('accounting.net')) ?></td><td class="num" id="t-net"></td></tr>
            <tr><td class="text-secondary"><?= e(t('accounting.vat')) ?></td><td class="num" id="t-vat"></td></tr>
            <tr class="phai-summary-grand" id="t-total-row"><td><?= e(t('sales.total')) ?></td><td class="num" id="t-total"></td></tr>
            <tbody id="t-wht"></tbody>
            <tr class="d-none" id="t-paid-row"><td class="text-secondary"><?= e(t('sales.balance')) ?></td><td class="num" id="t-paid"></td></tr>
        </table>
    </div>
</form>
<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'AP', 'nbr' => $config['nbr'] ?? '']) ?></div>

<script id="payable-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
