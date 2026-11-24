-- TOR SKP: self-registration with mandatory administrator approval.
-- Run exactly once in the database used by core/config.php, BEFORE copying PHP files.
-- Existing users remain approved because of DEFAULT 'approved'.
-- Back up the database before importing; do not reimport this migration twice.

ALTER TABLE `users`
    ADD COLUMN `full_name` VARCHAR(150) NOT NULL DEFAULT '' AFTER `username`,
    ADD COLUMN `department` VARCHAR(255) NOT NULL DEFAULT '' AFTER `full_name`,
    ADD COLUMN `account_status` ENUM('pending','approved','rejected','disabled') NOT NULL DEFAULT 'approved' AFTER `role`,
    ADD COLUMN `reviewed_by` INT NULL DEFAULT NULL AFTER `password_changed_at`,
    ADD COLUMN `reviewed_at` DATETIME NULL DEFAULT NULL AFTER `reviewed_by`,
    ADD KEY `users_account_status_created_idx` (`account_status`,`created_at`);

-- Verify after import:
-- SELECT id,username,role,account_status,created_at FROM users ORDER BY id;
