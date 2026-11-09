<?php

use Phai\Core\PdfKit\PdfException;
use Phai\Core\PdfKit\Reader;
use Phai\Core\PdfKit\Writer;
use Phai\Core\Qr;

/** A small PDF with a classic cross-reference table: $n pages of $w × $h, each saying "Page i". */
function pdfkitClassic(int $n = 2, float $w = 595, float $h = 842, string $extraTrailer = ''): string
{
    $objs = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => ''];
    $kids = [];
    $font = 3;
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    for ($i = 1; $i <= $n; $i++) {
        $content = "BT /F1 24 Tf 72 700 Td (Page $i) Tj ET";
        $c = count($objs) + 1;
        $objs[$c] = "<< /Length " . strlen($content) . " >>\nstream\n$content\nendstream";
        $p = count($objs) + 1;
        $objs[$p] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $w $h] /Resources << /Font << /F1 $font 0 R >> >> /Contents $c 0 R >>";
        $kids[] = "$p 0 R";
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
    $out = "%PDF-1.4\n";
    $off = [];
    foreach ($objs as $k => $body) {
        $off[$k] = strlen($out);
        $out .= "$k 0 obj\n$body\nendobj\n";
    }
    $x = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) {
        $out .= sprintf("%010d 00000 n \n", $o);
    }
    return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R $extraTrailer>>\nstartxref\n$x\n%%EOF\n";
}

