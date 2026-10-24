<?php
declare(strict_types=1);
// Creates a random, dedicated database; never changes config.php or business data.
require dirname(__DIR__) . '/src/autoload.php';
use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Db\Db;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Router;
use Phai\Install\Installer;
use Phai\Modules\Accounting\FixedAsset;
use Phai\Modules\Accounting\FixedAssetController;
$config = require PHAI_ROOT . '/config.php';
$source = new Db($config['db']);
$name = 'phaierp_fa_test_' . bin2hex(random_bytes(5));
$source->run('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$config['db']['name'] = $name; $config['app']['debug'] = true;
App::boot($config); $db = App::db();
$checks = 0;
function verify(bool $yes, string $name): void { if (!$yes) throw new RuntimeException($name); $GLOBALS['checks']++; echo "ok $name\n"; }
function fails(callable $fn, int $status, string $name): void {
    try { $fn(); } catch (HttpException $e) { verify($e->status === $status, $name); return; }
    throw new RuntimeException($name . ' accepted');
}
try {
    foreach (['database/schema/phaierp_schema.sql', 'database/seeds/20_reference.sql', 'database/seeds/30_system.sql', 'database/seeds/35_programs.sql', 'database/seeds/40_menu.sql'] as $f) foreach (Installer::statements(file_get_contents(PHAI_ROOT . '/' . $f)) as $sql) $db->run($sql);
    $db->update('gl_ctrl', ['gl_curr_year' => 2026, 'gl_curr_per' => 1], ['ctrl_id' => 1]);
    $db->insert('us_mstr', ['us_userid' => 'fa-test', 'us_name' => 'Asset test', 'us_email' => 'fa-test@example.invalid', 'us_password_hash' => 'login-disabled', 'us_role' => 'ADMIN']);
    Auth::actAs('fa-test');
    foreach (['ASSET', 'ACCUM', 'EXP', 'LOSS', 'CASH'] as $acct) $db->insert('ac_mstr', ['ac_acct' => $acct, 'ac_desc' => $acct, 'ac_desc_th' => $acct, 'ac_type' => 'A', 'ac_active' => 1, 'ac_is_control' => 0]);
    $db->insert('si_mstr', ['si_site' => 'TEST', 'si_desc' => 'TEST']);
    $db->update('co_mstr', ['co_name' => 'บริษัททดสอบสินทรัพย์', 'co_logo_path' => 'logo.png'], ['co_id' => 1]);
    $db->insert('cc_mstr', ['cc_cc' => 'CC', 'cc_desc' => 'CC']);
    $db->insert('fa_category', ['fc_code' => 'MACH', 'fc_desc' => 'Machines', 'fc_asset_acct' => 'ASSET', 'fc_accum_acct' => 'ACCUM', 'fc_expense_acct' => 'EXP', 'fc_gainloss_acct' => 'LOSS']);
    $body = ['fa_code' => 'FA001', 'fa_desc' => 'Machine', 'fa_category' => 'MACH', 'fa_site' => 'TEST', 'fa_cc' => 'CC', 'fa_acquired' => '2026-01-01', 'fa_start' => '2026-01-20', 'fa_cost' => 12000, 'fa_salvage' => 1200, 'fa_method' => 'SL', 'fa_life_months' => 12, 'fa_rate' => 40];
    $a = FixedAsset::save(null, $body); verify($a['fa_status'] === 'DRAFT', 'draft created');
    fails(fn () => FixedAsset::save(null, $body + []), 409, 'duplicate register blocked');
    $a = FixedAsset::activate('FA001', ['mode' => 'POST', 'credit_acct' => 'CASH']);
    verify($a['fa_status'] === 'ACTIVE' && $db->value("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_type='FA'") == 0, 'acquisition balanced');
    fails(fn () => FixedAsset::save('FA001', ['fa_cost' => 99]), 409, 'active edit blocked');
    fails(fn () => FixedAsset::depreciate('FA001', '2026-02'), 409, 'skipped month blocked');
    $a = FixedAsset::depreciate('FA001', '2026-01'); verify((float) $a['fa_accum'] === 900.0, 'monthly depreciation posted');
    $n = $db->value('SELECT COUNT(*) FROM gl_trhist');
    fails(fn () => FixedAsset::depreciate('FA001', '2026-01'), 409, 'duplicate month blocked');
    verify($n == $db->value('SELECT COUNT(*) FROM gl_trhist'), 'duplicate made no GL entries');
    $tx = (int) $db->value("SELECT MAX(fat_id) FROM fa_trhist WHERE fat_type='DEPR'");
    FixedAsset::reverse('FA001', $tx, ['date' => '2026-02-01', 'note' => 'Correction']);
    verify((float) FixedAsset::get('FA001')['fa_accum'] === 0.0, 'reverse restores register');
    FixedAsset::depreciate('FA001', '2026-01');
    FixedAsset::transfer('FA001', ['date' => '2026-02-01', 'site' => 'TEST', 'cc' => 'CC', 'location' => 'ROOM2', 'custodian' => 'Owner', 'note' => 'Move']);
    verify(FixedAsset::get('FA001')['fa_location'] === 'ROOM2', 'transfer recorded');
    $a = FixedAsset::dispose('FA001', ['date' => '2026-02-05', 'proceeds' => 10000, 'proceeds_acct' => 'CASH', 'note' => 'Sold']);
    verify($a['fa_status'] === 'DISPOSED', 'disposal closes register');
    verify((float) $db->value("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_acct='ASSET'") === 0.0, 'disposal clears asset cost');
    verify((float) $db->value("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_acct='ACCUM'") === 0.0, 'disposal clears accumulated depreciation');
    fails(fn () => FixedAsset::depreciate('FA001', '2026-02'), 409, 'disposed depreciation blocked');
    $tx = (int) $db->value("SELECT MAX(fat_id) FROM fa_trhist WHERE fat_type='DISPOSE'");
    FixedAsset::reverse('FA001', $tx, ['date' => '2026-02-06', 'note' => 'Sale cancelled']);
    verify(FixedAsset::get('FA001')['fa_status'] === 'ACTIVE', 'reverse disposal reopens register');
    $opening = array_replace($body, ['fa_code' => 'FA002', 'fa_method' => 'DB', 'fa_opening_depr' => 2000, 'fa_opening_month' => '2026-01']);
    FixedAsset::save(null, $opening);
    fails(fn () => FixedAsset::activate('FA002', ['mode' => 'POST', 'credit_acct' => 'CASH']), 422, 'opening cannot double-post cost');
    FixedAsset::activate('FA002', ['mode' => 'BOOKED']);
    $a = FixedAsset::depreciate('FA002', '2026-02'); verify((float) $a['fa_accum'] === 2333.33, 'declining balance uses opening NBV');
    $controller = new FixedAssetController();
    $before = $db->value('SELECT COUNT(*) FROM gl_trhist');
    fails(fn () => $controller->batch(new Request('POST', '/api/fixed-assets/depreciation-batch', '', [], ['month' => '2026-02', 'codes' => ['FA001', 'FA002']], [], '')), 409, 'batch duplicate rolls back all');
    verify($before == $db->value('SELECT COUNT(*) FROM gl_trhist') && FixedAsset::get('FA001')['fa_last_month'] === '2026-01', 'batch rollback leaves no partial postings');
    $old = array_replace($body, ['fa_code' => 'CLOSED', 'fa_acquired' => '2025-12-01', 'fa_start' => '2025-12-01']);
    FixedAsset::save(null, $old);
    fails(fn () => FixedAsset::activate('CLOSED', ['mode' => 'POST', 'credit_acct' => 'CASH']), 422, 'closed-period acquisition blocked');
    verify(FixedAsset::get('CLOSED')['fa_status'] === 'DRAFT', 'failed acquisition leaves draft intact');
    fails(fn () => FixedAsset::save(null, array_replace($body, ['fa_code' => 'BAD', 'fa_salvage' => 13000])), 422, 'residual above cost blocked');
    fails(fn () => FixedAsset::transfer('FA001', ['date' => '2025-01-01', 'site' => 'TEST', 'note' => 'Backdate']), 422, 'backdated movement blocked');
    fails(fn () => FixedAsset::save(null, array_replace($body, ['fa_code' => 'BAD/CODE'])), 422, 'unsafe URL code blocked');
    fails(fn () => Phai\Modules\Accounting\AccountResource::destroy(['ac_acct' => 'ASSET']), 409, 'asset account deletion blocked');
    $r = new Router(); $r->load(PHAI_ROOT . '/src/Modules');
    foreach (['/fixed-assets', '/fixed-assets/new', '/fixed-assets/FA001', '/fixed-assets/depreciation', '/fixed-asset-categories', '/api/fixed-assets', '/api/fixed-assets/depreciation-preview'] as $path) {
        $res = $r->dispatch(new Request('GET', $path, '', [], [], [], '')); verify(strlen($res->body) > 0, 'route ' . $path);
    }
    verify(str_contains($controller->print('FA001'), 'FA001'), 'print template renders');
    if (in_array('--pdf', $argv, true)) {
        file_put_contents(PHAI_ROOT . '/storage/tmp/fa-register-test.pdf', Phai\Core\Pdf::fromHtml($controller->print('FA001')));
        file_put_contents(PHAI_ROOT . '/storage/tmp/fa-report-test.pdf', Phai\Core\Pdf::fromHtml($controller->report()));
        verify(true, 'register and report PDFs rendered');
    }
    verify(abs((float) $db->value("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_type='FA'")) < .001, 'all FA entries balanced');
    $db->insert('role_mstr', ['role_code' => 'FAREAD', 'role_desc' => 'Read only']);
    $db->insert('us_mstr', ['us_userid' => 'fa-reader', 'us_name' => 'Reader', 'us_email' => 'reader@example.invalid', 'us_password_hash' => 'login-disabled', 'us_role' => 'FAREAD']);
    $db->insert('role_prog_det', ['rpd_role' => 'FAREAD', 'rpd_prog' => 'fixed-assets', 'rpd_can_view' => 1]);
    Auth::actAs('fa-reader');
    fails(fn () => $r->dispatch(new Request('POST', '/api/fixed-assets/FA001/depreciate', '', [], ['month' => '2026-02'], [], '')), 403, 'read-only cannot post');
    fails(fn () => $r->dispatch(new Request('GET', '/fixed-assets/FA001/print', '', ['html' => '1'], [], [], '')), 403, 'read-only cannot print without print permission');
    echo "$checks integration checks passed in isolated database.\n";
} finally {
    // Only the random database created above is removed.
    if (!preg_match('/^phaierp_fa_test_[a-f0-9]{10}$/D', $name) || $name === (require PHAI_ROOT . '/config.php')['db']['name']) throw new RuntimeException('Unsafe cleanup target');
    $source->run('DROP DATABASE `' . $name . '`');
}
