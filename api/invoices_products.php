<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/invoices_service.php';

vk_api_require_admin();

$pdo = db();
vk_ensure_invoices_schema($pdo);

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'search'));
$q = trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));

try {
    if ($action === 'recent' || $action === 'favourite' || $action === 'favorites') {
        $items = vk_invoice_recent_products($pdo, $limit);
        echo json_encode(['ok' => true, 'items' => $items], JSON_THROW_ON_ERROR);
        exit;
    }
    if ($action === 'barcode') {
        $barcode = trim((string) ($_GET['barcode'] ?? $_POST['barcode'] ?? $q));
        $items = vk_invoice_search_products($pdo, $barcode, 5);
        // Prefer exact barcode match when available
        $exact = null;
        foreach ($items as $it) {
            if (!empty($it['barcode']) && strcasecmp((string) $it['barcode'], $barcode) === 0) {
                $exact = $it;
                break;
            }
            if (!empty($it['product_code']) && strcasecmp((string) $it['product_code'], $barcode) === 0) {
                $exact = $it;
                break;
            }
        }
        echo json_encode([
            'ok' => true,
            'item' => $exact,
            'items' => $items,
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    $items = vk_invoice_search_products($pdo, $q, $limit);
    echo json_encode(['ok' => true, 'items' => $items], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
}
