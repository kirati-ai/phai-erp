<?php

// Inventory: moves, counts, lots, costing, stock view
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

function oh(string $part, string $loc = 'STOCK', string $lot = '', string $site = 'MAIN'): float
{
    $r = db("SELECT ld_qty_oh FROM ld_det WHERE ld_site='$site' AND ld_loc='$loc' AND ld_part='$part' AND ld_lot='$lot'");
    return (float) ($r[0]['ld_qty_oh'] ?? 0);
}

$a = login('admin', $pw);
$today = date('Y-m-d');

// setup: parts, a second location and warehouse
$a->req('POST', '/api/parts', ['pt_part' => 'P1', 'pt_desc1' => 'สินค้าธรรมดา', 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_cost_method' => 'AVG']);
$a->req('POST', '/api/parts', ['pt_part' => 'L1', 'pt_desc1' => 'สินค้าคุมล็อต', 'pt_um' => 'KG', 'pt_prod_line' => 'FG', 'pt_lot_ser' => 'L']);
$a->req('POST', '/api/locations', ['loc_site' => 'MAIN', 'loc_loc' => 'A1', 'loc_type' => 'M', 'loc_status' => 'ACTIVE']);
$a->req('POST', '/api/sites', ['si_site' => 'WH2', 'si_desc' => 'คลังสอง', 'si_default_loc' => 'S2', 'si_status' => true]);
$a->req('POST', '/api/locations', ['loc_site' => 'WH2', 'loc_loc' => 'S2', 'loc_type' => 'M', 'loc_status' => 'ACTIVE']);

foreach (['/stock', '/stock/moves', '/stock/history', '/stock-counts', '/settings/inventory'] as $p) {
    ok("page $p", $a->page($p)[0] === 200);
}

// validation
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => '2099-01-01', 'site' => 'MAIN', 'lines' => [
    '0' => ['part' => 'NOPE', 'loc' => 'STOCK', 'qty' => '5'],
    '3' => ['part' => 'L1', 'loc' => 'ZZ', 'qty' => '-1'],
    '4' => ['part' => '', 'qty' => ''],
]]);
$f = $r[1]['fields'] ?? [];
ok('receipt validation: future date, bad part, bad loc, qty > 0 (lot may be left to the document number), blank row ignored',
    $r[0] === 422 && isset($f['date'], $f['lines.0.part'], $f['lines.3.loc'], $f['lines.3.qty']) && !isset($f['lines.4.part'], $f['lines.3.lot']), $f);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'adjust', 'date' => $today, 'site' => 'MAIN', 'reason' => 'DMG', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '1']]]);
ok('no adjust kind (decrease = issue, increase = receipt)', $r[0] === 422 && isset($r[1]['fields']['kind']), $r[1]);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'reason' => 'DMG', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '1']]]);
ok('receipt rejects an issue reason', $r[0] === 422 && isset($r[1]['fields']['reason']), $r[1]);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => []]);
ok('no lines', $r[0] === 422 && isset($r[1]['fields']['lines']), $r[1]);

// receipts with average cost
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'reason' => 'OPEN', 'remark' => 'ยอดยกมา', 'lines' => [
    ['part' => 'p1', 'loc' => 'stock', 'qty' => '100', 'cost' => '10'],
    ['part' => 'L1', 'loc' => 'STOCK', 'lot' => 'LOT-A', 'qty' => '50', 'expire' => '2027-01-31'],
]]);
ok('receipt posted with IC number', $r[0] === 200 && $r[1]['data']['nbr'] === 'IC000001' && $r[1]['data']['lines'] === 2, $r[1]);
ok('balances after receipt', oh('P1') == 100 && oh('L1', 'STOCK', 'LOT-A') == 50);
ok('lot created with expiry', (db("SELECT lot_expire_date FROM lot_mstr WHERE lot_part='L1' AND lot_lot='LOT-A'")[0]['lot_expire_date'] ?? '') === '2027-01-31');
$a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '100', 'cost' => '20']]]);
ok('average cost (100@10 + 100@20 = 15)', (float) db("SELECT pt_cost FROM pt_mstr WHERE pt_part='P1'")[0]['pt_cost'] === 15.0);

// issue: not enough stock rolls back the whole document
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'MAIN', 'reason' => 'USE', 'lines' => [
    '0' => ['part' => 'P1', 'loc' => 'STOCK', 'qty' => '10'],
    '1' => ['part' => 'P1', 'loc' => 'STOCK', 'qty' => '500'],
]]);
ok('issue short -> error on that line', $r[0] === 422 && isset($r[1]['fields']['lines.1.qty']), $r[1]);
ok('nothing posted, number not used', oh('P1') == 200 && db("SELECT ic_tr_next_nbr n FROM ic_ctrl")[0]['n'] == 3);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'MAIN', 'reason' => 'USE', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '30']]]);
ok('issue posted', $r[0] === 200 && oh('P1') == 170, $r[1]);
$hist = db("SELECT tr_chg_qty, tr_cost, tr_rsn_code, tr_type FROM tr_hist WHERE tr_nbr = '{$r[1]['data']['nbr']}'");
ok('issue history: negative qty at average cost with reason', $hist && (float) $hist[0]['tr_chg_qty'] === -30.0 && (float) $hist[0]['tr_cost'] === 15.0 && $hist[0]['tr_rsn_code'] === 'USE', $hist);

