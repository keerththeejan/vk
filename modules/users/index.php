<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/users_management_service.php';

$perms = vk_users_require_module($pdo);
$actorId = (int) ($_SESSION['user_id'] ?? 0);

$filters = vk_users_parse_filters($_GET);
$perPageRaw = (string) ($_GET['per_page'] ?? $_COOKIE['vk_users_per_page'] ?? '25');
$perPage = $perPageRaw === 'all' ? 0 : max(10, min(500, (int) $perPageRaw));
$page = max(1, (int) ($_GET['page'] ?? 1));
$totalRows = vk_users_count($pdo, $filters, $perms, $actorId);
$pages = $perPage > 0 ? max(1, (int) ceil($totalRows / $perPage)) : 1;
$page = min($page, $pages);
$users = vk_users_fetch($pdo, $filters, $perms, $actorId, $page, $perPage ?: max(1, $totalRows));
$stats = vk_users_stats($pdo, $perms, $actorId);

$techRows = $pdo->query('SELECT id, name FROM technicians WHERE active = 1 ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
$deptRows = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department <> '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

$apiUrl = base_url('api/users_management.php');
$csrf = csrf_token();
$pageTitle = 'User Management';

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/users-management.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/users-admin.js');
$extraHead = '<link href="' . e(base_url('assets/css/users-management.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$roleOptions = array_map(static fn(string $r): array => ['key' => $r, 'label' => vk_auth_role_label($r)], VK_AUTH_ROLES);
$statusOptions = [
    'pending' => 'Pending', 'approved' => 'Approved', 'active' => 'Active',
    'inactive' => 'Inactive', 'suspended' => 'Suspended', 'rejected' => 'Rejected',
];
?>
<div class="um-root" id="usersManagementApp"
     data-api-url="<?= e($apiUrl) ?>"
     data-csrf="<?= e($csrf) ?>"
     data-can-manage="<?= $perms['can_manage'] ? '1' : '0' ?>"
     data-roles="<?= e(json_encode($roleOptions, JSON_THROW_ON_ERROR)) ?>"
     data-per-page="<?= e((string) ($perPage ?: $totalRows)) ?>">

    <section class="um-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-primary fw-semibold small text-uppercase">Enterprise directory</div>
                <h1 class="h2 mb-1">User Management</h1>
                <p class="text-muted mb-0">
                    <?php if ($perms['can_manage']): ?>
                        Manage accounts, roles, approvals, and access across VK Network.
                    <?php elseif ($perms['can_view_self_only']): ?>
                        View and manage your profile information.
                    <?php else: ?>
                        View users in your department (read-only).
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($perms['can_manage']): ?>
            <button type="button" class="btn btn-primary" id="umAddUserBtn">
                <i class="bi bi-person-plus me-1"></i>Add User
            </button>
            <?php endif; ?>
        </div>
        <div class="row g-2 g-md-3 mt-2">
            <div class="col-4 col-md"><div class="um-stat"><div class="small text-muted">Total</div><div class="h4 mb-0" data-stat="total"><?= (int) $stats['total'] ?></div></div></div>
            <div class="col-4 col-md"><div class="um-stat"><div class="small text-muted">Pending</div><div class="h4 mb-0" data-stat="pending"><?= (int) $stats['pending'] ?></div></div></div>
            <div class="col-4 col-md"><div class="um-stat"><div class="small text-muted">Active</div><div class="h4 mb-0" data-stat="active"><?= (int) $stats['active'] ?></div></div></div>
        </div>
    </section>

    <div class="card vk-card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end" id="umFilterForm">
                <div class="col-12 col-lg-3">
                    <label class="form-label small" for="umSearch">Search</label>
                    <input type="search" class="form-control" id="umSearch" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, email, phone, department…" autocomplete="off">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small" for="umFilterRole">Role</label>
                    <select class="form-select" id="umFilterRole" name="role">
                        <option value="">All roles</option>
                        <option value="admin" <?= in_array($filters['role'], ['admin_group','admin','super_admin'], true) ? 'selected' : '' ?>>Admin</option>
                        <option value="manager" <?= $filters['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                        <option value="employee" <?= $filters['role'] === 'staff' ? 'selected' : '' ?>>Employee</option>
                        <option value="technician" <?= $filters['role'] === 'technician' ? 'selected' : '' ?>>Technician</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small" for="umFilterStatus">Status</label>
                    <select class="form-select" id="umFilterStatus" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($statusOptions as $val => $label): ?>
                        <option value="<?= e($val) ?>" <?= $filters['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small" for="umFilterFrom">From</label>
                    <input type="date" class="form-control" id="umFilterFrom" name="date_from" value="<?= e($filters['date_from']) ?>">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label small" for="umFilterTo">To</label>
                    <input type="date" class="form-control" id="umFilterTo" name="date_to" value="<?= e($filters['date_to']) ?>">
                </div>
                <div class="col-6 col-lg-1">
                    <label class="form-label small" for="umPerPage">Show</label>
                    <select class="form-select" id="umPerPage" name="per_page">
                        <?php foreach (['10','25','50','100','all'] as $pp): ?>
                        <option value="<?= e($pp) ?>" <?= $perPageRaw === $pp ? 'selected' : '' ?>><?= e($pp === 'all' ? 'All' : $pp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="sort" id="umSortField" value="<?= e($filters['sort']) ?>">
                <input type="hidden" name="sort_dir" id="umSortDir" value="<?= e(strtolower($filters['sort_dir'])) ?>">
            </form>
        </div>
    </div>

    <div class="card vk-card">
        <?php if ($perms['can_manage']): ?>
        <div class="card-body border-bottom py-2 d-flex flex-wrap gap-2 align-items-center">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="umSelectAll">
                <label class="form-check-label small" for="umSelectAll">Select all</label>
            </div>
            <button type="button" class="btn btn-sm btn-outline-success um-bulk-btn" data-bulk="bulk_approve" disabled>Approve</button>
            <button type="button" class="btn btn-sm btn-outline-primary um-bulk-btn" data-bulk="bulk_activate" disabled>Activate</button>
            <button type="button" class="btn btn-sm btn-outline-secondary um-bulk-btn" data-bulk="bulk_deactivate" disabled>Deactivate</button>
            <button type="button" class="btn btn-sm btn-outline-warning um-bulk-btn" data-bulk="bulk_suspend" disabled>Suspend</button>
            <button type="button" class="btn btn-sm btn-outline-danger um-bulk-btn" data-bulk="bulk_delete" disabled>Delete</button>
            <button type="button" class="btn btn-sm btn-outline-info um-bulk-btn" data-bulk="bulk_export" disabled>Export Selected</button>
        </div>
        <?php endif; ?>

        <div class="position-relative">
            <div class="um-loading d-none" id="umLoading"><div class="spinner-border text-primary"></div></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 um-table" id="umUsersTable">
                    <thead class="table-light sticky-top">
                        <tr>
                            <?php if ($perms['can_manage']): ?><th style="width:2rem"></th><?php endif; ?>
                            <th>User</th>
                            <th class="d-none d-md-table-cell um-sortable" data-sort="email">Email</th>
                            <th class="d-none d-lg-table-cell">Phone</th>
                            <th class="um-sortable" data-sort="role">Role</th>
                            <th class="d-none d-xl-table-cell um-sortable" data-sort="department">Dept</th>
                            <th class="um-sortable" data-sort="status">Status</th>
                            <th class="d-none d-lg-table-cell um-sortable" data-sort="created_at">Registered</th>
                            <th class="d-none d-xl-table-cell um-sortable" data-sort="last_login_at">Last Login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="umTableBody">
                    <?php if ($users === []): ?>
                        <tr><td colspan="<?= $perms['can_manage'] ? 10 : 9 ?>" class="text-center text-muted py-5">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u):
                            $row = vk_users_row_json($u, $actorId, $perms);
                        ?>
                        <tr data-user-id="<?= (int) $row['id'] ?>" class="<?= $row['status'] === 'pending' ? 'um-row-pending' : '' ?>">
                            <?php if ($perms['can_manage']): ?>
                            <td><input type="checkbox" class="form-check-input um-row-check" value="<?= (int) $row['id'] ?>"></td>
                            <?php endif; ?>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="um-avatar"><?= e($row['initials']) ?></span>
                                    <div>
                                        <div class="fw-semibold"><?= e($row['fullname'] ?: 'Unnamed') ?></div>
                                        <div class="small text-muted"><code><?= e($row['username']) ?></code></div>
                                    </div>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell"><?= e($row['email'] ?: '—') ?></td>
                            <td class="d-none d-lg-table-cell"><?= e($row['phone'] ?: '—') ?></td>
                            <td><span class="badge um-role-badge"><?= e($row['role_label']) ?></span></td>
                            <td class="d-none d-xl-table-cell"><?= e($row['department'] ?: '—') ?></td>
                            <td><span class="badge um-status um-status-<?= e($row['status']) ?>"><?= e($row['status_label']) ?></span></td>
                            <td class="d-none d-lg-table-cell"><?= e(substr($row['created_at'], 0, 16)) ?></td>
                            <td class="d-none d-xl-table-cell"><?= $row['last_login_at'] ? e(substr($row['last_login_at'], 0, 16)) : '<span class="text-muted">Never</span>' ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary um-view-btn" data-id="<?= (int) $row['id'] ?>"><i class="bi bi-eye"></i></button>
                                    <?php if ($row['can_edit']): ?>
                                    <button type="button" class="btn btn-outline-primary um-edit-btn" data-id="<?= (int) $row['id'] ?>"><i class="bi bi-pencil"></i></button>
                                    <?php endif; ?>
                                    <?php if ($row['can_approve']): ?>
                                    <button type="button" class="btn btn-outline-success um-action-btn" data-action="approve" data-id="<?= (int) $row['id'] ?>"><i class="bi bi-check-lg"></i></button>
                                    <?php endif; ?>
                                    <?php if ($row['can_reject']): ?>
                                    <button type="button" class="btn btn-outline-warning um-action-btn" data-action="reject" data-id="<?= (int) $row['id'] ?>"><i class="bi bi-x-lg"></i></button>
                                    <?php endif; ?>
                                    <?php if ($row['can_delete']): ?>
                                    <button type="button" class="btn btn-outline-danger um-delete-btn" data-id="<?= (int) $row['id'] ?>" data-name="<?= e($row['username']) ?>"><i class="bi bi-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pages > 1 && $perPage > 0): ?>
        <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted">Page <?= (int) $page ?> of <?= (int) $pages ?> · <?= (int) $totalRows ?> users</span>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php for ($i = 1; $i <= min($pages, 12); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= e(BASE_URL) ?>/modules/users/index.php<?= e(vk_users_build_query($filters, $i, $perPage ?: 25)) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($perms['can_manage']): ?>
<div class="modal fade" id="umUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="umUserModalTitle">Add User</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="umFormId" value="0">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="umFormUsername">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="umFormUsername" maxlength="64" required autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="umFormFullname">Full name</label>
                        <input type="text" class="form-control" id="umFormFullname" maxlength="128">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="umFormEmail">Email</label>
                        <input type="email" class="form-control" id="umFormEmail" maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="umFormPhone">Phone</label>
                        <input type="text" class="form-control" id="umFormPhone" maxlength="32">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="umFormDepartment">Department</label>
                        <input type="text" class="form-control" id="umFormDepartment" list="umDeptList" maxlength="128">
                        <datalist id="umDeptList">
                            <?php foreach ($deptRows as $dept): ?>
                            <option value="<?= e((string) $dept) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="umFormRole">Role</label>
                        <select class="form-select" id="umFormRole">
                            <?php foreach (VK_AUTH_ROLES as $r): ?>
                            <?php if ($r === 'super_admin' && !$perms['is_super_admin']) continue; ?>
                            <option value="<?= e($r) ?>"><?= e(vk_auth_role_label($r)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="umFormStatus">Status</label>
                        <select class="form-select" id="umFormStatus">
                            <?php foreach ($statusOptions as $val => $label): ?>
                            <option value="<?= e($val) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-none" id="umFormTechWrap">
                        <label class="form-label" for="umFormTechnician">Linked technician</label>
                        <select class="form-select" id="umFormTechnician">
                            <option value="">— Select —</option>
                            <?php foreach ($techRows as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label mb-0" for="umFormPassword"><span id="umPwdLabel">Password</span></label>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="umGenPassword">Generate secure password</button>
                        </div>
                        <input type="password" class="form-control" id="umFormPassword" autocomplete="new-password">
                        <div class="form-text" id="umPwdHelp">Minimum 8 characters with letters and numbers.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="umFormSave">Save User</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="umViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5">User Profile</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="umViewBody"><div class="text-center py-4"><div class="spinner-border"></div></div></div>
        </div>
    </div>
</div>

<div class="modal fade" id="umConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="umConfirmTitle">Confirm</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="umConfirmBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="umConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/users-admin.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
