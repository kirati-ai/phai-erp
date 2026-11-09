<?php

declare(strict_types=1);

namespace Phai\Modules\Esign;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Mail;
use Phai\Core\Numbering;
use Phai\Modules\System\SignImage;

/**
 * Envelopes (env_mstr): documents from outside PhaiERP signed by people in order. The sender uploads the documents
 * and attachments, lines the signers up in steps (assistant manager → manager → owner), places their fields on the
 * pages and sends. Each step starts when the one before is fully signed; a refusal stops the envelope. When the last
 * step is signed, EnvelopePdf makes the final document (fields stamped, attachments, evidence pages, a footer on
 * every page) and everyone in the envelope gets the same file.
 *
 * Status: D draft, S being signed, C completed, X declined, W withdrawn by the sender. Signers: W not yet their turn,
 * R their turn (received), S signed, X declined, C replaced. Every change is an event in EnvelopeLog's hash chain;
 * the times a signer received, opened and signed come from this server's clock.
 *
 * Who sees an envelope: its sender, a signer once their turn has come (C-role readers once it is completed), and ADMIN.
 * Sending needs the add right on "envelopes"; signing needs only to be the person asked.
 *
 * People outside the company (kind E) are in the data but cannot be sent to yet: that goes through phai.monrak.net
 * (docs/e-document-sign.md in Phai Server, phase 2).
 */
final class Envelope
{
    public const PROGRAM = 'envelopes';
    public const ROLES = ['S', 'A', 'C'];
    public const SIGNER_TYPES = ['signature', 'initials', 'name', 'position', 'date', 'timeline', 'stamp', 'text', 'checkbox'];
    public const SENDER_TYPES = ['label', 'mark'];
    public const COLORS = ['#1d6fd8', '#d6336c', '#2f9e44', '#e8590c', '#7048e8', '#0c8599', '#c2255c', '#5c940d'];

    // ------------------------------------------------------------------ reading

    public static function row(string $nbr): array
    {
        return App::db()->one('SELECT * FROM env_mstr WHERE env_nbr = ?', [$nbr]) ?? throw HttpException::notFound();
    }

    /** The envelope as a page needs it, if the user may see it (404 otherwise, so numbers cannot be probed). */
    public static function load(string $nbr): array
    {
        $env = self::row($nbr);
        $signers = self::signers($nbr);
        $me = self::me($signers);
        if (!self::canSee($env, $me)) {
            throw HttpException::notFound();
        }
        $isOwner = $env['created_by'] === Auth::userId();
        return [
            'env' => $env,
            'files' => array_map([self::class, 'fileOut'], EnvelopeFile::list($nbr)),
            'signers' => $signers,
            'fields' => self::fields($nbr),
            'me' => $me,
            'owner' => $isOwner,
            'can' => [
                'edit' => $isOwner && $env['env_status'] === 'D',
                'sign' => $me !== null && $me['envs_status'] === 'R' && $env['env_status'] === 'S',
                'manage' => ($isOwner || Auth::isAdmin()) && $env['env_status'] === 'S',
                'final' => $env['env_status'] === 'C' && $env['env_final_file'] !== null,
            ],
        ];
    }

    public static function signers(string $nbr): array
    {
        return App::db()->all('SELECT * FROM envs_det WHERE envs_nbr = ? ORDER BY envs_step, envs_seq, envs_id', [$nbr]);
    }

    public static function fields(string $nbr): array
    {
        $rows = App::db()->all('SELECT * FROM envx_det WHERE envx_nbr = ? ORDER BY envx_page, envx_id', [$nbr]);
        foreach ($rows as &$r) {
            $r['envx_props'] = json_decode((string) $r['envx_props'], true) ?: [];
            foreach (['envx_x', 'envx_y', 'envx_w', 'envx_h'] as $k) {
                $r[$k] = (float) $r[$k];
            }
            $r['envx_signer'] = $r['envx_signer'] === null ? null : (int) $r['envx_signer'];
        }
        return $rows;
    }

    /** The current user's live row among the signers (the newest one not replaced), or null. */
    public static function me(array $signers): ?array
    {
        $uid = Auth::userId();
        $mine = null;
        foreach ($signers as $s) {
            if ($s['envs_userid'] === $uid && $s['envs_status'] !== 'C') {
                $mine = $s;
            }
        }
        return $mine;
    }

    private static function canSee(array $env, ?array $me): bool
    {
        if ($env['created_by'] === Auth::userId() || Auth::isAdmin()) {
            return true;
        }
        if ($me === null) {
            return false;
        }
        if ($me['envs_role'] === 'C') {
            return $env['env_status'] === 'C';
        }
        return $me['envs_status'] !== 'W';
    }

    private static function fileOut(array $f): array
    {
        $f['envf_sizes'] = json_decode((string) $f['envf_sizes'], true) ?: [];
        return $f;
    }

