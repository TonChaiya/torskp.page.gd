<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require __DIR__ . '/core/auth.php';
requireLogin();
header('Cache-Control: private, no-store, no-cache, must-revalidate');

function h(?string $v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function thaiMemoDigits(string $text): string { return strtr($text, ['0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔','5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙']); }
function memoField(string $key, string $default=''): string { return trim((string) ($_POST[$key] ?? $default)); }
function memoPerson($person, string $role): array {
    if (is_array($person)) return ['name'=>trim((string) ($person['name'] ?? '')), 'position'=>trim((string) ($person['position'] ?? '')), 'role'=>$role];
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $person) ?: []), static fn($line)=>$line!==''));
    return ['name'=>$lines[0] ?? '', 'position'=>$lines[1] ?? '', 'role'=>$role];
}

/** One document renderer for the browser preview and DOCX: the same field values and layout. */
function committeeResultDocumentHtml(array $values, string $memoNo, string $docDate, array $committee, bool $forWord = false): string
{
    $garudaSrc = 'picture/picture-1.png';
    if ($forWord && is_file(__DIR__ . '/picture/picture-1.png')) {
        $bytes = file_get_contents(__DIR__ . '/picture/picture-1.png');
        if (is_string($bytes)) $garudaSrc = 'data:image/png;base64,' . base64_encode($bytes);
    }
    ob_start();
    ?>
          <section class="memo-sheet">
            <table class="memo-head" role="presentation"><tr><td style="width:3cm"><img src="<?= h($garudaSrc) ?>" class="garuda" alt="ตราครุฑ"></td><td class="memo-heading">บันทึกข้อความ</td><td style="width:3cm">&nbsp;</td></tr></table>
            <div class="memo-meta"><span class="memo-label">ส่วนราชการ</span><span class="memo-value memo-office-value" data-memo-output="office" style="font-size:15pt!important;letter-spacing:-0.12pt;white-space:nowrap"><?=h($values['office'])?></span></div>
            <div class="memo-meta-no"><span class="memo-label">ที่</span><span class="memo-value">ชม ๕๑๐๐๖.๑๓.๑/<span data-memo-output="memo_no_suffix" data-digits="thai"><?=h(thaiMemoDigits($memoNo))?></span></span><span class="memo-label">วันที่</span><span class="memo-value" data-memo-output="doc_date" data-digits="thai"><?=h(thaiMemoDigits($docDate))?></span></div>
            <div class="memo-meta"><span class="memo-label">เรื่อง</span><span class="memo-value" data-memo-output="subject"><?=h($values['subject'])?></span></div>
            <div class="memo-meta" style="margin-top:8pt"><span class="memo-label">เรียน</span><span class="memo-value" data-memo-output="recipient"><?=h($values['recipient'])?></span></div>
            <div class="memo-section">ต้นเรื่อง</div><p class="memo-paragraph" data-memo-output="origin"><?=h($values['origin'])?></p>
            <div class="memo-section">ข้อเท็จจริง</div><p class="memo-paragraph" data-memo-output="facts"><?=h($values['facts'])?></p>
            <div class="memo-section">ข้อพิจารณา</div><p class="memo-paragraph" data-memo-output="consideration"><?=h($values['consideration'])?></p>
            <p class="memo-paragraph" style="margin-top:12pt" data-memo-output="conclusion"><?=h($values['conclusion'])?></p>
            <div class="memo-signatures">
              <div class="memo-signature"><div>(ลงชื่อ)................................................ประธานกรรมการ</div><div class="name"><?=h($committee[0]['name']!==''?$committee[0]['name']:'........................................')?></div><div><?=h($committee[0]['position'])?></div></div>
              <div class="memo-signature-row">
                <?php foreach([1,2] as $i): ?><div class="memo-signature"><div>(ลงชื่อ)....................................กรรมการ</div><div class="name"><?=h($committee[$i]['name']!==''?$committee[$i]['name']:'........................................')?></div><div><?=h($committee[$i]['position'])?></div></div><?php endforeach ?>
              </div>
            </div>
            <div class="memo-page-footer">/ความเห็นของ…</div>
          </section>
          <section class="memo-sheet">
            <div class="memo-sheet-header-number">-๒-</div>
            <div class="memo-page-2-title" data-memo-output="first_opinion_title"><?=h($values['first_opinion_title'])?></div>
            <div class="memo-opinion"><div class="memo-check"><span data-memo-check="first_decision:approved"><?= $values['first_decision']==='approved'?'☑':'☐' ?></span> เห็นชอบ</div><div class="memo-check"><span data-memo-check="first_decision:other"><?= $values['first_decision']==='other'?'☑':'☐' ?></span> อื่น ๆ <span data-memo-output="first_other" data-placeholder="........................................................"><?=h($values['first_other']!==''?$values['first_other']:'........................................................')?></span></div><div class="memo-opinion-signature">(<span data-memo-output="first_signer_name"><?=h($values['first_signer_name'])?></span>)<div data-memo-output="first_signer_position"><?=h($values['first_signer_position'])?></div></div></div>
            <div class="memo-page-2-title" data-memo-output="second_opinion_title"><?=h($values['second_opinion_title'])?></div>
            <div class="memo-opinion"><div class="memo-check"><span data-memo-check="second_decision:approved"><?= $values['second_decision']==='approved'?'☑':'☐' ?></span> อนุมัติ</div><div class="memo-check"><span data-memo-check="second_decision:rejected"><?= $values['second_decision']==='rejected'?'☑':'☐' ?></span> ไม่อนุมัติ</div><div class="memo-opinion-signature">(<span data-memo-output="second_signer_name"><?=h($values['second_signer_name'])?></span>)<div data-memo-output="second_signer_position"><?=h($values['second_signer_position'])?></div></div></div>
          </section>
    <?php
    return (string) ob_get_clean();
}

