# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](docs/PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### v1.3.0 — ปิดเวอร์ชัน / Production (25 พ.ย. 2569)
- v1.2.0 commit `fced437f022f07924ca163141b02a14c4f176b5c` รวม Import/preview/Popup/Excel hotfix/export/safe clear.
- v1.3.0 commit `cb7183f4dfe08a0ff93f909ad017a7eee2c0f0bf` รวม Document Snapshot, audit/backfill และ clear gate ใหม่; ผู้ใช้แจ้ง Dev ใช้งานได้เวลา 12:05 ICT.
- `.gitignore` commit `a892806e68e2e0aca2eb26fb2f25bac631e081f5`; ลบ staging 3 ไฟล์จาก HEAD ผ่านเว็บใน commit `f30c93cfa6be5030aa68ea76c26d941b07dfb52e`; เก็บไฟล์ป้องกัน storage ไว้. ไม่ล้างประวัติ Git.
- เวลา 12:56 ICT ผู้ใช้ยืนยันอัปโหลด InfinityFree และใช้งานจริงได้หมดแล้ว. ปิด implementation ตามรายงานผู้ใช้เป็น stable maintenance baseline; ไม่มีผลทดสอบ production รายกรณีหรือ independent certification เพิ่ม. ผลทดสอบเดิม 39 Snapshot / 117 Import assertions และ offline comparison คงตามข้อจำกัดเดิม.
- รอบนี้ documentation-only: ไม่มี PHP/JS/CSS/SQL/template binary change, ไม่มี migration, ไม่มี deploy/DB action/commit/tag โดยผู้ช่วย. งานดูแลคงเหลือดู [PROJECT_STATUS](docs/PROJECT_STATUS.md).

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Changelog — TOR SKP

หมายเลขรุ่นยึดจาก Git commit messages ณ 24 พ.ย. 2569; รุ่นย่อย Word Merge แยกจาก version เว็บไซต์. ประวัติ commit ก่อน v1.1.0 บางรายการมีเพียงเลขรุ่น จึงไม่สมมติรายละเอียดเพิ่ม

| วันที่ ICT | Commit | ข้อความรุ่น / สาระ |
|---|---|---|
| 2026-11-24 | `986c48c` | Delete torskp.page.gd.zip; ลบ archive จาก tree (ไม่ลบประวัติเดิม) |
| 2026-11-24 | `2d85b76` | V.1.1.0 — workspace, workflow, supplier/filename, Word merge, TOR |
| 2026-11-24 | `ccea0de` | V.1.0.5 |
| 2026-11-24 | `c1622c1` | V.1.0.4 |
| 2026-11-24 | `a0e15a1` | V.1.0.4 (อีก commit หมายเลขเดียวกัน) |
| 2026-11-24 | `31b2d2a` | v1.0.3 |
| 2026-11-23 | `1aa141e` | V.1.0.2 — Word template เชิญกรรมการ, รูปแบบราชการ/ตราครุฑ, PHPWord, รายงานผล, responsive, gitignore, login |
| 2026-11-23 | `7e3516b` | V.1.0.1 — เชิญกรรมการและรายงานประชุม เพิ่ม บก.06/ตารางแนบท้าย |
| 2026-11-17 | `7ae870d` | V.1.0.0 |
| 2026-11-17 | `970bf3a` | Initial commit |

## v1.1.0 — สรุปความสามารถปัจจุบัน

- สมัครสมาชิก pending/admin approval, UI กระชับ, workflow 6 ขั้น; ฟังก์ชัน auth บางส่วนมีอยู่ก่อน commit v1.1.0 แล้ว (ไม่อ้างว่าทั้งหมดเพิ่งเพิ่มใน commit นี้)
- บริษัทผูกกับ case, ชื่อไฟล์อิง step/date/number/supplier; history เปิดรายหัวข้อและรวม Word ราย case
- Word Merge V1 (DOCX ซ้อน) → V2 (section/style remap) → V3 (Thai alignment) → V4 (table paragraph zero); ผู้ใช้รับรองผล V4 ในชุดที่ทดลอง
- TOR ข้อความตามที่เลือกเท่านั้น, งบ 6 ล้านแก้ได้, optional revision/year, price_basis reference/offer, ฟอร์มเรียงใหม่; ไม่ย้ายตารางไปแนบท้าย; ไม่เปลี่ยนข้อ 11
- ใน release commit เดิม schema.sql และ migration supplier ยังไม่ตรงกับโค้ด; มีการจัดทำ baseline DB ต่อหลัง docs handoff (ดูรายการด้านล่าง)

## Unreleased — documentation handoff

