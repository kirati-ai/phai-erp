<?php
/**
 * Bank reconciliation statement (งบกระทบยอดเงินฝากธนาคาร) on A4.
 * @var array $bank    bk_bank, bk_desc, bk_acct
 * @var array $rec     bkr_mstr row
 * @var array $in      deposits in transit (posted, not on the statement yet)
 * @var array $out     outstanding payments (posted, not on the statement yet)
 */
use Phai\Core\View\View;

$m = fn ($v) => number_format((float) $v, 2);
$d = fn ($v) => $v ? implode('/', array_reverse(explode('-', substr($v, 0, 10)))) : '';
$sumIn = array_sum(array_map(fn ($l) => (float) $l['gl_amt'], $in));
$sumOut = -array_sum(array_map(fn ($l) => (float) $l['gl_amt'], $out));
$lines = function (array $list, int $sign) use ($m, $d) {
    foreach ($list as $l) {
        echo '<tr><td class="ind">' . e($d($l['gl_date'])) . '</td><td>' . e($l['gl_type'] . ' ' . $l['gl_ref']) . '</td><td>'
            . e($l['gl_desc'] ?? '') . '</td><td class="r">' . $m($sign * (float) $l['gl_amt']) . '</td><td></td></tr>';
    }
};
?>
<?= View::partial('doc/print-style') ?>
<?= View::partial('doc/print-toolbar') ?>
<style>
    .rec { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .rec td { padding: 4px 6px; vertical-align: top; }
    .rec .r { width: 30mm; }
    .rec .ind { padding-left: 18px; white-space: nowrap; width: 24mm; }
    .rec .h td { font-weight: 600; padding-top: 10px; }
    .rec .t td { font-weight: 600; border-top: 1px solid #000; }
    .rec .g td { font-weight: 600; border-top: 1px solid #000; border-bottom: 3px double #000; }
    .rec-sign { display: flex; justify-content: space-around; gap: 20mm; margin-top: 30mm; text-align: center; }
</style>

<div class="doc">
    <?= View::partial('doc/report-head', ['title' => t('accounting.bank_rec_statement'), 'meta' => [
        [t('accounting.bank'), $bank['bk_bank'] . ' · ' . $bank['bk_desc']],
        [t('accounting.account'), $bank['bk_acct']],
        [t('accounting.bank_rec_date'), $d($rec['bkr_date'])],
    ]]) ?>
    <table class="rec">
        <tr class="h"><td colspan="3"><?= e(t('accounting.bank_rec_balance')) ?></td><td></td><td class="r"><?= $m($rec['bkr_balance']) ?></td></tr>
        <tr class="h"><td colspan="5"><?= e(t('accounting.bank_rec_plus_in')) ?></td></tr>
        <?php $lines($in, 1) ?>
        <tr class="t"><td colspan="3"></td><td></td><td class="r"><?= $m($sumIn) ?></td></tr>
        <tr class="h"><td colspan="5"><?= e(t('accounting.bank_rec_less_out')) ?></td></tr>
        <?php $lines($out, -1) ?>
        <tr class="t"><td colspan="3"></td><td></td><td class="r">(<?= $m($sumOut) ?>)</td></tr>
        <tr class="g"><td colspan="3"><?= e(t('accounting.bank_rec_book')) ?></td><td></td><td class="r"><?= $m((float) $rec['bkr_balance'] + $sumIn - $sumOut) ?></td></tr>
    </table>
    <?php if (round((float) $rec['bkr_balance'] + $sumIn - $sumOut - (float) $rec['bkr_book'], 2) != 0.0): ?>
        <p style="margin-top: 8px"><?= e(t('accounting.bank_rec_book_now')) ?> <?= $m($rec['bkr_book']) ?></p>
    <?php endif ?>
    <div class="rec-sign">
        <div>........................................<br><?= e(t('accounting.bank_rec_prepared')) ?></div>
        <div>........................................<br><?= e(t('accounting.bank_rec_approved')) ?></div>
    </div>
</div>
