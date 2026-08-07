<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/service_gallery_admin_service.php';
require_admin();

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
$perms = vk_sg_admin_permissions($role);
if (!$perms['can_edit']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Edit not permitted.'], JSON_THROW_ON_ERROR);
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

require_csrf((string) ($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$pdo = db();
$result = vk_sg_admin_update($pdo, (int) ($data['id'] ?? 0), $data, (int) ($_SESSION['user_id'] ?? 0));
http_response_code($result['ok'] ? 200 : 422);
echo json_encode($result['ok'] ? ['ok' => true, 'title' => $result['item']['title'] ?? ''] : $result, JSON_THROW_ON_ERROR);
