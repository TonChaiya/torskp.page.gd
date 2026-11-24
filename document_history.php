<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require __DIR__ . '/core/auth.php';
requireLogin();

function h(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function historySearchText(string $text): string
{
    return mb_strtolower(strtr($text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]), 'UTF-8');
}
function historyThaiDigits(string $text): string
{
    return strtr($text, [
        '0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔',
        '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙',
    ]);
}

// Preserve the existing delete actions and their destinations. requireLogin()
// verifies CSRF for POST submissions before this handler is reached.
ensureProcurementTables();
$deleteError = '';
if (($_POST['action'] ?? '') === 'delete_selection') {
    $deleteCaseId = (int) ($_POST['case_id'] ?? 0);
    $deleteMode = (string) ($_POST['delete_mode'] ?? '');
    $documentTypes = is_array($_POST['document_types'] ?? null) ? $_POST['document_types'] : [];
    try {
        if ($deleteMode === 'case') {
            deleteProcurementCase($deleteCaseId);
        } else {
            deleteProcurementDocuments($deleteCaseId, $documentTypes);
        }
        header('Location: document_history.php?deleted=1');
        exit;
    } catch (Throwable $e) {
        $deleteError = 'ลบรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง';
    }
}

$documentSteps = [
    'principle_approval' => ['short' => 'อนุมัติหลักการ', 'label' => 'บันทึกข้อความ ขออนุมัติในหลักการ', 'page' => 'principle_approval_memo.php'],
    'committee_scope_invitation' => ['short' => 'เชิญกรรมการ', 'label' => 'แจ้งคำสั่งและเชิญประชุม', 'page' => 'committee_scope_invitation.php'],
    'scope_price_approval_memo' => ['short' => 'อนุมัติใช้ TOR', 'label' => 'ขออนุมัติคุณลักษณะและราคากลาง', 'page' => 'scope_price_approval_memo.php'],
    'tor_company_purchase' => ['short' => 'เอกสาร TOR', 'label' => 'เอกสาร TOR', 'page' => 'tor_company_purchase.php'],
    'committee_scope_result_memo' => ['short' => 'รายงานผล', 'label' => 'รายงานผลการพิจารณา', 'page' => 'committee_scope_result_memo.php'],
    'meeting_report' => ['short' => 'รายงานประชุม', 'label' => 'รายงานการประชุม', 'page' => 'meeting_report.php'],
];

// Get actual item names from the case record: procurementCases() does not
// select items_json. Reuse these details for the list and live-search index.
$caseRows = [];
$totalComplete = 0;
foreach (procurementCases() as $case) {
    $caseId = (int) ($case['id'] ?? 0);
    if ($caseId <= 0) continue;
    $fullCase = procurementCase($caseId) ?: [];
    $items = is_array($fullCase['items'] ?? null) ? $fullCase['items'] : [];
    $itemNames = [];
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $name = trim((string) ($item['name'] ?? ''));
        if ($name !== '') $itemNames[] = $name;
    }
    $completion = procurementCaseCompletion($caseId);
    $completedCount = 0;
    $nextStep = null;
    $deleteOptions = [];
    foreach ($documentSteps as $key => $step) {
        $hasDocument = !empty($completion[$key]);
        if ($hasDocument) $completedCount++;
        elseif ($nextStep === null) $nextStep = $step;
        $deleteOptions[$key] = ['label' => $step['short'], 'available' => $hasDocument];
    }
    $totalComplete += (int) ($completedCount === count($documentSteps));
    $memoNo = trim((string) ($case['principle_memo_no_suffix'] ?? ''));
    $docDate = trim((string) ($case['principle_doc_date'] ?? ''));
    $caseTitle = trim((string) ($case['title'] ?? ''));
    $label = 'ชม.๕๑๐๐๖.๑๓.๑/' . ($memoNo !== '' ? $memoNo : '—');
    $caseRows[] = [
        'id' => $caseId,
        'label' => $label,
        'doc_date' => $docDate,
        'title' => $caseTitle,
        'items_count' => count($items),
        'item_names' => $itemNames,
        'completion' => $completion,
        'completed_count' => $completedCount,
        'next_step' => $nextStep,
        'delete_options' => $deleteOptions,
        'search' => historySearchText(implode(' ', [$label, $memoNo, $docDate, $caseTitle, (string) $caseId, implode(' ', $itemNames)])),
    ];
}
$totalCases = count($caseRows);
$historySearch = trim((string) ($_GET['search'] ?? ''));
$initialNeedle = historySearchText($historySearch);
$initialVisibleCount = $initialNeedle === '' ? $totalCases : count(array_filter($caseRows,
    static fn(array $row): bool => mb_strpos($row['search'], $initialNeedle, 0, 'UTF-8') !== false
));
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ค้นหาและจัดการประวัติชุดเอกสาร TOR">
    <title>ประวัติเอกสาร | TOR Report</title>
    <link rel="stylesheet" href="assets/history-blue.css?v=20261124-1">
