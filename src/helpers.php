<?php

declare(strict_types=1);

use Phai\Core\App;
use Phai\Core\Auth\Csrf;
use Phai\Core\Lang;

/** Escape a value for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translate a key such as 'common.save'. */
function t(string $key, array $params = []): string
{
    return Lang::get($key, $params);
}

/** Read a config value such as 'db.host'. */
function config(string $key, mixed $default = null): mixed
{
    return App::config($key, $default);
}

/** Absolute URL path for an app route, e.g. url('/parts'). */
function url(string $path = '/'): string
{
    return App::basePath() . '/' . ltrim($path, '/');
}

/** URL of a file in public/, with a version stamp so browsers reload it after edits. */
function asset(string $path): string
{
    $file = PHAI_ROOT . '/public/' . ltrim($path, '/');
    $v = is_file($file) ? substr(hash_file('sha256', $file), 0, 12) : '0';
    return url($path) . '?v=' . $v;
}

function csrf_token(): string
{
    return Csrf::token();
}
