<?php

declare(strict_types=1);

namespace Phai\Core\PdfKit;

/**
 * Reads a PDF file well enough to copy its pages into a new file (Writer): the cross-reference table or stream (with
 * earlier updates through /Prev), object streams, the page tree with what pages inherit, and the Flate / ASCIIHex /
 * ASCII85 filters. A damaged cross-reference is rebuilt by scanning for "n g obj". Encrypted files are refused,
 * because their content cannot be checked or stamped.
 *
 *     $r = new Reader($bytes);
 *     foreach ($r->pages() as $p) { $p['box']; $p['rotate']; }
 *
 * No library: PhaiERP has no Composer, and Phai Server must make signed documents with nothing but PHP.
 */
final class Reader
{
    public readonly string $bytes;
    public Dict $trailer;
    /** object number => [1, offset] or [2, object stream number, index] */
    private array $xref = [];
    private array $cache = [];
    private array $objstm = [];
    private ?array $pageList = null;

    public function __construct(string $bytes)
    {
        $start = strpos($bytes, '%PDF-');
        if ($start === false || $start > 1024) {
            throw new PdfException('not a PDF');
        }
        $this->bytes = $start > 0 ? substr($bytes, $start) : $bytes;
        $this->trailer = new Dict();
        try {
            $this->loadXref();
            if (!$this->resolve($this->trailer->get('Root')) instanceof Dict) {
                throw new PdfException('no catalog');
            }
        } catch (PdfException $e) {
            if ($e->getMessage() === 'encrypted') {
                throw $e;
            }
            $this->rebuild();
        }
        if ($this->trailer->get('Encrypt') !== null) {
            throw new PdfException('encrypted');
        }
    }

    // ------------------------------------------------------------------ pages

    /**
     * Every page in order: ['dict' => Dict, 'resources' => Dict, 'box' => [x0, y0, x1, y1] (crop box, else media box),
     * 'media' => [...], 'rotate' => 0|90|180|270, 'width' / 'height' => size as shown, after the rotation].
     */
    public function pages(): array
    {
        if ($this->pageList !== null) {
            return $this->pageList;
        }
        $root = $this->resolve($this->trailer->get('Root'));
        $top = $root instanceof Dict ? $this->resolve($root->get('Pages')) : null;
        if (!$top instanceof Dict) {
            throw new PdfException('no page tree');
        }
        $out = [];
        $seen = [];
        $walk = function (Dict $node, array $inherit, int $depth) use (&$walk, &$out, &$seen): void {
            if ($depth > 64 || count($out) > 20000) {
                throw new PdfException('page tree too deep');
            }
            foreach (['Resources', 'MediaBox', 'CropBox', 'Rotate'] as $k) {
                if ($node->get($k) !== null) {
                    $inherit[$k] = $node->get($k);
                }
            }
            $kids = $this->resolve($node->get('Kids'));
            if ($node->name('Type') === 'Pages' || (is_array($kids) && $node->name('Type') !== 'Page')) {
                foreach (is_array($kids) ? $kids : [] as $kid) {
                    if ($kid instanceof Ref) {
                        if (isset($seen[$kid->num])) {
                            continue;
                        }
                        $seen[$kid->num] = true;
                    }
                    $k = $this->resolve($kid);
                    if ($k instanceof Dict) {
                        $walk($k, $inherit, $depth + 1);
                    }
                }
                return;
            }
            $media = $this->box($inherit['MediaBox'] ?? null) ?? [0, 0, 595.28, 841.89];
            $crop = $this->box($inherit['CropBox'] ?? null);
            $box = $crop ? [max($media[0], $crop[0]), max($media[1], $crop[1]), min($media[2], $crop[2]), min($media[3], $crop[3])] : $media;
            if ($box[2] - $box[0] < 1 || $box[3] - $box[1] < 1) {
                $box = $media;
            }
            $rotate = (int) $this->resolve($inherit['Rotate'] ?? 0);
            $rotate = (($rotate % 360) + 360) % 360;
            $rotate = in_array($rotate, [0, 90, 180, 270], true) ? $rotate : 0;
            $w = $box[2] - $box[0];
            $h = $box[3] - $box[1];
            $res = $this->resolve($inherit['Resources'] ?? null);
            $out[] = [
                'dict' => $node, 'resources' => $res instanceof Dict ? $res : new Dict(), 'box' => $box, 'media' => $media,
                'rotate' => $rotate, 'width' => $rotate % 180 ? $h : $w, 'height' => $rotate % 180 ? $w : $h,
            ];
        };
        $walk($top, [], 0);
        if (!$out) {
            throw new PdfException('no pages');
        }
        return $this->pageList = $out;
    }

