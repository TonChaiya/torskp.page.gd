<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require __DIR__ . '/core/word_export_lib.php';
require __DIR__ . '/core/auth.php';
requireLogin();

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function thaiDigits($value): string
{
    return strtr((string) $value, ['0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔','5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙']);
}
function arabicDigits($value): string
{
    return strtr((string) $value, ['๐'=>'0','๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4','๕'=>'5','๖'=>'6','๗'=>'7','๘'=>'8','๙'=>'9']);
}
function rawField(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $_GET[$key] ?? $default)); }
function field(string $key, string $default = ''): string { return thaiDigits(trim((string) ($_POST[$key] ?? $_GET[$key] ?? $default))); }
function money(float $value): string { return thaiDigits(number_format($value, 2)); }
function quantityText($value): string
{
    $number = (float) $value;
    $text = floor($number) == $number ? number_format($number, 0) : number_format($number, 2);
    return thaiDigits($text);
}
function meetingItemHasPackage(array $item): bool
{
    return (float) ($item['package_size'] ?? 0) > 0;
}
function meetingItemQuantity(array $item)
{
    if (meetingItemHasPackage($item) && (float) ($item['package_count'] ?? 0) > 0) {
        return $item['package_count'];
    }
    return $item['quantity'] ?? 0;
}
function meetingItemUnitText(array $item): string
{
    $unit = trim((string) ($item['unit'] ?? ''));
    if (!meetingItemHasPackage($item)) return $unit;
    return quantityText($item['package_size']) . ($unit !== '' ? ' ' . $unit : '') . '/กล่อง';
}
function meetingItemQuantityLine(array $item): string
{
    $quantity = quantityText(meetingItemQuantity($item));
    if (meetingItemHasPackage($item)) {
        return 'จำนวน ' . $quantity . ' กล่อง ขนาดบรรจุ ' . meetingItemUnitText($item);
    }
    return trim('จำนวน ' . $quantity . ' ' . meetingItemUnitText($item));
}
function meetingItemOfferTotal(array $item): float
{
    return (float) ($item['offer_price'] ?? 0) * (float) meetingItemQuantity($item);
}
function displayText(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = preg_split("/\n/u", $text) ?: [];
    $lines = array_map(static fn($line) => trim($line), $lines);
    $lines = array_values(array_filter($lines, static fn($line) => $line !== ''));
    return implode("\n", $lines);
}
function compactBreaks(string $html): string
{
    $html = preg_replace('/(?:<br\s*\/?>\s*){2,}/i', '<br>', $html) ?? $html;
    return $html;
}

function meetingWordLogicalParagraphs(string $text): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = preg_split('/\n/u', $text) ?: [];
    $paragraphs = [];
    $current = '';

    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/[\t \x{00A0}]+/u', ' ', trim($line)));
        if ($line === '') continue;

        $startsNewItem = preg_match('/^(?:[๐-๙0-9]+(?:\.[๐-๙0-9]+)*[.)]|[-–—•])\s+/u', $line) === 1;
        if ($startsNewItem && $current !== '') {
            $paragraphs[] = $current;
            $current = $line;
            continue;
        }
        $current = $current === '' ? $line : $current . ' ' . $line;
    }
    if ($current !== '') $paragraphs[] = $current;
    return $paragraphs;
}

function renderMeetingWordParagraphs(string $text): string
{
    return implode('', array_map(
        static fn(string $paragraph): string => '<p class="meeting-content-paragraph thai-distributed-speech">'
            . wordAddThaiLineBreakOpportunities(h($paragraph)) . '</p>',
        meetingWordLogicalParagraphs($text)
    ));
}

function renderMeetingWordSpeaker(string $speaker): string
{
    $lines = preg_split('/\R/u', displayText($speaker)) ?: [];
    return implode('', array_map(
        static function (string $line): string {
            return '<div class="speaker-line">' . h(trim($line)) . '</div>';
        },
        array_values(array_filter($lines, static fn(string $line): bool => trim($line) !== ''))
    ));
}

function savedValue(array $data, string $key, string $default = ''): string
{
    return (string) ($data[$key] ?? $default);
}

function meetingReportWordFilename(string $meetingDate, string $itemCount): string
{
    return trim('รายงานการประชุม วันที่ ' . meetingFilenameDate($meetingDate) . ' ยา ' . trim(arabicDigits($itemCount)) . ' รายการ.docx');
}

function meetingFilenameDate(string $date): string
{
    $date = trim(arabicDigits($date));
    $months = [
        'มกราคม' => '01', 'กุมภาพันธ์' => '02', 'มีนาคม' => '03', 'เมษายน' => '04',
        'พฤษภาคม' => '05', 'มิถุนายน' => '06', 'กรกฎาคม' => '07', 'สิงหาคม' => '08',
        'กันยายน' => '09', 'ตุลาคม' => '10', 'พฤศจิกายน' => '11', 'ธันวาคม' => '12',
    ];
    foreach ($months as $month => $no) {
        if (preg_match('/(\d{1,2}).*?' . preg_quote($month, '/') . '.*?(\d{4})/u', $date, $match)) {
            return str_pad($match[1], 2, '0', STR_PAD_LEFT) . '-' . $no . '-' . $match[2];
        }
    }
    return str_replace(['/', '\\'], '-', $date);
}

function itemSnapshot(array $items): array
{
    $snapshot = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $snapshot[] = [
            'name' => (string) ($item['name'] ?? ''),
            'quantity' => $item['quantity'] ?? '',
            'requested_quantity' => $item['requested_quantity'] ?? ($item['quantity'] ?? ''),
            'package_size' => $item['package_size'] ?? 0,
            'package_count' => $item['package_count'] ?? 0,
            'unit' => (string) ($item['unit'] ?? ''),
            'reference_price' => (float) ($item['reference_price'] ?? 0),
            'offer_price' => (float) ($item['offer_price'] ?? 0),
            'offer_total' => meetingItemOfferTotal($item),
        ];
    }
    return $snapshot;
}

function renderMeetingWordContent(string $content, array $items): string
{
    $contentText = str_replace(["\r\n", "\r"], "\n", trim($content));
    if (!str_contains($contentText, '[medicine_items]')) {
        return renderMeetingWordParagraphs($contentText);
    }

    [$beforeItems, $afterItems] = array_pad(explode('[medicine_items]', $contentText, 2), 2, '');
    ob_start();
    ?>
    <div class="content"><?= renderMeetingWordParagraphs($beforeItems) ?></div>
    <table class="medicine-lines">
        <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= thaiDigits($i + 1) ?>. <?= h((string) ($item['name'] ?? '')) ?></td>
                <td class="nowrap"><?= h(meetingItemQuantityLine($item)) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <div class="content"><?= renderMeetingWordParagraphs($afterItems) ?></div>
    <?php
    return (string) ob_get_clean();
}

