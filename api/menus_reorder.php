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
if (!is_array($data) || !isset($data['order']) || !is_array($data['order'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing order array'], JSON_THROW_ON_ERROR);
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

$order = $data['order'];
$pdo->beginTransaction();
try {
    $st = $pdo->prepare('UPDATE menus SET sort_order = ? WHERE id = ?');
    $pos = 0;
    foreach ($order as $idRaw) {
        $id = (int) $idRaw;
        if ($id <= 0) {
            continue;
        }
        $st->execute([$pos * 10, $id]);
        ++$pos;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Reorder failed'], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
