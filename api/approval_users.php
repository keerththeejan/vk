<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap_core.php';
require_once dirname(__DIR__) . '/includes/approval_users_service.php';

vk_api_require_admin();

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
if (!vk_auth_role_can_manage($role)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied. Only administrators can manage approvals.'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
vk_auth_ensure_schema($pdo);
$actorId = (int) ($_SESSION['user_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $action = (string) ($_GET['action'] ?? 'list');

    if ($action === 'stats') {
        echo json_encode(['ok' => true, 'stats' => vk_approval_stats($pdo)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'user') {
        $userId = (int) ($_GET['id'] ?? 0);
        $user = $userId > 0 ? vk_approval_get_user($pdo, $userId) : null;
        if (!$user) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'User not found.'], JSON_THROW_ON_ERROR);
            exit;
        }
        echo json_encode(['ok' => true, 'user' => $user], JSON_THROW_ON_ERROR);
        exit;
    }

    $filters = vk_approval_parse_filters($_GET);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(50, (int) ($_GET['per_page'] ?? 12)));
    $total = vk_approval_count($pdo, $filters);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $rows = vk_approval_fetch($pdo, $filters, $page, $perPage);

    echo json_encode([
        'ok' => true,
        'filters' => $filters,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'total' => $total,
        'users' => array_map(static fn(array $u): array => vk_approval_user_row_json($u, $actorId), $rows),
        'stats' => vk_approval_stats($pdo),
    ], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

require_csrf((string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$action = (string) ($_POST['action'] ?? '');
$userIds = [];
if (!empty($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    $userIds = $_POST['user_ids'];
} elseif (!empty($_POST['user_ids']) && is_string($_POST['user_ids'])) {
    $decoded = json_decode($_POST['user_ids'], true);
    if (is_array($decoded)) {
        $userIds = $decoded;
    }
}

$payload = [
    'user_id' => (int) ($_POST['user_id'] ?? 0),
    'user_ids' => $userIds,
    'role' => (string) ($_POST['role'] ?? ''),
    'note' => (string) ($_POST['note'] ?? $_POST['rejection_reason'] ?? ''),
    'rejection_reason' => (string) ($_POST['rejection_reason'] ?? ''),
];

$result = vk_approval_process_action($pdo, $action, $actorId, $payload);
if (!$result['ok']) {
    http_response_code(422);
}
echo json_encode($result, JSON_THROW_ON_ERROR);
