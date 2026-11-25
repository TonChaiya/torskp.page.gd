# TOR SKP — ระบบจัดทำเอกสารจัดซื้อยา

ระบบ PHP/MySQL สำหรับชุดเอกสารจัดซื้อเวชภัณฑ์ยา ภาษาไทย รองรับฟอร์มราชการ การจัดเก็บข้อมูลตามชุดจัดซื้อ การพิมพ์ PDF และดาวน์โหลด Word แยกรายหัวข้อหรือรวมทั้งชุด

**Baseline v1.1.0 (24 พฤศจิกายน 2569)** — commit รุ่น `2d85b762a006864cb3d5090909097bcb65cdf81f`; main หลังนำ ZIP ออก `986c48cdcfe76116742a3a8cccffac2c0e75eef6`. หมายเลขรุ่นมาจากข้อความ commit ไม่ใช่ตัวแปร version ในโปรแกรม. Repository: [TonChaiya/torskp.page.gd](https://github.com/TonChaiya/torskp.page.gd).

## เริ่มต้นอ่านสำหรับนักพัฒนาและ AI

| เอกสาร | ใช้เมื่อ |
|---|---|
| [AGENTS.md](AGENTS.md) / [CLAUDE.md](CLAUDE.md) | กฎก่อนเริ่มแก้โค้ดและขอบเขตที่ต้องรักษา |
| [PROJECT_STATUS](docs/PROJECT_STATUS.md) | สถานะจริงของรุ่น จุดที่ผ่าน และข้อจำกัด |
| [ARCHITECTURE](docs/ARCHITECTURE.md) | โครงสร้างไฟล์, dependencies, จุดเริ่มอ่านแต่ละงาน |
| [WORKFLOW](docs/WORKFLOW.md) | การส่งต่อข้อมูล 6 ขั้นตอน และ TOR |
| [DATABASE](docs/DATABASE.md) | ตาราง, key, migration และ schema drift |
| [WORD_EXPORT](docs/WORD_EXPORT.md) | Word/PDF, template, merge V4 และการจัดรูปแบบไทย |
| [UI_GUIDELINES](docs/UI_GUIDELINES.md) | หน้าจอและหลักการ UI |
| [DEVELOPMENT](docs/DEVELOPMENT.md) | สภาพแวดล้อม ทดสอบ และ release |
| [SECURITY](docs/SECURITY.md) | บัญชี สิทธิ์ ความปลอดภัย และข้อมูลจริง |
| [CHANGELOG](CHANGELOG.md) | ประวัติ Git และการเปลี่ยนแปลง |
| [REPOSITORY_INVENTORY](docs/REPOSITORY_INVENTORY.md) | รายการไฟล์ที่ตรวจเทียบกับ Git baseline |

## สภาพแวดล้อม

- Laragon บน Windows: `C:\laragon\www\torskp.page.gd`; URL ทดสอบที่ใช้ `http://torskp.page.gd.test:8081/` (ขึ้นกับการตั้งค่าของเครื่อง)
- PHP ^8.1, PDO MySQL/MariaDB, UTF-8/utf8mb4_unicode_ci, Composer, PHPWord ^1.4 และ mPDF ^8.3 ตาม composer.json; เปิด json, mbstring, PDO/pdo_mysql, zip, DOM/XML และ extensions ที่ Composer ต้องการ
- หน้าจอ PHP + Tailwind CDN บางหน้า + CSS/JS ภายในโครงการ; ไม่มีขั้น build frontend แยกที่กำหนดใน repository
- `core/config.php` อ่าน `TOR_DB_HOST`, `TOR_DB_NAME`, `TOR_DB_USER`, `TOR_DB_PASS` จาก environment; fallback สำหรับ Laragon เท่านั้น. ห้ามบันทึกรหัสฐานจริงใน Git หรือไฟล์เปิดผ่านเว็บ
- รัน `composer install --no-dev --optimize-autoloader` จาก root; ให้ PHP เขียน temp ได้. Microsoft Word Desktop ใช้ตรวจผล Word จริง; browser ใช้ Print/Save as PDF

## ตั้งฐานใหม่ / อัปเกรดฐานเดิม

**อ่าน [DATABASE.md](docs/DATABASE.md) ก่อนเสมอ:** schema.sql ใหม่เป็น structure-only อ้างอิงฐาน Dev 24 พ.ย. 2569 (MySQL 8.4.3) ครบ 12 ตาราง; ใช้เฉพาะฐานใหม่ว่าง ไม่ import ทับฐานเดิม. ฐาน Dev ที่ตรวจไม่ต้อง migrate; ฐานอื่นให้ใช้ `database/checks/` และ migration เฉพาะที่ขาด. ไม่มีบัญชี/ข้อมูลยา seed ใน schema ใหม่ ต้องตั้งข้อมูลและ bootstrap admin ตามคู่มือ. เก็บ full backup นอก Git/web root ตาม [DATABASE_BACKUP.md](docs/DATABASE_BACKUP.md). Production ต้องใช้ HTTPS และป้องกันไฟล์ SQL/config/storage.

## เส้นทางใช้งาน

เข้าสู่ระบบ → เลือก/สร้างชุดจัดซื้อพร้อมบริษัท → 1 อนุมัติหลักการ → 2 เชิญกรรมการ → 3 TOR → 4 อนุมัติคุณลักษณะและราคากลาง → 5 รายงานผล → 6 รายงานประชุม → ประวัติ → รวม Word ของชุดนั้น. หน้าจัดการบัญชีอยู่ `change_password.php`; หลังบ้านยา/ราคา/ผู้ลงนามอยู่ `admin.php`.

เอกสารชุดนี้เป็นคู่มือ handoff และ baseline โครงสร้าง DB; ยังไม่รับรอง fresh install จนกว่าจะ import และทดสอบกับ DB engine จริง. ไม่ได้เปลี่ยนฐานที่ใช้งานอยู่. เมื่อมี commit ใหม่ให้อัปเดต baseline และเฉพาะคู่มือที่ได้รับผลกระทบ.