// correcting stock: an issue with a reason takes away, a receipt (into an existing lot) adds
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'MAIN', 'reason' => 'DMG', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '5']]]);
$r2 = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'L1', 'loc' => 'STOCK', 'lot' => 'LOT-A', 'qty' => '2.5']]]);
ok('correct - and +', $r[0] === 200 && $r2[0] === 200 && oh('P1') == 165 && oh('L1', 'STOCK', 'LOT-A') == 52.5, [$r[1], $r2[1]]);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'MAIN', 'reason' => 'DMG', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '0']]]);
ok('issue 0 rejected', $r[0] === 422 && isset($r[1]['fields']['lines.0.qty']), $r[1]);

// no transfer screen: moving stock to another warehouse = issue here, receive there
ok('no transfer page or API', $a->page('/transfers')[0] === 404 && $a->req('POST', '/api/transfers', ['date' => $today])[0] === 404);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'MAIN', 'lines' => [
    ['part' => 'P1', 'loc' => 'STOCK', 'qty' => '65'],
    ['part' => 'L1', 'loc' => 'STOCK', 'lot' => 'LOT-A', 'qty' => '52.5'],
]]);
$r2 = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'WH2', 'lines' => [
    ['part' => 'P1', 'loc' => 'S2', 'qty' => '65', 'cost' => '15'],
    ['part' => 'L1', 'loc' => 'S2', 'lot' => 'LOT-A', 'qty' => '52.5', 'expire' => '2027-01-31'],
]]);
ok('issue from one warehouse, receive into another', $r[0] === 200 && $r2[0] === 200 && oh('P1') == 100 && oh('P1', 'S2', '', 'WH2') == 65, [$r[1], $r2[1]]);
ok('emptied lot row removed', !db("SELECT 1 FROM ld_det WHERE ld_site='MAIN' AND ld_part='L1'"));

// balances & history API
$r = $a->req('GET', '/api/stock?site=MAIN&q=P1');
ok('balance list', $r[0] === 200 && $r[1]['data']['total'] === 1 && (float) $r[1]['data']['rows'][0]['ld_qty_oh'] === 100.0, $r[1]);
$r = $a->req('GET', '/api/stock?by=part&sort=ld_qty_oh&dir=desc');
ok('balance totals per part and warehouse', $r[0] === 200 && $r[1]['data']['total'] === 3, $r[1]['data'] ?? $r);
$r = $a->req('GET', '/api/stock/history?part=P1&site=MAIN');
ok('history newest first', $r[0] === 200 && $r[1]['data']['total'] === 5 && $r[1]['data']['rows'][0]['tr_type'] === 'ISS-UNP', $r[1]['data']['rows'][0] ?? $r);
$r = $a->req('GET', '/api/stock/history?type=RCT-UNP&from=' . $today . '&to=' . $today);
ok('history filter by type and dates', $r[0] === 200 && $r[1]['data']['total'] === 6); // 2 + 1 receipts, + 1 into LOT-A, + 2 into WH2
$r = $a->req('GET', '/api/stock/onhand?part=P1&site=WH2');
ok('onhand lookup', $r[0] === 200 && count($r[1]['data']) === 1 && $r[1]['data'][0]['loc'] === 'S2');

