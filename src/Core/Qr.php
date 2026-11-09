<?php

declare(strict_types=1);

namespace Phai\Core;

/**
 * QR codes as SVG, no library: byte mode, error correction level M, versions 1–10 (up to 213 bytes), the mask with
 * the lowest penalty. Enough for the links printed on documents (a signed document's footer points at its check page).
 *
 *     Qr::svg('http://phai-server/envelopes/verify?n=ENV2026110001')   // <svg ...>
 *
 * The steps follow ISO/IEC 18004 as laid out in Project Nayuki's QR Code generator (MIT).
 */
final class Qr
{
    /** Level M, versions 1–10: error correction codewords per block, and the number of blocks. */
    private const ECC = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
    private const BLOCKS = [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];

    private int $size;
    private array $m = [];
    private array $fn = [];

    public static function svg(string $text, string $color = '#000'): string
    {
        $q = self::make($text);
        $n = count($q);
        $path = '';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($q[$y][$x]) {
                    $path .= 'M' . ($x + 4) . ',' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }
        $w = $n + 8;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $w . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="' . htmlspecialchars($color) . '"/></svg>';
    }

    /** @return bool[][] modules, [y][x], true = dark */
    public static function make(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        for ($ver = 1; $ver <= 10; $ver++) {
            $cap = self::dataCodewords($ver) * 8;
            $bits = 4 + ($ver < 10 ? 8 : 16) + 8 * count($bytes);
            if ($bits <= $cap) {
                $q = new self();
                return $q->build($ver, $bytes);
            }
        }
        throw new \InvalidArgumentException('Text too long for a QR code');
    }

