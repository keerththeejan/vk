<?php
declare(strict_types=1);

require_once __DIR__ . '/service_gallery.php';

function vk_sg_admin_log(string $level, string $message, array $context = []): void
{
    $payload = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    error_log('[service_gallery_admin][' . $level . '] ' . $message . $payload);
}

/** @return array{can_access:bool,can_upload:bool,can_edit:bool,can_delete:bool,can_bulk:bool,can_export:bool,is_super_admin:bool} */
function vk_sg_admin_permissions(string $role): array
{
    return match ($role) {
        'super_admin' => [
            'can_access' => true, 'can_upload' => true, 'can_edit' => true,
            'can_delete' => true, 'can_bulk' => true, 'can_export' => true, 'is_super_admin' => true,
        ],
        'admin' => [
            'can_access' => true, 'can_upload' => true, 'can_edit' => true,
            'can_delete' => true, 'can_bulk' => true, 'can_export' => true, 'is_super_admin' => false,
        ],
        'manager' => [
            'can_access' => true, 'can_upload' => true, 'can_edit' => true,
            'can_delete' => false, 'can_bulk' => true, 'can_export' => false, 'is_super_admin' => false,
        ],
        'staff' => [
            'can_access' => true, 'can_upload' => true, 'can_edit' => false,
            'can_delete' => false, 'can_bulk' => false, 'can_export' => false, 'is_super_admin' => false,
        ],
        default => [
            'can_access' => true, 'can_upload' => false, 'can_edit' => false,
            'can_delete' => false, 'can_bulk' => false, 'can_export' => false, 'is_super_admin' => false,
        ],
    };
}

/** @return array{can_access:bool,can_upload:bool,can_edit:bool,can_delete:bool,can_bulk:bool,can_export:bool,is_super_admin:bool} */
function vk_sg_admin_require(PDO $pdo): array
{
    require_admin();
    $role = (string) ($_SESSION['user_role'] ?? 'viewer');
    $perms = vk_sg_admin_permissions($role);
    if (!$perms['can_access']) {
        flash_set('error', 'Access denied.');
        redirect('/dashboard.php');
    }
    return $perms;
}

function vk_sg_admin_audit(PDO $pdo, int $actorId, string $action, int $imageId, array $meta = []): void
{
    if (!function_exists('vk_auth_activity')) {
        return;
    }
    vk_auth_activity($pdo, $actorId, $actorId, $action, 'service_gallery', $imageId, array_merge($meta, [
        'ip' => function_exists('vk_auth_client_ip') ? vk_auth_client_ip() : null,
        'user_agent' => function_exists('vk_auth_user_agent') ? vk_auth_user_agent() : null,
    ]));
}

