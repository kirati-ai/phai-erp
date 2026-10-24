# ข้อเสนอการพัฒนาระบบ e-Tax Invoice & e-Receipt สำหรับ PhaiERP

> **เอกสารวางแผนและสถาปัตยกรรม (Architecture & Implementation Proposal)**  
> สถานะ: เสนอแนะสำหรับแผนงานระยะถัดไป (Post v1.0 Roadmap)  
> มาตรฐานอ้างอิง: กรมสรรพากร (RD) และสำนักงานพัฒนาธุรกรรมทางอิเล็กทรอนิกส์ (ETDA)

> **สถานะปัจจุบัน:** ทำแล้วแบบ **e-Tax Invoice by Email** (ส่งอีเมลพร้อม PDF สำเนาถึง สพธอ., หน้าตั้งค่า SMTP, ประวัติการส่ง) — วิธีใช้อยู่ใน `usage.th.md` และแบบ **XML ลงลายมือชื่อ XAdES-BES** สำหรับ Upload XML ที่ etax.rd.go.th (ใบกำกับภาษี 388, ใบลดหนี้ 81, ใบเพิ่มหนี้ 80, ใบรับ T01, zip รายเดือน) — ยังไม่ทำ: ใบแจ้งยกเลิก (T07), Host-to-Host / Service Provider, การฝัง XML ใน PDF/A-3 — ส่วน PDF ที่ส่งทางอีเมลเป็น PDF/A-3B แล้ว (`Core\PdfA`, ตรวจด้วย veraPDF)

---

## 1. ที่มาและความสำคัญ (Background & Objectives)

ระบบใบกำกับภาษีอิเล็กทรอนิกส์และใบเสร็จรับเงินอิเล็กทรอนิกส์ (**e-Tax Invoice & e-Receipt**) เป็นก้าวสำคัญของธุรกิจไทยในการลดการใช้กระดาษ ลดค่าใช้จ่ายในการจัดส่งทางไปรษณีย์ และเตรียมพร้อมต่อมาตรการทางภาษีของกรมสรรพากร (เช่น โครงการ Easy E-Receipt หรือการบังคับใช้ในอนาคต)

PhaiERP มีโครงสร้างข้อมูลด้านการขาย, ลูกหนี้, ภาษีขาย (ภ.พ. 30), หัก ณ ที่จ่าย และข้อมูลลูกค้า/บริษัทที่สมบูรณ์อยู่แล้ว การต่อยอดให้สามารถออกและส่ง e-Tax Invoice ได้โดยตรงจะเพิ่มมูลค่าและขีดความสามารถในการแข่งขันให้กับ SME อย่างมหาศาล

---

## 2. รูปแบบการเชื่อมต่อของกรมสรรพากร (Two Main Approaches)

กรมสรรพากรแบ่งรูปแบบการจัดทำและนำส่งเป็น 2 รูปแบบหลัก:

| หัวข้อ | 1. e-Tax Invoice by Email | 2. e-Tax Invoice & e-Receipt (Web Service / XML) |
|---|---|---|
| **กลุ่มเป้าหมาย** | ผู้ประกอบการรายย่อย (นิติบุคคล/บุคคลธรรมดา) ที่มีรายได้ไม่เกิน 30 ล้านบาท/ปี | นิติบุคคลทุกขนาดที่มีความพร้อมทางไอที หรือธุรกิจที่มีปริมาณเอกสารสูง |
| **วิธีการทำงาน** | สร้าง PDF ใบกำกับภาษี ส่งอีเมลไปยังระบบของ สพธอ. (ETDA) เพื่อประทับรับรองเวลา (Time Stamp) แล้วระบบจะส่งต่อไปยังผู้ซื้อและสรรพากรอัตโนมัติ | จัดทำไฟล์ XML ตามมาตรฐาน ETDA + PDF/A-3 และลงลายมือชื่อดิจิทัล (Digital Signature) ด้วย Certificate นำส่งผ่าน Web Service หรือ Host-to-Host |
| **อุปกรณ์/ใบรับรอง** | ไม่ต้องใช้ Digital Certificate ขั้นสูง เพียงลงทะเบียนอีเมลกับสรรพากร | ต้องมี Digital Certificate (e-Token หรือ HSM) ออกโดย CA ที่เชื่อถือได้ (เช่น INET, Thai Digital ID) |
| **ความเหมาะสมกับ PhaiERP** | **ทำได้ทันทีใน Phase 1**: ออกแบบ Template PDF/A ตามรูปแบบ และส่งอีเมลพร้อมหัวข้อตามข้อกำหนด สพธอ. | **Phase 2**: สร้าง XML Generator ตาม Schema และเชื่อมต่อ Service Provider API หรือเซ็นเอกสารเอง |

---

## 3. สเปกทางเทคนิคที่ต้องรองรับ (Technical Specifications)

### 3.1 มาตรฐานเอกสาร XML (ETDA Data Schema)
เอกสารต้องจัดโครงสร้างตามมาตรฐานข้อความ XML ของ ETDA:
- **TIV (Tax Invoice)**: ใบกำกับภาษี
- **REC (Receipt)**: ใบเสร็จรับเงิน
- **CCN (Credit Note)**: ใบลดหนี้
- **CDN (Debit Note)**: ใบเพิ่มหนี้
- **INV (Invoice)**: ใบแจ้งหนี้

ข้อมูลจำเป็นใน XML:
- รหัสประเภทเอกสาร (Document Type Code)
- ข้อมูลผู้ขายและผู้ซื้อ: เลขประจำตัวผู้เสียภาษี 13 หลัก (`TaxID`), รหัสสาขา 5 หลัก (`00000` = สำนักงานใหญ่), ชื่อ, ที่อยู่ไทย
- สกุลเงิน, อัตราแลกเปลี่ยน, มูลค่ารวมก่อนภาษี, อัตราภาษี (7%), ภาษีมูลค่าเพิ่ม, ยอดสุทธิ
- เหตุผลในการออกใบลดหนี้/ใบเพิ่มหนี้ (Reason Code ตามประกาศสรรพากร)

