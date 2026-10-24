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
use Phai\Core\Routing\Get;
use Phai\Core\View\View;

/**
 * Bank book (สมุดบัญชีเงินฝาก): one bank account month by month, from the GL of its account — brought forward,
 * every deposit and withdrawal with a running balance and whether it is reconciled, and the month-end balance.
 * The list and the set-up form of the accounts are BankResource.
 */
#[Program('banks')]
final class BankController
{
    #[Get('/banks/{bank}')]
    public function page(string $bank, Request $request): string
    {
        $b = self::bank($bank);
        return View::render('accounting/bank-book', [
            'title'   => t('accounting.bank_book') . ' ' . $b['bk_bank'],
            'help'    => 'bank-book',
            'config'  => [
                'bank'     => $b['bk_bank'],
                'month'    => self::month((string) $request->input('month', date('Y-m'))),
                'banks'    => App::db()->all('SELECT bk_bank, bk_desc FROM bk_mstr WHERE bk_petty = 0 ORDER BY bk_bank'),
                'canPrint' => Auth::can('banks', 'print'),
            ],
            'scripts' => ['js/pages/bank-book.js'],
        ]);
    }

    /** ?month=YYYY-MM */
    #[Describe('Bank book of one bank account for a month: opening balance, each deposit (+) / withdrawal (-) with running balance and its reconciliation date, closing balance (baht, from the GL account of the bank)', ['month' => 'YYYY-MM'])]
    #[Get('/api/banks/{bank}/book')]
    public function book(string $bank, Request $request): array
    {
        return self::data(self::bank($bank), self::month((string) $request->input('month', date('Y-m'))));
    }

    #[Get('/banks/{bank}/book/{month}/print'), Action('print')]
    public function print(string $bank, string $month): string
    {
        $b = self::bank($bank);
        return View::render('accounting/bank-book-print', [
            'title'    => t('accounting.bank_book') . ' ' . $b['bk_bank'],
            'docTitle' => "BANKBOOK-{$b['bk_bank']}-$month",
            'bank'     => $b,
            'd'        => self::data($b, self::month($month)),
        ], 'blank');
    }

    private static function data(array $b, string $month): array
    {
        $db = App::db();
        $from = "$month-01";
        $to = date('Y-m-t', strtotime($from));
        $opening = round((float) $db->value('SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_acct = ? AND gl_date < ?', [$b['bk_acct'], $from]), 2);
        $lines = $db->all(
            "SELECT g.gl_trnbr, g.gl_date, g.gl_type, g.gl_ref, g.gl_desc, g.gl_amt, d.bkrd_date AS rec_date,
                    CONCAT(g.gl_type, '~', g.gl_ref, '~', g.gl_date) AS entry
               FROM gl_trhist g LEFT JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr
              WHERE g.gl_acct = ? AND g.gl_date BETWEEN ? AND ? ORDER BY g.gl_date, g.gl_trnbr",
            [$b['bk_acct'], $from, $to]
        );
        $bal = $opening;
        $in = $out = 0.0;
        foreach ($lines as &$l) {
            $amt = (float) $l['gl_amt'];
            $amt > 0 ? $in += $amt : $out -= $amt;
            $bal = round($bal + $amt, 2);
            $l['balance'] = $bal;
        }
        return [
            'month' => $month, 'month_text' => PettyCash::monthText($month), 'from' => $from, 'to' => $to,
            'opening' => $opening, 'in' => round($in, 2), 'out' => round($out, 2), 'closing' => $bal, 'lines' => $lines,
        ];
    }

    private static function bank(string $code): array
    {
        return App::db()->one('SELECT bk_bank, bk_desc, bk_acct, bk_curr, bk_petty FROM bk_mstr WHERE bk_bank = ?', [$code])
            ?? throw HttpException::notFound();
    }

    private static function month(string $v): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v) ? $v : date('Y-m');
    }
}
