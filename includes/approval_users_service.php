<?php
declare(strict_types=1);

/**
 * User Approval Center — query builders, actions, logging.
 * Preserves vk_auth_* business logic; adds filtering, bulk ops, and audit metadata.
 */

function vk_approval_log(string $level, string $message, array $context = []): void
{
    $payload = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    error_log('[approval_users][' . $level . '] ' . $message . $payload);
}

/** @return array{q:string,status:string,role:string,sort:string,date_from:string,date_to:string} */
function vk_approval_parse_filters(array $input): array
{
    $status = strtolower(trim((string) ($input['status'] ?? '')));
    if (!in_array($status, ['', 'pending', 'approved', 'rejected', 'suspended', 'inactive'], true)) {
        $status = '';
    }

    $role = strtolower(trim((string) ($input['role'] ?? '')));
    $roleMap = ['admin' => 'admin_group', 'manager' => 'manager', 'employee' => 'staff'];
    if (isset($roleMap[$role])) {
        $role = $roleMap[$role];
    }
    if (!in_array($role, ['', 'admin_group', 'manager', 'staff', 'technician', 'viewer', 'super_admin', 'admin'], true)) {
        $role = '';
    }

    $sort = strtolower(trim((string) ($input['sort'] ?? 'pending_first')));
    if (!in_array($sort, ['pending_first', 'newest', 'oldest', 'name_asc', 'name_desc'], true)) {
        $sort = 'pending_first';
    }

    return [
        'q' => trim((string) ($input['q'] ?? $input['search'] ?? '')),
        'status' => $status,
        'role' => $role,
        'sort' => $sort,
        'date_from' => trim((string) ($input['date_from'] ?? '')),
        'date_to' => trim((string) ($input['date_to'] ?? '')),
    ];
}

