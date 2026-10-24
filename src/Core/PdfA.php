<?php

declare(strict_types=1);

namespace Phai\Core;

/**
 * Turns a PDF made by Edge/Chrome (Pdf::fromHtml) into PDF/A-3B, as e-Tax Invoice by Email asks for —
 * no library: the browser already embeds every font with a ToUnicode map, so what is missing is added as an
 * incremental update at the end of the file:
 *
 *   - XMP metadata (pdfaid:part 3, conformance B) matching a new document information dictionary
 *   - an sRGB OutputIntent with its ICC profile (built here from the sRGB constants, see srgb())
 *   - the file /ID in the trailer
 *
 *     $pdfa = PdfA::convert(Pdf::fromHtml($html), 'IV2026100001');
 *
 * Only classic cross-reference tables are handled (what Skia writes); anything else throws.
 */
final class PdfA
{
    public static function convert(string $pdf, string $title, ?\DateTimeInterface $at = null): string
    {
        if (!preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $m)) {
            throw new \RuntimeException('PDF/A: no startxref');
        }
        $prev = (int) $m[1];
        if (substr($pdf, $prev, 4) !== 'xref' || !preg_match('/trailer\s*<<(.*?)>>\s*startxref\s+\d+\s+%%EOF\s*$/s', $pdf, $t)) {
            throw new \RuntimeException('PDF/A: only a classic cross-reference table is supported');
        }
        $size = (int) self::key($t[1], 'Size');
        $root = (int) self::key($t[1], 'Root');
        if ($size < 1 || $root < 1 || !preg_match('/(?<![0-9])' . $root . ' 0 obj\s*<<(.*)>>\s*endobj/sU', $pdf, $c)) {
            throw new \RuntimeException('PDF/A: catalog not found');
        }
        $catalog = self::dict($pdf, $root);
        // a catalog already carrying these would need merging; the browser never writes them
        $catalog = preg_replace('#/(Metadata|OutputIntents)\s+(\d+\s+\d+\s+R|\[[^\]]*\])#', '', $catalog);

        $at ??= new \DateTimeImmutable();
        $pdfDate = 'D:' . $at->format('YmdHis') . str_replace(':', "'", $at->format('P')) . "'";
        $xmpDate = $at->format('Y-m-d\TH:i:sP');
        $producer = 'PhaiERP';
        $xmp = self::xmp($title, $producer, $xmpDate);
        $icc = self::srgb();

        $n = $size;
        $objects = [
            $root   => "<<$catalog /Metadata $n 0 R /OutputIntents [" . ($n + 2) . ' 0 R]>>',
            $n      => '<</Type /Metadata /Subtype /XML /Length ' . strlen($xmp) . ">>\nstream\n$xmp\nendstream",
            $n + 1  => '<</N 3 /Length ' . strlen($icc) . ">>\nstream\n$icc\nendstream",
            $n + 2  => '<</Type /OutputIntent /S /GTS_PDFA1 /OutputConditionIdentifier (sRGB IEC61966-2.1) /Info (sRGB IEC61966-2.1) /DestOutputProfile '
                . ($n + 1) . ' 0 R>>',
            $n + 3  => '<</Title ' . self::text($title) . ' /Producer ' . self::text($producer) . " /CreationDate ($pdfDate) /ModDate ($pdfDate)>>",
        ];

