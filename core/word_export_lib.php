<?php
declare(strict_types=1);

const WORD_PRIMARY_FONT = 'TH SarabunIT๙';

function wordProjectFontFiles(): array
{
    $fontDirectory = dirname(__DIR__) . '/assets/fonts';
    return [
        'embedRegular' => $fontDirectory . '/THSarabunIT9-Regular.ttf',
        'embedBold' => $fontDirectory . '/THSarabunIT9-Bold.ttf',
        'embedItalic' => $fontDirectory . '/THSarabunIT9-Italic.ttf',
        'embedBoldItalic' => $fontDirectory . '/THSarabunIT9-BoldItalic.ttf',
    ];
}

function wordEmbeddedFontKey(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = strtoupper(bin2hex($bytes));
    return '{' . substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20) . '}';
}

function wordObfuscateEmbeddedFont(string $fontData, string $fontKey): string
{
    $key = hex2bin(str_replace(['{', '}', '-'], '', $fontKey));
    if ($key === false || strlen($key) !== 16 || strlen($fontData) < 32) {
        throw new RuntimeException('ไม่สามารถเตรียมฟอนต์สำหรับฝังใน DOCX ได้');
    }
    // ISO/IEC 29500 uses the GUID bytes in reverse order, repeated twice.
    $key = strrev($key);
    for ($index = 0; $index < 32; $index++) {
        $fontData[$index] = chr(ord($fontData[$index]) ^ ord($key[$index % 16]));
    }
    return $fontData;
}

function wordEmbedProjectFonts(string $path): void
{
    $fontFiles = wordProjectFontFiles();
    foreach ($fontFiles as $fontPath) {
        if (!is_file($fontPath)) {
            throw new RuntimeException('ไม่พบไฟล์ฟอนต์ TH SarabunIT๙ ในโปรเจกต์');
        }
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('ไม่สามารถเปิด DOCX เพื่อฝังฟอนต์ได้');
    }

    try {
        $fontElements = '';
        $fontRelationships = '';
        $fontIndex = 1;
        foreach ($fontFiles as $elementName => $fontPath) {
            $fontKey = wordEmbeddedFontKey();
            $relationshipId = 'rIdEmbeddedFont' . $fontIndex;
            $fontPart = 'word/fonts/thsarabunit9-' . $fontIndex . '.odttf';
            $fontData = file_get_contents($fontPath);
            if (!is_string($fontData)) {
                throw new RuntimeException('ไม่สามารถอ่านไฟล์ฟอนต์ TH SarabunIT๙ ได้');
            }
            $zip->addFromString($fontPart, wordObfuscateEmbeddedFont($fontData, $fontKey));
            $fontElements .= '<w:' . $elementName . ' r:id="' . $relationshipId
                . '" w:fontKey="' . $fontKey . '"/>';
            $fontRelationships .= '<Relationship Id="' . $relationshipId
                . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/font"'
                . ' Target="fonts/' . basename($fontPart) . '"/>';
            $fontIndex++;
        }

        $fontNameXml = htmlspecialchars(WORD_PRIMARY_FONT, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $zip->addFromString('word/fontTable.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:fonts xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<w:font w:name="' . $fontNameXml . '"><w:charset w:val="00"/><w:family w:val="swiss"/>'
            . '<w:pitch w:val="variable"/>' . $fontElements . '</w:font></w:fonts>');
        $zip->addFromString('word/_rels/fontTable.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $fontRelationships . '</Relationships>');

        $contentTypesXml = $zip->getFromName('[Content_Types].xml');
        if (!is_string($contentTypesXml)) {
            throw new RuntimeException('DOCX ไม่มีรายการชนิดเนื้อหา');
        }
        $contentTypes = new DOMDocument('1.0', 'UTF-8');
        $contentTypes->preserveWhiteSpace = false;
        if (!$contentTypes->loadXML($contentTypesXml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new RuntimeException('รายการชนิดเนื้อหา DOCX ไม่ถูกต้อง');
        }
        $contentNs = 'http://schemas.openxmlformats.org/package/2006/content-types';
        $contentXPath = new DOMXPath($contentTypes);
        $contentXPath->registerNamespace('ct', $contentNs);
        $odttfTypes = $contentXPath->query('/ct:Types/ct:Default[@Extension="odttf"]');
        if ($odttfTypes === false || $odttfTypes->length === 0) {
            $default = $contentTypes->createElementNS($contentNs, 'Default');
            $default->setAttribute('Extension', 'odttf');
            $default->setAttribute('ContentType', 'application/vnd.openxmlformats-officedocument.obfuscatedFont');
            $contentTypes->documentElement?->appendChild($default);
        }
        foreach ([
            '/word/fontTable.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml',
            '/word/settings.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml',
        ] as $partName => $contentType) {
            $overrides = $contentXPath->query('/ct:Types/ct:Override[@PartName="' . $partName . '"]');
            if ($overrides !== false && $overrides->length > 0) continue;
            $override = $contentTypes->createElementNS($contentNs, 'Override');
            $override->setAttribute('PartName', $partName);
            $override->setAttribute('ContentType', $contentType);
            $contentTypes->documentElement?->appendChild($override);
        }
        $zip->addFromString('[Content_Types].xml', (string) $contentTypes->saveXML());

        $relationshipsXml = $zip->getFromName('word/_rels/document.xml.rels');
        if (!is_string($relationshipsXml)) {
            throw new RuntimeException('DOCX ไม่มีความสัมพันธ์ของเอกสาร');
        }
        $relationships = new DOMDocument('1.0', 'UTF-8');
        $relationships->preserveWhiteSpace = false;
        if (!$relationships->loadXML($relationshipsXml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new RuntimeException('ความสัมพันธ์ของ DOCX ไม่ถูกต้อง');
        }
        $relationshipNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $relationshipXPath = new DOMXPath($relationships);
        $relationshipXPath->registerNamespace('rel', $relationshipNs);
        foreach ([
            'fontTable' => ['rIdEmbeddedFontTable', 'fontTable.xml'],
            'settings' => ['rIdEmbeddedFontSettings', 'settings.xml'],
        ] as $type => [$relationshipId, $target]) {
            $fullType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/' . $type;
            $existing = $relationshipXPath->query('/rel:Relationships/rel:Relationship[@Type="' . $fullType . '"]');
            if ($existing !== false && $existing->length > 0) continue;
            $relationship = $relationships->createElementNS($relationshipNs, 'Relationship');
            $relationship->setAttribute('Id', $relationshipId);
            $relationship->setAttribute('Type', $fullType);
            $relationship->setAttribute('Target', $target);
            $relationships->documentElement?->appendChild($relationship);
        }
        $zip->addFromString('word/_rels/document.xml.rels', (string) $relationships->saveXML());

        $settingsXml = $zip->getFromName('word/settings.xml');
        $settings = new DOMDocument('1.0', 'UTF-8');
        $settings->preserveWhiteSpace = false;
        if (is_string($settingsXml)) {
            if (!$settings->loadXML($settingsXml, LIBXML_NONET | LIBXML_NOBLANKS)) {
                throw new RuntimeException('การตั้งค่า DOCX ไม่ถูกต้อง');
            }
        } else {
            $settings->appendChild($settings->createElementNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'w:settings'
            ));
        }
        $settingsXPath = new DOMXPath($settings);
        $settingsXPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $embeddedSetting = $settingsXPath->query('/w:settings/w:embedTrueTypeFonts');
        if ($embeddedSetting === false || $embeddedSetting->length === 0) {
            $settings->documentElement?->insertBefore(
                $settings->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:embedTrueTypeFonts'),
                $settings->documentElement?->firstChild
            );
        }
        $zip->addFromString('word/settings.xml', (string) $settings->saveXML());
    } finally {
        $zip->close();
    }
}

function wordSafeFilename(string $filename): string
{
    $filename = trim($filename);
    $filename = preg_replace('/[\\\\\/:*?"<>|]+/u', '-', $filename) ?: 'document.docx';
    $filename = preg_replace('/\.docx?$/i', '', $filename) ?: 'document';
    return $filename . '.docx';
}

function wordEmbedLocalImages(string $html): string
{
    return (string) preg_replace_callback('/\s(src)=("|\')([^"\']+)\2/i', static function (array $match): string {
        $src = html_entity_decode($match[3], ENT_QUOTES, 'UTF-8');
        if (str_starts_with($src, 'data:image/')) {
            return ' ' . $match[1] . '=' . $match[2] . $match[3] . $match[2];
        }
        if (preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $src)) return '';

        $root = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'picture');
        $relative = preg_replace('#^picture[\\\\/]#i', '', ltrim($src, '/\\'));
        $path = $root && is_string($relative) ? realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative)) : false;
        $rootPrefix = $root ? rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : '';
        if (!$path || !$root || !str_starts_with($path, $rootPrefix) || !is_file($path) || filesize($path) > 5 * 1024 * 1024) return '';

        $mime = mime_content_type($path) ?: '';
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) return '';
        $data = base64_encode((string) file_get_contents($path));
        return ' ' . $match[1] . '=' . $match[2] . 'data:' . $mime . ';base64,' . $data . $match[2];
    }, $html);
}

function wordCleanHtml(string $html): string
{
    $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
    $html = preg_replace('/<button\b[^>]*class=("|\')[^"\']*\bno-print\b[^"\']*\1[^>]*>.*?<\/button>/is', '', $html) ?? $html;
    $html = preg_replace('/\scontenteditable=("|\')[^"\']*\1/i', '', $html) ?? $html;
    return wordEmbedLocalImages($html);
}

