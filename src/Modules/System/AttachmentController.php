<?php

declare(strict_types=1);

namespace Phai\Modules\System;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Http\Response;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Modules\Accounting\PettyCash;

/**
 * Files attached to documents (att_det, Attachment) through one set of routes for every kind of document in DOCS —
 * on a page: View::partial('partials/files', ['doc' => 'AP', 'nbr' => $ref]) (Phai.files). The rights are those of the
 * document's own screen: view to see, add or edit to attach, edit or delete to remove; the document must exist.
 * Petty cash: only the entry's holder attaches or removes, while its month is not counted (PettyCash::fileable).
 * A table row opens the same files in a dialog from its paperclip (Phai.files.button).
 */
final class AttachmentController
{
    /** doc code => [program, table, key column]; JE / BR / WR are checked by their own number (see exists()). */
    public const DOCS = [
        'PR' => ['purchase-requisitions', 'pr_mstr', 'pr_nbr'],
        'PO' => ['purchase-orders', 'po_mstr', 'po_nbr'],
        'RC' => ['receipts', 'rc_mstr', 'rc_nbr'],
        'LC' => ['landed-costs', 'rc_mstr', 'rc_nbr'],
        'RT' => ['purchase-returns', 'rt_mstr', 'rt_nbr'],
        'SO' => ['sales-orders', 'so_mstr', 'so_nbr'],
        'SH' => ['shipments', 'sh_mstr', 'sh_nbr'],
        'SR' => ['sales-returns', 'sr_mstr', 'sr_nbr'],
        'AP' => ['payables', 'ap_mstr', 'ap_ref'],
        'PV' => ['vouchers', 'vo_mstr', 'vo_nbr'],
        'CR' => ['cash-receipts', 'cr_mstr', 'cr_nbr'],
        'JE' => ['journals', null, null],     // type~ref~date of a ledger entry
        'BR' => ['bank-rec', null, null],     // bank~statement date of a reconciliation
        'VR' => ['vat-return', 'vr_mstr', 'vr_month'],
        'WR' => ['wht-report', null, null],   // form~month: PND3~2026-10 / PND53~2026-10
        'VD' => ['vendors', 'vd_mstr', 'vd_code'],
        'CM' => ['customers', 'cm_mstr', 'cm_code'],
        'PT' => ['parts', 'pt_mstr', 'pt_part'],
        'QC' => ['qc', 'qc_hist', 'qc_test_nbr'],
        'PI' => ['stock-counts', 'pi_mstr', 'pi_batch'],
        'FA' => ['fixed-assets', 'fa_mstr', 'fa_code'],
        'PC' => ['petty-cash', 'cb_mstr', 'cb_nbr'],
    ];

    /** ?nbrs=a,b,c → {nbr: number of files} (the paperclips of a list). */
    #[Get('/api/files/{doc}')]
    public function counts(string $doc, Request $request): array
    {
        $doc = self::program($doc, 'view');
        $nbrs = array_slice(array_values(array_filter(array_map('trim', explode(',', (string) $request->input('nbrs', ''))), 'strlen')), 0, 500);
        if (!$nbrs) {
            return [];
        }
        $rows = App::db()->all(
            'SELECT att_nbr, COUNT(*) AS n FROM att_det WHERE att_doc = ? AND att_nbr IN (' . implode(',', array_fill(0, count($nbrs), '?')) . ') GROUP BY att_nbr',
            [$doc, ...$nbrs]
        );
        return array_map('intval', array_column($rows, 'n', 'att_nbr'));
    }

    /** {files: [{att_id, att_name, att_mime, att_size, created_by, created_at}], can: {add, delete}, hint: what to attach} */
    #[Get('/api/files/{doc}/{nbr}')]
    public function list(string $doc, string $nbr): array
    {
        $doc = self::document($doc, $nbr, 'view');
        $prog = self::DOCS[$doc][0];
        if ($doc === 'PC') {
            try {
                $pc = Auth::can($prog, 'add') && PettyCash::fileable($nbr);
            } catch (HttpException) {
                $pc = false;
            }
            $can = ['add' => $pc, 'delete' => $pc];
        } else {
            $can = ['add' => Auth::can($prog, 'add') || Auth::can($prog, 'edit'), 'delete' => Auth::can($prog, 'edit') || Auth::can($prog, 'delete')];
        }
        return ['files' => Attachment::list($doc, $nbr), 'can' => $can, 'hint' => t('files.hint_' . $doc)];
    }

    /** Body {name, data: data URL}: a picture or PDF. */
    #[Post('/api/files/{doc}/{nbr}')]
    public function store(string $doc, string $nbr, Request $request): array
    {
        $doc = self::writable($doc, $nbr, 'add', 'edit');
        return Attachment::store($doc, $nbr, (string) ($request->body['name'] ?? ''), (string) ($request->body['data'] ?? ''));
    }

    #[Delete('/api/files/{doc}/{nbr}/{id}')]
    public function remove(string $doc, string $nbr, string $id): null
    {
        $doc = self::writable($doc, $nbr, 'edit', 'delete');
        Attachment::remove(Attachment::find($doc, $nbr, (int) $id));
        return null;
    }

    #[Get('/files/{doc}/{nbr}/{id}')]
    public function file(string $doc, string $nbr, string $id): Response
    {
        $doc = self::document($doc, $nbr, 'view');
        $row = Attachment::find($doc, $nbr, (int) $id);
        return new Response(Attachment::bytes($row), 200, [
            'Content-Type' => $row['att_mime'], 'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline; filename="' . rawurlencode((string) $row['att_name']) . '"', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The doc code, when the user has one of the rights on its screen. */
    private static function program(string $doc, string ...$actions): string
    {
        $doc = strtoupper($doc);
        $prog = self::DOCS[$doc][0] ?? throw new HttpException(403, t('auth.forbidden')); // no screen gives a right to it
        foreach ($actions as $action) {
            if (Auth::can($prog, $action)) {
                return $doc;
            }
        }
        throw new HttpException(403, t('auth.forbidden'));
    }

    /** As program(), and the document exists. */
    private static function document(string $doc, string $nbr, string ...$actions): string
    {
        $doc = self::program($doc, ...$actions);
        if (!self::exists($doc, $nbr)) {
            throw HttpException::notFound();
        }
        return $doc;
    }

    /** As document(), for attaching or removing: petty cash by its holder while the month is open. */
    private static function writable(string $doc, string $nbr, string ...$actions): string
    {
        if (strtoupper($doc) !== 'PC') {
            return self::document($doc, $nbr, ...$actions);
        }
        $doc = self::document($doc, $nbr, 'add');
        PettyCash::fileable($nbr);
        return $doc;
    }

    private static function exists(string $doc, string $nbr): bool
    {
        $db = App::db();
        [, $table, $key] = self::DOCS[$doc];
        $parts = explode('~', $nbr);
        return match ($doc) {
            'JE' => count($parts) === 3 && (bool) $db->value('SELECT 1 FROM gl_trhist WHERE gl_type = ? AND gl_ref = ? AND gl_date = ? LIMIT 1', $parts),
            'BR' => count($parts) === 2 && (bool) $db->value('SELECT 1 FROM bkr_mstr WHERE bkr_bank = ? AND bkr_date = ?', $parts),
            'WR' => (bool) preg_match('/^PND(3|53)~\d{4}-(0[1-9]|1[0-2])$/', $nbr),
            default => (bool) $db->value("SELECT 1 FROM $table WHERE $key = ?", [$nbr]),
        };
    }
}
