<?php
declare(strict_types=1);

const VK_GALLERY_MAX_BYTES = 3 * 1024 * 1024; // 3MB each
const VK_GALLERY_W = 1200;
const VK_GALLERY_H = 675; // 16:9
const VK_GALLERY_WEBP_QUALITY = 84;
const VK_GALLERY_JPEG_QUALITY = 86;

function vk_service_gallery_table_exists(PDO $pdo): bool
{
    return db_table_exists($pdo, 'service_gallery');
}

function vk_service_gallery_auto_migrate(PDO $pdo): void
{
    if (!vk_service_gallery_table_exists($pdo)) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS service_gallery (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            service_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(512) NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_service_gallery_service (service_id, id),
            INDEX idx_service_gallery_created (created_at),
            CONSTRAINT fk_service_gallery_service FOREIGN KEY (service_id) REFERENCES web_services(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    vk_service_gallery_upgrade_schema($pdo);
}

/**
 * Add columns on older databases (idempotent).
 */
function vk_service_gallery_upgrade_schema(PDO $pdo): void
{
    if (!vk_service_gallery_table_exists($pdo)) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($db) || $db === '') {
            return;
        }
        $chk = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $chk->execute([$db, 'service_gallery', 'original_filename']);
        if ((int) $chk->fetchColumn() === 0) {
            $pdo->exec(
                'ALTER TABLE service_gallery ADD COLUMN original_filename VARCHAR(255) NULL DEFAULT NULL AFTER title'
            );
        }
    } catch (Throwable $e) {
        // ignore
    }
    try {
        $pdo->exec('CREATE INDEX idx_service_gallery_created ON service_gallery (created_at)');
    } catch (Throwable $e) {
        // duplicate name: ignore
    }
}

/**
 * Sanitize stored original filename for display/search (no path components).
 */
function vk_service_gallery_sanitize_original_name(string $name): string
{
    $base = basename(str_replace(["\0", '\\'], '', $name));
    $base = preg_replace('/[^a-zA-Z0-9._\- ]+/u', '_', $base) ?? '';
    return trim(mb_substr($base, 0, 255));
}

/**
 * Normalize image_path from DB for disk checks and public URLs (handles /VK/, full URLs, Windows paths).
 */
function vk_service_gallery_normalize_db_path(string $raw): string
{
    $p = vk_normalize_upload_relative_path($raw);
    if ($p === '') {
        return '';
    }
    $marker = 'uploads/services/gallery/';
    $pos = stripos($p, $marker);
    if ($pos !== false) {
        $p = substr($p, $pos);
    }
    while (str_contains($p, 'uploads/services/gallery/uploads/services/gallery/')) {
        $p = str_replace('uploads/services/gallery/uploads/services/gallery/', 'uploads/services/gallery/', $p);
    }
    if (!str_contains($p, '/') && preg_match('/\.(jpe?g|png|webp)$/i', $p)) {
        $p = $marker . $p;
    }

    return $p;
}

/**
 * Return a normalized gallery path that exists on disk, or null.
 */
function vk_service_gallery_resolve_existing_path(string $raw): ?string
{
    $p = vk_service_gallery_normalize_db_path($raw);
    if ($p === '') {
        return null;
    }
    if (public_asset_file_exists($p)) {
        return $p;
    }
    $bn = basename($p);
    if ($bn !== '' && $bn !== '.' && $bn !== '..') {
        $try = 'uploads/services/gallery/' . $bn;
        if ($try !== $p && public_asset_file_exists($try)) {
            return $try;
        }
    }

    return null;
}

/**
 * Delete one gallery row and its file under uploads/services/gallery/.
 *
 * @return array{ok:bool,error:?string}
 */
