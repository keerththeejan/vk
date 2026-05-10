<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/layout_init.php';
if (!vk_auth_role_can_manage((string) ($_SESSION['user_role'] ?? 'viewer'))) {
    flash_set('error', 'Only administrators can manage approvals.');
    redirect('/dashboard.php');
}

$pdo = db();
$actionMessage = null;
$resetPassword = null;

if (($_GET['export'] ?? '') === 'users') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vk-network-users-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['User ID', 'Name', 'Username', 'Email', 'Phone', 'Department', 'Role', 'Status', 'Created', 'Last Login']);
    $rows = $pdo->query('SELECT user_uid, fullname, username, email, phone, department, role, status, created_at, last_login_at FROM users ORDER BY id DESC');
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify((string) ($_POST['csrf_token'] ?? ''))) {
        $actionMessage = ['type' => 'danger', 'text' => 'Security token expired. Refresh and try again.'];
    } else {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($userId <= 0) {
                throw new InvalidArgumentException('Select a valid user.');
            }
            if ($userId === (int) ($_SESSION['user_id'] ?? 0) && in_array($action, ['reject', 'suspend'], true)) {
                throw new RuntimeException('You cannot disable your own active session.');
            }
            if ($action === 'approve') {
                vk_auth_update_user_status($pdo, $userId, 'approved', (int) $_SESSION['user_id'], 'Approved from enterprise console');
                $actionMessage = ['type' => 'success', 'text' => 'User approved successfully.'];
            } elseif ($action === 'reject') {
                vk_auth_update_user_status($pdo, $userId, 'rejected', (int) $_SESSION['user_id'], 'Rejected from enterprise console');
                $actionMessage = ['type' => 'warning', 'text' => 'Registration rejected.'];
            } elseif ($action === 'suspend') {
                vk_auth_update_user_status($pdo, $userId, 'suspended', (int) $_SESSION['user_id'], 'Suspended from enterprise console');
                $actionMessage = ['type' => 'warning', 'text' => 'User suspended.'];
            } elseif ($action === 'reactivate') {
                vk_auth_update_user_status($pdo, $userId, 'approved', (int) $_SESSION['user_id'], 'Reactivated from enterprise console');
                $actionMessage = ['type' => 'success', 'text' => 'User reactivated.'];
            } elseif ($action === 'role') {
                vk_auth_change_role($pdo, $userId, (string) ($_POST['role'] ?? 'viewer'), (int) $_SESSION['user_id']);
                $actionMessage = ['type' => 'success', 'text' => 'Role updated.'];
            } elseif ($action === 'reset_password') {
                $resetPassword = vk_auth_admin_reset_password($pdo, $userId, (int) $_SESSION['user_id']);
                $actionMessage = ['type' => 'success', 'text' => 'Password reset generated.'];
            }
        } catch (Throwable $e) {
            $actionMessage = ['type' => 'danger', 'text' => APP_DEBUG ? $e->getMessage() : 'Unable to update user.'];
        }
    }
}

