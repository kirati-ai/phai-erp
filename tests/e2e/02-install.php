<?php

// Installer wizard steps and the company logo (installs its own copy; run through tests/e2e/run.php).

declare(strict_types=1);

require __DIR__ . '/lib.php';

/** A real PNG of the given size, as a data URL. */
function png(int $w, int $h): string
{
    $raw = '';
    for ($y = 0; $y < $h; $y++) {
        $raw .= "\0" . str_repeat("\x2f\x9e\x44", $w);
    }
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)) . $chunk('IDAT', gzcompress($raw)) . $chunk('IEND', '');
    return 'data:image/png;base64,' . base64_encode($png);
}

// run.php installed with the defaults; start again from the wizard
@unlink(PHAI_ROOT . '/config.php');
pdo(null)->exec('DROP DATABASE IF EXISTS `' . E2E_DB . '`');
$logoDir = PHAI_ROOT . '/storage/uploads/' . E2E_DB;
foreach (glob("$logoDir/logo.*") ?: [] as $f) {
    unlink($f);
}

$c = new Client(E2E_URL);
[$s, $html] = $c->page('/install');
ok('wizard page with five steps', $s === 200 && substr_count((string) $html, 'class="step-item') === 5 && str_contains((string) $html, 'id="logo-drop"'));

$db = ['db_host' => E2E_DB_HOST, 'db_port' => '3306', 'db_name' => E2E_DB, 'db_user' => E2E_DB_USER, 'db_pass' => E2E_DB_PASS];
ok('database step: wrong name refused', $c->req('POST', '/api/install/db', ['db_name' => 'bad name!'] + $db)[0] === 422);
ok('database step: ok', ($r = $c->req('POST', '/api/install/db', $db))[0] === 200 && $r[1]['data']['exists'] === false, $r[1]);

$r = $c->req('POST', '/api/install/check', $db + ['step' => 'company']);
ok('company step: name required', $r[0] === 422 && isset($r[1]['fields']['co_name']), $r[1]);
$bad = [
    'not an image'      => 'data:image/png;base64,' . base64_encode('<?php echo 1; ?>'),
    'svg'               => 'data:image/svg+xml;base64,' . base64_encode('<svg onload="alert(1)"/>'),
    'png claimed jpeg'  => str_replace('image/png', 'image/jpeg', png(32, 32)),
    'too small'         => png(4, 4),
    'too big (> 1 MB)'  => 'data:image/png;base64,' . base64_encode(str_repeat('x', 1100000)),
];
foreach ($bad as $why => $url) {
    $r = $c->req('POST', '/api/install/check', $db + ['step' => 'company', 'co_name' => 'X', 'co_logo' => $url]);
    ok("logo refused: $why", $r[0] === 422 && isset($r[1]['fields']['co_logo']), $r[1]);
}
$logo = png(240, 120);
ok('company step: ok with logo', $c->req('POST', '/api/install/check', $db + ['step' => 'company', 'co_name' => 'X', 'co_logo' => $logo])[0] === 200);
$r = $c->req('POST', '/api/install/check', $db + ['step' => 'admin', 'us_userid' => 'a b', 'us_email' => 'nope', 'password' => 'short']);
ok('admin step: checks user id, e-mail, password', $r[0] === 422 && isset($r[1]['fields']['us_userid'], $r[1]['fields']['us_email'], $r[1]['fields']['password']), $r[1]);
$r = $c->req('POST', '/api/install/check', $db + ['step' => 'admin', 'us_userid' => 'admin', 'us_email' => 'a@example.test', 'password' => $pw]);
ok('admin step: name required', $r[0] === 422 && array_keys($r[1]['fields']) === ['us_name'], $r[1]);
ok('nothing installed by the checks', !is_file(PHAI_ROOT . '/config.php') && !pdo(null)->query("SHOW DATABASES LIKE '" . E2E_DB . "'")->fetch());

$r = $c->req('POST', '/api/install/run', $db + ['co_name' => 'X', 'co_logo' => 'data:image/png;base64,AAAA', 'us_userid' => 'admin', 'us_name' => 'A', 'us_email' => 'a@example.test', 'password' => $pw]);
ok('install with a bad logo stops before creating anything', $r[0] === 422 && isset($r[1]['fields']['co_logo']) && !pdo(null)->query("SHOW DATABASES LIKE '" . E2E_DB . "'")->fetch());

// address pickers before there is a database
$pv = $c->req('GET', '/api/install/geo/provinces')[1]['data'] ?? [];
$bkk = array_values(array_filter($pv, fn ($p) => $p['name'] === 'กรุงเทพมหานคร'))[0] ?? null;
$am = $bkk ? $c->req('GET', '/api/install/geo/districts/' . $bkk['id'])[1]['data'] : [];
$pathum = array_values(array_filter($am, fn ($a) => str_contains($a['name'], 'ปทุมวัน')))[0] ?? null;
$tb = $pathum ? $c->req('GET', '/api/install/geo/subdistricts/' . $pathum['id'])[1]['data'] : [];
ok('address pickers work before install (77 provinces, districts, sub-districts with zip)', count($pv) === 77 && count($am) > 40 && $tb && $tb[0]['zip'] === '10330', [count($pv), count($am), $tb[0] ?? null]);
ok('bad zip refused', ($r = $c->req('POST', '/api/install/check', $db + ['step' => 'company', 'co_name' => 'X', 'ad_zip' => '<b>']))[0] === 422 && isset($r[1]['fields']['ad_zip']));