/** @param array<string,mixed> $filters @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,has_more:bool} */
function vk_sg_admin_list(PDO $pdo, array $filters): array
{
    vk_service_gallery_auto_migrate($pdo);

    $serviceId = max(0, (int) ($filters['service_id'] ?? 0));
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPageRaw = (string) ($filters['per_page'] ?? '12');
    $perPage = $perPageRaw === 'all' ? 0 : min(500, max(1, (int) $perPageRaw));
    $q = trim((string) ($filters['q'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    $sort = strtolower(trim((string) ($filters['sort'] ?? 'newest')));
    $category = trim((string) ($filters['category'] ?? ''));
    $status = trim((string) ($filters['status'] ?? ''));
    $featured = trim((string) ($filters['featured'] ?? ''));
    $order = $sort === 'oldest' ? 'ASC' : 'DESC';

    $where = ['1=1'];
    $params = [];

    if (vk_service_gallery_column_exists($pdo, 'deleted_at')) {
        $where[] = 'g.deleted_at IS NULL';
    }

    if ($serviceId > 0) {
        $where[] = 'g.service_id = ?';
        $params[] = $serviceId;
    }

    if ($q !== '') {
        $qClean = preg_replace('/[^\p{L}\p{N}\s._\-]/u', '', $q) ?? '';
        if ($qClean !== '') {
            $like = '%' . $qClean . '%';
            $where[] = '(g.title LIKE ? OR g.original_filename LIKE ? OR g.image_path LIKE ? OR g.description LIKE ? OR w.name LIKE ? OR w.slug LIKE ? OR u.fullname LIKE ? OR u.username LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }
    }

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'DATE(g.created_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'DATE(g.created_at) <= ?';
        $params[] = $dateTo;
    }

    if ($category !== '' && in_array($category, vk_service_gallery_categories(), true)) {
        $where[] = 'g.category = ?';
        $params[] = $category;
    }

    if ($status !== '' && in_array($status, ['published', 'hidden', 'draft'], true)) {
        $where[] = 'g.status = ?';
        $params[] = $status;
    }

    if ($featured === '1') {
        $where[] = 'g.is_featured = 1';
    } elseif ($featured === '0') {
        $where[] = 'g.is_featured = 0';
    }

    $whereSql = implode(' AND ', $where);
    $joinUser = db_table_exists($pdo, 'users')
        ? 'LEFT JOIN users u ON u.id = g.uploaded_by'
        : 'LEFT JOIN users u ON 1=0';

    $countSt = $pdo->prepare(
        "SELECT COUNT(*) FROM service_gallery g
         INNER JOIN web_services w ON w.id = g.service_id AND w.active = 1
         {$joinUser}
         WHERE {$whereSql}"
    );
    $countSt->execute($params);
    $total = (int) $countSt->fetchColumn();

    $limitSql = $perPage === 0 ? '' : ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
    $sql = "SELECT g.*, w.name AS service_name, w.slug AS service_slug,
                   COALESCE(u.fullname, u.username, '') AS uploader_name
            FROM service_gallery g
            INNER JOIN web_services w ON w.id = g.service_id AND w.active = 1
            {$joinUser}
            WHERE {$whereSql}
            ORDER BY g.is_featured DESC, g.created_at {$order}, g.id {$order}
            {$limitSql}";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $r) {
        $items[] = vk_sg_admin_format_row($r);
    }

    if ($total === 0 && $page === 1) {
        $items = vk_service_gallery_admin_sample_items();
        $total = count($items);
    }

    $shown = count($items);
    $hasMore = $perPage > 0 && $total > 0 ? (($page - 1) * $perPage + $shown) < $total : false;

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage === 0 ? $total : $perPage,
        'has_more' => $hasMore,
    ];
}

/** @param array<string,mixed> $r @return array<string,mixed> */
function vk_sg_admin_format_row(array $r): array
{
    $path = vk_service_gallery_resolve_existing_path((string) ($r['image_path'] ?? '')) ?? '';
    $thumb = trim((string) ($r['thumb_path'] ?? ''));
    $thumbResolved = $thumb !== '' ? (vk_service_gallery_resolve_existing_path($thumb) ?? $path) : $path;
    $fileSize = (int) ($r['file_size'] ?? 0);
    if ($fileSize <= 0 && $path !== '') {
        $full = ROOT_PATH . '/' . ltrim($path, '/');
        if (is_file($full)) {
            $fileSize = (int) filesize($full);
        }
    }

    return [
        'id' => (int) ($r['id'] ?? 0),
        'service_id' => (int) ($r['service_id'] ?? 0),
        'image_path' => $path,
        'thumb_path' => $thumbResolved,
        'medium_path' => vk_service_gallery_resolve_existing_path((string) ($r['medium_path'] ?? '')) ?? $path,
        'image_url' => $path !== '' ? public_asset_url($path) : '',
        'thumb_url' => $thumbResolved !== '' ? public_asset_url($thumbResolved) : '',
        'title' => trim((string) ($r['title'] ?? '')),
        'description' => trim((string) ($r['description'] ?? '')),
        'alt_text' => trim((string) ($r['alt_text'] ?? '')),
        'category' => trim((string) ($r['category'] ?? 'general')),
        'seo_keywords' => trim((string) ($r['seo_keywords'] ?? '')),
        'display_order' => (int) ($r['display_order'] ?? 0),
        'is_featured' => !empty($r['is_featured']),
        'status' => (string) ($r['status'] ?? 'published'),
        'original_filename' => trim((string) ($r['original_filename'] ?? '')),
        'created_at' => (string) ($r['created_at'] ?? ''),
        'service_name' => (string) ($r['service_name'] ?? ''),
        'service_slug' => (string) ($r['service_slug'] ?? ''),
        'uploader_name' => (string) ($r['uploader_name'] ?? ''),
        'file_size' => $fileSize,
        'file_size_label' => vk_service_gallery_format_bytes($fileSize),
        'width' => (int) ($r['width'] ?? 0),
        'height' => (int) ($r['height'] ?? 0),
        'resolution' => ((int) ($r['width'] ?? 0)) > 0 ? ($r['width'] . '×' . $r['height']) : '—',
        'is_sample' => false,
    ];
}

/** @param array<string,mixed> $data @return array{ok:bool,error?:string,item?:array<string,mixed>} */
function vk_sg_admin_upload(PDO $pdo, array $file, int $serviceId, int $actorId): array
{
    vk_service_gallery_auto_migrate($pdo);
    $chk = $pdo->prepare('SELECT id FROM web_services WHERE id = ? AND active = 1 LIMIT 1');
    $chk->execute([$serviceId]);
    if (!$chk->fetchColumn()) {
        return ['ok' => false, 'error' => 'Invalid or inactive service.'];
    }

    $res = vk_service_gallery_process_upload($file, $serviceId, $pdo);
    if (($res['error'] ?? null) !== null) {
        return ['ok' => false, 'error' => (string) $res['error']];
    }

    $path = (string) ($res['path'] ?? '');
    $origName = vk_service_gallery_sanitize_original_name((string) ($file['name'] ?? ''));
    $title = $origName !== '' ? pathinfo($origName, PATHINFO_FILENAME) : pathinfo($path, PATHINFO_FILENAME);
    $title = trim((string) $title) ?: 'Gallery image';

    $st = $pdo->prepare(
        'INSERT INTO service_gallery
        (service_id, image_path, thumb_path, medium_path, title, original_filename, description, alt_text,
         category, file_size, width, height, uploaded_by, file_hash, status, is_featured)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'published\', 0)'
    );
    $st->execute([
        $serviceId,
        $path,
        $res['thumb_path'] ?? null,
        $res['medium_path'] ?? null,
        mb_substr($title, 0, 255),
        $origName !== '' ? mb_substr($origName, 0, 255) : null,
        null,
        mb_substr($title, 0, 255),
        'general',
        (int) ($res['file_size'] ?? 0),
        (int) ($res['width'] ?? 0),
        (int) ($res['height'] ?? 0),
        $actorId > 0 ? $actorId : null,
        $res['file_hash'] ?? null,
    ]);
    $newId = (int) $pdo->lastInsertId();

    $wst = $pdo->prepare('SELECT w.name AS service_name, w.slug AS service_slug FROM web_services w WHERE w.id = ? LIMIT 1');
    $wst->execute([$serviceId]);
    $meta = $wst->fetch(PDO::FETCH_ASSOC) ?: [];

    vk_sg_admin_audit($pdo, $actorId, 'gallery_upload', $newId, ['service_id' => $serviceId, 'path' => $path]);

    $row = array_merge($meta, [
        'id' => $newId,
        'service_id' => $serviceId,
        'image_path' => $path,
        'thumb_path' => $res['thumb_path'] ?? null,
        'medium_path' => $res['medium_path'] ?? null,
        'title' => $title,
        'original_filename' => $origName,
        'created_at' => date('Y-m-d H:i:s'),
        'file_size' => (int) ($res['file_size'] ?? 0),
        'width' => (int) ($res['width'] ?? 0),
        'height' => (int) ($res['height'] ?? 0),
        'status' => 'published',
        'is_featured' => 0,
        'category' => 'general',
        'uploader_name' => (string) ($_SESSION['user_name'] ?? ''),
    ]);

    return ['ok' => true, 'item' => vk_sg_admin_format_row($row)];
}

/** @param array<string,mixed> $data @return array{ok:bool,error?:string,item?:array<string,mixed>} */
function vk_sg_admin_update(PDO $pdo, int $id, array $data, int $actorId): array
{
    vk_service_gallery_auto_migrate($pdo);
    $st = $pdo->prepare(
        'SELECT g.*, w.name AS service_name, w.slug AS service_slug FROM service_gallery g
         INNER JOIN web_services w ON w.id = g.service_id WHERE g.id = ? LIMIT 1'
    );
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Image not found.'];
    }

    $title = trim((string) ($data['title'] ?? $row['title'] ?? ''));
    if ($title === '') {
        return ['ok' => false, 'error' => 'Title is required.'];
    }

    $category = trim((string) ($data['category'] ?? $row['category'] ?? 'general'));
    if (!in_array($category, vk_service_gallery_categories(), true)) {
        $category = 'general';
    }

    $status = trim((string) ($data['status'] ?? $row['status'] ?? 'published'));
    if (!in_array($status, ['published', 'hidden', 'draft'], true)) {
        $status = 'published';
    }

    $pdo->prepare(
        'UPDATE service_gallery SET title = ?, description = ?, alt_text = ?, category = ?,
         seo_keywords = ?, display_order = ?, is_featured = ?, status = ? WHERE id = ?'
    )->execute([
        mb_substr($title, 0, 255),
        mb_substr(trim((string) ($data['description'] ?? $row['description'] ?? '')), 0, 5000),
        mb_substr(trim((string) ($data['alt_text'] ?? $row['alt_text'] ?? '')), 0, 255),
        $category,
        mb_substr(trim((string) ($data['seo_keywords'] ?? $row['seo_keywords'] ?? '')), 0, 500),
        max(0, (int) ($data['display_order'] ?? $row['display_order'] ?? 0)),
        !empty($data['is_featured']) ? 1 : 0,
        $status,
        $id,
    ]);

    vk_sg_admin_audit($pdo, $actorId, 'gallery_edit', $id, ['title' => $title]);

    $st->execute([$id]);
    $fresh = $st->fetch(PDO::FETCH_ASSOC) ?: $row;

    return ['ok' => true, 'item' => vk_sg_admin_format_row($fresh)];
}

