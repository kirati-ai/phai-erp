<?php
/**
 * Printed document on A4 (purchase order, quotation, delivery note, tax invoice, billing note,
 * receipt, payment voucher). Print it, or save it as PDF from the print dialog.
 * @var array $doc  see SalesPrint::render()
 */
$co = $doc['company'];
$lines = $doc['lines'];
$totals = $doc['totals'] ?? null;
$signs = $doc['signs'] ?? [];
?>
<?= \Phai\Core\View\View::partial('doc/print-style') ?>
<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>

<div class="doc">
    <header class="doc-head">
        <?= \Phai\Core\View\View::partial('doc/company', ['co' => $co]) ?>
        <div class="doc-title-box">
            <div class="doc-title"><?= e($doc['title']) ?></div>
            <table class="doc-meta">
                <tr><th><?= e(t('sales.nbr')) ?></th><td><strong><?= e($doc['nbr']) ?></strong></td></tr>
                <?php foreach ($doc['meta'] as [$label, $value]): ?>
                    <?php if ((string) $value !== ''): ?><tr><th><?= e($label) ?></th><td><?= e($value) ?></td></tr><?php endif ?>
                <?php endforeach ?>
            </table>
        </div>
    </header>

    <?php if (!empty($doc['party'])): /* internal documents (requisition, work order, count, journal) have no party */ ?>
    <section class="doc-box doc-party">
        <div class="doc-label"><?= e($doc['party']['label']) ?></div>
        <div class="doc-party-name"><?= e($doc['party']['name']) ?></div>
        <?php foreach ($doc['party']['lines'] as $line): ?><div><?= e($line) ?></div><?php endforeach ?>
    </section>
    <?php endif ?>

    <table class="doc-lines">
        <thead>
        <tr>
            <?php foreach ($lines['columns'] as $c): ?>
                <th class="<?= ($c['align'] ?? '') === 'r' ? 'r' : '' ?>"<?= isset($c['width']) ? ' style="width: ' . e($c['width']) . '"' : '' ?>><?= e($c['label']) ?></th>
            <?php endforeach ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($lines['rows'] as $row): ?>
            <?php if (isset($row['sub'])): /* SalesPrint::sub(): what the line above was for, under it across the table */ ?>
            <tr class="doc-sub"><td></td><td colspan="<?= count($lines['columns']) - 1 ?>"><?= e($row['sub']) ?></td></tr>
            <?php continue; endif ?>
            <tr>
                <?php foreach ($row as $i => $cell): ?>
                    <td class="<?= ($lines['columns'][$i]['align'] ?? '') === 'r' ? 'r' : '' ?>"><?= e((string) $cell) ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>

    <?php if ($totals): ?>
        <section class="doc-summary">
            <div class="doc-words">
                <?php if (($totals['words'] ?? '') !== ''): ?>
                    <div class="doc-label"><?= e(t('sales.amount_words')) ?></div>
                    <div class="doc-words-text">(<?= e($totals['words']) ?>)</div>
                <?php endif ?>
            </div>
            <table class="doc-totals">
                <?php foreach ($totals['rows'] as [$label, $value, $bold]): /* true = the highlighted bottom line, 'bold' = bold only */ ?>
                    <tr class="<?= $bold === 'bold' ? 'bold' : ($bold ? 'grand' : '') ?>"><th><?= e($label) ?></th><td><?= e($value) ?></td></tr>
                <?php endforeach ?>
            </table>
        </section>
    <?php endif ?>

    <?php if (($doc['notes'] ?? '') !== '' && $doc['notes'] !== null): ?>
        <section class="doc-box doc-notes">
            <div class="doc-label"><?= e(t('sales.remarks')) ?></div>
            <div><?= nl2br(e((string) $doc['notes'])) ?></div>
        </section>
    <?php endif ?>

    <?php if ($signs): ?>
        <section class="doc-signs" style="grid-template-columns: repeat(<?= count($signs) ?>, 1fr)">
            <?php foreach ($signs as $label): ?>
                <div>
                    <div class="doc-sign-line"></div>
                    <div class="doc-sign-label"><?= e($label) ?></div>
                    <div class="doc-sign-date"><?= e(t('sales.date')) ?> ____/____/________</div>
                </div>
            <?php endforeach ?>
        </section>
    <?php endif ?>

    <footer class="doc-foot">
        <span><?= e($co['name']) ?> · <?= e($doc['nbr']) ?></span>
        <span><?= e(t('sales.printed_at')) ?> <?= e(date('d/m/Y H:i')) ?></span>
    </footer>
</div>
