# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](docs/PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### รุ่นใช้งานจริงและคู่มือปัจจุบัน
- รุ่น v1.3.0 — Document Snapshot & Catalog Independence; feature commit [`cb7183f4`](https://github.com/TonChaiya/torskp.page.gd/commit/cb7183f4dfe08a0ff93f909ad017a7eee2c0f0bf); source baseline [`f30c93cf`](https://github.com/TonChaiya/torskp.page.gd/commit/f30c93cfa6be5030aa68ea76c26d941b07dfb52e). ไม่ใช่ Git tag ที่สร้างในรอบนี้.
- Production: InfinityFree ตามผู้ใช้; ใช้งานระบบหลักได้แล้ว. ไม่ต้องติดตั้งใหม่หรือรัน migration เพื่อปิดเอกสาร. ใช้ schema v1.1.0 (12 ตาราง) ต่อไป; `schema.sql` สำหรับฐานใหม่ว่างเท่านั้น.
- อ่าน [สถานะล่าสุด](docs/PROJECT_STATUS.md), [AGENTS](AGENTS.md), [Import](docs/IMPORT_SYSTEM.md), [Snapshot](docs/DOCUMENT_SNAPSHOT.md), [Backup](docs/DATABASE_BACKUP.md), [Security](docs/SECURITY.md) ก่อนงาน maintenance. Word Merge V4 คงเดิม; snapshot ไม่ใช่ immutable versioning.
- Deploy ครั้งต่อไป: สำรองไฟล์+DB ก่อน; ใช้ Git SHA ที่ตรวจแล้ว, vendor จาก Composer lock ที่ตรงกับ PHP/extensions ของ Hosting, รักษา config ฐานจริงแยกจาก Git; อัปโหลดไฟล์ runtime ที่เปลี่ยนเท่านั้น, ไม่อัปโหลด docs/tests/tools/SQL/dump. ทดสอบ smoke ตาม scope หลังอัปโหลด. ห้ามสั่งลบปลายทางอัตโนมัติ.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

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

## Import v1.2.0 (candidate สำหรับ Dev)

ระบบ Import รายการยาและราคากลางใช้ Preview ก่อนบันทึกเสมอ อ่าน [IMPORT_SYSTEM](docs/IMPORT_SYSTEM.md) สำหรับรูปแบบไฟล์, สถานะ, ข้อกำหนดและ UAT. รอบนี้ไม่เพิ่ม migration; ใช้ schema baseline v1.1.0 ตามเดิม. สถานะ v1.2.0 เป็นชุดส่งมอบเพื่อทดสอบ Dev ยังไม่ใช่ Git tag หรือ Production release. PHP ต้องมี `pdo_mysql`, `mbstring`, `zip`, `xmlreader`, `SimpleXML` สำหรับ XLSX; CSV UTF-8 เป็นทางเลือก. ต้องยืนยันผลบนฐาน MySQL จริงก่อน deploy.

### Template และ Popup (v1.2.0 candidate)
ดาวน์โหลด Excel Template ผ่าน Admin; ไฟล์อยู่ `templates/import/` มี 3 ชีต โดยกรอกเฉพาะชีตแรก `Import` แถว 2 เป็นต้นไป. ตัวอย่างราคาเป็นข้อมูลสมมุติ ไม่ใช่ราคากลางจริง. เพิ่ม/แก้ไข/นำเข้าใช้ Popup; Preview/confirm เดิมยังบังคับทุกครั้ง.

ราคากลางประจำปี: ปุ่มล้างราคากลางทั้งหมดแยกจาก Import ในหน้า Admin; ต้องสำรองและยืนยันจำนวน/คำยืนยันก่อนล้าง. หากต้องการให้คลังไม่ว่างระหว่างอัปเดต ให้ใช้ Import แบบแทนที่ทั้งหมดแทน. ไม่ต้อง migration.

## การพัฒนาล่าสุด v1.3.0 (candidate)

Git baseline ก่อนรอบนี้คือ v1.2.0 `fced437f022f07924ca163141b02a14c4f176b5c`; DB baseline v1.1.0 และประวัติด้านบนยังใช้เป็นอ้างอิง. เพิ่ม [Document Snapshot](docs/DOCUMENT_SNAPSHOT.md): เอกสารหัวข้อ 1 และ TOR อ้างอิงข้อมูลที่บันทึก ไม่ดึงชื่อ/คุณลักษณะจากคลังปัจจุบันเมื่อเปิดย้อนหลัง; Word รวมส่งข้อมูล archive โดยคง merge V4. หน้า Admin → ตรวจ Snapshot เอกสาร รองรับ explicit backfill เฉพาะหลักฐานที่ยืนยันได้ แล้วตรวจ readiness ก่อนล้างคลัง. ไม่มี migration. ดู [IMPORT_SYSTEM](docs/IMPORT_SYSTEM.md), [PROJECT_STATUS](docs/PROJECT_STATUS.md). ยังรอ UAT บน MySQL/Word จริง; อย่า deploy ก่อนผ่าน gate.
