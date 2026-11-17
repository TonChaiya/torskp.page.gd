# TOR SKP

ระบบจัดทำเอกสาร TOR ภาษาไทย ใช้ PHP 8.1 ขึ้นไป, MySQL/MariaDB, PDO และฐานข้อมูล `utf8mb4`.

## ตั้งค่าฐานข้อมูล

ระบบอ่านค่าการเชื่อมต่อจาก environment variables ต่อไปนี้ โดยมีค่าสำหรับ Laragon เป็นค่าเริ่มต้น:

- `TOR_DB_HOST`
- `TOR_DB_NAME`
- `TOR_DB_USER`
- `TOR_DB_PASS`

ห้ามเก็บรหัสผ่านฐานจริงไว้ใน repository หรือไฟล์ที่เปิดผ่านเว็บได้

## ติดตั้งฐานใหม่

1. สร้างฐานข้อมูลโดยเลือก character set `utf8mb4` และ collation `utf8mb4_unicode_ci`
2. นำเข้า `database/schema.sql` หากต้องการเฉพาะโครงสร้าง หรือใช้ไฟล์ full dump ที่ส่งมอบหากต้องการข้อมูลทั้งหมด
3. ตั้งค่าการเชื่อมต่อฐานข้อมูลผ่าน environment variables
4. รัน `composer install --no-dev --optimize-autoloader`
5. บังคับใช้ HTTPS บนระบบจริง

บัญชีที่ย้ายมาจากฐานเดิมจะถูกบังคับให้เปลี่ยนรหัสผ่านที่ไม่ซ้ำชื่อผู้ใช้ก่อนใช้งานหน้าอื่น บัญชีใหม่ต้องใช้รหัสผ่านอย่างน้อย 6 ตัวอักษรและมีทั้งตัวอักษรกับตัวเลข

## อัปเกรดฐานเดิม

สำรองฐานก่อนเสมอ แล้วรัน Migration ตามลำดับดังนี้

1. `database/migrations/20260821_security_hardening.sql` เพิ่มชนิดข้อมูล JSON, foreign key, check constraint, role, login throttling และ audit log
2. `database/migrations/20260821_document_signer_defaults.sql` เพิ่มค่าเริ่มต้นชื่อและตำแหน่งผู้เสนอ/ผู้อนุมัติสำหรับบันทึกขออนุมัติในหลักการ

Migration ทั้งสองไฟล์ไม่แก้เนื้อหาเอกสาร TOR และออกแบบให้รันซ้ำได้โดยไม่สร้างข้อมูลซ้ำ
