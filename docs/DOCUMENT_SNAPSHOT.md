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

หน้า `principle_approval_memo.php`: ช่องชื่อยาและผลค้นหาเต็มความกว้างแถวใน editor pane ทุกขนาด; จำนวน/หน่วย/ลบอยู่บรรทัดถัดไป. จำกัดความสูงผลค้นหา 13rem, เลื่อนใน list, คงค้นหา 3 ตัวอักษร/12 ผลลัพธ์ และการเลือกด้วยเมาส์หรือคีย์บอร์ด. ทั้งแถว PHP และแถวเพิ่มด้วย JavaScript ใช้ layout เดียวกัน; คงชื่อ field และ selection key/Snapshot เดิม.

หน้า `meeting_report.php`: เมื่อมี snapshot รายการยาของรายงานประชุมที่อ่านได้ครบและตรง history_id ให้ใช้รายการที่บันทึกไว้ใน `extra_json.items` สำหรับการเปิดและส่งออกตามปกติ. รายงานเก่าที่ไม่มี snapshot สมบูรณ์ใช้ประวัติ TOR ตาม fallback เดิม. ปุ่ม **ดึงรายการยาและราคา TOR ล่าสุด** เป็น POST `refresh_tor` ผ่านฟอร์มเดิมและ requireLogin/CSRF. ดึง `history_id` ที่หัวข้อ 3 บันทึกไว้เฉพาะ case_id เดียวกัน (หากไม่มี link ใน legacy เลือก history ล่าสุดของ case เดียวกันเท่านั้น); link ผิด case/ไม่มีข้อมูล/แถวอ่านไม่ได้ไม่เปลี่ยนไปใช้ข้อมูลอื่น. การดึงเป็น preview ไม่เรียก save; รักษาข้อมูลวันเวลา สถานที่ กรรมการ ข้อความที่กรอกในฟอร์ม และปรับเฉพาะวลีจำนวนรายการยารูปแบบมาตรฐาน. รายการในตาราง/ตัวแทน `[medicine_items]` ใช้ชุดที่เลือก; ข้อความรายการ/ราคาที่พิมพ์เองไม่ถูกเขียนทับ ต้องตรวจเอง. เมื่อกดบันทึก `save_document` จึงบันทึก snapshot ในรายงานเดิม; ไม่ลบประวัติและไม่แก้ TOR. Hidden source/hash ควบคุมการตรวจว่ารายการหรือ TOR link เปลี่ยนหลัง preview ก่อน Save/Word; ไม่รับราคาจาก hidden fields. คงสูตรราคาเสนอและขนาดบรรจุเดิม รวมถึงการเก็บราคากลางใน snapshot; ไม่เปลี่ยนโหมดราคาของ TOR. Word/PDF และ Word รวม V4 ใช้ข้อมูลของฟอร์มรายงานประชุมตาม source เดียวกัน; ไม่เปลี่ยน engine/แม่แบบ/จัดหน้า.

---

# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### สถานะ Phase 1–5 หลังผู้ใช้ทดสอบ
Implementation Phase 1–4 ส่งมอบและ Push แล้วใน `cb7183f4`; Phase 5 ปิดงานระดับการใช้งานตามผู้ใช้ที่แจ้ง Dev ใช้ได้และ Production ใช้งานจริงได้หมดแล้ว. ไม่มี log แยกราย test case ของ MySQL/Word/concurrency ที่ผู้ช่วยตรวจได้; ผล 39/117 และ offline 17/59/34 ด้านล่างเป็นหลักฐานเดิมเท่านั้น. ห้ามแปลงเป็น PASS ของทุก scenario หรือสมมติว่าจำนวน recoverable 58/deferred 1 คือฐานปัจจุบัน.

กฎปัจจุบัน: เปิด archive ตาม case/history row key, ไม่ remap live ID/name; audit read-only ก่อน explicit backfill, ห้ามเดาจากคลังปัจจุบัน. `recoverable` หรือ `issues` ต้องเป็นศูนย์ก่อน clear, deferred ตามเงื่อนไขเดิมแสดงหมายเหตุและไม่เติม spec ที่ไม่ตรง. ใช้ backup ack, hash recheck, transaction; ห้ามลบเอกสารเพื่อผ่าน guard. หากต้องแก้ TOR โดยเจตนายังอาจกระทบเอกสารที่อ้าง history เดียวกัน; immutable revision archive ไม่อยู่ใน scope รุ่นนี้. ไม่มี migration. Checklist Phase 5 เดิมใช้เป็น regression สำหรับงานใหม่บนฐาน clone ไม่ใช่คำสั่งให้ล้าง Production.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Document Snapshot — v1.3.0 candidate (25 พ.ย. 2569)