/** @param list<int> $ids @return array{ok:bool,error?:string,affected?:int} */
function vk_sg_admin_bulk(PDO $pdo, string $action, array $ids, int $actorId, ?int $targetServiceId = null): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0));
    if ($ids === []) {
        return ['ok' => false, 'error' => 'No valid images selected.'];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    return match ($action) {
        'delete' => vk_sg_admin_bulk_delete($pdo, $ids, $actorId),
        'publish' => vk_sg_admin_bulk_status($pdo, $ids, 'published', $actorId),
        'hide' => vk_sg_admin_bulk_status($pdo, $ids, 'hidden', $actorId),
        'draft' => vk_sg_admin_bulk_status($pdo, $ids, 'draft', $actorId),
        'feature' => vk_sg_admin_bulk_feature($pdo, $ids, true, $actorId),
        'unfeature' => vk_sg_admin_bulk_feature($pdo, $ids, false, $actorId),
        'move' => vk_sg_admin_bulk_move($pdo, $ids, (int) $targetServiceId, $actorId),
        default => ['ok' => false, 'error' => 'Unknown bulk action.'],
    };
}

/** @param list<int> $ids @return array{ok:bool,affected:int} */
function vk_sg_admin_bulk_delete(PDO $pdo, array $ids, int $actorId): array
{
    $affected = 0;
    foreach ($ids as $id) {
        $res = vk_service_gallery_delete_by_id($pdo, $id);
        if ($res['ok']) {
            $affected++;
            vk_sg_admin_audit($pdo, $actorId, 'gallery_delete', $id, []);
        }
    }

    return ['ok' => true, 'affected' => $affected];
}