</head>
<body class="torskp-history-body">
<?php require __DIR__ . '/components/navbar.php'; ?>
<main class="history-workspace" id="main-content">
    <header class="history-head">
        <div class="history-head-copy">
            <span class="history-eyebrow">DOCUMENT HISTORY</span>
            <h1>ประวัติเอกสาร</h1>
            <p>ค้นหาและกลับไปทำเอกสารเดิมได้ทันที · แสดงชุดที่แก้ไขล่าสุดก่อน</p>
        </div>
        <a class="history-create" href="principle_approval_memo.php"><span aria-hidden="true">＋</span> สร้างชุดใหม่</a>
    </header>

    <?php if (($_GET['deleted'] ?? '') === '1'): ?>
        <div class="history-feedback is-success" role="status">ลบชุดเอกสารเรียบร้อยแล้ว</div>
    <?php endif; ?>
    <?php if ($deleteError !== ''): ?>
        <div class="history-feedback is-error" role="alert"><?= h($deleteError) ?></div>
    <?php endif; ?>

    <section class="history-pane" aria-labelledby="history-pane-title">
        <div class="history-toolbar">
            <div class="history-title-area">
                <h2 id="history-pane-title">ชุดเอกสารจัดซื้อ</h2>
                <span class="history-stat"><?= h(historyThaiDigits((string) $totalCases)) ?> ชุด</span>
                <span class="history-complete-stat">สำเร็จ <?= h(historyThaiDigits((string) $totalComplete)) ?> ชุด</span>
            </div>
            <form id="history-search-form" action="document_history.php" method="get" class="history-controls" role="search">
                <label class="history-search-label" for="history-search"><span class="sr-only">ค้นหาชุดเอกสาร</span>
                    <span aria-hidden="true" class="history-search-icon">⌕</span>
                    <input id="history-search" type="search" name="search" autocomplete="off"
                        value="<?= h($historySearch) ?>" placeholder="ค้นหาเลขที่หนังสือ วันที่ ชื่อยา...">
                </label>
                <label class="history-status-label" for="history-status"><span class="sr-only">กรองสถานะ</span>
                    <select id="history-status"><option value="all">ทุกสถานะ</option><option value="progress">กำลังดำเนินการ</option><option value="complete">ครบ ๖ ขั้นตอน</option></select>
                </label>
                <button class="history-search-submit" type="submit">ค้นหา</button>
                <a href="document_history.php" class="history-clear" id="history-clear">ล้าง</a>
            </form>
        </div>
        <div class="history-table-head" aria-hidden="true"><span>ชุดเอกสาร / รายการยา</span><span>ความคืบหน้า</span><span>ดำเนินการ</span></div>
        <div class="history-rows" id="history-rows">
            <?php foreach ($caseRows as $row): ?>
                <?php
                $id = $row['id'];
                $done = $row['completed_count'] === count($documentSteps);
                $nextStep = $row['next_step'];
                $firstHref = $documentSteps['principle_approval']['page'] . '?case_id=' . $id;
                $continueHref = ($nextStep['page'] ?? $documentSteps['principle_approval']['page']) . '?case_id=' . $id;
                $isInitiallyHidden = $initialNeedle !== '' && mb_strpos($row['search'], $initialNeedle, 0, 'UTF-8') === false;
                ?>
                <article class="history-row" data-history-row data-search="<?= h($row['search']) ?>"
                    data-state="<?= $done ? 'complete' : 'progress' ?>" <?= $isInitiallyHidden ? 'hidden' : '' ?>>
                    <div class="history-case-main">
                        <div class="history-case-number"><span class="history-dot" aria-hidden="true"></span><strong><?= h($row['label']) ?></strong><span class="history-case-date"><?= $row['doc_date'] !== '' ? 'วันที่ ' . h($row['doc_date']) : 'ยังไม่ระบุวันที่' ?></span></div>
                        <?php if ($row['title'] !== ''): ?><div class="history-case-title"><?= h($row['title']) ?></div><?php endif; ?>
                        <div class="history-case-details-line">
                            <span><?= h(historyThaiDigits((string) $row['items_count'])) ?> รายการยา</span>
                            <?php if ($row['item_names']): ?><span class="history-medicine-preview" title="<?= h(implode(', ', $row['item_names'])) ?>"><?= h(implode(', ', array_slice($row['item_names'], 0, 3))) ?><?= count($row['item_names']) > 3 ? ' และอื่น ๆ' : '' ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="history-row-progress">
                        <span class="history-progress-label <?= $done ? 'is-done' : '' ?>"><?= $done ? 'ครบทุกหัวข้อ' : 'ดำเนินการ ' . h(historyThaiDigits((string) $row['completed_count'])) . '/๖' ?></span>
                        <span class="history-progress-track"><span style="width:<?= (int) round($row['completed_count'] * 100 / count($documentSteps)) ?>%"></span></span>
                    </div>
                    <div class="history-row-actions">
                        <a href="<?= h($continueHref) ?>" class="history-continue"><?= $done ? 'เปิดชุดเอกสาร' : 'ทำต่อ' ?><span aria-hidden="true">↗</span></a>
                        <details class="history-case-expand"><summary>ทุกหัวข้อ <span aria-hidden="true">⌄</span></summary>
                            <div class="history-step-list">
                                <div class="history-step-heading">เปิดเอกสารรายหัวข้อ</div>
                                <?php foreach ($documentSteps as $indexKey => $step): ?>
                                    <?php $stepDone = !empty($row['completion'][$indexKey]); ?>
                                    <a class="history-step-link" href="<?= h($step['page']) ?>?case_id=<?= $id ?>">
                                        <span class="history-step-indicator <?= $stepDone ? 'is-done' : '' ?>"><?= $stepDone ? '✓' : h(historyThaiDigits((string) (array_search($indexKey, array_keys($documentSteps), true) + 1))) ?></span>
                                        <span><?= h($step['label']) ?></span>
                                        <span class="history-step-end" aria-hidden="true">↗</span>
                                    </a>
                                <?php endforeach; ?>
                                <button type="button" class="history-delete" data-delete-case="1"
                                    data-case-id="<?= $id ?>"
                                    data-case-label="<?= h($row['label']) ?>"
                                    data-delete-options="<?= h((string) json_encode($row['delete_options'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">ลบเอกสารในชุดนี้...</button>
                            </div>
                        </details>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="history-empty" id="history-empty" <?= $initialVisibleCount > 0 ? 'hidden' : '' ?>><?= $totalCases ? 'ไม่พบชุดเอกสารที่ตรงกับคำค้น' : 'ยังไม่มีประวัติเอกสาร' ?></div>
        <div class="history-footer">
            <span id="history-count" role="status" aria-live="polite">พบ <?= h(historyThaiDigits((string) $initialVisibleCount)) ?> จาก <?= h(historyThaiDigits((string) $totalCases)) ?> ชุด</span>
            <button type="button" id="history-more" class="history-more" hidden>แสดงรายการเพิ่มเติม</button>
        </div>
    </section>
    <p class="history-note">กด “ทำต่อ” เพื่อเปิดขั้นตอนที่ยังไม่ครบ หรือกด “ทุกหัวข้อ” เพื่อเลือกเอกสารเฉพาะรายการ</p>
