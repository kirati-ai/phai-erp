<?php
/**
 * Bank book (สมุดบัญชีเงินฝาก) of one month on A4.
 * @var array $bank bk_bank, bk_desc, bk_acct
 * @var array $d    BankController::data(): opening, lines, in, out, closing, month_text
 */
use Phai\Core\View\View;

$m = fn ($v) => number_format((float) $v, 2);
$dt = fn ($v) => $v ? implode('/', array_reverse(explode('-', substr($v, 0, 10)))) : '';
?>
<?= View::partial('doc/print-style') ?>
<?= View::partial('doc/print-toolbar') ?>
<style>
    .doc-lines .s td { font-weight: 600; background: var(--soft); }
    .doc-lines .nw { white-space: nowrap; }
</style>

<div class="doc">
    <?= View::partial('doc/report-head', ['title' => t('accounting.bank_book'), 'meta' => [
        [t('accounting.bank'), $bank['bk_bank'] . ' · ' . $bank['bk_desc']],
        [t('accounting.account'), $bank['bk_acct']],
        [t('accounting.bank_book_month'), $d['month_text']],
    ]]) ?>
    <table class="doc-lines">
        <thead>
        <tr>
            <th><?= e(t('accounting.date')) ?></th><th><?= e(t('accounting.ref')) ?></th><th><?= e(t('accounting.bank_rec_desc')) ?></th>
            <th class="r"><?= e(t('accounting.bank_rec_deposit')) ?></th><th class="r"><?= e(t('accounting.bank_rec_withdraw')) ?></th>
            <th class="r"><?= e(t('accounting.bank_book_balance')) ?></th><th><?= e(t('accounting.bank_book_rec')) ?></th>
        </tr>
        </thead>
        <tbody>
        <tr class="s"><td colspan="5"><?= e(t('accounting.bank_book_opening')) ?></td><td class="r"><?= $m($d['opening']) ?></td><td></td></tr>
        <?php foreach ($d['lines'] as $l): ?>
            <tr>
                <td class="nw"><?= e($dt($l['gl_date'])) ?></td>
                <td class="nw"><?= e($l['gl_type'] . ' ' . $l['gl_ref']) ?></td>
                <td><?= e($l['gl_desc'] ?? '') ?></td>
                <td class="r"><?= (float) $l['gl_amt'] > 0 ? $m($l['gl_amt']) : '' ?></td>
                <td class="r"><?= (float) $l['gl_amt'] < 0 ? $m(-(float) $l['gl_amt']) : '' ?></td>
                <td class="r"><?= $m($l['balance']) ?></td>
                <td class="nw"><?= e($dt($l['rec_date'])) ?></td>
            </tr>
        <?php endforeach ?>
        <tr class="s"><td colspan="3"><?= e(t('accounting.bank_book_closing')) ?></td><td class="r"><?= $m($d['in']) ?></td><td class="r"><?= $m($d['out']) ?></td><td class="r"><?= $m($d['closing']) ?></td><td></td></tr>
        </tbody>
    </table>
</div>
