<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Lang;

/**
 * The general ledger (gl_trhist). Every posting — automatic or a manual journal —
 * goes through post(): lines must balance (debit +, credit -) and the date must be
 * in an open period (on or after the first day of gl_ctrl's current period).
 *
 * Posting types (gl_type): IC stock, AR invoice, CR cash receipt, AP payable,
 * PV payment, LC landed cost, WO production, JE manual journal.
 */
final class Gl
{
    /**
     * An account's name in the screen's language, as SQL ($alias = the ac_mstr alias): ac_desc_th in Thai, ac_desc (English,
     * the Thai one when empty) otherwise. Selected AS ac_desc_th / name where the screens read it; prints stay Thai.
     */
    public static function acctName(string $alias = ''): string
    {
        $p = $alias === '' ? '' : "$alias.";
        return Lang::locale() === 'th' ? "{$p}ac_desc_th" : "COALESCE(NULLIF({$p}ac_desc, ''), {$p}ac_desc_th)";
    }

    private static ?array $settings = null;

    /** gl_ctrl, including the default accounts (gl_ar_acct, gl_inv_acct, ...). */
    public static function settings(): array
    {
        return self::$settings ??= App::db()->one('SELECT * FROM gl_ctrl WHERE ctrl_id = 1') ?? [];
    }

    /** Default account by short name: ar, ap, sales, cogs, inv, wip, vat_out, vat_in, grni, labor, adj, wht_ar, ret_earn. */
    public static function acct(string $name): string
    {
        return (string) (self::settings()["gl_{$name}_acct"] ?? throw new \LogicException("No default account $name"));
    }

    /**
     * Account of a part from its product line (pl_inv_acct, pl_cogs_acct, pl_wip_acct,
     * pl_sls_acct, pl_pur_acct), else the default. $kind: inv, cogs, wip, sls, pur (expense of a non-stock purchase).
     */
    public static function partAcct(string $part, string $kind): string
    {
        static $cache = [];
        $col = ['inv' => 'pl_inv_acct', 'cogs' => 'pl_cogs_acct', 'wip' => 'pl_wip_acct', 'sls' => 'pl_sls_acct', 'pur' => 'pl_pur_acct'][$kind];
        $cache[$part] ??= App::db()->one(
            'SELECT pl.* FROM pt_mstr pt LEFT JOIN pl_mstr pl ON pl.pl_prod_line = pt.pt_prod_line WHERE pt.pt_part = ?',
            [$part]
        ) ?? [];
        $acct = (string) ($cache[$part][$col] ?? '');
        return $acct !== '' ? $acct : self::acct(['sls' => 'sales', 'pur' => 'exp'][$kind] ?? $kind);
    }

    /** First day of the current (open) period. */
    public static function periodStart(): string
    {
        $s = self::settings();
        return sprintf('%04d-%02d-01', (int) $s['gl_curr_year'], (int) $s['gl_curr_per']);
    }

    /**
     * Post one balanced entry.
     * @param list<array{0: string, 1: float, 2?: ?string, 3?: ?string}> $lines [account, amount (+debit / -credit), description, cost centre]
     */
    public static function post(string $ref, string $type, string $date, array $lines, string $desc = ''): void
    {
        if ($date < self::periodStart()) {
            throw new HttpException(422, t('accounting.period_closed', ['date' => self::periodStart()]));
        }
        $rows = [];
        $sum = 0.0;
        foreach ($lines as $l) {
            $amt = round((float) $l[1], 2);
            if ($amt == 0.0) {
                continue;
            }
            $sum += $amt;
            $rows[] = [$l[0], $amt, $l[2] ?? $desc, $l[3] ?? null];
        }
        if (!$rows) {
            return;
        }
        if (abs(round($sum, 2)) > 0.001) {
            throw new \LogicException("Unbalanced entry $type $ref: " . round($sum, 2));
        }

        $db = App::db();
        foreach ($rows as [$acct, $amt, $text, $cc]) {
            $db->insert('gl_trhist', [
                'gl_ref' => $ref, 'gl_date' => $date, 'gl_effdate' => $date, 'gl_acct' => $acct, 'gl_cc' => $cc,
                'gl_desc' => mb_substr((string) $text, 0, 255), 'gl_amt' => $amt, 'gl_type' => $type,
                'gl_userid' => Auth::userId() ?? 'system', 'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
            ]);
        }
    }

    /** Post the exact opposite of every line posted under $ref / $type, dated $date. */
    public static function reverse(string $ref, string $type, string $date, string $desc): void
    {
        $lines = App::db()->all('SELECT gl_acct, gl_amt, gl_cc FROM gl_trhist WHERE gl_ref = ? AND gl_type = ?', [$ref, $type]);
        self::post($ref, $type, $date, array_map(fn ($l) => [$l['gl_acct'], -(float) $l['gl_amt'], $desc, $l['gl_cc']], $lines));
    }

    /** True when a line of the entry was ticked in a bank reconciliation: the document behind it can no longer be cancelled. */
    public static function reconciled(string $ref, string $type): bool
    {
        return (bool) App::db()->value(
            'SELECT 1 FROM gl_trhist g JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr WHERE g.gl_ref = ? AND g.gl_type = ? LIMIT 1',
            [$ref, $type]
        );
    }

    /**
     * Split $total over lines rounded to satang so they add up exactly:
     * the rounding difference goes to the largest line.
     * @param array<string|int, float> $amounts
     * @return array<string|int, float>
     */
    public static function fit(array $amounts, float $total): array
    {
        $amounts = array_map(fn ($a) => round($a, 2), $amounts);
        $diff = round($total - array_sum($amounts), 2);
        if ($diff != 0.0 && $amounts) {
            $big = array_keys($amounts, max($amounts))[0];
            $amounts[$big] = round($amounts[$big] + $diff, 2);
        }
        return $amounts;
    }

    /** Value moved in a stock movement, booked between the item's stock account and the other side. */
    public static function stock(string $type, string $part, float $qty, float $cost, ?string $reason, string $date, string $nbr): void
    {
        $value = round(abs($qty) * $cost, 2);
        if ($value == 0.0) {
            return;
        }
        $inv = self::partAcct($part, 'inv');
        $other = match ($type) {
            'RCT-PO' => self::acct('grni'),
            'ISS-SO', 'RCT-RTN' => self::partAcct($part, 'cogs'), // a sales return comes back at the cost it went out
            'ISS-RTV' => null, // purchase return: PurchaseReturnController posts it at the purchase price (GRNI) with the variance
            'ISS-WO', 'RCT-WO' => self::acct('wip'),
            'ISS-TR', 'RCT-TR' => null, // same account on both sides
            'ISS-CO', 'RCT-CO' => null, // conversion: one entry material > finished good (StockMoveController::convert)
            default => ($reason ? App::db()->value('SELECT rs_acct FROM rs_mstr WHERE rs_code = ?', [$reason]) : null) ?: self::acct('adj'),
        };
        if ($other === null || $other === $inv) {
            return;
        }
        $in = $qty > 0 ? $value : -$value;
        self::post($nbr, 'IC', $date, [[$inv, $in], [$other, -$in]], "$type $part");
    }
}
