<?php

declare(strict_types=1);

namespace Phai\Install;

use Phai\Core\App;
use Phai\Core\Auth\Auth;
use Phai\Core\Http\HttpException;
use Phai\Core\Http\Request;
use Phai\Core\Routing\Router;

/**
 * Sample data for trying PhaiERP: a made-up company that makes steel shelves and tables,
 * with two months of purchases, production, sales and payments.
 *
 * Everything goes through the same API as the screens (as the given admin user), so stock,
 * costs and accounting are exactly what real use would produce. All names are fictional.
 */
final class Demo
{
    private Router $router;

    /** Numbers of the documents made so far, for the steps that follow them. */
    private array $n = [];

    public function __construct(private readonly string $userId)
    {
        $this->router = new Router();
        $this->router->load(PHAI_ROOT . '/src/Modules');
    }

    /**
     * Sample data is created by this (inactive) user and the sample users it makes (created_by shows it). It goes in
     * at installation only and stays: to start without it, install again (remove config.php and the database).
     */
    public const USER = 'demo';

    public static function loaded(): bool
    {
        return (bool) App::db()->value('SELECT 1 FROM us_mstr WHERE us_userid = ?', [self::USER]);
    }

    /** Load the sample data into an installed, still empty database. */
    public static function load(): void
    {
        if ((int) App::db()->value('SELECT COUNT(*) FROM pt_mstr') > 0 || self::loaded()) {
            throw new HttpException(409, t('install.demo_not_empty'));
        }
        $before = $_SESSION['user_id'] ?? null;
        $db = App::db();
        try {
            $db->transaction(function () use ($db) {
                $db->insert('us_mstr', [
                    'us_userid' => self::USER, 'us_name' => t('install.demo_user'), 'us_email' => 'demo@example.invalid',
                    'us_password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), // nobody knows it; the user is switched off below
                    'us_role' => 'ADMIN', 'us_active' => 1, 'created_by' => self::USER, 'updated_by' => self::USER,
                ]);
                Auth::actAs(self::USER);
                (new self(self::USER))->run();
                $db->update('us_mstr', ['us_active' => 0], ['us_userid' => self::USER]);
            });
        } finally {
            $before === null ? Auth::forget() : Auth::actAs((string) $before);
        }
    }

