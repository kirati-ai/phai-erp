<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\View\View;

/** e-Tax Invoice by Email: send an invoice / credit note / cancellation, and the log of what was sent (see Etax). */
#[Program('etax')]
final class EtaxController
{
    #[Get('/etax')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('etax.title'),
            'help'    => 'etax',
            'config'  => [
                'api'     => '/api/etax',
                'page'    => '/invoices',
                'key'     => 'etax_doc',
                'canAdd'  => false,
                'status'  => self::statusText(),
                'columns' => [
                    ['key' => 'etax_doc', 'label' => t('etax.doc'), 'sort' => true, 'strong' => true],
                    ['key' => 'created_at', 'label' => t('etax.sent_at'), 'sort' => true],
                    ['key' => 'etax_kind', 'label' => t('etax.kind')],
                    ['key' => 'etax_to', 'label' => t('etax.to')],
                    ['key' => 'etax_subject', 'label' => t('etax.subject')],
                    ['key' => 'etax_msg', 'label' => t('etax.msg')],
                    ['key' => 'created_by', 'label' => t('etax.by')],
                    ['key' => 'etax_status', 'label' => t('common.status'), 'type' => 'status'],
                ],
            ],
            'queues'  => self::queues(),
            'toolbar' => 'accounting/etax-toolbar',
            'scripts' => ['js/doclist.js', 'js/pages/etax-list.js'],
        ]);
    }

    /** Tax invoices and notes not sent by e-Tax email yet (only when e-Tax is on): one click opens the document to send. */
    public static function queues(): array
    {
        return Etax::enabled() && Auth::can('etax', 'add') ? [[
            'title'   => t('etax.q_title'),
            'api'     => '/api/etax/waiting',
            'key'     => 'ih_inv_nbr',
            'columns' => [
                ['key' => 'ih_inv_nbr', 'label' => t('etax.doc'), 'link' => '/{area}/{ih_inv_nbr}'],
                ['key' => 'kind', 'label' => t('etax.kind')],
                ['key' => 'ih_inv_date', 'label' => t('accounting.date'), 'type' => 'date'],
                ['key' => 'ih_cm_code', 'label' => t('sales.customer')],
                ['key' => 'cm_name', 'label' => t('sales.customer_name')],
                ['key' => 'cm_email', 'label' => t('etax.to')],
                ['key' => 'ih_tot_amt', 'label' => t('sales.total'), 'type' => 'money'],
            ],
            'action'  => ['label' => t('etax.send'), 'icon' => 'ti ti-mail-forward', 'href' => '/{area}/{ih_inv_nbr}'],
        ]] : [];
    }

    /** The signed XML (ETDA ขมธอ.3-2560) of one invoice or credit note, for the Revenue Department. */
    #[Get('/api/etax/{nbr}/xml'), Action('print')]
    public function xml(string $nbr): Response
    {
        return new Response(EtaxXml::signed($nbr), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nbr) . '.xml"',
        ]);
    }

    /** The signed XML of a receipt (ใบรับ, T01) for cash received. */
    #[Get('/api/etax/receipts/{nbr}/xml'), Action('print')]
    public function receiptXml(string $nbr): Response
    {
        return new Response(EtaxXml::signedReceipt($nbr), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nbr) . '.xml"',
        ]);
    }

    /**
     * Every invoice, credit note, debit note and receipt of a month (not cancelled) as signed XML in one zip, for "Upload XML" on
     * etax.rd.go.th (due by the 15th of the next month). Documents that cannot be made are listed in errors.txt.
     */
    #[Get('/etax/xml/{month}'), Action('print')]
    public function month(string $month): Response
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $month)) {
            throw HttpException::invalid(['month' => t('validation.date')]);
        }
        $from = "$month-01";
        $to = date('Y-m-t', strtotime($from));
        $docs = array_merge(
            App::db()->all(
                "SELECT ih_inv_nbr AS nbr, 0 AS receipt FROM ih_mstr WHERE ih_status <> 'C' AND ih_type IN ('IN', 'CM', 'DN') AND ih_inv_date BETWEEN ? AND ? ORDER BY ih_inv_nbr",
                [$from, $to]
            ),
            App::db()->all("SELECT cr_nbr AS nbr, 1 AS receipt FROM cr_mstr WHERE cr_status = 'P' AND cr_date BETWEEN ? AND ? ORDER BY cr_nbr", [$from, $to])
        );
        if (!$docs) {
            throw new HttpException(404, t('etax.xml_none'));
        }
        $tmp = tempnam(sys_get_temp_dir(), 'etax');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $errors = [];
        foreach ($docs as $d) {
            try {
                $xml = $d['receipt'] ? EtaxXml::signedReceipt($d['nbr']) : EtaxXml::signed($d['nbr']);
                $zip->addFromString(preg_replace('/[^A-Za-z0-9._-]/', '_', $d['nbr']) . '.xml', $xml);
            } catch (HttpException $e) {
                $errors[] = $d['nbr'] . ': ' . $e->getMessage();
                if ($e->status === 409 && str_contains($e->getMessage(), t('etax.cert_missing'))) {
                    throw $e;
                }
            }
        }
        if ($errors) {
            $zip->addFromString('errors.txt', implode("\r\n", $errors) . "\r\n");
        }
        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);
        return new Response($data, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="etax-' . $month . '.zip"',
        ]);
    }

    /** ?q= &status= */
    #[Describe('e-Tax Invoice by Email log: each email sent to a buyer with the ETDA time stamp copy (kind INV invoice, CDN credit note, CIV cancellation)', ['q' => 'document, email or subject', 'status' => 'S sent, F failed'])]
    #[Get('/api/etax')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (in_array($status = (string) $request->input('status', ''), ['S', 'F'], true)) {
            $where[] = 'etax_status = ?';
            $params[] = $status;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(etax_doc LIKE ? OR etax_to LIKE ? OR etax_subject LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            'SELECT etax_id, etax_doc, created_at, etax_kind, etax_to, etax_subject, etax_msg, created_by, etax_status',
            'FROM etax_log WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'created_at', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['etax_doc' => 'etax_doc', 'created_at' => 'etax_id'],
            'created_at'
        );
    }

    /**
     * Invoices, credit and debit notes not cancelled with no successful send yet, oldest first. Counted from the
     * month e-Tax was first used (or this month), so older documents from before e-Tax do not fill the list.
     */
    #[Describe('Invoices, credit notes and debit notes (not cancelled) not yet sent by e-Tax email, oldest first; kind INV / CDN / DBN')]
    #[Get('/api/etax/waiting')]
    public function waiting(): array
    {
        $from = (string) (App::db()->value("SELECT DATE_FORMAT(MIN(created_at), '%Y-%m-01') FROM etax_log") ?: date('Y-m-01'));
        $rows = App::db()->all(
            "SELECT h.ih_inv_nbr, h.ih_type, h.ih_inv_date, h.ih_cm_code, c.cm_name, c.cm_email, h.ih_tot_amt,
                    IF(h.ih_sh_nbr IS NULL, 'ar-invoices', 'invoices') AS area
               FROM ih_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code
              WHERE h.ih_status <> 'C' AND h.ih_inv_date >= ?
                AND NOT EXISTS (SELECT 1 FROM etax_log l WHERE l.etax_doc = h.ih_inv_nbr AND l.etax_status = 'S')
              ORDER BY h.ih_inv_date, h.ih_inv_nbr LIMIT 200",
            [$from]
        );
        foreach ($rows as &$r) {
            $r['kind'] = ['CM' => 'CDN', 'DN' => 'DBN'][$r['ih_type']] ?? 'INV';
        }
        return $rows;
    }

    #[Get('/api/etax/{nbr}')]
    public function preview(string $nbr): array
    {
        return Etax::preview($nbr);
    }

    /** {to} */
    #[Post('/api/etax/{nbr}/send'), Action('add')]
    public function send(string $nbr, Request $request): array
    {
        return Etax::send($nbr, (string) ($request->body['to'] ?? ''));
    }

    public static function statusText(): array
    {
        return [
            'S' => ['text' => t('etax.status_S'), 'color' => 'green'],
            'F' => ['text' => t('etax.status_F'), 'color' => 'red'],
        ];
    }
}
