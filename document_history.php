<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/procurement_case.php';
require __DIR__ . '/core/auth.php';
requireLogin();

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function historySearchText(string $text): string
{
    return mb_strtolower(strtr($text, [
        '๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4',
        '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9',
    ]), 'UTF-8');
}

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

$cases = procurementCases();
$historySearch = trim((string) ($_GET['search'] ?? ''));
if ($historySearch !== '') {
    $needle = historySearchText($historySearch);
    $cases = array_values(array_filter($cases, static function (array $case) use ($needle): bool {
        $items = json_decode((string) ($case['items_json'] ?? '[]'), true);
        $itemNames = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $itemNames[] = (string) ($item['name'] ?? '');
        }
        $haystack = implode(' ', [
            (string) ($case['principle_memo_no_suffix'] ?? ''),
            (string) ($case['principle_doc_date'] ?? ''),
            implode(' ', $itemNames),
        ]);
        return mb_strpos(historySearchText($haystack), $needle) !== false;
    }));
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ประวัติการออกเอกสาร</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100">
<?php require __DIR__ . '/components/navbar.php'; ?>
<main class="mx-auto max-w-6xl px-4 py-8">
    <section class="rounded-lg border border-zinc-800 bg-zinc-900/80">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-800 px-5 py-6 sm:px-7">
            <div>
                <p class="text-sm font-semibold text-emerald-300">ชุดเอกสารทั้งหมด</p>
                <h1 class="mt-1 text-3xl font-bold">ประวัติการออกเอกสาร</h1>
                <p class="mt-2 text-sm text-zinc-400">ค้นหา เปิดแก้ไข และตรวจความครบถ้วนของเอกสารทั้ง ๖ หัวข้อ</p>
            </div>
            <a href="principle_approval_memo.php" class="rounded-md bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">+ สร้างชุดเอกสารใหม่</a>
        </div>
        <?php if (($_GET['deleted'] ?? '') === '1'): ?>
            <div class="mx-5 mt-4 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-100">ลบชุดเอกสารเรียบร้อยแล้ว</div>
        <?php endif; ?>
        <?php if ($deleteError !== ''): ?>
            <div class="mx-5 mt-4 rounded-md border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm text-red-100"><?= h($deleteError) ?></div>
        <?php endif; ?>
        <form method="get" class="grid gap-3 border-b border-zinc-800 bg-slate-50/70 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:px-7">
            <label>
                <span class="sr-only">ค้นหาชุดเอกสาร</span>
                <input name="search" value="<?= h($historySearch) ?>" placeholder="ค้นหาเลขที่หนังสือ วันที่ หรือชื่อยา" class="w-full rounded-md border border-zinc-700 bg-white px-3 py-2 outline-none">
            </label>
            <div class="flex gap-2">
                <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">ค้นหา</button>
                <?php if ($historySearch !== ''): ?><a href="document_history.php" class="rounded-md border border-zinc-700 px-4 py-2 text-sm">ล้าง</a><?php endif; ?>
            </div>
        </form>
        <div class="divide-y divide-zinc-800">
            <?php if (!$cases): ?>
                <div class="px-5 py-10 text-center text-sm text-zinc-400"><?= $historySearch !== '' ? 'ไม่พบชุดเอกสารที่ตรงกับคำค้น' : 'ยังไม่มีประวัติเอกสาร' ?></div>
            <?php endif; ?>
            <?php foreach ($cases as $case): ?>
                <?php
                $id = (int) $case['id'];
                $fullCase = procurementCase($id);
                $itemsCount = count($fullCase['items'] ?? []);
                $completion = procurementCaseCompletion($id);
                $completedCount = count(array_filter($completion));
                $deleteOptions = [
                    'principle_approval' => ['label' => 'หัวข้อ 1', 'available' => $completion['principle_approval']],
                    'committee_scope_invitation' => ['label' => 'หัวข้อ 2', 'available' => $completion['committee_scope_invitation']],
                    'scope_price_approval_memo' => ['label' => 'หัวข้อ ๓', 'available' => $completion['scope_price_approval_memo']],
                    'tor_company_purchase' => ['label' => 'หัวข้อ ๔', 'available' => $completion['tor_company_purchase']],
                    'committee_scope_result_memo' => ['label' => 'หัวข้อ ๕', 'available' => $completion['committee_scope_result_memo']],
                    'meeting_report' => ['label' => 'หัวข้อ ๖', 'available' => $completion['meeting_report']],
                ];
                $memoNo = trim((string) ($case['principle_memo_no_suffix'] ?? ''));
                $docDate = trim((string) ($case['principle_doc_date'] ?? ''));
                $caseLabel = "ชม.๕๑๐๐๖.๑๓.๑/{$memoNo} ลงวันที่ {$docDate}";
                ?>
                <div class="grid gap-4 px-5 py-5 transition hover:bg-indigo-50/40 md:grid-cols-[minmax(0,1fr)_auto] md:items-center sm:px-7">
                    <div>
                        <div class="font-medium text-zinc-100">
                            <?= h($caseLabel) ?> ซื้อยา <?= h((string) $itemsCount) ?> รายการ
                        </div>
                        <div class="mt-2 flex items-center gap-3">
                            <div class="ui-progress-track max-w-52 flex-1"><div class="ui-progress-bar" style="width: <?= $completedCount * (100 / 6) ?>%"></div></div>
                            <div class="text-xs font-semibold <?= $completedCount === 6 ? 'text-emerald-300' : 'text-indigo-600' ?>">ครบ <?= h((string) $completedCount) ?>/๖</div>
                        </div>
                    </div>
                    <div class="flex flex-nowrap gap-2 overflow-x-auto text-sm">
                        <a href="principle_approval_memo.php?case_id=<?= $id ?>" class="whitespace-nowrap rounded-md border <?= $completion['principle_approval'] ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-zinc-700' ?> px-3 py-2 hover:border-emerald-400">1 อนุมัติ</a>
                        <a href="committee_scope_invitation.php?case_id=<?= $id ?>" class="whitespace-nowrap rounded-md border <?= $completion['committee_scope_invitation'] ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-zinc-700' ?> px-3 py-2 hover:border-emerald-400">2 เชิญกรรมการ</a>
                        <a href="scope_price_approval_memo.php?case_id=<?= $id ?>" class="whitespace-nowrap rounded-md border <?= $completion['scope_price_approval_memo'] ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-zinc-700' ?> px-3 py-2 hover:border-emerald-400">๓ อนุมัติใช้ TOR</a>
                        <a href="tor_company_purchase.php?case_id=<?= $id ?>" class="whitespace-nowrap rounded-md border <?= $completion['tor_company_purchase'] ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-zinc-700' ?> px-3 py-2 hover:border-emerald-400">๔ TOR</a>
                        <a href="committee_scope_result_memo.php?case_id=<?= $id ?>" class="whitespace-nowrap rounded-md border <?= $completion['committee_scope_result_memo'] ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-zinc-700' ?> px-3 py-2 hover:border-emerald-400">๕ รายงานผล</a>
                        <a href="meeting_report.php?case_id=<?= $id ?>" class="whitespace-nowrap rounded-md border <?= $completion['meeting_report'] ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-zinc-700' ?> px-3 py-2 hover:border-emerald-400">๖ ประชุม</a>
                        <button
                            type="button"
                            class="whitespace-nowrap rounded-md border border-red-500/60 px-3 py-2 text-red-200 hover:bg-red-500/10"
                            data-delete-case="1"
                            data-case-id="<?= $id ?>"
                            data-case-label="<?= h($caseLabel) ?>"
                            data-delete-options="<?= h(json_encode($deleteOptions, JSON_UNESCAPED_UNICODE)) ?>"
                        >ลบชุดนี้</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<div id="delete_modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/70 p-4">
    <div class="w-full max-w-xl rounded-lg border border-red-500/70 bg-zinc-950 p-5 shadow-xl">
        <div class="text-lg font-semibold text-red-300">เลือกหัวข้อที่ต้องการลบ</div>
        <p class="mt-3 text-sm text-red-100">
            ชุดเอกสาร: <span id="delete_case_label" class="font-medium"></span>
        </p>
        <form method="post" action="document_history.php" class="mt-4">
            <input type="hidden" name="action" value="delete_selection">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="delete_mode" id="delete_mode" value="documents">
            <input type="hidden" name="case_id" id="delete_case_id" value="">

            <label class="flex items-start gap-3 rounded-md border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-100">
                <input type="checkbox" id="delete_all_case" class="mt-1 h-4 w-4 rounded border-zinc-600 bg-zinc-900">
                <span>
                    <span class="block font-medium">ลบทั้งชุด</span>
                    <span class="block text-xs text-red-100/80">ลบทุกหัวข้อของชุดนี้ รวมถึงประวัติ TOR ที่ผูกกับชุดนี้</span>
                </span>
            </label>

            <div class="mt-3 rounded-md border border-zinc-800 p-3">
                <div class="mb-2 text-sm font-medium text-zinc-200">หรือลบเฉพาะหัวข้อ</div>
                <div id="delete_document_options" class="grid gap-2"></div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="cancel_delete" class="rounded-md border border-zinc-700 px-3 py-2 text-sm text-zinc-200 hover:border-zinc-500">ยกเลิก</button>
                <button type="submit" id="confirm_delete" class="rounded-md border border-red-500/70 bg-red-500/20 px-3 py-2 text-sm font-medium text-red-100 hover:bg-red-500/30">ตกลงลบ</button>
            </div>
        </form>
    </div>
