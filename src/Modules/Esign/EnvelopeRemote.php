<?php

declare(strict_types=1);

namespace Phai\Modules\Esign;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Lang;

/**
 * People outside the company (signers of kind E) sign through the Phai Sign service (phai.monrak.net, its own project
 * phai-monrak). This machine always calls out to it; nothing calls in, so no port is opened.
 *
 * When an outsider's step comes, push() sends the main documents with what earlier signers filled in already stamped
 * on (EnvelopePdf::snapshot), the attachments, and that person's fields. The service mails them, checks a code sent to
 * the same address, and lets them sign with their typed name in a handwriting style. sync() asks for results (from
 * bin/phai esign-sync on a timer, and on page loads at most every 30 seconds), signs the step in here with the
 * service's times (Envelope::remoteSigned), and has the service delete its copy. A finished envelope with outsiders is
 * sent up once more so they get the final document, and the SHA-256 of every finished envelope can be recorded there
 * as an outside witness.
 *
 * Off until an administrator turns it on and registers this machine (env_ctrl.env_remote_*): documents leave the
 * building for a while, and the people in charge must choose that.
 */
final class EnvelopeRemote
{
    private const SYNC_EVERY = 30;

    public static function settings(): array
    {
        return App::db()->one('SELECT * FROM env_ctrl WHERE ctrl_id = 1') ?? [];
    }

    public static function ready(): bool
    {
        $s = self::settings();
        return !empty($s['env_remote_on']) && !empty($s['env_remote_id']) && !empty($s['env_remote_key']) && !empty($s['env_remote_url']);
    }

    /** What the settings box shows (never the key). */
    public static function view(): array
    {
        $s = self::settings();
        return [
            'on' => !empty($s['env_remote_on']), 'url' => (string) ($s['env_remote_url'] ?? ''), 'registered' => !empty($s['env_remote_id']),
            'server' => $s['env_remote_id'] ?? null, 'name' => $s['env_remote_name'] ?? null, 'days' => (int) ($s['env_remote_days'] ?? 14),
            'witness' => !empty($s['env_witness_on']), 'sync' => Envelope::when($s['env_remote_sync'] ?? null),
            'company' => (string) (App::db()->value('SELECT co_name FROM co_mstr WHERE co_id = 1') ?? ''),
            'email' => (string) (App::db()->value('SELECT us_email FROM us_mstr WHERE us_userid = ?', [Auth::userId()]) ?? ''),
        ];
    }

    /** {on, url, days, witness}: ADMIN only. Turning it on needs the machine registered. */
    public static function save(array $in): array
    {
        self::needAdmin();
        $s = self::settings();
        $url = rtrim(trim((string) ($in['url'] ?? $s['env_remote_url'])), '/');
        if (!preg_match('#^https?://[^\s/]+(/\S*)?$#', $url)) {
            throw HttpException::invalid(['url' => t('esign.remote_bad_url')]);
        }
        $on = !empty($in['on']);
        if ($on && empty($s['env_remote_id'])) {
            throw HttpException::invalid(['on' => t('esign.remote_register_first')]);
        }
        App::db()->update('env_ctrl', [
            'env_remote_on' => $on ? 1 : 0, 'env_remote_url' => $url, 'env_remote_days' => max(1, min(60, (int) ($in['days'] ?? $s['env_remote_days']))),
            'env_witness_on' => !empty($in['witness']) ? 1 : 0, 'updated_by' => Auth::userId(),
        ], ['ctrl_id' => 1]);
        return self::view();
    }

    /** {url, name, contact, code}: register this machine with the service; keeps the id and key it hands back. */
    public static function register(array $in): array
    {
        self::needAdmin();
        $url = rtrim(trim((string) ($in['url'] ?? '')), '/');
        if (!preg_match('#^https?://[^\s/]+(/\S*)?$#', $url)) {
            throw HttpException::invalid(['url' => t('esign.remote_bad_url')]);
        }
        $name = trim(mb_substr((string) ($in['name'] ?? ''), 0, 150));
        if ($name === '') {
            throw HttpException::invalid(['name' => t('validation.required')]);
        }
        try {
            $r = self::http('POST', $url . '/api/v1/register', json_encode([
                'name' => $name, 'contact' => trim((string) ($in['contact'] ?? '')), 'code' => trim((string) ($in['code'] ?? '')),
                'version' => 'PhaiERP ' . App::VERSION,
            ], JSON_UNESCAPED_UNICODE), []);
        } catch (\RuntimeException $e) {
            throw new HttpException(502, $e->getMessage());
        }
        if (empty($r['server']) || empty($r['secret'])) {
            throw new HttpException(502, t('esign.remote_failed', ['error' => 'register']));
        }
        App::db()->update('env_ctrl', [
            'env_remote_url' => $url, 'env_remote_id' => $r['server'], 'env_remote_key' => $r['secret'], 'env_remote_name' => $r['name'] ?? $name,
            'updated_by' => Auth::userId(),
        ], ['ctrl_id' => 1]);
        return self::view();
    }

