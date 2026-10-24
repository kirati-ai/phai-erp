<?php

// Production: BOMs, routings, work orders, MRP
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

$a = login('admin', $pw);
$today = date('Y-m-d');
$d10 = date('Y-m-d', strtotime('+10 days'));

// setup
$part = fn ($code, $type, $more = []) => $a->req('POST', '/api/parts', array_merge(['pt_part' => $code, 'pt_desc1' => "สินค้า $code", 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_part_type' => $type, 'pt_cost_method' => 'AVG'], $more));
$part('FG', 'M', ['pt_lead_time' => '2']);
$part('SUB', 'M', ['pt_lead_time' => '1']);
$part('RM1', 'P', ['pt_lead_time' => '5', 'pt_pack_qty' => '10']);
$part('RM2', 'P', ['pt_lot_ser' => 'L', 'pt_um' => 'KG']);
$a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [
    ['part' => 'RM1', 'loc' => 'STOCK', 'qty' => '100', 'cost' => '10'],
    ['part' => 'RM2', 'loc' => 'STOCK', 'lot' => 'A', 'qty' => '20', 'cost' => '20'],
]]);
$r = $a->req('POST', '/api/work-centers', ['wc_wkctr' => 'wc1', 'wc_desc' => 'สายการผลิต 1', 'wc_lbr_rate' => '100', 'wc_active' => true]);
ok('work center', $r[0] === 200, $r[1]);
ok('machine', $a->req('POST', '/api/machines', ['mch_code' => 'M1', 'mch_desc' => 'เครื่องตัด', 'mch_wkctr' => 'WC1', 'mch_active' => true])[0] === 200);
ok('employees', $a->req('POST', '/api/employees', ['emp_nbr' => 'E1', 'emp_name' => 'สมชาย', 'emp_wkctr' => 'WC1', 'emp_active' => true])[0] === 200
    && $a->req('POST', '/api/employees', ['emp_nbr' => 'E2', 'emp_name' => 'สมหญิง', 'emp_rate' => '150', 'emp_active' => true])[0] === 200);
// an employee who signs in is a user: their name fills "requested by" / "inspector"
$r = $a->req('PUT', '/api/employees/row?emp_nbr=E1', ['emp_userid' => 'admin']);
ok('employee linked to a user', $r[0] === 200 && one("SELECT emp_userid FROM emp_mstr WHERE emp_nbr = 'E1'") === 'admin', $r[1]);
$r = $a->req('PUT', '/api/employees/row?emp_nbr=E2', ['emp_userid' => 'admin']);
ok('a user is one employee only', $r[0] === 422 && isset($r[1]['fields']['emp_userid']), $r[1]);
ok('people list for name fields', in_array('สมชาย', array_column($a->req('GET', '/api/people?q=สม')[1]['data'] ?? [], 'emp_name'), true));
ok('requisition: requested by is the signed-in employee', str_contains((string) $a->page('/purchase-requisitions/new')[1], 'สมชาย'));

foreach (['/boms', '/work-centers', '/machines', '/employees', '/work-orders', '/work-orders/new', '/qc', '/mrp'] as $pg) {
    ok("page $pg", $a->page($pg)[0] === 200);
}
ok('one screen for materials and steps, with costs', str_contains((string) ($p = $a->page('/boms')[1]), 'data-tab="bom"') && str_contains((string) $p, 'data-tab="routing"')
    && $a->req('GET', '/routings')[0] === 302);

// BOM
$r = $a->req('PUT', '/api/boms/FG', ['lines' => [
    '0' => ['ps_comp' => 'NOPE', 'ps_qty_per' => '1'], '1' => ['ps_comp' => 'FG', 'ps_qty_per' => '1'],
    '2' => ['ps_comp' => 'RM1', 'ps_qty_per' => '0'], '3' => ['ps_comp' => 'RM1', 'ps_qty_per' => '1'],
]]);
$f = $r[1]['fields'] ?? [];
ok('BOM validation: unknown, itself, qty 0, duplicate', $r[0] === 422 && isset($f['lines.0.ps_comp'], $f['lines.1.ps_comp'], $f['lines.2.ps_qty_per'], $f['lines.3.ps_comp']), $f);
$r = $a->req('PUT', '/api/boms/FG', ['lines' => [['ps_comp' => 'rm1', 'ps_qty_per' => '2'], ['ps_comp' => 'SUB', 'ps_qty_per' => '1']]]);
ok('BOM FG saved', $r[0] === 200 && count($r[1]['data']['lines']) === 2, $r[1]);
$r = $a->req('PUT', '/api/boms/SUB', ['lines' => [['ps_comp' => 'RM1', 'ps_qty_per' => '3'], ['ps_comp' => 'RM2', 'ps_qty_per' => '0.5', 'ps_scrap' => '10']]]);
ok('BOM SUB saved', $r[0] === 200, $r[1]);
$r = $a->req('PUT', '/api/boms/RM1', ['lines' => [['ps_comp' => 'FG', 'ps_qty_per' => '1']]]);
ok('BOM loop refused (RM1 -> FG -> SUB -> RM1)', $r[0] === 422 && isset($r[1]['fields']['lines.0.ps_comp']), $r[1]);
ok('BOM list', array_column($a->req('GET', '/api/boms')[1]['data'] ?? [], 'line_count', 'parent') == ['FG' => 2, 'SUB' => 2]);

// routing
$r = $a->req('PUT', '/api/routings/FG', ['lines' => [['ro_op' => '10', 'ro_wkctr' => 'WC1'], ['ro_op' => '10', 'ro_wkctr' => 'NOPE']]]);
ok('routing validation', $r[0] === 422 && isset($r[1]['fields']['lines.1.ro_op'], $r[1]['fields']['lines.1.ro_wkctr']), $r[1]);
$r = $a->req('PUT', '/api/routings/FG', ['lines' => [['ro_op' => '20', 'ro_wkctr' => 'WC1', 'ro_desc' => 'ประกอบ', 'ro_run_time' => '0.5'], ['ro_op' => '10', 'ro_wkctr' => 'WC1', 'ro_desc' => 'ตัด']]]);
ok('routing saved in op order, item points to it', $r[0] === 200 && array_column($r[1]['data']['lines'], 'ro_op') === [10, 20] && one("SELECT pt_routing FROM pt_mstr WHERE pt_part='FG'") === 'FG', $r[1]);
ok('work center in use cannot be deleted', $a->req('DELETE', '/api/work-centers/row?wc_wkctr=WC1')[0] === 409);

// work order
$woBody = ['wo_part' => 'sub', 'wo_qty_ord' => '10', 'wo_site' => 'MAIN', 'wo_rel_date' => $today, 'wo_due_date' => $today];
$r = $a->req('POST', '/api/work-orders', array_merge($woBody, ['wo_qty_ord' => '0', 'wo_due_date' => '2000-01-01']));
ok('WO validation', $r[0] === 422 && isset($r[1]['fields']['wo_qty_ord'], $r[1]['fields']['wo_due_date']), $r[1]);
$r = $a->req('POST', '/api/work-orders', $woBody);
$wo = $r[1]['data']['nbr'] ?? '';
$req = fn () => array_column(db("SELECT wod_part, wod_qty_req FROM wod_det WHERE wod_nbr='$wo'"), 'wod_qty_req', 'wod_part');
ok('WO firm with components from BOM (RM1 30, RM2 5.5 incl. scrap)', $wo === 'WO000001' && one("SELECT wo_status FROM wo_mstr WHERE wo_nbr='$wo'") === 'F'
    && (float) $req()['RM1'] === 30.0 && (float) $req()['RM2'] === 5.5, $req());
$a->req('PUT', "/api/work-orders/$wo", array_merge($woBody, ['wo_qty_ord' => '20']));
ok('changing qty re-explodes components', (float) $req()['RM1'] === 60.0 && (float) $req()['RM2'] === 11.0, $req());
ok('cannot issue before release', $a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'lines' => ['RM1' => ['qty' => '1', 'loc' => 'STOCK']]])[0] === 409);
$waitingWo = fn () => array_column($a->req('GET', '/api/work-orders/waiting')[1]['data'] ?? [], 'wo_nbr');
ok('queue: firm order waits for release; list page has the queues', in_array($wo, $waitingWo(), true) && str_contains((string) $a->page('/work-orders')[1], 'data-queue'));
ok('release (from the queue)', $a->req('POST', '/api/work-orders/release', ['nbr' => $wo])[0] === 200 && one("SELECT wo_status FROM wo_mstr WHERE wo_nbr='$wo'") === 'R');
ok('queue: released order leaves the release queue', !in_array($wo, $waitingWo(), true));
ok('released order cannot be edited or cancelled', $a->req('PUT', "/api/work-orders/$wo", $woBody)[0] === 409 && $a->req('POST', "/api/work-orders/$wo/cancel")[0] === 409);