/** @param array{q:string,status:string,role:string,sort:string,date_from:string,date_to:string} $filters */
function vk_approval_where_sql(array $filters): array
{
    $where = ['1=1'];
    $params = [];

    if ($filters['q'] !== '') {
        $term = '%' . $filters['q'] . '%';
        $where[] = '(u.fullname LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.user_uid LIKE ? OR u.department LIKE ?)';
        array_push($params, $term, $term, $term, $term, $term, $term);
    }

    if ($filters['status'] === 'approved') {
        $where[] = "u.status IN ('approved','active')";
    } elseif ($filters['status'] !== '') {
        $where[] = 'u.status = ?';
        $params[] = $filters['status'];
    }

    if ($filters['role'] === 'admin_group') {
        $where[] = "u.role IN ('super_admin','admin')";
    } elseif ($filters['role'] !== '') {
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

function vk_approval_order_sql(string $sort): string
{
    return match ($sort) {
        'newest' => 'u.id DESC',
        'oldest' => 'u.id ASC',
        'name_asc' => 'u.fullname ASC, u.id DESC',
        'name_desc' => 'u.fullname DESC, u.id DESC',
        default => "FIELD(u.status, 'pending', 'approved', 'active', 'rejected', 'suspended', 'inactive'), u.id DESC",
    };
}

/** @return array<string, int> */
function vk_approval_stats(PDO $pdo): array
{
    vk_auth_ensure_schema($pdo);
    $stats = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'suspended' => 0,
        'inactive' => 0,
        'total' => 0,
    ];
    foreach ($pdo->query('SELECT status, COUNT(*) AS c FROM users GROUP BY status') as $row) {
        $status = (string) $row['status'];
        $count = (int) $row['c'];
        if (vk_auth_status_is_approved($status)) {
            $stats['approved'] += $count;
        } elseif (isset($stats[$status])) {
            $stats[$status] += $count;
        }
        $stats['total'] += $count;
    }
    return $stats;
}

/** @param array{q:string,status:string,role:string,sort:string,date_from:string,date_to:string} $filters */
function vk_approval_count(PDO $pdo, array $filters): int
{
    [$where, $params] = vk_approval_where_sql($filters);
    $st = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
    $st->execute($params);
    return (int) $st->fetchColumn();
}

/** @param array{q:string,status:string,role:string,sort:string,date_from:string,date_to:string} $filters
 *  @return list<array<string, mixed>>
 */
function vk_approval_fetch(PDO $pdo, array $filters, int $page, int $perPage): array
{
    [$where, $params] = vk_approval_where_sql($filters);
    $order = vk_approval_order_sql($filters['sort']);
    $offset = max(0, ($page - 1) * $perPage);
    $sql = "SELECT u.id, u.user_uid, u.username, u.email, u.phone, u.fullname, u.department, u.role, u.status,
                   u.approved, u.created_at, u.approved_at, u.rejected_at, u.last_login_at, u.approved_by,
                   u.registration_ip,
                   ab.fullname AS approved_by_name
            FROM users u
            LEFT JOIN users ab ON ab.id = u.approved_by
            WHERE {$where}
            ORDER BY {$order}
            LIMIT ? OFFSET ?";
    $st = $pdo->prepare($sql);
    $i = 1;
    foreach ($params as $param) {
        $st->bindValue($i++, $param);
    }
    $st->bindValue($i++, $perPage, PDO::PARAM_INT);
    $st->bindValue($i, $offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function vk_approval_get_user(PDO $pdo, int $userId): ?array
{
    $st = $pdo->prepare(
        "SELECT u.id, u.user_uid, u.username, u.email, u.phone, u.fullname, u.department, u.role, u.status,
                u.approved, u.created_at, u.approved_at, u.rejected_at, u.last_login_at, u.approved_by,
                u.registration_ip,
                ab.fullname AS approved_by_name
         FROM users u
         LEFT JOIN users ab ON ab.id = u.approved_by
         WHERE u.id = ?
         LIMIT 1"
    );
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }

    $noteSt = $pdo->prepare(
        "SELECT note, action, created_at FROM approvals WHERE user_id = ? ORDER BY id DESC LIMIT 5"
    );
    $noteSt->execute([$userId]);
    $user['recent_approvals'] = $noteSt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $user['verification_status'] = vk_auth_status_is_approved((string) $user['status']) && (int) ($user['approved'] ?? 0) === 1
        ? 'Verified'
        : ((string) $user['status'] === 'pending' ? 'Pending verification' : 'Not verified');
    $user['created_by'] = 'Self-registration';
    $user['status_label'] = vk_auth_status_label((string) $user['status']);
    $user['role_label'] = vk_auth_role_label((string) $user['role']);

    return $user;
}

/** @return array{ok:bool,message?:string,error?:string,unchanged?:bool,reset_password?:string,user?:array<string,mixed>} */
function vk_approval_process_action(PDO $pdo, string $action, int $actorId, array $payload): array
{
    vk_auth_ensure_schema($pdo);

    $userIds = [];
    if (!empty($payload['user_ids']) && is_array($payload['user_ids'])) {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $payload['user_ids']), static fn(int $id): bool => $id > 0)));
    }
    $userId = (int) ($payload['user_id'] ?? 0);
    if ($userId > 0) {
        $userIds[] = $userId;
    }
    $userIds = array_values(array_unique($userIds));

    if ($userIds === []) {
        return ['ok' => false, 'error' => 'Select a valid user.'];
    }

    $note = trim((string) ($payload['note'] ?? $payload['rejection_reason'] ?? ''));
    $role = (string) ($payload['role'] ?? '');

    try {
        if (count($userIds) > 1 && !in_array($action, ['bulk_approve', 'bulk_reject', 'bulk_delete'], true)) {
            return ['ok' => false, 'error' => 'Invalid bulk action.'];
        }

        $mapAction = match ($action) {
            'bulk_approve' => 'approve',
            'bulk_reject' => 'reject',
            'bulk_delete' => 'soft_delete',
            default => $action,
        };

        $results = [];
        $pdo->beginTransaction();

        foreach ($userIds as $targetId) {
            if ($targetId === $actorId && in_array($mapAction, ['reject', 'suspend', 'soft_delete'], true)) {
                throw new RuntimeException('You cannot disable your own active session.');
            }

            $lock = $pdo->prepare('SELECT id, status, role, username FROM users WHERE id = ? FOR UPDATE');
            $lock->execute([$targetId]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                throw new RuntimeException('User #' . $targetId . ' not found.');
            }
            $currentStatus = (string) $current['status'];

            if ($mapAction === 'approve') {
                if (vk_auth_status_is_approved($currentStatus)) {
                    $results[] = ['id' => $targetId, 'unchanged' => true];
                    continue;
                }
                vk_auth_update_user_status($pdo, $targetId, 'approved', $actorId, $note !== '' ? $note : 'Approved from enterprise console');
                $results[] = ['id' => $targetId, 'status' => 'approved'];
            } elseif ($mapAction === 'reject') {
                if ($currentStatus === 'rejected') {
                    $results[] = ['id' => $targetId, 'unchanged' => true];
                    continue;
                }
                vk_auth_update_user_status($pdo, $targetId, 'rejected', $actorId, $note !== '' ? $note : 'Rejected from enterprise console');
                $results[] = ['id' => $targetId, 'status' => 'rejected'];
            } elseif ($mapAction === 'suspend') {
                vk_auth_update_user_status($pdo, $targetId, 'suspended', $actorId, $note !== '' ? $note : 'Suspended from enterprise console');
                $results[] = ['id' => $targetId, 'status' => 'suspended'];
            } elseif ($mapAction === 'reactivate') {
                vk_auth_update_user_status($pdo, $targetId, 'approved', $actorId, $note !== '' ? $note : 'Reactivated from enterprise console');
                $results[] = ['id' => $targetId, 'status' => 'approved'];
            } elseif ($mapAction === 'soft_delete') {
                if ($currentStatus === 'inactive') {
                    $results[] = ['id' => $targetId, 'unchanged' => true];
                    continue;
                }
                vk_auth_update_user_status($pdo, $targetId, 'inactive', $actorId, $note !== '' ? $note : 'Soft deleted from enterprise console');
                $results[] = ['id' => $targetId, 'status' => 'inactive'];
            } elseif ($mapAction === 'role') {
                if ($role === '') {
                    throw new InvalidArgumentException('Role is required.');
                }
                vk_auth_change_role($pdo, $targetId, $role, $actorId);
                $results[] = ['id' => $targetId, 'role' => $role];
            } elseif ($mapAction === 'reset_password') {
                $password = vk_auth_admin_reset_password($pdo, $targetId, $actorId);
                $pdo->commit();
                return ['ok' => true, 'message' => 'Password reset generated.', 'reset_password' => $password, 'user_id' => $targetId];
            } else {
                throw new InvalidArgumentException('Unknown action.');
            }
        }

        $pdo->commit();

        $message = match ($mapAction) {
            'approve' => 'User approved successfully.',
            'reject' => 'User rejected successfully.',
            'suspend' => 'User suspended.',
            'reactivate' => 'User reactivated.',
            'soft_delete' => 'User deactivated (soft delete).',
            'role' => 'Role updated.',
            default => 'Action completed.',
        };
        if (count($userIds) > 1) {
            $message = match ($mapAction) {
                'approve' => count($results) . ' user(s) approved.',
                'reject' => count($results) . ' user(s) rejected.',
                'soft_delete' => count($results) . ' user(s) deactivated.',
                default => 'Bulk action completed.',
            };
        }

        return ['ok' => true, 'message' => $message, 'results' => $results];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        vk_approval_log('error', $e->getMessage(), ['action' => $action, 'actor' => $actorId, 'users' => $userIds]);
        return ['ok' => false, 'error' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Unable to update user.'];
    }
}

