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

    if ($action === 'queue') {
        echo json_encode(['ok' => true, 'queue' => vk_email_settings_queue_list($pdo, 80)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'presets') {
        echo json_encode(['ok' => true, 'presets' => vk_email_settings_presets()], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'system_info') {
        echo json_encode(['ok' => true, 'info' => vk_email_settings_system_info()], JSON_THROW_ON_ERROR);
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
        echo json_encode(['ok' => false, 'error' => 'Permission denied. Administrator access required to edit SMTP settings.'], JSON_THROW_ON_ERROR);
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
        echo json_encode(['ok' => false, 'error' => 'Permission denied. Only administrators can send test emails.'], JSON_THROW_ON_ERROR);
        exit;
    }

    $subject = trim((string) ($data['subject'] ?? 'VK Network — SMTP Test'));
    if ($subject === '') {
        $subject = 'VK Network — SMTP Test';
    }
    $message = trim((string) ($data['message'] ?? ''));
    if ($message === '') {
        $message = "SMTP test from Email Configuration Center.\n\nSent at " . date('c');
    }

    $conn = vk_email_settings_connection_test($pdo, $isSuperAdmin ? $data : null);
    $steps = $conn['steps'] ?? [];
    $steps[] = ['label' => 'Send Test Mail', 'status' => 'running'];

    $attachPath = null;
    $attachName = null;
    if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file((string) $_FILES['attachment']['tmp_name'])) {
        $attachPath = (string) $_FILES['attachment']['tmp_name'];
        $attachName = basename((string) ($_FILES['attachment']['name'] ?? 'attachment.bin'));
    }

    $res = vk_mailer_send($pdo, $to, $subject, $message, null, [
        'template_type' => 'mail_test',
        'fallback_tls' => true,
        'relaxed_ssl' => true,
        'debug' => !empty($data['debug']) || !empty(vk_email_settings_form_data($pdo)['smtp_debug']),
        'attachment_path' => $attachPath,
        'attachment_name' => $attachName,
    ]);
    if (!$res['ok']) {
        $err = (string) ($res['error'] ?? 'Send failed');
        $steps[count($steps) - 1] = ['label' => 'Send Test Mail', 'status' => 'failed', 'detail' => mb_substr($err, 0, 180)];
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => $err,
            'reasons' => $res['reasons'] ?? ($conn['reasons'] ?? []),
            'steps' => $steps,
            'detail' => $res['raw_error'] ?? null,
            'debug' => $res['debug'] ?? ($conn['transcript'] ?? null),
            'ms' => $conn['ms'] ?? null,
        ], JSON_THROW_ON_ERROR);
        exit;
    }
    $steps[count($steps) - 1] = ['label' => 'Send Test Mail', 'status' => 'success', 'detail' => 'Delivered to ' . $to];
    echo json_encode([
        'ok' => true,
        'message' => 'Test email sent successfully.',
        'steps' => $steps,
        'debug' => $res['debug'] ?? null,
        'ms' => $conn['ms'] ?? null,
    ], JSON_THROW_ON_ERROR);
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

if ($action === 'queue_process') {
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $result = vk_email_queue_process($pdo, 25);
    echo json_encode(['ok' => true, 'message' => 'Queue processed.', 'result' => $result], JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'queue_retry') {
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0 || !db_table_exists($pdo, 'email_outbound_queue')) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid queue item.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $pdo->prepare("UPDATE email_outbound_queue SET status='pending', next_attempt_at=NOW() WHERE id=? AND status IN ('failed','pending')")->execute([$id]);
    echo json_encode(['ok' => true, 'message' => 'Queued for retry.'], JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'log_resend') {
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0 || !db_table_exists($pdo, 'email_send_log')) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid log id.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $st = $pdo->prepare('SELECT * FROM email_send_log WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Log not found.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $to = trim((string) ($data['to_email'] ?? $row['to_email'] ?? ''));
    $subject = trim((string) ($data['subject'] ?? $row['subject'] ?? ''));
    $message = trim((string) ($data['message'] ?? $row['body_preview'] ?? 'Resent message'));
    $res = vk_mailer_send($pdo, $to, $subject, $message, (string) ($row['to_name'] ?? ''), [
        'template_type' => (string) ($row['template_type'] ?? 'resend'),
        'fallback_tls' => true,
        'relaxed_ssl' => true,
    ]);
    http_response_code($res['ok'] ? 200 : 422);
    echo json_encode([
        'ok' => (bool) $res['ok'],
        'message' => $res['ok'] ? 'Email resent.' : (string) ($res['error'] ?? 'Resend failed'),
        'error' => $res['ok'] ? null : ($res['error'] ?? 'Resend failed'),
        'reasons' => $res['reasons'] ?? [],
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'log_delete') {
    if (!$isSuperAdmin) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $id = (int) ($data['id'] ?? 0);
    if ($id > 0 && db_table_exists($pdo, 'email_send_log')) {
        $pdo->prepare('DELETE FROM email_send_log WHERE id = ?')->execute([$id]);
    }
    echo json_encode(['ok' => true, 'message' => 'Log deleted.'], JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_THROW_ON_ERROR);
