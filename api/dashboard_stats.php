<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap_core.php';
vk_api_require_admin();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Cache-Control: private, max-age=20');
$pdo = db();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$alertDays = defined('WARRANTY_ALERT_DAYS') ? (int) WARRANTY_ALERT_DAYS : 30;

$salesToday = 0.0;
$salesMonth = 0.0;
$totalCustomers = 0;
$repairPipeline = 0;
$repairCompleted = 0;
$repairDelivered = 0;
$cctvActive = 0;
$cctvDone = 0;
$totalBookings = 0;
$totalServices = 0;
$completedJobs = 0;
$pendingJobs = 0;
$activeTechnicians = 0;
$activeContracts = 0;
$warrantyExpiring = 0;
$recentWebBookings = [];
$recentJobs = [];
$maintReminders = [];
$emergencyBookings = [];
$emergencyRepairs = [];

try {
    $st = $pdo->prepare('SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE invoice_date = ?');
    $st->execute([$today]);
    vk_perf_mark_query();
    $salesToday = (float) $st->fetchColumn();

    $st = $pdo->prepare('SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE invoice_date >= ? AND invoice_date <= ?');
    $st->execute([$monthStart, date('Y-m-t')]);
    vk_perf_mark_query();
    $salesMonth = (float) $st->fetchColumn();
} catch (Throwable $e) {
}

try {
    $totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    vk_perf_mark_query();
} catch (Throwable $e) {
}

