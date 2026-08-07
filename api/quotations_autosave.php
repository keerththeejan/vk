<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/quotations_service.php';

vk_api_require_admin();

$pdo = db();
vk_ensure_quotations_schema($pdo);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}

require_csrf((string) ($data['_csrf'] ?? $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$perms = vk_quotation_permissions();
if (empty($perms['create']) && empty($perms['edit'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied'], JSON_THROW_ON_ERROR);
    exit;
}

$id = (int) ($data['id'] ?? 0);
$header = vk_quotation_header_from_post($data);
$lines = vk_quotation_parse_lines_from_post($data);

if ($header['customer_id'] <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Customer required'], JSON_THROW_ON_ERROR);
    exit;
}
if ($lines === []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'At least one line item required'], JSON_THROW_ON_ERROR);
    exit;
}

$header['status'] = 'draft';
$header['approval_status'] = 'none';

try {
    $savedId = vk_quotation_save($pdo, $header, $lines, $id > 0 ? $id : null);
    $q = vk_quotation_get($pdo, $savedId);
    echo json_encode([
        'ok' => true,
        'id' => $savedId,
        'number' => (string) ($q['quotation_number'] ?? ''),
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
}
