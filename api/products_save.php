<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = array_merge($_POST, $_FILES);
try {
    $pdo = db();
    $id = isset($input['id']) ? (int)$input['id'] : null;
    $name = trim($input['name'] ?? '');
    $sku = trim($input['sku'] ?? null);
    $cost = $input['cost_price'] ?? 0;
    $price = $input['selling_price'] ?? 0;
    $category_id = $input['category_id'] ?? null;
    $brand_id = $input['brand_id'] ?? null;
    $supplier_id = $input['supplier_id'] ?? null;
    $status = $input['status'] ?? 'active';

    if ($name === '') {
        throw new RuntimeException('Product name is required');
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE products SET name=:name, sku=:sku, cost_price=:cost, selling_price=:price, category_id=:cat, brand_id=:brand, supplier_id=:sup, status=:status, updated_at=NOW() WHERE id=:id");
        $stmt->execute([':name'=>$name,':sku'=>$sku,':cost'=>$cost,':price'=>$price,':cat'=>$category_id,':brand'=>$brand_id,':sup'=>$supplier_id,':status'=>$status,':id'=>$id]);
        $newId = $id;
        $action = 'updated';
    } else {
        $slug = preg_replace('/[^a-z0-9\-]+/i','-', strtolower($name));
        $stmt = $pdo->prepare("INSERT INTO products (name, sku, slug, cost_price, selling_price, category_id, brand_id, supplier_id, status, created_at) VALUES (:name,:sku,:slug,:cost,:price,:cat,:brand,:sup,:status,NOW())");
        $stmt->execute([':name'=>$name,':sku'=>$sku,':slug'=>$slug,':cost'=>$cost,':price'=>$price,':cat'=>$category_id,':brand'=>$brand_id,':sup'=>$supplier_id,':status'=>$status]);
        $newId = (int)$pdo->lastInsertId();
        $action = 'created';
    }

    echo json_encode(['success'=>true,'action'=>$action,'id'=>$newId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
