<?php
declare(strict_types=1);

if (!function_exists('db_table_exists')) {
    require_once dirname(__DIR__, 2) . '/includes/functions.php';
}

/** Max raw upload size (2MB). */
const ST_SERVICE_IMAGE_MAX_BYTES = 2 * 1024 * 1024;

const ST_MAIN_W = 1200;
const ST_MAIN_H = 675;
const ST_THUMB_W = 400;
const ST_THUMB_H = 300;
const ST_GALLERY_W = 800;
const ST_GALLERY_H = 600;

const ST_WEBP_QUALITY = 82;
const ST_JPEG_FALLBACK_QUALITY = 85;

const ST_GALLERY_MAX = 10;

/**
 * @return array{main: ?string, thumb: ?string, error: ?string}
 */
function st_service_image_process_upload(array $file, int $serviceId): array
{
    $out = ['main' => null, 'thumb' => null, 'error' => null];
    if ($serviceId <= 0) {
        $out['error'] = 'Invalid template.';

        return $out;
    }
    if (!extension_loaded('gd')) {
        $out['error'] = 'Server missing GD extension for image processing.';

        return $out;
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') {
        return $out;
    }
    if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        $out['error'] = 'Upload failed.';

        return $out;
    }
    if ((int) $file['size'] > ST_SERVICE_IMAGE_MAX_BYTES) {
        $out['error'] = 'Image must be 2MB or smaller.';

        return $out;
    }
    $tmp = (string) $file['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $out['error'] = 'Invalid upload.';

        return $out;
    }
    $info = @getimagesize($tmp);
    if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        $out['error'] = 'Use JPG, PNG, or WebP only.';

        return $out;
    }
    $src = st_gd_load_image($tmp, (int) $info[2]);
    if ($src === false) {
        $out['error'] = 'Could not read image.';

        return $out;
    }
    $main = st_gd_cover_resize($src, ST_MAIN_W, ST_MAIN_H);
    $thumb = st_gd_cover_resize($src, ST_THUMB_W, ST_THUMB_H);
    imagedestroy($src);
    if ($main === false || $thumb === false) {
        if ($main !== false) {
            imagedestroy($main);
        }
        if ($thumb !== false) {
            imagedestroy($thumb);
        }
        $out['error'] = 'Could not resize image.';

        return $out;
    }
    $dir = ROOT_PATH . '/uploads/services';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ts = (string) (int) round(microtime(true) * 1000);
    $base = 'service_' . $serviceId . '_' . $ts;
    $mainRel = st_gd_save_optimized($main, 'uploads/services/' . $base);
    $thumbRel = st_gd_save_optimized($thumb, 'uploads/services/' . $base . '_thumb');
    imagedestroy($main);
    imagedestroy($thumb);
    if ($mainRel === null || $thumbRel === null) {
        st_service_image_unlink($mainRel);
        st_service_image_unlink($thumbRel);
        $out['error'] = 'Could not save optimized image.';

        return $out;
    }
    $out['main'] = $mainRel;
    $out['thumb'] = $thumbRel;

    return $out;
}

/**
 * Gallery image: 800×600 (4:3), WebP.
 *
 * @return array{path: ?string, error: ?string}
 */
