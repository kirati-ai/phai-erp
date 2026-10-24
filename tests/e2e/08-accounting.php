<?php

// Accounting: postings, receivables, payables, payments, trial balance
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

/** ledger amount of $acct under one reference (debit +) */
function gl(string $ref, string $acct, string $type = ''): float
{
    return (float) one("SELECT COALESCE(SUM(gl_amt),0) FROM gl_trhist WHERE gl_ref='$ref' AND gl_acct='$acct'" . ($type ? " AND gl_type='$type'" : ''));
}

$a = login('admin', $pw);
$today = date('Y-m-d');

/** keys of a work queue API (views/partials/queue.php) */
function acctQueue(Client $c, string $api, string $key): array
{
    return array_column($c->req('GET', $api)[1]['data'] ?? [], $key);
}

// setup
$a->req('POST', '/api/banks', ['bk_bank' => 'BANK1', 'bk_desc' => 'เงินฝากทดสอบ', 'bk_acct' => '10101', 'bk_curr' => 'THB', 'bk_active' => true]);
$a->req('PUT', '/api/company/row', ['co_name' => 'บริษัท ทดสอบ จำกัด', 'co_tax_id' => '0105566000001', 'co_branch' => '00000', 'co_base_curr' => 'THB']);
$a->req('PUT', '/api/company/address', ['ad_house_no' => '1', 'ad_road' => 'ถนนทดสอบ']);
$a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'V1', 'vd_name' => 'ผู้ขายหนึ่ง', 'vd_status' => 'ACTIVE', 'vd_terms' => '30DAYS', 'vd_curr' => 'THB', 'vd_tax_code' => 'VAT7', 'vd_tax_id_num' => '0105566001234']));
$a->req('POST', '/api/customers', party('cm', ['cm_code' => 'C1', 'cm_name' => 'ลูกค้าหนึ่ง', 'cm_status' => 'ACTIVE', 'cm_terms' => '30DAYS', 'cm_curr' => 'THB', 'cm_tax_code' => 'VAT7', 'cm_cr_limit' => '0']));
$a->req('POST', '/api/parts', ['pt_part' => 'P1', 'pt_desc1' => 'สินค้าหนึ่ง', 'pt_um' => 'EA', 'pt_prod_line' => 'FG', 'pt_cost_method' => 'AVG', 'pt_price' => '100']);

foreach (['/journals', '/journals/new', '/cash-receipts', '/cash-receipts/new', '/payables', '/payables/new', '/vouchers', '/vouchers/new',
          '/wht', '/receivables', '/trial-balance', '/accounts', '/sub-accounts', '/cost-centers', '/banks', '/wht-codes', '/settings/gl'] as $pg) {
    ok("page $pg", $a->page($pg)[0] === 200);
}

// stock: unplanned receipt -> Dr inventory / Cr adjustment
$r = $a->req('POST', '/api/stock/moves', ['kind' => 'receipt', 'date' => $today, 'site' => 'MAIN', 'lines' => [['part' => 'P1', 'loc' => 'STOCK', 'qty' => '100', 'cost' => '10']]]);
$ic1 = one("SELECT tr_nbr FROM tr_hist WHERE tr_type='RCT-UNP' ORDER BY tr_trnbr DESC LIMIT 1");
ok('stock receipt posts Dr 12101 1000 / Cr 59901', $r[0] === 200 && gl($ic1, '12101', 'IC') == 1000 && gl($ic1, '59901', 'IC') == -1000, $r[1]);

// purchase: PO 50 x 20 + VAT 7%, receive -> Dr inventory / Cr GRNI
$po = $a->req('POST', '/api/purchase-orders', ['po_vd_code' => 'V1', 'po_date' => $today, 'po_ship_to' => 'MAIN', 'po_cr_terms' => '30DAYS', 'po_curr' => 'THB', 'po_ex_rate' => '1',
    'po_tax_code' => 'VAT7', 'lines' => [['pod_part' => 'P1', 'pod_qty_ord' => '50', 'pod_price' => '20']]])[1]['data']['nbr'];
$rc = $a->req('POST', '/api/receipts', ['po' => $po, 'rc_date' => $today, 'rc_site' => 'MAIN', 'rc_inv_nbr' => 'TAX-V1-1', 'lines' => ['1' => ['qty' => '50', 'loc' => 'STOCK']]])[1]['data']['nbr'];
ok('PO receipt posts Dr 12101 1000 / Cr GRNI 20103', gl($rc, '12101', 'IC') == 1000 && gl($rc, '20103', 'IC') == -1000);

// payables
$r = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => '', 'receipts' => []]);
ok('payable validation', $r[0] === 422 && isset($r[1]['fields']['ap_inv_nbr'], $r[1]['fields']['lines']), $r[1]);
ok('uninvoiced receipts listed', count($a->req('GET', '/api/payables/receipts?vendor=V1')[1]['data']) === 1);
ok('queue: receipt waits for a payable, shown above the payables list', in_array($rc, acctQueue($a, '/api/payables/waiting', 'rc_nbr'), true)
    && str_contains((string) $a->page('/payables')[1], 'data-queue'));
ok('old receipts page goes to payables', ($p = $a->page('/payables/receipts'))[0] === 302 && str_ends_with((string) $p[2], '/payables'));
$r = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'TAX-V1-1', 'receipts' => [$rc]]);
$ap1 = $r[1]['data']['nbr'] ?? '';
$h = db("SELECT * FROM ap_mstr WHERE ap_ref='$ap1'")[0] ?? [];
ok('payable from receipt: 1070, due by terms, receipt marked', $r[0] === 200 && (float) $h['ap_amt'] === 1070.0 && $h['ap_due_date'] === date('Y-m-d', strtotime('+30 days'))
    && one("SELECT rc_ap_ref FROM rc_mstr WHERE rc_nbr='$rc'") === $ap1, [$r[1], $h]);
ok('payable posts Dr GRNI 1000, Dr VAT in 70 / Cr AP 1070', gl($ap1, '20103', 'AP') == 1000 && gl($ap1, '13101', 'AP') == 70 && gl($ap1, '20101', 'AP') == -1070);
ok('GRNI cleared for the receipt', abs((float) one("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_acct='20103'")) < 0.001);
ok('queue: receipt on a payable leaves the queue', !in_array($rc, acctQueue($a, '/api/payables/waiting', 'rc_nbr'), true));
ok('same receipt / same vendor invoice cannot be booked twice',
    $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'X2', 'receipts' => [$rc]])[0] === 422
    && $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'TAX-V1-1', 'lines' => [['acct' => '50301', 'amt' => '1']]])[0] === 422);
$r = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'RENT-10', 'tax_code' => 'VAT7',
    'lines' => [['acct' => '50301', 'desc' => 'ค่าเช่า', 'amt' => '10000'], ['acct' => 'NOPE', 'amt' => '1']]]);
ok('expense line account checked', $r[0] === 422 && isset($r[1]['fields']['lines.1.acct']), $r[1]);
$r = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'RENT-10', 'tax_code' => 'VAT7', 'lines' => [['acct' => '50301', 'desc' => 'ค่าเช่า', 'amt' => '10000']]]);
$ap2 = $r[1]['data']['nbr'] ?? '';
ok('expense payable 10000 + VAT 700', $r[0] === 200 && (float) one("SELECT ap_amt FROM ap_mstr WHERE ap_ref='$ap2'") === 10700.0 && gl($ap2, '50301') == 10000 && gl($ap2, '20101') == -10700);
$r = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'X-3', 'lines' => [['acct' => '59901', 'amt' => '100']]]);
$ap3 = $r[1]['data']['nbr'] ?? '';
ok('cancel unpaid payable reverses it', $a->req('POST', "/api/payables/$ap3/cancel")[0] === 200 && one("SELECT ap_status FROM ap_mstr WHERE ap_ref='$ap3'") === 'C' && gl($ap3, '20101') == 0);

// voucher (VO): the vendor's payables gathered for payment
$open = $a->req('GET', '/api/ap-vouchers/open?vendor=V1')[1]['data'];
ok('open payables for a voucher', count($open) === 2, $open);
ok('queue: open payables wait for a voucher', !array_diff([$ap1, $ap2], acctQueue($a, '/api/ap-vouchers/waiting', 'ap_ref'))
    && !in_array($ap3, acctQueue($a, '/api/ap-vouchers/waiting', 'ap_ref'), true) && str_contains((string) $a->page('/ap-vouchers')[1], 'data-queue'));
$r = $a->req('POST', '/api/ap-vouchers', ['vendor' => 'V1', 'apv_date' => $today, 'refs' => [$ap1, $ap2]]);
$vo = $r[1]['data']['nbr'] ?? '';
ok('voucher: Dr AP 11770 / Cr vouchers payable 11770', $r[0] === 200 && gl($vo, '20101', 'VO') == 11770 && gl($vo, '20104', 'VO') == -11770, $r[1]);
ok('payables settled by the voucher', one("SELECT GROUP_CONCAT(ap_status) FROM ap_mstr WHERE ap_ref IN ('$ap1','$ap2')") === 'P,P');
ok('queue: payables on a voucher leave it; the voucher waits for payment', !array_intersect([$ap1, $ap2], acctQueue($a, '/api/ap-vouchers/waiting', 'ap_ref'))
    && in_array($vo, acctQueue($a, '/api/vouchers/waiting', 'apv_nbr'), true) && str_contains((string) $a->page('/vouchers')[1], 'data-queue'));
