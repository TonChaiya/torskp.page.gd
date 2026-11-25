# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### ความเสี่ยงที่ยังเปิดหลัง Production
ตรวจ GitHub `f30c93cf`: repo ยัง Public, dump จริงเดิมและ staging ไม่อยู่ใน HEAD แล้ว, `.gitignore` มี runtime rules, `storage/.htaccess`, `storage/tmp/.htaccess`, `.gitkeep` ยังอยู่. การลบไม่ได้ล้าง history; ประเมินข้อมูลที่อาจเผยแพร่และจำกัด access/rotate credentials ที่กระทบตามผู้รับผิดชอบโดยเร็ว. ไม่ทำ history rewrite หรือ force push โดยไม่มีอนุมัติ. ไม่คัดลอกข้อมูลจริงลง docs/issue/chat.

ผู้ใช้ยืนยันเว็บใช้งานได้ ไม่ใช่หลักฐานว่า HTTPS, HTTP deny, sessions, role/CSRF, hosting config, backup/restore ผ่าน audit. ตรวจแบบไม่แตะข้อมูลจริง; เก็บ credentials นอก Git, ไม่เปิด debug หรือ upload SQL/tests/docs ลง web root. ไม่เปลี่ยน auth policy หรืออ้าง tenant isolation โดยไม่ตรวจ. ดู [DATABASE_BACKUP](DATABASE_BACKUP.md).

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Security และการดูแลข้อมูล

## Authentication/authorization

`core/auth.php` ใช้ secure session, security headers, currentUser อ่าน account_status จาก DB ต่อ request, reject session เมื่อไม่ approved. `requireLogin()` ตรวจ login และบังคับเปลี่ยนรหัสผ่านเมื่อ must_change_password=1 (ยกเว้น change_password/logout), ตรวจ CSRF ทุก POST. `requireAdmin()` ตรวจ role admin เพิ่ม. `register.php` สร้าง pending; admin review ผ่าน `change_password.php`; existing users default approved ตาม migration. รหัสผ่านอย่างน้อย 6 ตัว มีตัวอักษรและตัวเลขตาม policy เดิม; ใช้ password hash ไม่เก็บ plaintext. ห้ามลดเงื่อนไขเพื่อให้ test ผ่าน. ตรวจ throttle และ audit log ด้วย. `admin.php` requireAdmin. Word merge endpoint requireLogin+CSRF และตรวจ positive case_id/complete. ไม่ได้ยืนยันว่ามี per-user ownership ของ case; อย่าเพิ่ม tenant model หรืออ้างว่าแยกสิทธิ์ราย case อยู่แล้วโดยไม่วิเคราะห์.

## ข้อมูลและการ deploy

`core/config.php` ใช้ env สำหรับ production; local fallback เท่านั้น. `.htaccess` ห้าม directory listing, ปิด access SQL/log/ini/composer/env และ core/database/storage มีไฟล์ป้องกันเพิ่มเติม. ต้องตรวจ Apache AllowOverride และการป้องกันจริงบนโฮสต์ (Nginx ไม่อ่าน .htaccess). HTTPS ใน production. ไม่ส่งออก credentials/session/token/dump ใน logs หรือ docs.

**ข้อค้นพบสำคัญ:** Git ยัง track SQL dump 2 ไฟล์ใน database/; dump มีข้อมูลตาราง users และ procurement histories จาก DDL/เนื้อหาที่ตรวจ. มีความเสี่ยงข้อมูลส่วนบุคคล/รหัสผ่าน hash หลุดผ่าน repo หรือ archive เก่า. ZIP ทั้งโครงการถูกลบใน commit ล่าสุด แต่การลบไม่ได้ลบ blob จากประวัติ Git. อย่าคัดลอกหรือเผยข้อมูลเหล่านี้ซ้ำ. เจ้าของควรตรวจ visibility/access, secrets และความจำเป็นของ dump; วางแผนแยก backup ไปที่ private storage และ review history cleanup/credential rotation ตามผลตรวจ โดยต้องได้รับอนุมัติก่อนทำ destructive rewrite. รอบ DB baseline เพิ่มกฎ .gitignore และคู่มือ [DATABASE_BACKUP.md](DATABASE_BACKUP.md) สำหรับสำรองและ untrack 2 ไฟล์; ยังไม่ได้ untrack/push/ล้าง history จริง. schema ใหม่ไม่มีข้อมูลบัญชีหรือ seed; ไม่ใช้ backup เป็น source สำหรับ CI.

## การทดสอบและ upload

ใช้ฐาน sandbox และข้อมูลสมมติสำหรับ AI/CI; ห้าม resetProcurementWorkflowData/deleteProcurementCase บนฐานจริง. Admin import ต้องรักษาการตรวจไฟล์ XLSX/CSV และ temp cleanup; หลีกเลี่ยง arbitrary upload/path/SQL injection; ใช้ prepared statements และ output escaping. Merge รักษา same-origin fetch, session, CSRF, ไม่เพิ่มการ upload DOCX ไป server, reject DTD/unsupported relationships และตรวจ package structure. ไม่แก้ security gate เพื่อหลีกเลี่ยง error จาก schema เก่า.

## v1.3.0 candidate — ข้อมูลย้อนหลัง
`catalog_snapshot_review.php` ต้อง requireAdmin, POST CSRF และ backup acknowledgement; แสดงข้อมูล archive เฉพาะ admin, no-store. Server ตรวจ row key scope/fingerprint ไม่รับ spec จาก client; ล้าง catalog ตรวจ hash ของ catalog + เอกสารซ้ำภายใต้ transaction. ไม่กู้ข้อมูลด้วยการเดา, ไม่ commit SQL/fixtures จริงหรือ staging. Runtime files ที่พบใน v1.2.0 HEAD ผู้ใช้เลื่อน cleanup ไว้ ไม่ใช่การอนุญาตเผยแพร่เพิ่ม. ไม่ rewrite Git history เอง. อ่าน [DOCUMENT_SNAPSHOT.md](DOCUMENT_SNAPSHOT.md).
