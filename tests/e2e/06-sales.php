<?php

// Sales: price lists, quotations, orders, shipments, invoices, billing
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

$a = login('admin', $pw);
$today = date('Y-m-d');
$plus30 = date('Y-m-d', strtotime('+30 days'));

// setup
$a->req('PUT', '/api/company/row', ['co_name' => 'บริษัท ไผ่ทอง จำกัด', 'co_tax_id' => '0105566000001', 'co_branch' => '00000', 'co_base_curr' => 'THB']);
$a->req('PUT', '/api/company/address', ['ad_house_no' => '1', 'ad_road' => 'ถนนสุขุมวิท', 'ad_state' => 'กรุงเทพมหานคร', 'ad_zip' => '10110']);
$a->req('POST', '/api/exchange-rates', ['ex_curr' => 'USD', 'ex_date' => $today, 'ex_rate' => '35']);
$a->req('POST', '/api/customers', party('cm', ['cm_code' => 'C1', 'cm_name' => 'ร้านลูกค้าหนึ่ง', 'cm_status' => 'ACTIVE', 'cm_terms' => '30DAYS', 'cm_curr' => 'THB', 'cm_tax_code' => 'VAT7',
    'cm_cr_limit' => '10000', 'cm_pr_list' => 'vip', 'cm_tax_id_num' => '0105566009999', 'cm_tax_branch' => '00000', 'cm_house_no' => '99', 'cm_road' => 'ถนนพระราม 4', 'cm_province' => 'กรุงเทพมหานคร']));
$a->req('POST', '/api/customers', party('cm', ['cm_code' => 'C2', 'cm_name' => 'Overseas Buyer', 'cm_status' => 'ACTIVE', 'cm_terms' => 'COD', 'cm_curr' => 'USD', 'cm_tax_code' => 'VAT0']));
$a->req('POST', '/api/parts', ['pt_part' => 'P1', 'pt_desc1' => 'สินค้าหนึ่ง', 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_cost_method' => 'AVG', 'pt_price' => '100']);
$a->req('POST', '/api/parts', ['pt_part' => 'L1', 'pt_desc1' => 'สินค้าล็อต', 'pt_um' => 'KG', 'pt_prod_line' => 'FG', 'pt_lot_ser' => 'L', 'pt_price' => '50']);
$a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [
    ['part' => 'P1', 'loc' => 'STOCK', 'qty' => '200', 'cost' => '60'],
    ['part' => 'L1', 'loc' => 'STOCK', 'lot' => 'A', 'qty' => '20', 'expire' => '2027-01-31', 'cost' => '30'],
    ['part' => 'L1', 'loc' => 'STOCK', 'lot' => 'B', 'qty' => '10', 'expire' => '2027-06-30', 'cost' => '30'],
]]);

// price lists
$r = $a->req('POST', '/api/price-lists', ['pc_list' => 'vip', 'pc_part' => 'NOPE', 'pc_um' => 'EA', 'pc_curr' => 'THB', 'pc_start' => $today, 'pc_amt_type' => 'P', 'pc_price' => '1']);
ok('price list checks the part exists', $r[0] === 422 && isset($r[1]['fields']['pc_part']), $r[1]);
$a->req('POST', '/api/price-lists', ['pc_list' => 'VIP', 'pc_part' => 'P1', 'pc_um' => 'EA', 'pc_curr' => 'THB', 'pc_start' => $today, 'pc_amt_type' => 'P', 'pc_price' => '90', 'pc_min_qty' => '1']);
$a->req('POST', '/api/price-lists', ['pc_list' => 'VIP', 'pc_part' => 'P1', 'pc_um' => 'EA', 'pc_curr' => 'THB', 'pc_start' => date('Y-m-d', strtotime('-1 day')), 'pc_amt_type' => 'P', 'pc_price' => '80', 'pc_min_qty' => '10']);
$a->req('POST', '/api/price-lists', ['pc_list' => 'VIP', 'pc_part' => 'P1', 'pc_um' => 'EA', 'pc_curr' => 'THB', 'pc_start' => '2020-01-01', 'pc_expire' => '2020-12-31', 'pc_amt_type' => 'P', 'pc_price' => '1', 'pc_min_qty' => '50']);
$a->req('POST', '/api/price-lists', ['pc_list' => 'ALL', 'pc_part' => 'L1', 'pc_um' => 'KG', 'pc_curr' => 'THB', 'pc_start' => $today, 'pc_amt_type' => 'D', 'pc_disc_pct' => '10', 'pc_min_qty' => '1']);
$p = fn ($q) => $a->req('GET', '/api/sales/price?' . http_build_query($q))[1]['data'];
ok('price: customer list, qty 1 -> 90', $p(['part' => 'P1', 'customer' => 'C1', 'qty' => 1])['price'] == 90);
ok('price: qty break 10 -> 80; expired row ignored at 60', $p(['part' => 'P1', 'customer' => 'C1', 'qty' => 10])['price'] == 80 && $p(['part' => 'P1', 'customer' => 'C1', 'qty' => 60])['price'] == 80);
ok('price: promotion ALL gives 10% off', ($x = $p(['part' => 'L1', 'customer' => 'C1', 'qty' => 2]))['price'] == 50 && $x['disc'] == 10 && $x['source'] === 'ALL', $x);
ok('price: foreign customer gets item price in USD', abs($p(['part' => 'P1', 'customer' => 'C2', 'qty' => 1])['price'] - round(100 / 35, 5)) < 0.00001);

