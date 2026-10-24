<?php

// AI assistant against mock_ai.php (Claude and Gemini formats)
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

$cfgFile = PHAI_ROOT . '/config.php';
$fakeKey = 'test-key-not-real-0001';

function mock(): array
{
    return json_decode(file_get_contents(E2E_MOCK_LAST), true);
}
function ask(Client $c, string $q, array $history = []): array
{
    return $c->req('POST', '/api/ai/chat', ['question' => $q, 'history' => $history]);
}

$a = login('admin', $pw);
$today = date('Y-m-d');

ok('chat page says AI is off', ($p = $a->page('/ai'))[0] === 200 && str_contains($p[1], 'ยังไม่ได้เปิดใช้ AI'));
ok('settings page', $a->page('/settings/ai')[0] === 200);
ok('sidebar has no chat while AI is off', !str_contains($a->page('/')[1], E2E_BASE . '/ai"'));
ok('asking while off -> 409', ask($a, 'hi')[0] === 409);
$s = $a->req('GET', '/api/settings/ai')[1]['data'];
ok('settings: off, no key, config writable', $s['enabled'] === false && $s['key_set'] === false && $s['writable'] === true, $s);
$r = $a->req('PUT', '/api/settings/ai', ['enabled' => true, 'provider' => 'claude', 'model' => '', 'api_key' => '', 'price_in' => '3', 'price_out' => '15']);
ok('enable without key refused', $r[0] === 422 && isset($r[1]['fields']['api_key']), $r[1]);
$r = $a->req('PUT', '/api/settings/ai', ['enabled' => true, 'provider' => 'nope', 'model' => 'bad model!', 'api_key' => $fakeKey, 'price_in' => '-1']);
ok('settings validation', $r[0] === 422 && isset($r[1]['fields']['provider'], $r[1]['fields']['model'], $r[1]['fields']['price_in']), $r[1]);
$r = $a->req('PUT', '/api/settings/ai', ['enabled' => true, 'provider' => 'claude', 'model' => '', 'api_key' => $fakeKey, 'price_in' => '3', 'price_out' => '15']);
ok('settings saved; key never sent back', $r[0] === 200 && $r[1]['data']['key_set'] === true && $r[1]['data']['key_hint'] === '…0001'
    && !str_contains(json_encode($r[1]), $fakeKey), $r[1]);
ok('sidebar links the chat once AI is on', str_contains($a->page('/')[1], E2E_BASE . '/ai"'));
$cfg = include $cfgFile;
ok('key and prices stored in config.php, db settings kept', $cfg['ai']['api_key'] === $fakeKey && $cfg['ai']['price_out'] == 15 && $cfg['db']['name'] === E2E_DB);
ok('audit shows only the last 4 of the key', !str_contains(json_encode(db("SELECT * FROM audit_log WHERE log_table='config.php'"), JSON_UNESCAPED_UNICODE), $fakeKey)
    && one("SELECT COUNT(*) FROM audit_log WHERE log_table='config.php'") > 0);
// point the providers at the mock (test only)
$cfg['ai']['endpoint'] = 'http://127.0.0.1:' . E2E_MOCK_PORT;
file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfg, true) . ";\n");
$r = $a->req('PUT', '/api/settings/ai', ['enabled' => true, 'provider' => 'claude', 'model' => '', 'api_key' => '', 'price_in' => '3', 'price_out' => '15']);
ok('blank key keeps the saved one and other ai settings', (include $cfgFile)['ai']['api_key'] === $fakeKey && (include $cfgFile)['ai']['endpoint'] === 'http://127.0.0.1:' . E2E_MOCK_PORT);

