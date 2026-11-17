<?php
declare(strict_types=1);

function builtInDocumentSignerDefaults(string $documentType): array
{
    $defaults = [
        'principle_approval' => [
            'proposer_name' => '',
            'proposer_position' => '',
            'approver_name' => 'นายสุวิทย์ วรเจริญ',
            'approver_position' => "ผู้อำนวยการ รพ.สต.บ้านสันโค้ง\nปฏิบัติราชการแทนนายกองค์การบริหารส่วนจังหวัดเชียงใหม่",
        ],
    ];
    return $defaults[$documentType] ?? [
        'proposer_name' => '',
        'proposer_position' => '',
        'approver_name' => '',
        'approver_position' => '',
    ];
}

function documentSignerDefaults(string $documentType): array
{
    $fallback = builtInDocumentSignerDefaults($documentType);
    try {
        requireDatabaseTables(['document_signer_defaults']);
        $stmt = db()->prepare('SELECT proposer_name, proposer_position, approver_name, approver_position FROM document_signer_defaults WHERE document_type = ? LIMIT 1');
        $stmt->execute([$documentType]);
        $row = $stmt->fetch();
        return $row ? array_merge($fallback, $row) : $fallback;
    } catch (Throwable $e) {
        return $fallback;
    }
}

function saveDocumentSignerDefaults(string $documentType, array $values): void
{
    if ($documentType !== 'principle_approval') {
        throw new InvalidArgumentException('ประเภทเอกสารสำหรับผู้ลงนามไม่ถูกต้อง');
    }
    requireDatabaseTables(['document_signer_defaults']);
    $stmt = db()->prepare("
        INSERT INTO document_signer_defaults
            (document_type, proposer_name, proposer_position, approver_name, approver_position)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            proposer_name = VALUES(proposer_name),
            proposer_position = VALUES(proposer_position),
            approver_name = VALUES(approver_name),
            approver_position = VALUES(approver_position),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        $documentType,
        trim((string) ($values['proposer_name'] ?? '')),
        trim((string) ($values['proposer_position'] ?? '')),
        trim((string) ($values['approver_name'] ?? '')),
        trim((string) ($values['approver_position'] ?? '')),
    ]);
}
