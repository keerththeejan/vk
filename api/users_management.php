<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap_core.php';
require_once dirname(__DIR__) . '/includes/users_management_service.php';

vk_api_require_admin();

$pdo = db();
vk_auth_ensure_schema($pdo);

$actorId = (int) ($_SESSION['user_id'] ?? 0);
$perms = vk_users_session_permissions($pdo);

if (!$perms['can_access']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $action = (string) ($_GET['action'] ?? 'list');

    if ($action === 'stats') {
        echo json_encode(['ok' => true, 'stats' => vk_users_stats($pdo, $perms, $actorId)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'generate_password') {
        if (!$perms['can_manage']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
            exit;
        }
        echo json_encode(['ok' => true, 'password' => vk_users_generate_password()], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'user') {
        $userId = (int) ($_GET['id'] ?? 0);
        $user = $userId > 0 ? vk_users_get($pdo, $userId) : null;
        if (!$user) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'User not found.'], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($perms['can_view_self_only'] && $userId !== $actorId) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
            exit;
        }
        if (!$perms['can_view_all'] && !$perms['can_view_self_only'] && ($user['department'] ?? '') !== ($perms['department_filter'] ?? '')) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
            exit;
        }
        $user['initials'] = vk_users_initials($user);
        echo json_encode(['ok' => true, 'user' => $user], JSON_THROW_ON_ERROR);
        exit;
    }

    $filters = vk_users_parse_filters($_GET);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPageRaw = (string) ($_GET['per_page'] ?? '25');
    $perPage = $perPageRaw === 'all' ? 0 : max(1, min(500, (int) $perPageRaw));
    $total = vk_users_count($pdo, $filters, $perms, $actorId);
    $pages = $perPage > 0 ? max(1, (int) ceil($total / $perPage)) : 1;
    $page = min($page, $pages);
    $rows = vk_users_fetch($pdo, $filters, $perms, $actorId, $page, $perPage ?: max(1, $total));

    echo json_encode([
        'ok' => true,
        'filters' => $filters,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage ?: $total,
        'total' => $total,
        'can_manage' => $perms['can_manage'],
        'users' => array_map(static fn(array $u): array => vk_users_row_json($u, $actorId, $perms), $rows),
        'stats' => vk_users_stats($pdo, $perms, $actorId),
    ], JSON_THROW_ON_ERROR);
    exit;
}

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

require_csrf((string) ($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$action = (string) ($data['action'] ?? 'save');

if ($action === 'save') {
    $result = vk_users_save($pdo, $data, $actorId, $perms);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'delete') {
    $result = vk_users_soft_delete($pdo, (int) ($data['id'] ?? 0), $actorId, $perms);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if (in_array($action, ['approve', 'reject'], true)) {
    if (!$perms['can_manage']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
        exit;
    }
    require_once dirname(__DIR__) . '/includes/approval_users_service.php';
    $userId = (int) ($data['id'] ?? 0);
    $map = ['approve' => 'approve', 'reject' => 'reject'];
    $result = vk_approval_process_action($pdo, $map[$action], $actorId, [
        'user_id' => $userId,
        'note' => (string) ($data['note'] ?? $data['rejection_reason'] ?? ''),
    ]);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if (str_starts_with($action, 'bulk_')) {
    $userIds = $data['user_ids'] ?? [];
    if (is_string($userIds)) {
        $userIds = json_decode($userIds, true) ?: [];
    }
    $result = vk_users_bulk_action($pdo, $action, is_array($userIds) ? $userIds : [], $actorId, $perms, (string) ($data['note'] ?? ''));
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_THROW_ON_ERROR);
