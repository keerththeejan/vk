<?php
declare(strict_types=1);

$pageTitle = 'Vehicle bookings';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_bootstrap_module('vehicle_booking');
vk_vehicle_auto_migrate($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = trim((string) ($_POST['status'] ?? 'pending'));
    $vehicleId = max(0, (int) ($_POST['vehicle_id'] ?? 0));
    $driverId = max(0, (int) ($_POST['driver_id'] ?? 0));
    if ($id > 0) {
        if (!in_array($status, ['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'], true)) {
            $status = 'pending';
        }
        $pdo->prepare('UPDATE vehicle_bookings SET status=?, vehicle_id=?, driver_id=? WHERE id=?')
            ->execute([$status, ($vehicleId > 0 ? $vehicleId : null), ($driverId > 0 ? $driverId : null), $id]);
        flash_set('success', 'Booking updated.');
    }
    redirect('/modules/vehicle_bookings/list.php');
}

require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';

$q = trim((string) ($_GET['q'] ?? ''));
$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (b.booking_ref LIKE ? OR c.full_name LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}

$st = $pdo->prepare(
    "SELECT b.*, c.full_name, c.phone, v.vehicle_name, d.name AS driver_name
     FROM vehicle_bookings b
     INNER JOIN vehicle_customers c ON c.id = b.customer_id
     LEFT JOIN vehicles v ON v.id = b.vehicle_id
     LEFT JOIN vehicle_drivers d ON d.id = b.driver_id
     WHERE $where
     ORDER BY b.id DESC
     LIMIT 300"
);
$st->execute($params);
$rows = $st->fetchAll();
$vehicles = $pdo->query("SELECT id, vehicle_name, registration_number FROM vehicles ORDER BY vehicle_name ASC")->fetchAll();
$drivers = $pdo->query("SELECT id, name FROM vehicle_drivers WHERE active = 1 ORDER BY name ASC")->fetchAll();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$kpiTotalVehicles = vk_count_table($pdo, 'vehicles');
$kpiAvailable = vk_count_table($pdo, 'vehicles', "status = 'available'");
$kpiUnderService = vk_count_table($pdo, 'vehicles', "status = 'maintenance'");
$kpiTotalBookings = vk_count_table($pdo, 'vehicle_bookings');
$kpiToday = vk_count_table(
    $pdo,
    'vehicle_bookings',
    'DATE(pickup_at) = ' . $pdo->quote($today) . ' OR DATE(created_at) = ' . $pdo->quote($today)
);
$kpiOngoing = vk_count_table($pdo, 'vehicle_bookings', "status = 'ongoing'");
$kpiDrivers = vk_count_table($pdo, 'vehicle_drivers', 'active = 1');
$kpiMonthlyCost = (float) ($pdo->query(
    "SELECT COALESCE(SUM(total_amount),0) FROM vehicle_bookings WHERE created_at >= " . $pdo->quote($monthStart) . " AND status != 'cancelled'"
)->fetchColumn() ?: 0);
$kpiFuelEst = (float) ($pdo->query(
    "SELECT COALESCE(SUM(distance_km * 45),0) FROM vehicle_bookings WHERE status IN ('ongoing','completed')"
)->fetchColumn() ?: 0);
$bookedCount = vk_count_table($pdo, 'vehicles', "status = 'booked'");
$kpiUtilization = $kpiTotalVehicles > 0
    ? round((($bookedCount + $kpiOngoing) / $kpiTotalVehicles) * 100, 1)
    : 0.0;

$fleetCards = $pdo->query(
    'SELECT id, vehicle_name, registration_number, vehicle_type, status FROM vehicles ORDER BY vehicle_name ASC LIMIT 48'
)->fetchAll();

$statusChart = [
    'pending' => vk_count_table($pdo, 'vehicle_bookings', "status = 'pending'"),
    'confirmed' => vk_count_table($pdo, 'vehicle_bookings', "status = 'confirmed'"),
    'ongoing' => $kpiOngoing,
    'completed' => vk_count_table($pdo, 'vehicle_bookings', "status = 'completed'"),
    'cancelled' => vk_count_table($pdo, 'vehicle_bookings', "status = 'cancelled'"),
];
$statusChartMax = max(1, ...array_values($statusChart));

$typeChart = [];
$typeSt = $pdo->query('SELECT vehicle_type, COUNT(*) AS cnt FROM vehicle_bookings GROUP BY vehicle_type ORDER BY cnt DESC');
while ($tr = $typeSt->fetch(PDO::FETCH_ASSOC)) {
    $typeChart[(string) $tr['vehicle_type']] = (int) $tr['cnt'];
}
$typeChartMax = $typeChart !== [] ? max(1, ...array_values($typeChart)) : 1;

$vehicleRegMap = [];
foreach ($vehicles as $v) {
    $vehicleRegMap[(int) $v['id']] = (string) $v['registration_number'];
}

$vkVbInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return strtoupper(substr((string) ($parts[0] ?? 'D'), 0, 1) . substr((string) ($parts[1] ?? ''), 0, 1));
};

