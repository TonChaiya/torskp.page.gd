<?php
declare(strict_types=1);

// Document-owned medicine data. The catalog is never used as historical evidence.
function torSnapshotItems(string $json): array
{
    $rows = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($rows) || !array_is_list($rows) || !$rows) throw new RuntimeException('ไม่พบรายการที่บันทึกไว้หรือ JSON ไม่ถูกต้อง');
    foreach ($rows as $row) if (!is_array($row)) throw new RuntimeException('รูปแบบรายการไม่ถูกต้อง');
    return $rows;
}
function torSnapshotText($value): string
{
    return is_string($value) || is_int($value) ? (string)$value : '';
}
function torSnapshotComparable($value): string
{
    // Digit glyph differences are display-only; no fuzzy name or unit matching.
    return trim(strtr(torSnapshotText($value), ['๐'=>'0','๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4','๕'=>'5','๖'=>'6','๗'=>'7','๘'=>'8','๙'=>'9']));
}
function torSnapshotHasIdentity(array $row): bool
{
    return trim(torSnapshotText($row['name'] ?? null)) !== '' && trim(torSnapshotText($row['unit'] ?? null)) !== '';
}
function torSnapshotHasSpec(array $row): bool
{
    return isset($row['specification']) && is_string($row['specification']) && trim($row['specification']) !== '';
}
function torSnapshotNumber($value): bool
{
    if (!is_int($value) && !is_float($value) && !is_string($value)) return false;
    $v = str_replace(',', '', torSnapshotComparable((string)$value));
    return is_numeric($v) && is_finite((float)$v) && (float)$v >= 0;
}
function torSnapshotHistoryProblem(array $row): string
{
    if (!torSnapshotHasIdentity($row) || !torSnapshotHasSpec($row)) return 'ชื่อ หน่วย หรือคุณลักษณะใน TOR ไม่ครบ';
    foreach (['quantity','reference_price','offer_price','offer_total'] as $field) {
        if (!array_key_exists($field,$row) || !torSnapshotNumber($row[$field])) return 'ข้อมูลจำนวน/ราคาเดิมไม่ครบ: '.$field;
    }
    foreach (['requested_quantity','package_size','package_count'] as $field) {
        if (array_key_exists($field,$row) && !torSnapshotNumber($row[$field])) return 'ข้อมูลขนาดบรรจุไม่ถูกต้อง: '.$field;
    }
    $pkg=(float)($row['package_size']??0);
    if ($pkg>0 && (!isset($row['requested_quantity'],$row['package_count']) || abs((float)$row['requested_quantity']/$pkg-(float)$row['quantity'])>1e-8 || (float)$row['quantity']!==(float)$row['package_count'])) return 'ข้อมูลจำนวนกล่องใน TOR ไม่สอดคล้องกัน';
    if (abs((float)$row['quantity']*(float)$row['offer_price']-(float)$row['offer_total'])>max(0.000001,abs((float)$row['offer_total'])*1e-12)) return 'ยอดรวมราคาเสนอเดิมไม่สอดคล้องกับจำนวนและราคา';
    return '';
}
function torSnapshotKey(string $kind,int $owner,int $index,array $row): string
{
    return $kind.':'.$owner.':'.$index.':'.substr(hash('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),0,24);
}
function torSnapshotKeyRow(string $kind,int $owner,string $key,array $rows): ?array
{
    foreach ($rows as $i=>$row) if (hash_equals(torSnapshotKey($kind,$owner,$i,$row),$key)) return $row;
    return null;
}
function torSnapshotMedicine(array $row): array
{
    return ['id'=>(int)($row['medicine_id']??0),'name'=>torSnapshotText($row['name']??''),'unit'=>torSnapshotText($row['unit']??''),'specification'=>torSnapshotText($row['specification']??'')];
}
// Only an unambiguous match within the same case's already saved histories is evidence.
// Old histories may not have medicine_id. Never use a conflicting positive ID or a fuzzy name.
function torSnapshotRecoverCaseRow(array $row,array $histories): ?array
{
    if (!torSnapshotHasIdentity($row) || torSnapshotHasSpec($row)) return null;
    $matches=[];
    foreach ($histories as $history) {
        foreach ($history['rows'] as $index=>$item) {
            if (torSnapshotHistoryProblem($item)!=='') continue;
            if (torSnapshotComparable($row['name'])!==torSnapshotComparable($item['name']) || torSnapshotComparable($row['unit'])!==torSnapshotComparable($item['unit'])) continue;
            $a=(int)($row['medicine_id']??0); $b=(int)($item['medicine_id']??0);
            if ($a>0 && $b>0 && $a!==$b) continue;
            $matches[]=['specification'=>$item['specification'],'history_id'=>(int)$history['id'],'row'=>$index+1];
        }
    }
    if (!$matches) return null;
    $spec=$matches[0]['specification'];
    foreach ($matches as $match) if ($match['specification']!==$spec) return null;
    $row['specification']=$spec;
    $row['snapshot_source']='recovered_saved_tor';
    $row['snapshot_history_id']=$matches[0]['history_id'];
    $row['snapshot_history_row']=$matches[0]['row'];
    return $row;
}
function torSnapshotReadRows(PDO $db,string $table,string $columns,bool $lock): array
{
    // Internal fixed identifiers only. Full primary range lock is used for mutation gates.
    $allowed=['procurement_cases'=>'id, items_json','tor_histories'=>'id, case_id, items_json','procurement_documents'=>'id, case_id, document_type, extra_json','scope_price_approval_memos'=>'id, case_id'];
    if (($allowed[$table]??null)!==$columns) throw new LogicException('Invalid snapshot audit query');
    $stmt=$db->query("SELECT $columns FROM `$table` ORDER BY id".($lock?' FOR UPDATE':''));
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC); $stmt->closeCursor(); return $rows;
}
function torSnapshotAudit(PDO $db,bool $lock=false): array
{
    $cases=torSnapshotReadRows($db,'procurement_cases','id, items_json',$lock);
    $histories=torSnapshotReadRows($db,'tor_histories','id, case_id, items_json',$lock);
    $documents=torSnapshotReadRows($db,'procurement_documents','id, case_id, document_type, extra_json',$lock);
    $scope=torSnapshotReadRows($db,'scope_price_approval_memos','id, case_id',$lock);
    return torSnapshotAuditRows($cases,$histories,$documents,$scope);
}
function torSnapshotAuditRows(array $cases,array $histories,array $documents,array $scope): array
{
    $issues=[];$deferred=[];$recoverable=[];$changes=[];$caseIds=[];$historyIds=[];$completeHistories=[];$authoritative=[];$byCase=[];$caseItems=0;$historyItems=0;
    $issue=static function(string $table,int $id,int $row,string $reason) use (&$issues):void {$issues[]=['table'=>$table,'id'=>$id,'row'=>$row,'reason'=>$reason];};
    foreach($cases as $case) $caseIds[(int)$case['id']]=true;
    foreach($histories as $h){
        $id=(int)$h['id'];$cid=(int)($h['case_id']??0);$historyIds[$id]=$cid;
        if($cid>0&&!isset($caseIds[$cid])) $issue('tor_histories',$id,0,'ไม่พบชุดจัดซื้อที่อ้างอิง');
        try{$rows=torSnapshotItems((string)$h['items_json']);}catch(Throwable $e){$issue('tor_histories',$id,0,'JSON รายการ TOR ไม่สมบูรณ์');continue;}
        $historyItems+=count($rows);$byCase[$cid][]=['id'=>$id,'rows'=>$rows];
        $valid=true; foreach($rows as $i=>$row){$reason=torSnapshotHistoryProblem($row);if($reason!==''){$valid=false;$issue('tor_histories',$id,$i+1,$reason);}}
        if($valid)$completeHistories[$id]=$cid;
    }
    foreach($documents as $d){
        if(($d['document_type']??'')!=='tor_company_purchase')continue;
        $extra=json_decode((string)($d['extra_json']??''),true);
        $hid=(int)($extra['history_id']??0);$cid=(int)$d['case_id'];
        if($hid>0&&($completeHistories[$hid]??-1)===$cid)$authoritative[$cid]=true;
    }
    foreach($cases as $c){
        $id=(int)$c['id'];
        try{$rows=torSnapshotItems((string)$c['items_json']);}catch(Throwable $e){$issue('procurement_cases',$id,0,'JSON รายการหัวข้อ 1 ไม่สมบูรณ์');continue;}
        $caseItems+=count($rows);$changed=false;
        foreach($rows as $i=>$row){
            if(!torSnapshotHasIdentity($row)||!torSnapshotNumber($row['quantity']??null)){$issue('procurement_cases',$id,$i+1,'ชื่อ หน่วย หรือจำนวนในหัวข้อ 1 ไม่ครบ');continue;}
            if(torSnapshotHasSpec($row))continue;
            $recovered=torSnapshotRecoverCaseRow($row,$byCase[$id]??[]);
            if($recovered===null){
                if(isset($authoritative[$id])) {
                    // Principle itself uses saved name/unit/quantity; the completed TOR has its own independent data.
                    // Do not invent a historical specification for a different medicine in the principle.
                    $deferred[]=['table'=>'procurement_cases','id'=>$id,'row'=>$i+1,'reason'=>'หัวข้อ 1 มีชื่อ/หน่วย/จำนวนครบ และ TOR ที่บันทึกมี Snapshot ครบ แต่ไม่พบคุณลักษณะที่ตรงกัน หากสร้าง TOR ใหม่จากหัวข้อ 1 ต้องเลือกรายการจากคลังใหม่อย่างตั้งใจ'];
                } else $issue('procurement_cases',$id,$i+1,'ไม่พบคุณลักษณะจาก TOR เดิมที่ตรงกัน และไม่มี TOR สมบูรณ์ ให้ตรวจหัวข้อ 1 ด้วยตนเอง');
                continue;
            }
            $recoverable[]=['case_id'=>$id,'row'=>$i+1,'history_id'=>$recovered['snapshot_history_id'],'history_row'=>$recovered['snapshot_history_row'],'name'=>$row['name'],'unit'=>$row['unit'],'specification'=>$recovered['specification']];
            $rows[$i]=$recovered;$changed=true;
        }
        if($changed)$changes[$id]=['old'=>(string)$c['items_json'],'new'=>json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
    }
    foreach($documents as $d){
        $id=(int)$d['id'];$cid=(int)$d['case_id'];if(!isset($caseIds[$cid]))$issue('procurement_documents',$id,0,'ไม่พบชุดจัดซื้อ');
        if(!in_array($d['document_type'],['principle_approval','committee_scope_invitation','tor_company_purchase','scope_price_approval_memo','committee_scope_result_memo','meeting_report'],true))$issue('procurement_documents',$id,0,'ประเภทเอกสารที่ยังไม่ได้ตรวจสอบ');
        $raw=$d['extra_json']??null;$extra=[];
        if($raw!==null&&$raw!==''){try{$extra=json_decode((string)$raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($extra))throw new RuntimeException();}catch(Throwable $e){$issue('procurement_documents',$id,0,'extra_json ไม่สมบูรณ์');continue;}}
        $hid=(int)($extra['history_id']??0);
        if($hid>0&&(!array_key_exists($hid,$historyIds)||($historyIds[$hid]!==$cid&&$historyIds[$hid]!==0)))$issue('procurement_documents',$id,0,'History ID ไม่ตรงชุดหรือไม่พบ');
        if($d['document_type']==='tor_company_purchase'&&($hid<=0||($historyIds[$hid]??-1)!==$cid))$issue('procurement_documents',$id,0,'TOR ไม่มี history_id ที่ตรงชุด');
    }
    foreach($scope as $s)if(!isset($caseIds[(int)$s['case_id']]))$issue('scope_price_approval_memos',(int)$s['id'],0,'ไม่พบชุดจัดซื้อ');
    return ['cases'=>count($cases),'histories'=>count($histories),'case_items'=>$caseItems,'history_items'=>$historyItems,'recoverable'=>$recoverable,'deferred'=>$deferred,'issues'=>$issues,'changes'=>$changes,'hash'=>hash('sha256',json_encode([$cases,$histories,$documents,$scope],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))];
}
function torSnapshotBackfill(PDO $db,string $hash): int
{
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new RuntimeException('กรุณาตรวจสอบใหม่');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    if(!$db->beginTransaction())throw new RuntimeException('เริ่ม Transaction ไม่สำเร็จ');
    try{
        $audit=torSnapshotAudit($db,true);
        if(!hash_equals($audit['hash'],$hash))throw new RuntimeException('ข้อมูลเปลี่ยนระหว่างตรวจสอบ กรุณาเปิดตรวจใหม่');
        // Explicitly add missing metadata only. Keep document dates/timestamps and all existing fields.
        $stmt=$db->prepare('UPDATE procurement_cases SET items_json=?, updated_at=updated_at WHERE id=? AND BINARY items_json=BINARY ?');
        foreach($audit['changes'] as $id=>$change){$stmt->execute([$change['new'],$id,$change['old']]);if($stmt->rowCount()!==1)throw new RuntimeException('ไม่สามารถยืนยันข้อมูลเดิมของชุด '.$id);}
        if(!$db->commit())throw new RuntimeException('Commit ไม่สำเร็จ กรุณาตรวจฐานจริง');
        return count($audit['recoverable']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

// Build server-authoritative selection keys. Prefix distinguishes archived rows from live IDs.
// Keys include the raw row fingerprint, so a stale browser cannot silently select a changed row.
function torSnapshotSelections(array $cases,array $histories): array
{
    $map=[];$caseKeys=[];$historyKeys=[];$byCase=[];
    foreach($histories as $h){
        $id=(int)$h['id'];$cid=(int)($h['case_id']??0);
        try{$rows=torSnapshotItems((string)$h['items_json']);}catch(Throwable $e){continue;}
        $byCase[$cid][]=['id'=>$id,'rows'=>$rows];
        foreach($rows as $i=>$row){
            $key=torSnapshotKey('h',$id,$i,$row);$historyKeys[$id][$i]=$key;
            $map[$key]=torSnapshotMedicine($row)+['case_id'=>$cid,'history_id'=>$id,'source'=>'history','error'=>torSnapshotHistoryProblem($row)];
        }
    }
    foreach($cases as $c){
        $id=(int)$c['id'];
        try{$rows=torSnapshotItems((string)$c['items_json']);}catch(Throwable $e){continue;}
        foreach($rows as $i=>$row){
            $resolved=torSnapshotRecoverCaseRow($row,$byCase[$id]??[])??$row;
            // Include derived spec in the key, too, if archived evidence changed.
            $key=torSnapshotKey('c',$id,$i,$resolved);$caseKeys[$id][$i]=$key;
            $map[$key]=torSnapshotMedicine($resolved)+['case_id'=>$id,'history_id'=>0,'source'=>'case','error'=>torSnapshotHasIdentity($resolved)&&torSnapshotHasSpec($resolved)?'':'ยังไม่มีคุณลักษณะที่ยืนยันได้ กรุณาเลือกยาใหม่จากคลังโดยตั้งใจหรือทบทวนข้อมูลเดิม'];
        }
    }
    return ['medicines'=>$map,'case_keys'=>$caseKeys,'history_keys'=>$historyKeys];
}
function torSnapshotResolveSelection(string $key,int $caseId,int $reportId,array $live,array $archive): ?array
{
    if($key==='')return null;
    if(ctype_digit($key)&&$key!=='0'){
        $row=$live[(int)$key]??null;
        if(!$row)throw new RuntimeException('ไม่พบรายการยาในคลัง อาจมีการล้างหรือเปลี่ยนข้อมูล กรุณาโหลดหน้าใหม่');
        return $row+['source'=>'live'];
    }
    $row=$archive[$key]??null;
    if(!$row||(int)$row['case_id']!==$caseId||($row['source']==='history'&&(int)$row['history_id']!==$reportId)||($row['source']==='case'&&$reportId>0))throw new RuntimeException('ข้อมูลรายการที่บันทึกไม่ตรงชุดหรือเปลี่ยนแล้ว กรุณาโหลดเอกสารใหม่');
    if($row['error']!=='')throw new RuntimeException($row['error']);
    return $row;
}
