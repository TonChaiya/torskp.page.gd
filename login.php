<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }

ensureUsersTable();
startSecureSession();

if (currentUser()) {
    header('Location: index.php');
    exit;
}

$error = '';
$notice = (string) ($_GET['registered'] ?? '') === '1'
    ? 'ส่งคำขอสมัครสมาชิกแล้ว กรุณารอผู้ดูแลระบบอนุมัติบัญชีก่อนเข้าสู่ระบบ'
    : '';
$next = str_replace('\\', '/', (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php'));
if ($next === '' || str_starts_with($next, '/') || !preg_match('/^[a-zA-Z0-9_\/.?=&%-]+$/', $next)) {
    $next = 'index.php';
}
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = null;

    if (loginIsLocked($username)) {
        $error = 'มีการเข้าสู่ระบบผิดหลายครั้ง กรุณารอ 15 นาทีแล้วลองใหม่';
        auditEvent('login_blocked', null, ['username_hash' => loginThrottleKey($username)]);
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
    }
    if ($error === '' && $user && password_verify($password, (string) $user['password_hash'])) {
        $status = (string) ($user['account_status'] ?? '');
        if ($status !== 'approved') {
            // Only reveal account status after correct credentials, avoiding enumeration.
            $error = match ($status) {
                'pending' => 'บัญชีของคุณกำลังรอผู้ดูแลระบบอนุมัติ',
                'rejected' => 'คำขอสมัครสมาชิกยังไม่ได้รับการอนุมัติ กรุณาติดต่อผู้ดูแลระบบ',
                default => 'บัญชีนี้ไม่สามารถเข้าใช้งานได้ กรุณาติดต่อผู้ดูแลระบบ',
            };
            auditEvent('login_account_not_approved', (int) $user['id'], ['status' => $status]);
        } else {
            clearLoginFailures($username);
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['created_at'] = time();
            $_SESSION['last_seen'] = time();
            if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
                $rehash = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $rehash->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
            }
            auditEvent('login_success', (int) $user['id']);
            header('Location: ' . $next);
            exit;
        }
    }

    if ($error === '') {
        registerLoginFailure($username);
        auditEvent('login_failure', null, ['username_hash' => loginThrottleKey($username)]);
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0964a9">
    <title>เข้าสู่ระบบ | TOR SKP</title>
    <link rel="stylesheet" href="assets/auth-pages.css?v=20261124-1">
</head>
<body class="auth-page">
<main class="auth-shell" aria-label="เข้าสู่ระบบ TOR SKP">
    <aside class="auth-brand" aria-label="แนะนำระบบ">
        <div class="auth-brand-overlay" aria-hidden="true"></div>
        <div class="auth-brand-top"><span class="auth-brand-mark" aria-hidden="true">T</span><span>TOR SKP <small>DOCUMENT WORKSPACE</small></span></div>
        <div class="auth-welcome">
            <span class="auth-eyebrow">ยินดีต้อนรับ</span>
            <h1>ระบบจัดทำ<br>เอกสาร TOR</h1>
            <p>จัดการเอกสารงานจัดซื้อจัดจ้างอย่างเป็นระบบ รวดเร็ว และตรวจสอบได้ในที่เดียว</p>
            <div class="auth-brand-line"></div>
            <span class="auth-brand-tag">ระบบงานสำหรับเจ้าหน้าที่</span>
        </div>
        <div class="auth-orb auth-orb-one" aria-hidden="true"></div>
        <div class="auth-orb auth-orb-two" aria-hidden="true"></div>
        <div class="auth-brand-bottom">TOR SKP · SECURE ACCESS</div>
    </aside>
    <section class="auth-panel">
        <div class="auth-form-wrap">
            <div class="auth-form-kicker"><span class="auth-spark" aria-hidden="true"></span> เข้าสู่พื้นที่ทำงาน</div>
            <h2>เข้าสู่ระบบ</h2>
            <p class="auth-subtitle">กรอกชื่อผู้ใช้และรหัสผ่านเพื่อเข้าใช้งาน</p>
            <?php if ($notice !== ''): ?><div class="auth-alert auth-alert-success" role="status"><?= h($notice) ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="auth-alert auth-alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
            <form method="post" class="auth-form" id="signin-form">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="next" value="<?= h($next) ?>">
                <label class="auth-field" for="username"><span>ชื่อผู้ใช้</span>
                    <span class="auth-control"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-4 3-6 7-6s7 2 7 6"/></svg><input id="username" name="username" value="<?= h($username) ?>" autocomplete="username" maxlength="100" placeholder="กรอกชื่อผู้ใช้" required></span>
                </label>
                <label class="auth-field" for="password"><span>รหัสผ่าน</span>
                    <span class="auth-control"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><input id="password" type="password" name="password" autocomplete="current-password" placeholder="กรอกรหัสผ่าน" required><button class="auth-password-toggle" type="button" data-toggle-password="password" aria-controls="password" aria-pressed="false">แสดง</button></span>
                </label>
                <p class="auth-help">หากลืมรหัสผ่าน กรุณาติดต่อผู้ดูแลระบบ</p>
                <button class="auth-primary" type="submit" id="login-submit">เข้าสู่ระบบ <span aria-hidden="true">→</span></button>
            </form>
            <div class="auth-divider"><span>ยังไม่มีบัญชีผู้ใช้?</span></div>
            <a class="auth-secondary" href="register.php">สมัครสมาชิกใหม่</a>
            <p class="auth-approval-hint">สมาชิกใหม่ต้องได้รับการอนุมัติจากผู้ดูแลระบบก่อนเข้าใช้งาน</p>
        </div>
        <footer class="auth-footer">© <?= date('Y') ?> TOR SKP · ระบบเอกสารภายใน</footer>
    </section>
</main>
<script>
(() => {
    document.querySelectorAll('[data-toggle-password]').forEach(button => button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.togglePassword);
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'ซ่อน' : 'แสดง';
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
    }));
    document.getElementById('signin-form')?.addEventListener('submit', e => {
        const btn = document.getElementById('login-submit');
        if (btn && e.target.checkValidity()) { btn.disabled = true; btn.textContent = 'กำลังเข้าสู่ระบบ…'; }
    });
})();
</script>
</body>
</html>
