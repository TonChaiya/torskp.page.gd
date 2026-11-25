# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### สถานะ Import ที่ใช้งานจริงใน v1.3.0
v1.2.0 source/hotfix ทั้งหมดอยู่ Git และสืบทอดใน v1.3.0. ผู้ใช้ยืนยันระบบจริงใช้งานได้; ไม่มีรายงานทดสอบแยกราย XLSX/CSV/engine จาก Hosting ที่ผู้ช่วยตรวจได้. ไม่เปิดงานเดิมซ้ำเพียงเพราะหัวข้อ Candidate. ผลทดสอบล่าสุดก่อนปิดรุ่น: Import 117 assertions ด้วย fake PDO (native XLSX skipped); ตัวเลข 52/65/84/88/116 ในประวัติเป็นผลของคนละรอบ ไม่รวมกัน.

- แยกคลังยา (3 คอลัมน์, spec multiline) และราคากลาง (4 คอลัมน์, decimal 4 ตำแหน่งพร้อม warning) เสมอ; first worksheet Import, strict header, read-only preview, blocking error/duplicate/conflict, explicit confirmation และ rollback.
- Export Excel รายการยาทั้งหมดไม่ใช่ full DB backup; clear ราคากลางแยกจาก clear ยา. โหมด replace มีเฉพาะราคากลาง; clear medicines ใช้ snapshot readiness v1.3.0 แทน blanket guard เดิม (ดู [DOCUMENT_SNAPSHOT](DOCUMENT_SNAPSHOT.md)). ไม่ bypass guard หรือ reset ID.
- Hotfix POST 404 และ XMLReader อยู่ใน source ปัจจุบัน; ห้ามนำโค้ดเก่ามาทับ. Runtime staging ออกจาก HEAD แล้ว; ignore `/storage/tmp/*` พร้อม exceptions `.gitkeep` และ `.htaccess`. ตรวจ writable/deny HTTP และ hosting limits ก่อน Import ชุดใหญ่; ไม่รับรอง stress limit เป็น throughput จริง. ทดสอบ import/clear บน clone เมื่อต้องแก้ระบบ.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Import System — TOR SKP v1.2.0 candidate

วันที่ 25 พฤศจิกายน 2569 | Baseline `c923372cf8b10eab12be5152b3d7446036ae807e`. ใช้ PHP 8.1+, PDO MySQL, schema v1.1.0; ไม่มี migration หรือ dependency ใหม่. ไม่ใช่ release Production ที่ผ่าน UAT แล้ว.

## ติดตั้งไฟล์ใน Dev

สำรองโค้ดและ DB ที่ใช้งานอยู่ แล้วแตก ZIP ลง root `C:\laragon\www\torskp.page.gd` ตาม path. ไม่ import schema/migration ทับฐานเดิม. Ctrl+F5 → หลังบ้าน `admin.php`. `tests/` เป็นเครื่องมือ CLI สำหรับ Dev เท่านั้น ไม่ต้องนำขึ้น Hosting. ไม่ใช่ตัวติดตั้ง. หาก rollback code ให้คืน admin.php รุ่น v1.1.0 และเอาไฟล์ใหม่ออกจาก working tree หลังตรวจว่าไม่มีงาน import กำลังรัน; การ rollback code ไม่ย้อนข้อมูลที่ผู้ใช้ยืนยัน commit แล้ว ใช้ backup เมื่อจำเป็น.

## สัญญาข้อมูลและขอบเขต

| ประเภท | คอลัมน์ Excel/CSV ตามลำดับ | คีย์ DB |
|---|---|---|
| รายการยา | ชื่อยา, หน่วย, คุณสมบัติยา | medicines.name unique; unit ต่างกันเป็น conflict |
| ราคากลาง | รายการยา, รูปแบบยาและความแรง, หน่วย, ราคากลางยาต่อหน่วย (บาท) | medicine_name + dosage_form_strength + unit |