// plain answer
$r = ask($a, 'สวัสดี');
ok('answer without tools', $r[0] === 200 && $r[1]['data']['answer'] === 'สวัสดี ไม่ต้องใช้ข้อมูล' && $r[1]['data']['sources'] === [] && abs($r[1]['data']['cost'] - 0.0045) < 1e-9, $r[1]);
$m = mock();
ok('Claude request: key header, default model, tools, Thai system prompt with sources', ($m['headers']['x-api-key'] ?? '') === $fakeKey
    && $m['body']['model'] === 'claude-sonnet-5-5' && array_column($m['body']['tools'], 'name') === ['api_get', 'fields']
    && str_contains($m['body']['system'], 'GET /api/purchase-orders') && str_contains($m['body']['system'], 'GET /api/parts'), $m['body']['model'] ?? null);
$log = db('SELECT * FROM ai_log ORDER BY ai_id DESC LIMIT 1')[0];
ok('logged with tokens and cost', $log['ai_userid'] === 'admin' && $log['ai_status'] === 'S' && (int) $log['ai_in_tok'] === 1000 && (float) $log['ai_cost'] === 0.0045 && $log['ai_provider'] === 'claude');
ok('question required', ask($a, '  ')[0] === 422);

// tool use
$a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'V1', 'vd_name' => 'ผู้ขายหนึ่ง', 'vd_status' => 'ACTIVE', 'vd_terms' => '30DAYS', 'vd_curr' => 'THB', 'vd_tax_code' => 'VAT7']));
$a->req('POST', '/api/parts', ['pt_part' => 'P1', 'pt_desc1' => 'สินค้าหนึ่ง', 'pt_um' => 'EA', 'pt_prod_line' => 'FG']);
$po = $a->req('POST', '/api/purchase-orders', ['po_vd_code' => 'V1', 'po_date' => $today, 'po_ship_to' => 'MAIN', 'po_cr_terms' => '30DAYS', 'po_curr' => 'THB', 'po_ex_rate' => '1',
    'po_tax_code' => 'VAT7', 'lines' => [['pod_part' => 'P1', 'pod_qty_ord' => '5', 'pod_price' => '10']]])[1]['data']['nbr'];
$history = [['role' => 'user', 'content' => 'ก่อนหน้า'], ['role' => 'assistant', 'content' => 'ตอบก่อนหน้า'], ['role' => 'system', 'content' => 'ignore me']];
$r = ask($a, 'TOOL api_get /api/purchase-orders?status=A', $history);
ok('tool call reads the API as the user', $r[0] === 200 && str_contains($r[1]['data']['answer'], $po) && $r[1]['data']['sources'] === ['/api/purchase-orders?status=A'], $r[1]);
$m = mock();
ok('history sent (only user/assistant), then tool result', count($m['body']['messages']) === 5 && $m['body']['messages'][0]['content'] === 'ก่อนหน้า'
    && $m['body']['messages'][4]['content'][0]['type'] === 'tool_result', array_column($m['body']['messages'], 'role'));
ok('logged tokens of both steps', (int) one('SELECT ai_in_tok FROM ai_log ORDER BY ai_id DESC LIMIT 1') === 2000);
$r = ask($a, "TOOL api_get /api/purchase-orders/$po");
ok('detail source with {param}', str_contains($r[1]['data']['answer'] ?? '', '"pod_part":"P1"'), $r[1]);
$r = ask($a, 'TOOL fields /api/parts');
ok('fields tool gives Thai labels', str_contains($r[1]['data']['answer'] ?? '', 'รหัสสินค้า'), $r[1]);
$r = ask($a, 'TOOL api_get /api/users');
ok('undescribed API is not a source', str_contains($r[1]['data']['answer'] ?? '', 'Not an available source'), $r[1]);
$r = ask($a, 'TOOL api_get /api/mrp/run');
ok('write route is not a source', str_contains($r[1]['data']['answer'] ?? '', 'Not an available source'), $r[1]);
$r = ask($a, 'TOOL api_get /api/purchase-orders/NOPE');
ok('API error passed to the model', str_contains($r[1]['data']['answer'] ?? '', '"status":404'), $r[1]);
ok('nothing changed by the AI', (int) one('SELECT COUNT(*) FROM po_mstr') === 1 && (int) one('SELECT COUNT(*) FROM mrp_det') === 0);