foreach (['/quotations', '/quotations/new', '/sales-orders', '/sales-orders/new', '/shipments', '/shipments/new', '/invoices', '/invoices/new',
          '/billings', '/billings/new', '/price-lists', '/settings/sales'] as $pg) {
    ok("page $pg", $a->page($pg)[0] === 200);
}

// quotation
$r = $a->req('POST', '/api/quotations', ['qt_cm_code' => 'C1', 'qt_date' => $today, 'qt_expire' => '2000-01-01', 'qt_curr' => 'THB', 'qt_ex_rate' => '1', 'lines' => []]);
ok('quotation validation', $r[0] === 422 && isset($r[1]['fields']['qt_expire'], $r[1]['fields']['lines']), $r[1]);
$qtBody = ['qt_cm_code' => 'c1', 'qt_date' => $today, 'qt_expire' => $plus30, 'qt_cr_terms' => '30DAYS', 'qt_curr' => 'THB', 'qt_ex_rate' => '1', 'qt_tax_code' => 'VAT7',
    'lines' => [['qtd_part' => 'P1', 'qtd_qty' => '100', 'qtd_price' => '80'], ['qtd_part' => 'L1', 'qtd_qty' => '10', 'qtd_price' => '50', 'qtd_disc_pct' => '10']]];
$r = $a->req('POST', '/api/quotations', $qtBody);
$qt = $r[1]['data']['nbr'] ?? '';
ok('quotation totals (8000 + 450 = 8450, VAT 591.50)', $r[0] === 200 && (float) one("SELECT qt_tot_amt FROM qt_mstr WHERE qt_nbr='$qt'") === 9041.5 && one("SELECT qt_tax_code FROM qt_mstr WHERE qt_nbr='$qt'") === 'VAT7', $r[1]);
ok('quotation print in Thai words', ($pr = $a->page("/quotations/$qt/print?html=1"))[0] === 200 && str_contains($pr[1], 'เก้าพันสี่สิบเอ็ดบาทห้าสิบสตางค์') && str_contains($pr[1], 'ร้านลูกค้าหนึ่ง'));
$waiting = fn (string $api, string $key) => array_column($a->req('GET', $api)[1]['data'] ?? [], $key);
ok('work queues on the list pages', array_filter(['/sales-orders', '/shipments', '/invoices', '/billings'], fn ($pg) => !str_contains((string) $a->page($pg)[1], 'data-queue')) === []);
ok('open quotation waits for a sales order', in_array($qt, $waiting('/api/sales-orders/waiting', 'qt_nbr'), true));
$r = $a->req('POST', '/api/sales-orders/from-qt', ['qt' => $qt]);
$so1 = $r[1]['data']['nbr'] ?? '';
$html = (string) $a->page("/sales-orders/$so1")[1];
ok('SO view: one toolbar (no layout bar), print action', str_contains($html, 'documents\/sales-orders\/{nbr}\/print') && !str_contains($html, 'ti-arrow-left'));
ok('quotation -> sales order (within credit: O)', $r[0] === 200 && one("SELECT qt_status FROM qt_mstr WHERE qt_nbr='$qt'") === 'W' && one("SELECT so_status FROM so_mstr WHERE so_nbr='$so1'") === 'O'
    && !isset($r[1]['data']['notice']), $r[1]);
ok('converted quotation leaves the queue; order waits for a shipment', !in_array($qt, $waiting('/api/sales-orders/waiting', 'qt_nbr'), true)
    && in_array($so1, $waiting('/api/shipments/waiting', 'so_nbr'), true));
$line = current(array_filter($a->req('GET', '/api/shipments/waiting')[1]['data'] ?? [], fn ($r) => $r['so_nbr'] === $so1));
ok('shipment queue is by order line: part, quantity left, ship-to place, stock state', $line && $line['sod_part'] !== '' && (float) $line['open_qty'] > 0
    && array_key_exists('ship_place', $line) && in_array($line['stock_color'], ['green', 'orange', 'red', 'secondary'], true)
    && ($line['stock_color'] === 'green') === ($line['supply'] === null), $line);
