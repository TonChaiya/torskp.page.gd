<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require __DIR__ . '/core/word_export_lib.php';
require __DIR__ . '/core/auth.php';
requireLogin();

// Display-only Thai digits for exported DOCX (including fixed text inside the existing Word templates).
// XML structure, document IDs, field instructions, dates and numeric database values remain unchanged.
function thaiDocxVisibleDigits(string $text): string
{
    return preg_replace_callback('/&(?:#[0-9]+|#x[0-9a-f]+|[a-z][a-z0-9]+);|[0-9]+/i', static function (array $part): string {
        if ($part[0][0] === '&') return $part[0];
        return strtr($part[0], [
            '0'=>'๐', '1'=>'๑', '2'=>'๒', '3'=>'๓', '4'=>'๔',
            '5'=>'๕', '6'=>'๖', '7'=>'๗', '8'=>'๘', '9'=>'๙',
        ]);
    }, $text) ?? $text;
}
function thaiDocxVisibleHtml(string $html): string
{
    // Leave tags, CSS, JavaScript, attributes and HTML entities untouched.
    return preg_replace_callback(
        '~<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|<!--.*?-->|<[^>]+>|[^<]+~is',
        static fn(array $part): string => str_starts_with($part[0], '<') ? $part[0] : thaiDocxVisibleDigits($part[0]),
        $html
    ) ?? $html;
}
function thaiDocxDigitResponse(string $bytes): string
{
    if (!str_starts_with($bytes, "PK\x03\x04") || !class_exists(ZipArchive::class)) return $bytes;
    $temporary = tempnam(sys_get_temp_dir(), 'thai_docx_digits_');
    if ($temporary === false) return $bytes;
    try {
        if (file_put_contents($temporary, $bytes) === false) return $bytes;
        $zip = new ZipArchive();
        if ($zip->open($temporary) !== true) return $bytes;
        try {
            for ($i = 0, $count = $zip->numFiles; $i < $count; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) continue;
                if (preg_match('~^word/(?:document|header[0-9]+|footer[0-9]+|footnotes|endnotes|comments[0-9]*)\.xml$~i', $name)) {
                    $xml = $zip->getFromIndex($i);
                    if (!is_string($xml)) continue;
                    $converted = preg_replace_callback(
                        '~(<w:(t|delText)\b[^>]*>)(.*?)(</w:\\2>)~su',
                        static fn(array $match): string => $match[1] . thaiDocxVisibleDigits($match[3]) . $match[4],
                        $xml
                    ) ?? $xml;
                    if ($converted !== $xml) $zip->addFromString($name, $converted);
                } elseif (preg_match('~^word/afchunk\.html$~i', $name)) {
                    $html = $zip->getFromIndex($i);
                    if (!is_string($html)) continue;
                    $converted = thaiDocxVisibleHtml($html);
                    if ($converted !== $html) $zip->addFromString($name, $converted);
                }
            }
        } finally {
            $zip->close();
        }
        $changed = file_get_contents($temporary);
        if (!is_string($changed) || !str_starts_with($changed, "PK\x03\x04")) return $bytes;
        header_remove('Content-Length'); // Modified DOCX byte length differs from the original stream.
        return $changed;
    } catch (Throwable $error) {
        error_log('Thai DOCX digit display conversion failed: ' . $error->getMessage());
        return $bytes;
    } finally {
        @unlink($temporary);
    }
}
if (($_POST['action'] ?? '') === 'word') ob_start('thaiDocxDigitResponse');

// Always load the saved document state anew after a POST/redirect.
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

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
/** A price can have three decimals, or more where an existing TOR price needs it. */
function meetingPriceText($value): string
{
    $value = (float) $value;
    $formatted = number_format($value, 5, '.', ',');
    [$whole, $fraction] = explode('.', $formatted);
    $fraction = rtrim($fraction, '0');
    return thaiDigits($whole . '.' . str_pad($fraction, 3, '0'));
}
function meetingStoredTotal(array $item): float
{
    // TOR persists the line total with the selected unit/packaging convention.
    if (isset($item['offer_total']) && is_numeric($item['offer_total'])) {
        return round((float) $item['offer_total'], 2);
    }
    return round(meetingItemOfferTotal($item), 2);
}
function meetingBahtText(float $value): string
{
    $rounded = number_format(max(0, $value), 2, '.', '');
    [$baht, $satang] = explode('.', $rounded);
    $words = ['ศูนย์','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'];
    $units = ['','สิบ','ร้อย','พัน','หมื่น','แสน'];
    $readSix = static function (string $digits) use ($words, $units): string {
        $digits = ltrim($digits, '0');
        if ($digits === '') return '';
        $out = '';
        $length = strlen($digits);
        for ($i = 0; $i < $length; $i++) {
            $n = (int) $digits[$i];
            $position = $length - $i - 1;
            if ($n === 0) continue;
            if ($position === 1 && $n === 1) $out .= 'สิบ';
            elseif ($position === 1 && $n === 2) $out .= 'ยี่สิบ';
            elseif ($position === 0 && $n === 1 && $length > 1) $out .= 'เอ็ด';
            else $out .= $words[$n] . $units[$position];
        }
        return $out;
    };
    $readNumber = static function (string $digits) use ($readSix): string {
        $digits = ltrim($digits, '0');
        if ($digits === '') return 'ศูนย์';
        $parts = [];
        while ($digits !== '') {
            $parts[] = substr($digits, -6);
            $digits = substr($digits, 0, max(0, strlen($digits) - 6));
        }
        $result = '';
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            $part = $readSix($parts[$i]);
            if ($part === '') continue;
            $result .= $part . str_repeat('ล้าน', $i);
        }
        return $result;
    };
    return $readNumber($baht) . 'บาท' . ((int) $satang === 0 ? 'ถ้วน' : $readNumber($satang) . 'สตางค์');
}
function meetingItemDisplayQuantity(array $item): float
{
    $requested = (float) ($item['requested_quantity'] ?? 0);
    if ($requested > 0) return $requested;
    if (meetingItemHasPackage($item) && (float) ($item['package_count'] ?? 0) > 0) {
        return (float) $item['package_count'] * (float) $item['package_size'];
    }
    return (float) ($item['quantity'] ?? 0);
}
function meetingItemDisplayUnit(array $item): string
{
    return trim((string) ($item['unit'] ?? ''));
}
function meetingItemSpecification(array $item): string
{
    return trim((string) ($item['specification'] ?? ''));
}

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
            'offer_total' => meetingStoredTotal($item),
            'specification' => (string) ($item['specification'] ?? ''),
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

