<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout_init.php';
require_once __DIR__ . '/includes/approval_users_service.php';

if (!vk_auth_role_can_manage((string) ($_SESSION['user_role'] ?? 'viewer'))) {
    flash_set('error', 'Only administrators can manage approvals.');
    redirect('/dashboard.php');
}

$pdo = db();
vk_auth_ensure_schema($pdo);

if (($_GET['export'] ?? '') === 'users') {
    vk_approval_export_csv($pdo, vk_approval_parse_filters($_GET));
    exit;
}

$filters = vk_approval_parse_filters($_GET);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$totalRows = vk_approval_count($pdo, $filters);
$pages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $pages);
$users = vk_approval_fetch($pdo, $filters, $page, $perPage);
$stats = vk_approval_stats($pdo);
$loginLogs = vk_approval_login_logs($pdo, 80);
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$querySuffix = vk_approval_build_query($filters, $page);
$filterQueryBase = vk_approval_build_query($filters, 1);
$apiUrl = base_url('api/approval_users.php');
$csrf = csrf_token();

$pageTitle = 'User Approvals';
$cssV = (string) @filemtime(__DIR__ . '/assets/css/auth-enterprise.css');
$jsV = (string) @filemtime(__DIR__ . '/assets/js/approve-users.js');
$extraHead = '<link href="' . e(base_url('assets/css/auth-enterprise.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';
require_once __DIR__ . '/includes/layout_start.php';
?>
<div class="vk-enterprise-page" id="approvalUsersApp"
     data-api-url="<?= e($apiUrl) ?>"
     data-csrf="<?= e($csrf) ?>"
     data-current-user="<?= (int) $currentUserId ?>"
     data-roles="<?= e(json_encode(array_map(static fn(string $r): array => ['key' => $r, 'label' => vk_auth_role_label($r)], VK_AUTH_ROLES), JSON_THROW_ON_ERROR)) ?>">
    <section class="vk-enterprise-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-info fw-semibold small text-uppercase">Enterprise identity control</div>
                <h1 class="h2 mb-2">User Approval Center</h1>
                <p class="text-muted mb-0">Approve access, manage roles, reset credentials, and audit login activity across VK Network.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= e(BASE_URL) ?>/approve_users.php?export=users<?= e(substr($filterQueryBase, 1) ? '&' . substr($filterQueryBase, 1) : '') ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="approvalPrintBtn"><i class="bi bi-printer me-1"></i>Print</button>
            </div>
        </div>
        <div class="row g-3 mt-2" id="approvalStatsRow">
            <div class="col-6 col-lg-2"><div class="vk-enterprise-stat"><div class="text-muted small">Pending</div><div class="h3 mb-0" data-stat="pending"><?= (int) $stats['pending'] ?></div></div></div>
            <div class="col-6 col-lg-2"><div class="vk-enterprise-stat"><div class="text-muted small">Approved</div><div class="h3 mb-0" data-stat="approved"><?= (int) $stats['approved'] ?></div></div></div>
            <div class="col-6 col-lg-2"><div class="vk-enterprise-stat"><div class="text-muted small">Rejected</div><div class="h3 mb-0" data-stat="rejected"><?= (int) $stats['rejected'] ?></div></div></div>
            <div class="col-6 col-lg-2"><div class="vk-enterprise-stat"><div class="text-muted small">Suspended</div><div class="h3 mb-0" data-stat="suspended"><?= (int) $stats['suspended'] ?></div></div></div>
            <div class="col-6 col-lg-2"><div class="vk-enterprise-stat"><div class="text-muted small">Inactive</div><div class="h3 mb-0" data-stat="inactive"><?= (int) $stats['inactive'] ?></div></div></div>
            <div class="col-6 col-lg-2"><div class="vk-enterprise-stat"><div class="text-muted small">Total</div><div class="h3 mb-0" data-stat="total"><?= (int) $stats['total'] ?></div></div></div>
        </div>
    </section>

    <div class="card vk-card mb-4">
        <div class="card-body">
            <form method="get" action="<?= e(BASE_URL) ?>/approve_users.php" class="row g-2 g-lg-3 align-items-end" id="approvalFilterForm">
                <div class="col-12 col-lg-3">
                    <label class="form-label small mb-1" for="approvalSearch">Search</label>
                    <input type="search" class="form-control" id="approvalSearch" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, email, phone, role…" autocomplete="off">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1" for="filterStatus">Status</label>
                    <select class="form-select" id="filterStatus" name="status">
                        <option value="">All statuses</option>
                        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'inactive' => 'Inactive'] as $val => $label): ?>
                        <option value="<?= e($val) ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1" for="filterRole">Role</label>
                    <select class="form-select" id="filterRole" name="role">
                        <option value="">All roles</option>
                        <option value="admin" <?= in_array($filters['role'], ['admin_group', 'admin', 'super_admin'], true) ? 'selected' : '' ?>>Admin</option>
                        <option value="manager" <?= $filters['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="employee" <?= $filters['role'] === 'staff' ? 'selected' : '' ?>>Employee</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small mb-1" for="filterSort">Sort</label>
                    <select class="form-select" id="filterSort" name="sort">
                        <option value="pending_first" <?= $filters['sort'] === 'pending_first' ? 'selected' : '' ?>>Pending first</option>
                        <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="name_asc" <?= $filters['sort'] === 'name_asc' ? 'selected' : '' ?>>Name A–Z</option>
                        <option value="name_desc" <?= $filters['sort'] === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
                    </select>
                </div>
                <div class="col-6 col-lg-1">
                    <label class="form-label small mb-1" for="filterDateFrom">From</label>
                    <input type="date" class="form-control" id="filterDateFrom" name="date_from" value="<?= e($filters['date_from']) ?>">
                </div>
                <div class="col-6 col-lg-1">
                    <label class="form-label small mb-1" for="filterDateTo">To</label>
                    <input type="date" class="form-control" id="filterDateTo" name="date_to" value="<?= e($filters['date_to']) ?>">
                </div>
                <div class="col-12 col-lg-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card vk-card mb-4">
        <div class="card-body vk-table-tools border-bottom">
            <div>
                <h2 class="h5 mb-1">Registrations &amp; User Access</h2>
                <div class="text-muted small">Pending users cannot access dashboards until approved. <span id="approvalResultCount"><?= (int) $totalRows ?></span> user(s) match filters.</div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center" id="approvalBulkBar">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="approvalSelectAll">
                    <label class="form-check-label small" for="approvalSelectAll">Select all</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-success" data-bulk="bulk_approve" disabled>Bulk Approve</button>
                <button type="button" class="btn btn-sm btn-outline-warning" data-bulk="bulk_reject" disabled>Bulk Reject</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bulk="bulk_delete" disabled>Bulk Deactivate</button>
            </div>
        </div>
        <div class="position-relative">
            <div class="approval-loading-overlay d-none" id="approvalLoadingOverlay" aria-hidden="true">
                <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 vk-approval-table" id="approvalTable">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width:2.5rem"><span class="visually-hidden">Select</span></th>
                            <th data-sort="name">User</th>
                            <th class="d-none d-md-table-cell">Contact</th>
                            <th class="d-none d-lg-table-cell">Role</th>
                            <th>Status</th>
                            <th class="d-none d-xl-table-cell">Registered</th>
                            <th class="d-none d-xl-table-cell">Last Login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="approvalTableBody">
                    <?php if ($users === []): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No users match your filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <?php
                            $status = (string) $u['status'];
                            $role = (string) $u['role'];
                            $uid = (int) $u['id'];
                            $isSelf = $uid === $currentUserId;
                        ?>
                        <tr data-user-id="<?= $uid ?>" class="<?= $status === 'pending' ? 'table-warning-subtle' : '' ?>">
                            <td><input type="checkbox" class="form-check-input approval-row-check" value="<?= $uid ?>" aria-label="Select user"></td>
                            <td>
                                <div class="fw-semibold"><?= e((string) ($u['fullname'] ?: 'Unnamed User')) ?></div>
                                <div class="small text-muted"><code><?= e((string) $u['username']) ?></code> · <?= e((string) ($u['user_uid'] ?: 'No ID')) ?></div>
                                <div class="small text-muted d-md-none"><?= e((string) ($u['email'] ?: '-')) ?></div>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <div><?= e((string) ($u['email'] ?: '-')) ?></div>
                                <div class="small text-muted"><?= e((string) ($u['phone'] ?: '-')) ?></div>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <select class="form-select form-select-sm approval-role-select" data-user-id="<?= $uid ?>" aria-label="Role">
                                    <?php foreach (VK_AUTH_ROLES as $r): ?>
                                    <option value="<?= e($r) ?>" <?= $r === $role ? 'selected' : '' ?>><?= e(vk_auth_role_label($r)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><span class="vk-status-badge vk-status-<?= e($status) ?>" data-status="<?= e($status) ?>"><?= e(vk_auth_status_label($status)) ?></span></td>
                            <td class="d-none d-xl-table-cell"><?= e(substr((string) $u['created_at'], 0, 16)) ?></td>
                            <td class="d-none d-xl-table-cell"><?= $u['last_login_at'] ? e(substr((string) $u['last_login_at'], 0, 16)) : '<span class="text-muted">Never</span>' ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary approval-view-btn" data-user-id="<?= $uid ?>" title="View details"><i class="bi bi-eye"></i></button>
                                    <?php if (!vk_auth_status_is_approved($status)): ?>
                                    <button type="button" class="btn btn-outline-success approval-action-btn" data-action="approve" data-user-id="<?= $uid ?>">Approve</button>
                                    <?php endif; ?>
                                    <?php if ($status === 'pending'): ?>
                                    <button type="button" class="btn btn-outline-warning approval-action-btn" data-action="reject" data-user-id="<?= $uid ?>">Reject</button>
                                    <?php endif; ?>
                                    <?php if (vk_auth_status_is_approved($status) && !$isSelf): ?>
                                    <button type="button" class="btn btn-outline-danger approval-action-btn" data-action="suspend" data-user-id="<?= $uid ?>">Suspend</button>
                                    <?php endif; ?>
                                    <?php if (in_array($status, ['suspended', 'inactive', 'rejected'], true)): ?>
                                    <button type="button" class="btn btn-outline-primary approval-action-btn" data-action="reactivate" data-user-id="<?= $uid ?>">Reactivate</button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline-info approval-action-btn" data-action="reset_password" data-user-id="<?= $uid ?>">Reset</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pages > 1): ?>
        <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted">Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= (int) $totalRows ?> users</span>
            <nav aria-label="User pagination">
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(BASE_URL) ?>/approve_users.php<?= e(vk_approval_build_query($filters, $i)) ?>"><?= $i ?></a>
                    </li>
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
                <thead class="table-light sticky-top"><tr><th>When</th><th>User</th><th>Status</th><th>Reason</th><th>IP</th></tr></thead>
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

<div class="modal fade" id="approvalUserModal" tabindex="-1" aria-labelledby="approvalUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="approvalUserModalLabel">User Details</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="approvalUserModalBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="approvalRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">Confirm Rejection</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Reject this registration? The user will not be able to sign in.</p>
                <label class="form-label" for="rejectionReason">Reason (optional)</label>
                <textarea class="form-control" id="rejectionReason" rows="3" maxlength="255"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="approvalRejectConfirm">Reject</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="approvalConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="approvalConfirmTitle">Confirm</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="approvalConfirmBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="approvalConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>
<?php
$extraScripts = '<script src="' . e(base_url('assets/js/auth-enterprise.js')) . '?v=' . e($cssV) . '" defer></script>'
    . '<script src="' . e(base_url('assets/js/approve-users.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once __DIR__ . '/includes/layout_end.php';
