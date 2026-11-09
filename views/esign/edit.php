<?php
/** ตัวแก้ซองเอกสาร (EnvelopeController::editor): files, signers in steps, fields on the pages, then send. js/pages/envelope-edit.js */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText, 'payload' => $payload]);
?>
<div class="es-bar">
    <input type="text" class="form-control form-control-sm" id="es-title" maxlength="255" aria-label="<?= e(t('esign.col_title')) ?>">
    <span class="badge bg-secondary-lt" id="es-nbr"></span>
    <span class="small text-secondary" id="es-saved"></span>
    <span class="es-grow"></span>
    <div class="btn-group btn-group-sm">
        <button type="button" class="btn" data-zoom="-1" title="<?= e(t('esign.zoom_out')) ?>"><i class="ti ti-zoom-out"></i></button>
        <button type="button" class="btn" data-zoom="0" title="<?= e(t('esign.zoom_fit')) ?>"><i class="ti ti-arrows-horizontal"></i></button>
        <button type="button" class="btn" data-zoom="1" title="<?= e(t('esign.zoom_in')) ?>"><i class="ti ti-zoom-in"></i></button>
    </div>
    <button type="button" class="btn btn-sm" id="es-undo" title="<?= e(t('esign.undo')) ?> (Ctrl+Z)"><i class="ti ti-arrow-back-up"></i></button>
    <button type="button" class="btn btn-sm" id="es-redo" title="<?= e(t('esign.redo')) ?> (Ctrl+Y)"><i class="ti ti-arrow-forward-up"></i></button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="es-delete" data-cancel><i class="ti ti-trash me-1"></i><?= e(t('esign.delete_draft')) ?></button>
    <button type="button" class="btn btn-sm btn-primary" id="es-send"><i class="ti ti-send me-1"></i><?= e(t('esign.send')) ?></button>
</div>
<div class="es-wrap">
    <div class="es-side es-left">
        <div class="card"><div class="card-body">
            <h4><?= e(t('esign.files')) ?></h4>
            <div id="es-files"></div>
            <div class="d-flex gap-1 mt-2">
                <label class="btn btn-sm flex-fill"><i class="ti ti-file-upload me-1"></i><?= e(t('esign.add_main')) ?><input type="file" hidden multiple accept="application/pdf,image/png,image/jpeg,image/webp" data-kind="M"></label>
                <label class="btn btn-sm flex-fill"><i class="ti ti-paperclip me-1"></i><?= e(t('esign.add_attach')) ?><input type="file" hidden multiple accept="application/pdf,image/png,image/jpeg,image/webp" data-kind="A"></label>
            </div>
        </div></div>
        <div class="card"><div class="card-body">
            <h4><?= e(t('esign.signers')) ?></h4>
            <div id="es-signers"></div>
            <div class="d-flex gap-1 mt-2">
                <button type="button" class="btn btn-sm flex-fill" id="es-add-signer"><i class="ti ti-user-plus me-1"></i><?= e(t('esign.add_signer')) ?></button>
                <button type="button" class="btn btn-sm" id="es-add-outside" title="<?= e(t('esign.outside_hint')) ?>"><i class="ti ti-mail me-1"></i><?= e(t('esign.add_outside')) ?></button>
            </div>
        </div></div>
        <div class="card"><div class="card-body">
            <h4><?= e(t('esign.note')) ?></h4>
            <textarea class="form-control form-control-sm" id="es-note" rows="3" maxlength="2000"></textarea>
        </div></div>
    </div>
    <div class="es-main">
        <div class="es-pages" id="es-pages"><div class="text-secondary small p-4" id="es-empty"><?= e(t('esign.empty_pages')) ?></div></div>
    </div>
    <div class="es-side es-right">
        <div class="card"><div class="card-body">
            <h4><?= e(t('esign.tools_signer')) ?> <span class="es-dot ms-1" id="es-active-dot"></span> <span class="small" id="es-active-name"></span></h4>
            <div class="es-tools" id="es-tools-signer"></div>
            <h4 class="mt-2"><?= e(t('esign.tools_sender')) ?></h4>
            <div class="es-tools" id="es-tools-sender"></div>
            <div class="form-check form-switch mt-2 mb-0 small">
                <input class="form-check-input" type="checkbox" id="es-snap" checked><label class="form-check-label" for="es-snap"><?= e(t('esign.snap')) ?></label>
            </div>
            <div class="mt-2">
                <label class="form-label small mb-1" for="es-view"><?= e(t('esign.view_as')) ?></label>
                <select class="form-select form-select-sm" id="es-view"></select>
            </div>
        </div></div>
        <div class="card"><div class="card-body es-props" id="es-props"></div></div>
        <div class="card"><div class="card-body">
            <h4><?= e(t('esign.check')) ?></h4>
            <ul class="es-problems ps-3 mb-0" id="es-problems"></ul>
        </div></div>
    </div>
</div>
