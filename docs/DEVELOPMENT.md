# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### Maintenance gate หลัง v1.3.0
การพัฒนาหลักปิดตามผลผู้ใช้แล้ว; ไม่ rerun ทุก phase หรือ deploy ซ้ำเพียงเพราะข้อความ Candidate ด้านล่าง. เมื่อมีงานใหม่ ตรวจ HEAD/dirty tree และ source เฉพาะที่เกี่ยวข้อง, วางแผนก่อนแก้, ทดสอบ regression ตาม scope บน Dev/clone, สำรองก่อน deploy, อัปโหลดเฉพาะ runtime ที่เปลี่ยนและคง production config/vendor ที่ตรง lock. ไม่ทดสอบ delete/clear บนฐานจริง. เอกสาร-only รอบนี้ไม่ต้อง lint PHP หรือรัน MySQL/Word ซ้ำ; ตรวจ SHA/เนื้อหา MD และ staged files เท่านั้น.

สำหรับ Windows ที่ `composer` ไม่อยู่ PATH ให้ตรวจ Laragon Terminal หรือใช้ vendor ที่ตรง `composer.lock` และ PHP/extensions ของ Hosting; ไม่สั่ง composer update เพื่อแก้ปัญหา PATH. ใช้ `git pull --ff-only` เมื่อมี web commit และ working tree ปลอดภัย; ห้าม force/reset/clean. ก่อน Push ให้ตรวจ `git diff --check`, `git diff --cached --stat`, `git ls-files -- storage/tmp/` และ `git ls-files -- '*.sql'`. หลัง Push ตรวจ remote SHA จริง. การอัปเดต docs ไม่ต้องอัปโหลดไป Hosting.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Development / testing / release

## สภาพแวดล้อม

Windows + Laragon, PHP 8.1+, MySQL/MariaDB, PDO, Composer; path และ env ดู README. `composer.lock` ใช้ pin dependency; ไม่สั่ง composer update ถ้าไม่ได้แก้ dependency. ไม่มี CI/test suite อัตโนมัติที่พบใน tree baseline. ใช้ PHP CLI, browser, DB sandbox และ Microsoft Word Desktop ตามงาน. Tailwind CDN บางหน้า ต้องมี network หรือ asset fallback ที่ตรวจจริง. Browser merge ใช้ browser API ZIP/XML/Blob; ไม่สมมติว่าทุก browser รองรับ.

## Gate ก่อนแก้

```powershell
cd C:\laragon\www\torskp.page.gd
git status --short
git branch --show-current
git log -5 --oneline
git fetch origin
git rev-parse HEAD
git rev-parse origin/main
git diff --stat
```

ตรวจงานค้างของผู้ใช้ ห้าม reset --hard/clean -fd เพื่อให้ clean. ใช้ `git log -- <file>` และ `git diff` เฉพาะงาน. จด baseline SHA/รุ่น, เงื่อนไข DB, files ที่จะเปลี่ยนและ regression ที่ต้องรักษา. หาก branch/ฐานไม่ตรง ให้หยุดการแก้ที่เสี่ยงและรายงาน. การอ่าน docs/โค้ดก่อนแก้ไม่ใช่หลักฐานว่า test ผ่าน.

## ทดสอบขั้นต่ำตาม scope

| งาน | ตรวจที่จำเป็น |
|---|---|
| PHP | `php -l path/to/changed.php` ทุกไฟล์ PHP ที่แก้; `git diff --check` |
| JS | `node --check path/to/changed.js` หากมี Node; ทดสอบ browser จริงสำหรับ inline JS/DOM/fetch |
| DB | read-only schema gate; migration บน copy; ตรวจเก่า/ใหม่, data preservation, constraint/role |
| TOR | reference vs offer ต่างกัน, default budget 6m, แก้จำนวนเงิน/Thai baht, optional fields blank/restore, package validation, table หลายหน้า, save/reload/case switch, downstream และ Word merge |
| Word/PDF | XML + Microsoft Word Desktop เปรียบเทียบรายหัวข้อ/รวม; browser print ไม่แก้ screen CSS ให้กระทบ output |
| UI | 390/1366/2560px, scroll, form actions, filter/search, ไม่ทดสอบ delete กับฐานจริง |
| Auth | pending ไม่เข้า, approved เข้า, disabled/rejected session ถูกปิด, must_change_password, CSRF, admin/editor boundary |

