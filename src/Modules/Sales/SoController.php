<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Crud\Lookup;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Inventory\StockLookupController;
use Phai\Modules\Master\Money;
use Phai\Modules\Master\Schedule;

/**
 * Sales orders, no approval step. Status: O open (can be shipped), C closed, X cancelled.
 * With so_cr_chk on, an order over the customer's credit limit is saved with a warning.
 */
#[Program('sales-orders')]
final class SoController
{
    #[Get('/sales-orders')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'    => 'sales-orders',
            'title'   => t('sales.so_title'),
            'config'  => [
                'api'     => '/api/sales-orders',
                'files'   => 'SO',
                'page'    => '/sales-orders',
                'canAdd'  => Auth::can('sales-orders', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'so_nbr', 'label' => t('sales.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'so_date', 'label' => t('sales.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'so_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'so_po_ref', 'label' => t('sales.po_ref')],
                    ['key' => 'so_req_date', 'label' => t('sales.req_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'so_tot_amt', 'label' => t('sales.total'), 'type' => 'money', 'sort' => true, 'suffix' => 'so_curr'],
                    ['key' => 'so_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Open quotations not expired above the list: one click makes the sales order. */
    public static function queues(): array
    {
        return Auth::can('sales-orders', 'add') ? [[
            'title'   => t('sales.queue_so'),
            'api'     => '/api/sales-orders/waiting',
            'key'     => 'qt_nbr',
            'columns' => [
                ['key' => 'qt_nbr', 'label' => t('sales.qt_title'), 'link' => '/quotations/{qt_nbr}'],
                ['key' => 'qt_date', 'label' => t('sales.date'), 'type' => 'date'],
                ['key' => 'qt_cm_code', 'label' => t('sales.customer')],
                ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                ['key' => 'qt_expire', 'label' => t('sales.valid_until'), 'type' => 'date'],
                ['key' => 'qt_tot_amt', 'label' => t('sales.total'), 'type' => 'money'],
            ],
            'action'  => ['label' => t('sales.make_so'), 'icon' => 'ti ti-file-plus', 'post' => '/api/sales-orders/from-qt',
                          'body' => ['qt' => '{qt_nbr}'], 'open' => '/sales-orders/{nbr}', 'confirm' => t('sales.make_so_confirm')],
        ]] : [];
    }

    #[Get('/sales-orders/new')]
    public function newPage(): string
    {
        return $this->editor(null);
    }

    #[Get('/sales-orders/{nbr}')]
    public function editPage(string $nbr): string
    {
        self::head($nbr);
        return $this->editor($nbr);
    }

    #[Describe('Sales orders: customer, dates, totals in the order currency', ['q' => 'number, customer or customer PO', 'status' => 'P waiting credit approval, O open, C closed/fully shipped, X cancelled'])]

    #[Get('/api/sales-orders')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['O', 'C', 'X'], true)) {
            $where[] = 's.so_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(s.so_nbr LIKE ? OR s.so_cm_code LIKE ? OR c.cm_name LIKE ? OR s.so_po_ref LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT s.so_nbr, s.so_date, s.so_cm_code, c.cm_name, s.so_po_ref, s.so_req_date, s.so_curr, ROUND(s.so_tot_amt - s.so_tax_amt, 2) AS so_net_amt, s.so_tax_amt, s.so_tot_amt, s.so_status',
            'FROM so_mstr s LEFT JOIN cm_mstr c ON c.cm_code = s.so_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'so_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['so_nbr' => 's.so_nbr', 'so_date' => 's.so_date', 'so_cm_code' => 's.so_cm_code', 'so_req_date' => 's.so_req_date',
             'so_net_amt' => 'ROUND(s.so_tot_amt - s.so_tax_amt, 2)', 'so_tax_amt' => 's.so_tax_amt', 'so_tot_amt' => 's.so_tot_amt', 'so_status' => 's.so_status'],
            'so_nbr'
        );
    }

    #[Describe('Quotations waiting for a sales order: status O (open) and not expired, oldest first')]
    #[Get('/api/sales-orders/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            "SELECT q.qt_nbr, q.qt_date, q.qt_cm_code, c.cm_name, q.qt_expire, q.qt_curr, q.qt_tot_amt
               FROM qt_mstr q LEFT JOIN cm_mstr c ON c.cm_code = q.qt_cm_code
              WHERE q.qt_status = 'O' AND q.qt_expire >= ?
              ORDER BY q.qt_expire, q.qt_nbr LIMIT 200",
            [date('Y-m-d')]
        );
    }

    #[Describe('One sales order with lines: ordered and shipped quantities, prices')]

    #[Get('/api/sales-orders/{nbr}')]
    public function show(string $nbr): array
    {
        $head = self::head($nbr);
        $lines = self::lines($nbr);
        $head['so_site'] = $lines[0]['sod_site'] ?? null;
        $head['_editable'] = self::editable($head, $lines) && Auth::can('sales-orders', 'edit');
        return ['head' => $head, 'lines' => $lines];
    }

    #[Post('/api/sales-orders')]
    public function create(Request $request): array
    {
        [$h, $lines] = self::check($request->body);
        return App::db()->transaction(fn () => self::write(null, $h, $lines));
    }

    #[Put('/api/sales-orders/{nbr}')]
    public function update(string $nbr, Request $request): array
    {
        $old = self::head($nbr);
        if (!self::editable($old, self::lines($nbr))) {
            throw new HttpException(409, t('purchase.not_editable'));
        }
        [$h, $lines] = self::check($request->body);
        return App::db()->transaction(fn () => self::write($nbr, $h, $lines));
    }

    #[Post('/api/sales-orders/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        if (self::head($nbr)['so_status'] !== 'O' || self::shipped(self::lines($nbr))) {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->transaction(function (Db $db) use ($nbr) {
            self::setStatus($nbr, 'X');
            $db->run("UPDATE sod_det SET sod_status = 'X' WHERE sod_nbr = ?", [$nbr]);
            // a quotation that became this order is open again
            $db->run("UPDATE qt_mstr SET qt_status = 'O', qt_so_nbr = NULL WHERE qt_so_nbr = ?", [$nbr]);
        });
        return null;
    }

    /** Short-close: what was not shipped will not be. */
    #[Post('/api/sales-orders/{nbr}/close'), Action('edit')]
    public function close(string $nbr): null
    {
        if (self::head($nbr)['so_status'] !== 'O') {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->transaction(function (Db $db) use ($nbr) {
            self::setStatus($nbr, 'C');
            $db->run("UPDATE sod_det SET sod_status = 'C' WHERE sod_nbr = ? AND sod_status = 'O'", [$nbr]);
        });
        return null;
    }

    /** Body: {qt}. The quotation becomes a sales order (won). */
    #[Post('/api/sales-orders/from-qt'), Action('add')]
    public function fromQt(Request $request): array
    {
        $c = new Check();
        $qt = $c->exists('qt', $request->body['qt'] ?? '', 'qt_mstr', 'qt_nbr', true, "qt_status = 'O'");
        $date = $c->date('date', $request->body['date'] ?? '') ?? date('Y-m-d'); // the screen orders today; a date keys in an older order
        $c->done();
        $q = QtController::head($qt);
        if ($q['qt_expire'] < $date) {
            throw new HttpException(422, t('sales.qt_expired'));
        }
        $site = StockLookupController::sites()[0]['si_site'] ?? null;
        $h = [
            'so_cm_code' => $q['qt_cm_code'], 'so_date' => $date, 'so_req_date' => null, 'so_site' => $site,
            'so_cr_terms' => $q['qt_cr_terms'], 'so_curr' => $q['qt_curr'], 'so_ex_rate' => (float) $q['qt_ex_rate'],
            'so_tax_code' => $q['qt_tax_code'], 'so_slspsn' => $q['qt_sales_rep'], 'so_po_ref' => null,
            'so_remarks' => t('sales.from_qt', ['nbr' => $qt]),
        ];
        $lines = array_map(fn ($l) => [
            'sod_part' => $l['qtd_part'], 'sod_desc' => $l['qtd_desc'], 'sod_um' => $l['qtd_um'], 'sod_qty_ord' => (float) $l['qtd_qty'],
            'sod_price' => (float) $l['qtd_price'], 'sod_disc_pct' => (float) $l['qtd_disc_pct'], 'sod_req_date' => null,
        ], QtController::lines($qt));

        return App::db()->transaction(function (Db $db) use ($qt, $h, $lines) {
            $res = self::write(null, $h, $lines);
            $db->update('qt_mstr', ['qt_status' => 'W', 'qt_so_nbr' => $res['nbr'], 'updated_by' => Auth::userId()], ['qt_nbr' => $qt]);
            return $res;
        });
    }

    // ---------------------------------------------------------------- helpers

    private static function check(array $b): array
    {
        $c = new Check();
        $h = [
            'so_cm_code'  => $c->exists('so_cm_code', $b['so_cm_code'] ?? '', 'cm_mstr', 'cm_code', true, "cm_status = 'ACTIVE'"),
            'so_date'     => $c->date('so_date', $b['so_date'] ?? '', true),
            'so_req_date' => $c->date('so_req_date', $b['so_req_date'] ?? ''),
            'so_site'     => $c->exists('so_site', $b['so_site'] ?? '', 'si_mstr', 'si_site', true, 'si_status = 1'),
            'so_cr_terms' => $c->exists('so_cr_terms', $b['so_cr_terms'] ?? '', 'ct_mstr', 'ct_code', false),
            'so_curr'     => $c->exists('so_curr', $b['so_curr'] ?? '', 'cu_mstr', 'cu_curr', true),
            'so_ex_rate'  => $c->number('so_ex_rate', $b['so_ex_rate'] ?? '', true, 0, true),
            'so_tax_code' => $c->exists('so_tax_code', $b['so_tax_code'] ?? '', 'tx_mstr', 'tx_tax_code', false),
            'so_slspsn'   => $c->text('so_slspsn', $b['so_slspsn'] ?? '', false, 50, false),
            'so_po_ref'   => $c->text('so_po_ref', $b['so_po_ref'] ?? '', false, 50, false),
            'so_remarks'  => $c->text('so_remarks', $b['so_remarks'] ?? '', false, 2000, false),
            // ใบสั่งขายแบบกำหนดส่ง: the lines ship in dated instalments (Schedule)
            'so_sched'    => (int) filter_var($b['so_sched'] ?? false, FILTER_VALIDATE_BOOL),
        ];
        $lines = SalesLines::check($c, $b['lines'] ?? null, 'sod', 'sod_qty_ord', true);
        $c->done();
        return [$h, $lines];
    }

    /**
     * Insert ($nbr null) or replace an order. The status follows the settings and the
     * credit check. @return array{nbr: string, notice?: string}
     */
    public static function write(?string $nbr, array $h, array $lines): array
    {
        $db = App::db();
        $user = Auth::userId();
        $settings = Sales::settings();
        $site = $h['so_site'];
        unset($h['so_site']);

        $rows = SalesLines::rows($lines, 'sod', 'sod_qty_ord');
        $pct = Money::taxPct($h['so_tax_code']);
        $tot = Money::totals(array_column($rows, 'sod_line_amt'), $pct);
        $h += ['so_tax_pct' => $pct, 'so_tax_amt' => $tot['tax'], 'so_tot_amt' => $tot['total'], 'so_ex_date' => $h['so_date']];

        $notice = null;
        if (($settings['so_cr_chk'] ?? 0) && ($problem = Sales::creditProblem($h['so_cm_code'], $tot['total'] * $h['so_ex_rate'], (string) $nbr))) {
            $notice = t('sales.credit_hold', ['limit' => number_format($problem['limit'], 2), 'used' => number_format($problem['used'], 2)]);
        }

        if ($nbr === null) {
            $nbr = Numbering::next('SO', $h['so_date']);
            $db->insert('so_mstr', $h + ['so_nbr' => $nbr, 'so_status' => 'O', 'created_by' => $user, 'updated_by' => $user]);
        } else {
            $db->update('so_mstr', $h + ['updated_by' => $user], ['so_nbr' => $nbr]);
            $db->delete('sod_det', ['sod_nbr' => $nbr]);
        }
        foreach ($rows as $r) {
            $db->insert('sod_det', $r + ['sod_nbr' => $nbr, 'sod_site' => $site, 'sod_status' => 'O', 'created_by' => $user, 'updated_by' => $user]);
        }
        Schedule::seed('SO', $nbr);
        return $notice ? ['nbr' => $nbr, 'notice' => $notice] : ['nbr' => $nbr];
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT s.*, c.cm_name FROM so_mstr s LEFT JOIN cm_mstr c ON c.cm_code = s.so_cm_code WHERE s.so_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, pt.pt_lot_ser, pt.pt_inventory FROM sod_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.sod_part WHERE d.sod_nbr = ? ORDER BY d.sod_line',
            [$nbr]
        );
    }

    private static function shipped(array $lines): bool
    {
        foreach ($lines as $l) {
            if ((float) $l['sod_qty_shp'] > 0) {
                return true;
            }
        }
        return false;
    }

    private static function editable(array $head, array $lines): bool
    {
        return $head['so_status'] === 'O' && !self::shipped($lines);
    }

    private static function setStatus(string $nbr, string $status): void
    {
        $old = self::head($nbr);
        App::db()->update('so_mstr', ['so_status' => $status, 'updated_by' => Auth::userId()], ['so_nbr' => $nbr]);
        Audit::updated('so_mstr', "so_nbr=$nbr", $old, ['so_status' => $status]);
    }

    private function editor(?string $nbr): string
    {
        $config = SalesLines::editorConfig('so', 'sod', 'sod_qty_ord', [
            'api'    => '/api/sales-orders',
            'numbering' => 'SO', // the number field, may be typed (Numbering::next)
            'page'   => '/sales-orders',
            'nbr'    => $nbr,
            'statusField' => 'so_status',
            'status' => self::statusText(),
            'canEdit' => Auth::can('sales-orders', $nbr ? 'edit' : 'add'),
            'head'   => [
                ['name' => 'so_cm_code', 'label' => t('sales.customer'), 'type' => 'customer', 'col' => 4, 'required' => true, 'nameField' => 'cm_name'],
                ['name' => 'so_date', 'label' => t('sales.date'), 'type' => 'date', 'col' => 2, 'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'so_req_date', 'label' => t('sales.req_date'), 'type' => 'date', 'col' => 2],
                ['name' => 'so_site', 'label' => t('sales.ship_from'), 'type' => 'select', 'col' => 4, 'required' => true,
                 'options' => array_map(fn ($s) => ['value' => $s['si_site'], 'label' => $s['si_site'] . ' · ' . $s['si_desc']], StockLookupController::sites())],
                ['name' => 'so_cr_terms', 'label' => t('sales.terms'), 'type' => 'select', 'col' => 3, 'options' => Lookup::options('credit-terms')],
                ['name' => 'so_curr', 'label' => t('sales.currency'), 'type' => 'select', 'col' => 2, 'required' => true, 'options' => Lookup::options('currencies'), 'default' => Money::base()],
                ['name' => 'so_ex_rate', 'label' => t('sales.rate'), 'type' => 'number', 'col' => 2, 'required' => true, 'default' => '1'],
                ['name' => 'so_tax_code', 'label' => t('sales.tax'), 'type' => 'select', 'col' => 2, 'options' => Money::taxOptions()],
                ['name' => 'so_po_ref', 'label' => t('sales.po_ref'), 'type' => 'text', 'col' => 3],
                ['name' => 'so_slspsn', 'label' => t('sales.salesperson'), 'type' => 'text', 'col' => 3],
                ['name' => 'so_remarks', 'label' => t('sales.remarks'), 'type' => 'text', 'col' => 6],
                ['name' => 'so_sched', 'label' => t('schedule.field'), 'type' => 'select', 'col' => 3, 'default' => '0',
                 'options' => [['value' => '0', 'label' => t('schedule.kind_0')], ['value' => '1', 'label' => t('schedule.kind_1')]]],
            ],
            'partyDefaults' => ['so_cr_terms' => 'cm_terms', 'so_curr' => 'cm_curr', 'so_tax_code' => 'cm_tax_code', 'so_slspsn' => 'cm_salesperson'],
            'rate'   => ['curr' => 'so_curr', 'date' => 'so_date', 'out' => 'so_ex_rate'],
            'tax'    => 'so_tax_code',
            'priceParams' => ['customer' => 'so_cm_code', 'date' => 'so_date'],
            'actions' => [
                ['name' => 'ship', 'label' => t('sales.ship'), 'href' => '/shipments/new?so={nbr}', 'style' => 'btn-ghost-primary', 'when' => ['O'],
                 'can' => Auth::can('shipments', 'add')],
                ['name' => 'close', 'label' => t('sales.close'), 'api' => '/close', 'style' => 'btn-ghost-secondary', 'when' => ['O'],
                 'can' => Auth::can('sales-orders', 'edit'), 'confirm' => t('sales.close_confirm')],
                ['name' => 'cancel', 'label' => t('purchase.cancel_doc'), 'api' => '/cancel', 'style' => 'btn-ghost-danger', 'when' => ['O'],
                 'can' => Auth::can('sales-orders', 'delete'), 'confirm' => t('purchase.cancel_confirm')],
                // money before the goods (ใบรับเงินมัดจำ): its tax invoices deduct it later
                ['name' => 'deposit', 'label' => t('deposit.take'), 'href' => '/deposits/new?so={nbr}', 'style' => 'btn-ghost-primary', 'when' => ['O'],
                 'can' => Auth::can('deposits', 'add')],
                ['name' => 'print', 'label' => t('sales.print'), 'href' => '/documents/sales-orders/{nbr}/print', 'target' => '_blank', 'style' => 'btn',
                 'when' => array_keys(self::statusText()), 'can' => Auth::can('sales-orders', 'print')],
            ],
        ], [
            ['name' => 'sod_req_date', 'label' => t('sales.req_date'), 'type' => 'date', 'width' => '9.5rem'],
            ['name' => 'sod_qty_shp', 'label' => t('sales.shipped'), 'type' => 'static', 'width' => '5.5rem', 'existing' => true, 'number' => true],
        ]);
        if ($nbr !== null && (int) (self::head($nbr)['so_sched'] ?? 0)) {
            // the instalments are kept on their own page, open while the agreement is
            array_unshift($config['actions'], ['name' => 'schedule', 'label' => t('schedule.title'), 'href' => '/schedules/so/{nbr}',
                'style' => 'btn-ghost-primary', 'when' => array_keys(self::statusText()), 'can' => true]);
        }
        $config['files'] = 'SO';
        return View::render('doc/edit', ['help' => 'sales-orders', 'title' => $nbr ?? t('sales.so_new'), 'ownToolbar' => true, 'config' => $config, 'scripts' => ['js/doc.js']]);
    }

    public static function statusText(): array
    {
        return [
            'O' => ['text' => t('sales.so_status_O'), 'color' => 'blue'],
            'C' => ['text' => t('purchase.status_C'), 'color' => 'green'],
            'X' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }
}
