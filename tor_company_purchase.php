<?php
declare(strict_types=1);
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/procurement_case.php';
require_once __DIR__ . '/core/word_export_lib.php';
require_once __DIR__ . '/core/auth.php';
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


function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function money(float $value): string { return number_format($value, 2); }
function quantityNumber(float|int|string $value): string
{
    $number = (float) $value;
    if (abs($number - round($number)) < 0.000000001) {
        return number_format($number, 0, '.', ',');
    }
    return rtrim(rtrim(number_format($number, 2, '.', ','), '0'), '.');
}
function post(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }

function thaiIntegerText(int|string $number): string
{
    $number = (string) $number;
    $number = ltrim($number, '0');
    if ($number === '') {
        return 'ศูนย์';
    }
    if ($number[0] === '-') {
        return 'ลบ' . thaiIntegerText(substr($number, 1));
    }

    $value = (int) $number;
    if ($value >= 1000000) {
        $million = intdiv($value, 1000000);
        $remainder = $value % 1000000;
        return thaiIntegerText($million) . 'ล้าน' . ($remainder > 0 ? thaiIntegerText($remainder) : '');
    }

    $digits = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    $places = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];
    $length = strlen($number);
    $text = '';

    for ($i = 0; $i < $length; $i++) {
        $digit = (int) $number[$i];
        $place = $length - $i - 1;
        if ($digit === 0) {
            continue;
        }
        if ($place === 0 && $digit === 1 && $length > 1) {
            $text .= 'เอ็ด';
        } elseif ($place === 1 && $digit === 1) {
            $text .= 'สิบ';
        } elseif ($place === 1 && $digit === 2) {
            $text .= 'ยี่สิบ';
        } else {
            $text .= $digits[$digit] . $places[$place];
        }
    }

    return $text;
}

function thaiBahtText(float $amount): string
{
    $amount = round($amount, 2);
    $negative = $amount < 0;
    $formatted = number_format(abs($amount), 2, '.', '');
    [$baht, $satang] = explode('.', $formatted);
    $text = thaiIntegerText($baht) . 'บาท';
    $text .= ((int) $satang === 0) ? 'ถ้วน' : thaiIntegerText($satang) . 'สตางค์';
    return $negative ? 'ลบ' . $text : $text;
}

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

function normalizeNumberInput($value): string
{
    $value = arabicDigits((string) $value);
    return trim(str_replace([',', ' '], '', $value));
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

function filenameDate(string $date): string
{
    $date = trim(arabicDigits($date));
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
        if (preg_match('/(\d{1,2}).*?' . preg_quote($month, '/') . '.*?(\d{4})/u', $date, $match)) {
            return str_pad($match[1], 2, '0', STR_PAD_LEFT) . '-' . $no . '-' . $match[2];
        }
    }
    return str_replace(['/', '\\'], '-', $date);
}

function torCompanyPurchaseWordFilename(int $caseId, array $items): string
{
    $memoNo = '';
    $docDate = '';
    if ($caseId > 0) {
        $case = procurementCase($caseId);
        if ($case) {
            $memoNo = (string) ($case['principle_memo_no_suffix'] ?? '');
            $docDate = (string) ($case['principle_doc_date'] ?? '');
        }
    }

    return trim(
        'ชุดเอกสาร 3 TOR เลขที่หนังสือ '
        . trim(arabicDigits($memoNo))
        . ' ลงวันที่ '
        . filenameDate($docDate)
        . ' ราย '
        . count($items)
        . ' รายการ.docx'
    );
}

function thaiVisibleDigits(string $html): string
{
    if (!class_exists(DOMDocument::class)) {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//text()[not(ancestor::style) and not(ancestor::script)]') as $node) {
        $node->nodeValue = thaiDigits($node->nodeValue);
    }
    $output = $dom->saveHTML();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return preg_replace('/^<\?xml encoding="UTF-8"\?>\s*/', '', $output) ?? $output;
}

function loadData(): array
{
    $pdo = db();
    ensureProcurementTables();
    ensureCommitteeSetsTable();
    ensureTorHistoriesTable();
    return [
        'medicines' => $pdo->query('SELECT * FROM medicines ORDER BY name')->fetchAll(),
        'drafts' => $pdo->query('SELECT * FROM price_source_drafts ORDER BY created_at DESC, id DESC')->fetchAll(),
        'committee_sets' => $pdo->query('SELECT * FROM committee_sets ORDER BY title')->fetchAll(),
        'histories' => $pdo->query('SELECT * FROM tor_histories ORDER BY created_at DESC, id DESC')->fetchAll(),
        'procurement_cases' => $pdo->query('SELECT * FROM procurement_cases ORDER BY created_at DESC')->fetchAll(),
        'procurement_documents' => $pdo->query('SELECT * FROM procurement_documents ORDER BY created_at DESC')->fetchAll(),
    ];
}

function ensureCommitteeSetsTable(): void
{
    requireDatabaseTables(['committee_sets']);
}

function ensureMedicinePriceReferencesTable(): void
{
    requireDatabaseTables(['medicine_price_references']);
}

function ensureTorHistoriesTable(): void
{
    requireDatabaseTables(['tor_histories']);
}

function saveTorHistory(array $committee, array $items, string $priceSource, int $caseId = 0): int
{
    ensureTorHistoriesTable();
    $title = 'TOR จัดซื้อยา ' . thaiDigits((string) count($items)) . ' รายการ - ' . date('Y-m-d H:i');
    $historyItems = array_map(static fn(array $item): array => [
        'name' => (string) ($item['medicine']['name'] ?? ''),
        'unit' => (string) ($item['medicine']['unit'] ?? ''),
        'specification' => (string) ($item['medicine']['specification'] ?? ''),
        'medicine_id' => (int) ($item['medicine']['id'] ?? 0),
        'quantity' => (float) $item['quantity'],
        'requested_quantity' => (float) ($item['requested_quantity'] ?? $item['quantity']),
        'package_size' => (float) ($item['package_size'] ?? 0),
        'package_count' => (int) ($item['package_count'] ?? 0),
        'reference_price' => (float) $item['reference_price'],
        'offer_price' => (float) $item['offer_price'],
        'offer_total' => (float) $item['offer_total'],
    ], $items);
    $stmt = db()->prepare('INSERT INTO tor_histories (case_id, title, committee_json, items_json, price_source) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $caseId > 0 ? $caseId : null,
        $title,
        json_encode(array_values($committee), JSON_UNESCAPED_UNICODE),
        json_encode($historyItems, JSON_UNESCAPED_UNICODE),
        $priceSource,
    ]);
    return (int) db()->lastInsertId();
}

function updateTorHistory(int $id, array $committee, array $items, string $priceSource, int $caseId = 0): void
{
    ensureTorHistoriesTable();
    $historyItems = array_map(static fn(array $item): array => [
        'name' => (string) ($item['medicine']['name'] ?? ''),
        'unit' => (string) ($item['medicine']['unit'] ?? ''),
        'specification' => (string) ($item['medicine']['specification'] ?? ''),
        'medicine_id' => (int) ($item['medicine']['id'] ?? 0),
        'quantity' => (float) $item['quantity'],
        'requested_quantity' => (float) ($item['requested_quantity'] ?? $item['quantity']),
        'package_size' => (float) ($item['package_size'] ?? 0),
        'package_count' => (int) ($item['package_count'] ?? 0),
        'reference_price' => (float) $item['reference_price'],
        'offer_price' => (float) $item['offer_price'],
        'offer_total' => (float) $item['offer_total'],
    ], $items);
    $stmt = db()->prepare('UPDATE tor_histories SET case_id = ?, committee_json = ?, items_json = ?, price_source = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([
        $caseId > 0 ? $caseId : null,
        json_encode(array_values($committee), JSON_UNESCAPED_UNICODE),
        json_encode($historyItems, JSON_UNESCAPED_UNICODE),
        $priceSource,
        $id,
    ]);
}

function committeeText(string $name, string $position, string $role): string
{
    return trim($name . "\n" . $position . "\n" . $role);
}

function priceReferenceSearchTokens(string $query): array
{
    $query = trim(arabicDigits(mb_strtolower($query, 'UTF-8')));
    if ($query === '') {
        return [];
    }

    preg_match_all('/[\p{L}\p{N}.%]+/u', $query, $matches);
    $stopWords = [
        'tab' => true,
        'tabs' => true,
        'tablet' => true,
        'tablets' => true,
        'cap' => true,
        'caps' => true,
        'capsule' => true,
        'capsules' => true,
        'inj' => true,
        'injection' => true,
        'syrup' => true,
        'solution' => true,
        'cream' => true,
        'ointment' => true,
        'ยา' => true,
        'เม็ด' => true,
        'แคปซูล' => true,
        'ขวด' => true,
        'หลอด' => true,
        'amp' => true,
        'vial' => true,
        'mg' => true,
        'mcg' => true,
        'g' => true,
        'ml' => true,
        'iu' => true,
        'meq' => true,
    ];
    $tokens = [];
    foreach ($matches[0] ?? [] as $token) {
        $token = trim($token, ". \t\n\r\0\x0B");
        if ($token === '' || mb_strlen($token, 'UTF-8') < 2 || isset($stopWords[$token])) {
            continue;
        }
        $tokens[$token] = true;
    }

    return array_slice(array_keys($tokens), 0, 6);
}

function priceReferenceTokenVariants(string $token): array
{
    $variants = [$token => true];
    $synonyms = [
        'acyclovir' => ['aciclovir'],
        'aciclovir' => ['acyclovir'],
    ];
    foreach ($synonyms[$token] ?? [] as $synonym) {
        $variants[$synonym] = true;
    }
    return array_keys($variants);
}

function isStrengthToken(string $token): bool
{
    return (bool) preg_match('/^\d+(?:\.\d+)?%?$/', $token);
}

