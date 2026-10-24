<?php

declare(strict_types=1);
namespace Phai\Core\Crud;

use Phai\Core\Lang;

/** Display metadata for public list fields. Values never grant extra access. */
final class GridFields
{
    public static function describe(string $name, array $info = []): array
    {
        $suffix = preg_replace('/^[a-z]+_/', '', $name);
        // a field label, else the common column words (common.col_<name after the table prefix>), else the column comment
        $col = Lang::has("common.col_$name") ? $name : $suffix; // created_by etc. have no table prefix
        $label = Lang::has("fields.$name") ? t("fields.$name") : (Lang::has("common.col_$col") ? t("common.col_$col") : '');
        $label = $label ?: Lang::column($name, trim((string) preg_split('/\s*[(,]/u', $info['Comment'] ?? '')[0]));
        $label = $label ?: ucwords(str_replace('_', ' ', $name));
        $type = strtolower($info['Type'] ?? '');
        $isAmt = str_ends_with($name, '_amt');
        $scale = preg_match('/decimal\(\d+,(\d+)\)/', $type, $match) ? (int) $match[1] : ($isAmt ? 2 : 0);
        return ['key' => $name, 'label' => $label,
            'type' => $type === 'date' ? 'date' : (preg_match('/^(decimal|float|double|.*int)/', $type) || $isAmt ? 'number' : 'text'),
            'scale' => $scale, 'hidden' => true];
    }
}
