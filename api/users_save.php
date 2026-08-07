<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

$pdo = db();
require_once dirname(__DIR__) . '/includes/users_management_service.php';
vk_auth_ensure_schema($pdo);

$perms = vk_users_session_permissions($pdo);
if (!$perms['can_manage']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_THROW_ON_ERROR);
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

if (!empty($data['csrf_token']) || !empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    require_csrf((string) ($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
}

$result = vk_users_save($pdo, $data, (int) ($_SESSION['user_id'] ?? 0), $perms);
http_response_code($result['ok'] ? 200 : ($result['error'] === 'User not found.' ? 404 : 422));
echo json_encode($result, JSON_THROW_ON_ERROR);
