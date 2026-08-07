<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_settings_admin();

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
require_csrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '')));

$pdo = db();
vk_settings_ensure_schema($pdo);
$count = 0;
foreach (vk_settings_defaults() as $key => $meta) {
    vk_settings_set($pdo, $key, (string) $meta[0], (string) $meta[1], (string) $meta[2]);
    $count++;
}
vk_settings_audit($pdo, 'restore_defaults', '', (string) $count);

echo json_encode(['ok' => true, 'restored' => $count], JSON_THROW_ON_ERROR);
