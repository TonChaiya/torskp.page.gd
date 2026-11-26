# Project status — v1.4.0 canonical

**26 พฤศจิกายน 2569 — Git runtime baseline v1.4.0**

Repository: `TonChaiya/torskp.page.gd` · branch `main` · runtime commit [`c4699b22`](https://github.com/TonChaiya/torskp.page.gd/commit/c4699b22bbb58e563d4983f4fec9e0c119d97164).

- ผู้ใช้ Push แล้วเวลา 09:43:38 ICT (commit timestamp); ข้อความ commit `v1.4.0` / `feat(admin): redesign workspace and add committee management`. ตรวจ Git blob ตรงกับ ZIP Hotfix C ทั้ง 7 ไฟล์. รุ่นนี้เป็น source baseline ไม่ใช่ Git tag หรือการยืนยัน deploy.
- ผู้ใช้ตอบรับการแก้ UI ล่าสุดและอนุญาต Push; ยังไม่มีการยืนยันการอัปโหลด v1.4.0 ขึ้น InfinityFree หรือ UAT ครบทุกฟังก์ชันบน MySQL/Word จริง. v1.3.0 เป็น Production baseline ล่าสุดที่มีบันทึกยืนยันชัดเจน (25 พ.ย. 2569); ไม่อนุมานว่า Hosting ยังอยู่รุ่นใดในปัจจุบัน.
- Git baseline มีโค้ด hotfix v1.3.1 ของหลักการและรายงานประชุมอยู่แล้ว; ตรวจพบ `core/meeting_tor_refresh.php` และ renderer ใน `meeting_report.php` ที่รองรับ `price_basis`. ไม่ย้อนกลับไปใช้สูตรราคาเสนออย่างเดียว หรือเริ่มงานที่ปิดแล้วซ้ำโดยไม่มีปัญหาใหม่.
- เอกสารรอบนี้เป็น docs-only handoff ยังไม่ Push จากผู้ช่วย; HEAD จะเปลี่ยนเมื่อผู้ใช้ commit เอกสาร แต่ runtime baseline ข้างต้นยังเดิม. ไม่เปลี่ยน PHP/JS/CSS, SQL, template, DB หรือ Hosting.

## สถานะปัจจุบัน

| รายการ | สถานะล่าสุด |
|---|---|
| GitHub v1.4.0 | Push แล้ว; runtime SHA c4699b22 / ไฟล์ Hotfix C 7/7 ตรง Git |
| Admin UI + committee CRUD | Implemented; ผู้ใช้ตอบรับ UI ล่าสุดก่อน Push |
| Regression offline / first paint | 25/25 + 7/7 ตามรายงาน Hotfix C |
| Laragon/MySQL/Word UAT ครบชุด | ยังไม่ยืนยัน |
| InfinityFree v1.4.0 | ยังไม่ยืนยัน deploy |
| DB baseline | v1.1.0 / 12 ตาราง; ไม่มี migration ใน v1.4.0 |
| Word Merge | V4 เดิม ไม่ใช่หมายเลขรุ่นเว็บไซต์ |
| ผู้ลงนามรายชุด / master หมวดหมู่ยา | ยังไม่เริ่ม / ต้องแยก scope |
| Documentation closure v1.4.0 | ชุดนี้พร้อมวางทับและให้ผู้ใช้ Push; ยังไม่ Push |

## ขอบเขตและ contract

- Admin ใหม่: 2 กลุ่ม 4 เมนู (รายการยาและคุณลักษณะ, รายการราคากลาง, ชุดกรรมการ, ผู้เสนอและผู้อนุมัติเอกสาร) เป็น native dialog กลางจอ; ค้นหาและ toolbar อยู่ในหมวด, ปุ่มขนาดพอดี, เลื่อนด้วยเมาส์ใน Popup. First paint ใช้ max-width 1160px ตั้งแต่ CSS ใน head; จอ <=640px เว้นขอบรวม 20px. ถ้า JS ไม่ทำงานหรือช้าเกิน 8 วินาที มี fallback ให้เข้าถึงฟอร์มเดิม; หากปิด JS ไม่ซ่อนฟอร์มเดิม.
- ย้าย DOM node จริงจาก `admin.php` ไม่ clone; คง ID, input names, form POST action, CSRF, listeners, live search/pagination, CRUD ยา/ราคา, Excel/CSV preview-confirm-cancel, sample templates, export ยาทั้งหมด, Snapshot Review และ safe clear guards. ไม่ปลด guard หรือเปลี่ยน API เดิม.
- ชุดกรรมการ: ใช้ `committee_sets` เดิม (ประธาน 1 + กรรมการ 2), เพิ่ม/แก้ไข/ลบต้นแบบจาก `admin_committee.php`; requireAdmin และ POST CSRF, prepared statements, transaction, duplicate title protection, row fingerprint, ยืนยันชื่อก่อนลบ, inbound FK guard และ audit. หัวข้อ 1 ยังใช้ต้นแบบเดิม; ชุดเอกสารเก็บรายชื่อ snapshot ไม่เขียนทับจาก Admin. ไม่เปลี่ยนการเลือกกรรมการจากหน้าหลักการเดิม.
- ค่าเริ่มต้นผู้เสนอ/ผู้อนุมัติใช้ action และ helper เดิมสำหรับหัวข้อ 1; ชุดที่บันทึกชื่อแล้วคงชื่อเดิม ส่วนช่องเดิมที่ว่างอาจ fallback เป็นค่าเริ่มต้นตาม semantics เดิม. ไม่เพิ่ม master หมวดหมู่ยา หรือกำหนดผู้ลงนามรายชุด/รายขั้นในรุ่นนี้; เป็นงานอนาคตที่ต้องแยกแผนและขออนุมัติ.
- Runtime เปลี่ยน 4 ไฟล์: `admin_committee.php`, `assets/admin-compact.css`, `assets/admin-modal.js`, `assets/admin-workspace.css`. `admin.php` blob เดิม `4f98527ac0fabd570363c2d0b74142f305e22e70`. ไม่เปลี่ยนโค้ดเอกสาร 6 ขั้น, Word/PDF/Word รวม V4, history, auth/core หรือ SQL ใน commit v1.4.0. ไม่มี migration สำหรับ schema baseline v1.1.0 (12 ตาราง); ห้าม import schema/dump ทับฐานเดิม.

ผลทดสอบตามบันทึก Hotfix C: PHP lint และ Node syntax ผ่าน; Chromium offline fixture regression 25/25 และ first-paint 7/7 scenarios ผ่าน รวม 1366×768, 1920×1080, 2560×1080, 390×844 และ fallback. DOM/API mock ไม่ใช่ MySQL/HTTP integration, Word/PDF จริง หรือ Production UAT. รอบ docs-only นี้ตรวจ hash และเนื้อหาเอกสาร ไม่รันชุดทดสอบระบบซ้ำโดยไม่จำเป็น.

รายละเอียด: [Admin Workspace](ADMIN_WORKSPACE_20261126.md) · [Hotfix B](ADMIN_WORKSPACE_HOTFIX_20261126.md) · [Hotfix C / First Paint](ADMIN_WORKSPACE_FIRST_PAINT_20261126c.md).

## Inventory delta — commit v1.4.0

รายการนี้เป็น delta เฉพาะ 7 ไฟล์ใน commit ไม่ใช่ full repository tree audit; inventory รุ่นก่อนใน REPOSITORY_INVENTORY.md เก็บเป็นประวัติ.

| Path | Git blob SHA ณ runtime baseline |
|---|---|
| `admin_committee.php` | `383a8a8a93d5e899a98e9c6eef7f60b29fe6dc32` |
| `assets/admin-compact.css` | `97a20014eb6ba2902c51c65aa422d5bf3c997311` |
| `assets/admin-modal.js` | `92a08bc2de912f90cc15a59e2f41d54f9c2eb329` |
| `assets/admin-workspace.css` | `628899ed54c46213933936387e1f8fbca91af73a` |
| `docs/ADMIN_WORKSPACE_20261126.md` | `e79bd49f018c5828ca1fcb5b3b53e93869c6fa9c` |
| `docs/ADMIN_WORKSPACE_FIRST_PAINT_20261126c.md` | `6af9854aa8232b2698c35b8edb364ffe976a845a` |
| `docs/ADMIN_WORKSPACE_HOTFIX_20261126.md` | `db18cdb9c342d7cef92f720d6c0d016ea54028cf` |

## Release gate และงานดูแลต่อ

1. สำรองไฟล์และ DB แบบ private; ตรวจ runtime 4 path บน Laragon/ฐาน clone ตาม schema เดิม; Ctrl+F5 เนื่องจาก version query ใน admin.php ยังไม่เปลี่ยน. ไม่ล้างฐานจริงเพื่อทดสอบ.
2. ตรวจ 4 Popup, header first paint, mousewheel, live search/pagination, add/edit/delete ข้อมูลทดสอบ, Excel/CSV preview/cancel/confirm, sample/export, Snapshot Review และ clear preview/guard; ไม่ยืนยันล้างคลังจริง. ทดสอบปิด Popup ระหว่าง busy และ fallback เมื่อ JS/CSS ไม่พร้อม.
3. ตรวจชุดกรรมการ duplicate/stale/CSRF/non-admin, เลือกต้นแบบในหัวข้อ 1 และตรวจรายชื่อเอกสารเก่าไม่เปลี่ยน; ตรวจผู้ลงนามของชุดใหม่และชุดเดิมตาม semantics เดิม. เปิดเอกสารเก่าทั้ง 6 ขั้น, Word/PDF/Word รวม V4 และปุ่มดึง TOR ล่าสุดในรายงานประชุม (ทั้ง reference/offer, package, preview ไม่ save เอง).
4. เมื่อผู้ใช้ยืนยัน Dev แล้วจึงอัปโหลด runtime 4 ไฟล์ผ่าน FileZilla และ smoke-test InfinityFree; บันทึกผลจริงก่อนประกาศ Production release. ไม่อัปโหลด docs/tests/tools/ZIP/SQL dump และไม่เขียนทับ config ฐานจริง.
5. Rollback code: คืน `assets/admin-modal.js` และ `assets/admin-compact.css` จากก่อน v1.4.0; หลังคืน assets จึงนำไฟล์ใหม่อีกสองไฟล์ออกได้หากต้องการ. การ rollback code ไม่ย้อนการแก้ต้นแบบกรรมการที่บันทึกแล้ว; หากจำเป็นกู้เฉพาะข้อมูลต้นแบบจาก backup โดยไม่ restore ทับเอกสารทั้งหมด.

งานสำรอง/restore drill, ตรวจการป้องกัน core/storage/HTTPS, จำกัด access และประเมินความเสี่ยง dump/staging ใน Git history จากเอกสารเก่ายังไม่มีหลักฐานปิดงาน; ไม่ดำเนินการเองและไม่ rewrite Git history. ปรับผู้ลงนามรายชุด/รายเอกสารเมื่อผู้ใช้ขอและวิเคราะห์ snapshot/DB contract ก่อน. ไม่สร้างหมวดหมู่ยาใหม่โดยพลการ.

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

| งาน | สถานะ |
|---|---|
| Docs closure v1.3.0 | Push แล้ว `7aec524d` |
| v1.3.1 UI ช่องค้นหายา | candidate ส่งทดสอบ |
| v1.3.1 ดึง TOR ล่าสุดในรายงานประชุม | candidate ส่งทดสอบ |
| DB / Word Merge V4 | ไม่แก้ schema หรือ merge engine |
| Production v1.3.1 | ยังไม่ได้ deploy |

หน้า `principle_approval_memo.php`: ช่องชื่อยาและผลค้นหาเต็มความกว้างแถวใน editor pane ทุกขนาด; จำนวน/หน่วย/ลบอยู่บรรทัดถัดไป. จำกัดความสูงผลค้นหา 13rem, เลื่อนใน list, คงค้นหา 3 ตัวอักษร/12 ผลลัพธ์ และการเลือกด้วยเมาส์หรือคีย์บอร์ด. ทั้งแถว PHP และแถวเพิ่มด้วย JavaScript ใช้ layout เดียวกัน; คงชื่อ field และ selection key/Snapshot เดิม.

หน้า `meeting_report.php`: เมื่อมี snapshot รายการยาของรายงานประชุมที่อ่านได้ครบและตรง history_id ให้ใช้รายการที่บันทึกไว้ใน `extra_json.items` สำหรับการเปิดและส่งออกตามปกติ. รายงานเก่าที่ไม่มี snapshot สมบูรณ์ใช้ประวัติ TOR ตาม fallback เดิม. ปุ่ม **ดึงรายการยาและราคา TOR ล่าสุด** เป็น POST `refresh_tor` ผ่านฟอร์มเดิมและ requireLogin/CSRF. ดึง `history_id` ที่หัวข้อ 3 บันทึกไว้เฉพาะ case_id เดียวกัน (หากไม่มี link ใน legacy เลือก history ล่าสุดของ case เดียวกันเท่านั้น); link ผิด case/ไม่มีข้อมูล/แถวอ่านไม่ได้ไม่เปลี่ยนไปใช้ข้อมูลอื่น. การดึงเป็น preview ไม่เรียก save; รักษาข้อมูลวันเวลา สถานที่ กรรมการ ข้อความที่กรอกในฟอร์ม และปรับเฉพาะวลีจำนวนรายการยารูปแบบมาตรฐาน. รายการในตาราง/ตัวแทน `[medicine_items]` ใช้ชุดที่เลือก; ข้อความรายการ/ราคาที่พิมพ์เองไม่ถูกเขียนทับ ต้องตรวจเอง. เมื่อกดบันทึก `save_document` จึงบันทึก snapshot ในรายงานเดิม; ไม่ลบประวัติและไม่แก้ TOR. Hidden source/hash ควบคุมการตรวจว่ารายการหรือ TOR link เปลี่ยนหลัง preview ก่อน Save/Word; ไม่รับราคาจาก hidden fields. คงสูตรราคาเสนอและขนาดบรรจุเดิม รวมถึงการเก็บราคากลางใน snapshot; ไม่เปลี่ยนโหมดราคาของ TOR. Word/PDF และ Word รวม V4 ใช้ข้อมูลของฟอร์มรายงานประชุมตาม source เดียวกัน; ไม่เปลี่ยน engine/แม่แบบ/จัดหน้า.

ผลทดสอบผู้ช่วย: PHP lint ไฟล์ PHP ที่เปลี่ยน, helper unit tests และ integration mock 15 checks จากหน้า PHP จริงกับฐานจำลอง (snapshot เดิม, history ID เดิม/ใหม่, refresh read-only, cross-case, legacy/no saved report, preserve edits, count, stale Save/Word, Word HTML, explicit save/package). Chromium DOM fixture 6 checks: editor widths 280/340/420/560px, keyboard/list scrolling, dynamic mouse selection, refresh button form owner. ไม่ใช่ end-to-end MySQL, InfinityFree, Tailwind CDN หรือ Microsoft Word จริง. ต้องทดสอบบันทึก TOR แล้วดึงใหม่จากรายงานเดิมและบันทึก, reload, Word/PDF/Word รวม V4 ในชุดทดสอบก่อนขึ้น Hosting; ไม่ล้างข้อมูลจริงเพื่อทดสอบ.

---

# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### สถานะ canonical — รุ่นปัจจุบัน
| รายการ | สถานะ / หลักฐาน |
|---|---|
| v1.1.0 Workflow, Word/PDF, Word Merge V4, auth | implementation เดิมคงอยู่; ผู้ใช้เคยยืนยัน V4 ผ่าน |
| v1.2.0 Import & Catalog Management | อยู่ใน Git `fced437f022f07924ca163141b02a14c4f176b5c`; รวม hotfix และ export/clear |
| v1.3.0 Document Snapshot | อยู่ใน Git `cb7183f4dfe08a0ff93f909ad017a7eee2c0f0bf`; ผู้ใช้แจ้ง Dev ใช้ได้ 12:05 ICT |
| Git cleanup | `a892806e` เพิ่ม ignore; `f30c93cf` ลบ staging จาก HEAD; ตรวจ tree ล่าสุดมี storage protection ครบ |
| Production InfinityFree | ผู้ใช้ยืนยันอัปโหลดและใช้งานจริงได้หมดแล้ว 12:56 ICT; ปิดงานพัฒนาหลักตามการยืนยันของผู้ใช้ |
| DB schema / Word Merge | schema 12 ตารางเดิม; ไม่มี migration v1.2/v1.3; V4 merge engine ไม่เปลี่ยน |
| QA evidence | 39 Snapshot / 117 Import assertions, offline 17 histories / 59 rows, HTML 34 comparisons, Chromium DOM fixture ตามเอกสารเดิม; ไม่ใช่ผล MySQL/Word จริงจากผู้ช่วย |
| Docs closure | จัดชุด MD ฉบับปิดงานจาก HEAD `f30c93cfa6be5030aa68ea76c26d941b07dfb52e`; รอผู้ใช้นำไปวางทับและ Push |

ไม่มีหลักฐานรายกรณีจาก Production (PHP/MySQL versions, actual schema, restore drill, performance limits, security/HTTP protection, Word comparison) หรือ deployment manifest ที่ผู้ช่วยตรวจได้โดยตรง. ไม่สรุปว่าทุก edge case ผ่าน, ไม่บังคับเปิดงานที่ผู้ใช้ปิดแล้ว. Baseline นี้เป็นจุดอ้างอิง source ไม่ใช่การยืนยัน checksum ไฟล์บน Hosting. ข้อมูลสถิติ Dev dump เดิมเป็นประวัติ ไม่ใช่จำนวนแถวบน Production ปัจจุบัน.

### งานคงเหลือหลัง release (ไม่ใช่ implementation ค้าง)
| ลำดับ | งาน | สถานะ |
|---|---|---|
| P1 | สำรองไฟล์เว็บและ SQL Production หลังติดตั้ง; เก็บ private และทดลอง restore ลงฐานแยก | ยังไม่มีการยืนยัน |
| P1 | ประเมินความเสี่ยง SQL dump/Import staging ในประวัติ Git, จำกัด visibility/access, rotate credentials ที่กระทบ | repo ยัง Public ณ การตรวจ; ไม่ได้ดำเนินการ |
| P1 | ตรวจ HTTPS, deny access core/storage, permissions และ approval ตามสิทธิ์ | ยังไม่ได้ตรวจโดยผู้ช่วย |
| P2 | ปิดเอกสาร MD และ Push เฉพาะ docs | ชุดเอกสารพร้อมส่ง; ยังไม่ Push |
| P3 | Tailwind CDN, performance, backup cadence | optional; ไม่อนุมัติเป็น feature ใหม่ |

เมื่อมี bug ให้ตรวจ baseline และแก้เฉพาะจุด (พิจารณา v1.3.1); feature ใหม่แยกแผน (พิจารณา v1.4.0). ไม่เริ่ม refactor, schema migration หรือ immutable revision archive โดยพลการ. ใช้ [DEVELOPMENT](DEVELOPMENT.md) สำหรับ regression เมื่อมีงานใหม่. ไม่ล้างข้อมูลจริงเพื่อทดสอบ.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Project status — snapshot 24 พ.ย. 2569

## ยืนยันจาก GitHub

- Repository `TonChaiya/torskp.page.gd`, main `986c48cdcfe76116742a3a8cccffac2c0e75eef6` (Delete torskp.page.gd.zip, 21:34 ICT)
- Release commit `2d85b762a006864cb3d5090909097bcb65cdf81f` (V.1.1.0, 21:26 ICT). เปลี่ยน workspace, case/company filenames, history merge, six pages/TOR. 10 commits จาก initial ถึง HEAD ในผลค้น GitHub ครั้งนี้. ไม่มีการอ้าง tag/release object ที่ไม่ได้ตรวจ
- ใช้ recursive tree ของ HEAD และเทียบ Git blob SHA กับ snapshot local ที่ประกอบจาก ZIP รุ่นเดิมและไฟล์ปรับปรุงล่าสุด; 69 ไฟล์ที่ track ตรง hash เมื่อ normalize CRLF ตาม repo. migration supplier และเครื่องมือ QA นอก Git ไม่ถือเป็นส่วนของ HEAD. รายการใน REPOSITORY_INVENTORY
- ตรวจประวัติ commit messages, tree, source และ contracts ที่สำคัญ, DDL/migration และ metadata template/font; ไม่ได้ audit ทุกบรรทัดของ dump หรือวิเคราะห์ binary template/font ทั้งหมด และไม่ได้รันแอปบน production. เอกสารนี้เป็น handoff ไม่ใช่รายงาน security/functional certification

## สถานะที่ผู้ใช้ยืนยัน / สถานะจาก source

| งาน | สถานะ |
|---|---|
| ระบบ 6 ขั้น | code อยู่ Git; TOR ขั้น 3 / scope ขั้น 4 |
| Auth + registration approval + account center | code อยู่ Git; ต้องมี migration users |
| ธีม/หน้าจอ compact | code อยู่ Git; รักษา workspace/scroll และปุ่มขนาดเล็ก |
| Company/case filename | code อยู่ Git; ต้องมี supplier_name column |
| Word Merge V4 | ผู้ใช้รายงาน "ดีมากผ่านทุกอย่าง" หลัง V4; code V4 อยู่ Git; ไม่มีผล MS Word test ใหม่ในรอบ docs |
| TOR งบ default 6m, optional revision/year, ข้อ 7 price basis, ข้อความ/ลำดับใหม่ | code อยู่ Git; รอบส่งมอบก่อนหน้าแจ้ง test PHP/mock/HTML; ยังไม่ยืนยัน DB Laragon/MS Word จริงหลังการเปลี่ยนล่าสุดในรอบ docs |
| Fresh install | จัดทำ schema 12 ตารางตรง Dev + supplier migration แล้วในชุด DB baseline; ยังต้องทดสอบ import engine จริงและ bootstrap admin |
| Production deploy | ไม่ตรวจในรอบนี้; Git push ไม่เท่ากับ deploy |

## งานค้างที่เสนอ (ยังไม่ได้อนุมัติหรือแก้)

1. P1: baseline DB และ supplier migration จัดทำแล้ว (รอ commit/ทดสอบ import); ตรวจฐานเป้าหมาย read-only. Dev ไม่มี CHECK รุ่นเก่า; legacy CHECK ต้องแก้เฉพาะเมื่อพบจริง. ไม่ใช้ full dump ทับ
2. P1: ตรวจความปลอดภัยของ dump/ZIP ใน Git history และ access; วางแผนจัดเก็บ backup แบบ private (SECURITY.md). ห้าม rewrite history โดยไม่อนุมัติ
3. P2: UAT TOR ล่าสุดบน Laragon + Word/PDF/Word รวมทั้งสอง price modes, case เก่า/ใหม่, save/reload, ปี/ครั้งที่ว่าง, หลายรายการ/กล่อง. ไม่แก้ผลลัพธ์ขั้นตอนอื่นที่ผ่านแล้วหากไม่มีบั๊ก
4. P2: ทำ smoke tests/fixture ที่ไม่มีข้อมูลจริงสำหรับ form contract และ Word merge; ไม่มี CI suite ใน baseline. เพิ่มเฉพาะเมื่อผู้ใช้ขอ
5. P3: ปรับ README ใน templates/docx ให้ตรงแม่แบบ 3 ฉบับใน Git เมื่อมีงานเอกสารรอบต่อไป. Native export 3/5/6 เป็นทางเลือกในอนาคต ไม่ใช่ requirement เร่งด่วนเพราะ V4 ผ่านในชุดที่ทดสอบแล้ว

ทุกครั้งที่พัฒนาต่อให้ตรวจ SHA ใหม่และอัปเดตสถานะด้วยหลักฐานจริง. อย่าเอารายการเสนอมาเป็นคำสั่งทำงานอัตโนมัติ. รอบนี้สร้างเฉพาะ Markdown ไม่แก้โค้ด/SQL/ข้อมูล/remote.

## Update หลัง docs handoff — Database baseline 24 พ.ย. 2569

- ตรวจ Git HEAD `6c6ed8e01bc78049a4a264c696dca6665a2e5022`, Dev SQL dump MySQL 8.4.3; 12 tables, 100 columns, ไม่มี orphan/invalid JSON จากการตรวจ offline. สร้าง schema structure-only และ read-only checks. ฐาน Dev ไม่ต้อง migrate.
- ไม่มีการแก้ฐานเดิมหรือ PHP/JS/CSS. Fresh install/restore ยังไม่ได้ทดสอบกับ engine จริง.
- .gitignore ป้องกัน dump ใหม่; owner ต้องย้าย backup และ git rm --cached ตาม DATABASE_BACKUP. ไม่ได้ลบข้อมูลหรือประวัติ Git. Hardening constraints ที่ไม่ตรง Dev เป็นงานแยก.

## Update 25 พ.ย. 2569 — Import v1.2.0 candidate

- Git HEAD ก่อนงาน `c923372cf8b10eab12be5152b3d7446036ae807e`; DB baseline และเอกสารครบ, dump 2 ไฟล์ถูกเอาออกจาก HEAD (ยังอยู่ใน history; repo public ณ การตรวจก่อนหน้า). schema v1.1.0 คงเดิม.
- Phase 1–4: implementation ส่งมอบสำหรับ Dev; reader, medicine/price logic, staging, preview UI, confirm transaction. ไม่มีการแก้ระบบอื่น. Phase 5: PHP lint/JS syntax, CSV + fake PDO 52 assertions, Chromium offline mock ผ่าน; **ยังไม่ผ่าน full release gate** เพราะเครื่องทดสอบไม่มี MySQL/PDO MySQL/XMLReader/ZipArchive/SimpleXML. ไม่ได้ทดสอบ Word จริงหรือ Hosting.
- ให้ผู้ใช้ทำ UAT จาก [IMPORT_SYSTEM.md](IMPORT_SYSTEM.md) บนฐาน Dev ที่สำรองแล้ว โดยเฉพาะการจับคู่ collation, XLSX multiline, rollback/replace, stale preview, TOR/Word/PDF/Merge V4. อย่า deploy ก่อนยืนยัน. ไม่ได้ Push เอง. หมายเลข v1.2.0 เป็นเป้าหมาย release หลัง UAT.

### Candidate UI follow-up (25 พ.ย. 2569)
เพิ่ม Popup แยกเพิ่ม/แก้ไข/Import ของรายการยาและราคากลาง พร้อม Excel Template สองไฟล์ใน templates/import. คง schema, transaction, ขั้นตอน Preview และระบบเอกสาร. ตรวจ lint, CSV/fake PDO 52 assertions; XLSX namespace/ตัว template มี optional tests ที่ต้องรันบน Laragon. Production gate ยังไม่ผ่าน.

### Candidate annual price reset
เพิ่มปุ่มล้างราคากลางทั้งหมดเฉพาะ Admin; ตรวจ count/hash และคำยืนยันก่อน DELETE แบบ transaction. ไม่เปลี่ยน schema/เอกสาร. Fake PDO 65 assertions ผ่าน, MySQL/XLSX และ Production gate ยังรอ UAT.

## Update 25 พ.ย. 2569 — v1.2.0 push / v1.3.0 candidate
- v1.2.0 Push `fced437f022f07924ca163141b02a14c4f176b5c` เวลา 11:33 ICT; source Import/Popup/Template/hotfix/export/clear อยู่ Git. พบ runtime staging 3 ไฟล์ใน HEAD; ผู้ใช้เลื่อน cleanup ออกไป ห้ามดำเนินการเอง. SQL dump เก่าออกจาก HEAD แล้ว แต่ประวัติยังอยู่; supplier migration อยู่ Git; สถานะ Git เก่าในเนื้อหาก่อนหน้าเป็นประวัติไม่ใช่งานค้างล่าสุด.
- v1.3.0 Phase 1–4 candidate: archive selection, history-bundle hydrate, admin audit/backfill, safe clear gate. Phase 5: PHP lint + 39 snapshot / 117 import assertions + offline Dev history 17/59, HTML 34 comparisons ผ่าน; **รอ UAT MySQL clone/Word จริง**. ไม่มี migration, ไม่มีการแก้ DB/Production/remote. อ่าน [DOCUMENT_SNAPSHOT.md](DOCUMENT_SNAPSHOT.md) สำหรับข้อจำกัด/ขั้นตอน release. ไม่ใช้เวอร์ชัน candidate เป็นหลักฐานว่าทดสอบ Production แล้ว.

- ตรวจ Chromium แบบ DOM fixture ด้วย `hydrateTor` ตัวจริง: 17 histories / 59 rows, history ID/row token/name/reference price ตรง archive; stale/missing snapshot ถูกปฏิเสธ. ไม่ได้รันการประกอบไฟล์ Word จริง.
