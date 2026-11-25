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

คง PHP Word renderer และ Word Merge V4 เดิม. `meeting_report_form` มี hidden `meeting_items_source`, `meeting_items_hash`; Word รวมส่งผ่าน FormData ตามปกติ. การเปิด/ส่งออกปกติใช้ meeting snapshot ที่สมบูรณ์; preview ใช้ TOR ที่เลือก. หาก fingerprint เปลี่ยนก่อน Word จะตอบ 409 ให้ตรวจข้อมูลใหม่ ไม่สร้าง DOCX จากยอดที่เปลี่ยนโดยไม่แจ้ง.

---

# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### Word/PDF ใน stable maintenance baseline
v1.3.0 ปรับ hydration ของ TOR ใน Word รวมให้ส่ง archive row key ที่ server ตรวจ case/history ownership แทน live catalog ID. ไม่แก้ merge engine V4, font/template, Thai Distribute หรือ table spacing. ผู้ใช้เคยยืนยัน V4 ผ่านและปัจจุบันรายงานว่าเว็บใช้งานจริงได้หมด; ผู้ช่วยไม่ได้ทดสอบ Microsoft Word/Production จริงเพิ่มเติม. คงข้อจำกัด altChunk/Word Online/LibreOffice ตามประวัติ. เมื่อมีงาน export ใหม่ ให้ตรวจรายหัวข้อและรวมใน MS Word Desktop พร้อมค่า TOR สองโหมด/ขนาดบรรจุ/ชื่อไฟล์และ snapshot; อย่าอ้างว่า DOM fixture คือ DOCX rendering จริง. ดู [DOCUMENT_SNAPSHOT](DOCUMENT_SNAPSHOT.md).

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Word / PDF — สัญญาการจัดรูปแบบที่ต้องรักษา

## ตัวสร้างรายหัวข้อ

| Step | ตัวสร้างหลัก ณ baseline | จุดแก้ที่ถูกต้อง |
|---|---|---|
| 1 หลักการ | `outputPrincipleApprovalMemoDocx()` + template/native fallback | principle_approval_memo.php, word_export_lib, template |
| 2 เชิญกรรมการ | `outputCommitteeScopeInvitationDocx()` + template/native fallback | invitation page + library/template |
| 3 TOR | `reportHtml()` → `outputWordHtml(...,'tor')` → HTML altChunk DOCX | TOR report HTML/CSS และ Word profile |
| 4 อนุมัติคุณลักษณะฯ | `scopeApprovalOutputTemplateWord()` | scope page + scope_price_template_export + template, Thai breaker |
| 5 รายงานผล | `committeeResultDocumentHtml(...,true)` + `committeeResultWordCss()` → outputWordHtml | result page + profile |
| 6 รายงานประชุม | `meetingReportWordHtml()` → outputWordHtml profile meeting | meeting page + profile |

`outputWordDocx()` ที่ใช้งานอยู่สร้าง HTML altChunk ผ่าน `wordAltChunkHtml` / `wordCreateAltChunkDocx`; แม้มี helper `outputWordDocxViaPhpWord()` ไม่ได้แปลว่าทุกหน้าส่งออก native. `wordPatchDocx()` และ `wordEmbedProjectFonts()` มีบทบาทกับเส้นทาง native. อ่าน call graph จริงก่อนแก้. หลายหน้ามี output buffering สำหรับแปลงเลขไทยใน visible DOCX XML/HTML; อย่าแปลงเลขใน relationship/ID/XML metadata. แม่แบบจริงมีสามไฟล์ `templates/docx/{principle_approval_memo,committee_scope_invitation,scope_price_approval_memo}.docx`; README ในโฟลเดอร์นั้นยังไม่กล่าวถึง scope ครบ. ห้ามสร้าง template ทั้งหมดทับแม่แบบที่ผ่าน QA. `assets/fonts` ชื่อภายใน TH SarabunIT๙ (regular/bold/italic/bolditalic) ฝังได้ตาม README ฟอนต์. `picture/picture-1.png` ตราครุฑ.

