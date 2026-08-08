<?php
declare(strict_types=1);

/**
 * Consolidated dashboard statistics — single service with optional cache.
 */

function vk_dashboard_stats_ttl(): int
{
    return max(15, (int) (getenv('VK_DASHBOARD_CACHE_TTL') ?: 45));
}

/**
 * @return array<string, mixed>
 */
function vk_dashboard_stats_fetch(PDO $pdo, bool $useCache = true): array
{
    if ($useCache) {
        return vk_cache_remember('dashboard_stats_v3', vk_dashboard_stats_ttl(), static function () use ($pdo) {
            return vk_dashboard_stats_compute($pdo);
        });
    }

    return vk_dashboard_stats_compute($pdo);
}

/**
 * @return array<string, mixed>
 */
function vk_dashboard_stats_compute(PDO $pdo): array
{
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');
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
    $seoAverage = 0;

    try {
        $st = $pdo->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN invoice_date = ? THEN grand_total ELSE 0 END), 0) AS sales_today,
                COALESCE(SUM(grand_total), 0) AS sales_month
             FROM invoices
             WHERE invoice_date >= ? AND invoice_date <= ?'
        );
        $st->execute([$today, $monthStart, $monthEnd]);
        vk_perf_mark_query();
        $inv = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $salesToday = (float) ($inv['sales_today'] ?? 0);
        $salesMonth = (float) ($inv['sales_month'] ?? 0);
    } catch (Throwable) {
    }

    try {
        $totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
        vk_perf_mark_query();
    } catch (Throwable) {
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
    } catch (Throwable) {
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
    } catch (Throwable) {
    }

    $completedJobs = $repairCompleted + $repairDelivered + $cctvDone;
    $pendingJobs = $repairPipeline + $cctvActive;

    try {
        $activeTechnicians = (int) $pdo->query('SELECT COUNT(*) FROM technicians WHERE active = 1')->fetchColumn();
        vk_perf_mark_query();
    } catch (Throwable) {
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
        } catch (Throwable) {
        }
    }

    if (db_table_exists($pdo, 'web_services')) {
        try {
            $totalServices = (int) $pdo->query('SELECT COUNT(*) FROM web_services WHERE active = 1')->fetchColumn();
            vk_perf_mark_query();
        } catch (Throwable) {
        }
    }

    if (db_table_exists($pdo, 'maintenance_contracts')) {
        try {
            $activeContracts = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_contracts WHERE status = 'active'")->fetchColumn();
            vk_perf_mark_query();
            $maintReminders = $pdo->query(
                "SELECT m.contract_number, m.title, m.next_service_date, c.name AS customer_name
                 FROM maintenance_contracts m
                 INNER JOIN customers c ON c.id = m.customer_id
                 WHERE m.status = 'active' AND m.next_service_date IS NOT NULL
                   AND m.next_service_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                 ORDER BY m.next_service_date ASC
                 LIMIT 8"
            )->fetchAll();
            vk_perf_mark_query();
        } catch (Throwable) {
        }
    }

    if (db_table_exists($pdo, 'warranty_records')) {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM warranty_records
                 WHERE end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)'
            );
            $st->execute([$alertDays]);
            vk_perf_mark_query();
            $warrantyExpiring = (int) $st->fetchColumn();
        } catch (Throwable) {
        }
    }

    try {
        $recentJobs = $pdo->query(
            'SELECT id, ref, job_type, status, created_at, customer_name FROM (
                SELECT r.id, r.job_number AS ref, \'repair\' AS job_type, r.status, r.created_at, c.name AS customer_name
                FROM repair_jobs r INNER JOIN customers c ON c.id = r.customer_id
                UNION ALL
                SELECT v.id, v.job_number, \'cctv\', v.status, v.created_at, c.name
                FROM cctv_installations v INNER JOIN customers c ON c.id = v.customer_id
            ) u ORDER BY created_at DESC LIMIT 12'
        )->fetchAll();
        vk_perf_mark_query();
    } catch (Throwable) {
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
        } catch (Throwable) {
        }
    }

    if (db_table_exists($pdo, 'repair_jobs') && db_column_exists($pdo, 'repair_jobs', 'emergency_priority')) {
        try {
            $emergencyRepairs = $pdo->query(
                "SELECT r.id, r.job_number, r.status, c.name AS customer_name
                 FROM repair_jobs r INNER JOIN customers c ON c.id = r.customer_id
                 WHERE r.emergency_priority = 1 AND r.status NOT IN ('delivered','completed')
                 ORDER BY r.id DESC LIMIT 12"
            )->fetchAll();
            vk_perf_mark_query();
        } catch (Throwable) {
        }
    }

    $totalServiceJobs = $completedJobs + $pendingJobs;
    $workloadCompletion = $totalServiceJobs > 0 ? min(100, (int) round(($completedJobs / $totalServiceJobs) * 100)) : 0;
    $repairTotal = $repairPipeline + $repairCompleted + $repairDelivered;
    $repairCompletion = $repairTotal > 0
        ? min(100, (int) round((($repairCompleted + $repairDelivered) / $repairTotal) * 100))
        : 0;
    $criticalCount = count($emergencyBookings) + count($emergencyRepairs) + $warrantyExpiring;
    $systemPulse = $criticalCount > 0 ? 'Attention needed' : 'All channels stable';

    $marketing = ['reach' => 0, 'active_campaigns' => 0, 'leads' => 0, 'conversion_rate' => 0, 'whatsapp_delivery_rate' => 0];
    try {
        require_once __DIR__ . '/marketing_suite.php';
        // Seed once per day — avoid DDL/seed work on every dashboard cache miss.
        if (vk_cache_get('marketing_seeded_v1') !== '1') {
            vk_marketing_suite_seed($pdo);
            vk_cache_set('marketing_seeded_v1', '1', 86400);
        }
        $marketing = vk_marketing_metrics($pdo);
        if (db_table_exists($pdo, 'seo_settings')) {
            $seoAverage = (int) $pdo->query('SELECT COALESCE(ROUND(AVG(seo_score)),0) FROM seo_settings')->fetchColumn();
            vk_perf_mark_query();
        }
    } catch (Throwable) {
    }

    $smtpWarning = null;
    try {
        $smtpCached = vk_cache_remember('smtp_warning_v1', 300, static function () use ($pdo) {
            vk_bootstrap_module('mailer');
            $smtpCfg = vk_smtp_settings_get($pdo);
            if (!($smtpCfg['configured'] ?? false)) {
                return 'unconfigured';
            }
            if (
                trim((string) ($smtpCfg['smtp_pass'] ?? '')) === ''
                && !vk_smtp_env_key_set('VK_SMTP_PASS')
                && vk_smtp_env_value('MAIL_PASSWORD') === null
            ) {
                return 'missing_password';
            }

            return 'ok';
        });
        $smtpWarning = ($smtpCached === 'ok' || $smtpCached === null || $smtpCached === '')
            ? null
            : (string) $smtpCached;
    } catch (Throwable) {
    }

    // Additive ERP KPIs — read-only counts; safe if tables/columns are missing.
    $erp = [
        'quotations_total' => 0,
        'quotations_pending' => 0,
        'quotations_approved' => 0,
        'quotations_today' => 0,
        'invoices_total' => 0,
        'invoices_today' => 0,
        'outstanding' => 0.0,
        'products_total' => 0,
        'stock_items' => 0,
        'low_stock' => 0,
        'suppliers_total' => 0,
        'payments_today_count' => 0,
        'collections_today' => 0.0,
        'today_activities' => 0,
    ];
    $recentQuotations = [];
    $recentCustomers = [];
    $recentPayments = [];
    $recentInvoices = [];
    $charts = [
        'monthly_sales' => ['labels' => [], 'values' => []],
        'quotation_status' => ['labels' => [], 'values' => []],
        'customer_growth' => ['labels' => [], 'values' => []],
    ];

    if (db_table_exists($pdo, 'quotations')) {
        try {
            $qRow = $pdo->query(
                "SELECT
                    COUNT(*) AS total,
                    SUM(status = 'pending_approval') AS pending,
                    SUM(status = 'approved') AS approved,
                    SUM(quotation_date = CURDATE()) AS today,
                    SUM(status = 'draft') AS draft,
                    SUM(status = 'rejected') AS rejected,
                    SUM(status = 'expired') AS expired,
                    SUM(status IN ('converted_invoice','converted_so','accepted')) AS converted
                 FROM quotations"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            vk_perf_mark_query();
            $erp['quotations_total'] = (int) ($qRow['total'] ?? 0);
            $erp['quotations_pending'] = (int) ($qRow['pending'] ?? 0);
            $erp['quotations_approved'] = (int) ($qRow['approved'] ?? 0);
            $erp['quotations_today'] = (int) ($qRow['today'] ?? 0);
            $charts['quotation_status'] = [
                'labels' => ['Draft', 'Pending', 'Approved', 'Rejected', 'Expired', 'Converted'],
                'values' => [
                    (int) ($qRow['draft'] ?? 0),
                    (int) ($qRow['pending'] ?? 0),
                    (int) ($qRow['approved'] ?? 0),
                    (int) ($qRow['rejected'] ?? 0),
                    (int) ($qRow['expired'] ?? 0),
                    (int) ($qRow['converted'] ?? 0),
                ],
            ];
            $recentQuotations = $pdo->query(
                'SELECT q.id, q.quotation_number, q.status, q.grand_total, q.quotation_date, q.created_at, c.name AS customer_name
                 FROM quotations q
                 INNER JOIN customers c ON c.id = q.customer_id
                 ORDER BY q.id DESC LIMIT 8'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            vk_perf_mark_query();
        } catch (Throwable) {
        }
    }

    try {
        $invRow = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(invoice_date = CURDATE()) AS today,
                COALESCE(SUM(CASE WHEN status IN ('unpaid','partial')
                    THEN GREATEST(grand_total - paid_amount, 0) ELSE 0 END), 0) AS outstanding
             FROM invoices"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        vk_perf_mark_query();
        $erp['invoices_total'] = (int) ($invRow['total'] ?? 0);
        $erp['invoices_today'] = (int) ($invRow['today'] ?? 0);
        $erp['outstanding'] = (float) ($invRow['outstanding'] ?? 0);
        $recentInvoices = $pdo->query(
            'SELECT i.id, i.invoice_number, i.status, i.grand_total, i.paid_amount, i.invoice_date, i.created_at, c.name AS customer_name
             FROM invoices i
             LEFT JOIN customers c ON c.id = i.customer_id
             ORDER BY i.id DESC LIMIT 8'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        vk_perf_mark_query();
    } catch (Throwable) {
    }

    if (db_table_exists($pdo, 'products')) {
        try {
            $pRow = $pdo->query(
                'SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(GREATEST(stock, 0)), 0) AS stock_units,
                    SUM(stock <= COALESCE(low_stock_threshold, 5)) AS low_stock
                 FROM products'
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            vk_perf_mark_query();
            $erp['products_total'] = (int) ($pRow['total'] ?? 0);
            $erp['stock_items'] = (int) ($pRow['stock_units'] ?? 0);
            $erp['low_stock'] = (int) ($pRow['low_stock'] ?? 0);
        } catch (Throwable) {
            try {
                $erp['products_total'] = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
                vk_perf_mark_query();
            } catch (Throwable) {
            }
        }
    }

    if (db_table_exists($pdo, 'suppliers')) {
        try {
            $erp['suppliers_total'] = (int) $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();
            vk_perf_mark_query();
        } catch (Throwable) {
        }
    }

    if (db_table_exists($pdo, 'payments')) {
        try {
            $payRow = $pdo->query(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
                 FROM payments WHERE DATE(paid_at) = CURDATE()"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            vk_perf_mark_query();
            $erp['payments_today_count'] = (int) ($payRow['cnt'] ?? 0);
            $erp['collections_today'] = (float) ($payRow['total'] ?? 0);
            $recentPayments = $pdo->query(
                'SELECT p.id, p.amount, p.method, p.paid_at, p.invoice_id, i.invoice_number, c.name AS customer_name
                 FROM payments p
                 LEFT JOIN invoices i ON i.id = p.invoice_id
                 LEFT JOIN customers c ON c.id = i.customer_id
                 ORDER BY p.id DESC LIMIT 8'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            vk_perf_mark_query();
        } catch (Throwable) {
        }
    }

    try {
        $recentCustomers = $pdo->query(
            'SELECT id, name, phone, email, created_at FROM customers ORDER BY id DESC LIMIT 8'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        vk_perf_mark_query();
    } catch (Throwable) {
    }

    // Last 6 calendar months of invoice totals (for Chart.js).
    try {
        $labels = [];
        $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime('-' . $i . ' months');
            $ym = date('Y-m', $ts !== false ? $ts : time());
            $labels[] = date('M Y', $ts !== false ? $ts : time());
            $st = $pdo->prepare(
                'SELECT COALESCE(SUM(grand_total), 0) FROM invoices
                 WHERE DATE_FORMAT(invoice_date, \'%Y-%m\') = ?'
            );
            $st->execute([$ym]);
            vk_perf_mark_query();
            $values[] = (float) $st->fetchColumn();
        }
        $charts['monthly_sales'] = ['labels' => $labels, 'values' => $values];
    } catch (Throwable) {
    }

    // Customer growth last 6 months.
    try {
        $labels = [];
        $values = [];
        for ($i = 5; $i >= 0; $i--) {
            $ts = strtotime('-' . $i . ' months');
            $ym = date('Y-m', $ts !== false ? $ts : time());
            $labels[] = date('M Y', $ts !== false ? $ts : time());
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM customers WHERE DATE_FORMAT(created_at, \'%Y-%m\') = ?'
            );
            $st->execute([$ym]);
            vk_perf_mark_query();
            $values[] = (int) $st->fetchColumn();
        }
        $charts['customer_growth'] = ['labels' => $labels, 'values' => $values];
    } catch (Throwable) {
    }

    $erp['today_activities'] = (int) $erp['quotations_today']
        + (int) $erp['invoices_today']
        + (int) $erp['payments_today_count']
        + (int) $pendingJobs;

    return [
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
            // Flattened ERP fields for metric binders
            'quotations_total' => $erp['quotations_total'],
            'quotations_pending' => $erp['quotations_pending'],
            'quotations_approved' => $erp['quotations_approved'],
            'quotations_today' => $erp['quotations_today'],
            'invoices_total' => $erp['invoices_total'],
            'invoices_today' => $erp['invoices_today'],
            'outstanding' => $erp['outstanding'],
            'products_total' => $erp['products_total'],
            'stock_items' => $erp['stock_items'],
            'low_stock' => $erp['low_stock'],
            'suppliers_total' => $erp['suppliers_total'],
            'payments_today_count' => $erp['payments_today_count'],
            'collections_today' => $erp['collections_today'],
            'today_activities' => $erp['today_activities'],
        ],
        'erp' => $erp,
        'charts' => $charts,
        'marketing' => $marketing,
        'smtp_warning' => $smtpWarning,
        'recent_web_bookings' => $recentWebBookings,
        'recent_jobs' => $recentJobs,
        'recent_quotations' => $recentQuotations,
        'recent_customers' => $recentCustomers,
        'recent_payments' => $recentPayments,
        'recent_invoices' => $recentInvoices,
        'maint_reminders' => $maintReminders,
        'emergency_bookings' => $emergencyBookings,
        'emergency_repairs' => $emergencyRepairs,
        'schema_needs_v3' => !db_table_exists($pdo, 'maintenance_contracts') || !db_table_exists($pdo, 'warranty_records'),
    ];
}
