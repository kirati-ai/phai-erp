<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\BahtText;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Master\Money;

/**
 * Billing notes: a customer's open invoices collected on one paper for payment.
 * An invoice can be on one open billing note at a time. Status: O open, P paid, C cancelled.
 */
#[Program('billings')]
final class BillingController
{
    #[Get('/billings')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'    => 'billings',
            'title'   => t('sales.bl_title'),
            'config'  => [
                'api'     => '/api/billings',
                'page'    => '/billings',
                'canAdd'  => Auth::can('billings', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'bl_nbr', 'label' => t('sales.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'bl_date', 'label' => t('sales.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'bl_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'bl_due_date', 'label' => t('sales.pay_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'bl_tot_amt', 'label' => t('sales.total'), 'type' => 'money', 'sort' => true],
                    ['key' => 'bl_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Unpaid invoices not in an open billing note above the list: tick rows of one customer to make one billing note. */
    public static function queues(): array
    {
        return Auth::can('billings', 'add') ? [[
            'title'   => t('sales.queue_bl'),
            'api'     => '/api/billings/waiting',
            'key'     => 'ih_inv_nbr',
            'urgent'  => 'overdue',
            'columns' => [
                ['key' => 'ih_inv_nbr', 'label' => t('sales.invoice'), 'link' => '/invoices/{ih_inv_nbr}'],
                ['key' => 'ih_inv_date', 'label' => t('sales.date'), 'type' => 'date'],
                ['key' => 'customer', 'label' => t('sales.customer')],
                ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                ['key' => 'ih_due_date', 'label' => t('sales.due_date'), 'type' => 'date'],
                ['key' => 'balance', 'label' => t('sales.balance'), 'type' => 'money'],
            ],
            'multi'   => ['label' => t('sales.make_bl'), 'icon' => 'ti ti-receipt', 'same' => 'customer', 'sameText' => t('sales.one_customer'),
                          'href' => '/billings/new?customer={customer}&invoices={keys}'],
        ]] : [];
    }

    #[Get('/billings/new')]
    public function newPage(): string
    {
        return $this->page(null);
    }

    #[Get('/billings/{nbr}')]
    public function viewPage(string $nbr): string
    {
        self::head($nbr);
        return $this->page($nbr);
    }

    #[Get('/billings/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        $h = self::head($nbr);
        $m = fn ($v) => number_format((float) $v, 2);
        $lines = self::lines($nbr);
        $curr = (string) App::db()->value('SELECT ih_curr FROM ih_mstr WHERE ih_inv_nbr = ?', [$lines[0]['bld_inv_nbr'] ?? '']);
        $baht = $curr === '' || $curr === Money::base();
        return SalesPrint::render([
            'title' => t('sales.bl_title'),
            'nbr'   => $nbr,
            'meta'  => [[t('sales.date'), SalesPrint::date($h['bl_date'])], [t('sales.pay_date'), SalesPrint::date($h['bl_due_date'])]],
            'party' => SalesPrint::customer($h['bl_cm_code']),
            'lines' => [
                'columns' => [
                    ['label' => '#', 'width' => '28px'], ['label' => t('sales.invoice')],
                    ['label' => t('sales.date'), 'width' => '90px'], ['label' => t('sales.due_date'), 'width' => '90px'],
                    ['label' => t('sales.inv_amount'), 'align' => 'r', 'width' => '110px'], ['label' => t('sales.balance'), 'align' => 'r', 'width' => '110px'],
                ],
                'rows' => array_map(fn ($l) => [
                    $l['bld_line'], $l['bld_inv_nbr'], SalesPrint::date($l['bld_inv_date']), SalesPrint::date($l['bld_due_date']),
                    $m($l['bld_inv_amt']), $m($l['bld_bal_amt']),
                ], $lines),
            ],
            'totals' => [
                'rows'  => [
                    [t('sales.subtotal'), $m((float) $h['bl_tot_amt'] - ($vat = array_sum(array_column($lines, 'bld_vat')))), false],
                    [t('sales.vat'), $m($vat), false],
                    [t('sales.total') . ($baht ? '' : " ($curr)"), $m($h['bl_tot_amt']), true],
                ],
                'words' => $baht ? BahtText::of((float) $h['bl_tot_amt']) : '',
            ],
            'notes' => $h['bl_remarks'],
            'signs' => [t('sales.sign_receiver'), t('sales.sign_collector')],
        ]);
    }

    #[Describe('Billing notes sent to customers', ['q' => 'number or customer', 'status' => 'O open, P paid, C cancelled'])]

    #[Get('/api/billings')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['O', 'P', 'C'], true)) {
            $where[] = 'b.bl_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(b.bl_nbr LIKE ? OR b.bl_cm_code LIKE ? OR c.cm_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT b.bl_nbr, b.bl_date, b.bl_cm_code, c.cm_name, b.bl_due_date, b.bl_tot_amt, b.bl_status',
            'FROM bl_mstr b LEFT JOIN cm_mstr c ON c.cm_code = b.bl_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'bl_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['bl_nbr' => 'b.bl_nbr', 'bl_date' => 'b.bl_date', 'bl_cm_code' => 'b.bl_cm_code', 'bl_due_date' => 'b.bl_due_date',
             'bl_tot_amt' => 'b.bl_tot_amt', 'bl_status' => 'b.bl_status'],
            'bl_nbr'
        );
    }

