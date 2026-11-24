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