    /** Lists for /envelopes: waiting for me, sent by me, and finished ones I am in. */
    public static function lists(): array
    {
        $me = Auth::userId();
        $db = App::db();
        $base = 'SELECT e.env_nbr, e.env_title, e.env_status, e.env_step, e.env_sent_at, e.env_done_at, e.created_by, e.created_at, u.us_name AS sender
                   FROM env_mstr e LEFT JOIN us_mstr u ON u.us_userid = e.created_by';
        $waiting = $db->all(
            "SELECT e.env_nbr, e.env_title, e.env_sent_at, u.us_name AS sender, s.envs_role, s.envs_recv_at, s.envs_open_at
               FROM envs_det s JOIN env_mstr e ON e.env_nbr = s.envs_nbr LEFT JOIN us_mstr u ON u.us_userid = e.created_by
              WHERE s.envs_userid = ? AND s.envs_status = 'R' AND e.env_status = 'S' ORDER BY s.envs_recv_at",
            [$me]
        );
        $sent = $db->all("$base WHERE e.created_by = ? ORDER BY e.created_at DESC LIMIT 200", [$me]);
        $done = $db->all(
            "$base WHERE e.env_status = 'C' AND (e.created_by = ? OR EXISTS (SELECT 1 FROM envs_det s WHERE s.envs_nbr = e.env_nbr AND s.envs_userid = ? AND s.envs_status <> 'C'))
              ORDER BY e.env_done_at DESC LIMIT 200",
            [$me, $me]
        );
        $progress = self::progress(array_column($sent, 'env_nbr'));
        foreach ($sent as &$r) {
            $r['progress'] = $progress[$r['env_nbr']] ?? [0, 0];
        }
        return ['waiting' => $waiting, 'sent' => $sent, 'done' => $done, 'canSend' => Auth::can(self::PROGRAM, 'add')];
    }

    /** nbr => [signed, to sign] over the signers who sign (S, A). */
    private static function progress(array $nbrs): array
    {
        if (!$nbrs) {
            return [];
        }
        $rows = App::db()->all(
            "SELECT envs_nbr, SUM(envs_status = 'S') AS done, COUNT(*) AS n FROM envs_det
              WHERE envs_role IN ('S', 'A') AND envs_status <> 'C' AND envs_nbr IN (" . implode(',', array_fill(0, count($nbrs), '?')) . ') GROUP BY envs_nbr',
            $nbrs
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['envs_nbr']] = [(int) $r['done'], (int) $r['n']];
        }
        return $out;
    }

    public static function waitingCount(): int
    {
        $me = Auth::userId();
        return $me === null ? 0 : (int) App::db()->value(
            "SELECT COUNT(*) FROM envs_det s JOIN env_mstr e ON e.env_nbr = s.envs_nbr WHERE s.envs_userid = ? AND s.envs_status = 'R' AND e.env_status = 'S'",
            [$me]
        );
    }

    /** Active users who can be put in an envelope (anyone may be asked to sign: the envelope itself is the permission). */
    public static function people(): array
    {
        return App::db()->all(
            'SELECT u.us_userid, u.us_name, u.us_email, r.role_desc, u.us_sign IS NOT NULL AS has_sign
               FROM us_mstr u LEFT JOIN role_mstr r ON r.role_code = u.us_role WHERE u.us_active = 1 ORDER BY u.us_name'
        );
    }

    // ------------------------------------------------------------------ drafts

    public static function create(string $title): string
    {
        self::needSendRight();
        $title = trim(mb_substr($title, 0, 255));
        if ($title === '') {
            throw HttpException::invalid(['env_title' => t('validation.required')]);
        }
        $me = Auth::userId();
        return App::db()->transaction(function () use ($title, $me) {
            $nbr = Numbering::next('ENV', date('Y-m-d'));
            App::db()->insert('env_mstr', ['env_nbr' => $nbr, 'env_title' => $title, 'env_status' => 'D', 'created_by' => $me, 'updated_by' => $me]);
            EnvelopeLog::add($nbr, 'created', null, ['title' => $title]);
            return $nbr;
        });
    }

