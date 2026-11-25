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
