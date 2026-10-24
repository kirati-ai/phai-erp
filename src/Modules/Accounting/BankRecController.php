<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\View\View;
use Phai\Modules\System\Attachment;

/**
 * Bank reconciliation (กระทบยอดธนาคาร): tick the GL lines of the bank's account that are on the
 * statement and save; opening + ticked is the statement balance (the user checks it against the real one). One reconciliation per statement
 * date (bkr_mstr); the ticked lines (bkrd_det) are cleared and never offered again. Only the latest
 * reconciliation of a bank can be undone. Amounts are the baht posted to the bank's GL account.
 */
#[Program('bank-rec')]
final class BankRecController
{
    #[Get('/bank-rec')]
    public function page(): string
    {
        return View::render('accounting/bank-rec', [
            'title'   => t('accounting.bank_rec_title'),
            'help'    => 'bank-rec',
            'banks'   => App::db()->all('SELECT bk_bank, bk_desc, bk_acct FROM bk_mstr WHERE bk_active = 1 AND bk_petty = 0 ORDER BY bk_bank'),
            'queues'  => self::queues(),
            'scripts' => ['js/pages/bank-rec.js'],
        ]);
    }

    /** Bank accounts with lines not reconciled yet above the page: one click opens the reconciliation of that bank. */
    public static function queues(): array
    {
        return Auth::can('bank-rec', 'add') ? [[
            'title'   => t('accounting.q_bank'),
            'api'     => '/api/bank-rec/waiting',
            'key'     => 'bk_bank',
            'columns' => [
                ['key' => 'bk_bank', 'label' => t('accounting.bank')],
                ['key' => 'bk_desc', 'label' => t('accounting.q_bank_name')],
                ['key' => 'lines', 'label' => t('accounting.q_bank_lines'), 'type' => 'num'],
                ['key' => 'last_date', 'label' => t('accounting.q_bank_last'), 'type' => 'date'],
            ],
            'action'  => ['label' => t('accounting.bank_rec_title'), 'icon' => 'ti ti-checks', 'href' => '/bank-rec?bank={bk_bank}'],
        ]] : [];
    }

    /** ?bank= &date= */
    #[Describe('Lines of a bank account not yet reconciled up to a statement date, the last reconciliation and the book balance (baht; amount + = deposit, - = withdrawal)', ['bank' => 'bank code', 'date' => 'statement date YYYY-MM-DD'])]
    #[Get('/api/bank-rec/open')]
    public function open(Request $request): array
    {
        $bank = self::bank((string) $request->input('bank', ''));
        $date = self::date((string) $request->input('date', date('Y-m-d')));
        $last = self::last($bank['bk_bank']);
        return [
            'bank'    => $bank['bk_bank'],
            'date'    => $date,
            'last'    => $last,
            'opening' => $last ? (float) $last['bkr_balance'] : 0.0,
            'book'    => self::book($bank['bk_acct'], $date),
            'lines'   => self::uncleared($bank['bk_acct'], $date),
        ];
    }

    #[Describe('Active banks with ledger lines up to today not yet reconciled: number of lines and the last statement date, oldest statement first')]
    #[Get('/api/bank-rec/waiting')]
    public function waiting(): array
    {
        $rows = App::db()->all(
            'SELECT b.bk_bank, b.bk_desc, COUNT(*) AS n,
                    (SELECT MAX(r.bkr_date) FROM bkr_mstr r WHERE r.bkr_bank = b.bk_bank) AS last_date
               FROM bk_mstr b JOIN gl_trhist g ON g.gl_acct = b.bk_acct AND g.gl_date <= CURDATE()
               LEFT JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr
              WHERE b.bk_active = 1 AND b.bk_petty = 0 AND d.bkrd_trnbr IS NULL
              GROUP BY b.bk_bank, b.bk_desc
              ORDER BY last_date IS NOT NULL, last_date, b.bk_bank LIMIT 200'
        );
        foreach ($rows as &$r) {
            $r['lines'] = (int) $r['n'];
            unset($r['n']);
        }
        return $rows;
    }

