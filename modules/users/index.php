<?php
declare(strict_types=1);
$pageTitle = 'Users';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_users_admin($pdo);
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$techRows = $pdo->query('SELECT id, name FROM technicians WHERE active = 1 ORDER BY name ASC')->fetchAll();
$users = $pdo->query(
    'SELECT id, username, email, phone, fullname, role, technician_id, status, created_at
     FROM users ORDER BY id DESC'
)->fetchAll();

$roleBadge = static function (string $r): string {
    return match ($r) {
        'admin' => 'text-bg-danger',
        'technician' => 'text-bg-info',
        default => 'text-bg-secondary',
    };
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Users</h1>
        <p class="text-muted small mb-0">Manage accounts, roles, and access. Only administrators see this page.</p>
    </div>
    <button type="button" class="btn btn-primary" id="vkUserAddBtn" data-bs-toggle="modal" data-bs-target="#vkUserModal">
        <i class="bi bi-person-plus me-1"></i>Add user
    </button>
</div>

<div class="card vk-card mb-3">
    <div class="card-body py-2">
        <label class="form-label small text-muted mb-1" for="vkUserSearch">Search</label>
        <input type="search" class="form-control form-control-sm" id="vkUserSearch" placeholder="Filter by name, username, email, phone, role…" autocomplete="off">
    </div>
</div>

<div class="card vk-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="vkUsersTable">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php
                $rid = (int) $u['id'];
                $ruser = e((string) $u['username']);
                $remail = e((string) ($u['email'] ?? ''));
                $rphone = e((string) ($u['phone'] ?? ''));
                $rname = e((string) ($u['fullname'] ?? ''));
                $rrole = (string) ($u['role'] ?? 'staff');
                $rstat = (string) ($u['status'] ?? 'active');
                $rtid = $u['technician_id'] !== null ? (int) $u['technician_id'] : '';
                $searchBlob = strtolower(
                    (string) ($u['fullname'] ?? '') . ' '
                    . (string) ($u['username'] ?? '') . ' '
                    . (string) ($u['email'] ?? '') . ' '
                    . (string) ($u['phone'] ?? '') . ' '
                    . $rrole . ' ' . $rstat
                );
                ?>
                <tr class="vk-user-row" data-vk-search="<?= e($searchBlob) ?>">
                    <td><?= $rname !== '' ? $rname : '—' ?></td>
                    <td><code><?= $ruser ?></code></td>
                    <td><?= $remail !== '' ? $remail : '—' ?></td>
                    <td><?= $rphone !== '' ? $rphone : '—' ?></td>
                    <td><span class="badge <?= e($roleBadge($rrole)) ?>"><?= e($rrole) ?></span></td>
                    <td>
                        <?php if ($rstat === 'active'): ?>
                            <span class="badge text-bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary vk-user-edit"
                            data-id="<?= $rid ?>"
                            data-username="<?= $ruser ?>"
                            data-email="<?= $remail ?>"
                            data-phone="<?= $rphone ?>"
                            data-fullname="<?= $rname ?>"
                            data-role="<?= e($rrole) ?>"
                            data-status="<?= e($rstat) ?>"
                            data-technician-id="<?= $rtid !== '' ? (string) $rtid : '' ?>"
                        >Edit</button>
                        <?php if ($rid !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger vk-user-deactivate"
                                data-id="<?= $rid ?>"
                                data-username="<?= $ruser ?>"
                            >Deactivate</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="vkUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title" id="vkUserModalTitle">Add user</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="vkUserFormId" value="0">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="vkUserFormUsername">Username</label>
                        <input type="text" class="form-control" id="vkUserFormUsername" maxlength="64" required autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vkUserFormFullname">Full name</label>
                        <input type="text" class="form-control" id="vkUserFormFullname" maxlength="128">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vkUserFormEmail">Email</label>
                        <input type="email" class="form-control" id="vkUserFormEmail" maxlength="150" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vkUserFormPhone">Phone</label>
                        <input type="text" class="form-control" id="vkUserFormPhone" maxlength="32">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vkUserFormRole">Role</label>
                        <select class="form-select" id="vkUserFormRole">
                            <option value="admin">Administrator</option>
                            <option value="staff">Staff</option>
                            <option value="technician">Technician</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vkUserFormStatus">Status</label>
                        <select class="form-select" id="vkUserFormStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-12" id="vkUserFormTechWrap">
                        <label class="form-label" for="vkUserFormTechnician">Linked technician</label>
                        <select class="form-select" id="vkUserFormTechnician">
                            <option value="">— Select —</option>
                            <?php foreach ($techRows as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"><?= e((string) $t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="vkUserFormPassword"><span id="vkUserPwdLabel">Password</span></label>
                        <input type="password" class="form-control" id="vkUserFormPassword" autocomplete="new-password">
                        <div class="form-text" id="vkUserPwdHelp">Minimum 8 characters. Leave blank when editing to keep the current password.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="vkUserFormSave">Save</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(BASE_URL) . '/assets/js/users-admin.js" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
