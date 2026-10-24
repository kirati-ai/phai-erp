<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Validation\Check;

/**
 * Addresses (ad_mstr). The company's own address is the row ad_addr = 'COMPANY';
 * co_mstr.co_address keeps the same address as one line of text for printed documents.
 * Thai addresses: ad_sub_district ตำบล/แขวง, ad_city อำเภอ/เขต, ad_state จังหวัด. The company address is kept in
 * the parts the Revenue Department forms ask for (PARTS); ad_line1 is made from them for one-line printing.
 */
final class Address
{
    public const COMPANY = 'COMPANY';

    /**
     * อาคาร ห้องเลขที่ ชั้นที่ หมู่บ้าน เลขที่ หมู่ที่ ตรอก/ซอย แยก ถนน, as the Revenue Department forms ask:
     * column suffix => [max length, word written before it]. The same parts exist as ad_*, cm_* (customers), vd_* (vendors).
     */
    public const PARTS = [
        'building' => [70, 'อาคาร'], 'room' => [20, 'ห้อง'], 'floor' => [20, 'ชั้น'], 'village' => [70, 'หมู่บ้าน'],
        'house_no' => [16, ''], 'moo' => [10, 'หมู่'], 'soi' => [70, 'ซอย'], 'yaek' => [70, 'แยก'], 'road' => [70, 'ถนน'],
    ];

    /** Fields of the company address form, in order. */
    public const FIELDS = ['ad_building', 'ad_room', 'ad_floor', 'ad_village', 'ad_house_no', 'ad_moo', 'ad_soi', 'ad_yaek', 'ad_road',
        'ad_line2', 'ad_sub_district', 'ad_city', 'ad_state', 'ad_zip'];

    /** The parts as one line, house number first: "99/1 หมู่ 3 อาคาร… ชั้น 2 ห้อง 5 หมู่บ้าน… ซอย… แยก… ถนน…". */
    public static function line(array $row, string $prefix = 'ad'): string
    {
        $out = [];
        foreach (['house_no', 'moo', 'building', 'floor', 'room', 'village', 'soi', 'yaek', 'road'] as $k) {
            $v = trim((string) ($row["{$prefix}_$k"] ?? ''));
            $word = self::PARTS[$k][1];
            if ($v !== '') {
                // "ถนนพระราม 1" typed with its own word is left alone
                $out[] = $word === '' || str_starts_with($v, $word) ? $v : "$word $v";
            }
        }
        return implode(' ', $out);
    }

    /** ['ad_building', 'ad_room', …] for a table prefix. */
    public static function partColumns(string $prefix): array
    {
        return array_map(fn ($k) => "{$prefix}_$k", array_keys(self::PARTS));
    }

    /**
     * A row from a form with address parts gets its one-line address (<prefix>_line1) made from them.
     * Only when the parts were sent, so an update that leaves the address alone does not touch it.
     */
    public static function withLine(array $data, string $prefix): array
    {
        if (array_intersect_key($data, array_flip(self::partColumns($prefix)))) {
            $data["{$prefix}_line1"] = self::line($data, $prefix) ?: null;
        }
        return $data;
    }

    /**
     * A Thai vendor / customer must have what the later documents print and report: tax ID (13 digits) and branch
     * (5 digits) for tax invoices, 50 ทวิ, ภ.ง.ด. and the VAT reports, and the province / district / subdistrict / zip
     * for those forms and the e-Tax XML. A party abroad (country other than Thailand) has none of these.
     * Checks what was sent (an update that leaves a field out keeps it), together with the rest of the form.
     * @param callable(): array $validate the resource's own checks
     */
    public static function requireParty(array $data, bool $creating, string $prefix, callable $validate): array
    {
        $errors = [];
        $country = mb_strtolower(trim((string) ($data["{$prefix}_country"] ?? 'Thailand')));
        $abroad = !in_array($country, ['', 'thailand', 'ไทย', 'ประเทศไทย', 'th'], true);
        $rules = [
            "{$prefix}_tax_id_num" => '/^\d{13}$/', "{$prefix}_tax_branch" => '/^\d{5}$/', "{$prefix}_province" => null,
            "{$prefix}_district" => null, "{$prefix}_sub_district" => null, "{$prefix}_zip" => '/^\d{5}$/',
        ];
        foreach ($abroad ? [] : $rules as $col => $pattern) {
            if (!$creating && !array_key_exists($col, $data)) {
                continue;
            }
            $v = trim((string) ($data[$col] ?? ''));
            if ($v === '' && $col === "{$prefix}_tax_branch") {
                continue; // left empty: the column default 00000 (head office)
            }
            if ($v === '') {
                $errors[$col] = t('validation.required');
            } elseif ($pattern && !preg_match($pattern, $v)) {
                $errors[$col] = t('validation.party_' . substr($col, strlen($prefix) + 1));
            }
        }
        try {
            $row = $validate();
        } catch (HttpException $e) {
            throw HttpException::invalid($e->fields + $errors);
        }
        if ($errors) {
            throw HttpException::invalid($errors);
        }
        return $row;
    }