ใช้ไฟล์ตัวอย่างจากปุ่มเดิม (Excel ถ้ามี ZipArchive, CSV fallback). ข้อมูลเริ่มจากหัวคอลัมน์ที่ตรงประเภทใน worksheet `sheet1.xml`; ไม่รวมหลาย worksheet. หลีกเลี่ยง merged heading, แถวหัวข้อซ้ำ, สูตร Excel (Paste Values ก่อน), .xls, CSV delimiter อื่น และไฟล์ไม่ใช่ UTF-8. XLSX รองรับ shared strings / inline rich text และตำแหน่งเซลล์ว่าง; CSV รองรับ quoted multiline, comma, double quote, BOM และ CRLF/LF; malformed quotes จะ reject ทั้งไฟล์. เลขแถว XLSX ใช้ row index จริง, CSV ใช้ logical record (ข้อความหลายบรรทัดเป็น record เดียว). แถวว่างทั้งหมดข้าม. ไม่อนุญาตคอลัมน์เกินที่มีข้อมูล.

คุณลักษณะ: 1 ยา = 1 แถว, คอลัมน์ C เป็นข้อความหลายบรรทัด (Alt+Enter / Wrap Text), เก็บใน `specification` TEXT. รักษา LF ภายใน เลขข้อ ขีด ข้อย่อย; normalize CRLF/CR เป็น LF, trim ขอบเซลล์เท่านั้น. ไม่สร้างเลขข้อใหม่. ยาเดิมที่ spec ว่างในไฟล์ให้รักษา spec เดิม; ยาใหม่ spec ว่างเป็น warning และใช้ชื่อยาแทนตามพฤติกรรมเดิมหลังรับทราบ. ชื่อยา/หน่วยต้องไม่ว่างและอยู่ในขนาดคอลัมน์; หน่วยต่างกันแจ้ง conflict ไม่แก้เอง. ไม่สร้าง mapping ข้ามตาราง. ราคาใช้เลขทศนิยม string ไม่ float; 0 เป็น warning, ทศนิยมที่มีค่าจริงเกิน 4 ตำแหน่งให้แสดงค่าก่อน–หลังปัดเศษแบบ half-up เป็น warning และต้องรับทราบก่อนยืนยัน; ราคาว่าง/ติดลบ/ตัวอักษร/รูปแบบผิด/เกิน DECIMAL(15,4) หลังปัดเศษ เป็น error. ชื่อยา ความแรง และหน่วยในคลังราคากลางรักษา LF ภายในเซลล์ตามต้นฉบับ ไม่เปลี่ยนคีย์เป็นช่องว่าง; อักขระควบคุมอื่นยังไม่อนุญาต. ราคาใหม่ที่เท่าราคาเดิมไม่เขียนซ้ำ (source_file เดิมคงอยู่).

## Workflow และสถานะ

1. เลือกประเภท/ไฟล์/โหมด (รายการยา merge เท่านั้น; ราคา merge หรือ replace). กดตรวจสอบก่อนนำเข้า. อ่านไฟล์และ query target table แบบ read-only; ไม่เขียนตารางจริง.
2. Preview: สถานะ new/update/same/warning/error/duplicate/conflict เป็นกลุ่มแยกกัน; คำเตือนอาจเป็นรายการใหม่หรือเดิม. ตัวกรอง, ค้นหา, 25/50/100 แถว, ดูค่าก่อน/หลัง และรายงาน CSV คำเตือน/error. แสดงจำนวนที่จะนำออกใน replace.
3. error/duplicate/conflict block ทั้งงาน (ไม่ skip แถวผิด), แก้ไฟล์แล้ว cancel/re-upload. Warning ต้องรับทราบ; replace ต้องรับทราบจำนวนที่จะนำออกเพิ่มเติม. ปุ่มยืนยันถูกตรวจซ้ำ server-side. ยกเลิกก่อน confirm ล้าง staging เท่านั้น.
4. Confirm ตรวจ fingerprint อีกครั้งหลังล็อก target table range; หากมีการเปลี่ยนแปลงหลัง preview ให้ยกเลิกและตรวจใหม่. Batch สูงสุด 100 rows/ประมาณ 256 KiB ต่อ statement; ใช้ upsert ตาม unique key, skip same; replace ราคา DELETE เฉพาะ ID เดิมที่ไม่อยู่ในไฟล์ใหม่หลัง upsert. ทั้งหมดอยู่ใน transaction เดียว; ไม่ TRUNCATE ไม่เปลี่ยน ID ที่ยังอยู่. Rollback เมื่อเกิด exception ก่อน commit. การเชื่อมต่อขาดระหว่าง COMMIT อาจทำให้ผลไม่แน่นอน ให้ตรวจฐานจริงก่อนเริ่มซ้ำ; ไม่ retry commit อัตโนมัติ.
5. สรุปจำนวนที่ตรวจ/บันทึก/ไม่เปลี่ยน/นำออก แล้วกดรีเฟรชรายการ. ไม่เปลี่ยน TOR, Word/PDF, ประวัติ หรือบัญชี.

