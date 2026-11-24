# Security และการดูแลข้อมูล

## Authentication/authorization

`core/auth.php` ใช้ secure session, security headers, currentUser อ่าน account_status จาก DB ต่อ request, reject session เมื่อไม่ approved. `requireLogin()` ตรวจ login และบังคับเปลี่ยนรหัสผ่านเมื่อ must_change_password=1 (ยกเว้น change_password/logout), ตรวจ CSRF ทุก POST. `requireAdmin()` ตรวจ role admin เพิ่ม. `register.php` สร้าง pending; admin review ผ่าน `change_password.php`; existing users default approved ตาม migration. รหัสผ่านอย่างน้อย 6 ตัว มีตัวอักษรและตัวเลขตาม policy เดิม; ใช้ password hash ไม่เก็บ plaintext. ห้ามลดเงื่อนไขเพื่อให้ test ผ่าน. ตรวจ throttle และ audit log ด้วย. `admin.php` requireAdmin. Word merge endpoint requireLogin+CSRF และตรวจ positive case_id/complete. ไม่ได้ยืนยันว่ามี per-user ownership ของ case; อย่าเพิ่ม tenant model หรืออ้างว่าแยกสิทธิ์ราย case อยู่แล้วโดยไม่วิเคราะห์.

## ข้อมูลและการ deploy

`core/config.php` ใช้ env สำหรับ production; local fallback เท่านั้น. `.htaccess` ห้าม directory listing, ปิด access SQL/log/ini/composer/env และ core/database/storage มีไฟล์ป้องกันเพิ่มเติม. ต้องตรวจ Apache AllowOverride และการป้องกันจริงบนโฮสต์ (Nginx ไม่อ่าน .htaccess). HTTPS ใน production. ไม่ส่งออก credentials/session/token/dump ใน logs หรือ docs.

**ข้อค้นพบสำคัญ:** Git ยัง track SQL dump 2 ไฟล์ใน database/; dump มีข้อมูลตาราง users และ procurement histories จาก DDL/เนื้อหาที่ตรวจ. มีความเสี่ยงข้อมูลส่วนบุคคล/รหัสผ่าน hash หลุดผ่าน repo หรือ archive เก่า. ZIP ทั้งโครงการถูกลบใน commit ล่าสุด แต่การลบไม่ได้ลบ blob จากประวัติ Git. อย่าคัดลอกหรือเผยข้อมูลเหล่านี้ซ้ำ. เจ้าของควรตรวจ visibility/access, secrets และความจำเป็นของ dump; วางแผนแยก backup ไปที่ private storage และ review history cleanup/credential rotation ตามผลตรวจ โดยต้องได้รับอนุมัติก่อนทำ destructive rewrite. รอบเอกสารนี้ไม่ได้ลบ dump หรือแก้ประวัติ Git.

## การทดสอบและ upload

ใช้ฐาน sandbox และข้อมูลสมมติสำหรับ AI/CI; ห้าม resetProcurementWorkflowData/deleteProcurementCase บนฐานจริง. Admin import ต้องรักษาการตรวจไฟล์ XLSX/CSV และ temp cleanup; หลีกเลี่ยง arbitrary upload/path/SQL injection; ใช้ prepared statements และ output escaping. Merge รักษา same-origin fetch, session, CSRF, ไม่เพิ่มการ upload DOCX ไป server, reject DTD/unsupported relationships และตรวจ package structure. ไม่แก้ security gate เพื่อหลีกเลี่ยง error จาก schema เก่า.