// stock count
$r = $a->req('POST', '/api/stock-counts', ['site' => 'WH2', 'date' => $today, 'desc' => 'ทดสอบ']);
$batch = $r[1]['data']['pi_batch'] ?? '';
ok('count created', $r[0] === 200 && $batch === 'PI000001', $r[1]);
$waitingPi = fn () => array_column($a->req('GET', '/api/stock-counts/waiting')[1]['data'] ?? [], 'pi_batch');
ok('queue: open count waits on the counts page', in_array($batch, $waitingPi(), true) && str_contains((string) $a->page('/stock-counts')[1], 'data-queue'), $waitingPi());
$c = $a->req('GET', "/api/stock-counts/$batch")[1]['data'];
ok('snapshot has 2 tags', count($c['tags']) === 2 && $c['tags'][0]['pit_count_qty'] === null);
$r = $a->req('POST', "/api/stock-counts/$batch/post");
ok('cannot post before counting', $r[0] === 422, $r[1]);
$tagP1 = array_values(array_filter($c['tags'], fn ($t) => $t['pit_part'] === 'P1'))[0]['pit_tag'];
$tagL1 = array_values(array_filter($c['tags'], fn ($t) => $t['pit_part'] === 'L1'))[0]['pit_tag'];
$r = $a->req('PUT', "/api/stock-counts/$batch/counts", ["count_$tagP1" => '60', "count_$tagL1" => 'abc']);
ok('bad count rejected', $r[0] === 422 && isset($r[1]['fields']["count_$tagL1"]), $r[1]);
// the tagged stock is frozen while it is counted: nothing comes in or goes out until the count is posted
$sysP1 = (float) array_values(array_filter($c['tags'], fn ($t) => $t['pit_part'] === 'P1'))[0]['pit_sys_qty'];
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'WH2', 'lines' => [['part' => 'P1', 'loc' => 'S2', 'qty' => '3']]]);
ok('stock being counted is frozen', $r[0] !== 200 && str_contains(json_encode($r[1], JSON_UNESCAPED_UNICODE), $batch) && oh('P1', 'S2', '', 'WH2') == $sysP1, $r[1]);
$r = $a->req('PUT', "/api/stock-counts/$batch/counts", ["count_$tagP1" => '60', "count_$tagL1" => '52.5']);
$p1 = array_values(array_filter($r[1]['data'] ?? [], fn ($t) => $t['pit_tag'] == $tagP1))[0] ?? [];
ok('counts saved with their variance', $r[0] === 200 && (float) $p1['pit_variance'] === round(60 - $sysP1, 5), $r[1]);
$r = $a->req('POST', "/api/stock-counts/$batch/tags", ['part' => 'P1', 'loc' => 'S2', 'qty' => '1']);
ok('duplicate found tag rejected', $r[0] === 422 && isset($r[1]['fields']['part']), $r[1]);
$a->req('POST', '/api/locations', ['loc_site' => 'WH2', 'loc_loc' => 'S3', 'loc_type' => 'M', 'loc_status' => 'ACTIVE']);
$r = $a->req('POST', "/api/stock-counts/$batch/tags", ['part' => 'P1', 'loc' => 'S3', 'qty' => '7']);
ok('found item added', $r[0] === 200 && count($r[1]['data']) === 3, $r[1]);
// tags: the counter's name as written on them; a tag that is off is counted again, the first count kept
$r = $a->req('PUT', "/api/stock-counts/$batch/counts", ["count_$tagP1" => '60', "count_$tagL1" => '52.5', 'counter' => 'สมชาย นับดี']);
ok('an unchanged count keeps its first counter', $r[0] === 200 && one("SELECT pit_counter FROM pi_tag WHERE pit_batch='$batch' AND pit_tag=$tagP1") !== 'สมชาย นับดี');
$r = $a->req('POST', "/api/stock-counts/$batch/tags/$tagP1/recount");
ok('recount reopens the tag and keeps the first count', $r[0] === 200 && one("SELECT pit_status FROM pi_tag WHERE pit_batch='$batch' AND pit_tag=$tagP1") === 'O'
    && (float) one("SELECT pit_count1 FROM pi_tag WHERE pit_batch='$batch' AND pit_tag=$tagP1") === 60.0, $r[1]);
$r = $a->req('POST', "/api/stock-counts/$batch/post");
ok('cannot post while a tag is out: its number is named', $r[0] === 422 && str_contains((string) ($r[1]['error'] ?? ''), (string) $tagP1), $r[1]);
$r = $a->req('PUT', "/api/stock-counts/$batch/counts", ["count_$tagP1" => '60', 'counter' => 'สมหญิง ตรวจซ้ำ']);
ok('second count by another counter', $r[0] === 200 && one("SELECT pit_counter FROM pi_tag WHERE pit_batch='$batch' AND pit_tag=$tagP1") === 'สมหญิง ตรวจซ้ำ');
// a tag printed for a place that turned out empty is voided, never dropped: its number stays on the list
$a->req('POST', '/api/locations', ['loc_site' => 'WH2', 'loc_loc' => 'S4', 'loc_type' => 'M', 'loc_status' => 'ACTIVE']);
$r = $a->req('POST', "/api/stock-counts/$batch/tags", ['part' => 'P1', 'loc' => 'S4', 'qty' => '0']);
$tagX = max(array_column($r[1]['data'] ?? [[]], 'pit_tag'));
$a->req('POST', "/api/stock-counts/$batch/tags/$tagX/void", ['void' => true]);
$a->req('POST', "/api/stock-counts/$batch/tags/$tagX/void", ['void' => false]);
$r = $a->req('POST', "/api/stock-counts/$batch/tags/$tagX/void", ['void' => true]);
$x = array_values(array_filter($r[1]['data'] ?? [], fn ($t) => $t['pit_tag'] == $tagX))[0] ?? [];
ok('void tag: kept on the list as voided', $r[0] === 200 && ($x['pit_status'] ?? '') === 'V' && count($r[1]['data']) === 4, $r[1]);
[$s, $html] = $a->page("/stock-counts/$batch/tags/print?html=1");
ok('tags print: one card per tag in use, no system quantity (blind count)', $s === 200 && substr_count((string) $html, 'class="tagc"') === 3
    && !str_contains((string) $html, 'ยอดระบบ'), $s);