function meetingReportWordHtml(array $data): string
{
    $hasPackageItems = count(array_filter($data['items'], 'meetingItemHasPackage')) > 0;
    ob_start();
    ?>
    <!doctype html>
    <html lang="th">
    <head>
        <meta charset="utf-8">
        <title>รายงานการประชุม</title>
        <style>
            @page { size: A4; margin: 25mm 20mm 20mm 20mm; }
            body { margin: 0; color: #111; font-family: "TH Sarabun New", "Sarabun", sans-serif; font-size: 16pt; line-height: 1; }
            .report-page { width: 170mm; page-break-after: auto; }
            .word-page-break { page-break-before: always; font-size: 1pt; line-height: 1pt; }
            table { width: 100%; border-collapse: collapse; border-spacing: 0; mso-cellspacing: 0in; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
            td, th { font-size: 16pt; line-height: 1; vertical-align: top; }
            .doc-title { text-align: center; font-weight: 700; line-height: 1; }
            .section-label { margin-top: 10pt; font-weight: 700; }
            .attendee-table td { border: 0; padding: 0 3pt 0 0; }
            .speech-table { table-layout: fixed; margin-top: 0; page-break-inside: auto; }
            .speech-table td { border: 0; padding: 0; }
            .speech-table tr { page-break-inside: avoid; }
            .speech-left { width: 135pt; mso-width-source: userset; padding-right: 9pt !important; font-weight: 700; }
            .speaker-line { display: block; margin: 0; padding: 0; line-height: 1; white-space: nowrap; }
            .speech-body { width: 347pt; mso-width-source: userset; text-align: left; text-justify: auto; text-align-last: left; }
            .meeting-content-paragraph { margin: 0; padding: 0; line-height: 1; text-align: justify; text-justify: inter-cluster; -ms-text-justify: distribute; text-align-last: left; }
            .agenda-heading td { padding-top: 7pt; font-weight: 700; }
            .first-agenda td { padding-top: 3pt; }
            .medicine-lines { width: 100%; table-layout: fixed; margin: 2pt 0; }
            .medicine-lines td { border: 0; padding: 0 5pt 0 0; white-space: normal; overflow-wrap: anywhere; word-break: break-word; }
            .medicine-lines td:first-child { width: 52%; }
            .medicine-lines td:last-child { width: 48%; }
            .nowrap { white-space: normal; }
            .medicine-table { margin-top: 10pt; border-collapse: collapse; page-break-inside: avoid; }
            .attendance-table {
                margin-top: 10pt;
                table-layout: fixed;
                border-collapse: collapse;
                border-spacing: 0;
                mso-cellspacing: 0in;
                border: 0;
                mso-border-alt: none;
                font-family: "TH Sarabun New", "Sarabun", sans-serif;
                font-size: 16pt;
                line-height: 1;
            }
            .medicine-table th, .medicine-table td,
            .attendance-table th, .attendance-table td { padding: 3pt 5pt; }
            .medicine-table th, .medicine-table td { border: 0.5pt solid #000; mso-border-alt: solid #000 0.5pt; }
            .attendance-table th, .attendance-table td {
                border: 0.5pt solid #000;
                mso-border-alt: solid #000 0.5pt;
                font-family: "TH Sarabun New", "Sarabun", sans-serif !important;
                font-size: 16pt !important;
                font-weight: 400;
                line-height: 1 !important;
                page-break-inside: avoid;
            }
            .medicine-table th, .attendance-table th { text-align: center; font-weight: 700; }
            .medicine-table thead { display: table-header-group; }
            .medicine-table thead tr { page-break-after: avoid; }
            .medicine-table thead th { page-break-after: avoid; }
            .center { text-align: center; }
            .right { text-align: right; }
            .signature-table { margin-top: 28pt; text-align: center; }
            .signature-table td { border: 0; text-align: center; padding-top: 8pt; }
        </style>
    </head>
    <body>
        <div class="report-page">
            <div class="doc-title">
                <div><?= h($data['meeting_title']) ?></div>
                <div><?= h($data['purpose_line']) ?></div>
                <div><?= h($data['fiscal_line']) ?></div>
                <div>วันที่ <?= h($data['meeting_date']) ?> เวลา <?= h($data['meeting_time']) ?></div>
                <div>ณ&nbsp;&nbsp;<?= h($data['meeting_place']) ?></div>
            </div>

            <div class="section-label">ผู้เข้าประชุม</div>
            <table class="attendee-table">
                <?php foreach ($data['committee'] as $i => $person): ?>
                    <tr>
                        <td style="width:28pt;"><?= thaiDigits($i + 1) ?>.</td>
                        <td style="width:180pt;"><?= h($person['name']) ?></td>
                        <td style="width:145pt;"><?= h($person['position']) ?></td>
                        <td><?= h($person['role']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <div class="section-label">ผู้ไม่เข้าประชุม&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-weight:400;"><?= h($data['absent_text']) ?></span></div>
            <div class="section-label">เปิดประชุม&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-weight:400;">เวลา <?= h($data['open_time']) ?></span></div>

            <?php $shownAgendaIndex = 0; ?>
            <?php foreach ($data['agenda_order'] as $agendaName): ?>
                <?php if (empty($data['speeches_by_agenda'][$agendaName])) continue; ?>
                <table class="speech-table speech-group <?= $shownAgendaIndex === 0 ? 'first-agenda' : '' ?>" width="643" cellspacing="0" cellpadding="0" style="width:482pt;table-layout:fixed;mso-table-layout-alt:fixed;border-collapse:collapse;border-spacing:0;mso-cellspacing:0in;">
                    <colgroup><col width="180" style="width:135pt;mso-width-source:userset;"><col width="463" style="width:347pt;mso-width-source:userset;"></colgroup>
                    <tr class="agenda-heading">
                        <td class="speech-left" width="180" style="width:135pt;mso-width-source:userset;padding-right:9pt;"><?= h($agendaName) ?></td>
                        <td class="speech-body" width="463" style="width:347pt;mso-width-source:userset;font-weight:700;"><?= h($data['agenda_titles'][$agendaName] ?? '') ?></td>
                    </tr>
                    <?php foreach ($data['speeches_by_agenda'][$agendaName] as $speech): ?>
                        <tr>
                            <td class="speech-left" width="180" style="width:135pt;mso-width-source:userset;padding-right:9pt;"><?= renderMeetingWordSpeaker((string) ($speech['speaker'] ?? '')) ?></td>
                            <td class="speech-body" width="463" style="width:347pt;mso-width-source:userset;"><?= renderMeetingWordContent((string) ($speech['content'] ?? ''), $data['items']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php $shownAgendaIndex++; ?>
                <?php if ($agendaName === 'ระเบียบวาระที่ ๓' && $data['items']): ?>
                    <div class="word-page-break">&nbsp;</div>
                    <table class="medicine-table" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border-spacing:0;mso-cellspacing:0in;">
                        <thead>
                        <tr>
                            <th style="width:8%;"><?= $hasPackageItems ? 'ที่' : 'ลำดับ' ?></th>
                            <th>รายการยา</th>
                            <th style="width:14%;">ราคากลาง</th>
                            <th style="width:14%;">ราคาเสนอ</th>
                            <th style="width:<?= $hasPackageItems ? '11%' : '14%' ?>;">จำนวน</th>
                            <th style="width:<?= $hasPackageItems ? '20%' : '12%' ?>;"><?= $hasPackageItems ? 'ขนาดบรรจุ/หน่วยนับ' : 'หน่วยนับ' ?></th>
                            <th style="width:16%;">มูลค่ารวม</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($data['items'] as $i => $item): ?>
                            <tr>
                                <td class="center"><?= thaiDigits($i + 1) ?></td>
                                <td><?= h((string) ($item['name'] ?? '')) ?></td>
                                <td class="right"><?= money((float) ($item['reference_price'] ?? 0)) ?></td>
                                <td class="right"><?= money((float) ($item['offer_price'] ?? 0)) ?></td>
                                <td class="right"><?= quantityText(meetingItemQuantity($item)) ?></td>
                                <td class="center"><?= h(meetingItemUnitText($item)) ?></td>
                                <td class="right"><?= money(meetingItemOfferTotal($item)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="6" class="center">รวม</td>
                            <td class="right"><?= money((float) $data['offer_total']) ?></td>
                        </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="section-label">ปิดประชุม&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-weight:400;"><?= h($data['close_time']) ?></span></div>
            <table class="signature-table" cellspacing="0" cellpadding="0">
                <tr><td colspan="2">(ลงชื่อ)........................................ประธานกรรมการ<br><?= h($data['committee'][0]['name']) ?><br><?= h($data['committee'][0]['position']) ?></td></tr>
                <tr>
                    <td>(ลงชื่อ)........................................กรรมการ<br><?= h($data['committee'][1]['name']) ?><br><?= h($data['committee'][1]['position']) ?></td>
                    <td>(ลงชื่อ)........................................กรรมการ<br><?= h($data['committee'][2]['name']) ?><br><?= h($data['committee'][2]['position']) ?></td>
                </tr>
            </table>
        </div>

        <div class="word-page-break">&nbsp;</div>
        <div class="report-page">
            <div class="doc-title">
                <div><?= h($data['meeting_title']) ?></div>
                <div><?= h($data['purpose_line']) ?></div>
                <div><?= h($data['fiscal_line']) ?></div>
                <div>วันที่ <?= h($data['meeting_date']) ?> เวลา <?= h($data['meeting_time']) ?></div>
                <div>ณ&nbsp;&nbsp;<?= h($data['meeting_place']) ?></div>
            </div>
            <table class="attendance-table" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border-spacing:0;mso-cellspacing:0in;">
                <tr>
                    <th style="width:8%;">ลำดับ</th>
                    <th>รายชื่อคณะกรรมการ<br>ผู้เข้าร่วมประชุม</th>
                    <th style="width:20%;">ตำแหน่ง</th>
                    <th style="width:20%;">ลายมือชื่อ</th>
                    <th style="width:20%;">หมายเหตุ</th>
                </tr>
                <?php foreach ($data['committee'] as $i => $person): ?>
                    <tr>
                        <td class="center"><?= thaiDigits($i + 1) ?></td>
                        <td><?= h($person['name']) ?></td>
                        <td><?= h($person['role']) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </body>
    </html>
    <?php
    return (string) ob_get_clean();
}

function ensureTorHistoriesTable(): void
{
    requireDatabaseTables(['tor_histories']);
}

function splitCommittee(string $text, int $index): array
{
    $defaults = [
        ['name' => 'นางสาวชุติภัทร เงินอินต๊ะ', 'position' => 'เภสัชกรปฏิบัติการ', 'role' => 'ประธานกรรมการ'],
        ['name' => 'นางสาวพรไพลิน แก้วแสนขาว', 'position' => 'เภสัชกรปฏิบัติการ', 'role' => 'กรรมการ'],
        ['name' => 'นายไชยา บุญทานุช', 'position' => 'เจ้าพนักงานเภสัชกรรมปฏิบัติงาน', 'role' => 'กรรมการ'],
    ];
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), fn($line) => $line !== ''));
    return [
        'name' => $lines[0] ?? $defaults[$index]['name'],
        'position' => $lines[1] ?? $defaults[$index]['position'],
        'role' => $lines[2] ?? $defaults[$index]['role'],
    ];
}

$error = '';
$histories = [];
$history = null;
$caseId = (int) rawField('case_id', '0');
$activeCase = $caseId > 0 ? procurementCase($caseId) : null;
$savedDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'meeting_report') : null;
$savedMeetingExtra = is_array($savedDocument['extra'] ?? null) ? $savedDocument['extra'] : [];
$savedInvitationDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'committee_scope_invitation') : null;
$savedInvitationExtra = is_array($savedInvitationDocument['extra'] ?? null) ? $savedInvitationDocument['extra'] : [];
$savedTorDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'tor_company_purchase') : null;
$savedTorExtra = is_array($savedTorDocument['extra'] ?? null) ? $savedTorDocument['extra'] : [];
$historyId = (int) rawField('history_id', (string) ($savedMeetingExtra['history_id'] ?? $savedTorExtra['history_id'] ?? '0'));
try {
    ensureTorHistoriesTable();
    ensureTorHistoriesCaseColumn();
    if ($caseId > 0) {
        $stmt = db()->prepare('
            SELECT id, case_id, title, created_at
            FROM tor_histories
            WHERE case_id = ? OR id = ? OR case_id IS NULL
            ORDER BY
                CASE
                    WHEN case_id = ? THEN 0
                    WHEN id = ? THEN 1
                    ELSE 2
                END,
                created_at DESC,
                id DESC
            LIMIT 50
        ');
        $stmt->execute([$caseId, $historyId, $caseId, $historyId]);
        $histories = $stmt->fetchAll();
    } else {
        $stmt = db()->query('SELECT id, case_id, title, created_at FROM tor_histories ORDER BY created_at DESC, id DESC LIMIT 50');
        $histories = $stmt->fetchAll();
    }
    if ($historyId > 0) {
        $stmt = db()->prepare('SELECT * FROM tor_histories WHERE id = ?');
        $stmt->execute([$historyId]);
        $history = $stmt->fetch() ?: null;
    }
} catch (Throwable $e) {
    $error = 'ยังอ่านประวัติ TOR ไม่ได้ กรุณาออกเอกสาร TOR อย่างน้อย 1 ครั้ง หรือสร้างฐานข้อมูลด้วย schema.sql';
}
if ($caseId <= 0 && $history && !empty($history['case_id'])) {
    $caseId = (int) $history['case_id'];
    $activeCase = procurementCase($caseId);
    $savedDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'meeting_report') : null;
    $savedMeetingExtra = is_array($savedDocument['extra'] ?? null) ? $savedDocument['extra'] : [];
    $savedInvitationDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'committee_scope_invitation') : null;
    $savedInvitationExtra = is_array($savedInvitationDocument['extra'] ?? null) ? $savedInvitationDocument['extra'] : [];
    $savedTorDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'tor_company_purchase') : null;
    $savedTorExtra = is_array($savedTorDocument['extra'] ?? null) ? $savedTorDocument['extra'] : [];
}