    /**
     * Save a draft as the editor holds it: {env_title, env_note, signers: [{key, step, role, kind, userid, name, email,
     * position, color}], fields: [{signer: key|null, page, type, x, y, w, h, props, link}]}. Signers and fields are
     * written again whole; keys are the editor's own ids for its signers.
     */
    public static function save(string $nbr, array $in): array
    {
        $env = self::draft($nbr);
        $title = trim(mb_substr((string) ($in['env_title'] ?? $env['env_title']), 0, 255));
        if ($title === '') {
            throw HttpException::invalid(['env_title' => t('validation.required')]);
        }
        $note = trim(mb_substr((string) ($in['env_note'] ?? ''), 0, 2000));
        $pages = self::mainPages($nbr);
        $users = array_column(self::people(), null, 'us_userid');
        $errors = [];
        $signers = [];
        foreach (array_values((array) ($in['signers'] ?? [])) as $i => $s) {
            $key = (string) ($s['key'] ?? $i);
            $role = in_array($s['role'] ?? 'S', self::ROLES, true) ? $s['role'] : 'S';
            $kind = ($s['kind'] ?? 'U') === 'E' ? 'E' : 'U';
            $row = [
                'envs_step' => max(1, min(99, (int) ($s['step'] ?? 1))), 'envs_seq' => $i + 1, 'envs_role' => $role, 'envs_kind' => $kind,
                'envs_position' => mb_substr(trim((string) ($s['position'] ?? '')), 0, 150) ?: null,
                'envs_color' => preg_match('/^#[0-9a-f]{6}$/i', (string) ($s['color'] ?? '')) ? $s['color'] : self::COLORS[$i % count(self::COLORS)],
            ];
            if ($kind === 'U') {
                $u = $users[(string) ($s['userid'] ?? '')] ?? null;
                if ($u === null) {
                    $errors["signers.$i.userid"] = t('esign.no_user');
                    continue;
                }
                $row += ['envs_userid' => $u['us_userid'], 'envs_name' => $u['us_name'] ?: $u['us_userid'], 'envs_email' => $u['us_email'] ?: null];
            } else {
                $name = mb_substr(trim((string) ($s['name'] ?? '')), 0, 150);
                $email = trim((string) ($s['email'] ?? ''));
                if ($name === '') {
                    $errors["signers.$i.name"] = t('validation.required');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors["signers.$i.email"] = t('validation.email');
                }
                $row += ['envs_userid' => null, 'envs_name' => $name, 'envs_email' => mb_substr($email, 0, 150)];
            }
            $signers[$key] = $row;
        }
        $fields = [];
        foreach (array_values((array) ($in['fields'] ?? [])) as $i => $f) {
            $type = (string) ($f['type'] ?? '');
            $signer = isset($f['signer']) && $f['signer'] !== null && $f['signer'] !== '' ? (string) $f['signer'] : null;
            $page = (int) ($f['page'] ?? 0);
            if ($page < 1 || $page > count($pages)) {
                continue; // a field on a page whose file was removed
            }
            if ($signer === null ? !in_array($type, self::SENDER_TYPES, true) : (!in_array($type, self::SIGNER_TYPES, true) || !isset($signers[$signer]) || $signers[$signer]['envs_role'] === 'C')) {
                continue;
            }
            [$pw, $ph] = $pages[$page - 1];
            $w = max(6, min($pw, (float) ($f['w'] ?? 0)));
            $h = max(6, min($ph, (float) ($f['h'] ?? 0)));
            $x = max(0, min($pw - $w, (float) ($f['x'] ?? 0)));
            $y = max(0, min($ph - $h, (float) ($f['y'] ?? 0)));
            $fields[] = [
                'signer' => $signer, 'envx_page' => $page, 'envx_type' => $type,
                'envx_x' => round($x, 2), 'envx_y' => round($y, 2), 'envx_w' => round($w, 2), 'envx_h' => round($h, 2),
                'envx_props' => json_encode(self::props((array) ($f['props'] ?? []), $type), JSON_UNESCAPED_UNICODE),
                'envx_link' => preg_match('/^[A-Za-z0-9_-]{1,20}$/', (string) ($f['link'] ?? '')) ? $f['link'] : null,
            ];
        }
        if ($errors) {
            throw HttpException::invalid($errors);
        }
        $me = Auth::userId();
        App::db()->transaction(function () use ($nbr, $title, $note, $signers, $fields, $me) {
            $db = App::db();
            $db->update('env_mstr', ['env_title' => $title, 'env_note' => $note !== '' ? $note : null, 'updated_by' => $me], ['env_nbr' => $nbr]);
            $db->run('DELETE FROM envx_det WHERE envx_nbr = ?', [$nbr]);
            $db->run('DELETE FROM envs_det WHERE envs_nbr = ?', [$nbr]);
            $ids = [];
            foreach ($signers as $key => $row) {
                $ids[$key] = (int) $db->insert('envs_det', $row + ['envs_nbr' => $nbr, 'created_by' => $me, 'updated_by' => $me]);
            }
            foreach ($fields as $f) {
                $signer = $f['signer'] === null ? null : $ids[$f['signer']];
                unset($f['signer']);
                $db->insert('envx_det', $f + ['envx_nbr' => $nbr, 'envx_signer' => $signer, 'created_by' => $me, 'updated_by' => $me]);
            }
        });
        return self::load($nbr);
    }

    /** What a field keeps besides its place: only known keys, with sane values. */
    private static function props(array $p, string $type): array
    {
        $out = [];
        if (isset($p['text'])) {
            $out['text'] = mb_substr((string) $p['text'], 0, 500);
        }
        if (isset($p['size'])) {
            $out['size'] = max(5, min(72, (float) $p['size']));
        }
        foreach (['bold', 'italic', 'underline', 'required', 'border', 'locked'] as $k) {
            if (!empty($p[$k])) {
                $out[$k] = true;
            }
        }
        if (in_array($p['align'] ?? '', ['left', 'center', 'right'], true)) {
            $out['align'] = $p['align'];
        }
        if (preg_match('/^#[0-9a-f]{6}$/i', (string) ($p['color'] ?? ''))) {
            $out['color'] = $p['color'];
        }
        if (isset($p['label'])) {
            $out['label'] = mb_substr((string) $p['label'], 0, 100);
        }
        if (in_array($type, ['signature', 'initials'], true) && !array_key_exists('required', $p)) {
            $out['required'] = true;
        }
        return $out;
    }

