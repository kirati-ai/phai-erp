<?php

declare(strict_types=1);

namespace Phai\Core\Crud;

use Phai\Core\App;
use Phai\Core\Db\Db;
use Phai\Core\Db\Model;
use Phai\Core\Http\HttpException;
use Phai\Core\Lang;

/**
 * A standard data screen built from the table structure: list, search, add, edit, delete.
 * Put a class named *Resource in src/Modules/<Module>/ and the router adds the page and API:
 *
 *     GET /part-groups              page
 *     GET /api/part-groups          list   (?q= &sort= &dir= &page=)
 *     GET /api/part-groups/row      one row (?ptg_group=A)
 *     POST /api/part-groups         add
 *     PUT /api/part-groups/row      edit   (?ptg_group=A)
 *     DELETE /api/part-groups/row   delete (?ptg_group=A)
 *
 * Field types, lengths and required flags come from the database columns.
 * $form only adds what the database cannot say (layout, dropdowns, upper case).
 * Labels come from lang/<locale>/fields.php, else from the column comment.
 */
abstract class Resource extends Model
{
    /** Program code (permission) and default URL path. */
    public const CODE = '';
    /** URL path when it differs from '/' . CODE. */
    public const PATH = null;

    /** Columns shown in the list. */
    protected static array $list = [];

    /**
     * Figures the list shows that are not columns of the table: name => type (number, int, date, text).
     * The resource fills them in its own list(); put them in $list where they belong, label in fields.php.
     * A row may also carry `_link` (a page path): clicking it opens that page instead of the dialog.
     */
    protected static array $extra = [];

    /**
     * Form fields in order: column => options. Empty means every column.
     *   col      grid width 1-12 (default 6)
     *   section  tab name; fields without one go to the first tab
     *   lookup   Lookup name for a dropdown        options  fixed values for a dropdown
     *   upper    store in upper case               type     override: text, email, int, number, bool, date, textarea
     *   geo      province | district | subdistrict | zip (Thai address pickers)
     *   exists   [table, column] the value must exist there (for big tables where a dropdown does not fit)
     *   default  value for a new row ('today' = today's date, 'me' = the signed-in person's name)
     *   free     with pick: text that is not in the list may be kept (e.g. a name)
     *   required / readonly                        override what the database says
     */
    protected static array $form = [];

    /** Tables pointing at this record: table => column. Delete is refused while one does. */
    protected static array $usedBy = [];

    /** One-row table (co_mstr, gl_ctrl): the page is a form, not a list. */
    protected static bool $single = false;

    /** Document type in Numbering (e.g. 'QC'): a new row left without a key gets the next number. */
    protected static ?string $autoNumber = null;
    /** The row's date column the number takes its year/month from (e.g. 'qc_date'); none = today. */
    protected static ?string $numberDate = null;
    /** A status-like column shown as a badge beside the title when viewing a record, not as a field: [column => [value => color]] */
    protected static array $badge = [];

    /** Extra view shown above the form of a one-row screen, and its script (e.g. the company logo). */
    protected static ?string $aside = null;
    protected static ?string $asideScript = null;

    private const SYSTEM = ['created_by', 'created_at', 'updated_by', 'updated_at'];

    private static array $fields = [];

    public static function path(): string
    {
        return static::PATH ?? '/' . static::CODE;
    }

    public static function title(): string
    {
        $name = App::db()->value('SELECT prog_name FROM prog_mstr WHERE prog_code = ?', [static::CODE]);
        return $name === null ? static::CODE : Lang::name('programs', static::CODE, (string) $name); // lang/<locale>/programs.php
    }

    /** @return array{0: ?string, 1: ?string} view and script */
    public static function aside(): array
    {
        return [static::$aside, static::$asideScript];
    }

    /**
     * Dropdown filters beside the search box, sent to list() as query params; ?name= in the page URL picks one.
     * @return list<array{name: string, all: string, options: array<string, string>}>
     */
    public static function filters(): array
    {
        return [];
    }

    public static function autoNumber(): ?string
    {
        return static::$autoNumber;
    }

    public static function numberDate(): ?string
    {
        return static::$numberDate;
    }

    public static function isSingle(): bool
    {
        return static::$single;
    }

    /** A saved record is a document (e.g. QC): no edit and no delete here — what made it changes it. */
    protected static bool $locked = false;

    /** The print page of a record, {key} = its key (e.g. '/qc/{key}/print'); null = none. */
    protected static ?string $print = null;

    /** Files may be attached to a record (System\AttachmentController::DOCS code, e.g. 'VD'); null = none. */
    protected static ?string $files = null;

    public static function filesDoc(): ?string
    {
        return static::$files;
    }

    public static function isLocked(): bool
    {
        return static::$locked;
    }

    public static function printPath(): ?string
    {
        return static::$print;
    }

    public static function badge(): array
    {
        return static::$badge;
    }

    public static function keyColumns(): array
    {
        return static::$key;
    }

