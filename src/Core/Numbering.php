<?php

declare(strict_types=1);

namespace Phai\Core;

use Phai\Core\Audit\Audit;
use Phai\Core\Db\Db;

/**
 * Document numbers from the _ctrl tables: prefix + (year/month) + running number.
 *
 *     $nbr = Numbering::next('PO');   // call inside the transaction that saves the document
 *
 * Each document has a format (<stem>_format) — see FORMATS. Formats with a year or month
 * start the running number at 1 again in each new period (<stem>_period remembers which).
 */
final class Numbering
{
    /** Digits of the old fixed format (N6). */
    public const DIGITS = 6;

    /** format => [date part (PHP date()), digits, period key (PHP date()) or '' for never]. */
    public const FORMATS = [
        'YM4' => ['Ym', 4, 'Ym'],   // PO2026100001, from 0001 every month
        'yM4' => ['ym', 4, 'Ym'],   // PO26100001
        'Y5'  => ['Y', 5, 'Y'],     // PO202600001, from 00001 every year
        'N6'  => ['', 6, ''],       // PO000001, never starts again
    ];

    /** Document => [ctrl table, column stem]. Columns are <stem>_prefix, _next_nbr, _format, _period. */
    public const DOCS = [
        'PR'  => ['po_ctrl', 'po_pr'],
        'PO'  => ['po_ctrl', 'po'],
        'RC'  => ['po_ctrl', 'po_rc'],
        'RT'  => ['po_ctrl', 'po_rt'],
        'QT'  => ['so_ctrl', 'so_qt'],
        'SO'  => ['so_ctrl', 'so'],
        'SH'  => ['so_ctrl', 'so_sh'],
        'SR'  => ['so_ctrl', 'so_sr'],
        'INV' => ['so_ctrl', 'so_inv'],
        'CN'  => ['so_ctrl', 'so_cn'],
        'DN'  => ['so_ctrl', 'so_dn'],
        'BL'  => ['ar_ctrl', 'bl'],
        'CR'  => ['ar_ctrl', 'cr'],
        'DP'  => ['ar_ctrl', 'dp'],
        'AP'  => ['ap_ctrl', 'ap'],
        'VO'  => ['ap_ctrl', 'vo'],
        'WHT' => ['ap_ctrl', 'whtc'],
        'PV'  => ['ap_ctrl', 'pay'],
        'JE'  => ['gl_ctrl', 'gl_je'],
        'PC'  => ['gl_ctrl', 'gl_pc'],
        'IC'  => ['ic_ctrl', 'ic_tr'],
        'TO'  => ['ic_ctrl', 'ic_to'],
        'PI'  => ['ic_ctrl', 'ic_pi'],
        'WO'  => ['wo_ctrl', 'wo'],
        'QC'  => ['wo_ctrl', 'qc'],
        'MI'  => ['wo_ctrl', 'wo_mi'],
    ];

    /**
     * Take the next number. Locks the control row so two users never get the same one.
     * $date: the document's own date (YYYY-MM-DD) — a format with a year/month takes them from it, so a PO dated in
     * August is PO202608…, even when it is keyed in October. The counter of _ctrl serves the latest period; a document
     * of another period continues after the highest number already used for that period.
     */
    public static function next(string $doc, ?string $date = null): string
    {
        $own = self::typed($doc);
        if ($own !== null) {
            return $own;
        }
        [$t, $c] = self::columns($doc);
        $time = $date && strtotime($date) !== false ? strtotime($date) : time();
        return App::db()->transaction(function (Db $db) use ($t, $c, $doc, $time) {
            $row = $db->one("SELECT {$c['next']} AS n, {$c['prefix']} AS p, {$c['format']} AS f, {$c['period']} AS per FROM $t WHERE ctrl_id = 1 FOR UPDATE");
            $format = self::format((string) ($row['f'] ?? 'N6'));
            $prefix = (string) ($row['p'] ?? '');
            $period = self::period($format, $time);
            $stored = (string) ($row['per'] ?? '');
            $n = $period === '' || $stored === $period ? max(1, (int) ($row['n'] ?? 1)) : 1;
            // never below what is already used under this stem (another period's documents, or a number typed by hand)
            if (isset(self::KEYS[$doc])) {
                [$table, $col] = self::KEYS[$doc];
                $stem = self::make($prefix, $format, 0, $time);
                $stem = substr($stem, 0, strlen($stem) - self::FORMATS[$format][1]);
                $used = (string) $db->value(
                    'SELECT MAX(' . Db::quote($col) . ') FROM ' . Db::quote($table) . ' WHERE ' . Db::quote($col) . ' LIKE ? AND CHAR_LENGTH(' . Db::quote($col) . ') = ?',
                    [addcslashes($stem, '%_\\') . '%', strlen($stem) + self::FORMATS[$format][1]]
                );
                if ($used !== '' && ctype_digit($tail = substr($used, strlen($stem)))) {
                    $n = max($n, (int) $tail + 1);
                }
            }
            // the counter follows the latest period; an older period's document leaves it alone
            if ($period === '' || $stored === '' || $period >= $stored) {
                $db->run("UPDATE $t SET {$c['next']} = ?, {$c['period']} = ? WHERE ctrl_id = 1", [$n + 1, $period !== '' ? $period : null]);
            }
            return self::make($prefix, $format, $n, $time);
        });
    }

