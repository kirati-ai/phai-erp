<?php

declare(strict_types=1);

namespace Phai\Modules\Purchase;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
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
use Phai\Modules\Production\MrpController;

/**
 * Purchase requisitions, no approval step. Status: A open (editable, can become POs),
 * O ordered (a PO was made from it), X cancelled.
 */
#[Program('purchase-requisitions')]
final class PrController
{
    public const STATUS = ['A', 'O', 'X'];

    #[Get('/purchase-requisitions')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'    => 'purchase-requisitions',
            'title'   => t('purchase.pr_title'),
            'config'  => [
                'api'     => '/api/purchase-requisitions',
                'files'   => 'PR',
                'page'    => '/purchase-requisitions',
                'canAdd'  => Auth::can('purchase-requisitions', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'pr_nbr', 'label' => t('purchase.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'pr_date', 'label' => t('purchase.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'pr_req_by', 'label' => t('purchase.req_by'), 'sort' => true],
                    ['key' => 'pr_dept', 'label' => t('purchase.dept')],
                    ['key' => 'pr_tot_amt', 'label' => t('purchase.est_total'), 'type' => 'money', 'sort' => true],
                    ['key' => 'pr_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** MRP planned purchases above the list: one row or the ticked rows become one requisition. */
    public static function queues(): array
    {
        if (!Auth::can('purchase-requisitions', 'add')) {
            return [];
        }
        return [[
            'title'   => t('purchase.queue_mrp'),
            'api'     => '/api/purchase-requisitions/waiting',
            'key'     => 'mrp_key',
            'urgent'  => 'late',
            'columns' => [
                ['key' => 'part', 'label' => t('purchase.part')],
                ['key' => 'pt_desc1', 'label' => t('purchase.desc')],
                ['key' => 'qty', 'label' => t('purchase.qty'), 'type' => 'num'],
                ['key' => 'pt_um', 'label' => t('purchase.um')],
                ['key' => 'rel_date', 'label' => t('purchase.order_by'), 'type' => 'date'],
                ['key' => 'due', 'label' => t('purchase.need_date'), 'type' => 'date'],
            ],
            // tick the planned purchases that go on one requisition (or one row with its own button)
            'action'  => ['label' => t('purchase.queue_make_pr'), 'icon' => 'ti ti-file-plus', 'post' => '/api/purchase-requisitions/from-plan',
                'body' => ['keys' => '{mrp_key}'], 'open' => '/purchase-requisitions/{nbr}'],
            'multi'   => ['label' => t('purchase.queue_make_pr_ticked'), 'icon' => 'ti ti-files', 'post' => '/api/purchase-requisitions/from-plan',
                'open' => '/purchase-requisitions/{nbr}'],
        ]];
    }

    #[Describe('Planned purchases from the last MRP run that have no purchase requisition yet, earliest order date first (late = order date passed)')]
    #[Get('/api/purchase-requisitions/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            "SELECT CONCAT(m.mrp_part, '|', m.mrp_site, '|', m.mrp_due_date) AS mrp_key, m.mrp_part AS part, pt.pt_desc1, pt.pt_um,
                    m.mrp_qty AS qty, m.mrp_rel_date AS rel_date, m.mrp_due_date AS due,
                    COALESCE(m.mrp_rel_date, m.mrp_due_date) < CURDATE() AS late
               FROM mrp_det m LEFT JOIN pt_mstr pt ON pt.pt_part = m.mrp_part
              WHERE m.mrp_type = 'PO' ORDER BY COALESCE(m.mrp_rel_date, m.mrp_due_date), m.mrp_part LIMIT 200"
        );
    }

    /** Body: {keys: ["part|site|due", ...] or "part|site|due"} — planned purchases (mrp_det) become one requisition. */
    #[Post('/api/purchase-requisitions/from-plan')]
    public function fromPlan(Request $request): array
    {
        $keys = $request->body['keys'] ?? [];
        $rows = [];
        foreach (is_array($keys) ? $keys : explode(',', (string) $keys) as $k) {
            [$part, $site, $due] = array_pad(explode('|', (string) $k, 3), 3, '');
            $row = App::db()->one(
                "SELECT * FROM mrp_det WHERE mrp_part = ? AND mrp_site = ? AND mrp_due_date = ? AND mrp_type = 'PO'",
                [$part, $site, $due]
            );
            if ($row) {
                $rows[] = $row;
            }
        }
        if (!$rows) {
            throw new HttpException(422, t('production.pick_rows'));
        }
        return ['nbr' => App::db()->transaction(fn (Db $db) => MrpController::makePr($db, $rows))];
    }

    #[Get('/purchase-requisitions/new')]
    public function newPage(): string
    {
        return $this->editor(null);
    }

    #[Get('/purchase-requisitions/{nbr}')]
    public function editPage(string $nbr): string
    {
        self::head($nbr);
        return $this->editor($nbr);
    }

    #[Describe('Purchase requisitions', ['q' => 'number or requester', 'status' => 'A open, O ordered, X cancelled'])]

    #[Get('/api/purchase-requisitions')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), self::STATUS, true)) {
            $where[] = 'pr_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(pr_nbr LIKE ? OR pr_req_by LIKE ? OR pr_dept LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT pr_nbr, pr_date, pr_req_by, pr_dept, ROUND(pr_tot_amt - pr_tax_amt, 2) AS pr_net_amt, pr_tax_amt, pr_tot_amt, pr_status',
            'FROM pr_mstr WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'pr_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['pr_nbr' => 'pr_nbr', 'pr_date' => 'pr_date', 'pr_req_by' => 'pr_req_by', 'pr_net_amt' => 'ROUND(pr_tot_amt - pr_tax_amt, 2)', 'pr_tax_amt' => 'pr_tax_amt', 'pr_tot_amt' => 'pr_tot_amt', 'pr_status' => 'pr_status'],
            'pr_nbr'
        );
    }

    #[Describe('One purchase requisition with its lines')]

    #[Get('/api/purchase-requisitions/{nbr}')]
    public function show(string $nbr): array
    {
        $head = self::head($nbr);
        $head['_editable'] = $head['pr_status'] === 'A' && Auth::can('purchase-requisitions', 'edit');
        return ['head' => $head, 'lines' => self::lines($nbr)];
    }

    #[Post('/api/purchase-requisitions')]
    public function create(Request $request): array
    {
        [$head, $lines] = self::check($request->body);
        return ['nbr' => App::db()->transaction(fn () => self::write(null, $head, $lines))];
    }

    #[Put('/api/purchase-requisitions/{nbr}')]
    public function update(string $nbr, Request $request): array
    {
        if (self::head($nbr)['pr_status'] !== 'A') {
            throw new HttpException(409, t('purchase.not_editable'));
        }
        [$head, $lines] = self::check($request->body);
        return ['nbr' => App::db()->transaction(fn () => self::write($nbr, $head, $lines))];
    }

    #[Post('/api/purchase-requisitions/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        self::setStatus($nbr, ['A'], 'X');
        return null;
    }

    // ---------------------------------------------------------------- helpers

    private static function check(array $b): array
    {
        $c = new Check();
        $h = [
            'pr_date'    => $c->date('pr_date', $b['pr_date'] ?? '', true),
            'pr_req_by'  => $c->text('pr_req_by', $b['pr_req_by'] ?? '', true, 50, false),
            'pr_dept'    => $c->text('pr_dept', $b['pr_dept'] ?? '', false, 50, false),
            'pr_remarks' => $c->text('pr_remarks', $b['pr_remarks'] ?? '', false, 2000, false),
        ];
        $lines = [];
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $i => $l) {
            if (!is_array($l) || (trim((string) ($l['prd_part'] ?? '')) === '' && trim((string) ($l['prd_qty_req'] ?? '')) === '')) {
                continue;
            }
            $f = fn (string $n) => "lines.$i.$n";
            [$part, $desc, $um] = Stock::lineItem($c, $f('prd_part'), $l['prd_part'] ?? '', $f('prd_desc'), $l['prd_desc'] ?? '', $f('prd_um'), $l['prd_um'] ?? '', false);
            $lines[$i] = [
                'prd_part'     => $part,
                'prd_desc'     => $desc,
                'prd_um'       => $um,
                'prd_qty_req'  => $c->number($f('prd_qty_req'), $l['prd_qty_req'] ?? '', true, 0, true),
                'prd_req_date' => $c->date($f('prd_req_date'), $l['prd_req_date'] ?? '', true),
                'prd_est_cost' => $c->number($f('prd_est_cost'), $l['prd_est_cost'] ?? '', false, 0) ?? 0.0,
                'prd_pref_vd'  => $c->exists($f('prd_pref_vd'), $l['prd_pref_vd'] ?? '', 'vd_mstr', 'vd_code', false),
            ];
        }
        if (!$lines) {
            $c->fail('lines', t('stock.no_lines'));
        }
        $c->done();
        return [$h, $lines];
    }

    private static function write(?string $nbr, array $h, array $lines): string
    {
        $db = App::db();
        $user = Auth::userId();
        $rows = [];
        $n = 0;
        foreach ($lines as $l) {
            $part = Stock::part($l['prd_part']);
            $rows[] = ['prd_um' => $part['pt_um'] ?? $l['prd_um'] ?? 'EA'] + $l + [
                'prd_line' => ++$n,
                'prd_line_amt' => round($l['prd_qty_req'] * $l['prd_est_cost'], 2),
            ];
            $rows[$n - 1]['prd_desc'] = $l['prd_desc'] ?? $part['pt_desc1'] ?? null;
        }
        $h['pr_tot_amt'] = round(array_sum(array_column($rows, 'prd_line_amt')), 2);

        if ($nbr === null) {
            $nbr = Numbering::next('PR', $h['pr_date'] ?? null);
            $db->insert('pr_mstr', $h + ['pr_nbr' => $nbr, 'pr_status' => 'A', 'created_by' => $user, 'updated_by' => $user]);
        } else {
            $db->update('pr_mstr', $h + ['updated_by' => $user], ['pr_nbr' => $nbr]);
            $db->delete('prd_det', ['prd_nbr' => $nbr]);
        }
        foreach ($rows as $r) {
            $db->insert('prd_det', $r + ['prd_nbr' => $nbr, 'created_by' => $user, 'updated_by' => $user]);
        }
        return $nbr;
    }

    private static function head(string $nbr): array
    {
        return App::db()->one('SELECT * FROM pr_mstr WHERE pr_nbr = ?', [$nbr]) ?? throw HttpException::notFound();
    }

    private static function lines(string $nbr): array
    {
        return App::db()->all('SELECT * FROM prd_det WHERE prd_nbr = ? ORDER BY prd_line', [$nbr]);
    }

    private static function setStatus(string $nbr, array $from, string $to, array $more = []): void
    {
        $old = self::head($nbr);
        if (!in_array($old['pr_status'], $from, true)) {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        $new = ['pr_status' => $to] + $more;
        App::db()->transaction(function (Db $db) use ($nbr, $old, $new) {
            $db->update('pr_mstr', $new + ['updated_by' => Auth::userId()], ['pr_nbr' => $nbr]);
            Audit::updated('pr_mstr', "pr_nbr=$nbr", $old, $new);
        });
    }

    private function editor(?string $nbr): string
    {
        $config = [
            'api'    => '/api/purchase-requisitions',
            'numbering' => 'PR', // the number field, may be typed (Numbering::next)
            'page'   => '/purchase-requisitions',
            'nbr'    => $nbr,
            'nbrField' => 'pr_nbr',
            'statusField' => 'pr_status',
            'status' => self::statusText(),
            'canEdit' => Auth::can('purchase-requisitions', $nbr ? 'edit' : 'add'),
            'head'   => [
                ['name' => 'pr_date', 'label' => t('purchase.date'), 'type' => 'date', 'col' => 2, 'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'pr_req_by', 'label' => t('purchase.req_by'), 'type' => 'person', 'col' => 3, 'required' => true, 'default' => Auth::personName()],
                ['name' => 'pr_dept', 'label' => t('purchase.dept'), 'type' => 'text', 'col' => 3],
                ['name' => 'pr_remarks', 'label' => t('purchase.remarks'), 'type' => 'text', 'col' => 4],
            ],
            'lines'  => [
                'columns' => [
                    ['name' => 'prd_part', 'label' => t('purchase.part'), 'type' => 'part', 'width' => '11rem'],
                    ['name' => 'prd_desc', 'label' => t('purchase.desc'), 'type' => 'text'],
                    ['name' => 'prd_qty_req', 'label' => t('purchase.qty'), 'type' => 'number', 'width' => '6.5rem'],
                    ['name' => 'prd_um', 'label' => t('purchase.um'), 'type' => 'um', 'width' => '5rem'],
                    ['name' => 'prd_req_date', 'label' => t('purchase.need_date'), 'type' => 'date', 'width' => '9.5rem', 'default' => date('Y-m-d', strtotime('+7 days'))],
                    ['name' => 'prd_est_cost', 'label' => t('purchase.est_price'), 'type' => 'number', 'width' => '7.5rem'],
                    ['name' => 'prd_line_amt', 'label' => t('purchase.amount'), 'type' => 'amount', 'width' => '8rem'],
                    ['name' => 'prd_pref_vd', 'label' => t('purchase.pref_vendor'), 'type' => 'vendor', 'width' => '9rem'],
                    ['name' => 'prd_po_nbr', 'label' => t('purchase.po'), 'type' => 'static', 'width' => '7rem', 'existing' => true, 'link' => '/purchase-orders/'],
                ],
                'part'   => ['desc' => 'prd_desc', 'um' => 'prd_um', 'price' => 'prd_est_cost', 'priceApi' => '/api/purchase/last-price'],
                'amount' => ['qty' => 'prd_qty_req', 'price' => 'prd_est_cost', 'out' => 'prd_line_amt'],
            ],
            'totals' => 'net',
            'actions' => [
                ['name' => 'to-po', 'label' => t('purchase.make_po'), 'post' => '/api/purchase-orders/from-pr', 'body' => 'pr', 'askVendor' => true,
                 'go' => '/purchase-orders/', 'style' => 'btn-ghost-primary', 'when' => ['A'], 'can' => Auth::can('purchase-orders', 'add')],
                ['name' => 'cancel', 'label' => t('purchase.cancel_doc'), 'api' => '/cancel', 'style' => 'btn-ghost-danger', 'when' => ['A'],
                 'can' => Auth::can('purchase-requisitions', 'delete'), 'confirm' => t('purchase.cancel_confirm')],
                ['name' => 'print', 'label' => t('purchase.print'), 'href' => '/documents/purchase-requisitions/{nbr}/print', 'target' => '_blank', 'style' => 'btn',
                 'when' => array_keys(self::statusText()), 'can' => Auth::can('purchase-requisitions', 'print')],
            ],
        ];
        $config['files'] = 'PR';
        return View::render('doc/edit', [
            'help'    => 'purchase-requisitions',
            'title'   => $nbr ?? t('purchase.pr_new'),
            'ownToolbar' => true,
            'config'  => $config,
            'scripts' => ['js/doc.js'],
        ]);
    }

    public static function statusText(): array
    {
        return [
            'A' => ['text' => t('purchase.status_A'), 'color' => 'blue'],
            'O' => ['text' => t('purchase.status_O'), 'color' => 'green'],
            'X' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }
}
