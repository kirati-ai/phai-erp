<?php

declare(strict_types=1);

namespace Phai\Modules\Esign;

use Phai\Core\App;
use Phai\Core\Http\HttpException;
use Phai\Core\Pdf;
use Phai\Core\PdfKit\PdfException;
use Phai\Core\PdfKit\Reader;
use Phai\Core\PdfKit\Writer;
use Phai\Core\Qr;
use Phai\Core\View\View;

/**
 * The final document of an envelope, and checking a file against it.
 *
 * Final document: the main documents with every field stamped on, then the attachments, then the evidence pages
 * (views/esign/evidence.php). Every page has the envelope footer (number, page x / total, which file and its page,
 * a short code of the content, a QR code to the check page); the pages' own content is shrunk a little to make
 * room for it, so it never covers anything. The browser that prints documents (Pdf: Edge/Chrome, phai-pdf on Phai
 * Server) draws the fields and footers as an overlay, one A4 sheet per page with the page drawn scaled at its top
 * left, which PdfKit puts over each page as a form XObject. So Thai text is shaped by a real browser, and the
 * original pages stay as they were, vectors and text and all.
 *
 * The evidence pages list each page's fingerprint (Reader::fingerprint) of the document and attachment pages; these
 * are kept with the envelope (env_page_hash) with the SHA-256 of the whole file, so a changed copy shows which page.
 */
final class EnvelopePdf
{
    /** The page's own content is shrunk to this, leaving a strip at the bottom for the footer. */
    public const SHRINK = 0.96;
    /** Overlay sheets are A4 portrait, in points; a page is drawn on one at its top left, scaled to fit. */
    private const SHEET_W = 595.0;
    private const SHEET_H = 841.0;

    /** @return array{bytes: string, pages: int, fingerprints: string[], at: string} */
    public static function build(string $nbr): array
    {
        if (!Pdf::available()) {
            throw new HttpException(500, t('esign.no_pdf_engine'));
        }
        $env = Envelope::row($nbr);
        $at = date('Y-m-d H:i:s');
        $files = EnvelopeFile::rows($nbr);
        $parts = []; // one per page of the document and attachments: [reader, page, file row, page in file, page in main documents|null]
        $mainNo = 0;
        foreach ($files as $f) {
            $r = new Reader(EnvelopeFile::bytes($f));
            foreach ($r->pages() as $i => $p) {
                $parts[] = ['r' => $r, 'p' => $p, 'f' => $f, 'n' => $i + 1, 'main' => $f['envf_kind'] === 'M' ? ++$mainNo : null];
            }
        }
        $docPages = count($parts);
        $signers = Envelope::signers($nbr);
        $fields = Envelope::fields($nbr);
        $content = Envelope::contentHash($nbr);

        // how many evidence pages: render once with stand-in fingerprints of the same length
        $dummy = array_fill(0, $docPages, str_repeat('0', 64));
        $evidenceCount = count((new Reader(self::evidence($env, $files, $signers, $parts, $dummy, 0, $content, $at)))->pages());
        $total = $docPages + $evidenceCount;
        $overlay = new Reader(Pdf::fromHtml(self::overlay($env, $parts, $evidenceCount, $fields, $signers, $content)));
        if (count($overlay->pages()) !== $total) {
            throw new \RuntimeException('Overlay pages do not match the document');
        }

        // fingerprints of the stamped document pages, for the evidence pages
        $w = new Writer();
        self::addDocumentPages($w, $parts, $overlay);
        $draft = new Reader($w->output());
        $fingerprints = array_map(fn ($p) => $draft->fingerprint($p), $draft->pages());

        $evidence = new Reader(self::evidence($env, $files, $signers, $parts, $fingerprints, $evidenceCount, $content, $at));
        if (count($evidence->pages()) !== $evidenceCount) {
            throw new \RuntimeException('Evidence pages changed length');
        }
        $w = new Writer();
        self::addDocumentPages($w, $parts, $overlay);
        foreach ($evidence->pages() as $i => $p) {
            $o = $overlay->pages()[$docPages + $i];
            $form = $w->form($overlay, $o);
            $w->addPage($evidence, $p, ['flatten' => false, 'ops' => Writer::place($p, $o, self::scale($p), 'PhaiOv'), 'xobjects' => ['PhaiOv' => $form]]);
        }
        $bytes = $w->output([
            'Title' => $env['env_title'], 'Subject' => $nbr, 'Keywords' => "PhaiEnvelope:$nbr", 'PhaiEnvelope' => $nbr,
        ], $nbr . $content);
        $final = new Reader($bytes);
        $all = array_map(fn ($p) => $final->fingerprint($p), $final->pages());
        return ['bytes' => $bytes, 'pages' => $total, 'fingerprints' => $all, 'at' => $at];
    }

