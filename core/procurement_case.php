<?php
declare(strict_types=1);
require_once __DIR__ . '/scope_price_approval_store.php';
require_once __DIR__ . '/document_filename.php';

function ensureProcurementTables(): void
{
    requireDatabaseTables(['procurement_cases', 'procurement_documents', 'tor_histories']);
}

function ensureTorHistoriesCaseColumn(): void
{
    requireDatabaseTables(['tor_histories']);
}

function procurementCases(): array
{
    ensureProcurementTables();
    return db()->query('SELECT id, title, supplier_name, principle_memo_no_suffix, principle_doc_date, updated_at, created_at FROM procurement_cases ORDER BY updated_at DESC, id DESC')->fetchAll();
}

function procurementCase(int $caseId): ?array
{
    ensureProcurementTables();
    if ($caseId <= 0) return null;
    $stmt = db()->prepare('SELECT * FROM procurement_cases WHERE id = ?');
    $stmt->execute([$caseId]);
    $case = $stmt->fetch();
    if (!$case) return null;
    $case['items'] = json_decode((string) $case['items_json'], true) ?: [];
    $case['committee'] = json_decode((string) $case['committee_json'], true) ?: [];
    return $case;
}

function latestProcurementCase(): ?array
{
    ensureProcurementTables();
    $row = db()->query('SELECT id FROM procurement_cases ORDER BY updated_at DESC, id DESC LIMIT 1')->fetch();
    return $row ? procurementCase((int) $row['id']) : null;
}

function procurementCaseByPrinciple(string $memoNoSuffix, string $docDate): ?array
{
    ensureProcurementTables();
    $memoNoSuffix = trim($memoNoSuffix);
    $docDate = trim($docDate);
    if ($memoNoSuffix === '' || $docDate === '') return null;
    $stmt = db()->prepare('SELECT id FROM procurement_cases WHERE principle_memo_no_suffix = ? AND principle_doc_date = ? LIMIT 1');
    $stmt->execute([$memoNoSuffix, $docDate]);
    $row = $stmt->fetch();
    return $row ? procurementCase((int) $row['id']) : null;
}

function procurementCaseDocuments(int $caseId): array
{
    ensureProcurementTables();
    if ($caseId <= 0) return [];
    $stmt = db()->prepare('SELECT document_type, memo_no_suffix, doc_date, updated_at FROM procurement_documents WHERE case_id = ?');
    $stmt->execute([$caseId]);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[(string) $row['document_type']] = $row;
    }
    return $map;
}

function procurementCaseCompletion(int $caseId): array
{
    $documents = procurementCaseDocuments($caseId);
    return [
        'principle_approval' => isset($documents['principle_approval']),
        'committee_scope_invitation' => isset($documents['committee_scope_invitation']),
        'scope_price_approval_memo' => scopePriceApprovalExists($caseId),
        'tor_company_purchase' => isset($documents['tor_company_purchase']),
        'committee_scope_result_memo' => isset($documents['committee_scope_result_memo']),
        'meeting_report' => isset($documents['meeting_report']),
    ];
}

function procurementDocument(int $caseId, string $documentType): ?array
{
    ensureProcurementTables();
    if ($caseId <= 0) return null;
    $stmt = db()->prepare('SELECT * FROM procurement_documents WHERE case_id = ? AND document_type = ?');
    $stmt->execute([$caseId, $documentType]);
    $document = $stmt->fetch();
    if (!$document) return null;
    $document['extra'] = json_decode((string) ($document['extra_json'] ?? ''), true) ?: [];
    return $document;
}

