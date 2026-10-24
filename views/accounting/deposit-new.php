<?php /** @var array $config so, today, banks — a new deposit (ใบรับเงินมัดจำ / ใบกำกับภาษี) on one sales order */ ?>
<form id="dp-form" novalidate autocomplete="off">
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-6 col-md-3">
                    <label class="form-label"><?= e(t('sales.so')) ?></label>
                    <input class="form-control form-control-sm" name="so" data-pick="open-sos" value="<?= e($config['so']) ?>" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label"><?= e(t('sales.date')) ?></label>
                    <input type="date" class="form-control form-control-sm" name="dp_date" value="<?= e($config['today']) ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label"><?= e(t('sales.customer')) ?></label>
                    <div class="form-control-plaintext form-control-sm" id="dp-customer">—</div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label"><?= e(t('accounting.bank')) ?></label>
                    <select class="form-select form-select-sm" name="dp_bank" required>
                        <?php foreach ($config['banks'] as $b): ?><option value="<?= e($b['bk_bank']) ?>"><?= e($b['bk_bank'] . ' · ' . $b['bk_desc']) ?></option><?php endforeach ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label"><?= e(t('deposit.amount')) ?></label>
                    <input class="form-control form-control-sm text-end" name="amount" inputmode="decimal" required>
                </div>
                <div class="col-12 col-md-9">
                    <label class="form-label"><?= e(t('sales.remarks')) ?></label>
                    <input class="form-control form-control-sm" name="dp_remarks" maxlength="255">
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    </div>
</form>
<div class="d-flex justify-content-end">
    <table class="phai-summary">
        <tr><td><?= e(t('deposit.so_total')) ?></td><td class="num" id="dp-so-total"></td></tr>
        <tr><td><?= e(t('deposit.taken')) ?></td><td class="num" id="dp-taken"></td></tr>
        <tr><td><?= e(t('sales.subtotal')) ?></td><td class="num" id="dp-net"></td></tr>
        <tr><td><?= e(t('sales.vat')) ?> <span id="dp-pct"></span></td><td class="num" id="dp-tax"></td></tr>
        <tr class="phai-summary-grand"><td><?= e(t('deposit.amount')) ?></td><td class="num" id="dp-total"></td></tr>
    </table>
</div>
