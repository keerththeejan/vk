<?php
declare(strict_types=1);
$pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/includes/layout_start.php';

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
$recentJobs = [];

$totalBookings = 0;
$totalServices = 0;
$completedJobs = 0;
$pendingJobs = 0;
$activeTechnicians = 0;
$recentWebBookings = [];

try {
    $st = $pdo->prepare('SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE invoice_date = ?');
    $st->execute([$today]);
    $salesToday = (float) $st->fetchColumn();

    $st = $pdo->prepare('SELECT COALESCE(SUM(grand_total),0) FROM invoices WHERE invoice_date >= ? AND invoice_date <= ?');
    $st->execute([$monthStart, date('Y-m-t')]);
    $salesMonth = (float) $st->fetchColumn();
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('dashboard invoices: ' . $e->getMessage());
    }
}

try {
    $totalCustomers = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('dashboard customers: ' . $e->getMessage());
    }
}

try {
    $repairPipeline = (int) $pdo->query(
        "SELECT COUNT(*) FROM repair_jobs WHERE status IN ('pending','diagnosing','in_progress')"
    )->fetchColumn();
    $repairCompleted = (int) $pdo->query(
        "SELECT COUNT(*) FROM repair_jobs WHERE status = 'completed'"
    )->fetchColumn();
    $repairDelivered = (int) $pdo->query(
        "SELECT COUNT(*) FROM repair_jobs WHERE status = 'delivered'"
    )->fetchColumn();
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('dashboard repairs: ' . $e->getMessage());
    }
}

try {
    $cctvActive = (int) $pdo->query(
        "SELECT COUNT(*) FROM cctv_installations WHERE status IN ('pending','in_progress')"
    )->fetchColumn();
    $cctvDone = (int) $pdo->query(
        "SELECT COUNT(*) FROM cctv_installations WHERE status IN ('completed','delivered')"
    )->fetchColumn();
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('dashboard cctv: ' . $e->getMessage());
    }
}

try {
    $completedJobs = (int) $pdo->query(
        "SELECT COUNT(*) FROM repair_jobs WHERE status IN ('completed','delivered')"
    )->fetchColumn();
    $completedJobs += (int) $pdo->query(
        "SELECT COUNT(*) FROM cctv_installations WHERE status IN ('completed','delivered')"
    )->fetchColumn();

    $pendingJobs = (int) $pdo->query(
        "SELECT COUNT(*) FROM repair_jobs WHERE status IN ('pending','diagnosing','in_progress')"
    )->fetchColumn();
    $pendingJobs += (int) $pdo->query(
        "SELECT COUNT(*) FROM cctv_installations WHERE status IN ('pending','in_progress')"
    )->fetchColumn();
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('dashboard job totals: ' . $e->getMessage());
    }
}

try {
    $activeTechnicians = (int) $pdo->query(
        'SELECT COUNT(*) FROM technicians WHERE active = 1'
    )->fetchColumn();
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('dashboard technicians: ' . $e->getMessage());
    }
}

if (db_table_exists($pdo, 'web_bookings')) {
    try {
        $totalBookings = (int) $pdo->query('SELECT COUNT(*) FROM web_bookings')->fetchColumn();
        $recentWebBookings = $pdo->query(
            'SELECT id, booking_number, customer_name, phone, status, service_type, created_at
             FROM web_bookings ORDER BY created_at DESC LIMIT 8'
        )->fetchAll();
    } catch (Throwable $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('dashboard web_bookings: ' . $e->getMessage());
        }
    }
}

if (db_table_exists($pdo, 'web_services')) {
    try {
        $totalServices = (int) $pdo->query(
            'SELECT COUNT(*) FROM web_services WHERE active = 1'
        )->fetchColumn();
    } catch (Throwable $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('dashboard web_services: ' . $e->getMessage());
        }
    }
}

$hasMaintContracts = db_table_exists($pdo, 'maintenance_contracts');
$hasWarrantyRecords = db_table_exists($pdo, 'warranty_records');

$activeContracts = 0;
$warrantyExpiring = 0;
$maintReminders = [];

if ($hasMaintContracts) {
    try {
        $activeContracts = (int) $pdo->query("SELECT COUNT(*) FROM maintenance_contracts WHERE status = 'active'")->fetchColumn();
        $maintReminders = $pdo->query(
            "SELECT m.contract_number, m.title, m.next_service_date, c.name AS customer_name
             FROM maintenance_contracts m
             JOIN customers c ON c.id = m.customer_id
             WHERE m.status = 'active' AND m.next_service_date IS NOT NULL AND m.next_service_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             ORDER BY m.next_service_date ASC
             LIMIT 8"
        )->fetchAll();
    } catch (Throwable $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('dashboard maintenance: ' . $e->getMessage());
        }
    }
}