function saveProcurementDocument(int $caseId, string $documentType, string $memoNoSuffix, string $docDate, array $extra = []): void
{
    ensureProcurementTables();
    $stmt = db()->prepare("
        INSERT INTO procurement_documents (case_id, document_type, memo_no_suffix, doc_date, extra_json)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            memo_no_suffix = VALUES(memo_no_suffix),
            doc_date = VALUES(doc_date),
            extra_json = VALUES(extra_json)
    ");
    $stmt->execute([$caseId, $documentType, $memoNoSuffix, $docDate, json_encode($extra, JSON_UNESCAPED_UNICODE)]);
}

function saveProcurementCase(array $data): int
{
    ensureProcurementTables();
    $caseId = (int) ($data['id'] ?? 0);
    $principleMemoNoSuffix = trim((string) ($data['principle_memo_no_suffix'] ?? ''));
    $principleDocDate = trim((string) ($data['principle_doc_date'] ?? ''));
    if ($caseId === 0 && $principleMemoNoSuffix !== '' && $principleDocDate !== '') {
        $existing = procurementCaseByPrinciple($principleMemoNoSuffix, $principleDocDate);
        if ($existing) {
            $caseId = (int) $existing['id'];
        }
    }
    $itemsJson = json_encode(array_values($data['items'] ?? []), JSON_UNESCAPED_UNICODE);
    $committeeJson = json_encode(array_values($data['committee'] ?? []), JSON_UNESCAPED_UNICODE);
    $title = (string) ($data['title'] ?? ('จัดซื้อยา ' . date('Y-m-d H:i')));
    $supplierName = trim((string) ($data['supplier_name'] ?? ''));
    if ($caseId > 0 && !array_key_exists('supplier_name', $data)) {
        $oldCase = procurementCase($caseId);
        $supplierName = (string) ($oldCase['supplier_name'] ?? '');
    }
    if (preg_match_all('/./us', $supplierName) > 255) throw new InvalidArgumentException('ชื่อบริษัทต้องไม่เกิน 255 ตัวอักษร');
    $params = [
        $title,
        $supplierName,
        (string) ($data['fiscal_year'] ?? ''),
        (string) ($data['plan_approved_date'] ?? ''),
        (string) ($data['budget_amount'] ?? ''),
        (string) ($data['budget_amount_text'] ?? ''),
        (string) ($data['network_count'] ?? ''),
        $principleMemoNoSuffix,
        $principleDocDate,
        (string) ($data['signer_name'] ?? ''),
        (string) ($data['signer_position'] ?? ''),
        (string) ($data['director_name'] ?? ''),
        (string) ($data['director_position'] ?? ''),
        $itemsJson,
        $committeeJson,
    ];

    if ($caseId > 0) {
        $stmt = db()->prepare("
            UPDATE procurement_cases
            SET title = ?, supplier_name = ?, fiscal_year = ?, plan_approved_date = ?, budget_amount = ?, budget_amount_text = ?,
                network_count = ?, principle_memo_no_suffix = ?, principle_doc_date = ?,
                signer_name = ?, signer_position = ?, director_name = ?, director_position = ?,
                items_json = ?, committee_json = ?
            WHERE id = ?
        ");
        $stmt->execute([...$params, $caseId]);
        return $caseId;
    }

    $stmt = db()->prepare("
        INSERT INTO procurement_cases (
            title, supplier_name, fiscal_year, plan_approved_date, budget_amount, budget_amount_text,
            network_count, principle_memo_no_suffix, principle_doc_date,
            signer_name, signer_position, director_name, director_position,
            items_json, committee_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute($params);
    return (int) db()->lastInsertId();
}

function resetProcurementWorkflowData(): void
{
    ensureProcurementTables();
    db()->exec('DELETE FROM procurement_documents');
    db()->exec('DELETE FROM procurement_cases');
    db()->exec('DELETE FROM tor_histories');
    scopePriceApprovalClearAll();
}

function deleteProcurementCase(int $caseId): void
{
    ensureProcurementTables();
    if ($caseId <= 0) return;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('DELETE FROM procurement_documents WHERE case_id = ?');
        $stmt->execute([$caseId]);

        $stmt = $pdo->prepare('DELETE FROM tor_histories WHERE case_id = ?');
        $stmt->execute([$caseId]);

        $stmt = $pdo->prepare('DELETE FROM procurement_cases WHERE id = ?');
        $stmt->execute([$caseId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function deleteProcurementDocuments(int $caseId, array $documentTypes): void
{
    ensureProcurementTables();
    if ($caseId <= 0) return;

    $deleteScopePriceApproval = in_array('scope_price_approval_memo', $documentTypes, true);

    $allowedTypes = [
        'principle_approval',
        'committee_scope_invitation',
        'tor_company_purchase',
        'committee_scope_result_memo',
        'meeting_report',
    ];
    $documentTypes = array_values(array_intersect($allowedTypes, array_map('strval', $documentTypes)));
    if (!$documentTypes && !$deleteScopePriceApproval) return;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($deleteScopePriceApproval) {
            scopePriceApprovalDelete($caseId);
        }
        if ($documentTypes) {
            $placeholders = implode(',', array_fill(0, count($documentTypes), '?'));
            $stmt = $pdo->prepare("DELETE FROM procurement_documents WHERE case_id = ? AND document_type IN ($placeholders)");
            $stmt->execute([$caseId, ...$documentTypes]);
        }

        if (in_array('tor_company_purchase', $documentTypes, true)) {
            $stmt = $pdo->prepare('DELETE FROM tor_histories WHERE case_id = ?');
            $stmt->execute([$caseId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
