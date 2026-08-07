<?php
declare(strict_types=1);

const VK_ST_CATEGORIES = ['printer', 'computer', 'cctv', 'general'];
const VK_ST_STATUSES = ['active', 'inactive', 'draft', 'archived'];

function vk_st_templates_log(string $level, string $message, array $context = []): void
{
    $payload = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    error_log('[service_templates][' . $level . '] ' . $message . $payload);
}

function vk_st_templates_column_exists(PDO $pdo, string $column): bool
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
        $chk->execute([$db, 'service_templates', $column]);

        return (int) $chk->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function vk_st_templates_auto_migrate(PDO $pdo): void
{
    if (!db_table_exists($pdo, 'service_templates')) {
        return;
    }

    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $columns = [
        'template_code' => 'VARCHAR(64) NULL DEFAULT NULL',
        'service_type' => 'VARCHAR(100) NULL DEFAULT NULL',
        'status' => "ENUM('active','inactive','draft','archived') NOT NULL DEFAULT 'active'",
        'is_default' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'version' => 'INT UNSIGNED NOT NULL DEFAULT 1',
        'created_by' => 'INT UNSIGNED NULL DEFAULT NULL',
        'updated_by' => 'INT UNSIGNED NULL DEFAULT NULL',
        'updated_at' => 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
        'deleted_at' => 'TIMESTAMP NULL DEFAULT NULL',
    ];

    foreach ($columns as $col => $def) {
        if (!vk_st_templates_column_exists($pdo, $col)) {
            try {
                $pdo->exec("ALTER TABLE service_templates ADD COLUMN {$col} {$def}");
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    try {
        $pdo->exec('CREATE UNIQUE INDEX uq_st_template_code ON service_templates (template_code)');
    } catch (Throwable $e) {
        // ignore
    }
    foreach (['idx_st_status' => '(status, deleted_at)', 'idx_st_category' => '(category, status)'] as $idx => $cols) {
        try {
            $pdo->exec("CREATE INDEX {$idx} ON service_templates {$cols}");
        } catch (Throwable $e) {
            // ignore
        }
    }

    if (!db_table_exists($pdo, 'service_template_versions')) {
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS service_template_versions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id INT UNSIGNED NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                snapshot_json JSON NOT NULL,
                change_log VARCHAR(500) NULL DEFAULT NULL,
                created_by INT UNSIGNED NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_stv_template (template_id, version),
                CONSTRAINT fk_stv_template FOREIGN KEY (template_id) REFERENCES service_templates(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            // ignore
        }
    }

    vk_st_templates_backfill_codes($pdo);
}

function vk_st_templates_backfill_codes(PDO $pdo): void
{
    if (!vk_st_templates_column_exists($pdo, 'template_code')) {
        return;
    }
    $rows = $pdo->query("SELECT id, name, template_code FROM service_templates WHERE template_code IS NULL OR template_code = ''")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
        $code = vk_st_templates_generate_code($pdo, (string) ($r['name'] ?? ''), (int) ($r['id'] ?? 0));
        $pdo->prepare('UPDATE service_templates SET template_code = ? WHERE id = ?')->execute([$code, (int) $r['id']]);
    }
}

function vk_st_templates_slugify(string $name): string
{
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');

    return $s !== '' ? substr($s, 0, 48) : 'template';
}

function vk_st_templates_generate_code(PDO $pdo, string $name, int $excludeId = 0): string
{
    $base = strtoupper(str_replace('-', '_', vk_st_templates_slugify($name)));
    if ($base === 'TEMPLATE') {
        $base = 'SVC_TPL';
    }
    $code = $base;
    $n = 1;
    while (!vk_st_templates_code_available($pdo, $code, $excludeId)) {
        $code = $base . '_' . $n;
        $n++;
    }

    return substr($code, 0, 64);
}

function vk_st_templates_code_available(PDO $pdo, string $code, int $excludeId = 0): bool
{
    if ($code === '') {
        return false;
    }
    $sql = 'SELECT id FROM service_templates WHERE template_code = ?';
    $params = [$code];
    if ($excludeId > 0) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }
    if (vk_st_templates_column_exists($pdo, 'deleted_at')) {
        $sql .= ' AND deleted_at IS NULL';
    }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute($params);

    return !$st->fetchColumn();
}

/** @return array{can_access:bool,can_manage:bool,can_create:bool,can_edit:bool,can_delete:bool,can_bulk:bool,can_export:bool,is_super_admin:bool} */
function vk_st_templates_permissions(string $role): array
{
    return match ($role) {
        'super_admin' => [
            'can_access' => true, 'can_manage' => true, 'can_create' => true, 'can_edit' => true,
            'can_delete' => true, 'can_bulk' => true, 'can_export' => true, 'is_super_admin' => true,
        ],
        'admin' => [
            'can_access' => true, 'can_manage' => true, 'can_create' => true, 'can_edit' => true,
            'can_delete' => true, 'can_bulk' => true, 'can_export' => true, 'is_super_admin' => false,
        ],
        'manager' => [
            'can_access' => true, 'can_manage' => false, 'can_create' => true, 'can_edit' => true,
            'can_delete' => false, 'can_bulk' => true, 'can_export' => false, 'is_super_admin' => false,
        ],
        'staff', 'viewer' => [
            'can_access' => true, 'can_manage' => false, 'can_create' => false, 'can_edit' => false,
            'can_delete' => false, 'can_bulk' => false, 'can_export' => false, 'is_super_admin' => false,
        ],
        default => [
            'can_access' => false, 'can_manage' => false, 'can_create' => false, 'can_edit' => false,
            'can_delete' => false, 'can_bulk' => false, 'can_export' => false, 'is_super_admin' => false,
        ],
    };
}

/** @return array{can_access:bool,can_manage:bool,can_create:bool,can_edit:bool,can_delete:bool,can_bulk:bool,can_export:bool,is_super_admin:bool} */
function vk_st_templates_require(PDO $pdo): array
{
    require_admin();
    $role = (string) ($_SESSION['user_role'] ?? 'viewer');
    $perms = vk_st_templates_permissions($role);
    if (!$perms['can_access']) {
        flash_set('error', 'Access denied.');
        redirect('/dashboard.php');
    }
    vk_st_templates_auto_migrate($pdo);

    return $perms;
}

function vk_st_templates_audit(PDO $pdo, int $actorId, string $action, int $templateId, array $meta = []): void
{
    if (!function_exists('vk_auth_activity')) {
        return;
    }
    vk_auth_activity($pdo, $actorId, $actorId, $action, 'service_template', $templateId, array_merge($meta, [
        'ip' => function_exists('vk_auth_client_ip') ? vk_auth_client_ip() : null,
        'user_agent' => function_exists('vk_auth_user_agent') ? vk_auth_user_agent() : null,
    ]));
}

function vk_st_templates_usage_count(PDO $pdo, int $templateId): int
{
    if ($templateId <= 0 || !db_table_exists($pdo, 'repair_jobs') || !db_column_exists($pdo, 'repair_jobs', 'service_template_id')) {
        return 0;
    }
    $st = $pdo->prepare('SELECT COUNT(*) FROM repair_jobs WHERE service_template_id = ?');
    $st->execute([$templateId]);

    return (int) $st->fetchColumn();
}

/** @param array<string,mixed> $filters @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
function vk_st_templates_list(PDO $pdo, array $filters): array
{
    vk_st_templates_auto_migrate($pdo);

    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPageRaw = (string) ($filters['per_page'] ?? '25');
    $perPage = $perPageRaw === 'all' ? 0 : min(500, max(1, (int) $perPageRaw));
    $q = trim((string) ($filters['q'] ?? ''));
    $category = trim((string) ($filters['category'] ?? ''));
    $status = trim((string) ($filters['status'] ?? ''));
    $serviceType = trim((string) ($filters['service_type'] ?? ''));
    $featured = trim((string) ($filters['is_default'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    $sort = (string) ($filters['sort'] ?? 'created_at');
    $dir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

    $allowedSort = [
        'name' => 't.name',
        'template_code' => 't.template_code',
        'category' => 't.category',
        'service_type' => 't.service_type',
        'default_amount' => 't.default_amount',
        'status' => 't.status',
        'version' => 't.version',
        'created_at' => 't.created_at',
        'updated_at' => 't.updated_at',
        'usage_count' => 'usage_count',
    ];
    $orderCol = $allowedSort[$sort] ?? 't.created_at';

    $where = ['1=1'];
    $params = [];

    if (vk_st_templates_column_exists($pdo, 'deleted_at')) {
        $where[] = 't.deleted_at IS NULL';
    }

    if ($category !== '' && in_array($category, VK_ST_CATEGORIES, true)) {
        $where[] = 't.category = ?';
        $params[] = $category;
    }

    if ($status !== '' && in_array($status, VK_ST_STATUSES, true)) {
        $where[] = 't.status = ?';
        $params[] = $status;
    }

    if ($serviceType !== '') {
        $where[] = 't.service_type = ?';
        $params[] = $serviceType;
    }

    if ($featured === '1') {
        $where[] = 't.is_default = 1';
    } elseif ($featured === '0') {
        $where[] = 't.is_default = 0';
    }

    if ($q !== '') {
        $like = '%' . (preg_replace('/[^\p{L}\p{N}\s._\-]/u', '', $q) ?? '') . '%';
        if ($like !== '%%') {
            $where[] = '(t.name LIKE ? OR t.template_code LIKE ? OR t.description LIKE ? OR t.service_type LIKE ? OR u.fullname LIKE ? OR u.username LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
    }

    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'DATE(t.created_at) >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'DATE(t.created_at) <= ?';
        $params[] = $dateTo;
    }

    $whereSql = implode(' AND ', $where);
    $usageJoin = db_table_exists($pdo, 'repair_jobs') && db_column_exists($pdo, 'repair_jobs', 'service_template_id')
        ? 'LEFT JOIN (SELECT service_template_id, COUNT(*) AS usage_count FROM repair_jobs GROUP BY service_template_id) uu ON uu.service_template_id = t.id'
        : 'LEFT JOIN (SELECT 0 AS service_template_id, 0 AS usage_count) uu ON 1=0';
    $userJoin = db_table_exists($pdo, 'users') ? 'LEFT JOIN users u ON u.id = t.created_by' : 'LEFT JOIN users u ON 1=0';

    $hasThumb = db_column_exists($pdo, 'service_templates', 'image_thumb');
    $hasImg = db_column_exists($pdo, 'service_templates', 'image');
    if ($hasImg && $hasThumb) {
        $thumbExpr = "COALESCE(NULLIF(t.image_thumb, ''), t.image)";
    } elseif ($hasImg) {
        $thumbExpr = 't.image';
    } else {
        $thumbExpr = 'NULL';
    }

    $countSt = $pdo->prepare(
        "SELECT COUNT(*) FROM service_templates t {$userJoin} WHERE {$whereSql}"
    );
    $countSt->execute($params);
    $total = (int) $countSt->fetchColumn();

    $limitSql = $perPage === 0 ? '' : ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) (($page - 1) * $perPage);
    $sql = "SELECT t.*, {$thumbExpr} AS thumb_path,
                   COALESCE(uu.usage_count, 0) AS usage_count,
                   COALESCE(u.fullname, u.username, '') AS creator_name
            FROM service_templates t
            {$usageJoin}
            {$userJoin}
            WHERE {$whereSql}
            ORDER BY {$orderCol} {$dir}, t.id {$dir}
            {$limitSql}";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = array_map(static fn (array $r): array => vk_st_templates_format_row($r), $rows);
    $effectivePerPage = $perPage === 0 ? max(1, $total) : $perPage;

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $effectivePerPage,
        'total_pages' => $perPage === 0 ? 1 : max(1, (int) ceil($total / $effectivePerPage)),
    ];
}

/** @param array<string,mixed> $r @return array<string,mixed> */
function vk_st_templates_format_row(array $r): array
{
    $thumb = trim((string) ($r['thumb_path'] ?? ''));
    $base = defined('BASE_URL') ? BASE_URL : '';

    return [
        'id' => (int) ($r['id'] ?? 0),
        'name' => (string) ($r['name'] ?? ''),
        'template_code' => (string) ($r['template_code'] ?? ''),
        'category' => (string) ($r['category'] ?? 'general'),
        'service_type' => (string) ($r['service_type'] ?? $r['category'] ?? 'general'),
        'description' => (string) ($r['description'] ?? ''),
        'default_amount' => (float) ($r['default_amount'] ?? 0),
        'status' => (string) ($r['status'] ?? 'active'),
        'is_default' => !empty($r['is_default']),
        'version' => (int) ($r['version'] ?? 1),
        'usage_count' => (int) ($r['usage_count'] ?? 0),
        'creator_name' => (string) ($r['creator_name'] ?? ''),
        'created_at' => (string) ($r['created_at'] ?? ''),
        'updated_at' => (string) ($r['updated_at'] ?? ''),
        'thumb_path' => $thumb,
        'thumb_url' => $thumb !== '' ? rtrim($base, '/') . '/' . ltrim($thumb, '/') : '',
        'public_url' => rtrim($base, '/') . '/service-template-detail.php?id=' . (int) ($r['id'] ?? 0),
    ];
}

/** @return array{ok:bool,error?:string,item?:array<string,mixed>} */
function vk_st_templates_get(PDO $pdo, int $id): array
{
    vk_st_templates_auto_migrate($pdo);
    $sql = 'SELECT t.* FROM service_templates t WHERE t.id = ?';
    if (vk_st_templates_column_exists($pdo, 'deleted_at')) {
        $sql .= ' AND t.deleted_at IS NULL';
    }
    $st = $pdo->prepare($sql);
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Template not found.'];
    }
    $formatted = vk_st_templates_format_row($row);
    $formatted['usage_count'] = vk_st_templates_usage_count($pdo, $id);
    $formatted['versions'] = vk_st_templates_version_list($pdo, $id);

    return ['ok' => true, 'item' => $formatted];
}

