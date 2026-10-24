<?php

declare(strict_types=1);

namespace Phai\Core\Auth;

use Phai\Core\Http\Request;

/** One token per session. JavaScript sends it as X-CSRF-Token, plain forms as _csrf. */
final class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function check(Request $request): bool
    {
        $sent = $request->header('x-csrf-token') ?? $request->body['_csrf'] ?? '';
        return is_string($sent) && $sent !== '' && hash_equals(self::token(), $sent);
    }
}
