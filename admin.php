<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';
require __DIR__ . '/core/document_signer_defaults.php';
requireAdmin();

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function post(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }

function ensurePriceReferenceTable(): void
{
    requireDatabaseTables(['medicine_price_references', 'medicines']);
}

function moneyValue(string $value): float
{
    return (float) str_replace([',', ' ', "\xc2\xa0"], '', trim($value));
}

function importText(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $value = str_replace(["\xc2\xa0", "\r"], [' ', ''], $value);
    if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8, TIS-620, CP874, Windows-874, ISO-8859-11, ISO-8859-1');
    }
    return trim($value);
}

function appTempDir(): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
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

    stream_copy_to_stream($stream, $temp);
    fclose($stream);
    fclose($temp);

    return $tempPath;
}

function readXlsxSharedStrings(string $path): array
{
    $strings = [];
    if (!class_exists(XMLReader::class)) {
        throw new RuntimeException('Server ยังไม่เปิด PHP XMLReader จึงอ่านไฟล์ .xlsx ขนาดใหญ่ไม่ได้');
    }

    $reader = new XMLReader();
    if (!$reader->open($path)) {
        return $strings;
    }

    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
            continue;
        }
        $xml = $reader->readOuterXML();
        $si = simplexml_load_string($xml);
        $parts = [];
        if (isset($si->t)) {
            $parts[] = (string) $si->t;
        }
        foreach ($si->r ?? [] as $run) {
            $parts[] = (string) $run->t;
        }
        $strings[] = implode('', $parts);
    }
    $reader->close();

    return $strings;
}

function readXlsxRows(string $path): Generator
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('Server ยังไม่เปิด PHP ZipArchive จึงอ่านไฟล์ .xlsx ไม่ได้');
    }
    if (!class_exists(XMLReader::class)) {
        throw new RuntimeException('Server ยังไม่เปิด PHP XMLReader จึงอ่านไฟล์ .xlsx ไม่ได้');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('เปิดไฟล์ Excel ไม่ได้');
    }

    $sharedStrings = [];
    $sharedPath = zipEntryTempFile($zip, 'xl/sharedStrings.xml');
    if ($sharedPath !== null) {
        $sharedStrings = readXlsxSharedStrings($sharedPath);
        @unlink($sharedPath);
    }

    $sheetPath = zipEntryTempFile($zip, 'xl/worksheets/sheet1.xml');
    if ($sheetPath === null) {
        $zip->close();
        throw new RuntimeException('ไม่พบ worksheet แรกในไฟล์ Excel');
    }

    $reader = new XMLReader();
    $reader->open($sheetPath);
    while ($reader->read()) {
        if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
            continue;
        }
        $row = simplexml_load_string($reader->readOuterXML());
        $values = [];
        foreach ($row->c ?? [] as $cell) {
            $index = spreadsheetColumnIndex((string) $cell['r']);
            $type = (string) $cell['t'];
            $value = '';
            if ($type === 's') {
                $value = $sharedStrings[(int) $cell->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) ($cell->is->t ?? '');
            } else {
                $value = (string) ($cell->v ?? '');
            }
            $values[$index] = importText($value);
        }
        if ($values) {
            ksort($values);
            yield array_values($values + array_fill(0, 4, ''));
        }
    }
    $reader->close();
    $zip->close();
    @unlink($sheetPath);
}

function readCsvRows(string $path): Generator
{
    $handle = fopen($path, 'rb');
    if (!$handle) return;
    while (($row = fgetcsv($handle)) !== false) {
        yield array_map(static fn ($value): string => importText((string) $value), $row);
    }
    fclose($handle);
}

function isPriceReferenceHeader(array $row): bool
{
    return strpos((string) ($row[0] ?? ''), 'รายการยา') !== false;
}

function isMedicineImportHeader(array $row): bool
{
    return str_contains(importText((string) ($row[0] ?? '')), 'ชื่อยา');
}

function isPriceReferenceImportHeader(array $row): bool
{
    return str_contains(importText((string) ($row[1] ?? '')), 'รูปแบบยาและความแรง');
}

function isImportablePriceReferenceRow(array $row): bool
{
    if (isPriceReferenceHeader($row)) {
        return false;
    }

    $name = trim((string) ($row[0] ?? ''));
    $strength = trim((string) ($row[1] ?? ''));
    $unit = trim((string) ($row[2] ?? ''));

    return $name !== '' && $strength !== '' && $unit !== '';
}

function countImportableRows(iterable $rows): int
{
    $count = 0;
    foreach ($rows as $row) {
        if (isImportablePriceReferenceRow((array) $row)) {
            $count++;
        }
    }
    return $count;
}

function countCsvRows(string $path): int
{
    return countImportableRows(readCsvRows($path));
}

function countXlsxRows(string $path): int
{
    return countImportableRows(readXlsxRows($path));
}

function fileRows(string $path, string $name): Generator
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'csv') {
        yield from readCsvRows($path);
        return;
    }
    if ($ext === 'xlsx') {
        yield from readXlsxRows($path);
        return;
    }
    throw new RuntimeException('รองรับเฉพาะไฟล์ .xlsx หรือ .csv แบบ UTF-8');
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
            $cells[] = '<c r="' . $cell . '" t="inlineStr"><is><t>' . xmlText((string) $value) . '</t></is></c>';
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
        fputcsv($output, $row);
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

function isMedicineHeader(array $row): bool
{
    $first = importText((string) ($row[0] ?? ''));
    $second = importText((string) ($row[1] ?? ''));
    return $first !== '' && (
        str_contains($first, 'ชื่อยา')
        || (str_contains($first, 'รายการยา') && (str_contains($second, 'หน่วย') || str_contains($second, 'Unit')))
    );
}

function isImportableMedicineRow(array $row): bool
{
    if (isMedicineHeader($row)) {
        return false;
    }

    $name = importText((string) ($row[0] ?? ''));
    $unit = importText((string) ($row[1] ?? ''));

    return $name !== '' && $unit !== '';
}