ok('won quotation cannot be edited or converted again', $a->req('PUT', "/api/quotations/$qt", $qtBody)[0] === 409 && $a->req('POST', '/api/sales-orders/from-qt', ['qt' => $qt])[0] === 422);
$r2 = $a->req('POST', '/api/quotations', $qtBody);
$qt2 = $r2[1]['data']['nbr'];
ok('mark quotation lost', $a->req('POST', "/api/quotations/$qt2/lost")[0] === 200 && one("SELECT qt_status FROM qt_mstr WHERE qt_nbr='$qt2'") === 'L');

// credit limit: C1 has 10000, SO1 is 9041.50
$soBody = ['so_cm_code' => 'C1', 'so_date' => $today, 'so_site' => 'MAIN', 'so_cr_terms' => '30DAYS', 'so_curr' => 'THB', 'so_ex_rate' => '1', 'so_tax_code' => 'VAT7', 'so_po_ref' => 'PO-CUST-1',
    'lines' => [['sod_part' => 'P1', 'sod_qty_ord' => '20', 'sod_price' => '80']]];
$r = $a->req('POST', '/api/sales-orders', $soBody);
$so2 = $r[1]['data']['nbr'] ?? '';
ok('over credit limit -> saved open with a warning', $r[0] === 200 && one("SELECT so_status FROM so_mstr WHERE so_nbr='$so2'") === 'O' && str_contains($r[1]['data']['notice'] ?? '', '10,000.00'), $r[1]);
$r = $a->req('POST', '/api/sales-orders', array_merge($soBody, ['so_cm_code' => 'C2', 'so_curr' => 'USD', 'so_ex_rate' => '35', 'so_tax_code' => 'VAT0',
    'lines' => [['sod_part' => 'P1', 'sod_qty_ord' => '5', 'sod_price' => '3']]]));
$so3 = $r[1]['data']['nbr'] ?? '';
ok('customer without limit: no hold', $r[0] === 200 && one("SELECT so_status FROM so_mstr WHERE so_nbr='$so3'") === 'O', $r[1]);

// shipments
$r = $a->req('POST', '/api/shipments', ['so' => $so1, 'sh_date' => $today, 'lines' => ['1' => ['qty' => '101', 'loc' => 'STOCK'], '2' => ['qty' => '5', 'loc' => 'STOCK']]]);
ok('shipment: over-ship and missing lot', $r[0] === 422 && isset($r[1]['fields']['lines.1.qty'], $r[1]['fields']['lines.2.lot']), $r[1]);
$r = $a->req('POST', '/api/shipments', ['so' => $so1, 'sh_date' => $today, 'sh_ship_via' => 'Kerry', 'lines' => ['1' => ['qty' => '60', 'loc' => 'STOCK'], '2' => ['qty' => '5', 'loc' => 'STOCK', 'lot' => 'A']]]);
$sh1 = $r[1]['data']['nbr'] ?? '';
ok('partial shipment without invoice', $r[0] === 200 && !isset($r[1]['data']['inv']) && one("SELECT sh_status FROM sh_mstr WHERE sh_nbr='$sh1'") === 'C', $r[1]);
ok('stock out and order lines updated', (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='P1'") === 140.0 && (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='L1' AND ld_lot='A'") === 15.0
    && (float) one("SELECT sod_qty_shp FROM sod_det WHERE sod_nbr='$so1' AND sod_line=1") === 60.0 && one("SELECT so_status FROM so_mstr WHERE so_nbr='$so1'") === 'O');
ok('history ISS-SO at cost with sale price', one("SELECT COUNT(*) FROM tr_hist WHERE tr_type='ISS-SO' AND tr_nbr='$sh1'") == 2
    && (float) one("SELECT tr_cost FROM tr_hist WHERE tr_nbr='$sh1' AND tr_part='P1'") === 60.0 && (float) one("SELECT tr_price FROM tr_hist WHERE tr_nbr='$sh1' AND tr_part='P1'") === 80.0);