    public static function addFile(string $nbr, string $kind, string $name, string $data): array
    {
        self::draft($nbr);
        EnvelopeFile::store($nbr, $kind, $name, $data);
        return self::load($nbr);
    }

    /** Remove a file, or move it (dir -1 / +1), or make it a main document / an attachment (kind). */
    public static function changeFile(string $nbr, int $id, array $in): array
    {
        self::draft($nbr);
        $f = EnvelopeFile::find($nbr, $id);
        $db = App::db();
        $before = self::mainPages($nbr);
        if (!empty($in['remove'])) {
            EnvelopeFile::remove($f);
        } elseif (isset($in['kind'])) {
            $db->update('envf_det', ['envf_kind' => $in['kind'] === 'A' ? 'A' : 'M', 'updated_by' => Auth::userId()], ['envf_id' => $id]);
        } elseif (isset($in['dir'])) {
            $files = array_values(array_filter(EnvelopeFile::list($nbr), fn ($x) => $x['envf_kind'] === $f['envf_kind']));
            $at = array_search($id, array_map('intval', array_column($files, 'envf_id')), true);
            $to = $at + ((int) $in['dir'] < 0 ? -1 : 1);
            if ($at !== false && isset($files[$to])) {
                $db->update('envf_det', ['envf_seq' => $files[$to]['envf_seq']], ['envf_id' => $id]);
                $db->update('envf_det', ['envf_seq' => $f['envf_seq']], ['envf_id' => $files[$to]['envf_id']]);
            }
        }
        // fields belong to pages of the main documents: if those pages moved or went, the fields go with the old order
        if (self::mainPages($nbr) !== $before) {
            $db->run('DELETE FROM envx_det WHERE envx_nbr = ?', [$nbr]);
        }
        return self::load($nbr);
    }

    public static function deleteDraft(string $nbr): void
    {
        self::draft($nbr);
        foreach (EnvelopeFile::list($nbr) as $f) {
            EnvelopeFile::remove(EnvelopeFile::find($nbr, (int) $f['envf_id']));
        }
        $db = App::db();
        foreach (['envx_det' => 'envx_nbr', 'envs_det' => 'envs_nbr', 'envh_hist' => 'envh_nbr', 'env_mstr' => 'env_nbr'] as $t => $k) {
            $db->run("DELETE FROM $t WHERE $k = ?", [$nbr]);
        }
    }

    /** Sizes [w, h] of every page of the main documents, in order (the editor's page numbers). */
    public static function mainPages(string $nbr): array
    {
        $out = [];
        foreach (EnvelopeFile::list($nbr) as $f) {
            if ($f['envf_kind'] === 'M') {
                foreach (json_decode((string) $f['envf_sizes'], true) ?: [] as $s) {
                    $out[] = [(float) $s[0], (float) $s[1]];
                }
            }
        }
        return $out;
    }

    /** What stops a draft from being sent, as messages for the person (empty = ready). */
    public static function problems(string $nbr): array
    {
        $p = [];
        $files = EnvelopeFile::list($nbr);
        if (!array_filter($files, fn ($f) => $f['envf_kind'] === 'M')) {
            $p[] = t('esign.need_main');
        }
        $signers = array_filter(self::signers($nbr), fn ($s) => $s['envs_status'] !== 'C');
        $signing = array_filter($signers, fn ($s) => $s['envs_role'] !== 'C');
        if (!$signing) {
            $p[] = t('esign.need_signer');
        }
        $fields = self::fields($nbr);
        foreach ($signing as $s) {
            $has = array_filter($fields, fn ($f) => $f['envx_signer'] === (int) $s['envs_id'] && in_array($f['envx_type'], ['signature', 'initials'], true));
            if (!$has) {
                $p[] = t('esign.need_sign_field', ['name' => $s['envs_name']]);
            }
        }
        foreach ($signers as $s) {
            if ($s['envs_kind'] === 'E') {
                $p[] = t('esign.outside_not_yet', ['name' => $s['envs_name']]);
            }
        }
        foreach ($fields as $f) {
            if ($f['envx_type'] === 'stamp' && SignImage::dataUrl(null) === null) {
                $p[] = t('esign.no_company_stamp');
                break;
            }
        }
        return $p;
    }

    // ------------------------------------------------------------------ sending and signing