$r = $c->req('POST', '/api/install/run', $db + [
    'co_name' => 'บริษัท โลโก้ทดสอบ จำกัด', 'co_tax_id' => '0105500000000', 'co_phone' => '02-000-0000',
    'ad_line1' => '1 ถนนทดสอบ', 'ad_state' => $bkk['name'], 'ad_city' => $pathum['name'], 'ad_sub_district' => $tb[0]['name'], 'ad_zip' => $tb[0]['zip'],
    'co_logo' => $logo, 'us_userid' => 'admin', 'us_name' => 'Admin', 'us_email' => 'admin@example.test', 'password' => $pw,
]);
ok('installed', $r[0] === 200 && $r[1]['data']['config_written'] === true, $r[1]);
$co = db('SELECT * FROM co_mstr')[0];
$ad = db("SELECT * FROM ad_mstr WHERE ad_addr = 'COMPANY'")[0] ?? [];
ok('company details and logo saved', $co['co_phone'] === '02-000-0000' && $co['co_logo_path'] === 'logo.png' && is_file("$logoDir/logo.png"));
ok('company address in ad_mstr, printed form in co_address', ($ad['ad_state'] ?? '') === 'กรุงเทพมหานคร' && $ad['ad_zip'] === '10330' && $ad['ad_name'] === $co['co_name']
    && $co['co_address'] === "1 ถนนทดสอบ {$tb[0]['name']} {$pathum['name']} กรุงเทพมหานคร 10330", [$ad, $co['co_address']]);

[$s, $h, $body] = raw('GET', E2E_URL . '/logo');
ok('logo served as an image, without login', $s === 200 && str_contains(implode("\n", $h), 'Content-Type: image/png') && str_starts_with($body, "\x89PNG"));
ok('storage itself is not served', raw('GET', E2E_URL . '/storage/uploads/' . E2E_DB . '/logo.png')[0] !== 200);
ok('login page shows the logo', str_contains((string) (new Client(E2E_URL))->page('/login')[1], '/logo?v='));

$a = login('admin', $pw);
ok('company screen has the logo and address cards', str_contains((string) ($p = $a->page('/company')[1]), 'id="co-logo-change"') && str_contains((string) $p, 'id="company-address"'));
ok('company address read', ($r = $a->req('GET', '/api/company/address'))[0] === 200 && $r[1]['data']['ad_zip'] === '10330' && $r[1]['data']['text'] === $co['co_address']);
ok('an address from before the parts: house number taken from the line, the rest kept', $r[1]['data']['ad_house_no'] === '1' && $r[1]['data']['ad_line2'] === 'ถนนทดสอบ', $r[1]['data']);
$r = $a->req('PUT', '/api/company/address', ['ad_house_no' => '2', 'ad_moo' => '4', 'ad_building' => 'อาคารเอ', 'ad_floor' => '5', 'ad_room' => '501',
    'ad_village' => 'สวนสวย', 'ad_soi' => '3', 'ad_yaek' => '1', 'ad_road' => 'ถนนใหม่', 'ad_line2' => '',
    'ad_state' => 'เชียงใหม่', 'ad_city' => 'เมืองเชียงใหม่', 'ad_sub_district' => 'ศรีภูมิ', 'ad_zip' => '50200']);
ok('company address in parts; one printed line made from them', $r[0] === 200
    && one('SELECT co_address FROM co_mstr') === '2 หมู่ 4 อาคารเอ ชั้น 5 ห้อง 501 หมู่บ้าน สวนสวย ซอย 3 แยก 1 ถนนใหม่ ศรีภูมิ เมืองเชียงใหม่ เชียงใหม่ 50200'
    && one("SELECT ad_road FROM ad_mstr WHERE ad_addr='COMPANY'") === 'ถนนใหม่' && $r[1]['data']['ad_moo'] === '4', $r[1]);
ok('address checked', ($r = $a->req('PUT', '/api/company/address', ['ad_zip' => str_repeat('9', 30)]))[0] === 422 && isset($r[1]['fields']['ad_zip']));

// dashboard
[$s, $home] = $a->page('/');
ok('dashboard for a new system: greeting, figures, getting-started steps', $s === 200 && str_contains((string) $home, 'phai-hero') && substr_count((string) $home, 'phai-kpi ') === 8
    && str_contains((string) $home, 'phai-start') && !str_contains((string) $home, 'phai-kpi" href'));
