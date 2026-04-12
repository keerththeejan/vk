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
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid menu.'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
require_once dirname(__DIR__) . '/includes/site_menus.php';
vk_site_menus_ensure_schema($pdo);
if (!vk_site_menus_table_exists($pdo)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Menus table missing'], JSON_THROW_ON_ERROR);
    exit;
}

$chk = $pdo->prepare('SELECT id FROM menus WHERE id = ? LIMIT 1');
$chk->execute([$id]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Menu not found.'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo->prepare('DELETE FROM menus WHERE id = ?')->execute([$id]);

echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
