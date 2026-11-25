-- Run only after missing-column check returns zero rows. Read-only aggregate checks.

-- Counts may legitimately differ; compare before/after changes on a database copy.

SELECT 'audit_logs' AS table_name, COUNT(*) AS row_count FROM `audit_logs`
UNION ALL
SELECT 'committee_sets' AS table_name, COUNT(*) AS row_count FROM `committee_sets`
UNION ALL
SELECT 'document_signer_defaults' AS table_name, COUNT(*) AS row_count FROM `document_signer_defaults`
UNION ALL
SELECT 'login_attempts' AS table_name, COUNT(*) AS row_count FROM `login_attempts`
UNION ALL
SELECT 'medicines' AS table_name, COUNT(*) AS row_count FROM `medicines`
UNION ALL
SELECT 'medicine_price_references' AS table_name, COUNT(*) AS row_count FROM `medicine_price_references`
UNION ALL
SELECT 'price_source_drafts' AS table_name, COUNT(*) AS row_count FROM `price_source_drafts`
UNION ALL
SELECT 'procurement_cases' AS table_name, COUNT(*) AS row_count FROM `procurement_cases`
UNION ALL
SELECT 'procurement_documents' AS table_name, COUNT(*) AS row_count FROM `procurement_documents`
UNION ALL
SELECT 'tor_histories' AS table_name, COUNT(*) AS row_count FROM `tor_histories`
UNION ALL
SELECT 'users' AS table_name, COUNT(*) AS row_count FROM `users`
UNION ALL
SELECT 'scope_price_approval_memos' AS table_name, COUNT(*) AS row_count FROM `scope_price_approval_memos`;

SELECT document_type, COUNT(*) AS row_count FROM procurement_documents GROUP BY document_type;
SELECT account_status, COUNT(*) AS row_count FROM users GROUP BY account_status;
SELECT COUNT(*) AS duplicate_nonempty_principle_groups FROM (SELECT principle_memo_no_suffix,principle_doc_date FROM procurement_cases WHERE principle_memo_no_suffix<>'' AND principle_doc_date<>'' GROUP BY principle_memo_no_suffix,principle_doc_date HAVING COUNT(*)>1) AS d;

SELECT COUNT(*) AS orphan_procurement_documents FROM `procurement_documents` d LEFT JOIN procurement_cases c ON c.id=d.case_id WHERE d.case_id IS NOT NULL AND c.id IS NULL;

SELECT COUNT(*) AS orphan_scope_price_approval_memos FROM `scope_price_approval_memos` d LEFT JOIN procurement_cases c ON c.id=d.case_id WHERE d.case_id IS NOT NULL AND c.id IS NULL;

SELECT COUNT(*) AS orphan_tor_histories FROM `tor_histories` d LEFT JOIN procurement_cases c ON c.id=d.case_id WHERE d.case_id IS NOT NULL AND c.id IS NULL;

SELECT COUNT(*) AS orphan_audit_logs_user_id FROM `audit_logs` d LEFT JOIN users u ON u.id=d.`user_id` WHERE d.`user_id` IS NOT NULL AND u.id IS NULL;

SELECT COUNT(*) AS orphan_users_reviewed_by FROM `users` d LEFT JOIN users u ON u.id=d.`reviewed_by` WHERE d.`reviewed_by` IS NOT NULL AND u.id IS NULL;

SELECT COUNT(*) AS invalid_json FROM `audit_logs` WHERE `details_json` IS NOT NULL AND JSON_VALID(`details_json`)=0; -- audit_logs.details_json

SELECT COUNT(*) AS invalid_json FROM `procurement_cases` WHERE `items_json` IS NOT NULL AND JSON_VALID(`items_json`)=0; -- procurement_cases.items_json

SELECT COUNT(*) AS invalid_json FROM `procurement_cases` WHERE `committee_json` IS NOT NULL AND JSON_VALID(`committee_json`)=0; -- procurement_cases.committee_json

SELECT COUNT(*) AS invalid_json FROM `procurement_documents` WHERE `extra_json` IS NOT NULL AND JSON_VALID(`extra_json`)=0; -- procurement_documents.extra_json

SELECT COUNT(*) AS invalid_json FROM `scope_price_approval_memos` WHERE `data_json` IS NOT NULL AND JSON_VALID(`data_json`)=0; -- scope_price_approval_memos.data_json

SELECT COUNT(*) AS invalid_json FROM `tor_histories` WHERE `committee_json` IS NOT NULL AND JSON_VALID(`committee_json`)=0; -- tor_histories.committee_json

SELECT COUNT(*) AS invalid_json FROM `tor_histories` WHERE `items_json` IS NOT NULL AND JSON_VALID(`items_json`)=0; -- tor_histories.items_json

SELECT COUNT(*) AS negative_reference_price FROM medicine_price_references WHERE reference_price<0;

SELECT COUNT(*) AS invalid_user_role FROM users WHERE role NOT IN ('admin','editor');

SELECT COUNT(*) AS invalid_password_change_flag FROM users WHERE must_change_password NOT IN (0,1);

-- TOR links: report missing/bad history_id without disclosing IDs or document contents.
SELECT COUNT(*) AS tor_missing_or_wrong_case_history
FROM procurement_documents d
LEFT JOIN tor_histories h ON CAST(h.id AS CHAR)=
  CASE WHEN JSON_VALID(d.extra_json)=1 THEN JSON_UNQUOTE(JSON_EXTRACT(d.extra_json,'$.history_id')) ELSE NULL END
  AND h.case_id=d.case_id
WHERE d.document_type='tor_company_purchase' AND h.id IS NULL;
SELECT COUNT(*) AS duplicate_case_type_groups FROM (SELECT case_id,document_type FROM procurement_documents GROUP BY case_id,document_type HAVING COUNT(*)>1) AS d;
SELECT COUNT(*) AS duplicate_scope_case_groups FROM (SELECT case_id FROM scope_price_approval_memos GROUP BY case_id HAVING COUNT(*)>1) AS d;
SELECT COUNT(*) AS approved_admin_count FROM users WHERE role='admin' AND account_status='approved';
