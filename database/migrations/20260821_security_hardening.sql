-- TOR SKP security and schema hardening
-- Apply once to the imported local database before producing the deployment dump.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `committee_sets` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `medicine_price_references` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `medicines` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `price_source_drafts` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `procurement_cases` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `procurement_documents` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `tor_histories` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `users`
    ADD COLUMN `role` varchar(20) NOT NULL DEFAULT 'editor' AFTER `password_hash`,
    ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 1 AFTER `role`,
    ADD COLUMN `password_changed_at` datetime NULL DEFAULT NULL AFTER `must_change_password`,
    ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
    ADD CONSTRAINT `chk_users_role` CHECK (`role` IN ('admin', 'editor')),
    ADD CONSTRAINT `chk_users_must_change_password` CHECK (`must_change_password` IN (0, 1));

-- Keep one deterministic administrator and apply least privilege to other
-- imported accounts. Every imported account must choose a strong password.
UPDATE `users`
SET `role` = 'editor',
    `must_change_password` = 1,
    `password_changed_at` = NULL;

UPDATE `users`
SET `role` = 'admin'
WHERE `id` = (SELECT `first_id` FROM (SELECT MIN(`id`) AS `first_id` FROM `users`) AS `oldest_user`);

CREATE TABLE `login_attempts` (
    `identifier_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `ip_address` varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
    `attempts` smallint unsigned NOT NULL DEFAULT 0,
    `first_attempt_at` datetime NOT NULL,
    `locked_until` datetime NULL DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`identifier_hash`, `ip_address`),
    KEY `idx_login_attempts_locked_until` (`locked_until`),
    KEY `idx_login_attempts_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int NULL DEFAULT NULL,
    `event_type` varchar(100) NOT NULL,
    `ip_address` varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci NULL DEFAULT NULL,
    `details_json` json NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_user_id` (`user_id`),
    KEY `idx_audit_logs_event_type_created_at` (`event_type`, `created_at`),
    KEY `idx_audit_logs_created_at` (`created_at`),
    CONSTRAINT `fk_audit_logs_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `procurement_cases`
    MODIFY COLUMN `items_json` json NOT NULL,
    MODIFY COLUMN `committee_json` json NOT NULL;

ALTER TABLE `procurement_documents`
    MODIFY COLUMN `extra_json` json NULL,
    ADD CONSTRAINT `chk_procurement_documents_document_type`
        CHECK (`document_type` IN ('principle_approval', 'committee_scope_invitation', 'meeting_report', 'tor_company_purchase')),
    ADD CONSTRAINT `fk_procurement_documents_case`
        FOREIGN KEY (`case_id`) REFERENCES `procurement_cases` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE `tor_histories`
    MODIFY COLUMN `committee_json` json NOT NULL,
    MODIFY COLUMN `items_json` json NOT NULL,
    ADD CONSTRAINT `fk_tor_histories_case`
        FOREIGN KEY (`case_id`) REFERENCES `procurement_cases` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE `medicine_price_references`
    MODIFY COLUMN `reference_price` decimal(15,4) NOT NULL DEFAULT 0.0000,
    ADD CONSTRAINT `chk_medicine_price_references_reference_price`
        CHECK (`reference_price` >= 0);