    /** History of one bank, newest first. */
    #[Describe('Saved bank reconciliations of one bank, newest first: statement date, statement balance, book balance, lines cleared', ['bank' => 'bank code'])]
    #[Get('/api/bank-rec')]
    public function list(Request $request): array
    {
        $bank = self::bank((string) $request->input('bank', ''));
        return App::db()->all(
            'SELECT r.bkr_date, r.bkr_balance, r.bkr_book, r.bkr_remarks, r.created_by, COUNT(d.bkrd_trnbr) AS lines_cleared
               FROM bkr_mstr r LEFT JOIN bkrd_det d ON d.bkrd_bank = r.bkr_bank AND d.bkrd_date = r.bkr_date
              WHERE r.bkr_bank = ? GROUP BY r.bkr_bank, r.bkr_date ORDER BY r.bkr_date DESC LIMIT 60',
            [$bank['bk_bank']]
        );
    }

    /** {bank, date, remarks, lines: [gl_trnbr, ...]} — the statement balance is the last one + the ticked lines. */
    #[Post('/api/bank-rec')]
    public function save(Request $request): array
    {
        $b = $request->body;
        $bank = self::bank((string) ($b['bank'] ?? ''));
        $date = self::date((string) ($b['date'] ?? ''));
        $ids = array_values(array_unique(array_map('intval', is_array($b['lines'] ?? null) ? $b['lines'] : [])));
        if (!$ids) {
            throw HttpException::invalid(['lines' => t('accounting.bank_rec_none')]);
        }

        return App::db()->transaction(function ($db) use ($bank, $date, $ids, $b) {
            $db->one('SELECT bk_bank FROM bk_mstr WHERE bk_bank = ? FOR UPDATE', [$bank['bk_bank']]);
            $last = self::last($bank['bk_bank']);
            if ($last && $date <= $last['bkr_date']) {
                throw HttpException::invalid(['date' => t('accounting.bank_rec_after', ['date' => self::dmy($last['bkr_date'])])]);
            }
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $found = $db->all(
                "SELECT g.gl_trnbr, g.gl_amt FROM gl_trhist g LEFT JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr
                  WHERE g.gl_trnbr IN ($marks) AND g.gl_acct = ? AND g.gl_date <= ? AND d.bkrd_trnbr IS NULL",
                [...$ids, $bank['bk_acct'], $date]
            );
            if (count($found) !== count($ids)) {
                throw new HttpException(409, t('accounting.bank_rec_stale'));
            }
            $sum = array_sum(array_map(fn ($r) => (float) $r['gl_amt'], $found));
            $opening = $last ? (float) $last['bkr_balance'] : 0.0;
            $balance = round($opening + $sum, 2);

            $user = Auth::userId();
            $row = [
                'bkr_bank' => $bank['bk_bank'], 'bkr_date' => $date, 'bkr_balance' => $balance,
                'bkr_book' => self::book($bank['bk_acct'], $date),
                'bkr_remarks' => mb_substr(trim((string) ($b['remarks'] ?? '')), 0, 255) ?: null,
                'created_by' => $user, 'updated_by' => $user,
            ];
            $db->insert('bkr_mstr', $row);
            foreach ($ids as $id) {
                $db->insert('bkrd_det', ['bkrd_trnbr' => $id, 'bkrd_bank' => $bank['bk_bank'], 'bkrd_date' => $date, 'created_by' => $user, 'updated_by' => $user]);
            }
            Audit::created('bkr_mstr', Audit::key(['bkr_bank' => $bank['bk_bank'], 'bkr_date' => $date]), $row + ['lines' => count($ids)]);
            return ['bank' => $bank['bk_bank'], 'date' => $date, 'lines' => count($ids)];
        });
    }