    private function build(int $ver, array $bytes): array
    {
        $this->size = $ver * 4 + 17;
        $this->m = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->fn = $this->m;

        // data bits: mode, count, bytes, terminator, padding
        $bb = [];
        $put = function (int $v, int $len) use (&$bb): void {
            for ($i = $len - 1; $i >= 0; $i--) {
                $bb[] = ($v >> $i) & 1;
            }
        };
        $put(4, 4);
        $put(count($bytes), $ver < 10 ? 8 : 16);
        foreach ($bytes as $b) {
            $put($b, 8);
        }
        $cap = self::dataCodewords($ver) * 8;
        $put(0, min(4, $cap - count($bb)));
        $put(0, (8 - count($bb) % 8) % 8);
        for ($pad = 0xEC; count($bb) < $cap; $pad ^= 0xEC ^ 0x11) {
            $put($pad, 8);
        }
        $data = [];
        for ($i = 0; $i < count($bb); $i += 8) {
            $v = 0;
            for ($j = 0; $j < 8; $j++) {
                $v = ($v << 1) | $bb[$i + $j];
            }
            $data[] = $v;
        }

        $this->functionPatterns($ver);
        $this->codewords($this->interleave($ver, $data));

        $best = 0;
        $low = PHP_INT_MAX;
        $plain = $this->m;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->m = $plain;
            $this->mask($mask);
            $this->format($mask);
            $p = $this->penalty();
            if ($p < $low) {
                $low = $p;
                $best = $mask;
            }
        }
        $this->m = $plain;
        $this->mask($best);
        $this->format($best);
        return $this->m;
    }

    private static function rawModules(int $ver): int
    {
        $r = (16 * $ver + 128) * $ver + 64;
        if ($ver >= 2) {
            $align = intdiv($ver, 7) + 2;
            $r -= (25 * $align - 10) * $align - 55;
            if ($ver >= 7) {
                $r -= 36;
            }
        }
        return $r;
    }

    private static function dataCodewords(int $ver): int
    {
        return intdiv(self::rawModules($ver), 8) - self::ECC[$ver] * self::BLOCKS[$ver];
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->m[$y][$x] = $dark;
        $this->fn[$y][$x] = true;
    }

    private function functionPatterns(int $ver): void
    {
        $n = $this->size;
        for ($i = 0; $i < $n; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx;
                    $y = $cy + $dy;
                    if ($x >= 0 && $x < $n && $y >= 0 && $y < $n) {
                        $d = max(abs($dx), abs($dy));
                        $this->set($x, $y, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        $pos = $this->alignment($ver);
        $k = count($pos);
        for ($i = 0; $i < $k; $i++) {
            for ($j = 0; $j < $k; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $k - 1) || ($i === $k - 1 && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($pos[$i] + $dx, $pos[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->format(0); // reserve the format areas; the real bits come with the mask
        if ($ver >= 7) {
            $rem = $ver;
            for ($i = 0; $i < 12; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            }
            $bits = ($ver << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $dark = (($bits >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3;
                $b = intdiv($i, 3);
                $this->set($a, $b, $dark);
                $this->set($b, $a, $dark);
            }
        }
    }

    private function alignment(int $ver): array
    {
        if ($ver === 1) {
            return [];
        }
        $num = intdiv($ver, 7) + 2;
        $step = intdiv($ver * 8 + $num * 3 + 5, $num * 4 - 4) * 2;
        $out = array_fill(0, $num, 0);
        $out[0] = 6;
        for ($i = $num - 1, $p = $this->size - 7; $i >= 1; $i--, $p -= $step) {
            $out[$i] = $p;
        }
        return $out;
    }

    /** Level M = 0b00. */
    private function format(int $mask): void
    {
        $data = (0 << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = fn (int $i) => (($bits >> $i) & 1) === 1;
        $n = $this->size;
        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($n - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $n - 15 + $i, $bit($i));
        }
        $this->set(8, $n - 8, true);
    }

    private function interleave(int $ver, array $data): array
    {
        $blocks = self::BLOCKS[$ver];
        $eccLen = self::ECC[$ver];
        $raw = intdiv(self::rawModules($ver), 8);
        $short = $blocks - $raw % $blocks;
        $shortLen = intdiv($raw, $blocks);
        $div = self::divisor($eccLen);
        $all = [];
        for ($i = 0, $k = 0; $i < $blocks; $i++) {
            $len = $shortLen - $eccLen + ($i < $short ? 0 : 1);
            $dat = array_slice($data, $k, $len);
            $k += $len;
            $ecc = self::remainder($dat, $div);
            if ($i < $short) {
                $dat[] = 0;
            }
            $all[] = array_merge($dat, $ecc);
        }
        $out = [];
        $n = count($all[0]);
        for ($i = 0; $i < $n; $i++) {
            foreach ($all as $j => $b) {
                if ($i !== $shortLen - $eccLen || $j >= $short) {
                    $out[] = $b[$i];
                }
            }
        }
        return $out;
    }

    private static function mul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    private static function divisor(int $degree): array
    {
        $r = array_fill(0, $degree, 0);
        $r[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $r[$j] = self::mul($r[$j], $root);
                if ($j + 1 < $degree) {
                    $r[$j] ^= $r[$j + 1];
                }
            }
            $root = self::mul($root, 2);
        }
        return $r;
    }

    private static function remainder(array $data, array $div): array
    {
        $r = array_fill(0, count($div), 0);
        foreach ($data as $b) {
            $f = $b ^ array_shift($r);
            $r[] = 0;
            foreach ($div as $i => $d) {
                $r[$i] ^= self::mul($d, $f);
            }
        }
        return $r;
    }

    private function codewords(array $data): void
    {
        $n = $this->size;
        $i = 0;
        $total = count($data) * 8;
        for ($right = $n - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($v = 0; $v < $n; $v++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $up = (($right + 1) & 2) === 0;
                    $y = $up ? $n - 1 - $v : $v;
                    if (!$this->fn[$y][$x] && $i < $total) {
                        $this->m[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function mask(int $mask): void
    {
        $n = $this->size;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($this->fn[$y][$x]) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($flip) {
                    $this->m[$y][$x] = !$this->m[$y][$x];
                }
            }
        }
    }

    /** Runs of five or more, 2 × 2 blocks and the dark / light balance (the finder-like rule is left out). */
    private function penalty(): int
    {
        $n = $this->size;
        $p = 0;
        $dark = 0;
        for ($y = 0; $y < $n; $y++) {
            $runX = $runY = 1;
            for ($x = 0; $x < $n; $x++) {
                $dark += $this->m[$y][$x] ? 1 : 0;
                if ($x > 0) {
                    if ($this->m[$y][$x] === $this->m[$y][$x - 1]) {
                        $runX++;
                        $p += $runX === 5 ? 3 : ($runX > 5 ? 1 : 0);
                    } else {
                        $runX = 1;
                    }
                    if ($this->m[$x][$y] === $this->m[$x - 1][$y]) {
                        $runY++;
                        $p += $runY === 5 ? 3 : ($runY > 5 ? 1 : 0);
                    } else {
                        $runY = 1;
                    }
                }
                if ($x > 0 && $y > 0) {
                    $c = $this->m[$y][$x];
                    if ($c === $this->m[$y][$x - 1] && $c === $this->m[$y - 1][$x] && $c === $this->m[$y - 1][$x - 1]) {
                        $p += 3;
                    }
                }
            }
        }
        $total = $n * $n;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        return $p + max(0, $k) * 10;
    }
}