if ($hasWarrantyRecords) {
    try {
        $warrantyExpiring = (int) $pdo->query(
            'SELECT COUNT(*) FROM warranty_records WHERE end_date >= CURDATE() AND end_date <= DATE_ADD(CURDATE(), INTERVAL ' . $alertDays . ' DAY)'
        )->fetchColumn();
    } catch (Throwable $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('dashboard warranty: ' . $e->getMessage());
        }
    }
}

try {
    $recentJobs = $pdo->query(
        'SELECT * FROM (
            SELECT r.id, r.job_number AS ref, \'repair\' AS job_type, r.status, r.created_at, c.name AS customer_name
            FROM repair_jobs r JOIN customers c ON c.id = r.customer_id
            UNION ALL
            SELECT v.id, v.job_number, \'cctv\', v.status, v.created_at, c.name
            FROM cctv_installations v JOIN customers c ON c.id = v.customer_id
        ) u
        ORDER BY created_at DESC
        LIMIT 12'
    )->fetchAll();
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('dashboard recent jobs: ' . $e->getMessage());
    }
}

$schemaNeedsV3 = !$hasMaintContracts || !$hasWarrantyRecords;
$smtpCfg = vk_smtp_settings_get($pdo);

$emergencyBookings = [];
$emergencyRepairs = [];
if (db_table_exists($pdo, 'web_bookings') && db_column_exists($pdo, 'web_bookings', 'is_emergency')) {
    try {
        $ebCols = 'id, booking_number, customer_name, phone, service_type, created_at';
        if (db_column_exists($pdo, 'web_bookings', 'latitude')) {
            $ebCols .= ', latitude, longitude';
        }
        $emergencyBookings = $pdo->query(
            "SELECT {$ebCols} FROM web_bookings
             WHERE is_emergency = 1 AND status IN ('pending','in_progress') ORDER BY id DESC LIMIT 12"
        )->fetchAll();
    } catch (Throwable $e) {
        $emergencyBookings = [];
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
    } catch (Throwable $e) {
        $emergencyRepairs = [];
    }
}
?>
<?php if (!($smtpCfg['configured'] ?? false)): ?>
<div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="bi bi-envelope-exclamation me-2"></i>Email system not configured. Registration and booking emails may fail.</span>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm btn-outline-warning" href="<?= e(BASE_URL) ?>/modules/settings/index.php#pane-mail">Open Email Settings</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/settings/email.php">Email &amp; Inbox</a>
    </div>
</div>
<?php
elseif (
    trim((string) ($smtpCfg['smtp_pass'] ?? '')) === ''
    && !vk_smtp_env_key_set('VK_SMTP_PASS')
    && vk_smtp_env_value('MAIL_PASSWORD') === null
): ?>
<div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 small">
    <span><i class="bi bi-key me-2"></i>SMTP host and sender are set, but no password is stored. Add the mailbox password under <strong>Email</strong> settings, or set <code>VK_SMTP_PASS</code> / <code>MAIL_PASSWORD</code> in <code>.env</code>, or sending will fail.</span>
    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/settings/index.php#pane-mail">Add password</a>