function importMedicines(iterable $rows): int
{
    $findStmt = db()->prepare('SELECT id FROM medicines WHERE name = ? AND unit = ? LIMIT 1');
    $updateStmt = db()->prepare('UPDATE medicines SET specification = ? WHERE id = ?');
    $insertStmt = db()->prepare('INSERT INTO medicines (name, unit, specification) VALUES (?, ?, ?)');

    $count = 0;
    foreach ($rows as $row) {
        $row = (array) $row;
        if ($count === 0 && isPriceReferenceImportHeader($row)) {
            throw new RuntimeException('ไฟล์นี้เป็นรูปแบบราคากลางยา กรุณานำเข้าในหัวข้อราคากลางยา');
        }
        if (!isImportableMedicineRow($row)) {
            continue;
        }

        $name = importText((string) ($row[0] ?? ''));
        $unit = importText((string) ($row[1] ?? ''));
        $specification = importText((string) ($row[2] ?? ''));
        if ($specification === '') {
            $specification = $name;
        }

        $findStmt->execute([$name, $unit]);
        $id = (int) ($findStmt->fetchColumn() ?: 0);
        if ($id > 0) {
            $updateStmt->execute([$specification, $id]);
        } else {
            $insertStmt->execute([$name, $unit, $specification]);
        }
        $count++;
    }

    return $count;
}

function importProgressPath(string $id): string
{
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?: 'default';
    return appTempDir() . DIRECTORY_SEPARATOR . 'tor_import_' . $id . '.json';
}

function writeImportProgress(string $id, array $data): void
{
    file_put_contents(importProgressPath($id), json_encode($data, JSON_UNESCAPED_UNICODE));
}

