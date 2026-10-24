<?php
/**
 * ภ.พ.30 summary on A4 — the figures to copy into the Revenue Department's form (a report: shared print style).
 * @var array $r       VatReturnController::compute()
 * @var array $company co_name, co_tax_id, co_branch, co_address
 */
$m = fn ($v) => number_format((float) $v, 2);
$d = fn ($v) => $v ? implode('/', array_reverse(explode('-', $v))) : '';
$rows = [
    ['1', t('vatreturn.sales'), $r['sales_net']],
    ['', t('vatreturn.output'), $r['output']],
    ['2', t('vatreturn.purchases'), $r['purchases_net']],
    ['', t('vatreturn.input'), $r['input']],
    ['3', t('vatreturn.carried'), $r['carried']],
];
?>
<?= \Phai\Core\View\View::partial('doc/print-style') ?>
<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>
<style>
    .vr { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .vr td { padding: 5px 8px; border-bottom: 1px solid #ddd; }
    .vr .r { text-align: right; font-variant-numeric: tabular-nums; width: 40mm; }
    .vr .n { width: 10mm; color: #666; }
    .vr .g td { border-top: 1px solid #000; border-bottom: 3px double #000; font-weight: 600; }
    .vr-head { text-align: center; margin-bottom: 6px; }
    .vr-head div:first-child { font-size: 15px; font-weight: 600; }
</style>
<div class="doc">
    <div class="vr-head">
        <div><?= e(t('vatreturn.title')) ?></div>
        <div><?= e($company['co_name'] ?? '') ?> · <?= e(t('sales.tax_id')) ?> <?= e($company['co_tax_id'] ?? '') ?> <?= e(\Phai\Modules\Sales\Sales::branch($company['co_branch'] ?? null)) ?></div>
        <div><?= e(t('vatreturn.month')) ?> <?= e($r['month']) ?> · <?= e(t('vatreturn.due')) ?> <?= e($d($r['due'])) ?></div>
    </div>
    <table class="vr">
        <?php foreach ($rows as [$n, $label, $v]): ?>
            <tr><td class="n"><?= e($n) ?></td><td><?= e($label) ?></td><td class="r"><?= $m($v) ?></td></tr>
        <?php endforeach ?>
        <tr class="g"><td class="n"></td><td><?= e($r['net'] >= 0 ? t('vatreturn.to_pay') : t('vatreturn.credit')) ?></td><td class="r"><?= $m(abs($r['net'])) ?></td></tr>
    </table>
    <p style="margin-top: 10px"><?= e($r['filed'] ? t('vatreturn.filed', ['date' => $d($r['date'])]) : t('vatreturn.not_filed')) ?></p>
</div>
