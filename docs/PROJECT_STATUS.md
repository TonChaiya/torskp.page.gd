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
