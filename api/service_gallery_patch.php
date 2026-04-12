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
$title = trim((string) ($data['title'] ?? ''));
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid image id.'], JSON_THROW_ON_ERROR);
    exit;
}
if ($title === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Title is required.'], JSON_THROW_ON_ERROR);
    exit;
}
if (mb_strlen($title) > 255) {
    $title = mb_substr($title, 0, 255);
}

$pdo = db();
vk_service_gallery_auto_migrate($pdo);
$exists = $pdo->prepare('SELECT id FROM service_gallery WHERE id = ? LIMIT 1');
$exists->execute([$id]);
if (!$exists->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Image not found.'], JSON_THROW_ON_ERROR);
    exit;
}
$pdo->prepare('UPDATE service_gallery SET title = ? WHERE id = ?')->execute([$title, $id]);

echo json_encode(['ok' => true, 'title' => $title], JSON_THROW_ON_ERROR);
