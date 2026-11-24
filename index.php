<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';
requireLogin();

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Keep the original workflow routes, their order, and availability unchanged.
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
$activeWorkflow = null;
$inactiveWorkflows = [];
foreach ($workflows as $workflow) {
    if ($workflow['enabled'] && $activeWorkflow === null) {
        $activeWorkflow = $workflow;
    } elseif (!$workflow['enabled']) {
        $inactiveWorkflows[] = $workflow;
    }
}
$thaiNumbers = ['0'=>'๐', '1'=>'๑', '2'=>'๒', '3'=>'๓', '4'=>'๔', '5'=>'๕', '6'=>'๖', '7'=>'๗', '8'=>'๘', '9'=>'๙'];
$documents = $activeWorkflow['documents'] ?? [];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>หน้าหลัก | ระบบจัดทำเอกสาร TOR</title>
    <meta name="description" content="หน้าหลักระบบจัดทำเอกสาร TOR และเอกสารจัดซื้อจัดจ้าง">
    <link rel="stylesheet" href="assets/home-blue.css?v=20261124-2">
</head>
<body class="torskp-home-body">
<?php require __DIR__ . '/components/navbar.php'; ?>
<main class="torskp-home" id="main-content">
    <section class="home-hero hero" aria-labelledby="home-title">
        <div class="home-hero-content">
            <div class="home-eyebrow"><span class="home-eyebrow-dot" aria-hidden="true"></span> TOR REPORT · DOCUMENT WORKSPACE</div>
            <h1 id="home-title">ระบบจัดทำเอกสาร TOR</h1>
            <p>เริ่มงานจัดซื้อหรือกลับไปทำชุดเอกสารเดิม จัดลำดับเอกสารตามกระบวนงานทั้ง <?= h(strtr((string) count($documents), $thaiNumbers)) ?> ขั้นตอนในหน้าเดียว</p>
            <div class="home-hero-actions">
                <a class="home-button home-button-primary" href="<?= h($documents[0]['href'] ?? 'document_history.php') ?>">
                    <span aria-hidden="true">＋</span> เริ่มจัดทำเอกสาร
                </a>
                <a class="home-button home-button-outline" href="document_history.php">เปิดชุดเอกสารเดิม <span aria-hidden="true">↗</span></a>
            </div>
        </div>
        <div class="home-hero-decor" aria-hidden="true"><span></span><span></span><span></span></div>
    </section>

    <section class="home-workflow" aria-labelledby="workflow-title">
        <div class="home-workflow-head">
            <div class="home-workflow-heading">
                <div class="home-section-kicker">ประเภทงานที่พร้อมใช้งาน</div>
                <h2 id="workflow-title"><?= h($activeWorkflow['title'] ?? 'เอกสารจัดซื้อ') ?></h2>
                <p>เลือกเอกสารที่ต้องการจัดทำตามขั้นตอน ข้อมูลแต่ละชุดสามารถเชื่อมโยงกันผ่านหมายเลขชุดเอกสาร</p>
            </div>
            <a href="document_history.php" class="home-history-link">ประวัติเอกสาร <span aria-hidden="true">↗</span></a>
        </div>
        <?php if ($documents): ?>
            <ol class="home-stage-list" aria-label="รายการเอกสารตามขั้นตอน">
                <?php foreach ($documents as $index => $document): ?>
                    <li class="home-stage<?= $index === 0 ? ' home-stage-first' : '' ?>">
                        <span class="home-stage-num" aria-label="ขั้นตอน <?= $index + 1 ?>"><?= h(strtr((string) ($index + 1), $thaiNumbers)) ?></span>
                        <div class="home-stage-text">
                            <div class="home-stage-title"><?= h($document['title']) ?></div>
                            <?php if (!empty($document['note'])): ?><div class="home-stage-note"><?= h($document['note']) ?></div><?php endif; ?>
                        </div>
                        <a class="home-stage-action" href="<?= h($document['href']) ?>" aria-label="เปิดขั้นตอน <?= $index + 1 ?>: <?= h($document['title']) ?>">
                            เปิดเอกสาร <span aria-hidden="true">↗</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <div class="home-no-workflow">ยังไม่มีประเภทงานที่เปิดให้ใช้งาน</div>
        <?php endif; ?>
    </section>

    <section class="home-upcoming" aria-labelledby="upcoming-title">
        <div class="home-upcoming-title" id="upcoming-title">ประเภทงานอื่น <span class="home-upcoming-count"><?= h(strtr((string) count($inactiveWorkflows), $thaiNumbers)) ?> รายการ</span></div>
        <div class="home-upcoming-list">
            <?php foreach ($inactiveWorkflows as $workflow): ?>
                <span class="home-upcoming-item"><span class="home-upcoming-dot" aria-hidden="true"></span><?= h($workflow['title']) ?><span class="home-upcoming-status">ยังไม่เปิดใช้งาน</span></span>
            <?php endforeach; ?>
        </div>
    </section>
    <footer class="home-footer">ระบบจัดทำเอกสาร TOR · เลือกขั้นตอนตามลำดับ หรือเปิดงานเดิมจากประวัติเอกสาร</footer>
</main>
</body>
</html>
