<?php

declare(strict_types=1);

namespace Phai\Core;

use Phai\Core\Http\Response;

/**
 * Printed pages as real PDF files, made by a Chromium browser on the server (Edge or Chrome)
 * in headless mode — the same engine as the screen, so Thai text comes out right and no
 * PDF library is needed. Any print route gives a PDF with ?pdf=1 (see Router).
 *
 * The browser is config pdf.browser, else the first one found in the usual places.
 * Without one, the print page offers the browser's own "Save as PDF" instead.
 */
final class Pdf
{
    private const SEARCH = [
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/microsoft-edge',
        '/snap/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
        '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
    ];

    private static string|false|null $browser = null;

    public static function available(): bool
    {
        return self::browser() !== null && function_exists('proc_open');
    }

    public static function browser(): ?string
    {
        if (self::$browser === null) {
            self::$browser = false;
            $configured = trim((string) App::config('pdf.browser', ''));
            foreach ($configured !== '' ? [$configured] : self::SEARCH as $path) {
                if (is_file($path)) {
                    self::$browser = $path;
                    break;
                }
            }
        }
        return self::$browser ?: null;
    }

    /** PDF bytes of an HTML page (A4, as styled by its @page / @media print rules). */
    public static function fromHtml(string $html): string
    {
        $browser = self::browser() ?? throw new \RuntimeException('No browser for PDF');
        $dir = PHAI_ROOT . '/storage/tmp/pdf-' . bin2hex(random_bytes(8));
        if (!@mkdir($dir, 0700, true)) {
            throw new \RuntimeException('Cannot write storage/tmp');
        }
        $page = "$dir/page.html";
        $pdf = "$dir/page.pdf";
        // links like /phaierp/css/... and /phaierp/logo resolve against the site
        $origin = preg_replace('#^(https?://[^/]+).*$#', '$1', (string) App::config('app.url', 'http://localhost'));
        file_put_contents($page, preg_replace('/<head>/i', '<head><base href="' . htmlspecialchars($origin . '/') . '">', $html, 1));

        try {
            $process = proc_open([
                $browser, '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-extensions',
                '--no-pdf-header-footer', '--run-all-compositor-stages-before-draw', '--virtual-time-budget=5000',
                '--user-data-dir=' . "$dir/profile", '--print-to-pdf=' . $pdf, 'file:///' . ltrim(str_replace('\\', '/', $page), '/'),
            ], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new \RuntimeException('Cannot start the browser');
            }
            fclose($pipes[0]);
            // some builds hand the job to a background process and return at once: wait for the file
            $deadline = microtime(true) + 30;
            $last = -1;
            while (microtime(true) < $deadline) {
                clearstatcache(true, $pdf);
                $size = is_file($pdf) ? filesize($pdf) : -1;
                if ($size > 0 && $size === $last) {
                    break;
                }
                $last = $size;
                usleep(250000);
            }
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            $bytes = is_file($pdf) ? (string) file_get_contents($pdf) : '';
            if (!str_starts_with($bytes, '%PDF')) {
                throw new \RuntimeException('The browser did not make a PDF');
            }
            return $bytes;
        } finally {
            self::remove($dir);
        }
    }

    /** The page as an inline PDF named after the document; the HTML page itself when no browser is set up. */
    public static function response(string $html, string $name): Response
    {
        if (!self::available()) {
            return Response::html($html);
        }
        $file = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . '.pdf';
        return new Response(self::fromHtml($html), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $file . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    private static function remove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
