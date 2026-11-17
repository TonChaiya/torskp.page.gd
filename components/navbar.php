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
?>
<link rel="stylesheet" href="assets/app-theme.css?v=25690821-2">
<script>document.body.classList.add('app-themed');</script>
<nav class="app-nav no-print">
    <div class="app-nav-inner mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
        <a href="index.php" class="app-brand"><span class="app-brand-mark">T</span><span>TOR Report</span></a>
        <div class="app-nav-actions flex items-center gap-1.5">
            <?php foreach ($navItems as $href => $label): ?>
                <?php
                $isActive = $currentPage === $href;
                $class = 'app-nav-link' . ($isActive ? ' is-active' : '');
                ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($class, ENT_QUOTES, 'UTF-8') ?>" <?= $isActive ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
            <?php if ($user): ?>
                <span class="app-user-chip"><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></span>
                <a href="change_password.php" class="app-nav-link<?= $currentPage === 'change_password.php' ? ' is-active' : '' ?>">ตั้งค่าผู้ใช้</a>
                <form method="post" action="logout.php" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <button class="app-logout">ออกจากระบบ</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</nav>
