<?php

declare(strict_types=1);

namespace Phai\Core\PdfKit;

/**
 * Writes a new PDF from pages of other PDFs (Reader), each page optionally with things drawn on top: another page made
 * into a form XObject (an overlay the browser drew: signatures, Thai text, page footers) and the appearance of its
 * own form fields and annotations made part of the page (flatten). What is not copied stays behind on purpose:
 * JavaScript, open actions, links, form fields, outlines, attachments, metadata, earlier updates. So the new file
 * shows exactly what its pages show and nothing hidden.
 *
 *     $w = new Writer();
 *     $form = $w->form($overlay, $overlay->pages()[0]);
 *     $w->addPage($doc, $doc->pages()[0], ['flatten' => true, 'ops' => Writer::place($page, $overlayPage, $k, 'Ov1'), 'xobjects' => ['Ov1' => $form]]);
 *     $bytes = $w->output(['Title' => 'ENV2026110001']);
 */
final class Writer
{
    /** object number => serialized object (null while reserved) */
    private array $objects = [];
    /** spl_object_id(reader) => [old number => new number] */
    private array $maps = [];
    private array $kids = [];
    private int $pagesNum;

    public function __construct()
    {
        $this->pagesNum = $this->reserve();
    }

    public function pageCount(): int
    {
        return count($this->kids);
    }

    /**
     * Copy a page. $opt: 'flatten' => draw form fields and annotations into the page (default true),
     * 'ops' => content drawn on top, 'xobjects' => [name => object number] the ops use.
     */
    public function addPage(Reader $r, array $page, array $opt = []): int
    {
        $src = $page['dict'];
        $d = new Dict(['Type' => new Name('Page'), 'Parent' => new Ref($this->pagesNum)]);
        foreach (['Group', 'UserUnit', 'TrimBox', 'BleedBox', 'ArtBox'] as $k) {
            if ($src->get($k) !== null) {
                $d->v[$k] = $this->import($r, $src->get($k));
            }
        }
        $d->v['MediaBox'] = $page['media'];
        if ($page['box'] !== $page['media']) {
            $d->v['CropBox'] = $page['box'];
        }
        if ($page['rotate']) {
            $d->v['Rotate'] = $page['rotate'];
        }

        $res = new Dict();
        foreach ($page['resources']->v as $k => $v) {
            if ($k !== 'XObject') {
                $res->v[$k] = $this->import($r, $v);
            }
        }
        $xo = new Dict();
        $own = $r->resolve($page['resources']->get('XObject'));
        foreach ($own instanceof Dict ? $own->v : [] as $k => $v) {
            $xo->v[$k] = $this->import($r, $v);
        }

        $ops = '';
        if ($opt['flatten'] ?? true) {
            $ops .= $this->flatten($r, $src, $xo);
        }
        foreach ($opt['xobjects'] ?? [] as $name => $num) {
            $xo->v[$name] = new Ref($num);
        }
        $ops .= $opt['ops'] ?? '';
        if ($xo->v) {
            $res->v['XObject'] = $xo;
        }
        $d->v['Resources'] = $res;

        $contents = [];
        $c = $src->get('Contents');
        $resolved = $r->resolve($c);
        foreach (is_array($resolved) ? $resolved : [$c] as $part) {
            if ($part instanceof Ref || $part instanceof Stream) {
                $contents[] = $this->import($r, $part);
            }
        }
        if ($ops !== '' || !empty($opt['pre'])) {
            // the page's own content may leave the graphics state changed: keep it inside q ... Q
            array_unshift($contents, new Ref($this->stream(new Dict(), "q\n" . ($opt['pre'] ?? ''))));
            $contents[] = new Ref($this->stream(new Dict(), "\nQ\n" . $ops, true));
        }
        $d->v['Contents'] = $contents;
        $num = $this->add($d);
        $this->kids[] = new Ref($num);
        return count($this->kids);
    }