    /** The decoded content of a page: its content streams one after another. */
    public function content(array $page): string
    {
        $c = $this->resolve($page['dict']->get('Contents'));
        $parts = [];
        foreach (is_array($c) ? $c : [$c] as $s) {
            $s = $this->resolve($s);
            if ($s instanceof Stream) {
                $parts[] = $this->decode($s) ?? throw new PdfException('content filter not supported');
            }
        }
        return implode("\n", $parts);
    }

    /**
     * A fingerprint of what a page shows, independent of how the file is laid out: its size, rotation, decoded content
     * and every resource it uses, with dictionaries in key order and streams by their decoded bytes. Saving the file
     * again in another program keeps it; changing what is on the page does not.
     */
    public function fingerprint(array $page): string
    {
        $seen = [];
        $res = $this->canonical($page['resources'], $seen, 0);
        $c = $this->resolve($page['dict']->get('Contents'));
        $content = '';
        foreach (is_array($c) ? $c : [$c] as $s) {
            $s = $this->resolve($s);
            if ($s instanceof Stream) {
                $content .= ($this->decode($s) ?? $s->data) . "\n";
            }
        }
        $box = array_map(fn ($v) => round($v, 1), $page['box']);
        return hash('sha256', json_encode([$box, $page['rotate'], hash('sha256', $content), $res]));
    }

    private function canonical(mixed $v, array &$seen, int $depth): mixed
    {
        if ($depth > 40) {
            return 'deep';
        }
        if ($v instanceof Ref) {
            if (isset($seen[$v->num])) {
                return $seen[$v->num];
            }
            $seen[$v->num] = 'ref';
            $obj = $this->object($v->num);
            if ($obj instanceof Dict && in_array($obj->name('Type'), ['Page', 'Pages'], true)) {
                return $seen[$v->num] = 'page';
            }
            return $seen[$v->num] = $this->canonical($obj, $seen, $depth + 1);
        }
        if ($v instanceof Stream) {
            $d = $v->dict->v;
            unset($d['Length'], $d['Filter'], $d['DecodeParms']);
            $data = in_array($v->dict->name('Subtype'), ['Image'], true) ? $v->data : ($this->decode($v) ?? $v->data);
            return ['s' => $this->canonical(new Dict($d), $seen, $depth + 1), 'h' => hash('sha256', $data)];
        }
        if ($v instanceof Dict) {
            $out = [];
            $keys = array_keys($v->v);
            sort($keys, SORT_STRING);
            foreach ($keys as $k) {
                $out[$k] = $this->canonical($v->v[$k], $seen, $depth + 1);
            }
            return ['d' => $out];
        }
        if (is_array($v)) {
            return array_map(fn ($x) => $this->canonical($x, $seen, $depth + 1), $v);
        }
        if ($v instanceof Name) {
            return '/' . $v->v;
        }
        if ($v instanceof Str) {
            return 's:' . bin2hex($v->v);
        }
        if (is_float($v)) {
            return round($v, 4);
        }
        return $v;
    }

    // ------------------------------------------------------------------ objects

    /** Follow references until a direct value. */
    public function resolve(mixed $v): mixed
    {
        $n = 0;
        while ($v instanceof Ref && $n++ < 32) {
            $v = $this->object($v->num);
        }
        return $v instanceof Ref ? null : $v;
    }