    public static function send(string $nbr): array
    {
        self::draft($nbr);
        $problems = self::problems($nbr);
        if ($problems) {
            throw new HttpException(422, implode(' ', $problems), ['send' => $problems[0]]);
        }
        $mails = App::db()->transaction(function () use ($nbr) {
            $files = array_map(fn ($f) => ['name' => $f['envf_name'], 'kind' => $f['envf_kind'], 'pages' => (int) $f['envf_pages'], 'sha256' => $f['envf_hash']], EnvelopeFile::list($nbr));
            App::db()->update('env_mstr', ['env_status' => 'S', 'env_sent_at' => date('Y-m-d H:i:s'), 'updated_by' => Auth::userId()], ['env_nbr' => $nbr]);
            EnvelopeLog::add($nbr, 'sent', null, ['files' => $files, 'content' => self::contentHash($nbr)]);
            return self::nextStep($nbr);
        });
        self::deliverMails($mails);
        return self::load($nbr);
    }

    /** Open the envelope as its signer: the first time is the "opened" time. */
    public static function open(string $nbr): void
    {
        $env = self::row($nbr);
        $me = self::me(self::signers($nbr));
        if ($env['env_status'] !== 'S' || $me === null || $me['envs_status'] !== 'R' || $me['envs_open_at'] !== null) {
            return;
        }
        App::db()->transaction(function () use ($nbr, $me) {
            $done = App::db()->run('UPDATE envs_det SET envs_open_at = ? WHERE envs_id = ? AND envs_open_at IS NULL', [date('Y-m-d H:i:s'), $me['envs_id']])->rowCount();
            if ($done) {
                EnvelopeLog::add($nbr, 'opened', (int) $me['envs_id']);
            }
        });
    }

    /**
     * Sign as the current user: {values: {field id: value}, image: PNG data URL drawn now (else my saved signature)}.
     * The fingerprint kept is of what the signer saw: the files, every field and what earlier signers filled in.
     */
    public static function sign(string $nbr, array $values, string $image): array
    {
        [$env, $me] = self::myTurn($nbr);
        self::open($nbr);
        $mine = array_filter(self::fields($nbr), fn ($f) => $f['envx_signer'] === (int) $me['envs_id']);
        $errors = [];
        $save = [];
        $needImage = false;
        foreach ($mine as $f) {
            $id = (int) $f['envx_id'];
            $v = $values[$id] ?? $values[(string) $id] ?? null;
            switch ($f['envx_type']) {
                case 'text':
                    $v = mb_substr(trim((string) $v), 0, 500);
                    if ($v === '' && !empty($f['envx_props']['required'])) {
                        $errors["f$id"] = t('validation.required');
                    }
                    $save[$id] = $v;
                    break;
                case 'checkbox':
                    $v = !empty($v) && $v !== '0' ? '1' : '0';
                    if ($v === '0' && !empty($f['envx_props']['required'])) {
                        $errors["f$id"] = t('esign.must_tick');
                    }
                    $save[$id] = $v;
                    break;
                case 'signature':
                case 'initials':
                    $needImage = true;
                    break;
            }
        }
        $sig = null;
        if ($needImage) {
            $sig = $image !== '' ? self::checkImage($image) : SignImage::dataUrl((string) Auth::userId());
            if ($sig === null) {
                $errors['image'] = t('esign.no_signature');
            }
        }
        if ($errors) {
            throw HttpException::invalid($errors);
        }
        $mails = App::db()->transaction(function () use ($nbr, $me, $save, $sig) {
            $db = App::db();
            $locked = $db->one('SELECT envs_status FROM envs_det WHERE envs_id = ? FOR UPDATE', [$me['envs_id']]);
            if (($locked['envs_status'] ?? '') !== 'R') {
                throw new HttpException(409, t('esign.not_your_turn'));
            }
            foreach ($save as $id => $v) {
                $db->update('envx_det', ['envx_value' => $v, 'updated_by' => Auth::userId()], ['envx_id' => $id]);
            }
            $seen = self::seenHash($nbr);
            $db->update('envs_det', [
                'envs_status' => 'S', 'envs_sign_at' => date('Y-m-d H:i:s'), 'envs_ip' => Request::current()?->ip,
                'envs_agent' => EnvelopeLog::agent(), 'envs_hash' => $seen, 'envs_image' => $sig, 'updated_by' => Auth::userId(),
            ], ['envs_id' => $me['envs_id']]);
            EnvelopeLog::add($nbr, 'signed', (int) $me['envs_id'], ['seen' => $seen, 'role' => $me['envs_role']]);
            return self::nextStep($nbr);
        });
        self::deliverMails($mails);
        return self::load($nbr);
    }

