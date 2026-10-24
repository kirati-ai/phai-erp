<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Accounting\Gl;

/** Receipts and issues without a purchase or sales document. */
#[Program('stock-moves')]
final class StockMoveController
{
    // no adjust: a decrease is an issue (ISS-UNP) with a reason, an increase a receipt
    private const TYPE = ['receipt' => 'RCT-UNP', 'issue' => 'ISS-UNP'];

    #[Get('/stock/moves')]
    public function page(): string
    {
        return View::render('inventory/entry', [
            'docNbr'  => Numbering::peek('IC'),
            'title'   => t('stock.moves_title'),
            'sites'   => StockLookupController::sites(),
            'reasons' => App::db()->all('SELECT rs_code, rs_desc, rs_type FROM rs_mstr WHERE rs_active = 1 ORDER BY rs_code'),
            'help'    => 'stock-moves',
            'scripts' => ['js/pages/stock-entry.js'],
        ]);
    }

    /**
     * Body: {kind: receipt|issue, date, site, reason, remark,
     *        lines: [{part, loc, lot, qty, cost, expire}]}
     * Issues send a positive qty.
     */
    #[Post('/api/stock/moves')]
    public function post(Request $request): array
    {
        $kind = (string) ($request->body['kind'] ?? '');
        if ($kind === 'cost') {
            return $this->revalue($request->body);
        }
        if ($kind === 'convert') {
            return $this->convert($request->body);
        }
        if (!isset(self::TYPE[$kind])) {
            throw HttpException::invalid(['kind' => t('validation.in')]);
        }
        ['head' => $head, 'lines' => $lines] = MoveLines::check($request->body, $kind);

        return App::db()->transaction(function () use ($kind, $head, $lines) {
            $nbr = Numbering::next('IC', $head['date']);
            $n = 0;
            foreach ($lines as $i => $l) {
                Stock::move([
                    'type'    => self::TYPE[$kind],
                    'part'    => $l['part'],
                    'site'    => $head['site'],
                    'loc'     => $l['loc'],
                    'lot'     => $l['lot'] ?? $nbr,
                    'qty'     => $kind === 'issue' ? -$l['qty'] : $l['qty'],
                    'cost'    => $l['cost'],
                    'expire'  => $l['expire'],
                    'date'    => $head['date'],
                    'nbr'     => $nbr,
                    'line'    => ++$n,
                    'reason'  => $head['reason'],
                    'remark'  => $head['remark'],
                    'program' => 'stock-moves',
                    'field'   => "lines.$i.qty",
                ]);
            }
            return ['nbr' => $nbr, 'lines' => $n, 'next' => Numbering::peek('IC')];
        });
    }
    /**
     * Price adjustment: {kind: cost, date, reason, remark, lines: [{part, cost}]} — the part's whole stock
     * goes out at the old cost and back at the new one (Stock::revalue).
     */
    private function revalue(array $b): array
    {
        $c = new Check();
        $date = $c->date('date', $b['date'] ?? '', true);
        if ($date && $date > date('Y-m-d')) $c->fail('date', t('stock.future_date'));
        $reason = $c->exists('reason', $b['reason'] ?? '', 'rs_mstr', 'rs_code', false, 'rs_active = 1');
        $remark = $c->text('remark', $b['remark'] ?? '', false, 255);
        $lines = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $i => $l) {
            if (!is_array($l) || (trim((string) ($l['part'] ?? '')) === '' && trim((string) ($l['cost'] ?? '')) === '')) continue;
            $part = $c->exists("lines.$i.part", $l['part'] ?? '', 'pt_mstr', 'pt_part', true, 'pt_inventory = 1');
            $cost = $c->number("lines.$i.cost", $l['cost'] ?? '', true, 0);
            if ($part && isset($lines[$part])) $c->fail("lines.$i.part", t('validation.in'));
            if ($part !== null && $cost !== null) $lines[$part] = [$i, $cost];
        }
        if (!$lines && $c->ok('lines')) $c->fail('lines', t('stock.no_lines'));
        $c->done();

