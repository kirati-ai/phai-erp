<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
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
use Phai\Core\Validation\Check;
use Phai\Core\View\View;
use Phai\Modules\Master\Money;
use Phai\Modules\Sales\InvoiceController;
use Phai\Modules\Sales\Sales;
use Phai\Modules\Sales\SalesLines;

/**
 * Invoices and credit notes made by hand (not from a shipment): ih_mstr with no ih_sh_nbr.
 * Type IN opens a receivable; type CM (ใบลดหนี้) is a negative receivable that a cash receipt
 * nets against invoices. Both post to the ledger (AR) and show on the output VAT report.
 */
#[Program('ar-invoices')]
final class ArInvoiceController
{
    /** IN invoice, CM credit note (ใบลดหนี้), DN debit note (ใบเพิ่มหนี้) — the notes name the invoice they correct. */
    private const TYPES = ['IN', 'CM', 'DN'];
    private const SERIES = ['IN' => 'INV', 'CM' => 'CN', 'DN' => 'DN'];
    /** Reason codes of the Revenue Department (RD 1225) for a credit / debit note; a ...99 code needs the reason in the remarks. */
    public const REASONS = [
        'CM' => ['CDNG01', 'CDNG02', 'CDNG03', 'CDNG04', 'CDNG05', 'CDNG99', 'CDNS01', 'CDNS02', 'CDNS03', 'CDNS04', 'CDNS99'],
        'DN' => ['DBNG01', 'DBNG02', 'DBNG99', 'DBNS01', 'DBNS02', 'DBNS99'],
    ];

    /** "CDNG02 · สินค้าชำรุดเสียหาย" */
    public static function reasonText(?string $code): string
    {
        return $code ? $code . ' · ' . t('accounting.reason_' . $code) : '';
    }