/** @param list<int> $ids @return array{ok:bool,affected:int} */
function vk_sg_admin_bulk_status(PDO $pdo, array $ids, string $status, int $actorId): array
{
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$status], $ids);
    $st = $pdo->prepare("UPDATE service_gallery SET status = ? WHERE id IN ({$ph})");
    $st->execute($params);
    $affected = $st->rowCount();
    vk_sg_admin_audit($pdo, $actorId, 'gallery_bulk_status', 0, ['status' => $status, 'ids' => $ids]);

    return ['ok' => true, 'affected' => $affected];
}

/** @param list<int> $ids @return array{ok:bool,affected:int} */
function vk_sg_admin_bulk_feature(PDO $pdo, array $ids, bool $featured, int $actorId): array
{
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$featured ? 1 : 0], $ids);
    $st = $pdo->prepare("UPDATE service_gallery SET is_featured = ? WHERE id IN ({$ph})");
    $st->execute($params);
    vk_sg_admin_audit($pdo, $actorId, $featured ? 'gallery_bulk_feature' : 'gallery_bulk_unfeature', 0, ['ids' => $ids]);

    return ['ok' => true, 'affected' => $st->rowCount()];
}

/** @param list<int> $ids @return array{ok:bool,error?:string,affected?:int} */
function vk_sg_admin_bulk_move(PDO $pdo, array $ids, int $serviceId, int $actorId): array
{
    if ($serviceId <= 0) {
        return ['ok' => false, 'error' => 'Select a target album/service.'];
    }
    $chk = $pdo->prepare('SELECT id FROM web_services WHERE id = ? AND active = 1 LIMIT 1');
    $chk->execute([$serviceId]);
    if (!$chk->fetchColumn()) {
        return ['ok' => false, 'error' => 'Invalid target service.'];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$serviceId], $ids);
    $st = $pdo->prepare("UPDATE service_gallery SET service_id = ? WHERE id IN ({$ph})");
    $st->execute($params);
    vk_sg_admin_audit($pdo, $actorId, 'gallery_bulk_move', 0, ['service_id' => $serviceId, 'ids' => $ids]);

    return ['ok' => true, 'affected' => $st->rowCount()];
}