ok('count sheet lists the voided tag', str_contains((string) $a->page("/documents/stock-counts/$batch/print?html=1")[1], 'ยกเลิก'));
$r = $a->req('POST', "/api/stock-counts/$batch/post");
ok('count posted: 2 adjustments', $r[0] === 200 && $r[1]['data']['adjusted'] === 2, $r[1]);
ok('queue: posted count leaves the queue', !in_array($batch, $waitingPi(), true));
ok('stock follows the count', oh('P1', 'S2', '', 'WH2') == 60 && oh('P1', 'S3', '', 'WH2') == 7 && oh('L1', 'S2', 'LOT-A', 'WH2') == 52.5);
ok('posted: the stock moves again', $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'WH2', 'lines' => [['part' => 'P1', 'loc' => 'S2', 'qty' => '1']]])[0] === 200
    && $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'WH2', 'lines' => [['part' => 'P1', 'loc' => 'S2', 'qty' => '1']]])[0] === 200);
ok('posted count is closed', $a->req('PUT', "/api/stock-counts/$batch/counts", ["count_$tagP1" => '1'])[0] === 409 && $a->req('DELETE', "/api/stock-counts/$batch")[0] === 409);

// a cycle count: only the locations chosen are tagged and frozen, the rest of the warehouse keeps moving
$a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'WH2', 'lines' => [['part' => 'P1', 'loc' => 'S4', 'qty' => '2']]]);
ok('scope: an unknown location refused', $a->req('POST', '/api/stock-counts', ['site' => 'WH2', 'date' => $today, 'locs' => ['NOPE']])[0] === 422);
$cc = $a->req('POST', '/api/stock-counts', ['site' => 'WH2', 'date' => $today, 'desc' => 'นับหมุนเวียน', 'locs' => ['s3']])[1]['data']['pi_batch'] ?? '';
$ct = $a->req('GET', "/api/stock-counts/$cc")[1]['data'] ?? [];
ok('cycle count tags only the chosen location', ($ct['head']['pi_locs'] ?? '') === 'S3' && $ct['tags'] && !array_filter($ct['tags'], fn ($t) => $t['pit_loc'] !== 'S3'), $ct);
ok('outside the scope stock still moves, inside it is frozen',
    $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'WH2', 'lines' => [['part' => 'P1', 'loc' => 'S4', 'qty' => '1']]])[0] === 200
    && $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'WH2', 'lines' => [['part' => 'P1', 'loc' => 'S3', 'qty' => '1']]])[0] !== 200);
$r = $a->req('POST', "/api/stock-counts/$cc/tags", ['part' => 'L1', 'loc' => 'S4', 'qty' => '1']);
ok('a found item outside the scope is refused', $r[0] === 422 && isset($r[1]['fields']['loc']), $r[1]);
$a->req('DELETE', "/api/stock-counts/$cc");
ok('deleting the count unfreezes', $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'WH2', 'lines' => [['part' => 'P1', 'loc' => 'S3', 'qty' => '1']]])[0] === 200);

// opening stock through a count: reason OPEN, items found with their cost, booked against retained earnings
$a->req('POST', '/api/sites', ['si_site' => 'WH3', 'si_desc' => 'คลังใหม่', 'si_default_loc' => 'N1', 'si_status' => true]);
$a->req('POST', '/api/locations', ['loc_site' => 'WH3', 'loc_loc' => 'N1', 'loc_type' => 'M', 'loc_status' => 'ACTIVE']);
$a->req('POST', '/api/parts', ['pt_part' => 'OP1', 'pt_desc1' => 'ยอดยกมา', 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_cost_method' => 'AVG']);
ok('count reason must exist', $a->req('POST', '/api/stock-counts', ['site' => 'WH3', 'date' => $today, 'reason' => 'NOPE'])[0] === 422);
$ob = $a->req('POST', '/api/stock-counts', ['site' => 'WH3', 'date' => $today, 'desc' => 'ยอดยกมา', 'reason' => 'OPEN'])[1]['data']['pi_batch'] ?? '';
$r = $a->req('POST', "/api/stock-counts/$ob/tags", ['part' => 'OP1', 'loc' => 'N1', 'qty' => '10', 'cost' => '7']);
$r2 = $a->req('POST', "/api/stock-counts/$ob/tags", ['part' => 'L1', 'loc' => 'N1', 'qty' => '3', 'cost' => '2']);
ok('found items with cost; a lot left empty = the count number', $r[0] === 200 && $r2[0] === 200
    && one("SELECT pit_lot FROM pi_tag WHERE pit_batch = '$ob' AND pit_part = 'L1'") === $ob, [$r[1], $r2[1]]);
$r = $a->req('POST', "/api/stock-counts/$ob/post");
ok('opening posted: stock at the given cost, booked against OPEN (retained earnings)', $r[0] === 200 && oh('OP1', 'N1', '', 'WH3') == 10
    && (float) one("SELECT tr_cost FROM tr_hist WHERE tr_nbr = '$ob' AND tr_part = 'OP1'") === 7.0
    && (float) one("SELECT pt_cost FROM pt_mstr WHERE pt_part = 'OP1'") === 7.0
    && (float) one("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_ref = '$ob' AND gl_acct = (SELECT rs_acct FROM rs_mstr WHERE rs_code = 'OPEN')") === -76.0, $r[1]);

// negative stock setting
$a->req('PUT', '/api/settings/inventory/row', ['ic_neg_qty' => true]);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '150']]]);
ok('negative stock allowed when switched on', $r[0] === 200 && oh('P1') == -50, $r[1]);
$a->req('PUT', '/api/settings/inventory/row', ['ic_neg_qty' => false]);

