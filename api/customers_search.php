<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

$q = trim((string) ($_GET['q'] ?? ''));
$pdo = db();
if ($q === '') {
    $st = $pdo->query(
        'SELECT c.id, c.name, c.phone, c.email, a.id AS account_id
         FROM customers c
         JOIN accounts a ON a.customer_id = c.id
         ORDER BY c.name ASC
         LIMIT 50'
    );
} else {
    $like = '%' . $q . '%';
    $st = $pdo->prepare(
        'SELECT c.id, c.name, c.phone, c.email, a.id AS account_id
         FROM customers c
         JOIN accounts a ON a.customer_id = c.id
         WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?
         ORDER BY c.name ASC
         LIMIT 50'
    );
    $st->execute([$like, $like, $like]);
}
echo json_encode(['results' => $st->fetchAll()], JSON_THROW_ON_ERROR);
