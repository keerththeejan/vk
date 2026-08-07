<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';

vk_api_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

require_csrf((string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$name = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));

if ($name === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Customer name is required'], JSON_THROW_ON_ERROR);
    exit;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid email address'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('INSERT INTO customers (name, phone, email, address) VALUES (?,?,?,?)');
    $st->execute([$name, $phone !== '' ? $phone : null, $email !== '' ? $email : null, $address !== '' ? $address : null]);
    $cid = (int) $pdo->lastInsertId();
    $code = next_customer_account_code($pdo);
    $pdo->prepare(
        'INSERT INTO accounts (code, name, account_type, customer_id, current_balance) VALUES (?,?,?,?,0)'
    )->execute([$code, $name . ' — Account', 'customer', $cid]);
    $pdo->commit();
    if (function_exists('vk_cache_flush_dashboard')) {
        vk_cache_flush_dashboard();
    }
    echo json_encode([
        'ok' => true,
        'customer' => [
            'id' => $cid,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'code' => $code,
            'balance' => 0,
            'company_name' => $name,
            'contact_person' => $name,
        ],
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
}
