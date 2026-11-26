# TOR SKP — Admin Workspace Hotfix 2026-11-26b

## ขอบเขต
ชุดสะสมแทน ZIP 20261126 ก่อนหน้า; ไม่ต้องติดตั้งและไม่ต้อง migration. ใช้ `admin.php` เวอร์ชันเดิมจาก main (blob `4f98527ac0fabd570363c2d0b74142f305e22e70`). ไม่ได้แก้ PHP Business Logic ของ admin.php หรือเอกสารทั้งหกขั้น. `admin_committee.php` จาก ZIP ก่อนหน้าเหมือนเดิมทุกไบต์.

## การแก้ไข
- `assets/admin-compact.css` (ต้นฉบับ Git blob `f41aa9ac82f3de17ff74645d195e6c7c910f7b32` คงเดิมทุกไบต์ แล้วเพิ่ม CSS ต่อท้าย): ซ่อนส่วนฟอร์มเดิมระหว่างเตรียม workspace เมื่อ browser รองรับ scripting media query; หัวหน้าเว็บยังแสดงอยู่. ถ้าโหลด CSS workspace ไม่สำเร็จ JS จะเปิดหน้าเดิม; หาก JS ไม่ทำงานเลย CSS จะคืนหน้าเดิมหลัง 8 วินาที. หากปิด JS ไว้จะไม่ซ่อนฟอร์ม. ถ้าโหลดช้ามากเกิน 8 วินาที อาจเห็นหน้าเดิมเพื่อให้ระบบยังใช้งานได้.
- `assets/admin-modal.js`: คง controller เดิมจากชุดก่อนหน้า; เพิ่มการจัด layout โดยย้าย DOM เดิม ไม่ clone. กลุ่มค้นหา/เพิ่ม/นำเข้า/เครื่องมือ/ล้างข้อมูลแยกชัดเจน. แบ่งผู้เสนอและผู้อนุมัติเป็นสองส่วน. คงชื่อฟิลด์ ฟอร์ม action, CSRF และ event listener เดิม. การค้นหา/เปลี่ยนหน้าจะเลื่อนกลับต้น Popup เท่านั้นเมื่อรายการเปลี่ยน ไม่เลื่อนหน้าหลัก.
- `assets/admin-workspace.css`: ให้ body ของ Popup เป็นตัวเลื่อนแนวตั้งหลัก; ยกเลิกความสูงที่ขัดกันบน details และตาราง; ตารางยังเลื่อนแนวนอนได้. ปรับ UI ด้านในของ catalog, signer และ add/edit dialogs ให้สม่ำเสมอ.

## การทดสอบที่ทำจริง
PHP lint `admin_committee.php` ผ่าน; Node syntax check ผ่าน. Chromium offline DOM fixture 25 checks ผ่าน: ซ่อนฟอร์มเก่าก่อน JS, header ยังแสดง, 4 เมนู, identity/listener เดิม, toolbar, wheel scroll ที่กลาง Popup/บนตาราง, export/snapshot links, edit data, clear guard, signers layout/values, committee create/update/delete mock, ขนาดจอ 1366x768, 2560x1080, 390x844, deep link และไม่มี browser JS errors. ไม่ใช่การทดสอบหน้า PHP ที่เชื่อม MySQL จริง และไม่ได้ทดสอบ Word/PDF จริง. ไม่เปลี่ยน Git หรือ Production.

## วางทับ
สำรองไฟล์เดิมก่อน วางทับ `assets/admin-compact.css`, `assets/admin-modal.js`, `assets/admin-workspace.css`; ถ้ายังไม่ได้ลงชุดก่อนหน้า ให้วาง `admin_committee.php` ด้วย. เปิด admin.php กด Ctrl+F5 เพื่อโหลด assets ใหม่. ไม่ต้องลบไฟล์หรือข้อมูล. ควรทดสอบ Laragon ก่อน InfinityFree.

## UAT
- เปิด admin.php ใหม่จากหน้าหลัก ตรวจไม่เห็นฟอร์มเดิมระหว่างโหลดปกติ.
- เปิดรายการยา/ราคากลาง เลื่อนเมาส์จากกลางตารางและกดก่อนหน้า/ถัดไป; ตรวจ search, add/edit, export, import preview/cancel และ snapshot link.
- ตรวจผู้ลงนามและชุดกรรมการบนฐาน clone ก่อนบันทึกจริง; เอกสารเดิมทั้งหกขั้นเปิดได้เหมือนเดิม. ห้ามทดสอบล้างรายการบนฐานจริงโดยไม่มี backup และแผนทดสอบ.
