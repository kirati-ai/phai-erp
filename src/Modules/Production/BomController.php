<?php

declare(strict_types=1);

namespace Phai\Modules\Production;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Put;
use Phai\Core\Validation\Check;

/** Bills of material, one per parent item. Saved as a whole list; edited on the สูตรการผลิต screen (ProductionSetController). */
#[Program('boms')]
final class BomController
{
    /** Parent items that have a BOM. ?q= */
    #[Describe('Parts that have a bill of materials', ['q' => 'part or description'])]
    #[Get('/api/boms')]
    public function list(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            'SELECT ps.ps_par AS parent, pt.pt_desc1 AS descr, COUNT(*) AS line_count FROM ps_mstr ps
               LEFT JOIN pt_mstr pt ON pt.pt_part = ps.ps_par
              WHERE ps.ps_par LIKE ? OR pt.pt_desc1 LIKE ?
              GROUP BY ps.ps_par, pt.pt_desc1 ORDER BY ps.ps_par LIMIT 200',
            [$like, $like]
        );
    }

    #[Describe('Bill of materials (components and quantity per) of one part')]

    #[Get('/api/boms/{part}')]
    public function show(string $part): array
    {
        return [
            'parent' => self::parent($part),
            'lines'  => App::db()->all(
                'SELECT ps.*, pt.pt_desc1, pt.pt_um, pt.pt_cost FROM ps_mstr ps LEFT JOIN pt_mstr pt ON pt.pt_part = ps.ps_comp
                  WHERE ps.ps_par = ? ORDER BY ps.ps_comp',
                [$part]
            ),
        ];
    }

    /** Body: {lines: {row: {ps_comp, ps_qty_per, ps_scrap, ps_start, ps_end, ps_rmks}}} — replaces the BOM. */
    #[Put('/api/boms/{part}'), Action('view')]
    public function save(string $part, Request $request): array
    {
        self::parent($part);
        $old = App::db()->all('SELECT ps_comp, ps_qty_per, ps_scrap FROM ps_mstr WHERE ps_par = ? ORDER BY ps_comp', [$part]);
        if (!Auth::can('boms', $old ? 'edit' : 'add')) {
            throw new HttpException(403, t('auth.forbidden'));
        }

        $c = new Check();
        $rows = [];
        foreach (is_array($request->body['lines'] ?? null) ? $request->body['lines'] : [] as $i => $l) {
            if (!is_array($l) || trim((string) ($l['ps_comp'] ?? '')) === '') {
                continue;
            }
            $f = fn (string $n) => "lines.$i.$n";
            $comp = $c->exists($f('ps_comp'), $l['ps_comp'], 'pt_mstr', 'pt_part');
            if ($comp !== null && $c->ok($f('ps_comp'))) {
                if (isset($rows[$comp])) {
                    $c->fail($f('ps_comp'), t('production.duplicate_comp'));
                } elseif (Bom::wouldLoop($part, $comp)) {
                    $c->fail($f('ps_comp'), t('production.bom_loop'));
                }
            }
            $row = [
                'ps_qty_per' => $c->number($f('ps_qty_per'), $l['ps_qty_per'] ?? '', true, 0, true),
                'ps_scrap'   => $c->number($f('ps_scrap'), $l['ps_scrap'] ?? '', false, 0, false, 2) ?? 0.0,
                'ps_start'   => $c->date($f('ps_start'), $l['ps_start'] ?? ''),
                'ps_end'     => $c->date($f('ps_end'), $l['ps_end'] ?? ''),
                'ps_rmks'    => $c->text($f('ps_rmks'), $l['ps_rmks'] ?? '', false, 255, false),
            ];
            if ($row['ps_scrap'] >= 100) {
                $c->fail($f('ps_scrap'), t('validation.max', ['max' => 99.99]));
            }
            if ($row['ps_start'] && $row['ps_end'] && $row['ps_end'] < $row['ps_start']) {
                $c->fail($f('ps_end'), t('production.end_before_start'));
            }
            if ($comp !== null) {
                $rows[$comp] = $row;
            }
        }
        if (!$rows) {
            $c->fail('lines', t('stock.no_lines'));
        }
        $c->done();

        App::db()->transaction(function (Db $db) use ($part, $rows, $old) {
            $db->delete('ps_mstr', ['ps_par' => $part]);
            $user = Auth::userId();
            foreach ($rows as $comp => $r) {
                $db->insert('ps_mstr', $r + ['ps_par' => $part, 'ps_comp' => $comp, 'created_by' => $user, 'updated_by' => $user]);
            }
            $summary = fn (array $list) => json_encode($list, JSON_UNESCAPED_UNICODE);
            Audit::updated('ps_mstr', "ps_par=$part", ['lines' => $summary($old)], ['lines' => $summary(array_map(
                fn ($comp, $r) => ['ps_comp' => $comp, 'ps_qty_per' => $r['ps_qty_per'], 'ps_scrap' => $r['ps_scrap']],
                array_keys($rows),
                $rows
            ))]);
        });
        return $this->show($part);
    }

    #[Delete('/api/boms/{part}')]
    public function delete(string $part): null
    {
        App::db()->transaction(function (Db $db) use ($part) {
            if ($db->delete('ps_mstr', ['ps_par' => $part]) > 0) {
                Audit::deleted('ps_mstr', "ps_par=$part", ['ps_par' => $part]);
            }
        });
        return null;
    }

    private static function parent(string $part): array
    {
        return App::db()->one('SELECT pt_part, pt_desc1, pt_um FROM pt_mstr WHERE pt_part = ?', [$part]) ?? throw HttpException::notFound();
    }
}
