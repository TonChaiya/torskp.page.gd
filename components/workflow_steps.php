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
    ['page' => 'scope_price_approval_memo.php', 'key' => 'scope_price_approval_memo', 'label' => 'ขออนุมัติใช้ TOR'],
    ['page' => 'tor_company_purchase.php', 'key' => 'tor_company_purchase', 'label' => 'จัดทำ TOR'],
    ['page' => 'committee_scope_result_memo.php', 'key' => 'committee_scope_result_memo', 'label' => 'รายงานผล'],
    ['page' => 'meeting_report.php', 'key' => 'meeting_report', 'label' => 'รายงานประชุม'],
];
?>
<style>
/* Six steps, without changing the site's original stylesheet. */
.app-workflow-steps { grid-template-columns: repeat(6, minmax(0, 1fr)); }
@media (max-width: 1260px) {
  .app-workflow-steps { display:flex; overflow-x:auto; scrollbar-width:thin; }
  .app-workflow-step { min-width:max-content; }
}
</style>
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
                    <span class="app-workflow-number"><?= $workflowDone && !$workflowActive ? '✓' : strtr((string) ($workflowIndex + 1), ['0'=>'๐','1'=>'๑','2'=>'๒','3'=>'๓','4'=>'๔','5'=>'๕','6'=>'๖','7'=>'๗','8'=>'๘','9'=>'๙']) ?></span>
                    <span><?= htmlspecialchars($workflowStep['label'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
