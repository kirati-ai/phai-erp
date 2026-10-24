<?php

declare(strict_types=1);

namespace Phai\Core\Crud;

use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\View\View;

/** Handles the page and API of every Resource. The router passes the resource class in. */
final class CrudController
{
    /** @param class-string<Resource> $resource */
    public function __construct(private readonly string $resource)
    {
    }

    public function page(): string
    {
        $r = $this->resource;
        $code = $r::CODE;
        return View::render('crud/index', [
            'title'   => $r::title(),
            'help'    => str_replace('/', '-', ltrim($r::path(), '/')), // views/help/<lang>/<page path>.php when there is one
            'r'       => $r,
            'fields'  => $r::fields(),
            'can'     => [
                'add'    => Auth::can($code, 'add') && !$r::isSingle(),
                'edit'   => Auth::can($code, 'edit') && !$r::isLocked(),
                'delete' => Auth::can($code, 'delete') && !$r::isSingle() && !$r::isLocked(),
            ],
            'print'   => $r::printPath() !== null && Auth::can($code, 'print') ? $r::printPath() : null,
            'scripts' => array_values(array_filter(['js/crud.js', $r::aside()[1]])),
        ]);
    }

    public function list(Request $request): array
    {
        return ($this->resource)::list($request->query);
    }

    public function show(Request $request): array
    {
        $r = $this->resource;
        return $r::findOrFail($r::keyFrom($request->query));
    }

    public function create(Request $request): array
    {
        return $this->insert(($this->resource)::validate($request->body, true));
    }

    private function insert(array $data): array
    {
        $r = $this->resource;
        return App::db()->transaction(function () use ($r, $data) {
            if ($r::autoNumber() !== null && empty($data[$r::keyColumns()[0]])) {
                $data[$r::keyColumns()[0]] = Numbering::next($r::autoNumber(), $r::numberDate() ? ($data[$r::numberDate()] ?? null) : null);
            }
            return $r::create($data);
        });
    }

    /**
     * Rows from an Excel sheet: {rows: [{_row: 5, column: value, ...}]}. A row whose key exists is
     * updated (edit permission), others are added (add permission). All rows or none: any error
     * answers 422 with fields "Row 5 · Label" => message and nothing is saved.
     */
    #[Action('view')]
    public function import(Request $request): array
    {
        $r = $this->resource;
        if ($r::isSingle() || (!Auth::can($r::CODE, 'add') && !Auth::can($r::CODE, 'edit'))) {
            throw new HttpException(403, t('auth.forbidden'));
        }
        $rows = $request->body['rows'] ?? null;
        if (!is_array($rows) || !$rows) {
            throw new HttpException(422, t('js.import_empty'));
        }
        if (count($rows) > self::IMPORT_MAX) {
            throw new HttpException(422, t('js.import_too_many', ['max' => self::IMPORT_MAX]));
        }

        $fields = array_column($r::fields(), null, 'name');
        $errors = [];
        $fail = function (int $line, string $field, string $message) use (&$errors, $fields) {
            if (count($errors) < 100) {
                $errors[trim(t('js.row') . " $line · " . ($fields[$field]['label'] ?? $field), ' ·')] = $message;
            }
        };

        return App::db()->transaction(function () use ($r, $rows, $fields, &$errors, $fail) {
            $created = $updated = 0;
            foreach (array_values($rows) as $i => $row) {
                $line = is_array($row) && is_int($row['_row'] ?? null) ? $row['_row'] : $i + 2;
                if (!is_array($row)) {
                    continue;
                }
                $data = self::fromSheet($row, $fields);
                $key = [];
                foreach ($r::keyColumns() as $col) {
                    $v = trim((string) ($data[$col] ?? ''));
                    $key[$col] = ($fields[$col]['upper'] ?? false) ? mb_strtoupper($v) : $v;
                }
                $exists = !in_array('', $key, true) && $r::find($key) !== null;
                $action = $exists ? 'edit' : 'add';
                if ($exists && $r::isLocked()) {
                    $fail($line, $r::keyColumns()[0], t('common.locked'));
                    continue;
                }
                if (!Auth::can($r::CODE, $action)) {
                    $fail($line, $r::keyColumns()[0], t('auth.forbidden'));
                    continue;
                }
                try {
                    if ($exists) {
                        $r::update($key, $r::validate($data, false));
                        $updated++;
                    } else {
                        $this->insert($r::validate($data, true));
                        $created++;
                    }
                } catch (HttpException $e) {
                    foreach ($e->fields ?: ['' => $e->getMessage()] as $field => $message) {
                        $fail($line, (string) $field, $message);
                    }
                }
            }
            if ($errors) {
                throw new HttpException(422, t('js.import_errors'), $errors);
            }
            return ['created' => $created, 'updated' => $updated];
        });
    }

    private const IMPORT_MAX = 5000;

    /** @param class-string<Resource> $r */
    private static function unlocked(string $r): void
    {
        if ($r::isLocked()) {
            throw new HttpException(409, t('common.locked'));
        }
    }

    /** Excel values to what the form would send: serial dates, active/inactive words, option labels. */
    private static function fromSheet(array $row, array $fields): array
    {
        $out = [];
        foreach ($fields as $name => $f) {
            if (!array_key_exists($name, $row)) {
                continue;
            }
            $v = $row[$name];
            if (is_string($v)) {
                $v = trim($v);
            }
            if ($v === null || $v === '') {
                $out[$name] = $f['type'] === 'bool' ? 0 : '';
                continue;
            }
            switch ($f['type']) {
                case 'date':
                    if (is_int($v) || is_float($v)) {
                        $v = gmdate('Y-m-d', (int) round(($v - 25569) * 86400));
                    } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', (string) $v, $m)) {
                        $year = (int) $m[3] > 2400 ? (int) $m[3] - 543 : (int) $m[3];
                        $v = sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]);
                    }
                    break;
                case 'bool':
                    $word = mb_strtolower((string) $v);
                    $no = ['0', 'false', 'n', 'no', 'off', mb_strtolower(t('js.inactive')), 'inactive', 'ไม่ใช้งาน', 'ไม่', mb_strtolower(t('js.no')), 'ไม่ใช่'];
                    $v = $v === false || in_array($word, $no, true) ? 0 : 1;
                    break;
                case 'select':
                    $v = (string) $v;
                    $values = array_map('strval', array_column($f['options'], 'value'));
                    if (!in_array($v, $values, true)) {
                        foreach ($f['options'] as $o) {
                            if (mb_strtolower($o['label']) === mb_strtolower($v) || mb_strtolower((string) $o['value']) === mb_strtolower($v)) {
                                $v = (string) $o['value'];
                                break;
                            }
                        }
                        // a lookup shown as "CODE · Name"
                        if (!in_array($v, $values, true) && str_contains($v, ' · ')) {
                            $v = trim(explode(' · ', $v, 2)[0]);
                        }
                    }
                    break;
                case 'int':
                case 'number':
                    break;
                default:
                    // codes typed as numbers come back as numbers: 1001.0 -> "1001"
                    if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
                        $v = (string) (int) $v;
                    }
            }
            $out[$name] = is_bool($v) ? (int) $v : $v;
        }
        return $out;
    }

    public function update(Request $request): array
    {
        $r = $this->resource;
        self::unlocked($r);
        return $r::update($r::keyFrom($request->query), $r::validate($request->body, false));
    }

    public function delete(Request $request): null
    {
        $r = $this->resource;
        self::unlocked($r);
        $r::destroy($r::keyFrom($request->query));
        return null;
    }
}
