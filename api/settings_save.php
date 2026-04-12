<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

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

$tab = (string) ($data['tab'] ?? '');
$settings = $data['settings'] ?? null;
if (!is_array($settings)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing settings object'], JSON_THROW_ON_ERROR);
    exit;
}

$byTab = [
    'general' => ['site_name', 'analytics_domain', 'analytics_script_src'],
    'seo' => ['seo_site_title', 'seo_meta_description', 'seo_meta_keywords', 'seo_og_image', 'seo_auto_enabled', 'seo_locations', 'seo_service_slugs'],
    'whatsapp' => ['whatsapp_number', 'whatsapp_default_message'],
    'email' => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_secure', 'email_from', 'from_name'],
    'email_hub' => [
        'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_poll_enabled',
        'email_autoresponder_enabled', 'email_autoresponder_subject', 'email_autoresponder_body',
    ],
];

if (!isset($byTab[$tab])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown tab'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
if (!vk_settings_table_ready($pdo)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Settings table missing. Import sql/upgrade_settings.sql'], JSON_THROW_ON_ERROR);
    exit;
}

$allowed = array_flip($byTab[$tab]);

foreach ($settings as $key => $value) {
    if (!is_string($key) || !isset($allowed[$key])) {
        continue;
    }
    if (!is_string($value) && !is_numeric($value)) {
        continue;
    }
    $str = (string) $value;
    if ($key === 'smtp_password' || $key === 'imap_password') {
        $str = trim($str);
    }
    if ($key === 'smtp_password' && $str === '') {
        continue;
    }
    if ($key === 'imap_password' && $str === '') {
        continue;
    }
    vk_settings_set($pdo, $key, $str);
}

if ($tab === 'email') {
    $smtpIn = [
        'smtp_host' => trim((string) ($settings['smtp_host'] ?? '')),
        'smtp_port' => (int) ($settings['smtp_port'] ?? 587),
        'smtp_user' => trim((string) ($settings['smtp_username'] ?? '')),
        'smtp_pass' => trim((string) ($settings['smtp_password'] ?? '')),
        'smtp_secure' => (string) ($settings['smtp_secure'] ?? 'tls'),
        'from_email' => trim((string) ($settings['email_from'] ?? '')),
        'from_name' => trim((string) ($settings['from_name'] ?? '')),
    ];
    if ($smtpIn['smtp_host'] === '' && $smtpIn['smtp_user'] !== '') {
        $guess = vk_smtp_guess_defaults((string) $smtpIn['smtp_user']);
        $smtpIn['smtp_host'] = (string) $guess['smtp_host'];
        $smtpIn['smtp_port'] = (int) $guess['smtp_port'];
        $smtpIn['smtp_secure'] = (string) $guess['smtp_secure'];
    }
    vk_smtp_settings_save($pdo, $smtpIn);
    vk_autoresponder_settings_save($pdo, [
        'email_autoresponder_enabled' => ((string) ($settings['email_autoresponder_enabled'] ?? '0')) === '1' ? '1' : '0',
        'email_autoresponder_subject' => (string) ($settings['email_autoresponder_subject'] ?? ''),
        'email_autoresponder_body' => (string) ($settings['email_autoresponder_body'] ?? ''),
    ]);
    $cfgAfter = vk_smtp_settings_get($pdo);
    echo json_encode([
        'ok' => true,
        'saved' => $byTab[$tab],
        'smtp_password_configured' => ((string) ($cfgAfter['smtp_user'] ?? '')) === ''
            || trim((string) ($cfgAfter['smtp_pass'] ?? '')) !== '',
    ], JSON_THROW_ON_ERROR);
    exit;
}

if ($tab === 'email_hub') {
    vk_imap_settings_save($pdo, [
        'imap_host' => trim((string) ($settings['imap_host'] ?? '')),
        'imap_port' => (int) ($settings['imap_port'] ?? 993),
        'imap_username' => trim((string) ($settings['imap_username'] ?? '')),
        'imap_password' => trim((string) ($settings['imap_password'] ?? '')),
        'imap_poll_enabled' => ((string) ($settings['imap_poll_enabled'] ?? '0')) === '1' ? '1' : '0',
    ]);
    vk_autoresponder_settings_save($pdo, [
        'email_autoresponder_enabled' => ((string) ($settings['email_autoresponder_enabled'] ?? '0')) === '1' ? '1' : '0',
        'email_autoresponder_subject' => (string) ($settings['email_autoresponder_subject'] ?? ''),
        'email_autoresponder_body' => (string) ($settings['email_autoresponder_body'] ?? ''),
    ]);
}

echo json_encode(['ok' => true, 'saved' => $byTab[$tab]], JSON_THROW_ON_ERROR);
