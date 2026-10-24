<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\View\View;

/**
 * Trial balance for a date range, an account's ledger, and closing the period.
 * The fiscal year is the calendar year: revenue and expense accounts start each year
 * at zero and earlier years' profit shows in retained earnings.
 */
#[Program('trial-balance')]
final class TrialBalanceController
{
    #[Get('/trial-balance')]
    public function page(): string
    {
        $s = Gl::settings();
        return View::render('accounting/trial-balance', [
            'title'   => t('accounting.tb_title'),
            'help'    => 'trial-balance',
            'config'  => [
                'from'     => Gl::periodStart(),
                'to'       => date('Y-m-d'),
                'period'   => sprintf('%04d/%02d', $s['gl_curr_year'], $s['gl_curr_per']),
                'canClose' => Auth::can('trial-balance', 'edit'),
            ],
            'queues'  => self::queues($s),
            'scripts' => ['js/pages/trial-balance.js'],
        ]);
    }

    /** The open period once its month has ended, above the page: one click closes it ($s = Gl::settings(), read when not given). */
    public static function queues(?array $s = null): array
    {
        $s ??= Gl::settings();
        return Auth::can('trial-balance', 'edit') ? [[
            'title'   => t('accounting.q_close'),
            'api'     => '/api/trial-balance/waiting',
            'key'     => 'period',
            'columns' => [
                ['key' => 'label', 'label' => t('accounting.q_close_period')],
                ['key' => 'end', 'label' => t('accounting.q_close_end'), 'type' => 'date'],
            ],
            'action'  => ['label' => t('accounting.close_period'), 'icon' => 'ti ti-lock', 'post' => '/api/trial-balance/close',
                          'confirm' => t('accounting.close_confirm', ['period' => sprintf('%04d/%02d', $s['gl_curr_year'], $s['gl_curr_per'])])],
        ]] : [];
    }