    private function run(): void
    {
        $d = fn (int $daysAgo) => date('Y-m-d', strtotime(-$daysAgo . ' days')); // negative = days ahead
        // payment terms of each party (the screens fill these in from the vendor / customer)
        $terms = ['V-STEEL' => '30DAYS', 'V-HW' => '7DAYS', 'V-IMP' => '30DAYS', 'V-LOG' => '7DAYS', 'C-HOME' => '30DAYS', 'C-OFFICE' => '60DAYS', 'C-SHOP' => 'COD'];
        // History starts two months back: open that period, close it again at the end.
        $start = strtotime(date('Y-m-01') . ' -2 months');
        App::db()->update('gl_ctrl', ['gl_curr_year' => (int) date('Y', $start), 'gl_curr_per' => (int) date('n', $start)], ['ctrl_id' => 1]);

        // ---------------------------------------------------------------- master data
        $this->post('/api/cost-centers', ['cc_cc' => 'ADM', 'cc_desc' => 'สำนักงาน', 'cc_active' => true]);
        $this->post('/api/cost-centers', ['cc_cc' => 'PRD', 'cc_desc' => 'ฝ่ายผลิต', 'cc_active' => true]);
        $this->post('/api/banks', ['bk_bank' => 'BANK1', 'bk_desc' => 'บัญชีออมทรัพย์', 'bk_acct' => '10201', 'bk_curr' => 'THB', 'bk_active' => true]);
        foreach (['01' => 'สำนักงานใหญ่', '02' => 'โรงงาน'] as $code => $desc) {
            $this->post('/api/sub-accounts', ['sub_acct' => $code, 'sub_desc' => $desc, 'sub_active' => true]);
        }
        foreach ([['USD', 30, '34.85'], ['USD', 1, '35.10'], ['EUR', 1, '37.90']] as [$curr, $ago, $rate]) {
            $this->post('/api/exchange-rates', ['ex_curr' => $curr, 'ex_date' => $d($ago), 'ex_rate' => $rate]);
        }
        foreach (['P' => 'สินค้าที่ซื้อ', 'M' => 'สินค้าที่ผลิต'] as $code => $desc) {
            $this->post('/api/part-types', ['ptt_type' => $code, 'ptt_desc' => $desc, 'ptt_active' => true]);
        }
        foreach (['STEEL' => 'เฟอร์นิเจอร์เหล็ก', 'RAW' => 'วัตถุดิบ', 'TRADE' => 'สินค้าซื้อมาขายไป'] as $code => $desc) {
            $this->post('/api/part-groups', ['ptg_group' => $code, 'ptg_desc' => $desc, 'ptg_active' => true]);
        }
        // a second warehouse, with its address in parts
        $this->post('/api/sites', ['si_site' => 'WH2', 'si_desc' => 'คลังสินค้าสีลม', 'si_default_loc' => 'S1', 'si_status' => true,
            'si_house_no' => '12', 'si_road' => 'สีลม', 'si_sub_district' => 'สีลม', 'si_district' => 'เขต บางรัก', 'si_province' => 'กรุงเทพมหานคร', 'si_zip' => '10500']);
        $this->post('/api/locations', ['loc_site' => 'WH2', 'loc_loc' => 'S1', 'loc_desc' => 'ชั้นเก็บ 1', 'loc_type' => 'M', 'loc_status' => 'ACTIVE', 'loc_nettable' => true]);

        $part = fn (string $code, string $desc, string $um, string $type, array $more = []) => $this->post('/api/parts', $more + [
            'pt_part' => $code, 'pt_desc1' => $desc, 'pt_um' => $um, 'pt_prod_line' => str_starts_with($code, 'RM-') ? 'RM' : 'FG', 'pt_part_type' => $type, 'pt_cost_method' => 'AVG',
            'pt_lot_ser' => 'L', // every item is kept by lot: the tax invoice, work order or receipt number
        ]);
        $part('RM-STEEL', 'เหล็กแผ่น 1.2 มม.', 'KG', 'P', ['pt_lead_time' => '7']);
        $part('RM-SCREW', 'สกรูเกลียวปล่อย', 'EA', 'P', ['pt_lead_time' => '3', 'pt_pack_qty' => '1000']);
        $part('RM-PAINT', 'สีฝุ่นกันสนิม', 'KG', 'P', ['pt_lead_time' => '5']);
        $part('FG-SHELF', 'ชั้นวางเหล็ก 4 ชั้น', 'EA', 'M', ['pt_price' => '1890', 'pt_lead_time' => '2']);
        $part('FG-TABLE', 'โต๊ะพับเหล็ก', 'EA', 'M', ['pt_price' => '1450', 'pt_lead_time' => '2']);
        $part('TR-BOX', 'กล่องพลาสติกเก็บของ', 'EA', 'P', ['pt_price' => '159', 'pt_lead_time' => '5']);
        $part('FG-SHEET', 'เหล็กแผ่นตัดขาย', 'KG', 'P', ['pt_price' => '68']); // raw steel converted to sell (แปลงเป็น FG)
        // a fixed asset bought through purchasing: the office-equipment line (FA) books the receipt to the asset account
        $part('AS-PC', 'คอมพิวเตอร์สำนักงาน', 'EA', 'P', ['pt_prod_line' => 'OF', 'pt_inventory' => '0']);
        // a lower price from 50 pieces, for every customer; safety stock of the shelf at the main warehouse
        $this->post('/api/price-lists', ['pc_list' => 'ALL', 'pc_part' => 'FG-SHELF', 'pc_um' => 'EA', 'pc_curr' => 'THB', 'pc_start' => $d(60),
            'pc_amt_type' => 'P', 'pc_price' => '1790', 'pc_min_qty' => '50']);
        $this->post('/api/item-planning', ['pt_site' => 'MAIN', 'pt_part' => 'FG-SHELF', 'pt_status' => 'ACTIVE', 'pt_safe_stk' => '10', 'pt_rop' => '20', 'pt_roq' => '50']);

        // ---------------------------------------------------------------- roles and users (บทบาทและสิทธิ์ / ผู้ใช้)
        // one role per department, one user in each; their passwords are random (nobody knows them): an admin sets
        // a password on the user page (or php bin/phai password <user>) before signing in as one
        $work = ['view', 'add', 'edit', 'print'];
        $roles = [
            'PURCHASE' => ['ฝ่ายจัดซื้อ', 'buyer', 'สมชาย จัดซื้อ',
                ['vendors', 'purchase-requisitions', 'purchase-orders', 'receipts', 'landed-costs', 'purchase-returns', 'vendor-rating'], ['parts', 'stock']],
            'STORE'    => ['ฝ่ายคลังสินค้า', 'store', 'สมศรี คลังสินค้า',
                ['stock', 'stock-counts', 'receipts', 'locations'], ['parts', 'stock-history', 'stock-card', 'purchase-orders', 'sales-orders']],
            'PRODUCTION' => ['ฝ่ายผลิต', 'planner', 'สมหมาย ผลิต',
                ['work-orders', 'qc', 'mrp', 'boms', 'routings', 'work-centers', 'machines', 'employees', 'purchase-requisitions'], ['parts', 'stock', 'sales-orders']],
            'SALES'    => ['ฝ่ายขาย', 'seller', 'สมใจ ขาย',
                ['customers', 'price-lists', 'quotations', 'sales-orders', 'shipments', 'invoices', 'billings', 'sales-returns', 'deposits'], ['parts', 'stock', 'receivables']],
            'ACCOUNT'  => ['ฝ่ายบัญชี', 'accountant', 'สมพร บัญชี',
                ['payables', 'ap-vouchers', 'vouchers', 'wht', 'deposits', 'ar-invoices', 'cash-receipts', 'journals', 'bank-rec', 'vat-return', 'trial-balance', 'fixed-assets'],
                ['receivables', 'vat-purchases', 'vat-sales', 'wht-report', 'financial-statements', 'purchase-orders', 'sales-orders', 'receipts', 'shipments']],
        ];
        foreach ($roles as $code => [$desc, $user, $name, $does, $sees]) {
            $this->post('/api/roles', ['role_code' => $code, 'role_desc' => $desc, 'role_active' => true]);
            $cash = in_array($code, ['ACCOUNT', 'STORE'], true) ? ['petty-cash' => [...$work, 'delete']] : []; // they hold petty cash
            $this->put("/api/roles/$code/perms", ['perms' => array_fill_keys($sees, ['view']) + array_fill_keys($does, $work) + $cash]);
            $this->post('/api/users', ['us_userid' => $user, 'us_name' => $name, 'us_email' => "$user@example.invalid", 'us_role' => $code,
                'us_active' => true, 'password' => bin2hex(random_bytes(16))]);
        }

        // an hour on the line: labour 120 + machines (depreciation, power) 60 + other overhead (rent, supervision) 40
        $this->post('/api/work-centers', ['wc_wkctr' => 'ASSY', 'wc_desc' => 'สายประกอบ', 'wc_lbr_rate' => '120', 'wc_mch_rate' => '60', 'wc_bdn_rate' => '40', 'wc_active' => true]);
        $this->post('/api/employees', ['emp_nbr' => 'E01', 'emp_name' => 'พนักงาน ตัวอย่างหนึ่ง', 'emp_wkctr' => 'ASSY', 'emp_rate' => '110', 'emp_active' => true]);
        $this->post('/api/employees', ['emp_nbr' => 'E03', 'emp_name' => 'สมหมาย ผลิต', 'emp_dept' => 'วางแผนผลิต', 'emp_userid' => 'planner', 'emp_active' => true]);
        $this->post('/api/employees', ['emp_nbr' => 'E02', 'emp_name' => 'พนักงาน ตัวอย่างสอง', 'emp_wkctr' => 'ASSY', 'emp_active' => true]);
        $this->put('/api/boms/FG-SHELF', ['lines' => [
            ['ps_comp' => 'RM-STEEL', 'ps_qty_per' => '6'], ['ps_comp' => 'RM-SCREW', 'ps_qty_per' => '24'], ['ps_comp' => 'RM-PAINT', 'ps_qty_per' => '0.4'],
        ]]);
        $this->put('/api/boms/FG-TABLE', ['lines' => [
            ['ps_comp' => 'RM-STEEL', 'ps_qty_per' => '4'], ['ps_comp' => 'RM-SCREW', 'ps_qty_per' => '12'], ['ps_comp' => 'RM-PAINT', 'ps_qty_per' => '0.3'],
        ]]);
        foreach (['FG-SHELF', 'FG-TABLE'] as $fg) {
            $this->put("/api/routings/$fg", ['lines' => [
                // set-up once per order: dies on the press brake, then the paint line made ready
                ['ro_op' => '10', 'ro_wkctr' => 'ASSY', 'ro_desc' => 'ตัดและพับ', 'ro_setup_time' => '1', 'ro_run_time' => '0.3'],
                ['ro_op' => '20', 'ro_wkctr' => 'ASSY', 'ro_desc' => 'ประกอบและพ่นสี', 'ro_setup_time' => '0.5', 'ro_run_time' => '0.5'],
            ]]);
        }

        // made-up tax ids (prefix 0999999, valid check digit) and addresses in the parts the tax forms ask for
        $address = fn (string $p, array $a) => [
            "{$p}_house_no" => $a[0], "{$p}_soi" => $a[1], "{$p}_road" => $a[2], "{$p}_sub_district" => $a[3], "{$p}_district" => $a[4],
            "{$p}_province" => 'กรุงเทพมหานคร', "{$p}_zip" => $a[5],
        ];
        $vendor = fn (string $code, string $name, string $terms, string $tax, array $a, array $more = []) => $this->post('/api/vendors', $more + [
            'vd_code' => $code, 'vd_name' => $name, 'vd_status' => 'ACTIVE', 'vd_terms' => $terms, 'vd_curr' => 'THB', 'vd_tax_code' => 'VAT7',
            'vd_tax_id_num' => $tax,
        ] + $address('vd', $a));
        $vendor('V-STEEL', 'บริษัท เหล็กตัวอย่าง จำกัด', '30DAYS', '0999999000012', ['88/8', '', 'พระรามที่ 3', 'ช่องนนทรี', 'เขต ยานนาวา', '10120']);
        $vendor('V-HW', 'ร้านวัสดุตัวอย่าง', '7DAYS', '0999999000021', ['15', 'ลาดพร้าว 71', 'ลาดพร้าว', 'จรเข้บัว', 'เขต ลาดพร้าว', '10230']);
        $vendor('V-LOG', 'บริษัท ขนส่งตัวอย่าง จำกัด', '7DAYS', '0999999000039', ['200', '', 'สุนทรโกษา', 'คลองเตย', 'เขต คลองเตย', '10110'],
            ['vd_wht_code' => 'WHT1']); // its invoice lines start on transport 1%
        $vendor('V-IMP', 'บริษัท นำเข้าตัวอย่าง จำกัด', '30DAYS', '0999999000071', ['77', '', 'บางนา-ตราด', 'บางนา', 'เขต บางนา', '10260'],
            ['vd_curr' => 'USD']); // the one import (in dollars): its receipt carries the landed cost
        $customer = fn (string $code, string $name, string $terms, string $limit, string $tax, array $a) => $this->post('/api/customers', [
            'cm_code' => $code, 'cm_name' => $name, 'cm_status' => 'ACTIVE', 'cm_terms' => $terms, 'cm_curr' => 'THB', 'cm_tax_code' => 'VAT7',
            'cm_cr_limit' => $limit, 'cm_tax_id_num' => $tax,
        ] + $address('cm', $a));
        $customer('C-HOME', 'บริษัท บ้านสวยตัวอย่าง จำกัด', '30DAYS', '200000', '0999999000047', ['9/9', 'สีลม 3', 'สีลม', 'สีลม', 'เขต บางรัก', '10500']);
        $customer('C-OFFICE', 'บริษัท ออฟฟิศดีตัวอย่าง จำกัด', '60DAYS', '300000', '0999999000055', ['555', '', 'พหลโยธิน', 'ลาดยาว', 'เขต จตุจักร', '10900']);
        $customer('C-SHOP', 'ร้านค้าปลีกตัวอย่าง', 'COD', '50000', '0999999000063', ['12', 'สีลม 9', 'สีลม', 'สีลม', 'เขต บางรัก', '10500']);

        // ---------------------------------------------------------------- the two months of work
        // Every step is put on its day, then all of them run day by day (same day: in the order written), so each
        // month's documents are numbered from 1 in date order, as when people key them in as they happen.
        // Numbers a later step needs are kept in $this->n.
        $steps = [];
        $at = function (int $daysAgo, callable $step) use (&$steps) {
            $steps[] = [$daysAgo, count($steps), $step];
        };

        // machines of the assembly line are fixed assets that make the FG product line
        $machines = [['BEND', 'เครื่องพับเหล็ก', '350000', 'BD-2024-001'], ['WELD', 'เครื่องเชื่อมไฟฟ้า', '85000', 'WD-2024-017']];
        $at(55, function () use ($machines, $d) {
            foreach ($machines as [$code, $desc, $cost, $serial]) {
                $this->post('/api/fixed-assets', [
                    'fa_code' => 'FA-' . $code, 'fa_desc' => $desc, 'fa_category' => 'MC', 'fa_serial' => $serial, 'fa_site' => 'MAIN',
                    'fa_cc' => 'PRD', 'fa_prod_line' => 'FG', 'fa_acquired' => $d(55), 'fa_start' => $d(55), 'fa_cost' => $cost,
                ]);
                $this->post("/api/fixed-assets/FA-$code/activate", ['mode' => 'POST', 'credit_acct' => '30101']); // paid in as capital
            }
            // after all assets exist: the asset dropdown is read once per run
            foreach ($machines as [$code, $desc, , $serial]) {
                $this->post('/api/machines', [
                    'mch_code' => $code, 'mch_desc' => $desc, 'mch_wkctr' => 'ASSY', 'mch_serial' => $serial, 'mch_asset' => 'FA-' . $code, 'mch_active' => true,
                ]);
            }
        });

        // ---------------------------------------------------------------- opening balances
        // the owners pay their capital into the bank (the machines came in as capital in kind above); the stock that was
        // already on the shelves comes in at cost against ยอดยกมา (reason OPEN → retained earnings)
        $at(58, fn () => $this->post('/api/journals', ['date' => $d(58), 'desc' => 'ยอดยกมา: ทุนชำระแล้วเข้าบัญชีธนาคาร', 'lines' => [
            ['acct' => '10201', 'dr' => '500000'], ['acct' => '30101', 'cr' => '500000'],
        ]]));
        $at(58, fn () => $this->post('/api/stock/moves', ['kind' => 'receipt', 'date' => $d(58), 'site' => 'MAIN', 'reason' => 'OPEN', 'lines' => [
            ['part' => 'RM-STEEL', 'loc' => 'STOCK', 'qty' => '500', 'cost' => '45'],
            ['part' => 'RM-SCREW', 'loc' => 'STOCK', 'qty' => '5000', 'cost' => '0.8'],
            ['part' => 'RM-PAINT', 'loc' => 'STOCK', 'qty' => '40', 'cost' => '180'],
            ['part' => 'TR-BOX', 'loc' => 'STOCK', 'qty' => '100', 'cost' => '95'],
        ]]));
        // part of the boxes go to the Silom warehouse (โอนย้าย ISS-TR / RCT-TR at their cost)
        $at(50, function () use ($d) {
            $box = (int) App::db()->value("SELECT ld_id FROM ld_det WHERE ld_part = 'TR-BOX' AND ld_site = 'MAIN' AND ld_loc = 'STOCK' AND ld_qty_oh > 0 ORDER BY ld_id LIMIT 1");
            $this->post("/api/stock/view/$box/transfer", ['date' => $d(50), 'qty' => '20', 'to_site' => 'WH2', 'to_loc' => 'S1', 'remark' => 'ส่งไปขายที่คลังสีลม']);
        });

        // ---------------------------------------------------------------- purchasing
        $po = fn (string $vd, string $date, array $lines, array $more = []) => $this->post('/api/purchase-orders', $more + [
            'po_vd_code' => $vd, 'po_cr_terms' => $terms[$vd], 'po_date' => $date, 'po_ship_to' => 'MAIN', 'po_curr' => 'THB', 'po_ex_rate' => '1', 'po_tax_code' => 'VAT7',
            'lines' => array_map(fn ($l) => ['pod_part' => $l[0], 'pod_qty_ord' => $l[1], 'pod_price' => $l[2]], $lines),
        ])['nbr'];
        $receive = fn (string $po, string $date, string $inv, array $qty) => $this->post('/api/receipts', [
            'po' => $po, 'rc_date' => $date, 'rc_site' => 'MAIN', 'rc_inv_nbr' => $inv,
            'lines' => array_map(fn ($q) => ['qty' => $q, 'loc' => 'STOCK'], $qty),
        ])['nbr'];

        $at(50, fn () => $this->n['po1'] = $po('V-STEEL', $d(50), [['RM-STEEL', '1000', '46']]));
        $at(45, fn () => $this->n['rc1'] = $receive($this->n['po1'], $d(45), 'ST-6612', ['1' => '1000']));
        $at(44, fn () => $this->n['ap1'] = $this->post('/api/payables', ['vendor' => 'V-STEEL', 'ap_date' => $d(44), 'ap_inv_nbr' => 'ST-6612', 'receipts' => [$this->n['rc1']]])['nbr']);

        $at(40, fn () => $this->n['po2'] = $po('V-HW', $d(40), [['RM-SCREW', '10000', '0.75'], ['RM-PAINT', '50', '175']]));
        $at(36, fn () => $this->n['rc2'] = $receive($this->n['po2'], $d(36), 'HW-0381', ['1' => '10000', '2' => '30']));
        $at(35, fn () => $this->n['ap2'] = $this->post('/api/payables', ['vendor' => 'V-HW', 'ap_date' => $d(35), 'ap_inv_nbr' => 'HW-0381', 'receipts' => [$this->n['rc2']]])['nbr']);

        // the one import (in dollars): its receipt carries the landed cost
        $at(12, fn () => $this->n['poImp'] = $po('V-IMP', $d(12), [['RM-PAINT', '20', '5']], ['po_curr' => 'USD', 'po_ex_rate' => '34.85']));
        $at(8, fn () => $this->n['rcImp'] = $receive($this->n['poImp'], $d(8), 'IMP-2026-018', ['1' => '20']));
        $at(8, fn () => $this->post('/api/payables', ['vendor' => 'V-HW', 'ap_date' => $d(8), 'ap_inv_nbr' => 'HW-0402', 'tax_code' => 'VAT7',
            'lines' => [['acct' => '50401', 'cc' => 'ADM', 'desc' => 'วัสดุสำนักงาน', 'amt' => '2400']]])); // waits for a voucher
        $at(4, fn () => $this->n['po3'] = $po('V-STEEL', $d(4), [['RM-STEEL', '800', '46.5']])); // part of it comes at the end
        // a scheduled order (ใบสั่งซื้อแบบกำหนดส่ง, Supplier Schedule): screws at one price for four months, 5,000 a month;
        // its first release (set by us) is firm for the first delivery, the rest a forecast; the first came in. The later
        // deliveries are more than MRP needs: its action messages say cancel, and รอบใหม่จาก MRP would cut them
        $at(20, function () use ($po, $d) {
            $this->n['poSch'] = $po('V-HW', $d(20), [['RM-SCREW', '20000', '0.72']], ['po_sched' => '1', 'po_remarks' => 'สัญญาราคา 4 เดือน ส่งเดือนละ 5,000 ตัว']);
            $this->put("/api/schedules/po/{$this->n['poSch']}", ['date' => $d(20), 'firm_date' => $d(6), 'source' => 'MANUAL', 'lines' => ['1' => ['rows' => [
                ['date' => $d(10), 'qty' => '5000'], ['date' => $d(-20), 'qty' => '5000'], ['date' => $d(-50), 'qty' => '5000'], ['date' => $d(-80), 'qty' => '5000'],
            ]]]]);
        });
        $at(9, fn () => $receive($this->n['poSch'], $d(9), 'HW-0410', ['1' => '5000']));

        // a requisition from production, ordered from it in one click the next day (ใบขอซื้อ → ใบสั่งซื้อ)
        $at(16, fn () => $this->n['prPaint'] = $this->post('/api/purchase-requisitions', ['pr_date' => $d(16), 'pr_req_by' => 'พนักงาน ตัวอย่างสอง', 'pr_dept' => 'ผลิต', 'lines' => [
            ['prd_part' => 'RM-PAINT', 'prd_qty_req' => '25', 'prd_req_date' => $d(9), 'prd_est_cost' => '175', 'prd_pref_vd' => 'V-HW'],
        ]])['nbr']);
        $at(15, fn () => $this->post('/api/purchase-orders/from-pr', ['pr' => $this->n['prPaint'], 'vendor' => 'V-HW', 'date' => $d(15)]));

        // transport (1%) and a loading service (3%) on one invoice: the payment withholds both on one 50 ทวิ
        $at(30, fn () => $this->n['ap3'] = $this->post('/api/payables', ['vendor' => 'V-LOG', 'ap_date' => $d(30), 'ap_inv_nbr' => 'LG-1177', 'tax_code' => 'VAT7',
            'lines' => [['acct' => '50103', 'cc' => 'PRD', 'desc' => 'ค่าขนส่งวัตถุดิบ', 'amt' => '3500', 'wht' => 'WHT1'],
                        ['acct' => '50503', 'cc' => 'PRD', 'desc' => 'ค่าบริการขนถ่ายสินค้า', 'amt' => '1500', 'wht' => 'WHT3']]])['nbr']);

        // payables go on a voucher (VO), the payment (PV) pays the voucher
        $at(26, fn () => $this->n['vo3'] = $this->post('/api/ap-vouchers', ['vendor' => 'V-LOG', 'apv_date' => $d(26), 'apv_pay_date' => $d(25), 'refs' => [$this->n['ap3']]])['nbr']);
        $at(25, fn () => $this->post('/api/vouchers', ['vendor' => 'V-LOG', 'vo_date' => $d(25), 'vo_bank' => 'BANK1', 'vo_wht_form' => 'PND53',
            'wht' => [['code' => 'WHT1', 'base' => '3500'], ['code' => 'WHT3', 'base' => '1500']], 'lines' => [$this->n['vo3'] => ['amt' => '5350']]]));
        $at(21, fn () => $this->n['vo1'] = $this->post('/api/ap-vouchers', ['vendor' => 'V-STEEL', 'apv_date' => $d(21), 'apv_pay_date' => $d(20), 'refs' => [$this->n['ap1']]])['nbr']);
        $at(20, fn () => $this->post('/api/vouchers', ['vendor' => 'V-STEEL', 'vo_date' => $d(20), 'vo_bank' => 'BANK1',
            'lines' => [$this->n['vo1'] => ['amt' => $this->balance('vouchers', 'vendor', 'V-STEEL', $this->n['vo1'])]]]));
        $at(3, fn () => $this->post('/api/ap-vouchers', ['vendor' => 'V-HW', 'apv_date' => $d(3), 'apv_pay_date' => $d(-4), 'refs' => [$this->n['ap2']]])); // waits for payment

        // ---------------------------------------------------------------- production
        // released on its day, materials issued the next day (one issue slip), finished goods received — the routing's
        // standard hours come with the receipt (nobody types hours)
        $woCount = 0;
        $wo = function (string $part, int $qty, int $rel, ?int $recv) use ($at, $d, &$woCount) {
            $key = 'wo' . ++$woCount;
            $at($rel, function () use ($key, $part, $qty, $rel, $d) {
                $this->n[$key] = $this->post('/api/work-orders', ['wo_part' => $part, 'wo_qty_ord' => (string) $qty, 'wo_site' => 'MAIN',
                    'wo_rel_date' => $d($rel), 'wo_due_date' => $d(max(0, $rel - 5))])['nbr'];
                $this->post("/api/work-orders/{$this->n[$key]}/release");
            });
            $at($rel - 1, function () use ($key, $rel, $d) {
                $nbr = $this->n[$key];
                // one issue slip: a part spread over lots takes a row per lot
                $lines = [];
                foreach ($this->get("/api/work-orders/$nbr")['comps'] as $m) {
                    foreach ($this->fromLots($m['wod_part'], (float) $m['wod_qty_req']) as $k => [$lot, $q]) {
                        $lines[$m['wod_part'] . '~' . $k] = ['part' => $m['wod_part'], 'qty' => (string) $q, 'loc' => 'STOCK', 'lot' => $lot];
                    }
                }
                $this->post("/api/work-orders/$nbr/issue", ['date' => $d($rel - 1), 'lines' => $lines]);
            });
            if ($recv !== null) {
                $at($recv, function () use ($key, $qty, $recv, $d) {
                    $this->post("/api/work-orders/{$this->n[$key]}/receive", ['date' => $d($recv), 'qty' => (string) $qty, 'loc' => 'STOCK', 'inspector' => 'พนักงาน ตัวอย่างสอง']);
                    $this->post("/api/work-orders/{$this->n[$key]}/close");
                });
            }
        };
        $wo('FG-SHELF', 50, 42, 38);
        $wo('FG-TABLE', 30, 30, 26);
        $wo('FG-SHELF', 40, 6, null); // in progress
        $at(26, fn () => $this->post('/api/qc', ['qc_part' => 'FG-TABLE', 'qc_date' => $d(26), 'qc_result' => 'REWORK', 'qc_qty_inspect' => '5',
            'qc_inspector' => 'พนักงาน ตัวอย่างสอง', 'qc_remarks' => 'รอยสีไม่เรียบ ส่งพ่นใหม่']));

        // ---------------------------------------------------------------- sales
        $ship = function (string $so, string $date, array $qty) {
            $parts = array_column($this->get("/api/sales-orders/$so")['lines'], 'sod_part', 'sod_line');
            $lines = [];
            foreach ($qty as $line => $q) {
                $lines[$line] = ['qty' => $q, 'loc' => 'STOCK', 'lot' => $this->fromLots($parts[$line], (float) $q, true)[0][0]];
            }
            return $this->post('/api/shipments', ['so' => $so, 'sh_date' => $date, 'invoice' => true, 'lines' => $lines])['inv'];
        };
        $so = fn (string $cm, string $date, array $lines, array $more = []) => $this->post('/api/sales-orders', $more + [
            'so_cm_code' => $cm, 'so_cr_terms' => $terms[$cm], 'so_date' => $date, 'so_site' => 'MAIN', 'so_curr' => 'THB', 'so_ex_rate' => '1', 'so_tax_code' => 'VAT7',
            'lines' => array_map(fn ($l) => ['sod_part' => $l[0], 'sod_qty_ord' => $l[1], 'sod_price' => $l[2]], $lines),
        ])['nbr'];

        $at(33, fn () => $this->n['so1'] = $so('C-HOME', $d(33), [['FG-SHELF', '20', '1890'], ['TR-BOX', '30', '159']]));
        $at(31, fn () => $this->n['inv1'] = $ship($this->n['so1'], $d(31), ['1' => '20', '2' => '30']));
        $at(30, fn () => $this->post('/api/billings', ['customer' => 'C-HOME', 'bl_date' => $d(30), 'invoices' => [$this->n['inv1']]]));
        // the delivery went on to a second address: the extra freight on a debit note (ใบเพิ่มหนี้) of that invoice
        $at(29, fn () => $this->post('/api/ar-invoices', ['ih_type' => 'DN', 'ih_cm_code' => 'C-HOME', 'ih_inv_date' => $d(29), 'ih_curr' => 'THB', 'ih_ex_rate' => '1',
            'ih_tax_code' => 'VAT7', 'ih_ref_inv' => $this->n['inv1'], 'ih_reason' => 'DBNG99', 'ih_cr_terms' => $terms['C-HOME'], 'ih_remarks' => 'ค่าขนส่งเพิ่ม ส่งของอีกสาขาของลูกค้า',
            'lines' => ['1' => ['ihd_part' => 'FREIGHT', 'ihd_desc' => 'ค่าขนส่งเพิ่มเติม', 'ihd_um' => 'EA', 'ihd_qty_inv' => '1', 'ihd_price' => '600']]]));
        $at(10, fn () => $this->post('/api/cash-receipts', ['customer' => 'C-HOME', 'cr_date' => $d(10), 'cr_bank' => 'BANK1',
            'lines' => [$this->n['inv1'] => ['amt' => $this->balance('cash-receipts', 'customer', 'C-HOME', $this->n['inv1'])]]]));

        $at(27, fn () => $this->n['so2'] = $so('C-OFFICE', $d(27), [['FG-TABLE', '20', '1450'], ['FG-SHELF', '10', '1890']]));
        $at(24, fn () => $this->n['inv2'] = $ship($this->n['so2'], $d(24), ['1' => '20', '2' => '10']));
        $at(5, fn () => $this->post('/api/cash-receipts', ['customer' => 'C-OFFICE', 'cr_date' => $d(5), 'cr_bank' => 'BANK1', 'lines' => [$this->n['inv2'] => ['amt' => '20000']]]));

        // a service (installing the shelves): VAT falls due when paid — half paid so far, that receipt is the tax invoice
        $at(20, fn () => $this->n['svc'] = $this->post('/api/ar-invoices', ['ih_type' => 'IN', 'ih_cm_code' => 'C-HOME', 'ih_inv_date' => $d(20), 'ih_curr' => 'THB', 'ih_ex_rate' => '1',
            'ih_tax_code' => 'VAT7', 'ih_vat_on_rcpt' => '1', 'ih_cr_terms' => $terms['C-HOME'],
            'lines' => ['1' => ['ihd_part' => 'INSTALL', 'ihd_desc' => 'ค่าติดตั้งชั้นวางเหล็ก', 'ihd_um' => 'EA', 'ihd_qty_inv' => '1', 'ihd_price' => '8000']]])['nbr']);
        $at(9, fn () => $this->post('/api/cash-receipts', ['customer' => 'C-HOME', 'cr_date' => $d(9), 'cr_bank' => 'BANK1', 'lines' => [$this->n['svc'] => ['amt' => '4280']]]));

        $at(15, function () use ($so, $d) {
            $this->n['so3'] = $so('C-SHOP', $d(15), [['TR-BOX', '40', '159']]);
            // the shop paid a deposit with its order (ใบรับเงินมัดจำ / ใบกำกับภาษี): the tax invoice of the delivery deducts it
            // paid in cash at the office: the accountant takes it into her petty cash (bank CASH)
            Auth::actAs('accountant');
            try {
                $this->post('/api/deposits', ['so' => $this->n['so3'], 'dp_date' => $d(15), 'dp_bank' => 'CASH', 'amount' => '2000', 'dp_remarks' => 'มัดจำ 30% รับเป็นเงินสด']);
            } finally {
                Auth::actAs(self::USER);
            }
        });
        $at(14, fn () => $ship($this->n['so3'], $d(14), ['1' => '25']));

        // quotations: one won two days later (becomes an order), one lost to another seller, one still waiting
        $quote = fn (string $cm, int $date, array $lines) => $this->post('/api/quotations', [
            'qt_cm_code' => $cm, 'qt_cr_terms' => $terms[$cm], 'qt_date' => $d($date), 'qt_expire' => $d($date - 30), 'qt_curr' => 'THB', 'qt_ex_rate' => '1', 'qt_tax_code' => 'VAT7',
            'lines' => array_map(fn ($l) => ['qtd_part' => $l[0], 'qtd_qty' => $l[1], 'qtd_price' => $l[2]], $lines),
        ])['nbr'];
        $at(18, fn () => $this->n['qtLost'] = $quote('C-HOME', 18, [['FG-TABLE', '10', '1520']]));
        $at(10, fn () => $this->post("/api/quotations/{$this->n['qtLost']}/lost")); // the customer bought elsewhere
        $at(5, fn () => $this->n['qtWon'] = $quote('C-HOME', 5, [['FG-SHELF', '30', '1850']]));
        $at(3, fn () => $this->post('/api/sales-orders/from-qt', ['qt' => $this->n['qtWon'], 'date' => $d(3)]));
        $at(2, fn () => $quote('C-SHOP', 2, [['FG-TABLE', '15', '1490']]));
        // a scheduled order from the office customer (ใบสั่งขายแบบกำหนดส่ง, Customer Schedule): 10 shelves a month for
        // four months, keyed in from the schedule release the customer sent (a large customer would send it by EDI);
        // four days later the customer's next release raises the second month to 15 — the first stays as history
        $at(6, function () use ($so, $d) {
            $this->n['soSch'] = $so('C-OFFICE', $d(6), [['FG-SHELF', '40', '1850']], ['so_sched' => '1', 'so_po_ref' => 'OF-BLANKET-01']);
            $this->put("/api/schedules/so/{$this->n['soSch']}", ['date' => $d(6), 'firm_date' => $d(-5), 'source' => 'CUSTOMER', 'ref' => 'OF-REL-01', 'lines' => ['1' => ['rows' => [
                ['date' => $d(-5), 'qty' => '10'], ['date' => $d(-35), 'qty' => '10'], ['date' => $d(-65), 'qty' => '10'], ['date' => $d(-95), 'qty' => '10'],
            ]]]]);
        });
        $at(2, fn () => $this->put("/api/schedules/so/{$this->n['soSch']}", ['date' => $d(2), 'firm_date' => $d(-5), 'source' => 'CUSTOMER', 'ref' => 'OF-REL-02',
            'rmks' => 'ลูกค้าเพิ่มงวดที่สองเป็น 15', 'lines' => ['1' => ['rows' => [
                ['date' => $d(-5), 'qty' => '10'], ['date' => $d(-35), 'qty' => '15'], ['date' => $d(-65), 'qty' => '10'], ['date' => $d(-95), 'qty' => '10'],
            ]]]]));
        // more tables than there are: MRP plans a work order and the materials for it
        $at(1, fn () => $so('C-OFFICE', $d(1), [['FG-TABLE', '40', '1450']]));

        // ---------------------------------------------------------------- other entries
        $at(28, fn () => $this->post('/api/journals', ['date' => $d(28), 'desc' => 'ค่าเช่าโรงงาน', 'lines' => [
            ['acct' => '50301', 'cc' => 'PRD', 'dr' => '25000'], ['acct' => '10201', 'cr' => '25000'],
        ]]));
        $at(27, fn () => $this->post('/api/journals', ['date' => $d(27), 'desc' => 'ค่าไฟฟ้าและน้ำประปา', 'lines' => [
            ['acct' => '50302', 'cc' => 'PRD', 'dr' => '6200'], ['acct' => '50302', 'cc' => 'ADM', 'dr' => '1300'], ['acct' => '10201', 'cr' => '7500'],
        ]]));
        $at(12, fn () => $this->post('/api/stock/moves', ['kind' => 'issue', 'date' => $d(12), 'site' => 'MAIN', 'reason' => 'DMG', 'lines' => [
            ['part' => 'RM-SCREW', 'loc' => 'STOCK', 'qty' => '50', 'lot' => $this->fromLots('RM-SCREW', 50, true)[0][0]],
        ]]));
        // 60 kg of raw steel cut to sell as sheet (แปลงเป็น FG: ISS-CO / RCT-CO at the steel's cost)
        $at(19, fn () => $this->post('/api/stock/moves', ['kind' => 'convert', 'date' => $d(19), 'site' => 'MAIN', 'remark' => 'ตัดเหล็กแผ่นขายตามสั่ง', 'lines' => [
            ['part' => 'RM-STEEL', 'loc' => 'STOCK', 'lot' => $this->fromLots('RM-STEEL', 60, true)[0][0], 'qty' => '60', 'fg_part' => 'FG-SHEET', 'fg_qty' => '60', 'to_loc' => 'STOCK'],
        ]]));
        // the boxes' supplier raised its price: the stock of boxes is revalued (ปรับราคา ISS-ADJ / RCT-ADJ)
        $at(18, fn () => $this->post('/api/stock/moves', ['kind' => 'cost', 'date' => $d(18), 'remark' => 'ราคาทุนใหม่ตามราคาซื้อล่าสุด', 'lines' => [['part' => 'TR-BOX', 'cost' => '98']]]));
        // a requisition still open
        $at(3, fn () => $this->post('/api/purchase-requisitions', ['pr_date' => $d(3), 'pr_req_by' => 'พนักงาน ตัวอย่างหนึ่ง', 'pr_dept' => 'ผลิต', 'lines' => [
            ['prd_part' => 'RM-PAINT', 'prd_qty_req' => '40', 'prd_req_date' => $d(-7), 'prd_est_cost' => '175', 'prd_pref_vd' => 'V-HW'],
        ]]));
        // half of the last steel order arrived: waiting for the vendor's invoice
        $at(2, fn () => $this->n['rc3'] = $receive($this->n['po3'], $d(2), 'ST-6690', ['1' => '500']));
        // the import's costs (ค่าใช้จ่ายนำเข้า): air freight on its Air Waybill and the shipping agent's customs clearance,
        // spread over the imported paint; then the agent's invoice clears both from GRNI (still to be paid)
        $at(7, function () {
            $this->put("/api/landed-costs/{$this->n['rcImp']}", ['lines' => [
                '1' => ['type' => 'FREIGHT', 'ref' => 'AWB 217-1234 5675', 'vendor' => 'V-LOG', 'amount' => '1200'],
                '2' => ['type' => 'CLEAR', 'ref' => 'A0011-69-00123', 'vendor' => 'V-LOG', 'amount' => '800'],
            ]]);
            $this->post("/api/landed-costs/{$this->n['rcImp']}/allocate");
        });
        $at(1, fn () => $this->post('/api/payables', ['vendor' => 'V-LOG', 'ap_date' => $d(1), 'ap_inv_nbr' => 'LG-1215', 'tax_code' => 'VAT7',
            'receipts' => ["{$this->n['rcImp']}~1", "{$this->n['rcImp']}~2"], 'receipt_wht' => ["{$this->n['rcImp']}~1" => 'WHT1', "{$this->n['rcImp']}~2" => 'WHT1']]));
        // one shelf came back damaged: back into stock (ใบรับคืนสินค้า), then the credit note made from it
        $at(3, function () use ($d) {
            $sh2 = $this->get("/api/invoices/{$this->n['inv2']}")['head']['ih_sh_nbr'];
            $shelf = array_column($this->get("/api/sales-returns/shipment/$sh2")['lines'], 'shd_line', 'shd_part')['FG-SHELF'];
            $sr = $this->post('/api/sales-returns', ['sh' => $sh2, 'sr_date' => $d(3), 'sr_reason' => 'ชั้นวางชำรุดระหว่างขนส่ง 1 ชุด', 'lines' => [$shelf => '1']])['nbr'];
            $this->post('/api/ar-invoices', ['ih_type' => 'CM', 'ih_cm_code' => 'C-OFFICE', 'ih_inv_date' => $d(3), 'ih_curr' => 'THB', 'ih_ex_rate' => '1',
                'ih_tax_code' => 'VAT7', 'ih_ref_inv' => $this->n['inv2'], 'ih_reason' => 'CDNG02', 'ih_remarks' => 'ชั้นวางชำรุด 1 ชุด', 'return' => $sr,
                'lines' => ['1' => ['ihd_part' => 'FG-SHELF', 'ihd_desc' => 'ชั้นวางเหล็ก 4 ชั้น (ชำรุด)', 'ihd_um' => 'EA', 'ihd_qty_inv' => '-1', 'ihd_price' => '1890']]]);
        });
        // 20 of the steel just received are bent: sent back (ใบส่งคืนสินค้า); the steel invoice came the same day and
        // deducts the return (ตั้งหนี้ หักใบส่งคืน)
        $at(1, function () use ($d) {
            $rt = $this->post('/api/purchase-returns', ['rc' => $this->n['rc3'], 'rt_date' => $d(1), 'rt_reason' => 'เหล็กแผ่นงอ 20 แผ่น', 'lines' => ['1' => '20']])['nbr'];
            $this->post('/api/payables', ['vendor' => 'V-STEEL', 'ap_date' => $d(1), 'ap_inv_nbr' => 'ST-6690', 'receipts' => [$this->n['rc3'], "$rt~RTV"]]);
        });
        // an office computer ordered and received (the vendor's invoice HW-0455: Dr office equipment / Cr GRNI), then put in
        // the asset register from that receipt — it waits to be registered (ทุนลงบัญชีไว้แล้ว: the receipt booked it)
        $at(4, fn () => $this->n['poPc'] = $po('V-HW', $d(4), [['AS-PC', '1', '28000']]));
        $at(3, fn () => $receive($this->n['poPc'], $d(3), 'HW-0455', ['1' => '1']));
        $at(2, fn () => $this->post('/api/fixed-assets', [
            'fa_code' => 'FA-PC01', 'fa_desc' => 'คอมพิวเตอร์สำนักงาน', 'fa_category' => 'OF', 'fa_site' => 'MAIN', 'fa_cc' => 'ADM',
            'fa_source_ref' => 'HW-0455', 'fa_vendor' => 'V-HW', 'fa_custodian' => 'พนักงาน ตัวอย่างหนึ่ง',
            'fa_acquired' => $d(3), 'fa_start' => $d(2), 'fa_cost' => '28000',
        ]));
        $at(0, fn () => $this->post('/api/work-orders', ['wo_part' => 'FG-SHELF', 'wo_qty_ord' => '25', 'wo_site' => 'MAIN', 'wo_rel_date' => $d(-2), 'wo_due_date' => $d(-9)])); // waits to be released

        // ---------------------------------------------------------------- petty cash (เงินสดย่อย)
        // the accountant takes cash from the bank and hands part of it to the store keeper; each pays small bills from
        // their own cash and counts it at every month end (the store keeper is 20 baht short in the first month).
        // Dates by calendar month, so the counts fall after each month however the sample is loaded; this month is
        // still open (not yet counted). Each entry is made as its holder, who alone may work on their cash.
        $ago = fn (string $date) => (int) round((strtotime(date('Y-m-d')) - strtotime($date)) / 86400);
        $as = function (string $user, callable $fn) {
            Auth::actAs($user);
            try {
                return $fn();
            } finally {
                Auth::actAs(self::USER);
            }
        };
        $pc = fn (string $user, array $body) => $as($user, fn () => $this->post('/api/petty-cash', $body)['nbr']);
        $exp = fn (string $user, string $date, string $payee, array $lines, array $more = []) => $pc($user, $more + [
            'type' => 'EX', 'date' => $date, 'payee' => $payee,
            'lines' => array_map(fn ($l) => ['acct' => $l[0], 'cc' => $l[1], 'desc' => $l[2], 'amt' => $l[3], 'wht' => $l[4] ?? '', 'vat' => $l[5] ?? ''], $lines),
        ]);
        // the cash a holder counts: the box's balance at month end, less what went missing, in notes and coins
        $count = function (string $user, string $month, float $short = 0.0, ?string $remarks = null) use ($as) {
            $left = round(\Phai\Modules\Accounting\PettyCash::balance($user, \Phai\Modules\Accounting\PettyCash::monthEnd($month)) - $short, 2);
            $notes = [];
            foreach (\Phai\Modules\Accounting\PettyCash::DENOMINATIONS as $v) {
                $n = (int) floor(round($left / (float) $v, 6));
                if ($n > 0) {
                    $notes[$v] = $n;
                    $left = round($left - $n * (float) $v, 2);
                }
            }
            return $as($user, fn () => $this->post('/api/petty-cash/count', ['month' => $month, 'notes' => $notes, 'remarks' => $remarks]));
        };
        $m2 = date('Y-m', strtotime(date('Y-m-01') . ' -2 months'));
        $m1 = date('Y-m', strtotime(date('Y-m-01') . ' -1 month'));
        $day = fn (string $month, int $n) => date('Y-m-d', strtotime("$month-01 +" . ($n - 1) . ' days'));
        $at($ago($day($m2, 20)), fn () => $pc('accountant', ['type' => 'WD', 'date' => $day($m2, 20), 'bank' => 'BANK1', 'amount' => '5000', 'remarks' => 'ตั้งเงินสดย่อย']));
        $at($ago($day($m2, 21)), fn () => $pc('store', ['type' => 'TF', 'date' => $day($m2, 21), 'from' => 'accountant', 'amount' => '2000', 'remarks' => 'เงินสดย่อยของคลังสินค้า']));
        $at($ago($day($m2, 23)), fn () => $exp('store', $day($m2, 23), 'ปั๊มน้ำมันตัวอย่าง', [['50702', 'ADM', 'ค่าน้ำมันรถส่งของ', '450']], ['rcpt_nbr' => 'F-08231']));
        $at($ago($day($m2, 25)), fn () => $exp('accountant', $day($m2, 25), 'ไปรษณีย์', [['50401', 'ADM', 'ค่าส่งเอกสารลงทะเบียน', '120']], ['rcpt_nbr' => 'EMS-5521']));
        // with a full tax invoice: its VAT is input tax, in the purchase VAT report
        $at($ago($day($m2, 27)), fn () => $exp('store', $day($m2, 27), '', [['50402', 'PRD', 'อุปกรณ์ทำความสะอาดโกดัง', '800', '', 'VAT7']],
            ['vendor' => 'V-HW', 'inv_nbr' => 'HW-C0827', 'inv_date' => $day($m2, 27)]));
        $at($ago($day($m1, 1)), fn () => $count('accountant', $m2));
        $at($ago($day($m1, 1)), fn () => $count('store', $m2, 20, 'เงินทอนค่าน้ำมันขาด'));
        // a cleaning service, withheld 3% on the same certificate series as payments (50 ทวิ, ภ.ง.ด.53)
        $at($ago($day($m1, 6)), fn () => $exp('accountant', $day($m1, 6), '', [['50503', 'ADM', 'ค่าบริการทำความสะอาดสำนักงาน', '1000', 'WHT3']],
            ['vendor' => 'V-LOG', 'wht_form' => 'PND53', 'rcpt_nbr' => 'LG-R0906']));
        $at($ago($day($m1, 12)), fn () => $exp('store', $day($m1, 12), 'ปั๊มน้ำมันตัวอย่าง', [['50702', 'ADM', 'ค่าน้ำมันรถส่งของ', '600']], ['rcpt_nbr' => 'F-09122']));
        $at($ago($day($m1, 15)), fn () => $pc('store', ['type' => 'IN', 'date' => $day($m1, 15), 'payee' => 'ร้านรับซื้อของเก่า', 'acct' => '40901',
            'amount' => '35', 'rcpt_nbr' => 'S-0915', 'remarks' => 'ขายกล่องกระดาษเก่า']));
        $at($ago($day($m1, 18)), fn () => $pc('accountant', ['type' => 'WD', 'date' => $day($m1, 18), 'bank' => 'BANK1', 'amount' => '3000', 'remarks' => 'เบิกเพิ่ม']));
        $at($ago($day($m1, 25)), fn () => $exp('accountant', $day($m1, 25), 'ร้านอาหารตัวอย่าง', [['50602', 'ADM', 'รับรองลูกค้า', '850']], ['rcpt_nbr' => 'R-2509-118']));
        $at($ago(date('Y-m-01')), fn () => $count('accountant', $m1));
        $at($ago(date('Y-m-01')), fn () => $count('store', $m1));
        $at(0, fn () => $exp('store', date('Y-m-d'), 'ทางพิเศษ', [['50702', 'ADM', 'ค่าทางด่วนส่งของ', '60']], ['rcpt_nbr' => 'EXP-7781']));

        // day by day, oldest first
        usort($steps, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
        foreach ($steps as [, , $step]) {
            $step();
        }

        $this->post('/api/mrp/run');
        // the bank statement of last month matched every entry of the bank account up to its end
        $monthEnd = date('Y-m-t', strtotime(date('Y-m-01') . ' -1 month'));
        $open = $this->get('/api/bank-rec/open', ['bank' => 'BANK1', 'date' => $monthEnd]);
        $this->post('/api/bank-rec', ['bank' => 'BANK1', 'date' => $monthEnd,
            'lines' => array_column($open['lines'], 'gl_trnbr')]);

        // close the two finished months, each after its depreciation (a closed month can no longer take it)
        foreach ([2, 1] as $back) {
            $month = date('Y-m', strtotime(date('Y-m-01') . " -$back month"));
            $due = array_column(array_filter($this->get('/api/fixed-assets/depreciation-preview', ['month' => $month])['rows'] ?? [],
                fn ($a) => !empty($a['eligible'])), 'fa_code');
            if ($due) {
                $this->post('/api/fixed-assets/depreciation-batch', ['month' => $month, 'codes' => $due]);
            }
            $this->post('/api/vat-return', ['month' => $month]); // ภ.พ.30: closes output and input VAT of the month
            $this->post('/api/trial-balance/close');
        }
        // last: a count of the main warehouse, still being counted
        // the month-end count of the warehouse (the stock is frozen from the tags until it is posted): every tag keyed in
        // from what the counter wrote, the one that was short counted again by someone else and found one short still
        $pi = $this->post('/api/stock-counts', ['site' => 'MAIN', 'date' => $d(0), 'desc' => 'ตรวจนับสิ้นเดือน (ทั้งคลัง)'])['pi_batch'];
        $tags = $this->get("/api/stock-counts/$pi")['tags'];
        $counts = ['counter' => 'พนักงาน ตัวอย่างสอง'];
        foreach ($tags as $i => $t) {
            $counts['count_' . $t['pit_tag']] = (string) ((float) $t['pit_sys_qty'] - ($i === 0 ? 2 : 0)); // the first is two short
        }
        $this->put("/api/stock-counts/$pi/counts", $counts);
        $this->post("/api/stock-counts/$pi/tags/{$tags[0]['pit_tag']}/recount", []);
        $this->put("/api/stock-counts/$pi/counts", ['counter' => 'พนักงาน ตัวอย่างหนึ่ง', 'count_' . $tags[0]['pit_tag'] => (string) ((float) $tags[0]['pit_sys_qty'] - 1)]);
        $this->post("/api/stock-counts/$pi/post", []);
    }

    /**
     * Lots of MAIN / STOCK to take a quantity from, in the order the stock lookup lists them: [[lot, qty], ...].
     * $one: a single lot holding all of it.
     */
    private function fromLots(string $part, float $qty, bool $one = false): array
    {
        $out = [];
        foreach ($this->get('/api/stock/onhand', ['part' => $part, 'site' => 'MAIN']) as $l) {
            $have = (float) $l['qty'];
            if ($l['loc'] !== 'STOCK' || $have <= 0 || ($one && $have < $qty)) continue;
            $take = min($have, $qty);
            $out[] = [$l['lot'], $take];
            $qty = round($qty - $take, 5);
            if ($qty <= 0) return $out;
        }
        throw new \RuntimeException("Demo: not enough $part in stock");
    }

    /** Balance left on one open item (receivable or payable) as a string. */
    private function balance(string $api, string $param, string $party, string $doc): string
    {
        foreach ($this->get("/api/$api/open", [$param => $party]) as $row) {
            if ($row['doc'] === $doc) {
                return (string) $row['balance'];
            }
        }
        throw new \RuntimeException("Demo: $doc is not open");
    }

    private function get(string $path, array $query = []): array
    {
        return $this->call('GET', $path, $query, []);
    }

    private function post(string $path, array $body = []): array
    {
        return $this->call('POST', $path, [], $body) ?? [];
    }

    private function put(string $path, array $body): array
    {
        return $this->call('PUT', $path, [], $body) ?? [];
    }

    private function call(string $method, string $path, array $query, array $body): ?array
    {
        try {
            $response = $this->router->dispatch(new Request($method, $path, '', $query, $body, ['accept' => 'application/json'], '127.0.0.1'));
        } catch (HttpException $e) {
            throw new \RuntimeException("Demo: $method $path -> {$e->status} {$e->getMessage()} " . json_encode($e->fields, JSON_UNESCAPED_UNICODE));
        }
        return json_decode($response->body, true)['data'] ?? null;
    }
}
