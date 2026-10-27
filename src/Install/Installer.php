<?php

declare(strict_types=1);

namespace Phai\Install;

use PDO;
use Phai\Core\App;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Modules\System\Address;
use Phai\Modules\System\Logo;

/** Creates the database, loads schema and seeds, adds the first admin and writes config.php. */
final class Installer
{
    public const MIN_PHP = '8.2.0';

    /** @return list<array{name: string, ok: bool, required: bool}> */
    public static function requirements(): array
    {
        return [
            ['name' => 'PHP ' . PHP_VERSION . ' (≥ 8.2)', 'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='), 'required' => true],
            ['name' => 'pdo_mysql', 'ok' => extension_loaded('pdo_mysql'), 'required' => true],
            ['name' => 'mbstring', 'ok' => extension_loaded('mbstring'), 'required' => true],
            ['name' => 'curl (AI)', 'ok' => extension_loaded('curl'), 'required' => false],
            ['name' => 'Edge / Chrome (PDF)', 'ok' => \Phai\Core\Pdf::available(), 'required' => false],
            ['name' => 'storage/ ' . t('install.writable'), 'ok' => is_writable(PHAI_ROOT . '/storage/logs'), 'required' => true],
            ['name' => 'config.php ' . t('install.writable'), 'ok' => is_writable(PHAI_ROOT), 'required' => false],
        ];
    }

    /**
     * Database fields the install page starts with. install.defaults.php (written by the Windows
     * setup, DesktopPhaiERP, with the port it gave MySQL) overrides them. A password it may hold
     * (Phai Server writes one) is read by presetPassword() only, so it never reaches the page.
     *
     * @return array{db_host: string, db_port: int, db_name: string, db_user: string}
     */
    public static function defaults(?string $file = null): array
    {
        $defaults = ['db_host' => '127.0.0.1', 'db_port' => 3306, 'db_name' => 'phaierp', 'db_user' => 'root'];
        $file ??= PHAI_ROOT . '/install.defaults.php';
        $given = is_file($file) ? require $file : null;
        if (is_array($given)) {
            foreach ($defaults as $key => $value) {
                if (isset($given[$key]) && is_scalar($given[$key])) {
                    $defaults[$key] = is_int($value) ? (int) $given[$key] : (string) $given[$key];
                }
            }
        }
        return $defaults;
    }

    /**
     * The database password a server appliance (Phai Server) prepared, so the person installing
     * never has to know it. It stays on the server: the page only learns that one exists.
     */
    public static function presetPassword(?string $file = null): string
    {
        $file ??= PHAI_ROOT . '/install.defaults.php';
        $given = is_file($file) ? require $file : null;
        return is_array($given) && is_string($given['db_pass'] ?? null) ? $given['db_pass'] : '';
    }

    /** An empty password means "the prepared one", but only for the prepared server, database and user. */
    public static function usePresetPassword(InstallInput $in, ?string $file = null): void
    {
        $preset = self::presetPassword($file);
        $d = self::defaults($file);
        if ($in->db_pass === '' && $preset !== '' && $in->db_host === $d['db_host']
            && $in->db_port === $d['db_port'] && $in->db_name === $d['db_name'] && $in->db_user === $d['db_user']) {
            $in->db_pass = $preset;
        }
    }

    public static function requirementsMet(): bool
    {
        foreach (self::requirements() as $r) {
            if ($r['required'] && !$r['ok']) {
                return false;
            }
        }
        return true;
    }

    /** Connect to the server (not the database). Wrong host or password becomes a field error. */
    public static function connect(InstallInput $in): PDO
    {
        try {
            return new PDO(
                sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $in->db_host, $in->db_port),
                $in->db_user,
                $in->db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (\PDOException $e) {
            $field = in_array($e->errorInfo[1] ?? 0, [1045, 1044], true) ? 'db_pass' : 'db_host';
            throw HttpException::invalid([$field => t('install.connect_failed') . ': ' . $e->getMessage()]);
        }
    }

    /** Number of tables in the database, or null when it does not exist. */
    public static function tableCount(PDO $pdo, string $name): ?int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
        $stmt->execute([$name]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{config_written: bool, config: ?string} */
    public static function install(InstallInput $in, string $appUrl): array
    {
        if (!self::requirementsMet()) {
            throw new HttpException(422, t('install.requirements_failed'));
        }
        @set_time_limit(300);

        $pdo = self::connect($in);
        $tables = self::tableCount($pdo, $in->db_name);
        if ($tables > 0) {
            throw HttpException::invalid(['db_name' => t('install.db_not_empty')]);
        }
        $name = Db::quote($in->db_name);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE $name");
        $pdo->exec("SET time_zone = '" . date('P') . "'");

        try {
            $files = [PHAI_ROOT . '/database/schema/phaierp_schema.sql', ...glob(PHAI_ROOT . '/database/seeds/*.sql')];
            foreach ($files as $file) {
                foreach (self::statements((string) file_get_contents($file)) as $sql) {
                    $pdo->exec($sql);
                }
            }

            $logo = $in->co_logo !== null ? Logo::store($in->co_logo, 'co_logo', $in->db_name) : null;
            $address = ['ad_line1' => $in->ad_line1, 'ad_sub_district' => $in->ad_sub_district, 'ad_city' => $in->ad_city,
                'ad_state' => $in->ad_state, 'ad_zip' => $in->ad_zip];
            $pdo->prepare('UPDATE co_mstr SET co_name = ?, co_tax_id = ?, co_phone = ?, co_address = ?, co_logo_path = ? WHERE co_id = 1')
                ->execute([$in->co_name, $in->co_tax_id, $in->co_phone, Address::format($address) ?: null, $logo]);
            // the company address, to be completed later on the company screen when it could not be picked here
            $pdo->prepare(
                'INSERT INTO ad_mstr (ad_addr, ad_name, ad_line1, ad_sub_district, ad_city, ad_state, ad_zip, ad_country, ad_phone, ad_tax_id, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([Address::COMPANY, $in->co_name, $in->ad_line1, $in->ad_sub_district, $in->ad_city, $in->ad_state, $in->ad_zip, 'ไทย',
                $in->co_phone, $in->co_tax_id, 'install', 'install']);
            $pdo->prepare(
                'INSERT INTO us_mstr (us_userid, us_name, us_email, us_password_hash, us_role, us_active, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, 1, ?, ?)'
            )->execute([
                $in->us_userid, $in->us_name, strtolower((string) $in->us_email),
                $in->password_hash ?? password_hash((string) $in->password, PASSWORD_DEFAULT), 'ADMIN', 'install', 'install',
            ]);
        } catch (\Throwable $e) {
            // Leave the database (and uploads) as we found them, so the user can fix the problem and try again.
            Logo::removeFiles($in->db_name);
            if ($tables === null) {
                $pdo->exec("DROP DATABASE $name");
            } else {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                $stmt = $pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
                $stmt->execute([$in->db_name]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
                    $pdo->exec('DROP TABLE ' . Db::quote($table));
                }
            }
            throw $e;
        }

        $config = self::configFile($in, $appUrl);
        $written = is_writable(PHAI_ROOT) && file_put_contents(PHAI_ROOT . '/config.php', $config, LOCK_EX) !== false;
        $result = ['config_written' => $written, 'config' => $written ? null : $config];

        // Sample data needs the running app, so only when config.php is in place (else: php bin/phai demo).
        if ($in->demo) {
            try {
                if (!$written) {
                    throw new \RuntimeException(t('install.demo_later'));
                }
                App::boot(require PHAI_ROOT . '/config.php');
                Demo::load();
            } catch (\Throwable $e) {
                $result['demo_error'] = t('install.demo_failed', ['error' => $e->getMessage()]);
            }
        }
        return $result;
    }

    /** Split an SQL file into statements on ; outside quotes; drops -- comment lines. */
    public static function statements(string $sql): array
    {
        $out = [];
        $buf = '';
        $quote = null;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($quote !== null) {
                $buf .= $c;
                if ($c === '\\' && $quote !== '`' && $i + 1 < $len) {
                    $buf .= $sql[++$i];
                } elseif ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === '-' && ($sql[$i + 1] ?? '') === '-' && ($i === 0 || $sql[$i - 1] === "\n")) {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $len : $end;
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $quote = $c;
            } elseif ($c === ';') {
                if (trim($buf) !== '') {
                    $out[] = trim($buf);
                }
                $buf = '';
                continue;
            }
            $buf .= $c;
        }
        if (trim($buf) !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    private static function configFile(InstallInput $in, string $appUrl): string
    {
        $v = fn (mixed $x) => var_export($x, true);
        return <<<PHP
<?php

// Written by the PhaiERP installer. Keep this file out of git.

return [
    'app' => [
        'name'     => 'PhaiERP',
        'url'      => {$v($appUrl)},
        'debug'    => false,
        'timezone' => 'Asia/Bangkok',
        'locale'   => 'th',
    ],

    'db' => [
        'host'    => {$v($in->db_host)},
        'port'    => {$v($in->db_port)},
        'name'    => {$v($in->db_name)},
        'user'    => {$v($in->db_user)},
        'pass'    => {$v($in->db_pass)},
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'phaierp',
        'lifetime' => 480, // นาที
    ],

    'ai' => [
        'enabled'  => false,
        'provider' => 'claude', // claude | gemini
        'api_key'  => '',
        'model'    => '', // empty = default of the provider
        'price_in'  => 0, // USD per million input tokens, for the usage log
        'price_out' => 0, // USD per million output tokens
    ],

    'pdf' => [
        'browser' => '', // Edge / Chrome used to make PDF files; empty = found automatically
    ],
];

PHP;
    }
}
