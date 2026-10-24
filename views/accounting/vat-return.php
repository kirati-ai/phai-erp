<?php /** @var array $config month, canFile, canUndo, canPrint */ ?>
<div class="phai-toolbar">
    <input type="month" class="form-control form-control-sm w-auto" id="month" value="<?= e($config['month']) ?>" aria-label="<?= e(t('vatreturn.month')) ?>">
    <span class="badge" id="vr-status"></span>
    <span class="text-secondary small" id="vr-due"></span>
    <div class="ms-auto d-flex gap-2">
        <?php if ($config['canPrint']): ?>
            <a class="btn btn-sm" id="vr-print" target="_blank" rel="noopener" href="#"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
        <?php endif ?>
        <?php if ($config['canUndo']): ?>
            <button type="button" class="btn btn-sm btn-ghost-danger" id="vr-undo" hidden><i class="ti ti-arrow-back-up me-1"></i><?= e(t('vatreturn.undo')) ?></button>
        <?php endif ?>
        <?php if ($config['canFile']): ?>
            <button type="button" class="btn btn-sm btn-primary" id="vr-file" hidden><i class="ti ti-file-check me-1"></i><?= e(t('vatreturn.file')) ?></button>
        <?php endif ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="d-flex justify-content-end justify-content-lg-start">
            <table class="phai-summary">
                <tr><td><?= e(t('vatreturn.sales')) ?></td><td class="num" id="vr-sales"></td></tr>
                <tr><td><?= e(t('vatreturn.output')) ?></td><td class="num" id="vr-output"></td></tr>
                <tr><td><?= e(t('vatreturn.purchases')) ?></td><td class="num" id="vr-purchases"></td></tr>
                <tr><td><?= e(t('vatreturn.input')) ?></td><td class="num" id="vr-input"></td></tr>
                <tr><td><?= e(t('vatreturn.carried')) ?></td><td class="num" id="vr-carried"></td></tr>
                <tr class="phai-summary-grand"><td id="vr-net-label"></td><td class="num" id="vr-net"></td></tr>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header py-2"><h3 class="card-title"><?= e(t('vatreturn.history')) ?></h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table mb-0" id="vr-list">
                    <thead><tr><th><?= e(t('vatreturn.month')) ?></th><th class="text-end"><?= e(t('vatreturn.output')) ?></th>
                        <th class="text-end"><?= e(t('vatreturn.input')) ?></th><th class="text-end"><?= e(t('vatreturn.carried')) ?></th>
                        <th class="text-end"><?= e(t('vatreturn.to_pay')) ?></th><th class="phai-act"></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script id="vr-config" type="application/json"><?= json_encode($config + ['text' => [
    'to_pay' => t('vatreturn.to_pay'), 'credit' => t('vatreturn.credit'), 'due' => t('vatreturn.due'), 'filed' => t('vatreturn.filed'),
    'not_filed' => t('vatreturn.not_filed'), 'file_confirm' => t('vatreturn.file_confirm'), 'undo_confirm' => t('vatreturn.undo_confirm'),
    'filed_ok' => t('vatreturn.filed_ok'), 'undone' => t('vatreturn.undone'),
]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
