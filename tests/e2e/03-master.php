<?php

// Master data screens (generic Resource CRUD)
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

function q(array $key): string
{
    return '?' . http_build_query($key);
}

$a = login('admin', $pw);

// every screen renders and lists
$screens = ['uoms', 'part-groups', 'part-types', 'product-lines', 'parts', 'customers', 'vendors', 'currencies',
    'exchange-rates', 'taxes', 'credit-terms', 'sites', 'locations', 'inventory-statuses', 'reason-codes'];
foreach ($screens as $s) {
    [$st, $html] = $a->page("/$s");
    $ok = $st === 200 && str_contains($html, 'crud-config');
    [$st2, $list] = $a->req('GET', "/api/$s");
    ok("screen $s", $ok && $st2 === 200 && isset($list['data']['rows']), [$st, $st2]);
}
foreach (['/company', '/settings/gl', '/settings/numbering'] as $p) {
    ok("page $p", $a->page($p)[0] === 200);
}

// parts: validation, lookups, create, update
$r = $a->req('POST', '/api/parts', ['pt_part' => '', 'pt_desc1' => '', 'pt_um' => 'NOPE', 'pt_prod_line' => 'FG', 'pt_price' => 'abc', 'pt_status' => 'X']);
ok('part validation collects errors', $r[0] === 422 && isset($r[1]['fields']['pt_part'], $r[1]['fields']['pt_desc1'], $r[1]['fields']['pt_um'], $r[1]['fields']['pt_price'], $r[1]['fields']['pt_status']), $r[1]);
$part = ['pt_part' => 'a-100', 'pt_desc1' => 'สินค้าทดสอบ', 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_status' => 'ACTIVE',
    'pt_part_type' => 'P', 'pt_price' => '1,250.5', 'pt_cost_method' => 'AVG', 'pt_abc' => 'C', 'pt_lot_ser' => ''];
$r = $a->req('POST', '/api/parts', $part);
ok('create part (code upper, price parsed)', $r[0] === 200 && $r[1]['data']['pt_part'] === 'A-100' && (float) $r[1]['data']['pt_price'] === 1250.5, $r[1]);
ok('blank optional field saved as null', $r[0] === 200 && $r[1]['data']['pt_lot_ser'] === null && (float) $r[1]['data']['pt_pack_qty'] === 1.0, $r[1]['data']);
$r = $a->req('POST', '/api/parts', $part);
ok('duplicate part code', $r[0] === 422 && isset($r[1]['fields']['pt_part']), $r[1]);
$r = $a->req('PUT', '/api/parts/row' . q(['pt_part' => 'A-100']), array_merge($part, ['pt_part' => 'IGNORED', 'pt_desc1' => 'แก้ชื่อแล้ว']));
ok('update part, key unchanged', $r[0] === 200 && $r[1]['data']['pt_part'] === 'A-100' && $r[1]['data']['pt_desc1'] === 'แก้ชื่อแล้ว', $r[1]);
$r = $a->req('GET', '/api/parts?q=' . urlencode('แก้ชื่อ'));
ok('search parts in Thai', $r[1]['data']['total'] === 1);
ok('row 404 for unknown key', $a->req('GET', '/api/parts/row' . q(['pt_part' => 'ZZZ']))[0] === 404);
ok('row 404 without key', $a->req('GET', '/api/parts/row')[0] === 404);

// used-by protection
$r = $a->req('DELETE', '/api/uoms/row' . q(['um_um' => 'EA']));
ok('cannot delete UoM used by a part', $r[0] === 409, $r[1]);
$r = $a->req('DELETE', '/api/product-lines/row' . q(['pl_prod_line' => 'FG']));
ok('cannot delete product line used by a part', $r[0] === 409, $r[1]);

// composite key: locations
$r = $a->req('POST', '/api/locations', ['loc_site' => 'MAIN', 'loc_loc' => 'a1', 'loc_desc' => 'ชั้น A1', 'loc_type' => 'M', 'loc_status' => 'ACTIVE', 'loc_nettable' => true]);
ok('create location (composite key, unsent field = DB default)', $r[0] === 200 && $r[1]['data']['loc_loc'] === 'A1' && $r[1]['data']['loc_nettable'] === 1 && $r[1]['data']['loc_mrp'] === 1, $r[1]);
$r = $a->req('PUT', '/api/locations/row' . q(['loc_site' => 'MAIN', 'loc_loc' => 'A1']), ['loc_desc' => 'ชั้น A1 ใหม่', 'loc_type' => 'S', 'loc_status' => 'HOLD']);
ok('update location', $r[0] === 200 && $r[1]['data']['loc_status'] === 'HOLD', $r[1]);
ok('delete location', $a->req('DELETE', '/api/locations/row' . q(['loc_site' => 'MAIN', 'loc_loc' => 'A1']))[0] === 200);
$r = $a->req('POST', '/api/locations', ['loc_site' => 'NOPE', 'loc_loc' => 'X']);
ok('location needs a real site', $r[0] === 422 && isset($r[1]['fields']['loc_site']), $r[1]);

