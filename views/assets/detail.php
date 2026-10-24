<?php
use Phai\Core\View\View;
use Phai\Modules\Accounting\AssetDepreciation;
use Phai\Modules\Accounting\FixedAssetController;
$a = $asset;
$select = function (string $name, array $options, bool $blank = true): void {
    echo '<select class="form-select form-select-sm" name="' . e($name) . '">';
    if ($blank) echo '<option value=""></option>';
    foreach ($options as $o) echo '<option value="' . e($o['value']) . '">' . e($o['label']) . '</option>';
    echo '</select>';
};
$input = function (string $name, string $label, string $value = '', string $type = 'text', string $pick = ''): void {
    // $pick: a select box from the server's list (Phai.pick), e.g. people (any name may be typed too)
    $attr = $pick !== '' ? ' data-pick="' . e($pick) . '" data-free autocomplete="off"' : '';
    echo '<div class="col-md-2"><label class="form-label">' . e($label) . '</label><input class="form-control form-control-sm" type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $attr . '></div>';
};
?>
<div class="phai-toolbar">
<a class="btn btn-sm" href="<?= e(url('/fixed-assets')) ?>"><i class="ti ti-arrow-left me-1"></i><?= e(t('assets.back')) ?></a>
<?php if ($a): ?><span class="badge bg-<?= e(FixedAssetController::statuses()[$a['fa_status']]['color']) ?>-lt"><?= e(FixedAssetController::statuses()[$a['fa_status']]['text']) ?></span>
<div class="ms-auto d-flex flex-wrap gap-2">
<?php if ($canDelete && $a['fa_status'] === 'DRAFT' && !$history): ?><button type="button" class="btn btn-sm btn-ghost-danger" id="delete-asset"><i class="ti ti-trash me-1"></i><?= e(t('assets.delete_draft')) ?></button><?php endif ?>
<?php if ($canPrint): ?><a class="btn btn-sm" target="_blank" href="<?= e(url('/fixed-assets/' . rawurlencode($a['fa_code']) . '/print?pdf=1')) ?>"><i class="ti ti-printer me-1"></i><?= e(t('assets.print_pdf')) ?></a><?php endif ?>
<?php if (!$editing && $a['fa_status'] === 'DRAFT' && $canEdit): ?><a class="btn btn-sm btn-primary" href="<?= e(url('/fixed-assets/' . rawurlencode($a['fa_code']) . '?edit=1')) ?>"><i class="ti ti-pencil me-1"></i><?= e(t('assets.edit')) ?></a><?php endif ?>
</div>
<?php endif ?>
</div>
<div class="card mb-3<?= $editing ? '' : ' phai-view-mode' ?>"><div class="card-body">
<?php if ($editing): ?>
<p class="text-secondary small"><?= e(t('assets.detail_hint')) ?></p>
<form id="asset-form"><div class="row g-3">
<?php foreach ($fields as $f): ?><?= View::partial('crud/field', ['f' => $f]) ?><?php endforeach ?>
</div><div class="mt-3 text-end"><button class="btn btn-sm btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i><?= e(t('assets.save_draft')) ?></button></div></form>
<?php else: ?>
<div class="row g-2">
<?php foreach ($fields as $f): $v = $a[$f['name']] ?? ''; if ($f['type'] === 'select') foreach ($f['options'] as $o) if ((string) $o['value'] === (string) $v) { $v = $o['label']; break; } if ($f['type'] === 'date' && $v) $v = implode('/', array_reverse(explode('-', $v))); if (($f['pick'] ?? '') === 'vendors' && $v) $v .= ' · ' . (\Phai\Core\App::db()->value('SELECT vd_name FROM vd_mstr WHERE vd_code = ?', [$v]) ?? ''); ?>
<div class="col-6 col-md-<?= (int) min(4, max(2, $f['col'] ?? 3)) ?>"><label class="form-label"><?= e($f['label']) ?></label><div class="phai-view-value<?= $v === '' || $v === null ? ' is-empty' : '' ?>"><?= e((string) ($v === '' || $v === null ? '–' : $v)) ?></div></div>
<?php endforeach ?>
<div class="col-6 col-md-2"><label class="form-label"><?= e(t('assets.accum')) ?></label><div class="phai-view-value text-end"><?= number_format((float) $a['fa_accum'], 2) ?></div></div>
<div class="col-6 col-md-2"><label class="form-label"><?= e(t('assets.book_value')) ?></label><div class="phai-view-value text-end"><?= number_format($a['fa_status'] === 'DISPOSED' ? 0 : (float) $a['fa_cost'] - (float) $a['fa_accum'], 2) ?></div></div>
</div>
<?php endif ?>
</div></div>
<?php if ($a): /* documents that belong to the asset: invoice, warranty, photos, title deed (Phai.files) */ ?>
<?= View::partial('partials/files', ['doc' => 'FA', 'nbr' => $a['fa_code']]) ?>
<?php endif ?>
<?php if ($a && !$editing): ?>
<?php if ($a['fa_status'] === 'DRAFT' && $canPost): ?>
<details class="card mb-3"><summary class="card-header fw-normal" style="cursor:pointer"><?= e(t('assets.act_title')) ?></summary><form class="card-body asset-action" data-action="activate"><div class="row g-3">
<div class="col-md-2"><label class="form-label"><?= e(t('assets.act_mode')) ?></label><select name="mode" class="form-select form-select-sm"><option value="BOOKED"><?= e(t('assets.act_booked')) ?></option><option value="POST"><?= e(t('assets.act_post')) ?></option></select></div>
<div class="col-md-2"><label class="form-label"><?= e(t('assets.act_credit')) ?></label><?php $select('credit_acct', $accounts); ?></div>
<div class="col-md-2 align-self-end"><button class="btn btn-sm btn-primary"><i class="ti ti-circle-check me-1"></i><?= e(t('assets.act_button')) ?></button></div>
</div><p class="text-secondary small mt-2 mb-0"><?= e(t('assets.act_hint')) ?></p></form></details>
<?php endif ?>
<?php if ($a['fa_status'] === 'ACTIVE'): ?>
<?php if ($canPost && $a['fa_method'] !== 'NONE'): ?>
<details class="card mb-3"><summary class="card-header fw-normal" style="cursor:pointer"><?= e(t('assets.monthly_depr')) ?></summary><form class="card-body asset-action" data-action="depreciate"><div class="row g-3">
<?php $input('month', t('assets.month'), AssetDepreciation::next($a)); ?>
<div class="col-md-2 align-self-end"><button class="btn btn-sm btn-primary"><i class="ti ti-calculator me-1"></i><?= e(t('assets.depr_post')) ?></button></div>
</div><p class="text-secondary small mt-2 mb-0"><?= e(t('assets.depr_hint')) ?></p></form></details>
<?php endif ?>
<?php if ($canEdit): ?>
<details class="card mb-3"><summary class="card-header fw-normal" style="cursor:pointer"><?= e(t('assets.tr_title')) ?></summary><form class="card-body asset-action" data-action="transfer"><div class="row g-3">
<?php $input('date', t('assets.tr_date'), date('Y-m-d'), 'date'); ?>
<div class="col-md-2"><label class="form-label"><?= e(t('assets.tr_site')) ?></label><?php $select('site', $sites, false); ?></div><div class="col-md-2"><label class="form-label"><?= e(t('assets.tr_cc')) ?></label><?php $select('cc', $centres); ?></div>
<?php $input('location', t('assets.tr_location'), $a['fa_location'] ?? ''); $input('custodian', t('assets.tr_custodian'), $a['fa_custodian'] ?? '', 'text', 'people'); $input('note', t('assets.tr_note')); ?>
<div class="col-12 text-end"><button class="btn btn-sm btn-primary"><i class="ti ti-arrows-exchange me-1"></i><?= e(t('assets.tr_save')) ?></button></div>
</div></form></details>
<?php endif ?>
<?php if ($canPost): ?>
<details class="card mb-3"><summary class="card-header fw-normal" style="cursor:pointer"><?= e(t('assets.dsp_title')) ?></summary><form class="card-body asset-action" data-action="dispose"><div class="row g-3">
<?php $input('date', t('assets.dsp_date'), date('Y-m-d'), 'date'); $input('proceeds', t('assets.dsp_proceeds'), '0'); ?>
<div class="col-md-2"><label class="form-label"><?= e(t('assets.dsp_acct')) ?></label><?php $select('proceeds_acct', $accounts); ?></div>
<?php $input('ref', t('assets.dsp_ref')); $input('note', t('assets.dsp_note')); ?>
<div class="col-12 text-end"><button class="btn btn-sm btn-danger"><i class="ti ti-trash-x me-1"></i><?= e(t('assets.dsp_post')) ?></button></div>
</div><p class="text-secondary small mt-2 mb-0"><?= e(t('assets.dsp_hint')) ?></p></form></details>
<?php endif ?>
<?php endif ?>
<div class="card mb-3"><div class="card-header fw-normal"><?= e(t('assets.hist_title')) ?></div><div class="table-responsive"><table class="table table-sm card-table"><thead><tr><th><?= e(t('assets.hist_nbr')) ?></th><th><?= e(t('assets.hist_date')) ?></th><th><?= e(t('assets.hist_type')) ?></th><th><?= e(t('assets.hist_amount')) ?></th><th><?= e(t('assets.hist_note')) ?></th><th><?= e(t('assets.hist_status')) ?></th></tr></thead><tbody>
<?php $last = null; foreach ($history as $h) if ($h['fat_type'] !== 'REVERSE' && !$h['fat_reversed']) { $last = $h['fat_id']; break; }
foreach ($history as $h): ?>
<tr><td>FA-<?= e($h['fat_id']) ?></td><td><?= e($h['fat_date']) ?></td><td><?= e(t('fields.fat_type.' . $h['fat_type'])) ?></td><td class="text-end"><?= number_format((float) $h['fat_amount'], 2) ?></td><td><?= e($h['fat_note'] ?? '') ?></td><td><?= e($h['fat_reversed'] ? t('assets.hist_reversed') : ($h['fat_posted'] ? t('assets.hist_posted') : t('assets.hist_register'))) ?></td></tr>
<?php endforeach ?>
<?php if (!$history): ?><tr><td colspan="6" class="text-center text-secondary"><?= e(t('assets.hist_empty')) ?></td></tr><?php endif ?>
</tbody></table></div></div>
<?php if ($last !== null && $canPost): ?>
<details class="card mb-3"><summary class="card-header fw-normal" style="cursor:pointer"><?= e(t('assets.rev_title')) ?></summary><form class="card-body asset-action" data-action="reverse/<?= e($last) ?>"><div class="row g-3">
<?php $input('date', t('assets.rev_date', ['id' => $last]), date('Y-m-d'), 'date'); $input('note', t('assets.rev_note')); ?>
<div class="col-md-2 align-self-end"><button class="btn btn-sm btn-outline-danger"><i class="ti ti-arrow-back-up me-1"></i><?= e(t('assets.rev_title')) ?></button></div>
</div></form></details>
<?php endif ?>
<?php if ($schedule): ?>
<div class="card"><div class="card-header fw-normal"><?= e(t('assets.sch_title')) ?></div><div class="table-responsive" style="max-height:360px"><table class="table table-sm card-table"><thead><tr><th><?= e(t('assets.sch_month')) ?></th><th class="text-end"><?= e(t('assets.depr')) ?></th><th class="text-end"><?= e(t('assets.accum')) ?></th><th class="text-end"><?= e(t('assets.sch_remaining')) ?></th></tr></thead><tbody>
<?php foreach ($schedule as $s): ?><tr><td><?= e($s['month']) ?></td><td class="text-end"><?= number_format($s['depreciation'], 2) ?></td><td class="text-end"><?= number_format($s['accumulated'], 2) ?></td><td class="text-end"><?= number_format($s['book_value'], 2) ?></td></tr><?php endforeach ?>
</tbody></table></div></div>
<?php endif ?>
<?php endif ?>
<script type="application/json" id="asset-config"><?= json_encode(['asset' => $a, 'editing' => $editing, 'categories' => $categories, 'text' => ['registered' => t('assets.pick_registered'), 'confirmDelete' => t('assets.confirm_delete'), 'confirmAction' => t('assets.confirm_action')]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
