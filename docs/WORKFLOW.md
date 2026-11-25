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

### Workflow ปัจจุบัน v1.3.0
คงลำดับ 1 หลักการ → 2 เชิญกรรมการ → 3 TOR → 4 อนุมัติคุณลักษณะ/ราคากลาง → 5 รายงานผล → 6 รายงานประชุม. ทุกชุดแยก case_id; ห้ามรวมข้ามชุดหรือเปลี่ยน document_type. หัวข้อ 1 และ TOR ใช้ข้อมูล snapshot เดิม, Word รวม V4 ใช้ archive row token, 2/4/5/6 ใช้แหล่งเดิม. การเปลี่ยน/ล้างคลังต้องไม่กระทบเอกสารที่ snapshot ครบ; การแก้ TOR โดยเจตนาอาจกระทบเอกสารที่อ้าง history เดียวกัน. กู้ข้อมูลด้วยหลักฐานของชุดเดียวกันเท่านั้น. ไม่เปลี่ยนชื่อไฟล์ สูตรราคา งบจัดสรร หรือ layout ที่ผ่านแล้วโดยไม่มีคำสั่ง. อ่าน [DOCUMENT_SNAPSHOT](DOCUMENT_SNAPSHOT.md).

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Workflow และ data contracts

## ตัวตนของชุด

`procurement_cases.id` = `case_id` เป็นตัวเชื่อมหลักทุกหน้า. `supplier_name` เป็นชื่อบริษัทเต็มของ case ไม่ใช่ key และไม่ใช่เงื่อนไขรวมทุก case ของบริษัท. เลขหนังสือหลักการ + วันที่มี unique key เดิม ใช้ค้นหา case เดิมโดย `procurementCaseByPrinciple()`. ข้อมูลรายการยาและกรรมการใน case เป็น JSON; TOR snapshot อยู่ใน tor_histories แยกจากรายการยา master. อย่าแทน snapshot เก่าด้วยข้อมูล master ปัจจุบันโดยอัตโนมัติ.

| ขั้น | หน้า | key / storage | ข้อมูลที่ส่งต่อ |
|---|---|---|---|
| 1 | principle_approval_memo.php | `principle_approval`, procurement_cases + procurement_documents | case_id, supplier_name, ปีงบ, เลขหนังสือ/วันที่, รายการยา, กรรมการ, ผู้ลงนาม |
| 2 | committee_scope_invitation.php | `committee_scope_invitation` | เลขหนังสือ/วันที่, วันเวลา/สถานที่ประชุม, กรรมการ |
| 3 | tor_company_purchase.php | `tor_company_purchase`, tor_histories | history_id, snapshot ยา/ราคา/จำนวน/ขนาดบรรจุ, price_source, กรรมการ, วันที่ TOR, งบ/ครั้งที่/ปี/price_basis |
| 4 | scope_price_approval_memo.php | `scope_price_approval_memos` (แยกตาราง) | บันทึกอนุมัติคุณลักษณะและราคากลางต่อ case |
| 5 | committee_scope_result_memo.php | `committee_scope_result_memo` | รายงานผล, อ้างอิง case/invitation/TOR history, extra.history_id |
| 6 | meeting_report.php | `meeting_report` | รายงานประชุม, บก.06, ตารางแนบท้าย, history_id และข้อมูลการประชุม |

ลำดับนี้คือลำดับหน้าผู้ใช้ ไม่ใช่ลำดับ keys ใน `procurementCaseCompletion()`. ขั้น 4 เช็ค `scopePriceApprovalExists()` ไม่ได้เช็ค `procurement_documents` แบบหัวข้ออื่น. กด Word รวมต้องบันทึกครบทั้ง 6. ใช้ `case_id` ตรงกัน และตรวจ history_id ที่เกี่ยวข้องก่อนส่งออก; โค้ดบางหน้ารองรับประวัติเก่าที่ case_id เป็น NULL จึงต้องระวัง fallback ไม่หยิบชุดอื่น.

## TOR v1.1.0 — ลำดับกรอกและข้อความที่ตกลง

เลือกชุด/วันที่ → ปีงบประมาณ/ครั้งที่ปรับปรุง/วงเงินจัดสรร → ชุดกรรมการ → รายการยา/จำนวน/ขนาดบรรจุ/ราคา → price_basis และแหล่งราคากลาง → ประมวลผล/บันทึก/Word/พิมพ์. ใช้ฟอร์มเดิมและ POST array ของรายการยา. การเปลี่ยน case ต้อง hydrate ค่าที่บันทึกใหม่ ไม่ค้างค่าจาก case ก่อน.

