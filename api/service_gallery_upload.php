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

$serviceId = (int) ($_POST['service_id'] ?? 0);
if ($serviceId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Select a valid service.'], JSON_THROW_ON_ERROR);
    exit;
}

$file = $_FILES['file'] ?? null;
if (!is_array($file)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing file.'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
vk_service_gallery_auto_migrate($pdo);

$chk = $pdo->prepare('SELECT id FROM web_services WHERE id = ? AND active = 1 LIMIT 1');
$chk->execute([$serviceId]);
if (!$chk->fetchColumn()) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid or inactive service.'], JSON_THROW_ON_ERROR);
    exit;
}

$res = vk_service_gallery_process_upload($file, $serviceId);
if (($res['error'] ?? null) !== null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string) $res['error']], JSON_THROW_ON_ERROR);
    exit;
}

$path = (string) ($res['path'] ?? '');
$origName = vk_service_gallery_sanitize_original_name((string) ($file['name'] ?? ''));
$title = $origName !== '' ? pathinfo($origName, PATHINFO_FILENAME) : pathinfo($path, PATHINFO_FILENAME);
$title = trim((string) $title);
if ($title === '') {
    $title = 'Gallery image';
}

$st = $pdo->prepare(
    'INSERT INTO service_gallery (service_id, image_path, title, original_filename) VALUES (?, ?, ?, ?)'
);
$st->execute([$serviceId, $path, mb_substr($title, 0, 255), $origName !== '' ? mb_substr($origName, 0, 255) : null]);
$newId = (int) $pdo->lastInsertId();

$wst = $pdo->prepare(
    'SELECT w.name AS service_name, w.slug AS service_slug FROM web_services w WHERE w.id = ? LIMIT 1'
);
$wst->execute([$serviceId]);
$meta = $wst->fetch(PDO::FETCH_ASSOC) ?: [];

echo json_encode([
    'ok' => true,
    'item' => [
        'id' => $newId,
        'service_id' => $serviceId,
        'image_path' => $path,
        'image_url' => public_asset_url($path),
        'title' => $title,
        'original_filename' => $origName,
        'created_at' => date('Y-m-d H:i:s'),
        'service_name' => (string) ($meta['service_name'] ?? ''),
        'service_slug' => (string) ($meta['service_slug'] ?? ''),
    ],
], JSON_THROW_ON_ERROR);
