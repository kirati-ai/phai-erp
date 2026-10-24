<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;

/** Monthly output VAT (รายงานภาษีขาย); queries and printing in VatReport. */
#[Program('vat-sales')]
final class SalesVatController
{
    #[Get('/vat-sales')]
    public function page(): string
    {
        return VatReport::page('sales');
    }

    /** ?month=YYYY-MM */
    #[Describe('Output VAT report of one month (tax invoices, baht): date, number, customer, tax id, branch, net, VAT; credit memos negative', ['month' => 'YYYY-MM'])]
    #[Get('/api/vat-sales')]
    public function rows(Request $request): array
    {
        return VatReport::rows('sales', (string) $request->input('month', date('Y-m')));
    }

    /** The month is in the path so the PDF link (?pdf=1) keeps it. */
    #[Get('/vat-sales/{month}/print'), Action('print')]
    public function print(string $month): string
    {
        return VatReport::print('sales', $month);
    }
}
