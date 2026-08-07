<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/init.php';
require_settings_admin();

$pdo = db();
vk_settings_seed_defaults($pdo);
$payload = vk_settings_export($pdo);
vk_settings_audit($pdo, 'export');

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="vk-settings-' . date('Ymd-His') . '.json"');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
