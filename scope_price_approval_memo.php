<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require_once __DIR__ . '/core/scope_price_approval_store.php';
require __DIR__ . '/core/word_export_lib.php';
require_once __DIR__ . '/core/scope_price_template_export.php';
require __DIR__ . '/core/auth.php';
requireLogin();

/** เอกสารใหม่เป็นบันทึกแยกของชุดงาน: ไม่เปลี่ยนข้อมูล TOR และเอกสารอื่น */
const SCOPE_APPROVAL_DOCUMENT_TYPE = 'scope_price_approval_memo';

function scopeApprovalH($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function scopeApprovalThai($value): string { return strtr((string)$value, ['0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔','5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙']); }
function scopeApprovalArabic($value): string { return strtr((string)$value, ['๐'=>'0','๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4','๕'=>'5','๖'=>'6','๗'=>'7','๘'=>'8','๙'=>'9']); }
function scopeApprovalExpand(array $v, $text): string {
    $subs = [];
    foreach (['reference_no','reference_date','budget_amount','budget_amount_text','medicine_count','meeting_date','approval_amount','approval_amount_text'] as $key) {
        $value = trim((string)($v[$key] ?? ''));
        $subs['{{'.$key.'}}'] = $value !== '' ? $value : '................';
    }
    return strtr((string)$text, $subs);
}
function scopeApprovalParagraph($value): string { return scopeApprovalH(scopeApprovalThai(implode(' ', scopeApprovalLogicalParagraphs((string)$value)))); }
function scopeApprovalPreviewField(array $values, string $key): string {
    $text = scopeApprovalThai(scopeApprovalExpand($values, (string)($values[$key] ?? '')));
    $html = '';
    foreach (scopeApprovalLogicalParagraphs($text) as $paragraph) {
        $html .= '<p class="scope-paragraph">' . scopeApprovalH($paragraph) . '</p>';
    }
    return $html;
}
function scopeApprovalMoney($value): string {
    $value = trim(scopeApprovalArabic((string)$value));
    if (!is_numeric(str_replace(',', '', $value))) return $value;
    return number_format((float)str_replace(',', '', $value), 2);
}
function scopeApprovalDate($value): string { return scopeApprovalThai(trim((string)$value)); }
function scopeApprovalFilename(array $values): string {
    return torDocumentFilename((int)($values['_filename_case_id'] ?? 0), 4, (string)($values['doc_date'] ?? ''), (string)($values['memo_no_suffix'] ?? ''), (string)($values['_filename_supplier'] ?? ''));
}
function scopeApprovalCss(bool $forBrowser = false): string {
    // Keep original Word-export CSS, but only shared typography in the browser.
    // The browser has ONE dedicated named @page below; no competing unnamed @page.
    $wordPageCss = <<<'CSS'
/* หัวกระดาษ A4: หน้าแรกไม่แสดงเลขหน้า หน้าถัดไปนับเลขไทยอัตโนมัติ */
@counter-style scope-thai-page {system:numeric;symbols:"๐" "๑" "๒" "๓" "๔" "๕" "๖" "๗" "๘" "๙";suffix:"";}
@page {
  size:A4;
  margin:15mm 20mm 20mm 30mm;
  @top-center {content:"-" counter(page,scope-thai-page) "-";font-family:'TH Sarabun New','TH SarabunIT๙',sans-serif;font-size:16pt;font-weight:normal;}
}
@page :first {@top-center {content:none;}}
/* เฉพาะพิมพ์ผ่านเบราว์เซอร์: เลขหน้าสูง ๑๖ pt และเว้นเนื้อหาหน้าถัดไปอย่างน้อยหนึ่งบรรทัด
   ไม่เปลี่ยนขอบบน ๑.๕ ซม. ของหน้าแรก และไม่เปลี่ยน @page ที่ส่งให้ Word */
@media print {
  @page {margin-top:28mm;}
  @page :first {margin-top:15mm;}
}
CSS;
    $sharedCss = <<<'CSS'
.scope-paper{width:auto;background:#fff;color:#111;font-family:'TH SarabunIT๙','TH Sarabun New',sans-serif;font-size:16pt;line-height:1.07;}
.scope-paper *{box-sizing:border-box;}
.scope-page{page-break-after:always;min-height:247mm;position:relative;font-size:16pt;line-height:1.06;}
.scope-page:last-child{page-break-after:auto;}
.scope-masthead{width:100%;border-collapse:collapse;margin:0;}
.scope-masthead td{border:0;vertical-align:top;padding:0;}
.scope-masthead .scope-emblem{height:1.5cm;width:auto;}
.scope-masthead .scope-heading{font-size:29pt;font-weight:bold;text-align:center;padding-top:7pt;}
.scope-topline{margin:2pt 0;line-height:1.12;}
.scope-topline strong{font-size:19pt;}
.scope-sectitle{font-weight:bold;text-align:left;margin:4pt 0 2pt 2.5cm;}
.scope-sectitle u{text-decoration:underline;text-decoration-color:#111;text-decoration-thickness:1pt;text-underline-offset:1pt;}
.scope-paragraph{white-space:normal;text-align:left;text-justify:auto;text-indent:2.5cm;margin:0;line-height:1.0;}
.scope-paragraph.no-indent{text-indent:0;}
.scope-paragraph-group{margin:0;padding:0;}
.scope-paragraph-group > .scope-paragraph{margin:0;}
.scope-number{text-align:center;margin:0 0 5pt;font-size:16pt;}
.scope-signatures{margin-top:6pt;text-align:center;page-break-inside:avoid;break-inside:avoid;}
.scope-signatures table{width:100%;border-collapse:collapse;}
.scope-signatures tr{page-break-inside:avoid;break-inside:avoid;}
.scope-signatures td{text-align:center;border:0;vertical-align:top;width:50%;padding:2pt 3pt;}
.scope-signatures .name{white-space:pre-line;}
.scope-approval-block{padding-top:9pt;margin-left:2.5cm;}
.scope-approval-block h3{font-weight:bold;font-size:16pt;margin:0 0 3pt;}
.scope-approval-block p{margin:1pt 0;}
.scope-approval-signature{text-align:center;margin:38pt 0 14pt 0;white-space:pre-line;}
.scope-tail{margin-top:10pt;text-align:right;}
@media print{.scope-page{box-shadow:none!important;}}
CSS;
    return ($forBrowser ? '' : $wordPageCss . "\n") . $sharedCss;
}
/**
 * The DOCX importer does not consistently preserve CSS rules scoped to
 * .scope-paper.  Inline the actual run font and size for Word only; keep the
 * browser/print preview and the underlying form fields untouched.
 * 16 pt body, 18 pt government memo labels, 29 pt memo title.
 */
function scopeApprovalWordTypography(string $html): string
{
    $font = "TH SarabunIT๙";
    $attributeCss = "font-family:'" . $font . "';"
        . "mso-ascii-font-family:'" . $font . "';"
        . "mso-hansi-font-family:'" . $font . "';"
        . "mso-fareast-font-family:'" . $font . "';"
        . "mso-bidi-font-family:'" . $font . "';";
    return (string) preg_replace_callback(
        '/<(p|div|span|strong|td|th|table|h3|u)\\b([^>]*)>/iu',
        static function (array $match) use ($attributeCss): string {
            $tag = strtolower($match[1]);
            $attrs = $match[2];
            $size = '16';
            if (preg_match('/\\bclass\\s*=\\s*["\\\']([^"\\\']*)["\\\']/iu', $attrs, $classes)) {
                if (str_contains(' ' . $classes[1] . ' ', ' scope-heading ')) {
                    $size = '29';
                }
            }
            // Preserve the government memo's larger inline labels and title.
            if ($tag === 'strong') $size = '18';
            if (preg_match('/\\bstyle\\s*=\\s*["\\\']([^"\\\']*)["\\\']/iu', $attrs, $oldStyle)) {
                if (preg_match('/font-size\\s*:\\s*(\\d+(?:\\.\\d+)?)pt/iu', $oldStyle[1], $fontSize)) {
                    $size = $fontSize[1];
                }
            }
            $css = $attributeCss . 'font-size:' . $size . 'pt;';
            if (in_array($tag, ['p', 'td', 'th', 'h3'], true)) {
                $css .= 'line-height:1.08;';
            }
            if (preg_match('/\\bstyle\\s*=\\s*["\\\']([^"\\\']*)["\\\']/iu', $attrs)) {
                $attrs = preg_replace_callback(
                    '/\\bstyle\\s*=\\s*(["\\\'])(.*?)\\1/iu',
                    static fn(array $style): string => 'style=' . $style[1] . rtrim($style[2], '; ') . ';' . $css . $style[1],
                    $attrs,
                    1
                ) ?? $attrs;
            } else {
                $attrs .= ' style="' . $css . '"';
            }
            return '<' . $match[1] . $attrs . '>';
        },
        $html
    );
}

/** Keep Word's import font rules distinct from the browser's @page rules. */
function scopeApprovalWordFontCss(): string
{
    return <<<'CSS'
.scope-paper,.scope-paper p,.scope-paper div,.scope-paper span,
.scope-paper td,.scope-paper th,.scope-paper table,.scope-paper strong,
.scope-paper h3,.scope-paper u {
    font-family:"TH SarabunIT๙","TH Sarabun New",sans-serif !important;
    mso-ascii-font-family:"TH SarabunIT๙";
    mso-hansi-font-family:"TH SarabunIT๙";
    mso-fareast-font-family:"TH SarabunIT๙";
    mso-bidi-font-family:"TH SarabunIT๙";
}
.scope-paper,.scope-paper p,.scope-paper div,.scope-paper span,
.scope-paper td,.scope-paper th,.scope-paper table,
.scope-paper h3,.scope-paper u {font-size:16pt;}
.scope-paper .scope-heading,.scope-paper .scope-heading * {font-size:29pt !important;}
.Section1 .scope-paper .scope-topline strong,
.Section1 .scope-paper table strong {font-size:18pt !important;}
.Section1 .scope-paper p.scope-paragraph {
    font-family:'TH SarabunIT๙' !important;
    font-size:16pt !important;
    text-indent:70.87pt !important;
    text-align:justify !important;
    text-justify:distribute !important;
    -ms-text-justify:distribute !important;
    text-align-last:left !important;
    line-height:16pt !important;
    mso-line-height-rule:exactly;
}
.Section1 .scope-paper p.scope-sectitle {
    font-size:16pt !important;
    font-weight:bold;
    text-align:left !important;
    margin:4pt 0 2pt 70.87pt !important;
    text-indent:0 !important;
}
.Section1 .scope-paper .scope-approval-block {margin-left:70.87pt !important;}
CSS;
}

function scopeApprovalRender(array $v, bool $forWord = false): string {
    $h = static fn(string $key): string => scopeApprovalParagraph(scopeApprovalExpand($v, $v[$key] ?? ''));
    $subject = 'ขออนุมัติใช้รายละเอียดคุณลักษณะเฉพาะและราคากลาง สำหรับการจัดซื้อยา เพื่อใช้ในการรักษาพยาบาลประชาชนของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล';
    $subject = $v['subject'] ?? $subject;
    // Word's HTML importer does not reliably transfer class-based paragraph indents.
    // Set paragraph-specific Word styles only; leave the browser preview unchanged.
    $wordParagraphAttrs = $forWord
        ? ' lang="th" style="margin:0;text-indent:70.87pt;line-height:16pt;mso-line-height-rule:exactly;text-align:left;text-justify:auto;text-align-last:left;letter-spacing:normal;word-spacing:normal"'
        : '';
    $wordHeadingAttrs = $forWord
        ? ' lang="th" style="font-size:16pt;font-weight:bold;margin:4pt 0 2pt 70.87pt;text-align:left;text-decoration:underline;line-height:16pt;mso-line-height-rule:exactly"'
        : '';
    $wordApprovalAttrs = $forWord
        ? ' style="margin-left:70.87pt;padding-top:9pt;font-size:16pt;line-height:16pt;mso-line-height-rule:exactly"'
        : '';
    $garudaSrc = 'picture/picture-1.png';
    if ($forWord) {
        $garudaBytes = @file_get_contents(__DIR__ . '/picture/picture-1.png');
        if ($garudaBytes !== false) {
            $garudaSrc = 'data:image/png;base64,' . base64_encode($garudaBytes);
        }
    }
    ob_start(); ?>
<div class="scope-paper">
    <section class="scope-page">
        <table class="scope-masthead"><tr><td style="width:3cm"><img src="<?= scopeApprovalH($garudaSrc) ?>" class="scope-emblem" alt="ตราครุฑ"></td><td class="scope-heading memo-title" style="font-size:29pt;font-weight:bold;text-align:center;padding-top:7pt">บันทึกข้อความ</td><td style="width:3cm">&nbsp;</td></tr></table>
        <div class="scope-topline"><strong>ส่วนราชการ</strong> <span data-scope-out="office"><?= $h('office') ?></span></div>
        <table style="width:100%;border-collapse:collapse"><tr><td style="width:55%;padding:0;border:0"><strong style="font-size:<?= $forWord ? '18pt' : '19pt' ?>">ที่</strong> ชม ๕๑๐๐๖.๑๓.๑/<span data-scope-out="memo_no_suffix"><?= $h('memo_no_suffix') ?></span></td><td style="padding:0;border:0"><strong style="font-size:<?= $forWord ? '18pt' : '19pt' ?>">วันที่</strong> <span data-scope-out="doc_date"><?= $h('doc_date') ?></span></td></tr></table>
        <div class="scope-topline"><strong>เรื่อง</strong> <span data-scope-out="subject"><?= scopeApprovalParagraph($subject) ?></span></div>
        <div class="scope-topline" style="margin-top:6pt"><strong>เรียน</strong> <span data-scope-out="recipient"><?= $h('recipient') ?></span></div>
        <p class="scope-sectitle"<?= $wordHeadingAttrs ?>><u>เรื่องเดิม</u></p>
        <div class="scope-paragraph-group" data-scope-out="background_1"><?= scopeApprovalPreviewField($v, 'background_1') ?></div>
        <div class="scope-paragraph-group" data-scope-out="background_2"><?= scopeApprovalPreviewField($v, 'background_2') ?></div>
        <p class="scope-sectitle"<?= $wordHeadingAttrs ?>><u>ข้อเท็จจริง</u></p>
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="scope-paragraph-group" data-scope-out="fact_<?= $i ?>"><?= scopeApprovalPreviewField($v, 'fact_'.$i) ?></div>
        <?php endfor; ?>
        <p class="scope-sectitle"<?= $wordHeadingAttrs ?>><u>ข้อระเบียบ</u></p>
        <?php for ($i = 1; $i <= 6; $i++): ?>
            <div class="scope-paragraph-group" data-scope-out="law_<?= $i ?>"><?= scopeApprovalPreviewField($v, 'law_'.$i) ?></div>
        <?php endfor; ?>
        <p class="scope-sectitle"<?= $wordHeadingAttrs ?>><u>ข้อพิจารณา</u></p>
        <div class="scope-paragraph-group" data-scope-out="consideration_intro"><?= scopeApprovalPreviewField($v, 'consideration_intro') ?></div>
        <div class="scope-paragraph-group" data-scope-out="consideration_1"><?= scopeApprovalPreviewField($v, 'consideration_1') ?></div>
        <div class="scope-paragraph-group" data-scope-out="consideration_2"><?= scopeApprovalPreviewField($v, 'consideration_2') ?></div>
        <div class="scope-paragraph-group" data-scope-out="conclusion"><?= scopeApprovalPreviewField($v, 'conclusion') ?></div>
        <div class="scope-signatures"><table><tr><td colspan="2">(ลงชื่อ)..............................................ประธานกรรมการ<br><span class="name" data-scope-out="chair_name"><?= $h('chair_name') ?></span><br><span data-scope-out="chair_position"><?= $h('chair_position') ?></span></td></tr><tr>
        <td>(ลงชื่อ)......................กรรมการ<br><span data-scope-out="member1_name"><?= $h('member1_name') ?></span><br><span data-scope-out="member1_position"><?= $h('member1_position') ?></span></td>
        <td>(ลงชื่อ)......................กรรมการ<br><span data-scope-out="member2_name"><?= $h('member2_name') ?></span><br><span data-scope-out="member2_position"><?= $h('member2_position') ?></span></td></tr></table></div>
    </section>
    <section class="scope-page" style="page-break-after:auto">
        <div class="scope-number">-๔-</div>
        <div class="scope-approval-block"<?= $wordApprovalAttrs ?>><h3 data-scope-out="director_title"><?= $h('director_title') ?></h3>
            <p><span data-scope-checked="director_decision:approved"><?= ($v['director_decision'] ?? '') === 'approved' ? '☑' : '☐' ?></span> เห็นชอบ</p>
            <p><span data-scope-checked="director_decision:other"><?= ($v['director_decision'] ?? '') === 'other' ? '☑' : '☐' ?></span> อื่น ๆ <span data-scope-out="director_other"><?= $h('director_other') ?></span></p>
        </div>
        <div class="scope-approval-signature">(<span data-scope-out="director_name"><?= $h('director_name') ?></span>)
            <div data-scope-out="director_position"><?= $h('director_position') ?></div>
        </div>
        <div class="scope-approval-block"<?= $wordApprovalAttrs ?>><h3 data-scope-out="executive_title"><?= $h('executive_title') ?></h3>
            <p><span data-scope-checked="executive_decision:approved"><?= ($v['executive_decision'] ?? '') === 'approved' ? '☑' : '☐' ?></span> อนุมัติ</p>
            <p><span data-scope-checked="executive_decision:rejected"><?= ($v['executive_decision'] ?? '') === 'rejected' ? '☑' : '☐' ?></span> ไม่อนุมัติ</p>
        </div>
        <div class="scope-approval-signature">(<span data-scope-out="executive_name"><?= $h('executive_name') ?></span>)
            <div data-scope-out="executive_position"><?= $h('executive_position') ?></div>
        </div>
    </section>
</div>
<?php return (string)ob_get_clean();
}

/** Legacy native Word fallback retained for compatibility; the Word action uses the validated DOCX template. */
function scopeApprovalNativeXml(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function scopeApprovalNativeRun(string $text, int $size = 16, bool $bold = false, bool $underline = false): string
{
    $font = scopeApprovalNativeXml(WORD_PRIMARY_FONT);
    $properties = '<w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font
        . '" w:eastAsia="' . $font . '" w:cs="' . $font . '"/>'
        . '<w:sz w:val="' . ($size * 2) . '"/><w:szCs w:val="' . ($size * 2) . '"/>'
        . '<w:lang w:val="th-TH" w:eastAsia="th-TH" w:bidi="th-TH"/>';
    if ($bold) $properties .= '<w:b/><w:bCs/>';
    if ($underline) $properties .= '<w:u w:val="single"/>';
    return '<w:r><w:rPr>' . $properties . '</w:rPr><w:t xml:space="preserve">'
        . scopeApprovalNativeXml($text) . '</w:t></w:r>';
}

function scopeApprovalNativeParagraph(
    string $runs,
    string $alignment = 'left',
    int $firstIndent = 0,
    int $leftIndent = 0,
    int $before = 0,
    int $after = 0,
    bool $keepNext = false
): string {
    // Native OOXML thaiDistribute maps to Word's Thai Distributed, not CSS justify.
    $properties = '<w:spacing w:before="' . $before . '" w:after="' . $after
        . '" w:line="300" w:lineRule="atLeast"/>';
    if ($firstIndent > 0 || $leftIndent > 0) {
        $properties .= '<w:ind w:firstLine="' . $firstIndent . '" w:left="' . $leftIndent . '"/>';
    }
    $properties .= '<w:jc w:val="' . $alignment . '"/>';
    if ($keepNext) $properties .= '<w:keepNext/>';
    return '<w:p><w:pPr>' . $properties . '</w:pPr>' . $runs . '</w:p>';
}

function scopeApprovalNativeFlowLines(string $text): array
{
    // Text pasted from an existing memo often includes hard line endings at the
    // old page width. Merge those; keep only real list items or blank paragraphs.
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $parts = [];
    $current = '';
    foreach (explode("\n", $text) as $line) {
        $line = trim(preg_replace('/[\t ]+/u', ' ', $line) ?? $line);
        if ($line === '') {
            if ($current !== '') { $parts[] = $current; $current = ''; }
            continue;
        }
        $isNewItem = (bool) preg_match('/^(?:[๐-๙0-9]+[.)]|\([๐-๙0-9]+\)|[-•]\s)/u', $line);
        if ($isNewItem && $current !== '') {
            $parts[] = $current;
            $current = $line;
        } else {
            $current .= ($current === '' ? '' : ' ') . $line;
        }
    }
    if ($current !== '') $parts[] = $current;
    return $parts;
}

function scopeApprovalNativeTableCell(string $content, int $width, string $align = 'left'): string
{
    return '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/><w:vAlign w:val="center"/>'
        . '<w:tcBorders><w:top w:val="nil"/><w:bottom w:val="nil"/><w:left w:val="nil"/><w:right w:val="nil"/></w:tcBorders>'
        . '</w:tcPr>' . $content . '</w:tc>';
}

function scopeApprovalNativeTable(array $widths, array $rows): string
{
    $total = array_sum($widths);
    $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . $total . '" w:type="dxa"/>'
        . '<w:tblLayout w:type="fixed"/><w:tblBorders><w:top w:val="nil"/>'
        . '<w:bottom w:val="nil"/><w:left w:val="nil"/><w:right w:val="nil"/>'
        . '<w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr><w:tblGrid>';
    foreach ($widths as $width) $xml .= '<w:gridCol w:w="' . $width . '"/>';
    $xml .= '</w:tblGrid>';
    foreach ($rows as $cells) {
        $xml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
        foreach ($cells as $i => $content) {
            $xml .= scopeApprovalNativeTableCell($content, $widths[$i]);
        }
        $xml .= '</w:tr>';
    }
    return $xml . '</w:tbl>';
}

function scopeApprovalNativePicture(): string
{
    $cx = 488976; // 1.36 cm, preserving the original Garuda aspect ratio.
    $cy = 530060;
    return '<w:r><w:drawing><wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0">'
        . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:docPr id="1" name="Garuda" descr="ตราครุฑ"/>'
        . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
        . '<pic:nvPicPr><pic:cNvPr id="0" name="garuda.png"/><pic:cNvPicPr/></pic:nvPicPr>'
        . '<pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rIdScopeGaruda"/>'
        . '<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
        . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
        . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
        . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
}

function scopeApprovalNativeWordParts(array $v): array
{
    $body = '';
    $p = static fn(string $text, string $align = 'left', int $first = 0, int $left = 0, int $before = 0, int $after = 0, bool $bold = false, bool $under = false, bool $keep = false, int $size = 16): string =>
        scopeApprovalNativeParagraph(scopeApprovalNativeRun(scopeApprovalThai($text), $size, $bold, $under), $align, $first, $left, $before, $after, $keep);
    $field = static fn(string $name): string => scopeApprovalExpand($v, (string)($v[$name] ?? ''));

    $masthead = [
        scopeApprovalNativeParagraph(scopeApprovalNativePicture()),
        $p('บันทึกข้อความ', 'center', 0, 0, 0, 0, true, false, false, 29),
        scopeApprovalNativeParagraph(scopeApprovalNativeRun(' ', 16)),
    ];
    $body .= scopeApprovalNativeTable([1701, 5670, 1701], [$masthead]);
    $body .= scopeApprovalNativeParagraph(
        scopeApprovalNativeRun('ส่วนราชการ ', 18, true) . scopeApprovalNativeRun($field('office')),
        'left', 0, 0, 25, 15
    );
    $number = 'ชม ๕๑๐๐๖.๑๓.๑/' . $field('memo_no_suffix');
    $body .= scopeApprovalNativeTable([4900, 4172], [[
        scopeApprovalNativeParagraph(scopeApprovalNativeRun('ที่ ', 18, true) . scopeApprovalNativeRun($number)),
        scopeApprovalNativeParagraph(scopeApprovalNativeRun('วันที่ ', 18, true) . scopeApprovalNativeRun($field('doc_date'))),
    ]]);
    $body .= scopeApprovalNativeParagraph(scopeApprovalNativeRun('เรื่อง ', 18, true) . scopeApprovalNativeRun($field('subject')), 'left', 0, 0, 15, 15);
    $body .= scopeApprovalNativeParagraph(scopeApprovalNativeRun('เรียน ', 18, true) . scopeApprovalNativeRun($field('recipient')), 'left', 0, 0, 15, 85);

    $sections = [
        'เรื่องเดิม' => ['background_1', 'background_2'],
        'ข้อเท็จจริง' => ['fact_1', 'fact_2', 'fact_3', 'fact_4'],
        'ข้อระเบียบ' => ['law_1', 'law_2', 'law_3', 'law_4', 'law_5', 'law_6'],
        'ข้อพิจารณา' => ['consideration_intro', 'consideration_1', 'consideration_2', 'conclusion'],
    ];
    foreach ($sections as $heading => $fields) {
        $body .= $p($heading, 'left', 0, 1417, 120, 45, true, true, true);
        foreach ($fields as $key) {
            foreach (scopeApprovalNativeFlowLines($field($key)) as $line) {
                $body .= $p($line, 'thaiDistribute', 1417, 0, 0, 0);
            }
        }
    }
    // Signatures stay together. No body text is silently dropped from the document.
    $chair = $p('(ลงชื่อ)..............................................ประธานกรรมการ', 'center')
        . $p($field('chair_name'), 'center') . $p($field('chair_position'), 'center');
    $memberA = $p('(ลงชื่อ)......................กรรมการ', 'center')
        . $p($field('member1_name'), 'center') . $p($field('member1_position'), 'center');
    $memberB = $p('(ลงชื่อ)......................กรรมการ', 'center')
        . $p($field('member2_name'), 'center') . $p($field('member2_position'), 'center');
    // Keep the chair and both committee members on the same physical page.
    $body .= scopeApprovalNativeTable([9072], [[$chair
        . scopeApprovalNativeTable([4536, 4536], [[$memberA, $memberB]])]]);

    // Approval page is separate from the committee's recommendation.
    $body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    $body .= $p($field('director_title'), 'left', 0, 1417, 30, 80, true);
    $directorDecision = $field('director_decision');
    $body .= $p(($directorDecision === 'approved' ? '☑' : '☐') . ' เห็นชอบ', 'left', 0, 1417);
    $body .= $p(($directorDecision === 'other' ? '☑' : '☐') . ' อื่น ๆ ' . $field('director_other'), 'left', 0, 1417);
    $body .= $p('(' . $field('director_name') . ')', 'center', 0, 0, 600);
    foreach (scopeApprovalNativeFlowLines($field('director_position')) as $line) $body .= $p($line, 'center');
    $body .= $p($field('executive_title'), 'left', 0, 1417, 300, 80, true);
    $execDecision = $field('executive_decision');
    $body .= $p(($execDecision === 'approved' ? '☑' : '☐') . ' อนุมัติ', 'left', 0, 1417);
    $body .= $p(($execDecision === 'rejected' ? '☑' : '☐') . ' ไม่อนุมัติ', 'left', 0, 1417);
    $body .= $p('(' . $field('executive_name') . ')', 'center', 0, 0, 600);
    foreach (scopeApprovalNativeFlowLines($field('executive_position')) as $line) $body .= $p($line, 'center');

    $font = scopeApprovalNativeXml(WORD_PRIMARY_FONT);
    $section = '<w:sectPr><w:headerReference w:type="default" r:id="rIdScopeHeader"/>'
        . '<w:headerReference w:type="first" r:id="rIdScopeHeaderFirst"/><w:titlePg/>'
        . '<w:pgSz w:w="11906" w:h="16838"/>'
        . '<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1701" w:header="400" w:footer="0" w:gutter="0"/>'
        . '<w:pgNumType w:fmt="thaiNumbers" w:start="1"/></w:sectPr>';
    $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<w:body>' . $body . $section . '</w:body></w:document>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults>'
        . '<w:rPrDefault><w:rPr><w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font
        . '" w:eastAsia="' . $font . '" w:cs="' . $font . '"/><w:sz w:val="32"/><w:szCs w:val="32"/>'
        . '<w:lang w:val="th-TH" w:eastAsia="th-TH" w:bidi="th-TH"/></w:rPr></w:rPrDefault>'
        . '<w:pPrDefault><w:pPr><w:spacing w:before="0" w:after="0" w:line="300" w:lineRule="atLeast"/>'
        . '</w:pPr></w:pPrDefault></w:docDefaults>'
        . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/>'
        . '<w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr></w:style></w:styles>';
    $header = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        . '<w:p><w:pPr><w:jc w:val="center"/><w:spacing w:after="100"/></w:pPr>'
        . scopeApprovalNativeRun('- ', 16)
        . '<w:fldSimple w:instr=" PAGE \\* THAIARABIC "><w:r><w:rPr><w:rFonts w:ascii="' . $font . '"'
        . ' w:hAnsi="' . $font . '" w:eastAsia="' . $font . '"/><w:sz w:val="32"/></w:rPr>'
        . '<w:t>๒</w:t></w:r></w:fldSimple>'
        . scopeApprovalNativeRun(' -', 16) . '</w:p></w:hdr>';
    $emptyHeader = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p/></w:hdr>';
    return ['document' => $doc, 'styles' => $styles, 'header' => $header, 'firstHeader' => $emptyHeader];
}

function scopeApprovalOutputNativeWord(array $values): void
{
    $parts = scopeApprovalNativeWordParts($values);
    $filename = wordSafeFilename(scopeApprovalFilename($values));
    $tempPath = tempnam(sys_get_temp_dir(), 'scope_native_word_');
    if ($tempPath === false) throw new RuntimeException('ไม่สามารถสร้างไฟล์ Word ชั่วคราวได้');
    try {
        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ไม่สามารถสร้าง ZIP ภายใน Word ได้');
        }
        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Default Extension="png" ContentType="image/png"/>'
                . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
                . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
                . '<Override PartName="/word/header2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
                . '</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                . '</Relationships>');
            $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rIdScopeGaruda" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/garuda.png"/>'
                . '<Relationship Id="rIdScopeHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
                . '<Relationship Id="rIdScopeHeaderFirst" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header2.xml"/>'
                . '<Relationship Id="rIdScopeStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>');
            $zip->addFromString('word/document.xml', $parts['document']);
            $zip->addFromString('word/styles.xml', $parts['styles']);
            $zip->addFromString('word/header1.xml', $parts['header']);
            $zip->addFromString('word/header2.xml', $parts['firstHeader']);
            $garuda = @file_get_contents(__DIR__ . '/picture/picture-1.png');
            if (!is_string($garuda)) throw new RuntimeException('ไม่พบตราครุฑสำหรับ Word');
            $zip->addFromString('word/media/garuda.png', $garuda);
        } finally {
            $zip->close();
        }
        wordEmbedProjectFonts($tempPath);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="document.docx"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . (string)filesize($tempPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($tempPath);
    } finally {
        if (is_file($tempPath)) unlink($tempPath);
    }
    exit;
}

ensureProcurementTables();
if (!scopePriceApprovalInstalled()) {
    http_response_code(503);
    exit('ยังไม่พบตารางสำหรับเอกสารใหม่ กรุณานำเข้า database/migrations/20261124_scope_price_approval_memos.sql ในฐานข้อมูลนี้ก่อน');
}
$cases = procurementCases();
$caseId = (int)($_POST['case_id'] ?? $_GET['case_id'] ?? 0);
$activeCase = $caseId > 0 ? procurementCase($caseId) : null;
if ($caseId > 0 && !$activeCase) { http_response_code(404); exit('ไม่พบชุดเอกสารนี้'); }
$saved = $activeCase ? (scopePriceApprovalLoad($caseId) ?? []) : [];
$savedDocument = $saved;
$caseItems = is_array($activeCase['items'] ?? null) ? $activeCase['items'] : [];
$itemCount = scopeApprovalThai((string)count($caseItems));
$caseBudget = trim((string)($activeCase['budget_amount'] ?? ''));
$caseBudgetText = trim((string)($activeCase['budget_amount_text'] ?? ''));
$principleNo = scopeApprovalThai((string)($activeCase['principle_memo_no_suffix'] ?? ''));
$principleDate = scopeApprovalDate((string)($activeCase['principle_doc_date'] ?? ''));
$existingInvitation = $activeCase ? procurementDocument($caseId, 'committee_scope_invitation') : null;
$invExtra = is_array($existingInvitation['extra'] ?? null) ? $existingInvitation['extra'] : [];
$meetingDate = scopeApprovalDate((string)($invExtra['meeting_date'] ?? ''));
$defaultNo = (string)($savedDocument['memo_no_suffix'] ?? '');
$defaultDate = (string)($savedDocument['doc_date'] ?? '');
$defaultSigners = array_values(is_array($activeCase['committee'] ?? null) ? $activeCase['committee'] : []);
$signerName = static function (int $i) use ($defaultSigners): string { return trim((string)($defaultSigners[$i]['name'] ?? '')); };
$signerPosition = static function (int $i) use ($defaultSigners): string { return trim((string)($defaultSigners[$i]['position'] ?? '')); };

$defaults = [
    'memo_no_suffix' => $defaultNo,
    'doc_date' => $defaultDate,
    'office' => 'กองสาธารณสุข ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล อ.สันกำแพง โทร. ๐๕๓๓๓ ๑๓๖๒',
    'subject' => 'ขออนุมัติใช้รายละเอียดคุณลักษณะเฉพาะและราคากลาง สำหรับการจัดซื้อยา เพื่อใช้ในการรักษาพยาบาลประชาชนของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล',
    'recipient' => 'นายกองค์การบริหารส่วนจังหวัดเชียงใหม่',
    'reference_no' => $principleNo,
    'reference_date' => $principleDate,
    'budget_amount' => $caseBudget !== '' ? scopeApprovalMoney($caseBudget) : '',
    'budget_amount_text' => $caseBudgetText,
    'medicine_count' => $itemCount,
    'meeting_date' => $meetingDate,
    'approval_amount' => '',
    'approval_amount_text' => '',
    'background_1' => 'ตามบันทึกข้อความศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล ที่ ชม ๕๑๐๐๖.๑๓.๑/{{reference_no}} ลงวันที่ {{reference_date}} ได้ขออนุมัติในหลักการเพื่อดำเนินการจัดซื้อยาและได้อนุมัติแต่งตั้งคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง สำหรับการจัดซื้อยา',
    'background_2' => 'โดยให้คณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง มีอำนาจหน้าที่พิจารณากลั่นกรองและจัดทำ TOR : Term of Reference เพื่อกำหนดขอบเขตของงาน หรือรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง ให้เป็นไปตามวัตถุประสงค์ที่กำหนดไว้และกำหนดราคากลาง (ราคาอ้างอิง) ของงานที่จะจัดซื้อจัดจ้าง พร้อมทั้งรายงานผลต่อนายกองค์การบริหารส่วนจังหวัดเชียงใหม่',
    'fact_1' => '๑. ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล ได้อนุมัติงบประมาณในการจัดซื้อยาและเวชภัณฑ์ที่มิใช่ยา สนับสนุนการให้บริการสาธารณะระดับปฐมภูมิของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูลและเครือข่ายหน่วยบริการปฐมภูมิ อำเภอสันกำแพง จังหวัดเชียงใหม่ สังกัดองค์การบริหารส่วนจังหวัดเชียงใหม่ โดยใช้งบประมาณจากเงินบำรุงของโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง วงเงินงบประมาณ {{budget_amount}} บาท ({{budget_amount_text}}) ตามระเบียบกระทรวงมหาดไทย ว่าด้วยเงินบำรุงโรงพยาบาลและหน่วยบริการสาธารณสุขขององค์กรปกครองส่วนท้องถิ่น (ฉบับที่ ๒) พ.ศ. ๒๕๖๑',
    'fact_2' => '๒. คณะกรรมการกำหนดคุณลักษณะและราคากลาง (TOR : Term of Reference) ได้ปฏิบัติหน้าที่พิจารณากลั่นกรองและจัดทำ TOR เพื่อกำหนดขอบเขตของงาน หรือรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง ให้เป็นไปตามวัตถุประสงค์ที่กำหนดไว้ และกำหนดราคากลาง (ราคาอ้างอิง) ของงานที่จะจัดซื้อจัดจ้าง โดยถือปฏิบัติตามหนังสือคณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ด่วนที่สุด ที่ กค (กวจ) ๐๔๐๕.๒/ว ๗๘ ลงวันที่ ๓๑ มกราคม ๒๕๖๕ เรื่อง อนุมัติยกเว้นและกำหนดแนวทางปฏิบัติตามกฎกระทรวงกำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุที่รัฐต้องการส่งเสริมหรือสนับสนุน (ฉบับที่ ๒) พ.ศ. ๒๕๖๓ และตามหนังสือคณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ ด่วนที่สุด ที่ กค (กวจ) ๐๔๐๕.๔/ว ๑๕๙ ลงวันที่ ๒๐ มีนาคม ๒๕๖๖ เรื่อง แนวทางปฏิบัติในการจัดทำร่างรายละเอียดขอบเขตของงานทั้งโครงการ (Terms of Reference : TOR)',
    'fact_3' => '๓. ตามกฎกระทรวงกำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุที่รัฐต้องการส่งเสริมหรือสนับสนุน พ.ศ. ๒๕๖๓ ข้อ ๑๕ (๒) ยาในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยา ซึ่งองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหารได้ผลิตออกจำหน่ายแล้ว และข้อ ๑๗ ให้หน่วยงานของรัฐจัดซื้อพัสดุตามข้อ ๑๕ (๒) โดยวิธีเฉพาะเจาะจงจากองค์การเภสัชกรรมหรือสภากาชาดไทย เว้นแต่หน่วยงานในสังกัดกระทรวงกลาโหมให้จัดซื้อจากโรงงานเภสัชกรรมทหาร สำหรับหน่วยงานในสังกัดสำนักงานตำรวจแห่งชาติให้จัดซื้อจากองค์การเภสัชกรรมหรือโรงงานเภสัชกรรมทหารได้ ทั้งนี้ หน่วยงานของรัฐสามารถจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยาจากผู้ขายรายอื่นได้ หากองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหาร ไม่สามารถผลิตออกจำหน่ายให้แก่หน่วยงานของรัฐได้ทันกำหนดตามที่หน่วยงานของรัฐแจ้งแผนประจำปีล่วงหน้า ประกอบกับหนังสือคณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ที่ กค (กวจ) ๐๔๐๕.๒/ว ๑๑๙ ลงวันที่ ๑๒ มีนาคม ๒๕๖๒ ข้อ ๓.๑.๑',
    'fact_4' => '๔. คณะกรรมการฯ ได้ร่วมประชุม เมื่อวันที่ {{meeting_date}} โดยได้ตรวจสอบรายการเวชภัณฑ์ยาที่จะดำเนินการจัดซื้อ พบว่าเป็นยาในบัญชียาหลักแห่งชาติ จำนวน {{medicine_count}} รายการ ที่องค์การเภสัชกรรมมิได้จัดจำหน่าย จึงได้กำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อและกำหนดราคากลาง ปรากฏรายละเอียดตามเอกสารแนบ',
    'law_1' => '๑. พระราชบัญญัติการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. ๒๕๖๐ มาตรา ๔ ในพระราชบัญญัตินี้ “ราคากลาง” หมายความว่า ราคาเพื่อใช้เป็นฐานสำหรับเปรียบเทียบราคาที่ผู้ยื่นข้อเสนอได้ยื่นเสนอไว้ ซึ่งสามารถจัดซื้อจัดจ้างได้จริงตามลำดับ ดังต่อไปนี้\n(๑) ราคาที่ได้มาจากการคำนวณตามหลักเกณฑ์ที่คณะกรรมการราคากลางกำหนด\n(๒) ราคาที่ได้มาจากฐานข้อมูลราคาอ้างอิงของพัสดุที่กรมบัญชีกลางจัดทำ\n(๓) ราคามาตรฐานที่สำนักงบประมาณหรือหน่วยงานกลางอื่นกำหนด\n(๔) ราคาที่ได้มาจากการสืบราคาจากท้องตลาด\n(๕) ราคาที่เคยซื้อหรือจ้างครั้งหลังสุดภายในระยะเวลาสองปีงบประมาณ\n(๖) ราคาอื่นใดตามหลักเกณฑ์ วิธีการ หรือแนวทางปฏิบัติของหน่วยงานของรัฐนั้น ๆ\nในกรณีที่มีราคาตาม (๑) ให้ใช้ราคาตาม (๑) ก่อน หากไม่มีราคาตาม (๑) ให้ใช้ราคาตาม (๒) หรือ (๓) ก่อน โดยจะใช้ราคาใดตาม (๒) หรือ (๓) ให้คำนึงถึงประโยชน์ของหน่วยงานของรัฐเป็นสำคัญ ในกรณีที่ไม่มีราคาตาม (๑) (๒) และ (๓) ให้ใช้ราคาตาม (๔) (๕) หรือ (๖) โดยคำนึงถึงประโยชน์ของหน่วยงานของรัฐเป็นสำคัญ',
    'law_2' => '๒. หนังสือคณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ที่ กค (กวจ) ๐๔๐๕.๒/ว ๑๑๙ ลงวันที่ ๑๒ มีนาคม ๒๕๖๒ ข้อ ๓.๑.๑ ให้หน่วยงานของรัฐจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยาซึ่งองค์การเภสัชกรรมหรือสภากาชาดไทยได้ผลิตออกจำหน่ายแล้วโดยวิธีเฉพาะเจาะจงจากองค์การเภสัชกรรมหรือสภากาชาดไทย เว้นแต่หน่วยงานในสังกัดกระทรวงกลาโหมให้จัดซื้อจากโรงงานเภสัชกรรมทหาร สำหรับหน่วยงานในสังกัดสำนักงานตำรวจแห่งชาติให้จัดซื้อจากองค์การเภสัชกรรมหรือโรงงานเภสัชกรรมทหารได้ ทั้งนี้ หน่วยงานของรัฐสามารถจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยาจากผู้ขายรายอื่นได้ หากองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหารไม่สามารถผลิตออกจำหน่ายให้ทันกำหนดตามที่หน่วยงานได้แจ้งแผนประจำปีล่วงหน้า',
    'law_3' => '๓. กฎกระทรวงกำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุที่รัฐต้องการส่งเสริมหรือสนับสนุน พ.ศ. ๒๕๖๓ ข้อ ๑๕ ให้พัสดุส่งเสริมสุขภาพและสาธารณสุขดังต่อไปนี้เป็นพัสดุที่รัฐต้องการส่งเสริมหรือสนับสนุน\n(๑) ยาในบัญชียาหลักแห่งชาติ\n(๒) ยาในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยา ซึ่งองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหารได้ผลิตออกจำหน่ายแล้ว\nข้อ ๑๗ ให้หน่วยงานของรัฐจัดซื้อพัสดุตามข้อ ๑๕ (๒) โดยวิธีเฉพาะเจาะจงจากองค์การเภสัชกรรมหรือสภากาชาดไทย เว้นแต่หน่วยงานในสังกัดกระทรวงกลาโหมให้จัดซื้อจากโรงงานเภสัชกรรมทหาร สำหรับหน่วยงานในสังกัดสำนักงานตำรวจแห่งชาติให้จัดซื้อจากองค์การเภสัชกรรมหรือโรงงานเภสัชกรรมทหารได้ ทั้งนี้สามารถจัดซื้อจากผู้ขายรายอื่นได้ตามเงื่อนไขที่กำหนด',
    'law_4' => '๔. หนังสือกรมบัญชีกลาง ด่วนที่สุด ที่ กค ๐๔๓๓.๒/๒๐๖ ลงวันที่ ๑ พฤษภาคม ๒๕๖๒ เรื่อง คู่มือแนวทางการประกาศรายละเอียดข้อมูลราคากลางและการคำนวณราคากลางที่เกี่ยวกับการจัดซื้อจัดจ้างของหน่วยงานรัฐ',
    'law_5' => '๕. หนังสือคณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ ด่วนที่สุด ที่ กค (กวจ) ๐๔๐๕.๒/ว ๗๘ ลงวันที่ ๓๑ มกราคม ๒๕๖๕ เรื่อง อนุมัติยกเว้นและกำหนดแนวทางปฏิบัติตามกฎกระทรวงกำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุที่รัฐต้องการส่งเสริมหรือสนับสนุน (ฉบับที่ ๒) พ.ศ. ๒๕๖๓',
    'law_6' => '๖. หนังสือคณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ ด่วนที่สุด ที่ กค (กวจ) ๐๔๐๕.๔/ว ๑๕๙ ลงวันที่ ๒๐ มีนาคม ๒๕๖๖ เรื่อง แนวทางปฏิบัติในการจัดทำร่างรายละเอียดขอบเขตของงานทั้งโครงการ (Terms of Reference : TOR)',
    'consideration_intro' => 'เพื่อให้การดำเนินการงานจัดซื้อเวชภัณฑ์ยา เพื่อใช้ในการบริการทางการแพทย์ของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูลและเครือข่ายหน่วยบริการปฐมภูมิ เป็นไปด้วยความเรียบร้อย ถูกต้องตามระเบียบกฎหมายที่เกี่ยวข้อง และบรรลุตามวัตถุประสงค์ที่กำหนดไว้ เห็นควรพิจารณา ดังนี้',
    'consideration_1' => '๑. อนุมัติรายละเอียดคุณลักษณะเฉพาะและราคากลาง งานจัดซื้อเวชภัณฑ์ยาในบัญชียาหลักแห่งชาติ สนับสนุนการให้บริการสาธารณะระดับปฐมภูมิของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูลและเครือข่ายหน่วยบริการปฐมภูมิ จำนวน {{medicine_count}} รายการ วงเงินงบประมาณ {{approval_amount}} บาท ({{approval_amount_text}})',
    'consideration_2' => '๒. จัดส่งรายละเอียดคุณลักษณะเฉพาะและราคากลางงานจัดซื้อเวชภัณฑ์ยาดังกล่าวข้างต้น ให้เจ้าหน้าที่พัสดุเพื่อดำเนินการจัดหาพัสดุตามระเบียบฯ ต่อไป',
    'conclusion' => 'จึงเรียนมาเพื่อโปรดพิจารณาอนุมัติ',
    'chair_name' => $signerName(0), 'chair_position' => $signerPosition(0),
    'member1_name' => $signerName(1), 'member1_position' => $signerPosition(1),
    'member2_name' => $signerName(2), 'member2_position' => $signerPosition(2),
    'director_title' => 'ความเห็นของผู้อำนวยการโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง',
    'director_decision' => '', 'director_other' => '',
    'director_name' => trim((string)($activeCase['director_name'] ?? '')),
    'director_position' => trim((string)($activeCase['director_position'] ?? 'ผู้อำนวยการโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง')),
    'executive_title' => 'ความเห็นของนายกองค์การบริหารส่วนจังหวัดเชียงใหม่',
    'executive_decision' => '',
    'executive_name' => trim((string)($activeCase['director_name'] ?? '')),
    'executive_position' => 'ผู้อำนวยการ รพ.สต.บ้านสันโค้ง ปฏิบัติราชการแทนนายกองค์การบริหารส่วนจังหวัดเชียงใหม่',
];
$values = array_replace($defaults, array_intersect_key($saved, $defaults));
foreach ($values as $key => $value) { if (is_string($value)) $values[$key] = str_replace('\\n', "\n", $value); }
$values['memo_no_suffix'] = (string)($savedDocument['memo_no_suffix'] ?? $values['memo_no_suffix']);
$values['doc_date'] = (string)($savedDocument['doc_date'] ?? $values['doc_date']);
$error = '';
$allowed = array_keys($defaults);
$maxLength = 12000;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    foreach ($allowed as $key) {
        if (array_key_exists($key, $_POST)) {
            $raw = $_POST[$key];
            $values[$key] = is_string($raw) ? trim($raw) : '';
        } else if (in_array($key, ['director_decision', 'executive_decision'], true)) {
            $values[$key] = '';
        }
        if (mb_strlen((string)$values[$key], 'UTF-8') > $maxLength) $error = 'มีช่องข้อความยาวเกินขนาดที่กำหนด';
    }
    if (!$activeCase) $error = 'กรุณาเลือกชุดเอกสารก่อน';
    if (trim($values['memo_no_suffix']) === '' || trim($values['doc_date']) === '') $error = 'กรุณากรอกเลขหนังสือและวันที่';
    if (!in_array($values['director_decision'], ['', 'approved', 'other'], true)) $error = 'ข้อมูลความเห็นไม่ถูกต้อง';
    if (!in_array($values['executive_decision'], ['', 'approved', 'rejected'], true)) $error = 'ข้อมูลความเห็นไม่ถูกต้อง';
    if ($error === '' && ($_POST['action'] ?? '') === 'save') {
        scopePriceApprovalSave($caseId, $values);
        header('Location: scope_price_approval_memo.php?case_id='.$caseId.'&saved=1');
        exit;
    }
    if ($error === '' && ($_POST['action'] ?? '') === 'word') {
        try {
            $wordValues = $values;
            $wordValues['_filename_case_id'] = $caseId;
            $wordValues['_filename_supplier'] = (string)($activeCase['supplier_name'] ?? '');
            scopeApprovalOutputTemplateWord($wordValues);
        } catch (Throwable $e) {
            error_log('Scope approval Word export failed: '.$e->getMessage());
            $error = 'สร้าง Word ไม่สำเร็จ กรุณาตรวจสอบ templates/docx/scope_price_approval_memo.docx และ PHP ZipArchive';
        }
    }
}
$groups = [
    'เอกสารราชการ' => ['office' => 'ส่วนราชการ', 'subject' => 'เรื่อง', 'recipient' => 'เรียน'],
    'ข้อมูลอ้างอิง' => ['reference_no' => 'เลขที่หนังสืออนุมัติหลักการ', 'reference_date' => 'วันที่หนังสืออนุมัติหลักการ', 'budget_amount' => 'วงเงินงบประมาณตั้งต้น', 'budget_amount_text' => 'จำนวนเงินตัวอักษร', 'medicine_count' => 'จำนวนรายการยา', 'meeting_date' => 'วันที่ประชุมกรรมการ', 'approval_amount' => 'วงเงินขออนุมัติใช้ราคากลาง', 'approval_amount_text' => 'จำนวนเงินตัวอักษร (ราคากลาง)'],
    'เรื่องเดิม' => ['background_1' => 'เรื่องเดิม ย่อหน้า ๑', 'background_2' => 'เรื่องเดิม ย่อหน้า ๒'],
    'ข้อเท็จจริง' => ['fact_1' => 'ข้อเท็จจริง ข้อ ๑', 'fact_2' => 'ข้อเท็จจริง ข้อ ๒', 'fact_3' => 'ข้อเท็จจริง ข้อ ๓', 'fact_4' => 'ข้อเท็จจริง ข้อ ๔'],
    'ข้อระเบียบ' => ['law_1' => 'ข้อระเบียบ ข้อ ๑', 'law_2' => 'ข้อระเบียบ ข้อ ๒', 'law_3' => 'ข้อระเบียบ ข้อ ๓', 'law_4' => 'ข้อระเบียบ ข้อ ๔', 'law_5' => 'ข้อระเบียบ ข้อ ๕', 'law_6' => 'ข้อระเบียบ ข้อ ๖'],
    'ข้อพิจารณา' => ['consideration_intro' => 'บทนำ', 'consideration_1' => 'ขออนุมัติ', 'consideration_2' => 'ส่งเรื่องดำเนินการ', 'conclusion' => 'ข้อความลงท้าย'],
    'รายชื่อคณะกรรมการ' => ['chair_name' => 'ประธานกรรมการ (ชื่อ)', 'chair_position' => 'ตำแหน่งประธาน', 'member1_name' => 'กรรมการคนที่ ๑', 'member1_position' => 'ตำแหน่งกรรมการคนที่ ๑', 'member2_name' => 'กรรมการคนที่ ๒', 'member2_position' => 'ตำแหน่งกรรมการคนที่ ๒'],
    'ความเห็นผู้บังคับบัญชา' => ['director_title' => 'หัวข้อความเห็น ผู้อำนวยการ', 'director_other' => 'ความเห็นอื่น ๆ', 'director_name' => 'ชื่อผู้อำนวยการ', 'director_position' => 'ตำแหน่งผู้อำนวยการ', 'executive_title' => 'หัวข้อความเห็น นายก อบจ.', 'executive_name' => 'ชื่อผู้อนุมัติ', 'executive_position' => 'ตำแหน่งผู้อนุมัติ'],
];
?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ขออนุมัติใช้รายละเอียดคุณลักษณะเฉพาะและราคากลาง</title><script src="https://cdn.tailwindcss.com"></script>
<style>
.scope-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(350px,410px);gap:20px;align-items:start;max-width:1800px;margin:auto;padding:15px;}
.scope-preview{min-width:0;overflow:auto;border-radius:16px;background:#e9edf4;padding:15px;}
.scope-preview .scope-paper{max-width:210mm;margin:auto;font-family:'TH Sarabun New','Sarabun',sans-serif;}
.scope-preview .scope-page{width:210mm;min-height:297mm;padding:20mm 20mm 20mm 30mm;background:white;margin:0 auto 15px;box-shadow:0 5px 20px #1e293b24;}
.scope-editor{position:sticky;top:70px;max-height:calc(100vh - 85px);overflow:auto;border:1px solid #e2e8f0;border-radius:16px;background:white;color:#1f2937;padding:16px;}
.scope-editor label{display:block;font-size:12px;font-weight:bold;color:#334155;margin-bottom:10px;}
.scope-editor input:not([type=radio]),.scope-editor select,.scope-editor textarea{display:block;width:100%;margin-top:4px;border:1px solid #cbd5e1;border-radius:8px;padding:8px 10px;background:#fff;color:#111827;font-size:13px;font-weight:normal;}
.scope-editor textarea{min-height:100px;resize:vertical;line-height:1.5;}
.scope-editor details{margin:9px 0;border:1px solid #dbe3ee;border-radius:10px;overflow:hidden;}
.scope-editor summary{cursor:pointer;padding:11px 13px;background:#f1f5f9;font-weight:700;font-size:13px;color:#334155;}
.scope-details-body{padding:12px;}
.scope-actions{position:sticky;bottom:-16px;padding:14px 0;background:white;display:grid;gap:8px;}
.scope-btn{padding:10px;border-radius:9px;font-weight:700;text-align:center;background:#4f46e5;color:white;cursor:pointer;}
.scope-btn-alt{background:#0f766e;}
.scope-caption{font-size:12px;color:#64748b;}
.scope-approval-block h3{font-weight:700;}
.scope-date-wrap{position:relative;display:flex;align-items:center;gap:6px;}
.scope-date-wrap input.scope-display-date{flex:1;min-width:0;cursor:pointer;}
.scope-date-wrap button{padding:8px;border:1px solid #cbd5e1;border-radius:8px;white-space:nowrap;cursor:pointer;}
/* Keep the native date picker rendered beside the field; off-screen inputs cannot reliably open showPicker(). */
.scope-editor .scope-date-wrap input.scope-date-native{position:absolute;right:8px;top:50%;width:1px;height:1px;margin:0;padding:0;border:0;opacity:0;pointer-events:none;transform:translateY(-50%);}
@media(max-width:1100px){.scope-layout{grid-template-columns:1fr}.scope-editor{position:static;max-height:none}.scope-preview{order:2;}}
@media print{
  /* @page above owns the A4 margins. Never print a 210mm preview box inside
     the printable area (160mm): Chrome will shrink the entire document. */
  html,body{margin:0!important;padding:0!important;background:#fff!important;width:auto!important}
  .no-print,.scope-editor{display:none!important}
  .scope-layout{display:block!important;max-width:none!important;width:auto!important;margin:0!important;padding:0!important}
  .scope-preview{display:block!important;max-width:none!important;min-width:0!important;width:auto!important;overflow:visible!important;margin:0!important;padding:0!important;border:0!important;background:none!important}
  .scope-preview .scope-paper{max-width:none!important;width:auto!important;margin:0!important}
  .scope-preview .scope-page{width:auto!important;min-height:0!important;height:auto!important;padding:0!important;margin:0!important;box-shadow:none!important;page-break-after:always;break-after:page}
  .scope-preview .scope-page:last-child{page-break-after:auto;break-after:auto}
  /* Prevent the unbroken dotted signature lines from widening the print layout. */
  .scope-signatures table{table-layout:fixed!important;width:100%!important}
  .scope-signatures td{overflow-wrap:anywhere!important;word-break:break-word!important}
  /* หน้า ๔ ที่พิมพ์ไว้ในตัวอย่าง/Word จะไม่ซ้ำกับเลขหน้าที่ Chrome สร้างขณะพิมพ์ */
  .scope-preview .scope-number{display:none!important}
}
<?= scopeApprovalCss(true) ?>
/* ใช้หัวกระดาษแบบมีชื่อเหมือนหน้าอื่นที่ใช้งานได้: นับหน้าจริงไม่เขียนตัวเลขตายตัว */
@counter-style scope-print-thai {system:numeric;symbols:"๐" "๑" "๒" "๓" "๔" "๕" "๖" "๗" "๘" "๙";suffix:"";}
@font-face {font-family:'ScopeMemoA4';src:url('assets/fonts/THSarabunIT9-Regular.ttf') format('truetype');font-weight:400;font-display:swap;}
@font-face {font-family:'ScopeMemoA4';src:url('assets/fonts/THSarabunIT9-Bold.ttf') format('truetype');font-weight:700;font-display:swap;}
.scope-preview .scope-paper {font-family:'ScopeMemoA4','TH Sarabun New',serif!important;}
/* หัวหนังสืออิงความกว้างและระยะห้อยบรรทัดจากแม่แบบ Word */
.scope-preview [data-scope-out="office"]{font-size:15pt;}
/* Header-only Thai-aware justification: full lines justify; final line stays natural. */
.scope-preview .scope-topline:has([data-scope-out="office"]),
.scope-preview .scope-topline:has([data-scope-out="subject"]){
  text-align:justify;
  text-justify:auto;
  word-spacing:normal;
  letter-spacing:normal;
}
.scope-preview .scope-topline:has([data-scope-out="subject"]){padding-left:0.7cm;text-indent:-0.7cm;}
/* The "ที่ / วันที่" table uses zero inset like the surrounding memo labels. */
.scope-preview .scope-topline+table{border-spacing:0!important;margin-left:0!important;}
.scope-preview .scope-topline+table td{padding:0!important;text-indent:0!important;}
@page scopeMemo {
  size:A4; margin:20mm 20mm 20mm 30mm;
  @top-center {content:"- " counter(page,scope-print-thai) " -";font-family:'ScopeMemoA4','TH Sarabun New',sans-serif;font-size:16pt;font-weight:400;vertical-align:middle;margin:0;padding:0;}
}
@page scopeMemo:first {margin-top:20mm;@top-center{content:none;}}
@media print {
  .scope-preview .scope-page {page:scopeMemo;font-family:'ScopeMemoA4','TH Sarabun New',sans-serif!important;font-size:16pt!important;}
  .scope-preview .scope-sectitle u {text-decoration-line:underline!important;text-decoration-color:#111!important;text-decoration-thickness:1pt!important;text-underline-offset:1pt!important;}
}
</style><link rel="stylesheet" href="assets/document-workspace.css?v=20261124-1">
</head>
<body class="docws docws-scope-page bg-slate-100 text-slate-900">
<div class="no-print"><?php require __DIR__.'/components/navbar.php'; require __DIR__.'/components/workflow_steps.php'; ?></div>
<header class="no-print mx-auto max-w-7xl px-4 pt-5"><h1 class="text-xl font-bold">๓. ขออนุมัติใช้รายละเอียดคุณลักษณะเฉพาะและราคากลาง</h1><p class="scope-caption">เอกสารใหม่แยกจากเอกสารอื่นของชุดงาน · ข้อมูลส่วนข้อความปรับแก้ได้ · Word ใช้แม่แบบ DOCX ต้นฉบับโดยตรง</p></header>
<?php if (!$activeCase): ?>
<main class="mx-auto max-w-2xl px-4 py-12"><form method="get" class="rounded-xl bg-white p-6 shadow"><h2 class="mb-3 font-bold">เลือกชุดเอกสารก่อนเริ่ม</h2><select name="case_id" class="w-full rounded-md border border-slate-300 p-3" required><option value="">-- เลือกชุดเอกสาร --</option><?php foreach($cases as $c): ?><option value="<?= (int)$c['id'] ?>"><?= scopeApprovalH($c['title'] . ' · ' . torCaseSupplierLabel($c)) ?></option><?php endforeach ?></select><button class="scope-btn mt-3 w-full">เปิดแบบฟอร์ม</button></form></main>
<?php else: ?>
<div class="scope-layout docws-layout" data-docws-layout="1">
<div class="scope-preview docws-preview-pane" id="scope-preview" data-docws-preview="1"><?= scopeApprovalRender($values) ?></div>
<aside class="scope-editor no-print docws-editor-pane" data-docws-editor="1">
<form method="get" style="margin-bottom:15px"><label>ชุดเอกสาร <select name="case_id" onchange="this.form.submit()"><?php foreach ($cases as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id']===$caseId?'selected':'' ?>><?= scopeApprovalH($c['title'] . ' · ' . torCaseSupplierLabel($c)) ?></option><?php endforeach ?></select></label></form>
<?php if (isset($_GET['saved'])): ?><div class="mb-3 rounded-md bg-emerald-50 p-3 text-sm text-emerald-700">บันทึกเอกสารเรียบร้อยแล้ว</div><?php endif ?>
<?php if ($error !== ''): ?><div class="mb-3 rounded-md bg-red-50 p-3 text-sm text-red-700"><?= scopeApprovalH($error) ?></div><?php endif ?>
<form id="scope-form" method="post"><input type="hidden" name="case_id" value="<?= $caseId ?>"><input type="hidden" name="csrf_token" value="<?= scopeApprovalH(csrfToken()) ?>">
<div class="rounded-lg bg-indigo-50 p-3" style="margin-bottom:12px"><label>เลขที่หนังสือ (หลัง /)<input required name="memo_no_suffix" value="<?= scopeApprovalH(scopeApprovalThai($values['memo_no_suffix'])) ?>" autocomplete="off"></label>
<label>วันที่ออกหนังสือ<div class="scope-date-wrap"><input name="doc_date" class="scope-display-date" required readonly placeholder="กดเพื่อเลือกวันที่" value="<?= scopeApprovalH(scopeApprovalThai($values['doc_date'])) ?>"><button type="button" class="scope-pick-date">เลือกวันที่</button></div></label></div>
<?php $groupIndex=0; foreach($groups as $groupName=>$fields): $groupIndex++; ?>
<details <?= $groupIndex <= 2 ? 'open' : '' ?>><summary><?= scopeApprovalThai($groupIndex) ?>. <?= scopeApprovalH($groupName) ?></summary><div class="scope-details-body">
<?php if ($groupName==='ข้อมูลอ้างอิง'): ?><p class="scope-caption" style="margin-bottom:10px">ช่องอ้างอิงจะเติมในข้อความผ่านตัวแปร {{reference_no}}, {{meeting_date}}, {{approval_amount}} ฯลฯ โดยอัตโนมัติ</p><?php endif ?>
<?php foreach($fields as $key=>$label): ?>
<label><?= scopeApprovalH($label) ?><?php if (in_array($key, ['reference_date','meeting_date'],true)): ?><div class="scope-date-wrap"><input readonly class="scope-display-date" name="<?= $key ?>" value="<?= scopeApprovalH(scopeApprovalThai($values[$key])) ?>" placeholder="กดเพื่อเลือกวันที่"><button type="button" class="scope-pick-date">เลือกวันที่</button></div>
<?php elseif (mb_strlen((string)$values[$key],'UTF-8')>95 || preg_match('/^(?:law_|fact_|background_|consideration)/', $key)): ?><textarea name="<?= $key ?>" rows="<?= preg_match('/^(?:law_|fact_)/',$key)?7:4 ?>"><?= scopeApprovalH(scopeApprovalThai($values[$key])) ?></textarea>
<?php else: ?><input name="<?= $key ?>" value="<?= scopeApprovalH(scopeApprovalThai($values[$key])) ?>">
<?php endif ?></label>
<?php endforeach ?>
<?php if($groupName==='ความเห็นผู้บังคับบัญชา'): ?>
<label>ความเห็นผู้อำนวยการ<select name="director_decision"><option value="" <?= $values['director_decision']===''?'selected':'' ?>>ยังไม่เลือก</option><option value="approved" <?= $values['director_decision']==='approved'?'selected':'' ?>>เห็นชอบ</option><option value="other" <?= $values['director_decision']==='other'?'selected':'' ?>>อื่น ๆ</option></select></label>
<label>ความเห็นนายก อบจ.<select name="executive_decision"><option value="" <?= $values['executive_decision']===''?'selected':'' ?>>ยังไม่เลือก</option><option value="approved" <?= $values['executive_decision']==='approved'?'selected':'' ?>>อนุมัติ</option><option value="rejected" <?= $values['executive_decision']==='rejected'?'selected':'' ?>>ไม่อนุมัติ</option></select></label>
<?php endif; ?></div></details>
<?php endforeach ?>
<div class="scope-actions"><button class="scope-btn" name="action" value="save">บันทึกเอกสาร</button><button class="scope-btn scope-btn-alt" name="action" value="word">ดาวน์โหลด Word ตามแบบฟอร์ม</button><button class="rounded-md border border-slate-300 p-2" type="button" onclick="window.print()">พิมพ์ / PDF</button></div>
</form>
</aside></div>
<?php endif ?>
<script>
(function () {
const form=document.getElementById('scope-form'); if(!form)return;
const thaiDigits=s=>String(s).replace(/[0-9]/g,d=>'๐๑๒๓๔๕๖๗๘๙'[+d]);
const arabic=s=>String(s).replace(/[๐-๙]/g,d=>String('๐๑๒๓๔๕๖๗๘๙'.indexOf(d)));
const months=['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
function parseDate(value){const s=arabic(value).trim();const m=s.match(/^(\d{1,2})\s+([^\s]+)\s+(\d{4})$/);if(!m)return '';const ix=months.indexOf(m[2]),y=+m[3]-543;if(ix<0||y<1900)return '';return String(y).padStart(4,'0')+'-'+String(ix+1).padStart(2,'0')+'-'+String(+m[1]).padStart(2,'0');}
form.querySelectorAll('.scope-display-date').forEach(el=>{
    const wrap=el.closest('.scope-date-wrap');
    const picker=document.createElement('input');
    picker.type='date';
    picker.className='scope-date-native';
    picker.tabIndex=-1;
    picker.setAttribute('aria-label','ปฏิทินเลือกวันที่');
    picker.value=parseDate(el.value);
    wrap.appendChild(picker);
    const openPicker=()=>{
        picker.value=parseDate(el.value);
        try {
            if(typeof picker.showPicker==='function')picker.showPicker();
            else {picker.focus();picker.click();}
        }catch(error){picker.focus();picker.click();}
    };
    picker.addEventListener('change',()=>{
        if(!picker.value)return;
        const [y,m,d]=picker.value.split('-').map(Number);
        el.value=thaiDigits(d+' '+months[m-1]+' '+(y+543));
        el.dispatchEvent(new Event('input',{bubbles:true}));
        el.dispatchEvent(new Event('change',{bubbles:true}));
    });
    el.addEventListener('click',openPicker);
    wrap.querySelector('.scope-pick-date')?.addEventListener('click',openPicker);
});
function expand(text){return thaiDigits(String(text).replace(/\{\{(reference_no|reference_date|budget_amount|budget_amount_text|medicine_count|meeting_date|approval_amount|approval_amount_text)\}\}/g,(_,key)=>{const value=form.elements[key]?.value.trim()||'';return value||'................';}));}
// Match the Word DOCX rule: all copied line breaks (including blank lines)
// within one form field are SOFT wraps unless a new numbered item or ¶ starts.
function logicalLines(text){
    let current='';const paragraphs=[];
    const flush=()=>{if(current){paragraphs.push(current);current='';}};
    for(const raw of String(text).replace(/\r\n?|[\u2028\u2029]/g,'\n').split('\n')){
        let line=raw.replace(/[\t ]+/g,' ').trim();
        if(!line)continue; // A blank copied line is not a new paragraph.
        const explicit=line.startsWith('¶');
        if(explicit)line=line.slice(1).trimStart();
        if(!line)continue;
        const numbered=/^(?:[๐-๙0-9]{1,2}[.)](?:\s|$)|[（(][๐-๙0-9]{1,3}[）)](?:\s|$)|[•-]\s)/u.test(line);
        if(numbered||explicit)flush();
        if(!current){current=line;continue;}
        const glue=(/[ก-ฮะ-๏]$/u.test(current)&&/^[ก-ฮะ-๏]/u.test(line))||(/[/\-.]$/u.test(current)&&/^[๐-๙0-9]/u.test(line))?'':' ';
        current+=glue+line;
    }
    flush();return paragraphs.length?paragraphs:[''];
}
function refresh(){
  form.querySelectorAll('input[name],textarea[name],select[name]').forEach(el=>{
    if(['hidden','radio'].includes(el.type)||el.name==='csrf_token'||el.name==='case_id')return;
    if(el.tagName!=='SELECT' && !el.readOnly){
      let pos=el.selectionStart;el.value=thaiDigits(el.value);
      try{if(pos!==null)el.setSelectionRange(pos,pos);}catch(e){}
    }
    const value=thaiDigits(el.value);
    document.querySelectorAll('[data-scope-out="'+el.name+'"]').forEach(t=>{
      const lines=logicalLines(expand(value));
      if(t.classList.contains('scope-paragraph-group')){
        const nodes=lines.map(content=>{
          const p=document.createElement('p');p.className='scope-paragraph';p.textContent=content;return p;
        });
        t.replaceChildren(...nodes);
      }else{
        t.textContent=lines.join(' ');
      }
    });
  });
  document.querySelectorAll('[data-scope-checked]').forEach(el=>{
    const [name,match]=el.dataset.scopeChecked.split(':');
    el.textContent=form.elements[name]?.value===match?'☑':'☐';
  });
}
form.addEventListener('input',refresh);form.addEventListener('change',refresh);refresh();
})();
</script>
<script defer src="assets/document-workspace.js?v=20261124-1"></script>
<script src="assets/tor-document-print-name.js?v=20261124-1"></script>
<script>
window.TorDocumentPrintName.init(<?= json_encode(['step'=>4,'caseId'=>$caseId,'supplier'=>(string)($activeCase['supplier_name'] ?? ''),'dateField'=>'doc_date','numberField'=>'memo_no_suffix'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
</script>
</body></html>
