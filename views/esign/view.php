<?php
/** หน้าซองเอกสาร (EnvelopeController::show): progress, signers with their times, the events and the final document. js/pages/envelope-view.js */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText, 'payload' => $payload]);
?>
<div class="es-bar">
    <span class="badge bg-secondary-lt" id="es-nbr"></span>
    <span class="badge" id="es-status"></span>
    <span class="es-grow"></span>
    <a class="btn btn-sm btn-primary" id="es-sign" hidden><i class="ti ti-writing-sign me-1"></i><?= e(t('esign.go_sign')) ?></a>
    <a class="btn btn-sm btn-primary" id="es-final" target="_blank" rel="noopener" hidden><i class="ti ti-file-certificate me-1"></i><?= e(t('esign.final')) ?></a>
    <a class="btn btn-sm" id="es-download" hidden><i class="ti ti-download me-1"></i><?= e(t('esign.download')) ?></a>
    <button type="button" class="btn btn-sm" id="es-remind" hidden><i class="ti ti-bell me-1"></i><?= e(t('esign.remind')) ?></button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="es-recall" hidden data-cancel><i class="ti ti-arrow-back me-1"></i><?= e(t('esign.recall')) ?></button>
</div>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header py-2"><h3 class="card-title"><i class="ti ti-users me-1"></i><?= e(t('esign.signers')) ?></h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table">
                    <thead><tr><th><?= e(t('esign.step')) ?></th><th><?= e(t('esign.signer')) ?></th><th><?= e(t('esign.role')) ?></th><th><?= e(t('esign.col_state')) ?></th><th><?= e(t('esign.times')) ?></th><th></th></tr></thead>
                    <tbody id="es-signers"></tbody>
                </table>
            </div>
        </div>
        <div class="card" id="es-final-card" hidden>
            <iframe id="es-final-frame" title="<?= e(t('esign.final')) ?>" style="width:100%;height:75vh;border:0"></iframe>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card mb-3"><div class="card-body p-2">
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
