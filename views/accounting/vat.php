<?php
/**
 * Monthly VAT report screen (public/js/pages/vat.js).
 * @var string $side  sales | purchases
 * @var string $month YYYY-MM
 */
$party = $side === 'sales' ? t('accounting.vat_customer') : t('accounting.vat_vendor');
?>
<div class="phai-toolbar" id="vat" data-side="<?= e($side) ?>">
    <label class="text-secondary small mb-0" for="month"><?= e(t('accounting.vat_month')) ?></label>
    <input type="month" class="form-control form-control-sm w-auto" id="month" value="<?= e($month) ?>">
    <a class="btn btn-sm ms-auto" id="print" target="_blank" rel="noopener"><i class="ti ti-printer me-1"></i><?= e(t('sales.print')) ?></a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table" id="vat-table">
            <thead>
            <tr>
                <th class="text-end">#</th>
                <th><?= e(t('accounting.date')) ?></th>
                <th><?= e(t('accounting.vat_inv')) ?></th>
                <th><?= e($party) ?></th>
                <th><?= e(t('accounting.vat_tax_id')) ?></th>
                <th><?= e(t('accounting.vat_branch')) ?></th>
                <th class="text-end"><?= e(t('accounting.vat_net')) ?></th>
                <th class="text-end"><?= e(t('accounting.vat_tax')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
            <tfoot class="fw-semibold"></tfoot>
        </table>
    </div>
</div>
