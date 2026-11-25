<?php
declare(strict_types=1);

function importText(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    if (!preg_match('//u', $value)) throw new RuntimeException('ไฟล์ต้องเป็น UTF-8 กรุณาบันทึกเป็น CSV UTF-8');
    return trim(str_replace(["\r\n", "\r"], "\n", $value));
}

function appTempDir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์ temporary ของระบบได้');
    }
    if (!is_writable($dir)) {
        throw new RuntimeException('โฟลเดอร์ temporary ของระบบไม่สามารถเขียนไฟล์ได้');
    }

    return $dir;
}

function appTempFile(string $prefix): string
{
    $path = tempnam(appTempDir(), $prefix);
    if ($path === false) {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ temporary ได้');
    }

    return $path;
}

function validateUploadedSpreadsheet(array $file): void
{
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        throw new RuntimeException('ไฟล์ต้องมีขนาดไม่เกิน 10 MB');
    }
    $name = (string) ($file['name'] ?? '');
    $path = (string) ($file['tmp_name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx'], true) || !is_uploaded_file($path)) {
        throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx หรือ .csv ที่อัปโหลดโดยตรง');
    }
    if ($ext !== 'xlsx') return;
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Server ยังไม่เปิด PHP ZipArchive จึงอ่านไฟล์ .xlsx ไม่ได้');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('ไฟล์ Excel ไม่ถูกต้อง');
    $totalUncompressed = 0;
    if ($zip->numFiles > 2000) {
        $zip->close();
        throw new RuntimeException('ไฟล์ Excel มีรายการภายในมากเกินไป');
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $totalUncompressed += (int) ($stat['size'] ?? 0);
        if ($totalUncompressed > 50 * 1024 * 1024) {
            $zip->close();
            throw new RuntimeException('ไฟล์ Excel มีขนาดแตกไฟล์มากเกินไป');
        }
    }
    $validWorkbook = $zip->locateName('[Content_Types].xml') !== false
        && $zip->locateName('xl/worksheets/sheet1.xml') !== false;
    $zip->close();
    if (!$validWorkbook) throw new RuntimeException('โครงสร้างไฟล์ Excel ไม่ถูกต้อง');
}

function spreadsheetColumnIndex(string $cell): int
{
    preg_match('/^[A-Z]{1,4}/', strtoupper($cell), $matches);
    $letters = $matches[0] ?? 'A';
    $index = 0;
    $length = strlen($letters);
    for ($i = 0; $i < $length; $i++) {
        $letter = $letters[$i];
        $index = ($index * 26) + (ord($letter) - 64);
    }
    return $index - 1;
}

function zipEntryTempFile(ZipArchive $zip, string $entryName): ?string
{
    $stream = $zip->getStream($entryName);
    if (!$stream) {
        return null;
    }

    $tempPath = appTempFile('tor_xlsx_');
    $temp = fopen($tempPath, 'wb');
    if (!$temp) {
        fclose($stream);
        @unlink($tempPath);
        return null;
    }

    try {
        if (stream_copy_to_stream($stream, $temp) === false) throw new RuntimeException('อ่านข้อมูล Excel ไม่สำเร็จ');
    } catch (Throwable $e) { @unlink($tempPath); throw $e; }
    finally { fclose($stream); fclose($temp); }

    return $tempPath;
}

function importXmlReader(string $path): XMLReader
{
    $reader = new XMLReader();
    if (!$reader->open($path, null, LIBXML_NONET)) throw new RuntimeException('อ่าน XML ใน Excel ไม่สำเร็จ');
    return $reader;
}

// Read the streaming XML directly. Attribute lookup must not depend on
// SimpleXML namespace views (Excel may use default or explicit prefixes).
function importSpreadsheetNode(XMLReader $reader, string $name): bool
{
    return $reader->localName === $name && in_array($reader->namespaceURI, [
        'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
        'http://purl.oclc.org/ooxml/spreadsheetml/main',
    ], true);
}

