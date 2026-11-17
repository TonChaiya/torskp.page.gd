CREATE TABLE IF NOT EXISTS `document_signer_defaults` (
  `document_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `proposer_position` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approver_position` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_type`),
  CONSTRAINT `chk_document_signer_defaults_type` CHECK (`document_type` IN ('principle_approval'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `document_signer_defaults`
    (`document_type`, `proposer_name`, `proposer_position`, `approver_name`, `approver_position`)
VALUES
    ('principle_approval', '', '', 'นายสุวิทย์ วรเจริญ', 'ผู้อำนวยการ รพ.สต.บ้านสันโค้ง\nปฏิบัติราชการแทนนายกองค์การบริหารส่วนจังหวัดเชียงใหม่')
ON DUPLICATE KEY UPDATE `document_type` = VALUES(`document_type`);
