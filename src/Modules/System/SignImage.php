<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Http\HttpException;

/**
 * A user's signature (us_mstr.us_sign) and the company stamp (co_mstr.co_stamp_path): transparent PNGs that printed
 * documents put in their signature boxes. The browser trims and scales them first (js/sign-pad.js: 600 × 200 for a
 * signature, 400 × 400 for a stamp), so a photo of a signature on white paper works too. Kept beside the logo in
 * storage/uploads/<database>/signs, never served as a file: pages get them as data URLs.
 */
final class SignImage
{
    public const MAX_BYTES = 307200;

    /** Store a PNG data URL as a user's signature, or the stamp ($userId null). Returns the file name to keep. */
    public static function store(?string $userId, string $dataUrl, string $field = 'image'): string
    {
        $fail = fn (string $key) => HttpException::invalid([$field => t("system.$key")]);
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            throw $fail('sign_type');
        }
        $bytes = base64_decode($m[1], true);
        if ($bytes === false || $bytes === '') {
            throw $fail('sign_type');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw $fail('sign_size');
        }
        // really a PNG of a sensible size, not only a claim to be one
        $info = @getimagesizefromstring($bytes);
        if (!$info || ($info['mime'] ?? '') !== 'image/png' || $info[0] < 16 || $info[1] < 16 || $info[0] > 2000 || $info[1] > 2000) {
            throw $fail('sign_type');
        }
        if (!is_dir(self::dir())) {
            @mkdir(self::dir(), 0775, true);
        }
        $name = self::stem($userId) . '.png';
        if (file_put_contents(self::dir() . "/$name", $bytes, LOCK_EX) === false) {
            throw HttpException::invalid([$field => t('system.logo_write')]);
        }
        return $name;
    }

    public static function remove(?string $userId): void
    {
        @unlink(self::dir() . '/' . self::stem($userId) . '.png');
    }

    /** The user's signature, or the stamp ($userId null), as a data URL; null when there is none. */
    public static function dataUrl(?string $userId): ?string
    {
        $name = $userId === null
            ? (string) (App::db()->value('SELECT co_stamp_path FROM co_mstr WHERE co_id = 1') ?? '')
            : (string) (App::db()->value('SELECT us_sign FROM us_mstr WHERE us_userid = ?', [$userId]) ?? '');
        if ($name !== self::stem($userId) . '.png') {
            return null;
        }
        $bytes = @file_get_contents(self::dir() . "/$name");
        return $bytes ? 'data:image/png;base64,' . base64_encode($bytes) : null;
    }

    /** A file name from the user id that never needs escaping; the stamp is "stamp". */
    private static function stem(?string $userId): string
    {
        return $userId === null ? 'stamp' : 'u' . substr(hash('sha256', $userId), 0, 16);
    }

    private static function dir(): string
    {
        return Logo::dir() . '/signs';
    }
}
