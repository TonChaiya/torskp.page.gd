# TOR SKP — Admin Workspace Hotfix 20261126c

## สาเหตุ
ชุด b ซ่อน grid เดิมก่อน JavaScript พร้อม แต่ `.compact-admin-main` ใน CSS ที่โหลดจาก head ยังมีเพดาน 1920px ขณะที่ CSS workspace ที่โหลดภายหลังเปลี่ยนเป็น 1160px ทำให้หัวเว็บกว้างชั่วคราวแล้วหดลง รวมถึง grid ที่ visibility:hidden ยังจองพื้นที่แนวตั้งอยู่

## การแก้ไข
แก้เฉพาะ `assets/admin-compact.css` จาก ZIP b: กำหนดความกว้างและ padding เดียวกับ workspace ตั้งแต่ first paint (1160px สูงสุด; จอเล็ก <=640px เว้นขอบรวม 20px); ซ่อนและยุบ grid เก่าระหว่างเตรียมหน้า จัด nav ช่วงรอให้ใกล้เคียงกับหน้าใหม่. ไม่เปลี่ยนฟอร์ม, PHP, JS, endpoint, SQL, การจัดการกรรมการ, CSS ของ Popup, เอกสารทั้งหกขั้นหรือ Word/PDF. PHP, JS และ workspace CSS ในชุดสะสมนี้เหมือนชุด b ทุกไบต์. เอกสารประกอบเก่าทั้งสองฉบับคงไว้.

## Fallback
หากโหลด CSS workspace ไม่สำเร็จ ระบบเดิมยังแสดงทันทีผ่าน fallback class; หากสคริปต์ถูกบล็อกหรือใช้เวลามากกว่า 8 วินาที กลไก CSS เดิมเปิดให้เข้าถึงฟอร์มเดิมเพื่อไม่ให้หน้าใช้งานไม่ได้ แต่ header ยังมีความกว้างใหม่คงที่. หากปิด JavaScript ตั้งแต่แรกจะไม่ซ่อนฟอร์มเดิม. ไม่แก้ version query ใน admin.php; ต้อง Ctrl+F5 หลังวางทับเพื่อให้แน่ใจว่า browser ใช้ CSS ใหม่.

## การตรวจสอบ
- PHP lint และ Node syntax check ผ่าน; เปรียบเทียบ SHA-256 ไฟล์ PHP, JS, workspace CSS กับชุด b ตรงกัน.
- Chromium offline fixture regression 25 checks ผ่าน (popup, scroll, edit, search listener, export, snapshot, clear guard, committee mock, signers, responsive, deep link).
- First-paint 7 scenarios ผ่าน: 1366x768, 1920x1080, 2560x1080, 390x844 โดยหน่วง JS 750ms; ตำแหน่ง x/y และความกว้าง/สูง header ก่อน-หลังเท่ากันใน fixture; grid เก่ามีความสูง 0 ระหว่างรอ. ทดสอบ fallback เมื่อ JS ไม่โหลดหลัง 8 วินาที, explicit fallback class, และปิด JS.
- ยังไม่ได้รันบน Laragon/MySQL หรือ InfinityFree จริง ไม่ได้เปลี่ยน Git/Production.

## วางทับ
หากลงชุด b แล้ว วางทับเพียง `assets/admin-compact.css` (สำรองก่อน); หรือใช้ ZIP สะสมหากยังไม่ได้ลงชุดก่อน. เข้า admin.php จากหน้าอื่นแล้วกด Ctrl+F5; ตรวจการเข้าใหม่ทั้ง normal reload และ hard reload. ไม่ต้อง migration ไม่ต้องติดตั้ง และไม่ต้องลบข้อมูล.