## สถานะ / baseline

- พัฒนาจาก main `fced437f022f07924ca163141b02a14c4f176b5c` (v1.2.0). ไฟล์ต้นฉบับที่แก้ตรวจ Git blob SHA ตรงกับ recursive tree ที่อ่านหลัง Push. ยังไม่ได้ commit/push candidate นี้.
- เป้าหมายคือ **การเปลี่ยน/ล้าง catalog ไม่เปลี่ยนเอกสารเดิม** ไม่ใช่ระบบ immutable document versioning, ไม่ lock เอกสารจากการแก้ไขโดยเจตนา, ไม่ redesign layout และไม่เปลี่ยนสูตรราคา.
- ไม่มี SQL migration: `procurement_cases.items_json`, `tor_histories.items_json` และ `procurement_documents.extra_json` มีอยู่แล้ว. ใช้ schema v1.1.0 12 ตารางเดิม. ไม่ import schema ทับฐานเดิม. ก่อนใช้จริงต้องสำรอง DB และทดสอบบน clone.

## Phase 1 — สัญญาข้อมูลที่ตรวจพบ

| หน้า | แหล่งข้อมูลยาเดิม | การแก้ v1.3.0 |
|---|---|---|
| `principle_approval_memo.php` | `procurement_cases.items_json`: id/name/unit/quantity; POST เดิม prefer live name | เปิด/Word จากแถวที่บันทึก; เมื่อเลือกยาจากคลังและบันทึกหัวข้อ 1 เก็บ spec เพิ่ม |
| `committee_scope_invitation.php` | ข้อมูลชุดและกรรมการ ไม่ query catalog | ไม่แก้ |
| `tor_company_purchase.php` | `tor_histories.items_json` มีข้อความและราคา แต่ JS/POST เดิม remap เป็น live medicine ID | ใช้ selection key ของ archive ที่ server ตรวจสอบ; ราคา/จำนวนเดิมไม่ปัดทิ้งเมื่อเปิด |
| `scope_price_approval_memo.php` | ข้อมูลชุดและ `scope_price_approval_memos.data_json`; ไม่ query catalog | ไม่แก้ |
| `committee_scope_result_memo.php` | ประวัติ TOR ที่อ้างอิง (`items_json`) | ไม่แก้ |
| `meeting_report.php` | ประวัติ TOR และ extra ของเอกสาร | ไม่แก้ |
| `assets/history-bundle.js` | hydrate TOR เดิมค้น ID/live name | ใช้ history key และ history_id ของชุด; ห้ามจับชื่อข้ามชุด |
| `assets/history-docx-merge.js`, `core/word_export_lib.php` | การประกอบ DOCX V4 และ Word/PDF formatting | ไม่แก้ |

## Phase 2–3 — Snapshot และการเปิด/บันทึก

