<?php
/** ตรวจเอกสาร (EnvelopeController::verifyPage): is this file one that was signed here, unchanged? js/pages/envelope-verify.js */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText, 'payload' => ['nbr' => $nbr, 'summary' => $summary]]);
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <p class="small text-secondary mb-2"><?= e(t('esign.verify_hint')) ?></p>
            <label class="btn btn-primary btn-sm"><i class="ti ti-file-search me-1"></i><?= e(t('esign.verify_pick')) ?><input type="file" id="es-verify-file" hidden accept="application/pdf,image/*"></label>
            <div class="mt-3" id="es-verify-result"></div>
        </div></div>
    </div>
    <div class="col-lg-6"><div id="es-summary"></div></div>
</div>
