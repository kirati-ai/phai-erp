<?php

declare(strict_types=1);

namespace Phai\Core\Db;

use PDOException;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;

/**
 * Base for table models. Rows are plain arrays.
 *
 *     final class Unit extends Model {
 *         protected static string $table = 'um_mstr';
 *         protected static array $key = ['um_um'];
 *         protected static array $search = ['um_um', 'um_desc'];
 *     }
 *
 *     Unit::find('EA');  Unit::list(['q' => 'box']);
 *     Unit::create([...]);  Unit::update('EA', [...]);  Unit::delete('EA');
 *
 * Every create / update / delete is written to audit_log in the same transaction.
 */
abstract class Model
{
    protected static string $table;
    /** Primary key columns. */
    protected static array $key;
    /** Columns searched by list(['q' => ...]). */
    protected static array $search = [];
    /** Default ORDER BY column. */
    protected static ?string $order = null;
    protected static bool $audit = true;
    /** Columns never sent to the browser and masked in audit_log (e.g. password hashes). */
    protected static array $hidden = [];
    /** Unique index name => column, so a duplicate error points at the right field. */
    protected static array $unique = [];

    public static function table(): string
    {
        return static::$table;
    }

    public static function columns(): array
    {
        return App::db()->columns(static::$table);
    }

    /** @param scalar|array $key one value, or column => value for composite keys */
    public static function find(mixed $key): ?array
    {
        $row = static::findRaw($key);
        return $row ? static::hide($row) : null;
    }

    /** Row including hidden columns — for server-side use only. */
    protected static function findRaw(mixed $key): ?array
    {
        [$sql, $params] = Db::where(static::keyWhere($key));
        return App::db()->one('SELECT * FROM ' . Db::quote(static::$table) . " WHERE $sql", $params);
    }

    public static function findOrFail(mixed $key): array
    {
        return static::find($key) ?? throw HttpException::notFound();
    }