function vk_service_gallery_delete_by_id(PDO $pdo, int $imgId): array
{
    if ($imgId <= 0) {
        return ['ok' => false, 'error' => 'Invalid image.'];
    }
    $st = $pdo->prepare('SELECT id, image_path FROM service_gallery WHERE id = ? LIMIT 1');
    $st->execute([$imgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Image not found.'];
    }
    $path = trim((string) ($row['image_path'] ?? ''));
    if ($path !== '' && str_starts_with(str_replace('\\', '/', $path), 'uploads/services/gallery/')) {
        $full = ROOT_PATH . '/' . ltrim(str_replace('\\', '/', $path), '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }
    $pdo->prepare('DELETE FROM service_gallery WHERE id = ?')->execute([$imgId]);

    return ['ok' => true, 'error' => null];
}

/**
 * @return list<array{id:int,image_path:string,title:string}>
 */
function vk_service_gallery_fetch(PDO $pdo, int $serviceId, array $serviceRow): array
{
    $rows = [];
    if ($serviceId <= 0) {
        return vk_service_gallery_default_rows($serviceRow);
    }

    try {
        if (vk_service_gallery_table_exists($pdo)) {
            $st = $pdo->prepare('SELECT id, image_path, COALESCE(title, \'\') AS title FROM service_gallery WHERE service_id = ? ORDER BY id DESC');
            $st->execute([$serviceId]);
            $rows = $st->fetchAll();
        }
    } catch (Throwable $e) {
        $rows = [];
    }

    $valid = [];
    foreach ($rows as $r) {
        $resolved = vk_service_gallery_resolve_existing_path((string) ($r['image_path'] ?? ''));
        if ($resolved === null) {
            continue;
        }
        $valid[] = [
            'id' => (int) ($r['id'] ?? 0),
            'image_path' => $resolved,
            'title' => trim((string) ($r['title'] ?? '')),
        ];
    }
    if ($valid) {
        return $valid;
    }

    return vk_service_gallery_default_rows($serviceRow);
}

/**
 * @param array<string,mixed> $serviceRow
 * @return list<array{id:int,image_path:string,title:string}>
 */
function vk_service_gallery_default_rows(array $serviceRow): array
{
    $name = strtolower(trim((string) ($serviceRow['name'] ?? '')));
    $slug = strtolower(trim((string) ($serviceRow['slug'] ?? '')));
    $blob = trim($name . ' ' . $slug);

    $set = [
        ['path' => 'assets/images/gallery/laptop-repair.svg', 'title' => 'Laptop repair'],
        ['path' => 'assets/images/gallery/motherboard-repair.svg', 'title' => 'Motherboard repair'],
        ['path' => 'assets/images/gallery/pc-cleaning.svg', 'title' => 'PC cleaning'],
    ];
    if (str_contains($blob, 'printer')) {
        $set = [
            ['path' => 'assets/images/gallery/printer-repair.svg', 'title' => 'Printer repair'],
            ['path' => 'assets/images/gallery/toner-refill.svg', 'title' => 'Toner refill'],
            ['path' => 'assets/images/gallery/printer-repair.svg', 'title' => 'Office printer service'],
        ];
    } elseif (str_contains($blob, 'cctv') || str_contains($blob, 'camera')) {
        $set = [
            ['path' => 'assets/images/gallery/cctv-install.svg', 'title' => 'CCTV installation'],
            ['path' => 'assets/images/gallery/camera-setup.svg', 'title' => 'Camera setup'],
            ['path' => 'assets/images/gallery/cctv-install.svg', 'title' => 'DVR / NVR setup'],
        ];
    } elseif (str_contains($blob, 'maintenance')) {
        $set = [
            ['path' => 'assets/images/gallery/pc-cleaning.svg', 'title' => 'Preventive maintenance'],
            ['path' => 'assets/images/gallery/laptop-repair.svg', 'title' => 'Health checks'],
            ['path' => 'assets/images/gallery/motherboard-repair.svg', 'title' => 'Repair follow-up'],
        ];
    }

    $out = [];
    $i = 0;
    foreach ($set as $row) {
        $path = (string) ($row['path'] ?? '');
        if ($path === '' || !public_asset_file_exists($path)) {
            continue;
        }
        $out[] = [
            'id' => -1 - $i,
            'image_path' => $path,
            'title' => (string) ($row['title'] ?? ''),
        ];
        $i++;
    }

    return $out;
}

function vk_service_gallery_process_upload(array $file, int $serviceId): array
{
    $out = ['path' => null, 'error' => null];
    if ($serviceId <= 0) {
        $out['error'] = 'Invalid service.';
        return $out;
    }
    if (!extension_loaded('gd')) {
        $out['error'] = 'Server missing GD extension.';
        return $out;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $out;
    }
    if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        $out['error'] = 'Upload failed.';
        return $out;
    }
    if ((int) ($file['size'] ?? 0) > VK_GALLERY_MAX_BYTES) {
        $out['error'] = 'Each image must be 3MB or smaller.';
        return $out;
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $out['error'] = 'Invalid upload.';
        return $out;
    }
    $info = @getimagesize($tmp);
    if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        $out['error'] = 'Use JPG, PNG, or WebP images.';
        return $out;
    }
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = is_object($finfo) ? $finfo->file($tmp) : false;
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        if (!is_string($mime) || !in_array($mime, $allowedMime, true)) {
            $out['error'] = 'Invalid image MIME type.';
            return $out;
        }
    }

    $src = match ((int) $info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default => false,
    };
    if ($src === false) {
        $out['error'] = 'Could not read image.';
        return $out;
    }

    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 1 || $sh < 1) {
        imagedestroy($src);
        $out['error'] = 'Invalid image dimensions.';
        return $out;
    }
    $scale = max(VK_GALLERY_W / $sw, VK_GALLERY_H / $sh);
    $nw = (int) round($sw * $scale);
    $nh = (int) round($sh * $scale);
    $tmpIm = imagecreatetruecolor($nw, $nh);
    if ($tmpIm === false) {
        imagedestroy($src);
        $out['error'] = 'Image processing failed.';
        return $out;
    }
    imagecopyresampled($tmpIm, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
    imagedestroy($src);

    $sx = (int) (($nw - VK_GALLERY_W) / 2);
    $sy = (int) (($nh - VK_GALLERY_H) / 2);
    $dst = imagecreatetruecolor(VK_GALLERY_W, VK_GALLERY_H);
    if ($dst === false) {
        imagedestroy($tmpIm);
        $out['error'] = 'Image processing failed.';
        return $out;
    }
    imagecopy($dst, $tmpIm, 0, 0, $sx, $sy, VK_GALLERY_W, VK_GALLERY_H);
    imagedestroy($tmpIm);

    $dir = ROOT_PATH . '/uploads/services/gallery';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $base = 'uploads/services/gallery/service_' . $serviceId . '_g_' . (string) (int) round(microtime(true) * 1000);
    $fullBase = ROOT_PATH . '/' . $base;

    $saved = null;
    if (function_exists('imagewebp') && @imagewebp($dst, $fullBase . '.webp', VK_GALLERY_WEBP_QUALITY)) {
        $saved = $base . '.webp';
    } elseif (@imagejpeg($dst, $fullBase . '.jpg', VK_GALLERY_JPEG_QUALITY)) {
        $saved = $base . '.jpg';
    }
    imagedestroy($dst);
    if ($saved === null) {
        $out['error'] = 'Could not save image.';
        return $out;
    }
    $out['path'] = $saved;

    return $out;
}