    /** Can this machine reach the service with its key? */
    public static function ping(): array
    {
        self::needAdmin();
        try {
            $r = self::call('GET', '/api/v1/ping');
        } catch (\RuntimeException $e) {
            throw new HttpException(502, $e->getMessage());
        }
        return ['ok' => true, 'name' => $r['name'] ?? '', 'skew' => isset($r['ts']) ? (int) $r['ts'] - time() : null];
    }

    // ------------------------------------------------------------------ sending

    /** Send every outside signer whose turn it is and who is not at the service yet. Errors are kept on the signer. */
    public static function push(string $nbr): void
    {
        if (!self::ready()) {
            return;
        }
        $env = Envelope::row($nbr);
        if ($env['env_status'] !== 'S') {
            return;
        }
        foreach (Envelope::signers($nbr) as $s) {
            if ($s['envs_kind'] === 'E' && $s['envs_status'] === 'R' && $s['envs_ext'] === null) {
                self::pushOne($env, $s);
            }
        }
    }

    private static function pushOne(array $env, array $s): void
    {
        $nbr = $env['env_nbr'];
        $db = App::db();
        try {
            $mine = array_values(array_filter(Envelope::fields($nbr), fn ($f) => $f['envx_signer'] === (int) $s['envs_id']));
            $attach = [];
            foreach (EnvelopeFile::rows($nbr) as $f) {
                if ($f['envf_kind'] === 'A') {
                    $attach[] = ['name' => $f['envf_name'], 'data' => base64_encode(EnvelopeFile::bytes($f))];
                }
            }
            $set = self::settings();
            $r = self::call('POST', '/api/v1/requests', [
                'ref' => "$nbr/{$s['envs_id']}", 'title' => $env['env_title'], 'note' => $env['env_note'], 'lang' => Lang::locale(),
                'company' => (string) ($db->value('SELECT co_name FROM co_mstr WHERE co_id = 1') ?: $set['env_remote_name']),
                'sender' => (string) ($db->value('SELECT us_name FROM us_mstr WHERE us_userid = ?', [$env['created_by']]) ?? $env['created_by']),
                'expire_days' => (int) $set['env_remote_days'],
                'signer' => ['name' => $s['envs_name'], 'email' => $s['envs_email'], 'position' => $s['envs_position'], 'role' => $s['envs_role']],
                'pages' => Envelope::mainPages($nbr),
                'fields' => array_map(fn ($f) => [
                    'id' => (int) $f['envx_id'], 'type' => $f['envx_type'], 'page' => (int) $f['envx_page'],
                    'x' => $f['envx_x'], 'y' => $f['envx_y'], 'w' => $f['envx_w'], 'h' => $f['envx_h'], 'props' => $f['envx_props'],
                    'text' => match ($f['envx_type']) { 'name' => $s['envs_name'], 'position' => $s['envs_position'], default => null },
                ], $mine),
                'document_name' => $env['env_title'], 'document' => base64_encode(EnvelopePdf::snapshot($nbr, (int) $s['envs_id'])),
                'attachments' => $attach,
            ]);
            $db->transaction(function () use ($nbr, $s, $r, $db) {
                $db->update('envs_det', [
                    'envs_ext' => $r['id'], 'envs_ext_state' => 'R', 'envs_ext_err' => null,
                    'envs_recv_at' => isset($r['sent_ts']) ? date('Y-m-d H:i:s', (int) $r['sent_ts']) : date('Y-m-d H:i:s'),
                ], ['envs_id' => $s['envs_id']]);
                EnvelopeLog::add($nbr, 'remote_sent', (int) $s['envs_id'], ['id' => $r['id'], 'expires' => isset($r['expires_ts']) ? date('Y-m-d H:i:s', (int) $r['expires_ts']) : null], []);
            });
        } catch (\Throwable $e) {
            $msg = mb_substr($e->getMessage(), 0, 250);
            if ($s['envs_ext_err'] === null) {
                $db->transaction(fn () => EnvelopeLog::add($nbr, 'remote_failed', (int) $s['envs_id'], ['error' => $msg], []));
            }
            $db->update('envs_det', ['envs_ext_err' => $msg], ['envs_id' => $s['envs_id']]);
            error_log('PhaiERP Phai Sign push: ' . $msg);
        }
    }

