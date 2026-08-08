<?php
declare(strict_types=1);
$pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/includes/layout_init.php';

$userDisplay = (string) (($currentUser['fullname'] ?? '') ?: ($currentUser['username'] ?? 'Administrator'));
$userRole = (string) ($currentUser['role'] ?? 'staff');
$branchName = vk_app_setting('company_name', 'VK Network ERP');
$dashLogo = function_exists('getLogo') ? getLogo('mobile') : base_url('assets/images/logo.png');

$quickActions = [
    ['title' => 'New Quotation', 'icon' => 'bi-file-earmark-plus', 'href' => BASE_URL . '/modules/quotations/create.php', 'tone' => 'blue'],
    ['title' => 'New Invoice', 'icon' => 'bi-receipt', 'href' => BASE_URL . '/modules/invoices/create.php', 'tone' => 'purple'],
    ['title' => 'Add Customer', 'icon' => 'bi-person-plus', 'href' => BASE_URL . '/modules/customers/add.php', 'tone' => 'indigo'],
    ['title' => 'Add Product', 'icon' => 'bi-box-seam', 'href' => BASE_URL . '/modules/products/add.php', 'tone' => 'teal'],
    ['title' => 'Add Service', 'icon' => 'bi-wrench-adjustable', 'href' => BASE_URL . '/modules/repairs/add.php', 'tone' => 'cyan'],
    ['title' => 'Add Supplier', 'icon' => 'bi-truck', 'href' => BASE_URL . '/modules/products/add.php', 'tone' => 'amber'],
    ['title' => 'Receive Payment', 'icon' => 'bi-cash-coin', 'href' => BASE_URL . '/modules/payments/list.php', 'tone' => 'green'],
    ['title' => 'Customer Ledger', 'icon' => 'bi-journal-text', 'href' => BASE_URL . '/modules/accounts/list.php', 'tone' => 'slate'],
    ['title' => 'Reports', 'icon' => 'bi-bar-chart-line', 'href' => BASE_URL . '/modules/quotations/reports.php', 'tone' => 'purple'],
    ['title' => 'Settings', 'icon' => 'bi-gear', 'href' => BASE_URL . '/modules/settings/index.php', 'tone' => 'slate'],
];

$kpiCards = [
    ['label' => 'Customers', 'metric' => 'total-customers', 'sub' => 'Directory', 'icon' => 'bi-people', 'tone' => 'blue', 'href' => '/modules/customers/list.php', 'spark' => 'customers'],
    ['label' => 'Quotations', 'metric' => 'quotations-total', 'sub' => 'All quotes', 'icon' => 'bi-file-earmark-text', 'tone' => 'purple', 'href' => '/modules/quotations/list.php', 'spark' => 'quotes'],
    ['label' => 'Pending Quotes', 'metric' => 'quotations-pending', 'sub' => 'Awaiting approval', 'icon' => 'bi-hourglass-split', 'tone' => 'orange', 'href' => '/modules/quotations/approval.php'],
    ['label' => 'Approved Quotes', 'metric' => 'quotations-approved', 'sub' => 'Ready to convert', 'icon' => 'bi-check2-circle', 'tone' => 'green', 'href' => '/modules/quotations/list.php?status=approved'],
    ['label' => 'Invoices', 'metric' => 'invoices-total', 'sub' => 'Billed documents', 'icon' => 'bi-receipt', 'tone' => 'blue', 'href' => '/modules/invoices/list.php'],
    ['label' => 'Total Sales', 'metric' => 'sales-month', 'sub' => 'This month', 'icon' => 'bi-graph-up-arrow', 'tone' => 'green', 'href' => '/modules/invoices/list.php', 'spark' => 'revenue', 'money' => true],
    ['label' => 'Monthly Revenue', 'metric' => 'sales-month-kpi', 'sub' => 'Invoice total', 'icon' => 'bi-currency-rupee', 'tone' => 'teal', 'href' => '/modules/invoices/list.php', 'money' => true],
    ['label' => 'Outstanding', 'metric' => 'outstanding', 'sub' => 'Receivables', 'icon' => 'bi-wallet2', 'tone' => 'red', 'href' => '/modules/accounts/list.php', 'money' => true],
    ['label' => 'Products', 'metric' => 'products-total', 'sub' => 'Catalog', 'icon' => 'bi-box-seam', 'tone' => 'teal', 'href' => '/modules/products/list.php'],
    ['label' => 'Services', 'metric' => 'total-services', 'sub' => 'Active offerings', 'icon' => 'bi-gear-wide-connected', 'tone' => 'indigo', 'href' => '/modules/web_services/gallery.php'],
    ['label' => 'Suppliers', 'metric' => 'suppliers-total', 'sub' => 'Vendors', 'icon' => 'bi-truck', 'tone' => 'orange', 'href' => '/modules/products/list.php'],
    ['label' => 'Stock Items', 'metric' => 'stock-items', 'sub' => 'Units on hand', 'icon' => 'bi-boxes', 'tone' => 'blue', 'href' => '/modules/products/list.php'],
    ['label' => 'Low Stock', 'metric' => 'low-stock', 'sub' => 'Alerts', 'icon' => 'bi-exclamation-triangle', 'tone' => 'red', 'href' => '/modules/products/list.php'],
    ['label' => "Today's Activity", 'metric' => 'today-activities', 'sub' => 'Quotes · bills · jobs', 'icon' => 'bi-lightning-charge', 'tone' => 'purple', 'href' => '/modules/bookings/list.php', 'spark' => 'activity'],
];