    public function object(int $num): mixed
    {
        if (array_key_exists($num, $this->cache)) {
            return $this->cache[$num];
        }
        $this->cache[$num] = null; // a reference loop reads as null
        $e = $this->xref[$num] ?? null;
        $v = null;
        if ($e !== null && $e[0] === 1) {
            $v = $this->objectAt($e[1], $num);
        } elseif ($e !== null && $e[0] === 2) {
            $v = $this->fromObjectStream($e[1], $e[2], $num);
        }
        return $this->cache[$num] = $v;
    }

    /** The bytes of a stream with its filters undone; null when a filter is one this reader does not know (images). */
    public function decode(Stream $s): ?string
    {
        $filters = $this->resolve($s->dict->get('Filter'));
        $parms = $this->resolve($s->dict->get('DecodeParms'));
        $filters = $filters === null ? [] : (is_array($filters) ? $filters : [$filters]);
        $parms = is_array($parms) ? $parms : [$parms];
        $data = $s->data;
        foreach ($filters as $i => $f) {
            $f = $this->resolve($f);
            $name = $f instanceof Name ? $f->v : '';
            $p = $this->resolve($parms[$i] ?? null);
            if ($name === 'FlateDecode' || $name === 'Fl') {
                $data = self::inflate($data) ?? throw new PdfException('bad flate data');
                if ($p instanceof Dict && (int) $this->resolve($p->get('Predictor') ?? 1) >= 10) {
                    $data = self::png($data, $p, $this);
                }
            } elseif ($name === 'ASCIIHexDecode' || $name === 'AHx') {
                $hex = preg_replace('/[^0-9A-Fa-f]/', '', explode('>', $data)[0]);
                $data = (string) hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex);
            } elseif ($name === 'ASCII85Decode' || $name === 'A85') {
                $data = self::ascii85($data);
            } else {
                return null;
            }
        }
        return $data;
    }

    private static function inflate(string $data): ?string
    {
        $out = @gzuncompress($data);
        if ($out !== false) {
            return $out;
        }
        $out = @gzinflate(substr($data, 2));
        if ($out !== false) {
            return $out;
        }
        // truncated or with junk at the end: take what inflates
        $ctx = @inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($ctx === false) {
            return null;
        }
        $out = @inflate_add($ctx, substr($data, 2), ZLIB_SYNC_FLUSH);
        return $out === false || $out === '' ? null : $out;
    }

    private static function png(string $data, Dict $p, self $r): string
    {
        $colors = (int) ($r->resolve($p->get('Colors')) ?? 1);
        $bpc = (int) ($r->resolve($p->get('BitsPerComponent')) ?? 8);
        $columns = (int) ($r->resolve($p->get('Columns')) ?? 1);
        $bpp = max(1, intdiv($colors * $bpc + 7, 8));
        $row = intdiv($colors * $bpc * $columns + 7, 8);
        $out = '';
        $prev = str_repeat("\0", $row);
        $len = strlen($data);
        for ($i = 0; $i + $row < $len + 1 && $i < $len; $i += $row + 1) {
            $type = ord($data[$i]);
            $line = substr($data, $i + 1, $row);
            $line = str_pad($line, $row, "\0");
            $cur = '';
            for ($x = 0; $x < $row; $x++) {
                $a = $x >= $bpp ? ord($cur[$x - $bpp]) : 0;
                $b = ord($prev[$x]);
                $c = $x >= $bpp ? ord($prev[$x - $bpp]) : 0;
                $v = ord($line[$x]);
                $v = match ($type) {
                    1 => $v + $a,
                    2 => $v + $b,
                    3 => $v + intdiv($a + $b, 2),
                    4 => $v + self::paeth($a, $b, $c),
                    default => $v,
                };
                $cur .= chr($v & 0xFF);
            }
            $out .= $cur;
            $prev = $cur;
        }
        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);
        return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
    }

    private static function ascii85(string $data): string
    {
        $data = preg_replace('/\s+/', '', $data);
        if (str_starts_with($data, '<~')) {
            $data = substr($data, 2);
        }
        $data = explode('~>', $data)[0];
        $out = '';
        $group = [];
        $n = strlen($data);
        for ($i = 0; $i < $n; $i++) {
            $c = $data[$i];
            if ($c === 'z' && !$group) {
                $out .= "\0\0\0\0";
                continue;
            }
            $group[] = ord($c) - 33;
            if (count($group) === 5) {
                $v = 0;
                foreach ($group as $g) {
                    $v = $v * 85 + $g;
                }
                $out .= pack('N', $v & 0xFFFFFFFF);
                $group = [];
            }
        }
        if ($group) {
            $k = count($group);
            $group = array_pad($group, 5, 84);
            $v = 0;
            foreach ($group as $g) {
                $v = $v * 85 + $g;
            }
            $out .= substr(pack('N', $v & 0xFFFFFFFF), 0, $k - 1);
        }
        return $out;
    }

    // ------------------------------------------------------------------ cross-reference

    private function loadXref(): void
    {
        $at = strrpos($this->bytes, 'startxref');
        if ($at === false || !preg_match('/startxref\s+(\d+)/', $this->bytes, $m, 0, $at)) {
            throw new PdfException('no startxref');
        }
        $offset = (int) $m[1];
        $seen = [];
        $first = true;
        while ($offset > 0 && !isset($seen[$offset]) && count($seen) < 100) {
            $seen[$offset] = true;
            $p = $offset;
            $this->skip($p);
            if (substr($this->bytes, $p, 4) === 'xref') {
                $trailer = $this->xrefTable($p + 4);
            } else {
                $trailer = $this->xrefStream($p);
            }
            foreach ($trailer->v as $k => $v) {
                if (!array_key_exists($k, $this->trailer->v)) {
                    $this->trailer->v[$k] = $v;
                }
            }
            if ($first && $trailer->get('Encrypt') !== null) {
                throw new PdfException('encrypted');
            }
            $first = false;
            $stm = $trailer->get('XRefStm');
            if (is_int($stm)) {
                $this->xrefStream($stm);
            }
            $prev = $trailer->get('Prev');
            $offset = is_int($prev) ? $prev : 0;
        }
        unset($this->trailer->v['Prev'], $this->trailer->v['XRefStm']);
        if (!$this->xref) {
            throw new PdfException('empty cross-reference');
        }
    }

    private function xrefTable(int $p): Dict
    {
        while (true) {
            $this->skip($p);
            if (substr($this->bytes, $p, 7) === 'trailer') {
                $p += 7;
                $t = $this->value($p);
                return $t instanceof Dict ? $t : throw new PdfException('bad trailer');
            }
            if (!preg_match('/\G(\d+)\s+(\d+)/', $this->bytes, $m, 0, $p)) {
                throw new PdfException('bad xref section');
            }
            $p += strlen($m[0]);
            $start = (int) $m[1];
            $count = (int) $m[2];
            for ($i = 0; $i < $count; $i++) {
                $this->skip($p);
                if (!preg_match('/\G(\d{1,10})\s+(\d{1,5})\s+([nf])/', $this->bytes, $e, 0, $p)) {
                    throw new PdfException('bad xref entry');
                }
                $p += strlen($e[0]);
                $num = $start + $i;
                if ($e[3] === 'n' && !isset($this->xref[$num]) && (int) $e[1] > 0) {
                    $this->xref[$num] = [1, (int) $e[1]];
                } elseif (!isset($this->xref[$num])) {
                    $this->xref[$num] = [0];
                }
            }
        }
    }

    private function xrefStream(int $p): Dict
    {
        $s = $this->objectAt($p, null);
        if (!$s instanceof Stream || $s->dict->name('Type') !== 'XRef') {
            throw new PdfException('bad xref stream');
        }
        $data = $this->decode($s) ?? throw new PdfException('bad xref stream data');
        $w = array_map(fn ($x) => (int) $this->resolve($x), (array) $this->resolve($s->dict->get('W')));
        if (count($w) < 3) {
            throw new PdfException('bad xref widths');
        }
        $size = (int) $this->resolve($s->dict->get('Size'));
        $index = $this->resolve($s->dict->get('Index')) ?? [0, $size];
        $rowLen = array_sum($w);
        $pos = 0;
        $field = function (int $width) use ($data, &$pos): int {
            $v = 0;
            for ($i = 0; $i < $width; $i++) {
                $v = ($v << 8) | ord($data[$pos++] ?? "\0");
            }
            return $v;
        };
        for ($i = 0; $i + 1 < count($index); $i += 2) {
            $start = (int) $this->resolve($index[$i]);
            $count = (int) $this->resolve($index[$i + 1]);
            for ($j = 0; $j < $count && $pos + $rowLen <= strlen($data); $j++) {
                $type = $w[0] ? $field($w[0]) : 1;
                $a = $field($w[1]);
                $b = $field($w[2]);
                $num = $start + $j;
                if (isset($this->xref[$num])) {
                    continue;
                }
                $this->xref[$num] = match ($type) {
                    1 => [1, $a],
                    2 => [2, $a, $b],
                    default => [0],
                };
            }
        }
        return $s->dict;
    }

    /** A broken cross-reference: find every "n g obj" in the file (later ones win, as in an update) and the catalog. */
    private function rebuild(): void
    {
        $this->xref = [];
        $this->cache = [];
        $this->objstm = [];
        preg_match_all('/(?<![0-9])(\d{1,10})\s+(\d{1,5})\s+obj\b/', $this->bytes, $m, PREG_OFFSET_CAPTURE);
        foreach ($m[1] as $i => $hit) {
            $this->xref[(int) $hit[0]] = [1, (int) $m[0][$i][1]];
        }
        $root = null;
        foreach (array_keys($this->xref) as $num) {
            $o = $this->object($num);
            if ($o instanceof Stream && $o->dict->name('Type') === 'ObjStm') {
                $n = (int) $this->resolve($o->dict->get('N'));
                $head = $this->decode($o);
                if ($head !== null && preg_match_all('/(\d+)\s+(\d+)/', substr($head, 0, (int) $this->resolve($o->dict->get('First'))), $pairs)) {
                    foreach ($pairs[1] as $k => $inner) {
                        if ($k < $n && !isset($this->xref[(int) $inner])) {
                            $this->xref[(int) $inner] = [2, $num, $k];
                        }
                    }
                }
            }
        }
        $this->cache = [];
        foreach (array_keys($this->xref) as $num) {
            $o = $this->object($num);
            if ($o instanceof Dict && $o->name('Type') === 'Catalog') {
                $root = new Ref($num);
            }
            if ($o instanceof Dict && $o->get('Encrypt') !== null) {
                throw new PdfException('encrypted');
            }
        }
        if (preg_match_all('/trailer\s*(<<)/', $this->bytes, $t, PREG_OFFSET_CAPTURE)) {
            $p = end($t[1])[1];
            $d = $this->value($p);
            if ($d instanceof Dict) {
                $this->trailer = $d;
            }
        }
        if ($this->trailer->get('Encrypt') !== null) {
            throw new PdfException('encrypted');
        }
        if (!$this->resolve($this->trailer->get('Root')) instanceof Dict) {
            $root ?? throw new PdfException('no catalog');
            $this->trailer->v['Root'] = $root;
        }
    }

    // ------------------------------------------------------------------ parsing

    /** The object at a byte offset ("n g obj ... endobj"), a stream when one follows its dictionary. */
    private function objectAt(int $p, ?int $num): mixed
    {
        $saved = $this->buffer;
        $this->buffer = null;
        try {
            return $this->objectIn($p, $num);
        } finally {
            $this->buffer = $saved;
        }
    }

    private function objectIn(int $p, ?int $num): mixed
    {
        $this->skip($p);
        if (!preg_match('/\G(\d+)\s+(\d+)\s+obj/', $this->bytes, $m, 0, $p)) {
            return null;
        }
        if ($num !== null && (int) $m[1] !== $num) {
            return null;
        }
        $p += strlen($m[0]);
        $v = $this->value($p);
        $this->skip($p);
        if ($v instanceof Dict && substr($this->bytes, $p, 6) === 'stream') {
            $p += 6;
            if (($this->bytes[$p] ?? '') === "\r") {
                $p++;
            }
            if (($this->bytes[$p] ?? '') === "\n") {
                $p++;
            }
            $len = $v->get('Length');
            $len = $len instanceof Ref ? $this->object($len->num) : $len;
            $end = is_int($len) && $len >= 0 ? $p + $len : -1;
            if ($end < 0 || $end > strlen($this->bytes) || !preg_match('/\G\s*endstream/', $this->bytes, $x, 0, $end)) {
                $found = strpos($this->bytes, 'endstream', $p);
                $end = $found === false ? strlen($this->bytes) : $found;
                // the line end before endstream belongs to the keyword, not the data
                if ($end > $p && $this->bytes[$end - 1] === "\n") {
                    $end--;
                }
                if ($end > $p && $this->bytes[$end - 1] === "\r") {
                    $end--;
                }
            }
            return new Stream($v, substr($this->bytes, $p, $end - $p));
        }
        return $v;
    }

    private function fromObjectStream(int $stm, int $index, int $num): mixed
    {
        if (!isset($this->objstm[$stm])) {
            $s = $this->object($stm);
            $this->objstm[$stm] = false;
            if ($s instanceof Stream && ($data = $this->decode($s)) !== null) {
                $first = (int) $this->resolve($s->dict->get('First'));
                $n = (int) $this->resolve($s->dict->get('N'));
                preg_match_all('/(\d+)\s+(\d+)/', substr($data, 0, $first), $pairs);
                $offsets = [];
                foreach ($pairs[1] as $k => $inner) {
                    if ($k < $n) {
                        $offsets[$k] = [(int) $inner, $first + (int) $pairs[2][$k]];
                    }
                }
                $this->objstm[$stm] = [$data, $offsets];
            }
        }
        $entry = $this->objstm[$stm];
        if ($entry === false || !isset($entry[1][$index]) || $entry[1][$index][0] !== $num) {
            // the index may be off in a damaged file: look the object up by number
            foreach ($entry === false ? [] : $entry[1] as $o) {
                if ($o[0] === $num) {
                    return $this->valueIn($entry[0], $o[1]);
                }
            }
            return null;
        }
        return $this->valueIn($entry[0], $entry[1][$index][1]);
    }

    /** Parse one value from another buffer (inside an object stream). */
    private function valueIn(string $data, int $offset): mixed
    {
        $saved = $this->buffer;
        $this->buffer = $data;
        try {
            $p = $offset;
            return $this->value($p);
        } finally {
            $this->buffer = $saved;
        }
    }

    private ?string $buffer = null;

    private function src(): string
    {
        return $this->buffer ?? $this->bytes;
    }

    private function skip(int &$p): void
    {
        $b = $this->src();
        $n = strlen($b);
        while ($p < $n) {
            $c = $b[$p];
            if ($c === ' ' || $c === "\n" || $c === "\r" || $c === "\t" || $c === "\f" || $c === "\0") {
                $p++;
            } elseif ($c === '%') {
                while ($p < $n && $b[$p] !== "\n" && $b[$p] !== "\r") {
                    $p++;
                }
            } else {
                break;
            }
        }
    }

    private const DELIM = " \t\r\n\f\0()<>[]{}/%";

    /** One value at $p: number, reference, name, string, array, dictionary, true / false / null. */
    private function value(int &$p, int $depth = 0): mixed
    {
        if ($depth > 100) {
            throw new PdfException('too deep');
        }
        $b = $this->src();
        $this->skip($p);
        $c = $b[$p] ?? '';
        if ($c === '') {
            return null;
        }
        if ($c === '/') {
            $p++;
            $start = $p;
            $n = strlen($b);
            while ($p < $n && !str_contains(self::DELIM, $b[$p])) {
                $p++;
            }
            $name = substr($b, $start, $p - $start);
            return new Name(str_contains($name, '#') ? preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn ($m) => chr(hexdec($m[1])), $name) : $name);
        }
        if ($c === '<' && ($b[$p + 1] ?? '') === '<') {
            $p += 2;
            $d = new Dict();
            while (true) {
                $this->skip($p);
                if (($b[$p] ?? '>') === '>') {
                    $p += 2;
                    return $d;
                }
                $k = $this->value($p, $depth + 1);
                if (!$k instanceof Name) {
                    // a broken dictionary: skip to its end
                    $end = strpos($b, '>>', $p);
                    $p = $end === false ? strlen($b) : $end + 2;
                    return $d;
                }
                $d->v[$k->v] = $this->value($p, $depth + 1);
            }
        }
        if ($c === '<') {
            $end = strpos($b, '>', $p);
            $end = $end === false ? strlen($b) : $end;
            $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($b, $p + 1, $end - $p - 1));
            $p = $end + 1;
            return new Str((string) hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex));
        }
        if ($c === '(') {
            return new Str($this->literal($p));
        }
        if ($c === '[') {
            $p++;
            $a = [];
            while (true) {
                $this->skip($p);
                $x = $b[$p] ?? ']';
                if ($x === ']') {
                    $p++;
                    return $a;
                }
                $a[] = $this->value($p, $depth + 1);
            }
        }
        if (preg_match('/\G[+-]?(\d+\.?\d*|\.\d+)/', $b, $m, 0, $p)) {
            $p += strlen($m[0]);
            $isInt = !str_contains($m[0], '.');
            if ($isInt && preg_match('/\G\s+(\d+)\s+R(?![A-Za-z])/', $b, $r, 0, $p)) {
                $p += strlen($r[0]);
                return new Ref((int) $m[0], (int) $r[1]);
            }
            return $isInt ? (int) $m[0] : (float) $m[0];
        }
        if (preg_match('/\G[A-Za-z]+/', $b, $m, 0, $p)) {
            $p += strlen($m[0]);
            return match ($m[0]) {
                'true' => true,
                'false' => false,
                default => null,
            };
        }
        $p++; // a stray character
        return null;
    }

    private function literal(int &$p): string
    {
        $b = $this->src();
        $n = strlen($b);
        $p++;
        $depth = 1;
        $out = '';
        while ($p < $n) {
            $c = $b[$p++];
            if ($c === '\\') {
                $e = $b[$p++] ?? '';
                if ($e >= '0' && $e <= '7') {
                    $oct = $e;
                    while (strlen($oct) < 3 && ($b[$p] ?? '') >= '0' && ($b[$p] ?? '') <= '7') {
                        $oct .= $b[$p++];
                    }
                    $out .= chr(octdec($oct) & 0xFF);
                    continue;
                }
                if ($e === "\r") {
                    if (($b[$p] ?? '') === "\n") {
                        $p++;
                    }
                    continue;
                }
                if ($e === "\n") {
                    continue;
                }
                $out .= match ($e) {
                    'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f",
                    default => $e,
                };
                continue;
            }
            if ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                if (--$depth === 0) {
                    break;
                }
            }
            $out .= $c;
        }
        return $out;
    }

    private function box(mixed $v): ?array
    {
        $v = $this->resolve($v);
        if (!is_array($v) || count($v) < 4) {
            return null;
        }
        $n = array_map(fn ($x) => (float) $this->resolve($x), array_slice($v, 0, 4));
        return [min($n[0], $n[2]), min($n[1], $n[3]), max($n[0], $n[2]), max($n[1], $n[3])];
    }
}
