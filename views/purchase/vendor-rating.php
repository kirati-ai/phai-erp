<?php /** @var array $config from, to — vendor evaluation (การประเมินผู้ขาย) */ ?>
<div class="phai-toolbar">
    <input type="date" class="form-control form-control-sm w-auto" id="from" value="<?= e($config['from']) ?>">
    <span class="text-secondary">–</span>
    <input type="date" class="form-control form-control-sm w-auto" id="to" value="<?= e($config['to']) ?>">
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0" id="vr">
            <thead>
            <tr>
                <th><?= e(t('purchase.vendor')) ?></th><th><?= e(t('purchase.vendor_name')) ?></th>
                <th class="text-end"><?= e(t('purchase.vr_value')) ?></th><th class="text-end"><?= e(t('purchase.vr_lines')) ?></th>
                <th class="text-end"><?= e(t('purchase.vr_on_time')) ?></th><th class="text-end"><?= e(t('purchase.vr_returned')) ?></th>
                <th class="text-end"><?= e(t('purchase.vr_lead')) ?></th><th class="text-end"><?= e(t('purchase.vr_score')) ?></th>
                <th class="text-center"><?= e(t('purchase.vr_grade')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
<script id="vr-config" type="application/json"><?= json_encode($config + ['text' => ['none' => t('purchase.vr_none')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
