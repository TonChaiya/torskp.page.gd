<?php
declare(strict_types=1);

require __DIR__ . '/core/word_export_lib.php';
require __DIR__ . '/core/config.php';
require __DIR__ . '/core/auth.php';
requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$filename = trim((string) ($_POST['filename'] ?? 'document.docx'));
$html = (string) ($_POST['html'] ?? '');
$css = (string) ($_POST['css'] ?? '');
$profile = trim((string) ($_POST['profile'] ?? 'default'));

if (trim($html) === '') {
    http_response_code(400);
    echo 'Missing document HTML';
    exit;
}
if (strlen($html) > 5 * 1024 * 1024 || strlen($css) > 1024 * 1024) {
    http_response_code(413);
    echo 'Document is too large';
    exit;
}
if (!in_array($profile, ['default', 'principle', 'invitation', 'meeting', 'tor'], true)) {
    $profile = 'default';
}

outputWordHtml($html, $filename, $css, $profile);