    /** Turn the envelope down, with the reason: it stops, and the sender and everyone reached so far are told. */
    public static function decline(string $nbr, string $note): array
    {
        [, $me] = self::myTurn($nbr);
        $note = trim(mb_substr($note, 0, 255));
        if ($note === '') {
            throw HttpException::invalid(['note' => t('esign.reason_needed')]);
        }
        self::open($nbr);
        $now = date('Y-m-d H:i:s');
        App::db()->transaction(function () use ($nbr, $me, $note, $now) {
            $db = App::db();
            $db->update('envs_det', ['envs_status' => 'X', 'envs_sign_at' => $now, 'envs_note' => $note, 'envs_ip' => Request::current()?->ip,
                'envs_agent' => EnvelopeLog::agent(), 'updated_by' => Auth::userId()], ['envs_id' => $me['envs_id']]);
            $db->update('env_mstr', ['env_status' => 'X', 'env_done_at' => $now, 'updated_by' => Auth::userId()], ['env_nbr' => $nbr]);
            EnvelopeLog::add($nbr, 'declined', (int) $me['envs_id'], ['reason' => $note]);
        });
        $env = self::row($nbr);
        self::tellReached($env, t('esign.mail_declined_subject', ['title' => $env['env_title']]),
            t('esign.mail_declined_body', ['name' => $me['envs_name'], 'title' => $env['env_title'], 'nbr' => $nbr, 'reason' => $note]));
        return self::load($nbr);
    }

    /** The sender takes the envelope back while it is being signed. */
    public static function recall(string $nbr): array
    {
        $env = self::managed($nbr);
        App::db()->transaction(function () use ($nbr) {
            App::db()->update('env_mstr', ['env_status' => 'W', 'env_done_at' => date('Y-m-d H:i:s'), 'updated_by' => Auth::userId()], ['env_nbr' => $nbr]);
            App::db()->run("UPDATE envs_det SET envs_status = 'C', updated_by = ? WHERE envs_nbr = ? AND envs_status IN ('W', 'R')", [Auth::userId(), $nbr]);
            EnvelopeLog::add($nbr, 'recalled');
        });
        self::tellReached($env, t('esign.mail_recalled_subject', ['title' => $env['env_title']]), t('esign.mail_recalled_body', ['title' => $env['env_title'], 'nbr' => $nbr]));
        return self::load($nbr);
    }

    /** Mail the people whose turn it is again. */
    public static function remind(string $nbr): array
    {
        $env = self::managed($nbr);
        $mails = [];
        $sent = [];
        foreach (self::signers($nbr) as $s) {
            if ($s['envs_status'] === 'R') {
                $mails[] = self::turnMail($env, $s, true);
                $sent[] = $s['envs_name'];
            }
        }
        App::db()->transaction(fn () => EnvelopeLog::add($nbr, 'reminded', null, ['to' => $sent]));
        return self::load($nbr) + ['mailed' => self::deliverMails($mails)];
    }

    /** Put another user in place of a signer who has not signed yet (on leave, left the company). */
    public static function replace(string $nbr, int $signerId, string $userid): array
    {
        $env = self::managed($nbr);
        $old = App::db()->one('SELECT * FROM envs_det WHERE envs_id = ? AND envs_nbr = ?', [$signerId, $nbr]) ?? throw HttpException::notFound();
        if (!in_array($old['envs_status'], ['W', 'R'], true)) {
            throw new HttpException(409, t('esign.cannot_replace'));
        }
        $u = array_column(self::people(), null, 'us_userid')[$userid] ?? throw HttpException::invalid(['userid' => t('esign.no_user')]);
        $now = date('Y-m-d H:i:s');
        $mails = App::db()->transaction(function () use ($nbr, $old, $u, $now, $env) {
            $db = App::db();
            $db->update('envs_det', ['envs_status' => 'C', 'updated_by' => Auth::userId()], ['envs_id' => $old['envs_id']]);
            $row = array_intersect_key($old, array_flip(['envs_nbr', 'envs_step', 'envs_seq', 'envs_role', 'envs_kind', 'envs_position', 'envs_color']));
            $row += [
                'envs_userid' => $u['us_userid'], 'envs_name' => $u['us_name'] ?: $u['us_userid'], 'envs_email' => $u['us_email'] ?: null,
                'envs_status' => $old['envs_status'], 'envs_recv_at' => $old['envs_status'] === 'R' ? $now : null,
                'envs_replaces' => $old['envs_id'], 'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
            ];
            $id = (int) $db->insert('envs_det', $row);
            $db->run('UPDATE envx_det SET envx_signer = ? WHERE envx_signer = ?', [$id, $old['envs_id']]);
            EnvelopeLog::add($nbr, 'replaced', $id, ['old' => $old['envs_name'], 'new' => $row['envs_name']]);
            if ($old['envs_status'] === 'R') {
                EnvelopeLog::add($nbr, 'received', $id);
                return [self::turnMail($env, $db->one('SELECT * FROM envs_det WHERE envs_id = ?', [$id]))];
            }
            return [];
        });
        self::deliverMails($mails);
        return self::load($nbr);
    }

