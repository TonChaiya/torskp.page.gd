<?php
// Included in admin.php after requireAdmin(). $importKind is a fixed server-side value.
$priceImport = $importKind === 'prices';
?>
<div class="tor-import" data-import-kind="<?= h($importKind) ?>">
  <form class="ti-upload" method="post" action="admin.php" enctype="multipart/form-data">
    <input type="hidden" name="action" value="<?= $priceImport ? 'import_price_refs' : 'import_medicines' ?>">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="kind" value="<?= h($importKind) ?>">
    <label class="ti-label">ไฟล์ Excel / CSV UTF-8 (ไม่เกิน 10 MB)</label>
    <input class="ti-file" name="file" type="file" accept=".xlsx,.csv" required>
    <p class="ti-hint"><?= $priceImport ? 'รายการยา · รูปแบบยาและความแรง · หน่วย · ราคากลางต่อหน่วย' : 'ชื่อยา · หน่วย · คุณสมบัติยา (Alt+Enter ภายในเซลล์ได้)' ?></p>
    <p class="ti-hint">กรอกข้อมูลจริงที่ชีตแรก Import ตั้งแต่แถว 2; ชีตตัวอย่างและคู่มือไม่ถูกนำเข้า</p>
    <?php if ($priceImport): ?>
    <fieldset class="ti-modes"><legend>วิธีนำเข้า</legend>
      <label><input type="radio" name="mode" value="merge" checked> เพิ่มและอัปเดตข้อมูลเดิม</label>
      <label><input type="radio" name="mode" value="replace"> แทนที่ราคากลางทั้งหมด</label>
      <p class="ti-hint">โหมดแทนที่จะแจ้งจำนวนที่จะลบก่อนยืนยัน และจะย้อนคืนทั้งชุดเมื่อเกิดข้อผิดพลาด</p>
    </fieldset>
    <?php else: ?><input type="hidden" name="mode" value="merge"><?php endif; ?>
    <div class="ti-actions"><button type="submit" class="ti-primary">ตรวจสอบไฟล์ก่อนนำเข้า</button><a class="ti-secondary" href="admin.php?action=<?= $priceImport?'sample_price_refs':'sample_medicines' ?>">ดาวน์โหลด Excel Template</a></div>
  </form>
  <noscript><p>ต้องเปิด JavaScript เพื่อใช้งาน Preview และยืนยันการนำเข้า</p></noscript>
  <div class="ti-progress" hidden><progress max="100"></progress><span role="status" aria-live="polite"></span></div>
  <p class="ti-message" role="status" aria-live="polite" hidden></p>
  <section class="ti-preview" hidden aria-label="ตรวจสอบก่อนนำเข้า">
    <div class="ti-head"><strong>ตรวจสอบก่อนนำเข้า</strong><span class="ti-filename"></span></div>
    <p class="ti-hint">ยังไม่มีการเปลี่ยนแปลงข้อมูลจริง · Preview ใช้ได้ 2 ชั่วโมง</p>
    <div class="ti-summary"></div>
    <p class="ti-replace" hidden></p>
    <div class="ti-filter">
      <input type="search" class="ti-search" placeholder="ค้นหาชื่อยา / ความแรง / หน่วย" maxlength="120" aria-label="ค้นหาใน Preview">
      <select class="ti-status" aria-label="กรองสถานะ"><option value="all">ทุกสถานะ</option><option value="new">เพิ่มใหม่</option><option value="update">อัปเดต</option><option value="same">เหมือนเดิม</option><option value="warning">คำเตือน</option><option value="error">ผิดพลาด</option><option value="duplicate">ข้อมูลซ้ำ</option><option value="conflict">ขัดแย้ง</option></select>
      <select class="ti-size" aria-label="จำนวนต่อหน้า"><option>25</option><option>50</option><option>100</option></select>
    </div>
    <div class="ti-table-scroll"><table class="ti-table"><thead><tr><th>แถว</th><th>รายการยา</th><th>สถานะ / รายละเอียด</th><th>ตรวจดู</th></tr></thead><tbody></tbody></table></div>
    <div class="ti-paging"><span></span><button type="button" class="ti-prev ti-secondary">ก่อนหน้า</button><button type="button" class="ti-next ti-secondary">ถัดไป</button></div>
    <div class="ti-detail" hidden><div class="ti-head"><strong>เปรียบเทียบข้อมูล</strong><button type="button" class="ti-close ti-secondary">ปิด</button></div><div class="ti-comparison"><div><strong>ข้อมูลเดิม</strong><pre class="ti-old"></pre></div><div><strong>ข้อมูลที่จะบันทึก</strong><pre class="ti-new"></pre></div></div></div>
    <p class="ti-blocked" hidden></p>
    <label class="ti-ack-wrap" hidden><input type="checkbox" class="ti-ack"> รับทราบคำเตือนและตรวจสอบข้อมูลแล้ว</label>
    <label class="ti-replace-ack-wrap" hidden><input type="checkbox" class="ti-replace-ack"> ยืนยันจำนวนราคากลางที่จะถูกนำออกตามสรุป</label>
    <div class="ti-actions ti-bottom"><a class="ti-download ti-secondary" href="#" hidden>ดาวน์โหลดคำเตือน/ข้อผิดพลาด CSV</a><button type="button" class="ti-cancel ti-secondary">ยกเลิก</button><button type="button" class="ti-confirm ti-primary" disabled>ยืนยันนำเข้า</button></div>
  </section>
  <div class="ti-result" hidden><p role="status"></p><button type="button" class="ti-secondary ti-refresh">รีเฟรชรายการยาและราคากลาง</button></div>
</div>
