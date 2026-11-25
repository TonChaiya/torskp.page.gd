# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### โครงสร้างที่ใช้งานอยู่ v1.3.0
Source baseline `f30c93cf`. โครงสร้าง PHP หน้าเดิม + PDO + JS/CSS + PHPWord/mPDF ไม่เปลี่ยน framework. Import runtime ใน `core/catalog_import.php`, `core/spreadsheet_import.php`, `core/medicine_import.php`, `core/price_reference_import.php`; export/clear `core/medicine_catalog_tools.php`. Snapshot audit/resolution/backfill `core/document_snapshot.php` + `catalog_snapshot_review.php`; principle/TOR/history bundle ใช้ archive keys และ case/history ownership. `assets/history-docx-merge.js` กับ `core/word_export_lib.php` คง engine เดิม. ข้อความ architecture v1.1 และ candidate ด้านล่างอธิบายวิวัฒนาการ; ดู [DOCUMENT_SNAPSHOT](DOCUMENT_SNAPSHOT.md) และ [IMPORT_SYSTEM](IMPORT_SYSTEM.md) เป็น contract ล่าสุด. ไม่เพิ่ม services หรือ DB tables.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Architecture — v1.1.0

## รูปแบบระบบ

Server-rendered PHP แต่ละหน้ารับ GET เพื่อโหลดข้อมูล/ฟอร์ม และ POST เพื่อบันทึก/สร้างรายงานตาม action; PHP ติดต่อ MySQL ผ่าน `db()` ใน core/config.php. มี HTML/CSS/JS ในไฟล์หน้าเองและ shared assets. ไม่ใช่ Laravel, SPA, Node backend หรือ REST API เต็มรูปแบบ. ไม่ควร include หน้า PHP หลายหน้าใน request เดียว (มี helper ชื่อซ้ำและ output/exit ของตัวเอง).

```mermaid
flowchart TD
  U[Browser / Microsoft Word] --> P[PHP page controllers + views]
  P --> A[core/auth.php]
  P --> C[core/procurement_case.php]
  P --> S[scope_price_approval_store.php]
  P --> W[word_export_lib / template export]
  C --> DB[(MySQL / MariaDB)]
  A --> DB
  S --> DB
  P --> DB
  W --> D[DOCX download]
  P --> F[Print CSS / browser PDF]
  D --> M[history-bundle + history-docx-merge]
  M --> U
```

## หน้า PHP และหน้าที่

| Path | หน้าที่ / จุดเริ่มอ่าน |
|---|---|
| `index.php` | หน้าหลักและลำดับ workflow 6 ขั้น; navigation เข้าชุดงาน |
| `login.php` | login, ตรวจ password/status/throttle, session และ redirect |
| `register.php` | สมัครสมาชิก pending, validate username/password, admin อนุมัติก่อนใช้ |
| `logout.php` | ออกจาก session; รักษาเส้นทางและความปลอดภัยเดิม |
| `change_password.php` | บัญชีของตนเอง, เปลี่ยนรหัสผ่าน, admin จัดการผู้ใช้และสถานะ; ตรวจ self-action/role |
| `admin.php` | จัดการรายการยา, ราคากลาง, ค่าเริ่มต้นผู้ลงนาม, CSV/XLSX import/export ตัวอย่าง, progress และ live search `live_medicines`/`live_prices`; requireAdmin |
| `principle_approval_memo.php` | สร้าง/แก้ชุด case, บริษัท, รายการยา, คณะกรรมการและข้อมูลหลักการ; ส่งออก native DOCX; จุดเริ่มของ workflow |
| `committee_scope_invitation.php` | เชิญกรรมการ/ระเบียบวาระจาก case; บันทึก document แยก; native DOCX |
| `tor_company_purchase.php` | ฟอร์มยา ราคา ขนาดบรรจุ งบ/กรรมการ, `reportItems()`, `reportHtml()`, `saveTorHistory()`, `updateTorHistory()`; `save_report`, `report`, `word`, `save_draft` และฟังก์ชันชุดกรรมการ |
| `scope_price_approval_memo.php` | อนุมัติใช้คุณลักษณะและราคากลาง; ใช้ store เฉพาะ, template export; อ่าน case/เอกสารที่เกี่ยวข้อง |
| `committee_scope_result_memo.php` | รายงานผลพิจารณา; อ่าน case, invitation, TOR history, meeting เดิม; `committeeResultDocumentHtml()`, `committeeResultWordCss()`, action `word_document` |
| `meeting_report.php` | รายงานประชุม, บก.06 และรายละเอียดแนบท้าย; history snapshot, กรรมการ, เนื้อหาการประชุม; `meetingReportDocumentPages()`, `meetingReportWordHtml()` |
| `document_history.php` | รายการ case, completion, ค้นหา/กรอง, เปิดรายหัวข้อ, ลบตามสิทธิ์เดิม, ปุ่มรวม Word ราย case |
| `case_word_merge.php` | POST `bundle_action=prepare`, session+CSRF, ตรวจ case และครบ 6 ขั้น, คืน JSON filename เท่านั้น ไม่รับ DOCX upload/ไม่เขียน DB |
| `word_export.php` | endpoint Word เดิมที่ใช้ร่วมกับ client exporter; อ่าน contract ก่อนแก้ |

