<?php

// Petty cash: each holder's own box, money passed on without posting, expenses with VAT and withholding, files,
// month-end counts that lock the month and hold back the period close
// Run through tests/e2e/run.php (needs a fresh install). Never prints passwords.

declare(strict_types=1);

require __DIR__ . '/lib.php';

$a = login('admin', $pw);
$m1 = date('Y-m', strtotime(date('Y-m-01') . ' -1 month'));  // last month: counted in this suite
$today = date('Y-m-d');
$gl = fn (string $ref, string $acct) => (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_ref = '$ref' AND gl_acct = '$acct'");
$box = fn (string $who) => (float) one("SELECT COALESCE(SUM(cbh_amt), 0) FROM cbh_hist WHERE cbh_holder = '$who'");

// the books open at last month, so its entries post and its count can be made
pdo()->exec("UPDATE gl_ctrl SET gl_curr_year = " . (int) substr($m1, 0, 4) . ", gl_curr_per = " . (int) substr($m1, 5, 2) . " WHERE ctrl_id = 1");
// the runner sets every series to N6; this suite checks numbers by month (PC + yyyymm + 4 digits)
pdo()->exec("UPDATE gl_ctrl SET gl_pc_format = 'YM4' WHERE ctrl_id = 1");
$a->req('POST', '/api/banks', ['bk_bank' => 'BANK1', 'bk_desc' => 'ออมทรัพย์', 'bk_acct' => '10201', 'bk_curr' => 'THB', 'bk_active' => true]);
$a->req('POST', '/api/vendors', party('vd', ['vd_code' => 'V1', 'vd_name' => 'ผู้ขายหนึ่ง', 'vd_status' => 'ACTIVE', 'vd_terms' => '30DAYS', 'vd_curr' => 'THB',
    'vd_tax_code' => 'VAT7', 'vd_tax_id_num' => '0105566001234']));
$a->req('POST', '/api/roles', ['role_code' => 'CASH', 'role_desc' => 'Petty cash']);
$a->req('PUT', '/api/roles/CASH/perms', ['perms' => ['petty-cash' => ['view', 'add', 'edit', 'delete', 'print'], 'payables' => ['view', 'add'],
    'ap-vouchers' => ['view', 'add'], 'vouchers' => ['view', 'add', 'delete']]]);
$a->req('POST', '/api/roles', ['role_code' => 'CASHVIEW', 'role_desc' => 'Petty cash viewer']);
$a->req('PUT', '/api/roles/CASHVIEW/perms', ['perms' => ['petty-cash' => ['view']]]);
foreach (['ann' => 'CASH', 'bob' => 'CASH', 'pcview' => 'CASHVIEW'] as $u => $role) {
    $a->req('POST', '/api/users', ['us_userid' => $u, 'us_name' => ucfirst($u), 'us_email' => "$u@example.test", 'us_role' => $role, 'password' => $pw]);
}
$ann = login('ann', $pw);
$bob = login('bob', $pw);
$view = login('pcview', $pw);
ok('page /petty-cash', $ann->page('/petty-cash')[0] === 200);

// ------------------------------------------------------------------ withdraw, pass on
$r = $ann->req('POST', '/api/petty-cash', ['type' => 'WD', 'date' => "$m1-05", 'bank' => 'BANK1', 'amount' => '5000']);
$wd = $r[1]['data']['nbr'] ?? '';
ok('withdrawing posts Dr petty cash / Cr bank to the holder\'s box', $r[0] === 200 && $gl($wd, '10102') == 5000 && $gl($wd, '10201') == -5000 && $box('ann') == 5000, $r[1]);
$r = $bob->req('POST', '/api/petty-cash', ['type' => 'TF', 'date' => "$m1-06", 'from' => 'ann', 'amount' => '2000']);
$tf = $r[1]['data']['nbr'] ?? '';
$to = (string) one("SELECT cb_ref FROM cb_mstr WHERE cb_nbr = '$tf'");
ok('the receiver records money passed on: the giver\'s entry and the receiver\'s, each its own number, tied, no ledger entry', $r[0] === 200
    && one("SELECT cb_type FROM cb_mstr WHERE cb_nbr = '$to'") === 'TO' && one("SELECT cb_ref FROM cb_mstr WHERE cb_nbr = '$to'") === $tf
    && (int) one("SELECT COUNT(*) FROM cbh_hist WHERE cbh_nbr IN ('$tf', '$to')") === 2 && (int) one("SELECT COUNT(*) FROM gl_trhist WHERE gl_ref IN ('$tf', '$to')") === 0
    && $box('ann') == 3000 && $box('bob') == 2000, $r[1]);
ok('nobody passes on more than the sender holds', $bob->req('POST', '/api/petty-cash', ['type' => 'TF', 'date' => "$m1-06", 'from' => 'ann', 'amount' => '9000'])[0] === 422);
ok('only the holder cancels: another user gets 403', $bob->req('POST', "/api/petty-cash/$wd/cancel")[0] === 403);
ok('...and only what one entered oneself: the sender cannot cancel or change money passed on', $ann->req('POST', "/api/petty-cash/$tf/cancel")[0] === 403
    && $ann->req('PUT', "/api/petty-cash/$tf", ['date' => "$m1-06", 'from' => 'ann', 'amount' => '1'])[0] === 403);
$r = $ann->req('PUT', "/api/petty-cash/$wd", ['date' => "$m1-05", 'bank' => 'BANK1', 'amount' => '5500']);
ok('the maker corrects an entry: same number, register and ledger follow', $r[0] === 200 && $gl($wd, '10102') == 5500 && $gl($wd, '10201') == -5500 && $box('ann') == 3500
    && (int) one("SELECT COUNT(*) FROM cb_mstr WHERE cb_nbr = '$wd'") === 1, $r[1]);
ok('...and cannot take back cash already passed on', $ann->req('PUT', "/api/petty-cash/$wd", ['date' => "$m1-05", 'bank' => 'BANK1', 'amount' => '1000'])[0] === 409);
$ann->req('PUT', "/api/petty-cash/$wd", ['date' => "$m1-05", 'bank' => 'BANK1', 'amount' => '5000']);
ok('a viewer reads every box but cannot make entries', $view->req('GET', '/api/petty-cash/box?holder=ann')[0] === 200
    && $view->req('POST', '/api/petty-cash', ['type' => 'WD', 'date' => "$m1-05", 'bank' => 'BANK1', 'amount' => '1'])[0] === 403);

// ------------------------------------------------------------------ an expense with a full tax invoice and withholding
$r = $bob->req('POST', '/api/petty-cash', ['type' => 'EX', 'date' => "$m1-08", 'vendor' => 'V1', 'payee' => '', 'wht_form' => 'PND53',
    'inv_nbr' => 'T-001', 'inv_date' => "$m1-08", 'lines' => [['acct' => '50503', 'desc' => 'บริการ', 'amt' => '1000', 'vat' => 'VAT7', 'wht' => 'WHT3']]]);
$ex = $r[1]['data']['nbr'] ?? '';
ok('expense: Dr expense 1000 + VAT 70 / Cr withholding 30 / Cr petty cash 1040', $r[0] === 200 && $gl($ex, '50503') == 1000 && $gl($ex, '13101') == 70
    && $gl($ex, '10102') == -1040 && $box('bob') == 960, $r[1]);
$cert = one("SELECT whtc_nbr FROM wht_cert WHERE whtc_doc_ref = '$ex'");
ok('...its 50 ทวิ is in the same series as payments and prints', $cert !== null && str_starts_with((string) $cert, 'WT') && $a->page("/wht/$cert/print?html=1")[0] === 200);
ok('...and its VAT is in the purchase VAT report', in_array($ex, array_column($a->req('GET', "/api/vat-purchases?month=$m1")[1]['data']['rows'] ?? [], 'ref'), true));
ok('withholding needs a vendor for the certificate', isset($bob->req('POST', '/api/petty-cash', ['type' => 'EX', 'date' => "$m1-08", 'payee' => 'X',
    'lines' => [['acct' => '50503', 'amt' => '10', 'wht' => 'WHT3']]])[1]['fields']['vendor']));
ok('a box never goes below nothing', $bob->req('POST', '/api/petty-cash', ['type' => 'EX', 'date' => "$m1-09", 'payee' => 'X', 'lines' => [['acct' => '50401', 'desc' => 'ค่าของใช้', 'amt' => '5000']]])[0] === 422);

// ------------------------------------------------------------------ files
$png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
// the shared files routes (System\AttachmentController) with petty cash's own rule: only the holder, while the month is open
$r = $bob->req('POST', "/api/files/PC/$ex", ['name' => 'receipt.png', 'data' => $png]);
$file = $r[1]['data']['att_id'] ?? 0;
ok('the holder attaches a picture; anyone who may look opens it', $r[0] === 200 && $view->page("/files/PC/$ex/$file")[0] === 200
    && ($bob->req('GET', "/api/files/PC/$ex")[1]['data']['can']['add'] ?? null) === true && ($view->req('GET', "/api/files/PC/$ex")[1]['data']['can']['add'] ?? null) === false
    && ($view->req('GET', '/api/files/PC?nbrs=' . $ex)[1]['data'][$ex] ?? 0) === 1);
ok('...not someone else, and not a file that is not a picture or PDF', $ann->req('POST', "/api/files/PC/$ex", ['name' => 'x.png', 'data' => $png])[0] === 403
    && $ann->req('DELETE', "/api/files/PC/$ex/$file")[0] === 403
    && $bob->req('POST', "/api/files/PC/$ex", ['name' => 'x.txt', 'data' => 'data:text/plain;base64,' . base64_encode('hello')])[0] === 422);

// ------------------------------------------------------------------ month end
ok('nothing dated this month before last month is counted', isset($ann->req('POST', '/api/petty-cash', ['type' => 'WD', 'date' => $today, 'bank' => 'BANK1', 'amount' => '1'])[1]['fields']['date']));
ok('the period cannot close while a box of it is not counted', $a->req('POST', '/api/trial-balance/close')[0] === 409);
$r = $ann->req('POST', '/api/petty-cash/count', ['month' => $m1, 'notes' => ['1000' => 2, '500' => 1, '100' => 4], 'remarks' => 'ขาด']);
$ct = $r[1]['data']['nbr'] ?? '';
ok('a count 100 short posts Dr cash over and short / Cr petty cash on the month\'s last day', $r[0] === 200 && ($r[1]['data']['diff'] ?? null) == -100
    && $gl($ct, '59902') == 100 && $gl($ct, '10102') == -100 && one("SELECT gl_date FROM gl_trhist WHERE gl_ref = '$ct' LIMIT 1") === date('Y-m-t', strtotime("$m1-01")) && $box('ann') == 2900, $r[1]);
ok('a counted month takes no more entries', isset($ann->req('POST', '/api/petty-cash', ['type' => 'WD', 'date' => "$m1-20", 'bank' => 'BANK1', 'amount' => '1'])[1]['fields']['date']));
ok('money passed on needs both boxes open', isset($bob->req('POST', '/api/petty-cash', ['type' => 'TF', 'date' => "$m1-20", 'from' => 'ann', 'amount' => '1'])[1]['fields']['date']));
ok('the count can be undone while nothing is dated after it', $ann->req('POST', '/api/petty-cash/count/cancel')[0] === 200 && $box('ann') == 3000
    && $ann->req('POST', '/api/petty-cash/count', ['month' => $m1, 'notes' => ['1000' => 3]])[0] === 200 && $box('ann') == 3000);
ok('the other holder counts exactly', ($bob->req('POST', '/api/petty-cash/count', ['month' => $m1, 'notes' => ['500' => 1, '100' => 4, '50' => 1, '10' => 1]])[1]['data']['diff'] ?? null) == 0);
// ...then finds a bill not entered: undo the count, enter and correct it in that month, count again
ok('a forgotten bill cannot go into a counted month', $bob->req('POST', '/api/petty-cash', ['type' => 'EX', 'date' => "$m1-15", 'payee' => 'ร้าน', 'lines' => [['acct' => '50401', 'desc' => 'ค่าของใช้', 'amt' => '60']]])[0] === 422);
ok('undo the count', $bob->req('POST', '/api/petty-cash/count/cancel')[0] === 200);
$r = $bob->req('POST', '/api/petty-cash', ['type' => 'EX', 'date' => "$m1-15", 'payee' => 'ร้าน', 'lines' => [['acct' => '50401', 'desc' => 'ค่าของใช้', 'amt' => '60']]]);
$late = $r[1]['data']['nbr'] ?? '';
$prev = (string) one("SELECT MAX(cb_nbr) FROM cb_mstr WHERE cb_nbr LIKE 'PC" . str_replace('-', '', $m1) . "%' AND cb_nbr <> '$late'");
ok('the forgotten bill takes the next number of its own month', $r[0] === 200 && str_starts_with($late, 'PC' . str_replace('-', '', $m1)) && (int) substr($late, -4) === (int) substr($prev, -4) + 1, [$late, $prev]);
$r = $bob->req('PUT', "/api/petty-cash/$late", ['date' => "$m1-15", 'payee' => 'ร้าน', 'lines' => [['acct' => '50401', 'desc' => 'ค่าของใช้', 'amt' => '50']]]);
ok('...is corrected (50, not 60)', $r[0] === 200 && $gl($late, '50401') == 50 && $gl($late, '10102') == -50 && $box('bob') == 910, $r[1]);
ok('a bill without a receipt number, tax invoice or file holds back the count', isset($bob->req('POST', '/api/petty-cash/count', ['month' => $m1, 'notes' => ['500' => 1]])[1]['fields']['month']));
$bob->req('PUT', "/api/petty-cash/$late", ['date' => "$m1-15", 'payee' => 'ร้าน', 'rcpt_nbr' => 'R-15', 'lines' => [['acct' => '50401', 'desc' => 'ค่าของใช้', 'amt' => '50']]]);
ok('...and counted again once it has one', ($bob->req('POST', '/api/petty-cash/count', ['month' => $m1, 'notes' => ['500' => 1, '100' => 4, '10' => 1]])[1]['data']['diff'] ?? null) == 0);
ok('with every box counted the period closes', $a->req('POST', '/api/trial-balance/close')[0] === 200);
ok('...and a count of a closed period stays', $ann->req('POST', '/api/petty-cash/count/cancel')[0] === 409);

// ------------------------------------------------------------------ this month, cancel, prints
$r = $bob->req('POST', '/api/petty-cash', ['type' => 'EX', 'date' => $today, 'payee' => 'ร้าน', 'lines' => [['acct' => '50401', 'desc' => 'ค่าของใช้', 'amt' => '10']]]);
$x = $r[1]['data']['nbr'] ?? '';
ok('the next month opens once counted; a cancelled entry nets to nothing', $r[0] === 200 && $bob->req('POST', "/api/petty-cash/$x/cancel")[0] === 200
    && one("SELECT cb_status FROM cb_mstr WHERE cb_nbr = '$x'") === 'X' && $gl($x, '10102') == 0 && $box('bob') == 910);
ok('money passed on stays as it is, even for its receiver (the giver receives it back instead)', $bob->req('POST', "/api/petty-cash/$tf/cancel")[0] === 409
    && $bob->req('PUT', "/api/petty-cash/$tf", ['date' => "$m1-06", 'from' => 'ann', 'amount' => '1'])[0] === 409);
$r = $ann->req('POST', '/api/petty-cash', ['type' => 'IN', 'date' => $today, 'payee' => 'ร้านรับซื้อของเก่า', 'acct' => '40901', 'amount' => '100']);
$in = $r[1]['data']['nbr'] ?? '';
ok('money received into the box (scrap sold) posts Dr petty cash / Cr other income', $r[0] === 200 && $gl($in, '10102') == 100 && $gl($in, '40901') == -100, $r[1]);
// paying a vendor in cash (bank CASH = เงินสดย่อย): out of the payer's own box, changed only on the payment
$ap = $bob->req('POST', '/api/payables', ['vendor' => 'V1', 'ap_date' => $today, 'ap_inv_nbr' => 'C-1', 'tax_code' => 'VAT7', 'lines' => [['acct' => '50401', 'amt' => '100']]])[1]['data']['nbr'] ?? '';
$vo = $bob->req('POST', '/api/ap-vouchers', ['vendor' => 'V1', 'apv_date' => $today, 'apv_pay_date' => $today, 'refs' => [$ap]])[1]['data']['nbr'] ?? '';
$r = $bob->req('POST', '/api/vouchers', ['vendor' => 'V1', 'vo_date' => $today, 'vo_bank' => 'CASH', 'lines' => [$vo => ['amt' => '107']]]);
$pv = $r[1]['data']['nbr'] ?? '';
$do = (string) one("SELECT cb_nbr FROM cb_mstr WHERE cb_ref = '$pv'");
ok('a payment in cash comes out of the payer\'s petty cash (Cr petty cash on the payment)', $r[0] === 200 && $box('bob') == 803 && $gl($pv, '10102') == -107
    && $do !== '' && $bob->req('POST', "/api/petty-cash/$do/cancel")[0] === 409, $r[1]);
ok('...and cancelling the payment puts the cash back', $bob->req('POST', "/api/vouchers/$pv/cancel")[0] === 200 && $box('bob') == 910
    && one("SELECT cb_status FROM cb_mstr WHERE cb_nbr = '$do'") === 'X');
ok('petty cash is not a bank to reconcile', !in_array('CASH', array_column($a->req('GET', '/api/bank-rec/waiting')[1]['data'] ?? [], 'bk_bank'), true));
ok('voucher and register print', $bob->page("/petty-cash/$ex/print?html=1")[0] === 200 && $bob->page("/petty-cash/register/$m1/print?holder=bob&html=1")[0] === 200);
ok('the boxes add up to the petty cash account', (float) one("SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist WHERE gl_acct = '10102'") == (float) one('SELECT SUM(cbh_amt) FROM cbh_hist'));

finish();