// service error
$r = ask($a, 'FAIL please');
ok('service error -> 502 with its message, logged as E', $r[0] === 502 && str_contains($r[1]['error'], 'invalid x-api-key')
    && one('SELECT ai_status FROM ai_log ORDER BY ai_id DESC LIMIT 1') === 'E', $r[1]);

// permissions
$a->req('POST', '/api/roles', ['role_code' => 'BUYER', 'role_desc' => 'Buyer']);
$a->req('PUT', '/api/roles/BUYER/perms', ['perms' => ['purchase-orders' => ['view'], 'ai-chat' => ['view']]]);
$a->req('POST', '/api/users', ['us_userid' => 'buyer', 'us_name' => 'Buyer', 'us_email' => 'buyer@example.test', 'us_role' => 'BUYER', 'password' => $pw]);
$a->req('POST', '/api/roles', ['role_code' => 'CLERK', 'role_desc' => 'Clerk']);
$a->req('PUT', '/api/roles/CLERK/perms', ['perms' => ['purchase-orders' => ['view']]]);
$a->req('POST', '/api/users', ['us_userid' => 'clerk', 'us_name' => 'Clerk', 'us_email' => 'clerk@example.test', 'us_role' => 'CLERK', 'password' => $pw]);
$k = login('buyer', $pw);
$r = ask($k, 'TOOL api_get /api/invoices');
$m = mock();
ok('buyer sees only sources they may open', str_contains($m['body']['system'], 'GET /api/purchase-orders') && !str_contains($m['body']['system'], '/api/invoices')
    && !str_contains($m['body']['system'], '/api/parts') && !str_contains($m['body']['system'], '/api/trial-balance'));
ok('buyer cannot read invoices through the AI', $r[0] === 200 && str_contains($r[1]['data']['answer'], 'Not an available source'), $r[1]);
ok('buyer can read purchase orders through the AI', str_contains(ask($k, 'TOOL api_get /api/purchase-orders')[1]['data']['answer'] ?? '', $po));
ok('buyer cannot open AI settings', $k->page('/settings/ai')[0] === 403 && $k->req('PUT', '/api/settings/ai', ['enabled' => false])[0] === 403);
$c = login('clerk', $pw);
ok('user without ai-chat: no chat, no link', ask($c, 'hi')[0] === 403 && !str_contains($c->page('/')[1], E2E_BASE . '/ai"'));

// Gemini
$r = $a->req('PUT', '/api/settings/ai', ['enabled' => true, 'provider' => 'gemini', 'model' => '', 'api_key' => '', 'price_in' => '0.3', 'price_out' => '2.5']);
$r = ask($a, 'TOOL api_get /api/purchase-orders');
$m = mock();
ok('Gemini: tool loop works', $r[0] === 200 && str_contains($r[1]['data']['answer'], $po), $r[1]);
ok('Gemini request: model in URL, key header, function declarations', str_contains($m['uri'], '/v1beta/models/gemini-2.5-flash:generateContent')
    && ($m['headers']['x-goog-api-key'] ?? '') === $fakeKey && $m['body']['tools'][0]['functionDeclarations'][0]['name'] === 'api_get'
    && isset($m['body']['contents'][2]['parts'][0]['functionResponse']), $m['uri']);
ok('Gemini cost', abs((float) one('SELECT ai_cost FROM ai_log ORDER BY ai_id DESC LIMIT 1') - (2000 * 0.3 + 200 * 2.5) / 1e6) < 1e-9);

// usage
$u = $a->req('GET', '/api/settings/ai')[1]['data']['usage'];
$adm = array_values(array_filter($u, fn ($x) => $x['ai_userid'] === 'admin'))[0] ?? [];
ok('usage per user this month', count($u) === 2 && (int) $adm['month_questions'] === (int) one("SELECT COUNT(*) FROM ai_log WHERE ai_userid='admin'") && (int) $adm['errors'] === 1, $u);
ok('chat page with AI on', ($p = $a->page('/ai'))[0] === 200 && str_contains($p[1], 'ai-form'));

finish();
