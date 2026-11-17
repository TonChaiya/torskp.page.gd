<?php
declare(strict_types=1);
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';

startSecureSession();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method Not Allowed';
    exit;
}
verifyCsrf();
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
auditEvent('logout', $userId);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
