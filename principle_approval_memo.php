<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require __DIR__ . '/core/word_export_lib.php';
require __DIR__ . '/core/auth.php';
require __DIR__ . '/core/document_signer_defaults.php';
requireLogin();

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function thaiDigits($value): string
{
    return strtr((string) $value, [
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
function arabicDigits($value): string
{
    return strtr((string) $value, [
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
ensureProcurementTables();

function ensureCommitteeSetsTable(): void
{
    requireDatabaseTables(['committee_sets']);
}

function rawField(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $_GET[$key] ?? $default)); }
function field(string $key, string $default = ''): string { return thaiDigits(rawField($key, $default)); }
function normalizeBudgetNumber(string $value): ?string
{
    $value = str_replace([',', ' ', "\xc2\xa0"], '', arabicDigits(trim($value)));
    if (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $value)) return null;
    [$integer, $decimal] = array_pad(explode('.', $value, 2), 2, '');
    $integer = ltrim($integer, '0');
    return ($integer === '' ? '0' : $integer) . ($decimal === '' ? '' : '.' . str_pad($decimal, 2, '0'));
}
function thaiNumberText(string $integer, bool $useEtForFinalOne = false): string
{
    $integer = ltrim($integer, '0');
    if ($integer === '') return 'ศูนย์';
    if (strlen($integer) > 6) {
        $head = substr($integer, 0, -6);
        $tail = substr($integer, -6);
        return thaiNumberText($head) . 'ล้าน' . (trim($tail, '0') === '' ? '' : thaiNumberText($tail, true));
    }
    $digits = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    $units = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];
    $result = '';
    $length = strlen($integer);
    for ($i = 0; $i < $length; $i++) {
        $digit = (int) $integer[$i];
        if ($digit === 0) continue;
        $position = $length - $i - 1;
        if ($position === 1 && $digit === 1) {
            $result .= 'สิบ';
        } elseif ($position === 1 && $digit === 2) {
            $result .= 'ยี่สิบ';
        } elseif ($position === 0 && $digit === 1 && ($length > 1 || $useEtForFinalOne)) {
            $result .= 'เอ็ด';
        } else {
            $result .= $digits[$digit] . $units[$position];
        }
    }
    return $result;
}
function thaiBahtText(string $number): string
{
    [$integer, $decimal] = array_pad(explode('.', $number, 2), 2, '');
    $decimal = str_pad(substr($decimal, 0, 2), 2, '0');
    $text = thaiNumberText($integer) . 'บาท';
    return $decimal === '00' ? $text . 'ถ้วน' : $text . thaiNumberText($decimal) . 'สตางค์';
}
function quantityText(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    $arabic = strtr($value, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]);
    $arabic = str_replace(',', '', $arabic);
    if (!preg_match('/^-?\d+(?:\.\d+)?$/', $arabic)) {
        return thaiDigits($value);
    }
    $sign = '';
    if (str_starts_with($arabic, '-')) {
        $sign = '-';
        $arabic = substr($arabic, 1);
    }
    $parts = explode('.', $arabic, 2);
    $integer = $parts[0];
    $decimal = $parts[1] ?? '';
    $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $integer);
    $formatted = $sign . $integer . ($decimal !== '' ? '.' . $decimal : '');
    return thaiDigits($formatted);
}

