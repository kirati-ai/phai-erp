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
use Phai\Modules\Accounting\Gl;
use Phai\Modules\Inventory\Stock;

/**
 * ใบรับคืนสินค้า (SR): goods a customer sends back, against the shipment they left on. They come back into the lot they
 * went out from (RCT-RTN) at the cost they went out at, so cost of sales is undone exactly: Dr inventory / Cr cost of sales.
 * The money side is the customer's credit note (ใบลดหนี้, ม.86/10) made from it on the AR invoices page: Dr sales,
 * Dr output VAT / Cr AR — status O (waiting for the credit note) → C.
 */
#[Program('sales-returns')]
final class SalesReturnController
{
    #[Get('/sales-returns')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'   => 'sales-returns',
            'title'  => t('sales.sr_title'),
            'config' => [
                'api'     => '/api/sales-returns',
                'files'   => 'SR',
                'page'    => '/sales-returns',
                'canAdd'  => false, // a return starts from its shipment
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'sr_nbr', 'label' => t('sales.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'sr_date', 'label' => t('sales.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'sr_sh_nbr', 'label' => t('sales.shipment'), 'sort' => true],
                    ['key' => 'sr_inv_nbr', 'label' => t('sales.invoice')],
                    ['key' => 'sr_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'sr_cn_nbr', 'label' => t('sales.credit_note')],
                    ['key' => 'sr_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'scripts' => ['js/doclist.js'],
        ]);
    }

    #[Get('/sales-returns/new'), Action('add')]
    public function newPage(Request $request): string
    {
        return View::render('sales/return-new', [
            'help'    => 'sales-returns',
            'title'   => t('sales.sr_new'),
            'docNbr'  => Numbering::peek('SR'),
            'config'  => ['sh' => (string) $request->input('sh', ''), 'today' => date('Y-m-d')],
            'scripts' => ['js/pages/sales-return.js'],
        ]);
    }

    #[Get('/sales-returns/{nbr}')]
    public function viewPage(string $nbr): string
    {
        return View::render('sales/return-view', [
            'help'       => 'sales-returns',
            'title'      => $nbr,
            'ownToolbar' => true,
            'head'       => self::head($nbr),
            'lines'      => self::lines($nbr),
            'status'     => self::statusText(),
            'canPrint'   => Auth::can('sales-returns', 'print'),
        ]);
    }

