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
use Phai\Core\Http\Response;

/** Routings: the operations to make an item, one routing per item (ro_routing = item code). */
#[Program('routings')]
final class RoutingController
{
    /** The steps are a tab of the สูตรการผลิต screen. */
    #[Get('/routings')]
    public function page(): Response
    {
        return Response::redirect(url('/boms?tab=routing'));
    }

    #[Describe('Parts that have a routing', ['q' => 'part or description'])]

    #[Get('/api/routings')]
    public function list(Request $request): array
    {
        $like = '%' . addcslashes(trim((string) $request->input('q', '')), '%_\\') . '%';
        return App::db()->all(
            'SELECT ro.ro_routing AS parent, pt.pt_desc1 AS descr, COUNT(*) AS line_count FROM ro_mstr ro
               LEFT JOIN pt_mstr pt ON pt.pt_part = ro.ro_routing
              WHERE ro.ro_routing LIKE ? OR pt.pt_desc1 LIKE ?
              GROUP BY ro.ro_routing, pt.pt_desc1 ORDER BY ro.ro_routing LIMIT 200',
            [$like, $like]
        );
    }

    #[Describe('Routing (operations, work centers, times) of one part')]

    #[Get('/api/routings/{part}')]
    public function show(string $part): array
    {
        return ['parent' => self::parent($part), 'lines' => self::ops($part)];
    }

    /** Body: {lines: {row: {ro_op, ro_wkctr, ro_desc, ro_setup_time, ro_run_time}}} — replaces the routing. */
    #[Put('/api/routings/{part}'), Action('view')]
    public function save(string $part, Request $request): array
    {
        self::parent($part);
        $old = self::ops($part);
        if (!Auth::can('routings', $old ? 'edit' : 'add')) {
            throw new HttpException(403, t('auth.forbidden'));
        }
        $c = new Check();
        $rows = [];
        foreach (is_array($request->body['lines'] ?? null) ? $request->body['lines'] : [] as $i => $l) {
            if (!is_array($l) || (trim((string) ($l['ro_op'] ?? '')) === '' && trim((string) ($l['ro_wkctr'] ?? '')) === '')) {
                continue;
            }
            $f = fn (string $n) => "lines.$i.$n";
            $op = $c->number($f('ro_op'), $l['ro_op'] ?? '', true, 0, true, 0);
            if ($op !== null && isset($rows[(int) $op])) {
                $c->fail($f('ro_op'), t('production.duplicate_op'));
            }
            $row = [
                'ro_wkctr'      => $c->exists($f('ro_wkctr'), $l['ro_wkctr'] ?? '', 'wc_mstr', 'wc_wkctr'),
                'ro_desc'       => $c->text($f('ro_desc'), $l['ro_desc'] ?? '', false, 100, false),
                'ro_setup_time' => $c->number($f('ro_setup_time'), $l['ro_setup_time'] ?? '', false, 0, false, 4) ?? 0.0,
                'ro_run_time'   => $c->number($f('ro_run_time'), $l['ro_run_time'] ?? '', false, 0, false, 4) ?? 0.0,
            ];
            if ($op !== null) {
                $rows[(int) $op] = $row;
            }
        }
        if (!$rows) {
            $c->fail('lines', t('stock.no_lines'));
        }
        $c->done();
        ksort($rows);

        App::db()->transaction(function (Db $db) use ($part, $rows, $old) {
            $db->delete('ro_mstr', ['ro_routing' => $part]);
            $user = Auth::userId();
            foreach ($rows as $op => $r) {
                $db->insert('ro_mstr', $r + ['ro_routing' => $part, 'ro_op' => $op, 'created_by' => $user, 'updated_by' => $user]);
            }
            $db->update('pt_mstr', ['pt_routing' => $part], ['pt_part' => $part]);
            Audit::updated('ro_mstr', "ro_routing=$part",
                ['ops' => json_encode(array_column($old, 'ro_wkctr', 'ro_op'))],
                ['ops' => json_encode(array_column(array_map(fn ($op, $r) => ['op' => $op, 'wc' => $r['ro_wkctr']], array_keys($rows), $rows), 'wc', 'op'))]);
        });
        return $this->show($part);
    }

    #[Delete('/api/routings/{part}')]
    public function delete(string $part): null
    {
        App::db()->transaction(function (Db $db) use ($part) {
            if ($db->delete('ro_mstr', ['ro_routing' => $part]) > 0) {
                $db->update('pt_mstr', ['pt_routing' => null], ['pt_part' => $part]);
                Audit::deleted('ro_mstr', "ro_routing=$part", ['ro_routing' => $part]);
            }
        });
        return null;
    }

    public static function ops(string $part): array
    {
        return App::db()->all(
            'SELECT ro.*, wc.wc_desc, COALESCE(wc.wc_lbr_rate, 0) AS wc_lbr_rate, COALESCE(wc.wc_mch_rate, 0) + COALESCE(wc.wc_bdn_rate, 0) AS wc_ovh_rate
               FROM ro_mstr ro LEFT JOIN wc_mstr wc ON wc.wc_wkctr = ro.ro_wkctr WHERE ro.ro_routing = ? ORDER BY ro.ro_op',
            [$part]
        );
    }

    private static function parent(string $part): array
    {
        return App::db()->one('SELECT pt_part, pt_desc1, pt_um FROM pt_mstr WHERE pt_part = ?', [$part]) ?? throw HttpException::notFound();
    }
}
