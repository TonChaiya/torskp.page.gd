# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](docs/PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### สถานะของงาน DB baseline เดิม
ขั้นตอนด้านล่างเป็นประวัติการส่งมอบ v1.1.0 ไม่ต้องทำซ้ำกับระบบ Production ที่ใช้งานได้แล้ว. supplier migration อยู่ใน Git แล้ว; schema 12 ตารางยังใช้กับ v1.3.0, ไม่มี migration ใหม่ใน v1.2/v1.3. Dump สองไฟล์เดิมไม่อยู่ใน HEAD ล่าสุดแล้ว แต่ประวัติยังอยู่. ห้าม import schema/migration ทับฐานจริง; ใช้ read-only checks เฉพาะเมื่อมีงานฐานข้อมูลใหม่. ดู [DATABASE](docs/DATABASE.md) และ [BACKUP](docs/DATABASE_BACKUP.md).

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# TOR SKP v1.1.0 — Database baseline / backup hygiene

วางทับไฟล์ตาม path เดิมที่ root โครงการ (ไฟล์ SQL เป็น source สำหรับ Git ไม่ใช่คำสั่ง import ฐานเดิม). อ่าน docs/DATABASE.md และ docs/DATABASE_BACKUP.md ก่อนดำเนินการ.

1. สำรอง full dump ล่าสุดภายนอก Git และ web root. เก็บ SQL ที่ส่งออกจาก Dev เป็น private backup เท่านั้น.
2. วางไฟล์ ZIP ชุดนี้ทับโครงการ. ฐาน Dev ที่ตรวจมี 12 ตารางครบแล้ว **ไม่ต้อง Import schema หรือ migration**.
3. ถ้าต้องการตรวจฐานจริง ใช้ database/checks/v1.1.0_schema_check.sql ก่อน ตามด้วย integrity_check เมื่อครบคอลัมน์. ทั้งสองเป็น SELECT เท่านั้น.
4. สำรอง dump 2 ไฟล์เดิมและ untrack ด้วย git rm --cached ตาม DATABASE_BACKUP.md. ตรวจ staged list ก่อน push. ไม่ใช้ git add .
5. สำหรับฐานใหม่ว่างเท่านั้น import database/schema.sql; ทดสอบ restore/install และ bootstrap admin ตามคู่มือ. ไม่มีข้อมูลจริง/บัญชีสำเร็จรูป.
6. commit message: `chore(db): sync v1.1.0 schema and protect private backups` (งาน DB/docs ไม่จำเป็นต้อง bump version แอป). ยังไม่มีการ push หรือแก้ remote.

ผลตรวจ: offline DDL/row parser, 12 tables/100 columns, 10 auto-increment columns, indexes/FK ตรง Dev, JSON/orphan/duplicate/TOR history links ตรวจผ่าน. ตรวจ .gitignore ด้วย Git จริงใน repo ชั่วคราว. ไม่มี MySQL/MariaDB server ในสภาพแวดล้อมผู้ช่วย จึงยังไม่ได้ import/run checks บน engine จริง. ไม่ได้ตรวจ Production. ไม่แก้ PHP/JS/CSS, Word, หรือฐานที่ใช้งานอยู่. Constraints ที่ขาดจาก schema เก่าไม่ได้ถูกนำกลับโดยอัตโนมัติ; วางแผน hardening แยก.
