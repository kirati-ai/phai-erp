# ความปลอดภัย / Security

## แจ้งช่องโหว่

กรุณาแจ้งเป็นการส่วนตัวกับผู้ดูแลโปรเจกต์ อย่าเปิดเป็น issue สาธารณะ ระบุรุ่น ขั้นตอนที่ทำให้เกิด และผลกระทบ

*Please report vulnerabilities privately to the maintainers, not as a public issue. Include the version, steps to reproduce and the impact.*

## สิ่งที่ระบบป้องกัน

| เรื่อง | วิธี |
|---|---|
| SQL injection | prepared statement ทุกคำสั่ง ชื่อคอลัมน์สำหรับเรียงลำดับมาจาก whitelist เท่านั้น |
| XSS | escape ทุกค่าที่แสดงในหน้าเว็บ (`e()` ฝั่ง PHP, `Phai.esc()` ฝั่ง JS) ข้อมูล JSON ในหน้าใช้ `JSON_HEX_TAG` |
| CSRF | token ต่อ session ทุกคำขอที่แก้ข้อมูล (รวมการเข้าสู่ระบบ) token เปลี่ยนเมื่อเข้าสู่ระบบ |
| สิทธิ์ | ทุก route ต้องเข้าสู่ระบบ ทุกหน้าจอและ API ตรวจสิทธิ์ (ดู เพิ่ม แก้ ลบ พิมพ์ อนุมัติ) ชุดเดียวกัน รวมถึง AI |
| รหัสผ่าน | `password_hash()` ไม่ส่ง hash ออกจากเซิร์ฟเวอร์ ล็อกชั่วคราวเมื่อใส่ผิด 5 ครั้งใน 15 นาที |
| Session | cookie `HttpOnly`, `SameSite=Lax`, `Secure` เมื่อใช้ HTTPS เปลี่ยน session id เมื่อเข้าสู่ระบบ |
| ไฟล์ | ให้บริการเฉพาะ `public/` — `config.php`, `src/`, `database/`, `storage/` เปิดจากเว็บไม่ได้ |
| ความลับ | รหัสผ่านฐานข้อมูลและ API key อยู่ใน `config.php` เท่านั้น (ไม่อยู่ใน git ไม่อยู่ในฐานข้อมูล) |
| AI | เรียกได้เฉพาะ API อ่านข้อมูลที่ผู้ใช้มีสิทธิ์ ผ่าน router เดียวกับหน้าเว็บ แก้ไขข้อมูลไม่ได้ |
| Header | `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: same-origin` |

ทุกข้อด้านบนมีการทดสอบอัตโนมัติใน `tests/e2e/10-security.php` รวมถึงไล่เรียกทุก route ทั้งแบบไม่เข้าสู่ระบบและแบบไม่มีสิทธิ์ — รันด้วย `php tests/e2e/run.php security`

## ผู้ดูแลระบบควรทำ

- ใช้ HTTPS และตั้ง `app.debug` เป็น `false`
- ชี้ document root ไปที่ `public/`
- ใช้ผู้ใช้ฐานข้อมูลเฉพาะของ PhaiERP
- สำรองฐานข้อมูลทุกวัน
