<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) { http_response_code(400); echo json_encode(['error'=>'Missing id']); exit; }

try {
  $pdo = db();
  $stmt = $pdo->prepare('DELETE FROM products WHERE id=:id');
  $stmt->execute([':id'=>$id]);
  echo json_encode(['success'=>true]);
} catch (Exception $e) {
  http_response_code(500); echo json_encode(['error'=>$e->getMessage()]);
}
