<?php
/**
 * ภ.ง.ด.3 / ภ.ง.ด.53 on the Revenue Department's own layout: the return (A4 portrait) and its attachment
 * sheets (A4 landscape, six payees a sheet, three payment lines each; ลำดับที่ is per payee and matches the
 * 50 ทวิ certificates). Plain text, no bold, as the paper form.
 * @var array $report   form, month, rows (with seq, branch_no), payees, base, tax (WhtReportController::report)
 * @var array $company  co_name, co_tax_id, co_branch
 * @var array $address  the company address in parts (Address::companyParts())
 */
$f = $report['form'];
$x = fn (string $k, array $p = []) => e(t("pndform.$k", $p));
$m = fn ($v) => number_format((float) $v, 2);
[$y, $mo] = array_map('intval', explode('-', $report['month']));
$be = $y + 543;
$d = function (string $v): string {
    [$yy, $mm, $dd] = explode('-', substr($v, 0, 10));
    return "$dd/$mm/" . ((int) $yy + 543);
};
$pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
$box = fn (bool $on) => '<span class="cb">' . ($on ? '✓' : '') . '</span>';
// digit boxes grouped like the form: tax id 1-4-5-2-1, branch 5
$digits = function (string $value, array $groups): string {
    $out = '';
    $pos = 0;
    foreach ($groups as $n) {
        $out .= '<span class="grp">';
        for ($k = 0; $k < $n; $k++, $pos++) {
            $out .= '<span class="dg">' . e($value[$pos] ?? '') . '</span>';
        }
        $out .= '</span>';
    }
    return $out;
};
$taxId = preg_replace('/\D/', '', (string) ($company['co_tax_id'] ?? ''));
$branch = str_pad(preg_replace('/\D/', '', (string) ($company['co_branch'] ?? '')), 5, '0', STR_PAD_LEFT);
$ad = fn (string $k) => e(trim((string) ($address[$k] ?? '')));

