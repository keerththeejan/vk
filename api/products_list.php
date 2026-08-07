<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = db();
    $q = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? '';
    $limit = max(1, (int)($_GET['limit'] ?? 50));
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = "(p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q)";
        $params[':q'] = "%$q%";
    }
    if ($status !== '') {
        $where[] = "p.status = :status";
        $params[':status'] = $status;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql = "SELECT p.*, c.name AS category_name, b.name AS brand_name
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN brands b ON b.id = p.brand_id
            $whereSql
            ORDER BY p.updated_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['data' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

$pdo = db();
$rows = $pdo->query('SELECT id, name, price, stock, category FROM products WHERE stock > 0 ORDER BY name')->fetchAll();
echo json_encode(['results' => $rows], JSON_THROW_ON_ERROR);