function importReadTextElement(XMLReader $reader): string
{
    if ($reader->isEmptyElement) return '';
    $depth = $reader->depth; $text = '';
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth) return $text;
        if (in_array($reader->nodeType, [XMLReader::TEXT, XMLReader::CDATA, XMLReader::WHITESPACE, XMLReader::SIGNIFICANT_WHITESPACE], true)) {
            $text .= $reader->value;
        } elseif ($reader->nodeType === XMLReader::ELEMENT || $reader->nodeType === XMLReader::ENTITY_REF || $reader->nodeType === XMLReader::DOC_TYPE) {
            throw new RuntimeException('โครงสร้างข้อความ XML ใน Excel ไม่ถูกต้อง');
        }
    }
    throw new RuntimeException('XML ข้อความใน Excel ไม่สมบูรณ์');
}

function importReadRichText(XMLReader $reader): string
{
    if ($reader->isEmptyElement) return '';
    $depth = $reader->depth; $text = ''; $runDepth = null;
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::END_ELEMENT) {
            if ($reader->depth === $depth) return $text;
            if ($reader->depth === $runDepth) $runDepth = null;
            continue;
        }
        if ($reader->nodeType !== XMLReader::ELEMENT) continue;
        if ($reader->depth === $depth + 1 && importSpreadsheetNode($reader, 'r')) {
            $runDepth = $reader->isEmptyElement ? null : $reader->depth;
        } elseif (importSpreadsheetNode($reader, 't') && ($reader->depth === $depth + 1 || ($runDepth !== null && $reader->depth === $runDepth + 1))) {
            $text .= importReadTextElement($reader);
        }
    }
    throw new RuntimeException('XML ข้อความใน Excel ไม่สมบูรณ์');
}

function readXlsxSharedStrings(string $path): array
{
    $strings = []; $reader = importXmlReader($path);
    try {
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::DOC_TYPE) throw new RuntimeException('Excel มี DTD ที่ไม่รองรับ');
            if ($reader->nodeType === XMLReader::ELEMENT && importSpreadsheetNode($reader, 'si')) {
                $strings[] = importReadRichText($reader);
                if (count($strings) > 250000) throw new RuntimeException('Excel มีข้อความจำนวนมากเกินขีดจำกัด');
            }
        }
        if (libxml_get_last_error()) throw new RuntimeException('XML ข้อความใน Excel ไม่สมบูรณ์');
    } finally { $reader->close(); }
    return $strings;
}

function importReadXlsxCell(XMLReader $reader, array $strings): array
{
    $type = $reader->getAttribute('t') ?? ''; $depth = $reader->depth;
    $value = ''; $inline = ''; $issues = [];
    if ($type === 'e') $issues[] = 'พบสูตรหรือเซลล์ error กรุณาวางเป็นค่า (Paste Values)';
    if (!$reader->isEmptyElement) {
        $closed = false;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth) { $closed = true; break; }
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->depth !== $depth + 1) continue;
            if (importSpreadsheetNode($reader, 'f')) {
                $issues[] = 'พบสูตรหรือเซลล์ error กรุณาวางเป็นค่า (Paste Values)';
            } elseif (importSpreadsheetNode($reader, 'v')) {
                $value = importReadTextElement($reader);
            } elseif (importSpreadsheetNode($reader, 'is')) {
                $inline = importReadRichText($reader);
            }
        }
        if (!$closed) throw new RuntimeException('XML เซลล์ Excel ไม่สมบูรณ์');
    }
    if ($type === 's') {
        if (!ctype_digit($value) || !array_key_exists((int)$value, $strings)) throw new RuntimeException('ดัชนีข้อความ Excel ไม่ถูกต้อง');
        $value = $strings[(int)$value];
    } elseif ($type === 'inlineStr') { $value = $inline; }
    return [importText($value), array_values(array_unique($issues))];
}

