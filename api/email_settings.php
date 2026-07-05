<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/email_settings_service.php';

vk_api_require_admin();

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
$perms = vk_email_settings_permissions($role);
if (!$perms['can_access']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$pdo = db();
$actorId = (int) ($_SESSION['user_id'] ?? 0);
$isSuperAdmin = $perms['can_edit'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $action = (string) ($_GET['action'] ?? 'config');

    if ($action === 'config') {
        echo json_encode(['ok' => true, 'config' => vk_email_settings_form_data($pdo), 'can_edit' => $isSuperAdmin], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'templates') {
        $key = (string) ($_GET['key'] ?? 'registration');
        $company = (string) vk_settings_get($pdo, 'company_name', 'VK Network');
        echo json_encode([
            'ok' => true,
            'templates' => vk_email_settings_templates(),
            'html' => vk_email_settings_template_html($key, $company),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'export') {
        if (!$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
            exit;
        }
        echo json_encode(vk_email_settings_export($pdo), JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'logs') {
        echo json_encode(['ok' => true, 'logs' => vk_email_settings_send_log($pdo, 100)], JSON_THROW_ON_ERROR);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action'], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}
require_csrf((string) ($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$action = (string) ($data['action'] ?? 'save');

if ($action === 'save') {
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Only Super Admin can edit SMTP settings.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $result = vk_email_settings_save($pdo, $data, $actorId);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'test_connection') {
    $result = vk_email_settings_connection_test($pdo, $isSuperAdmin ? $data : null);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'send_test') {
    $to = trim((string) ($data['to'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Valid recipient email required.'], JSON_THROW_ON_ERROR);
        exit;
    }
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Only Super Admin can send test emails.'], JSON_THROW_ON_ERROR);
        exit;
    }

    $steps = [
        ['label' => 'Connecting', 'status' => 'success'],
        ['label' => 'Authenticating', 'status' => 'success'],
        ['label' => 'Sending', 'status' => 'running'],
    ];
    $res = vk_mailer_send($pdo, $to, 'VK Network — SMTP Test', "SMTP test from Email Configuration Center.\n\nSent at " . date('c'), null, [
        'template_type' => 'mail_test',
        'fallback_tls' => true,
    ]);
    if (!$res['ok']) {
        $err = vk_email_settings_friendly_error((string) ($res['error'] ?? ''));
        $steps[2] = ['label' => 'Sending', 'status' => 'failed', 'detail' => $isSuperAdmin ? (string) ($res['error'] ?? $err) : $err];
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $err, 'steps' => $steps, 'detail' => $isSuperAdmin ? ($res['error'] ?? null) : null], JSON_THROW_ON_ERROR);
        exit;
    }
    $steps[2] = ['label' => 'Sending', 'status' => 'success'];
    echo json_encode(['ok' => true, 'message' => 'Test email sent successfully.', 'steps' => $steps], JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'import') {
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $payload = $data['config'] ?? $data;
    $result = vk_email_settings_import($pdo, is_array($payload) ? $payload : [], $actorId);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'restore_defaults') {
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $result = vk_email_settings_restore_defaults($pdo, $actorId);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'save_inbox') {
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
        exit;
    }
    require_once dirname(__DIR__) . '/includes/email_system.php';
    vk_imap_settings_save($pdo, [
        'imap_host' => trim((string) ($data['imap_host'] ?? '')),
        'imap_port' => (int) ($data['imap_port'] ?? 993),
        'imap_username' => trim((string) ($data['imap_username'] ?? '')),
        'imap_password' => trim((string) ($data['imap_password'] ?? '')),
        'imap_poll_enabled' => !empty($data['imap_poll_enabled']) ? '1' : '0',
    ]);
    vk_autoresponder_settings_save($pdo, [
        'email_autoresponder_enabled' => !empty($data['email_autoresponder_enabled']) ? '1' : '0',
        'email_autoresponder_subject' => (string) ($data['email_autoresponder_subject'] ?? ''),
        'email_autoresponder_body' => (string) ($data['email_autoresponder_body'] ?? ''),
    ]);
    vk_settings_audit($pdo, 'email_inbox_save', 'imap', '[inbox]');
    echo json_encode(['ok' => true, 'message' => 'Inbox settings saved.'], JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_THROW_ON_ERROR);
