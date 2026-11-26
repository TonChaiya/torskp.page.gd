# TOR SKP — Admin Workspace (candidate) — 26 พฤศจิกายน 2569

## ขอบเขตและต้นฉบับ

อ้างอิง `TonChaiya/torskp.page.gd` สาขา `main` จาก GitHub: `admin.php` blob `4f98527ac0fabd570363c2d0b74142f305e22e70` และ `assets/admin-modal.js` blob `3ba14e3bebdd89e034caa685149d9e7d5e79b1ad` พร้อมตรวจ schema, auth, committee flow, signer defaults, import และ document snapshot ที่เกี่ยวข้อง

- ปรับหน้าจอ Admin โดยใช้โครง HTML และฟอร์มจริงของ `admin.php` เดิม ไม่เขียนทับ PHP หน้านี้
- เมนู 2 กลุ่ม: ข้อมูลยาและราคากลาง / บุคลากรและผู้ลงนามเอกสาร (4 เมนู)
- เปิดรายการยา ราคากลาง ชุดกรรมการ และผู้ลงนามผ่าน native dialog กลางจอ จำกัดความกว้าง/สูง และเลื่อนเฉพาะภายใน
- เพิ่ม API จัดการต้นแบบกรรมการเดิม: ประธาน 1 คน + กรรมการ 2 คน โดยใช้ `committee_sets` ของระบบ ไม่เพิ่มตาราง
- ค่าเริ่มต้นผู้เสนอ/ผู้อนุมัติหัวข้อ 1 ใช้ฟอร์มและ action เดิมทั้งหมด; การกำหนดผู้ลงนามแยกตามชุด/ขั้นตอนเป็นขอบเขตอนาคต ไม่เปลี่ยนพฤติกรรมปัจจุบัน
- schema ที่ตรวจไม่มีตาราง/ฟิลด์หมวดหมู่ยาโดยเฉพาะ จึงจัดกลุ่มเมนูแทนการเพิ่ม master category ใหม่โดยไม่ได้ตกลง

## ไฟล์ Runtime (วางตามตำแหน่งเดิม)

| ไฟล์ | การเปลี่ยนแปลง |
| --- | --- |
| `assets/admin-modal.js` | ทับไฟล์เดิม; 8,075 bytes แรกคือ source เดิมตรง Git blob ทุกไบต์ เพิ่มส่วนจัดหน้าจอและตัวจัดการกรรมการท้ายไฟล์ |
| `assets/admin-workspace.css` | ไฟล์ใหม่สำหรับ layout และ dialog เฉพาะ Admin |
| `admin_committee.php` | ไฟล์ใหม่ API admin-only ใช้ฐานข้อมูลเดิม |

`admin.php`, `core/`, ไฟล์เอกสาร 6 ขั้นตอน, ประวัติ, template Word/PDF, Word รวม V4, `change_password.php`, login และ SQL เดิมไม่ถูกแก้หรือแทนที่ การเปิดใช้งาน UI ใหม่ต้องมี stylesheet ใหม่ครบ; หาก stylesheet โหลดไม่ได้ ระบบเดิมยังแสดงได้

## การรักษาฟังก์ชันเดิม

- ย้าย DOM node จริง ไม่ clone form/table/input; ID, event listener, POST action, CSRF, URL ของเอกสาร และค่าฟอร์มเดิมคงอยู่
- ค้นหายา/ราคาแบบ live, pagination, เพิ่ม/แก้ไข/ลบเดิม, Excel/CSV Preview และ Confirm, sample template, export medicines, Snapshot Review และ guard ล้างคลังเดิมยังใช้โค้ดต้นฉบับ
- คง guard cancel/confirm, backup ack, count/hash และ snapshot readiness; ไม่แก้ server import/clear
- ฟอร์มบันทึกจากรายการหรือผู้ลงนามกลับไปเปิดหมวดเดิม; รีเฟรชหลัง Import สำเร็จเป็น GET ใหม่เพื่อไม่ค้างอยู่ที่ fragment เดิม
- ชุดกรรมการใหม่ใช้ requireAdmin เดิมซึ่งตรวจ login/account approval/forced password/POST CSRF; ใช้ prepared statement, transaction, row fingerprint ตรวจ stale data, audit event และยืนยันชื่อชุดก่อนลบ
- ไม่ใช้ upsert เมื่อกดเพิ่มชุดจาก Admin เพื่อป้องกันชื่อซ้ำเขียนทับต้นแบบอื่นโดยไม่ตั้งใจ
- DELETE เฉพาะ `committee_sets` ตาม id; ตรวจ incoming FK ก่อนลบและปฏิเสธหากพบ; ไม่เขียนตารางเอกสาร/ประวัติหรือข้อมูลกรรมการ snapshot ในชุดเอกสารเดิม

