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
$name = trim((string) ($data['name'] ?? ''));
$slug = strtolower(trim((string) ($data['slug'] ?? '')));
$urlRaw = trim((string) ($data['url'] ?? ''));
$icon = trim((string) ($data['icon'] ?? ''));
$status = strtolower(trim((string) ($data['status'] ?? 'active')));

if ($name === '' || mb_strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name is required (max 100 chars).'], JSON_THROW_ON_ERROR);
    exit;
}
if ($slug === '' || !preg_match('/^[a-z0-9\-]{1,100}$/', $slug)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Slug: lowercase letters, numbers, hyphen only.'], JSON_THROW_ON_ERROR);
    exit;
}
if (!in_array($status, ['active', 'inactive'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid status.'], JSON_THROW_ON_ERROR);
    exit;
}
if (mb_strlen($icon) > 100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Icon too long.'], JSON_THROW_ON_ERROR);
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

$url = vk_site_menus_sanitize_url($urlRaw);
$iconVal = $icon === '' ? null : $icon;

if ($id <= 0) {
    $uq = $pdo->prepare('SELECT id FROM menus WHERE slug = ? LIMIT 1');
    $uq->execute([$slug]);
    if ($uq->fetchColumn()) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Slug already in use.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $max = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0) FROM menus')->fetchColumn();
    $pdo->prepare(
        'INSERT INTO menus (name, slug, url, icon, sort_order, status) VALUES (?,?,?,?,?,?)'
    )->execute([$name, $slug, $url, $iconVal, $max + 10, $status]);
    vk_cache_invalidate_after_write('menus');
    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()], JSON_THROW_ON_ERROR);
    exit;
}

$uq = $pdo->prepare('SELECT id FROM menus WHERE slug = ? AND id != ? LIMIT 1');
$uq->execute([$slug, $id]);
if ($uq->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Slug already in use.'], JSON_THROW_ON_ERROR);
    exit;
}

$ex = $pdo->prepare('SELECT id FROM menus WHERE id = ? LIMIT 1');
$ex->execute([$id]);
if (!$ex->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Menu not found.'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo->prepare(
    'UPDATE menus SET name = ?, slug = ?, url = ?, icon = ?, status = ? WHERE id = ?'
)->execute([$name, $slug, $url, $iconVal, $status, $id]);

vk_cache_invalidate_after_write('menus');
echo json_encode(['ok' => true, 'id' => $id], JSON_THROW_ON_ERROR);