    /** ?customer= — unpaid invoices not on an open billing note. */
    #[Get('/api/billings/open-invoices')]
    public function openInvoices(Request $request): array
    {
        return self::open((string) $request->input('customer', ''));
    }

    #[Describe('Unpaid invoices and debit notes (status U) not on an open billing note, by due date; overdue = due date passed; balance in the invoice currency')]
    #[Get('/api/billings/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            "SELECT h.ih_inv_nbr, h.ih_inv_date, h.ih_cm_code AS customer, c.cm_name, h.ih_due_date, h.ih_curr,
                    ROUND(h.ih_tot_amt - h.ih_paid_amt, 2) AS balance, h.ih_due_date < ? AS overdue
               FROM ih_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code
              WHERE h.ih_status = 'U' AND ROUND(h.ih_tot_amt - h.ih_paid_amt, 2) <> 0
                AND NOT EXISTS (SELECT 1 FROM bld_det d JOIN bl_mstr b ON b.bl_nbr = d.bld_nbr
                                 WHERE d.bld_inv_nbr = h.ih_inv_nbr AND b.bl_status = 'O')
              ORDER BY h.ih_due_date, h.ih_inv_nbr LIMIT 200",
            [date('Y-m-d')]
        );
    }

    #[Describe('One billing note with its invoices')]

    #[Get('/api/billings/{nbr}')]
    public function show(string $nbr): array
    {
        return ['head' => self::head($nbr), 'lines' => self::lines($nbr)];
    }

    /** Body: {customer, bl_date, bl_due_date, bl_remarks, invoices: [nbr, ...]} */
    #[Post('/api/billings')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $customer = $c->exists('customer', $b['customer'] ?? '', 'cm_mstr', 'cm_code');
        $h = [
            'bl_date'     => $c->date('bl_date', $b['bl_date'] ?? '', true),
            'bl_due_date' => $c->date('bl_due_date', $b['bl_due_date'] ?? ''),
            'bl_remarks'  => $c->text('bl_remarks', $b['bl_remarks'] ?? '', false, 2000, false),
        ];
        $c->done();

        $wanted = array_values(array_unique(array_filter(is_array($b['invoices'] ?? null) ? $b['invoices'] : [], 'is_string')));
        if (!$wanted) {
            throw HttpException::invalid(['invoices' => t('sales.pick_invoice')]);
        }

        $nbr = App::db()->transaction(function (Db $db) use ($customer, $h, $wanted) {
            // Lock the invoices, then make sure each is still open and free.
            $marks = implode(',', array_fill(0, count($wanted), '?'));
            $db->all("SELECT ih_inv_nbr FROM ih_mstr WHERE ih_inv_nbr IN ($marks) FOR UPDATE", $wanted);
            $open = array_column(self::open($customer), null, 'ih_inv_nbr');
            $currencies = [];
            foreach ($wanted as $inv) {
                if (!isset($open[$inv])) {
                    throw HttpException::invalid(['invoices' => t('sales.inv_not_open', ['nbr' => $inv])]);
                }
                $currencies[$open[$inv]['ih_curr']] = true;
            }
            if (count($currencies) > 1) {
                throw HttpException::invalid(['invoices' => t('sales.one_currency')]);
            }

            $nbr = Numbering::next('BL', $h['bl_date']);
            $user = Auth::userId();
            $total = 0.0;
            foreach ($wanted as $i => $inv) {
                $o = $open[$inv];
                $bal = round((float) $o['ih_tot_amt'] - (float) $o['ih_paid_amt'], 2);
                $total += $bal;
                $db->insert('bld_det', [
                    'bld_nbr' => $nbr, 'bld_line' => $i + 1, 'bld_inv_nbr' => $inv, 'bld_inv_date' => $o['ih_inv_date'],
                    'bld_due_date' => $o['ih_due_date'], 'bld_inv_amt' => $o['ih_tot_amt'], 'bld_paid_amt' => $o['ih_paid_amt'],
                    'bld_bal_amt' => $bal, 'created_by' => $user, 'updated_by' => $user,
                ]);
            }
            $db->insert('bl_mstr', $h + [
                'bl_nbr' => $nbr, 'bl_cm_code' => $customer, 'bl_status' => 'O', 'bl_tot_amt' => round($total, 2), 'bl_paid_amt' => 0,
                'created_by' => $user, 'updated_by' => $user,
            ]);
            return $nbr;
        });
        return ['nbr' => $nbr];
    }

    #[Post('/api/billings/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        $h = self::head($nbr);
        if ($h['bl_status'] !== 'O') {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        App::db()->update('bl_mstr', ['bl_status' => 'C', 'updated_by' => Auth::userId()], ['bl_nbr' => $nbr]);
        Audit::updated('bl_mstr', "bl_nbr=$nbr", $h, ['bl_status' => 'C']);
        return null;
    }

    private static function open(string $customer): array
    {
        return App::db()->all(
            "SELECT h.ih_inv_nbr, h.ih_inv_date, h.ih_due_date, h.ih_curr, h.ih_tot_amt, h.ih_tax_amt, h.ih_paid_amt
               FROM ih_mstr h
              WHERE h.ih_cm_code = ? AND h.ih_status = 'U' AND ROUND(h.ih_tot_amt - h.ih_paid_amt, 2) <> 0
                AND NOT EXISTS (SELECT 1 FROM bld_det d JOIN bl_mstr b ON b.bl_nbr = d.bld_nbr
                                 WHERE d.bld_inv_nbr = h.ih_inv_nbr AND b.bl_status = 'O')
              ORDER BY h.ih_due_date, h.ih_inv_nbr",
            [$customer]
        );
    }

    public static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT b.*, c.cm_name FROM bl_mstr b LEFT JOIN cm_mstr c ON c.cm_code = b.bl_cm_code WHERE b.bl_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function lines(string $nbr): array
    {
        // bld_vat: the VAT in what is billed (the invoice's VAT share of its balance)
        return App::db()->all(
            'SELECT d.*, ROUND(d.bld_bal_amt * h.ih_tax_amt / NULLIF(h.ih_tot_amt, 0), 2) AS bld_vat
               FROM bld_det d LEFT JOIN ih_mstr h ON h.ih_inv_nbr = d.bld_inv_nbr
              WHERE d.bld_nbr = ? ORDER BY d.bld_line',
            [$nbr]
        );
    }

    private function page(?string $nbr): string
    {
        if ($nbr !== null) { // a saved billing note is a read-only view like a purchase order (ux/ui.md "View pages")
            return View::render('sales/billing-view', [
                'help'    => 'billings',
                'title'      => $nbr,
                'ownToolbar' => true,
                'head'       => self::head($nbr),
                'lines'      => self::lines($nbr),
                'status'     => self::statusText(),
                'canPrint'   => Auth::can('billings', 'print'),
                'canCancel'  => Auth::can('billings', 'delete'),
                'scripts'    => ['js/view.js'],
            ]);
        }
        return View::render('sales/billing', [
            'help'    => 'billings',
            'docNbr' => $nbr === null ? Numbering::peek('BL') : null,
            'title'   => $nbr ?? t('sales.bl_new'),
            'config'  => [
                'nbr'       => $nbr,
                'status'    => self::statusText(),
                'canPrint'  => Auth::can('billings', 'print'),
                'canCancel' => Auth::can('billings', 'delete'),
                'today'     => date('Y-m-d'),
                'base'      => Money::base(),
            ],
            'scripts' => ['js/pages/billing.js'],
        ]);
    }

    public static function statusText(): array
    {
        return [
            'O' => ['text' => t('sales.bl_status_O'), 'color' => 'yellow'],
            'P' => ['text' => t('sales.inv_status_P'), 'color' => 'green'],
            'C' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }
}
