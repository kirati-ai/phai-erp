<?php
/** หน้าเซ็นซองเอกสาร (EnvelopeController::signPage): the pages with my fields to fill, then sign or decline. js/pages/envelope-sign.js */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText, 'payload' => $payload]);
?>
<div class="es-bar">
    <span class="badge bg-secondary-lt" id="es-nbr"></span>
    <span class="fw-semibold" id="es-head"></span>
    <span class="es-grow"></span>
    <a class="btn btn-sm" id="es-envelope"><i class="ti ti-list-details me-1"></i><?= e(t('esign.open_envelope')) ?></a>
    <button type="button" class="btn btn-sm btn-outline-danger" id="es-decline" hidden data-cancel><i class="ti ti-x me-1"></i><?= e(t('esign.decline')) ?></button>
    <button type="button" class="btn btn-sm btn-primary" id="es-submit" hidden><i class="ti ti-writing-sign me-1"></i><span></span></button>
</div>
<div class="alert alert-info py-2 small" id="es-msg" hidden></div>
<div class="row g-3">
    <div class="col-xl-9"><div class="es-pages" id="es-pages"></div></div>
    <div class="col-xl-3">
        <div class="card mb-3" id="es-sigcard" hidden><div class="card-body p-2">
            <h4 class="small fw-semibold text-secondary mb-2"><?= e(t('esign.my_signature')) ?></h4>
            <div class="border rounded d-flex align-items-center justify-content-center mb-2" style="height:70px" id="es-sigbox"></div>
            <button type="button" class="btn btn-sm w-100" id="es-draw"><i class="ti ti-signature me-1"></i><?= e(t('esign.draw')) ?></button>
        </div></div>
        <div class="card mb-3" id="es-note-box" hidden><div class="card-body p-2 small"></div></div>
        <div class="card"><div class="card-body p-2">
            <h4 class="small fw-semibold text-secondary mb-2"><?= e(t('esign.files')) ?></h4>
            <div id="es-files"></div>
        </div></div>
    </div>
</div>
