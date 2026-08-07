<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/quotations_service.php';

vk_api_require_admin();

$pdo = db();
vk_ensure_quotations_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? null);
    if ($token !== null && $token !== '') {
        require_csrf((string) $token);
    }
}

$q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

try {
    $items = vk_quotation_search_products($pdo, $q, $limit);
    echo json_encode(['ok' => true, 'items' => $items], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
}
