<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Validation\Check;
use Phai\Modules\Accounting\Gl;

/**
 * The only place that changes stock. Every change updates the balance (ld_det),
 * writes one history row (tr_hist) and books its value in the ledger (Gl::stock).
 *
 *     App::db()->transaction(fn () => Stock::move([...]));   // always inside a transaction
 */
final class Stock
{
    /** Movement types written to tr_hist.tr_type. */
    public const TYPES = ['RCT-UNP', 'ISS-UNP', 'ISS-ADJ', 'RCT-ADJ', 'ISS-CO', 'RCT-CO', 'ADJ', 'ISS-TR', 'RCT-TR', 'CYC-CNT', 'RCT-PO', 'ISS-SO', 'ISS-WO', 'RCT-WO', 'ISS-RTV', 'RCT-RTN'];

    private static array $parts = [];

    /** Drop a part from the cache after its settings changed in this request. */
    public static function forget(string $code): void
    {
        unset(self::$parts[$code]);
    }

    /** Part fields stock needs, or null when the part does not exist. */
    public static function part(string $code): ?array
    {
        if (!array_key_exists($code, self::$parts)) {
            self::$parts[$code] = App::db()->one(
                'SELECT pt_part, pt_desc1, pt_um, pt_lot_ser, pt_cost, pt_cost_method, pt_status, pt_inventory FROM pt_mstr WHERE pt_part = ?',
                [$code]
            );
        }
        return self::$parts[$code];
    }

    /**
     * Is this item kept in stock? Only these go through move() (tr_hist, on-hand per location);
     * a part marked non-stock (pt_inventory = 0) or free text not in pt_mstr never does.
     */
    public static function stocked(string $code): bool
    {
        return (bool) (self::part($code)['pt_inventory'] ?? false);
    }

    /**
     * Item of a purchase or sales line: a code from pt_mstr, or any text for something not kept
     * there (a service, a one-off buy). A free item needs its description; its unit is typed.
     * @return array{0: ?string, 1: ?string, 2: ?string} code, description, unit
     */
    public static function lineItem(Check $c, string $partField, mixed $part, string $descField, mixed $desc, string $umField, mixed $um, bool $needCode = true): array
    {
        // $needCode false (requisitions, orders): a line may be just a description, its code left empty
        $code = $c->text($partField, $part, $needCode, 50, true);
        $free = $code === null || self::part($code) === null;
        // the unit typed for a free item must be one of the units (um_mstr)
        $unit = $c->exists($umField, $um, 'um_mstr', 'um_um', false);
        return [$code ?? '', $c->text($descField, $desc, $free, 255), $unit];
    }

    /** A part on a raw material product line (pl_inventory_type RM). */
    public static function isRawMaterial(string $part): bool
    {
        return (bool) App::db()->value(
            "SELECT 1 FROM pt_mstr pt JOIN pl_mstr pl ON pl.pl_prod_line = pt.pt_prod_line WHERE pt.pt_part = ? AND pl.pl_inventory_type = 'RM'",
            [$part]
        );
    }

    /** Lot or serial controlled parts need a lot number on every move. */
    public static function needsLot(array $part): bool
    {
        return in_array($part['pt_lot_ser'], ['L', 'S'], true);
    }

    /** Every column that holds a lot number, with the part column beside it. */
    /** ld_det.ld_status */
    public const LD_STATUSES = ['ACTIVE', 'HOLD', 'EXPIRED', 'QUARANTINE'];

    public const LOT_COLUMNS = [
        'lot_mstr' => ['lot_part', 'lot_lot'], 'ld_det' => ['ld_part', 'ld_lot'], 'tr_hist' => ['tr_part', 'tr_lot'],
        'rcd_det' => ['rcd_part', 'rcd_lot'], 'shd_det' => ['shd_part', 'shd_lot'], 'wo_mstr' => ['wo_part', 'wo_lot'],
        'pi_tag' => ['pit_part', 'pit_lot'], 'qc_hist' => ['qc_part', 'qc_lot'], 'abs_det' => ['absd_part', 'absd_lot'],
    ];

    /**
     * Changes a lot number everywhere at once (lot_mstr is the register; the history, balances and
     * documents follow it), so a lot never has two names. Call inside a transaction.
     */
    public static function renameLot(string $part, string $old, string $new): void
    {
        $new = trim($new);
        if ($old === '' || $new === '' || $new === $old || mb_strlen($new) > 50) {
            throw HttpException::invalid(['lot' => t('validation.in')]);
        }
        $db = App::db();
        $row = $db->one('SELECT * FROM lot_mstr WHERE lot_part = ? AND lot_lot = ? FOR UPDATE', [$part, $old]);
        if (!$row) throw HttpException::notFound();
        if ($db->value('SELECT 1 FROM lot_mstr WHERE lot_part = ? AND lot_lot = ?', [$part, $new])) {
            throw HttpException::invalid(['lot' => t('stock.lot_exists')]);
        }
        foreach (self::LOT_COLUMNS as $table => [$partCol, $lotCol]) {
            $db->run("UPDATE `$table` SET `$lotCol` = ?, updated_by = ? WHERE `$partCol` = ? AND `$lotCol` = ?", [$new, Auth::userId(), $part, $old]);
        }
        \Phai\Core\Audit\Audit::updated('lot_mstr', $part . '~' . $old, $row, ['lot_lot' => $new] + $row);
    }