    /**
     * The number the next saved document will get, without taking it (shown on a new document's
     * page). Someone saving first gets it; the document then takes the next one when saved.
     */
    public static function peek(string $doc): string
    {
        foreach (self::all() as $d) {
            if ($d['doc'] === $doc) {
                return self::make($d['prefix'], $d['format'], $d['next']);
            }
        }
        throw new \InvalidArgumentException("Unknown document $doc");
    }

    /** Where each document's number lives, to check that a number typed by hand is not taken. */
    public const KEYS = [
        'PR'  => ['pr_mstr', 'pr_nbr'],
        'PO'  => ['po_mstr', 'po_nbr'],
        'QT'  => ['qt_mstr', 'qt_nbr'],
        'SO'  => ['so_mstr', 'so_nbr'],
        'INV' => ['ih_mstr', 'ih_inv_nbr'],
        'CN'  => ['ih_mstr', 'ih_inv_nbr'],
        'DN'  => ['ih_mstr', 'ih_inv_nbr'],
        'RC'  => ['rc_mstr', 'rc_nbr'],
        'RT'  => ['rt_mstr', 'rt_nbr'],
        'SR'  => ['sr_mstr', 'sr_nbr'],
        'SH'  => ['sh_mstr', 'sh_nbr'],
        'BL'  => ['bl_mstr', 'bl_nbr'],
        'WO'  => ['wo_mstr', 'wo_nbr'],
        'TO'  => ['to_mstr', 'to_nbr'],
        'IC'  => ['tr_hist', 'tr_nbr'],
        'CR'  => ['cr_mstr', 'cr_nbr'],
        'DP'  => ['dp_mstr', 'dp_nbr'],
        'VO'  => ['apv_mstr', 'apv_nbr'], // ใบสำคัญจ่าย
        'PV'  => ['vo_mstr', 'vo_nbr'],   // จ่ายชำระ (the table is vo_mstr, the series PV)
        'AP'  => ['ap_mstr', 'ap_ref'],
        'JE'  => ['gl_trhist', 'gl_ref'],
        'QC'  => ['qc_hist', 'qc_test_nbr'],
        'MI'  => ['wis_mstr', 'wis_nbr'],
        'PI'  => ['pi_mstr', 'pi_batch'],
        'WHT' => ['wht_cert', 'whtc_nbr'],
        'PC'  => ['cb_mstr', 'cb_nbr'],   // เงินสดย่อย
    ];

    private static bool $typedUsed = false;

    /**
     * A number the user typed on a new document page (body doc_nbr, for doc_type e.g. "PO" or "INV,CN"):
     * used once per request, instead of the running number, when it differs from the number on offer.
     * Left as offered, the running number is taken as usual (someone may have saved first).
     */
    private static function typed(string $doc): ?string
    {
        $body = Http\Request::current()?->body ?? [];
        $wanted = strtoupper(trim((string) ($body['doc_nbr'] ?? '')));
        if (self::$typedUsed || $wanted === '' || !isset(self::KEYS[$doc])) {
            return null;
        }
        $types = (string) ($body['doc_type'] ?? '');
        if ($types !== '' && !in_array($doc, explode(',', $types), true)) {
            return null;
        }
        if ($wanted === self::peek($doc)) {
            return null;
        }
        if (!preg_match('/^[A-Z0-9][A-Z0-9._\/-]{0,49}$/D', $wanted)) {
            throw Http\HttpException::invalid(['doc_nbr' => t('validation.nbr_format')]);
        }
        [$table, $column] = self::KEYS[$doc];
        if (App::db()->value('SELECT 1 FROM ' . Db::quote($table) . ' WHERE ' . Db::quote($column) . ' = ?', [$wanted])) {
            throw Http\HttpException::invalid(['doc_nbr' => t('validation.nbr_taken')]);
        }
        self::$typedUsed = true;
        return $wanted;
    }