ok('dashboard prints on A4 (PDF)', $a->page('/dashboard/' . date('Y-m-d') . '/print?html=1')[0] === 200 && str_contains((string) $a->page('/dashboard/' . date('Y-m-d') . '/print?html=1')[1], 'dash-page'));
ok('printed documents carry the logo', (function () use ($a) {
    $a->req('POST', '/api/customers', party('cm', ['cm_code' => 'C1', 'cm_name' => 'C', 'cm_status' => 'ACTIVE', 'cm_curr' => 'THB', 'cm_tax_code' => 'VAT7']));
    $a->req('POST', '/api/parts', ['pt_part' => 'P1', 'pt_desc1' => 'P', 'pt_um' => 'EA', 'pt_prod_line' => 'FG']);
    $qt = $a->req('POST', '/api/quotations', ['qt_cm_code' => 'C1', 'qt_date' => date('Y-m-d'), 'qt_expire' => date('Y-m-d', strtotime('+7 days')), 'qt_curr' => 'THB',
        'qt_ex_rate' => '1', 'lines' => [['qtd_part' => 'P1', 'qtd_qty' => '1', 'qtd_price' => '1']]])[1]['data']['nbr'] ?? '';
    return str_contains((string) $a->page("/quotations/$qt/print?html=1")[1], 'class="doc-logo"') && str_contains((string) $a->page("/quotations/$qt/print?html=1")[1], 'src="data:image/');
})());
$qt1 = one('SELECT MIN(qt_nbr) FROM qt_mstr');
ok('new installs number documents by year and month: QT' . date('Ym') . '0001', one('SELECT so_qt_format FROM so_ctrl') === 'YM4' && $qt1 === 'QT' . date('Ym') . '0001', $qt1);
// pretend the last quotation was in an earlier month
pdo()->exec('DELETE FROM qtd_det');
pdo()->exec('DELETE FROM qt_mstr');
pdo()->exec("UPDATE so_ctrl SET so_qt_period = '202001', so_qt_next_nbr = 57");
$qt2r = $a->req('POST', '/api/quotations', ['qt_cm_code' => 'C1', 'qt_date' => date('Y-m-d'), 'qt_expire' => date('Y-m-d', strtotime('+7 days')), 'qt_curr' => 'THB',
    'qt_ex_rate' => '1', 'lines' => [['qtd_part' => 'P1', 'qtd_qty' => '1', 'qtd_price' => '1']]]);
$qt2 = $qt2r[1]['data']['nbr'] ?? '';
ok('a new month starts again at 0001', $qt2 === 'QT' . date('Ym') . '0001' && (int) one('SELECT so_qt_next_nbr FROM so_ctrl') === 2, $qt2r);
$r = $a->req('PUT', '/api/company/logo', ['logo' => 'data:image/svg+xml;base64,' . base64_encode('<svg/>')]);
ok('company screen refuses a bad logo', $r[0] === 422 && isset($r[1]['fields']['logo']));
$r = $a->req('PUT', '/api/company/logo', ['logo' => str_replace('image/png', 'image/webp', png(64, 64))]);
ok('...and a mislabelled one', $r[0] === 422);
$jpeg = (function () {
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $im = imagecreatetruecolor(80, 40);
    ob_start();
    imagejpeg($im);
    return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
})();
$r = $a->req('PUT', '/api/company/logo', ['logo' => $jpeg ?? png(80, 40)]);
ok('change the logo', $r[0] === 200 && str_contains((string) $r[1]['data']['url'], '/logo?v=') && count(glob("$logoDir/logo.*")) === 1, $r[1]);
ok('remove the logo', $a->req('DELETE', '/api/company/logo')[0] === 200 && raw('GET', E2E_URL . '/logo')[0] === 404 && !glob("$logoDir/logo.*")
    && one('SELECT co_logo_path FROM co_mstr') === null);
$a->req('POST', '/api/roles', ['role_code' => 'VIEWCO', 'role_desc' => 'Company viewer']);
$a->req('PUT', '/api/roles/VIEWCO/perms', ['perms' => ['company' => ['view']]]);
$a->req('POST', '/api/users', ['us_userid' => 'viewer', 'us_name' => 'V', 'us_email' => 'v@example.test', 'us_role' => 'VIEWCO', 'password' => $pw]);
$v = login('viewer', $pw);
ok('changing the logo needs edit rights', $v->req('PUT', '/api/company/logo', ['logo' => png(64, 64)])[0] === 403 && $v->req('DELETE', '/api/company/logo')[0] === 403
    && !str_contains((string) $v->page('/company')[1], 'id="co-logo-change"'));
ok('...and so does the address (reading is fine)', $v->req('PUT', '/api/company/address', ['ad_zip' => '10000'])[0] === 403 && $v->req('GET', '/api/company/address')[0] === 200);
[$s, $home] = $v->page('/');
ok('dashboard shows only what the user may open', $s === 200 && !str_contains((string) $home, 'phai-kpi ') && !str_contains((string) $home, 'phai-start')
    && !str_contains((string) $home, '/sales-orders/new'));

finish();
