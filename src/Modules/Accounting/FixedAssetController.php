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
use Phai\Core\Routing\Get;
use Phai\Core\Routing\Delete;
use Phai\Core\Routing\Post;
use Phai\Core\Routing\Put;
use Phai\Core\View\View;
use Phai\Modules\System\Attachment;

#[Program('fixed-assets')]
final class FixedAssetController
{
    #[Get('/fixed-assets')]
    public function page(): string
    {
        return View::render('assets/list', ['help' => 'fixed-assets', 'title' => t('assets.title'), 'config' => [
            'api' => '/api/fixed-assets', 'page' => '/fixed-assets', 'canAdd' => Auth::can('fixed-assets', 'add'), 'files' => 'FA',
            'status' => self::statuses(), 'key' => 'fa_code', 'columns' => [
                ['key' => 'fa_code', 'label' => t('assets.col_code'), 'sort' => true], ['key' => 'fa_desc', 'label' => t('assets.col_desc'), 'sort' => true],
                ['key' => 'fa_category', 'label' => t('assets.col_category'), 'sort' => true], ['key' => 'fa_site', 'label' => t('assets.col_site'), 'sort' => true],
                ['key' => 'fa_cost', 'label' => t('assets.col_cost'), 'type' => 'money', 'sort' => true],
                ['key' => 'fa_accum', 'label' => t('assets.accum'), 'type' => 'money', 'sort' => true],
                ['key' => 'book_value', 'label' => t('assets.book_value'), 'type' => 'money'],
                ['key' => 'fa_status', 'label' => t('assets.col_status'), 'type' => 'status', 'sort' => true],
            ],
        ], 'queues' => self::queues(), 'scripts' => ['js/doclist.js']]);
    }
    /** Drafts waiting to be activated, and months whose depreciation is not posted yet. */
    public static function queues(): array
    {
        if (!Auth::can('fixed-assets', 'edit')) {
            return [];
        }
        return [[
            'title' => t('accounting.q_fa_draft'), 'api' => '/api/fixed-assets/waiting', 'key' => 'fa_code',
            'columns' => [
                ['key' => 'fa_code', 'label' => t('accounting.q_fa_code'), 'link' => '/fixed-assets/{fa_code}'],
                ['key' => 'fa_desc', 'label' => t('accounting.q_fa_desc')],
                ['key' => 'fa_category', 'label' => t('accounting.q_fa_category')],
                ['key' => 'fa_start', 'label' => t('accounting.q_fa_start'), 'type' => 'date'],
                ['key' => 'fa_cost', 'label' => t('accounting.q_fa_cost'), 'type' => 'money'],
            ],
            'action' => ['label' => t('accounting.q_fa_activate'), 'icon' => 'ti ti-circle-check', 'href' => '/fixed-assets/{fa_code}'],
        ], [
            'title' => t('accounting.q_fa_depr'), 'api' => '/api/fixed-assets/depreciation-waiting', 'key' => 'month',
            'urgent' => 'overdue',
            'columns' => [
                ['key' => 'month', 'label' => t('accounting.q_fa_month')],
                ['key' => 'assets', 'label' => t('accounting.q_fa_assets'), 'type' => 'num'],
                ['key' => 'amount', 'label' => t('accounting.q_fa_amount'), 'type' => 'money'],
            ],
            'action' => ['label' => t('accounting.q_fa_post'), 'icon' => 'ti ti-chart-line', 'href' => '/fixed-assets/depreciation?month={month}'],
        ]];
    }
    #[Describe('Fixed assets still in draft, waiting to be activated (registered), oldest in-service date first')]
    #[Get('/api/fixed-assets/waiting')]
    public function waiting(): array
    {
        return App::db()->all("SELECT fa_code, fa_desc, fa_category, fa_start, fa_cost FROM fa_mstr WHERE fa_status = 'DRAFT' ORDER BY fa_start, fa_code LIMIT 200");
    }
    #[Describe('Months up to last month with depreciation not posted yet: month (yyyy-mm), number of active assets due, amount (baht); overdue = before last month')]
    #[Get('/api/fixed-assets/depreciation-waiting')]
    public function depreciationWaiting(): array
    {
        $now = date('Y-m');
        $open = substr(Gl::periodStart(), 0, 7);
        $months = [];
        foreach (App::db()->all("SELECT * FROM fa_mstr WHERE fa_status = 'ACTIVE' AND fa_method <> 'NONE'") as $a) {
            $next = AssetDepreciation::next($a);
            if ($next >= $now || $next < $open || ($amount = AssetDepreciation::amount($a, $next)) <= 0) {
                continue;
            }
            $months[$next] ??= ['month' => $next, 'assets' => 0, 'amount' => 0.0];
            $months[$next]['assets']++;
            $months[$next]['amount'] = round($months[$next]['amount'] + $amount, 2);
        }
        ksort($months);
        $last = date('Y-m', strtotime('first day of last month'));
        return array_slice(array_map(fn ($m) => $m + ['overdue' => $m['month'] < $last], array_values($months)), 0, 200);
    }
    #[Get('/api/fixed-assets')]
    public function list(Request $r): array
    {
        $opt = $r->query;
        if (isset(self::statuses()[$opt['status'] ?? ''])) $opt['where']['fa_status'] = $opt['status'];
        $rows = FixedAssetForm::list($opt);
        foreach ($rows['rows'] as &$a) $a['book_value'] = $a['fa_status'] === 'DISPOSED' ? 0 : round((float) $a['fa_cost'] - (float) $a['fa_accum'], 2);
        // Public computed field is returned consistently with the shared column chooser.
        $rows['fields'][] = ['key' => 'book_value', 'label' => t('assets.book_value'), 'type' => 'money'];
        return $rows;
    }
    #[Get('/fixed-assets/new'), Action('add')]
    public function newPage(): string { return $this->detail(null, true); }
    #[Get('/fixed-assets/{code}')]
    public function viewPage(string $code, Request $r): string
    {
        $a = FixedAsset::get($code);
        return $this->detail($a, $a['fa_status'] === 'DRAFT' && Auth::can('fixed-assets', 'edit') && $r->input('edit') === '1');
    }
    private function detail(?array $a, bool $editing): string
    {
        return View::render('assets/detail', ['help' => 'fixed-assets', 'title' => $a['fa_code'] ?? t('assets.new_title'), 'asset' => $a, 'editing' => $editing,
            'fields' => FixedAssetForm::fields(), 'schedule' => $a && $a['fa_status'] !== 'DISPOSED' ? AssetDepreciation::schedule($a) : [],
            'history' => $a ? $this->history($a['fa_code']) : [], 'accounts' => Lookup::options('accounts'),
            'sites' => Lookup::options('sites'), 'centres' => Lookup::options('cost-centers'),
            'canPost' => Auth::can('fixed-assets', 'edit'), 'canEdit' => Auth::can('fixed-assets', 'edit'),
            'canPrint' => Auth::can('fixed-assets', 'print'), 'canDelete' => Auth::can('fixed-assets', 'delete'),
            'categories' => App::db()->all('SELECT fc_code,fc_method,fc_life_months,fc_rate FROM fa_category WHERE fc_active=1'), 'scripts' => ['js/pages/fixed-assets.js']]);
    }
    #[Get('/api/fixed-assets/{code}')]
    public function show(string $code): array { return ['head' => FixedAsset::get($code), 'history' => $this->history($code)]; }
    private function history(string $code): array
    {
        return App::db()->all('SELECT fat_id,fat_type,fat_date,fat_month,fat_amount,fat_ref,fat_note,fat_posted,fat_reversed,created_by FROM fa_trhist WHERE fat_asset=? ORDER BY fat_id DESC', [$code]);
    }
    #[Post('/api/fixed-assets'), Action('add')]
    public function create(Request $r): array { return FixedAsset::save(null, $r->body); }
    #[Put('/api/fixed-assets/{code}'), Action('edit')]
    public function update(string $code, Request $r): array { return FixedAsset::save($code, $r->body); }
    #[Delete('/api/fixed-assets/{code}'), Action('delete')]
    public function delete(string $code): array
    {
        return App::db()->transaction(function ($db) use ($code) {
            $a = FixedAsset::get($code, true);
            if ($a['fa_status'] !== 'DRAFT' || $db->value('SELECT 1 FROM fa_trhist WHERE fat_asset=? LIMIT 1', [$code])) throw new HttpException(409, t('assets.err_delete'));
            $db->delete('fa_mstr', ['fa_code' => $code]);
            \Phai\Core\Audit\Audit::deleted('fa_mstr', 'fa_code=' . $code, $a);
            Attachment::removeAll('FA', $code);
            return ['deleted' => true];
        });
    }

