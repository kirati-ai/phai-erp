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
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Inventory\Stock;
use Phai\Modules\Master\Money;

/** Quotations. Status: O open, W won (a sales order was made), L lost. */
#[Program('quotations')]
final class QtController
{
    #[Get('/quotations')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'    => 'quotations',
            'title'   => t('sales.qt_title'),
            'config'  => [
                'api'     => '/api/quotations',
                'page'    => '/quotations',
                'canAdd'  => Auth::can('quotations', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'qt_nbr', 'label' => t('sales.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'qt_date', 'label' => t('sales.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'qt_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'qt_expire', 'label' => t('sales.valid_until'), 'type' => 'date', 'sort' => true],
                    ['key' => 'qt_tot_amt', 'label' => t('sales.total'), 'type' => 'money', 'sort' => true, 'suffix' => 'qt_curr'],
                    ['key' => 'qt_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'scripts' => ['js/doclist.js'],
        ]);
    }

    #[Get('/quotations/new')]
    public function newPage(): string
    {
        return $this->editor(null);
    }

    #[Get('/quotations/{nbr}')]
    public function editPage(string $nbr): string
    {
        self::head($nbr);
        return $this->editor($nbr);
    }

    #[Get('/quotations/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $lines = self::lines($nbr);
        return SalesPrint::render([
            'title'  => t('sales.qt_title'),
            'nbr'    => $nbr,
            'meta'   => [[t('sales.date'), SalesPrint::date($h['qt_date'])], [t('sales.valid_until'), SalesPrint::date($h['qt_expire'])],
                         [t('sales.terms'), $h['qt_cr_terms']], [t('sales.salesperson'), $h['qt_sales_rep']]],
            'party'  => SalesPrint::customer($h['qt_cm_code']),
            'lines'  => SalesPrint::priceLines($lines, 'qtd', 'qtd_qty'),
            'totals' => SalesPrint::totals($h['qt_net_amt'], $h['qt_tax_pct'], $h['qt_tax_amt'], $h['qt_tot_amt'], $h['qt_curr']),
            'notes'  => $h['qt_remarks'],
            'signs'  => [SalesPrint::outside(t('sales.sign_customer')), SalesPrint::approve(t('sales.sign_authorized'))],
        ]);
    }

    #[Describe('Sales quotations', ['q' => 'number or customer', 'status' => 'O open, W won, L lost'])]

