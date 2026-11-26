# UI Guidelines — Admin v1.4.0

**26 พฤศจิกายน 2569 — Git runtime baseline v1.4.0**

Repository: `TonChaiya/torskp.page.gd` · branch `main` · runtime commit [`c4699b22`](https://github.com/TonChaiya/torskp.page.gd/commit/c4699b22bbb58e563d4983f4fec9e0c119d97164).

- ผู้ใช้ Push แล้วเวลา 09:43:38 ICT (commit timestamp); ข้อความ commit `v1.4.0` / `feat(admin): redesign workspace and add committee management`. ตรวจ Git blob ตรงกับ ZIP Hotfix C ทั้ง 7 ไฟล์. รุ่นนี้เป็น source baseline ไม่ใช่ Git tag หรือการยืนยัน deploy.
- ผู้ใช้ตอบรับการแก้ UI ล่าสุดและอนุญาต Push; ยังไม่มีการยืนยันการอัปโหลด v1.4.0 ขึ้น InfinityFree หรือ UAT ครบทุกฟังก์ชันบน MySQL/Word จริง. v1.3.0 เป็น Production baseline ล่าสุดที่มีบันทึกยืนยันชัดเจน (25 พ.ย. 2569); ไม่อนุมานว่า Hosting ยังอยู่รุ่นใดในปัจจุบัน.
- Git baseline มีโค้ด hotfix v1.3.1 ของหลักการและรายงานประชุมอยู่แล้ว; ตรวจพบ `core/meeting_tor_refresh.php` และ renderer ใน `meeting_report.php` ที่รองรับ `price_basis`. ไม่ย้อนกลับไปใช้สูตรราคาเสนออย่างเดียว หรือเริ่มงานที่ปิดแล้วซ้ำโดยไม่มีปัญหาใหม่.
- เอกสารรอบนี้เป็น docs-only handoff ยังไม่ Push จากผู้ช่วย; HEAD จะเปลี่ยนเมื่อผู้ใช้ commit เอกสาร แต่ runtime baseline ข้างต้นยังเดิม. ไม่เปลี่ยน PHP/JS/CSS, SQL, template, DB หรือ Hosting.

## มาตรฐานหน้าจอ Admin ล่าสุด

- ธีมฟ้า-ขาว กระชับ centered max-width 1160px; จอเล็ก <=640px เว้นขอบรวม 20px. Header ไม่ขยายเต็มจอแล้วหดหลัง JS พร้อม; grid เก่าไม่จองพื้นที่ระหว่างรอ. ทดสอบ slow JS และ CSS fallback ไม่ให้หน้า Admin ใช้งานไม่ได้.
- แบ่ง 2 กลุ่ม 4 เมนูชัดเจน: ยา/ราคากลาง และชุดกรรมการ/ผู้เสนอผู้อนุมัติ. คลิกเมนูเปิด native dialog กลางจอ; ไม่ยืดหน้าเดิมลงล่าง. ปุ่มไม่ยืดเต็มแถวโดยไม่จำเป็น.
- Popup มีขนาดพอดี viewport, header/ปุ่มควบคุมชัดเจน; mousewheel เมื่อวางเมาส์ตรงกลางหรือบนตารางเลื่อนเนื้อหาได้, ตารางกว้างเลื่อนแนวนอนได้, ไม่เลื่อนพื้นหลัง. รักษา focus/keyboard/close และ busy/confirmation/error states เดิม.
- จัดกลุ่มค้นหา/เพิ่ม/นำเข้า/ส่งออก/ตรวจ Snapshot/ล้างข้อมูลชัดเจน; ไม่ซ่อนฟังก์ชันเดิม. รักษา live search, pagination, ไม่ reload/กระโดดหน้าหลักตอนค้นหา; การแก้ไขเปิด Popup เดิม, ไม่ clone หรือเปลี่ยน form contract.
- ชุดกรรมการแสดงประธานและกรรมการสองคน, เพิ่ม/แก้ไข/ลบต้นแบบพร้อมยืนยันชื่อและตรวจ stale. ผู้เสนอและผู้อนุมัติแยกส่วนกรอก ใช้ field เดิม; ไม่มี per-case override ใหม่.
- ไม่แก้ layout ของ 6 ขั้น, Word/PDF, history หรือ login/account center เพียงเพราะเปลี่ยน Admin.

ผลทดสอบตามบันทึก Hotfix C: PHP lint และ Node syntax ผ่าน; Chromium offline fixture regression 25/25 และ first-paint 7/7 scenarios ผ่าน รวม 1366×768, 1920×1080, 2560×1080, 390×844 และ fallback. DOM/API mock ไม่ใช่ MySQL/HTTP integration, Word/PDF จริง หรือ Production UAT. รอบ docs-only นี้ตรวจ hash และเนื้อหาเอกสาร ไม่รันชุดทดสอบระบบซ้ำโดยไม่จำเป็น.

รายละเอียด: [Admin Workspace](ADMIN_WORKSPACE_20261126.md) · [Hotfix B](ADMIN_WORKSPACE_HOTFIX_20261126.md) · [Hotfix C / First Paint](ADMIN_WORKSPACE_FIRST_PAINT_20261126c.md).

---

> **ประวัติเดิม (เก็บครบตาม Git blob ก่อนปรับ v1.4.0):** ข้อมูลเวอร์ชัน, HEAD, สถานะ candidate, คำว่า "ปัจจุบัน" และคำสั่ง release ในเนื้อหาด้านล่างอ้างอิงช่วงเวลาที่เขียน ไม่ใช่สถานะล่าสุด ให้ยึดส่วน v1.4.0 ด้านบนและ PROJECT_STATUS เป็นหลักสำหรับสถานะ; ข้อกำหนดทางเทคนิคเดิมยังมีผลเว้นแต่มีการแก้ไขชัดเจนในส่วนใหม่.

# v1.3.1 — Maintenance hotfix

**25 พฤศจิกายน 2569 — v1.3.1 Hotfix (candidate):** v1.3.0 ยังเป็น Production baseline ที่ผู้ใช้ยืนยัน; Git HEAD ก่อนแก้ `7aec524d106b1c4a61653fa4c9d518a6d07d7c07` เป็น docs closure ที่ Push แล้ว. ผู้ใช้รายงานสองปัญหาหลังขึ้น Production และอนุมัติแก้เฉพาะจุด. Hotfix ยังไม่ได้ Push หรือ deploy และรอผู้ใช้ทดสอบ Dev/Production. ไม่มี migration. ข้อมูลประวัติเดิมด้านล่างคงอยู่ครบ.

หน้าอนุมัติหลักการ: ห้ามกลับไปใช้ grid 4 คอลัมน์ตาม viewport เพราะ editor pane แคบแม้จอใหญ่; ชื่อยาและ list ใช้ทั้งแถว, จำนวน/หน่วย/ลบอยู่แถวถัดไป. Scroll list แยกจาก editor; คงฟังก์ชันค้นหาและเลือกเดิม. ปุ่ม refresh ในรายงานประชุมอยู่ในส่วนเลือกประวัติ TOR ด้านบน ขนาดกระชับ; ใช้ form owner `meeting_report_form`.

---

# บันทึกสถานะปัจจุบัน — v1.3.0

**สถานะล่าสุด 25 พฤศจิกายน 2569 (ปิดงาน v1.3.0):** ผู้ใช้ยืนยันว่า Dev ใช้งานได้ และอัปโหลดขึ้น InfinityFree ใช้งานจริงได้หมดแล้ว (12:56 น. ICT). กำหนด v1.3.0 เป็น stable maintenance baseline ตามการยืนยันของผู้ใช้; ไม่ใช่ผลรับรอง independent UAT หรือ security audit. ข้อความ Candidate/รอ Push/รอ Production ในเนื้อหาเดิมด้านล่างเป็นบันทึกตามเวลาที่เขียน ไม่ใช่งานค้างปัจจุบัน. ข้อกำหนดทางเทคนิคและข้อจำกัดการทดสอบเดิมยังคงมีผล.

อ่าน [สถานะ canonical](PROJECT_STATUS.md) สำหรับงานคงเหลือและขอบเขตหลักฐาน.

### UI ที่ต้องรักษาในรุ่นปัจจุบัน
Admin มี toolbar กระชับเหนือรายการ, Popup แยก add/edit/import และ safe clear ของแต่ละคลัง, Excel export รายการยา, ปุ่มตรวจ Snapshot และรายละเอียด recoverable/issues/deferred. รักษา live search ไม่ reload, scroll ภายใน Popup, busy/confirmation/error states, action attribute fix ไม่ใช้ `form.action` เมื่อมี named input `action`. ไม่เพิ่มการ์ดซ้อน/ปุ่มยืดหรือย้ายฟอร์มลงท้ายรายการ. ไม่ปรับหน้าจอที่ผู้ใช้ใช้งานได้แล้วโดยไม่มี scope; ดู [IMPORT_SYSTEM](IMPORT_SYSTEM.md) และ [DOCUMENT_SNAPSHOT](DOCUMENT_SNAPSHOT.md) ก่อนแก้.

---

> **เอกสารต้นฉบับ / ประวัติการพัฒนา:** เนื้อหาต่อจากนี้เก็บครบตาม Git baseline; กฎปัจจุบันด้านบนใช้เฉพาะเมื่อแก้สถานะหรือชี้แจงความขัดแย้ง ไม่ยกเลิกข้อกำหนดทางเทคนิคเดิม.

# UI/UX guidelines

## เป้าหมาย

ระบบงานราชการภาษาไทย เน้นความชัดเจนและประสิทธิภาพ ไม่ใช่ dashboard เชิงการตลาด. ธีมฟ้า-ขาวสอดคล้อง login. หน้าจอโน้ตบุ๊ก 15–16 นิ้ว และ ultrawide 29 นิ้วต้องไม่ถูกยืดจนอ่านยาก. จัด centered max width, ปุ่มสั้นเท่าที่จำเป็น, หลีกเลี่ยงการ์ดซ้อนการ์ดและป้ายที่ไม่มีหน้าที่.

| หน้า | มาตรฐาน |
|---|---|
| index | รายการงาน 6 ขั้นตอนเรียงตาม workflow, compact, เมนูชัดเจน |
| login/register | ธีมเดียวกัน, error/status สมัคร pending ชัดเจน, ไม่เปิดใช้ก่อนอนุมัติ |
| change_password | ข้อมูลบัญชีตนเองก่อน, admin จัดการบัญชีและ approval ตามสิทธิ์ |
| admin | งานหลักยา/ราคากลาง/ผู้ลงนาม; live search ไม่ reload/กระโดดขึ้นบน, import progress, ตารางกระชับ |
| 5 memo workspaces | preview ซ้าย/ฟอร์มขวาใน desktop, scroll แยก, กว้างพอดีจอ; small screen ใช้แท็บกรอก/ตัวอย่าง |
| TOR | full-width form centered ตามลำดับ WORKFLOW, ไม่บังคับแบ่งสองคอลัมน์ A4 กับ editor |
| history | 1 row ต่อ case; search/filter, completion, ปุ่มเปิด/ทำต่อ, รวม Word, ทุกหัวข้อ; ปุ่ม merge ราว 71×35px ตามรุ่นที่ผู้ใช้รับ ไม่สร้างแถวใหญ่เพิ่ม; status เฉพาะเวลาประมวลผล |

`document-workspace.js` ใช้ body class docws, data-docws-layout/preview/editor, desktop breakpoint >=1040px และสูง >=600px; fitPages จากขนาดกระดาษจริง (ไม่คูณ zoom ซ้อน), mousewheel ใน preview เมื่อเลื่อนได้, ไม่ hijack ctrl zoom หรือ input. ข้อยกเว้น result memo มี shell ภายใน; TOR ไม่มี desktop two-pane. CSS/JS มีหน้าที่ screen-only ห้ามแก้ print layout โดยพลการ. ไม่คืน toolbar ลอย A4/เลื่อนเฉพาะพรีวิว/พอดีพื้นที่/100% ที่เคยเอาออก.

## ทดสอบหลังแก้ UI

ตรวจ 390, 1366, 2560px เป็นตัวแทน mobile/notebook/wide (ไม่เท่ากับรับรองอุปกรณ์จริงทั้งหมด); scroll ด้วยเมาส์กลาง preview, ไม่มี horizontal overflow, ปุ่มไม่ยืด, ฟอร์มไม่หาย, focus/keyboard และ error state ใช้งานได้. ตรวจ browser print แยกจาก screen. หน้าประวัติทดสอบ case ครบ/ไม่ครบและปุ่มแต่ละ case; admin ทดสอบค้นหา/clear/no results/import โดยไม่เปลี่ยนข้อมูลจริง. อย่าเปลี่ยน form ID/name/POST action เพื่อความสวยงามเพียงอย่างเดียว.
