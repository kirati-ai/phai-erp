<?php

declare(strict_types=1);

namespace Phai\Modules\Inventory;

use Phai\Core\Ai\Describe;
use Phai\Core\App;
use Phai\Core\Auth\Action;
use Phai\Core\Auth\Auth;
use Phai\Core\Auth\Program;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Numbering;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;
use Phai\Modules\System\Attachment;

/**
 * Physical inventory (PI) by tags. Creating a count takes a snapshot of the warehouse: one numbered tag (ป้าย)
 * per location / part / lot. The tags are printed and hung on the stock, the counter writes the quantity and signs,
 * the tags come back and are keyed in. Every tag number must be accounted for — counted, or voided when it was not
 * used — before posting adjusts stock by the differences. A tag that is off can be recounted: the first count is kept.
 * From creation until it is posted or deleted, the tagged stock is frozen: nothing receives, ships or moves it.
 * A count covers the whole warehouse (a full count, once a year) or only the locations chosen (a cycle count):
 * only those are tagged and frozen, the rest of the warehouse keeps working.
 */
#[Program('stock-counts')]
final class StockCountController
{
    private const OPEN = 'O';
    private const POSTED = 'P';

    #[Get('/stock-counts')]
    public function page(): string
    {
        return View::render('inventory/counts', [
            'title'   => t('stock.counts_title'),
            'sites'   => StockLookupController::sites(),
            'canAdd'  => Auth::can('stock-counts', 'add'),
            'reasons' => App::db()->all('SELECT rs_code, rs_desc FROM rs_mstr WHERE rs_active = 1 ORDER BY rs_code'),
            'help'    => 'stock-counts',
            'queues'  => self::queues(),
            'scripts' => ['js/pages/stock-counts.js'],
        ]);
    }

    /** Stock counts not posted yet above the list: one click opens the batch. */
    public static function queues(): array
    {
        return Auth::can('stock-counts', 'edit') ? [[
            'title'   => t('stock.queue_counts'), 'api' => '/api/stock-counts/waiting', 'key' => 'pi_batch',
            'columns' => [
                ['key' => 'pi_batch', 'label' => t('stock.nbr'), 'link' => '/stock-counts/{pi_batch}'],
                ['key' => 'pi_site', 'label' => t('stock.site')],
                ['key' => 'pi_date', 'label' => t('stock.date'), 'type' => 'date'],
                ['key' => 'pi_desc', 'label' => t('stock.count_desc')],
                ['key' => 'progress', 'label' => t('stock.counted') . ' / ' . t('stock.tags')],
            ],
            'urgent'  => 'late',
            'action'  => ['label' => t('stock.queue_open'), 'icon' => 'ti ti-clipboard-list', 'href' => '/stock-counts/{pi_batch}'],
        ]] : [];
    }

    #[Describe('Stock counts not posted yet, oldest first, with counted / total lines (late = count date passed)')]
    #[Get('/api/stock-counts/waiting')]
    public function waiting(): array
    {
        return App::db()->all(
            "SELECT pi.pi_batch, pi.pi_site, pi.pi_date, pi.pi_desc, pi.pi_date < CURDATE() AS late,
                    CONCAT((SELECT COUNT(*) FROM pi_tag t WHERE t.pit_batch = pi.pi_batch AND t.pit_status = 'C'), ' / ',
                           (SELECT COUNT(*) FROM pi_tag t WHERE t.pit_batch = pi.pi_batch AND t.pit_status <> 'V')) AS progress
               FROM pi_mstr pi WHERE pi.pi_status IN (?, 'F') ORDER BY pi.pi_date, pi.pi_batch LIMIT 200",
            [self::OPEN]
        );
    }

    #[Describe('Stock count batches and progress')]

    #[Get('/api/stock-counts')]
    public function list(Request $request): array
    {
        $where = '';
        $params = [];
        if (($q = trim((string) $request->input('q', ''))) !== '') {
            $where = ' WHERE pi.pi_batch LIKE ? OR pi.pi_desc LIKE ? OR pi.pi_site LIKE ?';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params = [$like, $like, $like];
        }
        return App::db()->page(
            "SELECT pi.pi_batch, pi.pi_site, pi.pi_date, pi.pi_desc, pi.pi_status, pi.pi_locs,
                    (SELECT COUNT(*) FROM pi_tag t WHERE t.pit_batch = pi.pi_batch AND t.pit_status <> 'V') AS tags,
                    (SELECT COUNT(*) FROM pi_tag t WHERE t.pit_batch = pi.pi_batch AND t.pit_status = 'C') AS counted",
            'FROM pi_mstr pi' . $where,
            $params,
            ['sort' => $request->query['sort'] ?? 'pi_batch', 'dir' => $request->query['dir'] ?? 'desc'] + $request->query,
            ['pi_batch' => 'pi.pi_batch', 'pi_date' => 'pi.pi_date', 'pi_site' => 'pi.pi_site', 'pi_status' => 'pi.pi_status'],
            'pi_batch'
        );
    }

