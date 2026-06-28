<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap_core.php';
vk_api_require_admin();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=30');

$role = (string) ($_SESSION['user_role'] ?? 'viewer');
if (!vk_auth_role_can_manage($role)) {
    echo json_encode(['ok' => true, 'admin' => null], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
$pendingApprovals = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending' AND approved = 0")->fetchColumn();
vk_perf_mark_query();
$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
vk_perf_mark_query();
$approvedUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE approved = 1 AND status IN ('approved','active')")->fetchColumn();
vk_perf_mark_query();
$suspendedUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();
vk_perf_mark_query();
$recentRegistrations = $pdo->query(
    "SELECT fullname, email, username, department, status, created_at
     FROM users ORDER BY created_at DESC, id DESC LIMIT 6"
)->fetchAll();
vk_perf_mark_query();
$recentLogins = $pdo->query(
    "SELECT l.created_at, l.status, l.ip_address, COALESCE(u.fullname, l.username) AS display_name
     FROM login_logs l LEFT JOIN users u ON u.id = l.user_id
     ORDER BY l.id DESC LIMIT 6"
)->fetchAll();
vk_perf_mark_query();

echo json_encode([
    'ok' => true,
    'admin' => [
        'pending_approvals' => $pendingApprovals,
        'total_users' => $totalUsers,
        'approved_users' => $approvedUsers,
        'suspended_users' => $suspendedUsers,
        'recent_registrations' => $recentRegistrations,
        'recent_logins' => $recentLogins,
    ],
], JSON_THROW_ON_ERROR);
