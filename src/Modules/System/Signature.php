<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Mail;
use Phai\Core\Routing\Router;
use Phai\Modules\Sales\SalesPrint;

/**
 * Signatures in the signature boxes of printed documents (views/doc/print.php), every document at once: a document is
 * known by its print path (/invoices/IV2026100001/print) and a box by its place in the document's `signs` (0 = left).
 * Boxes for the other side (customer, vendor: SalesPrint::outside()) are left to be signed on paper.
 *
 * - Our plain boxes (who made, sent or received it) anyone who may print the document signs with their own name; the
 *   approval boxes (SalesPrint::approve(): checked, approved, authorized) take a request to a named person, or an
 *   ADMIN signing directly.
 * - A request (R) goes by e-mail when mail is set up, and always to the person's "รอฉันเซ็น" list; the person opens
 *   the document after logging in and signs (S) or rejects it with a reason (X). Email is only the notice.
 * - Signing keeps a copy of the signature image and a fingerprint of what the document said (fingerprint()): a
 *   document changed afterwards prints without that signature until it is signed again.
 * Rows are never deleted: sig_det is the record of who signed what and when (C = withdrawn or replaced).
 */
final class Signature
{
    /** The print path a document is being rendered for, when it is not the request's own (document()). */
    private static ?string $path = null;

    /** What a document says, apart from its signature boxes: signing again is needed when this changes. */
    public static function fingerprint(array $doc): string
    {
        return hash('sha256', json_encode(
            [$doc['title'] ?? '', $doc['nbr'] ?? '', $doc['meta'] ?? [], $doc['party'] ?? null, $doc['lines'] ?? [], $doc['totals'] ?? null, $doc['notes'] ?? ''],
            JSON_UNESCAPED_UNICODE
        ));
    }

    /** The print path of the document being rendered now, or null outside a print route. */
    public static function currentPath(): ?string
    {
        $path = self::$path ?? Request::current()?->path;
        return $path !== null && self::isPrintPath($path) ? $path : null;
    }

    public static function isPrintPath(string $path): bool
    {
        return (bool) preg_match('#^/[a-z0-9-]+(?:/[^/?\#]+)+/print$#', $path) && strlen($path) <= 200;
    }

    /**
     * The document at a print path, rendered through its own route as the user signed in, so the user must be allowed
     * to print it (403 / 404 otherwise). Only documents made with SalesPrint::render() have signature boxes.
     */
    public static function document(string $path): array
    {
        if (!self::isPrintPath($path)) {
            throw HttpException::notFound();
        }
        SalesPrint::$last = null;
        self::$path = $path;
        try {
            $router = new Router();
            $router->load(PHAI_ROOT . '/src/Modules');
            $router->dispatch(new Request('GET', $path, '', ['html' => '1'], [], [], ''));
        } finally {
            self::$path = null;
        }
        return SalesPrint::$last ?? throw HttpException::notFound();
    }

    /**
     * The boxes of a document as print.php draws them: the label, and for our boxes the signature on it now (image,
     * name, date) plus what the user looking may do. $doc['signs']: labels, or SalesPrint::outside() for the other side.
     */
    public static function boxes(array $doc, ?string $path): array
    {
        $rows = $path !== null ? self::latest($path) : [];
        $hash = self::fingerprint($doc);
        $me = Auth::userId();
        $admin = Auth::isAdmin();
        $out = [];
        foreach ($doc['signs'] ?? [] as $i => $s) {
            $label = is_array($s) ? $s['label'] : $s;
            if (is_array($s) && !empty($s['outside'])) {
                $out[] = ['label' => $label, 'outside' => true];
                continue;
            }
            $approve = is_array($s) && !empty($s['approve']);
            $r = $rows[$i] ?? null;
            $signed = $r !== null && $r['sig_status'] === 'S' && $r['sig_hash'] === $hash;
            $box = ['label' => $label, 'slot' => $i, 'outside' => false, 'approve' => $approve, 'signed' => $signed, 'status' => $r['sig_status'] ?? null];
            if ($r !== null) {
                $box += [
                    'userid' => $r['sig_userid'], 'name' => $r['us_name'] ?? $r['sig_userid'], 'note' => $r['sig_note'],
                    'date' => $r['sig_at'] ? date('d/m/Y', strtotime($r['sig_at'])) : '',
                    'image' => $signed ? $r['sig_image'] : null,
                    'stale' => $r['sig_status'] === 'S' && !$signed, // signed, then the document changed
                ];
            }
            if ($path !== null && $me !== null) {
                $askedMe = $r !== null && $r['sig_status'] === 'R' && $r['sig_userid'] === $me;
                $askedOther = $r !== null && $r['sig_status'] === 'R' && !$askedMe;
                $box['can'] = [
                    'sign'     => !$signed && ($askedMe || (!$askedOther && (!$approve || $admin))),
                    'request'  => !$signed,
                    'reject'   => $askedMe,
                    'withdraw' => ($signed || !empty($box['stale'])) && $r['sig_userid'] === $me,
                    'cancel'   => $askedOther && $r['sig_req_by'] === $me,
                ];
            }
            $out[] = $box;
        }
        return $out;
    }