    /** A page of another PDF as a form XObject, to draw on top of a page. Returns its object number. */
    public function form(Reader $r, array $page): int
    {
        $box = $page['media'];
        $dict = new Dict([
            'Type' => new Name('XObject'), 'Subtype' => new Name('Form'), 'BBox' => $box,
            'Resources' => $this->import($r, $page['resources']),
        ]);
        if ($page['dict']->get('Group') !== null) {
            $dict->v['Group'] = $this->import($r, $page['dict']->get('Group'));
        }
        return $this->stream($dict, $r->content($page), true);
    }

    /**
     * The content that draws form $name over $target, when the overlay page $overlay drew the target page at its top
     * left, scaled by $k (all in points, top-left origin, as the target page is shown, after its rotation).
     */
    public static function place(array $target, array $overlay, float $k, string $name): string
    {
        $rot = self::shown($target);
        [$ox0, , , $oy1] = $overlay['media'];
        $t = [1 / $k, 0, 0, 1 / $k, -$ox0 / $k, $target['height'] - $oy1 / $k];
        return 'q ' . self::nums($rot) . ' cm ' . self::nums($t) . " cm /$name Do Q\n";
    }

    /**
     * Content that makes the page's own content $s times smaller, kept at the top and centred as the page is shown
     * (after its rotation): room at the bottom for a footer, without covering anything on the page.
     */
    public static function shrink(array $page, float $s): string
    {
        $r = self::shown($page);
        $w = $page['width'];
        $h = $page['height'];
        $scale = [$s, 0, 0, $s, (1 - $s) * $w / 2, (1 - $s) * $h];
        [$a, $b, $c, $d, $e, $f] = $r;
        $det = $a * $d - $b * $c;
        $inv = [$d / $det, -$b / $det, -$c / $det, $a / $det, ($c * $f - $d * $e) / $det, ($b * $e - $a * $f) / $det];
        // applied right to left: to shown space, scale there, back to the page's own space
        return self::nums($r) . ' cm ' . self::nums($scale) . ' cm ' . self::nums($inv) . " cm\n";
    }

    /** The matrix from the page as shown (origin bottom left, after rotation) to the page's own space. */
    private static function shown(array $page): array
    {
        [$x0, $y0, $x1, $y1] = $page['box'];
        $w = $x1 - $x0;
        $h = $y1 - $y0;
        return match ($page['rotate']) {
            90 => [0, 1, -1, 0, $x0 + $w, $y0],
            180 => [-1, 0, 0, -1, $x0 + $w, $y0 + $h],
            270 => [0, -1, 1, 0, $x0, $y0 + $h],
            default => [1, 0, 0, 1, $x0, $y0],
        };
    }

    /** The finished file. $info: Title, Subject, Keywords, Author... (UTF-8); $id: what the file /ID is made from. */
    public function output(array $info = [], string $id = ''): string
    {
        $this->objects[$this->pagesNum] = $this->ser(new Dict(['Type' => new Name('Pages'), 'Kids' => $this->kids, 'Count' => count($this->kids)]));
        $catalog = $this->add(new Dict(['Type' => new Name('Catalog'), 'Pages' => new Ref($this->pagesNum)]));
        $infoDict = new Dict(['Producer' => self::text('PhaiERP'), 'CreationDate' => new Str('D:' . date('YmdHisO'))]);
        $infoDict->v['CreationDate'] = new Str('D:' . date('YmdHis') . substr_replace(date('O'), "'", 3, 0) . "'");
        foreach ($info as $k => $v) {
            $infoDict->v[$k] = self::text((string) $v);
        }
        $infoNum = $this->add($infoDict);

        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        ksort($this->objects);
        foreach ($this->objects as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= "$num 0 obj\n" . ($body ?? 'null') . "\nendobj\n";
        }
        $xref = strlen($out);
        $size = max(array_keys($this->objects)) + 1;
        $out .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $out .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $hash = md5($id !== '' ? $id : $out);
        $out .= "trailer\n<< /Size $size /Root $catalog 0 R /Info $infoNum 0 R /ID [<$hash> <$hash>] >>\nstartxref\n$xref\n%%EOF\n";
        return $out;
    }

