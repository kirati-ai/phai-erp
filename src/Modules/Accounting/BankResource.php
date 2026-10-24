<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Crud\Resource;

/**
 * บัญชีธนาคาร: the list shows what each account holds today (GL of its account), its last reconciliation
 * and the lines still waiting for one; a row opens its bank book (BankController), petty cash opens /petty-cash.
 */
final class BankResource extends Resource
{
    public const CODE = 'banks';
    protected static string $table = 'bk_mstr';
    protected static array $key = ['bk_bank'];
    protected static array $search = ['bk_bank', 'bk_desc'];
    protected static array $list = ['bk_bank', 'bk_desc', 'bk_balance', 'bk_rec_date', 'bk_waiting', 'bk_active'];
    protected static array $extra = ['bk_balance' => 'number', 'bk_rec_date' => 'date', 'bk_waiting' => 'int'];
    protected static array $form = [
        'bk_bank'   => ['col' => 4, 'upper' => true],
        'bk_desc'   => ['col' => 8],
        'bk_acct'   => ['col' => 8, 'lookup' => 'accounts'],
        'bk_curr'   => ['col' => 4, 'lookup' => 'currencies'],
        'bk_petty'  => ['col' => 6],
        'bk_active' => ['col' => 6],
    ];
    protected static array $usedBy = [
        'cr_mstr' => 'cr_bank',
        'vo_mstr' => 'vo_bank',
    ];

    public static function list(array $opt = []): array
    {
        $page = parent::list($opt);
        $db = App::db();
        $today = date('Y-m-d');
        foreach ($page['rows'] as &$r) {
            $r['bk_balance'] = round((float) $db->value('SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_acct = ? AND gl_date <= ?', [$r['bk_acct'], $today]), 2);
            if ((int) $r['bk_petty'] === 1) {
                // petty cash is counted box by box, never reconciled with a bank
                $r['bk_rec_date'] = $r['bk_waiting'] = null;
                $r['_link'] = '/petty-cash';
                continue;
            }
            $r['bk_rec_date'] = $db->value('SELECT MAX(bkr_date) FROM bkr_mstr WHERE bkr_bank = ?', [$r['bk_bank']]);
            $r['bk_waiting'] = (int) $db->value(
                'SELECT COUNT(*) FROM gl_trhist g LEFT JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr
                  WHERE g.gl_acct = ? AND g.gl_date <= ? AND d.bkrd_trnbr IS NULL',
                [$r['bk_acct'], $today]
            );
            $r['_link'] = '/banks/' . rawurlencode($r['bk_bank']);
        }
        return $page;
    }
}