/** @return list<array{id:int,name:string,slug:string,image_count:int}> */
function vk_sg_admin_albums(PDO $pdo): array
{
    vk_service_gallery_auto_migrate($pdo);
    $deleted = vk_service_gallery_column_exists($pdo, 'deleted_at') ? ' AND g.deleted_at IS NULL' : '';
    $sql = "SELECT w.id, w.name, w.slug, COUNT(g.id) AS image_count
            FROM web_services w
            LEFT JOIN service_gallery g ON g.service_id = w.id{$deleted}
            WHERE w.active = 1
            GROUP BY w.id, w.name, w.slug
            ORDER BY w.sort_order ASC, w.id ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static fn (array $r): array => [
        'id' => (int) ($r['id'] ?? 0),
        'name' => (string) ($r['name'] ?? ''),
        'slug' => (string) ($r['slug'] ?? ''),
        'image_count' => (int) ($r['image_count'] ?? 0),
    ], $rows);
}

/** @param list<array<string,mixed>> $items */
function vk_sg_admin_export_csv(array $items): string
{
    $out = fopen('php://temp', 'r+');
    if ($out === false) {
        return '';
    }
    fputcsv($out, ['id', 'service', 'title', 'category', 'status', 'featured', 'filename', 'size', 'resolution', 'uploaded_by', 'created_at']);
    foreach ($items as $it) {
        fputcsv($out, [
            $it['id'] ?? '',
            $it['service_name'] ?? '',
            $it['title'] ?? '',
            $it['category'] ?? '',
            $it['status'] ?? '',
            !empty($it['is_featured']) ? 'yes' : 'no',
            $it['original_filename'] ?? '',
            $it['file_size_label'] ?? '',
            $it['resolution'] ?? '',
            $it['uploader_name'] ?? '',
            $it['created_at'] ?? '',
        ]);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    return is_string($csv) ? $csv : '';
}

/** @param list<int> $ids @return array{ok:bool,error?:string,path?:string} */
function vk_sg_admin_build_zip(PDO $pdo, array $ids): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'ZIP extension not available.'];
    }
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0));
    if ($ids === []) {
        return ['ok' => false, 'error' => 'No images selected.'];
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, image_path, original_filename, title FROM service_gallery WHERE id IN ({$ph})");
    $st->execute($ids);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $zip = new ZipArchive();
    $tmp = tempnam(sys_get_temp_dir(), 'vk_sg_');
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'error' => 'Could not create ZIP archive.'];
    }
    foreach ($rows as $r) {
        $rel = vk_service_gallery_resolve_existing_path((string) ($r['image_path'] ?? ''));
        if ($rel === null) {
            continue;
        }
        $full = ROOT_PATH . '/' . ltrim($rel, '/');
        if (!is_file($full)) {
            continue;
        }
        $name = vk_service_gallery_sanitize_original_name((string) ($r['original_filename'] ?? basename($rel)));
        if ($name === '') {
            $name = 'image_' . (int) ($r['id'] ?? 0) . '.' . pathinfo($rel, PATHINFO_EXTENSION);
        }
        $zip->addFile($full, $name);
    }
    $zip->close();

    return ['ok' => true, 'path' => $tmp];
}
