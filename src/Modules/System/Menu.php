<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;

/** Side menu from mnu_det, showing only items the user may view (mnu_access = prog_code). */
final class Menu
{
    /** @return list<array{name: string, icon: string, items: list<array>}> groups in the order of their header's mnu_seq */
    public static function tree(): array
    {
        self::ensureSynced();
        $rows = App::db()->all(
            'SELECT h.mnu_seq AS grp_seq, m.mnu_menu, m.mnu_code, m.mnu_name, m.mnu_access, m.mnu_icon, m.mnu_link, m.mnu_target, m.mnu_seq
               FROM mnu_det m
               LEFT JOIN mnu_det h ON h.mnu_menu = m.mnu_menu AND h.mnu_code LIKE \'%.0\'
              ORDER BY h.mnu_seq, m.mnu_menu, m.mnu_seq'
        );

        $groups = [];
        foreach ($rows as $row) {
            $g = $row['mnu_menu'];
            $groups[$g] ??= ['name' => '', 'icon' => '', 'items' => []];
            $row['mnu_name'] = \Phai\Core\Lang::name('menu', $row['mnu_code'], $row['mnu_name']); // lang/<locale>/menu.php
            if (str_ends_with($row['mnu_code'], '.0')) {
                $groups[$g]['name'] = $row['mnu_name'];
                $groups[$g]['icon'] = $row['mnu_icon'];
            } elseif (($row['mnu_access'] === '*' || Auth::can($row['mnu_access'])) && !self::unused($row['mnu_link'])) {
                $groups[$g]['items'][] = $row;
            }
        }
        foreach ($groups as &$group) {
            $group['items'] = self::dropEmptyLabels($group['items']);
        }
        unset($group);
        return array_values(array_filter($groups, fn ($g) => $g['items']));
    }

    /** A page for a feature not set up yet: e-Tax stays hidden until email is turned on, a certificate is in (XML) or something was sent. */
    private static function unused(string $link): bool
    {
        return $link === '/etax' && !\Phai\Modules\Accounting\Etax::enabled() && !is_file(\Phai\Modules\Accounting\Etax::certFile())
            && !App::db()->value('SELECT 1 FROM etax_log LIMIT 1');
    }

    /** A row with link "#" inside a group is a small heading (e.g. "ลูกหนี้ (AR)"); one with nothing visible under it goes. */
    public static function isLabel(array $item): bool
    {
        return $item['mnu_link'] === '#';
    }

    /** The "ตั้งค่า" heading of a group (x.10): what is under it folds away, closed unless opened. */
    public static function isFold(array $item): bool
    {
        return self::isLabel($item) && str_ends_with($item['mnu_code'], '.10');
    }

    private static function dropEmptyLabels(array $items): array
    {
        $out = [];
        foreach ($items as $i => $item) {
            $next = $items[$i + 1] ?? null;
            if (!self::isLabel($item) || ($next !== null && !self::isLabel($next))) {
                $out[] = $item;
            }
        }
        return $out;
    }

    private static function ensureSynced(): void
    {
        try {
            $db = App::db();
            if (!$db->value("SELECT 1 FROM mnu_det WHERE mnu_link = '/purchase' LIMIT 1")) {
                $file = PHAI_ROOT . '/database/seeds/40_menu.sql';
                if (is_file($file)) {
                    $db->transaction(function () use ($db, $file) {
                        $db->run('DELETE FROM mnu_det');
                        foreach (\Phai\Install\Installer::statements((string) file_get_contents($file)) as $sql) {
                            $db->run($sql);
                        }
                    });
                }
            }
        } catch (\Throwable) {
        }
    }
}
