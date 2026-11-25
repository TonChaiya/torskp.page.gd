-- TOR SKP: supplier per procurement case (one-time schema change).
-- Compatible with MySQL 5.7+/8.0 and MariaDB. Safe for existing rows.
-- Before execution: back up the database and select the correct database in phpMyAdmin.
-- Idempotent: repeat execution does not add the column a second time.
SET @tor_skp_supplier_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'procurement_cases'
    AND COLUMN_NAME = 'supplier_name'
);
SET @tor_skp_supplier_sql := IF(
  @tor_skp_supplier_exists = 0,
  'ALTER TABLE `procurement_cases` ADD COLUMN `supplier_name` VARCHAR(255) NOT NULL DEFAULT '''' AFTER `title`',
  'SELECT ''supplier_name already exists: no changes needed'' AS message'
);
PREPARE tor_skp_supplier_stmt FROM @tor_skp_supplier_sql;
EXECUTE tor_skp_supplier_stmt;
DEALLOCATE PREPARE tor_skp_supplier_stmt;
-- Existing cases receive empty supplier_name, not fictional supplier values.
-- No DELETE, no UPDATE of existing case content, and no changes to the existing keys.