    /**
     * Receipts of fixed-asset items (product lines of type FA) to pick as an asset's purchase reference: the receipt
     * posted the cost to the asset account already (Dr asset / Cr GRNI), so the asset is registered as booked.
     */
    #[Describe('Goods receipts of fixed-asset items (product lines of type FA), newest first, to pick as the purchase reference of an asset: rc_inv_nbr = the vendor\'s invoice / delivery no., receipt, date, vendor, item, quantity, unit_cost in baht, category, registered = assets already referring to that invoice', ['q' => 'invoice, receipt, item or vendor'])]
    #[Get('/api/fixed-assets/receipts')]
    public function receipts(Request $r): array
    {
        $where = '';
        $params = [];
        if (($q = trim((string) $r->input('q', ''))) !== '') {
            $where = 'AND (r.rc_inv_nbr LIKE ? OR r.rc_nbr LIKE ? OR d.rcd_part LIKE ? OR p.pt_desc1 LIKE ? OR r.rc_vd_code LIKE ? OR v.vd_name LIKE ?)';
            $params = array_fill(0, 6, '%' . addcslashes($q, '%_\\') . '%');
        }
        return App::db()->all(
            "SELECT r.rc_nbr, r.rc_date, r.rc_inv_nbr, r.rc_vd_code, v.vd_name, d.rcd_line, d.rcd_part, p.pt_desc1, d.rcd_qty_rcvd AS qty, d.rcd_um,
                    ROUND(d.rcd_line_amt / NULLIF(d.rcd_qty_rcvd, 0) * COALESCE(r.rc_ex_rate, 1), 2) AS unit_cost, pl.pl_fa_category AS category,
                    (SELECT COUNT(*) FROM fa_mstr f WHERE f.fa_source_ref = r.rc_inv_nbr AND f.fa_vendor = r.rc_vd_code) AS registered
               FROM rcd_det d JOIN rc_mstr r ON r.rc_nbr = d.rcd_nbr JOIN pt_mstr p ON p.pt_part = d.rcd_part
               JOIN pl_mstr pl ON pl.pl_prod_line = p.pt_prod_line AND pl.pl_inventory_type = 'FA'
               LEFT JOIN vd_mstr v ON v.vd_code = r.rc_vd_code
              WHERE d.rcd_qty_rcvd > 0 $where ORDER BY r.rc_date DESC, r.rc_nbr DESC, d.rcd_line LIMIT 30",
            $params
        );
    }