## โครงสร้างไฟล์

- `admin.php`: route `import_v2_prepare|status|page|detail|errors|confirm|cancel`; auth + CSRF เดิม, legacy direct import POST ถูกปฏิเสธ, CRUD/ค้นหา/ผู้ลงนามคงเดิม.
- `core/spreadsheet_import.php`: validate upload/zip, CSV parser, XLSX streaming, template generator.
- `core/medicine_import.php`, `core/price_reference_import.php`: validate/decision เฉพาะตาราง.
- `core/catalog_import.php`: staging JSONL + meta, query batch ด้วย collation/WEIGHT_STRING, ตรวจ InnoDB/unique/collation, snapshot, transaction, report. Token random 128-bit + session owner; ทุก request requireAdmin, POST CSRF. ปลด session lock ก่อนงานยาวเพื่อ polling. Job lock ป้องกันการ confirm/cancel พร้อมกัน; status meta atomic rename. บังคับ schema baseline, ห้ามเปลี่ยน DB เพื่อเลี่ยงข้อผิดพลาด.
- `components/catalog_import_panel.php`, `assets/admin-import.js`, `assets/admin-import.css`: UI สองชุด scoped; progress upload จริง, ตรวจไฟล์แสดงจำนวนแถว (ไม่อ้างเปอร์เซ็นต์ก่อนทราบ total), commit แสดงจำนวน/เปอร์เซ็นต์; จำกัด 100 แถวต่อ response และดู detail แยก.

## ข้อจำกัด Hosting และการเก็บข้อมูลชั่วคราว

- ไฟล์ upload <=10 MiB; XLSX <=50 MiB uncompressed, <=2,000 entries, <=250,000 shared strings; <=50,000 nonblank data rows/job; stage <=100 MiB. เป็นเพดานป้องกันทรัพยากร ไม่รับรองว่า Hosting ทุกแห่งจะทำงานถึงเพดาน. XML shared strings ยังใช้ RAM; หาก memory/timeout ต่ำใช้ CSV UTF-8 หรือแบ่งไฟล์ (merge เท่านั้น; replace ต้องครบชุด). Preview และ confirm เป็น HTTP request เดียวแต่ SQL แบ่ง batch; ไม่ใช่ background worker หรือ resumable import. ห้ามแยกไฟล์แล้วกด replace ทีละชุด เพราะข้อมูลส่วนอื่นจะถูกนำออก.
- ตรวจ PHP `pdo_mysql`, `mbstring`, `zip`, `XMLReader`, `SimpleXML`; `upload_max_filesize` >=10M, `post_max_size` สูงกว่า upload limit, `memory_limit`, `max_execution_time`, proxy timeout, max_allowed_packet. เริ่มทดสอบ 234/2,520 ก่อนเพิ่มขนาด. ตั้งค่าตาม hosting policy ไม่สั่ง bypass limit.
- `storage/tmp/tor_import_v2_<token>/` เป็นพื้นที่ private; ต้อง writable และ server ปฏิเสธ HTTP GET โดยตรง (ตรวจ .htaccess/hosting). ไม่เผยแพร่ JSONL/ไฟล์จริง; directory 0700/file 0600 เมื่อระบบรองรับ. Preview อายุ 2 ชั่วโมง, cancel ลบ job, job เก่าถูก sweep เมื่อเริ่ม import ใหม่; ห้ามลบ job ที่กำลังประมวลผล. ตรวจพื้นที่ disk และทำ cleanup แบบปลอดภัยตามนโยบาย server; ไม่มี cron ใหม่. ถ้าปิดหน้า งานที่กำลังทำอาจเสร็จหรือหยุด ให้ตรวจฐานก่อนเริ่มใหม่.
- SQL ใช้ MySQL/MariaDB InnoDB, utf8mb4_unicode_ci, WEIGHT_STRING, SHA2, native PDO prepared statements. ตรวจจริงกับ MySQL 8.4.3 ของ Dev และ engine Hosting เป้าหมาย; ไม่รองรับ engine ที่ไม่มี transaction หรือ schema/unique key ไม่ตรง. การล็อก target table range อาจบล็อกการแก้ catalog พร้อมกันชั่วคราว; ไม่ควร import ชุดใหญ่ในเวลาใช้งานหนาแน่น.

