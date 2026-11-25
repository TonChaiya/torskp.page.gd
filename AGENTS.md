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

ขอบเขต maintenance นี้มีเพียงสองหน้าและ helper ใหม่ ห้ามเปลี่ยน semantics ของ Import/คลังยา/เอกสารอื่นหรือ Word Merge V4. อ่าน PROJECT_STATUS, DOCUMENT_SNAPSHOT, WORKFLOW ก่อนแก้.

หน้า `principle_approval_memo.php`: ช่องชื่อยาและผลค้นหาเต็มความกว้างแถวใน editor pane ทุกขนาด; จำนวน/หน่วย/ลบอยู่บรรทัดถัดไป. จำกัดความสูงผลค้นหา 13rem, เลื่อนใน list, คงค้นหา 3 ตัวอักษร/12 ผลลัพธ์ และการเลือกด้วยเมาส์หรือคีย์บอร์ด. ทั้งแถว PHP และแถวเพิ่มด้วย JavaScript ใช้ layout เดียวกัน; คงชื่อ field และ selection key/Snapshot เดิม.

หน้า `meeting_report.php`: เมื่อมี snapshot รายการยาของรายงานประชุมที่อ่านได้ครบและตรง history_id ให้ใช้รายการที่บันทึกไว้ใน `extra_json.items` สำหรับการเปิดและส่งออกตามปกติ. รายงานเก่าที่ไม่มี snapshot สมบูรณ์ใช้ประวัติ TOR ตาม fallback เดิม. ปุ่ม **ดึงรายการยาและราคา TOR ล่าสุด** เป็น POST `refresh_tor` ผ่านฟอร์มเดิมและ requireLogin/CSRF. ดึง `history_id` ที่หัวข้อ 3 บันทึกไว้เฉพาะ case_id เดียวกัน (หากไม่มี link ใน legacy เลือก history ล่าสุดของ case เดียวกันเท่านั้น); link ผิด case/ไม่มีข้อมูล/แถวอ่านไม่ได้ไม่เปลี่ยนไปใช้ข้อมูลอื่น. การดึงเป็น preview ไม่เรียก save; รักษาข้อมูลวันเวลา สถานที่ กรรมการ ข้อความที่กรอกในฟอร์ม และปรับเฉพาะวลีจำนวนรายการยารูปแบบมาตรฐาน. รายการในตาราง/ตัวแทน `[medicine_items]` ใช้ชุดที่เลือก; ข้อความรายการ/ราคาที่พิมพ์เองไม่ถูกเขียนทับ ต้องตรวจเอง. เมื่อกดบันทึก `save_document` จึงบันทึก snapshot ในรายงานเดิม; ไม่ลบประวัติและไม่แก้ TOR. Hidden source/hash ควบคุมการตรวจว่ารายการหรือ TOR link เปลี่ยนหลัง preview ก่อน Save/Word; ไม่รับราคาจาก hidden fields. คงสูตรราคาเสนอและขนาดบรรจุเดิม รวมถึงการเก็บราคากลางใน snapshot; ไม่เปลี่ยนโหมดราคาของ TOR. Word/PDF และ Word รวม V4 ใช้ข้อมูลของฟอร์มรายงานประชุมตาม source เดียวกัน; ไม่เปลี่ยน engine/แม่แบบ/จัดหน้า.

---

# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](docs/PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### คำสั่งสำหรับ AI/ผู้พัฒนาที่มีผลปัจจุบัน
- เริ่มที่ [PROJECT_STATUS](docs/PROJECT_STATUS.md) และตรวจ HEAD/working tree จริงก่อนแก้. Source baseline ที่ตรวจ `f30c93cfa6be5030aa68ea76c26d941b07dfb52e`; commit ฟีเจอร์ v1.3.0 `cb7183f4dfe08a0ff93f909ad017a7eee2c0f0bf`. รุ่นนี้ขึ้น Production ตามผู้ใช้แล้ว; ไม่ต้องนำงาน v1.1–v1.3 กลับมาทำใหม่โดยไม่มีปัญหาหรือคำสั่งใหม่.
- อ่าน [DOCUMENT_SNAPSHOT](docs/DOCUMENT_SNAPSHOT.md) และ [IMPORT_SYSTEM](docs/IMPORT_SYSTEM.md) เพิ่มเมื่อแก้ catalog/document. กฎ v1.3.0 ใช้เหนือ blanket guard v1.2.0: ล้าง medicines ได้เมื่อ audit ไม่มี issues/recoverable และผ่าน count/hash, backup ack, Admin+CSRF, transaction; deferred เป็นหมายเหตุที่ต้องรักษาตาม contract. ห้ามปลด guard หรือเดาข้อมูลย้อนหลัง. ไม่ใช่ immutable revision archive.
- Git ปัจจุบันไม่มี dump จริงและ staging ที่เคยติด HEAD; `.gitignore` ป้องกัน `storage/tmp/*` ยกเว้น `.gitkeep`/`.htaccess`. ประวัติ Git เก่ายังเสี่ยง; ห้าม rewrite/force push เอง. ห้ามนำ config จริง, vendor, backup, fixture จริงขึ้น Git.
- งานปัจจุบันเป็น maintenance: ขออนุมัติขอบเขตงานใหม่ก่อนแก้, ใช้ diff เฉพาะจุด, ทดสอบตามผลกระทบจริง, ห้ามลด auth/CSRF/validation เพื่อให้ผ่าน. Bugfix พิจารณา v1.3.1; feature ใหม่พิจารณา v1.4.0 เมื่อผู้ใช้กำหนด. ไม่สร้าง tag หรือ release เอง.
- เอกสารชุดนี้เก็บข้อความต้นฉบับทุกไบต์ไว้ใต้ส่วนประวัติ; อ่านหมายเหตุล่าสุดก่อนตีความสถานะเก่า. เพิ่ม/แก้สถานะพร้อมหลักฐานและวันที่ ไม่ลบประวัติ/ข้อกำหนดโดยพลการ. ห้ามอ้างผลทดสอบ DB/Word/Hosting ที่ไม่ได้รันจริง.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

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

## v1.3.0 candidate — Document Snapshot (กฎล่าสุดเหนือ blanket guard v1.2.0)
อ่าน [docs/DOCUMENT_SNAPSHOT.md](docs/DOCUMENT_SNAPSHOT.md) ก่อนแก้เอกสาร/คลังยา. Baseline Git `fced437f022f07924ca163141b02a14c4f176b5c`; candidate ยังรอ UAT จริง. หัวข้อ 1 ใช้ snapshot ชื่อ/หน่วย/จำนวนที่บันทึกและเก็บ spec เมื่อเลือกยาจากคลัง; TOR/Word รวมต้องใช้ history row key + case/history ownership ไม่ remap ID หรือชื่อจาก live catalog. ไม่เดาข้อมูลเก่าจากคลังปัจจุบัน. Backfill ใช้หลักฐาน TOR ในชุดเดียวกันเท่านั้นและต้อง Admin ยืนยันหลังสำรอง. ล้างยาได้เฉพาะผ่าน snapshot audit, recoverable/issue เป็นศูนย์, ตรวจ hash ของเอกสาร+catalog ซ้ำใน transaction; deferred (หัวข้อ 1 สมบูรณ์และ TOR ที่บันทึกมี snapshot ครบ) ไม่เติม spec ที่ไม่ตรงกันและต้องแสดงหมายเหตุ. ห้ามลบ/เขียนทับประวัติ TOR เพื่อปลด guard. คง V4 merge engine, layout, สูตรราคา, price modes, auth และ import v1.2.0. ไม่มี migration; ทดสอบ clone DB/Word จริงก่อน Production. ไฟล์ชั่วคราวที่ติด Git อยู่เป็นงานที่ผู้ใช้เลื่อนออกไป ห้ามเผยแพร่เพิ่ม/แก้ Git history เอง.
