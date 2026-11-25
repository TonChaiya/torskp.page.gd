# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](docs/PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### จุดเริ่มต้นปัจจุบัน
ใช้ [AGENTS.md](AGENTS.md) และ [PROJECT_STATUS.md](docs/PROJECT_STATUS.md) เป็นกฎและสถานะ canonical. อ่าน [DOCUMENT_SNAPSHOT](docs/DOCUMENT_SNAPSHOT.md) กับ [IMPORT_SYSTEM](docs/IMPORT_SYSTEM.md) เมื่อเกี่ยวข้อง; อย่าย้อนใช้กฎ blanket guard เก่าหรือเริ่ม implementation v1.3.0 ซ้ำ. ปฏิบัติงาน maintenance เฉพาะที่ผู้ใช้สั่ง; รักษาเอกสารเดิม, ไม่เปลี่ยน PHP/DB โดยไม่มี scope. ไม่อ้าง independent UAT จากรายงานผู้ใช้.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# CLAUDE.md — จุดเริ่มต้นสำหรับ Claude

ใช้ [AGENTS.md](AGENTS.md) เป็นกฎหลักร่วมกันกับ AI เจ้าอื่น ห้ามมีนโยบายซ้ำที่ขัดกัน

อ่าน AGENTS.md ให้จบก่อน จากนั้นอ่าน [สถานะล่าสุด](docs/PROJECT_STATUS.md), [Workflow](docs/WORKFLOW.md), [Architecture](docs/ARCHITECTURE.md) และคู่มือเฉพาะงานตามรายการใน AGENTS.md. ตรวจ Git HEAD จริงก่อนใช้ baseline; อย่าอาศัย context จากแชทเก่าอย่างเดียว. หากต้องแก้ Word ให้เปิด WORD_EXPORT และอ่านทั้งตัวสร้างรายหัวข้อกับตัวรวม; หากแก้ฐานข้อมูลให้เปิด DATABASE ก่อน. ปฏิบัติตามขอบเขตที่ผู้ใช้ยืนยันและรายงานผลทดสอบตามจริง.
