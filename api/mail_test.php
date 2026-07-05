<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/email_settings_service.php';
require_admin();

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
$perms = vk_email_settings_permissions($role);
if (!$perms['can_edit']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON'], JSON_THROW_ON_ERROR);
    exit;
}

require_csrf((string) ($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$to = trim((string) ($data['to'] ?? ''));
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Valid recipient email required'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
if (!vk_settings_table_ready($pdo)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Settings table missing'], JSON_THROW_ON_ERROR);
    exit;
}

$res = vk_mailer_send(
    $pdo,
    $to,
    'VK Network — test email',
    "This is a test message from your VK admin panel.\r\n\r\nSent at " . date('c'),
    null,
    [
        'template_type' => 'mail_test',
        'fallback_tls' => true,
    ]
);
if (!$res['ok']) {
    http_response_code(500);
    $err = vk_email_settings_friendly_error((string) ($res['error'] ?? ''));
    echo json_encode(['ok' => false, 'error' => $err, 'detail' => $res['error'] ?? null], JSON_THROW_ON_ERROR);
    exit;
}
echo json_encode(['ok' => true, 'message' => 'Test email sent'], JSON_THROW_ON_ERROR);
