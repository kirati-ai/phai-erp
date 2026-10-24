<?php
declare(strict_types=1);
namespace Phai\Modules\Accounting;

use Phai\Core\App;
use Phai\Core\Audit\Audit;
use Phai\Core\Auth\Auth;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Validation\Check;

final class FixedAsset
{
    public static function get(string $code, bool $lock = false): array
    {
        return App::db()->one('SELECT * FROM fa_mstr WHERE fa_code=?' . ($lock ? ' FOR UPDATE' : ''), [$code]) ?? throw HttpException::notFound();
    }
    public static function save(?string $code, array $body): array
    {
        return App::db()->transaction(function (Db $db) use ($code, $body) {
            $old = $code !== null ? self::get($code, true) : null;
            if ($old && $old['fa_status'] !== 'DRAFT') throw new HttpException(409, t('assets.err_not_draft'));
            $c = new Check();
            $row = [];
            $row['fa_code'] = $code ?? $c->text('fa_code', $body['fa_code'] ?? '', true, 50, true);
            if ($row['fa_code'] && !preg_match('/^[\pL\pN._-]+$/uD', $row['fa_code'])) $c->fail('fa_code', t('assets.err_code_chars'));
            $b = $body + ($old ?? []);
            foreach (['fa_desc' => 255, 'fa_serial' => 100, 'fa_location' => 100, 'fa_custodian' => 100, 'fa_source_ref' => 100, 'fa_notes' => 4000] as $k => $max) $row[$k] = $c->text($k, $b[$k] ?? '', $k === 'fa_desc', $max);
            $row['fa_category'] = $c->exists('fa_category', $b['fa_category'] ?? '', 'fa_category', 'fc_code', true, 'fc_active=1');
            $row['fa_site'] = $c->exists('fa_site', $b['fa_site'] ?? '', 'si_mstr', 'si_site', true, 'si_status=1');
            $row['fa_cc'] = $c->exists('fa_cc', $b['fa_cc'] ?? '', 'cc_mstr', 'cc_cc', false, 'cc_active=1');
            $row['fa_prod_line'] = $c->exists('fa_prod_line', $b['fa_prod_line'] ?? '', 'pl_mstr', 'pl_prod_line', false, 'pl_active=1');
            $row['fa_vendor'] = $c->exists('fa_vendor', $b['fa_vendor'] ?? '', 'vd_mstr', 'vd_code', false);
            foreach (['fa_acquired', 'fa_start'] as $k) $row[$k] = $c->date($k, $b[$k] ?? '', true);
            $row['fa_cost'] = $c->number('fa_cost', $b['fa_cost'] ?? '', true, 0, true, 2);
            $row['fa_salvage'] = $c->number('fa_salvage', $b['fa_salvage'] ?? 0, true, 0, false, 2);
            $row['fa_opening_depr'] = $c->number('fa_opening_depr', $b['fa_opening_depr'] ?? 0, true, 0, false, 2);
            $category = $row['fa_category'] ? $db->one('SELECT * FROM fa_category WHERE fc_code=?', [$row['fa_category']]) : [];
            $row['fa_method'] = $c->text('fa_method', $b['fa_method'] ?? $category['fc_method'] ?? 'SL', true, 4);
            $life = $c->number('fa_life_months', $b['fa_life_months'] ?? $category['fc_life_months'] ?? 60, true, 0, false, 0);
            $row['fa_life_months'] = (int) $life;
            if (isset($b['fa_life_months']) && is_numeric($b['fa_life_months']) && (float) $b['fa_life_months'] != $life) $c->fail('fa_life_months', t('assets.err_life_whole'));
            $row['fa_rate'] = $c->number('fa_rate', $b['fa_rate'] ?? $category['fc_rate'] ?? 20, true, 0, false, 4);
            if (!in_array($row['fa_method'], ['SL', 'DB', 'NONE'], true)) $c->fail('fa_method', t('assets.err_method'));
            if ($row['fa_life_months'] > 1200 || ($row['fa_method'] !== 'NONE' && $row['fa_life_months'] < 1)) $c->fail('fa_life_months', t('assets.err_life'));
            if ($row['fa_rate'] > 100 || ($row['fa_method'] === 'DB' && $row['fa_rate'] <= 0)) $c->fail('fa_rate', t('assets.err_rate'));
            if ($row['fa_salvage'] + $row['fa_opening_depr'] > $row['fa_cost']) $c->fail('fa_opening_depr', t('assets.err_salvage'));
            if ($row['fa_start'] < $row['fa_acquired']) $c->fail('fa_start', t('assets.err_start'));
            $row['fa_opening_month'] = $c->text('fa_opening_month', $b['fa_opening_month'] ?? '', false, 7);
            if ($row['fa_opening_month'] && (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $row['fa_opening_month']) || $row['fa_opening_month'] < substr($row['fa_start'] ?? '', 0, 7))) $c->fail('fa_opening_month', t('assets.err_opening_month'));
            if ($row['fa_opening_depr'] > 0 && !$row['fa_opening_month']) $c->fail('fa_opening_month', t('assets.err_opening_need'));
            if ($row['fa_method'] === 'NONE' && $row['fa_opening_depr'] > 0) $c->fail('fa_opening_depr', t('assets.err_opening_none'));
            $c->done();
            $row['fa_accum'] = $row['fa_opening_depr'];
            $row['fa_last_month'] = $row['fa_opening_month'];
            $row['updated_by'] = Auth::userId();
            if ($old) {
                $db->update('fa_mstr', $row, ['fa_code' => $code]);
                Audit::updated('fa_mstr', 'fa_code=' . $code, $old, $row);
            } else {
                if ($db->value('SELECT 1 FROM fa_mstr WHERE fa_code=?', [$row['fa_code']])) throw new HttpException(409, t('assets.err_exists'));
                $row['created_by'] = Auth::userId();
                $db->insert('fa_mstr', $row);
                Audit::created('fa_mstr', 'fa_code=' . $row['fa_code'], $row);
            }
            return self::get($row['fa_code']);
        });
    }
    public static function activate(string $code, array $b): array
    {
        return App::db()->transaction(function (Db $db) use ($code, $b) {
            $a = self::get($code, true);
            if ($a['fa_status'] !== 'DRAFT') throw new HttpException(409, t('assets.err_activated'));
            $cat = $db->one('SELECT * FROM fa_category WHERE fc_code=? AND fc_active=1', [$a['fa_category']]) ?? throw new HttpException(422, t('assets.err_category'));
            $after = $a;
            foreach (['asset', 'accum', 'expense', 'gainloss'] as $kind) {
                self::account($cat['fc_' . $kind . '_acct']);
                $after['fa_' . $kind . '_acct'] = $cat['fc_' . $kind . '_acct'];
            }
            if (count(array_unique([$after['fa_asset_acct'], $after['fa_accum_acct'], $after['fa_expense_acct'], $after['fa_gainloss_acct']])) !== 4) throw new HttpException(422, t('assets.err_accounts_same'));
            if ($a['fa_acquired'] > date('Y-m-d') || $a['fa_start'] > date('Y-m-d') || ($a['fa_opening_month'] && $a['fa_opening_month'] >= date('Y-m'))) throw new HttpException(422, t('assets.err_future'));
            $mode = $b['mode'] ?? '';
            if (!in_array($mode, ['POST', 'BOOKED'], true)) throw new HttpException(422, t('assets.err_mode'));
            if ($mode === 'POST' && ($a['fa_opening_depr'] > 0 || $a['fa_opening_month'])) throw new HttpException(422, t('assets.err_mode_opening'));
            $lines = [];
            if ($mode === 'POST') {
                $credit = self::account((string) ($b['credit_acct'] ?? ''));
                if ($credit === $after['fa_asset_acct']) throw new HttpException(422, t('assets.err_credit'));
                $lines = [[$after['fa_asset_acct'], (float) $a['fa_cost'], $a['fa_desc'], $a['fa_cc']], [$credit, -(float) $a['fa_cost']]];
            }
            $after['fa_status'] = 'ACTIVE';
            return self::record($a, $after, 'ACQUIRE', $a['fa_acquired'], (float) $a['fa_cost'], $lines, $mode === 'BOOKED' ? t('assets.note_booked') : t('assets.note_acquire'), $a['fa_source_ref']);
        });
    }
    public static function depreciate(string $code, string $month): array
    {
        return App::db()->transaction(function (Db $db) use ($code, $month) {
            $a = self::get($code, true);
            self::active($a);
            try { AssetDepreciation::month($month); } catch (\InvalidArgumentException $e) { throw new HttpException(422, $e->getMessage()); }
            if ($a['fa_method'] === 'NONE') throw new HttpException(422, t('assets.err_no_depr'));
            if ($month !== AssetDepreciation::next($a)) throw new HttpException(409, t('assets.err_depr_next', ['month' => AssetDepreciation::next($a)]));
            $date = date('Y-m-t', strtotime($month . '-01'));
            self::chronology($a, $date);
            $amount = AssetDepreciation::amount($a, $month);
            if ($amount <= 0) throw new HttpException(422, t('assets.err_depr_done'));
            $after = $a;
            $after['fa_accum'] = round((float) $a['fa_accum'] + $amount, 2);
            $after['fa_last_month'] = $month;
            foreach (['expense', 'accum'] as $kind) self::account($a['fa_' . $kind . '_acct']);
            return self::record($a, $after, 'DEPR', $date, $amount, [[$a['fa_expense_acct'], $amount, t('assets.note_depr', ['ref' => $code]), $a['fa_cc']], [$a['fa_accum_acct'], -$amount]], t('assets.note_depr', ['ref' => $month]), null, $month);
        });
    }
    public static function transfer(string $code, array $b): array
    {
        return App::db()->transaction(function (Db $db) use ($code, $b) {
            $a = self::get($code, true); self::active($a);
            $c = new Check();
            $date = $c->date('date', $b['date'] ?? '', true);
            $after = $a;
            $after['fa_site'] = $c->exists('site', $b['site'] ?? '', 'si_mstr', 'si_site', true, 'si_status=1');
            $after['fa_cc'] = $c->exists('cc', $b['cc'] ?? '', 'cc_mstr', 'cc_cc', false, 'cc_active=1');
            $after['fa_location'] = $c->text('location', $b['location'] ?? '', false, 100);
            $after['fa_custodian'] = $c->text('custodian', $b['custodian'] ?? '', false, 100);
            $note = $c->text('note', $b['note'] ?? '', true, 255);
            $c->done(); self::chronology($a, $date);
            if ($date < Gl::periodStart()) throw new HttpException(422, t('assets.err_transfer_closed'));
            return self::record($a, $after, 'TRANSFER', $date, 0, [], $note);
        });
    }
    public static function dispose(string $code, array $b): array
    {
        return App::db()->transaction(function (Db $db) use ($code, $b) {
            $a = self::get($code, true); self::active($a);
            $c = new Check();
            $date = $c->date('date', $b['date'] ?? '', true);
            $proceeds = $c->number('proceeds', $b['proceeds'] ?? 0, true, 0, false, 2);
            $note = $c->text('note', $b['note'] ?? '', true, 255);
            $ref = $c->text('ref', $b['ref'] ?? '', false, 100);
            $c->done(); self::chronology($a, $date);
            $previous = (new \DateTimeImmutable(substr($date, 0, 7) . '-01'))->modify('-1 month')->format('Y-m');
            if ($a['fa_method'] !== 'NONE' && (float) $a['fa_cost'] - (float) $a['fa_salvage'] - (float) $a['fa_accum'] > .001 && AssetDepreciation::next($a) <= $previous) throw new HttpException(409, t('assets.err_depr_first'));
            if ($a['fa_method'] !== 'NONE' && $a['fa_last_month'] && $a['fa_last_month'] >= substr($date, 0, 7)) throw new HttpException(409, t('assets.err_depr_dispose_month'));
            $book = round((float) $a['fa_cost'] - (float) $a['fa_accum'], 2);
            $lines = [[$a['fa_asset_acct'], -(float) $a['fa_cost']], [$a['fa_accum_acct'], (float) $a['fa_accum']], [$a['fa_gainloss_acct'], $book - $proceeds, $note, $a['fa_cc']]];
            foreach (['asset', 'accum', 'gainloss'] as $kind) self::account($a['fa_' . $kind . '_acct']);
            if ($proceeds > 0) {
                $cash = self::account((string) ($b['proceeds_acct'] ?? ''));
                if (in_array($cash, [$a['fa_asset_acct'], $a['fa_accum_acct'], $a['fa_gainloss_acct']], true)) throw new HttpException(422, t('assets.err_proceeds_acct'));
                $lines[] = [$cash, $proceeds];
            }
            $after = $a; $after['fa_status'] = 'DISPOSED'; $after['fa_disposed'] = $date;
            return self::record($a, $after, 'DISPOSE', $date, $proceeds, $lines, $note, $ref);
        });
    }
    public static function reverse(string $code, int $id, array $b): array
    {
        return App::db()->transaction(function (Db $db) use ($code, $id, $b) {
            $a = self::get($code, true);
            $tx = $db->one("SELECT * FROM fa_trhist WHERE fat_asset=? AND fat_type<>'REVERSE' AND fat_reversed=0 ORDER BY fat_id DESC LIMIT 1 FOR UPDATE", [$code]);
            if (!$tx || (int) $tx['fat_id'] !== $id) throw new HttpException(409, t('assets.err_reverse_latest'));
            $c = new Check(); $date = $c->date('date', $b['date'] ?? '', true); $note = $c->text('note', $b['note'] ?? '', true, 255); $c->done();
            if ($date < $tx['fat_date'] || $date < Gl::periodStart() || $date > date('Y-m-d')) throw new HttpException(422, t('assets.err_reverse_date'));
            $after = json_decode($tx['fat_before'], true, 512, JSON_THROW_ON_ERROR);
            $db->update('fa_trhist', ['fat_reversed' => 1, 'fat_guard' => null, 'updated_by' => Auth::userId()], ['fat_id' => $id]);
            $lines = $tx['fat_posted'] ? array_map(fn ($r) => [$r['gl_acct'], -(float) $r['gl_amt'], $note, $r['gl_cc']], $db->all("SELECT * FROM gl_trhist WHERE gl_ref=? AND gl_type='FA'", ['FA-' . $id])) : [];
            return self::record($a, $after, 'REVERSE', $date, -(float) $tx['fat_amount'], $lines, $note, 'FA-' . $id, null, $id);
        });
    }
    private static function active(array $a): void
    {
        if ($a['fa_status'] !== 'ACTIVE') throw new HttpException(409, t('assets.err_not_active'));
    }
    private static function chronology(array $a, string $date): void
    {
        $last = App::db()->value("SELECT MAX(fat_date) FROM fa_trhist WHERE fat_asset=? AND fat_reversed=0 AND fat_type<>'REVERSE'", [$a['fa_code']]);
        $floor = max($last ?? $a['fa_acquired'], $a['fa_opening_month'] ? date('Y-m-t', strtotime($a['fa_opening_month'] . '-01')) : $a['fa_acquired']);
        if ($date < $floor || $date > date('Y-m-d')) throw new HttpException(422, t('assets.err_date'));
    }
    private static function account(string $account): string
    {
        if (!App::db()->value('SELECT 1 FROM ac_mstr WHERE ac_acct=? AND ac_active=1 AND ac_is_control=0', [$account])) throw new HttpException(422, t('assets.err_account', ['acct' => $account]));
        return $account;
    }
    private static function record(array $before, array $after, string $type, string $date, float $amount, array $lines, string $note, ?string $ref = null, ?string $month = null, ?int $reverseOf = null): array
    {
        $db = App::db();
        $after['updated_by'] = Auth::userId();
        $id = $db->insert('fa_trhist', ['fat_asset' => $before['fa_code'], 'fat_type' => $type, 'fat_date' => $date, 'fat_month' => $month, 'fat_amount' => $amount, 'fat_ref' => $ref, 'fat_note' => $note, 'fat_before' => json_encode($before, JSON_UNESCAPED_UNICODE), 'fat_after' => json_encode($after, JSON_UNESCAPED_UNICODE), 'fat_posted' => $lines ? 1 : 0, 'fat_guard' => $month ? 'DEPR:' . $month : null, 'fat_reverse_of' => $reverseOf, 'created_by' => Auth::userId(), 'updated_by' => Auth::userId()]);
        if ($lines) Gl::post('FA-' . $id, 'FA', $date, $lines, $note . ' ' . $before['fa_code']);
        $changes = array_diff_key($after, array_flip(['fa_code', 'created_by', 'created_at', 'updated_at']));
        $db->update('fa_mstr', $changes, ['fa_code' => $before['fa_code']]);
        Audit::updated('fa_mstr', 'fa_code=' . $before['fa_code'], $before, $after);
        Audit::created('fa_trhist', 'fat_id=' . $id, ['asset' => $before['fa_code'], 'type' => $type, 'amount' => $amount, 'date' => $date, 'reverse_of' => $reverseOf]);
        return self::get($before['fa_code']);
    }
}
