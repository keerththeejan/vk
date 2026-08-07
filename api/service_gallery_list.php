<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/service_gallery_admin_service.php';
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
if (!db_table_exists($pdo, 'web_services')) {
    echo json_encode(['ok' => true, 'items' => [], 'total' => 0, 'page' => 1, 'per_page' => 12, 'has_more' => false], JSON_THROW_ON_ERROR);
    exit;
}

$result = vk_sg_admin_list($pdo, [
    'service_id' => (int) ($_GET['service_id'] ?? 0),
    'page' => (int) ($_GET['page'] ?? 1),
    'per_page' => (int) ($_GET['per_page'] ?? 12),
    'sort' => (string) ($_GET['sort'] ?? 'newest'),
    'q' => (string) ($_GET['q'] ?? ''),
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to' => (string) ($_GET['date_to'] ?? ''),
    'category' => '',
    'status' => '',
    'featured' => '',
]);

echo json_encode([
    'ok' => true,
    'items' => $result['items'],
    'total' => $result['total'],
    'page' => $result['page'],
    'per_page' => $result['per_page'],
    'has_more' => $result['has_more'],
], JSON_THROW_ON_ERROR);
