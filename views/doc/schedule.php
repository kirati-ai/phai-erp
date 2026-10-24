<?php
/**
 * Delivery schedule of a scheduled purchase / sales order (ScheduleController), release by release: pick a release,
 * see each line's instalments (firm / planned, against the release before), issue a new release (by hand, or on the
 * purchase side from MRP), print it.
 * @var array $config type, nbr, back, canEdit, canPrint, sources, today, firmDays, text
 */
?>
<div class="phai-toolbar phai-sticky">
    <a class="btn btn-sm" href="<?= e(url($config['back'])) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('js.back')) ?></a>
    <span class="text-secondary small"><?= e($config['text']['party']) ?></span>
    <select class="form-select form-select-sm w-auto" id="sc-release" aria-label="<?= e(t('schedule.rlse')) ?>"></select>
    <div class="ms-auto d-flex flex-wrap gap-2">
        <?php if ($config['canEdit'] && $config['type'] === 'po'): ?>
            <button type="button" class="btn btn-sm" id="sc-mrp" hidden><i class="ti ti-calculator me-1"></i><?= e(t('schedule.from_mrp')) ?></button>
        <?php endif ?>
        <button type="button" class="btn btn-sm btn-primary" id="sc-edit" hidden><i class="ti ti-pencil me-1"></i><?= e(t('schedule.new_release')) ?></button>
        <?php if ($config['canPrint']): ?>
            <a class="btn btn-sm" id="sc-print" target="_blank" rel="noopener" href="#"><i class="ti ti-printer me-1"></i><?= e(t('purchase.print')) ?> / PDF</a>
        <?php endif ?>
        <button type="button" class="btn btn-sm" id="sc-undo" data-cancel hidden><i class="ti ti-x me-1"></i><?= e(t('common.cancel')) ?></button>
        <button type="submit" form="sc-form" class="btn btn-sm btn-primary" id="sc-save" hidden><i class="ti ti-device-floppy me-1"></i><?= e(t('schedule.save_release')) ?></button>
    </div>
</div>

<form id="sc-form" novalidate autocomplete="off">
    <div class="card mb-3"><div class="card-body py-2" id="sc-head"></div></div>
    <div id="sc-lines"></div>
</form>

<script id="sc-config" type="application/json"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
