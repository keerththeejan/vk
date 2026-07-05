<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap_core.php';
vk_api_require_admin();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Cache-Control: private, max-age=20');
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/dashboard_stats_service.php';

$pdo = db();
$payload = vk_dashboard_stats_fetch($pdo, true);

echo json_encode([
    'ok' => true,
    'generated_at' => time(),
    'cached' => true,
    ...$payload,
], JSON_THROW_ON_ERROR);