// exchange rates: date key
$r = $a->req('POST', '/api/exchange-rates', ['ex_curr' => 'USD', 'ex_date' => '2026-02-30', 'ex_rate' => '35.5']);
ok('bad date rejected', $r[0] === 422 && isset($r[1]['fields']['ex_date']), $r[1]);
$r = $a->req('POST', '/api/exchange-rates', ['ex_curr' => 'USD', 'ex_date' => '2026-10-01', 'ex_rate' => '35.5']);
ok('create exchange rate', $r[0] === 200, $r[1]);
$r = $a->req('DELETE', '/api/currencies/row' . q(['cu_curr' => 'USD']));
ok('cannot delete currency with rates', $r[0] === 409, $r[1]);

// customer with Thai address
$prov = $a->req('GET', '/api/geo/provinces')[1]['data'];
$bkk = array_values(array_filter($prov, fn ($p) => $p['name'] === 'กรุงเทพมหานคร'))[0] ?? null;
$dist = $bkk ? $a->req('GET', '/api/geo/districts/' . $bkk['id'])[1]['data'] : [];
$sub = $dist ? $a->req('GET', '/api/geo/subdistricts/' . $dist[0]['id'])[1]['data'] : [];
ok('geo: 77 provinces, districts, subdistricts with zip', count($prov) === 77 && $dist && $sub && preg_match('/^\d{5}$/', (string) $sub[0]['zip']), [count($prov), count($dist), $sub[0] ?? null]);
$r = $a->req('POST', '/api/customers', party('cm', ['cm_code' => 'c001', 'cm_name' => 'บริษัท ทดสอบ จำกัด', 'cm_status' => 'ACTIVE', 'cm_email' => 'bad',
    'cm_province' => 'กรุงเทพมหานคร', 'cm_district' => $dist[0]['name'] ?? '', 'cm_sub_district' => $sub[0]['name'] ?? '', 'cm_zip' => $sub[0]['zip'] ?? '',
    'cm_terms' => '30DAYS', 'cm_curr' => 'THB', 'cm_tax_code' => 'VAT7', 'cm_tax_in_price' => false, 'cm_cr_limit' => '50000']));
ok('customer bad email rejected', $r[0] === 422 && isset($r[1]['fields']['cm_email']), $r[1]);
$r = $a->req('POST', '/api/customers', party('cm', ['cm_code' => 'c001', 'cm_name' => 'บริษัท ทดสอบ จำกัด', 'cm_status' => 'ACTIVE', 'cm_email' => 'a@b.test',
    'cm_province' => 'กรุงเทพมหานคร', 'cm_district' => $dist[0]['name'] ?? '', 'cm_sub_district' => $sub[0]['name'] ?? '', 'cm_zip' => $sub[0]['zip'] ?? '',
    'cm_terms' => '30DAYS', 'cm_curr' => 'THB', 'cm_tax_code' => 'VAT7', 'cm_cr_limit' => '50000']));
ok('create customer (defaults: country, branch)', $r[0] === 200 && $r[1]['data']['cm_country'] === 'Thailand' && $r[1]['data']['cm_tax_branch'] === '00000', $r[1]);
$r = $a->req('DELETE', '/api/credit-terms/row' . q(['ct_code' => '30DAYS']));
ok('cannot delete credit term used by a customer', $r[0] === 409, $r[1]);

// one-row screens
$r = $a->req('GET', '/api/company/row');
ok('company row', $r[0] === 200 && $r[1]['data']['co_name'] === 'E2E Test Co', $r[1]);
$r = $a->req('PUT', '/api/company/row', ['co_name' => 'บริษัท ไผ่ จำกัด', 'co_base_curr' => 'THB', 'co_email' => 'x@y.test']);
ok('save company', $r[0] === 200 && $r[1]['data']['co_name'] === 'บริษัท ไผ่ จำกัด', $r[1]);
$r = $a->req('PUT', '/api/company/row', ['co_idle_min' => '30']);
ok('idle sign-out minutes: saved, on every page (data-idle), told back by /api/me/alive', $r[0] === 200 && (int) one('SELECT co_idle_min FROM co_mstr') === 30
    && str_contains((string) $a->page('/')[1], 'data-idle="30"') && ($a->req('GET', '/api/me/alive')[1]['data']['idle'] ?? null) === 30, $r[1]);