ok('a payable goes on one voucher only', $a->req('POST', '/api/ap-vouchers', ['vendor' => 'V1', 'apv_date' => $today, 'refs' => [$ap1]])[0] === 422);

// payment (PV) of the voucher with 3% withholding on the part before VAT
$open = $a->req('GET', '/api/vouchers/open?vendor=V1')[1]['data'];
ok('open voucher with pre-VAT ratio', count($open) === 1 && abs($open[0]['ratio'] - 11000 / 11770) < 0.0001, $open);
$r = $a->req('POST', '/api/vouchers', ['vendor' => 'V1', 'vo_date' => $today, 'vo_bank' => 'BANK1', 'lines' => [$vo => ['amt' => '20000']]]);
ok('payment over balance refused', $r[0] === 422 && isset($r[1]['fields']["lines.$vo.amt"]), $r[1]);
$r = $a->req('POST', '/api/vouchers', ['vendor' => 'V1', 'vo_date' => $today, 'vo_bank' => 'BANK1', 'vo_wht_code' => 'WHT3', 'wht_base' => '99999', 'lines' => [$vo => ['amt' => '11770']]]);
ok('withholding base over payment refused', $r[0] === 422 && isset($r[1]['fields']['wht_base']), $r[1]);
$r = $a->req('POST', '/api/vouchers', ['vendor' => 'V1', 'vo_date' => $today, 'vo_bank' => 'BANK1', 'vo_wht_code' => 'WHT3', 'vo_wht_form' => 'PND53', 'wht_base' => '11000',
    'lines' => [$vo => ['amt' => '11770']]]);
$pv = $r[1]['data']['nbr'] ?? '';
ok('payment: Dr vouchers payable 11770 / Cr cash 11440, Cr WHT 330', $r[0] === 200 && gl($pv, '20104', 'PV') == 11770 && gl($pv, '10101', 'PV') == -11440 && gl($pv, '21103', 'PV') == -330, $r[1]);
ok('voucher paid', one("SELECT apv_status FROM apv_mstr WHERE apv_nbr='$vo'") === 'P');
ok('queue: paid voucher leaves the payment queue', !in_array($vo, acctQueue($a, '/api/vouchers/waiting', 'apv_nbr'), true));
$c = db("SELECT * FROM wht_cert WHERE whtc_doc_ref='$pv'")[0] ?? [];
ok('50 ทวิ issued: base 11000, tax 330', (float) ($c['whtc_base_amt'] ?? 0) === 11000.0 && (float) $c['whtc_tax_amt'] === 330.0 && $c['whtc_form_type'] === 'PND53');
$p = $a->page('/wht/' . $c['whtc_nbr'] . '/print?html=1');
$flat = preg_replace('/\s+/', '', strip_tags((string) $p[1]));
ok('50 ทวิ print: the RD form in its two copies, payee tax id in the boxes, ภ.ง.ด.53 ticked, amount and rate on row 5', $p[0] === 200
    && substr_count($p[1], 'หนังสือรับรองการหักภาษี ณ ที่จ่าย</b>') === 2
    && str_contains($p[1], 'ฉบับที่ 1 (สำหรับผู้ถูกหักภาษี ณ ที่จ่าย ใช้แนบพร้อมกับแบบแสดงรายการภาษี)')
    && str_contains($p[1], 'ฉบับที่ 2 (สำหรับผู้ถูกหักภาษี ณ ที่จ่าย เก็บไว้เป็นหลักฐาน)')
    && str_contains($flat, '0105566001234') && str_contains($p[1], 'สามร้อยสามสิบบาทถ้วน')
    && str_contains($p[1], '<span class="cb">✓</span>(7) ภ.ง.ด.53') && str_contains($p[1], '<span class="cb"></span>(4) ภ.ง.ด.3')
    && (bool) preg_match('#<tr class="rate">\s*<td class="inc i1">- [^<]*อัตราร้อยละ 3</td>\s*<td class="c">\d{2}/\d{2}/25\d\d</td><td class="r">11,000.00</td><td class="r">330.00</td>#u', $p[1]));
ok('payment voucher print', ($p = $a->page("/vouchers/$pv/print"))[0] === 200);
ok('voucher print', ($p = $a->page("/ap-vouchers/$vo/print?html=1"))[0] === 200 && str_contains($p[1], 'ใบสำคัญจ่าย'));
// what was paid for: the goods of the receipt are printed under the payable, the voucher and (below) the payment
$rcPart = one("SELECT rcd_part FROM rcd_det WHERE rcd_nbr='$rc' LIMIT 1");
ok('payable and voucher prints list the goods received', str_contains((string) $a->page("/documents/payables/$ap1/print?html=1")[1], "– $rcPart")
    && substr_count($p[1], 'doc-sub') > 0 && str_contains($p[1], "– $rcPart"));
ok('payment print: the vouchers, their payables and goods, the vendor address', str_contains($pp = (string) $a->page("/vouchers/$pv/print?html=1")[1], "– $rcPart")
    && str_contains($pp, $ap1) && str_contains($pp, (string) one("SELECT vd_zip FROM vd_mstr WHERE vd_code='V1'")));
ok('payment show for the page', ($s = $a->req('GET', "/api/vouchers/$pv")[1]['data'])['head']['whtc_nbr'] === $c['whtc_nbr'] && count($s['lines']) === 1);
// a partial payment cancelled
$ap4 = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'X-4', 'lines' => [['acct' => '59901', 'amt' => '500']]])[1]['data']['nbr'];
$vo2 = $a->req('POST', '/api/ap-vouchers', ['vendor' => 'V1', 'apv_date' => $today, 'refs' => [$ap4]])[1]['data']['nbr'] ?? '';
$pv2 = $a->req('POST', '/api/vouchers', ['vendor' => 'V1', 'vo_date' => $today, 'vo_bank' => 'BANK1', 'vo_wht_code' => 'WHT1', 'wht_base' => '200',
    'doc_nbr' => 'PV-HAND-1', 'doc_type' => 'PV', 'lines' => [$vo2 => ['amt' => '200']]])[1]['data']['nbr'] ?? '';
ok('a payment number typed on the page is used (series PV)', $pv2 === 'PV-HAND-1' && str_contains((string) $a->page('/vouchers/new')[1], 'value="PV'), $pv2);
ok('a vendor with documents cannot be deleted', $a->req('DELETE', '/api/vendors/row?vd_code=V1')[0] === 409);
ok('partial payment keeps voucher open', one("SELECT CONCAT(apv_status, apv_paid_amt) FROM apv_mstr WHERE apv_nbr='$vo2'") === 'O200.00');
ok('paid voucher cannot be cancelled', $a->req('POST', "/api/ap-vouchers/$vo2/cancel")[0] === 409);
ok('cancel payment: voucher reopened, certificate void, posting reversed', $a->req('POST', "/api/vouchers/$pv2/cancel")[0] === 200
    && one("SELECT apv_paid_amt FROM apv_mstr WHERE apv_nbr='$vo2'") == 0 && one("SELECT whtc_status FROM wht_cert WHERE whtc_doc_ref='$pv2'") === 'C' && gl($pv2, '10101') == 0);
ok('cancel voucher: payable open again', $a->req('POST', "/api/ap-vouchers/$vo2/cancel")[0] === 200 && one("SELECT ap_status FROM ap_mstr WHERE ap_ref='$ap4'") === 'O');
// one payment, two rates (services 3% + transport 1%) on one 50 ทวิ
$ap5 = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'X-5', 'lines' => [['acct' => '59901', 'amt' => '1000']]])[1]['data']['nbr'];
$vo5 = $a->req('POST', '/api/ap-vouchers', ['vendor' => 'V1', 'apv_date' => $today, 'refs' => [$ap5]])[1]['data']['nbr'] ?? '';
$pay5 = fn (array $wht) => $a->req('POST', '/api/vouchers', ['vendor' => 'V1', 'vo_date' => $today, 'vo_bank' => 'BANK1', 'vo_wht_form' => 'PND53', 'wht' => $wht, 'lines' => [$vo5 => ['amt' => '1000']]]);
$r = $pay5([['code' => 'WHT3', 'base' => '600'], ['code' => 'WHT3', 'base' => '400']]);
ok('the same rate twice refused', $r[0] === 422 && isset($r[1]['fields']['wht.1.code']), $r[1]);
$r = $pay5([['code' => 'WHT3', 'base' => '600'], ['code' => 'WHT1', 'base' => '500']]);
ok('bases over the payment refused', $r[0] === 422 && isset($r[1]['fields']['wht.1.base']), $r[1]);
$r = $pay5([['code' => 'WHT3', 'base' => '600'], ['code' => 'WHT1', 'base' => '400'], ['code' => '', 'base' => '']]);
$pv5 = $r[1]['data']['nbr'] ?? '';
$c5 = db("SELECT * FROM wht_cert WHERE whtc_doc_ref='$pv5'")[0] ?? [];
ok('two rates: tax 18 + 4, cash 978, one certificate with two lines', $r[0] === 200 && gl($pv5, '21103', 'PV') == -22 && gl($pv5, '10101', 'PV') == -978
    && (float) ($c5['whtc_base_amt'] ?? 0) === 1000.0 && (float) $c5['whtc_tax_amt'] === 22.0
    && one("SELECT GROUP_CONCAT(CONCAT(whtd_code, ':', ROUND(whtd_tax_amt)) ORDER BY whtd_line) FROM whtd_det WHERE whtd_nbr = '{$c5['whtc_nbr']}'") === 'WHT3:18,WHT1:4', $r[1]);