        return App::db()->transaction(function () use ($date, $reason, $remark, $lines) {
            $nbr = Numbering::next('IC', $date);
            $n = 0;
            foreach ($lines as $part => [$i, $cost]) {
                $n = Stock::revalue(['part' => $part, 'cost' => $cost, 'date' => $date, 'nbr' => $nbr, 'reason' => $reason,
                    'remark' => $remark, 'program' => 'stock-moves', 'field' => "lines.$i.cost"], $n);
            }
            return ['nbr' => $nbr, 'lines' => $n, 'next' => Numbering::peek('IC')];
        });
    }

    /**
     * One stock line (ld_id) to another place: {date, qty, to_site, to_loc, remark}.
     * Out (ISS-TR) and in (RCT-TR) at the line's unit cost, so the value moved = qty × cost; same lot and expiry.
     */
    #[Post('/api/stock/view/{id}/transfer')]
    public function transfer(int $id, Request $request): array
    {
        $from = StockController::row($id);
        $b = $request->body;
        $c = new Check();
        $date = $c->date('date', $b['date'] ?? '', true);
        if ($date && $date > date('Y-m-d')) $c->fail('date', t('stock.future_date'));
        $qty = $c->number('qty', $b['qty'] ?? '', true, 0, true);
        $site = $c->exists('to_site', $b['to_site'] ?? '', 'si_mstr', 'si_site', true, 'si_status = 1');
        $loc = $site === null ? null
            : $c->exists('to_loc', $b['to_loc'] ?? '', 'loc_mstr', 'loc_loc', true, 'loc_site = ' . App::db()->pdo->quote($site));
        if ($site === $from['ld_site'] && $loc === $from['ld_loc']) $c->fail('to_loc', t('stock.same_place'));
        $remark = $c->text('remark', $b['remark'] ?? '', false, 255);
        $c->done();

        return App::db()->transaction(function () use ($from, $date, $qty, $site, $loc, $remark) {
            $nbr = Numbering::next('TO', $date);
            $common = ['part' => $from['ld_part'], 'lot' => $from['ld_lot'], 'date' => $date, 'nbr' => $nbr,
                'remark' => $remark, 'program' => 'stock-moves'];
            Stock::move($common + ['type' => 'ISS-TR', 'site' => $from['ld_site'], 'loc' => $from['ld_loc'], 'qty' => -$qty, 'line' => 1, 'field' => 'qty']);
            Stock::move($common + ['type' => 'RCT-TR', 'site' => $site, 'loc' => $loc, 'qty' => $qty, 'line' => 2,
                'cost' => (float) $from['cost'], 'expire' => $from['ld_expire']]);
            $to = App::db()->value(
                "SELECT ld_id FROM ld_det WHERE ld_site = ? AND ld_loc = ? AND ld_part = ? AND ld_lot = ?",
                [$site, $loc, $from['ld_part'], $from['ld_lot']]
            );
            if ($from['ld_ref'] !== '') {
                App::db()->run("UPDATE ld_det SET ld_ref = ? WHERE ld_id = ? AND ld_ref = ''", [$from['ld_ref'], $to]);
            }
            return ['nbr' => $nbr, 'id' => (int) $to, 'value' => round($qty * (float) $from['cost'], 2)];
        });
    }

    /**
     * Edit one stock line — every field a person may set: {ref, pack_qty, status, grade, mfg_date, expire} on every line,
     * {lot} on every line (an item without lot control is switched to lot control, pt_lot_ser = 'L'), and for a
     * lot-controlled item {vend_lot, lot_rmks}. A new lot number renames the lot everywhere
     * (Stock::renameLot); a line still without a lot (item switched to lot control later) gets one by moving its
     * whole quantity out of lot '' into the new lot at the same place (ISS-TR / RCT-TR, same cost), so the history
     * still adds up — the line then has a new id. Expiry, supplier lot and lot remark belong to the lot.
     * Not here: warehouse/location (Transfer), quantity (receipts, issues, counts), cost (price adjust),
     * item, allocated (set by the system), created/updated. Returns the line as saved.
     */
    #[Put('/api/stock/view/{id}')]
    public function edit(int $id, Request $request): array
    {
        $row = StockController::row($id);
        $lotted = Stock::needsLot(Stock::part($row['ld_part']));
        $b = $request->body;
        $c = new Check();
        $ref = $c->text('ref', $b['ref'] ?? '', false, 50) ?? '';
        $pack = $c->number('pack_qty', $b['pack_qty'] ?? '', false, 0, true) ?? 1.0;
        $status = $c->text('status', $b['status'] ?? '', true, 20, true);
        if ($status !== null && !in_array($status, Stock::LD_STATUSES, true)) $c->fail('status', t('validation.in'));
        $grade = $c->text('grade', $b['grade'] ?? '', false, 20);
        $expire = $c->date('expire', $b['expire'] ?? '');
        $mfg = $c->date('mfg_date', $b['mfg_date'] ?? '');
        $lot = $c->text('lot', $b['lot'] ?? '', $row['ld_lot'] !== '', 50) ?? '';
        $turnOn = !$lotted && $lot !== '' && $row['ld_lot'] === ''; // first lot of an item without lot control
        $vend = $lotted ? $c->text('vend_lot', $b['vend_lot'] ?? '', false, 50) : null;
        $rmks = $lotted ? $c->text('lot_rmks', $b['lot_rmks'] ?? '', false, 1000) : null;
        if ($row['ld_lot'] === '' && $lot !== '' && (float) $row['ld_qty_oh'] <= 0) $c->fail('lot', t('stock.lot_needs_stock'));
        $c->done();

        $id = App::db()->transaction(function () use ($row, $lotted, $turnOn, $ref, $pack, $status, $grade, $expire, $mfg, $lot, $vend, $rmks) {
            $db = App::db();
            $user = Auth::userId();
            $id = (int) $row['ld_id'];
            if ($turnOn) {
                $db->run("UPDATE pt_mstr SET pt_lot_ser = 'L', updated_by = ? WHERE pt_part = ?", [$user, $row['ld_part']]);
                \Phai\Core\Audit\Audit::updated('pt_mstr', $row['ld_part'], ['pt_lot_ser' => ''], ['pt_lot_ser' => 'L']);
                Stock::forget($row['ld_part']);
            }
            if ($lot !== $row['ld_lot'] && $row['ld_lot'] !== '') {
                Stock::renameLot($row['ld_part'], $row['ld_lot'], $lot);
            } elseif ($lot !== '' && $row['ld_lot'] === '') {
                $nbr = Numbering::next('IC');
                $qty = (float) $row['ld_qty_oh'];
                $common = ['part' => $row['ld_part'], 'site' => $row['ld_site'], 'loc' => $row['ld_loc'], 'date' => date('Y-m-d'),
                    'nbr' => $nbr, 'program' => 'stock-moves', 'remark' => t('stock.lot') . ' ' . $lot];
                Stock::move($common + ['type' => 'ISS-TR', 'lot' => '', 'qty' => -$qty, 'line' => 1, 'field' => 'lot']);
                Stock::move($common + ['type' => 'RCT-TR', 'lot' => $lot, 'qty' => $qty, 'line' => 2,
                    'cost' => (float) $row['cost'], 'expire' => $row['ld_expire']]);
                $id = (int) $db->value('SELECT ld_id FROM ld_det WHERE ld_site = ? AND ld_loc = ? AND ld_part = ? AND ld_lot = ?',
                    [$row['ld_site'], $row['ld_loc'], $row['ld_part'], $lot]);
            }
            $before = $db->one('SELECT ld_ref, ld_pack_qty, ld_status, ld_grade, ld_expire, ld_mfg_date FROM ld_det WHERE ld_id = ?', [$id]);
            $after = ['ld_ref' => $ref, 'ld_pack_qty' => $pack, 'ld_status' => $status, 'ld_grade' => $grade, 'ld_expire' => $expire, 'ld_mfg_date' => $mfg];
            $db->run('UPDATE ld_det SET ld_ref = ?, ld_pack_qty = ?, ld_status = ?, ld_grade = ?, ld_expire = ?, ld_mfg_date = ?, updated_by = ? WHERE ld_id = ?',
                [$ref, $pack, $status, $grade, $expire, $mfg, $user, $id]);
            \Phai\Core\Audit\Audit::updated('ld_det', (string) $id, $before, $after);
            if (($lotted || $turnOn) && $lot !== '') {
                $lotBefore = $db->one('SELECT * FROM lot_mstr WHERE lot_part = ? AND lot_lot = ?', [$row['ld_part'], $lot]);
                $db->run('UPDATE lot_mstr SET lot_expire_date = ?, lot_vend_lot = ?, lot_rmks = ?, updated_by = ? WHERE lot_part = ? AND lot_lot = ?',
                    [$expire, $vend, $rmks, $user, $row['ld_part'], $lot]);
                $db->run('UPDATE ld_det SET ld_expire = ?, updated_by = ? WHERE ld_part = ? AND ld_lot = ?',
                    [$expire, $user, $row['ld_part'], $lot]);
                if ($lotBefore) {
                    \Phai\Core\Audit\Audit::updated('lot_mstr', $row['ld_part'] . '~' . $lot, $lotBefore,
                        ['lot_expire_date' => $expire, 'lot_vend_lot' => $vend, 'lot_rmks' => $rmks] + $lotBefore);
                }
            }
            return $id;
        });
        return StockController::row($id);
    }

    /** ?part= — current unit cost and total on hand, for the price adjustment tab. */
    #[Get('/api/stock/moves/cost')]
    public function cost(Request $request): array
    {
        $part = (string) $request->input('part', '');
        return App::db()->one(
            "SELECT p.pt_cost AS cost, p.pt_um AS um, COALESCE(SUM(l.ld_qty_oh), 0) AS onhand
               FROM pt_mstr p LEFT JOIN ld_det l ON l.ld_part = p.pt_part AND l.ld_qty_oh > 0
              WHERE p.pt_part = ? GROUP BY p.pt_cost, p.pt_um",
            [$part]
        ) ?? throw HttpException::notFound();
    }
    /**
     * Raw material into a finished good — the only way a material becomes something to sell:
     * {kind: convert, date, site, reason, remark, lines: [{part, loc, lot, qty, fg_part, fg_qty, to_loc}]}.
     * The material goes out (ISS-CO) at its cost, the finished good comes in (RCT-CO) carrying that value,
     * its lot = this document number; the ledger moves the value from the material's stock account to the good's.
     */
    private function convert(array $b): array
    {
        ['head' => $head, 'lines' => $lines] = MoveLines::check($b, 'issue');
        $c = new Check();
        $fgs = [];
        foreach ($lines as $i => $l) {
            $raw = $b['lines'][$i];
            if (!Stock::isRawMaterial($l['part'])) $c->fail("lines.$i.part", t('stock.convert_rm_only'));
            $fg = $c->exists("lines.$i.fg_part", $raw['fg_part'] ?? '', 'pt_mstr', 'pt_part', true,
                "pt_inventory = 1 AND pt_prod_line IN (SELECT pl_prod_line FROM pl_mstr WHERE pl_inventory_type = 'FG')");
            $qty = $c->number("lines.$i.fg_qty", $raw['fg_qty'] ?? '', true, 0, true);
            $loc = $c->exists("lines.$i.to_loc", $raw['to_loc'] ?? '', 'loc_mstr', 'loc_loc', true, 'loc_site = ' . App::db()->pdo->quote((string) $head['site']));
            $fgs[$i] = [$fg, $qty, $loc];
        }
        $c->done();

        return App::db()->transaction(function () use ($head, $lines, $fgs) {
            $nbr = Numbering::next('IC', $head['date']);
            $n = 0;
            $common = ['site' => $head['site'], 'date' => $head['date'], 'nbr' => $nbr, 'reason' => $head['reason'],
                'remark' => $head['remark'], 'program' => 'stock-moves'];
            foreach ($lines as $i => $l) {
                [$fg, $fgQty, $toLoc] = $fgs[$i];
                $cost = (float) Stock::part($l['part'])['pt_cost'];
                Stock::move($common + ['type' => 'ISS-CO', 'part' => $l['part'], 'loc' => $l['loc'], 'lot' => $l['lot'],
                    'qty' => -$l['qty'], 'line' => ++$n, 'field' => "lines.$i.qty"]);
                $value = round($l['qty'] * $cost, 2);
                $fgPart = Stock::part($fg);
                Stock::move($common + ['type' => 'RCT-CO', 'part' => $fg, 'loc' => $toLoc, 'lot' => Stock::needsLot($fgPart) ? $nbr : '',
                    'qty' => $fgQty, 'cost' => round($value / $fgQty, 5), 'line' => ++$n]);
                $from = Gl::partAcct($l['part'], 'inv');
                $to = Gl::partAcct($fg, 'inv');
                if ($value != 0.0 && $from !== $to) {
                    Gl::post($nbr, 'IC', $head['date'], [[$to, $value], [$from, -$value]], "CO {$l['part']} > $fg");
                }
            }
            return ['nbr' => $nbr, 'lines' => $n, 'next' => Numbering::peek('IC')];
        });
    }
}