ไม่ต้องรันชุดทดสอบเดิมทั้งหมดซ้ำหลายรอบหากไม่เกี่ยวข้อง แต่ต้องตรวจ contract ข้ามหน้าที่ได้รับผลกระทบ. ถ้าไม่มี MS Word/ฐานจริงให้รายงานว่าไม่ได้ทดสอบ ไม่ใช้ XML ผ่านแทน visual PASS.

## Deliverable และ deploy

ZIP เฉพาะไฟล์พร้อมวางทับตามโครงสร้างเดิม + README สั้น; ไม่มี installer/patch script, ไม่มี vendor/DB dump/รูปเอกสารจริง. SQL migration แยกเมื่อจำเป็นและอธิบายลำดับ. สำหรับ docs-only ไม่ต้อง migrate/ติดตั้ง dependency. ผู้ใช้ตรวจ Laragon ก่อน Push; ใช้ commit message สื่อความหมายและระบุรุ่นเมื่อเป็น release. ปรับ AGENTS/PROJECT_STATUS/CHANGELOG และคู่มือที่ได้รับผลกระทบหลัง release. version ของเว็บ v1.1.0 ต่างจาก Word Merge V4.

```powershell
git status --short
git diff --check
# ตรวจรายชื่อไฟล์ก่อน git add
git add README.md AGENTS.md CLAUDE.md CHANGELOG.md docs/
git diff --cached --stat
git commit -m "docs: add v1.1.0 architecture and AI development handoff"
git push origin main
```

ตัวอย่างนี้สำหรับเอกสารเท่านั้น ไม่สร้าง tag/version ใหม่โดยอัตโนมัติ. ห้ามใช้ git add . โดยไม่ตรวจไฟล์. ไม่ push เองหากผู้ใช้ไม่ได้สั่ง. ตรวจ remote SHA หลัง push. อย่าลบเอกสารประวัติเดิม; หากข้อมูลเก่าให้ติดป้าย superseded และอ้างไฟล์ canonical.

## Database baseline และ staged safety

ใช้ `database/schema.sql` เฉพาะฐานใหม่ว่าง; read-only checks ก่อนแก้ฐานเดิม. ห้ามรัน migration เดิมทั้งหมดหลัง import schema. ตรวจ `git ls-files -- "*.sql"` และ staged file list; commit เฉพาะ schema/migrations/checks ที่ไม่มีข้อมูลจริง. ดู [DATABASE_BACKUP.md](DATABASE_BACKUP.md).

## Import v1.2.0 candidate QA

`php tests/catalog_import_test.php` ใช้ fake PDO และไฟล์สมมุติใน system temp เท่านั้น ไม่แตะ DB จริง; หากเปิด extensions XLSX จะเพิ่มการตรวจ sparse cell/shared strings/formula. ต้องทำ manual UAT ตาม [IMPORT_SYSTEM.md](IMPORT_SYSTEM.md) กับ MySQL Dev แยก/สำรองก่อน, ตรวจ PHP limits และ storage access. ไม่อัปโหลด tests/ ไป web root จริง. ไม่ต้อง migrate ฐาน v1.1.0 ที่ตรง schema. ห้ามอ้าง performance mock เป็น performance hosting.

## v1.3.0 candidate — Snapshot UAT
รัน `php tests/document_snapshot_test.php` และ `php tests/catalog_import_test.php`, lint ไฟล์ PHP/JS ที่แก้; ทดสอบบน MySQL clone จาก backup ไม่ใช่ฐานหลัก. ตรวจ review/read-only, backfill/rollback/stale, เปลี่ยน/ล้าง catalog แล้ว reopen/export 6 ขั้น, Word รวม V4, ตรวจราคา/pack/ข้อความหลายบรรทัดและ import เดิม. อย่าตีความ fake PDO/HTML comparison ว่า MySQL หรือ Word ผ่าน. ไม่มี migration. เงื่อนไขปิด release ใน [DOCUMENT_SNAPSHOT.md](DOCUMENT_SNAPSHOT.md).