if (($_GET['action'] ?? '') === 'search_price_reference') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        ensureMedicinePriceReferencesTable();
        $q = trim((string) ($_GET['q'] ?? ''));
        $tokens = priceReferenceSearchTokens($q);
        $nameTokens = array_values(array_filter($tokens, static fn(string $token): bool => !isStrengthToken($token)));
        $strengthTokens = array_values(array_filter($tokens, static fn(string $token): bool => isStrengthToken($token)));
        $whereParams = [];
        $scoreParams = [];
        $where = '? = \'\'';
        $whereParams[] = $q;
        $scoreParts = ['CASE WHEN CONCAT_WS(\' \', medicine_name, dosage_form_strength, unit) LIKE ? THEN 100 ELSE 0 END'];
        $scoreParams[] = '%' . $q . '%';
        if ($nameTokens) {
            $where .= ' OR (';
            $nameClauses = [];
            foreach ($nameTokens as $token) {
                foreach (priceReferenceTokenVariants($token) as $variant) {
                    $nameClauses[] = 'medicine_name LIKE ?';
                    $term = '%' . $variant . '%';
                    $whereParams[] = $term;
                    $scoreParts[] = 'CASE WHEN medicine_name LIKE ? THEN 120 ELSE 0 END';
                    $scoreParams[] = $term;
                    $scoreParts[] = 'CASE WHEN dosage_form_strength LIKE ? THEN 20 ELSE 0 END';
                    $scoreParams[] = $term;
                }
            }
            $where .= implode(' OR ', $nameClauses) . ')';
        }
        foreach ($strengthTokens as $token) {
            $term = '%' . $token . '%';
            if (!$nameTokens) {
                $where .= ' OR dosage_form_strength LIKE ? OR medicine_name LIKE ?';
                array_push($whereParams, $term, $term);
            }
            $scoreParts[] = 'CASE WHEN dosage_form_strength LIKE ? THEN 80 ELSE 0 END';
            $scoreParams[] = $term;
            $scoreParts[] = 'CASE WHEN medicine_name LIKE ? THEN 10 ELSE 0 END';
            $scoreParams[] = $term;
        }
        if (!$tokens && $q !== '') {
            $where .= ' OR medicine_name LIKE ? OR dosage_form_strength LIKE ? OR unit LIKE ?';
            $term = '%' . $q . '%';
            array_push($whereParams, $term, $term, $term);
        }
        $scoreSql = implode(' + ', $scoreParts);
        $stmt = db()->prepare("
            SELECT id, medicine_name, dosage_form_strength, unit, reference_price, ($scoreSql) AS match_score
            FROM medicine_price_references
            WHERE $where
            ORDER BY
                match_score DESC,
                medicine_name,
                dosage_form_strength,
                unit
            LIMIT 30
        ");
        $stmt->execute([...$scoreParams, ...$whereParams]);
        echo json_encode(['ok' => true, 'items' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function mapById(array $rows): array
{
    $map = [];
    foreach ($rows as $row) {
        $map[(int) $row['id']] = $row;
    }
    return $map;
}

function selectedDraft(array $drafts, int $id): ?array
{
    foreach ($drafts as $draft) {
        if ((int) $draft['id'] === $id) return $draft;
    }
    return null;
}

function reportItems(array $medicines, array $procurementCases = []): array
{
    global $packageValidationError;
    $packageValidationError = '';
    $medicineMap = mapById($medicines);
    $ids = $_POST['medicine_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $refs = $_POST['reference_price'] ?? [];
    $offers = $_POST['offer_price'] ?? [];
    $requestedQuantities = $_POST['requested_quantity'] ?? [];
    $packageSizes = $_POST['package_size'] ?? [];
    $packageMode = post('package_mode') === '1';
    $sourceQuantityByMedicineId = [];
    $caseId = (int) post('case_id');
    if ($packageMode && $caseId > 0) {
        foreach ($procurementCases as $case) {
            if ((int) ($case['id'] ?? 0) !== $caseId) continue;
            $caseItems = json_decode((string) ($case['items_json'] ?? '[]'), true);
            foreach (is_array($caseItems) ? $caseItems : [] as $caseItem) {
                $sourceMedicineId = (int) ($caseItem['medicine_id'] ?? 0);
                if ($sourceMedicineId > 0) {
                    $sourceQuantityByMedicineId[$sourceMedicineId] = max(0, (float) normalizeNumberInput($caseItem['quantity'] ?? 0));
                }
            }
            break;
        }
    }
    $items = [];

    $max = max(count($ids), count($quantities), count($refs), count($offers), 1);
    for ($i = 0; $i < $max; $i++) {
        $id = (int) ($ids[$i] ?? 0);
        if (!$id || !isset($medicineMap[$id])) continue;

        $quantity = max(0, (float) normalizeNumberInput($quantities[$i] ?? 0));
        $requestedQuantity = max(0, (float) normalizeNumberInput($requestedQuantities[$i] ?? $quantity));
        if (isset($sourceQuantityByMedicineId[$id])) {
            $requestedQuantity = $sourceQuantityByMedicineId[$id];
        }
        $packageSize = $packageMode ? max(0, (float) normalizeNumberInput($packageSizes[$i] ?? 0)) : 0;
        $packageCount = 0;
        if ($packageMode) {
            $medicineName = (string) ($medicineMap[$id]['name'] ?? ('ลำดับที่ ' . ($i + 1)));
            if ($requestedQuantity <= 0 || $packageSize <= 0) {
                $packageValidationError = 'กรุณากรอกขนาดบรรจุให้ครบ และจำนวนตั้งต้นต้องมากกว่า 0: ' . $medicineName;
            } else {
                $quotient = $requestedQuantity / $packageSize;
                $nearestInteger = round($quotient);
                if (abs($quotient - $nearestInteger) > 0.000000001) {
                    $packageValidationError = 'จำนวนตั้งต้นต้องหารด้วยขนาดบรรจุลงตัว: ' . $medicineName;
                } else {
                    $packageCount = (int) $nearestInteger;
                    $quantity = $packageCount;
                }
            }
        }
        $referencePrice = max(0, (float) normalizeNumberInput($refs[$i] ?? 0));
        $offerPrice = max(0, (float) normalizeNumberInput($offers[$i] ?? 0));
        $pricedQuantity = $quantity;
        $items[] = [
            'medicine' => $medicineMap[$id],
            'quantity' => $quantity,
            'requested_quantity' => $requestedQuantity,
            'package_size' => $packageSize,
            'package_count' => $packageCount,
            'reference_price' => $referencePrice,
            'offer_price' => $offerPrice,
            'reference_total' => $pricedQuantity * $referencePrice,
            'offer_total' => $pricedQuantity * $offerPrice,
        ];
    }

    return $items;
}

function torFiscalYearForCase(array $cases, int $caseId): string
{
    foreach ($cases as $case) {
        if ((int) ($case['id'] ?? 0) === $caseId) {
            $year = trim((string) ($case['fiscal_year'] ?? ''));
            return $year !== '' ? thaiDigits($year) : '................';
        }
    }
    return '................';
}

function reportHtml(array $report): string
{
    $refTotal = array_sum(array_column($report['items'], 'reference_total'));
    $offerTotal = array_sum(array_column($report['items'], 'offer_total'));
    $hasPackageItems = count(array_filter(
        $report['items'],
        static fn(array $item): bool => (float) ($item['package_size'] ?? 0) > 0
    )) > 0;
    $committee = array_map(fn($name) => $name !== '' ? $name : '................................', $report['committee']);
    $signatureCell = static function (string $person): string {
        $lines = preg_split('/\R/u', trim($person)) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
        $personHtml = implode('', array_map(
            static fn(string $line): string => '<div class="signature-name-line">' . h($line) . '</div>',
            $lines
        ));
        return '<td><div class="signature-line">ลงชื่อ........................................</div>'
            . '<div class="signature-name">' . $personHtml . '</div></td>';
    };
    $torFlowAttributes = 'align="left" style="white-space:normal;text-align:left!important;text-justify:auto!important;-ms-text-justify:auto!important;letter-spacing:normal!important;word-spacing:normal!important"';
    ob_start();
    ?>
    <!doctype html>
    <html lang="th">
    <head>
        <meta charset="utf-8">
        <title>รายงาน TOR จัดซื้อยา</title>
        <style>
            @page { size: A4; margin: 2cm 2cm 2cm 3cm; }
            body { font-family: "TH SarabunIT๙", "TH Sarabun New", "Sarabun", sans-serif; color: #111; font-size: 16pt; line-height: 1; margin: 0; }
            h1 { text-align: center; font-family: "TH SarabunIT๙", "TH Sarabun New", sans-serif; font-size: 16pt; margin: 0; line-height: 1; }
            h2 { font-size: 16pt; margin: 14pt 0 6pt; }
            p { margin: 0; mso-margin-top-alt: 0pt; mso-margin-bottom-alt: 0pt; text-align: justify; text-justify: inter-character; -ms-text-justify: distribute; text-align-last: left; text-indent: 1.5cm; line-height: 1; mso-line-height-rule: exactly; }
            .indent, .subindent, .price-note { text-align: justify; text-justify: inter-character; -ms-text-justify: distribute; text-align-last: left; }
            table { width: 100%; border-collapse: collapse; margin-top: 8px; }
            th, td { border: 1px solid #333; padding: 5px 7px; vertical-align: top; }
            .price-table th, .price-table td { padding: 4px 3px; }
            th { font-weight: normal; text-align: center; }
            .right { text-align: right; }
            .center { text-align: center; }
            .red { color: #c62828; }
            .page { min-height: 25.2cm; position: relative; page-break-after: auto; padding-bottom: 2.8cm; box-sizing: border-box; }
            .compact-page { padding-bottom: 2.2cm; }
            .compact-page .section-title { margin: 5pt 0 2pt; }
            .compact-page p, .compact-page .subindent { margin-bottom: 1pt; }
            .tor-continuous-flow p, .tor-continuous-flow .subindent {
                white-space: normal;
                text-align: left;
                text-justify: auto;
                -ms-text-justify: auto;
                letter-spacing: normal;
                word-spacing: normal;
            }
            .compact-page .signature td { padding-top: 4px; }
            .compact-page .signature-name { margin-top: 6px; }
            .last-page { page-break-after: auto; }
            .stars { text-align: center; letter-spacing: 2px; margin: 6px 0 18px; }
            .section-title { font-weight: bold; margin: 10pt 0 4pt; text-align: left; text-indent: 0; }
            .indent { margin: 0; text-indent: 1.5cm; }
            .subindent { margin: 0; text-indent: 1.5cm; }
            .signature { position: absolute; left: 0; right: 0; bottom: 0; color: #c62828; line-height: 1; padding-top: 14pt; box-sizing: border-box; }
            .signature table { border: 0; table-layout: fixed; margin: 0; }
            .signature td { border: 0; text-align: center; vertical-align: top; width: 33.33%; padding: 0 5pt; }
            .signature, .signature table, .signature tr, .signature td { page-break-inside: avoid; break-inside: avoid; }
            .signature-line { display: block; text-align: center; white-space: nowrap; }
            .signature-name { display: block; margin-top: 3pt; text-align: center; }
            .signature-name-line { display: block; margin: 0; line-height: 1; text-align: center; }
            .signature-final td { width: 50%; padding-top: 4px; }
            .signature-final .chair { width: 100%; padding-bottom: 6px; }
            .last-page .signature { position: static; margin-top: 10pt; }
            .last-page .signature table, .last-page .signature tbody { display: block; width: 100%; }
            .last-page .signature tr { display: flex; flex-wrap: wrap; width: 100%; }
            .last-page .signature td { display: block; width: 50%; box-sizing: border-box; padding-top: 10px; }
            .last-page .signature td:first-child { width: 100%; padding-bottom: 6px; }
            .price-note { text-indent: 1.5cm; }
            .no-border td { border: 0; }
            @media print { .no-print { display: none; } }
        </style>
        <!--[if gte mso 9]>
        <style>
            .detail-page { min-height: 0 !important; height: auto !important; position: static !important; padding-bottom: 0 !important; }
            .detail-page .signature { position: static !important; margin-top: 28pt !important; padding-top: 0 !important; }
        </style>
        <![endif]-->
    </head>
    <body>
        <button class="no-print" onclick="window.print()">พิมพ์ผ่านเบราว์เซอร์</button>

        <div class="page">
            <h1>รายละเอียดคุณลักษณะของพัสดุที่จะซื้อหรือจ้าง<br>ตามระเบียบกระทรวงการคลังว่าด้วยการจัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ พ.ศ. 2560<br>งานจัดซื้อเวชภัณฑ์ยาของหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง</h1>
            <div class="stars">******************************</div>

            <div class="section-title">1. หลักการและเหตุผล</div>
            <p>ด้วยหน่วยบริการประจำโรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง จะดำเนินการจัดซื้อยา                       ในบัญชียาหลักแห่งชาติ เพื่อสนับสนุนการให้บริการรักษาประชาชน ของหน่วยบริการประจำและ                 เครือข่ายปฐมภูมิจำนวน 5 แห่ง อำเภอสันกำแพง จังหวัดเชียงใหม่ สังกัดองค์การบริหารส่วนจังหวัดเชียงใหม่ ตามแผนงบประมาณเงินบำรุง โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง ปรับปรุงครั้งที่ 1 ปีงบประมาณ <?= h((string) ($report['fiscal_year'] ?? '................')) ?> หมวดค่าเวชภัณฑ์ยา ตั้งไว้ 2,000,000.00 บาท ( สองล้านบาทถ้วน )</p>

            <div class="section-title">2. วัตถุประสงค์</div>
            <p>สำหรับใช้ในการให้บริการรักษาพยาบาลผู้ป่วย เพื่อให้ยาเพียงพอ พร้อมใช้ มีคุณภาพต่อการให้บริการประชาชนของหน่วยบริการประจำและเครือข่ายปฐมภูมิจำนวน 5 แห่ง อำเภอสันกำแพง จังหวัดเชียงใหม่ สังกัดองค์การบริหารส่วนจังหวัดเชียงใหม่</p>

            <div class="section-title">3. คุณสมบัติผู้เสนอราคา</div>
            <div class="subindent">3.1 มีความสามารถตามกฎหมายกำหนด</div>
            <div class="subindent">3.2 ไม่เป็นบุคคลล้มละลาย</div>
            <div class="subindent">3.3 ไม่อยู่ระหว่างเลิกกิจการ</div>
            <div class="subindent">3.4 ไม่เป็นบุคคลซึ่งอยู่ระหว่างถูกระงับการยื่นข้อเสนอหรือทำสัญญากับหน่วยงานของรัฐไว้ชั่วคราว เนื่องจากเป็นผู้ที่ไม่ผ่านเกณฑ์การประเมินผลการปฏิบัติงานของผู้ประกอบการตามระเบียบที่รัฐมนตรีว่าการกระทรวงการคลัง กำหนดตามที่ประกาศเผยแพร่ในระบบเครือข่ายสารสนเทศของกรมบัญชีกลาง</div>
            <div class="subindent">3.5 ไม่เป็นบุคคลซึ่งถูกระบุชื่อไว้ในบัญชีรายชื่อผู้ทิ้งงานและได้แจ้งเวียนชื่อให้เป็นผู้ทิ้งงานของหน่วยงานของรัฐในระบบเครือข่ายสารสนเทศของกรมบัญชีกลาง ซึ่งรวมถึงนิติบุคคลที่ผู้ทิ้งงานเป็นหุ้นส่วนผู้จัดการ กรรมการผู้จัดการ ผู้บริหาร ผู้มีอำนาจในการดำเนินงานในกิจการของนิติบุคคลนั้นด้วย</div>
            <div class="subindent">3.6 มีคุณสมบัติและไม่มีลักษณะต้องห้ามตามที่คณะกรรมการนโยบายการจัดซื้อจัดจ้าง
และการบริหารพัสดุภาครัฐกำหนดในราชกิจจานุเบกษา
</div>
            <div class="subindent">3.7 เป็นบุคคลธรรมดาหรือนิติบุคคลผู้มีอาชีพขายพัสดุดังกล่าว</div>
            <div class="subindent">3.8 ไม่เป็นผู้มีผลประโยชน์ร่วมกันกับผู้ยื่นข้อเสนอรายอื่นที่เข้ายื่นข้อเสนอให้แก่ศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล ณ วันประกาศประกวดราคาอิเล็กทรอนิกส์ครั้งนี้</div>
            <div class="subindent">3.9 ไม่เป็นผู้รับเอกสิทธิ์หรือความคุ้มกัน ซึ่งอาจปฏิเสธไม่ยอมขึ้นศาลไทย เว้นแต่รัฐบาลของผู้ยื่นข้อเสนอได้มีคำสั่งให้สละเอกสิทธิ์และความคุ้มกันเช่นว่านั้น</div>

            <div class="signature">
                <table>
                    <tr>
                        <?= $signatureCell($committee[0]) ?>
                        <?= $signatureCell($committee[1]) ?>
                        <?= $signatureCell($committee[2]) ?>
                    </tr>
                </table>
            </div>
        </div>

        <?php
        $specChunks = array_chunk($report['items'], 3, true);
        $lastSpecChunkIndex = count($specChunks) - 1;
        $finishedProductNeedsOwnPage = count($report['items']) > 3
            && isset($specChunks[$lastSpecChunkIndex])
            && count($specChunks[$lastSpecChunkIndex]) >= 3;
        $pageNo = 2;
        ?>
        <?php foreach ($specChunks as $chunkIndex => $itemsChunk): ?>
        <div class="page detail-page <?= count($report['items']) === 3 && $chunkIndex === $lastSpecChunkIndex ? 'compact-page' : '' ?>">
            <div class="center word-inline-page-number" style="page-break-before: always; break-before: page;">- <?= $pageNo++ ?> -</div>
            <div class="section-title">4. รายละเอียดคุณลักษณะเฉพาะ<?= $chunkIndex > 0 ? ' (ต่อ)' : '' ?></div>
            <div class="section-title">1) คุณสมบัติทั่วไป<?= $chunkIndex > 0 ? ' (ต่อ)' : '' ?></div>
            <table class="detail-table">
                <thead>
                <tr>
                    <th style="width: 6%">ที่</th>
                    <th style="width: 28%">รายการ</th>
                    <th style="width: 18%">จำนวน</th>
                    <th>คุณสมบัติ</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($itemsChunk as $i => $item): ?>
                <tr>
                    <td class="center"><?= $i + 1 ?></td>
                    <td><?= h($item['medicine']['name']) ?></td>
                    <td class="right">
                        <?= h(quantityNumber(($item['package_size'] ?? 0) > 0 ? $item['requested_quantity'] : $item['quantity'])) ?> <?= h($item['medicine']['unit']) ?>
                    </td>
                    <td><?= nl2br(h($item['medicine']['specification'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($chunkIndex === $lastSpecChunkIndex && !$finishedProductNeedsOwnPage): ?>
            <div class="section-title">2) คุณสมบัติทางเทคนิคของ Finished product</div>
            <p>ผลการวิเคราะห์คุณภาพเป็นไปตาม Finished product Specification และ Drug substance Specification ซึ่งได้จดทะเบียนต่อสำนักงานคณะกรรมการอาหารและยา กระทรวงสาธารณสุข (เภสัชตำรับที่ใช้อ้างอิงต้องเป็นฉบับที่เทียบเท่าหรือใหม่หว่ามาตราฐานเภสัชตำรับหนึ่ง ตามประกาศกระทรวงสาธารณสุข เรื่อง ระบุตำรายา พ.ศ. 2561  ลงวันที่ 6 ธันวาคม 2561)</p>
            <?php endif; ?>

            <div class="signature">
                <table>
                    <tr>
                        <?= $signatureCell($committee[0]) ?>
                        <?= $signatureCell($committee[1]) ?>
                        <?= $signatureCell($committee[2]) ?>
                    </tr>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if ($finishedProductNeedsOwnPage): ?>
        <div class="page detail-page">
            <div class="center word-inline-page-number" style="page-break-before: always; break-before: page;">- <?= $pageNo++ ?> -</div>
            <div class="section-title">2) คุณสมบัติทางเทคนิคของ Finished product</div>
            <p>ผลการวิเคราะห์คุณภาพเป็นไปตาม Finished product Specification และ Drug substance Specification ซึ่งได้จดทะเบียนต่อสำนักงานคณะกรรมการอาหารและยา กระทรวงสาธารณสุข (เภสัชตำรับที่ใช้อ้างอิงต้องเป็นฉบับที่เทียบเท่าหรือใหม่หว่ามาตราฐานเภสัชตำรับหนึ่ง ตามประกาศกระทรวงสาธารณสุข เรื่อง ระบุตำรายา พ.ศ. 2561  ลงวันที่ 6 ธันวาคม 2561)</p>

            <div class="signature">
                <table>
                    <tr>
                        <?= $signatureCell($committee[0]) ?>
                        <?= $signatureCell($committee[1]) ?>
                        <?= $signatureCell($committee[2]) ?>
                    </tr>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <div class="page compact-page">
            <div class="center word-inline-page-number" style="page-break-before: always; break-before: page;">- <?= $pageNo++ ?> -</div>
            <div class="tor-continuous-flow" <?= $torFlowAttributes ?>>
            <div class="section-title">3) เงื่อนไขเพิ่มเติม</div>
            <p class="subindent" <?= $torFlowAttributes ?>>1. ยาที่เสนอต้องได้รับอนุญาตขึ้นทะเบียนตำรับยาเพื่อจำหน่ายในประเทศไทยและต้องมีสำเนาใบสำคัญการขึ้นทะเบียนยาที่เสนอ (ใบสำคัญการขึ้นทะเบียนตำรับยา ได้แก่ ย.2, ย.3, ย.4 หรือ ย.1 แล้วแต่กรณี)</p>
            <p class="subindent" <?= $torFlowAttributes ?>>2. ยาที่ผลิตในประเทศไทย ผู้ผลิตต้องมีหนังสือรับรองมาตราฐานการผลิตยาตามหลักเกณฑ์วิธีการที่ดีในการผลิตยาของกระทรวงสาธารณสุข (GMP) ในหมวดยาที่เสนอขาย ในกรณีที่เป็นยานำเข้าจากต่างประเทศ ผู้ผลิตต้องมีหนังสือรับรองมาตราฐานการผลิตยาตามหลักเกณฑ์วิธีการที่ดีในการผลิตยาของประเทศผู้ผลิต</p>
            <p class="subindent" <?= $torFlowAttributes ?>>3. มีหนังสือรับรองการตรวจวิเคราะห์ยารุ่นที่นำมาเสนอขายจากผู้ผลิตหรือจากหน่วยงานที่กระทรวงสาธารณสุขรับรอง</p>
            <p class="subindent" <?= $torFlowAttributes ?>>4.ยาที่นำเสนอเป็นยาที่บริษัทเป็นผู้ผลิตหรือเป็นผู้แทนจำหน่ายโดยตรง</p>
            <p class="subindent" <?= $torFlowAttributes ?>>5.ยาที่ส่งมอบต้องมีอายุใช้ได้ไม่น้อยกว่า 1 ปี นับจากวันส่งมอบ</p>
            <p class="subindent" <?= $torFlowAttributes ?>>6.ผู้เสนอราคา(ผู้ขาย) ต้องรับเปลี่ยนยาเมื่อใกล้หมดอายุ หรือเมื่อเกิดการเสื่อมสภาพด้วยประการใดๆ ก่อนกำหนด โดยไม่มีเงื่อนไข</p>

            <div class="section-title">4) เงื่อนไขอื่น ๆ</div>
            <p class="subindent" <?= $torFlowAttributes ?>>4.1 ในการเสนอราคาให้เสนอราคาเป็นเงินบาท และเสนอราคาได้เพียงครั้งเดียวและราคาเดียว โดยเสนอต่อรายการ</p>
            <p class="subindent" <?= $torFlowAttributes ?>>4.2 ในการเสนอราคา กำหนดยืนราคาภายใน 30 วัน นับตั้งแต่วันยื่นเสนอราคาโดยภายในกำหนดยืนราคา ผู้เสนอราคาต้องรับผิดชอบราคาที่ตนเสนอไว้ และจะถอนการเสนอราคามิได้</p>
            <p class="subindent" <?= $torFlowAttributes ?>>4.3 กรณีพัสดุที่จะซื้อจัดจ้างหรือส่งมอบในการจัดจ้างครั้งนี้ หากแม้ผู้ขาย/คู่สัญญาเป็นผู้ประกอบการวิสาหกิจขนาดกลางหรือขนาดย่อม (SMEs) ให้ผู้ขาย-คู่สัญญาแสดงหรือการขึ้นทะเบียนฯ ด้วย (ถ้ามี)</p>
            
            <div class="section-title">5) ระยะเวลาส่งมอบของ</div>
            <p <?= $torFlowAttributes ?>>ผู้ขายต้องส่งมอบของ ภายใน <span class="red">30 วันทำการ</span> นับจากวันที่ได้รับใบสั่งซื้อ</p>

            <div class="section-title">6) เกณฑ์ในการพิจารณาผลการยื่นข้อเสนอ</div>
            <p <?= $torFlowAttributes ?>>ในการพิจารณาผลการยื่นข้อเสนอครั้งนี้ โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง จะพิจารณาตัดสินโดยใช้ แนวทางปฏิบัติตามหนังสือคณะกรรมการวินิจฉัยปัญหา จัดซื้อจัดจ้างและการบริหารพัสดุภาครัฐ กรมบัญชีกลาง ที่ กค (กวจ) 0405.2/ว 119 ลงวันที่ 12 มีนาคม 2562 ข้อ 3.1.1 ให้หน่วยงานของรัฐจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มิใช่ยาซึ่งองค์การเภสัชกรรมหรือสภากาชาดไทยได้ผลิตออกจำหน่ายแล้ว โดยวิธีเฉพาะเจาะจงจากองค์การเภสัชกรรมหรือสภากาชาดไทย เว้นแต่หน่วยงานในสังกัดกระทรวงกลาโหมให้จัดซื้อจากโรงงานเภสัชกรรมทหารสำหรับหน่วยงานในสังกัดสำนักงานตำรวจแห่งชาติจะซื้อจากองค์การเภสัชกรรมหรือโรงงานเภสัชกรรมทหารก็ได้ ทั้งนี้ หน่วยงานของรัฐสามารถจัดซื้อยาที่อยู่ในบัญชียาหลักแห่งชาติหรือเวชภัณฑ์ที่มีใช่ยาจากผู้ขายรายอื่นได้หากองค์การเภสัชกรรม สภากาชาดไทย หรือโรงงานเภสัชกรรมทหาร ไม่สามารถผลิตออกจำหน่ายให้แก่หน่วยงานของรัฐได้ทันกำหนดตามที่หน่วยงาน ของรัฐได้แจ้งแผนประจำปีล่วงหน้า</p>
            </div>

            <div class="signature">
                <table>
                    <tr>
                        <?= $signatureCell($committee[0]) ?>
                        <?= $signatureCell($committee[1]) ?>
                        <?= $signatureCell($committee[2]) ?>
                    </tr>
                </table>
            </div>
        </div>

        <?php
        // Paginate items: show up to 8 items per page. If a page is full (i.e. not the last chunk),
        // render signature at bottom so the next section/header starts on a new page.
        $itemChunks = array_chunk($report['items'], 8);
        $chunkCount = count($itemChunks);
        foreach ($itemChunks as $chunkIndex => $chunkItems):
            $isLastChunk = ($chunkIndex === $chunkCount - 1);
        ?>
        <div class="page <?= $isLastChunk ? 'last-page' : '' ?>">
            <div class="center word-inline-page-number" style="page-break-before: always; break-before: page;">- <?= $pageNo++ ?> -</div>
            <?php if ($chunkIndex === 0): ?>
                <div class="section-title">7) ราคากลางและแหล่งที่มาของราคากลาง</div>
                <p class="price-note">ราคากลาง จำนวน <?= money($offerTotal) ?> บาท โดยมีแหล่งที่มาของราคากลาง ดังนี้</p>
                <p><?= nl2br(h($report['price_source'])) ?></p>
            <?php else: ?>
                <!-- continuation page: do not repeat section title, only table header -->
            <?php endif; ?>
            <table class="price-table">
                <thead>
                <tr>
                    <th style="width: 6%"><?= $hasPackageItems ? 'ที่' : 'ลำดับ' ?></th>
                    <th>รายการยา</th>
                    <th style="width: 12%">ราคากลาง</th>
                    <th style="width: 12%">ราคาเสนอ</th>
                    <th style="width: <?= $hasPackageItems ? '10%' : '11%' ?>">จำนวน</th>
                    <th style="width: <?= $hasPackageItems ? '21%' : '11%' ?>"><?= $hasPackageItems ? 'ขนาดบรรจุ/หน่วยนับ' : 'หน่วยนับ' ?></th>
                    <th style="width: 15%"><?= $hasPackageItems ? 'มูลค่า' : 'มูลค่ารวม' ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($chunkItems as $j => $item):
                    $i = $chunkIndex * 8 + $j;
                ?>
                <tr>
                    <td class="center"><?= $i + 1 ?></td>
                    <td><?= h($item['medicine']['name']) ?></td>
                    <td class="right"><?= money($item['reference_price']) ?></td>
                    <td class="right"><?= money($item['offer_price']) ?></td>
                    <td class="right"><?= h(quantityNumber($item['quantity'])) ?></td>
                    <td class="center">
                        <?php if (($item['package_size'] ?? 0) > 0): ?>
                            <?= h(quantityNumber($item['package_size'])) ?> <?= h($item['medicine']['unit']) ?>/กล่อง
                        <?php else: ?>
                            <?= h($item['medicine']['unit']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="right"><?= money($item['offer_total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($isLastChunk): ?>
                <tr>
                    <td colspan="6" class="center">รวมราคาเสนอทั้งสิ้น</td>
                    <td class="right"><?= money($offerTotal) ?></td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if (! $isLastChunk): ?>
                <div class="signature">
                    <table>
                        <tr>
                            <?= $signatureCell($committee[0]) ?>
                            <?= $signatureCell($committee[1]) ?>
                            <?= $signatureCell($committee[2]) ?>
                        </tr>
                    </table>
                </div>
            <?php else: ?>
                <!-- Last chunk: render remaining sections inline so they continue on this page if space allows -->
                <div class="section-title">8) วงเงินงบประมาณ/วงเงินที่ได้รับจัดสรร</div>
                <p>วงเงินงบประมาณราคากลางรวม จำนวน <?= money($offerTotal) ?> บาท ( <?= h(thaiBahtText($offerTotal)) ?> ) โดยเบิกจ่ายจากงบประมาณเงินบำรุงของศูนย์สาธารณสุขและการแพทย์ตำบลทรายมูล วงเงินงบประมาณทั้งสิ้น 2,000,000.00 บาท ( <?= h(thaiBahtText(2000000)) ?> )</p>

                <div class="section-title">9) เงื่อนไขการชำระเงิน</div>
                <p>ผู้ซื้อตกลงชำระเงินให้แก่ผู้ขาย จำนวน 1 งวด เมื่อผู้ซื้อได้รับมอบสิ่งของและคณะกรรมการตรวจรับพัสดุได้ตรวจรับสิ่งของที่ส่งมอบถูกต้องครบถ้วนตามสัญญาแล้ว</p>

                <div class="section-title">10) อัตราค่าปรับ</div>
                <p>กรณีส่งมอบเกินกำหนด คิดค่าปรับเป็นรายวัน ในอัตราร้อยละ 0.20 ของราคาสิ่งของที่ยังไม่ได้รับมอบ</p>

                <div class="section-title">11) ผู้รับผิดชอบงาน/โครงการ</div>
                <p>โรงพยาบาลส่งเสริมสุขภาพตำบลบ้านสันโค้ง เลขที่3/1 หมู่ 2 ตำบลทรายมูล อำเภอสันกำแพง จังหวัดเชียงใหม่ 50130</p>

                <div class="signature">
                    <table>
                        <tr>
                            <?= $signatureCell($committee[0]) ?>
                            <?= $signatureCell($committee[1]) ?>
                            <?= $signatureCell($committee[2]) ?>
                        </tr>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <script>
            window.addEventListener('load', () => {
                setTimeout(() => window.print(), 150);
            });
        </script>
    </body>
    </html>
    <?php
    return thaiVisibleDigits((string) ob_get_clean());
}

function outputBrowserPrint(string $html): void
{
    echo $html;
    exit;
}

$error = '';
$packageValidationError = '';
$data = ['medicines' => [], 'drafts' => [], 'committee_sets' => []];

try {
    $data = loadData();
} catch (Throwable $e) {
    $error = 'ยังเชื่อมต่อฐานข้อมูลไม่ได้ กรุณาสร้างฐานข้อมูลด้วย schema.sql และตรวจค่าใน config.php';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !$error) {
    $action = post('action');
    if ($action === 'save_committee_set') {
        $title = post('committee_set_title');
        if ($title !== '') {
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
                    member2_position = VALUES(member2_position)
            ");
            $stmt->execute([
                $title,
                post('chair_name'),
                post('chair_position'),
                post('member1_name'),
                post('member1_position'),
                post('member2_name'),
                post('member2_position'),
            ]);
        }
        header('Location: tor_company_purchase.php');
        exit;
    }

    if ($action === 'save_draft') {
        $title = post('draft_title');
        $content = post('price_source');
        if ($title !== '' && $content !== '') {
            $stmt = db()->prepare('INSERT INTO price_source_drafts (title, content) VALUES (?, ?)');
            $stmt->execute([$title, $content]);
        }
        header('Location: tor_company_purchase.php');
        exit;
    }

    if ($action === 'save_report') {
        $caseId = (int) post('case_id');
        $items = reportItems($data['medicines'], $data['procurement_cases']);
        if ($packageValidationError !== '') {
            $error = $packageValidationError;
        } elseif (!$items) {
            $error = 'กรุณาเลือกยาอย่างน้อย 1 รายการ';
        } else {
            $committee = [post('committee_1'), post('committee_2'), post('committee_3')];
            $priceSource = post('price_source');
            $reportId = (int) post('report_id');
            if ($reportId > 0) {
                updateTorHistory($reportId, $committee, $items, $priceSource, $caseId);
                $historyId = $reportId;
            } else {
                $historyId = saveTorHistory($committee, $items, $priceSource, $caseId);
            }
            if ($caseId > 0) {
                saveProcurementDocument($caseId, 'tor_company_purchase', '', '', [
                    'history_id' => $historyId,
                ]);
            }
        }
        if ($error === '') {
            header('Location: tor_company_purchase.php' . ($caseId > 0 ? '?case_id=' . $caseId : ''));
            exit;
        }
    }

    if ($action === 'report') {
        $items = reportItems($data['medicines'], $data['procurement_cases']);
        if ($packageValidationError !== '') {
            $error = $packageValidationError;
        } elseif (!$items) {
            $error = 'กรุณาเลือกยาอย่างน้อย 1 รายการ';
        } else {
            $committee = [post('committee_1'), post('committee_2'), post('committee_3')];
            $priceSource = post('price_source');
            outputBrowserPrint(thaiDocxVisibleHtml(reportHtml([
                'committee' => $committee,
                'items' => $items,
                'price_source' => $priceSource,
                'fiscal_year' => torFiscalYearForCase($data['procurement_cases'], (int) post('case_id')),
            ])));
        }
    }

    if ($action === 'word') {
        $items = reportItems($data['medicines'], $data['procurement_cases']);
        if ($packageValidationError !== '') {
            $error = $packageValidationError;
        } elseif (!$items) {
            $error = 'กรุณาเลือกยาอย่างน้อย 1 รายการ';
        } else {
            $committee = [post('committee_1'), post('committee_2'), post('committee_3')];
            $priceSource = post('price_source');
            $caseId = (int) post('case_id');
            $torWordHtml = reportHtml([
                'committee' => $committee,
                'items' => $items,
                'price_source' => $priceSource,
                'fiscal_year' => torFiscalYearForCase($data['procurement_cases'], $caseId),
            ]);
            try {
                outputWordHtml($torWordHtml, torCompanyPurchaseWordFilename($caseId, $items), '', 'tor');
            } catch (Throwable $wordError) {
                error_log('TOR DOCX export failed: ' . $wordError->getMessage());
                $error = 'สร้างไฟล์ Word ไม่สำเร็จ กรุณาตรวจสอบว่าอัปโหลด core/word_export_lib.php และ assets/fonts ครบ และ PHP เปิดใช้งาน ZipArchive';
            }
        }
    }
}

$draftId = (int) ($_POST['draft_id'] ?? $_GET['draft_id'] ?? 0);
$selectedCaseId = (int) ($_POST['case_id'] ?? $_GET['case_id'] ?? 0);
$draft = selectedDraft($data['drafts'], $draftId);
$postedMedicineIds = $_POST['medicine_id'] ?? [];
$postedQty = $_POST['quantity'] ?? [];
$postedRequestedQty = $_POST['requested_quantity'] ?? [];
$postedPackageSize = $_POST['package_size'] ?? [];
$postedPackageMode = ($_POST['package_mode'] ?? '') === '1';
$postedRef = $_POST['reference_price'] ?? [];
$postedOffer = $_POST['offer_price'] ?? [];

// Deduplicate histories by case_id + items_json signature so identical sets don't show multiple times
$uniqueHistories = [];
$seenHistorySign = [];
foreach ($data['histories'] as $h) {
    $casePart = isset($h['case_id']) && $h['case_id'] !== null ? (string) $h['case_id'] : '';
    $itemsJson = $h['items_json'] ?? '';
    $sig = $casePart . '|' . md5($itemsJson);
    if (isset($seenHistorySign[$sig])) continue;
    $seenHistorySign[$sig] = true;
    $uniqueHistories[] = $h;
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ระบบออกรายงาน TOR จัดซื้อยา</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100">
<?php require __DIR__ . '/components/navbar.php'; ?>
<?php require __DIR__ . '/components/workflow_steps.php'; ?>
<main class="mx-auto grid max-w-7xl gap-6 px-4 py-6">
    <section class="rounded-lg border border-zinc-800 bg-zinc-900/80 p-5">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3 border-b border-zinc-800 pb-4">
            <div>
                <p class="text-sm text-emerald-300">จัดซื้อผ่านบริษัทยา</p>
                <h1 class="mt-1 text-2xl font-semibold">ระบบออกรายงาน TOR จัดซื้อยา</h1>
                <p class="mt-2 text-sm text-zinc-400">เลือกยาได้หลายรายการใน TOR เดียว ระบบดึงคุณสมบัติจากฐานข้อมูลและคำนวณราคาให้</p>
            </div>
            <a href="admin.php" class="rounded-md border border-zinc-700 px-4 py-2 text-sm hover:border-emerald-400">จัดการข้อมูลยา</a>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 rounded-md border border-red-500/40 bg-red-950/60 p-3 text-sm text-red-100"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="space-y-5" autocomplete="off">
            <input type="hidden" name="action" value="report">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="report_id" id="report_id" value="">
            <input type="hidden" name="case_id" id="case_id" value="<?= (int) $selectedCaseId ?>">
            <input type="hidden" name="package_mode" id="package_mode" value="<?= $postedPackageMode ? '1' : '0' ?>">

            <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                <div class="mb-3">
                    <label class="block">
                        <span class="text-sm text-zinc-300">เลือกชุดเอกสาร</span>
                        <select id="procurement_case_id" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                            <option value="">เลือกชุดที่ต้องการ</option>
                            <?php foreach ($data['procurement_cases'] as $case): ?>
                                <?php
                                $caseItems = @json_decode($case['items_json'] ?? '[]', true) ?: [];
                                $caseMemo = trim((string) ($case['principle_memo_no_suffix'] ?? ''));
                                $caseDate = trim((string) ($case['principle_doc_date'] ?? ''));
                                $caseLabel = 'หนังสือเลขที่ ' . $caseMemo . ' ลงวันที่ ' . $caseDate . ' ซื้อยาจำนวน ' . thaiDigits((string) count($caseItems)) . ' รายการ';
                                ?>
                                <option value="<?= (int) $case['id'] ?>" <?= (int) $case['id'] === $selectedCaseId ? 'selected' : '' ?>><?= h($caseLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p id="case_load_status" class="mt-2 text-xs text-zinc-500"></p>
                </div>
                <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                    <label class="block">
                        <span class="text-sm text-zinc-300">ชุดกรรมการที่บันทึกไว้</span>
                        <select id="committee_set_id" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                            <option value="">เลือกชุดกรรมการ</option>
                            <?php foreach ($data['committee_sets'] as $set): ?>
                                <option value="<?= (int) $set['id'] ?>"><?= h($set['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" id="apply_committee_set" class="self-end rounded-md border border-emerald-500/60 px-4 py-2 text-emerald-200 hover:bg-emerald-500/10">ใช้ชุดนี้</button>
                </div>
                
                <div class="mt-3">
                    <button type="button" id="toggle_committee_set_form" class="text-sm text-zinc-400 hover:text-emerald-300">บันทึก/แก้ไขชุดกรรมการ</button>
                </div>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                    <label class="block">
                        <span class="text-sm text-zinc-300">กรรมการคนที่ <?= $i ?></span>
                        <textarea name="committee_<?= $i ?>" rows="3" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400"><?= h($_POST['committee_' . $i] ?? '') ?></textarea>
                    </label>
                <?php endfor; ?>
            </div>

            <div id="committee_set_panel" class="hidden rounded-md border border-zinc-800 bg-zinc-950 p-4">
                <div class="mb-3 text-sm font-medium text-zinc-200">บันทึกชุดกรรมการใหม่/อัปเดตชุดเดิม</div>
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="block md:col-span-3">
                        <span class="text-sm text-zinc-300">ชื่อชุดกรรมการ</span>
                        <input name="committee_set_title" form="committee_set_form" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">ประธานกรรมการ - ชื่อ</span>
                        <input name="chair_name" form="committee_set_form" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">กรรมการ 1 - ชื่อ</span>
                        <input name="member1_name" form="committee_set_form" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">กรรมการ 2 - ชื่อ</span>
                        <input name="member2_name" form="committee_set_form" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">ตำแหน่ง</span>
                        <input name="chair_position" form="committee_set_form" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">ตำแหน่ง</span>
                        <input name="member1_position" form="committee_set_form" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">ตำแหน่ง</span>
                        <input name="member2_position" form="committee_set_form" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                </div>
                <button type="submit" form="committee_set_form" class="mt-3 rounded-md border border-zinc-700 px-4 py-2 text-zinc-100 hover:border-emerald-400">บันทึกชุดกรรมการ</button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-medium text-zinc-100">รายการยาและจำนวนที่จัดซื้อ</div>
                    <div class="mt-1 text-xs text-zinc-500">จำนวนตั้งต้นดึงจากเอกสารอนุมัติหลักการ และต้องหารด้วยขนาดบรรจุลงตัวเท่านั้น</div>
                </div>
                <button type="button" id="toggle_package_mode" aria-pressed="<?= $postedPackageMode ? 'true' : 'false' ?>" class="rounded-md border border-sky-500/60 px-4 py-2 text-sm text-sky-200 hover:bg-sky-500/10">ปรับขนาดบรรจุ</button>
            </div>

            <div id="medicine_table_wrap" class="overflow-x-auto rounded-md border border-zinc-800">
                <table class="w-full min-w-[1020px] text-sm">
                    <thead class="bg-zinc-950 text-zinc-300">
                    <tr>
                        <th class="w-12 px-3 py-2 text-right">#</th>
                        <th class="px-3 py-2 text-left">ชื่อยา</th>
                        <th id="quantity_heading" class="w-64 px-3 py-2 text-right">จำนวน</th>
                        <th id="reference_price_heading" class="w-36 px-3 py-2 text-right">ราคากลาง/หน่วยยา</th>
                        <th id="offer_price_heading" class="w-36 px-3 py-2 text-right">ราคาเสนอ/หน่วยยา</th>
                        <th class="w-12 px-3 py-2"></th>
                        <th class="w-36 px-3 py-2 text-right">รวมราคาเสนอ</th>
                    </tr>
                    </thead>
                    <tbody id="medicine_rows" class="divide-y divide-zinc-800">
                    <?php
                    $medicineMap = mapById($data['medicines']);
                    $rowCount = max(count((array) $postedMedicineIds), 1);
                    for ($i = 0; $i < $rowCount; $i++):
                        $selectedMedicine = $medicineMap[(int) ($postedMedicineIds[$i] ?? 0)] ?? null;
                    ?>
                        <tr>
                            <td class="px-3 py-2 text-right text-zinc-400"><?= $i + 1 ?></td>
                            <td class="px-3 py-2">
                                <div class="medicine-picker relative">
                                    <input type="hidden" name="medicine_id[]" class="medicine-select" value="<?= (int) ($postedMedicineIds[$i] ?? 0) ?: '' ?>">
                                    <input type="text" class="medicine-search w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 outline-none focus:border-emerald-400" value="<?= h($selectedMedicine['name'] ?? '') ?>" placeholder="พิมพ์ชื่อยาอย่างน้อย 3 ตัว">
                                    <div class="medicine-results absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-64 overflow-auto rounded-md border border-zinc-700 bg-zinc-950 shadow-xl"></div>
                                </div>
                            </td>
                            <td class="quantity-cell px-3 py-2">
                                <?php $initialRequestedQty = normalizeNumberInput($postedRequestedQty[$i] ?? $postedQty[$i] ?? ''); ?>
                                <input name="quantity[]" type="number" min="0" step="0.01" value="<?= h(normalizeNumberInput($postedQty[$i] ?? '')) ?>" class="calc-input quantity normal-quantity w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-emerald-400">
                                <input name="requested_quantity[]" type="hidden" value="<?= h($initialRequestedQty) ?>" class="requested-quantity">
                                <div class="package-fields hidden grid grid-cols-2 gap-2">
                                    <label class="block"><span class="mb-1 block text-[11px] text-zinc-500">จำนวนกล่อง</span><input type="number" tabindex="-1" readonly class="package-count w-full rounded-md border border-zinc-700 bg-zinc-900 px-2 py-2 text-right"></label>
                                    <label class="block"><span class="mb-1 block text-[11px] text-zinc-500">ขนาดบรรจุ/กล่อง</span><input name="package_size[]" type="number" min="0.01" step="0.01" value="<?= h(normalizeNumberInput($postedPackageSize[$i] ?? '')) ?>" class="package-size w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-sky-400"></label>
                                    <p class="package-message col-span-2 hidden rounded px-2 py-1 text-xs"></p>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex gap-1">
                                    <input name="reference_price[]" type="number" step="0.01" value="<?= h(normalizeNumberInput($postedRef[$i] ?? '')) ?>" class="calc-input reference min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-emerald-400">
                                    <button type="button" class="price-search-btn rounded-md border border-zinc-700 px-2 py-2 text-zinc-200 hover:border-emerald-400" title="ค้นหาราคายาอ้างอิง">🔍</button>
                                </div>
                            </td>
                            <td class="px-3 py-2"><input name="offer_price[]" type="number" step="0.01" value="<?= h(normalizeNumberInput($postedOffer[$i] ?? '')) ?>" class="calc-input offer w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-emerald-400"></td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" class="remove-row-btn rounded-md border border-red-700 px-2 py-1 text-red-300 hover:bg-red-700/10">✕</button>
                            </td>
                            <td class="row-total px-3 py-2 text-right font-medium">0.00</td>
                        </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" id="add_row" class="mt-3 rounded-md border border-emerald-500/60 px-4 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10">เพิ่มรายการยา</button>

            <!-- Removed the separate "คุณสมบัติยาที่เลือก" panel per request -->

            <div class="rounded-md border border-zinc-800 bg-zinc-950 p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-zinc-800 pb-3">
                    <div>
                        <div class="text-sm font-medium text-zinc-100">ข้อ 7 ราคากลางและแหล่งที่มาของราคากลาง</div>
                        <div class="mt-1 text-xs text-zinc-500">เลือกดราฟเดิม หรือแก้ไขข้อความแล้วบันทึกเป็นดราฟใหม่</div>
                    </div>
                    <button name="action" value="save_draft" class="rounded-md border border-emerald-500/60 px-4 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10">บันทึกข้อความข้อ 7 เป็นดราฟ</button>
                </div>
                <div class="grid gap-3 md:grid-cols-[minmax(220px,1fr)_minmax(220px,1fr)]">
                    <label class="block">
                        <span class="text-sm text-zinc-300">ใช้ดราฟข้อ 7</span>
                        <select id="draft_id" name="draft_id" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                            <option value="">ไม่ใช้ดราฟ</option>
                            <?php foreach ($data['drafts'] as $item): ?>
                                <option value="<?= (int) $item['id'] ?>" <?= $draftId === (int) $item['id'] ? 'selected' : '' ?>><?= h($item['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">ชื่อดราฟใหม่</span>
                        <input name="draft_title" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block md:col-span-2">
                        <span class="text-sm text-zinc-300">ข้อความข้อ 7</span>
                        <textarea id="price_source" name="price_source" rows="5" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400"><?= h($_POST['price_source'] ?? ($draft['content'] ?? '')) ?></textarea>
                    </label>
                </div>
            </div>

            <div class="grid gap-3 rounded-md border border-zinc-800 bg-zinc-950 p-4 md:grid-cols-2">
                <div><div class="text-sm text-zinc-400">รวมราคากลาง</div><div id="reference_total" class="text-xl font-semibold">0.00 บาท</div></div>
                <div><div class="text-sm text-zinc-400">รวมราคาเสนอ</div><div id="offer_total" class="text-xl font-semibold">0.00 บาท</div></div>
            </div>

            <div class="app-action-bar flex flex-wrap gap-3">
                <button class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">ประมวลผลรายงาน</button>
                <button type="submit" name="action" value="word" class="rounded-md border border-sky-500/60 px-4 py-2 text-sm text-sky-200 hover:bg-sky-500/10">ดาวน์โหลด Word</button>
                <button name="action" value="save_report" class="rounded-md border border-emerald-500/60 px-4 py-2 text-sm text-emerald-200 hover:bg-emerald-500/10">บันทึก/อัปเดตรายงาน</button>
            </div>
        </form>
        <form id="committee_set_form" method="post" class="hidden">
            <input type="hidden" name="action" value="save_committee_set">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        </form>
    </section>

    <aside class="hidden">
        <section class="rounded-lg border border-zinc-800 bg-zinc-900/80 p-5">
            <h2 class="text-lg font-semibold">ฐานข้อมูลยา</h2>
            <p class="mt-1 text-sm text-zinc-400">มีข้อมูลตัวอย่าง 10 รายการ แก้ไขเพิ่มได้ที่ตาราง medicines ใน phpMyAdmin</p>
            <div class="mt-4 max-h-[520px] space-y-3 overflow-y-auto pr-2">
                <?php foreach ($data['medicines'] as $medicine): ?>
                    <div class="border-b border-zinc-800 pb-3">
                        <div class="font-medium"><?= h($medicine['name']) ?></div>
                        <div class="mt-1 whitespace-pre-line text-sm text-zinc-400"><?= h($medicine['specification']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </aside>
</main>
<div id="price_reference_modal" class="fixed inset-0 z-50 hidden bg-black/70 p-4">
    <div class="mx-auto mt-12 max-w-3xl rounded-lg border border-zinc-800 bg-zinc-950 p-5 shadow-xl">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">ค้นหาราคายาอ้างอิง</h2>
            <button type="button" id="close_price_reference_modal" class="rounded-md border border-zinc-700 px-3 py-1 text-sm hover:border-emerald-400">ปิด</button>
        </div>
        <input id="price_reference_query" class="mt-4 w-full rounded-md border border-zinc-700 bg-zinc-900 px-3 py-2 outline-none focus:border-emerald-400" placeholder="พิมพ์ชื่อยา รูปแบบยา ความแรง หรือหน่วย">
        <div id="price_reference_status" class="mt-2 text-sm text-zinc-400">พิมพ์คำค้นหาเพื่อค้นหาราคากลางอ้างอิง</div>
        <div class="mt-3 max-h-[420px] overflow-auto rounded-md border border-zinc-800">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="sticky top-0 bg-zinc-950 text-zinc-300">
                <tr>
                    <th class="px-3 py-2 text-left">รายการยา</th>
                    <th class="px-3 py-2 text-left">รูปแบบยาและความแรง</th>
                    <th class="w-28 px-3 py-2 text-left">หน่วย</th>
                    <th class="w-32 px-3 py-2 text-right">ราคา</th>
                    <th class="w-24 px-3 py-2 text-right">เลือก</th>
                </tr>
                </thead>
                <tbody id="price_reference_results" class="divide-y divide-zinc-800"></tbody>
            </table>
        </div>
    </div>
</div>
<script>
const medicines = <?= json_encode(array_column($data['medicines'], null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const medicineList = Object.entries(medicines).map(([id, item]) => ({ id, ...item }));
const drafts = <?= json_encode(array_column($data['drafts'], null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const committeeSets = <?= json_encode(array_column($data['committee_sets'], null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const histories = <?= json_encode(array_column($uniqueHistories, null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const procurementCases = <?= json_encode(array_column($data['procurement_cases'], null, 'id'), JSON_UNESCAPED_UNICODE) ?>;
const baht = new Intl.NumberFormat('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
let activeReferenceInput = null;
let priceReferenceSearchTimer = null;
let packageMode = document.getElementById('package_mode')?.value === '1';

function isPageReload() {
    const navigation = performance.getEntriesByType?.('navigation')?.[0];
    return navigation ? navigation.type === 'reload' : performance.navigation?.type === 1;
}
function committeeText(name, position, role) {
    return [name, position, role].filter((part) => part && part.trim()).join("\n");
}
function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"]|'/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[char] || char);
}

function arabicDigits(value) {
    return String(value ?? '').replace(/[๐-๙]/g, (char) => '0123456789'['๐๑๒๓๔๕๖๗๘๙'.indexOf(char)] || char);
}
function numericInputValue(value) {
    return arabicDigits(value).replace(/[, ]/g, '').trim();
}
function setCaseLoadStatus(message, tone = 'zinc') {
    const target = document.getElementById('case_load_status');
    if (!target) return;
    target.textContent = message || '';
    target.className = `mt-2 text-xs ${tone === 'emerald' ? 'text-emerald-300' : 'text-zinc-500'}`;
}
function n(input) { return Number(numericInputValue(input.value) || 0); }
function quantityCellHtml() {
    return `
        <input name="quantity[]" type="number" min="0" step="0.01" class="calc-input quantity normal-quantity w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-emerald-400">
        <input name="requested_quantity[]" type="hidden" class="requested-quantity">
        <div class="package-fields hidden grid grid-cols-2 gap-2">
            <label class="block"><span class="mb-1 block text-[11px] text-zinc-500">จำนวนกล่อง</span><input type="number" tabindex="-1" readonly class="package-count w-full rounded-md border border-zinc-700 bg-zinc-900 px-2 py-2 text-right"></label>
            <label class="block"><span class="mb-1 block text-[11px] text-zinc-500">ขนาดบรรจุ/กล่อง</span><input name="package_size[]" type="number" min="0.01" step="0.01" class="package-size w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-sky-400"></label>
            <p class="package-message col-span-2 hidden rounded px-2 py-1 text-xs"></p>
        </div>`;
}
function packageSizeSuggestions(requested, entered) {
    if (!Number.isInteger(requested) || requested <= 0 || requested > 1000000) return [];
    const divisors = [];
    for (let divisor = 1; divisor * divisor <= requested; divisor++) {
        if (requested % divisor !== 0) continue;
        divisors.push(divisor);
        if (divisor !== requested / divisor) divisors.push(requested / divisor);
    }
    return divisors.sort((a, b) => Math.abs(a - entered) - Math.abs(b - entered)).slice(0, 3);
}
function calculatePackageRow(row) {
    const quantity = row.querySelector('.quantity');
    const requestedInput = row.querySelector('.requested-quantity');
    const packageSizeInput = row.querySelector('.package-size');
    const packageCountInput = row.querySelector('.package-count');
    const message = row.querySelector('.package-message');
    if (!quantity || !requestedInput || !packageSizeInput || !packageCountInput || !message) return true;

    if (!packageMode) {
        quantity.classList.remove('hidden');
        row.querySelector('.package-fields')?.classList.add('hidden');
        message.classList.add('hidden');
        row.classList.remove('bg-amber-500/5');
        return true;
    }

    if (!numericInputValue(requestedInput.value)) requestedInput.value = numericInputValue(quantity.value);
    const requested = n(requestedInput);
    const packageSize = n(packageSizeInput);
    quantity.classList.add('hidden');
    row.querySelector('.package-fields')?.classList.remove('hidden');
    message.classList.remove('hidden');

    if (requested <= 0 || packageSize <= 0) {
        quantity.value = '';
        packageCountInput.value = '';
        message.textContent = requested <= 0 ? 'ไม่พบจำนวนตั้งต้นจากเอกสารแรก' : 'กรอกขนาดบรรจุเพื่อคำนวณจำนวนกล่อง';
        message.className = 'package-message col-span-2 rounded bg-zinc-800 px-2 py-1 text-xs text-zinc-400';
        row.classList.toggle('bg-amber-500/5', requested <= 0);
        return false;
    }

    const quotient = requested / packageSize;
    const nearestInteger = Math.round(quotient);
    if (Math.abs(quotient - nearestInteger) > 1e-9) {
        quantity.value = '';
        packageCountInput.value = '';
        const suggestions = packageSizeSuggestions(requested, packageSize);
        message.textContent = `${requested} ÷ ${packageSize} หารไม่ลงตัว กรุณาเลือกขนาดบรรจุที่หารลงตัว`;
        suggestions.forEach((suggestion) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'package-suggestion ml-1 rounded border border-amber-500/40 px-1.5 py-0.5 font-medium hover:bg-amber-500/15';
            button.dataset.size = String(suggestion);
            button.textContent = `ใช้ ${suggestion}`;
            message.appendChild(button);
        });
        message.className = 'package-message col-span-2 rounded bg-amber-500/10 px-2 py-1 text-xs text-amber-700';
        row.classList.add('bg-amber-500/5');
        return false;
    }

    quantity.value = String(nearestInteger);
    packageCountInput.value = String(nearestInteger);
    message.textContent = `${requested} ÷ ${packageSize} = ${nearestInteger} กล่อง (หารลงตัว)`;
    message.className = 'package-message col-span-2 rounded bg-emerald-500/10 px-2 py-1 text-xs text-emerald-700';
    row.classList.remove('bg-amber-500/5');
    return true;
}
function setPackageMode(enabled) {
    packageMode = Boolean(enabled);
    const modeInput = document.getElementById('package_mode');
    const button = document.getElementById('toggle_package_mode');
    const heading = document.getElementById('quantity_heading');
    const referenceHeading = document.getElementById('reference_price_heading');
    const offerHeading = document.getElementById('offer_price_heading');
    if (modeInput) modeInput.value = packageMode ? '1' : '0';
    if (button) {
        button.setAttribute('aria-pressed', packageMode ? 'true' : 'false');
        button.textContent = packageMode ? 'ยกเลิกปรับขนาดบรรจุ' : 'ปรับขนาดบรรจุ';
        button.classList.toggle('bg-sky-500/15', packageMode);
    }
    if (heading) heading.textContent = packageMode ? 'จำนวนกล่อง / ขนาดบรรจุ' : 'จำนวน';
    if (referenceHeading) referenceHeading.textContent = packageMode ? 'ราคากลาง/แพ็ค' : 'ราคากลาง/หน่วยยา';
    if (offerHeading) offerHeading.textContent = packageMode ? 'ราคาเสนอ/แพ็ค' : 'ราคาเสนอ/หน่วยยา';
    document.querySelectorAll('#medicine_rows tr').forEach((row) => {
        if (!packageMode) {
            const requested = row.querySelector('.requested-quantity');
            const quantity = row.querySelector('.quantity');
            if (requested && quantity && numericInputValue(requested.value)) quantity.value = numericInputValue(requested.value);
        }
        calculatePackageRow(row);
    });
    updatePage();
}
function priceReferenceQueryFromMedicine(medicine, typedName = '') {
    const raw = `${medicine?.name || typedName || ''} ${medicine?.unit || ''}`;
    const stopWords = new Set([
        'tab', 'tabs', 'tablet', 'tablets', 'cap', 'caps', 'capsule', 'capsules',
        'inj', 'injection', 'syrup', 'solution', 'cream', 'ointment',
        'ยา', 'เม็ด', 'แคปซูล', 'ขวด', 'หลอด', 'amp', 'vial',
        'mg', 'mcg', 'g', 'ml', 'iu', 'meq'
    ]);
    const tokens = (arabicDigits(raw).toLocaleLowerCase('th-TH').match(/[\p{L}\p{N}.%]+/gu) || [])
        .map((token) => token.replace(/^\.+|\.+$/g, ''))
        .filter((token) => token.length >= 2 && !stopWords.has(token));
    return Array.from(new Set(tokens)).slice(0, 4).join(' ') || raw.trim();
}
function updateMedicineDropdownSpace() {
    const wrap = document.getElementById('medicine_table_wrap');
    if (!wrap) return;
    const isOpen = Array.from(document.querySelectorAll('.medicine-results')).some((results) => !results.classList.contains('hidden'));
    wrap.style.paddingBottom = isOpen ? '18rem' : '';
}
function hideMedicineResults(row) {
    const results = row.querySelector('.medicine-results');
    if (results) {
        results.classList.add('hidden');
        results.innerHTML = '';
    }
    updateMedicineDropdownSpace();
}
function selectMedicine(row, medicine) {
    row.querySelector('.medicine-select').value = medicine.id;
    row.querySelector('.medicine-search').value = medicine.name;
    hideMedicineResults(row);
    updatePage();
}
function medicineResultButtons(row) { return Array.from(row.querySelectorAll('.medicine-result')); }
function setActiveMedicineResult(row, index) {
    const buttons = medicineResultButtons(row);
    buttons.forEach((button, i) => {
        button.classList.toggle('bg-emerald-500/20', i === index);
        button.classList.toggle('text-emerald-100', i === index);
        if (i === index) button.scrollIntoView({ block: 'nearest' });
    });
    row.dataset.activeMedicineIndex = buttons.length ? String(index) : '-1';
}
function moveActiveMedicineResult(row, direction) {
    const buttons = medicineResultButtons(row);
    if (!buttons.length) return;
    const current = Number(row.dataset.activeMedicineIndex ?? -1);
    const next = current < 0 ? (direction > 0 ? 0 : buttons.length - 1) : (current + direction + buttons.length) % buttons.length;
    setActiveMedicineResult(row, next);
}
function selectActiveMedicineResult(row) {
    const buttons = medicineResultButtons(row);
    const activeIndex = Number(row.dataset.activeMedicineIndex ?? -1);
    const button = buttons[activeIndex] || buttons[0];
    const medicine = medicines[button?.dataset.id];
    if (medicine) selectMedicine(row, { id: button.dataset.id, ...medicine });
}
function handleMedicineSearchKeydown(row, event) {
    const results = row.querySelector('.medicine-results');
    const isOpen = results && !results.classList.contains('hidden');
    if (event.key === 'Enter') {
        event.preventDefault();
        if (isOpen) selectActiveMedicineResult(row);
        return;
    }
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (!isOpen) searchMedicines(row, event.target.value);
        moveActiveMedicineResult(row, 1);
        return;
    }
    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (!isOpen) searchMedicines(row, event.target.value);
        moveActiveMedicineResult(row, -1);
        return;
    }
    if (event.key === 'Escape') {
        hideMedicineResults(row);
    }
}
function searchMedicines(row, query) {
    const hidden = row.querySelector('.medicine-select');
    const input = row.querySelector('.medicine-search');
    const results = row.querySelector('.medicine-results');
    const term = arabicDigits(query.trim()).toLocaleLowerCase('th-TH');

    if (arabicDigits(medicines[hidden.value]?.name || '') !== arabicDigits(input.value)) {
        hidden.value = '';
    }
    if (term.length < 3) {
        hideMedicineResults(row);
        updatePage();
        return;
    }

    const matches = medicineList
        .filter((medicine) => {
            const haystack = arabicDigits(`${medicine.name || ''} ${medicine.specification || ''} ${medicine.unit || ''}`).toLocaleLowerCase('th-TH');
            return haystack.includes(term);
        })
        .slice(0, 12);

    if (!matches.length) {
        results.innerHTML = '<div class="px-3 py-2 text-sm text-zinc-400">ไม่พบรายการยาใกล้เคียง</div>';
        results.classList.remove('hidden');
        row.dataset.activeMedicineIndex = '-1';
        updateMedicineDropdownSpace();
        updatePage();
        return;
    }

    results.innerHTML = matches.map((medicine) => `
        <button type="button" class="medicine-result block w-full border-b border-zinc-800 px-3 py-2 text-left text-sm hover:bg-emerald-500/10" data-id="${escapeHtml(medicine.id)}">
            <span class="block text-zinc-100">${escapeHtml(medicine.name)}</span>
            <span class="block text-xs text-zinc-400">${escapeHtml(medicine.unit || '')}</span>
        </button>
    `).join('');
    results.classList.remove('hidden');
    setActiveMedicineResult(row, 0);
    updateMedicineDropdownSpace();
    updatePage();
}
function bindRow(row) {
    row.querySelectorAll('.calc-input').forEach((el) => el.addEventListener('input', updatePage));
    row.querySelector('.normal-quantity')?.addEventListener('input', (event) => {
        if (!packageMode) row.querySelector('.requested-quantity').value = numericInputValue(event.target.value);
    });
    row.querySelector('.package-size')?.addEventListener('input', () => {
        calculatePackageRow(row);
        updatePage();
    });
    row.querySelector('.package-message')?.addEventListener('click', (event) => {
        const suggestion = event.target.closest('.package-suggestion');
        if (!suggestion) return;
        row.querySelector('.package-size').value = suggestion.dataset.size || '';
        calculatePackageRow(row);
        updatePage();
    });
    row.querySelector('.medicine-search')?.addEventListener('input', (event) => searchMedicines(row, event.target.value));
    row.querySelector('.medicine-search')?.addEventListener('focus', (event) => searchMedicines(row, event.target.value));
    row.querySelector('.medicine-search')?.addEventListener('keydown', (event) => handleMedicineSearchKeydown(row, event));
    row.querySelector('.medicine-select')?.addEventListener('change', updatePage);
    row.querySelector('.medicine-results')?.addEventListener('click', (event) => {
        const button = event.target.closest('.medicine-result');
        if (!button) return;
        const medicine = medicines[button.dataset.id];
        if (medicine) selectMedicine(row, { id: button.dataset.id, ...medicine });
    });
    row.querySelector('.medicine-results')?.addEventListener('mouseover', (event) => {
        const button = event.target.closest('.medicine-result');
        if (!button) return;
        setActiveMedicineResult(row, medicineResultButtons(row).indexOf(button));
    });
    row.querySelector('.price-search-btn')?.addEventListener('click', () => openPriceReferenceModal(row));
    row.querySelector('.remove-row-btn')?.addEventListener('click', () => {
        const tr = row;
        tr.remove();
        renumberRows();
        updatePage();
    });
}
function renumberRows() {
    document.querySelectorAll('#medicine_rows tr').forEach((row, index) => {
        row.querySelector('td').textContent = index + 1;
    });
}
function updatePage() {
    let refTotal = 0, offerTotal = 0;
    const specLines = [];
    document.querySelectorAll('#medicine_rows tr').forEach((row) => {
        if (packageMode) calculatePackageRow(row);
        const qty = n(row.querySelector('.quantity'));
        const ref = n(row.querySelector('.reference'));
        const offer = n(row.querySelector('.offer'));
        const med = medicines[row.querySelector('.medicine-select').value];
        const pricedQty = qty;
        refTotal += pricedQty * ref;
        offerTotal += pricedQty * offer;
        row.querySelector('.row-total').textContent = baht.format(pricedQty * offer);
        if (med) specLines.push(med.name + "\n" + med.specification);
    });
    document.getElementById('reference_total').textContent = baht.format(refTotal) + ' บาท';
    document.getElementById('offer_total').textContent = baht.format(offerTotal) + ' บาท';
    const specsEl = document.getElementById('medicine_specs');
    if (specsEl) specsEl.textContent = specLines.length ? specLines.join("\n\n") : 'เลือกชื่อยาเพื่อแสดงคุณสมบัติ';
}
function clearMainFormForReload() {
    if (new URLSearchParams(window.location.search).has('case_id')) return;
    const form = document.querySelector('form[method="post"].space-y-5');
    if (!form) return;

    form.querySelectorAll('input, select, textarea').forEach((field) => {
        if (field.name === 'action') return;
        field.value = '';
    });

    const rows = Array.from(document.querySelectorAll('#medicine_rows tr'));
    rows.slice(1).forEach((row) => row.remove());
    rows[0]?.querySelectorAll('input').forEach((input) => {
        input.value = '';
    });
    if (rows[0]) hideMedicineResults(rows[0]);
    renumberRows();
    updatePage();
}

document.getElementById('add_row')?.addEventListener('click', () => {
    const body = document.getElementById('medicine_rows');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td class="px-3 py-2 text-right text-zinc-400"></td>
        <td class="px-3 py-2">
            <div class="medicine-picker relative">
                <input type="hidden" name="medicine_id[]" class="medicine-select">
                <input type="text" class="medicine-search w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 outline-none focus:border-emerald-400" placeholder="พิมพ์ชื่อยาอย่างน้อย 3 ตัว">
                <div class="medicine-results absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-64 overflow-auto rounded-md border border-zinc-700 bg-zinc-950 shadow-xl"></div>
            </div>
        </td>
        <td class="quantity-cell px-3 py-2">${quantityCellHtml()}</td>
        <td class="px-3 py-2">
            <div class="flex gap-1">
                <input name="reference_price[]" type="number" step="0.01" class="calc-input reference min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-emerald-400">
                <button type="button" class="price-search-btn rounded-md border border-zinc-700 px-2 py-2 text-zinc-200 hover:border-emerald-400" title="ค้นหาราคายาอ้างอิง">🔍</button>
            </div>
        </td>
        <td class="px-3 py-2"><input name="offer_price[]" type="number" step="0.01" class="calc-input offer w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-emerald-400"></td>
        <td class="px-3 py-2 text-center">
            <button type="button" class="remove-row-btn rounded-md border border-red-700 px-2 py-1 text-red-300 hover:bg-red-700/10">✕</button>
        </td>
        <td class="row-total px-3 py-2 text-right font-medium">0.00</td>
    `;
    body.appendChild(row);
    bindRow(row);
    renumberRows();
    updatePage();
});
document.querySelector('form[method="post"].space-y-5')?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    if (event.target.matches('textarea')) return;
    event.preventDefault();
});
document.querySelector('form[method="post"].space-y-5')?.addEventListener('submit', (event) => {
    if (!packageMode) return;
    const invalidRow = Array.from(document.querySelectorAll('#medicine_rows tr')).find((row) => {
        if (!row.querySelector('.medicine-select')?.value) return false;
        return !calculatePackageRow(row);
    });
    if (!invalidRow) return;
    event.preventDefault();
    invalidRow.querySelector('.package-size')?.focus();
    invalidRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
document.querySelectorAll('#medicine_rows tr').forEach(bindRow);
document.getElementById('toggle_package_mode')?.addEventListener('click', () => setPackageMode(!packageMode));
setPackageMode(packageMode);
if (isPageReload()) {
    setTimeout(clearMainFormForReload, 0);
}
document.addEventListener('click', (event) => {
    document.querySelectorAll('#medicine_rows tr').forEach((row) => {
        if (!row.querySelector('.medicine-picker')?.contains(event.target)) {
            hideMedicineResults(row);
        }
    });
});
document.getElementById('draft_id')?.addEventListener('change', (event) => {
    const item = drafts[event.target.value];
    if (item) document.getElementById('price_source').value = item.content;
});
// Helper to find history by id robustly (object keyed by id or array-like)
function findHistoryById(id) {
    if (!id) return null;
    if (histories[id]) return histories[id];
    for (const k in histories) {
        if (String(histories[k].id) === String(id)) return histories[k];
    }
    return null;
}

function findHistoryByCaseId(caseId) {
    if (!caseId) return null;
    let matched = null;
    for (const k in histories) {
        if (String(histories[k].case_id || '') === String(caseId)) {
            if (!matched || Number(histories[k].id || 0) > Number(matched.id || 0)) {
                matched = histories[k];
            }
        }
    }
    return matched;
}

function loadHistoryIntoForm(h, sourceLabel = '') {
    if (!h) return;
    try {
        const committee = JSON.parse(h.committee_json || '[]');
        const items = JSON.parse(h.items_json || '[]');
        document.getElementById('report_id').value = h.id || '';
        document.getElementById('case_id').value = h.case_id || '';
        document.querySelector('[name="committee_1"]').value = committee[0] || '';
        document.querySelector('[name="committee_2"]').value = committee[1] || '';
        document.querySelector('[name="committee_3"]').value = committee[2] || '';
        document.getElementById('price_source').value = h.price_source || '';

        const body = document.getElementById('medicine_rows');
        body.innerHTML = '';
        (items || []).forEach((it, idx) => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td class="px-3 py-2 text-right text-zinc-400">${idx + 1}</td>
                <td class="px-3 py-2">
                    <div class="medicine-picker relative">
                        <input type="hidden" name="medicine_id[]" class="medicine-select">
                        <input type="text" class="medicine-search w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 outline-none focus:border-emerald-400" placeholder="พิมพ์ชื่อยาอย่างน้อย 3 ตัว">
                        <div class="medicine-results absolute left-0 right-0 top-full z-50 mt-1 hidden max-h-64 overflow-auto rounded-md border border-zinc-700 bg-zinc-950 shadow-xl"></div>
                    </div>
                </td>
                <td class="quantity-cell px-3 py-2">${quantityCellHtml()}</td>
                <td class="px-3 py-2">
                    <div class="flex gap-1">
                        <input name="reference_price[]" type="number" step="0.01" class="calc-input reference min-w-0 flex-1 rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-emerald-400">
                        <button type="button" class="price-search-btn rounded-md border border-zinc-700 px-2 py-2 text-zinc-200 hover:border-emerald-400" title="ค้นหาราคายาอ้างอิง">🔍</button>
                    </div>
                </td>
                <td class="px-3 py-2"><input name="offer_price[]" type="number" step="0.01" class="calc-input offer w-full rounded-md border border-zinc-700 bg-zinc-950 px-2 py-2 text-right outline-none focus:border-emerald-400"></td>
                <td class="px-3 py-2 text-center">
                    <button type="button" class="remove-row-btn rounded-md border border-red-700 px-2 py-1 text-red-300 hover:bg-red-700/10">✕</button>
                </td>
                <td class="row-total px-3 py-2 text-right font-medium">0.00</td>
            `;
            body.appendChild(row);
            bindRow(row);

            const medName = (it.name || '').trim();
            let matchedId = it.medicine_id || '';
            if (matchedId && !medicines[matchedId]) matchedId = '';
            for (const mid in medicines) {
                if (matchedId) break;
                if ((medicines[mid].name || '').trim().toLowerCase() === medName.toLowerCase()) {
                    matchedId = mid;
                    break;
                }
            }
            const select = row.querySelector('.medicine-select');
            const search = row.querySelector('.medicine-search');
            if (matchedId) {
                select.value = matchedId;
                search.value = medicines[matchedId].name || medName;
            } else {
                select.value = '';
                search.value = medName;
            }
            const packageSize = numericInputValue(it.package_size || '');
            const storedQuantity = numericInputValue(it.quantity || '');
            const requestedQuantity = numericInputValue(it.requested_quantity || (packageSize ? Number(storedQuantity || 0) * Number(packageSize || 0) : storedQuantity));
            row.querySelector('[name="quantity[]"]').value = storedQuantity;
            row.querySelector('[name="requested_quantity[]"]').value = requestedQuantity;
            row.querySelector('[name="package_size[]"]').value = packageSize;
            const referencePrice = numericInputValue(it.reference_price || '');
            const offerPrice = numericInputValue(it.offer_price || '');
            row.querySelector('[name="reference_price[]"]').value = referencePrice === '' ? '' : Number(referencePrice || 0).toFixed(2);
            row.querySelector('[name="offer_price[]"]').value = offerPrice === '' ? '' : Number(offerPrice || 0).toFixed(2);
        });
        renumberRows();
        setPackageMode((items || []).some((item) => Number(numericInputValue(item.package_size || 0)) > 0));
        setCaseLoadStatus(sourceLabel, sourceLabel ? 'emerald' : 'zinc');
    } catch (e) {
        console.error(e);
    }
}

function loadCaseIntoForm(item) {
    if (!item) return;
    let committee = [];
    let items = [];
    try {
        committee = JSON.parse(item.committee_json || '[]').map((person) => {
            if (typeof person === 'string') return person;
            return committeeText(person.name || '', person.position || '', person.role || '');
        });
        items = JSON.parse(item.items_json || '[]').map((row) => ({
            medicine_id: row.medicine_id || '',
            name: row.name || '',
            unit: row.unit || '',
            specification: row.specification || '',
            quantity: numericInputValue(row.quantity || ''),
            reference_price: numericInputValue(row.reference_price || ''),
            offer_price: numericInputValue(row.offer_price || ''),
        }));
    } catch (e) {
        console.error(e);
    }
    loadHistoryIntoForm({
        id: '',
        case_id: item.id,
        committee_json: JSON.stringify(committee),
        items_json: JSON.stringify(items),
        price_source: '',
    }, 'ยังไม่พบรายงาน TOR ที่บันทึกไว้ จึงดึงรายการยาและจำนวนจากหัวข้อ 1');
}

document.getElementById('procurement_case_id')?.addEventListener('change', (e) => {
    const id = e.target.value;
    document.getElementById('case_id').value = id || '';
    document.getElementById('report_id').value = '';
    if (!id) {
        setCaseLoadStatus('');
        return;
    }
    const savedHistory = findHistoryByCaseId(id);
    if (savedHistory) {
        loadHistoryIntoForm(savedHistory, 'พบรายงาน TOR ที่เคยบันทึกไว้ จึงโหลดข้อมูลเดิมของใบนี้');
        return;
    }
    const item = procurementCases[id];
    if (item) loadCaseIntoForm(item);
});

const initialCaseId = document.getElementById('case_id')?.value;
if (initialCaseId && procurementCases[initialCaseId]) {
    setTimeout(() => {
        const savedHistory = findHistoryByCaseId(initialCaseId);
        if (savedHistory) {
            loadHistoryIntoForm(savedHistory, 'พบรายงาน TOR ที่เคยบันทึกไว้ จึงโหลดข้อมูลเดิมของใบนี้');
        } else {
            loadCaseIntoForm(procurementCases[initialCaseId]);
        }
    }, 0);
}
document.getElementById('apply_committee_set')?.addEventListener('click', () => {
    const item = committeeSets[document.getElementById('committee_set_id').value];
    if (!item) return;

    document.querySelector('[name="committee_1"]').value = committeeText(item.chair_name, item.chair_position, 'ประธานกรรมการ');
    document.querySelector('[name="committee_2"]').value = committeeText(item.member1_name, item.member1_position, 'กรรมการ');
    document.querySelector('[name="committee_3"]').value = committeeText(item.member2_name, item.member2_position, 'กรรมการ');
});
document.getElementById('toggle_committee_set_form')?.addEventListener('click', () => {
    document.getElementById('committee_set_panel')?.classList.toggle('hidden');
});
function openPriceReferenceModal(row) {
    activeReferenceInput = row.querySelector('.reference');
    const selectedMedicine = medicines[row.querySelector('.medicine-select')?.value];
    const query = priceReferenceQueryFromMedicine(selectedMedicine, row.querySelector('.medicine-search')?.value || '');
    document.getElementById('price_reference_modal').classList.remove('hidden');
    document.getElementById('price_reference_query').value = query;
    document.getElementById('price_reference_query').focus();
    searchPriceReferences(query);
}
function closePriceReferenceModal() {
    document.getElementById('price_reference_modal').classList.add('hidden');
}
async function searchPriceReferences(query) {
    const results = document.getElementById('price_reference_results');
    const status = document.getElementById('price_reference_status');
    status.textContent = 'กำลังค้นหา...';
    results.innerHTML = '';
    const response = await fetch(`tor_company_purchase.php?action=search_price_reference&q=${encodeURIComponent(arabicDigits(query))}&t=${Date.now()}`);
    const data = await response.json();
    if (!data.ok) {
        status.textContent = data.error || 'ค้นหาไม่สำเร็จ';
        return;
    }
    if (!data.items.length) {
        status.textContent = 'ไม่พบข้อมูลราคายาอ้างอิง';
        return;
    }
    status.textContent = `พบ ${data.items.length} รายการ`;
    results.innerHTML = data.items.map((item) => `
        <tr>
            <td class="px-3 py-2 align-top font-medium">${escapeHtml(item.medicine_name)}</td>
            <td class="px-3 py-2 align-top text-zinc-300">${escapeHtml(item.dosage_form_strength)}</td>
            <td class="px-3 py-2 align-top">${escapeHtml(item.unit)}</td>
            <td class="px-3 py-2 align-top text-right">${baht.format(Number(item.reference_price || 0))}</td>
            <td class="px-3 py-2 align-top text-right">
                <button type="button" class="select-price-reference rounded-md border border-emerald-500/60 px-3 py-1 text-emerald-200 hover:bg-emerald-500/10" data-price="${escapeHtml(item.reference_price)}">เลือก</button>
            </td>
        </tr>
    `).join('');
}
document.getElementById('close_price_reference_modal')?.addEventListener('click', closePriceReferenceModal);
document.getElementById('price_reference_modal')?.addEventListener('click', (event) => {
    if (event.target.id === 'price_reference_modal') closePriceReferenceModal();
});
document.getElementById('price_reference_query')?.addEventListener('input', (event) => {
    clearTimeout(priceReferenceSearchTimer);
    priceReferenceSearchTimer = setTimeout(() => searchPriceReferences(event.target.value), 250);
});
document.getElementById('price_reference_results')?.addEventListener('click', (event) => {
    const button = event.target.closest('.select-price-reference');
    if (!button || !activeReferenceInput) return;
    activeReferenceInput.value = Number(button.dataset.price || 0).toFixed(2);
    updatePage();
    closePriceReferenceModal();
});
updatePage();
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
