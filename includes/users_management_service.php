<?php
declare(strict_types=1);

/**
 * Enterprise User Management — queries, permissions, CRUD, bulk, audit.
 */

function vk_users_log(string $level, string $message, array $context = []): void
{
    $payload = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    error_log('[users_management][' . $level . '] ' . $message . $payload);
}

/** @return array{can_access:bool,can_manage:bool,can_view_all:bool,can_view_self_only:bool,department_filter:?string,is_super_admin:bool} */
function vk_users_permissions(string $role): array
{
    return match ($role) {
        'super_admin' => [
            'can_access' => true,
            'can_manage' => true,
            'can_view_all' => true,
            'can_view_self_only' => false,
            'department_filter' => null,
            'is_super_admin' => true,
        ],
        'admin' => [
            'can_access' => true,
            'can_manage' => true,
            'can_view_all' => true,
            'can_view_self_only' => false,
            'department_filter' => null,
            'is_super_admin' => false,
        ],
        'manager' => [
            'can_access' => true,
            'can_manage' => false,
            'can_view_all' => false,
            'can_view_self_only' => false,
            'department_filter' => 'session',
            'is_super_admin' => false,
        ],
        default => [
            'can_access' => true,
            'can_manage' => false,
            'can_view_all' => false,
            'can_view_self_only' => true,
            'department_filter' => null,
            'is_super_admin' => false,
        ],
    };
}

/** @return array{can_access:bool,can_manage:bool,can_view_all:bool,can_view_self_only:bool,department_filter:?string,is_super_admin:bool} */
function vk_users_session_permissions(PDO $pdo): array
{
    vk_auth_ensure_schema($pdo);
    $role = (string) ($_SESSION['user_role'] ?? 'viewer');
    $perms = vk_users_permissions($role);
    if ($perms['department_filter'] === 'session') {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        $st = $pdo->prepare('SELECT department FROM users WHERE id = ? LIMIT 1');
        $st->execute([$uid]);
        $dept = trim((string) ($st->fetchColumn() ?: ''));
        $perms['department_filter'] = $dept !== '' ? $dept : '__none__';
    }
    return $perms;
}

function vk_users_require_module(PDO $pdo): array
{
    require_admin();
    $perms = vk_users_session_permissions($pdo);
    if (!$perms['can_access']) {
        flash_set('error', 'Access denied.');
        redirect('/dashboard.php');
    }
    return $perms;
}

/** @return array{q:string,role:string,status:string,sort:string,sort_dir:string,date_from:string,date_to:string} */
function vk_users_parse_filters(array $input): array
{
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    $allowedStatus = ['', 'pending', 'approved', 'active', 'inactive', 'suspended', 'rejected'];
    if (!in_array($status, $allowedStatus, true)) {
        $status = '';
    }

    $role = strtolower(trim((string) ($input['role'] ?? '')));
    $roleMap = [
        'employee' => 'staff',
        'sales' => 'staff',
        'support' => 'staff',
        'admin' => 'admin_group',
    ];
    if (isset($roleMap[$role])) {
        $role = $roleMap[$role];
    }

    $sort = strtolower(trim((string) ($input['sort'] ?? 'created_at')));
    $allowedSort = ['created_at', 'fullname', 'username', 'email', 'role', 'status', 'last_login_at', 'department'];
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'created_at';
    }

    $sortDir = strtolower(trim((string) ($input['sort_dir'] ?? 'desc')));
    $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';

    return [
        'q' => trim((string) ($input['q'] ?? $input['search'] ?? '')),
        'role' => $role,
        'status' => $status,
        'sort' => $sort,
        'sort_dir' => $sortDir,
        'date_from' => trim((string) ($input['date_from'] ?? '')),
        'date_to' => trim((string) ($input['date_to'] ?? '')),
    ];
}

