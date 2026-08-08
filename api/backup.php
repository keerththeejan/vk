<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_settings_admin();
vk_bootstrap_module('backup_service');

header('X-Content-Type-Options: nosniff');

$pdo = db();
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? ''));

if ($action === 'download') {
    $id = vk_backup_safe_id((string) ($_GET['id'] ?? ''));
    if ($id === '') {
        http_response_code(400);
        echo 'Missing backup id';
        exit;
    }
    vk_backup_stream_download($id);
}

header('Content-Type: application/json; charset=utf-8');

$token = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_GET['_csrf'] ?? '');
$readActions = ['dashboard', 'list', 'logs', 'details', 'schedule_get', 'system_info'];
if (!in_array($action, $readActions, true) && !csrf_verify($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    // Lazy scheduled backup when dashboard loads
    if ($action === 'dashboard') {
        try {
            vk_backup_maybe_run_scheduled($pdo);
        } catch (Throwable $e) {
            // do not block dashboard
        }
    }

    switch ($action) {
        case 'dashboard':
        case 'system_info':
            echo json_encode(['ok' => true, 'data' => vk_backup_dashboard($pdo)], JSON_UNESCAPED_UNICODE);
            break;

        case 'list':
            $items = vk_backup_manifest_load();
            foreach ($items as &$it) {
                $it['size_label'] = vk_backup_format_bytes((int) ($it['size'] ?? 0));
                $it['created_label'] = !empty($it['created_at']) ? date('Y-m-d H:i', strtotime((string) $it['created_at'])) : '—';
            }
            unset($it);
            echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
            break;

        case 'logs':
            echo json_encode(['ok' => true, 'logs' => vk_backup_logs(80)], JSON_UNESCAPED_UNICODE);
            break;

        case 'details':
            $id = vk_backup_safe_id((string) ($_GET['id'] ?? $_POST['id'] ?? ''));
            $item = vk_backup_find($id);
            if (!$item) {
                echo json_encode(['ok' => false, 'message' => 'Backup not found']);
                break;
            }
            $item['size_label'] = vk_backup_format_bytes((int) ($item['size'] ?? 0));
            echo json_encode(['ok' => true, 'item' => $item], JSON_UNESCAPED_UNICODE);
            break;

        case 'schedule_get':
            echo json_encode(['ok' => true, 'schedule' => vk_backup_schedule_get($pdo)], JSON_UNESCAPED_UNICODE);
            break;

        case 'schedule_save':
            $components = $_POST['components'] ?? [];
            if (!is_array($components)) {
                $components = [];
            }
            vk_backup_schedule_save($pdo, [
                'enabled' => !empty($_POST['enabled']),
                'frequency' => (string) ($_POST['frequency'] ?? 'daily'),
                'time' => (string) ($_POST['time'] ?? '02:00'),
                'retention' => (int) ($_POST['retention'] ?? 10),
                'components' => $components,
            ]);
            echo json_encode(['ok' => true, 'message' => 'Auto-backup schedule saved.', 'schedule' => vk_backup_schedule_get($pdo)]);
            break;

        case 'create':
            @set_time_limit(0);
            $type = (string) ($_POST['type'] ?? 'database');
            $components = $_POST['components'] ?? [];
            if (!is_array($components)) {
                $components = [];
            }
            $meta = vk_backup_create($pdo, $type, $components, [
                'compress' => !empty($_POST['compress']),
                'encrypt' => !empty($_POST['encrypt']),
                'password' => (string) ($_POST['password'] ?? ''),
                'gzip_sql' => !empty($_POST['gzip']),
                'name' => trim((string) ($_POST['name'] ?? '')),
            ]);
            $meta['size_label'] = vk_backup_format_bytes((int) ($meta['size'] ?? 0));
            echo json_encode(['ok' => true, 'message' => 'Backup created successfully.', 'item' => $meta], JSON_UNESCAPED_UNICODE);
            break;

        case 'delete':
            $id = vk_backup_safe_id((string) ($_POST['id'] ?? ''));
            $ok = vk_backup_delete($id);
            echo json_encode(['ok' => $ok, 'message' => $ok ? 'Backup deleted.' : 'Backup not found.']);
            break;

        case 'rename':
            $id = vk_backup_safe_id((string) ($_POST['id'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $ok = vk_backup_rename($id, $name);
            echo json_encode(['ok' => $ok, 'message' => $ok ? 'Backup renamed.' : 'Rename failed.']);
            break;

        case 'verify':
            $id = vk_backup_safe_id((string) ($_POST['id'] ?? $_GET['id'] ?? ''));
            echo json_encode(array_merge(['ok' => false], vk_backup_verify($id)), JSON_UNESCAPED_UNICODE);
            break;

        case 'restore':
            @set_time_limit(0);
            $id = vk_backup_safe_id((string) ($_POST['id'] ?? ''));
            $mode = (string) ($_POST['mode'] ?? 'everything');
            if (!in_array($mode, ['database', 'files', 'everything'], true)) {
                $mode = 'everything';
            }
            $result = vk_backup_restore($pdo, $id, $mode, [
                'password' => (string) ($_POST['password'] ?? ''),
                'force' => !empty($_POST['force']),
            ]);
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'upload_restore':
            @set_time_limit(0);
            if (empty($_FILES['backup_file']) || !is_uploaded_file((string) ($_FILES['backup_file']['tmp_name'] ?? ''))) {
                echo json_encode(['ok' => false, 'message' => 'No backup file uploaded.']);
                break;
            }
            $upload = $_FILES['backup_file'];
            $name = basename((string) ($upload['name'] ?? 'upload.bak'));
            if (!preg_match('/\.(zip|sql|gz|enc)$/i', $name)) {
                echo json_encode(['ok' => false, 'message' => 'Invalid backup file type.']);
                break;
            }
            $id = 'bk_upload_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2));
            $destName = 'vk_upload_' . date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name);
            $dest = vk_backup_dir() . DIRECTORY_SEPARATOR . $destName;
            if (!move_uploaded_file((string) $upload['tmp_name'], $dest)) {
                echo json_encode(['ok' => false, 'message' => 'Failed to store uploaded backup.']);
                break;
            }
            $meta = [
                'id' => $id,
                'name' => 'Uploaded: ' . $name,
                'type' => 'uploaded',
                'components' => ['database'],
                'created_at' => date('c'),
                'created_by' => (string) ($_SESSION['username'] ?? 'admin'),
                'created_by_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                'status' => 'completed',
                'encrypted' => str_ends_with(strtolower($destName), '.enc'),
                'php_version' => PHP_VERSION,
                'mysql_version' => (string) $pdo->query('SELECT VERSION()')->fetchColumn(),
                'db_name' => (string) (vk_backup_db_credentials()['name'] ?? ''),
                'checksum' => hash_file('sha256', $dest) ?: '',
                'size' => (int) filesize($dest),
                'filename' => $destName,
                'location' => 'storage/backups',
            ];
            $manifest = vk_backup_manifest_load();
            array_unshift($manifest, $meta);
            vk_backup_manifest_save($manifest);
            vk_backup_log('uploaded', 'success', $destName, $id);

            $mode = (string) ($_POST['mode'] ?? 'database');
            if (!in_array($mode, ['database', 'files', 'everything'], true)) {
                $mode = 'database';
            }
            if (!empty($_POST['restore_now'])) {
                $result = vk_backup_restore($pdo, $id, $mode, [
                    'password' => (string) ($_POST['password'] ?? ''),
                    'force' => true,
                ]);
                echo json_encode(array_merge($result, ['item' => $meta]), JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(['ok' => true, 'message' => 'Backup uploaded and validated.', 'item' => $meta], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => APP_DEBUG ? $e->getMessage() : 'Backup operation failed.',
    ], JSON_UNESCAPED_UNICODE);
}