    /**
     * The main documents as an outsider is shown them at Phai Sign: what the sender put on and what signers before
     * them filled in, stamped on; no footer, nothing shrunk, so their own fields (sent beside it) sit where the
     * sender placed them. Fields of people who have not signed yet stay off.
     */
    public static function snapshot(string $nbr, int $forSigner): string
    {
        $parts = [];
        foreach (EnvelopeFile::rows($nbr) as $f) {
            if ($f['envf_kind'] !== 'M') {
                continue;
            }
            $r = new Reader(EnvelopeFile::bytes($f));
            foreach ($r->pages() as $p) {
                $parts[] = ['r' => $r, 'p' => $p];
            }
        }
        $signers = array_column(Envelope::signers($nbr), null, 'envs_id');
        $byPage = [];
        foreach (Envelope::fields($nbr) as $x) {
            $s = $x['envx_signer'] !== null ? ($signers[$x['envx_signer']] ?? null) : null;
            if ($x['envx_signer'] === $forSigner || ($x['envx_signer'] !== null && ($s['envs_status'] ?? '') !== 'S')) {
                continue;
            }
            $byPage[(int) $x['envx_page']][] = self::fieldOut($x, $signers);
        }
        $w = new Writer();
        if (!$byPage) {
            foreach ($parts as $part) {
                $w->addPage($part['r'], $part['p'], ['flatten' => false]);
            }
            return $w->output([], $nbr . 'snapshot');
        }
        if (!Pdf::available()) {
            throw new HttpException(500, t('esign.no_pdf_engine'));
        }
        $sheets = [];
        foreach ($parts as $i => $part) {
            $sheets[] = ['w' => $part['p']['width'], 'h' => $part['p']['height'], 'k' => self::scale($part['p']), 'shrink' => 1.0, 'fields' => $byPage[$i + 1] ?? [], 'foot' => null];
        }
        $overlay = new Reader(Pdf::fromHtml(View::partial('esign/overlay', ['sheets' => $sheets, 'nbr' => $nbr, 'total' => count($sheets), 'code' => '', 'qr' => ''])));
        if (count($overlay->pages()) !== count($parts)) {
            throw new \RuntimeException('Overlay pages do not match the document');
        }
        foreach ($parts as $i => $part) {
            $o = $overlay->pages()[$i];
            $form = $w->form($overlay, $o);
            $w->addPage($part['r'], $part['p'], ['flatten' => false, 'ops' => Writer::place($part['p'], $o, self::scale($part['p']), 'PhaiOv'), 'xobjects' => ['PhaiOv' => $form]]);
        }
        return $w->output([], $nbr . 'snapshot');
    }

    private static function addDocumentPages(Writer $w, array $parts, Reader $overlay): void
    {
        foreach ($parts as $i => $part) {
            $o = $overlay->pages()[$i];
            $form = $w->form($overlay, $o);
            $w->addPage($part['r'], $part['p'], [
                'flatten' => false, 'pre' => Writer::shrink($part['p'], self::SHRINK),
                'ops' => Writer::place($part['p'], $o, self::scale($part['p']), 'PhaiOv'), 'xobjects' => ['PhaiOv' => $form],
            ]);
        }
    }

    /** How much a page is scaled to fit an overlay sheet. */
    private static function scale(array $p): float
    {
        return min(self::SHEET_W / $p['width'], self::SHEET_H / $p['height']);
    }

