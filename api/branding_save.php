<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_admin();

$pdo = db();
if (!vk_settings_table_ready($pdo)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Settings table missing'], JSON_THROW_ON_ERROR);
    exit;
}

$uploadDir = ROOT_PATH . '/uploads/settings';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

$allowedLogo = ['image/png', 'image/jpeg', 'image/svg+xml'];
$allowedFavicon = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png'];
$maxLogoSize = 2 * 1024 * 1024; // 2MB
$maxFaviconSize = 1 * 1024 * 1024; // 1MB

$errors = [];
$uploaded = [];

// Handle site_title
$siteTitle = trim((string) ($_POST['site_title'] ?? ''));
if ($siteTitle !== '') {
    vk_settings_set($pdo, 'site_title', $siteTitle);
}

// Handle remove_logo
if (!empty($_POST['remove_logo'])) {
    $oldLogo = vk_settings_get($pdo, 'site_logo');
    if ($oldLogo && file_exists(ROOT_PATH . '/' . $oldLogo)) {
        @unlink(ROOT_PATH . '/' . $oldLogo);
    }
    vk_settings_set($pdo, 'site_logo', '');
    $uploaded[] = 'logo_removed';
}

// Handle remove_favicon
if (!empty($_POST['remove_favicon'])) {
    $oldFavicon = vk_settings_get($pdo, 'site_favicon');
    if ($oldFavicon && file_exists(ROOT_PATH . '/' . $oldFavicon)) {
        @unlink(ROOT_PATH . '/' . $oldFavicon);
    }
    vk_settings_set($pdo, 'site_favicon', '');
    $uploaded[] = 'favicon_removed';
}

// Handle logo upload
if (!empty($_FILES['site_logo']) && $_FILES['site_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['site_logo'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Logo upload failed (error ' . $file['error'] . ')';
    } elseif (!in_array($file['type'], $allowedLogo, true)) {
        $errors[] = 'Logo must be PNG, JPG, or SVG';
    } elseif ($file['size'] > $maxLogoSize) {
        $errors[] = 'Logo must be under 2MB';
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Delete old logo
            $oldLogo = vk_settings_get($pdo, 'site_logo');
            if ($oldLogo && file_exists(ROOT_PATH . '/' . $oldLogo)) {
                @unlink(ROOT_PATH . '/' . $oldLogo);
            }
            vk_settings_set($pdo, 'site_logo', 'uploads/settings/' . $filename);
            $uploaded[] = 'logo';
        } else {
            $errors[] = 'Failed to save logo';
        }
    }
}

// Handle favicon upload
if (!empty($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['site_favicon'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Favicon upload failed (error ' . $file['error'] . ')';
    } elseif (!in_array($file['type'], $allowedFavicon, true)) {
        $errors[] = 'Favicon must be ICO or PNG';
    } elseif ($file['size'] > $maxFaviconSize) {
        $errors[] = 'Favicon must be under 1MB';
    } else {
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'favicon_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Delete old favicon
            $oldFavicon = vk_settings_get($pdo, 'site_favicon');
            if ($oldFavicon && file_exists(ROOT_PATH . '/' . $oldFavicon)) {
                @unlink(ROOT_PATH . '/' . $oldFavicon);
            }
            vk_settings_set($pdo, 'site_favicon', 'uploads/settings/' . $filename);
            $uploaded[] = 'favicon';
        } else {
            $errors[] = 'Failed to save favicon';
        }
    }
}

if (count($errors) > 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'errors' => $errors], JSON_THROW_ON_ERROR);
    exit;
}

echo json_encode(['ok' => true, 'uploaded' => $uploaded], JSON_THROW_ON_ERROR);