        $out = $pdf;
        if (!str_ends_with($out, "\n")) {
            $out .= "\n";
        }
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= "$num 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n";
        ksort($offsets);
        // one subsection per run of consecutive object numbers
        $runs = [];
        foreach ($offsets as $num => $off) {
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last]['start'] + count($runs[$last]['offs']) === $num) {
                $runs[$last]['offs'][] = $off;
            } else {
                $runs[] = ['start' => $num, 'offs' => [$off]];
            }
        }
        foreach ($runs as $run) {
            $out .= $run['start'] . ' ' . count($run['offs']) . "\n";
            foreach ($run['offs'] as $off) {
                $out .= sprintf("%010d 00000 n \n", $off);
            }
        }
        $id = md5($pdf . $title . $xmpDate);
        $out .= 'trailer' . "\n" . '<</Size ' . ($n + 4) . " /Root $root 0 R /Info " . ($n + 3) . " 0 R /Prev $prev /ID [<$id> <$id>]>>\n"
            . "startxref\n$xref\n%%EOF\n";
        return $out;
    }

 /** The XMP packet: PDF/A-3B identification plus the same title, producer and dates as the info dictionary. */
    private static function xmp(string $title, string $producer, string $date): string
    {
        $x = fn (string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xpacket begin="' . "\u{FEFF}" . '" id="W5M0MpCehiHzreSzNTczkc9d"?>' . "\n"
            . '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' . "\n"
            . '<rdf:Description rdf:about="" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/" xmlns:dc="http://purl.org/dc/elements/1.1/"'
            . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/" xmlns:pdf="http://ns.adobe.com/pdf/1.3/">' . "\n"
            . '<pdfaid:part>3</pdfaid:part><pdfaid:conformance>B</pdfaid:conformance>' . "\n"
            . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . $x($title) . '</rdf:li></rdf:Alt></dc:title>' . "\n"
            . '<pdf:Producer>' . $x($producer) . '</pdf:Producer>' . "\n"
            . "<xmp:CreateDate>$date</xmp:CreateDate><xmp:ModifyDate>$date</xmp:ModifyDate><xmp:MetadataDate>$date</xmp:MetadataDate>\n"
            . '</rdf:Description></rdf:RDF></x:xmpmeta>' . "\n"
            . str_repeat(str_repeat(' ', 99) . "\n", 20) . '<?xpacket end="w"?>';
    }

    /**
     * An ICC v2 display profile for sRGB IEC 61966-2.1: D50-adapted primaries, D65 media white,
     * and the sRGB transfer curve sampled at 1024 points.
     */
    public static function srgb(): string
    {
        $s15 = fn (float $v) => pack('N', (int) round($v * 65536) & 0xFFFFFFFF);
        $xyz = fn (float $x, float $y, float $z) => 'XYZ ' . "\0\0\0\0" . $s15($x) . $s15($y) . $s15($z);
        $desc = function (string $s) {
            $ascii = $s . "\0";
            return 'desc' . "\0\0\0\0" . pack('N', strlen($ascii)) . $ascii . pack('NN', 0, 0) . pack('nC', 0, 0) . str_repeat("\0", 67);
        };
        $curve = 'curv' . "\0\0\0\0" . pack('N', 1024);
        for ($i = 0; $i < 1024; $i++) {
            $v = $i / 1023;
            $lin = $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
            $curve .= pack('n', (int) round($lin * 65535));
        }
        $tags = [
            'desc' => $desc('sRGB IEC61966-2.1'),
            'cprt' => 'text' . "\0\0\0\0" . "No copyright, use freely\0",
            'wtpt' => $xyz(0.9505, 1.0, 1.0891),
            'rXYZ' => $xyz(0.4360, 0.2225, 0.0139),
            'gXYZ' => $xyz(0.3851, 0.7169, 0.0971),
            'bXYZ' => $xyz(0.1431, 0.0606, 0.7141),
            'rTRC' => $curve,
        ];
        $table = '';
        $data = '';
        $offset = 128 + 4 + 12 * (count($tags) + 2);
        foreach ($tags as $sig => $body) {
            $table .= $sig . pack('NN', $offset + strlen($data), strlen($body));
            if ($sig === 'rTRC') {
                // green and blue share the red curve
                $table .= 'gTRC' . pack('NN', $offset + strlen($data), strlen($body));
                $table .= 'bTRC' . pack('NN', $offset + strlen($data), strlen($body));
            }
            $data .= $body . str_repeat("\0", (4 - strlen($body) % 4) % 4);
        }
        $body = pack('N', count($tags) + 2) . $table . $data;
        $size = 128 + strlen($body);
        $header = pack('N', $size) . "\0\0\0\0" . pack('N', 0x02100000) . 'mntr' . 'RGB ' . 'XYZ '
            . pack('nnnnnn', 2026, 1, 1, 0, 0, 0) . 'acsp' . "\0\0\0\0" . pack('N', 0) . "\0\0\0\0\0\0\0\0" . pack('NN', 0, 0) . pack('N', 0)
            . $s15(0.9642) . $s15(1.0) . $s15(0.8249) . "\0\0\0\0" . str_repeat("\0", 16) . str_repeat("\0", 28);
        return $header . $body;
    }

    /** A value from the trailer dictionary: /Size 154 -> "154", /Root 129 0 R -> "129". */
    private static function key(string $dict, string $key): ?string
    {
        return preg_match('#/' . $key . '\s+(\d+)#', $dict, $m) ? $m[1] : null;
    }

    /** The inside of an object's top-level dictionary (between the outer << >>), nested dictionaries included. */
    private static function dict(string $pdf, int $num): string
    {
        preg_match('/(?<![0-9])' . $num . ' 0 obj\s*<</', $pdf, $m, PREG_OFFSET_CAPTURE);
        $start = $m[0][1] + strlen($m[0][0]);
        $depth = 1;
        for ($i = $start, $len = strlen($pdf); $i < $len - 1; $i++) {
            $two = $pdf[$i] . $pdf[$i + 1];
            if ($two === '<<') {
                $depth++;
                $i++;
            } elseif ($two === '>>') {
                if (--$depth === 0) {
                    return substr($pdf, $start, $i - $start);
                }
                $i++;
            }
        }
        throw new \RuntimeException('PDF/A: catalog not closed');
    }

    /** A PDF text string: plain ASCII as (…), anything else as UTF-16BE with a byte order mark. */
    private static function text(string $s): string
    {
        if (preg_match('/^[\x20-\x7e]*$/', $s)) {
            return '(' . addcslashes($s, '()\\') . ')';
        }
        return '<FEFF' . strtoupper(bin2hex(mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'))) . '>';
    }
}