ok('delivery note print', ($pr = $a->page("/shipments/$sh1/print?html=1"))[0] === 200 && str_contains($pr[1], 'Kerry') && str_contains($pr[1], 'สินค้าหนึ่ง') && str_contains($pr[1], 'ใบส่งของ'));
// one order line out of two lots: a row each ("2" and "2~1"), together no more than is left to ship
$r = $a->req('POST', '/api/shipments', ['so' => $so1, 'sh_date' => $today, 'lines' => ['1' => ['qty' => '40', 'loc' => 'STOCK'], '2' => ['qty' => '3', 'loc' => 'STOCK', 'lot' => 'A'], '2~1' => ['qty' => '3', 'loc' => 'STOCK', 'lot' => 'B']]]);
ok('rows of one order line together cannot pass what is left (5)', $r[0] === 422 && isset($r[1]['fields']['lines.2~1.qty']), $r[1]);
$r = $a->req('POST', '/api/shipments', ['so' => $so1, 'sh_date' => $today, 'invoice' => true, 'lines' => ['1' => ['qty' => '40', 'loc' => 'STOCK'], '2' => ['qty' => '3', 'loc' => 'STOCK', 'lot' => 'A'], '2~1' => ['qty' => '2', 'loc' => 'STOCK', 'lot' => 'B']]]);
$sh2 = $r[1]['data']['nbr'] ?? '';
$inv2 = $r[1]['data']['inv'] ?? '';
ok('final shipment with invoice now closes the order', $r[0] === 200 && $inv2 !== '' && one("SELECT so_status FROM so_mstr WHERE so_nbr='$so1'") === 'C' && one("SELECT sh_status FROM sh_mstr WHERE sh_nbr='$sh2'") === 'I', $r[1]);
ok('shipped order leaves the shipment queue', !in_array($so1, $waiting('/api/shipments/waiting', 'so_nbr'), true));
$html1 = (string) $a->page("/shipments/$sh1")[1];
$html2 = (string) $a->page("/shipments/$sh2")[1];
ok('saved shipment opens as a view: waiting one offers make invoice, invoiced one links its invoice', str_contains($html1, 'phai-view-mode')
    && str_contains($html1, "/invoices/new?sh=$sh1") && str_contains($html2, "/invoices/$inv2") && !str_contains($html2, '/invoices/new?sh=')
    && substr_count($html1, 'ti-arrow-left') === 1);
$ih = db("SELECT * FROM ih_mstr WHERE ih_inv_nbr='$inv2'")[0] ?? [];
ok('invoice amounts (40x80 + 5x45 = 3425, VAT 239.75), due in 30 days', (float) $ih['ih_net_amt'] === 3425.0 && (float) $ih['ih_tax_amt'] === 239.75
    && (float) $ih['ih_tot_amt'] === 3664.75 && $ih['ih_due_date'] === $plus30 && $ih['ih_sh_nbr'] === $sh2, $ih);
ok('two lots: a delivery row each, one invoice line', (int) one("SELECT COUNT(*) FROM shd_det WHERE shd_nbr='$sh2'") === 3 && (int) one("SELECT COUNT(*) FROM ihd_det WHERE ihd_inv_nbr='$inv2'") === 2
    && (float) one("SELECT ihd_qty_inv FROM ihd_det WHERE ihd_inv_nbr='$inv2' AND ihd_part='L1'") === 5.0 && (float) one("SELECT ld_qty_oh FROM ld_det WHERE ld_part='L1' AND ld_lot='B'") === 8.0);
ok('receivable opened', (float) one("SELECT ar_amt FROM ar_mstr WHERE ar_nbr='$inv2'") === 3664.75 && one("SELECT ar_status FROM ar_mstr WHERE ar_nbr='$inv2'") === 'O');

// scheduled sales order (ใบสั่งขายแบบกำหนดส่ง): the customer's delivery schedule keyed in, shipped instalment by instalment
$in = fn (int $days) => date('Y-m-d', strtotime("+$days days"));
$r = $a->req('POST', '/api/sales-orders', array_merge($soBody, ['so_sched' => '1', 'lines' => [['sod_part' => 'P1', 'sod_qty_ord' => '20', 'sod_price' => '80']]]));
$ss = $r[1]['data']['nbr'] ?? '';
$r = $a->req('PUT', "/api/schedules/so/$ss", ['source' => 'CUSTOMER', 'ref' => 'C-REL-1', 'lines' => ['1' => ['rows' => [['date' => $today, 'qty' => '5'], ['date' => $in(30), 'qty' => '15']]]]]);
ok('scheduled SO: schedule saved', $r[0] === 200 && count($r[1]['data']['lines'][0]['rows']) === 2, $r[1]);
ok('customer schedule: the customer\'s release number kept; MRP is not a source on the sales side', ($r[1]['data']['release']['ref'] ?? '') === 'C-REL-1'
    && $r[1]['data']['release']['source'] === 'CUSTOMER' && isset($a->req('PUT', "/api/schedules/so/$ss", ['source' => 'MRP', 'lines' => []])[1]['fields']['source'])
    && $a->req('GET', "/api/schedules/so/$ss/from-mrp")[0] === 404, $r[1]);
// its own 20 come in first, so the stock the later cases count on stays as it was
$a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '20', 'cost' => one("SELECT pt_cost FROM pt_mstr WHERE pt_part='P1'")]]]);
$a->req('POST', '/api/shipments', ['so' => $ss, 'sh_date' => $today, 'lines' => ['1' => ['qty' => '5', 'loc' => 'STOCK']]]);
$a->req('POST', '/api/shipments', ['so' => $ss, 'sh_date' => $today, 'lines' => ['1' => ['qty' => '15', 'loc' => 'STOCK']]]);
$rows = $a->req('GET', "/api/schedules/so/$ss")[1]['data']['lines'][0]['rows'] ?? [];
ok('scheduled SO: shipped against the instalments, the agreement stays open', array_map('floatval', array_column($rows, 'done')) === [5.0, 15.0]
    && one("SELECT so_status FROM so_mstr WHERE so_nbr='$ss'") === 'O', $rows);
