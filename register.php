<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';

function h(?string $text): string { return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

ensureUsersTable();
startSecureSession();
if (currentUser()) { header('Location: index.php'); exit; }

$error = '';
$sent = (string) ($_GET['sent'] ?? '') === '1';
$values = ['full_name' => '', 'department' => '', 'username' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    foreach ($values as $key => $_) $values[$key] = trim((string) ($_POST[$key] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['confirm_password'] ?? '');
    try {
        if (loginIsLocked("\0TOR_REGISTRATION")) {
            throw new RuntimeException('มีการส่งคำขอจากเครือข่ายนี้หลายครั้ง กรุณารอ 15 นาทีแล้วลองใหม่');
        }
        // Reuse the existing IP-based throttle table to limit automated submissions.
        registerLoginFailure("\0TOR_REGISTRATION");
        if ((string) ($_POST['website'] ?? '') !== '') {
            throw new RuntimeException('ไม่สามารถส่งคำขอได้');
        }
        if (mb_strlen($values['full_name'], 'UTF-8') < 3 || mb_strlen($values['full_name'], 'UTF-8') > 150) {
            throw new RuntimeException('กรุณากรอกชื่อ–นามสกุล 3–150 ตัวอักษร');
        }
        if ($values['department'] === '' || mb_strlen($values['department'], 'UTF-8') > 255) {
            throw new RuntimeException('กรุณากรอกหน่วยงาน ไม่เกิน 255 ตัวอักษร');
        }
        if (!preg_match('/^[\p{L}\p{N}._@-]{4,50}$/u', $values['username'])) {
            throw new RuntimeException('ชื่อผู้ใช้ต้องยาว 4–50 ตัวอักษร ใช้ตัวอักษร ตัวเลข . _ @ หรือ - ได้');
        }
        if (mb_strlen($password, 'UTF-8') < 10 || strlen($password) > 72) {
            throw new RuntimeException('รหัสผ่านต้องยาวอย่างน้อย 10 ตัวอักษร และไม่เกิน 72 ไบต์');
        }
        $policyError = passwordPolicyError($password, $values['username']);
        if ($policyError !== '') throw new RuntimeException($policyError);
        if ($password !== $confirmation) throw new RuntimeException('ยืนยันรหัสผ่านไม่ตรงกัน');
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$values['username']]);
        if ($stmt->fetchColumn()) throw new RuntimeException('ชื่อผู้ใช้นี้ถูกใช้แล้ว กรุณาเลือกชื่อใหม่');

        $stmt = db()->prepare("INSERT INTO users (username, full_name, department, password_hash, role, account_status, must_change_password, password_changed_at) VALUES (?, ?, ?, ?, 'editor', 'pending', 0, NOW())");
        $stmt->execute([$values['username'], $values['full_name'], $values['department'], password_hash($password, PASSWORD_DEFAULT)]);
        $newId = (int) db()->lastInsertId();
        auditEvent('registration_submitted', $newId, ['username_hash' => loginThrottleKey($values['username'])]);
        header('Location: register.php?sent=1');
        exit;
    } catch (PDOException $e) {
        // Concurrent duplicate username requests are prevented by the database unique index.
        if ((string) $e->getCode() === '23000') $error = 'ชื่อผู้ใช้นี้ถูกใช้แล้ว กรุณาเลือกชื่อใหม่';
        else { error_log('Registration failed: ' . $e->getMessage()); $error = 'ไม่สามารถส่งคำขอได้ กรุณาติดต่อผู้ดูแลระบบ'; }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0964a9">
    <title>สมัครสมาชิก | TOR SKP</title>
    <link rel="stylesheet" href="assets/auth-pages.css?v=20261124-1">
</head>
<body class="auth-page">
<main class="auth-shell" aria-label="สมัครสมาชิก TOR SKP">
    <aside class="auth-brand" aria-label="แนะนำระบบ">
        <div class="auth-brand-overlay" aria-hidden="true"></div>
        <div class="auth-brand-top"><span class="auth-brand-mark" aria-hidden="true">T</span><span>TOR SKP <small>DOCUMENT WORKSPACE</small></span></div>
        <div class="auth-welcome">
            <span class="auth-eyebrow">เริ่มต้นใช้งาน</span>
            <h1>ร่วมเป็นส่วนหนึ่ง<br>ของ TOR SKP</h1>
            <p>สมัครบัญชีเพื่อจัดทำและบริหารเอกสารอย่างเป็นระบบ โดยผู้ดูแลจะตรวจสอบคำขอก่อนเปิดสิทธิ์ใช้งาน</p>
            <div class="auth-brand-line"></div>
            <span class="auth-brand-tag">ปลอดภัย · มีขั้นตอนอนุมัติ</span>
        </div>
        <div class="auth-orb auth-orb-one" aria-hidden="true"></div>
        <div class="auth-orb auth-orb-two" aria-hidden="true"></div>
        <div class="auth-brand-bottom">TOR SKP · SECURE ACCESS</div>
    </aside>
    <section class="auth-panel auth-panel-registration">
        <div class="auth-form-wrap">
            <a href="login.php" class="auth-back">← กลับไปหน้าเข้าสู่ระบบ</a>
            <div class="auth-form-kicker"><span class="auth-spark" aria-hidden="true"></span> ลงทะเบียนบัญชีใหม่</div>
            <h2>สมัครสมาชิก</h2>
            <p class="auth-subtitle">กรอกข้อมูลให้ครบถ้วนเพื่อส่งให้ผู้ดูแลระบบพิจารณา</p>
            <?php if ($sent): ?>
                <div class="auth-alert auth-alert-success" role="status"><strong>ส่งคำขอสำเร็จ</strong><br>กรุณารอแอดมินอนุมัติบัญชี ก่อนเข้าสู่ระบบ</div>
                <a class="auth-primary auth-primary-link" href="login.php">กลับหน้าเข้าสู่ระบบ <span aria-hidden="true">→</span></a>
            <?php else: ?>
                <?php if ($error !== ''): ?><div class="auth-alert auth-alert-error" role="alert"><?= h($error) ?></div><?php endif; ?>
                <form method="post" class="auth-form" id="register-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <span class="auth-honeypot" aria-hidden="true"><label for="website">Website</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></span>
                    <label class="auth-field" for="full_name"><span>ชื่อ–นามสกุล</span><span class="auth-control"><input id="full_name" name="full_name" value="<?= h($values['full_name']) ?>" maxlength="150" autocomplete="name" placeholder="ชื่อและนามสกุล" required></span></label>
                    <label class="auth-field" for="department"><span>หน่วยงาน</span><span class="auth-control"><input id="department" name="department" value="<?= h($values['department']) ?>" maxlength="255" autocomplete="organization" placeholder="ชื่อหน่วยงานของคุณ" required></span></label>
                    <label class="auth-field" for="username"><span>ชื่อผู้ใช้</span><span class="auth-control"><input id="username" name="username" value="<?= h($values['username']) ?>" maxlength="50" minlength="4" autocomplete="username" placeholder="ชื่อผู้ใช้ 4–50 ตัวอักษร" required></span></label>
                    <label class="auth-field" for="password"><span>ตั้งรหัสผ่าน</span><span class="auth-control"><input id="password" name="password" type="password" minlength="10" autocomplete="new-password" placeholder="อย่างน้อย 10 ตัวอักษร มีตัวเลขด้วย" required><button class="auth-password-toggle" type="button" data-toggle-password="password" aria-controls="password" aria-pressed="false">แสดง</button></span></label>
                    <label class="auth-field" for="confirm_password"><span>ยืนยันรหัสผ่าน</span><span class="auth-control"><input id="confirm_password" name="confirm_password" type="password" minlength="10" autocomplete="new-password" placeholder="พิมพ์รหัสผ่านอีกครั้ง" required><button class="auth-password-toggle" type="button" data-toggle-password="confirm_password" aria-controls="confirm_password" aria-pressed="false">แสดง</button></span></label>
                    <p class="auth-help">บัญชีใหม่จะได้รับสิทธิ์ผู้ใช้งานทั่วไปหลังจากได้รับอนุมัติ</p>
                    <button class="auth-primary" type="submit" id="register-submit">ส่งคำขอสมัครสมาชิก <span aria-hidden="true">→</span></button>
                </form>
            <?php endif; ?>
            <p class="auth-approval-hint">ระบบไม่อนุญาตให้สมาชิกใหม่เข้าใช้งานจนกว่าแอดมินจะอนุมัติ</p>
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
    document.getElementById('register-form')?.addEventListener('submit', e => {
        const btn = document.getElementById('register-submit');
        if (btn && e.target.checkValidity()) { btn.disabled = true; btn.textContent = 'กำลังส่งคำขอ…'; }
    });
})();
</script>
</body>
</html>