## ผลทดสอบที่มีหลักฐานในรอบส่งมอบ

- PHP lint ทุก PHP ที่แก้/เพิ่ม, Node syntax check: ผ่าน.
- `php tests/catalog_import_test.php` บน PHP 8.4 CLI: 52 assertions ผ่าน; CSV + fake PDO, ราคากลาง 234/2,520/5,000/10,000/50,000 แถว, ยา 234/10,000 แถวพร้อม spec multiline; duplicate/conflict/warning/error, zero, decimal, rollback จำลอง, replace, ID, isolation, stale preview. ตัวจำลองไม่ทดสอบ SQL syntax จริง, Unicode collation จริง, lock หรือ DB engine. ไม่ใช้ข้อมูลจริง.
- Chromium offline API mock: Preview, filter, multiline detail, cancel, error block, warning/replace acknowledgement, confirm success, mobile width ผ่าน. ไม่ได้ทดสอบ browser เชื่อม PHP/DB จริง.
- เครื่องนี้ไม่มี PDO MySQL, MySQL Server, ZipArchive, XMLReader และ SimpleXML; XLSX test ถูก skip. ไม่ทดสอบ Microsoft Word/PDF/V4 ในรอบนี้. ไม่อ้างเวลา mock เป็นเวลา MySQL/hosting.

## Gate Phase 5 — ทำบน Dev ที่สำรองแล้ว (ก่อน Push release/Production)

1. `php -m` ตรวจ extensions, `php tests/catalog_import_test.php` ตรวจ optional XLSX cases. ในฐานทดสอบแยกตรวจ schema/integrity SQL เดิม. เปิด admin ด้วย approved admin.
2. ดาวน์โหลด sample ยา, เพิ่ม 2–3 ยา spec หลายบรรทัด (รวมช่องว่าง), Import preview→cancel แล้วตรวจ count/values เท่าเดิม. ทำทั้ง XLSX และ CSV. ลองหัวคอลัมน์สลับ, ชื่อซ้ำต่างหน่วย, ราคา blank/negative/text, duplicate, formula; ต้องปิด confirm. ลอง warning และรับทราบ. ตรวจ multiline/เลขข้อใน detail และหลัง save.
3. Import merge บนฐานทดสอบ; ตรวจว่า ID เดิมคงที่และตารางอีกประเภทไม่เปลี่ยน. Preview แล้วแก้ catalog ในอีก tab; confirm ต้อง reject stale preview. ลอง replace ด้วย sample subset เฉพาะฐานทดสอบว่าง/สำเนา; ตรวจจำนวน remove และคง ID ที่ match. จำลอง error บนฐานทดสอบเท่านั้น (เช่น constraint/permission ที่ทำให้ write ล้มเหลว) ตรวจค่าก่อนหลังว่า rollback ทั้งชุด.
4. ทดสอบข้อมูล 234/2,520 แล้ว 5,000/10,000 ตามทรัพยากรจริง; 50,000 เป็น stress target ไม่ใช่ข้อรับรอง. บันทึกเวลาจริงและ RAM/timeout; ทดสอบแยกทั้งสองประเภท. หากล้มเหลวใช้ merge batch file หรือวางแผน durable worker แยกโดยได้รับอนุมัติ.
5. เปิดเอกสารทั้ง 6 ขั้น: TOR ข้อ 4 รักษาคุณลักษณะทุกข้อ, ข้อ 7 ทั้ง reference/offer, ตรวจเอกสารเดิมไม่เปลี่ยน, preview/print/Word/PDF/Word รวม V4 ใน Word Desktop. ตรวจ CRUD/search เดิมและ navbar. ตรวจหน้าจอ 15–16 นิ้ว/29 นิ้ว.
6. เมื่อผ่านแล้วอัปเดตผลจริงใน PROJECT_STATUS และ CHANGELOG, commit `v1.2.0 - safe catalog import preview and transactional apply`. ก่อน Hosting ต้องสำรอง private, ตรวจ schema/permissions, ทดสอบแยกกับ engine จริง. ไม่ใช้ full dump ทับฐานเดิม.