เพิ่ม README/AGENTS/CLAUDE/CHANGELOG และคู่มือใน docs/. ไม่เปลี่ยน version เว็บไซต์ ไม่แก้โค้ด/DB. ยืนยัน baseline กับ GitHub ก่อนสร้างเอกสารนี้; รายละเอียดหลักฐานและข้อจำกัดใน PROJECT_STATUS. เมื่อ commit docs ให้ใช้ docs-only commit.

## Unreleased — database baseline / backup hygiene

- อ้างอิง Dev export 24 พ.ย. 2569; structure-only 12 ตาราง, 100 คอลัมน์, migration supplier และ read-only checks. ไม่มีข้อมูลจริง/DDL บนฐานเดิม; ไม่เปลี่ยน PHP/UI/Word.
- เพิ่มกฎ ignore SQL dumps/archive และคู่มือ backup/untrack; ผู้ใช้ต้องสำรองและ untrack เอง. Git history cleanup ยังไม่ดำเนินการ.
- Docs handoff ก่อนงานนี้อยู่ commit `6c6ed8e0`; งาน DB นี้ยังรอ commit และทดสอบ import DB จริง. เสนอ commit `chore(db): sync v1.1.0 schema and protect private backups`.

## v1.2.0 — Import & Data Quality (candidate; ยังไม่ Push/รับรอง Production)

- Baseline Git `c923372cf8b10eab12be5152b3d7446036ae807e` (v1.1.0 หลัง DB baseline, 25 พ.ย. 2569).
- แยก reader, medicine/price validation, staging/confirm ออกจาก admin.php; รองรับ CSV UTF-8 multiline และ XLSX sheet1 แบบ streaming, strict header/price/length. Preview แยกสถานะ, ค้นหา, 25/50/100 ต่อหน้า, เปรียบเทียบข้อมูลก่อน–หลัง, CSV รายงานข้อผิดพลาด, cancel/confirm.
- ยืนยันก่อนบันทึก, ตรวจ snapshot, batch SQL ภายใต้ InnoDB transaction; replace ราคาเท่านั้น ลบเฉพาะรายการที่ไม่มีในไฟล์ใหม่ รักษา ID รายการที่ตรงกัน. ไม่มี migration/เปลี่ยน schema หรือแก้ TOR/Word V4.
- ตรวจ PHP lint, JS syntax, CSV + fake PDO 52 assertions (ราคา 234–50,000 แถว, ยา 234/10,000 แถว), Chromium offline UI mock ผ่าน. ยังไม่ได้รันจริง MySQL/MariaDB, XLSX extensions, Laragon/Word/Hosting; ดู UAT ใน docs/IMPORT_SYSTEM.md. ปิด Production gate จนผู้ใช้ทดสอบ.
- Candidate UI follow-up: ย้ายปุ่มเพิ่ม/แก้ไข/Import ทั้งสองประเภทไป Popup; toolbar เหนือตาราง, scroll ภายใน, ป้องกันปิดขณะ busy, cancel staging ก่อนปิด. เพิ่ม Excel Template แยกข้อมูลจริง/ตัวอย่าง/คู่มือ, ปรับ reader ให้รองรับ namespace prefix ของ SpreadsheetML. ไม่มี migration หรือเปลี่ยน logic transaction. ยังรอ XLSX/MySQL UAT.

- Candidate annual price reset: เพิ่มปุ่มล้างราคากลางทั้งหมดพร้อม Popup ยืนยันจำนวนจริง, backup acknowledgement และคำยืนยัน; ตรวจ snapshot ภายใต้ transaction, rollback เมื่อผิดพลาด, ไม่ reset ID และไม่แก้ตารางรายการยา. โหมด replace Import เดิมยังอยู่. ทดสอบ fake PDO 65 assertions; รอ UAT MySQL/XLSX จริง.

- Candidate hotfix (25 พ.ย. 2569): แก้ Popup POST 404 `[object HTMLInputElement]` จาก hidden input `name="action"` บัง `HTMLFormElement.action`; อ่าน/เขียน HTML attribute ผ่าน getAttribute/setAttribute และเพิ่ม cache version admin-modal.js เป็น 1204. ทดสอบ submit event ใน Chromium แบบ offline (จับ URL/FormData ก่อน network) ครบ 5 กรณี clear/add/edit ของทั้งสองหมวด; fake PDO 65 assertions ผ่าน. ไม่แก้ DB/Import transaction. รอ UAT Laragon จริง.

- Candidate hotfix (25 พ.ย. 2569): รองรับราคากลางจาก Excel ที่มี LF ในชื่อ/ความแรง/หน่วยโดยรักษาคีย์เดิม และทศนิยมเกิน 4 ตำแหน่งโดยปัดเศษแบบ decimal string พร้อม warning ก่อนยืนยัน. ชุดข้อมูลจริง 2,520 แถว ผ่าน validation จาก XML ที่สกัดเป็น CSV และ fake PDO (272 warning, 0 blocked); regression 84 assertions. รอ UAT XLSX/MySQL บน Laragon. ไม่แก้ schema/ข้อมูลเดิม/ไฟล์ Excel ต้นฉบับ.

