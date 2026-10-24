-- PhaiERP seed: company profile, control tables, admin role, main warehouse
SET NAMES utf8mb4;

INSERT INTO `co_mstr` (`co_id`, `co_name`, `co_base_curr`) VALUES (1, 'My Company', 'THB');

INSERT INTO `role_mstr` (`role_code`, `role_desc`) VALUES ('ADMIN', 'ผู้ดูแลระบบ');

INSERT INTO `ic_ctrl` (`ctrl_id`, `ic_neg_qty`, `ic_issue_method`, `ic_lot_req`, `ic_auto_loc`, `ic_to_next_nbr`, `ic_to_prefix`, `ic_tr_next_nbr`, `ic_tr_prefix`, `ic_pi_next_nbr`, `ic_pi_prefix`)
VALUES (1, 0, 'FIFO', 1, 'STOCK', 1, 'TO', 1, 'IC', 1, 'PI');

INSERT INTO `po_ctrl` (`ctrl_id`, `po_next_nbr`, `po_prefix`, `po_rcv_tol`, `po_pr_req`, `po_pr_next_nbr`, `po_pr_prefix`, `po_rc_next_nbr`, `po_rc_prefix`)
VALUES (1, 1, 'PO', 0.00, 0, 1, 'PR', 1, 'RC');

INSERT INTO `so_ctrl` (`ctrl_id`, `so_next_nbr`, `so_prefix`, `so_inv_next_nbr`, `so_inv_prefix`, `so_qt_next_nbr`, `so_tax_pct`, `so_cr_chk`, `created_by`, `created_at`, `updated_by`, `updated_at`, `so_sh_next_nbr`, `so_sh_prefix`, `so_qt_prefix`) VALUES (1,1,'SO',1,'IV',1,7.00000,1,NULL,NULL,NULL,NULL,1,'SH','QT');

INSERT INTO `wo_ctrl` (`ctrl_id`, `wo_next_nbr`, `wo_prefix`, `qc_next_nbr`, `qc_prefix`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES (1,1,'WO',1,'QC',NULL,NULL,NULL,NULL);

INSERT INTO `ar_ctrl` (`ctrl_id`, `bl_next_nbr`, `bl_prefix`, `cr_next_nbr`, `cr_prefix`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES (1,1,'BL',1,'CR',NULL,NULL,NULL,NULL);

INSERT INTO `ap_ctrl` (`ctrl_id`, `vo_next_nbr`, `vo_prefix`, `whtc_next_nbr`, `whtc_prefix`, `pay_next_nbr`, `pay_prefix`) VALUES (1,1,'VO',1,'WT',1,'PV');

-- current year/period; the installer may change them
INSERT INTO `gl_ctrl` (`ctrl_id`, `gl_curr_year`, `gl_curr_per`, `gl_base_curr`, `gl_ret_earn_acct`, `gl_ytd_inc_acct`,
  `gl_ar_acct`, `gl_ap_acct`, `gl_sales_acct`, `gl_cogs_acct`, `gl_inv_acct`, `gl_wip_acct`, `gl_vat_out_acct`, `gl_vat_in_acct`, `gl_vat_pay_acct`, `gl_vat_cr_acct`, `gl_vat_defer_acct`, `gl_dep_acct`,
  `gl_grni_acct`, `gl_labor_acct`, `gl_ovh_acct`, `gl_adj_acct`, `gl_exp_acct`, `gl_vo_acct`, `gl_wht_ar_acct`, `gl_je_next_nbr`, `gl_je_prefix`, `gl_pc_acct`, `gl_cash_diff_acct`, `gl_pc_exp_acct`, `gl_wo_var_acct`)
VALUES (1, YEAR(CURDATE()), MONTH(CURDATE()), 'THB', '30301', '30901',
  '11101', '20101', '40101', '50101', '12101', '12102', '21101', '13101', '21105', '13104', '21102', '21201',
  '20103', '50202', '50309', '59901', '59901', '20104', '13103', 1, 'JE', '10102', '59902', '59901', '50104');

-- main warehouse with one location; inventory statuses
INSERT INTO `si_mstr` (`si_site`, `si_desc`, `si_default_loc`, `si_status`) VALUES ('MAIN', 'คลังหลัก', 'STOCK', 1);

INSERT INTO `loc_mstr` (`loc_site`, `loc_loc`, `loc_desc`, `loc_type`, `loc_status`, `loc_nettable`, `loc_mrp`) VALUES ('MAIN', 'STOCK', 'พื้นที่เก็บสินค้า', 'M', 'ACTIVE', 1, 1);

INSERT INTO `is_mstr` (`is_status`, `is_desc`, `is_avail`, `is_nettable`, `is_overissue`) VALUES
('AC', 'พร้อมใช้งาน', 1, 1, 0),
('HD', 'ระงับใช้', 0, 0, 0);

-- reasons for receipts, issues and adjustments (accounts can be changed later)
INSERT INTO `rs_mstr` (`rs_code`, `rs_desc`, `rs_type`, `rs_acct`, `rs_active`) VALUES
('OPEN', 'ยอดยกมา', 'RCT', '30301', 1),
('FOUND', 'พบสินค้าเพิ่ม', 'RCT', '40901', 1),
('USE', 'เบิกใช้ภายใน', 'ISS', '59901', 1),
('DMG', 'สินค้าเสียหาย', 'ISS', '59901', 1),
('EXP', 'สินค้าหมดอายุ', 'ISS', '59901', 1),
('LOST', 'สินค้าสูญหาย', 'ISS', '59901', 1);

-- accounts used by automatic postings
INSERT INTO `ac_mstr` (`ac_acct`, `ac_desc`, `ac_desc_th`, `ac_type`, `ac_active`, `ac_is_control`) VALUES
('13103', 'Withholding Tax Receivable', 'ภาษีถูกหัก ณ ที่จ่าย', 'A', 1, 0),
('20103', 'Goods Received Not Invoiced', 'สินค้ารับแล้วรอใบแจ้งหนี้', 'L', 1, 1),
('20104', 'Vouchers Payable', 'เจ้าหนี้ตามใบสำคัญจ่าย', 'L', 1, 1);
-- only the system posts to these (sub-ledgers): no manual journals
UPDATE `ac_mstr` SET `ac_is_control` = 1 WHERE `ac_acct` IN ('11101', '20101', '12101', '12102', '12103');

-- cash is petty cash: paying or receiving with CASH takes it from / puts it in the petty cash of whoever makes the document
INSERT INTO `bk_mstr` (`bk_bank`, `bk_desc`, `bk_curr`, `bk_acct`, `bk_active`, `bk_petty`) VALUES
('CASH', 'เงินสดย่อย (ของผู้ทำรายการ)', 'THB', '10102', 1, 1);

INSERT INTO `wht_mstr` (`wht_code`, `wht_desc`, `wht_pct`, `wht_acct`, `wht_form_row`, `wht_active`) VALUES
('WHT1', 'ค่าขนส่ง / ค่าโฆษณา 1%', 1.0000, '21103', '5', 1),
('WHT3', 'ค่าบริการ / ค่าจ้างทำของ 3%', 3.0000, '21103', '5', 1),
('WHT5', 'ค่าเช่า 5%', 5.0000, '21104', '5', 1);
