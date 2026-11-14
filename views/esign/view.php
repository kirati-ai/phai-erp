<?php
/** หน้าซองเอกสาร (EnvelopeController::show): progress, signers with their times, the events and the final document. js/pages/envelope-view.js */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText, 'payload' => $payload]);
?>
<div class="es-top"><div class="es-bar">
    <span class="badge bg-secondary-lt" id="es-nbr"></span>
    <span class="badge" id="es-status"></span>
    <span class="es-grow"></span>
    <a class="btn btn-sm btn-primary" id="es-sign" hidden><i class="ti ti-writing-sign me-1"></i><?= e(t('esign.go_sign')) ?></a>
    <a class="btn btn-sm btn-primary" id="es-final" target="_blank" rel="noopener" hidden><i class="ti ti-file-certificate me-1"></i><?= e(t('esign.final')) ?></a>
    <a class="btn btn-sm" id="es-download" hidden><i class="ti ti-download me-1"></i><?= e(t('esign.download')) ?></a>
    <button type="button" class="btn btn-sm" id="es-remind" hidden><i class="ti ti-bell me-1"></i><?= e(t('esign.remind')) ?></button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="es-recall" hidden data-cancel><i class="ti ti-arrow-back me-1"></i><?= e(t('esign.recall')) ?></button>
    <button type="button" class="btn btn-sm btn-primary" id="es-reopen" hidden><i class="ti ti-pencil me-1"></i><?= e(t('esign.act_reopen')) ?></button>
</div></div>
<!-- the signers and the final document on the left, files and history on the right; the bar between them is
     dragged to change the width (js/esign-split.js), and each side scrolls on its own -->
<div class="es-split">
    <div class="es-doc-pane es-col">
        <div class="card">
            <div class="card-header py-2"><h3 class="card-title"><i class="ti ti-users me-1"></i><?= e(t('esign.signers')) ?></h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table">
                    <thead><tr><th><?= e(t('esign.step')) ?></th><th><?= e(t('esign.signer')) ?></th><th><?= e(t('esign.role')) ?></th><th><?= e(t('esign.col_state')) ?></th><th><?= e(t('esign.times')) ?></th><th></th></tr></thead>
                    <tbody id="es-signers"></tbody>
                </table>
            </div>
        </div>
        <!-- until the final document exists: the pages with what is signed so far (read only) -->
        <div class="card es-doc-card" id="es-doc-card" hidden>
            <div class="card-header py-2"><h3 class="card-title"><i class="ti ti-file-text me-1"></i><?= e(t('esign.doc_so_far')) ?></h3></div>
            <div class="es-pages" id="es-pages"></div>
        </div>
        <div class="card es-final-card" id="es-final-card" hidden>
            <iframe id="es-final-frame" title="<?= e(t('esign.final')) ?>"></iframe>
        </div>
    </div>
    <div class="es-handle" tabindex="0" role="separator" aria-orientation="vertical" title="<?= e(t('esign.drag_width')) ?>" aria-label="<?= e(t('esign.drag_width')) ?>"></div>
    <div class="es-side-pane">
        <div class="card"><div class="card-body p-2">
            <h4 class="small fw-semibold text-secondary mb-2"><?= e(t('esign.files')) ?></h4>
            <div id="es-files"></div>
            <div class="small mt-2" id="es-hash"></div>
        </div></div>
        <div class="card"><div class="card-body p-2">
            <h4 class="small fw-semibold text-secondary mb-2"><?= e(t('esign.events')) ?> <span id="es-chain"></span></h4>
            <ul class="es-timeline" id="es-events"></ul>
        </div></div>
    </div>
</div>