- หัวเอกสารและข้อ 1,6 ใช้ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล; ข้อ 1 ทั้งยาใน/นอกบัญชียาหลัก; ข้อ 1,2 เครือข่าย 10 แห่ง; ข้อ 3.8 วันที่ยื่นข้อเสนอราคา; เงื่อนไขอื่น 1–3; ส่งมอบ 30 วันทำการ (สามสิบวัน); ข้อ 11 ยัง รพ.สต.บ้านสันโค้ง. ไม่แก้ข้ออื่นหรือย้ายตารางเป็นภาคผนวกโดยไม่ได้สั่ง
- `TOR_DEFAULT_ALLOCATED_BUDGET` 6,000,000; `torNormalizeAllocatedBudget` ตรวจ nonnegative และทศนิยม <= 2; `thaiBahtText` แปลงยอดเป็นคำ; ข้อ 1/8 ใช้ allocated_budget เดียวกัน. ข้อ 8 ไม่ใช้ยอดซื้อมาแสดงเป็นงบจัดสรร
- `torOptionalNumber()` รับค่าครั้งที่/ปี; เว้นว่างได้ทั้งสอง; ปี default จาก case, revision default 1; ไม่สับสน empty string กับ missing key. `tor_fiscal_year` ของ TOR ไม่เขียนทับ fiscal_year ของหลักการโดยอัตโนมัติ
- ฟอร์ม POST `tor_price_basis` → `torPriceBasis()` ใช้ `offer` เมื่อค่าตรงเท่านั้น นอกนั้น `reference`. `reportItems()` คำนวณ `reference_total`, `offer_total` แยกกันด้วย priced quantity. โหมดกล่องคิดจำนวนกล่องที่ผ่าน validation และเก็บ requested_quantity แยก; อย่าคูณราคาต่อกล่องด้วยจำนวนหน่วยเดิม
- `reportHtml()` รวม `refTotal` และ `offerTotal`; เลือก `section7Total` ตาม price_basis; label/มูลค่ารายแถว/ยอดท้ายตาราง/จำนวนเงินตัวอักษรต้องสอดคล้องกัน. `reference` ซ่อนราคาเสนอเฉพาะ output; `offer` แสดงสองราคา. ไม่ทำให้ราคาเสนอหายจากฟอร์มหรือ history เพราะ meeting/report ยังใช้
- `save_report` เขียน tor_histories และ upsert document extra โดย merge ค่าเดิม: `history_id`, `allocated_budget`, `budget_revision`, `tor_fiscal_year`, `price_basis`; doc_date มาจาก `tor_doc_date`. `report` และ `word` สร้าง output จากค่าฟอร์ม; ไม่สมมติว่า export เท่ากับ save

## ชื่อไฟล์

`ชุด0024-01-25691103-1252-อนุมัติหลักการ-ชุมชนเภสัช.docx` เป็นตัวอย่างรูปแบบ ไม่ใช่ข้อมูลที่กำหนดตายตัว. `core/document_filename.php` ใช้เลขชุด 4 หลัก, step 2 หลัก, วันที่จริง พ.ศ. YYYYMMDD, เลขหลัง slash หากมี, ชื่อหัวข้อและชื่อบริษัทย่อ. ไม่สร้างเลขหนังสือ/วันที่เท็จ; fallback `ไม่ระบุวันที่`, `ไม่ระบุบริษัท`. Word รวม `ชุด0024-รวมเอกสาร01-06-ชุมชนเภสัช.docx`; ไม่รวมหลาย case แม้บริษัทเดียวกัน. PDF ชื่อที่แนะนำผ่าน document.title ขึ้นกับ browser. รักษาการแสดงเลขไทยในเนื้อหา แต่ใช้เลขอารบิกในชื่อไฟล์.

## v1.3.0 candidate — เอกสารและคลังยา
หัวข้อ 1 เก็บชื่อ หน่วย จำนวน และ spec ที่เลือก ณ บันทึก; TOR ที่มี history ใช้ snapshot ของ history_id ในเอกสาร ไม่อ้าง ID live. หัวข้อ 2/4/5/6 ใช้แหล่งเดิม ไม่แก้. Word รวม V4 ใช้ token ของ history ที่ตรงชุด. การเปลี่ยน/ล้าง catalog ไม่ควรเปลี่ยนเอกสารที่ snapshot ครบ; การแก้ไข TOR ที่บันทึกโดยเจตนายังอาจกระทบเอกสารที่อ้าง history เดียวกัน (ไม่ใช่ immutable versioning). ห้ามกู้ spec จาก catalog ปัจจุบันโดยเดา; audit/backfill ตาม [DOCUMENT_SNAPSHOT.md](DOCUMENT_SNAPSHOT.md).