    /**
     * Start the next step whose signers have not signed; with none left, finish the envelope. Runs inside the
     * caller's transaction and returns the mails to send once it is committed.
     */
    private static function nextStep(string $nbr): array
    {
        $db = App::db();
        $signers = array_filter(self::signers($nbr), fn ($s) => $s['envs_status'] !== 'C' && $s['envs_role'] !== 'C');
        if (array_filter($signers, fn ($s) => $s['envs_status'] === 'R')) {
            return []; // the step is not fully signed yet
        }
        $waiting = array_filter($signers, fn ($s) => $s['envs_status'] === 'W');
        if (!$waiting) {
            return self::finish($nbr);
        }
        $step = min(array_map(fn ($s) => (int) $s['envs_step'], $waiting));
        $env = self::row($nbr);
        $now = date('Y-m-d H:i:s');
        $mails = [];
        $db->update('env_mstr', ['env_step' => $step], ['env_nbr' => $nbr]);
        foreach ($waiting as $s) {
            if ((int) $s['envs_step'] !== $step) {
                continue;
            }
            $db->update('envs_det', ['envs_status' => 'R', 'envs_recv_at' => $now], ['envs_id' => $s['envs_id']]);
            $s['envs_status'] = 'R';
            $mail = self::turnMail($env, $s);
            EnvelopeLog::add($nbr, 'received', (int) $s['envs_id'], ['step' => $step, 'mail' => $mail !== null]);
            $mails[] = $mail;
        }
        return $mails;
    }

    /** Make the final document, keep it, and send it to everyone in the envelope. */
    private static function finish(string $nbr): array
    {
        $env = self::row($nbr);
        $built = EnvelopePdf::build($nbr);
        $file = EnvelopeFile::write($built['bytes']);
        $hash = hash('sha256', $built['bytes']);
        App::db()->update('env_mstr', [
            'env_status' => 'C', 'env_done_at' => $built['at'], 'env_pages' => $built['pages'], 'env_final_file' => $file,
            'env_final_hash' => $hash, 'env_final_size' => strlen($built['bytes']), 'env_page_hash' => json_encode($built['fingerprints']),
            'updated_by' => Auth::userId(),
        ], ['env_nbr' => $nbr]);
        EnvelopeLog::add($nbr, 'completed', null, ['sha256' => $hash, 'pages' => $built['pages']]);
        $mails = [];
        $to = [];
        foreach (self::recipients($nbr) as $r) {
            $to[] = $r['name'];
            if ($r['email']) {
                $mails[] = [
                    'to' => $r['email'], 'subject' => t('esign.mail_done_subject', ['title' => $env['env_title']]),
                    'body' => t('esign.mail_done_body', ['title' => $env['env_title'], 'nbr' => $nbr, 'pages' => $built['pages'], 'hash' => $hash]),
                    'link' => "/envelopes/$nbr",
                    'attach' => strlen($built['bytes']) <= 10 * 1024 * 1024 ? ['name' => "$nbr.pdf", 'data' => $built['bytes'], 'type' => 'application/pdf'] : null,
                ];
            }
        }
        EnvelopeLog::add($nbr, 'delivered', null, ['to' => $to]);
        return $mails;
    }

    /** Everyone who gets the final document: the sender, every signer and every reader, once each. */
    public static function recipients(string $nbr): array
    {
        $env = self::row($nbr);
        $out = [];
        $owner = App::db()->one('SELECT us_userid, us_name, us_email FROM us_mstr WHERE us_userid = ?', [$env['created_by']]);
        if ($owner) {
            $out[$owner['us_userid']] = ['name' => $owner['us_name'] ?: $owner['us_userid'], 'email' => $owner['us_email'] ?: null];
        }
        foreach (self::signers($nbr) as $s) {
            if ($s['envs_status'] === 'C') {
                continue;
            }
            $key = $s['envs_userid'] ?? 'e:' . strtolower((string) $s['envs_email']);
            $out[$key] ??= ['name' => $s['envs_name'], 'email' => $s['envs_email']];
        }
        return array_values($out);
    }

    // ------------------------------------------------------------------ fingerprints

    /** One fingerprint for the set of clean files (the short code in every page footer). */
    public static function contentHash(string $nbr): string
    {
        return hash('sha256', implode(',', array_column(EnvelopeFile::list($nbr), 'envf_hash')));
    }

    /** What a signer saw when signing: the files, every field, and what was filled in before. */
    public static function seenHash(string $nbr): string
    {
        $fields = array_map(fn ($f) => [$f['envx_page'], $f['envx_type'], $f['envx_x'], $f['envx_y'], $f['envx_w'], $f['envx_h'], $f['envx_signer'], $f['envx_value'], $f['envx_props']], self::fields($nbr));
        $signed = array_map(fn ($s) => [$s['envs_id'], $s['envs_status'], $s['envs_sign_at'], $s['envs_image'] ? hash('sha256', (string) $s['envs_image']) : null], self::signers($nbr));
        return hash('sha256', json_encode([self::contentHash($nbr), $fields, $signed], JSON_UNESCAPED_UNICODE));
    }

    // ------------------------------------------------------------------ helpers

    private static function needSendRight(): void
    {
        if (!Auth::can(self::PROGRAM, 'add')) {
            throw new HttpException(403, t('auth.forbidden'));
        }
    }

    /** A draft the current user sent (only the sender edits it). */
    private static function draft(string $nbr): array
    {
        $env = self::row($nbr);
        if ($env['created_by'] !== Auth::userId()) {
            throw HttpException::notFound();
        }
        if ($env['env_status'] !== 'D') {
            throw new HttpException(409, t('esign.not_draft'));
        }
        return $env;
    }