## Shared PHP

| Path | Contract |
|---|---|
| `core/config.php` | PDO connection, environment vars, `requireDatabaseTables()` ตรวจมี table ไม่ตรวจ columns |
| `core/auth.php` | `currentUser`, `requireLogin`, `requireAdmin`, `csrfToken`, `verifyCsrf`, password policy, throttling, audit, headers/session |
| `core/procurement_case.php` | CRUD case, decode JSON, document upsert, completion, delete/reset (destructive; ไม่เรียกใน QA) |
| `core/document_filename.php` | แปลงเลข/วันที่ พ.ศ., sanitize, ตัดชื่อบริษัท, `torDocumentFilename`, `torCaseSupplierLabel` |
| `core/document_signer_defaults.php` | load/save ค่าเริ่มต้นผู้เสนอ/ผู้อนุมัติของหลักการ |
| `core/scope_price_approval_store.php` | `scopePriceApprovalInstalled/Load/Exists/Save/Delete`, ข้อมูลแยกเฉพาะขั้น 4 |
| `core/scope_price_template_export.php` | template field mapping, XML logical paragraphs, output native Word ขั้น 4 |
| `core/scope_price_thai_breaker.php` + `core/scope_price_thai_words.txt` | ตัดคำ/จุดขึ้นบรรทัดภาษาไทยสำหรับ scope; ห้ามนำไปแทนข้อความทุกหน้าทั้งหมด |
| `core/word_export_lib.php` | Word profiles, font embed, HTML clean/inline images, DOCX patch, native templates, HTML altChunk fallback/output; อ่าน WORD_EXPORT |
| `components/navbar.php` | เมนูร่วมตาม login/role |
| `components/workflow_steps.php` | สถานะและลำดับ 6 ขั้น; ต้องตรงกับ index/history |

## Assets และ resource

`assets/app-theme.css` ธีมหลัก; `auth-pages.css` login/register; `backoffice-blue.css` หลังบ้าน/บัญชี; `admin-compact.css` admin; `home-blue.css` หน้าหลัก; `history-blue.css` ประวัติ/ปุ่มรวม; `nav-blue.css` navbar. `document-workspace.css/.js` screen-only workspace/fit/scroll; `tor-document-print-name.js` ตั้งชื่อ PDF ที่ browser อาจใช้; `assets/js/word_export.js` client export เดิม; `history-bundle.js` ดึง Word 6 หัวข้อ; `history-docx-merge.js` ผสาน ZIP/OOXML ใน browser. `picture/picture-1.png` ตราครุฑ; `assets/fonts/` ฟอนต์ฝัง; `templates/docx/` แม่แบบ 3 ฉบับ. `tools/build_docx_templates.php` เครื่องมือสร้างแม่แบบ ไม่ควรรันเพื่อแก้ format โดยไม่เก็บต้นฉบับ. `storage/tmp/` พื้นที่ชั่วคราว; `.htaccess` ป้องกัน directory listing / sensitive files. `composer.json/lock` dependency; `database/` schema/migrations/dumps (ดู DATABASE/SECURITY). รายการทุก path ใน REPOSITORY_INVENTORY.

