<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id']); exit; }

try {
  $pdo = db();
  $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name, b.name AS brand_name FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id WHERE p.id=:id');
  $stmt->execute([':id'=>$id]);
  $product = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$product) { http_response_code(404); echo json_encode(['error'=>'Not found']); exit; }
  echo json_encode(['data'=>$product]);
} catch (Exception $e) {
  http_response_code(500); echo json_encode(['error'=>$e->getMessage()]);
}