## UI follow-up: Popup และ Excel Template แบบมีคู่มือ
ปุ่มเพิ่ม/แก้ไขและ Import อยู่เหนือตาราง. ใช้ native dialog และ scroll เฉพาะ body; ปิดไม่ได้ขณะ request กำลังทำงาน. ถ้า Preview พร้อม การปิดต้องยืนยัน cancel staging; หากสถานะ commit ไม่แน่นอนต้องตรวจฐานจริงก่อนเริ่มใหม่. การแก้ไขจากแถวค้นหาใช้ค่าแถวนั้น (ยังใช้ save POST/CSRF เดิม) รักษาทศนิยมราคาเดิม 4 ตำแหน่ง. `templates/import/` มี XLSX 2 ไฟล์ ชีตแรก Import ว่างมี header, ชีตตัวอย่างแยกและคู่มือ; กรอกจริงแถว 2 เป็นต้นไป ห้ามใช้ราคาสมมุติ. endpoint sample เดิมดาวน์โหลดไฟล์โดยตรง. Reader รองรับ namespace prefix ของ SpreadsheetML. เพิ่ม optional XLSX test สำหรับ template จริงและ prefixed worksheet; บนเครื่องนี้ยัง skip เพราะไม่มี PHP extensions. ก่อน Production ทดสอบดาวน์โหลด/กรอก/preview/cancel และ confirm ทั้งสองไฟล์บน Laragon จริง.

## ล้างคลังราคากลางประจำปี (v1.2.0 candidate)
ปุ่มเฉพาะหมวดราคากลางเหนือรายการ เปิด Popup แยกจาก Import. `GET admin.php?action=price_clear_preview` เป็น read-only และต้องผ่าน Admin auth; ส่ง count และ hash ปัจจุบัน. POST `clear_price_refs` ใช้ CSRF เดิม, รับทราบ backup และพิมพ์ `ล้างราคากลาง`, ตรวจ count/hash ซ้ำภายใต้ REPEATABLE READ + primary range lock. ปฏิเสธหากพบ inbound FK หรือ schema ไม่ตรง baseline. DELETE เฉพาะ ID ใน `medicine_price_references` แบ่งชุด 200 ภายใน transaction เดียว; rollback เมื่อผิดพลาดก่อน commit. ไม่ TRUNCATE, ไม่ reset AUTO_INCREMENT, ไม่ล้างตาราง medicines หรือประวัติ. ไม่ได้ทำ backup อัตโนมัติ; ผู้ใช้สำรองเอง. หากเปิด Import preview เดิมก่อนล้างแล้ว confirm จะถูกปฏิเสธด้วย stale fingerprint เมื่อข้อมูลเปลี่ยน. ราคาที่บันทึกในเอกสารเดิมไม่ถูกเขียนทับโดย action นี้; การค้นหาราคาอ้างอิงใหม่จะไม่มีข้อมูลจนกว่าจะนำเข้าใหม่. แนะนำ replace ผ่าน Preview หากต้องการอัปเดตโดยไม่ให้คลังว่าง. ทดสอบ fake PDO 65 assertions (รวม 13 เงื่อนไข clear, rollback ชุดที่สอง, stale snapshot, isolation, inbound FK); ไม่ใช่ MySQL จริง. UAT: สำรองฐานทดสอบก่อน, เปิด popup และยกเลิก, ลองคำยืนยันผิด, เปลี่ยนราคาในอีก tab แล้ว confirm, ล้างจริงบนสำเนาและตรวจ 0 ราคา/จำนวนยาเดิม/เอกสารเดิม, นำเข้าใหม่. ห้ามทำ destructive UAT บน Production.

## Hotfix: Popup POST 404 (25 พ.ย. 2569)