    #[Post('/api/fixed-assets/{code}/activate'), Action('edit')]
    public function activate(string $code, Request $r): array { return FixedAsset::activate($code, $r->body); }
    #[Post('/api/fixed-assets/{code}/depreciate'), Action('edit')]
    public function depreciate(string $code, Request $r): array { return FixedAsset::depreciate($code, (string) ($r->body['month'] ?? '')); }
    #[Post('/api/fixed-assets/{code}/transfer'), Action('edit')]
    public function transfer(string $code, Request $r): array { return FixedAsset::transfer($code, $r->body); }
    #[Post('/api/fixed-assets/{code}/dispose'), Action('edit')]
    public function dispose(string $code, Request $r): array { return FixedAsset::dispose($code, $r->body); }
    #[Post('/api/fixed-assets/{code}/reverse/{id}'), Action('edit')]
    public function reverse(string $code, int $id, Request $r): array { return FixedAsset::reverse($code, $id, $r->body); }
    #[Get('/fixed-assets/depreciation')]
    public function depreciationPage(): string
    {
        return View::render('assets/depreciation', ['help' => 'fixed-assets', 'title' => t('assets.dep_title'), 'canPrint' => Auth::can('fixed-assets', 'print'), 'canPost' => Auth::can('fixed-assets', 'edit'), 'scripts' => ['js/pages/asset-depreciation.js']]);
    }
    #[Get('/api/fixed-assets/depreciation-preview')]
    public function preview(Request $r): array
    {
        $month = (string) $r->input('month', date('Y-m'));
        try { AssetDepreciation::month($month); } catch (\InvalidArgumentException $e) { throw new HttpException(422, $e->getMessage()); }
        $rows = [];
        foreach (App::db()->all("SELECT * FROM fa_mstr WHERE fa_status='ACTIVE' AND fa_method<>'NONE' ORDER BY fa_code") as $a) {
            $next = AssetDepreciation::next($a);
            $amount = AssetDepreciation::amount($a, $month);
            $a['next_month'] = $next; $a['depreciation'] = $amount;
            $a['eligible'] = $next === $month && $amount > 0 && $month >= substr(Gl::periodStart(), 0, 7) && $month < date('Y-m');
            $a['reason'] = $next !== $month ? t('assets.why_next', ['month' => $next]) : ($month >= date('Y-m') ? t('assets.why_open') : ($month < substr(Gl::periodStart(), 0, 7) ? t('assets.why_closed') : ($amount <= 0 ? t('assets.why_done') : t('assets.why_ready'))));
            $rows[] = $a;
        }
        return ['rows' => $rows, 'month' => $month];
    }
    #[Post('/api/fixed-assets/depreciation-batch'), Action('edit')]
    public function batch(Request $r): array
    {
        $codes = is_array($r->body['codes'] ?? null) ? $r->body['codes'] : [];
        if (!$codes || count($codes) > 500 || array_filter($codes, fn ($c) => !is_string($c))) throw new HttpException(422, t('assets.err_batch'));
        $codes = array_unique($codes); sort($codes);
        return App::db()->transaction(function () use ($codes, $r) {
            foreach ($codes as $code) FixedAsset::depreciate($code, (string) ($r->body['month'] ?? ''));
            return ['posted' => count($codes)];
        });
    }
    #[Get('/fixed-assets/{code}/print'), Action('print')]
    public function print(string $code): string
    {
        return View::render('assets/print', ['title' => $code, 'asset' => FixedAsset::get($code), 'history' => $this->history($code),
            'company' => App::db()->one('SELECT * FROM co_mstr LIMIT 1') ?? []], null);
    }
    #[Get('/fixed-assets/report/print'), Action('print')]
    public function report(): string
    {
        return View::render('assets/report', ['title' => t('assets.print_register'),
            'company' => App::db()->one('SELECT * FROM co_mstr LIMIT 1') ?? [],
            'rows' => App::db()->all('SELECT * FROM fa_mstr ORDER BY fa_category,fa_code')], null);
    }
    #[Get('/fixed-assets/depreciation/print'), Action('print')]
    public function depreciationPrint(Request $r): string
    {
        $month = (string) $r->input('month', date('Y-m'));
        try { AssetDepreciation::month($month); } catch (\InvalidArgumentException $e) { throw new HttpException(422, $e->getMessage()); }
        return View::render('assets/depreciation-print', ['title' => t('assets.print_depr', ['month' => $month]), 'month' => $month,
            'company' => App::db()->one('SELECT * FROM co_mstr LIMIT 1') ?? [],
            'rows' => App::db()->all("SELECT t.fat_id,t.fat_asset,a.fa_desc,a.fa_category,t.fat_date,t.fat_amount,t.fat_reversed,t.fat_note FROM fa_trhist t JOIN fa_mstr a ON a.fa_code=t.fat_asset WHERE t.fat_type='DEPR' AND t.fat_month=? ORDER BY t.fat_asset,t.fat_id", [$month])], null);
    }
    public static function statuses(): array
    {
        return ['DRAFT' => ['text' => t('assets.status_draft'), 'color' => 'secondary'], 'ACTIVE' => ['text' => t('assets.status_active'), 'color' => 'green'], 'DISPOSED' => ['text' => t('assets.status_disposed'), 'color' => 'red']];
    }
}
