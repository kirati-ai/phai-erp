<?php
/**
 * Head card of a read-only view page (ux/ui.md "View pages"), the same on every view:
 * label over a plain value, empty = "–", numbers right-aligned.
 * @var array  $fields  [[label, value, column classes, numeric?], ...]
 * @var string $class   extra classes on the card (default "mb-3")
 */
?>
<div class="card phai-view-mode <?= e($class ?? 'mb-3') ?>">
    <div class="card-body pb-3">
        <div class="row g-2">
            <?php foreach ($fields as $f): [$label, $value, $col] = $f; $isNum = $f[3] ?? false; ?>
                <div class="<?= e($col) ?>">
                    <label class="form-label"><?= e($label) ?></label>
                    <?php if ($value === '' || $value === null): ?>
                        <div class="phai-view-value is-empty">–</div>
                    <?php else: ?>
                        <div class="phai-view-value<?= $isNum ? ' text-end' : '' ?>"><?= e((string) $value) ?></div>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