</main>

<dialog id="delete_modal" class="history-modal" aria-labelledby="delete-modal-title">
    <div class="history-modal-head"><h2 id="delete-modal-title">เลือกรายการที่ต้องการลบ</h2><button type="button" id="cancel_delete_top" class="history-modal-close" aria-label="ปิด">×</button></div>
    <p class="history-modal-case">ชุดเอกสาร: <strong id="delete_case_label"></strong></p>
    <form method="post" action="document_history.php" id="delete_form">
        <input type="hidden" name="action" value="delete_selection">
        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="delete_mode" id="delete_mode" value="documents">
        <input type="hidden" name="case_id" id="delete_case_id" value="">
        <label class="history-delete-all-option"><input type="checkbox" id="delete_all_case"><span><strong>ลบทั้งชุดเอกสาร</strong><small>ลบทุกหัวข้อและประวัติ TOR ที่ผูกกับชุดนี้</small></span></label>
        <div class="history-delete-parts"><strong>หรือลบเฉพาะหัวข้อ</strong><div id="delete_document_options"></div></div>
        <p class="history-delete-warning">การลบไม่สามารถย้อนกลับได้ กรุณาตรวจสอบก่อนยืนยัน</p>
        <div class="history-modal-actions"><button type="button" id="cancel_delete" class="history-modal-cancel">ยกเลิก</button><button type="submit" id="confirm_delete" class="history-modal-submit" disabled>ยืนยันการลบ</button></div>
    </form>
