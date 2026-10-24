<?php
/**
 * Monthly VAT report on A4 landscape (รายงานภาษีขาย / รายงานภาษีซื้อ).
 * @var string $side     sales | purchases
 * @var array  $report   month, rows, net, tax (VatReport::rows)
 * @var array  $company  co_name, co_tax_id, co_branch, co_address
 */
$m = fn ($v) => number_format((float) $v, 2);
$d = fn ($v) => $v ? implode('/', array_reverse(explode('-', substr($v, 0, 10)))) : '';
[$y, $mo] = explode('-', $report['month']);
?>
<style>
    .doc { max-width: 297mm; margin: 0 auto; padding: 10mm 12mm; background: #fff; color: #000; font-size: 12px; }
    .doc .title { font-size: 16px; font-weight: 600; text-align: center; }
    .doc .head { display: flex; justify-content: space-between; margin: 8px 0; }
    .doc table { width: 100%; border-collapse: collapse; }
    .doc th, .doc td { border: 1px solid #999; padding: 3px 5px; vertical-align: top; }
    .doc th { background: #f2f2f2; font-weight: 600; }
    .doc .r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .doc tfoot td { font-weight: 600; }
    @page { size: A4 landscape; margin: 0; }
    @media print { .no-print { display: none !important; } body { background: #fff; } }
</style>

<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>

<div class="doc">
    <div class="title"><?= e(t("accounting.vat_{$side}_title")) ?></div>
    <div class="head">
        <div>
            <?= e(t('accounting.vat_month')) ?> <?= e($mo . '/' . $y) ?><br>
            <?= e(t('accounting.vat_company')) ?> <?= e($company['co_name'] ?? '') ?><br>
            <?= e($company['co_address'] ?? '') ?>
        </div>
        <div style="text-align: right">
            <?= e(t('accounting.vat_tax_id')) ?> <?= e($company['co_tax_id'] ?? '') ?><br>
            <?= e(\Phai\Modules\Sales\Sales::branch($company['co_branch'] ?? null)) ?>
        </div>
    </div>
    <table>
        <thead>
        <tr>
            <th class="r">#</th>
            <th><?= e(t('accounting.date')) ?></th>
            <th><?= e(t('accounting.vat_inv')) ?></th>
            <th><?= e($side === 'sales' ? t('accounting.vat_customer') : t('accounting.vat_vendor')) ?></th>
            <th><?= e(t('accounting.vat_tax_id')) ?></th>
            <th><?= e(t('accounting.vat_branch')) ?></th>
            <th class="r"><?= e(t('accounting.vat_net')) ?></th>
            <th class="r"><?= e(t('accounting.vat_tax')) ?></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($report['rows'] as $i => $r): ?>
            <tr>
                <td class="r"><?= $i + 1 ?></td>
                <td><?= e($d($r['date'])) ?></td>
                <td><?= e($r['nbr'] ?? '') ?></td>
                <td><?= e($r['name'] ?? '') ?></td>
                <td><?= e($r['tax_id'] ?? '') ?></td>
                <td><?= e($r['branch'] ?? '') ?></td>
                <td class="r"><?= $m($r['net']) ?></td>
                <td class="r"><?= $m($r['tax']) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
        <tfoot>
        <tr>
            <td colspan="6" class="r"><?= e(t('accounting.vat_total')) ?></td>
            <td class="r"><?= $m($report['net']) ?></td>
            <td class="r"><?= $m($report['tax']) ?></td>
        </tr>
        </tfoot>
    </table>
</div>