    #[Get('/ar-invoices')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('accounting.arinv_title'),
            'help'    => 'ar-invoices',
            'config'  => [
                'api'     => '/api/ar-invoices',
                'page'    => '/ar-invoices',
                'canAdd'  => Auth::can('ar-invoices', 'add'),
                'status'  => InvoiceController::statusText(),
                'columns' => [
                    ['key' => 'ih_inv_nbr', 'label' => t('sales.nbr'), 'sort' => true, 'strong' => true],
                    ['key' => 'type_text', 'label' => t('accounting.arinv_type')],
                    ['key' => 'ih_inv_date', 'label' => t('sales.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'ih_cm_code', 'label' => t('sales.customer'), 'sort' => true],
                    ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                    ['key' => 'ih_ref_inv', 'label' => t('sales.ref_invoice')],
                    ['key' => 'ih_tot_amt', 'label' => t('sales.total'), 'type' => 'money', 'sort' => true, 'suffix' => 'ih_curr'],
                    ['key' => 'ih_status', 'label' => t('common.status'), 'type' => 'status', 'sort' => true],
                ],
            ],
            'queues'  => self::queues(),
            'scripts' => ['js/doclist.js'],
        ]);
    }

    /** Sales returns waiting for a credit note above the list: one click opens a new credit note filled from the return. */
    public static function queues(): array
    {
        return Auth::can('ar-invoices', 'add') && Auth::can('sales-returns') ? [[
            'title'   => t('sales.sr_waiting'),
            'api'     => '/api/sales-returns/waiting',
            'key'     => 'sr_nbr',
            'columns' => [
                ['key' => 'sr_nbr', 'label' => t('sales.sr_title'), 'link' => '/sales-returns/{sr_nbr}'],
                ['key' => 'sr_date', 'label' => t('sales.date'), 'type' => 'date'],
                ['key' => 'sr_inv_nbr', 'label' => t('sales.invoice')],
                ['key' => 'sr_cm_code', 'label' => t('sales.customer')],
                ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                ['key' => 'sr_reason', 'label' => t('sales.sr_reason')],
            ],
            'action'  => ['label' => t('sales.sr_make_cn'), 'icon' => 'ti ti-file-minus', 'href' => '/ar-invoices/new?return={sr_nbr}'],
        ]] : [];
    }

    /** ?return=SR… opens a credit note filled from that sales return (customer, invoice, reason, lines at the invoice price). */
    #[Get('/ar-invoices/new'), Action('add')]
    public function newPage(Request $request): string
    {
        return $this->editor(null, self::fromReturn((string) $request->input('return', '')));
    }

    private static function fromReturn(string $sr): ?array
    {
        if ($sr === '') {
            return null;
        }
        $h = App::db()->one("SELECT * FROM sr_mstr WHERE sr_nbr = ? AND sr_status = 'O'", [$sr]);
        if (!$h) {
            return null;
        }
        $inv = $h['sr_inv_nbr'] ? App::db()->one('SELECT ih_curr, ih_ex_rate, ih_cr_terms FROM ih_mstr WHERE ih_inv_nbr = ?', [$h['sr_inv_nbr']]) : null;
        $lines = [];
        foreach (\Phai\Modules\Sales\SalesReturnController::lines($sr) as $l) {
            $p = $h['sr_inv_nbr'] ? App::db()->one(
                'SELECT ihd_desc, ihd_price, ihd_disc_pct FROM ihd_det WHERE ihd_inv_nbr = ? AND ihd_part = ? ORDER BY ihd_line LIMIT 1',
                [$h['sr_inv_nbr'], $l['srd_part']]
            ) : null;
            $lines[] = ['ihd_part' => $l['srd_part'], 'ihd_desc' => $p['ihd_desc'] ?? $l['pt_desc1'], 'ihd_um' => $l['srd_um'],
                        'ihd_qty_inv' => -(float) $l['srd_qty'], 'ihd_price' => $p['ihd_price'] ?? 0, 'ihd_disc_pct' => $p['ihd_disc_pct'] ?? 0];
        }
        return [
            'head' => ['ih_type' => 'CM', 'ih_cm_code' => $h['sr_cm_code'], 'ih_ref_inv' => $h['sr_inv_nbr'], 'ih_reason' => 'CDNG05',
                       'ih_remarks' => t('sales.sr_title') . ' ' . $sr . ': ' . $h['sr_reason'],
                       'ih_curr' => $inv['ih_curr'] ?? null, 'ih_ex_rate' => $inv['ih_ex_rate'] ?? null, 'ih_cr_terms' => $inv['ih_cr_terms'] ?? null],
            'lines' => $lines,
            'body' => ['return' => $sr],
        ];
    }

    #[Get('/ar-invoices/{nbr}')]
    public function viewPage(string $nbr): string
    {
        self::own($nbr);
        return $this->editor($nbr);
    }

    #[Get('/ar-invoices/{nbr}/print'), Action('print')]
    public function print(string $nbr): string
    {
        self::own($nbr);
        return InvoiceController::document($nbr);
    }

    /** ?q= &status= */
    #[Describe('Invoices and credit notes made by hand in receivables (type IN invoice, CM credit note)', ['q' => 'number, customer or referenced invoice', 'status' => 'U unpaid, P paid, C cancelled'])]
    #[Get('/api/ar-invoices')]
    public function list(Request $request): array
    {
        $where = ['h.ih_sh_nbr IS NULL'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['U', 'P', 'C'], true)) {
            $where[] = 'h.ih_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(h.ih_inv_nbr LIKE ? OR h.ih_cm_code LIKE ? OR c.cm_name LIKE ? OR h.ih_ref_inv LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $page = App::db()->page(
            'SELECT h.ih_inv_nbr, h.ih_type, h.ih_inv_date, h.ih_cm_code, c.cm_name, h.ih_ref_inv, h.ih_curr, h.ih_tot_amt, h.ih_status',
            'FROM ih_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'ih_inv_nbr', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['ih_inv_nbr' => 'h.ih_inv_nbr', 'ih_inv_date' => 'h.ih_inv_date', 'ih_cm_code' => 'h.ih_cm_code',
             'ih_tot_amt' => 'h.ih_tot_amt', 'ih_status' => 'h.ih_status'],
            'ih_inv_nbr'
        );
        foreach ($page['rows'] as &$r) {
            $r['type_text'] = t('accounting.arinv_type_' . $r['ih_type']);
        }
        return $page;
    }

    #[Get('/api/ar-invoices/{nbr}')]
    public function show(string $nbr): array
    {
        $head = self::own($nbr);
        $lines = InvoiceController::lines($nbr);
        foreach ($lines as &$l) {
            $l['ihd_desc'] ??= $l['pt_desc1'];
        }
        $head['ih_tax_code'] = $lines[0]['ihd_tax_code'] ?? null;
        $head['_editable'] = false;
        return ['head' => $head, 'lines' => $lines];
    }

    #[Post('/api/ar-invoices')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $type = in_array($b['ih_type'] ?? '', self::TYPES, true) ? $b['ih_type'] : null;
        if ($type === null) {
            $c->fail('ih_type', t('validation.in'));
        }
        $h = [
            'ih_cm_code'  => $c->exists('ih_cm_code', $b['ih_cm_code'] ?? '', 'cm_mstr', 'cm_code', true, "cm_status = 'ACTIVE'"),
            'ih_inv_date' => $c->date('ih_inv_date', $b['ih_inv_date'] ?? '', true),
            'ih_due_date' => $c->date('ih_due_date', $b['ih_due_date'] ?? ''),
            'ih_cr_terms' => $c->exists('ih_cr_terms', $b['ih_cr_terms'] ?? '', 'ct_mstr', 'ct_code', false),
            'ih_curr'     => $c->exists('ih_curr', $b['ih_curr'] ?? '', 'cu_mstr', 'cu_curr', true),
            'ih_ex_rate'  => $c->number('ih_ex_rate', $b['ih_ex_rate'] ?? '', true, 0, true),
            'ih_po_ref'   => $c->text('ih_po_ref', $b['ih_po_ref'] ?? '', false, 50, false),
            'ih_remarks'  => $c->text('ih_remarks', $b['ih_remarks'] ?? '', false, 2000, false),
        ];
        $taxCode = $c->exists('ih_tax_code', $b['ih_tax_code'] ?? '', 'tx_mstr', 'tx_tax_code', false);
        // a credit / debit note states why (Revenue Code s.86/9, 86/10); "other" needs the reason written out
        $h['ih_reason'] = null;
        if ($type === 'CM' || $type === 'DN') {
            $reason = (string) ($b['ih_reason'] ?? '');
            if ($reason === '') {
                $c->fail('ih_reason', t('validation.required'));
            } elseif (!in_array($reason, self::REASONS[$type], true)) {
                $c->fail('ih_reason', t('validation.in'));
            } else {
                $h['ih_reason'] = $reason;
                if (str_ends_with($reason, '99') && ($h['ih_remarks'] ?? null) === null) {
                    $c->fail('ih_remarks', t('accounting.reason_other_needs_text'));
                }
            }
        }
        // a credit or debit note names the invoice it corrects (same customer, not cancelled)
        $h['ih_ref_inv'] = $c->exists('ih_ref_inv', $b['ih_ref_inv'] ?? '', 'ih_mstr', 'ih_inv_nbr', $type === 'CM' || $type === 'DN',
            "ih_type = 'IN' AND ih_status <> 'C' AND ih_cm_code = " . App::db()->pdo->quote((string) ($b['ih_cm_code'] ?? '')));
        if ($h['ih_inv_date'] && $h['ih_inv_date'] < Gl::periodStart()) {
            $c->fail('ih_inv_date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $lines = SalesLines::check($c, $b['lines'] ?? null, 'ihd', 'ihd_qty_inv', false, $type === 'CM'); // a credit note: quantities keyed negative
        // a service: output VAT falls due when the customer pays (ม.78/1) — the receipt is the tax invoice
        $h['ih_vat_on_rcpt'] = $type === 'IN' && (string) ($b['ih_vat_on_rcpt'] ?? '0') === '1' ? 1 : 0;
        // made from a sales return (ใบรับคืนสินค้า): the credit note closes it
        $return = $type === 'CM' ? trim((string) ($b['return'] ?? '')) : '';
        $c->done();

        $nbr = App::db()->transaction(function (Db $db) use ($type, $h, $taxCode, $lines, $return) {
            $nbr = Numbering::next(self::SERIES[$type], $h['ih_inv_date']);
            $user = Auth::userId();
            $rows = SalesLines::rows($lines, 'ihd', 'ihd_qty_inv');
            $tot = Money::totals(array_column($rows, 'ihd_line_amt'), Money::taxPct($taxCode));
            $due = $h['ih_due_date'] ?: Money::dueDate($h['ih_inv_date'], $h['ih_cr_terms']);
            $rate = (float) $h['ih_ex_rate'];
            $accts = [];
            foreach ($rows as $r) {
                $accts[] = Gl::partAcct($r['ihd_part'], 'sls');
                $db->insert('ihd_det', $r + [
                    'ihd_inv_nbr' => $nbr, 'ihd_tax_code' => $taxCode, 'ihd_acct' => end($accts), 'created_by' => $user, 'updated_by' => $user,
                ]);
            }
            $db->insert('ih_mstr', $h + [
                'ih_inv_nbr' => $nbr, 'ih_type' => $type, 'ih_due_date' => $due, 'ih_net_amt' => $tot['net'], 'ih_tax_amt' => $tot['tax'],
                'ih_tot_amt' => $tot['total'], 'ih_paid_amt' => 0, 'ih_status' => 'U', 'created_by' => $user, 'updated_by' => $user,
            ]);
            $arBase = round($tot['total'] * $rate, 2);
            $db->insert('ar_mstr', [
                'ar_nbr' => $nbr, 'ar_cm_code' => $h['ih_cm_code'], 'ar_type' => $type, 'ar_date' => $h['ih_inv_date'], 'ar_due_date' => $due,
                'ar_effdate' => $h['ih_inv_date'], 'ar_amt' => $arBase, 'ar_paid_amt' => 0, 'ar_status' => 'O',
                'created_by' => $user, 'updated_by' => $user,
            ]);

            // Invoice: Dr receivable / Cr sales (by product line) / Cr output VAT. A credit note's amounts are negative: the opposite.
            $taxBase = round($tot['tax'] * $rate, 2);
            $sales = Gl::fit(array_map(fn ($a) => $a * $rate, array_column($rows, 'ihd_line_amt')), $arBase - $taxBase);
            $vatAcct = $h['ih_vat_on_rcpt'] ? Gl::acct('vat_defer') : Gl::acct('vat_out'); // a service: not due until paid
            $post = [[Sales::customer($h['ih_cm_code'])['cm_ar_acct'] ?: Gl::acct('ar'), $arBase], [$vatAcct, -$taxBase]];
            foreach ($sales as $i => $amt) {
                $post[] = [$accts[$i], -$amt];
            }
            Gl::post($nbr, 'AR', $h['ih_inv_date'], $post, t('accounting.desc_invoice', ['nbr' => $nbr, 'cm' => $h['ih_cm_code']]));
            if ($return !== '') {
                $sr = $db->one('SELECT sr_status, sr_cm_code FROM sr_mstr WHERE sr_nbr = ? FOR UPDATE', [$return]);
                if (!$sr || $sr['sr_status'] !== 'O' || $sr['sr_cm_code'] !== $h['ih_cm_code']) {
                    throw HttpException::invalid(['ih_cm_code' => t('sales.sr_not_open', ['nbr' => $return])]);
                }
                $db->update('sr_mstr', ['sr_cn_nbr' => $nbr, 'sr_status' => 'C', 'updated_by' => $user], ['sr_nbr' => $return]);
            }
            return $nbr;
        });
        return ['nbr' => $nbr];
    }

    #[Post('/api/ar-invoices/{nbr}/delete'), Action('delete')]
    public function delete(string $nbr): null
    {
        InvoiceController::deleteCancelled($nbr);
        return null;
    }

    #[Post('/api/ar-invoices/{nbr}/cancel'), Action('delete')]
    public function cancel(string $nbr): null
    {
        self::own($nbr);
        App::db()->transaction(function (Db $db) use ($nbr) {
            InvoiceController::cancelDocument($nbr);
            // a cancelled credit note puts its sales return back to waiting for one
            $db->run("UPDATE sr_mstr SET sr_cn_nbr = NULL, sr_status = 'O', updated_by = ? WHERE sr_cn_nbr = ?", [Auth::userId(), $nbr]);
        });
        return null;
    }

    /** Header of a hand-made invoice or credit note (one from a shipment belongs to the sales invoice screen). */
    private static function own(string $nbr): array
    {
        $h = InvoiceController::head($nbr);
        if ($h['ih_sh_nbr'] !== null) {
            throw HttpException::notFound();
        }
        return $h;
    }

    private function editor(?string $nbr, ?array $prefill = null): string
    {
        $config = SalesLines::editorConfig('ih', 'ihd', 'ihd_qty_inv', [
            'api'    => '/api/ar-invoices',
            'numbering' => 'INV,CN,DN', // the number field, may be typed (Numbering::next)
            'page'   => '/ar-invoices',
            'nbr'    => $nbr,
            'statusField' => 'ih_status',
            'status' => InvoiceController::statusText(),
            'canEdit' => $nbr === null && Auth::can('ar-invoices', 'add'),
            'head'   => [
                ['name' => 'ih_type', 'label' => t('accounting.arinv_type'), 'type' => 'select', 'col' => 2, 'required' => true, 'default' => 'IN',
                 'options' => array_map(fn ($t) => ['value' => $t, 'label' => t('accounting.arinv_type_' . $t)], self::TYPES)],
                ['name' => 'ih_cm_code', 'label' => t('sales.customer'), 'type' => 'customer', 'col' => 4, 'required' => true, 'nameField' => 'cm_name'],
                ['name' => 'ih_inv_date', 'label' => t('sales.date'), 'type' => 'date', 'col' => 2, 'required' => true, 'default' => date('Y-m-d')],
                ['name' => 'ih_due_date', 'label' => t('sales.due_date'), 'type' => 'date', 'col' => 2],
                ['name' => 'ih_ref_inv', 'label' => t('sales.ref_invoice'), 'type' => 'text', 'col' => 2],
                ['name' => 'ih_reason', 'label' => t('accounting.reason'), 'type' => 'select', 'col' => 4,
                 'options' => array_merge([['value' => '', 'label' => '']], array_map(
                     fn ($code) => ['value' => $code, 'label' => (str_starts_with($code, 'CDN') ? t('accounting.arinv_type_CM') : t('accounting.arinv_type_DN')) . ': ' . self::reasonText($code)],
                     [...self::REASONS['CM'], ...self::REASONS['DN']]
                 ))],
                ['name' => 'ih_vat_on_rcpt', 'label' => t('accounting.vat_point'), 'type' => 'select', 'col' => 3, 'default' => '0',
                 'options' => [['value' => '0', 'label' => t('accounting.vat_point_0')], ['value' => '1', 'label' => t('accounting.vat_point_1')]]],
                ['name' => 'ih_cr_terms', 'label' => t('sales.terms'), 'type' => 'select', 'col' => 3, 'options' => Lookup::options('credit-terms')],
                ['name' => 'ih_curr', 'label' => t('sales.currency'), 'type' => 'select', 'col' => 2, 'required' => true, 'options' => Lookup::options('currencies'), 'default' => Money::base()],
                ['name' => 'ih_ex_rate', 'label' => t('sales.rate'), 'type' => 'number', 'col' => 2, 'required' => true, 'default' => '1'],
                ['name' => 'ih_tax_code', 'label' => t('sales.tax'), 'type' => 'select', 'col' => 2, 'options' => Money::taxOptions()],
                ['name' => 'ih_po_ref', 'label' => t('sales.po_ref'), 'type' => 'text', 'col' => 3],
                ['name' => 'ih_remarks', 'label' => t('sales.remarks'), 'type' => 'text', 'col' => 12],
            ],
            'partyDefaults' => ['ih_cr_terms' => 'cm_terms', 'ih_curr' => 'cm_curr', 'ih_tax_code' => 'cm_tax_code'],
            'rate'   => ['curr' => 'ih_curr', 'date' => 'ih_inv_date', 'out' => 'ih_ex_rate'],
            'tax'    => 'ih_tax_code',
            'priceParams' => ['customer' => 'ih_cm_code', 'date' => 'ih_inv_date'],
            'actions' => [
                ['name' => 'etax-xml', 'label' => t('etax.xml'), 'href' => '/api/etax/{nbr}/xml', 'style' => 'btn-ghost-primary', 'when' => ['U', 'P'],
                 'can' => is_file(Etax::certFile()) && Auth::can('etax', 'print')],
                ['name' => 'etax', 'label' => t('etax.send'), 'event' => 'etax', 'style' => 'btn-ghost-primary', 'when' => ['U', 'P', 'C'],
                 'can' => Etax::enabled() && Auth::can('etax', 'add')],
                ['name' => 'print', 'label' => t('sales.print'), 'href' => '/ar-invoices/{nbr}/print', 'target' => '_blank', 'style' => 'btn',
                 'when' => ['U', 'P'], 'can' => Auth::can('ar-invoices', 'print')],
                ['name' => 'cancel', 'label' => t('purchase.cancel_doc'), 'api' => '/cancel', 'style' => 'btn-ghost-danger', 'when' => ['U'],
                 'can' => Auth::can('ar-invoices', 'delete'), 'confirm' => t('sales.inv_cancel_confirm')],
                ['name' => 'delete', 'label' => t('sales.inv_delete'), 'api' => '/delete', 'gone' => '/ar-invoices', 'style' => 'btn-ghost-danger', 'when' => ['C'],
                 'can' => Auth::can('ar-invoices', 'delete'), 'confirm' => t('sales.inv_delete_confirm')],
            ],
        ]);
        $config['nbrField'] = 'ih_inv_nbr';
        if ($prefill) {
            $config['prefill'] = $prefill; // doc.js fills a new document with it and sends prefill.body along
        }
        return View::render('doc/edit', ['help' => 'ar-invoices', 'title' => $nbr ?? t('accounting.arinv_new'), 'config' => $config, 'scripts' => ['js/doc.js', 'js/pages/etax-send.js']]);
    }
}
