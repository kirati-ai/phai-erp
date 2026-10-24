<?php

declare(strict_types=1);

namespace Phai\Core\Audit;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\Request;

/** Writes changes to audit_log. Model calls this automatically on create, update and delete. */
final class Audit
{
    /** Columns that change on every save and are not worth logging. */
    public const SKIP = ['created_by', 'created_at', 'updated_by', 'updated_at'];

    public static function created(string $table, string $key, array $row): void
    {
        self::write($table, $key, 'CREATE', null, null, self::json($row));
    }

    public static function updated(string $table, string $key, array $old, array $new): void
    {
        foreach ($new as $field => $value) {
            if (in_array($field, self::SKIP, true) || !array_key_exists($field, $old)) {
                continue;
            }
            if (self::same($old[$field], $value)) {
                continue;
            }
            self::write($table, $key, 'UPDATE', $field, self::text($old[$field]), self::text($value));
        }
    }

    public static function deleted(string $table, string $key, array $row): void
    {
        self::write($table, $key, 'DELETE', null, self::json($row), null);
    }

    /** 'um_um=EA' or 'absd_id=A1,absd_line=2' */
    public static function key(array $key): string
    {
        return implode(',', array_map(fn ($c, $v) => "$c=$v", array_keys($key), $key));
    }

    public static function same(mixed $a, mixed $b): bool
    {
        // "1.50000" from the database equals 1.5 from input, but code "007" is not "7".
        if (is_numeric($a) && is_numeric($b) && !(is_string($a) && is_string($b))) {
            return (float) $a === (float) $b;
        }
        return self::text($a) === self::text($b);
    }

    private static function write(string $table, string $key, string $action, ?string $field, ?string $old, ?string $new): void
    {
        App::db()->insert('audit_log', [
            'log_table'   => $table,
            'log_key'     => mb_substr($key, 0, 255),
            'log_action'  => $action,
            'log_field'   => $field,
            'log_old_val' => $old,
            'log_new_val' => $new,
            'log_userid'  => Auth::userId() ?? 'system',
            'log_ip'      => Request::current()?->ip,
        ]);
    }

    private static function text(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? '1' : '0',
            default         => (string) $value,
        };
    }

    private static function json(array $row): string
    {
        return json_encode(array_diff_key($row, array_flip(self::SKIP)), JSON_UNESCAPED_UNICODE);
    }
}