/** @return list<array<string,mixed>> */
function vk_st_templates_version_list(PDO $pdo, int $templateId, int $limit = 10): array
{
    if (!db_table_exists($pdo, 'service_template_versions')) {
        return [];
    }
    $st = $pdo->prepare(
        'SELECT v.id, v.version, v.change_log, v.created_at, COALESCE(u.fullname, u.username, \'\') AS author
         FROM service_template_versions v
         LEFT JOIN users u ON u.id = v.created_by
         WHERE v.template_id = ?
         ORDER BY v.version DESC
         LIMIT ?'
    );
    $st->bindValue(1, $templateId, PDO::PARAM_INT);
    $st->bindValue(2, max(1, min(50, $limit)), PDO::PARAM_INT);
    $st->execute();

    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function vk_st_templates_snapshot_version(PDO $pdo, int $templateId, int $actorId, string $changeLog = 'Updated'): void
{
    if (!db_table_exists($pdo, 'service_template_versions')) {
        return;
    }
    $st = $pdo->prepare('SELECT * FROM service_templates WHERE id = ? LIMIT 1');
    $st->execute([$templateId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }
    $version = (int) ($row['version'] ?? 1);
    $pdo->prepare(
        'INSERT INTO service_template_versions (template_id, version, snapshot_json, change_log, created_by)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $templateId,
        $version,
        json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        mb_substr($changeLog, 0, 500),
        $actorId > 0 ? $actorId : null,
    ]);
}

function vk_st_templates_bump_version(PDO $pdo, int $templateId, int $actorId): void
{
    if (!vk_st_templates_column_exists($pdo, 'version')) {
        return;
    }
    $pdo->prepare('UPDATE service_templates SET version = version + 1, updated_by = ? WHERE id = ?')
        ->execute([$actorId > 0 ? $actorId : null, $templateId]);
}

/** @return array{ok:bool,error?:string,id?:int} */
function vk_st_templates_after_create(PDO $pdo, int $id, int $actorId, ?string $code = null): array
{
    vk_st_templates_auto_migrate($pdo);
    $st = $pdo->prepare('SELECT name FROM service_templates WHERE id = ?');
    $st->execute([$id]);
    $name = (string) ($st->fetchColumn() ?: '');
    $templateCode = $code !== null && $code !== ''
        ? strtoupper(preg_replace('/[^A-Z0-9_\-]/', '_', strtoupper($code)) ?? '')
        : vk_st_templates_generate_code($pdo, $name, $id);

    if (!vk_st_templates_code_available($pdo, $templateCode, $id)) {
        $templateCode = vk_st_templates_generate_code($pdo, $name, $id);
    }

    $updates = [];
    $params = [];
    if (vk_st_templates_column_exists($pdo, 'template_code')) {
        $updates[] = 'template_code = ?';
        $params[] = $templateCode;
    }
    if (vk_st_templates_column_exists($pdo, 'created_by')) {
        $updates[] = 'created_by = ?';
        $params[] = $actorId > 0 ? $actorId : null;
    }
    if ($updates !== []) {
        $params[] = $id;
        $pdo->prepare('UPDATE service_templates SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    }
    if (vk_st_templates_column_exists($pdo, 'service_type')) {
        $pdo->prepare('UPDATE service_templates SET service_type = category WHERE id = ? AND (service_type IS NULL OR service_type = \'\')')
            ->execute([$id]);
    }

    vk_st_templates_audit($pdo, $actorId, 'template_created', $id, ['code' => $templateCode]);

    return ['ok' => true, 'id' => $id];
}

/** @return array{ok:bool,error?:string,usage_count?:int} */
function vk_st_templates_soft_delete(PDO $pdo, int $id, int $actorId, bool $force = false): array
{
    vk_st_templates_auto_migrate($pdo);
    $usage = vk_st_templates_usage_count($pdo, $id);
    if ($usage > 0 && !$force) {
        return [
            'ok' => false,
            'error' => 'Template is used by ' . $usage . ' repair job(s). Archive instead or remove references first.',
            'usage_count' => $usage,
        ];
    }

    $st = $pdo->prepare('SELECT id FROM service_templates WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    if (!$st->fetchColumn()) {
        return ['ok' => false, 'error' => 'Template not found.'];
    }

    if (vk_st_templates_column_exists($pdo, 'deleted_at')) {
        $pdo->prepare("UPDATE service_templates SET deleted_at = NOW(), status = 'archived' WHERE id = ?")->execute([$id]);
    } else {
        $pdo->prepare('DELETE FROM service_templates WHERE id = ?')->execute([$id]);
    }

    vk_st_templates_audit($pdo, $actorId, 'template_deleted', $id, ['usage' => $usage]);

    return ['ok' => true, 'usage_count' => $usage];
}

/** @return array{ok:bool,error?:string,item?:array<string,mixed>} */
function vk_st_templates_duplicate(PDO $pdo, int $id, int $actorId): array
{
    $got = vk_st_templates_get($pdo, $id);
    if (!$got['ok']) {
        return $got;
    }
    $src = $got['item'];
    $name = trim($src['name'] . ' (Copy)');
    $category = in_array($src['category'], VK_ST_CATEGORIES, true) ? $src['category'] : 'general';

    $pdo->prepare(
        'INSERT INTO service_templates (name, category, default_amount, description) VALUES (?, ?, ?, ?)'
    )->execute([$name, $category, $src['default_amount'], $src['description'] ?: null]);

    $newId = (int) $pdo->lastInsertId();
    vk_st_templates_after_create($pdo, $newId, $actorId);

    if (vk_st_templates_column_exists($pdo, 'service_type')) {
        $pdo->prepare('UPDATE service_templates SET service_type = ?, status = ?, is_default = 0 WHERE id = ?')
            ->execute([$src['service_type'] ?? $category, 'draft', $newId]);
    }

    // Copy image paths reference (same files — not duplicate files to save disk)
    $orig = $pdo->prepare('SELECT image, image_thumb FROM service_templates WHERE id = ?');
    $orig->execute([$id]);
    $imgRow = $orig->fetch(PDO::FETCH_ASSOC);
    if ($imgRow && db_column_exists($pdo, 'service_templates', 'image')) {
        $sets = ['image = ?'];
        $vals = [$imgRow['image'] ?? null];
        if (db_column_exists($pdo, 'service_templates', 'image_thumb')) {
            $sets[] = 'image_thumb = ?';
            $vals[] = $imgRow['image_thumb'] ?? null;
        }
        $vals[] = $newId;
        $pdo->prepare('UPDATE service_templates SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    vk_st_templates_audit($pdo, $actorId, 'template_duplicated', $newId, ['from' => $id]);

    return vk_st_templates_get($pdo, $newId);
}

/** @param list<int> $ids @return array{ok:bool,affected:int,error?:string} */
function vk_st_templates_bulk(PDO $pdo, string $action, array $ids, int $actorId): array
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $v): bool => $v > 0));
    if ($ids === []) {
        return ['ok' => false, 'error' => 'No templates selected.', 'affected' => 0];
    }

    if ($action === 'delete') {
        $affected = 0;
        foreach ($ids as $id) {
            $r = vk_st_templates_soft_delete($pdo, $id, $actorId);
            if ($r['ok']) {
                $affected++;
            }
        }

        return ['ok' => true, 'affected' => $affected];
    }

    $statusMap = [
        'activate' => 'active',
        'deactivate' => 'inactive',
        'archive' => 'archived',
        'draft' => 'draft',
    ];
    if (isset($statusMap[$action])) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$statusMap[$action]], $ids);
        $st = $pdo->prepare("UPDATE service_templates SET status = ? WHERE id IN ({$ph})");
        $st->execute($params);
        vk_st_templates_audit($pdo, $actorId, 'template_bulk_' . $action, 0, ['ids' => $ids]);

        return ['ok' => true, 'affected' => $st->rowCount()];
    }

    if ($action === 'duplicate') {
        $affected = 0;
        foreach ($ids as $id) {
            if (vk_st_templates_duplicate($pdo, $id, $actorId)['ok']) {
                $affected++;
            }
        }

        return ['ok' => true, 'affected' => $affected];
    }

    return ['ok' => false, 'error' => 'Unknown bulk action.', 'affected' => 0];
}