$stats = [
    'pending' => 0,
    'approved' => 0,
    'suspended' => 0,
    'total' => 0,
];
foreach ($pdo->query('SELECT status, COUNT(*) c FROM users GROUP BY status') as $row) {
    $key = vk_auth_status_is_approved((string) $row['status']) ? 'approved' : (string) $row['status'];
    $stats[$key] = ($stats[$key] ?? 0) + (int) $row['c'];
    $stats['total'] += (int) $row['c'];
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$totalRows = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$pages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$stUsers = $pdo->prepare(
    'SELECT id, user_uid, username, email, phone, fullname, department, role, status, approved, created_at, approved_at, last_login_at
     FROM users ORDER BY FIELD(status, "pending", "approved", "active", "suspended", "rejected", "inactive"), id DESC
     LIMIT ? OFFSET ?'
);
$stUsers->bindValue(1, $perPage, PDO::PARAM_INT);
$stUsers->bindValue(2, $offset, PDO::PARAM_INT);
$stUsers->execute();
$users = $stUsers->fetchAll();
$loginLogs = $pdo->query(
    'SELECT l.*, u.fullname FROM login_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 80'
)->fetchAll();

$pageTitle = 'User Approvals';
$extraHead = '<link href="' . e(base_url('assets/css/auth-enterprise.css')) . '?v=' . e((string) filemtime(__DIR__ . '/assets/css/auth-enterprise.css')) . '" rel="stylesheet">';
require_once __DIR__ . '/includes/layout_start.php';
?>
<div class="vk-enterprise-page">
    <section class="vk-enterprise-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-info fw-semibold small text-uppercase">Enterprise identity control</div>
                <h1 class="h2 mb-2">User Approval Center</h1>
                <p class="text-muted mb-0">Approve access, manage roles, reset credentials, and audit login activity across VK Network.</p>
            </div>
            <a href="<?= e(BASE_URL) ?>/approve_users.php?export=users" class="btn btn-outline-info"><i class="bi bi-download me-1"></i>Export user list</a>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-6 col-lg-3"><div class="vk-enterprise-stat"><div class="text-muted small">Pending</div><div class="h3 mb-0"><?= (int) $stats['pending'] ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="vk-enterprise-stat"><div class="text-muted small">Approved</div><div class="h3 mb-0"><?= (int) $stats['approved'] ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="vk-enterprise-stat"><div class="text-muted small">Suspended</div><div class="h3 mb-0"><?= (int) $stats['suspended'] ?></div></div></div>
            <div class="col-6 col-lg-3"><div class="vk-enterprise-stat"><div class="text-muted small">Total Users</div><div class="h3 mb-0"><?= (int) $stats['total'] ?></div></div></div>
        </div>
    </section>

    <?php if ($actionMessage): ?>
        <div class="alert alert-<?= e($actionMessage['type']) ?>"><?= e($actionMessage['text']) ?></div>
    <?php endif; ?>

    <?php if ($resetPassword): ?>
        <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div><strong>New temporary password:</strong> <code id="resetPasswordValue"><?= e($resetPassword) ?></code></div>
            <button class="btn btn-sm btn-outline-success" type="button" data-vk-copy="#resetPasswordValue"><i class="bi bi-copy me-1"></i>Copy</button>
        </div>
    <?php endif; ?>

    <div class="card vk-card mb-4">
        <div class="card-body vk-table-tools">
            <div>
                <h2 class="h5 mb-1">Registrations &amp; User Access</h2>
                <div class="text-muted small">Pending users cannot access dashboards or modules until approved.</div>
            </div>
            <div style="min-width:min(100%, 320px);">
                <input type="search" class="form-control" placeholder="Search name, email, role, status..." data-vk-filter="#approvalTable">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="approvalTable">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Contact</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Last Login</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $status = (string) $u['status']; $role = (string) $u['role']; ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e((string) ($u['fullname'] ?: 'Unnamed User')) ?></div>
                            <div class="small text-muted"><code><?= e((string) $u['username']) ?></code> · <?= e((string) ($u['user_uid'] ?: 'No ID')) ?></div>
                            <div class="small text-muted"><?= e((string) ($u['department'] ?: 'No department')) ?></div>
                        </td>
                        <td>
                            <div><?= e((string) ($u['email'] ?: '-')) ?></div>
                            <div class="small text-muted"><?= e((string) ($u['phone'] ?: '-')) ?></div>
                        </td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <input type="hidden" name="action" value="role">
                                <select class="form-select form-select-sm" name="role" aria-label="Change role for <?= e((string) $u['username']) ?>">
                                    <?php foreach (VK_AUTH_ROLES as $r): ?>
                                        <option value="<?= e($r) ?>" <?= $r === $role ? 'selected' : '' ?>><?= e(vk_auth_role_label($r)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                            </form>
                        </td>
                        <td><span class="vk-status-badge vk-status-<?= e($status) ?>"><?= e(vk_auth_status_label($status)) ?></span></td>
                        <td><?= e(substr((string) $u['created_at'], 0, 16)) ?></td>
                        <td><?= $u['last_login_at'] ? e(substr((string) $u['last_login_at'], 0, 16)) : '<span class="text-muted">Never</span>' ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <?php if (!vk_auth_status_is_approved($status)): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-outline-success" type="submit">Approve</button></form>
                                <?php endif; ?>
                                <?php if ($status === 'pending'): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-outline-warning" type="submit">Reject</button></form>
                                <?php endif; ?>
                                <?php if (vk_auth_status_is_approved($status) && (int) $u['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="suspend"><button class="btn btn-outline-danger" type="submit">Suspend</button></form>
                                <?php endif; ?>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="action" value="reset_password"><button class="btn btn-outline-info" type="submit">Reset</button></form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center">
                <span class="small text-muted">Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= (int) $totalRows ?> users</span>
                <nav aria-label="User pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(BASE_URL) ?>/approve_users.php?page=<?= $i ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <div class="card vk-card">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Login Activity Logs</h2>
            <span class="badge text-bg-dark">Latest 80</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light"><tr><th>When</th><th>User</th><th>Status</th><th>Reason</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($loginLogs as $log): ?>
                    <tr>
                        <td><?= e(substr((string) $log['created_at'], 0, 16)) ?></td>
                        <td><?= e((string) ($log['fullname'] ?: $log['username'] ?: '-')) ?></td>
                        <td><span class="badge text-bg-<?= $log['status'] === 'success' ? 'success' : ($log['status'] === 'blocked' ? 'danger' : 'secondary') ?>"><?= e((string) $log['status']) ?></span></td>
                        <td><?= e((string) ($log['failure_reason'] ?: '-')) ?></td>
                        <td><code><?= e((string) $log['ip_address']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
$extraScripts = '<script src="' . e(base_url('assets/js/auth-enterprise.js')) . '?v=' . e((string) filemtime(__DIR__ . '/assets/js/auth-enterprise.js')) . '" defer></script>';
require_once __DIR__ . '/includes/layout_end.php';
?>