</div>

<script>
const deleteModal = document.getElementById('delete_modal');
const deleteCaseId = document.getElementById('delete_case_id');
const deleteCaseLabel = document.getElementById('delete_case_label');
const cancelDelete = document.getElementById('cancel_delete');
const deleteMode = document.getElementById('delete_mode');
const deleteAllCase = document.getElementById('delete_all_case');
const deleteDocumentOptions = document.getElementById('delete_document_options');
const confirmDelete = document.getElementById('confirm_delete');

function renderDeleteOptions(options) {
    deleteDocumentOptions.innerHTML = '';
    Object.entries(options || {}).forEach(([type, option]) => {
        const label = document.createElement('label');
        label.className = 'flex items-center gap-3 rounded-md border border-zinc-800 px-3 py-2 text-sm text-zinc-200';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'document_types[]';
        checkbox.value = type;
        checkbox.className = 'delete-document-option h-4 w-4 rounded border-zinc-600 bg-zinc-900';
        checkbox.dataset.available = option.available ? '1' : '0';
        checkbox.disabled = !option.available;

        const text = document.createElement('span');
        text.textContent = option.label + (option.available ? '' : ' (ยังไม่มีเอกสาร)');
        if (!option.available) text.className = 'text-zinc-500';

        label.append(checkbox, text);
        deleteDocumentOptions.appendChild(label);
    });
}

