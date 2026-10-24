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
 * Financial statements from the ledger: งบแสดงฐานะการเงิน (balance sheet) at a date and งบกำไรขาดทุน (income statement)
 * for a period. Accounts fall into the usual Thai sections by their code (see SECTIONS); profit of the year shows in
 * equity, so assets = liabilities + equity always.
 */
#[Program('financial-statements')]
final class FinancialStatementController
{
    /** [section key, account type, code prefixes] in printing order; the first matching row wins. */
    private const SECTIONS = [
        ['current_assets', 'A', ['10', '11', '12', '13']],
        ['noncurrent_assets', 'A', []],
        ['current_liabilities', 'L', ['20', '21', '22']],
        ['noncurrent_liabilities', 'L', []],
        ['equity', 'E', []],
        ['revenue', 'R', ['401']],
        ['other_income', 'R', []],
        ['cost_of_sales', 'X', ['501']],
        ['expenses', 'X', ['502', '503', '504', '505', '506', '507', '508', '509', '599']],
        ['finance_cost', 'X', ['510']],
        ['income_tax', 'X', ['520']],
    ];

    #[Get('/financial-statements')]
    public function page(): string
    {
        return View::render('accounting/statements', [
            'title'   => t('fs.title'),
            'help'    => 'financial-statements',
            'config'  => ['from' => date('Y') . '-01-01', 'to' => date('Y-m-d'), 'canPrint' => Auth::can('financial-statements', 'print')],
            'scripts' => ['js/pages/statements.js'],
        ]);
    }

    #[Describe('Financial statements: balance sheet at `to` and income statement from `from` to `to`, by section with each account (baht; amounts shown positive in their natural side)', ['from' => 'YYYY-MM-DD (default: 1 January)', 'to' => 'YYYY-MM-DD (default: today)'])]
    #[Get('/api/financial-statements')]
    public function statements(Request $request): array
    {
        $ok = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
        $to = $ok($request->input('to')) ? $request->input('to') : date('Y-m-d');
        $from = $ok($request->input('from')) ? $request->input('from') : substr($to, 0, 4) . '-01-01';
        if ($from > $to) {
            throw HttpException::invalid(['to' => t('accounting.bad_range')]);
        }
        return self::build($from, $to);
    }

    #[Get('/financial-statements/{to}/print'), Action('print')]
    public function print(string $to, Request $request): string
    {
        $from = (string) $request->input('from', substr($to, 0, 4) . '-01-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            throw HttpException::notFound();
        }
        return View::render('accounting/statements-print', [
            'title'    => t('fs.title'),
            'docTitle' => 'FS-' . $to,
            'fs'       => self::build($from, $to),
            'company'  => App::db()->one('SELECT co_name, co_tax_id, co_address FROM co_mstr WHERE co_id = 1') ?? [],
        ], 'blank');
    }

    public static function build(string $from, string $to): array
    {
        // balance sheet: every entry up to $to; income statement: revenue and expense within $from..$to
        $rows = App::db()->all(
            "SELECT a.ac_acct AS acct, " . Gl::acctName('a') . " AS name, a.ac_type AS type,
                    COALESCE(SUM(g.gl_amt), 0) AS bal,
                    COALESCE(SUM(IF(g.gl_date >= ?, g.gl_amt, 0)), 0) AS period
               FROM ac_mstr a JOIN gl_trhist g ON g.gl_acct = a.ac_acct AND g.gl_date <= ?
              GROUP BY a.ac_acct, a.ac_desc_th, a.ac_desc, a.ac_type ORDER BY a.ac_acct",
            [$from, $to]
        );
        $yearStart = substr($to, 0, 4) . '-01-01';
        $sections = array_fill_keys(array_column(self::SECTIONS, 0), []);
        $profitYear = 0.0;   // revenue − expense of this year up to $to (for the balance sheet)
        $profitBefore = 0.0; // of earlier years: belongs to retained earnings
        foreach ($rows as $r) {
            if (in_array($r['type'], ['R', 'X'], true)) {
                // revenue/expense: income statement over the period; their total goes to equity on the balance sheet
                $all = (float) $r['bal'];
                $thisYear = (float) App::db()->value(
                    'SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_acct = ? AND gl_date BETWEEN ? AND ?',
                    [$r['acct'], $yearStart, $to]
                );
                $profitYear -= $thisYear;
                $profitBefore -= $all - $thisYear;
                $amount = (float) $r['period'];
            } else {
                $amount = (float) $r['bal'];
            }
            if (round($amount, 2) == 0.0) {
                continue;
            }
            // shown on its natural side: assets and expenses debit-positive, the rest credit-positive
            $shown = in_array($r['type'], ['A', 'X'], true) ? $amount : -$amount;
            $sections[self::section($r['acct'], $r['type'])][] = ['acct' => $r['acct'], 'name' => $r['name'], 'amount' => round($shown, 2)];
        }
        $retained = Gl::acct('ret_earn');
        if (round($profitBefore, 2) != 0.0) {
            $sections['equity'][] = ['acct' => $retained, 'name' => t('fs.retained_before'), 'amount' => round($profitBefore, 2)];
        }
        $sections['equity'][] = ['acct' => '', 'name' => t('fs.profit_year'), 'amount' => round($profitYear, 2)];

        $sum = fn (string $k) => round(array_sum(array_column($sections[$k], 'amount')), 2);
        $totals = array_combine(array_keys($sections), array_map($sum, array_keys($sections)));
        $gross = round($totals['revenue'] - $totals['cost_of_sales'], 2);
        $beforeTax = round($gross + $totals['other_income'] - $totals['expenses'] - $totals['finance_cost'], 2);
        return [
            'from' => $from, 'to' => $to, 'sections' => $sections, 'totals' => $totals,
            'assets'      => round($totals['current_assets'] + $totals['noncurrent_assets'], 2),
            'liabilities' => round($totals['current_liabilities'] + $totals['noncurrent_liabilities'], 2),
            'gross'       => $gross,
            'before_tax'  => $beforeTax,
            'net'         => round($beforeTax - $totals['income_tax'], 2),
        ];
    }

    /** The labels of both statements (lang/<locale>/fs.php), for the page script and the print. */
    public static function text(): array
    {
        $keys = ['current_assets', 'noncurrent_assets', 'current_liabilities', 'noncurrent_liabilities', 'equity', 'revenue', 'other_income',
            'cost_of_sales', 'expenses', 'finance_cost', 'income_tax', 'total', 'assets', 'liabilities', 'liab_equity', 'gross', 'before_tax',
            'net', 'as_of', 'balance_sheet', 'income'];
        return array_combine($keys, array_map(fn ($k) => t("fs.$k"), $keys));
    }

    private static function section(string $acct, string $type): string
    {
        foreach (self::SECTIONS as [$key, $t, $prefixes]) {
            if ($t !== $type) {
                continue;
            }
            if (!$prefixes) {
                return $key;
            }
            foreach ($prefixes as $p) {
                if (str_starts_with($acct, $p)) {
                    return $key;
                }
            }
        }
        return ['A' => 'noncurrent_assets', 'L' => 'noncurrent_liabilities', 'E' => 'equity', 'R' => 'other_income'][$type] ?? 'expenses';
    }
}
