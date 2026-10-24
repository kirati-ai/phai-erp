<?php

// Purchasing: requisitions, orders, receipts, landed cost
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

$a = login('admin', $pw);
$today = date('Y-m-d');

// setup
$a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'V1', 'vd_name' => 'ผู้ขายไทย', 'vd_status' => 'ACTIVE', 'vd_terms' => '30DAYS', 'vd_curr' => 'THB', 'vd_tax_code' => 'VAT7']));
$a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'V2', 'vd_name' => 'US Supplier', 'vd_status' => 'ACTIVE', 'vd_terms' => 'COD', 'vd_curr' => 'USD', 'vd_tax_code' => 'VAT0']));
$a->req('POST', '/api/exchange-rates', ['ex_curr' => 'USD', 'ex_date' => $today, 'ex_rate' => '35']);
$a->req('POST', '/api/parts', ['pt_part' => 'P1', 'pt_desc1' => 'สินค้าหนึ่ง', 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_cost_method' => 'AVG']);
$a->req('POST', '/api/parts', ['pt_part' => 'L1', 'pt_desc1' => 'สินค้าล็อต', 'pt_um' => 'KG', 'pt_prod_line' => 'FG', 'pt_lot_ser' => 'L', 'pt_cost_method' => 'AVG']);

foreach (['/purchase-requisitions', '/purchase-requisitions/new', '/purchase-orders', '/purchase-orders/new',
          '/receipts', '/receipts/new', '/landed-costs', '/settings/purchase'] as $p) {
    ok("page $p", $a->page($p)[0] === 200);
}

// lookups
ok('vendor search', count($a->req('GET', '/api/purchase/vendors?q=V')[1]['data']) === 2);
ok('rate lookup', $a->req('GET', "/api/rate?curr=USD&date=$today")[1]['data']['rate'] == 35
    && $a->req('GET', "/api/rate?curr=THB&date=$today")[1]['data']['rate'] == 1
    && $a->req('GET', '/api/rate?curr=USD&date=2000-01-01')[1]['data']['rate'] === null);

// PR
$r = $a->req('POST', '/api/purchase-requisitions', ['pr_date' => $today, 'pr_req_by' => '', 'lines' => ['0' => ['prd_part' => 'NOPE', 'prd_qty_req' => '0']]]);
ok('PR validation', $r[0] === 422 && isset($r[1]['fields']['pr_req_by'], $r[1]['fields']['lines.0.prd_desc'], $r[1]['fields']['lines.0.prd_qty_req'], $r[1]['fields']['lines.0.prd_req_date']), $r[1]);
$r = $a->req('POST', '/api/purchase-requisitions', ['pr_date' => $today, 'pr_req_by' => 'สมชาย', 'pr_dept' => 'ผลิต', 'lines' => [
    ['prd_part' => 'p1', 'prd_qty_req' => '10', 'prd_req_date' => $today, 'prd_est_cost' => '50', 'prd_pref_vd' => 'v1'],
    ['prd_part' => 'L1', 'prd_qty_req' => '5', 'prd_req_date' => $today, 'prd_est_cost' => '100'],
]]);
$pr = $r[1]['data']['nbr'] ?? null;
ok('PR created open, no approval step', $r[0] === 200 && $pr === 'PR000001' && one("SELECT pr_status FROM pr_mstr WHERE pr_nbr='$pr'") === 'A' && (float) one("SELECT pr_tot_amt FROM pr_mstr WHERE pr_nbr='$pr'") === 1000.0, $r[1]);
$html = (string) $a->page("/purchase-requisitions/$pr")[1];
ok('PR view: one toolbar (no layout bar), print action', str_contains($html, 'documents\/purchase-requisitions\/{nbr}\/print') && !str_contains($html, 'ti-arrow-left'));
ok('no approve route any more', $a->req('POST', "/api/purchase-requisitions/$pr/approve")[0] === 404);

// work queues above the lists: the step before that still waits for this page
$waiting = fn (string $api) => (array) ($a->req('GET', $api)[1]['data'] ?? []);
foreach (['/purchase-requisitions', '/purchase-orders', '/receipts', '/landed-costs'] as $p) {
    ok("queue on $p", str_contains((string) $a->page($p)[1], 'data-queue'));
}
$q = array_column($waiting('/api/purchase-orders/waiting'), null, 'pr_nbr');
ok('open PR waits for a PO, vendor = its preferred vendor', ($q[$pr]['vendor'] ?? null) === 'V1' && (float) $q[$pr]['amount'] === 1000.0, $q);
pdo()->exec("INSERT INTO mrp_det (mrp_part, mrp_site, mrp_type, mrp_qty, mrp_rel_date, mrp_due_date) VALUES ('L1', 'MAIN', 'PO', 7, '2000-01-01', '2000-01-08')");
$q = $waiting('/api/purchase-requisitions/waiting');
ok('MRP planned purchase waits for a PR, late when its order date passed', ($q[0]['part'] ?? null) === 'L1' && (int) $q[0]['late'] === 1, $q);
$r = $a->req('POST', '/api/purchase-requisitions/from-plan', ['keys' => [$q[0]['mrp_key']]]);
$mrpPr = $r[1]['data']['nbr'] ?? '';
ok('a planned purchase becomes a requisition in one click (from the queue)', $r[0] === 200
    && one("SELECT CONCAT(prd_part, ':', ROUND(prd_qty_req)) FROM prd_det WHERE prd_nbr = '$mrpPr'") === 'L1:7', $r[1]);
$a->req('POST', "/api/purchase-requisitions/$mrpPr/cancel"); // out of the way of the orders below
ok('no MRP rows, nothing waits for a PR', !in_array('L1', array_column($waiting('/api/purchase-requisitions/waiting'), 'part'), true));
$r = $a->req('POST', '/api/purchase-orders/from-pr', ['pr' => $pr, 'vendor' => 'V1']);
$po1 = $r[1]['data']['nbr'] ?? null;
ok('PO from PR', $r[0] === 200 && $po1 === 'PO000001' && one("SELECT pr_status FROM pr_mstr WHERE pr_nbr='$pr'") === 'O'
    && one("SELECT COUNT(*) FROM prd_det WHERE prd_nbr='$pr' AND prd_po_nbr='$po1'") == 2, $r[1]);
ok('ordered PR no longer waits for a PO', !in_array($pr, array_column($waiting('/api/purchase-orders/waiting'), 'pr_nbr'), true));
ok('ordered PR not editable', $a->req('PUT', "/api/purchase-requisitions/$pr", ['pr_date' => $today, 'pr_req_by' => 'x', 'lines' => [['prd_part' => 'P1', 'prd_qty_req' => '1', 'prd_req_date' => $today]]])[0] === 409);
$h = db("SELECT * FROM po_mstr WHERE po_nbr='$po1'")[0];
ok('PO from PR: vendor defaults, approved, totals with VAT 7%', $h['po_status'] === 'A' && $h['po_cr_terms'] === '30DAYS' && $h['po_tax_code'] === 'VAT7'
    && (float) $h['po_tax_amt'] === 70.0 && (float) $h['po_tot_amt'] === 1070.0, $h);
ok('cancel PO frees the PR', $a->req('POST', "/api/purchase-orders/$po1/cancel")[0] === 200
    && one("SELECT pr_status FROM pr_mstr WHERE pr_nbr='$pr'") === 'A' && one("SELECT COUNT(*) FROM prd_det WHERE prd_nbr='$pr' AND prd_po_nbr IS NOT NULL") == 0
    && one("SELECT po_status FROM po_mstr WHERE po_nbr='$po1'") === 'X');

// manual PO
$poBody = ['po_vd_code' => 'v1', 'po_date' => $today, 'po_ship_to' => 'MAIN', 'po_cr_terms' => '30DAYS', 'po_curr' => 'THB', 'po_ex_rate' => '1', 'po_tax_code' => 'VAT7',
    'lines' => [
        ['pod_part' => 'P1', 'pod_qty_ord' => '100', 'pod_price' => '10', 'pod_disc_pct' => '10'],
        ['pod_part' => 'L1', 'pod_qty_ord' => '20', 'pod_price' => '50'],
    ]];
$r = $a->req('POST', '/api/purchase-orders', array_merge($poBody, ['po_ex_rate' => '0', 'lines' => [['pod_part' => 'P1', 'pod_qty_ord' => '1', 'pod_price' => '1', 'pod_disc_pct' => '150']]]));
ok('PO validation: rate > 0, discount <= 100', $r[0] === 422 && isset($r[1]['fields']['po_ex_rate'], $r[1]['fields']['lines.0.pod_disc_pct']), $r[1]);
$r = $a->req('POST', '/api/purchase-orders', $poBody);
$po2 = $r[1]['data']['nbr'] ?? null;
$h = db("SELECT * FROM po_mstr WHERE po_nbr='$po2'")[0] ?? [];
ok('manual PO totals (900 + 1000 = 1900, VAT 133)', $r[0] === 200 && (float) $h['po_tax_amt'] === 133.0 && (float) $h['po_tot_amt'] === 2033.0
    && (float) one("SELECT pod_line_amt FROM pod_det WHERE pod_nbr='$po2' AND pod_line=1") === 900.0, [$r[1], $h]);
ok('last price lookup', (float) $a->req('GET', '/api/purchase/last-price?part=P1&vendor=V1')[1]['data']['price'] === 10.0);
$r = $a->req('GET', "/api/purchase-orders/$po2");
ok('PO show is editable before receipt', $r[0] === 200 && $r[1]['data']['head']['_editable'] === true && count($r[1]['data']['lines']) === 2);
ok('print page with Thai amount', ($p = $a->page("/purchase-orders/$po2/print?html=1"))[0] === 200 && str_contains($p[1], 'สองพันสามสิบสามบาทถ้วน'));
[$s, $h, $pdf] = raw('GET', E2E_URL . "/purchase-orders/$po2/print?pdf=1", [cookie($a)]);
$hs = implode("
", $h);
if (str_contains($hs, 'application/pdf')) {
    ok('PDF made on the server: one A4 page, Thai font embedded, named after the order', $s === 200 && str_starts_with($pdf, '%PDF')
        && preg_match_all('#/Type\s*/Page[^s]#', $pdf) === 1 && str_contains($pdf, 'IBMPlexSansThai') && str_contains($hs, "filename=\"$po2.pdf\""), [$s, strlen($pdf)]);
} else {
    ok('no browser on this server: the print page is returned instead', $s === 200 && str_contains($pdf, 'doc-toolbar'));
}
ok('PDF needs a login too', in_array(raw('GET', E2E_URL . "/purchase-orders/$po2/print?pdf=1")[0], [302, 401], true));

// receipts
ok('open POs list', in_array($po2, array_column($a->req('GET', '/api/receipts/open-pos?q=')[1]['data'], 'po_nbr'), true));
$q = array_column($waiting('/api/receipts/waiting'), null, 'po_nbr');
ok('open PO waits for goods with its open qty and value', (float) ($q[$po2]['open_qty'] ?? 0) === 120.0 && (float) $q[$po2]['open_amt'] === 1900.0, $q);
$r = $a->req('POST', '/api/receipts', ['po' => $po2, 'rc_date' => $today, 'rc_site' => 'MAIN', 'lines' => ['1' => ['qty' => '150', 'loc' => 'STOCK'], '2' => ['qty' => '5', 'loc' => 'STOCK']]]);
ok('receipt: no invoice or delivery note for the lot: error on the head (receiving more than ordered is not an error)', $r[0] === 422 && !isset($r[1]['fields']['lines.1.qty']) && isset($r[1]['fields']['rc_inv_nbr']), $r[1]);
$r = $a->req('POST', '/api/receipts', ['po' => $po2, 'rc_date' => $today, 'rc_site' => 'MAIN', 'rc_inv_nbr' => 'DO-1',
    'lines' => ['1' => ['qty' => '60', 'loc' => 'STOCK'], '2' => ['qty' => '', 'loc' => 'STOCK']]]);
$rc1 = $r[1]['data']['nbr'] ?? null;
ok('partial receipt', $r[0] === 200 && $rc1 === 'RC000001', $r[1]);
ok('stock in at net PO price (10 less 10% = 9)', (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='P1'") === 60.0 && (float) one("SELECT pt_cost FROM pt_mstr WHERE pt_part='P1'") === 9.0);
ok('PO line received, PO still open', (float) one("SELECT pod_qty_rcvd FROM pod_det WHERE pod_nbr='$po2' AND pod_line=1") === 60.0 && one("SELECT po_status FROM po_mstr WHERE po_nbr='$po2'") === 'A');
ok('receipt totals in baht (540 + 7%)', (float) one("SELECT rc_net_amt FROM rc_mstr WHERE rc_nbr='$rc1'") === 540.0 && (float) one("SELECT rc_tot_amt FROM rc_mstr WHERE rc_nbr='$rc1'") === 577.8);
[$s, $html] = $a->page("/receipts/$rc1");
ok('saved receipt opens as a view: back, waiting badge, create payable, print, lines', $s === 200 && str_contains((string) $html, 'phai-view-mode')
    && str_contains((string) $html, 'รอตั้งหนี้') && str_contains((string) $html, '/payables/new?') && str_contains((string) $html, "/documents/receipts/$rc1/print")
    && substr_count((string) $html, 'ti-arrow-left') === 1);
ok('PO not editable after receipt', $a->req('PUT', "/api/purchase-orders/$po2", $poBody)[0] === 409 && $a->req('POST', "/api/purchase-orders/$po2/cancel")[0] === 409);
$r = $a->req('POST', '/api/receipts', ['po' => $po2, 'rc_date' => $today, 'rc_site' => 'MAIN',
    'lines' => ['1' => ['qty' => '40', 'loc' => 'STOCK'], '2' => ['qty' => '20', 'loc' => 'STOCK', 'lot' => 'LOT-9', 'expire' => '2027-06-30', 'pack' => '5']]]);
ok('final receipt closes the PO', $r[0] === 200 && one("SELECT po_status FROM po_mstr WHERE po_nbr='$po2'") === 'C'
    && one("SELECT COUNT(*) FROM pod_det WHERE pod_nbr='$po2' AND pod_status='C'") == 2, $r[1]);
ok('lot stock with expiry', (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='L1' AND ld_lot='LOT-9'") === 20.0 && one("SELECT ld_expire FROM ld_det WHERE ld_part='L1'") === '2027-06-30');
ok('pack qty: 1 by default, as given otherwise', (float) one("SELECT ld_pack_qty FROM ld_det WHERE ld_part='P1'") === 1.0
    && (float) one("SELECT ld_pack_qty FROM ld_det WHERE ld_part='L1' AND ld_lot='LOT-9'") === 5.0);
ok('received PO no longer waits for goods', !in_array($po2, array_column($waiting('/api/receipts/waiting'), 'po_nbr'), true));
ok('closed PO cannot be received', $a->req('GET', "/api/receipts/po/$po2")[0] === 409);
ok('history type RCT-PO with price', one("SELECT COUNT(*) FROM tr_hist WHERE tr_type='RCT-PO' AND tr_price > 0") == 3);

// foreign currency + receiving tolerance
$a->req('PUT', '/api/settings/purchase/row', ['po_rcv_tol' => '10']);
$r = $a->req('POST', '/api/purchase-orders', ['po_vd_code' => 'V2', 'po_date' => $today, 'po_ship_to' => 'MAIN', 'po_curr' => 'USD', 'po_ex_rate' => '35', 'po_tax_code' => 'VAT0',
    'lines' => [['pod_part' => 'P1', 'pod_qty_ord' => '10', 'pod_price' => '2']]]);
$po3 = $r[1]['data']['nbr'] ?? null;
ok('new PO is open at once', $r[0] === 200 && one("SELECT po_status FROM po_mstr WHERE po_nbr='$po3'") === 'A');
$a->req('PUT', "/api/purchase-orders/$po3", ['po_vd_code' => 'V2', 'po_date' => $today, 'po_ship_to' => 'MAIN', 'po_curr' => 'USD', 'po_ex_rate' => '35', 'po_tax_code' => 'VAT0',
    'lines' => [['pod_part' => 'P1', 'pod_qty_ord' => '10', 'pod_price' => '2']]]);
ok('edited PO stays open', one("SELECT po_status FROM po_mstr WHERE po_nbr='$po3'") === 'A');
$r = $a->req('POST', '/api/receipts', ['po' => $po3, 'rc_date' => $today, 'rc_site' => 'MAIN', 'lines' => ['1' => ['qty' => '11', 'loc' => 'STOCK']]]);
ok('10% tolerance lets 11 of 10 in without a warning', $r[0] === 200 && !isset($r[1]['data']['notice']), $r[1]);
ok('USD receipt costed at 2 x 35 = 70 baht (avg of 100@9 and 11@70)', abs((float) one("SELECT pt_cost FROM pt_mstr WHERE pt_part='P1'") - round((100 * 9 + 11 * 70) / 111, 5)) < 0.0001);
$rc3 = $r[1]['data']['nbr'];

// close short
$r = $a->req('POST', '/api/purchase-orders', ['po_vd_code' => 'V1', 'po_date' => $today, 'po_ship_to' => 'MAIN', 'po_curr' => 'THB', 'po_ex_rate' => '1',
    'lines' => [['pod_part' => 'P1', 'pod_qty_ord' => '10', 'pod_price' => '9']]]);
$po4 = $r[1]['data']['nbr'];
$a->req('POST', '/api/receipts', ['po' => $po4, 'rc_date' => $today, 'rc_site' => 'MAIN', 'lines' => ['1' => ['qty' => '4', 'loc' => 'STOCK']]]);
ok('short close', $a->req('POST', "/api/purchase-orders/$po4/close")[0] === 200 && one("SELECT po_status FROM po_mstr WHERE po_nbr='$po4'") === 'C');

// scheduled order (ใบสั่งซื้อแบบกำหนดส่ง): one price agreement, the line arrives in dated instalments
$in = fn (int $days) => date('Y-m-d', strtotime("+$days days"));
$r = $a->req('POST', '/api/purchase-orders', ['po_vd_code' => 'V1', 'po_date' => $today, 'po_ship_to' => 'MAIN', 'po_curr' => 'THB', 'po_ex_rate' => '1', 'po_tax_code' => 'VAT7',
    'po_sched' => '1', 'lines' => [['pod_part' => 'P1', 'pod_qty_ord' => '30', 'pod_price' => '10', 'pod_req_date' => $today]]]);
$ps = $r[1]['data']['nbr'] ?? '';
ok('scheduled PO starts with one instalment of the whole line', $r[0] === 200 && one("SELECT COUNT(*) FROM schd_det WHERE schd_type='PO' AND schd_nbr='$ps'") == 1
    && (float) one("SELECT schd_qty FROM schd_det WHERE schd_nbr='$ps'") === 30.0, $r[1]);
ok('its page offers the schedule, the schedule page opens', str_contains((string) $a->page("/purchase-orders/$ps")[1], '"name":"schedule"')
    && $a->page("/schedules/po/$ps")[0] === 200 && $a->page("/schedules/po/$po4")[0] === 404);
$r = $a->req('PUT', "/api/schedules/po/$ps", ['lines' => ['1' => ['rows' => [['date' => $today, 'qty' => '10'], ['date' => '', 'qty' => 'x']]]]]);
ok('schedule rows are checked', $r[0] === 422 && isset($r[1]['fields']['lines.1.rows.1.date'], $r[1]['fields']['lines.1.rows.1.qty']), $r[1]);
$r = $a->req('PUT', "/api/schedules/po/$ps", ['lines' => ['1' => ['rows' => [['date' => $in(60), 'qty' => '10'], ['date' => $today, 'qty' => '10'], ['date' => $in(30), 'qty' => '10']]]]]);
ok('schedule saved in date order, the line follows', $r[0] === 200 && array_column($r[1]['data']['lines'][0]['rows'], 'date') === [$today, $in(30), $in(60)]
    && (float) one("SELECT pod_qty_ord FROM pod_det WHERE pod_nbr='$ps'") === 30.0, $r[1]);
$a->req('POST', '/api/receipts', ['po' => $ps, 'rc_date' => $today, 'rc_site' => 'MAIN', 'lines' => ['1' => ['qty' => '12', 'loc' => 'STOCK']]]);
$rows = $a->req('GET', "/api/schedules/po/$ps")[1]['data']['lines'][0]['rows'] ?? [];
ok('received is taken from the earliest instalments', array_map('floatval', array_column($rows, 'done')) === [10.0, 2.0, 0.0], $rows);
$a->req('POST', '/api/receipts', ['po' => $ps, 'rc_date' => $today, 'rc_site' => 'MAIN', 'lines' => ['1' => ['qty' => '18', 'loc' => 'STOCK']]]);
ok('everything released has arrived: the agreement stays open', one("SELECT po_status FROM po_mstr WHERE po_nbr='$ps'") === 'A'
    && one("SELECT pod_status FROM pod_det WHERE pod_nbr='$ps'") === 'C');
$r = $a->req('PUT', "/api/schedules/po/$ps", ['lines' => ['1' => ['rows' => [['date' => $today, 'qty' => '20']]]]]);
ok('a schedule cannot fall below what was received', $r[0] === 422 && isset($r[1]['fields']['lines.1.total']), $r[1]);
$r = $a->req('PUT', "/api/schedules/po/$ps", ['lines' => ['1' => ['rows' => [['date' => $today, 'qty' => '10'], ['date' => $in(30), 'qty' => '10'], ['date' => $in(60), 'qty' => '10'], ['date' => $in(90), 'qty' => '5']]]]]);
ok('a new instalment reopens the line and the totals follow', $r[0] === 200 && one("SELECT pod_status FROM pod_det WHERE pod_nbr='$ps'") === 'O'
    && (float) one("SELECT pod_qty_ord FROM pod_det WHERE pod_nbr='$ps'") === 35.0 && (float) one("SELECT po_tot_amt FROM po_mstr WHERE po_nbr='$ps'") === 374.5, $r[1]);
ok('the printed order lists the instalments', str_contains((string) $a->page("/purchase-orders/$ps/print?html=1")[1], date('d/m/Y', strtotime($in(90)))));
// releases (Supplier Schedule): the first save replaced the release made with the order; each later one is a new release,
// the newest in use, the old ones kept as they were
$s = $a->req('GET', "/api/schedules/po/$ps")[1]['data'] ?? [];
ok('releases: a new one each time, the newest in use', count($s['head']['releases'] ?? []) === 2 && ($s['release']['active'] ?? false) === true
    && $s['release']['source'] === 'MANUAL' && $s['release']['id'] === str_replace('-', '', $today) . '-02', $s['head'] ?? $s);
$old = $a->req('GET', "/api/schedules/po/$ps?rlse=" . ($s['head']['releases'][1]['id'] ?? ''))[1]['data'] ?? [];
ok('the release before is kept as it was', ($old['release']['active'] ?? true) === false && count($old['lines'][0]['rows'] ?? []) === 3, $old);
ok('firm up to the firm date (14 days), planned after; each date against the release before', array_column($s['lines'][0]['rows'], 'fpps') === ['F', 'P', 'P', 'P']
    && $s['lines'][0]['rows'][3]['prev'] === null && (float) $s['lines'][0]['rows'][0]['prev'] === 10.0, $s['lines'][0]['rows'] ?? $s);
ok('a release cannot be dated before the one in use', isset($a->req('PUT', "/api/schedules/po/$ps", ['date' => date('Y-m-d', strtotime('-1 day')), 'lines' => []])[1]['fields']['date']));
$m = $a->req('GET', "/api/schedules/po/$ps/from-mrp");
ok('new release from MRP: a draft that keeps what is firm or received', $m[0] === 200 && ($m[1]['data']['lines']['1'][0]['date'] ?? '') === $today
    && one("SELECT COUNT(*) FROM sch_mstr WHERE sch_nbr='$ps'") == 2, $m[1]);
ok('the release prints for the supplier', str_contains((string) $a->page("/schedules/po/$ps/print?html=1")[1], 'ตารางกำหนดส่งสินค้า'));
$list = array_column($a->req('GET', '/api/schedules/po')[1]['data']['rows'] ?? [], null, 'nbr');
ok('menu: every supplier schedule with its release in use (its column layout too)', $a->page('/schedules/po')[0] === 200
    && $a->req('GET', '/api/grid-layout?source=/api/schedules/po&table=docs')[0] === 200 && ($list[$ps]['rlse'] ?? '') === $s['release']['id']
    && !isset($list[$po4]), $list);
ok('closed by hand, the schedule no longer changes', $a->req('POST', "/api/purchase-orders/$ps/close")[0] === 200
    && $a->req('PUT', "/api/schedules/po/$ps", ['lines' => ['1' => ['rows' => [['date' => $today, 'qty' => '40']]]]])[0] === 409);

// permissions: buyer without delete / print / receive
$a->req('POST', '/api/roles', ['role_code' => 'BUYER', 'role_desc' => 'Buyer']);
$a->req('PUT', '/api/roles/BUYER/perms', ['perms' => ['purchase-orders' => ['view', 'add', 'edit'], 'purchase-requisitions' => ['view', 'add']]]);
$a->req('POST', '/api/users', ['us_userid' => 'buyer', 'us_name' => 'Buyer', 'us_email' => 'buyer@example.test', 'us_role' => 'BUYER', 'password' => $pw]);
$k = login('buyer', $pw);
$r = $k->req('POST', '/api/purchase-orders', $poBody);
$po5 = $r[1]['data']['nbr'] ?? '';
ok('buyer can create PO', $r[0] === 200);
ok('buyer cannot cancel or print', $k->req('POST', "/api/purchase-orders/$po5/cancel")[0] === 403 && $k->page("/purchase-orders/$po5/print")[0] === 403);
ok('buyer cannot receive', $k->req('POST', '/api/receipts', ['po' => $po5])[0] === 403);

// PR required
$a->req('PUT', '/api/settings/purchase/row', ['po_pr_req' => true]);
ok('manual PO blocked when PR required', $a->req('POST', '/api/purchase-orders', $poBody)[0] === 422);
$r = $a->req('POST', '/api/purchase-orders/from-pr', ['pr' => $pr, 'vendor' => 'V1']);
ok('PO from PR still allowed', $r[0] === 200, $r[1]);
$a->req('PUT', '/api/settings/purchase/row', ['po_pr_req' => false]);

// landed cost on the USD receipt (11 x P1)
$costBefore = (float) one("SELECT pt_cost FROM pt_mstr WHERE pt_part='P1'");
$onHand = (float) one("SELECT SUM(ld_qty_oh) FROM ld_det WHERE ld_part='P1'");
$r = $a->req('PUT', "/api/landed-costs/$rc3", ['lines' => ['1' => ['type' => 'FREIGHT', 'vendor' => 'v1', 'amount' => '555'],
    '2' => ['type' => 'DUTY', 'amount' => 'x'], '3' => ['type' => 'CLEAR', 'amount' => '50']]]);
ok('landed cost validation', $r[0] === 422 && isset($r[1]['fields']['lines.2.amount'], $r[1]['fields']['lines.3.vendor']), $r[1]);
$r = $a->req('PUT', "/api/landed-costs/$rc3", ['lines' => ['1' => ['type' => 'FREIGHT', 'ref' => 'AWB 1', 'vendor' => 'v1', 'amount' => '555'],
    '2' => ['type' => 'DUTY', 'amount' => '']]]);
ok('landed cost saved, blank line dropped', $r[0] === 200 && count($r[1]['data']['costs']) === 1 && $r[1]['data']['costs'][0]['amount'] == 555
    && $r[1]['data']['costs'][0]['ref'] === 'AWB 1', $r[1]);
[$s, $html] = $a->page("/landed-costs/$rc3");
ok('landed costs open as a view: back, edit, allocate, print', $s === 200 && str_contains((string) $html, 'id="lc-edit"') && str_contains((string) $html, 'id="lc-allocate"')
    && str_contains((string) $html, "/documents/landed-costs/$rc3/print") && substr_count((string) $html, 'ti-arrow-left') === 1);
$q = array_column($waiting('/api/landed-costs/waiting'), 'rc_nbr');
ok('USD receipt waits for landed costs, baht receipt does not', in_array($rc3, $q, true) && !in_array($rc1, $q, true), $q);
$q = array_column($a->req('GET', '/api/landed-costs?per_page=100')[1]['data']['rows'] ?? [], 'rc_nbr');
ok('landed cost list shows imports only', in_array($rc3, $q, true) && !in_array($rc1, $q, true), $q);
$r = $a->req('PUT', "/api/landed-costs/$rc1", ['lines' => ['1' => ['type' => 'FREIGHT', 'vendor' => 'v1', 'amount' => '10']]]);
ok('baht receipt takes no landed cost', $r[0] === 422, $r[1]);
$r = $a->req('POST', "/api/landed-costs/$rc3/allocate");
ok('allocated receipt no longer waits', !in_array($rc3, array_column($waiting('/api/landed-costs/waiting'), 'rc_nbr'), true));
ok('allocated into average cost', $r[0] === 200 && abs((float) one("SELECT pt_cost FROM pt_mstr WHERE pt_part='P1'") - round($costBefore + 555 / $onHand, 5)) < 0.0001, [$r[1], $costBefore, $onHand]);
ok('allocated cost locked', $a->req('POST', "/api/landed-costs/$rc3/allocate")[0] === 422
    && (float) one("SELECT lc_amt FROM lc_mstr WHERE lc_rc_nbr='$rc3'") === 555.0);
$a->req('PUT', "/api/landed-costs/$rc3", ['lines' => ['1' => ['type' => 'DUTY', 'vendor' => 'v1', 'amount' => '100']]]);
ok('locked line unchanged, new line added after it', (float) one("SELECT lc_amt FROM lc_mstr WHERE lc_rc_nbr='$rc3' AND lc_line=1") === 555.0
    && (float) one("SELECT lc_amt FROM lc_mstr WHERE lc_rc_nbr='$rc3' AND lc_line=2 AND lc_cost_type='DUTY'") === 100.0);

// the carrier bills the freight: its payable clears what the allocation put on GRNI
$key = "$rc3~1";
ok('allocated freight waits for the carrier invoice', in_array($key, array_column($waiting('/api/payables/receipts?vendor=V1'), 'rc_nbr'), true)
    && !in_array("$rc3~2", array_column($waiting('/api/payables/waiting'), 'rc_nbr'), true));
$r = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => date('Y-m-d'), 'ap_inv_nbr' => 'FR-1', 'tax_code' => 'VAT7', 'receipts' => [$key]]);
$apLc = $r[1]['data']['nbr'] ?? '';
$grni = (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_acct = '20103' AND ((gl_type = 'LC' AND gl_ref = '$rc3') OR (gl_type = 'AP' AND gl_ref = '$apLc'))");
ok('freight payable clears GRNI and links back', $r[0] === 200 && abs($grni) < 0.005
    && one("SELECT lc_ap_ref FROM lc_mstr WHERE lc_rc_nbr='$rc3' AND lc_line=1") === $apLc
    && (float) one("SELECT ap_amt FROM ap_mstr WHERE ap_ref='$apLc'") === round(555 * 1.07, 2), [$r[1], $grni]);
$r = $a->req('PUT', "/api/landed-costs/$rc3", ['lines' => ['1' => ['type' => 'OTHER', 'vendor' => 'v1', 'amount' => '5']]]);
ok('billed landed costs cannot be changed', $r[0] === 422 && $a->req('GET', "/api/landed-costs/$rc3")[1]['data']['billed'] === true, $r[1]);
ok('billed freight no longer waits', !in_array($key, array_column($waiting('/api/payables/waiting'), 'rc_nbr'), true)
    && $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => date('Y-m-d'), 'ap_inv_nbr' => 'FR-2', 'receipts' => [$key]])[0] === 422);

// a purchase return (ใบส่งคืนสินค้า): 10 of the 60 P1 back to V1 at the purchase price; the vendor's invoice deducts it
ok('purchase return: the receipt picker lists the receipt', in_array($rc1, array_column($a->req('GET', '/api/purchase-returns/receipts?q=' . $rc1)[1]['data'] ?? [], 'rc_nbr'), true));
$r = $a->req('POST', '/api/purchase-returns', ['rc' => $rc1, 'rt_date' => $today, 'rt_reason' => 'ชำรุด', 'lines' => ['1' => '999']]);
ok('return over what was received refused', $r[0] === 422 && isset($r[1]['fields']['lines.1.qty']), $r[1]);
$onHandP1 = (float) one("SELECT SUM(ld_qty_oh) FROM ld_det WHERE ld_part='P1'");
$r = $a->req('POST', '/api/purchase-returns', ['rc' => $rc1, 'rt_date' => $today, 'rt_reason' => 'ชำรุด', 'lines' => ['1' => '10']]);
$rt = $r[1]['data']['nbr'] ?? '';
$grniOf = fn (string $ref) => (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_ref = '$ref' AND gl_acct = '20103'");
ok('return: stock out, GRNI back at the purchase price', $r[0] === 200 && abs((float) one("SELECT SUM(ld_qty_oh) FROM ld_det WHERE ld_part='P1'") - ($onHandP1 - 10)) < 0.00001
    && abs($grniOf($rt) - 90) < 0.005 && (float) one("SELECT rt_tot_amt FROM rt_mstr WHERE rt_nbr='$rt'") === 96.3
    && (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_ref = '$rt'") == 0.0, $r[1]);
$rows = array_column($waiting('/api/payables/receipts?vendor=V1'), null, 'rc_nbr');
ok('return waits for the vendor, as a deduction', (float) ($rows["$rt~RTV"]['rc_tot_amt'] ?? 0) === -96.3, array_keys($rows));
$r = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'INV-RT', 'receipts' => [$rc1, "$rt~RTV"]]);
$apRt = $r[1]['data']['nbr'] ?? '';
ok('invoice net of the return clears GRNI of both', $r[0] === 200 && (float) one("SELECT ap_amt FROM ap_mstr WHERE ap_ref='$apRt'") === 481.5
    && abs($grniOf($rc1) + $grniOf($rt) + $grniOf($apRt)) < 0.005 && one("SELECT rt_status FROM rt_mstr WHERE rt_nbr='$rt'") === 'C', $r[1]);
ok('return pages and print', $a->page("/purchase-returns/$rt")[0] === 200 && $a->page('/purchase-returns')[0] === 200
    && $a->page("/purchase-returns/new?rc=$rc1")[0] === 200 && $a->page("/purchase-returns/$rt/print?html=1")[0] === 200);

// document lists show one month at a time (month + month_key, Db::page)
$pos = $a->req('GET', '/api/purchase-orders?month=' . substr($today, 0, 7) . '&month_key=po_date')[1]['data'] ?? [];
$other = $a->req('GET', '/api/purchase-orders?month=2001-01&month_key=po_date')[1]['data'] ?? [];
ok('list of one month', ($pos['total'] ?? 0) > 0 && !array_filter($pos['rows'], fn ($r) => substr($r['po_date'], 0, 7) !== substr($today, 0, 7))
    && ($other['total'] ?? -1) === 0, [$pos['total'] ?? null, $other['total'] ?? null]);

// vendor evaluation: V1 delivered and had goods sent back, so its returned % is above zero
$vr = array_column($a->req('GET', '/api/vendor-rating')[1]['data']['rows'] ?? [], null, 'vendor');
ok('vendor evaluation scores V1 with its return', isset($vr['V1']) && $vr['V1']['returned_pct'] > 0 && in_array($vr['V1']['grade'], ['A', 'B', 'C'], true)
    && $a->page('/vendor-rating')[0] === 200, $vr['V1'] ?? $vr);

// stock still consistent
$sum = db("SELECT tr_site, tr_loc, tr_part, tr_lot, SUM(tr_chg_qty) q FROM tr_hist GROUP BY 1,2,3,4 HAVING q <> 0 ORDER BY 1,2,3,4");
$ld = db("SELECT ld_site, ld_loc, ld_part, ld_lot, ld_qty_oh q FROM ld_det ORDER BY 1,2,3,4");
ok('sum of history = balances', array_map('array_values', $sum) == array_map('array_values', $ld));

finish();
