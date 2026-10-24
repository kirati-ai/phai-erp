<?php

declare(strict_types=1);

namespace Phai\Modules\Accounting;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Crud\Lookup;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Validation\Check;
use Phai\Core\View\View;

/**
 * Every ledger entry, automatic or manual. An entry is identified by type~ref~date.
 * Manual journals (JE) can only post to accounts that are not control accounts,
 * and are corrected by reversing, never by editing.
 */
#[Program('journals')]
final class JournalController
{
    #[Get('/journals')]
    public function listPage(): string
    {
        return View::render('doc/list', [
            'title'   => t('accounting.je_title'),
            'help'    => 'journals',
            'config'  => [
                'api'     => '/api/journals',
                'files'   => 'JE',
                'page'    => '/journals',
                'canAdd'  => Auth::can('journals', 'add'),
                'status'  => self::types(),
                'key'     => 'entry',
                'sort'    => 'last_id',
                'columns' => [
                    ['key' => 'gl_ref', 'label' => t('accounting.ref'), 'sort' => true, 'strong' => true],
                    ['key' => 'gl_date', 'label' => t('accounting.date'), 'type' => 'date', 'sort' => true],
                    ['key' => 'gl_type', 'label' => t('accounting.type'), 'type' => 'status', 'sort' => true],
                    ['key' => 'gl_desc', 'label' => t('accounting.desc')],
                    ['key' => 'amount', 'label' => t('accounting.amount'), 'type' => 'money', 'sort' => true],
                    ['key' => 'gl_userid', 'label' => t('stock.user')],
                ],
            ],
            'scripts' => ['js/doclist.js'],
        ]);
    }

    #[Get('/journals/new')]
    public function newPage(): string
    {
        return $this->page(null);
    }

    #[Get('/journals/{entry}')]
    public function viewPage(string $entry): string
    {
        self::lines($entry);
        return $this->page($entry);
    }

