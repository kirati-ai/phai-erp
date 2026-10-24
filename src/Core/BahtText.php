<?php

declare(strict_types=1);

namespace Phai\Core;

/**
 * Amount in Thai words for documents: 1250.50 -> "หนึ่งพันสองร้อยห้าสิบบาทห้าสิบสตางค์".
 */
final class BahtText
{
    private const DIGITS = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    private const PLACES = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

    public static function of(float $amount): string
    {
        $negative = $amount < 0;
        $satang = (int) round(abs($amount) * 100);
        $baht = intdiv($satang, 100);
        $satang %= 100;

        if ($baht === 0 && $satang === 0) {
            return 'ศูนย์บาทถ้วน';
        }
        $text = '';
        if ($baht > 0) {
            $text .= self::number($baht) . 'บาท';
        }
        $text .= $satang > 0 ? self::number($satang) . 'สตางค์' : 'ถ้วน';
        return ($negative ? 'ลบ' : '') . $text;
    }

    /** Whole number in words; millions repeat ("หนึ่งล้านล้าน"). */
    private static function number(int $n): string
    {
        if ($n >= 1000000) {
            return self::number(intdiv($n, 1000000)) . 'ล้าน' . ($n % 1000000 > 0 ? self::below(($n % 1000000), true) : '');
        }
        return self::below($n, false);
    }

    /** 1 – 999,999. $afterMillion: a trailing 1 is still "เอ็ด" (e.g. ล้านเอ็ด). */
    private static function below(int $n, bool $afterMillion): string
    {
        $text = '';
        $digits = array_reverse(str_split((string) $n));
        $count = count($digits);
        for ($place = $count - 1; $place >= 0; $place--) {
            $d = (int) $digits[$place];
            if ($d === 0) {
                continue;
            }
            if ($place === 1 && $d === 1) {
                $text .= 'สิบ';
            } elseif ($place === 1 && $d === 2) {
                $text .= 'ยี่สิบ';
            } elseif ($place === 0 && $d === 1 && ($count > 1 || $afterMillion)) {
                $text .= 'เอ็ด';
            } else {
                $text .= self::DIGITS[$d] . self::PLACES[$place];
            }
        }
        return $text;
    }
}
