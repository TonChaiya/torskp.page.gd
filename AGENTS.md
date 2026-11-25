# AGENTS.md — TOR SKP

คู่มือสำหรับ AI และผู้พัฒนาทุกเจ้า | baseline: v1.1.0, `986c48cdcfe76116742a3a8cccffac2c0e75eef6` (24 พฤศจิกายน 2569)

## อ่านก่อนทำงาน

1. ตรวจ `git status --short`, `git log -1 --oneline`, `git branch --show-current` และ SHA ของ origin/main (ถ้าเข้าถึงได้) ก่อนเชื่อหมายเลขรุ่นในเอกสาร
2. อ่านไฟล์นี้ → `docs/PROJECT_STATUS.md` → `docs/WORKFLOW.md` → `docs/ARCHITECTURE.md` แล้วอ่านคู่มือเฉพาะงาน (`DATABASE`, `WORD_EXPORT`, `UI_GUIDELINES`, `DEVELOPMENT`, `SECURITY`) และไฟล์โค้ดที่เกี่ยวข้องจริง
3. ตรวจประวัติ `git log --oneline --all`, `git log -- <path>` และ diff ที่เกี่ยวข้องก่อนแก้ ห้ามอาศัยชื่อไฟล์หรือความจำจากแชทเป็นหลักฐานว่าฟังก์ชันทำงานอย่างไร
4. สรุป baseline, ขอบเขต, dependency, ผลกระทบ DB และแผนทดสอบก่อนเขียนโค้ด ถ้าผู้ใช้ขอวิเคราะห์อย่างเดียว ห้ามแก้ไฟล์

## กฎที่ห้ามละเมิด

- ใช้ PHP 8.1+ แบบหน้า PHP เดิม + PDO MySQL/MariaDB + JavaScript/CSS; ไม่ย้าย framework, ไม่เพิ่มบริการภายนอก/ตัวติดตั้ง หรือ refactor ข้ามหลายหน้าโดยไม่ได้รับอนุญาต
- `case_id` คือหน่วยของชุดจัดซื้อ (ไม่ใช่ supplier อย่างเดียว); บริษัทเดียวมีหลายชุดได้ ห้ามรวมข้อมูลข้าม case หรือข้ามบริษัท; รักษา document_type และ `history_id` เดิม
- ลำดับหน้าผู้ใช้ 1 หลักการ → 2 เชิญกรรมการ → 3 TOR → 4 อนุมัติคุณลักษณะ/ราคากลาง → 5 รายงานผล → 6 รายงานประชุม; ห้ามสลับเพื่อให้ตรงกับลำดับใน associative array เก่า
- ห้ามลบเอกสารเก่า ข้อมูลจริง หรือ migration เดิม; ห้ามเรียก reset/delete เพื่อทดสอบ; ห้าม drop/reimport ฐานจริงโดยอัตโนมัติ
- ห้ามแก้ชื่อ field, form selector, `action`, hidden input, CSRF หรือ route ที่ Word Merge ใช้โดยไม่ปรับ consumer และทดสอบร่วมกัน
- คง Word/PDF แยกและ Word รวม V4 ที่ผู้ใช้ตรวจผ่าน; แก้เฉพาะจุด ไม่บังคับ font/alignment ทั้งเอกสาร ไม่เปลี่ยน template ที่ผ่านแล้วโดยไม่มีตัวอย่างเปรียบเทียบ
- คง Thai Distribute สำหรับย่อหน้าเนื้อหาที่เหมาะสม, font TH SarabunIT๙, ขนาดและระยะต้นฉบับ, ตาราง Word รวม Left/Right/Before/After = 0; ไม่บังคับระยะบรรทัดหรือหัวเรื่อง/ลายเซ็นทั่วไฟล์
- TOR ข้อ 7 default `reference` (ราคากลาง × จำนวน) ซ่อนคอลัมน์ราคาเสนอเฉพาะเอกสาร; `offer` ใช้ราคาเสนอ × จำนวน; เก็บราคาเสนอไว้ใช้ในขั้นตอนอื่น; อย่าแก้สูตรขนาดบรรจุโดยพลการ
- TOR งบจัดสรร default 6,000,000 บาท แยกจากยอดจัดซื้อ; ข้อ 1 และ 8 ใช้ค่าเดียวกัน; ครั้งที่ปรับปรุง/ปีงบประมาณว่างได้ ไม่แสดง label ค้าง; ไม่ย้ายตารางไปแนบท้ายโดยพลการ
- UI สีฟ้า-ขาว กระชับ centered desktop; ไม่เพิ่มการ์ดซ้อน ปุ่มยืด หรือแถบลอย; preview scroll ด้วยเมาส์ได้; TOR เป็นข้อยกเว้นของ layout สองคอลัมน์
- Login/registration/account approval, role, password policy, session, CSRF และ audit ต้องไม่ถดถอย; admin.php เป็นหลังบ้านรายการยา ไม่ใช่จัดการบัญชี; change_password.php เป็นศูนย์บัญชี
- ไม่ commit secrets, SQL dump ข้อมูลจริง, ZIP ทั้งโครงการ, vendor, เอกสารที่มีข้อมูลส่วนบุคคล หรือไฟล์ทดสอบชั่วคราว; พบไฟล์เก่าที่เสี่ยงให้แจ้งผู้ใช้ ไม่เผยแพร่ซ้ำ

## วิธีส่งมอบ