function st_service_gallery_process_upload(array $file, int $serviceId): array
{
    $out = ['path' => null, 'error' => null];
    if ($serviceId <= 0) {
        $out['error'] = 'Invalid template.';

        return $out;
    }
    if (!extension_loaded('gd')) {
        $out['error'] = 'Server missing GD extension for image processing.';

        return $out;
    }
    if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        $out['error'] = 'Upload failed.';

        return $out;
    }
    if ((int) $file['size'] > ST_SERVICE_IMAGE_MAX_BYTES) {
        $out['error'] = 'Image must be 2MB or smaller.';

        return $out;
    }
    $tmp = (string) $file['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $out['error'] = 'Invalid upload.';

        return $out;
    }
    $info = @getimagesize($tmp);
    if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        $out['error'] = 'Use JPG, PNG, or WebP only.';

        return $out;
    }
    $src = st_gd_load_image($tmp, (int) $info[2]);
    if ($src === false) {
        $out['error'] = 'Could not read image.';

        return $out;
    }
    $dst = st_gd_cover_resize($src, ST_GALLERY_W, ST_GALLERY_H);
    imagedestroy($src);
    if ($dst === false) {
        $out['error'] = 'Could not resize image.';

        return $out;
    }
    $dir = ROOT_PATH . '/uploads/services';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ts = (string) (int) round(microtime(true) * 1000);
    $base = 'uploads/services/service_' . $serviceId . '_g_' . $ts;
    $rel = st_gd_save_optimized($dst, $base);
    imagedestroy($dst);
    if ($rel === null) {
        $out['error'] = 'Could not save gallery image.';

        return $out;
    }
    $out['path'] = $rel;

    return $out;
}

function st_gd_load_image(string $path, int $type)
{
    return match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($path),
        IMAGETYPE_PNG => imagecreatefrompng($path),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
        default => false,
    };
}

/** @param resource|\GdImage $src */
function st_gd_cover_resize($src, int $dstW, int $dstH)
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 1 || $sh < 1) {
        return false;
    }
    $scale = max($dstW / $sw, $dstH / $sh);
    $nw = (int) round($sw * $scale);
    $nh = (int) round($sh * $scale);
    $tmp = imagecreatetruecolor($nw, $nh);
    if ($tmp === false) {
        return false;
    }
    imagealphablending($tmp, false);
    imagesavealpha($tmp, true);
    imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);
    $sx = (int) (($nw - $dstW) / 2);
    $sy = (int) (($nh - $dstH) / 2);
    $dst = imagecreatetruecolor($dstW, $dstH);
    if ($dst === false) {
        imagedestroy($tmp);

        return false;
    }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopy($dst, $tmp, 0, 0, $sx, $sy, $dstW, $dstH);
    imagedestroy($tmp);

    return $dst;
}

/** Save as WebP when available, else JPEG. Returns relative path from project root. */
function st_gd_save_optimized($im, string $relativeBaseNoExt): ?string
{
    $relativeBaseNoExt = ltrim(str_replace('\\', '/', $relativeBaseNoExt), '/');
    $fullBase = ROOT_PATH . '/' . $relativeBaseNoExt;
    $dir = dirname($fullBase);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $webpPath = $fullBase . '.webp';
    if (function_exists('imagewebp') && @imagewebp($im, $webpPath, ST_WEBP_QUALITY)) {
        return $relativeBaseNoExt . '.webp';
    }
    $jpgPath = $fullBase . '.jpg';
    if (@imagejpeg($im, $jpgPath, ST_JPEG_FALLBACK_QUALITY)) {
        return $relativeBaseNoExt . '.jpg';
    }

    return null;
}

function st_service_image_unlink(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }
    $norm = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (!str_starts_with($norm, 'uploads/services/')) {
        return;
    }
    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $norm);
    if (is_file($full)) {
        @unlink($full);
    }
}

/** Remove main + thumb (thumb path optional — derived from main if missing). */
function st_service_image_unlink_pair(?string $mainPath, ?string $thumbPath): void
{
    st_service_image_unlink($mainPath);
    if ($thumbPath !== null && $thumbPath !== '') {
        st_service_image_unlink($thumbPath);

        return;
    }
    if ($mainPath === null || $mainPath === '') {
        return;
    }
    $norm = ltrim(str_replace('\\', '/', $mainPath), '/');
    if (preg_match('/^(.+?)(\.webp|\.jpg)$/i', $norm, $m)) {
        $derived = $m[1] . '_thumb' . $m[2];
        st_service_image_unlink($derived);
    }
}

function st_service_gallery_count(PDO $pdo, int $serviceId): int
{
    if (!db_table_exists($pdo, 'service_images')) {
        return 0;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM service_images WHERE service_id = ?');
    $st->execute([$serviceId]);

    return (int) $st->fetchColumn();
}
