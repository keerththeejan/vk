<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

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

$id = (int) ($data['id'] ?? 0);
$pdo = db();
vk_service_gallery_auto_migrate($pdo);
$res = vk_service_gallery_delete_by_id($pdo, $id);
if (!$res['ok']) {
    http_response_code($id <= 0 ? 400 : 404);
    echo json_encode(['ok' => false, 'error' => $res['error'] ?? 'Delete failed'], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
