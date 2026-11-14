<?php
/**
 * หน้าเซ็นซองเอกสาร (EnvelopeController::signPage): a bar on top that stays put (zoom, the envelope, decline, sign),
 * then the pages on the left and a panel on the right (what to do, my signature, the sender's message, the files),
 * with a bar between them that is dragged to change the panel's width (js/esign-split.js).
 * js/pages/envelope-sign.js
 */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText, 'payload' => $payload]);
$tip = fn (string $label) => ' title="' . e($label) . '" aria-label="' . e($label) . '"';
?>
<div class="es-top">
    <div class="es-bar">
        <span class="badge bg-secondary-lt" id="es-nbr"></span>
        <span class="fw-semibold" id="es-head"></span>
        <span class="es-grow"></span>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn" data-zoom="-1"<?= $tip(t('esign.zoom_out')) ?>><i class="ti ti-zoom-out"></i></button>
            <button type="button" class="btn" data-zoom="0"<?= $tip(t('esign.zoom_fit')) ?>><i class="ti ti-arrows-horizontal"></i></button>
            <button type="button" class="btn" data-zoom="1"<?= $tip(t('esign.zoom_in')) ?>><i class="ti ti-zoom-in"></i></button>
        </div>
        <a class="btn btn-sm" id="es-envelope"><i class="ti ti-list-details me-1"></i><?= e(t('esign.open_envelope')) ?></a>
        <button type="button" class="btn btn-sm btn-outline-danger" id="es-decline" hidden data-cancel><i class="ti ti-x me-1"></i><?= e(t('esign.decline')) ?></button>
        <button type="button" class="btn btn-sm btn-primary" id="es-submit" hidden><i class="ti ti-writing-sign me-1"></i><span></span></button>
    </div>
</div>

<div class="es-split">
    <div class="es-doc-pane"><div class="es-pages" id="es-pages"></div></div>
    <div class="es-handle" tabindex="0" role="separator" aria-orientation="vertical"<?= $tip(t('esign.drag_width')) ?>></div>
    <div class="es-side-pane">
        <div class="alert py-2 small mb-2" id="es-msg" hidden></div>
        <div class="card" id="es-sigcard" hidden><div class="card-body p-2">
            <h4 class="small fw-semibold text-secondary mb-2"><?= e(t('esign.my_signature')) ?></h4>
            <div class="border rounded d-flex align-items-center justify-content-center mb-2 es-sigbox-lg" id="es-sigbox"></div>
            <button type="button" class="btn btn-sm w-100" id="es-draw"><i class="ti ti-signature me-1"></i><?= e(t('esign.draw')) ?></button>
        </div></div>
        <div class="card" id="es-note-box" hidden><div class="card-body p-2">
            <h4 class="small fw-semibold text-secondary mb-2"><i class="ti ti-message me-1"></i><?= e(t('esign.note')) ?></h4>
            <div class="small" style="white-space:pre-line" id="es-note-text"></div>
        </div></div>
        <div class="card"><div class="card-body p-2">
            <h4 class="small fw-semibold text-secondary mb-2"><?= e(t('esign.files')) ?> <span class="badge bg-secondary-lt ms-1" id="es-files-n"></span></h4>
            <div id="es-files"></div>
        </div></div>
    </div>
</div>