    /** The overlay: one sheet per final page, with the fields of the main documents and every page's footer. */
    private static function overlay(array $env, array $parts, int $evidenceCount, array $fields, array $signers, string $content): string
    {
        $byId = array_column($signers, null, 'envs_id');
        $total = count($parts) + $evidenceCount;
        $byPage = [];
        foreach ($fields as $f) {
            $byPage[(int) $f['envx_page']][] = $f;
        }
        $attachNo = [];
        $n = 0;
        foreach ($parts as $p) {
            if ($p['main'] === null && !isset($attachNo[$p['f']['envf_id']])) {
                $attachNo[$p['f']['envf_id']] = ++$n;
            }
        }
        $sheets = [];
        foreach ($parts as $i => $p) {
            $f = $p['f'];
            $where = $p['main'] !== null
                ? t('esign.foot_main', ['n' => $p['n'], 'of' => $f['envf_pages']])
                : t('esign.foot_attach', ['k' => $attachNo[$f['envf_id']], 'n' => $p['n'], 'of' => $f['envf_pages']]);
            $sheets[] = [
                'w' => $p['p']['width'], 'h' => $p['p']['height'], 'k' => self::scale($p['p']), 'shrink' => self::SHRINK,
                'fields' => $p['main'] !== null ? array_map(fn ($x) => self::fieldOut($x, $byId), $byPage[$p['main']] ?? []) : [],
                'foot' => ['page' => $i + 1, 'where' => $where],
            ];
        }
        for ($e = 1; $e <= $evidenceCount; $e++) {
            $sheets[] = [
                'w' => 595.28, 'h' => 841.89, 'k' => self::SHEET_W / 595.28, 'shrink' => 1.0, 'fields' => [],
                'foot' => ['page' => count($parts) + $e, 'where' => t('esign.foot_evidence', ['n' => $e, 'of' => $evidenceCount])],
            ];
        }
        return View::partial('esign/overlay', [
            'sheets' => $sheets, 'nbr' => $env['env_nbr'], 'total' => $total, 'code' => substr($content, 0, 8),
            'qr' => 'data:image/svg+xml;base64,' . base64_encode(Qr::svg(self::verifyUrl($env['env_nbr']))),
        ]);
    }

    /** A field as the overlay draws it: what it shows, from the signer and the values filled in. */
    private static function fieldOut(array $f, array $byId): array
    {
        $s = $f['envx_signer'] !== null ? ($byId[$f['envx_signer']] ?? null) : null;
        $out = ['type' => $f['envx_type'], 'x' => $f['envx_x'], 'y' => $f['envx_y'], 'w' => $f['envx_w'], 'h' => $f['envx_h'], 'props' => $f['envx_props']];
        $date = fn (?string $at) => $at ? Envelope::when($at, false) : '';
        switch ($f['envx_type']) {
            case 'signature':
            case 'initials':
                $out['image'] = $s['envs_image'] ?? null;
                if ($f['envx_type'] === 'signature' && $s) {
                    $out['caption'] = trim($s['envs_name'] . ($s['envs_position'] ? ' · ' . $s['envs_position'] : ''));
                    $out['times'] = self::times($s);
                }
                break;
            case 'name':
                $out['text'] = $s['envs_name'] ?? '';
                break;
            case 'position':
                $out['text'] = $s['envs_position'] ?? '';
                break;
            case 'date':
                $out['text'] = $s && $s['envs_sign_at'] ? self::day($s['envs_sign_at']) : '';
                break;
            case 'timeline':
                $out['text'] = $s ? self::times($s) : '';
                break;
            case 'stamp':
                $out['image'] = \Phai\Modules\System\SignImage::dataUrl(null);
                break;
            case 'text':
                $out['text'] = (string) $f['envx_value'];
                break;
            case 'checkbox':
                $out['checked'] = $f['envx_value'] === '1';
                break;
            default: // label, mark: the sender's own text
                $out['text'] = (string) ($f['envx_props']['text'] ?? '');
        }
        return $out;
    }

    /** รับ 9 พ.ย. 2569 09:02 · เปิด 09:40 · เซ็น 09:41: later times on the same day without the date. */
    public static function times(array $s): string
    {
        $parts = [];
        $day = null;
        foreach (['recv' => 'envs_recv_at', 'open' => 'envs_open_at', 'sign' => 'envs_sign_at'] as $k => $col) {
            if (!$s[$col]) {
                continue;
            }
            $d = substr($s[$col], 0, 10);
            $parts[] = t("esign.t_$k") . ' ' . ($d === $day ? substr($s[$col], 11, 5) : Envelope::when($s[$col], false));
            $day = $d;
        }
        return implode(' · ', $parts);
    }

    private static function day(string $at): string
    {
        return trim(preg_replace('/\s+\d{2}:\d{2}(:\d{2})?$/', '', Envelope::when($at, false)));
    }