function splitCommittee($text, int $index): array
{
    $defaults = [
        ['name' => '', 'position' => '', 'role' => 'ประธานกรรมการ'],
        ['name' => '', 'position' => '', 'role' => 'กรรมการ'],
        ['name' => '', 'position' => '', 'role' => 'กรรมการ'],
    ];
    if (is_array($text)) {
        return [
            'name' => trim((string) ($text['name'] ?? $text[0] ?? '')),
            'position' => trim((string) ($text['position'] ?? $text[1] ?? '')),
            'role' => trim((string) ($text['role'] ?? $text[2] ?? $defaults[$index]['role'])),
        ];
    }
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $text) ?: []), fn($line) => $line !== ''));
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
$savedResultDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'committee_scope_result_memo') : null;
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
// Do not combine a TOR history linked to a different procurement project.
if ($caseId > 0 && $history && (int) ($history['case_id'] ?? 0) > 0 && (int) $history['case_id'] !== $caseId) {
    $error = 'ประวัติ TOR ที่เลือกไม่ตรงกับโครงการ กรุณาเลือกประวัติของโครงการนี้';
    $history = null;
    $historyId = 0;
}
if ($caseId <= 0 && $history && !empty($history['case_id'])) {
    $caseId = (int) $history['case_id'];
    $activeCase = procurementCase($caseId);
    $savedDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'meeting_report') : null;
    $savedMeetingExtra = is_array($savedDocument['extra'] ?? null) ? $savedDocument['extra'] : [];
    $savedInvitationDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'committee_scope_invitation') : null;
    $savedInvitationExtra = is_array($savedInvitationDocument['extra'] ?? null) ? $savedInvitationDocument['extra'] : [];
    $savedResultDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'committee_scope_result_memo') : null;
    $savedTorDocument = $activeCase ? procurementDocument((int) $activeCase['id'], 'tor_company_purchase') : null;
    $savedTorExtra = is_array($savedTorDocument['extra'] ?? null) ? $savedTorDocument['extra'] : [];
}

$committeeRaw = $history ? (json_decode((string) $history['committee_json'], true) ?: []) : [];
if (!$committeeRaw && $activeCase) {
    $committeeRaw = $activeCase['committee'] ?? [];
}
$items = $history ? (json_decode((string) $history['items_json'], true) ?: []) : [];
$hasSelectedHistory = $historyId > 0 && $history !== null;
$committee = [
    splitCommittee($committeeRaw[0] ?? '', 0),
    splitCommittee($committeeRaw[1] ?? '', 1),
    splitCommittee($committeeRaw[2] ?? '', 2),
];

$defaultPurposeLine = 'เพื่อจัดหาการจัดซื้อยา จำนวน ' . thaiDigits((string) count($items)) . ' รายการ';
$savedPurposeLine = savedValue($savedMeetingExtra, 'purpose_line', $defaultPurposeLine);
if ((int) ($savedMeetingExtra['history_id'] ?? 0) !== $historyId || (count($items) > 0 && str_contains($savedPurposeLine, 'จำนวน ๐ รายการ'))) {
    $savedPurposeLine = $defaultPurposeLine;
}
$meetingTitle = field('meeting_title', savedValue($savedMeetingExtra, 'meeting_title', 'รายงานการประชุมคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและกำหนดราคากลาง'));
$purposeLine = field('purpose_line', $savedPurposeLine);
// ปีงบประมาณใช้ข้อมูลกลางของชุดเอกสาร; ไม่เปลี่ยนปีของวันที่ประชุม
$caseFiscalYear = trim((string) ($activeCase['fiscal_year'] ?? ''));
$fiscalLine = $caseFiscalYear !== ''
    ? 'ประจำปีงบประมาณ พ.ศ. ' . thaiDigits($caseFiscalYear)
    : field('fiscal_line', savedValue($savedMeetingExtra, 'fiscal_line', 'ประจำปีงบประมาณ พ.ศ. ........'));
$meetingDate = field('meeting_date', savedValue($savedMeetingExtra, 'meeting_date', savedValue($savedInvitationExtra, 'meeting_date', '๒๔ เดือน กุมภาพันธ์ พ.ศ. ๒๕๖๘')));
$meetingTime = field('meeting_time', savedValue($savedMeetingExtra, 'meeting_time', savedValue($savedInvitationExtra, 'meeting_time', '๐๙.๓๐ น.')));
$meetingPlace = field('meeting_place', savedValue($savedMeetingExtra, 'meeting_place', 'ห้องประชุมศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล อำเภอสันกำแพง จังหวัดเชียงใหม่'));
$openTime = field('open_time', savedValue($savedMeetingExtra, 'open_time', savedValue($savedInvitationExtra, 'meeting_time', '๐๙.๓๐ น.')));
$closeTime = field('close_time', savedValue($savedMeetingExtra, 'close_time', '๑๑.๐๐ น.'));
$absentText = field('absent_text', savedValue($savedMeetingExtra, 'absent_text', 'ไม่มี'));

if ((int) ($savedMeetingExtra['history_id'] ?? 0) === $historyId && is_array($savedMeetingExtra['committee'] ?? null)) {
    foreach ($savedMeetingExtra['committee'] as $i => $savedPerson) {
        if (isset($committee[$i]) && is_array($savedPerson)) {
            $committee[$i] = array_merge($committee[$i], array_intersect_key($savedPerson, $committee[$i]));
        }
    }
}
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