    /**
     * Body: {site, date, desc, reason, locs: [loc, ...]}. Snapshots the warehouse — or only the locations given — into
     * tags. The reason (e.g. OPEN for opening stock) picks the account the differences are booked against; none =
     * the stock adjustment account.
     */
    #[Post('/api/stock-counts')]
    public function create(Request $request): array
    {
        $site = trim((string) ($request->body['site'] ?? ''));
        $date = trim((string) ($request->body['date'] ?? ''));
        $desc = trim((string) ($request->body['desc'] ?? ''));
        $reason = trim((string) ($request->body['reason'] ?? ''));
        $errors = [];
        if ($reason !== '' && !App::db()->value('SELECT 1 FROM rs_mstr WHERE rs_code = ? AND rs_active = 1', [$reason])) {
            $errors['reason'] = t('validation.in');
        }
        if (!App::db()->value('SELECT 1 FROM si_mstr WHERE si_site = ? AND si_status = 1', [$site])) {
            $errors['site'] = $site === '' ? t('validation.required') : t('validation.in');
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors['date'] = $date === '' ? t('validation.required') : t('validation.date');
        }
        if (mb_strlen($desc) > 100) {
            $errors['desc'] = t('validation.max_length', ['max' => 100]);
        }
        // the scope: locations of this warehouse, none = all of it
        $locs = array_values(array_unique(array_filter(array_map(
            fn ($l) => mb_strtoupper(trim((string) $l)),
            is_array($request->body['locs'] ?? null) ? $request->body['locs'] : []
        ))));
        if ($locs && !isset($errors['site'])) {
            $known = array_column(App::db()->all('SELECT loc_loc FROM loc_mstr WHERE loc_site = ?', [$site]), 'loc_loc');
            if (array_diff($locs, $known) || mb_strlen(implode(',', $locs)) > 1000) {
                $errors['locs'] = t('validation.in');
            }
        }
        if ($errors) {
            throw HttpException::invalid($errors);
        }

        return App::db()->transaction(function (Db $db) use ($site, $date, $desc, $reason, $locs) {
            $batch = Numbering::next('PI', $date);
            $user = Auth::userId();
            $db->insert('pi_mstr', [
                'pi_batch' => $batch, 'pi_site' => $site, 'pi_desc' => $desc !== '' ? $desc : null, 'pi_date' => $date,
                'pi_status' => self::OPEN, 'pi_rsn_code' => $reason !== '' ? $reason : null, 'pi_locs' => $locs ? implode(',', $locs) : null,
                'pi_freeze_date' => date('Y-m-d H:i:s'), 'created_by' => $user, 'updated_by' => $user,
            ]);
            $db->run(
                "INSERT INTO pi_tag (pit_batch, pit_tag, pit_part, pit_loc, pit_lot, pit_sys_qty, pit_status, created_by, updated_by)
                 SELECT ?, ROW_NUMBER() OVER (ORDER BY ld_loc, ld_part, ld_lot), ld_part, ld_loc, ld_lot, ld_qty_oh, 'O', ?, ?
                   FROM ld_det WHERE ld_site = ? AND ld_qty_oh <> 0"
                   . ($locs ? ' AND ld_loc IN (' . implode(',', array_fill(0, count($locs), '?')) . ')' : ''),
                array_merge([$batch, $user, $user, $site], $locs)
            );
            return ['pi_batch' => $batch];
        });
    }

    #[Get('/stock-counts/{batch}')]
    public function detail(string $batch): string
    {
        $head = self::head($batch);
        return View::render('inventory/count', [
            'title'      => t('stock.count_title', ['nbr' => $batch]),
            'ownToolbar' => true,
            'head'       => $head,
            'canEdit'    => Auth::can('stock-counts', 'edit') && $head['pi_status'] === self::OPEN,
            'canPost'    => Auth::can('stock-counts', 'edit') && $head['pi_status'] === self::OPEN,
            'canDelete'  => Auth::can('stock-counts', 'delete') && $head['pi_status'] === self::OPEN,
            'help'       => 'stock-counts',
            'scripts'    => ['js/pages/stock-count.js'],
        ]);
    }

