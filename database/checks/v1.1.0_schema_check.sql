-- Read-only. Safe even if tables/columns are missing. Select target database first.
SELECT DATABASE() AS selected_database, VERSION() AS server_version;
-- Missing required v1.1.0 columns: expect zero rows. Do NOT auto-run every migration.
SELECT e.table_name, e.column_name AS missing_column
FROM (
SELECT 'audit_logs' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'audit_logs' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'audit_logs' AS table_name, 'event_type' AS column_name
UNION ALL
SELECT 'audit_logs' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'audit_logs' AS table_name, 'details_json' AS column_name
UNION ALL
SELECT 'audit_logs' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'chair_name' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'chair_position' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'member1_name' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'member1_position' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'member2_name' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'member2_position' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'committee_sets' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'document_signer_defaults' AS table_name, 'document_type' AS column_name
UNION ALL
SELECT 'document_signer_defaults' AS table_name, 'proposer_name' AS column_name
UNION ALL
SELECT 'document_signer_defaults' AS table_name, 'proposer_position' AS column_name
UNION ALL
SELECT 'document_signer_defaults' AS table_name, 'approver_name' AS column_name
UNION ALL
SELECT 'document_signer_defaults' AS table_name, 'approver_position' AS column_name
UNION ALL
SELECT 'document_signer_defaults' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'document_signer_defaults' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'login_attempts' AS table_name, 'identifier_hash' AS column_name
UNION ALL
SELECT 'login_attempts' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'login_attempts' AS table_name, 'attempts' AS column_name
UNION ALL
SELECT 'login_attempts' AS table_name, 'first_attempt_at' AS column_name
UNION ALL
SELECT 'login_attempts' AS table_name, 'locked_until' AS column_name
UNION ALL
SELECT 'login_attempts' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'medicines' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'medicines' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'medicines' AS table_name, 'unit' AS column_name
UNION ALL
SELECT 'medicines' AS table_name, 'specification' AS column_name
UNION ALL
SELECT 'medicines' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'medicine_price_references' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'medicine_price_references' AS table_name, 'medicine_name' AS column_name
UNION ALL
SELECT 'medicine_price_references' AS table_name, 'dosage_form_strength' AS column_name
UNION ALL
SELECT 'medicine_price_references' AS table_name, 'unit' AS column_name
UNION ALL
SELECT 'medicine_price_references' AS table_name, 'reference_price' AS column_name
UNION ALL
SELECT 'medicine_price_references' AS table_name, 'source_file' AS column_name
UNION ALL
SELECT 'medicine_price_references' AS table_name, 'imported_at' AS column_name
UNION ALL
SELECT 'medicine_price_references' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'price_source_drafts' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'price_source_drafts' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'price_source_drafts' AS table_name, 'content' AS column_name
UNION ALL
SELECT 'price_source_drafts' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'supplier_name' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'fiscal_year' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'plan_approved_date' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'budget_amount' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'budget_amount_text' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'network_count' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'principle_memo_no_suffix' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'principle_doc_date' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'signer_name' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'signer_position' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'director_name' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'director_position' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'items_json' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'committee_json' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'procurement_cases' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'procurement_documents' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'procurement_documents' AS table_name, 'case_id' AS column_name
UNION ALL
SELECT 'procurement_documents' AS table_name, 'document_type' AS column_name
UNION ALL
SELECT 'procurement_documents' AS table_name, 'memo_no_suffix' AS column_name
UNION ALL
SELECT 'procurement_documents' AS table_name, 'doc_date' AS column_name
UNION ALL
SELECT 'procurement_documents' AS table_name, 'extra_json' AS column_name
UNION ALL
SELECT 'procurement_documents' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'procurement_documents' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'scope_price_approval_memos' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'scope_price_approval_memos' AS table_name, 'case_id' AS column_name
UNION ALL
SELECT 'scope_price_approval_memos' AS table_name, 'memo_no_suffix' AS column_name
UNION ALL
SELECT 'scope_price_approval_memos' AS table_name, 'doc_date' AS column_name
UNION ALL
SELECT 'scope_price_approval_memos' AS table_name, 'data_json' AS column_name
UNION ALL
SELECT 'scope_price_approval_memos' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'scope_price_approval_memos' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'tor_histories' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'tor_histories' AS table_name, 'case_id' AS column_name
UNION ALL
SELECT 'tor_histories' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'tor_histories' AS table_name, 'committee_json' AS column_name
UNION ALL
SELECT 'tor_histories' AS table_name, 'items_json' AS column_name
UNION ALL
SELECT 'tor_histories' AS table_name, 'price_source' AS column_name
UNION ALL
SELECT 'tor_histories' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'tor_histories' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'username' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'full_name' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'department' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'password_hash' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'role' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'account_status' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'must_change_password' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'password_changed_at' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'reviewed_by' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'reviewed_at' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'users' AS table_name, 'updated_at' AS column_name
) AS e
LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME=e.table_name AND c.COLUMN_NAME=e.column_name
WHERE c.COLUMN_NAME IS NULL ORDER BY e.table_name,e.column_name;
-- Actual column types/defaults/collations and nullability; compare with schema.sql.
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLLATION_NAME, EXTRA
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION;
-- Keys and constraints, including legacy CHECK (separate SHOW CREATE for expression).
SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME
FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX;
SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE
FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,CONSTRAINT_TYPE,CONSTRAINT_NAME;
