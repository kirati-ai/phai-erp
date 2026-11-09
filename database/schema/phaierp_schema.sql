-- =====================================================================
-- PhaiERP - Database Schema (structure only)
-- Target: MySQL 8.0+ / MariaDB 10.6+ , utf8mb4
-- Seed data lives in database/seeds/ (run after this file, in name order)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `abs_det`;
CREATE TABLE `abs_det` (
  `absd_id` varchar(50) NOT NULL,
  `absd_line` int(11) NOT NULL,
  `absd_part` varchar(50) NOT NULL,
  `absd_qty` decimal(18,4) DEFAULT 0.0000,
  `absd_um` varchar(10) DEFAULT NULL,
  `absd_lot` varchar(50) DEFAULT NULL,
  `absd_po_nbr` varchar(50) DEFAULT NULL,
  `absd_po_line` int(11) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`absd_id`,`absd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `abs_mstr`;
CREATE TABLE `abs_mstr` (
  `abs_id` varchar(50) NOT NULL,
  `abs_ship_via` varchar(50) DEFAULT NULL,
  `abs_date` date DEFAULT NULL,
  `abs_type` varchar(20) DEFAULT NULL,
  `abs_status` varchar(10) DEFAULT 'O',
  `abs_gross_wt` decimal(18,4) DEFAULT 0.0000,
  `abs_wt_um` varchar(10) DEFAULT 'KG',
  `abs_container` varchar(50) DEFAULT NULL,
  `abs_remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(50) DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`abs_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ai_log`;
CREATE TABLE `ai_log` (
  `ai_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ai_userid` varchar(50) DEFAULT NULL COMMENT 'ผู้ถาม',
  `ai_provider` varchar(20) NOT NULL COMMENT 'ผู้ให้บริการ (claude, gemini)',
  `ai_model` varchar(100) NOT NULL COMMENT 'รุ่นโมเดล',
  `ai_question` text NOT NULL COMMENT 'คำถาม',
  `ai_answer` mediumtext DEFAULT NULL COMMENT 'คำตอบ',
  `ai_tools` text DEFAULT NULL COMMENT 'API ที่ AI เรียกอ่าน (JSON)',
  `ai_in_tok` int(11) NOT NULL DEFAULT 0 COMMENT 'โทเค็นขาเข้า',
  `ai_out_tok` int(11) NOT NULL DEFAULT 0 COMMENT 'โทเค็นขาออก',
  `ai_cost` decimal(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'ค่าใช้จ่าย (USD)',
  `ai_ms` int(11) NOT NULL DEFAULT 0 COMMENT 'เวลาที่ใช้ (มิลลิวินาที)',
  `ai_status` varchar(1) NOT NULL DEFAULT 'S' COMMENT 'S=สำเร็จ, E=ผิดพลาด',
  `ai_error` varchar(255) DEFAULT NULL COMMENT 'ข้อความผิดพลาด',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ai_id`),
  KEY `idx_ai_user_date` (`ai_userid`, `created_at`),
  KEY `idx_ai_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI Questions and Usage Log';

DROP TABLE IF EXISTS `ac_mstr`;
CREATE TABLE `ac_mstr` (
  `ac_acct` varchar(50) NOT NULL COMMENT 'รหัสบัญชี (GL Account No.)',
  `ac_desc` varchar(255) NOT NULL COMMENT 'ชื่อบัญชี',
  `ac_desc_th` varchar(255) DEFAULT NULL,
  `ac_type` varchar(1) NOT NULL COMMENT 'A=Asset, L=Liability, E=Equity, R=Revenue, E=Expense',
  `ac_active` tinyint(1) DEFAULT 1,
  `ac_is_control` tinyint(1) DEFAULT 0 COMMENT '1=บัญชีคุม (ห้าม User ลง Manual Journal ต้องผ่านระบบย่อย เช่น AP/AR/IC)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ac_acct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chart of Accounts Master';

DROP TABLE IF EXISTS `acd_det`;
CREATE TABLE `acd_det` (
  `acd_acct` varchar(50) NOT NULL COMMENT 'รหัสบัญชี (FK: ac_acct)',
  `acd_cc` varchar(50) NOT NULL DEFAULT '' COMMENT 'ศูนย์ต้นทุน/แผนก (ถ้าองค์กรมีการแยกงบตามแผนก)',
  `acd_sub` varchar(2) NOT NULL DEFAULT '' COMMENT 'Sub Account',
  `acd_year` int(11) NOT NULL COMMENT 'ปีบัญชี (เช่น 2026)',
  `acd_per` int(11) NOT NULL COMMENT 'งวดบัญชี / เดือน (1 - 12)',
  `acd_beg_bal` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดยกมาต้นงวด (Beginning Balance)',
  `acd_dr_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดรวมฝั่งเดบิตในงวด',
  `acd_cr_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดรวมฝั่งเครดิตในงวด',
  `acd_end_bal` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดยกไปสิ้นงวด (Ending Balance)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`acd_acct`,`acd_cc`,`acd_year`,`acd_per`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GL Account Balances by Period';

DROP TABLE IF EXISTS `ad_mstr`;
CREATE TABLE `ad_mstr` (
  `ad_addr` varchar(50) NOT NULL COMMENT 'รหัสที่อยู่ (เช่น CUS001, VEND001, MAIN_SITE)',
  `ad_name` varchar(255) NOT NULL COMMENT 'ชื่อบริษัท / ชื่อบุคคล',
  `ad_name2` varchar(255) DEFAULT NULL COMMENT 'ชื่อบรรทัด 2',
  `ad_line1` varchar(255) DEFAULT NULL COMMENT 'ที่อยู่บรรทัดที่ 1',
  `ad_line2` varchar(255) DEFAULT NULL COMMENT 'ที่อยู่บรรทัดที่ 2',
  `ad_line3` varchar(255) DEFAULT NULL COMMENT 'ที่อยู่บรรทัดที่ 3',
  `ad_building` varchar(70) DEFAULT NULL COMMENT 'อาคาร',
  `ad_room` varchar(20) DEFAULT NULL COMMENT 'ห้องเลขที่',
  `ad_floor` varchar(20) DEFAULT NULL COMMENT 'ชั้นที่',
  `ad_village` varchar(70) DEFAULT NULL COMMENT 'หมู่บ้าน',
  `ad_house_no` varchar(16) DEFAULT NULL COMMENT 'เลขที่ (บ้านเลขที่)',
  `ad_moo` varchar(10) DEFAULT NULL COMMENT 'หมู่ที่',
  `ad_soi` varchar(70) DEFAULT NULL COMMENT 'ตรอก/ซอย',
  `ad_yaek` varchar(70) DEFAULT NULL COMMENT 'แยก',
  `ad_road` varchar(70) DEFAULT NULL COMMENT 'ถนน',
  `ad_sub_district` varchar(100) DEFAULT NULL COMMENT 'ตำบล/แขวง',
  `ad_city` varchar(100) DEFAULT NULL COMMENT 'อำเภอ/เขต (เมือง)',
  `ad_state` varchar(100) DEFAULT NULL COMMENT 'จังหวัด (รัฐ)',
  `ad_zip` varchar(20) DEFAULT NULL COMMENT 'รหัสไปรษณีย์',
  `ad_country` varchar(100) DEFAULT 'Thailand' COMMENT 'ประเทศ',
  `ad_attn` varchar(100) DEFAULT NULL COMMENT 'ผู้ติดต่อ (Attention to)',
  `ad_phone` varchar(50) DEFAULT NULL COMMENT 'เบอร์โทรศัพท์',
  `ad_phone2` varchar(50) DEFAULT NULL COMMENT 'เบอร์โทรศัพท์ 2',
  `ad_fax` varchar(50) DEFAULT NULL COMMENT 'แฟกซ์',
  `ad_email` varchar(100) DEFAULT NULL COMMENT 'อีเมล',
  `ad_website` varchar(255) DEFAULT NULL,
  `ad_tax_id` varchar(50) DEFAULT NULL COMMENT 'เลขประจำตัวผู้เสียภาษี',
  `ad_tax_branch` varchar(50) DEFAULT '00000' COMMENT 'สาขาผู้เสียภาษี (00000=สนง.ใหญ่)',
  `ad_lat` decimal(10,8) DEFAULT NULL COMMENT 'ละติจูด',
  `ad_lng` decimal(11,8) DEFAULT NULL COMMENT 'ลองจิจูด',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ad_addr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Centralized Address Master';

DROP TABLE IF EXISTS `am_mstr`;
CREATE TABLE `am_mstr` (
  `am_id` int(11) NOT NULL AUTO_INCREMENT,
  `am_code` int(11) NOT NULL,
  `am_name_th` varchar(150) NOT NULL,
  `am_name_en` varchar(150) DEFAULT NULL,
  `am_pv_id` int(11) NOT NULL,
  PRIMARY KEY (`am_id`),
  UNIQUE KEY `ux_am_code` (`am_code`),
  KEY `fk_am_pv` (`am_pv_id`),
  CONSTRAINT `fk_am_pv` FOREIGN KEY (`am_pv_id`) REFERENCES `pv_mstr` (`pv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ap_ctrl`;
CREATE TABLE `ap_ctrl` (
  `ctrl_id` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'ตารางนี้มีได้แถวเดียว (ค่าตั้งต้นของระบบ)',
  `ap_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ใบตั้งหนี้ (AP)',
  `ap_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่ (เหมือน vo_format)',
  `ap_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป',
  `ap_prefix` varchar(10) DEFAULT 'AP' COMMENT 'คำนำหน้าใบตั้งหนี้',
  `vo_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ใบสำคัญจ่าย (VO: รวมตั้งหนี้ของผู้ขายเพื่อจ่าย)',
  `vo_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `vo_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `vo_prefix` varchar(10) DEFAULT 'VO' COMMENT 'คำนำหน้าใบสำคัญจ่าย',
  `whtc_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running WHT 50 Tawi',
  `whtc_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `whtc_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `whtc_prefix` varchar(10) DEFAULT 'WT' COMMENT 'WHT Prefix',
  `pay_next_nbr` int(11) DEFAULT 1 COMMENT 'เลขถัดไปของใบสำคัญจ่าย',
  `pay_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `pay_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `pay_prefix` varchar(10) DEFAULT 'PV' COMMENT 'คำนำหน้าใบสำคัญจ่าย',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` datetime DEFAULT NULL COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` datetime DEFAULT NULL COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ctrl_id`),
  CONSTRAINT `chk_ap_ctrl_single` CHECK (`ctrl_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ap_mstr`;
CREATE TABLE `ap_mstr` (
  `ap_ref` varchar(50) NOT NULL COMMENT 'เลขที่ Voucher',
  `ap_vd_code` varchar(50) NOT NULL,
  `ap_po_nbr` varchar(50) DEFAULT NULL COMMENT 'อ้างอิงใบสั่งซื้อที่รับมา',
  `ap_inv_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ Invoice ของ Vendor',
  `ap_ref_ap` varchar(50) DEFAULT NULL COMMENT 'การตั้งหนี้ที่ใบลดหนี้ (ap_type CM) นี้อ้างถึง',
  `ap_acct` varchar(50) DEFAULT NULL COMMENT 'บัญชีเจ้าหนี้ (ค่าตั้งต้นจากผู้ขาย) ใบสำคัญจ่ายเดบิตบัญชีนี้',
  `ap_date` date NOT NULL,
  `ap_effdate` date NOT NULL COMMENT 'วันที่ลงบันทึกบัญชี GL',
  `ap_due_date` date DEFAULT NULL,
  `ap_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดตั้งหนี้ทั้งหมด',
  `ap_paid_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดที่เราจ่ายไปแล้ว (Accumulated)',
  `ap_type` varchar(10) DEFAULT 'VO' COMMENT 'VO=Voucher, DM=Debit Memo',
  `ap_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, P=Paid',
  `ap_cr_terms` varchar(20) DEFAULT NULL,
  `ap_bank` varchar(50) DEFAULT NULL COMMENT 'ธนาคารที่จะจ่ายเงินออก',
  `ap_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ap_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AP Voucher Master';

DROP TABLE IF EXISTS `apv_mstr`;
CREATE TABLE `apv_mstr` (
  `apv_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบสำคัญจ่าย (VO)',
  `apv_vd_code` varchar(50) NOT NULL COMMENT 'ผู้ขาย',
  `apv_date` date NOT NULL COMMENT 'วันที่',
  `apv_pay_date` date DEFAULT NULL COMMENT 'วันนัดจ่าย',
  `apv_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ยอดรวม (บาท)',
  `apv_paid_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ยอดที่จ่ายแล้ว (PV)',
  `apv_status` varchar(1) NOT NULL DEFAULT 'O' COMMENT 'O=รอจ่าย, P=จ่ายแล้ว, C=ยกเลิก',
  `apv_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`apv_nbr`),
  KEY `idx_apv_vd` (`apv_vd_code`, `apv_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Voucher: payables of one vendor gathered for payment';

DROP TABLE IF EXISTS `apvd_det`;
CREATE TABLE `apvd_det` (
  `apvd_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบสำคัญจ่าย (FK -> apv_mstr)',
  `apvd_line` int(11) NOT NULL COMMENT 'ลำดับบรรทัด',
  `apvd_ap_ref` varchar(50) NOT NULL COMMENT 'ใบตั้งหนี้ (FK -> ap_mstr.ap_ref)',
  `apvd_acct` varchar(50) NOT NULL COMMENT 'บัญชีเจ้าหนี้ของใบตั้งหนี้ (เดบิต)',
  `apvd_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ยอดที่โอนเข้าใบสำคัญจ่าย (ใบลดหนี้ติดลบ)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`apvd_nbr`, `apvd_line`),
  KEY `idx_apvd_ap` (`apvd_ap_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Voucher lines: one payable each';

DROP TABLE IF EXISTS `apd_det`;
CREATE TABLE `apd_det` (
  `apd_ref` varchar(50) NOT NULL COMMENT 'เลข Voucher',
  `apd_line` int(11) NOT NULL,
  `apd_acct` varchar(50) NOT NULL COMMENT 'รหัสบัญชี (GL Account - Ex. บัญชีค่าใช้จ่าย, ภาษีซื้อ, Inventory Clearing)',
  `apd_cc` varchar(50) DEFAULT NULL COMMENT 'ศูนย์ต้นทุน/แผนกที่รับค่าใช้จ่าย',
  `apd_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account',
  `apd_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่ลงในบัญชีนั้น',
  `apd_desc` varchar(255) DEFAULT NULL COMMENT 'รายละเอียดบันทึกบัญชี',
  `apd_po_nbr` varchar(50) DEFAULT NULL COMMENT 'เอกสาร PO อ้างอิง',
  `apd_pod_line` int(11) DEFAULT NULL COMMENT 'ลำดับใน PO',
  `apd_wht_code` varchar(20) DEFAULT NULL COMMENT 'รหัสหัก ณ ที่จ่ายของบรรทัดนี้ (ฐาน = ยอดบรรทัด)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`apd_ref`,`apd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AP Voucher Detail / GL Distribution';

DROP TABLE IF EXISTS `ar_ctrl`;
CREATE TABLE `ar_ctrl` (
  `ctrl_id` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'ตารางนี้มีได้แถวเดียว (ค่าตั้งต้นของระบบ)',
  `bl_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running Billing',
  `bl_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `bl_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `bl_prefix` varchar(10) DEFAULT 'BL' COMMENT 'Billing Prefix',
  `cr_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running Cash Receipt',
  `cr_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `cr_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `cr_prefix` varchar(10) DEFAULT 'CR' COMMENT 'Receipt Prefix',
  `dp_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ใบรับเงินมัดจำ',
  `dp_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่ใบรับเงินมัดจำ',
  `dp_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป',
  `dp_prefix` varchar(10) DEFAULT 'DP' COMMENT 'Deposit prefix',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` datetime DEFAULT NULL COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` datetime DEFAULT NULL COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ctrl_id`),
  CONSTRAINT `chk_ar_ctrl_single` CHECK (`ctrl_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ar_mstr`;
CREATE TABLE `ar_mstr` (
  `ar_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ตั้งลูกหนี้ (ปกติจะเท่ากับ ih_inv_nbr)',
  `ar_cm_code` varchar(50) NOT NULL COMMENT 'รหัสลูกค้า (FK -> cm_mstr)',
  `ar_type` varchar(10) DEFAULT 'IN' COMMENT 'IN=Invoice, CM=Credit Memo, DM=Debit Memo',
  `ar_date` date NOT NULL COMMENT 'วันที่เกิดรายการ',
  `ar_due_date` date DEFAULT NULL COMMENT 'วันครบกำหนดชำระ',
  `ar_effdate` date NOT NULL COMMENT 'วันที่ลงบันทึก GL',
  `ar_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดตั้งลูกหนี้รวม',
  `ar_paid_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดที่ได้รับชำระแล้ว (Accumulated)',
  `ar_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, P=Paid, C=Closed/Reversed',
  `ar_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ar_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Accounts Receivable Master';

DROP TABLE IF EXISTS `ard_det`;
CREATE TABLE `ard_det` (
  `ard_nbr` varchar(50) NOT NULL COMMENT 'เลขตั้งลูกหนี้',
  `ard_line` int(11) NOT NULL,
  `ard_acct` varchar(50) NOT NULL COMMENT 'รหัสบัญชี (เช่น รายได้, ภาษีขาย)',
  `ard_cc` varchar(50) DEFAULT NULL,
  `ard_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account',
  `ard_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดลงบัญชี (DR/CR อิงตามเครื่องหมาย)',
  `ard_desc` varchar(255) DEFAULT NULL COMMENT 'คำอธิบายรายบรรทัดสำหรับคนทำบัญชี',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ard_nbr`,`ard_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AR Account Integration Details';

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `log_table` varchar(50) NOT NULL COMMENT 'ชื่อตาราง (เช่น pt_mstr, cm_mstr)',
  `log_key` varchar(255) NOT NULL COMMENT 'Primary Key ที่ถูกแก้ (เช่น pt_part=ITEM01)',
  `log_action` varchar(10) NOT NULL COMMENT 'CREATE, UPDATE, DELETE',
  `log_field` varchar(50) DEFAULT NULL COMMENT 'ชื่อฟิลด์ที่ถูกเปลี่ยน',
  `log_old_val` text DEFAULT NULL COMMENT 'ค่าเดิม',
  `log_new_val` text DEFAULT NULL COMMENT 'ค่าใหม่',
  `log_userid` varchar(50) NOT NULL COMMENT 'ผู้กระทำ',
  `log_ip` varchar(50) DEFAULT NULL COMMENT 'IP Address',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_time` (`created_at`),
  KEY `idx_audit_table` (`log_table`),
  KEY `idx_audit_key` (`log_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System Audit Logs';

DROP TABLE IF EXISTS `bk_mstr`;
CREATE TABLE `bk_mstr` (
  `bk_bank` varchar(50) NOT NULL COMMENT 'รหัสสมุดธนาคาร (เช่น BBL-01, SCB-01)',
  `bk_desc` varchar(255) NOT NULL COMMENT 'ชื่อบัญชีธนาคาร',
  `bk_curr` varchar(3) NOT NULL DEFAULT 'THB' COMMENT 'สกุลเงินของบัญชีนี้',
  `bk_acct` varchar(50) NOT NULL COMMENT 'รหัส GL ที่ผูกกับเงินฝากก้อนนี้',
  `bk_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account Default',
  `bk_active` tinyint(1) DEFAULT 1,
  `bk_petty` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = เงินสดย่อย: เงินเข้า/ออกจากกล่องของผู้ทำเอกสาร (บัญชี gl_pc_acct) ไม่อยู่ในกระทบยอดธนาคาร',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`bk_bank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank Account Master';

DROP TABLE IF EXISTS `bl_mstr`;
CREATE TABLE `bl_mstr` (
  `bl_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบวางบิล',
  `bl_cm_code` varchar(50) NOT NULL COMMENT 'รหัสลูกค้า',
  `bl_bill_to` varchar(50) DEFAULT NULL COMMENT 'สถานที่วางบิล',
  `bl_date` date NOT NULL COMMENT 'วันที่ทำใบวางบิล',
  `bl_due_date` date DEFAULT NULL COMMENT 'วันกำหนดชำระเงินที่นัดหมาย',
  `bl_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, P=Paid (ส่วนใหญ่จ่ายครบ), C=Canceled',
  `bl_tot_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดรวมตะกร้าบิลทั้งหมด',
  `bl_paid_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดที่ลูกค้าจ่ายรวมสำหรับบิลคอกนี้',
  `bl_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`bl_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Billing / Statement Header';

DROP TABLE IF EXISTS `bld_det`;
CREATE TABLE `bld_det` (
  `bld_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบวางบิล (FK -> bl_nbr)',
  `bld_line` int(11) NOT NULL COMMENT 'ลำดับบรรทัด',
  `bld_inv_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบแจ้งหนี้ (FK -> ih_inv_nbr)',
  `bld_inv_date` date NOT NULL COMMENT 'วันที่ของใบแจ้งหนี้',
  `bld_due_date` date DEFAULT NULL COMMENT 'วันครบกำหนดเดิมของใบแจ้งหนี้',
  `bld_inv_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดรวมของใบแจ้งหนี้นี้',
  `bld_paid_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดที่จ่ายมาแล้วก่อนหน้า (ถ้ามี)',
  `bld_bal_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดหนี้คงค้างของใบนี้ตอนที่รวมเข้าคอก',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`bld_nbr`,`bld_line`),
  KEY `idx_bld_inv` (`bld_inv_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Billing / Statement Detail';

DROP TABLE IF EXISTS `cc_mstr`;
CREATE TABLE `cc_mstr` (
  `cc_cc` varchar(3) NOT NULL COMMENT 'Cost Center Code',
  `cc_desc` varchar(255) NOT NULL COMMENT 'Description',
  `cc_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`cc_cc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cm_mstr`;
CREATE TABLE `cm_mstr` (
  `cm_code` varchar(50) NOT NULL COMMENT 'รหัสลูกค้า (เชื่อม -> ad_addr)',
  `cm_name` varchar(255) NOT NULL,
  `cm_name2` varchar(255) DEFAULT NULL,
  `cm_line1` varchar(255) DEFAULT NULL,
  `cm_line2` varchar(255) DEFAULT NULL,
  `cm_line3` varchar(255) DEFAULT NULL,
  `cm_building` varchar(70) DEFAULT NULL COMMENT 'อาคาร',
  `cm_room` varchar(20) DEFAULT NULL COMMENT 'ห้องเลขที่',
  `cm_floor` varchar(20) DEFAULT NULL COMMENT 'ชั้นที่',
  `cm_village` varchar(70) DEFAULT NULL COMMENT 'หมู่บ้าน',
  `cm_house_no` varchar(16) DEFAULT NULL COMMENT 'เลขที่ (บ้านเลขที่)',
  `cm_moo` varchar(10) DEFAULT NULL COMMENT 'หมู่ที่',
  `cm_soi` varchar(70) DEFAULT NULL COMMENT 'ตรอก/ซอย',
  `cm_yaek` varchar(70) DEFAULT NULL COMMENT 'แยก',
  `cm_road` varchar(70) DEFAULT NULL COMMENT 'ถนน',
  `cm_sub_district` varchar(100) DEFAULT NULL COMMENT 'ตำบล',
  `cm_district` varchar(100) DEFAULT NULL COMMENT 'อำเภอ',
  `cm_province` varchar(100) DEFAULT NULL COMMENT 'จังหวัด',
  `cm_zip` varchar(20) DEFAULT NULL,
  `cm_country` varchar(100) DEFAULT 'Thailand',
  `cm_attn` varchar(100) DEFAULT NULL,
  `cm_phone` varchar(50) DEFAULT NULL,
  `cm_phone2` varchar(50) DEFAULT NULL,
  `cm_fax` varchar(50) DEFAULT NULL,
  `cm_email` varchar(100) DEFAULT NULL,
  `cm_website` varchar(255) DEFAULT NULL,
  `cm_tax_id_num` varchar(50) DEFAULT NULL,
  `cm_tax_branch` varchar(50) DEFAULT '00000',
  `cm_type` varchar(50) DEFAULT NULL COMMENT 'ประเภทลูกค้า',
  `cm_region` varchar(50) DEFAULT NULL COMMENT 'ภูมิภาค',
  `cm_terms` varchar(20) DEFAULT NULL COMMENT 'เงื่อนไขการชำระเงิน',
  `cm_curr` varchar(3) DEFAULT 'THB',
  `cm_tax_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษี',
  `cm_tax_in_price` tinyint(1) DEFAULT 0 COMMENT '1 = ราคาสินค้ารวมภาษีแล้ว (VAT IN)',
  `cm_salesperson` varchar(50) DEFAULT NULL COMMENT 'พนักงานขาย',
  `cm_site` varchar(50) DEFAULT NULL COMMENT 'โรงงานที่ส่งของให้ประจำ',
  `cm_shipvia` varchar(50) DEFAULT NULL COMMENT 'วิธีการจัดส่งประจำ',
  `cm_pr_list` varchar(50) DEFAULT NULL COMMENT 'ผูก Price List พิเศษ',
  `cm_cr_limit` decimal(18,5) DEFAULT 0.00000 COMMENT 'วงเงินเครดิตสูงสุด',
  `cm_bank` varchar(50) DEFAULT NULL,
  `cm_bank_acct` varchar(50) DEFAULT NULL,
  `cm_bank_branch` varchar(100) DEFAULT NULL,
  `cm_swift_code` varchar(20) DEFAULT NULL,
  `cm_balance` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดหนี้คงค้างรวม',
  `cm_ar_acct` varchar(50) DEFAULT NULL COMMENT 'บัญชีลูกหนี้ผูกเฉพาะราย (ถ้ามี)',
  `cm_ar_sub` varchar(2) DEFAULT NULL COMMENT 'AR Sub Account',
  `cm_status` varchar(20) DEFAULT 'ACTIVE' COMMENT 'ACTIVE, HOLD, INACTIVE',
  `cm_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`cm_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer Master';

DROP TABLE IF EXISTS `cm_ship_to`;
CREATE TABLE `cm_ship_to` (
  `cm_code` varchar(50) NOT NULL COMMENT 'รหัสลูกค้า (FK -> cm_code)',
  `cm_ship_code` varchar(50) NOT NULL COMMENT 'รหัสระบุสาขาส่งของ (เช่น 00001, WH01)',
  `cm_ship_name` varchar(255) NOT NULL,
  `cm_ship_addr1` varchar(255) DEFAULT NULL,
  `cm_ship_addr2` varchar(255) DEFAULT NULL,
  `cm_ship_city` varchar(100) DEFAULT NULL,
  `cm_ship_state` varchar(100) DEFAULT NULL,
  `cm_ship_zip` varchar(20) DEFAULT NULL,
  `cm_ship_country` varchar(100) DEFAULT 'Thailand',
  `cm_ship_phone` varchar(50) DEFAULT NULL,
  `cm_ship_contact` varchar(100) DEFAULT NULL,
  `cm_ship_default` tinyint(1) DEFAULT 0 COMMENT '1=เป็นสถานที่ส่งหลัก',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`cm_code`,`cm_ship_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer Multiple Ship-to Addresses';

DROP TABLE IF EXISTS `cn_mstr`;
CREATE TABLE `cn_mstr` (
  `cn_id` int(11) NOT NULL AUTO_INCREMENT,
  `cn_code` char(2) NOT NULL DEFAULT '',
  `cn_alpha_3` char(3) NOT NULL DEFAULT '',
  `cn_name_th` varchar(100) NOT NULL DEFAULT '',
  `cn_name_en` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`cn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `co_mstr`;
CREATE TABLE `co_mstr` (
  `co_id` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'มีได้แถวเดียว',
  `co_name` varchar(255) NOT NULL COMMENT 'ชื่อบริษัท',
  `co_tax_id` varchar(50) DEFAULT NULL COMMENT 'เลขประจำตัวผู้เสียภาษี',
  `co_branch` varchar(20) DEFAULT '00000' COMMENT 'สาขา (00000 = สำนักงานใหญ่)',
  `co_address` text DEFAULT NULL COMMENT 'ที่อยู่สำหรับพิมพ์บนเอกสาร',
  `co_contact_name` varchar(100) DEFAULT NULL COMMENT 'ชื่อผู้ติดต่อ',
  `co_email` varchar(100) DEFAULT NULL,
  `co_phone` varchar(50) DEFAULT NULL,
  `co_logo_path` varchar(255) DEFAULT NULL COMMENT 'Path ไฟล์โลโก้บริษัท',
  `co_stamp_path` varchar(40) DEFAULT NULL COMMENT 'ไฟล์ตราประทับบริษัท (PNG พื้นใส)',
  `co_base_curr` varchar(3) DEFAULT 'THB' COMMENT 'สกุลเงินหลักทางบัญชี',
  `co_idle_min` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'ออกจากระบบเมื่อไม่ใช้งาน (นาที, 0 = ไม่ออก)',
  `co_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`co_id`),
  CONSTRAINT `chk_co_single` CHECK (`co_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Company Profile (single company)';

DROP TABLE IF EXISTS `crd_det`;
CREATE TABLE `crd_det` (
  `crd_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบเสร็จ (cr_nbr)',
  `crd_line` int(11) NOT NULL,
  `crd_ar_nbr` varchar(50) NOT NULL COMMENT 'ลูกหนี้ / ใบแจ้งหนี้ที่ตัด',
  `crd_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่ตัดหนี้ (รวมภาษีที่ถูกหัก)',
  `crd_wht_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ภาษีที่ลูกค้าหักในใบนี้',
  `crd_net_amt` decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'มูลค่าบริการที่ภาษีถึงกำหนดในการรับครั้งนี้ (บาท)',
  `crd_vat_amt` decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'ภาษีขายที่ถึงกำหนดในการรับครั้งนี้ (บาท) — งานบริการ',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`crd_nbr`,`crd_line`),
  KEY `idx_crd_ar` (`crd_ar_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cash Receipt Detail';

DROP TABLE IF EXISTS `cr_mstr`;
CREATE TABLE `cr_mstr` (
  `cr_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบเสร็จ',
  `cr_bank` varchar(50) NOT NULL COMMENT 'รับโอนเข้าธนาคารไหน (FK: bk_bank)',
  `cr_cm_code` varchar(50) NOT NULL COMMENT 'รับเงินจากลูกค้าคนไหน',
  `cr_date` date NOT NULL COMMENT 'วันที่รับเงิน',
  `cr_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนเงินสด/โอนสุทธิที่เข้าธนาคาร',
  `cr_wht_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ภาษีที่ลูกค้าหัก ณ ที่จ่าย',
  `cr_ar_nbr` varchar(50) DEFAULT NULL COMMENT 'อ้างอิงเลขที่ลูกหนี้/Invoice ที่ต้องการตัด (ในกรณีรับบิลเดียว)',
  `cr_status` varchar(1) DEFAULT 'P' COMMENT 'U=Unapplied (เงินรอลอยๆ ยังไม่ตัดหนี้), P=Posted (ตัดหนี้เรียบร้อยแล้ว)',
  `cr_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`cr_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cash Receipt / AR Payment';

DROP TABLE IF EXISTS `ct_mstr`;
CREATE TABLE `ct_mstr` (
  `ct_code` varchar(20) NOT NULL COMMENT 'รหัสเงื่อนไข (เช่น 30DAYS, COD)',
  `ct_desc` varchar(100) NOT NULL COMMENT 'คำอธิบาย (เช่น เครดิต 30 วัน)',
  `ct_net_days` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนวันเครดิต (คำนวณ Due Date)',
  `ct_disc_days` int(11) DEFAULT 0 COMMENT 'จำนวนวันที่ให้คอยจ่ายเพื่อรับส่วนลด',
  `ct_disc_pct` decimal(10,4) DEFAULT 0.0000 COMMENT 'เปอร์เซ็นตส่วนลดถ้าจ่ายเร็ว',
  `ct_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ct_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Credit Terms Master';

DROP TABLE IF EXISTS `cu_mstr`;
CREATE TABLE `cu_mstr` (
  `cu_curr` varchar(3) NOT NULL,
  `cu_desc` varchar(100) NOT NULL,
  `cu_symbol` varchar(10) DEFAULT NULL,
  `cu_icon` varchar(50) DEFAULT NULL,
  `cu_active` smallint(6) NOT NULL DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cu_curr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `emp_mstr`;
CREATE TABLE `emp_mstr` (
  `emp_nbr` varchar(50) NOT NULL COMMENT 'รหัสพนักงาน',
  `emp_name` varchar(255) NOT NULL COMMENT 'ชื่อ-สกุล',
  `emp_dept` varchar(50) DEFAULT NULL COMMENT 'แผนก',
  `emp_wkctr` varchar(50) DEFAULT NULL COMMENT 'เครื่องจักร/พื้นที่รับผิดชอบ',
  `emp_type` varchar(20) DEFAULT 'FULL_TIME' COMMENT 'FULL_TIME, PART_TIME, SUBCONTRACT',
  `emp_rate` decimal(15,5) DEFAULT 0.00000 COMMENT 'อัตราค่าแรง/ชั่วโมง',
  `emp_active` tinyint(1) DEFAULT 1,
  `emp_userid` varchar(50) DEFAULT NULL COMMENT 'ผู้ใช้ที่เข้าระบบ (us_mstr) — คนเดียวกับพนักงานนี้',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`emp_nbr`),
  UNIQUE KEY `emp_userid` (`emp_userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee Master';

DROP TABLE IF EXISTS `ex_mstr`;
CREATE TABLE `ex_mstr` (
  `ex_curr` varchar(3) NOT NULL COMMENT 'สกุลเงิน (เช่น USD, EUR, JPY)',
  `ex_date` date NOT NULL COMMENT 'วันที่ของอัตราแลกเปลี่ยน',
  `ex_rate` decimal(18,5) NOT NULL DEFAULT 1.00000 COMMENT 'อัตราแลกเปลี่ยนเทียบกับ Base Currency',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ex_curr`,`ex_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Exchange Rate Master';

DROP TABLE IF EXISTS `fc_mstr`;
CREATE TABLE `fc_mstr` (
  `fc_nbr` varchar(50) NOT NULL COMMENT 'เลขที่แผนพยากรณ์',
  `fc_desc` varchar(100) DEFAULT NULL COMMENT 'รายละเอียดแผน',
  `fc_cust` varchar(50) DEFAULT NULL COMMENT 'รหัสลูกค้า (ถ้าเจาะจง)',
  `fc_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`fc_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `fcd_det`;
CREATE TABLE `fcd_det` (
  `fcd_nbr` varchar(50) NOT NULL COMMENT 'อ้างอิง fc_mstr.fc_nbr',
  `fcd_line` int(11) NOT NULL COMMENT 'ลำดับรายการ',
  `fcd_pt_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `fcd_qty_forecast` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนที่พยากรณ์',
  `fcd_date` date NOT NULL COMMENT 'วันที่คาดการณ์',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`fcd_nbr`,`fcd_line`),
  KEY `idx_fcd_part` (`fcd_pt_part`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `gl_ctrl`;
CREATE TABLE `gl_ctrl` (
  `ctrl_id` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'ตารางนี้มีได้แถวเดียว (ค่าตั้งต้นของระบบ)',
  `gl_curr_year` int(11) NOT NULL COMMENT 'ปีบัญชีปัจจุบัน',
  `gl_curr_per` int(11) NOT NULL COMMENT 'งวด/เดือนบัญชีปัจจุบัน (1-12)',
  `gl_base_curr` varchar(3) DEFAULT 'THB',
  `gl_ret_earn_acct` varchar(50) DEFAULT NULL COMMENT 'บัญชีกำไรสะสม (Retained Earnings)',
  `gl_ytd_inc_acct` varchar(50) DEFAULT NULL COMMENT 'บัญชีกำไร/ขาดทุนงวดปัจจุบัน',
  `gl_ar_acct` varchar(50) DEFAULT NULL COMMENT 'ลูกหนี้การค้า',
  `gl_ap_acct` varchar(50) DEFAULT NULL COMMENT 'เจ้าหนี้การค้า',
  `gl_sales_acct` varchar(50) DEFAULT NULL COMMENT 'รายได้จากการขาย',
  `gl_cogs_acct` varchar(50) DEFAULT NULL COMMENT 'ต้นทุนขาย',
  `gl_inv_acct` varchar(50) DEFAULT NULL COMMENT 'สินค้าคงคลัง',
  `gl_wip_acct` varchar(50) DEFAULT NULL COMMENT 'งานระหว่างทำ',
  `gl_vat_out_acct` varchar(50) DEFAULT NULL COMMENT 'ภาษีขาย',
  `gl_vat_in_acct` varchar(50) DEFAULT NULL COMMENT 'ภาษีซื้อ',
  `gl_vat_pay_acct` varchar(50) DEFAULT NULL COMMENT 'ภาษีมูลค่าเพิ่มค้างจ่าย (ภ.พ.30 ต้องชำระ)',
  `gl_vat_cr_acct` varchar(50) DEFAULT NULL COMMENT 'ภาษีมูลค่าเพิ่มชำระเกินยกไป (ภ.พ.30 เครดิต)',
  `gl_dep_acct` varchar(50) DEFAULT NULL COMMENT 'เงินมัดจำรับจากลูกค้า (หนี้สิน)',
  `gl_vat_defer_acct` varchar(50) DEFAULT NULL COMMENT 'ภาษีขายยังไม่ถึงกำหนด (งานบริการ ถึงกำหนดเมื่อรับชำระ)',
  `gl_grni_acct` varchar(50) DEFAULT NULL COMMENT 'สินค้ารับแล้วรอใบแจ้งหนี้',
  `gl_labor_acct` varchar(50) DEFAULT NULL COMMENT 'ค่าแรงที่โอนเข้าการผลิต',
  `gl_ovh_acct` varchar(50) DEFAULT NULL COMMENT 'ค่าโสหุ้ยการผลิตที่จัดสรรเข้างาน (ชม. x อัตราเครื่องจักร + โสหุ้ยของศูนย์งาน)',
  `gl_adj_acct` varchar(50) DEFAULT NULL COMMENT 'ปรับปรุงสินค้า (เมื่อไม่ระบุเหตุผล)',
  `gl_exp_acct` varchar(50) DEFAULT NULL COMMENT 'ค่าใช้จ่ายของรายการซื้อที่ไม่เก็บสต็อก',
  `gl_vo_acct` varchar(50) DEFAULT NULL COMMENT 'เจ้าหนี้ตามใบสำคัญจ่าย (VO โอนเจ้าหนี้มา PV จ่ายออก)',
  `gl_wht_ar_acct` varchar(50) DEFAULT NULL COMMENT 'ภาษีถูกหัก ณ ที่จ่าย',
  `gl_je_next_nbr` int(11) DEFAULT 1 COMMENT 'เลขถัดไปของสมุดรายวัน',
  `gl_je_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `gl_je_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `gl_je_prefix` varchar(10) DEFAULT 'JE' COMMENT 'คำนำหน้าสมุดรายวัน',
  `gl_pc_acct` varchar(50) DEFAULT NULL COMMENT 'เงินสดย่อย (ทุกกล่องรวมกัน)',
  `gl_cash_diff_acct` varchar(50) DEFAULT NULL COMMENT 'เงินขาด/เกินบัญชี (ส่วนต่างจากการนับเงินสดย่อย)',
  `gl_pc_exp_acct` varchar(50) DEFAULT NULL COMMENT 'ค่าใช้จ่ายเงินสดย่อยที่ไม่ระบุบัญชี (ค่าใช้จ่ายเบ็ดเตล็ด)',
  `gl_wo_var_acct` varchar(50) DEFAULT NULL COMMENT 'ผลต่างต้นทุนการผลิต (งานระหว่างทำที่เหลือตอนปิดใบสั่งผลิต)',
  `gl_pc_next_nbr` int(11) DEFAULT 1 COMMENT 'เลขถัดไปของรายการเงินสดย่อย',
  `gl_pc_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่ (ดู gl_je_format)',
  `gl_pc_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป',
  `gl_pc_prefix` varchar(10) DEFAULT 'PC' COMMENT 'คำนำหน้ารายการเงินสดย่อย',
  `gl_ap_closed` tinyint(1) DEFAULT 0 COMMENT '1=ปิดระบบเจ้าหนี้',
  `gl_ar_closed` tinyint(1) DEFAULT 0 COMMENT '1=ปิดระบบลูกหนี้',
  `gl_ic_closed` tinyint(1) DEFAULT 0 COMMENT '1=ปิดระบบสินค้าคงคลัง',
  `gl_pr_closed` tinyint(1) DEFAULT 0 COMMENT '1=ปิดระบบจัดซื้อ',
  `gl_so_closed` tinyint(1) DEFAULT 0 COMMENT '1=ปิดระบบขาย',
  `gl_wo_closed` tinyint(1) DEFAULT 0 COMMENT '1=ปิดระบบผลิต',
  `gl_cm_closed` tinyint(1) DEFAULT 0 COMMENT '1=ปิดระบบเงินสด',
  `gl_all_closed` tinyint(1) DEFAULT 0 COMMENT '1=ปิดบัญชีทั้งหมด (Month-End)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ctrl_id`),
  CONSTRAINT `chk_gl_ctrl_single` CHECK (`ctrl_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GL Control';

DROP TABLE IF EXISTS `gl_trhist`;
CREATE TABLE `gl_trhist` (
  `gl_trnbr` int(11) NOT NULL AUTO_INCREMENT COMMENT 'เลข Running (Journal ID)',
  `gl_ref` varchar(50) NOT NULL COMMENT 'เอกสารอ้างอิงต้นทาง (เช่น Invoice#, RC#, Void#)',
  `gl_date` date NOT NULL COMMENT 'วันที่เกิดรายการ (Transaction Date)',
  `gl_effdate` date NOT NULL COMMENT 'วันที่ลงบัญชี (Effective Date) - มักจะใช้ปิดงบ',
  `gl_acct` varchar(50) NOT NULL COMMENT 'รหัสบัญชี (FK: ac_acct)',
  `gl_cc` varchar(50) DEFAULT NULL COMMENT 'ศูนย์ต้นทุน/แผนก (ถ้ามี)',
  `gl_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account',
  `gl_desc` varchar(255) DEFAULT NULL COMMENT 'คำอธิบายรายการ (Description)',
  `gl_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนเงิน (Debit เป็นบวก, Credit เป็นลบ)',
  `gl_type` varchar(10) NOT NULL COMMENT 'ประเภท Source ท่ี่มา (JE=Manual, AP, AR, IC)',
  `gl_userid` varchar(50) NOT NULL COMMENT 'คนทำรายการ',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`gl_trnbr`),
  KEY `idx_gl_acct_effdate` (`gl_acct`,`gl_effdate`),
  KEY `idx_gl_ref` (`gl_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='General Ledger Journal Entries';

DROP TABLE IF EXISTS `ic_ctrl`;
CREATE TABLE `ic_ctrl` (
  `ctrl_id` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'ตารางนี้มีได้แถวเดียว (ค่าตั้งต้นของระบบ)',
  `ic_neg_qty` tinyint(1) DEFAULT 0 COMMENT '1=อนุญาตให้สต็อกติดลบเวลาขายได้',
  `ic_issue_method` varchar(10) DEFAULT 'FIFO' COMMENT 'FIFO, LIFO, RANDOM',
  `ic_lot_req` tinyint(1) DEFAULT 1 COMMENT 'บังคับบิงล็อตตอนรับเข้าหรือจ่ายออก',
  `ic_auto_loc` varchar(50) DEFAULT 'STOCK' COMMENT 'คลังเริ่มต้นเวลารับของจากการผลิต',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  `ic_to_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running Transfer Order',
  `ic_to_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `ic_to_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `ic_to_prefix` varchar(10) DEFAULT 'TO' COMMENT 'TO Prefix',
  `ic_tr_next_nbr` int(11) DEFAULT 1 COMMENT 'เลขถัดไปของใบรับ/เบิก/ปรับยอด',
  `ic_tr_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `ic_tr_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `ic_tr_prefix` varchar(10) DEFAULT 'IC' COMMENT 'คำนำหน้าใบรับ/เบิก/ปรับยอด',
  `ic_pi_next_nbr` int(11) DEFAULT 1 COMMENT 'เลขถัดไปของใบตรวจนับ',
  `ic_pi_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `ic_pi_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `ic_pi_prefix` varchar(10) DEFAULT 'PI' COMMENT 'คำนำหน้าใบตรวจนับ',
  PRIMARY KEY (`ctrl_id`),
  CONSTRAINT `chk_ic_ctrl_single` CHECK (`ctrl_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory Control';

DROP TABLE IF EXISTS `ih_mstr`;
CREATE TABLE `ih_mstr` (
  `ih_inv_nbr` varchar(50) NOT NULL COMMENT 'เลขที่แจ้งหนี้',
  `ih_cm_code` varchar(50) NOT NULL,
  `ih_bill_to` varchar(50) DEFAULT NULL,
  `ih_inv_date` date NOT NULL,
  `ih_due_date` date DEFAULT NULL COMMENT 'วันครบกำหนดจ่าย (อิงจาก Credit Terms)',
  `ih_sh_nbr` varchar(50) DEFAULT NULL COMMENT 'อ้างอิงเลขที่ใบส่งของ (Shipper)',
  `ih_so_nbr` varchar(50) DEFAULT NULL COMMENT 'อ้างอิงเลขที่ใบสั่งขาย',
  `ih_po_ref` varchar(50) DEFAULT NULL COMMENT 'Customer PO',
  `ih_ref_inv` varchar(50) DEFAULT NULL COMMENT 'ใบแจ้งหนี้ที่ใบลดหนี้นี้อ้างถึง',
  `ih_reason` varchar(10) DEFAULT NULL COMMENT 'รหัสเหตุผลใบลดหนี้/ใบเพิ่มหนี้ (กรมสรรพากร CDNG01.. / DBNG01..)',
  `ih_dp_amt` decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'เงินมัดจำที่หักในใบนี้ (ก่อนภาษี) — ih_net_amt คือมูลค่าหลังหักแล้ว',
  `ih_vat_on_rcpt` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = งานบริการ ภาษีถึงกำหนดเมื่อรับชำระ (ม.78/1) ใบกำกับภาษีออกพร้อมใบเสร็จ',
  `ih_curr` varchar(3) DEFAULT 'THB' COMMENT 'สกุลเงิน',
  `ih_ex_rate` decimal(18,5) DEFAULT 1.00000 COMMENT 'อัตราแลกเปลี่ยน',
  `ih_cr_terms` varchar(20) DEFAULT NULL,
  `ih_net_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดก่อนภาษี',
  `ih_tax_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดภาษี',
  `ih_tot_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดสุทธิรวม',
  `ih_paid_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดที่ลูกค้าชำระมาแล้ว (Accumulated)',
  `ih_status` varchar(1) DEFAULT 'U' COMMENT 'U=Unpaid, P=Paid, C=Canceled',
  `ih_type` varchar(10) DEFAULT 'IN' COMMENT 'IN=Invoice, CM=Credit Memo',
  `ih_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ih_inv_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Invoice Header';

DROP TABLE IF EXISTS `ihd_det`;
CREATE TABLE `ihd_det` (
  `ihd_inv_nbr` varchar(50) NOT NULL COMMENT 'เลขที่แจ้งหนี้ (FK -> ih_inv_nbr)',
  `ihd_line` int(11) NOT NULL COMMENT 'ลำดับบรรทัด',
  `ihd_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `ihd_desc` varchar(255) DEFAULT NULL COMMENT 'รายละเอียด (ใบแจ้งหนี้/ใบลดหนี้ที่ทำเอง หรือรายการที่ไม่มีใน pt_mstr)',
  `ihd_qty_inv` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนที่เปิดบิล',
  `ihd_um` varchar(10) NOT NULL,
  `ihd_price` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ราคาขายสินค้า',
  `ihd_disc_pct` decimal(15,5) DEFAULT 0.00000,
  `ihd_line_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดสุทธิบรรทัดนี้',
  `ihd_tax_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษีที่ใช้คิดเฉพาะตัวนี้',
  `ihd_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชีรายได้ที่ใช้ลงบันทึก (GL)',
  `ihd_cc` varchar(50) DEFAULT NULL COMMENT 'ศุนย์ต้นทุน/แผนก (Cost Center)',
  `ihd_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account',
  `ihd_so_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ SO อ้างอิง',
  `ihd_sod_line` int(11) DEFAULT NULL COMMENT 'ลำดับบน SO (เพื่อตรวจสอบ)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ihd_inv_nbr`,`ihd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Invoice Detail';

DROP TABLE IF EXISTS `is_mstr`;
CREATE TABLE `is_mstr` (
  `is_status` varchar(20) NOT NULL COMMENT 'รหัสสถานะ (AC=Active, HD=Hold, RJ=Reject, IN=Inspect)',
  `is_desc` varchar(100) NOT NULL COMMENT 'คำอธิบายสถานะ',
  `is_avail` tinyint(1) DEFAULT 1 COMMENT '1 = อนุญาตให้เบิกไปขาย/ผลิตได้',
  `is_nettable` tinyint(1) DEFAULT 1 COMMENT '1 = ให้นับเป็นยอดคงคลังในรายงานสรุปด้วย',
  `is_overissue` tinyint(1) DEFAULT 0 COMMENT '1 = ยอมให้เบิกเกินยอดที่มีอยู่ได้ (ยอดติดลบ)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`is_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory Status Master';

DROP TABLE IF EXISTS `lc_mstr`;
CREATE TABLE `lc_mstr` (
  `lc_rc_nbr` varchar(50) NOT NULL COMMENT 'อ้างอิงใบรับของ (rc_nbr)',
  `lc_line` int(11) NOT NULL DEFAULT 1 COMMENT 'บรรทัด',
  `lc_ref` varchar(50) DEFAULT NULL COMMENT 'เลขที่เอกสาร (เช่น เลข AWB / B/L / ใบขนสินค้า)',
  `lc_ap_ref` varchar(50) DEFAULT NULL COMMENT 'อ้างอิง AP Voucher ของบริษัทชิปปิ้ง',
  `lc_vd_code` varchar(50) DEFAULT NULL COMMENT 'บริษัทขนส่ง/ชิปปิ้งนำเข้า',
  `lc_cost_type` varchar(20) NOT NULL DEFAULT 'FREIGHT' COMMENT 'FREIGHT, CLEAR, DUTY, INSURANCE, OTHER',
  `lc_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดเงินที่ต้องปันส่วน',
  `lc_allocated` tinyint(1) DEFAULT 0 COMMENT '1=ปันเข้าต้นทุนสินค้าแล้ว',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`lc_rc_nbr`,`lc_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Landed Cost / Import Expenses';

DROP TABLE IF EXISTS `ld_det`;
CREATE TABLE `ld_det` (
  `ld_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `ld_site` varchar(50) NOT NULL COMMENT 'รหัสสาขา',
  `ld_loc` varchar(50) NOT NULL COMMENT 'รหัสหลุม/จุดเก็บของ (FK -> loc_loc)',
  `ld_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า (FK -> pt_part)',
  `ld_lot` varchar(50) NOT NULL DEFAULT '' COMMENT 'เลขที่ล็อต ถ้าไม่คุมใส่เป็นค่าว่าง',
  `ld_ref` varchar(50) NOT NULL DEFAULT '' COMMENT 'อ้างอิง (S/N หรือ พาเลต) ข้อความกำกับแถว แก้ได้ ไม่ใช่ส่วนของคีย์',
  `ld_qty_oh` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'Quantity On Hand (ยอดในหลุม)',
  `ld_qty_all` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'Quantity Allocated (ยอดจองแล้ว)',
  `ld_pack_qty` decimal(15,5) NOT NULL DEFAULT 1.00000 COMMENT 'จำนวนต่อแพ็ค/มัดของแถวนี้ ใส่ตอนรับ (ค่าเริ่มต้น 1) แก้ได้',
  `ld_cost` decimal(18,5) DEFAULT 0.00000 COMMENT 'ต้นทุนต่อหน่วยของล็อตนี้ (กรณี FIFO/Actual Cost)',
  `ld_status` varchar(20) DEFAULT 'ACTIVE' COMMENT 'ACTIVE, HOLD, EXPIRED, QUARANTINE',
  `ld_expire` date DEFAULT NULL COMMENT 'วันหมดอายุของระบุล็อตนี้',
  `ld_mfg_date` date DEFAULT NULL COMMENT 'วันที่ผลิต/วันที่รับเข้าล็อตนี้',
  `ld_grade` varchar(20) DEFAULT NULL COMMENT 'เกรดสินค้า',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ld_site`,`ld_loc`,`ld_part`,`ld_lot`),
  UNIQUE KEY `uk_ld_id` (`ld_id`),
  KEY `idx_ld_part_site` (`ld_part`,`ld_site`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory Balances (Location/Lot Details)';

DROP TABLE IF EXISTS `lg_hist`;
CREATE TABLE `lg_hist` (
  `lg_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `lg_userid` varchar(50) NOT NULL COMMENT 'ชื่อผู้ใช้ที่พยายามเข้าระบบ',
  `lg_ip` varchar(50) DEFAULT NULL,
  `lg_ok` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = สำเร็จ, 0 = ไม่สำเร็จ',
  `lg_agent` varchar(255) DEFAULT NULL COMMENT 'User agent',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`lg_id`),
  KEY `idx_lg_user` (`lg_userid`,`created_at`),
  KEY `idx_lg_ip` (`lg_ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login History';

DROP TABLE IF EXISTS `loc_mstr`;
CREATE TABLE `loc_mstr` (
  `loc_site` varchar(50) NOT NULL COMMENT 'รหัสสาขา',
  `loc_loc` varchar(50) NOT NULL COMMENT 'รหัสโซน/จุดจัดเก็บ',
  `loc_desc` varchar(255) DEFAULT NULL COMMENT 'รายละเอียด เช่น โซนวัตถุดิบ, โซนของเสีย',
  `loc_type` varchar(20) DEFAULT 'S' COMMENT 'ประเภท (S=Single Item, M=Multi Item)',
  `loc_status` varchar(20) DEFAULT 'ACTIVE' COMMENT 'ACTIVE, HOLD, RESTRICT',
  `loc_project` varchar(50) DEFAULT NULL COMMENT 'ผูกกับแผนกหรือโปรเจค',
  `loc_nettable` tinyint(1) DEFAULT 1 COMMENT '1 = ให้นับเป็นยอดคงคลังพร้อมใช้',
  `loc_mrp` tinyint(1) DEFAULT 1 COMMENT '1 = นำไปคำนวณเติมของ (MRP)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`loc_site`,`loc_loc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory Location Master';

DROP TABLE IF EXISTS `lot_mstr`;
CREATE TABLE `lot_mstr` (
  `lot_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `lot_lot` varchar(50) NOT NULL COMMENT 'รหัสลำดับล็อต/Serial',
  `lot_vend_lot` varchar(50) DEFAULT NULL COMMENT 'ล็อตของ Supplier',
  `lot_status` varchar(20) DEFAULT 'ACTIVE' COMMENT 'ACTIVE, HOLD, EXPIRED, RESTRICTED',
  `lot_mfg_date` date DEFAULT NULL COMMENT 'วันที่ผลิต',
  `lot_expire_date` date DEFAULT NULL COMMENT 'วันหมดอายุ',
  `lot_rcv_date` date DEFAULT NULL COMMENT 'วันที่รับของเข้าคลัง',
  `lot_grade` varchar(20) DEFAULT NULL COMMENT 'เกรดผลลัพธ์ (เช่น A, B, Rework)',
  `lot_rmks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`lot_part`,`lot_lot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lot / Serial Detail Master';

DROP TABLE IF EXISTS `mch_mstr`;
CREATE TABLE `mch_mstr` (
  `mch_code` varchar(50) NOT NULL COMMENT 'รหัสเครื่องจักร',
  `mch_desc` varchar(100) NOT NULL COMMENT 'ชื่อ/คำอธิบายเครื่องจักร',
  `mch_wkctr` varchar(50) NOT NULL COMMENT 'รหัส Work Center (FK)',
  `mch_brand` varchar(50) DEFAULT NULL COMMENT 'ยี่ห้อ',
  `mch_model` varchar(50) DEFAULT NULL COMMENT 'รุ่น',
  `mch_serial` varchar(50) DEFAULT NULL COMMENT 'Serial Number',
  `mch_asset` varchar(50) DEFAULT NULL COMMENT 'รหัสสินทรัพย์ถาวร (fa_mstr)',
  `mch_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`mch_code`),
  KEY `idx_mch_wkctr` (`mch_wkctr`),
  KEY `idx_mch_asset` (`mch_asset`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `mnu_det`;
CREATE TABLE `mnu_det` (
  `mnu_menu` varchar(50) NOT NULL COMMENT 'รหัสเมนูแม่ (เช่น 1, 1.4, 3.1, 7)',
  `mnu_code` varchar(50) NOT NULL COMMENT 'รหัสเมนูย่อยอ้างอิงถึง (เช่น 1.4.1 หรือ FK -> prog_code)',
  `mnu_name` varchar(100) NOT NULL COMMENT 'ชื่อเมนูที่จะให้แสดงบนหน้าเว็บ (Display Name)',
  `mnu_access` varchar(255) DEFAULT '*' COMMENT 'รหัสโปรแกรม (prog_mstr) ที่ต้องมีสิทธิ์ดู, * = ทุกคนที่ล็อกอิน',
  `mnu_icon` varchar(50) DEFAULT NULL COMMENT 'คลาสของ Icon (เช่น fas fa-shopping-cart, bi-file-earmark)',
  `mnu_link` varchar(255) DEFAULT '#' COMMENT 'URL หรือ Path ที่จะลิ้งก์ไปหน้าจอ (เช่น /sales/so-entry)',
  `mnu_seq` int(11) DEFAULT 0 COMMENT 'ลำดับการแสดงผล (0, 10, 20...)',
  `mnu_target` varchar(20) DEFAULT '_self' COMMENT '_self (เปิดหน้าเดิม), _blank (ขึ้นแท็บใหม่)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`mnu_menu`,`mnu_code`),
  KEY `idx_mnu_code` (`mnu_code`),
  KEY `idx_mnu_seq` (`mnu_menu`,`mnu_seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='UI Menu Navigation Detail';

DROP TABLE IF EXISTS `mrp_det`;
CREATE TABLE `mrp_det` (
  `mrp_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `mrp_site` varchar(50) NOT NULL COMMENT 'รหัสสาขา',
  `mrp_type` varchar(20) NOT NULL COMMENT 'ประเภท (Planned PO / Planned WO)',
  `mrp_qty` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนที่แนะนำ',
  `mrp_rel_date` date DEFAULT NULL COMMENT 'วันที่ควรเริ่มสั่ง (Release Date)',
  `mrp_due_date` date NOT NULL COMMENT 'วันที่ต้องการของ (Due Date)',
  `mrp_ref` varchar(50) DEFAULT NULL COMMENT 'อ้างอิงเอกสาร (ถ้ามี)',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`mrp_part`,`mrp_site`,`mrp_due_date`,`mrp_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `pc_mstr`;
CREATE TABLE `pc_mstr` (
  `pc_list` varchar(50) NOT NULL COMMENT 'รหัส Price List',
  `pc_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `pc_curr` varchar(3) NOT NULL DEFAULT 'THB' COMMENT 'สกุลเงินของราคานี้',
  `pc_um` varchar(10) NOT NULL COMMENT 'หน่วยนับ',
  `pc_start` date NOT NULL COMMENT 'วันเริ่มโปรโมชั่น',
  `pc_expire` date DEFAULT NULL COMMENT 'วันหมดอายุโปรโมชั่น',
  `pc_amt_type` varchar(1) DEFAULT 'P' COMMENT 'P=Price (ระบุราคาตายตัว), D=Discount (ให้ส่วนลดจาก Base Price)',
  `pc_price` decimal(18,5) DEFAULT 0.00000 COMMENT 'ราคาขายพิเศษ',
  `pc_disc_pct` decimal(10,4) DEFAULT 0.0000 COMMENT 'เปอร์เซ็นต์ส่วนลด (%)',
  `pc_min_qty` decimal(15,5) DEFAULT 1.00000 COMMENT 'ขั้นต่ำที่ต้องซื้อเพื่อให้ได้ราคานี้',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`pc_list`,`pc_part`,`pc_curr`,`pc_um`,`pc_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales Price List / Promotion Master';

DROP TABLE IF EXISTS `pi_mstr`;
CREATE TABLE `pi_mstr` (
  `pi_batch` varchar(50) NOT NULL COMMENT 'รหัสเอกสารการนับสต็อก (เช่น PI-2026-001)',
  `pi_site` varchar(50) NOT NULL COMMENT 'สาขาที่ตรวจนับ',
  `pi_desc` varchar(100) DEFAULT NULL COMMENT 'คำอธิบาย (เช่น ตรวจนับประจำปี 2026)',
  `pi_date` date NOT NULL COMMENT 'วันที่ดำเนินการนับ',
  `pi_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, F=Frozen(อายัตสต็อก), P=Posted(ปรับปรุงยอดแล้ว), C=Canceled',
  `pi_rsn_code` varchar(50) DEFAULT NULL COMMENT 'รหัสเหตุผล (เช่น OPEN ยอดยกมา) เลือกบัญชีคู่ของการปรับยอด',
  `pi_locs` varchar(1000) DEFAULT NULL COMMENT 'ขอบเขตการนับ: ตำแหน่งที่นับ คั่นด้วย , (ว่าง = ทั้งคลัง)',
  `pi_freeze_date` datetime DEFAULT NULL COMMENT 'วันที่เวลาทำการอายัตสต็อกตั้งต้น',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`pi_batch`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Physical Inventory Batch';

DROP TABLE IF EXISTS `pi_tag`;
CREATE TABLE `pi_tag` (
  `pit_batch` varchar(50) NOT NULL COMMENT 'รหัสการนับ (FK: pi_batch)',
  `pit_tag` int(11) NOT NULL COMMENT 'เลขทะเบียน Tag',
  `pit_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `pit_loc` varchar(50) NOT NULL COMMENT 'หลุมที่อยู่',
  `pit_lot` varchar(50) DEFAULT '' COMMENT 'เลขล็อต',
  `pit_sys_qty` decimal(15,5) DEFAULT 0.00000 COMMENT 'จำนวนในระบบ ณ ตอน Freeze',
  `pit_count_qty` decimal(15,5) DEFAULT NULL COMMENT 'จำนวนที่นับได้จริง (NULL = ยังไม่นับ)',
  `pit_variance` decimal(15,5) DEFAULT 0.00000 COMMENT 'ผลต่าง (count - sys)',
  `pit_cost` decimal(18,5) DEFAULT NULL COMMENT 'ต้นทุน/หน่วยของของที่นับเพิ่ม (ยอดยกมา) ว่าง = ต้นทุนสินค้า',
  `pit_counter` varchar(100) DEFAULT NULL COMMENT 'ชื่อผู้นับ (ตามที่เขียนบนป้าย)',
  `pit_count1` decimal(15,5) DEFAULT NULL COMMENT 'ผลนับครั้งแรก เมื่อสั่งนับซ้ำ',
  `pit_counter1` varchar(100) DEFAULT NULL COMMENT 'ผู้นับครั้งแรก เมื่อสั่งนับซ้ำ',
  `pit_status` varchar(1) DEFAULT 'O' COMMENT 'O=ยังไม่นับ, C=นับแล้ว, V=ยกเลิก',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`pit_batch`,`pit_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Physical Inventory Tags';

DROP TABLE IF EXISTS `pl_mstr`;
CREATE TABLE `pl_mstr` (
  `pl_prod_line` varchar(50) NOT NULL COMMENT 'รหัสกลุ่มสินค้า',
  `pl_desc` varchar(255) NOT NULL,
  `pl_inventory_type` varchar(2) DEFAULT NULL COMMENT 'ประเภทสต็อก FG สินค้าสำเร็จรูป RM วัตถุดิบ FA สินทรัพย์ถาวร',
  `pl_fa_category` varchar(50) DEFAULT NULL COMMENT 'หมวดสินทรัพย์ถาวร (สายประเภท FA)',
  `pl_inv_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชีสินค้าคงคลัง (GL)',
  `pl_inv_sub` varchar(2) DEFAULT NULL COMMENT 'Inv Sub',
  `pl_sls_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชีรายได้จากการขาย (GL)',
  `pl_sls_sub` varchar(2) DEFAULT NULL COMMENT 'Sls Sub',
  `pl_cogs_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชีต้นทุนขาย (GL)',
  `pl_cogs_sub` varchar(2) DEFAULT NULL COMMENT 'Cogs Sub',
  `pl_pur_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชีการซื้อ (GL)',
  `pl_pur_sub` varchar(2) DEFAULT NULL COMMENT 'Pur Sub',
  `pl_wip_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชีงานระหว่างทำ (WIP) สำหรับผลิต',
  `pl_wip_sub` varchar(2) DEFAULT NULL COMMENT 'Wip Sub',
  `pl_tax_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษีเริ่มต้นของกลุ่มนี้',
  `pl_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`pl_prod_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product Line / Category Master';

DROP TABLE IF EXISTS `po_ctrl`;
CREATE TABLE `po_ctrl` (
  `ctrl_id` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'ตารางนี้มีได้แถวเดียว (ค่าตั้งต้นของระบบ)',
  `po_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ถัดไปของ PO',
  `po_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `po_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `po_prefix` varchar(10) DEFAULT 'PO' COMMENT 'PO Prefix (e.g. PO26-)',
  `po_rcv_tol` decimal(5,2) DEFAULT 0.00 COMMENT 'ความคลาดเคลื่อนตอนรับของ (+%)',
  `po_pr_req` tinyint(1) DEFAULT 0 COMMENT '1=ต้องมี PR ถึงจะเปิด PO ได้',
  `po_pr_next_nbr` int(11) DEFAULT 1,
  `po_pr_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `po_pr_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  `po_rc_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running Receipt',
  `po_rc_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `po_rc_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `po_rc_prefix` varchar(10) DEFAULT 'RC' COMMENT 'Receipt Prefix',
  `po_pr_prefix` varchar(10) DEFAULT 'PR',
  `po_rt_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ใบส่งคืนสินค้า',
  `po_rt_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่ใบส่งคืนสินค้า',
  `po_rt_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป',
  `po_rt_prefix` varchar(10) DEFAULT 'RT' COMMENT 'Purchase return prefix',
  PRIMARY KEY (`ctrl_id`),
  CONSTRAINT `chk_po_ctrl_single` CHECK (`ctrl_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Purchase Order Control';

DROP TABLE IF EXISTS `po_mstr`;
CREATE TABLE `po_mstr` (
  `po_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบสั่งซื้อ',
  `po_vd_code` varchar(50) NOT NULL COMMENT 'รหัสผู้จำหน่าย (FK -> vd_code)',
  `po_ship_to` varchar(50) DEFAULT NULL COMMENT 'รหัสสถานที่ส่งของ (FK -> ad_addr)',
  `po_bill_to` varchar(50) DEFAULT NULL COMMENT 'รหัสสถานที่วางบิล (FK -> ad_addr)',
  `po_date` date NOT NULL COMMENT 'วันที่เปิดใบสั่งซื้อ',
  `po_due_date` date DEFAULT NULL COMMENT 'วันกำหนดส่งของโดยรวม',
  `po_sched` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = ใบสั่งซื้อแบบกำหนดส่ง (ส่งหลายงวดตาม schd_det)',
  `po_status` varchar(1) DEFAULT 'P' COMMENT 'P=Pending, A=Approved, C=Closed, X=Canceled',
  `po_buyer` varchar(50) DEFAULT NULL COMMENT 'พนักงานจัดซื้อ',
  `po_cr_terms` varchar(20) DEFAULT NULL COMMENT 'เงื่อนไขการจ่ายเงิน',
  `po_ship_via` varchar(50) DEFAULT NULL COMMENT 'วิธีการจัดส่ง (เช่น DHL, รถบริษัท)',
  `po_curr` varchar(3) DEFAULT 'THB' COMMENT 'สกุลเงิน',
  `po_ex_rate` decimal(18,5) DEFAULT 1.00000 COMMENT 'อัตราแลกเปลี่ยน ณ วันที่เปิด PO',
  `po_ex_date` date DEFAULT NULL,
  `po_tax_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษี',
  `po_tax_pct` decimal(15,5) DEFAULT 0.00000 COMMENT 'อัตราภาษี',
  `po_tax_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดภาษีรวม',
  `po_tot_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดสุทธิเจ้าหนี้',
  `po_remarks` text DEFAULT NULL,
  `po_print_cnt` int(11) DEFAULT 0 COMMENT 'จำนวนครั้งที่พิมพ์เอกสารออกไปแล้ว',
  `po_apv_by` varchar(50) DEFAULT NULL COMMENT 'ผู้อนุมัติ',
  `po_apv_date` datetime DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`po_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Purchase Order Master';

DROP TABLE IF EXISTS `pod_det`;
CREATE TABLE `pod_det` (
  `pod_nbr` varchar(50) NOT NULL COMMENT 'เลขใบสั่งซื้อ (FK -> po_nbr)',
  `pod_line` int(11) NOT NULL COMMENT 'ลำดับบรรทัด (1, 2, 3...)',
  `pod_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `pod_desc` varchar(255) DEFAULT NULL COMMENT 'ชื่อสินค้า (เผื่อมีการแก้ไขเฉพาะบรรทัด)',
  `pod_qty_ord` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนสั่ง',
  `pod_qty_rcvd` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนรับเข้าแล้ว (Accumulated)',
  `pod_um` varchar(10) NOT NULL COMMENT 'หน่วยนับ',
  `pod_price` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ราคาต่อหน่วย',
  `pod_disc_pct` decimal(15,5) DEFAULT 0.00000 COMMENT 'ส่วนลด(%) บรรทัดนี้',
  `pod_line_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดรวมบรรทัดนี้ (หลังหักส่วนลด)',
  `pod_req_date` date DEFAULT NULL COMMENT 'วันที่ต้องการของเฉพาะบรรทัดนี้',
  `pod_site` varchar(50) DEFAULT NULL COMMENT 'สาขาที่รับของเข้า',
  `pod_loc` varchar(50) DEFAULT NULL COMMENT 'จุดรับของเข้า',
  `pod_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชี GL (กรณีซื้อของใช้ทั่วไป ไม่เข้าสต็อก)',
  `pod_cc` varchar(50) DEFAULT NULL COMMENT 'ศูนย์ต้นทุน/แผนก',
  `pod_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account',
  `pod_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, C=Closed, X=Canceled',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`pod_nbr`,`pod_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Purchase Order Detail';

DROP TABLE IF EXISTS `pr_mstr`;
CREATE TABLE `pr_mstr` (
  `pr_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบ PR',
  `pr_req_by` varchar(50) NOT NULL COMMENT 'ผู้ร้องขอ (User ID)',
  `pr_dept` varchar(50) DEFAULT NULL COMMENT 'แผนกที่ร้องขอ',
  `pr_date` date NOT NULL COMMENT 'วันที่ขอ',
  `pr_status` varchar(1) DEFAULT 'P' COMMENT 'P=Pending Approval, A=Approved, O=Ordered (แปลงเป็น PO แล้ว), X=Canceled',
  `pr_apv_by` varchar(50) DEFAULT NULL COMMENT 'ผู้อนุมัติ',
  `pr_apv_date` datetime DEFAULT NULL,
  `pr_ex_rate` decimal(15,5) DEFAULT 1.00000,
  `pr_ex_date` date DEFAULT NULL,
  `pr_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  `pr_tax_pct` decimal(18,2) DEFAULT 0.00,
  `pr_tax_amt` decimal(18,2) DEFAULT 0.00,
  `pr_tot_amt` decimal(18,2) DEFAULT 0.00,
  PRIMARY KEY (`pr_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Purchase Requisition Header';

DROP TABLE IF EXISTS `prd_det`;
CREATE TABLE `prd_det` (
  `prd_nbr` varchar(50) NOT NULL,
  `prd_line` int(11) NOT NULL,
  `prd_part` varchar(50) NOT NULL,
  `prd_desc` varchar(255) DEFAULT NULL COMMENT 'รายละเอียดเพิ่มเติม/สเปค',
  `prd_qty_req` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่ขอซื้อ',
  `prd_qty_ord` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่จัดซื้อเปิด PO ไปแล้ว (Accumulated)',
  `prd_um` varchar(10) NOT NULL,
  `prd_req_date` date NOT NULL COMMENT 'วันที่ต้องการใช้ของบรรทัดนี้',
  `prd_est_cost` decimal(18,5) DEFAULT 0.00000 COMMENT 'ราคาประเมินต่อหน่วยเบื้องต้น',
  `prd_pref_vd` varchar(50) DEFAULT NULL COMMENT 'ซัพพลายเออร์ที่แนะนำ',
  `prd_cc` varchar(50) DEFAULT NULL COMMENT 'ชาร์จเข้าแผนกไหน',
  `prd_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account',
  `prd_po_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ PO ที่ผูกไปแล้ว',
  `prd_pod_line` int(11) DEFAULT NULL COMMENT 'บรรทัดใน PO',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  `prd_line_amt` decimal(18,2) DEFAULT 0.00,
  PRIMARY KEY (`prd_nbr`,`prd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Purchase Requisition Detail';

DROP TABLE IF EXISTS `prog_mstr`;
CREATE TABLE `prog_mstr` (
  `prog_code` varchar(50) NOT NULL COMMENT 'รหัสหน้าจอ (เช่น uoms, sales-orders) ใช้ใน #[Program] และ mnu_det.mnu_access',
  `prog_name` varchar(100) NOT NULL COMMENT 'ชื่อหน้าจอ (เช่น บันทึกใบสั่งขาย)',
  `prog_module` varchar(50) DEFAULT NULL COMMENT 'โมดูล (เช่น SO, PO, AP, AR, IC)',
  `prog_type` varchar(20) DEFAULT 'MENU' COMMENT 'MENU, REPORT, PROCESS',
  `prog_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`prog_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Program / Screen Master';

DROP TABLE IF EXISTS `ps_mstr`;
CREATE TABLE `ps_mstr` (
  `ps_par` varchar(50) NOT NULL COMMENT 'รหัสสินค้าแม่ (Parent Item - FK: pt_part)',
  `ps_comp` varchar(50) NOT NULL COMMENT 'รหัสวัตถุดิบลูก (Component Item - FK: pt_part)',
  `ps_qty_per` decimal(15,5) NOT NULL DEFAULT 1.00000 COMMENT 'จำนวนลูกที่ใช้ต่อแม่ 1 หน่วย',
  `ps_scrap` decimal(5,2) DEFAULT 0.00 COMMENT 'เปอร์เซ็นต์เผื่อเสีย (Scrap %)',
  `ps_start` date DEFAULT NULL COMMENT 'วันที่เริ่มใช้สูตรนี้ (Effectivity Start)',
  `ps_end` date DEFAULT NULL COMMENT 'วันที่เลิกใช้สูตรนี้ (Effectivity End)',
  `ps_rmks` varchar(255) DEFAULT NULL COMMENT 'หมายเหตุ',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ps_par`,`ps_comp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Product Structure / BOM Master';

DROP TABLE IF EXISTS `pt_mstr`;
CREATE TABLE `pt_mstr` (
  `pt_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `pt_desc1` varchar(255) NOT NULL COMMENT 'ชื่อสินค้าบรรทัดแรก',
  `pt_desc2` varchar(255) DEFAULT NULL COMMENT 'ชื่อสินค้าบรรทัดสอง (เพิ่มเติม)',
  `pt_ad_code` varchar(50) DEFAULT NULL COMMENT 'รหัสเพิ่มเติม (รหัสสำรอง เช่น รหัสเดิม รหัสลูกค้า)',
  `pt_um` varchar(10) NOT NULL COMMENT 'หน่วยนับหลัก (FK -> um_um)',
  `pt_prod_line` varchar(50) NOT NULL COMMENT 'กลุ่มบัญชี Financial (FK -> pl_prod_line)',
  `pt_item_type` varchar(20) DEFAULT NULL COMMENT 'ประเภทชิ้นงาน (FK -> ptt_type)',
  `pt_item_group` varchar(50) DEFAULT NULL COMMENT 'หมวดหมู่จัดซื้อ/ขาย (FK -> ptg_group)',
  `pt_part_type` varchar(20) DEFAULT 'M' COMMENT 'M=Manufacture (ผลิตเอง), P=Purchased (ซื้อมา)',
  `pt_status` varchar(20) DEFAULT 'ACTIVE' COMMENT 'ACTIVE, OBSOLETE, PHASE-OUT',
  `pt_abc` varchar(1) DEFAULT 'C' COMMENT 'ABC Classification หมวดความสำคัญตามมูลค่า',
  `pt_lot_ser` varchar(1) DEFAULT '' COMMENT 'L=Control By Lot, S=Control By Serial, ค่าว่าง=ไม่คุม',
  `pt_inventory` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'เก็บสต็อก: 1 = มียอดคงเหลือและลง tr_hist, 0 = ไม่เก็บสต็อก (ค่าบริการ วัสดุสิ้นเปลือง)',
  `pt_routing` varchar(50) DEFAULT NULL COMMENT 'เส้นทางการผลิตหลัก (ถ้ามี)',
  `pt_bom_code` varchar(50) DEFAULT NULL COMMENT 'รหัสสูตรการผลิต (BOM)',
  `pt_price` decimal(18,5) DEFAULT 0.00000 COMMENT 'ราคาขายมาตรฐาน',
  `pt_cost_method` varchar(10) DEFAULT 'AVG' COMMENT 'AVG, STD, FIFO (วิธีคิดต้นทุน)',
  `pt_cost` decimal(18,5) DEFAULT 0.00000 COMMENT 'ต้นทุนมาตรฐาน (Standard Cost)',
  `pt_net_wt` decimal(15,5) DEFAULT 0.00000 COMMENT 'น้ำหนักสุทธิ',
  `pt_wt_um` varchar(10) DEFAULT 'KG' COMMENT 'หน่วยของน้ำหนัก (FK -> um_um)',
  `pt_dim_l` decimal(15,5) DEFAULT 0.00000 COMMENT 'ขนาด: ยาว',
  `pt_dim_w` decimal(15,5) DEFAULT 0.00000 COMMENT 'ขนาด: กว้าง',
  `pt_dim_h` decimal(15,5) DEFAULT 0.00000 COMMENT 'ขนาด: สูง',
  `pt_dim_um` varchar(10) DEFAULT 'CM' COMMENT 'หน่วยของขนาด',
  `pt_article` varchar(100) DEFAULT NULL COMMENT 'รหัสบาร์โค้ด / EAN / UPC',
  `pt_drw_ref` varchar(100) DEFAULT NULL COMMENT 'เลขที่แบบร่าง (Drawing Ref) กรณีจ้างผลิต',
  `pt_lead_time` int(11) DEFAULT 0 COMMENT 'ระยะเวลาสั่งของตามมาตรฐาน (วัน)',
  `pt_max_qty` decimal(15,5) DEFAULT 0.00000 COMMENT 'เพดานการเก็บสูงสุด',
  `pt_min_ord` decimal(15,5) NOT NULL DEFAULT 0 COMMENT 'จำนวนสั่งขั้นต่ำ',
  `pt_max_ord` decimal(15,5) NOT NULL DEFAULT 0 COMMENT 'จำนวนสั่งสูงสุดต่อรายการ 0 ไม่จำกัด',
  `pt_mult_ord` decimal(15,5) NOT NULL DEFAULT 1 COMMENT 'จำนวนทวีคูณในการสั่ง',
  `pt_pack_qty` decimal(15,5) DEFAULT 1.00000 COMMENT 'จำนวนต่อแพ็ค/พาเลซ',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`pt_part`),
  KEY `idx_pt_article` (`pt_article`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Part / Item Master';

DROP TABLE IF EXISTS `pt_site_mstr`;
CREATE TABLE `pt_site_mstr` (
  `pt_site` varchar(50) NOT NULL COMMENT 'สาขา (FK -> si_site)',
  `pt_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า (FK -> pt_part)',
  `pt_status` varchar(20) DEFAULT 'ACTIVE' COMMENT 'ถ้าสาขานี้ไม่ขายจะถูกระงับแยก',
  `pt_roq` decimal(15,5) DEFAULT 0.00000 COMMENT 'Reorder Quantity (ปริมาณสั่งซื้อเมื่อถึงจุด)',
  `pt_rop` decimal(15,5) DEFAULT 0.00000 COMMENT 'Reorder Point (จุดยิงสั่งซื้อ - สีเหลือง)',
  `pt_safe_stk` decimal(15,5) DEFAULT 0.00000 COMMENT 'Safety Stock (สต็อกปลอดภัย - สีแดง)',
  `pt_buyer` varchar(50) DEFAULT NULL COMMENT 'รหัสพนักงานจัดซื้อที่ดูแลของสาขานี้',
  `pt_sourcing` varchar(50) DEFAULT NULL COMMENT 'นโยบายการจัดหา (เช่น ทำเอง, โอนจากสาขาแม่, ซื้อจากซัพฯ)',
  `pt_mfg_lead_time` int(11) DEFAULT 0 COMMENT 'ระยะเวลาผลิต (ถ้าผลิตเอง)',
  `pt_pur_lead_time` int(11) DEFAULT 0 COMMENT 'ระยะเวลาสั่งซื้อ (ถ้าซื้อมา)',
  `pt_iss_loc` varchar(50) DEFAULT NULL COMMENT 'จุดเบิกของ (Issue Location) ประจำ',
  `pt_rcv_loc` varchar(50) DEFAULT NULL COMMENT 'จุดรับของ (Receiving Location) ประจำ',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`pt_site`,`pt_part`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Item-Site Planning Master (MRP Parameters)';

DROP TABLE IF EXISTS `ptg_mstr`;
CREATE TABLE `ptg_mstr` (
  `ptg_group` varchar(50) NOT NULL COMMENT 'รหัสกลุ่มสินค้าย่อย',
  `ptg_desc` varchar(255) NOT NULL,
  `ptg_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ptg_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Item Group Master';

DROP TABLE IF EXISTS `ptt_mstr`;
CREATE TABLE `ptt_mstr` (
  `ptt_type` varchar(20) NOT NULL COMMENT 'รหัสประเภท (RM, FG, SFG, SP, CO)',
  `ptt_desc` varchar(100) NOT NULL COMMENT 'คำอธิบาย (วัตถุดิบ, สินค้าสำเร็จรูป)',
  `ptt_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ptt_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Item Type Master';

DROP TABLE IF EXISTS `pv_mstr`;
CREATE TABLE `pv_mstr` (
  `pv_id` int(11) NOT NULL AUTO_INCREMENT,
  `pv_code` int(11) NOT NULL,
  `pv_name_th` varchar(150) NOT NULL,
  `pv_name_en` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`pv_id`),
  UNIQUE KEY `ux_pv_code` (`pv_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `qc_hist`;
CREATE TABLE `qc_hist` (
  `qc_test_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบ QC',
  `qc_ref_doc` varchar(50) DEFAULT NULL COMMENT 'อ้างอิงเอกสารรับของ(PO) หรือรับจากการผลิต(WO)',
  `qc_part` varchar(50) NOT NULL COMMENT 'สินค้า',
  `qc_lot` varchar(50) DEFAULT '' COMMENT 'เลขล็อต',
  `qc_qty_inspect` decimal(15,5) DEFAULT 0.00000 COMMENT 'จำนวนที่สุ่มตรวจ',
  `qc_qty_pass` decimal(15,5) DEFAULT 0.00000 COMMENT 'ของดี (รับเข้าคลัง)',
  `qc_qty_fail` decimal(15,5) DEFAULT 0.00000 COMMENT 'ของเสีย',
  `qc_result` varchar(20) DEFAULT 'PASS' COMMENT 'PASS, FAIL, REWORK',
  `qc_inspector` varchar(50) DEFAULT NULL COMMENT 'ผู้ตรวจสอบ',
  `qc_date` datetime DEFAULT NULL,
  `qc_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`qc_test_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='QC Inspection History';

DROP TABLE IF EXISTS `qt_mstr`;
CREATE TABLE `qt_mstr` (
  `qt_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบเสนอราคา',
  `qt_cm_code` varchar(50) NOT NULL COMMENT 'รหัสลูกค้า (หรือ Prospect)',
  `qt_date` date NOT NULL COMMENT 'วันที่เสนอราคา',
  `qt_expire` date NOT NULL COMMENT 'วันที่ยืนราคาถึง',
  `qt_curr` varchar(3) DEFAULT 'THB',
  `qt_ex_rate` decimal(15,5) DEFAULT 1.00000,
  `qt_ex_date` date DEFAULT NULL,
  `qt_tax_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษี',
  `qt_tax_pct` decimal(18,2) DEFAULT 0.00,
  `qt_cr_terms` varchar(20) DEFAULT NULL,
  `qt_net_amt` decimal(18,5) DEFAULT 0.00000,
  `qt_tax_amt` decimal(18,5) DEFAULT 0.00000,
  `qt_tot_amt` decimal(18,5) DEFAULT 0.00000,
  `qt_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, W=Won (ได้งาน->แปลงเป็น SO), L=Lost, C=Canceled',
  `qt_so_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ SO กรณีที่ลูกค้าตกลงซื้อ',
  `qt_remarks` text DEFAULT NULL,
  `qt_sales_rep` varchar(50) DEFAULT NULL COMMENT 'พนักงานขาย',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`qt_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quotation Header';

DROP TABLE IF EXISTS `qtd_det`;
CREATE TABLE `qtd_det` (
  `qtd_nbr` varchar(50) NOT NULL,
  `qtd_line` int(11) NOT NULL,
  `qtd_part` varchar(50) NOT NULL,
  `qtd_desc` varchar(255) DEFAULT NULL,
  `qtd_qty` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนที่เสนอ',
  `qtd_um` varchar(10) NOT NULL,
  `qtd_price` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ราคาเสนอ/หน่วย',
  `qtd_disc_pct` decimal(15,5) DEFAULT 0.00000 COMMENT 'ส่วนลด (%)',
  `qtd_line_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดสุทธิบรรทัดนี้',
  `qtd_tax_code` varchar(20) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`qtd_nbr`,`qtd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quotation Detail';

DROP TABLE IF EXISTS `rc_mstr`;
CREATE TABLE `rc_mstr` (
  `rc_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบรับของ (System Generated)',
  `rc_po_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ใบสั่งซื้ออ้างอิง',
  `rc_vd_code` varchar(50) DEFAULT NULL COMMENT 'รหัสผู้จำหน่ายที่มาส่งของ',
  `rc_site` varchar(50) DEFAULT NULL COMMENT 'คลังที่รับของเข้า',
  `rc_date` date NOT NULL COMMENT 'วันที่รับของเข้าคลัง',
  `rc_inv_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ใบกำกับภาษี หรือใบส่งของของผู้ขาย (ใช้เป็นล็อต)',
  `rc_inv_date` date DEFAULT NULL COMMENT 'วันที่บนใบแจ้งหนี้',
  `rc_curr` varchar(3) DEFAULT 'THB' COMMENT 'สกุลเงินที่ใช้ตาม PO',
  `rc_ex_rate` decimal(15,5) DEFAULT 1.00000 COMMENT 'อัตราแลกเปลี่ยน ณ วันรับของ',
  `rc_cr_terms` varchar(20) DEFAULT NULL COMMENT 'เงื่อนไขชำระเงิน (คำนวณ Due Date ตอนตั้ง AP)',
  `rc_net_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'มูลค่ารับของสุทธิก่อนภาษี (เบสสกุลเงิน)',
  `rc_tax_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'มูลค่าภาษี',
  `rc_tot_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'มูลค่ายอดรวมทั้งสิ้น',
  `rc_receiver` varchar(50) DEFAULT NULL COMMENT 'พนักงานคลังที่กดรับของ',
  `rc_ap_posted` tinyint(1) DEFAULT 0 COMMENT '0 = ยังไม่ตั้งหนี้, 1 = ตั้งเป็น AP Voucher แล้ว',
  `rc_ap_ref` varchar(50) DEFAULT NULL COMMENT 'เลขที่ AP Voucher ที่ผูกไป',
  `rc_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`rc_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Receipt Transaction Header';

DROP TABLE IF EXISTS `rcd_det`;
CREATE TABLE `rcd_det` (
  `rcd_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบรับของ',
  `rcd_line` int(11) NOT NULL COMMENT 'บรรทัดการรับ',
  `rcd_pod_line` int(11) DEFAULT NULL COMMENT 'อ้างอิงบรรทัดในใบสั่งซื้อ (PO Line)',
  `rcd_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `rcd_qty_rcvd` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนที่รับเข้าจริง',
  `rcd_qty_rej` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนที่ปฏิเสธรับ/ของเสีย (ส่งคืน)',
  `rcd_um` varchar(10) NOT NULL,
  `rcd_price` decimal(18,5) DEFAULT 0.00000 COMMENT 'ราคาต่อหน่วยรับเข้า (ดึงมาจาก PO)',
  `rcd_line_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดเงินสุทธิของบรรทัดนี้',
  `rcd_tax_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษีของบรรทัดนี้',
  `rcd_pur_acct` varchar(50) DEFAULT NULL COMMENT 'บัญชีพักสินค้ารอรับใบแจ้งหนี้ (GR/IR Clearing Account)',
  `rcd_loc` varchar(50) NOT NULL COMMENT 'หลุมที่เอาของไปเก็บ',
  `rcd_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account',
  `rcd_lot` varchar(50) DEFAULT '' COMMENT 'ล็อตของ Supplier หรือสร้างใหม่',
  `rcd_status` varchar(20) DEFAULT 'ACTIVE' COMMENT 'สถานะตอนรับ (อ้างอิง is_status)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`rcd_nbr`,`rcd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Receipt Transaction Detail';

DROP TABLE IF EXISTS `ro_mstr`;
CREATE TABLE `ro_mstr` (
  `ro_routing` varchar(50) NOT NULL COMMENT 'รหัสเส้นทางการผลิต (ปกติผูกกับ pt_routing)',
  `ro_op` int(11) NOT NULL COMMENT 'ลำดับขั้นตอน (Operation No. เช่น 10, 20, 30)',
  `ro_wkctr` varchar(50) NOT NULL COMMENT 'ทำที่เครื่องจักรไหน (FK: wc_wkctr)',
  `ro_desc` varchar(100) DEFAULT NULL COMMENT 'รายละเอียดงานที่ทำ',
  `ro_setup_time` decimal(10,4) DEFAULT 0.0000 COMMENT 'เวลาตั้งเครื่อง (ชม.)',
  `ro_run_time` decimal(10,4) DEFAULT 0.0000 COMMENT 'เวลาเดินเครื่องต่อ 1 ชิ้น (ชม.)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ro_routing`,`ro_op`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Routing Master';

DROP TABLE IF EXISTS `role_mstr`;
CREATE TABLE `role_mstr` (
  `role_code` varchar(50) NOT NULL COMMENT 'รหัสสิทธิ์ (เช่น ADMIN, SALES_MGR, BUYER)',
  `role_desc` varchar(100) NOT NULL COMMENT 'คำอธิบาย (เช่น ผู้ดูแลระบบ, ผู้จัดการฝ่ายขาย)',
  `role_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Role Master';

DROP TABLE IF EXISTS `role_prog_det`;
CREATE TABLE `role_prog_det` (
  `rpd_role` varchar(50) NOT NULL COMMENT 'รหัส Role (FK -> role_code)',
  `rpd_prog` varchar(50) NOT NULL COMMENT 'รหัสโปรแกรม (FK -> prog_code)',
  `rpd_can_view` tinyint(1) DEFAULT 0 COMMENT 'สิทธิ์ดูข้อมูล',
  `rpd_can_add` tinyint(1) DEFAULT 0 COMMENT 'สิทธิ์สร้างใหม่',
  `rpd_can_edit` tinyint(1) DEFAULT 0 COMMENT 'สิทธิ์แก้ไข',
  `rpd_can_delete` tinyint(1) DEFAULT 0 COMMENT 'สิทธิ์ลบ',
  `rpd_can_print` tinyint(1) DEFAULT 0 COMMENT 'สิทธิ์พิมพ์',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`rpd_role`,`rpd_prog`),
  KEY `fk_rpd_prog` (`rpd_prog`),
  CONSTRAINT `fk_rpd_role` FOREIGN KEY (`rpd_role`) REFERENCES `role_mstr` (`role_code`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rpd_prog` FOREIGN KEY (`rpd_prog`) REFERENCES `prog_mstr` (`prog_code`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Role-Program Access Rights';

DROP TABLE IF EXISTS `rs_mstr`;
CREATE TABLE `rs_mstr` (
  `rs_code` varchar(20) NOT NULL COMMENT 'รหัสเหตุผล (เช่น DAMG, EXPR, FOUN)',
  `rs_desc` varchar(255) NOT NULL COMMENT 'แตกหัก, หมดอายุ, หาเจอตอนตรวจนับ',
  `rs_type` varchar(10) NOT NULL COMMENT 'ISS=จ่ายออก, RCT=รับเข้า',
  `rs_acct` varchar(50) NOT NULL COMMENT 'รหัสบัญชีเพื่อทิ้งค่าใช้จ่าย/รายได้ (GL)',
  `rs_cc` varchar(50) DEFAULT NULL COMMENT 'แผนกที่รับผิดชอบ',
  `rs_sub` varchar(2) DEFAULT NULL COMMENT 'Reason Sub Account',
  `rs_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`rs_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory Adjustment Reason Codes';

DROP TABLE IF EXISTS sch_mstr;
CREATE TABLE IF NOT EXISTS sch_mstr (
 sch_type varchar(2) NOT NULL COMMENT 'PO ตารางส่งของผู้ขาย (Supplier Schedule) / SO ตารางส่งของลูกค้า (Customer Schedule)',
 sch_nbr varchar(50) NOT NULL COMMENT 'เลขที่ใบสั่งแบบกำหนดส่ง',
 sch_rlse_id varchar(20) NOT NULL COMMENT 'เลขที่รอบตาราง (Release ID): ปีเดือนวันที่ออก-ลำดับ รอบล่าสุดคือรอบที่ใช้',
 sch_date date NOT NULL COMMENT 'วันที่ออกรอบ',
 sch_firm_date date NOT NULL COMMENT 'ยืนยันส่งจริงถึงวันที่: งวดถึงวันนี้ = F ยืนยัน หลังจากนั้น = P พยากรณ์',
 sch_source varchar(10) NOT NULL DEFAULT 'MANUAL' COMMENT 'ORDER จากใบสั่ง / MANUAL กำหนดเอง / MRP จาก MRP / CUSTOMER ลูกค้าส่งมา',
 sch_ref varchar(50) DEFAULT NULL COMMENT 'เลขที่อ้างอิงของรอบ เช่น เลขตารางของลูกค้า',
 sch_rmks varchar(255) DEFAULT NULL COMMENT 'หมายเหตุ',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (sch_type, sch_nbr, sch_rlse_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Schedule releases of scheduled orders (รอบตารางกำหนดส่ง)';

DROP TABLE IF EXISTS schd_det;
CREATE TABLE IF NOT EXISTS schd_det (
 schd_type varchar(2) NOT NULL COMMENT 'PO ใบสั่งซื้อ / SO ใบสั่งขาย',
 schd_nbr varchar(50) NOT NULL COMMENT 'เลขที่ใบสั่ง',
 schd_rlse_id varchar(20) NOT NULL COMMENT 'เลขที่รอบตาราง (sch_mstr)',
 schd_line int(11) NOT NULL COMMENT 'บรรทัดของใบสั่ง',
 schd_seq int(11) NOT NULL COMMENT 'งวดที่',
 schd_part varchar(50) DEFAULT NULL COMMENT 'สินค้าของบรรทัด (ตรวจว่าบรรทัดยังเป็นสินค้าเดิม)',
 schd_date date NOT NULL COMMENT 'วันที่กำหนดส่ง',
 schd_qty decimal(15,5) NOT NULL DEFAULT 0 COMMENT 'จำนวนของงวด',
 schd_fpps char(1) NOT NULL DEFAULT 'F' COMMENT 'F ยืนยันส่งจริง (Firm) / P พยากรณ์ (Planned)',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (schd_type, schd_nbr, schd_rlse_id, schd_line, schd_seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Delivery schedule of scheduled purchase / sales orders, release by release (ตารางกำหนดส่ง)';

DROP TABLE IF EXISTS `sh_mstr`;
CREATE TABLE `sh_mstr` (
  `sh_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบส่งของ (Shipper)',
  `sh_so_nbr` varchar(50) NOT NULL COMMENT 'อ้างอิงใบสั่งขาย (SO)',
  `sh_cm_code` varchar(50) NOT NULL COMMENT 'รหัสลูกค้า',
  `sh_date` date NOT NULL COMMENT 'วันที่ส่งของ/ตัดสต็อกออก',
  `sh_ship_via` varchar(50) DEFAULT NULL COMMENT 'วิธีการจัดส่ง / ทะเบียนรถ',
  `sh_tracking` varchar(100) DEFAULT NULL COMMENT 'เลข Tracking ของบริษัทขนส่ง',
  `sh_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, C=Closed (ตัดสต็อกแล้ว), I=Invoiced (ออกใบแจ้งหนี้แล้ว)',
  `sh_inv_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ Invoice ที่ผูกกัน',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`sh_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shipper / Delivery Order Header';

DROP TABLE IF EXISTS `shd_det`;
CREATE TABLE `shd_det` (
  `shd_nbr` varchar(50) NOT NULL,
  `shd_line` int(11) NOT NULL,
  `shd_sod_line` int(11) NOT NULL COMMENT 'อ้างอิงบรรทัดใน SO',
  `shd_part` varchar(50) NOT NULL,
  `shd_qty_shp` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่ส่ง/ตัดสต็อกจริง',
  `shd_um` varchar(10) NOT NULL,
  `shd_loc` varchar(50) NOT NULL COMMENT 'ไปหยิบของมาจากหลุมไหน',
  `shd_lot` varchar(50) DEFAULT '' COMMENT 'รหัสล็อตของที่ส่ง',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`shd_nbr`,`shd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shipper / Delivery Order Detail';

DROP TABLE IF EXISTS `si_mstr`;
CREATE TABLE `si_mstr` (
  `si_site` varchar(50) NOT NULL COMMENT 'รหัสสาขา / คลัง',
  `si_desc` varchar(255) NOT NULL COMMENT 'ชื่อสาขา/รายละเอียด',
  `si_entity` varchar(50) DEFAULT NULL COMMENT 'นิติบุคคลย่อย (ถ้ามี)',
  `si_default_loc` varchar(50) DEFAULT NULL COMMENT 'จุดรับของเริ่มต้น',
  `si_house_no` varchar(16) DEFAULT NULL COMMENT 'เลขที่ (บ้านเลขที่)',
  `si_moo` varchar(10) DEFAULT NULL COMMENT 'หมู่ที่',
  `si_building` varchar(70) DEFAULT NULL COMMENT 'อาคาร',
  `si_floor` varchar(20) DEFAULT NULL COMMENT 'ชั้นที่',
  `si_room` varchar(20) DEFAULT NULL COMMENT 'ห้องเลขที่',
  `si_village` varchar(70) DEFAULT NULL COMMENT 'หมู่บ้าน',
  `si_soi` varchar(70) DEFAULT NULL COMMENT 'ตรอก/ซอย',
  `si_yaek` varchar(70) DEFAULT NULL COMMENT 'แยก',
  `si_road` varchar(70) DEFAULT NULL COMMENT 'ถนน',
  `si_line1` varchar(255) DEFAULT NULL COMMENT 'ที่อยู่บรรทัดเดียว (ประกอบจากช่องด้านบน)',
  `si_line2` varchar(255) DEFAULT NULL COMMENT 'ที่อยู่เพิ่มเติม',
  `si_sub_district` varchar(100) DEFAULT NULL COMMENT 'ตำบล/แขวง',
  `si_district` varchar(100) DEFAULT NULL COMMENT 'อำเภอ/เขต',
  `si_province` varchar(100) DEFAULT NULL COMMENT 'จังหวัด',
  `si_zip` varchar(20) DEFAULT NULL COMMENT 'รหัสไปรษณีย์',
  `si_status` tinyint(1) DEFAULT 1 COMMENT '1 = Active',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`si_site`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Site / Branch Master';

DROP TABLE IF EXISTS `so_ctrl`;
CREATE TABLE `so_ctrl` (
  `ctrl_id` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'ตารางนี้มีได้แถวเดียว (ค่าตั้งต้นของระบบ)',
  `so_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running SO',
  `so_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `so_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `so_prefix` varchar(10) DEFAULT 'SO',
  `so_inv_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running Invoice',
  `so_inv_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `so_inv_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `so_inv_prefix` varchar(10) DEFAULT 'IV',
  `so_cn_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ใบลดหนี้',
  `so_cn_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่ (เหมือน so_inv_format)',
  `so_cn_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป',
  `so_cn_prefix` varchar(10) DEFAULT 'CN',
  `so_dn_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ใบเพิ่มหนี้',
  `so_dn_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่ (เหมือน so_inv_format)',
  `so_dn_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป',
  `so_dn_prefix` varchar(10) DEFAULT 'DN',
  `so_qt_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running Quotation',
  `so_qt_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `so_qt_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `so_tax_pct` decimal(15,5) DEFAULT 7.00000 COMMENT 'ภาษีขายเริ่มต้น',
  `so_cr_chk` tinyint(1) DEFAULT 1 COMMENT '1=บังคับเช็ควงเงินเครดิตลูกค้าเสมอ',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  `so_sh_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running Delivery',
  `so_sh_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `so_sh_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `so_sh_prefix` varchar(10) DEFAULT 'SH' COMMENT 'Delivery Prefix',
  `so_sr_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ใบรับคืนสินค้า',
  `so_sr_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่ใบรับคืนสินค้า',
  `so_sr_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป',
  `so_sr_prefix` varchar(10) DEFAULT 'SR' COMMENT 'Sales return prefix',
  `so_qt_prefix` varchar(10) DEFAULT 'QT',
  PRIMARY KEY (`ctrl_id`),
  CONSTRAINT `chk_so_ctrl_single` CHECK (`ctrl_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales Order Control';

DROP TABLE IF EXISTS `so_mstr`;
CREATE TABLE `so_mstr` (
  `so_nbr` varchar(50) NOT NULL,
  `so_cm_code` varchar(50) NOT NULL COMMENT 'รหัสลูกค้า',
  `so_ship_to` varchar(50) DEFAULT NULL COMMENT 'สถานที่ส่งของลุกค้า',
  `so_bill_to` varchar(50) DEFAULT NULL COMMENT 'สถานที่วางบิล',
  `so_date` date NOT NULL,
  `so_req_date` date DEFAULT NULL COMMENT 'วันที่ลูกค้าร้องขอส่งของ',
  `so_due_date` date DEFAULT NULL COMMENT 'วันที่ครบกำหนดตามรอบ (ถ้าระบุ)',
  `so_sched` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = ใบสั่งขายแบบกำหนดส่ง (ส่งหลายงวดตาม schd_det)',
  `so_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, C=Closed, X=Canceled, S=Shipped',
  `so_slspsn` varchar(50) DEFAULT NULL COMMENT 'พนักงานขาย',
  `so_channel` varchar(50) DEFAULT NULL COMMENT 'ช่องทางการขาย (Online, Retail, B2B)',
  `so_po_ref` varchar(50) DEFAULT NULL COMMENT 'เลขที่ PO ของลูกค้า (Customer PO)',
  `so_cr_terms` varchar(20) DEFAULT NULL,
  `so_curr` varchar(3) DEFAULT 'THB',
  `so_ex_rate` decimal(18,5) DEFAULT 1.00000,
  `so_ex_date` date DEFAULT NULL,
  `so_tax_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษี',
  `so_tax_pct` decimal(15,5) DEFAULT 0.00000,
  `so_tax_amt` decimal(18,5) DEFAULT 0.00000,
  `so_wht_code` varchar(20) DEFAULT NULL COMMENT 'รหัสหัก ณ ที่จ่าย (ถ้าลูกค้าเป็นนิติบุคคล)',
  `so_wht_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดประเมินหัก ณ ที่จ่าย',
  `so_tot_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดสุทธิรวมทั้งบิล',
  `so_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`so_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales Order Master';

DROP TABLE IF EXISTS `sod_det`;
CREATE TABLE `sod_det` (
  `sod_nbr` varchar(50) NOT NULL,
  `sod_line` int(11) NOT NULL,
  `sod_part` varchar(50) NOT NULL,
  `sod_desc` varchar(255) DEFAULT NULL,
  `sod_qty_ord` decimal(15,5) NOT NULL DEFAULT 0.00000,
  `sod_qty_all` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดคงคลังที่ถูกจองไว้สำหรับออเดอร์นี้',
  `sod_qty_shp` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนที่ส่งของ/ตัดสต็อกไปแล้ว (Accumulated)',
  `sod_um` varchar(10) NOT NULL,
  `sod_price` decimal(18,5) NOT NULL DEFAULT 0.00000,
  `sod_disc_pct` decimal(15,5) DEFAULT 0.00000,
  `sod_line_amt` decimal(18,5) DEFAULT 0.00000,
  `sod_req_date` date DEFAULT NULL COMMENT 'วันที่ลูกค้าต้องการของเฉพะบรรทัดนี้',
  `sod_site` varchar(50) DEFAULT NULL COMMENT 'ตัดสต็อกออกจากสาขาไหน',
  `sod_loc` varchar(50) DEFAULT NULL COMMENT 'ตัดสต็อกออกจากหลุมไหน',
  `sod_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชีรายได้',
  `sod_cc` varchar(50) DEFAULT NULL COMMENT 'แผนกที่รับรายได้',
  `sod_sub` varchar(2) DEFAULT NULL COMMENT 'Sub Account',
  `sod_status` varchar(1) DEFAULT 'O',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`sod_nbr`,`sod_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales Order Detail';

DROP TABLE IF EXISTS `sub_mstr`;
CREATE TABLE `sub_mstr` (
  `sub_acct` varchar(2) NOT NULL COMMENT 'Sub Account Code',
  `sub_desc` varchar(255) NOT NULL COMMENT 'Description',
  `sub_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sub_acct`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tb_mstr`;
CREATE TABLE `tb_mstr` (
  `tb_id` int(11) NOT NULL AUTO_INCREMENT,
  `tb_code` int(11) NOT NULL,
  `tb_name_th` varchar(150) NOT NULL,
  `tb_name_en` varchar(150) DEFAULT NULL,
  `tb_am_id` int(11) NOT NULL,
  `tb_zip_code` varchar(10) DEFAULT NULL,
  `tb_lat` decimal(10,6) DEFAULT NULL,
  `tb_lng` decimal(10,6) DEFAULT NULL,
  PRIMARY KEY (`tb_id`),
  UNIQUE KEY `ux_tb_code` (`tb_code`),
  KEY `fk_tb_am` (`tb_am_id`),
  CONSTRAINT `fk_tb_am` FOREIGN KEY (`tb_am_id`) REFERENCES `am_mstr` (`am_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `to_mstr`;
CREATE TABLE `to_mstr` (
  `to_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบสั่งย้าย',
  `to_from_site` varchar(50) NOT NULL COMMENT 'จากสาขา',
  `to_to_site` varchar(50) NOT NULL COMMENT 'ไปสาขา',
  `to_date` date NOT NULL,
  `to_req_date` date DEFAULT NULL COMMENT 'วันที่ต้องการให้ของถึง',
  `to_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, S=Shipped, R=Received, C=Closed',
  `to_ship_via` varchar(50) DEFAULT NULL COMMENT 'วิธีการจัดส่ง',
  `to_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`to_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transfer Order Master';

DROP TABLE IF EXISTS `tod_det`;
CREATE TABLE `tod_det` (
  `tod_nbr` varchar(50) NOT NULL,
  `tod_line` int(11) NOT NULL,
  `tod_part` varchar(50) NOT NULL,
  `tod_qty_ord` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดสั่งย้าย',
  `tod_qty_shp` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่ตัดออกจากต้นทางแล้ว',
  `tod_qty_rcvd` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่รับเข้าปลายทางแล้ว (ของอยู่ระหว่างทาง = shp - rcvd)',
  `tod_um` varchar(10) NOT NULL,
  `tod_from_loc` varchar(50) DEFAULT NULL COMMENT 'หลุมต้นทาง',
  `tod_to_loc` varchar(50) DEFAULT NULL COMMENT 'หลุมปลายทางเริ่มต้น',
  `tod_status` varchar(1) DEFAULT 'O',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`tod_nbr`,`tod_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Transfer Order Detail';

DROP TABLE IF EXISTS `tr_hist`;
CREATE TABLE `tr_hist` (
  `tr_trnbr` int(11) NOT NULL AUTO_INCREMENT COMMENT 'เลข Running กิจกรรม (Primary Key)',
  `tr_date` date NOT NULL COMMENT 'วันที่เกิด Transaction',
  `tr_time` time DEFAULT NULL COMMENT 'เวลาที่เกิด',
  `tr_type` varchar(10) NOT NULL COMMENT 'ประเภท (RCT-PO, ISS-SO, ISS-TR, RCT-TR, ISS-UNP, CYC-CNT)',
  `tr_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้า',
  `tr_site` varchar(50) NOT NULL COMMENT 'สาขาที่เกิดรายการ',
  `tr_loc` varchar(50) NOT NULL COMMENT 'หลุมที่เกิดรายการ',
  `tr_lot` varchar(50) DEFAULT '' COMMENT 'เลขล็อต (ถ้ามี)',
  `tr_ref` varchar(50) DEFAULT '' COMMENT 'รหัสย่อยเพิ่มเติม',
  `tr_begin_qty` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดคงเหลือของคลัง/ตำแหน่ง/ล็อตก่อนทำรายการ',
  `tr_chg_qty` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนที่เคลื่อนไหว (รับเป็น +, จ่ายเป็น -)',
  `tr_loc_qty` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดคงเหลือหลังทำรายการ = tr_begin_qty + tr_chg_qty',
  `tr_um` varchar(10) NOT NULL COMMENT 'หน่วยนับตอนทำรายการ',
  `tr_price` decimal(18,5) DEFAULT 0.00000 COMMENT 'ราคา (กรณีรับจากการซื้อ)',
  `tr_cost` decimal(18,5) DEFAULT 0.00000 COMMENT 'ต้นทุน ณ วันที่เกิดรายการ',
  `tr_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่เอกสารอ้างอิงหลัก (เช่น PO#, SO#, TR#)',
  `tr_line` int(11) DEFAULT NULL COMMENT 'บรรทัดเอกสารอ้างอิง',
  `tr_rmks` varchar(255) DEFAULT NULL COMMENT 'หมายเหตุ',
  `tr_rsn_code` varchar(20) DEFAULT NULL COMMENT 'รหัสเหตุผล (rs_mstr)',
  `tr_userid` varchar(50) NOT NULL COMMENT 'ผู้ทำรายการ',
  `tr_program` varchar(50) DEFAULT NULL COMMENT 'โค้ดหน้าจอที่ใช้ทำรายการ (เช่น 3.1.1, po_rcv)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`tr_trnbr`),
  KEY `idx_tr_date` (`tr_date`),
  KEY `idx_tr_nbr` (`tr_nbr`),
  KEY `idx_tr_part` (`tr_part`,`tr_site`,`tr_loc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventory Transaction History';

DROP TABLE IF EXISTS `tx_mstr`;
CREATE TABLE `tx_mstr` (
  `tx_tax_code` varchar(20) NOT NULL COMMENT 'รหัสภาษี (เช่น VAT7, EXMPT)',
  `tx_desc` varchar(100) NOT NULL COMMENT 'คำอธิบาย (เช่น ภาษีมูลค่าเพิ่ม 7%)',
  `tx_tax_pct` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'อัตราภาษี (%)',
  `tx_tax_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสผังบัญชีที่ผูก (GL Account)',
  `tx_tax_sub` varchar(2) DEFAULT NULL COMMENT 'Tax Sub Account',
  `tx_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`tx_tax_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tax Class / Code Master';

DROP TABLE IF EXISTS `um_mstr`;
CREATE TABLE `um_mstr` (
  `um_um` varchar(10) NOT NULL COMMENT 'รหัสหน่วยนับ (เช่น EA, PC, KG, LITER)',
  `um_desc` varchar(100) NOT NULL COMMENT 'ชื่อเต็มของหน่วยนับ',
  `um_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`um_um`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Unit of Measure Master';

DROP TABLE IF EXISTS `us_mstr`;
CREATE TABLE `us_mstr` (
  `us_userid` varchar(50) NOT NULL COMMENT 'รหัสพนักงาน หรือ Username',
  `us_name` varchar(255) NOT NULL COMMENT 'ชื่อ-นามสกุล',
  `us_email` varchar(255) NOT NULL,
  `us_password_hash` varchar(255) NOT NULL,
  `us_role` varchar(50) DEFAULT NULL COMMENT 'รหัสบทบาท (FK -> role_mstr.role_code)',
  `us_dept` varchar(50) DEFAULT NULL COMMENT 'แผนกที่สังกัด',
  `us_phone` varchar(50) DEFAULT NULL,
  `us_mobile` varchar(50) DEFAULT NULL,
  `us_lang` varchar(10) DEFAULT 'th-TH' COMMENT 'ภาษาที่ใช้แสดงผล',
  `us_theme` varchar(10) NOT NULL DEFAULT 'light' COMMENT 'ธีม: light = ไผ่, mist = หมอก, indigo = คราม, grape = อัญชัน, clay = ดินเผา, dark = ไผ่ยามค่ำ',
  `us_pins` varchar(1000) DEFAULT NULL COMMENT 'เมนูที่ปักหมุดไว้ใต้ เรียนรู้ ERP (mnu_code คั่นด้วย , ตามลำดับที่ปัก)',
  `us_timezone` varchar(50) DEFAULT 'Asia/Bangkok',
  `us_avatar` varchar(255) DEFAULT NULL,
  `us_sign` varchar(40) DEFAULT NULL COMMENT 'ไฟล์ลายเซ็น (PNG พื้นใส)',
  `us_last_state` varchar(50) DEFAULT NULL,
  `us_last_state_date` date DEFAULT NULL,
  `us_last_login` datetime DEFAULT NULL,
  `us_login_count` int(11) DEFAULT 0,
  `us_active` tinyint(1) DEFAULT 1 COMMENT '1 = ใช้งานได้, 0 = ถูกระงับ',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`us_userid`),
  UNIQUE KEY `idx_us_email` (`us_email`),
  KEY `fk_us_role` (`us_role`),
  CONSTRAINT `fk_us_role` FOREIGN KEY (`us_role`) REFERENCES `role_mstr` (`role_code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User Master';

DROP TABLE IF EXISTS `vd_mstr`;
CREATE TABLE `vd_mstr` (
  `vd_code` varchar(50) NOT NULL COMMENT 'รหัสผู้จำหน่าย (เชื่อม -> ad_addr)',
  `vd_name` varchar(255) NOT NULL,
  `vd_name2` varchar(255) DEFAULT NULL,
  `vd_line1` varchar(255) DEFAULT NULL,
  `vd_line2` varchar(255) DEFAULT NULL,
  `vd_line3` varchar(255) DEFAULT NULL,
  `vd_building` varchar(70) DEFAULT NULL COMMENT 'อาคาร',
  `vd_room` varchar(20) DEFAULT NULL COMMENT 'ห้องเลขที่',
  `vd_floor` varchar(20) DEFAULT NULL COMMENT 'ชั้นที่',
  `vd_village` varchar(70) DEFAULT NULL COMMENT 'หมู่บ้าน',
  `vd_house_no` varchar(16) DEFAULT NULL COMMENT 'เลขที่ (บ้านเลขที่)',
  `vd_moo` varchar(10) DEFAULT NULL COMMENT 'หมู่ที่',
  `vd_soi` varchar(70) DEFAULT NULL COMMENT 'ตรอก/ซอย',
  `vd_yaek` varchar(70) DEFAULT NULL COMMENT 'แยก',
  `vd_road` varchar(70) DEFAULT NULL COMMENT 'ถนน',
  `vd_sub_district` varchar(100) DEFAULT NULL COMMENT 'ตำบล',
  `vd_district` varchar(100) DEFAULT NULL COMMENT 'อำเภอ',
  `vd_province` varchar(100) DEFAULT NULL COMMENT 'จังหวัด',
  `vd_zip` varchar(20) DEFAULT NULL,
  `vd_country` varchar(100) DEFAULT 'Thailand',
  `vd_attn` varchar(100) DEFAULT NULL,
  `vd_phone` varchar(50) DEFAULT NULL,
  `vd_phone2` varchar(50) DEFAULT NULL,
  `vd_fax` varchar(50) DEFAULT NULL,
  `vd_email` varchar(100) DEFAULT NULL,
  `vd_website` varchar(255) DEFAULT NULL,
  `vd_tax_id_num` varchar(50) DEFAULT NULL,
  `vd_tax_branch` varchar(50) DEFAULT '00000',
  `vd_type` varchar(50) DEFAULT NULL,
  `vd_terms` varchar(20) DEFAULT NULL,
  `vd_curr` varchar(3) DEFAULT 'THB',
  `vd_tax_code` varchar(20) DEFAULT NULL,
  `vd_tax_in_price` tinyint(1) DEFAULT 0 COMMENT 'VAT IN',
  `vd_buyer` varchar(50) DEFAULT NULL COMMENT 'จัดซื้อที่รับผิดชอบ',
  `vd_ap_acct` varchar(50) DEFAULT NULL COMMENT 'บัญชีเจ้าหนี้เฉพาะราย',
  `vd_wht_code` varchar(20) DEFAULT NULL COMMENT 'รหัสหัก ณ ที่จ่ายเริ่มต้นของบรรทัดตั้งหนี้',
  `vd_ap_sub` varchar(2) DEFAULT NULL COMMENT 'AP Sub Account',
  `vd_bank` varchar(50) DEFAULT NULL COMMENT 'ธนาคารผู้จำหน่าย (โอนเงิน)',
  `vd_bank_acct` varchar(50) DEFAULT NULL,
  `vd_bank_branch` varchar(100) DEFAULT NULL,
  `vd_swift_code` varchar(20) DEFAULT NULL,
  `vd_balance` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดที่เราค้างจ่ายรวม',
  `vd_status` varchar(20) DEFAULT 'ACTIVE',
  `vd_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`vd_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vendor / Supplier Master';

DROP TABLE IF EXISTS `vo_mstr`;
CREATE TABLE `vo_mstr` (
  `vo_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบสั่งจ่าย/เช็ค',
  `vo_bank` varchar(50) NOT NULL COMMENT 'จ่ายเงินออกจากธนาคารไหน (FK: bk_bank)',
  `vo_vd_code` varchar(50) NOT NULL COMMENT 'จ่ายเงินให้ Vendor คนไหน',
  `vo_date` date NOT NULL COMMENT 'วันที่สั่งจ่าย',
  `vo_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนเงินสด/โอนสุทธิที่จ่ายออกไป',
  `vo_wht_code` varchar(20) DEFAULT NULL COMMENT 'รหัสหัก ณ ที่จ่ายตอนจ่ายชำระ',
  `vo_wht_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดที่หักเขาไว้ (ใบ 50 ทวิ)',
  `vo_wht_form` varchar(10) DEFAULT NULL COMMENT 'PND3 / PND53',
  `vo_ap_ref` varchar(50) DEFAULT NULL COMMENT 'อ้างอิงเลขตั๋ว AP Voucher ที่ต้องการตัด',
  `vo_status` varchar(1) DEFAULT 'P' COMMENT 'U=Unapplied, P=Posted, V=Voided',
  `vo_remarks` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`vo_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment Voucher / AP Payment';

DROP TABLE IF EXISTS `vod_det`;
CREATE TABLE `vod_det` (
  `vod_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบสั่งจ่าย (FK -> vo_nbr)',
  `vod_line` int(11) NOT NULL COMMENT 'ลำดับบรรทัด',
  `vod_ap_ref` varchar(50) NOT NULL COMMENT 'เลขที่ใบสำคัญจ่าย VO ที่จ่าย (FK -> apv_mstr.apv_nbr)',
  `vod_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดเงินที่ตัดตั้งหนี้บิลนี้ (ก่อนภาษีหัก ณ ที่จ่าย)',
  `vod_wht_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดหัก ณ ที่จ่ายเฉพาะบิลนี้',
  `vod_net_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดชำระสุทธิเฉพาะบิลนี้ที่นำไปรวมเป็น vo_amt (ยอดเช็ค)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`vod_nbr`,`vod_line`),
  KEY `idx_vod_ap` (`vod_ap_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Payment Voucher Detail';

DROP TABLE IF EXISTS `wc_mstr`;
CREATE TABLE `wc_mstr` (
  `wc_wkctr` varchar(50) NOT NULL COMMENT 'รหัสเครื่องจักร/จุดทำงาน',
  `wc_desc` varchar(100) NOT NULL COMMENT 'ชื่อเครื่อง/แผนก',
  `wc_dept` varchar(50) DEFAULT NULL COMMENT 'แผนกที่สังกัด',
  `wc_mch_rate` decimal(18,5) DEFAULT 0.00000 COMMENT 'ค่าใช้จ่ายเครื่องจักร/ชม. (Machine Rate)',
  `wc_lbr_rate` decimal(18,5) DEFAULT 0.00000 COMMENT 'ค่าแรงงานคน/ชม. (Labor Rate)',
  `wc_bdn_rate` decimal(18,5) DEFAULT 0.00000 COMMENT 'ค่าโสหุ้ย/ชม. (Burden Rate)',
  `wc_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`wc_wkctr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Work Center Master';

DROP TABLE IF EXISTS `wht_cert`;
CREATE TABLE `wht_cert` (
  `whtc_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบ 50 ทวิ (Running Number)',
  `whtc_type` varchar(10) DEFAULT 'AP' COMMENT 'AP = เราหักเขา, AR = เขาหักเรา',
  `whtc_doc_ref` varchar(50) DEFAULT NULL COMMENT 'อ้างอิงเลขที่ตั้งหนี้/ตั๋ว (AP/AR)',
  `whtc_vd_cm_code` varchar(50) NOT NULL COMMENT 'รหัส Vendor หรือ Customer',
  `whtc_date` date NOT NULL COMMENT 'วันที่ออกหนังสือ / วันที่รับหนังสือ',
  `whtc_form_type` varchar(20) DEFAULT 'PND53' COMMENT 'ประเภทแบบฟอร์ม (ภ.ง.ด.3, ภ.ง.ด.53)',
  `whtc_base_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ฐานรายได้ที่ใช้คำนวณ',
  `whtc_tax_amt` decimal(18,5) DEFAULT 0.00000 COMMENT 'ยอดภาษีหัก ณ ที่จ่าย',
  `whtc_status` varchar(1) DEFAULT 'O' COMMENT 'O=Open, P=Printed, C=Canceled',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`whtc_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withholding Tax Certificates';

DROP TABLE IF EXISTS `whtd_det`;
CREATE TABLE `whtd_det` (
  `whtd_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบ 50 ทวิ (wht_cert)',
  `whtd_line` int(11) NOT NULL COMMENT 'ลำดับ',
  `whtd_code` varchar(20) NOT NULL COMMENT 'รหัส WHT',
  `whtd_desc` varchar(100) DEFAULT NULL COMMENT 'ประเภทเงินได้ (ตามรหัส ณ วันที่หัก)',
  `whtd_pct` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'อัตราหัก ณ ที่จ่าย (%) ณ วันที่หัก',
  `whtd_form_row` varchar(4) NOT NULL DEFAULT '5' COMMENT 'แถวประเภทเงินได้ในแบบ 50 ทวิ',
  `whtd_base_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'จำนวนเงินที่จ่าย (ฐานภาษี)',
  `whtd_tax_amt` decimal(18,5) NOT NULL DEFAULT 0.00000 COMMENT 'ภาษีที่หัก',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`whtd_nbr`, `whtd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withholding Tax Certificate lines (one per rate)';

DROP TABLE IF EXISTS `wht_mstr`;
CREATE TABLE `wht_mstr` (
  `wht_code` varchar(20) NOT NULL COMMENT 'รหัส WHT (เช่น WHT3, WHT1)',
  `wht_desc` varchar(100) NOT NULL COMMENT 'คำอธิบาย (เช่น ค่าบริการ 3%, ค่าขนส่ง 1%)',
  `wht_pct` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'อัตราหัก ณ ที่จ่าย (%)',
  `wht_acct` varchar(50) DEFAULT NULL COMMENT 'รหัสบัญชีผูก GL',
  `wht_sub` varchar(2) DEFAULT NULL COMMENT 'WHT Sub Account',
  `wht_form_row` varchar(4) NOT NULL DEFAULT '5' COMMENT 'แถวประเภทเงินได้ในแบบ 50 ทวิ (1, 2, 3, 4a, 4b, 5, 6)',
  `wht_active` tinyint(1) DEFAULT 1,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`wht_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withholding Tax Master';

DROP TABLE IF EXISTS `wo_ctrl`;
CREATE TABLE `wo_ctrl` (
  `ctrl_id` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'ตารางนี้มีได้แถวเดียว (ค่าตั้งต้นของระบบ)',
  `wo_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running Work Order',
  `wo_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `wo_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `wo_prefix` varchar(10) DEFAULT 'WO' COMMENT 'WO Prefix',
  `qc_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running QC Test',
  `qc_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `qc_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `qc_prefix` varchar(10) DEFAULT 'QC' COMMENT 'QC Prefix',
  `wo_mi_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ใบเบิก / ใบคืนวัตถุดิบ',
  `wo_mi_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่ใบเบิกวัตถุดิบ',
  `wo_mi_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `wo_mi_prefix` varchar(10) DEFAULT 'MI' COMMENT 'คำนำหน้าเลขที่ใบเบิกวัตถุดิบ',
  `wo_mrp_run_at` datetime DEFAULT NULL COMMENT 'รัน MRP ล่าสุดเมื่อ',
  `wo_mrp_run_by` varchar(50) DEFAULT NULL COMMENT 'ผู้รัน MRP ล่าสุด',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` datetime DEFAULT NULL COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` datetime DEFAULT NULL COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`ctrl_id`),
  CONSTRAINT `chk_wo_ctrl_single` CHECK (`ctrl_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wo_labor`;
CREATE TABLE `wo_labor` (
  `wol_trnbr` int(11) NOT NULL AUTO_INCREMENT COMMENT 'เลข Running กิจกรรม',
  `wol_wo_nbr` varchar(50) NOT NULL COMMENT 'เลขใบสั่งผลิต (FK: wo_nbr)',
  `wol_op` int(11) NOT NULL COMMENT 'ขั้นตอนการผลิต',
  `wol_emp_nbr` varchar(50) NOT NULL COMMENT 'รหัสพนักงานผู้ปฏิบัติการ (FK: emp_nbr)',
  `wol_wkctr` varchar(50) NOT NULL COMMENT 'รหัสเครื่องจักร',
  `wol_date` date NOT NULL COMMENT 'วันที่ทำ',
  `wol_start_time` time DEFAULT NULL COMMENT 'เวลาเริ่ม',
  `wol_end_time` time DEFAULT NULL COMMENT 'เวลาจบ',
  `wol_hours` decimal(10,4) DEFAULT 0.0000 COMMENT 'ชั่วโมงทำงานรวม',
  `wol_qty_comp` decimal(15,5) DEFAULT 0.00000 COMMENT 'ยอดที่ทำได้ (ของดี)',
  `wol_qty_rjct` decimal(15,5) DEFAULT 0.00000 COMMENT 'ยอดของเสีย',
  `wol_rjct_rsn` varchar(50) DEFAULT NULL COMMENT 'รหัสสาเหตุของเสีย',
  `wol_auto` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = ลงให้เองตามชั่วโมงมาตรฐานของขั้นตอน ตอนรับผลผลิต',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`wol_trnbr`),
  KEY `idx_wol_wo` (`wol_wo_nbr`,`wol_op`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Work Order Labor Transactions';

DROP TABLE IF EXISTS `wis_mstr`;
CREATE TABLE `wis_mstr` (
  `wis_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบเบิก / ใบคืนวัตถุดิบ (เลขชุด MI) — บรรทัดคือ tr_hist ที่ tr_ref = เลขนี้',
  `wis_wo_nbr` varchar(50) NOT NULL COMMENT 'ใบสั่งผลิต',
  `wis_type` char(1) NOT NULL DEFAULT 'I' COMMENT 'I = เบิก, R = คืน',
  `wis_date` date NOT NULL COMMENT 'วันที่เบิก / คืน',
  `wis_reason` varchar(255) DEFAULT NULL COMMENT 'เหตุผล (บังคับเมื่อเบิกเกินสูตร)',
  `wis_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'มูลค่าตามต้นทุน (บาท)',
  `wis_status` char(1) NOT NULL DEFAULT 'A' COMMENT 'A = ใช้งาน, X = ยกเลิก (ของกลับที่เดิมด้วยรายการกลับข้าง tr_ref เดียวกัน)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`wis_nbr`),
  KEY `idx_wis_wo` (`wis_wo_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ใบเบิก / ใบคืนวัตถุดิบของใบสั่งผลิต';

DROP TABLE IF EXISTS `wo_mstr`;
CREATE TABLE `wo_mstr` (
  `wo_nbr` varchar(50) NOT NULL COMMENT 'เลขที่ใบสั่งผลิต',
  `wo_lot` varchar(50) DEFAULT NULL COMMENT 'เลข Batch/Lot ของงานผลิตนี้',
  `wo_part` varchar(50) NOT NULL COMMENT 'รหัสสินค้าลูกที่จะผลิต',
  `wo_qty_ord` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดสั่งผลิต',
  `wo_qty_comp` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดผลิตเสร็จ/รับเข้าแล้ว',
  `wo_qty_rjct` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดของเสีย (Scrap)',
  `wo_rel_date` date NOT NULL COMMENT 'วันที่เริ่มผลิต (Release Date)',
  `wo_due_date` date NOT NULL COMMENT 'วันที่กําหนดเสร็จ',
  `wo_status` varchar(1) DEFAULT 'R' COMMENT 'F=Firmed, R=Released, C=Closed',
  `wo_routing` varchar(50) DEFAULT NULL COMMENT 'เส้นทางที่ใช้อ้างอิง',
  `wo_bom_code` varchar(50) DEFAULT NULL COMMENT 'สูตร (BOM) ที่ใช้',
  `wo_site` varchar(50) DEFAULT NULL COMMENT 'คลังที่ผลิต (เบิกวัตถุดิบและรับสินค้าเข้า)',
  `wo_rmks` text DEFAULT NULL COMMENT 'หมายเหตุ',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`wo_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Work Order Header';

DROP TABLE IF EXISTS `wod_det`;
CREATE TABLE `wod_det` (
  `wod_nbr` varchar(50) NOT NULL COMMENT 'อ้างอิงใบสั่งผลิต (FK: wo_nbr)',
  `wod_part` varchar(50) NOT NULL COMMENT 'รหัสวัตถุดิบ (Component)',
  `wod_qty_req` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่ต้องเบิกตามสูตร',
  `wod_qty_iss` decimal(15,5) NOT NULL DEFAULT 0.00000 COMMENT 'ยอดที่เบิกไปจริงแล้ว',
  `wod_loc` varchar(50) DEFAULT NULL COMMENT 'จุดที่คาดว่าจะไปเบิก (Issue Location)',
  `wod_op` int(11) DEFAULT NULL COMMENT 'เบิกไปใช้ที่ขั้นตอนไหน (Op No.)',
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้สร้างรายการ',
  `created_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'วันที่สร้างรายการ',
  `updated_by` varchar(50) DEFAULT NULL COMMENT 'ผู้แก้ไขล่าสุด',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'วันที่แก้ไขล่าสุด',
  PRIMARY KEY (`wod_nbr`,`wod_part`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Work Order Detail / Material Allocation';

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS grid_layout (
 layout_key varchar(255) NOT NULL PRIMARY KEY,
 layout_columns text NOT NULL,
 updated_by varchar(50) NOT NULL,
 updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Fixed assets
DROP TABLE IF EXISTS fa_trhist;
DROP TABLE IF EXISTS fa_mstr;
DROP TABLE IF EXISTS fa_category;
CREATE TABLE IF NOT EXISTS fa_category (
 fc_code varchar(50) PRIMARY KEY COMMENT 'หมวดสินทรัพย์',
 fc_desc varchar(255) NOT NULL COMMENT 'ชื่อหมวด',
 fc_method varchar(4) NOT NULL DEFAULT 'SL' COMMENT 'วิธีค่าเสื่อม',
 fc_life_months int NOT NULL DEFAULT 60 COMMENT 'อายุใช้งาน (เดือน)',
 fc_rate decimal(7,4) NOT NULL DEFAULT 20 COMMENT 'อัตรายอดลดลงต่อปี (%)',
 fc_asset_acct varchar(50) NOT NULL COMMENT 'บัญชีสินทรัพย์',
 fc_accum_acct varchar(50) NOT NULL COMMENT 'บัญชีค่าเสื่อมสะสม',
 fc_expense_acct varchar(50) NOT NULL COMMENT 'บัญชีค่าเสื่อมราคา',
 fc_gainloss_acct varchar(50) NOT NULL COMMENT 'บัญชีกำไร/ขาดทุนจำหน่าย',
 fc_active tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ใช้งาน',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fa_mstr (
 fa_code varchar(50) PRIMARY KEY COMMENT 'รหัสสินทรัพย์',
 fa_desc varchar(255) NOT NULL COMMENT 'ชื่อสินทรัพย์',
 fa_category varchar(50) NOT NULL COMMENT 'หมวดสินทรัพย์',
 fa_serial varchar(100) COMMENT 'เลขเครื่อง/ทะเบียน',
 fa_site varchar(50) NOT NULL COMMENT 'สาขา', fa_location varchar(100) COMMENT 'สถานที่ตั้ง',
 fa_cc varchar(50) COMMENT 'ศูนย์ต้นทุน', fa_custodian varchar(100) COMMENT 'ผู้รับผิดชอบ',
 fa_prod_line varchar(50) COMMENT 'สายผลิตภัณฑ์ที่ใช้ผลิต',
 fa_vendor varchar(50) COMMENT 'ผู้ขาย', fa_source_ref varchar(100) COMMENT 'เอกสารซื้ออ้างอิง',
 fa_acquired date NOT NULL COMMENT 'วันที่ได้มา', fa_start date NOT NULL COMMENT 'วันที่เริ่มใช้งาน',
 fa_cost decimal(15,2) NOT NULL COMMENT 'ราคาทุน', fa_salvage decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'มูลค่าซาก',
 fa_method varchar(4) NOT NULL DEFAULT 'SL' COMMENT 'วิธีค่าเสื่อม',
 fa_life_months int NOT NULL DEFAULT 60 COMMENT 'อายุใช้งาน (เดือน)',
 fa_rate decimal(7,4) NOT NULL DEFAULT 20 COMMENT 'อัตรายอดลดลงต่อปี (%)',
 fa_opening_depr decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'ค่าเสื่อมสะสมยกมา',
 fa_opening_month varchar(7) COMMENT 'ยกมายอดถึงเดือน (yyyy-mm)',
 fa_accum decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'ค่าเสื่อมสะสมปัจจุบัน',
 fa_last_month varchar(7) COMMENT 'ลงค่าเสื่อมล่าสุดถึงเดือน',
 fa_status varchar(12) NOT NULL DEFAULT 'DRAFT' COMMENT 'สถานะ',
 fa_disposed date COMMENT 'วันที่จำหน่าย', fa_notes text COMMENT 'หมายเหตุ',
 fa_asset_acct varchar(50) COMMENT 'บัญชีสินทรัพย์', fa_accum_acct varchar(50) COMMENT 'บัญชีค่าเสื่อมสะสม',
 fa_expense_acct varchar(50) COMMENT 'บัญชีค่าเสื่อมราคา', fa_gainloss_acct varchar(50) COMMENT 'บัญชีกำไร/ขาดทุนจำหน่าย',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY (fa_category), KEY (fa_status), KEY (fa_prod_line), CONSTRAINT fk_fa_category FOREIGN KEY (fa_category) REFERENCES fa_category(fc_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS fa_trhist (
 fat_id bigint unsigned AUTO_INCREMENT PRIMARY KEY COMMENT 'เลขรายการ',
 fat_asset varchar(50) NOT NULL COMMENT 'รหัสสินทรัพย์', fat_type varchar(12) NOT NULL COMMENT 'ประเภทรายการ',
 fat_date date NOT NULL COMMENT 'วันที่', fat_month varchar(7) COMMENT 'เดือนค่าเสื่อม',
 fat_amount decimal(15,2) NOT NULL DEFAULT 0 COMMENT 'จำนวนเงิน',
 fat_ref varchar(100) COMMENT 'เอกสารอ้างอิง', fat_note varchar(255) COMMENT 'หมายเหตุ',
 fat_before longtext NOT NULL COMMENT 'ข้อมูลก่อนรายการ', fat_after longtext NOT NULL COMMENT 'ข้อมูลหลังรายการ',
 fat_posted tinyint NOT NULL DEFAULT 0 COMMENT 'ลงบัญชีแล้ว', fat_reversed tinyint NOT NULL DEFAULT 0 COMMENT 'กลับรายการแล้ว',
 fat_reverse_of bigint unsigned COMMENT 'กลับรายการเลขที่',
 fat_guard varchar(20) COMMENT 'ป้องกันค่าเสื่อมซ้ำ',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY fa_period_once (fat_asset, fat_guard), KEY (fat_asset, fat_date),
 CONSTRAINT fk_fat_asset FOREIGN KEY (fat_asset) REFERENCES fa_mstr(fa_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS bkrd_det;
DROP TABLE IF EXISTS bkr_mstr;
CREATE TABLE IF NOT EXISTS bkr_mstr (
 bkr_bank varchar(50) NOT NULL COMMENT 'สมุดธนาคาร (bk_mstr)',
 bkr_date date NOT NULL COMMENT 'วันที่ตาม statement',
 bkr_balance decimal(18,2) NOT NULL COMMENT 'ยอดคงเหลือตาม statement',
 bkr_book decimal(18,2) NOT NULL COMMENT 'ยอดคงเหลือตามบัญชี ณ วันที่ statement',
 bkr_remarks varchar(255) DEFAULT NULL COMMENT 'หมายเหตุ',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (bkr_bank, bkr_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bank reconciliations (one per statement)';
CREATE TABLE IF NOT EXISTS bkrd_det (
 bkrd_trnbr int NOT NULL COMMENT 'รายการบัญชีที่ตรงกับ statement (gl_trhist.gl_trnbr)',
 bkrd_bank varchar(50) NOT NULL COMMENT 'สมุดธนาคาร',
 bkrd_date date NOT NULL COMMENT 'กระทบยอดครั้งที่ (bkr_date)',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (bkrd_trnbr),
 KEY bkrd_rec (bkrd_bank, bkrd_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GL lines cleared by a bank reconciliation';

DROP TABLE IF EXISTS etax_log;
CREATE TABLE IF NOT EXISTS etax_log (
 etax_id int NOT NULL AUTO_INCREMENT,
 etax_kind varchar(3) NOT NULL COMMENT 'INV ใบกำกับภาษี, CDN ใบลดหนี้, CIV ยกเลิกใบกำกับภาษี',
 etax_doc varchar(50) NOT NULL COMMENT 'เลขที่เอกสาร (ih_mstr.ih_inv_nbr)',
 etax_ref varchar(50) DEFAULT NULL COMMENT 'ใบกำกับภาษีที่อ้างถึง',
 etax_to varchar(200) NOT NULL COMMENT 'อีเมลผู้ซื้อ',
 etax_subject varchar(255) NOT NULL COMMENT 'หัวข้ออีเมลตามรูปแบบ สพธอ.',
 etax_status varchar(1) NOT NULL COMMENT 'S ส่งแล้ว, F ไม่สำเร็จ',
 etax_msg varchar(255) DEFAULT NULL COMMENT 'ข้อความจาก mail server เมื่อไม่สำเร็จ',
 etax_msgid varchar(150) DEFAULT NULL COMMENT 'Message-ID ของอีเมลที่ส่ง',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (etax_id),
 KEY etax_doc (etax_doc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='e-Tax Invoice by Email log';

DROP TABLE IF EXISTS vr_mstr;
CREATE TABLE IF NOT EXISTS vr_mstr (
 vr_month char(7) NOT NULL COMMENT 'เดือนภาษี (YYYY-MM)',
 vr_output decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'ภาษีขายตามรายงานภาษีขาย',
 vr_input decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'ภาษีซื้อตามรายงานภาษีซื้อ',
 vr_carried decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'ภาษีชำระเกินยกมาจากเดือนก่อน',
 vr_net decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'บวก = ภาษีที่ต้องชำระ, ลบ = ชำระเกินยกไป',
 vr_date date NOT NULL COMMENT 'วันที่ลงบัญชีปิดภาษีซื้อ/ขาย',
 vr_remarks varchar(255) DEFAULT NULL COMMENT 'หมายเหตุ',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (vr_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ภ.พ.30 VAT returns (one per month)';

DROP TABLE IF EXISTS rtd_det;
DROP TABLE IF EXISTS rt_mstr;
CREATE TABLE IF NOT EXISTS rt_mstr (
 rt_nbr varchar(50) NOT NULL COMMENT 'เลขที่ใบส่งคืนสินค้า',
 rt_rc_nbr varchar(50) NOT NULL COMMENT 'ใบรับสินค้าที่ส่งคืน (rc_mstr)',
 rt_po_nbr varchar(50) DEFAULT NULL COMMENT 'ใบสั่งซื้อ',
 rt_vd_code varchar(50) NOT NULL COMMENT 'ผู้ขาย',
 rt_site varchar(50) NOT NULL COMMENT 'คลังที่ส่งของออก',
 rt_date date NOT NULL COMMENT 'วันที่ส่งคืน',
 rt_reason varchar(255) DEFAULT NULL COMMENT 'เหตุผลที่ส่งคืน',
 rt_net_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'มูลค่าตามราคาซื้อ (บาท) ไม่รวม VAT',
 rt_tax_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'ภาษีซื้อของส่วนที่คืน (บาท)',
 rt_tot_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'รวม (บาท)',
 rt_ap_ref varchar(50) DEFAULT NULL COMMENT 'ใบลดหนี้/ตั้งหนี้ของผู้ขายที่หักยอดนี้แล้ว (ap_mstr)',
 rt_status char(1) NOT NULL DEFAULT 'O' COMMENT 'O รอใบลดหนี้จากผู้ขาย, C หักหนี้แล้ว',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (rt_nbr), KEY rt_rc (rt_rc_nbr), KEY rt_vd (rt_vd_code, rt_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Purchase returns (ใบส่งคืนสินค้า)';
CREATE TABLE IF NOT EXISTS rtd_det (
 rtd_nbr varchar(50) NOT NULL COMMENT 'ใบส่งคืนสินค้า',
 rtd_line int NOT NULL COMMENT 'บรรทัด',
 rtd_rc_line int NOT NULL COMMENT 'บรรทัดของใบรับ (rcd_line)',
 rtd_part varchar(50) NOT NULL COMMENT 'สินค้า',
 rtd_qty decimal(15,5) NOT NULL COMMENT 'จำนวนที่คืน',
 rtd_um varchar(10) DEFAULT NULL COMMENT 'หน่วย',
 rtd_loc varchar(50) DEFAULT NULL COMMENT 'ตำแหน่งที่เอาของออก',
 rtd_lot varchar(50) DEFAULT '' COMMENT 'ล็อต',
 rtd_price decimal(18,5) NOT NULL DEFAULT 0 COMMENT 'ราคาซื้อต่อหน่วย (บาท)',
 rtd_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'มูลค่าตามราคาซื้อ (บาท)',
 rtd_cost decimal(18,5) NOT NULL DEFAULT 0 COMMENT 'ต้นทุนถัวเฉลี่ยที่ตัดออกจากคลัง',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (rtd_nbr, rtd_line)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Purchase return lines';

DROP TABLE IF EXISTS srd_det;
DROP TABLE IF EXISTS sr_mstr;
CREATE TABLE IF NOT EXISTS sr_mstr (
 sr_nbr varchar(50) NOT NULL COMMENT 'เลขที่ใบรับคืนสินค้า',
 sr_sh_nbr varchar(50) NOT NULL COMMENT 'ใบส่งของที่รับคืน (sh_mstr)',
 sr_so_nbr varchar(50) DEFAULT NULL COMMENT 'ใบสั่งขาย',
 sr_inv_nbr varchar(50) DEFAULT NULL COMMENT 'ใบกำกับภาษีของใบส่งของ',
 sr_cm_code varchar(50) NOT NULL COMMENT 'ลูกค้า',
 sr_site varchar(50) NOT NULL COMMENT 'คลังที่รับของเข้า',
 sr_date date NOT NULL COMMENT 'วันที่รับคืน',
 sr_reason varchar(255) DEFAULT NULL COMMENT 'เหตุผลที่ลูกค้าคืน',
 sr_cn_nbr varchar(50) DEFAULT NULL COMMENT 'ใบลดหนี้ที่ออกให้ลูกค้า (ih_mstr)',
 sr_status char(1) NOT NULL DEFAULT 'O' COMMENT 'O รอออกใบลดหนี้, C ออกใบลดหนี้แล้ว',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (sr_nbr), KEY sr_sh (sr_sh_nbr), KEY sr_cm (sr_cm_code, sr_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales returns (ใบรับคืนสินค้า)';
CREATE TABLE IF NOT EXISTS srd_det (
 srd_nbr varchar(50) NOT NULL COMMENT 'ใบรับคืนสินค้า',
 srd_line int NOT NULL COMMENT 'บรรทัด',
 srd_sh_line int NOT NULL COMMENT 'บรรทัดของใบส่งของ (shd_line)',
 srd_part varchar(50) NOT NULL COMMENT 'สินค้า',
 srd_qty decimal(15,5) NOT NULL COMMENT 'จำนวนที่รับคืน',
 srd_um varchar(10) DEFAULT NULL COMMENT 'หน่วย',
 srd_loc varchar(50) DEFAULT NULL COMMENT 'ตำแหน่งที่รับเข้า',
 srd_lot varchar(50) DEFAULT '' COMMENT 'ล็อต (ตามที่ส่งออกไป)',
 srd_cost decimal(18,5) NOT NULL DEFAULT 0 COMMENT 'ต้นทุนต่อหน่วยตอนส่งออก (กลับเข้าคลังที่ราคานี้)',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (srd_nbr, srd_line)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sales return lines';

DROP TABLE IF EXISTS dpa_det;
DROP TABLE IF EXISTS dp_mstr;
CREATE TABLE IF NOT EXISTS dp_mstr (
 dp_nbr varchar(50) NOT NULL COMMENT 'เลขที่ใบรับเงินมัดจำ',
 dp_so_nbr varchar(50) NOT NULL COMMENT 'ใบสั่งขายที่รับมัดจำ',
 dp_cm_code varchar(50) NOT NULL COMMENT 'ลูกค้า',
 dp_date date NOT NULL COMMENT 'วันที่รับเงิน (จุดความรับผิดภาษี)',
 dp_bank varchar(50) NOT NULL COMMENT 'สมุดธนาคารที่รับเงินเข้า',
 dp_tax_code varchar(20) DEFAULT NULL COMMENT 'รหัสภาษีตามใบสั่งขาย',
 dp_net_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'เงินมัดจำก่อนภาษี (บาท)',
 dp_tax_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'ภาษีขายของเงินมัดจำ',
 dp_tot_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'รับเงินทั้งหมด',
 dp_used_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'หักในใบกำกับภาษีแล้ว (ก่อนภาษี)',
 dp_status char(1) NOT NULL DEFAULT 'O' COMMENT 'O ยังหักไม่หมด, C หักครบแล้ว, X ยกเลิก',
 dp_remarks varchar(255) DEFAULT NULL COMMENT 'หมายเหตุ',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (dp_nbr), KEY dp_so (dp_so_nbr, dp_status), KEY dp_cm (dp_cm_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Customer deposits (ใบรับเงินมัดจำ / ใบกำกับภาษี)';
CREATE TABLE IF NOT EXISTS dpa_det (
 dpa_dp_nbr varchar(50) NOT NULL COMMENT 'ใบรับเงินมัดจำ',
 dpa_inv_nbr varchar(50) NOT NULL COMMENT 'ใบกำกับภาษีที่หักมัดจำ',
 dpa_amt decimal(18,2) NOT NULL DEFAULT 0 COMMENT 'ยอดมัดจำที่หัก (ก่อนภาษี)',
 created_by varchar(50), created_at timestamp DEFAULT CURRENT_TIMESTAMP,
 updated_by varchar(50), updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (dpa_dp_nbr, dpa_inv_nbr), KEY dpa_inv (dpa_inv_nbr)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Deposits deducted on tax invoices';

-- เงินสดย่อย: each user holds their own petty cash (a "box"); the ledger has one account (gl_pc_acct), the boxes are in cbh_hist
CREATE TABLE `cb_mstr` (
  `cb_nbr` varchar(50) NOT NULL COMMENT 'เลขที่รายการเงินสดย่อย (PC)',
  `cb_type` varchar(2) NOT NULL COMMENT 'WD = เบิกจากธนาคาร, RT = คืนเข้าธนาคาร, TF = รับเงินจากผู้ถืออื่น, TO = ส่งเงินให้ผู้ถืออื่น (คู่ของ TF ใน cb_ref), IN = รับเงิน (รายรับ), EX = จ่ายค่าใช้จ่าย, CT = ส่วนต่างจากการนับเงิน, DO = จ่ายตามเอกสาร, DI = รับตามเอกสาร',
  `cb_date` date NOT NULL COMMENT 'วันที่รายการ',
  `cb_holder` varchar(50) NOT NULL COMMENT 'ผู้ถือเงิน (us_userid) ผู้ทำรายการ',
  `cb_from` varchar(50) DEFAULT NULL COMMENT 'ผู้ส่งเงิน (TF: เงินออกจากกล่องของคนนี้)',
  `cb_bank` varchar(50) DEFAULT NULL COMMENT 'บัญชีธนาคาร (WD, RT)',
  `cb_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'เงินที่เข้าหรือออกจากกล่อง (EX = สุทธิหลังหัก ณ ที่จ่าย, CT: + เกิน / - ขาด)',
  `cb_payee` varchar(255) DEFAULT NULL COMMENT 'ผู้รับเงิน (EX) / ผู้จ่ายเงินให้ (IN)',
  `cb_acct` varchar(50) DEFAULT NULL COMMENT 'บัญชีรายได้ (IN)',
  `cb_ref` varchar(50) DEFAULT NULL COMMENT 'เอกสารที่จ่าย/รับเงินสดผ่านกองนี้ (DO / DI: จ่ายชำระ PV, รับชำระ CR, มัดจำ DP) — แก้/ยกเลิกที่เอกสารนั้น',
  `cb_rcpt_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ใบเสร็จ / บิล (EX: ต้องมีเลขนี้ ใบกำกับภาษี หรือไฟล์แนบ ก่อนนับเงินสิ้นเดือน)',
  `cb_vd_code` varchar(50) DEFAULT NULL COMMENT 'ผู้ขาย (บังคับเมื่อหัก ณ ที่จ่าย: ชื่อ ที่อยู่ และเลขผู้เสียภาษีใน 50 ทวิ)',
  `cb_tax_id` varchar(20) DEFAULT NULL COMMENT 'เลขผู้เสียภาษีของผู้ออกใบกำกับภาษี',
  `cb_tax_branch` varchar(10) DEFAULT NULL COMMENT 'สาขาของผู้ออกใบกำกับภาษี',
  `cb_inv_nbr` varchar(50) DEFAULT NULL COMMENT 'เลขที่ใบกำกับภาษีเต็มรูป (มี = ขอคืนภาษีซื้อ)',
  `cb_inv_date` date DEFAULT NULL COMMENT 'วันที่ใบกำกับภาษี',
  `cb_tax_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษีซื้อ',
  `cb_net_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ค่าใช้จ่ายก่อน VAT',
  `cb_vat_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ภาษีซื้อ',
  `cb_wht_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ภาษีหัก ณ ที่จ่าย',
  `cb_wht_form` varchar(20) DEFAULT NULL COMMENT 'PND3 / PND53',
  `cb_status` varchar(1) NOT NULL DEFAULT 'A' COMMENT 'A = ใช้ได้, X = ยกเลิก',
  `cb_remarks` varchar(255) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cb_nbr`),
  KEY `cb_holder` (`cb_holder`, `cb_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Petty cash transactions';

CREATE TABLE `cbd_det` (
  `cbd_nbr` varchar(50) NOT NULL,
  `cbd_line` int(11) NOT NULL,
  `cbd_acct` varchar(50) NOT NULL COMMENT 'บัญชีค่าใช้จ่าย',
  `cbd_cc` varchar(50) DEFAULT NULL COMMENT 'ศูนย์ต้นทุน',
  `cbd_desc` varchar(255) DEFAULT NULL,
  `cbd_amt` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'จำนวนเงินก่อน VAT',
  `cbd_tax_code` varchar(20) DEFAULT NULL COMMENT 'ภาษีซื้อของบรรทัด (มี = ต้องมีใบกำกับภาษีเต็มรูป)',
  `cbd_wht_code` varchar(20) DEFAULT NULL COMMENT 'รหัสภาษีหัก ณ ที่จ่ายของบรรทัด (ฐาน = จำนวนเงินก่อน VAT)',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cbd_nbr`, `cbd_line`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Petty cash expense lines';

CREATE TABLE `cbh_hist` (
  `cbh_id` int(11) NOT NULL AUTO_INCREMENT,
  `cbh_holder` varchar(50) NOT NULL COMMENT 'กล่องของผู้ถือ',
  `cbh_date` date NOT NULL,
  `cbh_nbr` varchar(50) NOT NULL COMMENT 'เลขที่รายการ (หนึ่งแถวต่อรายการ)',
  `cbh_amt` decimal(18,2) NOT NULL COMMENT '+ เข้ากล่อง / - ออกจากกล่อง',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cbh_id`),
  KEY `cbh_holder` (`cbh_holder`, `cbh_date`),
  KEY `cbh_nbr` (`cbh_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Petty cash register: each holder''s box (balance = sum)';

CREATE TABLE `cbc_mstr` (
  `cbc_holder` varchar(50) NOT NULL,
  `cbc_month` char(7) NOT NULL COMMENT 'เดือนที่นับ yyyy-mm',
  `cbc_book` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ยอดตามทะเบียน ณ สิ้นเดือน',
  `cbc_counted` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ยอดที่นับได้ (ยกไปเดือนถัดไป)',
  `cbc_diff` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'ส่วนต่าง: + เกิน / - ขาด',
  `cbc_notes` varchar(500) DEFAULT NULL COMMENT 'จำนวนธนบัตรและเหรียญ (JSON ราคา => จำนวน)',
  `cbc_ct_nbr` varchar(50) DEFAULT NULL COMMENT 'รายการส่วนต่าง (CT)',
  `cbc_remarks` varchar(255) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้นับ',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cbc_holder`, `cbc_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Petty cash counted at month end (locks the month)';

-- files attached to a document (receipts, slips): kept in storage/uploads/<database>/attach, served after a permission check
CREATE TABLE `att_det` (
  `att_id` int(11) NOT NULL AUTO_INCREMENT,
  `att_doc` varchar(10) NOT NULL COMMENT 'ชนิดเอกสาร เช่น PC',
  `att_nbr` varchar(50) NOT NULL COMMENT 'เลขที่เอกสาร',
  `att_name` varchar(255) NOT NULL COMMENT 'ชื่อไฟล์เดิม',
  `att_file` varchar(100) NOT NULL COMMENT 'ชื่อไฟล์ที่เก็บ',
  `att_mime` varchar(100) NOT NULL,
  `att_size` int(11) NOT NULL DEFAULT 0,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`att_id`),
  KEY `att_doc` (`att_doc`, `att_nbr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Attached files';

CREATE TABLE `sig_det` (
  `sig_id` int(11) NOT NULL AUTO_INCREMENT,
  `sig_path` varchar(200) NOT NULL COMMENT 'หน้าพิมพ์ของเอกสาร เช่น /invoices/IV2026100001/print',
  `sig_slot` tinyint(4) NOT NULL COMMENT 'ช่องลงนามที่ (เริ่ม 0 จากซ้าย)',
  `sig_label` varchar(100) NOT NULL COMMENT 'ชื่อช่องลงนาม',
  `sig_title` varchar(255) NOT NULL COMMENT 'ชื่อเอกสารและเลขที่ สำหรับรายการ',
  `sig_userid` varchar(50) NOT NULL COMMENT 'ผู้ลงนาม',
  `sig_status` char(1) NOT NULL DEFAULT 'R' COMMENT 'R รอเซ็น, S เซ็นแล้ว, X ไม่อนุมัติ, C ยกเลิก',
  `sig_note` varchar(255) DEFAULT NULL COMMENT 'ข้อความถึงผู้เซ็น หรือเหตุผลที่ไม่อนุมัติ',
  `sig_req_by` varchar(50) DEFAULT NULL COMMENT 'ผู้ขอให้เซ็น (ว่าง = เซ็นเอง)',
  `sig_at` datetime DEFAULT NULL COMMENT 'เวลาที่เซ็นหรือไม่อนุมัติ',
  `sig_ip` varchar(45) DEFAULT NULL,
  `sig_hash` char(64) DEFAULT NULL COMMENT 'ลายนิ้วมือของเนื้อหาเอกสารตอนเซ็น: แก้เอกสารแล้วลายเซ็นไม่แสดง',
  `sig_image` mediumtext DEFAULT NULL COMMENT 'ภาพลายเซ็นตอนเซ็น (data URL) เปลี่ยนลายเซ็นภายหลังไม่กระทบเอกสารเก่า',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sig_id`),
  KEY `sig_path` (`sig_path`, `sig_slot`),
  KEY `sig_userid` (`sig_userid`, `sig_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Signatures on printed documents';

-- ------------------------------------------------------------------ e-Document Sign (Esign\Envelope)

CREATE TABLE `env_ctrl` (
  `ctrl_id` int(11) NOT NULL DEFAULT 1,
  `env_prefix` varchar(10) DEFAULT 'ENV' COMMENT 'คำนำหน้าเลขที่ซองเอกสาร',
  `env_next_nbr` int(11) DEFAULT 1 COMMENT 'เลข Running ซองเอกสาร',
  `env_format` varchar(10) DEFAULT 'YM4' COMMENT 'รูปแบบเลขที่: N6=PO000001, YM4=PO2026100001 (เริ่มใหม่ทุกเดือน), yM4=PO26100001, Y5=PO202600001 (เริ่มใหม่ทุกปี)',
  `env_period` varchar(6) DEFAULT NULL COMMENT 'งวดของเลขถัดไป (yyyymm หรือ yyyy) ใช้เริ่มนับใหม่',
  `env_remote_on` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ส่งคนนอกผ่านบริการ Phai Sign (ผู้ดูแลเปิดเอง)',
  `env_remote_url` varchar(255) NOT NULL DEFAULT 'https://phai.monrak.net' COMMENT 'ที่อยู่บริการ',
  `env_remote_id` varchar(24) DEFAULT NULL COMMENT 'เลขเครื่องที่ลงทะเบียนกับบริการ',
  `env_remote_key` varchar(64) DEFAULT NULL COMMENT 'กุญแจ HMAC ของเครื่อง',
  `env_remote_name` varchar(150) DEFAULT NULL COMMENT 'ชื่อบริษัทที่ลงทะเบียน',
  `env_remote_days` smallint(6) NOT NULL DEFAULT 14 COMMENT 'ลิงก์ของคนนอกใช้ได้กี่วัน',
  `env_witness_on` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'บันทึก SHA-256 ของฉบับสุดท้ายที่บริการเป็นพยาน',
  `env_remote_sync` datetime DEFAULT NULL COMMENT 'ถามผลจากบริการครั้งล่าสุด',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ctrl_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='e-Document Sign settings';

CREATE TABLE `env_mstr` (
  `env_nbr` varchar(20) NOT NULL COMMENT 'เลขที่ซองเอกสาร',
  `env_title` varchar(255) NOT NULL COMMENT 'ชื่อซอง',
  `env_note` text DEFAULT NULL COMMENT 'ข้อความถึงผู้ลงนาม',
  `env_status` char(1) NOT NULL DEFAULT 'D' COMMENT 'D ร่าง, S กำลังเซ็น, C เสร็จ, X ไม่อนุมัติ, W ถอน',
  `env_step` smallint(6) NOT NULL DEFAULT 0 COMMENT 'ขั้นที่กำลังเซ็น',
  `env_sent_at` datetime DEFAULT NULL,
  `env_done_at` datetime DEFAULT NULL COMMENT 'เวลาที่เสร็จ ไม่อนุมัติ หรือถอน',
  `env_pages` int(11) NOT NULL DEFAULT 0 COMMENT 'จำนวนหน้าของฉบับสุดท้าย',
  `env_final_file` varchar(64) DEFAULT NULL COMMENT 'ไฟล์ฉบับสุดท้าย (storage/uploads/<db>/esign)',
  `env_final_hash` char(64) DEFAULT NULL COMMENT 'SHA-256 ของฉบับสุดท้าย',
  `env_final_size` int(11) DEFAULT NULL,
  `env_page_hash` mediumtext DEFAULT NULL COMMENT 'ลายนิ้วมือรายหน้าของฉบับสุดท้าย (JSON)',
  `env_chain` char(64) DEFAULT NULL COMMENT 'hash ของเหตุการณ์ล่าสุด (ปลายโซ่ใน envh_hist)',
  `env_remote_final` varchar(24) DEFAULT NULL COMMENT 'ส่งฉบับสุดท้ายให้คนนอกผ่านบริการแล้ว (เลขที่บริการ)',
  `env_witness_id` varchar(24) DEFAULT NULL COMMENT 'เลขที่ใบรับพยานจากบริการ',
  `env_witness_at` datetime DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL COMMENT 'ผู้ส่ง',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`env_nbr`),
  KEY `env_owner` (`created_by`, `env_status`),
  KEY `env_final_hash` (`env_final_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='e-Document Sign envelopes';

CREATE TABLE `envf_det` (
  `envf_id` int(11) NOT NULL AUTO_INCREMENT,
  `envf_nbr` varchar(20) NOT NULL,
  `envf_seq` smallint(6) NOT NULL DEFAULT 1 COMMENT 'ลำดับในซอง',
  `envf_kind` char(1) NOT NULL DEFAULT 'M' COMMENT 'M เอกสารหลัก (มีช่องเซ็น), A เอกสารแนบท้าย',
  `envf_name` varchar(255) NOT NULL COMMENT 'ชื่อไฟล์ที่อัปโหลด',
  `envf_src_mime` varchar(50) NOT NULL COMMENT 'ชนิดของไฟล์ที่อัปโหลด',
  `envf_src_hash` char(64) NOT NULL COMMENT 'SHA-256 ของไฟล์ที่อัปโหลด',
  `envf_src_size` int(11) NOT NULL,
  `envf_file` varchar(64) NOT NULL COMMENT 'PDF ที่ทำความสะอาดแล้ว (สิ่งที่ส่งเซ็น)',
  `envf_hash` char(64) NOT NULL COMMENT 'SHA-256 ของ PDF ที่ทำความสะอาดแล้ว',
  `envf_size` int(11) NOT NULL,
  `envf_pages` int(11) NOT NULL COMMENT 'จำนวนหน้า',
  `envf_sizes` text NOT NULL COMMENT 'ขนาดแต่ละหน้า [[กว้าง, สูง], ...] หน่วย pt (JSON)',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`envf_id`),
  KEY `envf_nbr` (`envf_nbr`, `envf_seq`),
  KEY `envf_hash` (`envf_hash`),
  KEY `envf_src_hash` (`envf_src_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Files of an envelope';

CREATE TABLE `envs_det` (
  `envs_id` int(11) NOT NULL AUTO_INCREMENT,
  `envs_nbr` varchar(20) NOT NULL,
  `envs_step` smallint(6) NOT NULL DEFAULT 1 COMMENT 'ขั้น: ขั้นถัดไปเริ่มเมื่อขั้นนี้เซ็นครบ',
  `envs_seq` smallint(6) NOT NULL DEFAULT 1,
  `envs_role` char(1) NOT NULL DEFAULT 'S' COMMENT 'S ลงนาม, A อนุมัติ, C รับทราบ (ได้ฉบับสุดท้าย)',
  `envs_kind` char(1) NOT NULL DEFAULT 'U' COMMENT 'U ผู้ใช้ในระบบ, E คนนอก (อีเมล)',
  `envs_userid` varchar(50) DEFAULT NULL,
  `envs_name` varchar(150) NOT NULL,
  `envs_email` varchar(150) DEFAULT NULL,
  `envs_position` varchar(150) DEFAULT NULL COMMENT 'ตำแหน่ง',
  `envs_color` varchar(7) DEFAULT NULL COMMENT 'สีของผู้ลงนามในตัวแก้เอกสาร',
  `envs_status` char(1) NOT NULL DEFAULT 'W' COMMENT 'W ยังไม่ถึงคิว, R ถึงคิวแล้ว, S เซ็นแล้ว, X ไม่อนุมัติ, C ถูกแทนหรือยกเลิก',
  `envs_recv_at` datetime DEFAULT NULL COMMENT 'รับ',
  `envs_open_at` datetime DEFAULT NULL COMMENT 'เปิด',
  `envs_sign_at` datetime DEFAULT NULL COMMENT 'เซ็นหรือไม่อนุมัติ',
  `envs_ip` varchar(45) DEFAULT NULL,
  `envs_agent` varchar(255) DEFAULT NULL,
  `envs_note` varchar(255) DEFAULT NULL COMMENT 'เหตุผลที่ไม่อนุมัติ',
  `envs_hash` char(64) DEFAULT NULL COMMENT 'ลายนิ้วมือของฉบับที่เห็นตอนเซ็น',
  `envs_image` mediumtext DEFAULT NULL COMMENT 'ภาพลายเซ็นตอนเซ็น (data URL)',
  `envs_replaces` int(11) DEFAULT NULL COMMENT 'แทนผู้ลงนาม envs_id',
  `envs_ext` varchar(24) DEFAULT NULL COMMENT 'เลขคำขอที่บริการ Phai Sign (คนนอก)',
  `envs_ext_state` char(1) DEFAULT NULL COMMENT 'สถานะที่บริการ: R รอ, E ลิงก์หมดอายุ, L ล็อก, S เซ็น, X ไม่อนุมัติ, W ถอน, D ลบสำเนาแล้ว',
  `envs_ext_err` varchar(255) DEFAULT NULL COMMENT 'ส่งไปบริการไม่สำเร็จ',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`envs_id`),
  KEY `envs_nbr` (`envs_nbr`, `envs_step`),
  KEY `envs_userid` (`envs_userid`, `envs_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Signers of an envelope, in steps';

CREATE TABLE `envx_det` (
  `envx_id` int(11) NOT NULL AUTO_INCREMENT,
  `envx_nbr` varchar(20) NOT NULL,
  `envx_signer` int(11) DEFAULT NULL COMMENT 'envs_id ของผู้กรอก, ว่าง = สิ่งที่ผู้ส่งใส่เอง',
  `envx_page` smallint(6) NOT NULL COMMENT 'หน้าในเอกสารหลัก (เริ่ม 1)',
  `envx_type` varchar(20) NOT NULL COMMENT 'signature, initials, name, position, date, timeline, stamp, text, checkbox, label, mark',
  `envx_x` decimal(8,2) NOT NULL COMMENT 'จากซ้าย (pt)',
  `envx_y` decimal(8,2) NOT NULL COMMENT 'จากบน (pt)',
  `envx_w` decimal(8,2) NOT NULL,
  `envx_h` decimal(8,2) NOT NULL,
  `envx_props` text DEFAULT NULL COMMENT 'ข้อความ ขนาดตัวอักษร ตัวหนา จัดแนว สี ต้องกรอก (JSON)',
  `envx_value` text DEFAULT NULL COMMENT 'ค่าที่ผู้ลงนามกรอก',
  `envx_link` varchar(20) DEFAULT NULL COMMENT 'ช่องที่คัดลอกแบบเชื่อมกันมีค่าเดียวกัน',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`envx_id`),
  KEY `envx_nbr` (`envx_nbr`, `envx_page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fields placed on the pages of an envelope';

CREATE TABLE `envh_hist` (
  `envh_id` int(11) NOT NULL AUTO_INCREMENT,
  `envh_nbr` varchar(20) NOT NULL,
  `envh_at` datetime NOT NULL COMMENT 'เวลาของเซิร์ฟเวอร์',
  `envh_event` varchar(20) NOT NULL COMMENT 'created, sent, received, opened, signed, declined, reminded, replaced, recalled, completed, delivered',
  `envh_signer` int(11) DEFAULT NULL COMMENT 'envs_id',
  `envh_userid` varchar(50) DEFAULT NULL COMMENT 'ผู้ทำ',
  `envh_ip` varchar(45) DEFAULT NULL,
  `envh_agent` varchar(255) DEFAULT NULL,
  `envh_detail` text DEFAULT NULL COMMENT 'รายละเอียด (JSON)',
  `envh_prev` char(64) NOT NULL COMMENT 'hash ของเหตุการณ์ก่อนหน้า',
  `envh_hash` char(64) NOT NULL COMMENT 'hash ของเหตุการณ์นี้ รวม envh_prev: แก้แถวใดโซ่ขาด',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`envh_id`),
  KEY `envh_nbr` (`envh_nbr`, `envh_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Events of an envelope, a hash chain';