$a->req('PUT', '/api/company/row', ['co_idle_min' => '0']);
// my profile: name, e-mail, phones and picture, from the menu under my name
$me = $a->req('GET', '/api/users/admin')[1]['data'] ?? [];
$r = $a->req('PUT', '/api/me/profile', ['us_name' => 'ผู้ดูแล ทดสอบ', 'us_email' => $me['us_email'] ?? 'admin@example.test', 'us_phone' => '02-000-0000', 'us_mobile' => '']);
ok('my profile: saved and shown in the top bar', $r[0] === 200 && one("SELECT us_name FROM us_mstr WHERE us_userid = 'admin'") === 'ผู้ดูแล ทดสอบ'
    && str_contains((string) $a->page('/')[1], 'ผู้ดูแล ทดสอบ'), $r[1]);
$png = 'data:image/png;base64,' . base64_encode((function () { $im = imagecreatetruecolor(32, 32); ob_start(); imagepng($im); return ob_get_clean(); })());
$r = $a->req('PUT', '/api/me/avatar', ['avatar' => $png]);
ok('my picture: stored, served to signed-in users, removable', $r[0] === 200 && !empty($r[1]['data']['url']) && $a->page('/avatar/admin')[0] === 200
    && $a->req('PUT', '/api/me/avatar', ['avatar' => 'data:image/svg+xml;base64,PHN2Zz4='])[0] === 422
    && $a->req('DELETE', '/api/me/avatar')[0] === 200 && $a->page('/avatar/admin')[0] === 404, $r[1]);
// theme: ไผ่ / ไผ่ยามค่ำ, kept with the user
ok('theme: dark kept with the user and on every page; unknown refused', $a->req('PUT', '/api/me/theme', ['theme' => 'dark'])[0] === 200
    && str_contains((string) $a->page('/')[1], 'data-bs-theme="dark"') && $a->req('PUT', '/api/me/theme', ['theme' => 'pink'])[0] === 422
    && $a->req('PUT', '/api/me/theme', ['theme' => 'light'])[0] === 200 && str_contains((string) $a->page('/')[1], 'data-bs-theme="light"'));
$page = fn () => (string) $a->page('/')[1];
ok('theme: a colour theme is light with its palette, and the menu has a dot for every theme', $a->req('PUT', '/api/me/theme', ['theme' => 'indigo'])[0] === 200
    && str_contains($p = $page(), 'data-bs-theme="light" data-phai-theme="indigo"') && substr_count($p, ' data-theme="') === 6
    && str_contains($p, 'phai-theme-dot active" data-theme="indigo"')
    && $a->req('PUT', '/api/me/theme', ['theme' => 'light'])[0] === 200 && !str_contains($page(), 'data-phai-theme'));
ok('language: English kept with the user — the page, its menu and the Thai flag to switch back; prints stay Thai; unknown refused',
    $a->req('PUT', '/api/me/lang', ['lang' => 'en'])[0] === 200
    && str_contains($p = $page(), '<html lang="en"') && str_contains($p, 'id="phai-lang" data-lang="th"') && str_contains($p, 'Purchasing')
    && str_contains((string) $a->page('/dashboard/' . date('Y-m-d') . '/print?html=1')[1], '<html lang="th"')
    && $a->req('PUT', '/api/me/lang', ['lang' => 'xx'])[0] === 422
    && $a->req('PUT', '/api/me/lang', ['lang' => 'th'])[0] === 200 && str_contains($p = $page(), '<html lang="th"') && str_contains($p, 'data-lang="en"'));
ok('company has no list/add/delete', $a->req('GET', '/api/company')[0] === 404 && $a->req('POST', '/api/company', [])[0] === 404);
$r = $a->req('PUT', '/api/settings/gl/row', ['gl_curr_year' => '2026', 'gl_curr_per' => '10', 'gl_base_curr' => 'THB', 'gl_ret_earn_acct' => '30301', 'gl_ytd_inc_acct' => 'NOPE']);
ok('gl settings validates account', $r[0] === 422 && isset($r[1]['fields']['gl_ytd_inc_acct']), $r[1]);

