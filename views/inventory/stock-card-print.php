<?php
/**
 * รายงานสินค้าและวัตถุดิบ (ม.87(3)) on A4 landscape: per item its opening, each movement in/out and the running balance
 * — one item or all of them, one table with a heading row per item (the column heads repeat on every page).
 * @var array $report  StockCardController::build()
 * @var array $company co_name, co_tax_id, co_branch, co_address
 */
$m = fn ($v) => $v === null ? '' : number_format((float) $v, 2);
$q = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 5, '.', ','), '0'), '.');
$d = fn ($v) => implode('/', array_reverse(explode('-', $v)));
$filter = $report['part'] !== '' ? $report['part'] : ($report['type'] !== '' ? t('fields.pl_inventory_type.' . $report['type']) : t('stock.card_all'));
?>
<?= \Phai\Core\View\View::partial('doc/print-style') ?>
<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>
<style>
    @page { size: A4 landscape; margin: 0; }
    .doc.sc-doc { width: 297mm; min-height: 210mm; padding: 10mm 10mm; }
    @media print { .doc.sc-doc { min-height: 0; } }
    .sc { width: 100%; border-collapse: collapse; font-size: 11px; }
    .sc th, .sc td { border: 1px solid #999; padding: 3px 5px; }
    .sc th { background: #f2f2f2; font-weight: 600; text-align: center; }
    .sc thead { display: table-header-group; }
    .sc tr { break-inside: avoid; }
    .sc .r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sc .t td { font-weight: 600; }
    .sc .g td { font-weight: 600; background: #f2f2f2; break-after: avoid; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sc .g + tr td { break-before: avoid; }
    .sc-head { display: flex; justify-content: space-between; margin-bottom: 8px; }
    .sc-title { font-size: 15px; font-weight: 600; }
</style>
<div class="doc sc-doc">
    <div class="sc-head">
        <div>
            <div class="sc-title"><?= e(t('stock.card_title')) ?></div>
            <div><?= e($company['co_name'] ?? '') ?> · <?= e(t('sales.tax_id')) ?> <?= e($company['co_tax_id'] ?? '') ?> <?= e(\Phai\Modules\Sales\Sales::branch($company['co_branch'] ?? null)) ?></div>
        </div>
        <div style="text-align: right">
            <div><?= e(t('stock.part')) ?>: <strong><?= e($filter) ?></strong></div>
            <div><?= e($report['site'] !== '' ? $report['site'] : t('stock.all_sites')) ?> · <?= e($d($report['from'])) ?> – <?= e($d($report['to'])) ?></div>
        </div>
    </div>
    <table class="sc">
        <thead>
        <tr><th rowspan="2"><?= e(t('stock.date')) ?></th><th rowspan="2"><?= e(t('stock.doc_nbr')) ?></th><th rowspan="2"><?= e(t('stock.card_type')) ?></th>
            <th rowspan="2"><?= e(t('stock.unit_cost')) ?></th><th colspan="2"><?= e(t('stock.card_in')) ?></th><th colspan="2"><?= e(t('stock.card_out')) ?></th>
            <th colspan="2"><?= e(t('stock.card_balance')) ?></th></tr>
        <tr><?php for ($i = 0; $i < 3; $i++): ?><th><?= e(t('stock.qty')) ?></th><th><?= e(t('stock.card_value')) ?></th><?php endfor ?></tr>
        </thead>
        <tbody>
        <?php foreach ($report['cards'] as $i => $card): $p = $card['part']; ?>
            <tr class="g"><td colspan="10"><?= $i + 1 ?>. &nbsp;<?= e($p['pt_part']) ?> &nbsp;<?= e($p['pt_desc1'] ?? '') ?> (<?= e($p['pt_um']) ?>)</td></tr>
            <tr><td><?= e($d($report['from'])) ?></td><td colspan="7"><?= e(t('stock.card_opening')) ?></td>
                <td class="r"><?= $q($card['opening']['qty']) ?></td><td class="r"><?= $m($card['opening']['value']) ?></td></tr>
            <?php foreach ($card['rows'] as $r): ?>
                <tr><td><?= e($d($r['date'])) ?></td><td><?= e($r['nbr'] ?? '') ?></td><td><?= e($r['type_text']) ?></td><td class="r"><?= $m($r['cost']) ?></td>
                    <td class="r"><?= $q($r['in_qty']) ?></td><td class="r"><?= $m($r['in_value']) ?></td>
                    <td class="r"><?= $q($r['out_qty']) ?></td><td class="r"><?= $m($r['out_value']) ?></td>
                    <td class="r"><?= $q($r['qty']) ?></td><td class="r"><?= $m($r['value']) ?></td></tr>
            <?php endforeach ?>
            <tr class="t"><td colspan="4"><?= e(t('stock.card_total')) ?></td>
                <td class="r"><?= $q($card['in']['qty']) ?></td><td class="r"><?= $m($card['in']['value']) ?></td>
                <td class="r"><?= $q($card['out']['qty']) ?></td><td class="r"><?= $m($card['out']['value']) ?></td>
                <td class="r"><?= $q($card['closing']['qty']) ?></td><td class="r"><?= $m($card['closing']['value']) ?></td></tr>
        <?php endforeach ?>
        <?php if (count($report['cards']) > 1): ?>
            <tr class="t"><td colspan="4"><?= e(t('stock.card_grand')) ?></td>
                <td></td><td class="r"><?= $m($report['total']['in']) ?></td>
                <td></td><td class="r"><?= $m($report['total']['out']) ?></td>
                <td></td><td class="r"><?= $m($report['total']['closing']) ?></td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>
