<?php

declare(strict_types=1);

namespace Phai\Core\Db;

use InvalidArgumentException;
use PDO;
use PDOStatement;

/** Thin PDO wrapper. Values always go through prepared statements. */
final class Db
{
    public readonly PDO $pdo;
    private int $txDepth = 0;
    private array $columns = [];

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 3306,
            $config['name'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );
        $this->pdo = new PDO($dsn, $config['user'] ?? '', $config['pass'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $this->pdo->exec("SET time_zone = '" . date('P') . "'");
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function one(string $sql, array $params = []): ?array
    {
        return $this->run($sql, $params)->fetch() ?: null;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function insert(string $table, array $data): string
    {
        $cols = array_map([self::class, 'quote'], array_keys($data));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            self::quote($table),
            implode(', ', $cols),
            implode(', ', array_fill(0, count($data), '?'))
        );
        $this->run($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    /** @param array $where column => value, joined with AND */
    public function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(fn ($c) => self::quote($c) . ' = ?', array_keys($data)));
        [$whereSql, $whereParams] = self::where($where);
        $sql = sprintf('UPDATE %s SET %s WHERE %s', self::quote($table), $set, $whereSql);
        return $this->run($sql, [...array_values($data), ...$whereParams])->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        [$whereSql, $whereParams] = self::where($where);
        return $this->run(sprintf('DELETE FROM %s WHERE %s', self::quote($table), $whereSql), $whereParams)->rowCount();
    }

    /** Runs $fn inside a transaction. Nested calls join the outer transaction. */
    public function transaction(callable $fn): mixed
    {
        if ($this->txDepth++ === 0) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $fn($this);
            if (--$this->txDepth === 0) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if (--$this->txDepth === 0 && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Column names of a table, in table order. */
    public function columns(string $table): array
    {
        return $this->columns[$table] ??= array_column(
            $this->all('SHOW COLUMNS FROM ' . self::quote($table)),
            'Field'
        );
    }

    /**
     * Paged list from any query, same shape as Model::list.
     *
     *     $db->page('SELECT a.x, b.y', 'FROM a JOIN b ON ... WHERE a.z = ?', [$z], $request->query,
     *               ['x' => 'a.x', 'y' => 'b.y'], 'x');
     *
     * @param array $sortable  API sort name => SQL expression; anything else falls back to $default
     * @return array{rows: array, total: int, page: int, per_page: int}
     */
    public function page(string $select, string $from, array $params, array $opt, array $sortable, string $default, string $groupBy = ''): array
    {
        $gridFields = [];
        // Only business document/history tables: never expand credentials or system secrets.
        $publicTables = ['pr_mstr','po_mstr','rc_mstr','qt_mstr','so_mstr','sh_mstr',
            'ih_mstr','bl_mstr','ap_mstr','cr_mstr','vo_mstr','wo_mstr','pi_mstr','tr_hist','whtc_mstr','ld_det'];
        if (preg_match('/^FROM\s+([a-z0-9_]+)(?:\s+(?!WHERE\b|LEFT\b|JOIN\b|INNER\b)([a-z][a-z0-9_]*))?/i', trim($from), $base)
            && in_array($base[1], $publicTables, true) && $groupBy === '') {
            $alias = $base[2] ?? $base[1];
            $info = $this->columnInfo($base[1]);
            foreach ($info as $name => $column) {
                $gridFields[] = \Phai\Core\Crud\GridFields::describe($name, $column);
                if (!preg_match('/\b' . preg_quote($name, '/') . '\b/', $select)
                    && !preg_match('/(?:\b' . preg_quote($alias, '/') . '\.)?\*/', $select)) {
                    $select .= ', ' . self::quote($alias) . '.' . self::quote($name);
                }
                $sortable[$name] ??= self::quote($alias) . '.' . self::quote($name);
            }
        }
        $sort = $sortable[$opt['sort'] ?? ''] ?? $sortable[$default];
        $dir = strtolower((string) ($opt['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
        $perPage = max(1, min(500, (int) ($opt['per_page'] ?? 25)));
        $page = max(1, (int) ($opt['page'] ?? 1));
        $offset = ($page - 1) * $perPage;
        $group = $groupBy !== '' ? " GROUP BY $groupBy" : '';

        // Include every selected field for aggregate/report grids as well.
        $metadata = $this->run("$select $from$group LIMIT 0", $params);
        $knownFields = array_column($gridFields, 'key');
        $tables = [];
        $tableInfo = [];
        preg_match_all('/(?:FROM|JOIN)\s+([a-z0-9_]+)(?:\s+(?!WHERE\b|LEFT\b|JOIN\b|INNER\b|ON\b)([a-z][a-z0-9_]*))?/i', $from, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) $tables[$match[2] ?? $match[1]] = $match[1];
        for ($i = 0; $i < $metadata->columnCount(); $i++) {
            $meta = $metadata->getColumnMeta($i);
            if (in_array($meta['name'], $knownFields, true)) continue;
            $tableName = $tables[$meta['table'] ?? ''] ?? null;
            if ($tableName && !isset($tableInfo[$tableName])) $tableInfo[$tableName] = $this->columnInfo($tableName);
            $column = $tableName ? ($tableInfo[$tableName][$meta['name']] ?? []) : [];
            $gridFields[] = \Phai\Core\Crud\GridFields::describe($meta['name'], $column);
        }

        // Search the public result fields before pagination, including joined and computed fields; and/or keep one month
        // of a date field (month=YYYY-MM, month_key=<field>: a document list shows a month at a time).
        $search = trim((string) ($opt['grid_search'] ?? ''));
        $month = (string) ($opt['month'] ?? '');
        $monthKey = (string) ($opt['month_key'] ?? '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month) || !preg_match('/^[a-z0-9_]+$/D', $monthKey)) {
            $month = '';
        }
        if ($search !== '' || $month !== '') {
            $query = "$select $from$group";
            $metadata = $this->run("SELECT * FROM ($query) grid_fields LIMIT 0", $params);
            $names = [];
            for ($i = 0; $i < $metadata->columnCount(); $i++) {
                $names[] = $metadata->getColumnMeta($i)['name'];
            }
            $conds = [];
            $searchParams = $params;
            if ($search !== '') {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $conds[] = '(' . implode(' OR ', array_map(fn ($name) => 'CAST(' . self::quote($name) . ' AS CHAR) LIKE ?', $names)) . ')';
                array_push($searchParams, ...array_fill(0, count($names), $like));
            }
            if ($month !== '' && in_array($monthKey, $names, true)) {
                $conds[] = self::quote($monthKey) . ' BETWEEN ? AND ?';
                array_push($searchParams, $month . '-01', date('Y-m-t', strtotime($month . '-01')) . ' 23:59:59');
            }
            $filtered = "FROM ($query) grid_rows" . ($conds ? ' WHERE ' . implode(' AND ', $conds) : '');
            $orderName = in_array($opt['sort'] ?? '', $names, true) ? $opt['sort']
                : (in_array($default, $names, true) ? $default : $names[0]);
            return [
                'rows' => $this->all('SELECT * ' . $filtered . ' ORDER BY ' . self::quote($orderName) . " $dir LIMIT $perPage OFFSET $offset", $searchParams),
                'total' => (int) $this->value("SELECT COUNT(*) $filtered", $searchParams),
                'page' => $page, 'per_page' => $perPage, 'fields' => $gridFields,
            ];
        }

        $total = $groupBy !== ''
            ? (int) $this->value("SELECT COUNT(*) FROM (SELECT 1 $from$group) t", $params)
            : (int) $this->value("SELECT COUNT(*) $from", $params);

        return [
            'rows'     => $this->all("$select $from$group ORDER BY $sort $dir LIMIT $perPage OFFSET $offset", $params),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'fields' => $gridFields,
        ];
    }

    /**
     * Full column definitions, keyed by name:
     * ['Field', 'Type', 'Null' (YES|NO), 'Key', 'Default', 'Extra', 'Comment', ...]
     */
    public function columnInfo(string $table): array
    {
        $rows = $this->all('SHOW FULL COLUMNS FROM ' . self::quote($table));
        return array_column($rows, null, 'Field');
    }

    /** @return array{0: string, 1: array} */
    public static function where(array $where): array
    {
        if (!$where) {
            throw new InvalidArgumentException('Empty WHERE');
        }
        $sql = implode(' AND ', array_map(fn ($c) => self::quote($c) . ' = ?', array_keys($where)));
        return [$sql, array_values($where)];
    }

    /** Quote a table or column name. Only letters, digits and _ are allowed. */
    public static function quote(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException("Bad identifier: $name");
        }
        return "`$name`";
    }
}
