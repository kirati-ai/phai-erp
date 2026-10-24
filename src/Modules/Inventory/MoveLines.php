<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\App;
use Phai\Core\Http\HttpException;

/**
 * Checks a stock document before anything is posted: header and every line.
 * Errors are keyed by form field (e.g. 'lines.2.qty') and thrown together as 422.
 */
final class MoveLines
{
    public const KINDS = ['receipt', 'issue'];
    private const MAX_LINES = 200;

    /** @return array{head: array, lines: list<array>} */
    public static function check(array $body, string $kind): array
    {
        $errors = [];
        $head = self::head($body, $kind, $errors);

        $lines = [];
        $raw = $body['lines'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        foreach ($raw as $i => $line) {
            if (!is_array($line) || self::blank($line)) {
                continue;
            }
            $lines[$i] = self::line($line, (string) $i, $kind, $head, $errors);
        }
        if (!$lines) {
            $errors['lines'] = t('stock.no_lines');
        } elseif (count($lines) > self::MAX_LINES) {
            $errors['lines'] = t('stock.too_many_lines', ['max' => self::MAX_LINES]);
        }

        if ($errors) {
            throw HttpException::invalid($errors);
        }
        return ['head' => $head, 'lines' => $lines];
    }

    private static function head(array $b, string $kind, array &$errors): array
    {
        $head = [
            'date'   => self::str($b['date'] ?? ''),
            'remark' => self::str($b['remark'] ?? ''),
            'reason' => self::str($b['reason'] ?? ''),
        ];

        $d = \DateTime::createFromFormat('!Y-m-d', $head['date']);
        if ($head['date'] === '') {
            $errors['date'] = t('validation.required');
        } elseif (!$d || $d->format('Y-m-d') !== $head['date']) {
            $errors['date'] = t('validation.date');
        } elseif ($head['date'] > date('Y-m-d')) {
            $errors['date'] = t('stock.future_date');
        }
        if (mb_strlen($head['remark']) > 255) {
            $errors['remark'] = t('validation.max_length', ['max' => 255]);
        }

        $head['site'] = self::str($b['site'] ?? '');
        if ($head['site'] === '') {
            $errors['site'] = t('validation.required');
        } elseif (!App::db()->value('SELECT 1 FROM si_mstr WHERE si_site = ? AND si_status = 1', [$head['site']])) {
            $errors['site'] = t('validation.in');
        }

        $type = ['receipt' => 'RCT', 'issue' => 'ISS'][$kind] ?? null;
        if ($head['reason'] !== '' && !App::db()->value(
            'SELECT 1 FROM rs_mstr WHERE rs_code = ? AND rs_active = 1' . ($type ? ' AND rs_type = ?' : ''),
            $type ? [$head['reason'], $type] : [$head['reason']]
        )) {
            $errors['reason'] = t('validation.in');
        }
        $head['reason'] = $head['reason'] !== '' ? $head['reason'] : null;
        $head['remark'] = $head['remark'] !== '' ? $head['remark'] : null;
        return $head;
    }

    private static function line(array $l, string $i, string $kind, array $head, array &$errors): array
    {
        $f = fn (string $name) => "lines.$i.$name";
        $site = $head['site'];
        $out = [
            'part'   => mb_strtoupper(self::str($l['part'] ?? '')),
            'loc'    => mb_strtoupper(self::str($l['loc'] ?? '')),
            'lot'    => self::str($l['lot'] ?? ''),
            'qty'    => 0.0,
            'cost'   => null,
            'expire' => null,
        ];

        $part = $out['part'] !== '' ? Stock::part($out['part']) : null;
        if ($out['part'] === '') {
            $errors[$f('part')] = t('validation.required');
        } elseif ($part === null) {
            $errors[$f('part')] = t('stock.no_part');
        }

        if ($out['loc'] === '') {
            $errors[$f('loc')] = t('validation.required');
        } elseif (!self::locExists($site, $out['loc'])) {
            $errors[$f('loc')] = t('validation.in');
        }

        if ($part && Stock::needsLot($part)) {
            if ($out['lot'] === '' && $kind === 'receipt') {
                $out['lot'] = null; // the lot of a receipt is its document number (StockMoveController)
            } elseif ($out['lot'] === '') {
                $errors[$f('lot')] = t('stock.lot_required');
            } elseif (mb_strlen($out['lot']) > 50) {
                $errors[$f('lot')] = t('validation.max_length', ['max' => 50]);
            }
        } else {
            $out['lot'] = '';
        }

        $qty = str_replace(',', '', self::str($l['qty'] ?? ''));
        if ($qty === '') {
            $errors[$f('qty')] = t('validation.required');
        } elseif (!is_numeric($qty) || abs((float) $qty) >= 1e10) {
            $errors[$f('qty')] = t('validation.number');
        } else {
            $out['qty'] = round((float) $qty, 5);
            if ($out['qty'] <= 0) {
                $errors[$f('qty')] = t('stock.qty_positive');
            }
        }

        if ($kind === 'receipt') {
            $cost = str_replace(',', '', self::str($l['cost'] ?? ''));
            if ($cost !== '') {
                if (!is_numeric($cost) || (float) $cost < 0) {
                    $errors[$f('cost')] = t('validation.number');
                } else {
                    $out['cost'] = round((float) $cost, 5);
                }
            }
            $exp = self::str($l['expire'] ?? '');
            if ($exp !== '') {
                $d = \DateTime::createFromFormat('!Y-m-d', $exp);
                if (!$d || $d->format('Y-m-d') !== $exp) {
                    $errors[$f('expire')] = t('validation.date');
                } else {
                    $out['expire'] = $exp;
                }
            }
        }

        return $out;
    }

    private static function locExists(string $site, string $loc): bool
    {
        return $site !== '' && (bool) App::db()->value('SELECT 1 FROM loc_mstr WHERE loc_site = ? AND loc_loc = ?', [$site, $loc]);
    }

    /** A row the user left empty. */
    private static function blank(array $l): bool
    {
        return self::str($l['part'] ?? '') === '' && self::str($l['qty'] ?? '') === '';
    }

    private static function str(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }
}
