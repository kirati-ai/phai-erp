<?php
/**
 * Month picker of a toolbar: ‹ ตุลาคม 2569 › and a panel of the months of a year. Driven by Phai.month(el, {...}).
 * @var string $id
 * @var bool   $all  show "ทุกเดือน" (an empty value)
 */
?>
<div class="phai-month" id="<?= e($id) ?>">
    <button type="button" class="phai-month-step" data-step="-1" aria-label="<?= e(t('common.prev_month')) ?>"><i class="ti ti-chevron-left"></i></button>
    <button type="button" class="phai-month-label" aria-haspopup="true"><i class="ti ti-calendar-month"></i><span></span></button>
    <button type="button" class="phai-month-step" data-step="1" aria-label="<?= e(t('common.next_month')) ?>"><i class="ti ti-chevron-right"></i></button>
    <div class="phai-month-panel" hidden>
        <div class="phai-month-year">
            <button type="button" class="phai-month-step" data-year="-1"><i class="ti ti-chevron-left"></i></button>
            <strong data-year-text></strong>
            <button type="button" class="phai-month-step" data-year="1"><i class="ti ti-chevron-right"></i></button>
        </div>
        <div class="phai-month-grid">
            <?php foreach (explode(',', t('common.months_short')) as $i => $m): ?>
                <button type="button" data-month="<?= $i + 1 ?>"><?= e($m) ?></button>
            <?php endforeach ?>
        </div>
        <div class="phai-month-foot">
            <?php if (!empty($all)): ?><button type="button" data-all><?= e(t('common.all_months')) ?></button><?php endif ?>
            <button type="button" data-now><?= e(t('common.this_month')) ?></button>
        </div>
    </div>
</div>