### v1.2.0 — XLSX reader / header diagnostic hotfix (Dev)
- เปลี่ยนตัวอ่าน XLSX ให้ใช้ XMLReader อ่านแถว เซลล์ แอตทริบิวต์ และ shared/inline rich text โดยตรง รองรับ default/prefixed namespace โดยไม่ต้องใช้ SimpleXML. เพิ่มข้อความแจ้งหัวคอลัมน์ที่อ่านได้จริงและเลขแถวเมื่อไม่ตรงประเภท. คงการตรวจชนิดไฟล์ สูตร ขนาด และการ Preview/Confirm/Rollback เดิม.
- ไฟล์ราคากลางผู้ใช้ 2,520 แถว: CSV ตรงต้นฉบับและผ่าน fake PDO 88 assertions รวม fixture จริง; ทดสอบตัวอ่านใหม่กับ XML จริงผ่าน adapter libxml2/PharData ได้ข้อมูลตรง CSV ทั้ง 2,521 แถวรวม header และ template ทั้งสองผ่าน. ยังไม่ใช่การทดสอบ native PHP XMLReader/ZipArchive หรือ MySQL บน Laragon. ไม่มี migration. ไฟล์ราคาจริง CSV เป็นไฟล์แยกสำหรับผู้ใช้ ไม่บรรจุใน ZIP หรือ Git.


### v1.2.0 — ส่งออกและล้างคลังรายการยา (Dev)
- เพิ่มปุ่มส่งออก Excel ทั้งคลัง `medicines` ไม่จำกัดผลค้นหา/การแบ่งหน้า: ชีต Import, 3 คอลัมน์, ข้อความหลายบรรทัด, freeze header, wrap text, filter, text cell ไม่ใช่สูตร; ไม่มีข้อมูลราคากลาง.
- เพิ่ม Popup ล้างรายการยาแยกจากราคากลาง: snapshot, จำนวนจริง, รับทราบสำรองข้อมูล, คำยืนยัน `ล้างรายการยา`, ตรวจซ้ำฝั่ง Server, DELETE แบบ batch/transaction ไม่ TRUNCATE. ปิดการล้างถ้ามีชุดจัดซื้อหรือเอกสารใดในสี่ตารางที่เกี่ยวข้อง เพราะหน้า TOR ยังต้องใช้ ID จากคลังเดิม. ใช้ import merge แทนเมื่อมีเอกสารเก่า.
- ทดสอบ fake PDO/CSV 116 assertions (รวม fixture ราคากลางเดิม 2,520 แถว), ตรวจแพ็กเกจ XLSX 234 แถวจำลองผ่าน PharData adapter และ XML, Chromium offline/mock API ผ่านทั้งสอง Popup. ยังไม่ทดสอบ native ZipArchive, MySQL หรือ Microsoft Excel บน Dev จริง. ไม่แก้ schema, Word Merge, เอกสารทั้งหกขั้นตอน หรือค่าข้อมูลจริง.

## v1.3.0 — Document Snapshot & Catalog Independence (candidate, 25 พ.ย. 2569)

- ฐาน v1.2.0 `fced437f022f07924ca163141b02a14c4f176b5c`. แก้ principle save/open, TOR report/Word, history-bundle hydrate ให้ใช้ข้อมูลที่บันทึกด้วย row token ที่มี ownership/fingerprint; ไม่ remap archive เป็น live ID. คง merge engine V4, สูตรและ layout.
- เพิ่ม `core/document_snapshot.php`, `catalog_snapshot_review.php`: read-only audit + explicit transactional backfill จาก TOR เดิมที่ match อย่างชัดเจนเท่านั้น; ไม่เขียนทับประวัติ TOR. ใช้ JSON ที่มีอยู่, ไม่มี migration. ตรวจเอกสาร+catalog fingerprint ก่อน clear; incomplete/ recoverable บล็อก; completed TOR ที่มีรายการต่างจาก principle แสดง deferred ไม่เดา spec.
- Offline prior Dev dump 17/17 histories, 59 rows, 58 recoverable/0 issues/1 deferred. PHP Snapshot 39 assertions, Import 117 assertions (XLSX skipped); TOR 34 HTML comparisons บน catalog เปลี่ยน/ว่างผ่าน. ไม่รับรอง MySQL/Word/Hosting จริง. อ่าน docs/DOCUMENT_SNAPSHOT.md และทดสอบ clone ก่อนล้างจริง. ยังไม่ได้ push.
