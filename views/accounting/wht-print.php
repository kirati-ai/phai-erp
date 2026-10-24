<?php
/**
 * Withholding tax certificate on the Revenue Department's own 50 ทวิ layout, in the two copies the form
 * calls for: ฉบับที่ 1 (the payee files it with the tax return) and ฉบับที่ 2 (the payee keeps it) — one A4 each.
 * @var array  $w         wht_cert + vendor + wht_mstr (wht_desc, wht_pct, wht_form_row)
 * @var array  $lines     one per rate withheld: desc, pct, row (income row on the form), base, tax
 * @var array  $payer     name, tax, branch, address
 * @var array  $payee     name, tax, branch, address
 * @var string $words     the tax in words
 * @var ?int   $seq       ลำดับที่ in the month's ภ.ง.ด. attachment
 * @var string $payerTax  13 digits
 * @var string $payeeTax  13 digits
 */
$m = fn ($v) => number_format((float) $v, 2);
// วัน เดือน ปี (พ.ศ.)
$d = function (?string $v): string {
    if (!$v) {
        return '';
    }
    [$y, $mo, $dd] = explode('-', substr($v, 0, 10));
    return "$dd/$mo/" . ((int) $y + 543);
};
$x = fn (string $k) => e(t("whtform.$k"));
$byRow = [];
foreach ($lines as $l) {
    $r = (string) $l['row'];
    // a further rate on a row already used goes to "6. อื่น ๆ (ระบุ)", written there with its rate
    if (isset($byRow[$r]) && $r !== '6' && !isset($byRow['6'])) {
        $r = '6';
    }
    $byRow[$r][] = $l;
}
$rate = fn (array $l) => e(\Phai\Modules\Accounting\WhtController::rateText($l['desc'], $l['pct']));
$void = $w['whtc_status'] === 'C';
$digits = function (string $tax): string {
    // 13 boxes grouped like the form: 1-4-5-2-1
    $out = '';
    foreach ([1, 4, 5, 2, 1] as $i => $n) {
        $out .= '<span class="grp">';
        for ($k = 0; $k < $n; $k++) {
            $pos = array_sum(array_slice([1, 4, 5, 2, 1], 0, $i)) + $k;
            $out .= '<span class="dg">' . e($tax[$pos] ?? '') . '</span>';
        }
        $out .= '</span>';
    }
    return $out;
};
$box = fn (bool $on) => '<span class="cb">' . ($on ? '✓' : '') . '</span>';
// each rate withheld is a line under its income row, with its rate, date, amount paid and tax; other rows stay empty
$cells = fn (?array $l) => '<td class="c">' . ($l ? e($d($w['whtc_date'])) : '') . '</td><td class="r">' . ($l ? e($m($l['base'])) : '')
    . '</td><td class="r">' . ($l ? e($m($l['tax'])) : '') . '</td>';
