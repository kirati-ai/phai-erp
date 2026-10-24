<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;

/** Monthly input VAT (รายงานภาษีซื้อ); queries and printing in VatReport. */
#[Program('vat-purchases')]
final class PurchaseVatController
{
    #[Get('/vat-purchases')]
    public function page(): string
    {
        return VatReport::page('purchases');
    }

    /** ?month=YYYY-MM */
    #[Describe('Input VAT report of one month (vendor invoices set up as payables, baht): date, vendor invoice number, vendor, tax id, branch, net, VAT', ['month' => 'YYYY-MM'])]
    #[Get('/api/vat-purchases')]
    public function rows(Request $request): array
    {
        return VatReport::rows('purchases', (string) $request->input('month', date('Y-m')));
    }

    /** The month is in the path so the PDF link (?pdf=1) keeps it. */
    #[Get('/vat-purchases/{month}/print'), Action('print')]
    public function print(string $month): string
    {
        return VatReport::print('purchases', $month);
    }
}
