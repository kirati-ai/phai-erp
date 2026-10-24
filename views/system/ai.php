<?php /** @var array $config  enabled, canSetup */ ?>
<?php if (!$config['enabled']): ?>
    <div class="card">
        <div class="card-body text-center text-secondary py-5">
            <i class="ti ti-sparkles fs-1 d-block mb-2"></i>
            <?= e(t('ai.disabled')) ?>
            <?php if ($config['canSetup']): ?>
                <div class="mt-2"><a class="btn btn-sm" href="<?= e(url('/settings/ai')) ?>"><i class="ti ti-settings me-1"></i><?= e(t('ai.settings_title')) ?></a></div>
            <?php endif ?>
        </div>
    </div>
<?php else: ?>
    <div class="card phai-chat">
        <div class="card-body" id="ai-log"></div>
        <form id="ai-form" class="card-footer d-flex gap-2 align-items-end" novalidate autocomplete="off" data-no-cancel>
            <button type="button" class="btn btn-sm btn-ghost-secondary px-2" id="ai-new" title="<?= e(t('ai.new_chat')) ?>"><i class="ti ti-message-plus"></i></button>
            <textarea class="form-control form-control-sm" name="question" rows="1" maxlength="2000" placeholder="<?= e(t('ai.placeholder')) ?>"></textarea>
            <button type="submit" class="btn btn-sm btn-primary px-2"><i class="ti ti-send"></i></button>
        </form>
    </div>
    <script id="ai-config" type="application/json"><?= json_encode(['text' => [
        'used'  => t('ai.used'),
        'empty' => t('ai.empty'),
    ]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif ?>
