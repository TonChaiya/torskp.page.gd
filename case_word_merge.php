<?php
declare(strict_types=1);
/**
 * Read-only preparation for client-side DOCX assembly.
 * Checks session, CSRF and all six saved document statuses.
 * Does not accept uploads, create temporary files or write procurement data.
 */
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require __DIR__ . '/core/auth.php';
requireLogin(); // includes CSRF verification on every POST

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function bundleRespond(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function bundleCase(int $caseId): array
{
    if ($caseId <= 0) bundleRespond(400, ['error' => 'เลขชุดเอกสารไม่ถูกต้อง']);
    $case = procurementCase($caseId);
    if (!$case) bundleRespond(404, ['error' => 'ไม่พบชุดเอกสารที่เลือก']);
    $completed = procurementCaseCompletion($caseId);
    $order = [
        'principle_approval', 'committee_scope_invitation', 'tor_company_purchase',
        'scope_price_approval_memo', 'committee_scope_result_memo', 'meeting_report',
    ];
    $missing = [];
    foreach ($order as $name) if (empty($completed[$name])) $missing[] = $name;
    if ($missing) bundleRespond(409, ['error' => 'ชุดเอกสารยังบันทึกไม่ครบทั้ง 6 ขั้นตอน กรุณาบันทึกเอกสารที่ขาดก่อนรวม Word']);
    return $case;
}

function bundleFilename(int $caseId, string $supplier): string
{
    $short = function_exists('torFilenameSupplier') ? torFilenameSupplier($supplier) : trim($supplier);
    $short = $short !== '' ? $short : 'ไม่ระบุบริษัท';
    return 'ชุด' . str_pad((string)$caseId, 4, '0', STR_PAD_LEFT)
        . '-รวมเอกสาร01-06-' . $short . '.docx';
}

// Only metadata is returned. All document bytes remain in the browser.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') bundleRespond(405, ['error' => 'ต้องเรียกจากหน้าประวัติเอกสาร']);
if (($_POST['bundle_action'] ?? '') !== 'prepare') bundleRespond(400, ['error' => 'กรุณาโหลดหน้าใหม่ด้วย Ctrl+F5 เพื่อใช้ตัวรวม Word รุ่นล่าสุด']);
$caseId = filter_var($_POST['case_id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range'=>1]]) ?: 0;
if ((int)(currentUser()['id'] ?? 0) <= 0) bundleRespond(403, ['error' => 'กรุณาเข้าสู่ระบบใหม่']);
$case = bundleCase((int)$caseId);
bundleRespond(200, ['ok'=>true, 'filename'=>bundleFilename((int)$caseId, (string)($case['supplier_name'] ?? ''))]);
