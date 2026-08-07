<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';

vk_api_require_admin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing customer id'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
$st = $pdo->prepare(
    'SELECT c.id, c.name, c.phone, c.email, c.address,
            a.code AS account_code, a.current_balance
     FROM customers c
     LEFT JOIN accounts a ON a.customer_id = c.id
     WHERE c.id = ? LIMIT 1'
);
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Customer not found'], JSON_THROW_ON_ERROR);
    exit;
}

$code = (string) ($row['account_code'] ?? ('CUS-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT)));

echo json_encode([
    'ok' => true,
    'customer' => [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'phone' => (string) ($row['phone'] ?? ''),
        'mobile' => (string) ($row['phone'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'billing_address' => (string) ($row['address'] ?? ''),
        'shipping_address' => (string) ($row['address'] ?? ''),
        'company_name' => (string) $row['name'],
        'contact_person' => (string) $row['name'],
        'code' => $code,
        'customer_code' => $code,
        'balance' => (float) ($row['current_balance'] ?? 0),
        'credit_limit' => 0,
        'tax_number' => '',
    ],
], JSON_THROW_ON_ERROR);
