<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/service_gallery_admin_service.php';
require_admin();

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
$perms = vk_sg_admin_permissions($role);
if (!$perms['can_delete']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Delete not permitted.'], JSON_THROW_ON_ERROR);
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

$id = (int) ($data['id'] ?? 0);
$pdo = db();
vk_service_gallery_auto_migrate($pdo);
$res = vk_service_gallery_delete_by_id($pdo, $id);
if ($res['ok']) {
    vk_sg_admin_audit($pdo, (int) ($_SESSION['user_id'] ?? 0), 'gallery_delete', $id, []);
}
http_response_code($res['ok'] ? 200 : ($id <= 0 ? 400 : 404));
echo json_encode($res['ok'] ? ['ok' => true] : $res, JSON_THROW_ON_ERROR);