## Word Merge V4 — browser-side assembly

V1 ฝัง DOCX ซ้อนทั้งหมดใน altChunk เดียวกับค่าเริ่มต้นร่วม → ผู้ใช้พบฟอนต์/ขนาด/indent/spacing/Thai Distribute เพี้ยน. V2 ผสาน OOXML โดยแยก Section/Style ของ native และคง HTML altChunk ชั้นเดียว. V3 ปรับย่อหน้าภาษาไทยเฉพาะเนื้อหา. V4 zero table paragraph. ผู้ใช้แจ้งผ่านทุกอย่างในชุดทดสอบเดิม 24 พ.ย. 2569. รักษา V4 เป็น regression baseline.

1. `document_history.php`: ปุ่ม `data-merge-word` ราย case; load `history-docx-merge.js?v=20261124-bundle4` ก่อน `history-bundle.js`.
2. `case_word_merge.php`: POST `bundle_action=prepare`, case_id, CSRF; ตรวจ session และ completion 6 ขั้น; คืน filename. ไม่ upload Word, ไม่เขียนฐาน/ไฟล์ชั่วคราว.
3. `history-bundle.js`: GET หน้าเอกสารด้วย case_id, parse form, สร้าง POST action เดิม, เติมข้อมูล TOR จาก history/JS hydration, fetch Word ทั้ง 6 ด้วย session same-origin. ลำดับ hard-coded ตาม workflow. ตรวจ HTTP และ DOCX ก่อนรวม. ห้ามเปลี่ยน form selector/hidden fields โดยไม่ทดสอบ merge.
4. `history-docx-merge.js`: unzip/zip ใน browser, parse XML แบบปฏิเสธ DTD/entity, import native XML, map styles/numbering/rels, copy images/header/footer, แยก Section, ตรวจ embedded font compatibility, map drawing IDs, ป้องกัน relationship dangling. HTML altChunk ที่เป็น text/html ยังอยู่ (matchSrc true); ปฏิเสธ DOCX altChunk ซ้อน. `fixNativeThaiBody`/`fixHtmlThaiBody` ปรับเฉพาะย่อหน้าเป้าหมาย; `zeroNativeTableParagraphs`/`zeroHtmlTableParagraphs` ตั้งระยะตารางเป็นศูนย์. `window.TorskpDocxMerge.combine(blobs)` ต้องรับ 6 blobs; คืน blob/htmlSteps. ไม่เพิ่ม Word server หรือ Composer package ใหม่เพียงเพื่อ merge.
5. ดาวน์โหลด `ชุดNNNN-รวมเอกสาร01-06-บริษัท.docx`. เปิด MS Word Desktop เพื่อประมวลผล HTML และบันทึกใหม่ได้. Word Online/LibreOffice อาจตีความ altChunk ต่างกัน; ยังไม่รับรองความเหมือนทุกโปรแกรม.

### Form/action contracts (อย่าเปลี่ยนโดยไม่แก้ consumer)

| Step | selector ใน history-bundle.js | POST action |
|---|---|---|
| 1 | `#principle_case_form` | word |
| 2 | `#committee_scope_form` | word |
| 3 | `form.space-y-5[method="post"]` | word |
| 4 | `#scope-form` | word |
| 5 | `#memo-form` | word_document |
| 6 | `#meeting_report_form` | word |

TOR field ใหม่ `tor_allocated_budget`, `tor_budget_revision`, `tor_fiscal_year` และ `tor_price_basis` ต้องผ่าน hydration และ FormData เดิม; JS merger ไม่คำนวณราคาเอง. ตรวจกรณีกรอก 2 โหมดและเว้นว่างปี/ครั้งที่. ถ้าปรับ selector ให้ปรับทั้งหน้าและ bundle tests.

## มาตรฐานเอกสาร