$committeeRaw = $history ? (json_decode((string) $history['committee_json'], true) ?: []) : [];
$items = $history ? (json_decode((string) $history['items_json'], true) ?: []) : [];
$hasSelectedHistory = $historyId > 0 && $history !== null;
$committee = [
    splitCommittee((string) ($committeeRaw[0] ?? ''), 0),
    splitCommittee((string) ($committeeRaw[1] ?? ''), 1),
    splitCommittee((string) ($committeeRaw[2] ?? ''), 2),
];

$defaultPurposeLine = 'เพื่อจัดหาการจัดซื้อยา จำนวน ' . thaiDigits((string) count($items)) . ' รายการ';
$savedPurposeLine = savedValue($savedMeetingExtra, 'purpose_line', $defaultPurposeLine);
if ((int) ($savedMeetingExtra['history_id'] ?? 0) !== $historyId || (count($items) > 0 && str_contains($savedPurposeLine, 'จำนวน ๐ รายการ'))) {
    $savedPurposeLine = $defaultPurposeLine;
}
$meetingTitle = field('meeting_title', savedValue($savedMeetingExtra, 'meeting_title', 'รายงานการประชุมคณะกรรมการกำหนดร่างขอบเขตและกำหนดราคากลาง'));
$purposeLine = field('purpose_line', $savedPurposeLine);
$fiscalLine = field('fiscal_line', savedValue($savedMeetingExtra, 'fiscal_line', 'ประจำปีงบประมาณ พ.ศ. ๒๕๖๙'));
$meetingDate = field('meeting_date', savedValue($savedMeetingExtra, 'meeting_date', savedValue($savedInvitationExtra, 'meeting_date', '๒๔ เดือน กุมภาพันธ์ พ.ศ. ๒๕๖๘')));
$meetingTime = field('meeting_time', savedValue($savedMeetingExtra, 'meeting_time', savedValue($savedInvitationExtra, 'meeting_time', '๐๙.๓๐ น.')));
$meetingPlace = field('meeting_place', savedValue($savedMeetingExtra, 'meeting_place', 'ห้องประชุมโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง อำเภอสันกำแพง จังหวัดเชียงใหม่'));
$openTime = field('open_time', savedValue($savedMeetingExtra, 'open_time', savedValue($savedInvitationExtra, 'meeting_time', '๐๙.๓๐ น.')));
$closeTime = field('close_time', savedValue($savedMeetingExtra, 'close_time', '๑๑.๐๐ น.'));
$absentText = field('absent_text', savedValue($savedMeetingExtra, 'absent_text', 'ไม่มี'));

for ($i = 0; $i < 3; $i++) {
    $committee[$i]['name'] = field("committee_{$i}_name", $committee[$i]['name']);
    $committee[$i]['position'] = field("committee_{$i}_position", $committee[$i]['position']);
    $committee[$i]['role'] = field("committee_{$i}_role", $committee[$i]['role']);
}

