<?php
/**
 * What EnvelopePdf draws over every page of a signed document: one A4 sheet per page, with the page (w × h points, as
 * shown) drawn at the sheet's top left scaled by k. On it: the fields, shrunk with the page's own content (shrink,
 * kept at the top and centred), and the envelope footer in the strip that leaves at the bottom. Transparent, so the
 * page shows through. No script: phai-pdf renders with JavaScript off.
 * @var array  $sheets [{w, h, k, shrink, fields: [{type, x, y, w, h, props, text?, image?, caption?, times?, checked?}], foot: {page, where}}]
 * @var string $nbr, $code, $qr
 * @var int    $total
 */
$pt = fn (float $v) => rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.') . 'pt';
?>
<!doctype html>
<html><head><meta charset="utf-8">
<link href="<?= e(asset('fonts/ibm-plex-sans-thai/ibm-plex-sans-thai.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('fonts/handwriting/handwriting.css')) ?>" rel="stylesheet">
<style>
@page { size: A4; margin: 0; }
html, body { margin: 0; padding: 0; background: transparent; }
* { box-sizing: border-box; }
.sheet { position: relative; width: 595pt; height: 841pt; overflow: hidden; break-after: page; page-break-after: always; }
.sheet:last-child { break-after: auto; page-break-after: auto; }
.pg, .fl { position: absolute; left: 0; top: 0; transform-origin: 0 0; }
.f { position: absolute; display: flex; align-items: center; font-family: 'IBM Plex Sans Thai', Tahoma, sans-serif; color: #111; line-height: 1.2; }
.f img { max-width: 100%; max-height: 100%; object-fit: contain; display: block; }
.f.sig { align-items: flex-end; justify-content: center; }
.f.label { align-items: flex-start; white-space: pre-wrap; }
.f.mark { justify-content: center; border-style: solid; border-radius: 3pt; font-weight: 700; letter-spacing: .5pt; text-align: center; }
.f.box { justify-content: center; }
.cap { position: absolute; left: 0; top: 100%; padding-top: 1pt; font-size: 5.5pt; line-height: 1.3; color: #333; white-space: nowrap; font-family: 'IBM Plex Sans Thai', Tahoma, sans-serif; }
.ft { position: absolute; display: flex; align-items: center; gap: 5pt; color: #555; font-family: 'IBM Plex Sans Thai', Tahoma, sans-serif; border-top: .4pt solid #bbb; white-space: nowrap; }
.ft .t { flex: 1; overflow: hidden; }
.ft img { display: block; }
</style></head><body>
<?php foreach ($sheets as $s):
    $w = (float) $s['w'];
    $h = (float) $s['h'];
    $strip = (1 - $s['shrink']) * $h;
    $fh = $s['shrink'] < 1 ? max(8.0, min(22.0, $strip - 2)) : 22.0;
    ?>
<div class="sheet"><div class="pg" style="width:<?= $pt($w) ?>;height:<?= $pt($h) ?>;transform:scale(<?= round($s['k'], 6) ?>)">
    <div class="fl" style="width:<?= $pt($w) ?>;height:<?= $pt($h) ?>;transform:translate(<?= $pt((1 - $s['shrink']) * $w / 2) ?>,0) scale(<?= $s['shrink'] ?>)">
    <?php foreach ($s['fields'] as $f):
        $p = $f['props'] ?? [];
        $size = (float) ($p['size'] ?? ($f['type'] === 'mark' ? 12 : 10));
        $style = 'left:' . $pt($f['x']) . ';top:' . $pt($f['y']) . ';width:' . $pt($f['w']) . ';height:' . $pt($f['h']) . ';font-size:' . $pt($size) . ';';
        $style .= !empty($p['bold']) ? 'font-weight:700;' : '';
        $style .= !empty($p['italic']) ? 'font-style:italic;' : '';
        $style .= !empty($p['underline']) ? 'text-decoration:underline;' : '';
        // the field's font, when it chose one PhaiERP can draw offline (Envelope::FONTS)
        $font = ['noto' => 'Noto Sans Thai', 'charm' => 'Charm', 'charmonman' => 'Charmonman', 'itim' => 'Itim', 'mali' => 'Mali', 'sriracha' => 'Sriracha'][$p['font'] ?? ''] ?? null;
        $style .= $font ? "font-family:'$font','IBM Plex Sans Thai',sans-serif;" : '';
        $style .= isset($p['color']) ? 'color:' . e($p['color']) . ';border-color:' . e($p['color']) . ';' : '';
        $align = $p['align'] ?? 'left';
        $style .= 'justify-content:' . ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$align] . ';text-align:' . $align . ';';
        $class = match ($f['type']) {
            'signature', 'initials', 'stamp' => 'sig',
            'label' => 'label',
            'mark' => 'mark',
            'checkbox' => 'box',
            default => '',
        };
        if ($f['type'] === 'mark') {
            $style .= 'border-width:' . $pt(max(1, $size / 10)) . ';' . (isset($p['color']) ? '' : 'color:#c92a2a;border-color:#c92a2a;') . 'justify-content:center;';
        }
        if ($f['type'] === 'checkbox') {
            $style .= 'border:.8pt solid #333;justify-content:center;';
        }
        ?>
        <div class="f <?= $class ?>" style="<?= $style ?>">
            <?php if (in_array($f['type'], ['signature', 'initials', 'stamp'], true)): ?>
                <?php if (!empty($f['image'])): ?><img src="<?= e($f['image']) ?>" alt=""><?php endif ?>
                <?php if (!empty($f['caption'])): ?><div class="cap"><?= e($f['caption']) ?><?php if (!empty($f['times'])): ?><br><?= e($f['times']) ?><?php endif ?></div><?php endif ?>
            <?php elseif ($f['type'] === 'checkbox'): ?>
                <?php if (!empty($f['checked'])): ?><svg viewBox="0 0 10 10" style="width:80%;height:80%"><path d="M1.5 5.2 4 7.6 8.6 2.4" fill="none" stroke="#1d2b7a" stroke-width="1.4"/></svg><?php endif ?>
            <?php else: ?>
                <span><?= e($f['text'] ?? '') ?></span>
            <?php endif ?>
        </div>
    <?php endforeach ?>
    </div>
    <?php if (!empty($s['foot'])): ?>
    <div class="ft" style="left:<?= $pt(min(28, $w * 0.04)) ?>;right:<?= $pt(min(28, $w * 0.04)) ?>;bottom:<?= $pt(max(1, ($fh < 22 ? 1 : 6))) ?>;height:<?= $pt($fh) ?>;font-size:<?= $pt(max(4, $fh * 0.3)) ?>">
        <span class="t"><?= e(t('esign.foot', ['nbr' => $nbr, 'page' => $s['foot']['page'], 'total' => $total])) ?> · <?= e($s['foot']['where']) ?> · <?= e(t('esign.foot_code', ['code' => $code])) ?></span>
        <img src="<?= e($qr) ?>" alt="" style="width:<?= $pt($fh - 1) ?>;height:<?= $pt($fh - 1) ?>">
    </div>
    <?php endif ?>
</div></div>
<?php endforeach ?>
</body></html>