// one block per payee (ลำดับที่), three payment lines each; more payments continue in another block
$blocks = [];
foreach ($report['rows'] as $r) {
    $last = array_key_last($blocks);
    if ($last === null || $blocks[$last]['seq'] !== $r['seq'] || count($blocks[$last]['lines']) === 3) {
        $blocks[] = ['seq' => $r['seq'], 'head' => $r, 'lines' => []];
        $last = array_key_last($blocks);
    }
    $blocks[$last]['lines'][] = $r;
}
$sheets = array_chunk($blocks, 6) ?: [[]];
$sheetCount = count($sheets);
?>
<style>
    .pnd { color: #000; font-size: 11px; line-height: 1.35; margin: 0 auto; }
    .pnd, .pnd * { font-weight: 400 !important; }
    .pnd.ret { page: ret; width: 190mm; padding: 10mm 0 0; font-size: 13px; line-height: 1.6; }
    .pnd.ret .frame { padding: 8px 12px; }
    .pnd.ret .sum td { padding: 7px 6px; }
    .pnd.ret .dg { width: 14px; height: 18px; line-height: 17px; }
    .pnd.ret .cb { width: 13px; height: 13px; line-height: 12px; }
    .pnd.att { page: att; width: 281mm; padding: 7mm 0 0; break-before: page; page-break-before: always; font-size: 12px; }
    @page ret { size: A4 portrait; margin: 0; }
    @page att { size: A4 landscape; margin: 0; }
    .pnd .frame { border: 1px solid #000; border-radius: 6px; padding: 5px 8px; }
    .pnd .top { display: flex; gap: 6px; align-items: stretch; }
    .pnd .top .frame:first-child { flex: 1; text-align: center; }
    .pnd .badge { border: 1.5px solid #000; border-radius: 8px; font-size: 26px; padding: 4px 14px; display: flex; align-items: center; }
    .pnd .t { font-size: 15px; }
    .pnd .grp { display: inline-flex; margin-right: 3px; }
    .pnd .dg { display: inline-block; width: 11px; height: 14px; border: 1px solid #000; border-left-width: 0; text-align: center; line-height: 13px; }
    .pnd .grp .dg:first-child { border-left-width: 1px; }
    .pnd .cb { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; margin: 0 3px -2px 0; text-align: center; line-height: 10px; font-size: 10px; }
    .pnd .row2 { display: flex; gap: 6px; margin-top: 6px; }
    .pnd .row2 > .frame { flex: 1; }
    .pnd .row2 > .frame:first-child { flex: 1.35; }
    .pnd .ln { display: flex; gap: 5px; align-items: baseline; margin-top: 3px; white-space: nowrap; }
    .pnd .fill { flex: 1; border-bottom: 1px dotted #000; min-width: 0; padding: 0 3px; min-height: 13px; overflow: hidden; text-overflow: ellipsis; }
    .pnd .hint { font-size: 9.5px; font-style: italic; }
    .pnd .months { display: grid; grid-template-columns: repeat(4, auto); gap: 4px 8px; margin-top: 4px; white-space: nowrap; font-size: 11px; }
    .pnd .sum { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .pnd .sum td { padding: 3px 4px; }
    .pnd .sum td.r { border: 1px solid #000; text-align: right; width: 38mm; font-variant-numeric: tabular-nums; }
    .pnd .sign { margin-top: 14px; text-align: center; position: relative; padding-bottom: 14px !important; }
    .pnd .seal { position: absolute; right: 14mm; top: 38px; width: 15mm; height: 15mm; border: 1px solid #000; border-radius: 50%; font-size: 8px; display: flex; align-items: center; justify-content: center; text-align: center; }
    .pnd table.att-t { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .pnd .att-t th, .pnd .att-t td { border: 1px solid #000; padding: 1px 4px; vertical-align: top; }
    .pnd .att-t th { text-align: center; vertical-align: middle; font-size: 10px; }
    .pnd .att-t td.r { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .pnd .att-t td.c { text-align: center; white-space: nowrap; }
    .pnd .att-t td.ln3 { vertical-align: middle; }
    .pnd .att-t tbody tr { height: 26px; }
    .pnd .att-head { display: flex; align-items: center; gap: 10px; }
    .pnd .att-head .t { font-size: 20px; }
    .pnd .att-foot { display: flex; gap: 8px; margin-top: 4px; font-size: 9.5px; }
    .pnd .att-foot > div:first-child { flex: 1; }
    @media print { .no-print { display: none !important; } body { background: #fff; } }
</style>

<?= \Phai\Core\View\View::partial('doc/print-toolbar') ?>

<div class="pnd ret">
    <div class="top">
        <div class="frame"><span class="t"><?= $x('title') ?></span><br><?= $x("law_$f") ?></div>
        <div class="badge"><?= $x("form_$f") ?></div>
    </div>
    <div class="row2">
        <div class="frame">
            <div class="ln" style="white-space: normal"><?= $x('payer_tax') ?></div>
            <div class="ln"><?= $digits($taxId, [1, 4, 5, 2, 1]) ?> <span style="margin-left: auto"><?= $x('branch') ?> <?= $digits($branch, [5]) ?></span></div>
            <div class="ln"><?= $x('payer_name') ?></div>
            <div class="ln"><span class="fill"><?= e($company['co_name'] ?? '') ?></span></div>
            <div class="ln"><?= $x('address') ?> <?= $x('a_building') ?> <span class="fill"><?= $ad('ad_building') ?></span> <?= $x('a_room') ?> <span class="fill" style="flex: .4"><?= $ad('ad_room') ?></span> <?= $x('a_floor') ?> <span class="fill" style="flex: .3"><?= $ad('ad_floor') ?></span></div>
            <div class="ln"><?= $x('a_village') ?> <span class="fill"><?= $ad('ad_village') ?></span> <?= $x('a_no') ?> <span class="fill"><?= $ad('ad_house_no') ?></span> <?= $x('a_moo') ?> <span class="fill" style="flex: .3"><?= $ad('ad_moo') ?></span></div>
            <div class="ln"><?= $x('a_soi') ?> <span class="fill"><?= $ad('ad_soi') ?></span> <?= $x('a_yaek') ?> <span class="fill"><?= $ad('ad_yaek') ?></span> <?= $x('a_road') ?> <span class="fill"><?= trim($ad('ad_road') . ' ' . $ad('ad_line2')) ?></span></div>
            <div class="ln"><?= $x('a_sub') ?> <span class="fill"><?= e($address['ad_sub_district'] ?? '') ?></span> <?= $x('a_city') ?> <span class="fill"><?= e($address['ad_city'] ?? '') ?></span></div>
            <div class="ln"><?= $x('a_province') ?> <span class="fill"><?= e($address['ad_state'] ?? '') ?></span> <?= $x('a_zip') ?> <?= $digits(preg_replace('/\D/', '', (string) ($address['ad_zip'] ?? '')), [5]) ?></div>
            <div class="ln" style="margin-top: 8px"><?= $box(true) ?><?= $x('normal') ?> &nbsp; <?= $box(false) ?><?= $x('extra') ?> ......</div>
        </div>
        <div class="frame">
            <div><?= $x('month_paid') ?></div>
            <div class="hint"><?= $x('month_hint') ?> <?= $be ?></div>
            <div class="months">
                <?php foreach ([1, 4, 7, 10, 2, 5, 8, 11, 3, 6, 9, 12] as $n): ?>
                    <span><?= $box($n === $mo) ?><?= $x("m$n") ?></span>
                <?php endforeach ?>
            </div>
            <div style="margin-top: 8px"><?= $x('remit') ?></div>
            <?php foreach (\Phai\Core\Lang::group('th', 'pndform')["remit_$f"] as $i => $law): // the form is Thai only ?>
                <div><?= $box($i === 0) ?><?= e($law) ?></div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="frame" style="margin-top: 6px; display: flex; gap: 10px">
        <div style="width: 70mm"><?= $x('detail') ?><div class="hint"><?= $x('detail_hint', ['form' => t("pndform.form_$f")]) ?></div></div>
        <div style="flex: 1">
            <div class="ln"><?= $box(true) ?><?= $x('attached', ['form' => t("pndform.form_$f")]) ?>
                <span style="margin-left: auto"><?= $x('count') ?> <span class="fill" style="display: inline-block; min-width: 14mm; text-align: center"><?= $report['payees'] ?></span> <?= $x('people') ?>
                &nbsp; <?= $x('count') ?> <span class="fill" style="display: inline-block; min-width: 14mm; text-align: center"><?= $report['payees'] ? $sheetCount : '' ?></span> <?= $x('sheets') ?></span></div>
            <div class="ln"><?= $x('or') ?></div>
            <div class="ln"><?= $box(false) ?><?= $x('media') ?> <span style="margin-left: auto"><?= $x('count') ?> ........ <?= $x('people') ?> &nbsp; <?= $x('count') ?> ........ <?= $x('sheets') ?></span></div>
        </div>
    </div>

    <div class="frame" style="margin-top: 6px">
        <table class="sum">
            <tr><td style="text-align: center"><?= $x('summary') ?></td><td class="r" style="text-align: center"><?= $x('amount') ?></td></tr>
            <tr><td><?= $x('s1') ?></td><td class="r"><?= $m($report['base']) ?></td></tr>
            <tr><td><?= $x('s2') ?></td><td class="r"><?= $m($report['tax']) ?></td></tr>
            <tr><td><?= $x('s3') ?></td><td class="r"></td></tr>
            <tr><td><?= $x('s4') ?></td><td class="r"><?= $m($report['tax']) ?></td></tr>
        </table>
    </div>

    <div class="frame sign" style="margin-top: 6px">
        <?= $x('certify') ?>
        <div style="margin-top: 16px"><?= $x('sign') ?> ............................................. <?= $x('payer') ?></div>
        <div>( ............................................. )</div>
        <div><?= $x('position') ?> .............................................</div>
        <div><?= $x('filed') ?></div>
        <div class="seal"><?= $x('seal') ?></div>
    </div>
    <div class="hint" style="margin-top: 4px"><?= $x('info') ?></div>
</div>

<?php foreach ($sheets as $si => $sheet): ?>
<div class="pnd att">
    <div class="att-head">
        <span class="t"><?= $x('attach') ?> <?= $x("form_$f") ?></span>
        <span><?= $x('payer_tax') ?> <?= $digits($taxId, [1, 4, 5, 2, 1]) ?></span>
        <span style="margin-left: auto"><?= $x('branch') ?> <?= $digits($branch, [5]) ?><br><?= $x('sheet_of', ['n' => $si + 1, 'of' => $sheetCount]) ?></span>
    </div>
    <table class="att-t">
        <thead>
        <tr>
            <th rowspan="2" style="width: 9mm"><?= $x('seq') ?></th>
            <th rowspan="2"><?= $x('payee_tax') ?><br><?= $x("payee_$f") ?></th>
            <th rowspan="2" style="width: 20mm"><?= $x('branch') ?></th>
            <th colspan="4"><?= $x('pay_detail') ?></th>
            <th rowspan="2" style="width: 30mm"><?= $x("tax_$f") ?></th>
            <th rowspan="2" style="width: 12mm"><?= $x('condition') ?></th>
        </tr>
        <tr>
            <th style="width: 22mm"><?= $x('date_paid') ?></th>
            <th style="width: 48mm"><?= $x("income_$f") ?></th>
            <th style="width: 12mm"><?= $x('rate') ?></th>
            <th style="width: 32mm"><?= $x("paid_$f") ?></th>
        </tr>
        </thead>
        <tbody>
        <?php for ($b = 0; $b < 6; $b++):
            $block = $sheet[$b] ?? null;
            $h = $block['head'] ?? null;
            $name = (string) ($h['name'] ?? '');
            // ภ.ง.ด.3 asks for the given name and the surname apart
            [$given, $surname] = $f === 'PND3' && str_contains($name, ' ') ? [substr($name, 0, strrpos($name, ' ')), substr($name, strrpos($name, ' ') + 1)] : [$name, ''];
            for ($l = 0; $l < 3; $l++):
                $p = $block['lines'][$l] ?? null; ?>
            <tr>
                <?php if ($l === 0): ?>
                    <td rowspan="3" class="c"><?= e((string) ($block['seq'] ?? '')) ?></td>
                    <td rowspan="3">
                        <?= $digits(preg_replace('/\D/', '', (string) ($h['tax_id'] ?? '')), [1, 4, 5, 2, 1]) ?>
                        <div class="ln"><?= $x('name') ?> <span class="fill"><?= e($given) ?></span>
                            <?php if ($f === 'PND3'): ?><?= $x('surname') ?> <span class="fill"><?= e($surname) ?></span><?php endif ?></div>
                        <div class="ln"><?= $x('addr') ?> <span class="fill" style="font-size: 9.5px"><?= e($h['address'] ?? '') ?></span></div>
                    </td>
                    <td rowspan="3" class="c"><?= $h ? $digits((string) $h['branch_no'], [5]) : $digits('', [5]) ?></td>
                <?php endif ?>
                <td class="c ln3"><?= $p ? e($d($p['date'])) : '' ?></td>
                <td class="ln3" style="font-size: 9.5px"><?= $p ? e(preg_replace('/\s*\d+(\.\d+)?\s*%\s*$/u', '', (string) $p['income'])) : '' ?></td>
                <td class="c ln3"><?= $p ? e($pct($p['pct'])) : '' ?></td>
                <td class="r ln3"><?= $p ? $m($p['base']) : '' ?></td>
                <td class="r ln3"><?= $p ? $m($p['tax']) : '' ?></td>
                <td class="c ln3"><?= $p ? '1' : '' ?></td>
            </tr>
        <?php endfor; endfor ?>
        <?php $lines = array_merge(...array_map(fn ($bk) => $bk['lines'], $sheet ?: [['lines' => []]])); ?>
        <tr>
            <td colspan="6" style="text-align: right"><?= $x('sheet_total', ['form' => t("pndform.form_$f")]) ?></td>
            <td class="r"><?= $m(array_sum(array_column($lines, 'base'))) ?></td>
            <td class="r"><?= $m(array_sum(array_column($lines, 'tax'))) ?></td>
            <td></td>
        </tr>
        </tbody>
    </table>
    <div class="att-foot">
        <div><?= $x("seq_hint_$f") ?><br><?= $x('note') ?> 1. <?= $x("note1_$f") ?><br>2. <?= $x("note2_$f") ?></div>
        <div class="frame" style="width: 85mm; position: relative; font-size: 10.5px">
            <?= $x('sign') ?> ........................................ <?= $x('payer') ?><br>
            ( ........................................ )<br>
            <?= $x('position') ?> ........................................<br>
            <?= $x('filed') ?>
        </div>
    </div>
</div>
<?php endforeach ?>