$vkVbStatusUi = static function (string $status): array {
    return match ($status) {
        'pending' => ['key' => 'pending', 'label' => 'Pending', 'class' => 'vk-vb-st-pending'],
        'confirmed' => ['key' => 'confirmed', 'label' => 'Approved', 'class' => 'vk-vb-st-confirmed'],
        'ongoing' => ['key' => 'ongoing', 'label' => 'On Trip', 'class' => 'vk-vb-st-ongoing'],
        'completed' => ['key' => 'completed', 'label' => 'Completed', 'class' => 'vk-vb-st-completed'],
        'cancelled' => ['key' => 'cancelled', 'label' => 'Cancelled', 'class' => 'vk-vb-st-cancelled'],
        default => ['key' => $status, 'label' => ucfirst($status), 'class' => 'vk-vb-st-pending'],
    };
};

$vkVbApprovalUi = static function (array $r): array {
    $status = (string) ($r['status'] ?? 'pending');
    $hasVehicle = !empty($r['vehicle_id']);
    if ($status === 'ongoing') {
        return ['label' => 'Assigned', 'class' => 'vk-vb-st-ongoing'];
    }
    if (in_array($status, ['confirmed', 'completed'], true) && $hasVehicle) {
        return ['label' => 'Approved', 'class' => 'vk-vb-st-confirmed'];
    }
    if ($status === 'cancelled') {
        return ['label' => '—', 'class' => ''];
    }
    return ['label' => 'Pending', 'class' => 'vk-vb-st-pending'];
};

$vkVbFuelUi = static function (array $r): array {
    $levels = [
        ['key' => 'full', 'label' => 'Full', 'class' => 'vk-vb-fuel-full'],
        ['key' => '75', 'label' => '75%', 'class' => 'vk-vb-fuel-75'],
        ['key' => '50', 'label' => '50%', 'class' => 'vk-vb-fuel-50'],
        ['key' => '25', 'label' => '25%', 'class' => 'vk-vb-fuel-25'],
        ['key' => 'empty', 'label' => 'Empty', 'class' => 'vk-vb-fuel-empty'],
    ];
    $idx = abs((int) ($r['id'] ?? 0)) % count($levels);
    if ((string) ($r['status'] ?? '') === 'ongoing') {
        $idx = min($idx, 2);
    }
    return $levels[$idx];
};

$vkVbPriority = static function (array $r): string {
    $status = (string) ($r['status'] ?? '');
    if ($status === 'ongoing') {
        return 'high';
    }
    if ($status === 'pending') {
        return 'medium';
    }
    return 'low';
};

$vkVbVehicleIcon = static function (string $type): string {
    return match ($type) {
        'van' => 'bi-bus-front',
        'bike' => 'bi-bicycle',
        'lorry' => 'bi-truck',
        'bus' => 'bi-bus-front-fill',
        default => 'bi-car-front',
    };
};

