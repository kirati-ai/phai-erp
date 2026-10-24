<?php

declare(strict_types=1);

namespace Phai\Core\Auth;

use Phai\Core\App;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;

/**
 * Login, logout and permissions.
 * The user row is read again on every request, so a disabled user is out at once.
 */
final class Auth
{
    public const ADMIN = 'ADMIN';
    public const ACTIONS = ['view', 'add', 'edit', 'delete', 'print'];

    /** Failed logins allowed per user (and 4x per IP) in LOCK_MINUTES. */
    private const MAX_FAILS = 5;
    private const LOCK_MINUTES = 15;

    private static array|false|null $user = null;
    private static ?array $perms = null;

    public static function userId(): ?string
    {
        return self::user()['us_userid'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Logged-in user without the password hash, or null. */
    public static function user(): ?array
    {
        if (self::$user === null) {
            self::$user = false;
            $id = $_SESSION['user_id'] ?? null;
            if ($id !== null) {
                $row = App::db()->one(
                    'SELECT u.us_userid, u.us_name, u.us_email, u.us_phone, u.us_mobile, u.us_avatar, u.us_role, u.us_lang, u.us_theme, u.us_pins, r.role_desc
                       FROM us_mstr u LEFT JOIN role_mstr r ON r.role_code = u.us_role
                      WHERE u.us_userid = ? AND u.us_active = 1',
                    [$id]
                );
                $idle = self::idleMinutes();
                if ($row && $idle > 0 && isset($_SESSION['seen']) && time() - (int) $_SESSION['seen'] > $idle * 60) {
                    self::logout(); // nothing done for longer than the company allows (co_idle_min)
                } elseif ($row) {
                    self::$user = $row;
                    $_SESSION['seen'] = time();
                } else {
                    self::logout();
                }
            }
        }
        return self::$user ?: null;
    }

    /** Minutes without use before the session ends (co_mstr.co_idle_min), 0 = never. The page counts the same down. */
    public static function idleMinutes(): int
    {
        static $min = null;
        return $min ??= (int) App::db()->value('SELECT co_idle_min FROM co_mstr WHERE co_id = 1');
    }

    /** Work as this user without a login (command line and sample data). */
    public static function actAs(string $userId): void
    {
        $_SESSION['user_id'] = $userId;
        self::$user = null;
        self::$perms = null;
    }

    /** Undo actAs(): nobody is signed in for the rest of this request. */
    public static function forget(): void
    {
        unset($_SESSION['user_id']);
        self::$user = null;
        self::$perms = null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['us_role'] ?? null) === self::ADMIN;
    }

    /** The active employee (emp_mstr) linked to the signed-in user, or null. */
    public static function employee(): ?array
    {
        $id = self::user()['us_userid'] ?? null;
        return $id === null ? null
            : App::db()->one('SELECT emp_nbr, emp_name, emp_dept, emp_wkctr FROM emp_mstr WHERE emp_userid = ? AND emp_active = 1', [$id]);
    }

    /** Name to put in "requested by", "inspector" and the like: the linked employee's, else the user's. */
    public static function personName(): string
    {
        return (string) (self::employee()['emp_name'] ?? self::user()['us_name'] ?? '');
    }

    /** May the current user do $action on program $prog? ADMIN may do everything. */
    public static function can(string $prog, string $action = 'view'): bool
    {
        if (!self::check() || !in_array($action, self::ACTIONS, true)) {
            return false;
        }
        if (self::isAdmin()) {
            return true;
        }
        return !empty(self::perms()[$prog][$action]);
    }

    /**
     * Checks the password and starts the session. Throws a 422/429 HttpException
     * with a message for the user when it fails.
     */
    public static function attempt(string $login, string $password): array
    {
        $db = App::db();
        $request = Request::current();
        $ip = $request?->ip ?? '';

        $since = date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60);
        $userFails = (int) $db->value(
            'SELECT COUNT(*) FROM lg_hist WHERE lg_ok = 0 AND created_at >= ? AND lg_userid = ?',
            [$since, $login]
        );
        $ipFails = (int) $db->value(
            'SELECT COUNT(*) FROM lg_hist WHERE lg_ok = 0 AND created_at >= ? AND lg_ip = ?',
            [$since, $ip]
        );
        if ($userFails >= self::MAX_FAILS || $ipFails >= self::MAX_FAILS * 4) {
            throw new HttpException(429, t('auth.locked', ['min' => self::LOCK_MINUTES]));
        }

        $row = $db->one(
            'SELECT us_userid, us_password_hash, us_active FROM us_mstr WHERE us_userid = ? OR us_email = ? LIMIT 1',
            [$login, $login]
        );
        // Verify against a dummy hash when the user does not exist, so timing does not reveal it.
        $hash = $row['us_password_hash'] ?? password_hash('-', PASSWORD_DEFAULT);
        $ok = password_verify($password, $hash) && $row && (int) $row['us_active'] === 1;

        $db->insert('lg_hist', [
            'lg_userid' => mb_substr($row['us_userid'] ?? $login, 0, 50),
            'lg_ip'     => $ip,
            'lg_ok'     => $ok ? 1 : 0,
            'lg_agent'  => mb_substr($request?->header('user-agent') ?? '', 0, 255),
        ]);

        if (!$ok) {
            throw new HttpException(422, t('auth.failed'));
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $db->update('us_mstr', ['us_password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['us_userid' => $row['us_userid']]);
        }
        $db->run(
            'UPDATE us_mstr SET us_last_login = NOW(), us_login_count = COALESCE(us_login_count, 0) + 1 WHERE us_userid = ?',
            [$row['us_userid']]
        );

        session_regenerate_id(true);
        $_SESSION = ['user_id' => $row['us_userid'], 'seen' => time()];
        self::$user = null;
        self::$perms = null;
        return self::user();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = false;
        self::$perms = null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** @return array<string, array<string, bool>> prog_code => action => allowed */
    private static function perms(): array
    {
        if (self::$perms === null) {
            self::$perms = [];
            $rows = App::db()->all(
                'SELECT p.* FROM role_prog_det p JOIN role_mstr r ON r.role_code = p.rpd_role
                  WHERE p.rpd_role = ? AND r.role_active = 1',
                [self::user()['us_role'] ?? '']
            );
            foreach ($rows as $r) {
                foreach (self::ACTIONS as $a) {
                    self::$perms[$r['rpd_prog']][$a] = (bool) $r['rpd_can_' . $a];
                }
            }
        }
        return self::$perms;
    }
}
