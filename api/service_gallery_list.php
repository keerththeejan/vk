<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
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
vk_service_gallery_auto_migrate($pdo);

$serviceId = max(0, (int) ($_GET['service_id'] ?? 0));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(48, max(1, (int) ($_GET['per_page'] ?? 12)));
$q = trim((string) ($_GET['q'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$sort = strtolower(trim((string) ($_GET['sort'] ?? 'newest')));
$order = $sort === 'oldest' ? 'ASC' : 'DESC';
if (!in_array($order, ['ASC', 'DESC'], true)) {
    $order = 'DESC';
}

$where = ['1=1'];
$params = [];

if ($serviceId > 0) {
    $where[] = 'g.service_id = ?';
    $params[] = $serviceId;
}

if ($q !== '') {
    $qClean = preg_replace('/[^\p{L}\p{N}\s._\-]/u', '', $q) ?? '';
    if ($qClean !== '') {
        $like = '%' . $qClean . '%';
        $where[] = '(g.title LIKE ? OR g.original_filename LIKE ? OR g.image_path LIKE ? OR w.name LIKE ? OR w.slug LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'DATE(g.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'DATE(g.created_at) <= ?';
    $params[] = $dateTo;
}

$whereSql = implode(' AND ', $where);

$countSt = $pdo->prepare(
    "SELECT COUNT(*) FROM service_gallery g
     INNER JOIN web_services w ON w.id = g.service_id AND w.active = 1
     WHERE $whereSql"
);
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();

$offset = ($page - 1) * $perPage;
$sql = "SELECT g.id, g.service_id, g.image_path, g.title, g.original_filename, g.created_at,
               w.name AS service_name, w.slug AS service_slug
        FROM service_gallery g
        INNER JOIN web_services w ON w.id = g.service_id AND w.active = 1
        WHERE $whereSql
        ORDER BY g.created_at $order, g.id $order
        LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset;

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$items = [];
foreach ($rows as $r) {
    $resolved = vk_service_gallery_resolve_existing_path((string) ($r['image_path'] ?? ''));
    $path = $resolved ?? '';
    $url = $path !== '' ? public_asset_url($path) : '';
    $items[] = [
        'id' => (int) ($r['id'] ?? 0),
        'service_id' => (int) ($r['service_id'] ?? 0),
        'image_path' => $path,
        'image_url' => $url,
        'title' => trim((string) ($r['title'] ?? '')),
        'original_filename' => trim((string) ($r['original_filename'] ?? '')),
        'created_at' => (string) ($r['created_at'] ?? ''),
        'service_name' => (string) ($r['service_name'] ?? ''),
        'service_slug' => (string) ($r['service_slug'] ?? ''),
        'is_sample' => false,
    ];
}

if ($total === 0 && $page === 1) {
    $items = vk_service_gallery_admin_sample_items();
    $total = count($items);
}

echo json_encode([
    'ok' => true,
    'items' => $items,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
    'has_more' => $total > 0 ? ($offset + count($items)) < $total : false,
], JSON_THROW_ON_ERROR);
