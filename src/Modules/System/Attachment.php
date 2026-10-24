<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;

/**
 * Files attached to a document (att_det): a picture of a receipt, a transfer slip, a PDF. Kept outside public/ in
 * storage/uploads/<database>/attach under a random name, so they are only seen through a route that checks the
 * document's own permission. Pictures arrive already made smaller by the browser (Phai.shrinkImage).
 */
final class Attachment
{
    /** Accepted types => file extension. */
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** Files of a document, oldest first: [{att_id, att_name, att_mime, att_size, created_by, created_at}]. */
    public static function list(string $doc, string $nbr): array
    {
        return App::db()->all(
            'SELECT att_id, att_name, att_mime, att_size, created_by, created_at FROM att_det WHERE att_doc = ? AND att_nbr = ? ORDER BY att_id',
            [$doc, $nbr]
        );
    }

    /** Store a data URL (data:<type>;base64,...) for the document. Returns the new row. */
    public static function store(string $doc, string $nbr, string $name, string $dataUrl): array
    {
        if (!preg_match('#^data:([a-z/+.-]+);base64,(.+)$#s', $dataUrl, $m) || ($bytes = base64_decode($m[2], true)) === false) {
            throw HttpException::invalid(['file' => t('system.attach_bad')]);
        }
        // the type is read from the bytes, never trusted from the browser
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        if (!isset(self::TYPES[$mime])) {
            throw HttpException::invalid(['file' => t('system.attach_type')]);
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw HttpException::invalid(['file' => t('system.attach_size', ['mb' => self::MAX_BYTES / 1024 / 1024])]);
        }
        if (!is_dir(self::dir()) && !@mkdir(self::dir(), 0775, true)) {
            throw HttpException::invalid(['file' => t('system.logo_write')]);
        }
        $file = bin2hex(random_bytes(12)) . '.' . self::TYPES[$mime];
        if (file_put_contents(self::dir() . "/$file", $bytes, LOCK_EX) === false) {
            throw HttpException::invalid(['file' => t('system.logo_write')]);
        }
        $name = mb_substr(trim(basename(str_replace('\\', '/', $name))) ?: $file, 0, 255);
        $db = App::db();
        $id = (int) $db->insert('att_det', [
            'att_doc' => $doc, 'att_nbr' => $nbr, 'att_name' => $name, 'att_file' => $file, 'att_mime' => $mime, 'att_size' => strlen($bytes),
            'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
        ]);
        return $db->one('SELECT att_id, att_name, att_mime, att_size, created_by, created_at FROM att_det WHERE att_id = ?', [$id]);
    }

    /** The row of a file of this document, or 404. */
    public static function find(string $doc, string $nbr, int $id): array
    {
        return App::db()->one('SELECT * FROM att_det WHERE att_id = ? AND att_doc = ? AND att_nbr = ?', [$id, $doc, $nbr])
            ?? throw HttpException::notFound();
    }

    /** Bytes of a stored file. */
    public static function bytes(array $row): string
    {
        $path = self::dir() . '/' . basename((string) $row['att_file']);
        return is_file($path) ? (string) file_get_contents($path) : throw HttpException::notFound();
    }

    public static function remove(array $row): void
    {
        App::db()->run('DELETE FROM att_det WHERE att_id = ?', [$row['att_id']]);
        @unlink(self::dir() . '/' . basename((string) $row['att_file']));
    }

    /** Every file of a document — when the document itself is deleted (a draft, an undone filing, a master record). */
    public static function removeAll(string $doc, string $nbr): void
    {
        foreach (self::list($doc, $nbr) as $row) {
            self::remove(self::find($doc, $nbr, (int) $row['att_id']));
        }
    }

    private static function dir(): string
    {
        return Logo::dir() . '/attach';
    }
}