</div>
<?php endif; ?>
<?php if ($emergencyBookings || $emergencyRepairs): ?>
<div class="card border-danger mb-3 shadow-sm">
    <div class="card-header bg-danger text-white fw-semibold"><i class="bi bi-exclamation-octagon me-2"></i>Emergency &amp; high priority</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Type</th><th>Ref</th><th>Customer</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($emergencyBookings as $eb): ?>
                    <tr class="table-danger">
                        <td>Booking</td>
                        <td><code><?= e($eb['booking_number']) ?></code></td>
                        <td><?= e($eb['customer_name']) ?> · <?= e($eb['phone']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php $waEb = vk_whatsapp_me_link((string) $eb['phone'], vk_whatsapp_web_booking_message($eb)); ?>
                            <a class="btn btn-sm btn-success me-1" href="<?= e($waEb) ?>" target="_blank" rel="noopener noreferrer" title="WhatsApp customer"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>
                            <a class="btn btn-sm btn-dark" href="<?= e(BASE_URL) ?>/modules/bookings/view.php?id=<?= (int) $eb['id'] ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($emergencyRepairs as $er): ?>
                    <tr class="table-warning">
                        <td>Repair job</td>
                        <td><code><?= e($er['job_number']) ?></code></td>
                        <td><?= e($er['customer_name']) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-warning text-dark" href="<?= e(BASE_URL) ?>/modules/repairs/view.php?id=<?= (int) $er['id'] ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if ($schemaNeedsV3): ?>
<div class="alert alert-warning d-flex flex-column flex-md-row align-items-start gap-2 mb-3" role="alert">
    <div><i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Database update needed.</strong>
        Maintenance and warranty features require v3 tables. Import <code>sql/upgrade_v3_maintenance.sql</code> into <code>vk_billing</code> (backup first), or reinstall from <code>sql/install.sql</code> on a fresh database.</div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar2-check"></i></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="text-muted small">Total bookings</div>
                    <div class="fs-4 fw-semibold"><?= e((string) $totalBookings) ?></div>
                    <div class="small text-muted">Web · <?= e((string) $totalServices) ?> active services</div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-top-0 pt-0 small">
                <a href="<?= e(BASE_URL) ?>/modules/bookings/list.php" class="text-decoration-none">Open bookings</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-all"></i></div>
                <div>
                    <div class="text-muted small">Completed jobs</div>
                    <div class="fs-4 fw-semibold"><?= e((string) $completedJobs) ?></div>
                    <div class="small text-muted">Repairs + CCTV (done / delivered)</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                <div>
                    <div class="text-muted small">Pending jobs</div>
                    <div class="fs-4 fw-semibold"><?= e((string) $pendingJobs) ?></div>
                    <div class="small text-muted">In pipeline (repair + CCTV)</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-person-badge"></i></div>
                <div>
                    <div class="text-muted small">Active technicians</div>
                    <div class="fs-4 fw-semibold"><?= e((string) $activeTechnicians) ?></div>
                    <div class="small text-muted"><a href="<?= e(BASE_URL) ?>/modules/technicians/list.php" class="text-decoration-none">Manage team</a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-wrench-adjustable"></i></div>
                <div>
                    <div class="text-muted small">Repair pipeline</div>
                    <div class="fs-4 fw-semibold"><?= e((string) $repairPipeline) ?></div>
                    <div class="small text-muted">Pending / diagnosing / in progress</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="text-muted small">Repairs completed / delivered</div>
                    <div class="fs-5 fw-semibold"><?= e((string) ($repairCompleted + $repairDelivered)) ?></div>
                    <div class="small text-muted">Done <?= e((string) $repairCompleted) ?> · Out <?= e((string) $repairDelivered) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-info bg-opacity-10 text-info"><i class="bi bi-camera-video"></i></div>
                <div>
                    <div class="text-muted small">CCTV jobs</div>
                    <div class="fs-5 fw-semibold"><?= e((string) ($cctvActive + $cctvDone)) ?></div>
                    <div class="small text-muted">Active <?= e((string) $cctvActive) ?> · Done <?= e((string) $cctvDone) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-currency-dollar"></i></div>
                <div>
                    <div class="text-muted small">Sales today / month</div>
                    <div class="fs-5 fw-semibold"><?= e(number_format($salesToday, 2)) ?></div>
                    <div class="small text-muted">Month: <?= e(number_format($salesMonth, 2)) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-md-4">
        <div class="card vk-card h-100 border-primary border-opacity-25">
            <div class="card-body">
                <div class="text-muted small">Active maintenance contracts</div>
                <div class="fs-3 fw-bold"><?= e((string) $activeContracts) ?></div>
                <a class="small" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php?status=active">View contracts</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4">
        <div class="card vk-card h-100 <?= $warrantyExpiring > 0 ? 'border-warning' : '' ?>">
            <div class="card-body">
                <div class="text-muted small">Warranties expiring (<?= (int) $alertDays ?> days)</div>
                <div class="fs-3 fw-bold <?= $warrantyExpiring > 0 ? 'text-warning' : '' ?>"><?= e((string) $warrantyExpiring) ?></div>
                <a class="small" href="<?= e(BASE_URL) ?>/modules/warranties/list.php?filter=expiring">Review list</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4">
        <div class="card vk-card h-100">
            <div class="card-body">
                <div class="text-muted small">Customers</div>
                <div class="fs-3 fw-bold"><?= e((string) $totalCustomers) ?></div>
                <a class="small" href="<?= e(BASE_URL) ?>/modules/customers/list.php">Directory</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card vk-card h-100">
            <div class="card-header bg-transparent fw-semibold">Quick actions</div>
            <div class="card-body d-grid gap-2">
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/repairs/add.php"><i class="bi bi-tools me-2"></i>New repair job</a>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/maintenance/add.php"><i class="bi bi-calendar-check me-2"></i>New maintenance contract</a>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><i class="bi bi-receipt me-2"></i>New invoice</a>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/warranties/add.php"><i class="bi bi-shield-check me-2"></i>Add warranty record</a>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/customers/add.php"><i class="bi bi-person-plus me-2"></i>Add customer</a>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/cctv/add.php"><i class="bi bi-camera-video me-2"></i>New CCTV job</a>
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/bookings/list.php"><i class="bi bi-inbox me-2"></i>Web bookings</a>
                <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/settings/index.php"><i class="bi bi-gear-wide-connected me-2"></i>System Settings</a>
                <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="<?= e(BASE_URL) ?>/index.php"><i class="bi bi-globe2 me-2"></i>Public website</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Maintenance reminders (next 14 days)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Contract</th><th>Customer</th><th>Next</th></tr></thead>
                        <tbody>
                        <?php if (!$maintReminders): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">No upcoming dates in this window.</td></tr>
                        <?php else: ?>
                            <?php foreach ($maintReminders as $m): ?>
                                <?php $due = $m['next_service_date'] <= $today; ?>
                                <tr class="<?= $due ? 'table-warning' : '' ?>">
                                    <td><code><?= e($m['contract_number']) ?></code><div class="small text-muted"><?= e($m['title']) ?></div></td>
                                    <td><?= e($m['customer_name']) ?></td>
                                    <td><span class="badge text-bg-<?= $due ? 'warning text-dark' : 'secondary' ?>"><?= e($m['next_service_date']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (db_table_exists($pdo, 'web_bookings')): ?>
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">Recent web bookings</span>
                <a class="small" href="<?= e(BASE_URL) ?>/modules/bookings/list.php">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive table-responsive-stack">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Booking</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>When</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$recentWebBookings): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No bookings yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentWebBookings as $wb): ?>
                                <tr>
                                    <td><code><?= e((string) ($wb['booking_number'] ?? '')) ?></code></td>
                                    <td><?= e((string) ($wb['customer_name'] ?? '')) ?><div class="small text-muted"><?= e((string) ($wb['phone'] ?? '')) ?></div></td>
                                    <td><?= e(str_replace('_', ' ', (string) ($wb['service_type'] ?? ''))) ?></td>
                                    <td><span class="badge text-bg-secondary"><?= e(str_replace('_', ' ', (string) ($wb['status'] ?? ''))) ?></span></td>
                                    <td><?= e(substr((string) ($wb['created_at'] ?? ''), 0, 16)) ?></td>
                                    <td class="text-end">
                                        <?php if (!empty($wb['id'])): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/bookings/view.php?id=<?= (int) $wb['id'] ?>">View</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card vk-card h-100">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">Recent service jobs</span>
                <div class="d-flex gap-2 small">
                    <a href="<?= e(BASE_URL) ?>/modules/repairs/list.php">Repairs</a>
                    <span class="text-muted">|</span>
                    <a href="<?= e(BASE_URL) ?>/modules/cctv/list.php">CCTV</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive table-responsive-stack">
                    <table class="table table-hover table-sm mb-0 sortable">
                        <thead class="table-light">
                            <tr>
                                <th data-sort="0">Type</th>
                                <th data-sort="1">Job</th>
                                <th data-sort="2">Customer</th>
                                <th data-sort="3">Status</th>
                                <th data-sort="4">When</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$recentJobs): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No jobs yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentJobs as $r): ?>
                                <?php
                                $bc = $r['job_type'] === 'cctv'
                                    ? 'info'
                                    : repair_status_badge_class((string) $r['status']);
                                ?>
                                <tr>
                                    <td><span class="badge text-bg-<?= $r['job_type'] === 'cctv' ? 'info' : 'secondary' ?>"><?= e(strtoupper($r['job_type'])) ?></span></td>
                                    <td><code><?= e($r['ref']) ?></code></td>
                                    <td><?= e($r['customer_name']) ?></td>
                                    <td><span class="badge text-bg-<?= e($bc) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
                                    <td><?= e(substr((string) $r['created_at'], 0, 16)) ?></td>
                                    <td class="text-end">
                                        <?php if ($r['job_type'] === 'cctv'): ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/cctv/view.php?id=<?= (int) $r['id'] ?>">View</a>
                                        <?php else: ?>
                                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/repairs/view.php?id=<?= (int) $r['id'] ?>">View</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/includes/layout_end.php'; ?>
