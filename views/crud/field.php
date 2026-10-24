<?php
/**
 * One form field of a Resource.
 * @var array $f  see Resource::buildFields()
 */
$id = 'f-' . $f['name'];
$attrs = 'id="' . e($id) . '" name="' . e($f['name']) . '"'
    . ($f['readonly'] ? ' readonly' : '')
    . ($f['key'] ? ' data-key' : '');
$default = $f['default'] ?? '';
?>
<div class="<?= (int) $f['col'] <= 3 ? 'col-6 ' : '' ?>col-sm-<?= (int) $f['col'] ?>"><?php // short fields sit two a row on a phone ?>
<?php if ($f['type'] === 'bool'): ?>
    <label class="form-check form-switch mb-0 mt-sm-4">
        <input class="form-check-input" type="checkbox" <?= $attrs ?><?= $default === '1' ? ' checked' : '' ?>>
        <span class="form-check-label"><?= e($f['label']) ?></span>
    </label>
<?php else: ?>
    <label class="form-label<?= $f['required'] ? ' required' : '' ?>" for="<?= e($id) ?>"><?= e($f['label']) ?></label>
    <?php if ($f['geo'] !== null && $f['geo'] !== 'zip'): ?>
        <select class="form-select form-select-sm" <?= $attrs ?> data-geo="<?= e($f['geo']) ?>">
            <option value=""></option>
        </select>
    <?php elseif ($f['type'] === 'select'): ?>
        <select class="form-select form-select-sm" <?= $attrs ?>>
            <?php if (!$f['required']): ?><option value=""></option><?php endif ?>
            <?php foreach ($f['options'] as $o): ?>
                <option value="<?= e($o['value']) ?>"<?= (string) $o['value'] === $default ? ' selected' : '' ?>><?= e($o['label']) ?></option>
            <?php endforeach ?>
        </select>
    <?php elseif (!empty($f['pick'])): ?>
        <input type="text" class="form-control form-control-sm<?= empty($f['free']) ? ' text-uppercase' : '' ?>" <?= $attrs ?> data-pick="<?= e($f['pick']) ?>"<?= empty($f['free']) ? '' : ' data-free' ?> value="<?= e($default) ?>" autocomplete="off">
    <?php elseif ($f['type'] === 'textarea'): ?>
        <textarea class="form-control form-control-sm" rows="2" <?= $attrs ?>><?= e($default) ?></textarea>
    <?php elseif ($f['type'] === 'int' || $f['type'] === 'number'): ?>
        <input type="text" class="form-control form-control-sm text-end" inputmode="<?= $f['type'] === 'int' ? 'numeric' : 'decimal' ?>" <?= $attrs ?>
               value="<?= e($default === '' ? '' : (string) (float) $default) ?>" autocomplete="off">
    <?php else: ?>
        <input type="<?= $f['type'] === 'date' ? 'date' : ($f['type'] === 'email' ? 'email' : 'text') ?>"
               class="form-control form-control-sm<?= $f['upper'] ? ' text-uppercase' : '' ?>" <?= $attrs ?>
               <?= $f['max'] ? 'maxlength="' . (int) $f['max'] . '"' : '' ?>
               <?= $f['geo'] === 'zip' ? 'data-geo="zip"' : '' ?>
               value="<?= e($default) ?>" autocomplete="off">
    <?php endif ?>
<?php endif ?>
</div>