    /**
     * Give a deleted document's number back so the next document takes it again — only the highest number of its
     * period can come back (a gap in the middle stays a gap). False when a later number exists. Call inside the
     * transaction that deletes the document, before the row is gone.
     */
    public static function giveBack(string $doc, string $nbr, string $date): bool
    {
        [$t, $c] = self::columns($doc);
        [$table, $col] = self::KEYS[$doc];
        $db = App::db();
        $time = strtotime($date) ?: time();
        $row = $db->one("SELECT {$c['next']} AS n, {$c['prefix']} AS p, {$c['format']} AS f, {$c['period']} AS per FROM $t WHERE ctrl_id = 1 FOR UPDATE");
        $format = self::format((string) ($row['f'] ?? 'N6'));
        $digits = self::FORMATS[$format][1];
        $stem = substr(self::make((string) ($row['p'] ?? ''), $format, 0, $time), 0, -$digits);
        $tail = substr($nbr, strlen($stem));
        if (!str_starts_with($nbr, $stem) || strlen($tail) !== $digits || !ctype_digit($tail)) {
            return true; // a number typed by hand: nothing to give back, nothing in the way
        }
        $later = $db->value(
            'SELECT 1 FROM ' . Db::quote($table) . ' WHERE ' . Db::quote($col) . ' LIKE ? AND CHAR_LENGTH(' . Db::quote($col) . ') = ? AND '
                . Db::quote($col) . ' > ? LIMIT 1',
            [addcslashes($stem, '%_\\') . '%', strlen($nbr), $nbr]
        );
        if ($later) {
            return false;
        }
        $period = self::period($format, $time);
        if ((int) $row['n'] === (int) $tail + 1 && ($period === '' || (string) $row['per'] === $period)) {
            $db->run("UPDATE $t SET {$c['next']} = ? WHERE ctrl_id = 1", [(int) $tail]);
        }
        return true;
    }

    /** "PO" + YM4 + 12 -> PO2026100012 (for the current date). */
    public static function make(string $prefix, string $format, int $n, ?int $time = null): string
    {
        [$datePart, $digits] = self::FORMATS[self::format($format)];
        return $prefix . ($datePart !== '' ? date($datePart, $time ?? time()) : '') . str_pad((string) $n, $digits, '0', STR_PAD_LEFT);
    }

    /** @return list<array{doc: string, prefix: string, next: int, format: string}> next = the number the next document gets */
    public static function all(): array
    {
        $db = App::db();
        $out = [];
        foreach (array_keys(self::DOCS) as $doc) {
            [$t, $c] = self::columns($doc);
            $row = $db->one("SELECT {$c['prefix']} AS p, {$c['next']} AS n, {$c['format']} AS f, {$c['period']} AS per FROM $t WHERE ctrl_id = 1") ?? [];
            $format = self::format((string) ($row['f'] ?? 'N6'));
            $period = self::period($format);
            $next = $period !== '' && (string) ($row['per'] ?? '') !== $period ? 1 : max(1, (int) ($row['n'] ?? 1));
            $out[] = ['doc' => $doc, 'prefix' => (string) ($row['p'] ?? ''), 'next' => $next, 'format' => $format];
        }
        return $out;
    }

    /** Save prefix, format and next number (of the current period) of one document. */
    public static function set(string $doc, string $prefix, int $next, string $format = 'N6'): void
    {
        [$t, $c, $stem, $table] = self::columns($doc);
        $format = self::format($format);
        $old = App::db()->one("SELECT {$c['prefix']}, {$c['next']}, {$c['format']} FROM $t WHERE ctrl_id = 1") ?? [];
        $new = [$stem . '_prefix' => $prefix, $stem . '_next_nbr' => $next, $stem . '_format' => $format, $stem . '_period' => self::period($format) ?: null];
        App::db()->update($table, $new, ['ctrl_id' => 1]);
        Audit::updated($table, 'ctrl_id=1', $old, $new);
    }

    private static function format(string $format): string
    {
        return isset(self::FORMATS[$format]) ? $format : 'N6';
    }

    /** Period a running number belongs to (now, or at $time): '202610', '2026' or '' (never resets). */
    private static function period(string $format, ?int $time = null): string
    {
        $key = self::FORMATS[$format][2];
        return $key !== '' ? date($key, $time ?? time()) : '';
    }

    /** @return array{0: string, 1: array<string, string>, 2: string, 3: string} quoted table, quoted columns, stem, table */
    private static function columns(string $doc): array
    {
        [$table, $stem] = self::DOCS[$doc] ?? throw new \InvalidArgumentException("Unknown document $doc");
        $cols = [];
        foreach (['prefix', 'next' => 'next_nbr', 'format', 'period'] as $key => $suffix) {
            $cols[is_int($key) ? $suffix : $key] = Db::quote($stem . '_' . $suffix);
        }
        return [Db::quote($table), $cols, $stem, $table];
    }
}