function importPriceReferences(iterable $rows, string $sourceFile, int $total = 0, ?callable $progress = null): int
{
    $stmt = db()->prepare("
        INSERT INTO medicine_price_references
            (medicine_name, dosage_form_strength, unit, reference_price, source_file)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            reference_price = VALUES(reference_price),
            source_file = VALUES(source_file)
    ");

    $count = 0;
    $processed = 0;
    foreach ($rows as $i => $row) {
        if ($count === 0 && isMedicineImportHeader((array) $row)) {
            throw new RuntimeException('ไฟล์นี้เป็นรูปแบบรายการยา กรุณานำเข้าในหัวข้อรายการยา');
        }
        if (!isImportablePriceReferenceRow((array) $row)) {
            continue;
        }
        $processed++;
        $name = trim((string) ($row[0] ?? ''));
        $strength = trim((string) ($row[1] ?? ''));
        $unit = trim((string) ($row[2] ?? ''));
        $price = moneyValue((string) ($row[3] ?? '0'));
        if ($name === '' || $strength === '' || $unit === '') {
            if ($progress && ($processed % 100 === 0 || $processed === $total)) {
                $progress($processed, $total, $count, 'running');
            }
            continue;
        }
        $stmt->execute([$name, $strength, $unit, $price, $sourceFile]);
        $count++;
        if ($progress && ($processed % 100 === 0 || $processed === $total)) {
            $progress($processed, $total, $count, 'running');
        }
    }

    return $count;
}

ensurePriceReferenceTable();

$message = '';
$error = '';
$submittedAction = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? post('action') : '';

if (($_GET['action'] ?? '') === 'import_progress') {
    header('Content-Type: application/json; charset=utf-8');
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['id'] ?? ''));
    $path = importProgressPath($id);
    if ($id !== '' && is_file($path)) {
        echo file_get_contents($path);
    } else {
        echo json_encode(['status' => 'waiting', 'processed' => 0, 'total' => 0, 'imported' => 0], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Read-only, admin-authenticated JSON search. No page reload is needed.
// Queries are bounded and parameterized; existing write actions remain unchanged.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && in_array((string) ($_GET['action'] ?? ''), ['live_medicines', 'live_prices'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    try {
        $kind = (string) $_GET['action'];
        $query = trim((string) ($_GET['q'] ?? ''));
        // No unlimited database scan from arbitrary large user input.
        if (mb_strlen($query, 'UTF-8') > 120) $query = mb_substr($query, 0, 120, 'UTF-8');
        $page = max(1, min(200, (int) ($_GET['page'] ?? 1)));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        if ($kind === 'live_medicines') {
            $table = 'medicines';
            $where = $query === '' ? '' : ' WHERE name LIKE ? OR unit LIKE ? OR specification LIKE ?';
            $params = $query === '' ? [] : array_fill(0, 3, '%' . $query . '%');
            $order = ' ORDER BY name, id';
            $cols = 'id, name, unit, specification';
        } else {
            $table = 'medicine_price_references';
            $where = $query === '' ? '' : ' WHERE medicine_name LIKE ? OR dosage_form_strength LIKE ? OR unit LIKE ? OR reference_price LIKE ?';
            $params = $query === '' ? [] : array_fill(0, 4, '%' . $query . '%');
            $order = ' ORDER BY medicine_name, dosage_form_strength, unit, id';
            $cols = 'id, medicine_name, dosage_form_strength, unit, reference_price';
        }
        $countStmt = db()->prepare('SELECT COUNT(*) FROM ' . $table . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $stmt = db()->prepare('SELECT ' . $cols . ' FROM ' . $table . $where . $order . ' LIMIT 50 OFFSET ' . $offset);
        $stmt->execute($params);
        echo json_encode([
            'ok' => true, 'page' => $page, 'limit' => $limit,
            'total' => $total, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        error_log('Admin live search failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'ไม่สามารถค้นหาได้ในขณะนี้'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_GET['action'] ?? '') === 'sample_medicines') {
    sendSampleXlsx('medicine_import_sample.xlsx', [
        ['ชื่อยา', 'หน่วย', 'คุณสมบัติยา'],
        ['Acyclovir 400 mg', 'tablet', 'เป็นยาเม็ดหรือแคปซูล ชนิดรับประทาน'],
        ['Amoxicillin 500 mg', 'capsule', 'เป็นยาแคปซูล ชนิดรับประทาน'],
    ]);
}

if (($_GET['action'] ?? '') === 'sample_price_refs') {
    sendSampleXlsx('medicine_price_reference_sample.xlsx', [
        ['รายการยา', 'รูปแบบยาและความแรง', 'หน่วย', 'ราคากลางยาต่อหน่วย (บาท)'],
        ['Acyclovir', 'tablet 400 mg', 'tablet', '2.50'],
        ['Amoxicillin', 'capsule 500 mg', 'capsule', '1.25'],
    ]);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = $submittedAction;

        if ($action === 'save_document_signer_defaults') {
            saveDocumentSignerDefaults('principle_approval', [
                'proposer_name' => post('proposer_name'),
                'proposer_position' => post('proposer_position'),
                'approver_name' => post('approver_name'),
                'approver_position' => post('approver_position'),
            ]);
            $message = 'บันทึกค่าเริ่มต้นผู้เสนอและผู้อนุมัติแล้ว';
        }

        if ($action === 'save_medicine') {
            $id = (int) post('id');
            $name = post('name');
            $unit = post('unit');
            $specification = post('specification');
            if ($name === '' || $unit === '' || $specification === '') {
                throw new RuntimeException('กรุณากรอกข้อมูลยาให้ครบ');
            }
            if ($id > 0) {
                $stmt = db()->prepare('UPDATE medicines SET name = ?, unit = ?, specification = ? WHERE id = ?');
                $stmt->execute([$name, $unit, $specification, $id]);
                $message = 'อัปเดตรายการยาแล้ว';
            } else {
                $stmt = db()->prepare('INSERT INTO medicines (name, unit, specification) VALUES (?, ?, ?)');
                $stmt->execute([$name, $unit, $specification]);
                $message = 'เพิ่มรายการยาแล้ว';
            }
        }

        if ($action === 'delete_medicine') {
            $stmt = db()->prepare('DELETE FROM medicines WHERE id = ?');
            $stmt->execute([(int) post('id')]);
            $message = 'ลบรายการยาแล้ว';
        }

        if ($action === 'import_medicines') {
            if (!isset($_FILES['medicine_file']) || $_FILES['medicine_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('กรุณาเลือกไฟล์ Excel หรือ CSV');
            }
            validateUploadedSpreadsheet($_FILES['medicine_file']);
            $name = (string) $_FILES['medicine_file']['name'];
            $count = importMedicines(fileRows($_FILES['medicine_file']['tmp_name'], $name));
            $message = "นำเข้ารายการยา {$count} รายการแล้ว";
        }

        if ($action === 'save_price_ref') {
            $id = (int) post('id');
            $name = post('medicine_name');
            $strength = post('dosage_form_strength');
            $unit = post('price_unit');
            $price = moneyValue(post('reference_price'));
            if ($name === '' || $strength === '' || $unit === '') {
                throw new RuntimeException('กรุณากรอกข้อมูลราคากลางยาให้ครบ');
            }
            if ($id > 0) {
                $stmt = db()->prepare('UPDATE medicine_price_references SET medicine_name = ?, dosage_form_strength = ?, unit = ?, reference_price = ? WHERE id = ?');
                $stmt->execute([$name, $strength, $unit, $price, $id]);
                $message = 'อัปเดตราคากลางยาแล้ว';
            } else {
                $stmt = db()->prepare('INSERT INTO medicine_price_references (medicine_name, dosage_form_strength, unit, reference_price) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $strength, $unit, $price]);
                $message = 'เพิ่มราคากลางยาแล้ว';
            }
        }

        if ($action === 'delete_price_ref') {
            $stmt = db()->prepare('DELETE FROM medicine_price_references WHERE id = ?');
            $stmt->execute([(int) post('id')]);
            $message = 'ลบราคากลางยาแล้ว';
        }

        if ($action === 'import_price_refs') {
            if (!isset($_FILES['price_file']) || $_FILES['price_file']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('กรุณาเลือกไฟล์ Excel หรือ CSV');
            }
            validateUploadedSpreadsheet($_FILES['price_file']);
            $importId = preg_replace('/[^a-zA-Z0-9_-]/', '', post('import_id')) ?: bin2hex(random_bytes(8));
            writeImportProgress($importId, [
                'status' => 'starting',
                'processed' => 0,
                'total' => 0,
                'imported' => 0,
                'message' => 'กำลังเตรียมไฟล์',
            ]);
            if (post('clear_existing') === '1') {
                db()->exec('TRUNCATE TABLE medicine_price_references');
            }
            $name = $_FILES['price_file']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $total = $ext === 'csv'
                ? countCsvRows($_FILES['price_file']['tmp_name'])
                : countXlsxRows($_FILES['price_file']['tmp_name']);
            writeImportProgress($importId, [
                'status' => 'running',
                'processed' => 0,
                'total' => $total,
                'imported' => 0,
                'message' => 'เริ่มนำเข้า',
            ]);
            $rows = $ext === 'csv'
                ? readCsvRows($_FILES['price_file']['tmp_name'])
                : readXlsxRows($_FILES['price_file']['tmp_name']);
            $count = importPriceReferences($rows, $name, $total, function (int $processed, int $total, int $imported, string $status) use ($importId): void {
                writeImportProgress($importId, [
                    'status' => $status,
                    'processed' => $processed,
                    'total' => $total,
                    'imported' => $imported,
                    'message' => 'กำลังนำเข้า',
                ]);
            });
            writeImportProgress($importId, [
                'status' => 'done',
                'processed' => $total,
                'total' => $total,
                'imported' => $count,
                'message' => 'นำเข้าเสร็จแล้ว',
            ]);
            $message = "นำเข้าราคากลางยา {$count} รายการแล้ว";
        }
    }
} catch (Throwable $e) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && post('action') === 'import_price_refs') {
        $importId = preg_replace('/[^a-zA-Z0-9_-]/', '', post('import_id'));
        if ($importId !== '') {
            writeImportProgress($importId, [
                'status' => 'error',
                'processed' => 0,
                'total' => 0,
                'imported' => 0,
                'message' => $e->getMessage(),
            ]);
        }
    }
    $error = $e->getMessage();
}

$editMedicine = null;
if (isset($_GET['edit_medicine'])) {
    $stmt = db()->prepare('SELECT * FROM medicines WHERE id = ?');
    $stmt->execute([(int) $_GET['edit_medicine']]);
    $editMedicine = $stmt->fetch() ?: null;
}

$editPrice = null;
if (isset($_GET['edit_price'])) {
    $stmt = db()->prepare('SELECT * FROM medicine_price_references WHERE id = ?');
    $stmt->execute([(int) $_GET['edit_price']]);
    $editPrice = $stmt->fetch() ?: null;
}

$medicineSearch = trim((string) ($_GET['medicine_search'] ?? ''));
$priceSearch = trim((string) ($_GET['price_search'] ?? ''));

$medicineSql = 'SELECT * FROM medicines';
$medicineParams = [];
if ($medicineSearch !== '') {
    $medicineSql .= ' WHERE name LIKE ? OR unit LIKE ? OR specification LIKE ?';
    $term = '%' . $medicineSearch . '%';
    $medicineParams = [$term, $term, $term];
}
$medicineSql .= ' ORDER BY name LIMIT 50';
$stmt = db()->prepare($medicineSql);
$stmt->execute($medicineParams);
$medicines = $stmt->fetchAll();

$priceSql = 'SELECT * FROM medicine_price_references';
$priceParams = [];
if ($priceSearch !== '') {
    $priceSql .= ' WHERE medicine_name LIKE ? OR dosage_form_strength LIKE ? OR unit LIKE ? OR reference_price LIKE ?';
    $term = '%' . $priceSearch . '%';
    $priceParams = [$term, $term, $term, $term];
}
$priceSql .= ' ORDER BY medicine_name, dosage_form_strength, unit LIMIT 50';
$stmt = db()->prepare($priceSql);
$stmt->execute($priceParams);
$priceRefs = $stmt->fetchAll();
$medicineTotal = (int) db()->query('SELECT COUNT(*) FROM medicines')->fetchColumn();
$priceReferenceTotal = (int) db()->query('SELECT COUNT(*) FROM medicine_price_references')->fetchColumn();
$principleSignerDefaults = documentSignerDefaults('principle_approval');

$openSigners = $submittedAction === 'save_document_signer_defaults';
$openMedicines = $editMedicine !== null || $medicineSearch !== '' || in_array($submittedAction, ['save_medicine', 'delete_medicine', 'import_medicines'], true);
$openPrices = $editPrice !== null || $priceSearch !== '' || in_array($submittedAction, ['save_price_ref', 'delete_price_ref', 'import_price_refs'], true);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>จัดการข้อมูลยาและเอกสาร | TOR SKP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/backoffice-blue.css?v=20261124">
    <link rel="stylesheet" href="assets/admin-compact.css?v=20261124b">
    <style>
        .admin-panel > summary::-webkit-details-marker { display: none; }
    </style>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 backoffice-page admin-backoffice">
<?php require __DIR__ . '/components/navbar.php'; ?>
<main class="mx-auto max-w-7xl px-4 py-6 backoffice-main compact-admin-main">
    <header class="bo-hero compact-hero">
      <div class="bo-hero-content">
        <p class="bo-eyebrow"><span class="bo-dot"></span> TOR SKP · ADMIN WORKSPACE</p>
        <h1>จัดการข้อมูลหลังบ้าน</h1>
        <p>ค้นหาและแก้ไขข้อมูลยา ราคากลาง และค่าเริ่มต้นเอกสาร ในหน้าจอเดียว</p>
      </div>
      <div class="compact-hero-right">
        <span class="compact-count"><?= number_format($medicineTotal) ?> <small>รายการยา</small></span>
        <span class="compact-count"><?= number_format($priceReferenceTotal) ?> <small>ราคาอ้างอิง</small></span>
        <a href="tor_company_purchase.php" class="bo-hero-secondary">กลับไปสร้างเอกสาร</a>
      </div>
    </header>
    <nav class="bo-section-links compact-section-nav" aria-label="หมวดจัดการข้อมูล">
      <a href="#medicine-catalog">รายการยา</a><a href="#price-references">ราคากลางยา</a><a href="#document-signers">ผู้ลงนามเอกสาร</a>
      <a href="change_password.php" class="compact-account-link">บัญชีผู้ใช้</a>
    </nav>

    <?php if ($message): ?>
        <div class="mb-4 rounded-md border border-emerald-500/40 bg-emerald-950/60 p-3 text-sm text-emerald-100"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="mb-4 rounded-md border border-red-500/40 bg-red-950/60 p-3 text-sm text-red-100"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="grid gap-6">
        <section id="medicine-catalog" class="rounded-lg border border-zinc-800 bg-zinc-900/80 p-5">
            <details class="admin-panel" data-panel="medicines" data-active="1" open>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <span>
                    <span class="block text-lg font-semibold">รายการยา</span>
                    <span class="mt-1 block text-sm text-zinc-400"><?= number_format($medicineTotal) ?> รายการ · นำเข้าจาก Excel/CSV หรือเพิ่มเองทีละรายการ</span>
                </span>
                <span class="panel-indicator rounded-md border border-zinc-700 px-3 py-1 text-sm text-zinc-300">แสดง</span>
            </summary>

            <form method="get" id="medicines-search-form" class="compact-find" data-live-kind="medicines">
                <label class="compact-find-label" for="medicines-search-input">ค้นหาชื่อยา หน่วย หรือคุณสมบัติ</label>
                <div class="compact-find-row">
                  <input id="medicines-search-input" name="medicine_search" value="<?= h($medicineSearch) ?>" data-live-input="medicines" type="search" autocomplete="off" placeholder="พิมพ์เพื่อค้นหาทันที…" aria-controls="medicines-rows" />
                  <button type="submit" class="compact-search-submit">ค้นหา</button>
                  <button type="button" class="compact-clear" data-live-clear="medicines">ล้าง</button>
                </div>
                <p id="medicines-search-status" role="status" aria-live="polite" class="compact-status">กำลังแสดงรายการล่าสุดสูงสุด 50 รายการ</p>
            </form>
            <div class="compact-table-viewport">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="sticky top-0 bg-zinc-950 text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 text-left">ชื่อยา</th>
                        <th class="w-28 px-3 py-2 text-left">หน่วย</th>
                        <th class="px-3 py-2 text-left">คุณสมบัติ</th>
                        <th class="w-36 px-3 py-2 text-right">จัดการ</th>
                    </tr>
                    </thead>
                    <tbody id="medicines-rows" class="divide-y divide-zinc-800">
                    <?php foreach ($medicines as $medicine): ?>
                        <tr>
                            <td class="px-3 py-2 align-top font-medium"><?= h($medicine['name']) ?></td>
                            <td class="px-3 py-2 align-top"><?= h($medicine['unit']) ?></td>
                            <td class="max-w-md whitespace-pre-line px-3 py-2 align-top text-zinc-300"><?= h($medicine['specification']) ?></td>
                            <td class="px-3 py-2 align-top text-right">
                                <a href="admin.php?edit_medicine=<?= (int) $medicine['id'] ?>" class="text-emerald-300 hover:text-emerald-200">แก้ไข</a>
                                <form method="post" class="ml-3 inline" onsubmit="return confirm('ยืนยันลบรายการยานี้?')">
                                    <input type="hidden" name="action" value="delete_medicine">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $medicine['id'] ?>">
                                    <button class="text-red-300 hover:text-red-200">ลบ</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="compact-pager" data-live-pager="medicines" aria-label="เลื่อนหน้าผลการค้นหา">
                <span id="medicines-page-info">แสดงสูงสุด 50 รายการแรก</span>
                <div><button type="button" data-live-prev="medicines" disabled>ก่อนหน้า</button><button type="button" data-live-next="medicines">ถัดไป</button></div>
            </div>
            <div class="compact-utility-row">
              <details class="compact-tool" id="medicines-edit-box"  <?= $editMedicine ? 'open' : '' ?>>
                <summary>เพิ่ม / แก้ไขรายการยา</summary>
                <div class="compact-tool-content">
            <h2 class="mt-5 text-lg font-semibold"><?= $editMedicine ? 'แก้ไขรายการยา' : 'เพิ่มรายการยาเอง' ?></h2>
            <form method="post" class="mt-4 grid gap-3">
                <input type="hidden" name="action" value="save_medicine">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= h((string) ($editMedicine['id'] ?? 0)) ?>">
                <label class="block">
                    <span class="text-sm text-zinc-300">ชื่อยา</span>
                    <input name="name" value="<?= h($editMedicine['name'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                </label>
                <label class="block">
                    <span class="text-sm text-zinc-300">หน่วย</span>
                    <input name="unit" value="<?= h($editMedicine['unit'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                </label>
                <label class="block">
                    <span class="text-sm text-zinc-300">คุณสมบัติยา</span>
                    <textarea name="specification" rows="4" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400"><?= h($editMedicine['specification'] ?? '') ?></textarea>
                </label>
                <div class="flex gap-3">
                    <button class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">บันทึก</button>
                    <?php if ($editMedicine): ?>
                        <a href="admin.php" class="rounded-md border border-zinc-700 px-4 py-2 hover:border-emerald-400">ยกเลิก</a>
                    <?php endif; ?>
                </div>
            </form>

                </div>
              </details>
              <details class="compact-tool" id="medicines-import-box">
                <summary>นำเข้า Excel / CSV</summary>
                <div class="compact-tool-content">
            <form method="post" enctype="multipart/form-data" class="mt-4 rounded-md border border-zinc-800 bg-zinc-950 p-4">
                <input type="hidden" name="action" value="import_medicines">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <label class="block">
                    <span class="text-sm text-zinc-300">นำเข้ารายการยาจาก Excel/CSV UTF-8</span>
                    <input type="file" name="medicine_file" accept=".xlsx,.csv" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm">
                </label>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">นำเข้ารายการยา</button>
                    <a href="admin.php?action=sample_medicines" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:border-emerald-400">ดาวน์โหลด Excel ตัวอย่าง</a>
                    <span class="text-xs text-zinc-500">คอลัมน์: ชื่อยา/รายการยา, หน่วย, คุณสมบัติยา</span>
                </div>
            </form>

                </div>
              </details>
            </div>
            </details>
        </section>

        <section id="price-references" class="rounded-lg border border-zinc-800 bg-zinc-900/80 p-5">
            <details class="admin-panel" data-panel="prices" data-active="<?= $openPrices ? '1' : '0' ?>" <?= $openPrices ? 'open' : '' ?>>
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                <span>
                    <span class="block text-lg font-semibold">ราคากลางยา</span>
                    <span class="mt-1 block text-sm text-zinc-400"><?= number_format($priceReferenceTotal) ?> รายการ · นำเข้าราคากลางหรือแก้ไขรายการอ้างอิง</span>
                </span>
                <span class="panel-indicator rounded-md border border-zinc-700 px-3 py-1 text-sm text-zinc-300">แสดง</span>
            </summary>
            <form method="get" id="prices-search-form" class="compact-find" data-live-kind="prices">
                <label class="compact-find-label" for="prices-search-input">ค้นหาชื่อยา รูปแบบยา หน่วย หรือราคา</label>
                <div class="compact-find-row">
                  <input id="prices-search-input" name="price_search" value="<?= h($priceSearch) ?>" data-live-input="prices" type="search" autocomplete="off" placeholder="พิมพ์เพื่อค้นหาทันที…" aria-controls="prices-rows" />
                  <button type="submit" class="compact-search-submit">ค้นหา</button>
                  <button type="button" class="compact-clear" data-live-clear="prices">ล้าง</button>
                </div>
                <p id="prices-search-status" role="status" aria-live="polite" class="compact-status">กำลังแสดงรายการล่าสุดสูงสุด 50 รายการ</p>
            </form>
            <div class="compact-table-viewport">
                <table class="w-full min-w-[860px] text-sm">
                    <thead class="sticky top-0 bg-zinc-950 text-zinc-300">
                    <tr>
                        <th class="px-3 py-2 text-left">รายการยา</th>
                        <th class="px-3 py-2 text-left">รูปแบบยาและความแรง</th>
                        <th class="w-32 px-3 py-2 text-left">หน่วย</th>
                        <th class="w-36 px-3 py-2 text-right">ราคา/หน่วย</th>
                        <th class="w-36 px-3 py-2 text-right">จัดการ</th>
                    </tr>
                    </thead>
                    <tbody id="prices-rows" class="divide-y divide-zinc-800">
                    <?php foreach ($priceRefs as $row): ?>
                        <tr>
                            <td class="px-3 py-2 align-top font-medium"><?= h($row['medicine_name']) ?></td>
                            <td class="px-3 py-2 align-top text-zinc-300"><?= h($row['dosage_form_strength']) ?></td>
                            <td class="px-3 py-2 align-top"><?= h($row['unit']) ?></td>
                            <td class="px-3 py-2 align-top text-right"><?= number_format((float) $row['reference_price'], 2) ?></td>
                            <td class="px-3 py-2 align-top text-right">
                                <a href="admin.php?edit_price=<?= (int) $row['id'] ?>" class="text-emerald-300 hover:text-emerald-200">แก้ไข</a>
                                <form method="post" class="ml-3 inline" onsubmit="return confirm('ยืนยันลบราคากลางยานี้?')">
                                    <input type="hidden" name="action" value="delete_price_ref">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button class="text-red-300 hover:text-red-200">ลบ</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="compact-pager" data-live-pager="prices" aria-label="เลื่อนหน้าผลการค้นหา">
                <span id="prices-page-info">แสดงสูงสุด 50 รายการแรก</span>
                <div><button type="button" data-live-prev="prices" disabled>ก่อนหน้า</button><button type="button" data-live-next="prices">ถัดไป</button></div>
            </div>
            <div class="compact-utility-row">
              <details class="compact-tool" id="prices-edit-box"  <?= $editPrice ? 'open' : '' ?>>
                <summary>เพิ่ม / แก้ไขราคากลาง</summary>
                <div class="compact-tool-content">
            <form method="post" class="mt-5 grid gap-3 rounded-md border border-zinc-800 bg-zinc-950 p-4">
                <h3 class="font-medium"><?= $editPrice ? 'แก้ไขราคากลางยา' : 'เพิ่มราคากลางยาเอง' ?></h3>
                <input type="hidden" name="action" value="save_price_ref">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= h((string) ($editPrice['id'] ?? 0)) ?>">
                <label class="block">
                    <span class="text-sm text-zinc-300">รายการยา</span>
                    <input name="medicine_name" value="<?= h($editPrice['medicine_name'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                </label>
                <label class="block">
                    <span class="text-sm text-zinc-300">รูปแบบยาและความแรง</span>
                    <input name="dosage_form_strength" value="<?= h($editPrice['dosage_form_strength'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                </label>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm text-zinc-300">หน่วย</span>
                        <input name="price_unit" value="<?= h($editPrice['unit'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">ราคากลางต่อหน่วย</span>
                        <input name="reference_price" type="text" value="<?= h(isset($editPrice['reference_price']) ? number_format((float) $editPrice['reference_price'], 2) : '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-right outline-none focus:border-emerald-400">
                    </label>
                </div>
                <div class="flex gap-3">
                    <button class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">บันทึก</button>
                    <?php if ($editPrice): ?>
                        <a href="admin.php" class="rounded-md border border-zinc-700 px-4 py-2 hover:border-emerald-400">ยกเลิก</a>
                    <?php endif; ?>
                </div>
            </form>

                </div>
              </details>
              <details class="compact-tool" id="prices-import-box">
                <summary>นำเข้า Excel / CSV</summary>
                <div class="compact-tool-content">
            <form id="price_import_form" method="post" enctype="multipart/form-data" class="mt-4 rounded-md border border-zinc-800 bg-zinc-950 p-4">
                <input type="hidden" name="action" value="import_price_refs">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" id="import_id" name="import_id" value="">
                <label class="block">
                    <span class="text-sm text-zinc-300">นำเข้า Excel/CSV</span>
                    <input type="file" name="price_file" accept=".xlsx,.csv" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm">
                </label>
                <label class="mt-3 flex items-center gap-2 text-sm text-zinc-300">
                    <input type="checkbox" name="clear_existing" value="1" class="rounded border-zinc-700 bg-zinc-950">
                    ล้างข้อมูลเดิมก่อนนำเข้า
                </label>
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <button id="import_button" class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">นำเข้า</button>
                    <a href="admin.php?action=sample_price_refs" class="rounded-md border border-zinc-700 px-4 py-2 text-sm text-zinc-200 hover:border-emerald-400">ดาวน์โหลด Excel ตัวอย่าง</a>
                    <span id="import_status" class="text-sm text-zinc-400">ยังไม่ได้นำเข้า</span>
                </div>
                <p class="mt-2 text-xs text-zinc-500">คอลัมน์ที่รองรับ: รายการยา, รูปแบบยาและความแรง, หน่วย, ราคากลางยาต่อหน่วย (บาท)</p>
            </form>

                </div>
              </details>
            </div>
            </details>
        </section>
        <section id="document-signers" class="rounded-lg border border-zinc-800 bg-zinc-900/80 p-5">
            <details class="admin-panel" data-panel="signers" data-active="<?= $openSigners ? '1' : '0' ?>" <?= $openSigners ? 'open' : '' ?>>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
                    <span>
                        <span class="block text-lg font-semibold">ผู้เสนอและผู้อนุมัติเอกสาร</span>
                        <span class="mt-1 block text-sm text-zinc-400">ค่าเริ่มต้นสำหรับชุดเอกสารใหม่ โดยไม่รวมรายชื่อกรรมการ</span>
                    </span>
                    <span class="panel-indicator rounded-md border border-zinc-700 px-3 py-1 text-sm text-zinc-300">แสดง</span>
                </summary>

                <p class="compact-subnote">ค่าเริ่มต้นสำหรับบันทึกขออนุมัติในหลักการ (หัวข้อ 1) · เอกสารอื่นใช้ผู้ลงนามตามชุดเอกสารที่บันทึกไว้</p>

                <form method="post" class="mt-5 rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/70 to-white p-4 sm:p-5">
                    <input type="hidden" name="action" value="save_document_signer_defaults">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <div class="mb-4"><h2 class="font-semibold">ค่าเริ่มต้นหัวข้อ 1 — บันทึกขออนุมัติในหลักการ</h2><p class="mt-1 text-xs text-zinc-500">ชุดเอกสารที่บันทึกชื่อไว้แล้วจะยังใช้ข้อมูลเดิม ส่วนชุดใหม่หรือช่องเดิมที่ว่างจะใช้ค่านี้</p></div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block"><span class="text-sm text-zinc-300">ชื่อผู้เสนอ</span><input name="proposer_name" value="<?= h((string) $principleSignerDefaults['proposer_name']) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-white px-3 py-2 outline-none"></label>
                        <label class="block"><span class="text-sm text-zinc-300">ตำแหน่งผู้เสนอ</span><textarea name="proposer_position" rows="2" class="mt-1 w-full rounded-md border border-zinc-700 bg-white px-3 py-2 outline-none"><?= h((string) $principleSignerDefaults['proposer_position']) ?></textarea></label>
                        <label class="block"><span class="text-sm text-zinc-300">ชื่อผู้อนุมัติ</span><input name="approver_name" value="<?= h((string) $principleSignerDefaults['approver_name']) ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-white px-3 py-2 outline-none"></label>
                        <label class="block"><span class="text-sm text-zinc-300">ตำแหน่งผู้อนุมัติ</span><textarea name="approver_position" rows="2" class="mt-1 w-full rounded-md border border-zinc-700 bg-white px-3 py-2 outline-none"><?= h((string) $principleSignerDefaults['approver_position']) ?></textarea></label>
                    </div>
                    <div class="mt-4 flex justify-end"><button class="rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">บันทึกค่าผู้ลงนาม</button></div>
                </form>
            </details>
        </section>

    </div>
</main>
<script>
document.querySelectorAll('.admin-panel').forEach((panel) => {
    const key = `admin-panel:${panel.dataset.panel || ''}`;
    const indicator = panel.querySelector('.panel-indicator');
    if (panel.dataset.active !== '1') {
        const saved = localStorage.getItem(key);
        if (saved !== null) {
            panel.open = saved === '1';
        }
    }
    const updateIndicator = () => {
        if (indicator) indicator.textContent = panel.open ? 'ซ่อน' : 'แสดง';
    };
    panel.addEventListener('toggle', () => {
        localStorage.setItem(key, panel.open ? '1' : '0');
        updateIndicator();
    });
    updateIndicator();
});

// Real-time search is read-only. Saving, deletion and import still use their original forms.
(function () {
    'use strict';
    const tokenField = document.querySelector('input[name="csrf_token"]');
    const csrfToken = tokenField ? tokenField.value : '';
    const configs = {
        medicines: { endpoint: 'live_medicines', param: 'medicine_search', tbody: 'medicines-rows', columns: 4 },
        prices: { endpoint: 'live_prices', param: 'price_search', tbody: 'prices-rows', columns: 5 }
    };
    const addCell = (row, value, className) => {
        const cell = document.createElement('td');
        cell.className = className || 'px-3 py-2 align-top';
        cell.textContent = String(value ?? '');
        row.appendChild(cell);
        return cell;
    };
    const hidden = (form, name, value) => {
        const field = document.createElement('input');
        field.type = 'hidden'; field.name = name; field.value = String(value);
        form.appendChild(field);
    };
    function appendActions(row, kind, id) {
        const cell = document.createElement('td');
        cell.className = 'px-3 py-2 align-top text-right compact-row-actions';
        const edit = document.createElement('a');
        edit.href = 'admin.php?' + (kind === 'medicines' ? 'edit_medicine=' : 'edit_price=') + encodeURIComponent(String(id));
        edit.textContent = 'แก้ไข';
        cell.appendChild(edit);
        const form = document.createElement('form');
        form.method = 'post'; form.className = 'ml-3 inline';
        form.addEventListener('submit', (event) => {
            if (!confirm(kind === 'medicines' ? 'ยืนยันลบรายการยานี้?' : 'ยืนยันลบราคากลางยานี้?')) event.preventDefault();
        });
        hidden(form, 'action', kind === 'medicines' ? 'delete_medicine' : 'delete_price_ref');
        hidden(form, 'csrf_token', csrfToken);
        hidden(form, 'id', id);
        const del = document.createElement('button');
        del.type = 'submit'; del.textContent = 'ลบ'; del.className = 'compact-delete';
        form.appendChild(del); cell.appendChild(form); row.appendChild(cell);
    }
    function buildRows(kind, rows, tbody, span) {
        const frag = document.createDocumentFragment();
        if (!rows.length) {
            const tr = document.createElement('tr');
            const td = document.createElement('td'); td.colSpan = span;
            td.className = 'compact-empty'; td.textContent = 'ไม่พบรายการที่ตรงกับคำค้นหา';
            tr.appendChild(td); frag.appendChild(tr);
        }
        rows.forEach((item) => {
            const tr = document.createElement('tr');
            if (kind === 'medicines') {
                addCell(tr, item.name, 'px-3 py-2 align-top font-medium');
                addCell(tr, item.unit);
                addCell(tr, item.specification, 'px-3 py-2 align-top compact-spec');
            } else {
                addCell(tr, item.medicine_name, 'px-3 py-2 align-top font-medium');
                addCell(tr, item.dosage_form_strength, 'px-3 py-2 align-top compact-spec');
                addCell(tr, item.unit);
                const price = Number(item.reference_price);
                addCell(tr, Number.isFinite(price) ? price.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : item.reference_price,
                    'px-3 py-2 align-top text-right');
            }
            appendActions(tr, kind, Number(item.id));
            frag.appendChild(tr);
        });
        tbody.replaceChildren(frag);
    }
    Object.entries(configs).forEach(([kind, config]) => {
        const form = document.getElementById(kind + '-search-form');
        const input = document.getElementById(kind + '-search-input');
        const tbody = document.getElementById(config.tbody);
        const status = document.getElementById(kind + '-search-status');
        const info = document.getElementById(kind + '-page-info');
        const prev = document.querySelector('[data-live-prev="' + kind + '"]');
        const next = document.querySelector('[data-live-next="' + kind + '"]');
        const clear = document.querySelector('[data-live-clear="' + kind + '"]');
        if (!form || !input || !tbody || !status || !prev || !next || !clear) return;
        let controller = null, timer = null, revision = 0, page = 1, total = 0;
        function updateUrl() {
            try {
                const url = new URL(window.location.href);
                const query = input.value.trim();
                if (query) url.searchParams.set(config.param, query);
                else url.searchParams.delete(config.param);
                window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
            } catch (_error) { /* Search still works if history is restricted. */ }
        }
        async function refresh() {
            const serial = ++revision;
            if (controller) controller.abort();
            controller = new AbortController();
            status.textContent = 'กำลังค้นหา…';
            status.dataset.state = 'loading';
            try {
                const params = new URLSearchParams({action: config.endpoint, q: input.value.trim(), page:String(page)});
                const response = await fetch('admin.php?' + params, {
                    credentials:'same-origin', signal: controller.signal,
                    headers: {'Accept':'application/json'}, cache:'no-store'
                });
                if (!response.ok) throw new Error('Search unavailable');
                const data = await response.json();
                if (serial !== revision || !data.ok || !Array.isArray(data.rows)) return;
                total = Number(data.total) || 0;
                buildRows(kind, data.rows, tbody, config.columns);
                const count = data.rows.length;
                const start = count ? ((page - 1) * 50 + 1) : 0;
                const end = count ? start + count - 1 : 0;
                status.textContent = 'พบ ' + total.toLocaleString('th-TH') + ' รายการ · แสดง ' + start + '–' + end;
                status.dataset.state = 'ready';
                info.textContent = 'หน้า ' + page.toLocaleString('th-TH') + ' จาก ' + Math.max(1, Math.ceil(total / 50)).toLocaleString('th-TH');
                prev.disabled = page <= 1;
                next.disabled = page * 50 >= total;
                const viewport = tbody.closest('.compact-table-viewport');
                if (viewport) viewport.scrollTop = 0;
                updateUrl();
            } catch (error) {
                if (error.name === 'AbortError' || serial !== revision) return;
                status.textContent = 'ค้นหาไม่สำเร็จ กรุณาลองใหม่'; status.dataset.state = 'error';
            }
        }
        function schedule() {
            page = 1;
            clearTimeout(timer);
            // Abort immediately, so an older response cannot replace the user's latest query.
            revision++;
            if (controller) controller.abort();
            status.textContent = 'กำลังค้นหา…';
            timer = setTimeout(refresh, 260);
        }
        input.addEventListener('input', schedule);
        form.addEventListener('submit', (event) => {event.preventDefault(); clearTimeout(timer); page = 1; refresh();});
        clear.addEventListener('click', () => {input.value = ''; schedule(); input.focus();});
        prev.addEventListener('click', () => {if (page > 1) {page--; refresh();}});
        next.addEventListener('click', () => {if (page * 50 < total) {page++; refresh();}});
        refresh();
    });
    // Editing via its existing URL remains compatible; reveal the relevant form.
    const params = new URLSearchParams(location.search);
    const editBox = params.has('edit_medicine') ? document.getElementById('medicines-edit-box') :
        params.has('edit_price') ? document.getElementById('prices-edit-box') : null;
    if (editBox) {
        editBox.open = true;
        const parent = editBox.closest('.admin-panel');
        if (parent) parent.open = true;
        requestAnimationFrame(() => editBox.scrollIntoView({block:'center'}));
    }
})();

const importForm = document.getElementById('price_import_form');
const importStatus = document.getElementById('import_status');
const importButton = document.getElementById('import_button');
const importIdInput = document.getElementById('import_id');
let importPollTimer = null;

function newImportId() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
}

function setImportStatus(data) {
    const processed = Number(data.processed || 0);
    const total = Number(data.total || 0);
    const imported = Number(data.imported || 0);
    const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
    if (data.status === 'done') {
        importStatus.textContent = `เสร็จแล้ว ${imported.toLocaleString()} รายการ จาก ${total.toLocaleString()} แถว`;
        importStatus.className = 'text-sm text-emerald-300';
        importButton.disabled = false;
        importButton.classList.remove('opacity-60');
        return;
    }
    if (data.status === 'error') {
        importStatus.textContent = data.message || 'นำเข้าไม่สำเร็จ';
        importStatus.className = 'text-sm text-red-300';
        importButton.disabled = false;
        importButton.classList.remove('opacity-60');
        return;
    }
    importStatus.textContent = total > 0
        ? `กำลังนำเข้า ${processed.toLocaleString()} / ${total.toLocaleString()} แถว (${percent}%)`
        : (data.message || 'กำลังเตรียมไฟล์');
    importStatus.className = 'text-sm text-amber-300';
}

function pollImportProgress(importId) {
    clearInterval(importPollTimer);
    importPollTimer = setInterval(async () => {
        const response = await fetch(`admin.php?action=import_progress&id=${encodeURIComponent(importId)}&t=${Date.now()}`);
        const data = await response.json();
        setImportStatus(data);
        if (data.status === 'done' || data.status === 'error') {
            clearInterval(importPollTimer);
        }
    }, 500);
}

importForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    const fileInput = importForm.querySelector('input[type="file"]');
    if (!fileInput?.files?.length) {
        importStatus.textContent = 'กรุณาเลือกไฟล์ก่อน';
        importStatus.className = 'text-sm text-red-300';
        return;
    }

    const importId = newImportId();
    importIdInput.value = importId;
    const formData = new FormData(importForm);
    const xhr = new XMLHttpRequest();

    importButton.disabled = true;
    importButton.classList.add('opacity-60');
    importStatus.textContent = 'กำลังอัปโหลดไฟล์ 0%';
    importStatus.className = 'text-sm text-amber-300';

    xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable) {
            const percent = Math.round((event.loaded / event.total) * 100);
            importStatus.textContent = `กำลังอัปโหลดไฟล์ ${percent}%`;
        }
    });

    xhr.addEventListener('loadstart', () => pollImportProgress(importId));
    xhr.addEventListener('load', () => {
        if (xhr.status >= 400) {
            clearInterval(importPollTimer);
            setImportStatus({ status: 'error', message: 'นำเข้าไม่สำเร็จ' });
        }
    });
    xhr.addEventListener('error', () => {
        clearInterval(importPollTimer);
        setImportStatus({ status: 'error', message: 'เชื่อมต่อไม่สำเร็จ' });
    });

    xhr.open('POST', 'admin.php');
    xhr.send(formData);
});
</script>
</body>
</html>