$cssV = (string) @filemtime(dirname(__DIR__) . '/assets/css/enterprise-dashboard.css');
$jsEntV = (string) @filemtime(dirname(__DIR__) . '/assets/js/enterprise-dashboard.js');
$extraHead = ($extraHead ?? '')
    . '<link rel="stylesheet" href="' . e(base_url('assets/css/enterprise-dashboard.css')) . '?v=' . e($cssV) . '">'
    . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>';

$extraScripts = ($extraScripts ?? '') . "\n"
    . '<script src="' . e(base_url('assets/js/dashboard-widgets.js')) . '?v=' . e(vk_asset_mtime_version('assets/js/dashboard-widgets.js')) . '" defer></script>'
    . "\n" . '<script src="' . e(base_url('assets/js/enterprise-dashboard.js')) . '?v=' . e($jsEntV) . '" defer></script>';

require_once dirname(__DIR__) . '/includes/layout_start.php';
?>
<div class="vk-dashboard-2026 vk-dash-admin vk-dash-skeleton" data-vk-dashboard="async">

<div id="vkSmtpAlerts" class="d-none mb-2" aria-live="polite"></div>
<div id="vkSchemaAlert" class="d-none mb-2"></div>
<div id="vkEmergencyPanel" class="d-none mb-2"></div>

<header class="vk-dash-exec" aria-label="Executive dashboard header">
    <div class="vk-dash-exec-brand">
        <img class="vk-dash-exec-logo" src="<?= e($dashLogo) ?>" alt="<?= e($branchName) ?>" width="40" height="40" decoding="async">
        <div class="vk-dash-exec-main">
            <p class="vk-dash-greeting" id="vkDashGreeting">Good morning</p>
            <h1 class="vk-dash-exec-title" id="dashboardTitle"><?= e($branchName) ?></h1>
            <div class="vk-dash-exec-meta">
                <span><i class="bi bi-calendar3 me-1" aria-hidden="true"></i><strong id="vkDashDate"><?= e(date('D, M j, Y')) ?></strong></span>
                <span><i class="bi bi-clock me-1" aria-hidden="true"></i><strong id="vkDashTime"><?= e(date('H:i:s')) ?></strong></span>
                <span><i class="bi bi-person-circle me-1" aria-hidden="true"></i><strong><?= e($userDisplay) ?></strong> · <?= e(ucfirst(str_replace('_', ' ', $userRole))) ?></span>
            </div>
        </div>
    </div>
    <div class="vk-dash-exec-tools">
        <form id="vkDashSearchForm" class="vk-dash-search" role="search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" id="vkDashGlobalSearch" placeholder="Search customers, invoices, repairs…" aria-label="Global dashboard search" autocomplete="off">
            <select id="vkDashSearchScope" class="visually-hidden" aria-hidden="true" tabindex="-1">
                <option value="customers">Customers</option>
                <option value="invoices">Invoices</option>
                <option value="repairs">Repairs</option>
                <option value="bookings">Bookings</option>
                <option value="products">Products</option>
                <option value="maintenance">Maintenance</option>
            </select>
        </form>
        <button type="button" class="vk-dash-icon-btn position-relative" id="vkDashNotifyBtn" aria-label="Notifications" aria-expanded="false">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span class="vk-dash-badge-dot d-none" id="vkDashNotifyDot" aria-hidden="true"></span>
        </button>
        <a class="vk-dash-icon-btn" href="<?= e(BASE_URL) ?>/modules/settings/index.php" aria-label="Profile and settings" title="Settings &amp; profile"><i class="bi bi-person-gear" aria-hidden="true"></i></a>
        <a class="vk-dash-icon-btn text-danger" href="<?= e(BASE_URL) ?>/logout.php" aria-label="Logout" title="Logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></a>
    </div>
</header>

