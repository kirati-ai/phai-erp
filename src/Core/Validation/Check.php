<?php

declare(strict_types=1);

namespace Phai\Core\Validation;

use Phai\Core\App;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;

/**
 * Field-by-field checks for documents with lines, where input objects do not fit.
 * Every method records an error under the field name and returns the clean value
 * (or null); call done() at the end to throw them all as one 422.
 *
 *     $c = new Check();
 *     $date = $c->date('po_date', $b['po_date'] ?? '', required: true);
 *     $qty  = $c->number("lines.$i.qty", $l['qty'] ?? '', min: 0, above: true);
 *     $c->done();
 */
final class Check
{
    public array $errors = [];

    public function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    public function ok(string $field): bool
    {
        return !isset($this->errors[$field]);
    }

    public function text(string $field, mixed $v, bool $required = false, int $max = 255, bool $upper = false): ?string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';
        if ($v === '') {
            if ($required) {
                $this->fail($field, t('validation.required'));
            }
            return null;
        }
        if (mb_strlen($v) > $max) {
            $this->fail($field, t('validation.max_length', ['max' => $max]));
        }
        return $upper ? mb_strtoupper($v) : $v;
    }

    /**
     * @param bool $above true: must be greater than $min, false: greater or equal
     */
    public function number(string $field, mixed $v, bool $required = true, ?float $min = null, bool $above = false, int $scale = 5): ?float
    {
        $v = is_scalar($v) ? str_replace(',', '', trim((string) $v)) : '';
        if ($v === '') {
            if ($required) {
                $this->fail($field, t('validation.required'));
            }
            return null;
        }
        if (!is_numeric($v) || abs((float) $v) >= 1e12) {
            $this->fail($field, t('validation.number'));
            return null;
        }
        $n = round((float) $v, $scale);
        if ($min !== null && ($above ? $n <= $min : $n < $min)) {
            $this->fail($field, $above && $min == 0 ? t('validation.positive') : t('validation.min', ['min' => $min]));
        }
        return $n;
    }

    public function date(string $field, mixed $v, bool $required = false): ?string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';
        if ($v === '') {
            if ($required) {
                $this->fail($field, t('validation.required'));
            }
            return null;
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            $this->fail($field, t('validation.date'));
            return null;
        }
        return $v;
    }

    /** Value must exist in $table.$column (optionally with an extra condition, e.g. "vd_status = 'ACTIVE'"). */
    public function exists(string $field, mixed $v, string $table, string $column, bool $required = true, string $where = '', bool $upper = true): ?string
    {
        $v = $this->text($field, $v, $required, 50, $upper);
        if ($v === null || !$this->ok($field)) {
            return $v;
        }
        $sql = 'SELECT 1 FROM ' . Db::quote($table) . ' WHERE ' . Db::quote($column) . ' = ?' . ($where !== '' ? " AND $where" : '');
        if (!App::db()->value($sql, [$v])) {
            $this->fail($field, t('validation.in'));
        }
        return $v;
    }

    public function done(): void
    {
        if ($this->errors) {
            throw HttpException::invalid($this->errors);
        }
    }
}