// Physical Excel row number is preserved; missing cells do not shift columns.
function readXlsxRows(string $path): Generator
{
    if (!class_exists(ZipArchive::class) || !class_exists(XMLReader::class)) {
        throw new RuntimeException('Server ต้องเปิด ZipArchive และ XMLReader หรือใช้ CSV UTF-8');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('เปิดไฟล์ Excel ไม่ได้');
    $sharedPath = $sheetPath = null; $reader = null;
    $previous = libxml_use_internal_errors(true); libxml_clear_errors();
    try {
        $sharedPath = zipEntryTempFile($zip, 'xl/sharedStrings.xml');
        $strings = $sharedPath === null ? [] : readXlsxSharedStrings($sharedPath);
        $sheetPath = zipEntryTempFile($zip, 'xl/worksheets/sheet1.xml');
        if ($sheetPath === null) throw new RuntimeException('ไม่พบ sheet1.xml กรุณาใช้ไฟล์ตัวอย่างหรือ CSV UTF-8');
        $reader = importXmlReader($sheetPath); $last = 0;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::DOC_TYPE) throw new RuntimeException('Excel มี DTD ที่ไม่รองรับ');
            if ($reader->nodeType !== XMLReader::ELEMENT || !importSpreadsheetNode($reader, 'row')) continue;
            $number = (int) ($reader->getAttribute('r') ?? ($last + 1));
            if ($number <= $last || $number > 1048576) throw new RuntimeException('ลำดับแถว Excel ไม่ถูกต้อง');
            $last = $number; $values = array_fill(0, 4, ''); $issues = []; $depth = $reader->depth;
            if (!$reader->isEmptyElement) {
                $closed = false; $nextIndex = 0; $seen = [];
                while ($reader->read()) {
                    if ($reader->nodeType === XMLReader::END_ELEMENT && $reader->depth === $depth) { $closed = true; break; }
                    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->depth !== $depth + 1 || !importSpreadsheetNode($reader, 'c')) continue;
                    $ref = $reader->getAttribute('r');
                    if ($ref !== null && !preg_match('/^[A-Z]{1,3}[1-9][0-9]*$/iD', $ref)) throw new RuntimeException('ตำแหน่งเซลล์ Excel ไม่ถูกต้อง ที่แถว '.$number);
                    $index = $ref === null ? $nextIndex : spreadsheetColumnIndex($ref);
                    $nextIndex = $index + 1;
                    if ($index < 0 || $index >= 16384 || isset($seen[$index])) throw new RuntimeException('ตำแหน่งเซลล์ Excel ซ้ำหรือไม่ถูกต้อง ที่แถว '.$number);
                    $seen[$index] = true;
                    [$value, $cellIssues] = importReadXlsxCell($reader, $strings);
                    $issues = array_merge($issues, $cellIssues);
                    if ($index < 4) $values[$index] = $value;
                    elseif ($value !== '') $issues[] = 'พบข้อมูลนอกคอลัมน์ที่รองรับ';
                }
                if (!$closed) throw new RuntimeException('XML แถว Excel ไม่สมบูรณ์');
            }
            yield $number => ['cells'=>$values, 'issues'=>array_values(array_unique($issues))];
        }
        if (libxml_get_last_error()) throw new RuntimeException('XML ของ worksheet ไม่สมบูรณ์');
    } finally {
        if ($reader) $reader->close();
        $zip->close();
        if ($sharedPath !== null) @unlink($sharedPath);
        if ($sheetPath !== null) @unlink($sheetPath);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
    }
}

