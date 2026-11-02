<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\BahtText;
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
use Phai\Modules\Inventory\Stock;
use Phai\Modules\Inventory\StockLookupController;
use Phai\Modules\Master\Money;
use Phai\Modules\Master\Schedule;
use Phai\Modules\Sales\SalesPrint;

/**
 * Purchase orders, no approval step. Status: A open (can be received),
 * C closed, X cancelled.
 */
#[Program('purchase-orders')]
final class PoController
{
    public const STATUS = ['A', 'C', 'X'];

    #[Get('/purchase-orders')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'    => 'purchase-orders',
            'title'   => t('purchase.po_title'),
            'config'  => [
                'api'     => '/api/purchase-orders',
                'files'   => 'PO',
                'page'    => '/purchase-orders',
                'canAdd'  => Auth::can('purchase-orders', 'add') && !Purchase::settings()['po_pr_req'],
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'po_nbr', 'label' => t('purchase.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'po_date', 'label' => t('purchase.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'po_vd_code', 'label' => t('purchase.vendor'), 'sort' => true],
                    ['key' => 'vd_name', 'label' => t('purchase.vendor_name')],
                    ['key' => 'po_due_date', 'label' => t('purchase.due_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'po_tot_amt', 'label' => t('purchase.total'), 'type' => 'money', 'sort' => true, 'suffix' => 'po_curr'],
                    ['key' => 'po_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Open requisitions above the list: one click makes the orders (same as the requisition's "สร้างใบสั่งซื้อ"). */
    public static function queues(): array
    {
        if (!Auth::can('purchase-orders', 'add')) {
            return [];
        }
        return [[
            'title'   => t('purchase.queue_pr'),
            'api'     => '/api/purchase-orders/waiting',
            'key'     => 'pr_nbr',
            'urgent'  => 'late',
            'columns' => [
                ['key' => 'pr_nbr', 'label' => t('purchase.pr'), 'link' => '/purchase-requisitions/{pr_nbr}'],
                ['key' => 'pr_date', 'label' => t('purchase.date'), 'type' => 'date'],
                ['key' => 'pr_req_by', 'label' => t('purchase.req_by')],
                ['key' => 'pr_dept', 'label' => t('purchase.dept')],
                ['key' => 'need_date', 'label' => t('purchase.need_date'), 'type' => 'date'],
                ['key' => 'vendor', 'label' => t('purchase.vendor')],
                ['key' => 'amount', 'label' => t('purchase.est_total'), 'type' => 'money'],
            ],
            'action'  => ['label' => t('purchase.make_po'), 'icon' => 'ti ti-file-plus', 'post' => '/api/purchase-orders/from-pr',
                          'body' => ['pr' => '{pr_nbr}', 'vendor' => '{vendor}'], 'open' => '/purchase-orders/{nbr}'],
        ]];
    }

    /**
     * Open requisitions with lines not ordered yet, earliest need first. vendor = the vendor for lines without a
     * preferred one: the first preferred vendor of the PR, else the vendor of the latest order of one of its items.
     */
    #[Describe('Open purchase requisitions waiting for a purchase order, earliest need date first (late = need date passed; vendor = suggested vendor)')]
    #[Get('/api/purchase-orders/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            "SELECT h.pr_nbr, h.pr_date, h.pr_req_by, h.pr_dept, MIN(d.prd_req_date) AS need_date, MIN(d.prd_req_date) < CURDATE() AS late,
                    ROUND(SUM((d.prd_qty_req - d.prd_qty_ord) * d.prd_est_cost), 2) AS amount,
                    COALESCE(
                        (SELECT p.prd_pref_vd FROM prd_det p WHERE p.prd_nbr = h.pr_nbr AND p.prd_pref_vd <> '' ORDER BY p.prd_line LIMIT 1),
                        (SELECT o.po_vd_code FROM prd_det p JOIN pod_det od ON od.pod_part = p.prd_part JOIN po_mstr o ON o.po_nbr = od.pod_nbr
                           JOIN vd_mstr vd ON vd.vd_code = o.po_vd_code AND vd.vd_status = 'ACTIVE'
                          WHERE p.prd_nbr = h.pr_nbr AND o.po_status <> 'X' ORDER BY o.po_date DESC, o.po_nbr DESC LIMIT 1)
                    ) AS vendor
               FROM pr_mstr h JOIN prd_det d ON d.prd_nbr = h.pr_nbr AND d.prd_qty_req > d.prd_qty_ord
              WHERE h.pr_status = 'A'
              GROUP BY h.pr_nbr, h.pr_date, h.pr_req_by, h.pr_dept
              ORDER BY need_date, h.pr_nbr LIMIT 200"
        );
    }

    #[Get('/purchase-orders/new')]
    public function newPage(): string
    {
        return $this->editor(null);
    }

    #[Get('/purchase-orders/{nbr}')]
    public function editPage(string $nbr): string
    {
        self::head($nbr);
        return $this->editor($nbr);
    }

    #[Get('/purchase-orders/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        App::db()->run('UPDATE po_mstr SET po_print_cnt = COALESCE(po_print_cnt, 0) + 1 WHERE po_nbr = ?', [$nbr]);
        $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 5), '0'), '.');
        $lines = SalesPrint::priceLines(self::lines($nbr), 'pod', 'pod_qty_ord');
        return SalesPrint::render([
            'title'  => t('purchase.po_title') . ((int) $h['po_sched'] ? ' ' . t('schedule.kind') : ''),
            'nbr'    => $nbr,
            'meta'   => [
                [t('purchase.date'), SalesPrint::date($h['po_date'])],
                [t('purchase.due_date'), SalesPrint::date($h['po_due_date'])],
                [t('purchase.ship_to'), trim(($h['po_ship_to'] ?? '') . ' ' . ($h['si_desc'] ?? ''))],
                [t('purchase.terms'), (string) ($h['po_cr_terms'] ?? '')],
                [t('purchase.currency'), $h['po_curr'] . ((float) $h['po_ex_rate'] != 1 ? ' @ ' . $q($h['po_ex_rate']) : '')],
                [t('purchase.buyer'), (string) ($h['po_buyer'] ?? '')],
            ],
            'party'  => SalesPrint::vendor($h['po_vd_code']),
            'lines'  => $lines,
            'totals' => SalesPrint::totals($h['po_net_amt'] ?? ((float) $h['po_tot_amt'] - (float) $h['po_tax_amt']), $h['po_tax_pct'], $h['po_tax_amt'], $h['po_tot_amt'], $h['po_curr']),
            'notes'  => trim($h['po_remarks'] . "\n" . Schedule::notes('PO', $nbr)),
            'signs'  => [t('purchase.buyer'), SalesPrint::approve(t('purchase.approve'))],
        ]);
    }

    /** ?q= (number or vendor) &status= &sort= &dir= &page= */
    #[Describe('Purchase orders: vendor, dates, totals in the order currency', ['q' => 'number or vendor', 'status' => 'A open, C closed/fully received, X cancelled'])]
    #[Get('/api/purchase-orders')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), self::STATUS, true)) {
            $where[] = 'p.po_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(p.po_nbr LIKE ? OR p.po_vd_code LIKE ? OR v.vd_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT p.po_nbr, p.po_date, p.po_vd_code, v.vd_name, p.po_due_date, p.po_curr, ROUND(p.po_tot_amt - p.po_tax_amt, 2) AS po_net_amt, p.po_tax_amt, p.po_tot_amt, p.po_status',
            'FROM po_mstr p LEFT JOIN vd_mstr v ON v.vd_code = p.po_vd_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'po_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            [
                'po_nbr' => 'p.po_nbr', 'po_date' => 'p.po_date', 'po_vd_code' => 'p.po_vd_code',
                'po_due_date' => 'p.po_due_date', 'po_net_amt' => 'ROUND(p.po_tot_amt - p.po_tax_amt, 2)',
                'po_tax_amt' => 'p.po_tax_amt', 'po_tot_amt' => 'p.po_tot_amt', 'po_status' => 'p.po_status',
            ],
            'po_nbr'
        );
    }

    #[Describe('One purchase order with lines: ordered and received quantities, prices')]

    #[Get('/api/purchase-orders/{nbr}')]
    public function show(string $nbr): array
    {
        $head = self::head($nbr);
        $lines = self::lines($nbr);
        $head['_editable'] = self::editable($head, $lines) && Auth::can('purchase-orders', 'edit');
        return ['head' => $head, 'lines' => $lines];
    }

    #[Post('/api/purchase-orders')]
    public function create(Request $request): array
    {
        if (Purchase::settings()['po_pr_req'] ?? false) {
            throw new HttpException(422, t('purchase.pr_required'));
        }
        [$head, $lines] = self::check($request->body);
        return ['nbr' => App::db()->transaction(fn () => self::write(null, $head, $lines))];
    }

    #[Put('/api/purchase-orders/{nbr}')]
    public function update(string $nbr, Request $request): array
    {
        $old = self::head($nbr);
        if (!self::editable($old, self::lines($nbr))) {
            throw new HttpException(409, t('purchase.not_editable'));
        }
        [$head, $lines] = self::check($request->body);
        return ['nbr' => App::db()->transaction(fn () => self::write($nbr, $head, $lines, $old))];
    }

    /** Only while nothing has been received. Linked PR lines become free again. */
    #[Post('/api/purchase-orders/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        $head = self::head($nbr);
        if ($head['po_status'] !== 'A' || self::received(self::lines($nbr))) {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->transaction(function (Db $db) use ($nbr) {
            self::setStatus($nbr, 'X');
            $db->run("UPDATE pod_det SET pod_status = 'X' WHERE pod_nbr = ?", [$nbr]);
            $prs = array_column($db->all('SELECT DISTINCT prd_nbr FROM prd_det WHERE prd_po_nbr = ?', [$nbr]), 'prd_nbr');
            $db->run('UPDATE prd_det SET prd_qty_ord = 0, prd_po_nbr = NULL, prd_pod_line = NULL WHERE prd_po_nbr = ?', [$nbr]);
            foreach ($prs as $pr) {
                $db->run("UPDATE pr_mstr SET pr_status = 'A' WHERE pr_nbr = ? AND pr_status = 'O'", [$pr]);
            }
        });
        return null;
    }

    /** Short-close an open order: what was not received will not come. */
    #[Post('/api/purchase-orders/{nbr}/close'), Action('edit')]
    public function close(string $nbr): null
    {
        if (self::head($nbr)['po_status'] !== 'A') {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->transaction(function (Db $db) use ($nbr) {
            self::setStatus($nbr, 'C');
            $db->run("UPDATE pod_det SET pod_status = 'C' WHERE pod_nbr = ? AND pod_status = 'O'", [$nbr]);
        });
        return null;
    }

    /**
     * Body: {pr, vendor}. The open lines of a PR become one order per vendor:
     * a line goes to its preferred vendor (prd_pref_vd), lines without one to {vendor}.
     * Returns the first order, all of them and, when there are several, a notice: {nbr, pos, notice?}.
     */
    #[Post('/api/purchase-orders/from-pr'), Action('add')]
    public function fromPr(Request $request): array
    {
        $c = new Check();
        $prNbr = $c->exists('pr', $request->body['pr'] ?? '', 'pr_mstr', 'pr_nbr', true, "pr_status = 'A'");
        $vendor = $c->exists('vendor', $request->body['vendor'] ?? '', 'vd_mstr', 'vd_code', false, "vd_status = 'ACTIVE'");
        $date = $c->date('date', $request->body['date'] ?? '') ?? date('Y-m-d'); // the screen orders today; a date keys in an older order
        $c->done();

        $prLines = App::db()->all('SELECT * FROM prd_det WHERE prd_nbr = ? AND prd_qty_req > prd_qty_ord ORDER BY prd_line', [$prNbr]);
        if (!$prLines) {
            throw new HttpException(422, t('purchase.nothing_to_order'));
        }
        $byVendor = [];
        foreach ($prLines as $l) {
            $code = $l['prd_pref_vd'] ?: $vendor ?? throw HttpException::invalid(['vendor' => t('validation.required')]);
            $byVendor[$code][] = $l;
        }
        $site = StockLookupController::sites()[0]['si_site'] ?? null;
        $orders = [];
        foreach ($byVendor as $code => $vdLines) {
            $vd = App::db()->one("SELECT * FROM vd_mstr WHERE vd_code = ? AND vd_status = 'ACTIVE'", [$code])
                ?? throw HttpException::invalid(['vendor' => t('purchase.vendor_inactive', ['vendor' => $code])]);
            $curr = $vd['vd_curr'] ?: Money::base();
            $rate = Money::rate($curr, $date) ?? throw HttpException::invalid(['vendor' => t('purchase.no_rate', ['curr' => $curr])]);
            $head = [
                'po_vd_code' => $code, 'po_date' => $date, 'po_due_date' => null, 'po_ship_to' => $site,
                'po_cr_terms' => $vd['vd_terms'], 'po_curr' => $curr, 'po_ex_rate' => $rate, 'po_tax_code' => $vd['vd_tax_code'],
                'po_ship_via' => null, 'po_buyer' => Auth::userId(), 'po_remarks' => t('purchase.from_pr', ['nbr' => $prNbr]),
            ];
            $orders[] = [$head, $vdLines];
        }

        $pos = App::db()->transaction(function (Db $db) use ($orders, $prNbr) {
            $pos = [];
            foreach ($orders as [$head, $vdLines]) {
                $nbr = self::write(null, $head, array_map(fn ($l) => [
                    'pod_part' => $l['prd_part'], 'pod_desc' => $l['prd_desc'], 'pod_um' => $l['prd_um'],
                    'pod_qty_ord' => round((float) $l['prd_qty_req'] - (float) $l['prd_qty_ord'], 5),
                    'pod_price' => (float) $l['prd_est_cost'], 'pod_disc_pct' => 0.0, 'pod_req_date' => $l['prd_req_date'],
                ], $vdLines));
                foreach ($vdLines as $i => $l) {
                    $db->run(
                        'UPDATE prd_det SET prd_qty_ord = prd_qty_req, prd_po_nbr = ?, prd_pod_line = ? WHERE prd_nbr = ? AND prd_line = ?',
                        [$nbr, $i + 1, $prNbr, $l['prd_line']]
                    );
                }
                $pos[] = $nbr;
            }
            $db->run("UPDATE pr_mstr SET pr_status = 'O' WHERE pr_nbr = ?", [$prNbr]);
            return $pos;
        });
        return ['nbr' => $pos[0], 'pos' => $pos] + (count($pos) > 1 ? ['notice' => t('purchase.pos_made', ['nbrs' => implode(', ', $pos)])] : []);
    }

    // ---------------------------------------------------------------- helpers

    /** Check a posted order. @return array{0: array, 1: list<array>} */
    public static function check(array $b): array
    {
        $c = new Check();
        $h = [
            'po_vd_code'  => $c->exists('po_vd_code', $b['po_vd_code'] ?? '', 'vd_mstr', 'vd_code', true, "vd_status = 'ACTIVE'"),
            'po_date'     => $c->date('po_date', $b['po_date'] ?? '', true),
            'po_due_date' => $c->date('po_due_date', $b['po_due_date'] ?? ''),
            'po_ship_to'  => $c->exists('po_ship_to', $b['po_ship_to'] ?? '', 'si_mstr', 'si_site', true, 'si_status = 1'),
            'po_cr_terms' => $c->exists('po_cr_terms', $b['po_cr_terms'] ?? '', 'ct_mstr', 'ct_code', false),
            'po_curr'     => $c->exists('po_curr', $b['po_curr'] ?? '', 'cu_mstr', 'cu_curr', true),
            'po_ex_rate'  => $c->number('po_ex_rate', $b['po_ex_rate'] ?? '', true, 0, true),
            'po_tax_code' => $c->exists('po_tax_code', $b['po_tax_code'] ?? '', 'tx_mstr', 'tx_tax_code', false),
            'po_ship_via' => $c->text('po_ship_via', $b['po_ship_via'] ?? '', false, 50),
            'po_buyer'    => $c->text('po_buyer', $b['po_buyer'] ?? '', false, 50),
            'po_remarks'  => $c->text('po_remarks', $b['po_remarks'] ?? '', false, 2000),
            // ใบสั่งซื้อแบบกำหนดส่ง: the lines arrive in dated instalments (Schedule)
            'po_sched'    => (int) filter_var($b['po_sched'] ?? false, FILTER_VALIDATE_BOOL),
        ];

        $lines = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $i => $l) {
            if (!is_array($l) || (trim((string) ($l['pod_part'] ?? '')) === '' && trim((string) ($l['pod_qty_ord'] ?? '')) === '')) {
                continue;
            }
            $f = fn (string $n) => "lines.$i.$n";
            [$part, $desc, $um] = Stock::lineItem($c, $f('pod_part'), $l['pod_part'] ?? '', $f('pod_desc'), $l['pod_desc'] ?? '', $f('pod_um'), $l['pod_um'] ?? '', false);
            $lines[$i] = [
                'pod_part'     => $part,
                'pod_desc'     => $desc,
                'pod_um'       => $um,
                'pod_qty_ord'  => $c->number($f('pod_qty_ord'), $l['pod_qty_ord'] ?? '', true, 0, true),
                'pod_price'    => $c->number($f('pod_price'), $l['pod_price'] ?? '', true, 0),
                'pod_disc_pct' => $c->number($f('pod_disc_pct'), $l['pod_disc_pct'] ?? '', false, 0) ?? 0.0,
                'pod_req_date' => $c->date($f('pod_req_date'), $l['pod_req_date'] ?? ''),
            ];
            if ($lines[$i]['pod_disc_pct'] > 100) {
                $c->fail($f('pod_disc_pct'), t('validation.max', ['max' => 100]));
            }
        }
        if (!$lines) {
            $c->fail('lines', t('stock.no_lines'));
        }
        $c->done();
        return [$h, $lines];
    }

    /** Insert a new order ($nbr null) or replace an existing one. Returns the number. */
    public static function write(?string $nbr, array $h, array $lines, ?array $old = null): string
    {
        $db = App::db();
        $user = Auth::userId();

        $rows = [];
        $n = 0;
        foreach ($lines as $l) {
            $part = Stock::part($l['pod_part']);
            $rows[] = [
                'pod_line'     => ++$n,
                'pod_part'     => $l['pod_part'],
                'pod_desc'     => $l['pod_desc'] ?? $part['pt_desc1'] ?? null,
                'pod_qty_ord'  => $l['pod_qty_ord'],
                'pod_um'       => $part['pt_um'] ?? ($l['pod_um'] ?? null) ?? 'EA', // a free item keeps the unit typed
                'pod_price'    => $l['pod_price'],
                'pod_disc_pct' => $l['pod_disc_pct'],
                'pod_line_amt' => Money::lineAmount($l['pod_qty_ord'], $l['pod_price'], $l['pod_disc_pct']),
                'pod_req_date' => $l['pod_req_date'],
                'pod_site'     => $h['po_ship_to'],
                'pod_status'   => 'O',
            ];
        }
        $taxPct = Money::taxPct($h['po_tax_code']);
        $tot = Money::totals(array_column($rows, 'pod_line_amt'), $taxPct);
        $h += ['po_tax_pct' => $taxPct, 'po_tax_amt' => $tot['tax'], 'po_tot_amt' => $tot['total'], 'po_ex_date' => $h['po_date']];

        if ($nbr === null) {
            $nbr = Numbering::next('PO', $h['po_date']);
            $db->insert('po_mstr', $h + ['po_nbr' => $nbr, 'po_status' => 'A', 'created_by' => $user, 'updated_by' => $user]);
        } else {
            $db->update('po_mstr', $h + ['updated_by' => $user], ['po_nbr' => $nbr]);
            $db->delete('pod_det', ['pod_nbr' => $nbr]);
        }
        foreach ($rows as $r) {
            $db->insert('pod_det', $r + ['pod_nbr' => $nbr, 'created_by' => $user, 'updated_by' => $user]);
        }
        Schedule::seed('PO', $nbr);
        return $nbr;
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT p.*, v.vd_name, si.si_desc FROM po_mstr p
               LEFT JOIN vd_mstr v ON v.vd_code = p.po_vd_code
               LEFT JOIN si_mstr si ON si.si_site = p.po_ship_to
              WHERE p.po_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, pt.pt_lot_ser, pt.pt_inventory FROM pod_det d LEFT JOIN pt_mstr pt ON pt.pt_part = d.pod_part WHERE d.pod_nbr = ? ORDER BY d.pod_line',
            [$nbr]
        );
    }

    /** Receipts of an order, newest first, for the panel beside it: number, date, who, lines (item, qty unit). */
    private static function receipts(string $nbr): array
    {
        $db = App::db();
        $out = [];
        foreach ($db->all('SELECT rc_nbr, rc_date, rc_receiver FROM rc_mstr WHERE rc_po_nbr = ? ORDER BY rc_date DESC, rc_nbr DESC', [$nbr]) as $r) {
            $lines = $db->all(
                'SELECT d.rcd_part, d.rcd_qty_rcvd, d.rcd_um, p.pod_desc FROM rcd_det d
                   LEFT JOIN pod_det p ON p.pod_nbr = ? AND p.pod_line = d.rcd_pod_line
                  WHERE d.rcd_nbr = ? ORDER BY d.rcd_line',
                [$nbr, $r['rc_nbr']]
            );
            $out[] = [
                'nbr'   => $r['rc_nbr'],
                'date'  => date('d/m/Y', strtotime($r['rc_date'])),
                'by'    => (string) $r['rc_receiver'],
                'link'  => '/receipts/' . rawurlencode($r['rc_nbr']),
                'lines' => array_map(fn ($l) => [
                    $l['rcd_part'] !== '' ? $l['rcd_part'] : (string) $l['pod_desc'],
                    rtrim(rtrim(number_format((float) $l['rcd_qty_rcvd'], 5), '0'), '.') . ' ' . $l['rcd_um'],
                ], $lines),
            ];
        }
        return $out;
    }

    public static function received(array $lines): bool
    {
        foreach ($lines as $l) {
            if ((float) $l['pod_qty_rcvd'] > 0) {
                return true;
            }
        }
        return false;
    }

    private static function editable(array $head, array $lines): bool
    {
        return $head['po_status'] === 'A' && !self::received($lines);
    }

    private static function setStatus(string $nbr, string $status, array $more = []): void
    {
        $old = self::head($nbr);
        $new = ['po_status' => $status] + $more;
        App::db()->update('po_mstr', $new + ['updated_by' => Auth::userId()], ['po_nbr' => $nbr]);
        \Phai\Core\Audit\Audit::updated('po_mstr', "po_nbr=$nbr", $old, $new);
    }

    /** Page with the document editor (public/js/doc.js). */
    private function editor(?string $nbr): string
    {
        $canEdit = Auth::can('purchase-orders', $nbr ? 'edit' : 'add');
        $config = [
            'api'    => '/api/purchase-orders',
            'numbering' => 'PO', // the number field, may be typed (Numbering::next)
            'page'   => '/purchase-orders',
            'nbr'    => $nbr,
            'nbrField' => 'po_nbr',
            'statusField' => 'po_status',
            'status' => self::statusText(),
            'canEdit' => $canEdit,
            'head'   => [
                ['name' => 'po_vd_code', 'label' => t('purchase.vendor'), 'type' => 'vendor', 'col' => 4, 'required' => true, 'nameField' => 'vd_name'],
                ['name' => 'po_date', 'label' => t('purchase.date'), 'type' => 'date', 'col' => 2, 'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'po_due_date', 'label' => t('purchase.due_date'), 'type' => 'date', 'col' => 2],
                ['name' => 'po_ship_to', 'label' => t('purchase.ship_to'), 'type' => 'select', 'col' => 4, 'required' => true,
                 'options' => array_map(fn ($s) => ['value' => $s['si_site'], 'label' => $s['si_site'] . ' · ' . $s['si_desc']], StockLookupController::sites())],
                ['name' => 'po_cr_terms', 'label' => t('purchase.terms'), 'type' => 'select', 'col' => 3, 'options' => Lookup::options('credit-terms')],
                ['name' => 'po_curr', 'label' => t('purchase.currency'), 'type' => 'select', 'col' => 2, 'required' => true, 'options' => Lookup::options('currencies'), 'default' => Money::base()],
                ['name' => 'po_ex_rate', 'label' => t('purchase.rate'), 'type' => 'number', 'col' => 2, 'required' => true, 'default' => '1'],
                ['name' => 'po_tax_code', 'label' => t('purchase.tax'), 'type' => 'select', 'col' => 2, 'options' => Money::taxOptions()],
                ['name' => 'po_buyer', 'label' => t('purchase.buyer'), 'type' => 'text', 'col' => 3, 'default' => Auth::userId()],
                ['name' => 'po_ship_via', 'label' => t('purchase.ship_via'), 'type' => 'text', 'col' => 6],
                ['name' => 'po_remarks', 'label' => t('purchase.remarks'), 'type' => 'text', 'col' => 6],
                ['name' => 'po_sched', 'label' => t('schedule.field'), 'type' => 'select', 'col' => 3, 'default' => '0',
                 'options' => [['value' => '0', 'label' => t('schedule.kind_0')], ['value' => '1', 'label' => t('schedule.kind_1')]]],
            ],
            'partyDefaults' => ['po_cr_terms' => 'vd_terms', 'po_curr' => 'vd_curr', 'po_tax_code' => 'vd_tax_code'],
            'rate'   => ['curr' => 'po_curr', 'date' => 'po_date', 'out' => 'po_ex_rate'],
            'tax'    => 'po_tax_code',
            'lines'  => [
                'columns' => [
                    ['name' => 'pod_part', 'label' => t('purchase.part'), 'type' => 'part', 'width' => '11rem'],
                    ['name' => 'pod_desc', 'label' => t('purchase.desc'), 'type' => 'text'],
                    ['name' => 'pod_qty_ord', 'label' => t('purchase.qty'), 'type' => 'number', 'width' => '6.5rem'],
                    ['name' => 'pod_um', 'label' => t('purchase.um'), 'type' => 'um', 'width' => '5rem'],
                    ['name' => 'pod_price', 'label' => t('purchase.price'), 'type' => 'number', 'width' => '7.5rem'],
                    ['name' => 'pod_disc_pct', 'label' => t('purchase.disc'), 'type' => 'number', 'width' => '5rem'],
                    ['name' => 'pod_line_amt', 'label' => t('purchase.amount'), 'type' => 'amount', 'width' => '8rem'],
                    ['name' => 'pod_req_date', 'label' => t('purchase.req_date'), 'type' => 'date', 'width' => '9.5rem'],
                    ['name' => 'pod_qty_rcvd', 'label' => t('purchase.received'), 'type' => 'static', 'width' => '5.5rem', 'existing' => true, 'number' => true],
                ],
                'part'   => ['desc' => 'pod_desc', 'um' => 'pod_um', 'price' => 'pod_price', 'priceApi' => '/api/purchase/last-price', 'priceParams' => ['vendor' => 'po_vd_code']],
                'amount' => ['qty' => 'pod_qty_ord', 'price' => 'pod_price', 'disc' => 'pod_disc_pct', 'out' => 'pod_line_amt'],
            ],
            'totals' => true,
            'actions' => [
                ['name' => 'close', 'label' => t('purchase.close'), 'api' => '/close', 'style' => 'btn-ghost-secondary', 'when' => ['A'],
                 'can' => Auth::can('purchase-orders', 'edit'), 'confirm' => t('purchase.close_confirm')],
                ['name' => 'cancel', 'label' => t('purchase.cancel_doc'), 'api' => '/cancel', 'style' => 'btn-ghost-danger', 'when' => ['A'],
                 'can' => Auth::can('purchase-orders', 'delete'), 'confirm' => t('purchase.cancel_confirm')],
                ['name' => 'receive', 'label' => t('purchase.receive'), 'href' => '/receipts/new?po={nbr}', 'style' => 'btn-ghost-primary', 'when' => ['A'],
                 'can' => Auth::can('receipts', 'add')],
                ['name' => 'print', 'label' => t('purchase.print'), 'href' => '/purchase-orders/{nbr}/print', 'target' => '_blank', 'style' => 'btn',
                 'when' => ['A', 'C'], 'can' => Auth::can('purchase-orders', 'print')],
            ],
        ];
        if ($nbr !== null && (int) (self::head($nbr)['po_sched'] ?? 0)) {
            // the instalments are kept on their own page, open while the agreement is
            array_unshift($config['actions'], ['name' => 'schedule', 'label' => t('schedule.title'), 'href' => '/schedules/po/{nbr}',
                'style' => 'btn-ghost-primary', 'when' => ['A', 'C', 'X'], 'can' => true]);
        }
        $config['files'] = 'PO';
        if ($nbr !== null && ($receipts = self::receipts($nbr))) {
            $config['history'] = ['title' => t('purchase.receipt_history'), 'items' => $receipts];
        }
        return View::render('doc/edit', [
            'help'    => 'purchase-orders',
            'title'   => $nbr ?? t('purchase.po_new'),
            'config'  => $config,
            'scripts' => ['js/doc.js'],
        ]);
    }

    public static function statusText(): array
    {
        return [
            'A' => ['text' => t('purchase.status_A'), 'color' => 'blue'],
            'C' => ['text' => t('purchase.status_C'), 'color' => 'green'],
            'X' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }
}
