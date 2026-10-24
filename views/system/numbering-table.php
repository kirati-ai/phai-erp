<?php
/**
 * Prefix, format and next number of some documents, saved with PUT /api/settings/numbering (public/js/pages/numbering.js).
 * On /settings/numbering (every document) and at the foot of each module's settings page (NumberingController::table).
 * @var array   $docs     [{doc, prefix, next, format}]
 * @var bool    $canEdit
 * @var ?string $title    a heading, on a module's settings page
 * @var ?string $width    the card's max width (the settings form's above it)
 */

use Phai\Core\Numbering;

// what each format adds after the prefix, for the live example: [date part, digits]
$shapes = array_map(fn ($f) => ['date' => $f[0] !== '' ? date($f[0]) : '', 'digits' => $f[1]], Numbering::FORMATS);
if (!$docs) {
    return;
}
?>
<form class="card<?= !empty($title) ? ' mt-3' : '' ?>" id="numbering" novalidate autocomplete="off" style="max-width: <?= e($width ?? '50rem') ?>">
    <?php if (!empty($title)): ?>
        <div class="card-header py-2"><h3 class="card-title"><i class="ti ti-numbers me-1"></i><?= e($title) ?></h3></div>
    <?php endif ?>
    <fieldset<?= $canEdit ? '' : ' disabled' ?>>
        <div class="table-responsive">
            <table class="table table-sm table-vcenter card-table mb-0">
                <thead>
                <tr>
                    <th><?= e(t('numbering.document')) ?></th>
                    <th style="width: 6.5rem"><?= e(t('numbering.prefix')) ?></th>
                    <th style="width: 13rem"><?= e(t('numbering.format')) ?></th>
                    <th style="width: 6.5rem" class="text-end"><?= e(t('numbering.next')) ?></th>
                    <th style="width: 9rem"><?= e(t('numbering.example')) ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr data-doc="<?= e($d['doc']) ?>">
                        <td><?= e(t('numbering.' . $d['doc'])) ?></td>
                        <td><input type="text" class="form-control form-control-sm text-uppercase" name="prefix_<?= e($d['doc']) ?>"
                                   value="<?= e($d['prefix']) ?>" maxlength="2"></td>
                        <td>
                            <select class="form-select form-select-sm" name="format_<?= e($d['doc']) ?>">
                                <?php foreach (array_keys(Numbering::FORMATS) as $f): ?>
                                    <option value="<?= e($f) ?>"<?= $f === $d['format'] ? ' selected' : '' ?>><?= e(t("numbering.format_$f")) ?></option>
                                <?php endforeach ?>
                            </select>
                        </td>
                        <td><input type="text" class="form-control form-control-sm text-end" inputmode="numeric" name="next_<?= e($d['doc']) ?>"
                                   value="<?= (int) $d['next'] ?>"></td>
                        <td class="font-monospace small text-secondary" data-example></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </fieldset>
    <?php if ($canEdit): ?>
        <div class="card-footer d-flex align-items-center gap-2">
            <span class="small text-secondary"><?= e(t('numbering.reset_note')) ?></span>
            <button type="submit" class="btn btn-sm btn-primary ms-auto"><i class="ti ti-device-floppy me-1"></i><?= e(t('common.save')) ?></button>
        </div>
    <?php endif ?>
</form>
<script id="numbering-shapes" type="application/json"><?= json_encode($shapes, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script defer src="<?= e(asset('js/pages/numbering.js')) ?>"></script><?php // defer: after the layout's phai.js ?>