<section class="vk-dash-today-strip" aria-label="Today snapshot">
    <div class="vk-dash-today-card">
        <span class="vk-dash-today-label">Today's sales</span>
        <strong data-vk-metric="sales-today">—</strong>
    </div>
    <div class="vk-dash-today-card">
        <span class="vk-dash-today-label">Today's quotations</span>
        <strong data-vk-metric="quotations-today">—</strong>
    </div>
    <div class="vk-dash-today-card">
        <span class="vk-dash-today-label">Today's collections</span>
        <strong data-vk-metric="collections-today">—</strong>
    </div>
    <div class="vk-dash-today-card">
        <span class="vk-dash-today-label">Outstanding balance</span>
        <strong data-vk-metric="outstanding">—</strong>
    </div>
    <div class="vk-dash-today-card">
        <span class="vk-dash-today-label">Pending approvals</span>
        <strong data-vk-metric="quotations-pending">—</strong>
    </div>
    <div class="vk-dash-today-card">
        <span class="vk-dash-today-label">System health</span>
        <strong data-vk-metric="system-pulse">Loading…</strong>
    </div>
</section>

<section class="vk-dash-kpi-grid" aria-label="Enterprise KPI metrics">
    <?php foreach ($kpiCards as $kpi): ?>
    <a class="vk-dash-kpi vk-dash-kpi-<?= e($kpi['tone']) ?>" href="<?= e(BASE_URL . $kpi['href']) ?>">
        <div class="vk-dash-kpi-icon"><i class="bi <?= e($kpi['icon']) ?>" aria-hidden="true"></i></div>
        <div class="vk-dash-kpi-body">
            <span class="vk-dash-kpi-label"><?= e($kpi['label']) ?></span>
            <span class="vk-dash-kpi-value" data-vk-metric="<?= e($kpi['metric']) ?>">—</span>
            <span class="vk-dash-kpi-sub"><?= e($kpi['sub']) ?></span>
        </div>
        <?php if (!empty($kpi['spark'])): ?>
        <svg class="vk-dash-sparkline" data-vk-spark="<?= e($kpi['spark']) ?>" viewBox="0 0 48 16" aria-hidden="true"></svg>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</section>

