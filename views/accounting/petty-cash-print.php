<?php
/**
 * ทะเบียนเงินสดย่อย of one box for a month (PettyCashController::register) on A4: brought forward, every entry with its
 * running balance, the month-end count by notes and coins, and the holder's and checker's signatures.
 * @var array $box     PettyCashController::boxData()
 */
$T = fn (string $k, array $p = []) => e(t("pettycash.$k", $p));
$m = fn ($v) => number_format((float) $v, 2);
$d = fn ($v) => $v ? implode('/', array_reverse(explode('-', substr((string) $v, 0, 10)))) : '';
$count = $box['count'];
?>
<?= \Phai\Core\View\View::partial('doc/print-style') ?>
<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>
<style>
    .pc-reg { width: 100%; border-collapse: collapse; }
    .pc-reg th { background: var(--soft); font-weight: 600; font-size: 11.5px; text-align: left; padding: 6px 8px; border-bottom: 2px solid var(--accent); }
    .pc-reg td { padding: 5px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
    .pc-reg .r { text-align: right; }
    .pc-reg tr.x td { color: var(--muted); text-decoration: line-through; }
    .pc-reg tr.t td { font-weight: 600; border-top: 1px solid #999; }
    .pc-count { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 14px; }
    .pc-count table { width: 100%; border-collapse: collapse; }
    .pc-count td, .pc-count th { padding: 3px 8px; border-bottom: 1px solid var(--line); }
    .pc-signs { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px; text-align: center; }
    .pc-signs div { border-top: 1px dotted #000; padding-top: 4px; }
</style>
<div class="doc">
    <?= \Phai\Core\View\View::partial('doc/report-head', ['title' => t('pettycash.register'), 'meta' => [
        [t('pettycash.month'), $box['month_text']],
        [t('pettycash.holder'), $box['name']],
    ]]) ?>
    <table class="pc-reg">
        <thead>
        <tr>
            <th><?= $T('date') ?></th><th><?= $T('nbr') ?></th><th><?= $T('what') ?></th>
            <th class="r"><?= $T('in') ?></th><th class="r"><?= $T('out') ?></th><th class="r"><?= $T('balance') ?></th>
        </tr>
        </thead>
        <tbody>
        <tr><td></td><td></td><td><?= $T('opening') ?><?= $box['opening_from'] ? ' · ' . e($box['opening_from']) : '' ?></td><td></td><td></td><td class="r"><?= $m($box['opening']) ?></td></tr>
        <?php foreach ($box['rows'] as $r): ?>
            <tr class="<?= $r['cb_status'] === 'X' ? 'x' : '' ?>">
                <td><?= e($d($r['cb_date'])) ?></td>
                <td><?= e($r['cb_nbr']) ?></td>
                <td><?= e(trim($r['text'] . ($r['cb_remarks'] ? ' · ' . $r['cb_remarks'] : ''))) ?><?= $r['cb_status'] === 'X' ? ' (' . $T('cancelled') . ')' : '' ?></td>
                <td class="r"><?= $r['amt'] > 0 ? $m($r['amt']) : '' ?></td>
                <td class="r"><?= $r['amt'] < 0 ? $m(-$r['amt']) : '' ?></td>
                <td class="r"><?= $m($r['bal']) ?></td>
            </tr>
        <?php endforeach ?>
        <tr class="t"><td></td><td></td><td><?= $T('closing') ?></td><td></td><td></td><td class="r"><?= $m($box['closing']) ?></td></tr>
        </tbody>
    </table>

    <?php if ($count): ?>
        <div class="pc-count">
            <table>
                <tr><th style="text-align:left">฿</th><th class="r"><?= $T('pieces') ?></th><th class="r"><?= $T('amount') ?></th></tr>
                <?php foreach ($count['notes'] as $note => $n): ?>
                    <tr><td><?= e(rtrim(rtrim(number_format((float) $note, 2), '0'), '.')) ?></td><td class="r"><?= (int) $n ?></td><td class="r"><?= $m((float) $note * (int) $n) ?></td></tr>
                <?php endforeach ?>
            </table>
            <table>
                <tr><td><?= $T('book') ?></td><td class="r"><?= $m($count['cbc_book']) ?></td></tr>
                <tr><td><?= $T('counted') ?></td><td class="r"><?= $m($count['cbc_counted']) ?></td></tr>
                <tr><td><b><?= $T('diff') ?></b></td><td class="r"><b><?= $m($count['cbc_diff']) ?></b></td></tr>
                <tr><td colspan="2" class="r" style="color: var(--muted)"><?= $T('counted_as', ['date' => $d($count['created_at']), 'who' => $count['counter']]) ?></td></tr>
                <?php if ($count['cbc_remarks']): ?><tr><td colspan="2"><?= e($count['cbc_remarks']) ?></td></tr><?php endif ?>
            </table>
        </div>
    <?php endif ?>

    <div class="pc-signs">
        <div><?= $T('sign_holder') ?> / <?= $T('sign_counter') ?></div>
        <div><?= $T('sign_witness') ?></div>
    </div>
</div>