    #[Describe('Sales returns (goods customers sent back)', ['q' => 'return, shipment, invoice or customer', 'status' => 'O waiting for the credit note, C credit note issued'])]
    #[Get('/api/sales-returns')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($s = (string) $request->input('status', ''), ['O', 'C'], true)) {
            $where[] = 'r.sr_status = ?';
            $params[] = $s;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(r.sr_nbr LIKE ? OR r.sr_sh_nbr LIKE ? OR r.sr_inv_nbr LIKE ? OR r.sr_cm_code LIKE ? OR c.cm_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT r.sr_nbr, r.sr_date, r.sr_sh_nbr, r.sr_inv_nbr, r.sr_cm_code, c.cm_name, r.sr_cn_nbr, r.sr_status',
            'FROM sr_mstr r LEFT JOIN cm_mstr c ON c.cm_code = r.sr_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'sr_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['sr_nbr' => 'r.sr_nbr', 'sr_date' => 'r.sr_date', 'sr_sh_nbr' => 'r.sr_sh_nbr', 'sr_cm_code' => 'r.sr_cm_code', 'sr_status' => 'r.sr_status'],
            'sr_nbr'
        );
    }

    #[Describe('Sales returns waiting for a credit note (status O), oldest first')]
    #[Get('/api/sales-returns/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            "SELECT r.sr_nbr, r.sr_date, r.sr_sh_nbr, r.sr_inv_nbr, r.sr_cm_code, c.cm_name, r.sr_reason
               FROM sr_mstr r LEFT JOIN cm_mstr c ON c.cm_code = r.sr_cm_code
              WHERE r.sr_status = 'O' ORDER BY r.sr_date, r.sr_nbr LIMIT 200"
        );
    }

    /** ?q= — delivery notes with something left that can come back, for the shipment picker. */
    #[Get('/api/sales-returns/shipments'), Action('add')]
    public function shipments(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            "SELECT h.sh_nbr, h.sh_date, h.sh_cm_code, c.cm_name, h.sh_so_nbr FROM sh_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.sh_cm_code
              WHERE h.sh_status IN ('C', 'I') AND (h.sh_nbr LIKE ? OR h.sh_cm_code LIKE ? OR c.cm_name LIKE ? OR h.sh_so_nbr LIKE ?)
                AND (SELECT SUM(d.shd_qty_shp) FROM shd_det d WHERE d.shd_nbr = h.sh_nbr)
                  > COALESCE((SELECT SUM(x.srd_qty) FROM srd_det x JOIN sr_mstr r ON r.sr_nbr = x.srd_nbr WHERE r.sr_sh_nbr = h.sh_nbr), 0)
              ORDER BY h.sh_nbr DESC LIMIT 20",
            [$like, $like, $like, $like]
        );
    }

    /** The lines of a shipment with what can still come back (shipped − returned) and the cost each went out at. */
    #[Get('/api/sales-returns/shipment/{sh}'), Action('add')]
    public function shipment(string $sh): array
    {
        $head = ShipmentController::head($sh);
        $lines = App::db()->all(
            "SELECT d.shd_line, d.shd_part, pt.pt_desc1, d.shd_qty_shp, d.shd_um, d.shd_loc, d.shd_lot, s.sod_site AS site,
                    COALESCE((SELECT t.tr_cost FROM tr_hist t WHERE t.tr_nbr = d.shd_nbr AND t.tr_line = d.shd_line AND t.tr_type = 'ISS-SO' LIMIT 1), 0) AS cost,
                    d.shd_qty_shp - COALESCE((SELECT SUM(x.srd_qty) FROM srd_det x JOIN sr_mstr r ON r.sr_nbr = x.srd_nbr
                                               WHERE r.sr_sh_nbr = d.shd_nbr AND x.srd_sh_line = d.shd_line), 0) AS returnable
               FROM shd_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.shd_part
               LEFT JOIN sod_det s ON s.sod_nbr = ? AND s.sod_line = d.shd_sod_line
              WHERE d.shd_nbr = ? ORDER BY d.shd_line",
            [$head['sh_so_nbr'], $sh]
        );
        return ['head' => $head, 'lines' => $lines];
    }

    #[Describe('One sales return with its lines')]
    #[Get('/api/sales-returns/{nbr}')]
    public function show(string $nbr): array
    {
        return ['head' => self::head($nbr), 'lines' => self::lines($nbr)];
    }

    /** Body: {sh, sr_date, sr_reason, lines: {shd_line: qty}}. */
    #[Post('/api/sales-returns')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $sh = $c->exists('sh', $b['sh'] ?? '', 'sh_mstr', 'sh_nbr');
        $date = $c->date('sr_date', $b['sr_date'] ?? '', true);
        $reason = $c->text('sr_reason', $b['sr_reason'] ?? '', true, 255, false);
        $want = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $line => $qty) {
            if (trim((string) $qty) === '') {
                continue;
            }
            $q = $c->number("lines.$line.qty", $qty, true, 0, true, 5);
            if ($q) {
                $want[(int) $line] = $q;
            }
        }
        if (!$want && $c->ok('lines')) {
            $c->fail('lines', t('sales.sr_nothing'));
        }
        if ($date && $date < Gl::periodStart()) {
            $c->fail('sr_date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $c->done();

        $nbr = App::db()->transaction(function (Db $db) use ($sh, $date, $reason, $want) {
            $h = $db->one('SELECT * FROM sh_mstr WHERE sh_nbr = ? FOR UPDATE', [$sh]);
            $lines = array_column($this->shipment($sh)['lines'], null, 'shd_line');
            $nbr = Numbering::next('SR', $date);
            $user = Auth::userId();
            $n = 0;
            $site = null;
            foreach ($want as $line => $qty) {
                $l = $lines[$line] ?? throw HttpException::invalid(["lines.$line.qty" => t('validation.in')]);
                if ($qty > round((float) $l['returnable'], 5)) {
                    throw HttpException::invalid(["lines.$line.qty" => t('sales.sr_over', ['max' => Stock::fmt((float) $l['returnable'])])]);
                }
                $n++;
                $site ??= $l['site'];
                if (Stock::stocked($l['shd_part'])) {
                    // back into the lot it left from, at the cost it left at: Dr inventory / Cr cost of sales (Gl::stock)
                    Stock::move([
                        'type' => 'RCT-RTN', 'part' => $l['shd_part'], 'site' => $l['site'], 'loc' => $l['shd_loc'], 'lot' => (string) $l['shd_lot'],
                        'qty' => $qty, 'cost' => (float) $l['cost'], 'date' => $date, 'nbr' => $nbr, 'line' => $n, 'program' => 'sales-returns',
                        'remark' => $sh,
                    ]);
                }
                $db->insert('srd_det', [
                    'srd_nbr' => $nbr, 'srd_line' => $n, 'srd_sh_line' => $line, 'srd_part' => $l['shd_part'], 'srd_qty' => $qty,
                    'srd_um' => $l['shd_um'], 'srd_loc' => $l['shd_loc'], 'srd_lot' => (string) $l['shd_lot'], 'srd_cost' => $l['cost'],
                    'created_by' => $user, 'updated_by' => $user,
                ]);
            }
            $db->insert('sr_mstr', [
                'sr_nbr' => $nbr, 'sr_sh_nbr' => $sh, 'sr_so_nbr' => $h['sh_so_nbr'], 'sr_inv_nbr' => $h['sh_inv_nbr'], 'sr_cm_code' => $h['sh_cm_code'],
                'sr_site' => (string) $site, 'sr_date' => $date, 'sr_reason' => $reason, 'sr_status' => 'O', 'created_by' => $user, 'updated_by' => $user,
            ]);
            return $nbr;
        });
        return ['nbr' => $nbr];
    }

    #[Get('/sales-returns/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 5, '.', ','), '0'), '.');
        return SalesPrint::render([
            'title' => t('sales.sr_title'), 'nbr' => $nbr,
            'meta'  => [[t('sales.date'), SalesPrint::date($h['sr_date'])], [t('sales.shipment'), $h['sr_sh_nbr']],
                        [t('sales.invoice'), (string) $h['sr_inv_nbr']], [t('sales.so'), (string) $h['sr_so_nbr']]],
            'party' => SalesPrint::customer($h['sr_cm_code']),
            'lines' => ['columns' => [
                ['label' => '#', 'width' => '28px'], ['label' => t('stock.part') . ' / ' . t('stock.desc')], ['label' => t('stock.qty'), 'align' => 'r'],
                ['label' => t('stock.um')], ['label' => t('stock.loc')], ['label' => t('stock.lot')]],
                'rows' => array_map(fn ($l) => [$l['srd_line'], $l['srd_part'] . ' ' . ($l['pt_desc1'] ?? ''), $q($l['srd_qty']), $l['srd_um'],
                    $l['srd_loc'], $l['srd_lot']], self::lines($nbr))],
            'notes' => t('sales.sr_reason') . ': ' . $h['sr_reason'],
            'signs' => [t('sales.sr_sign_customer'), t('sales.sr_sign_receiver')],
        ]);
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT r.*, c.cm_name FROM sr_mstr r LEFT JOIN cm_mstr c ON c.cm_code = r.sr_cm_code WHERE r.sr_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, pt.pt_desc1 FROM srd_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.srd_part WHERE d.srd_nbr = ? ORDER BY d.srd_line',
            [$nbr]
        );
    }

    public static function statusText(): array
    {
        return ['O' => ['text' => t('sales.sr_status_O'), 'color' => 'yellow'], 'C' => ['text' => t('sales.sr_status_C'), 'color' => 'green']];
    }
}