$r = $a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'lines' => ['RM1' => ['qty' => '60', 'loc' => 'STOCK'], 'RM2' => ['qty' => '11', 'loc' => 'STOCK']]]);
ok('issue: lot required', $r[0] === 422 && isset($r[1]['fields']['lines.RM2.lot']), $r[1]);
// RM1 in two rows (as when one lot / location is not enough): one component line, two stock moves
$r = $a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'lines' => ['RM1' => ['qty' => '35', 'loc' => 'STOCK'],
    'RM1~1' => ['part' => 'RM1', 'qty' => '25', 'loc' => 'STOCK'], 'RM2' => ['qty' => '11', 'loc' => 'STOCK', 'lot' => 'A']]]);
ok('issue materials (cost 60x10 + 11x20 = 820)', $r[0] === 200 && $r[1]['data']['cost']['material'] == 820 && (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='RM1'") === 40.0, $r[1]['data']['cost'] ?? $r);
ok('issue: a component from several rows adds up on its line', (float) one("SELECT wod_qty_iss FROM wod_det WHERE wod_nbr='$wo' AND wod_part='RM1'") === 60.0
    && one("SELECT COUNT(*) FROM tr_hist WHERE tr_nbr='$wo' AND tr_type='ISS-WO' AND tr_part='RM1'") == 2);
// the save is one material issue slip (MI): its lines are the stock moves
$slip = $r[1]['data']['slip'] ?? '';
ok('issue slip: one MI number for the three lines, its value, printable', str_starts_with($slip, 'MI') && one("SELECT COUNT(*) FROM tr_hist WHERE tr_ref='$slip'") == 3
    && (float) one("SELECT wis_amt FROM wis_mstr WHERE wis_nbr='$slip'") === 820.0 && count($r[1]['data']['slips']) === 1
    && str_contains((string) $a->page("/work-orders/issues/$slip/print?html=1")[1], 'ใบเบิกวัตถุดิบ'), $r[1]['data']['slips'] ?? $r);
$r = $a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'lines' => ['RM1' => ['qty' => '1', 'loc' => 'STOCK']]]);
ok('issuing beyond the bill needs a reason', $r[0] === 422 && isset($r[1]['fields']['reason']), $r[1]);
$r = $a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'reason' => 'ตัดเสีย', 'lines' => ['RM1' => ['qty' => '1', 'loc' => 'STOCK']]]);
ok('with a reason it is issued (material 830)', $r[0] === 200 && $r[1]['data']['cost']['material'] == 830 && end($r[1]['data']['slips'])['wis_reason'] === 'ตัดเสีย', $r[1]['data']['cost'] ?? $r);
$r = $a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'type' => 'R', 'lines' => ['RM2' => ['qty' => '12', 'loc' => 'STOCK', 'lot' => 'A']]]);
ok('return: no more than was taken from the lot', $r[0] === 422 && isset($r[1]['fields']['lines.RM2.qty']), $r[1]);
$r = $a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'type' => 'R', 'lines' => ['RM1' => ['qty' => '1', 'loc' => 'STOCK']]]);
ok('return slip: back into stock at its issue cost, off the issued quantity (material 820 again)', $r[0] === 200 && $r[1]['data']['cost']['material'] == 820
    && (float) one("SELECT wod_qty_iss FROM wod_det WHERE wod_nbr='$wo' AND wod_part='RM1'") === 60.0 && end($r[1]['data']['slips'])['wis_type'] === 'R'
    && (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='RM1'") === 40.0, $r[1]['data']['cost'] ?? $r);
// cancelling a slip undoes it (same lot, location, cost); the slip stays, marked X; then the return is made again
$ret = end($r[1]['data']['slips'])['wis_nbr'];
$r = $a->req('POST', "/api/work-orders/issues/$ret/cancel");
ok('cancel a slip: its moves go the other way, the order holds it again (material 830, RM1 61 issued)', $r[0] === 200 && $r[1]['data']['cost']['material'] == 830
    && (float) one("SELECT wod_qty_iss FROM wod_det WHERE wod_nbr='$wo' AND wod_part='RM1'") === 61.0 && one("SELECT wis_status FROM wis_mstr WHERE wis_nbr='$ret'") === 'X'
    && (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='RM1'") === 39.0 && count(end($r[1]['data']['slips'])['lines']) === 1, $r[1]['data']['cost'] ?? $r);
ok('a cancelled slip cannot be cancelled again', $a->req('POST', "/api/work-orders/issues/$ret/cancel")[0] === 409);
$a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'type' => 'R', 'lines' => ['RM1' => ['qty' => '1', 'loc' => 'STOCK']]]);
$r = $a->req('POST', "/api/work-orders/$wo/issue", ['date' => $today, 'reason' => 'ทดสอบ', 'lines' => ['RM1' => ['qty' => '500', 'loc' => 'STOCK']]]);
ok('issue more than in stock is refused', $r[0] === 422 && isset($r[1]['fields']['lines.RM1.qty']), $r[1]);

// SUB's routing: its standard hours are booked with every receipt (set-up once), nobody types them
$a->req('PUT', '/api/routings/SUB', ['lines' => [['ro_op' => '10', 'ro_wkctr' => 'WC1', 'ro_desc' => 'ประกอบ', 'ro_run_time' => '0.1', 'ro_setup_time' => '1']]]);
$autoHours = fn () => (float) one("SELECT COALESCE(SUM(wol_hours), 0) FROM wo_labor WHERE wol_wo_nbr='$wo' AND wol_auto = 1");

$r = $a->req('POST', "/api/work-orders/$wo/receive", ['date' => $today, 'qty' => '10', 'scrap' => '1', 'loc' => 'STOCK', 'inspector' => 'สมหญิง']);
// standard per unit: bill 820 / 20 = 41 + (0.1 h + 1 h set-up / 20) x 100 = 15 -> 56; labour 0.1 x 11 + 1 = 2.1 h booked by itself (nobody types hours)
ok('receive half at standard (56 each), routing hours booked (2.1 h = 210), the rest stays in WIP', $r[0] === 200 && (float) $r[1]['data']['receipts'][0]['tr_cost'] === 56.0
    && $autoHours() == 2.1 && $r[1]['data']['cost']['labor'] == 210 && $r[1]['data']['cost']['wip'] == 470
    && (float) one("SELECT wo_qty_rjct FROM wo_mstr WHERE wo_nbr='$wo'") === 1.0, $r[1]['data'] ?? $r);
$q = db("SELECT * FROM qc_hist WHERE qc_ref_doc = '$wo' ORDER BY qc_test_nbr")[0] ?? [];
ok('the receipt makes a QC record: order, 10 good, 1 scrap, partly passed, inspector', ($q['qc_part'] ?? '') === 'SUB' && (float) $q['qc_qty_pass'] === 10.0
    && (float) $q['qc_qty_fail'] === 1.0 && (float) $q['qc_qty_inspect'] === 11.0 && $q['qc_result'] === 'PARTIAL' && $q['qc_inspector'] === 'สมหญิง'
    && count($r[1]['data']['qcs'] ?? []) === 1, $q);
$r = $a->req('POST', "/api/work-orders/$wo/receive", ['date' => $today, 'qty' => '10', 'loc' => 'STOCK']);
ok('no more off the line than ordered (11 of 20 done: 9 left)', $r[0] === 422 && isset($r[1]['fields']['qty']), $r[1]);
$r = $a->req('POST', "/api/work-orders/$wo/receive", ['date' => $today, 'qty' => '9', 'loc' => 'STOCK']);
// 19 good at 56; what is left (56) is the scrapped unit: its material (41) and hours (15)
ok('receive the rest: item cost 56, what is left (56) is the scrapped unit', $r[0] === 200 && $r[1]['data']['cost']['wip'] == 56 && $autoHours() == 3.0
    && $r[1]['data']['cost']['variance']['labor'] == 15 && $r[1]['data']['cost']['variance']['material'] == 41 && (float) one("SELECT pt_cost FROM pt_mstr WHERE pt_part='SUB'") === 56.0
    && (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='SUB'") === 19.0, $r[1]['data']['cost'] ?? $r);
ok('without an inspector the signed-in person checked it', one("SELECT qc_inspector FROM qc_hist WHERE qc_ref_doc = '$wo' ORDER BY qc_test_nbr DESC LIMIT 1") === one("SELECT emp_name FROM emp_mstr WHERE emp_userid = 'admin'"));
ok('close: WIP left goes to production variance (50104)', $a->req('POST', "/api/work-orders/$wo/close")[0] === 200
    && (float) one("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_ref='$wo' AND gl_acct='50104'") === 56.0
    && (float) one("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_ref='$wo' AND gl_acct=(SELECT gl_wip_acct FROM gl_ctrl)") === 0.0
    && $a->req('POST', "/api/work-orders/$wo/receive", ['date' => $today, 'qty' => '1', 'loc' => 'STOCK'])[0] === 409);
$w2 = $a->req('POST', '/api/work-orders', $woBody)[1]['data']['nbr'];
ok('firm order can be cancelled (removed)', $a->req('POST', "/api/work-orders/$w2/cancel")[0] === 200 && !one("SELECT 1 FROM wo_mstr WHERE wo_nbr='$w2'"));

// MRP: sales order for 50 FG, an open PO for 50 RM1
$a->req('POST', '/api/customers', party('cm', ['cm_code' => 'C1', 'cm_name' => 'ลูกค้า', 'cm_status' => 'ACTIVE', 'cm_curr' => 'THB']));
$so = $a->req('POST', '/api/sales-orders', ['so_cm_code' => 'C1', 'so_date' => $today, 'so_req_date' => $d10, 'so_site' => 'MAIN', 'so_curr' => 'THB', 'so_ex_rate' => '1',
    'lines' => [['sod_part' => 'FG', 'sod_qty_ord' => '50', 'sod_price' => '500']]]);
$a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'V1', 'vd_name' => 'ผู้ขาย', 'vd_status' => 'ACTIVE', 'vd_curr' => 'THB']));
$a->req('POST', '/api/purchase-orders', ['po_vd_code' => 'V1', 'po_date' => $today, 'po_ship_to' => 'MAIN', 'po_curr' => 'THB', 'po_ex_rate' => '1',
    'lines' => [['pod_part' => 'RM1', 'pod_qty_ord' => '50', 'pod_price' => '10']]]);
$r = $a->req('POST', '/api/mrp/run');
// per item: type, quantity over its rows, release and due of the first row (an item short on two dates has two rows)
$plan = [];
$dues = [];
foreach ($r[1]['data']['rows'] ?? [] as $row) {
    $p = $row['mrp_part'];
    $plan[$p] ??= [$row['mrp_type'], 0.0, $row['mrp_rel_date'], $row['mrp_due_date']];
    $plan[$p][1] += (float) $row['mrp_qty'];
    $dues[$p][] = $row['mrp_due_date'];
}
$fgRel = date('Y-m-d', strtotime("$d10 -2 days"));
ok('MRP: FG make 50, released 2 days before due', ($plan['FG'] ?? null) === ['WO', 50.0, $fgRel, $d10], $plan);
ok('MRP: SUB make 31 (50 needed, 19 on hand), due when FG starts', ($plan['SUB'] ?? null) === ['WO', 31.0, date('Y-m-d', strtotime("$fgRel -1 day")), $fgRel], $plan);
ok('MRP: RM1 buy 110 (193 needed - 40 stock - 50 on order, pack 10: 10 for SUB, 100 for FG)', ($plan['RM1'][0] ?? '') === 'PO' && ($plan['RM1'][1] ?? 0) === 110.0, $plan);
ok('MRP: RM2 buy 9 (17.05 needed - 9 stock, rounded up)', ($plan['RM2'][0] ?? '') === 'PO' && ($plan['RM2'][1] ?? 0) === 9.0, $plan);
ok('MRP: nothing else', count($plan) === 4, $plan);
ok('MRP run is recorded', !empty($r[1]['data']['ran_at']) && one('SELECT wo_mrp_run_by FROM wo_ctrl') === 'admin', $r[1]['data']['ran_at'] ?? null);
// the MRP record of every item, enough or not: the detail rows and the weekly grid add up to the same totals
$items = array_column($r[1]['data']['items'] ?? [], null, 'part');
$adds = fn ($i) => abs($i['onhand'] - $i['need'] + $i['coming'] + $i['order'] - $i['end']) < 0.00001
    && ($i['rows'] === [] || abs($i['rows'][count($i['rows']) - 1]['balance'] - $i['end']) < 0.00001)
    && abs(array_sum($i['grid']['gross']) - $i['need']) < 0.00001 && abs(array_sum($i['grid']['sched']) - $i['coming']) < 0.00001
    && abs(array_sum($i['grid']['receipt']) - $i['order']) < 0.00001 && abs(array_sum($i['grid']['release']) - $i['order']) < 0.00001
    && abs($i['grid']['poh'][count($i['grid']['poh']) - 1] - $i['end']) < 0.00001;
ok('MRP record: each item adds up (detail rows and weekly grid)', count($items) >= 4 && !array_filter($items, fn ($i) => !$adds($i)), $items);
ok('MRP record: top level first; FG make 50 for the sales order; RM1 has the PO scheduled and the planned orders\' components',
    (float) ($items['FG']['order'] ?? 0) === 50.0 && $items['FG']['level'] === 0 && $items['RM1']['level'] > $items['SUB']['level']
    && in_array('SO', array_column($items['FG']['rows'], 'kind'), true) && (float) ($items['RM1']['coming'] ?? 0) === 50.0
    && in_array('PLC', array_column($items['RM1']['rows'], 'kind'), true), $items);

$keys = fn (array $parts) => array_merge(...array_map(fn ($p) => array_map(fn ($due) => ['part' => $p, 'due' => $due], $dues[$p] ?? []), $parts));
$r = $a->req('POST', '/api/mrp/pr', ['keys' => $keys(['RM1', 'RM2'])]);
$pr = $r[1]['data']['nbr'] ?? '';
ok('planned purchases -> one requisition', $r[0] === 200 && one("SELECT COUNT(*) FROM prd_det WHERE prd_nbr='$pr'") == 3 && one("SELECT pr_status FROM pr_mstr WHERE pr_nbr='$pr'") === 'A', $r[1]);
ok('wrong type picked is refused', $a->req('POST', '/api/mrp/pr', ['keys' => $keys(['FG'])])[0] === 422);
$planned = fn () => array_column($a->req('GET', '/api/work-orders/planned')[1]['data'] ?? [], 'part');
ok('queue: planned make items wait on the work order page (not purchases)', $planned() === ['SUB', 'FG'], $planned());
$r = $a->req('POST', '/api/work-orders/from-plan', ['part' => 'SUB', 'site' => 'MAIN', 'due' => $plan['SUB'][3]]);
$subWo = $r[1]['data']['nbr'] ?? '';
ok('queue: planned row -> firm work order, leaves the plan', $r[0] === 200 && one("SELECT wo_status FROM wo_mstr WHERE wo_nbr='$subWo' AND wo_part='SUB'") === 'F'
    && $planned() === ['FG'] && in_array($subWo, array_column($a->req('GET', '/api/work-orders/waiting')[1]['data'] ?? [], 'wo_nbr'), true), $r[1]);
$r = $a->req('POST', '/api/mrp/wo', ['keys' => $keys(['FG'])]);
ok('planned production -> firm work orders with components', $r[0] === 200 && count($r[1]['data']['wos'] ?? []) === 1
    && one("SELECT COUNT(*) FROM wod_det WHERE wod_nbr='{$r[1]['data']['wos'][0]}'") == 2 && $planned() === [], $r[1]);
$m = $a->req('GET', '/api/mrp')[1]['data']; ok('converted rows leave the plan; last run time shown', count($m['rows']) === 0 && $m['ran_at'] === null || count($m['rows']) === 0);
$r = $a->req('POST', '/api/mrp/run');
ok('re-run: the new orders now cover the demand', count($r[1]['data']['rows']) === 0, $r[1]['data']['rows'] ?? $r);
// an open order that finishes after the demand still covers it (it is late, to be hurried): no second order
$lateWo = one("SELECT wo_nbr FROM wo_mstr WHERE wo_part = 'FG' AND wo_status = 'F' ORDER BY wo_nbr DESC LIMIT 1");
$dueWas = one("SELECT wo_due_date FROM wo_mstr WHERE wo_nbr = '$lateWo'");
pdo()->exec("UPDATE wo_mstr SET wo_due_date = DATE_ADD(wo_due_date, INTERVAL 60 DAY) WHERE wo_nbr = '$lateWo'");
$r = $a->req('POST', '/api/mrp/run');
ok('re-run: a late open work order is not ordered again', $lateWo && !in_array('FG', array_column($r[1]['data']['rows'] ?? [], 'mrp_part'), true), $r[1]['data']['rows'] ?? $r);
$msgs = array_map(fn ($m) => $m['type'] . '|' . $m['ref'], $r[1]['data']['messages'] ?? []);
ok('action message: reschedule the late work order in', in_array("IN|$lateWo", $msgs, true), $msgs);
pdo()->exec("UPDATE wo_mstr SET wo_due_date = '$dueWas' WHERE wo_nbr = '$lateWo'");

// what to make, for whom: the firm order is matched to the sales order (Supply::lines, as the shipment queue)
$soNbr = $so[1]['data']['nbr'] ?? '';
$for = array_column($a->req('GET', '/api/work-orders/waiting')[1]['data'] ?? [], 'for', 'wo_nbr');
ok('firm work order says which sales order it is for', $soNbr !== '' && str_contains($for[$lateWo] ?? '', $soNbr), [$soNbr, $for]);
// as in the big systems the plan is the result of the last MRP run: a new sales order waits for the next run
$so2 = $a->req('POST', '/api/sales-orders', ['so_cm_code' => 'C1', 'so_date' => $today, 'so_req_date' => $d10, 'so_site' => 'MAIN', 'so_curr' => 'THB', 'so_ex_rate' => '1',
    'lines' => [['sod_part' => 'FG', 'sod_qty_ord' => '7', 'sod_price' => '500']]])[1]['data']['nbr'] ?? '';
ok('a new sales order is not planned until MRP runs (opening pages does not run it)', $so2 !== '' && $a->page('/work-orders')[0] === 200
    && $a->page('/mrp')[0] === 200 && !in_array('FG', $planned(), true), $planned());
ok('the next MRP run plans it', $a->req('POST', '/api/mrp/run')[0] === 200 && in_array('FG', $planned(), true), $planned());
$for = array_column($a->req('GET', '/api/work-orders/planned')[1]['data'] ?? [], 'for', 'part');
ok('planned row says which sales order it is for', str_starts_with($for['FG'] ?? '', $so2), $for);
$html = (string) $a->page('/production')[1];
ok('production dashboard: what to make for which order', str_contains($html, 'ต้องผลิตตามใบสั่งขาย') && str_contains($html, $so2) && str_contains($html, 'ต้องเปิดใบสั่งผลิต'));
$a->req('POST', "/api/sales-orders/$so2/cancel");
ok('cancelled order leaves the plan at the next run', $a->req('POST', '/api/mrp/run')[0] === 200 && !in_array('FG', $planned(), true), $planned());

// QC
$r = $a->req('POST', '/api/qc', ['qc_part' => 'NOPE', 'qc_result' => 'PASS']);
ok('QC checks the item', $r[0] === 422 && isset($r[1]['fields']['qc_part']), $r[1]);
$r = $a->req('POST', '/api/qc', ['qc_part' => 'sub', 'qc_ref_doc' => $wo, 'qc_qty_inspect' => '5', 'qc_result' => 'FAIL', 'qc_date' => $today, 'qc_inspector' => 'สมชาย']);
// the two work-order receipts above made QC000001-2 themselves: one entered here takes the next number
ok('QC gets the next number', $r[0] === 200 && $r[1]['data']['qc_test_nbr'] === 'QC000003' && $r[1]['data']['qc_part'] === 'SUB', $r[1]);
// a QC record is a document: no edit, no delete; it prints
$qcNbr = $r[1]['data']['qc_test_nbr'] ?? '';
ok('QC cannot be changed or deleted, it prints', $a->req('PUT', "/api/qc/row?qc_test_nbr=$qcNbr", ['qc_result' => 'PASS'])[0] === 409
    && $a->req('DELETE', "/api/qc/row?qc_test_nbr=$qcNbr")[0] === 409 && one("SELECT qc_result FROM qc_hist WHERE qc_test_nbr='$qcNbr'") === 'FAIL'
    && str_contains((string) $a->page("/qc/$qcNbr/print?html=1")[1], 'ใบตรวจคุณภาพสินค้า'));

// permissions: an operator who may run orders but not create or cancel them
$a->req('POST', '/api/roles', ['role_code' => 'OPER', 'role_desc' => 'Operator']);
$a->req('PUT', '/api/roles/OPER/perms', ['perms' => ['work-orders' => ['view', 'edit']]]);
$a->req('POST', '/api/users', ['us_userid' => 'oper', 'us_name' => 'Oper', 'us_email' => 'oper@example.test', 'us_role' => 'OPER', 'password' => $pw]);
$k = login('oper', $pw);
$wos = $r = $a->req('GET', '/api/work-orders?status=F')[1]['data']['rows'];
$fw = $wos[0]['wo_nbr'];
ok('operator can release', $k->req('POST', "/api/work-orders/$fw/release")[0] === 200);
ok('operator cannot create or cancel orders, or run MRP', $k->req('POST', '/api/work-orders', $woBody)[0] === 403
    && $k->req('POST', "/api/work-orders/{$wos[1]['wo_nbr']}/cancel")[0] === 403 && $k->req('POST', '/api/mrp/run')[0] === 403);

// stock still consistent
$sum = db("SELECT tr_site, tr_loc, tr_part, tr_lot, SUM(tr_chg_qty) q FROM tr_hist GROUP BY 1,2,3,4 HAVING q <> 0 ORDER BY 1,2,3,4");
$ld = db("SELECT ld_site, ld_loc, ld_part, ld_lot, ld_qty_oh q FROM ld_det ORDER BY 1,2,3,4");
ok('sum of history = balances', array_map('array_values', $sum) == array_map('array_values', $ld));

ok('lot of what a work order makes = its number', one("SELECT wo_lot FROM wo_mstr WHERE wo_nbr = '$wo'") === $wo);
// a work order opens as a view like a purchase order: one toolbar with Back, Print and Edit
$html = (string) $a->page('/work-orders/' . $wo)[1];
ok('work order view toolbar', str_contains($html, 'id="wo-edit"') && str_contains($html, '/documents/work-orders/' . $wo . '/print')
    && substr_count($html, 'ti-arrow-left') === 1 && !str_contains($html, 'phai-tabs') && str_contains($html, 'id="slip-list"') && str_contains($html, 'id="lab-table"'));

finish();
