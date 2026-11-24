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
