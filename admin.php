<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';
require __DIR__ . '/core/document_signer_defaults.php';
requireAdmin();
require_once __DIR__ . '/core/spreadsheet_import.php';
require_once __DIR__ . '/core/catalog_import.php';
require_once __DIR__ . '/core/medicine_catalog_tools.php';

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

ensurePriceReferenceTable();

$message = '';
$error = '';
$submittedAction = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? post('action') : '';

// All import writes require an authenticated, validated preview token.
if (str_starts_with((string) ($_GET['action'] ?? ''), 'import_v2_')) {
    torImportEndpoint(db());
    exit;
}

// Admin-only full medicine export and read-only reset preview.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['action'] ?? '') === 'export_medicines') {
    try { torExportMedicines(db()); exit; }
    catch (Throwable $e) { error_log('Medicine export failed: '.$e->getMessage()); $error = 'ส่งออกไม่สำเร็จ: '.($e instanceof PDOException ? 'กรุณาตรวจสอบฐานข้อมูล' : $e->getMessage()); }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['action'] ?? '') === 'medicine_clear_preview') {
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: private, no-store');
    try { echo json_encode(['ok'=>true]+torMedicineClearPreview(db()), JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { error_log('Medicine clear preview failed: '.$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false,'error'=>'ไม่สามารถตรวจสอบรายการยาได้'],JSON_UNESCAPED_UNICODE); }
    exit;
}

// Read-only confirmation snapshot for an explicit annual price-catalog reset.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['action'] ?? '') === 'price_clear_preview') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    try {
        echo json_encode(['ok'=>true] + torClearPricePreview(db()), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log('Price clear preview failed: '.$e->getMessage());
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'ไม่สามารถตรวจข้อมูลราคากลางได้ กรุณาตรวจสอบฐานข้อมูล'], JSON_UNESCAPED_UNICODE);
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
    $template = __DIR__ . '/templates/import/TOR_SKP_v1.2.0_medicine_template.xlsx';
    if (is_file($template) && is_readable($template)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="TOR_SKP_v1.2.0_medicine_template.xlsx"');
        header('Content-Length: ' . filesize($template));
        readfile($template);
        exit;
    }
    sendSampleXlsx('medicine_import_sample.xlsx', [
        ['ชื่อยา', 'หน่วย', 'คุณสมบัติยา'],
        ['Acyclovir 400 mg', 'tablet', "1. เป็นยาเม็ดชนิดรับประทาน\n2. ประกอบด้วยตัวยา Acyclovir 400 mg\n3. บรรจุในภาชนะปิดสนิท\n4. ฉลาก\n- ระบุชื่อยาและความแรง"],
        ['Amoxicillin 500 mg', 'capsule', 'เป็นยาแคปซูล ชนิดรับประทาน'],
    ]);
}

