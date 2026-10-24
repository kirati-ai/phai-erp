<?php
/**
 * @var array $sites
 * @var array $types
 */
$typeText = [];
foreach ($types as $t) {
    $typeText[$t] = t('stock.type_' . $t);
}
?>
<div class="phai-toolbar">
    <div class="input-icon phai-search">
        <span class="input-icon-addon"><i class="ti ti-search"></i></span>
        <input type="search" id="q" class="form-control form-control-sm" placeholder="<?= e(t('common.search')) ?>" data-search>
    </div>
    <input type="date" class="form-control form-control-sm w-auto" id="from" title="<?= e(t('stock.from')) ?>">
    <span class="text-secondary">–</span>
    <input type="date" class="form-control form-control-sm w-auto" id="to" title="<?= e(t('stock.to')) ?>">
    <select class="form-select form-select-sm w-auto" id="site">
        <option value=""><?= e(t('stock.all_sites')) ?></option>
        <?php foreach ($sites as $s): ?>
            <option value="<?= e($s['si_site']) ?>"><?= e($s['si_site']) ?></option>
        <?php endforeach ?>
    </select>
    <select class="form-select form-select-sm w-auto" id="type">
        <option value=""><?= e(t('stock.all_types')) ?></option>
        <?php foreach ($typeText as $code => $text): ?>
            <option value="<?= e($code) ?>"><?= e($text) ?></option>
        <?php endforeach ?>
    </select>
    <span class="badge bg-blue-lt d-none" id="part-filter"></span>
</div>

<?= \Phai\Core\View\View::partial('inventory/history-table') ?>

<script id="type-text" type="application/json"><?= json_encode($typeText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