function vk_approval_audit(
    PDO $pdo,
    int $actorId,
    int $targetUserId,
    string $action,
    string $fromStatus,
    string $toStatus,
    ?string $note = null
): void {
    vk_auth_activity($pdo, $targetUserId, $actorId, 'approval_' . $action, 'user', $targetUserId, [
        'from_status' => $fromStatus,
        'to_status' => $toStatus,
        'note' => $note,
        'user_agent' => vk_auth_user_agent(),
        'ip' => vk_auth_client_ip(),
    ]);
}

/** @param array{q:string,status:string,role:string,sort:string,date_from:string,date_to:string} $filters */
function vk_approval_export_csv(PDO $pdo, array $filters): void
{
    [$where, $params] = vk_approval_where_sql($filters);
    $order = vk_approval_order_sql($filters['sort']);
    $st = $pdo->prepare(
        "SELECT u.user_uid, u.fullname, u.username, u.email, u.phone, u.department, u.role, u.status, u.created_at, u.last_login_at
         FROM users u WHERE {$where} ORDER BY {$order}"
    );
    $st->execute($params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vk-network-users-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['User ID', 'Name', 'Username', 'Email', 'Phone', 'Department', 'Role', 'Status', 'Created', 'Last Login']);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['user_uid'],
            $row['fullname'],
            $row['username'],
            $row['email'],
            $row['phone'],
            $row['department'],
            $row['role'],
            $row['status'],
            $row['created_at'],
            $row['last_login_at'],
        ]);
    }
    fclose($out);
}