    /** Sign box $slot of the document at $path as the user signed in. */
    public static function sign(string $path, int $slot): array
    {
        [$doc, $box] = self::box($path, $slot);
        if (empty($box['can']['sign'])) {
            throw new HttpException(403, t('sign.not_yours'));
        }
        $me = (string) Auth::userId();
        $image = SignImage::dataUrl($me) ?? throw HttpException::invalid(['sign' => t('sign.no_image')]);
        $db = App::db();
        $db->transaction(function () use ($db, $doc, $path, $slot, $box, $me, $image) {
            $pending = self::pending($path, $slot);
            self::close($path, $slot);
            $db->insert('sig_det', self::row($doc, $path, $slot, $box['label']) + [
                'sig_userid' => $me, 'sig_status' => 'S', 'sig_at' => date('Y-m-d H:i:s'), 'sig_ip' => Request::current()?->ip,
                'sig_hash' => self::fingerprint($doc), 'sig_image' => $image,
                'sig_req_by' => $pending['sig_req_by'] ?? null, 'sig_note' => $pending['sig_note'] ?? null,
            ]);
            if ($pending && $pending['sig_req_by'] && $pending['sig_req_by'] !== $me) {
                self::mail($pending['sig_req_by'], t('sign.mail_signed_subject', ['doc' => self::title($doc)]),
                    t('sign.mail_signed_body', ['name' => Auth::user()['us_name'] ?? $me, 'box' => $box['label'], 'doc' => self::title($doc)]), $path);
            }
        });
        return self::state($path);
    }

    /** Ask $userid to sign box $slot; replaces a request still waiting there. */
    public static function request(string $path, int $slot, string $userid, string $note): array
    {
        [$doc, $box] = self::box($path, $slot);
        if (empty($box['can']['request'])) {
            throw new HttpException(409, t('sign.already_signed'));
        }
        if (!in_array($userid, array_column(self::people($path), 'us_userid'), true)) {
            throw HttpException::invalid(['userid' => t('sign.no_user')]);
        }
        $me = (string) Auth::userId();
        App::db()->transaction(function () use ($doc, $path, $slot, $box, $userid, $note, $me) {
            self::close($path, $slot);
            App::db()->insert('sig_det', self::row($doc, $path, $slot, $box['label']) + [
                'sig_userid' => $userid, 'sig_status' => 'R', 'sig_req_by' => $me, 'sig_note' => $note !== '' ? $note : null,
            ]);
        });
        $id = (int) App::db()->value('SELECT MAX(sig_id) FROM sig_det WHERE sig_path = ? AND sig_slot = ?', [$path, $slot]);
        $mailed = $userid !== $me && self::mail($userid, t('sign.mail_request_subject', ['doc' => self::title($doc)]),
            t('sign.mail_request_body', ['name' => Auth::user()['us_name'] ?? $me, 'box' => $box['label'], 'doc' => self::title($doc)])
            . ($note !== '' ? "\n\n" . $note : ''), '/signatures/' . $id);
        return self::state($path) + ['mailed' => $mailed];
    }