## ผลการตรวจในสภาพแวดล้อมผู้ช่วย

- PHP syntax `admin_committee.php`: PASS (PHP 8.4 CLI)
- JS syntax `assets/admin-modal.js`: PASS (Node 22)
- original JS prefix: PASS (Git blob SHA ตรง 3ba14e3bebdd89e034caa685149d9e5e79b1ad)
- Chromium offline DOM fixture: PASS 18 checks — 4 เมนู, DOM node identity, event listener, snapshot link, export link, edit medicines, clear guard, edit price mapping, signer values, committee create payload + CSRF, update fingerprint, delete guard, delete payload, dialog center 1366x768 / 2560x1080 / 390x844, deep-link, no JS errors
- การทดสอบ browser ใช้ DOM จำลองตาม source และ mock JSON API ไม่ได้เชื่อม PHP/MySQL หรือทดสอบ Word/PDF จริง; ไม่ใช่ Production UAT

## วิธีวางไฟล์และทดสอบบน Laragon

1. สำรองไฟล์ `assets/admin-modal.js` เดิม และสำรองฐานข้อมูลจริงแบบ private ก่อนทดสอบการแก้ข้อมูล
2. แตก ZIP และวางทับที่ `C:\laragon\www\torskp.page.gd\` ตามโครง path ภายใน ZIP (ไม่ต้องรัน installer และไม่มี migration สำหรับ schema baseline 12 ตาราง)
3. เปิด `admin.php` แล้วกด Ctrl+F5 เพื่อให้โหลด JavaScript เวอร์ชันใหม่จาก server; หากไม่ขึ้น ตรวจว่าไฟล์ทั้ง 3 อยู่ครบตาม path และ browser console ไม่พบ 404
4. บนฐานทดสอบ/clone: เปิดทั้ง 4 Popup; ลองค้นหายาและราคา, pagination, เพิ่ม/แก้ไข/ลบรายการทดสอบ; ทดสอบ template, export, Import preview/cancel และ confirm บนข้อมูลทดสอบ; ตรวจ Snapshot Review และ preview ล้างข้อมูลโดยไม่ยืนยันล้างฐานจริง
5. เพิ่ม/แก้/ลบชุดกรรมการทดสอบจาก Admin; โหลดหัวข้อ 1 ใหม่และตรวจว่าตัวเลือกชุดตรงกัน; ทดสอบ duplicate title, stale edit/delete, CSRF, role non-admin; ตรวจเอกสารเก่าที่เคยเลือกชุดกรรมการว่ารายชื่อเดิมไม่เปลี่ยน
6. เปลี่ยนค่าเริ่มต้นผู้ลงนามบนฐานทดสอบ; ตรวจชุดเอกสารใหม่และชุดเดิมตาม semantics เดิม (ช่องเดิมที่ว่างอาจ fallback เป็นค่าเริ่มต้น ตามระบบเดิม)
7. เปิดชุดเอกสารเดิมครบทั้ง 6 ขั้นและตรวจ Word/PDF/Word รวม V4; เทียบกับผลก่อนอัปเดต; ตรวจหน้ารายงานประชุมและปุ่มดึงราคาล่าสุดจาก TOR
8. เมื่อผ่าน Laragon ค่อยอัปโหลด 3 runtime files ผ่าน FileZilla, hard refresh และ smoke-test Production; ห้ามนำ schema.sql หรือ backup dump ทับฐานเดิม

## ย้อนกลับ

คืน `assets/admin-modal.js` เวอร์ชันที่สำรองไว้; UI เดิมจะกลับมา แล้วจึงนำไฟล์ใหม่สองไฟล์ออกได้หากต้องการ การคืนไฟล์ไม่ย้อนข้อมูลต้นแบบกรรมการที่ผู้ดูแลบันทึกไปแล้ว หากจำเป็นให้กู้เฉพาะต้นแบบจาก backup อย่างระมัดระวัง ไม่ restore ทับฐานเอกสารทั้งระบบ
