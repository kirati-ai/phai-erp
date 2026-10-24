<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\App;

/** Bills of material (ps_mstr): parent item -> components, quantity per parent, scrap %. */
final class Bom
{
    /**
     * Components needed for $qty of $part on $date (one level), scrap included.
     * @return list<array{part: string, qty: float}>
     */
    public static function explode(string $part, float $qty, string $date): array
    {
        $rows = App::db()->all(
            'SELECT ps_comp, ps_qty_per, ps_scrap FROM ps_mstr
              WHERE ps_par = ? AND (ps_start IS NULL OR ps_start <= ?) AND (ps_end IS NULL OR ps_end >= ?)
              ORDER BY ps_comp',
            [$part, $date, $date]
        );
        return array_map(fn ($r) => [
            'part' => $r['ps_comp'],
            'qty'  => round($qty * (float) $r['ps_qty_per'] * (1 + (float) $r['ps_scrap'] / 100), 5),
        ], $rows);
    }

    /** Would putting $component under $parent make the structure loop back to $parent? */
    public static function wouldLoop(string $parent, string $component): bool
    {
        if ($parent === $component) {
            return true;
        }
        $seen = [];
        $todo = [$component];
        while ($todo) {
            $p = array_pop($todo);
            if (isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            foreach (App::db()->all('SELECT ps_comp FROM ps_mstr WHERE ps_par = ?', [$p]) as $r) {
                if ($r['ps_comp'] === $parent) {
                    return true;
                }
                $todo[] = $r['ps_comp'];
            }
        }
        return false;
    }

    /**
     * Low-level code of every item that appears in a BOM: 0 for top items, deepest
     * level a component is used at otherwise. MRP plans parents before components.
     * @return array<string, int>
     */
    public static function lowLevelCodes(): array
    {
        $children = [];
        $isChild = [];
        foreach (App::db()->all('SELECT ps_par, ps_comp FROM ps_mstr') as $r) {
            $children[$r['ps_par']][] = $r['ps_comp'];
            $isChild[$r['ps_comp']] = true;
        }
        $level = [];
        $walk = function (string $p, int $l, array $path) use (&$walk, &$level, $children): void {
            if (isset($path[$p])) {
                return; // a loop slipped in: stop rather than spin
            }
            $level[$p] = max($level[$p] ?? 0, $l);
            foreach ($children[$p] ?? [] as $c) {
                $walk($c, $l + 1, $path + [$p => true]);
            }
        };
        foreach (array_keys($children) as $p) {
            if (!isset($isChild[$p])) {
                $walk($p, 0, []);
            }
        }
        return $level;
    }
}
