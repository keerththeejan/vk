<?php
declare(strict_types=1);
$pageTitle = 'Dashboard';
require_once dirname(__DIR__) . '/includes/layout_init.php';

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

$quickActions = [
    ['title' => 'New Repair Job', 'desc' => 'Open a diagnostics workflow', 'icon' => 'bi-tools', 'href' => BASE_URL . '/modules/repairs/add.php', 'tone' => 'blue'],
    ['title' => 'Maintenance Contract', 'desc' => 'Create recurring service coverage', 'icon' => 'bi-calendar-check', 'href' => BASE_URL . '/modules/maintenance/add.php', 'tone' => 'green'],
    ['title' => 'Create Quotation', 'desc' => 'Professional quote with approval workflow', 'icon' => 'bi-file-earmark-ruled', 'href' => BASE_URL . '/modules/quotations/create.php', 'tone' => 'blue'],
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

$userDisplay = (string) (($currentUser['fullname'] ?? '') ?: ($currentUser['username'] ?? 'Administrator'));
$userRole = (string) ($currentUser['role'] ?? 'staff');
$branchName = vk_app_setting('company_name', 'VK Network ERP');

$cssV = (string) @filemtime(dirname(__DIR__) . '/assets/css/enterprise-dashboard.css');
$jsEntV = (string) @filemtime(dirname(__DIR__) . '/assets/js/enterprise-dashboard.js');
$extraHead = ($extraHead ?? '') . '<link rel="stylesheet" href="' . e(base_url('assets/css/enterprise-dashboard.css')) . '?v=' . e($cssV) . '" media="print" onload="this.media=\'all\'">'
    . '<noscript><link rel="stylesheet" href="' . e(base_url('assets/css/enterprise-dashboard.css')) . '?v=' . e($cssV) . '"></noscript>';

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
    <div class="vk-dash-exec-main">
        <p class="vk-dash-greeting" id="vkDashGreeting">Good morning</p>
        <h1 class="vk-dash-exec-title" id="dashboardTitle"><?= e($branchName) ?></h1>
        <div class="vk-dash-exec-meta">
            <span><i class="bi bi-calendar3 me-1"></i><strong id="vkDashDate"><?= e(date('D, M j, Y')) ?></strong></span>
            <span><i class="bi bi-clock me-1"></i><strong id="vkDashTime"><?= e(date('H:i:s')) ?></strong></span>
            <span><i class="bi bi-person-circle me-1"></i><strong><?= e($userDisplay) ?></strong> · <?= e(ucfirst(str_replace('_', ' ', $userRole))) ?></span>
            <span><i class="bi bi-building me-1"></i><?= e($branchName) ?></span>
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
        <span class="vk-dash-weather" title="Weather widget"><i class="bi bi-cloud-sun"></i> 28°C · Clear</span>
        <button type="button" class="vk-dash-icon-btn position-relative" id="vkDashNotifyBtn" aria-label="Notifications" aria-expanded="false">
            <i class="bi bi-bell"></i>
            <span class="vk-dash-badge-dot" id="vkDashNotifyDot" aria-hidden="true"></span>
        </button>
        <a class="vk-dash-icon-btn" href="<?= e(BASE_URL) ?>/modules/settings/index.php" aria-label="Profile and settings" data-bs-toggle="tooltip" title="Settings &amp; profile"><i class="bi bi-person-gear"></i></a>
    </div>
</header>

<section class="vk-dash-command" aria-label="Operations command strip">
    <div class="vk-dash-command-card">
        <div class="vk-dash-command-icon"><i class="bi bi-activity"></i></div>
        <div class="vk-dash-command-body">
            <span>Live workload</span>
            <strong data-vk-metric="pending-jobs"><?= e((string) $pendingJobs) ?> active jobs</strong>
        </div>
        <div class="vk-dash-mini-bars" aria-hidden="true"><span style="height:70%"></span><span style="height:42%"></span><span style="height:86%"></span><span style="height:58%"></span></div>
    </div>
    <div class="vk-dash-command-card">
        <div class="vk-dash-command-icon"><i class="bi bi-cpu"></i></div>
        <div class="vk-dash-command-body">
            <span>Repair throughput</span>
            <strong data-vk-metric="repair-finished"><?= e((string) ($repairCompleted + $repairDelivered)) ?> finished</strong>
        </div>
        <div class="vk-dash-progress" aria-hidden="true"><div class="vk-dash-progress-bar" data-vk-metric-bar="repair-completion" style="width:<?= (int) $repairCompletion ?>%"></div></div>
    </div>
    <div class="vk-dash-command-card">
        <div class="vk-dash-command-icon"><i class="bi bi-broadcast-pin"></i></div>
        <div class="vk-dash-command-body">
            <span>System pulse</span>
            <strong data-vk-metric="system-pulse"><?= e($systemPulse) ?></strong>
        </div>
        <span class="vk-status-pill <?= $criticalCount > 0 ? 'vk-pill-hot' : 'vk-pill-calm' ?>"><?= $criticalCount > 0 ? 'Review' : 'Stable' ?></span>
    </div>
</section>

<section class="vk-dash-kpi-grid" aria-label="Enterprise KPI metrics">
    <a class="vk-dash-kpi vk-dash-kpi-blue" href="<?= e(BASE_URL) ?>/modules/customers/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-people"></i></div>
        <div class="vk-dash-kpi-body">
            <span class="vk-dash-kpi-label">Customers</span>
            <span class="vk-dash-kpi-value" data-vk-metric="total-customers"><?= e((string) $totalCustomers) ?></span>
            <span class="vk-dash-kpi-sub">Directory</span>
        </div>
        <svg class="vk-dash-sparkline text-primary" data-vk-spark="customers" viewBox="0 0 48 16" aria-hidden="true"></svg>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-teal" href="<?= e(BASE_URL) ?>/modules/products/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-box-seam"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Products</span><span class="vk-dash-kpi-value" id="vkDashKpiProducts">—</span><span class="vk-dash-kpi-sub">Parts &amp; inventory</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-orange" href="<?= e(BASE_URL) ?>/modules/repairs/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-cart3"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Orders</span><span class="vk-dash-kpi-value" data-vk-metric="pending-jobs-kpi"><?= e((string) $pendingJobs) ?></span><span class="vk-dash-kpi-sub">Active pipeline</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-green" href="<?= e(BASE_URL) ?>/modules/invoices/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-currency-rupee"></i></div>
        <div class="vk-dash-kpi-body">
            <span class="vk-dash-kpi-label">Revenue</span>
            <span class="vk-dash-kpi-value" data-vk-metric="sales-month"><?= e(number_format($salesMonth, 0)) ?></span>
            <span class="vk-dash-kpi-sub">This month</span>
        </div>
        <svg class="vk-dash-sparkline text-success" data-vk-spark="revenue" viewBox="0 0 48 16" aria-hidden="true"></svg>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-purple" href="<?= e(BASE_URL) ?>/modules/invoices/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-file-earmark-text"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Quotations</span><span class="vk-dash-kpi-value" data-vk-metric="marketing-leads"><?= e((string) $marketingMetrics['leads']) ?></span><span class="vk-dash-kpi-sub">Lead pipeline</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-blue" href="<?= e(BASE_URL) ?>/modules/invoices/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-receipt"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Invoices</span><span class="vk-dash-kpi-value" data-vk-metric="sales-month-kpi"><?= e(number_format($salesMonth, 0)) ?></span><span class="vk-dash-kpi-sub">Billed</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-green" href="<?= e(BASE_URL) ?>/modules/payments/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-credit-card"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Payments</span><span class="vk-dash-kpi-value" data-vk-metric="sales-today"><?= e(number_format($salesToday, 0)) ?></span><span class="vk-dash-kpi-sub">Today</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-orange" href="<?= e(BASE_URL) ?>/modules/repairs/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-wrench-adjustable"></i></div>
        <div class="vk-dash-kpi-body">
            <span class="vk-dash-kpi-label">Repairs</span>
            <span class="vk-dash-kpi-value" data-vk-metric="repair-pipeline"><?= e((string) $repairPipeline) ?></span>
            <span class="vk-dash-kpi-sub">In pipeline</span>
        </div>
        <svg class="vk-dash-sparkline text-warning" data-vk-spark="repairs" viewBox="0 0 48 16" aria-hidden="true"></svg>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-teal" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-calendar-check"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Maintenance</span><span class="vk-dash-kpi-value" data-vk-metric="active-contracts"><?= e((string) $activeContracts) ?></span><span class="vk-dash-kpi-sub">Active contracts</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-purple" href="<?= e(BASE_URL) ?>/modules/cctv/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-camera-video"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">CCTV</span><span class="vk-dash-kpi-value" data-vk-metric="cctv-total"><?= e((string) ($cctvActive + $cctvDone)) ?></span><span class="vk-dash-kpi-sub">Projects</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-blue" href="<?= e(BASE_URL) ?>/modules/bookings/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-calendar2-check"></i></div>
        <div class="vk-dash-kpi-body">
            <span class="vk-dash-kpi-label">Bookings</span>
            <span class="vk-dash-kpi-value" data-vk-metric="total-bookings"><?= e((string) $totalBookings) ?></span>
            <span class="vk-dash-kpi-sub"><span data-vk-metric="total-services"><?= e((string) $totalServices) ?></span> services</span>
        </div>
        <svg class="vk-dash-sparkline text-primary" data-vk-spark="bookings" viewBox="0 0 48 16" aria-hidden="true"></svg>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-teal" href="<?= e(BASE_URL) ?>/modules/products/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-boxes"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Inventory</span><span class="vk-dash-kpi-value" id="vkDashInvValue">—</span><span class="vk-dash-kpi-sub">Stock value</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-purple" href="<?= e(BASE_URL) ?>/modules/technicians/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-person-badge"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Employees</span><span class="vk-dash-kpi-value" data-vk-metric="active-technicians"><?= e((string) $activeTechnicians) ?></span><span class="vk-dash-kpi-sub">Technicians</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-orange" href="<?= e(BASE_URL) ?>/modules/accounts/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-truck"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Accounts</span><span class="vk-dash-kpi-value" data-vk-metric="total-customers"><?= e((string) $totalCustomers) ?></span><span class="vk-dash-kpi-sub">Ledger</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-green" href="<?= e(BASE_URL) ?>/modules/invoices/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Sales</span><span class="vk-dash-kpi-value" data-vk-metric="sales-today"><?= e(number_format($salesToday, 0)) ?></span><span class="vk-dash-kpi-sub">Today</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-green" href="<?= e(BASE_URL) ?>/modules/invoices/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-pie-chart"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Profit</span><span class="vk-dash-kpi-value" data-vk-metric="workload-completion"><?= e((string) $workloadCompletion) ?>%</span><span class="vk-dash-kpi-sub">Completion</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-red" href="<?= e(BASE_URL) ?>/modules/accounts/list.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-wallet2"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Alerts</span><span class="vk-dash-kpi-value" data-vk-metric="critical-count"><?= e((string) $criticalCount) ?></span><span class="vk-dash-kpi-sub">Priority queue</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-green" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-whatsapp"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">WhatsApp</span><span class="vk-dash-kpi-value" data-vk-metric="marketing-whatsapp"><?= e((string) $marketingMetrics['whatsapp_delivery_rate']) ?>%</span><span class="vk-dash-kpi-sub">Delivery</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-purple" href="<?= e(BASE_URL) ?>/modules/marketing/index.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-megaphone"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">Marketing</span><span class="vk-dash-kpi-value" data-vk-metric="marketing-reach"><?= e(number_format((int) $marketingMetrics['reach'])) ?></span><span class="vk-dash-kpi-sub" id="vkDashKpiMarketingSub"><span data-vk-metric="marketing-campaigns"><?= e((string) $marketingMetrics['active_campaigns']) ?></span> campaigns</span></div>
    </a>
    <a class="vk-dash-kpi vk-dash-kpi-blue" href="<?= e(BASE_URL) ?>/modules/seo/index.php">
        <div class="vk-dash-kpi-icon"><i class="bi bi-search-heart"></i></div>
        <div class="vk-dash-kpi-body"><span class="vk-dash-kpi-label">SEO</span><span class="vk-dash-kpi-value" data-vk-metric="seo-average"><?= e((string) $seoAverage) ?>%</span><span class="vk-dash-kpi-sub">Avg score</span></div>
    </a>
</section>

<section class="vk-dash-growth-grid" aria-label="Growth and automation">
    <a class="vk-dash-growth-card" href="<?= e(BASE_URL) ?>/modules/seo/index.php">
        <span><i class="bi bi-search-heart"></i></span>
        <div><small>SEO score</small><strong data-vk-metric="seo-average"><?= e((string) $seoAverage) ?>%</strong><em>Metadata, sitemap, schema</em></div>
    </a>
    <a class="vk-dash-growth-card" href="<?= e(BASE_URL) ?>/modules/marketing/index.php">
        <span><i class="bi bi-megaphone"></i></span>
        <div><small>Marketing reach</small><strong data-vk-metric="marketing-reach"><?= e(number_format((int) $marketingMetrics['reach'])) ?></strong><em><span data-vk-metric="marketing-campaigns"><?= e((string) $marketingMetrics['active_campaigns']) ?></span> active campaigns</em></div>
    </a>
    <a class="vk-dash-growth-card" href="<?= e(BASE_URL) ?>/modules/marketing/leads.php">
        <span><i class="bi bi-funnel"></i></span>
        <div><small>Lead pipeline</small><strong data-vk-metric="marketing-leads"><?= e((string) $marketingMetrics['leads']) ?></strong><em><span data-vk-metric="marketing-conversion"><?= e((string) $marketingMetrics['conversion_rate']) ?></span>% conversion</em></div>
    </a>
    <a class="vk-dash-growth-card" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php">
        <span><i class="bi bi-whatsapp"></i></span>
        <div><small>WhatsApp</small><strong data-vk-metric="marketing-whatsapp"><?= e((string) $marketingMetrics['whatsapp_delivery_rate']) ?>%</strong><em>Delivery performance</em></div>
    </a>
</section>

<div class="vk-dash-layout">
    <div class="vk-dash-widgets-col" id="vkDashWidgetsCol">

        <div class="vk-dash-widget" data-widget-id="quick-actions">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-lightning-charge me-1"></i> Quick actions</h2>
                <div class="vk-dash-widget-actions"><button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse quick actions"><i class="bi bi-chevron-up"></i></button></div>
            </div>
            <div class="vk-dash-widget-body">
                <div class="vk-dash-actions-grid">
                    <a class="vk-dash-action vk-dash-action-indigo" href="<?= e(BASE_URL) ?>/modules/customers/add.php"><span class="vk-dash-action-icon"><i class="bi bi-person-plus"></i></span><strong>New Customer</strong><kbd>Alt+C</kbd></a>
                    <a class="vk-dash-action vk-dash-action-purple" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><span class="vk-dash-action-icon"><i class="bi bi-receipt"></i></span><strong>New Invoice</strong><kbd>Alt+I</kbd></a>
                    <a class="vk-dash-action vk-dash-action-blue" href="<?= e(BASE_URL) ?>/modules/quotations/create.php"><span class="vk-dash-action-icon"><i class="bi bi-file-earmark-text"></i></span><strong>Quotation</strong></a>
                    <a class="vk-dash-action vk-dash-action-blue" href="<?= e(BASE_URL) ?>/modules/repairs/add.php"><span class="vk-dash-action-icon"><i class="bi bi-tools"></i></span><strong>New Repair</strong><kbd>Alt+R</kbd></a>
                    <a class="vk-dash-action vk-dash-action-green" href="<?= e(BASE_URL) ?>/modules/maintenance/add.php"><span class="vk-dash-action-icon"><i class="bi bi-calendar-check"></i></span><strong>Maintenance</strong><kbd>Alt+M</kbd></a>
                    <a class="vk-dash-action vk-dash-action-teal" href="<?= e(BASE_URL) ?>/modules/cctv/add.php"><span class="vk-dash-action-icon"><i class="bi bi-camera-video"></i></span><strong>CCTV Job</strong></a>
                    <a class="vk-dash-action vk-dash-action-cyan" href="<?= e(BASE_URL) ?>/book.php" target="_blank" rel="noopener"><span class="vk-dash-action-icon"><i class="bi bi-calendar-plus"></i></span><strong>Booking</strong><kbd>Alt+B</kbd></a>
                    <a class="vk-dash-action vk-dash-action-teal" href="<?= e(BASE_URL) ?>/modules/products/list.php"><span class="vk-dash-action-icon"><i class="bi bi-box-seam"></i></span><strong>Add Product</strong></a>
                    <a class="vk-dash-action vk-dash-action-green" href="<?= e(BASE_URL) ?>/modules/payments/list.php"><span class="vk-dash-action-icon"><i class="bi bi-cash-coin"></i></span><strong>Payment</strong></a>
                    <a class="vk-dash-action vk-dash-action-amber" href="<?= e(BASE_URL) ?>/modules/accounts/list.php"><span class="vk-dash-action-icon"><i class="bi bi-wallet2"></i></span><strong>Expense</strong></a>
                    <a class="vk-dash-action vk-dash-action-green" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php"><span class="vk-dash-action-icon"><i class="bi bi-whatsapp"></i></span><strong>WhatsApp</strong></a>
                    <a class="vk-dash-action vk-dash-action-purple" href="<?= e(BASE_URL) ?>/modules/marketing/index.php"><span class="vk-dash-action-icon"><i class="bi bi-megaphone"></i></span><strong>Campaign</strong></a>
                    <a class="vk-dash-action vk-dash-action-slate" href="<?= e(BASE_URL) ?>/modules/settings/index.php"><span class="vk-dash-action-icon"><i class="bi bi-database"></i></span><strong>Backup</strong></a>
                    <a class="vk-dash-action vk-dash-action-slate" href="<?= e(BASE_URL) ?>/modules/settings/index.php"><span class="vk-dash-action-icon"><i class="bi bi-gear"></i></span><strong>Settings</strong></a>
                </div>
                <hr class="border-secondary border-opacity-25 my-3">
                <div class="vk-dash-actions-grid">
                    <?php foreach ($quickActions as $action): ?>
                    <a class="vk-dash-action vk-dash-action-<?= e($action['tone']) ?>" href="<?= e($action['href']) ?>" <?= !empty($action['target']) ? 'target="_blank" rel="noopener"' : '' ?>>
                        <span class="vk-dash-action-icon"><i class="bi <?= e($action['icon']) ?>"></i></span>
                        <strong><?= e($action['title']) ?></strong>
                        <small class="text-muted" style="font-size:9px"><?= e($action['desc']) ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="vk-dash-widget" data-widget-id="modules">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-grid me-1"></i> Modules</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse modules"><i class="bi bi-chevron-up"></i></button>
            </div>
            <div class="vk-dash-widget-body">
                <div class="vk-dash-modules">
                    <?php
                    $mods = [
                        ['Customers', 'bi-people', '/modules/customers/list.php', ''],
                        ['Products', 'bi-box-seam', '/modules/products/list.php', ''],
                        ['Sales', 'bi-graph-up', '/modules/invoices/list.php', ''],
                        ['Repairs', 'bi-wrench', '/modules/repairs/list.php', 'vkDashModRepairs'],
                        ['Maint.', 'bi-calendar-check', '/modules/maintenance/list.php', 'vkDashModMaint'],
                        ['CCTV', 'bi-camera-video', '/modules/cctv/list.php', ''],
                        ['Bookings', 'bi-calendar2-check', '/modules/bookings/list.php', 'vkDashModBookings'],
                        ['Inventory', 'bi-boxes', '/modules/products/list.php', ''],
                        ['Payments', 'bi-cash-coin', '/modules/payments/list.php', ''],
                        ['Accounts', 'bi-wallet2', '/modules/accounts/list.php', ''],
                        ['Reports', 'bi-bar-chart', '/modules/invoices/list.php', ''],
                        ['Technicians', 'bi-person-badge', '/modules/technicians/list.php', ''],
                        ['WhatsApp', 'bi-whatsapp', '/modules/whatsapp/index.php', ''],
                        ['Marketing', 'bi-megaphone', '/modules/marketing/index.php', ''],
                        ['SEO', 'bi-search-heart', '/modules/seo/index.php', ''],
                        ['Settings', 'bi-gear', '/modules/settings/index.php', ''],
                    ];
                    foreach ($mods as $mod):
                    ?>
                    <a class="vk-dash-mod" href="<?= e(BASE_URL . $mod[2]) ?>">
                        <?php if ($mod[3] !== ''): ?><span class="vk-dash-mod-badge" id="<?= e($mod[3]) ?>">0</span><?php endif; ?>
                        <span class="vk-dash-mod-icon"><i class="bi <?= e($mod[1]) ?>"></i></span>
                        <span><?= e($mod[0]) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="vk-dash-widget" data-widget-id="schedule">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-calendar-event me-1"></i> Today's schedule</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse schedule"><i class="bi bi-chevron-up"></i></button>
            </div>
            <div class="vk-dash-widget-body" id="vkDashSchedule"><p class="small text-muted mb-0">Loading schedule…</p></div>
        </div>

        <div class="vk-dash-widget" data-widget-id="system">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-hdd-network me-1"></i> System status</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse system status"><i class="bi bi-chevron-up"></i></button>
            </div>
            <div class="vk-dash-widget-body"><div class="vk-dash-status-list" id="vkDashSystemStatus"><div class="text-muted small">Checking…</div></div></div>
        </div>

        <div class="vk-dash-widget" data-widget-id="finance">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-bank me-1"></i> Finance summary</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse finance"><i class="bi bi-chevron-up"></i></button>
            </div>
            <div class="vk-dash-widget-body">
                <div class="vk-dash-stat-grid mb-2">
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Cash (today)</div><div class="vk-dash-stat-value" id="vkDashFinCash">—</div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Revenue (mo)</div><div class="vk-dash-stat-value" id="vkDashFinRevenue">—</div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Receivables</div><div class="vk-dash-stat-value" id="vkDashFinReceivable">—</div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Completion</div><div class="vk-dash-stat-value" data-vk-metric="workload-completion"><?= e((string) $workloadCompletion) ?>%</div></div>
                </div>
                <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">Repairs</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-vk-dash-bar="repairs" data-width="<?= (int) $repairCompletion ?>"></div></div></div>
                <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">Workload</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-vk-dash-bar="workload" data-width="<?= (int) $workloadCompletion ?>"></div></div></div>
            </div>
        </div>

        <div class="vk-dash-widget" data-widget-id="inventory">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-boxes me-1"></i> Inventory</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse inventory"><i class="bi bi-chevron-up"></i></button>
            </div>
            <div class="vk-dash-widget-body">
                <div class="vk-dash-stat-grid">
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Low stock alerts</div><div class="vk-dash-stat-value" id="vkDashInvLow">0</div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Completed jobs</div><div class="vk-dash-stat-value" data-vk-metric="completed-jobs"><?= e((string) $completedJobs) ?></div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Warranty exp.</div><div class="vk-dash-stat-value" data-vk-metric="warranty-expiring"><?= e((string) $warrantyExpiring) ?></div></div>
                    <div class="vk-dash-stat"><div class="vk-dash-stat-label">Services live</div><div class="vk-dash-stat-value" data-vk-metric="total-services"><?= e((string) $totalServices) ?></div></div>
                </div>
            </div>
        </div>

    </div>

    <div class="vk-dash-main-col">

        <div class="vk-dash-widget" data-widget-id="analytics">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-bar-chart-line me-1"></i> Business analytics</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse analytics"><i class="bi bi-chevron-up"></i></button>
            </div>
            <div class="vk-dash-widget-body">
                <div class="vk-dash-chart-grid">
                    <div>
                        <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">Sales today</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-width="40"></div></div><span data-vk-metric="sales-today"><?= e(number_format($salesToday, 0)) ?></span></div>
                        <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">Sales month</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-width="75"></div></div><span data-vk-metric="sales-month"><?= e(number_format($salesMonth, 0)) ?></span></div>
                        <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">Customers</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-width="60"></div></div><span data-vk-metric="total-customers"><?= e((string) $totalCustomers) ?></span></div>
                    </div>
                    <div>
                        <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">Repairs done</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-width="<?= min(100, (int) $repairCompletion) ?>"></div></div><span data-vk-metric="repair-done-total"><?= e((string) ($repairCompleted + $repairDelivered)) ?></span></div>
                        <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">CCTV active</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-width="50"></div></div><span data-vk-metric="cctv-active"><?= e((string) $cctvActive) ?></span></div>
                        <div class="vk-dash-bar-row"><span class="vk-dash-bar-label">Bookings</span><div class="vk-dash-bar-track"><div class="vk-dash-bar-fill" data-width="45"></div></div><span data-vk-metric="total-bookings"><?= e((string) $totalBookings) ?></span></div>
                    </div>
                </div>
                <div class="row g-2 mt-1 small text-muted">
                    <div class="col-6 col-md-3">Repair: <span data-vk-metric="repair-completed"><?= e((string) $repairCompleted) ?></span> / <span data-vk-metric="repair-delivered"><?= e((string) $repairDelivered) ?></span></div>
                    <div class="col-6 col-md-3">CCTV done: <span data-vk-metric="cctv-done"><?= e((string) $cctvDone) ?></span></div>
                    <div class="col-6 col-md-3">Conversion: <span data-vk-metric="marketing-conversion"><?= e((string) $marketingMetrics['conversion_rate']) ?></span>%</div>
                    <div class="col-6 col-md-3">Monthly rev: <span data-vk-metric="sales-month-kpi"><?= e(number_format($salesMonth, 0)) ?></span></div>
                </div>
            </div>
        </div>

        <div class="vk-dash-widget" data-widget-id="activity">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-clock-history me-1"></i> Recent activity</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse activity"><i class="bi bi-chevron-up"></i></button>
            </div>
            <div class="vk-dash-widget-body"><ul class="vk-dash-timeline" id="vkDashTimeline"><li class="text-muted small">Loading activity…</li></ul></div>
        </div>

        <div class="vk-dash-widget" data-widget-id="maint-reminders">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-wrench me-1"></i> Maintenance reminders (14 days)</h2>
                <button type="button" class="vk-dash-widget-btn" data-widget-toggle aria-label="Collapse maintenance"><i class="bi bi-chevron-up"></i></button>
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
                <h2 class="vk-dash-widget-title"><i class="bi bi-inbox me-1"></i> Recent web bookings</h2>
                <a class="small text-decoration-none" href="<?= e(BASE_URL) ?>/modules/bookings/list.php">View all</a>
            </div>
            <div class="vk-dash-widget-body p-0">
                <div class="vk-dash-table-wrap table-responsive-stack">
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

        <div class="vk-dash-widget" data-widget-id="recent-jobs">
            <div class="vk-dash-widget-head">
                <h2 class="vk-dash-widget-title"><i class="bi bi-tools me-1"></i> Recent service jobs</h2>
                <div class="d-flex gap-2 small">
                    <a href="<?= e(BASE_URL) ?>/modules/repairs/list.php">Repairs</a>
                    <span class="text-muted">|</span>
                    <a href="<?= e(BASE_URL) ?>/modules/cctv/list.php">CCTV</a>
                </div>
            </div>
            <div class="vk-dash-widget-body p-0">
                <div class="vk-dash-table-wrap table-responsive-stack">
                    <table class="table vk-dash-table table-hover table-sm mb-0 sortable">
                        <thead class="table-light">
                            <tr><th data-sort="0">Type</th><th data-sort="1">Job</th><th data-sort="2">Customer</th><th data-sort="3">Status</th><th data-sort="4">When</th><th></th></tr>
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

<div class="vk-dash-notify-backdrop" id="vkDashNotifyBackdrop" aria-hidden="true"></div>
<aside class="vk-dash-notify-panel" id="vkDashNotifyPanel" aria-hidden="true" aria-label="Notification center">
    <div class="vk-dash-notify-head">
        <h2 class="h6 mb-0 fw-bold">Notifications <span class="badge rounded-pill bg-danger ms-1" id="vkDashNotifyCount">0</span></h2>
        <button type="button" class="vk-dash-icon-btn" id="vkDashNotifyClose" aria-label="Close notifications"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="vk-dash-notify-scroll" id="vkDashNotifyList"><p class="small text-muted">Loading…</p></div>
</aside>

</div>
<?php require_once dirname(__DIR__) . '/includes/layout_end.php'; ?>
