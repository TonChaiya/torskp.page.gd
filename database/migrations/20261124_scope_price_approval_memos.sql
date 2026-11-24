-- Run once on the existing MySQL/MariaDB database (local and production).
-- Safe to re-run: does not delete or modify any existing procurement data.
-- This document is independent of other document types; the unique case_id
-- ensures that each procurement case has at most one such approval memo.
CREATE TABLE IF NOT EXISTS `scope_price_approval_memos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `case_id` INT NOT NULL,
  `memo_no_suffix` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `doc_date` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `data_json` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scope_price_approval_case` (`case_id`),
  CONSTRAINT `fk_scope_price_approval_case`
    FOREIGN KEY (`case_id`) REFERENCES `procurement_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