// permissions: a stock clerk who may view balances and post moves, nothing else
$a->req('POST', '/api/roles', ['role_code' => 'STOCK', 'role_desc' => 'Stock clerk']);
$a->req('PUT', '/api/roles/STOCK/perms', ['perms' => ['stock' => ['view'], 'stock-moves' => ['view', 'add'], 'stock-counts' => ['view', 'edit']]]);
$a->req('POST', '/api/users', ['us_userid' => 'clerk', 'us_name' => 'Clerk', 'us_email' => 'clerk@example.test', 'us_role' => 'STOCK', 'password' => $pw]);
$k = login('clerk', $pw);
ok('clerk can post receipt', $k->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '50']]])[0] === 200);
ok('clerk cannot see history', $k->req('GET', '/api/stock/history')[0] === 403);
// counting and posting are both 'edit' (no separate approval right): the clerk counts, only adding a new count needs 'add'
ok('clerk cannot create counts', $k->req('POST', '/api/stock-counts', ['site' => 'MAIN', 'date' => $today])[0] === 403);
ok('clerk can use lookups', $k->req('GET', '/api/stock/parts?q=P')[0] === 200);

// history and balance agree
$sum = db("SELECT tr_site, tr_loc, tr_part, tr_lot, SUM(tr_chg_qty) q FROM tr_hist GROUP BY 1,2,3,4 HAVING q <> 0 ORDER BY 1,2,3,4");
$ld = db("SELECT ld_site, ld_loc, ld_part, ld_lot, ld_qty_oh q FROM ld_det ORDER BY 1,2,3,4");
ok('sum of history = balances', array_map(fn ($r) => array_values($r), $sum) == array_map(fn ($r) => array_values($r), $ld), [$sum, $ld]);

// a receipt without a lot takes its document number as the lot; an issue must name a lot
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'L1', 'loc' => 'STOCK', 'qty' => '5']]]);
$icLot = $r[1]['data']['nbr'] ?? '';
ok('receipt lot = its document number', $r[0] === 200 && oh('L1', 'STOCK', $icLot) == 5, $r[1]);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'MAIN', 'reason' => 'USE', 'lines' => [['part' => 'L1', 'loc' => 'STOCK', 'qty' => '1']]]);
ok('issue still needs a lot', $r[0] === 422 && isset($r[1]['fields']['lines.0.lot']), $r[1]);

// a lot renamed in one place is renamed everywhere (register, balances, history)
require_once PHAI_ROOT . '/src/autoload.php';
Phai\Core\App::boot(require PHAI_ROOT . '/config.php');
restore_error_handler();
restore_exception_handler();
Phai\Core\Auth\Auth::actAs('admin');
Phai\Core\App::db()->transaction(fn () => Phai\Modules\Inventory\Stock::renameLot('L1', 'LOT-A', 'LOT-Z'));
ok('lot renamed in lot_mstr, ld_det and tr_hist', !one("SELECT COUNT(*) FROM tr_hist WHERE tr_part='L1' AND tr_lot='LOT-A'")
    && one("SELECT COUNT(*) FROM tr_hist WHERE tr_part='L1' AND tr_lot='LOT-Z'") > 0
    && !one("SELECT COUNT(*) FROM ld_det WHERE ld_part='L1' AND ld_lot='LOT-A'")
    && one("SELECT COUNT(*) FROM lot_mstr WHERE lot_part='L1' AND lot_lot='LOT-Z'") == 1
    && !one("SELECT COUNT(*) FROM lot_mstr WHERE lot_part='L1' AND lot_lot='LOT-A'"));

// price adjustment: each place gives its stock out at the old cost and takes it back at the new one
$before = db("SELECT ld_site, ld_loc, ld_lot, ld_qty_oh FROM ld_det WHERE ld_part='P1' ORDER BY 1,2,3");
$old = (float) one("SELECT pt_cost FROM pt_mstr WHERE pt_part='P1'");
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'cost', 'date' => $today, 'lines' => [['part' => 'P1', 'cost' => '25']]]);
$nbr = $r[1]['data']['nbr'] ?? '';
$iss = db("SELECT tr_chg_qty, tr_cost FROM tr_hist WHERE tr_nbr='$nbr' AND tr_type='ISS-ADJ'");
$rct = db("SELECT tr_chg_qty, tr_cost FROM tr_hist WHERE tr_nbr='$nbr' AND tr_type='RCT-ADJ'");
ok('price adjust: out at old cost, in at new, quantities unchanged', $r[0] === 200 && count($iss) === count($before) && count($rct) === count($before)
    && (float) $iss[0]['tr_cost'] === $old && (float) $rct[0]['tr_cost'] === 25.0 && (float) one("SELECT pt_cost FROM pt_mstr WHERE pt_part='P1'") === 25.0
    && db("SELECT ld_site, ld_loc, ld_lot, ld_qty_oh FROM ld_det WHERE ld_part='P1' ORDER BY 1,2,3") == $before, [$r[1], $iss, $rct]);
