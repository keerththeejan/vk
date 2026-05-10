<?php
declare(strict_types=1);
$pageTitle = 'Enterprise Dashboard';
require_once __DIR__ . '/includes/layout_init.php';

$user = current_user($pdo) ?: [];
$role = (string) ($user['role'] ?? 'viewer');
$status = (string) ($user['status'] ?? 'pending');
if ($status !== 'active') {
    $_SESSION = [];
    session_destroy();
    session_name(SESSION_NAME);
    session_start();
    flash_set('warning', 'Your account is waiting for administrator approval.');
    redirect('/login.php');
}

$pendingApprovals = 0;
$totalUsers = 0;
$recentLogins = [];
if (vk_auth_role_can_manage($role)) {
    $pendingApprovals = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $recentLogins = $pdo->query(
        "SELECT l.created_at, l.status, l.ip_address, COALESCE(u.fullname, l.username) AS display_name
         FROM login_logs l LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.id DESC LIMIT 6"
    )->fetchAll();
}

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
        <div class="col-md-4">
            <div class="card vk-card h-100">
                <div class="card-body">
                    <div class="text-muted small">User ID</div>
                    <div class="h4 mb-1"><?= e((string) ($user['user_uid'] ?? 'VK-ACTIVE')) ?></div>
                    <div class="small text-muted"><?= e((string) ($user['department'] ?? 'Enterprise')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card vk-card h-100">
                <div class="card-body">
                    <div class="text-muted small">Security Layer</div>
                    <div class="h4 mb-1">CSRF + Session Guard</div>
                    <div class="small text-muted">Regenerated sessions, approval gate, login audit trails.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card vk-card h-100">
                <div class="card-body">
                    <div class="text-muted small"><?= vk_auth_role_can_manage($role) ? 'Pending Approvals' : 'Access Mode' ?></div>
                    <div class="h4 mb-1"><?= vk_auth_role_can_manage($role) ? (int) $pendingApprovals : 'Role Based' ?></div>
                    <div class="small text-muted"><?= vk_auth_role_can_manage($role) ? ((int) $totalUsers . ' total users') : 'Only approved modules are available.' ?></div>
                </div>
            </div>
        </div>
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
            <div class="card vk-card h-100">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">Recent Login Signals</h2>
                    <a class="small" href="<?= e(BASE_URL) ?>/approve_users.php">View logs</a>
                </div>
                <div class="card-body">
                    <?php foreach ($recentLogins as $log): ?>
                        <div class="d-flex justify-content-between gap-3 border-bottom border-light border-opacity-10 py-2">
                            <div>
                                <div class="fw-semibold"><?= e((string) $log['display_name']) ?></div>
                                <div class="small text-muted"><?= e(substr((string) $log['created_at'], 0, 16)) ?> · <?= e((string) $log['ip_address']) ?></div>
                            </div>
                            <span class="badge text-bg-<?= $log['status'] === 'success' ? 'success' : 'secondary' ?> align-self-start"><?= e((string) $log['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$recentLogins): ?><div class="text-muted">No login activity yet.</div><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