    // ------------------------------------------------------------------ copying

    /** A value from $r, with every object it refers to copied once into this file. Pages are never pulled in. */
    private function import(Reader $r, mixed $v, int $depth = 0): mixed
    {
        if ($depth > 200) {
            throw new PdfException('too deep');
        }
        if ($v instanceof Ref) {
            $map = &$this->maps[spl_object_id($r)];
            if (isset($map[$v->num])) {
                return $map[$v->num] === 0 ? null : new Ref($map[$v->num]);
            }
            $obj = $r->object($v->num);
            if ($obj === null || ($obj instanceof Dict && in_array($obj->name('Type'), ['Page', 'Pages', 'Catalog', 'Annot'], true))) {
                $map[$v->num] = 0;
                return null;
            }
            $num = $this->reserve();
            $map[$v->num] = $num;
            $this->objects[$num] = $this->ser($obj instanceof Stream
                ? new Stream($this->import($r, $obj->dict, $depth + 1), $obj->data)
                : $this->import($r, $obj, $depth + 1));
            return new Ref($num);
        }
        if ($v instanceof Stream) {
            return new Ref($this->add(new Stream($this->import($r, $v->dict, $depth + 1), $v->data)));
        }
        if ($v instanceof Dict) {
            $d = new Dict();
            foreach ($v->v as $k => $x) {
                if ($k === 'Parent' || $k === 'P' && $x instanceof Ref) {
                    continue;
                }
                $d->v[$k] = $this->import($r, $x, $depth + 1);
            }
            return $d;
        }
        if (is_array($v)) {
            return array_map(fn ($x) => $this->import($r, $x, $depth + 1), $v);
        }
        return $v;
    }

    /**
     * The appearance of the page's form fields and annotations, drawn as part of the page: what was filled in or
     * stamped stays visible, but nothing is left to click or change. Links and hidden annotations go.
     */
    private function flatten(Reader $r, Dict $page, Dict $xo): string
    {
        $annots = $r->resolve($page->get('Annots'));
        $ops = '';
        $n = 0;
        foreach (is_array($annots) ? $annots : [] as $a) {
            $a = $r->resolve($a);
            if (!$a instanceof Dict || in_array($a->name('Subtype'), ['Link', 'Popup'], true)) {
                continue;
            }
            $flags = (int) $r->resolve($a->get('F') ?? 0);
            if ($flags & (2 | 32)) {
                continue;
            }
            $ap = $r->resolve($a->get('AP'));
            if (!$ap instanceof Dict) {
                continue;
            }
            $normal = $ap->get('N');
            $resolved = $r->resolve($normal);
            if ($resolved instanceof Dict && !$resolved instanceof Stream) {
                $state = $a->name('AS');
                $normal = $state !== null ? $resolved->get($state) : null;
                $resolved = $r->resolve($normal);
            }
            if (!$resolved instanceof Stream) {
                continue;
            }
            $rect = self::rect($r, $a->get('Rect'));
            $bbox = self::rect($r, $resolved->dict->get('BBox'));
            if ($rect === null || $bbox === null) {
                continue;
            }
            $m = array_map(fn ($x) => (float) $r->resolve($x), (array) ($r->resolve($resolved->dict->get('Matrix')) ?? [1, 0, 0, 1, 0, 0]));
            if (count($m) < 6) {
                $m = [1, 0, 0, 1, 0, 0];
            }
            $xs = $ys = [];
            foreach ([[$bbox[0], $bbox[1]], [$bbox[2], $bbox[1]], [$bbox[0], $bbox[3]], [$bbox[2], $bbox[3]]] as [$x, $y]) {
                $xs[] = $m[0] * $x + $m[2] * $y + $m[4];
                $ys[] = $m[1] * $x + $m[3] * $y + $m[5];
            }
            $tw = max($xs) - min($xs);
            $th = max($ys) - min($ys);
            if ($tw < 0.01 || $th < 0.01) {
                continue;
            }
            $sx = ($rect[2] - $rect[0]) / $tw;
            $sy = ($rect[3] - $rect[1]) / $th;
            $dict = $this->import($r, $resolved->dict);
            $dict->v['Type'] = new Name('XObject');
            $dict->v['Subtype'] = new Name('Form');
            $name = 'PhaiAn' . ++$n;
            $xo->v[$name] = new Ref($this->add(new Stream($dict, $resolved->data)));
            $ops .= 'q ' . self::nums([$sx, 0, 0, $sy, $rect[0] - min($xs) * $sx, $rect[1] - min($ys) * $sy]) . " cm /$name Do Q\n";
        }
        return $ops;
    }