ok('a normal order has no schedule', $a->req('GET', "/api/schedules/so/$so1")[0] === 404);

// shipping more than in stock
$r = $a->req('POST', '/api/shipments', ['so' => $so2, 'sh_date' => $today, 'lines' => ['1' => ['qty' => '20', 'loc' => 'STOCK']]]);
ok('ship order 2', $r[0] === 200, $r[1]);
$a->req('POST', '/api/stock/moves', ['kind' => 'issue', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '78']]]);
$r = $a->req('POST', '/api/shipments', ['so' => $so3, 'sh_date' => $today, 'lines' => ['1' => ['qty' => '5', 'loc' => 'STOCK']]]);
ok('not enough stock -> error on the line, nothing saved', $r[0] === 422 && isset($r[1]['fields']['lines.1.qty']) && one("SELECT COUNT(*) FROM sh_mstr WHERE sh_so_nbr='$so3'") == 0, $r[1]);

// invoices from waiting shipments
$list = array_column($a->req('GET', '/api/invoices/shipments')[1]['data'], 'sh_nbr');
ok('waiting shipments', in_array($sh1, $list, true) && !in_array($sh2, $list, true));
ok('invoice queue: waiting shipment listed, invoiced one not', in_array($sh1, $q = $waiting('/api/invoices/waiting', 'sh_nbr'), true) && !in_array($sh2, $q, true));
// the invoice of a shipment is first a draft page (filled from it), then saved with what was changed
$draft = (string) $a->page("/invoices/new?sh=$sh1")[1];
ok('draft invoice page filled from the shipment', str_contains($draft, '"sh":"' . $sh1 . '"') && str_contains($draft, '"fixed":true'));
$r = $a->req('POST', '/api/invoices', ['sh' => $sh1, 'date' => $today, 'ih_due_date' => '2000-01-01']);
ok('draft: due date before the invoice date refused', $r[0] === 422 && isset($r[1]['fields']['ih_due_date']), $r[1]);
$r = $a->req('POST', '/api/invoices', ['sh' => $sh1, 'date' => $today]);
$inv1 = $r[1]['data']['nbr'] ?? '';
ok('invoice a shipment', $r[0] === 200 && (float) one("SELECT ih_tot_amt FROM ih_mstr WHERE ih_inv_nbr='$inv1'") === round((60 * 80 + 5 * 45) * 1.07, 2), $r[1]);
ok('invoiced shipment leaves the invoice queue', !in_array($sh1, $waiting('/api/invoices/waiting', 'sh_nbr'), true));
ok('shipment cannot be invoiced twice', $a->req('POST', '/api/invoices', ['sh' => $sh1, 'date' => $today])[0] === 422);
$pr = $a->page("/invoices/$inv1/print?html=1");
ok('tax invoice print', $pr[0] === 200 && str_contains($pr[1], 'ใบกำกับภาษี') && str_contains($pr[1], '0105566009999') && str_contains($pr[1], 'สำนักงานใหญ่')
    && str_contains($pr[1], '0105566000001') && str_contains($pr[1], '1 ถนนสุขุมวิท'));
$s = $a->req('GET', "/api/invoices/$inv1")[1]['data'];
ok('invoice view data', $s['head']['ih_tax_code'] === 'VAT7' && $s['head']['_editable'] === false && count($s['lines']) === 2);
$sh3 = $a->req('GET', "/api/shipments?q=$so2")[1]['data']['rows'][0]['sh_nbr'];
$inv3 = $a->req('POST', '/api/invoices', ['sh' => $sh3, 'date' => $today])[1]['data']['nbr'];

// billing notes
$open = array_column($a->req('GET', '/api/billings/open-invoices?customer=C1')[1]['data'], 'ih_inv_nbr');
ok('open invoices for billing', count($open) === 3, $open);
ok('billing queue lists unpaid invoices with their customer', count(array_filter($a->req('GET', '/api/billings/waiting')[1]['data'], fn ($x) => $x['ih_inv_nbr'] === $inv1 && $x['customer'] === 'C1')) === 1);
$r = $a->req('POST', '/api/billings', ['customer' => 'C1', 'bl_date' => $today, 'bl_due_date' => $plus30, 'invoices' => [$inv1, $inv2]]);
$bl = $r[1]['data']['nbr'] ?? '';
ok('billing note', $r[0] === 200 && (float) one("SELECT bl_tot_amt FROM bl_mstr WHERE bl_nbr='$bl'") === round((float) one("SELECT SUM(ih_tot_amt) FROM ih_mstr WHERE ih_inv_nbr IN ('$inv1','$inv2')"), 2), $r[1]);
ok('billed invoices leave the open list', array_column($a->req('GET', '/api/billings/open-invoices?customer=C1')[1]['data'], 'ih_inv_nbr') === [$inv3]);
ok('billed invoices leave the billing queue', !array_intersect([$inv1, $inv2], $waiting('/api/billings/waiting', 'ih_inv_nbr')));
ok('same invoice cannot go on two billing notes', $a->req('POST', '/api/billings', ['customer' => 'C1', 'bl_date' => $today, 'invoices' => [$inv1]])[0] === 422);
$html = (string) $a->page("/billings/$bl")[1];
ok('saved billing note opens as a view: invoices, cancel, print', str_contains($html, 'phai-view-mode') && str_contains($html, "/invoices/$inv1")
    && str_contains($html, "/api/billings/$bl/cancel") && str_contains($html, "/billings/$bl/print"));
