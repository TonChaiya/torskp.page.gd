<?php
declare(strict_types=1);

$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$navItems = [
    'index.php' => 'หน้าแรก',
    'document_history.php' => 'ประวัติเอกสาร',
];
$user = function_exists('currentUser') ? currentUser() : null;
if (($user['role'] ?? '') === 'admin') {
    $navItems['admin.php'] = 'จัดการระบบ';
}
$navItems['change_password.php'] = 'จัดการบัญชี';
$navUsername = trim((string) ($user['username'] ?? ''));
$navUserInitial = function_exists('mb_substr') ? mb_substr($navUsername, 0, 1, 'UTF-8') : substr($navUsername, 0, 1);
if ($navUserInitial === '') $navUserInitial = 'U';
?>
<link rel="stylesheet" href="assets/app-theme.css?v=25690821-2">
<link rel="stylesheet" href="assets/nav-blue.css?v=20261124-1">
<script>document.body.classList.add('app-themed');</script>
<nav class="app-nav torskp-nav no-print" aria-label="เมนูหลัก">
    <div class="torskp-nav-inner">
        <a href="index.php" class="app-brand torskp-nav-brand" aria-label="TOR Report - หน้าแรก">
            <span class="app-brand-mark torskp-brand-mark" aria-hidden="true">T</span>
            <span class="torskp-brand-text"><span class="torskp-brand-main">TOR <strong>Report</strong></span><small>ระบบจัดทำเอกสาร</small></span>
        </a>
        <div class="torskp-nav-links">
            <?php foreach ($navItems as $href => $label): ?>
                <?php $isActive = $currentPage === $href; ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="app-nav-link torskp-nav-link<?= $isActive ? ' is-active' : '' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="torskp-nav-account">
            <?php if ($user): ?>
                <span class="torskp-nav-user" title="บัญชีที่กำลังใช้งาน">
                    <span class="torskp-nav-avatar" aria-hidden="true"><?= htmlspecialchars($navUserInitial, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="torskp-nav-username"><?= htmlspecialchars($navUsername, ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <form method="post" action="logout.php" class="torskp-nav-logout-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="app-logout torskp-nav-logout">ออกจากระบบ</button>
                </form>
            <?php endif; ?>
        </div>
        <details class="torskp-nav-menu">
            <summary aria-label="เปิดเมนูนำทาง"><span class="torskp-menu-bars" aria-hidden="true"><i></i><i></i><i></i></span><span>เมนู</span></summary>
            <div class="torskp-nav-menu-panel">
                <?php foreach ($navItems as $href => $label): ?>
                    <?php $isActive = $currentPage === $href; ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="torskp-nav-mobile-link<?= $isActive ? ' is-active' : '' ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
                <?php if ($user): ?>
                    <div class="torskp-nav-menu-footer">
                        <span class="torskp-nav-mobile-user"><?= htmlspecialchars($navUsername, ENT_QUOTES, 'UTF-8') ?></span>
                        <form method="post" action="logout.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="torskp-nav-mobile-logout">ออกจากระบบ</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </details>
    </div>
</nav>
