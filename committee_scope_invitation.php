<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require __DIR__ . '/core/word_export_lib.php';
require __DIR__ . '/core/auth.php';
requireLogin();

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function thai_digits(string $text): string
{
    return strtr($text, [
        '0' => '๐',
        '1' => '๑',
        '2' => '๒',
        '3' => '๓',
        '4' => '๔',
        '5' => '๕',
        '6' => '๖',
        '7' => '๗',
        '8' => '๘',
        '9' => '๙',
    ]);
}
function field(string $key, string $default = ''): string { return thai_digits(trim((string) ($_POST[$key] ?? $_GET[$key] ?? $default))); }
function arabic_digits(string $text): string
{
    return strtr($text, [
        '๐' => '0',
        '๑' => '1',
        '๒' => '2',
        '๓' => '3',
        '๔' => '4',
        '๕' => '5',
        '๖' => '6',
        '๗' => '7',
        '๘' => '8',
        '๙' => '9',
    ]);
}

function filename_date(string $date): string
{
    $date = trim(arabic_digits($date));
    $months = [
        'มกราคม' => '01',
        'กุมภาพันธ์' => '02',
        'มีนาคม' => '03',
        'เมษายน' => '04',
        'พฤษภาคม' => '05',
        'มิถุนายน' => '06',
        'กรกฎาคม' => '07',
        'สิงหาคม' => '08',
        'กันยายน' => '09',
        'ตุลาคม' => '10',
        'พฤศจิกายน' => '11',
        'ธันวาคม' => '12',
    ];
    foreach ($months as $month => $no) {
        if (preg_match('/(\d{1,2})\s+' . preg_quote($month, '/') . '\s+(\d{4})/u', $date, $match)) {
            return str_pad($match[1], 2, '0', STR_PAD_LEFT) . '-' . $no . '-' . $match[2];
        }
    }
    return str_replace(['/', '\\'], '-', $date);
}

/** Format the meeting agenda date as "๑๙ เดือน ตุลาคม พ.ศ. ๒๕๖๙". */
function committeeScopeAgendaDate(string $date): string
{
    $date = trim(arabic_digits($date));
    if (preg_match('/^(\d{1,2})\s+(?:เดือน\s+)?([ก-๙]+)\s+(?:พ\.ศ\.\s*)?(\d{4})$/u', $date, $match)) {
        return thai_digits($match[1] . ' เดือน ' . $match[2] . ' พ.ศ. ' . $match[3]);
    }
    return thai_digits($date);
}

/** Keep the agenda time in official form with the Thai "น." suffix. */
function committeeScopeAgendaTime(string $time): string
{
    $time = trim(thai_digits($time));
    if ($time === '' || preg_match('/น\.\s*$/u', $time)) {
        return $time;
    }
    return $time . ' น.';
}

function committeeScopeWordFilename(string $memoNoSuffix, string $docDate, string $itemCount): string
{
    return trim(
        'ชุดแจ้งคำสั่งและเชิญประชุม 2 เลขที่หนัง '
        . trim(arabic_digits($memoNoSuffix))
        . ' วันที่ '
        . filename_date($docDate)
        . ' ยา '
        . trim(arabic_digits($itemCount))
        . ' รายการ.docx'
    );
}