    /** Turn down a request to me, with the reason. */
    public static function reject(string $path, int $slot, string $note): array
    {
        [$doc, $box] = self::box($path, $slot);
        if (empty($box['can']['reject'])) {
            throw new HttpException(403, t('sign.not_yours'));
        }
        if ($note === '') {
            throw HttpException::invalid(['note' => t('sign.reason_needed')]);
        }
        $r = self::pending($path, $slot);
        App::db()->update('sig_det', ['sig_status' => 'X', 'sig_note' => mb_substr($note, 0, 255), 'sig_at' => date('Y-m-d H:i:s'),
            'sig_ip' => Request::current()?->ip, 'updated_by' => Auth::userId()], ['sig_id' => $r['sig_id']]);
        if ($r['sig_req_by']) {
            self::mail($r['sig_req_by'], t('sign.mail_rejected_subject', ['doc' => self::title($doc)]),
                t('sign.mail_rejected_body', ['name' => Auth::user()['us_name'] ?? '', 'box' => $box['label'], 'doc' => self::title($doc)]) . "\n\n" . $note, $path);
        }
        return self::state($path);
    }

    /** Take back my signature, or a request I made that is still waiting. */
    public static function withdraw(string $path, int $slot): array
    {
        [, $box] = self::box($path, $slot);
        if (empty($box['can']['withdraw']) && empty($box['can']['cancel'])) {
            throw new HttpException(403, t('sign.not_yours'));
        }
        self::close($path, $slot);
        return self::state($path);
    }

    /** The boxes of the document at $path, for the page after a change. */
    public static function state(string $path): array
    {
        $doc = self::document($path);
        return ['boxes' => self::boxes($doc, $path), 'stamp' => self::stamp($doc, $path)];
    }

    /**
     * The company stamp beside the boxes: only on documents for the other side (they have a party), and only once one
     * of our boxes is signed, so an unsigned document never carries it.
     */
    public static function stamp(array $doc, ?string $path, ?array $boxes = null): ?array
    {
        if (empty($doc['party'])) {
            return null;
        }
        $boxes ??= self::boxes($doc, $path);
        $signed = (bool) array_filter($boxes, fn ($b) => !empty($b['signed']));
        return [
            'image' => $signed ? SignImage::dataUrl(null) : null,
            'outside' => (bool) array_filter($boxes, fn ($b) => $b['outside']),
            'party' => $doc['party']['label'] ?? '',
        ];
    }

    /** Requests waiting for me, and what I asked of others lately: the "รอเซ็น" page. */
    public static function mine(): array
    {
        $me = Auth::userId();
        $sql = 'SELECT s.sig_id, s.sig_path, s.sig_label, s.sig_title, s.sig_status, s.sig_note, s.sig_at, s.created_at,
                       s.sig_userid, u.us_name AS signer, s.sig_req_by, q.us_name AS asker
                  FROM sig_det s LEFT JOIN us_mstr u ON u.us_userid = s.sig_userid LEFT JOIN us_mstr q ON q.us_userid = s.sig_req_by';
        return [
            'waiting' => App::db()->all("$sql WHERE s.sig_userid = ? AND s.sig_status = 'R' ORDER BY s.sig_id", [$me]),
            'asked'   => App::db()->all("$sql WHERE s.sig_req_by = ? AND s.sig_status IN ('R', 'S', 'X') ORDER BY s.sig_id DESC LIMIT 100", [$me]),
        ];
    }

    /** How many requests wait for me (the badge in the top bar). */
    public static function waitingCount(): int
    {
        $me = Auth::userId();
        return $me === null ? 0 : (int) App::db()->value("SELECT COUNT(*) FROM sig_det WHERE sig_userid = ? AND sig_status = 'R'", [$me]);
    }

