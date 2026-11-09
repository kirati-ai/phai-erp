<?php
/** ซองเอกสาร (EnvelopeController::page): waiting for my signature, sent by me, and finished ones I am in. */
echo \Phai\Core\View\View::partial('esign/_head', ['esignText' => $esignText]);
$tab = fn (string $id, string $icon, string $label, bool $on = false) =>
    '<li class="nav-item"><a href="#' . $id . '" class="nav-link' . ($on ? ' active' : '') . '" data-bs-toggle="tab"><i class="ti ti-' . $icon . ' me-1"></i>' . e($label) . ' <span class="badge bg-secondary-lt ms-1" data-count="' . $id . '"></span></a></li>';
$tables = [
    'es-waiting' => ['nbr', 'title', 'from', 'role', 'received', 'opened'],
    'es-sent'    => ['nbr', 'title', 'state', 'progress', 'sent', 'done'],
    'es-done'    => ['nbr', 'title', 'from', 'done'],
];
?>
<div class="es-bar">
    <span class="es-grow"></span>
    <button type="button" class="btn btn-sm btn-primary" id="es-new" hidden><i class="ti ti-plus me-1"></i><?= e(t('esign.new')) ?></button>
</div>
<div class="card">
    <div class="card-header py-2">
        <ul class="nav nav-tabs card-header-tabs">
            <?= $tab('es-waiting', 'writing-sign', t('esign.tab_waiting'), true) ?>
            <?= $tab('es-sent', 'send', t('esign.tab_sent')) ?>
            <?= $tab('es-done', 'file-certificate', t('esign.tab_done')) ?>
        </ul>
    </div>
    <div class="tab-content">
        <?php foreach ($tables as $id => $cols): ?>
        <div class="tab-pane<?= $id === 'es-waiting' ? ' active show' : '' ?>" id="<?= $id ?>">
            <div class="table-responsive">
                <table class="table table-sm table-vcenter card-table table-hover">
                    <thead><tr><?php foreach ($cols as $c): ?><th><?= e(t("esign.col_$c")) ?></th><?php endforeach ?></tr></thead>
                    <tbody><tr><td colspan="<?= count($cols) ?>" class="text-secondary"><?= e(t('js.loading')) ?></td></tr></tbody>
                </table>
            </div>
        </div>
        <?php endforeach ?>
    </div>
</div>
