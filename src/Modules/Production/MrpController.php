<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\View\View;
use Phai\Modules\Inventory\Stock;

/** Run MRP and turn planned orders into purchase requisitions or work orders. */
#[Program('mrp')]
final class MrpController
{
    #[Get('/mrp')]
    public function page(): string
    {
        return View::render('production/mrp', [
            'title'   => t('production.mrp_title'),
            'help'    => 'mrp',
            'config'  => [
                'canRun' => Auth::can('mrp', 'edit'),
                'canPr'  => Auth::can('mrp', 'edit') && Auth::can('purchase-requisitions', 'add'),
                'canWo'  => Auth::can('mrp', 'edit') && Auth::can('work-orders', 'add'),
                'today'  => date('Y-m-d'),
            ],
            'scripts' => ['js/pages/mrp.js'],
        ]);
    }

    /** Planned orders of the last run, its time, and the review: the MRP record of each item and the action messages. */
    #[Describe('MRP: rows = planned orders of the last run (mrp_type WO make / PO buy, order date, due date); ran_at / ran_by = the last MRP run; messages = action messages (RELEASE order the planned order now, IN reschedule in / expedite an open order that arrives after the need, OUT reschedule out, CANCEL not needed; date = now, to = suggested); items = every item planned, top level first: level, lead (days), lot, safety, onhand, need, coming (scheduled receipts), order (planned), end; grid = the MRP record by week (weeks: 0 = past due, then week start dates): gross, sched, poh (projected on hand), net, receipt, release; rows = each requirement (SO, WOC component of a work order, PLC component of a planned order), receipt (PO, PR, WO) and PLAN by date with the projected balance')]
    #[Get('/api/mrp')]
    public function list(): array
    {
        $run = App::db()->one('SELECT wo_mrp_run_at, wo_mrp_run_by FROM wo_ctrl WHERE ctrl_id = 1') ?? [];
        return [
            'rows' => App::db()->all(
                'SELECT m.*, pt.pt_desc1, pt.pt_um FROM mrp_det m LEFT JOIN pt_mstr pt ON pt.pt_part = m.mrp_part
                  ORDER BY m.mrp_rel_date, m.mrp_part'
            ),
            'ran_at' => $run['wo_mrp_run_at'] ?? null,
            'ran_by' => $run['wo_mrp_run_by'] ?? null,
        ] + Mrp::review();
    }

    #[Post('/api/mrp/run'), Action('edit')]
    public function run(): array
    {
        Mrp::run();
        return $this->list();
    }

    /** Body: {keys: [{part, due}]} — planned purchases become one requisition. */
    #[Post('/api/mrp/pr'), Action('edit')]
    public function toPr(Request $request): array
    {
        if (!Auth::can('purchase-requisitions', 'add')) {
            throw new HttpException(403, t('auth.forbidden'));
        }
        $rows = self::picked($request, 'PO');
        $nbr = App::db()->transaction(fn (Db $db) => self::makePr($db, $rows));
        return ['nbr' => $nbr];
    }

    /** Body: {keys: [{part, due}]} — one firm work order per planned row. */
    #[Post('/api/mrp/wo'), Action('edit')]
    public function toWo(Request $request): array
    {
        if (!Auth::can('work-orders', 'add')) {
            throw new HttpException(403, t('auth.forbidden'));
        }
        $rows = self::picked($request, 'WO');
        return ['wos' => App::db()->transaction(fn (Db $db) => self::makeWos($db, $rows))];
    }

    /** One purchase requisition for planned purchase rows (mrp_det, type PO), sized by the item's order rules; the rows go. */
    public static function makePr(Db $db, array $rows): string
    {
        $nbr = Numbering::next('PR');
        $user = Auth::userId();
        $total = 0.0;
        $line = 0;
        foreach ($rows as $r) {
            $part = Stock::part($r['mrp_part']);
            foreach (OrderSizing::lots((float) $r['mrp_qty'], $part) as $lot) {
                $amt = round($lot * (float) $part['pt_cost'], 2);
                $total += $amt;
                $db->insert('prd_det', [
                    'prd_nbr' => $nbr, 'prd_line' => ++$line, 'prd_part' => $r['mrp_part'], 'prd_desc' => $part['pt_desc1'],
                    'prd_qty_req' => $lot, 'prd_um' => $part['pt_um'], 'prd_req_date' => $r['mrp_due_date'],
                    'prd_est_cost' => $part['pt_cost'], 'prd_line_amt' => $amt, 'created_by' => $user, 'updated_by' => $user,
                ]);
            }
        }
        $db->insert('pr_mstr', [
            'pr_nbr' => $nbr, 'pr_req_by' => Auth::personName() ?: $user, 'pr_dept' => 'MRP', 'pr_date' => date('Y-m-d'),
            'pr_status' => 'A', 'pr_remarks' => t('production.from_mrp'), 'pr_tot_amt' => round($total, 2),
            'created_by' => $user, 'updated_by' => $user,
        ]);
        self::remove($db, $rows);
        return $nbr;
    }


    /** Planned make rows (mrp_det) -> firm work orders (split by order sizing); the rows leave the plan. Call inside a transaction. */
    public static function makeWos(Db $db, array $rows): array
    {
        $wos = [];
        foreach ($rows as $r) {
            foreach (OrderSizing::lots((float) $r['mrp_qty'], Stock::part($r['mrp_part'])) as $lot) {
                $wos[] = WoController::write(null, [
                    'wo_part' => $r['mrp_part'], 'wo_qty_ord' => $lot, 'wo_site' => $r['mrp_site'],
                    'wo_rel_date' => $r['mrp_rel_date'], 'wo_due_date' => max($r['mrp_due_date'], $r['mrp_rel_date']),
                    'wo_lot' => null, 'wo_rmks' => t('production.from_mrp'),
                ]);
            }
        }
        self::remove($db, $rows);
        return $wos;
    }

    /** The planned rows the user ticked, of one type. */
    private static function picked(Request $request, string $type): array
    {
        $rows = [];
        foreach (is_array($request->body['keys'] ?? null) ? $request->body['keys'] : [] as $k) {
            $row = is_array($k) ? App::db()->one(
                'SELECT * FROM mrp_det WHERE mrp_part = ? AND mrp_due_date = ? AND mrp_type = ?',
                [(string) ($k['part'] ?? ''), (string) ($k['due'] ?? ''), $type]
            ) : null;
            if ($row) {
                $rows[] = $row;
            }
        }
        if (!$rows) {
            throw new HttpException(422, t('production.pick_rows'));
        }
        return $rows;
    }

    private static function remove(Db $db, array $rows): void
    {
        foreach ($rows as $r) {
            $db->delete('mrp_det', ['mrp_part' => $r['mrp_part'], 'mrp_site' => $r['mrp_site'], 'mrp_due_date' => $r['mrp_due_date'], 'mrp_type' => $r['mrp_type']]);
        }
    }
}
