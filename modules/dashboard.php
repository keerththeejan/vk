<?php
declare(strict_types=1);
$pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/includes/layout_start.php';

$alertDays = defined('WARRANTY_ALERT_DAYS') ? (int) WARRANTY_ALERT_DAYS : 30;
$today = date('Y-m-d');
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
$workloadCompletion = 0;
$repairCompletion = 0;
$criticalCount = 0;
$systemPulse = 'Loading…';
$schemaNeedsV3 = false;
$seoAverage = 0;
$marketingMetrics = ['reach' => 0, 'active_campaigns' => 0, 'leads' => 0, 'conversion_rate' => 0, 'whatsapp_delivery_rate' => 0];
$extraScripts = ($extraScripts ?? '') . "\n" . '<script src="' . e(base_url('assets/js/dashboard-widgets.js')) . '?v=' . e(vk_asset_mtime_version('assets/js/dashboard-widgets.js')) . '" defer></script>';

$quickActions = [
    ['title' => 'New Repair Job', 'desc' => 'Open a diagnostics workflow', 'icon' => 'bi-tools', 'href' => BASE_URL . '/modules/repairs/add.php', 'tone' => 'blue'],
    ['title' => 'Maintenance Contract', 'desc' => 'Create recurring service coverage', 'icon' => 'bi-calendar-check', 'href' => BASE_URL . '/modules/maintenance/add.php', 'tone' => 'green'],
    ['title' => 'Create Invoice', 'desc' => 'Bill jobs, products, or services', 'icon' => 'bi-receipt', 'href' => BASE_URL . '/modules/invoices/create.php', 'tone' => 'purple'],
    ['title' => 'Add Warranty', 'desc' => 'Track coverage and expiry alerts', 'icon' => 'bi-shield-plus', 'href' => BASE_URL . '/modules/warranties/add.php', 'tone' => 'amber'],
    ['title' => 'Add Customer', 'desc' => 'Register a new customer profile', 'icon' => 'bi-person-plus', 'href' => BASE_URL . '/modules/customers/add.php', 'tone' => 'indigo'],
    ['title' => 'CCTV Job', 'desc' => 'Schedule install or field service', 'icon' => 'bi-camera-video', 'href' => BASE_URL . '/modules/cctv/add.php', 'tone' => 'teal'],
    ['title' => 'Web Bookings', 'desc' => 'Review online customer requests', 'icon' => 'bi-inbox', 'href' => BASE_URL . '/modules/bookings/list.php', 'tone' => 'cyan'],
    ['title' => 'System Settings', 'desc' => 'Configure business operations', 'icon' => 'bi-gear-wide-connected', 'href' => BASE_URL . '/modules/settings/index.php', 'tone' => 'slate'],
    ['title' => 'SEO Studio', 'desc' => 'Tune metadata and search readiness', 'icon' => 'bi-search-heart', 'href' => BASE_URL . '/modules/seo/index.php', 'tone' => 'indigo'],
    ['title' => 'Marketing Hub', 'desc' => 'Launch campaigns and track leads', 'icon' => 'bi-megaphone', 'href' => BASE_URL . '/modules/marketing/index.php', 'tone' => 'purple'],
    ['title' => 'WhatsApp Automation', 'desc' => 'Monitor templates and delivery', 'icon' => 'bi-whatsapp', 'href' => BASE_URL . '/modules/whatsapp/index.php', 'tone' => 'green'],
    ['title' => 'Public Website', 'desc' => 'Open the customer-facing site', 'icon' => 'bi-globe2', 'href' => BASE_URL . '/index.php', 'tone' => 'blue', 'target' => '_blank'],
];
?>
<div class="vk-dashboard-2026" data-vk-dashboard="async">
<div id="vkSmtpAlerts" class="d-none" aria-live="polite"></div>
<div id="vkEmergencyPanel" class="d-none"></div>
<div id="vkSchemaAlert" class="d-none"></div>