$lawText = "๑. ระเบียบกระทรวงมหาดไทยว่าด้วยการเบิกค่าใช้จ่ายในการบริหารงานขององค์กรปกครองส่วนท้องถิ่น พ.ศ.๒๕๖๒ หมวดที่ ๒ ค่าใช้สอย ข้อ ๑๑ ค่าใช้จ่ายที่เป็นค่าใช้สอยมีดังต่อไปนี้(๒) ค่าจ้างเอกชนดำเนินงานขององค์กรปกครองส่วนท้องถิ่น\n"
    . "๒. พระราชบัญญัติการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ.๒๕๖๐\n"
    . "๓. ระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. ๒๕๖๐ หมวด ๒ ส่วนที่ ๒ กระบวนการซื้อหรือจ้าง ข้อ ๒๑ ในการซื้อหรือจ้างที่มิใช่การจ้างก่อสร้าง ให้หัวหน้าหน่วยงานของรัฐแต่งตั้งคณะกรรมการขึ้นมาคณะหนึ่งหรือจะให้เจ้าหน้าที่หรือบุคคลใดบุคคลหนึ่งรับผิดชอบในการจัดทำร่างขอบเขตงานหรือรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้างรวมทั้งกำหนดหลักเกณฑ์การพิจารณาคัดเลือกข้อเสนอด้วย\n"
    . "๔. หนังสือกรมส่งเสริมการปกครองส่วนท้องถิ่น ที่ มท ๐๘๐๘.๒/ว ๑๐๙๕ ลงวันที่ ๒๘ พฤษภาคม ๒๕๖๔ เรื่องรูปแบบและการจำแนกประเภทรายรับ-รายจ่ายงบประมาณรายจ่ายประจำปีขององค์กรปกครองส่วนท้องถิ่น\n"
    . "๕. ระเบียบกระทรวงมหาดไทย ว่าด้วยเงินบำรุงโรงพยาบาลและหน่วยบริการสาธารณสุขขององค์กรปกครองส่วนท้องถิ่น (ฉบับที่ ๒) พ.ศ. ๒๕๖๑ (๑) ค่าใช้สอย รายจ่ายเพื่อให้ได้มาซึ่งบริการ\n"
    . "๖. หนังสือกระทรวงมหาดไทย ด่วนที่สุด ที่ มท ๐๘๐๘.๒/ว ๗๓๐๓ ลงวันที่ ๓๐ กันยายน ๒๕๖๕ เรื่อง ซักซ้อมแนวทางการปฏิบัติงานด้านการเงินการคลังของโรงพยาบาลและหน่วยบริการสาธารณสุขขององค์กรปกครองส่วนท้องถิ่น";

