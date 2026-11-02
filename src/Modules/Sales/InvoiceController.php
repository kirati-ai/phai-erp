<?php

declare(strict_types=1);

namespace Phai\Modules\Sales;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\BahtText;
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
use Phai\Modules\Accounting\ArInvoiceController;
use Phai\Modules\Accounting\Etax;
use Phai\Modules\Accounting\Gl;
use Phai\Modules\Master\Money;

/**
 * Invoices / tax invoices, one per shipment, at the order's prices.
 * Each invoice opens a receivable (ar_mstr, in baht). Status: U unpaid, P paid, C cancelled.
 */
#[Program('invoices')]
final class InvoiceController
{
    #[Get('/invoices')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'help'    => 'invoices',
            'title'   => t('sales.inv_title'),
            'config'  => [
                'api'     => '/api/invoices',
                'page'    => '/invoices',
                'canAdd'  => Auth::can('invoices', 'add'),
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'ih_inv_nbr', 'label' => t('sales.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'ih_inv_date', 'label' => t('sales.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'ih_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'ih_so_nbr', 'label' => t('sales.so')],
                    ['key' => 'ih_due_date', 'label' => t('sales.due_date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'ih_tot_amt', 'label' => t('sales.total'), 'type' => 'money', 'sort' => true, 'suffix' => 'ih_curr'],
                    ['key' => 'ih_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Shipments not invoiced yet above the list: one click makes the invoice dated today. */
    public static function queues(): array
    {
        return Auth::can('invoices', 'add') ? [[
            'title'   => t('sales.queue_inv'),
            'api'     => '/api/invoices/waiting',
            'key'     => 'sh_nbr',
            'columns' => [
                ['key' => 'sh_nbr', 'label' => t('sales.shipment'), 'link' => '/shipments/{sh_nbr}'],
                ['key' => 'sh_date', 'label' => t('sales.date'), 'type' => 'date'],
                ['key' => 'sh_so_nbr', 'label' => t('sales.so'), 'link' => '/sales-orders/{sh_so_nbr}'],
                ['key' => 'sh_cm_code', 'label' => t('sales.customer')],
                ['key' => 'cm_name', 'label' => t('sales.customer_name')],
            ],
            'action'  => ['label' => t('sales.make_invoice'), 'icon' => 'ti ti-file-invoice', 'href' => '/invoices/new?sh={sh_nbr}'],
        ]] : [];
    }

    /** Shipments waiting for an invoice. */
    #[Get('/invoices/new')]
    public function newPage(Request $request): string
    {
        $sh = strtoupper(trim((string) ($request->query['sh'] ?? '')));
        if ($sh !== '') {
            return $this->editor(null, self::draft($sh));
        }
        return View::render('sales/invoice-new', ['help' => 'invoices', 'docNbr' => Numbering::peek('INV'), 'title' => t('sales.inv_new'), 'scripts' => ['js/pages/invoice-new.js']]);
    }

    #[Get('/invoices/{nbr}')]
    public function viewPage(string $nbr): string
    {
        self::head($nbr);
        return $this->editor($nbr);
    }

    /**
     * The invoice a shipment would get, not saved: its lines at the order's prices, dated today. The new-invoice page
     * shows it to check and change (date, due date, customer PO, remarks, price, discount) before saving.
     */
    public static function draft(string $sh): array
    {
        $db = App::db();
        $ship = $db->one('SELECT * FROM sh_mstr WHERE sh_nbr = ?', [$sh]);
        if (!$ship) {
            throw HttpException::notFound();
        }
        if ($ship['sh_status'] !== 'C') {
            throw new HttpException(409, t('sales.sh_invoiced'));
        }
        $so = SoController::head($ship['sh_so_nbr']);
        $today = date('Y-m-d');
        return [
            'head' => [
                'ih_cm_code' => $so['so_cm_code'] . ' · ' . (Sales::customer($so['so_cm_code'])['cm_name'] ?? ''),
                'ih_inv_date' => $today, 'ih_due_date' => Money::dueDate($today, $so['so_cr_terms']),
                'ih_so_nbr' => $ship['sh_so_nbr'], 'ih_sh_nbr' => $sh, 'ih_po_ref' => $so['so_po_ref'], 'ih_cr_terms' => $so['so_cr_terms'],
                'ih_curr' => $so['so_curr'], 'ih_tax_code' => $so['so_tax_code'],
            ],
            'lines' => array_map(fn ($r) => [
                'ihd_part' => $r['shd_part'], 'pt_desc1' => $r['pt_desc1'], 'ihd_qty_inv' => $r['shd_qty_shp'], 'ihd_um' => $r['shd_um'],
                'ihd_price' => $r['sod_price'], 'ihd_disc_pct' => $r['sod_disc_pct'],
            ], self::shipmentLines($ship['sh_so_nbr'], $sh)),
            'body' => ['sh' => $sh],
        ];
    }

    /** One invoice line per order line, however many lots it went out from. */
    private static function shipmentLines(string $so, string $sh): array
    {
        return App::db()->all(
            'SELECT MIN(s.shd_line) AS shd_line, s.shd_sod_line, s.shd_part, SUM(s.shd_qty_shp) AS shd_qty_shp, s.shd_um, d.sod_price, d.sod_disc_pct,
                    pl.pl_sls_acct, COALESCE(NULLIF(d.sod_desc, \'\'), pt.pt_desc1) AS pt_desc1
               FROM shd_det s
               JOIN sod_det d ON d.sod_nbr = ? AND d.sod_line = s.shd_sod_line
               LEFT JOIN pt_mstr pt ON pt.pt_part = s.shd_part
               LEFT JOIN pl_mstr pl ON pl.pl_prod_line = pt.pt_prod_line
              WHERE s.shd_nbr = ?
              GROUP BY s.shd_sod_line, s.shd_part, s.shd_um, d.sod_price, d.sod_disc_pct, pl.pl_sls_acct, d.sod_desc, pt.pt_desc1
              ORDER BY shd_line',
            [$so, $sh]
        );
    }

    /** A saved invoice ($nbr), or a new one filled from a shipment ($prefill, see draft()). */
    private function editor(?string $nbr, ?array $prefill = null): string
    {
        $new = $nbr === null;
        $config = [
            'api' => '/api/invoices', 'page' => '/invoices', 'nbr' => $nbr, 'nbrField' => 'ih_inv_nbr',
            'statusField' => 'ih_status', 'status' => self::statusText(), 'canEdit' => $new && Auth::can('invoices', 'add'),
            'numbering' => $new ? 'INV' : null,
            'head' => [
                $new ? ['name' => 'ih_cm_code', 'label' => t('sales.customer'), 'type' => 'text', 'col' => 4, 'readonly' => true]
                     : ['name' => 'ih_cm_code', 'label' => t('sales.customer'), 'type' => 'customer', 'col' => 4, 'nameField' => 'cm_name'],
                ['name' => 'ih_inv_date', 'label' => t('sales.date'), 'type' => 'date', 'col' => 2, 'required' => $new],
                ['name' => 'ih_due_date', 'label' => t('sales.due_date'), 'type' => 'date', 'col' => 2],
                ['name' => 'ih_so_nbr', 'label' => t('sales.so'), 'type' => 'text', 'col' => 2, 'readonly' => true],
                ['name' => 'ih_sh_nbr', 'label' => t('sales.shipment'), 'type' => 'text', 'col' => 2, 'readonly' => true],
                ['name' => 'ih_po_ref', 'label' => t('sales.po_ref'), 'type' => 'text', 'col' => 3],
                ['name' => 'ih_cr_terms', 'label' => t('sales.terms'), 'type' => 'text', 'col' => 3, 'readonly' => true],
                ['name' => 'ih_curr', 'label' => t('sales.currency'), 'type' => 'text', 'col' => 2, 'readonly' => true],
                ['name' => 'ih_tax_code', 'label' => t('sales.tax'), 'type' => 'select', 'col' => 2, 'options' => Money::taxOptions(), 'readonly' => true],
                ...($new ? [['name' => 'ih_remarks', 'label' => t('sales.remarks'), 'type' => 'text', 'col' => 12]]
                         : [['name' => 'ih_paid_amt', 'label' => t('sales.paid'), 'type' => 'number', 'col' => 2]]),
            ],
            'lines' => [
                'fixed' => true, // the shipment's lines: no line added or taken out
                'columns' => [
                    ['name' => 'ihd_part', 'label' => t('sales.part'), 'type' => 'static', 'width' => '10rem'],
                    ['name' => 'pt_desc1', 'label' => t('sales.desc'), 'type' => 'static'],
                    ['name' => 'ihd_qty_inv', 'label' => t('sales.qty'), 'type' => 'number', 'width' => '6.5rem', 'readonly' => true],
                    ['name' => 'ihd_um', 'label' => t('sales.um'), 'type' => 'static', 'width' => '4rem'],
                    ['name' => 'ihd_price', 'label' => t('sales.price'), 'type' => 'number', 'width' => '7.5rem'],
                    ['name' => 'ihd_disc_pct', 'label' => t('sales.disc'), 'type' => 'number', 'width' => '5rem'],
                    ['name' => 'ihd_line_amt', 'label' => t('sales.amount'), 'type' => 'amount', 'width' => '8rem'],
                ],
                'amount' => ['qty' => 'ihd_qty_inv', 'price' => 'ihd_price', 'disc' => 'ihd_disc_pct', 'out' => 'ihd_line_amt'],
            ],
            'tax' => 'ih_tax_code',
            'totals' => true,
            'deduct' => 'ih_dp_amt', 'deductLabel' => t('deposit.deduct'), 'afterLabel' => t('deposit.after'),
            'actions' => [
                ['name' => 'etax-xml', 'label' => t('etax.xml'), 'href' => '/api/etax/{nbr}/xml', 'style' => 'btn-ghost-primary', 'when' => ['U', 'P'],
                 'can' => is_file(Etax::certFile()) && Auth::can('etax', 'print')],
                ['name' => 'etax', 'label' => t('etax.send'), 'event' => 'etax', 'style' => 'btn-ghost-primary', 'when' => ['U', 'P', 'C'],
                 'can' => Etax::enabled() && Auth::can('etax', 'add')],
                ['name' => 'print', 'label' => t('sales.print'), 'href' => '/invoices/{nbr}/print', 'target' => '_blank', 'style' => 'btn',
                 'when' => ['U', 'P'], 'can' => Auth::can('invoices', 'print')],
                ['name' => 'cancel', 'label' => t('purchase.cancel_doc'), 'api' => '/cancel', 'style' => 'btn-ghost-danger', 'when' => ['U'],
                 'can' => Auth::can('invoices', 'delete'), 'confirm' => t('sales.inv_cancel_confirm')],
                ['name' => 'delete', 'label' => t('sales.inv_delete'), 'api' => '/delete', 'gone' => '/invoices', 'style' => 'btn-ghost-danger', 'when' => ['C'],
                 'can' => Auth::can('invoices', 'delete'), 'confirm' => t('sales.inv_delete_confirm')],
            ],
        ];
        if ($prefill) {
            $config['prefill'] = $prefill; // doc.js fills the new invoice with it and sends prefill.body (sh) along
        }
        return View::render('doc/edit', ['help' => 'invoices', 'title' => $nbr ?? t('sales.inv_new'), 'config' => $config, 'scripts' => ['js/doc.js', 'js/pages/etax-send.js']]);
    }

    /** Tax invoice. */
    #[Get('/invoices/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        return self::document($nbr);
    }

    /** Printed tax invoice, or credit / debit note (ih_type CM / DN) with the invoice it corrects. */
    public static function document(string $nbr): string
    {
        $h = self::head($nbr);
        $note = in_array($h['ih_type'], ['CM', 'DN'], true);
        $totals = SalesPrint::totals($h['ih_net_amt'], $h['ih_tax_pct'] ?? null, $h['ih_tax_amt'], $h['ih_tot_amt'], $h['ih_curr'], (float) $h['ih_ex_rate']);
        if ($h['ih_type'] === 'CM' && $totals['words'] !== '') { // the amount of a credit note in words, without "ลบ": the title says it is one
            $totals['words'] = BahtText::of(abs((float) $h['ih_tot_amt'] * (float) $h['ih_ex_rate']));
        }
        $refDate = '';
        if ((float) ($h['ih_dp_amt'] ?? 0) != 0.0) {
            // deposits deducted (ใบรับเงินมัดจำ): the goods' full value, less the deposit, is what VAT is on
            $m = fn ($v) => number_format((float) $v, 2);
            $dp = (float) $h['ih_dp_amt'];
            array_splice($totals['rows'], 0, 1, [
                [t('sales.subtotal'), $m((float) $h['ih_net_amt'] + $dp), false],
                [t('deposit.deduct') . ' ' . implode(', ', array_column(App::db()->all('SELECT dpa_dp_nbr FROM dpa_det WHERE dpa_inv_nbr = ?', [$nbr]), 'dpa_dp_nbr')), '-' . $m($dp), false],
                [t('deposit.after'), $m($h['ih_net_amt']), false],
            ]);
        }
        if ($note && $h['ih_ref_inv']) {
            // ม.86/10: the original invoice's value, the correct value and the difference (this note)
            $ref = App::db()->one('SELECT ih_inv_date, ih_net_amt FROM ih_mstr WHERE ih_inv_nbr = ?', [$h['ih_ref_inv']]) ?? [];
            $refDate = SalesPrint::date($ref['ih_inv_date'] ?? null);
            $earlier = (float) App::db()->value(
                "SELECT COALESCE(SUM(ih_net_amt), 0) FROM ih_mstr
                  WHERE ih_ref_inv = ? AND ih_inv_nbr <> ? AND ih_status <> 'C' AND ih_type IN ('CM', 'DN') AND created_at <= ?",
                [$h['ih_ref_inv'], $nbr, $h['created_at']]
            );
            $original = (float) ($ref['ih_net_amt'] ?? 0) + $earlier;
            $diff = (float) $h['ih_net_amt'];
            $m = fn ($v) => number_format((float) $v, 2);
            array_splice($totals['rows'], 0, 1, [
                [t('sales.note_original'), $m($original), false],
                [t('sales.note_correct'), $m($original + $diff), false], // a credit note's amount is negative
                [t('sales.note_diff'), $m($diff), false],
            ]);
        }
        return SalesPrint::render([
            // a service bills first; its tax invoice is the receipt when paid (ม.78/1), so this one is not a tax invoice
            'title'  => $h['ih_type'] === 'CM' ? t('sales.credit_note') : ($h['ih_type'] === 'DN' ? t('sales.debit_note')
                : (!empty($h['ih_vat_on_rcpt']) ? t('sales.invoice_service') : t('sales.tax_invoice'))),
            'nbr'    => $nbr,
            'meta'   => [[t('sales.date'), SalesPrint::date($h['ih_inv_date'])], [t('sales.due_date'), SalesPrint::date($h['ih_due_date'])],
                         [t('sales.terms'), $h['ih_cr_terms']], $note ? [t('sales.ref_invoice'), trim($h['ih_ref_inv'] . ($refDate ? ' ' . t('sales.dated', ['date' => $refDate]) : ''))] : [t('sales.so'), $h['ih_so_nbr']],
                         [t('sales.po_ref'), $h['ih_po_ref']]],
            'party'  => SalesPrint::customer($h['ih_cm_code']),
            'lines'  => SalesPrint::priceLines(array_map(fn ($l) => ['ihd_desc' => $l['pt_desc1']] + $l, self::lines($nbr)), 'ihd', 'ihd_qty_inv'),
            'totals' => $totals,
            'notes'  => trim(($h['ih_reason'] ? t('accounting.reason') . ': ' . ArInvoiceController::reasonText($h['ih_reason']) . "\n" : '')
                . (!empty($h['ih_vat_on_rcpt']) ? t('sales.invoice_service_note') . "\n" : '')
                . (string) $h['ih_remarks']),
            'signs'  => [SalesPrint::outside(t('sales.sign_receiver')), SalesPrint::approve(t('sales.sign_authorized'))],
        ]);
    }

    #[Describe('Sales invoices (tax invoices): totals and paid amount in the invoice currency', ['q' => 'number, customer or order', 'status' => 'U unpaid, P paid, C cancelled'])]

    #[Get('/api/invoices')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['U', 'P', 'C'], true)) {
            $where[] = 'h.ih_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(h.ih_inv_nbr LIKE ? OR h.ih_cm_code LIKE ? OR c.cm_name LIKE ? OR h.ih_so_nbr LIKE ? OR h.ih_po_ref LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT h.ih_inv_nbr, h.ih_inv_date, h.ih_cm_code, c.cm_name, h.ih_so_nbr, h.ih_due_date, h.ih_curr, h.ih_net_amt, h.ih_tax_amt, h.ih_tot_amt, h.ih_paid_amt, h.ih_status',
            'FROM ih_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'ih_inv_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['ih_inv_nbr' => 'h.ih_inv_nbr', 'ih_inv_date' => 'h.ih_inv_date', 'ih_cm_code' => 'h.ih_cm_code',
             'ih_due_date' => 'h.ih_due_date', 'ih_net_amt' => 'h.ih_net_amt', 'ih_tax_amt' => 'h.ih_tax_amt',
             'ih_tot_amt' => 'h.ih_tot_amt', 'ih_paid_amt' => 'h.ih_paid_amt', 'ih_status' => 'h.ih_status'],
            'ih_inv_nbr'
        );
    }

    /** ?q= — shipments not invoiced yet. */
    #[Get('/api/invoices/shipments')]
    public function shipments(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            "SELECT h.sh_nbr, h.sh_date, h.sh_so_nbr, h.sh_cm_code, c.cm_name FROM sh_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.sh_cm_code
              WHERE h.sh_status = 'C' AND (h.sh_nbr LIKE ? OR h.sh_so_nbr LIKE ? OR h.sh_cm_code LIKE ? OR c.cm_name LIKE ?)
              ORDER BY h.sh_date, h.sh_nbr LIMIT 100",
            [$like, $like, $like, $like]
        );
    }

    #[Describe('Shipments waiting for an invoice (status C, shipped not invoiced), oldest first; today = the invoice date used')]
    #[Get('/api/invoices/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            "SELECT h.sh_nbr, h.sh_date, h.sh_so_nbr, h.sh_cm_code, c.cm_name, ? AS today
               FROM sh_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.sh_cm_code
              WHERE h.sh_status = 'C' ORDER BY h.sh_date, h.sh_nbr LIMIT 200",
            [date('Y-m-d')]
        );
    }

    #[Describe('One sales invoice with its lines')]

    #[Get('/api/invoices/{nbr}')]
    public function show(string $nbr): array
    {
        $head = self::head($nbr);
        $lines = self::lines($nbr);
        $head['ih_tax_code'] = $lines[0]['ihd_tax_code'] ?? null;
        $head['_editable'] = false;
        return ['head' => $head, 'lines' => $lines];
    }

    /** Body: {sh, date}. */
    #[Post('/api/invoices')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $sh = $c->exists('sh', $b['sh'] ?? '', 'sh_mstr', 'sh_nbr', true, "sh_status = 'C'");
        $dateField = isset($b['ih_inv_date']) ? 'ih_inv_date' : 'date';
        $date = $c->date($dateField, $b[$dateField] ?? '', true);
        // what the draft page may change; the lines in the shipment's order (draft())
        $edit = [
            'due'     => $c->date('ih_due_date', $b['ih_due_date'] ?? '', false),
            'po_ref'  => isset($b['ih_po_ref']) ? ($c->text('ih_po_ref', $b['ih_po_ref'], false, 50, false) ?? '') : null,
            'remarks' => isset($b['ih_remarks']) ? ($c->text('ih_remarks', $b['ih_remarks'], false, 1000, false) ?? '') : null,
            'lines'   => [],
        ];
        if ($date && $edit['due'] && $edit['due'] < $date) {
            $c->fail('ih_due_date', t('sales.due_before_date'));
        }
        foreach (array_values(is_array($b['lines'] ?? null) ? $b['lines'] : []) as $i => $l) {
            $key = array_keys($b['lines'])[$i];
            $edit['lines'][$i] = [
                'price' => $c->number("lines.$key.ihd_price", $l['ihd_price'] ?? '', true, 0),
                'disc'  => $c->number("lines.$key.ihd_disc_pct", $l['ihd_disc_pct'] ?? '', false, 0, 100) ?? 0.0,
            ];
        }
        $c->done();
        return ['nbr' => App::db()->transaction(fn () => self::fromShipment($sh, $date, $edit))];
    }