if (($_GET['action'] ?? '') === 'sample_price_refs') {
    $template = __DIR__ . '/templates/import/TOR_SKP_v1.2.0_price_reference_template.xlsx';
    if (is_file($template) && is_readable($template)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="TOR_SKP_v1.2.0_price_reference_template.xlsx"');
        header('Content-Length: ' . filesize($template));
        readfile($template);
        exit;
    }
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

        if (in_array($action, ['import_medicines', 'import_price_refs'], true)) {
            throw new RuntimeException('กรุณาโหลดหน้าใหม่และตรวจสอบ Preview ก่อนยืนยันนำเข้า');
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

        if ($action === 'clear_medicines') {
            $expected = filter_var(post('expected_count'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($expected === false) throw new RuntimeException('จำนวนรายการไม่ถูกต้อง กรุณาเปิดหน้าต่างใหม่');
            $removed = torClearMedicines(db(), post('snapshot_hash'), $expected, post('confirm_phrase'), post('backup_ack') === '1');
            $message = 'ล้างข้อมูลรายการยาและคุณลักษณะแล้ว '.number_format($removed).' รายการ';
        }

        if ($action === 'clear_price_refs') {
            $expected = filter_var(post('expected_count'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($expected === false) throw new RuntimeException('จำนวนรายการไม่ถูกต้อง กรุณาเปิดหน้าต่างใหม่');
            $removed = torClearPriceReferences(db(), post('snapshot_hash'), $expected, post('confirm_phrase'), post('backup_ack') === '1');
            $message = 'ล้างข้อมูลราคากลางแล้ว '.number_format($removed).' รายการ สามารถนำเข้าราคากลางชุดใหม่ได้';
        }

        if ($action === 'delete_price_ref') {
            $stmt = db()->prepare('DELETE FROM medicine_price_references WHERE id = ?');
            $stmt->execute([(int) post('id')]);
            $message = 'ลบราคากลางยาแล้ว';
        }

    }
} catch (Throwable $e) {
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

// Preserve manual-entry values after a validation/database error and reopen the modal.
$medicineFormError = $error !== '' && $submittedAction === 'save_medicine';
$priceFormError = $error !== '' && $submittedAction === 'save_price_ref';
if ($medicineFormError) $editMedicine = ['id'=>(int)post('id'),'name'=>post('name'),'unit'=>post('unit'),'specification'=>post('specification')];
if ($priceFormError) $editPrice = ['id'=>(int)post('id'),'medicine_name'=>post('medicine_name'),'dosage_form_strength'=>post('dosage_form_strength'),'unit'=>post('price_unit'),'reference_price'=>post('reference_price')];

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
$openMedicines = $editMedicine !== null || $medicineSearch !== '' || in_array($submittedAction, ['save_medicine', 'delete_medicine', 'import_medicines', 'clear_medicines'], true);
$openPrices = $editPrice !== null || $priceSearch !== '' || in_array($submittedAction, ['save_price_ref', 'delete_price_ref', 'import_price_refs', 'clear_price_refs'], true);
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
    <link rel="stylesheet" href="assets/admin-import.css?v=1203">
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
            <div class="tor-catalog-tools" aria-label="จัดการรายการยา">
                <button type="button" class="tor-modal-primary" data-modal-add="medicines"><span aria-hidden="true">＋</span> เพิ่มรายการยา</button>
                <button type="button" class="tor-modal-secondary" data-modal-open="medicines-import-box">นำเข้า Excel / CSV</button>
                <a class="tor-template-link" href="admin.php?action=sample_medicines">ดาวน์โหลด Excel Template</a>
                <a class="tor-modal-secondary" href="admin.php?action=export_medicines">ส่งออก Excel รายการยาทั้งหมด</a>
                <a class="tor-modal-secondary" href="catalog_snapshot_review.php">ตรวจ Snapshot เอกสาร</a>
                <button type="button" class="tor-modal-danger tor-clear-trigger" data-modal-open="medicines-clear-box">ล้างรายการยาทั้งหมด</button>
            </div>
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
                                <a href="admin.php?edit_medicine=<?= (int) $medicine['id'] ?>" data-modal-edit="medicines" data-row="<?= h(json_encode(['id'=> (int) $medicine['id'], 'name'=>$medicine['name'], 'unit'=>$medicine['unit'], 'specification'=>$medicine['specification']], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>" class="text-emerald-300 hover:text-emerald-200">แก้ไข</a>
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
            <div class="tor-catalog-tools" aria-label="จัดการราคากลางยา">
                <button type="button" class="tor-modal-primary" data-modal-add="prices"><span aria-hidden="true">＋</span> เพิ่มราคากลาง</button>
                <button type="button" class="tor-modal-secondary" data-modal-open="prices-import-box">นำเข้า Excel / CSV</button>
                <a class="tor-template-link" href="admin.php?action=sample_price_refs">ดาวน์โหลด Excel Template</a>
                <button type="button" class="tor-modal-danger tor-clear-trigger" data-modal-open="prices-clear-box">ล้างราคากลางทั้งหมด</button>
            </div>
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
                                <a href="admin.php?edit_price=<?= (int) $row['id'] ?>" data-modal-edit="prices" data-row="<?= h(json_encode(['id'=>(int) $row['id'], 'medicine_name'=>$row['medicine_name'], 'dosage_form_strength'=>$row['dosage_form_strength'], 'unit'=>$row['unit'], 'reference_price'=>$row['reference_price']], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?>" class="text-emerald-300 hover:text-emerald-200">แก้ไข</a>
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
    function appendActions(row, kind, item) {
        const id = Number(item.id);
        const cell = document.createElement('td');
        cell.className = 'px-3 py-2 align-top text-right compact-row-actions';
        const edit = document.createElement('a');
        edit.href = 'admin.php?' + (kind === 'medicines' ? 'edit_medicine=' : 'edit_price=') + encodeURIComponent(String(id));
        edit.textContent = 'แก้ไข';
        edit.dataset.modalEdit = kind;
        edit.dataset.row = JSON.stringify(item);
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
            appendActions(tr, kind, item);
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

})();

</script>
<dialog class="tor-admin-modal tor-edit-modal" id="medicines-edit-box" aria-labelledby="medicines-edit-title" <?= $editMedicine ? 'data-auto-open' : '' ?>>
  <header class="tor-modal-header"><div><small>TOR SKP · จัดการข้อมูล</small><h2 id="medicines-edit-title"><?= !empty($editMedicine['id']) ? 'แก้ไขรายการยา' : 'เพิ่มรายการยา' ?></h2></div><button type="button" class="tor-modal-x" data-modal-close aria-label="ปิดหน้าต่าง">×</button></header>
  <div class="tor-modal-body"><?php if ($medicineFormError): ?><p class="tor-modal-error" role="alert"><?= h($error) ?></p><?php endif; ?><form method="post" action="admin.php#medicine-catalog" class="tor-modal-form">
                <input type="hidden" name="action" value="save_medicine">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= h((string) ($editMedicine['id'] ?? 0)) ?>">
                <label class="block">
                    <span class="text-sm text-zinc-300">ชื่อยา</span>
                    <input name="name" required maxlength="255" value="<?= h($editMedicine['name'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                </label>
                <label class="block">
                    <span class="text-sm text-zinc-300">หน่วย</span>
                    <input name="unit" required maxlength="100" value="<?= h($editMedicine['unit'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                </label>
                <label class="block">
                    <span class="text-sm text-zinc-300">คุณสมบัติยา</span>
                    <textarea name="specification" required rows="9" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400"><?= h($editMedicine['specification'] ?? '') ?></textarea><small class="tor-modal-hint">ขึ้นบรรทัดใหม่ได้ตามข้อ 1, 2, 3 และข้อย่อย ระบบจะเก็บข้อความตามที่กรอก</small>
                </label>
                <div class="tor-modal-actions"><button type="button" class="tor-modal-secondary" data-modal-close>ยกเลิก</button><button type="submit" class="tor-modal-primary">บันทึก</button></div>
            </form></div>
</dialog>
<dialog class="tor-admin-modal tor-import-modal" id="medicines-import-box" aria-labelledby="medicines-import-title">
  <header class="tor-modal-header"><div><small>TOR SKP · ตรวจสอบก่อนบันทึก</small><h2 id="medicines-import-title">นำเข้ารายการยา</h2></div><button type="button" class="tor-modal-x" data-modal-close aria-label="ปิดหน้าต่าง">×</button></header>
  <div class="tor-modal-body"><?php $importKind = 'medicines'; require __DIR__ . '/components/catalog_import_panel.php'; ?></div>
</dialog>
<dialog class="tor-admin-modal tor-edit-modal" id="prices-edit-box" aria-labelledby="prices-edit-title" <?= $editPrice ? 'data-auto-open' : '' ?>>
  <header class="tor-modal-header"><div><small>TOR SKP · จัดการข้อมูล</small><h2 id="prices-edit-title"><?= !empty($editPrice['id']) ? 'แก้ไขราคากลางยา' : 'เพิ่มราคากลาง' ?></h2></div><button type="button" class="tor-modal-x" data-modal-close aria-label="ปิดหน้าต่าง">×</button></header>
  <div class="tor-modal-body"><?php if ($priceFormError): ?><p class="tor-modal-error" role="alert"><?= h($error) ?></p><?php endif; ?><form method="post" action="admin.php#price-references" class="tor-modal-form">
                <input type="hidden" name="action" value="save_price_ref">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= h((string) ($editPrice['id'] ?? 0)) ?>">
                <label class="block">
                    <span class="text-sm text-zinc-300">รายการยา</span>
                    <input name="medicine_name" required maxlength="255" value="<?= h($editPrice['medicine_name'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                </label>
                <label class="block">
                    <span class="text-sm text-zinc-300">รูปแบบยาและความแรง</span>
                    <input name="dosage_form_strength" required maxlength="255" value="<?= h($editPrice['dosage_form_strength'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                </label>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block">
                        <span class="text-sm text-zinc-300">หน่วย</span>
                        <input name="price_unit" required maxlength="100" value="<?= h($editPrice['unit'] ?? '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
                    </label>
                    <label class="block">
                        <span class="text-sm text-zinc-300">ราคากลางต่อหน่วย</span>
                        <input name="reference_price" type="text" value="<?= h(isset($editPrice['reference_price']) ? (string) $editPrice['reference_price'] : '') ?>" class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 text-right outline-none focus:border-emerald-400">
                    </label>
                </div>
                <div class="tor-modal-actions"><button type="button" class="tor-modal-secondary" data-modal-close>ยกเลิก</button><button type="submit" class="tor-modal-primary">บันทึก</button></div>
            </form></div>
</dialog>
<dialog class="tor-admin-modal tor-import-modal" id="prices-import-box" aria-labelledby="prices-import-title">
  <header class="tor-modal-header"><div><small>TOR SKP · ตรวจสอบก่อนบันทึก</small><h2 id="prices-import-title">นำเข้าราคากลางยา</h2></div><button type="button" class="tor-modal-x" data-modal-close aria-label="ปิดหน้าต่าง">×</button></header>
  <div class="tor-modal-body"><?php $importKind = 'prices'; require __DIR__ . '/components/catalog_import_panel.php'; ?></div>
</dialog>
<dialog class="tor-admin-modal tor-clear-modal" id="medicines-clear-box" aria-labelledby="medicines-clear-title">
  <header class="tor-modal-header"><div><small>TOR SKP · จัดการคลังคุณลักษณะยา</small><h2 id="medicines-clear-title">ล้างรายการยาทั้งหมด</h2></div><button type="button" class="tor-modal-x" data-modal-close aria-label="ปิดหน้าต่าง">×</button></header>
  <div class="tor-modal-body">
    <form method="post" action="admin.php#medicine-catalog" class="tor-modal-form" id="medicine-clear-form">
      <input type="hidden" name="action" value="clear_medicines">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="snapshot_hash" value="">
      <input type="hidden" name="expected_count" value="">
      <div class="tor-clear-notice"><strong>รายการยาที่จะล้าง: <span id="medicine-clear-count" aria-live="polite">กำลังตรวจสอบ…</span></strong><p>ล้างทุกรายการในคลังรายการยา ไม่ใช่เฉพาะผลการค้นหา ไม่สามารถเรียกคืนจากหน้านี้ได้</p></div>
      <p class="tor-modal-hint">ไม่ลบราคากลางหรือประวัติเอกสาร ระบบตรวจ Snapshot ก่อนอนุญาตให้ล้าง กรุณาปิดฟอร์มเอกสารที่เปิดค้างและสำรองฐานก่อนล้าง หากมีข้อมูลไม่ครบให้เปิด <a class="underline" href="catalog_snapshot_review.php">ตรวจ Snapshot เอกสาร</a> ก่อน</p>
      <p id="medicine-clear-warning" class="tor-modal-hint" role="status" hidden></p>
      <p id="medicine-clear-error" class="tor-modal-error" role="alert" hidden></p>
      <label class="tor-clear-ack"><input type="checkbox" name="backup_ack" value="1" required><span>สำรองข้อมูลแล้ว และเข้าใจว่ารายการยาและคุณลักษณะทั้งหมดจะถูกลบ</span></label>
      <label><span>พิมพ์คำว่า ล้างรายการยา เพื่อยืนยัน</span><input name="confirm_phrase" required autocomplete="off" maxlength="32" placeholder="ล้างรายการยา"></label>
      <div class="tor-modal-actions"><button type="button" class="tor-modal-secondary" data-modal-close>ยกเลิก</button><button type="submit" class="tor-modal-danger" disabled>ยืนยันล้างรายการยา</button></div>
    </form>
  </div>
</dialog>
<dialog class="tor-admin-modal tor-clear-modal" id="prices-clear-box" aria-labelledby="prices-clear-title">
  <header class="tor-modal-header"><div><small>TOR SKP · อัปเดตราคากลางประจำปี</small><h2 id="prices-clear-title">ล้างราคากลางทั้งหมด</h2></div><button type="button" class="tor-modal-x" data-modal-close aria-label="ปิดหน้าต่าง">×</button></header>
  <div class="tor-modal-body">
    <form method="post" action="admin.php#price-references" class="tor-modal-form" id="price-clear-form">
      <input type="hidden" name="action" value="clear_price_refs">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="snapshot_hash" value="">
      <input type="hidden" name="expected_count" value="">
      <div class="tor-clear-notice"><strong>ราคากลางที่จะล้าง: <span id="price-clear-count" aria-live="polite">กำลังตรวจสอบ…</span></strong><p>ล้างทุกรายการในคลังราคากลาง ไม่ใช่เฉพาะผลการค้นหา ไม่สามารถเรียกคืนจากหน้านี้ได้</p></div>
      <p class="tor-modal-hint">ไม่ลบรายการยา คุณลักษณะยา หรือประวัติเอกสารที่บันทึกไว้ หากต้องการอัปเดตราคากลางโดยไม่ให้คลังว่างระหว่างปี ให้ใช้โหมดแทนที่ทั้งหมดใน Import แทน</p>
      <p id="price-clear-error" class="tor-modal-error" role="alert" hidden></p>
      <label class="tor-clear-ack"><input type="checkbox" name="backup_ack" value="1" required><span>สำรองข้อมูลแล้ว และเข้าใจว่าราคากลางทั้งหมดจะถูกลบ</span></label>
      <label><span>พิมพ์คำว่า ล้างราคากลาง เพื่อยืนยัน</span><input name="confirm_phrase" required autocomplete="off" maxlength="32" placeholder="ล้างราคากลาง"></label>
      <div class="tor-modal-actions"><button type="button" class="tor-modal-secondary" data-modal-close>ยกเลิก</button><button type="submit" class="tor-modal-danger" disabled>ยืนยันล้างราคากลาง</button></div>
    </form>
  </div>
</dialog>
<script src="assets/admin-import.js?v=1202"></script>
<script src="assets/admin-modal.js?v=1300"></script>
</body>
</html>
