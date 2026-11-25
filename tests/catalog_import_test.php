<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__.'/../core/spreadsheet_import.php';
require __DIR__.'/../core/catalog_import.php';
require __DIR__.'/../core/medicine_catalog_tools.php';
// A deterministic fake PDO exercises PHP control flow only. It does NOT certify MySQL SQL/collations/locking.
final class FakeStmt extends PDOStatement {
    public array $rows=[];private int $index=0;public int $affected=0;
    public function __construct(private FakeDb $db,private string $sql){}
    public function execute(?array $params=null):bool{$this->index=0;[$this->rows,$this->affected]=$this->db->run($this->sql,$params??[]);return true;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->rows[$this->index++]??false;}
    public function fetchColumn(int $column=0):mixed{$r=$this->fetch();return $r===false?false:array_values($r)[$column];}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{if($mode===PDO::FETCH_KEY_PAIR){$out=[];foreach($this->rows as $r){$v=array_values($r);$out[$v[0]]=$v[1];}return $out;}return $this->rows;}
    public function getIterator():Iterator{return new ArrayIterator($this->rows);}
    public function closeCursor():bool{return true;}
    public function rowCount():int{return $this->affected;}
}
final class FakeDb extends PDO {
    public array $med=[],$prices=[];public int $writes=0,$next=100000;public ?int $failWrite=null;public int $inboundFk=0;public array $documentTables=[];private ?array $backup=null;
    public function __construct(){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new FakeStmt($this,$query);}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs):PDOStatement|false{$s=$this->prepare($query);$s->execute();return $s;}
    public function exec(string $statement):int|false{if(!str_starts_with($statement,'SET TRANSACTION'))throw new Exception('unexpected exec');return 0;}
    public function beginTransaction():bool{$this->backup=[$this->med,$this->prices];return true;}
    public function commit():bool{$this->backup=null;return true;}
    public function rollBack():bool{[$this->med,$this->prices]=$this->backup;$this->backup=null;return true;}
    public function inTransaction():bool{return $this->backup!==null;}
    public function key(array $r,bool $price):string{return strtolower($r['name']).($price?'|'.strtolower($r['strength']).'|'.strtolower($r['unit']):'');}
    public function run(string $sql,array $p):array{
        if(preg_match('/^SELECT id(?:, [^`]+)? FROM `(procurement_cases|procurement_documents|tor_histories|scope_price_approval_memos)`/', $sql, $m)){
            $row=['id'=>1,'case_id'=>99,'items_json'=>'[]','document_type'=>'tor_company_purchase','extra_json'=>'{}'];
            return [is_array($this->documentTables[$m[1]]??null)?$this->documentTables[$m[1]]:(isset($this->documentTables[$m[1]])?[$row]:[]),0];
        }
        if($sql==='SELECT name, unit, specification FROM medicines ORDER BY name, id')return [array_values($this->med),0];
        if(str_contains($sql,'information_schema.KEY_COLUMN_USAGE'))return [[['count'=>$this->inboundFk]],0];
        if(str_contains($sql,'information_schema.TABLES'))return [[['ENGINE'=>'InnoDB']],0];
        if(str_contains($sql,'information_schema.COLUMNS'))return [array_map(fn($k)=>['COLUMN_NAME'=>$k,'COLLATION_NAME'=>'utf8mb4_unicode_ci'],$p[0]==='medicines'?['name','unit']:['medicine_name','dosage_form_strength','unit']),0];
        if(str_contains($sql,'information_schema.STATISTICS')){ $keys=$p[0]==='medicines'?['name']:['medicine_name','dosage_form_strength','unit'];$rows=[['INDEX_NAME'=>'PRIMARY','COLUMN_NAME'=>'id','SEQ_IN_INDEX'=>1]];foreach($keys as $i=>$k)$rows[]=['INDEX_NAME'=>'unique','COLUMN_NAME'=>$k,'SEQ_IN_INDEX'=>$i+1];return [$rows,0]; }
        if(str_contains($sql,' FORCE INDEX ')){$data=str_contains($sql,'`medicines`')?$this->med:$this->prices;$rows=[];foreach($data as $r)$rows[]=['id'=>$r['id'],'sig'=>hash('sha256',json_encode($r))];usort($rows,fn($a,$b)=>$a['id']<=>$b['id']);return [$rows,0];}
        if(str_contains($sql,'WEIGHT_STRING')){$price=str_contains($sql,'LEFT JOIN medicine_price_references');$n=$price?4:3;$data=$price?$this->prices:$this->med;$rows=[];foreach(array_chunk($p,$n) as $v){$key=$this->key(['name'=>$v[1],'unit'=>$v[2],'strength'=>$v[3]??''],$price);$rows[]=array_merge(['row_no'=>$v[0],'key_hash'=>hash('sha256',$key)],$data[$key]??['id'=>null]);}return [$rows,0];}
        if(str_starts_with($sql,'INSERT INTO')||str_starts_with($sql,'DELETE FROM')){
            if(!$this->inTransaction())throw new Exception('write without transaction');if(++$this->writes===$this->failWrite)throw new PDOException('injected write failure');
            if(str_starts_with($sql,'DELETE FROM')){$data=&$this->prices;if(str_starts_with($sql,'DELETE FROM medicines '))$data=&$this->med;$count=0;foreach($data as $key=>$r)if(in_array($r['id'],$p,true)){unset($data[$key]);$count++;}return [[], $count];}
            $price=str_starts_with($sql,'INSERT INTO medicine_price_references');$n=$price?5:3;$data=&$this->med;if($price)$data=&$this->prices;
            foreach(array_chunk($p,$n) as $v){$r=$price?['name'=>$v[0],'strength'=>$v[1],'unit'=>$v[2],'price'=>$v[3],'source_file'=>$v[4]]:['name'=>$v[0],'unit'=>$v[1],'specification'=>$v[2]];$key=$this->key($r,$price);$r['id']=$data[$key]['id']??$this->next++;$data[$key]=$r;}return [[],count($p)/$n];
        }
        throw new Exception('unexpected SQL '.$sql);
    }
}
$checks=0;function check(bool $v,string $msg):void{global $checks;$checks++;if(!$v)throw new Exception('FAIL '.$msg);}
function fails(callable $f,string $msg):void{try{$f();}catch(RuntimeException|PDOException $e){check(true,$msg);return;}throw new Exception('Expected failure: '.$msg);}
$root=sys_get_temp_dir().'/tor_catalog_test_'.bin2hex(random_bytes(8));mkdir($root,0700);$dirs=[];
function stage(FakeDb $db,string $kind,array $rows,string $mode='merge'):array{global $root,$dirs;$dir=$root.'/'.count($dirs);mkdir($dir);$dirs[]=$dir;$file=$dir.'/source.csv';$h=fopen($file,'wb');fwrite($h,"\xEF\xBB\xBF");fputcsv($h,$kind==='medicines'?['ชื่อยา','หน่วย','คุณสมบัติยา']:['รายการยา','รูปแบบยาและความแรง','หน่วย','ราคากลางยาต่อหน่วย (บาท)'],',','"','');foreach($rows as $r)fputcsv($h,$r,',','"','');fclose($h);$meta=['kind'=>$kind,'mode'=>$mode,'filename'=>'fixture.csv','status'=>'checking','processed'=>0,'total'=>0,'counts'=>array_fill_keys(TOR_IMPORT_STATUSES,0)];$meta=torImportPrepare($db,$dir,$meta,['tmp_name'=>$file,'name'=>'fixture.csv']);torImportWrite($dir.'/meta.json',$meta);return [$dir,$meta];}
try{
    check(torPriceDecimal('1,234.5000')==='1234.5000','exact decimal');foreach(['','abc','-1','1,23','1e3','12.00001','100000000000'] as $p)check(torPriceDecimal($p)===null,'invalid price '.$p);check(torPriceDecimal('0')==='0.0000','zero');
    // Excel stores numeric values with binary representation tails. Import may
    // round to four decimal places only with an explicit preview acknowledgement.
    foreach (['2324.5500000000002'=>'2324.5500','1050.0000682500001'=>'1050.0001','0.00005'=>'0.0001','9.99995'=>'10.0000','1,234.56785'=>'1234.5679','99999999999.99994'=>'99999999999.9999'] as $raw=>$want) check(torPriceImportDecimal($raw)===$want,'round '.$raw);
    foreach (['','abc','-1','1,23','1e3','99999999999.99995','100000000000'] as $raw) check(torPriceImportDecimal($raw)===null,'reject '.$raw);
    [$linePrice,$lineErrors]=torPriceRow(["Soluble insulin\n(Neutral insulin)","sterile sol\n100 iu/ml",'1 หลอด','75.33']);
    check(!$lineErrors && $linePrice['name']==="Soluble insulin\n(Neutral insulin)" && $linePrice['strength']==="sterile sol\n100 iu/ml",'price multiline original key preserved');
    [, $controlErrors]=torPriceRow(["Drug\x01",'tab','1','1.00']);check(count($controlErrors)===1,'other control characters rejected');
    $roundDb=new FakeDb();
    [$rd,$rm]=stage($roundDb,'prices',[['Precision','tab','1','2324.5500000000002'],['True precision','tab','1','1050.0000682500001'],['Exact','tab','1','1.2345']]);
    check($rm['blocked']===0 && $rm['counts']['warning']===2 && $roundDb->writes===0,'round warnings preview read only');
    $roundRows=iterator_to_array(torImportRows($rd),false);check($roundRows[0]['new']['original_price']==='2324.5500000000002' && str_contains($roundRows[0]['message'],'2324.5500'),'round original and proposed value visible');
    fails(fn()=>torImportApply($roundDb,$rd,$rm,false),'round acknowledgment required');
    check(torImportApply($roundDb,$rd,$rm,true)['changed']===3 && $roundDb->prices['true precision|tab|1']['price']==='1050.0001','commit rounded exact decimal');
    check(torImportCsvCell('=1+1')==="'=1+1",'formula injection');check(torImportHeader(['ชื่อยา','หน่วย','คุณสมบัติยา'],'medicines'),'medicine header');check(!torImportHeader(['ชื่อยา','หน่วย','คุณสมบัติยา'],'prices'),'reject wrong type');
    $multi="1. เป็นยาฉีด\r\n2. ตัวยา \"Atropine\"\r\n4. ฉลาก\r\n- ระบุชื่อยา";$expected=str_replace("\r\n","\n",$multi);
    $db=new FakeDb();$db->med['existing']=['id'=>7,'name'=>'Existing','unit'=>'tablet','specification'=>'old'];$db->prices['x|s|u']=['id'=>8,'name'=>'X','strength'=>'S','unit'=>'u','price'=>'2.0000','source_file'=>'old.csv'];
    [$dir,$meta]=stage($db,'medicines',[['New','ampoule',$multi],['Existing','tablet',''],['Bad','','x'],['new','ampoule','duplicate'],['Existing2','tablet','']]);
    check($db->writes===0,'preview no writes');check($meta['blocked']===2,'error + duplicate block');check($meta['counts']['warning']===2,'warnings');$rr=iterator_to_array(torImportRows($dir),false);check($rr[0]['new']['specification']===$expected,'multiline quote CRLF');check($rr[1]['new']['specification']==='old','preserve blank existing');fails(fn()=>torImportApply($db,$dir,$meta,true),'blocked commit');
    [$dir,$meta]=stage($db,'medicines',[['Existing','bottle','new']]);check($meta['counts']['conflict']===1,'unit conflict');
    [$dir,$meta]=stage($db,'medicines',[['New','ampoule',$multi],['Existing','tablet','new spec'],['Another','tablet','']]);fails(fn()=>torImportApply($db,$dir,$meta,false),'warning acknowledgement required');$done=torImportApply($db,$dir,$meta,true);check($done['changed']===3,'commit count');check($db->med['existing']['id']===7,'ID preserved');check($db->med['new']['specification']===$expected,'spec stored');check(count($db->prices)===1,'medicine isolation');
    [$dir,$meta]=stage($db,'medicines',[['Existing','tablet','new spec']]);check($meta['counts']['same']===1,'same');check(torImportApply($db,$dir,$meta,true)['changed']===0,'skip same');
    [$dir,$meta]=stage($db,'prices',[['X','S','u','3.25'],['Y','S','u','0'],['Z','S','u','no']]);check($meta['counts']['error']===1&&$meta['counts']['warning']===1,'price validation');fails(fn()=>torImportApply($db,$dir,$meta,true),'price errors block');
    [$dir,$meta]=stage($db,'prices',[['Y','S','u','4']], 'replace');check($meta['remove']===1,'replace removal count');fails(fn()=>torImportApply($db,$dir,$meta,true,false),'replace ack');$before=$db->prices;$db->failWrite=$db->writes+2;fails(fn()=>torImportApply($db,$dir,$meta,true,true),'rollback on delete failure');check($db->prices===$before,'rollback restored old prices');$db->failWrite=null;
    [$dir,$meta]=stage($db,'prices',[['X','S','u','3.2500'],['Y','S','u','4']],'replace');$done=torImportApply($db,$dir,$meta,true,true);check($db->prices['x|s|u']['id']===8,'price ID preserved');check(count($db->med)===3,'price isolation');
    [$dir,$meta]=stage($db,'prices',[['Y','S','u','4']],'replace');$done=torImportApply($db,$dir,$meta,true,true);check($done['removed']===1&&count($db->prices)===1,'replace removes only missing');
    [$dir,$meta]=stage($db,'prices',[['Y','S','u','5']]);$db->prices['y|s|u']['price']='6.0000';fails(fn()=>torImportApply($db,$dir,$meta,true),'stale preview');check($db->prices['y|s|u']['price']==='6.0000','no stale overwrite');
    $mal=$root.'/bad.csv';file_put_contents($mal,"a,b,\"not closed\n");fails(fn()=>iterator_to_array(readCsvRows($mal)),'unclosed CSV quotes');unlink($mal);
    foreach([234,2520,5000,10000,50000] as $size){$large=new FakeDb();$rows=(function()use($size){for($i=0;$i<$size;$i++)yield ['Drug '.$i,'tablet 1 mg','tablet','1.2500'];})();$rows=iterator_to_array($rows,false);$t=microtime(true);[$dir,$meta]=stage($large,'prices',$rows);check($meta['total']===$size&&$meta['counts']['new']===$size,'large preview '.$size);$done=torImportApply($large,$dir,$meta,true);check(count($large->prices)===$size&&$done['changed']===$size,'large apply '.$size);printf("Mock PDO + CSV %d rows: %.2fs\n",$size,microtime(true)-$t);unset($large,$rows);}
    foreach([234,10000] as $size){$large=new FakeDb();$items=[];for($i=0;$i<$size;$i++)$items[]=['Medicine '.$i,'tablet',$expected];[$dir,$meta]=stage($large,'medicines',$items);check($meta['total']===$size,'medicine preview '.$size);$done=torImportApply($large,$dir,$meta,true);check(count($large->med)===$size&&$large->med['medicine 0']['specification']===$expected,'medicine apply '.$size);unset($large,$items);}
    if(class_exists(ZipArchive::class)&&class_exists(XMLReader::class)){
        // Optional on Laragon: actual sparse XLSX cells + shared/inline strings and formula warning.
        $path=$root.'/sample.xlsx';$zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml','<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><r><t>ชื่อ</t></r><r><t>ยา</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="2"><c r="A2" t="s"><v>0</v></c><c r="C2" t="inlineStr"><is><t xml:space="preserve">ข้อ 1&#10;ข้อ 2</t></is></c></row><row r="5"><c r="D5"><f>1+2</f><v>3</v></c></row></sheetData></worksheet>');$zip->close();
        $result=iterator_to_array(readXlsxRows($path));check($result[2]['cells']===['ชื่อยา','','ข้อ 1'."\n".'ข้อ 2',''],'XLSX sparse and multiline');check(count($result[5]['issues'])===1,'XLSX formula rejected');unlink($path);
        // Actual bundled templates use prefixed SpreadsheetML. Only the first sheet is read.
        foreach(['medicines'=>'TOR_SKP_v1.2.0_medicine_template.xlsx','prices'=>'TOR_SKP_v1.2.0_price_reference_template.xlsx'] as $kind=>$filename){
            $template=__DIR__.'/../templates/import/'.$filename;
            $rows=array_values(array_filter(iterator_to_array(readXlsxRows($template)),static fn($r)=>count(array_filter($r['cells'],static fn($v)=>$v!==''))>0));
            check(count($rows)===1 && torImportHeader($rows[0]['cells'],$kind),'bundled XLSX template header and no example data '.$kind);
        }
        // Excel-saved workbook: real shared-string headers and native numeric values.
        $path=$root.'/shared_headers.xlsx';$zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml','<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>รายการยา</t></si><si><t>รูปแบบยาและความแรง</t></si><si><t>หน่วย</t></si><si><t>ราคากลางยาต่อหน่วย (บาท)</t></si><si><t>Example drug</t></si><si><t>tablet 10 mg</t></si><si><t>tablet</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml','<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row><row r="2"><c r="A2" t="s"><v>4</v></c><c r="B2" t="s"><v>5</v></c><c r="C2" t="s"><v>6</v></c><c r="D2"><v>2324.5500000000002</v></c></row></sheetData></worksheet>');$zip->close();
        $result=iterator_to_array(readXlsxRows($path));check(torImportHeader($result[1]['cells'],'prices')&&!$result[1]['issues'],'Excel shared-string price headers');check($result[2]['cells']===['Example drug','tablet 10 mg','tablet','2324.5500000000002'],'Excel shared-string and numeric row');unlink($path);
        $path=$root.'/prefixed.xlsx';$zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $zip->addFromString('xl/sharedStrings.xml','<x:sst xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:si><x:r><x:t>ชื่อ</x:t></x:r><x:r><x:t>ยา</x:t></x:r></x:si></x:sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml','<x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheetData><x:row r="2"><x:c r="A2" t="s"><x:v>0</x:v></x:c><x:c r="C2" t="inlineStr"><x:is><x:t>ข้อ 1&#10;ข้อ 2</x:t></x:is></x:c></x:row></x:sheetData></x:worksheet>');$zip->close();
        $result=iterator_to_array(readXlsxRows($path));check($result[2]['cells']===['ชื่อยา','','ข้อ 1'."\n".'ข้อ 2',''],'prefixed XLSX sparse and rich text');unlink($path);
    }else{echo "SKIP XLSX: PHP extensions unavailable\n";}
    // Optional private workbook/CSV regression, never commit real catalog fixtures.
    $privateFile=getenv('TOR_IMPORT_TEST_PRICE_FILE');
    if($privateFile!==false && $privateFile!=='') {
        $ext=strtolower(pathinfo($privateFile,PATHINFO_EXTENSION));
        if(!in_array($ext,['csv','xlsx'],true)||!is_file($privateFile))throw new RuntimeException('Invalid TOR_IMPORT_TEST_PRICE_FILE');
        $privateDb=new FakeDb();$pd=$root.'/'.count($dirs);mkdir($pd);$dirs[]=$pd;
        $pm=['kind'=>'prices','mode'=>'merge','filename'=>basename($privateFile),'status'=>'checking','processed'=>0,'total'=>0,'counts'=>array_fill_keys(TOR_IMPORT_STATUSES,0)];
        $pm=torImportPrepare($privateDb,$pd,$pm,['tmp_name'=>$privateFile,'name'=>basename($privateFile)]);
        echo 'Private price fixture: '.json_encode(['total'=>$pm['total'],'counts'=>$pm['counts'],'blocked'=>$pm['blocked']],JSON_UNESCAPED_UNICODE)."\n";
        check($privateDb->writes===0,'private fixture preview read only');
        check($pm['blocked']===0,'private fixture no blocked rows');
        $result=torImportApply($privateDb,$pd,$pm,true);check($result['changed']===$pm['total'],'private fixture apply count');
        check(count($privateDb->prices)===$pm['total'],'private fixture database count');
    }
    // Annual clear: exact target only, explicit confirmation and fresh snapshot.
    $clearDb=new FakeDb();$clearDb->med['keep']=['id'=>1,'name'=>'Keep','unit'=>'u','specification'=>'keep'];
    for($i=1;$i<=401;$i++)$clearDb->prices['p'.$i.'|s|u']=['id'=>$i,'name'=>'P'.$i,'strength'=>'S','unit'=>'u','price'=>'2.0000','source_file'=>'fixture.csv'];
    $preview=torClearPricePreview($clearDb);$original=$clearDb->prices;$keep=$clearDb->med;
    check($preview['count']===401&&$clearDb->writes===0,'clear preview read only');
    fails(fn()=>torClearPriceReferences($clearDb,$preview['hash'],401,'wrong',true),'clear incorrect phrase');
    fails(fn()=>torClearPriceReferences($clearDb,$preview['hash'],401,'ล้างราคากลาง',false),'clear missing backup acknowledgement');
    fails(fn()=>torClearPriceReferences($clearDb,$preview['hash'],400,'ล้างราคากลาง',true),'clear wrong count');
    $clearDb->prices['p1|s|u']['price']='3.0000';fails(fn()=>torClearPriceReferences($clearDb,$preview['hash'],401,'ล้างราคากลาง',true),'clear same count but stale snapshot');$clearDb->prices=$original;
    $clearDb->inboundFk=1;fails(fn()=>torClearPriceReferences($clearDb,$preview['hash'],401,'ล้างราคากลาง',true),'clear rejects inbound foreign key');$clearDb->inboundFk=0;
    $clearDb->failWrite=$clearDb->writes+2;fails(fn()=>torClearPriceReferences($clearDb,$preview['hash'],401,'ล้างราคากลาง',true),'clear failure on second batch');check($clearDb->prices==$original&&$clearDb->med===$keep&&!$clearDb->inTransaction(),'clear rollback and isolation');$clearDb->failWrite=null;
    [$pendingDir,$pendingMeta]=stage($clearDb,'prices',[['P1','S','u','4.00']]);
    check(torClearPriceReferences($clearDb,$preview['hash'],401,'ล้างราคากลาง',true)===401,'clear exact count');check($clearDb->prices===[]&&$clearDb->med===$keep,'clear only prices');
    fails(fn()=>torImportApply($clearDb,$pendingDir,$pendingMeta,true),'old import preview invalidated after clear');
    fails(fn()=>torClearPriceReferences($clearDb,$preview['hash'],401,'ล้างราคากลาง',true),'clear cannot replay');
    check(torClearPricePreview($clearDb)['count']===0,'empty catalog preview');
    // Full medicine export preserves multiline and never writes formula cells.
    $f=fopen('php://temp','w+b');
    $sampleSpec="1. ข้อแรก\n2. ข้อสอง & <ยา>\n- ฉลาก";
    check(torMedicineWriteSheet($f,[['name'=>'=1+1','unit'=>'tablet','specification'=>$sampleSpec],['name'=>'B','unit'=>'ขวด','specification'=>'ข้อความ']])===2,'export all rows');
    rewind($f);$xml=stream_get_contents($f);fclose($f);
    check(str_contains($xml,'ชื่อยา')&&str_contains($xml,'คุณสมบัติยา')&&str_contains($xml,'A1:C3'),'export compatible header and filter');
    check(str_contains($xml,"ข้อแรก\n2. ข้อสอง &amp; &lt;ยา&gt;\n- ฉลาก"),'export preserves line breaks and escapes XML');
    check(str_contains($xml,'t="inlineStr"><is><t xml:space="preserve">=1+1')&&!str_contains($xml,'<f>'),'export text not formula');
    fails(fn()=>torMedicineExportCell(str_repeat('ก',32768)),'export rejects oversized Excel text rather than truncating');
    $medDb=new FakeDb();$medDb->prices['keep|s|u']=['id'=>1,'name'=>'Keep','strength'=>'s','unit'=>'u','price'=>'1.0000','source_file'=>'x'];
    for($i=1;$i<=401;$i++)$medDb->med['medicine '.$i]=['id'=>$i,'name'=>'Medicine '.$i,'unit'=>'tablet','specification'=>$sampleSpec];
    $mp=torMedicineClearPreview($medDb);$oldMed=$medDb->med;$oldPrice=$medDb->prices;
    check($mp['count']===401&&$mp['blocked']===''&&$medDb->writes===0,'medicine clear preview read only');
    foreach(['procurement_cases','procurement_documents','tor_histories','scope_price_approval_memos'] as $table){$medDb->documentTables=[$table=>true];check(torMedicineClearPreview($medDb)['blocked']!=='','block saved '.$table);fails(fn()=>torClearMedicines($medDb,$mp['hash'],401,'ล้างรายการยา',true),'server blocks '.$table);check($medDb->med===$oldMed&&$medDb->prices===$oldPrice,'no modification '.$table);}
    $medDb->documentTables=[];
    fails(fn()=>torClearMedicines($medDb,$mp['hash'],401,'ล้างราคากลาง',true),'wrong medicine phrase');
    fails(fn()=>torClearMedicines($medDb,$mp['hash'],401,'ล้างรายการยา',false),'backup acknowledgement required');
    $medDb->inboundFk=1;fails(fn()=>torClearMedicines($medDb,$mp['hash'],401,'ล้างรายการยา',true),'medicine inbound FK blocked');$medDb->inboundFk=0;
    $medDb->med['medicine 1']['specification']='updated';fails(fn()=>torClearMedicines($medDb,$mp['hash'],401,'ล้างรายการยา',true),'medicine stale snapshot');$medDb->med=$oldMed;
    $medDb->failWrite=$medDb->writes+2;fails(fn()=>torClearMedicines($medDb,$mp['hash'],401,'ล้างรายการยา',true),'medicine clear failure second batch');check($medDb->med===$oldMed&&$medDb->prices===$oldPrice&&!$medDb->inTransaction(),'medicine clear rollback');$medDb->failWrite=null;
    [$mPending,$mMeta]=stage($medDb,'medicines',[['Medicine 1','tablet','updated']]);
    check(torClearMedicines($medDb,$mp['hash'],401,'ล้างรายการยา',true)===401,'medicine clear count');
    check($medDb->med===[]&&$medDb->prices===$oldPrice,'clear medicine only');fails(fn()=>torImportApply($medDb,$mPending,$mMeta,true),'medicine old import invalidated');
    fails(fn()=>torClearMedicines($medDb,$mp['hash'],401,'ล้างรายการยา',true),'medicine clear replay blocked');
    // New policy: a complete saved case/TOR remains independent when the live catalog is cleared.
    $safeDb=new FakeDb();
    $safeDb->med=['current'=>['id'=>77,'name'=>'Current','unit'=>'tablet','specification'=>'New spec']];
    $safeDb->prices=$oldPrice;
    $safeDb->documentTables=[
        'procurement_cases'=>[['id'=>1,'items_json'=>json_encode([['medicine_id'=>77,'name'=>'Old','unit'=>'tablet','quantity'=>2,'specification'=>'Old spec']])]],
        'tor_histories'=>[['id'=>4,'case_id'=>1,'items_json'=>json_encode([['name'=>'Old','unit'=>'tablet','specification'=>'Old spec','quantity'=>2,'reference_price'=>1.2,'offer_price'=>1.1,'offer_total'=>2.2]])]],
        'procurement_documents'=>[['id'=>3,'case_id'=>1,'document_type'=>'tor_company_purchase','extra_json'=>'{"history_id":4}']],
    ];
    $safeDocs=$safeDb->documentTables;$safePreview=torMedicineClearPreview($safeDb);
    check($safePreview['blocked']===''&&$safePreview['count']===1,'complete snapshot allows clear preview');
    $safeDb->documentTables['procurement_cases'][0]['items_json']=str_replace('Old spec','Changed spec',$safeDb->documentTables['procurement_cases'][0]['items_json']);
    fails(fn()=>torClearMedicines($safeDb,$safePreview['hash'],1,'ล้างรายการยา',true),'document changed after preview blocks clear');
    check(count($safeDb->med)===1,'stale doc confirmation leaves catalog unchanged');
    $safeDb->documentTables=$safeDocs;
    check(torClearMedicines($safeDb,$safePreview['hash'],1,'ล้างรายการยา',true)===1,'clear with complete saved docs');
    check(!$safeDb->med&&$safeDb->prices===$oldPrice&&$safeDb->documentTables===$safeDocs,'clear preserves archived docs and prices');
    echo "PASS $checks assertions (CSV + fake PDO; not MySQL; see XLSX skip above)\n";
}finally{foreach($dirs as $d)torImportClean($d);@rmdir($root);}
