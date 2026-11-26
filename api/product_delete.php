<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
if (!csrf_verify(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null)) { http_response_code(419); echo json_encode(['error' => 'Your session expired. Refresh and try again.']); exit; }

$ids = [];
if (isset($_POST['ids'])) $ids = array_map('intval', explode(',', (string) $_POST['ids']));
elseif (isset($_POST['id'])) $ids = [(int) $_POST['id']];
$ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
if (!$ids) { http_response_code(400); echo json_encode(['error' => 'Select at least one product.']); exit; }

try {
    $pdo = db();
    $pdo->beginTransaction();
    $marks = implode(',', array_fill(0, count($ids), '?'));
    if (db_table_exists($pdo, 'invoice_items')) {
        $used = $pdo->prepare("SELECT DISTINCT product_id FROM invoice_items WHERE product_id IN ($marks)");
        $used->execute($ids);
        $blocked = array_map('intval', $used->fetchAll(PDO::FETCH_COLUMN));
        if ($blocked) throw new RuntimeException('Products used on invoices cannot be deleted: ' . implode(', ', $blocked));
    }
    $delete = $pdo->prepare("DELETE FROM products WHERE id IN ($marks)");
    $delete->execute($ids);
    $deleted = $delete->rowCount();
    $pdo->commit();
    echo json_encode(['success' => true, 'deleted' => $deleted]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409);
    echo json_encode(['error' => $e instanceof RuntimeException ? $e->getMessage() : 'Unable to delete the selected products.']);
}
