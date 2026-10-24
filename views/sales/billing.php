<?php
/** @var array $config  nbr, status, canPrint, canCancel, today, base */
$cfg = $config + ['text' => [
    'confirm' => t('purchase.cancel_confirm'),
    'saved'   => t('purchase.saved_nbr', ['nbr' => ':nbr']),
    'none'    => t('sales.no_open_invoices'),
]];
?>
<form id="billing" novalidate autocomplete="off">
    <div class="phai-toolbar phai-sticky">
        <a class="btn btn-sm" href="<?= e(url('/billings')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
        <span class="badge d-none" id="bl-status"></span>
        <div class="ms-auto d-flex gap-2">
            <a class="btn btn-sm" id="bl-print" target="_blank" hidden><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?></a>
            <button type="button" class="btn btn-sm btn-ghost-danger" id="bl-cancel" hidden><i class="ti ti-ban me-1"></i><?= e(t('purchase.cancel_doc')) ?></button>
            <button type="submit" class="btn btn-sm btn-primary" id="bl-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-3">
            <div class="row g-2">
                <div class="col-sm-6 col-lg-1">
                    <label class="form-label" for="bl_doc_nbr">
                        <?= e(t('common.doc_nbr')) ?>
                        <?php if (empty($config['nbr'])): ?>
                            <span class="text-secondary fw-normal small">(<?= e(t('common.editable_hint')) ?>)</span>
                        <?php endif ?>
                    </label>
                    <input type="text" class="form-control form-control-sm text-uppercase phai-doc-nbr-input" id="bl_doc_nbr" name="doc_nbr"
                        value="<?= e($config['nbr'] ?? \Phai\Core\Numbering::peek('BL')) ?>"
                        autocomplete="off"
                        <?= empty($config['nbr']) ? 'title="' . e(t('common.editable_hint')) . '" placeholder="' . e(t('common.editable_hint')) . '"' : 'readonly data-locked' ?>>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label required" for="customer"><?= e(t('sales.customer')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="customer" name="customer" data-pick="customers" autocomplete="off">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label required" for="bl_date"><?= e(t('sales.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="bl_date" name="bl_date">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label" for="bl_due_date"><?= e(t('sales.pay_date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="bl_due_date" name="bl_due_date">
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label class="form-label" for="bl_remarks"><?= e(t('sales.remarks')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="bl_remarks" name="bl_remarks">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="bl-lines">
                <thead>
                <tr>
                    <th class="w-1"><input type="checkbox" class="form-check-input m-0" id="bl-all" data-new></th>
                    <th><?= e(t('sales.invoice')) ?></th>
                    <th><?= e(t('sales.date')) ?></th>
                    <th><?= e(t('sales.due_date')) ?></th>
                    <th class="text-end"><?= e(t('sales.inv_amount')) ?></th>
                    <th class="text-end"><?= e(t('sales.balance')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <input type="hidden" name="invoices">
    </div>

    <div class="d-flex justify-content-end mt-3">
        <table class="phai-summary">
            <tr><td><?= e(t('sales.subtotal')) ?></td><td class="num" id="bl-net"></td></tr>
            <tr><td><?= e(t('sales.vat')) ?></td><td class="num" id="bl-vat"></td></tr>
            <tr class="phai-summary-grand"><td><?= e(t('sales.total')) ?></td><td class="num" id="bl-total"></td></tr>
        </table>
    </div>
</form>

<script id="billing-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
