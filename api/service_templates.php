<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/service_templates_service.php';

vk_api_require_admin();

$pdo = db();
vk_auth_ensure_schema($pdo);
vk_st_templates_auto_migrate($pdo);

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
$perms = vk_st_templates_permissions($role);
if (!$perms['can_access']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
$actorId = (int) ($_SESSION['user_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $action = (string) ($_GET['action'] ?? 'list');

    if ($action === 'list') {
        $result = vk_st_templates_list($pdo, [
            'page' => (int) ($_GET['page'] ?? 1),
            'per_page' => $_GET['per_page'] ?? '25',
            'q' => (string) ($_GET['q'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'service_type' => (string) ($_GET['service_type'] ?? ''),
            'is_default' => (string) ($_GET['is_default'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to' => (string) ($_GET['date_to'] ?? ''),
            'sort' => (string) ($_GET['sort'] ?? 'created_at'),
            'sort_dir' => (string) ($_GET['sort_dir'] ?? 'desc'),
        ]);
        $stats = vk_st_templates_dashboard_stats($pdo);
        echo json_encode(['ok' => true] + $result + ['stats' => $stats] + ['permissions' => $perms], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'get') {
        $result = vk_st_templates_get($pdo, (int) ($_GET['id'] ?? 0));
        http_response_code($result['ok'] ? 200 : 404);
        echo json_encode($result, JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'categories') {
        echo json_encode(['ok' => true, 'categories' => vk_st_templates_category_stats($pdo)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'export') {
        if (!$perms['can_export']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
            exit;
        }
        $format = strtolower((string) ($_GET['format'] ?? 'json'));
        $list = vk_st_templates_list($pdo, [
            'page' => 1,
            'per_page' => 'all',
            'q' => (string) ($_GET['q'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'service_type' => (string) ($_GET['service_type'] ?? ''),
            'is_default' => (string) ($_GET['is_default'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to' => (string) ($_GET['date_to'] ?? ''),
            'sort' => (string) ($_GET['sort'] ?? 'created_at'),
            'sort_dir' => (string) ($_GET['sort_dir'] ?? 'desc'),
        ]);
        vk_st_templates_audit($pdo, $actorId, 'template_export', 0, ['format' => $format]);
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="service-templates-' . date('Y-m-d') . '.csv"');
            echo vk_st_templates_export_csv($list['items']);
            exit;
        }
        echo json_encode(['ok' => true, 'exported_at' => date(DATE_ATOM), 'items' => $list['items']], JSON_THROW_ON_ERROR);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_THROW_ON_ERROR);
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

$action = (string) ($data['action'] ?? '');

if ($action === 'delete') {
    if (!$perms['can_delete']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Delete not permitted.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $result = vk_st_templates_soft_delete($pdo, (int) ($data['id'] ?? 0), $actorId);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'duplicate') {
    if (!$perms['can_create']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Duplicate not permitted.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $result = vk_st_templates_duplicate($pdo, (int) ($data['id'] ?? 0), $actorId);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'bulk') {
    if (!$perms['can_bulk']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Bulk actions not permitted.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $bulkAction = (string) ($data['bulk_action'] ?? '');
    if ($bulkAction === 'delete' && !$perms['can_delete']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Delete not permitted.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];
    $result = vk_st_templates_bulk($pdo, $bulkAction, $ids, $actorId);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'rollback') {
    if (!$perms['can_edit']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Rollback not permitted.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $result = vk_st_templates_rollback($pdo, (int) ($data['template_id'] ?? 0), (int) ($data['version_id'] ?? 0), $actorId);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_THROW_ON_ERROR);