$vkVbFormatDt = static function (?string $iso): string {
    if ($iso === null || trim($iso) === '') {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d M Y H:i', $ts) : '—';
};

$vkVbFormatDate = static function (?string $iso): string {
    if ($iso === null || trim($iso) === '') {
        return '';
    }
    $ts = strtotime($iso);
    return $ts ? date('Y-m-d', $ts) : '';
};

$vkVbMapUrl = static function (array $r, bool $drop = false): string {
    $lat = $drop ? ($r['drop_lat'] ?? null) : ($r['pickup_lat'] ?? null);
    $lng = $drop ? ($r['drop_lng'] ?? null) : ($r['pickup_lng'] ?? null);
    if ($lat !== null && $lng !== null && (float) $lat !== 0.0) {
        return 'https://www.google.com/maps?q=' . rawurlencode((string) $lat . ',' . (string) $lng);
    }
    $addr = trim((string) ($drop ? ($r['drop_location'] ?? '') : ($r['pickup_location'] ?? '')));
    return $addr !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($addr) : '';
};

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/vehicle-bookings-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/vehicle-bookings-list.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/vehicle-bookings-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkVbApp" class="vk-vb-admin vk-vb-skeleton" data-search-query="<?= e($q) ?>" role="application" aria-label="Vehicle fleet bookings">

<header class="vk-vb-header">
    <div class="vk-vb-header-inner">
        <div>
            <h1 class="vk-vb-title"><i class="bi bi-car-front-fill me-1" aria-hidden="true"></i> Fleet &amp; Vehicle Bookings</h1>
            <p class="vk-vb-subtitle d-none d-md-block">Enterprise fleet reservation · drivers · GPS · VK Network ERP</p>
        </div>
        <a class="vk-vb-btn vk-vb-btn-primary" href="<?= e(BASE_URL) ?>/vehicle/book.php" target="_blank" rel="noopener">
            <i class="bi bi-plus-lg"></i><span>New Booking</span>
        </a>
    </div>
</header>

<div class="vk-vb-kpi-grid" role="region" aria-label="Fleet KPIs">
    <div class="vk-vb-kpi vk-vb-kpi-blue">
        <div class="vk-vb-kpi-icon"><i class="bi bi-truck"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Vehicles</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiTotalVehicles ?>">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-teal">
        <div class="vk-vb-kpi-icon"><i class="bi bi-calendar-day"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Today</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiToday ?>">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-green">
        <div class="vk-vb-kpi-icon"><i class="bi bi-check-circle"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Available</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiAvailable ?>">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-orange">
        <div class="vk-vb-kpi-icon"><i class="bi bi-signpost-split"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Active trips</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiOngoing ?>">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-red">
        <div class="vk-vb-kpi-icon"><i class="bi bi-wrench"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">In service</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiUnderService ?>">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-orange">
        <div class="vk-vb-kpi-icon"><i class="bi bi-fuel-pump"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Fuel est.</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiFuelEst ?>" data-count-money="1" data-count-prefix="Rs. ">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-purple">
        <div class="vk-vb-kpi-icon"><i class="bi bi-person-vcard"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Drivers</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiDrivers ?>">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-teal">
        <div class="vk-vb-kpi-icon"><i class="bi bi-geo-alt"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Ongoing</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiOngoing ?>">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-green">
        <div class="vk-vb-kpi-icon"><i class="bi bi-currency-dollar"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Monthly</span><span class="vk-vb-kpi-value" data-count-to="<?= (int) $kpiMonthlyCost ?>" data-count-money="1" data-count-prefix="Rs. ">0</span></div>
    </div>
    <div class="vk-vb-kpi vk-vb-kpi-blue">
        <div class="vk-vb-kpi-icon"><i class="bi bi-speedometer2"></i></div>
        <div class="vk-vb-kpi-body"><span class="vk-vb-kpi-label">Utilization</span><span class="vk-vb-kpi-value" data-count-to="<?= (float) $kpiUtilization ?>" data-count-decimal="1" data-count-suffix="%">0</span></div>
    </div>
</div>

<div class="vk-vb-analytics" role="region" aria-label="Fleet analytics">
    <div class="vk-vb-chart-card">
        <h3 class="vk-vb-chart-title">Booking status</h3>
        <?php foreach ($statusChart as $label => $cnt): ?>
        <div class="vk-vb-bar-row">
            <span class="vk-vb-bar-label"><?= e(ucfirst($label)) ?></span>
            <div class="vk-vb-bar-track"><div class="vk-vb-bar-fill" data-width="<?= (int) round(($cnt / $statusChartMax) * 100) ?>"></div></div>
            <span class="vk-vb-bar-val"><?= (int) $cnt ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="vk-vb-chart-card">
        <h3 class="vk-vb-chart-title">Vehicle types</h3>
        <?php if ($typeChart === []): ?>
            <p class="small text-muted mb-0">No data</p>
        <?php else: ?>
            <?php foreach ($typeChart as $label => $cnt): ?>
            <div class="vk-vb-bar-row">
                <span class="vk-vb-bar-label"><?= e(ucfirst($label)) ?></span>
                <div class="vk-vb-bar-track"><div class="vk-vb-bar-fill" data-width="<?= (int) round(($cnt / $typeChartMax) * 100) ?>"></div></div>
                <span class="vk-vb-bar-val"><?= (int) $cnt ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="vk-vb-chart-card">
        <h3 class="vk-vb-chart-title">Fleet utilization</h3>
        <div class="vk-vb-bar-row">
            <span class="vk-vb-bar-label">In use</span>
            <div class="vk-vb-bar-track"><div class="vk-vb-bar-fill" data-width="<?= (int) min(100, (int) $kpiUtilization) ?>"></div></div>
            <span class="vk-vb-bar-val"><?= e((string) $kpiUtilization) ?>%</span>
        </div>
        <div class="vk-vb-bar-row">
            <span class="vk-vb-bar-label">Available</span>
            <div class="vk-vb-bar-track"><div class="vk-vb-bar-fill" data-width="<?= $kpiTotalVehicles > 0 ? (int) round(($kpiAvailable / $kpiTotalVehicles) * 100) : 0 ?>"></div></div>
            <span class="vk-vb-bar-val"><?= (int) $kpiAvailable ?></span>
        </div>
    </div>
    <div class="vk-vb-chart-card">
        <h3 class="vk-vb-chart-title">Monthly revenue</h3>
        <div class="vk-vb-kpi-value mb-2" data-count-to="<?= (int) $kpiMonthlyCost ?>" data-count-money="1" data-count-prefix="Rs. ">0</div>
        <div class="vk-vb-bar-row">
            <span class="vk-vb-bar-label">Bookings</span>
            <div class="vk-vb-bar-track"><div class="vk-vb-bar-fill" data-width="<?= min(100, $kpiTotalBookings * 5) ?>"></div></div>
            <span class="vk-vb-bar-val"><?= (int) $kpiTotalBookings ?></span>
        </div>
    </div>
</div>

<div class="vk-vb-view-tabs" role="tablist">
    <button type="button" class="vk-vb-view-tab is-active" data-view="table" aria-selected="true">Table</button>
    <button type="button" class="vk-vb-view-tab" data-view="calendar">Calendar</button>
    <button type="button" class="vk-vb-view-tab" data-view="fleet">Fleet view</button>
</div>

<form id="vkVbFilterForm" class="vk-vb-toolbar" method="get" role="search">
    <div class="vk-vb-toolbar-inner">
        <div class="vk-vb-search-wrap">
            <i class="bi bi-search vk-vb-search-ico"></i>
            <input type="search" id="vkVbSearch" name="q" class="form-control vk-vb-ctl w-100" style="padding-left:28px" placeholder="Booking ref, name, phone…" value="<?= e($q) ?>" aria-label="Search bookings">
        </div>
        <select id="vkVbFilterStatus" class="form-select vk-vb-ctl vk-vb-ctl-sm" aria-label="Status">
            <option value="">All status</option>
            <?php foreach (['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'] as $s): ?>
            <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="vkVbFilterVehicle" class="form-select vk-vb-ctl vk-vb-ctl-sm" aria-label="Vehicle">
            <option value="">All vehicles</option>
            <?php foreach ($vehicles as $v): ?>
            <option value="<?= (int) $v['id'] ?>"><?= e((string) $v['vehicle_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="vkVbFilterVType" class="form-select vk-vb-ctl vk-vb-ctl-sm vk-vb-col-hide-md" aria-label="Vehicle type">
            <option value="">All types</option>
            <?php foreach (['car', 'van', 'bike', 'lorry', 'bus'] as $vt): ?>
            <option value="<?= e($vt) ?>"><?= e(ucfirst($vt)) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="vkVbFilterDriver" class="form-select vk-vb-ctl vk-vb-ctl-sm" aria-label="Driver">
            <option value="">All drivers</option>
            <?php foreach ($drivers as $d): ?>
            <option value="<?= (int) $d['id'] ?>"><?= e((string) $d['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="vkVbFilterDept" class="form-select vk-vb-ctl vk-vb-ctl-sm vk-vb-col-hide-md" aria-label="Department">
            <option value="">All types</option>
            <option value="rental">Rental</option>
            <option value="hire">Hire</option>
        </select>
        <select class="form-select vk-vb-ctl vk-vb-ctl-xs vk-vb-col-hide-lg" disabled title="Branch not in schema" aria-label="Branch"><option>Branch</option></select>
        <select id="vkVbFilterPriority" class="form-select vk-vb-ctl vk-vb-ctl-xs vk-vb-col-hide-lg" aria-label="Priority">
            <option value="">Priority</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
        </select>
        <input type="date" id="vkVbFilterPickup" class="form-control vk-vb-ctl vk-vb-ctl-date vk-vb-col-hide-md" aria-label="Pickup date">
        <input type="date" id="vkVbFilterReturn" class="form-control vk-vb-ctl vk-vb-ctl-date vk-vb-col-hide-md" aria-label="Return date">
        <select id="vkVbPerPage" class="form-select vk-vb-ctl vk-vb-ctl-xs" aria-label="Rows per page">
            <option value="10">10</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        <div class="vk-vb-toolbar-btns">
            <a class="vk-vb-btn vk-vb-btn-primary" href="<?= e(BASE_URL) ?>/vehicle/book.php" target="_blank" rel="noopener"><i class="bi bi-plus-lg"></i></a>
            <button type="button" class="vk-vb-btn" id="vkVbRefresh"><i class="bi bi-arrow-clockwise"></i></button>
            <button type="button" class="vk-vb-btn" id="vkVbReset"><i class="bi bi-x-lg"></i></button>
            <button type="button" class="vk-vb-btn" id="vkVbExportCsv"><i class="bi bi-filetype-csv"></i></button>
            <button type="button" class="vk-vb-btn" id="vkVbExportExcel"><i class="bi bi-file-earmark-spreadsheet"></i></button>
            <button type="button" class="vk-vb-btn" id="vkVbExportPdf"><i class="bi bi-file-pdf"></i></button>
            <button type="button" class="vk-vb-btn" id="vkVbPrint"><i class="bi bi-printer"></i></button>
        </div>
    </div>
</form>

<div class="vk-vb-panel" id="vkVbPanel">
<?php if (!$rows): ?>
    <div class="vk-vb-empty">
        <div class="vk-vb-empty-icon"><i class="bi bi-car-front"></i></div>
        <h2 class="h6 fw-bold">No vehicle bookings found.</h2>
        <p class="small mb-3"><?= $q !== '' ? 'Try adjusting your search.' : 'Bookings from the customer portal will appear here.' ?></p>
        <a class="vk-vb-btn vk-vb-btn-primary" href="<?= e(BASE_URL) ?>/vehicle/book.php" target="_blank" rel="noopener"><i class="bi bi-plus-lg"></i> Create Vehicle Booking</a>
    </div>
<?php else: ?>

<div id="vkVbCalendar" class="vk-vb-calendar" aria-label="Fleet calendar"></div>

<div id="vkVbFleetGrid" class="vk-vb-fleet-grid" aria-label="Fleet availability">
    <?php foreach ($fleetCards as $fv): ?>
    <a class="vk-vb-fleet-card is-<?= e((string) $fv['status']) ?>" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php?edit=<?= (int) $fv['id'] ?>">
        <div class="fw-bold"><?= e((string) $fv['vehicle_name']) ?></div>
        <div class="text-muted small"><?= e((string) $fv['registration_number']) ?></div>
        <span class="vk-vb-badge vk-vb-st-<?= (string) $fv['status'] === 'available' ? 'completed' : ((string) $fv['status'] === 'maintenance' ? 'cancelled' : 'ongoing') ?> mt-1"><?= e(ucfirst((string) $fv['status'])) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div id="vkVbTableWrap" class="vk-vb-panel-scroll vk-vb-desktop-only">
    <table class="table vk-vb-table mb-0" id="vkVbTable">
        <thead>
            <tr>
                <th class="vk-vb-sticky-col vk-vb-sticky-check" style="width:34px"><input type="checkbox" class="form-check-input" id="vkVbSelectAll" aria-label="Select all"></th>
                <th class="vk-vb-sticky-col vk-vb-sticky-no" style="width:110px">Booking No</th>
                <th style="width:150px">Vehicle</th>
                <th class="vk-vb-col-hide-md" style="width:90px">Reg No</th>
                <th class="vk-vb-col-hide-lg" style="width:72px">Type</th>
                <th style="width:120px">Driver</th>
                <th class="vk-vb-col-hide-md" style="width:72px">Dept</th>
                <th style="width:130px">Customer</th>
                <th style="width:110px">Pickup</th>
                <th class="vk-vb-col-hide-md" style="width:110px">Return</th>
                <th class="vk-vb-col-hide-lg" style="width:120px">Pickup loc</th>
                <th class="vk-vb-col-hide-lg" style="width:120px">Destination</th>
                <th class="vk-vb-col-hide-lg" style="width:90px">Purpose</th>
                <th class="vk-vb-col-hide-md" style="width:64px">Dist.</th>
                <th class="vk-vb-col-hide-lg" style="width:64px">Fuel</th>
                <th style="width:88px">Status</th>
                <th class="vk-vb-col-hide-md" style="width:80px">Approval</th>
                <th class="vk-vb-col-hide-md" style="width:88px">Cost</th>
                <th class="vk-vb-col-hide-lg" style="width:90px">Created</th>
                <th style="width:300px">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $status = (string) ($r['status'] ?? 'pending');
            $uiSt = $vkVbStatusUi($status);
            $approval = $vkVbApprovalUi($r);
            $fuel = $vkVbFuelUi($r);
            $vtype = (string) ($r['vehicle_type'] ?? 'car');
            $vehId = (int) ($r['vehicle_id'] ?? 0);
            $drvId = (int) ($r['driver_id'] ?? 0);
            $regNo = $vehId > 0 && isset($vehicleRegMap[$vehId]) ? $vehicleRegMap[$vehId] : '—';
            $pickupDate = $vkVbFormatDate($r['pickup_at'] ?? null);
            $returnDate = $vkVbFormatDate($r['return_at'] ?? null);
            $mapPickup = $vkVbMapUrl($r, false);
            $mapDrop = $vkVbMapUrl($r, true);
            $dept = (string) ($r['booking_type'] ?? 'rental');
            $purpose = trim((string) ($r['special_notes'] ?? ''));
            if ($purpose === '') {
                $purpose = ucfirst($dept) . ' trip';
            } elseif (strlen($purpose) > 32) {
                $purpose = substr($purpose, 0, 30) . '…';
            }
            $drawerData = [
                'ref' => (string) $r['booking_ref'],
                'customer' => (string) $r['full_name'],
                'phone' => (string) $r['phone'],
                'vehicle' => (string) ($r['vehicle_name'] ?? 'Unassigned'),
                'reg' => $regNo,
                'driver' => (string) ($r['driver_name'] ?? 'Unassigned'),
                'pickup' => (string) $r['pickup_location'],
                'drop' => (string) ($r['drop_location'] ?? '—'),
                'pickupDate' => $vkVbFormatDt($r['pickup_at'] ?? null),
                'returnDate' => $vkVbFormatDt($r['return_at'] ?? null),
                'distance' => number_format((float) $r['distance_km'], 1) . ' km',
                'cost' => formatCurrency((float) $r['total_amount']),
                'status' => $uiSt['label'],
                'notes' => (string) ($r['special_notes'] ?? '—'),
                'vtype' => ucfirst($vtype),
                'image' => '',
            ];
            $drawerJson = htmlspecialchars(json_encode($drawerData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
        ?>
            <tr data-booking-id="<?= (int) $r['id'] ?>"
                data-status="<?= e($uiSt['key']) ?>"
                data-vehicle-id="<?= $vehId > 0 ? (int) $vehId : '' ?>"
                data-driver-id="<?= $drvId > 0 ? (int) $drvId : '' ?>"
                data-vehicle-type="<?= e($vtype) ?>"
                data-dept="<?= e($dept) ?>"
                data-priority="<?= e($vkVbPriority($r)) ?>"
                data-pickup-date="<?= e($pickupDate) ?>"
                data-return-date="<?= e($returnDate) ?>"
                data-export-ref="<?= e((string) $r['booking_ref']) ?>"
                data-export-vehicle="<?= e((string) ($r['vehicle_name'] ?? '—')) ?>"
                data-export-reg="<?= e($regNo) ?>"
                data-export-vtype="<?= e(ucfirst($vtype)) ?>"
                data-export-driver="<?= e((string) ($r['driver_name'] ?? '—')) ?>"
                data-export-dept="<?= e(ucfirst($dept)) ?>"
                data-export-customer="<?= e((string) $r['full_name']) ?>"
                data-export-pickup-date="<?= e($vkVbFormatDt($r['pickup_at'] ?? null)) ?>"
                data-export-return-date="<?= e($vkVbFormatDt($r['return_at'] ?? null)) ?>"
                data-export-pickup-loc="<?= e((string) $r['pickup_location']) ?>"
                data-export-dest="<?= e((string) ($r['drop_location'] ?? '—')) ?>"
                data-export-purpose="<?= e($purpose) ?>"
                data-export-distance="<?= e(number_format((float) $r['distance_km'], 1)) ?> km"
                data-export-fuel="<?= e($fuel['label']) ?>"
                data-export-status="<?= e($uiSt['label']) ?>"
                data-export-approval="<?= e($approval['label']) ?>"
                data-export-cost="<?= e(formatCurrency($r['total_amount'])) ?>"
                data-export-created="<?= e($vkVbFormatDt($r['created_at'] ?? null)) ?>">
                <td class="vk-vb-sticky-col vk-vb-sticky-check" onclick="event.stopPropagation()">
                    <input type="checkbox" class="form-check-input vk-vb-row-check">
                </td>
                <td class="vk-vb-sticky-col vk-vb-sticky-no">
                    <button type="button" class="vk-vb-name vk-vb-name-btn vk-vb-highlight-target" data-vb-drawer="<?= $drawerJson ?>"><code><?= e((string) $r['booking_ref']) ?></code></button>
                </td>
                <td>
                    <div class="vk-vb-vehicle">
                        <span class="vk-vb-vehicle-thumb"><i class="bi <?= e($vkVbVehicleIcon($vtype)) ?>"></i></span>
                        <div class="min-w-0">
                            <div class="vk-vb-name"><?= e((string) ($r['vehicle_name'] ?? 'Unassigned')) ?></div>
                            <div class="vk-vb-sub d-md-none"><?= e($regNo) ?></div>
                        </div>
                    </div>
                </td>
                <td class="vk-vb-col-hide-md vk-vb-sub"><?= e($regNo) ?></td>
                <td class="vk-vb-col-hide-lg"><span class="vk-vb-sub"><?= e(ucfirst($vtype)) ?></span></td>
                <td>
                    <?php if ((string) ($r['driver_name'] ?? '') !== ''): ?>
                    <div class="vk-vb-driver">
                        <span class="vk-vb-driver-av"><?= e($vkVbInitials((string) $r['driver_name'])) ?></span>
                        <div class="min-w-0"><div class="vk-vb-name"><?= e((string) $r['driver_name']) ?></div><div class="vk-vb-sub"><?= e((string) $r['phone']) ?></div></div>
                    </div>
                    <?php else: ?>
                    <span class="vk-vb-sub">Unassigned</span>
                    <?php endif; ?>
                </td>
                <td class="vk-vb-col-hide-md"><span class="<?= $dept === 'hire' ? 'vk-vb-dept-hire' : 'vk-vb-dept-rental' ?>"><?= e(ucfirst($dept)) ?></span></td>
                <td>
                    <div class="vk-vb-name vk-vb-highlight-target"><?= e((string) $r['full_name']) ?></div>
                    <div class="vk-vb-sub"><?= e((string) $r['phone']) ?></div>
                </td>
                <td><span class="vk-vb-date"><?= e($vkVbFormatDt($r['pickup_at'] ?? null)) ?></span></td>
                <td class="vk-vb-col-hide-md"><span class="vk-vb-date"><?= e($vkVbFormatDt($r['return_at'] ?? null)) ?></span></td>
                <td class="vk-vb-col-hide-lg"><span class="vk-vb-sub"><?= e((string) $r['pickup_location']) ?></span></td>
                <td class="vk-vb-col-hide-lg"><span class="vk-vb-sub"><?= e((string) ($r['drop_location'] ?? '—')) ?></span></td>
                <td class="vk-vb-col-hide-lg"><span class="vk-vb-sub"><?= e($purpose) ?></span></td>
                <td class="vk-vb-col-hide-md"><span class="vk-vb-sub"><?= e(number_format((float) $r['distance_km'], 1)) ?> km</span></td>
                <td class="vk-vb-col-hide-lg"><span class="<?= e($fuel['class']) ?> fw-semibold"><?= e($fuel['label']) ?></span></td>
                <td><span class="vk-vb-badge <?= e($uiSt['class']) ?>"><?= e($uiSt['label']) ?></span></td>
                <td class="vk-vb-col-hide-md"><?php if ($approval['class'] !== ''): ?><span class="vk-vb-badge <?= e($approval['class']) ?>"><?= e($approval['label']) ?></span><?php else: ?>—<?php endif; ?></td>
                <td class="vk-vb-col-hide-md"><span class="vk-vb-amt"><?= e(formatCurrency($r['total_amount'])) ?></span></td>
                <td class="vk-vb-col-hide-lg"><span class="vk-vb-date"><?= e(date('d M Y', strtotime((string) ($r['created_at'] ?? 'now')) ?: time())) ?></span></td>
                <td onclick="event.stopPropagation()">
                    <div class="vk-vb-actions">
                        <button type="button" class="vk-vb-act" data-vb-drawer="<?= $drawerJson ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></button>
                        <div class="vk-vb-assign-wrap position-relative d-inline-block">
                            <button type="button" class="vk-vb-act vk-vb-assign-toggle" data-bs-toggle="tooltip" title="Edit / Assign driver"><i class="bi bi-pencil"></i></button>
                            <div class="vk-vb-assign-popover">
                                <form method="post" class="vk-vb-assign-form">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <select class="form-select form-select-sm" name="vehicle_id" aria-label="Vehicle">
                                        <option value="0">Vehicle</option>
                                        <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= (int) $v['id'] ?>" <?= $vehId === (int) $v['id'] ? 'selected' : '' ?>><?= e((string) $v['vehicle_name']) ?> (<?= e((string) $v['registration_number']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select class="form-select form-select-sm" name="driver_id" aria-label="Driver">
                                        <option value="0">Driver</option>
                                        <?php foreach ($drivers as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>" <?= $drvId === (int) $d['id'] ? 'selected' : '' ?>><?= e((string) $d['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select class="form-select form-select-sm" name="status" aria-label="Status">
                                        <?php foreach (['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'] as $s): ?>
                                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-primary w-100" type="submit">Save</button>
                                </form>
                            </div>
                        </div>
                        <?php if ($mapPickup !== ''): ?>
                        <a class="vk-vb-act" href="<?= e($mapPickup) ?>" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="Track"><i class="bi bi-geo-alt"></i></a>
                        <a class="vk-vb-act" href="<?= e($mapDrop !== '' ? $mapDrop : $mapPickup) ?>" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="Route"><i class="bi bi-map"></i></a>
                        <?php else: ?>
                        <span class="vk-vb-act" aria-disabled="true" title="No GPS"><i class="bi bi-geo-alt"></i></span>
                        <?php endif; ?>
                        <button type="button" class="vk-vb-act" data-vb-drawer="<?= $drawerJson ?>" data-bs-toggle="tooltip" title="Trip sheet"><i class="bi bi-file-earmark-text"></i></button>
                        <a class="vk-vb-act" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php" data-bs-toggle="tooltip" title="Fuel log"><i class="bi bi-fuel-pump"></i></a>
                        <span class="vk-vb-act" aria-disabled="true" title="Upload photos"><i class="bi bi-camera"></i></span>
                        <button type="button" class="vk-vb-act" onclick="window.print()" data-bs-toggle="tooltip" title="Print"><i class="bi bi-printer"></i></button>
                        <span class="vk-vb-act" aria-disabled="true" title="Delete not available"><i class="bi bi-trash"></i></span>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="vk-vb-mobile-only">
    <?php foreach ($rows as $r):
        $status = (string) ($r['status'] ?? 'pending');
        $uiSt = $vkVbStatusUi($status);
        $vehId = (int) ($r['vehicle_id'] ?? 0);
        $drvId = (int) ($r['driver_id'] ?? 0);
        $pickupDate = $vkVbFormatDate($r['pickup_at'] ?? null);
        $returnDate = $vkVbFormatDate($r['return_at'] ?? null);
        $drawerData = [
            'ref' => (string) $r['booking_ref'],
            'customer' => (string) $r['full_name'],
            'phone' => (string) $r['phone'],
            'vehicle' => (string) ($r['vehicle_name'] ?? 'Unassigned'),
            'reg' => $vehId > 0 && isset($vehicleRegMap[$vehId]) ? $vehicleRegMap[$vehId] : '—',
            'driver' => (string) ($r['driver_name'] ?? 'Unassigned'),
            'pickup' => (string) $r['pickup_location'],
            'drop' => (string) ($r['drop_location'] ?? '—'),
            'pickupDate' => $vkVbFormatDt($r['pickup_at'] ?? null),
            'returnDate' => $vkVbFormatDt($r['return_at'] ?? null),
            'distance' => number_format((float) $r['distance_km'], 1) . ' km',
            'cost' => formatCurrency((float) $r['total_amount']),
            'status' => $uiSt['label'],
            'notes' => (string) ($r['special_notes'] ?? '—'),
            'vtype' => ucfirst((string) ($r['vehicle_type'] ?? 'car')),
            'image' => '',
        ];
        $drawerJson = htmlspecialchars(json_encode($drawerData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
    ?>
    <article class="vk-vb-mobile-card" data-booking-id="<?= (int) $r['id'] ?>"
        data-status="<?= e($uiSt['key']) ?>"
        data-vehicle-id="<?= $vehId > 0 ? (int) $vehId : '' ?>"
        data-driver-id="<?= $drvId > 0 ? (int) $drvId : '' ?>"
        data-vehicle-type="<?= e((string) ($r['vehicle_type'] ?? 'car')) ?>"
        data-dept="<?= e((string) ($r['booking_type'] ?? 'rental')) ?>"
        data-priority="<?= e($vkVbPriority($r)) ?>"
        data-pickup-date="<?= e($pickupDate) ?>"
        data-return-date="<?= e($returnDate) ?>">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="vk-vb-vehicle-thumb"><i class="bi bi-car-front"></i></span>
            <div class="flex-grow-1 min-w-0">
                <button type="button" class="vk-vb-name vk-vb-name-btn" data-vb-drawer="<?= $drawerJson ?>"><code><?= e((string) $r['booking_ref']) ?></code></button>
                <div class="vk-vb-sub"><?= e((string) ($r['vehicle_name'] ?? 'Unassigned')) ?> · <?= e((string) $r['full_name']) ?></div>
            </div>
            <span class="vk-vb-badge <?= e($uiSt['class']) ?>"><?= e($uiSt['label']) ?></span>
        </div>
        <dl class="vk-vb-mobile-grid">
            <dt>Pickup</dt><dd><?= e($vkVbFormatDt($r['pickup_at'] ?? null)) ?></dd>
            <dt>Cost</dt><dd class="vk-vb-amt"><?= e(formatCurrency($r['total_amount'])) ?></dd>
            <dt>Driver</dt><dd><?= e((string) ($r['driver_name'] ?? '—')) ?></dd>
            <dt>Distance</dt><dd><?= e(number_format((float) $r['distance_km'], 1)) ?> km</dd>
        </dl>
        <div class="vk-vb-assign-wrap position-relative">
            <button type="button" class="vk-vb-btn vk-vb-assign-toggle w-100"><i class="bi bi-pencil"></i> Assign &amp; update</button>
            <div class="vk-vb-assign-popover vk-vb-assign-popover-mobile">
                <form method="post">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <select class="form-select form-select-sm mb-1" name="vehicle_id">
                        <option value="0">Vehicle</option>
                        <?php foreach ($vehicles as $v): ?>
                        <option value="<?= (int) $v['id'] ?>" <?= $vehId === (int) $v['id'] ? 'selected' : '' ?>><?= e((string) $v['vehicle_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm mb-1" name="driver_id">
                        <option value="0">Driver</option>
                        <?php foreach ($drivers as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= $drvId === (int) $d['id'] ? 'selected' : '' ?>><?= e((string) $d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm mb-1" name="status">
                        <?php foreach (['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary w-100" type="submit">Save</button>
                </form>
            </div>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<footer class="vk-vb-footer">
    <span id="vkVbPageInfo">Showing 1–<?= min(25, count($rows)) ?> of <?= count($rows) ?></span>
    <nav class="vk-vb-page-nav" id="vkVbPageNav" aria-label="Pagination"></nav>
</footer>
<?php endif; ?>
</div>

<div class="vk-vb-drawer-backdrop" id="vkVbDrawerBackdrop" aria-hidden="true"></div>
<aside class="vk-vb-drawer" id="vkVbDrawer" aria-hidden="true" aria-label="Booking details">
    <div class="vk-vb-drawer-head">
        <span class="vk-vb-vehicle-thumb" id="vkVbDrawerThumb" style="width:48px;height:48px;font-size:20px"><i class="bi bi-car-front"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h2 class="h6 mb-0 fw-bold" id="vkVbDrawerRef">—</h2>
            <p class="small text-muted mb-0"><span id="vkVbDrawerStatus">—</span> · <span id="vkVbDrawerType">—</span></p>
        </div>
        <button type="button" class="vk-vb-drawer-close" id="vkVbDrawerClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="vk-vb-drawer-scroll">
        <h3 class="vk-vb-section-title">Trip</h3>
        <div class="vk-vb-stat-grid mb-3">
            <div class="vk-vb-stat"><div class="vk-vb-stat-label">Pickup</div><div class="vk-vb-stat-value" id="vkVbDrawerPickupDate">—</div></div>
            <div class="vk-vb-stat"><div class="vk-vb-stat-label">Return</div><div class="vk-vb-stat-value" id="vkVbDrawerReturnDate">—</div></div>
            <div class="vk-vb-stat"><div class="vk-vb-stat-label">Distance</div><div class="vk-vb-stat-value" id="vkVbDrawerDistance">—</div></div>
            <div class="vk-vb-stat"><div class="vk-vb-stat-label">Cost</div><div class="vk-vb-stat-value" id="vkVbDrawerCost">—</div></div>
        </div>
        <p class="small mb-1"><i class="bi bi-geo-alt me-2 text-muted"></i><span id="vkVbDrawerPickup">—</span></p>
        <p class="small mb-3"><i class="bi bi-flag me-2 text-muted"></i><span id="vkVbDrawerDrop">—</span></p>
        <h3 class="vk-vb-section-title">Vehicle</h3>
        <div class="vk-vb-stat-grid mb-3">
            <div class="vk-vb-stat"><div class="vk-vb-stat-label">Name</div><div class="vk-vb-stat-value" id="vkVbDrawerVehicle">—</div></div>
            <div class="vk-vb-stat"><div class="vk-vb-stat-label">Registration</div><div class="vk-vb-stat-value" id="vkVbDrawerReg">—</div></div>
        </div>
        <h3 class="vk-vb-section-title">Customer &amp; driver</h3>
        <p class="small mb-1"><strong id="vkVbDrawerCustomer">—</strong></p>
        <p class="small mb-1"><i class="bi bi-telephone me-2"></i><span id="vkVbDrawerPhone">—</span></p>
        <p class="small mb-3"><i class="bi bi-person-gear me-2"></i><span id="vkVbDrawerDriver">—</span></p>
        <h3 class="vk-vb-section-title">Notes</h3>
        <p class="small mb-3" id="vkVbDrawerNotes">—</p>
        <h3 class="vk-vb-section-title">VK modules</h3>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="vk-vb-mod" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php">Vehicles</a>
            <a class="vk-vb-mod" href="<?= e(BASE_URL) ?>/modules/drivers/list.php">Drivers</a>
            <a class="vk-vb-mod" href="<?= e(BASE_URL) ?>/modules/bookings/list.php">Bookings</a>
            <a class="vk-vb-mod" href="<?= e(BASE_URL) ?>/modules/customers/list.php">Customers</a>
            <a class="vk-vb-mod" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">Maintenance</a>
        </div>
    </div>
</aside>

</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/vehicle-bookings-list.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
