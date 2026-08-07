<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/service_gallery_admin_service.php';

vk_api_require_admin();

$pdo = db();
vk_auth_ensure_schema($pdo);

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
$perms = vk_sg_admin_permissions($role);
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
        $result = vk_sg_admin_list($pdo, [
            'service_id' => (int) ($_GET['service_id'] ?? 0),
            'page' => (int) ($_GET['page'] ?? 1),
            'per_page' => $_GET['per_page'] ?? '12',
            'sort' => (string) ($_GET['sort'] ?? 'newest'),
            'q' => (string) ($_GET['q'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to' => (string) ($_GET['date_to'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'featured' => (string) ($_GET['featured'] ?? ''),
        ]);
        echo json_encode(['ok' => true] + $result + ['permissions' => $perms], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'albums') {
        echo json_encode(['ok' => true, 'albums' => vk_sg_admin_albums($pdo)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'export') {
        if (!$perms['can_export']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
            exit;
        }
        $format = strtolower((string) ($_GET['format'] ?? 'json'));
        $list = vk_sg_admin_list($pdo, [
            'service_id' => (int) ($_GET['service_id'] ?? 0),
            'page' => 1,
            'per_page' => 'all',
            'sort' => (string) ($_GET['sort'] ?? 'newest'),
            'q' => (string) ($_GET['q'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to' => (string) ($_GET['date_to'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'featured' => (string) ($_GET['featured'] ?? ''),
        ]);
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="gallery-export-' . date('Y-m-d') . '.csv"');
            echo vk_sg_admin_export_csv($list['items']);
            exit;
        }
        echo json_encode(['ok' => true, 'exported_at' => date(DATE_ATOM), 'items' => $list['items']], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'zip') {
        if (!$perms['can_bulk']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permission denied.'], JSON_THROW_ON_ERROR);
            exit;
        }
        $ids = array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))));
        $zip = vk_sg_admin_build_zip($pdo, $ids);
        if (!$zip['ok']) {
            http_response_code(422);
            echo json_encode($zip, JSON_THROW_ON_ERROR);
            exit;
        }
        vk_sg_admin_audit($pdo, $actorId, 'gallery_download_zip', 0, ['ids' => $ids]);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="gallery-' . date('Y-m-d-His') . '.zip"');
        readfile((string) $zip['path']);
        @unlink((string) $zip['path']);
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

$contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
$isMultipart = str_contains($contentType, 'multipart/form-data');

if ($isMultipart) {
    require_csrf((string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    $action = (string) ($_POST['action'] ?? 'upload');
    if ($action === 'upload') {
        if (!$perms['can_upload']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Upload not permitted.'], JSON_THROW_ON_ERROR);
            exit;
        }
        $serviceId = (int) ($_POST['service_id'] ?? 0);
        $file = $_FILES['file'] ?? null;
        if (!is_array($file)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Missing file.'], JSON_THROW_ON_ERROR);
            exit;
        }
        $result = vk_sg_admin_upload($pdo, $file, $serviceId, $actorId);
        http_response_code($result['ok'] ? 200 : 422);
        echo json_encode($result, JSON_THROW_ON_ERROR);
        exit;
    }
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    $data = $_POST;
}
require_csrf((string) ($data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

$action = (string) ($data['action'] ?? '');

if ($action === 'update') {
    if (!$perms['can_edit']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Edit not permitted.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $result = vk_sg_admin_update($pdo, (int) ($data['id'] ?? 0), $data, $actorId);
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

if ($action === 'delete') {
    if (!$perms['can_delete']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Delete not permitted.'], JSON_THROW_ON_ERROR);
        exit;
    }
    $id = (int) ($data['id'] ?? 0);
    $res = vk_service_gallery_delete_by_id($pdo, $id);
    if ($res['ok']) {
        vk_sg_admin_audit($pdo, $actorId, 'gallery_delete', $id, []);
    }
    http_response_code($res['ok'] ? 200 : 404);
    echo json_encode($res, JSON_THROW_ON_ERROR);
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
    $result = vk_sg_admin_bulk($pdo, $bulkAction, $ids, $actorId, (int) ($data['target_service_id'] ?? 0));
    http_response_code($result['ok'] ? 200 : 422);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_THROW_ON_ERROR);