    /** @return array<string, string> computed list column => type */
    public static function extraColumns(): array
    {
        return static::$extra;
    }

    public static function listColumns(): array
    {
        return static::$list ?: array_slice(array_column(static::fields(), 'name'), 0, 4);
    }

    /** Every public database field may be selected in the list. */
    public static function gridFields(): array
    {
        $fields = array_column(static::fields(), null, 'name');
        foreach (App::db()->columnInfo(static::$table) as $name => $info) {
            if (in_array($name, static::$hidden, true)) {
                unset($fields[$name]);
                continue;
            }
            $fields[$name] ??= ['name' => $name, 'label' => self::label($name, (string) $info['Comment']),
                'type' => preg_match('/^(decimal|float|double)/', $info['Type']) ? 'number' : (preg_match('/^(tiny|small|medium|big)?int/', $info['Type']) ? 'int' : 'text'),
                'scale' => 5, 'options' => [], 'lookup' => null];
        }
        return array_values($fields);
    }

    /** Resolved form fields. */
    public static function fields(): array
    {
        return self::$fields[static::class] ??= static::buildFields();
    }

    /** Key values from the query string, e.g. ?loc_site=MAIN&loc_loc=A1. */
    public static function keyFrom(array $query): array
    {
        if (static::$single) {
            return [static::$key[0] => 1];
        }
        $key = [];
        foreach (static::$key as $col) {
            if (!isset($query[$col]) || !is_string($query[$col]) || $query[$col] === '') {
                throw HttpException::notFound();
            }
            $key[$col] = $query[$col];
        }
        return $key;
    }

    /**
     * Check and convert form input. Throws 422 with all field errors.
     * Only fields present in $data are returned, so an update can send just what changed.
     */
    public static function validate(array $data, bool $creating): array
    {
        $out = [];
        $errors = [];

        foreach (static::fields() as $f) {
            $name = $f['name'];
            if ($f['readonly'] || ($f['key'] && !$creating)) {
                continue;
            }
            // A field not sent at all is left alone: the column default on create, unchanged on update.
            if (!array_key_exists($name, $data)) {
                if ($creating && $f['required']) {
                    $errors[$name] = t('validation.required');
                }
                continue;
            }
            $v = $data[$name];
            if (is_string($v)) {
                $v = trim($v);
            }

            if ($f['type'] === 'bool') {
                $out[$name] = filter_var($v, FILTER_VALIDATE_BOOL) ? 1 : 0;
                continue;
            }
            if ($v === null || $v === '') {
                if ($f['required']) {
                    $errors[$name] = t('validation.required');
                } else {
                    // NOT NULL columns with a default take the default instead of null.
                    $out[$name] = $f['nullable'] ? null : $f['default'];
                }
                continue;
            }
            if (!is_scalar($v)) {
                $errors[$name] = t('validation.type');
                continue;
            }

            $v = (string) $v;
            $error = null;
            switch ($f['type']) {
                case 'int':
                    $v = str_replace(',', '', $v);
                    $error = preg_match('/^-?\d+$/', $v) ? null : t('validation.int');
                    $v = (int) $v;
                    break;
                case 'number':
                    $v = str_replace(',', '', $v);
                    $error = is_numeric($v) ? null : t('validation.number');
                    $v = is_numeric($v) ? round((float) $v, $f['scale']) : $v;
                    break;
                case 'date':
                    $d = \DateTime::createFromFormat('!Y-m-d', $v);
                    $error = $d && $d->format('Y-m-d') === $v ? null : t('validation.date');
                    break;
                case 'email':
                    $error = filter_var($v, FILTER_VALIDATE_EMAIL) ? null : t('validation.email');
                    break;
                case 'select':
                    $allowed = $f['lookup'] !== null
                        ? array_column(Lookup::options($f['lookup'], false), 'value')
                        : array_column($f['options'], 'value');
                    $error = in_array($v, array_map('strval', $allowed), true) ? null : t('validation.in');
                    break;
            }
            if ($error === null && $f['max'] !== null && mb_strlen((string) $v) > $f['max']) {
                $error = t('validation.max_length', ['max' => $f['max']]);
            }
            if ($f['upper'] && is_string($v)) {
                $v = mb_strtoupper($v);
            }
            if ($error === null && $f['exists'] !== null) {
                [$table, $column] = $f['exists'];
                $found = App::db()->value('SELECT 1 FROM ' . Db::quote($table) . ' WHERE ' . Db::quote($column) . ' = ?', [$v]);
                $error = $found ? null : t('validation.in');
            }

            if ($error !== null) {
                $errors[$name] = $error;
            } else {
                $out[$name] = $v;
            }
        }

        if ($errors) {
            throw HttpException::invalid($errors);
        }
        return $out;
    }

