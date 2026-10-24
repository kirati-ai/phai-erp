<?php
/**
 * Count tags (ป้ายตรวจนับ) to cut out and hang on the stock: eight to an A4 page. A label, not a document, so it
 * has its own small layout instead of SalesPrint. No system quantity on it (a blind count): the counter writes what
 * is there and signs; the tag comes back to be keyed in.
 * @var array  $head  pi_mstr + si_desc
 * @var array  $tags  the count's tags (voided ones left out)
 * @var string $co    company name
 */
$date = implode('/', array_reverse(explode('-', (string) $head['pi_date'])));
$x = fn (string $k) => e(t("stock.$k"));
?>
<style>
    .tags { width: 200mm; margin: 0 auto; padding: 6mm 0; display: grid; grid-template-columns: 1fr 1fr; gap: 0; color: #000; font-size: 11.5px; }
    .tagc { height: 70mm; border: 1px dashed #777; padding: 4mm 5mm; display: flex; flex-direction: column; break-inside: avoid; }
    .tagc:nth-child(8n + 1) { break-before: page; page-break-before: always; }
    .tagc:first-child { break-before: auto; page-break-before: auto; }
    .tagc .top { display: flex; justify-content: space-between; align-items: baseline; border-bottom: 1px solid #000; padding-bottom: 1mm; }
    .tagc .no { font-size: 22px; font-weight: 600; font-variant-numeric: tabular-nums; }
    .tagc .meta { color: #333; font-size: 10px; }
    .tagc dl { display: grid; grid-template-columns: 22mm 1fr; gap: .4mm 2mm; margin: 2mm 0 0; }
    .tagc dt { font-weight: 400; color: #333; }
    .tagc dd { margin: 0; font-weight: 600; }
    .tagc .ln { display: flex; gap: 2mm; align-items: baseline; margin-top: 2.5mm; }
    .tagc .fill { flex: 1; border-bottom: 1px dotted #000; min-height: 5mm; }
    .tagc .big .fill { min-height: 8mm; }
    @page { size: A4; margin: 0; }
    @media print { .no-print { display: none !important; } body { background: #fff; } }
</style>

<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>

<div class="tags">
    <?php foreach ($tags as $tg): ?>
        <div class="tagc">
            <div class="top">
                <span><b><?= $x('tag_card') ?></b> <span class="meta"><?= e($co) ?></span></span>
                <span class="no"><?= $x('tag') ?> <?= (int) $tg['pit_tag'] ?></span>
            </div>
            <div class="meta"><?= e($head['pi_batch']) ?> · <?= e($date) ?> · <?= e($head['pi_site'] . ' ' . ($head['si_desc'] ?? '')) ?></div>
            <dl>
                <dt><?= $x('loc') ?></dt><dd><?= e($tg['pit_loc']) ?></dd>
                <dt><?= $x('part') ?></dt><dd><?= e($tg['pit_part']) ?> <span style="font-weight: 400"><?= e($tg['pt_desc1'] ?? '') ?></span></dd>
                <dt><?= $x('lot') ?></dt><dd><?= e($tg['pit_lot'] !== '' ? $tg['pit_lot'] : '–') ?></dd>
            </dl>
            <div class="ln big"><span><?= $x('count_qty') ?></span><span class="fill"></span><span><?= e($tg['pt_um'] ?? '') ?></span></div>
            <div class="ln"><span><?= $x('counter') ?></span><span class="fill"></span><span><?= $x('sign_checker') ?></span><span class="fill"></span></div>
            <div class="ln"><span><?= $x('tag_counted_at') ?></span><span class="fill"></span></div>
        </div>
    <?php endforeach ?>
</div>