ฟอร์ม manual add/edit และ annual clear มี hidden field `name="action"` ซึ่งบัง property `form.action` ของ HTMLFormElement; ห้ามอ่านหรือเขียน URL ผ่าน property นี้. ใช้ `form.getAttribute('action')` / `form.setAttribute('action', url)` โดยรักษา POST action/CSRF, query ค้นหาทั้งสองหมวด และไม่ส่ง legacy edit IDs. `admin.php` โหลด `admin-modal.js?v=1204`. ทดสอบ Chromium offline โดยดัก submit event และ FormData ก่อน network: ทำซ้ำ URL ผิดในรุ่นเดิมและตรวจ URL `/admin.php` ที่แก้แล้วครบ 5 กรณี. ไม่ใช่การส่ง HTTP ไป Laragon หรือทดสอบ MySQL จริง; หลังวางทับให้เปิด admin.php ใหม่และกด Ctrl+F5 ตรวจจำนวนราคากลางก่อนยืนยันล้างซ้ำ.

## Hotfix: ราคากลาง Excel จริง (25 พ.ย. 2569)

ไฟล์ผู้ใช้มี 2,520 แถวข้อมูล: 272 แถวมีค่าทศนิยมเกิน 4 ตำแหน่ง, 170 แถวมี LF ในคอลัมน์ข้อความ, ซ้ำกัน 20 แถว รวม 422 แถวที่ตัวตรวจเดิมปฏิเสธ. `core/price_reference_import.php` อนุญาต LF โดยรักษาคีย์เดิม และปัดเศษราคาแบบ decimal string half-up ให้ตรง DECIMAL(15,4) พร้อม warning ระบุค่าต้นฉบับ/ค่าใหม่; ต้องรับทราบก่อนยืนยัน ไม่แก้ไฟล์ Excel ต้นฉบับ. ราคาว่าง/ลบ/ผิดรูปแบบ/เกินขนาดยังถูกปฏิเสธ. ทดสอบ PHP lint, ชุด fake PDO 84 assertions; อ่าน XML ของ XLSX จริงแยกเป็นข้อมูล CSV เพื่อทดสอบ validation/preview/apply จำลอง: 2,520 แถว, 2,248 new, 272 warning, 0 blocked บนฐานจำลองว่าง. ยังไม่ได้ทดสอบ PHP XLSX extensions หรือ MySQL จริง; ไม่รับรอง collation ของ MySQL จาก fake PDO. ไม่มี migration หรือแก้ UI/Word/TOR.

## Hotfix: XLSX header diagnostic และตัวอ่าน XML โดยตรง (v1.2.0 Dev)
กรณีผู้ใช้พบ HTTP 400 หัวคอลัมน์ไม่ตรงประเภท แม้ตรวจ XML ของ workbook แล้วตรงครบสี่ช่อง: เปลี่ยน `core/spreadsheet_import.php` ไปอ่าน XMLReader โดยตรง (getAttribute, depth, localName, namespaceURI) ไม่พึ่ง SimpleXML namespace views. รองรับ shared strings, inline strings, rich text, ช่องว่าง, เลขแถวจริง และค่า numeric ดิบ; ไม่ลดข้อกำหนดตรวจ header หรือข้าม error. `core/catalog_import.php` แจ้งเลขแถวและหัวคอลัมน์ที่อ่านได้เพื่อวิเคราะห์กรณีเกิดซ้ำ. Warning CDN Tailwind และ favicon 404 ไม่เกี่ยวกับการตรวจ header.
ผลทดสอบ: PHP lint, 84 assertions เดิม + 4 assertions จาก CSV จริง 2,520 แถว (2,248 new, 272 warning, 0 blocked) ด้วย fake PDO. ทดสอบ reader กับ XML ต้นฉบับผ่าน test adapter ที่ใช้ libxml2 และ PharData ได้ parity ทุกแถวกับ CSV รวม header 2,521 และ template ทั้งสองผ่าน. Native PHP ZipArchive/XMLReader ไม่มีในเครื่องทดสอบ จึงยังต้องทดสอบบน Laragon; MySQL ยังไม่ได้ทดสอบ. เพิ่ม optional regression สำหรับ shared-string header และค่า numeric; สามารถรัน `php tests/catalog_import_test.php` หรือกำหนด environment `TOR_IMPORT_TEST_PRICE_FILE` เป็น path ไฟล์จริงนอก repository ก่อนรัน. ผล fixture จำลองฐานว่าง ไม่รับรอง collation/locking จริง. CSV UTF-8 ที่แปลงจากไฟล์ต้นฉบับเป็นทางเลี่ยง ไม่ commit ข้อมูลจริงเข้า Git. ไม่มี migration และไม่แก้ UI/เอกสาร TOR/Word Merge V4.