    /** An envelope being signed, for its sender (or ADMIN) to remind, recall or change a signer. */
    private static function managed(string $nbr): array
    {
        $env = self::row($nbr);
        if ($env['created_by'] !== Auth::userId() && !Auth::isAdmin()) {
            throw HttpException::notFound();
        }
        if ($env['env_status'] !== 'S') {
            throw new HttpException(409, t('esign.not_signing'));
        }
        return $env;
    }

    /** @return array{0: array, 1: array} the envelope and my signer row, when it is my turn */
    private static function myTurn(string $nbr): array
    {
        $env = self::row($nbr);
        $me = self::me(self::signers($nbr));
        if ($me === null || !self::canSee($env, $me)) {
            throw HttpException::notFound();
        }
        if ($env['env_status'] !== 'S' || $me['envs_status'] !== 'R' || $me['envs_role'] === 'C') {
            throw new HttpException(409, t('esign.not_your_turn'));
        }
        return [$env, $me];
    }

    /** A signature drawn now: a real PNG of a sensible size, like a saved one (SignImage). */
    private static function checkImage(string $dataUrl): string
    {
        if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m) || ($bytes = base64_decode($m[1], true)) === false
            || strlen($bytes) > SignImage::MAX_BYTES) {
            throw HttpException::invalid(['image' => t('system.sign_type')]);
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info || ($info['mime'] ?? '') !== 'image/png' || $info[0] < 16 || $info[1] < 16 || $info[0] > 2000 || $info[1] > 2000) {
            throw HttpException::invalid(['image' => t('system.sign_type')]);
        }
        return 'data:image/png;base64,' . base64_encode($bytes);
    }

    /** The mail telling a signer it is their turn (null when they have no address or mail is not set up). */
    private static function turnMail(array $env, array $s, bool $again = false): ?array
    {
        if (!$s['envs_email']) {
            return null;
        }
        $sender = (string) (App::db()->value('SELECT us_name FROM us_mstr WHERE us_userid = ?', [$env['created_by']]) ?? $env['created_by']);
        $body = t($again ? 'esign.mail_remind_body' : 'esign.mail_turn_body', [
            'name' => $s['envs_name'], 'sender' => $sender, 'title' => $env['env_title'], 'nbr' => $env['env_nbr'],
            'role' => t('esign.role_' . $s['envs_role']),
        ]) . ($env['env_note'] ? "\n\n" . $env['env_note'] : '');
        return ['to' => $s['envs_email'], 'subject' => t('esign.mail_turn_subject', ['title' => $env['env_title']]), 'body' => $body, 'link' => "/envelopes/{$env['env_nbr']}/sign"];
    }

    /** Mail the sender and every signer the envelope has reached. */
    private static function tellReached(array $env, string $subject, string $body): void
    {
        $mails = [];
        $seen = [];
        $owner = App::db()->value('SELECT us_email FROM us_mstr WHERE us_userid = ?', [$env['created_by']]);
        foreach (array_merge([$owner], array_column(array_filter(self::signers($env['env_nbr']), fn ($s) => $s['envs_recv_at'] !== null), 'envs_email')) as $to) {
            if ($to && !isset($seen[strtolower($to)])) {
                $seen[strtolower($to)] = true;
                $mails[] = ['to' => $to, 'subject' => $subject, 'body' => $body, 'link' => "/envelopes/{$env['env_nbr']}"];
            }
        }
        self::deliverMails($mails);
    }

    /** Send what a change queued, after it is saved; mail not set up or failing never undoes the change. */
    private static function deliverMails(array $mails): int
    {
        $mails = array_values(array_filter($mails));
        if (!$mails || trim((string) App::config('mail.host', '')) === '') {
            return 0;
        }
        $base = rtrim((string) App::config('app.url', ''), '/');
        $sent = 0;
        foreach ($mails as $m) {
            try {
                Mail::send([
                    'to' => $m['to'], 'subject' => $m['subject'], 'body' => $m['body'] . "\n\n" . $base . $m['link'],
                    'attachments' => !empty($m['attach']) ? [$m['attach']] : [],
                ]);
                $sent++;
            } catch (\Throwable $e) {
                error_log('PhaiERP envelope mail: ' . $e->getMessage());
            }
        }
        return $sent;
    }

    /** A server time as people read it: 9 พ.ย. 2569 09:02:05 (Thai) / 9 Nov 2026 09:02:05. */
    public static function when(?string $at, bool $seconds = true): string
    {
        if (!$at) {
            return '';
        }
        $ts = strtotime($at);
        $months = explode(',', t('esign.months'));
        $year = (int) date('Y', $ts) + (int) t('esign.year_offset');
        return (int) date('j', $ts) . ' ' . ($months[(int) date('n', $ts) - 1] ?? date('M', $ts)) . " $year " . date($seconds ? 'H:i:s' : 'H:i', $ts);
    }
}