function wordProfileCss(string $profile): string
{
    $common = <<<'CSS'
        .memo-page, .report-page {
            overflow: visible !important;
            box-shadow: none !important;
        }
        .memo-line, .memo-text, .content, .speech-body, .speaker, .topic {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            mso-margin-top-alt: 0pt !important;
            mso-margin-bottom-alt: 0pt !important;
            mso-line-height-rule: exactly;
        }
        .red { color: #c00000 !important; }
CSS;

    if ($profile === 'principle') {
        return $common . <<<'CSS'
        @page Section1 { size: 210mm 297mm; margin: 25mm 20mm 20mm 30mm; }
        .memo-page {
            width: 160mm !important;
            min-height: 252mm !important;
            font-size: 16pt !important;
            line-height: 1 !important;
        }
        .memo-title { font-size: 28pt !important; line-height: 1 !important; font-weight: 700 !important; }
        .memo-label { font-size: 19pt !important; line-height: 1 !important; font-weight: 700 !important; white-space: nowrap !important; }
        .memo-line, .memo-text, .indent, .numbered, .section-title, .medicine-line, .committee-line {
            font-size: 16pt !important;
            line-height: 1 !important;
        }
        .word-principle-page p {
            margin: 0 !important;
            mso-margin-top-alt: 0pt !important;
            mso-margin-bottom-alt: 0pt !important;
            text-align: justify !important;
            text-justify: inter-character !important;
            -ms-text-justify: distribute !important;
            text-align-last: left !important;
            text-indent: 70pt !important;
            line-height: 1 !important;
            mso-line-height-rule: exactly !important;
        }
        .word-principle-page .section-title {
            font-weight: 700 !important;
            margin: 8pt 0 2pt 70pt !important;
            text-align: left !important;
            text-indent: 0 !important;
        }
        .word-principle-page table {
            border-collapse: collapse !important;
            mso-table-lspace: 0pt !important;
            mso-table-rspace: 0pt !important;
        }
        .word-principle-page td {
            font-size: 16pt !important;
            line-height: 1 !important;
            mso-line-height-rule: exactly !important;
        }
        .word-principle-page .header-table,
        .word-principle-page .header-table td {
            border: none !important;
            mso-border-alt: none !important;
            line-height: 1 !important;
        }
        .word-document-table,
        .word-details-table,
        .word-approval-table {
            border: none !important;
            mso-border-alt: none !important;
        }
        .word-document-table td,
        .word-details-table td,
        .word-approval-table td {
            border: none !important;
            mso-border-alt: none !important;
        }
        .word-medicine-table,
        .word-committee-table,
        .word-numbered-table {
            border: none !important;
            mso-border-alt: none !important;
        }
        .word-medicine-table td,
        .word-committee-table td,
        .word-numbered-table td {
            border: none !important;
            mso-border-alt: none !important;
        }
        .word-memo-header,
        .word-memo-header td {
            border: none !important;
            mso-border-alt: none !important;
        }
        .word-memo-header .memo-title {
            font-size: 28pt !important;
            line-height: 1 !important;
            text-align: center !important;
            font-weight: 700 !important;
        }
        .word-meta-table .memo-label {
            font-size: 19pt !important;
            line-height: 1 !important;
            font-weight: 700 !important;
            white-space: nowrap !important;
        }
        .word-meta-table .memo-line,
        .word-meta-table .memo-text {
            font-size: 16pt !important;
            line-height: 1 !important;
        }
        .word-medicine-table,
        .word-committee-table,
        .word-numbered-table {
            page-break-inside: avoid;
        }
        .word-numbered-table td,
        .word-medicine-table td,
        .word-committee-table td {
            vertical-align: top !important;
        }
        .word-approval-table {
            page-break-inside: avoid;
        }
        .indent, .numbered { text-indent: 70pt !important; }
        .section-title, .medicine-line, .committee-line { margin-left: 70pt !important; }
        .medicine-line > *:first-child { width: 24pt !important; }
        .medicine-line > *:nth-child(3), .medicine-line > *:nth-child(4) { width: 62pt !important; text-align: right !important; }
        .committee-line > *:first-child { width: 18pt !important; }
        .signature { margin-top: 32pt !important; line-height: 1.12 !important; }
        .approval { margin-top: 22pt !important; }
        .approval > *:first-child { width: 90pt !important; }
        .approval-signature { width: 215pt !important; text-align: center !important; }
CSS;
    }

    if ($profile === 'meeting') {
        return $common . <<<'CSS'
        @page Section1 { size: 210mm 297mm; margin: 25mm 20mm 20mm 20mm; }
        .report-page {
            width: 170mm !important;
            min-height: 252mm !important;
            font-size: 16pt !important;
            line-height: 1 !important;
        }
        .report-page * {
            font-size: 16pt;
            line-height: 1;
        }
        .doc-title {
            font-size: 16pt !important;
            line-height: 1 !important;
            font-weight: 700 !important;
            text-align: center !important;
        }
        .attendee-grid > *:first-child { width: 28pt !important; }
        .attendee-grid > *:nth-child(2) { width: 180pt !important; }
        .attendee-grid > *:nth-child(3) { width: 145pt !important; }
        .speech-row > *:first-child { width: 136pt !important; }
        .speech-row > *:nth-child(2) { width: 345pt !important; }
        .speaker, .speaker-line, .speech-body, .topic, .content {
            line-height: 1 !important;
        }
        .speech-body, .meeting-content-paragraph {
            text-align: justify !important;
            text-justify: inter-cluster !important;
            -ms-text-justify: distribute !important;
            text-align-last: left !important;
            letter-spacing: normal !important;
            word-spacing: normal !important;
        }
        .agenda-heading { margin-top: 7pt !important; }
        .medicine-line > *:first-child { width: 360pt !important; }
        .medicine-line .qty { width: 96pt !important; white-space: nowrap !important; }
        .medicine-table th, .medicine-table td,
        .attendance-table th, .attendance-table td {
            font-size: 16pt !important;
            line-height: 1 !important;
            padding: 3pt 5pt !important;
        }
        .signature-grid { margin-top: 34pt !important; }
        .signature-grid > * { width: 50% !important; text-align: center !important; }
        .signature-grid .chair { width: 100% !important; }
CSS;
    }

    return $common;
}

function wordEnsureXmlChild(DOMDocument $document, DOMElement $parent, string $qualifiedName, string $namespace): DOMElement
{
    foreach ($parent->childNodes as $child) {
        if ($child instanceof DOMElement && $child->namespaceURI === $namespace && $child->localName === substr($qualifiedName, strpos($qualifiedName, ':') + 1)) {
            return $child;
        }
    }
    $child = $document->createElementNS($namespace, $qualifiedName);
    $parent->appendChild($child);
    return $child;
}

function wordSetXmlAttribute(DOMElement $element, string $name, string $value, string $namespace): void
{
    $element->setAttributeNS($namespace, 'w:' . $name, $value);
}

function wordAddThaiLineBreakOpportunities(string $text): string
{
    return preg_replace_callback('/[\x{0E01}-\x{0E5B}]+/u', static function (array $matches): string {
        $thaiRun = $matches[0];
        $segments = [];
        if (class_exists('IntlBreakIterator')) {
            $iterator = IntlBreakIterator::createWordInstance('th_TH');
            if ($iterator instanceof IntlBreakIterator && $iterator->setText($thaiRun)) {
                foreach ($iterator->getPartsIterator() as $part) {
                    if ($part !== '') $segments[] = $part;
                }
            }
        }
        if (count($segments) < 2) return $thaiRun;
        return implode("\u{200B}", $segments);
    }, $text) ?? $text;
}

function wordPatchDocx(string $path, string $profile, bool $convertThaiDigits = true): void
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('ไม่สามารถเปิดไฟล์ DOCX ที่สร้างขึ้นได้');
    }

    $wordNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $documentXml = $zip->getFromName('word/document.xml');
    if (!is_string($documentXml)) {
        $zip->close();
        throw new RuntimeException('ไฟล์ DOCX ไม่มีโครงสร้างเอกสารหลัก');
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $document->preserveWhiteSpace = false;
    if (!$document->loadXML($documentXml, LIBXML_NONET | LIBXML_NOBLANKS)) {
        $zip->close();
        throw new RuntimeException('โครงสร้างเอกสาร DOCX ไม่ถูกต้อง');
    }
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', $wordNs);
    $useNaturalMemoSpacing = in_array($profile, ['principle', 'invitation'], true);
    $principleHeadings = ['ต้นเรื่อง', 'ข้อเท็จจริง', 'ข้อกฎหมาย', 'ข้อพิจารณา'];

    foreach ($xpath->query('//w:sectPr/w:pgSz') ?: [] as $pageSize) {
        if (!$pageSize instanceof DOMElement) continue;
        wordSetXmlAttribute($pageSize, 'w', '11906', $wordNs);
        wordSetXmlAttribute($pageSize, 'h', '16838', $wordNs);
    }

    foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
        if (!$paragraph instanceof DOMElement) continue;
        $paragraphProperties = wordEnsureXmlChild($document, $paragraph, 'w:pPr', $wordNs);
        if ($paragraph->firstChild !== $paragraphProperties) {
            $paragraph->insertBefore($paragraphProperties, $paragraph->firstChild);
        }
        $spacing = wordEnsureXmlChild($document, $paragraphProperties, 'w:spacing', $wordNs);
        wordSetXmlAttribute($spacing, 'before', '0', $wordNs);
        wordSetXmlAttribute($spacing, 'after', '0', $wordNs);
        wordSetXmlAttribute($spacing, 'beforeAutospacing', '0', $wordNs);
        wordSetXmlAttribute($spacing, 'afterAutospacing', '0', $wordNs);
        wordSetXmlAttribute($spacing, 'line', '240', $wordNs);
        wordSetXmlAttribute($spacing, 'lineRule', 'auto', $wordNs);
        if ($useNaturalMemoSpacing) {
            $wordWrap = wordEnsureXmlChild($document, $paragraphProperties, 'w:wordWrap', $wordNs);
            wordSetXmlAttribute($wordWrap, 'val', '1', $wordNs);
        }

        $paragraphText = '';
        foreach ($xpath->query('.//w:t', $paragraph) ?: [] as $textNode) {
            $paragraphText .= $textNode->textContent;
        }

        $alignment = $xpath->query('./w:pPr/w:jc', $paragraph)->item(0);
        if ($alignment instanceof DOMElement) {
            $value = $alignment->getAttributeNS($wordNs, 'val');
            if (in_array($value, ['both', 'justify', 'distribute', 'thaiDistribute'], true)) {
                wordSetXmlAttribute($alignment, 'val', $useNaturalMemoSpacing ? 'left' : 'thaiDistribute', $wordNs);
            }
        } elseif (mb_strlen(trim($paragraphText), 'UTF-8') >= 40) {
            $alignment = wordEnsureXmlChild($document, $paragraphProperties, 'w:jc', $wordNs);
            wordSetXmlAttribute($alignment, 'val', $useNaturalMemoSpacing ? 'left' : 'thaiDistribute', $wordNs);
        }

        // Keep the section label and the first character of its body at the same
        // horizontal position.  A 1,400-twip heading indent is exactly matched by
        // the body's 1,400-twip first-line indent, while later lines use the full
        // text width.  Standard Word justification avoids stretching Thai letters.
        if ($profile === 'principle' && in_array(trim($paragraphText), $principleHeadings, true)) {
            $indentation = wordEnsureXmlChild($document, $paragraphProperties, 'w:ind', $wordNs);
            wordSetXmlAttribute($indentation, 'left', '1400', $wordNs);
            wordSetXmlAttribute($indentation, 'right', '0', $wordNs);
            wordSetXmlAttribute($indentation, 'firstLine', '0', $wordNs);
            wordSetXmlAttribute($indentation, 'hanging', '0', $wordNs);
            $headingAlignment = wordEnsureXmlChild($document, $paragraphProperties, 'w:jc', $wordNs);
            wordSetXmlAttribute($headingAlignment, 'val', 'left', $wordNs);
        }

        $currentIndentation = $xpath->query('./w:pPr/w:ind', $paragraph)->item(0);
        $isContinuousMemoParagraph = in_array($profile, ['principle', 'invitation'], true)
            && $currentIndentation instanceof DOMElement
            && (int) $currentIndentation->getAttributeNS($wordNs, 'firstLine') > 0;
        if ($isContinuousMemoParagraph) {
            $bodyAlignment = wordEnsureXmlChild($document, $paragraphProperties, 'w:jc', $wordNs);
            wordSetXmlAttribute($bodyAlignment, 'val', 'thaiDistribute', $wordNs);
            $bodyIndentation = wordEnsureXmlChild($document, $paragraphProperties, 'w:ind', $wordNs);
            wordSetXmlAttribute($bodyIndentation, 'left', '0', $wordNs);
            wordSetXmlAttribute($bodyIndentation, 'right', '0', $wordNs);
            wordSetXmlAttribute($bodyIndentation, 'firstLine', '1400', $wordNs);
            foreach ($xpath->query('.//w:br | .//w:cr', $paragraph) ?: [] as $manualBreak) {
                $manualBreak->parentNode?->removeChild($manualBreak);
            }
        }

        $indentation = $xpath->query('./w:pPr/w:ind', $paragraph)->item(0);
        if ($indentation instanceof DOMElement) {
            $firstLine = (int) $indentation->getAttributeNS($wordNs, 'firstLine');
            if ($firstLine > 0) {
                // firstLine and hanging are mutually exclusive in WordprocessingML.
                // PHPWord emits hanging="0", which makes desktop Word ignore the
                // requested first-line indent even though firstLine is present.
                $indentation->removeAttributeNS($wordNs, 'hanging');
            }
        }

        $fontHalfPoints = trim($paragraphText) === 'บันทึกข้อความ'
            ? ($profile === 'principle' ? '56' : '58')
            : '32';
        foreach ($xpath->query('.//w:r', $paragraph) ?: [] as $run) {
            if (!$run instanceof DOMElement) continue;
            $runProperties = wordEnsureXmlChild($document, $run, 'w:rPr', $wordNs);
            if ($run->firstChild !== $runProperties) {
                $run->insertBefore($runProperties, $run->firstChild);
            }
            $fonts = wordEnsureXmlChild($document, $runProperties, 'w:rFonts', $wordNs);
            foreach (['ascii', 'hAnsi', 'eastAsia', 'cs'] as $fontAttribute) {
                wordSetXmlAttribute($fonts, $fontAttribute, WORD_PRIMARY_FONT, $wordNs);
            }
            if ($useNaturalMemoSpacing) {
                $language = wordEnsureXmlChild($document, $runProperties, 'w:lang', $wordNs);
                wordSetXmlAttribute($language, 'val', 'th-TH', $wordNs);
                wordSetXmlAttribute($language, 'eastAsia', 'th-TH', $wordNs);
                wordSetXmlAttribute($language, 'bidi', 'th-TH', $wordNs);
            }
            $size = wordEnsureXmlChild($document, $runProperties, 'w:sz', $wordNs);
            $complexSize = wordEnsureXmlChild($document, $runProperties, 'w:szCs', $wordNs);
            wordSetXmlAttribute($size, 'val', $fontHalfPoints, $wordNs);
            wordSetXmlAttribute($complexSize, 'val', $fontHalfPoints, $wordNs);
        }
    }

    if ($convertThaiDigits && in_array($profile, ['principle', 'invitation'], true)) {
        $thaiDigitMap = [
            '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
            '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
        ];
        foreach ($xpath->query('//w:t') ?: [] as $textNode) {
            $textNode->nodeValue = wordAddThaiLineBreakOpportunities(
                strtr($textNode->nodeValue ?? '', $thaiDigitMap)
            );
        }
    }

    foreach ($xpath->query('//w:tblBorders/*[@w:sz="0"]') ?: [] as $border) {
        if (!$border instanceof DOMElement) continue;
        wordSetXmlAttribute($border, 'val', 'nil', $wordNs);
        $border->removeAttributeNS($wordNs, 'sz');
    }
    $zip->addFromString('word/document.xml', (string) $document->saveXML());

    $settingsXml = $zip->getFromName('word/settings.xml');
    if (is_string($settingsXml)) {
        $settings = new DOMDocument('1.0', 'UTF-8');
        $settings->preserveWhiteSpace = false;
        if ($settings->loadXML($settingsXml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            $settingsXPath = new DOMXPath($settings);
            $settingsXPath->registerNamespace('w', $wordNs);
            foreach ($settingsXPath->query('//w:documentProtection') ?: [] as $protection) {
                $protection->parentNode?->removeChild($protection);
            }
            $zip->addFromString('word/settings.xml', (string) $settings->saveXML());
        }
    }
    $zip->close();
    wordEmbedProjectFonts($path);
}

