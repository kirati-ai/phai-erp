<?php

// Security: login and permission on every route, CSRF, SQL injection, XSS, files, sessions. @demo
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

$routes = routes();

/** Raw request returning [status, headers, body]. */
$fill = fn (string $path) => preg_replace('/\{\w+\}/', 'X', $path);
$open = ['/api/geo/provinces', '/api/geo/districts/{pv_id}', '/api/geo/subdistricts/{am_id}', '/api/rate', '/api/logout', '/api/me/password', '/api/me/alive', '/api/me/theme', '/api/me/lang', '/api/me/pins', '/dashboard/{date}/print', '/api/me/profile', '/api/me/avatar', '/avatar/{userid}', '/',
    // module overviews (menu *): each panel shows only what the user may see, like the home page
    '/purchase', '/inventory', '/production', '/sales', '/accounting',
    // teaching material, like the help pages
    '/learn',
    // my own signature and my signing list; what may be signed is checked on the document's print right (13-signatures)
    '/signatures', '/signatures/{id}', '/api/signatures', '/api/signatures/people', '/api/signatures/sign', '/api/signatures/request',
    '/api/signatures/reject', '/api/signatures/withdraw', '/api/me/sign',
    // envelopes: anyone may be asked to sign; an envelope shows only to those in it (404 otherwise), making one needs
    // the add right on "envelopes", and the check page answers anyone signed in (14-envelopes); asking Phai Sign for
    // results is open to anyone signed in but runs at most every 30 s for them (the settings are ADMIN only, 15-outsiders)
    '/envelopes', '/envelopes/verify', '/envelopes/{nbr}', '/envelopes/{nbr}/edit', '/envelopes/{nbr}/sign', '/envelopes/{nbr}/files/{id}',
    '/envelopes/{nbr}/final', '/api/envelopes', '/api/envelopes/people', '/api/envelopes/verify', '/api/envelopes/{nbr}',
    '/api/envelopes/{nbr}/files', '/api/envelopes/{nbr}/files/{id}', '/api/envelopes/{nbr}/send', '/api/envelopes/{nbr}/sign',
    '/api/envelopes/{nbr}/decline', '/api/envelopes/{nbr}/recall', '/api/envelopes/{nbr}/reopen', '/api/envelopes/{nbr}/remind', '/api/envelopes/{nbr}/replace',
    '/api/envelopes/sync'];

$a = login('admin', $pw);
$a->req('POST', '/api/roles', ['role_code' => 'NOPERM', 'role_desc' => 'No rights']);
$a->req('POST', '/api/users', ['us_userid' => 'nobody', 'us_name' => 'Nobody', 'us_email' => 'nobody@example.test', 'us_role' => 'NOPERM', 'password' => $pw]);
$a->req('POST', '/api/roles', ['role_code' => 'VIEWPO', 'role_desc' => 'PO viewer']);
$a->req('PUT', '/api/roles/VIEWPO/perms', ['perms' => ['purchase-orders' => ['view']]]);
$a->req('POST', '/api/users', ['us_userid' => 'viewer', 'us_name' => 'Viewer', 'us_email' => 'viewer@example.test', 'us_role' => 'VIEWPO', 'password' => $pw]);

// ------------------------------------------------------------------ 1. login required everywhere
$anon = new Client($B);
$anon->page('/login'); // anonymous session with a CSRF token
$bad = [];
foreach ($routes as $r) {
    if ($r['guest'] || $r['path'] === '/api/logout') continue;
    [$s, , $loc] = $anon->req($r['method'], $fill($r['path']), $r['method'] === 'GET' ? null : []);
    $isApi = str_starts_with($r['path'], '/api/');
    // JSON clients get 401 for pages too; browsers are redirected (checked below)
    if ($s !== 401) $bad[] = "$s {$r['method']} {$r['path']}";
}
ok('every route needs a login', !$bad, array_slice($bad, 0, 10));
[$s, $h] = raw('GET', "$B/purchase-orders", ['Accept: text/html']);
ok('a browser is sent to the login page', $s === 302 && str_contains(implode("\n", $h), '/login?next='));