// numbering
$r = $a->req('PUT', '/api/settings/numbering', ['prefix_PO' => 'POR', 'next_PO' => '15', 'prefix_SO' => 'S1', 'next_SO' => '0']);
ok('numbering validation', $r[0] === 422 && isset($r[1]['fields']['prefix_PO'], $r[1]['fields']['prefix_SO'], $r[1]['fields']['next_SO']), $r[1]);
$r = $a->req('PUT', '/api/settings/numbering', ['prefix_PO' => 'px', 'next_PO' => '15']);
ok('numbering save (prefix: 2 letters, stored upper case)', $r[0] === 200 && one('SELECT po_prefix FROM po_ctrl') === 'PX', $r[1]);
ok('the former 3-letter prefixes are 2 letters now', one('SELECT so_inv_prefix FROM so_ctrl') === 'IV'
    && one('SELECT whtc_prefix FROM ap_ctrl') === 'WT');
ok('numbering: unknown format refused', ($r = $a->req('PUT', '/api/settings/numbering', ['format_PO' => 'XX']))[0] === 422 && isset($r[1]['fields']['format_PO']));
$r = $a->req('PUT', '/api/settings/numbering', ['format_PO' => 'Y5', 'next_PO' => '15']);
ok('numbering: format saved with its period', $r[0] === 200 && one('SELECT CONCAT(po_format, "/", po_next_nbr, "/", po_period) FROM po_ctrl') === 'Y5/15/' . date('Y')
    && str_contains((string) $a->page('/settings/numbering')[1], 'value="Y5" selected'));
$page = (string) $a->page('/settings/purchase')[1];
ok('each module\'s settings page has its documents\' numbering', str_contains($page, 'name="prefix_PO"') && !str_contains($page, 'name="prefix_SO"')
    && str_contains((string) $a->page('/settings/production')[1], 'name="prefix_WO"') && str_contains((string) $a->page('/settings/gl')[1], 'name="prefix_JE"'));
$a->req('POST', '/api/roles', ['role_code' => 'SETPUR', 'role_desc' => 'Purchase settings']);
$a->req('PUT', '/api/roles/SETPUR/perms', ['perms' => ['settings-purchase' => ['view', 'edit']]]);
$a->req('POST', '/api/users', ['us_userid' => 'numpur', 'us_name' => 'Purchase settings', 'us_email' => 'numpur@example.test', 'us_role' => 'SETPUR', 'password' => $pw]);
$np = login('numpur', $pw);
ok('purchase settings right: may number purchase documents', $np->req('PUT', '/api/settings/numbering', ['next_PR' => '7'])[0] === 200 && (int) one('SELECT po_pr_next_nbr FROM po_ctrl') === 7);
ok('...but not sales ones', $np->req('PUT', '/api/settings/numbering', ['next_SO' => '7'])[0] === 403 && (int) one('SELECT so_next_nbr FROM so_ctrl') !== 7);

// permissions: a role that may only view parts
$a->req('POST', '/api/roles', ['role_code' => 'VIEWER', 'role_desc' => 'ดูอย่างเดียว']);
$a->req('PUT', '/api/roles/VIEWER/perms', ['perms' => ['parts' => ['view']]]);
$a->req('POST', '/api/users', ['us_userid' => 'viewer', 'us_name' => 'Viewer', 'us_email' => 'viewer@example.test', 'us_role' => 'VIEWER', 'password' => $pw]);
$v = login('viewer', $pw);
ok('...an e-mail of another user is refused (my profile)', $a->req('PUT', '/api/me/profile', ['us_name' => 'ผู้ดูแล ทดสอบ', 'us_email' => 'viewer@example.test'])[0] === 422
    && one("SELECT us_userid FROM us_mstr WHERE us_email = 'viewer@example.test'") === 'viewer');
[$st, $html] = $v->page('/parts');
ok('viewer sees parts page without add/delete', $st === 200 && !str_contains($html, 'id="add"') && !str_contains($html, 'id="del"') && str_contains($html, '"edit":false'));
ok('viewer cannot add part', $v->req('POST', '/api/parts', $part)[0] === 403);
ok('viewer cannot open customers', $v->req('GET', '/api/customers')[0] === 403);
ok('viewer can use geo api', $v->req('GET', '/api/geo/provinces')[0] === 200);

