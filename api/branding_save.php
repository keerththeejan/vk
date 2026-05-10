<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_settings_admin();
require_csrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ''));

$pdo = db();
vk_settings_seed_defaults($pdo);

$uploadDir = ROOT_PATH . '/uploads/settings';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    http_response_code(500);
    error_log('branding_save: upload directory missing or not writable: ' . $uploadDir);
    echo json_encode(['ok' => false, 'error' => 'Settings upload directory is not writable.'], JSON_THROW_ON_ERROR);
    exit;
}

$imageKeys = ['company_logo', 'site_logo_dark', 'site_logo_light', 'mobile_logo', 'site_favicon', 'seo_og_image', 'seo_twitter_image'];
$allowedMime = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
$allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'svg', 'ico'];
$maxSize = 3 * 1024 * 1024;
$errors = [];
$uploaded = [];
$assets = [];
$meta = vk_settings_defaults();

$finfo = new finfo(FILEINFO_MIME_TYPE);

foreach ($imageKeys as $key) {
    if (!empty($_POST['remove_' . $key])) {
        $oldPaths = [$old = vk_settings_get($pdo, $key)];
        if ($key === 'company_logo') {
            $oldPaths[] = vk_settings_get($pdo, 'site_logo');
        }
        foreach (array_unique(array_filter($oldPaths)) as $oldPath) {
            $oldRel = vk_setting_relative_path((string) $oldPath);
            if ($oldRel && is_file(ROOT_PATH . '/' . $oldRel) && str_starts_with($oldRel, 'uploads/settings/')) {
                @unlink(ROOT_PATH . '/' . $oldRel);
            }
        }
        vk_settings_set($pdo, $key, '', $meta[$key][1] ?? 'branding', 'image');
        if ($key === 'company_logo') {
            vk_settings_set($pdo, 'site_logo', '', 'branding', 'image');
        }
        vk_settings_audit($pdo, 'remove_file', $key);
        $uploaded[] = $key . '_removed';
        $assets[$key] = ['path' => '', 'url' => getLogo($key === 'site_favicon' ? 'favicon' : 'main'), 'exists' => false];
    }

    if (empty($_FILES[$key]) || ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
    }

    $file = $_FILES[$key];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = $key . ' upload failed.';
        continue;
    }
    if ((int) $file['size'] > $maxSize) {
        $errors[] = $key . ' must be under 3MB.';
        continue;
    }
    $sourceExt = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if (!in_array($sourceExt, $allowedExt, true)) {
        $errors[] = $key . ' has an unsupported file extension.';
        continue;
    }

    $tmp = (string) $file['tmp_name'];
    $mime = $finfo->file($tmp) ?: '';
    if ($mime === 'text/plain' && strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) === 'svg') {
        $mime = 'image/svg+xml';
    }
    if (!isset($allowedMime[$mime])) {
        $errors[] = $key . ' must be PNG, JPG, WEBP, SVG, or ICO.';
        continue;
    }
    if ($mime === 'image/svg+xml') {
        $svg = file_get_contents($tmp) ?: '';
        if (preg_match('/<script|on\w+\s*=|javascript:/i', $svg)) {
            $errors[] = $key . ' SVG contains unsafe script content.';
            continue;
        }
    } elseif (!@getimagesize($tmp)) {
        $errors[] = $key . ' is not a valid image.';
        continue;
    }

    $filename = $key . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $allowedMime[$mime];
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: ('logo_' . bin2hex(random_bytes(8)) . '.' . $allowedMime[$mime]);
    $dest = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        error_log('branding_save: move_uploaded_file failed for ' . $key . ' to ' . $dest);
        $errors[] = 'Failed to save ' . $key . '.';
        continue;
    }

    $relative = 'uploads/settings/' . $filename;
    $oldPaths = [vk_settings_get($pdo, $key)];
    if ($key === 'company_logo') {
        $oldPaths[] = vk_settings_get($pdo, 'site_logo');
    }
    foreach (array_unique(array_filter($oldPaths)) as $oldPath) {
        $oldRel = vk_setting_relative_path((string) $oldPath);
        if ($oldRel && $oldRel !== $relative && is_file(ROOT_PATH . '/' . $oldRel) && str_starts_with($oldRel, 'uploads/settings/')) {
            @unlink(ROOT_PATH . '/' . $oldRel);
        }
    }
    vk_settings_set($pdo, $key, $relative, $meta[$key][1] ?? 'branding', 'image');
    if ($key === 'company_logo') {
        vk_settings_set($pdo, 'site_logo', $relative, 'branding', 'image');
    }
    $saved = vk_settings_get($pdo, $key, '');
    if ($saved !== $relative || !is_file($dest)) {
        error_log('branding_save: post-save validation failed for ' . $key . ' value=' . (string) $saved . ' file=' . $dest);
        $errors[] = 'Post-save validation failed for ' . $key . '.';
        continue;
    }
    vk_settings_audit($pdo, 'upload_file', $key, $relative);
    $uploaded[] = $key;
    $assets[$key] = [
        'path' => $relative,
        'url' => vk_setting_asset_url($relative, 'assets/images/default-logo.svg', true),
        'exists' => true,
        'version' => (string) filemtime($dest),
    ];
}

if ($errors) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'errors' => $errors], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode(['ok' => true, 'uploaded' => $uploaded, 'assets' => $assets], JSON_THROW_ON_ERROR);