    public static function negativeAllowed(): bool
    {
        return (bool) App::db()->value('SELECT ic_neg_qty FROM ic_ctrl WHERE ctrl_id = 1');
    }

    /**
     * Move stock in (+qty) or out (-qty) of one place.
     *
     * @param array $m type, part, site, loc, qty (signed), date, nbr, line, program,
     *                 and optional lot, cost (unit cost of a receipt), price (purchase price), expire, pack (qty per pack, 1), reason, remark, ref (tr_ref: the slip it belongs to),
     *                 field (form field to blame when stock is short, e.g. 'lines.2.qty')
     * @return float on-hand after the move
     */
    public static function move(array $m): float
    {
        $db = App::db();
        $part = self::part($m['part']) ?? throw new \InvalidArgumentException("Unknown part {$m['part']}");
        if (!$part['pt_inventory']) {
            $msg = t('stock.not_stocked', ['part' => $m['part']]);
            throw isset($m['field']) ? HttpException::invalid([$m['field'] => $msg]) : new HttpException(422, $msg);
        }
        $lot = (string) ($m['lot'] ?? '');
        $qty = round((float) $m['qty'], 5);
        if (!in_array($m['type'], self::TYPES, true) || $qty == 0) {
            throw new \InvalidArgumentException('Bad stock move');
        }

        $where = [$m['site'], $m['loc'], $m['part'], $lot];
        // frozen while it is being counted: from the count's tags until the count is posted (or deleted)
        if ($m['type'] !== 'CYC-CNT' && ($pi = $db->value(
            "SELECT t.pit_batch FROM pi_tag t JOIN pi_mstr p ON p.pi_batch = t.pit_batch
              WHERE p.pi_status = 'O' AND p.pi_site = ? AND t.pit_loc = ? AND t.pit_part = ? AND t.pit_lot = ? AND t.pit_status <> 'V' LIMIT 1",
            $where
        ))) {
            $msg = t('stock.frozen', ['part' => $m['part'], 'loc' => $m['loc'], 'nbr' => $pi]);
            throw isset($m['field']) ? HttpException::invalid([$m['field'] => $msg]) : new HttpException(409, $msg);
        }
        $row = $db->one(
            "SELECT ld_qty_oh, ld_qty_all, ld_ref FROM ld_det
              WHERE ld_site = ? AND ld_loc = ? AND ld_part = ? AND ld_lot = ? FOR UPDATE",
            $where
        );
        $old = (float) ($row['ld_qty_oh'] ?? 0);
        $new = round($old + $qty, 5);

        if ($new < 0 && !self::negativeAllowed()) {
            $msg = t('stock.not_enough', ['qty' => self::fmt($old), 'um' => $part['pt_um']]);
            throw isset($m['field']) ? HttpException::invalid([$m['field'] => $msg]) : new HttpException(422, $msg);
        }

        // Receipts with a cost update the moving average of AVG parts.
        $cost = (float) $part['pt_cost'];
        if ($qty > 0 && isset($m['cost']) && $m['cost'] !== null) {
            $in = (float) $m['cost'];
            if ($part['pt_cost_method'] === 'AVG') {
                $onHand = max(0.0, (float) $db->value('SELECT COALESCE(SUM(ld_qty_oh), 0) FROM ld_det WHERE ld_part = ?', [$m['part']]));
                $avg = round(($onHand * $cost + $qty * $in) / ($onHand + $qty), 5);
                $db->update('pt_mstr', ['pt_cost' => $avg], ['pt_part' => $m['part']]);
                self::$parts[$m['part']]['pt_cost'] = $avg;
            }
            $cost = $in;
        }

        if ($row === null) {
            $db->insert('ld_det', [
                'ld_site' => $m['site'], 'ld_loc' => $m['loc'], 'ld_part' => $m['part'], 'ld_lot' => $lot, 'ld_ref' => '',
                'ld_qty_oh' => $new, 'ld_pack_qty' => $m['pack'] ?? 1, 'ld_cost' => $cost, 'ld_expire' => $m['expire'] ?? null,
                'ld_mfg_date' => $qty > 0 ? $m['date'] : null,
                'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
            ]);
        } elseif ($new == 0 && (float) $row['ld_qty_all'] == 0) {
            $db->run("DELETE FROM ld_det WHERE ld_site = ? AND ld_loc = ? AND ld_part = ? AND ld_lot = ?", $where);
        } else {
            $db->run(
                "UPDATE ld_det SET ld_qty_oh = ?, updated_by = ? WHERE ld_site = ? AND ld_loc = ? AND ld_part = ? AND ld_lot = ?",
                [$new, Auth::userId(), ...$where]
            );
        }

        if ($lot !== '' && $qty > 0) {
            $db->run(
                'INSERT INTO lot_mstr (lot_part, lot_lot, lot_rcv_date, lot_expire_date, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE lot_part = lot_part',
                [$m['part'], $lot, $m['date'], $m['expire'] ?? null, Auth::userId(), Auth::userId()]
            );
        }

        $db->insert('tr_hist', [
            'tr_date'     => $m['date'],
            'tr_time'     => date('H:i:s'),
            'tr_type'     => $m['type'],
            'tr_part'     => $m['part'],
            'tr_site'     => $m['site'],
            'tr_loc'      => $m['loc'],
            'tr_lot'      => $lot,
            'tr_ref'      => $m['ref'] ?? ($row['ld_ref'] ?? ''), // the document line's own reference (a material issue slip), else the location's
            'tr_begin_qty' => $old,
            'tr_chg_qty'  => $qty,
            'tr_loc_qty'  => $new,
            'tr_um'       => $part['pt_um'],
            'tr_price'    => $m['price'] ?? 0,
            'tr_cost'     => $cost,
            'tr_nbr'      => $m['nbr'],
            'tr_line'     => $m['line'],
            'tr_rmks'     => $m['remark'] ?? null,
            'tr_rsn_code' => $m['reason'] ?? null,
            'tr_userid'   => Auth::userId() ?? 'system',
            'tr_program'  => $m['program'],
            'created_by'  => Auth::userId(),
            'updated_by'  => Auth::userId(),
        ]);

        Gl::stock($m['type'], $m['part'], $qty, $cost, $m['reason'] ?? null, $m['date'], $m['nbr']);

        return $new;
    }

