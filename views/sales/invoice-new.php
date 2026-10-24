<div class="phai-toolbar">
    <a class="btn btn-sm" href="<?= e(url('/invoices')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <div class="input-icon phai-search">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table" id="to-invoice">
            <thead>
            <tr>
                <th><?= e(t('sales.shipment')) ?></th>
                <th><?= e(t('sales.date')) ?></th>
                <th><?= e(t('sales.so')) ?></th>
                <th><?= e(t('sales.customer')) ?></th>
                <th><?= e(t('sales.customer_name')) ?></th>
                <th class="w-1"></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script id="invoice-new-text" type="application/json"><?= json_encode([
    'make'  => t('sales.make_invoice'),
    'empty' => t('sales.nothing_to_invoice'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
