<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_settings_admin();
require_once dirname(__DIR__) . '/includes/invoice_print_settings.php';

$pdo = db();
$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'get');

try {
    if ($action === 'get') {
        echo json_encode(['ok' => true, 'settings' => vk_invoice_print_settings_get($pdo), 'presets' => vk_invoice_print_stamp_presets()], JSON_THROW_ON_ERROR);
        exit;
    }

    require_csrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ''));

    if ($action === 'preview') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw !== false ? $raw : '{}', true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid preview payload.');
        }
        $_SESSION['invoice_print_preview_draft'] = array_merge(vk_invoice_print_settings_defaults(), $payload);
        echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'save') {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw !== false ? $raw : '{}', true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid settings payload.');
        }
        vk_invoice_print_settings_save($pdo, $payload);
        unset($_SESSION['invoice_print_preview_draft']);
        echo json_encode(['ok' => true, 'settings' => vk_invoice_print_settings_get($pdo)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'reset') {
        vk_invoice_print_settings_reset($pdo);
        unset($_SESSION['invoice_print_preview_draft']);
        echo json_encode(['ok' => true, 'settings' => vk_invoice_print_settings_get($pdo)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'backup') {
        vk_invoice_print_settings_backup($pdo);
        echo json_encode(['ok' => true, 'message' => 'Settings backed up.'], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'restore') {
        if (!vk_invoice_print_settings_restore($pdo)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'No backup found to restore.'], JSON_THROW_ON_ERROR);
            exit;
        }
        unset($_SESSION['invoice_print_preview_draft']);
        echo json_encode(['ok' => true, 'settings' => vk_invoice_print_settings_get($pdo)], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'upload') {
        $field = (string) ($_POST['field'] ?? '');
        $map = [
            'logo' => 'logo_path',
            'signature' => 'signature_path',
            'stamp' => 'stamp_path',
            'watermark' => 'watermark_path',
        ];
        if (!isset($map[$field])) {
            throw new RuntimeException('Invalid upload field.');
        }
        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }
        $file = $_FILES['file'];
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            throw new RuntimeException('File must be under 5MB.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']) ?: '';
        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
        if (!isset($allowed[$mime])) {
            throw new RuntimeException('Only PNG, JPG, WEBP, or SVG allowed.');
        }
        $ext = $allowed[$mime];
        $dir = ROOT_PATH . '/uploads/invoices/print';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not save uploaded file.');
        }
        $rel = 'uploads/invoices/print/' . $name;
        $settings = vk_invoice_print_settings_get($pdo);
        $settings[$map[$field]] = $rel;
        vk_invoice_print_settings_save($pdo, $settings);
        echo json_encode([
            'ok' => true,
            'path' => $rel,
            'url' => vk_invoice_print_asset_url($rel),
            'settings' => vk_invoice_print_settings_get($pdo),
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if ($action === 'delete_asset') {
        $field = (string) ($_POST['field'] ?? '');
        $map = ['logo' => 'logo_path', 'signature' => 'signature_path', 'stamp' => 'stamp_path', 'watermark' => 'watermark_path'];
        if (!isset($map[$field])) {
            throw new RuntimeException('Invalid field.');
        }
        $settings = vk_invoice_print_settings_get($pdo);
        $key = $map[$field];
        $old = (string) ($settings[$key] ?? '');
        if (str_starts_with($old, 'uploads/invoices/print/')) {
            $abs = ROOT_PATH . '/' . $old;
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
        $defaults = vk_invoice_print_settings_defaults();
        $settings[$key] = $defaults[$key];
        vk_invoice_print_settings_save($pdo, $settings);
        echo json_encode(['ok' => true, 'settings' => vk_invoice_print_settings_get($pdo)], JSON_THROW_ON_ERROR);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action.'], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
}
