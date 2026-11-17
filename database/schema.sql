
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `event_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `details_json` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_event_type_created_at` (`event_type`,`created_at`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `committee_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `identifier_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ip_address` varchar(45) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL DEFAULT '0',
  `first_attempt_at` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`identifier_hash`,`ip_address`),
  KEY `idx_login_attempts_locked_until` (`locked_until`),
  KEY `idx_login_attempts_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicine_price_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
  KEY `medicine_price_name_idx` (`medicine_name`),
  CONSTRAINT `chk_medicine_price_references_reference_price` CHECK ((`reference_price` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=2521 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'หน่วย',
  `specification` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `medicines_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=235 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_signer_defaults`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_signer_defaults` (
  `document_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `proposer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `proposer_position` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approver_position` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`document_type`),
  CONSTRAINT `chk_document_signer_defaults_type` CHECK ((`document_type` in (_utf8mb4'principle_approval')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `document_signer_defaults` VALUES ('principle_approval','','','นายสุวิทย์ วรเจริญ','ผู้อำนวยการ รพ.สต.บ้านสันโค้ง\nปฏิบัติราชการแทนนายกองค์การบริหารส่วนจังหวัดเชียงใหม่',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP);
DROP TABLE IF EXISTS `price_source_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `price_source_drafts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_cases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procurement_cases` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  `items_json` json NOT NULL,
  `committee_json` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `procurement_cases_principle_unique` (`principle_memo_no_suffix`,`principle_doc_date`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `procurement_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `procurement_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int NOT NULL,
  `document_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `memo_no_suffix` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `doc_date` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `extra_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `procurement_documents_case_type_unique` (`case_id`,`document_type`),
  KEY `procurement_documents_case_idx` (`case_id`),
  CONSTRAINT `fk_procurement_documents_case` FOREIGN KEY (`case_id`) REFERENCES `procurement_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_procurement_documents_document_type` CHECK ((`document_type` in (_utf8mb4'principle_approval',_utf8mb4'committee_scope_invitation',_utf8mb4'meeting_report',_utf8mb4'tor_company_purchase')))
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tor_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tor_histories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `committee_json` json NOT NULL,
  `items_json` json NOT NULL,
  `price_source` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tor_histories_case_idx` (`case_id`),
  CONSTRAINT `fk_tor_histories_case` FOREIGN KEY (`case_id`) REFERENCES `procurement_cases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'editor',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '1',
  `password_changed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  CONSTRAINT `chk_users_must_change_password` CHECK ((`must_change_password` in (0,1))),
  CONSTRAINT `chk_users_role` CHECK ((`role` in (_utf8mb4'admin',_utf8mb4'editor')))
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