// ------------------------------------------------------------------ 2. permissions on every route
$n = login('nobody', $pw);
$bad = [];
$allowed = [];
foreach ($routes as $r) {
    if ($r['guest'] || in_array($r['path'], $open, true)) continue;
    [$s] = $n->req($r['method'], $fill($r['path']), $r['method'] === 'GET' ? null : []);
    if ($s !== 403) $bad[] = "$s {$r['method']} {$r['path']}";
}
ok('user without rights gets 403 on all ' . count($routes) . ' routes except login-only ones', !$bad, array_slice($bad, 0, 10));
ok('lookups need a related screen (vendors, customers, prices, stock)', $n->req('GET', '/api/purchase/vendors')[0] === 403
    && $n->req('GET', '/api/sales/customers')[0] === 403 && $n->req('GET', '/api/sales/price?part=FG-SHELF')[0] === 403 && $n->req('GET', '/api/stock/parts')[0] === 403);
$v = login('viewer', $pw);
ok('view right on purchase orders: can read and use the vendor lookup', $v->req('GET', '/api/purchase-orders')[0] === 200 && $v->req('GET', '/api/purchase/vendors')[0] === 200);
$po = one('SELECT po_nbr FROM po_mstr ORDER BY po_nbr DESC LIMIT 1');
ok('...but cannot add, edit, cancel or print', $v->req('POST', '/api/purchase-orders', [])[0] === 403 && $v->req('PUT', "/api/purchase-orders/$po", [])[0] === 403
    && $v->req('POST', "/api/purchase-orders/$po/cancel")[0] === 403
    && $v->page("/purchase-orders/$po/print")[0] === 403);
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
$file = $a->req('POST', "/api/files/PO/$po", ['name' => 'quote.png', 'data' => $png])[1]['data']['att_id'] ?? 0;
ok('files go by the rights of their document: the viewer opens the order\'s files but cannot attach or remove; nothing of another screen',
    $v->req('GET', "/api/files/PO/$po")[0] === 200 && $v->page("/files/PO/$po/$file")[0] === 200
    && $v->req('POST', "/api/files/PO/$po", ['name' => 'x.png', 'data' => $png])[0] === 403 && $v->req('DELETE', "/api/files/PO/$po/$file")[0] === 403
    && $v->req('GET', '/api/files/AP?nbrs=X')[0] === 403 && $v->page("/files/PO/$po/$file")[0] === 200);
$a->req('DELETE', "/api/files/PO/$po/$file");
[$s, $flow] = $v->page('/purchase');
// the purchase order right opens the orders and their vendor schedules (ScheduleController shares it), nothing else
preg_match_all('~class="card phai-flow-card" href="' . preg_quote(E2E_BASE, '~') . '([^"]*)"~', (string) $flow, $cards);
ok('...the purchase overview has cards only for the screens it may open, and no queue it may not act on',
    $s === 200 && $cards[1] === ['/purchase-orders', '/schedules/po'] && !str_contains((string) $flow, 'data-flow='), [$s, $cards[1]]);
[$s, $flow] = $a->page('/purchase');
ok('admin sees a card per purchase screen, with what waits for each', $s === 200 && substr_count((string) $flow, 'phai-flow-card') >= 5
    && str_contains((string) $flow, '/api/purchase-orders/waiting') && str_contains((string) $flow, '/api/receipts/waiting'));
ok('...and the accounting overview splits its cards into cash and bank, AP, AR, GL and FA', substr_count((string) $a->page('/accounting')[1], 'phai-flow-title') === 5);
ok('...and nothing else', $v->req('GET', '/api/sales-orders')[0] === 403 && $v->req('GET', '/api/sales/customers')[0] === 403 && $v->req('GET', '/api/users')[0] === 403);
ok('cannot give yourself rights', $v->req('PUT', '/api/roles/VIEWPO/perms', ['perms' => ['users' => ['view', 'add', 'edit']]])[0] === 403
    && $v->req('PUT', '/api/users/viewer', ['us_role' => 'ADMIN'])[0] === 403 && one("SELECT us_role FROM us_mstr WHERE us_userid='viewer'") === 'VIEWPO');