$itemCountText = thaiDigits((string) count($items));
$itemListForSpeech = $items ? '[medicine_items]' : 'ยังไม่มีรายการยาจากประวัติ TOR กรุณาเลือกประวัติ TOR ที่บันทึกจากหน้าเอกสาร TOR';
$invitationMemoNo = trim((string) ($savedInvitationDocument['memo_no_suffix'] ?? ''));
$invitationDocDate = trim((string) ($savedInvitationDocument['doc_date'] ?? ''));
$invitationMemoNo = $invitationMemoNo !== '' ? thaiDigits($invitationMemoNo) : '.................';
$invitationDocDate = $invitationDocDate !== '' ? thaiDigits($invitationDocDate) : '..................................';
$referenceDocuments = [];
if ($activeCase) {
    $referenceDocuments[] = [
        'document_type' => 'principle_approval',
        'memo_no_suffix' => (string) ($activeCase['principle_memo_no_suffix'] ?? ''),
        'doc_date' => (string) ($activeCase['principle_doc_date'] ?? ''),
    ];
}
if ($savedInvitationDocument) {
    $referenceDocuments[] = [
        'document_type' => 'committee_scope_invitation',
        'memo_no_suffix' => (string) ($savedInvitationDocument['memo_no_suffix'] ?? ''),
        'doc_date' => (string) ($savedInvitationDocument['doc_date'] ?? ''),
    ];
}
if ($history) {
    $referenceDocuments[] = [
        'document_type' => 'tor_company_purchase',
        'history_id' => (int) ($history['id'] ?? 0),
        'title' => (string) ($history['title'] ?? ''),
        'created_at' => (string) ($history['created_at'] ?? ''),
    ];
}

$lawText = "๑. ระเบียบกระทรวงมหาดไทยว่าด้วยการเบิกค่าใช้จ่ายในการบริหารงานขององค์กรปกครองส่วนท้องถิ่น พ.ศ. ๒๕๖๒ หมวดที่ ๒ ค่าใช้สอย ข้อ ๑๑ ค่าใช้จ่ายที่เป็นค่าใช้สอยมีดังต่อไปนี้ (๒) ค่าจ้างเอกชนดำเนินงานขององค์กรปกครองส่วนท้องถิ่น\n"
    . "๒. พระราชบัญญัติการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. ๒๕๖๐\n"
    . "๓. ระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. ๒๕๖๐ หมวด ๒ ส่วนที่ ๒ กระบวนการซื้อหรือจ้าง ข้อ ๒๑ ในการซื้อหรือจ้างที่มิใช่การจ้างก่อสร้าง ให้หัวหน้าหน่วยงานของรัฐแต่งตั้งคณะกรรมการขึ้นมาคณะหนึ่ง หรือจะให้เจ้าหน้าที่หรือบุคคลใดบุคคลหนึ่งรับผิดชอบในการจัดทำร่างขอบเขตงานหรือรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง รวมทั้งกำหนดหลักเกณฑ์การพิจารณาคัดเลือกข้อเสนอด้วย\n"
    . "๔. หนังสือกรมส่งเสริมการปกครองส่วนท้องถิ่น ที่ มท ๐๘๐๘.๒/ว ๑๐๙๕ ลงวันที่ ๒๘ พฤษภาคม ๒๕๖๔ เรื่อง รูปแบบและการจำแนกประเภทรายรับ-รายจ่าย งบประมาณรายจ่ายประจำปีขององค์กรปกครองส่วนท้องถิ่น\n"
    . "๕. ระเบียบกระทรวงมหาดไทย ว่าด้วยเงินบำรุงโรงพยาบาลและหน่วยบริการสาธารณสุขขององค์กรปกครองส่วนท้องถิ่น (ฉบับที่ ๒) พ.ศ. ๒๕๖๑ (๑) ค่าใช้สอย รายจ่ายเพื่อให้ได้มาซึ่งบริการ\n"
    . "๖. หนังสือกระทรวงมหาดไทย ด่วนที่สุด ที่ มท ๐๘๐๘.๒/ว ๗๓๐๓ ลงวันที่ ๓๐ กันยายน ๒๕๖๕ เรื่อง ซักซ้อมแนวทางการปฏิบัติงานด้านการเงินการคลังของโรงพยาบาลและหน่วยบริการสาธารณสุขขององค์กรปกครองส่วนท้องถิ่น";