ok('price adjust: same cost rejected', $a->req('POST', '/api/stock/moves', ['kind' => 'cost', 'date' => $today, 'lines' => [['part' => 'P1', 'cost' => '25']]])[0] === 422);

// a raw material becomes a finished good only by conversion: material out, good in carrying its value
$a->req('POST', '/api/parts', ['pt_part' => 'R1', 'pt_desc1' => 'วัตถุดิบ', 'pt_um' => 'EA', 'pt_prod_line' => 'RM', 'pt_cost_method' => 'AVG']);
$a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'R1', 'loc' => 'STOCK', 'qty' => '10', 'cost' => '4']]]);
$p1 = oh('P1');
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'convert', 'date' => $today, 'site' => 'MAIN', 'lines' => [
    ['part' => 'R1', 'loc' => 'STOCK', 'qty' => '10', 'fg_part' => 'P1', 'fg_qty' => '5', 'to_loc' => 'STOCK']]]);
$co = $r[1]['data']['nbr'] ?? '';
ok('convert: ISS-CO of the material, RCT-CO of the good at the material value', $r[0] === 200 && oh('R1') == 0 && oh('P1') == $p1 + 5
    && (float) one("SELECT tr_cost FROM tr_hist WHERE tr_nbr='$co' AND tr_type='RCT-CO' AND tr_part='P1'") === 8.0
    && (float) one("SELECT tr_chg_qty FROM tr_hist WHERE tr_nbr='$co' AND tr_type='ISS-CO' AND tr_part='R1'") === -10.0, $r[1]);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'convert', 'date' => $today, 'site' => 'MAIN', 'lines' => [
    ['part' => 'P1', 'loc' => 'STOCK', 'qty' => '1', 'fg_part' => 'R1', 'fg_qty' => '1', 'to_loc' => 'STOCK']]]);
ok('convert: only material in, only finished good out', $r[0] === 422 && isset($r[1]['fields']['lines.0.part'], $r[1]['fields']['lines.0.fg_part']), $r[1]);

// price adjustment is for finished goods: a material keeps what it was received at
$a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'R1', 'loc' => 'STOCK', 'qty' => '1', 'cost' => '4']]]);
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'cost', 'date' => $today, 'lines' => [['part' => 'R1', 'cost' => '9']]]);
ok('price adjust: raw material refused', $r[0] === 422 && isset($r[1]['fields']['lines.0.cost']), $r[1]);
$html = (string) $a->page('/stock?type=RM')[1];
ok('stock page: no receipt, issue or convert buttons; price adjust for goods only', !str_contains($html, '/stock/moves?kind=receipt')
    && !str_contains($html, '/stock/moves?kind=issue') && !str_contains($html, '/stock/moves?kind=convert') && !str_contains($html, '/stock/moves?kind=cost')
    && str_contains((string) $a->page('/stock?type=FG')[1], '/stock/moves?kind=cost'));

// one stock line, opened by its ld_det id: details, value and the movements of that place and lot
$r = $a->req('GET', '/api/stock?part=P1&site=WH2');
$row = $r[1]['data']['rows'][0] ?? [];
ok('stock list gives each line its id', !empty($row['ld_id']) && $row['ld_loc'] !== '', $row);
$t = $a->req('GET', '/api/stock?by=part&part=P1')[1]['data']['rows'][0] ?? [];
ok('totals per part have no single line', array_key_exists('ld_id', $t) && $t['ld_id'] === null, $t);
[$s, $html] = $a->page('/stock/view?id=' . $row['ld_id']);
ok('view page: details, history, transfer, edit (no lot fields); no convert for a good', $s === 200 && str_contains((string) $html, 'id="history"')
    && str_contains((string) $html, 'id="transfer-modal"') && !str_contains((string) $html, 'id="convert-modal"') && str_contains((string) $html, 'id="edit-modal"')
    && !str_contains((string) $html, 'name="vend_lot"')
    && str_contains((string) $html, '"loc":"' . $row['ld_loc'] . '"'));
$v = $a->req('GET', '/api/stock/view/' . $row['ld_id']);
ok('view API: the line with cost and value', $v[0] === 200 && $v[1]['data']['ld_part'] === 'P1' && $v[1]['data']['ld_site'] === 'WH2'
    && (float) $v[1]['data']['value'] === round((float) $v[1]['data']['ld_qty_oh'] * (float) $v[1]['data']['cost'], 2), $v[1]);