/** @param array<string,mixed> $filters @param array<string,mixed> $perms */
function vk_users_where_sql(array $filters, array $perms, int $selfUserId): array
{
    $where = ['1=1'];
    $params = [];

    if ($perms['can_view_self_only']) {
        $where[] = 'u.id = ?';
        $params[] = $selfUserId;
    } elseif (!$perms['can_view_all'] && !empty($perms['department_filter'])) {
        if ($perms['department_filter'] === '__none__') {
            $where[] = '0=1';
        } else {
            $where[] = 'u.department = ?';
            $params[] = $perms['department_filter'];
        }
    }

    if ($filters['q'] !== '') {
        $term = '%' . $filters['q'] . '%';
        $where[] = '(u.fullname LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.department LIKE ? OR u.user_uid LIKE ? OR u.role LIKE ? OR u.status LIKE ?)';
        array_push($params, $term, $term, $term, $term, $term, $term, $term, $term);
    }

    if ($filters['status'] === 'approved') {
        $where[] = "u.status IN ('approved','active')";
    } elseif ($filters['status'] !== '') {
        $where[] = 'u.status = ?';
        $params[] = $filters['status'];
    }

    if ($filters['role'] === 'admin_group') {
        $where[] = "u.role IN ('super_admin','admin')";
    } elseif ($filters['role'] !== '' && in_array($filters['role'], VK_AUTH_ROLES, true)) {
        $where[] = 'u.role = ?';
        $params[] = $filters['role'];
    }

    if ($filters['date_from'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
        $where[] = 'DATE(u.created_at) >= ?';
        $params[] = $filters['date_from'];
    }
    if ($filters['date_to'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
        $where[] = 'DATE(u.created_at) <= ?';
        $params[] = $filters['date_to'];
    }

    return [implode(' AND ', $where), $params];
}

function vk_users_order_sql(array $filters): string
{
    $col = $filters['sort'];
    $dir = $filters['sort_dir'];
    $allowed = ['created_at', 'fullname', 'username', 'email', 'role', 'status', 'last_login_at', 'department'];
    if (!in_array($col, $allowed, true)) {
        $col = 'created_at';
    }
    return "u.`{$col}` {$dir}, u.id DESC";
}

/** @param array<string,mixed> $filters @param array<string,mixed> $perms */
function vk_users_count(PDO $pdo, array $filters, array $perms, int $selfUserId): int
{
    [$where, $params] = vk_users_where_sql($filters, $perms, $selfUserId);
    $st = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
    $st->execute($params);
    return (int) $st->fetchColumn();
}

/** @return list<array<string,mixed>> */
function vk_users_fetch(PDO $pdo, array $filters, array $perms, int $selfUserId, int $page, int $perPage): array
{
    [$where, $params] = vk_users_where_sql($filters, $perms, $selfUserId);
    $order = vk_users_order_sql($filters);
    $offset = max(0, ($page - 1) * $perPage);
    $limitSql = $perPage > 0 ? 'LIMIT ? OFFSET ?' : '';

    $sql = "SELECT u.id, u.user_uid, u.username, u.email, u.phone, u.fullname, u.department, u.role,
                   u.technician_id, u.status, u.approved, u.approved_at, u.approved_by, u.created_at, u.last_login_at,
                   ab.fullname AS approved_by_name,
                   t.name AS technician_name
            FROM users u
            LEFT JOIN users ab ON ab.id = u.approved_by
            LEFT JOIN technicians t ON t.id = u.technician_id
            WHERE {$where}
            ORDER BY {$order}
            {$limitSql}";

    $st = $pdo->prepare($sql);
    $i = 1;
    foreach ($params as $param) {
        $st->bindValue($i++, $param);
    }
    if ($perPage > 0) {
        $st->bindValue($i++, $perPage, PDO::PARAM_INT);
        $st->bindValue($i, $offset, PDO::PARAM_INT);
    }
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function vk_users_get(PDO $pdo, int $userId): ?array
{
    $st = $pdo->prepare(
        "SELECT u.*, ab.fullname AS approved_by_name, t.name AS technician_name
         FROM users u
         LEFT JOIN users ab ON ab.id = u.approved_by
         LEFT JOIN technicians t ON t.id = u.technician_id
         WHERE u.id = ?
         LIMIT 1"
    );
    $st->execute([$userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    unset($row['password_hash']);

    $act = $pdo->prepare(
        "SELECT action, created_at FROM activity_logs WHERE entity_type = 'user' AND entity_id = ? ORDER BY id DESC LIMIT 10"
    );
    $act->execute([$userId]);
    $row['recent_activity'] = $act->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $log = $pdo->prepare(
        "SELECT status, failure_reason, created_at FROM login_logs WHERE user_id = ? ORDER BY id DESC LIMIT 5"
    );
    $log->execute([$userId]);
    $row['recent_logins'] = $log->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $row['status_label'] = vk_auth_status_label((string) ($row['status'] ?? ''));
    $row['role_label'] = vk_auth_role_label((string) ($row['role'] ?? ''));

    return $row;
}

function vk_users_initials(array $user): string
{
    $name = trim((string) ($user['fullname'] ?? $user['username'] ?? '?'));
    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';
    foreach ($parts as $p) {
        if ($p !== '') {
            $initials .= strtoupper($p[0]);
        }
    }
    return substr($initials, 0, 2) ?: '?';
}

function vk_users_can_modify_target(array $perms, array $target, int $actorId): bool
{
    if (!$perms['can_manage']) {
        return false;
    }
    if ((int) ($target['id'] ?? 0) === $actorId && ($target['status'] ?? '') === 'inactive') {
        return false;
    }
    if (($target['role'] ?? '') === 'super_admin' && !$perms['is_super_admin']) {
        return false;
    }
    return true;
}

function vk_users_validate_password(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must include at least one letter and one number.';
    }
    return null;
}

function vk_users_generate_password(int $length = 14): string
{
    return vk_auth_generate_password($length);
}

/** @return array{ok:bool,error?:string,id?:int,message?:string,password?:string} */
function vk_users_save(PDO $pdo, array $data, int $actorId, array $perms): array
{
    vk_auth_ensure_schema($pdo);

    if (!$perms['can_manage']) {
        return ['ok' => false, 'error' => 'Permission denied.'];
    }

    $id = (int) ($data['id'] ?? 0);
    $username = trim((string) ($data['username'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $fullname = trim((string) ($data['fullname'] ?? ''));
    $department = trim((string) ($data['department'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $role = strtolower(trim((string) ($data['role'] ?? 'staff')));
    $status = strtolower(trim((string) ($data['status'] ?? 'active')));
    $technicianId = isset($data['technician_id']) && $data['technician_id'] !== '' && $data['technician_id'] !== null
        ? (int) $data['technician_id'] : null;

    if (!in_array($role, VK_AUTH_ROLES, true)) {
        return ['ok' => false, 'error' => 'Invalid role.'];
    }
    if (!$perms['is_super_admin'] && $role === 'super_admin') {
        return ['ok' => false, 'error' => 'Only Super Admin can assign Super Admin role.'];
    }
    if (!in_array($status, VK_AUTH_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Invalid status.'];
    }
    if ($username === '' || !preg_match('/^[a-zA-Z0-9._-]{1,64}$/', $username)) {
        return ['ok' => false, 'error' => 'Username: 1–64 letters, numbers, dot, underscore, hyphen.'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Invalid email address.'];
    }
    if (mb_strlen($fullname) > 128) {
        return ['ok' => false, 'error' => 'Name is too long.'];
    }
    if ($role === 'technician' && ($technicianId === null || $technicianId <= 0)) {
        return ['ok' => false, 'error' => 'Technician role requires a linked technician profile.'];
    }
    if ($role !== 'technician') {
        $technicianId = null;
    }

    if ($technicianId !== null) {
        $chk = $pdo->prepare('SELECT id FROM technicians WHERE id = ? AND active = 1 LIMIT 1');
        $chk->execute([$technicianId]);
        if (!$chk->fetchColumn()) {
            return ['ok' => false, 'error' => 'Invalid technician.'];
        }
    }

    try {
        if ($id <= 0) {
            if ($password === '') {
                $password = vk_users_generate_password();
            }
            $pwdErr = vk_users_validate_password($password);
            if ($pwdErr) {
                return ['ok' => false, 'error' => $pwdErr];
            }

            $uq = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $uq->execute([$username]);
            if ($uq->fetchColumn()) {
                return ['ok' => false, 'error' => 'Username already taken.'];
            }
            if ($email !== '') {
                $eq = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $eq->execute([$email]);
                if ($eq->fetchColumn()) {
                    return ['ok' => false, 'error' => 'Email already in use.'];
                }
            }
            if ($phone !== '') {
                $pq = $pdo->prepare('SELECT id FROM users WHERE phone = ? LIMIT 1');
                $pq->execute([$phone]);
                if ($pq->fetchColumn()) {
                    return ['ok' => false, 'error' => 'Phone already in use.'];
                }
            }

            $approved = vk_auth_status_is_approved($status) ? 1 : 0;
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $uid = vk_auth_generate_user_uid($pdo);

            $pdo->prepare(
                'INSERT INTO users (username, email, phone, password_hash, fullname, department, user_uid, role, technician_id, status, approved, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())'
            )->execute([
                $username,
                $email === '' ? null : $email,
                $phone === '' ? null : $phone,
                $hash,
                $fullname === '' ? null : $fullname,
                $department === '' ? null : $department,
                $uid,
                $role,
                $technicianId,
                $status,
                $approved,
            ]);
            $newId = (int) $pdo->lastInsertId();
            vk_auth_activity($pdo, $newId, $actorId, 'user_created', 'user', $newId, ['role' => $role, 'status' => $status]);
            vk_users_log('info', 'User created', ['id' => $newId, 'actor' => $actorId]);

            return ['ok' => true, 'id' => $newId, 'message' => 'User created successfully.', 'generated_password' => $password];
        }

        $old = vk_users_get($pdo, $id);
        if (!$old) {
            return ['ok' => false, 'error' => 'User not found.'];
        }
        if (!vk_users_can_modify_target($perms, $old, $actorId)) {
            return ['ok' => false, 'error' => 'Permission denied for this user.'];
        }

        if (!$perms['is_super_admin'] && ($old['role'] ?? '') === 'super_admin') {
            return ['ok' => false, 'error' => 'Cannot modify Super Admin account.'];
        }

        vk_users_guard_last_admin($pdo, $old, $role, $status, $id);

        $uq = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
        $uq->execute([$username, $id]);
        if ($uq->fetchColumn()) {
            return ['ok' => false, 'error' => 'Username already taken.'];
        }
        if ($email !== '') {
            $eq = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $eq->execute([$email, $id]);
            if ($eq->fetchColumn()) {
                return ['ok' => false, 'error' => 'Email already in use.'];
            }
        }
        if ($phone !== '') {
            $pq = $pdo->prepare('SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1');
            $pq->execute([$phone, $id]);
            if ($pq->fetchColumn()) {
                return ['ok' => false, 'error' => 'Phone already in use.'];
            }
        }

        $approved = vk_auth_status_is_approved($status) ? 1 : 0;
        $params = [
            $username,
            $email === '' ? null : $email,
            $phone === '' ? null : $phone,
            $fullname === '' ? null : $fullname,
            $department === '' ? null : $department,
            $role,
            $technicianId,
            $status,
            $approved,
            $id,
        ];

        if ($password !== '') {
            $pwdErr = vk_users_validate_password($password);
            if ($pwdErr) {
                return ['ok' => false, 'error' => $pwdErr];
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare(
                'UPDATE users SET username=?, email=?, phone=?, password_hash=?, fullname=?, department=?, role=?, technician_id=?, status=?, approved=?, updated_at=NOW() WHERE id=?'
            )->execute([
                $username,
                $email === '' ? null : $email,
                $phone === '' ? null : $phone,
                $hash,
                $fullname === '' ? null : $fullname,
                $department === '' ? null : $department,
                $role,
                $technicianId,
                $status,
                $approved,
                $id,
            ]);
        } else {
            $pdo->prepare(
                'UPDATE users SET username=?, email=?, phone=?, fullname=?, department=?, role=?, technician_id=?, status=?, approved=?, updated_at=NOW() WHERE id=?'
            )->execute($params);
        }

        if ($id === $actorId) {
            $_SESSION['user_role'] = $role;
            $_SESSION['technician_id'] = $role === 'technician' ? $technicianId : null;
        }

        vk_auth_activity($pdo, $id, $actorId, 'user_updated', 'user', $id, [
            'from_role' => $old['role'] ?? null,
            'to_role' => $role,
            'from_status' => $old['status'] ?? null,
            'to_status' => $status,
        ]);
        vk_users_log('info', 'User updated', ['id' => $id, 'actor' => $actorId]);

        return ['ok' => true, 'id' => $id, 'message' => 'User updated successfully.'];
    } catch (Throwable $e) {
        vk_users_log('error', $e->getMessage(), ['action' => 'save', 'id' => $id]);
        return ['ok' => false, 'error' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Unable to save user.'];
    }
}

function vk_users_guard_last_admin(PDO $pdo, array $old, string $newRole, string $newStatus, int $id): void
{
    $wasAdmin = in_array((string) ($old['role'] ?? ''), ['admin', 'super_admin'], true)
        && vk_auth_status_is_approved((string) ($old['status'] ?? ''));
    $willBeAdmin = in_array($newRole, ['admin', 'super_admin'], true) && vk_auth_status_is_approved($newStatus);
    if ($wasAdmin && !$willBeAdmin) {
        $cnt = $pdo->prepare(
            "SELECT COUNT(*) FROM users WHERE role IN ('admin','super_admin') AND status IN ('approved','active') AND id != ?"
        );
        $cnt->execute([$id]);
        if ((int) $cnt->fetchColumn() === 0) {
            throw new RuntimeException('Cannot remove the last active administrator.');
        }
    }
}

/** @return array{ok:bool,error?:string,message?:string} */
function vk_users_soft_delete(PDO $pdo, int $id, int $actorId, array $perms): array
{
    if (!$perms['can_manage']) {
        return ['ok' => false, 'error' => 'Permission denied.'];
    }
    if ($id === $actorId) {
        return ['ok' => false, 'error' => 'You cannot delete your own account.'];
    }
    $user = vk_users_get($pdo, $id);
    if (!$user) {
        return ['ok' => false, 'error' => 'User not found.'];
    }
    if (($user['role'] ?? '') === 'super_admin') {
        return ['ok' => false, 'error' => 'Super Admin accounts cannot be deleted.'];
    }
    if (!vk_users_can_modify_target($perms, $user, $actorId)) {
        return ['ok' => false, 'error' => 'Permission denied.'];
    }

    try {
        vk_users_guard_last_admin($pdo, $user, 'staff', 'inactive', $id);
        vk_auth_update_user_status($pdo, $id, 'inactive', $actorId, 'Soft deleted from user management');
        vk_auth_activity($pdo, $id, $actorId, 'user_deleted', 'user', $id);
        return ['ok' => true, 'message' => 'User deactivated successfully.'];
    } catch (Throwable $e) {
        vk_users_log('error', $e->getMessage(), ['action' => 'delete', 'id' => $id]);
        return ['ok' => false, 'error' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Unable to delete user.'];
    }
}

/** @return array{ok:bool,error?:string,message?:string,results?:list<mixed>} */
function vk_users_bulk_action(PDO $pdo, string $action, array $userIds, int $actorId, array $perms, ?string $note = null): array
{
    if (!$perms['can_manage']) {
        return ['ok' => false, 'error' => 'Permission denied.'];
    }
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $v): bool => $v > 0)));
    if ($userIds === []) {
        return ['ok' => false, 'error' => 'No users selected.'];
    }

    $statusMap = [
        'bulk_approve' => 'approved',
        'bulk_activate' => 'approved',
        'bulk_deactivate' => 'inactive',
        'bulk_suspend' => 'suspended',
        'bulk_delete' => 'inactive',
    ];
    if (!isset($statusMap[$action]) && $action !== 'bulk_export') {
        return ['ok' => false, 'error' => 'Invalid bulk action.'];
    }

    $results = [];
    try {
        $pdo->beginTransaction();
        foreach ($userIds as $uid) {
            if ($uid === $actorId && in_array($action, ['bulk_deactivate', 'bulk_suspend', 'bulk_delete'], true)) {
                continue;
            }
            $user = vk_users_get($pdo, $uid);
            if (!$user || !vk_users_can_modify_target($perms, $user, $actorId)) {
                continue;
            }
            if (($user['role'] ?? '') === 'super_admin' && in_array($action, ['bulk_deactivate', 'bulk_suspend', 'bulk_delete'], true)) {
                continue;
            }

            if ($action === 'bulk_export') {
                $results[] = $user;
                continue;
            }

            $newStatus = $statusMap[$action];
            if ($action === 'bulk_approve' && vk_auth_status_is_approved((string) $user['status'])) {
                $results[] = ['id' => $uid, 'unchanged' => true];
                continue;
            }
            vk_auth_update_user_status($pdo, $uid, $newStatus, $actorId, $note ?: ('Bulk ' . $action));
            $results[] = ['id' => $uid, 'status' => $newStatus];
        }
        $pdo->commit();

        if ($action === 'bulk_export') {
            return ['ok' => true, 'message' => 'Export ready.', 'export' => $results];
        }

        return ['ok' => true, 'message' => count($results) . ' user(s) updated.', 'results' => $results];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        vk_users_log('error', $e->getMessage(), ['action' => $action]);
        return ['ok' => false, 'error' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Bulk action failed.'];
    }
}

/** @return array<string,mixed> */
function vk_users_row_json(array $u, int $actorId, array $perms): array
{
    $status = (string) ($u['status'] ?? '');
    return [
        'id' => (int) $u['id'],
        'user_uid' => (string) ($u['user_uid'] ?? ''),
        'username' => (string) ($u['username'] ?? ''),
        'email' => (string) ($u['email'] ?? ''),
        'phone' => (string) ($u['phone'] ?? ''),
        'fullname' => (string) ($u['fullname'] ?? ''),
        'department' => (string) ($u['department'] ?? ''),
        'role' => (string) ($u['role'] ?? ''),
        'role_label' => vk_auth_role_label((string) ($u['role'] ?? '')),
        'status' => $status,
        'status_label' => vk_auth_status_label($status),
        'technician_id' => $u['technician_id'] !== null ? (int) $u['technician_id'] : null,
        'technician_name' => (string) ($u['technician_name'] ?? ''),
        'created_at' => (string) ($u['created_at'] ?? ''),
        'last_login_at' => $u['last_login_at'] ? (string) $u['last_login_at'] : null,
        'approved_at' => $u['approved_at'] ? (string) $u['approved_at'] : null,
        'approved_by_name' => (string) ($u['approved_by_name'] ?? ''),
        'initials' => vk_users_initials($u),
        'is_self' => (int) $u['id'] === $actorId,
        'can_edit' => vk_users_can_modify_target($perms, $u, $actorId),
        'can_delete' => vk_users_can_modify_target($perms, $u, $actorId) && ($u['role'] ?? '') !== 'super_admin' && (int) $u['id'] !== $actorId,
        'can_approve' => $perms['can_manage'] && !vk_auth_status_is_approved($status),
        'can_reject' => $perms['can_manage'] && $status === 'pending',
    ];
}

/** @return array<string,int> */
function vk_users_stats(PDO $pdo, array $perms, int $selfUserId): array
{
    $filters = vk_users_parse_filters([]);
    return [
        'total' => vk_users_count($pdo, $filters, $perms, $selfUserId),
        'pending' => vk_users_count($pdo, array_merge($filters, ['status' => 'pending']), $perms, $selfUserId),
        'active' => vk_users_count($pdo, array_merge($filters, ['status' => 'approved']), $perms, $selfUserId),
    ];
}

function vk_users_build_query(array $filters, int $page = 1, int $perPage = 25): string
{
    $roleOut = $filters['role'];
    if ($roleOut === 'admin_group') {
        $roleOut = 'admin';
    } elseif ($roleOut === 'staff') {
        $roleOut = 'employee';
    }

    $params = array_filter([
        'q' => $filters['q'] !== '' ? $filters['q'] : null,
        'status' => $filters['status'] !== '' ? $filters['status'] : null,
        'role' => $roleOut !== '' ? $roleOut : null,
        'sort' => $filters['sort'] !== 'created_at' ? $filters['sort'] : null,
        'sort_dir' => $filters['sort_dir'] === 'ASC' ? 'asc' : null,
        'date_from' => $filters['date_from'] !== '' ? $filters['date_from'] : null,
        'date_to' => $filters['date_to'] !== '' ? $filters['date_to'] : null,
        'page' => $page > 1 ? (string) $page : null,
        'per_page' => $perPage !== 25 ? (string) $perPage : null,
    ], static fn($v) => $v !== null && $v !== '');

    $query = http_build_query($params);
    return $query !== '' ? '?' . $query : '';
}
