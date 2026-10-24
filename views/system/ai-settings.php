<?php /** @var array $config  providers {code: default model} */ ?>
<form id="ai-settings" class="card mb-3" novalidate autocomplete="off">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-12">
                <label class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" name="enabled">
                    <span class="form-check-label"><?= e(t('ai.enabled')) ?></span>
                </label>
            </div>
            <div class="col-sm-4 col-lg-2">
                <label class="form-label" for="provider"><?= e(t('ai.provider')) ?></label>
                <select class="form-select form-select-sm" id="provider" name="provider">
                    <?php foreach (array_keys($config['providers']) as $p): ?>
                        <option value="<?= e($p) ?>"><?= e(t("ai.provider_$p")) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-sm-8 col-lg-2">
                <label class="form-label" for="model"><?= e(t('ai.model')) ?></label>
                <input type="text" class="form-control form-control-sm" id="model" name="model" maxlength="100">
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="api_key"><?= e(t('ai.api_key')) ?></label>
                <input type="password" class="form-control form-control-sm" id="api_key" name="api_key" autocomplete="new-password">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label" for="price_in"><?= e(t('ai.price_in')) ?></label>
                <input type="text" class="form-control form-control-sm text-end" id="price_in" name="price_in" inputmode="decimal">
            </div>
            <div class="col-sm-6 col-lg-2">
                <label class="form-label" for="price_out"><?= e(t('ai.price_out')) ?></label>
                <input type="text" class="form-control form-control-sm text-end" id="price_out" name="price_out" inputmode="decimal">
            </div>
        </div>
        <div class="text-danger small mt-2 d-none" id="not-writable"><?= e(t('error.config_write')) ?></div>
    </div>
    <div class="card-footer text-end py-2">
        <button type="submit" class="btn btn-sm btn-primary"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
    </div>
</form>

<div class="card">
    <div class="card-header py-2"><h3 class="card-title"><?= e(t('ai.usage')) ?></h3></div>
    <div class="table-responsive">
        <table class="table table-sm table-vcenter card-table mb-0" id="ai-usage">
            <thead>
            <tr>
                <th><?= e(t('ai.user')) ?></th>
                <th class="text-end"><?= e(t('ai.month_questions')) ?></th>
                <th class="text-end"><?= e(t('ai.month_cost')) ?></th>
                <th class="text-end"><?= e(t('ai.questions')) ?></th>
                <th class="text-end"><?= e(t('ai.tokens')) ?></th>
                <th class="text-end"><?= e(t('ai.cost')) ?></th>
                <th><?= e(t('ai.last_at')) ?></th>
            </tr>
            </thead>
            <tbody></tbody>
            <tfoot class="fw-semibold"></tfoot>
        </table>
    </div>
</div>
<script id="ai-settings-config" type="application/json"><?= json_encode($config + ['text' => [
    'key_saved' => t('ai.key_saved', ['hint' => ':hint']),
    'empty'     => t('ai.no_usage'),
    'total'     => t('sales.total'),
]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
