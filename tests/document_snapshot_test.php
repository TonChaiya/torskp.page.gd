<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../core/document_snapshot.php';
require_once __DIR__.'/../core/medicine_catalog_tools.php';
$n=0;
function ok(bool $condition,string $message):void{global $n;++$n;if(!$condition)throw new RuntimeException('FAIL '.$message);}
function rejected(callable $fn,string $message):void{try{$fn();}catch(RuntimeException $e){ok(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
function j(array $a):string{return json_encode($a,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function c(int $id,array $rows):array{return ['id'=>$id,'items_json'=>j($rows)];}
function hrow(int $id,int $case,array $rows):array{return ['id'=>$id,'case_id'=>$case,'items_json'=>j($rows)];}
function doc(int $id,int $case,int $history):array{return ['id'=>$id,'case_id'=>$case,'document_type'=>'tor_company_purchase','extra_json'=>j(['history_id'=>$history,'price_basis'=>'offer'])];}
$old=['medicine_id'=>17,'name'=>'Drug ๑','unit'=>'tablet','quantity'=>'๑๐','unknown_key'=>'keep'];
$hist=['name'=>'Drug 1','unit'=>'tablet','specification'=>"1. ข้อเดิม\n2. ฉลาก\n- ชื่อยา",'quantity'=>10,'reference_price'=>1.2345,'offer_price'=>2.125,'offer_total'=>21.25];
$old2=['medicine_id'=>18,'name'=>'Other','unit'=>'bottle','quantity'=>'5'];
$cases=[c(1,[$old]),c(2,[$old2])];$histories=[hrow(20,1,[$hist]),hrow(21,2,[$hist])];$docs=[doc(1,1,20),doc(2,2,21)];
$a=torSnapshotAuditRows($cases,$histories,$docs,[]);
ok(count($a['recoverable'])===1 && count($a['deferred'])===1 && !$a['issues'],'recover only proven, complete TOR mismatch deferred');
ok(torMedicineSnapshotBlock($a)!=='','explicit backfill required');
$updated=torSnapshotItems($a['changes'][1]['new'])[0];
ok($updated['specification']===$hist['specification']&&$updated['snapshot_history_id']===20&&$updated['snapshot_history_row']===1,'exact text and provenance');
foreach($old as $key=>$val)ok($updated[$key]===$val,'preserve original '.$key);
ok(!isset($a['changes'][2]),'never invent mismatched spec');
$after=[c(1,[$updated]),$cases[1]];
$b=torSnapshotAuditRows($after,$histories,$docs,[]);ok(!$b['issues']&&!$b['recoverable']&&count($b['deferred'])===1&&torMedicineSnapshotBlock($b)==='','existing complete documents can survive clear');
ok($a['hash']!==$b['hash'],'document fingerprint changes after recovery');
$pending=torSnapshotAuditRows([c(3,[$old2])],[],[],[]);ok(count($pending['issues'])===1&&torMedicineSnapshotBlock($pending)!=='','unfinished case blocks clear');
$bad=$hist;unset($bad['specification']);$incomplete=torSnapshotAuditRows([$cases[0]],[hrow(20,1,[$bad])],[$docs[0]],[]);ok(count($incomplete['issues'])>=2,'incomplete historical TOR blocks');
$bad=$hist;$bad['offer_total']=0;ok(torSnapshotHistoryProblem($bad)!=='','inconsistent stored total blocks');
$bad=$hist;$bad['medicine_id']=999;ok(torSnapshotRecoverCaseRow($old,[['id'=>20,'rows'=>[$bad]]])===null,'contradictory ID not recovered');
$otherSpec=$hist;$otherSpec['specification']='Different';ok(torSnapshotRecoverCaseRow($old,[['id'=>20,'rows'=>[$hist]],['id'=>22,'rows'=>[$otherSpec]]])===null,'conflicting specs not guessed');
$cross=torSnapshotAuditRows([$cases[0]],[$histories[0]],[doc(1,1,21)],[]);ok(count($cross['issues'])>=1,'missing history link blocks');
$badjson=torSnapshotAuditRows([['id'=>3,'items_json'=>'not json']],[],[],[]);ok(count($badjson['issues'])===1,'invalid JSON blocks');
$orphan=torSnapshotAuditRows([],[],[doc(1,1,20)],[]);ok(count($orphan['issues'])>=1,'orphan document blocks');
$selections=torSnapshotSelections($after,$histories);$key=$selections['history_keys'][20][0];$arch=$selections['medicines'];
$live=[17=>['id'=>17,'name'=>'Changed catalog','unit'=>'vial','specification'=>'new']];
$before=torSnapshotResolveSelection($key,1,20,$live,$arch);$cleared=torSnapshotResolveSelection($key,1,20,[],$arch);ok($before===$cleared&&$before['specification']===$hist['specification']&&$before['id']===0,'old history with no ID opens without live catalog');
rejected(fn()=>torSnapshotResolveSelection($key,2,20,[],$arch),'cross case rejected');
rejected(fn()=>torSnapshotResolveSelection($key,1,21,[],$arch),'cross history rejected');
rejected(fn()=>torSnapshotResolveSelection('17',1,20,[],$arch),'deleted live ID rejected');
ok(torSnapshotResolveSelection('17',1,20,$live,$arch)['name']==='Changed catalog','intentional live selection separate');
$caseKey=$selections['case_keys'][1][0];ok(torSnapshotResolveSelection($caseKey,1,0,[],$arch)['specification']===$hist['specification'],'case snapshot can create TOR without live catalog');
rejected(fn()=>torSnapshotResolveSelection($caseKey,1,20,[],$arch),'case row cannot masquerade as saved TOR');
$deferred=$selections['case_keys'][2][0];rejected(fn()=>torSnapshotResolveSelection($deferred,2,0,[],$arch),'deferred principle row cannot generate new TOR without explicit selection');
$stale=torSnapshotSelections($after,[hrow(20,1,[$otherSpec]),$histories[1]]);rejected(fn()=>torSnapshotResolveSelection($key,1,20,[],$stale['medicines']),'stale history row fingerprint rejected');
$principleKey=torSnapshotKey('c',1,0,$updated);ok(torSnapshotKeyRow('c',1,$principleKey,[$updated])===$updated,'principle saved row resolves exactly');ok(torSnapshotKeyRow('c',2,$principleKey,[$updated])===null,'principle key cannot cross case');
$pack=$hist;$pack['quantity']=5;$pack['requested_quantity']=100;$pack['package_size']=20;$pack['package_count']=5;$pack['offer_total']=10.625;ok(torSnapshotHistoryProblem($pack)==='','package snapshot valid');$pack['package_count']=6;ok(torSnapshotHistoryProblem($pack)!=='','invalid package blocked');
// Admin backfill uses only the case JSON, keeps its metadata, and rolls back the entire change.
final class SnapshotStmt extends PDOStatement {
    private int $idx=0; private array $rows=[]; private int $affected=0;
    public function __construct(private SnapshotDb $db,private string $sql){}
    public function execute(?array $params=null):bool{[$this->rows,$this->affected]=$this->db->run($this->sql,$params??[]);$this->idx=0;return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
    public function closeCursor():bool{return true;}
    public function rowCount():int{return $this->affected;}
}
final class SnapshotDb extends PDO {
    public array $tables=[];public int $writes=0;public bool $fail=false;private ?array $backup=null;
    public function __construct(array $tables){$this->tables=$tables;}
    public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{$s=$this->prepare($query);$s->execute();return $s;}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new SnapshotStmt($this,$query);}
    public function exec(string $statement):int|false{if(!str_starts_with($statement,'SET TRANSACTION'))throw new RuntimeException('unexpected exec');return 0;}
    public function beginTransaction():bool{$this->backup=$this->tables;return true;}
    public function inTransaction():bool{return $this->backup!==null;}
    public function rollBack():bool{$this->tables=$this->backup;$this->backup=null;return true;}
    public function commit():bool{$this->backup=null;return true;}
    public function run(string $sql,array $p):array{if(preg_match('/^SELECT .* FROM `([a-z_]+)` ORDER BY id(?: FOR UPDATE)?$/',$sql,$m))return [$this->tables[$m[1]]??[],0];if(str_starts_with($sql,'UPDATE procurement_cases SET items_json=')){if(!$this->inTransaction())throw new RuntimeException('write without transaction');foreach($this->tables['procurement_cases'] as &$r){if($r['id']===$p[1]&&$r['items_json']===$p[2]){$r['items_json']=$p[0];$this->writes++;if($this->fail)throw new PDOException('injected failure');return [[],1];}}return [[],0];}throw new RuntimeException('unexpected SQL '.$sql);}
}
$db=new SnapshotDb(['procurement_cases'=>$cases,'tor_histories'=>$histories,'procurement_documents'=>$docs,'scope_price_approval_memos'=>[]]);$baseline=$db->tables;
$review=torSnapshotAudit($db);ok($db->writes===0&&$db->tables===$baseline,'audit read only');
$db->fail=true;rejected(fn()=>torSnapshotBackfill($db,$review['hash']),'backfill injected rollback');ok($db->tables===$baseline&&!$db->inTransaction(),'rollback restores all data');$db->fail=false;
ok(torSnapshotBackfill($db,$review['hash'])===1,'one verified case row recovered');ok($db->tables['tor_histories']===$histories&&$db->tables['procurement_documents']===$docs,'no historical rows or other documents rewritten');
rejected(fn()=>torSnapshotBackfill($db,$review['hash']),'stale review rejected');ok(torMedicineSnapshotBlock(torSnapshotAudit($db))==='','verified after recovery');
echo "PASS $n assertions (pure PHP + fake PDO; not MySQL/Word)\n";