// pt_ad_code: the customer of a finished good, the vendor of a raw material — found by the item list and lookup
$a->req('POST', '/api/customers', party('cm', ['cm_code' => 'CADC', 'cm_name' => 'ลูกค้ารหัสสินค้า', 'cm_status' => 'ACTIVE', 'cm_curr' => 'THB']));
$r = $a->req('POST', '/api/parts', ['pt_part' => 'ADC1', 'pt_desc1' => 'ของลูกค้า', 'pt_ad_code' => 'cadc', 'pt_um' => 'EA', 'pt_prod_line' => 'FG']);
ok('item ad code: a customer for a finished good, searchable', $r[0] === 200 && one("SELECT pt_ad_code FROM pt_mstr WHERE pt_part='ADC1'") === 'CADC'
    && in_array('ADC1', array_column($a->req('GET', '/api/parts?q=CADC')[1]['data']['rows'] ?? [], 'pt_part'), true)
    && in_array('ADC1', array_column($a->req('GET', '/api/stock/parts?q=CADC')[1]['data'] ?? [], 'pt_part'), true), $r[1]);
$r = $a->req('POST', '/api/parts', ['pt_part' => 'ADC2', 'pt_desc1' => 'วัตถุดิบ', 'pt_ad_code' => 'CADC', 'pt_um' => 'EA', 'pt_prod_line' => 'RM']);
ok('item ad code: a raw material needs a vendor', $r[0] === 422 && isset($r[1]['fields']['pt_ad_code']), $r[1]);

// Excel import: add + update in one go, sheet values (active words, serial dates, "CODE · name"), all or nothing
$r = $a->req('POST', '/api/uoms/import', ['rows' => [
    ['_row' => 5, 'um_um' => 'xbox', 'um_desc' => 'กล่องใหญ่', 'um_active' => 'ใช้งาน'],
    ['_row' => 6, 'um_um' => 'EA', 'um_desc' => 'ชิ้น (นำเข้า)', 'um_active' => 'ไม่ใช้งาน'],
]]);
ok('import adds and updates', $r[0] === 200 && $r[1]['data'] === ['created' => 1, 'updated' => 1]
    && one("SELECT um_active FROM um_mstr WHERE um_um='XBOX'") == 1 && one("SELECT um_active FROM um_mstr WHERE um_um='EA'") == 0
    && one("SELECT created_by FROM um_mstr WHERE um_um='XBOX'") === 'admin', $r[1]);
$r = $a->req('POST', '/api/uoms/import', ['rows' => [
    ['_row' => 2, 'um_um' => 'YBOX', 'um_desc' => 'ใหม่'],
    ['_row' => 3, 'um_um' => 'ZBOX', 'um_desc' => str_repeat('ยาว', 100)],
]]);
ok('import error names the row and saves nothing', $r[0] === 422 && count($r[1]['fields']) === 1
    && str_contains(array_key_first($r[1]['fields']), '3') && one("SELECT COUNT(*) FROM um_mstr WHERE um_um='YBOX'") == 0, $r[1]);
$r = $a->req('POST', '/api/exchange-rates/import', ['rows' => [['ex_curr' => 'USD · ดอลลาร์', 'ex_date' => 46300, 'ex_rate' => 33.25]]]);
ok('import serial date and lookup label', $r[0] === 200 && one("SELECT ex_rate FROM ex_mstr WHERE ex_curr='USD' AND ex_date='2026-10-05'") == 33.25, $r[1]);
ok('viewer cannot import', $v->req('POST', '/api/parts/import', ['rows' => [['pt_part' => 'V1']]])[0] === 403);
ok('import needs rows', $a->req('POST', '/api/uoms/import', ['rows' => []])[0] === 422);

// customer / vendor address in the parts the Revenue Department forms ask for; the printed line is made from them
$r = $a->req('POST', '/api/customers', party('cm', ['cm_code' => 'ADDR1', 'cm_name' => 'ที่อยู่แยกช่อง', 'cm_status' => 'ACTIVE', 'cm_curr' => 'THB',
    'cm_house_no' => '12/3', 'cm_moo' => '4', 'cm_building' => 'อาคารบี', 'cm_floor' => '7', 'cm_room' => '701', 'cm_village' => 'สวนสวย',
    'cm_soi' => 'ลาดพร้าว 1', 'cm_yaek' => '2', 'cm_road' => 'ถนนพหลโยธิน', 'cm_line2' => 'ตรงข้ามตลาด']));