    #[Describe('One stock count with its tags (system and counted quantity)')]

    #[Get('/api/stock-counts/{batch}')]
    public function show(string $batch): array
    {
        return ['head' => self::head($batch), 'tags' => self::tags($batch)];
    }

    /**
     * Body: {count_1: "12", count_2: "", ..., counter: "name on the tags"} — blank means not counted yet; the counter
     * (default: who is logged in) goes on the tags whose count changed.
     * A count is compared with what the system holds when it is entered. The tagged stock is frozen until the count
     * is posted (Stock::move refuses to move it), so that is the snapshot figure unless something changed it before.
     */
    #[Put('/api/stock-counts/{batch}/counts')]
    public function saveCounts(string $batch, Request $request): array
    {
        $head = self::open($batch);
        $tags = array_column(self::tags($batch), null, 'pit_tag');
        $errors = [];
        $set = [];
        $counter = mb_substr(trim((string) ($request->body['counter'] ?? '')), 0, 100) ?: (Auth::personName() ?: Auth::userId());
        foreach ($request->body as $name => $value) {
            if (!preg_match('/^count_(\d+)$/', (string) $name, $m) || !isset($tags[(int) $m[1]]) || $tags[(int) $m[1]]['pit_status'] === 'V') {
                continue;
            }
            $v = str_replace(',', '', trim((string) (is_scalar($value) ? $value : '')));
            if ($v === '') {
                $set[(int) $m[1]] = null;
            } elseif (!is_numeric($v) || (float) $v < 0 || (float) $v >= 1e10) {
                $errors[$name] = t('validation.number');
            } else {
                $set[(int) $m[1]] = round((float) $v, 5);
            }
        }
        if ($errors) {
            throw HttpException::invalid($errors);
        }

        App::db()->transaction(function (Db $db) use ($batch, $head, $set, $tags, $counter) {
            foreach ($set as $tag => $qty) {
                $t = $tags[$tag];
                $was = $t['pit_count_qty'] === null ? null : round((float) $t['pit_count_qty'], 5);
                if ($qty === $was) {
                    continue; // unchanged: keeps its counter and the system figure of when it was keyed
                }
                $sys = $qty === null ? (float) $t['pit_sys_qty'] : (float) $db->value(
                    'SELECT COALESCE(SUM(ld_qty_oh), 0) FROM ld_det WHERE ld_site = ? AND ld_loc = ? AND ld_part = ? AND ld_lot = ?',
                    [$head['pi_site'], $t['pit_loc'], $t['pit_part'], $t['pit_lot']]
                );
                $db->update('pi_tag', [
                    'pit_sys_qty'   => $sys,
                    'pit_count_qty' => $qty,
                    'pit_variance'  => $qty === null ? 0 : round($qty - $sys, 5),
                    'pit_status'    => $qty === null ? 'O' : 'C',
                    'pit_counter'   => $qty === null ? null : $counter,
                    'updated_by'    => Auth::userId(),
                ], ['pit_batch' => $batch, 'pit_tag' => $tag]);
            }
        });
        return self::tags($batch);
    }

