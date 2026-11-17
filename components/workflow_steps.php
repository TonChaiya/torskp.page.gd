<?php
declare(strict_types=1);

$workflowCurrentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$workflowCaseId = (int) ($_REQUEST['case_id'] ?? 0);
$workflowCompletion = [];
if ($workflowCaseId > 0 && function_exists('procurementCaseCompletion')) {
    try {
        $workflowCompletion = procurementCaseCompletion($workflowCaseId);
    } catch (Throwable $e) {
        $workflowCompletion = [];
    }
}
$workflowSteps = [
    ['page' => 'principle_approval_memo.php', 'key' => 'principle_approval', 'label' => 'อนุมัติหลักการ'],
    ['page' => 'committee_scope_invitation.php', 'key' => 'committee_scope_invitation', 'label' => 'เชิญกรรมการ'],
    ['page' => 'tor_company_purchase.php', 'key' => 'tor_company_purchase', 'label' => 'จัดทำ TOR'],
    ['page' => 'meeting_report.php', 'key' => 'meeting_report', 'label' => 'รายงานประชุม'],
];
?>
<div class="app-workflow no-print" aria-label="ลำดับการจัดทำเอกสาร">
    <div class="app-workflow-inner">
        <div class="app-workflow-label">
            <span>ลำดับงาน</span>
            <?php if ($workflowCaseId > 0): ?><small>ชุดเอกสาร #<?= $workflowCaseId ?></small><?php endif; ?>
        </div>
        <div class="app-workflow-steps">
            <?php foreach ($workflowSteps as $workflowIndex => $workflowStep): ?>
                <?php
                $workflowActive = $workflowCurrentPage === $workflowStep['page'];
                $workflowDone = !empty($workflowCompletion[$workflowStep['key']]);
                $workflowHref = $workflowStep['page'] . ($workflowCaseId > 0 ? '?case_id=' . $workflowCaseId : '');
                $workflowClass = 'app-workflow-step' . ($workflowActive ? ' is-active' : '') . ($workflowDone ? ' is-done' : '');
                ?>
                <a href="<?= htmlspecialchars($workflowHref, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($workflowClass, ENT_QUOTES, 'UTF-8') ?>" <?= $workflowActive ? 'aria-current="step"' : '' ?>>
                    <span class="app-workflow-number"><?= $workflowDone && !$workflowActive ? '✓' : $workflowIndex + 1 ?></span>
                    <span><?= htmlspecialchars($workflowStep['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
