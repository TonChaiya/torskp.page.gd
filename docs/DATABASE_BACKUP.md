# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### สถานะ cleanup และ backup ปัจจุบัน
ตรวจ tree ของ `f30c93cf`: ไม่พบ dump สองไฟล์เดิมใน HEAD และไม่พบ import staging; `.gitignore` มี SQL deny-by-default และ runtime tmp exclusions. คำสั่ง untrack ในประวัติด้านล่างไม่ใช่งานที่ต้องรันซ้ำ. การลบจาก HEAD ไม่ได้ลบประวัติ/clone/fork/cache; repo ยัง Public ณ วันที่ตรวจ. จำกัดสิทธิ์, ตรวจผลกระทบ, rotate secrets/บัญชีที่เสี่ยงตามผู้รับผิดชอบ; ห้าม rewrite history เอง.

หลังขึ้น Production ให้ Export structure+data ผ่าน phpMyAdmin และสำรองไฟล์เว็บ/config/vendor ที่ใช้งานได้พร้อม SHA รุ่นและวันเวลา เก็บแบบ private นอก web root/Git. ตรวจไฟล์ backup และทดสอบ restore ไปฐานแยก; อย่าใช้ระบบ Production เป็นที่ทดสอบ. ยังไม่มีการยืนยันว่า backup หลัง release/restore drill เสร็จแล้ว.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# Backup, restore และนำ SQL dump ออกจาก Git

## สิ่งที่พบ

main `6c6ed8e0` ยัง track full dumps 2 ไฟล์: `database/backup-laragon-if0_40414720_tor_skp.sql` และ `database/tor_skp-full-localhost-ready-for-import-25690821-163909.sql`. ตรวจพบ INSERT ใน users, procurement_cases/documents, tor_histories และข้อมูลตั้งค่าอื่น ๆ. ถือเป็นข้อมูลจริงที่ต้องจำกัดสิทธิ์; ไม่คัดลอกค่า hash, IP, ชื่อ หรือรายละเอียดเอกสารลง docs. ZIP โครงการที่ลบจาก tree ก่อนหน้านี้ยังอาจอยู่ใน history. `.gitignore` ป้องกันไฟล์ใหม่ ไม่เอาไฟล์ tracked ออกจาก Git และไม่ลบประวัติ.

## เก็บสำรองก่อน untrack (Windows/Laragon PowerShell)

ใช้ folder นอก `C:\laragon\www` และนอก Git; ตัวอย่าง `C:\TOR-SKP-private-backups` ต้องตั้ง Windows ACL ให้เฉพาะผู้ได้รับอนุญาตและเข้ารหัส backup หากทำได้. ไม่ใช้ public cloud link. เก็บ dump Dev ล่าสุดที่ส่งออกมาแยกด้วย. ห้ามอัปโหลดไฟล์นั้นขึ้น Git.

```powershell
Set-Location C:\laragon\www\torskp.page.gd
$backup = 'C:\TOR-SKP-private-backups'
New-Item -ItemType Directory -Force -Path $backup | Out-Null
$files = @(
  'database/backup-laragon-if0_40414720_tor_skp.sql',
  'database/tor_skp-full-localhost-ready-for-import-25690821-163909.sql'
)
foreach ($f in $files) {
  $dst = Join-Path $backup (Split-Path $f -Leaf)
  if (Test-Path $dst) { throw "Backup destination already exists: $dst; use a new dated folder" }
  Copy-Item -LiteralPath $f -Destination $dst -ErrorAction Stop
  if ((Get-FileHash -LiteralPath $f -Algorithm SHA256).Hash -ne (Get-FileHash -LiteralPath $dst -Algorithm SHA256).Hash) { throw "Backup verification failed" }
}
```

หลังตรวจ copy และ hash ครบแล้ว จึงนำออกจาก **Git index เท่านั้น** (ไฟล์ในเครื่องยังอยู่) ด้วยคำสั่งแยกต่างหาก:

```powershell
git rm --cached -- database/backup-laragon-if0_40414720_tor_skp.sql database/tor_skp-full-localhost-ready-for-import-25690821-163909.sql
git status --short
git ls-files -- '*.sql'
```

ตรวจว่าเหลือ schema/migrations/checks ที่ตรวจแล้วเท่านั้น; git status แสดง D สำหรับ dump หมายถึงเอาออกจาก commit ถัดไป ไม่ใช่ลบไฟล์ในเครื่อง. เมื่อยืนยันสำรองภายนอกแล้ว ควรเก็บไฟล์จริงเฉพาะภายนอก web root (การย้ายไฟล์ใน working tree ต้องให้ผู้ใช้ทำเอง). ห้ามใช้ git rm แบบไม่มี --cached หรือ git clean เพื่อทำงานนี้.

## Backup และ Restore ปกติ

- phpMyAdmin Export → Custom → SQL → เลือกทุก 12 ตาราง → structure + data สำหรับ private backup. เก็บไฟล์ลงที่ปลอดภัยพร้อมวันเวลาและรุ่น; แยก Dev และ Production; backup ก่อน migration/deploy, กำหนดรอบตามความสำคัญของข้อมูล.
- สำหรับ Git ใช้เฉพาะ `database/schema.sql` ที่ไม่มี data, migration ที่ review แล้ว และ read-only checks. ไม่ commit dump, archive, credential, vendor หรือเอกสารจริง. `.gitignore` ไม่ใช่เครื่องมือตรวจ secret; ตรวจ staged diff ทุกครั้งแม้ไฟล์ชื่อ schema/migration.
- Restore ทดสอบไป **ฐานใหม่** (ชื่อแยก) ไม่ใช่ฐานที่ใช้งานจริง. ตรวจว่า dump ไม่มี USE/CREATE DATABASE/DDL ที่ชี้ฐานอื่นก่อน import; จากนั้นตรวจ schema/count/integrity และ smoke test. เปลี่ยน env เฉพาะ instance ทดสอบ; ไม่ชี้โปรแกรม Production ไปฐานทดสอบ. ไม่ import schema ใหม่ทับ full backup เพราะ backup มี DDL อยู่แล้ว.
- เก็บสำรองแบบจำกัดสิทธิ์และเข้ารหัสตาม policy หน่วยงาน, ทดสอบ restore จริงเป็นระยะ. ไม่ส่ง private backup ต่อใน chat/Git/issue.

## ประวัติ Git และความเสี่ยงเดิม

การ untrack/commit/push ไม่ทำให้ dump หายจาก commit เก่า, clone, fork หรือ cache. ตรวจ repository visibility และผู้มีสิทธิ์, ทบทวนข้อมูลที่อาจเคยเปิดเผย; หากเคยเปิด public หรือแชร์นอกกลุ่ม ให้จำกัด access ทันที ประเมินผลกระทบข้อมูลส่วนบุคคลกับผู้รับผิดชอบ และเปลี่ยนรหัสผ่านบัญชีที่อาจได้รับผลกระทบ รวมทั้ง revoke/rotate secrets ที่พบว่ารั่วจริง. Password hash ยังเป็นข้อมูลอ่อนไหวแม้ไม่ใช่ plaintext. อย่าเปิดเผยค่าในรายงาน. ประเมิน git filter-repo/ประสาน GitHub เพื่อล้าง history แยกเป็นงานที่ต้องอนุมัติ เพราะเปลี่ยน SHA และกระทบ clone ทุกเครื่อง; ไม่ force push หรือ rewrite ในงานนี้. ตรวจ policy และสิทธิ์ก่อนทำงานต่อ.