    /** ?q= &status= (posting type) &sort= &dir= &page= */
    #[Describe('General ledger entries, one row per entry (column entry identifies it)', ['q' => 'reference, description or account', 'status' => 'posting type: JE manual journal, IC inventory, AR sales invoice, CR cash receipt, AP payable, PV payment, LC landed cost, WO production'])]
    #[Get('/api/journals')]
    public function list(Request $request): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (isset(self::types()[$type = (string) $request->input('status', '')])) {
            $where[] = 'gl_type = ?';
            $params[] = $type;
        }
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where[] = '(gl_ref LIKE ? OR gl_desc LIKE ? OR gl_acct LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        return App::db()->page(
            "SELECT CONCAT(gl_type, '~', gl_ref, '~', gl_date) AS entry, gl_ref, gl_date, gl_type, MIN(gl_desc) AS gl_desc,
                    SUM(GREATEST(gl_amt, 0)) AS amount, MIN(gl_userid) AS gl_userid, MAX(gl_trnbr) AS last_id",
            'FROM gl_trhist WHERE ' . implode(' AND ', $where),
            $params,
            ['sort' => $request->query['sort'] ?? 'last_id', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['last_id' => 'MAX(gl_trnbr)', 'gl_ref' => 'gl_ref', 'gl_date' => 'gl_date', 'gl_type' => 'gl_type', 'amount' => 'SUM(GREATEST(gl_amt, 0))'],
            'last_id',
            'gl_type, gl_ref, gl_date'
        );
    }

    #[Describe('Lines of one ledger entry; entry = TYPE~REF~YYYY-MM-DD (the entry column of the list)')]

    #[Get('/api/journals/{entry}')]
    public function show(string $entry): array
    {
        [$type, $ref, $date] = self::split($entry);
        $lines = self::lines($entry);
        return [
            'head'  => ['type' => $type, 'ref' => $ref, 'date' => $date, 'desc' => $lines[0]['gl_desc'] ?? '',
                        'reversible' => $type === 'JE' && Auth::can('journals', 'add')],
            'lines' => $lines,
        ];
    }

    /** Body: {date, desc, lines: {row: {acct, cc, desc, dr, cr}}} */
    #[Post('/api/journals')]
    public function create(Request $request): array
    {
        $b = $request->body;
        $c = new Check();
        $date = $c->date('date', $b['date'] ?? '', true);
        if ($date && $date < Gl::periodStart()) {
            $c->fail('date', t('accounting.period_closed', ['date' => Gl::periodStart()]));
        }
        $desc = $c->text('desc', $b['desc'] ?? '', true, 255, false);
        $lines = [];
        $dr = $cr = 0.0;
        foreach (is_array($b['lines'] ?? null) ? $b['lines'] : [] as $i => $l) {
            if (!is_array($l) || (trim((string) ($l['acct'] ?? '')) === '' && trim((string) ($l['dr'] ?? '')) === '' && trim((string) ($l['cr'] ?? '')) === '')) {
                continue;
            }
            $f = fn (string $n) => "lines.$i.$n";
            $acct = $c->exists($f('acct'), $l['acct'] ?? '', 'ac_mstr', 'ac_acct', true, 'ac_active = 1');
            if ($acct !== null && $c->ok($f('acct')) && App::db()->value('SELECT ac_is_control FROM ac_mstr WHERE ac_acct = ?', [$acct])) {
                $c->fail($f('acct'), t('accounting.control_account'));
            }
            $cc = $c->exists($f('cc'), $l['cc'] ?? '', 'cc_mstr', 'cc_cc', false);
            $d = $c->number($f('dr'), $l['dr'] ?? '', false, 0, false, 2) ?? 0.0;
            $k = $c->number($f('cr'), $l['cr'] ?? '', false, 0, false, 2) ?? 0.0;
            if (($d > 0) === ($k > 0) && $c->ok($f('dr')) && $c->ok($f('cr'))) {
                $c->fail($f('dr'), t('accounting.dr_or_cr'));
            }
            $dr += $d;
            $cr += $k;
            $lines[] = [$acct, $d - $k, $c->text($f('desc'), $l['desc'] ?? '', false, 255, false) ?? $desc, $cc];
        }
        if (count($lines) < 2) {
            $c->fail('lines', t('accounting.two_lines'));
        } elseif (round($dr - $cr, 2) != 0.0) {
            $c->fail('lines', t('accounting.not_balanced', ['diff' => number_format($dr - $cr, 2)]));
        }
        $c->done();

        $ref = App::db()->transaction(function () use ($date, $desc, $lines) {
            $ref = Numbering::next('JE', $date);
            Gl::post($ref, 'JE', $date, $lines, $desc);
            return $ref;
        });
        return ['nbr' => "JE~$ref~$date"];
    }

    /** A new journal with every line the other way round, dated today. */
    #[Post('/api/journals/{entry}/reverse'), Action('add')]
    public function reverse(string $entry): array
    {
        [$type, $ref] = self::split($entry);
        if ($type !== 'JE') {
            throw new HttpException(409, t('accounting.only_je'));
        }
        $lines = self::lines($entry);
        $date = max(date('Y-m-d'), Gl::periodStart());
        $new = App::db()->transaction(function () use ($lines, $ref, $date) {
            $new = Numbering::next('JE', $date);
            Gl::post($new, 'JE', $date, array_map(
                fn ($l) => [$l['gl_acct'], -(float) $l['gl_amt'], t('accounting.reverse_of', ['ref' => $ref]), $l['gl_cc']],
                $lines
            ));
            return $new;
        });
        return ['nbr' => "JE~$new~$date"];
    }

    /** "AR~INV000001~2026-10-03" -> [type, ref, date] */
    private static function split(string $entry): array
    {
        $parts = explode('~', $entry);
        if (count($parts) !== 3) {
            throw HttpException::notFound();
        }
        return $parts;
    }

    private static function lines(string $entry): array
    {
        [$type, $ref, $date] = self::split($entry);
        $lines = App::db()->all(
            'SELECT g.gl_acct, ' . Gl::acctName('a') . ' AS ac_desc_th, g.gl_cc, g.gl_desc, g.gl_amt,
                    GREATEST(g.gl_amt, 0) AS dr, GREATEST(-g.gl_amt, 0) AS cr
               FROM gl_trhist g LEFT JOIN ac_mstr a ON a.ac_acct = g.gl_acct
              WHERE g.gl_type = ? AND g.gl_ref = ? AND g.gl_date = ? ORDER BY g.gl_trnbr',
            [$type, $ref, $date]
        );
        return $lines ?: throw HttpException::notFound();
    }

    private function page(?string $entry): string
    {
        return View::render('accounting/journal', [
            'docNbr' => $entry === null ? Numbering::peek('JE') : null,
            'title'   => $entry ? explode('~', $entry)[1] : t('accounting.je_new'),
            'help'    => 'journals',
            'ownToolbar' => true,
            'config'  => [
                'entry'    => $entry,
                'printUrl' => $entry !== null && Auth::can('journals', 'print') ? '/documents/journals/' . rawurlencode($entry) . '/print' : null,
                'types'    => self::types(),
                'accounts' => App::db()->all(
                    "SELECT ac_acct AS value, CONCAT(ac_acct, ' · ', " . Gl::acctName() . ") AS label
                       FROM ac_mstr WHERE ac_active = 1 AND ac_is_control = 0 ORDER BY ac_acct"
                ),
                'costCenters' => Lookup::options('cost-centers'),
                'today'    => date('Y-m-d'),
                'periodStart' => Gl::periodStart(),
            ],
            'scripts' => ['js/pages/journal.js'],
        ]);
    }

    /** Posting types with a label and badge colour (doclist shows them like statuses). */
    public static function types(): array
    {
        $colors = ['JE' => 'blue', 'IC' => 'secondary', 'AR' => 'green', 'CR' => 'teal', 'AP' => 'orange', 'VO' => 'pink', 'PV' => 'red', 'LC' => 'yellow', 'WO' => 'purple', 'FA' => 'cyan', 'VR' => 'lime', 'PC' => 'indigo'];
        $out = [];
        foreach ($colors as $code => $color) {
            $out[$code] = ['text' => t("accounting.type_$code"), 'color' => $color];
        }
        return $out;
    }
}