function wordXhtmlFragment(string $html): string
{
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<?xml encoding="UTF-8"><div id="word-export-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        throw new RuntimeException('ไม่สามารถแปลงเนื้อหาเป็น DOCX ได้');
    }
    $root = $document->getElementById('word-export-root');
    if (!$root instanceof DOMElement) {
        throw new RuntimeException('ไม่พบเนื้อหาเอกสารสำหรับสร้าง DOCX');
    }
    $xhtml = '';
    foreach ($root->childNodes as $child) {
        $xhtml .= (string) $document->saveXML($child);
    }
    return $xhtml;
}

function outputWordDocxViaPhpWord(string $html, string $filename, string $css, string $profile): void
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('ยังไม่ได้ติดตั้งไลบรารีสำหรับสร้างไฟล์ DOCX');
    }
    require_once $autoload;

    $isMeeting = $profile === 'meeting' || str_contains($html, 'report-page');
    $profile = $profile === 'default' && $isMeeting ? 'meeting' : $profile;
    $filename = wordSafeFilename($filename);
    $html = wordCleanHtml($html);

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $phpWord->setDefaultFontName(WORD_PRIMARY_FONT);
    $phpWord->setDefaultFontSize(16);
    $phpWord->setDefaultParagraphStyle([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::THAI_DISTRIBUTE,
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'spacing' => 0,
        'lineHeight' => 1.0,
        'contextualSpacing' => false,
    ]);

    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => 1417,
        'marginRight' => 1134,
        'marginBottom' => 1134,
        'marginLeft' => $isMeeting ? 1134 : 1701,
        'headerHeight' => 0,
        'footerHeight' => 0,
    ]);
    $docxCss = <<<'CSS'
        body, p, div, span, td, th, h1, h2, h3, h4, h5, h6 {
            font-family: "TH SarabunIT๙";
            font-size: 16pt;
            line-height: 1;
        }
        p { margin: 0; text-align: justify; }