/** @return list<array<string, mixed>> */
function vk_approval_login_logs(PDO $pdo, int $limit = 80): array
{
    vk_auth_ensure_schema($pdo);
    $st = $pdo->prepare(
        'SELECT l.*, u.fullname FROM login_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT ?'
    );
    $st->bindValue(1, max(1, min(200, $limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function vk_approval_user_row_json(array $u, int $currentUserId): array
{
    $status = (string) $u['status'];
    return [
        'id' => (int) $u['id'],
        'user_uid' => (string) ($u['user_uid'] ?? ''),
        'username' => (string) ($u['username'] ?? ''),
        'email' => (string) ($u['email'] ?? ''),
        'phone' => (string) ($u['phone'] ?? ''),
        'fullname' => (string) ($u['fullname'] ?? ''),
        'department' => (string) ($u['department'] ?? ''),
        'role' => (string) ($u['role'] ?? ''),
        'status' => $status,
        'status_label' => vk_auth_status_label($status),
        'role_label' => vk_auth_role_label((string) ($u['role'] ?? '')),
        'created_at' => (string) ($u['created_at'] ?? ''),
        'last_login_at' => $u['last_login_at'] ? (string) $u['last_login_at'] : null,
        'approved_at' => $u['approved_at'] ? (string) $u['approved_at'] : null,
        'is_self' => (int) $u['id'] === $currentUserId,
        'can_approve' => !vk_auth_status_is_approved($status),
        'can_reject' => $status === 'pending',
        'can_suspend' => vk_auth_status_is_approved($status) && (int) $u['id'] !== $currentUserId,
        'can_reactivate' => in_array($status, ['suspended', 'inactive', 'rejected'], true),
    ];
}

function vk_approval_build_query(array $filters, int $page = 1): string
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
        'sort' => $filters['sort'] !== 'pending_first' ? $filters['sort'] : null,
        'date_from' => $filters['date_from'] !== '' ? $filters['date_from'] : null,
        'date_to' => $filters['date_to'] !== '' ? $filters['date_to'] : null,
        'page' => $page > 1 ? (string) $page : null,
    ], static fn($v) => $v !== null && $v !== '');
    $query = http_build_query($params);
    return $query !== '' ? '?' . $query : '';
}