ok('payment show lists both rates', count($a->req('GET', "/api/vouchers/$pv5")[1]['data']['head']['wht'] ?? []) === 2);
$p = (string) $a->page('/wht/' . ($c5['whtc_nbr'] ?? '') . '/print?html=1')[1];
ok('50 ทวิ shows each rate with its tax', str_contains($p, 'อัตราร้อยละ 3') && str_contains($p, 'อัตราร้อยละ 1') && str_contains($p, '18.00') && str_contains($p, '4.00') && str_contains($p, '22.00'));
ok('the certificate page goes to its print path (PDF button)', $a->req('GET', '/wht/' . ($c5['whtc_nbr'] ?? ''))[0] === 302);
// a payable still owed can be changed; each line says what the payment withholds
$r = $a->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'X-6', 'tax_code' => 'VAT7',
    'lines' => [['acct' => '59901', 'desc' => 'ค่าขนส่ง', 'amt' => '2000', 'wht' => 'WHT1']]]);
$ap6 = $r[1]['data']['nbr'] ?? '';
ok('payable line keeps its withholding code', $r[0] === 200 && one("SELECT apd_wht_code FROM apd_det WHERE apd_ref = '$ap6' AND apd_acct = '59901'") === 'WHT1', $r[1]);
ok('an owed payable on no voucher is editable', ($a->req('GET', "/api/payables/$ap6")[1]['data']['head']['_editable'] ?? null) === true);
$r = $a->req('PUT', "/api/payables/$ap6", ['ap_date' => $today, 'ap_inv_nbr' => 'X-6', 'tax_code' => 'VAT7', 'lines' => [
    ['acct' => '59901', 'desc' => 'ค่าขนส่ง', 'amt' => '2000', 'wht' => 'WHT1'], ['acct' => '59901', 'desc' => 'ค่าบริการติดตั้ง', 'amt' => '1000', 'wht' => 'WHT3'],
]]);
ok('edit adds a line: 3,000 + VAT = 3,210 owed, the ledger moved by the difference only', $r[0] === 200
    && (float) one("SELECT ap_amt FROM ap_mstr WHERE ap_ref = '$ap6'") === 3210.0 && gl($ap6, '20101', 'AP') == -3210 && gl($ap6, '59901', 'AP') == 3000
    && (int) one("SELECT COUNT(*) FROM gl_trhist WHERE gl_ref = '$ap6' AND gl_type = 'AP'") === 3 + 3, $r[1]);
$vo6 = $a->req('POST', '/api/ap-vouchers', ['vendor' => 'V1', 'apv_date' => $today, 'refs' => [$ap6]])[1]['data']['nbr'] ?? '';
ok('on a voucher the payable cannot be changed', $a->req('PUT', "/api/payables/$ap6", ['ap_date' => $today, 'ap_inv_nbr' => 'X-6', 'lines' => [['acct' => '59901', 'amt' => '1']]])[0] === 409);
$open = array_column($a->req('GET', '/api/vouchers/open?vendor=V1')[1]['data'] ?? [], null, 'doc');
ok('the payment knows what the lines withhold: 2,000 at 1% and 1,000 at 3%', (array) ($open[$vo6]['wht'] ?? []) == ['WHT1' => 2000, 'WHT3' => 1000], $open[$vo6] ?? $open);

// sales: SO 10 x 100 + VAT, ship & invoice -> AR posting and COGS
$so = $a->req('POST', '/api/sales-orders', ['so_cm_code' => 'C1', 'so_date' => $today, 'so_site' => 'MAIN', 'so_cr_terms' => '30DAYS', 'so_curr' => 'THB', 'so_ex_rate' => '1', 'so_tax_code' => 'VAT7',
    'lines' => [['sod_part' => 'P1', 'sod_qty_ord' => '20', 'sod_price' => '100']]])[1]['data']['nbr'];
$a->req('POST', '/api/shipments', ['so' => $so, 'sh_date' => $today, 'invoice' => true, 'lines' => ['1' => ['qty' => '10', 'loc' => 'STOCK']]]);
$inv = one("SELECT ih_inv_nbr FROM ih_mstr ORDER BY ih_inv_nbr DESC LIMIT 1");
ok('a customer with documents cannot be deleted', $a->req('DELETE', '/api/customers/row?cm_code=C1')[0] === 409);
$sh = one("SELECT ih_sh_nbr FROM ih_mstr WHERE ih_inv_nbr='$inv'");
$cost = (float) one("SELECT tr_price FROM tr_hist WHERE tr_type='ISS-SO' LIMIT 1");
ok('invoice posts Dr AR 1070 / Cr VAT 70, Cr sales 1000', gl($inv, '11101', 'AR') == 1070 && gl($inv, '21101', 'AR') == -70 && gl($inv, '40101', 'AR') == -1000);
ok('shipment posts COGS at cost', gl($sh, '50101', 'IC') > 0 && gl($sh, '50101', 'IC') == -gl($sh, '12101', 'IC'));
$a->req('POST', '/api/shipments', ['so' => $so, 'sh_date' => $today, 'invoice' => true, 'lines' => ['1' => ['qty' => '10', 'loc' => 'STOCK']]]);
$inv2 = one("SELECT ih_inv_nbr FROM ih_mstr ORDER BY ih_inv_nbr DESC LIMIT 1");
$bl = $a->req('POST', '/api/billings', ['customer' => 'C1', 'bl_date' => $today, 'invoices' => [$inv2]])[1]['data']['nbr'] ?? '';

$aging = $a->req('GET', '/api/receivables')[1]['data'];
ok('receivables aging: C1 2140 not due', count($aging) === 1 && (float) $aging[0]['b0'] === 2140.0 && (float) $aging[0]['total'] === 2140.0, $aging);
ok('receivable items', count($a->req('GET', '/api/receivables/C1')[1]['data']) === 2);

// cash receipts
ok('queue: unpaid invoices wait for a receipt', !array_diff([$inv, $inv2], acctQueue($a, '/api/cash-receipts/waiting', 'ar_nbr'))
    && str_contains((string) $a->page('/cash-receipts')[1], 'data-queue'));
$r = $a->req('POST', '/api/cash-receipts', ['customer' => 'C1', 'cr_date' => $today, 'cr_bank' => 'BANK1', 'lines' => [$inv => ['amt' => '1000', 'wht' => '100']]]);
ok('receipt over balance refused', $r[0] === 422 && isset($r[1]['fields']["lines.$inv.amt"]), $r[1]);
$r = $a->req('POST', '/api/cash-receipts', ['customer' => 'C1', 'cr_date' => '2000-01-01', 'cr_bank' => 'BANK1', 'lines' => [$inv => ['amt' => '10']]]);
ok('receipt in a closed period refused', $r[0] === 422 && isset($r[1]['fields']['cr_date']), $r[1]);
$r = $a->req('POST', '/api/cash-receipts', ['customer' => 'C1', 'cr_date' => $today, 'cr_bank' => 'BANK1', 'lines' => [$inv => ['amt' => '1000', 'wht' => '10']]]);
$cr1 = $r[1]['data']['nbr'] ?? '';
ok('receipt: Dr cash 1000, Dr WHT 10 / Cr AR 1010', $r[0] === 200 && gl($cr1, '10101', 'CR') == 1000 && gl($cr1, '13103', 'CR') == 10 && gl($cr1, '11101', 'CR') == -1010, $r[1]);
ok('partly paid: AR open, invoice paid amount follows', one("SELECT ar_status FROM ar_mstr WHERE ar_nbr='$inv'") === 'O' && (float) one("SELECT ih_paid_amt FROM ih_mstr WHERE ih_inv_nbr='$inv'") === 1010.0
    && one("SELECT ih_status FROM ih_mstr WHERE ih_inv_nbr='$inv'") === 'U');
$cr2 = $a->req('POST', '/api/cash-receipts', ['customer' => 'C1', 'cr_date' => $today, 'cr_bank' => 'BANK1', 'lines' => [$inv => ['amt' => '60'], $inv2 => ['amt' => '1070']]])[1]['data']['nbr'] ?? '';
ok('fully paid: AR and invoices P, billing note P', one("SELECT GROUP_CONCAT(ar_status ORDER BY ar_nbr) FROM ar_mstr") === 'P,P'
    && one("SELECT GROUP_CONCAT(ih_status) FROM ih_mstr") === 'P,P' && one("SELECT CONCAT(bl_status, bl_paid_amt) FROM bl_mstr WHERE bl_nbr='$bl'") === 'P1070.00000');
ok('queue: paid invoices leave the receipt queue', !array_intersect([$inv, $inv2], acctQueue($a, '/api/cash-receipts/waiting', 'ar_nbr')));
ok('receipt print', ($p = $a->page("/cash-receipts/$cr2/print?html=1"))[0] === 200 && str_contains($p[1], 'ใบเสร็จรับเงิน') && str_contains($p[1], 'หนึ่งพันหนึ่งร้อยสามสิบบาทถ้วน')
    && str_contains($p[1], '– ' . one("SELECT ihd_part FROM ihd_det WHERE ihd_inv_nbr='$inv2' LIMIT 1")));
