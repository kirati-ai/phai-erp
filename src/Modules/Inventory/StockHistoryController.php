<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;

/** Every stock movement (tr_hist), newest first. */
#[Program('stock-history')]
final class StockHistoryController
{
    #[Get('/stock/history')]
    public function page(): string
    {
        return View::render('inventory/history', [
            'title'   => t('stock.history_title'),
            'sites'   => StockLookupController::sites(),
            'types'   => Stock::TYPES,
            'help'    => 'stock-history',
            'scripts' => ['js/stock-history-grid.js', 'js/pages/stock-history.js'],
        ]);
    }

    /** ?q= (part, name, document, lot) &part= (exact) &site= &type= &from= &to= &sort= &dir= &page= */
    #[Describe('Stock movements with quantity and unit cost', ['q' => 'part, description, document or lot', 'part' => 'exact part', 'site' => 'site', 'loc' => 'location', 'lot' => 'lot', 'type' => 'RCT-UNP receipt, ISS-UNP issue, ADJ adjust, ISS-TR/RCT-TR transfer, CYC-CNT count, RCT-PO purchase receipt, ISS-SO shipment, ISS-WO production issue, RCT-WO production receipt', 'from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD'])]
    #[Get('/api/stock/history')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        $exact = ['part' => 'tr.tr_part', 'site' => 'tr.tr_site', 'loc' => 'tr.tr_loc', 'lot' => 'tr.tr_lot', 'type' => 'tr.tr_type'];
        foreach ($exact as $name => $col) {
            if (($v = (string) $request->input($name, '')) !== '') {
                $where[] = "$col = ?";
                $params[] = $v;
            }
        }
        foreach (['from' => '>=', 'to' => '<='] as $name => $op) {
            $v = (string) $request->input($name, '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                $where[] = "tr.tr_date $op ?";
                $params[] = $v;
            }
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(tr.tr_part LIKE ? OR pt.pt_desc1 LIKE ? OR tr.tr_nbr LIKE ? OR tr.tr_lot LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $page = App::db()->page(
            'SELECT tr.tr_trnbr, tr.tr_date, tr.tr_time, tr.tr_type, tr.tr_nbr, tr.tr_part, pt.pt_desc1, tr.tr_site, tr.tr_loc,
                    tr.tr_lot, tr.tr_begin_qty, tr.tr_chg_qty, tr.tr_loc_qty, tr.tr_um, tr.tr_line, tr.tr_price, tr.tr_cost, tr.tr_rsn_code, tr.tr_rmks, tr.tr_userid',
            'FROM tr_hist tr LEFT JOIN pt_mstr pt ON pt.pt_part = tr.tr_part WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'tr_trnbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            [
                'tr_trnbr' => 'tr.tr_trnbr', 'tr_date' => 'tr.tr_date', 'tr_type' => 'tr.tr_type', 'tr_nbr' => 'tr.tr_nbr',
                'tr_part' => 'tr.tr_part', 'tr_site' => 'tr.tr_site', 'tr_loc' => 'tr.tr_loc', 'tr_chg_qty' => 'tr.tr_chg_qty',
            ],
            'tr_trnbr'
        );
        return $page;
    }
}
