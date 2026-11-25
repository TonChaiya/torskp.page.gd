<?php
declare(strict_types=1);
require_once __DIR__.'/core/config.php';
require_once __DIR__.'/core/auth.php';
requireAdmin();
require_once __DIR__.'/core/document_snapshot.php';
header('Cache-Control: private, no-store');
function h($value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$message='';$error='';$audit=null;
try{
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        if(($_POST['action']??'')!=='backfill'||($_POST['backup_ack']??'')!=='1')throw new RuntimeException('กรุณารับทราบและสำรองฐานข้อมูลก่อนบันทึก');
        verifyCsrf();
        $count=torSnapshotBackfill(db(),(string)($_POST['snapshot_hash']??''));
        $message='เติมคุณลักษณะจาก TOR ที่บันทึกแล้ว '.$count.' รายการ โดยไม่แก้ประวัติ TOR หรือชื่อ หน่วย จำนวน ราคาเดิม';
    }
    $audit=torSnapshotAudit(db());
}catch(Throwable $e){error_log('Snapshot review: '.$e->getMessage());$error=$e instanceof PDOException?'ไม่สามารถตรวจฐานข้อมูลได้ กรุณาตรวจสอบการเชื่อมต่อและ schema':$e->getMessage();try{$audit=torSnapshotAudit(db());}catch(Throwable $ignored){}}
?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ตรวจ Snapshot เอกสาร · TOR SKP</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#19364d;font:15px system-ui,Tahoma,sans-serif}main{max-width:1120px;margin:auto;padding:24px}header{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}h1{font-size:23px;margin:6px 0}h2{font-size:17px;margin:20px 0 10px}small,p{line-height:1.55}.summary{display:flex;flex-wrap:wrap;gap:10px 25px;border-block:1px solid #d7e2eb;padding:14px 0;margin:16px 0}.summary b{font-size:19px;display:block}.btn,button{display:inline-block;background:#176a9e;color:white;text-decoration:none;border:0;border-radius:6px;padding:9px 14px;font:inherit;cursor:pointer}button:disabled{opacity:.45;cursor:not-allowed}.back{background:white;color:#19577c;border:1px solid #c7d7e3}.note{padding:12px 14px;background:#fff;border:1px solid #d7e2eb;border-radius:6px}.warn{color:#805100}.bad{color:#a02323}.good{color:#17633e}.scroll{overflow:auto;max-height:430px;background:white;border:1px solid #d7e2eb;border-radius:6px}table{width:100%;border-collapse:collapse;text-align:left}th,td{padding:9px 10px;border-bottom:1px solid #e2e8ee;vertical-align:top}th{background:#edf4f9;position:sticky;top:0;white-space:nowrap}pre{white-space:pre-wrap;overflow-wrap:anywhere;font:inherit;max-width:580px;margin:8px 0}details summary{cursor:pointer;color:#176a9e}form{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:14px}a{color:#176a9e}</style></head><body><main>
<header><div><small>TOR SKP · v1.3.0 · สำหรับผู้ดูแล</small><h1>ตรวจ Snapshot เอกสารก่อนล้างคลังยา</h1></div><a class="btn back" href="admin.php#medicine-catalog">กลับหน้าจัดการยา</a></header>
<p>ตรวจข้อมูลที่บันทึกในหัวข้อ 1 และ TOR เท่านั้น ไม่ใช้ข้อมูลคลังปัจจุบันมาเดาข้อมูลย้อนหลัง ไม่แก้ไขข้อมูลจนกว่าจะกดยืนยัน</p>
<?php if($message):?><p class="note good"><?=h($message)?></p><?php endif;?>
<?php if($error):?><p class="note bad" role="alert"><?=h($error)?></p><?php endif;?>
<?php if($audit):?>
<div class="summary"><?php foreach(['ชุดจัดซื้อ'=>$audit['cases'],'ประวัติ TOR'=>$audit['histories'],'รายการหัวข้อ 1'=>$audit['case_items'],'รายการ TOR'=>$audit['history_items'],'เติมได้'=>count($audit['recoverable']),'ต้องตรวจ (ขัดขวางการล้าง)'=>count($audit['issues']),'หมายเหตุ (ไม่ขัดขวาง)'=>count($audit['deferred'])] as $label=>$n):?><div><small><?=h($label)?></small><b><?=number_format($n)?></b></div><?php endforeach;?></div>
<?php if(!$audit['issues']&&!$audit['recoverable']):?><p class="note good">ผ่านเงื่อนไข Snapshot สำหรับล้างคลังยา (ยังต้องยืนยันการล้างที่หน้า Admin และตรวจการใช้งานจริงก่อน)</p><?php endif;?>
<?php if($audit['recoverable']):?><h2>คุณลักษณะที่กู้ได้จาก TOR เดิม</h2><p>ตรวจข้อมูลก่อนยืนยัน ระบบจะเพิ่มเฉพาะคุณลักษณะที่ขาดและที่มาของข้อมูลใน JSON หัวข้อ 1 ไม่เปลี่ยนข้อมูลเอกสาร TOR และไม่อ้างว่าเป็นคุณลักษณะ ณ วันที่ขออนุมัติหลักการ</p><div class="scroll"><table><thead><tr><th>ชุด / แถว</th><th>ชื่อยา / หน่วย</th><th>แหล่งข้อมูล</th></tr></thead><tbody><?php foreach($audit['recoverable'] as $r):?><tr><td><?=h($r['case_id'])?> / <?=h($r['row'])?></td><td><?=h($r['name'])?> · <?=h($r['unit'])?><details><summary>ดูคุณลักษณะที่จะเติม</summary><pre><?=h($r['specification'])?></pre></details></td><td>TOR <?=h($r['history_id'])?> แถว <?=h($r['history_row'])?></td></tr><?php endforeach;?></tbody></table></div>
<form method="post"><input type="hidden" name="action" value="backfill"><input type="hidden" name="csrf_token" value="<?=h(csrfToken())?>"><input type="hidden" name="snapshot_hash" value="<?=h($audit['hash'])?>"><label><input type="checkbox" name="backup_ack" value="1" required> ตรวจข้อมูลและสำรองฐานแล้ว</label><button type="submit">ยืนยันเติม Snapshot ที่ตรวจได้</button></form><?php endif;?>
<?php foreach(['issues'=>'รายการที่ยังขัดขวางการล้าง','deferred'=>'หมายเหตุ: หัวข้อ 1 ที่ TOR เดิมมีรายการต่างกัน'] as $key=>$title):if(!$audit[$key])continue;?><h2 class="<?=$key==='issues'?'bad':'warn'?>"><?=h($title)?></h2><div class="scroll"><table><thead><tr><th>ตาราง</th><th>ID / แถว</th><th>เหตุผล</th></tr></thead><tbody><?php foreach($audit[$key] as $r):?><tr><td><?=h($r['table'])?></td><td><?=h($r['id'])?> / <?=h($r['row']?:'–')?><?php if($r['table']==='procurement_cases'):?> · <a href="principle_approval_memo.php?case_id=<?=h($r['id'])?>">เปิดหัวข้อ 1</a><?php endif;?></td><td><?=h($r['reason'])?></td></tr><?php endforeach;?></tbody></table></div><?php endforeach;?>
<p class="note">รายการที่กู้ไม่ได้ไม่ถูกเขียนทับ หาก TOR ที่บันทึกสมบูรณ์ หัวข้อ 1 ยังใช้ชื่อ หน่วย และจำนวนเดิมได้ หากภายหลังต้องสร้าง TOR ใหม่จากหัวข้อ 1 ให้เลือกยาใหม่จากคลังอย่างตั้งใจ ไม่ใช่เปลี่ยนเอกสารย้อนหลังโดยอัตโนมัติ</p>
<?php endif;?></main></body></html>