ok('cancel receipt: invoices open again, billing note open', $a->req('POST', "/api/cash-receipts/$cr2/cancel")[0] === 200
    && one("SELECT GROUP_CONCAT(ar_status) FROM ar_mstr") === 'O,O' && one("SELECT ih_status FROM ih_mstr WHERE ih_inv_nbr='$inv2'") === 'U'
    && one("SELECT bl_status FROM bl_mstr WHERE bl_nbr='$bl'") === 'O' && gl($cr2, '11101') == 0);
ok('cancelled receipt cannot be cancelled again', $a->req('POST', "/api/cash-receipts/$cr2/cancel")[0] === 409);
ok('paid invoice cannot be cancelled', $a->req('POST', "/api/invoices/$inv/cancel")[0] === 409);

// manual journal
$r = $a->req('POST', '/api/journals', ['date' => $today, 'desc' => 'x', 'lines' => [['acct' => '11101', 'dr' => '5'], ['acct' => '10101', 'cr' => '5']]]);
ok('journal refuses control account', $r[0] === 422 && isset($r[1]['fields']['lines.0.acct']), $r[1]);
$r = $a->req('POST', '/api/journals', ['date' => $today, 'desc' => 'x', 'lines' => [['acct' => '50401', 'dr' => '5'], ['acct' => '10101', 'cr' => '4']]]);
ok('journal must balance', $r[0] === 422 && isset($r[1]['fields']['lines']), $r[1]);
$r = $a->req('POST', '/api/journals', ['date' => $today, 'desc' => 'x', 'lines' => [['acct' => '50401', 'dr' => '5', 'cr' => '5'], ['acct' => '10101', 'cr' => '5']]]);
ok('journal line is debit or credit', $r[0] === 422 && isset($r[1]['fields']['lines.0.dr']), $r[1]);
$r = $a->req('POST', '/api/journals', ['date' => $today, 'desc' => 'ซื้อวัสดุสำนักงาน', 'lines' => [['acct' => '50401', 'cc' => '', 'dr' => '500'], ['acct' => '10101', 'cr' => '500'], ['acct' => '', 'dr' => '']]]);
$je = $r[1]['data']['nbr'] ?? '';
ok('journal posted as JE000001', $r[0] === 200 && $je === "JE~JE000001~$today" && gl('JE000001', '50401', 'JE') == 500, $r[1]);
ok('journal show', ($s = $a->req('GET', '/api/journals/' . rawurlencode($je)))[0] === 200 && count($s[1]['data']['lines']) === 2 && $s[1]['data']['head']['reversible'] === true);
$r = $a->req('POST', '/api/journals/' . rawurlencode($je) . '/reverse');
ok('journal reversed by JE000002', $r[0] === 200 && gl('JE000002', '50401', 'JE') == -500 && gl('JE000002', '10101', 'JE') == 500, $r[1]);
ok('only manual journals can be reversed', $a->req('POST', '/api/journals/' . rawurlencode("AR~$inv~$today") . '/reverse')[0] === 409);
$l = $a->req('GET', '/api/journals?status=AR')[1]['data'];
ok('journal list by type', $l['total'] === 2 && $l['rows'][0]['gl_type'] === 'AR', $l);
ok('journal pages', $a->page('/journals/' . rawurlencode("AP~$ap1~$today"))[0] === 200 && $a->page('/journals/nope')[0] === 404);

// every entry balances
$bad = db("SELECT gl_type, gl_ref, gl_date, SUM(gl_amt) s FROM gl_trhist GROUP BY gl_type, gl_ref, gl_date HAVING ABS(SUM(gl_amt)) > 0.001");
ok('every posting balances', !$bad, $bad);

// trial balance
$tb = $a->req('GET', "/api/trial-balance?from=$today&to=$today")[1]['data'];
ok('trial balance: debit = credit, closing nets to zero', $tb['total']['debit'] == $tb['total']['credit'] && abs($tb['total']['closing']) < 0.001 && count($tb['rows']) > 5, $tb['total']);
$row = array_values(array_filter($tb['rows'], fn ($x) => $x['acct'] === '11101'))[0] ?? [];
ok('trial balance: AR closing = open invoices 1130', (float) ($row['closing'] ?? 0) === 1130.0, $row);
$ld = $a->req('GET', "/api/trial-balance/ledger?acct=10101&from=$today&to=$today")[1]['data'];
ok('ledger running balance = trial balance closing', end($ld['lines'])['balance'] == array_values(array_filter($tb['rows'], fn ($x) => $x['acct'] === '10101'))[0]['closing']);
ok('bad range refused', $a->req('GET', "/api/trial-balance?from=$today&to=2000-01-01")[0] === 422);

// prior-year profit shows in retained earnings
$py = (date('Y') - 1) . '-06-30';
db("UPDATE gl_ctrl SET gl_curr_year = YEAR(CURDATE()) - 1, gl_curr_per = 6");
$a->req('POST', '/api/journals', ['date' => $py, 'desc' => 'ปีก่อน', 'lines' => [['acct' => '10101', 'dr' => '300'], ['acct' => '40901', 'cr' => '300']]]);
db("UPDATE gl_ctrl SET gl_curr_year = YEAR(CURDATE()), gl_curr_per = MONTH(CURDATE())");
$tb = $a->req('GET', "/api/trial-balance?from=$today&to=$today")[1]['data'];
$re = array_values(array_filter($tb['rows'], fn ($x) => $x['acct'] === '30301'))[0] ?? [];
$oi = array_values(array_filter($tb['rows'], fn ($x) => $x['acct'] === '40901'));
ok('last year income moved to retained earnings opening', (float) ($re['opening'] ?? 0) === -300.0 && !$oi && abs($tb['total']['closing']) < 0.001, [$re, $oi]);

// closing the period
ok('current period cannot be closed before it ends', $a->req('POST', '/api/trial-balance/close')[0] === 409);
$prev = date('Y-m-01', strtotime('first day of last month'));
ok('queue: no period to close before the month ends', acctQueue($a, '/api/trial-balance/waiting', 'period') === []);
db("UPDATE gl_ctrl SET gl_curr_year = YEAR('$prev'), gl_curr_per = MONTH('$prev')");
ok('queue: an ended period waits to be closed', acctQueue($a, '/api/trial-balance/waiting', 'period') === [date('Y/m', strtotime($prev))]
    && str_contains((string) $a->page('/trial-balance')[1], 'data-queue'));
$r = $a->req('POST', '/api/trial-balance/close');
ok('queue: closed period leaves the queue', acctQueue($a, '/api/trial-balance/waiting', 'period') === []);
ok('close last month -> this month open', $r[0] === 200 && $r[1]['data']['period'] === date('Y/m'), $r[1]);
$r = $a->req('POST', '/api/journals', ['date' => $prev, 'desc' => 'x', 'lines' => [['acct' => '50401', 'dr' => '5'], ['acct' => '10101', 'cr' => '5']]]);
ok('journal in closed period refused', $r[0] === 422 && isset($r[1]['fields']['date']), $r[1]);

// fixed assets: a draft waits to be activated, then last month's depreciation waits to be posted
db("UPDATE gl_ctrl SET gl_curr_year = YEAR('$prev'), gl_curr_per = MONTH('$prev')");
$r = $a->req('POST', '/api/fixed-assets', ['fa_code' => 'FA-Q1', 'fa_desc' => 'คอมพิวเตอร์', 'fa_category' => 'OF', 'fa_site' => 'MAIN',
    'fa_acquired' => $prev, 'fa_start' => $prev, 'fa_cost' => '6000']);
ok('queue: draft asset waits to be activated', $r[0] === 200 && in_array('FA-Q1', acctQueue($a, '/api/fixed-assets/waiting', 'fa_code'), true)
    && str_contains((string) $a->page('/fixed-assets')[1], 'data-queue'), $r[1]);
$r = $a->req('POST', '/api/fixed-assets/FA-Q1/activate', ['mode' => 'BOOKED']);
$pm = substr($prev, 0, 7);
ok('queue: active asset leaves the drafts, its month waits for depreciation', $r[0] === 200 && !in_array('FA-Q1', acctQueue($a, '/api/fixed-assets/waiting', 'fa_code'), true)
    && in_array($pm, acctQueue($a, '/api/fixed-assets/depreciation-waiting', 'month'), true), $r[1]);
$r = $a->req('POST', '/api/fixed-assets/FA-Q1/depreciate', ['month' => $pm]);
ok('queue: posted month leaves the depreciation queue', $r[0] === 200 && !in_array($pm, acctQueue($a, '/api/fixed-assets/depreciation-waiting', 'month'), true), $r[1]);
// files attached to documents (a 1x1 png): one set of routes for every document (System\AttachmentController), the document's own rights
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
$file = $a->req('POST', '/api/files/FA/FA-Q1', ['name' => 'warranty.png', 'data' => $png]);
$fid = $file[1]['data']['att_id'] ?? 0;
ok('asset: a file attached, listed, counted for the list, opened, removed', $file[0] === 200
    && str_contains((string) $a->page('/fixed-assets/FA-Q1')[1], 'data-files="FA" data-nbr="FA-Q1"')
    && ($a->req('GET', '/api/files/FA/FA-Q1')[1]['data']['files'][0]['att_name'] ?? '') === 'warranty.png'
    && ($a->req('GET', '/api/files/FA?nbrs=FA-Q1,NOPE')[1]['data'] ?? []) === ['FA-Q1' => 1]
    && $a->page("/files/FA/FA-Q1/$fid")[0] === 200
    && $a->req('DELETE', "/api/files/FA/FA-Q1/$fid")[0] === 200
    && (int) one("SELECT COUNT(*) FROM att_det WHERE att_doc = 'FA' AND att_nbr = 'FA-Q1'") === 0, $file[1]);
