<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

$pdo = db();
require_once dirname(__DIR__) . '/includes/users_schema.php';
vk_ensure_users_management_schema($pdo);

if (($_SESSION['user_role'] ?? 'admin') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden'], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON'], JSON_THROW_ON_ERROR);
    exit;
}

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid user.'], JSON_THROW_ON_ERROR);
    exit;
}

if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'You cannot deactivate your own account here.'], JSON_THROW_ON_ERROR);
    exit;
}

$old = $pdo->prepare('SELECT role, status FROM users WHERE id = ? LIMIT 1');
$old->execute([$id]);
$oldRow = $old->fetch(PDO::FETCH_ASSOC);
if (!$oldRow) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'User not found.'], JSON_THROW_ON_ERROR);
    exit;
}

$wasActiveAdmin = ((string) ($oldRow['role'] ?? '') === 'admin') && ((string) ($oldRow['status'] ?? 'active') === 'active');
if ($wasActiveAdmin) {
    $cnt = $pdo->prepare(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id != ?"
    );
    $cnt->execute([$id]);
    if ((int) $cnt->fetchColumn() === 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Cannot deactivate the last active administrator.'], JSON_THROW_ON_ERROR);
        exit;
    }
}

$pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$id]);

echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