ok('billing print', ($pr = $a->page("/billings/$bl/print?html=1"))[0] === 200 && str_contains($pr[1], $inv1) && str_contains($pr[1], 'บาท'));
ok('invoice on open billing cannot be cancelled', $a->req('POST', "/api/invoices/$inv1/cancel")[0] === 409);
ok('cancel billing note', $a->req('POST', "/api/billings/$bl/cancel")[0] === 200 && one("SELECT bl_status FROM bl_mstr WHERE bl_nbr='$bl'") === 'C');
ok('then cancel invoice: AR closed, shipment waits again', $a->req('POST', "/api/invoices/$inv1/cancel")[0] === 200
    && one("SELECT ar_status FROM ar_mstr WHERE ar_nbr='$inv1'") === 'C' && one("SELECT sh_status FROM sh_mstr WHERE sh_nbr='$sh1'") === 'C');
ok('a cancelled invoice a billing note named stays (cannot be deleted)', $a->req('POST', "/api/invoices/$inv1/delete")[0] === 409 && one("SELECT ih_status FROM ih_mstr WHERE ih_inv_nbr='$inv1'") === 'C');
$r = $a->req('POST', '/api/invoices', ['sh' => $sh1, 'date' => $today]);
$inv4 = $r[1]['data']['nbr'] ?? '';
ok('re-invoice the shipment', $r[0] === 200 && $inv4 !== '');
// cancelled by mistake right away: deleted, its number goes to the next invoice
ok('only a cancelled invoice can be deleted', $a->req('POST', "/api/invoices/$inv4/delete")[0] === 409);
$a->req('POST', "/api/invoices/$inv4/cancel");
ok('delete the last cancelled invoice: rows and entries gone', $a->req('POST', "/api/invoices/$inv4/delete")[0] === 200
    && one("SELECT COUNT(*) FROM ih_mstr WHERE ih_inv_nbr='$inv4'") == 0 && one("SELECT COUNT(*) FROM gl_trhist WHERE gl_ref='$inv4'") == 0
    && one("SELECT COUNT(*) FROM ar_mstr WHERE ar_nbr='$inv4'") == 0);
ok('its number is used again', ($a->req('POST', '/api/invoices', ['sh' => $sh1, 'date' => $today])[1]['data']['nbr'] ?? '') === $inv4);

// order cancel / close
$r = $a->req('POST', '/api/sales-orders', array_merge($soBody, ['lines' => [['sod_part' => 'P1', 'sod_qty_ord' => '1', 'sod_price' => '80']]]));
$so4 = $r[1]['data']['nbr'];
ok('cancel unshipped order', $a->req('POST', "/api/sales-orders/$so4/cancel")[0] === 200 && one("SELECT so_status FROM so_mstr WHERE so_nbr='$so4'") === 'X');
ok('shipped order cannot be cancelled', $a->req('POST', "/api/sales-orders/$so1/cancel")[0] === 409);

// permissions
$a->req('POST', '/api/roles', ['role_code' => 'SALES', 'role_desc' => 'Sales']);
$a->req('PUT', '/api/roles/SALES/perms', ['perms' => ['quotations' => ['view', 'add', 'edit'], 'sales-orders' => ['view', 'add', 'edit']]]);
$a->req('POST', '/api/users', ['us_userid' => 'sale', 'us_name' => 'Sale', 'us_email' => 'sale@example.test', 'us_role' => 'SALES', 'password' => $pw]);
$k = login('sale', $pw);
$r = $k->req('POST', '/api/sales-orders', $soBody);
ok('sales can create orders', $r[0] === 200, $r[1]);
ok('sales cannot ship, invoice or print', $k->req('POST', '/api/shipments', ['so' => $so2])[0] === 403 && $k->req('POST', '/api/invoices', ['sh' => $sh1])[0] === 403
    && $k->page("/quotations/$qt/print")[0] === 403);