1. รายการหัวข้อ 1 ใหม่: เก็บ `medicine_id` (เป็น provenance ไม่ใช่ FK ของ archive), `name`, `unit`, `quantity`, `specification` และ `snapshot_source=selected_catalog_at_save` เมื่อเลือก catalog ที่มีชื่อ/หน่วยตรงกัน; manual row ที่ไม่มี spec ยังแสดงหัวข้อ 1 ได้ แต่ห้ามสร้าง TOR จาก spec ที่เดาไม่ได้. เมื่อเปิดหัวข้อ 1 ที่บันทึก ให้ใช้ชื่อ/หน่วยเดิม; เปิด focus ไม่ reset ID. ผู้ใช้เลือก live ใหม่หรือแก้ข้อความเองโดยเจตนาได้.
2. TOR เก็บ `name`, `unit`, `specification`, `medicine_id` (อาจ 0/ไม่มีใน legacy), `quantity`, `requested_quantity`, `package_size`, `package_count`, `reference_price`, `offer_price`, `offer_total` ตามสัญญาเดิม; save ใหม่เพิ่ม `snapshot_source=saved_tor`. ไม่ต้องมี ID ใน catalog ปัจจุบัน. `reference_total` ยังใช้สูตรเดิม quantity × reference_price; `offer_total` สูตรเดิม. price_basis, allocated budget, revision/year อยู่ใน extra_json เดิม.
3. `core/document_snapshot.php`: parse JSON อย่างเคร่งครัด, audit, resolution และ explicit backfill. `torSnapshotSelections` สร้าง map key `h:<history_id>:<row index>:<fingerprint>` และ `c:<case_id>:<row index>:<fingerprint>`; key เป็น token สำหรับ form ไม่ใช่ medicine_id ใน DB. TOR รับเฉพาะ token ที่ตรง case/report ที่ส่ง, ตรวจ fingerprint กับข้อมูลฝั่ง server; stale/malformed/cross-case หรือไม่มี spec ต้องแสดง error ไม่ silently skip. Positive ID เป็นการเลือก live โดยตั้งใจและต้องตรงชื่อที่แสดง; live search list ไม่รวม archive. Principle ใช้ raw case key ของรายการเดิม. ไม่เชื่อข้อมูล spec ที่ส่งจาก client.
4. หน้า TOR ใช้ `procurement_documents.extra_json.history_id` เป็นหลัก; หากมี link แต่ไม่พบห้าม fallback เป็นรายการชุดอื่น/สร้าง TOR ใหม่ทับโดยพลการ. Legacy ไม่มี link ใช้ history ของชุดตามกลไกเดิม. JS `historySnapshotKeys`, `snapshotMedicines`, `savedTorHistoryIds` เป็น contract กับ history-bundle; Word รวมต้องส่ง token ไม่ใช่ remap จาก catalog. ไม่แก้ DOCX merge engine V4.
5. ประวัติที่มี `requested_quantity`/package ใช้ค่าที่บันทึก ไม่ดึงจำนวนหัวข้อ 1 ปัจจุบันมาทับ history. การแสดงราคาจาก history ไม่ round 2 ตำแหน่งใน input ก่อน POST; หน้าพิมพ์ยังจัดรูปแบบเดิม. การแก้ไข/บันทึก TOR โดยผู้ใช้โดยเจตนายังเปลี่ยน history และอาจเปลี่ยนเอกสารที่อ้าง history เดียวกัน; งาน immutable revision archive แยกต่างหาก.

## Phase 4 — ตรวจสอบและเติมข้อมูลเก่า (ต้องมีผู้ดูแลยืนยัน)

เข้า `admin.php` → รายการยา → **ตรวจ Snapshot เอกสาร** (`catalog_snapshot_review.php`, Admin + POST CSRF). เปิดอ่านได้โดยไม่แก้ฐาน. แสดงจำนวนชุด/ประวัติ/รายการ, recoverable, issues, deferred และดู spec ที่จะเติมรายแถวได้. Backfill ทำเมื่อกดยืนยัน + รับทราบ backup เท่านั้น. Re-read และ lock ภายใน transaction; hash ข้อมูลเปลี่ยนแล้วต้องตรวจใหม่; UPDATE เฉพาะ `procurement_cases.items_json` ที่ตรงค่าเดิม และรักษา `updated_at` เดิม. เพิ่มเฉพาะ spec ที่ขาด พร้อม `snapshot_source=recovered_saved_tor`, `snapshot_history_id`, `snapshot_history_row`; ไม่เปลี่ยนชื่อ/หน่วย/จำนวน/ID/extra เดิม ไม่ UPDATE TOR, ไม่ลบเอกสาร. Rollback ทั้งชุดเมื่อผิดพลาด.

- หลักฐานที่ใช้กู้: TOR ใน **case เดียวกัน**, ชื่อและหน่วยตรงกันแบบ exact หลัง normalize เฉพาะเลขไทย/อารบิกและ trim ขอบ, ID ถ้าทั้งสองฝั่งมี positive ต้องตรง, spec ไม่ว่างและทุก candidate ให้ spec เดียวกัน. ไม่ fuzzy match, ไม่ใช้ catalog ปัจจุบันเดาว่าเป็นอดีต. การเติมจาก TOR เป็น provenance จาก TOR ไม่ใช่รับรองว่าเป็น spec ณ วันหัวข้อ 1.
- `issues`: JSON ผิด, ขาดข้อมูลที่จำเป็น, ยอดรวม/ขนาดบรรจุขัดแย้ง, history/doc/case link ไม่สมบูรณ์, case ยังไม่มี TOR ครบและ spec หาย — **บล็อกการล้าง**. ให้ตรวจเอกสารและหลักฐานจริงก่อน. ไม่ auto-repair.
- `recoverable`: ต้องตรวจและบันทึกก่อนล้าง. `deferred`: หัวข้อ 1 มี name/unit/quantity ครบแต่ TOR ที่อ้างอิงเก็บรายการต่างกันและมี snapshot ครบ; เอกสารเดิมแต่ละฉบับยังเปิดได้จากข้อมูลตัวเอง. ไม่เติมข้อมูลที่ไม่ตรงกัน. ไม่ขัดขวางการล้างหลัง recoverable/issue เป็นศูนย์; แสดง warning ใน dialog. หากต้องสร้าง TOR ใหม่จากหัวข้อ 1 ในอนาคต ให้เลือกยาจากคลังใหม่โดยเจตนา.
- ปุ่มล้าง `medicines` ตรวจ audit และ hash ของทั้ง catalog + documents ก่อนและใน transaction; stale confirmation, FK inbound, issue/recoverable บล็อก. คง backup ack, คำยืนยัน, batch DELETE, rollback, ไม่ TRUNCATE/ไม่ reset AUTO_INCREMENT. ไม่แตะ `medicine_price_references` หรือเอกสาร. ปิดฟอร์มเอกสารที่เปิดค้างก่อน backfill/clear; ทำใน maintenance window, หลีกเลี่ยงผู้ใช้อื่นแก้ข้อมูลพร้อมกัน. ห้าม bypass guard. โหมดล้างราคากลางและ Import เดิมไม่เปลี่ยน.