$defaultSpeeches = [
    [
        'agenda' => 'ระเบียบวาระที่ ๑',
        'speaker' => $committee[0]['name'] . "\n" . $committee[0]['role'],
        'content' => "ตามที่ได้เชิญคณะกรรมการฯ เข้าร่วมประชุมในครั้งนี้ สืบเนื่องจากการที่ได้รับแต่งตั้งเป็นคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง เพื่อจัดซื้อยา ใช้สนับสนุนการให้บริการรักษาประชาชน ของหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ประจำปีงบประมาณ พ.ศ. ๒๕๖๙ ตามบันทึกข้อความโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ที่................. ลงวันที่.................................. เรื่อง แจ้งคำสั่งและเชิญประชุมแต่งตั้งคณะกรรมการร่างขอบเขตและกำหนดราคากลาง เพื่อจัดหาการจัดซื้อยา จำนวน {$itemCountText} รายการ ประจำปีงบประมาณ พ.ศ. ๒๕๖๙ โดยให้มีหน้าที่พิจารณากลั่นกรองและกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลางของยาที่จะจัดซื้อ ให้ตรงตามวัตถุประสงค์และตามระเบียบราชการ ให้เกิดความคุ้มค่ามากที่สุด เพื่อให้การดำเนินงานตามอำนาจหน้าที่ของคณะกรรมการฯ เป็นไปด้วยความเรียบร้อย จึงได้เชิญท่านเข้าร่วมประชุมในครั้งนี้",
    ],
    ['agenda' => 'ระเบียบวาระที่ ๑', 'speaker' => 'มติที่ประชุม', 'topic' => '', 'content' => 'รับทราบ'],
    ['agenda' => 'ระเบียบวาระที่ ๒', 'speaker' => $committee[0]['name'] . "\n" . $committee[0]['role'], 'topic' => '', 'content' => $lawText],
    [
        'agenda' => 'ระเบียบวาระที่ ๓',
        'speaker' => $committee[0]['name'] . "\n" . $committee[0]['role'],
        'content' => "ขอให้คณะกรรมการร่วมกันพิจารณารายละเอียดคุณลักษณะเฉพาะและกำหนดราคากลาง เพื่อจัดหาการจัดซื้อยา จำนวน {$itemCountText} รายการ ดังรายการต่อไปนี้\n{$itemListForSpeech}\nเพื่อเป็นไปตามระเบียบพัสดุอย่างรอบคอบ เพื่อใช้สนับสนุนการให้บริการรักษาประชาชนของหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ประจำปีงบประมาณ พ.ศ. ๒๕๖๙",
    ],
    [
        'agenda' => 'ระเบียบวาระที่ ๓',
        'speaker' => 'มติที่ประชุม',
        'topic' => '',
        'content' => "เห็นชอบกำหนดรายละเอียดคุณลักษณะเฉพาะ ดังนี้\n๑. เป็นยาในบัญชียาหลักแห่งชาติที่องค์การเภสัชกรรมมีจัดจำหน่าย\n๒. ส่วนประกอบยาแต่ละรายการตามชื่อยาสามัญทางยา ที่กำหนด {$itemCountText} รายการ\n๓. บรรจุภัณฑ์สามารถบรรจุในขวดปราศจากเชื้อ ภาชนะปิดสนิท แผง สตริป หรือบรรจุภัณฑ์ที่ผ่านการรับรองมาตรฐาน\n๔. ฉลาก\n- ระบุชื่อยา ส่วนประกอบสำคัญและความแรง วันผลิต วันสิ้นอายุ เลขที่ผลิต และเลขทะเบียนตำรับยาไว้ชัดเจนปรากฏบนบรรจุภัณฑ์\n- กรณีที่บรรจุในขวด หลอด แผง ต้องระบุวัน เดือน ปีที่ยาหมดอายุ และเลขที่ผลิตไว้อย่างชัดเจน",
    ],
    [
        'agenda' => 'ระเบียบวาระที่ ๓',
        'speaker' => $committee[1]['name'],
        'topic' => '',
        'content' => 'จากการพิจารณาขอบเขตงานและราคากลางที่อ้างอิงราคากลาง อ้างอิงตาระเบียบการจัดซื้อจัดจ้าง ตามกฎกระทรวงกำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุที่รัฐต้องการส่งเสริมหรือสนับสนุน พ.ศ.2563 ข้อ 15 (๒) ยาในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยา ซึ่งองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหาร ได้ผลิตออกจำหน่ายแล้ว และข้อ ๑7 ให้หน่วยงานของรัฐจัดซื้อพัสดุตามข้อ 15 (2) โดยวิธีเฉพาะเจาะจงจากองค์การเภสัชกรรม หรือสภากาชาดไทย เว้นแต่หน่วยงานในสังกัดกระทรวงกลาโหมให้จัดซื้อจากโรงงานเภสัชกรรมทหารสำหรับหน่วยงานในสังกัดสำนักงานตำรวจแห่งชาติจะซื้อจากองค์การเภสัชกรรมหรือโรงงานเภสัชกรรมทหารก็ได้ ทั้งนี้ หน่วยงานของรัฐสามารถจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มีใช่ยาจากผู้ขายรายอื่นได้ หากองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหาร ไม่สามารถผลิตออกจำหน่ายให้แก่หน่วยงานของรัฐได้ทันกำหนดตามที่หน่วยงานของรัฐได้แจ้งแผนประจำปีล่วงหน้า ประกอบกับหนังสือคณะกรรมการวินิจฉัยปัญหา จัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ที่ กค (กวจ) 0405.2/ว 119 ลงวันที่ 12 มีนาคม 2562 ข้อ ๓.๑.๑ ให้หน่วยงานของรัฐจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยาซึ่งองค์การเภสัชกรรมหรือสภากาชาดไทยได้ผลิตออกจำหน่ายแล้ว โดยวิธีเฉพาะเจาะจงจากองค์การเภสัชกรรมหรือสภากาชาดไทย เว้นแต่หน่วยงานในสังกัดกระทรวงกลาโหมให้จัดซื้อจากโรงงานเภสัชกรรมทหารสำหรับหน่วยงานในสังกัดสำนักงานตำรวจแห่งชาติจะซื้อจากองค์การเภสัชกรรมหรือโรงงานเภสัชกรรมทหารก็ได้ ทั้งนี้ หน่วยงานของรัฐสามารถจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยาจากผู้ขายรายอื่นได้หากองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหาร ไม่สามารถผลิตออกจำหน่ายให้แก่หน่วยงานของรัฐได้ทันกำหนดตามที่หน่วยงานของรัฐได้แจ้งแผนประจำปีล่วงหน้า',
    ],
    [
        'agenda' => 'ระเบียบวาระที่ ๓',
        'speaker' => $committee[2]['name'],
        'topic' => '',
        'content' => 'จากประวัติการจัดซื้อจัดจ้างย้อนหลัง ๒ ปี และข้อมูลราคาที่มีอยู่ เห็นควรเสนอจัดซื้อจัดจ้างตามระเบียบ โดยอ้างอิงจากการจัดซื้อจัดจ้างตามระเบียบ โดยสามารถอ้างอิงราคาที่เคยซื้อย้อนหลัง 2 ปี หรือสืบราคาร้านค้าเพื่อใช้ประกอบการกำหนดราคากลางไม่น้อยกว่า 3 ร้านตามระยะเวลาที่กำหนดให้เสนอราคา',
    ],
    [
        'agenda' => 'ระเบียบวาระที่ ๓',
        'speaker' => 'มติที่ประชุม',
        'topic' => '',
        'content' => 'เห็นชอบกำหนดราคากลาง โดยใช้ราคากลางจาก อ้างอิงตามหนังสือคณะกรรมการวินิจฉัยปัญหา จัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ที่ กค (กวจ) ๐๔๐๕.๒/ว ๑๑๙ ลงวันที่ ๑๒ มีนาคม ๒๕๖๒ สืบราคาไม่ต่ำกว่า 3 ร้าน โดยไม่เกินราคาที่ได้มาจากการคํานวณตามหลักเกณฑ์ที่คณะกรรมการราคากลางกำหนดจาก ประกาศคณะกรรมการพัฒนาระบบยาแห่งชาติ เรื่อง กำหนดราคากลางยา ลงวันที่ ๒๕ สิงหาคม ๒๕๖๘ (ฉบับลงราชกิจจานุเบกษา) โดยมีผู้เสนอราคา จำนวน ๑ ร้านได้แก่ บริษัท ................................................... รายละเอียด ดังนี้',
    ],
];
$defaultSpeeches = array_map(static function (array $speech) use ($invitationMemoNo, $invitationDocDate): array {
    if (isset($speech['content'])) {
        $speech['content'] = str_replace(
            'ที่................. ลงวันที่..................................',
            'ที่ ' . $invitationMemoNo . ' ลงวันที่ ' . $invitationDocDate,
            (string) $speech['content']
        );
    }
    return $speech;
}, $defaultSpeeches);
$postedSpeakers = $_POST['speech_speaker'] ?? null;
$savedSpeeches = is_array($savedMeetingExtra['speeches'] ?? null) ? $savedMeetingExtra['speeches'] : [];
if ((int) ($savedMeetingExtra['history_id'] ?? 0) !== $historyId) {
    $savedSpeeches = [];
}
if ($savedSpeeches && count($items) > 0) {
    foreach ($savedSpeeches as $savedSpeech) {
        if (is_array($savedSpeech) && str_contains((string) ($savedSpeech['content'] ?? ''), 'จำนวน ๐ รายการ')) {
            $savedSpeeches = [];
            break;
        }
    }
}
$speeches = [];
if (is_array($postedSpeakers)) {
    $agendas = $_POST['speech_agenda'] ?? [];
    $topics = $_POST['speech_topic'] ?? [];
    $contents = $_POST['speech_content'] ?? [];
    $max = max(count($agendas), count($postedSpeakers), count($topics), count($contents));
    for ($i = 0; $i < $max; $i++) {
            $agenda = thaiDigits(trim((string) ($agendas[$i] ?? '')));
            $speaker = thaiDigits(trim((string) ($postedSpeakers[$i] ?? '')));
            $topic = thaiDigits(trim((string) ($topics[$i] ?? '')));
            $content = thaiDigits(trim((string) ($contents[$i] ?? '')));
        if ($agenda !== '' || $speaker !== '' || $topic !== '' || $content !== '') {
            $speeches[] = ['agenda' => $agenda, 'speaker' => $speaker, 'topic' => $topic, 'content' => $content];
        }
    }
}
if (!$speeches) $speeches = $savedSpeeches ?: $defaultSpeeches;
$agendaTitles = [
    'ระเบียบวาระที่ ๑' => 'เรื่อง ประธานแจ้งให้ที่ประชุมทราบ',
    'ระเบียบวาระที่ ๒' => 'เรื่อง ข้อกฎหมาย ระเบียบ และหนังสือสั่งการที่เกี่ยวข้อง',
    'ระเบียบวาระที่ ๓' => 'พิจารณากำหนดรายละเอียดคุณลักษณะเฉพาะและกำหนดราคากลางเพื่อจัดหา',
    'ระเบียบวาระที่ ๔' => 'เรื่องอื่นๆ (ถ้ามี)',
];
$agendaOrder = array_keys($agendaTitles);
$speechesByAgenda = [];
foreach ($agendaOrder as $agendaName) {
    $speechesByAgenda[$agendaName] = [];
}
foreach ($speeches as $speech) {
    $agenda = trim((string) ($speech['agenda'] ?? ''));
    if ($agenda === '' || !isset($speechesByAgenda[$agenda])) {
        $agenda = 'ระเบียบวาระที่ ๓';
    }
    $speech['agenda'] = $agenda;
    $speechesByAgenda[$agenda][] = $speech;
}

