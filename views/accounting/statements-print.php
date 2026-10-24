<?php
/**
 * งบแสดงฐานะการเงิน และงบกำไรขาดทุน on A4 (a report: the shared print style, no party or signatures box).
 * @var array $fs      FinancialStatementController::build()
 * @var array $company co_name, co_tax_id, co_address
 */
$T = \Phai\Modules\Accounting\FinancialStatementController::text();
$m = function ($v) {
    $s = number_format(abs((float) $v), 2);
    return (float) $v < 0 ? "($s)" : $s;
};
$d = fn ($v) => implode('/', array_reverse(explode('-', $v)));
$block = function (string $key) use ($fs, $T, $m) {
    if (!$fs['sections'][$key]) {
        return;
    }
    echo '<tr class="h"><td colspan="2">' . e($T[$key]) . '</td></tr>';
    foreach ($fs['sections'][$key] as $r) {
        echo '<tr><td class="ind">' . e(trim($r['acct'] . ' ' . $r['name'])) . '</td><td class="r">' . $m($r['amount']) . '</td></tr>';
    }
    echo '<tr class="t"><td>' . e($T['total'] . $T[$key]) . '</td><td class="r">' . $m($fs['totals'][$key]) . '</td></tr>';
};
$total = fn (string $label, float $v, bool $grand = false) => '<tr class="' . ($grand ? 'g' : 't') . '"><td>' . e($label) . '</td><td class="r">' . $m($v) . '</td></tr>';
?>
<?= \Phai\Core\View\View::partial('doc/print-style') ?>
<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>
<style>
    .fs { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
    .fs td { padding: 3px 6px; }
    .fs .r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 34mm; }
    .fs .ind { padding-left: 18px; }
    .fs .h td { padding-top: 8px; font-weight: 600; }
    .fs .t td { border-top: 1px solid #999; }
    .fs .g td { border-top: 1px solid #000; border-bottom: 3px double #000; font-weight: 600; }
    .fs-title { text-align: center; margin: 4px 0 10px; }
    .fs-title div:first-child { font-size: 15px; font-weight: 600; }
    .fs-page { break-after: page; }
</style>
<div class="doc">
    <section class="fs-page">
        <div class="fs-title"><div><?= e($company['co_name'] ?? '') ?></div><div><?= e($T['balance_sheet']) ?></div><div><?= e(str_replace(':date', $d($fs['to']), $T['as_of'])) ?></div></div>
        <table class="fs">
            <?php $block('current_assets'); $block('noncurrent_assets'); echo $total($T['assets'], $fs['assets'], true) ?>
            <?php $block('current_liabilities'); $block('noncurrent_liabilities'); echo $total($T['liabilities'], $fs['liabilities']) ?>
            <?php $block('equity'); echo $total($T['liab_equity'], $fs['liabilities'] + $fs['totals']['equity'], true) ?>
        </table>
    </section>
    <section>
        <div class="fs-title"><div><?= e($company['co_name'] ?? '') ?></div><div><?= e($T['income']) ?></div><div><?= e($d($fs['from']) . ' – ' . $d($fs['to'])) ?></div></div>
        <table class="fs">
            <?php $block('revenue'); $block('cost_of_sales'); echo $total($T['gross'], $fs['gross']) ?>
            <?php $block('other_income'); $block('expenses'); $block('finance_cost'); echo $total($T['before_tax'], $fs['before_tax']) ?>
            <?php $block('income_tax'); echo $total($T['net'], $fs['net'], true) ?>
        </table>
    </section>
</div>
