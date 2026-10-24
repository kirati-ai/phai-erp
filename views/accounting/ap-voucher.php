<?php
/**
 * Voucher (ใบสำคัญจ่าย): pick open payables of one vendor. Driven by public/js/pages/ap-voucher.js.
 * @var array $config  nbr, status, canPrint, canCancel, canPay, today
 */
$cfg = $config + ['text' => [
    'confirm' => t('purchase.cancel_confirm'),
    'none'    => t('accounting.no_open_items'),
    'type'    => ['IN' => t('accounting.ap_type_IN'), 'CM' => t('accounting.ap_type_CM')],
    'whtEst'  => t('accounting.wht_est'),
    'netToPay' => t('accounting.net_to_pay'),
]];
?>
<form id="apv" novalidate autocomplete="off">
    <div class="phai-toolbar phai-sticky">
<a class="btn btn-sm" href="<?= e(url('/ap-vouchers')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
        <span class="badge d-none" id="apv-status"></span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-ghost-primary" id="apv-pay" hidden><i class="ti ti-cash me-1"></i><?= e(t('accounting.apv_pay')) ?></a>
            <button type="button" class="btn btn-sm btn-ghost-danger" id="apv-cancel" hidden><i class="ti ti-ban me-1"></i><?= e(t('purchase.cancel_doc')) ?></button>
            <a class="btn btn-sm" id="apv-print" target="_blank" hidden><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?> / PDF</a>
            <button type="submit" class="btn btn-sm btn-primary" id="apv-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-3">
            <div class="row g-2">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label required" for="vendor"><?= e(t('purchase.vendor')) ?></label>
                    <input type="text" class="form-control form-control-sm text-uppercase" id="vendor" name="vendor" data-pick="vendors" autocomplete="off">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label required" for="apv_date"><?= e(t('accounting.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="apv_date" name="apv_date">
                </div>
                <div class="col-sm-3 col-lg-1">
                    <label class="form-label" for="apv_pay_date"><?= e(t('accounting.apv_pay_date')) ?></label>
                    <input type="date" class="form-control form-control-sm" id="apv_pay_date" name="apv_pay_date">
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="apv_remarks"><?= e(t('sales.remarks')) ?></label>
                    <input type="text" class="form-control form-control-sm" id="apv_remarks" name="apv_remarks">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2"><h3 class="card-title"><?= e(t('accounting.apv_payables')) ?></h3></div>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="apv-lines">
                <thead>
                <tr>
                    <th class="w-1"><input type="checkbox" class="form-check-input m-0" id="apv-all"></th>
                    <th><?= e(t('accounting.nbr')) ?></th>
                    <th><?= e(t('accounting.ap_type')) ?></th>
                    <th><?= e(t('accounting.vendor_inv')) ?></th>
                    <th><?= e(t('accounting.date')) ?></th>
                    <th><?= e(t('sales.due_date')) ?></th>
                    <th><?= e(t('accounting.ap_acct')) ?></th>
                    <th class="text-end"><?= e(t('accounting.amount')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</form>

<div class="d-flex justify-content-end mt-3">
    <table class="phai-summary" id="apv-foot">
        <tr class="phai-summary-grand" id="apv-total-row"><td><?= e(t('sales.total')) ?></td><td class="num" id="apv-total">0.00</td></tr>
    </table>
</div>

<script id="apv-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