    /**
     * Something found that was not in the snapshot (or everything, for opening stock). Body: {part, loc, lot, qty, cost}.
     * A lot left empty on a lot-controlled item is the count's number; cost = unit cost it comes in at (empty: item cost).
     */
    #[Post('/api/stock-counts/{batch}/tags'), Action('edit')]
    public function addTag(string $batch, Request $request): array
    {
        $head = self::open($batch);
        $b = $request->body;
        $part = mb_strtoupper(trim((string) ($b['part'] ?? '')));
        $loc = mb_strtoupper(trim((string) ($b['loc'] ?? '')));
        $lot = trim((string) ($b['lot'] ?? ''));
        $qty = str_replace(',', '', trim((string) ($b['qty'] ?? '')));
        $cost = str_replace(',', '', trim((string) ($b['cost'] ?? '')));
        $errors = [];
        if ($cost !== '' && (!is_numeric($cost) || (float) $cost < 0)) {
            $errors['cost'] = t('validation.number');
        }

        $p = $part !== '' ? Stock::part($part) : null;
        if (!$p) {
            $errors['part'] = $part === '' ? t('validation.required') : t('stock.no_part');
        }
        if (!App::db()->value('SELECT 1 FROM loc_mstr WHERE loc_site = ? AND loc_loc = ?', [$head['pi_site'], $loc])) {
            $errors['loc'] = $loc === '' ? t('validation.required') : t('validation.in');
        } elseif ($head['pi_locs'] && !in_array($loc, explode(',', $head['pi_locs']), true)) {
            $errors['loc'] = t('stock.loc_out_of_scope', ['locs' => str_replace(',', ', ', $head['pi_locs'])]); // not counted, not frozen
        }
        if ($p && Stock::needsLot($p) && $lot === '') {
            $lot = $batch;
        } elseif ($p && !Stock::needsLot($p)) {
            $lot = '';
        }
        if ($qty === '' || !is_numeric($qty) || (float) $qty < 0) {
            $errors['qty'] = $qty === '' ? t('validation.required') : t('validation.number');
        }
        if (!$errors) {
            $dup = App::db()->value(
                "SELECT pit_tag FROM pi_tag WHERE pit_batch = ? AND pit_part = ? AND pit_loc = ? AND pit_lot = ? AND pit_status <> 'V'",
                [$batch, $part, $loc, $lot]
            );
            if ($dup) {
                $errors['part'] = t('stock.tag_exists', ['tag' => $dup]);
            }
        }
        if ($errors) {
            throw HttpException::invalid($errors);
        }

        App::db()->transaction(function (Db $db) use ($batch, $head, $part, $loc, $lot, $qty, $cost, $b) {
            // Compare with what the system holds now, since this place was not in the snapshot.
            $sys = (float) $db->value(
                "SELECT ld_qty_oh FROM ld_det WHERE ld_site = ? AND ld_loc = ? AND ld_part = ? AND ld_lot = ?",
                [$head['pi_site'], $loc, $part, $lot]
            );
            $tag = (int) $db->value('SELECT COALESCE(MAX(pit_tag), 0) + 1 FROM pi_tag WHERE pit_batch = ? FOR UPDATE', [$batch]);
            $db->insert('pi_tag', [
                'pit_batch' => $batch, 'pit_tag' => $tag, 'pit_part' => $part, 'pit_loc' => $loc, 'pit_lot' => $lot,
                'pit_sys_qty' => $sys, 'pit_count_qty' => round((float) $qty, 5), 'pit_variance' => round((float) $qty - $sys, 5),
                'pit_cost' => $cost !== '' ? round((float) $cost, 5) : null,
                'pit_status' => 'C', 'pit_counter' => mb_substr(trim((string) ($b['counter'] ?? '')), 0, 100) ?: (Auth::personName() ?: Auth::userId()),
                'created_by' => Auth::userId(), 'updated_by' => Auth::userId(),
            ]);
        });
        return self::tags($batch);
    }

    /** Adjust stock by every difference and close the count. */
    #[Post('/api/stock-counts/{batch}/post'), Action('edit')]
    public function post(string $batch): array
    {
        $head = self::open($batch);
        $tags = self::tags($batch);
        $open = array_column(array_filter($tags, fn ($t) => $t['pit_status'] === 'O'), 'pit_tag');
        if ($open) {
            $list = implode(', ', array_slice($open, 0, 20)) . (count($open) > 20 ? ', …' : '');
            throw new HttpException(422, t('stock.count_incomplete', ['n' => count($open), 'tags' => $list]));
        }

        return App::db()->transaction(function (Db $db) use ($batch, $head, $tags) {
            $adjusted = 0;
            foreach ($tags as $t) {
                if ($t['pit_status'] !== 'C' || (float) $t['pit_variance'] == 0) {
                    continue;
                }
                Stock::move([
                    'type' => 'CYC-CNT', 'part' => $t['pit_part'], 'site' => $head['pi_site'], 'loc' => $t['pit_loc'],
                    'lot' => $t['pit_lot'], 'qty' => (float) $t['pit_variance'], 'date' => $head['pi_date'],
                    'nbr' => $batch, 'line' => (int) $t['pit_tag'], 'program' => 'stock-counts', 'reason' => $head['pi_rsn_code'],
                    'cost' => (float) $t['pit_variance'] > 0 && $t['pit_cost'] !== null ? (float) $t['pit_cost'] : null,
                ]);
                $adjusted++;
            }
            $db->update('pi_mstr', ['pi_status' => self::POSTED, 'updated_by' => Auth::userId()], ['pi_batch' => $batch]);
            return ['adjusted' => $adjusted];
        });
    }