## อ่านตามประเภทงาน

- ข้อมูล TOR → tor_company_purchase.php + procurement_case.php + history-bundle.js + report/meeting consumers
- เปลี่ยนลำดับ workflow → index, workflow_steps, history, bundle order, filename step mapping (ห้ามแก้ DB keys)
- เปลี่ยน Word รูปแบบ → หน้าเฉพาะ + word_export_lib หรือ scope template + merge code + print CSS
- เปลี่ยนบัญชี → auth/login/register/change_password + migration; admin ไม่ใช่ account hub
- เปลี่ยน UI → หน้าและ shared CSS/JS เฉพาะงาน; ห้ามแก้ print CSS โดยไม่ตั้งใจ

## Import v1.2.0 candidate — เฉพาะหลังบ้าน

`admin.php` ยังคง requireAdmin, CSRF และ CRUD/live search เดิม. แทนเส้นทาง import โดย action `import_v2_*` ผ่าน `core/catalog_import.php` (staging, preview, fingerprint, confirm). `core/spreadsheet_import.php` เป็นตัวอ่าน CSV/XLSX และสร้างตัวอย่าง; `core/medicine_import.php` / `core/price_reference_import.php` แยก validation และ decision. `components/catalog_import_panel.php` เป็นฟอร์มสองชุดและ Preview; `assets/admin-import.js` / `.css` จัด UI แบบ scoped. ไฟล์ชั่วคราวอยู่ storage/tmp ที่มี access protection; ไม่มีตารางถาวรเพิ่ม. อ่าน [IMPORT_SYSTEM.md](IMPORT_SYSTEM.md). สถานะ candidate ต้อง UAT จริงก่อน Production.

### Import popup / templates
`assets/admin-modal.js` จัดการ dialog, เพิ่ม/แก้ไขจากผลค้นหา, ปิด/ยกเลิกอย่างปลอดภัย; `assets/admin-import.css` จัด layout และ scroll. `templates/import/*.xlsx` เป็น template แยกประเภท 3 ชีต; endpoint sample เดิมส่งไฟล์นี้และ fallback เป็น generator เดิมเมื่อไม่มีไฟล์. ตัวอ่านรองรับ SpreadsheetML default และ prefixed namespace.

ส่วน annual price reset: `admin.php` GET `price_clear_preview` และ POST `clear_price_refs` → `core/catalog_import.php` (`torClearPricePreview`, `torClearPriceReferences`) → เฉพาะ `medicine_price_references`; popup ใช้ `assets/admin-modal.js` และ `assets/admin-import.css` เดิม. ไม่แก้ TOR/Word/history.

## v1.3.0 candidate — archive selection
`core/document_snapshot.php` เป็น contract ของ snapshot, audit/backfill และ selection keys. `principle_approval_memo.php` ใช้ case-owned name/unit; `tor_company_purchase.php` ใช้ h/c keys กับ history/case rows, live list แยก; `assets/history-bundle.js` hydrate TOR ด้วย archive key. `catalog_snapshot_review.php` เป็น Admin read-only review และ explicit transactional recovery. `core/medicine_catalog_tools.php` ใช้ readiness audit สำหรับ clear. ไม่แก้ merge engine/Word renderer/ตารางฐาน; รายละเอียดและข้อจำกัด [DOCUMENT_SNAPSHOT.md](DOCUMENT_SNAPSHOT.md).
