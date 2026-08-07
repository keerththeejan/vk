<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/service_gallery_admin_service.php';
require_admin();

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
$perms = vk_sg_admin_permissions($role);
if (!$perms['can_upload']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Upload not permitted.'], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

require_csrf((string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$serviceId = (int) ($_POST['service_id'] ?? 0);
$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing file.'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
$result = vk_sg_admin_upload($pdo, $file, $serviceId, (int) ($_SESSION['user_id'] ?? 0));
http_response_code($result['ok'] ? 200 : 422);
echo json_encode($result['ok'] ? ['ok' => true, 'item' => $result['item']] : $result, JSON_THROW_ON_ERROR);