CSS;
    $docxCss .= "\n" . $css . "\n" . wordProfileCss($profile);
    $docxCss = preg_replace('/\s*!important\b/i', '', $docxCss) ?? $docxCss;
    $xhtml = wordXhtmlFragment('<style>' . $docxCss . '</style>' . $html);
    $phpWordWarningHandler = static function (int $severity, string $message, string $file): bool {
        $isPhpWord = str_contains(str_replace('\\', '/', $file), '/vendor/phpoffice/phpword/');
        return $isPhpWord && in_array($severity, [E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true);
    };
    set_error_handler($phpWordWarningHandler);
    try {
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $xhtml, false, false);
    } finally {
        restore_error_handler();
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'torskp_docx_');
    if ($tempPath === false) {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ DOCX ชั่วคราวได้');
    }
    try {
        set_error_handler($phpWordWarningHandler);
        try {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($tempPath);
        } finally {
            restore_error_handler();
        }
        wordPatchDocx($tempPath, $profile);

        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header("Content-Disposition: attachment; filename=\"$filename\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . (string) filesize($tempPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($tempPath);
    } finally {
        if (is_file($tempPath)) unlink($tempPath);
    }
    exit;
}

function wordAddMsoParagraphClasses(string $html): string
{
    return preg_replace_callback('/<p\b([^>]*)>/i', static function (array $match): string {
        $attributes = $match[1];
        if (preg_match('/\bclass=("|\')([^"\']*)\1/i', $attributes)) {
            $attributes = preg_replace('/\bclass=("|\')([^"\']*)\1/i', 'class=$1$2 MsoNormal$1', $attributes, 1) ?? $attributes;
        } else {
            $attributes = ' class="MsoNormal"' . $attributes;
        }
        return '<p' . $attributes . '>';
    }, $html) ?? $html;
}

function wordAltChunkHtml(string $html, string $css, string $profile): string
{
    $titleSize = $profile === 'principle' ? '28pt' : '29pt';
    $bodyFont = WORD_PRIMARY_FONT;
    if ($profile === 'tor') {
        $css = preg_replace('/text-align\s*:\s*justify\s*;?/i', 'text-align:left;', $css) ?? $css;
        $css = preg_replace('/(?<!-)text-justify\s*:\s*[^;}]+;?/i', 'text-justify:auto;', $css) ?? $css;
        $css = preg_replace('/-ms-text-justify\s*:\s*[^;}]+;?/i', '-ms-text-justify:auto;', $css) ?? $css;
    }
    $normalParagraphAlignment = in_array($profile, ['meeting', 'tor'], true)
        ? 'text-align:left!important;text-justify:auto!important;-ms-text-justify:auto!important;letter-spacing:normal!important;word-spacing:normal!important'
        : 'text-align:justify;text-justify:inter-cluster';
    $flowParagraphAlignment = in_array($profile, ['meeting', 'tor'], true)
        ? 'text-align:left!important;text-justify:auto!important;-ms-text-justify:auto!important;letter-spacing:normal!important;word-spacing:normal!important'
        : 'text-align:justify;text-justify:inter-cluster';
    // DOMDocument serializes the Thai digit in CSS as an HTML entity. Word's
    // HTML importer does not decode entities inside quoted font-family values,
    // so normalize every legacy/encoded spelling before creating the altChunk.
    $fontAliases = ['TH Sarabun New', 'TH SarabunIT&#3673;', 'TH SarabunIT&#xE59;', 'TH SarabunIT&#xe59;'];
    $css = str_replace($fontAliases, WORD_PRIMARY_FONT, $css);
    $html = str_replace($fontAliases, WORD_PRIMARY_FONT, $html);
    $torTypographyCss = $profile === 'tor'
        ? '.Section1,.Section1 *{font-family:"TH SarabunIT๙"!important;mso-ascii-font-family:"TH SarabunIT๙"!important;'
            . 'mso-fareast-font-family:"TH SarabunIT๙"!important;mso-hansi-font-family:"TH SarabunIT๙"!important;'
            . 'mso-bidi-font-family:"TH SarabunIT๙"!important;font-size:16pt!important}'
            . '.Section1 p,.Section1 h1,.Section1 h2,.Section1 .indent,.Section1 .subindent,'
            . '.Section1 .price-note,.Section1 .section-title{margin-top:0!important;margin-bottom:0!important;'
            . 'mso-margin-top-alt:0pt!important;mso-margin-bottom-alt:0pt!important;line-height:16pt!important;'
            . 'mso-line-height-rule:exactly!important}'
            . '.Section1 .tor-continuous-flow p,.Section1 .tor-continuous-flow .subindent{'
            . 'white-space:normal!important;text-align:left!important;text-justify:auto!important;'
            . '-ms-text-justify:auto!important;letter-spacing:normal!important;word-spacing:normal!important}'
        : '';
    $meetingTypographyCss = $profile === 'meeting'
        ? '.Section1 p.MsoNormal.thai-distributed-speech{'
            . 'margin-top:0!important;margin-bottom:0!important;'
            . 'mso-margin-top-alt:0pt!important;mso-margin-bottom-alt:0pt!important;'
            . 'line-height:16pt!important;mso-line-height-rule:exactly!important;'
            . 'text-align:justify!important;text-justify:inter-cluster!important;'
            . '-ms-text-justify:distribute!important;text-align-last:left!important;'
            . 'letter-spacing:normal!important;word-spacing:normal!important}'
        : '';
    $html = wordAddMsoParagraphClasses(wordCleanHtml($html));
    if ($profile === 'tor') {
        $html = preg_replace(
            '/<div\b[^>]*class=("|\')[^"\']*\bword-inline-page-number\b[^"\']*\1[^>]*>.*?<\/div>/is',
            '<div style="page-break-before:always;break-before:page;height:1pt;line-height:1pt;font-size:1pt;margin:0;padding:0">&nbsp;</div>',
            $html
        ) ?? $html;
        $html = wordAddThaiLineBreakOpportunities($html);
    }
    $html = str_replace('>บันทึกข้อความ<', '><b>บันทึกข้อความ</b><', $html);
    $profileCss = wordProfileCss($profile);

    return '<!doctype html><html xmlns:o="urn:schemas-microsoft-com:office:office" '
        . 'xmlns:w="urn:schemas-microsoft-com:office:word" lang="th"><head><meta charset="utf-8"><style>'
        . 'p.MsoNormal{mso-style-name:Normal;margin:0cm;margin-bottom:.0001pt;line-height:16pt;'
        . 'mso-line-height-rule:exactly;font-family:"' . $bodyFont . '";font-size:16pt}'
        . $css . "\n" . $profileCss
        . '.Section1,.Section1 *{font-family:"' . $bodyFont . '","TH Sarabun New","Sarabun",sans-serif!important;font-size:16pt}'
        . '.Section1 .memo-title,.Section1 .memo-title *{font-size:' . $titleSize . ';font-weight:700}'
        . '.Section1 p.MsoNormal{margin:0cm!important;margin-bottom:.0001pt!important;'
        . 'mso-margin-top-alt:0pt;mso-margin-bottom-alt:0pt;line-height:16pt!important;'
        . 'mso-line-height-rule:exactly;' . $normalParagraphAlignment . '}'
        . '.Section1 p,.Section1 .meta,.Section1 .meta-line,.Section1 .memo-no-date,'
        . '.Section1 .section-title,.Section1 .numbered,.Section1 .signature,'
        . '.Section1 .approval,.Section1 .agenda-heading,.Section1 .signature-grid{'
        . 'margin-top:0!important;margin-bottom:0!important;'
        . 'mso-margin-top-alt:0pt!important;mso-margin-bottom-alt:0pt!important}'
        . '.Section1 .legal-item,.Section1 .consideration-item,.Section1 .content,'
        . '.Section1 .speech-body,.Section1 .agenda-detail,.Section1 .indent,'
        . '.Section1 .subindent,.Section1 .justify,.Section1 .thai-justify{'
        . 'margin-top:0;margin-bottom:0;line-height:16pt;mso-line-height-rule:exactly;'
        . $flowParagraphAlignment . '}'
        . $torTypographyCss
        . $meetingTypographyCss
        . '</style></head><body><div class="Section1">' . $html . '</div></body></html>';
}

