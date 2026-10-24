<?php
/**
 * Monthly withholding tax attachment screen (public/js/pages/wht-report.js).
 * @var string $month YYYY-MM
 */
?>
<div class="phai-toolbar" id="pnd">
    <select class="form-select form-select-sm w-auto" id="form" aria-label="<?= e(t('accounting.wht_form')) ?>">
        <option value="PND53"><?= e(t('accounting.form_PND53')) ?></option>
        <option value="PND3"><?= e(t('accounting.form_PND3')) ?></option>
    </select>
    <label class="text-secondary small mb-0" for="month"><?= e(t('accounting.vat_month')) ?></label>
    <input type="month" class="form-control form-control-sm w-auto" id="month" value="<?= e($month) ?>">
    <a class="btn btn-sm ms-auto" id="print" target="_blank" rel="noopener"><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?></a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table" id="pnd-table">
            <thead>
            <tr>
                <th class="text-end">#</th>
                <th><?= e(t('accounting.vat_tax_id')) ?></th>
                <th><?= e(t('accounting.vat_branch')) ?></th>
                <th><?= e(t('accounting.pnd_payee')) ?></th>
                <th><?= e(t('accounting.pnd_paid_date')) ?></th>
                <th><?= e(t('accounting.income_type')) ?></th>
                <th class="text-end"><?= e(t('accounting.pnd_rate')) ?></th>
                <th class="text-end"><?= e(t('accounting.pnd_paid')) ?></th>
                <th class="text-end"><?= e(t('accounting.wht_amt')) ?></th>
                <th><?= e(t('accounting.wht_cert')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
            <tfoot class="fw-semibold"></tfoot>
        </table>
    </div>
</div>

<?php /* the filed return of the form and month on screen (wht-report.js) */ ?>
<div class="mt-3"><?= \Phai\Core\View\View::partial('partials/files', ['doc' => 'WR', 'nbr' => '']) ?></div>
