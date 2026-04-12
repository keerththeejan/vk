<?php
declare(strict_types=1);

/**
 * Automated email bootstrap: sync environment → database, probe SMTP / IMAP / POP3.
 *
 * Usage (secrets in .env or server env only — never commit passwords):
 *   GET  /VK/setup/email-auto-config.php?token=YOUR_VK_SETUP_SECRET
 *   POST /VK/setup/email-auto-config.php  JSON: { "token": "...", "force": false, "apply_preset_vkitnet": true, "enable_imap_poll": true, "send_test": false, "test_send_to": "you@example.com" }
 *
 * Re-run is blocked once email_auto_setup_done=1 unless force:true.
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
require_once dirname(__DIR__) . '/includes/email_system.php';
require_once dirname(__DIR__) . '/includes/email_auto_validation.php';

$rawInput = file_get_contents('php://input');
$parsedBody = ($rawInput !== false && $rawInput !== '') ? json_decode($rawInput, true) : null;
if (!is_array($parsedBody)) {
    $parsedBody = [];
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if ($token === '' && isset($parsedBody['token'])) {
    $token = (string) $parsedBody['token'];
}

if (!vk_email_setup_secret_ok($token)) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Forbidden. Set VK_SETUP_SECRET in the environment (or .env) and pass the same value as token.',
        'framework' => vk_mail_detect_framework(),
        'imap_extension_loaded' => extension_loaded('imap'),
    ], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable'], JSON_THROW_ON_ERROR);
    exit;
}

if (!vk_settings_table_ready($pdo)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Import sql/upgrade_settings.sql first'], JSON_THROW_ON_ERROR);
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $cfg = vk_email_resolve_from_environment();
    echo json_encode([
        'ok' => true,
        'framework' => vk_mail_detect_framework(),
        'imap_extension_loaded' => extension_loaded('imap'),
        'imap_extension_hint' => extension_loaded('imap') ? null : 'Enable extension=imap in php.ini, then restart the web server.',
        'phpmailer_present' => is_file(ROOT_PATH . '/vendor/autoload.php'),
        'email_auto_setup_done' => vk_email_auto_setup_completed($pdo),
        'env_smtp_preview' => [
            'host' => $cfg['smtp_host'],
            'port' => $cfg['smtp_port'],
            'user' => $cfg['smtp_user'],
            'password_masked' => vk_email_mask_secret($cfg['smtp_pass']),
            'from' => $cfg['from_email'],
        ],
        'env_imap_preview' => [
            'host' => $cfg['imap_host'],
            'port' => $cfg['imap_port'],
            'user' => $cfg['imap_user'],
            'password_masked' => vk_email_mask_secret($cfg['imap_pass']),
        ],
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$body = $parsedBody;

$force = !empty($body['force']);
if (vk_email_auto_setup_completed($pdo) && !$force) {
    http_response_code(403);
    echo json_encode([
        'ok' => false,
        'error' => 'Setup already marked complete. POST again with "force": true to re-run.',
        'email_auto_setup_done' => true,
    ], JSON_THROW_ON_ERROR);
    exit;
}

$applyPreset = !empty($body['apply_preset_vkitnet']);
$enablePoll = array_key_exists('enable_imap_poll', $body) ? !empty($body['enable_imap_poll']) : true;
$sendTest = !empty($body['send_test']);
$testTo = isset($body['test_send_to']) ? trim((string) $body['test_send_to']) : '';

$sync = vk_email_sync_environment_to_database($pdo, $enablePoll, $applyPreset);

if (!$sync['saved']) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => $sync['detail']['error'] ?? 'Sync failed',
        'detail' => $sync['detail'],
        'framework' => vk_mail_detect_framework(),
    ], JSON_THROW_ON_ERROR);
    exit;
}

$skipInbound = !empty($body['skip_inbound_check']);
$validation = vk_email_run_setup_validation($pdo, $testTo !== '' ? $testTo : null, $sendTest && $testTo !== '', $skipInbound);

vk_email_auto_setup_set_flag($pdo, (bool) ($validation['overall_ok'] ?? false));

echo json_encode([
    'ok' => (bool) ($validation['overall_ok'] ?? false),
    'framework' => vk_mail_detect_framework(),
    'synced' => $sync['detail'],
    'validation' => [
        'messages' => $validation['messages'],
        'smtp' => [
            'probe_ok' => (bool) ($validation['smtp']['probe']['ok'] ?? false),
            'profile' => $validation['smtp']['probe']['profile'] ?? null,
            'tried' => $validation['smtp']['probe']['tried'] ?? [],
            'send_test' => $validation['smtp']['send_test'] ?? null,
        ],
        'inbound' => (static function (array $in): array {
            $p = $in['probe'] ?? null;
            $base = [
                'skipped' => (bool) ($in['skipped'] ?? false),
                'reason' => $in['reason'] ?? null,
            ];
            if (!is_array($p)) {
                return array_merge($base, ['probe_ok' => null, 'method' => null, 'message_count' => 0, 'tried' => []]);
            }
            return array_merge($base, [
                'probe_ok' => (bool) ($p['ok'] ?? false),
                'method' => $p['method'] ?? null,
                'message_count' => (int) ($p['message_count'] ?? 0),
                'tried' => $p['tried'] ?? [],
            ]);
        })($validation['inbound']),
    ],
    'email_auto_setup_done' => vk_email_auto_setup_completed($pdo),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