    // ------------------------------------------------------------------ results

    /** Ask for results, on page loads; at most every SYNC_EVERY seconds, and never in the way of the page. */
    public static function maybeSync(): void
    {
        try {
            if (!self::ready()) {
                return;
            }
            $cut = date('Y-m-d H:i:s', time() - self::SYNC_EVERY);
            $took = App::db()->run('UPDATE env_ctrl SET env_remote_sync = ? WHERE ctrl_id = 1 AND (env_remote_sync IS NULL OR env_remote_sync < ?)', [date('Y-m-d H:i:s'), $cut])->rowCount();
            if ($took) {
                self::sync();
            }
        } catch (\Throwable $e) {
            error_log('PhaiERP Phai Sign sync: ' . $e->getMessage());
        }
    }

    /**
     * Everything owed to or by the service: push outsiders not sent yet, pull results of those waiting, then for
     * finished envelopes the final document for outsiders and the witness. Returns counts, for bin/phai esign-sync.
     */
    public static function sync(): array
    {
        $n = ['pushed' => 0, 'signed' => 0, 'declined' => 0, 'finals' => 0, 'witnessed' => 0, 'errors' => 0];
        if (!self::ready()) {
            return $n;
        }
        // what follows is the machine's doing, whoever's page load set it off
        $was = EnvelopeLog::$as;
        EnvelopeLog::$as = [];
        try {
            return self::syncAll($n);
        } finally {
            EnvelopeLog::$as = $was;
        }
    }

    private static function syncAll(array $n): array
    {
        $db = App::db();
        $db->run('UPDATE env_ctrl SET env_remote_sync = ? WHERE ctrl_id = 1', [date('Y-m-d H:i:s')]);
        $waiting = $db->all(
            "SELECT s.* FROM envs_det s JOIN env_mstr e ON e.env_nbr = s.envs_nbr
              WHERE s.envs_kind = 'E' AND s.envs_status = 'R' AND e.env_status = 'S' ORDER BY s.envs_id"
        );
        foreach ($waiting as $s) {
            if ($s['envs_ext'] === null) {
                self::push($s['envs_nbr']);
                $n['pushed']++;
            }
        }
        $ids = array_column(array_filter($waiting, fn ($s) => $s['envs_ext'] !== null), 'envs_id', 'envs_ext');
        foreach (array_chunk(array_keys($ids), 100) as $chunk) {
            try {
                $r = self::call('GET', '/api/v1/requests?ids=' . implode(',', $chunk));
            } catch (\Throwable $e) {
                $n['errors']++;
                error_log('PhaiERP Phai Sign pull: ' . $e->getMessage());
                continue;
            }
            foreach ($r['requests'] ?? [] as $req) {
                try {
                    $what = self::apply((int) $ids[$req['id']], $req);
                    if ($what) {
                        $n[$what]++;
                    }
                } catch (\Throwable $e) {
                    $n['errors']++;
                    error_log('PhaiERP Phai Sign apply: ' . $e->getMessage());
                }
            }
        }
        foreach ($db->all("SELECT env_nbr FROM env_mstr WHERE env_status = 'C' AND (env_remote_final IS NULL OR env_witness_id IS NULL) AND env_done_at > ? ORDER BY env_done_at",
            [date('Y-m-d H:i:s', strtotime('-30 days'))]) as $e) {
            $done = self::finished($e['env_nbr']);
            $n['finals'] += $done['final'];
            $n['witnessed'] += $done['witness'];
        }
        return $n;
    }

