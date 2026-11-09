<?php

declare(strict_types=1);

namespace Phai\Modules\Esign;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Pdf;
use Phai\Core\PdfKit\PdfException;
use Phai\Core\PdfKit\Reader;
use Phai\Core\PdfKit\Writer;
use Phai\Modules\System\Logo;

/**
 * The files of an envelope (envf_det), kept outside public/ in storage/uploads/<database>/esign under random names.
 *
 * Every upload is made into a clean PDF before anything else sees it, so what is signed is exactly what is shown:
 * a picture becomes one A4 page; a PDF is written again page by page (PdfKit), which leaves behind JavaScript,
 * actions, links, form fields, earlier updates and anything hidden, and draws filled-in form fields into the page.
 * Password-protected PDFs are refused. Both fingerprints are kept: of the file as uploaded, and of the clean PDF.
 */
final class EnvelopeFile
{
    private const TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    public const MAX_BYTES = 15 * 1024 * 1024;
    public const MAX_FILES = 20;
    public const MAX_PAGES = 300;

    /** Store an upload (data URL) as a file of a draft envelope. $kind: M main document, A attachment. */
    public static function store(string $nbr, string $kind, string $name, string $dataUrl): array
    {
        if (!preg_match('#^data:[^;,]*;base64,(.+)$#s', $dataUrl, $m) || ($bytes = base64_decode($m[1], true)) === false || $bytes === '') {
            throw HttpException::invalid(['file' => t('esign.file_bad')]);
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw HttpException::invalid(['file' => t('esign.file_size', ['mb' => self::MAX_BYTES / 1024 / 1024])]);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: '';
        if (!in_array($mime, self::TYPES, true)) {
            throw HttpException::invalid(['file' => t('esign.file_type')]);
        }
        $db = App::db();
        if ((int) $db->value('SELECT COUNT(*) FROM envf_det WHERE envf_nbr = ?', [$nbr]) >= self::MAX_FILES) {
            throw HttpException::invalid(['file' => t('esign.file_many', ['n' => self::MAX_FILES])]);
        }
        $pdf = $mime === 'application/pdf' ? self::clean($bytes) : self::fromImage($bytes, $mime);
        $reader = new Reader($pdf);
        $sizes = array_map(fn ($p) => [round($p['width'], 2), round($p['height'], 2)], $reader->pages());
        if (count($sizes) > self::MAX_PAGES) {
            throw HttpException::invalid(['file' => t('esign.file_pages', ['n' => self::MAX_PAGES])]);
        }
        if (!is_dir(self::dir()) && !@mkdir(self::dir(), 0775, true)) {
            throw HttpException::invalid(['file' => t('system.logo_write')]);
        }
        $file = bin2hex(random_bytes(12)) . '.pdf';
        if (file_put_contents(self::dir() . "/$file", $pdf, LOCK_EX) === false) {
            throw HttpException::invalid(['file' => t('system.logo_write')]);
        }
        $seq = (int) $db->value('SELECT COALESCE(MAX(envf_seq), 0) + 1 FROM envf_det WHERE envf_nbr = ?', [$nbr]);
        $id = (int) $db->insert('envf_det', [
            'envf_nbr' => $nbr, 'envf_seq' => $seq, 'envf_kind' => $kind === 'A' ? 'A' : 'M',
            'envf_name' => mb_substr(trim(basename(str_replace('\\', '/', $name))) ?: $file, 0, 255),
            'envf_src_mime' => $mime, 'envf_src_hash' => hash('sha256', $bytes), 'envf_src_size' => strlen($bytes),
            'envf_file' => $file, 'envf_hash' => hash('sha256', $pdf), 'envf_size' => strlen($pdf),
            'envf_pages' => count($sizes), 'envf_sizes' => json_encode($sizes),
            'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
        ]);
        return self::find($nbr, $id);
    }

    /** The PDF written again from its pages, flattened; a 422 the person understands when it cannot be. */
    public static function clean(string $bytes): string
    {
        try {
            $r = new Reader($bytes);
            $w = new Writer();
            foreach ($r->pages() as $page) {
                $w->addPage($r, $page, ['flatten' => true]);
            }
            return $w->output([], hash('sha256', $bytes));
        } catch (PdfException $e) {
            throw HttpException::invalid(['file' => t($e->getMessage() === 'encrypted' ? 'esign.pdf_locked' : 'esign.pdf_bad')]);
        }
    }

    /** A picture as one A4 page, drawn by the same browser that prints documents. */
    private static function fromImage(string $bytes, string $mime): string
    {
        if (!Pdf::available()) {
            throw HttpException::invalid(['file' => t('esign.image_needs_pdf')]);
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info) {
            throw HttpException::invalid(['file' => t('esign.file_bad')]);
        }
        $landscape = $info[0] > $info[1];
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4 ' . ($landscape ? 'landscape' : 'portrait') . ';margin:10mm}'
            . 'html,body{margin:0;height:100%}div{display:flex;align-items:center;justify-content:center;width:100%;height:' . ($landscape ? '189mm' : '276mm') . '}'
            . 'img{max-width:100%;max-height:100%}</style></head><body><div><img src="data:' . $mime . ';base64,' . base64_encode($bytes) . '"></div></body></html>';
        return self::clean(Pdf::fromHtml($html));
    }

    public static function list(string $nbr): array
    {
        return App::db()->all(
            'SELECT envf_id, envf_seq, envf_kind, envf_name, envf_src_mime, envf_src_hash, envf_src_size, envf_hash, envf_size, envf_pages, envf_sizes
               FROM envf_det WHERE envf_nbr = ? ORDER BY envf_kind = \'A\', envf_seq',
            [$nbr]
        );
    }

    /** Every column of the files, in envelope order: for making the final document, never sent to a page. */
    public static function rows(string $nbr): array
    {
        return App::db()->all('SELECT * FROM envf_det WHERE envf_nbr = ? ORDER BY envf_kind = \'A\', envf_seq', [$nbr]);
    }

    public static function find(string $nbr, int $id): array
    {
        return App::db()->one('SELECT * FROM envf_det WHERE envf_id = ? AND envf_nbr = ?', [$id, $nbr]) ?? throw HttpException::notFound();
    }

    public static function bytes(array $row): string
    {
        return self::read((string) $row['envf_file']);
    }

    public static function read(string $file): string
    {
        $path = self::dir() . '/' . basename($file);
        return is_file($path) ? (string) file_get_contents($path) : throw HttpException::notFound();
    }

    public static function write(string $bytes): string
    {
        if (!is_dir(self::dir()) && !@mkdir(self::dir(), 0775, true)) {
            throw new \RuntimeException('Cannot write the envelope folder');
        }
        $file = bin2hex(random_bytes(12)) . '.pdf';
        if (file_put_contents(self::dir() . "/$file", $bytes, LOCK_EX) === false) {
            throw new \RuntimeException('Cannot write the signed document');
        }
        return $file;
    }

    /** Only while the envelope is a draft: a sent envelope keeps every file. */
    public static function remove(array $row): void
    {
        App::db()->run('DELETE FROM envf_det WHERE envf_id = ?', [$row['envf_id']]);
        @unlink(self::dir() . '/' . basename((string) $row['envf_file']));
    }

    private static function dir(): string
    {
        return Logo::dir() . '/esign';
    }
}
