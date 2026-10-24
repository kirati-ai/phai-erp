<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

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
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Inventory\Stock;

/**
 * Ship goods on an open sales order: stock goes out of the order's warehouse,
 * order lines count what was shipped, and the order closes when everything is out.
 * Status: C shipped (waiting for an invoice), I invoiced.
 */
#[Program('shipments')]
final class ShipmentController
{
    #[Get('/shipments')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'    => 'shipments',
            'title'   => t('sales.sh_title'),
            'config'  => [
                'api'     => '/api/shipments',
                'files'   => 'SH',
                'page'    => '/shipments',
                'canAdd'  => Auth::can('shipments', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'sh_nbr', 'label' => t('sales.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'sh_date', 'label' => t('sales.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'sh_so_nbr', 'label' => t('sales.so'), 'sort' => true],
                    ['key' => 'sh_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'sh_inv_nbr', 'label' => t('sales.invoice')],
                    ['key' => 'sh_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Open sales orders not fully shipped above the list: one click opens a new shipment for the order. */
    public static function queues(): array
    {
        return Auth::can('shipments', 'add') ? [[
            'title'   => t('sales.queue_sh'),
            'api'     => '/api/shipments/waiting',
            'key'     => 'so_nbr',
            'urgent'  => 'late',
            // one row per order line: what has to go out, and whether the stock is there for it
            'columns' => [
                ['key' => 'so_nbr', 'label' => t('sales.so'), 'link' => '/sales-orders/{so_nbr}'],
                ['key' => 'want_date', 'label' => t('sales.req_date'), 'type' => 'date'],
                ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                ['key' => 'ship_place', 'label' => t('sales.ship_place')],
                ['key' => 'sod_part', 'label' => t('sales.part')],
                ['key' => 'part_desc', 'label' => t('sales.desc')],
                ['key' => 'open_qty', 'label' => t('sales.qty_open'), 'type' => 'num'],
                ['key' => 'sod_um', 'label' => t('stock.um')],
                ['key' => 'onhand', 'label' => t('stock.onhand_here'), 'type' => 'num'],
                ['key' => 'stock', 'label' => t('sales.stock_state'), 'type' => 'badge'],
                ['key' => 'supply', 'label' => t('sales.supply')],
                ['key' => 'ready_date', 'label' => t('sales.ready_date'), 'type' => 'date'],
            ],
            'action'  => ['label' => t('sales.ship'), 'icon' => 'ti ti-truck-delivery', 'href' => '/shipments/new?so={so_nbr}'],
        ]] : [];
    }

    #[Get('/shipments/new')]
    public function newPage(Request $request): string
    {
        return $this->page(null, (string) $request->input('so', ''));
    }

    #[Get('/shipments/{nbr}')]
    public function viewPage(string $nbr): string
    {
        self::head($nbr);
        return $this->page($nbr, '');
    }

    /** Delivery note. */
    #[Get('/shipments/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 5), '0'), '.');
        return SalesPrint::render([
            'title' => t('sales.delivery_note'),
            'nbr'   => $nbr,
            'meta'  => [[t('sales.date'), SalesPrint::date($h['sh_date'])], [t('sales.so'), $h['sh_so_nbr']], [t('sales.po_ref'), $h['so_po_ref']],
                        [t('sales.ship_via'), $h['sh_ship_via']], [t('sales.tracking'), $h['sh_tracking']]],
            'party' => SalesPrint::customer($h['sh_cm_code']),
            'lines' => [
                'columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('sales.part') . ' / ' . t('sales.desc')],
                    ['label' => t('sales.lot'), 'width' => '110px'], ['label' => t('sales.qty'), 'align' => 'r', 'width' => '80px'],
                    ['label' => t('sales.um'), 'width' => '60px'],
                ],
                'rows' => array_map(fn ($l) => [$l['shd_line'], $l['shd_part'] . ' ' . ($l['pt_desc1'] ?? ''), $l['shd_lot'], $q($l['shd_qty_shp']), $l['shd_um']], self::lines($nbr)),
            ],
            'signs' => [t('sales.sign_receiver'), t('sales.sign_sender')],
        ]);
    }

    #[Describe('Shipments (delivery notes)', ['q' => 'number, order or customer', 'status' => 'C shipped not invoiced, I invoiced'])]

    #[Get('/api/shipments')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['C', 'I'], true)) {
            $where[] = 'h.sh_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(h.sh_nbr LIKE ? OR h.sh_so_nbr LIKE ? OR h.sh_cm_code LIKE ? OR c.cm_name LIKE ? OR h.sh_inv_nbr LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT h.sh_nbr, h.sh_date, h.sh_so_nbr, h.sh_cm_code, c.cm_name, h.sh_inv_nbr, h.sh_status',
            'FROM sh_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.sh_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'sh_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['sh_nbr' => 'h.sh_nbr', 'sh_date' => 'h.sh_date', 'sh_so_nbr' => 'h.sh_so_nbr', 'sh_cm_code' => 'h.sh_cm_code', 'sh_status' => 'h.sh_status'],
            'sh_nbr'
        );
    }