function wordCreateAltChunkDocx(string $path, string $html, bool $isMeeting, string $fontName = WORD_PRIMARY_FONT, bool $numberedHeader = false): void
{
    $leftMargin = $isMeeting ? '1134' : '1701';
    $fontNameXml = htmlspecialchars($fontName, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $headerContentTypes = $numberedHeader
        ? '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/header2.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
        : '';
    $headerRelationships = $numberedHeader
        ? '<Relationship Id="rIdHeaderDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
            . '<Relationship Id="rIdHeaderFirst" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header2.xml"/>'
        : '';
    $headerReferences = $numberedHeader
        ? '<w:headerReference w:type="default" r:id="rIdHeaderDefault"/><w:headerReference w:type="first" r:id="rIdHeaderFirst"/><w:titlePg/>'
        : '';
    $headerDistance = $numberedHeader ? '567' : '0';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ DOCX ได้');
    }
    try {
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="html" ContentType="text/html"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . $headerContentTypes
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'
            . '<w:altChunk r:id="htmlChunk"/><w:sectPr>' . $headerReferences . '<w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1417" w:right="1134" w:bottom="1134" w:left="' . $leftMargin . '" '
            . 'w:header="' . $headerDistance . '" w:footer="0" w:gutter="0"/></w:sectPr></w:body></w:document>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="htmlChunk" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="afchunk.html"/>'
            . '<Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . $headerRelationships
            . '</Relationships>');
        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults>'
            . '<w:rPrDefault><w:rPr><w:rFonts w:ascii="' . $fontNameXml . '" w:hAnsi="' . $fontNameXml . '" '
            . 'w:eastAsia="' . $fontNameXml . '" w:cs="' . $fontNameXml . '"/><w:sz w:val="32"/><w:szCs w:val="32"/>'
            . '</w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:before="0" w:after="0" '
            . 'w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/></w:pPr>'
            . '<w:rPr><w:rFonts w:ascii="' . $fontNameXml . '" w:hAnsi="' . $fontNameXml . '" w:eastAsia="' . $fontNameXml . '" '
            . 'w:cs="' . $fontNameXml . '"/><w:sz w:val="32"/><w:szCs w:val="32"/></w:rPr></w:style></w:styles>');
        if ($numberedHeader) {
            $headerRunProperties = '<w:rPr><w:rFonts w:ascii="' . $fontNameXml . '" w:hAnsi="' . $fontNameXml . '" w:eastAsia="' . $fontNameXml . '" w:cs="' . $fontNameXml . '"/>'
                . '<w:sz w:val="32"/><w:szCs w:val="32"/><w:lang w:val="th-TH" w:eastAsia="th-TH" w:bidi="th-TH"/></w:rPr>';
            $zip->addFromString('word/header1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:jc w:val="center"/>'
                . '<w:spacing w:before="0" w:after="0" w:line="240" w:lineRule="auto"/></w:pPr>'
                . '<w:r>' . $headerRunProperties . '<w:t xml:space="preserve">- </w:t></w:r>'
                . '<w:fldSimple w:instr=" PAGE \\* ARABIC "><w:r>' . $headerRunProperties . '<w:t>๒</w:t></w:r></w:fldSimple>'
                . '<w:r>' . $headerRunProperties . '<w:t xml:space="preserve"> -</w:t></w:r></w:p></w:hdr>');
            $zip->addFromString('word/header2.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr></w:p></w:hdr>');
        }
        $zip->addFromString('word/afchunk.html', "\xEF\xBB\xBF" . $html);
    } finally {
        $zip->close();
    }
    wordEmbedProjectFonts($path);
}