    /** The evidence pages (A4) as a PDF. */
    private static function evidence(array $env, array $files, array $signers, array $parts, array $fingerprints, int $pages, string $content, string $at): string
    {
        $docPages = count($parts);
        $ranges = [];
        $pos = 1;
        $attach = 0;
        foreach ($files as $f) {
            $ranges[] = [
                'name' => $f['envf_name'], 'kind' => $f['envf_kind'], 'k' => $f['envf_kind'] === 'A' ? ++$attach : null,
                'pages' => (int) $f['envf_pages'], 'from' => $pos, 'to' => $pos + (int) $f['envf_pages'] - 1,
                'src' => $f['envf_src_hash'], 'clean' => $f['envf_hash'], 'size' => (int) $f['envf_src_size'],
            ];
            $pos += (int) $f['envf_pages'];
        }
        $html = View::partial('esign/evidence', [
            'env' => $env, 'ranges' => $ranges, 'docPages' => $docPages, 'evidencePages' => $pages, 'total' => $docPages + $pages,
            'signers' => array_values(array_filter($signers, fn ($s) => $s['envs_status'] !== 'C' || $s['envs_sign_at'])),
            'events' => EnvelopeLog::all($env['env_nbr']), 'chain' => (string) App::db()->value('SELECT env_chain FROM env_mstr WHERE env_nbr = ?', [$env['env_nbr']]),
            'fingerprints' => array_slice($fingerprints, 0, $docPages), 'content' => $content, 'at' => $at,
            'sender' => (string) (App::db()->value('SELECT us_name FROM us_mstr WHERE us_userid = ?', [$env['created_by']]) ?? $env['created_by']),
            'recipients' => Envelope::recipients($env['env_nbr']), 'verify' => self::verifyUrl($env['env_nbr']),
        ]);
        return Pdf::fromHtml($html);
    }

    public static function verifyUrl(string $nbr): string
    {
        return rtrim((string) App::config('app.url', 'http://localhost'), '/') . '/envelopes/verify?n=' . rawurlencode($nbr);
    }

    // ------------------------------------------------------------------ checking a file

    /**
     * Check a file against what was signed: the whole file's SHA-256 against every final document and every file
     * as uploaded or cleaned; then, for a PDF that names its envelope (or for $nbr), page by page. Returns
     * ['result' => match|original|changed|unknown, 'nbr' => ?, 'pages' => [...differing page numbers], 'count' => [file, signed]].
     */
    public static function verify(string $bytes, string $nbr = ''): array
    {
        $db = App::db();
        $hash = hash('sha256', $bytes);
        $hit = $db->one("SELECT env_nbr FROM env_mstr WHERE env_final_hash = ? AND env_status = 'C'", [$hash]);
        if ($hit) {
            return ['result' => 'match', 'nbr' => $hit['env_nbr'], 'sha256' => $hash];
        }
        $file = $db->one('SELECT envf_nbr, envf_name FROM envf_det WHERE envf_hash = ? OR envf_src_hash = ? LIMIT 1', [$hash, $hash]);
        if ($file) {
            return ['result' => 'original', 'nbr' => $file['envf_nbr'], 'name' => $file['envf_name'], 'sha256' => $hash];
        }
        try {
            $r = new Reader($bytes);
            $pages = $r->pages();
        } catch (PdfException) {
            return ['result' => 'unknown', 'nbr' => null, 'sha256' => $hash];
        }
        if ($nbr === '') {
            $info = $r->resolve($r->trailer->get('Info'));
            $named = $info instanceof \Phai\Core\PdfKit\Dict ? $r->resolve($info->get('PhaiEnvelope')) : null;
            $nbr = $named instanceof \Phai\Core\PdfKit\Str ? $named->v : '';
        }
        $env = $nbr !== '' ? $db->one("SELECT env_nbr, env_page_hash FROM env_mstr WHERE env_nbr = ? AND env_status = 'C'", [$nbr]) : null;
        if (!$env) {
            return ['result' => 'unknown', 'nbr' => null, 'sha256' => $hash];
        }
        $signed = json_decode((string) $env['env_page_hash'], true) ?: [];
        $diff = [];
        foreach ($pages as $i => $p) {
            if (($signed[$i] ?? null) !== $r->fingerprint($p)) {
                $diff[] = $i + 1;
            }
        }
        for ($i = count($pages); $i < count($signed); $i++) {
            $diff[] = $i + 1;
        }
        return [
            'result' => !$diff ? 'same-pages' : 'changed', 'nbr' => $env['env_nbr'], 'sha256' => $hash,
            'pages' => $diff, 'count' => [count($pages), count($signed)],
        ];
    }
}
