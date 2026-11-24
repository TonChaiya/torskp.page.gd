<?php
declare(strict_types=1);

/**
 * สร้าง Word จาก DOCX ต้นฉบับ (Template 4 หน้า) แทนการแปลง HTML หรือสร้าง Word XML ใหม่
 * ไฟล์แม่แบบ: templates/docx/scope_price_approval_memo.docx
 * รักษา page setup, table, ตราครุฑ, tab, indentation, header/page-number และช่องลายเซ็นเดิม
 * เนื้อหายังคง left alignment; เฉพาะหัวหนังสือใช้ justified แบบปกติ
 * ไม่ใช้ Thai Distributed ที่เคยทำให้ตัวอักษรไทยยืดผิดปกติ
 * ไม่เปลี่ยนข้อมูลในฐานข้อมูล และไม่กระทบหน้า TOR / Word หัวข้อ 1
 */
require_once __DIR__ . '/scope_price_thai_breaker.php';

function scopeApprovalTemplateFields(array $values): array
{
    $raw = static function (string $key) use ($values): string {
        $value = (string) ($values[$key] ?? '');
        // Existing form supports {{reference_no}}, {{meeting_date}}, ... inside textarea contents.
        return scopeApprovalThai(scopeApprovalExpand($values, $value));
    };

    $keys = [
        'office', 'subject', 'recipient', 'doc_date',
        'background_1', 'background_2', 'fact_1', 'fact_2', 'fact_3', 'fact_4',
        'law_1', 'law_2', 'law_3', 'law_4', 'law_5', 'law_6',
        'consideration_intro', 'consideration_1', 'consideration_2', 'conclusion',
        'chair_name', 'chair_position', 'member1_name', 'member1_position',
        'member2_name', 'member2_position',
        'director_title', 'director_position', 'executive_title', 'executive_position',
    ];
    $fields = [];
    foreach ($keys as $key) {
        $fields[$key] = $raw($key);
    }

    $fields['memo_number'] = 'ชม ๕๑๐๐๖.๑๓.๑/' . $raw('memo_no_suffix');
    $directorDecision = (string) ($values['director_decision'] ?? '');
    $fields['director_approved_line'] = ($directorDecision === 'approved' ? '☑' : '☐') . ' เห็นชอบ';
    $fields['director_other_line'] = ($directorDecision === 'other' ? '☑' : '☐') . ' อื่น ๆ ' . $raw('director_other');
    $executiveDecision = (string) ($values['executive_decision'] ?? '');
    $fields['executive_approved_line'] = ($executiveDecision === 'approved' ? '☑' : '☐') . ' อนุมัติ';
    $fields['executive_rejected_line'] = ($executiveDecision === 'rejected' ? '☑' : '☐') . ' ไม่อนุมัติ';
    $fields['director_name_wrapped'] = '(' . $raw('director_name') . ')';
    $fields['executive_name_wrapped'] = '(' . $raw('executive_name') . ')';
    return $fields;
}