/** @param list<array<string,mixed>> $items */
function vk_st_templates_export_csv(array $items): string
{
    $out = fopen('php://temp', 'r+');
    if ($out === false) {
        return '';
    }
    fputcsv($out, ['id', 'code', 'name', 'category', 'service_type', 'amount', 'status', 'version', 'usage', 'created']);
    foreach ($items as $it) {
        fputcsv($out, [
            $it['id'] ?? '',
            $it['template_code'] ?? '',
            $it['name'] ?? '',
            $it['category'] ?? '',
            $it['service_type'] ?? '',
            $it['default_amount'] ?? '',
            $it['status'] ?? '',
            $it['version'] ?? '',
            $it['usage_count'] ?? '',
            $it['created_at'] ?? '',
        ]);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);

    return is_string($csv) ? $csv : '';
}

/** @return array{total:int,active:int,inactive:int,categories:int,total_value:float,most_used_name:string,most_used_count:int} */
function vk_st_templates_dashboard_stats(PDO $pdo): array
{
    vk_st_templates_auto_migrate($pdo);
    $del = vk_st_templates_column_exists($pdo, 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';
    $hasStatus = vk_st_templates_column_exists($pdo, 'status');
    $activeExpr = $hasStatus ? "SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END)" : 'COUNT(*)';
    $inactiveExpr = $hasStatus ? "SUM(CASE WHEN status IN ('inactive','draft','archived') THEN 1 ELSE 0 END)" : '0';

    $agg = $pdo->query(
        "SELECT COUNT(*) AS total,
                {$activeExpr} AS active_count,
                {$inactiveExpr} AS inactive_count,
                COUNT(DISTINCT category) AS categories_count,
                COALESCE(SUM(default_amount), 0) AS total_value
         FROM service_templates{$del}"
    )->fetch(PDO::FETCH_ASSOC) ?: [];

    $mostUsedName = '';
    $mostUsedCount = 0;
    if (db_table_exists($pdo, 'repair_jobs') && db_column_exists($pdo, 'repair_jobs', 'service_template_id')) {
        $delT = vk_st_templates_column_exists($pdo, 'deleted_at') ? ' AND t.deleted_at IS NULL' : '';
        $mu = $pdo->query(
            "SELECT t.name, COUNT(rj.id) AS cnt
             FROM repair_jobs rj
             INNER JOIN service_templates t ON t.id = rj.service_template_id
             WHERE 1=1{$delT}
             GROUP BY t.id, t.name
             ORDER BY cnt DESC
             LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($mu)) {
            $mostUsedName = (string) ($mu['name'] ?? '');
            $mostUsedCount = (int) ($mu['cnt'] ?? 0);
        }
    }

    return [
        'total' => (int) ($agg['total'] ?? 0),
        'active' => (int) ($agg['active_count'] ?? 0),
        'inactive' => (int) ($agg['inactive_count'] ?? 0),
        'categories' => (int) ($agg['categories_count'] ?? 0),
        'total_value' => (float) ($agg['total_value'] ?? 0),
        'most_used_name' => $mostUsedName,
        'most_used_count' => $mostUsedCount,
    ];
}

/** @return list<array{id:int,name:string,category:string,count:int}> */
function vk_st_templates_category_stats(PDO $pdo): array
{
    vk_st_templates_auto_migrate($pdo);
    $del = vk_st_templates_column_exists($pdo, 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';
    $rows = $pdo->query("SELECT category, COUNT(*) AS cnt FROM service_templates{$del} GROUP BY category ORDER BY category")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach (VK_ST_CATEGORIES as $cat) {
        $found = null;
        foreach ($rows as $r) {
            if (($r['category'] ?? '') === $cat) {
                $found = $r;
                break;
            }
        }
        $out[] = [
            'id' => $cat,
            'name' => ucfirst($cat),
            'category' => $cat,
            'count' => (int) ($found['cnt'] ?? 0),
        ];
    }

    return $out;
}

/** For repair job dropdowns — active, non-deleted templates only. */
function vk_st_templates_for_select(PDO $pdo): array
{
    vk_st_templates_auto_migrate($pdo);
    $where = '1=1';
    if (vk_st_templates_column_exists($pdo, 'deleted_at')) {
        $where .= ' AND deleted_at IS NULL';
    }
    if (vk_st_templates_column_exists($pdo, 'status')) {
        $where .= " AND status IN ('active','draft')";
    }

    return $pdo->query(
        "SELECT id, name, category, default_amount, template_code FROM service_templates WHERE {$where} ORDER BY category, name"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function vk_st_templates_rollback(PDO $pdo, int $templateId, int $versionId, int $actorId): array
{
    if (!db_table_exists($pdo, 'service_template_versions')) {
        return ['ok' => false, 'error' => 'Version history not available.'];
    }
    $st = $pdo->prepare('SELECT snapshot_json FROM service_template_versions WHERE id = ? AND template_id = ? LIMIT 1');
    $st->execute([$versionId, $templateId]);
    $json = $st->fetchColumn();
    if (!is_string($json) || $json === '') {
        return ['ok' => false, 'error' => 'Version not found.'];
    }
    $snap = json_decode($json, true);
    if (!is_array($snap)) {
        return ['ok' => false, 'error' => 'Invalid snapshot.'];
    }

    $fields = ['name', 'category', 'default_amount', 'description'];
    $sets = [];
    $params = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $snap)) {
            $sets[] = "{$f} = ?";
            $params[] = $snap[$f];
        }
    }
    if ($sets === []) {
        return ['ok' => false, 'error' => 'Nothing to restore.'];
    }
    $params[] = $templateId;
    $pdo->prepare('UPDATE service_templates SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    vk_st_templates_bump_version($pdo, $templateId, $actorId);
    vk_st_templates_snapshot_version($pdo, $templateId, $actorId, 'Rollback to version snapshot');
    vk_st_templates_audit($pdo, $actorId, 'template_rollback', $templateId, ['version_id' => $versionId]);

    return vk_st_templates_get($pdo, $templateId);
}