    /**
     * Price adjustment: every place holding the part gives its quantity out at the old cost (ISS-ADJ)
     * and takes it back at the new one (RCT-ADJ) — two history rows each, quantity unchanged, the
     * difference booked against the reason account (or gl adj). Call inside a transaction.
     *
     * @param array $m part, cost (new unit cost), date, nbr, reason, remark, program, field
     * @return int history lines written
     */
    public static function revalue(array $m, int $line = 0): int
    {
        $db = App::db();
        $part = self::part($m['part']) ?? throw new \InvalidArgumentException("Unknown part {$m['part']}");
        $rows = $db->all(
            "SELECT ld_site, ld_loc, ld_lot, ld_qty_oh, ld_expire, ld_mfg_date FROM ld_det
              WHERE ld_part = ? AND ld_qty_oh > 0 ORDER BY ld_site, ld_loc, ld_lot FOR UPDATE",
            [$m['part']]
        );
        $new = round((float) $m['cost'], 5);
        if (self::isRawMaterial($m['part'])) { // a material keeps the cost it was bought at
            throw HttpException::invalid([$m['field'] => t('stock.cost_fg_only')]);
        }
        if (!$rows || $new == (float) $part['pt_cost']) {
            throw HttpException::invalid([$m['field'] => t($rows ? 'stock.same_cost' : 'stock.nothing_on_hand')]);
        }
        $move = fn (array $r, string $type, float $qty, ?float $cost) => self::move([
            'type' => $type, 'part' => $m['part'], 'site' => $r['ld_site'], 'loc' => $r['ld_loc'], 'lot' => $r['ld_lot'],
            'qty' => $qty, 'cost' => $cost, 'expire' => $r['ld_expire'], 'date' => $m['date'], 'nbr' => $m['nbr'],
            'line' => ++$line, 'reason' => $m['reason'] ?? null, 'remark' => $m['remark'] ?? null, 'program' => $m['program'],
        ]);
        // all out first, so the average starts again from the new cost when they come back in
        foreach ($rows as $r) $move($r, 'ISS-ADJ', -(float) $r['ld_qty_oh'], null);
        $db->update('pt_mstr', ['pt_cost' => $new], ['pt_part' => $m['part']]);
        self::$parts[$m['part']]['pt_cost'] = $new;
        foreach ($rows as $r) {
            $move($r, 'RCT-ADJ', (float) $r['ld_qty_oh'], $new);
            $db->run("UPDATE ld_det SET ld_mfg_date = ? WHERE ld_site = ? AND ld_loc = ? AND ld_part = ? AND ld_lot = ?",
                [$r['ld_mfg_date'], $r['ld_site'], $r['ld_loc'], $m['part'], $r['ld_lot']]);
        }
        return $line;
    }

    public static function fmt(float $qty): string
    {
        return rtrim(rtrim(number_format($qty, 5, '.', ','), '0'), '.');
    }
}