try {
    $repairCounts = $pdo->query(
        "SELECT
            SUM(status IN ('pending','diagnosing','in_progress')) AS pipeline,
            SUM(status = 'completed') AS completed,
            SUM(status = 'delivered') AS delivered
         FROM repair_jobs"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    vk_perf_mark_query();
    $repairPipeline = (int) ($repairCounts['pipeline'] ?? 0);
    $repairCompleted = (int) ($repairCounts['completed'] ?? 0);
    $repairDelivered = (int) ($repairCounts['delivered'] ?? 0);
} catch (Throwable $e) {
}

try {
    $cctvCounts = $pdo->query(
        "SELECT
            SUM(status IN ('pending','in_progress')) AS active,
            SUM(status IN ('completed','delivered')) AS done
         FROM cctv_installations"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    vk_perf_mark_query();
    $cctvActive = (int) ($cctvCounts['active'] ?? 0);
    $cctvDone = (int) ($cctvCounts['done'] ?? 0);
} catch (Throwable $e) {
}

try {
    $jobTotals = $pdo->query(
        "SELECT
            SUM(tbl = 'repair' AND status IN ('completed','delivered')) +
            SUM(tbl = 'cctv' AND status IN ('completed','delivered')) AS completed,
            SUM(tbl = 'repair' AND status IN ('pending','diagnosing','in_progress')) +
            SUM(tbl = 'cctv' AND status IN ('pending','in_progress')) AS pending
         FROM (
            SELECT 'repair' AS tbl, status FROM repair_jobs
            UNION ALL
            SELECT 'cctv', status FROM cctv_installations
         ) jobs"
    )->fetch(PDO::FETCH_ASSOC) ?: [];
    vk_perf_mark_query();
    $completedJobs = (int) ($jobTotals['completed'] ?? 0);
    $pendingJobs = (int) ($jobTotals['pending'] ?? 0);
} catch (Throwable $e) {
}

try {
    $activeTechnicians = (int) $pdo->query('SELECT COUNT(*) FROM technicians WHERE active = 1')->fetchColumn();
    vk_perf_mark_query();
} catch (Throwable $e) {
}

if (db_table_exists($pdo, 'web_bookings')) {
    try {
        $totalBookings = (int) $pdo->query('SELECT COUNT(*) FROM web_bookings')->fetchColumn();
        vk_perf_mark_query();
        $recentWebBookings = $pdo->query(
            'SELECT id, booking_number, customer_name, phone, status, service_type, created_at
             FROM web_bookings ORDER BY created_at DESC LIMIT 8'
        )->fetchAll();
        vk_perf_mark_query();
    } catch (Throwable $e) {
    }
}

if (db_table_exists($pdo, 'web_services')) {
    try {
        $totalServices = (int) $pdo->query('SELECT COUNT(*) FROM web_services WHERE active = 1')->fetchColumn();
        vk_perf_mark_query();
    } catch (Throwable $e) {
    }
}

if (db_table_exists($pdo, 'maintenance_contracts')) {
    try {
        $activeContracts = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_contracts WHERE status = 'active'")->fetchColumn();
        vk_perf_mark_query();
        $maintReminders = $pdo->query(
            "SELECT m.contract_number, m.title, m.next_service_date, c.name AS customer_name
             FROM maintenance_contracts m
             JOIN customers c ON c.id = m.customer_id
             WHERE m.status = 'active' AND m.next_service_date IS NOT NULL
               AND m.next_service_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             ORDER BY m.next_service_date ASC
             LIMIT 8"
        )->fetchAll();
        vk_perf_mark_query();
    } catch (Throwable $e) {
    }
}

if (db_table_exists($pdo, 'warranty_records')) {
    try {
        $warrantyExpiring = (int) $pdo->query(
            'SELECT COUNT(*) FROM warranty_records WHERE end_date >= CURDATE()
             AND end_date <= DATE_ADD(CURDATE(), INTERVAL ' . $alertDays . ' DAY)'
        )->fetchColumn();
        vk_perf_mark_query();
    } catch (Throwable $e) {
    }
}

try {
    $recentJobs = $pdo->query(
        'SELECT id, ref, job_type, status, created_at, customer_name FROM (
            SELECT r.id, r.job_number AS ref, \'repair\' AS job_type, r.status, r.created_at, c.name AS customer_name
            FROM repair_jobs r JOIN customers c ON c.id = r.customer_id
            UNION ALL
            SELECT v.id, v.job_number, \'cctv\', v.status, v.created_at, c.name
            FROM cctv_installations v JOIN customers c ON c.id = v.customer_id
        ) u ORDER BY created_at DESC LIMIT 12'
    )->fetchAll();
    vk_perf_mark_query();
} catch (Throwable $e) {
}

if (db_table_exists($pdo, 'web_bookings') && db_column_exists($pdo, 'web_bookings', 'is_emergency')) {
    try {
        $emergencyBookings = $pdo->query(
            "SELECT id, booking_number, customer_name, phone, service_type, created_at
             FROM web_bookings
             WHERE is_emergency = 1 AND status IN ('pending','in_progress')
             ORDER BY id DESC LIMIT 12"
        )->fetchAll();
        vk_perf_mark_query();
    } catch (Throwable $e) {
    }
}

if (db_table_exists($pdo, 'repair_jobs') && db_column_exists($pdo, 'repair_jobs', 'emergency_priority')) {
    try {
        $emergencyRepairs = $pdo->query(
            "SELECT r.id, r.job_number, r.status, c.name AS customer_name
             FROM repair_jobs r JOIN customers c ON c.id = r.customer_id
             WHERE r.emergency_priority = 1 AND r.status NOT IN ('delivered','completed')
             ORDER BY r.id DESC LIMIT 12"
        )->fetchAll();
        vk_perf_mark_query();
    } catch (Throwable $e) {
    }
}

$totalServiceJobs = $completedJobs + $pendingJobs;
$workloadCompletion = $totalServiceJobs > 0 ? min(100, (int) round(($completedJobs / $totalServiceJobs) * 100)) : 0;
$repairCompletion = ($repairPipeline + $repairCompleted + $repairDelivered) > 0
    ? min(100, (int) round((($repairCompleted + $repairDelivered) / ($repairPipeline + $repairCompleted + $repairDelivered)) * 100))
    : 0;
$criticalCount = count($emergencyBookings) + count($emergencyRepairs) + $warrantyExpiring;
$systemPulse = $criticalCount > 0 ? 'Attention needed' : 'All channels stable';

$marketing = ['reach' => 0, 'active_campaigns' => 0, 'leads' => 0, 'conversion_rate' => 0, 'whatsapp_delivery_rate' => 0];
$seoAverage = 0;
try {
    require_once dirname(__DIR__) . '/includes/marketing_suite.php';
    vk_marketing_suite_seed($pdo);
    $marketing = vk_marketing_metrics($pdo);
    if (db_table_exists($pdo, 'seo_settings')) {
        $seoAverage = (int) $pdo->query('SELECT COALESCE(ROUND(AVG(seo_score)),0) FROM seo_settings')->fetchColumn();
        vk_perf_mark_query();
    }
} catch (Throwable $e) {
}

$smtpWarning = null;
try {
    vk_bootstrap_module('mailer');
    $smtpCfg = vk_smtp_settings_get($pdo);
    if (!($smtpCfg['configured'] ?? false)) {
        $smtpWarning = 'unconfigured';
    } elseif (
        trim((string) ($smtpCfg['smtp_pass'] ?? '')) === ''
        && !vk_smtp_env_key_set('VK_SMTP_PASS')
        && vk_smtp_env_value('MAIL_PASSWORD') === null
    ) {
        $smtpWarning = 'missing_password';
    }
} catch (Throwable $e) {
}

echo json_encode([
    'ok' => true,
    'generated_at' => time(),
    'stats' => [
        'sales_today' => $salesToday,
        'sales_month' => $salesMonth,
        'total_customers' => $totalCustomers,
        'repair_pipeline' => $repairPipeline,
        'repair_completed' => $repairCompleted,
        'repair_delivered' => $repairDelivered,
        'cctv_active' => $cctvActive,
        'cctv_done' => $cctvDone,
        'total_bookings' => $totalBookings,
        'total_services' => $totalServices,
        'completed_jobs' => $completedJobs,
        'pending_jobs' => $pendingJobs,
        'active_technicians' => $activeTechnicians,
        'active_contracts' => $activeContracts,
        'warranty_expiring' => $warrantyExpiring,
        'workload_completion' => $workloadCompletion,
        'repair_completion' => $repairCompletion,
        'critical_count' => $criticalCount,
        'system_pulse' => $systemPulse,
        'seo_average' => $seoAverage,
    ],
    'marketing' => $marketing,
    'smtp_warning' => $smtpWarning,
    'recent_web_bookings' => $recentWebBookings,
    'recent_jobs' => $recentJobs,
    'maint_reminders' => $maintReminders,
    'emergency_bookings' => $emergencyBookings,
    'emergency_repairs' => $emergencyRepairs,
    'schema_needs_v3' => !db_table_exists($pdo, 'maintenance_contracts') || !db_table_exists($pdo, 'warranty_records'),
], JSON_THROW_ON_ERROR);
