<?php /** @var array $config sh, today — a new sales return (ใบรับคืนสินค้า) from one shipment */ ?>
<form id="sr-form" novalidate autocomplete="off">
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <label class="form-label"><?= e(t('sales.shipment')) ?></label>
                    <input class="form-control form-control-sm" name="sh" data-pick="returnable-shs" value="<?= e($config['sh']) ?>" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label"><?= e(t('sales.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" name="sr_date" value="<?= e($config['today']) ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label"><?= e(t('sales.customer')) ?></label>
                    <div class="form-control-plaintext form-control-sm" id="sr-customer">—</div>
                </div>
                <div class="col-12">
                    <label class="form-label"><?= e(t('sales.sr_reason')) ?></label>
                    <input class="form-control form-control-sm" name="sr_reason" maxlength="255" required>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0" id="sr-lines">
                <thead>
                <tr>
                    <th>#</th><th><?= e(t('purchase.part')) ?></th><th><?= e(t('purchase.desc')) ?></th>
                    <th><?= e(t('purchase.loc')) ?></th><th><?= e(t('purchase.lot')) ?></th>
                    <th class="text-end"><?= e(t('sales.sr_returnable')) ?></th><th class="text-end"><?= e(t('stock.unit_cost')) ?></th>
                    <th class="text-end" style="width: 8rem"><?= e(t('sales.sr_qty')) ?></th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    </div>
</form>
<script id="sr-config" type="application/json"><?= json_encode($config + ['text' => ['none' => t('sales.sr_none')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