    private static function rect(Reader $r, mixed $v): ?array
    {
        $v = $r->resolve($v);
        if (!is_array($v) || count($v) < 4) {
            return null;
        }
        $n = array_map(fn ($x) => (float) $r->resolve($x), array_slice($v, 0, 4));
        return [min($n[0], $n[2]), min($n[1], $n[3]), max($n[0], $n[2]), max($n[1], $n[3])];
    }

    // ------------------------------------------------------------------ objects

    private function reserve(): int
    {
        $num = count($this->objects) + 1;
        $this->objects[$num] = null;
        return $num;
    }

    private function add(mixed $value): int
    {
        $num = $this->reserve();
        $this->objects[$num] = $this->ser($value);
        return $num;
    }

    private function stream(Dict $dict, string $data, bool $compress = false): int
    {
        if ($compress) {
            $data = (string) gzcompress($data, 6);
            $dict->v['Filter'] = new Name('FlateDecode');
        }
        return $this->add(new Stream($dict, $data));
    }

    /** UTF-8 text as a PDF text string: plain bytes when ASCII, else UTF-16BE with its byte order mark. */
    public static function text(string $s): Str
    {
        return new Str(preg_match('/^[\x20-\x7e]*$/', $s) ? $s : "\xFE\xFF" . mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'));
    }

    private static function nums(array $a): string
    {
        return implode(' ', array_map(fn ($x) => self::num((float) $x), $a));
    }

    private static function num(int|float $v): string
    {
        if (is_int($v)) {
            return (string) $v;
        }
        $s = rtrim(rtrim(sprintf('%.5F', $v), '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }

    private function ser(mixed $v): string
    {
        if ($v === null) {
            return 'null';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v) || is_float($v)) {
            return self::num($v);
        }
        if ($v instanceof Name) {
            return '/' . preg_replace_callback('/[^\x21-\x7e]|[#()<>\[\]{}\/%]/', fn ($m) => sprintf('#%02X', ord($m[0])), $v->v);
        }
        if ($v instanceof Ref) {
            return "{$v->num} 0 R";
        }
        if ($v instanceof Str) {
            return '<' . bin2hex($v->v) . '>';
        }
        if ($v instanceof Stream) {
            $d = new Dict($v->dict->v);
            $d->v['Length'] = strlen($v->data);
            return $this->ser($d) . "\nstream\n" . $v->data . "\nendstream";
        }
        if ($v instanceof Dict) {
            $out = '<<';
            foreach ($v->v as $k => $x) {
                $out .= $this->ser(new Name((string) $k)) . ' ' . $this->ser($x) . ' ';
            }
            return rtrim($out) . '>>';
        }
        if (is_array($v)) {
            return '[' . implode(' ', array_map(fn ($x) => $this->ser($x), $v)) . ']';
        }
        return 'null';
    }
}