</dialog>
<script>
(() => {
    'use strict';
    const rows = Array.from(document.querySelectorAll('[data-history-row]'));
    const search = document.getElementById('history-search');
    const status = document.getElementById('history-status');
    const form = document.getElementById('history-search-form');
    const count = document.getElementById('history-count');
    const empty = document.getElementById('history-empty');
    const more = document.getElementById('history-more');
    const clear = document.getElementById('history-clear');
    const pageSize = 15;
    let visibleLimit = pageSize;
    const toAscii = {'๐':'0','๑':'1','๒':'2','๓':'3','๔':'4','๕':'5','๖':'6','๗':'7','๘':'8','๙':'9'};
    const norm = (value) => String(value || '').toLowerCase().replace(/[๐-๙]/g, digit => toAscii[digit]);
    const thai = (value) => String(value).replace(/[0-9]/g, digit => '๐๑๒๓๔๕๖๗๘๙'[Number(digit)]);
    function filterRows() {
        const needle = norm(search.value.trim());
        const selected = status.value;
        let matched = 0;
        rows.forEach(row => {
            const show = (!needle || row.dataset.search.includes(needle)) && (selected === 'all' || row.dataset.state === selected);
            if (show) matched++;
            row.hidden = !show || matched > visibleLimit;
            if (!show) row.querySelectorAll('details').forEach(detail => detail.open = false);
        });
        count.textContent = 'พบ ' + thai(matched) + ' จาก ' + thai(rows.length) + ' ชุด';
        empty.hidden = matched !== 0;
        empty.textContent = rows.length ? 'ไม่พบชุดเอกสารที่ตรงกับตัวกรอง' : 'ยังไม่มีประวัติเอกสาร';
        more.hidden = matched <= visibleLimit;
        clear.hidden = needle === '' && selected === 'all';
    }
    search.addEventListener('input', () => { visibleLimit = pageSize; filterRows(); });
    status.addEventListener('change', () => { visibleLimit = pageSize; filterRows(); });
    form.addEventListener('submit', event => { event.preventDefault(); visibleLimit = pageSize; filterRows(); });
    more.addEventListener('click', () => { visibleLimit += pageSize; filterRows(); });
    clear.addEventListener('click', event => {
        event.preventDefault(); search.value = ''; status.value = 'all'; visibleLimit = pageSize;
        filterRows();
        if (window.history && window.history.replaceState) window.history.replaceState(null, '', 'document_history.php');
        search.focus();
    });
    filterRows();

    const modal = document.getElementById('delete_modal');
    const deleteForm = document.getElementById('delete_form');
    const deleteMode = document.getElementById('delete_mode');
    const deleteCaseId = document.getElementById('delete_case_id');
    const deleteCaseLabel = document.getElementById('delete_case_label');
    const deleteAllCase = document.getElementById('delete_all_case');
    const deleteOptions = document.getElementById('delete_document_options');
    const confirmDelete = document.getElementById('confirm_delete');
    function syncDeleteMode() {
        const all = deleteAllCase.checked;
        deleteMode.value = all ? 'case' : 'documents';
        const boxes = Array.from(deleteOptions.querySelectorAll('input[type="checkbox"]'));
        boxes.forEach(box => { box.disabled = all || box.dataset.available !== '1'; if (all) box.checked = false; });
        confirmDelete.disabled = !all && !boxes.some(box => box.checked);
    }
    function renderDeleteOptions(options) {
        deleteOptions.replaceChildren();
        Object.entries(options || {}).forEach(([type, option]) => {
            const label = document.createElement('label');
            const input = document.createElement('input');
            input.type = 'checkbox'; input.name = 'document_types[]'; input.value = type;
            input.dataset.available = option.available ? '1' : '0'; input.disabled = !option.available;
            input.addEventListener('change', syncDeleteMode);
            const description = document.createElement('span');
            description.textContent = option.label + (option.available ? '' : ' (ยังไม่มีเอกสาร)');
            label.append(input, description);
            deleteOptions.appendChild(label);
        });
    }
    document.querySelectorAll('[data-delete-case="1"]').forEach(button => {
        button.addEventListener('click', () => {
            deleteCaseId.value = button.dataset.caseId || '';
            deleteCaseLabel.textContent = button.dataset.caseLabel || '';
            deleteAllCase.checked = false;
            try { renderDeleteOptions(JSON.parse(button.dataset.deleteOptions || '{}')); }
            catch (_) { renderDeleteOptions({}); }
            syncDeleteMode();
            modal.showModal();
        });
    });
    deleteAllCase.addEventListener('change', syncDeleteMode);
    document.getElementById('cancel_delete').addEventListener('click', () => modal.close());
    document.getElementById('cancel_delete_top').addEventListener('click', () => modal.close());
    modal.addEventListener('click', event => { if (event.target === modal) modal.close(); });
    deleteForm.addEventListener('submit', event => {
        if (deleteMode.value === 'case') {
            if (!window.confirm('ยืนยันลบทั้งชุดเอกสารนี้และประวัติที่เกี่ยวข้อง?')) event.preventDefault();
        } else if (!deleteOptions.querySelector('input[type="checkbox"]:checked')) {
            event.preventDefault();
        }
    });
})();
</script>
</body>
</html>