$entry = one("SELECT CONCAT(gl_type, '~', gl_ref, '~', gl_date) FROM gl_trhist ORDER BY gl_trnbr LIMIT 1");
ok('files: a ledger entry by type~ref~date, the PND report by form~month; not a document that is not there, not a text file',
    $a->req('POST', '/api/files/JE/' . rawurlencode($entry), ['name' => 'contract.png', 'data' => $png])[0] === 200
    && $a->req('POST', '/api/files/WR/PND53~' . date('Y-m'), ['name' => 'pnd53.png', 'data' => $png])[0] === 200
    && $a->req('POST', '/api/files/WR/53~' . date('Y-m'), ['name' => 'x.png', 'data' => $png])[0] === 404
    && $a->req('POST', '/api/files/PO/NOPE', ['name' => 'x.png', 'data' => $png])[0] === 404
    && $a->req('POST', '/api/files/XX/1', ['name' => 'x.png', 'data' => $png])[0] === 403
    && $a->req('POST', '/api/files/JE/' . rawurlencode($entry), ['name' => 'x.txt', 'data' => 'data:text/plain;base64,' . base64_encode('hello')])[0] === 422);
ok('asset: receipts of fixed-asset items to pick as the purchase reference', $a->req('GET', '/api/fixed-assets/receipts')[0] === 200);
db("UPDATE gl_ctrl SET gl_curr_year = YEAR(CURDATE()), gl_curr_per = MONTH(CURDATE())");

// settings and masters
$r = $a->req('PUT', '/api/settings/gl/row', ['gl_ar_acct' => 'NOPE']);
ok('GL settings validate accounts', $r[0] === 422 && isset($r[1]['fields']['gl_ar_acct']), $r[1]);
$r = $a->req('POST', '/api/accounts', ['ac_acct' => '59990', 'ac_desc' => 'Test', 'ac_desc_th' => 'ทดสอบ', 'ac_type' => 'X', 'ac_active' => '1']);
ok('add account', $r[0] === 200, $r[1]);
ok('used account cannot be deleted', $a->req('DELETE', '/api/accounts/row?ac_acct=10101')[0] === 409);
ok('new account deletable', $a->req('DELETE', '/api/accounts/row?ac_acct=59990')[0] === 200);

// ภ.ง.ด.3 / 53 attachment: certificates of the month by form, cancelled ones left out
$month = date('Y-m');
$r = $a->req('GET', "/api/wht-report?form=PND53&month=$month");
$want = (float) one("SELECT COALESCE(SUM(whtc_tax_amt), 0) FROM wht_cert WHERE whtc_status <> 'C' AND whtc_form_type = 'PND53' AND DATE_FORMAT(whtc_date, '%Y-%m') = '$month'");
$nbrs = array_column($r[1]['data']['rows'] ?? [], 'nbr');
ok('PND53 lists the month certificates, not the cancelled one', $r[0] === 200 && in_array($c['whtc_nbr'], $nbrs, true)
    && !in_array(one("SELECT whtc_nbr FROM wht_cert WHERE whtc_doc_ref='$pv2'"), $nbrs, true) && $r[1]['data']['tax'] == $want, $r[1]);
ok('PND3 is separate', ($a->req('GET', "/api/wht-report?form=PND3&month=$month")[1]['data']['rows'] ?? null) !== null
    && !in_array($c['whtc_nbr'], array_column($a->req('GET', "/api/wht-report?form=PND3&month=$month")[1]['data']['rows'], 'nbr'), true));
ok('PND form checked', $a->req('GET', "/api/wht-report?form=X&month=$month")[0] === 422);
[$st, $html] = $a->page("/wht-report/PND53/$month/print?html=1");
$pnd = $a->req('GET', "/api/wht-report?form=PND53&month=$month")[1]['data'] ?? [];
ok('PND53 prints as the RD return and attachment: month ticked, payees counted, per-payee sequence, rate and condition', $st === 200
    && str_contains($html, 'ภ.ง.ด.53') && str_contains($html, 'ใบแนบ ภ.ง.ด.53') && str_contains($html, '330.00')
    && str_contains($html, '<span class="cb">✓</span>(' . (int) date('n') . ') ')
    && (bool) preg_match('#ราย#u', $html) && $pnd['payees'] === count(array_unique(array_column($pnd['rows'], 'code')))
    && $pnd['rows'][0]['seq'] === 1 && (bool) preg_match('#<td class="c ln3">3</td>\s*<td class="r ln3">11,000.00</td>\s*<td class="r ln3">330.00</td>\s*<td class="c ln3">1</td>#', $html));
ok('PND report page', $a->page('/wht-report')[0] === 200);

// bank reconciliation: tick the lines on the statement; its balance is the last one + those lines
$acct = one("SELECT bk_acct FROM bk_mstr WHERE bk_bank = 'BANK1'");
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$o = $a->req('GET', "/api/bank-rec/open?bank=BANK1&date=$today")[1]['data'] ?? [];
ok('queue: bank with uncleared lines waits for reconciliation', in_array('BANK1', acctQueue($a, '/api/bank-rec/waiting', 'bk_bank'), true)
    && str_contains((string) $a->page('/bank-rec')[1], 'data-queue'));
$ids = array_column($o['lines'] ?? [], 'gl_trnbr');
$sum = round(array_sum(array_column($o['lines'] ?? [], 'gl_amt')), 2);
ok('bank rec: open lines are the account lines, book balance from GL', $a->page('/bank-rec')[0] === 200 && count($ids) > 0
    && count($ids) == one("SELECT COUNT(*) FROM gl_trhist WHERE gl_acct = '$acct' AND gl_date <= '$today'")
    && $o['book'] == round((float) one("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_acct = '$acct' AND gl_date <= '$today'"), 2), $o);
$r = $a->req('POST', '/api/bank-rec', ['bank' => 'BANK1', 'date' => $today, 'lines' => []]);
ok('bank rec: nothing ticked is refused', $r[0] === 422 && isset($r[1]['fields']['lines']), $r[1]);
$first = $ids[0];
$part = round($sum - (float) ($o['lines'][0]['gl_amt']), 2);
$r = $a->req('POST', '/api/bank-rec', ['bank' => 'BANK1', 'date' => $today, 'lines' => array_slice($ids, 1)]);
ok('bank rec: saved, statement balance = opening + ticked', $r[0] === 200 && one("SELECT COUNT(*) FROM bkrd_det WHERE bkrd_bank='BANK1'") == count($ids) - 1
    && one("SELECT created_by FROM bkr_mstr WHERE bkr_bank='BANK1' AND bkr_date='$today'") === 'admin'
    && (float) one("SELECT bkr_balance FROM bkr_mstr WHERE bkr_bank='BANK1' AND bkr_date='$today'") == $part, $r[1]);
$o2 = $a->req('GET', "/api/bank-rec/open?bank=BANK1&date=$tomorrow")[1]['data'] ?? [];
ok('bank rec: next time opens from the statement balance with the unticked line left', $o2['opening'] == $part
    && array_column($o2['lines'], 'gl_trnbr') === [$first], $o2);
ok('bank rec: same date again refused', $a->req('POST', '/api/bank-rec', ['bank' => 'BANK1', 'date' => $today, 'lines' => [$first]])[0] === 422);
ok('bank rec: a cleared line cannot be ticked again', $a->req('POST', '/api/bank-rec', ['bank' => 'BANK1', 'date' => $tomorrow, 'lines' => [$ids[1]]])[0] === 409);
[$st, $html] = $a->page("/bank-rec/BANK1/$today/print?html=1");
ok('bank rec: statement prints the outstanding line', $st === 200 && str_contains($html, number_format(abs((float) $o['lines'][0]['gl_amt']), 2)));
$r = $a->req('POST', '/api/bank-rec', ['bank' => 'BANK1', 'date' => $tomorrow, 'lines' => [$first]]);
ok('bank rec: second statement clears the rest', $r[0] === 200 && count($a->req('GET', '/api/bank-rec?bank=BANK1')[1]['data']) === 2, $r[1]);
$recCr = one("SELECT g.gl_ref FROM gl_trhist g JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr JOIN cr_mstr c ON c.cr_nbr = g.gl_ref WHERE g.gl_type = 'CR' AND c.cr_status = 'P' LIMIT 1");
$recPv = one("SELECT g.gl_ref FROM gl_trhist g JOIN bkrd_det d ON d.bkrd_trnbr = g.gl_trnbr JOIN vo_mstr v ON v.vo_nbr = g.gl_ref WHERE g.gl_type = 'PV' AND v.vo_status = 'P' LIMIT 1");
ok('reconciled receipt / payment cannot be cancelled (next step done)', ($recCr || $recPv)
    && (!$recCr || ($a->req('POST', "/api/cash-receipts/$recCr/cancel")[0] === 409 && $a->req('GET', "/api/cash-receipts/$recCr")[1]['data']['head']['reconciled'] === true))
    && (!$recPv || $a->req('POST', "/api/vouchers/$recPv/cancel")[0] === 409), [$recCr, $recPv]);