function committeeScopeWordHtml(array $data): string
{
    ob_start();
    ?>
    <!doctype html>
    <html lang="th">
    <head>
        <meta charset="utf-8">
        <title>บันทึกข้อความ แจ้งคำสั่งและเชิญประชุม</title>
        <style>
            @page { size: A4; margin: 25mm 20mm 20mm 30mm; }
            body { margin: 0; color: #111; font-family: "TH Sarabun New", "Sarabun", sans-serif; font-size: 16pt; line-height: 1; }
            .memo-page { width: 160mm; page-break-after: auto !important; }
            .word-page-break { page-break-before: always; mso-special-character: line-break; font-size: 1pt; line-height: 1pt; height: 1pt; }
            table { width: 100%; border-collapse: collapse; border: 0; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
            td { border: 0; padding: 0; vertical-align: top; font-size: 16pt; line-height: 1; }
            p, .justify { margin: 0; mso-margin-top-alt: 0pt; mso-margin-bottom-alt: 0pt; text-align: justify; text-justify: inter-character; -ms-text-justify: distribute; text-align-last: left; line-height: 1; mso-line-height-rule: exactly; }
            p { text-indent: 70pt; }
            .header-table { margin: 0 0 3pt; }
            .garuda { width: auto; height: 1.5cm; }
            .memo-title { text-align: center; font-size: 29pt; font-weight: 700; line-height: 1; padding-top: 8pt; }
            .meta-line { margin-top: 3pt; line-height: 1; text-align: justify; text-justify: inter-character; -ms-text-justify: distribute; text-align-last: left; }
            .department-line { white-space: nowrap; font-size: 14pt; letter-spacing: -0.25pt; }
            .memo-no-date { margin-top: 3pt; line-height: 1; white-space: nowrap; }
            .inline-label { display: inline; white-space: nowrap; font-size: 20pt; font-weight: 700; line-height: 1; margin-right: 8pt; }
            .memo-no-text { display: inline-block; width: 220pt; }
            .date-label-text { display: inline-block; width: 45pt; font-size: 20pt; font-weight: 700; line-height: 1; padding-left: 8pt; }
            .date-value-text { display: inline-block; width: 142pt; text-align: center; }
            .signature {
                margin-top: 10pt;
                text-align: center;
                color: #c00000;
                line-height: 1;
            }
            .red { color: #c00000; }
            .agenda-title, .agenda-header { text-align: center; font-weight: 700; line-height: 1; }
            .agenda-rule { width: 198pt; margin: 8pt auto 10pt; border-top: 0.5pt dashed #111; }
            .agenda-table { width: 100%; margin-top: 10pt; table-layout: fixed; }
            .agenda-table td { border: 0; padding: 0; vertical-align: top; }
            .agenda-label-cell { width: 112pt; font-weight: 700; white-space: nowrap; }
            .agenda-detail-cell { width: 340pt; }
            .agenda-topic { font-weight: 700; }
            .agenda-detail { margin-top: 2pt; text-align: justify; text-justify: inter-character; -ms-text-justify: distribute; text-align-last: left; }
            .agenda-detail-line { display: block; }
            .agenda-continuation { display: block; }
            .agenda-tail-line { border-top: 0.5pt solid #111; margin-top: 24pt; }
        </style>
    </head>
    <body>
        <div class="memo-page">
            <table class="header-table">
                <tr>
                    <td style="width:3cm;"><img src="picture/picture-1.png" class="garuda" alt=""></td>
                    <td class="memo-title">บันทึกข้อความ</td>
                    <td style="width:3cm;">&nbsp;</td>
                </tr>
            </table>

            <div class="meta-line department-line"><span class="inline-label">ส่วนราชการ</span><?= h($data['department']) ?></div>
            <div class="memo-no-date">
                <span class="inline-label">ที่</span><span class="memo-no-text">ชม ๕๑๐๐๖.๑๓.๑/<?= h($data['memo_no_suffix']) ?></span>
                <span class="date-label-text">วันที่</span><span class="date-value-text"><?= h($data['doc_date']) ?></span>
            </div>
            <div class="meta-line"><span class="inline-label">เรื่อง</span><?= h($data['subject']) ?> <span class="red">จำนวน <?= h($data['item_count']) ?> รายการ</span> ประจำปีงบประมาณ พ.ศ. <?= h($data['fiscal_year']) ?></div>
            <div class="meta-line" style="margin-top:8pt;"><span class="inline-label">เรียน</span>คณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง</div>

            <p style="margin-top:8pt;">ตามที่ท่านได้รับแต่งตั้งให้เป็นคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะขอบเขตและกำหนดราคากลาง สำหรับการ<span class="red">จัดซื้อยา จำนวน <?= h($data['item_count']) ?> รายการ</span> เพื่อสนับสนุนการให้บริการทางการแพทย์ รักษาพยาบาลประชาชนของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล ประจำปีงบประมาณ พ.ศ. <?= h($data['fiscal_year']) ?> ตามบันทึกข้อความขออนุมัติในหลักการเพื่อดำเนินการจัดซื้อยา ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล ที่ ชม ๕๑๐๐๖.๑๓.๑/<?= h($data['principle_no_suffix']) ?> ลงวันที่ <span class="red"><?= h($data['principle_date']) ?></span></p>
            <p style="margin-top:8pt;">เพื่อให้การดำเนินงานตามโครงการดังกล่าว เป็นไปด้วยความเรียบร้อย จึงขอเชิญท่านซึ่งเป็นคณะกรรมการร่างขอบเขตและกำหนดราคากลาง เข้าร่วมประชุมเพื่อจัดหาการ<span class="red">จัดซื้อยา จำนวน <?= h($data['item_count']) ?> รายการ ในวันที่ <?= h($data['meeting_date']) ?> เวลา <?= h($data['meeting_time']) ?></span> ณ <?= h($data['location']) ?></p>
            <p style="margin-top:6pt;">จึงเรียนมาเพื่อทราบ และเข้าร่วมประชุมตามวัน เวลา และสถานที่ดังกล่าว</p>

            <div class="signature">
                <div><?= h($data['signer_signature']) ?></div>
                <div>(<?= h($data['signer_name']) ?>)</div>
                <div><?= nl2br(h($data['signer_position'])) ?></div>
            </div>
        </div>

        <div class="word-page-break">&nbsp;</div>
        <div class="memo-page">
            <div class="agenda-title">ระเบียบวาระการประชุม</div>
            <div class="agenda-header">
                <div>คณะกรรมการร่างขอบเขตและกำหนดราคากลางเพื่อจัดหาการ<span class="red">จัดซื้อยา จำนวน <?= h($data['item_count']) ?> รายการ</span></div>
                <div>เพื่อใช้ในการปฏิบัติงานของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล</div>
                <div>ประจำปีงบประมาณ พ.ศ. <?= h($data['fiscal_year']) ?></div>
                <div>วันที่ <span class="red"><?= h(committeeScopeAgendaDate($data['meeting_date'])) ?></span> เวลา <span class="red"><?= h(committeeScopeAgendaTime($data['meeting_time'])) ?></span></div>
                <div>ณ&nbsp;&nbsp;<?= h($data['location']) ?></div>
            </div>
            <div class="agenda-rule"></div>

            <table class="agenda-table">
                <colgroup>
                    <col style="width:112pt;">
                    <col style="width:340pt;">
                </colgroup>
                <tr>
                    <td class="agenda-label-cell">ระเบียบวาระที่ ๑</td>
                    <td class="agenda-detail-cell"><div class="agenda-topic">เรื่อง&nbsp;&nbsp;ประธานแจ้งให้ที่ประชุมทราบ</div><div class="agenda-detail"><div class="agenda-detail-line">ตามบันทึกข้อความศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล</div><div class="agenda-detail-line">ที่ชม ๕๑๐๐๖.๑๓.๑/<?= h($data['principle_no_suffix']) ?> ลงวันที่ <span class="red"><?= h($data['principle_date']) ?></span> เรื่อง ขออนุมัติในหลักการเพื่อดำเนินการ<span class="red">จัดซื้อยา จำนวน <?= h($data['item_count']) ?> รายการ</span> เพื่อใช้สนับสนุนการให้บริการทางการแพทย์รักษาพยาบาลประชาชน ประจำปีงบประมาณ พ.ศ. <?= h($data['fiscal_year']) ?></div></div></td>
                </tr>
                <tr>
                    <td class="agenda-label-cell">ระเบียบวาระที่ ๒</td>
                    <td class="agenda-detail-cell"><div class="agenda-topic">เรื่อง&nbsp;&nbsp;ข้อกฎหมาย ระเบียบ และหนังสือสั่งการที่เกี่ยวข้อง</div><div class="agenda-detail">-</div></td>
                </tr>
                <tr>
                    <td class="agenda-label-cell">ระเบียบวาระที่ ๓</td>
                    <td class="agenda-detail-cell"><div class="agenda-topic">เรื่อง&nbsp;&nbsp;พิจารณากำหนดร่างขอบเขตและกำหนดราคากลางเพื่อจัดหา</div><div class="agenda-detail"><div class="agenda-continuation"><span class="red">การจัดซื้อยา จำนวน <?= h($data['item_count']) ?> รายการ</span> เพื่อใช้ในการปฏิบัติงานของ</div><div class="agenda-continuation">ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล</div><div class="agenda-continuation">ประจำปีงบประมาณ พ.ศ. <?= h($data['fiscal_year']) ?></div></div></td>
                </tr>
                <tr>
                    <td class="agenda-label-cell">ระเบียบวาระที่ ๔</td>
                    <td class="agenda-detail-cell"><div class="agenda-topic">เรื่อง&nbsp;&nbsp;อื่นๆ (ถ้ามี)</div></td>
                </tr>
            </table>
            <div class="agenda-tail-line"></div>
        </div>
    </body>
    </html>
    <?php
    return (string) ob_get_clean();
}

ensureProcurementTables();
$caseId = (int) ($_POST['case_id'] ?? $_GET['case_id'] ?? 0);
$activeCase = procurementCase($caseId);
$caseOptions = procurementCases();
$savedDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'committee_scope_invitation') : null;
$savedExtra = is_array($savedDocument['extra'] ?? null) ? $savedDocument['extra'] : [];

$department = 'กองสาธารณสุข ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล อ.สันกำแพง โทร. ๐๕๓๓๓ ๑๓๖๒';
$docDate = field('doc_date', (string) ($savedDocument['doc_date'] ?? ''));
$subject = 'แจ้งคำสั่งและเชิญประชุมแต่งตั้งคณะกรรมการร่างขอบเขตและกำหนดราคากลาง เพื่อจัดซื้อยา ที่องค์การเภสัชกรรมมิได้ผลิตจำหน่าย';
$itemCountValue = 0;
if ($activeCase && is_array($activeCase['items'] ?? null)) {
    foreach ($activeCase['items'] as $item) {
        if (!is_array($item)) continue;
        $name = trim((string) ($item['name'] ?? ''));
        $quantity = trim((string) ($item['quantity'] ?? ''));
        $unit = trim((string) ($item['unit'] ?? ''));
        if ($name === '' && $quantity === '' && $unit === '') continue;
        $itemCountValue++;
    }
}
$itemCount = thai_digits((string) $itemCountValue);
$fiscalYear = $activeCase ? thai_digits((string) ($activeCase['fiscal_year'] ?? '')) : field('fiscal_year', '');
$memoNoSuffix = field('memo_no_suffix', (string) ($savedDocument['memo_no_suffix'] ?? ''));
$principleNoSuffix = $activeCase ? thai_digits((string) ($activeCase['principle_memo_no_suffix'] ?? '')) : field('principle_no_suffix', '');
$principleDate = $activeCase ? thai_digits((string) ($activeCase['principle_doc_date'] ?? '')) : field('principle_date', '');
$meetingDate = field('meeting_date', (string) ($savedExtra['meeting_date'] ?? ''));
$meetingTime = field('meeting_time', (string) ($savedExtra['meeting_time'] ?? ''));
$location = 'ห้องประชุมศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล อำเภอสันกำแพง จังหวัดเชียงใหม่';
$defaultSignerName = 'นางสาวชุตติภัทร เงินอินต๊ะ';
$defaultSignerPosition = "เภสัชกรปฏิบัติการ\nประธานคณะกรรมการร่างขอบเขตของงานฯ";
$signerSignature = field('signer_signature', (string) ($savedExtra['signer_signature'] ?? ''));
$signerName = $defaultSignerName;
$signerPosition = $defaultSignerPosition;
if ($activeCase && !empty($activeCase['committee'][0]) && is_array($activeCase['committee'][0])) {
    $chair = $activeCase['committee'][0];
    $chairName = trim((string) ($chair['name'] ?? ''));
    $chairPosition = trim((string) ($chair['position'] ?? ''));
    if ($chairName !== '') {
        $signerName = thai_digits($chairName);
    }
    $signerPosition = thai_digits(trim($chairPosition . "\nประธานคณะกรรมการร่างขอบเขตของงานฯ"));
}

$saveError = '';
if (($_POST['action'] ?? '') === 'word') {
    $wordData = [
        'department' => $department,
        'memo_no_suffix' => $memoNoSuffix,
        'doc_date' => $docDate,
        'subject' => $subject,
        'item_count' => $itemCount,
        'fiscal_year' => $fiscalYear,
        'principle_no_suffix' => $principleNoSuffix,
        'principle_date' => $principleDate,
        'meeting_date' => $meetingDate,
        'meeting_time' => $meetingTime,
        'location' => $location,
        'signer_signature' => $signerSignature,
        'signer_name' => $signerName,
        'signer_position' => $signerPosition,
    ];
    $wordFilename = committeeScopeWordFilename($memoNoSuffix, $docDate, $itemCount);
    try {
        outputCommitteeScopeInvitationDocx($wordData, $wordFilename);
    } catch (Throwable $primaryError) {
        error_log('Committee invitation DOCX template export failed: ' . $primaryError->getMessage());
        try {
            // Shared hosting can reject an incomplete Composer upload or a
            // template operation. This writer only needs PHP ZipArchive.
            outputWordDocx(committeeScopeWordHtml($wordData), $wordFilename, '', 'invitation');
        } catch (Throwable $fallbackError) {
            error_log('Committee invitation DOCX fallback export failed: ' . $fallbackError->getMessage());
            $saveError = 'สร้างไฟล์ Word ไม่สำเร็จ กรุณาตรวจสอบว่าอัปโหลดโฟลเดอร์ vendor, templates และ assets/fonts ครบ และ PHP เปิดใช้งาน ZipArchive';
        }
    }
}

if (($_POST['action'] ?? '') === 'save_document') {
    if (!$activeCase) {
        $saveError = 'กรุณาเลือกชุดเอกสารจากหัวข้อ 1 ก่อนบันทึกเอกสาร';
    } elseif (trim($memoNoSuffix) === '' || trim($docDate) === '') {
        $saveError = 'กรุณากรอกเลขที่หลัง / และวันที่ ของหัวข้อ 2 ก่อนบันทึก';
    } else {
        saveProcurementDocument((int) $activeCase['id'], 'committee_scope_invitation', $memoNoSuffix, $docDate, [
            'meeting_date' => $meetingDate,
            'meeting_time' => $meetingTime,
            'signer_signature' => $signerSignature,
            'signer_name' => $signerName,
            'signer_position' => $signerPosition,
        ]);
        header('Location: committee_scope_invitation.php?case_id=' . (int) $activeCase['id'] . '&saved=1');
        exit;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>บันทึกข้อความ แจ้งคำสั่งและเชิญประชุม</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page { size: 210mm 297mm; margin: 25mm 20mm 20mm 30mm; }
        .memo-page {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: #fff;
            color: #111;
            padding: 25mm 20mm 20mm 30mm;
            box-sizing: border-box;
            font-family: "TH Sarabun New", "Sarabun", sans-serif;
            font-size: 16pt;
            line-height: 1.2;
        }
        .garuda { width: auto; height: 1.5cm; display: block; }
        .memo-title { font-size: 29pt; font-weight: 700; letter-spacing: 0; }
        .memo-line { font-size: 16pt; min-height: 1.05em; }
        .department-text { white-space: nowrap; font-size: 14pt; letter-spacing: -0.25pt; min-width: 0; }
        .memo-label { font-size: 20pt; font-weight: 700; line-height: 1; white-space: nowrap; }
        .memo-text { font-size: 16pt; line-height: 1.2; }
        .document-stack { display: flex; flex-direction: column; gap: 12mm; }
        .thai-justify,
        .indent {
            text-align: justify;
            text-align-last: left;
            text-justify: inter-character;
            -ms-text-justify: distribute;
        }
        .indent { text-indent: 2.5cm; }
        .red { color: #e11d1d; }
        .signature { margin-top: 2.1cm; text-align: center; color: #e11d1d; line-height: 1.25; }
        .signature-line { min-height: 1.3em; }
        .agenda-page { line-height: 1.25; }
        .agenda-title { text-align: center; font-weight: 700; }
        .agenda-header { text-align: center; font-weight: 700; }
        .agenda-rule { width: 7cm; margin: 0.25cm auto 0.35cm; border-top: 1px dashed #111; }
        .agenda-row { display: grid; grid-template-columns: 4.7cm 1fr; gap: 0.35cm; margin-top: 0.35cm; }
        .agenda-label { font-weight: 700; white-space: nowrap; }
        .agenda-topic { font-weight: 700; }
        .agenda-detail { margin-top: 0.1cm; }
        .agenda-detail-line { display: block; }
        .agenda-continuation { display: block; }
        .agenda-tail-line { border-top: 1px solid #111; margin-top: 0.75cm; }
        @media print {
            html,
            body {
                width: auto;
                min-height: auto;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .no-print { display: none !important; }
            main,
            section {
                display: block !important;
                width: auto !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }
            .document-stack {
                display: block !important;
            }
            .memo-page {
                width: auto;
                height: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
                break-after: page;
                page-break-after: always;
            }
            .memo-page:last-child {
                break-after: auto;
                page-break-after: auto;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100">
<?php require __DIR__ . '/components/navbar.php'; ?>
<?php require __DIR__ . '/components/workflow_steps.php'; ?>

<main class="mx-auto grid max-w-7xl gap-5 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_420px]">
    <aside class="no-print order-2 rounded-lg border border-zinc-800 bg-zinc-900/80 p-4 lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto lg:overscroll-contain">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">ข้อมูลเอกสาร</h1>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="rounded-md bg-emerald-500 px-3 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">พิมพ์</button>
                <button type="submit" form="committee_scope_form" name="action" value="word" class="rounded-md border border-emerald-500/60 px-3 py-2 text-sm font-medium text-emerald-200 hover:bg-emerald-500/10">ดาวน์โหลด Word</button>
            </div>
        </div>
        <?php if ($saveError !== ''): ?>
            <div class="mb-3 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-100"><?= h($saveError) ?></div>
        <?php endif; ?>
        <form method="get" action="committee_scope_invitation.php" class="mb-3 rounded-md border border-zinc-800 p-3">
            <label class="block">
                <span class="text-xs text-zinc-400">เลือกชุดเอกสาร</span>
                <select name="case_id" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" onchange="this.form.submit()">
                    <option value="">เลือกชุดที่ต้องการ</option>
                    <?php foreach ($caseOptions as $caseOption): ?>
                        <?php
                        $optionMemo = trim((string) ($caseOption['principle_memo_no_suffix'] ?? ''));
                        $optionDate = trim((string) ($caseOption['principle_doc_date'] ?? ''));
                        $optionLabel = 'ชม ๕๑๐๐๖.๑๓.๑/' . $optionMemo . ' ลงวันที่ ' . $optionDate;
                        ?>
                        <option value="<?= (int) $caseOption['id'] ?>" <?= (int) $caseOption['id'] === (int) $caseId ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <a href="document_history.php" class="mt-2 inline-flex rounded-md border border-zinc-700 px-3 py-2 text-xs text-zinc-200 hover:border-emerald-400">ดูประวัติเอกสารทั้งหมด</a>
        </form>
        <?php if (($_GET['saved'] ?? '') === '1' && $activeCase): ?>
            <div class="mb-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-100">
                บันทึกเลขที่และวันที่ของหัวข้อ 2 แล้ว
            </div>
            <div class="mb-3 grid gap-2 text-sm">
                <a class="rounded-md border border-emerald-500/60 px-3 py-2 text-emerald-200 hover:bg-emerald-500/10" href="tor_company_purchase.php?case_id=<?= (int) $activeCase['id'] ?>">ไปหัวข้อ 3 เอกสาร TOR</a>
                <a class="rounded-md border border-emerald-500/60 px-3 py-2 text-emerald-200 hover:bg-emerald-500/10" href="meeting_report.php?case_id=<?= (int) $activeCase['id'] ?>">ไปหัวข้อ 4 รายงานการประชุม</a>
            </div>
        <?php endif; ?>
        <form id="committee_scope_form" method="post" action="committee_scope_invitation.php" class="grid gap-3">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
            <?php
            $controls = [
                'memo_no_suffix' => 'เลขที่บันทึก หลัง /',
                'doc_date' => 'วันที่บันทึกข้อความ',
                'principle_no_suffix' => 'เลขที่บันทึกข้อความ ขออนุมัติในหลักการ',
                'principle_date' => 'วันที่บันทึกขออนุมัติในหลักการ',
                'meeting_date' => 'ลงวันที่เพื่อแจ้งกรรมการมาประชุม',
                'meeting_time' => 'เวลาประชุม',
                'signer_signature' => 'ลายเซ็นต์',
                'signer_name' => 'ชื่อประธานกรรมการร่างขอบเขต (อัตโนมัติ)',
                'signer_position' => 'ตำแหน่งประธานกรรมการร่างขอบเขต (อัตโนมัติ)',
            ];
            $controlValues = [
                'memo_no_suffix' => $memoNoSuffix,
                'doc_date' => $docDate,
                'principle_no_suffix' => $principleNoSuffix,
                'principle_date' => $principleDate,
                'meeting_date' => $meetingDate,
                'meeting_time' => $meetingTime,
                'signer_signature' => $signerSignature,
                'signer_name' => $signerName,
                'signer_position' => $signerPosition,
            ];
            $dateFields = ['doc_date', 'principle_date', 'meeting_date'];
            $fieldGroups = [
                '1. ข้อมูลหนังสือเชิญประชุม' => ['memo_no_suffix', 'doc_date'],
                '2. อ้างอิงหนังสือขออนุมัติ' => ['principle_no_suffix', 'principle_date'],
                '3. วันและเวลานัดประชุม' => ['meeting_date', 'meeting_time'],
                '4. ข้อมูลผู้ลงนาม' => ['signer_signature', 'signer_name', 'signer_position'],
            ];
            foreach ($fieldGroups as $groupTitle => $groupFields):
            ?>
            <section class="rounded-lg border border-zinc-800 p-3" aria-label="<?= h($groupTitle) ?>">
                <h2 class="mb-3 text-sm font-semibold text-emerald-300"><?= h($groupTitle) ?></h2>
                <?php foreach ($groupFields as $name):
                    $label = $controls[$name];
                    $datePickerAttr = in_array($name, $dateFields, true) ? ' data-thai-date-picker="1"' : '';
                ?>
                <label class="block">
                    <span class="text-sm text-zinc-300"><?= h($label) ?></span>
                    <?php if ($name === 'signer_position'): ?>
                        <textarea name="<?= h($name) ?>" data-field="<?= h($name) ?>" rows="2" readonly class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900/70 px-3 py-2 text-sm text-zinc-500"><?= h($controlValues[$name]) ?></textarea>
                    <?php else: ?>
                        <input name="<?= h($name) ?>" data-field="<?= h($name) ?>"<?= $datePickerAttr ?> value="<?= h($controlValues[$name]) ?>" <?= $name === 'signer_name' ? 'readonly' : '' ?> class="mt-1 w-full rounded-md border border-zinc-700 <?= $name === 'signer_name' ? 'bg-zinc-900/70 text-zinc-500' : 'bg-zinc-950' ?> px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </section>
            <?php endforeach; ?>
            <section class="rounded-lg border border-zinc-800 p-3" aria-label="ข้อมูลอ้างอิงจากชุดเอกสาร">
                <h2 class="mb-3 text-sm font-semibold text-emerald-300">ข้อมูลจากชุดเอกสาร (อ่านอย่างเดียว)</h2>
                <label class="mb-3 block">
                    <span class="text-sm text-zinc-300">ปีงบประมาณ พ.ศ. (กำหนดในหัวข้อ 1)</span>
                    <input value="<?= h($fiscalYear) ?>" readonly class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900/70 px-3 py-2 text-sm text-zinc-500">
                </label>
            <label class="block">
                <span class="text-sm text-zinc-300">จำนวนยา (รายการ) (ดึงอัตโนมัติ)</span>
                <input value="<?= h($itemCount) ?>" readonly class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900/70 px-3 py-2 text-sm text-zinc-100">
            </label>
            </section>
            <button type="submit" name="action" value="save_document" class="app-primary-save rounded-md border border-emerald-500/60 px-4 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10">บันทึกหัวข้อ 2 และอัปเดตเอกสาร</button>
        </form>
    </aside>

    <section class="document-stack order-1 overflow-auto">
        <article class="memo-page">
            <div class="grid grid-cols-[3cm_1fr_3cm] items-start">
                <img src="picture/picture-1.png" alt="" class="garuda">
                <div class="memo-title pt-2 text-center">บันทึกข้อความ</div>
            </div>

            <div class="mt-1 grid grid-cols-[auto_1fr] items-end gap-x-2">
                <div class="memo-label">ส่วนราชการ</div>
                <div class="memo-line department-text"><?= h($department) ?></div>
            </div>
            <div class="mt-1 grid grid-cols-[auto_1fr_auto_1fr] items-end gap-x-2">
                <div class="memo-label">ที่</div>
                <div class="memo-line">ชม ๕๑๐๐๖.๑๓.๑/<span data-bind="memo_no_suffix"><?= h($memoNoSuffix) ?></span></div>
                <div class="memo-label">วันที่</div>
                <div class="memo-line text-center"><span data-bind="doc_date"><?= h($docDate) ?></span></div>
            </div>
            <div class="mt-1 grid grid-cols-[auto_1fr] items-start gap-x-2">
                <div class="memo-label">เรื่อง</div>
                <div class="memo-text thai-justify"><?= h($subject) ?> <span class="red">จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ</span> ประจำปีงบประมาณ พ.ศ. <?= h($fiscalYear) ?></div>
            </div>
            <div class="mt-4 grid grid-cols-[auto_1fr] gap-x-2">
                <div class="memo-label">เรียน</div>
                <div>คณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง</div>
            </div>

            <p class="indent mt-4">
                ตามที่ท่านได้รับแต่งตั้งให้เป็นคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะขอบเขตและกำหนดราคากลาง สำหรับการ<span class="red">จัดซื้อยา จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ</span>
                เพื่อสนับสนุนการให้บริการทางการแพทย์ รักษาพยาบาลประชาชนของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล ประจำปีงบประมาณ พ.ศ. <?= h($fiscalYear) ?>
                ตามบันทึกข้อความขออนุมัติในหลักการเพื่อดำเนินการจัดซื้อยา ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล ที่ ชม ๕๑๐๐๖.๑๓.๑/<span data-bind="principle_no_suffix"><?= h($principleNoSuffix) ?></span> ลงวันที่ <span class="red" data-bind="principle_date"><?= h($principleDate) ?></span>
            </p>

            <p class="indent mt-4">
                เพื่อให้การดำเนินงานตามโครงการดังกล่าว เป็นไปด้วยความเรียบร้อย จึงขอเชิญท่านซึ่งเป็นคณะกรรมการร่างขอบเขตและกำหนดราคากลาง เข้าร่วมประชุม
                เพื่อจัดหาการ<span class="red">จัดซื้อยา จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ ในวันที่ <span data-bind="meeting_date"><?= h($meetingDate) ?></span> เวลา <span data-bind="meeting_time"><?= h($meetingTime) ?></span></span>
                ณ <?= h($location) ?>
            </p>

            <p class="indent mt-3">จึงเรียนมาเพื่อทราบ และเข้าร่วมประชุมตามวัน เวลา และสถานที่ดังกล่าว</p>

            <div class="signature">
                <div class="signature-line" data-bind="signer_signature"><?= h($signerSignature) ?></div>
                <div>(<span data-bind="signer_name"><?= h($signerName) ?></span>)</div>
                <div data-bind="signer_position"><?= nl2br(h($signerPosition)) ?></div>
            </div>
        </article>

        <article class="memo-page agenda-page">
            <div class="agenda-title">ระเบียบวาระการประชุม</div>
            <div class="agenda-header">
                <div>คณะกรรมการร่างขอบเขตและกำหนดราคากลางเพื่อจัดหาการ<span class="red">จัดซื้อยา จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ</span></div>
                <div>เพื่อใช้ในการปฏิบัติงานของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล</div>
                <div>ประจำปีงบประมาณ พ.ศ. <?= h($fiscalYear) ?></div>
                <div>วันที่ <span class="red" data-bind="meeting_date" data-agenda-date="1"><?= h(committeeScopeAgendaDate($meetingDate)) ?></span> เวลา <span class="red" data-bind="meeting_time" data-agenda-time="1"><?= h(committeeScopeAgendaTime($meetingTime)) ?></span></div>
                <div>ณ&nbsp;&nbsp;<?= h($location) ?></div>
            </div>
            <div class="agenda-rule"></div>

            <div class="agenda-row">
                <div class="agenda-label">ระเบียบวาระที่ ๑</div>
                <div>
                    <div class="agenda-topic">เรื่อง&nbsp;&nbsp;ประธานแจ้งให้ที่ประชุมทราบ</div>
                    <div class="agenda-detail thai-justify">
                        <div class="agenda-detail-line">ตามบันทึกข้อความศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล</div>
                        <div class="agenda-detail-line">ที่ชม ๕๑๐๐๖.๑๓.๑/<span data-bind="principle_no_suffix"><?= h($principleNoSuffix) ?></span>
                        ลงวันที่ <span class="red" data-bind="principle_date"><?= h($principleDate) ?></span>
                        เรื่อง ขออนุมัติในหลักการเพื่อดำเนินการ<span class="red">จัดซื้อยา จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ</span>
                        เพื่อใช้สนับสนุนการให้บริการทางการแพทย์รักษาพยาบาลประชาชน ประจำปีงบประมาณ พ.ศ. <?= h($fiscalYear) ?></div>
                    </div>
                </div>
            </div>

            <div class="agenda-row">
                <div class="agenda-label">ระเบียบวาระที่ ๒</div>
                <div>
                    <div class="agenda-topic">เรื่อง&nbsp;&nbsp;ข้อกฎหมาย ระเบียบ และหนังสือสั่งการที่เกี่ยวข้อง</div>
                    <div class="agenda-detail">-</div>
                </div>
            </div>

            <div class="agenda-row">
                <div class="agenda-label">ระเบียบวาระที่ ๓</div>
                <div>
                    <div class="agenda-topic">เรื่อง&nbsp;&nbsp;พิจารณากำหนดร่างขอบเขตและกำหนดราคากลางเพื่อจัดหา</div>
                    <div class="agenda-detail thai-justify">
                        <div class="agenda-continuation"><span class="red">การจัดซื้อยา จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ</span> เพื่อใช้ในการปฏิบัติงานของ</div>
                        <div class="agenda-continuation">ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล</div>
                        <div class="agenda-continuation">ประจำปีงบประมาณ พ.ศ. <?= h($fiscalYear) ?></div>
                    </div>
                </div>
            </div>

            <div class="agenda-row">
                <div class="agenda-label">ระเบียบวาระที่ ๔</div>
                <div>
                    <div class="agenda-topic">เรื่อง&nbsp;&nbsp;อื่นๆ (ถ้ามี)</div>
                </div>
            </div>

            <div class="agenda-tail-line"></div>
        </article>
    </section>
</main>
<script src="assets/js/word_export.js"></script>
<script>
    const thaiDigitMap = {
        0: '๐',
        1: '๑',
        2: '๒',
        3: '๓',
        4: '๔',
        5: '๕',
        6: '๖',
        7: '๗',
        8: '๘',
        9: '๙',
    };
    const thaiDigits = (value) => String(value).replace(/[0-9]/g, (digit) => thaiDigitMap[digit] ?? digit);
    const arabicDigits = (value) => String(value).replace(/[๐-๙]/g, (digit) => '๐๑๒๓๔๕๖๗๘๙'.indexOf(digit));
    const thaiMonthNames = [
        'มกราคม',
        'กุมภาพันธ์',
        'มีนาคม',
        'เมษายน',
        'พฤษภาคม',
        'มิถุนายน',
        'กรกฎาคม',
        'สิงหาคม',
        'กันยายน',
        'ตุลาคม',
        'พฤศจิกายน',
        'ธันวาคม',
    ];

    function formatThaiDate(dateValue) {
        if (!dateValue) return '';
        const [year, month, day] = dateValue.split('-').map(Number);
        if (!year || !month || !day) return '';
        return `${day} ${thaiMonthNames[month - 1]} ${year + 543}`;
    }

    function parseThaiDate(value) {
        const normalized = arabicDigits(value).trim();
        for (let i = 0; i < thaiMonthNames.length; i++) {
            const pattern = new RegExp(`^(\\d{1,2})\\s+${thaiMonthNames[i]}\\s+(\\d{4})$`);
            const match = normalized.match(pattern);
            if (!match) continue;
            const year = Number(match[2]) - 543;
            const month = String(i + 1).padStart(2, '0');
            const day = String(Number(match[1])).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        return '';
    }

    function setupThaiDatePicker(input) {
        if (input.dataset.datePickerBound === '1') return;
        input.dataset.datePickerBound = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'mt-1 grid grid-cols-[minmax(0,1fr)_auto] gap-2';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.classList.remove('mt-1');

        const picker = document.createElement('input');
        picker.type = 'date';
        picker.value = parseThaiDate(input.value);
        picker.className = 'absolute h-px w-px opacity-0 pointer-events-none';
        picker.tabIndex = -1;
        wrapper.appendChild(picker);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:border-emerald-400';
        button.textContent = 'เลือกวันที่';
        wrapper.appendChild(button);

        const openPicker = () => {
            picker.value = parseThaiDate(input.value) || picker.value;
            try {
                if (typeof picker.showPicker === 'function') {
                    picker.showPicker();
                    return;
                }
                picker.focus();
                picker.click();
            } catch (error) {
                picker.focus();
            }
        };

        picker.addEventListener('change', () => {
            input.value = formatThaiDate(picker.value);
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        button.addEventListener('click', openPicker);
        input.addEventListener('focus', openPicker);
        input.addEventListener('click', openPicker);
    }

    document.querySelectorAll('[data-field]').forEach((input) => {
        const update = () => {
            const value = thaiDigits(input.value);
            input.value = value;
            document.querySelectorAll(`[data-bind="${input.dataset.field}"]`).forEach((target) => {
                if (input.tagName === 'TEXTAREA') {
                    target.innerHTML = escapeHtml(value).replace(/\n/g, '<br>');
                } else if (target.dataset.agendaTime === '1') {
                    target.textContent = value && !/น\.\s*$/.test(value) ? `${value} น.` : value;
                } else if (target.dataset.agendaDate === '1') {
                    const normalized = arabicDigits(value).trim();
                    const match = normalized.match(/^(\d{1,2})\s+(?:เดือน\s+)?([^\s]+)\s+(?:พ\.ศ\.\s*)?(\d{4})$/);
                    target.textContent = match ? thaiDigits(`${match[1]} เดือน ${match[2]} พ.ศ. ${match[3]}`) : value;
                } else {
                    target.textContent = value;
                }
            });
        };
        input.addEventListener('input', update);
        update();
    });
    document.querySelectorAll('[data-thai-date-picker]').forEach(setupThaiDatePicker);

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
</script>
</body>
</html>
