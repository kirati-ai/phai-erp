<?php
/**
 * Printed document on A4 (purchase order, quotation, delivery note, tax invoice, billing note,
 * receipt, payment voucher). Print it, or save it as PDF from the print dialog.
 * The item table stretches down to the signature row, so a short document still reads as a whole form.
 * @var array $doc  see SalesPrint::render(); its boxes (System\Signature::boxes) and stamp come with it
 * @var ?string $signPath  the print path when the page is open to sign (?html=1 in a browser), else null
 */
$co = $doc['company'];
$lines = $doc['lines'];
$totals = $doc['totals'] ?? null;
$boxes = $doc['boxes'] ?? [];
$stamp = $doc['stamp'] ?? null;
$cols = count($lines['columns']);
$cells = count($boxes) + ($stamp ? 1 + (int) $stamp['outside'] : 0);
?>
<?= \Phai\Core\View\View::partial('doc/print-style') ?>
<?= \Phai\Core\View\View::partial('doc/print-toolbar', ['signPath' => $signPath ?? null]) ?>

<div class="doc doc-form"<?= !empty($signPath) ? ' data-sign-path="' . e($signPath) . '"' : '' ?>>
    <header class="doc-head">
        <?= \Phai\Core\View\View::partial('doc/company', ['co' => $co]) ?>
        <div class="doc-title-box">
            <div class="doc-title"><?= e($doc['title']) ?></div>
            <div class="doc-nbr"><?= e($doc['nbr']) ?></div>
        </div>
    </header>

    <section class="doc-info<?= empty($doc['party']) ? ' doc-info-solo' : '' ?>">
        <?php if (!empty($doc['party'])): /* internal documents (requisition, work order, count, journal) have no party */ ?>
        <div class="doc-party">
            <div class="doc-label"><?= e($doc['party']['label']) ?></div>
            <div class="doc-party-name"><?= e($doc['party']['name']) ?></div>
            <?php foreach ($doc['party']['lines'] as $line): ?><div><?= e($line) ?></div><?php endforeach ?>
        </div>
        <?php endif ?>
        <table class="doc-meta">
            <tr><th><?= e(t('sales.nbr')) ?></th><td><strong><?= e($doc['nbr']) ?></strong></td></tr>
            <?php foreach ($doc['meta'] as [$label, $value]): ?>
                <?php if ((string) $value !== ''): ?><tr><th><?= e($label) ?></th><td><?= e($value) ?></td></tr><?php endif ?>
            <?php endforeach ?>
        </table>
    </section>

    <div class="doc-table">
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
                <tr class="doc-sub"><td></td><td colspan="<?= $cols - 1 ?>"><?= e($row['sub']) ?></td></tr>
                <?php continue; endif ?>
                <tr>
                    <?php foreach ($row as $i => $cell): ?>
                        <td class="<?= ($lines['columns'][$i]['align'] ?? '') === 'r' ? 'r' : '' ?>"><?= e((string) $cell) ?></td>
                    <?php endforeach ?>
                </tr>
            <?php endforeach ?>
            <tr class="doc-fill"><td colspan="<?= $cols ?>"></td></tr>
            </tbody>
        </table>
    </div>

    <?php if ($totals): ?>
        <section class="doc-summary">
            <div class="doc-words">
                <?php if (($totals['words'] ?? '') !== ''): ?>
                    <div class="doc-label"><?= e(t('sales.amount_words')) ?></div>
                    <div class="doc-words-text"><?= e($totals['words']) ?></div>
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
        <section class="doc-notes">
            <div class="doc-label"><?= e(t('sales.remarks')) ?></div>
            <div><?= nl2br(e((string) $doc['notes'])) ?></div>
        </section>
    <?php endif ?>

    <?php if ($cells): ?>
        <section class="doc-signs" style="grid-template-columns: repeat(<?= $cells ?>, minmax(0, 1fr))">
            <?php foreach ($boxes as $b): ?>
                <div class="doc-sign<?= $b['outside'] ? ' doc-sign-outside' : '' ?>"<?= $b['outside'] ? '' : ' data-slot="' . (int) $b['slot'] . '"' ?>>
                    <div class="doc-sign-label"><?= e($b['label']) ?></div>
                    <div class="doc-sign-area"><?php if (!empty($b['image'])): ?><img src="<?= e($b['image']) ?>" alt=""><?php endif ?></div>
                    <div class="doc-sign-name"><?= !empty($b['signed']) ? e($b['name']) : '' ?></div>
                    <div class="doc-sign-date"><?= !empty($b['signed']) ? e($b['date']) : e(t('sales.date')) . ' ____/____/______' ?></div>
                </div>
            <?php endforeach ?>
            <?php if ($stamp): ?>
                <div class="doc-sign doc-stamp">
                    <div class="doc-sign-label"><?= e(t('sign.stamp_ours')) ?></div>
                    <div class="doc-stamp-area"><?php if ($stamp['image']): ?><img src="<?= e($stamp['image']) ?>" alt=""><?php endif ?></div>
                </div>
                <?php if ($stamp['outside']): ?>
                <div class="doc-sign doc-stamp">
                    <div class="doc-sign-label"><?= e(t('sign.stamp_party', ['party' => $stamp['party']])) ?></div>
                    <div class="doc-stamp-area doc-stamp-blank"></div>
                </div>
                <?php endif ?>
            <?php endif ?>
        </section>
    <?php endif ?>

    <footer class="doc-foot">
        <span><?= e($co['name']) ?> · <?= e($doc['nbr']) ?></span>
        <span><?= e(t('sales.printed_at')) ?> <?= e(date('d/m/Y H:i')) ?></span>
    </footer>
</div>
<?php if (!empty($signPath)): /* what this user may do on each of our boxes, for js/doc-sign.js */ ?>
<script id="doc-sign-data" type="application/json"><?= json_encode([
    'boxes' => array_values(array_filter($boxes, fn ($b) => !$b['outside'])),
    'mine'  => \Phai\Modules\System\SignImage::dataUrl(\Phai\Core\Auth\Auth::userId()),
    'text'  => \Phai\Core\Lang::group(\Phai\Core\Lang::locale(), 'sign') ?: \Phai\Core\Lang::group('th', 'sign'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif ?>