## ส่งออกและล้างคลังรายการยา (v1.2.0 Dev)
`admin.php?action=export_medicines` ส่งออกจาก `medicines` ทุกแถวโดยเรียงชื่อ/ID ไม่ใช้ filter/pagination; `core/medicine_catalog_tools.php` เขียน XML ทีละแถวลงไฟล์ชั่วคราวแล้วบีบอัด XLSX ด้วย ZipArchive. ชีตแรกชื่อ Import มีหัว `ชื่อยา | หน่วย | คุณสมบัติยา` ตรงกับ importer, เซลล์ข้อความ wrap และรักษา LF, ไม่สร้างสูตร. ขนาดไฟล์ส่งออกอาจเกินข้อจำกัด import 10 MB / 50,000 แถว ต้องแบ่งไฟล์ในกรณีนั้น; เซลล์เกินขีดจำกัด Excel หรือข้อความ XML/escape พิเศษจะส่งออกไม่สำเร็จแทนการตัดข้อมูล. XLSX นี้ไม่ใช่ full SQL backup และไม่เก็บ ID.
`medicine_clear_preview` ตรวจจำนวน/hash และการมีชุดจัดซื้อหรือเอกสารใน procurement_cases, procurement_documents, tor_histories, scope_price_approval_memos. `clear_medicines` เป็น POST ผ่าน requireAdmin/CSRF เดิม; ตรวจคำว่า ล้างรายการยา, backup acknowledgement, FK ขาเข้า, InnoDB, schema/unique key, snapshot และเอกสารซ้ำใน transaction; ลบเฉพาะ medicines เป็น batch 200, ไม่รีเซ็ต ID. เงื่อนไขตั้งใจ conservative: บล็อกถ้ามีข้อมูลในตารางใดตารางหนึ่ง แม้ชุดนั้นอาจไม่ได้ใช้ยาทุกตัว เพราะ TOR เดิมยังอ้างอิง ID จาก live catalog. ห้ามลบเอกสารเพื่อหลบ guard; ใช้เพิ่ม/อัปเดตโดยรักษา ID เดิม หรือวางแผน archival snapshot แยกต่างหาก. ทำการล้างเฉพาะฐานใหม่/ช่วง maintenance ที่ปิดฟอร์มและไม่มีผู้ใช้อื่นสร้างเอกสารค้างอยู่. ไม่กระทบราคากลาง; ไม่รับรองการรักษาเอกสารเก่าหากฝืนล้างจาก SQL โดยตรง.
`assets/admin-modal.js?v=1205` แยก controller และสถานะยืนยันตามหมวด โดยรักษาการเข้าถึง form action ผ่าน attribute แก้ 404 เดิม. ผลทดสอบ: fake PDO/CSV 116 assertions รวม fixture ราคากลาง, XML package 234 รายการจำลองผ่าน adapter PharData, Chromium offline/mock API ตรวจ block/คำยืนยัน/POST target ทั้งสองหมวด. Native XLSX/MySQL/Excel/Word จริงยังรอ UAT.

## v1.3.0 candidate — ปรับ guard ล้างยา (แทน blanket guard รุ่นก่อน)
ส่งออก Excel/Import ทั้งสองระบบ/ล้างราคากลางคงเดิม. ล้าง `medicines` ใช้ snapshot readiness แทนการห้ามเพียงเพราะมีเอกสาร: Admin ตรวจผ่าน, recoverable ต้อง backfill ที่ยืนยันแล้ว, issue ต้องแก้ด้วยหลักฐาน, deferred ที่ TOR ครบแสดงหมายเหตุ. ใช้ hash catalog + docs, recheck ภายใต้ lock, backup ack/phrase, DELETE แบบ transaction ไม่ reset ID, ไม่ลบเอกสาร. หาก snapshot ไม่ครบห้าม bypass. รายละเอียด [DOCUMENT_SNAPSHOT.md](DOCUMENT_SNAPSHOT.md). ชุดทดสอบ Import 117 assertions ในเครื่องนี้ (XLSX skipped); ไม่รับรอง DB จริง.