// ------------------------------------------------------------------ 3. CSRF
$cookie = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($a->cookies), $a->cookies));
$json = ['Accept: application/json', 'Content-Type: application/json', $cookie];
$body = json_encode(['um_um' => 'CSRF', 'um_desc' => 'x', 'um_active' => true]);
ok('write without CSRF token -> 403', raw('POST', "$B/api/uoms", $json, $body)[0] === 403);
ok('write with a wrong token -> 403', raw('POST', "$B/api/uoms", [...$json, 'X-CSRF-Token: ' . str_repeat('a', 64)], $body)[0] === 403);
ok('PUT / DELETE without token -> 403', raw('PUT', "$B/api/uoms/row?um_um=EA", $json, $body)[0] === 403 && raw('DELETE', "$B/api/uoms/row?um_um=EA", $json)[0] === 403);
ok('form post with _csrf of another session -> 403', raw('POST', "$B/api/uoms", ['Accept: application/json', 'Content-Type: application/json', $cookie],
    json_encode(['_csrf' => $n->token] + json_decode($body, true)))[0] === 403);
ok('nothing was written', !one("SELECT 1 FROM um_mstr WHERE um_um='CSRF'"));
ok('login itself needs the token', raw('POST', "$B/api/login", ['Accept: application/json', 'Content-Type: application/json'], json_encode(['login' => 'admin', 'password' => $pw]))[0] === 403);

// ------------------------------------------------------------------ 4. SQL injection
$parts = (int) one('SELECT COUNT(*) FROM pt_mstr'); // the sample data's, before the attempts
$inject = ['q' => "' OR 1=1 -- ", 'sort' => "pt_part; DROP TABLE pt_mstr; --", 'dir' => 'desc; --', 'page' => '-1 OR 1=1', 'per_page' => '99999',
    'status' => "' OR '1'='1", 'site' => "' OR '1'='1", 'from' => "2020-01-01' OR '1'='1", 'to' => "x'", 'part' => "' OR 1=1 #", 'acct' => "' OR 1=1 #",
    'customer' => "' OR 1=1 #", 'vendor' => "' OR 1=1 #", 'by' => "part'", 'type' => "' OR 1=1 #"];
$errors = [];
$leaks = [];
$checked = 0;
foreach ($routes as $r) {
    if ($r['method'] !== 'GET' || !str_starts_with($r['path'], '/api/')) continue;
    $path = str_contains($r['path'], '{') ? preg_replace('/\{\w+\}/', rawurlencode("X' OR '1'='1"), $r['path']) : $r['path'];
    [$s, $res] = $a->req('GET', $path . '?' . http_build_query($inject));
    $checked++;
    if ($s >= 500) $errors[] = "$s {$r['path']}";
    if (!in_array($r['path'], ['/api/trial-balance', '/api/roles', '/api/petty-cash/month'], true) && !str_contains($r['path'], '{') && is_array($res['data']['rows'] ?? null) && count($res['data']['rows']) > 0 && isset($res['data']['total'])) $leaks[] = $r['path'];
    if (str_contains($r['path'], '{') && $s === 200 && !empty($res['data']['head'])) $leaks[] = $r['path'];
}
ok("no server error on $checked read APIs with injection payloads", !$errors, $errors);
ok('injected search matches nothing', !$leaks, $leaks);
[$s, $res] = $a->req('GET', '/api/parts?' . http_build_query(['q' => "' OR '1'='1", 'sort' => '(SELECT SLEEP(3))']));
ok('resource search is a literal match, unknown sort ignored', $s === 200 && $res['data']['total'] === 0);
ok('tables intact after the attempts', (int) one("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='" . E2E_DB . "'") === preg_match_all('/^CREATE TABLE/mi', file_get_contents(PHAI_ROOT . '/database/schema/phaierp_schema.sql')) && (int) one('SELECT COUNT(*) FROM pt_mstr') === $parts);
$r = $a->req('POST', '/api/login', ['login' => "admin' -- ", 'password' => 'x']);
ok('login with injected user id fails normally', $r[0] === 422);