function outputWordDocx(string $html, string $filename, string $css, string $profile): void
{
    $isMeeting = $profile === 'meeting' || str_contains($html, 'report-page');
    if ($profile === 'default' && $isMeeting) $profile = 'meeting';
    $filename = wordSafeFilename($filename);
    $documentHtml = wordAltChunkHtml($html, $css, $profile);
    $tempPath = tempnam(sys_get_temp_dir(), 'torskp_docx_');
    if ($tempPath === false) throw new RuntimeException('ไม่สามารถสร้างไฟล์ DOCX ชั่วคราวได้');

    try {
        wordCreateAltChunkDocx(
            $tempPath,
            $documentHtml,
            $isMeeting,
            WORD_PRIMARY_FONT,
            $profile === 'tor'
        );
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header("Content-Disposition: attachment; filename=\"document.docx\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . (string) filesize($tempPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($tempPath);
    } finally {
        if (is_file($tempPath)) unlink($tempPath);
    }
    exit;
}

function wordNativeFont(array $overrides = []): array
{
    return array_merge([
        'name' => WORD_PRIMARY_FONT,
        'size' => 16,
        'color' => '111111',
    ], $overrides);
}

function wordNativeParagraph(array $overrides = []): array
{
    return array_merge([
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'spacing' => 0,
        'lineHeight' => 1.0,
        'contextualSpacing' => false,
    ], $overrides);
}

function wordNativeAddText(
    \PhpOffice\PhpWord\Element\AbstractContainer $container,
    string $text,
    array $font = [],
    array $paragraph = []
): \PhpOffice\PhpWord\Element\TextRun {
    $run = $container->addTextRun(wordNativeParagraph($paragraph));
    $run->addText($text, wordNativeFont($font));
    return $run;
}

function wordNativeAddLabelLine(
    \PhpOffice\PhpWord\Element\AbstractContainer $container,
    string $label,
    string $text,
    bool $labelLarge = true
): void {
    $run = $container->addTextRun(wordNativeParagraph());
    $run->addText($label . ' ', wordNativeFont(['bold' => true, 'size' => $labelLarge ? 20 : 16]));
    $run->addText($text, wordNativeFont());
}

function outputPrincipleApprovalMemoDocx(array $data, string $filename, ?string $destinationPath = null): void
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('ยังไม่ได้ติดตั้งไลบรารีสำหรับสร้างไฟล์ DOCX');
    }
    require_once $autoload;

    $templatePath = dirname(__DIR__) . '/templates/docx/principle_approval_memo.docx';
    if ($destinationPath === null && is_file($templatePath)) {
        $tempPath = tempnam(sys_get_temp_dir(), 'torskp_template_docx_');
        if ($tempPath === false) throw new RuntimeException('ไม่สามารถสร้างไฟล์ DOCX ชั่วคราวได้');
        try {
            $processor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            $revision = trim((string) ($data['plan_revision_note'] ?? ''));
            $directorLines = preg_split('/\R/u', (string) ($data['director_position'] ?? '')) ?: [];
            $processor->setValues([
                'department' => (string) ($data['department'] ?? ''),
                'memo_no_suffix' => (string) ($data['memo_no_suffix'] ?? ''),
                'doc_date' => (string) ($data['doc_date'] ?? ''),
                'fiscal_year' => (string) ($data['fiscal_year'] ?? ''),
                'plan_revision_clause' => $revision !== '' ? ' (' . $revision . ')' : '',
                'plan_approved_date' => (string) ($data['plan_approved_date'] ?? ''),
                'budget_amount' => (string) ($data['budget_amount'] ?? ''),
                'budget_amount_text' => (string) ($data['budget_amount_text'] ?? ''),
                'network_count' => (string) ($data['network_count'] ?? ''),
                'item_count' => (string) ($data['item_count'] ?? ''),
                'signer_name' => (string) ($data['signer_name'] ?? ''),
                'signer_position' => (string) ($data['signer_position'] ?? ''),
                'director_name' => (string) ($data['director_name'] ?? ''),
                'director_position_1' => (string) ($directorLines[0] ?? ''),
                'director_position_2' => (string) ($directorLines[1] ?? ''),
                'director_position_3' => (string) ($directorLines[2] ?? ''),
            ]);
            $itemRows = [];
            foreach ((array) ($data['items'] ?? []) as $index => $item) {
                $number = function_exists('thaiDigits') ? thaiDigits((string) ($index + 1)) : (string) ($index + 1);
                $quantity = function_exists('quantityText')
                    ? quantityText((string) ($item['quantity'] ?? ''))
                    : (string) ($item['quantity'] ?? '');
                $itemRows[] = [
                    'item_no' => $number . '.',
                    'item_name' => (string) ($item['name'] ?? ''),
                    'item_quantity' => $quantity,
                    'item_unit' => (string) ($item['unit'] ?? ''),
                ];
            }
            $processor->cloneRowAndSetValues('item_no', $itemRows ?: [[
                'item_no' => '', 'item_name' => '', 'item_quantity' => '', 'item_unit' => '',
            ]]);
            $committeeRows = [];
            foreach ((array) ($data['committee'] ?? []) as $index => $person) {
                $number = function_exists('thaiDigits') ? thaiDigits((string) ($index + 1)) : (string) ($index + 1);
                $committeeRows[] = [
                    'committee_no' => $number . '.',
                    'committee_name' => (string) ($person['name'] ?? ''),
                    'committee_position' => (string) ($person['position'] ?? ''),
                    'committee_role' => (string) ($person['role'] ?? ''),
                ];
            }
            $processor->cloneRowAndSetValues('committee_no', $committeeRows ?: [[
                'committee_no' => '', 'committee_name' => '', 'committee_position' => '', 'committee_role' => '',
            ]]);
            $processor->saveAs($tempPath);
            wordPatchDocx($tempPath, 'principle');

            $filename = wordSafeFilename($filename);
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header("Content-Disposition: attachment; filename=\"document.docx\"; filename*=UTF-8''" . rawurlencode($filename));
            header('Content-Length: ' . (string) filesize($tempPath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('X-Content-Type-Options: nosniff');
            readfile($tempPath);
        } finally {
            if (is_file($tempPath)) unlink($tempPath);
        }
        exit;
    }

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $phpWord->setDefaultFontName(WORD_PRIMARY_FONT);
    $phpWord->setDefaultFontSize(16);
    $phpWord->setDefaultParagraphStyle(wordNativeParagraph());
    $phpWord->addParagraphStyle('TorBody', wordNativeParagraph([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
        'indentation' => ['firstLine' => 1400],
    ]));
    $phpWord->addParagraphStyle('TorLegal', wordNativeParagraph([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
        'indentation' => ['firstLine' => 1400],
    ]));
    $phpWord->addParagraphStyle('TorHeading', wordNativeParagraph([
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT,
        'indentation' => ['left' => 1400],
        'keepNext' => true,
    ]));

    $section = $phpWord->addSection([
        'paperSize' => 'A4',
        'marginTop' => 1417,
        'marginRight' => 1134,
        'marginBottom' => 1134,
        'marginLeft' => 1701,
        'headerHeight' => 0,
        'footerHeight' => 0,
    ]);

    $header = $section->addTable([
        'width' => 9072,
        'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        'layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,
        'borderSize' => 0,
        'cellMargin' => 0,
    ]);
    $headerRow = $header->addRow();
    $logoCell = $headerRow->addCell(1701, ['valign' => 'top']);
    $logoParagraph = $logoCell->addTextRun(wordNativeParagraph());
    $logoPath = dirname(__DIR__) . '/picture/picture-1.png';
    if (is_file($logoPath)) {
        $logoParagraph->addImage($logoPath, ['height' => 57, 'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT]);
    }
    $titleCell = $headerRow->addCell(5670, ['valign' => 'top']);
    wordNativeAddText($titleCell, 'บันทึกข้อความ', ['size' => 29, 'bold' => true], [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
    ]);
    $headerRow->addCell(1701, ['valign' => 'top']);

    wordNativeAddLabelLine($section, 'ส่วนราชการ', (string) ($data['department'] ?? ''));
    $meta = $section->addTable([
        'width' => 9072,
        'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        'layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,
        'borderSize' => 0,
        'cellMargin' => 0,
    ]);
    $metaRow = $meta->addRow();
    $c1 = $metaRow->addCell(700);
    wordNativeAddText($c1, 'ที่', ['bold' => true, 'size' => 20]);
    $c2 = $metaRow->addCell(4450);
    wordNativeAddText($c2, 'ชม.๕๑๐๐๖.๑๓.๑/' . (string) ($data['memo_no_suffix'] ?? ''));
    $c3 = $metaRow->addCell(900);
    wordNativeAddText($c3, 'วันที่', ['bold' => true, 'size' => 20]);
    $c4 = $metaRow->addCell(3022);
    wordNativeAddText($c4, (string) ($data['doc_date'] ?? ''), [], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

    wordNativeAddLabelLine(
        $section,
        'เรื่อง',
        'ขออนุมัติในหลักการเพื่อดำเนินการจัดซื้อยา จำนวน ' . (string) ($data['item_count'] ?? '') . ' รายการ'
    );
    wordNativeAddLabelLine($section, 'เรียน', 'นายกองค์การบริหารส่วนจังหวัดเชียงใหม่', false);

    $addHeading = static function (string $text) use ($section): void {
        wordNativeAddText($section, $text, ['bold' => true], ['styleName' => 'TorHeading', 'indentation' => ['left' => 1400], 'keepNext' => true]);
    };
    $addBody = static function (string $text) use ($section): void {
        wordNativeAddText($section, $text, [], [
            'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::BOTH,
            'indentation' => ['firstLine' => 1400],
        ]);
    };

    $addHeading('ต้นเรื่อง');
    $revision = trim((string) ($data['plan_revision_note'] ?? ''));
    $revisionClause = array_key_exists('plan_revision_clause', $data)
        ? (string) $data['plan_revision_clause']
        : ($revision !== '' ? ' (' . $revision . ')' : '');
    $addBody(
        'ตาม โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ในสังกัดองค์การบริหารส่วนจังหวัดเชียงใหม่ประกาศแผนการใช้จ่ายเงินประจำปีงบประมาณ พ.ศ. '
        . (string) ($data['fiscal_year'] ?? '')
        . $revisionClause
        . ' อนุมัติให้ใช้เมื่อวันที่ ' . (string) ($data['plan_approved_date'] ?? '')
        . ' หมวดยาและเวชภัณฑ์มิใช่ยา หมวด 1 ยา ที่ตั้งไว้ ' . (string) ($data['budget_amount'] ?? '')
        . ' บาท (' . (string) ($data['budget_amount_text'] ?? '')
        . ') ตามหลักการจำแนกประเภทรายจ่ายตามงบประมาณขององค์กรปกครองส่วนท้องถิ่นเป็นไปตามระเบียบกระทรวงมหาดไทย ด้วยการเบิกค่าใช้จ่ายในการบริหารงานขององค์กรปกครองส่วนท้องถิ่น พ.ศ. ๒๕๖๒'
    );

    $addHeading('ข้อเท็จจริง');
    $addBody(
        'หน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง มีความประสงค์จัดซื้อยา เพื่อใช้ในการรักษาพยาบาลแก่ประชาชนในเขตพื้นที่รับผิดชอบ หน่วยบริการประจำ (CUP) โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้งและหน่วยบริการปฐมภูมิในเครือข่ายจำนวน '
        . (string) ($data['network_count'] ?? '') . ' แห่ง อำเภอสันกำแพง ตามแผนงบประมาณเงินบำรุงประเภทยาและเวชภัณฑ์ที่มิใช่ยา ปีพ.ศ.'
        . (string) ($data['fiscal_year'] ?? '')
        . ' โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง เนื่องจากมีปริมาณยาคงคลังไม่เพียงพอต่อการให้บริการประชาชนและเพื่อความต่อเนื่องในการปฏิบัติงานของเจ้าหน้าที่ จำนวน '
        . (string) ($data['item_count'] ?? '') . ' รายการ ได้แก่'
    );

    $medicineTable = $section->addTable([
        'width' => 7640,
        'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        'layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,
        'indent' => new \PhpOffice\PhpWord\ComplexType\TblWidth(1400),
        'borderSize' => 0,
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => 60,
        'cellMarginRight' => 60,
    ]);
    foreach ((array) ($data['items'] ?? []) as $index => $item) {
        $row = $medicineTable->addRow();
        $number = (string) ($item['item_no'] ?? (function_exists('thaiDigits') ? thaiDigits((string) ($index + 1)) . '.' : (string) ($index + 1) . '.'));
        $quantity = (string) ($item['item_quantity'] ?? (function_exists('quantityText')
            ? quantityText((string) ($item['quantity'] ?? ''))
            : (string) ($item['quantity'] ?? '')));
        wordNativeAddText($row->addCell(480), $number);
        wordNativeAddText($row->addCell(4280), (string) ($item['item_name'] ?? $item['name'] ?? ''));
        wordNativeAddText($row->addCell(1240), $quantity, [], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::RIGHT]);
        wordNativeAddText($row->addCell(1640), (string) ($item['item_unit'] ?? $item['unit'] ?? ''));
    }

    $addHeading('ข้อกฎหมาย');
    $legalItems = [
        '๑.ตามพระราชบัญญัติการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. ๒๕๖๐ มาตรา ๔  ในพระราชบัญญัตินี้ “ราคากลาง” หมายความว่า ราคาเพื่อใช้เป็นฐานสำหรับเปรียบเทียบราคาที่ผู้ยื่นข้อเสนอได้ยื่นเสนอไว้ซึ่งสามารถจัดซื้อจัดจ้างได้จริงตามลำดับ ดังต่อไปนี้ (๑) ราคาที่ได้มาจากการคำนวณตามหลักเกณฑ์ที่คณะกรรมการราคากลางกำหนด (๒) ราคาที่ได้มาจากฐานข้อมูลราคาอ้างอิงของพัสดุที่กรมบัญชีกลางจัดทำ (๓) ราคามาตรฐานที่สำนักงบประมาณหรือหน่วยงานกลางอื่นกำหนด (๔) ราคาที่ได้มาจากการสืบราคาจากท้องตลาด (๕) ราคาที่เคยซื้อหรือจ้างครั้งหลังสุดภายในระยะเวลาสองปีงบประมาณ (๖) ราคาอื่นใดตามหลักเกณฑ์ วิธีการ หรือแนวทางปฏิบัติของหน่วยงานของรัฐนั้น ๆในกรณีที่มีราคาตาม (๑) ให้ใช้ราคาตาม (๑) ก่อน ในกรณีที่ไม่มีราคาตาม (๑) แต่มีราคาตาม (๒)หรือ (๓) ให้ใช้ราคาตาม (๒) หรือ (๓) ก่อน โดยจะใช้ราคาใดตาม (๒) หรือ (๓) ให้คำนึงถึงประโยชน์ของหน่วยงานของรัฐเป็นสำคัญ ในกรณีที่ไม่มีราคาตาม (๑) (๒) และ (๓) ให้ใช้ราคาตาม (๔) (๕) หรือ (๖)โดยจะใช้ราคาใดตาม (๔) (๕) หรือ (๖) ให้คำนึงถึงประโยชน์ของหน่วยงานของรัฐเป็นสำคัญ',
        '๒. ข้อ ๒๑ ในการซื้อหรือจ้างที่มิใช่การจ้างก่อสร้าง ให้หัวหน้าหน่วยงานของรัฐแต่งตั้ง คณะกรรมการขึ้นมาคณะหนึ่ง หรือจะให้เจ้าหน้าที่หรือบุคคลใดบุคคลหนึ่งรับผิดชอบในการจัดทำร่างขอบเขตของงาน หรือรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง รวมทั้งกำหนดหลักเกณฑ์การพิจารณาคัดเลือกข้อเสนอด้วย เพื่อให้การกำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้างมีมาตรฐานและเป็นประโยชน์ต่อทางราชการ หากพัสดุที่จะซื้อหรือจ้างใดมีประกาศกำหนดมาตรฐานผลิตภัณฑ์อุตสาหกรรมแล้วให้กำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้าง หรือรายการในการก่อสร้างตามมาตรฐานผลิตภัณฑ์อุตสาหกรรม หรือเพื่อความสะดวกจะระบุเฉพาะหมายเลขมาตรฐานก็ได้หรือในกรณีพัสดุ ที่จะซื้อหรือจ้างใดยังไม่มีประกาศกำหนดมาตรฐานผลิตภัณฑ์อุตสาหกรรมแต่มีผู้ได้รับการจดทะเบียนผลิตภัณฑ์ไว้กับกระทรวงอุตสาหกรรมแล้ว ให้กำหนดรายละเอียดคุณลักษณะเฉพาะของพัสดุที่จะซื้อหรือจ้างหรือรายการในการก่อสร้างให้สอดคล้องกับรายละเอียดคุณลักษณะเฉพาะตามที่ระบุในคู่มือผู้ซื้อหรือใบแทรกคู่มือผู้ซื้อ ที่กระทรวงอุตสาหกรรมจัดทำขึ้น',
        '๓. ตามหนังสือ คณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ ด่วนที่สุด ที่ กค(กวจ) ๐๔๐๕.๒/ว78 ลงวันที่ 31 มกราคม ๒๕๖5 เรื่อง อนุมัติยกเว้นและกำหนดแนวทางแนวทางปฏิบัติตามกฎกระทรวง กำหนดพัสดุและวิธีการจัดซื้อจัดจ้างพัสดุ ที่รัฐต้องการส่งเสริมหรือสนับสนุน (ฉบับที่ ๒) พ.ศ.๒๕๖๓',
        '๔. ตามหนังสือ คณะกรรมการวินิจฉัยปัญหาการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ด่วนที่สุด ที่ กค(กวจ) ๐๔๐๕.๒/ว๕๖ ลงวันที่ ๒๔ มกราคม ๒๕๖๖ เรื่อง แนวทางปฏิบัติสำหรับการส่งเสริมหรือสนับสนุนผู้ประกอบการวิสาหกิจขนาดกลางและขนาดย่อม (SMEs) เพิ่มเติม',
    ];
    foreach ($legalItems as $legalItem) $addBody($legalItem);

    $addHeading('ข้อพิจารณา');
    $addBody('เพื่อให้เป็นไปด้วยความเรียบร้อย ถูกต้องตามระเบียบกฎหมาย จึงขออนุมัติดำเนินการดังต่อไปนี้');
    $addBody('๑.อนุมัติให้ดำเนินการจัดซื้อยา จำนวน ' . (string) ($data['item_count'] ?? '') . ' รายการ');
    $addBody('๒.ขออนุมัติแต่งตั้งผู้กำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง การจัดซื้อยา โดยให้ใช้หนังสือบันทึกข้อความฉบับนี้แทนคำสั่งแต่งตั้งคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลางโดยอนุโลม ดังรายชื่อต่อไปนี้');

    $committeeTable = $section->addTable([
        'width' => 7640,
        'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        'layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,
        'indent' => new \PhpOffice\PhpWord\ComplexType\TblWidth(1400),
        'borderSize' => 0,
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => 60,
        'cellMarginRight' => 60,
    ]);
    foreach ((array) ($data['committee'] ?? []) as $index => $person) {
        $row = $committeeTable->addRow();
        $number = (string) ($person['committee_no'] ?? (function_exists('thaiDigits') ? thaiDigits((string) ($index + 1)) . '.' : (string) ($index + 1) . '.'));
        wordNativeAddText($row->addCell(360), $number);
        wordNativeAddText($row->addCell(2660), (string) ($person['committee_name'] ?? $person['name'] ?? ''));
        wordNativeAddText($row->addCell(2320), (string) ($person['committee_position'] ?? $person['position'] ?? ''));
        wordNativeAddText($row->addCell(2300), (string) ($person['committee_role'] ?? $person['role'] ?? ''));
    }

    $addBody('จึงเรียนมาเพื่อโปรดพิจารณา หากเห็นชอบโปรดอนุมัติในหลักการเพื่อจะได้ดำเนินการตามระเบียบพัสดุฯ ต่อไป');
    wordNativeAddText($section, '', [], ['keepNext' => true]);
    wordNativeAddText($section, '(' . (string) ($data['signer_name'] ?? '') . ')', [], [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'keepNext' => true,
    ]);
    wordNativeAddText($section, (string) ($data['signer_position'] ?? ''), [], [
        'alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'keepLines' => true,
    ]);
    wordNativeAddText($section, '', [], ['keepNext' => true]);

    $approval = $section->addTable([
        'width' => 9072,
        'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,
        'layout' => \PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,
        'borderSize' => 0,
        'cellMargin' => 0,
    ]);
    $approvalRow = $approval->addRow();
    $note = $approvalRow->addCell(1800);
    wordNativeAddText($note, '-เห็นชอบ');
    wordNativeAddText($note, '-อนุมัติ');
    $signature = $approvalRow->addCell(4400);
    wordNativeAddText($signature, '');
    wordNativeAddText($signature, '(' . (string) ($data['director_name'] ?? '') . ')', [], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    $directorLines = isset($data['director_position_lines']) && is_array($data['director_position_lines'])
        ? $data['director_position_lines']
        : (preg_split('/\R/u', (string) ($data['director_position'] ?? '')) ?: []);
    foreach ($directorLines as $line) {
        wordNativeAddText($signature, $line, [], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    }
    $approvalRow->addCell(2872);

    $tempPath = tempnam(sys_get_temp_dir(), 'torskp_native_docx_');
    if ($tempPath === false) throw new RuntimeException('ไม่สามารถสร้างไฟล์ DOCX ชั่วคราวได้');
    try {
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);
        wordPatchDocx($tempPath, 'principle', $destinationPath === null);
        if ($destinationPath !== null) {
            $directory = dirname($destinationPath);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์แม่แบบ DOCX ได้');
            }
            if (!copy($tempPath, $destinationPath)) {
                throw new RuntimeException('ไม่สามารถบันทึกแม่แบบ DOCX ได้');
            }
            return;
        }
        $filename = wordSafeFilename($filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header("Content-Disposition: attachment; filename=\"document.docx\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . (string) filesize($tempPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($tempPath);
    } finally {
        if (is_file($tempPath)) unlink($tempPath);
    }
    exit;
}

function outputCommitteeScopeInvitationDocx(array $data, string $filename, ?string $destinationPath = null): void
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('ยังไม่ได้ติดตั้งไลบรารีสำหรับสร้างไฟล์ DOCX');
    require_once $autoload;

    $templatePath = dirname(__DIR__) . '/templates/docx/committee_scope_invitation.docx';
    if ($destinationPath === null && is_file($templatePath)) {
        $tempPath = tempnam(sys_get_temp_dir(), 'torskp_template_docx_');
        if ($tempPath === false) throw new RuntimeException('ไม่สามารถสร้างไฟล์ DOCX ชั่วคราวได้');
        try {
            $processor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);
            $positionLines = preg_split('/\R/u', (string) ($data['signer_position'] ?? '')) ?: [];
            $processor->setValues([
                'department' => (string) ($data['department'] ?? ''),
                'memo_no_suffix' => (string) ($data['memo_no_suffix'] ?? ''),
                'doc_date' => (string) ($data['doc_date'] ?? ''),
                'subject' => (string) ($data['subject'] ?? ''),
                'item_count' => (string) ($data['item_count'] ?? ''),
                'fiscal_year' => (string) ($data['fiscal_year'] ?? ''),
                'principle_no_suffix' => (string) ($data['principle_no_suffix'] ?? ''),
                'principle_date' => (string) ($data['principle_date'] ?? ''),
                'meeting_date' => (string) ($data['meeting_date'] ?? ''),
                'meeting_time' => (string) ($data['meeting_time'] ?? ''),
                'location' => (string) ($data['location'] ?? ''),
                'signer_signature' => (string) ($data['signer_signature'] ?? ''),
                'signer_name' => (string) ($data['signer_name'] ?? ''),
                'signer_position_1' => (string) ($positionLines[0] ?? ''),
                'signer_position_2' => (string) ($positionLines[1] ?? ''),
                'signer_position_3' => (string) ($positionLines[2] ?? ''),
            ]);
            $processor->saveAs($tempPath);
            wordPatchDocx($tempPath, 'invitation');
            $filename = wordSafeFilename($filename);
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header("Content-Disposition: attachment; filename=\"document.docx\"; filename*=UTF-8''" . rawurlencode($filename));
            header('Content-Length: ' . (string) filesize($tempPath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('X-Content-Type-Options: nosniff');
            readfile($tempPath);
        } finally {
            if (is_file($tempPath)) unlink($tempPath);
        }
        exit;
    }

    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $phpWord->setDefaultFontName(WORD_PRIMARY_FONT);
    $phpWord->setDefaultFontSize(16);
    $phpWord->setDefaultParagraphStyle(wordNativeParagraph());
    $section = $phpWord->addSection([
        'paperSize' => 'A4', 'marginTop' => 1417, 'marginRight' => 1134,
        'marginBottom' => 1134, 'marginLeft' => 1701, 'headerHeight' => 0, 'footerHeight' => 0,
    ]);

    $header = $section->addTable(['width'=>9072,'unit'=>\PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,'layout'=>\PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,'borderSize'=>0,'cellMargin'=>0]);
    $row = $header->addRow();
    $logo = $row->addCell(1701);
    $logoRun = $logo->addTextRun(wordNativeParagraph());
    $logoPath = dirname(__DIR__) . '/picture/picture-1.png';
    if (is_file($logoPath)) $logoRun->addImage($logoPath, ['height'=>57]);
    wordNativeAddText($row->addCell(5670), 'บันทึกข้อความ', ['size'=>29,'bold'=>true], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    $row->addCell(1701);
    wordNativeAddLabelLine($section, 'ส่วนราชการ', (string) ($data['department'] ?? ''));

    $meta = $section->addTable(['width'=>9072,'unit'=>\PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,'layout'=>\PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,'borderSize'=>0,'cellMargin'=>0]);
    $row = $meta->addRow();
    wordNativeAddText($row->addCell(700), 'ที่', ['bold'=>true,'size'=>20]);
    wordNativeAddText($row->addCell(4450), 'ชม ๕๑๐๐๖.๓๑/' . (string) ($data['memo_no_suffix'] ?? ''));
    wordNativeAddText($row->addCell(900), 'วันที่', ['bold'=>true,'size'=>20]);
    wordNativeAddText($row->addCell(3022), (string) ($data['doc_date'] ?? ''), [], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

    $subject = $section->addTextRun(wordNativeParagraph());
    $subject->addText('เรื่อง ', wordNativeFont(['bold'=>true,'size'=>20]));
    $subject->addText((string) ($data['subject'] ?? '') . ' ', wordNativeFont());
    $subject->addText('จำนวน ' . (string) ($data['item_count'] ?? '') . ' รายการ', wordNativeFont(['color'=>'C00000']));
    $subject->addText(' ประจำปีงบประมาณ พ.ศ. ' . (string) ($data['fiscal_year'] ?? ''), wordNativeFont());
    wordNativeAddLabelLine($section, 'เรียน', 'คณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะและราคากลาง');

    $body = $section->addTextRun(wordNativeParagraph(['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::BOTH,'indentation'=>['firstLine'=>1400]]));
    $body->addText('ตามที่ท่านได้รับแต่งตั้งให้เป็นคณะกรรมการกำหนดรายละเอียดคุณลักษณะเฉพาะขอบเขตและกำหนดราคากลาง สำหรับการ', wordNativeFont());
    $body->addText('จัดซื้อยา จำนวน ' . (string) ($data['item_count'] ?? '') . ' รายการ', wordNativeFont(['color'=>'C00000']));
    $body->addText(' เพื่อสนับสนุนการให้บริการทางการแพทย์ รักษาพยาบาลประชาชนของหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ประจำปีงบประมาณ พ.ศ. ' . (string) ($data['fiscal_year'] ?? '') . ' ตามบันทึกข้อความขออนุมัติในหลักการหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ที่ ชม ๕๑๐๐๖.๓๑/' . (string) ($data['principle_no_suffix'] ?? '') . ' ลงวันที่ ', wordNativeFont());
    $body->addText((string) ($data['principle_date'] ?? ''), wordNativeFont(['color'=>'C00000']));
    $body->addText(' เพื่อให้การดำเนินงานตามโครงการดังกล่าว เป็นไปด้วยความเรียบร้อย จึงขอเชิญท่านซึ่งเป็นคณะกรรมการร่างขอบเขตและกำหนดราคากลาง เข้าร่วมประชุม เพื่อจัดหาการ', wordNativeFont());
    $body->addText('จัดซื้อยา จำนวน ' . (string) ($data['item_count'] ?? '') . ' รายการ ในวันที่ ' . (string) ($data['meeting_date'] ?? '') . ' เวลา ' . (string) ($data['meeting_time'] ?? ''), wordNativeFont(['color'=>'C00000']));
    $body->addText(' ณ ' . (string) ($data['location'] ?? ''), wordNativeFont());
    wordNativeAddText($section, '', [], ['keepNext'=>true]);
    wordNativeAddText($section, 'จึงเรียนมาเพื่อทราบ และเข้าร่วมประชุมตาม วัน เวลา และสถานที่ดังกล่าว', [], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::BOTH,'indentation'=>['firstLine'=>1400],'keepNext'=>true]);
    wordNativeAddText($section, '', [], ['keepNext'=>true]);
    wordNativeAddText($section, (string) ($data['signer_signature'] ?? ''), ['color'=>'C00000'], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER,'keepNext'=>true]);
    wordNativeAddText($section, '(' . (string) ($data['signer_name'] ?? '') . ')', ['color'=>'C00000'], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER,'keepNext'=>true]);
    $positionLines = isset($data['signer_position_lines']) && is_array($data['signer_position_lines']) ? $data['signer_position_lines'] : (preg_split('/\R/u', (string) ($data['signer_position'] ?? '')) ?: []);
    $lastPositionIndex = count($positionLines) - 1;
    foreach ($positionLines as $index => $line) wordNativeAddText($section, (string) $line, ['color'=>'C00000'], [
        'alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER,
        'keepNext'=>$index < $lastPositionIndex,
        'keepLines'=>true,
    ]);

    $section->addPageBreak();
    wordNativeAddText($section, 'ระเบียบวาระการประชุม', ['bold'=>true], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    $agendaLines = [
        'คณะกรรมการร่างขอบเขตและกำหนดราคากลางเพื่อจัดหาการจัดซื้อยา จำนวน ' . (string) ($data['item_count'] ?? '') . ' รายการ',
        'เพื่อใช้ในการปฏิบัติงานของหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพบ้านสันโค้ง',
        'ประจำปีงบประมาณ พ.ศ. ' . (string) ($data['fiscal_year'] ?? ''),
        'วันที่ ' . (string) ($data['meeting_date'] ?? '') . ' เวลา ' . (string) ($data['meeting_time'] ?? ''),
        'ณ  ' . (string) ($data['location'] ?? ''),
    ];
    foreach ($agendaLines as $line) wordNativeAddText($section, $line, ['bold'=>true], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
    wordNativeAddText($section, '', [], ['indentation'=>['left'=>2556,'right'=>2556],'borderBottomSize'=>4,'borderBottomColor'=>'111111']);

    $agenda = $section->addTable(['width'=>9072,'unit'=>\PhpOffice\PhpWord\SimpleType\TblWidth::TWIP,'layout'=>\PhpOffice\PhpWord\Style\Table::LAYOUT_FIXED,'borderSize'=>0,'cellMargin'=>0]);
    $agendaRows = [
        ['ระเบียบวาระที่ ๑','เรื่อง  ประธานแจ้งให้ที่ประชุมทราบ','ตามบันทึกข้อความขออนุมัติในหลักการหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ที่ ชม ๕๑๐๐๖.๓๑/' . (string) ($data['principle_no_suffix'] ?? '') . ' ลงวันที่ ' . (string) ($data['principle_date'] ?? '') . ' เรื่อง ขออนุมัติในหลักการเพื่อดำเนินการจัดซื้อยา จำนวน ' . (string) ($data['item_count'] ?? '') . ' รายการ เพื่อใช้ในการปฏิบัติงานของหน่วยบริการประจำ ประจำปีงบประมาณ พ.ศ. ' . (string) ($data['fiscal_year'] ?? '')],
        ['ระเบียบวาระที่ ๒','เรื่อง  ข้อกฎหมาย ระเบียบ และหนังสือสั่งการที่เกี่ยวข้อง','-'],
        ['ระเบียบวาระที่ ๓','เรื่อง  พิจารณากำหนดร่างขอบเขตและกำหนดราคากลางเพื่อจัดหา','การจัดซื้อยา จำนวน ' . (string) ($data['item_count'] ?? '') . ' รายการ เพื่อใช้ในการปฏิบัติงานของหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ประจำปีงบประมาณ พ.ศ. ' . (string) ($data['fiscal_year'] ?? '')],
        ['ระเบียบวาระที่ ๔','เรื่อง  อื่น ๆ (ถ้ามี)',''],
    ];
    foreach ($agendaRows as [$label,$topic,$detail]) {
        $row = $agenda->addRow();
        wordNativeAddText($row->addCell(2240), $label, ['bold'=>true]);
        $cell = $row->addCell(6832);
        wordNativeAddText($cell, $topic, ['bold'=>true]);
        if ($detail !== '') wordNativeAddText($cell, $detail, [], ['alignment'=>\PhpOffice\PhpWord\SimpleType\Jc::BOTH]);
    }
    wordNativeAddText($section, '', [], ['borderBottomSize'=>4,'borderBottomColor'=>'111111']);

    $tempPath = tempnam(sys_get_temp_dir(), 'torskp_native_docx_');
    if ($tempPath === false) throw new RuntimeException('ไม่สามารถสร้างไฟล์ DOCX ชั่วคราวได้');
    try {
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);
        wordPatchDocx($tempPath, 'invitation', $destinationPath === null);
        if ($destinationPath !== null) {
            $directory = dirname($destinationPath);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์แม่แบบ DOCX ได้');
            if (!copy($tempPath, $destinationPath)) throw new RuntimeException('ไม่สามารถบันทึกแม่แบบ DOCX ได้');
            return;
        }
        $filename = wordSafeFilename($filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header("Content-Disposition: attachment; filename=\"document.docx\"; filename*=UTF-8''" . rawurlencode($filename));
        header('Content-Length: ' . (string) filesize($tempPath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        readfile($tempPath);
    } finally {
        if (is_file($tempPath)) unlink($tempPath);
    }
    exit;
}

function outputWordHtml(string $html, string $filename = 'document.docx', string $css = '', string $profile = 'default'): void
{
    if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $html, $docxBodyMatch)) {
        if ($css === '' && preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $docxStyleMatches)) {
            $css = implode("\n", $docxStyleMatches[1]);
        }
        $html = $docxBodyMatch[1];
    }
    outputWordDocx($html, $filename, $css, $profile);
}