### 3.2 ไฟล์รูปแบบ PDF/A-3
- แปลงเอกสารเป็น **PDF/A-3 (ISO 19005-3)** ซึ่งอนุญาตให้ฝังไฟล์ XML (`etax.xml`) เข้าไปเป็นไฟล์แนบในตัว PDF
- ลงลายมือชื่อดิจิทัล (Digital Signature - PAdES) บนตัว PDF และ XAdES บน XML

---

## 4. แผนสถาปัตยกรรมใน PhaiERP (Architecture Design)

```
[ เอกสารขายใน PhaiERP ]
 (invoices / ar-invoices / cash-receipts)
          │
          ▼
┌────────────────────────────────────────────────────────┐
│ PhaiERP e-Tax Core Module                              │
│                                                        │
│ 1. Builder: ดึงข้อมูลและสร้าง ETDA XML (Pure PHP)      │
│ 2. PDF/A-3 Embedder: ผนึก XML ลงใน PDF ของเอกสาร      │
│ 3. Signer: ลงลายมือชื่อ (ผ่าน Local OpenSSL / Provider)│
│ 4. Dispatcher:                                         │
│    - Mode A: ส่งอีเมล (e-Tax by Email)                 │
│    - Mode B: ส่งผ่าน Service Provider API (e.g. INET)  │
│    - Mode C: Export XML/PDF ให้ผู้ใช้ดาวน์โหลด         │
└────────────────────────────────────────────────────────┘
          │
          ├──► บันทึก Log ลงตาราง `etax_log`
          └──► ส่งต่อไปยัง ผู้ซื้อ (Customer Email) / สรรพากร
```

### 4.1 ตารางฐานข้อมูลที่ต้องเพิ่ม (Database Additions)
1. **`etax_log`**:
   - `etax_id` (PK)
   - `etax_doc_type`: `INV`, `TIV`, `REC`, `CCN`
   - `etax_doc_nbr`: เลขที่เอกสารอ้างอิง (เช่น `IV26100001`)
   - `etax_status`: `pending`, `signed`, `submitted`, `rejected`, `cancelled`
   - `etax_uuid`: Document Unique Identifier
   - `etax_hash`: SHA-256 Digest
   - `etax_xml_path`: พาธเก็บไฟล์ XML
   - `etax_pdf_path`: พาธเก็บไฟล์ PDF/A-3
   - `etax_response_code` / `etax_response_msg`: สถานะตอบรับจากระบบสรรพากรหรือ Service Provider
   - `created_at`, `created_by`
2. **`etax_ctrl`**:
   - ตั้งค่าโหมดการทำงาน (`email`, `provider_api`, `self_signed`, `manual_export`)
   - API Endpoint, API Key / Secret Token
   - ข้อมูลการตั้งค่า Certificate (ถ้ามี)
   - Template อีเมลสำหรับจัดส่ง

### 4.2 ส่วนติดต่อผู้ใช้งาน (UI Integration)
- **หน้าจอเอกสาร (Invoice / Receipt / CN)**:
  - เพิ่มปุ่ม **"ออก e-Tax Invoice"** (แสดงเฉพาะสถานะที่อนุมัติ/ผ่านบัญชีแล้ว)
  - Badge แสดงสถานะ e-Tax: `ยังไม่ออก`, `ส่งสำเร็จ`, `เกิดข้อผิดพลาด`
  - ปุ่มดาวน์โหลด: `PDF/A-3` และ `XML (ETDA)`
- **หน้ารายการ (List View)**:
  - เพิ่ม Filter กรองตามสถานะ e-Tax เพื่อให้ฝ่ายบัญชีตรวจสอบเอกสารที่ยังตกค้างได้ง่าย
- **หน้าตั้งค่า (`/settings/etax`)**:
  - เมนูตั้งค่าระบบ e-Tax Invoice ใต้หมวดบัญชีหรือตั้งค่าระบบ

---

## 5. แผนการดำเนินงานแบ่งเป็นระยะ (Phased Roadmap)

1. **Phase 1: XML Builder & Manual Export (ไม่ต้องพึ่งพาระบบภายนอก)**
   - เขียน Class สำหรับสร้าง XML ตามโครงสร้าง ETDA 3.0 ด้วย Pure PHP (`SimpleXMLElement` / `DOMDocument`)
   - สร้างฟังก์ชัน Export XML และตรวจสอบความถูกต้องของ Schema (XSD Validation)
   - ปรับแบบพิมพ์ PDF ให้ตรงตามมาตรฐานฟอร์มใบกำกับภาษีอิเล็กทรอนิกส์

2. **Phase 2: e-Tax by Email Integration**
   - รองรับการตั้งค่า Mail Server (SMTP) ในระบบ
   - ฟังก์ชันส่งอีเมลอัตโนมัติแนบ PDF ไปยังอีเมลของลูกค้า และ CC ไปยังอีเมลของ สพธอ. ตามรูปแบบที่สรรพากรกำหนด

3. **Phase 3: Service Provider API Adapter**
   - สร้าง Adapter Interface เพื่อเชื่อมต่อกับ e-Tax Service Providers ยอดนิยมในไทย (เช่น INET e-Tax, PromptTax, Leceipt ฯลฯ)
   - มีระบบ Webhook รับสถานะการอนุมัติ/ตีกลับมาอัปเดตในระบบ PhaiERP อัตโนมัติ