ok('queue: reconciled bank leaves the queue', !in_array('BANK1', acctQueue($a, '/api/bank-rec/waiting', 'bk_bank'), true));
ok('bank rec: only the latest can be undone', $a->req('DELETE', "/api/bank-rec/BANK1/$today")[0] === 409
    && $a->req('DELETE', "/api/bank-rec/BANK1/$tomorrow")[0] === 200 && one("SELECT COUNT(*) FROM bkrd_det WHERE bkrd_trnbr = $first") == 0);

// bank accounts: the list shows the balance, last reconciliation and waiting lines; a row opens its bank book
$banks = array_column($a->req('GET', '/api/banks')[1]['data']['rows'] ?? [], null, 'bk_bank');
$book = round((float) one("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_acct = '$acct' AND gl_date <= CURDATE()"), 2);
ok('banks: list shows balance, last reconciliation, waiting lines and the bank book link', ($banks['BANK1']['bk_balance'] ?? null) == $book
    && $banks['BANK1']['bk_rec_date'] === $today && $banks['BANK1']['bk_waiting'] === 1 && $banks['BANK1']['_link'] === '/banks/BANK1'
    && ($banks['CASH']['_link'] ?? '') === '/petty-cash' && $banks['CASH']['bk_waiting'] === null, $banks);
$bb = $a->req('GET', '/api/banks/BANK1/book?month=' . date('Y-m'))[1]['data'] ?? [];
$last = end($bb['lines']);
ok('bank book: brought forward + lines = month-end balance, running balance and reconciliation date per line', $bb['closing'] == $book
    && round($bb['opening'] + $bb['in'] - $bb['out'], 2) == $bb['closing'] && $last['balance'] == $bb['closing']
    && count(array_filter($bb['lines'], fn ($l) => $l['rec_date'] === $today)) > 0, $bb);
ok('bank book: page and print', $a->page('/banks/BANK1')[0] === 200 && str_contains((string) $a->page('/banks/BANK1/book/' . date('Y-m') . '/print?html=1')[1], 'BANK1'));

// e-Tax Invoice by Email: subject in the ETDA form, one PDF, To the buyer, Cc the ETDA system; mail.host 'file' writes .eml
ok('e-Tax pages', $a->page('/etax')[0] === 200 && $a->page('/settings/etax')[0] === 200);
ok('e-Tax off: nothing sent', $a->req('POST', "/api/etax/$inv/send", ['to' => 'buyer@example.test'])[0] === 409);
ok('queue: no e-Tax queue while e-Tax is off', !str_contains((string) $a->page('/etax')[1], 'data-queue'));
$etaxMenu = fn () => (bool) preg_match('#href="[^"]*(?<!/settings)/etax"#', (string) $a->page('/settings/etax')[1]);
ok('e-Tax off and nothing sent: its menu is hidden', !$etaxMenu());
$r = $a->req('PUT', '/api/settings/etax', ['enabled' => true, 'host' => 'file', 'port' => '25', 'secure' => 'none', 'from' => 'nope', 'cc' => '']);
ok('e-Tax settings need a real sender', $r[0] === 422 && isset($r[1]['fields']['from']), $r[1]);
$r = $a->req('PUT', '/api/settings/etax', ['enabled' => true, 'host' => 'file', 'port' => '25', 'secure' => 'none', 'from' => 'billing@example.test',
    'user' => 'u', 'pass' => 'secret-x', 'cc' => 'csemail@etax.teda.th', 'year' => 'BE']);
ok('e-Tax settings saved, password not sent back', $r[0] === 200 && $r[1]['data']['enabled'] === true && $r[1]['data']['pass_set'] === true
    && !str_contains(json_encode($r[1]), 'secret-x'), $r[1]);
ok('e-Tax on: its menu shows', $etaxMenu());
ok('queue: unsent invoice waits for e-Tax', in_array($inv, acctQueue($a, '/api/etax/waiting', 'ih_inv_nbr'), true)
    && str_contains((string) $a->page('/etax')[1], 'data-queue'));
$d = one("SELECT ih_inv_date FROM ih_mstr WHERE ih_inv_nbr = '$inv'");
[$y, $m, $dd] = explode('-', $d);
$p = $a->req('GET', "/api/etax/$inv")[1]['data'] ?? [];
ok('e-Tax subject [dd/mm/yyyy BE][INV][number]', ($p['subject'] ?? '') === '[' . "$dd/$m/" . ((int) $y + 543) . "][INV][$inv]" && $p['cc'] === ['csemail@etax.teda.th'], $p);
ok('e-Tax needs a valid buyer email', ($r = $a->req('POST', "/api/etax/$inv/send", ['to' => 'x']))[0] === 422 && isset($r[1]['fields']['to']));
$before = glob(PHAI_ROOT . '/storage/mail/*.eml') ?: [];
$r = $a->req('POST', "/api/etax/$inv/send", ['to' => 'buyer@example.test']);
$new = array_values(array_diff(glob(PHAI_ROOT . '/storage/mail/*.eml') ?: [], $before));
// a server without Edge/Chrome cannot make the PDF: then the attempt must be refused and logged
if (!str_contains((string) ($r[1]['error'] ?? ''), 'Edge/Chrome')) {
    $eml = $new ? file_get_contents($new[0]) : '';
    ok('e-Tax sent: one email To the buyer, Cc ETDA, a PDF/A-3 attached, logged', $r[0] === 200 && count($new) === 1
        && str_contains($eml, "To: buyer@example.test\r\n") && str_contains($eml, "Cc: csemail@etax.teda.th\r\n")
        && str_contains($eml, 'Subject: ' . $p['subject']) && substr_count($eml, 'Content-Type: application/pdf') === 1
        && str_contains($pdf = base64_decode(preg_replace('/^.*Content-Disposition: attachment[^\r]*\r\n\r\n([A-Za-z0-9+\/=\r\n]+).*$/s', '$1', $eml)), '%PDF')
        && str_contains($pdf, '<pdfaid:part>3</pdfaid:part>') && str_contains($pdf, '/OutputIntents') && preg_match('#/ID \[<[0-9a-f]{32}>#', $pdf)
        && one("SELECT etax_status FROM etax_log WHERE etax_doc = '$inv' ORDER BY etax_id DESC LIMIT 1") === 'S', $r[1]);
} else {
    ok('e-Tax without a PDF browser: refused and logged', $r[0] === 422 && one("SELECT etax_status FROM etax_log WHERE etax_doc = '$inv'") === 'F', $r[1]);
}
ok('queue: e-Tax queue follows the send result', in_array($inv, acctQueue($a, '/api/etax/waiting', 'ih_inv_nbr'), true)
    === (one("SELECT etax_status FROM etax_log WHERE etax_doc = '$inv' ORDER BY etax_id DESC LIMIT 1") !== 'S'));