// RFC 4180 record parser: rejects malformed quotes, handles embedded LF/CRLF and escaped quotes.
// Row number is the logical CSV record, matching spreadsheet row when opened in Excel.
function readCsvRows(string $path): Generator
{
    $handle = fopen($path, 'rb');
    if (!$handle) throw new RuntimeException('เปิด CSV ไม่ได้');
    try {
        if (fread($handle, 3) !== "\xEF\xBB\xBF") rewind($handle);
        $n=0; $row=[]; $field=''; $state='start'; $hasData=false;
        while (($line=fgets($handle))!==false) {
            $len=strlen($line);
            for ($i=0;$i<$len;$i++) {
                $c=$line[$i];
                if ($state==='quoted') {
                    if ($c==='"') $state='after_quote'; else $field.=$c;
                    continue;
                }
                if ($state==='after_quote' && $c==='"') { $field.='"';$state='quoted';continue; }
                if ($c===',' || $c==="\n" || $c==="\r") {
                    $row[]=importText($field);$field='';$state='start';$hasData=true;
                    if (count($row)>64) throw new RuntimeException('CSV มีคอลัมน์มากเกินกำหนด ที่แถว '.($n+1));
                    if ($c!==',') {
                        if ($c==="\r" && ($line[$i+1]??'')==="\n") $i++;
                        yield ++$n=>['cells'=>$row,'issues'=>[]];$row=[];$hasData=false;
                    }
                    continue;
                }
                if ($state==='after_quote') throw new RuntimeException('CSV มีข้อความหลังเครื่องหมายคำพูดที่แถว '.($n+1));
                if ($c==='"') {
                    if ($state!=='start') throw new RuntimeException('CSV มีเครื่องหมายคำพูดผิดรูปแบบที่แถว '.($n+1));
                    $state='quoted';$hasData=true;continue;
                }
                $state='plain';$field.=$c;$hasData=true;
            }
        }
        if (!feof($handle)) throw new RuntimeException('อ่าน CSV ไม่สำเร็จ');
        if ($state==='quoted') throw new RuntimeException('CSV ปิดเครื่องหมายคำพูดไม่ครบที่แถว '.($n+1));
        if ($hasData || $row) { $row[]=importText($field);yield ++$n=>['cells'=>$row,'issues'=>[]]; }
    } finally { fclose($handle); }
}

function fileRows(string $path, string $name): Generator
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'csv') { yield from readCsvRows($path); return; }
    if ($ext === 'xlsx') { yield from readXlsxRows($path); return; }
    throw new RuntimeException('รองรับเฉพาะ .xlsx และ .csv UTF-8');
}

function xmlText(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
}

function spreadsheetColumnName(int $index): string
{
    $name = '';
    $index++;
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $index = intdiv($index - $remainder - 1, 26);
    }
    return $name;
}

function worksheetXml(array $rows): string
{
    $xmlRows = [];
    foreach ($rows as $rowIndex => $row) {
        $cells = [];
        foreach ($row as $columnIndex => $value) {
            $cell = spreadsheetColumnName((int) $columnIndex) . ($rowIndex + 1);
            $cells[] = '<c r="' . $cell . '" t="inlineStr"><is><t xml:space="preserve">' . xmlText((string) $value) . '</t></is></c>';
        }
        $xmlRows[] = '<row r="' . ($rowIndex + 1) . '">' . implode('', $cells) . '</row>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
        . '</worksheet>';
}

function sendSampleCsv(string $filename, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'wb');
    if (!$output) {
        throw new RuntimeException('ไม่สามารถสร้างไฟล์ตัวอย่าง CSV ได้');
    }

    fwrite($output, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($output, $row, ',', '"', '');
    }
    fclose($output);
    exit;
}

function sendSampleXlsx(string $filename, array $rows): void
{
    if (!class_exists(ZipArchive::class)) {
        sendSampleCsv(preg_replace('/\.xlsx$/i', '.csv', $filename), $rows);
    }

    try {
        $path = appTempFile('tor_sample_');
    } catch (Throwable $e) {
        sendSampleCsv(preg_replace('/\.xlsx$/i', '.csv', $filename), $rows);
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
        @unlink($path);
        sendSampleCsv(preg_replace('/\.xlsx$/i', '.csv', $filename), $rows);
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', worksheetXml($rows));
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    @unlink($path);
    exit;
}

