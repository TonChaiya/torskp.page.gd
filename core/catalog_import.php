<?php
declare(strict_types=1);
require_once __DIR__.'/medicine_import.php';
require_once __DIR__.'/price_reference_import.php';

const TOR_IMPORT_MAX_ROWS = 50000;
const TOR_IMPORT_TTL = 7200;
const TOR_IMPORT_STATUSES = ['new','update','same','warning','error','duplicate','conflict'];
function torChars(string $s): int { return preg_match_all('/./us',$s); }
function torImportJson(mixed $v): string { return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
function torImportWrite(string $path,array $v): void {
    $temp = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
    $data = torImportJson($v);
    try {
        if (file_put_contents($temp,$data) !== strlen($data)) throw new RuntimeException('เขียนข้อมูลพักไม่สำเร็จ');
        @chmod($temp,0600);
        if (!rename($temp,$path)) throw new RuntimeException('บันทึกสถานะไม่สำเร็จ');
    } finally { if (is_file($temp)) @unlink($temp); }
}
function torImportMeta(string $dir): array { $raw = @file_get_contents($dir.'/meta.json'); if ($raw===false) throw new RuntimeException('ไม่พบงานนำเข้า'); return json_decode($raw,true,512,JSON_THROW_ON_ERROR); }
function torImportRows(string $dir): Generator {
    $h = fopen($dir.'/rows.jsonl','rb'); if (!$h) throw new RuntimeException('ไม่พบข้อมูล Preview');
    try { while (($line=fgets($h))!==false) yield json_decode($line,true,512,JSON_THROW_ON_ERROR); if (!feof($h)) throw new RuntimeException('อ่านข้อมูลพักไม่สำเร็จ'); } finally { fclose($h); }
}
function torImportClean(string $dir): void {
    foreach (glob($dir.'/*') ?: [] as $f) if (is_file($f) && !is_link($f)) @unlink($f);
    @rmdir($dir);
}
function torImportSweep(): void {
    foreach (glob(appTempDir().'/tor_import_v2_*',GLOB_ONLYDIR) ?: [] as $d) {
        if (is_link($d) || time()-(int)@filemtime($d)<TOR_IMPORT_TTL) continue;
        $lock=@fopen($d.'/lock','c');
        if ($lock) { if (flock($lock,LOCK_EX|LOCK_NB)) { torImportClean($d); flock($lock,LOCK_UN); } fclose($lock); }
    }
}
function torImportKind(string $kind): string { if (!in_array($kind,['medicines','prices'],true)) throw new RuntimeException('ประเภทข้อมูลไม่ถูกต้อง'); return $kind; }
function torImportTable(string $kind): string { return torImportKind($kind)==='medicines'?'medicines':'medicine_price_references'; }
function torImportHeader(array $c,string $kind): bool {
    $c=array_map(static fn($s)=>trim(preg_replace('/\s+/u',' ',(string)$s)??''),$c);
    if ($kind==='medicines') return in_array($c[0]??'',['ชื่อยา','รายการยา'],true) && in_array($c[1]??'',['หน่วย','หน่วยนับ','Unit'],true) && in_array($c[2]??'',['คุณสมบัติยา','คุณลักษณะยา','คุณลักษณะเฉพาะ','คุณสมบัติ'],true) && count(array_filter(array_slice($c,3),static fn($s)=>$s!==''))===0;
    return in_array($c[0]??'',['รายการยา','ชื่อยา'],true) && in_array($c[1]??'',['รูปแบบยาและความแรง','รูปแบบและความแรง'],true) && in_array($c[2]??'',['หน่วย','หน่วยนับ','Unit'],true) && in_array($c[3]??'',['ราคากลางยาต่อหน่วย (บาท)','ราคากลางยาต่อหน่วย','ราคากลาง','ราคากลาง (บาท)'],true) && count(array_filter(array_slice($c,4),static fn($s)=>$s!==''))===0;
}
// InnoDB is required for atomic commit/rollback. Use the exact collation/unique keys of v1.1.0.
function torImportSchema(PDO $db,string $kind): void {
    $table=torImportTable($kind); $s=$db->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);
    if (strtoupper((string)$s->fetchColumn())!=='INNODB') throw new RuntimeException('Import ต้องใช้ตาราง InnoDB เพื่อรองรับ Rollback');
    $keys=$kind==='medicines'?['name','unit']:['medicine_name','dosage_form_strength','unit'];
    $s=$db->prepare('SELECT COLUMN_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);$cols=$s->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($keys as $k) if (($cols[$k]??'')!=='utf8mb4_unicode_ci') throw new RuntimeException('Collation ของตารางไม่ตรง baseline v1.1.0 กรุณาตรวจ schema');
    $s=$db->prepare('SELECT INDEX_NAME,COLUMN_NAME,SEQ_IN_INDEX FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND NON_UNIQUE=0 ORDER BY INDEX_NAME,SEQ_IN_INDEX');$s->execute([$table]);$indices=[];foreach($s as $r)$indices[$r['INDEX_NAME']][]=$r['COLUMN_NAME'];
    if (!in_array($kind==='medicines'?['name']:$keys,$indices,true) || count($indices)!==2) throw new RuntimeException('Unique Key ไม่ตรง baseline v1.1.0 กรุณาตรวจ schema');
}
// Fingerprint entire target table. At confirm, lock the primary-index range under REPEATABLE READ.
// No actual data is sent to the browser except matching rows and summary.
function torImportSnapshot(PDO $db,string $kind,bool $lock=false): array {
    $table=torImportTable($kind);
    $fields=$kind==='medicines'?['name','unit','specification']:['medicine_name','dosage_form_strength','unit','reference_price','source_file'];
    $expressions=array_map(static fn($f)=>"COALESCE(HEX(CAST(`$f` AS CHAR)), 'NULL')",$fields);
    $sql="SELECT id, SHA2(CONCAT_WS('|',".implode(',',$expressions)."),256) AS sig FROM `$table` FORCE INDEX (PRIMARY) ORDER BY id".($lock?' FOR UPDATE':'');
    $s=$db->query($sql);$ctx=hash_init('sha256');$ids=[];
    while($r=$s->fetch(PDO::FETCH_ASSOC)){ $ids[]=(int)$r['id'];hash_update($ctx,$r['id'].':'.$r['sig']."\n"); }
    $s->closeCursor();return ['hash'=>hash_final($ctx),'ids'=>$ids];
}
// Batch matching via database collation, not PHP lower-case approximations.
function torImportMatch(PDO $db,string $kind,array $batch): array {
    if (!$batch) return [];
    $price=$kind==='prices';$args=[];$parts=[];
    foreach($batch as $r){$n=$r['new'];$parts[]='SELECT ? AS row_no, CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS name, CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS unit'.($price?', CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AS strength':'');array_push($args,$r['row'],$n['name'],$n['unit']);if($price)$args[]=$n['strength'];}
    $weights="HEX(WEIGHT_STRING(i.name AS CHAR(255)))".($price?",HEX(WEIGHT_STRING(i.strength AS CHAR(255))),HEX(WEIGHT_STRING(i.unit AS CHAR(100)))":'');
    $key="SHA2(CONCAT_WS('|',$weights),256) AS key_hash";
    $from=' FROM ('.implode(' UNION ALL ',$parts).') i ';
    $sql=$price?"SELECT i.row_no,$key,m.id,m.medicine_name AS name,m.dosage_form_strength AS strength,m.unit,CAST(m.reference_price AS CHAR) AS price,m.source_file $from LEFT JOIN medicine_price_references m ON m.medicine_name=i.name AND m.dosage_form_strength=i.strength AND m.unit=i.unit":"SELECT i.row_no,$key,m.id,m.name,m.unit,m.specification $from LEFT JOIN medicines m ON m.name=i.name";
    $s=$db->prepare($sql);$s->execute($args);$out=[];while($r=$s->fetch(PDO::FETCH_ASSOC)){$row=(int)$r['row_no'];$hash=$r['key_hash'];if (!is_string($hash)||strlen($hash)!==64||isset($out[$row])) throw new RuntimeException('ไม่สามารถตรวจ Unique Key ได้');unset($r['row_no'],$r['key_hash']);$out[$row]=['key'=>$hash,'old'=>$r['id']===null?null:$r];} $s->closeCursor();
    if(count($out)!==count($batch))throw new RuntimeException('ตรวจรายการในฐานข้อมูลไม่ครบ');return $out;
}
function torImportPrepare(PDO $db,string $dir,array $meta,array $file): array {
    torImportSchema($db,$meta['kind']);$kind=$meta['kind'];$before=torImportSnapshot($db,$kind);$meta['snapshot']=$before['hash'];$meta['existing']=count($before['ids']);unset($before);
    $h=fopen($dir.'/rows.jsonl','xb');if(!$h)throw new RuntimeException('สร้างข้อมูลพักไม่สำเร็จ');@chmod($dir.'/rows.jsonl',0600);
    $seen=[];$matched=[];$batch=[];$bytes=0;$stageBytes=0;$header=false;$total=0;
    $flush=function()use($db,$kind,$h,&$meta,&$seen,&$matched,&$batch,&$bytes,&$stageBytes,$dir):void{
        $valid=array_values(array_filter($batch,static fn($r)=>!$r['issues']));$matches=torImportMatch($db,$kind,$valid);
        foreach($batch as $r){
            $r['old']=null;$r['status']='error';$r['message']=implode('; ',$r['issues']);
            if(!$r['issues']){
                $match=$matches[$r['row']];$r['old']=$match['old'];$key=$match['key'];
                if(isset($seen[$key])){$r['status']='duplicate';$r['message']='ซ้ำกับแถว '.$seen[$key];}
                else{
                    $seen[$key]=$r['row'];
                    [$r['status'],$r['message'],$r['new']]=$kind==='medicines'?torMedicineDecision($r['new'],$r['old']):torPriceDecision($r['new'],$r['old']);
                    if($r['old']!==null)$matched[(int)$r['old']['id']]=true;
                }
            }
            unset($r['issues']);$meta['counts'][$r['status']]++;$meta['processed']++;
            $line=torImportJson($r)."\n";$stageBytes+=strlen($line);if($stageBytes>100*1024*1024)throw new RuntimeException('ข้อมูล Preview เกิน 100 MB กรุณาแบ่งไฟล์');if(fwrite($h,$line)!==strlen($line))throw new RuntimeException('พื้นที่พักข้อมูลไม่เพียงพอ');
        }
        $batch=[];$bytes=0;torImportWrite($dir.'/meta.json',$meta);
    };
    try{
        foreach(fileRows($file['tmp_name'],$file['name']) as $number=>$entry){
            $cells=$entry['cells'];$issues=$entry['issues'];
            if(!array_filter($cells,static fn($v)=>$v!=='')&&!$issues)continue;
            if(!$header){
                if($issues||!torImportHeader($cells,$kind)) {
                    $shown=array_map(static fn($v)=>$v===''?'[ว่าง]':(preg_match('/^.{0,100}/us',(string)$v,$m)?$m[0]:'[อ่านไม่ได้]'),array_slice($cells,0,4));
                    $expected=$kind==='medicines'?'ชื่อยา | หน่วย | คุณสมบัติยา':'รายการยา | รูปแบบยาและความแรง | หน่วย | ราคากลางยาต่อหน่วย (บาท)';
                    throw new RuntimeException('หัวคอลัมน์ไม่ตรงประเภท ที่แถว '.$number.' (อ่านได้: '.implode(' | ',$shown).') ต้องเป็น: '.$expected.($issues?' — '.implode('; ',$issues):''));
                }
                $header=true;continue;
            }
            if(++$total>TOR_IMPORT_MAX_ROWS)throw new RuntimeException('รองรับสูงสุด 50,000 แถวต่อไฟล์ กรุณาแบ่งไฟล์');
            $max=$kind==='medicines'?3:4;
            if(array_filter(array_slice($cells,$max),static fn($v)=>$v!==''))$issues[]='พบข้อมูลนอกคอลัมน์ที่รองรับ';
            [$new,$errs]=$kind==='medicines'?torMedicineRow($cells):torPriceRow($cells);$issues=array_merge($issues,$errs);
            // Bound malformed cells in preview (never truncate accepted values).
            if($issues)foreach($new as &$v)if(strlen($v)>65535)$v='[ข้อความยาวเกินขนาด]';unset($v);
            $r=['row'=>(int)$number,'new'=>$new,'issues'=>$issues];$batch[]=$r;$bytes+=strlen(torImportJson($r));
            if(count($batch)>=100||$bytes>=256*1024)$flush();
        }
        if($batch)$flush();if(!$header||!$total)throw new RuntimeException('ไม่พบรายการสำหรับนำเข้า');
        if(torImportSnapshot($db,$kind)['hash']!==$meta['snapshot'])throw new RuntimeException('ข้อมูลในฐานเปลี่ยนระหว่างตรวจสอบ กรุณาตรวจไฟล์ใหม่');
        $meta['total']=$total;$meta['remove']=$meta['mode']==='replace'?$meta['existing']-count($matched):0;
        $meta['blocked']=$meta['counts']['error']+$meta['counts']['duplicate']+$meta['counts']['conflict'];$meta['status']='ready';
        return $meta;
    }finally{fclose($h);}
}
function torImportApply(PDO $db,string $dir,array $meta,bool $ack, bool $replaceAck=false): array {
    if($meta['status']!=='ready'||$meta['blocked']>0||($meta['counts']['warning']>0&&!$ack)||($meta['mode']==='replace'&&!$replaceAck))throw new RuntimeException('ยังไม่สามารถยืนยันได้ กรุณาแก้ข้อผิดพลาดหรือรับทราบคำเตือน');
    torImportSchema($db,$meta['kind']);$kind=$meta['kind'];$meta['status']='applying';$meta['processed']=0;torImportWrite($dir.'/meta.json',$meta);
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');if(!$db->beginTransaction())throw new RuntimeException('เริ่ม Transaction ไม่สำเร็จ');$batch=[];$bytes=0;$keep=[];$changed=0;$processed=0;$removed=0;
    $flush=function()use($db,$kind,$meta,&$batch,&$bytes,&$changed):void{
        if(!$batch)return;$args=[];$parts=[];
        foreach($batch as $r){$n=$r['new'];$o=$r['old'];if($kind==='medicines'){$parts[]='(?,?,?)';array_push($args,$o['name']??$n['name'],$o['unit']??$n['unit'],$n['specification']);}else{$parts[]='(?,?,?,?,?)';array_push($args,$o['name']??$n['name'],$o['strength']??$n['strength'],$o['unit']??$n['unit'],$n['price'],$meta['filename']);}}
        $sql=$kind==='medicines'?'INSERT INTO medicines (name,unit,specification) VALUES '.implode(',',$parts).' ON DUPLICATE KEY UPDATE specification=VALUES(specification)':'INSERT INTO medicine_price_references (medicine_name,dosage_form_strength,unit,reference_price,source_file) VALUES '.implode(',',$parts).' ON DUPLICATE KEY UPDATE reference_price=VALUES(reference_price),source_file=VALUES(source_file)';
        $s=$db->prepare($sql);$s->execute($args);$changed+=count($batch);$batch=[];$bytes=0;
    };
    try{
        $current=torImportSnapshot($db,$kind,true);if(!hash_equals($meta['snapshot'],$current['hash']))throw new RuntimeException('ข้อมูลในฐานเปลี่ยนหลัง Preview กรุณายกเลิกและตรวจไฟล์ใหม่');
        foreach(torImportRows($dir) as $r){
            if(!in_array($r['status'],['new','update','same','warning'],true))throw new RuntimeException('ข้อมูลพักไม่ผ่านการตรวจสอบ');
            if($r['old']!==null)$keep[(int)$r['old']['id']]=true;
            $skip=$r['status']==='same'||($r['status']==='warning'&&$r['old']!==null&&($kind==='medicines'?$r['old']['specification']===$r['new']['specification']:$r['old']['price']===$r['new']['price']));
            if(!$skip){$batch[]=$r;$bytes+=strlen(torImportJson($r['new']));if(count($batch)>=100||$bytes>=256*1024)$flush();}
            if(++$processed%100===0){$meta['processed']=$processed;torImportWrite($dir.'/meta.json',$meta);}
        }
        $flush();if($processed!==$meta['total'])throw new RuntimeException('จำนวนข้อมูลพักไม่ตรงกับ Preview');
        if($meta['mode']==='replace'){
            if($kind!=='prices')throw new RuntimeException('ไม่รองรับการแทนที่รายการยา');
            $remove=array_values(array_filter($current['ids'],static fn($id)=>!isset($keep[$id])));if(count($remove)!==$meta['remove'])throw new RuntimeException('จำนวนรายการที่จะลบเปลี่ยนไป');
            foreach(array_chunk($remove,200) as $ids){$s=$db->prepare('DELETE FROM medicine_price_references WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$s->execute($ids);$removed+=$s->rowCount();}
            if($removed!==count($remove))throw new RuntimeException('จำนวนรายการที่ลบไม่ตรง Preview');
        }
        if(!$db->commit())throw new RuntimeException('ยืนยันผล Commit ไม่สำเร็จ กรุณาตรวจจำนวนข้อมูลจริงก่อนลองใหม่');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    $meta['status']='done';$meta['processed']=$processed;$meta['changed']=$changed;$meta['removed']=$removed;return $meta;
}
// Explicit annual reset: only the reference catalog, never the medicine catalog or document history.
function torClearPricePreview(PDO $db): array {
    torImportSchema($db, 'prices');
    $snapshot = torImportSnapshot($db, 'prices');
    return ['count'=>count($snapshot['ids']), 'hash'=>$snapshot['hash']];
}
function torClearPriceReferences(PDO $db, string $hash, int $expected, string $phrase, bool $backupAck): int {
    if (!$backupAck || $phrase !== 'ล้างราคากลาง' || $expected < 1 || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
        throw new RuntimeException('กรุณารับทราบการสำรองข้อมูลและพิมพ์คำยืนยันให้ถูกต้อง');
    }
    torImportSchema($db, 'prices');
    // Refuse unexpected inbound foreign keys rather than risking cascade/SET NULL in another table.
    $fk = $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='medicine_price_references'");
    if ((int)$fk->fetchColumn() !== 0) throw new RuntimeException('พบตารางที่อ้างอิงราคากลาง กรุณาตรวจสอบฐานข้อมูลก่อนล้าง');
    $fk->closeCursor();
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    if (!$db->beginTransaction()) throw new RuntimeException('เริ่ม Transaction ไม่สำเร็จ');
    try {
        $current = torImportSnapshot($db, 'prices', true);
        if (count($current['ids']) !== $expected || !hash_equals($hash, $current['hash'])) {
            throw new RuntimeException('ข้อมูลราคากลางเปลี่ยนไป กรุณาเปิดหน้าต่างล้างข้อมูลใหม่');
        }
        $removed = 0;
        foreach (array_chunk($current['ids'], 200) as $ids) {
            $stmt = $db->prepare('DELETE FROM medicine_price_references WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
            $stmt->execute($ids);
            $removed += $stmt->rowCount();
        }
        if ($removed !== $expected) throw new RuntimeException('จำนวนที่ลบไม่ตรงกับที่ยืนยัน');
        if (!$db->commit()) throw new RuntimeException('ยืนยันผล Commit ไม่สำเร็จ กรุณาตรวจจำนวนข้อมูลจริงก่อนลองใหม่');
        return $removed;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
function torImportPublic(array $meta): array { unset($meta['owner'],$meta['snapshot']);return $meta; }
function torImportCsvCell(string $s): string { return preg_match('/^[\s]*[=+\-@\t\r]/u',$s)?"'".$s:$s; }
function torImportEndpoint(PDO $db): void {
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
    // requireAdmin already ran and verified CSRF for all POST requests.
    if(empty($_SESSION['tor_import_owner']))$_SESSION['tor_import_owner']=bin2hex(random_bytes(32));
    $owner=hash('sha256',(string)$_SESSION['user_id'].':'.$_SESSION['tor_import_owner']);session_write_close();
    $action=substr((string)($_GET['action']??''),10);$method=$_SERVER['REQUEST_METHOD']??'GET';$id=(string)($_GET['id']??$_POST['id']??'');$lock=null;$locked=false;$dir='';$meta=null;$created=false;
    try{
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('รหัสงานนำเข้าไม่ถูกต้อง');
        if(!in_array($action,['prepare','confirm','cancel','status','page','detail','errors'],true)||($method!==(in_array($action,['prepare','confirm','cancel'],true)?'POST':'GET')))throw new RuntimeException('คำขอไม่ถูกต้อง');
        $dir=appTempDir().'/tor_import_v2_'.$id;
        if($action==='prepare'){
            torImportSweep();$kind=torImportKind((string)($_POST['kind']??''));$mode=(string)($_POST['mode']??'merge');
            if(!in_array($mode,['merge','replace'],true)||($mode==='replace'&&$kind!=='prices'))throw new RuntimeException('โหมดนำเข้าไม่ถูกต้อง');
            $file=$_FILES['file']??[];if(($file['error']??-1)!==UPLOAD_ERR_OK)throw new RuntimeException('อัปโหลดไฟล์ไม่สำเร็จ ตรวจ upload_max_filesize และ post_max_size');
            validateUploadedSpreadsheet($file);
            $filename=basename(str_replace('\\','/',(string)$file['name']));if(!preg_match('//u',$filename)||torChars($filename)>255)throw new RuntimeException('ชื่อไฟล์ไม่ถูกต้องหรือยาวเกิน 255 ตัวอักษร');
            if(!mkdir($dir,0700))throw new RuntimeException('ไม่สามารถสร้างงานนำเข้าใหม่');$created=true;
            $lock=fopen($dir.'/lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('ไม่สามารถล็อกงานนำเข้า');$locked=true;
            $meta=['owner'=>$owner,'kind'=>$kind,'mode'=>$mode,'filename'=>$filename,'status'=>'checking','created'=>time(),'processed'=>0,'total'=>0,'counts'=>array_fill_keys(TOR_IMPORT_STATUSES,0)];torImportWrite($dir.'/meta.json',$meta);
            $meta=torImportPrepare($db,$dir,$meta,$file);torImportWrite($dir.'/meta.json',$meta);echo torImportJson(['ok'=>true,'meta'=>torImportPublic($meta)]);return;
        }
        if($action==='status'&&!is_file($dir.'/meta.json')){echo torImportJson(['ok'=>true,'meta'=>['status'=>'waiting','processed'=>0]]);return;}
        $meta=torImportMeta($dir);if(!hash_equals($meta['owner'],$owner))throw new RuntimeException('ไม่พบงานนำเข้าของคุณ');
        if(time()-$meta['created']>TOR_IMPORT_TTL)throw new RuntimeException('Preview หมดอายุ กรุณาตรวจไฟล์ใหม่');
        if($action==='status'){echo torImportJson(['ok'=>true,'meta'=>torImportPublic($meta)]);return;}
        $lock=fopen($dir.'/lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('งานกำลังประมวลผล กรุณารอจนเสร็จ');$locked=true;
        $meta=torImportMeta($dir);if(!hash_equals($meta['owner'],$owner))throw new RuntimeException('ไม่พบงานนำเข้าของคุณ');
        if($action==='cancel'){if($meta['status']==='applying')throw new RuntimeException('สถานะบันทึกไม่แน่นอน กรุณาตรวจฐานก่อนเริ่มใหม่');torImportClean($dir);echo torImportJson(['ok'=>true]);return;}
        if($action==='confirm'){
            $meta=torImportApply($db,$dir,$meta,($_POST['ack']??'')==='1',($_POST['replace_ack']??'')==='1');
            // A metadata write failure after commit must not imply rollback or enable retry.
            try{torImportWrite($dir.'/meta.json',$meta);}catch(Throwable $e){error_log('TOR import commit succeeded but status write failed');}
            echo torImportJson(['ok'=>true,'meta'=>torImportPublic($meta)]);return;
        }
        if($meta['status']!=='ready')throw new RuntimeException('Preview ยังไม่พร้อม');
        if($action==='errors'){
            header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="tor-import-errors.csv"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['แถว','ชื่อยา','สถานะ','รายละเอียด'],',','"','');
            foreach(torImportRows($dir) as $r)if(in_array($r['status'],['warning','error','duplicate','conflict'],true))fputcsv($out,array_map('torImportCsvCell',[(string)$r['row'],$r['new']['name'],$r['status'],$r['message']]),',','"','');fclose($out);return;
        }
        if($action==='detail'){foreach(torImportRows($dir) as $r)if($r['row']===(int)($_GET['row']??0)){echo torImportJson(['ok'=>true,'row'=>$r]);return;}throw new RuntimeException('ไม่พบแถว');}
        $page=max(1,min(2000,(int)($_GET['page']??1)));$size=(int)($_GET['size']??25);if(!in_array($size,[25,50,100],true))$size=25;$status=(string)($_GET['filter']??'all');$q=trim((string)($_GET['q']??''));if(!preg_match('//u',$q)||torChars($q)>120)throw new RuntimeException('คำค้นยาวเกินกำหนด');$q=function_exists('mb_strtolower')?mb_strtolower($q,'UTF-8'):strtolower($q);
        $total=0;$rows=[];foreach(torImportRows($dir) as $r){if($status!=='all'&&$r['status']!==$status)continue;$hay=implode(' ',[$r['new']['name'],$r['new']['strength']??'',$r['new']['unit']]);$hay=function_exists('mb_strtolower')?mb_strtolower($hay,'UTF-8'):strtolower($hay);if($q!==''&&!str_contains($hay,$q))continue;if($total>=($page-1)*$size&&count($rows)<$size){unset($r['old'],$r['new']['specification']);$rows[]=$r;}$total++;}
        echo torImportJson(['ok'=>true,'rows'=>$rows,'total'=>$total,'page'=>$page,'size'=>$size]);
    }catch(Throwable $e){
        // Fail closed. Never report a committed import as rolled back. An applying job cannot be replayed.
        $msg=$e instanceof PDOException?'ฐานข้อมูลไม่สามารถประมวลผลได้ กรุณาตรวจ schema/ข้อจำกัด Hosting แล้วลองใหม่':($e instanceof RuntimeException?$e->getMessage():'ไม่สามารถประมวลผลได้ กรุณาตรวจ log ของ Server');
        if($e instanceof PDOException)error_log('TOR import PDO error: '.$e->getCode());
        if($created&&$meta!==null){$meta['status']='error';$meta['message']=$msg;try{torImportWrite($dir.'/meta.json',$meta);}catch(Throwable $ignore){}if(is_file($dir.'/rows.jsonl'))@unlink($dir.'/rows.jsonl');}
        if($action==='confirm'&&$locked&&$meta!==null&&($meta['owner']??'')===$owner){try{$saved=torImportMeta($dir);if($saved['status']==='applying'){$saved['status']='error';$saved['message']=$msg;torImportWrite($dir.'/meta.json',$saved);}}catch(Throwable $ignore){}}
        http_response_code(400);echo torImportJson(['ok'=>false,'error'=>$msg]);
    }finally{if(is_resource($lock)){if($locked)flock($lock,LOCK_UN);fclose($lock);}}
}
