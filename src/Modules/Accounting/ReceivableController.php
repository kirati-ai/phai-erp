<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\View\View;

/** Receivables by customer, aged by days past due (today). */
#[Program('receivables')]
final class ReceivableController
{
    #[Get('/receivables')]
    public function page(): string
    {
        return View::render('accounting/receivables', [
            'title'   => t('accounting.ar_title'),
            'help'    => 'receivables',
            'config'  => ['canReceive' => Auth::can('cash-receipts', 'add')],
            'scripts' => ['js/pages/receivables.js'],
        ]);
    }

    /** ?q= — one row per customer with an open balance: current, 1-30, 31-60, 61-90, over 90 days. */
    #[Describe('Customer balances aged by days past due today (baht): b0 not due, b30 1-30, b60 31-60, b90 61-90, b91 over 90 days, total', ['q' => 'customer code or name'])]
    #[Get('/api/receivables')]
    public function list(Request $request): array
    {
        $params = [date('Y-m-d')];
        $where = '';
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where = 'AND (a.ar_cm_code LIKE ? OR c.cm_name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        return App::db()->all(
            "SELECT t.ar_cm_code, t.cm_name,
                    SUM(IF(t.days <= 0, t.bal, 0)) AS b0,
                    SUM(IF(t.days BETWEEN 1 AND 30, t.bal, 0)) AS b30,
                    SUM(IF(t.days BETWEEN 31 AND 60, t.bal, 0)) AS b60,
                    SUM(IF(t.days BETWEEN 61 AND 90, t.bal, 0)) AS b90,
                    SUM(IF(t.days > 90, t.bal, 0)) AS b91,
                    SUM(t.bal) AS total
               FROM (SELECT a.ar_cm_code, c.cm_name, a.ar_amt - a.ar_paid_amt AS bal, DATEDIFF(?, a.ar_due_date) AS days
                       FROM ar_mstr a LEFT JOIN cm_mstr c ON c.cm_code = a.ar_cm_code
                      WHERE a.ar_status = 'O' AND a.ar_amt <> a.ar_paid_amt $where) t
              GROUP BY t.ar_cm_code, t.cm_name
              ORDER BY total DESC",
            $params
        );
    }

    /** Open items of one customer. */
    #[Describe('Open invoices of one customer with days late')]
    #[Get('/api/receivables/{customer}')]
    public function items(string $customer): array
    {
        return App::db()->all(
            "SELECT ar_nbr, ar_date, ar_due_date, ar_amt, ar_paid_amt, ar_amt - ar_paid_amt AS balance, DATEDIFF(?, ar_due_date) AS days
               FROM ar_mstr WHERE ar_cm_code = ? AND ar_status = 'O' AND ar_amt <> ar_paid_amt ORDER BY ar_due_date, ar_nbr",
            [date('Y-m-d'), $customer]
        );
    }
}
