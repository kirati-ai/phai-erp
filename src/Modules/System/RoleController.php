<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;

#[Program('roles')]
final class RoleController
{
    #[Get('/roles')]
    public function page(): string
    {
        return View::render('system/roles', [
            'title'   => t('system.roles'),
            'help'    => 'roles',
            'scripts' => ['js/pages/roles.js'],
        ]);
    }

    #[Get('/api/roles')]
    public function list(): array
    {
        return Role::list(['per_page' => 200]);
    }

    #[Post('/api/roles')]
    public function create(RoleInput $input): array
    {
        return Role::create($input);
    }

    #[Put('/api/roles/{role_code}')]
    public function update(string $role_code, RoleInput $input): array
    {
        self::notAdmin($role_code);
        return Role::update($role_code, $input);
    }

    #[Delete('/api/roles/{role_code}')]
    public function delete(string $role_code): null
    {
        self::notAdmin($role_code);
        Role::delete($role_code);
        return null;
    }

    /**
     * Every program with this role's rights, in menu order:
     * [{prog_code, prog_name, group, view: bool, add: bool, ...}]
     */
    #[Get('/api/roles/{role_code}/perms')]
    public function perms(string $role_code): array
    {
        Role::findOrFail($role_code);
        $rows = App::db()->all(
            'SELECT p.prog_code, p.prog_name, g.mnu_name AS grp, g.mnu_code AS grp_code, d.*
               FROM prog_mstr p
               LEFT JOIN (SELECT mnu_access, MIN(CONCAT(mnu_menu, \'|\', mnu_code)) AS k FROM mnu_det GROUP BY mnu_access) one
                      ON one.mnu_access = p.prog_code
               LEFT JOIN mnu_det m ON CONCAT(m.mnu_menu, \'|\', m.mnu_code) = one.k
               LEFT JOIN mnu_det g ON g.mnu_menu = m.mnu_menu AND g.mnu_code LIKE \'%.0\'
               LEFT JOIN role_prog_det d ON d.rpd_prog = p.prog_code AND d.rpd_role = ?
              WHERE p.prog_active = 1
              ORDER BY m.mnu_menu IS NULL, g.mnu_seq, m.mnu_seq, p.prog_code',
            [$role_code]
        );
        return array_map(function ($r) {
            $out = [ // names in the screen's language (lang/<locale>/programs.php, menu.php)
                'prog_code' => $r['prog_code'],
                'prog_name' => \Phai\Core\Lang::name('programs', $r['prog_code'], (string) $r['prog_name']),
                'group'     => $r['grp'] === null ? '' : \Phai\Core\Lang::name('menu', (string) $r['grp_code'], (string) $r['grp']),
            ];
            foreach (Auth::ACTIONS as $a) {
                $out[$a] = (bool) ($r['rpd_can_' . $a] ?? false);
            }
            return $out;
        }, $rows);
    }

    /** Body: {"perms": {"uoms": ["view", "add"], ...}} — replaces all rights of the role. */
    #[Put('/api/roles/{role_code}/perms'), Action('edit')]
    public function savePerms(string $role_code, PermsInput $input): null
    {
        self::notAdmin($role_code);
        Role::findOrFail($role_code);

        $db = App::db();
        $known = array_flip(array_column($db->all('SELECT prog_code FROM prog_mstr'), 'prog_code'));
        $new = [];
        foreach ($input->perms as $prog => $actions) {
            if (!isset($known[$prog]) || !is_array($actions)) {
                continue;
            }
            $actions = array_values(array_intersect(Auth::ACTIONS, $actions));
            if ($actions) {
                $new[$prog] = $actions;
            }
        }
        ksort($new);

        $db->transaction(function (Db $db) use ($role_code, $new) {
            $old = [];
            foreach ($db->all('SELECT * FROM role_prog_det WHERE rpd_role = ? ORDER BY rpd_prog', [$role_code]) as $r) {
                $old[$r['rpd_prog']] = array_values(array_filter(Auth::ACTIONS, fn ($a) => (bool) $r['rpd_can_' . $a]));
            }
            $old = array_filter($old);
            if ($old === $new) {
                return;
            }

            $db->delete('role_prog_det', ['rpd_role' => $role_code]);
            $now = date('Y-m-d H:i:s');
            foreach ($new as $prog => $actions) {
                $row = ['rpd_role' => $role_code, 'rpd_prog' => $prog];
                foreach (Auth::ACTIONS as $a) {
                    $row['rpd_can_' . $a] = in_array($a, $actions, true) ? 1 : 0;
                }
                $db->insert('role_prog_det', $row + [
                    'created_by' => Auth::userId(), 'created_at' => $now,
                    'updated_by' => Auth::userId(), 'updated_at' => $now,
                ]);
            }
            Audit::updated('role_prog_det', "rpd_role=$role_code", ['perms' => self::json($old)], ['perms' => self::json($new)]);
        });
        return null;
    }

    private static function notAdmin(string $role_code): void
    {
        if (strtoupper($role_code) === Auth::ADMIN) {
            throw new HttpException(422, t('system.admin_locked'));
        }
    }

    private static function json(array $perms): string
    {
        return json_encode($perms ?: new \stdClass(), JSON_UNESCAPED_UNICODE);
    }
}