    /** Delete, unless another table still points at this record. */
    public static function destroy(array $key): void
    {
        $value = $key[static::$key[0]];
        foreach (static::$usedBy as $table => $columns) {
            foreach ((array) $columns as $column) { // a table may point at it from more than one column (BOM: parent, component)
                $used = App::db()->value(
                    'SELECT 1 FROM ' . Db::quote($table) . ' WHERE ' . Db::quote($column) . ' = ? LIMIT 1',
                    [$value]
                );
                if ($used) {
                    throw new HttpException(409, t('error.in_use'));
                }
            }
        }
        static::delete($key);
        if (static::$files !== null) {
            \Phai\Modules\System\Attachment::removeAll(static::$files, (string) $value); // the record's files go with it
        }
    }

    private static function buildFields(): array
    {
        $info = App::db()->columnInfo(static::$table);
        $form = static::$form ?: array_fill_keys(array_diff(array_keys($info), self::SYSTEM), []);
        $fields = [];

        foreach ($form as $name => $opt) {
            $col = $info[$name] ?? throw new \LogicException(static::class . ": no column $name");
            $type = strtolower($col['Type']);
            $f = [
                'name'     => $name,
                'label'    => $opt['label'] ?? self::label($name, (string) $col['Comment']),
                'type'     => 'text',
                // a third (4) becomes a quarter (3), two thirds (8) a half (6), then one narrower still:
                // more boxes a row, and the row fills up to the right edge anyway
                'col'      => max(1, ([4 => 3, 8 => 6][$opt['col'] ?? 6] ?? $opt['col'] ?? 6) - 1),
                'section'  => $opt['section'] ?? null,
                'key'      => in_array($name, static::$key, true),
                'max'      => null,
                'scale'    => 0,
                'upper'    => $opt['upper'] ?? false,
                'lookup'   => $opt['lookup'] ?? null,
                'options'  => [],
                'geo'      => $opt['geo'] ?? null,
                'exists'   => $opt['exists'] ?? null,
                'pick'     => $opt['pick'] ?? null, // a Phai.pick source (vendors, customers, parts, people) instead of a plain box
                'free'     => $opt['free'] ?? false,
                'readonly' => $opt['readonly'] ?? false,
                'nullable' => $col['Null'] === 'YES',
                'default'  => isset($opt['default'])
                    ? match ($opt['default']) { 'today' => date('Y-m-d'), 'me' => \Phai\Core\Auth\Auth::personName(), default => (string) $opt['default'] }
                    : self::cleanDefault($col['Default']),
            ];

            if (preg_match('/^tinyint\(1\)/', $type)) {
                $f['type'] = 'bool';
            } elseif (preg_match('/^(tiny|small|medium|big)?int/', $type)) {
                $f['type'] = 'int';
            } elseif (preg_match('/^decimal\(\d+,(\d+)\)/', $type, $m)) {
                $f['type'] = 'number';
                $f['scale'] = (int) $m[1];
            } elseif ($type === 'date') {
                $f['type'] = 'date';
            } elseif (preg_match('/^(datetime|timestamp)/', $type)) {
                $f['type'] = 'datetime';
                $f['readonly'] = true;
            } elseif (str_contains($type, 'text')) {
                $f['type'] = 'textarea';
            } elseif (preg_match('/char\((\d+)\)/', $type, $m)) {
                $f['max'] = (int) $m[1];
                $f['type'] = str_ends_with($name, '_email') ? 'email' : 'text';
            }

            if (isset($opt['type'])) {
                $f['type'] = $opt['type'];
            }
            if (isset($opt['readonly'])) {
                $f['readonly'] = $opt['readonly']; // e.g. a DATETIME column used as a plain date
            }
            if ($f['lookup'] !== null) {
                $f['type'] = 'select';
                $f['options'] = Lookup::options($f['lookup']);
            } elseif (isset($opt['options'])) {
                $f['type'] = 'select';
                $f['options'] = array_map(fn ($v) => ['value' => (string) $v, 'label' => self::optionLabel($name, (string) $v)], $opt['options']);
            }

            if ($f['key'] && static::$autoNumber !== null) {
                $opt['required'] ??= false; // filled from Numbering when left empty
            }
            $f['required'] = $opt['required']
                ?? ($col['Null'] === 'NO' && $col['Default'] === null && !str_contains((string) $col['Extra'], 'auto_increment') && $f['type'] !== 'bool');

            $fields[] = $f;
        }
        return $fields;
    }

    private static function label(string $name, string $comment): string
    {
        if (Lang::has("fields.$name")) {
            return t("fields.$name");
        }
        return Lang::column($name, trim((string) preg_split('/\s*[(,]/u', $comment)[0])); // the Thai comment, or in English the column name
    }

    /** Text for a fixed option: lang fields.<column>.<value>, else the value itself. */
    public static function optionLabel(string $column, string $value): string
    {
        return $value !== '' && Lang::has("fields.$column.$value") ? t("fields.$column.$value") : $value;
    }

    /** SHOW COLUMNS defaults: keep plain values, drop expressions like current_timestamp(). */
    private static function cleanDefault(?string $default): ?string
    {
        if ($default === null || str_contains($default, '(')) {
            return null;
        }
        return trim($default, "'");
    }
}
