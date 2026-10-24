<?php

declare(strict_types=1);

use Phai\Core\PdfA;

/** A one-page PDF with a classic cross-reference table, like the browser writes. */
function pdfaSample(): string
{
    $objs = [
        1 => '<</Title (x) /Producer (Skia/PDF)>>',
        2 => '<</Type /Catalog /Pages 3 0 R /MarkInfo <</Marked true>> /Lang (th)>>',
        3 => '<</Type /Pages /Kids [4 0 R] /Count 1>>',
        4 => '<</Type /Page /Parent 3 0 R /MediaBox [0 0 595 842]>>',
    ];
    $pdf = "%PDF-1.4\n%\xD3\xEB\xE9\xE1\n";
    $off = [];
    foreach ($objs as $n => $o) {
        $off[$n] = strlen($pdf);
        $pdf .= "$n 0 obj\n$o\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 5\n0000000000 65535 f \n";
    foreach ($off as $o) {
        $pdf .= sprintf("%010d 00000 n \n", $o);
    }
    return $pdf . "trailer\n<</Size 5\n/Root 2 0 R\n/Info 1 0 R>>\nstartxref\n$xref\n%%EOF";
}

test('PDF/A-3: catalog keeps its entries and gains metadata and an sRGB output intent', function () {
    $pdf = PdfA::convert(pdfaSample(), 'ใบกำกับภาษี IV1', new DateTimeImmutable('2026-10-05T20:10:00+07:00'));
    check(str_starts_with($pdf, pdfaSample()), 'incremental: the original bytes stay');
    check((bool) preg_match('#2 0 obj\n<<.*/Pages 3 0 R.*/MarkInfo <</Marked true>>.*/Metadata 5 0 R /OutputIntents \[7 0 R\]>>#s', substr($pdf, strlen(pdfaSample()))));
    check(str_contains($pdf, '<pdfaid:part>3</pdfaid:part><pdfaid:conformance>B</pdfaid:conformance>'));
    check(str_contains($pdf, '<xmp:CreateDate>2026-10-05T20:10:00+07:00</xmp:CreateDate>') && str_contains($pdf, "/CreationDate (D:20261005201000+07'00')"));
    check(str_contains($pdf, '/Title <FEFF0E43'), 'Thai title as UTF-16BE');
    check((bool) preg_match('#/Size 9 /Root 2 0 R /Info 8 0 R /Prev \d+ /ID \[<([0-9a-f]{32})> <\1>\]#', $pdf));
});

test('PDF/A-3: every new cross-reference offset points at its object', function () {
    $pdf = PdfA::convert(pdfaSample(), 'IV1');
    preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $m);
    $xref = substr($pdf, (int) $m[1]);
    preg_match_all('/^(\d+) (\d+)\n((?:\d{10} 00000 n \n)+)/m', $xref, $runs, PREG_SET_ORDER);
    $seen = 0;
    foreach ($runs as $run) {
        preg_match_all('/(\d{10}) 00000 n/', $run[3], $offs);
        foreach ($offs[1] as $i => $off) {
            $num = (int) $run[1] + $i;
            check(substr($pdf, (int) $off, strlen("$num 0 obj")) === "$num 0 obj", "object $num");
            $seen++;
        }
    }
    check($seen === 5, 'catalog + 4 new objects');
});

test('PDF/A-3: the sRGB ICC profile is well formed', function () {
    $icc = PdfA::srgb();
    check(unpack('N', $icc)[1] === strlen($icc), 'size field');
    check(substr($icc, 12, 12) === 'mntrRGB XYZ ' && substr($icc, 36, 4) === 'acsp');
    check(unpack('N', substr($icc, 128, 4))[1] === 9, 'nine tags');
});

test('PDF/A-3: refuses a cross-reference stream it cannot extend', function () {
    try {
        PdfA::convert("%PDF-1.5\n1 0 obj\n<<>>\nendobj\nstartxref\n9\n%%EOF", 'x');
        check(false, 'should throw');
    } catch (RuntimeException $e) {
        check(str_starts_with($e->getMessage(), 'PDF/A'));
    }
});