<section class="vk-dash-widget mb-2" data-widget-id="quick-actions" aria-label="Quick actions">
    <div class="vk-dash-widget-head">
        <h2 class="vk-dash-widget-title"><i class="bi bi-lightning-charge me-1" aria-hidden="true"></i> Quick actions</h2>
        <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse quick actions"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
    </div>
    <div class="vk-dash-widget-body">
        <div class="vk-dash-actions-grid vk-dash-actions-grid-wide">
            <?php foreach ($quickActions as $action): ?>
            <a class="vk-dash-action vk-dash-action-<?= e($action['tone']) ?>" href="<?= e($action['href']) ?>">
                <span class="vk-dash-action-icon"><i class="bi <?= e($action['icon']) ?>" aria-hidden="true"></i></span>
                <strong><?= e($action['title']) ?></strong>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="vk-dash-layout">
    <div class="vk-dash-widgets-col" id="vkDashWidgetsCol">

        <div class="vk-dash-widget" data-widget-id="schedule">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i> Calendar &amp; follow-ups</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse schedule"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
            </div>
            <div class="vk-dash-widget-body" id="vkDashSchedule"><p class="small text-muted mb-0">Loading schedule…</p></div>
        </div>

        <div class="vk-dash-widget" data-widget-id="tasks">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-list-check me-1" aria-hidden="true"></i> Tasks &amp; pipeline</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse tasks"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
            </div>
            <div class="vk-dash-widget-body">
                <div class="vk-dash-stat-grid">
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Pending jobs</div><div class="vk-dash-stat-value" data-vk-metric="pending-jobs-kpi">—</div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Repairs</div><div class="vk-dash-stat-value" data-vk-metric="repair-pipeline">—</div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Pending quotes</div><div class="vk-dash-stat-value" data-vk-metric="quotations-pending">—</div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Low stock</div><div class="vk-dash-stat-value" data-vk-metric="low-stock">—</div></div>
                </div>
                <div class="vk-dash-bar-row mt-2"><span class="vk-dash-bar-label">Workload</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-vk-dash-bar="workload" data-width="0"></div></div><span data-vk-metric="workload-completion">0%</span></div>
                <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">Repairs</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-vk-dash-bar="repairs" data-width="0"></div></div><span data-vk-metric="repair-completion-label">0%</span></div>
            </div>
        </div>

        <div class="vk-dash-widget" data-widget-id="system">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-hdd-network me-1" aria-hidden="true"></i> System health</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse system status"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
            </div>
            <div class="vk-dash-widget-body"><div class="vk-dash-status-list" id="vkDashSystemStatus"><div class="text-muted small">Checking…</div></div></div>
        </div>

        <div class="vk-dash-widget" data-widget-id="notifications-mini">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-bell me-1" aria-hidden="true"></i> Recent notifications</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse notifications"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
            </div>
            <div class="vk-dash-widget-body" id="vkDashNotifyMini"><p class="small text-muted mb-0">Loading…</p></div>
        </div>

    </div>

    <div class="vk-dash-main-col">

        <div class="vk-dash-widget" data-widget-id="analytics">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i> Sales analytics</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse analytics"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
            </div>
            <div class="vk-dash-widget-body">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="vk-dash-chart-card">
                            <h3 class="vk-dash-chart-title">Monthly sales</h3>
                            <canvas id="vkChartMonthlySales" height="160" aria-label="Monthly sales chart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="vk-dash-chart-card">
                            <h3 class="vk-dash-chart-title">Quotation status</h3>
                            <canvas id="vkChartQuoteStatus" height="160" aria-label="Quotation status chart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="vk-dash-chart-card">
                            <h3 class="vk-dash-chart-title">Revenue trend</h3>
                            <canvas id="vkChartRevenue" height="140" aria-label="Revenue trend chart"></canvas>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="vk-dash-chart-card">
                            <h3 class="vk-dash-chart-title">Customer growth</h3>
                            <canvas id="vkChartCustomers" height="140" aria-label="Customer growth chart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="vk-dash-widget" data-widget-id="recent-erp">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-clock-history me-1" aria-hidden="true"></i> Recent activity</h2>
                <div class="vk-dash-tabs" role="tablist" aria-label="Recent activity tabs">
                    <button type="button" class="vk-dash-tab is-active" data-vk-tab="quotations" role="tab" aria-selected="true">Quotations</button>
                    <button type="button" class="vk-dash-tab" data-vk-tab="customers" role="tab" aria-selected="false">Customers</button>
                    <button type="button" class="vk-dash-tab" data-vk-tab="payments" role="tab" aria-selected="false">Payments</button>
                    <button type="button" class="vk-dash-tab" data-vk-tab="invoices" role="tab" aria-selected="false">Invoices</button>
                    <button type="button" class="vk-dash-tab" data-vk-tab="jobs" role="tab" aria-selected="false">Jobs</button>
                </div>
            </div>
            <div class="vk-dash-widget-body p-0">
                <div class="vk-dash-table-wrap table-responsive">
                    <table class="table vk-dash-table table-hover table-sm mb-0">
                        <thead class="table-light"><tr id="vkDashRecentHead"><th>Ref</th><th>Party</th><th>Status</th><th>When</th><th></th></tr></thead>
                        <tbody id="vkDashRecentBody"><tr><td colspan="5" class="text-center text-muted py-4">Loading activity…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="vk-dash-widget" data-widget-id="maint-reminders">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-wrench me-1" aria-hidden="true"></i> Upcoming follow-ups (14 days)</h2>
                <a class="small text-decoration-none" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">View all</a>
            </div>
            <div class="vk-dash-widget-body p-0">
                <div class="vk-dash-table-wrap">
                    <table class="table vk-dash-table table-sm mb-0">
                        <thead class="table-light"><tr><th>Contract</th><th>Customer</th><th>Next</th></tr></thead>
                        <tbody data-vk-table="maint-reminders">
                            <tr><td colspan="3" class="text-center text-muted py-3">Loading reminders…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="vk-dash-widget" data-widget-id="recent-bookings" data-vk-section="recent-bookings">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-inbox me-1" aria-hidden="true"></i> Recent web bookings</h2>
                <a class="small text-decoration-none" href="<?= e(BASE_URL) ?>/modules/bookings/list.php">View all</a>
            </div>
            <div class="vk-dash-widget-body p-0">
                <div class="vk-dash-table-wrap table-responsive">
                    <table class="table vk-dash-table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr><th>Booking</th><th>Customer</th><th>Service</th><th>Status</th><th>When</th><th></th></tr>
                        </thead>
                        <tbody data-vk-table="recent-bookings">
                            <tr><td colspan="6" class="text-center text-muted py-4">Loading bookings…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="vk-dash-notify-backdrop" id="vkDashNotifyBackdrop" aria-hidden="true"></div>
<aside class="vk-dash-notify-panel" id="vkDashNotifyPanel" aria-hidden="true" aria-label="Notification center">
    <div class="vk-dash-notify-head">
        <h2 class="h6 mb-0 fw-bold">Notifications <span class="badge rounded-pill bg-danger ms-1" id="vkDashNotifyCount">0</span></h2>
        <button type="button" class="vk-dash-icon-btn" id="vkDashNotifyClose" aria-label="Close notifications"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="vk-dash-notify-scroll" id="vkDashNotifyList"><p class="small text-muted">Loading…</p></div>
</aside>

</div>
<?php require_once dirname(__DIR__) . '/includes/layout_end.php'; ?>
