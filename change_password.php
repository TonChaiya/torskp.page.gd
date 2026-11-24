<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';
requireLogin();

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function settingPost(string $key, string $default = ''): string { return trim((string) ($_POST[$key] ?? $default)); }
function validUsername(string $username): bool { return preg_match('/^[\p{L}\p{N}._@-]{3,100}$/u', $username) === 1; }
function settingRedirect(string $status): never
{
    header('Location: change_password.php?status=' . rawurlencode($status));
    exit;
}

$user = currentUser();
$isAdmin = ($user['role'] ?? '') === 'admin';
$error = '';
$statusMessages = [
    'own_saved' => 'บันทึกข้อมูลบัญชีของคุณเรียบร้อยแล้ว',
    'user_saved' => 'บันทึกข้อมูลผู้ใช้เรียบร้อยแล้ว',
    'user_created' => 'เพิ่มผู้ใช้เรียบร้อยแล้ว',
    'user_deleted' => 'ลบผู้ใช้เรียบร้อยแล้ว',
    'approved' => 'อนุมัติสมาชิกเรียบร้อยแล้ว',
    'rejected' => 'ปฏิเสธคำขอสมัครสมาชิกแล้ว',
    'disabled' => 'ระงับสิทธิ์บัญชีเรียบร้อยแล้ว',
    'enabled' => 'เปิดใช้งานบัญชีเรียบร้อยแล้ว',
];
$message = $statusMessages[(string) ($_GET['status'] ?? '')] ?? '';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = settingPost('action');

        if ($action === 'save_own_account') {
            $username = settingPost('username');
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            if (!validUsername($username)) {
                throw new RuntimeException('ชื่อผู้ใช้ต้องยาว 3-100 ตัว และใช้ได้เฉพาะตัวอักษร ตัวเลข จุด ขีดกลาง ขีดล่าง หรือ @');
            }
            $stmt = db()->prepare('SELECT password_hash, must_change_password FROM users WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
            $account = $stmt->fetch();
            if (!$account || !password_verify($currentPassword, (string) $account['password_hash'])) {
                throw new RuntimeException('รหัสผ่านปัจจุบันไม่ถูกต้อง');
            }
            if ((int) $account['must_change_password'] === 1 && $newPassword === '') {
                throw new RuntimeException('บัญชีนี้ต้องกำหนดรหัสผ่านใหม่ก่อนใช้งานส่วนอื่น');
            }
            if ($newPassword !== '') {
                if ($newPassword !== $confirmPassword) throw new RuntimeException('การยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
                $policyError = passwordPolicyError($newPassword, $username);
                if ($policyError !== '') throw new RuntimeException($policyError);
                $stmt = db()->prepare('UPDATE users SET username = ?, password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?');
                $stmt->execute([$username, password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]);
            } else {
                $stmt = db()->prepare('UPDATE users SET username = ? WHERE id = ?');
                $stmt->execute([$username, (int) $user['id']]);
            }
            auditEvent('own_account_updated', (int) $user['id'], ['username_changed' => $username !== (string) $user['username'], 'password_changed' => $newPassword !== '']);
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            settingRedirect('own_saved');
        }

        if (!$isAdmin) {
            http_response_code(403);
            throw new RuntimeException('ไม่มีสิทธิ์จัดการบัญชีผู้ใช้อื่น');
        }

        // Approval workflow lives in the account center, not admin.php.
        if (in_array($action, ['review_registration', 'set_editor_status'], true)) {
            $targetId = (int) settingPost('account_id');
            $decision = settingPost('decision');
            if ($targetId <= 0 || $targetId === (int) $user['id']) {
                throw new RuntimeException('ไม่สามารถจัดการสถานะบัญชีนี้ได้');
            }
            $transitions = [
                'review_registration' => [
                    'approve' => ['pending', 'approved'],
                    'approve_rejected' => ['rejected', 'approved'],
                    'reject' => ['pending', 'rejected'],
                ],
                'set_editor_status' => [
                    'disable' => ['approved', 'disabled'],
                    'enable' => ['disabled', 'approved'],
                ],
            ];
            if (!isset($transitions[$action][$decision])) {
                throw new RuntimeException('คำสั่งอนุมัติบัญชีไม่ถูกต้อง');
            }
            [$fromStatus, $toStatus] = $transitions[$action][$decision];
            // Atomic status transition prevents repeated or stale approvals.
            $stmt = db()->prepare("UPDATE users SET account_status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND role = 'editor' AND account_status = ?");
            $stmt->execute([$toStatus, (int) $user['id'], $targetId, $fromStatus]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('สถานะบัญชีเปลี่ยนไปแล้ว กรุณาโหลดหน้าใหม่ก่อนดำเนินการ');
            }
            auditEvent('registration_account_status_changed', (int) $user['id'], [
                'target_user_id' => $targetId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ]);
            settingRedirect($toStatus === 'approved' ? ($decision === 'enable' ? 'enabled' : 'approved') : $toStatus);
        }

        if ($action === 'create_user') {
            $username = settingPost('new_username');
            $password = (string) ($_POST['new_user_password'] ?? '');
            $role = settingPost('new_role', 'editor');
            $fullName = settingPost('new_full_name');
            $department = settingPost('new_department');
            if (mb_strlen($fullName, 'UTF-8') > 150 || mb_strlen($department, 'UTF-8') > 255) {
                throw new RuntimeException('ชื่อ–นามสกุลหรือหน่วยงานยาวเกินที่กำหนด');
            }
            if (!validUsername($username)) throw new RuntimeException('ชื่อผู้ใช้ใหม่ไม่ถูกต้อง');
            if (!in_array($role, ['admin', 'editor'], true)) throw new RuntimeException('สิทธิ์ผู้ใช้ไม่ถูกต้อง');
            $policyError = passwordPolicyError($password, $username);
            if ($policyError !== '') throw new RuntimeException($policyError);
            $stmt = db()->prepare("INSERT INTO users (username, full_name, department, password_hash, role, account_status, must_change_password) VALUES (?, ?, ?, ?, ?, 'approved', 1)");
            $stmt->execute([$username, $fullName, $department, password_hash($password, PASSWORD_DEFAULT), $role]);
            auditEvent('user_created', (int) $user['id'], ['created_user_id' => (int) db()->lastInsertId(), 'role' => $role]);
            settingRedirect('user_created');
        }

        if ($action === 'update_user') {
            $targetId = (int) settingPost('user_id');
            if ($targetId === (int) $user['id']) throw new RuntimeException('กรุณาแก้บัญชีของคุณจากส่วน “บัญชีของฉัน”');
            $stmt = db()->prepare('SELECT id, username, role, account_status FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target) throw new RuntimeException('ไม่พบบัญชีผู้ใช้');
            $username = settingPost('username');
            $role = settingPost('role', 'editor');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            if (!validUsername($username)) throw new RuntimeException('ชื่อผู้ใช้ไม่ถูกต้อง');
            if (!in_array($role, ['admin', 'editor'], true)) throw new RuntimeException('สิทธิ์ผู้ใช้ไม่ถูกต้อง');
            if ($role === 'admin' && (string) ($target['account_status'] ?? '') !== 'approved') {
                throw new RuntimeException('ต้องอนุมัติและเปิดใช้งานบัญชีนี้ก่อนจึงจะให้สิทธิ์แอดมินได้');
            }
            $fullName = settingPost('full_name');
            $department = settingPost('department');
            if (mb_strlen($fullName, 'UTF-8') > 150 || mb_strlen($department, 'UTF-8') > 255) {
                throw new RuntimeException('ชื่อ–นามสกุลหรือหน่วยงานยาวเกินที่กำหนด');
            }
            if (($target['role'] ?? '') === 'admin' && $role !== 'admin') {
                $adminCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($adminCount <= 1) throw new RuntimeException('ระบบต้องมีผู้ดูแลอย่างน้อย 1 บัญชี');
            }
            $mustChange = isset($_POST['force_password_change']) ? 1 : 0;
            if ($newPassword !== '') {
                if ($newPassword !== $confirmPassword) throw new RuntimeException('การยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
                $policyError = passwordPolicyError($newPassword, $username);
                if ($policyError !== '') throw new RuntimeException($policyError);
                $stmt = db()->prepare('UPDATE users SET username = ?, full_name = ?, department = ?, role = ?, password_hash = ?, must_change_password = 1, password_changed_at = NULL WHERE id = ?');
                $stmt->execute([$username, $fullName, $department, $role, password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
                $mustChange = 1;
            } else {
                $stmt = db()->prepare('UPDATE users SET username = ?, full_name = ?, department = ?, role = ?, must_change_password = ? WHERE id = ?');
                $stmt->execute([$username, $fullName, $department, $role, $mustChange, $targetId]);
            }
            auditEvent('user_updated', (int) $user['id'], ['target_user_id' => $targetId, 'role' => $role, 'password_reset' => $newPassword !== '', 'must_change_password' => $mustChange]);
            settingRedirect('user_saved');
        }

        if ($action === 'delete_user') {
            $targetId = (int) settingPost('user_id');
            if ($targetId === (int) $user['id']) throw new RuntimeException('ไม่สามารถลบบัญชีที่กำลังใช้งาน');
            $stmt = db()->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $targetRole = $stmt->fetchColumn();
            if ($targetRole === false) throw new RuntimeException('ไม่พบบัญชีผู้ใช้');
            if ($targetRole === 'admin') {
                $adminCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($adminCount <= 1) throw new RuntimeException('ไม่สามารถลบผู้ดูแลคนสุดท้ายได้');
            }
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            auditEvent('user_deleted', (int) $user['id'], ['deleted_user_id' => $targetId]);
            settingRedirect('user_deleted');
        }
    }
} catch (PDOException $e) {
    $error = $e->getCode() === '23000' ? 'ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว' : 'ไม่สามารถบันทึกข้อมูลผู้ใช้ได้';
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$ownStmt = db()->prepare('SELECT id, username, full_name, department, role, account_status, must_change_password, password_changed_at, created_at FROM users WHERE id = ?');
$ownStmt->execute([(int) (currentUser()['id'] ?? 0)]);
$user = $ownStmt->fetch();
$isAdmin = ($user['role'] ?? '') === 'admin';
$users = $isAdmin ? db()->query("SELECT id, username, full_name, department, role, account_status, reviewed_at, must_change_password, password_changed_at, created_at FROM users ORDER BY CASE account_status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 WHEN 'disabled' THEN 2 ELSE 3 END, id DESC")->fetchAll() : [];
$adminUsers = $isAdmin ? array_values(array_filter($users, static fn(array $account): bool => ($account['role'] ?? '') === 'admin')) : [];
$editorUsers = $isAdmin ? array_values(array_filter($users, static fn(array $account): bool => ($account['role'] ?? '') === 'editor')) : [];
$pendingPasswordCount = $isAdmin ? count(array_filter($users, static fn(array $account): bool => (int) ($account['must_change_password'] ?? 0) === 1)) : 0;
$pendingRegistrations = $isAdmin ? array_values(array_filter($users, static fn(array $account): bool => ($account['account_status'] ?? '') === 'pending' && ($account['role'] ?? '') === 'editor')) : [];
$accountStatusCounts = ['pending'=>0,'approved'=>0,'rejected'=>0,'disabled'=>0];
foreach ($users as $accountStat) {
    $key = (string) ($accountStat['account_status'] ?? '');
    if (isset($accountStatusCounts[$key])) $accountStatusCounts[$key]++;
}
$accountStatusLabels = ['pending'=>'รออนุมัติ','approved'=>'ใช้งานได้','rejected'=>'ไม่อนุมัติ','disabled'=>'ระงับสิทธิ์'];
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ศูนย์บัญชีผู้ใช้และการอนุมัติ | TOR SKP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/backoffice-blue.css?v=20261124">
    <style>
      .account-card > summary::-webkit-details-marker{display:none}
      .account-card .chevron{transition:transform .2s}
      .account-card[open] .chevron{transform:rotate(180deg)}
    </style>
</head>
<body class="min-h-screen backoffice-page account-backoffice">
<?php require __DIR__ . '/components/navbar.php'; ?>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 account-main">
    <div class="surface overflow-hidden rounded-3xl">
    <header class="bo-hero account-hero">
      <div class="bo-hero-content">
        <p class="bo-eyebrow"><span class="bo-dot"></span> TOR SKP · ACCOUNT CENTER</p>
        <h1>ศูนย์จัดการบัญชีผู้ใช้</h1>
        <p><?= $isAdmin ? 'ตรวจสอบคำขอสมัครสมาชิก อนุมัติสิทธิ์ จัดการไอดีและรหัสผ่านจากหน้าเดียว โดยแยกจากข้อมูลยาและเอกสาร' : 'ดูแลบัญชี เปลี่ยนชื่อผู้ใช้ และตั้งรหัสผ่านใหม่อย่างปลอดภัย' ?></p>
      </div>
      <div class="bo-hero-actions">
        <span class="bo-user-chip">เข้าสู่ระบบ: <strong><?= h((string) $user['username']) ?></strong> #<?= (int) $user['id'] ?></span>
        <?php if ($isAdmin): ?><a href="admin.php" class="bo-hero-button">จัดการยาและเอกสาร ↗</a><?php else: ?><a href="index.php" class="bo-hero-button">กลับหน้าแรก ↗</a><?php endif; ?>
      </div>
    </header>

    <?php if ($error !== ''): ?><div class="mx-5 mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm sm:mx-7">⚠ <?= h($error) ?></div><?php endif; ?>
    <?php if ($message !== ''): ?><div class="mx-5 mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm sm:mx-7">✓ <?= h($message) ?></div><?php endif; ?>

    <?php if ($isAdmin): ?>
        <section class="bo-metrics" aria-label="สรุปบัญชีผู้ใช้">
          <div class="bo-metric"><span>คำขอรออนุมัติ</span><strong><?= number_format($accountStatusCounts['pending']) ?></strong><small>ตรวจสอบสมาชิกใหม่</small></div>
          <div class="bo-metric"><span>บัญชีใช้งานได้</span><strong><?= number_format($accountStatusCounts['approved']) ?></strong><small>รวมผู้ดูแลระบบ</small></div>
          <div class="bo-metric"><span>บัญชีที่ระงับ</span><strong><?= number_format($accountStatusCounts['disabled']) ?></strong><small>ยังไม่สามารถเข้าสู่ระบบ</small></div>
          <div class="bo-metric"><span>บัญชีทั้งหมด</span><strong><?= number_format(count($users)) ?></strong><small>ผู้ดูแล <?= number_format(count($adminUsers)) ?> บัญชี</small></div>
        </section>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2 border-b border-slate-100 bg-white px-5 py-4 sm:px-7" aria-label="หมวดการตั้งค่า">
        <a href="#my-account" class="rounded-full bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700">บัญชีของฉัน</a>
        <?php if ($isAdmin): ?><a href="#approval-queue" class="bo-pending-link">คำขอรออนุมัติ <span><?= count($pendingRegistrations) ?></span></a><?php endif; ?>
        <?php if ($isAdmin): ?><a href="#new-account" class="rounded-full bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700">เพิ่มผู้ใช้</a><a href="#user-directory" class="rounded-full bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-700">รายชื่อผู้ใช้</a><?php endif; ?>
    </nav>

    <section id="my-account" class="scroll-mt-6 border-b border-slate-100">
        <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50 via-white to-cyan-50 px-5 py-5 sm:px-7">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><div class="text-xs font-bold uppercase tracking-[.16em] text-indigo-500">My account</div><h2 class="mt-1 text-xl font-bold text-slate-800">บัญชีของฉัน</h2></div>
                <div class="flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm"><span class="status-dot bg-emerald-400"></span><?= $isAdmin ? 'ผู้ดูแลระบบ' : 'ผู้ใช้งานเอกสาร' ?> · ID <?= (int) $user['id'] ?></div>
            </div>
            <?php if ((int) ($user['must_change_password'] ?? 0) === 1): ?><p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">บัญชีนี้ต้องกำหนดรหัสผ่านใหม่ก่อนใช้งานหน้าอื่น</p><?php endif; ?>
        </div>
        <form method="post" class="grid gap-4 p-5 md:grid-cols-2 sm:p-7">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="action" value="save_own_account">
            <label class="field-label">ชื่อผู้ใช้<input name="username" value="<?= h((string) $user['username']) ?>" required class="field"></label>
            <label class="field-label">รหัสผ่านปัจจุบัน<input type="password" name="current_password" autocomplete="current-password" required class="field"></label>
            <label class="field-label">รหัสผ่านใหม่<input type="password" name="new_password" minlength="6" autocomplete="new-password" class="field" placeholder="เว้นว่างหากไม่เปลี่ยน"></label>
            <label class="field-label">ยืนยันรหัสผ่านใหม่<input type="password" name="confirm_password" minlength="6" autocomplete="new-password" class="field"></label>
            <div class="flex flex-wrap items-center justify-between gap-3 md:col-span-2"><p class="text-xs text-slate-500">อย่างน้อย 6 ตัว มีตัวอักษรและตัวเลข และไม่เหมือนชื่อผู้ใช้</p><button class="primary-button min-w-48">บันทึกบัญชีของฉัน</button></div>
        </form>
    </section>

    <?php if ($isAdmin): ?>
        <section id="approval-queue" class="bo-approval-queue" aria-labelledby="approval-title">
          <div class="bo-approval-heading">
            <div><span class="bo-label">MEMBER APPROVALS</span><h2 id="approval-title">คำขอสมัครสมาชิก</h2><p>บัญชีสมัครเองจะอยู่ในสถานะรออนุมัติ และยังเข้าสู่ระบบไม่ได้จนกว่าจะได้รับอนุญาต</p></div>
            <span class="bo-queue-count"><?= count($pendingRegistrations) ?> รายการรอตรวจสอบ</span>
          </div>
          <?php if (!$pendingRegistrations): ?>
            <div class="bo-empty-state"><span aria-hidden="true">✓</span><strong>ไม่มีคำขอที่ค้างอยู่</strong><p>คำขอใหม่จะแสดงในส่วนนี้ทันทีที่มีผู้สมัคร</p></div>
          <?php else: ?>
            <div class="bo-pending-list">
              <?php foreach ($pendingRegistrations as $candidate): ?>
                <article class="bo-pending-card">
                  <div class="bo-pending-avatar" aria-hidden="true"><?= h(mb_substr((string) ($candidate['full_name'] ?: $candidate['username']), 0, 1, 'UTF-8')) ?></div>
                  <div class="bo-pending-info"><h3><?= h((string) ($candidate['full_name'] ?: $candidate['username'])) ?></h3><p>@<?= h((string) $candidate['username']) ?> · <?= h((string) ($candidate['department'] ?: 'ไม่ระบุหน่วยงาน')) ?></p><small>สมัคร <?= h((string) $candidate['created_at']) ?> · รหัส #<?= (int) $candidate['id'] ?></small></div>
                  <div class="bo-pending-actions">
                    <form method="post" onsubmit="return confirm('ยืนยันอนุมัติบัญชีนี้ให้ใช้งานเอกสาร?')"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="review_registration"><input type="hidden" name="account_id" value="<?= (int) $candidate['id'] ?>"><input type="hidden" name="decision" value="approve"><button class="bo-approve-button" type="submit">อนุมัติ</button></form>
                    <form method="post" onsubmit="return confirm('ยืนยันปฏิเสธคำขอนี้?')"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="review_registration"><input type="hidden" name="account_id" value="<?= (int) $candidate['id'] ?>"><input type="hidden" name="decision" value="reject"><button class="bo-reject-button" type="submit">ไม่อนุมัติ</button></form>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section id="new-account" class="scroll-mt-6 border-b border-slate-100">
            <div class="grid lg:grid-cols-[.7fr_1.3fr]">
                <div class="bg-gradient-to-br from-cyan-500 to-teal-600 p-6 text-white sm:p-7"><div class="text-xs font-bold uppercase tracking-[.16em] text-cyan-100">New account</div><h2 class="mt-2 text-2xl font-bold">เพิ่มผู้ใช้ใหม่</h2><p class="mt-2 text-sm leading-6 text-cyan-50">กำหนดไอดี สิทธิ์ และรหัสเริ่มต้น ผู้ใช้ใหม่จะถูกขอให้เปลี่ยนรหัสทันทีหลังเข้าสู่ระบบ</p></div>
                <form method="post" class="grid gap-4 p-5 md:grid-cols-2 sm:p-7">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="action" value="create_user">
                    <label class="field-label">ชื่อผู้ใช้ใหม่<input name="new_username" required class="field" placeholder="เช่น somchai01"></label>
                    <label class="field-label">ชื่อ–นามสกุล<input name="new_full_name" maxlength="150" class="field" placeholder="ชื่อจริงของเจ้าหน้าที่"></label>
                    <label class="field-label md:col-span-2">หน่วยงาน<input name="new_department" maxlength="255" class="field" placeholder="เช่น ศูนย์สาธารณสุขและการแพทย์ตำบล..."></label>
                    <label class="field-label">สิทธิ์<select name="new_role" class="field"><option value="editor">ผู้ใช้งานเอกสาร</option><option value="admin">ผู้ดูแลระบบ</option></select></label>
                    <label class="field-label md:col-span-2">รหัสผ่านเริ่มต้น<input type="password" name="new_user_password" minlength="6" required autocomplete="new-password" class="field" placeholder="อย่างน้อย 6 ตัว มีตัวอักษรและตัวเลข"></label>
                    <button class="primary-button md:col-span-2">+ สร้างบัญชีผู้ใช้</button>
                </form>
            </div>
        </section>

        <section id="user-directory" class="scroll-mt-6 p-5 sm:p-7">
            <div class="flex flex-wrap items-end justify-between gap-4"><div><div class="text-xs font-bold uppercase tracking-[.16em] text-indigo-500">User directory</div><h2 class="mt-1 text-2xl font-bold text-slate-800">ไอดีผู้ใช้ทั้งหมด</h2><p class="mt-1 text-sm text-slate-500">เลือกไอดีเพื่อเปิดแก้ไข ระบบไม่สามารถแสดงรหัสผ่านเดิมที่จัดเก็บแบบ hash ได้</p></div><label class="w-full max-w-sm"><span class="sr-only">ค้นหาผู้ใช้</span><input id="account_search" class="field" placeholder="ค้นหาด้วย ID หรือชื่อผู้ใช้..."></label></div>
            <?php foreach ([['title' => 'ผู้ดูแลระบบ', 'subtitle' => 'เข้าถึงการตั้งค่าและข้อมูลส่วนผู้ดูแล', 'items' => $adminUsers, 'badge' => 'admin-badge', 'accent' => 'text-violet-600'], ['title' => 'ผู้ใช้งานเอกสาร', 'subtitle' => 'ใช้งานและจัดทำเอกสารของระบบ', 'items' => $editorUsers, 'badge' => 'editor-badge', 'accent' => 'text-teal-600']] as $group): ?>
                <div class="account-group mt-6">
                    <div class="mb-3 flex items-center gap-3"><h3 class="text-lg font-bold <?= $group['accent'] ?>"><?= h($group['title']) ?></h3><span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-500 shadow-sm"><?= count($group['items']) ?> ไอดี</span><span class="hidden text-sm text-slate-400 sm:inline"><?= h($group['subtitle']) ?></span></div>
                    <div class="grid gap-3 lg:grid-cols-2">
                    <?php foreach ($group['items'] as $account): ?>
                        <details class="account-card account-item" data-search="<?= h(mb_strtolower('#' . (int) $account['id'] . ' ' . (string) $account['username'] . ' ' . (string) ($account['full_name'] ?? '') . ' ' . (string) ($account['department'] ?? ''), 'UTF-8')) ?>">
                            <summary class="flex items-center gap-3 p-4 sm:p-5">
                                <div class="id-badge <?= h($group['badge']) ?>">#<?= (int) $account['id'] ?></div>
                                <div class="min-w-0 flex-1"><div class="truncate font-bold text-slate-800"><?= h((string) (($account['full_name'] ?? '') ?: $account['username'])) ?></div><div class="truncate text-xs text-slate-500">@<?= h((string) $account['username']) ?></div><div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500"><span><span class="status-dot <?= ($account['account_status'] ?? '') === 'approved' ? 'bg-emerald-400' : 'bg-amber-400' ?>"></span> <?= h($accountStatusLabels[(string) ($account['account_status'] ?? '')] ?? 'ไม่ทราบสถานะ') ?><?= (int) $account['must_change_password'] === 1 ? ' · รอเปลี่ยนรหัสผ่าน' : '' ?></span><?php if ((int) $account['id'] === (int) $user['id']): ?><span class="rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-600">บัญชีของคุณ</span><?php endif; ?></div></div>
                                <div class="chevron text-slate-400">⌄</div>
                            </summary>
                            <div class="border-t border-slate-100 bg-slate-50/70 p-4 sm:p-5">
                            <?php if ((int) $account['id'] === (int) $user['id']): ?>
                                <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">แก้ไขไอดีนี้ได้จากหมวด “บัญชีของฉัน” ด้านบน</div>
                            <?php else: ?>
                                <form method="post" class="grid gap-4 md:grid-cols-2">
                                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                    <label class="field-label">ชื่อผู้ใช้<input name="username" value="<?= h((string) $account['username']) ?>" required class="field"></label>
                                    <label class="field-label">ชื่อ–นามสกุล<input name="full_name" value="<?= h((string) ($account['full_name'] ?? '')) ?>" maxlength="150" class="field"></label>
                                    <label class="field-label md:col-span-2">หน่วยงาน<input name="department" value="<?= h((string) ($account['department'] ?? '')) ?>" maxlength="255" class="field"></label>
                                    <label class="field-label">สิทธิ์<select name="role" class="field"><option value="editor"<?= $account['role'] === 'editor' ? ' selected' : '' ?>>ผู้ใช้งานเอกสาร</option><option value="admin"<?= $account['role'] === 'admin' ? ' selected' : '' ?>>ผู้ดูแลระบบ</option></select></label>
                                    <div class="rounded-2xl border border-violet-100 bg-violet-50/70 p-4 md:col-span-2"><div class="mb-3 text-xs font-bold uppercase tracking-[.12em] text-violet-600">Reset password</div><div class="grid gap-3 md:grid-cols-2"><label class="field-label">รหัสผ่านใหม่<input type="password" name="new_password" minlength="6" autocomplete="new-password" class="field" placeholder="เว้นว่างหากไม่เปลี่ยน"></label><label class="field-label">ยืนยันรหัสผ่าน<input type="password" name="confirm_password" minlength="6" autocomplete="new-password" class="field"></label></div></div>
                                    <label class="flex items-center gap-2 text-sm text-slate-600 md:col-span-2"><input type="checkbox" name="force_password_change" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600"<?= (int) $account['must_change_password'] === 1 ? ' checked' : '' ?>> บังคับเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบ</label>
                                    <button class="primary-button md:col-span-2">บันทึกการแก้ไข ID #<?= (int) $account['id'] ?></button>
                                </form>
                                <?php if (($account['role'] ?? '') === 'editor'): ?>
                                <div class="bo-account-actions">
                                  <?php $accountStatus = (string) ($account['account_status'] ?? 'approved'); ?>
                                  <?php if ($accountStatus === 'approved'): ?>
                                    <form method="post" onsubmit="return confirm('ระงับสิทธิ์บัญชีนี้ทันที?')"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="set_editor_status"><input type="hidden" name="account_id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="decision" value="disable"><button type="submit" class="bo-suspend-button">ระงับการเข้าสู่ระบบ</button></form>
                                  <?php elseif ($accountStatus === 'disabled'): ?>
                                    <form method="post" onsubmit="return confirm('เปิดใช้งานบัญชีนี้อีกครั้ง?')"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="set_editor_status"><input type="hidden" name="account_id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="decision" value="enable"><button type="submit" class="bo-reactivate-button">เปิดใช้งานอีกครั้ง</button></form>
                                  <?php elseif ($accountStatus === 'rejected'): ?>
                                    <form method="post" onsubmit="return confirm('อนุมัติบัญชีที่เคยถูกปฏิเสธ?')"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="review_registration"><input type="hidden" name="account_id" value="<?= (int) $account['id'] ?>"><input type="hidden" name="decision" value="approve_rejected"><button type="submit" class="bo-reactivate-button">อนุมัติบัญชีนี้</button></form>
                                  <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <form method="post" class="mt-3" onsubmit="return confirm('ยืนยันลบบัญชีผู้ใช้นี้?')"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>"><button class="w-full rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50">ลบบัญชีนี้</button></form>
                            <?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="no_account_results" class="mt-5 hidden rounded-2xl border border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">ไม่พบไอดีที่ค้นหา</div>
        </section>
    <?php endif; ?>
    </div>
</main>
<script>
const accountSearch = document.getElementById('account_search');
accountSearch?.addEventListener('input', () => {
    const term = accountSearch.value.trim().toLocaleLowerCase('th-TH');
    let visible = 0;
    document.querySelectorAll('.account-item').forEach((item) => {
        const matched = !term || (item.dataset.search || '').includes(term);
        item.classList.toggle('hidden', !matched);
        if (matched) visible++;
    });
    document.querySelectorAll('.account-group').forEach((group) => {
        group.classList.toggle('hidden', !group.querySelector('.account-item:not(.hidden)'));
    });
    document.getElementById('no_account_results')?.classList.toggle('hidden', visible !== 0);
});
</script>
</body>
</html>
