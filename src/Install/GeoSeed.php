<?php

declare(strict_types=1);

namespace Phai\Install;

/**
 * Thai provinces, districts and sub-districts for the installer's address pickers.
 * There is no database yet, so they are read from the seed file itself.
 */
final class GeoSeed
{
    private static ?array $data = null;

    /** @return list<array{id: int, name: string}> */
    public static function provinces(): array
    {
        $rows = self::data()['pv'];
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return $rows;
    }

    /** @return list<array{id: int, name: string}> */
    public static function districts(int $province): array
    {
        return self::children('am', $province);
    }

    /** @return list<array{id: int, name: string, zip: string}> */
    public static function subdistricts(int $district): array
    {
        return self::children('tb', $district);
    }

    private static function children(string $table, int $parent): array
    {
        $rows = array_values(array_filter(self::data()[$table], fn ($r) => $r['parent'] === $parent));
        usort($rows, fn ($a, $b) => strcmp($a['name'], $b['name']));
        return array_map(function ($r) {
            unset($r['parent']);
            return $r;
        }, $rows);
    }

    private static function data(): array
    {
        if (self::$data !== null) {
            return self::$data;
        }
        $sql = (string) file_get_contents(PHAI_ROOT . '/database/seeds/10_geography.sql');
        $out = ['pv' => [], 'am' => [], 'tb' => []];
        foreach (Installer::statements($sql) as $stmt) {
            if (!preg_match('/^INSERT INTO `(pv|am|tb)_mstr`/', $stmt, $m)) {
                continue;
            }
            $table = $m[1];
            // one tuple per row: (1,'10','กรุงเทพมหานคร','Bangkok', ...)
            preg_match_all("/\\(((?:[^()']|'(?:[^'\\\\]|\\\\.|'')*')*)\\)/u", substr($stmt, (int) strpos($stmt, 'VALUES')), $tuples);
            foreach ($tuples[1] as $tuple) {
                $v = str_getcsv($tuple, ',', "'", '\\');
                $out[$table][] = match ($table) {
                    'pv' => ['id' => (int) $v[0], 'name' => $v[2]],
                    'am' => ['id' => (int) $v[0], 'name' => $v[2], 'parent' => (int) $v[4]],
                    'tb' => ['id' => (int) $v[0], 'name' => $v[2], 'parent' => (int) $v[4], 'zip' => (string) $v[5]],
                };
            }
        }
        return self::$data = $out;
    }
}
