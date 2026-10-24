<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Lang;
use Phai\Core\Mail;
use Phai\Core\Pdf;
use Phai\Core\PdfA;
use Phai\Modules\Sales\InvoiceController;

/**
 * e-Tax Invoice by Email (ETDA time stamp, for VAT registrants with revenue up to 30 million baht a year):
 * one email per document, To the buyer (one address), Cc csemail@etax.teda.th, sent from the address
 * registered with the Revenue Department, the printed document as a PDF/A-3 of at most 3 MB, and a subject
 *
 *     INV  tax invoice      [date][INV][number]
 *     CDN  credit note      [date][CDN][number][invoice it reduces]
 *     DBN  debit note       [date][DBN][number][invoice it adds to]
 *     CIV  cancelled invoice [date][CIV][number]
 *
 * Every attempt is kept in etax_log. Settings are in config.php: etax.* and mail.* (SMTP).
 */
final class Etax
{
    public const ETDA = 'csemail@etax.teda.th';
    private const MAX_BYTES = 3 * 1024 * 1024;

    /** storage/etax/<database>: kept apart per installation, so a test database never touches the real files. */
    public static function dir(): string
    {
        return PHAI_ROOT . '/storage/etax/' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) App::config('db.name', 'phaierp'));
    }

    /** The signing certificate (PKCS#12), outside public/; its password is etax.p12_pass in config.php. */
    public static function certFile(): string
    {
        return self::dir() . '/cert.p12';
    }

    public static function enabled(): bool
    {
        return (bool) App::config('etax.enabled', false);
    }

    /** What would be sent for an invoice: kind, subject, default recipient, last attempt. */
    public static function preview(string $nbr): array
    {
        $h = self::head($nbr);
        $kind = self::kind($h);
        return [
            'nbr'     => $nbr,
            'kind'    => $kind,
            'subject' => self::subject($kind, $h),
            'to'      => (string) ($h['cm_email'] ?? ''),
            'cc'      => self::cc(),
            'enabled' => self::enabled(),
            'last'    => App::db()->one(
                'SELECT etax_kind, etax_to, etax_status, etax_msg, created_at, created_by FROM etax_log WHERE etax_doc = ? ORDER BY etax_id DESC LIMIT 1',
                [$nbr]
            ),
        ];
    }

    /** Send one invoice / credit note / cancellation to $to; the attempt is logged either way. */
    public static function send(string $nbr, string $to): array
    {
        if (!self::enabled()) {
            throw new HttpException(409, t('etax.disabled'));
        }
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw HttpException::invalid(['to' => t('validation.email')]);
        }
        $h = self::head($nbr);
        $kind = self::kind($h);
        $subject = self::subject($kind, $h);
        $log = ['etax_kind' => $kind, 'etax_doc' => $nbr, 'etax_ref' => $h['ih_ref_inv'] ?: null, 'etax_to' => $to, 'etax_subject' => $subject];
        try {
            if (!Pdf::available()) {
                throw new \RuntimeException(t('etax.no_pdf'));
            }
            // the ETDA time stamp service takes PDF/A-3 only
            $locale = Lang::locale();
            Lang::setLocale('th'); // the tax invoice itself is Thai whatever the screen's language
            try {
                $html = InvoiceController::document($nbr);
            } finally {
                Lang::setLocale($locale);
            }
            $pdf = PdfA::convert(Pdf::fromHtml($html), $nbr);
            if (strlen($pdf) > self::MAX_BYTES) {
                throw new \RuntimeException(t('etax.too_big'));
            }
            $id = Mail::send([
                'to'          => $to,
                'cc'          => self::cc(),
                'subject'     => $subject,
                'body'        => t("etax.body_$kind", [
                    'nbr' => $nbr, 'date' => self::dmy($h['ih_inv_date']), 'ref' => (string) $h['ih_ref_inv'],
                    'amt' => number_format((float) $h['ih_tot_amt'], 2) . ' ' . $h['ih_curr'],
                    'company' => (string) App::db()->value('SELECT co_name FROM co_mstr WHERE co_id = 1'),
                ]),
                'attachments' => [['name' => preg_replace('/[^A-Za-z0-9._-]/', '_', $nbr) . '.pdf', 'data' => $pdf, 'type' => 'application/pdf']],
            ]);
            self::log($log + ['etax_status' => 'S', 'etax_msgid' => $id]);
        } catch (HttpException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            self::log($log + ['etax_status' => 'F', 'etax_msg' => mb_substr($e->getMessage(), 0, 255)]);
            throw new HttpException(422, t('etax.failed', ['msg' => $e->getMessage()]));
        }
        return ['kind' => $kind, 'subject' => $subject, 'to' => $to];
    }

    /** [date][INV][nbr] — no spaces, the document date as dd/mm/yyyy in the year system set (พ.ศ. by default). */
    public static function subject(string $kind, array $h): string
    {
        $parts = [self::dmy($h['ih_inv_date'], App::config('etax.year', 'BE') === 'BE'), $kind, $h['ih_inv_nbr']];
        if ($kind === 'CDN' || $kind === 'DBN') {
            $parts[] = (string) $h['ih_ref_inv'];
        }
        return implode('', array_map(fn ($p) => '[' . str_replace(' ', '', $p) . ']', $parts));
    }

    public static function cc(): array
    {
        $cc = trim((string) App::config('etax.cc', self::ETDA));
        return $cc === '' ? [] : [$cc];
    }

    private static function kind(array $h): string
    {
        if ($h['ih_status'] === 'C') {
            if ($h['ih_type'] !== 'IN') {
                throw new HttpException(409, t('etax.cm_cancelled'));
            }
            return 'CIV';
        }
        return ['CM' => 'CDN', 'DN' => 'DBN'][$h['ih_type']] ?? 'INV';
    }

    private static function head(string $nbr): array
    {
        return App::db()->one(
            'SELECT h.ih_inv_nbr, h.ih_inv_date, h.ih_type, h.ih_status, h.ih_ref_inv, h.ih_tot_amt, h.ih_curr, c.cm_email
               FROM ih_mstr h LEFT JOIN cm_mstr c ON c.cm_code = h.ih_cm_code WHERE h.ih_inv_nbr = ?',
            [$nbr]
        ) ?? throw HttpException::notFound();
    }

    public static function log(array $row): void
    {
        $user = Auth::userId();
        App::db()->insert('etax_log', $row + ['created_by' => $user, 'updated_by' => $user]);
    }

    private static function dmy(string $date, bool $buddhist = false): string
    {
        [$y, $m, $d] = explode('-', substr($date, 0, 10));
        return "$d/$m/" . ($buddhist ? (int) $y + 543 : $y);
    }
}