if (($_POST['action'] ?? '') === 'save_document') {
    if ($caseId <= 0 || !$activeCase) {
        $error = 'กรุณาเลือกชุดข้อมูลจาก TOR ก่อนบันทึก';
    } elseif ($historyId <= 0 || !$history) {
        $error = 'กรุณาเลือกข้อมูล TOR จากหัวข้อ 1 ก่อนบันทึก';
    } else {
        saveProcurementDocument($caseId, 'meeting_report', '', '', [
            'history_id' => $historyId,
            'meeting_title' => $meetingTitle,
            'purpose_line' => $purposeLine,
            'fiscal_line' => $fiscalLine,
            'meeting_date' => $meetingDate,
            'meeting_time' => $meetingTime,
            'meeting_place' => $meetingPlace,
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'absent_text' => $absentText,
            'committee' => $committee,
            'items' => itemSnapshot($items),
            'references' => $referenceDocuments,
            'speeches' => $speeches,
            'saved_at' => date('c'),
        ]);
        header('Location: meeting_report.php?case_id=' . $caseId . '&history_id=' . $historyId . '&saved=1');
        exit;
    }
}

$offerTotal = array_sum(array_map('meetingItemOfferTotal', $items));
$hasPackageItems = count(array_filter($items, 'meetingItemHasPackage')) > 0;
if (($_POST['action'] ?? '') === 'word') {
    $meetingWordHtml = meetingReportWordHtml([
        'meeting_title' => $meetingTitle,
        'purpose_line' => $purposeLine,
        'fiscal_line' => $fiscalLine,
        'meeting_date' => $meetingDate,
        'meeting_time' => $meetingTime,
        'meeting_place' => $meetingPlace,
        'open_time' => $openTime,
        'close_time' => $closeTime,
        'absent_text' => $absentText,
        'committee' => $committee,
        'items' => $items,
        'offer_total' => $offerTotal,
        'agenda_order' => $agendaOrder,
        'agenda_titles' => $agendaTitles,
        'speeches_by_agenda' => $speechesByAgenda,
    ]);
    try {
        outputWordHtml($meetingWordHtml, meetingReportWordFilename($meetingDate, $itemCountText), '', 'meeting');
    } catch (Throwable $wordError) {
        error_log('Meeting report DOCX export failed: ' . $wordError->getMessage());
        $error = 'สร้างไฟล์ Word ไม่สำเร็จ กรุณาตรวจสอบว่าอัปโหลด core/word_export_lib.php และ assets/fonts ครบ และ PHP เปิดใช้งาน ZipArchive';
    }
}

