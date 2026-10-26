# PhaiERP

ERP โอเพนซอร์ส เขียนด้วย PHP ล้วน สำหรับธุรกิจขนาดเล็กถึงกลาง — เบา เร็ว ติดตั้งง่าย และถามข้อมูลกับ AI ได้

ครอบคลุม จัดซื้อ ขาย คลังสินค้า ผลิต และบัญชี รองรับ VAT ภาษีหัก ณ ที่จ่าย (50 ทวิ) ใบวางบิล และที่อยู่ตามจังหวัด อำเภอ ตำบล

*Open-source ERP in plain PHP for small and medium businesses: purchasing, sales, inventory, production and accounting (Thai VAT and withholding tax), with a built-in AI assistant. English docs: [install](docs/install.en.md) · [user guide](docs/usage.en.md).*

**รุ่น 1.0.0** — ดู [CHANGELOG.md](CHANGELOG.md)

## จุดเด่น

- PHP 8.2+ และ MySQL/MariaDB เท่านั้น ไม่ต้องใช้ Composer, npm หรือ build
- หน้าจอกะทัดรัด ทำงานผ่าน JSON API ทุกหน้าจอมี API คู่กัน
- สต็อก ต้นทุนถัวเฉลี่ย และบัญชีแยกประเภท ลงให้อัตโนมัติจากทุกเอกสาร
- สิทธิ์ต่อหน้าจอ (ดู เพิ่ม แก้ ลบ พิมพ์ อนุมัติ) และประวัติการแก้ไขทุกรายการ
- ถาม AI (Claude หรือ Gemini) ด้วยภาษาคน — AI อ่านได้เฉพาะข้อมูลที่ผู้ถามมีสิทธิ์ และแก้ไขข้อมูลไม่ได้
- ข้อมูลตัวอย่างสำหรับทดลองใช้ ติดตั้งพร้อมกันได้ในคลิกเดียว

## เริ่มใช้

1. วางโฟลเดอร์ไว้ใต้ web root (หรือชี้เว็บเซิร์ฟเวอร์ไปที่ `public/`)
2. เปิดเว็บ — ระบบพาไปหน้าติดตั้ง กรอกฐานข้อมูล บริษัท และผู้ดูแลระบบ (ติ๊ก **ใส่ข้อมูลตัวอย่าง** ถ้าต้องการลองใช้)
3. เข้าสู่ระบบ

รายละเอียด: [คู่มือติดตั้ง](docs/install.th.md) · [คู่มือการใช้งาน](docs/usage.th.md) · [ความปลอดภัย](SECURITY.md)

## สำหรับนักพัฒนา

```bash
php tests/run.php          # unit tests
php tests/e2e/run.php      # ทดสอบทั้งระบบผ่านเว็บ รวมความปลอดภัย (ใช้ฐานข้อมูลทดสอบแยก)
php bin/phai version
php bin/phai demo
```

```
public/      จุดเข้าเว็บ, css, js
src/Core/    แกนกลาง (Http, Routing, Db, Validation, Auth, View, Audit, Ai)
src/Modules/ โมดูลธุรกิจ (Master, Inventory, Purchase, Sales, Production, Accounting, System)
src/Install/ หน้าติดตั้งและข้อมูลตัวอย่าง
views/       หน้าจอ
lang/        ไฟล์ภาษา (th, en)
database/    schema และข้อมูลตั้งต้น
docs/        คู่มือ
```

แนวทางการเขียนโค้ดอยู่ใน [CLAUDE.md](CLAUDE.md) และแผนการพัฒนาใน [PHAIERP_PLAN.md](PHAIERP_PLAN.md)

## สัญญาอนุญาต

MIT ดูไฟล์ [LICENSE](LICENSE) — Copyright (c) 2026 Monrak Net Co., Ltd.