/** DOCX uses the exact same HTML as the browser preview. */
function committeeResultWordCss(): string
{
    return <<<'CSS'
@page{size:A4;margin:15mm 20mm 20mm 30mm;}
body{margin:0;color:#111;font-family:"TH SarabunIT๙","TH Sarabun New",sans-serif;font-size:16pt;line-height:1.05;}
.memo-sheet{width:160mm;min-height:0;margin:0;padding:0;color:#111;font-family:"TH SarabunIT๙","TH Sarabun New",sans-serif;font-size:16pt;line-height:1.07;}
.memo-sheet+.memo-sheet{page-break-before:always;break-before:page;}
.memo-head{width:100%;display:table;table-layout:fixed;border-collapse:collapse;margin:0 0 3pt;}
.memo-head td{padding:0;border:0;vertical-align:top;}
.memo-head .memo-heading{font-size:29pt!important;font-weight:bold;text-align:center;}
.memo-head img{height:1.5cm;max-height:1.5cm;width:auto;}
.memo-heading{font-size:29pt;font-weight:bold;text-align:center;}
.memo-meta,.memo-meta-no{margin:2pt 0;}
.memo-label{font-size:20pt;font-weight:bold;}
.memo-value{font-size:16pt;}
.memo-office-value{font-size:15pt!important;white-space:nowrap;letter-spacing:-0.12pt;}
.memo-section{font-weight:bold;margin:8pt 0 3pt;text-indent:2.5cm;}
.memo-paragraph{font-size:16pt;line-height:1.05;text-indent:2.5cm;margin:0 0 5pt;white-space:pre-line;text-align:justify;}
.memo-signatures{margin-top:14pt;text-align:center;}
.memo-signature{text-align:center;margin:0 auto 10pt;}
.memo-signature-row{width:100%;display:table;table-layout:fixed;}
.memo-signature-row .memo-signature{display:table-cell;width:50%;text-align:center;}
.memo-page-footer{text-align:right;margin-top:10pt;}
.memo-sheet-header-number{text-align:center;font-size:16pt;margin:0 0 14pt;}
.memo-page-2-title{font-size:16pt;font-weight:bold;margin:0 0 8pt;}
.memo-opinion{margin-bottom:25mm;}
.memo-check{margin:6pt 0;}
.memo-opinion-signature{text-align:center;margin:18mm 0 10mm;white-space:pre-line;}
CSS;
}

$caseId = (int) ($_POST['case_id'] ?? $_GET['case_id'] ?? 0);
$case = $caseId > 0 ? procurementCase($caseId) : null;
$caseOptions = procurementCases();
$caseFiscalYear = $case ? thaiMemoDigits(trim((string) ($case['fiscal_year'] ?? ''))) : '';
$saved = $case ? procurementDocument($caseId, 'committee_scope_result_memo') : null;
$extra = is_array($saved['extra'] ?? null) ? $saved['extra'] : [];
$invitation = $case ? procurementDocument($caseId, 'committee_scope_invitation') : null;
$torDocument = $case ? procurementDocument($caseId, 'tor_company_purchase') : null;
$oldReport = $case ? procurementDocument($caseId, 'meeting_report') : null;
$historyId = (int) ($torDocument['extra']['history_id'] ?? $oldReport['extra']['history_id'] ?? 0);
$history = null;
$torError = '';
if ($case && $historyId > 0) {
    $stmt = db()->prepare('SELECT * FROM tor_histories WHERE id = ? AND (case_id = ? OR case_id IS NULL) LIMIT 1');
    $stmt->execute([$historyId,$caseId]);
    $history = $stmt->fetch() ?: null;
}
if ($case && !$history) {
    $stmt = db()->prepare('SELECT * FROM tor_histories WHERE case_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
    $stmt->execute([$caseId]);
    $history = $stmt->fetch() ?: null;
}
if ($case && !$history) $torError = 'ยังไม่พบประวัติ TOR ของโครงการนี้ กรุณาบันทึก TOR ขั้นตอนที่ 3 ก่อน';
$items = $history ? (json_decode((string)($history['items_json']??'[]'),true) ?: []) : [];
$committeeRaw = $history ? (json_decode((string)($history['committee_json']??'[]'),true) ?: []) : [];
if (!$committeeRaw) $committeeRaw = $case['committee'] ?? [];
$committee = [memoPerson($committeeRaw[0]??'','ประธานกรรมการ'),memoPerson($committeeRaw[1]??'','กรรมการ'),memoPerson($committeeRaw[2]??'','กรรมการ')];
$memoNo = memoField('memo_no_suffix',(string)($saved['memo_no_suffix']??''));
$docDate = memoField('doc_date',(string)($saved['doc_date']??''));
$invNo = thaiMemoDigits(trim((string)($invitation['memo_no_suffix']??'')));
$invDate = thaiMemoDigits(trim((string)($invitation['doc_date']??'')));
$count = thaiMemoDigits((string)count($items));
$defaultSubject = 'รายงานผลการพิจารณากำหนดรายละเอียดคุณลักษณะเฉพาะสำหรับการจัดซื้อยาที่องค์การเภสัชกรรมมิได้ผลิตจำหน่าย เพื่อใช้ในการรักษาพยาบาลประชาชนของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูลและเครือข่ายหน่วยบริการปฐมภูมิ';
$defaultOrigin = 'ตามบันทึกข้อความศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล ที่ชม ๕๑๐๐๖.๑๓.๑/' . ($invNo !== '' ? $invNo : '................') . ' ลงวันที่ ' . ($invDate !== '' ? $invDate : '........................') . ' เรื่อง แต่งตั้งคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง สำหรับการจัดซื้อยาเพื่อใช้ในการรักษาพยาบาลประชาชนของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูลและเครือข่ายหน่วยบริการปฐมภูมิ จำนวน ' . $count . ' รายการ นั้น';
$defaultFacts = 'บัดนี้ คณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง ซึ่งได้รับการแต่งตั้งตามบันทึกข้อความดังกล่าว ได้ดำเนินการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลางสำหรับการจัดซื้อยา จำนวน ' . $count . ' รายการ เสร็จเรียบร้อยแล้ว โดยได้พิจารณากำหนดคุณลักษณะให้เหมาะสม และสอดคล้องกับการให้บริการรักษาพยาบาลของหน่วยบริการ รายละเอียดปรากฏตามเอกสารที่แนบมาพร้อมบันทึกฉบับนี้';
$defaultConsider = 'เพื่อโปรดพิจารณาให้ความเห็นชอบรายละเอียดคุณลักษณะเฉพาะและราคากลางสำหรับการจัดซื้อยาดังกล่าว เพื่อใช้เป็นแนวทางในการดำเนินการจัดซื้อจัดจ้างตามพระราชบัญญัติการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. ๒๕๖๐ และระเบียบที่เกี่ยวข้องต่อไป';
$defaults = [
 'office'=>'กองสาธารณสุข ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล อ.สันกำแพง โทร.๐๕๓๓๓ ๑๓๖๒',
 'subject'=>$defaultSubject,'recipient'=>'นายกองค์การบริหารส่วนจังหวัดเชียงใหม่',
 'origin'=>$defaultOrigin,'facts'=>$defaultFacts,'consideration'=>$defaultConsider,
 'conclusion'=>'จึงเรียนมาเพื่อโปรดพิจารณากำหนดรายละเอียดคุณลักษณะเฉพาะตามที่เสนอมาพร้อมนี้',
 'first_opinion_title'=>'ความเห็นของผู้อำนวยการโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง',
 'first_signer_name'=>'นายสุวิทย์ วรเจริญ',
 'first_signer_position'=>'ผู้อำนวยการโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง',
 'second_opinion_title'=>'ความเห็นของนายกองค์การบริหารส่วนจังหวัดเชียงใหม่',
 'second_signer_name'=>'นายสุวิทย์ วรเจริญ',
 'second_signer_position'=>"ผู้อำนวยการ รพ.สต.บ้านสันโค้ง ปฏิบัติราชการแทน\nนายกองค์การบริหารส่วนจังหวัดเชียงใหม่",
 'first_decision'=>'','first_other'=>'','second_decision'=>''
];
$values=[];
foreach ($defaults as $key=>$value) $values[$key]=memoField($key, (string)($extra[$key]??$value));
$error='';
if (($_POST['action']??'')==='save_document') {
    if (!$case) $error='กรุณาเลือกโครงการก่อนบันทึก';
    elseif (!$history) $error='กรุณาบันทึก TOR ก่อนบันทึกข้อความฉบับนี้';
    elseif (!in_array($values['first_decision'], ['','approved','other'],true) || !in_array($values['second_decision'],['','approved','rejected'],true)) $error='ค่าผลการพิจารณาไม่ถูกต้อง';
    else {
        $values['history_id']=(int)$history['id'];
        saveProcurementDocument($caseId,'committee_scope_result_memo',$memoNo,$docDate,$values);
        header('Location: committee_scope_result_memo.php?case_id='.$caseId.'&saved=1#memo-preview',true,303);
        exit;
    }
}
if (($_POST['action'] ?? '') === 'word_document') {
    if (!$case) $error = 'กรุณาเลือกชุดเอกสารก่อนดาวน์โหลด Word';
    elseif (!$history) $error = 'ยังไม่พบ TOR ของชุดเอกสารนี้ กรุณาบันทึก TOR ก่อน';
    else {
        require_once __DIR__ . '/core/word_export_lib.php';
        $name = torDocumentFilename($caseId, 5, $docDate, $memoNo, (string)($case['supplier_name'] ?? ''));
        try {
            outputWordHtml(committeeResultDocumentHtml($values, $memoNo, $docDate, $committee, true), $name, committeeResultWordCss(), 'principle');
        } catch (Throwable $wordError) {
            error_log('Result memo DOCX export failed: '.$wordError->getMessage());
            $error = 'สร้างไฟล์ Word ไม่สำเร็จ กรุณาตรวจสอบ PHP ZipArchive และ core/word_export_lib.php';
        }
    }
}
?>

<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>บันทึกข้อความ รายงานผลการพิจารณาฯ | TOR Report</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  :root { color-scheme: light; --ink:#172033; --muted:#64748b; --line:#e2e8f0; --primary:#4f46e5; --surface:#fff; }
  * { box-sizing:border-box; }
  html { scroll-behavior:smooth; }
  body.result-memo-app { margin:0; min-width:320px; background:#f6f7fb; color:var(--ink); font-family:"Noto Sans Thai","Leelawadee UI",Tahoma,system-ui,sans-serif; line-height:1.55; }
  button,input,textarea,select { font:inherit; }
  [hidden] { display:none!important; }
  button,a,input,textarea,select { -webkit-tap-highlight-color:transparent; }
  button:focus-visible,a:focus-visible,input:focus-visible,textarea:focus-visible,select:focus-visible,summary:focus-visible { outline:3px solid rgba(79,70,229,.35); outline-offset:2px; }
  .result-layout { width:min(100% - 36px,1510px); margin:0 auto; padding:23px 0 52px; }
  .result-top { display:flex; flex-wrap:wrap; justify-content:space-between; gap:12px 20px; align-items:flex-end; margin-bottom:18px; }
  .result-eyebrow { font-size:12px; color:#6366f1; font-weight:750; letter-spacing:.03em; }
  .result-title { margin:4px 0; font-size:clamp(20px,2.2vw,27px); font-weight:800; line-height:1.35; }
  .result-subtitle { margin:0; font-size:13px; color:var(--muted); }
  .result-top-links { display:flex; flex-wrap:wrap; gap:8px; }
  .result-link { display:inline-flex; align-items:center; justify-content:center; min-height:38px; border:1px solid #dce2ee; border-radius:10px; padding:7px 12px; background:#fff; color:#475569; font-size:13px; font-weight:700; text-decoration:none; }
  .result-link:hover { border-color:#a5b4fc; color:#4338ca; }
  .result-workspace { display:grid; grid-template-columns:minmax(0,1fr) minmax(340px,400px); align-items:start; gap:20px; }
  .result-preview-column { order:1; min-width:0; }
  .result-editor-column { order:2; min-width:0; }
  .result-preview-heading { display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:8px 14px; margin-bottom:11px; }
  .result-preview-heading h2 { margin:0; font-size:15px; font-weight:800; }
  .result-preview-hint { color:#64748b; font-size:12px; }
  .result-preview-toolbar { display:flex; gap:8px; flex-wrap:wrap; }
  .result-small-button { border:1px solid #dce2ee; border-radius:9px; background:#fff; color:#475569; font-size:12px; font-weight:700; padding:7px 10px; cursor:pointer; }
  .result-small-button:hover { border-color:#a5b4fc; }
  .result-preview-stage { width:100%; position:relative; min-width:0; }
  .result-document-stack { width:210mm; margin:0 auto; transform-origin:top left; }
  .result-editor-shell { position:sticky; top:18px; max-height:calc(100dvh - 36px); overflow-y:auto; overscroll-behavior:contain; background:#fff; border:1px solid #e2e8f0; border-radius:18px; box-shadow:0 13px 38px rgba(51,65,85,.09); }
  .result-editor-head { position:relative; padding:17px 18px 14px; border-bottom:1px solid #e7eaf1; background:linear-gradient(120deg,#eef2ff,#fff 72%); }
  .result-editor-head h2 { margin:0; font-size:17px; font-weight:800; }
  .result-editor-head p { color:#64748b; font-size:12px; margin:4px 0 0; }
  .result-case-select { padding:14px 18px 12px; border-bottom:1px solid #e7eaf1; }
  .result-field-label { display:block; color:#334155; font-size:12px; font-weight:700; line-height:1.5; }
  .result-input { display:block; width:100%; min-width:0; margin-top:5px; padding:9px 10px; min-height:41px; border:1px solid #d7dfed; border-radius:9px; background:#fff; color:#1e293b; font-size:14px; line-height:1.5; outline:0; }
  .result-input:focus { border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.11); }
  .result-date-wrap { display:flex; gap:6px; align-items:stretch; }
  .result-date-wrap .result-input { flex:1; width:min(100%,1px); }
  .result-date-button { flex:0 0 auto; align-self:flex-end; min-height:41px; margin-top:5px; border:1px solid #d7dfed; background:#f8fafc; border-radius:9px; padding:8px 9px; color:#475569; font-size:12px; font-weight:700; cursor:pointer; }
  .result-date-button:hover { border-color:#a5b4fc; color:#4338ca; }
  .result-native-date { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
  textarea.result-input { min-height:76px; resize:vertical; }
  .result-field-help { margin:5px 0 0; color:#64748b; font-size:11px; line-height:1.5; }
  .result-case-hint { margin:8px 0 0; color:#64748b; font-size:11px; }
  .result-facts { display:flex; gap:6px; flex-wrap:wrap; margin-top:10px; }
  .result-fact { padding:4px 8px; border-radius:8px; background:#f1f5f9; color:#475569; font-size:11px; font-weight:650; overflow-wrap:anywhere; }
  .result-message { padding:11px 13px; border-radius:10px; margin-bottom:10px; font-size:13px; line-height:1.55; }
  .result-message.success { color:#166534; background:#f0fdf4; border:1px solid #bbf7d0; }
  .result-message.error { color:#9f1239; background:#fff1f2; border:1px solid #fecdd3; }
  .result-message.warning { color:#92400e; background:#fffbeb; border:1px solid #fde68a; }
  .result-alerts { margin:0 0 13px; }
  .result-alerts .result-message { margin-bottom:8px; }
  .result-form { padding:0; }
  .result-group { border-bottom:1px solid #e7eaf1; }
  .result-group:last-of-type { border-bottom:0; }
  .result-group-title { display:flex; align-items:center; gap:10px; padding:14px 18px; min-height:52px; }
  details.result-group > summary { list-style:none; cursor:pointer; }
  details.result-group > summary::-webkit-details-marker { display:none; }
  .result-group-number { display:inline-grid; place-items:center; width:26px; height:26px; flex:0 0 26px; border-radius:8px; background:#e0e7ff; color:#4338ca; font-size:12px; font-weight:850; }
  .result-group-heading { display:flex; flex-direction:column; min-width:0; flex:1; }
  .result-group-heading strong { color:#1e293b; font-size:13px; font-weight:800; }
  .result-group-heading small { color:#64748b; font-size:11px; font-weight:500; }
  .result-chevron { color:#64748b; transition:transform .15s ease; font-size:15px; }
  details.result-group[open] .result-chevron { transform:rotate(180deg); }
  .result-group-content { padding:0 18px 16px; display:grid; gap:12px; }
  .result-two-col { display:grid; grid-template-columns:1fr 1fr; gap:11px; }
  .result-divider { height:1px; width:100%; background:#edf0f4; margin:2px 0; }
  .result-subheading { margin:3px 0 0; color:#475569; font-size:12px; font-weight:800; }
  .result-form-actions { position:sticky; bottom:0; z-index:5; padding:12px 18px 14px; background:#fff; border-top:1px solid #e2e8f0; box-shadow:0 -9px 24px rgba(30,41,59,.05); }
  .result-primary-button { display:flex; width:100%; align-items:center; justify-content:center; border:0; border-radius:10px; min-height:44px; padding:9px 14px; background:#4f46e5; color:#fff; font-size:14px; font-weight:800; cursor:pointer; }
  .result-primary-button:hover { background:#4338ca; }
  .result-primary-button:disabled { background:#94a3b8; cursor:not-allowed; }
  .result-secondary-actions { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px; }
  .result-action { display:flex; justify-content:center; align-items:center; padding:8px; min-height:37px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:9px; color:#334155; font-weight:700; font-size:12px; cursor:pointer; text-decoration:none; }
  .result-action:hover { border-color:#c7d2fe; color:#4338ca; }
  .result-dirty-state { min-height:17px; font-size:11px; color:#b45309; margin:5px 0 0; }
  .result-empty { max-width:800px; margin:auto; background:white; text-align:center; border:1px solid #e2e8f0; border-radius:18px; padding:54px 20px; color:#475569; }
  .result-empty h2 { font-size:18px; margin:0 0 8px; color:#172033; }
  .result-empty p { margin:0; font-size:13px; }
  /* A4 document canvas is deliberately isolated from the application's UI. */
  .memo-sheet { width:210mm; min-height:297mm; padding:15mm 20mm 20mm 30mm; margin:0 auto 12mm; background:#fff; color:#111; font-family:"TH Sarabun New","Sarabun",sans-serif; font-size:16pt; line-height:1.2; box-shadow:0 10px 28px rgba(15,23,42,.14); }
  .memo-sheet, .memo-sheet * { color:#111; }
  .memo-sheet .memo-head { display:table; width:100%; table-layout:fixed; border-collapse:collapse; }
  .memo-sheet .memo-head td { padding:0; border:0; vertical-align:top; }
  .memo-sheet .garuda { width:auto; height:1.5cm; display:block; }
  .memo-sheet .memo-heading { text-align:center; font-size:29pt; font-weight:700; line-height:1; padding-top:8pt; margin:0; }
  .memo-sheet .memo-meta { display:grid; grid-template-columns:auto minmax(0,1fr); gap:0 8pt; align-items:baseline; margin-top:3pt; }
  .memo-sheet .memo-meta-no { display:grid; grid-template-columns:auto minmax(0,1.4fr) auto minmax(0,1fr); gap:0 8pt; align-items:baseline; margin-top:3pt; }
  .memo-sheet .memo-label { font-size:20pt; font-weight:700; line-height:1; white-space:nowrap; }
  .memo-sheet .memo-value { font-size:16pt; line-height:1.2; overflow-wrap:break-word; }
  .memo-sheet .memo-office-value { font-size:15pt!important; line-height:1.2; letter-spacing:-0.12pt; white-space:nowrap; overflow-wrap:normal; }
  .memo-sheet .memo-section { font-weight:bold; margin-top:12pt; margin-bottom:3pt; text-indent:2.5cm; }
  .memo-sheet .memo-paragraph { margin:0 0 5pt; text-align:justify; text-align-last:left; text-justify:inter-character; text-indent:2.5cm; white-space:pre-line; overflow-wrap:break-word; }
  .memo-sheet .memo-signatures { margin-top:13pt; line-height:1.15; }
  .memo-sheet .memo-signature { text-align:center; margin:0 auto 12pt; max-width:125mm; break-inside:avoid; }
  .memo-sheet .memo-signature .name { margin-top:2pt; }
  .memo-sheet .memo-signature-row { display:grid; grid-template-columns:1fr 1fr; column-gap:5mm; }
  .memo-sheet .memo-signature-row .memo-signature { max-width:none; font-size:16pt; }
  .memo-sheet .memo-page-footer { text-align:right; margin-top:9pt; font-weight:700; }
  .memo-sheet .memo-sheet-header-number { text-align:center; font-size:16pt; margin:-5mm 0 18mm; }
  .memo-sheet .memo-page-2-title { font-size:16pt; font-weight:700; margin:0 0 8pt; }
  .memo-sheet .memo-opinion { margin-bottom:27mm; }
  .memo-sheet .memo-check { margin:6pt 0; }
  .memo-sheet .memo-opinion-signature { text-align:center; margin:20mm 0 0 auto; max-width:120mm; white-space:pre-line; }
  .memo-sheet .memo-page-footer, .memo-sheet .memo-sheet-header-number { font-weight:700; }
  @media (max-width:1260px) {
    .result-workspace { grid-template-columns:minmax(0,1fr); gap:23px; }
    .result-editor-column { order:1; }
    .result-preview-column { order:2; }
    .result-editor-shell { position:static; max-height:none; overflow:visible; }
    .result-form-actions { position:static; box-shadow:none; }
    .result-preview-heading { max-width:850px; margin-left:auto; margin-right:auto; }
  }
  @media (max-width:640px) {
    .result-layout { width:calc(100% - 24px); padding:15px 0 28px; }
    .result-top { margin-bottom:12px; }
    .result-top-links { width:100%; }
    .result-link { flex:1; font-size:12px; }
    .result-editor-head, .result-case-select { padding-left:14px; padding-right:14px; }
    .result-group-title { padding:13px 14px; }
    .result-group-content { padding:0 14px 14px; }
    .result-form-actions { padding:10px 14px 12px; }
    .result-two-col { grid-template-columns:1fr; }
    .result-editor-shell { border-radius:13px; }
    .result-preview-heading { align-items:flex-start; }
    .result-document-stack { margin-left:0; }
  }
  @counter-style result-memo-thai {system:numeric;symbols:"๐" "๑" "๒" "๓" "๔" "๕" "๖" "๗" "๘" "๙";suffix:"";}

        @font-face {font-family:"WorkflowThaiPrint";src:url("assets/fonts/THSarabunIT9-Regular.ttf") format("truetype");font-weight:400;font-style:normal;}
        @font-face {font-family:"WorkflowThaiPrint";src:url("assets/fonts/THSarabunIT9-Bold.ttf") format("truetype");font-weight:700;font-style:normal;}
  @page resultMemo {size:A4;margin:15mm 20mm 20mm 30mm;@top-center {content:"- " counter(page,result-memo-thai) " -";font-family:"WorkflowThaiPrint","TH Sarabun New",sans-serif;font-size:16pt;font-weight:400;vertical-align:middle;margin:0;padding:0;}}
  @page resultMemo:first {margin-top:15mm;@top-center{content:none;}}
  @media print {
    html,body.result-memo-app { margin:0!important; padding:0!important; background:#fff!important; width:auto!important; min-width:0!important; }
    .no-print, .result-top, .result-editor-column, .result-preview-heading { display:none!important; }
    .result-layout, .result-workspace, .result-preview-column, .result-preview-stage { display:block!important; width:auto!important; max-width:none!important; margin:0!important; padding:0!important; overflow:visible!important; height:auto!important; }
    .result-document-stack { display:block!important; width:auto!important; margin:0!important; padding:0!important; transform:none!important; }
    .memo-sheet { page:resultMemo; font-family:"WorkflowThaiPrint","TH Sarabun New",sans-serif!important; width:auto!important; min-height:0; height:auto; padding:0!important; margin:0!important; box-shadow:none!important; break-after:page; page-break-after:always; }
    .memo-sheet-header-number { display:none!important; }
    .memo-sheet:last-child { break-after:auto; page-break-after:auto; }
  }
</style>
<link rel="stylesheet" href="assets/document-workspace.css?v=20261124-1">
<style>
/* Result memo screen-only refinement. Printing and Word export remain unchanged. */
@media screen {
  body.result-memo-app .result-top {align-items:center;margin-bottom:8px;min-height:0;}
  body.result-memo-app .result-top .result-title {font-size:17px!important;margin:0!important;line-height:1.35;}
  body.result-memo-app .result-preview-title-line {display:flex;align-items:baseline;gap:9px;flex-wrap:wrap;min-width:0;}
  body.result-memo-app .result-preview-title-line h2 {font-size:13px!important;}
  body.result-memo-app .result-preview-hint {font-size:11px!important;}
  body.result-memo-app .result-preview-toolbar {margin-left:auto;}
  body.result-memo-app .result-form-actions {padding:9px 12px!important;}
  body.result-memo-app .result-command-row {display:flex;align-items:center;justify-content:flex-start;gap:7px;flex-wrap:wrap;}
  body.result-memo-app .result-command-row > button {
    display:inline-flex!important;align-items:center;justify-content:center;
    flex:0 1 auto!important;width:auto!important;max-width:100%!important;
    min-height:34px!important;padding:7px 11px!important;
    border-radius:8px!important;font-size:12px!important;font-weight:700!important;
    line-height:1.35!important;white-space:normal!important;
  }
  body.result-memo-app .result-command-row .result-primary-button {background:#155ba0!important;color:#fff!important;}
  body.result-memo-app .result-command-row .result-primary-button:disabled {background:#94a3b8!important;}
  body.result-memo-app .result-dirty-state:empty {display:none;}
  body.result-memo-app .result-dirty-state:not(:empty) {margin-top:6px!important;}
}
</style>
</head>
<body class="docws docws-result-page result-memo-app">
<div class="no-print"><?php require __DIR__.'/components/navbar.php'; require __DIR__.'/components/workflow_steps.php'; ?></div>
<main class="result-layout">
  <header class="result-top no-print">
    <h1 class="result-title">บันทึกข้อความ · รายงานผลการพิจารณาฯ</h1>
  </header>
  <?php if ($case): ?>
  <div class="result-workspace docws-layout" data-docws-layout="1">
    <section class="result-preview-column docws-preview-pane" id="memo-preview" aria-label="ตัวอย่างบันทึกข้อความ" data-docws-preview="1">
      <div class="result-preview-heading no-print">
        <div class="result-preview-title-line"><h2>ตัวอย่างเอกสาร</h2><span class="result-preview-hint">A4 · ๒ หน้า</span></div>
        <div class="result-preview-toolbar"><button type="button" class="result-small-button" id="result-preview-zoom" aria-pressed="false" aria-label="สลับขนาดพรีวิว">ขนาดจริง</button></div>
      </div>
      <div class="result-preview-stage" id="result-preview-stage">
        <div class="result-document-stack" id="result-document-stack">
          <?= committeeResultDocumentHtml($values, $memoNo, $docDate, $committee) ?>
        </div>
      </div>
    </section>
    <aside class="result-editor-column no-print docws-editor-pane" aria-label="แผงกรอกข้อมูลเอกสาร" data-docws-editor="1">
      <div class="result-editor-shell">
        <div class="result-editor-head"><h2>ข้อมูลเอกสาร</h2><p>กรอกข้อมูลหลักก่อน ส่วนข้อความและผู้ลงนามเปิดแก้ได้ตามต้องการ</p></div>
        <div class="result-case-select">
          <form method="get" action="committee_scope_result_memo.php" id="result-case-form">
            <label for="case_id" class="result-field-label">ชุดเอกสารจัดซื้อ</label>
            <select id="case_id" name="case_id" class="result-input" aria-label="เลือกชุดเอกสารจัดซื้อ">
              <option value="0">-- เลือกโครงการ --</option>
              <?php foreach ($caseOptions as $choice): ?><option value="<?= (int)$choice['id'] ?>" <?= (int)$choice['id']===$caseId?'selected':'' ?>>#<?= (int)$choice['id'] ?> — <?=h((string)$choice['title'] . ' · ' . torCaseSupplierLabel($choice))?></option><?php endforeach ?>
            </select>
          </form>
          <div class="result-facts"><span class="result-fact">ปีงบประมาณ พ.ศ. <?= h($caseFiscalYear !== '' ? $caseFiscalYear : 'ยังไม่ระบุ') ?></span><span class="result-fact">รายการยา <?=h($count)?> รายการ</span><span class="result-fact">อ้างอิงหนังสือแต่งตั้ง <?=h($invNo!==''?$invNo:'ยังไม่ระบุ')?></span></div>
          <p class="result-case-hint">รายการยาและกรรมการดึงจาก TOR ที่บันทึกไว้ ไม่ต้องกรอกซ้ำ</p>
        </div>
        <div class="result-alerts" style="padding:12px 18px 0; margin:0;">
          <?php if (isset($_GET['saved'])): ?><div class="result-message success" role="status">บันทึกข้อมูลเรียบร้อยแล้ว</div><?php endif; ?>
          <?php if ($error): ?><div class="result-message error" role="alert"><?=h($error)?></div><?php endif; ?>
          <?php if ($torError): ?><div class="result-message warning" role="alert"><?=h($torError)?></div><?php endif; ?>
        </div>
        <form id="memo-form" method="post" class="result-form">
          <input type="hidden" name="action" value="save_document"><input type="hidden" name="csrf_token" value="<?=h(csrfToken())?>"><input type="hidden" name="case_id" value="<?= $caseId ?>">
          <section class="result-group" aria-labelledby="result-group-main">
            <div class="result-group-title"><span class="result-group-number">1</span><span class="result-group-heading"><strong id="result-group-main">เลขหนังสือและวันที่</strong><small>ข้อมูลที่ต้องกรอกก่อนบันทึก</small></span></div>
            <div class="result-group-content">
              <div class="result-two-col">
                <label class="result-field-label">เลขหนังสือ หลัง /<input class="result-input" name="memo_no_suffix" value="<?=h($memoNo)?>" placeholder="เช่น 123" autocomplete="off"></label>
                <label class="result-field-label">วันที่บันทึก<span class="result-date-wrap"><input class="result-input" name="doc_date" id="result-doc-date" value="<?=h($docDate)?>" placeholder="เช่น ๒๐ ต.ค. ๒๕๖๙" autocomplete="off"><button class="result-date-button" type="button" id="result-date-button" aria-label="เปิดปฏิทินเลือกวันที่">ปฏิทิน</button></span><input class="result-native-date" type="date" id="result-native-date" tabindex="-1" aria-label="วันที่ในปฏิทิน"></label>
              </div>
              <p class="result-field-help">เลขหนังสือใช้คำนำหน้า ชม ๕๑๐๐๖.๑๓.๑/ อัตโนมัติ</p>
            </div>
          </section>
          <details class="result-group" id="result-group-body">
            <summary class="result-group-title"><span class="result-group-number">2</span><span class="result-group-heading"><strong>เนื้อหาและเอกสารอ้างอิง</strong><small>ต้นเรื่อง ข้อเท็จจริง และข้อพิจารณา</small></span><span class="result-chevron" aria-hidden="true">⌄</span></summary>
            <div class="result-group-content">
              <label class="result-field-label">ส่วนราชการ<input class="result-input" name="office" value="<?=h($values['office'])?>"></label>
              <label class="result-field-label">เรื่อง<textarea class="result-input" name="subject" rows="3"><?=h($values['subject'])?></textarea></label>
              <label class="result-field-label">เรียน<input class="result-input" name="recipient" value="<?=h($values['recipient'])?>"></label>
              <div class="result-divider"></div>
              <label class="result-field-label">ต้นเรื่อง<textarea class="result-input" name="origin" rows="5"><?=h($values['origin'])?></textarea></label>
              <label class="result-field-label">ข้อเท็จจริง<textarea class="result-input" name="facts" rows="5"><?=h($values['facts'])?></textarea></label>
              <label class="result-field-label">ข้อพิจารณา<textarea class="result-input" name="consideration" rows="4"><?=h($values['consideration'])?></textarea></label>
              <label class="result-field-label">ข้อความสรุป<textarea class="result-input" name="conclusion" rows="3"><?=h($values['conclusion'])?></textarea></label>
            </div>
          </details>
          <section class="result-group" aria-labelledby="result-group-opinion">
            <div class="result-group-title"><span class="result-group-number">3</span><span class="result-group-heading"><strong id="result-group-opinion">ผลการพิจารณา</strong><small>แสดงเครื่องหมายเลือกในเอกสารหน้าที่ 2</small></span></div>
            <div class="result-group-content">
              <label class="result-field-label">ความเห็นส่วนที่ 1
                <select class="result-input" name="first_decision">
                  <option value="" <?= $values['first_decision']===''?'selected':'' ?>>ยังไม่เลือก</option>
                  <option value="approved" <?= $values['first_decision']==='approved'?'selected':'' ?>>เห็นชอบ</option>
                  <option value="other" <?= $values['first_decision']==='other'?'selected':'' ?>>อื่น ๆ</option>
                </select>
              </label>
              <label class="result-field-label" id="result-first-other-wrap" <?= $values['first_decision']!=='other'?'hidden':'' ?>>ระบุความเห็นอื่น ๆ<input class="result-input" name="first_other" value="<?=h($values['first_other'])?>"></label>
              <label class="result-field-label">ความเห็นส่วนที่ 2
                <select class="result-input" name="second_decision">
                  <option value="" <?= $values['second_decision']===''?'selected':'' ?>>ยังไม่เลือก</option>
                  <option value="approved" <?= $values['second_decision']==='approved'?'selected':'' ?>>อนุมัติ</option>
                  <option value="rejected" <?= $values['second_decision']==='rejected'?'selected':'' ?>>ไม่อนุมัติ</option>
                </select>
              </label>
            </div>
          </section>
          <details class="result-group" id="result-group-signers">
            <summary class="result-group-title"><span class="result-group-number">4</span><span class="result-group-heading"><strong>ผู้ลงนามและตำแหน่ง</strong><small>แก้ไขเฉพาะเมื่อข้อมูลผู้ลงนามเปลี่ยน</small></span><span class="result-chevron" aria-hidden="true">⌄</span></summary>
            <div class="result-group-content">
              <div class="result-subheading">ความเห็นส่วนที่ 1</div>
              <label class="result-field-label">หัวข้อความเห็น<input class="result-input" name="first_opinion_title" value="<?=h($values['first_opinion_title'])?>"></label>
              <label class="result-field-label">ชื่อผู้ลงนาม<input class="result-input" name="first_signer_name" value="<?=h($values['first_signer_name'])?>"></label>
              <label class="result-field-label">ตำแหน่งผู้ลงนาม<textarea class="result-input" name="first_signer_position" rows="2"><?=h($values['first_signer_position'])?></textarea></label>
              <div class="result-divider"></div>
              <div class="result-subheading">ความเห็นส่วนที่ 2</div>
              <label class="result-field-label">หัวข้อความเห็น<input class="result-input" name="second_opinion_title" value="<?=h($values['second_opinion_title'])?>"></label>
              <label class="result-field-label">ชื่อผู้ลงนาม<input class="result-input" name="second_signer_name" value="<?=h($values['second_signer_name'])?>"></label>
              <label class="result-field-label">ตำแหน่งผู้ลงนาม<textarea class="result-input" name="second_signer_position" rows="3"><?=h($values['second_signer_position'])?></textarea></label>
            </div>
          </details>
          <div class="result-form-actions" aria-label="คำสั่งเอกสาร">
            <div class="result-command-row">
              <button type="submit" class="result-primary-button" <?= !$history?'disabled title="ต้องบันทึก TOR ก่อน"':'' ?>>บันทึกเอกสาร</button>
              <button type="submit" name="action" value="word_document" class="result-action" <?= !$history?'disabled':'' ?>>ดาวน์โหลด Word</button>
              <button type="button" class="result-action" onclick="window.print()">พิมพ์ / PDF</button>
            </div>
            <p class="result-dirty-state" id="result-dirty-state" role="status" aria-live="polite"></p>
          </div>
        </form>
      </div>
    </aside>
  </div>
  <?php else: ?>
    <div class="result-empty no-print"><h2>เลือกชุดเอกสารเพื่อเริ่มต้น</h2><p>กรุณาเลือกโครงการจากรายการด้านล่าง</p>
      <form method="get" action="committee_scope_result_memo.php" style="max-width:520px;margin:18px auto 0;text-align:left;"><label class="result-field-label" for="empty-case-select">ชุดเอกสารจัดซื้อ</label><select id="empty-case-select" name="case_id" class="result-input" required><option value="">-- เลือกโครงการ --</option><?php foreach($caseOptions as $choice): ?><option value="<?= (int)$choice['id'] ?>">#<?= (int)$choice['id'] ?> — <?= h((string)$choice['title'] . ' · ' . torCaseSupplierLabel($choice)) ?></option><?php endforeach ?></select><button class="result-primary-button" style="margin-top:12px;" type="submit">เปิดชุดเอกสาร</button></form>
    </div>
  <?php endif; ?>
</main>
<script>
(function () {
  'use strict';
  const form = document.getElementById('memo-form');
  if (!form) return;
  const stage = document.getElementById('result-preview-stage');
  const stack = document.getElementById('result-document-stack');
  const zoomButton = document.getElementById('result-preview-zoom');
  const dirtyState = document.getElementById('result-dirty-state');
  const otherWrap = document.getElementById('result-first-other-wrap');
  const selectCase = document.getElementById('case_id');
  const dateInput = document.getElementById('result-doc-date');
  const nativeDateInput = document.getElementById('result-native-date');
  const dateButton = document.getElementById('result-date-button');
  const thaiMonths = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
  function toArabicDigits(text) { return String(text).replace(/[๐-๙]/g, function (d) { return String('๐๑๒๓๔๕๖๗๘๙'.indexOf(d)); }); }
  function parseThaiDate(text) {
    const normalized=toArabicDigits(text).trim();
    const match=normalized.match(/^(\d{1,2})\s+([^\s]+)\s+(\d{4})$/);
    if (!match) return '';
    const m=thaiMonths.indexOf(match[2]);
    const year=Number(match[3])-543;
    if (m<0 || year<1900 || Number(match[1])<1 || Number(match[1])>31) return '';
    return String(year).padStart(4,'0')+'-'+String(m+1).padStart(2,'0')+'-'+String(Number(match[1])).padStart(2,'0');
  }
  dateButton?.addEventListener('click', function() {
    if (!nativeDateInput || !dateInput) return;
    nativeDateInput.value=parseThaiDate(dateInput.value)||nativeDateInput.value;
    try { if (nativeDateInput.showPicker) nativeDateInput.showPicker(); else nativeDateInput.click(); }
    catch (e) { nativeDateInput.focus(); nativeDateInput.click(); }
  });
  nativeDateInput?.addEventListener('change', function() {
    if (!dateInput || !nativeDateInput.value) return;
    const parts=nativeDateInput.value.split('-').map(Number);
    dateInput.value=thaiDigits(parts[2]+' '+thaiMonths[parts[1]-1]+' '+(parts[0]+543));
    dateInput.dispatchEvent(new Event('input',{bubbles:true}));
  });
  const caseForm = document.getElementById('result-case-form');
  let dirty = false;
  let fullSize = false;
  let currentScale = 1;
  function thaiDigits(text) {
    return String(text).replace(/[0-9]/g, function (digit) { return '๐๑๒๓๔๕๖๗๘๙'[Number(digit)]; });
  }
  function updatePreview() {
    form.querySelectorAll('[name]').forEach(function(field) {
      if (!field.name) return;
      const targets = document.querySelectorAll('[data-memo-output="' + field.name + '"]');
      targets.forEach(function(target) {
        const value = field.value || '';
        const prepared = target.dataset.digits === 'thai' ? thaiDigits(value) : value;
        target.textContent = prepared || target.dataset.placeholder || '';
      });
    });
    document.querySelectorAll('[data-memo-check]').forEach(function(target) {
      const parts = target.dataset.memoCheck.split(':');
      const field = form.elements.namedItem(parts[0]);
      target.textContent = field && field.value === parts[1] ? '☑' : '☐';
    });
    otherWrap.hidden = form.elements.namedItem('first_decision').value !== 'other';
    fitPreview();
  }
  function fitPreview() {
    if (!stage || !stack) return;
    stack.style.transform = 'none';
    const naturalWidth = stack.offsetWidth;
    const availableWidth = stage.clientWidth;
    if (!naturalWidth || !availableWidth) return;
    currentScale = (!fullSize && availableWidth < naturalWidth) ? availableWidth / naturalWidth : 1;
    if (currentScale < 1) {
      stack.style.marginLeft = '0';
      stack.style.marginRight = '0';
      stack.style.transform = 'scale(' + currentScale + ')';
      stage.style.height = Math.ceil(stack.offsetHeight * currentScale) + 'px';
      stage.style.overflowX = 'hidden';
    } else {
      stack.style.marginLeft = '';
      stack.style.marginRight = '';
      stack.style.transform = 'none';
      stage.style.height = '';
      stage.style.overflowX = fullSize && availableWidth < naturalWidth ? 'auto' : '';
    }
    if (zoomButton) { zoomButton.textContent = fullSize ? 'พอดีหน้าจอ' : 'ขนาดจริง'; zoomButton.setAttribute('aria-pressed', fullSize?'true':'false'); }
  }
  form.addEventListener('input', function () { dirty=true; dirtyState.textContent='มีข้อมูลแก้ไขที่ยังไม่ได้บันทึก'; updatePreview(); });
  form.addEventListener('change', function () { dirty=true; dirtyState.textContent='มีข้อมูลแก้ไขที่ยังไม่ได้บันทึก'; updatePreview(); });
  form.addEventListener('submit', function () { dirty=false; });
  if (caseForm && selectCase) {
    const initialValue=selectCase.value;
    selectCase.addEventListener('change', function() {
      if (dirty && !window.confirm('มีข้อมูลที่ยังไม่ได้บันทึก ต้องการเปลี่ยนโครงการหรือไม่?')) { selectCase.value=initialValue; return; }
      caseForm.submit();
    });
  }
  document.getElementById('result-view-preview')?.addEventListener('click', function() { document.getElementById('memo-preview')?.scrollIntoView({behavior:'smooth',block:'start'}); });
  zoomButton?.addEventListener('click',function() { fullSize=!fullSize; fitPreview(); });
  window.addEventListener('resize',fitPreview,{passive:true});
  if ('ResizeObserver' in window && stage) {
    let lastWidth = -1;
    new ResizeObserver(function() {
      const width = stage.clientWidth;
      if (width !== lastWidth) { lastWidth = width; fitPreview(); }
    }).observe(stage);
  }
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitPreview);
  window.addEventListener('beforeprint', function() { if (stack) stack.style.transform='none'; if(stage) stage.style.height=''; });
  window.addEventListener('afterprint',fitPreview);
  updatePreview();
})();
</script>

<script>
/* Display-only Thai digits. Field names, IDs, URLs and stored numeric values are not changed. */
(() => {
    const thai = (value) => String(value).replace(/[0-9]/g, digit => '๐๑๒๓๔๕๖๗๘๙'[Number(digit)]);
    document.title = thai(document.title);
    const blocked = 'script, style, noscript, template';
    const isField = (node) => node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement;
    function visibleField(field) {
        if (!isField(field)) return false;
        if (field instanceof HTMLInputElement) {
            if (['hidden', 'date', 'datetime-local', 'time', 'month', 'week', 'checkbox', 'radio', 'file', 'submit', 'button', 'reset', 'color', 'range', 'password', 'email', 'url', 'tel'].includes(field.type)) return false;
            if (field.type === 'number') {
                // Native number inputs reject Thai numerals; retain the same field name and
                // decimal keyboard while letting the existing Thai-aware calculations parse values.
                field.type = 'text';
                field.inputMode = 'decimal';
                field.pattern = '[0-9๐-๙]+(?:[.,][0-9๐-๙]+)*';
            }
        }
        return true;
    }
    function changeField(field) {
        if (!visibleField(field)) return;
        const oldValue = field.value;
        const newValue = thai(oldValue);
        if (oldValue === newValue) return;
        let start = null, end = null;
        try { start = field.selectionStart; end = field.selectionEnd; } catch (_) {}
        field.value = newValue;
        if (start !== null && end !== null && document.activeElement === field) {
            try { field.setSelectionRange(start, end); } catch (_) {}
        }
    }
    function convertText(node) {
        if (node.nodeType !== Node.TEXT_NODE) return;
        const parent = node.parentElement;
        if (!parent || parent.closest(blocked + ', textarea')) return;
        const updated = thai(node.nodeValue || '');
        if (updated !== node.nodeValue) node.nodeValue = updated;
    }
    function visit(node, includeFields = true) {
        if (node.nodeType === Node.TEXT_NODE) { convertText(node); return; }
        if (node.nodeType !== Node.ELEMENT_NODE || node.closest(blocked)) return;
        if (includeFields && isField(node)) changeField(node);
        if (node.hasAttribute('placeholder')) node.setAttribute('placeholder', thai(node.getAttribute('placeholder')));
        if (node.hasAttribute('title')) node.setAttribute('title', thai(node.getAttribute('title')));
        const walker = document.createTreeWalker(node, NodeFilter.SHOW_TEXT);
        while (walker.nextNode()) convertText(walker.currentNode);
        if (includeFields) node.querySelectorAll('input, textarea').forEach(changeField);
        node.querySelectorAll('[placeholder], [title]').forEach(el => {
            if (el.hasAttribute('placeholder')) {
                const before = el.getAttribute('placeholder'); const after = thai(before);
                if (after !== before) el.setAttribute('placeholder', after);
            }
            if (el.hasAttribute('title')) {
                const before = el.getAttribute('title'); const after = thai(before);
                if (after !== before) el.setAttribute('title', after);
            }
        });
    }
    visit(document.body);
    const observer = new MutationObserver(records => {
        for (const mutation of records) {
            if (mutation.type === 'characterData') convertText(mutation.target);
            else for (const node of mutation.addedNodes) visit(node);
        }
    });
    observer.observe(document.body, { subtree: true, characterData: true, childList: true });
    document.addEventListener('input', event => changeField(event.target), true);
    document.addEventListener('change', event => changeField(event.target), true);
    // Covers values populated by existing click handlers (medicine choices and saved drafts).
    document.addEventListener('click', () => requestAnimationFrame(() => {
        document.querySelectorAll('input, textarea').forEach(changeField);
    }), true);
})();
</script>

<script defer src="assets/document-workspace.js?v=20261124-1"></script>
<script src="assets/tor-document-print-name.js?v=20261124-1"></script>
<script>
window.TorDocumentPrintName.init(<?= json_encode(['step'=>5,'caseId'=>$caseId,'supplier'=>(string)($case['supplier_name'] ?? ''),'dateField'=>'doc_date','numberField'=>'memo_no_suffix'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
</script>
</body>
</html>