    /**
     * Void a tag that was not used (nothing there, printed twice) — it keeps its number, so the tags still add up —
     * or bring a voided one back. Body: {void: true|false}.
     */
    #[Post('/api/stock-counts/{batch}/tags/{tag}/void'), Action('edit')]
    public function void(string $batch, int $tag, Request $request): array
    {
        self::open($batch);
        $t = self::tag($batch, $tag);
        $void = filter_var($request->body['void'] ?? true, FILTER_VALIDATE_BOOL);
        App::db()->update('pi_tag', [
            'pit_status' => $void ? 'V' : 'O', 'pit_count_qty' => null, 'pit_variance' => 0, 'pit_counter' => null, 'updated_by' => Auth::userId(),
        ], ['pit_batch' => $batch, 'pit_tag' => $t['pit_tag']]);
        return self::tags($batch);
    }

    /** Count a tag again (another person, when the difference is large): the first count is kept beside it. */
    #[Post('/api/stock-counts/{batch}/tags/{tag}/recount'), Action('edit')]
    public function recount(string $batch, int $tag): array
    {
        self::open($batch);
        $t = self::tag($batch, $tag);
        if ($t['pit_status'] !== 'C') {
            throw new HttpException(409, t('stock.recount_not_counted'));
        }
        App::db()->update('pi_tag', [
            'pit_count1' => $t['pit_count_qty'], 'pit_counter1' => $t['pit_counter'], 'pit_count_qty' => null, 'pit_variance' => 0,
            'pit_counter' => null, 'pit_status' => 'O', 'updated_by' => Auth::userId(),
        ], ['pit_batch' => $batch, 'pit_tag' => $t['pit_tag']]);
        return self::tags($batch);
    }

    /**
     * The tags to hang on the stock, eight to an A4 page: number, place, item, lot and room to write the count and sign.
     * No system quantity on them, so people count what is there (a blind count). Voided tags are not printed.
     */
    #[Get('/stock-counts/{batch}/tags/print'), Action('print')]
    public function printTags(string $batch): string
    {
        $head = self::head($batch);
        return View::render('inventory/count-tags', [
            'title' => t('stock.tags_print') . ' ' . $batch,
            'head'  => $head,
            'tags'  => array_values(array_filter(self::tags($batch), fn ($t) => $t['pit_status'] !== 'V')),
            'co'    => (string) App::db()->value('SELECT co_name FROM co_mstr WHERE co_id = 1'),
        ], 'blank');
    }

    #[Delete('/api/stock-counts/{batch}')]
    public function delete(string $batch): null
    {
        self::open($batch);
        App::db()->transaction(function (Db $db) use ($batch) {
            $db->delete('pi_tag', ['pit_batch' => $batch]);
            $db->delete('pi_mstr', ['pi_batch' => $batch]);
            Attachment::removeAll('PI', $batch);
        });
        return null;
    }

    private static function head(string $batch): array
    {
        return App::db()->one(
            'SELECT pi.*, si.si_desc FROM pi_mstr pi LEFT JOIN si_mstr si ON si.si_site = pi.pi_site WHERE pi.pi_batch = ?',
            [$batch]
        ) ?? throw HttpException::notFound();
    }

    /** The count, if it can still be changed. */
    private static function open(string $batch): array
    {
        $head = self::head($batch);
        if ($head['pi_status'] !== self::OPEN) {
            throw new HttpException(409, t('stock.count_closed'));
        }
        return $head;
    }

    /** Every tag, voided ones too: a tag number is never dropped. */
    private static function tags(string $batch): array
    {
        return App::db()->all(
            "SELECT t.pit_tag, t.pit_loc, t.pit_part, pt.pt_desc1, pt.pt_um, t.pit_lot, t.pit_sys_qty, t.pit_count_qty,
                    t.pit_variance, t.pit_cost, t.pit_status, t.pit_counter, t.pit_count1, t.pit_counter1
               FROM pi_tag t LEFT JOIN pt_mstr pt ON pt.pt_part = t.pit_part
              WHERE t.pit_batch = ? ORDER BY t.pit_tag",
            [$batch]
        );
    }

    private static function tag(string $batch, int $tag): array
    {
        return App::db()->one('SELECT * FROM pi_tag WHERE pit_batch = ? AND pit_tag = ?', [$batch, $tag]) ?? throw HttpException::notFound();
    }
}
