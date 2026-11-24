<?php
declare(strict_types=1);

const SESSION_IDLE_TIMEOUT = 1800;
const SESSION_ABSOLUTE_TIMEOUT = 28800;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 900;

function applySecurityHeaders(): void
{
    if (headers_sent()) return;
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, max-age=0');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function startSecureSession(): void
{
    applySecurityHeaders();
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('TORSKPSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    $createdAt = (int) ($_SESSION['created_at'] ?? 0);
    $lastSeen = (int) ($_SESSION['last_seen'] ?? 0);
    if (($createdAt > 0 && $now - $createdAt > SESSION_ABSOLUTE_TIMEOUT)
        || ($lastSeen > 0 && $now - $lastSeen > SESSION_IDLE_TIMEOUT)) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['created_at'] = (int) ($_SESSION['created_at'] ?? $now);
    $_SESSION['last_seen'] = $now;
}

function ensureUsersTable(): void
{
    requireDatabaseTables(['users', 'login_attempts', 'audit_logs']);
}

function currentUser(): ?array
{
    startSecureSession();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    // Re-read approval state for every HTTP request, including existing sessions.
    $stmt = db()->prepare('SELECT id, username, full_name, department, role, account_status, must_change_password, password_changed_at, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user || (string) ($user['account_status'] ?? '') !== 'approved') {
        $_SESSION = [];
        session_regenerate_id(true);
        $user = null;
    }
    return $user;
}

function requireLogin(): void
{
    ensureUsersTable();
    $user = currentUser();
    if ($user) {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ((int) ($user['must_change_password'] ?? 0) === 1 && !in_array($script, ['change_password.php', 'logout.php'], true)) {
            header('Location: change_password.php');
            exit;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            verifyCsrf();
        }
        return;
    }

    $target = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: login.php?next=' . rawurlencode($target));
    exit;
}

function requireAdmin(): void
{
    requireLogin();
    $user = currentUser();
    if (($user['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo 'ไม่มีสิทธิ์เข้าถึงส่วนผู้ดูแลระบบ';
        exit;
    }
}

function csrfToken(): string
{
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    startSecureSession();
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        echo 'คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง';
        exit;
    }
}

function clientIp(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function loginThrottleKey(string $username): string
{
    return hash('sha256', mb_strtolower(trim($username), 'UTF-8'));
}


function loginIsLocked(string $username): bool
{
    $stmt = db()->prepare('SELECT locked_until FROM login_attempts WHERE identifier_hash = ? AND ip_address = ?');
    $stmt->execute([loginThrottleKey($username), clientIp()]);
    $lockedUntil = $stmt->fetchColumn();

    return $lockedUntil !== false
        && $lockedUntil !== null
        && strtotime((string) $lockedUntil) > time();
}


function registerLoginFailure(string $username): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $key = loginThrottleKey($username);
        $ip = clientIp();
        $stmt = $pdo->prepare('SELECT attempts, first_attempt_at FROM login_attempts WHERE identifier_hash = ? AND ip_address = ? FOR UPDATE');
        $stmt->execute([$key, $ip]);
        $row = $stmt->fetch();
        $attempts = 1;
        if ($row && strtotime((string) $row['first_attempt_at']) >= time() - LOGIN_LOCK_SECONDS) {
            $attempts = (int) $row['attempts'] + 1;
        }
        $lockedUntil = $attempts >= LOGIN_MAX_ATTEMPTS ? date('Y-m-d H:i:s', time() + LOGIN_LOCK_SECONDS) : null;
        $stmt = $pdo->prepare('INSERT INTO login_attempts (identifier_hash, ip_address, attempts, first_attempt_at, locked_until) VALUES (?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), first_attempt_at = IF(VALUES(attempts)=1, NOW(), first_attempt_at), locked_until = VALUES(locked_until), updated_at = NOW()');
        $stmt->execute([$key, $ip, $attempts, $lockedUntil]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function clearLoginFailures(string $username): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE identifier_hash = ? AND ip_address = ?');
    $stmt->execute([loginThrottleKey($username), clientIp()]);
}

function auditEvent(string $eventType, ?int $userId = null, array $details = []): void
{
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (user_id, event_type, ip_address, details_json) VALUES (?, ?, ?, ?)');
        $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $stmt->execute([$userId, mb_substr($eventType, 0, 100, 'UTF-8'), clientIp(), $json === false ? null : $json]);
    } catch (Throwable $e) {
        error_log('Audit log failure: ' . $e->getMessage());
    }
}

function passwordPolicyError(string $password, string $username = ''): string
{
    if (mb_strlen($password, 'UTF-8') < 6) return 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    if ($username !== '' && hash_equals(mb_strtolower($username, 'UTF-8'), mb_strtolower($password, 'UTF-8'))) return 'รหัสผ่านต้องไม่เหมือนชื่อผู้ใช้';
    if (!preg_match('/\p{L}/u', $password) || !preg_match('/\p{N}/u', $password)) return 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข';
    return '';
}