// stock still consistent
// a deposit (ใบรับเงินมัดจำ / ใบกำกับภาษี): its VAT is due when received; the order's tax invoice deducts it
$a->req('POST', '/api/banks', ['bk_bank' => 'BDP', 'bk_desc' => 'ธนาคารทดสอบ', 'bk_acct' => '10201', 'bk_curr' => 'THB', 'bk_active' => true]);
$soDp = $a->req('POST', '/api/sales-orders', array_merge($soBody, ['lines' => [['sod_part' => 'P1', 'sod_qty_ord' => '1', 'sod_price' => '200']]]))[1]['data']['nbr'] ?? '';
ok('deposit: the order picker lists the open baht order', in_array($soDp, array_column($a->req('GET', '/api/deposits/open-sos?q=' . $soDp)[1]['data'] ?? [], 'so_nbr'), true));
ok('deposit over the order refused', $a->req('POST', '/api/deposits', ['so' => $soDp, 'dp_date' => $today, 'dp_bank' => 'BDP', 'amount' => '999'])[0] === 422);
$r = $a->req('POST', '/api/deposits', ['so' => $soDp, 'dp_date' => $today, 'dp_bank' => 'BDP', 'amount' => '107']);
$dp = $r[1]['data']['nbr'] ?? '';
$glDp = fn (string $ref, string $acct) => (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_ref = '$ref' AND gl_acct = '$acct'");
ok('deposit: Dr bank / Cr deposits 100 / Cr output VAT 7', $r[0] === 200 && $glDp($dp, '21201') == -100.0 && $glDp($dp, '21101') == -7.0
    && in_array($dp, array_column($a->req('GET', '/api/vat-sales?month=' . substr($today, 0, 7))[1]['data']['rows'] ?? [], 'nbr'), true), $r[1]);
$r = $a->req('POST', '/api/shipments', ['so' => $soDp, 'sh_date' => $today, 'invoice' => true, 'lines' => ['1' => ['qty' => '1', 'loc' => 'STOCK']]]);
$invDp = $r[1]['data']['inv'] ?? '';
$ih = db("SELECT ih_net_amt, ih_tax_amt, ih_tot_amt, ih_dp_amt FROM ih_mstr WHERE ih_inv_nbr = '$invDp'")[0] ?? [];
ok('tax invoice deducts the deposit: VAT only on what is left', $r[0] === 200 && (float) ($ih['ih_dp_amt'] ?? 0) === 100.0 && (float) $ih['ih_net_amt'] === 100.0
    && (float) $ih['ih_tax_amt'] === 7.0 && $glDp($invDp, '21201') == 100.0 && one("SELECT dp_status FROM dp_mstr WHERE dp_nbr='$dp'") === 'C', [$r[1], $ih]);
ok('deposit in use cannot be cancelled', $a->req('POST', "/api/deposits/$dp/cancel")[0] === 409);
ok('cancelling the tax invoice gives the deposit back', $a->req('POST', "/api/invoices/$invDp/cancel")[0] === 200
    && one("SELECT dp_status FROM dp_mstr WHERE dp_nbr='$dp'") === 'O' && (float) one("SELECT dp_used_amt FROM dp_mstr WHERE dp_nbr='$dp'") === 0.0);
ok('deposit pages and print', $a->page("/deposits/$dp")[0] === 200 && $a->page('/deposits')[0] === 200 && $a->page("/deposits/new?so=$soDp")[0] === 200
    && str_contains((string) $a->page("/deposits/$dp/print?html=1")[1], 'ใบรับเงินมัดจำ'));
ok('unused deposit can be cancelled', $a->req('POST', "/api/deposits/$dp/cancel")[0] === 200 && $glDp($dp, '21201') == 0.0);

// a sales return (ใบรับคืนสินค้า): 2 back from the invoiced shipment, at the cost they left at; then its credit note
ok('sales return: the shipment picker lists the delivery note', in_array($sh2, array_column($a->req('GET', '/api/sales-returns/shipments?q=' . $sh2)[1]['data'] ?? [], 'sh_nbr'), true));
$shl = $a->req('GET', "/api/sales-returns/shipment/$sh2")[1]['data']['lines'][0] ?? [];
$r = $a->req('POST', '/api/sales-returns', ['sh' => $sh2, 'sr_date' => $today, 'sr_reason' => 'ชำรุด', 'lines' => [$shl['shd_line'] => '999']]);
ok('sales return over what was shipped refused', $r[0] === 422, $r[1]);
$r = $a->req('POST', '/api/sales-returns', ['sh' => $sh2, 'sr_date' => $today, 'sr_reason' => 'ชำรุด', 'lines' => [$shl['shd_line'] => '2']]);
$sr = $r[1]['data']['nbr'] ?? '';
$cogs = (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_ref = '$sr' AND gl_acct = '50101'");
ok('sales return: back in stock, cost of sales undone at the shipped cost', $r[0] === 200
    && abs($cogs + round(2 * (float) $shl['cost'], 2)) < 0.005 && (int) one("SELECT COUNT(*) FROM tr_hist WHERE tr_nbr='$sr' AND tr_type='RCT-RTN'") === 1, [$r[1], $cogs, $shl]);
ok('return waits for a credit note', in_array($sr, array_column($a->req('GET', '/api/sales-returns/waiting')[1]['data'] ?? [], 'sr_nbr'), true)
    && str_contains((string) $a->page("/ar-invoices/new?return=$sr")[1], 'CDNG05'));
$part = $shl['shd_part'];
$r = $a->req('POST', '/api/ar-invoices', ['ih_type' => 'CM', 'ih_cm_code' => one("SELECT sh_cm_code FROM sh_mstr WHERE sh_nbr='$sh2'"), 'ih_inv_date' => $today,
    'ih_curr' => 'THB', 'ih_ex_rate' => '1', 'ih_tax_code' => 'VAT7', 'ih_ref_inv' => $inv2, 'ih_reason' => 'CDNG05', 'return' => $sr,
    'lines' => ['1' => ['ihd_part' => $part, 'ihd_desc' => $part, 'ihd_um' => $shl['shd_um'], 'ihd_qty_inv' => '-2', 'ihd_price' => '10']]]);
$cn = $r[1]['data']['nbr'] ?? '';
ok('credit note: keyed negative, kept negative, receivable and VAT go down', $r[0] === 200 && (float) one("SELECT ih_tot_amt FROM ih_mstr WHERE ih_inv_nbr='$cn'") === -21.4
    && (float) one("SELECT ar_amt FROM ar_mstr WHERE ar_nbr='$cn'") === -21.4 && (float) one("SELECT ihd_qty_inv FROM ihd_det WHERE ihd_inv_nbr='$cn'") === -2.0, $r[1]);
$r2 = $a->req('POST', '/api/ar-invoices', ['ih_type' => 'CM', 'ih_cm_code' => one("SELECT sh_cm_code FROM sh_mstr WHERE sh_nbr='$sh2'"), 'ih_inv_date' => $today,
    'ih_curr' => 'THB', 'ih_ex_rate' => '1', 'ih_tax_code' => 'VAT7', 'ih_ref_inv' => $inv2, 'ih_reason' => 'CDNG05',
    'lines' => ['1' => ['ihd_part' => $part, 'ihd_desc' => $part, 'ihd_um' => $shl['shd_um'], 'ihd_qty_inv' => '2', 'ihd_price' => '10']]]);
ok('a credit note with a positive quantity is refused', $r2[0] === 422 && isset($r2[1]['fields']['lines.1.ihd_qty_inv']), $r2[1]);
ok('credit note closes the return', $r[0] === 200 && one("SELECT sr_status FROM sr_mstr WHERE sr_nbr='$sr'") === 'C' && one("SELECT sr_cn_nbr FROM sr_mstr WHERE sr_nbr='$sr'") === $cn, $r[1]);
ok('cancelling the credit note reopens the return', $a->req('POST', "/api/ar-invoices/$cn/cancel")[0] === 200 && one("SELECT sr_status FROM sr_mstr WHERE sr_nbr='$sr'") === 'O');
ok('sales return pages and print', $a->page("/sales-returns/$sr")[0] === 200 && $a->page('/sales-returns')[0] === 200 && $a->page("/sales-returns/$sr/print?html=1")[0] === 200);

$sum = db("SELECT tr_site, tr_loc, tr_part, tr_lot, SUM(tr_chg_qty) q FROM tr_hist GROUP BY 1,2,3,4 HAVING q <> 0 ORDER BY 1,2,3,4");
$ld = db("SELECT ld_site, ld_loc, ld_part, ld_lot, ld_qty_oh q FROM ld_det ORDER BY 1,2,3,4");
ok('sum of history = balances', array_map('array_values', $sum) == array_map('array_values', $ld));

// raw materials are not sold as they are
$a->req('POST', '/api/parts', ['pt_part' => 'RMX', 'pt_desc1' => 'วัตถุดิบขายไม่ได้', 'pt_um' => 'EA', 'pt_prod_line' => 'RM']);
$r = $a->req('POST', '/api/quotations', array_merge($qtBody, ['lines' => [['qtd_part' => 'RMX', 'qtd_qty' => '1', 'qtd_price' => '10']]]));
ok('a raw material cannot be quoted or sold', $r[0] === 422 && isset($r[1]['fields']['lines.0.qtd_part']), $r[1]);
ok('the sales part list leaves raw materials out', !in_array('RMX', array_column($a->req('GET', '/api/stock/parts?sale=1&q=RMX')[1]['data'] ?? [], 'pt_part'), true)
    && in_array('RMX', array_column($a->req('GET', '/api/stock/parts?q=RMX')[1]['data'] ?? [], 'pt_part'), true));

finish();