    /**
     * Paged list for tables on screen.
     * Options: q (search text), sort (column), dir (asc|desc), page, per_page, where (column => value).
     *
     * @return array{rows: array, total: int, page: int, per_page: int}
     */
    public static function list(array $opt = []): array
    {
        $db = App::db();
        $columns = static::columns();
        $conds = [];
        $params = [];

        foreach ($opt['where'] ?? [] as $col => $value) {
            if (in_array($col, $columns, true)) {
                $conds[] = Db::quote($col) . ' = ?';
                $params[] = $value;
            }
        }

        foreach ($opt['whereAny'] ?? [] as $col => $values) {
            if (!in_array($col, $columns, true) || !$values) continue;
            $any = [];
            foreach ($values as $value) {
                $any[] = Db::quote($col) . ($value === null ? ' IS NULL' : ' = ?');
                if ($value !== null) $params[] = $value;
            }
            $conds[] = '(' . implode(' OR ', $any) . ')';
        }

        $q = trim((string) ($opt['grid_search'] ?? $opt['q'] ?? ''));
        $searchColumns = array_values(array_diff($columns, static::$hidden));
        if ($q !== '' && $searchColumns) {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $conds[] = '(' . implode(' OR ', array_map(fn ($c) => Db::quote($c) . ' LIKE ?', $searchColumns)) . ')';
            array_push($params, ...array_fill(0, count($searchColumns), $like));
        }

        $whereSql = $conds ? ' WHERE ' . implode(' AND ', $conds) : '';
        $from = ' FROM ' . Db::quote(static::$table) . $whereSql;

        $sort = in_array($opt['sort'] ?? null, $columns, true) ? $opt['sort'] : (static::$order ?? static::$key[0]);
        $dir = strtolower((string) ($opt['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $perPage = max(1, min(500, (int) ($opt['per_page'] ?? 25)));
        $page = max(1, (int) ($opt['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $fieldInfo = $db->columnInfo(static::$table);
        return [
            'rows'     => array_map([static::class, 'hide'], $db->all("SELECT *$from ORDER BY " . Db::quote($sort) . " $dir LIMIT $perPage OFFSET $offset", $params)),
            'total'    => (int) $db->value("SELECT COUNT(*)$from", $params),
            'page'     => $page,
            'per_page' => $perPage,
            'fields' => array_map(fn ($name) => \Phai\Core\Crud\GridFields::describe($name, $fieldInfo[$name] ?? []), array_values(array_diff($columns, static::$hidden))),
        ];
    }

    public static function create(array|object $data): array
    {
        $data = static::only($data);
        $now = date('Y-m-d H:i:s');
        $user = Auth::userId();
        $data += array_intersect_key(
            ['created_by' => $user, 'created_at' => $now, 'updated_by' => $user, 'updated_at' => $now],
            array_flip(static::columns())
        );

        return App::db()->transaction(function (Db $db) use ($data) {
            try {
                $id = $db->insert(static::$table, $data);
            } catch (PDOException $e) {
                throw static::friendly($e);
            }
            // Auto-increment tables get their key from the insert.
            $key = array_intersect_key($data, array_flip(static::$key));
            if (count($key) < count(static::$key) && count(static::$key) === 1) {
                $key = [static::$key[0] => $id];
            }
            $row = static::findRaw($key);
            if (static::$audit) {
                Audit::created(static::$table, Audit::key($key), static::mask($row));
            }
            return static::hide($row);
        });
    }

    public static function update(mixed $key, array|object $data): array
    {
        $where = static::keyWhere($key);
        $data = array_diff_key(static::only($data), array_flip([...static::$key, 'created_by', 'created_at']));

        return App::db()->transaction(function (Db $db) use ($key, $where, $data) {
            $old = static::findRaw($key) ?? throw HttpException::notFound();
            $changed = array_filter($data, fn ($v, $c) => !Audit::same($old[$c] ?? null, $v), ARRAY_FILTER_USE_BOTH);
            if (!$changed) {
                return static::hide($old);
            }
            $changed += array_intersect_key(
                ['updated_by' => Auth::userId(), 'updated_at' => date('Y-m-d H:i:s')],
                array_flip(static::columns())
            );
            try {
                $db->update(static::$table, $changed, $where);
            } catch (PDOException $e) {
                throw static::friendly($e);
            }
            if (static::$audit) {
                Audit::updated(static::$table, Audit::key($where), static::mask($old), static::mask($changed, $old));
            }
            return static::hide(static::findRaw($key));
        });
    }

    public static function delete(mixed $key): void
    {
        $where = static::keyWhere($key);
        App::db()->transaction(function (Db $db) use ($key, $where) {
            $old = static::findRaw($key) ?? throw HttpException::notFound();
            try {
                $db->delete(static::$table, $where);
            } catch (PDOException $e) {
                throw static::friendly($e);
            }
            if (static::$audit) {
                Audit::deleted(static::$table, Audit::key($where), static::mask($old));
            }
        });
    }

    public static function hide(array $row): array
    {
        return array_diff_key($row, array_flip(static::$hidden));
    }

    /**
     * Replace hidden values with *** for audit_log. For an update pass $old too:
     * a changed hidden value becomes '*** (new)' so the change is still logged.
     */
    protected static function mask(array $row, ?array $old = null): array
    {
        foreach (static::$hidden as $col) {
            if (!isset($row[$col])) {
                continue;
            }
            $changed = $old !== null && ($old[$col] ?? null) !== $row[$col];
            $row[$col] = $changed ? '*** (new)' : '***';
        }
        return $row;
    }

    /** Keep only real columns of the table (input objects may carry extra fields). */
    protected static function only(array|object $data): array
    {
        $data = is_object($data) ? get_object_vars($data) : $data;
        $data = array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $data);
        return array_intersect_key($data, array_flip(static::columns()));
    }

    protected static function keyWhere(mixed $key): array
    {
        if (!is_array($key)) {
            if (count(static::$key) !== 1) {
                throw new \InvalidArgumentException(static::class . ' has a composite key; pass an array');
            }
            return [static::$key[0] => $key];
        }
        $where = [];
        foreach (static::$key as $col) {
            if (!array_key_exists($col, $key)) {
                throw new \InvalidArgumentException("Missing key column $col");
            }
            $where[$col] = $key[$col];
        }
        return $where;
    }

    private static function duplicateField(PDOException $e): string
    {
        preg_match("/for key '(?:\w+\.)?(\w+)'/", $e->getMessage(), $m);
        return static::$unique[$m[1] ?? ''] ?? static::$key[array_key_last(static::$key)];
    }

    /** Turn common MySQL errors into messages a user can act on. */
    protected static function friendly(PDOException $e): \Throwable
    {
        return match ($e->errorInfo[1] ?? null) {
            1062       => HttpException::invalid([static::duplicateField($e) => t('validation.duplicate')]),
            1451       => new HttpException(409, t('error.in_use')),
            1452       => new HttpException(422, t('error.bad_reference')),
            default    => $e,
        };
    }
}