function principleApprovalMemoWordHtml(array $data): string
{
    ob_start();
    ?>
    <!doctype html>
    <html lang="th">
    <head>
        <meta charset="utf-8">
        <title>บันทึกข้อความ ขออนุมัติในหลักการ</title>
        <style>
            @page { size: A4; margin: 25mm 20mm 20mm 30mm; }
            body {
                margin: 0;
                color: #111;
                font-family: "TH Sarabun New", "Sarabun", sans-serif;
                font-size: 16pt;
                line-height: 1;
            }
            .memo-page { width: 160mm; min-height: 252mm; }
            table {
                width: 100%;
                border-collapse: collapse;
                mso-table-lspace: 0pt;
                mso-table-rspace: 0pt;
                border: 0;
            }
            td {
                border: 0;
                padding: 0;
                vertical-align: top;
                font-size: 16pt;
                line-height: 1;
            }
            p { margin: 0; mso-margin-top-alt: 0pt; mso-margin-bottom-alt: 0pt; text-align: justify; text-justify: inter-character; -ms-text-justify: distribute; text-align-last: left; text-indent: 70pt; line-height: 1; mso-line-height-rule: exactly; }
            .header-table { margin: 0 0 3pt; border: 0; }
            .header-table td { border: 0; line-height: 1; vertical-align: top; }
            .garuda { width: auto; height: 1.5cm; }
            .memo-title { text-align: center; font-size: 29pt; font-weight: 700; line-height: 1; padding-top: 8pt; }
            .meta { margin-top: 3pt; }
            .meta td { line-height: 1; padding-top: 2pt; }
            .meta-line { margin-top: 3pt; line-height: 1; text-align: justify; text-justify: inter-character; -ms-text-justify: distribute; text-align-last: left; }
            .memo-no-date { margin-top: 3pt; line-height: 1; white-space: nowrap; }
            .label { width: 70pt; white-space: nowrap; font-size: 20pt; font-weight: 700; line-height: 1; }
            .inline-label { display: inline; white-space: nowrap; font-size: 20pt; font-weight: 700; line-height: 1; margin-right: 8pt; }
            .inline-label-small { display: inline; white-space: nowrap; font-size: 16pt; font-weight: 700; line-height: 1; margin-right: 8pt; }
            .memo-no-text { display: inline-block; width: 220pt; }
            .date-label-text { display: inline-block; width: 45pt; font-size: 20pt; font-weight: 700; line-height: 1; padding-left: 8pt; }
            .date-value-text { display: inline-block; width: 142pt; text-align: center; }
            .section-title { margin: 7pt 0 2pt 70pt; font-weight: 700; line-height: 1; }
            .medicine-table { width: 382pt; margin-left: 70pt; margin-top: 1pt; }
            .medicine-table,
            .numbered,
            .committee-table {
                border: 0;
            }
            .medicine-table td,
            .numbered td,
            .committee-table td {
                border: 0;
                mso-border-alt: none;
            }
            .medicine-table td { padding: 0 3pt 0 3pt; }
            .numbered { margin-top: 2pt; }
            .numbered .num { width: 88pt; padding-left: 70pt; padding-right: 4pt; }
            .numbered .text { padding-left: 6pt; text-align: justify; text-justify: inter-character; -ms-text-justify: distribute; text-align-last: left; }
            .legal-item,
            .consideration-item {
                margin: 0;
                mso-margin-top-alt: 0pt;
                mso-margin-bottom-alt: 0pt;
                text-align: justify;
                text-justify: inter-character;
                -ms-text-justify: distribute;
                text-align-last: left;
                text-indent: 70pt;
                line-height: 1;
                page-break-inside: auto;
                break-inside: auto;
            }
            .committee-table { width: 382pt; margin-left: 70pt; margin-top: 3pt; }
            .committee-table td { padding: 0 3pt 0 3pt; }
            .signature { margin-top: 32pt; text-align: center; line-height: 1; }
            .approval { margin-top: 22pt; }
            .approval-note { width: 90pt; }
            .approval-signature { width: 300pt; text-align: center; white-space: nowrap; }
            .red { color: #c00000; }
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

            <div class="meta-line"><span class="inline-label">ส่วนราชการ</span><?= h($data['department']) ?></div>
            <div class="memo-no-date">
                <span class="inline-label">ที่</span><span class="memo-no-text">ชม.๕๑๐๐๖.๑๓.๑/<?= h($data['memo_no_suffix']) ?></span>
                <span class="date-label-text">วันที่</span><span class="date-value-text"><?= h($data['doc_date']) ?></span>
            </div>
            <div class="meta-line"><span class="inline-label">เรื่อง</span>ขออนุมัติในหลักการเพื่อดำเนินการจัดซื้อยา จำนวน <?= h($data['item_count']) ?> รายการ</div>
            <div class="meta-line"><span class="inline-label-small">เรียน</span>นายกองค์การบริหารส่วนจังหวัดเชียงใหม่</div>

            <div class="section-title">ต้นเรื่อง</div>
            <p>ตาม โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ในสังกัดองค์การบริหารส่วนจังหวัดเชียงใหม่ประกาศแผนการใช้จ่ายเงินประจำปีงบประมาณ พ.ศ. <?= h($data['fiscal_year']) ?><?= trim((string) ($data['plan_revision_note'] ?? '')) !== '' ? ' (' . h($data['plan_revision_note']) . ')' : '' ?> อนุมัติให้ใช้เมื่อวันที่ <?= h($data['plan_approved_date']) ?> หมวดยาและเวชภัณฑ์มิใช่ยา หมวด 1 ยา ที่ตั้งไว้ <?= h($data['budget_amount']) ?> บาท (<?= h($data['budget_amount_text']) ?>) ตามหลักการจำแนกประเภทรายจ่ายตามงบประมาณขององค์กรปกครองส่วนท้องถิ่นเป็นไปตามระเบียบกระทรวงมหาดไทย ด้วยการเบิกค่าใช้จ่ายในการบริหารงานขององค์กรปกครองส่วนท้องถิ่น พ.ศ. ๒๕๖๒</p>

            <div class="section-title">ข้อเท็จจริง</div>
            <p>หน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง มีความประสงค์จัดซื้อยา เพื่อใช้ในการรักษาพยาบาลแก่ประชาชนในเขตพื้นที่รับผิดชอบ หน่วยบริการประจำ (CUP) โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้งและหน่วยบริการปฐมภูมิในเครือข่ายจำนวน <?= h($data['network_count']) ?> แห่ง อำเภอสันกำแพง ตามแผนงบประมาณเงินบำรุงประเภทยาและเวชภัณฑ์ที่มิใช่ยา ปีพ.ศ.<?= h($data['fiscal_year']) ?> โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง เนื่องจากมีปริมาณยาคงคลังไม่เพียงพอต่อการให้บริการประชาชนและเพื่อความต่อเนื่องในการปฏิบัติงานของเจ้าหน้าที่ จำนวน <?= h($data['item_count']) ?> รายการ ได้แก่</p>
            <table class="medicine-table">
                <?php foreach ($data['items'] as $i => $item): ?>
                    <tr>
                        <td style="width:24pt;"><?= thaiDigits((string) ($i + 1)) ?>.</td>
                        <td><?= h((string) $item['name']) ?></td>
                        <td style="width:62pt;text-align:right;"><?= h(quantityText((string) $item['quantity'])) ?></td>
                        <td style="width:62pt;"><?= h((string) $item['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <div class="section-title">ข้อกฎหมาย</div>
            <div class="legal-item">๑.ตามพระราชบัญญัติการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. ๒๕๖๐ มาตรา ๔  ในพระราชบัญญัตินี้ “ราคากลาง” หมายความว่า ราคาเพื่อใช้เป็นฐานสำหรับเปรียบเทียบราคาที่ผู้ยื่นข้อเสนอได้ยื่นเสนอไว้ซึ่งสามารถจัดซื้อจัดจ้างได้จริงตามลำดับ ดังต่อไปนี้ (๑) ราคาที่ได้มาจากการคำนวณตามหลักเกณฑ์ที่คณะกรรมการราคากลางกำหนด (๒) ราคาที่ได้มาจากฐานข้อมูลราคาอ้างอิงของพัสดุที่กรมบัญชีกลางจัดทำ (๓) ราคามาตรฐานที่สำนักงบประมาณหรือหน่วยงานกลางอื่นกำหนด (๔) ราคาที่ได้มาจากการสืบราคาจากท้องตลาด (๕) ราคาที่เคยซื้อหรือจ้างครั้งหลังสุดภายในระยะเวลาสองปีงบประมาณ (๖) ราคาอื่นใดตามหลักเกณฑ์ วิธีการ หรือแนวทางปฏิบัติของหน่วยงานของรัฐนั้น ๆในกรณีที่มีราคาตาม (๑) ให้ใช้ราคาตาม (๑) ก่อน ในกรณีที่ไม่มีราคาตาม (๑) แต่มีราคาตาม (๒)หรือ (๓) ให้ใช้ราคาตาม (๒) หรือ (๓) ก่อน โดยจะใช้ราคาใดตาม (๒) หรือ (๓) ให้คำนึงถึงประโยชน์ของหน่วยงานของรัฐเป็นสำคัญ ในกรณีที่ไม่มีราคาตาม (๑) (๒) และ (๓) ให้ใช้ราคาตาม (๔) (๕) หรือ (๖)โดยจะใช้ราคาใดตาม (๔) (๕) หรือ (๖) ให้คำนึงถึงประโยชน์ของหน่วยงานของรัฐเป็นสำคัญ</div>
            <div class="legal-item">๒. ข้อ ๒๑ ในการซื้อหรือจ้างที่มิใช่การจ้างก่อสร้าง ให้หัวหน้าหน่วยงานของรัฐแต่งตั้ง คณะกรรมการขึ้นมาคณะหนึ่ง หรือจะให้เจ้าหน้าที่หรือบุคคลใดบุคคลหนึ่งรับผิดชอบในการจัดทำร่างขอบเขตของงาน หรือรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง รวมทั้งกำหนดหลักเกณฑ์การพิจารณาคัดเลือกข้อเสนอด้วย เพื่อให้การกำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้างมีมาตรฐานและเป็นประโยชน์ต่อทางราชการ หากพัสดุที่จะซื้อหรือจ้างใดมีประกาศกำหนดมาตรฐานผลิตภัณฑ์อุตสาหกรรมแล้วให้กำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง หรือรายการในการก่อสร้างตามมาตรฐานผลิตภัณฑ์อุตสาหกรรม หรือเพื่อความสะดวกจะระบุเฉพาะหมายเลขมาตรฐานก็ได้หรือในกรณีพัสดุ ที่จะซื้อหรือจ้างใดยังไม่มีประกาศกำหนดมาตรฐานผลิตภัณฑ์อุตสาหกรรมแต่มีผู้ได้รับการจดทะเบียนผลิตภัณฑ์ไว้กับกระทรวงอุตสาหกรรมแล้ว ให้กำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้างหรือรายการในการก่อสร้างให้สอดคล้องกับรายละเอียดคุณลักษณะเฉพาะตามที่ระบุในคู่มือผู้ซื้อหรือใบแทรกคู่มือผู้ซื้อ ที่กระทรวงอุตสาหกรรมจัดทำขึ้น</div>
            <div class="legal-item">๓. ตามหนังสือ คณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ ด่วนที่สุด ที่ กค(กวจ) ๐๔๐๕.๒/ว78 ลงวันที่ 31 มกราคม ๒๕๖5 เรื่อง อนุมัติยกเว้นและกำหนดแนวทางแนวทางปฏิบัติตามกฎกระทรวง กำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุ ที่รัฐต้องการส่งเสริมหรือสนับสนุน (ฉบับที่ ๒) พ.ศ.๒๕๖๓</div>
            <div class="legal-item">๔. ตามหนังสือ คณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ด่วนที่สุด ที่ กค(กวจ) ๐๔๐๕.๒/ว๕๖ ลงวันที่ ๒๔ มกราคม ๒๕๖๖ เรื่อง แนวทางปฏิบัติสำหรับการส่งเสริมหรือสนับสนุนผู้ประกอบการวิสาหกิจขนาดกลางและขนาดย่อม (SMEs) เพิ่มเติม</div>

            <div class="section-title">ข้อพิจารณา</div>
            <p>เพื่อให้เป็นไปด้วยความเรียบร้อย ถูกต้องตามระเบียบกฎหมาย จึงขออนุมัติดำเนินการดังต่อไปนี้</p>
            <div class="consideration-item">๑.อนุมัติให้ดำเนินการจัดซื้อยา จำนวน <?= h($data['item_count']) ?> รายการ</div>
            <div class="consideration-item">๒.ขออนุมัติแต่งตั้งผู้กำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง การจัดซื้อยา โดยให้ใช้หนังสือบันทึกข้อความฉบับนี้แทนคำสั่งแต่งตั้งคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลางโดยอนุโลม ดังรายชื่อต่อไปนี้</div>
            <table class="committee-table">
                <?php foreach ($data['committee'] as $i => $person): ?>
                    <tr>
                        <td style="width:18pt;"><?= thaiDigits((string) ($i + 1)) ?>.</td>
                        <td style="width:133pt;"><?= h((string) $person['name']) ?></td>
                        <td style="width:116pt;"><?= h((string) $person['position']) ?></td>
                        <td style="width:91pt;"><?= h((string) $person['role']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <p style="margin-top:10pt;">จึงเรียนมาเพื่อโปรดพิจารณา หากเห็นชอบโปรดอนุมัติในหลักการเพื่อจะได้ดำเนินการตามระเบียบพัสดุฯ ต่อไป</p>
            <div class="signature">
                <div>(<?= h($data['signer_name']) ?>)</div>
                <div><?= h($data['signer_position']) ?></div>
            </div>
            <table class="approval">
                <tr>
                    <td class="approval-note">-เห็นชอบ<br>-อนุมัติ</td>
                    <td class="approval-signature"><br><br>(<?= h($data['director_name']) ?>)<br><?= nl2br(h($data['director_position'])) ?></td>
                    <td>&nbsp;</td>
                </tr>
            </table>
        </div>
    </body>
    </html>
    <?php
    return (string) ob_get_clean();
}

function principleApprovalMemoWordFilename(string $memoNoSuffix, string $docDate, string $itemCount): string
{
    return trim(
        'ชุดขออนุมัติในหลักการ 1 เลขที่หนัง '
        . trim(arabicDigits($memoNoSuffix))
        . ' วันที่ '
        . principleFilenameDate($docDate)
        . ' ยา '
        . trim(arabicDigits($itemCount))
        . ' รายการ.docx'
    );
}

function principleFilenameDate(string $docDate): string
{
    $date = trim(arabicDigits($docDate));
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

    foreach ($months as $monthName => $monthNo) {
        if (preg_match('/(\d{1,2})\s+' . preg_quote($monthName, '/') . '\s+(\d{4})/u', $date, $match)) {
            return str_pad($match[1], 2, '0', STR_PAD_LEFT) . '-' . $monthNo . '-' . $match[2];
        }
    }

    return str_replace(['/', '\\'], '-', $date);
}

$medicines = [];
$loadError = '';
try {
    $medicines = db()->query('SELECT id, name, unit FROM medicines ORDER BY name')->fetchAll();
} catch (Throwable $e) {
    $loadError = 'ยังอ่านรายการยาจากฐานข้อมูลไม่ได้ กรุณาตรวจสอบ config.php และฐานข้อมูล tor_report';
}

$fallbackMedicine = [
    'id' => 0,
    'name' => '',
    'unit' => 'แอมป์',
];
$defaultMedicine = $medicines[0] ?? $fallbackMedicine;
$fallbackMedicine['unit'] = '';
$medicineMap = [];
foreach ($medicines as $medicine) {
    $medicineMap[(string) $medicine['id']] = $medicine;
}

$caseId = (int) rawField('case_id', '0');
$activeCase = procurementCase($caseId);
$caseOptions = procurementCases();
ensureCommitteeSetsTable();

if (in_array(($_POST['action'] ?? ''), ['save_committee_set', 'save_committee_set_ajax'], true)) {
    $setId = (int) rawField('committee_set_id', '0');
    $title = rawField('committee_set_title', '');
    $values = [
        $title,
        rawField('committee_set_chair_name', ''),
        rawField('committee_set_chair_position', ''),
        rawField('committee_set_member1_name', ''),
        rawField('committee_set_member1_position', ''),
        rawField('committee_set_member2_name', ''),
        rawField('committee_set_member2_position', ''),
    ];
    $savedItem = null;
    $saveCommitteeError = '';

    if ($title === '') {
        $saveCommitteeError = 'กรุณากรอกชื่อชุดกรรมการ';
    } else {
        try {
            if ($setId > 0) {
                $stmt = db()->prepare("
                    UPDATE committee_sets SET
                        title = ?, chair_name = ?, chair_position = ?,
                        member1_name = ?, member1_position = ?,
                        member2_name = ?, member2_position = ?
                    WHERE id = ?
                ");
                $stmt->execute([...$values, $setId]);
                if ($stmt->rowCount() === 0) {
                    $exists = db()->prepare('SELECT id FROM committee_sets WHERE id = ? LIMIT 1');
                    $exists->execute([$setId]);
                    if (!$exists->fetchColumn()) {
                        $saveCommitteeError = 'ไม่พบชุดกรรมการที่ต้องการแก้ไข';
                    }
                }
            } else {
                $stmt = db()->prepare("
                    INSERT INTO committee_sets
                        (title, chair_name, chair_position, member1_name, member1_position, member2_name, member2_position)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        chair_name = VALUES(chair_name),
                        chair_position = VALUES(chair_position),
                        member1_name = VALUES(member1_name),
                        member1_position = VALUES(member1_position),
                        member2_name = VALUES(member2_name),
                        member2_position = VALUES(member2_position),
                        id = LAST_INSERT_ID(id)
                ");
                $stmt->execute($values);
                $setId = (int) db()->lastInsertId();
            }
        } catch (PDOException $e) {
            $saveCommitteeError = (string) $e->getCode() === '23000'
                ? 'ชื่อชุดกรรมการนี้ถูกใช้งานแล้ว กรุณาใช้ชื่ออื่น'
                : 'บันทึกชุดกรรมการไม่สำเร็จ กรุณาลองใหม่';
        }

        if ($saveCommitteeError === '') {
            $stmt = db()->prepare('SELECT * FROM committee_sets WHERE id = ? LIMIT 1');
            $stmt->execute([$setId]);
            $savedItem = $stmt->fetch() ?: null;
        }
    }
    if (($_POST['action'] ?? '') === 'save_committee_set_ajax') {
        header('Content-Type: application/json; charset=utf-8');
        if ($saveCommitteeError !== '') http_response_code(422);
        echo json_encode([
            'ok' => $saveCommitteeError === '' && $savedItem !== null,
            'item' => $savedItem,
            'message' => $saveCommitteeError,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: principle_approval_memo.php' . ($caseId > 0 ? '?case_id=' . $caseId : ''));
    exit;
}

$committeeSets = db()->query('SELECT * FROM committee_sets ORDER BY title')->fetchAll();

$department = 'กองสาธารณสุข หน่วยบริการประจำ รพ.สต.บ้านสันโค้ง อ.สันกำแพง โทร. 05333 1362';
$memoNoSuffix = field('memo_no_suffix', '');
$docDate = field('doc_date', '');
// ปีงบประมาณเป็นข้อมูลของชุดเอกสาร (พ.ศ.) แยกจากปีของวันที่เอกสาร
$thaiToday = new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok'));
$currentFiscalYear = (int) $thaiToday->format('Y') + 543 + ((int) $thaiToday->format('n') >= 10 ? 1 : 0);
$fiscalYear = thaiDigits(field('fiscal_year', (string) $currentFiscalYear));
$planApprovedDate = field('plan_approved_date', '17 กรกฏาคม 2569');
$planRevisionNote = field('plan_revision_note', 'ปรับปรุงครั้งที่ 3');
$budgetAmountInput = rawField('budget_amount', '3000000');
$networkCount = field('network_count', '');
$principleSignerDefaults = documentSignerDefaults('principle_approval');
$signerName = field('signer_name', (string) $principleSignerDefaults['proposer_name']);
$signerPosition = field('signer_position', (string) $principleSignerDefaults['proposer_position']);
$directorName = field('director_name', (string) $principleSignerDefaults['approver_name']);
$directorPosition = field('director_position', (string) $principleSignerDefaults['approver_position']);
if ($activeCase && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $fiscalYear = thaiDigits((string) ($activeCase['fiscal_year'] ?? ''));
    $memoNoSuffix = thaiDigits((string) ($activeCase['principle_memo_no_suffix'] ?? $memoNoSuffix));
    $docDate = thaiDigits((string) ($activeCase['principle_doc_date'] ?? $docDate));
    $activePlanApprovedDate = trim((string) ($activeCase['plan_approved_date'] ?? ''));
    if ($activePlanApprovedDate !== '') {
        $planApprovedDate = thaiDigits($activePlanApprovedDate);
    }
    $planRevisionNote = thaiDigits((string) ($activeCase['plan_revision_note'] ?? $planRevisionNote));
    $savedBudgetAmount = trim((string) ($activeCase['budget_amount'] ?? ''));
    if ($savedBudgetAmount !== '') {
        $budgetAmountInput = str_replace(',', '', arabicDigits($savedBudgetAmount));
    }
    $networkCount = thaiDigits((string) ($activeCase['network_count'] ?? $networkCount));
    $savedSignerName = trim((string) ($activeCase['signer_name'] ?? ''));
    $savedSignerPosition = trim((string) ($activeCase['signer_position'] ?? ''));
    $savedDirectorName = trim((string) ($activeCase['director_name'] ?? ''));
    $savedDirectorPosition = trim((string) ($activeCase['director_position'] ?? ''));
    $signerName = thaiDigits($savedSignerName !== '' ? $savedSignerName : $signerName);
    $signerPosition = thaiDigits($savedSignerPosition !== '' ? $savedSignerPosition : $signerPosition);
    $directorName = thaiDigits($savedDirectorName !== '' ? $savedDirectorName : $directorName);
    $directorPosition = thaiDigits($savedDirectorPosition !== '' ? $savedDirectorPosition : $directorPosition);
}
$normalizedBudgetAmount = normalizeBudgetNumber($budgetAmountInput);
$budgetInputValid = $normalizedBudgetAmount !== null;
$normalizedBudgetAmount ??= '3000000';
$budgetAmountInput = $normalizedBudgetAmount;
$budgetAmount = thaiDigits(number_format((float) $normalizedBudgetAmount, 2, '.', ','));
$budgetAmountText = thaiBahtText($normalizedBudgetAmount);

$committee = [
    [
        'name' => field('chair_name', 'นางสาวชุตติภัทร เงินอินต๊ะ'),
        'position' => field('chair_position', ''),
        'role' => 'ประธานกรรมการ',
    ],
    [
        'name' => field('member1_name', ''),
        'position' => field('member1_position', ''),
        'role' => 'กรรมการ',
    ],
    [
        'name' => field('member2_name', ''),
        'position' => field('member2_position', ''),
        'role' => 'กรรมการ',
    ],
];
if (trim(rawField('chair_name', '')) === '') {
    $committee[0]['name'] = '';
}
if ($activeCase && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($activeCase['committee'])) {
    foreach ([0, 1, 2] as $i) {
        if (!isset($activeCase['committee'][$i])) continue;
        $committee[$i]['name'] = thaiDigits((string) ($activeCase['committee'][$i]['name'] ?? $committee[$i]['name']));
        $committee[$i]['position'] = thaiDigits((string) ($activeCase['committee'][$i]['position'] ?? $committee[$i]['position']));
        $committee[$i]['role'] = thaiDigits((string) ($activeCase['committee'][$i]['role'] ?? $committee[$i]['role']));
    }
}

$defaultItems = [];
$submittedMedicineIds = $_POST['medicine_id'] ?? $_GET['medicine_id'] ?? [];
$submittedMedicineNames = $_POST['medicine_name'] ?? $_GET['medicine_name'] ?? [];
$submittedQuantities = $_POST['quantity'] ?? $_GET['quantity'] ?? [];
$submittedUnits = $_POST['unit'] ?? $_GET['unit'] ?? [];
if (is_array($submittedMedicineIds) || is_array($submittedMedicineNames)) {
    $submittedMedicineIds = is_array($submittedMedicineIds) ? $submittedMedicineIds : [];
    $submittedMedicineNames = is_array($submittedMedicineNames) ? $submittedMedicineNames : [];
    $submittedQuantities = is_array($submittedQuantities) ? $submittedQuantities : [];
    $submittedUnits = is_array($submittedUnits) ? $submittedUnits : [];
    $maxItems = max(count($submittedMedicineIds), count($submittedMedicineNames), count($submittedQuantities), count($submittedUnits));
    for ($i = 0; $i < $maxItems; $i++) {
        $medicineId = trim((string) ($submittedMedicineIds[$i] ?? ''));
        $medicine = $medicineMap[$medicineId] ?? null;
        $name = $medicine ? (string) $medicine['name'] : trim((string) ($submittedMedicineNames[$i] ?? ''));
        $quantity = thaiDigits(trim((string) ($submittedQuantities[$i] ?? '')));
        $unit = thaiDigits(trim((string) ($submittedUnits[$i] ?? ($medicine['unit'] ?? ''))));
        if ($name === '' && $quantity === '' && $unit === '') {
            continue;
        }
        $defaultItems[] = [
            'medicine_id' => $medicineId,
            'name' => $name,
            'quantity' => $quantity,
            'unit' => $unit,
        ];
    }
}
if (!$defaultItems && $activeCase && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !empty($activeCase['items'])) {
    foreach ($activeCase['items'] as $item) {
        $defaultItems[] = [
            'medicine_id' => (string) ($item['medicine_id'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'quantity' => thaiDigits((string) ($item['quantity'] ?? '')),
            'unit' => thaiDigits((string) ($item['unit'] ?? '')),
        ];
    }
}
if (!$defaultItems) {
    $defaultItems = [
        [
            'medicine_id' => '',
            'name' => '',
            'quantity' => '',
            'unit' => '',
        ],
    ];
}
$itemCount = thaiDigits((string) count($defaultItems));
$saveError = '';

if (($_POST['action'] ?? '') === 'word') {
    $principleWordData = [
        'department' => $department,
        'memo_no_suffix' => $memoNoSuffix,
        'doc_date' => $docDate,
        'fiscal_year' => $fiscalYear,
        'plan_approved_date' => $planApprovedDate,
        'plan_revision_note' => $planRevisionNote,
        'budget_amount' => $budgetAmount,
        'budget_amount_text' => $budgetAmountText,
        'network_count' => $networkCount,
        'item_count' => $itemCount,
        'items' => $defaultItems,
        'committee' => $committee,
        'signer_name' => $signerName,
        'signer_position' => $signerPosition,
        'director_name' => $directorName,
        'director_position' => $directorPosition,
    ];
    $principleWordFilename = principleApprovalMemoWordFilename($memoNoSuffix, $docDate, $itemCount);
    try {
        outputPrincipleApprovalMemoDocx($principleWordData, $principleWordFilename);
    } catch (Throwable $primaryError) {
        error_log('Principle DOCX template export failed: ' . $primaryError->getMessage());
        try {
            outputWordDocx(principleApprovalMemoWordHtml($principleWordData), $principleWordFilename, '', 'principle');
        } catch (Throwable $fallbackError) {
            error_log('Principle DOCX fallback export failed: ' . $fallbackError->getMessage());
            $saveError = 'สร้างไฟล์ Word ไม่สำเร็จ กรุณาตรวจสอบว่าอัปโหลดโฟลเดอร์ vendor, templates และ assets/fonts ครบ และ PHP เปิดใช้งาน ZipArchive';
        }
    }
}

if (($_POST['action'] ?? '') === 'save_case') {
    $normalizedFiscalYear = arabicDigits(trim($fiscalYear));
    if (!preg_match('/^2[4-9][0-9]{2}$/', $normalizedFiscalYear)) {
        $saveError = 'กรุณาระบุปีงบประมาณ พ.ศ. เป็นตัวเลข 4 หลัก เช่น 2570';
    } elseif (!$budgetInputValid) {
        $saveError = 'กรุณากรอกวงเงินเป็นตัวเลขไม่เกิน 12 หลัก และทศนิยมไม่เกิน 2 ตำแหน่ง';
    } elseif (trim($memoNoSuffix) === '' || trim($docDate) === '') {
        $saveError = 'กรุณากรอกเลขที่หลัง / และวันที่ ของหัวข้อ 1 ก่อนบันทึก';
    } elseif (count(array_filter($defaultItems, static fn(array $item): bool => trim((string) ($item['name'] ?? '')) !== '')) === 0) {
        $saveError = 'กรุณาเพิ่มรายการยาอย่างน้อย 1 รายการก่อนบันทึก';
    } elseif (trim($committee[0]['name']) === '' || trim($committee[1]['name']) === '' || trim($committee[2]['name']) === '') {
        $saveError = 'กรุณากรอกรายชื่อกรรมการให้ครบ 3 คนก่อนบันทึก';
    }
}

if (($_POST['action'] ?? '') === 'save_case' && $saveError === '') {
    $caseTitle = 'จัดซื้อยา ' . $itemCount . ' รายการ - ' . $docDate;
    $caseId = saveProcurementCase([
        'id' => $caseId,
        'title' => $caseTitle,
        'fiscal_year' => arabicDigits($fiscalYear),
        'plan_approved_date' => $planApprovedDate,
        'plan_revision_note' => $planRevisionNote,
        'budget_amount' => $budgetAmount,
        'budget_amount_text' => $budgetAmountText,
        'network_count' => $networkCount,
        'principle_memo_no_suffix' => $memoNoSuffix,
        'principle_doc_date' => $docDate,
        'signer_name' => $signerName,
        'signer_position' => $signerPosition,
        'director_name' => $directorName,
        'director_position' => $directorPosition,
        'items' => $defaultItems,
        'committee' => $committee,
    ]);
    saveProcurementDocument($caseId, 'principle_approval', $memoNoSuffix, $docDate);
    header('Location: principle_approval_memo.php?case_id=' . $caseId . '&saved=1');
    exit;
}

?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>บันทึกข้อความ ขออนุมัติในหลักการ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @counter-style thai-decimal {
            system: numeric;
            symbols: "๐" "๑" "๒" "๓" "๔" "๕" "๖" "๗" "๘" "๙";
            suffix: "";
        }
        @page memo {
            size: 210mm 297mm;
            margin: 25mm 20mm 20mm 30mm;
            @top-center {
                content: "- " counter(page, thai-decimal) " -";
                font-family: "TH Sarabun New", "Sarabun", sans-serif;
                font-size: 14pt;
                padding-top: 12.5mm;
            }
        }
        @page memo:first {
            @top-center { content: ""; }
        }
        .memo-page {
            page: memo;
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            color: #111;
            padding: 25mm 20mm 20mm 30mm;
            box-sizing: border-box;
            position: relative;
            font-family: "TH Sarabun New", "Sarabun", sans-serif;
            font-size: 16pt;
            line-height: 1.2;
        }
        .garuda { width: auto; height: 1.5cm; display: block; }
        .memo-title { font-size: 29pt; font-weight: 700; letter-spacing: 0; }
        .memo-line { font-size: 16pt; min-height: 1.05em; }
        .memo-label { font-size: 20pt; font-weight: 700; line-height: 1; white-space: nowrap; }
        .memo-text { font-size: 16pt; line-height: 1.2; }
        .document-stack { display: flex; flex-direction: column; gap: 12mm; }
        @media screen and (min-width: 1024px) {
            .document-stack { zoom: 0.82; }
        }
        .thai-justify,
        .indent {
            text-align: justify;
            text-align-last: left;
            text-justify: inter-character;
            -ms-text-justify: distribute;
        }
        .indent { text-indent: 2.5cm; }
        .section-title { font-weight: 700; margin-top: 0.25cm; margin-left: 2.5cm; }
        .numbered { display: block; text-indent: 2.5cm; margin-left: 0; }
        .numbered > div { display: inline; }
        .numbered > div:first-child { margin-right: 0.15cm; }
        .medicine-line {
            display: grid;
            grid-template-columns: 0.8cm 1fr 2.2cm 2.2cm;
            column-gap: 0.2cm;
            align-items: baseline;
            margin-left: 2.5cm;
        }
        .medicine-line > div:nth-child(3) { text-align: right; }
        .committee-line { display: grid; grid-template-columns: 0.6cm 4.7cm 4.1cm 3.2cm; column-gap: 0.1cm; margin-left: 2.5cm; }
        .red { color: #e11d1d; }
        .continued-note,
        .continued-note-marker,
        .continued-notes-layer {
            display: none !important;
        }
        .closing-block {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .signature { margin-top: 1.1cm; text-align: center; line-height: 1.25; }
        .approval { margin-top: 0.75cm; display: grid; grid-template-columns: 3.2cm 9.8cm 1fr; align-items: start; }
        .approval-signature { grid-column: 2; text-align: center; white-space: nowrap; }
        .signature,
        .approval,
        .approval-signature {
            word-break: keep-all;
            overflow-wrap: normal;
        }
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
            .document-stack { display: block !important; }
            .memo-page {
                width: auto;
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

<main class="mx-auto grid max-w-[100rem] gap-5 px-4 py-6 lg:grid-cols-[minmax(0,1fr)_620px]">
    <aside class="no-print order-2 rounded-lg border border-zinc-800 bg-zinc-900/80 p-4 lg:sticky lg:top-20 lg:max-h-[calc(100vh-6rem)] lg:self-start lg:overflow-y-auto lg:overscroll-contain">
        <div class="mb-4 grid gap-3">
            <h1 class="text-lg font-semibold">ข้อมูลบันทึกข้อความ</h1>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" onclick="window.print()" class="whitespace-nowrap rounded-md bg-emerald-500 px-3 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">พิมพ์</button>
                <button type="submit" form="principle_case_form" name="action" value="word" class="whitespace-nowrap rounded-md border border-emerald-500/60 px-3 py-2 text-sm font-medium text-emerald-200 hover:bg-emerald-500/10">ดาวน์โหลด Word</button>
            </div>
        </div>
        <?php if ($loadError !== ''): ?>
            <div class="mb-3 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-100"><?= h($loadError) ?></div>
        <?php endif; ?>
        <?php if ($saveError !== ''): ?>
            <div class="mb-3 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-100"><?= h($saveError) ?></div>
        <?php endif; ?>

        <form method="get" action="principle_approval_memo.php" class="mb-3 rounded-md border border-zinc-800 p-3">
            <label class="block">
                <span class="text-xs text-zinc-400">เลือกชุดเอกสาร</span>
                <select name="case_id" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" onchange="this.form.submit()">
                    <option value="">เริ่มชุดเอกสารใหม่</option>
                    <?php foreach ($caseOptions as $caseOption): ?>
                        <?php
                        $optionMemo = trim((string) ($caseOption['principle_memo_no_suffix'] ?? ''));
                        $optionDate = trim((string) ($caseOption['principle_doc_date'] ?? ''));
                        $optionLabel = 'ชม.๕๑๐๐๖.๑๓.๑/' . $optionMemo . ' ลงวันที่ ' . $optionDate;
                        ?>
                        <option value="<?= (int) $caseOption['id'] ?>" <?= (int) $caseOption['id'] === (int) $caseId ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <a href="document_history.php" class="mt-2 inline-flex rounded-md border border-zinc-700 px-3 py-2 text-xs text-zinc-200 hover:border-emerald-400">ดูประวัติเอกสารทั้งหมด</a>
        </form>

        <?php if (($_GET['saved'] ?? '') === '1'): ?>
            <div class="mb-3 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-100">
                บันทึกชุดงานจัดซื้อแล้ว สามารถเปิดเอกสารถัดไปด้วยข้อมูลชุดเดียวกันได้
            </div>
            <div class="mb-3 grid gap-2 text-sm">
                <a class="rounded-md border border-emerald-500/60 px-3 py-2 text-emerald-200 hover:bg-emerald-500/10" href="committee_scope_invitation.php?case_id=<?= (int) $caseId ?>">ไปหัวข้อ 2 แจ้งคำสั่งและเชิญประชุม</a>
                <a class="rounded-md border border-emerald-500/60 px-3 py-2 text-emerald-200 hover:bg-emerald-500/10" href="tor_company_purchase.php?case_id=<?= (int) $caseId ?>">ไปหัวข้อ 3 เอกสาร TOR</a>
                <a class="rounded-md border border-emerald-500/60 px-3 py-2 text-emerald-200 hover:bg-emerald-500/10" href="meeting_report.php?case_id=<?= (int) $caseId ?>">ไปหัวข้อ 4 รายงานการประชุม</a>
            </div>
        <?php endif; ?>

        <form id="principle_case_form" method="post" action="principle_approval_memo.php" class="grid gap-4">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
            <section class="rounded-md border border-zinc-800 p-3" aria-labelledby="principle-primary-data">
                <div id="principle-primary-data" class="mb-2 text-sm font-semibold text-emerald-300">1. ข้อมูลโครงการและหนังสือ</div>
                <p class="mb-3 text-xs text-zinc-500">ระบุปีงบประมาณของชุดเอกสารตั้งแต่ขั้นตอนแรก วันที่ในหนังสือเป็นคนละข้อมูล</p>
                <label class="mb-3 block">
                    <span class="text-xs text-zinc-400">ปีงบประมาณ พ.ศ. <span aria-hidden="true">*</span></span>
                    <input name="fiscal_year" data-field="fiscal_year" inputmode="numeric" maxlength="4" required value="<?= h($fiscalYear) ?>" placeholder="เช่น ๒๕๗๐" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                </label>
                <?php foreach (['memo_no_suffix' => ['เลขที่หนังสือ หลัง /', $memoNoSuffix], 'doc_date' => ['วันที่ออกบันทึก', $docDate]] as $name => [$label, $value]): ?>
                    <label class="mb-2 block">
                        <span class="text-xs text-zinc-400"><?= h($label) ?></span>
                        <input name="<?= h($name) ?>" data-field="<?= h($name) ?>"<?= $name === 'doc_date' ? ' data-thai-date-picker="1"' : '' ?> value="<?= h($value) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                <?php endforeach; ?>
            </section>
            <section class="rounded-md border border-zinc-800 p-3" aria-labelledby="principle-budget-data">
                <div id="principle-budget-data" class="mb-3 text-sm font-semibold text-emerald-300">2. ข้อมูลแผนและวงเงินงบประมาณ</div>
                <?php foreach (['plan_approved_date' => ['วันที่อนุมัติแผน', $planApprovedDate], 'plan_revision_note' => ['ปรับปรุงครั้งที่', $planRevisionNote]] as $name => [$label, $value]): ?>
                    <label class="mb-2 block">
                        <span class="text-xs text-zinc-400"><?= h($label) ?></span>
                        <input name="<?= h($name) ?>" data-field="<?= h($name) ?>"<?= $name === 'plan_approved_date' ? ' data-thai-date-picker="1"' : '' ?> value="<?= h($value) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                <?php endforeach; ?>
                <label class="mb-2 block">
                    <span class="text-xs text-zinc-400">วงเงินงบประมาณ (บาท)</span>
                    <input name="budget_amount" id="budget_amount" inputmode="decimal" value="<?= h($budgetAmountInput) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400" placeholder="3000000">
                    <span class="mt-1 block text-xs text-zinc-500">แสดงในเอกสาร: <span id="budget_amount_preview"><?= h($budgetAmount) ?> บาท (<?= h($budgetAmountText) ?>)</span></span>
                </label>
            </section>

            <section class="rounded-md border border-zinc-800 p-3">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div class="text-sm font-medium text-emerald-300">3. รายการยา</div>
                    <button type="button" id="add_medicine" class="rounded-md border border-zinc-700 px-2 py-1 text-xs hover:border-emerald-400">เพิ่มรายการ</button>
                </div>
                <div id="medicine_rows" class="grid gap-3">
                    <?php foreach ($defaultItems as $i => $item): ?>
                        <div class="medicine-row rounded-md border border-zinc-800 bg-zinc-950 p-3">
                            <div class="mb-2 text-xs font-medium text-emerald-300">รายการที่ <span class="medicine-row-number"><?= thaiDigits((string) ($i + 1)) ?></span></div>
                            <div class="grid gap-2 xl:grid-cols-[minmax(0,1fr)_5.5rem_6rem_auto] xl:items-end">
                                <label class="block min-w-0">
                                    <span class="text-xs text-zinc-400">ชื่อยา</span>
                                    <input type="hidden" name="medicine_id[]" class="medicine-id" value="<?= h($item['medicine_id']) ?>">
                                    <input type="text" name="medicine_name[]" class="medicine-search mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400" value="<?= h($item['name']) ?>" placeholder="พิมพ์ชื่อยาอย่างน้อย 3 ตัว">
                                    <div class="medicine-results hidden max-h-52 overflow-auto rounded-md border border-zinc-700 bg-zinc-950"></div>
                                </label>
                                <label class="block">
                                    <span class="text-xs text-zinc-400">จำนวน</span>
                                    <input name="quantity[]" class="quantity mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-right text-sm outline-none focus:border-emerald-400" value="<?= h($item['quantity']) ?>">
                                </label>
                                <label class="block">
                                    <span class="text-xs text-zinc-400">หน่วย</span>
                                    <input name="unit[]" class="unit mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400" value="<?= h($item['unit']) ?>">
                                </label>
                                <button type="button" class="remove-medicine rounded-md border border-red-500/50 px-3 py-2 text-xs text-red-200 hover:bg-red-500/10">ลบ</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rounded-md border border-zinc-800 p-3">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div class="text-sm font-medium text-emerald-300">4. คณะกรรมการ</div>
                    <button type="button" id="open_committee_modal" class="rounded-md border border-emerald-500/60 px-2 py-1 text-xs text-emerald-200 hover:bg-emerald-500/10">เลือกชุดกรรมการ</button>
                </div>
                <?php
                $committeeControls = [
                    ['chair_name', 'chair_position', 'ประธาน', $committee[0]],
                    ['member1_name', 'member1_position', 'กรรมการ 1', $committee[1]],
                    ['member2_name', 'member2_position', 'กรรมการ 2', $committee[2]],
                ];
                foreach ($committeeControls as [$nameKey, $positionKey, $label, $person]):
                ?>
                    <div class="mb-3 rounded-md border border-zinc-800 bg-zinc-950 p-3">
                        <div class="mb-2 text-xs font-medium text-zinc-300"><?= h($label) ?></div>
                        <input name="<?= h($nameKey) ?>" data-field="<?= h($nameKey) ?>" value="<?= h($person['name']) ?>" class="mb-2 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400" placeholder="ชื่อ-สกุล">
                        <input name="<?= h($positionKey) ?>" data-field="<?= h($positionKey) ?>" value="<?= h($person['position']) ?>" class="w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400" placeholder="ตำแหน่ง">
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="rounded-md border border-zinc-800 p-3">
                <div class="mb-3 text-sm font-medium text-emerald-300">5. ผู้ลงนาม</div>
                <label class="mb-2 block">
                    <span class="text-xs text-zinc-400">ชื่อผู้เสนอ</span>
                    <input name="signer_name" data-field="signer_name" value="<?= h($signerName) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                </label>
                <label class="mb-2 block">
                    <span class="text-xs text-zinc-400">ตำแหน่งผู้เสนอ</span>
                    <input name="signer_position" data-field="signer_position" value="<?= h($signerPosition) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                </label>
                <label class="mb-2 block">
                    <span class="text-xs text-zinc-400">ชื่อผู้อนุมัติ</span>
                    <input name="director_name" data-field="director_name" value="<?= h($directorName) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                </label>
                <label class="block">
                    <span class="text-xs text-zinc-400">ตำแหน่งผู้อนุมัติ</span>
                    <textarea name="director_position" data-field="director_position" rows="2" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400"><?= h($directorPosition) ?></textarea>
                </label>
            </section>

            <button type="submit" name="action" value="save_case" class="app-primary-save rounded-md border border-emerald-500/60 px-4 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10">บันทึกชุดงานและอัปเดตเอกสาร</button>
        </form>
    </aside>

    <section class="document-stack order-1 overflow-auto">
        <article class="memo-page" id="memo_page_1">
            <div class="grid grid-cols-[3cm_1fr_3cm] items-start">
                <img src="picture/picture-1.png" alt="" class="garuda">
                <div class="memo-title pt-2 text-center">บันทึกข้อความ</div>
            </div>

            <div class="mt-1 grid grid-cols-[auto_1fr] items-end gap-x-2">
                <div class="memo-label">ส่วนราชการ</div>
                <div class="memo-line thai-justify"><?= h($department) ?></div>
            </div>
            <div class="mt-1 grid grid-cols-[auto_1fr_auto_1fr] items-end gap-x-2">
                <div class="memo-label">ที่</div>
                <div class="memo-line">ชม.๕๑๐๐๖.๑๓.๑/<span data-bind="memo_no_suffix"><?= h($memoNoSuffix) ?></span></div>
                <div class="memo-label">วันที่</div>
                <div class="memo-line text-center"><span data-bind="doc_date"><?= h($docDate) ?></span></div>
            </div>
            <div class="mt-1 grid grid-cols-[auto_1fr] items-start gap-x-2">
                <div class="memo-label">เรื่อง</div>
                <div class="memo-text thai-justify">ขออนุมัติในหลักการเพื่อดำเนินการจัดซื้อยา จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ</div>
            </div>
            <div class="mt-1 flex items-baseline gap-x-2">
                <div class="memo-label text-[16pt] leading-[1]">เรียน</div>
                <div class="memo-text leading-[1]">นายกองค์การบริหารส่วนจังหวัดเชียงใหม่</div>
            </div>

            <div class="section-title">ต้นเรื่อง</div>
            <p class="indent">
                ตาม โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ในสังกัดองค์การบริหารส่วนจังหวัดเชียงใหม่ประกาศแผนการใช้จ่ายเงินประจำปีงบประมาณ พ.ศ. <span data-bind="fiscal_year"><?= h($fiscalYear) ?></span><span data-plan-revision-wrapper class="<?= trim($planRevisionNote) === '' ? 'hidden' : '' ?>"> (<span data-bind="plan_revision_note"><?= h($planRevisionNote) ?></span>)</span> อนุมัติให้ใช้เมื่อวันที่ <span data-bind="plan_approved_date"><?= h($planApprovedDate) ?></span> หมวดยาและเวชภัณฑ์มิใช่ยา หมวด 1 ยา ที่ตั้งไว้ <span data-bind="budget_amount"><?= h($budgetAmount) ?></span> บาท (<span data-bind="budget_amount_text"><?= h($budgetAmountText) ?></span>) ตามหลักการจำแนกประเภทรายจ่ายตามงบประมาณขององค์กรปกครองส่วนท้องถิ่นเป็นไปตามระเบียบกระทรวงมหาดไทย ด้วยการเบิกค่าใช้จ่ายในการบริหารงานขององค์กรปกครองส่วนท้องถิ่น พ.ศ. ๒๕๖๒
            </p>

            <div class="section-title">ข้อเท็จจริง</div>
            <p class="indent">
                หน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง มีความประสงค์จัดซื้อยา เพื่อใช้ในการรักษาพยาบาลแก่ประชาชนในเขตพื้นที่รับผิดชอบ หน่วยบริการประจำ (CUP) โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้งและหน่วยบริการปฐมภูมิในเครือข่ายจำนวน <span data-bind="network_count"><?= h($networkCount) ?></span> แห่ง อำเภอสันกำแพง ตามแผนงบประมาณเงินบำรุงประเภทยาและเวชภัณฑ์ที่มิใช่ยา ปีพ.ศ.<span data-bind="fiscal_year"><?= h($fiscalYear) ?></span> โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง เนื่องจากมีปริมาณยาคงคลังไม่เพียงพอต่อการให้บริการประชาชนและเพื่อความต่อเนื่องในการปฏิบัติงานของเจ้าหน้าที่ จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ ได้แก่
            </p>
            <div id="doc_medicine_items" class="mt-1">
                <?php foreach ($defaultItems as $i => $item): ?>
                    <div class="medicine-line">
                        <div><?= thaiDigits((string) ($i + 1)) ?>.</div>
                        <div><?= h($item['name']) ?></div>
                        <div class="text-right"><?= h(quantityText($item['quantity'])) ?></div>
                        <div><?= h($item['unit']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div>
                <div>
                    <div class="section-title">ข้อกฎหมาย</div>
                    <div class="numbered thai-justify"><div>๑.</div><div>ตามพระราชบัญญัติการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. ๒๕๖๐
มาตรา ๔  ในพระราชบัญญัตินี้
                    “ราคากลาง” หมายความว่า ราคาเพื่อใช้เป็นฐานสำหรับเปรียบเทียบราคาที่ผู้ยื่นข้อเสนอได้ยื่นเสนอไว้ซึ่งสามารถจัดซื้อจัดจ้างได้จริงตามลำดับ ดังต่อไปนี้
(๑) ราคาที่ได้มาจากการคำนวณตามหลักเกณฑ์ที่คณะกรรมการราคากลางกำหนด
(๒) ราคาที่ได้มาจากฐานข้อมูลราคาอ้างอิงของพัสดุที่กรมบัญชีกลางจัดทำ
(๓) ราคามาตรฐานที่สำนักงบประมาณหรือหน่วยงานกลางอื่นกำหนด
(๔) ราคาที่ได้มาจากการสืบราคาจากท้องตลาด
(๕) ราคาที่เคยซื้อหรือจ้างครั้งหลังสุดภายในระยะเวลาสองปีงบประมาณ
(๖) ราคาอื่นใดตามหลักเกณฑ์ วิธีการ หรือแนวทางปฏิบัติของหน่วยงานของรัฐนั้น ๆในกรณีที่มีราคาตาม (๑) ให้ใช้ราคาตาม (๑) ก่อน ในกรณีที่ไม่มีราคาตาม (๑) แต่มีราคาตาม (๒)หรือ (๓) ให้ใช้ราคาตาม (๒) หรือ (๓) ก่อน โดยจะใช้ราคาใดตาม (๒) หรือ (๓) ให้คำนึงถึงประโยชน์ของหน่วยงานของรัฐเป็นสำคัญ ในกรณีที่ไม่มีราคาตาม (๑) (๒) และ (๓) ให้ใช้ราคาตาม (๔) (๕) หรือ (๖)โดยจะใช้ราคาใดตาม (๔) (๕) หรือ (๖) ให้คำนึงถึงประโยชน์ของหน่วยงานของรัฐเป็นสำคัญ
</div></div>
                </div>
            </div>
            <div class="numbered thai-justify"><div>๒.</div><div>
 	  ข้อ ๒๑ ในการซื้อหรือจ้างที่มิใช่การจ้างก่อสร้าง ให้หัวหน้าหน่วยงานของรัฐแต่งตั้ง คณะกรรมการ
ขึ้นมาคณะหนึ่ง หรือจะให้เจ้าหน้าที่หรือบุคคลใดบุคคลหนึ่งรับผิดชอบในการจัดทำร่างขอบเขตของงาน               หรือรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง รวมทั้งกำหนดหลักเกณฑ์การพิจารณาคัดเลือกข้อเสนอด้วย
   เพื่อให้การกำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้างมีมาตรฐานและเป็นประโยชน์ต่อทางราชการ หากพัสดุที่จะซื้อหรือจ้างใดมีประกาศกำหนดมาตรฐานผลิตภัณฑ์อุตสาหกรรมแล้วให้กำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง หรือรายการในการก่อสร้างตามมาตรฐานผลิตภัณฑ์อุตสาหกรรม หรือเพื่อความสะดวกจะระบุเฉพาะหมายเลขมาตรฐานก็ได้หรือในกรณีพัสดุ  ที่จะซื้อหรือจ้างใดยังไม่มีประกาศกำหนดมาตรฐานผลิตภัณฑ์อุตสาหกรรมแต่มีผู้ได้รับการจดทะเบียนผลิตภัณฑ์ไว้กับกระทรวงอุตสาหกรรมแล้ว ให้กำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้างหรือรายการในการก่อสร้างให้สอดคล้องกับรายละเอียดคุณลักษณะเฉพาะตามที่ระบุในคู่มือผู้ซื้อหรือใบแทรกคู่มือผู้ซื้อ               ที่กระทรวงอุตสาหกรรมจัดทำขึ้น
</div></div>
            <div class="numbered thai-justify mt-2"><div>๓.</div><div>ตามหนังสือ คณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ            ด่วนที่สุด ที่ กค(กวจ) ๐๔๐๕.๒/ว78 ลงวันที่ 31 มกราคม ๒๕๖5 เรื่อง อนุมัติยกเว้นและกำหนดแนวทางแนวทางปฏิบัติตามกฎกระทรวง กำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุ ที่รัฐต้องการส่งเสริมหรือสนับสนุน                  (ฉบับที่ ๒) พ.ศ.๒๕๖๓ </div></div>
            <div class="numbered thai-justify mt-2"><div>๔.</div><div>ตามหนังสือ คณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ด่วนที่สุด ที่ กค(กวจ) ๐๔๐๕.๒/ว๕๖ ลงวันที่ ๒๔ มกราคม ๒๕๖๖ เรื่อง แนวทางปฏิบัติสำหรับการส่งเสริมหรือสนับสนุนผู้ประกอบการวิสาหกิจขนาดกลางและขนาดย่อม (SMEs) เพิ่มเติม</div></div>

            <div class="section-title">ข้อพิจารณา</div>
            <p class="indent">
                เพื่อให้เป็นไปด้วยความเรียบร้อย ถูกต้องตามระเบียบกฎหมาย จึงขออนุมัติดำเนินการดังต่อไปนี้
            </p>
            <div class="numbered thai-justify"><div>๑.</div><div>อนุมัติให้ดำเนินการจัดซื้อยา จำนวน <span data-bind="item_count"><?= h($itemCount) ?></span> รายการ</div></div>
            <div class="numbered thai-justify"><div>๒.</div><div>ขออนุมัติแต่งตั้งผู้กำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง การจัดซื้อยา โดยให้ใช้หนังสือบันทึกข้อความฉบับนี้แทนคำสั่งแต่งตั้งคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลางโดยอนุโลม ดังรายชื่อต่อไปนี้</div></div>
            <div id="doc_committee_items" class="mt-2">
                <?php foreach ($committee as $i => $person): ?>
                    <div class="committee-line">
                        <div><?= thaiDigits((string) ($i + 1)) ?>.</div>
                        <div data-bind="<?= $i === 0 ? 'chair_name' : 'member' . $i . '_name' ?>"><?= h($person['name']) ?></div>
                        <div data-bind="<?= $i === 0 ? 'chair_position' : 'member' . $i . '_position' ?>"><?= h($person['position']) ?></div>
                        <div><?= h($person['role']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="closing-block">
                <p class="indent mt-3">
                    จึงเรียนมาเพื่อโปรดพิจารณา หากเห็นชอบโปรดอนุมัติในหลักการเพื่อจะได้ดำเนินการตามระเบียบพัสดุฯ ต่อไป
                </p>

                <div class="signature">
                    <div>(<span data-bind="signer_name"><?= h($signerName) ?></span>)</div>
                    <div data-bind="signer_position"><?= h($signerPosition) ?></div>
                </div>

                <div class="approval">
                    <div>
                        <div>-เห็นชอบ</div>
                        <div>-อนุมัติ</div>
                    </div>
                    <div class="approval-signature">
                        <div style="height: 1.1cm;"></div>
                        <div>(<span data-bind="director_name"><?= h($directorName) ?></span>)</div>
                        <div data-bind="director_position"><?= nl2br(h($directorPosition)) ?></div>
                    </div>
                </div>
            </div>
        </article>
    </section>
</main>
<div id="committee_modal" class="fixed inset-0 z-50 hidden bg-black/70 p-4 no-print">
    <div class="mx-auto mt-8 grid max-h-[90vh] max-w-5xl gap-4 overflow-auto rounded-lg border border-zinc-800 bg-zinc-950 p-5 shadow-xl lg:grid-cols-[minmax(260px,1fr)_minmax(320px,1fr)]">
        <div>
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">เลือกชุดกรรมการ</h2>
                <button type="button" id="close_committee_modal" class="rounded-md border border-zinc-700 px-3 py-1 text-sm hover:border-emerald-400">ปิด</button>
            </div>
            <div class="space-y-2">
                <?php if (!$committeeSets): ?>
                    <div class="rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-sm text-zinc-400">ยังไม่มีชุดกรรมการที่บันทึกไว้</div>
                <?php endif; ?>
                <?php foreach ($committeeSets as $set): ?>
                    <button type="button" class="committee-set-choice w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-left text-sm hover:border-emerald-400" data-id="<?= (int) $set['id'] ?>">
                        <span class="block font-medium text-zinc-100"><?= h((string) $set['title']) ?></span>
                        <span class="mt-1 block text-xs text-zinc-400"><?= h((string) $set['chair_name']) ?> / <?= h((string) $set['member1_name']) ?> / <?= h((string) $set['member2_name']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <form id="committee_set_form" method="post" action="principle_approval_memo.php" class="mt-5 rounded-md border border-zinc-800 bg-zinc-900/70 p-3">
                <input type="hidden" name="action" value="save_committee_set">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="case_id" value="<?= (int) $caseId ?>">
                <input type="hidden" name="committee_set_id" value="">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <div id="committee_set_form_heading" class="text-sm font-medium text-emerald-300">เพิ่มชุดกรรมการใหม่</div>
                        <div id="committee_set_form_hint" class="mt-1 text-xs text-zinc-500">หรือเลือกชุดด้านบนเพื่อแก้ไขข้อมูลเดิม</div>
                    </div>
                    <button type="button" id="cancel_committee_set_edit" class="hidden rounded-md border border-zinc-700 px-3 py-1 text-xs text-zinc-300 hover:border-emerald-400">ยกเลิกแก้ไข</button>
                </div>
                <div id="committee_set_message" class="mb-3 hidden rounded-md border px-3 py-2 text-xs"></div>
                <label class="mb-2 block">
                    <span class="text-xs text-zinc-400">ชื่อชุดกรรมการ</span>
                    <input name="committee_set_title" required maxlength="255" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                </label>
                <div class="grid gap-2 md:grid-cols-2">
                    <label class="block md:col-span-2">
                        <span class="text-xs text-zinc-400">ประธานกรรมการ - ชื่อ</span>
                        <input name="committee_set_chair_name" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-xs text-zinc-400">ประธานกรรมการ - ตำแหน่ง</span>
                        <input name="committee_set_chair_position" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-xs text-zinc-400">กรรมการ 1 - ชื่อ</span>
                        <input name="committee_set_member1_name" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-xs text-zinc-400">กรรมการ 1 - ตำแหน่ง</span>
                        <input name="committee_set_member1_position" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-xs text-zinc-400">กรรมการ 2 - ชื่อ</span>
                        <input name="committee_set_member2_name" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-xs text-zinc-400">กรรมการ 2 - ตำแหน่ง</span>
                        <input name="committee_set_member2_position" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm outline-none focus:border-emerald-400">
                    </label>
                </div>
                <button type="submit" id="committee_set_submit" class="mt-3 rounded-md border border-emerald-500/60 px-4 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10">เพิ่มชุดกรรมการ</button>
            </form>
        </div>

        <div class="rounded-md border border-zinc-800 bg-zinc-900/70 p-4">
            <div class="mb-3 text-sm font-medium text-emerald-300">รายละเอียดชุดที่เลือก</div>
            <div id="committee_preview_empty" class="rounded-md border border-zinc-800 bg-zinc-950 px-3 py-3 text-sm text-zinc-400">เลือกชุดกรรมการทางซ้ายเพื่อดูรายชื่อก่อนใช้งาน</div>
            <div id="committee_preview" class="hidden space-y-3"></div>
            <button type="button" id="apply_committee_set" class="mt-4 hidden w-full rounded-md bg-emerald-500 px-4 py-2 text-sm font-medium text-zinc-950 hover:bg-emerald-400">เลือกใช้ชุดนี้</button>
        </div>
    </div>
</div>
<script src="assets/js/word_export.js"></script>
<script>
const medicines = <?= json_encode($medicines, JSON_UNESCAPED_UNICODE) ?>;
let committeeSets = <?= json_encode(array_column($committeeSets, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const fallbackMedicine = <?= json_encode($fallbackMedicine, JSON_UNESCAPED_UNICODE) ?>;
const thaiDigits = (value) => String(value ?? '').replace(/[0-9]/g, (digit) => String.fromCharCode(0x0E50 + Number(digit)));
const thaiToArabicDigits = (value) => String(value ?? '').replace(/[๐-๙]/g, (digit) => String(digit.charCodeAt(0) - 0x0E50));
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
    const normalized = thaiToArabicDigits(value).trim();
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

function formatThaiQuantityDisplay(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    const normalized = thaiToArabicDigits(raw).replaceAll(',', '');
    if (!/^-?\d+(?:\.\d+)?$/.test(normalized)) return thaiDigits(raw);
    const sign = normalized.startsWith('-') ? '-' : '';
    const numberText = sign ? normalized.slice(1) : normalized;
    const [integerPart, decimalPart = ''] = numberText.split('.');
    const grouped = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return thaiDigits(`${sign}${grouped}${decimalPart ? `.${decimalPart}` : ''}`);
}

function normalizeBudgetNumberClient(value) {
    const normalized = thaiToArabicDigits(String(value ?? '')).replaceAll(',', '').replaceAll(' ', '').trim();
    if (!/^\d{1,12}(?:\.\d{1,2})?$/.test(normalized)) return null;
    const [rawInteger, rawDecimal = ''] = normalized.split('.');
    const integer = rawInteger.replace(/^0+(?=\d)/, '') || '0';
    return rawDecimal ? `${integer}.${rawDecimal.padEnd(2, '0')}` : integer;
}

function thaiNumberTextClient(value, useEtForFinalOne = false) {
    const integer = String(value).replace(/^0+/, '') || '0';
    if (integer === '0') return 'ศูนย์';
    if (integer.length > 6) {
        const head = integer.slice(0, -6);
        const tail = integer.slice(-6);
        return `${thaiNumberTextClient(head)}ล้าน${/^0+$/.test(tail) ? '' : thaiNumberTextClient(tail, true)}`;
    }
    const digits = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    const units = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];
    let result = '';
    [...integer].forEach((character, index) => {
        const digit = Number(character);
        if (!digit) return;
        const position = integer.length - index - 1;
        if (position === 1 && digit === 1) result += 'สิบ';
        else if (position === 1 && digit === 2) result += 'ยี่สิบ';
        else if (position === 0 && digit === 1 && (integer.length > 1 || useEtForFinalOne)) result += 'เอ็ด';
        else result += digits[digit] + units[position];
    });
    return result;
}

function budgetDisplayClient(value) {
    const normalized = normalizeBudgetNumberClient(value);
    if (normalized === null) return null;
    const [integer, rawDecimal = ''] = normalized.split('.');
    const decimal = rawDecimal.padEnd(2, '0');
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const amount = thaiDigits(`${grouped}.${decimal}`);
    const text = `${thaiNumberTextClient(integer)}บาท${decimal === '00' ? 'ถ้วน' : `${thaiNumberTextClient(decimal)}สตางค์`}`;
    return { amount, text };
}

function bindBudgetAmount() {
    const input = document.getElementById('budget_amount');
    const preview = document.getElementById('budget_amount_preview');
    if (!input) return;
    const update = () => {
        const display = budgetDisplayClient(input.value);
        input.setCustomValidity(display ? '' : 'กรุณากรอกตัวเลขไม่เกิน 12 หลัก และทศนิยมไม่เกิน 2 ตำแหน่ง');
        if (!display) {
            if (preview) preview.textContent = 'รูปแบบตัวเลขไม่ถูกต้อง';
            return;
        }
        document.querySelectorAll('[data-bind="budget_amount"]').forEach((target) => target.textContent = display.amount);
        document.querySelectorAll('[data-bind="budget_amount_text"]').forEach((target) => target.textContent = display.text);
        if (preview) preview.textContent = `${display.amount} บาท (${display.text})`;
    };
    input.addEventListener('input', update);
    update();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

let selectedCommitteeSet = null;

function openCommitteeModal() {
    document.getElementById('committee_modal')?.classList.remove('hidden');
}

function closeCommitteeModal() {
    document.getElementById('committee_modal')?.classList.add('hidden');
}

function committeePeopleFromSet(set) {
    return [
        { label: 'ประธานกรรมการ', name: set.chair_name || '', position: set.chair_position || '', role: 'ประธานกรรมการ' },
        { label: 'กรรมการ 1', name: set.member1_name || '', position: set.member1_position || '', role: 'กรรมการ' },
        { label: 'กรรมการ 2', name: set.member2_name || '', position: set.member2_position || '', role: 'กรรมการ' },
    ];
}

function renderCommitteePreview(set) {
    selectedCommitteeSet = set || null;
    const preview = document.getElementById('committee_preview');
    const empty = document.getElementById('committee_preview_empty');
    const apply = document.getElementById('apply_committee_set');
    if (!set || !preview || !empty || !apply) return;

    empty.classList.add('hidden');
    preview.classList.remove('hidden');
    apply.classList.remove('hidden');
    preview.innerHTML = `
        <div class="rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-100">${escapeHtml(set.title || '')}</div>
        ${committeePeopleFromSet(set).map((person) => `
            <div class="rounded-md border border-zinc-800 bg-zinc-950 p-3 text-sm">
                <div class="mb-1 text-xs font-medium text-zinc-400">${escapeHtml(person.label)}</div>
                <div class="font-medium text-zinc-100">${escapeHtml(person.name)}</div>
                <div class="mt-1 text-zinc-300">${escapeHtml(person.position)}</div>
                <div class="mt-1 text-xs text-zinc-500">${escapeHtml(person.role)}</div>
            </div>
        `).join('')}
    `;
}

function showCommitteeSetMessage(message = '', type = 'success') {
    const box = document.getElementById('committee_set_message');
    if (!box) return;
    if (!message) {
        box.textContent = '';
        box.classList.add('hidden');
        return;
    }
    box.textContent = message;
    box.className = `mb-3 rounded-md border px-3 py-2 text-xs ${type === 'error'
        ? 'border-red-500/40 bg-red-500/10 text-red-100'
        : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100'}`;
}

function fillCommitteeSetForm(set) {
    const form = document.getElementById('committee_set_form');
    if (!form || !set) return;
    const values = {
        committee_set_id: set.id,
        committee_set_title: set.title,
        committee_set_chair_name: set.chair_name,
        committee_set_chair_position: set.chair_position,
        committee_set_member1_name: set.member1_name,
        committee_set_member1_position: set.member1_position,
        committee_set_member2_name: set.member2_name,
        committee_set_member2_position: set.member2_position,
    };
    Object.entries(values).forEach(([name, value]) => {
        const input = form.elements.namedItem(name);
        if (input) input.value = value || '';
    });
    document.getElementById('committee_set_form_heading').textContent = `แก้ไขชุด “${set.title || ''}”`;
    document.getElementById('committee_set_form_hint').textContent = 'แก้ชื่อ ตำแหน่ง หรือชื่อชุด แล้วกดบันทึกการแก้ไข';
    document.getElementById('committee_set_submit').textContent = 'บันทึกการแก้ไข';
    document.getElementById('cancel_committee_set_edit').classList.remove('hidden');
    showCommitteeSetMessage();
}

function resetCommitteeSetForm() {
    const form = document.getElementById('committee_set_form');
    if (!form) return;
    form.reset();
    const idInput = form.elements.namedItem('committee_set_id');
    if (idInput) idInput.value = '';
    document.getElementById('committee_set_form_heading').textContent = 'เพิ่มชุดกรรมการใหม่';
    document.getElementById('committee_set_form_hint').textContent = 'หรือเลือกชุดด้านบนเพื่อแก้ไขข้อมูลเดิม';
    document.getElementById('committee_set_submit').textContent = 'เพิ่มชุดกรรมการ';
    document.getElementById('cancel_committee_set_edit').classList.add('hidden');
    showCommitteeSetMessage();
}

function appendCommitteeSetChoice(set) {
    if (!set?.id) return;
    const container = document.querySelector('.committee-set-choice')?.parentElement || document.querySelector('#committee_modal .space-y-2');
    if (!container) return;
    container.querySelector('.text-zinc-400')?.closest('div.rounded-md')?.remove();
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'committee-set-choice w-full rounded-md border border-zinc-800 bg-zinc-900 px-3 py-2 text-left text-sm hover:border-emerald-400';
    button.dataset.id = set.id;
    button.innerHTML = `
        <span class="block font-medium text-zinc-100">${escapeHtml(set.title || '')}</span>
        <span class="mt-1 block text-xs text-zinc-400">${escapeHtml(set.chair_name || '')} / ${escapeHtml(set.member1_name || '')} / ${escapeHtml(set.member2_name || '')}</span>
    `;
    const existing = Array.from(container.querySelectorAll('.committee-set-choice')).find((item) => String(item.dataset.id) === String(set.id));
    if (existing) {
        existing.replaceWith(button);
    } else {
        container.appendChild(button);
    }
}

function setFieldValue(name, value) {
    const field = document.querySelector(`[name="${name}"]`);
    if (!field) return;
    field.value = thaiDigits(value || '');
    field.dispatchEvent(new Event('input', { bubbles: true }));
}

function applySelectedCommitteeSet() {
    if (!selectedCommitteeSet) return;
    setFieldValue('chair_name', selectedCommitteeSet.chair_name);
    setFieldValue('chair_position', selectedCommitteeSet.chair_position);
    setFieldValue('member1_name', selectedCommitteeSet.member1_name);
    setFieldValue('member1_position', selectedCommitteeSet.member1_position);
    setFieldValue('member2_name', selectedCommitteeSet.member2_name);
    setFieldValue('member2_position', selectedCommitteeSet.member2_position);
    closeCommitteeModal();
}

function currentMedicineRows() {
    return Array.from(document.querySelectorAll('.medicine-row'));
}

function rowData(row) {
    return {
        name: row.querySelector('.medicine-search')?.value.trim() || '',
        quantity: thaiDigits(row.querySelector('.quantity')?.value.trim() || ''),
        unit: thaiDigits(row.querySelector('.unit')?.value.trim() || ''),
    };
}

function renderMedicineItems() {
    const rows = currentMedicineRows();
    rows.forEach((row, index) => {
        row.querySelector('.medicine-row-number')?.replaceChildren(document.createTextNode(thaiDigits(index + 1)));
    });
    const items = rows
        .map(rowData)
        .filter((item) => item.name || item.quantity || item.unit);
    const count = thaiDigits(items.length || 0);
    document.querySelectorAll('[data-bind="item_count"]').forEach((target) => target.textContent = count);
    document.getElementById('doc_medicine_items').innerHTML = items.map((item, index) => `
        <div class="medicine-line">
            <div>${thaiDigits(index + 1)}.</div>
            <div>${escapeHtml(item.name)}</div>
            <div class="text-right">${escapeHtml(formatThaiQuantityDisplay(item.quantity))}</div>
            <div>${escapeHtml(item.unit)}</div>
        </div>
    `).join('');
}

function updateOverflowMarker() {
    return;
}

function charRect(node, index) {
    const range = document.createRange();
    range.setStart(node, index);
    range.setEnd(node, index + 1);
    const rect = Array.from(range.getClientRects()).find((candidate) => candidate.width > 0 || candidate.height > 0) || null;
    range.detach?.();
    return rect;
}

function normalizeLeadText(text) {
    return text.replace(/\s+/g, ' ').trim();
}

function shortLeadPreview(text, maxChars = 26) {
    const normalized = normalizeLeadText(text);
    if (!normalized) return '';
    if (normalized.length <= maxChars) return normalized;
    return `${normalized.slice(0, maxChars).trim()}...`;
}

function isLeadWordChar(char) {
    return /[\p{L}\p{N}]/u.test(char || '');
}

function cleanLeadText(text) {
    const normalized = normalizeLeadText(text);
    return normalized.replace(/^[^A-Za-z0-9ก-๙]+/u, '').trim();
}

function extractNextPageLeadLine(page, breakTop) {
    const nodes = getReadableTextNodes(page);
    const threshold = breakTop - 0.5;
    let startNodeIndex = -1;
    let startCharIndex = -1;

    for (let i = 0; i < nodes.length; i++) {
        const node = nodes[i];
        const text = node.nodeValue || '';
        if (text.length === 0) continue;
        const nodeRange = document.createRange();
        nodeRange.selectNodeContents(node);
        const rects = Array.from(nodeRange.getClientRects()).filter((rect) => rect.width > 0 || rect.height > 0);
        nodeRange.detach?.();
        if (!rects.length) continue;
        const maxBottom = Math.max(...rects.map((rect) => rect.bottom));
        if (maxBottom < threshold) continue;

        const begin = Math.max(0, text.search(/\S/));
        for (let j = begin; j < text.length; j++) {
            if (!isLeadWordChar(text[j])) continue;
            const rect = charRect(node, j);
            if (!rect) continue;
            if (rect.top >= threshold) {
                startNodeIndex = i;
                startCharIndex = j;
                break;
            }
        }
        if (startNodeIndex >= 0) break;
    }

    if (startNodeIndex < 0) return '';

    let lineTop = null;
    let collected = '';
    for (let i = startNodeIndex; i < nodes.length; i++) {
        const node = nodes[i];
        const text = node.nodeValue || '';
        let j = i === startNodeIndex ? startCharIndex : 0;
        for (; j < text.length; j++) {
            const rect = charRect(node, j);
            if (!rect || rect.top < threshold) continue;
            if (lineTop === null) lineTop = rect.top;
            if (Math.abs(rect.top - lineTop) <= 1.2) {
                collected += text[j];
                continue;
            }
            return normalizeLeadText(collected);
        }
    }
    return normalizeLeadText(collected);
}

function convertTextNodeDigitsToThai(root = document) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parentTag = node.parentElement?.tagName;
            if (!parentTag) return NodeFilter.FILTER_REJECT;
            if (['SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT', 'SELECT', 'OPTION'].includes(parentTag)) {
                return NodeFilter.FILTER_REJECT;
            }
            return /\d/.test(node.nodeValue || '') ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((node) => {
        node.nodeValue = thaiDigits(node.nodeValue || '');
    });
}

function bindTextFields(scope = document) {
    scope.querySelectorAll('[data-field]').forEach((input) => {
        if (input.dataset.bound === '1') return;
        input.dataset.bound = '1';
        const update = () => {
            const converted = thaiDigits(input.value);
            input.value = converted;
            document.querySelectorAll(`[data-bind="${input.dataset.field}"]`).forEach((target) => {
                if (input.tagName === 'TEXTAREA') {
                    target.innerHTML = escapeHtml(converted).replaceAll('\n', '<br>');
                } else {
                    target.textContent = converted;
                }
            });
            if (input.dataset.field === 'plan_revision_note') {
                document.querySelectorAll('[data-plan-revision-wrapper]').forEach((target) => {
                    target.classList.toggle('hidden', converted.trim() === '');
                });
            }
        };
        input.addEventListener('input', update);
        update();
    });
}

function showMedicineResults(row, query) {
    const results = row.querySelector('.medicine-results');
    const hidden = row.querySelector('.medicine-id');
    const term = query.trim().toLocaleLowerCase('th-TH');
    if (term.length < 3) {
        results.classList.add('hidden');
        results.innerHTML = '';
        return;
    }
    const matches = medicines
        .filter((medicine) => `${medicine.name || ''} ${medicine.unit || ''}`.toLocaleLowerCase('th-TH').includes(term))
        .slice(0, 12);
    hidden.value = '';
    results.innerHTML = matches.length ? matches.map((medicine) => `
        <button type="button" class="medicine-choice block w-full border-b border-zinc-800 px-3 py-2 text-left text-sm hover:bg-emerald-500/10" data-id="${escapeHtml(medicine.id)}">
            <span class="block text-zinc-100">${escapeHtml(medicine.name)}</span>
            <span class="block text-xs text-zinc-400">${escapeHtml(medicine.unit || '')}</span>
        </button>
    `).join('') : '<div class="px-3 py-2 text-sm text-zinc-400">ไม่พบรายการยา</div>';
    results.classList.remove('hidden');
    setActiveMedicineChoice(row, 0);
}

function medicineChoices(row) {
    return Array.from(row.querySelectorAll('.medicine-choice'));
}

function setActiveMedicineChoice(row, index) {
    const choices = medicineChoices(row);
    choices.forEach((choice, choiceIndex) => {
        const active = choiceIndex === index;
        choice.classList.toggle('bg-emerald-500/20', active);
        choice.classList.toggle('text-emerald-100', active);
        choice.setAttribute('aria-selected', active ? 'true' : 'false');
        if (active) choice.scrollIntoView({ block: 'nearest' });
    });
    row.dataset.activeMedicineIndex = choices.length ? String(index) : '-1';
}

function moveActiveMedicineChoice(row, direction) {
    const choices = medicineChoices(row);
    if (!choices.length) return;
    const current = Number(row.dataset.activeMedicineIndex ?? -1);
    const next = current < 0
        ? (direction > 0 ? 0 : choices.length - 1)
        : (current + direction + choices.length) % choices.length;
    setActiveMedicineChoice(row, next);
}

function selectMedicineChoice(row, button) {
    if (!button) return;
    const medicine = medicines.find((item) => String(item.id) === String(button.dataset.id));
    if (!medicine) return;
    row.querySelector('.medicine-id').value = medicine.id;
    row.querySelector('.medicine-search').value = medicine.name || '';
    row.querySelector('.unit').value = thaiDigits(medicine.unit || '');
    row.querySelector('.medicine-results').classList.add('hidden');
    renderMedicineItems();
}

function selectActiveMedicineChoice(row) {
    const choices = medicineChoices(row);
    const activeIndex = Number(row.dataset.activeMedicineIndex ?? -1);
    selectMedicineChoice(row, choices[activeIndex] || choices[0]);
}

function handleMedicineSearchKeydown(row, event) {
    const results = row.querySelector('.medicine-results');
    const isOpen = results && !results.classList.contains('hidden');
    if (event.key === 'Enter') {
        event.preventDefault();
        if (isOpen) selectActiveMedicineChoice(row);
        return;
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!isOpen) showMedicineResults(row, event.target.value);
        moveActiveMedicineChoice(row, 1);
        return;
    }
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!isOpen) showMedicineResults(row, event.target.value);
        moveActiveMedicineChoice(row, -1);
        return;
    }
    if (event.key === 'Escape') {
        results?.classList.add('hidden');
    }
}

function bindMedicineRow(row) {
    row.querySelectorAll('input').forEach((input) => {
        input.addEventListener('input', () => {
            if (input.classList.contains('quantity') || input.classList.contains('unit')) input.value = thaiDigits(input.value);
            renderMedicineItems();
        });
    });
    row.querySelector('.medicine-search')?.addEventListener('input', (event) => showMedicineResults(row, event.target.value));
    row.querySelector('.medicine-search')?.addEventListener('focus', (event) => showMedicineResults(row, event.target.value));
    row.querySelector('.medicine-search')?.addEventListener('keydown', (event) => handleMedicineSearchKeydown(row, event));
    row.querySelector('.medicine-results')?.addEventListener('click', (event) => {
        const button = event.target.closest('.medicine-choice');
        selectMedicineChoice(row, button);
    });
    row.querySelector('.medicine-results')?.addEventListener('mouseover', (event) => {
        const button = event.target.closest('.medicine-choice');
        if (!button) return;
        setActiveMedicineChoice(row, medicineChoices(row).indexOf(button));
    });
}

function addMedicineRow(medicine = null) {
    const item = medicine || fallbackMedicine;
    const wrapper = document.createElement('div');
    wrapper.className = 'medicine-row rounded-md border border-zinc-800 bg-zinc-950 p-3';
    wrapper.innerHTML = `
        <div class="mb-2 text-xs font-medium text-emerald-300">รายการที่ <span class="medicine-row-number"></span></div>
        <div class="grid gap-2 xl:grid-cols-[minmax(0,1fr)_5.5rem_6rem_auto] xl:items-end">
            <label class="block min-w-0">
                <span class="text-xs text-zinc-400">ชื่อยา</span>
                <input type="hidden" name="medicine_id[]" class="medicine-id" value="${escapeHtml(item.id || '')}">
                <input type="text" name="medicine_name[]" class="medicine-search mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400" value="${escapeHtml(item.name || '')}" placeholder="พิมพ์ชื่อยาอย่างน้อย 3 ตัว">
                <div class="medicine-results hidden max-h-52 overflow-auto rounded-md border border-zinc-700 bg-zinc-950"></div>
            </label>
            <label class="block">
                <span class="text-xs text-zinc-400">จำนวน</span>
                <input name="quantity[]" class="quantity mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-right text-sm outline-none focus:border-emerald-400">
            </label>
            <label class="block">
                <span class="text-xs text-zinc-400">หน่วย</span>
                <input name="unit[]" class="unit mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm outline-none focus:border-emerald-400" value="${escapeHtml(item.unit || '')}">
            </label>
            <button type="button" class="remove-medicine rounded-md border border-red-500/50 px-3 py-2 text-xs text-red-200 hover:bg-red-500/10">ลบ</button>
        </div>
    `;
    document.getElementById('medicine_rows').appendChild(wrapper);
    bindMedicineRow(wrapper);
    renderMedicineItems();
}

document.getElementById('add_medicine')?.addEventListener('click', () => addMedicineRow({ name: '', unit: '', id: '' }));
document.getElementById('medicine_rows')?.addEventListener('click', (event) => {
    const button = event.target.closest('.remove-medicine');
    if (!button) return;
    button.closest('.medicine-row')?.remove();
    renderMedicineItems();
});
document.addEventListener('click', (event) => {
    currentMedicineRows().forEach((row) => {
        if (!row.contains(event.target)) row.querySelector('.medicine-results')?.classList.add('hidden');
    });
});
document.getElementById('open_committee_modal')?.addEventListener('click', openCommitteeModal);
document.getElementById('close_committee_modal')?.addEventListener('click', closeCommitteeModal);
document.getElementById('committee_modal')?.addEventListener('click', (event) => {
    if (event.target.id === 'committee_modal') closeCommitteeModal();
    const choice = event.target.closest('.committee-set-choice');
    if (!choice) return;
    document.querySelectorAll('.committee-set-choice').forEach((button) => {
        button.classList.toggle('border-emerald-400', button === choice);
        button.classList.toggle('bg-emerald-500/10', button === choice);
    });
    const set = committeeSets[choice.dataset.id];
    renderCommitteePreview(set);
    fillCommitteeSetForm(set);
});
document.getElementById('apply_committee_set')?.addEventListener('click', applySelectedCommitteeSet);
document.getElementById('cancel_committee_set_edit')?.addEventListener('click', resetCommitteeSetForm);
document.getElementById('committee_set_form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    data.set('action', 'save_committee_set_ajax');
    const submit = document.getElementById('committee_set_submit');
    submit.disabled = true;
    submit.textContent = 'กำลังบันทึก...';
    try {
        const response = await fetch('principle_approval_memo.php', { method: 'POST', body: data });
        const result = await response.json();
        if (!result.ok || !result.item) {
            showCommitteeSetMessage(result.message || 'บันทึกชุดกรรมการไม่สำเร็จ', 'error');
            return;
        }
        committeeSets[String(result.item.id)] = result.item;
        appendCommitteeSetChoice(result.item);
        renderCommitteePreview(result.item);
        fillCommitteeSetForm(result.item);
        const activeButton = document.querySelector(`.committee-set-choice[data-id="${result.item.id}"]`);
        document.querySelectorAll('.committee-set-choice').forEach((button) => {
            button.classList.toggle('border-emerald-400', button === activeButton);
            button.classList.toggle('bg-emerald-500/10', button === activeButton);
        });
        showCommitteeSetMessage('บันทึกการแก้ไขชุดกรรมการแล้ว');
    } catch (error) {
        showCommitteeSetMessage('เชื่อมต่อเพื่อบันทึกไม่สำเร็จ กรุณาลองใหม่', 'error');
    } finally {
        submit.disabled = false;
        submit.textContent = form.elements.namedItem('committee_set_id')?.value ? 'บันทึกการแก้ไข' : 'เพิ่มชุดกรรมการ';
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeCommitteeModal();
});

bindTextFields();
bindBudgetAmount();
document.querySelectorAll('[data-thai-date-picker]').forEach(setupThaiDatePicker);
currentMedicineRows().forEach(bindMedicineRow);
renderMedicineItems();
convertTextNodeDigitsToThai(document.getElementById('memo_page_1') || document);
</script>
</body>
</html>