function syncDeleteMode() {
    const isCaseDelete = Boolean(deleteAllCase?.checked);
    deleteMode.value = isCaseDelete ? 'case' : 'documents';
    document.querySelectorAll('.delete-document-option').forEach((checkbox) => {
        checkbox.disabled = isCaseDelete || checkbox.dataset.available !== '1';
        if (isCaseDelete) checkbox.checked = false;
    });
}

document.querySelectorAll('[data-delete-case="1"]').forEach((button) => {
    button.addEventListener('click', () => {
        deleteCaseId.value = button.dataset.caseId || '';
        deleteCaseLabel.textContent = button.dataset.caseLabel || '';
        deleteAllCase.checked = false;
        renderDeleteOptions(JSON.parse(button.dataset.deleteOptions || '{}'));
        syncDeleteMode();
        deleteModal.classList.remove('hidden');
        deleteModal.classList.add('flex');
    });
});

function closeDeleteModal() {
    deleteModal.classList.add('hidden');
    deleteModal.classList.remove('flex');
}

cancelDelete?.addEventListener('click', closeDeleteModal);
deleteAllCase?.addEventListener('change', syncDeleteMode);
confirmDelete?.closest('form')?.addEventListener('submit', (event) => {
    if (deleteMode.value === 'case') return;
    const selected = document.querySelectorAll('.delete-document-option:checked');
    if (selected.length === 0) {
        event.preventDefault();
        alert('กรุณาเลือกหัวข้อที่ต้องการลบ หรือเลือก "ลบทั้งชุด"');
    }
});
deleteModal?.addEventListener('click', (event) => {
    if (event.target === deleteModal) closeDeleteModal();
});
</script>
</body>
</html>
