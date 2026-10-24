-- PhaiERP seed: currencies, UoM, tax, payment terms, chart of accounts, default product line
SET NAMES utf8mb4;

INSERT INTO `cu_mstr` (`cu_curr`, `cu_desc`, `cu_symbol`, `cu_icon`, `cu_active`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES ('CNY','Chinese Yuan','¥','fas fa-yen-sign',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('EUR','Euro','€','fas fa-euro-sign',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('JPY','Japanese Yen','¥','fas fa-yen-sign',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('THB','Thai Baht','฿','fas fa-baht-sign',1,'system','2026-03-04 05:14:01',NULL,'2026-03-04 05:14:01'),
('USD','US Dollar','$','fas fa-dollar-sign',1,'system','2026-03-04 05:14:01',NULL,'2026-03-04 05:14:01');

INSERT INTO `um_mstr` (`um_um`, `um_desc`, `um_active`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES ('BOX','Box',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('CM','Centimeter',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('EA','Each',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('G','Gram',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('KG','Kilogram',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('LITER','Liter',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('M','Meter',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('PC','Piece',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('ROLL','Roll',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('SET','Set',1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13');

INSERT INTO `tx_mstr` (`tx_tax_code`, `tx_desc`, `tx_tax_pct`, `tx_tax_acct`, `tx_tax_sub`, `tx_active`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES ('NON','Non VAT',0.0000,NULL,NULL,1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('VAT0','VAT 0%',0.0000,NULL,NULL,1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('VAT7','VAT 7%',7.0000,NULL,NULL,1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13');

INSERT INTO `ct_mstr` (`ct_code`, `ct_desc`, `ct_net_days`, `ct_disc_days`, `ct_disc_pct`, `ct_active`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES ('30DAYS','Credit 30 Days',30,0,0.0000,1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('60DAYS','Credit 60 Days',60,0,0.0000,1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('7DAYS','Credit 7 Days',7,0,0.0000,1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('90DAYS','Credit 90 Days',90,0,0.0000,1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13'),
('COD','Cash on Delivery',0,0,0.0000,1,NULL,'2026-03-04 06:19:13',NULL,'2026-03-04 06:19:13');

INSERT INTO `ac_mstr` (`ac_acct`, `ac_desc`, `ac_desc_th`, `ac_type`, `ac_active`, `ac_is_control`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES ('10101','Cash on Hand','เงินสดในมือ','A',1,0,'system','2026-03-10 05:13:41','system','2026-03-10 05:13:41'),
('10102','Petty Cash','เงินสดย่อย','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('10201','Bank: Savings Account','เงินฝากออมทรัพย์','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('10202','Bank: Current Account','เงินฝากกระแสรายวัน','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('11101','Trade Accounts Receivable','ลูกหนี้การค้า','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('11102','Notes Receivable','ตั๋วเงินรับ','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('11109','Allowance for Doubtful Accounts','ค่าเผื่อหนี้สงสัยจะสูญ','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('11201','Other Receivables','ลูกหนี้อื่น','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('12101','Finished Goods','สินค้าสำเร็จรูป','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('12102','Work in Process','งานระหว่างทำ','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('12103','Raw Materials','วัตถุดิบ','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('13104','VAT Credit Carried Forward','ภาษีมูลค่าเพิ่มชำระเกินยกไป','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('13101','Input VAT','ภาษีซื้อ','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('13102','Undue Input VAT','ภาษีซื้อยังไม่ถึงกำหนด','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('13201','Prepaid Expenses','ค่าใช้จ่ายจ่ายล่วงหน้า','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('13202','Advance Payments','เงินจ่ายล่วงหน้า','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('13203','Refundable Deposits','เงินมัดจำและเงินประกัน','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14101','Land','ที่ดิน','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14201','Buildings','อาคาร','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14202','Accumulated Depreciation - Buildings','ค่าเสื่อมราคาสะสม - อาคาร','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14301','Machinery and Equipment','เครื่องจักรและอุปกรณ์','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14302','Accumulated Depreciation - Machinery','ค่าเสื่อมราคาสะสม - เครื่องจักร','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14401','Office Equipment','อุปกรณ์สำนักงาน','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14402','Accumulated Depreciation - Office Equipment','ค่าเสื่อมราคาสะสม - อุปกรณ์สำนักงาน','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14501','Vehicles','ยานพาหนะ','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('14502','Accumulated Depreciation - Vehicles','ค่าเสื่อมราคาสะสม - ยานพาหนะ','A',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('20101','Trade Accounts Payable','เจ้าหนี้การค้า','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('20102','Notes Payable','ตั๋วเงินจ่าย','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('20201','Other Payables','เจ้าหนี้อื่น','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('20202','Accrued Expenses','ค่าใช้จ่ายค้างจ่าย','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('20203','Accrued Salaries','เงินเดือนค้างจ่าย','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('21101','Output VAT','ภาษีขาย','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('21102','Undue Output VAT','ภาษีขายยังไม่ถึงกำหนด','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('21103','WHT Payable','ภาษีหัก ณ ที่จ่ายค้างจ่าย','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('21105','VAT Payable','ภาษีมูลค่าเพิ่มค้างจ่าย','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('21201','Customer Deposits','เงินมัดจำรับจากลูกค้า','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('21104','WHT 5% Payable','ภาษีหัก ณ ที่จ่าย 5% ค้างจ่าย','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('21106','Social Security Fund Payable','เงินสมทบประกันสังคมค้างจ่าย','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('22101','Short-term Loans','เงินกู้ยืมระยะสั้น','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('23101','Long-term Loans','เงินกู้ยืมระยะยาว','L',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('30101','Authorized Share Capital','ทุนเรือนหุ้น','E',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('30201','Legal Reserve','สำรองตามกฎหมาย','E',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('30301','Retained Earnings','กำไรสะสมยังไม่ได้จัดสรร','E',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('30901','Net Profit/Loss for the period','กำไร (ขาดทุน) สุทธิสำหรับงวด','E',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('40101','Sales Income','รายได้จากการขายสินค้า','R',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('40102','Service Income','รายได้จากการให้บริการ','R',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('40103','Sales Returns','รับคืนสินค้า','R',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('40104','Sales Discounts','ส่วนลดจ่าย','R',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('40201','Commission Income','รายได้ค่านายหน้า','R',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('40202','Interest Income','รายได้ดอกเบี้ย','R',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('40203','Gain on Exchange Rate','กำไรจากอัตราแลกเปลี่ยน','R',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('40901','Other Income','รายได้อื่น','R',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50101','Cost of Goods Sold','ต้นทุนขาย','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50102','Purchase Discounts','ส่วนลดรับ','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50103','Freight In','ค่าขนส่งเข้า (ซื้อสินค้า/วัตถุดิบ)','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50104','Production Variance','ผลต่างต้นทุนการผลิต','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50201','Salaries and Bonus','เงินเดือนและโบนัส','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50202','Staff Wages and Overtime','ค่าจ้างและค่าล่วงเวลา','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50203','Social Security Contribution','เงินสมทบประกันสังคม (บ.)','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50204','Staff Welfare','สวัสดิการพนักงาน','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50301','Rent Expense','ค่าเช่า','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50302','Electricity and Water','ค่าน้ำประปาและไฟฟ้า','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50303','Telephone and Internet','ค่าโทรศัพท์และอินเทอร์เน็ต','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50309','Applied Manufacturing Overhead','ค่าโสหุ้ยการผลิตจัดสรรเข้างาน','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50401','Office Supplies','วัสดุสำนักงาน','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50402','Repair and Maintenance','ค่าซ่อมแซมและบำรุงรักษา','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50501','Audit and Accounting Fee','ค่าธรรมเนียมสอบบัญชีและทำบัญชี','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50502','Legal and Professional Fee','ค่าที่ปรึกษาและกฎหมาย','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50503','Service and Contract Work','ค่าจ้างทำของและค่าบริการ','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50601','Travel and Accommodation','ค่าเดินทางและที่พัก','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50602','Entertainment Expense','ค่ารับรอง','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50701','Marketing and Advertising','ค่าโฆษณาและส่งเสริมการขาย','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50702','Freight Out','ค่าขนส่งออก (ส่งสินค้าให้ลูกค้า)','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50801','Insurance Expense','เบี้ยประกันภัย','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50802','Bank Fees','ค่าธรรมเนียมธนาคาร','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50803','Taxes and Licenses','ภาษีและค่าธรรมเนียมราชการ','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('50901','Depreciation Expense','ค่าเสื่อมราคา','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('51001','Interest Expense','ดอกเบี้ยจ่าย','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('52001','Corporate Income Tax','ภาษีเงินได้นิติบุคคล','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25'),
('59901','Miscellaneous Expenses','ค่าใช้จ่ายเบ็ดเตล็ด','X',1,0,'system','2026-03-10 05:14:25','system','2026-03-10 05:14:25');

-- cash over and short: the difference a holder finds counting their petty cash at month end
INSERT INTO `ac_mstr` (`ac_acct`, `ac_desc`, `ac_desc_th`, `ac_type`, `ac_active`, `ac_is_control`) VALUES
('59902','Cash Over and Short','เงินขาดเกินบัญชี','X',1,0);

-- account for gains and losses when fixed assets are sold or written off
INSERT INTO `ac_mstr` (`ac_acct`, `ac_desc`, `ac_desc_th`, `ac_type`, `ac_active`, `ac_is_control`) VALUES
('40204','Gain/Loss on Disposal of Assets','กำไร (ขาดทุน) จากการจำหน่ายสินทรัพย์','R',1,0);

-- fixed asset categories: depreciation method, life and accounts
INSERT INTO `fa_category` (`fc_code`, `fc_desc`, `fc_method`, `fc_life_months`, `fc_rate`, `fc_asset_acct`, `fc_accum_acct`, `fc_expense_acct`, `fc_gainloss_acct`, `fc_active`) VALUES
('BD','อาคาร','SL',240,5,'14201','14202','50901','40204',1),
('MC','เครื่องจักรและอุปกรณ์','SL',120,10,'14301','14302','50901','40204',1),
('OF','อุปกรณ์สำนักงาน','SL',60,20,'14401','14402','50901','40204',1),
('VH','ยานพาหนะ','SL',60,20,'14501','14502','50901','40204',1);

-- product lines: FG and RM carry stock with their own GL accounts;
-- FA lines are items bought as fixed assets: mark the item "not kept in stock" and its receipt
-- posts to the asset account (pl_pur_acct), then register the asset as already booked
INSERT INTO `pl_mstr` (`pl_prod_line`, `pl_desc`, `pl_inventory_type`, `pl_fa_category`, `pl_inv_acct`, `pl_sls_acct`, `pl_cogs_acct`, `pl_wip_acct`, `pl_pur_acct`, `pl_tax_code`, `pl_active`) VALUES
('FG','สินค้าสำเร็จรูป','FG',NULL,'12101','40101','50101','12102',NULL,'VAT7',1),
('RM','วัตถุดิบ','RM',NULL,'12103','40101','50101','12102',NULL,'VAT7',1),
('BD','อาคาร','FA','BD','14201',NULL,NULL,NULL,'14201','VAT7',1),
('MC','เครื่องจักรและอุปกรณ์','FA','MC','14301',NULL,NULL,NULL,'14301','VAT7',1),
('OF','อุปกรณ์สำนักงาน','FA','OF','14401',NULL,NULL,NULL,'14401','VAT7',1),
('VH','ยานพาหนะ','FA','VH','14501',NULL,NULL,NULL,'14501','VAT7',1);
