<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';

$secret = function_exists('vk_email_env_str') ? vk_email_env_str('VK_CRON_SECRET', '') : '';
if ($secret === '') {
    $secret = (string) (getenv('VK_CRON_SECRET') ?: '');
}
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
if ($secret === '' || $token === '' || !hash_equals($secret, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden — set VK_CRON_SECRET and pass ?token= matching value'], JSON_THROW_ON_ERROR);
    exit;
}

try {
    $pdo = db();
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database unavailable'], JSON_THROW_ON_ERROR);
    exit;
}

$imap = [];
$queue = ['processed' => 0, 'sent' => 0, 'failed' => 0];

if (function_exists('vk_email_imap_poll')) {
    $imap = vk_email_imap_poll($pdo);
}
if (function_exists('vk_email_queue_process')) {
    $queue = vk_email_queue_process($pdo);
}

echo json_encode(
    [
        'ok' => true,
        'time' => date('c'),
        'imap' => $imap,
        'outbound_queue' => $queue,
    ],
    JSON_THROW_ON_ERROR
);
