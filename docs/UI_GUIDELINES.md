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
