<?php
declare(strict_types=1);
/**
 * JSON API: service template images (admin only).
 * Actions: upload, list, delete, set_primary, reorder, update_caption
 */
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

header('Content-Type: application/json; charset=utf-8');

if (!db_table_exists($pdo, 'service_images')) {
    echo json_encode(['ok' => false, 'error' => 'service_images table missing. Run sql/upgrade_service_template_images.sql']);
    exit;
}

const ST_MAX_IMAGES = 10;
const ST_MAX_BYTES = 4 * 1024 * 1024;

function st_json_out(bool $ok, array $data = []): void
{
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}

function st_template_exists(PDO $pdo, int $id): bool
{
    $st = $pdo->prepare('SELECT 1 FROM service_templates WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return (bool) $st->fetchColumn();
}

function st_image_belongs(PDO $pdo, int $imageId, int $serviceId): bool
{
    $st = $pdo->prepare('SELECT 1 FROM service_images WHERE id = ? AND service_id = ? LIMIT 1');
    $st->execute([$imageId, $serviceId]);
    return (bool) $st->fetchColumn();
}

function st_count_images(PDO $pdo, int $serviceId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM service_images WHERE service_id = ?');
    $st->execute([$serviceId]);
    return (int) $st->fetchColumn();
}

function st_save_upload(array $file, int $serviceId): ?string
{
    if (empty($file['name']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if ((int) $file['size'] > ST_MAX_BYTES) {
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return null;
    }
    $ext = match ($info[2]) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        default => 'bin',
    };
    $dir = ROOT_PATH . '/uploads/services';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fn = 'st' . $serviceId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $rel = 'uploads/services/' . $fn;
    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $fn)) {
        return $rel;
    }
    return null;
}

function st_unlink_if_under_uploads(string $relativePath): void
{
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    if (!str_starts_with($relativePath, 'uploads/services/')) {
        return;
    }
    $full = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($full)) {
        @unlink($full);
    }
}

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

