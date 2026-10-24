<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Http\HttpException;

/**
 * The company logo: a PNG, JPEG or WebP image up to 1 MB, kept in storage/uploads/<database> (not web
 * accessible, and apart per installation so a test database never touches the real one) and served by
 * LogoController at /logo. Comes in as a data URL from the browser.
 * SVG is not accepted: it can carry scripts.
 */
final class Logo
{
    public const MAX_BYTES = 1048576;
    private const TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    /**
     * Check a data URL and write it as storage/uploads/<database>/logo.<ext>. Returns the file name.
     * $db: the database being installed (before config.php names it).
     * @throws HttpException 422 on $field when it is not a usable image
     */
    public static function store(string $dataUrl, string $field = 'logo', ?string $db = null): string
    {
        [$bytes, $ext] = self::decode($dataUrl, $field);
        self::removeFiles($db);
        $name = "logo.$ext";
        if (!is_dir(self::dir($db))) {
            @mkdir(self::dir($db), 0775, true);
        }
        if (file_put_contents(self::dir($db) . "/$name", $bytes, LOCK_EX) === false) {
            throw HttpException::invalid([$field => t('system.logo_write')]);
        }
        return $name;
    }

    /** @return array{0: string, 1: string} image bytes and file extension */
    public static function decode(string $dataUrl, string $field = 'logo'): array
    {
        $fail = fn (string $key) => HttpException::invalid([$field => t("system.$key")]);
        if (!preg_match('#^data:(image/(?:png|jpeg|webp));base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            throw $fail('logo_type');
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || $bytes === '') {
            throw $fail('logo_type');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw $fail('logo_size');
        }
        // The content must really be the declared image type, not just claim to be.
        $info = @getimagesizefromstring($bytes);
        if (!$info || ($info['mime'] ?? '') !== $m[1] || $info[0] < 16 || $info[1] < 16 || $info[0] > 4000 || $info[1] > 4000) {
            throw $fail('logo_type');
        }
        return [$bytes, self::TYPES[$m[1]]];
    }

    /** Full path of the current logo, or null. */
    public static function file(): ?string
    {
        $name = (string) (App::db()->value('SELECT co_logo_path FROM co_mstr WHERE co_id = 1') ?? '');
        if (!preg_match('/^logo\.(png|jpg|webp)$/', $name)) {
            return null;
        }
        $path = self::dir() . "/$name";
        // a logo saved before logos were kept per database moves into this database's folder
        $old = PHAI_ROOT . "/storage/uploads/$name";
        if (!is_file($path) && is_file($old) && (is_dir(self::dir()) || @mkdir(self::dir(), 0775, true))) {
            @rename($old, $path);
        }
        return is_file($path) ? $path : null;
    }

    /** URL for <img src>, changing whenever the logo does; null when there is none. */
    public static function url(): ?string
    {
        $file = self::file();
        return $file ? url('/logo') . '?v=' . filemtime($file) : null;
    }

    /** Embed the stored image so printed PDFs do not depend on an HTTP image request. */
    public static function printSource(): ?string
    {
        $file = self::file();
        if ($file === null) return null;
        $bytes = file_get_contents($file);
        return $bytes === false ? null : 'data:' . self::mime($file) . ';base64,' . base64_encode($bytes);
    }

    public static function mime(string $file): string
    {
        return array_search(pathinfo($file, PATHINFO_EXTENSION), self::TYPES, true) ?: 'application/octet-stream';
    }

    public static function removeFiles(?string $db = null): void
    {
        foreach (glob(self::dir($db) . '/logo.*') ?: [] as $old) {
            @unlink($old);
        }
    }

    /** storage/uploads/<database> */
    public static function dir(?string $db = null): string
    {
        return PHAI_ROOT . '/storage/uploads/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $db ?? (string) App::config('db.name', 'phaierp'));
    }
}