$meetingFiscalYear = thaiDigits((string) ($activeCase['fiscal_year'] ?? ''));
$meetingFiscalPhrase = 'พ.ศ. ' . ($meetingFiscalYear !== '' ? $meetingFiscalYear : '................');
$invitationRef = 'ชม ๕๑๐๐๖.๑๓.๑/' . $invitationMemoNo;
$meetingPlaceUnit = 'ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล';
$defaultSpeeches = [
    [
        'agenda' => 'ระเบียบวาระที่ ๑',
        'speaker' => 'ประธานกรรมการ',
        'content' => "ตามที่ได้เชิญคณะกรรมการฯ เข้าร่วมประชุมในครั้งนี้ สืบเนื่องจากการที่ได้รับแต่งตั้งเป็นคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง เพื่อจัดซื้อยาใช้สนับสนุนการให้บริการรักษาประชาชนของ{$meetingPlaceUnit}และเครือข่ายหน่วยบริการปฐมภูมิ จำนวน ๑๐ แห่ง ประจำปีงบประมาณ {$meetingFiscalPhrase} ตามบันทึกข้อความ{$meetingPlaceUnit} ที่{$invitationRef} ลงวันที่ {$invitationDocDate} เรื่อง แต่งตั้งคณะกรรมการร่างขอบเขตและกำหนดราคากลางเพื่อจัดหาการจัดซื้อยา จำนวน {$itemCountText} รายการ ประจำปีงบประมาณ {$meetingFiscalPhrase} โดยให้มีหน้าที่พิจารณากลั่นกรองและกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลางของยาที่จะจัดซื้อให้ตรงตามวัตถุประสงค์และตามระเบียบราชการ ให้เกิดความคุ้มค่ามากที่สุด เพื่อให้การดำเนินงานตามอำนาจหน้าที่ของคณะกรรมการฯ เป็นไปด้วยความเรียบร้อย จึงได้เชิญท่านเข้าร่วมประชุมในครั้งนี้",
    ],
    ['agenda' => 'ระเบียบวาระที่ ๑', 'speaker' => 'มติที่ประชุม', 'content' => 'รับทราบ'],
    ['agenda' => 'ระเบียบวาระที่ ๒', 'speaker' => 'ประธานกรรมการ', 'content' => $lawText],
    [
        'agenda' => 'ระเบียบวาระที่ ๓',
        'speaker' => 'ประธานกรรมการ',
        'content' => "ขอให้คณะกรรมการร่วมกันพิจารณารายละเอียดคุณลักษณะเฉพาะและกำหนดราคากลาง เพื่อจัดหาการจัดซื้อยา จำนวน {$itemCountText} รายการ ดังรายการต่อไปนี้\n{$itemListForSpeech}\nเพื่อเป็นไปตามระเบียบพัสดุ อย่างรอบคอบ เพื่อใช้สนับสนุนการให้บริการรักษาประชาชนของ{$meetingPlaceUnit}และเครือข่ายหน่วยบริการปฐมภูมิประจำปีงบประมาณ {$meetingFiscalPhrase}",
    ],
    ['agenda' => 'ระเบียบวาระที่ ๓', 'speaker' => 'มติที่ประชุม', 'content' => 'เห็นชอบกำหนดรายละเอียดคุณลักษณะเฉพาะ รายละเอียดตามเอกสารแนบท้ายตารางที่ ๑'],
    [
        'agenda' => 'ระเบียบวาระที่ ๓',
        'speaker' => $committee[1]['name'] !== '' ? $committee[1]['name'] : 'กรรมการ',
        'content' => 'จากการพิจารณาขอบเขตงานและราคากลางที่อ้างอิงราคากลางตามพระราชบัญญัติจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ.๒๕๖๐ มาตรา ๔(๒) ราคาที่ได้มาจากฐานข้อมูลราคาอ้างอิงของพัสดุที่กรมบัญชีกลางจัดทำ โดยอ้างอิงเกณฑ์ราคา และกฎกระทรวงกำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุที่รัฐต้องการส่งเสริมหรือสนับสนุน พ.ศ.๒๕๖๓ หมวด ๕ พัสดุส่งเสริมสุขภาพและสาธารณสุข ข้อ ๑๗ ให้หน่วยงานของรัฐจัดซื้อพัสดุตามข้อ ๑๕ (๒) โดยวิธีเฉพาะเจาะจงจากองค์การเภสัชกรรมหรือสภากาชาดไทย เว้นแต่หน่วยงานในสังกัดกระทรวงกลาโหมให้จัดซื้อจากโรงงานเภสัชกรรมทหาร สำหรับหน่วยงานในสังกัดสำนักงานตำรวจแห่งชาติจะจัดซื้อจากองค์การเภสัชกรรมหรือโรงงานเภสัชกรรมทหารก็ได้ ทั้งนี้ หน่วยงานของรัฐสามารถจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยาจากผู้ขายรายอื่นได้หากองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหารไม่สามารถผลิตออกจำหน่ายให้แก่หน่วยงานของรัฐได้ทันกำหนดตามที่หน่วยงานของรัฐได้แจ้งแผนประจำปีล่วงหน้า',
    ],
    [
        'agenda' => 'ระเบียบวาระที่ ๓',
        'speaker' => 'มติที่ประชุม',
        'content' => "เห็นชอบกำหนดราคากลาง โดยใช้ราคากลางจากคณะกรรมการพัฒนาระบบยาแห่งชาติ เรื่องกำหนดราคากลางยาลงวันที่ ๒๕ สิงหาคม ๒๕๖๘ (ฉบับลงราชกิจจานุเบกษา) ได้กำหนดไว้เพื่อใช้ในการปฏิบัติงานของสาธารณสุขและการแพทย์ตำบลทรายมูล ประจำปีงบประมาณ {$meetingFiscalPhrase} รายละเอียดตามเอกสารแนบท้าย ตารางที่ ๒",
    ],
    ['agenda' => 'ระเบียบวาระที่ ๔', 'speaker' => '', 'content' => 'ไม่มี'],
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
// Track whether the user has adopted the new wording for this specific TOR history.
// A preview never changes stored data; the marker is saved only with save_document.
$templateRevision = 'meeting-template-2026-11-v2';
$isTemplatePreview = (($_GET['use_template'] ?? '') === '1');
$hasSameSavedHistory = (int) ($savedMeetingExtra['history_id'] ?? 0) === $historyId;
$savedTemplateRevision = $hasSameSavedHistory ? (string) ($savedMeetingExtra['template_revision'] ?? '') : '';
$postedTemplateRevision = (string) ($_POST['template_revision'] ?? '');
$activeTemplateRevision = $isTemplatePreview ? $templateRevision : ($postedTemplateRevision !== '' ? $postedTemplateRevision : $savedTemplateRevision);
$hasAdoptedTemplate = $hasSameSavedHistory && $savedTemplateRevision === $templateRevision;
$postedSpeakers = $_POST['speech_speaker'] ?? null;
$savedSpeeches = is_array($savedMeetingExtra['speeches'] ?? null) ? $savedMeetingExtra['speeches'] : [];
if (!$hasSameSavedHistory || $isTemplatePreview) {
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
// Older documents may have saved the new wording before revision tracking existed.
// Compare the actual speaking roles and content, not incidental 'topic' keys.
$sameAsCurrentTemplate = count($savedSpeeches) === count($defaultSpeeches);
if ($sameAsCurrentTemplate) {
    foreach ($defaultSpeeches as $i => $templateSpeech) {
        $oldSpeech = $savedSpeeches[$i] ?? [];
        if (!is_array($oldSpeech)) { $sameAsCurrentTemplate = false; break; }
        foreach (['agenda', 'speaker', 'content'] as $key) {
            if (trim((string) ($oldSpeech[$key] ?? '')) !== trim((string) ($templateSpeech[$key] ?? ''))) {
                $sameAsCurrentTemplate = false;
                break 2;
            }
        }
    }
}
$showNewTemplateLink = $hasSelectedHistory && !$isTemplatePreview && !$hasAdoptedTemplate && !$sameAsCurrentTemplate && (bool) $savedSpeeches;
$agendaTitles = [
    'ระเบียบวาระที่ ๑' => 'เรื่อง ประธานแจ้งให้ที่ประชุมทราบ',
    'ระเบียบวาระที่ ๒' => 'เรื่อง ข้อกฎหมาย ระเบียบ และหนังสือสั่งการที่เกี่ยวข้อง',
    'ระเบียบวาระที่ ๓' => 'พิจารณากำหนดรายละเอียดคุณลักษณะเฉพาะและกำหนดราคากลางเพื่อจัดหา',
    'ระเบียบวาระที่ ๔' => '',
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

$offerTotal = array_sum(array_map('meetingStoredTotal', $items));
$meetingProjectName = 'จัดซื้อยาที่องค์การเภสัชกรรมมิได้ผลิตจำหน่าย จำนวน ' . $itemCountText . ' รายการ';
// Annexes 1 and 2 start with the number/date saved in step 4.
// If a user has explicitly changed either field in this document, keep that override.
// Earlier documents without override flags preserve a differing saved value.
$resultMemoNo = trim((string) ($savedResultDocument['memo_no_suffix'] ?? ''));
$resultDocDate = trim((string) ($savedResultDocument['doc_date'] ?? ''));
$savedReportMemoNo = savedValue($savedMeetingExtra, 'report_memo_no');
$savedReportDocDate = savedValue($savedMeetingExtra, 'report_doc_date');
$memoNoWasOverridden = array_key_exists('report_memo_no_override', $savedMeetingExtra)
    ? (bool) $savedMeetingExtra['report_memo_no_override']
    : ($savedReportMemoNo !== '' && $resultMemoNo !== '' && thaiDigits($savedReportMemoNo) !== thaiDigits($resultMemoNo));
$docDateWasOverridden = array_key_exists('report_doc_date_override', $savedMeetingExtra)
    ? (bool) $savedMeetingExtra['report_doc_date_override']
    : ($savedReportDocDate !== '' && $resultDocDate !== '' && thaiDigits($savedReportDocDate) !== thaiDigits($resultDocDate));
$reportMemoNo = field('report_memo_no', $memoNoWasOverridden ? $savedReportMemoNo : ($resultMemoNo !== '' ? $resultMemoNo : $savedReportMemoNo));
$reportDocDate = field('report_doc_date', $docDateWasOverridden ? $savedReportDocDate : ($resultDocDate !== '' ? $resultDocDate : $savedReportDocDate));
$reportMemoNoOverride = $reportMemoNo !== thaiDigits($resultMemoNo);
$reportDocDateOverride = $reportDocDate !== thaiDigits($resultDocDate);
$priceReferenceText = 'อ้างอิงราคากลางตามพระราชบัญญัติจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ.๒๕๖๐ มาตรา ๔ (๒) ราคาที่ได้มาจากฐานข้อมูลราคาอ้างอิงของพัสดุที่กรมบัญชีกลางจัดทำ กำหนดราคากลางไว้ในประกาศคณะกรรมการพัฒนาระบบยาแห่งชาติ เรื่องกำหนดราคากลางยาลงวันที่ ๒๕ สิงหาคม ๒๕๖๘ (ฉบับลงราชกิจจานุเบกษา)';
$meetingPriceWarnings = [];
foreach ($committee as $i => $person) {
    if (trim((string) ($person['name'] ?? '')) === '') {
        $meetingPriceWarnings[] = 'ยังไม่มีรายชื่อกรรมการลำดับที่ ' . thaiDigits((string) ($i + 1)) . ' ในข้อมูลที่เลือก';
    }
}
foreach ($items as $i => $item) {
    if (!isset($item['offer_total']) || !is_numeric($item['offer_total'])) continue;
    if (abs((float) $item['offer_total'] - meetingItemOfferTotal($item)) > 0.011) {
        $meetingPriceWarnings[] = 'รายการที่ ' . thaiDigits((string) ($i + 1)) . ' ยอดเงินในประวัติ TOR ไม่ตรงกับผลคูณราคาและจำนวน โปรดตรวจสอบหน่วยบรรจุ';
    }
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
            'report_memo_no' => $reportMemoNo,
            'report_doc_date' => $reportDocDate,
            'report_memo_no_override' => $reportMemoNoOverride,
            'report_doc_date_override' => $reportDocDateOverride,
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'absent_text' => $absentText,
            'committee' => $committee,
            'items' => itemSnapshot($items),
            'references' => $referenceDocuments,
            'speeches' => $speeches,
            'template_revision' => $activeTemplateRevision === $templateRevision ? $templateRevision : '',
            'saved_at' => date('c'),
        ]);
        header('Location: meeting_report.php?case_id=' . $caseId . '&history_id=' . $historyId . '&saved=1#meeting-preview', true, 303);
        exit;
    }
}

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
    'fiscal_line' => ['label' => 'ปีงบประมาณ (จากหัวข้อ 1)', 'value' => $fiscalLine],
    'meeting_title' => ['label' => 'ชื่อรายงาน', 'value' => $meetingTitle],
    'purpose_line' => ['label' => 'บรรทัดวัตถุประสงค์/จำนวนรายการ', 'value' => $purposeLine],
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
            /* The attendance sheet precedes the minutes and is unnumbered.
               Restart the physical page counter so the minutes begin at ๑. */
            counter-reset: page 0;
            @top-center { content: none; }
        }
        @page budget {
            @top-center { content: none; }
        }
        @page annex {
            @top-center { content: none; }
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
        .annex-page { page: annex; font-size: 16pt; }
        /* Consistent 16pt type in annex tables 1 and 2, in preview and print. */
        .annex-page .doc-title,
        .annex-page .doc-title *,
        .annex-page .annex-heading,
        .annex-page .annex-table,
        .annex-page .annex-table th,
        .annex-page .annex-table td,
        .annex-page .annex-table td *,
        .annex-page .spec-detail {
            font-size: 16pt !important;
        }
        .budget-page { page: budget; }
        .annex-table { width:100%; border-collapse:collapse; table-layout:fixed; margin-top:.3cm; }
        .annex-table th,.annex-table td { border:0.5pt solid #000; padding:3px 6px; vertical-align:top; overflow-wrap:anywhere; }
        .annex-table th { font-weight:700; text-align:center; }
        .annex-table thead { display:table-header-group; }
        .annex-table tr { break-inside:avoid; page-break-inside:avoid; }
        .annex-heading { font-weight:700; margin:.4cm 0 .2cm; }
        .spec-detail { white-space:pre-line; overflow-wrap:anywhere; }
        .budget-frame { border:.5pt solid #000; padding:.3cm .25cm; margin-top:.45cm; }
        .budget-line { display:grid; grid-template-columns: 4.2cm minmax(0,1fr); column-gap:.2cm; margin-bottom:.2cm; }
        .budget-committee { margin:.15cm 0 .15cm .4cm; }
        .report-page h3 { font-size:16pt; }

        @media print {
            /* Chrome can print inherited background graphics, including subtle UI gradients.
               Reset only the document print surface; keep screen styling unchanged. */
            html, body, main, main > section, .report-page, .report-page *,
            .report-page *::before, .report-page *::after {
                background: #fff !important;
                background-color: #fff !important;
                background-image: none !important;
                box-shadow: none !important;
                text-shadow: none !important;
            }
            html, body { margin: 0 !important; padding: 0 !important; }
            .no-print { display: none !important; }
            main { display: block !important; max-width: none !important; padding: 0 !important; margin: 0 !important; }
            .report-page { width: auto; min-height: 0; margin: 0; padding: 0; page-break-after: always; }
            .report-page:last-child { page-break-after: auto; }

            /* A single collapsed 1px rule avoids overlapping outer/table-cell
               borders and fractional 0.5pt rasterization differences in Chrome. */
            .report-page .attendance-table,
            .report-page .annex-table,
            .report-page .medicine-table {
                border: 0 !important;
                border-collapse: collapse !important;
                border-spacing: 0 !important;
                outline: 0 !important;
                box-shadow: none !important;
            }
            .report-page .attendance-table th,
            .report-page .attendance-table td,
            .report-page .annex-table th,
            .report-page .annex-table td,
            .report-page .medicine-table th,
            .report-page .medicine-table td {
                border: 1px solid #000 !important;
                outline: 0 !important;
                box-shadow: none !important;
            }
            .report-page .budget-frame { border: 1px solid #000 !important; }
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
        <?php foreach ($meetingPriceWarnings as $priceWarning): ?>
            <div class="mb-2 rounded-md border border-amber-500/40 bg-amber-950/40 p-3 text-sm text-amber-100"><?= h($priceWarning) ?></div>
        <?php endforeach; ?>
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
                <?php if ($showNewTemplateLink): ?>
                <a class="mt-2 inline-block rounded border border-amber-500/50 px-3 py-2 text-xs text-amber-200" href="meeting_report.php?case_id=<?= (int) $caseId ?>&amp;history_id=<?= (int) $historyId ?>&amp;use_template=1">แสดงข้อความต้นแบบใหม่ (ยังไม่ทับข้อมูลที่บันทึก)</a>
                <?php endif; ?>
        </form>

        <form id="meeting_report_form" method="post" class="grid gap-4">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
            <input type="hidden" name="history_id" value="<?= (int) $historyId ?>">
            <input type="hidden" name="template_revision" value="<?= h($activeTemplateRevision) ?>">
            <section class="rounded-md border border-zinc-800">
                <button type="button" class="accordion-toggle flex w-full items-center justify-between gap-3 p-3 text-left text-sm font-medium text-zinc-100" aria-expanded="true">
                    <span>2. หัวเอกสารและปีงบประมาณ</span>
                    <span class="accordion-icon text-zinc-400">ปิด</span>
                </button>
                <div class="accordion-panel border-t border-zinc-800 p-3">
                    <p class="mb-3 text-xs text-zinc-500">ปีงบประมาณดึงจากชุดเอกสารหัวข้อ 1 ส่วนวันที่ประชุมแก้ไขได้แยกต่างหาก</p>
                    <?php foreach ($headerFields as $name => $field): ?>
                        <label class="mb-3 block">
                            <span class="text-sm text-zinc-300"><?= h($field['label']) ?></span>
                            <input name="<?= h($name) ?>" value="<?= h($field['value']) ?>" <?= $name === 'fiscal_line' && $caseFiscalYear !== '' ? 'readonly aria-readonly="true" title="แก้ไขปีงบประมาณที่หัวข้อ 1"' : '' ?> class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rounded-md border border-zinc-800">
                <button type="button" class="accordion-toggle flex w-full items-center justify-between gap-3 p-3 text-left text-sm font-medium text-zinc-100" aria-expanded="true">
                    <span>3. วัน เวลา สถานที่ และหนังสืออ้างอิง</span>
                    <span class="accordion-icon text-zinc-400">ปิด</span>
                </button>
                <div class="accordion-panel border-t border-zinc-800 p-3">
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
                        <span class="text-sm text-zinc-300">เลขที่หนังสือรายงานผล (ใช้ในหัวเอกสารแนบท้าย)</span>
                        <input name="report_memo_no" value="<?= h($reportMemoNo) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 sm:text-sm">
                    </label>
                    <label class="mt-3 block">
                        <span class="text-sm text-zinc-300">วันที่หนังสือรายงานผล (ใช้ในหัวเอกสารแนบท้าย)</span>
                        <input name="report_doc_date" value="<?= h($reportDocDate) ?>" data-thai-date-picker="1" readonly aria-readonly="true" placeholder="กดเพื่อเลือกวันที่" class="mt-1 w-full cursor-pointer rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 sm:text-sm">
                        <span class="mt-1 block text-xs text-zinc-500">ดึงวันที่จากหัวข้อ 4 อัตโนมัติ กดช่องวันที่หรือปุ่มเลือกวันที่เพื่อแก้ไข</span>
                    </label>
                    <label class="mt-3 block">
                        <span class="text-sm text-zinc-300">ผู้ไม่เข้าประชุม</span>
                        <input name="absent_text" value="<?= h($absentText) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                </div>
            </section>

            <section class="rounded-md border border-zinc-800">
                <button type="button" class="accordion-toggle flex w-full items-center justify-between gap-3 p-3 text-left text-sm font-medium text-zinc-100" aria-expanded="false">
                    <span>4. รายชื่อคณะกรรมการ (ตรวจสอบ/แก้ไข)</span>
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

    <section id="meeting-preview" class="order-1 overflow-auto" tabindex="-1">
        <?php if (($_GET['saved'] ?? '') === '1' && $caseId > 0): ?>
        <div class="no-print mb-4 rounded-lg border p-4" style="background:#ffffff !important;color:#17212b !important;border-color:#94a3b8 !important;box-shadow:none !important;" role="status">
            <div class="font-semibold" style="color:#17212b !important;">บันทึกรายงานการประชุมเรียบร้อยแล้ว</div>
            <p class="mt-1 text-sm" style="color:#334155 !important;">นี่คือตัวอย่างรายงานจากข้อมูลที่บันทึกไว้ในโครงการและประวัติ TOR ที่เลือก หากต้องการแก้ไขเพิ่มเติม ใช้ฟอร์มด้านข้างแล้วกดบันทึกอีกครั้ง</p>
        </div>
        <?php endif; ?>
        <article class="report-page attendance-page">
            <?php if ($hasSelectedHistory): ?>
            <div class="doc-title">
                <div>รายงานการประชุม</div>
                <div>คณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและกำหนดราคากลาง</div>
                <div>เพื่อจัดหาการจัดซื้อยา จำนวน <?= h($itemCountText) ?> รายการ</div>
                <div>ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล</div>
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

        <article class="report-page report-main">
            <?php if ($hasSelectedHistory): ?>
            <div class="doc-title">
                <div>รายงานการประชุม<?= h(preg_replace('/^รายงานการประชุม/u', '', $meetingTitle) ?? $meetingTitle) ?></div>
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
                                            <div class="qty"><?= quantityText(meetingItemDisplayQuantity($item)) ?> <?= h(meetingItemDisplayUnit($item)) ?></div>
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
        <article class="report-page budget-page">
            <?php if ($hasSelectedHistory): ?>
            <div style="text-align:right;font-weight:700;">แบบ บก.06</div>
            <div class="doc-title" style="margin-top:.3cm;">
                <div>ตารางแสดงวงเงินงบประมาณที่ได้รับจัดสรรและรายละเอียดค่าใช้จ่าย</div>
                <div>การจัดซื้อจัดจ้างที่มิใช่งานก่อสร้าง</div>
            </div>
            <div class="budget-frame">
                <div class="budget-line"><strong>๑. ชื่อโครงการ</strong><div><?= h($meetingProjectName) ?></div></div>
                <div class="budget-line"><strong>๒. หน่วยงานเจ้าของโครงการ</strong><div>ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล</div></div>
                <div class="budget-line"><strong>๓. วงเงินงบประมาณที่ได้รับการจัดสรร</strong><div><?= money($offerTotal) ?> บาท (<?= h(meetingBahtText($offerTotal)) ?>)</div></div>
                <div class="budget-line"><strong>๔. วันที่กำหนดราคากลาง (ราคาอ้างอิง)</strong><div><?= h($meetingDate) ?><div>เป็นเงิน <?= money($offerTotal) ?> บาท (<?= h(meetingBahtText($offerTotal)) ?>)</div></div></div>
                <div><strong>๕. แหล่งที่มาของราคากลาง (ราคาอ้างอิง) :</strong> <?= h($priceReferenceText) ?></div>
                <div style="margin-top:.2cm;"><strong>๖. เจ้าหน้าที่ผู้กำหนดราคากลาง (ราคาอ้างอิง)</strong></div>
                <?php foreach ($committee as $i => $person): ?>
                    <div class="budget-committee"><?= thaiDigits($i + 1) ?>. <?= h($person['name']) ?>&nbsp;&nbsp;<?= h($person['position']) ?>&nbsp;&nbsp;<?= h($person['role']) ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </article>
        <article class="report-page annex-page">
            <?php if ($hasSelectedHistory): ?>
            <div class="doc-title">
                <div>เอกสารแนบท้ายหนังสือ ที่ ชม ๕๑๐๐๖.๑๓.๑/<span data-meeting-annex="report_memo_no"><?= h($reportMemoNo !== '' ? $reportMemoNo : '.................') ?></span> ลงวันที่ <span data-meeting-annex="report_doc_date"><?= h($reportDocDate !== '' ? $reportDocDate : '........................') ?></span></div>
                <div>เรื่อง รายงานผลการพิจารณากำหนดรายละเอียดคุณลักษณะเฉพาะสำหรับการจัดซื้อยาที่องค์การเภสัชกรรมมิได้ผลิตจำหน่าย</div>
                <div>เพื่อใช้ในการรักษาพยาบาลประชาชนของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูลและเครือข่ายหน่วยบริการปฐมภูมิ</div>
            </div>
            <div class="annex-heading">ตารางที่ ๑ กำหนดรายละเอียดคุณลักษณะเฉพาะสำหรับการจัดซื้อยา <?= h($itemCountText) ?> รายการ</div>
            <table class="annex-table">
                <thead><tr><th style="width:8%">ลำดับ</th><th style="width:25%">รายการ</th><th style="width:17%">จำนวน/หน่วย</th><th>คุณสมบัติ</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td class="center"><?= thaiDigits($i+1) ?></td>
                        <td><?= h((string) ($item['name'] ?? '')) ?></td>
                        <td><?= quantityText(meetingItemDisplayQuantity($item)) ?> <?= h(meetingItemDisplayUnit($item)) ?></td>
                        <td>
                            <?php $itemSpecification = meetingItemSpecification($item); ?>
                            <?php if ($itemSpecification !== ''): ?>
                            <div class="spec-detail"><?= h($itemSpecification) ?></div>
                            <?php else: ?>
                            <?php $isTablet = in_array(trim((string) ($item['unit'] ?? '')), ['เม็ด', 'tablet', 'tablets'], true); ?>
                            ๑.<?= $isTablet ? 'ยาเม็ดชนิดรับประทาน' : 'รูปแบบยาตามรายละเอียดของรายการ' ?><br>
                            ๒.ประกอบด้วยตัวยา <?= h((string) ($item['name'] ?? '')) ?><br>
                            ๓.<?= $isTablet ? 'บรรจุในขวด ภาชนะ ปิดสนิท' : 'ภาชนะบรรจุและรูปแบบตามคุณลักษณะเฉพาะที่บันทึกใน TOR' ?><br>
                            ๔.ฉลาก<br>
                            -ระบุชื่อยา ส่วนประกอบสำคัญและความแรง วันผลิต วันสิ้นอายุ เลขที่ผลิต และเลขทะเบียนตำรับยาไว้ชัดเจนปรากฏบนบรรจุภัณฑ์<br>
                            -กรณีที่บรรจุในขวด หลอด แผง ต้องระบุวัน เดือน ปี ที่ยาหมดอายุ, เลขที่ผลิตไว้อย่างชัดเจน
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </article>
        <article class="report-page annex-page">
            <?php if ($hasSelectedHistory): ?>
            <div class="doc-title">
                <div>เอกสารแนบท้ายหนังสือ ที่ ชม ๕๑๐๐๖.๑๓.๑/<span data-meeting-annex="report_memo_no"><?= h($reportMemoNo !== '' ? $reportMemoNo : '.................') ?></span> ลงวันที่ <span data-meeting-annex="report_doc_date"><?= h($reportDocDate !== '' ? $reportDocDate : '........................') ?></span></div>
                <div>เรื่อง รายงานผลการพิจารณากำหนดรายละเอียดคุณลักษณะเฉพาะสำหรับการจัดซื้อยาที่องค์การเภสัชกรรมมิได้ผลิตจำหน่าย</div>
                <div>เพื่อใช้ในการรักษาพยาบาลประชาชนของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูลและเครือข่ายหน่วยบริการปฐมภูมิ</div>
            </div>
            <div class="annex-heading">ตารางที่ ๒ กำหนดรายละเอียดราคากลางสำหรับการจัดซื้อยา <?= h($itemCountText) ?> รายการ</div>
            <table class="annex-table">
                <thead><tr><th style="width:7%">ลำดับ</th><th style="width:28%">รายการ</th><th style="width:17%">ราคากลาง (บาท)</th><th style="width:12%">หน่วย</th><th style="width:15%">จำนวนจัดซื้อ</th><th style="width:21%">จำนวนงบประมาณที่จัดซื้อ (บาท)</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td class="center"><?= thaiDigits($i + 1) ?>.</td>
                        <td><?= h((string) ($item['name'] ?? '')) ?></td>
                        <td class="right"><?= meetingPriceText($item['offer_price'] ?? 0) ?><?php if (meetingItemHasPackage($item)): ?> (<?= quantityText($item['package_size']) ?> <?= h(meetingItemDisplayUnit($item)) ?>)<?php endif; ?></td>
                        <td class="center"><?= h(meetingItemDisplayUnit($item)) ?></td>
                        <td class="right"><?= quantityText(meetingItemDisplayQuantity($item)) ?></td>
                        <td class="right"><?= money(meetingStoredTotal($item)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="5"><strong>รวมเป็นเงิน</strong> -<?= h(meetingBahtText($offerTotal)) ?>-</td><td class="right"><strong><?= money($offerTotal) ?></strong></td></tr>
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

// Keep both annex headers in sync with the editable number/date fields.
for (const [fieldName, blankText] of [
    ['report_memo_no', '.................'],
    ['report_doc_date', '........................'],
]) {
    const input = document.querySelector(`[name="${fieldName}"]`);
    if (!input) continue;
    const updateAnnexHeaders = () => {
        const value = thaiDigits(input.value.trim()) || blankText;
        document.querySelectorAll(`[data-meeting-annex="${fieldName}"]`).forEach((node) => {
            node.textContent = value;
        });
    };
    input.addEventListener('input', updateAnnexHeaders);
    input.addEventListener('change', updateAnnexHeaders);
    updateAnnexHeaders();
}

// Use the same native calendar popup as the other document pages; no manual date typing.
const meetingThaiMonthNames = [
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม',
];
const meetingThaiMonthShortNames = [
    'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
    'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.',
];
function parseMeetingThaiDate(value) {
    const normalized = String(value).replace(/[๐-๙]/g, (digit) => String('๐๑๒๓๔๕๖๗๘๙'.indexOf(digit)))
        .replace(/^วันที่\s*/, '').trim().replace(/\s+/g, ' ');
    const matched = normalized.match(/^(\d{1,2})\s+(?:เดือน\s+)?([^\s]+)\s+(?:พ\.\s*ศ\.\s*)?(\d{4})$/);
    if (!matched) return '';
    let monthIndex = meetingThaiMonthNames.indexOf(matched[2]);
    if (monthIndex < 0) monthIndex = meetingThaiMonthShortNames.indexOf(matched[2]);
    let year = Number(matched[3]);
    if (year >= 2400) year -= 543;
    const day = Number(matched[1]);
    if (monthIndex < 0 || year < 1900 || day < 1 || day > 31) return '';
    const date = new Date(Date.UTC(year, monthIndex, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== monthIndex || date.getUTCDate() !== day) return '';
    return `${String(year).padStart(4, '0')}-${String(monthIndex + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}
function formatMeetingThaiDate(value) {
    const matched = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!matched) return '';
    const year = Number(matched[1]);
    const month = Number(matched[2]);
    const day = Number(matched[3]);
    if (!year || month < 1 || month > 12 || day < 1 || day > 31) return '';
    return thaiDigits(`${day} ${meetingThaiMonthNames[month - 1]} ${year + 543}`);
}
function setupMeetingThaiDatePicker(input) {
    if (input.dataset.datePickerBound === '1') return;
    input.dataset.datePickerBound = '1';
    const wrapper = document.createElement('div');
    wrapper.className = 'relative mt-1 grid grid-cols-[minmax(0,1fr)_auto] gap-2';
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    input.classList.remove('mt-1');

    const picker = document.createElement('input');
    picker.type = 'date';
    picker.value = parseMeetingThaiDate(input.value);
    picker.className = 'absolute h-px w-px opacity-0 pointer-events-none';
    picker.tabIndex = -1;
    picker.setAttribute('aria-label', 'ปฏิทินวันที่หนังสือรายงานผล');
    wrapper.appendChild(picker);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:border-emerald-400';
    button.textContent = 'เลือกวันที่';
    wrapper.appendChild(button);

    const openPicker = () => {
        picker.value = parseMeetingThaiDate(input.value);
        try {
            if (typeof picker.showPicker === 'function') picker.showPicker();
            else { picker.focus(); picker.click(); }
        } catch (error) {
            picker.focus();
            picker.click();
        }
    };
    picker.addEventListener('change', () => {
        input.value = formatMeetingThaiDate(picker.value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    button.addEventListener('click', openPicker);
    input.addEventListener('click', openPicker);
    input.addEventListener('focus', openPicker);
}
document.querySelectorAll('[data-thai-date-picker]').forEach(setupMeetingThaiDatePicker);

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

</body>
</html>
