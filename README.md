# TOR SKP — v1.4.0 Admin Workspace

**26 พฤศจิกายน 2569 — Git runtime baseline v1.4.0**

Repository: `TonChaiya/torskp.page.gd` · branch `main` · runtime commit [`c4699b22`](https://github.com/TonChaiya/torskp.page.gd/commit/c4699b22bbb58e563d4983f4fec9e0c119d97164).

- ผู้ใช้ Push แล้วเวลา 09:43:38 ICT (commit timestamp); ข้อความ commit `v1.4.0` / `feat(admin): redesign workspace and add committee management`. ตรวจ Git blob ตรงกับ ZIP Hotfix C ทั้ง 7 ไฟล์. รุ่นนี้เป็น source baseline ไม่ใช่ Git tag หรือการยืนยัน deploy.
- ผู้ใช้ตอบรับการแก้ UI ล่าสุดและอนุญาต Push; ยังไม่มีการยืนยันการอัปโหลด v1.4.0 ขึ้น InfinityFree หรือ UAT ครบทุกฟังก์ชันบน MySQL/Word จริง. v1.3.0 เป็น Production baseline ล่าสุดที่มีบันทึกยืนยันชัดเจน (25 พ.ย. 2569); ไม่อนุมานว่า Hosting ยังอยู่รุ่นใดในปัจจุบัน.
- Git baseline มีโค้ด hotfix v1.3.1 ของหลักการและรายงานประชุมอยู่แล้ว; ตรวจพบ `core/meeting_tor_refresh.php` และ renderer ใน `meeting_report.php` ที่รองรับ `price_basis`. ไม่ย้อนกลับไปใช้สูตรราคาเสนออย่างเดียว หรือเริ่มงานที่ปิดแล้วซ้ำโดยไม่มีปัญหาใหม่.
- เอกสารรอบนี้เป็น docs-only handoff ยังไม่ Push จากผู้ช่วย; HEAD จะเปลี่ยนเมื่อผู้ใช้ commit เอกสาร แต่ runtime baseline ข้างต้นยังเดิม. ไม่เปลี่ยน PHP/JS/CSS, SQL, template, DB หรือ Hosting.

## ความสามารถและขอบเขตล่าสุด

- Admin ใหม่: 2 กลุ่ม 4 เมนู (รายการยาและคุณลักษณะ, รายการราคากลาง, ชุดกรรมการ, ผู้เสนอและผู้อนุมัติเอกสาร) เป็น native dialog กลางจอ; ค้นหาและ toolbar อยู่ในหมวด, ปุ่มขนาดพอดี, เลื่อนด้วยเมาส์ใน Popup. First paint ใช้ max-width 1160px ตั้งแต่ CSS ใน head; จอ <=640px เว้นขอบรวม 20px. ถ้า JS ไม่ทำงานหรือช้าเกิน 8 วินาที มี fallback ให้เข้าถึงฟอร์มเดิม; หากปิด JS ไม่ซ่อนฟอร์มเดิม.
- ย้าย DOM node จริงจาก `admin.php` ไม่ clone; คง ID, input names, form POST action, CSRF, listeners, live search/pagination, CRUD ยา/ราคา, Excel/CSV preview-confirm-cancel, sample templates, export ยาทั้งหมด, Snapshot Review และ safe clear guards. ไม่ปลด guard หรือเปลี่ยน API เดิม.
- ชุดกรรมการ: ใช้ `committee_sets` เดิม (ประธาน 1 + กรรมการ 2), เพิ่ม/แก้ไข/ลบต้นแบบจาก `admin_committee.php`; requireAdmin และ POST CSRF, prepared statements, transaction, duplicate title protection, row fingerprint, ยืนยันชื่อก่อนลบ, inbound FK guard และ audit. หัวข้อ 1 ยังใช้ต้นแบบเดิม; ชุดเอกสารเก็บรายชื่อ snapshot ไม่เขียนทับจาก Admin. ไม่เปลี่ยนการเลือกกรรมการจากหน้าหลักการเดิม.
- ค่าเริ่มต้นผู้เสนอ/ผู้อนุมัติใช้ action และ helper เดิมสำหรับหัวข้อ 1; ชุดที่บันทึกชื่อแล้วคงชื่อเดิม ส่วนช่องเดิมที่ว่างอาจ fallback เป็นค่าเริ่มต้นตาม semantics เดิม. ไม่เพิ่ม master หมวดหมู่ยา หรือกำหนดผู้ลงนามรายชุด/รายขั้นในรุ่นนี้; เป็นงานอนาคตที่ต้องแยกแผนและขออนุมัติ.
- Runtime เปลี่ยน 4 ไฟล์: `admin_committee.php`, `assets/admin-compact.css`, `assets/admin-modal.js`, `assets/admin-workspace.css`. `admin.php` blob เดิม `4f98527ac0fabd570363c2d0b74142f305e22e70`. ไม่เปลี่ยนโค้ดเอกสาร 6 ขั้น, Word/PDF/Word รวม V4, history, auth/core หรือ SQL ใน commit v1.4.0. ไม่มี migration สำหรับ schema baseline v1.1.0 (12 ตาราง); ห้าม import schema/dump ทับฐานเดิม.

ผลทดสอบตามบันทึก Hotfix C: PHP lint และ Node syntax ผ่าน; Chromium offline fixture regression 25/25 และ first-paint 7/7 scenarios ผ่าน รวม 1366×768, 1920×1080, 2560×1080, 390×844 และ fallback. DOM/API mock ไม่ใช่ MySQL/HTTP integration, Word/PDF จริง หรือ Production UAT. รอบ docs-only นี้ตรวจ hash และเนื้อหาเอกสาร ไม่รันชุดทดสอบระบบซ้ำโดยไม่จำเป็น.

รายละเอียด: [Admin Workspace](docs/ADMIN_WORKSPACE_20261126.md) · [Hotfix B](docs/ADMIN_WORKSPACE_HOTFIX_20261126.md) · [Hotfix C / First Paint](docs/ADMIN_WORKSPACE_FIRST_PAINT_20261126c.md).

## เริ่มต้นอ่าน

อ่าน [PROJECT_STATUS](docs/PROJECT_STATUS.md) สำหรับสถานะ canonical และงานคงเหลือ, [AGENTS](AGENTS.md) / [CLAUDE](CLAUDE.md) สำหรับกฎ AI, [ARCHITECTURE](docs/ARCHITECTURE.md) และ [UI_GUIDELINES](docs/UI_GUIDELINES.md) สำหรับโครงหน้า Admin ใหม่, [DEVELOPMENT](docs/DEVELOPMENT.md) สำหรับ release gate. คู่มือฐานข้อมูล, Workflow, Import, Snapshot, Security และ Word Export เดิมยังคงใช้สำหรับ technical contracts; ข้อมูล version ในบันทึกเก่าไม่ใช่ Git HEAD ล่าสุด.

## ก่อนขึ้น Production

1. สำรองไฟล์และ DB แบบ private; ตรวจ runtime 4 path บน Laragon/ฐาน clone ตาม schema เดิม; Ctrl+F5 เนื่องจาก version query ใน admin.php ยังไม่เปลี่ยน. ไม่ล้างฐานจริงเพื่อทดสอบ.
2. ตรวจ 4 Popup, header first paint, mousewheel, live search/pagination, add/edit/delete ข้อมูลทดสอบ, Excel/CSV preview/cancel/confirm, sample/export, Snapshot Review และ clear preview/guard; ไม่ยืนยันล้างคลังจริง. ทดสอบปิด Popup ระหว่าง busy และ fallback เมื่อ JS/CSS ไม่พร้อม.
3. ตรวจชุดกรรมการ duplicate/stale/CSRF/non-admin, เลือกต้นแบบในหัวข้อ 1 และตรวจรายชื่อเอกสารเก่าไม่เปลี่ยน; ตรวจผู้ลงนามของชุดใหม่และชุดเดิมตาม semantics เดิม. เปิดเอกสารเก่าทั้ง 6 ขั้น, Word/PDF/Word รวม V4 และปุ่มดึง TOR ล่าสุดในรายงานประชุม (ทั้ง reference/offer, package, preview ไม่ save เอง).
4. เมื่อผู้ใช้ยืนยัน Dev แล้วจึงอัปโหลด runtime 4 ไฟล์ผ่าน FileZilla และ smoke-test InfinityFree; บันทึกผลจริงก่อนประกาศ Production release. ไม่อัปโหลด docs/tests/tools/ZIP/SQL dump และไม่เขียนทับ config ฐานจริง.
5. Rollback code: คืน `assets/admin-modal.js` และ `assets/admin-compact.css` จากก่อน v1.4.0; หลังคืน assets จึงนำไฟล์ใหม่อีกสองไฟล์ออกได้หากต้องการ. การ rollback code ไม่ย้อนการแก้ต้นแบบกรรมการที่บันทึกแล้ว; หากจำเป็นกู้เฉพาะข้อมูลต้นแบบจาก backup โดยไม่ restore ทับเอกสารทั้งหมด.

---

> **ประวัติเดิม (เก็บครบตาม Git blob ก่อนปรับ v1.4.0):** ข้อมูลเวอร์ชัน, HEAD, สถานะ candidate, คำว่า "ปัจจุบัน" และคำสั่ง release ในเนื้อหาด้านล่างอ้างอิงช่วงเวลาที่เขียน ไม่ใช่สถานะล่าสุด ให้ยึดส่วน v1.4.0 ด้านบนและ PROJECT_STATUS เป็นหลักสำหรับสถานะ; ข้อกำหนดทางเทคนิคเดิมยังมีผลเว้นแต่มีการแก้ไขชัดเจนในส่วนใหม่.

# v1.3.1 — Meeting price refresh correction

**25 พฤศจิกายน 2569 — v1.3.1 follow-up: Meeting TOR price basis (candidate)**

ผู้ใช้พบว่ากดดึง TOR ล่าสุดแล้วราคายังไม่เปลี่ยน. ตรวจพบ renderer เดิมอ่าน `offer_price`/`offer_total` เท่านั้น แม้ TOR ใช้ `price_basis=reference` เป็นค่าเริ่มต้น. ข้อความด้านล่างที่ระบุคงสูตรราคาเสนอจึงเป็นประวัติของ hotfix ก่อนหน้า ไม่ใช่สัญญาปัจจุบัน.

- เมื่อ refresh หรือสร้างรายงานจาก TOR ที่เชื่อมกับหัวข้อ 3 ใช้ `price_basis` จาก extra ของ TOR ที่บันทึก: `reference` (ค่าเริ่มต้น) แสดง `reference_price` และคำนวณจำนวนที่ใช้คิดราคา × ราคากลาง; `offer` ใช้ราคาเสนอและยอดตามสูตรเดิม. กรณีบรรจุกล่องใช้ package_count ไม่ใช่ requested_quantity. ตารางแนบท้าย 2, ยอดรวม, จำนวนเงินตัวอักษรและ HTML สำหรับ Word ใช้ฐานราคาเดียวกัน. ประวัติอื่นที่ผู้ใช้เลือกเองและไม่มี metadata ของโหมดราคาคง fallback ราคาเสนอเดิม.
- เมื่อผู้ใช้กดบันทึกรายงาน เก็บ `price_basis` และทั้งสองราคาใน extra_json เดิม. รายงานเดิมที่ไม่มี `price_basis` และมี snapshot ที่สมบูรณ์ยังใช้ราคาเสนอเหมือนเดิม จนกว่าจะกดดึงแล้วบันทึกเอง. ไม่แก้ประวัติ TOR/ข้อความที่พิมพ์เอง/รายชื่อกรรมการ/วันเวลา ไม่บันทึกตอน refresh. Fingerprint ครอบคลุมฐานราคา จึงป้องกันกรณี TOR เปลี่ยนเฉพาะโหมดก่อน Save/Word. แสดงฐานราคาและยอดรวมหลัง refresh ให้ตรวจสอบก่อนบันทึก.
- ไม่มี SQL migration; ไม่มีการเปลี่ยน TOR, Import, Word Merge V4 หรือหน้าที่ไม่เกี่ยวข้อง. ใช้ ZIP สะสมแทนชุด v1.3.1 ก่อนหน้า; Git HEAD ก่อนแก้ยัง `7aec524d106b1c4a61653fa4c9d518a6d07d7c07`. Runtime 3 ไฟล์เหมือนชุดก่อน (หน้าหลักการคงเดิมจาก hotfix ก่อน, แก้เพิ่มเฉพาะ meeting_report.php และ core/meeting_tor_refresh.php).
- QA: PHP lint; helper 30 checks; integration PHP/ฐานจำลองเดิม 15 checks + 11 checks เฉพาะราคา (เปลี่ยนเฉพาะราคากลาง, ID เดิม/ใหม่, package, ทั้งสองโหมด, stale basis/reference, Save, reload, Word HTML, legacy snapshot). Chromium offline DOM ทดสอบกดปุ่มจริงและ FormData 2 checks ผ่าน; นำ payload ไปประมวล PHP mock และ render ผล ไม่ใช่ HTTP end-to-end เพราะ sandbox บล็อก navigation. ยังไม่ทดสอบ MySQL, InfinityFree หรือ Word Desktop จริง. UAT: บันทึก TOR ราคากลางใหม่โดยไม่แก้ราคาเสนอ แล้วกดดึงรายงานเดิม ตรวจตารางที่ 2 และยอดรวม กดบันทึกเอง เปิดใหม่ และตรวจ Word.

---

# v1.3.1 — Maintenance hotfix

**25 พฤศจิกายน 2569 — v1.3.1 Hotfix (candidate):** v1.3.0 ยังเป็น Production baseline ที่ผู้ใช้ยืนยัน; Git HEAD ก่อนแก้ `7aec524d106b1c4a61653fa4c9d518a6d07d7c07` เป็น docs closure ที่ Push แล้ว. ผู้ใช้รายงานสองปัญหาหลังขึ้น Production และอนุมัติแก้เฉพาะจุด. Hotfix ยังไม่ได้ Push หรือ deploy และรอผู้ใช้ทดสอบ Dev/Production. ไม่มี migration. ข้อมูลประวัติเดิมด้านล่างคงอยู่ครบ.

Hotfix สองจุด: ปรับช่องค้นหายาหน้าอนุมัติหลักการ และเพิ่มปุ่มดึงรายการ/ราคาจาก TOR ล่าสุดในรายงานประชุมแบบผู้ใช้กดเองแล้วบันทึกเอง. ดู `docs/PROJECT_STATUS.md` และ `docs/DOCUMENT_SNAPSHOT.md`. ไม่ต้อง migration; วางทับ PHP ที่แก้และ helper ใหม่พร้อมกัน.

---

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
