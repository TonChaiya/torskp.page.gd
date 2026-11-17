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
$next = str_replace('\\', '/', (string) ($_GET['next'] ?? $_POST['next'] ?? 'index.php'));
if ($next === '' || str_starts_with($next, '/') || !preg_match('/^[a-zA-Z0-9_\/.?=&%-]+$/', $next)) {
    $next = 'index.php';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = null;

    if (loginIsLocked($username)) {
        $error = 'เข้าสู่ระบบผิดหลายครั้ง กรุณารอ 15 นาทีแล้วลองใหม่';
        auditEvent('login_blocked', null, ['username_hash' => loginThrottleKey($username)]);
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
    }
    if ($error === '' && $user && password_verify($password, (string) $user['password_hash'])) {
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
    <title>เข้าสู่ระบบ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/app-theme.css?v=25690821-2">
</head>
<body class="app-themed min-h-screen bg-zinc-950 text-zinc-100">
<main class="flex min-h-screen items-center justify-center px-4 py-8">
    <section class="w-full max-w-md rounded-lg border border-zinc-800 bg-zinc-900/90 p-6">
        <p class="text-sm text-emerald-300">TOR Report</p>
        <h1 class="mt-1 text-2xl font-semibold">เข้าสู่ระบบ</h1>
        <p class="mt-2 text-sm text-zinc-400">กรุณาเข้าสู่ระบบก่อนใช้งานเว็บไซต์</p>

        <?php if ($error !== ''): ?>
            <div class="mt-4 rounded-md border border-red-500/40 bg-red-950/60 p-3 text-sm text-red-100"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="mt-5 grid gap-4">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <label class="block">
                <span class="text-sm text-zinc-300">ชื่อผู้ใช้</span>
                <input name="username" autocomplete="username" required class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
            </label>
            <label class="block">
                <span class="text-sm text-zinc-300">รหัสผ่าน</span>
                <input name="password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-md border border-zinc-700 bg-zinc-950 px-3 py-2 outline-none focus:border-emerald-400">
            </label>
            <button class="rounded-md bg-emerald-500 px-4 py-2 font-medium text-zinc-950 hover:bg-emerald-400">เข้าสู่ระบบ</button>
        </form>
    </section>
</main>
</body>
</html>