## Phase 5 — ผลตรวจและ release gate

- Offline Dev dump ที่ส่งเมื่อ 24 พ.ย. 2569 (ไม่ใช่ฐาน live ปัจจุบัน): 17 cases, 17 histories, 59 principle rows และ 59 TOR rows; 44 TOR rows ไม่มี medicine_id แต่มีข้อความ/ราคา. Audit พบ recoverable 58, issues 0, deferred 1 (case 11 แถว 1 ชื่อ/หน่วยไม่ตรง TOR; ไม่กู้แบบเดา). ตัวเลขนี้อาจเปลี่ยนเมื่อเปิดตรวจฐานจริง. ไม่ commit dump/fixture จริง.
- PHP CLI test `php tests/document_snapshot_test.php`: 39 assertions ผ่าน (synthetic fixtures + fake PDO, rollback/stale/ownership/old data/complete vs deferred). `php tests/catalog_import_test.php`: 117 assertions ผ่านในสภาพแวดล้อมนี้, XLSX tests ถูก skip เนื่องจาก PHP extensions ไม่ครบ. จำนวน 116 ในประวัติเป็นผลจากรอบที่มี adapter; ไม่ใช่การรับรอง native XLSX รอบนี้.
- Offline integration: เรียก `reportItems` และ `reportHtml` ของหน้า TOR จากข้อมูล dump เดิม; เปรียบเทียบกรณี catalog ถูกเปลี่ยนกับ catalog ว่าง: 17 history, 59 rows (ข้อความ, qty, ราคากลาง, ราคาเสนอ, ยอด), HTML 34 ครั้งทั้งสองโหมดตรงกัน. ไม่ใช่ผล DB/Word Desktop จริง.
- ต้องทดสอบ Laragon/MySQL จริงบน **ฐาน clone**: backup/restore; เปิด review แล้วเทียบผลจริง; ตรวจ 58 รายการก่อนยืนยัน (ไม่ยึดเลขนี้หาก DB เปลี่ยน); ตรวจ snapshot JSON/จำนวนชุดและ timestamps ก่อน–หลัง; สร้างชุดใหม่จากยาเดิม, บันทึก TOR ทั้งสอง mode และ package; เปลี่ยน spec/name ในคลัง, เปิดหัวข้อ 1/3/5/6, ส่งออก Word/PDF และ Word รวม V4 ก่อนและหลัง clear; เปรียบเทียบข้อความและยอดจริงใน Word; ล้างแล้ว import ใหม่, ตรวจ doc ทุกชุดเดิม; ทดสอบ pending case/unknown token/cross-case/stale/rollback; ทดสอบ import/CRUD เดิม. หากพบ discrepancy ห้าม clear ฐานจริง, rollback ด้วย backup และรายงาน. หลัง UAT ค่อย push/release.
- ข้อจำกัด: ไม่มี MySQL Server/PDO MySQL, native XLSX extensions, DOM/Word Desktop ในเครื่องทดสอบนี้. ยังไม่รับรอง concurrency/locking บน DB จริงหรือ Production. ไม่มีการแก้ฐาน Dev/Hosting/remote. หากต้องแก้โครงสร้างฐานในอนาคตต้องขออนุมัติแยกและจัด migration พร้อม backup/rollback.

- ตรวจ Chromium แบบ DOM fixture ด้วย `hydrateTor` ตัวจริง: 17 histories / 59 rows, history ID/row token/name/reference price ตรง archive; stale/missing snapshot ถูกปฏิเสธ. ไม่ได้รันการประกอบไฟล์ Word จริง.
