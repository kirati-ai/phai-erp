<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Http\HttpException;

/**
 * A user's own picture (us_mstr.us_avatar): PNG, JPEG or WebP up to 1 MB, checked like the company logo (Logo::decode),
 * kept beside it in storage/uploads/<database>/avatars and served by AuthController at /avatar/{userid}.
 */
final class Avatar
{
    /** Store a data URL as the user's picture. Returns the file name kept in us_avatar. */
    public static function store(string $userId, string $dataUrl): string
    {
        [$bytes, $ext] = Logo::decode($dataUrl, 'avatar');
        self::remove($userId);
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0775, true);
        }
        $name = self::stem($userId) . ".$ext";
        if (file_put_contents(self::dir() . "/$name", $bytes, LOCK_EX) === false) {
            throw HttpException::invalid(['avatar' => t('system.logo_write')]);
        }
        return $name;
    }

    public static function remove(string $userId): void
    {
        foreach (glob(self::dir() . '/' . self::stem($userId) . '.*') ?: [] as $old) {
            @unlink($old);
        }
    }

    /** Full path of the user's picture, or null. */
    public static function file(string $userId): ?string
    {
        $name = (string) (App::db()->value('SELECT us_avatar FROM us_mstr WHERE us_userid = ?', [$userId]) ?? '');
        if (!preg_match('/^[a-f0-9]{16}\.(png|jpg|webp)$/', $name)) {
            return null;
        }
        $path = self::dir() . "/$name";
        return is_file($path) ? $path : null;
    }

    /** URL for <img src> from the user row (us_userid, us_avatar), changing whenever the picture does; null when none. */
    public static function url(array $user): ?string
    {
        $name = (string) ($user['us_avatar'] ?? '');
        if ($name === '') {
            return null;
        }
        $path = self::dir() . "/$name";
        return is_file($path) ? url('/avatar/' . rawurlencode((string) $user['us_userid'])) . '?v=' . filemtime($path) : null;
    }

    /** The first letter of each of the first two words of a name, for a user without a picture. */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $out = '';
        foreach (array_slice(array_filter($words), 0, 2) as $w) {
            $out .= mb_substr($w, 0, 1);
        }
        return $out !== '' ? $out : '?';
    }

    /** A file name from the user id that never needs escaping. */
    private static function stem(string $userId): string
    {
        return substr(hash('sha256', $userId), 0, 16);
    }

    private static function dir(): string
    {
        return Logo::dir() . '/avatars';
    }
}