    /** ?from= &to= — [{acct, name, type, opening, debit, credit, closing}] plus totals. */
    #[Describe('Trial balance for a date range: opening, debit, credit and closing per account (debit positive, credit negative)', ['from' => 'YYYY-MM-DD (default: start of open period)', 'to' => 'YYYY-MM-DD (default: today)'])]
    #[Get('/api/trial-balance')]
    public function balance(Request $request): array
    {
        [$from, $to] = self::range($request);
        $yearStart = substr($from, 0, 4) . '-01-01';
        $rows = App::db()->all(
            "SELECT a.ac_acct AS acct, " . Gl::acctName('a') . " AS name, a.ac_type AS type,
                    COALESCE(SUM(IF(g.gl_date < ? AND (a.ac_type IN ('A', 'L', 'E') OR g.gl_date >= ?), g.gl_amt, 0)), 0) AS opening,
                    COALESCE(SUM(IF(g.gl_date >= ? AND g.gl_amt > 0, g.gl_amt, 0)), 0) AS debit,
                    COALESCE(SUM(IF(g.gl_date >= ? AND g.gl_amt < 0, -g.gl_amt, 0)), 0) AS credit
               FROM ac_mstr a JOIN gl_trhist g ON g.gl_acct = a.ac_acct AND g.gl_date <= ?
              GROUP BY a.ac_acct, a.ac_desc_th, a.ac_desc, a.ac_type
              ORDER BY a.ac_acct",
            [$from, $yearStart, $from, $from, $to]
        );
        $carried = self::priorProfit($yearStart);
        $retained = Gl::acct('ret_earn');
        if ($carried != 0.0 && !in_array($retained, array_column($rows, 'acct'), true)) {
            $rows[] = ['acct' => $retained, 'name' => App::db()->value('SELECT ' . Gl::acctName() . ' FROM ac_mstr WHERE ac_acct = ?', [$retained]),
                       'type' => 'E', 'opening' => 0, 'debit' => 0, 'credit' => 0];
            usort($rows, fn ($a, $b) => strcmp($a['acct'], $b['acct']));
        }
        $total = ['opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0];
        $out = [];
        foreach ($rows as $r) {
            $open = round((float) $r['opening'] + ($r['acct'] === $retained ? $carried : 0), 2);
            $r = ['opening' => $open, 'debit' => round((float) $r['debit'], 2), 'credit' => round((float) $r['credit'], 2)] + $r;
            $r['closing'] = round($open + $r['debit'] - $r['credit'], 2);
            if ($r['opening'] == 0.0 && $r['debit'] == 0.0 && $r['credit'] == 0.0) {
                continue; // revenue/expense of earlier years only
            }
            foreach ($total as $k => $_) {
                $total[$k] = round($total[$k] + $r[$k], 2);
            }
            $out[] = $r;
        }
        return ['rows' => $out, 'total' => $total, 'from' => $from, 'to' => $to];
    }

    /** ?acct= &from= &to= — opening balance and every line in the range with a running balance. */
    #[Describe('Ledger lines of one account with running balance', ['acct' => 'account code', 'from' => 'YYYY-MM-DD', 'to' => 'YYYY-MM-DD'])]
    #[Get('/api/trial-balance/ledger')]
    public function ledger(Request $request): array
    {
        [$from, $to] = self::range($request);
        $acct = (string) $request->input('acct', '');
        $type = App::db()->value('SELECT ac_type FROM ac_mstr WHERE ac_acct = ?', [$acct]) ?? throw HttpException::notFound();
        $yearStart = substr($from, 0, 4) . '-01-01';
        $open = (float) App::db()->value(
            'SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_acct = ? AND gl_date < ?' . (in_array($type, ['R', 'X'], true) ? ' AND gl_date >= ?' : ''),
            in_array($type, ['R', 'X'], true) ? [$acct, $from, $yearStart] : [$acct, $from]
        );
        if ($acct === Gl::acct('ret_earn')) {
            $open += self::priorProfit($yearStart);
        }
        $lines = App::db()->all(
            "SELECT gl_date, gl_type, gl_ref, gl_desc, gl_cc, gl_amt, CONCAT(gl_type, '~', gl_ref, '~', gl_date) AS entry
               FROM gl_trhist WHERE gl_acct = ? AND gl_date BETWEEN ? AND ? ORDER BY gl_date, gl_trnbr LIMIT 2000",
            [$acct, $from, $to]
        );
        $bal = round($open, 2);
        foreach ($lines as &$l) {
            $bal = round($bal + (float) $l['gl_amt'], 2);
            $l['balance'] = $bal;
        }
        return ['opening' => round($open, 2), 'lines' => $lines];
    }

    /** The open period when its month has ended (one row), else nothing. */
    #[Describe('The open accounting period when its month has already ended and it can be closed (empty when not yet)')]
    #[Get('/api/trial-balance/waiting')]
    public function waiting(): array
    {
        $s = Gl::settings();
        $end = date('Y-m-t', strtotime(Gl::periodStart()));
        if ($end >= date('Y-m-d')) {
            return [];
        }
        $period = sprintf('%04d/%02d', $s['gl_curr_year'], $s['gl_curr_per']);
        return [['period' => $period, 'label' => t('accounting.close_period') . ' ' . $period, 'end' => $end]];
    }

    /** Close the current period: nothing can be posted before the next one any more. */
    #[Post('/api/trial-balance/close'), Action('edit')]
    public function close(): array
    {
        return App::db()->transaction(function (Db $db) {
            $s = $db->one('SELECT gl_curr_year, gl_curr_per FROM gl_ctrl WHERE ctrl_id = 1 FOR UPDATE');
            $end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $s['gl_curr_year'], $s['gl_curr_per'])));
            if ($end >= date('Y-m-d')) {
                throw new HttpException(409, t('accounting.period_not_over', ['date' => $end]));
            }
            // a month's depreciation belongs to that month: once it is closed it can never be posted
            $month = substr($end, 0, 7);
            foreach ($db->all("SELECT * FROM fa_mstr WHERE fa_status = 'ACTIVE' AND fa_method <> 'NONE'") as $a) {
                if (AssetDepreciation::next($a) <= $month && AssetDepreciation::amount($a, AssetDepreciation::next($a)) > 0) {
                    throw new HttpException(409, t('accounting.close_depr_first', ['month' => AssetDepreciation::next($a), 'code' => $a['fa_code']]));
                }
            }
            // every petty cash box with cash or entries in the month is counted first: the cash in hand is then known
            if ($left = PettyCash::uncounted($month)) {
                throw new HttpException(409, t('pettycash.count_first', ['who' => $left[0]['name'], 'month' => $left[0]['month_text']]));
            }
            $year = (int) $s['gl_curr_year'] + ((int) $s['gl_curr_per'] === 12 ? 1 : 0);
            $per = (int) $s['gl_curr_per'] % 12 + 1;
            $db->update('gl_ctrl', ['gl_curr_year' => $year, 'gl_curr_per' => $per], ['ctrl_id' => 1]);
            Audit::updated('gl_ctrl', 'ctrl_id=1', $s, ['gl_curr_year' => $year, 'gl_curr_per' => $per]);
            return ['period' => sprintf('%04d/%02d', $year, $per), 'from' => sprintf('%04d-%02d-01', $year, $per)];
        });
    }

    /** Profit of all years before $yearStart, as a ledger amount (credit negative). */
    private static function priorProfit(string $yearStart): float
    {
        return (float) App::db()->value(
            "SELECT COALESCE(SUM(g.gl_amt), 0) FROM gl_trhist g JOIN ac_mstr a ON a.ac_acct = g.gl_acct
              WHERE a.ac_type IN ('R', 'X') AND g.gl_date < ?",
            [$yearStart]
        );
    }

    private static function range(Request $request): array
    {
        $ok = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
        $from = $request->input('from');
        $to = $request->input('to');
        $from = $ok($from) ? $from : Gl::periodStart();
        $to = $ok($to) ? $to : date('Y-m-d');
        if ($to < $from) {
            throw HttpException::invalid(['to' => t('accounting.bad_range')]);
        }
        return [$from, $to];
    }
}