เปลี่ยนเฉพาะไฟล์จำเป็น ใช้ฐานจาก SHA ล่าสุด ตรวจ diff และ lint ไฟล์ที่แก้ ทดสอบเฉพาะเส้นทางที่ได้รับผลกระทบและ regression สำคัญ ไม่อ่าน/ทดสอบซ้ำโดยไม่มีเหตุผล หากผู้ใช้ต้องการไฟล์พร้อมวางทับ ให้ ZIP เฉพาะไฟล์เปลี่ยนตาม path เดิม; migration แยกเมื่อจำเป็นเท่านั้น ไม่ทำ installer. ระบุสิ่งที่ทดสอบจริง สิ่งที่ยังไม่ได้ทดสอบ (โดยเฉพาะ MS Word/DB จริง) และความเสี่ยง; อย่าอ้าง PASS จากการดูโค้ดอย่างเดียว. Push/commit เมื่อได้รับคำสั่งเท่านั้น.

## ข้อควรระวัง ณ baseline

`schema.sql` ใหม่เป็นโครงสร้าง Dev 24 พ.ย. 2569 ครบ 12 ตาราง; ใช้เฉพาะฐานใหม่ว่าง. ฐานเดิมต้องตรวจ read-only gate ก่อน, ไม่รัน migration เก่าซ้ำ. Legacy CHECK document_type อาจไม่รวมรายงานผล; Dev ไม่มี CHECK ดังกล่าว. ห้ามถอดหรือเพิ่ม constraints ของฐานเดิมโดยพลการ. อ่าน `docs/DATABASE_BACKUP.md`; full dump ห้ามอยู่ Git/web root และการล้าง history ต้องได้รับอนุมัติ. อ่าน `docs/DATABASE.md` ก่อนติดตั้งใหม่หรือเปลี่ยน DB. ห้ามแก้ปัญหาด้วยการลบฐานหรือปิด constraint ทั้งหมด. สถานะที่ผู้ใช้ตรวจผ่าน Word รวม V4 เป็นการรับรองของผู้ใช้ในสภาพแวดล้อมของเขา ไม่ใช่หลักฐานว่าเอกสารใหม่ทุกชนิดผ่านแล้ว.

## Import v1.2.0 — กฎบังคับ (candidate 25 พ.ย. 2569)

อ่าน [docs/IMPORT_SYSTEM.md](docs/IMPORT_SYSTEM.md) ก่อนแก้ Import. แยก `medicines` (คุณลักษณะ) และ `medicine_price_references` (ราคา) ตลอดเส้นทาง; ห้าม map/merge ตารางหรือแก้ ID เดิมโดยพลการ. Excel ยาใช้ 3 คอลัมน์และข้อความหลายบรรทัดใน `specification` TEXT; ห้าม collapse บรรทัด. ใช้ preview แบบ read-only ก่อน confirm ทุกครั้ง; error/duplicate/conflict ต้อง block ทั้งงาน, warning ต้องรับทราบ. Replace ใช้เฉพาะราคากลางและต้องยืนยันจำนวนที่จะนำออก, ห้าม TRUNCATE/DDL ในเส้นทาง import, ใช้ InnoDB transaction และตรวจ fingerprint ภายใต้ lock. ห้ามเปิด route import เดิมให้ bypass Preview. ไม่เก็บไฟล์ staging ใน Git หรือเปิดผ่าน HTTP. ไม่มี migration ในรอบนี้. ต้องทดสอบ MySQL/XLSX จริงบน Dev ก่อน Production; fake PDO/Chromium mock ไม่ใช่ผลทดสอบฐานจริง.

### Import UI guard
รักษา toolbar เหนือตารางและ Popup ทั้งสองประเภท; ห้ามย้ายฟอร์มกลับไปต่อท้ายรายการ. `templates/import/` มี template แยกประเภท, ชีตแรก Import ว่างพร้อม header; ตัวอย่างและคู่มืออยู่ชีตถัดไป. ห้าม import ชีตตัวอย่างอัตโนมัติ. ปิด Popup ขณะงาน busy ไม่ได้; เมื่อยกเลิกต้องเรียก cancel staging และไม่อ้าง rollback หากผล commit ไม่แน่นอน.

การล้างราคากลางประจำปี: แยก action `clear_price_refs` ออกจาก Import/รายการยา; Admin POST + CSRF + คำยืนยัน + รับทราบ backup + count/hash ล่าสุดภายใต้ lock. ลบเฉพาะ `medicine_price_references` ใน transaction, ไม่ TRUNCATE/ไม่ reset ID. ห้ามเปิดทางล้างโดย GET หรือ bypass confirmation. ห้ามลบเอกสาร/คุณลักษณะยา. อ่าน IMPORT_SYSTEM.md ก่อนแก้ไข.


## ข้อกำหนดเพิ่มเติม: ล้างคลังรายการยา
ห้ามทำให้ปุ่มล้างรายการยาเป็น TRUNCATE หรือ bypass guard เอกสารเดิม: TOR ยังอ้างอิง ID live medicines แม้ history เก็บข้อความ snapshot บางส่วน. ล้างได้เฉพาะเมื่อไม่มีชุดจัดซื้อ/เอกสารที่บันทึกไว้ทั้งสี่ตารางที่ตรวจ และต้องมี backup acknowledgement/snapshot/transaction. หากมีเอกสารให้ใช้ import merge รักษา ID เดิม; งานรองรับล้างคลังพร้อมรักษาประวัติทุกกรณีต้องวิเคราะห์และขออนุมัติแยก. ส่งออก Excel 3 คอลัมน์เป็นข้อมูลคลังเท่านั้น ไม่ใช่ backup ฐานข้อมูล. อ่าน docs/IMPORT_SYSTEM.md.
