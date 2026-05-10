<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_settings_admin();
require_csrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ''));

if (empty($_FILES['settings_file']) || ($_FILES['settings_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Choose a valid JSON backup file.'], JSON_THROW_ON_ERROR);
    exit;
}
if ((int) $_FILES['settings_file']['size'] > 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Backup file must be under 1MB.'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents((string) $_FILES['settings_file']['tmp_name']) ?: '';
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['settings']) || !is_array($data['settings'])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid settings backup format.'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
vk_settings_seed_defaults($pdo);
$defaults = vk_settings_defaults();
$imported = 0;
foreach ($data['settings'] as $row) {
    if (!is_array($row)) {
        continue;
    }
    $key = (string) ($row['key_name'] ?? $row['setting_key'] ?? '');
    if ($key === '' || !preg_match('/^[a-z0-9_]{2,128}$/i', $key)) {
        continue;
    }
    $value = (string) ($row['value'] ?? $row['setting_value'] ?? '');
    $group = (string) ($row['setting_group'] ?? ($defaults[$key][1] ?? 'general'));
    $type = (string) ($row['setting_type'] ?? ($defaults[$key][2] ?? 'text'));
    vk_settings_set($pdo, $key, $value, $group, $type);
    $imported++;
}
vk_settings_audit($pdo, 'import', '', (string) $imported);

echo json_encode(['ok' => true, 'imported' => $imported], JSON_THROW_ON_ERROR);