    /** ?q= — open sales orders that still have something to ship. */
    #[Describe('Open sales orders still to ship', ['q' => 'order number or customer'])]
    #[Get('/api/shipments/open-sos')]
    public function openSos(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            "SELECT s.so_nbr, s.so_cm_code, c.cm_name FROM so_mstr s LEFT JOIN cm_mstr c ON c.cm_code = s.so_cm_code
              WHERE s.so_status = 'O' AND (s.so_nbr LIKE ? OR s.so_cm_code LIKE ? OR c.cm_name LIKE ? OR s.so_po_ref LIKE ?)
              ORDER BY s.so_nbr DESC LIMIT 20",
            [$like, $like, $like, $like]
        );
    }

    /**
     * Sales-order lines still to ship, earliest wanted first, with the stock at the order's site: the lines wanted first
     * take it first, so "enough" on a row means it can really go out once the rows above it have.
     */
    #[Describe('Sales-order lines waiting for a shipment, earliest wanted first: ship_place = customer district, province; open_qty left to ship, onhand at the site, stock = enough / short by / none / not stocked (stock is given to earlier lines first); when short, supply = open work orders / purchase orders that cover it and ready_date = the date the last of them is due (empty supply = nothing planned); from_stock = what the stock gives the line, short = what no order covers yet; late = wanted date passed')]
    #[Get('/api/shipments/waiting')]
    public function waiting(): array
    {
        return Supply::lines();
    }

    /** The order to ship: header and open lines with what is left. */
    #[Get('/api/shipments/so/{so}')]
    public function so(string $so): array
    {
        $head = SoController::head($so);
        if ($head['so_status'] !== 'O') {
            throw new HttpException(409, t('sales.so_not_open'));
        }
        $lines = array_values(array_filter(SoController::lines($so), fn ($l) => $l['sod_status'] === 'O'));
        foreach ($lines as &$l) {
            $l['open_qty'] = max(0, round((float) $l['sod_qty_ord'] - (float) $l['sod_qty_shp'], 5));
        }
        return ['head' => $head, 'lines' => $lines];
    }

    #[Describe('One shipment with its lines')]

    #[Get('/api/shipments/{nbr}')]
    public function show(string $nbr): array
    {
        return ['head' => self::head($nbr), 'lines' => self::lines($nbr)];
    }

    /** Body: {so, sh_date, sh_ship_via, sh_tracking, invoice (bool), lines: {sod_line: {qty, loc, lot}}} */
    #[Post('/api/shipments')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $so = $c->exists('so', $b['so'] ?? '', 'so_mstr', 'so_nbr', true, "so_status = 'O'");
        $h = [
            'sh_date'     => $c->date('sh_date', $b['sh_date'] ?? '', true),
            'sh_ship_via' => $c->text('sh_ship_via', $b['sh_ship_via'] ?? '', false, 50, false),
            'sh_tracking' => $c->text('sh_tracking', $b['sh_tracking'] ?? '', false, 100, false),
        ];
        if ($h['sh_date'] && $h['sh_date'] > date('Y-m-d')) {
            $c->fail('sh_date', t('stock.future_date'));
        }
        $c->done();

        $open = array_column($this->so($so)['lines'], null, 'sod_line');
        $lines = [];
        $sum = []; // order line => quantity over all its rows (one row per location / lot: "<line>" or "<line>~<n>")
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $key => $l) {
            $sodLine = (int) explode('~', (string) $key)[0];
            if (!is_array($l) || !isset($open[$sodLine])) {
                continue;
            }
            $f = fn (string $n) => "lines.$key.$n";
            $qty = $c->number($f('qty'), $l['qty'] ?? '', false, 0);
            if (!$qty) {
                continue;
            }
            $sod = $open[$sodLine];
            $sum[$sodLine] = round(($sum[$sodLine] ?? 0) + $qty, 5);
            if ($sum[$sodLine] > (float) $sod['open_qty']) {
                $c->fail($f('qty'), t('purchase.over_receipt', ['max' => Stock::fmt((float) $sod['open_qty'])]));
            }
            // items not kept in stock (services, free text) need no location and never touch stock
            $stocked = Stock::stocked($sod['sod_part']);
            $loc = $stocked ? $c->exists($f('loc'), $l['loc'] ?? '', 'loc_mstr', 'loc_loc', true, 'loc_site = ' . App::db()->pdo->quote((string) $sod['sod_site'])) : '';
            $needsLot = $stocked && in_array($sod['pt_lot_ser'], ['L', 'S'], true);
            $lot = $c->text($f('lot'), $l['lot'] ?? '', false, 50, false);
            if ($needsLot && $lot === null) {
                $c->fail($f('lot'), t('stock.lot_required'));
            }
            $lines[$key] = ['line' => $sodLine, 'sod' => $sod, 'stocked' => $stocked, 'qty' => $qty, 'loc' => $loc, 'lot' => $needsLot ? (string) $lot : ''];
        }
        if (!$lines && !$c->errors) {
            $c->fail('lines', t('sales.nothing_shipped'));
        }
        $c->done();

        return App::db()->transaction(function (Db $db) use ($so, $h, $lines, $sum, $b) {
            // Lock the order lines and check again: someone may have shipped meanwhile.
            $now = array_column($db->all('SELECT sod_line, sod_qty_ord, sod_qty_shp FROM sod_det WHERE sod_nbr = ? FOR UPDATE', [$so]), null, 'sod_line');
            foreach ($sum as $sodLine => $qty) {
                $left = (float) $now[$sodLine]['sod_qty_ord'] - (float) $now[$sodLine]['sod_qty_shp'];
                if ($qty > round($left, 5)) {
                    throw HttpException::invalid(["lines.$sodLine.qty" => t('purchase.over_receipt', ['max' => Stock::fmt(max(0, $left))])]);
                }
            }

            $soHead = SoController::head($so);
            $nbr = Numbering::next('SH', $h['sh_date']);
            $user = Auth::userId();
            $db->insert('sh_mstr', $h + [
                'sh_nbr' => $nbr, 'sh_so_nbr' => $so, 'sh_cm_code' => $soHead['so_cm_code'], 'sh_status' => 'C',
                'created_by' => $user, 'updated_by' => $user,
            ]);
            $n = 0;
            foreach ($lines as $key => $l) {
                $sod = $l['sod'];
                $sodLine = $l['line'];
                $db->insert('shd_det', [
                    'shd_nbr' => $nbr, 'shd_line' => ++$n, 'shd_sod_line' => $sodLine, 'shd_part' => $sod['sod_part'],
                    'shd_qty_shp' => $l['qty'], 'shd_um' => $sod['sod_um'], 'shd_loc' => $l['loc'], 'shd_lot' => $l['lot'],
                    'created_by' => $user, 'updated_by' => $user,
                ]);
                if ($l['stocked']) {
                    Stock::move([
                        'type' => 'ISS-SO', 'part' => $sod['sod_part'], 'site' => $sod['sod_site'], 'loc' => $l['loc'], 'lot' => $l['lot'],
                        'qty' => -$l['qty'], 'price' => round((float) $sod['sod_price'] * (1 - (float) $sod['sod_disc_pct'] / 100), 5),
                        'date' => $h['sh_date'], 'nbr' => $nbr, 'line' => $n, 'remark' => $so, 'program' => 'shipments',
                        'field' => "lines.$key.qty",
                    ]);
                }
                $db->run(
                    "UPDATE sod_det SET sod_qty_shp = sod_qty_shp + ?, sod_status = IF(sod_qty_shp >= sod_qty_ord, 'C', 'O'), updated_by = ?
                      WHERE sod_nbr = ? AND sod_line = ?",
                    [$l['qty'], $user, $so, $sodLine]
                );
            }
            if (!$db->value("SELECT 1 FROM sod_det WHERE sod_nbr = ? AND sod_status = 'O'", [$so]) && !$db->value('SELECT so_sched FROM so_mstr WHERE so_nbr = ?', [$so])) {
                $db->update('so_mstr', ['so_status' => 'C', 'updated_by' => $user], ['so_nbr' => $so]);
            }

            $res = ['nbr' => $nbr];
            if (!empty($b['invoice']) && filter_var($b['invoice'], FILTER_VALIDATE_BOOL)) {
                if (!Auth::can('invoices', 'add')) {
                    throw new HttpException(403, t('auth.forbidden'));
                }
                $res['inv'] = InvoiceController::fromShipment($nbr, $h['sh_date']);
            }
            return $res;
        });
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT h.*, c.cm_name, s.so_po_ref FROM sh_mstr h
               LEFT JOIN cm_mstr c ON c.cm_code = h.sh_cm_code
               LEFT JOIN so_mstr s ON s.so_nbr = h.sh_so_nbr
              WHERE h.sh_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, COALESCE(so.sod_desc, pt.pt_desc1) AS pt_desc1 FROM shd_det d JOIN sh_mstr h ON h.sh_nbr = d.shd_nbr
               LEFT JOIN sod_det so ON so.sod_nbr = h.sh_so_nbr AND so.sod_line = d.shd_sod_line
               LEFT JOIN pt_mstr pt ON pt.pt_part = d.shd_part WHERE d.shd_nbr = ? ORDER BY d.shd_line',
            [$nbr]
        );
    }

    private function page(?string $nbr, string $so): string
    {
        if ($nbr !== null) { // a saved shipment is a read-only view like a purchase order (ux/ui.md "View pages")
            return View::render('sales/shipment-view', [
                'help'    => 'shipments',
                'title'      => $nbr,
                'ownToolbar' => true,
                'head'       => self::head($nbr),
                'lines'      => self::lines($nbr),
                'status'     => self::statusText(),
                'canInvoice' => Auth::can('invoices', 'add'),
                'canPrint'   => Auth::can('shipments', 'print'),
                'scripts'    => ['js/view.js'],
            ]);
        }
        return View::render('sales/shipment', [
            'help'    => 'shipments',
            'docNbr' => $nbr === null ? Numbering::peek('SH') : null,
            'title'   => $nbr ?? t('sales.sh_new'),
            'nbr'     => $nbr,
            'so'      => $so,
            'canInvoice' => Auth::can('invoices', 'add'),
            'canPrint' => Auth::can('shipments', 'print'),
            'scripts' => ['js/pages/shipment.js'],
        ]);
    }

    public static function statusText(): array
    {
        return [
            'C' => ['text' => t('sales.sh_status_C'), 'color' => 'yellow'],
            'I' => ['text' => t('sales.sh_status_I'), 'color' => 'green'],
        ];
    }
}
