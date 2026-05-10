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

$imageKeys = ['site_logo', 'site_logo_dark', 'site_logo_light', 'mobile_logo', 'site_favicon', 'seo_og_image', 'seo_twitter_image'];
$allowedMime = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
$maxSize = 3 * 1024 * 1024;
$errors = [];
$uploaded = [];
$meta = vk_settings_defaults();

$finfo = new finfo(FILEINFO_MIME_TYPE);

foreach ($imageKeys as $key) {
    if (!empty($_POST['remove_' . $key])) {
        $old = vk_settings_get($pdo, $key);
        if ($old && is_file(ROOT_PATH . '/' . $old) && str_starts_with((string) $old, 'uploads/settings/')) {
            @unlink(ROOT_PATH . '/' . $old);
        }
        vk_settings_set($pdo, $key, '', $meta[$key][1] ?? 'branding', 'image');
        vk_settings_audit($pdo, 'remove_file', $key);
        $uploaded[] = $key . '_removed';
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
    $dest = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmp, $dest)) {
        $errors[] = 'Failed to save ' . $key . '.';
        continue;
    }

    $old = vk_settings_get($pdo, $key);
    if ($old && is_file(ROOT_PATH . '/' . $old) && str_starts_with((string) $old, 'uploads/settings/')) {
        @unlink(ROOT_PATH . '/' . $old);
    }
    $relative = 'uploads/settings/' . $filename;
    vk_settings_set($pdo, $key, $relative, $meta[$key][1] ?? 'branding', 'image');
    vk_settings_audit($pdo, 'upload_file', $key, $relative);
    $uploaded[] = $key;
}

if ($errors) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'errors' => $errors], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode(['ok' => true, 'uploaded' => $uploaded], JSON_THROW_ON_ERROR);
