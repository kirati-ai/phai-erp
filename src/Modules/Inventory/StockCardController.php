<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;

/**
 * Stock card — รายงานสินค้าและวัตถุดิบ (ประมวลรัษฎากร ม.87(3)): per item over a period, every movement with the quantity
 * and value in and out and the running balance, from the opening balance before the period. One item, or every item
 * that has a balance or a movement (by stock type / site). It reads tr_hist, which Stock::move() writes for every change,
 * so each item's closing balance always equals its stock on hand at that date.
 */
#[Program('stock-card')]
final class StockCardController
{
    public const TYPES = ['FG', 'RM', 'FA'];

    #[Get('/stock-card')]
    public function page(): string
    {
        return View::render('inventory/stock-card', [
            'title'   => t('stock.card_title'),
            'help'    => 'stock-card',
            'sites'   => StockLookupController::sites(),
            'config'  => ['from' => date('Y-m-01'), 'to' => date('Y-m-t'), 'canPrint' => Auth::can('stock-card', 'print')],
            'scripts' => ['js/pages/stock-card.js'],
        ]);
    }

    #[Describe('Stock card per item: opening balance, each movement in/out with quantity, unit cost and value, running balance (baht); without part every item that has a balance or a movement, with the total value', ['part' => 'item code (empty: all items)', 'type' => 'FG finished goods | RM raw materials | FA fixed assets (empty: all)', 'site' => 'site (empty: all)', 'from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD'])]
    #[Get('/api/stock-card')]
    public function card(Request $request): array
    {
        return self::build(...self::filters($request));
    }

    #[Get('/stock-card/print'), Action('print')]
    public function print(Request $request): string
    {
        $report = self::build(...self::filters($request));
        return View::render('inventory/stock-card-print', [
            'title'    => t('stock.card_title'),
            'docTitle' => 'STOCK-CARD-' . ($report['part'] !== '' ? $report['part'] : 'ALL') . '-' . $report['to'],
            'report'   => $report,
            'company'  => App::db()->one('SELECT co_name, co_tax_id, co_branch, co_address FROM co_mstr WHERE co_id = 1') ?? [],
        ], 'blank');
    }

    /** part, type, site, from, to of the page's filters. */
    private static function filters(Request $request): array
    {
        return [
            (string) $request->input('part', ''), (string) $request->input('type', ''), (string) $request->input('site', ''),
            (string) $request->input('from', date('Y-m-01')), (string) $request->input('to', date('Y-m-d')),
        ];
    }

    public static function build(string $part, string $type, string $site, string $from, string $to): array
    {
        $ok = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
        if (!$ok($from) || !$ok($to) || $from > $to) {
            throw HttpException::invalid(['to' => t('accounting.bad_range')]);
        }
        $part = mb_strtoupper(trim($part));
        $type = strtoupper(trim($type));
        $where = [];
        $args = [];
        if ($part !== '') {
            $where[] = 'tr.tr_part = ?';
            $args[] = $part;
        }
        if ($type !== '') {
            $where[] = "COALESCE(pl.pl_inventory_type, 'FG') = ?";
            $args[] = $type;
        }
        if ($site !== '') {
            $where[] = 'tr.tr_site = ?';
            $args[] = $site;
        }
        $base = 'FROM tr_hist tr JOIN pt_mstr pt ON pt.pt_part = tr.tr_part LEFT JOIN pl_mstr pl ON pl.pl_prod_line = pt.pt_prod_line WHERE '
            . implode(' AND ', [...$where, '1 = 1']);
        $card = fn (array $r) => ['part' => ['pt_part' => $r['pt_part'], 'pt_desc1' => $r['pt_desc1'], 'pt_um' => $r['pt_um']],
            'opening' => ['qty' => 0.0, 'value' => 0.0], 'moves' => []];

        // opening balance before the period; an item with nothing before and nothing in the period is left out
        $cards = [];
        foreach (App::db()->all(
            "SELECT pt.pt_part, pt.pt_desc1, pt.pt_um, SUM(tr.tr_chg_qty) AS qty, SUM(tr.tr_chg_qty * tr.tr_cost) AS value
               $base AND tr.tr_date < ? GROUP BY pt.pt_part, pt.pt_desc1, pt.pt_um",
            [...$args, $from]
        ) as $r) {
            $qty = round((float) $r['qty'], 5);
            $value = round((float) $r['value'], 2);
            if ($qty != 0.0 || $value != 0.0) {
                $cards[$r['pt_part']] = ['opening' => ['qty' => $qty, 'value' => $value]] + $card($r);
            }
        }
        foreach (App::db()->all(
            "SELECT pt.pt_part, pt.pt_desc1, pt.pt_um, tr.tr_date, tr.tr_type, tr.tr_nbr, tr.tr_site, tr.tr_loc, tr.tr_lot, tr.tr_chg_qty, tr.tr_cost
               $base AND tr.tr_date BETWEEN ? AND ? ORDER BY tr.tr_part, tr.tr_date, tr.tr_trnbr",
            [...$args, $from, $to]
        ) as $t) {
            $cards[$t['pt_part']] ??= $card($t);
            $cards[$t['pt_part']]['moves'][] = $t;
        }
        if ($part !== '' && !$cards) {
            $p = App::db()->one('SELECT pt_part, pt_desc1, pt_um FROM pt_mstr WHERE pt_part = ?', [$part])
                ?? throw HttpException::invalid(['part' => t('stock.no_part')]);
            $cards[$part] = $card($p);
        }
        ksort($cards, SORT_STRING);

        $out = [];
        foreach ($cards as $c) {
            $qty = $c['opening']['qty'];
            $value = $c['opening']['value'];
            $rows = [];
            foreach ($c['moves'] as $t) {
                $q = (float) $t['tr_chg_qty'];
                $v = round($q * (float) $t['tr_cost'], 2);
                $qty = round($qty + $q, 5);
                $value = round($value + $v, 2);
                $rows[] = [
                    'date' => $t['tr_date'], 'nbr' => $t['tr_nbr'], 'type' => $t['tr_type'], 'type_text' => t('stock.type_' . $t['tr_type']),
                    'where' => trim($t['tr_site'] . ' / ' . $t['tr_loc'] . ($t['tr_lot'] !== '' ? ' / ' . $t['tr_lot'] : '')),
                    'cost' => round((float) $t['tr_cost'], 5),
                    'in_qty' => $q > 0 ? $q : null, 'in_value' => $q > 0 || ($q == 0.0 && $v > 0) ? $v : null,
                    'out_qty' => $q < 0 ? -$q : null, 'out_value' => $q < 0 ? -$v : null,
                    'qty' => $qty, 'value' => $value,
                ];
            }
            $out[] = [
                'part' => $c['part'], 'opening' => $c['opening'], 'rows' => $rows,
                'in' => ['qty' => round(array_sum(array_column($rows, 'in_qty')), 5), 'value' => round(array_sum(array_column($rows, 'in_value')), 2)],
                'out' => ['qty' => round(array_sum(array_column($rows, 'out_qty')), 5), 'value' => round(array_sum(array_column($rows, 'out_value')), 2)],
                'closing' => ['qty' => $qty, 'value' => $value],
            ];
        }
        $sum = fn (string $k) => round(array_sum(array_map(fn ($c) => $c[$k]['value'], $out)), 2);
        return [
            'part' => $part, 'type' => $type, 'site' => $site, 'from' => $from, 'to' => $to,
            'cards' => $out,
            'total' => ['opening' => $sum('opening'), 'in' => $sum('in'), 'out' => $sum('out'), 'closing' => $sum('closing')],
        ];
    }
}
