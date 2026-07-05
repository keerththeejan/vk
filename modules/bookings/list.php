<?php
declare(strict_types=1);

$pageTitle = 'Web bookings';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

if (!db_table_exists($pdo, 'web_bookings')) {
    require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
    echo '<div class="alert alert-warning">Run <code>sql/upgrade_v4_public.sql</code> to enable bookings.</div>';
    require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';

$q = trim((string) ($_GET['q'] ?? ''));
$em = ($_GET['emergency'] ?? '') === '1';
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPageOptions = [10, 20, 25, 50, 100];
$perPageReq = (int) ($_GET['per_page'] ?? 20);
$perPage = in_array($perPageReq, $perPageOptions, true) ? $perPageReq : 20;
$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (b.booking_number LIKE ? OR b.customer_name LIKE ? OR b.phone LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$wbHasEmergency = db_column_exists($pdo, 'web_bookings', 'is_emergency');
if ($em && $wbHasEmergency) {
    $where .= ' AND b.is_emergency = 1';
}

$countSt = $pdo->prepare("SELECT COUNT(*) FROM web_bookings b WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$wbHasAssignTech = db_column_exists($pdo, 'web_bookings', 'assigned_technician_id');
$wbHasRepairJob = db_column_exists($pdo, 'web_bookings', 'repair_job_id');
$joinTech = $wbHasAssignTech ? ' LEFT JOIN technicians t ON t.id = b.assigned_technician_id' : '';
$selTech = $wbHasAssignTech ? ', t.name AS tech_name' : '';
$orderBy = $wbHasEmergency ? 'b.is_emergency DESC, b.created_at DESC' : 'b.created_at DESC';
$sql = "SELECT b.*{$selTech}
        FROM web_bookings b{$joinTech}
        WHERE $where
        ORDER BY {$orderBy}
        LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$today = date('Y-m-d');
$kpiTotal = vk_count_table($pdo, 'web_bookings');
$kpiPending = vk_count_table($pdo, 'web_bookings', "status = 'pending'");
$kpiCancelled = vk_count_table($pdo, 'web_bookings', "status = 'cancelled'");
$kpiCompleted = vk_count_table($pdo, 'web_bookings', "status IN ('completed','delivered')");
$kpiToday = vk_count_table(
    $pdo,
    'web_bookings',
    'preferred_date = ' . $pdo->quote($today) . ' OR DATE(created_at) = ' . $pdo->quote($today)
);
$kpiConfirmed = vk_count_table($pdo, 'web_bookings', "status = 'in_progress'");
if ($wbHasAssignTech) {
    $kpiConfirmed += vk_count_table(
        $pdo,
        'web_bookings',
        "status = 'pending' AND assigned_technician_id IS NOT NULL AND assigned_technician_id > 0"
    );
}
$kpiAssignedTech = 0;
if ($wbHasAssignTech) {
    $kpiAssignedTech = (int) ($pdo->query(
        'SELECT COUNT(DISTINCT assigned_technician_id) FROM web_bookings WHERE assigned_technician_id IS NOT NULL AND assigned_technician_id > 0'
    )->fetchColumn() ?: 0);
}
$kpiRevenue = (float) ($pdo->query(
    "SELECT COALESCE(SUM(estimated_cost),0) FROM web_bookings WHERE status NOT IN ('cancelled')"
)->fetchColumn() ?: 0);
$kpiSiteVisits = (int) ($pdo->query(
    "SELECT COUNT(*) FROM web_bookings WHERE (latitude IS NOT NULL AND longitude IS NOT NULL) OR (address IS NOT NULL AND TRIM(address) != '')"
)->fetchColumn() ?: 0);
$kpiSatisfaction = $kpiTotal > 0 ? round(($kpiCompleted / $kpiTotal) * 100, 1) : 0.0;
$kpiInProgress = vk_count_table($pdo, 'web_bookings', "status = 'in_progress'");

$statusChart = [
    'pending' => vk_count_table($pdo, 'web_bookings', "status = 'pending'"),
    'in_progress' => $kpiInProgress,
    'completed' => vk_count_table($pdo, 'web_bookings', "status IN ('completed','delivered')"),
    'cancelled' => $kpiCancelled,
];
$statusChartMax = max(1, ...array_values($statusChart));

$serviceChart = [];
$serviceSt = $pdo->query("SELECT service_type, COUNT(*) AS cnt FROM web_bookings GROUP BY service_type ORDER BY cnt DESC LIMIT 6");
while ($sr = $serviceSt->fetch(PDO::FETCH_ASSOC)) {
    $serviceChart[(string) $sr['service_type']] = (int) $sr['cnt'];
}
$serviceChartMax = $serviceChart !== [] ? max(1, ...array_values($serviceChart)) : 1;

$techWorkload = [];
if ($wbHasAssignTech && db_table_exists($pdo, 'technicians')) {
    $twSt = $pdo->query(
        "SELECT COALESCE(t.name, 'Unassigned') AS name, COUNT(*) AS cnt
         FROM web_bookings b
         LEFT JOIN technicians t ON t.id = b.assigned_technician_id
         WHERE b.status NOT IN ('cancelled','delivered','completed')
         GROUP BY b.assigned_technician_id, t.name
         ORDER BY cnt DESC LIMIT 5"
    );
    while ($tr = $twSt->fetch(PDO::FETCH_ASSOC)) {
        $techWorkload[(string) $tr['name']] = (int) $tr['cnt'];
    }
}
$techChartMax = $techWorkload !== [] ? max(1, ...array_values($techWorkload)) : 1;

$techFilterOptions = [];
if ($wbHasAssignTech && db_table_exists($pdo, 'technicians')) {
    $tfSt = $pdo->query(
        'SELECT DISTINCT t.id, t.name FROM technicians t
         INNER JOIN web_bookings b ON b.assigned_technician_id = t.id
         ORDER BY t.name ASC'
    );
    while ($tf = $tfSt->fetch(PDO::FETCH_ASSOC)) {
        $techFilterOptions[(int) $tf['id']] = (string) $tf['name'];
    }
}

$pageFrom = $total > 0 ? $pg['offset'] + 1 : 0;
$pageTo = min($pg['offset'] + count($rows), $total);

$queryBase = static function (array $extra = []) use ($q, $em, $perPage): string {
    return http_build_query(array_merge([
        'q' => $q,
        'emergency' => $em ? '1' : '',
        'per_page' => $perPage,
    ], $extra));
};

$vkBookInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr((string) ($parts[0] ?? 'B'), 0, 1) . substr((string) ($parts[1] ?? ''), 0, 1));
};

$vkBookUiStatus = static function (array $r) use ($wbHasAssignTech): array {
    $status = (string) ($r['status'] ?? 'pending');
    $hasTech = $wbHasAssignTech && !empty($r['assigned_technician_id']);
    if ($status === 'pending' && $hasTech) {
        return ['key' => 'confirmed', 'label' => 'Confirmed', 'class' => 'vk-book-st-confirmed'];
    }
    return match ($status) {
        'pending' => ['key' => 'pending', 'label' => 'Pending', 'class' => 'vk-book-st-pending'],
        'in_progress' => ['key' => 'in_progress', 'label' => 'In Progress', 'class' => 'vk-book-st-in_progress'],
        'completed', 'delivered' => ['key' => 'completed', 'label' => 'Completed', 'class' => 'vk-book-st-completed'],
        'cancelled' => ['key' => 'cancelled', 'label' => 'Cancelled', 'class' => 'vk-book-st-cancelled'],
        default => ['key' => $status, 'label' => booking_public_status_label($status), 'class' => 'vk-book-st-pending'],
    };
};

$vkBookPriority = static function (array $r) use ($wbHasEmergency): array {
    if ($wbHasEmergency && !empty($r['is_emergency'])) {
        return ['key' => 'critical', 'label' => 'Critical', 'class' => 'vk-book-pri-critical'];
    }
    $status = (string) ($r['status'] ?? '');
    if ($status === 'pending') {
        return ['key' => 'high', 'label' => 'High', 'class' => 'vk-book-pri-high'];
    }
    if ($status === 'in_progress') {
        return ['key' => 'medium', 'label' => 'Medium', 'class' => 'vk-book-pri-medium'];
    }
    return ['key' => 'low', 'label' => 'Low', 'class' => 'vk-book-pri-low'];
};

$vkBookPaymentUi = static function (array $r) use ($wbHasRepairJob): array {
    $status = (string) ($r['status'] ?? '');
    if ($status === 'cancelled') {
        return ['key' => 'none', 'label' => '—', 'class' => ''];
    }
    if ($wbHasRepairJob && !empty($r['repair_job_id']) && in_array($status, ['completed', 'delivered'], true)) {
        return ['key' => 'paid', 'label' => 'Paid', 'class' => 'vk-book-pay-paid'];
    }
    $cost = (float) ($r['estimated_cost'] ?? 0);
    if ($cost > 0 && $status === 'in_progress') {
        return ['key' => 'partial', 'label' => 'Partial', 'class' => 'vk-book-pay-partial'];
    }
    if ($cost > 0) {
        return ['key' => 'pending', 'label' => 'Pending', 'class' => 'vk-book-pay-pending'];
    }
    return ['key' => 'pending', 'label' => 'Pending', 'class' => 'vk-book-pay-pending'];
};

$vkBookServiceIcon = static function (string $type): string {
    return match ($type) {
        'computer' => 'bi-pc-display',
        'printer' => 'bi-printer',
        'cctv' => 'bi-camera-video',
        'maintenance' => 'bi-wrench-adjustable',
        'automobile' => 'bi-car-front',
        'ac' => 'bi-snow',
        'electrical' => 'bi-lightning-charge',
        default => 'bi-gear',
    };
};

$vkBookFormatDate = static function (?string $iso): string {
    if ($iso === null || trim($iso) === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y', $ts) : '—';
};

$vkBookFormatTime = static function (?string $iso): string {
    if ($iso === null || trim($iso) === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('h:i A', $ts) : '—';
};

$vkBookBookDate = static function (array $r): string {
    $pref = trim((string) ($r['preferred_date'] ?? ''));
    if ($pref !== '') {
        return $pref;
    }
    $created = (string) ($r['created_at'] ?? '');
    return $created !== '' ? date('Y-m-d', strtotime($created) ?: time()) : '';
};

$vkBookMapUrl = static function (array $r): string {
    $lat = $r['latitude'] ?? null;
    $lng = $r['longitude'] ?? null;
    if ($lat !== null && $lng !== null && (float) $lat !== 0.0 && (float) $lng !== 0.0) {
        return 'https://www.google.com/maps?q=' . rawurlencode((string) $lat . ',' . (string) $lng);
    }
    $addr = trim((string) ($r['address'] ?? ''));
    return $addr !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr) : '';
};

$vkBookShortAddress = static function (?string $address): string {
    if ($address === null || trim($address) === '') {
        return '—';
    }
    $line = trim(strtok($address, "\n"));
    return strlen($line) > 42 ? substr($line, 0, 40) . '…' : $line;
};

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/bookings-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/bookings-list.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/bookings-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkBookApp" class="vk-book-admin vk-book-skeleton"
     data-filtered-total="<?= (int) $total ?>"
     data-search-query="<?= e($q) ?>"
     role="application" aria-label="Booking management">

<header class="vk-book-header">
    <div class="vk-book-header-inner">
        <div>
            <h1 class="vk-book-title"><i class="bi bi-calendar2-check me-1" aria-hidden="true"></i> Bookings</h1>
            <p class="vk-book-subtitle d-none d-md-block">Enterprise appointment &amp; field service scheduling · VK Network</p>
        </div>
        <a class="vk-book-btn vk-book-btn-primary" href="<?= e(BASE_URL) ?>/book.php" target="_blank" rel="noopener">
            <i class="bi bi-plus-lg" aria-hidden="true"></i><span>New Booking</span>
        </a>
    </div>
</header>

<div class="vk-book-kpi-grid" role="region" aria-label="Booking KPIs">
    <div class="vk-book-kpi vk-book-kpi-blue">
        <div class="vk-book-kpi-icon"><i class="bi bi-calendar-event"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Total</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiTotal ?>">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-green">
        <div class="vk-book-kpi-icon"><i class="bi bi-check-circle"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Confirmed</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiConfirmed ?>">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-orange">
        <div class="vk-book-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Pending</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiPending ?>">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-teal">
        <div class="vk-book-kpi-icon"><i class="bi bi-calendar-day"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Today</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiToday ?>">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-red">
        <div class="vk-book-kpi-icon"><i class="bi bi-x-circle"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Cancelled</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiCancelled ?>">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-green">
        <div class="vk-book-kpi-icon"><i class="bi bi-patch-check"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Completed</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiCompleted ?>">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-purple">
        <div class="vk-book-kpi-icon"><i class="bi bi-person-gear"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Technicians</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiAssignedTech ?>">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-green">
        <div class="vk-book-kpi-icon"><i class="bi bi-currency-rupee"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Revenue</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiRevenue ?>" data-count-money="1">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-teal">
        <div class="vk-book-kpi-icon"><i class="bi bi-geo-alt"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Site visits</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (int) $kpiSiteVisits ?>">0</span>
        </div>
    </div>
    <div class="vk-book-kpi vk-book-kpi-purple">
        <div class="vk-book-kpi-icon"><i class="bi bi-star"></i></div>
        <div class="vk-book-kpi-body">
            <span class="vk-book-kpi-label">Completion</span>
            <span class="vk-book-kpi-value" data-count-to="<?= (float) $kpiSatisfaction ?>" data-count-decimal="1" data-count-suffix="%">0</span>
        </div>
    </div>
</div>

<div class="vk-book-analytics" role="region" aria-label="Booking analytics">
    <div class="vk-book-chart-card">
        <h3 class="vk-book-chart-title">Booking status</h3>
        <?php foreach ($statusChart as $label => $cnt): ?>
        <div class="vk-book-bar-row">
            <span class="vk-book-bar-label"><?= e(ucfirst(str_replace('_', ' ', $label))) ?></span>
            <div class="vk-book-bar-track"><div class="vk-book-bar-fill" data-width="<?= (int) round(($cnt / $statusChartMax) * 100) ?>"></div></div>
            <span class="vk-book-bar-val"><?= (int) $cnt ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="vk-book-chart-card">
        <h3 class="vk-book-chart-title">Service types</h3>
        <?php if ($serviceChart === []): ?>
            <p class="small text-muted mb-0">No data</p>
        <?php else: ?>
            <?php foreach ($serviceChart as $label => $cnt): ?>
            <div class="vk-book-bar-row">
                <span class="vk-book-bar-label"><?= e(ucfirst(str_replace('_', ' ', $label))) ?></span>
                <div class="vk-book-bar-track"><div class="vk-book-bar-fill" data-width="<?= (int) round(($cnt / $serviceChartMax) * 100) ?>"></div></div>
                <span class="vk-book-bar-val"><?= (int) $cnt ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="vk-book-chart-card">
        <h3 class="vk-book-chart-title">Technician workload</h3>
        <?php if ($techWorkload === []): ?>
            <p class="small text-muted mb-0">No active assignments</p>
        <?php else: ?>
            <?php foreach ($techWorkload as $label => $cnt): ?>
            <div class="vk-book-bar-row">
                <span class="vk-book-bar-label"><?= e($label) ?></span>
                <div class="vk-book-bar-track"><div class="vk-book-bar-fill" data-width="<?= (int) round(($cnt / $techChartMax) * 100) ?>"></div></div>
                <span class="vk-book-bar-val"><?= (int) $cnt ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="vk-book-chart-card">
        <h3 class="vk-book-chart-title">Revenue trend</h3>
        <div class="vk-book-chart-metric" data-count-to="<?= (int) $kpiRevenue ?>" data-count-money="1">0</div>
        <div class="vk-book-bar-row">
            <span class="vk-book-bar-label">Active</span>
            <div class="vk-book-bar-track"><div class="vk-book-bar-fill" data-width="<?= $kpiTotal > 0 ? (int) round((($kpiTotal - $kpiCancelled) / $kpiTotal) * 100) : 0 ?>"></div></div>
            <span class="vk-book-bar-val"><?= (int) $kpiTotal - (int) $kpiCancelled ?></span>
        </div>
        <div class="vk-book-bar-row">
            <span class="vk-book-bar-label">Cancel %</span>
            <div class="vk-book-bar-track"><div class="vk-book-bar-fill" style="background:var(--danger)" data-width="<?= $kpiTotal > 0 ? (int) round(($kpiCancelled / $kpiTotal) * 100) : 0 ?>"></div></div>
            <span class="vk-book-bar-val"><?= $kpiTotal > 0 ? round(($kpiCancelled / $kpiTotal) * 100, 1) : 0 ?>%</span>
        </div>
    </div>
</div>

<div class="vk-book-view-tabs" role="tablist" aria-label="View mode">
    <button type="button" class="vk-book-view-tab is-active" data-view="table" role="tab" aria-selected="true">Table</button>
    <button type="button" class="vk-book-view-tab" data-view="calendar" role="tab" aria-selected="false" id="vkBookCalendarBtn">Calendar</button>
</div>

<form id="vkBookFilterForm" class="vk-book-toolbar" method="get" role="search" aria-label="Filter bookings">
    <div class="vk-book-toolbar-mobile-head d-xl-none">
        <span class="fw-semibold small">Filters</span>
        <button class="vk-book-btn vk-book-btn-ghost vk-book-btn-icon" type="button" data-bs-toggle="collapse" data-bs-target="#vkBookToolbarCollapse" aria-expanded="false" aria-controls="vkBookToolbarCollapse"><i class="bi bi-funnel"></i></button>
    </div>
    <div id="vkBookToolbarCollapse" class="collapse vk-book-toolbar-collapse">
        <div class="vk-book-toolbar-inner">
            <div class="vk-book-search-wrap">
                <i class="bi bi-search vk-book-search-ico" aria-hidden="true"></i>
                <input type="search" id="vkBookSearch" name="q" class="form-control vk-book-ctl w-100" placeholder="Booking #, customer, phone…" value="<?= e($q) ?>" aria-label="Search bookings" autocomplete="off">
            </div>
            <select id="vkBookFilterStatus" class="form-select vk-book-ctl vk-book-ctl-sm" aria-label="Filter by status">
                <option value="">All status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select id="vkBookFilterService" class="form-select vk-book-ctl vk-book-ctl-sm" aria-label="Filter by service">
                <option value="">All services</option>
                <?php foreach (['computer', 'printer', 'cctv', 'maintenance', 'automobile', 'ac', 'electrical', 'other'] as $svc): ?>
                <option value="<?= e($svc) ?>"><?= e(ucfirst(str_replace('_', ' ', $svc))) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="vkBookFilterTech" class="form-select vk-book-ctl vk-book-ctl-sm vk-book-col-hide-md" aria-label="Filter by technician" <?= $techFilterOptions === [] ? 'disabled' : '' ?>>
                <option value="">All technicians</option>
                <?php foreach ($techFilterOptions as $tid => $tname): ?>
                <option value="<?= (int) $tid ?>"><?= e($tname) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="vkBookFilterPriority" class="form-select vk-book-ctl vk-book-ctl-xs vk-book-col-hide-md" aria-label="Filter by priority">
                <option value="">Priority</option>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
            </select>
            <select class="form-select vk-book-ctl vk-book-ctl-sm vk-book-col-hide-lg" disabled aria-label="Branch filter unavailable" title="Branch not in schema">
                <option>All branches</option>
            </select>
            <input type="date" id="vkBookFilterDateFrom" class="form-control vk-book-ctl vk-book-ctl-date vk-book-col-hide-md" aria-label="Date from">
            <input type="date" id="vkBookFilterDateTo" class="form-control vk-book-ctl vk-book-ctl-date vk-book-col-hide-md" aria-label="Date to">
            <select id="vkBookFilterTimeSlot" class="form-select vk-book-ctl vk-book-ctl-sm vk-book-col-hide-lg" aria-label="Time slot">
                <option value="">Any time</option>
                <option value="morning">Morning</option>
                <option value="afternoon">Afternoon</option>
                <option value="evening">Evening</option>
            </select>
            <select id="vkBookPerPage" name="per_page" class="form-select vk-book-ctl vk-book-ctl-xs" aria-label="Rows per page">
                <?php foreach ($perPageOptions as $opt): ?>
                <option value="<?= $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
            <label class="vk-book-emergency-check"<?= $wbHasEmergency ? '' : ' title="Add is_emergency column"' ?>>
                <input type="checkbox" name="emergency" value="1" id="vkBookEmergency" <?= $em ? 'checked' : '' ?> <?= $wbHasEmergency ? '' : 'disabled' ?>>
                Emergency
            </label>
            <div class="vk-book-toolbar-btns">
                <a class="vk-book-btn vk-book-btn-primary" href="<?= e(BASE_URL) ?>/book.php" target="_blank" rel="noopener"><i class="bi bi-plus-lg"></i><span class="d-none d-lg-inline">New</span></a>
                <button type="button" class="vk-book-btn" id="vkBookRefresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
                <button type="button" class="vk-book-btn" id="vkBookReset" aria-label="Reset filters"><i class="bi bi-x-lg"></i></button>
                <button type="button" class="vk-book-btn" id="vkBookExportCsv" aria-label="Export CSV"><i class="bi bi-filetype-csv"></i></button>
                <button type="button" class="vk-book-btn" id="vkBookExportExcel" aria-label="Export Excel"><i class="bi bi-file-earmark-spreadsheet"></i></button>
                <button type="button" class="vk-book-btn" id="vkBookExportPdf" aria-label="Print PDF"><i class="bi bi-file-pdf"></i></button>
                <button type="button" class="vk-book-btn" id="vkBookPrint" aria-label="Print"><i class="bi bi-printer"></i></button>
            </div>
        </div>
    </div>
</form>

<div class="vk-book-panel" id="vkBookPanel">
    <?php if (!$rows): ?>
    <div class="vk-book-empty" role="status">
        <div class="vk-book-empty-icon"><i class="bi bi-calendar-x"></i></div>
        <h2>No bookings found.</h2>
        <p class="small mb-3"><?= $q !== '' || $em ? 'Try adjusting your search or filters.' : 'Online bookings will appear here when customers submit the public form.' ?></p>
        <a class="vk-book-btn vk-book-btn-primary" href="<?= e(BASE_URL) ?>/book.php" target="_blank" rel="noopener"><i class="bi bi-plus-lg"></i> Create Booking</a>
    </div>
    <?php else: ?>

    <div id="vkBookCalendar" class="vk-book-calendar" aria-label="Calendar view"></div>

    <div id="vkBookTableWrap" class="vk-book-panel-scroll vk-book-desktop-only">
        <table class="table vk-book-table mb-0" id="vkBookTable" aria-label="Bookings data grid">
            <thead>
                <tr>
                    <th class="vk-book-sticky-col vk-book-sticky-check" scope="col" style="width:34px"><input type="checkbox" class="form-check-input" id="vkBookSelectAll" aria-label="Select all"></th>
                    <th class="vk-book-sticky-col vk-book-sticky-no" scope="col" style="width:120px">Booking No</th>
                    <th scope="col" style="width:180px">Customer</th>
                    <th class="vk-book-col-hide-md" scope="col" style="width:110px">Phone</th>
                    <th scope="col" style="width:140px">Service</th>
                    <th scope="col" style="width:130px">Technician</th>
                    <th scope="col" style="width:110px">Date</th>
                    <th class="vk-book-col-hide-md" scope="col" style="width:80px">Time</th>
                    <th class="vk-book-col-hide-lg" scope="col" style="width:72px">Priority</th>
                    <th scope="col" style="width:100px">Status</th>
                    <th class="vk-book-col-hide-md" scope="col" style="width:140px">Location</th>
                    <th class="vk-book-col-hide-lg" scope="col" style="width:90px">Est. Cost</th>
                    <th class="vk-book-col-hide-md" scope="col" style="width:80px">Payment</th>
                    <th class="vk-book-col-hide-lg" scope="col" style="width:80px">Created</th>
                    <th scope="col" style="width:280px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $isEmerg = $wbHasEmergency && isset($r['is_emergency']) && (int) $r['is_emergency'] === 1;
                $uiSt = $vkBookUiStatus($r);
                $pri = $vkBookPriority($r);
                $pay = $vkBookPaymentUi($r);
                $svc = (string) ($r['service_type'] ?? 'other');
                $svcLabel = ucfirst(str_replace('_', ' ', $svc));
                $techName = trim((string) ($r['tech_name'] ?? ''));
                $techId = $wbHasAssignTech ? (int) ($r['assigned_technician_id'] ?? 0) : 0;
                $bookDate = $vkBookBookDate($r);
                $bookHour = (string) ($r['created_at'] ?? '') !== '' ? date('G', strtotime((string) $r['created_at']) ?: time()) : '';
                $prefDate = $vkBookFormatDate($r['preferred_date'] ?? null);
                $prefTime = $vkBookFormatTime($r['created_at'] ?? null);
                $cost = (float) ($r['estimated_cost'] ?? 0);
                $mapUrl = $vkBookMapUrl($r);
                $waUrl = vk_whatsapp_me_link((string) $r['phone'], vk_whatsapp_web_booking_message($r));
                $viewUrl = BASE_URL . '/modules/bookings/view.php?id=' . (int) $r['id'];
                $telUrl = preg_replace('/\D+/', '', (string) $r['phone']) !== '' ? 'tel:' . preg_replace('/\D+/', '', (string) $r['phone']) : '';
                $repairUrl = ($wbHasRepairJob && !empty($r['repair_job_id']))
                    ? BASE_URL . '/modules/repairs/view.php?id=' . (int) $r['repair_job_id']
                    : '';
                $drawerData = [
                    'id' => (int) $r['id'],
                    'bookingNo' => (string) $r['booking_number'],
                    'customer' => (string) $r['customer_name'],
                    'phone' => (string) $r['phone'],
                    'email' => (string) ($r['email'] ?? ''),
                    'address' => (string) ($r['address'] ?? ''),
                    'service' => $svcLabel,
                    'statusLabel' => $uiSt['label'],
                    'tech' => $techName !== '' ? $techName : 'Unassigned',
                    'date' => $prefDate,
                    'time' => $prefTime,
                    'cost' => $cost > 0 ? '₹' . number_format($cost, 0) : '—',
                    'payment' => $pay['label'],
                    'problem' => (string) ($r['problem_description'] ?? ''),
                    'created' => $vkBookFormatDate($r['created_at'] ?? null),
                    'initials' => $vkBookInitials((string) $r['customer_name']),
                    'waUrl' => $waUrl,
                    'mapUrl' => $mapUrl,
                    'repairJobId' => $wbHasRepairJob ? (int) ($r['repair_job_id'] ?? 0) : 0,
                ];
                $drawerJson = htmlspecialchars(json_encode($drawerData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
            ?>
                <tr data-booking-id="<?= (int) $r['id'] ?>"
                    class="<?= $isEmerg ? 'is-emergency' : '' ?>"
                    data-ui-status="<?= e($uiSt['key']) ?>"
                    data-service="<?= e($svc) ?>"
                    data-priority="<?= e($pri['key']) ?>"
                    data-tech="<?= $techId > 0 ? (int) $techId : '' ?>"
                    data-book-date="<?= e($bookDate) ?>"
                    data-book-hour="<?= e($bookHour) ?>"
                    data-emergency="<?= $isEmerg ? '1' : '0' ?>"
                    data-export-no="<?= e((string) $r['booking_number']) ?>"
                    data-export-customer="<?= e((string) $r['customer_name']) ?>"
                    data-export-phone="<?= e((string) $r['phone']) ?>"
                    data-export-service="<?= e($svcLabel) ?>"
                    data-export-tech="<?= e($techName !== '' ? $techName : '—') ?>"
                    data-export-date="<?= e($prefDate) ?>"
                    data-export-time="<?= e($prefTime) ?>"
                    data-export-priority="<?= e($pri['label']) ?>"
                    data-export-status="<?= e($uiSt['label']) ?>"
                    data-export-location="<?= e($vkBookShortAddress($r['address'] ?? null)) ?>"
                    data-export-cost="<?= $cost > 0 ? e(number_format($cost, 2)) : '—' ?>"
                    data-export-payment="<?= e($pay['label']) ?>"
                    data-export-created-by="Web Portal">
                    <td class="vk-book-sticky-col vk-book-sticky-check" onclick="event.stopPropagation()">
                        <input type="checkbox" class="form-check-input vk-book-row-check" aria-label="Select booking <?= e((string) $r['booking_number']) ?>">
                    </td>
                    <td class="vk-book-sticky-col vk-book-sticky-no">
                        <span class="vk-book-no vk-book-highlight-target"><?= e((string) $r['booking_number']) ?></span>
                        <?php if ($isEmerg): ?><span class="vk-book-pri-critical ms-1">!</span><?php endif; ?>
                        <?php if ($repairUrl !== ''): ?>
                        <a class="vk-book-mod ms-1" href="<?= e($repairUrl) ?>" title="Linked repair job" onclick="event.stopPropagation()">Repair</a>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="vk-book-person">
                            <span class="vk-book-avatar" aria-hidden="true"><?= e($vkBookInitials((string) $r['customer_name'])) ?></span>
                            <div class="min-w-0">
                                <button type="button" class="vk-book-name vk-book-name-btn vk-book-highlight-target" data-booking-drawer="<?= $drawerJson ?>"><?= e((string) $r['customer_name']) ?></button>
                                <div class="vk-book-sub d-md-none"><?= e((string) $r['phone']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="vk-book-col-hide-md vk-book-highlight-target"><?= e((string) $r['phone']) ?></td>
                    <td>
                        <div class="vk-book-service-cell">
                            <span class="vk-book-svc-icon" aria-hidden="true"><i class="bi <?= e($vkBookServiceIcon($svc)) ?>"></i></span>
                            <span class="vk-book-name"><?= e($svcLabel) ?></span>
                        </div>
                    </td>
                    <td>
                        <?php if ($techName !== ''): ?>
                        <div class="vk-book-tech-cell">
                            <span class="vk-book-tech-av" aria-hidden="true"><?= e(strtoupper(substr($techName, 0, 2))) ?></span>
                            <span class="vk-book-name"><?= e($techName) ?></span>
                        </div>
                        <?php else: ?>
                        <span class="vk-book-sub">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="vk-book-date-main"><?= e($prefDate) ?></div>
                        <div class="vk-book-date-sub d-md-none"><?= e($prefTime) ?></div>
                    </td>
                    <td class="vk-book-col-hide-md"><span class="vk-book-date-sub"><?= e($prefTime) ?></span></td>
                    <td class="vk-book-col-hide-lg"><span class="<?= e($pri['class']) ?>"><?= e($pri['label']) ?></span></td>
                    <td><span class="vk-book-badge <?= e($uiSt['class']) ?>"><?= e($uiSt['label']) ?></span></td>
                    <td class="vk-book-col-hide-md">
                        <div class="vk-book-loc">
                            <span class="vk-book-loc-text"><?= e($vkBookShortAddress($r['address'] ?? null)) ?></span>
                            <?php if ($mapUrl !== ''): ?>
                            <a class="vk-book-act" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener" title="Open in Google Maps" onclick="event.stopPropagation()"><i class="bi bi-geo-alt"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="vk-book-col-hide-lg"><span class="vk-book-amt"><?= $cost > 0 ? '₹' . e(number_format($cost, 0)) : '—' ?></span></td>
                    <td class="vk-book-col-hide-md">
                        <?php if ($pay['class'] !== ''): ?>
                        <span class="vk-book-badge <?= e($pay['class']) ?>"><?= e($pay['label']) ?></span>
                        <?php else: ?>
                        <span class="vk-book-sub">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="vk-book-col-hide-lg"><span class="vk-book-sub">Web</span></td>
                    <td onclick="event.stopPropagation()">
                        <div class="vk-book-actions" role="group" aria-label="Booking actions">
                            <a class="vk-book-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                            <a class="vk-book-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="Edit / Manage"><i class="bi bi-pencil"></i></a>
                            <a class="vk-book-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="Assign technician"><i class="bi bi-person-gear"></i></a>
                            <a class="vk-book-act" href="<?= e($viewUrl) ?>" data-bs-toggle="tooltip" title="Reschedule"><i class="bi bi-calendar-event"></i></a>
                            <?php if ($mapUrl !== ''): ?>
                            <a class="vk-book-act" href="<?= e($mapUrl) ?>" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="Track location"><i class="bi bi-pin-map"></i></a>
                            <?php else: ?>
                            <span class="vk-book-act" aria-disabled="true" data-bs-toggle="tooltip" title="No location"><i class="bi bi-pin-map"></i></span>
                            <?php endif; ?>
                            <?php if ($telUrl !== ''): ?>
                            <a class="vk-book-act vk-book-act-success" href="<?= e($telUrl) ?>" data-bs-toggle="tooltip" title="Call"><i class="bi bi-telephone"></i></a>
                            <?php endif; ?>
                            <a class="vk-book-act vk-book-act-success" href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" data-bs-toggle="tooltip" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                            <?php if ($repairUrl !== ''): ?>
                            <a class="vk-book-act" href="<?= e($repairUrl) ?>" data-bs-toggle="tooltip" title="Repair job"><i class="bi bi-receipt"></i></a>
                            <?php else: ?>
                            <a class="vk-book-act" href="<?= e(BASE_URL) ?>/modules/invoices/create.php" data-bs-toggle="tooltip" title="Invoice"><i class="bi bi-receipt"></i></a>
                            <?php endif; ?>
                            <button type="button" class="vk-book-act" data-bs-toggle="tooltip" title="Print" onclick="window.print()"><i class="bi bi-printer"></i></button>
                            <span class="vk-book-act" aria-disabled="true" data-bs-toggle="tooltip" title="Delete not available"><i class="bi bi-trash"></i></span>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="vk-book-mobile-only" aria-label="Bookings mobile list">
        <?php foreach ($rows as $r):
            $isEmerg = $wbHasEmergency && isset($r['is_emergency']) && (int) $r['is_emergency'] === 1;
            $uiSt = $vkBookUiStatus($r);
            $pri = $vkBookPriority($r);
            $pay = $vkBookPaymentUi($r);
            $svc = (string) ($r['service_type'] ?? 'other');
            $techName = trim((string) ($r['tech_name'] ?? ''));
            $techId = $wbHasAssignTech ? (int) ($r['assigned_technician_id'] ?? 0) : 0;
            $bookDate = $vkBookBookDate($r);
            $bookHour = (string) ($r['created_at'] ?? '') !== '' ? date('G', strtotime((string) $r['created_at']) ?: time()) : '';
            $prefDate = $vkBookFormatDate($r['preferred_date'] ?? null);
            $cost = (float) ($r['estimated_cost'] ?? 0);
            $viewUrl = BASE_URL . '/modules/bookings/view.php?id=' . (int) $r['id'];
            $waUrl = vk_whatsapp_me_link((string) $r['phone'], vk_whatsapp_web_booking_message($r));
            $drawerData = [
                'id' => (int) $r['id'],
                'bookingNo' => (string) $r['booking_number'],
                'customer' => (string) $r['customer_name'],
                'phone' => (string) $r['phone'],
                'email' => (string) ($r['email'] ?? ''),
                'address' => (string) ($r['address'] ?? ''),
                'service' => ucfirst(str_replace('_', ' ', $svc)),
                'statusLabel' => $uiSt['label'],
                'tech' => $techName !== '' ? $techName : 'Unassigned',
                'date' => $prefDate,
                'time' => $vkBookFormatTime($r['created_at'] ?? null),
                'cost' => $cost > 0 ? '₹' . number_format($cost, 0) : '—',
                'payment' => $pay['label'],
                'problem' => (string) ($r['problem_description'] ?? ''),
                'created' => $vkBookFormatDate($r['created_at'] ?? null),
                'initials' => $vkBookInitials((string) $r['customer_name']),
                'waUrl' => $waUrl,
                'mapUrl' => $vkBookMapUrl($r),
                'repairJobId' => $wbHasRepairJob ? (int) ($r['repair_job_id'] ?? 0) : 0,
            ];
            $drawerJson = htmlspecialchars(json_encode($drawerData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
        ?>
        <article class="vk-book-mobile-card" data-booking-id="<?= (int) $r['id'] ?>"
            data-ui-status="<?= e($uiSt['key']) ?>"
            data-service="<?= e($svc) ?>"
            data-priority="<?= e($pri['key']) ?>"
            data-tech="<?= $techId > 0 ? (int) $techId : '' ?>"
            data-book-date="<?= e($bookDate) ?>"
            data-book-hour="<?= e($bookHour) ?>"
            data-emergency="<?= $isEmerg ? '1' : '0' ?>"
            data-export-no="<?= e((string) $r['booking_number']) ?>"
            data-export-customer="<?= e((string) $r['customer_name']) ?>"
            data-export-phone="<?= e((string) $r['phone']) ?>"
            data-export-service="<?= e(ucfirst(str_replace('_', ' ', $svc))) ?>"
            data-export-tech="<?= e($techName !== '' ? $techName : '—') ?>"
            data-export-date="<?= e($prefDate) ?>"
            data-export-time="<?= e($vkBookFormatTime($r['created_at'] ?? null)) ?>"
            data-export-priority="<?= e($pri['label']) ?>"
            data-export-status="<?= e($uiSt['label']) ?>"
            data-export-location="<?= e($vkBookShortAddress($r['address'] ?? null)) ?>"
            data-export-cost="<?= $cost > 0 ? e(number_format($cost, 2)) : '—' ?>"
            data-export-payment="<?= e($pay['label']) ?>"
            data-export-created-by="Web Portal">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="vk-book-avatar"><?= e($vkBookInitials((string) $r['customer_name'])) ?></span>
                <div class="flex-grow-1 min-w-0">
                    <button type="button" class="vk-book-name vk-book-name-btn" data-booking-drawer="<?= $drawerJson ?>"><?= e((string) $r['customer_name']) ?></button>
                    <div class="vk-book-sub"><code><?= e((string) $r['booking_number']) ?></code> · <?= e((string) $r['phone']) ?></div>
                </div>
                <span class="vk-book-badge <?= e($uiSt['class']) ?>"><?= e($uiSt['label']) ?></span>
            </div>
            <dl class="vk-book-mobile-grid">
                <dt>Service</dt><dd><?= e(ucfirst(str_replace('_', ' ', $svc))) ?></dd>
                <dt>Date</dt><dd><?= e($prefDate) ?></dd>
                <dt>Technician</dt><dd><?= e($techName !== '' ? $techName : 'Unassigned') ?></dd>
                <dt>Cost</dt><dd class="vk-book-amt"><?= $cost > 0 ? '₹' . e(number_format($cost, 0)) : '—' ?></dd>
            </dl>
            <div class="vk-book-actions">
                <a class="vk-book-act" href="<?= e($viewUrl) ?>" title="Manage"><i class="bi bi-eye"></i></a>
                <a class="vk-book-act vk-book-act-success" href="<?= e($waUrl) ?>" target="_blank" rel="noopener" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                <a class="vk-book-act" href="<?= e($viewUrl) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <footer class="vk-book-footer">
        <span>Showing <?= (int) $pageFrom ?>–<?= (int) $pageTo ?> of <?= number_format($total) ?></span>
        <?php if ($pg['pages'] > 1): ?>
        <nav class="vk-book-page-nav" aria-label="Pagination">
            <?php if ($pg['page'] > 1): ?>
            <a class="vk-book-page-link" href="?<?= e($queryBase(['p' => $pg['page'] - 1])) ?>" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <?php
            $startP = max(1, $pg['page'] - 2);
            $endP = min($pg['pages'], $pg['page'] + 2);
            for ($i = $startP; $i <= $endP; $i++):
            ?>
            <a class="vk-book-page-link <?= $i === $pg['page'] ? 'is-active' : '' ?>" href="?<?= e($queryBase(['p' => $i])) ?>" <?= $i === $pg['page'] ? 'aria-current="page"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pg['page'] < $pg['pages']): ?>
            <a class="vk-book-page-link" href="?<?= e($queryBase(['p' => $pg['page'] + 1])) ?>" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </footer>
    <?php endif; ?>
</div>

<div class="vk-book-drawer-backdrop" id="vkBookDrawerBackdrop" aria-hidden="true"></div>
<aside class="vk-book-drawer" id="vkBookDrawer" aria-hidden="true" aria-label="Booking details">
    <div class="vk-book-drawer-head">
        <div class="vk-book-avatar" id="vkBookDrawerAvatar" style="width:48px;height:48px;font-size:16px">B</div>
        <div class="min-w-0 flex-grow-1">
            <h2 class="h6 mb-0 fw-bold" id="vkBookDrawerCustomer">Customer</h2>
            <p class="small text-muted mb-0"><span id="vkBookDrawerNo">—</span> · <span id="vkBookDrawerStatus">—</span></p>
        </div>
        <button type="button" class="vk-book-drawer-close" id="vkBookDrawerClose" aria-label="Close details"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="vk-book-drawer-scroll">
        <h3 class="vk-book-section-title">Booking information</h3>
        <div class="vk-book-stat-grid mb-3">
            <div class="vk-book-stat"><div class="vk-book-stat-label">Service</div><div class="vk-book-stat-value" id="vkBookDrawerService">—</div></div>
            <div class="vk-book-stat"><div class="vk-book-stat-label">Date</div><div class="vk-book-stat-value" id="vkBookDrawerDate">—</div></div>
            <div class="vk-book-stat"><div class="vk-book-stat-label">Time</div><div class="vk-book-stat-value" id="vkBookDrawerTime">—</div></div>
            <div class="vk-book-stat"><div class="vk-book-stat-label">Est. cost</div><div class="vk-book-stat-value" id="vkBookDrawerCost">—</div></div>
        </div>
        <h3 class="vk-book-section-title">Customer</h3>
        <p class="small mb-1"><i class="bi bi-telephone me-2 text-muted"></i><span id="vkBookDrawerPhone">—</span></p>
        <p class="small mb-1"><i class="bi bi-envelope me-2 text-muted"></i><span id="vkBookDrawerEmail">—</span></p>
        <p class="small mb-3"><i class="bi bi-geo-alt me-2 text-muted"></i><span id="vkBookDrawerAddress">—</span></p>
        <h3 class="vk-book-section-title">Assignment</h3>
        <p class="small mb-3"><i class="bi bi-person-gear me-2 text-muted"></i><span id="vkBookDrawerTech">—</span></p>
        <h3 class="vk-book-section-title">Problem / notes</h3>
        <p class="small mb-3" id="vkBookDrawerProblem">—</p>
        <h3 class="vk-book-section-title">Payment</h3>
        <p class="small mb-3"><span id="vkBookDrawerPayment">—</span> · Created <span id="vkBookDrawerCreated">—</span></p>
        <h3 class="vk-book-section-title">VK modules</h3>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="vk-book-mod" href="<?= e(BASE_URL) ?>/modules/customers/list.php">Customers</a>
            <a class="vk-book-mod" href="<?= e(BASE_URL) ?>/modules/repairs/list.php">Repairs</a>
            <a class="vk-book-mod" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">Maintenance</a>
            <a class="vk-book-mod" href="<?= e(BASE_URL) ?>/modules/invoices/list.php">Invoices</a>
            <a class="vk-book-mod" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php">WhatsApp</a>
        </div>
        <h3 class="vk-book-section-title">Quick actions</h3>
        <div class="d-flex flex-wrap gap-2">
            <a class="vk-book-btn" id="vkBookDrawerManage" href="#"><i class="bi bi-eye"></i> Manage</a>
            <a class="vk-book-btn vk-book-btn-success" id="vkBookDrawerWa" href="#" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>
            <a class="vk-book-btn" id="vkBookDrawerMap" href="#" target="_blank" rel="noopener"><i class="bi bi-geo-alt"></i> Map</a>
            <a class="vk-book-btn d-none" id="vkBookDrawerRepair" href="#"><i class="bi bi-tools"></i> Repair job</a>
        </div>
    </div>
</aside>

</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/bookings-list.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
