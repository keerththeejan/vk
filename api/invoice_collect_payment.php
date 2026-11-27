<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/layout_init.php';
require_once dirname(__DIR__) . '/includes/invoices_service.php';
require_once dirname(__DIR__) . '/includes/invoice_payment_service.php';
vk_ensure_finance_schemas($pdo);

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}
if (!csrf_verify(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null)) {
    http_response_code(419);
    echo json_encode(['error' => 'Your session expired. Refresh the page and try again.']);
    exit;
}
if (empty(vk_invoice_permissions()['collect_payment'])) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to collect invoice payments.']);
    exit;
}

try {
    foreach (['reference_number', 'received_by', 'request_token'] as $requiredColumn) {
        if (!db_column_exists($pdo, 'payments', $requiredColumn)) {
            throw new RuntimeException('The payment table needs the draft payment upgrade. Apply sql/draft_invoice_payment_migration.sql and retry.');
        }
    }
    $result = vk_invoice_collect_payment($pdo, $_POST, (int) ($currentUser['id'] ?? 0));
    echo json_encode(['success' => true] + $result, JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('invoice_collect_payment: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => APP_DEBUG ? $e->getMessage() : 'Could not collect the payment.']);
}