<section class="vk-hero-panel mb-4" aria-labelledby="dashboardTitle">
    <div class="vk-hero-copy">
        <div class="vk-eyebrow"><span class="vk-live-dot"></span>Enterprise command center</div>
        <h1 id="dashboardTitle">Service operations cockpit</h1>
        <p>Repair, CCTV, maintenance, bookings, finance, warranty, and technician signals in one executive workspace.</p>
        <div class="vk-hero-actions">
            <a class="btn btn-primary btn-lg" href="<?= e(BASE_URL) ?>/modules/repairs/add.php"><i class="bi bi-plus-lg me-2"></i>New job</a>
            <a class="btn btn-outline-light btn-lg" href="<?= e(BASE_URL) ?>/modules/bookings/list.php"><i class="bi bi-inbox me-2"></i>Review bookings</a>
        </div>
    </div>
    <div class="vk-hero-intel" aria-label="Dashboard summary">
        <div>
            <span>System pulse</span>
            <strong data-vk-metric="system-pulse"><?= e($systemPulse) ?></strong>
        </div>
        <div>
            <span>Monthly revenue</span>
            <strong data-vk-metric="sales-month"><?= e(number_format($salesMonth, 2)) ?></strong>
        </div>
        <div>
            <span>Completion rate</span>
            <strong data-vk-metric="workload-completion"><?= e((string) $workloadCompletion) ?>%</strong>
        </div>
    </div>
</section>

<section class="vk-command-grid mb-4" aria-label="Operations summary">
    <div class="vk-command-card">
        <div class="vk-command-icon"><i class="bi bi-activity"></i></div>
        <div>
            <span>Live workload</span>
            <strong data-vk-metric="pending-jobs"><?= e((string) $pendingJobs) ?> active jobs</strong>
        </div>
        <div class="vk-mini-bars" aria-hidden="true"><span style="height: 70%"></span><span style="height: 42%"></span><span style="height: 86%"></span><span style="height: 58%"></span></div>
    </div>
    <div class="vk-command-card">
        <div class="vk-command-icon"><i class="bi bi-cpu"></i></div>
        <div>
            <span>Repair throughput</span>
            <strong data-vk-metric="repair-finished"><?= e((string) ($repairCompleted + $repairDelivered)) ?> finished</strong>
        </div>
        <div class="progress vk-progress"><div class="progress-bar" data-vk-metric-bar="repair-completion" style="width: <?= (int) $repairCompletion ?>%"></div></div>
    </div>
    <div class="vk-command-card">
        <div class="vk-command-icon"><i class="bi bi-broadcast-pin"></i></div>
        <div>
            <span>Priority queue</span>
            <strong data-vk-metric="critical-count"><?= e((string) $criticalCount) ?> alerts</strong>
        </div>
        <span class="vk-status-pill <?= $criticalCount > 0 ? 'vk-pill-hot' : 'vk-pill-calm' ?>"><?= $criticalCount > 0 ? 'Review' : 'Stable' ?></span>
    </div>
</section>

<section class="vk-growth-grid mb-4" aria-label="Growth and automation summary">
    <a class="vk-growth-card" href="<?= e(BASE_URL) ?>/modules/seo/index.php">
        <span><i class="bi bi-search-heart"></i></span>
        <div><small>SEO score</small><strong data-vk-metric="seo-average"><?= e((string) $seoAverage) ?>%</strong><em>Metadata, sitemap, schema</em></div>
    </a>
    <a class="vk-growth-card" href="<?= e(BASE_URL) ?>/modules/marketing/index.php">
        <span><i class="bi bi-megaphone"></i></span>
        <div><small>Marketing reach</small><strong data-vk-metric="marketing-reach"><?= e(number_format((int) $marketingMetrics['reach'])) ?></strong><em><span data-vk-metric="marketing-campaigns"><?= e((string) $marketingMetrics['active_campaigns']) ?></span> active campaigns</em></div>
    </a>
    <a class="vk-growth-card" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php">
        <span><i class="bi bi-funnel"></i></span>
        <div><small>Lead pipeline</small><strong data-vk-metric="marketing-leads"><?= e((string) $marketingMetrics['leads']) ?></strong><em><span data-vk-metric="marketing-conversion"><?= e((string) $marketingMetrics['conversion_rate']) ?></span>% conversion rate</em></div>
    </a>
    <a class="vk-growth-card" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php">
        <span><i class="bi bi-whatsapp"></i></span>
        <div><small>WhatsApp automation</small><strong data-vk-metric="marketing-whatsapp"><?= e((string) $marketingMetrics['whatsapp_delivery_rate']) ?>%</strong><em>Delivery performance</em></div>
    </a>
</section>

