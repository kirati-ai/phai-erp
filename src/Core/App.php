<?php

declare(strict_types=1);

namespace Phai\Core;

use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Csrf;
use Phai\Core\Db\Db;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Routing\Router;
use Phai\Install\InstallController;

final class App
{
    public const VERSION = '1.0.0';

    private static array $config = [];
    private static bool $installed = false;
    private static string $basePath = '';
    private static ?Db $db = null;

    /** @param array|null $config contents of config.php, or null when not installed yet */
    public static function boot(?array $config): void
    {
        self::$installed = $config !== null;
        self::$config = $config ?? [];
        date_default_timezone_set(self::config('app.timezone', 'Asia/Bangkok'));
        Lang::setLocale(self::config('app.locale', 'th'));
        ErrorHandler::register((bool) self::config('app.debug', false));
    }

    public static function run(): void
    {
        $request = Request::fromGlobals();
        self::$basePath = $request->basePath;

        self::startSession();

        if ($request->isWrite() && !Csrf::check($request)) {
            Response::error(403, t('error.csrf'), $request)->send();
            return;
        }

        $router = new Router();
        if (!self::$installed) {
            if (!str_starts_with($request->path, '/install') && !str_starts_with($request->path, '/api/install')) {
                Response::redirect(url('/install'))->send();
                return;
            }
            $router->addController(InstallController::class);
        } else {
            // the user's language (us_lang); before signing in, the one picked on the login page (cookie phai_lang)
            if ($lang = Lang::pick(Auth::user()['us_lang'] ?? null) ?? Lang::pick($_COOKIE['phai_lang'] ?? null)) {
                Lang::setLocale($lang);
            }
            $router->load(PHAI_ROOT . '/src/Modules');
        }
        $router->dispatch($request)->send();
    }

    public static function installed(): bool
    {
        return self::$installed;
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** The whole config.php array (for screens that save settings back to it). */
    public static function configAll(): array
    {
        return self::$config;
    }

    /** Write config.php and use the new values for the rest of this request. */
    public static function saveConfig(array $config): void
    {
        $file = PHAI_ROOT . '/config.php';
        $php = "<?php\n\n// PhaiERP settings. Keep this file out of git.\n\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents($file, $php, LOCK_EX) === false) {
            throw new Http\HttpException(409, t('error.config_write'));
        }
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file, true);
        }
        self::$config = $config;
    }

    public static function basePath(): string
    {
        return self::$basePath;
    }

    public static function db(): Db
    {
        return self::$db ??= new Db(self::config('db'));
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(self::config('session.name', 'phaierp'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::$basePath . '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        ini_set('session.gc_maxlifetime', (string) (self::config('session.lifetime', 480) * 60));
        ini_set('session.use_strict_mode', '1');
        session_start();
    }
}