$h = $a->req('GET', '/api/stock/history?' . http_build_query(['part' => 'P1', 'site' => 'WH2', 'loc' => $row['ld_loc']]));
ok('history by location', $h[0] === 200 && $h[1]['data']['total'] > 0 && !array_diff(array_column($h[1]['data']['rows'], 'tr_loc'), [$row['ld_loc']]), $h[1]);
// transfer from the line: quantity given, value = quantity × the line's unit cost, same lot and expiry
$src = db("SELECT ld_id, ld_qty_oh FROM ld_det WHERE ld_site='WH2' AND ld_part='L1' AND ld_lot='LOT-Z'")[0];
$srcCost = (float) $a->req('GET', '/api/stock/view/' . $src['ld_id'])[1]['data']['cost'];
$r = $a->req('POST', "/api/stock/view/{$src['ld_id']}/transfer", ['date' => $today, 'qty' => '1', 'to_site' => 'WH2', 'to_loc' => 'S2']);
ok('transfer to the same place rejected', $r[0] === 422 && isset($r[1]['fields']['to_loc']), $r[1]);
$r = $a->req('POST', "/api/stock/view/{$src['ld_id']}/transfer", ['date' => $today, 'qty' => '999', 'to_site' => 'MAIN', 'to_loc' => 'A1']);
ok('transfer more than on hand rejected', $r[0] === 422 && isset($r[1]['fields']['qty']), $r[1]);
$r = $a->req('POST', "/api/stock/view/{$src['ld_id']}/transfer", ['date' => $today, 'qty' => '2.5', 'to_site' => 'MAIN', 'to_loc' => 'A1']);
$to = $r[1]['data']['id'] ?? 0;
ok('transfer: out and in, value follows quantity, lands on a line with expiry', $r[0] === 200 && str_starts_with($r[1]['data']['nbr'], 'TO')
    && oh('L1', 'S2', 'LOT-Z', 'WH2') == (float) $src['ld_qty_oh'] - 2.5 && oh('L1', 'A1', 'LOT-Z') == 2.5
    && (float) $r[1]['data']['value'] === round(2.5 * $srcCost, 2)
    && (db("SELECT ld_expire FROM ld_det WHERE ld_id = " . (int) $to)[0]['ld_expire'] ?? '') === '2027-01-31'
    && count(db("SELECT 1 FROM tr_hist WHERE tr_nbr = '{$r[1]['data']['nbr']}' AND tr_type IN ('ISS-TR', 'RCT-TR')")) === 2, $r[1]);

// lot edit from the line: number renamed everywhere, expiry and supplier lot for every place of the lot
[$s, $html] = $a->page('/stock/view?id=' . $to);
ok('view page of a lot line edits the lot too', $s === 200 && str_contains((string) $html, 'name="vend_lot"'));
$r = $a->req('PUT', "/api/stock/view/$to", ['lot' => 'LOT-Y', 'expire' => '2027-06-30', 'vend_lot' => 'V-9', 'status' => 'ACTIVE', 'grade' => 'A']);
ok('lot edited: renamed everywhere, expiry and supplier lot on every place', $r[0] === 200 && $r[1]['data']['ld_lot'] === 'LOT-Y'
    && $r[1]['data']['lot_vend_lot'] === 'V-9' && oh('L1', 'S2', 'LOT-Y', 'WH2') > 0 && !one("SELECT COUNT(*) FROM tr_hist WHERE tr_part='L1' AND tr_lot='LOT-Z'")
    && !one("SELECT COUNT(*) FROM ld_det WHERE ld_part='L1' AND ld_lot='LOT-Y' AND ld_expire <> '2027-06-30'"), $r[1]);
$plain = db("SELECT ld_id FROM ld_det WHERE ld_part='P1' LIMIT 1")[0]['ld_id'];
$r = $a->req('PUT', "/api/stock/view/$plain", ['status' => 'HOLD', 'grade' => 'B', 'expire' => '', 'mfg_date' => '2026-01-15']);
ok('line without lots: status, grade and received date edited', $r[0] === 200 && $r[1]['data']['ld_mfg_date'] === '2026-01-15' && $r[1]['data']['ld_status'] === 'HOLD' && $r[1]['data']['ld_grade'] === 'B' && $r[1]['data']['ld_lot'] === '', $r[1]);
$r = $a->req('PUT', "/api/stock/view/$plain", ['status' => 'ACTIVE', 'ref' => 'PAL-01', 'pack_qty' => '12']);
ok('reference and pack qty edited; line stays in the balance list', $r[0] === 200 && $r[1]['data']['ld_ref'] === 'PAL-01' && (float) $r[1]['data']['ld_pack_qty'] === 12.0
    && in_array((int) $plain, array_map('intval', array_column($a->req('GET', '/api/stock?part=P1&per_page=100')[1]['data']['rows'], 'ld_id')), true), $r[1]);