<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card vk-card vk-kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="vk-stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar2-check"></i></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="text-muted small">Total bookings</div>
                    <div class="fs-4 fw-semibold" data-vk-metric="total-bookings"><?= e((string) $totalBookings) ?></div>
                    <div class="small text-muted">Web · <span data-vk-metric="total-services"><?= e((string) $totalServices) ?></span> active services</div>
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
                    <div class="fs-4 fw-semibold" data-vk-metric="completed-jobs"><?= e((string) $completedJobs) ?></div>
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
                    <div class="fs-4 fw-semibold" data-vk-metric="pending-jobs-kpi"><?= e((string) $pendingJobs) ?></div>
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
                    <div class="fs-4 fw-semibold" data-vk-metric="active-technicians"><?= e((string) $activeTechnicians) ?></div>
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
                    <div class="fs-4 fw-semibold" data-vk-metric="repair-pipeline"><?= e((string) $repairPipeline) ?></div>
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
                    <div class="fs-5 fw-semibold" data-vk-metric="repair-done-total"><?= e((string) ($repairCompleted + $repairDelivered)) ?></div>
                    <div class="small text-muted">Done <span data-vk-metric="repair-completed"><?= e((string) $repairCompleted) ?></span> · Out <span data-vk-metric="repair-delivered"><?= e((string) $repairDelivered) ?></span></div>
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
                    <div class="fs-5 fw-semibold" data-vk-metric="cctv-total"><?= e((string) ($cctvActive + $cctvDone)) ?></div>
                    <div class="small text-muted">Active <span data-vk-metric="cctv-active"><?= e((string) $cctvActive) ?></span> · Done <span data-vk-metric="cctv-done"><?= e((string) $cctvDone) ?></span></div>
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
                    <div class="fs-5 fw-semibold" data-vk-metric="sales-today"><?= e(number_format($salesToday, 2)) ?></div>
                    <div class="small text-muted">Month: <span data-vk-metric="sales-month-kpi"><?= e(number_format($salesMonth, 2)) ?></span></div>
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
                <div class="fs-3 fw-bold" data-vk-metric="active-contracts"><?= e((string) $activeContracts) ?></div>
                <a class="small" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php?status=active">View contracts</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4">
        <div class="card vk-card h-100 <?= $warrantyExpiring > 0 ? 'border-warning' : '' ?>">
            <div class="card-body">
                <div class="text-muted small">Warranties expiring (<?= (int) $alertDays ?> days)</div>
                <div class="fs-3 fw-bold <?= $warrantyExpiring > 0 ? 'text-warning' : '' ?>" data-vk-metric="warranty-expiring"><?= e((string) $warrantyExpiring) ?></div>
                <a class="small" href="<?= e(BASE_URL) ?>/modules/warranties/list.php?filter=expiring">Review list</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4">
        <div class="card vk-card h-100">
            <div class="card-body">
                <div class="text-muted small">Customers</div>
                <div class="fs-3 fw-bold" data-vk-metric="total-customers"><?= e((string) $totalCustomers) ?></div>
                <a class="small" href="<?= e(BASE_URL) ?>/modules/customers/list.php">Directory</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card vk-card h-100">
            <div class="card-header bg-transparent fw-semibold d-flex align-items-center justify-content-between">
                <span>Quick actions</span>
                <span class="vk-status-pill vk-pill-calm">Fast lane</span>
            </div>
            <div class="card-body">
                <div class="vk-action-grid">
                    <?php foreach ($quickActions as $action): ?>
                        <a class="vk-action-tile vk-action-<?= e($action['tone']) ?>" href="<?= e($action['href']) ?>" <?= !empty($action['target']) ? 'target="_blank" rel="noopener"' : '' ?>>
                            <span class="vk-action-icon"><i class="bi <?= e($action['icon']) ?>"></i></span>
                            <span>
                                <strong><?= e($action['title']) ?></strong>
                                <small><?= e($action['desc']) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
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
                        <tbody data-vk-table="maint-reminders">
                            <tr><td colspan="3" class="text-center text-muted py-3">Loading reminders…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card vk-card mb-3" data-vk-section="recent-bookings">
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
                        <tbody data-vk-table="recent-bookings">
                            <tr><td colspan="6" class="text-center text-muted py-4">Loading bookings…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

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
                        <tbody data-vk-table="recent-jobs">
                            <tr><td colspan="6" class="text-center text-muted py-4">Loading jobs…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<?php require_once dirname(__DIR__) . '/includes/layout_end.php'; ?>
