<?php
declare(strict_types=1);

const VK_GALLERY_MAX_BYTES = 3 * 1024 * 1024; // 3MB each
const VK_GALLERY_W = 1200;
const VK_GALLERY_H = 675; // 16:9
const VK_GALLERY_WEBP_QUALITY = 84;
const VK_GALLERY_JPEG_QUALITY = 86;
const VK_GALLERY_THUMB_W = 400;
const VK_GALLERY_THUMB_H = 225;
const VK_GALLERY_MEDIUM_W = 800;
const VK_GALLERY_MEDIUM_H = 450;
const VK_GALLERY_SVG_MAX_BYTES = 512 * 1024;

/** @return list<string> */
function vk_service_gallery_categories(): array
{
    return ['general', 'before-after', 'equipment', 'team', 'workspace', 'promotional'];
}

function vk_service_gallery_column_exists(PDO $pdo, string $column): bool
{
    try {
        $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
        if (!is_string($db) || $db === '') {
            return false;
        }
        $chk = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $chk->execute([$db, 'service_gallery', $column]);

        return (int) $chk->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function vk_service_gallery_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 2) . ' MB';
}

function vk_service_gallery_unlink_relative(string $rel): void
{
    $rel = vk_service_gallery_normalize_db_path($rel);
    if ($rel === '' || !str_starts_with(str_replace('\\', '/', $rel), 'uploads/services/gallery/')) {
        return;
    }
    $full = ROOT_PATH . '/' . ltrim(str_replace('\\', '/', $rel), '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

/**
 * @param array<string,mixed> $row
 */
function vk_service_gallery_delete_files_from_row(array $row): void
{
    foreach (['image_path', 'thumb_path', 'medium_path'] as $key) {
        $p = trim((string) ($row[$key] ?? ''));
        if ($p !== '') {
            vk_service_gallery_unlink_relative($p);
        }
    }
}

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

    $columns = [
        'description' => 'TEXT NULL',
        'alt_text' => 'VARCHAR(255) NULL DEFAULT NULL',
        'category' => "VARCHAR(100) NOT NULL DEFAULT 'general'",
        'seo_keywords' => 'VARCHAR(500) NULL DEFAULT NULL',
        'display_order' => 'INT UNSIGNED NOT NULL DEFAULT 0',
        'is_featured' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'status' => "ENUM('published','hidden','draft') NOT NULL DEFAULT 'published'",
        'thumb_path' => 'VARCHAR(512) NULL DEFAULT NULL',
        'medium_path' => 'VARCHAR(512) NULL DEFAULT NULL',
        'file_size' => 'INT UNSIGNED NULL DEFAULT NULL',
        'width' => 'INT UNSIGNED NULL DEFAULT NULL',
        'height' => 'INT UNSIGNED NULL DEFAULT NULL',
        'uploaded_by' => 'INT UNSIGNED NULL DEFAULT NULL',
        'deleted_at' => 'TIMESTAMP NULL DEFAULT NULL',
        'file_hash' => 'CHAR(64) NULL DEFAULT NULL',
    ];
    foreach ($columns as $col => $def) {
        if (!vk_service_gallery_column_exists($pdo, $col)) {
            try {
                $pdo->exec("ALTER TABLE service_gallery ADD COLUMN {$col} {$def}");
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
    foreach (['idx_sg_status' => '(status, created_at)', 'idx_sg_featured' => '(is_featured, service_id)', 'idx_sg_hash' => '(service_id, file_hash)'] as $idx => $cols) {
        try {
            $pdo->exec("CREATE INDEX {$idx} ON service_gallery {$cols}");
        } catch (Throwable $e) {
            // ignore
        }
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
function vk_service_gallery_delete_by_id(PDO $pdo, int $imgId, bool $forceHard = false): array
{
    if ($imgId <= 0) {
        return ['ok' => false, 'error' => 'Invalid image.'];
    }
    $st = $pdo->prepare('SELECT * FROM service_gallery WHERE id = ? LIMIT 1');
    $st->execute([$imgId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Image not found.'];
    }

    $softDelete = vk_service_gallery_column_exists($pdo, 'deleted_at') && !$forceHard;
    if ($softDelete) {
        $pdo->prepare('UPDATE service_gallery SET deleted_at = NOW(), status = \'hidden\' WHERE id = ?')->execute([$imgId]);
    } else {
        vk_service_gallery_delete_files_from_row($row);
        $pdo->prepare('DELETE FROM service_gallery WHERE id = ?')->execute([$imgId]);
    }

    return ['ok' => true, 'error' => null];
}

/**
 * @return list<array{id:int,image_path:string,title:string}>
 */
function vk_service_gallery_fetch(PDO $pdo, int $serviceId, array $serviceRow): array
{
    vk_service_gallery_auto_migrate($pdo);
    $rows = [];
    if ($serviceId <= 0) {
        return vk_service_gallery_default_rows($serviceRow);
    }

    try {
        if (vk_service_gallery_table_exists($pdo)) {
            $sql = 'SELECT id, image_path, COALESCE(title, \'\') AS title, thumb_path
                    FROM service_gallery
                    WHERE service_id = ?';
            if (vk_service_gallery_column_exists($pdo, 'deleted_at')) {
                $sql .= ' AND deleted_at IS NULL';
            }
            if (vk_service_gallery_column_exists($pdo, 'status')) {
                $sql .= ' AND status = \'published\'';
            }
            $order = vk_service_gallery_column_exists($pdo, 'display_order')
                ? 'display_order ASC, id DESC'
                : 'id DESC';
            $sql .= ' ORDER BY ' . $order;
            $st = $pdo->prepare($sql);
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
        $thumb = trim((string) ($r['thumb_path'] ?? ''));
        $thumbResolved = $thumb !== '' ? vk_service_gallery_resolve_existing_path($thumb) : null;
        $valid[] = [
            'id' => (int) ($r['id'] ?? 0),
            'image_path' => $resolved,
            'thumb_path' => $thumbResolved ?? $resolved,
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

/**
 * Demo-only items for the admin gallery when no real gallery rows exist yet.
 *
 * @return list<array{id:int,service_id:int,image_path:string,image_url:string,title:string,original_filename:string,created_at:string,service_name:string,service_slug:string,is_sample:bool}>
 */
function vk_service_gallery_admin_sample_items(): array
{
    $samples = [
        [
            'service_name' => 'Computer Repair',
            'service_slug' => 'computer-repair',
            'title' => 'Laptop repair bench',
            'image_path' => 'assets/images/gallery/laptop-repair.svg',
        ],
        [
            'service_name' => 'Computer Repair',
            'service_slug' => 'computer-repair',
            'title' => 'Motherboard repair work',
            'image_path' => 'assets/images/gallery/motherboard-repair.svg',
        ],
        [
            'service_name' => 'Maintenance',
            'service_slug' => 'maintenance',
            'title' => 'Preventive cleaning service',
            'image_path' => 'assets/images/gallery/pc-cleaning.svg',
        ],
        [
            'service_name' => 'Printer Repair',
            'service_slug' => 'printer-repair',
            'title' => 'Printer repair sample',
            'image_path' => 'assets/images/gallery/printer-repair.svg',
        ],
        [
            'service_name' => 'Printer Repair',
            'service_slug' => 'printer-repair',
            'title' => 'Toner refill sample',
            'image_path' => 'assets/images/gallery/toner-refill.svg',
        ],
        [
            'service_name' => 'CCTV Installation',
            'service_slug' => 'cctv-installation',
            'title' => 'Camera setup sample',
            'image_path' => 'assets/images/gallery/camera-setup.svg',
        ],
    ];

    $items = [];
    foreach ($samples as $index => $sample) {
        $path = (string) ($sample['image_path'] ?? '');
        if ($path === '' || !public_asset_file_exists($path)) {
            continue;
        }
        $items[] = [
            'id' => -1000 - $index,
            'service_id' => 0,
            'image_path' => $path,
            'image_url' => public_asset_url($path),
            'thumb_url' => public_asset_url($path),
            'title' => (string) ($sample['title'] ?? ''),
            'original_filename' => basename($path),
            'created_at' => '',
            'service_name' => (string) ($sample['service_name'] ?? 'Sample service'),
            'service_slug' => (string) ($sample['service_slug'] ?? 'sample-service'),
            'category' => 'general',
            'status' => 'published',
            'is_featured' => false,
            'file_size_label' => '—',
            'resolution' => '—',
            'uploader_name' => '',
            'is_sample' => true,
        ];
    }

    return $items;
}

function vk_service_gallery_sanitize_svg(string $raw): ?string
{
    if (!str_contains(strtolower($raw), '<svg')) {
        return null;
    }
    $raw = preg_replace('/<\?(php|=)/i', '', $raw) ?? '';
    $raw = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $raw) ?? '';
    $raw = preg_replace('/\s(on\w+|xmlns:xlink)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $raw) ?? '';
    $raw = preg_replace('/javascript:/i', '', $raw) ?? '';
    $raw = preg_replace('/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $raw) ?? '';
    if (strlen($raw) > VK_GALLERY_SVG_MAX_BYTES) {
        return null;
    }

    return trim($raw) !== '' ? trim($raw) : null;
}

/**
 * @return \GdImage|false
 */
function vk_service_gallery_load_image(string $tmp, int $type)
{
    return match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        IMAGETYPE_GIF => @imagecreatefromgif($tmp),
        default => false,
    };
}

/** @param \GdImage $src */
function vk_service_gallery_resize_cover($src, int $tw, int $th): ?\GdImage
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 1 || $sh < 1) {
        return null;
    }
    $scale = max($tw / $sw, $th / $sh);
    $nw = (int) round($sw * $scale);
    $nh = (int) round($sh * $scale);
    $tmpIm = imagecreatetruecolor($nw, $nh);
    if ($tmpIm === false) {
        return null;
    }
    imagealphablending($tmpIm, true);
    imagesavealpha($tmpIm, true);
    imagecopyresampled($tmpIm, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
    $dst = imagecreatetruecolor($tw, $th);
    if ($dst === false) {
        imagedestroy($tmpIm);

        return null;
    }
    imagealphablending($dst, true);
    imagesavealpha($dst, true);
    $sx = (int) max(0, ($nw - $tw) / 2);
    $sy = (int) max(0, ($nh - $th) / 2);
    imagecopy($dst, $tmpIm, 0, 0, $sx, $sy, $tw, $th);
    imagedestroy($tmpIm);

    return $dst;
}

/** @param \GdImage $img */
function vk_service_gallery_save_variant($img, string $fullPath): ?string
{
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (function_exists('imagewebp') && @imagewebp($img, $fullPath . '.webp', VK_GALLERY_WEBP_QUALITY)) {
        return $fullPath . '.webp';
    }
    if (@imagejpeg($img, $fullPath . '.jpg', VK_GALLERY_JPEG_QUALITY)) {
        return $fullPath . '.jpg';
    }

    return null;
}

/**
 * @return array{path:?string,thumb_path:?string,medium_path:?string,width:int,height:int,file_size:int,file_hash:?string,error:?string}
 */
function vk_service_gallery_process_upload(array $file, int $serviceId, ?PDO $pdo = null): array
{
    $out = [
        'path' => null,
        'thumb_path' => null,
        'medium_path' => null,
        'width' => 0,
        'height' => 0,
        'file_size' => 0,
        'file_hash' => null,
        'error' => null,
    ];
    if ($serviceId <= 0) {
        $out['error'] = 'Invalid service.';

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

    $mime = '';
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = is_object($finfo) ? (string) ($finfo->file($tmp) ?: '') : '';
    }
    $origName = vk_service_gallery_sanitize_original_name((string) ($file['name'] ?? ''));
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if ($mime === 'image/svg+xml' || $ext === 'svg') {
        $raw = file_get_contents($tmp);
        if (!is_string($raw)) {
            $out['error'] = 'Could not read SVG.';

            return $out;
        }
        if (strlen($raw) > VK_GALLERY_SVG_MAX_BYTES) {
            $out['error'] = 'SVG must be 512KB or smaller.';

            return $out;
        }
        $clean = vk_service_gallery_sanitize_svg($raw);
        if ($clean === null) {
            $out['error'] = 'Invalid or unsafe SVG.';

            return $out;
        }
        $hash = hash('sha256', $clean);
        if ($pdo instanceof PDO && vk_service_gallery_column_exists($pdo, 'file_hash')) {
            $dupSql = 'SELECT id FROM service_gallery WHERE service_id = ? AND file_hash = ?';
            if (vk_service_gallery_column_exists($pdo, 'deleted_at')) {
                $dupSql .= ' AND deleted_at IS NULL';
            }
            $dupSql .= ' LIMIT 1';
            $dup = $pdo->prepare($dupSql);
            $dup->execute([$serviceId, $hash]);
            if ($dup->fetchColumn()) {
                $out['error'] = 'Duplicate file already exists in this album.';

                return $out;
            }
        }
        $stamp = (string) (int) round(microtime(true) * 1000);
        $rel = 'uploads/services/gallery/service_' . $serviceId . '_g_' . $stamp . '.svg';
        $full = ROOT_PATH . '/' . $rel;
        $dir = dirname($full);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (file_put_contents($full, $clean) === false) {
            $out['error'] = 'Could not save SVG.';

            return $out;
        }
        $out['path'] = $rel;
        $out['file_size'] = strlen($clean);
        $out['file_hash'] = $hash;

        return $out;
    }

    if (!extension_loaded('gd')) {
        $out['error'] = 'Server missing GD extension.';

        return $out;
    }

    $info = @getimagesize($tmp);
    if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        $out['error'] = 'Use JPG, PNG, WebP, GIF, or SVG images.';

        return $out;
    }
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
        $out['error'] = 'Invalid image MIME type.';

        return $out;
    }

    $hash = @hash_file('sha256', $tmp) ?: null;
    if ($hash && $pdo instanceof PDO && vk_service_gallery_column_exists($pdo, 'file_hash')) {
        $dupSql = 'SELECT id FROM service_gallery WHERE service_id = ? AND file_hash = ?';
        if (vk_service_gallery_column_exists($pdo, 'deleted_at')) {
            $dupSql .= ' AND deleted_at IS NULL';
        }
        $dupSql .= ' LIMIT 1';
        $dup = $pdo->prepare($dupSql);
        $dup->execute([$serviceId, $hash]);
        if ($dup->fetchColumn()) {
            $out['error'] = 'Duplicate file already exists in this album.';

            return $out;
        }
    }

    $src = vk_service_gallery_load_image($tmp, (int) $info[2]);
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

    $stamp = (string) (int) round(microtime(true) * 1000);
    $baseRel = 'uploads/services/gallery/service_' . $serviceId . '_g_' . $stamp;
    $thumbRelBase = 'uploads/services/gallery/thumbs/service_' . $serviceId . '_g_' . $stamp;
    $mediumRelBase = 'uploads/services/gallery/medium/service_' . $serviceId . '_g_' . $stamp;

    $fullImg = vk_service_gallery_resize_cover($src, VK_GALLERY_W, VK_GALLERY_H);
    $thumbImg = vk_service_gallery_resize_cover($src, VK_GALLERY_THUMB_W, VK_GALLERY_THUMB_H);
    $mediumImg = vk_service_gallery_resize_cover($src, VK_GALLERY_MEDIUM_W, VK_GALLERY_MEDIUM_H);
    imagedestroy($src);

    if ($fullImg === null || $thumbImg === null || $mediumImg === null) {
        if ($fullImg) {
            imagedestroy($fullImg);
        }
        if ($thumbImg) {
            imagedestroy($thumbImg);
        }
        if ($mediumImg) {
            imagedestroy($mediumImg);
        }
        $out['error'] = 'Image processing failed.';

        return $out;
    }

    $savedFull = vk_service_gallery_save_variant($fullImg, ROOT_PATH . '/' . $baseRel);
    $savedThumb = vk_service_gallery_save_variant($thumbImg, ROOT_PATH . '/' . $thumbRelBase);
    $savedMedium = vk_service_gallery_save_variant($mediumImg, ROOT_PATH . '/' . $mediumRelBase);
    imagedestroy($fullImg);
    imagedestroy($thumbImg);
    imagedestroy($mediumImg);

    if ($savedFull === null) {
        $out['error'] = 'Could not save image.';

        return $out;
    }

    $out['path'] = str_replace(ROOT_PATH . '/', '', str_replace('\\', '/', $savedFull));
    if ($savedThumb !== null) {
        $out['thumb_path'] = str_replace(ROOT_PATH . '/', '', str_replace('\\', '/', $savedThumb));
    }
    if ($savedMedium !== null) {
        $out['medium_path'] = str_replace(ROOT_PATH . '/', '', str_replace('\\', '/', $savedMedium));
    }
    $out['width'] = VK_GALLERY_W;
    $out['height'] = VK_GALLERY_H;
    $fullDisk = ROOT_PATH . '/' . ltrim($out['path'], '/');
    $out['file_size'] = is_file($fullDisk) ? (int) filesize($fullDisk) : 0;
    $out['file_hash'] = $hash;

    return $out;
}