    /**
     * Rows saved before the parts existed: the house number comes from the start of <prefix>_line1 and the rest
     * moves in front of <prefix>_line2, so the printed address stays the same. Rows that have parts are left alone.
     * @return int rows changed
     */
    public static function splitOldLines(string $table, string $prefix, string $key): int
    {
        $db = App::db();
        $cols = self::partColumns($prefix);
        $empty = implode(' AND ', array_map(fn ($c) => "COALESCE($c, '') = ''", $cols));
        $n = 0;
        foreach ($db->all("SELECT $key AS k, {$prefix}_line1 AS l1, {$prefix}_line2 AS l2 FROM $table WHERE $empty AND COALESCE({$prefix}_line1, '') <> ''") as $r) {
            preg_match('#^(\d[\d/\-]*)?\s*(.*)$#u', trim((string) $r['l1']), $m);
            $house = ($m[1] ?? '') ?: null;
            $rest = trim(($m[2] ?? '') . ' ' . ($r['l2'] ?? '')) ?: null;
            $db->update($table, ["{$prefix}_house_no" => $house, "{$prefix}_line1" => $house, "{$prefix}_line2" => $rest], [$key => $r['k']]);
            $n++;
        }
        return $n;
    }

    /**
     * The company address for the form. An address saved before the parts existed gets its house number from the
     * start of ad_line1 and the rest kept in ad_line2, so nothing is lost when it is first edited.
     */
    public static function companyParts(): array
    {
        $ad = self::company() ?? [];
        $out = array_intersect_key($ad, array_flip(self::FIELDS)) + array_fill_keys(self::FIELDS, null);
        $hasParts = array_filter(array_intersect_key($out, array_flip(self::partColumns('ad'))), fn ($v) => trim((string) $v) !== '');
        $line1 = trim((string) ($ad['ad_line1'] ?? ''));
        if (!$hasParts && $line1 !== '') {
            preg_match('#^(\d[\d/\-]*)?\s*(.*)$#u', $line1, $m);
            $out['ad_house_no'] = ($m[1] ?? '') ?: null;
            $out['ad_line2'] = trim(($m[2] ?? '') . ' ' . ($out['ad_line2'] ?? '')) ?: null;
        }
        return $out;
    }

    /** One line for printing: "12 ถนน… แขวง… เขต… กรุงเทพมหานคร 10110". */
    public static function format(?array $ad): string
    {
        if (!$ad) {
            return '';
        }
        return trim(implode(' ', array_filter(array_map(
            fn ($k) => trim((string) ($ad[$k] ?? '')),
            ['ad_line1', 'ad_line2', 'ad_line3', 'ad_sub_district', 'ad_city', 'ad_state', 'ad_zip']
        ))));
    }

    public static function company(): ?array
    {
        return App::db()->one('SELECT * FROM ad_mstr WHERE ad_addr = ?', [self::COMPANY]);
    }

    /** Check the address fields of a request body; errors are collected in $c under the same names. */
    public static function check(Check $c, array $b): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = $c->text($f, $b[$f] ?? '', false, self::PARTS[substr($f, 3)][0] ?? ($f === 'ad_zip' ? 20 : ($f === 'ad_line2' ? 255 : 100)), false);
        }
        $out['ad_line1'] = self::line($out) ?: null;
        if ($out['ad_zip'] !== null && !preg_match('/^[0-9A-Za-z -]{3,20}$/', $out['ad_zip'])) {
            $c->fail('ad_zip', t('validation.in'));
        }
        return $out;
    }

    /** Save the company address and its printed form. */
    public static function saveCompany(array $fields): void
    {
        $db = App::db();
        $co = $db->one('SELECT co_name, co_phone, co_tax_id, co_branch FROM co_mstr WHERE co_id = 1') ?? [];
        $row = $fields + [
            'ad_name' => $co['co_name'] ?? '', 'ad_phone' => $co['co_phone'] ?? null, 'ad_tax_id' => $co['co_tax_id'] ?? null,
            'ad_tax_branch' => $co['co_branch'] ?? '00000', 'updated_by' => Auth::userId(),
        ];
        if (self::company()) {
            $db->update('ad_mstr', $row, ['ad_addr' => self::COMPANY]);
        } else {
            $db->insert('ad_mstr', $row + ['ad_addr' => self::COMPANY, 'ad_country' => 'ไทย', 'created_by' => Auth::userId()]);
        }
        $db->update('co_mstr', ['co_address' => self::format($fields) ?: null, 'updated_by' => Auth::userId()], ['co_id' => 1]);
    }
}