try {
    switch ($action) {
        case 'list':
            $sid = (int) ($_GET['service_id'] ?? 0);
            if ($sid <= 0 || !st_template_exists($pdo, $sid)) {
                st_json_out(false, ['error' => 'Invalid template.']);
                break;
            }
            $st = $pdo->prepare('SELECT id, image_path, caption, is_primary, sort_order, created_at FROM service_images WHERE service_id = ? ORDER BY sort_order ASC, id ASC');
            $st->execute([$sid]);
            $rows = $st->fetchAll();
            st_json_out(true, ['images' => $rows, 'max' => ST_MAX_IMAGES]);
            break;

        case 'upload':
            $sid = (int) ($_POST['service_id'] ?? 0);
            if ($sid <= 0 || !st_template_exists($pdo, $sid)) {
                st_json_out(false, ['error' => 'Invalid template.']);
                break;
            }
            if (st_count_images($pdo, $sid) >= ST_MAX_IMAGES) {
                st_json_out(false, ['error' => 'Maximum ' . ST_MAX_IMAGES . ' images reached.']);
                break;
            }
            if (empty($_FILES['file'])) {
                st_json_out(false, ['error' => 'No file uploaded.']);
                break;
            }
            $path = st_save_upload($_FILES['file'], $sid);
            if ($path === null) {
                st_json_out(false, ['error' => 'Invalid image. Use JPG, PNG, or WebP (max 4MB).']);
                break;
            }
            $nextOrder = st_count_images($pdo, $sid);
            $isFirst = $nextOrder === 0;
            $pdo->beginTransaction();
            if ($isFirst) {
                $pdo->prepare('INSERT INTO service_images (service_id, image_path, caption, is_primary, sort_order) VALUES (?,?,NULL,1,0)')->execute([$sid, $path]);
            } else {
                $pdo->prepare('INSERT INTO service_images (service_id, image_path, caption, is_primary, sort_order) VALUES (?,?,NULL,0,?)')->execute([$sid, $path, $nextOrder]);
            }
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();
            $st = $pdo->prepare('SELECT id, image_path, caption, is_primary, sort_order, created_at FROM service_images WHERE id = ?');
            $st->execute([$newId]);
            $row = $st->fetch();
            st_json_out(true, ['image' => $row]);
            break;

        case 'delete':
            $sid = (int) ($_POST['service_id'] ?? 0);
            $imgId = (int) ($_POST['image_id'] ?? 0);
            if ($sid <= 0 || $imgId <= 0 || !st_image_belongs($pdo, $imgId, $sid)) {
                st_json_out(false, ['error' => 'Invalid image.']);
                break;
            }
            $st = $pdo->prepare('SELECT image_path, is_primary FROM service_images WHERE id = ? AND service_id = ?');
            $st->execute([$imgId, $sid]);
            $row = $st->fetch();
            if (!$row) {
                st_json_out(false, ['error' => 'Not found.']);
                break;
            }
            $pdo->prepare('DELETE FROM service_images WHERE id = ? AND service_id = ?')->execute([$imgId, $sid]);
            st_unlink_if_under_uploads((string) $row['image_path']);
            if ((int) $row['is_primary'] === 1) {
                $st2 = $pdo->prepare('SELECT id FROM service_images WHERE service_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1');
                $st2->execute([$sid]);
                $first = $st2->fetchColumn();
                if ($first) {
                    $pdo->prepare('UPDATE service_images SET is_primary = 1 WHERE id = ?')->execute([(int) $first]);
                }
            }
            st_json_out(true, ['deleted' => $imgId]);
            break;

        case 'set_primary':
            $sid = (int) ($_POST['service_id'] ?? 0);
            $imgId = (int) ($_POST['image_id'] ?? 0);
            if ($sid <= 0 || $imgId <= 0 || !st_image_belongs($pdo, $imgId, $sid)) {
                st_json_out(false, ['error' => 'Invalid image.']);
                break;
            }
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE service_images SET is_primary = 0 WHERE service_id = ?')->execute([$sid]);
            $pdo->prepare('UPDATE service_images SET is_primary = 1 WHERE id = ? AND service_id = ?')->execute([$imgId, $sid]);
            $pdo->commit();
            st_json_out(true, []);
            break;

        case 'reorder':
            $sid = (int) ($_POST['service_id'] ?? 0);
            $orderRaw = $_POST['order'] ?? '[]';
            $order = is_string($orderRaw) ? json_decode($orderRaw, true) : $orderRaw;
            if ($sid <= 0 || !st_template_exists($pdo, $sid) || !is_array($order)) {
                st_json_out(false, ['error' => 'Invalid order.']);
                break;
            }
            $order = array_values(array_filter(array_map('intval', $order)));
            $pdo->beginTransaction();
            foreach ($order as $pos => $imgId) {
                if ($imgId <= 0 || !st_image_belongs($pdo, $imgId, $sid)) {
                    $pdo->rollBack();
                    st_json_out(false, ['error' => 'Invalid image in order.']);
                    exit;
                }
                $pdo->prepare('UPDATE service_images SET sort_order = ? WHERE id = ? AND service_id = ?')->execute([$pos, $imgId, $sid]);
            }
            $pdo->commit();
            st_json_out(true, []);
            break;

        case 'update_caption':
            $sid = (int) ($_POST['service_id'] ?? 0);
            $imgId = (int) ($_POST['image_id'] ?? 0);
            $caption = trim((string) ($_POST['caption'] ?? ''));
            if (strlen($caption) > 255) {
                $caption = substr($caption, 0, 255);
            }
            if ($sid <= 0 || $imgId <= 0 || !st_image_belongs($pdo, $imgId, $sid)) {
                st_json_out(false, ['error' => 'Invalid image.']);
                break;
            }
            $pdo->prepare('UPDATE service_images SET caption = ? WHERE id = ? AND service_id = ?')->execute([$caption !== '' ? $caption : null, $imgId, $sid]);
            st_json_out(true, ['caption' => $caption]);
            break;

        default:
            st_json_out(false, ['error' => 'Unknown action.']);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    st_json_out(false, ['error' => APP_DEBUG ? $e->getMessage() : 'Server error.']);
}
