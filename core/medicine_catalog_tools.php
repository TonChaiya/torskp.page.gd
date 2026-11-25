<?php
declare(strict_types=1);

require_once __DIR__.'/document_snapshot.php';

function torMedicineClearBlock(PDO $db, bool $lock = false): string
{
    $audit=torSnapshotAudit($db,$lock);
    return torMedicineSnapshotBlock($audit);
}
function torMedicineSnapshotBlock(array $audit): string
{
    if($audit['issues']) return 'พบข้อมูลเอกสารที่ยังไม่สมบูรณ์ '.count($audit['issues']).' จุด กรุณาเปิดตรวจ Snapshot ก่อนล้าง';
    if($audit['recoverable']) return 'พบคุณลักษณะที่กู้จาก TOR เดิมได้ '.count($audit['recoverable']).' รายการ กรุณาตรวจและบันทึก Snapshot ก่อนล้าง';
    return '';
}
function torMedicineClearHash(string $catalogHash,string $documentHash): string
{
    return hash('sha256',$catalogHash.':'.$documentHash);
}
function torMedicineClearPreview(PDO $db): array
{
    torImportSchema($db, 'medicines');
    $snapshot = torImportSnapshot($db, 'medicines');
    $audit=torSnapshotAudit($db);
    return ['count'=>count($snapshot['ids']), 'hash'=>torMedicineClearHash($snapshot['hash'],$audit['hash']), 'blocked'=>torMedicineSnapshotBlock($audit), 'deferred'=>count($audit['deferred'])];
}
function torClearMedicines(PDO $db, string $hash, int $expected, string $phrase, bool $backupAck): int
{
    if (!$backupAck || $phrase !== 'ล้างรายการยา' || $expected < 1 || !preg_match('/^[a-f0-9]{64}$/D', $hash)) {
        throw new RuntimeException('กรุณารับทราบการสำรองข้อมูลและพิมพ์คำยืนยันให้ถูกต้อง');
    }
    torImportSchema($db, 'medicines');
    $fk = $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='medicines'");
    $hasFk = (int)$fk->fetchColumn() !== 0; $fk->closeCursor();
    if ($hasFk) throw new RuntimeException('พบตารางที่อ้างอิงรายการยา กรุณาตรวจสอบฐานข้อมูลก่อนล้าง');
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    if (!$db->beginTransaction()) throw new RuntimeException('เริ่ม Transaction ไม่สำเร็จ');
    try {
        $audit=torSnapshotAudit($db,true);
        $block = torMedicineSnapshotBlock($audit);
        if ($block !== '') throw new RuntimeException($block);
        $current = torImportSnapshot($db, 'medicines', true);
        if (count($current['ids']) !== $expected || !hash_equals($hash,torMedicineClearHash($current['hash'],$audit['hash']))) throw new RuntimeException('ข้อมูลรายการยาเปลี่ยนไป กรุณาเปิดหน้าต่างล้างข้อมูลใหม่');
        $removed = 0;
        foreach (array_chunk($current['ids'],200) as $ids) {
            $stmt = $db->prepare('DELETE FROM medicines WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')');
            $stmt->execute($ids); $removed += $stmt->rowCount();
        }
        if ($removed !== $expected) throw new RuntimeException('จำนวนที่ลบไม่ตรงกับที่ยืนยัน');
        if (!$db->commit()) throw new RuntimeException('ยืนยัน Commit ไม่สำเร็จ กรุณาตรวจข้อมูลจริงก่อนลองใหม่');
        return $removed;
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

// Export the full catalog, not the current search or the first 50 rows.
// Text cells are never emitted as formulas, and LF is retained inside cells.
function torMedicineExportCell(string $text): string
{
    if (!preg_match('//u',$text) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]|[\x{FFFE}\x{FFFF}]/u',$text)) throw new RuntimeException('พบข้อความที่ Excel ไม่รองรับ ไม่สามารถส่งออกโดยไม่สูญเสียข้อมูล');
    $utf16 = function_exists('mb_convert_encoding') ? mb_convert_encoding($text,'UTF-16LE','UTF-8') : iconv('UTF-8','UTF-16LE',$text);
    if ($utf16 === false || strlen($utf16)>65534) throw new RuntimeException('พบเซลล์ที่เกินความยาวสูงสุดของ Excel (32,767 ตัวอักษร)');
    // The current importer does not decode OOXML escape tokens. Fail closed for rare ambiguous literals.
    if (preg_match('/_x[0-9a-fA-F]{4}_/',$text)) throw new RuntimeException('พบข้อความที่มีรูปแบบ escape พิเศษของ Excel กรุณาตรวจข้อมูลก่อนส่งออก');
    return str_replace("\r",'&#13;',htmlspecialchars($text,ENT_XML1|ENT_QUOTES,'UTF-8'));
}
function torMedicineWriteSheet($stream, iterable $rows): int
{
    $write = static function(string $s) use ($stream): void { if (fwrite($stream,$s)!==strlen($s)) throw new RuntimeException('พื้นที่จัดเก็บไฟล์ส่งออกไม่เพียงพอ'); };
    $write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="1" width="48" customWidth="1"/><col min="2" max="2" width="18" customWidth="1"/><col min="3" max="3" width="90" customWidth="1"/></cols><sheetData>');
    $number=0;
    $all=(static function() use ($rows): Generator { yield ['ชื่อยา','หน่วย','คุณสมบัติยา']; foreach($rows as $r) yield [(string)$r['name'],(string)$r['unit'],(string)$r['specification']]; })();
    foreach($all as $r){
        ++$number;
        if($number>1048576) throw new RuntimeException('จำนวนแถวเกินขนาดของ Excel');
        $height=$number===1?26:min(409,max(32,18*(substr_count($r[2],"\n")+1+intdiv(torChars($r[2]),80))));
        $line='<row r="'.$number.'" ht="'.$height.'" customHeight="1">';
        foreach($r as $i=>$text) $line.='<c r="'.chr(65+$i).$number.'" s="'.($number===1?'1':'2').'" t="inlineStr"><is><t xml:space="preserve">'.torMedicineExportCell($text).'</t></is></c>';
        $write($line.'</row>');
    }
    $write('</sheetData><autoFilter ref="A1:C'.$number.'"/></worksheet>');
    return $number-1;
}
function torExportMedicines(PDO $db): void
{
    if (!class_exists(ZipArchive::class)) throw new RuntimeException('Server ยังไม่เปิด ZipArchive สำหรับส่งออก Excel');
    $xml = appTempFile('tor_med_export_sheet_'); $out = null;
    try {
        $out=appTempFile('tor_med_export_');
        $stream=fopen($xml,'wb'); if(!$stream) throw new RuntimeException('สร้างไฟล์ส่งออกไม่ได้');
        $stmt=null;
        try {
            $stmt=$db->query('SELECT name, unit, specification FROM medicines ORDER BY name, id');
            torMedicineWriteSheet($stream,(static function()use($stmt):Generator{while($r=$stmt->fetch(PDO::FETCH_ASSOC))yield $r;})());
        } finally { fclose($stream); if($stmt) $stmt->closeCursor(); }
        $zip=new ZipArchive(); if($zip->open($out,ZipArchive::OVERWRITE)!==true) throw new RuntimeException('สร้างไฟล์ Excel ไม่สำเร็จ');
        $files=[
            '[Content_Types].xml'=>'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF205C86"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>'
        ];
        try { foreach($files as $name=>$body) if(!$zip->addFromString($name,'<?xml version="1.0" encoding="UTF-8"?>'.$body)) throw new RuntimeException('สร้างส่วนประกอบ Excel ไม่สำเร็จ'); if(!$zip->addFile($xml,'xl/worksheets/sheet1.xml')) throw new RuntimeException('เพิ่มข้อมูลใน Excel ไม่สำเร็จ'); } finally { $closed=$zip->close(); }
        if(!$closed) throw new RuntimeException('บันทึกไฟล์ Excel ไม่สำเร็จ');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="TOR_SKP_medicines_'.date('Ymd_His').'.xlsx"');
        header('Cache-Control: private, no-store'); header('X-Content-Type-Options: nosniff');
        header('Content-Length: '.filesize($out)); readfile($out);
    } finally { @unlink($xml); if($out!==null) @unlink($out); }
}