    /** Only unpaid invoices that are not on an open billing note. The shipment can be invoiced again. */
    #[Post('/api/invoices/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        self::cancelDocument($nbr);
        return null;
    }

    #[Post('/api/invoices/{nbr}/delete'), Action('delete')]
    public function delete(string $nbr): null
    {
        self::deleteCancelled($nbr);
        return null;
    }

    /** Cancel an unpaid invoice or credit note that is not on an open billing note; a shipment can be invoiced again. */
    public static function cancelDocument(string $nbr): void
    {
        $h = self::head($nbr);
        if ($h['ih_status'] !== 'U' || (float) $h['ih_paid_amt'] != 0) {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        $billed = App::db()->value(
            "SELECT b.bl_nbr FROM bld_det d JOIN bl_mstr b ON b.bl_nbr = d.bld_nbr WHERE d.bld_inv_nbr = ? AND b.bl_status = 'O'",
            [$nbr]
        );
        if ($billed) {
            throw new HttpException(409, t('sales.inv_on_billing', ['nbr' => $billed]));
        }
        App::db()->transaction(function (Db $db) use ($nbr, $h) {
            $db->update('ih_mstr', ['ih_status' => 'C', 'updated_by' => Auth::userId()], ['ih_inv_nbr' => $nbr]);
            \Phai\Modules\Accounting\DepositController::release($db, $nbr); // deposits it deducted can be deducted again
            $db->update('ar_mstr', ['ar_status' => 'C', 'updated_by' => Auth::userId()], ['ar_nbr' => $nbr]);
            if ($h['ih_sh_nbr']) {
                $db->update('sh_mstr', ['sh_status' => 'C', 'sh_inv_nbr' => null, 'updated_by' => Auth::userId()], ['sh_nbr' => $h['ih_sh_nbr']]);
            }
            Gl::reverse($nbr, 'AR', max(date('Y-m-d'), Gl::periodStart()), t('accounting.desc_cancel', ['nbr' => $nbr]));
            Audit::updated('ih_mstr', "ih_inv_nbr=$nbr", $h, ['ih_status' => 'C']);
        });
    }

    /**
     * Delete a cancelled invoice / credit / debit note so its number is used again — e.g. cancelled by mistake right
     * after it was made. Only while nobody can hold it: the last number of its series, never sent by e-Tax, no billing
     * note, receipt or note pointing at it, its entries in the open period. Otherwise it stays, cancelled (a tax
     * invoice once issued is kept with its number).
     */
    public static function deleteCancelled(string $nbr): void
    {
        $h = self::head($nbr);
        if ($h['ih_status'] !== 'C') {
            throw new HttpException(409, t('purchase.wrong_status'));
        }
        $db = App::db();
        if ($db->value('SELECT 1 FROM etax_log WHERE etax_doc = ? LIMIT 1', [$nbr])) {
            throw new HttpException(409, t('sales.inv_delete_sent'));
        }
        if ($db->value(
            'SELECT 1 FROM bld_det WHERE bld_inv_nbr = ? UNION SELECT 1 FROM crd_det WHERE crd_ar_nbr = ? UNION SELECT 1 FROM ih_mstr WHERE ih_ref_inv = ? LIMIT 1',
            [$nbr, $nbr, $nbr]
        )) {
            throw new HttpException(409, t('sales.inv_delete_used'));
        }
        $first = (string) $db->value("SELECT MIN(gl_date) FROM gl_trhist WHERE gl_ref = ? AND gl_type = 'AR'", [$nbr]);
        if ($first !== '' && $first < Gl::periodStart()) {
            throw new HttpException(409, t('sales.inv_delete_closed'));
        }
        $series = ['IN' => 'INV', 'CM' => 'CN', 'DN' => 'DN'][$h['ih_type'] ?? 'IN'] ?? 'INV';
        $db->transaction(function (Db $db) use ($nbr, $h, $series) {
            if (!Numbering::giveBack($series, $nbr, (string) $h['ih_inv_date'])) {
                throw new HttpException(409, t('sales.inv_delete_last'));
            }
            $db->run("DELETE FROM gl_trhist WHERE gl_ref = ? AND gl_type = 'AR'", [$nbr]); // the entry and its reversal
            $db->run('DELETE FROM dpa_det WHERE dpa_inv_nbr = ?', [$nbr]);
            $db->run('DELETE FROM ar_mstr WHERE ar_nbr = ?', [$nbr]);
            $db->run('DELETE FROM ihd_det WHERE ihd_inv_nbr = ?', [$nbr]);
            $db->run('DELETE FROM ih_mstr WHERE ih_inv_nbr = ?', [$nbr]);
            Audit::deleted('ih_mstr', "ih_inv_nbr=$nbr", $h);
        });
    }

    /** Invoice one shipment at its order's prices and open the receivable. Call inside a transaction. */
    public static function fromShipment(string $sh, string $date, array $edit = []): string
    {
        $db = App::db();
        $ship = $db->one("SELECT * FROM sh_mstr WHERE sh_nbr = ? FOR UPDATE", [$sh]);
        if (!$ship || $ship['sh_status'] !== 'C') {
            throw new HttpException(409, t('sales.sh_invoiced'));
        }
        $so = SoController::head($ship['sh_so_nbr']);
        $rows = self::shipmentLines($ship['sh_so_nbr'], $sh);
        foreach ($rows as $i => &$r) { // price and discount changed on the draft page
            if (isset($edit['lines'][$i])) {
                $r['sod_price'] = $edit['lines'][$i]['price'];
                $r['sod_disc_pct'] = $edit['lines'][$i]['disc'];
            }
        }
        unset($r);

        $nbr = Numbering::next('INV', $date);
        $user = Auth::userId();
        $amounts = [];
        $salesAcct = [];
        $n = 0;
        foreach ($rows as $r) {
            $amt = Money::lineAmount((float) $r['shd_qty_shp'], (float) $r['sod_price'], (float) $r['sod_disc_pct']);
            $amounts[] = $amt;
            $salesAcct[] = Gl::partAcct($r['shd_part'], 'sls');
            $db->insert('ihd_det', [
                'ihd_inv_nbr' => $nbr, 'ihd_line' => ++$n, 'ihd_part' => $r['shd_part'], 'ihd_qty_inv' => $r['shd_qty_shp'],
                'ihd_um' => $r['shd_um'], 'ihd_price' => $r['sod_price'], 'ihd_disc_pct' => $r['sod_disc_pct'], 'ihd_line_amt' => $amt,
                'ihd_tax_code' => $so['so_tax_code'], 'ihd_acct' => $r['pl_sls_acct'], 'ihd_so_nbr' => $ship['sh_so_nbr'],
                'ihd_sod_line' => $r['shd_sod_line'], 'created_by' => $user, 'updated_by' => $user,
            ]);
        }
        $tot = Money::totals($amounts, (float) $so['so_tax_pct']);
        // deposits taken on the order (ใบรับเงินมัดจำ) come off this tax invoice: value and VAT are on what is left
        $dp = $so['so_curr'] === Money::base() ? \Phai\Modules\Accounting\DepositController::apply($db, $ship['sh_so_nbr'], $nbr, $tot['net']) : 0.0;
        if ($dp != 0.0) {
            $tot = Money::totals([round($tot['net'] - $dp, 2)], (float) $so['so_tax_pct']);
        }
        $due = !empty($edit['due']) ? $edit['due'] : Money::dueDate($date, $so['so_cr_terms']);
        $db->insert('ih_mstr', [
            'ih_inv_nbr' => $nbr, 'ih_cm_code' => $so['so_cm_code'], 'ih_inv_date' => $date, 'ih_due_date' => $due,
            'ih_sh_nbr' => $sh, 'ih_so_nbr' => $ship['sh_so_nbr'], 'ih_po_ref' => $edit['po_ref'] ?? $so['so_po_ref'], 'ih_cr_terms' => $so['so_cr_terms'],
            'ih_remarks' => ($edit['remarks'] ?? '') !== '' ? $edit['remarks'] : null,
            'ih_curr' => $so['so_curr'], 'ih_ex_rate' => $so['so_ex_rate'],
            'ih_net_amt' => $tot['net'], 'ih_tax_amt' => $tot['tax'], 'ih_tot_amt' => $tot['total'], 'ih_paid_amt' => 0, 'ih_dp_amt' => $dp,
            'ih_status' => 'U', 'ih_type' => 'IN', 'created_by' => $user, 'updated_by' => $user,
        ]);
        $db->insert('ar_mstr', [
            'ar_nbr' => $nbr, 'ar_cm_code' => $so['so_cm_code'], 'ar_type' => 'IN', 'ar_date' => $date, 'ar_due_date' => $due,
            'ar_effdate' => $date, 'ar_amt' => round($tot['total'] * (float) $so['so_ex_rate'], 2), 'ar_paid_amt' => 0, 'ar_status' => 'O',
            'created_by' => $user, 'updated_by' => $user,
        ]);
        $db->update('sh_mstr', ['sh_status' => 'I', 'sh_inv_nbr' => $nbr, 'updated_by' => $user], ['sh_nbr' => $sh]);

        // Receivable + deposit used = sales (by product line) + output VAT, all in baht.
        $rate = (float) $so['so_ex_rate'];
        $arBase = round($tot['total'] * $rate, 2);
        $taxBase = round($tot['tax'] * $rate, 2);
        $sales = Gl::fit(array_map(fn ($a) => $a * $rate, $amounts), $arBase - $taxBase + $dp);
        $lines = [[Sales::customer($so['so_cm_code'])['cm_ar_acct'] ?: Gl::acct('ar'), $arBase], [Gl::acct('vat_out'), -$taxBase]];
        if ($dp != 0.0) {
            $lines[] = [Gl::acct('dep'), $dp]; // the deposit received is used up
        }
        foreach ($sales as $i => $amt) {
            $lines[] = [$salesAcct[$i], -$amt];
        }
        Gl::post($nbr, 'AR', $date, $lines, t('accounting.desc_invoice', ['nbr' => $nbr, 'cm' => $so['so_cm_code']]));
        return $nbr;
    }

    public static function head(string $nbr): array
    {
        $h = App::db()->one(
            'SELECT h.*, c.cm_name FROM ih_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code WHERE h.ih_inv_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
        $net = (float) $h['ih_net_amt'];
        $h['ih_tax_pct'] = $net != 0 ? round((float) $h['ih_tax_amt'] / $net * 100, 2) : 0;
        return $h;
    }

    public static function lines(string $nbr): array
    {
        return App::db()->all(
            'SELECT d.*, COALESCE(d.ihd_desc, so.sod_desc, pt.pt_desc1) AS pt_desc1 FROM ihd_det d
               LEFT JOIN sod_det so ON so.sod_nbr = d.ihd_so_nbr AND so.sod_line = d.ihd_sod_line
               LEFT JOIN pt_mstr pt ON pt.pt_part = d.ihd_part WHERE d.ihd_inv_nbr = ? ORDER BY d.ihd_line',
            [$nbr]
        );
    }

    public static function statusText(): array
    {
        return [
            'U' => ['text' => t('sales.inv_status_U'), 'color' => 'yellow'],
            'P' => ['text' => t('sales.inv_status_P'), 'color' => 'green'],
            'C' => ['text' => t('purchase.status_X'), 'color' => 'secondary'],
        ];
    }
}