    /** One request as the service has it: opened, signed, declined, or a link that expired or locked. */
    private static function apply(int $signerId, array $req): ?string
    {
        $db = App::db();
        $s = $db->one('SELECT * FROM envs_det WHERE envs_id = ?', [$signerId]);
        if (!$s || $s['envs_ext'] !== $req['id'] || $s['envs_status'] !== 'R') {
            return null;
        }
        $nbr = $s['envs_nbr'];
        if (!empty($req['open_ts']) && $s['envs_open_at'] === null) {
            $db->transaction(function () use ($s, $req, $nbr, $db) {
                if ($db->run('UPDATE envs_det SET envs_open_at = ? WHERE envs_id = ? AND envs_open_at IS NULL', [date('Y-m-d H:i:s', (int) $req['open_ts']), $s['envs_id']])->rowCount()) {
                    $ev = array_values(array_filter($req['events'] ?? [], fn ($e) => $e['type'] === 'opened'))[0] ?? [];
                    EnvelopeLog::add($nbr, 'opened', (int) $s['envs_id'], ['remote' => true, 'at' => date('Y-m-d H:i:s', (int) $req['open_ts'])],
                        ['ip' => $ev['ip'] ?? null, 'agent' => $ev['data']['agent'] ?? null]);
                }
            });
        }
        $state = $req['status'] === 'R' && !empty($req['locked']) ? 'L' : $req['status'];
        if ($state !== $s['envs_ext_state']) {
            $db->update('envs_det', ['envs_ext_state' => $state], ['envs_id' => $s['envs_id']]);
        }
        $what = null;
        if ($req['status'] === 'S') {
            Envelope::remoteSigned($nbr, $s, $req);
            $what = 'signed';
        } elseif ($req['status'] === 'X') {
            Envelope::remoteDeclined($nbr, $s, $req);
            $what = 'declined';
        }
        if ($what) {
            self::close($req['id'], (int) $s['envs_id']);
            self::after($nbr);
        }
        return $what;
    }

    /** After a change to an envelope: send outsiders whose turn came, or deliver a finished one. Never throws. */
    public static function after(string $nbr): void
    {
        try {
            if (!self::ready()) {
                return;
            }
            $env = Envelope::row($nbr);
            if ($env['env_status'] === 'S') {
                self::push($nbr);
            } elseif ($env['env_status'] === 'C') {
                self::finished($nbr);
            } elseif (in_array($env['env_status'], ['X', 'W'], true)) {
                self::withdraw($nbr);
            }
        } catch (\Throwable $e) {
            error_log('PhaiERP Phai Sign after: ' . $e->getMessage());
        }
    }

    /** A finished envelope: the final document to its outsiders (once), and its SHA-256 as a witness (once). */
    private static function finished(string $nbr): array
    {
        $out = ['final' => 0, 'witness' => 0];
        $env = Envelope::row($nbr);
        $set = self::settings();
        $db = App::db();
        $outsiders = array_values(array_filter(Envelope::signers($nbr), fn ($s) => $s['envs_kind'] === 'E' && $s['envs_status'] !== 'C' && $s['envs_email']));
        if ($env['env_remote_final'] === null) {
            if (!$outsiders) {
                $db->update('env_mstr', ['env_remote_final' => '-'], ['env_nbr' => $nbr]);
            } else {
                try {
                    $bytes = EnvelopeFile::read((string) $env['env_final_file']);
                    $r = self::call('POST', '/api/v1/finals', [
                        'ref' => $nbr, 'title' => $env['env_title'], 'sha256' => $env['env_final_hash'], 'pages' => (int) $env['env_pages'], 'lang' => Lang::locale(),
                        'company' => (string) ($db->value('SELECT co_name FROM co_mstr WHERE co_id = 1') ?: $set['env_remote_name']),
                        'document' => base64_encode($bytes),
                        'recipients' => array_map(fn ($s) => ['name' => $s['envs_name'], 'email' => $s['envs_email']], $outsiders),
                    ]);
                    $db->transaction(function () use ($nbr, $r, $outsiders, $db) {
                        $db->update('env_mstr', ['env_remote_final' => $r['id']], ['env_nbr' => $nbr]);
                        EnvelopeLog::add($nbr, 'remote_final', null, ['id' => $r['id'], 'to' => array_column($outsiders, 'envs_name'), 'until' => date('Y-m-d H:i:s', (int) $r['expires_ts'])], []);
                    });
                    $out['final'] = 1;
                } catch (\Throwable $e) {
                    error_log('PhaiERP Phai Sign final: ' . $e->getMessage());
                }
            }
        }
        if ($env['env_witness_id'] === null) {
            if (empty($set['env_witness_on'])) {
                return $out;
            }
            try {
                $r = self::call('POST', '/api/v1/witness', ['sha256' => $env['env_final_hash'], 'pages' => (int) $env['env_pages']]);
                $at = date('Y-m-d H:i:s', (int) $r['ts']);
                $db->transaction(function () use ($nbr, $r, $at, $db) {
                    $db->update('env_mstr', ['env_witness_id' => $r['receipt'], 'env_witness_at' => $at], ['env_nbr' => $nbr]);
                    EnvelopeLog::add($nbr, 'witnessed', null, ['receipt' => $r['receipt'], 'at' => $at], []);
                });
                $out['witness'] = 1;
            } catch (\Throwable $e) {
                error_log('PhaiERP Phai Sign witness: ' . $e->getMessage());
            }
        }
        return $out;
    }

