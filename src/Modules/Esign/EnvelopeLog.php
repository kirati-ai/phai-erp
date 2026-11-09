<?php

declare(strict_types=1);

namespace Phai\Modules\Esign;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\Request;

/**
 * What happened to an envelope (envh_hist), as a hash chain: every row's hash covers its own fields and the hash of
 * the row before it, and the envelope keeps the hash of the newest row (env_mstr.env_chain). Rows are only ever
 * added; changing or deleting one breaks the chain from that row on, which check() reports.
 */
final class EnvelopeLog
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** While set ([] = the machine itself), events without their own $by are not put down to the signed-in user (EnvelopeRemote::sync). */
    public static ?array $as = null;

    /**
     * Add an event; returns its hash. Call inside the transaction that made the change. $by is for what did not come
     * from the person at this page: an outsider through Phai Sign ({ip, agent} as the service saw them) or the
     * machine itself ([]), so neither is put down to whoever happened to load the page that pulled it in.
     */
    public static function add(string $nbr, string $event, ?int $signer = null, array $detail = [], ?array $by = null): string
    {
        $by ??= self::$as;
        $db = App::db();
        $prev = (string) ($db->value('SELECT env_chain FROM env_mstr WHERE env_nbr = ? FOR UPDATE', [$nbr]) ?? '');
        $prev = $prev !== '' ? $prev : self::GENESIS;
        $req = Request::current();
        $row = [
            'envh_nbr' => $nbr, 'envh_at' => date('Y-m-d H:i:s'), 'envh_event' => $event, 'envh_signer' => $signer,
            'envh_userid' => $by === null ? Auth::userId() : null,
            'envh_ip' => $by === null ? $req?->ip : (isset($by['ip']) ? mb_substr((string) $by['ip'], 0, 45) : null),
            'envh_agent' => $by === null ? self::agent() : (isset($by['agent']) ? mb_substr((string) $by['agent'], 0, 255) : null),
            'envh_detail' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'envh_prev' => $prev,
        ];
        $row['envh_hash'] = self::hash($row);
        $db->insert('envh_hist', $row + ['created_by' => Auth::userId(), 'updated_by' => Auth::userId()]);
        $db->run('UPDATE env_mstr SET env_chain = ? WHERE env_nbr = ?', [$row['envh_hash'], $nbr]);
        return $row['envh_hash'];
    }

    /** Every event of an envelope, oldest first, with the signer's name. */
    public static function all(string $nbr): array
    {
        return App::db()->all(
            'SELECT h.*, s.envs_name, u.us_name FROM envh_hist h LEFT JOIN envs_det s ON s.envs_id = h.envh_signer
               LEFT JOIN us_mstr u ON u.us_userid = h.envh_userid WHERE h.envh_nbr = ? ORDER BY h.envh_id',
            [$nbr]
        );
    }

    /** Is the chain whole, from the first event to env_chain? Returns the id of the first broken row, or null. */
    public static function check(string $nbr): ?int
    {
        $prev = self::GENESIS;
        foreach (self::all($nbr) as $r) {
            $row = array_intersect_key($r, array_flip(['envh_nbr', 'envh_at', 'envh_event', 'envh_signer', 'envh_userid', 'envh_ip', 'envh_agent', 'envh_detail', 'envh_prev']));
            if ($r['envh_prev'] !== $prev || self::hash($row) !== $r['envh_hash']) {
                return (int) $r['envh_id'];
            }
            $prev = $r['envh_hash'];
        }
        $end = (string) (App::db()->value('SELECT env_chain FROM env_mstr WHERE env_nbr = ?', [$nbr]) ?? '');
        return $end === '' || $end === $prev ? null : -1;
    }

    public static function agent(): ?string
    {
        $ua = (string) (Request::current()?->headers['user-agent'] ?? '');
        return $ua !== '' ? mb_substr($ua, 0, 255) : null;
    }

    private static function hash(array $row): string
    {
        $signer = $row['envh_signer'] === null ? '' : (string) (int) $row['envh_signer'];
        return hash('sha256', implode("\x1f", [
            $row['envh_prev'], $row['envh_nbr'], $row['envh_at'], $row['envh_event'], $signer,
            (string) $row['envh_userid'], (string) $row['envh_ip'], (string) $row['envh_agent'], (string) $row['envh_detail'],
        ]));
    }
}