    /**
     * People who can be asked to sign the document at $path: active users, other than the one asking, whose role may
     * open it (its print route's program right; ADMIN always). The sample-data user is not active.
     */
    public static function people(string $path): array
    {
        self::document($path); // the asker may open it
        $roles = self::roles($path);
        $in = $roles === null ? '' : ' AND u.us_role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')';
        return App::db()->all(
            "SELECT u.us_userid, u.us_name, r.role_desc, u.us_sign IS NOT NULL AS has_sign
               FROM us_mstr u LEFT JOIN role_mstr r ON r.role_code = u.us_role
              WHERE u.us_active = 1 AND u.us_userid <> ?$in ORDER BY u.us_name",
            [Auth::userId(), ...($roles ?? [])]
        );
    }

    /** The roles that may open the document at $path, or null when any user may. */
    private static function roles(string $path): ?array
    {
        if (preg_match('#^/documents/([a-z-]+)/#', $path, $m)) {
            $right = ['codes' => [$m[1]], 'action' => 'print']; // DocumentPrintController checks Auth::can($kind, 'print') itself
        } else {
            $router = new Router();
            $router->load(PHAI_ROOT . '/src/Modules');
            $right = $router->permission($path) ?? throw HttpException::notFound();
        }
        if (!$right['codes']) {
            return null;
        }
        $action = in_array($right['action'], Auth::ACTIONS, true) ? $right['action'] : 'view';
        $roles = App::db()->all(
            'SELECT DISTINCT p.rpd_role FROM role_prog_det p JOIN role_mstr r ON r.role_code = p.rpd_role AND r.role_active = 1
              WHERE p.rpd_prog IN (' . implode(',', array_fill(0, count($right['codes']), '?')) . ") AND p.rpd_can_$action = 1",
            $right['codes']
        );
        return [Auth::ADMIN, ...array_column($roles, 'rpd_role')];
    }

    /** One request by id, for the link in the e-mail: only the person asked, or who asked, may follow it. */
    public static function find(int $id): array
    {
        $r = App::db()->one('SELECT sig_path, sig_userid, sig_req_by FROM sig_det WHERE sig_id = ?', [$id]);
        if (!$r || !in_array(Auth::userId(), [$r['sig_userid'], $r['sig_req_by']], true)) {
            throw HttpException::notFound();
        }
        return $r;
    }

    /** @return array{0: array, 1: array} the document and one of our boxes on it */
    private static function box(string $path, int $slot): array
    {
        $doc = self::document($path);
        foreach (self::boxes($doc, $path) as $b) {
            if (!$b['outside'] && $b['slot'] === $slot) {
                return [$doc, $b];
            }
        }
        throw HttpException::notFound();
    }

    /** What is on every box of a document now: its newest row, by box (a withdrawn one, C, leaves the box empty). */
    private static function latest(string $path): array
    {
        $out = [];
        $rows = App::db()->all(
            'SELECT s.*, u.us_name FROM sig_det s LEFT JOIN us_mstr u ON u.us_userid = s.sig_userid WHERE s.sig_path = ? ORDER BY s.sig_id',
            [$path]
        );
        foreach ($rows as $r) {
            $out[(int) $r['sig_slot']] = $r;
        }
        return array_filter($out, fn ($r) => $r['sig_status'] !== 'C');
    }

    private static function pending(string $path, int $slot): ?array
    {
        return App::db()->one("SELECT * FROM sig_det WHERE sig_path = ? AND sig_slot = ? AND sig_status = 'R' ORDER BY sig_id DESC LIMIT 1", [$path, $slot]);
    }

    /** Retire what is on a box now (a signature, a request), before something new takes its place. */
    private static function close(string $path, int $slot): void
    {
        App::db()->run(
            "UPDATE sig_det SET sig_status = 'C', updated_by = ? WHERE sig_path = ? AND sig_slot = ? AND sig_status IN ('R', 'S')",
            [Auth::userId(), $path, $slot]
        );
    }

    private static function row(array $doc, string $path, int $slot, string $label): array
    {
        return [
            'sig_path' => $path, 'sig_slot' => $slot, 'sig_label' => mb_substr($label, 0, 100),
            'sig_title' => mb_substr(self::title($doc), 0, 255), 'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
        ];
    }

    private static function title(array $doc): string
    {
        return trim(($doc['title'] ?? '') . ' ' . ($doc['nbr'] ?? ''));
    }

    /**
     * Tell someone by e-mail, with a link that opens the document after logging in. Without mail set up, or when it
     * fails, the request still waits on the person's list: false says the mail did not go.
     */
    private static function mail(string $userid, string $subject, string $body, string $link): bool
    {
        $to = (string) App::db()->value('SELECT us_email FROM us_mstr WHERE us_userid = ? AND us_active = 1', [$userid]);
        if ($to === '' || trim((string) App::config('mail.host', '')) === '') {
            return false;
        }
        $base = rtrim((string) App::config('app.url', ''), '/');
        try {
            Mail::send(['to' => $to, 'subject' => $subject, 'body' => $body . "\n\n" . $base . $link]);
            return true;
        } catch (\Throwable $e) {
            error_log('PhaiERP signature mail: ' . $e->getMessage());
            return false;
        }
    }
}