/** The same kind of PDF as Word or Acrobat writes it: objects in a compressed object stream, a cross-reference stream with the PNG predictor. */
function pdfkitCompressed(int $n = 3): string
{
    $inner = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
    $kids = [];
    $streams = [];
    $next = 4;
    for ($i = 1; $i <= $n; $i++) {
        $c = $next++;
        $p = $next++;
        $streams[$c] = "BT /F1 20 Tf 72 700 Td (Compressed $i) Tj ET";
        $inner[$p] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Rotate " . ($i === 2 ? 90 : 0) . " /Resources << /Font << /F1 3 0 R >> >> /Contents $c 0 R >>";
        $kids[] = "$p 0 R";
    }
    $inner[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $n . ' >>';
    ksort($inner);
    $stm = $next++;
    $xrefNum = $next++;
    $head = '';
    $body = '';
    foreach ($inner as $num => $obj) {
        $head .= "$num " . strlen($body) . ' ';
        $body .= $obj . "\n";
    }
    $data = gzcompress($head . $body);
    $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($streams as $num => $s) {
        $z = gzcompress($s);
        $offsets[$num] = strlen($out);
        $out .= "$num 0 obj\n<< /Length " . strlen($z) . " /Filter /FlateDecode >>\nstream\n$z\nendstream\nendobj\n";
    }
    $offsets[$stm] = strlen($out);
    $out .= "$stm 0 obj\n<< /Type /ObjStm /N " . count($inner) . ' /First ' . strlen($head) . ' /Length ' . strlen($data) . " /Filter /FlateDecode >>\nstream\n$data\nendstream\nendobj\n";
    $offsets[$xrefNum] = strlen($out);
    // rows: type (1 byte), field 2 (2 bytes), field 3 (1 byte), PNG "up" predictor per row
    $rows = [];
    for ($i = 0; $i < $next; $i++) {
        if ($i === 0) {
            $rows[] = [0, 0, 255];
        } elseif (isset($inner[$i])) {
            $rows[] = [2, $stm, array_search($i, array_keys($inner), true)];
        } elseif (isset($offsets[$i])) {
            $rows[] = [1, $offsets[$i], 0];
        } else {
            $rows[] = [0, 0, 0];
        }
    }
    $raw = '';
    $prev = str_repeat("\0", 4);
    foreach ($rows as [$t, $a, $b]) {
        $row = chr($t) . pack('n', $a) . chr($b);
        $up = '';
        for ($k = 0; $k < 4; $k++) {
            $up .= chr((ord($row[$k]) - ord($prev[$k])) & 0xFF);
        }
        $raw .= "\x02" . $up;
        $prev = $row;
    }
    $z = gzcompress($raw);
    $out .= "$xrefNum 0 obj\n<< /Type /XRef /Size $next /W [1 2 1] /Root 1 0 R /DecodeParms << /Columns 4 /Predictor 12 >> /Filter /FlateDecode /Length " . strlen($z) . " >>\nstream\n$z\nendstream\nendobj\n";
    return $out . "startxref\n{$offsets[$xrefNum]}\n%%EOF\n";
}

test('PdfKit reads a classic PDF: pages, sizes, content', function () {
    $r = new Reader(pdfkitClassic(3, 300, 400));
    check(count($r->pages()) === 3);
    check(abs($r->pages()[0]['width'] - 300) < 0.01 && abs($r->pages()[0]['height'] - 400) < 0.01);
    check(str_contains($r->content($r->pages()[2]), '(Page 3)'));
});

test('PdfKit reads object streams and a cross-reference stream with the PNG predictor', function () {
    $r = new Reader(pdfkitCompressed(3));
    $pages = $r->pages();
    check(count($pages) === 3, 'pages: ' . count($pages));
    check($pages[1]['rotate'] === 90 && abs($pages[1]['width'] - 792) < 0.01, 'rotated page is shown landscape');
    check(str_contains($r->content($pages[2]), 'Compressed 3'));
});

test('PdfKit rebuilds a broken cross-reference', function () {
    $pdf = preg_replace('/startxref\s+\d+/', 'startxref 999999', pdfkitClassic(2));
    check(count((new Reader($pdf))->pages()) === 2);
});

test('PdfKit refuses an encrypted PDF', function () {
    try {
        new Reader(pdfkitClassic(1, 595, 842, '/Encrypt << /Filter /Standard >> '));
        check(false, 'should refuse');
    } catch (PdfException $e) {
        check($e->getMessage() === 'encrypted');
    }
});

test('PdfKit writes a new file from pages of others, with an overlay, and reads it back', function () {
    $a = new Reader(pdfkitClassic(2));
    $b = new Reader(pdfkitCompressed(3));
    $ov = new Reader(pdfkitClassic(1, 595, 842));
    $w = new Writer();
    foreach ([[$a, $a->pages()[0]], [$b, $b->pages()[1]], [$b, $b->pages()[2]]] as [$r, $p]) {
        $form = $w->form($ov, $ov->pages()[0]);
        $k = min(595 / $p['width'], 841 / $p['height']);
        $w->addPage($r, $p, ['pre' => Writer::shrink($p, 0.96), 'ops' => Writer::place($p, $ov->pages()[0], $k, 'Ov'), 'xobjects' => ['Ov' => $form]]);
    }
    $out = $w->output(['Title' => 'ทดสอบ'], 'x');
    $back = new Reader($out);
    check(count($back->pages()) === 3);
    check($back->pages()[1]['rotate'] === 90, 'rotation kept');
    check(str_contains($back->content($back->pages()[0]), '/Ov Do'), 'overlay drawn');
    check(str_contains($back->content($back->pages()[2]), 'Compressed 3'), 'original content kept');
    check(!str_contains($out, '/JavaScript'), 'nothing extra');
});

test('PdfKit fingerprints follow what a page shows, not how the file is written', function () {
    $r = new Reader(pdfkitClassic(2));
    $w = new Writer();
    foreach ($r->pages() as $p) {
        $w->addPage($r, $p);
    }
    $copy = new Reader($w->output());
    check($copy->fingerprint($copy->pages()[0]) === $r->fingerprint($r->pages()[0]), 'same page, other layout');
    check($r->fingerprint($r->pages()[0]) !== $r->fingerprint($r->pages()[1]), 'different pages differ');
    $changed = new Reader(str_replace('(Page 2)', '(Page X)', pdfkitClassic(2)));
    check($changed->fingerprint($changed->pages()[1]) !== $r->fingerprint($r->pages()[1]), 'a changed page differs');
    check($changed->fingerprint($changed->pages()[0]) === $r->fingerprint($r->pages()[0]), 'the other page does not');
});

test('PdfKit drops JavaScript and draws filled form fields into the page', function () {
    $pdf = pdfkitClassic(1);
    // a form field with an appearance, and an open action with JavaScript, added as an update
    $ap = 'q 0 0 1 rg 0 0 100 20 re f Q';
    $add = "20 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 100 20] /Length " . strlen($ap) . " >>\nstream\n$ap\nendstream\nendobj\n"
        . "21 0 obj\n<< /Type /Annot /Subtype /Widget /Rect [50 50 150 70] /AP << /N 20 0 R >> >>\nendobj\n"
        . "22 0 obj\n<< /S /JavaScript /JS (app.alert(1)) >>\nendobj\n";
    $pdf = str_replace('/Contents 4 0 R >>', '/Contents 4 0 R /Annots [21 0 R] >>', $pdf);
    $pdf = str_replace('<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Catalog /Pages 2 0 R /OpenAction 22 0 R >>', $pdf);
    $pdf = preg_replace('/xref.*$/s', '', $pdf) . $add;
    $r = new Reader($pdf . "trailer\n<< /Root 1 0 R >>\n%%EOF\n"); // no valid xref: rebuilt by scanning
    $w = new Writer();
    $w->addPage($r, $r->pages()[0], ['flatten' => true]);
    $out = $w->output();
    check(!str_contains($out, 'JavaScript') && !str_contains($out, '/Annots'), 'no script, no annotations');
    check(str_contains((new Reader($out))->content((new Reader($out))->pages()[0]), '/PhaiAn1 Do'), 'field drawn into the page');
});

test('QR codes: a version that fits, quiet zone, SVG', function () {
    $m = Qr::make('http://phai-server/envelopes/verify?n=ENV2026110001');
    check(count($m) === 33, 'version 4 is 33 modules: ' . count($m));
    check($m[0][0] && $m[6][6] && !$m[7][7], 'finder pattern in the corner');
    check(str_starts_with(Qr::svg('x'), '<svg'));
});