// ------------------------------------------------------------------ 5. stored XSS
$x1 = '<script>alert("x1")</script>';
$x2 = '"><img src=x onerror=alert(2)>';
$a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'XSS', 'vd_name' => $x1, 'vd_name2' => $x2, 'vd_status' => 'ACTIVE', 'vd_terms' => 'COD', 'vd_curr' => 'THB', 'vd_tax_code' => 'VAT7', 'vd_line2' => $x2]));
$a->req('POST', '/api/customers', party('cm', ['cm_code' => 'XSS', 'cm_name' => $x1, 'cm_status' => 'ACTIVE', 'cm_terms' => 'COD', 'cm_curr' => 'THB', 'cm_tax_code' => 'VAT7', 'cm_cr_limit' => '0', 'cm_line2' => $x2]));
$a->req('POST', '/api/parts', ['pt_part' => 'XSS-1', 'pt_desc1' => $x2, 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_part_type' => 'P', 'pt_price' => '1']);
$xpo = $a->req('POST', '/api/purchase-orders', ['po_vd_code' => 'XSS', 'po_date' => date('Y-m-d'), 'po_ship_to' => 'MAIN', 'po_curr' => 'THB', 'po_ex_rate' => '1', 'po_tax_code' => 'VAT7',
    'po_remarks' => $x1, 'lines' => [['pod_part' => 'XSS-1', 'pod_qty_ord' => '1', 'pod_price' => '1', 'pod_desc' => $x2]]])[1]['data']['nbr'] ?? '';
$xqt = $a->req('POST', '/api/quotations', ['qt_cm_code' => 'XSS', 'qt_date' => date('Y-m-d'), 'qt_expire' => date('Y-m-d', strtotime('+7 days')), 'qt_curr' => 'THB', 'qt_ex_rate' => '1',
    'qt_tax_code' => 'VAT7', 'qt_remarks' => $x1, 'lines' => [['qtd_part' => 'XSS-1', 'qtd_qty' => '1', 'qtd_price' => '1']]])[1]['data']['nbr'] ?? '';
ok('XSS samples saved as given', $xpo !== '' && $xqt !== '' && one("SELECT vd_name FROM vd_mstr WHERE vd_code='XSS'") === $x1);
$raw = [];
foreach (['/vendors', '/customers', '/parts', '/purchase-orders', "/purchase-orders/$xpo", "/purchase-orders/$xpo/print", '/quotations', "/quotations/$xqt", "/quotations/$xqt/print",
          '/payables/new', '/vouchers/new', '/stock', '/trial-balance', '/ai', '/settings/ai', '/'] as $pg) {
    [$s, $html] = $a->page($pg);
    if ($s !== 200 || str_contains((string) $html, $x1) || str_contains((string) $html, '<img src=x')) $raw[] = "$s $pg";
}
ok('no page prints the payloads unescaped', !$raw, $raw);
ok('print page shows the text escaped', str_contains($a->page("/purchase-orders/$xpo/print?html=1")[1], '&lt;script&gt;alert(&quot;x1&quot;)&lt;/script&gt;'));
$next1 = $a->req('POST', '/api/login', ['login' => 'admin', 'password' => $pw, 'next' => '//evil.example/x'])[1]['data']['next'] ?? '';
$a->page('/'); // new session, new CSRF token
$next = $a->req('POST', '/api/login', ['login' => 'admin', 'password' => $pw, 'next' => 'javascript:alert(1)'])[1]['data']['next'] ?? '';
$a->page('/');
ok('login "next" cannot leave the site', $next1 === E2E_BASE . '/' && $next === E2E_BASE . '/', [$next1, $next]);

// ------------------------------------------------------------------ 6. files and headers
$leak = [];
foreach (['/config.php', '/config.example.php', '/src/bootstrap.php', '/src/Core/App.php', '/database/schema/phaierp_schema.sql', '/database/seeds/30_system.sql',
          '/bin/phai', '/storage/logs/', '/.htaccess', '/CLAUDE.md', '/lang/th/ai.php', '/views/layouts/app.php', '/tests/run.php', '/public/../config.php', '/%2e%2e/config.php'] as $f) {
    [$s, , $out] = raw('GET', $B . $f);
    if ($s === 200 || str_contains($out, '<?php') || str_contains($out, 'CREATE TABLE') || str_contains($out, "'db'")) $leak[] = "$s $f";
}
ok('source, config, schema and logs are not served', !$leak, $leak);
[$s, $h] = raw('GET', "$B/login");
$hs = implode("\n", $h);
ok('security headers', str_contains($hs, 'X-Content-Type-Options: nosniff') && str_contains($hs, 'X-Frame-Options: SAMEORIGIN') && str_contains($hs, 'Referrer-Policy: same-origin'));
ok('session cookie is HttpOnly and SameSite', (bool) preg_match('/Set-Cookie: phaierp=[^;]+;.*HttpOnly/i', $hs) && (bool) preg_match('/Set-Cookie: phaierp=.*SameSite=Lax/i', $hs));
$c = new Client($B);
$c->page('/login');
$before = $c->cookies['phaierp'] ?? '';
$c->req('POST', '/api/login', ['login' => 'admin', 'password' => $pw]);
$c->page('/');
ok('new session id after login (no fixation)', $before !== '' && ($c->cookies['phaierp'] ?? '') !== $before);
$c->req('POST', '/api/logout');
ok('logout ends the session', $c->req('GET', '/api/purchase-orders')[0] === 401);

// ------------------------------------------------------------------ 7. passwords and accounts
$u = json_encode($a->req('GET', '/api/users')[1]) . json_encode($a->req('GET', '/api/users/admin')[1]);
ok('password hashes never leave the server', !str_contains($u, 'password_hash') && !str_contains($u, '$2y$') && !str_contains($u, '$argon'));
ok('stored as a hash', str_starts_with((string) one("SELECT us_password_hash FROM us_mstr WHERE us_userid='admin'"), '$2y$') || str_starts_with((string) one("SELECT us_password_hash FROM us_mstr WHERE us_userid='admin'"), '$argon'));
$a->req('POST', '/api/users', ['us_userid' => 'locked', 'us_name' => 'Lock', 'us_email' => 'locked@example.test', 'us_role' => 'VIEWPO', 'password' => $pw]);
$l = new Client($B);
$l->page('/login');
for ($i = 0; $i < 5; $i++) $l->req('POST', '/api/login', ['login' => 'locked', 'password' => 'wrong-password']);
ok('locked after 5 wrong passwords, even with the right one', $l->req('POST', '/api/login', ['login' => 'locked', 'password' => $pw])[0] === 429);
$r = $a->req('POST', '/api/parts', ['pt_part' => 'MASS', 'pt_desc1' => 'x', 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_part_type' => 'P', 'created_by' => 'hacker', 'created_at' => '2000-01-01']);
ok('system columns cannot be set from outside', $r[0] === 200 && one("SELECT created_by FROM pt_mstr WHERE pt_part='MASS'") === 'admin');
ok('short passwords refused', $a->req('POST', '/api/users', ['us_userid' => 'weak', 'us_name' => 'W', 'us_email' => 'weak@example.test', 'us_role' => 'VIEWPO', 'password' => 'short'])[0] === 422);
$cfg = include PHAI_ROOT . '/config.php';
ok('installed with debug off', $cfg['app']['debug'] === false);

// ------------------------------------------------------------------ 8. menus
ok('every menu link opens a page', ($m = $a->req('GET', '/api/menus?per_page=200'))[0] === 200 && !array_filter(
    $m[1]['data']['rows'], fn ($row) => $row['mnu_link'] !== '#' && $a->page($row['mnu_link'])[0] !== 200
), $m[1]['data']['total'] ?? null);
$r = $a->req('PUT', '/api/menus/row?mnu_menu=1&mnu_code=1.11', ['mnu_name' => 'สินค้าและบริการ']);
ok('menu can be renamed', $r[0] === 200 && str_contains($a->page('/')[1], 'สินค้าและบริการ'), $r[1]);
ok('version shown', str_contains($a->page('/')[1], 'PhaiERP 1.0.0'));

finish();