    /** Undo the latest reconciliation of a bank: its lines are open again. */
    #[Delete('/api/bank-rec/{bank}/{date}')]
    public function undo(string $bank, string $date): null
    {
        $bank = self::bank($bank);
        App::db()->transaction(function ($db) use ($bank, $date) {
            $db->one('SELECT bk_bank FROM bk_mstr WHERE bk_bank = ? FOR UPDATE', [$bank['bk_bank']]);
            $last = self::last($bank['bk_bank']);
            if (!$last || $last['bkr_date'] !== $date) {
                throw new HttpException(409, t('accounting.bank_rec_latest_only'));
            }
            $key = ['bkr_bank' => $bank['bk_bank'], 'bkr_date' => $date];
            $row = $db->one('SELECT * FROM bkr_mstr WHERE bkr_bank = ? AND bkr_date = ?', array_values($key));
            $db->delete('bkrd_det', ['bkrd_bank' => $bank['bk_bank'], 'bkrd_date' => $date]);
            $db->delete('bkr_mstr', $key);
            Audit::deleted('bkr_mstr', Audit::key($key), $row);
            Attachment::removeAll('BR', $bank['bk_bank'] . '~' . $date);
        });
        return null;
    }

    /**
     * Reconciliation statement: balance per bank, plus deposits in transit, less outstanding payments
     * (lines up to the date not cleared by then) = balance per book.
     */
    #[Get('/bank-rec/{bank}/{date}/print'), Action('print')]
    public function print(string $bank, string $date): string
    {
        $bank = self::bank($bank);
        $rec = App::db()->one('SELECT * FROM bkr_mstr WHERE bkr_bank = ? AND bkr_date = ?', [$bank['bk_bank'], $date])
            ?? throw HttpException::notFound();
        $open = App::db()->all(
            'SELECT g.gl_date, g.gl_type, g.gl_ref, g.gl_desc, g.gl_amt FROM gl_trhist g
               LEFT JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr AND d.bkrd_date <= ?
              WHERE g.gl_acct = ? AND g.gl_date <= ? AND d.bkrd_trnbr IS NULL
              ORDER BY g.gl_date, g.gl_trnbr',
            [$date, $bank['bk_acct'], $date]
        );
        return View::render('accounting/bank-rec-print', [
            'title'    => t('accounting.bank_rec_title') . ' ' . $bank['bk_bank'] . ' ' . self::dmy($date),
            'docTitle' => "BANKREC-{$bank['bk_bank']}-$date",
            'bank'     => $bank,
            'rec'      => $rec,
            'in'       => array_values(array_filter($open, fn ($l) => (float) $l['gl_amt'] > 0)),
            'out'      => array_values(array_filter($open, fn ($l) => (float) $l['gl_amt'] < 0)),
        ], 'blank');
    }

    private static function bank(string $code): array
    {
        if ($code === '') {
            throw HttpException::invalid(['bank' => t('validation.required')]);
        }
        return App::db()->one('SELECT bk_bank, bk_desc, bk_acct, bk_curr FROM bk_mstr WHERE bk_bank = ?', [$code])
            ?? throw HttpException::invalid(['bank' => t('validation.in')]);
    }

    private static function date(string $v): string
    {
        $d = \DateTime::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) {
            throw HttpException::invalid(['date' => t('validation.date')]);
        }
        return $v;
    }

    private static function last(string $bank): ?array
    {
        return App::db()->one('SELECT bkr_date, bkr_balance FROM bkr_mstr WHERE bkr_bank = ? ORDER BY bkr_date DESC LIMIT 1', [$bank]);
    }

    private static function book(string $acct, string $date): float
    {
        return round((float) App::db()->value('SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_acct = ? AND gl_date <= ?', [$acct, $date]), 2);
    }

    private static function uncleared(string $acct, string $date): array
    {
        $rows = App::db()->all(
            'SELECT g.gl_trnbr, g.gl_date, g.gl_type, g.gl_ref, g.gl_desc, g.gl_amt FROM gl_trhist g
               LEFT JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr
              WHERE g.gl_acct = ? AND g.gl_date <= ? AND d.bkrd_trnbr IS NULL
              ORDER BY g.gl_date, g.gl_trnbr',
            [$acct, $date]
        );
        foreach ($rows as &$r) {
            $r['gl_trnbr'] = (int) $r['gl_trnbr'];
            $r['gl_amt'] = round((float) $r['gl_amt'], 2);
        }
        return $rows;
    }

    private static function dmy(string $date): string
    {
        return implode('/', array_reverse(explode('-', $date)));
    }
}
