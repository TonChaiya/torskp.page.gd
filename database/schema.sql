-- TOR SKP v1.1.0 | structure-only baseline derived from Dev export 2026-11-24.
-- MySQL 8.4.3 source; InnoDB / utf8mb4_unicode_ci.
-- NEW EMPTY DATABASE ONLY. Select a new database before running.
-- No DROP, INSERT, UPDATE, DELETE, real data, or exported auto-increment counters.
-- Existing databases: use read-only checks and only missing migrations.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_event_type_created_at` (`event_type`,`created_at`),
  KEY `idx_audit_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `committee_sets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chair_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `chair_position` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member1_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member1_position` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member2_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `member2_position` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `committee_sets_title_unique` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `document_signer_defaults` (
  `document_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `proposer_position` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approver_position` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `identifier_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ip_address` varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `attempts` smallint UNSIGNED NOT NULL DEFAULT '0',
  `first_attempt_at` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`identifier_hash`,`ip_address`),
  KEY `idx_login_attempts_locked_until` (`locked_until`),
  KEY `idx_login_attempts_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `medicines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'หน่วย',
  `specification` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicines_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `medicine_price_references` (
  `id` int NOT NULL AUTO_INCREMENT,
  `medicine_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dosage_form_strength` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `source_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imported_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `price_ref_unique` (`medicine_name`,`dosage_form_strength`,`unit`),
  KEY `medicine_price_name_idx` (`medicine_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `price_source_drafts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `procurement_cases` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fiscal_year` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plan_approved_date` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `budget_amount` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `budget_amount_text` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `network_count` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `principle_memo_no_suffix` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `principle_doc_date` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `signer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `signer_position` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `director_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `director_position` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `committee_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `procurement_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int NOT NULL,
  `document_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `memo_no_suffix` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `doc_date` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `extra_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_procurement_documents_case_type` (`case_id`,`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tor_histories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `committee_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `price_source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tor_histories_case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `department` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'editor',
  `account_status` enum('pending','approved','rejected','disabled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `password_changed_at` datetime DEFAULT NULL,
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_account_status_created_idx` (`account_status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `scope_price_approval_memos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int NOT NULL,
  `memo_no_suffix` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `doc_date` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scope_price_approval_case` (`case_id`),
  CONSTRAINT `fk_scope_price_approval_case` FOREIGN KEY (`case_id`) REFERENCES `procurement_cases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