$rows = [
    ['1', 'r1', 0], ['2', 'r2', 0], ['3', 'r3', 0], ['4a', 'r4a', 0], ['4b', 'r4b', 1],
    [null, 'r4b1', 2], [null, 'r4b11', 3], [null, 'r4b12', 3], [null, 'r4b13', 3], [null, 'r4b14', 3],
    [null, 'r4b2', 2], [null, 'r4b21', 3], [null, 'r4b22', 3], [null, 'r4b23', 3], [null, 'r4b24', 3], [null, 'r4b25', 3],
    ['5', 'r5', 0], ['6', 'r6', 0],
];
?>
<style>
    .w50 { width: 194mm; margin: 0 auto; padding: 7mm 0 0; color: #000; font-size: 11.5px; line-height: 1.4; }
    .w50 + .w50 { break-before: page; page-break-before: always; }
    .w50 .copy { font-style: italic; font-size: 10px; }
    .w50 .frame { border: 1.2px solid #000; border-radius: 6px; padding: 4px 8px; margin-top: 4px; position: relative; }
    .w50 .head { display: flex; align-items: flex-start; }
    .w50 .head .t { flex: 1; text-align: center; }
    .w50 .head .t b { font-size: 15px; }
    .w50 .head .no { width: 44mm; text-align: left; }
    .w50 .ln { display: flex; gap: 6px; align-items: baseline; margin-top: 3px; }
    .w50 .fill { flex: 1; border-bottom: 1px dotted #000; min-height: 14px; padding: 0 4px; }
    .w50 .hint { font-size: 9.5px; font-style: italic; margin: 0 0 0 26px; }
    .w50 .grp { display: inline-flex; margin-right: 4px; }
    .w50 .dg { display: inline-block; width: 12px; height: 15px; border: 1px solid #000; border-left-width: 0; text-align: center; line-height: 14px; font-weight: 600; }
    .w50 .grp .dg:first-child { border-left-width: 1px; }
    .w50 .cb { display: inline-block; width: 12px; height: 12px; border: 1px solid #000; margin: 0 3px -2px 0; text-align: center; line-height: 11px; font-size: 11px; font-weight: 700; }
    .w50 .forms { display: grid; grid-template-columns: repeat(4, auto); gap: 2px 16px; margin-left: 8px; }
    .w50 .seqbox { display: inline-block; min-width: 22mm; border: 1px solid #000; text-align: center; font-weight: 600; }
    .w50 table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .w50 th, .w50 td { border: 1px solid #000; padding: 2px 5px; vertical-align: top; }
    .w50 th { font-weight: 600; text-align: center; vertical-align: middle; }
    .w50 td.r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .w50 td.c { text-align: center; white-space: nowrap; }
    .w50 td.inc { border-top: 0; border-bottom: 0; }
    .w50 tr.rate td.inc { font-size: 11.5px; }
    .w50 .i1 { padding-left: 14px; } .w50 .i2 { padding-left: 26px; } .w50 .i3 { padding-left: 44px; }
    .w50 .tot td { font-weight: 600; }
    .w50 .words { background: #e6e6e6; font-weight: 600; text-align: center; }
    .w50 .bottom { display: flex; gap: 4px; margin-top: 4px; }
    .w50 .bottom .frame { margin-top: 0; }
    .w50 .warn { width: 62mm; font-size: 9.5px; }
    .w50 .cert { flex: 1; text-align: center; }
    .w50 .seal { position: absolute; right: 8px; bottom: 8px; width: 15mm; height: 15mm; border: 1px solid #000; border-radius: 50%; font-size: 8px; display: flex; align-items: center; justify-content: center; text-align: center; }
    .w50 .note { font-size: 8.5px; margin-top: 3px; }
    .w50 .void { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 60px; color: rgba(200, 0, 0, .25); transform: rotate(-20deg); font-weight: 700; pointer-events: none; }
    /* the form is plain text: no bold anywhere */
    .w50, .w50 * { font-weight: 400 !important; }
    @page { size: A4; margin: 0; }
    @media print { .no-print { display: none !important; } body { background: #fff; } }
</style>

<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>

<?php foreach ([1, 2] as $copy): ?>
<div class="w50" style="position: relative">
    <?php if ($void): ?><div class="void"><?= $x('void') ?></div><?php endif ?>
    <div class="copy"><?= $x("copy_$copy") ?></div>
    <div class="frame">
        <div class="head">
            <div class="t"><b><?= $x('title') ?></b><br><?= $x('sub') ?></div>
            <div class="no"><?= $x('book') ?> ..........<br><?= $x('nbr') ?> <b><?= e($w['whtc_nbr']) ?></b></div>
        </div>

        <div class="frame">
            <div class="ln"><b><?= $x('payer') ?></b><span style="margin-left: auto"><?= $x('tax13') ?> <?= $digits($payerTax) ?></span></div>
            <div class="ln"><b><?= $x('name') ?></b><span class="fill"><?= e($payer['name']) ?></span><span><?= $x('branch') ?> <?= e($payer['branch']) ?></span></div>
            <div class="hint"><?= $x('name_hint') ?></div>
            <div class="ln"><b><?= $x('address') ?></b><span class="fill"><?= e($payer['address']) ?></span></div>
            <div class="hint"><?= $x('address_hint') ?></div>
        </div>

        <div class="frame">
            <div class="ln"><b><?= $x('payee') ?></b><span style="margin-left: auto"><?= $x('tax13') ?> <?= $digits($payeeTax) ?></span></div>
            <div class="ln"><b><?= $x('name') ?></b><span class="fill"><?= e($payee['name']) ?></span><span><?= $x('branch') ?> <?= e($payee['branch']) ?></span></div>
            <div class="hint"><?= $x('name_hint') ?></div>
            <div class="ln"><b><?= $x('address') ?></b><span class="fill"><?= e($payee['address']) ?></span></div>
            <div class="hint"><?= $x('address_hint') ?></div>
            <div class="ln" style="margin-top: 5px; align-items: flex-start">
                <div><b><?= $x('seq') ?></b> <span class="seqbox"><?= e((string) ($seq ?? '')) ?></span> <?= $x('in_form') ?>
                    <div class="hint" style="margin: 0; max-width: 48mm"><?= $x('seq_hint') ?></div></div>
                <div class="forms">
                    <?php foreach (['f1' => '', 'f2' => '', 'f3' => '', 'f4' => 'PND3', 'f5' => '', 'f6' => '', 'f7' => 'PND53'] as $f => $type): ?>
                        <span><?= $box($type !== '' && $w['whtc_form_type'] === $type) ?><?= $x($f) ?></span>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <table>
            <thead>
            <tr>
                <th><?= $x('col_income') ?></th>
                <th style="width: 24mm"><?= $x('col_date') ?></th>
                <th style="width: 28mm"><?= $x('col_paid') ?></th>
                <th style="width: 26mm"><?= $x('col_tax') ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as [$key, $text, $indent]):
                // 6. อื่น ๆ (ระบุ) takes its rate on the line itself; other rows list theirs under the row text
                $inline = $key === '6' ? $byRow['6'][0] ?? null : null;
                $under = $key !== null ? array_slice($byRow[$key] ?? [], $inline ? 1 : 0) : [];
                ?>
                <tr>
                    <td class="inc i<?= $indent ?>"><?= $x($text) ?><?= $inline ? ' ' . $rate($inline) : '' ?></td>
                    <?= $inline ? $cells($inline) : ($key !== null && !$under ? $cells(null) : '<td class="inc"></td><td class="inc"></td><td class="inc"></td>') ?>
                </tr>
                <?php foreach ($under as $l): ?>
                    <tr class="rate">
                        <td class="inc i<?= $indent + 1 ?>">- <?= $rate($l) ?></td>
                        <?= $cells($l) ?>
                    </tr>
                <?php endforeach ?>
            <?php endforeach ?>
            <tr class="tot">
                <td style="text-align: right" colspan="2"><?= $x('total') ?></td>
                <td class="r"><?= e($m($w['whtc_base_amt'])) ?></td>
                <td class="r"><?= e($m($w['whtc_tax_amt'])) ?></td>
            </tr>
            <tr>
                <td colspan="4"><b><?= $x('total_words') ?></b> <span class="words" style="display: inline-block; min-width: 120mm">(<?= e($words) ?>)</span></td>
            </tr>
            </tbody>
        </table>

        <div class="frame">
            <b><?= $x('funds') ?></b> <?= $x('fund_gpf') ?> ............ <?= $x('baht') ?>
            &nbsp; <?= $x('fund_sso') ?> ............ <?= $x('baht') ?> &nbsp; <?= $x('fund_pvd') ?> ............ <?= $x('baht') ?>
        </div>
        <div class="frame">
            <b><?= $x('payer_mode') ?></b> &nbsp;
            <?= $box(true) ?><?= $x('mode1') ?> &nbsp; <?= $box(false) ?><?= $x('mode2') ?> &nbsp;
            <?= $box(false) ?><?= $x('mode3') ?> &nbsp; <?= $box(false) ?><?= $x('mode4') ?> ............
        </div>

        <div class="bottom">
            <div class="frame warn"><b><?= $x('warning') ?></b> <?= $x('warning_text') ?></div>
            <div class="frame cert" style="min-height: 25mm">
                <?= $x('certify') ?>
                <div style="margin-top: 14px"><?= $x('sign') ?> ........................................ <?= $x('signer') ?></div>
                <div style="margin-top: 4px"><b><?= e($d($w['whtc_date'])) ?></b></div>
                <div><?= $x('issued') ?></div>
                <div class="seal"><?= $x('seal') ?></div>
            </div>
        </div>
    </div>
    <div class="note"><?= $x('note') ?></div>
</div>
<?php endforeach ?>