ok('customer address parts make the printed line', $r[0] === 200
    && one("SELECT cm_line1 FROM cm_mstr WHERE cm_code = 'ADDR1'") === '12/3 หมู่ 4 อาคารบี ชั้น 7 ห้อง 701 หมู่บ้าน สวนสวย ซอย ลาดพร้าว 1 แยก 2 ถนนพหลโยธิน', $r[1]);
$r = $a->req('PUT', '/api/customers/row?cm_code=ADDR1', ['cm_name' => 'ชื่อใหม่']);
ok('an update without the address leaves the printed line alone', $r[0] === 200 && one("SELECT cm_line1 FROM cm_mstr WHERE cm_code = 'ADDR1'") === '12/3 หมู่ 4 อาคารบี ชั้น 7 ห้อง 701 หมู่บ้าน สวนสวย ซอย ลาดพร้าว 1 แยก 2 ถนนพหลโยธิน', $r[1]);
$r = $a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'ADDR2', 'vd_name' => 'ผู้ขายแยกช่อง', 'vd_status' => 'ACTIVE', 'vd_curr' => 'THB', 'vd_house_no' => '5', 'vd_road' => 'สุขุมวิท']));
ok('vendor address parts too, the road word added', $r[0] === 200 && one("SELECT vd_line1 FROM vd_mstr WHERE vd_code = 'ADDR2'") === '5 ถนน สุขุมวิท', $r[1]);
// a master record's files (Resource::$files): the box in its dialog, the paperclip in the list, gone with the record
$a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'FILES1', 'vd_name' => 'ผู้ขายมีไฟล์', 'vd_status' => 'ACTIVE']));
$png1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
$r = $a->req('POST', '/api/files/VD/FILES1', ['name' => 'pp20.png', 'data' => $png1]);
ok('vendor: a file attached, its dialog has the box, the list counts it', $r[0] === 200
    && str_contains((string) $a->page('/vendors')[1], 'data-files="VD"')
    && ($a->req('GET', '/api/files/VD?nbrs=FILES1')[1]['data']['FILES1'] ?? 0) === 1, $r[1]);
ok('...deleting the vendor deletes its files', $a->req('DELETE', '/api/vendors/row?vd_code=FILES1')[0] === 200
    && (int) one("SELECT COUNT(*) FROM att_det WHERE att_doc = 'VD' AND att_nbr = 'FILES1'") === 0);
$r = $a->req('POST', '/api/vendors', ['vd_code' => 'NOTAX', 'vd_name' => 'ไม่มีเลขภาษี', 'vd_status' => 'ACTIVE', 'vd_tax_id_num' => '123']);
ok('a Thai vendor needs what later documents use: tax ID (13 digits), address, terms, currency, VAT', $r[0] === 422
    && isset($r[1]['fields']['vd_tax_id_num'], $r[1]['fields']['vd_province'], $r[1]['fields']['vd_zip'], $r[1]['fields']['vd_terms'], $r[1]['fields']['vd_curr'], $r[1]['fields']['vd_tax_code']), $r[1]);
$r = $a->req('POST', '/api/vendors', ['vd_code' => 'ABROAD', 'vd_name' => 'Overseas Co', 'vd_status' => 'ACTIVE', 'vd_country' => 'Japan', 'vd_terms' => 'COD', 'vd_curr' => 'USD', 'vd_tax_code' => 'VAT0']);
ok('a vendor abroad needs no Thai tax ID or address', $r[0] === 200, $r[1]);
$r = $a->req('PUT', '/api/customers/row?cm_code=ADDR1', ['cm_tax_id_num' => '']);
ok('the tax ID cannot be emptied later', $r[0] === 422 && isset($r[1]['fields']['cm_tax_id_num']), $r[1]);

$r = $a->req('POST', '/api/sites', ['si_site' => 'br2', 'si_desc' => 'สาขาเชียงใหม่', 'si_status' => true, 'si_house_no' => '88', 'si_moo' => '2',
    'si_road' => 'ถนนห้วยแก้ว', 'si_province' => 'เชียงใหม่', 'si_district' => 'เมืองเชียงใหม่', 'si_sub_district' => 'สุเทพ', 'si_zip' => '50200']);
ok('site / warehouse address in parts', $r[0] === 200 && one("SELECT si_line1 FROM si_mstr WHERE si_site = 'BR2'") === '88 หมู่ 2 ถนนห้วยแก้ว'
    && one("SELECT si_zip FROM si_mstr WHERE si_site = 'BR2'") === '50200' && str_contains((string) $a->page('/sites')[1], 'si_house_no'), $r[1]);

finish();
