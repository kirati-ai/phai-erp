<?php
/** ซองเอกสาร (EnvelopeController::page): waiting for my signature, sent by me, and finished ones I am in. */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText]);
$tab = fn (string $id, string $icon, string $label, bool $on = false) =>
    '<li class="nav-item"><a href="#' . $id . '" class="nav-link' . ($on ? ' active' : '') . '" data-bs-toggle="tab"><i class="ti ti-' . $icon . ' me-1"></i>' . e($label) . ' <span class="text-secondary ms-1" data-count="' . $id . '"></span></a></li>';
$tables = [
    'es-waiting' => ['nbr', 'title', 'from', 'role', 'received', 'opened'],
    'es-sent'    => ['nbr', 'title', 'state', 'progress', 'sent', 'done', 'act'],
    'es-done'    => ['nbr', 'title', 'from', 'done'],
];
?>
<!-- compact tabs outside the card, the buttons on their right (ux/ui.md: never tabs in a card header) -->
<div class="phai-toolbar">
    <ul class="nav nav-tabs phai-tabs flex-grow-1" role="tablist">
        <?= $tab('es-waiting', 'writing-sign', t('esign.tab_waiting'), true) ?>
        <?= $tab('es-sent', 'send', t('esign.tab_sent')) ?>
        <?= $tab('es-done', 'file-certificate', t('esign.tab_done')) ?>
    </ul>
    <button type="button" class="btn btn-sm" id="es-remote" hidden><i class="ti ti-world-share me-1"></i><?= e(t('esign.remote_settings')) ?></button>
    <button type="button" class="btn btn-sm btn-primary" id="es-new" hidden><i class="ti ti-plus me-1"></i><?= e(t('esign.new')) ?></button>
    <input type="file" id="es-new-files" hidden multiple accept="application/pdf,image/png,image/jpeg,image/webp">
</div>
<div class="modal fade" id="es-remote-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><?= e(t('esign.remote_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <p class="small text-secondary"><?= e(t('esign.remote_intro')) ?></p>
            <div class="alert py-2 small" id="es-rm-state"></div>
            <div id="es-rm-register" hidden>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label small" for="es-rm-url1"><?= e(t('esign.remote_url')) ?></label><input class="form-control form-control-sm" id="es-rm-url1"></div>
                    <div class="col-md-6"><label class="form-label small" for="es-rm-name"><?= e(t('esign.remote_company')) ?></label><input class="form-control form-control-sm" id="es-rm-name" maxlength="150"></div>
                    <div class="col-md-6"><label class="form-label small" for="es-rm-contact"><?= e(t('esign.remote_contact')) ?></label><input class="form-control form-control-sm" id="es-rm-contact" type="email" maxlength="150"></div>
                    <div class="col-md-6"><label class="form-label small" for="es-rm-code"><?= e(t('esign.remote_code')) ?></label><input class="form-control form-control-sm" id="es-rm-code" autocomplete="off"></div>
                </div>
                <button type="button" class="btn btn-sm btn-primary mt-2" id="es-rm-do-register"><i class="ti ti-plug-connected me-1"></i><?= e(t('esign.remote_register')) ?></button>
            </div>
            <div id="es-rm-settings" hidden>
                <label class="form-check form-switch"><input class="form-check-input" type="checkbox" id="es-rm-on"><span class="form-check-label"><?= e(t('esign.remote_on')) ?></span></label>
                <div class="row g-2">
                    <div class="col-md-8"><label class="form-label small" for="es-rm-url"><?= e(t('esign.remote_url')) ?></label><input class="form-control form-control-sm" id="es-rm-url"></div>
                    <div class="col-md-4"><label class="form-label small" for="es-rm-days"><?= e(t('esign.remote_days')) ?></label><input class="form-control form-control-sm" id="es-rm-days" type="number" min="1" max="60"></div>
                </div>
                <label class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" id="es-rm-witness"><span class="form-check-label small"><?= e(t('esign.remote_witness')) ?></span></label>
                <div class="small text-secondary" id="es-rm-last"></div>
            </div>
        </div>
        <div class="modal-footer" id="es-rm-foot" hidden>
            <button type="button" class="btn btn-sm" id="es-rm-ping"><i class="ti ti-plug me-1"></i><?= e(t('esign.remote_ping')) ?></button>
            <button type="button" class="btn btn-sm" id="es-rm-sync"><i class="ti ti-refresh me-1"></i><?= e(t('esign.remote_sync_now')) ?></button>
            <span class="es-grow"></span>
            <button type="button" class="btn btn-sm btn-primary" id="es-rm-save"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    </div></div>
</div>
<div class="card">
    <div class="tab-content">
        <?php foreach ($tables as $id => $cols): ?>
        <div class="tab-pane<?= $id === 'es-waiting' ? ' active show' : '' ?>" id="<?= $id ?>">
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table table-hover es-rows">
                    <thead><tr><?php foreach ($cols as $c): ?><th<?= $c === 'act' ? ' class="text-end"' : '' ?>><?= $c === 'act' ? '' : e(t("esign.col_$c")) ?></th><?php endforeach ?></tr></thead>
                    <tbody><tr><td colspan="<?= count($cols) ?>" class="text-secondary"><?= e(t('js.loading')) ?></td></tr></tbody>
                </table>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>
