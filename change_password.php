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

        if ($action === 'create_user') {
            $username = settingPost('new_username');
            $password = (string) ($_POST['new_user_password'] ?? '');
            $role = settingPost('new_role', 'editor');
            if (!validUsername($username)) throw new RuntimeException('ชื่อผู้ใช้ใหม่ไม่ถูกต้อง');
            if (!in_array($role, ['admin', 'editor'], true)) throw new RuntimeException('สิทธิ์ผู้ใช้ไม่ถูกต้อง');
            $policyError = passwordPolicyError($password, $username);
            if ($policyError !== '') throw new RuntimeException($policyError);
            $stmt = db()->prepare('INSERT INTO users (username, password_hash, role, must_change_password) VALUES (?, ?, ?, 1)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);
            auditEvent('user_created', (int) $user['id'], ['created_user_id' => (int) db()->lastInsertId(), 'role' => $role]);
            settingRedirect('user_created');
        }

        if ($action === 'update_user') {
            $targetId = (int) settingPost('user_id');
            if ($targetId === (int) $user['id']) throw new RuntimeException('กรุณาแก้บัญชีของคุณจากส่วน “บัญชีของฉัน”');
            $stmt = db()->prepare('SELECT id, username, role FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target) throw new RuntimeException('ไม่พบบัญชีผู้ใช้');
            $username = settingPost('username');
            $role = settingPost('role', 'editor');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            if (!validUsername($username)) throw new RuntimeException('ชื่อผู้ใช้ไม่ถูกต้อง');
            if (!in_array($role, ['admin', 'editor'], true)) throw new RuntimeException('สิทธิ์ผู้ใช้ไม่ถูกต้อง');
            if (($target['role'] ?? '') === 'admin' && $role !== 'admin') {
                $adminCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($adminCount <= 1) throw new RuntimeException('ระบบต้องมีผู้ดูแลอย่างน้อย 1 บัญชี');
            }
            $mustChange = isset($_POST['force_password_change']) ? 1 : 0;
            if ($newPassword !== '') {
                if ($newPassword !== $confirmPassword) throw new RuntimeException('การยืนยันรหัสผ่านใหม่ไม่ตรงกัน');
                $policyError = passwordPolicyError($newPassword, $username);
                if ($policyError !== '') throw new RuntimeException($policyError);
                $stmt = db()->prepare('UPDATE users SET username = ?, role = ?, password_hash = ?, must_change_password = 1, password_changed_at = NULL WHERE id = ?');
                $stmt->execute([$username, $role, password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
                $mustChange = 1;
            } else {
                $stmt = db()->prepare('UPDATE users SET username = ?, role = ?, must_change_password = ? WHERE id = ?');
                $stmt->execute([$username, $role, $mustChange, $targetId]);
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

$ownStmt = db()->prepare('SELECT id, username, role, must_change_password, password_changed_at, created_at FROM users WHERE id = ?');
$ownStmt->execute([(int) (currentUser()['id'] ?? 0)]);
$user = $ownStmt->fetch();
$isAdmin = ($user['role'] ?? '') === 'admin';
$users = $isAdmin ? db()->query('SELECT id, username, role, must_change_password, password_changed_at, created_at FROM users ORDER BY id')->fetchAll() : [];
$adminUsers = $isAdmin ? array_values(array_filter($users, static fn(array $account): bool => ($account['role'] ?? '') === 'admin')) : [];
$editorUsers = $isAdmin ? array_values(array_filter($users, static fn(array $account): bool => ($account['role'] ?? '') === 'editor')) : [];
$pendingPasswordCount = $isAdmin ? count(array_filter($users, static fn(array $account): bool => (int) ($account['must_change_password'] ?? 0) === 1)) : 0;
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตั้งค่าผู้ใช้</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { color-scheme: light; }
        body { background: #f6f7fb !important; color: #172033 !important; }
        body::before { content: ''; position: fixed; inset: 0; z-index: -1; background: radial-gradient(circle at 8% 8%, rgba(99,102,241,.13), transparent 27rem), radial-gradient(circle at 92% 20%, rgba(236,72,153,.10), transparent 24rem), radial-gradient(circle at 55% 95%, rgba(20,184,166,.10), transparent 25rem); }
        body > nav { border-color: rgba(255,255,255,.7) !important; background: rgba(255,255,255,.82) !important; box-shadow: 0 1px 20px rgba(30,41,59,.07); backdrop-filter: blur(18px); }
        body > nav a:first-child { color: #172033 !important; }
        body > nav span { color: #64748b !important; }
        .surface { border: 1px solid rgba(226,232,240,.9); background: rgba(255,255,255,.88); box-shadow: 0 18px 50px rgba(51,65,85,.08); backdrop-filter: blur(14px); }
        .hero { background: linear-gradient(120deg, #4f46e5 0%, #7c3aed 52%, #db2777 100%); box-shadow: 0 24px 55px rgba(79,70,229,.23); }
        .field { width: 100%; margin-top: .4rem; border: 1px solid #dbe2ea; border-radius: .8rem; background: #fff; padding: .72rem .85rem; color: #172033; outline: none; transition: border-color .18s, box-shadow .18s; }
        .field:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,.12); }
        .field-label { display: block; font-size: .8rem; font-weight: 650; color: #475569; }
        .primary-button { border-radius: .8rem; background: linear-gradient(110deg, #4f46e5, #7c3aed); padding: .75rem 1rem; font-weight: 700; color: #fff; box-shadow: 0 9px 20px rgba(79,70,229,.2); transition: transform .18s, box-shadow .18s; }
        .primary-button:hover { transform: translateY(-1px); box-shadow: 0 12px 25px rgba(79,70,229,.27); }
        .account-card { overflow: hidden; border: 1px solid #e5eaf0; border-radius: 1rem; background: rgba(255,255,255,.92); box-shadow: 0 10px 30px rgba(51,65,85,.06); transition: border-color .18s, transform .18s, box-shadow .18s; }
        .account-card:hover { border-color: #c7d2fe; transform: translateY(-2px); box-shadow: 0 16px 35px rgba(79,70,229,.10); }
        .account-card[open] { border-color: #a5b4fc; }
        .account-card > summary { list-style: none; cursor: pointer; }
        .account-card > summary::-webkit-details-marker { display: none; }
        .account-card .chevron { transition: transform .18s; }
        .account-card[open] .chevron { transform: rotate(180deg); }
        .id-badge { display: inline-grid; min-width: 3.1rem; height: 3.1rem; place-items: center; border-radius: .9rem; font-weight: 800; letter-spacing: -.03em; }
        .admin-badge { background: linear-gradient(135deg, #ede9fe, #fae8ff); color: #6d28d9; }
        .editor-badge { background: linear-gradient(135deg, #ccfbf1, #dbeafe); color: #0f766e; }
        .status-dot { width: .5rem; height: .5rem; border-radius: 999px; display: inline-block; }
    </style>
</head>
<body class="min-h-screen">
<?php require __DIR__ . '/components/navbar.php'; ?>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="surface overflow-hidden rounded-3xl">
    <header class="hero relative overflow-hidden px-6 py-8 text-white sm:px-9">
        <div class="absolute -right-12 -top-16 h-52 w-52 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-20 right-32 h-44 w-44 rounded-full bg-cyan-300/10"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-5">
            <div>
                <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium backdrop-blur">ACCOUNT CENTER</div>
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">ตั้งค่าผู้ใช้</h1>
                <p class="mt-2 max-w-2xl text-sm text-indigo-100 sm:text-base"><?= $isAdmin ? 'จัดการไอดี สิทธิ์ และความปลอดภัยของผู้ใช้ทั้งหมดได้จากหน้าเดียว' : 'จัดการชื่อผู้ใช้และรหัสผ่านของบัญชีคุณอย่างปลอดภัย' ?></p>
            </div>
            <div class="rounded-2xl border border-white/20 bg-white/10 px-5 py-3 text-right backdrop-blur">
                <div class="text-xs text-indigo-100">กำลังใช้งาน</div>
                <div class="mt-1 font-semibold"><?= h((string) $user['username']) ?> <span class="font-normal text-indigo-200">#<?= (int) $user['id'] ?></span></div>
            </div>
        </div>
    </header>

    <?php if ($error !== ''): ?><div class="mx-5 mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 shadow-sm sm:mx-7">⚠ <?= h($error) ?></div><?php endif; ?>
    <?php if ($message !== ''): ?><div class="mx-5 mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 shadow-sm sm:mx-7">✓ <?= h($message) ?></div><?php endif; ?>

    <?php if ($isAdmin): ?>
        <section class="grid grid-cols-2 gap-3 border-b border-slate-100 bg-slate-50/70 p-5 lg:grid-cols-4 sm:p-7">
            <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4"><div class="text-xs font-medium text-slate-500">บัญชีทั้งหมด</div><div class="mt-2 text-3xl font-bold text-indigo-600"><?= count($users) ?></div></div>
            <div class="rounded-2xl border border-violet-100 bg-violet-50 p-4"><div class="text-xs font-medium text-slate-500">ผู้ดูแลระบบ</div><div class="mt-2 text-3xl font-bold text-violet-600"><?= count($adminUsers) ?></div></div>
            <div class="rounded-2xl border border-teal-100 bg-teal-50 p-4"><div class="text-xs font-medium text-slate-500">ผู้ใช้งานเอกสาร</div><div class="mt-2 text-3xl font-bold text-teal-600"><?= count($editorUsers) ?></div></div>
            <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4"><div class="text-xs font-medium text-slate-500">รอเปลี่ยนรหัสผ่าน</div><div class="mt-2 text-3xl font-bold text-amber-500"><?= $pendingPasswordCount ?></div></div>
        </section>
    <?php endif; ?>

    <nav class="flex flex-wrap gap-2 border-b border-slate-100 bg-white px-5 py-4 sm:px-7" aria-label="หมวดการตั้งค่า">
        <a href="#my-account" class="rounded-full bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700">บัญชีของฉัน</a>
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
        <section id="new-account" class="scroll-mt-6 border-b border-slate-100">
            <div class="grid lg:grid-cols-[.7fr_1.3fr]">
                <div class="bg-gradient-to-br from-cyan-500 to-teal-600 p-6 text-white sm:p-7"><div class="text-xs font-bold uppercase tracking-[.16em] text-cyan-100">New account</div><h2 class="mt-2 text-2xl font-bold">เพิ่มผู้ใช้ใหม่</h2><p class="mt-2 text-sm leading-6 text-cyan-50">กำหนดไอดี สิทธิ์ และรหัสเริ่มต้น ผู้ใช้ใหม่จะถูกขอให้เปลี่ยนรหัสทันทีหลังเข้าสู่ระบบ</p></div>
                <form method="post" class="grid gap-4 p-5 md:grid-cols-2 sm:p-7">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="action" value="create_user">
                    <label class="field-label">ชื่อผู้ใช้ใหม่<input name="new_username" required class="field" placeholder="เช่น somchai01"></label>
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
                        <details class="account-card account-item" data-search="<?= h(mb_strtolower('#' . (int) $account['id'] . ' ' . (string) $account['username'], 'UTF-8')) ?>">
                            <summary class="flex items-center gap-3 p-4 sm:p-5">
                                <div class="id-badge <?= h($group['badge']) ?>">#<?= (int) $account['id'] ?></div>
                                <div class="min-w-0 flex-1"><div class="truncate font-bold text-slate-800"><?= h((string) $account['username']) ?></div><div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500"><span><span class="status-dot <?= (int) $account['must_change_password'] === 1 ? 'bg-amber-400' : 'bg-emerald-400' ?>"></span> <?= (int) $account['must_change_password'] === 1 ? 'รอเปลี่ยนรหัสผ่าน' : 'ใช้งานปกติ' ?></span><?php if ((int) $account['id'] === (int) $user['id']): ?><span class="rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-600">บัญชีของคุณ</span><?php endif; ?></div></div>
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
                                    <label class="field-label">สิทธิ์<select name="role" class="field"><option value="editor"<?= $account['role'] === 'editor' ? ' selected' : '' ?>>ผู้ใช้งานเอกสาร</option><option value="admin"<?= $account['role'] === 'admin' ? ' selected' : '' ?>>ผู้ดูแลระบบ</option></select></label>
                                    <div class="rounded-2xl border border-violet-100 bg-violet-50/70 p-4 md:col-span-2"><div class="mb-3 text-xs font-bold uppercase tracking-[.12em] text-violet-600">Reset password</div><div class="grid gap-3 md:grid-cols-2"><label class="field-label">รหัสผ่านใหม่<input type="password" name="new_password" minlength="6" autocomplete="new-password" class="field" placeholder="เว้นว่างหากไม่เปลี่ยน"></label><label class="field-label">ยืนยันรหัสผ่าน<input type="password" name="confirm_password" minlength="6" autocomplete="new-password" class="field"></label></div></div>
                                    <label class="flex items-center gap-2 text-sm text-slate-600 md:col-span-2"><input type="checkbox" name="force_password_change" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600"<?= (int) $account['must_change_password'] === 1 ? ' checked' : '' ?>> บังคับเปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบ</label>
                                    <button class="primary-button md:col-span-2">บันทึกการแก้ไข ID #<?= (int) $account['id'] ?></button>
                                </form>
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
