<?php

// Sample data: loaded by the installer; it stays (install again for an empty system). @demo

declare(strict_types=1);

require __DIR__ . '/lib.php';

function demoRows(): int
{
    $n = 0;
    foreach (db("SELECT TABLE_NAME AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . E2E_DB . "' AND COLUMN_NAME = 'created_by'") as $r) {
        $n += (int) one('SELECT COUNT(*) FROM `' . $r['t'] . "` WHERE created_by = 'demo'");
    }
    return $n;
}
function cli(string $args): string
{
    return (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PHAI_ROOT . '/bin/phai') . " $args 2>&1");
}

$a = login('admin', $pw);
ok('sample data made by the switched-off user "demo"', demoRows() > 150 && one("SELECT us_active FROM us_mstr WHERE us_userid = 'demo'") == 0
    && (int) one("SELECT COUNT(*) FROM po_mstr WHERE created_by <> 'demo'") === 0, demoRows());
ok('every sample item is kept by lot, and every stock row has one (invoice, work order or receipt number)',
    (int) one("SELECT COUNT(*) FROM pt_mstr WHERE created_by = 'demo' AND pt_lot_ser <> 'L'") === 0
    && (int) one("SELECT COUNT(*) FROM ld_det WHERE ld_lot = ''") === 0 && (int) one("SELECT COUNT(*) FROM ld_det WHERE ld_lot LIKE 'WO%'") > 0
    && (int) one("SELECT COUNT(*) FROM ld_det WHERE ld_lot IN ('ST-6612', 'HW-0381')") > 0);
// every menu has something to look at
$filled = [];
foreach (['ex_mstr', 'ptg_mstr', 'ptt_mstr', 'sub_mstr', 'pc_mstr', 'pt_site_mstr', 'pr_mstr', 'lc_mstr', 'qc_hist', 'bkr_mstr', 'pi_mstr', 'apv_mstr', 'bl_mstr', 'mrp_det', 'wis_mstr'] as $t) {
    if ((int) one("SELECT COUNT(*) FROM $t") === 0) $filled[] = $t;
}
ok('every menu has sample rows', !$filled, $filled);
ok('a receipt still waiting for its invoice', (int) one("SELECT COUNT(*) FROM rc_mstr WHERE rc_ap_ref IS NULL") > 0);
ok('a credit note and a second warehouse with its address', (int) one("SELECT COUNT(*) FROM ih_mstr WHERE ih_type = 'CM'") === 1
    && one("SELECT si_line1 FROM si_mstr WHERE si_site = 'WH2'") === '12 ถนน สีลม');
ok('one payment withholds at two rates (1% and 3%) on one 50 ทวิ', one("SELECT GROUP_CONCAT(ROUND(d.whtd_pct) ORDER BY d.whtd_line) FROM whtd_det d JOIN wht_cert c ON c.whtc_nbr = d.whtd_nbr WHERE c.whtc_doc_ref LIKE 'PV%'") === '1,3'
    && (float) one("SELECT SUM(whtc_tax_amt) FROM wht_cert WHERE whtc_doc_ref LIKE 'PV%'") === 80.0);
ok('petty cash: two holders, counted months, a cash deposit, the boxes add up to the account', (int) one('SELECT COUNT(DISTINCT cbh_holder) FROM cbh_hist') === 2
    && (int) one('SELECT COUNT(*) FROM cbc_mstr') === 4 && one("SELECT 1 FROM cb_mstr WHERE cb_type = 'DI' AND cb_ref LIKE 'DP%'")
    && (float) one("SELECT SUM(gl_amt) FROM gl_trhist WHERE gl_acct = '10102'") === (float) one('SELECT SUM(cbh_amt) FROM cbh_hist'));
ok('vendors and customers have tax ids and addresses in parts', (int) one("SELECT COUNT(*) FROM vd_mstr WHERE vd_tax_id_num IS NULL OR vd_road IS NULL") === 0
    && (int) one("SELECT COUNT(*) FROM cm_mstr WHERE cm_tax_id_num IS NULL OR cm_road IS NULL") === 0);
ok('nobody can sign in as demo', login('demo', 'anything')->req('GET', '/api/parts')[0] === 401);
// it stays: nothing on the home page, in the API or on the command line takes it out (install again for an empty system)
ok('no way to remove it', !str_contains((string) $a->page('/')[1], 'demo-remove') && $a->req('POST', '/api/demo/remove')[0] === 404
    && !str_contains(cli(''), '--remove'));
ok('command line: not into a system that has data', str_contains(cli('demo'), 'empty system') || str_contains(cli('demo'), 'ใส่ข้อมูลตัวอย่างได้เฉพาะ'));
ok('ledger balances', abs((float) one('SELECT COALESCE(SUM(gl_amt), 0) FROM gl_trhist')) < 0.001);

finish();