ok('pack qty must be above 0', $a->req('PUT', "/api/stock/view/$plain", ['status' => 'ACTIVE', 'pack_qty' => '0'])[0] === 422);
$ls = one("SELECT pt_lot_ser FROM pt_mstr WHERE pt_part = 'P1'");
$q = (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_id = " . (int) $plain);
$r = $a->req('PUT', "/api/stock/view/$plain", ['status' => 'ACTIVE', 'ref' => 'PAL-01', 'lot' => 'NEW-LOT']);
$new = $r[1]['data']['ld_id'] ?? 0;
ok('item without lot control: a lot switches it on, whole quantity moved into the lot, new id, history still adds up', $r[0] === 200 && $r[1]['data']['ld_lot'] === 'NEW-LOT'
    && one("SELECT pt_lot_ser FROM pt_mstr WHERE pt_part = 'P1'") === 'L'
    && (int) $new !== (int) $plain && (float) $r[1]['data']['ld_qty_oh'] === $q && $r[1]['data']['ld_ref'] === 'PAL-01'
    && (float) one("SELECT SUM(tr_chg_qty) FROM tr_hist WHERE tr_part='P1' AND tr_lot='NEW-LOT'") === $q, $r[1]);
pdo()->prepare("UPDATE pt_mstr SET pt_lot_ser = ? WHERE pt_part = 'P1'")->execute([$ls]);
ok('bad status rejected', $a->req('PUT', "/api/stock/view/$new", ['status' => 'NOPE'])[0] === 422);
ok('clerk cannot edit a line', $k->req('PUT', "/api/stock/view/$to", ['lot' => 'LOT-X', 'status' => 'ACTIVE'])[0] === 403);

// convert from the view page of a raw material line
$rm = db("SELECT ld_id FROM ld_det WHERE ld_part='R1' AND ld_qty_oh > 0 LIMIT 1")[0]['ld_id'] ?? 0;
ok('raw material line offers convert', str_contains((string) $a->page('/stock/view?id=' . $rm)[1], 'id="convert-modal"'));

$html = (string) $a->page('/stock?type=FG')[1];
ok('stock page from another menu has the line modal', str_contains($html, 'id="line-modal"') && str_contains($html, 'js/stock-history-grid.js'));
$v = $a->req('GET', '/api/stock/view/' . $new);
ok('view API gives the fields to show', $v[0] === 200 && count($v[1]['data']['fields']) > 10 && $v[1]['data']['status_color'] !== '', $v[1]);
ok('old item page gone, unknown line 404', $a->page('/stock/item?part=P1')[0] === 404 && $a->page('/stock/view?id=999999')[0] === 404);
$r = $a->req('GET', '/api/stock?part=P1');
ok('stock list by exact part', $r[0] === 200 && $r[1]['data']['total'] > 0 && !array_diff(array_column($r[1]['data']['rows'], 'ld_part'), ['P1']), $r[1]);

ok('an item with stock and history cannot be deleted', $a->req('DELETE', '/api/parts/row?pt_part=P1')[0] === 409);

// stock card (รายงานสินค้าและวัตถุดิบ): opening + in − out = closing = stock on hand
$range = 'from=' . date('Y-m-01') . '&to=' . $today;
$cards = $a->req('GET', "/api/stock-card?part=P1&$range")[1]['data']['cards'] ?? [];
$card = $cards[0] ?? [];
ok('stock card closes on the stock on hand', count($cards) === 1 && isset($card['closing'])
    && abs($card['opening']['qty'] + $card['in']['qty'] - $card['out']['qty'] - $card['closing']['qty']) < 0.00001
    && abs($card['closing']['qty'] - (float) one("SELECT COALESCE(SUM(ld_qty_oh), 0) FROM ld_det WHERE ld_part = 'P1'")) < 0.00001, $card['closing'] ?? $cards);
// without an item: every item that has stock or a movement, one card each, and the total value of all of them
$all = $a->req('GET', "/api/stock-card?$range")[1]['data'] ?? [];
$closing = [];
foreach ($all['cards'] ?? [] as $c) {
    $closing[$c['part']['pt_part']] = $c['closing'];
}
$onHand = array_column(db('SELECT ld_part, SUM(ld_qty_oh) AS q FROM ld_det GROUP BY ld_part HAVING q <> 0'), 'q', 'ld_part');
ok('stock card of all items: each closes on its stock on hand', count($closing) > 1 && $onHand
    && !array_filter($onHand, fn ($q, $p) => abs((float) $q - ($closing[$p]['qty'] ?? 0)) > 0.00001, ARRAY_FILTER_USE_BOTH), [$onHand, $closing]);
ok('stock card total = the items added up', abs($all['total']['closing'] - array_sum(array_column($closing, 'value'))) < 0.01
    && abs($all['total']['opening'] + $all['total']['in'] - $all['total']['out'] - $all['total']['closing']) < 0.01, $all['total'] ?? $all);
$rm = array_map(fn ($c) => $c['part']['pt_part'], $a->req('GET', "/api/stock-card?type=RM&$range")[1]['data']['cards'] ?? []);
ok('stock card by type: raw materials only', in_array('R1', $rm, true) && !array_diff($rm, array_column(db(
    "SELECT pt_part FROM pt_mstr JOIN pl_mstr ON pl_prod_line = pt_prod_line WHERE pl_inventory_type = 'RM'"), 'pt_part')), $rm);
ok('stock card page and print', $a->page('/stock-card')[0] === 200 && $a->page("/stock-card/print?html=1&part=P1&from=$today&to=$today")[0] === 200
    && str_contains((string) $a->page("/stock-card/print?html=1&$range")[1], 'R1'));

finish();