/** XML-encode only text nodes, not Word XML tags/attributes. */
function scopeApprovalTemplateXmlText(string $text): string
{
    // XML 1.0 prohibits most C0 characters. Replace non-printables and keep text clean.
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Text from a single form field belongs to ONE logical paragraph by default.
 * Copied PDF/Word may contain single/double/triple hard wraps mid-sentence;
 * none of those creates a DOCX paragraph. Only an explicit ¶ marker or a
 * standalone numbered sub-item at the start of a copied line can split it.
 * Each separate form field (background_1/2, fact_1/2, etc.) is already its own
 * Word paragraph, so no other inference about sentence boundaries is needed.
 */
function scopeApprovalLogicalParagraphs(string $text): array
{
    $text = str_replace(["\r\n", "\r", "\u{2028}", "\u{2029}"], "\n", $text);
    $result = [];
    $current = '';
    $joinFragment = static function (string $before, string $after): string {
        if ($before === '' || $after === '') return $before . $after;
        // A PDF may wrap a Thai word or a reference number at the page edge.
        if (preg_match('/[ก-ฮะ-๏]$/u', $before) && preg_match('/^[ก-ฮะ-๏]/u', $after)) {
            return $before . $after;
        }
        if (preg_match('~[/\-.]$~u', $before) && preg_match('/^[๐-๙0-9]/u', $after)) {
            return $before . $after;
        }
        return rtrim($before) . ' ' . ltrim($after);
    };
    foreach (explode("\n", $text) as $rawLine) {
        $line = trim(preg_replace('/[\t ]+/u', ' ', $rawLine) ?? $rawLine);
        // Do NOT infer a new paragraph from empty/blank copied lines.
        if ($line === '') continue;
        $explicit = str_starts_with($line, '¶');
        if ($explicit) $line = ltrim(substr($line, strlen('¶')));
        if ($line === '') continue;
        // Start an actual new numbered sub-item only if it starts the line.
        $numbered = (bool) preg_match('/^(?:[๐-๙0-9]{1,2}[.)](?:\s|$)|[（(][๐-๙0-9]{1,3}[）)](?:\s|$)|[•\-]\s)/u', $line);
        if (($explicit || $numbered) && $current !== '') {
            $result[] = $current;
            $current = '';
        }
        $current = $current === '' ? $line : $joinFragment($current, $line);
    }
    if ($current !== '') $result[] = $current;
    return $result === [] ? [''] : $result;
}

/**
 * Replace markers only inside existing Word paragraphs, preserving the template
 * paragraph formatting. Soft line breaks are joined, not made into paragraphs.
 */
function scopeApprovalTemplatePopulateXml(string $xml, array $fields): string
{
    $marker = '/\{\{scope\.([a-z][a-z0-9_]*)\}\}/u';
    $paragraph = '~<w:p(?:\s[^>]*)?>.*?</w:p>~su';
    $found = [];
    $result = preg_replace_callback($paragraph, static function (array $match) use ($fields, $marker, &$found): string {
        $templateParagraph = $match[0];
        if (!preg_match_all($marker, $templateParagraph, $matched)) {
            return $templateParagraph;
        }
        foreach ($matched[1] as $name) {
            if (!array_key_exists($name, $fields)) {
                throw new RuntimeException('Template field missing: ' . $name);
            }
            $found[$name] = true;
        }
        $keys = array_unique($matched[1]);
        // A paragraph dedicated to one editable field is expanded on line breaks.
        if (count($keys) === 1) {
            $key = reset($keys);
            // Only editable BODY fields may expand into multiple Word paragraphs.
            // Header labels share a paragraph with their marker, so cloning those
            // would duplicate `ส่วนราชการ`, `เรื่อง`, etc. on each inserted line.
            $isBodyField = (bool) preg_match('/^(?:background_[12]|fact_[1-4]|law_[1-6]|consideration_(?:intro|[12])|conclusion)$/', (string)$key);
            $text = (string)$fields[$key];
            $paragraphs = scopeApprovalLogicalParagraphs($text);
            if ($isBodyField && (count($paragraphs) > 1 || str_contains($text, "\n") || str_contains($text, "\r"))) {
                $output = '';
                foreach ($paragraphs as $line) {
                    $replacement = scopeApprovalTemplateXmlText(scopeApprovalThaiSoftBreaks($line));
                    $output .= str_replace('{{scope.' . $key . '}}', $replacement, $templateParagraph);
                }
                return $output;
            }
        }
        return preg_replace_callback($marker, static function (array $m) use ($fields): string {
            return scopeApprovalTemplateXmlText(scopeApprovalThaiSoftBreaks(implode(' ', scopeApprovalLogicalParagraphs((string)$fields[$m[1]]))));
        }, $templateParagraph) ?? $templateParagraph;
    }, $xml);
    if (!is_string($result)) {
        throw new RuntimeException('ไม่สามารถประมวลผลข้อความใน Template DOCX');
    }
    // Reject incomplete marker maps so no {{scope...}} placeholders leak to downloaded documents.
    if (preg_match($marker, $result)) {
        throw new RuntimeException('มีตัวแปร Template ที่ยังไม่ได้แทนค่า');
    }
    $expected = array_keys($fields);
    $missing = array_diff($expected, array_keys($found));
    if ($missing !== []) {
        throw new RuntimeException('ไม่พบตัวแปรใน Template: ' . implode(', ', $missing));
    }
    return $result;
}

function scopeApprovalOutputTemplateWord(array $values): void
{
    $path = dirname(__DIR__) . '/templates/docx/scope_price_approval_memo.docx';
    if (!is_readable($path)) {
        throw new RuntimeException('ไม่พบไฟล์แม่แบบ Word: ' . $path);
    }
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ต้องเปิดใช้ ZipArchive เพื่อส่งออก DOCX');
    }
    $fields = scopeApprovalTemplateFields($values);
    $temp = tempnam(sys_get_temp_dir(), 'scope_docx_template_');
    if (!is_string($temp)) {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ชั่วคราวสำหรับ Word ได้');
    }
    try {
        if (!copy($path, $temp)) {
            throw new RuntimeException('ไม่สามารถคัดลอกแม่แบบ DOCX');
        }
        $zip = new ZipArchive();
        if ($zip->open($temp) !== true) {
            throw new RuntimeException('ไม่สามารถเปิดแม่แบบ DOCX');
        }
        $hasEmbeddedFonts = ($zip->locateName('word/fontTable.xml') !== false)
            && ($zip->locateName('word/fonts/thsarabunit9-1.odttf') !== false);
        try {
            $document = $zip->getFromName('word/document.xml');
            if (!is_string($document)) {
                throw new RuntimeException('เอกสาร DOCX ไม่มี word/document.xml');
            }
            $document = scopeApprovalTemplatePopulateXml($document, $fields);
            if (!$zip->addFromString('word/document.xml', $document)) {
                throw new RuntimeException('ไม่สามารถเขียนเนื้อหาลง DOCX');
            }
        } finally {
            $zip->close();
        }
        // The supplied Word document already embeds all four TH SarabunIT9 fonts.
        // Do not re-embed them; doing so can alter a Word file whose original layout
        // has already been approved. Fall back to the shared helper only if needed.
        if (!$hasEmbeddedFonts && function_exists('wordEmbedProjectFonts')) {
            wordEmbedProjectFonts($temp);
        }
        $filename = wordSafeFilename(scopeApprovalFilename($values));
        if (headers_sent()) {
            throw new RuntimeException('HTTP headers ถูกส่งแล้วก่อนดาวน์โหลด Word');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="scope_approval.docx"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . (string)filesize($temp));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        readfile($temp);
    } finally {
        @unlink($temp);
    }
    exit;
}
