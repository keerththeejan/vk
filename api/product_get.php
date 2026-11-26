<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }
try {
    $pdo = db();
    $columns = array_column($pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $tables = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
    $joins = [];
    $extras = [];
    if (in_array('category_id', $columns, true) && in_array('categories', $tables, true)) {
        $joins[] = 'LEFT JOIN categories c ON c.id = p.category_id';
        $extras[] = 'c.name AS category_name';
    }
    if (in_array('brand_id', $columns, true) && in_array('brands', $tables, true)) {
        $joins[] = 'LEFT JOIN brands b ON b.id = p.brand_id';
        $extras[] = 'b.name AS brand_name';
    }
    if (in_array('supplier_id', $columns, true) && in_array('suppliers', $tables, true)) {
        $joins[] = 'LEFT JOIN suppliers s ON s.id = p.supplier_id';
        $extras[] = 's.name AS supplier_name';
    }
    if (in_array('product_images', $tables, true)) {
        $extras[] = '(SELECT pi.path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_thumbnail DESC, pi.sort_order ASC, pi.id ASC LIMIT 1) AS thumbnail_path';
    }
    $stmt = $pdo->prepare('SELECT p.*' . ($extras ? ', ' . implode(', ', $extras) : '') . ' FROM products p ' . implode(' ', $joins) . ' WHERE p.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
    echo json_encode(['data' => $product], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('product_get: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load the product.']);
}