- รักษากระดาษ A4, ขนาดฟอนต์และระยะขอบรายหน้า/Section; บันทึกข้อความขอบบนเป้าหมาย 1.5 cm ตามการตกลงเดิม ไม่บังคับใช้กับ TOR หรือทุก template โดยไม่ดูไฟล์จริง. ตรวจ 16pt เนื้อหาตามต้นฉบับ หัวเรื่อง/ส่วนราชการอาจต่างกัน. ผลรายงานต้องรักษาบรรทัดส่วนราชการที่ปรับให้พอดีหน้า
- Thai distributed alignment: OOXML `w:jc w:val="thaiDistribute"` สำหรับย่อหน้าเนื้อหาเป้าหมาย; `w:rFonts` และ `w:szCs` (Thai complex script) + ภาษา th-TH ตาม template. HTML `text-align` / `text-justify`/MSO CSS อาจถูก Word แปลต่างกัน. ไม่บังคับทั้งตาราง หัวเรื่อง ลายเซ็น หรือย่อหน้าที่ต้องจัดกลาง/ขวา
- Table V4: left/right indent = 0, spacing before/after = 0 ในข้อความภายในตาราง Word รวม; รักษา line spacing, font, table widths และ alignment ตามเดิม. อย่าตั้ง spacing ทุกย่อหน้านอกตารางเป็นศูนย์
- invitation ไม่มีเลขหน้าตามคำขอก่อนหน้า; รักษาเลขหน้าและหัว/ท้ายกระดาษของแต่ละหัวข้อตามแบบเดิม. ทดสอบหน้าแรก/หน้าต่อเนื่องและลายเซ็นไม่โดดหน้าว่าง
- PDF หลักผ่าน browser print (`window.print()` และ print CSS). ชื่อ PDF ผ่าน document.title เป็น suggested filename ไม่รับประกันทุก browser; Foxit กับ browser อาจ scale ต่างกัน. Preview screen-only ไม่ใช่การเปลี่ยนค่ากระดาษจริง

## วิธี QA ที่จำเป็นเมื่อแก้ export

สร้างจาก case เดียวกับรายการยา/ราคา/กรรมการเหมือนกัน; ดาวน์โหลด Word 6 ไฟล์และรวม; ตรวจ DOCX package (zip integrity, XML well-formed, relationship targets, Section, fonts, paragraph/table properties) และเปิดใน MS Word Desktop; เปรียบเทียบทุกหน้าแบบ side-by-side กับ Word แยก, รวมทั้งตัวอย่าง 1 รายการและหลายหน้า, ภาษาไทย, Thai digits, ราคา/ข้อความ, table 0, garuda, headers, signature. Export PDF ด้วย browser ตรวจ print preview และ scale 100% ตามรูปแบบจริง. อย่าอ้าง PASS visual ถ้าตรวจเฉพาะ XML หรือ HTML. หากต้องแก้ Native DOCX ของ 3/5/6 ในอนาคต ต้องทำทีละหัวข้อและให้ผู้ใช้อนุมัติ ไม่ใช่งานที่จำเป็นตอนนี้.

## v1.3.0 candidate — hydrate TOR จาก archive
แก้เฉพาะ contract data adapter ใน `assets/history-bundle.js` และ cache version ใน `document_history.php`: ใช้ savedTorHistoryIds/historySnapshotKeys/snapshotMedicines ของหน้า TOR, ส่ง key ที่ตรวจ scope/fingerprint; ไม่ remap medicine_id หรือชื่อจาก live list. `assets/history-docx-merge.js` และ `core/word_export_lib.php` ไม่แก้; V4 formatting และ section/table settings คงเดิม. Offline HTML 34 comparisons ผ่านจาก dump เดิม แต่ต้องตรวจ Word/PDF/Merge Desktop ก่อน release. อ่าน [DOCUMENT_SNAPSHOT.md](DOCUMENT_SNAPSHOT.md).

- ตรวจ Chromium แบบ DOM fixture ด้วย `hydrateTor` ตัวจริง: 17 histories / 59 rows, history ID/row token/name/reference price ตรง archive; stale/missing snapshot ถูกปฏิเสธ. ไม่ได้รันการประกอบไฟล์ Word จริง.