foreach ($new as $f) {
    @unlink($f);
}
ok('e-Tax log lists it (as the page asks: empty sort)', in_array($inv, array_column($a->req('GET', '/api/etax?sort=&dir=asc&q=' . urlencode($inv))[1]['data']['rows'] ?? [], 'etax_doc'), true));
db("INSERT INTO ih_mstr (ih_inv_nbr, ih_cm_code, ih_inv_date, ih_type, ih_ref_inv, ih_status, ih_curr, ih_ex_rate, ih_net_amt, ih_tax_amt, ih_tot_amt, created_by)
    VALUES ('CN-ET1', 'C1', '2026-03-05', 'CM', '$inv', 'U', 'THB', 1, -100, -7, -107, 'admin')");
ok('e-Tax credit note: [date][CDN][number][invoice]', ($a->req('GET', '/api/etax/CN-ET1')[1]['data']['subject'] ?? '') === "[05/03/2569][CDN][CN-ET1][$inv]");
db("UPDATE ih_mstr SET ih_status = 'C' WHERE ih_inv_nbr = 'CN-ET1'");
ok('e-Tax: a cancelled credit note is not sent', $a->req('GET', '/api/etax/CN-ET1')[0] === 409);
db("UPDATE ih_mstr SET ih_type = 'IN', ih_ref_inv = NULL WHERE ih_inv_nbr = 'CN-ET1'");
ok('e-Tax cancelled invoice: [date][CIV][number]', ($a->req('GET', '/api/etax/CN-ET1')[1]['data']['subject'] ?? '') === '[05/03/2569][CIV][CN-ET1]');
$a->req('PUT', '/api/settings/etax', ['enabled' => true, 'host' => 'file', 'from' => 'billing@example.test', 'cc' => 'csemail@etax.teda.th', 'year' => 'CE']);
ok('e-Tax year can be Gregorian', ($a->req('GET', '/api/etax/CN-ET1')[1]['data']['subject'] ?? '') === '[05/03/2026][CIV][CN-ET1]');

// e-Tax XML (ETDA ขมธอ.3-2560) signed with XAdES-BES; files live in storage/etax/<this database>, removed at the end
require_once PHAI_ROOT . '/src/autoload.php';
$etaxDir = PHAI_ROOT . '/storage/etax/' . E2E_DB;
$cnf = sys_get_temp_dir() . '/phai-e2e-openssl.cnf';
file_put_contents($cnf, "[req]\ndistinguished_name = dn\n[dn]\n");
$opt = ['config' => $cnf, 'digest_alg' => 'sha256', 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
$key = openssl_pkey_new($opt);
$cert = openssl_csr_sign(openssl_csr_new(['countryName' => 'TH', 'organizationName' => 'E2E Test Co', 'commonName' => 'E2E Test Co'], $key, $opt), null, $key, 30, $opt, 777);
openssl_pkcs12_export($cert, $p12, $key, 'p12-pass');
ok('e-Tax XML needs a certificate', $a->req('GET', "/api/etax/$inv/xml")[0] === 409);
$r = $a->req('PUT', '/api/settings/etax/cert', ['p12' => base64_encode($p12), 'password' => 'wrong']);
ok('certificate with a wrong password refused', $r[0] === 422 && isset($r[1]['fields']['cert_password']), $r[1]);
$r = $a->req('PUT', '/api/settings/etax/cert', ['p12' => 'data:application/x-pkcs12;base64,' . base64_encode($p12), 'password' => 'p12-pass']);
ok('certificate saved, shown, password not sent back', $r[0] === 200 && ($r[1]['data']['cert']['subject'] ?? '') === 'E2E Test Co'
    && is_file("$etaxDir/cert.p12") && !str_contains(json_encode($r[1]), 'p12-pass'), $r[1]);
ok('e-Tax XML refuses an incomplete company', ($r = $a->req('GET', "/api/etax/$inv/xml"))[0] === 409, [$r[0], is_array($r[1]) ? $r[1] : substr((string) $r[1], 0, 200)]);
db("UPDATE co_mstr SET co_tax_id = '0105556123456', co_branch = '00000' WHERE co_id = 1");
db("DELETE FROM ad_mstr WHERE ad_addr = 'COMPANY'");
db("INSERT INTO ad_mstr (ad_addr, ad_name, ad_line1, ad_sub_district, ad_city, ad_state, ad_zip, created_by)
    VALUES ('COMPANY', 'E2E Test Co', '99/1 ถนนพระราม 1', 'รองเมือง', 'ปทุมวัน', 'กรุงเทพมหานคร', '10330', 'admin')");
db("UPDATE cm_mstr SET cm_line1 = '12/3 ถนนพหลโยธิน', cm_province = 'กรุงเทพมหานคร', cm_zip = '10900', cm_country = 'Thailand', cm_tax_id_num = '0105556987654', cm_tax_branch = '1'");
[$st, $xml] = $a->page("/api/etax/$inv/xml");
$xml = (string) $xml;
ok('e-Tax XML: signed tax invoice 388, seller 18-digit TXID, TISI address codes, VAT rate from the line', $st === 200
    && Phai\Core\Xades::verify($xml) && str_contains($xml, '<rsm:TaxInvoice_CrossIndustryInvoice') && str_contains($xml, '<ram:TypeCode>388</ram:TypeCode>')
    && str_contains($xml, '<ram:ID schemeID="TXID">010555612345600000</ram:ID>') && str_contains($xml, '<ram:ID schemeID="TXID">010555698765400001</ram:ID>')
    && str_contains($xml, '<ram:CityName>1007</ram:CityName>') && str_contains($xml, '<ram:CitySubDivisionName>100701</ram:CitySubDivisionName>')
    && str_contains($xml, '<ram:CountrySubDivisionID>10</ram:CountrySubDivisionID>') && str_contains($xml, '<ram:BuildingNumber>99/1</ram:BuildingNumber>')
    && str_contains($xml, '<ram:CalculatedRate>7</ram:CalculatedRate>')
    && str_contains($xml, 'xmldsig-more#rsa-sha512') && !preg_match('/<ram:[A-Za-z]+\/>(?<!ApplicableHeaderTradeDelivery\/>)/', $xml), substr($xml, 0, 300));
ok('e-Tax XML is kept: the same file again, logged once', $a->page("/api/etax/$inv/xml")[1] === $xml
    && one("SELECT COUNT(*) FROM etax_log WHERE etax_doc = '$inv' AND etax_to = 'XML'") == 1);
$invDate = one("SELECT ih_inv_date FROM ih_mstr WHERE ih_inv_nbr = '$inv'");
db("INSERT INTO ih_mstr (ih_inv_nbr, ih_cm_code, ih_inv_date, ih_type, ih_ref_inv, ih_status, ih_curr, ih_ex_rate, ih_net_amt, ih_tax_amt, ih_tot_amt, created_by)
    SELECT 'CN-XML1', ih_cm_code, '$invDate', 'CM', ih_inv_nbr, 'U', ih_curr, 1, -100, -7, -107, 'admin' FROM ih_mstr WHERE ih_inv_nbr = '$inv'");
db("INSERT INTO ihd_det (ihd_inv_nbr, ihd_line, ihd_part, ihd_desc, ihd_qty_inv, ihd_um, ihd_price, ihd_disc_pct, ihd_line_amt, ihd_tax_code)
    SELECT 'CN-XML1', 1, ihd_part, 'รับคืน', -1, ihd_um, 100, 0, -100, ihd_tax_code FROM ihd_det WHERE ihd_inv_nbr = '$inv' LIMIT 1");
[$st, $cn] = $a->page('/api/etax/CN-XML1/xml');
$cn = (string) $cn;
$orig = number_format((float) one("SELECT ih_net_amt FROM ih_mstr WHERE ih_inv_nbr = '$inv'"), 2, '.', '');
ok('e-Tax XML credit note 81: original, corrected, difference, purpose and the invoice it refers to', $st === 200 && Phai\Core\Xades::verify($cn)
    && str_contains($cn, '<rsm:DebitCreditNote_CrossIndustryInvoice') && str_contains($cn, '<ram:TypeCode>81</ram:TypeCode>')
    && str_contains($cn, '<ram:PurposeCode>CDNG99</ram:PurposeCode>') && str_contains($cn, "<ram:IssuerAssignedID>$inv</ram:IssuerAssignedID>")
    && str_contains($cn, "<ram:OriginalInformationAmount>$orig</ram:OriginalInformationAmount>")
    && str_contains($cn, '<ram:DifferenceInformationAmount>100.00</ram:DifferenceInformationAmount>') && str_contains($cn, '<ram:GrandTotalAmount>107.00</ram:GrandTotalAmount>'), substr($cn, 0, 300));
[$st, $zipBody] = $a->page('/etax/xml/' . substr($invDate, 0, 7));
$zf = tempnam(sys_get_temp_dir(), 'z');
file_put_contents($zf, (string) $zipBody);
$z = new ZipArchive();
ok('e-Tax XML of the month: one zip with every signed document', $st === 200 && $z->open($zf) === true
    && $z->getFromName(preg_replace('/[^A-Za-z0-9._-]/', '_', $inv) . '.xml') === $xml && $z->getFromName('CN-XML1.xml') === $cn);
@$z->close();
@unlink($zf);
db("UPDATE ih_mstr SET ih_status = 'C' WHERE ih_inv_nbr = 'CN-XML1'");
ok('e-Tax XML: a cancelled document has none', $a->req('GET', '/api/etax/CN-XML1/xml')[0] === 409);
// debit note (ใบเพิ่มหนี้, DN): made by hand against an invoice, posted like one, XML 80
$cm = one("SELECT ih_cm_code FROM ih_mstr WHERE ih_inv_nbr = '$inv'");
$dnBody = ['ih_type' => 'DN', 'ih_cm_code' => $cm, 'ih_inv_date' => $today, 'ih_curr' => 'THB', 'ih_ex_rate' => '1', 'ih_tax_code' => 'VAT7',
    'ih_remarks' => 'ค่าขนส่งเพิ่มเติม', 'lines' => ['1' => ['ihd_part' => 'FREIGHT', 'ihd_desc' => 'ค่าขนส่งเพิ่มเติม', 'ihd_um' => 'EA', 'ihd_qty_inv' => '1', 'ihd_price' => '200']]];
$r = $a->req('POST', '/api/ar-invoices', $dnBody);
ok('debit note needs the invoice it adds to', $r[0] === 422 && isset($r[1]['fields']['ih_ref_inv']), $r[1]);
$r = $a->req('POST', '/api/ar-invoices', $dnBody + ['ih_ref_inv' => $inv]);
ok('debit note needs a reason code', $r[0] === 422 && isset($r[1]['fields']['ih_reason']), $r[1]);
$r = $a->req('POST', '/api/ar-invoices', $dnBody + ['ih_ref_inv' => $inv, 'ih_reason' => 'CDNG02']);
ok('a credit-note reason does not fit a debit note', $r[0] === 422 && isset($r[1]['fields']['ih_reason']), $r[1]);
$r = $a->req('POST', '/api/ar-invoices', ['ih_remarks' => ''] + $dnBody + ['ih_ref_inv' => $inv, 'ih_reason' => 'DBNG99']);
ok('"other" reason needs the reason written in the remarks', $r[0] === 422 && isset($r[1]['fields']['ih_remarks']), $r[1]);
$r = $a->req('POST', '/api/ar-invoices', $dnBody + ['ih_ref_inv' => $inv, 'ih_reason' => 'DBNG01']);
$dn = $r[1]['data']['nbr'] ?? '';
ok('debit note saved: DN number, receivable up, posted like an invoice', $r[0] === 200 && str_starts_with($dn, 'DN')
    && one("SELECT ar_amt FROM ar_mstr WHERE ar_nbr = '$dn'") == 214 && one("SELECT ih_type FROM ih_mstr WHERE ih_inv_nbr = '$dn'") === 'DN'
    && round((float) one("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_ref = '$dn'"), 2) == 0.0, $r[1]);
ok('debit note in the output VAT report as a plus', in_array($dn, array_column($a->req('GET', '/api/vat-sales?month=' . substr($today, 0, 7))[1]['data']['rows'] ?? [], 'nbr'), true));
[$st, $html] = $a->page("/ar-invoices/$dn/print?html=1");
ok('debit note prints as ใบเพิ่มหนี้ with its reason', $st === 200 && str_contains((string) $html, 'ใบเพิ่มหนี้')
    && str_contains((string) $html, 'DBNG01 · มีการเพิ่มราคาค่าสินค้า'));
[$st, $dx] = $a->page("/api/etax/$dn/xml");
$dx = (string) $dx;
$orig = (float) one("SELECT ih_net_amt FROM ih_mstr WHERE ih_inv_nbr = '$inv'");
ok('e-Tax XML debit note 80: the chosen reason, original + difference = corrected', $st === 200 && Phai\Core\Xades::verify($dx)
    && str_contains($dx, '<ram:TypeCode>80</ram:TypeCode>') && str_contains($dx, '<ram:Name>ใบเพิ่มหนี้</ram:Name>') && str_contains($dx, '<ram:PurposeCode>DBNG01</ram:PurposeCode>')
    && str_contains($dx, '<ram:Purpose>ค่าขนส่งเพิ่มเติม</ram:Purpose>')
    && str_contains($dx, '<ram:LineTotalAmount>' . number_format($orig + 200, 2, '.', '') . '</ram:LineTotalAmount>')
    && str_contains($dx, '<ram:DifferenceInformationAmount>200.00</ram:DifferenceInformationAmount>') && str_contains($dx, '<ram:GrandTotalAmount>214.00</ram:GrandTotalAmount>'), substr($dx, 0, 300));
ok('e-Tax email subject for a debit note: [date][DBN][number][invoice] (year set to Gregorian above)',
    ($a->req('GET', "/api/etax/$dn")[1]['data']['subject'] ?? '') === '[' . date('d/m/Y', strtotime($today)) . "][DBN][$dn][$inv]");

// ใบรับ (T01) from a cash receipt: one line per invoice paid
$crn = one("SELECT cr_nbr FROM cr_mstr WHERE cr_status = 'P' ORDER BY cr_nbr LIMIT 1");
$settled = number_format((float) one("SELECT SUM(crd_amt + crd_wht_amt) FROM crd_det WHERE crd_nbr = '$crn' AND crd_amt + crd_wht_amt >= 0"), 2, '.', '');
[$st, $rx] = $a->page("/api/etax/receipts/$crn/xml");
$rx = (string) $rx;
ok('e-Tax XML receipt T01: signed, a line per invoice, total settled', $st === 200 && Phai\Core\Xades::verify($rx)
    && str_contains($rx, '<rsm:Receipt_CrossIndustryInvoice') && str_contains($rx, '<ram:TypeCode>T01</ram:TypeCode>')
    && str_contains($rx, "<ram:LineTotalAmount>$settled</ram:LineTotalAmount>")
    && substr_count($rx, '<ram:IncludedSupplyChainTradeLineItem>') === (int) one("SELECT COUNT(*) FROM crd_det WHERE crd_nbr = '$crn' AND crd_amt + crd_wht_amt >= 0"), substr($rx, 0, 300));
ok('cash receipt page offers the XML', str_contains((string) $a->page("/cash-receipts/$crn")[1], 'etax\/receipts\/{nbr}\/xml'));
[$st, $zipBody] = $a->page('/etax/xml/' . substr($today, 0, 7));
$zf = tempnam(sys_get_temp_dir(), 'z');
file_put_contents($zf, (string) $zipBody);
$z = new ZipArchive();
ok('XML of the month includes debit notes and receipts', $st === 200 && $z->open($zf) === true && $z->getFromName("$dn.xml") === $dx
    && (substr(one("SELECT cr_date FROM cr_mstr WHERE cr_nbr = '$crn'"), 0, 7) !== substr($today, 0, 7) || $z->getFromName("$crn.xml") === $rx));
@$z->close();
@unlink($zf);
ok('certificate removed', array_key_exists('cert', $r = $a->req('DELETE', '/api/settings/etax/cert')[1]['data'] ?? []) && $r['cert'] === null && !is_file("$etaxDir/cert.p12"), $r);
$rm = function (string $d) use (&$rm) {
    foreach (glob("$d/*") ?: [] as $f) {
        is_dir($f) ? $rm($f) : unlink($f);
    }
    @rmdir($d);
};
$rm($etaxDir);

// a service: output VAT is not due on the invoice but on each payment, and the receipt is the tax invoice
$cmSvc = one("SELECT cm_code FROM cm_mstr ORDER BY cm_code LIMIT 1");
$r = $a->req('POST', '/api/ar-invoices', ['ih_type' => 'IN', 'ih_cm_code' => $cmSvc, 'ih_inv_date' => $today, 'ih_curr' => 'THB', 'ih_ex_rate' => '1',
    'ih_tax_code' => 'VAT7', 'ih_vat_on_rcpt' => '1', 'lines' => ['1' => ['ihd_part' => 'SERVICE', 'ihd_desc' => 'ค่าบริการ', 'ihd_um' => 'EA', 'ihd_qty_inv' => '1', 'ihd_price' => '1000']]]);
$svc = $r[1]['data']['nbr'] ?? '';
$glSvc = fn (string $ref, string $acct) => (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_ref = '$ref' AND gl_acct = '$acct'");
ok('service invoice: VAT waits in "not yet due", not in the sales VAT report', $r[0] === 200 && $glSvc($svc, '21102') == -70.0 && $glSvc($svc, '21101') == 0.0
    && !in_array($svc, array_column($a->req('GET', '/api/vat-sales?month=' . substr($today, 0, 7))[1]['data']['rows'] ?? [], 'nbr'), true), $r[1]);
$cr1 = $a->req('POST', '/api/cash-receipts', ['customer' => $cmSvc, 'cr_date' => $today, 'cr_bank' => 'BANK1', 'lines' => [$svc => ['amt' => '535']]])[1]['data']['nbr'] ?? '';
$cr2 = $a->req('POST', '/api/cash-receipts', ['customer' => $cmSvc, 'cr_date' => $today, 'cr_bank' => 'BANK1', 'lines' => [$svc => ['amt' => '535']]])[1]['data']['nbr'] ?? '';
$vs = array_column($a->req('GET', '/api/vat-sales?month=' . substr($today, 0, 7))[1]['data']['rows'] ?? [], null, 'nbr');
ok('each payment makes its share due; the receipts are in the sales VAT report', $glSvc($cr1, '21101') == -35.0 && $glSvc($cr2, '21101') == -35.0
    && $glSvc($cr1, '21102') + $glSvc($cr2, '21102') == 70.0 && (float) ($vs[$cr1]['tax'] ?? 0) === 35.0 && (float) ($vs[$cr2]['net'] ?? 0) === 500.0, [$cr1, $cr2, $vs[$cr1] ?? null]);
ok('the service receipt prints as receipt / tax invoice, the invoice is no tax invoice', str_contains((string) $a->page("/cash-receipts/$cr1/print?html=1")[1], 'ใบกำกับภาษี')
    && !str_contains((string) $a->page("/ar-invoices/$svc/print?html=1")[1], 'ใบแจ้งหนี้ / ใบกำกับภาษี'));

// financial statements: assets = liabilities + equity (profit of the year sits in equity)
$fs = $a->req('GET', '/api/financial-statements')[1]['data'] ?? [];
ok('financial statements balance', isset($fs['assets']) && abs($fs['assets'] - $fs['liabilities'] - $fs['totals']['equity']) < 0.01
    && abs($fs['net'] - ($fs['gross'] + $fs['totals']['other_income'] - $fs['totals']['expenses'] - $fs['totals']['finance_cost'] - $fs['totals']['income_tax'])) < 0.01, $fs['totals'] ?? $fs);
ok('statements print', $a->page('/financial-statements/' . $today . '/print?html=1')[0] === 200 && $a->page('/financial-statements')[0] === 200);

// ภ.พ.30: output − input − carried; filing closes both VAT accounts into VAT payable / credit
$prevMonth = date('Y-m', strtotime(date('Y-m-01') . ' -1 month'));
ok('VAT return of this month cannot be filed yet', $a->req('POST', '/api/vat-return', ['month' => substr($today, 0, 7)])[0] === 409);
$vr = $a->req('GET', "/api/vat-return?month=$prevMonth")[1]['data'] ?? [];
$r = $a->req('POST', '/api/vat-return', ['month' => $prevMonth]);
$vrGl = fn (string $acct) => (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_type = 'VR' AND gl_ref = '$prevMonth' AND gl_acct = '$acct'");
ok('VAT return filed and posted', $r[0] === 200 && ($r[1]['data']['filed'] ?? false) && abs($vrGl('21101') - $vr['output']) < 0.01
    && abs($vrGl('13101') + $vr['input']) < 0.01 && abs($vrGl($vr['net'] >= 0 ? '21105' : '13104') + $vr['net']) < 0.01, [$r[1], $vr]);
ok('filed twice refused, page and print open', $a->req('POST', '/api/vat-return', ['month' => $prevMonth])[0] === 409
    && $a->page('/vat-return')[0] === 200 && $a->page("/vat-return/$prevMonth/print?html=1")[0] === 200);
$r = $a->req('DELETE', "/api/vat-return/$prevMonth");
ok('undo the latest return reverses it', $r[0] === 200 && !($r[1]['data']['filed'] ?? true) && abs($vrGl('21101')) < 0.01);

finish();
