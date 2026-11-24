<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';
requireLogin();

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }

$workflows = [
    [
        'title' => 'จัดซื้อยาในบัญชี GPO',
        'enabled' => false,
        'documents' => [],
    ],
    [
        'title' => 'จัดซื้อยาในบัญชี (บริษัท)',
        'enabled' => true,
        'documents' => [
            ['title' => 'บันทึกข้อความ ขออนุมัติในหลักการ', 'href' => 'principle_approval_memo.php'],
            ['title' => 'บันทึกข้อความ แจ้งคำสั่งและเชิญประชุมแต่งตั้งคณะกรรมการร่างขอบเขต และวาระการประชุม', 'href' => 'committee_scope_invitation.php'],
            ['title' => 'บันทึกข้อความ ขออนุมัติใช้รายละเอียดคุณลักษณะเฉพาะและราคากลาง', 'href' => 'scope_price_approval_memo.php'],
            ['title' => 'เอกสาร TOR', 'href' => 'tor_company_purchase.php', 'note' => 'ออกเอกสาร TOR จัดซื้อผ่านบริษัท'],
            ['title' => 'บันทึกข้อความ รายงานผลการพิจารณากำหนดรายละเอียดคุณลักษณะเฉพาะ', 'href' => 'committee_scope_result_memo.php'],
            ['title' => 'รายงานการประชุม', 'href' => 'meeting_report.php'],
        ],
    ],
    [
        'title' => 'จัดซื้อยา นอกบัญชี GPO',
        'enabled' => false,
        'documents' => [],
    ],
    [
        'title' => 'จัดซื้อยา นอกบัญชี (บริษัท)',
        'enabled' => false,
        'documents' => [],
    ],
];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard ออกเอกสาร TOR</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100">
<?php require __DIR__ . '/components/navbar.php'; ?>
<main class="mx-auto max-w-6xl px-4 py-7 sm:px-6 sm:py-9">
    <section class="overflow-hidden rounded-lg border border-zinc-800 bg-zinc-900/80">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-zinc-800 px-5 py-6 sm:px-7">
            <div>
                <p class="text-sm font-semibold text-emerald-300">ระบบออกเอกสารจัดซื้อ</p>
                <h1 class="mt-1 text-3xl font-bold">เริ่มจัดทำเอกสาร TOR</h1>
                <p class="mt-2 max-w-2xl text-sm text-zinc-400">เลือกประเภทการจัดซื้อ แล้วดำเนินการตามหัวข้อที่ ๑–๖ ข้อมูลของแต่ละชุดจะเชื่อมถึงกันอัตโนมัติ</p>
            </div>
            <a href="document_history.php" class="rounded-md border border-zinc-700 bg-white px-4 py-2.5 text-sm font-semibold text-zinc-200 hover:border-indigo-400">เปิดชุดเอกสารเดิม</a>
        </div>

        <div class="grid gap-4 p-5 sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3">
                <div>
                    <div class="text-sm font-semibold text-indigo-900">ประเภทงานที่พร้อมใช้งาน</div>
                    <div class="mt-0.5 text-xs text-indigo-600">ขณะนี้เปิดระบบจัดซื้อยาในบัญชีผ่านบริษัท</div>
                </div>
                <span class="ui-stat-chip"><strong class="text-indigo-600">1</strong> จาก 4 ประเภท</span>
            </div>
            <?php foreach ($workflows as $workflowIndex => $workflow): ?>
                <div class="workflow-card overflow-hidden rounded-xl border <?= $workflow['enabled'] ? 'border-emerald-500/50 bg-white' : 'border-zinc-800 bg-zinc-950/70' ?>">
                    <div class="flex items-center gap-3 px-4 py-4 sm:px-5">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= $workflow['enabled'] ? 'bg-emerald-500 text-white' : 'bg-zinc-800 text-zinc-400' ?> text-sm font-bold shadow-sm">
                            <?= $workflowIndex + 1 ?>
                        </div>
                        <button type="button" class="workflow-toggle flex flex-1 items-center justify-between gap-3 text-left font-semibold <?= $workflow['enabled'] ? 'text-emerald-100' : 'text-zinc-400' ?>" aria-expanded="<?= $workflow['enabled'] ? 'true' : 'false' ?>">
                            <span><span class="block"><?= h($workflow['title']) ?></span><span class="mt-0.5 block text-xs font-normal text-zinc-500"><?= $workflow['enabled'] ? 'พร้อมจัดทำเอกสาร ๖ ขั้นตอน' : 'ยังไม่เปิดใช้งาน' ?></span></span>
                            <span class="workflow-icon rounded-lg bg-slate-100 px-2.5 py-1 text-xs text-zinc-500"><?= $workflow['enabled'] ? 'ซ่อน' : 'ดู' ?></span>
                        </button>
                        <?php if (!$workflow['enabled']): ?>
                            <span class="rounded-md border border-zinc-700 px-2 py-1 text-xs text-zinc-500">ยังไม่ลิงก์</span>
                        <?php endif; ?>
                    </div>

                    <div class="workflow-panel <?= $workflow['enabled'] ? '' : 'hidden' ?> border-t <?= $workflow['enabled'] ? 'border-emerald-500/30' : 'border-zinc-800' ?>">
                    <?php if ($workflow['enabled']): ?>
                        <div class="divide-y divide-zinc-800">
                            <?php foreach ($workflow['documents'] as $index => $document): ?>
                                <div class="grid gap-3 px-4 py-4 md:grid-cols-[2.25rem_1fr_auto] md:items-center sm:px-5">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full border border-indigo-200 bg-indigo-50 text-sm font-bold text-indigo-600"><?= strtr((string) ($index + 1), ['0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔','5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙']) ?></div>
                                    <div>
                                        <div class="font-medium text-zinc-100"><?= h($document['title']) ?></div>
                                        <?php if (!empty($document['note'])): ?>
                                            <div class="mt-1 text-sm text-emerald-300"><?= h($document['note']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= h($document['href']) ?>" class="inline-flex justify-center rounded-md border border-emerald-500/60 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-200 hover:bg-emerald-500/10">
                                        เริ่มทำหัวข้อนี้
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="px-4 py-4 text-sm text-zinc-500">ยังไม่เปิดใช้งานในขั้นตอนนี้</div>
                    <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>
<script>
document.querySelectorAll('.workflow-toggle').forEach((button) => {
    button.addEventListener('click', () => {
        const card = button.closest('.workflow-card');
        const panel = card?.querySelector('.workflow-panel');
        const icon = button.querySelector('.workflow-icon');
        if (!panel) return;

        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        panel.classList.toggle('hidden', expanded);
        if (icon) icon.textContent = expanded ? 'ดู' : 'ซ่อน';
    });
});
</script>
</body>
</html>