$headerFields = [
    'meeting_title' => ['label' => 'ชื่อรายงาน', 'value' => $meetingTitle],
    'purpose_line' => ['label' => 'บรรทัดวัตถุประสงค์/จำนวนรายการ', 'value' => $purposeLine],
    'fiscal_line' => ['label' => 'ปีงบประมาณ', 'value' => $fiscalLine],
];
$timeFields = [
    'meeting_date' => ['label' => 'วันที่ประชุม', 'value' => $meetingDate],
    'meeting_time' => ['label' => 'เวลาตามหัวรายงาน', 'value' => $meetingTime],
    'open_time' => ['label' => 'เวลาเปิดประชุม', 'value' => $openTime],
    'close_time' => ['label' => 'เวลาปิดประชุม', 'value' => $closeTime],
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>รายงานการประชุม</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @counter-style thai-page {
            system: numeric;
            symbols: "๐" "๑" "๒" "๓" "๔" "๕" "๖" "๗" "๘" "๙";
            suffix: "";
        }
        @page { size: A4; margin: 2.5cm 2cm 2cm 2cm; }
        @page report {
            @top-center {
                content: "- " counter(page, thai-page) " -";
                font-family: "TH Sarabun New", "Sarabun", sans-serif;
                font-size: 16pt;
            }
        }
        @page report:first {
            @top-center { content: ""; }
        }
        @page attendance {
            @top-center { content: ""; }
        }
        .report-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 12mm;
            padding: 2.5cm 2cm 2cm 2cm;
            box-sizing: border-box;
            background: #fff;
            color: #111;
            font-family: "TH Sarabun New", "Sarabun", sans-serif;
            font-size: 16pt;
            line-height: 1;
        }
        .report-page * { color: #111 !important; }
        .doc-title { text-align: center; font-weight: 700; line-height: 1; }
        .attendee-grid { display: grid; grid-template-columns: 1cm 6.5cm 5.2cm 1fr; column-gap: .25cm; }
        .speech-row { display: grid; grid-template-columns: 4.8cm 1fr; column-gap: .45cm; margin-top: 0; align-items: start; }
        .agenda-heading { margin-top: .22cm; }
        .agenda-heading.first-agenda { margin-top: .08cm; }
        .speech-left,
        .speech-body { line-height: 1; }
        .speaker { margin-top: 0; white-space: normal; font-weight: 700; line-height: 1; }
        .speaker-line { display: block; line-height: 1; margin: 0; padding: 0; }
        .topic { font-weight: 700; white-space: normal; }
        .thai-justify,
        .content {
            text-align: justify;
            text-align-last: left;
            text-justify: inter-character;
            -ms-text-justify: distribute;
        }
        .content { white-space: normal; }
        .medicine-lines { margin: .05cm 0; }
        .medicine-line { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 46%); column-gap: .3cm; line-height: 1; }
        .medicine-line > div { min-width: 0; overflow-wrap: anywhere; word-break: break-word; }
        .medicine-line .qty { text-align: left; white-space: normal; }
        .content br + br,
        .topic br + br,
        .speaker br + br { display: none; }
        .medicine-table { width: 100%; border-collapse: collapse; margin-top: .4cm; }
        .medicine-table th, .medicine-table td { border: 0.5pt solid #000; padding: 3px 6px; vertical-align: top; }
        .medicine-table th { text-align: center; font-weight: 700; }
        .attendance-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse !important;
            border-spacing: 0 !important;
            margin-top: 1cm;
            border: 0.5pt solid #000 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            overflow: visible;
            font-family: "TH Sarabun New", "Sarabun", sans-serif;
            font-size: 16pt;
            line-height: 1;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        .attendance-table th,
        .attendance-table td {
            border: 0.5pt solid #000 !important;
            border-radius: 0 !important;
            padding: 5px 8px;
            vertical-align: top;
            font-family: "TH Sarabun New", "Sarabun", sans-serif !important;
            font-size: 16pt !important;
            font-weight: 400;
            line-height: 1 !important;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .attendance-table th { text-align: center; font-weight: 700; }
        .center { text-align: center; }
        .right { text-align: right; }
        .signature-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1cm; margin-top: 1.2cm; text-align: center; }
        .signature-grid .chair { grid-column: 1 / -1; }
        .report-main { page: report; }
        .attendance-page { page: attendance; }
        @media print {
            body { background: #fff !important; margin: 0 !important; }
            .no-print { display: none !important; }
            main { display: block !important; max-width: none !important; padding: 0 !important; margin: 0 !important; }
            .report-page { width: auto; min-height: 0; margin: 0; padding: 0; page-break-after: always; }
            .report-page:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100">
<?php require __DIR__ . '/components/navbar.php'; ?>
<?php require __DIR__ . '/components/workflow_steps.php'; ?>
<main class="mx-auto grid max-w-[96rem] gap-6 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_560px]">
    <aside class="no-print order-2 rounded-lg border border-zinc-800 bg-zinc-900/80 p-4 lg:sticky lg:top-4 lg:max-h-[calc(100vh-2rem)] lg:overflow-auto">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-lg font-semibold">รายงานการประชุม</h1>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="window.print()" class="rounded-md bg-emerald-500 px-3 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">พิมพ์ / PDF</button>
                <button type="submit" form="meeting_report_form" name="action" value="word" class="rounded-md border border-emerald-500/60 px-3 py-2 text-sm font-medium text-emerald-200 hover:bg-emerald-500/10">ดาวน์โหลด Word</button>
            </div>
        </div>
        <?php if ($error): ?>
            <div class="mb-4 rounded-md border border-red-500/40 bg-red-950/60 p-3 text-sm text-red-100"><?= h($error) ?></div>
        <?php endif; ?>
        <?php if (($_GET['saved'] ?? '') === '1' && $caseId > 0): ?>
            <div class="mb-4 rounded-md border border-emerald-500/40 bg-emerald-500/10 p-3 text-sm text-emerald-100">
                บันทึกเอกสารหัวข้อ 4 เรียบร้อยแล้ว
            </div>
        <?php endif; ?>
        <form method="get" action="meeting_report.php" class="mb-4 rounded-md border border-zinc-800 p-3">
                <div class="mb-3 text-sm font-medium text-zinc-100">1. เลือกชุดข้อมูลจาก TOR</div>
                <label class="block">
                    <span class="text-sm text-zinc-300">ประวัติ TOR ที่บันทึกไว้</span>
                    <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                    <select name="history_id" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" onchange="this.form.case_id.value = this.selectedOptions[0]?.dataset.caseId || ''; this.form.submit()">
                        <option value="">เลือกประวัติ TOR</option>
                        <?php if (!$histories): ?>
                            <option value="">ยังไม่มีประวัติ TOR</option>
                        <?php endif; ?>
                        <?php foreach ($histories as $row): ?>
                            <option value="<?= (int) $row['id'] ?>" data-case-id="<?= (int) ($row['case_id'] ?? 0) ?>" <?= $history && (int) $history['id'] === (int) $row['id'] ? 'selected' : '' ?>><?= h($row['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <p class="mt-2 text-xs text-zinc-500">เลือกประวัติ TOR เพื่อดึงรายชื่อกรรมการและรายการยามาใช้ในรายงานการประชุม</p>
        </form>

        <form id="meeting_report_form" method="post" class="grid gap-4">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
            <input type="hidden" name="history_id" value="<?= (int) $historyId ?>">
            <section class="rounded-md border border-zinc-800">
                <button type="button" class="accordion-toggle flex w-full items-center justify-between gap-3 p-3 text-left text-sm font-medium text-zinc-100" aria-expanded="false">
                    <span>2. หัวเอกสาร</span>
                    <span class="accordion-icon text-zinc-400">เปิด</span>
                </button>
                <div class="accordion-panel hidden border-t border-zinc-800 p-3">
                    <?php foreach ($headerFields as $name => $field): ?>
                        <label class="mb-3 block">
                            <span class="text-sm text-zinc-300"><?= h($field['label']) ?></span>
                            <input name="<?= h($name) ?>" value="<?= h($field['value']) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rounded-md border border-zinc-800">
                <button type="button" class="accordion-toggle flex w-full items-center justify-between gap-3 p-3 text-left text-sm font-medium text-zinc-100" aria-expanded="false">
                    <span>3. วัน เวลา และสถานที่ประชุม</span>
                    <span class="accordion-icon text-zinc-400">เปิด</span>
                </button>
                <div class="accordion-panel hidden border-t border-zinc-800 p-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <?php foreach ($timeFields as $name => $field): ?>
                            <label class="block">
                                <span class="text-sm text-zinc-300"><?= h($field['label']) ?></span>
                                <input name="<?= h($name) ?>" value="<?= h($field['value']) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <label class="mt-3 block">
                        <span class="text-sm text-zinc-300">สถานที่ประชุม</span>
                        <input name="meeting_place" value="<?= h($meetingPlace) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                    <label class="mt-3 block">
                        <span class="text-sm text-zinc-300">ผู้ไม่เข้าประชุม</span>
                        <input name="absent_text" value="<?= h($absentText) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                </div>
            </section>

            <section class="rounded-md border border-zinc-800">
                <button type="button" class="accordion-toggle flex w-full items-center justify-between gap-3 p-3 text-left text-sm font-medium text-zinc-100" aria-expanded="false">
                    <span>4. รายชื่อคณะกรรมการ</span>
                    <span class="accordion-icon text-zinc-400">เปิด</span>
                </button>
                <div class="accordion-panel hidden border-t border-zinc-800 p-3">
                    <?php foreach ($committee as $i => $person): ?>
                        <div class="mb-3 rounded-md border border-zinc-800 bg-zinc-950 p-3">
                            <div class="mb-2 text-xs font-medium text-emerald-300">กรรมการคนที่ <?= thaiDigits($i + 1) ?></div>
                            <label class="mb-2 block">
                                <span class="text-xs text-zinc-400">ชื่อ-สกุล</span>
                                <input name="committee_<?= $i ?>_name" value="<?= h($person['name']) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                            </label>
                            <label class="mb-2 block">
                                <span class="text-xs text-zinc-400">ตำแหน่งราชการ</span>
                                <input name="committee_<?= $i ?>_position" value="<?= h($person['position']) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                            </label>
                            <label class="block">
                                <span class="text-xs text-zinc-400">บทบาทในคณะกรรมการ</span>
                                <input name="committee_<?= $i ?>_role" value="<?= h($person['role']) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rounded-md border border-zinc-800 p-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <div class="text-sm font-medium text-zinc-100">5. หัวข้อและรายละเอียดการประชุม</div>
                </div>
                <div id="speech_editor" class="grid gap-3">
                    <?php foreach ($agendaOrder as $agendaName): ?>
                        <div class="agenda-editor rounded-md border border-zinc-800 bg-zinc-950 p-3" data-agenda="<?= h($agendaName) ?>">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <div class="text-sm font-medium text-emerald-300"><?= h($agendaName) ?></div>
                                <button type="button" class="add-speech rounded-md border border-zinc-700 px-2 py-1 text-xs hover:border-emerald-400" data-agenda="<?= h($agendaName) ?>">เพิ่มหัวข้อในวาระนี้</button>
                            </div>
                            <div class="agenda-items grid gap-3">
                                <?php foreach ($speechesByAgenda[$agendaName] as $speech): ?>
                                    <div class="speech-editor-row rounded-md border border-zinc-800 bg-zinc-900/70 p-3">
                                        <input type="hidden" name="speech_agenda[]" value="<?= h($agendaName) ?>">
                                        <input type="hidden" name="speech_topic[]" value="">
                                        <textarea name="speech_speaker[]" rows="2" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-sm leading-relaxed outline-none focus:border-emerald-400" placeholder="ผู้พูด/มติที่ประชุม"><?= h((string) ($speech['speaker'] ?? '')) ?></textarea>
                                        <textarea name="speech_content[]" rows="8" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-sm leading-relaxed outline-none focus:border-emerald-400" placeholder="รายละเอียด"><?= h((string) ($speech['content'] ?? '')) ?></textarea>
                                        <button type="button" class="remove-speech mt-2 rounded-md border border-red-500/50 px-2 py-1 text-xs text-red-200 hover:bg-red-500/10">ลบหัวข้อนี้</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <button type="submit" name="action" value="save_document" class="app-primary-save rounded-md border border-emerald-500/60 px-4 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10">บันทึกเอกสารหัวข้อ 4</button>
        </form>
    </aside>

    <section class="order-1 overflow-auto">
        <article class="report-page report-main">
            <?php if ($hasSelectedHistory): ?>
            <div class="doc-title">
                <div><?= h($meetingTitle) ?></div>
                <div><?= h($purposeLine) ?></div>
                <div><?= h($fiscalLine) ?></div>
                <div>วันที่ <?= h($meetingDate) ?> เวลา <?= h($meetingTime) ?></div>
                <div>ณ&nbsp;&nbsp;<?= h($meetingPlace) ?></div>
            </div>

            <div class="mt-8 font-bold">ผู้เข้าประชุม</div>
            <?php foreach ($committee as $i => $person): ?>
                <div class="attendee-grid thai-justify">
                    <div><?= thaiDigits($i + 1) ?>.</div>
                    <div><?= h($person['name']) ?></div>
                    <div><?= h($person['position']) ?></div>
                    <div><?= h($person['role']) ?></div>
                </div>
            <?php endforeach; ?>
            <div class="mt-2 font-bold">ผู้ไม่เข้าประชุม&nbsp;&nbsp;&nbsp;&nbsp;<span class="font-normal"><?= h($absentText) ?></span></div>
            <div class="mt-2 font-bold">เปิดประชุม&nbsp;&nbsp;&nbsp;&nbsp;<span class="font-normal">เวลา <?= h($openTime) ?></span></div>

            <?php $shownAgendaIndex = 0; ?>
            <?php foreach ($agendaOrder as $agendaName): ?>
                <?php if (empty($speechesByAgenda[$agendaName])) continue; ?>
                <div class="speech-row agenda-heading <?= $shownAgendaIndex === 0 ? 'first-agenda' : '' ?>">
                    <div class="topic"><?= h($agendaName) ?></div>
                    <div class="topic"><?= h($agendaTitles[$agendaName] ?? '') ?></div>
                </div>
                <?php $shownAgendaIndex++; ?>
                <?php foreach ($speechesByAgenda[$agendaName] as $speechIndex => $speech): ?>
                    <div class="speech-row thai-justify">
                        <div class="speech-left">
                            <?php $speakerLines = array_values(array_filter(array_map('trim', explode("\n", displayText((string) ($speech['speaker'] ?? '')))), fn($line) => $line !== '')); ?>
                            <?php if ($speakerLines): ?>
                                <div class="speaker">
                                    <?php foreach ($speakerLines as $line): ?>
                                        <span class="speaker-line"><?= h($line) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="speech-body">
                            <?php $contentText = displayText((string) ($speech['content'] ?? '')); ?>
                            <?php if (str_contains($contentText, '[medicine_items]')): ?>
                                <?php [$beforeItems, $afterItems] = array_pad(explode('[medicine_items]', $contentText, 2), 2, ''); ?>
                                <div class="content"><?= compactBreaks(nl2br(h(displayText($beforeItems)))) ?></div>
                                <div class="medicine-lines">
                                    <?php foreach ($items as $i => $item): ?>
                                        <div class="medicine-line">
                                            <div><?= thaiDigits($i + 1) ?>. <?= h((string) ($item['name'] ?? '')) ?></div>
                                            <div class="qty"><?= h(meetingItemQuantityLine($item)) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="content"><?= compactBreaks(nl2br(h(displayText($afterItems)))) ?></div>
                            <?php else: ?>
                                <div class="content"><?= compactBreaks(nl2br(h($contentText))) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if ($agendaName === 'ระเบียบวาระที่ ๓' && $items): ?>
                    <table class="medicine-table">
                        <thead>
                        <tr>
                            <th style="width: 8%"><?= $hasPackageItems ? 'ที่' : 'ลำดับ' ?></th>
                            <th>รายการยา</th>
                            <th style="width: 14%">ราคากลาง</th>
                            <th style="width: 14%">ราคาเสนอ</th>
                            <th style="width: <?= $hasPackageItems ? '11%' : '14%' ?>">จำนวน</th>
                            <th style="width: <?= $hasPackageItems ? '20%' : '12%' ?>"><?= $hasPackageItems ? 'ขนาดบรรจุ/หน่วยนับ' : 'หน่วยนับ' ?></th>
                            <th style="width: 16%">มูลค่ารวม</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $i => $item): ?>
                            <tr>
                                <td class="center"><?= thaiDigits($i + 1) ?></td>
                                <td><?= h((string) ($item['name'] ?? '')) ?></td>
                                <td class="right"><?= money((float) ($item['reference_price'] ?? 0)) ?></td>
                                <td class="right"><?= money((float) ($item['offer_price'] ?? 0)) ?></td>
                                <td class="right"><?= quantityText(meetingItemQuantity($item)) ?></td>
                                <td class="center"><?= h(meetingItemUnitText($item)) ?></td>
                                <td class="right"><?= money(meetingItemOfferTotal($item)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="6" class="center">รวม</td>
                            <td class="right"><?= money((float) $offerTotal) ?></td>
                        </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="mt-2 font-bold">ปิดประชุม&nbsp;&nbsp;&nbsp;&nbsp;<span class="font-normal"><?= h($closeTime) ?></span></div>

            <div class="signature-grid">
                <div class="chair">
                    (ลงชื่อ)........................................ประธานกรรมการ<br>
                    <?= h($committee[0]['name']) ?><br><?= h($committee[0]['position']) ?>
                </div>
                <div>
                    (ลงชื่อ)........................................กรรมการ<br>
                    <?= h($committee[1]['name']) ?><br><?= h($committee[1]['position']) ?>
                </div>
                <div>
                    (ลงชื่อ)........................................กรรมการ<br>
                    <?= h($committee[2]['name']) ?><br><?= h($committee[2]['position']) ?>
                </div>
            </div>
            <?php endif; ?>
        </article>

        <article class="report-page attendance-page">
            <?php if ($hasSelectedHistory): ?>
            <div class="doc-title">
                <div><?= h($meetingTitle) ?></div>
                <div><?= h($purposeLine) ?></div>
                <div><?= h($fiscalLine) ?></div>
                <div>วันที่ <?= h($meetingDate) ?> เวลา <?= h($meetingTime) ?></div>
                <div>ณ&nbsp;&nbsp;<?= h($meetingPlace) ?></div>
            </div>

            <table class="attendance-table">
                <thead>
                <tr>
                    <th style="width: 8%">ลำดับ</th>
                    <th>รายชื่อคณะกรรมการ<br>ผู้เข้าร่วมประชุม</th>
                    <th style="width: 20%">ตำแหน่ง</th>
                    <th style="width: 20%">ลายมือชื่อ</th>
                    <th style="width: 20%">หมายเหตุ</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($committee as $i => $person): ?>
                    <tr>
                        <td class="center"><?= thaiDigits($i + 1) ?></td>
                        <td><?= h($person['name']) ?></td>
                        <td><?= h($person['role']) ?></td>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </article>
    </section>
</main>
<script src="assets/js/word_export.js"></script>
<script>
const speechEditor = document.getElementById('speech_editor');
const thaiDigits = (value) => String(value).replace(/[0-9]/g, (digit) => String.fromCharCode(0x0E50 + Number(digit)));

function bindThaiDigitInputs(scope = document) {
    scope.querySelectorAll('input:not([type="hidden"]), textarea').forEach((field) => {
        if (field.dataset.thaiDigitsBound === '1') return;
        field.dataset.thaiDigitsBound = '1';
        const convert = () => {
            const start = field.selectionStart ?? field.value.length;
            const end = field.selectionEnd ?? field.value.length;
            const converted = thaiDigits(field.value);
            if (field.value !== converted) {
                field.value = converted;
                field.setSelectionRange?.(start, end);
            }
        };
        field.addEventListener('input', convert);
        field.addEventListener('change', convert);
        convert();
    });
}

bindThaiDigitInputs();

document.querySelectorAll('.accordion-toggle').forEach((button) => {
    button.addEventListener('click', () => {
        const panel = button.nextElementSibling;
        const icon = button.querySelector('.accordion-icon');
        const isOpen = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!isOpen));
        panel?.classList.toggle('hidden', isOpen);
        if (icon) icon.textContent = isOpen ? 'เปิด' : 'ปิด';
    });
});
speechEditor?.addEventListener('click', (event) => {
    const addButton = event.target.closest('.add-speech');
    if (!addButton) return;
    const agenda = addButton.dataset.agenda || '';
    const target = addButton.closest('.agenda-editor')?.querySelector('.agenda-items');
    const row = document.createElement('div');
    row.className = 'speech-editor-row rounded-md border border-zinc-800 bg-zinc-900/70 p-3';
    row.innerHTML = `
        <input type="hidden" name="speech_agenda[]" value="${escapeHtml(agenda)}">
        <input type="hidden" name="speech_topic[]" value="">
        <textarea name="speech_speaker[]" rows="2" class="w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-sm leading-relaxed outline-none focus:border-emerald-400" placeholder="ผู้พูด/มติที่ประชุม"></textarea>
        <textarea name="speech_content[]" rows="8" class="mt-2 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2.5 text-sm leading-relaxed outline-none focus:border-emerald-400" placeholder="รายละเอียด"></textarea>
        <button type="button" class="remove-speech mt-2 rounded-md border border-red-500/50 px-2 py-1 text-xs text-red-200 hover:bg-red-500/10">ลบหัวข้อนี้</button>
    `;
    target?.appendChild(row);
    bindThaiDigitInputs(row);
});
speechEditor?.addEventListener('click', (event) => {
    const button = event.target.closest('.remove-speech');
    if (button) button.closest('.speech-editor-row')?.remove();
});

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>
</body>
</html>