    #[Get('/api/quotations')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['O', 'W', 'L'], true)) {
            $where[] = 'q.qt_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(q.qt_nbr LIKE ? OR q.qt_cm_code LIKE ? OR c.cm_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT q.qt_nbr, q.qt_date, q.qt_cm_code, c.cm_name, q.qt_expire, q.qt_curr, q.qt_tot_amt, q.qt_status',
            'FROM qt_mstr q LEFT JOIN cm_mstr c ON c.cm_code = q.qt_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'qt_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['qt_nbr' => 'q.qt_nbr', 'qt_date' => 'q.qt_date', 'qt_cm_code' => 'q.qt_cm_code', 'qt_expire' => 'q.qt_expire',
             'qt_tot_amt' => 'q.qt_tot_amt', 'qt_status' => 'q.qt_status'],
            'qt_nbr'
        );
    }

    #[Describe('One sales quotation with lines')]

    #[Get('/api/quotations/{nbr}')]
    public function show(string $nbr): array
    {
        $head = self::head($nbr);
        $head['_editable'] = $head['qt_status'] === 'O' && Auth::can('quotations', 'edit');
        return ['head' => $head, 'lines' => self::lines($nbr)];
    }

    #[Post('/api/quotations')]
    public function create(Request $request): array
    {
        [$h, $lines] = self::check($request->body);
        return ['nbr' => App::db()->transaction(fn () => self::write(null, $h, $lines))];
    }

    #[Put('/api/quotations/{nbr}')]
    public function update(string $nbr, Request $request): array
    {
        if (self::head($nbr)['qt_status'] !== 'O') {
            throw new HttpException(409, t('purchase.not_editable'));
        }
        [$h, $lines] = self::check($request->body);
        return ['nbr' => App::db()->transaction(fn () => self::write($nbr, $h, $lines))];
    }

    #[Post('/api/quotations/{nbr}/lost'), Action('edit')]
    public function lost(string $nbr): null
    {
        $old = self::head($nbr);
        if ($old['qt_status'] !== 'O') {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->update('qt_mstr', ['qt_status' => 'L', 'updated_by' => Auth::userId()], ['qt_nbr' => $nbr]);
        Audit::updated('qt_mstr', "qt_nbr=$nbr", $old, ['qt_status' => 'L']);
        return null;
    }

    // ---------------------------------------------------------------- helpers

    private static function check(array $b): array
    {
        $c = new Check();
        $h = [
            'qt_cm_code'   => $c->exists('qt_cm_code', $b['qt_cm_code'] ?? '', 'cm_mstr', 'cm_code', true, "cm_status = 'ACTIVE'"),
            'qt_date'      => $c->date('qt_date', $b['qt_date'] ?? '', true),
            'qt_expire'    => $c->date('qt_expire', $b['qt_expire'] ?? '', true),
            'qt_cr_terms'  => $c->exists('qt_cr_terms', $b['qt_cr_terms'] ?? '', 'ct_mstr', 'ct_code', false),
            'qt_curr'      => $c->exists('qt_curr', $b['qt_curr'] ?? '', 'cu_mstr', 'cu_curr', true),
            'qt_ex_rate'   => $c->number('qt_ex_rate', $b['qt_ex_rate'] ?? '', true, 0, true),
            'qt_tax_code'  => $c->exists('qt_tax_code', $b['qt_tax_code'] ?? '', 'tx_mstr', 'tx_tax_code', false),
            'qt_sales_rep' => $c->text('qt_sales_rep', $b['qt_sales_rep'] ?? '', false, 50, false),
            'qt_remarks'   => $c->text('qt_remarks', $b['qt_remarks'] ?? '', false, 2000, false),
        ];
        if ($h['qt_date'] && $h['qt_expire'] && $h['qt_expire'] < $h['qt_date']) {
            $c->fail('qt_expire', t('sales.expire_before_date'));
        }
        $lines = SalesLines::check($c, $b['lines'] ?? null, 'qtd', 'qtd_qty');
        $c->done();
        return [$h, $lines];
    }

    private static function write(?string $nbr, array $h, array $lines): string
    {
        $db = App::db();
        $user = Auth::userId();
        $rows = SalesLines::rows($lines, 'qtd', 'qtd_qty');
        $pct = Money::taxPct($h['qt_tax_code']);
        $tot = Money::totals(array_column($rows, 'qtd_line_amt'), $pct);
        $h += ['qt_tax_pct' => $pct, 'qt_net_amt' => $tot['net'], 'qt_tax_amt' => $tot['tax'], 'qt_tot_amt' => $tot['total'], 'qt_ex_date' => $h['qt_date']];

        if ($nbr === null) {
            $nbr = Numbering::next('QT', $h['qt_date']);
            $db->insert('qt_mstr', $h + ['qt_nbr' => $nbr, 'qt_status' => 'O', 'created_by' => $user, 'updated_by' => $user]);
        } else {
            $db->update('qt_mstr', $h + ['updated_by' => $user], ['qt_nbr' => $nbr]);
            $db->delete('qtd_det', ['qtd_nbr' => $nbr]);
        }
        foreach ($rows as $r) {
            $db->insert('qtd_det', $r + ['qtd_nbr' => $nbr, 'qtd_tax_code' => $h['qt_tax_code'], 'created_by' => $user, 'updated_by' => $user]);
        }
        return $nbr;
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT q.*, c.cm_name FROM qt_mstr q LEFT JOIN cm_mstr c ON c.cm_code = q.qt_cm_code WHERE q.qt_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all('SELECT * FROM qtd_det WHERE qtd_nbr = ? ORDER BY qtd_line', [$nbr]);
    }

    private function editor(?string $nbr): string
    {
        $config = SalesLines::editorConfig('qt', 'qtd', 'qtd_qty', [
            'api'    => '/api/quotations',
            'numbering' => 'QT', // the number field, may be typed (Numbering::next)
            'page'   => '/quotations',
            'nbr'    => $nbr,
            'statusField' => 'qt_status',
            'status' => self::statusText(),
            'canEdit' => Auth::can('quotations', $nbr ? 'edit' : 'add'),
            'head'   => [
                ['name' => 'qt_cm_code', 'label' => t('sales.customer'), 'type' => 'customer', 'col' => 4, 'required' => true, 'nameField' => 'cm_name'],
                ['name' => 'qt_date', 'label' => t('sales.date'), 'type' => 'date', 'col' => 2, 'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'qt_expire', 'label' => t('sales.valid_until'), 'type' => 'date', 'col' => 2, 'required' => true, 'default' => date('Y-m-d', strtotime('+30 days'))],
                ['name' => 'qt_cr_terms', 'label' => t('sales.terms'), 'type' => 'select', 'col' => 4, 'options' => Lookup::options('credit-terms')],
                ['name' => 'qt_curr', 'label' => t('sales.currency'), 'type' => 'select', 'col' => 2, 'required' => true, 'options' => Lookup::options('currencies'), 'default' => Money::base()],
                ['name' => 'qt_ex_rate', 'label' => t('sales.rate'), 'type' => 'number', 'col' => 2, 'required' => true, 'default' => '1'],
                ['name' => 'qt_tax_code', 'label' => t('sales.tax'), 'type' => 'select', 'col' => 2, 'options' => Money::taxOptions()],
                ['name' => 'qt_sales_rep', 'label' => t('sales.salesperson'), 'type' => 'text', 'col' => 2],
                ['name' => 'qt_remarks', 'label' => t('sales.remarks'), 'type' => 'text', 'col' => 4],
            ],
            'partyDefaults' => ['qt_cr_terms' => 'cm_terms', 'qt_curr' => 'cm_curr', 'qt_tax_code' => 'cm_tax_code', 'qt_sales_rep' => 'cm_salesperson'],
            'rate'   => ['curr' => 'qt_curr', 'date' => 'qt_date', 'out' => 'qt_ex_rate'],
            'tax'    => 'qt_tax_code',
            'priceParams' => ['customer' => 'qt_cm_code', 'date' => 'qt_date'],
            'actions' => [
                ['name' => 'so', 'label' => t('sales.make_so'), 'post' => '/api/sales-orders/from-qt', 'body' => 'qt', 'go' => '/sales-orders/',
                 'style' => 'btn-success', 'when' => ['O'], 'can' => Auth::can('sales-orders', 'add'), 'confirm' => t('sales.make_so_confirm')],
                ['name' => 'lost', 'label' => t('sales.mark_lost'), 'api' => '/lost', 'style' => 'btn-ghost-secondary', 'when' => ['O'],
                 'can' => Auth::can('quotations', 'edit'), 'confirm' => t('sales.lost_confirm')],
                ['name' => 'print', 'label' => t('sales.print'), 'href' => '/quotations/{nbr}/print', 'target' => '_blank', 'style' => 'btn',
                 'when' => ['O', 'W', 'L'], 'can' => Auth::can('quotations', 'print')],
            ],
        ]);
        return View::render('doc/edit', ['help' => 'quotations', 'title' => $nbr ?? t('sales.qt_new'), 'config' => $config, 'scripts' => ['js/doc.js']]);
    }

    public static function statusText(): array
    {
        return [
            'O' => ['text' => t('sales.qt_status_O'), 'color' => 'yellow'],
            'W' => ['text' => t('sales.qt_status_W'), 'color' => 'green'],
            'L' => ['text' => t('sales.qt_status_L'), 'color' => 'secondary'],
        ];
    }
}
