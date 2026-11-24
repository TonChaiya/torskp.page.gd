<?php
declare(strict_types=1);

/** Separate persistence for the additional approval memo. Does not modify TOR records. */
function scopePriceApprovalInstalled(): bool
{
    static $installed = null;
    if ($installed !== null) return $installed;
    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute(['scope_price_approval_memos']);
    $installed = (int) $stmt->fetchColumn() === 1;
    return $installed;
}

function scopePriceApprovalLoad(int $caseId): ?array
{
    if ($caseId <= 0 || !scopePriceApprovalInstalled()) return null;
    $stmt = db()->prepare('SELECT data_json FROM scope_price_approval_memos WHERE case_id = ? LIMIT 1');
    $stmt->execute([$caseId]);
    $json = $stmt->fetchColumn();
    if ($json === false) return null;
    $data = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('ข้อมูลบันทึกข้อความไม่ถูกต้อง');
    return $data;
}

function scopePriceApprovalExists(int $caseId): bool
{
    if ($caseId <= 0 || !scopePriceApprovalInstalled()) return false;
    $stmt = db()->prepare('SELECT 1 FROM scope_price_approval_memos WHERE case_id = ? LIMIT 1');
    $stmt->execute([$caseId]);
    return $stmt->fetchColumn() !== false;
}

function scopePriceApprovalSave(int $caseId, array $data): void
{
    if ($caseId <= 0) throw new InvalidArgumentException('Invalid case ID');
    requireDatabaseTables(['scope_price_approval_memos']);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    $stmt = db()->prepare(
        'INSERT INTO scope_price_approval_memos (case_id, memo_no_suffix, doc_date, data_json) '
        . 'VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE '
        . 'memo_no_suffix = VALUES(memo_no_suffix), doc_date = VALUES(doc_date), '
        . 'data_json = VALUES(data_json)'
    );
    $stmt->execute([
        $caseId,
        trim((string) ($data['memo_no_suffix'] ?? '')),
        trim((string) ($data['doc_date'] ?? '')),
        $json,
    ]);
}

function scopePriceApprovalDelete(int $caseId): void
{
    if ($caseId <= 0 || !scopePriceApprovalInstalled()) return;
    $stmt = db()->prepare('DELETE FROM scope_price_approval_memos WHERE case_id = ?');
    $stmt->execute([$caseId]);
}

function scopePriceApprovalClearAll(): void
{
    if (!scopePriceApprovalInstalled()) return;
    db()->exec('DELETE FROM scope_price_approval_memos');
}
