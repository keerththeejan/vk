<?php
declare(strict_types=1);
$pageTitle = 'Enterprise Dashboard';
require_once __DIR__ . '/includes/layout_init.php';

$user = vk_auth_cached_user() ?: current_user($pdo) ?: [];
$role = (string) ($user['role'] ?? 'viewer');
$status = (string) ($user['status'] ?? 'pending');
if (!vk_auth_status_is_approved($status)) {
    $_SESSION = [];
    session_destroy();
    session_name(SESSION_NAME);
    session_start();
    flash_set('warning', 'Your account is awaiting administrator approval.');
    redirect('/login.php');
}

$pendingApprovals = 0;
$totalUsers = 0;
$approvedUsers = 0;
$suspendedUsers = 0;
$recentLogins = [];
$recentRegistrations = [];
$extraScripts = ($extraScripts ?? '') . "\n" . '<script src="' . e(base_url('assets/js/dashboard-admin.js')) . '?v=' . e(vk_asset_mtime_version('assets/js/dashboard-admin.js')) . '" defer></script>';

$quickActions = [
    ['label' => 'Operations dashboard', 'icon' => 'bi-speedometer2', 'href' => BASE_URL . '/modules/dashboard.php', 'roles' => ['super_admin','admin','manager','staff','viewer']],
    ['label' => 'Approval center', 'icon' => 'bi-person-check', 'href' => BASE_URL . '/approve_users.php', 'roles' => ['super_admin','admin']],
    ['label' => 'Service jobs', 'icon' => 'bi-tools', 'href' => BASE_URL . '/modules/repairs/list.php', 'roles' => ['super_admin','admin','manager','technician','staff']],
    ['label' => 'Billing', 'icon' => 'bi-receipt', 'href' => BASE_URL . '/modules/invoices/list.php', 'roles' => ['super_admin','admin','manager','staff']],
    ['label' => 'Settings', 'icon' => 'bi-gear-wide-connected', 'href' => BASE_URL . '/modules/settings/index.php', 'roles' => ['super_admin','admin']],
];
$visibleActions = array_values(array_filter($quickActions, static fn(array $a): bool => in_array($role, $a['roles'], true)));

$extraHead = '<link href="' . e(base_url('assets/css/auth-enterprise.css')) . '?v=' . e((string) filemtime(__DIR__ . '/assets/css/auth-enterprise.css')) . '" rel="stylesheet">';
require_once __DIR__ . '/includes/layout_start.php';
?>
<div class="vk-enterprise-page">
    <section class="vk-enterprise-hero mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <div class="text-info fw-semibold small text-uppercase">Secure enterprise workspace</div>
                <h1 class="display-6 fw-bold mb-2">Welcome, <?= e((string) ($user['fullname'] ?: $user['username'] ?: 'User')) ?></h1>
                <p class="text-muted mb-0">Your VK Network account is active with <?= e(vk_auth_role_label($role)) ?> access. Role-based modules and approvals are enforced across the system.</p>
            </div>
            <div class="col-lg-4">
                <div class="vk-enterprise-stat">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Account Status</span>
                        <span class="vk-status-badge vk-status-active">Approved</span>
                    </div>
                    <div class="h5 mt-3 mb-1"><?= e(vk_auth_role_label($role)) ?></div>
                    <div class="small text-muted">Last login: <?= e((string) ($user['last_login_at'] ?? 'Current session')) ?></div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card vk-card h-100">
                <div class="card-body">
                    <div class="text-muted small">User ID</div>
                    <div class="h4 mb-1"><?= e((string) ($user['user_uid'] ?? 'VK-ACTIVE')) ?></div>
                    <div class="small text-muted"><?= e((string) ($user['department'] ?? 'Enterprise')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card vk-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Security Layer</div>
                    <div class="h4 mb-1">CSRF + Session Guard</div>
                    <div class="small text-muted">Regenerated sessions, approval gate, login audit trails.</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card vk-card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= vk_auth_role_can_manage($role) ? 'Pending Approvals' : 'Access Mode' ?></div>
                    <div class="h4 mb-1" data-vk-admin="pending"><?= vk_auth_role_can_manage($role) ? '…' : 'Role Based' ?></div>
                    <div class="small text-muted" data-vk-admin="users-summary"><?= vk_auth_role_can_manage($role) ? 'Loading users…' : 'Only approved modules are available.' ?></div>
                </div>
            </div>
        </div>
        <?php if (vk_auth_role_can_manage($role)): ?>
        <div class="col-md-3">
            <div class="card vk-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Approved / Suspended</div>
                    <div class="h4 mb-1" data-vk-admin="approved-suspended">…</div>
                    <div class="small text-muted">Identity governance snapshot.</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-<?= vk_auth_role_can_manage($role) ? '7' : '12' ?>">
            <div class="card vk-card h-100">
                <div class="card-header bg-transparent">
                    <h2 class="h5 mb-0">Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($visibleActions as $action): ?>
                            <div class="col-md-6">
                                <a class="vk-enterprise-stat d-flex align-items-center gap-3 text-decoration-none text-reset h-100" href="<?= e($action['href']) ?>">
                                    <i class="bi <?= e($action['icon']) ?> fs-3 text-info"></i>
                                    <strong><?= e($action['label']) ?></strong>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php if (vk_auth_role_can_manage($role)): ?>
        <div class="col-lg-5">
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Recent Registrations</h2>
                    <span class="badge text-bg-info" data-vk-admin="pending-badge">New Registrations …</span>
                </div>
                <div class="card-body" data-vk-admin="registrations">
                    <div class="text-muted">Loading registrations…</div>
                </div>
            </div>
            <div class="card vk-card">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Recent Login Signals</h2>
                    <a class="small" href="<?= e(BASE_URL) ?>/approve_users.php">View logs</a>
                </div>
                <div class="card-body" data-vk-admin="logins">
                    <div class="text-muted">Loading login activity…</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