    /** Withdraw every outsider of a stopped envelope still waiting at the service (they are told), and delete the copies. */
    public static function withdraw(string $nbr): void
    {
        foreach (Envelope::signers($nbr) as $s) {
            if ($s['envs_ext'] !== null && !in_array($s['envs_ext_state'], ['S', 'X', 'D', 'W'], true)) {
                self::close($s['envs_ext'], (int) $s['envs_id']);
            }
        }
    }

    /** Ask the service to delete its copy (and withdraw it if it was still waiting). */
    public static function close(string $ext, int $signerId): void
    {
        try {
            $r = self::call('DELETE', '/api/v1/requests/' . $ext);
            App::db()->update('envs_det', ['envs_ext_state' => !empty($r['purged']) ? ($r['status'] === 'W' ? 'W' : 'D') : $r['status']], ['envs_id' => $signerId]);
        } catch (\Throwable $e) {
            error_log('PhaiERP Phai Sign close: ' . $e->getMessage());
        }
    }

    /** A new link for an outsider (the old one stops working); sends them again if they never reached the service. */
    public static function remind(array $s): bool
    {
        if (!self::ready()) {
            return false;
        }
        if ($s['envs_ext'] === null) {
            self::push($s['envs_nbr']);
            return App::db()->value('SELECT envs_ext FROM envs_det WHERE envs_id = ?', [$s['envs_id']]) !== null;
        }
        try {
            $r = self::call('POST', '/api/v1/requests/' . $s['envs_ext'] . '/remind', ['expire_days' => (int) self::settings()['env_remote_days']]);
            App::db()->update('envs_det', ['envs_ext_state' => $r['status'] ?? 'R', 'envs_ext_err' => null], ['envs_id' => $s['envs_id']]);
            return true;
        } catch (\Throwable $e) {
            App::db()->update('envs_det', ['envs_ext_err' => mb_substr($e->getMessage(), 0, 250)], ['envs_id' => $s['envs_id']]);
            return false;
        }
    }

    // ------------------------------------------------------------------ the wire

    /** A signed call: HMAC-SHA256 over method, path, time, nonce and the body's SHA-256 (phai-monrak src/Api.php). */
    private static function call(string $method, string $path, ?array $body = null): array
    {
        $s = self::settings();
        if (empty($s['env_remote_id']) || empty($s['env_remote_key'])) {
            throw new \RuntimeException(t('esign.remote_not_ready'));
        }
        $json = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $time = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $base = rtrim((string) $s['env_remote_url'], '/');
        // the path is signed as the service sees it under its own address: /api/v1/... with the query
        $sign = hash_hmac('sha256', implode("\n", [$method, $path, $time, $nonce, hash('sha256', $json)]), (string) $s['env_remote_key']);
        return self::http($method, $base . $path, $body === null ? null : $json, [
            'X-Phai-Server: ' . $s['env_remote_id'], "X-Phai-Time: $time", "X-Phai-Nonce: $nonce", "X-Phai-Sign: $sign",
        ]);
    }

    /** PHP's own HTTP streams (no curl needed), certificates checked; any answer's body is read, errors too. */
    private static function http(string $method, string $url, ?string $body, array $headers): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => $method, 'header' => implode("\r\n", array_merge(['Content-Type: application/json', 'Accept: application/json', 'Connection: close'], $headers)),
                'content' => $body ?? '', 'timeout' => 60, 'ignore_errors' => true, 'follow_location' => 0, 'protocol_version' => 1.1,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $err = '';
        set_error_handler(function (int $no, string $msg) use (&$err): bool {
            $err = preg_replace('/^.*?\): /', '', $msg);
            return true;
        });
        try {
            $raw = file_get_contents($url, false, $ctx);
        } finally {
            restore_error_handler();
        }
        // PHP 8.4 has the function; the old magic variable is deprecated from 8.5 (Phai Server)
        $head = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        $status = $head[0] ?? '';
        $code = preg_match('#^HTTP/\S+\s+(\d{3})#', $status, $m) ? (int) $m[1] : 0;
        if ($raw === false || $code === 0) {
            throw new \RuntimeException(t('esign.remote_failed', ['error' => $err ?: 'no answer']));
        }
        $d = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300 || !is_array($d)) {
            throw new \RuntimeException(t('esign.remote_failed', ['error' => "$code " . (is_array($d) ? ($d['error'] ?? '') : mb_substr((string) $raw, 0, 120))]));
        }
        return $d;
    }

    private static function needAdmin(): void
    {
        if (!Auth::isAdmin()) {
            throw new HttpException(403, t('auth.forbidden'));
        }
    }
}
