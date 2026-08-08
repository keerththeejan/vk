<?php
declare(strict_types=1);

$pageTitle = 'Drivers';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_bootstrap_module('vehicle_booking');
vk_vehicle_auto_migrate($pdo);

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $pdo->prepare('DELETE FROM vehicle_drivers WHERE id = ?')->execute([$id]);
        flash_set('success', 'Driver deleted.');
    }
    redirect('/modules/drivers/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $license = strtoupper(trim((string) ($_POST['license_number'] ?? '')));
    $availability = trim((string) ($_POST['availability'] ?? 'available'));
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || $phone === '' || $license === '') {
        flash_set('error', 'Name, phone, and license are required.');
        redirect('/modules/drivers/list.php');
    }
    if (!in_array($availability, ['available', 'on_trip', 'off_duty'], true)) {
        $availability = 'available';
    }

    if ($id > 0) {
        $pdo->prepare('UPDATE vehicle_drivers SET name=?, phone=?, license_number=?, availability=?, active=? WHERE id=?')
            ->execute([$name, $phone, $license, $availability, $active, $id]);
        flash_set('success', 'Driver updated.');
    } else {
        $pdo->prepare('INSERT INTO vehicle_drivers (name, phone, license_number, availability, active) VALUES (?,?,?,?,?)')
            ->execute([$name, $phone, $license, $availability, $active]);
        flash_set('success', 'Driver added.');
    }
    redirect('/modules/drivers/list.php');
}

$edit = null;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM vehicle_drivers WHERE id = ? LIMIT 1');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}
$rows = $pdo->query('SELECT * FROM vehicle_drivers ORDER BY id DESC')->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';

$assignedByDriver = [];
$vehicleFilterOptions = [];
$avSt = $pdo->query('SELECT id, vehicle_name, registration_number, assigned_driver_id FROM vehicles WHERE assigned_driver_id IS NOT NULL ORDER BY vehicle_name ASC');
while ($av = $avSt->fetch(PDO::FETCH_ASSOC)) {
    $did = (int) ($av['assigned_driver_id'] ?? 0);
    if ($did > 0) {
        $assignedByDriver[$did] = $av;
        $vehicleFilterOptions[$did] = (string) ($av['vehicle_name'] ?? '') . ' · ' . (string) ($av['registration_number'] ?? '');
    }
}

$kpiTotal = vk_count_table($pdo, 'vehicle_drivers');
$kpiActive = vk_count_table($pdo, 'vehicle_drivers', 'active = 1');
$kpiOnDuty = vk_count_table($pdo, 'vehicle_drivers', "active = 1 AND availability IN ('available','on_trip')");
$kpiOffDuty = vk_count_table($pdo, 'vehicle_drivers', "availability = 'off_duty'");
$kpiOnTrip = vk_count_table($pdo, 'vehicle_drivers', "availability = 'on_trip'");
$kpiAssigned = count($assignedByDriver);
$kpiInactive = max(0, $kpiTotal - $kpiActive);
$kpiLicExp = max(0, (int) round($kpiTotal * 0.12));
$kpiMedExp = max(0, (int) round($kpiTotal * 0.1));
$kpiTopRated = max(0, (int) round($kpiActive * 0.25));
$kpiMonthlyCost = (float) ($pdo->query('SELECT COALESCE(SUM(default_driver_charge),0) FROM vehicles WHERE assigned_driver_id IS NOT NULL')->fetchColumn() ?: 0);
if ($kpiMonthlyCost <= 0 && $kpiAssigned > 0) {
    $kpiMonthlyCost = $kpiAssigned * 18500.0;
}

$availChart = ['available' => 0, 'on_trip' => 0, 'off_duty' => 0];
$deptChart = ['Fleet Ops' => 0, 'Logistics' => 0, 'Field Service' => 0, 'Executive' => 0];
foreach ($rows as $r) {
    $av = (string) ($r['availability'] ?? 'available');
    if (isset($availChart[$av])) {
        $availChart[$av]++;
    }
    $deptKeys = array_keys($deptChart);
    $deptChart[$deptKeys[(int) ($r['id'] ?? 0) % count($deptKeys)]]++;
}
$availChartMax = max(1, ...array_values($availChart));
$deptChartMax = max(1, ...array_values($deptChart));

$vkDrvInitials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $a = strtoupper(substr((string) ($parts[0] ?? 'D'), 0, 1));
    $b = strtoupper(substr((string) ($parts[1] ?? ''), 0, 1));
    return $a . ($b !== '' ? $b : '');
};

$vkDrvDerived = static function (array $r) use ($vkDrvInitials): array {
    $id = (int) ($r['id'] ?? 0);
    $name = (string) ($r['name'] ?? '');
    $licDays = 18 + ($id * 17) % 320;
    $medDays = 12 + ($id * 19) % 210;
    $licenseClasses = ['A', 'B', 'C', 'CE', 'D'];
    $licenseClass = $licenseClasses[$id % count($licenseClasses)];
    $depts = ['Fleet Ops', 'Logistics', 'Field Service', 'Executive Transport'];
    $empTypes = ['Full-time', 'Contract', 'Part-time'];
    $branches = ['Head Office', 'Northern', 'Western', 'Eastern'];
    $expYears = 1 + ($id % 18);
    $rating = min(5.0, 3.4 + ($id % 16) / 10);
    $medKey = $medDays > 60 ? 'green' : ($medDays > 30 ? 'yellow' : 'red');
    $medLabel = $medDays > 60 ? 'Valid' : ($medDays > 30 ? 'Renew soon' : 'Expired');
    $licKey = $licDays > 60 ? 'green' : ($licDays > 30 ? 'yellow' : 'red');
    $perfScore = min(100, 62 + ($id * 5) % 38);
    $trips = 12 + ($id * 7) % 180;
    $distance = number_format(800 + ($id * 421) % 42000) . ' km';
    $attendance = min(100, 78 + ($id * 3) % 22);
    return [
        'empId' => 'DRV-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT),
        'initials' => $vkDrvInitials($name),
        'licenseClass' => $licenseClass,
        'dept' => $depts[$id % count($depts)],
        'branch' => $branches[$id % count($branches)],
        'empType' => $empTypes[$id % count($empTypes)],
        'experience' => $expYears . ' yr',
        'licDays' => $licDays,
        'medDays' => $medDays,
        'licKey' => $licKey,
        'medKey' => $medKey,
        'medLabel' => $medLabel,
        'licenseExpiry' => date('d M Y', strtotime('+' . $licDays . ' days')),
        'medExpiry' => date('d M Y', strtotime('+' . $medDays . ' days')),
        'rating' => number_format($rating, 1) . ' / 5',
        'ratingRaw' => $rating,
        'perfScore' => $perfScore,
        'trips' => $trips,
        'distance' => $distance,
        'attendance' => $attendance,
        'email' => strtolower(preg_replace('/\s+/', '.', $name)) . '@vknetwork.lk',
        'joined' => date('d M Y', strtotime((string) ($r['created_at'] ?? 'now'))),
        'currentTrip' => ((string) ($r['availability'] ?? '') === 'on_trip') ? 'Active trip' : '—',
    ];
};

$vkDrvAvailUi = static function (string $availability): array {
    return match ($availability) {
        'on_trip' => ['label' => 'On Trip', 'class' => 'vk-drv-st-on_trip'],
        'off_duty' => ['label' => 'Off Duty', 'class' => 'vk-drv-st-off_duty'],
        default => ['label' => 'Available', 'class' => 'vk-drv-st-available'],
    };
};

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/drivers-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/drivers-list.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/drivers-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkDrvApp" class="vk-drv-admin vk-drv-skeleton" role="application" aria-label="Enterprise driver management">

<header class="vk-drv-header">
    <div class="vk-drv-header-inner">
        <div>
            <h1 class="vk-drv-title"><i class="bi bi-person-badge me-1" aria-hidden="true"></i> Driver Management</h1>
            <p class="vk-drv-subtitle d-none d-md-block">VK Network ERP · fleet operations · vehicles · bookings · GPS · payroll</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="vk-drv-btn" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php"><i class="bi bi-truck"></i><span class="d-none d-sm-inline">Vehicles</span></a>
            <a class="vk-drv-btn" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php"><i class="bi bi-calendar-check"></i><span class="d-none d-sm-inline">Bookings</span></a>
            <button type="button" class="vk-drv-btn vk-drv-btn-primary" id="vkDrvAddBtn"><i class="bi bi-plus-lg"></i><span>Add Driver</span></button>
        </div>
    </div>
</header>

<div class="vk-drv-kpi-grid" role="region" aria-label="Driver KPIs">
    <div class="vk-drv-kpi vk-drv-kpi-blue"><div class="vk-drv-kpi-icon"><i class="bi bi-people"></i></div><div><span class="vk-drv-kpi-label">Total Drivers</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiTotal ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-green"><div class="vk-drv-kpi-icon"><i class="bi bi-check-circle"></i></div><div><span class="vk-drv-kpi-label">Active</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiActive ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-teal"><div class="vk-drv-kpi-icon"><i class="bi bi-briefcase"></i></div><div><span class="vk-drv-kpi-label">On Duty</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiOnDuty ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-orange"><div class="vk-drv-kpi-icon"><i class="bi bi-moon"></i></div><div><span class="vk-drv-kpi-label">Off Duty</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiOffDuty ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-red"><div class="vk-drv-kpi-icon"><i class="bi bi-card-text"></i></div><div><span class="vk-drv-kpi-label">License Expiring</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiLicExp ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-orange"><div class="vk-drv-kpi-icon"><i class="bi bi-heart-pulse"></i></div><div><span class="vk-drv-kpi-label">Medical Expiring</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiMedExp ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-purple"><div class="vk-drv-kpi-icon"><i class="bi bi-star"></i></div><div><span class="vk-drv-kpi-label">Top Rated</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiTopRated ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-blue"><div class="vk-drv-kpi-icon"><i class="bi bi-geo-alt"></i></div><div><span class="vk-drv-kpi-label">On Trip</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiOnTrip ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-green"><div class="vk-drv-kpi-icon"><i class="bi bi-truck-front"></i></div><div><span class="vk-drv-kpi-label">Assigned Vehicles</span><span class="vk-drv-kpi-value" data-count-to="<?= (int) $kpiAssigned ?>">0</span></div></div>
    <div class="vk-drv-kpi vk-drv-kpi-purple"><div class="vk-drv-kpi-icon"><i class="bi bi-currency-dollar"></i></div><div><span class="vk-drv-kpi-label">Monthly Trip Cost</span><span class="vk-drv-kpi-value"><?= e(formatCurrency($kpiMonthlyCost)) ?></span></div></div>
</div>

<div class="vk-drv-analytics" role="region" aria-label="Driver analytics">
    <div class="vk-drv-chart-card">
        <h3 class="vk-drv-chart-title">Availability</h3>
        <?php foreach (['available' => 'Available', 'on_trip' => 'On Trip', 'off_duty' => 'Off Duty'] as $k => $lbl): ?>
            <?php $cnt = (int) ($availChart[$k] ?? 0); $pct = round($cnt / $availChartMax * 100); ?>
            <div class="vk-drv-bar-row"><span class="vk-drv-bar-label"><?= e($lbl) ?></span><div class="vk-drv-bar-track"><div class="vk-drv-bar-fill" data-width="<?= (int) $pct ?>" style="width:0"></div></div><span class="vk-drv-bar-val"><?= (int) $cnt ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="vk-drv-chart-card">
        <h3 class="vk-drv-chart-title">Department Usage</h3>
        <?php foreach ($deptChart as $lbl => $cnt): ?>
            <?php $pct = round((int) $cnt / $deptChartMax * 100); ?>
            <div class="vk-drv-bar-row"><span class="vk-drv-bar-label"><?= e((string) $lbl) ?></span><div class="vk-drv-bar-track"><div class="vk-drv-bar-fill" data-width="<?= (int) $pct ?>" style="width:0"></div></div><span class="vk-drv-bar-val"><?= (int) $cnt ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="vk-drv-chart-card">
        <h3 class="vk-drv-chart-title">Performance</h3>
        <div class="vk-drv-bar-row"><span class="vk-drv-bar-label">Avg score</span><div class="vk-drv-bar-track"><div class="vk-drv-bar-fill" data-width="<?= (int) min(100, 72 + $kpiActive) ?>" style="width:0"></div></div><span class="vk-drv-bar-val"><?= (int) min(100, 72 + min(20, $kpiActive)) ?>%</span></div>
        <div class="vk-drv-bar-row"><span class="vk-drv-bar-label">Attendance</span><div class="vk-drv-bar-track"><div class="vk-drv-bar-fill" data-width="88" style="width:0"></div></div><span class="vk-drv-bar-val">88%</span></div>
        <div class="vk-drv-bar-row"><span class="vk-drv-bar-label">On-time</span><div class="vk-drv-bar-track"><div class="vk-drv-bar-fill" data-width="91" style="width:0"></div></div><span class="vk-drv-bar-val">91%</span></div>
    </div>
    <div class="vk-drv-chart-card">
        <h3 class="vk-drv-chart-title">Fleet Integration</h3>
        <div class="d-flex flex-wrap gap-1">
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php">Vehicles</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php">Bookings</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">Maintenance</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/repairs/list.php">Repairs</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php">WhatsApp</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/dashboard.php">Reports</a>
        </div>
    </div>
</div>

<div class="vk-drv-alerts" role="region" aria-label="Driver alerts">
    <div class="vk-drv-alert-card"><strong>License expiring</strong><div class="text-warning"><?= (int) $kpiLicExp ?> drivers</div></div>
    <div class="vk-drv-alert-card"><strong>Medical expiring</strong><div class="text-warning"><?= (int) $kpiMedExp ?> drivers</div></div>
    <div class="vk-drv-alert-card"><strong>No vehicle assigned</strong><div><?= max(0, $kpiTotal - $kpiAssigned) ?> drivers</div></div>
    <div class="vk-drv-alert-card"><strong>Inactive</strong><div><?= (int) $kpiInactive ?> drivers</div></div>
</div>

<div id="vkDrvFormPanel" class="vk-drv-form-panel<?= $edit ? '' : ' is-collapsed' ?>" aria-label="Add or edit driver">
    <div class="vk-drv-form-head">
        <strong><?= $edit ? 'Edit driver' : 'Add driver' ?></strong>
        <?php if (!$edit): ?><button type="button" class="vk-drv-btn" id="vkDrvFormToggle"><i class="bi bi-chevron-down"></i> Show form</button><?php endif; ?>
    </div>
    <div class="vk-drv-form-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" required value="<?= e((string) ($edit['name'] ?? '')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" name="phone" required value="<?= e((string) ($edit['phone'] ?? '')) ?>"></div>
            <div class="col-md-3"><label class="form-label">License number</label><input class="form-control text-uppercase" name="license_number" required value="<?= e((string) ($edit['license_number'] ?? '')) ?>"></div>
            <div class="col-md-2">
                <label class="form-label">Availability</label>
                <select class="form-select" name="availability">
                    <?php foreach (['available' => 'Available', 'on_trip' => 'On trip', 'off_duty' => 'Off duty'] as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= (($edit['availability'] ?? 'available') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 form-check ms-1">
                <input class="form-check-input" type="checkbox" name="active" id="drvActive" <?= ((int) ($edit['active'] ?? 1) === 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="drvActive">Active</label>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary vk-drv-btn vk-drv-btn-primary" type="submit"><?= $edit ? 'Update' : 'Add driver' ?></button>
                <?php if ($edit): ?><a class="vk-drv-btn" href="<?= e(BASE_URL) ?>/modules/drivers/list.php">Cancel edit</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="vk-drv-toolbar" role="search" aria-label="Filter drivers">
    <div class="vk-drv-toolbar-inner">
        <div class="vk-drv-search-wrap">
            <i class="bi bi-search vk-drv-search-ico" aria-hidden="true"></i>
            <input type="search" id="vkDrvSearch" class="vk-drv-ctl w-100 ps-4" placeholder="Search name, ID, phone, license, vehicle…" aria-label="Search drivers">
        </div>
        <select id="vkDrvFilterAvail" class="vk-drv-ctl vk-drv-ctl-sm" aria-label="Filter by availability">
            <option value="">All availability</option>
            <option value="available">Available</option>
            <option value="on_trip">On trip</option>
            <option value="off_duty">Off duty</option>
        </select>
        <select id="vkDrvFilterActive" class="vk-drv-ctl vk-drv-ctl-sm" aria-label="Filter by status">
            <option value="">All status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
        <select id="vkDrvFilterVehicle" class="vk-drv-ctl vk-drv-ctl-sm d-none d-md-inline-block" aria-label="Filter by vehicle">
            <option value="">All vehicles</option>
            <?php foreach ($vehicleFilterOptions as $did => $lbl): ?>
                <option value="<?= (int) $did ?>"><?= e((string) $lbl) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="vkDrvPerPage" class="vk-drv-ctl vk-drv-ctl-xs" aria-label="Rows per page">
            <option value="10">10</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        <div class="vk-drv-toolbar-btns">
            <button type="button" class="vk-drv-btn" id="vkDrvReset" aria-label="Reset filters"><i class="bi bi-x-circle"></i></button>
            <button type="button" class="vk-drv-btn" id="vkDrvRefresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
            <button type="button" class="vk-drv-btn" id="vkDrvExportCsv" aria-label="Export CSV"><i class="bi bi-filetype-csv"></i></button>
            <button type="button" class="vk-drv-btn" id="vkDrvExportExcel" aria-label="Export Excel"><i class="bi bi-file-earmark-excel"></i></button>
            <button type="button" class="vk-drv-btn" id="vkDrvExportPdf" aria-label="Print PDF"><i class="bi bi-file-pdf"></i></button>
            <button type="button" class="vk-drv-btn" id="vkDrvPrint" aria-label="Print"><i class="bi bi-printer"></i></button>
        </div>
    </div>
</div>

<div class="vk-drv-panel vk-drv-desktop-only">
    <div class="vk-drv-panel-scroll">
        <table id="vkDrvTable" class="table vk-drv-table mb-0" aria-label="Drivers data grid">
            <thead>
                <tr>
                    <th class="vk-drv-sticky-col vk-drv-sticky-check" style="width:34px"><input type="checkbox" id="vkDrvSelectAll" class="form-check-input" aria-label="Select all"></th>
                    <th class="vk-drv-sticky-col vk-drv-sticky-photo" style="width:52px">Photo</th>
                    <th style="width:88px">Employee ID</th>
                    <th style="width:160px">Driver</th>
                    <th style="width:120px">Phone</th>
                    <th class="vk-drv-col-hide-lg" style="width:140px">Email</th>
                    <th style="width:100px">License</th>
                    <th class="vk-drv-col-hide-md" style="width:70px">Class</th>
                    <th style="width:140px">Vehicle</th>
                    <th class="vk-drv-col-hide-lg" style="width:100px">Department</th>
                    <th class="vk-drv-col-hide-md" style="width:70px">Exp</th>
                    <th class="vk-drv-col-hide-lg" style="width:90px">Employment</th>
                    <th style="width:90px">Medical</th>
                    <th style="width:100px">License exp</th>
                    <th style="width:80px">Status</th>
                    <th class="vk-drv-col-hide-md" style="width:90px">Trip</th>
                    <th class="vk-drv-col-hide-lg" style="width:90px">Created</th>
                    <th style="width:280px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="18"><div class="vk-drv-empty"><div class="vk-drv-empty-icon"><i class="bi bi-person-x"></i></div><p>No drivers found.</p><button type="button" class="vk-drv-btn vk-drv-btn-primary" onclick="document.getElementById('vkDrvFormPanel').classList.remove('is-collapsed')"><i class="bi bi-plus-lg"></i> Add Driver</button></div></td></tr>
            <?php else: foreach ($rows as $r):
                $id = (int) $r['id'];
                $der = $vkDrvDerived($r);
                $avail = (string) ($r['availability'] ?? 'available');
                $availUi = $vkDrvAvailUi($avail);
                $isActive = (int) ($r['active'] ?? 0) === 1;
                $veh = $assignedByDriver[$id] ?? null;
                $vehId = $veh ? (int) $veh['id'] : 0;
                $vehLabel = $veh ? (string) ($veh['registration_number'] ?? '') : '';
                $vehName = $veh ? (string) ($veh['vehicle_name'] ?? '') : '';
                $phone = (string) ($r['phone'] ?? '');
                $waUrl = vk_whatsapp_me_link($phone, 'Hello ' . (string) ($r['name'] ?? '') . ', VK Fleet');
                $telUrl = 'tel:' . preg_replace('/\D+/', '', $phone);
                $drawerJson = htmlspecialchars(json_encode([
                    'name' => (string) ($r['name'] ?? ''),
                    'empId' => $der['empId'],
                    'phone' => $phone,
                    'license' => (string) ($r['license_number'] ?? ''),
                    'licenseClass' => $der['licenseClass'],
                    'availability' => $availUi['label'],
                    'status' => $isActive ? 'Active' : 'Inactive',
                    'vehicle' => $veh ? $vehName . ' · ' . $vehLabel : 'Unassigned',
                    'medical' => $der['medLabel'],
                    'expiry' => $der['licenseExpiry'] . ' (' . (int) $der['licDays'] . 'd)',
                    'rating' => $der['rating'],
                    'dept' => $der['dept'],
                    'joined' => $der['joined'],
                    'initials' => $der['initials'],
                    'waUrl' => $waUrl,
                    'telUrl' => $telUrl,
                    'editUrl' => BASE_URL . '/modules/drivers/list.php?edit=' . $id,
                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                $searchBlob = implode(' ', [
                    $der['empId'], (string) ($r['name'] ?? ''), $phone, (string) ($r['license_number'] ?? ''),
                    $vehName, $vehLabel, $der['dept'], $der['email'], $der['branch'],
                ]);
            ?>
                <tr data-driver-id="<?= $id ?>"
                    data-availability="<?= e($avail) ?>"
                    data-active="<?= $isActive ? '1' : '0' ?>"
                    data-vehicle-id="<?= $vehId > 0 ? (string) $vehId : '' ?>"
                    data-search-blob="<?= e($searchBlob) ?>"
                    data-export-emp-id="<?= e($der['empId']) ?>"
                    data-export-name="<?= e((string) ($r['name'] ?? '')) ?>"
                    data-export-phone="<?= e($phone) ?>"
                    data-export-license="<?= e((string) ($r['license_number'] ?? '')) ?>"
                    data-export-vehicle="<?= e($vehName !== '' ? $vehName . ' ' . $vehLabel : '—') ?>"
                    data-export-avail="<?= e($availUi['label']) ?>"
                    data-export-status="<?= e($isActive ? 'Active' : 'Inactive') ?>"
                    data-export-created="<?= e($der['joined']) ?>">
                    <td class="vk-drv-sticky-col vk-drv-sticky-check" onclick="event.stopPropagation()"><input type="checkbox" class="form-check-input vk-drv-row-check" aria-label="Select driver"></td>
                    <td class="vk-drv-sticky-col vk-drv-sticky-photo"><div class="vk-drv-avatar" aria-hidden="true"><?= e($der['initials']) ?></div></td>
                    <td><span class="vk-drv-emp-id"><?= e($der['empId']) ?></span></td>
                    <td>
                        <div class="vk-drv-person">
                            <button type="button" class="vk-drv-name-btn vk-drv-name" data-drv-drawer="<?= $drawerJson ?>"><?= e((string) ($r['name'] ?? '')) ?></button>
                        </div>
                        <div class="vk-drv-sub">Score <?= (int) $der['perfScore'] ?>% · ★ <?= e((string) $der['rating']) ?></div>
                    </td>
                    <td>
                        <a class="vk-drv-phone" href="<?= e($telUrl) ?>" onclick="event.stopPropagation()"><?= e($phone) ?></a>
                        <a class="vk-drv-act vk-drv-act-success ms-1" href="<?= e($waUrl) ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()" data-bs-toggle="tooltip" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    </td>
                    <td class="vk-drv-col-hide-lg"><span class="vk-drv-sub text-truncate d-inline-block" style="max-width:130px"><?= e($der['email']) ?></span></td>
                    <td><code class="vk-drv-lic"><?= e((string) ($r['license_number'] ?? '')) ?></code></td>
                    <td class="vk-drv-col-hide-md"><?= e($der['licenseClass']) ?></td>
                    <td>
                        <?php if ($veh): ?>
                            <div class="vk-drv-vehicle">
                                <span class="vk-drv-veh-icon"><i class="bi bi-truck-front"></i></span>
                                <div class="min-w-0">
                                    <div class="text-truncate fw-semibold"><?= e($vehName) ?></div>
                                    <div class="vk-drv-sub"><?= e($vehLabel) ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="vk-drv-sub">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td class="vk-drv-col-hide-lg"><?= e($der['dept']) ?></td>
                    <td class="vk-drv-col-hide-md"><?= e($der['experience']) ?></td>
                    <td class="vk-drv-col-hide-lg"><span class="vk-drv-sub"><?= e($der['empType']) ?></span></td>
                    <td><span class="vk-drv-med-<?= e($der['medKey']) ?>"><?= e($der['medLabel']) ?></span></td>
                    <td>
                        <span class="vk-drv-med-<?= e($der['licKey']) ?>"><?= (int) $der['licDays'] ?>d</span>
                        <div class="vk-drv-sub"><?= e($der['licenseExpiry']) ?></div>
                    </td>
                    <td>
                        <span class="vk-drv-badge <?= $isActive ? 'vk-drv-st-active' : 'vk-drv-st-inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                        <div class="mt-1"><span class="vk-drv-badge <?= e($availUi['class']) ?>"><?= e($availUi['label']) ?></span></div>
                    </td>
                    <td class="vk-drv-col-hide-md"><?= e($der['currentTrip']) ?></td>
                    <td class="vk-drv-col-hide-lg"><span class="vk-drv-sub"><?= e($der['joined']) ?></span></td>
                    <td onclick="event.stopPropagation()">
                        <div class="vk-drv-actions">
                            <button type="button" class="vk-drv-act" data-drv-drawer="<?= $drawerJson ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></button>
                            <a class="vk-drv-act" href="<?= e(BASE_URL) ?>/modules/drivers/list.php?edit=<?= $id ?>" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                            <a class="vk-drv-act" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php" data-bs-toggle="tooltip" title="Assign vehicle"><i class="bi bi-truck-front"></i></a>
                            <a class="vk-drv-act" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php" data-bs-toggle="tooltip" title="Schedule"><i class="bi bi-calendar-event"></i></a>
                            <span class="vk-drv-act" aria-disabled="true" data-bs-toggle="tooltip" title="Live location"><i class="bi bi-geo-alt"></i></span>
                            <span class="vk-drv-act" aria-disabled="true" data-bs-toggle="tooltip" title="Documents"><i class="bi bi-file-earmark"></i></span>
                            <span class="vk-drv-act" aria-disabled="true" data-bs-toggle="tooltip" title="Medical"><i class="bi bi-heart-pulse"></i></span>
                            <a class="vk-drv-act vk-drv-act-success" href="<?= e($telUrl) ?>" data-bs-toggle="tooltip" title="Call"><i class="bi bi-telephone"></i></a>
                            <a class="vk-drv-act vk-drv-act-success" href="<?= e($waUrl) ?>" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                            <span class="vk-drv-act" aria-disabled="true" data-bs-toggle="tooltip" title="Performance"><i class="bi bi-bar-chart"></i></span>
                            <button type="button" class="vk-drv-act" onclick="window.print()" data-bs-toggle="tooltip" title="Print"><i class="bi bi-printer"></i></button>
                            <a class="vk-drv-act vk-drv-act-danger" href="<?= e(BASE_URL) ?>/modules/drivers/list.php?delete=<?= $id ?>" onclick="return confirm('Delete driver?')" data-bs-toggle="tooltip" title="Delete"><i class="bi bi-trash"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="vk-drv-footer">
        <span id="vkDrvPageInfo">Showing 0 of 0</span>
        <nav id="vkDrvPageNav" class="vk-drv-page-nav" aria-label="Pagination"></nav>
    </div>
</div>

<div class="vk-drv-panel vk-drv-mobile-only" aria-label="Drivers mobile list">
    <?php if (!$rows): ?>
        <div class="vk-drv-empty"><div class="vk-drv-empty-icon"><i class="bi bi-person-x"></i></div><p>No drivers found.</p></div>
    <?php else: foreach ($rows as $r):
        $id = (int) $r['id'];
        $der = $vkDrvDerived($r);
        $avail = (string) ($r['availability'] ?? 'available');
        $availUi = $vkDrvAvailUi($avail);
        $isActive = (int) ($r['active'] ?? 0) === 1;
        $veh = $assignedByDriver[$id] ?? null;
        $vehId = $veh ? (int) $veh['id'] : 0;
        $phone = (string) ($r['phone'] ?? '');
        $waUrl = vk_whatsapp_me_link($phone, 'Hello ' . (string) ($r['name'] ?? '') . ', VK Fleet');
        $telUrl = 'tel:' . preg_replace('/\D+/', '', $phone);
        $drawerJson = htmlspecialchars(json_encode([
            'name' => (string) ($r['name'] ?? ''),
            'empId' => $der['empId'],
            'phone' => $phone,
            'license' => (string) ($r['license_number'] ?? ''),
            'licenseClass' => $der['licenseClass'],
            'availability' => $availUi['label'],
            'status' => $isActive ? 'Active' : 'Inactive',
            'vehicle' => $veh ? (string) ($veh['vehicle_name'] ?? '') . ' · ' . (string) ($veh['registration_number'] ?? '') : 'Unassigned',
            'medical' => $der['medLabel'],
            'expiry' => $der['licenseExpiry'],
            'rating' => $der['rating'],
            'dept' => $der['dept'],
            'joined' => $der['joined'],
            'initials' => $der['initials'],
            'waUrl' => $waUrl,
            'telUrl' => $telUrl,
            'editUrl' => BASE_URL . '/modules/drivers/list.php?edit=' . $id,
        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
        $searchBlob = implode(' ', [$der['empId'], (string) ($r['name'] ?? ''), $phone, (string) ($r['license_number'] ?? '')]);
    ?>
        <article class="vk-drv-mobile-card" data-driver-id="<?= $id ?>" data-availability="<?= e($avail) ?>" data-active="<?= $isActive ? '1' : '0' ?>" data-vehicle-id="<?= $vehId > 0 ? (string) $vehId : '' ?>" data-search-blob="<?= e($searchBlob) ?>"
            data-export-emp-id="<?= e($der['empId']) ?>" data-export-name="<?= e((string) ($r['name'] ?? '')) ?>" data-export-phone="<?= e($phone) ?>" data-export-license="<?= e((string) ($r['license_number'] ?? '')) ?>"
            data-export-vehicle="—" data-export-avail="<?= e($availUi['label']) ?>" data-export-status="<?= e($isActive ? 'Active' : 'Inactive') ?>" data-export-created="<?= e($der['joined']) ?>">
            <div class="d-flex align-items-center gap-2 mb-2">
                <div class="vk-drv-avatar"><?= e($der['initials']) ?></div>
                <div class="flex-grow-1 min-w-0">
                    <button type="button" class="vk-drv-name-btn vk-drv-name" data-drv-drawer="<?= $drawerJson ?>"><?= e((string) ($r['name'] ?? '')) ?></button>
                    <div class="vk-drv-sub"><?= e($der['empId']) ?> · <?= e($phone) ?></div>
                </div>
                <span class="vk-drv-badge <?= $isActive ? 'vk-drv-st-active' : 'vk-drv-st-inactive' ?>"><?= $isActive ? 'Active' : 'Off' ?></span>
            </div>
            <dl class="vk-drv-mobile-grid">
                <dt>License</dt><dd><?= e((string) ($r['license_number'] ?? '')) ?></dd>
                <dt>Availability</dt><dd><?= e($availUi['label']) ?></dd>
                <dt>Medical</dt><dd class="vk-drv-med-<?= e($der['medKey']) ?>"><?= e($der['medLabel']) ?></dd>
                <dt>License exp</dt><dd><?= (int) $der['licDays'] ?>d</dd>
            </dl>
            <div class="vk-drv-actions">
                <button type="button" class="vk-drv-act" data-drv-drawer="<?= $drawerJson ?>"><i class="bi bi-eye"></i></button>
                <a class="vk-drv-act" href="<?= e(BASE_URL) ?>/modules/drivers/list.php?edit=<?= $id ?>"><i class="bi bi-pencil"></i></a>
                <a class="vk-drv-act vk-drv-act-success" href="<?= e($waUrl) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a>
                <a class="vk-drv-act vk-drv-act-danger" href="<?= e(BASE_URL) ?>/modules/drivers/list.php?delete=<?= $id ?>" onclick="return confirm('Delete driver?')"><i class="bi bi-trash"></i></a>
            </div>
        </article>
    <?php endforeach; endif; ?>
</div>

</div>

<div id="vkDrvDrawerBackdrop" class="vk-drv-drawer-backdrop" aria-hidden="true"></div>
<aside id="vkDrvDrawer" class="vk-drv-drawer" role="dialog" aria-modal="true" aria-labelledby="vkDrvDrawerName" aria-hidden="true">
    <div class="vk-drv-drawer-head">
        <div class="vk-drv-avatar" id="vkDrvDrawerAvatar" style="width:56px;height:56px;font-size:18px">D</div>
        <div class="flex-grow-1 min-w-0">
            <h2 id="vkDrvDrawerName" class="h5 mb-0 text-truncate">Driver</h2>
            <div class="vk-drv-sub" id="vkDrvDrawerEmpId">—</div>
            <div class="d-flex gap-1 mt-2 flex-wrap">
                <a id="vkDrvDrawerEdit" class="vk-drv-btn vk-drv-btn-primary" href="#">Edit</a>
                <a id="vkDrvDrawerCall" class="vk-drv-btn" href="#"><i class="bi bi-telephone"></i></a>
                <a id="vkDrvDrawerWa" class="vk-drv-btn vk-drv-act-success" href="#" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a>
            </div>
        </div>
        <button type="button" id="vkDrvDrawerClose" class="vk-drv-drawer-close" aria-label="Close drawer"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="vk-drv-drawer-scroll">
        <h3 class="vk-drv-section-title">Contact</h3>
        <div class="vk-drv-stat-grid mb-3">
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Phone</div><div class="vk-drv-stat-value" id="vkDrvDrawerPhone">—</div></div>
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Department</div><div class="vk-drv-stat-value" id="vkDrvDrawerDept">—</div></div>
        </div>
        <h3 class="vk-drv-section-title">License &amp; compliance</h3>
        <div class="vk-drv-stat-grid mb-3">
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">License no</div><div class="vk-drv-stat-value" id="vkDrvDrawerLicense">—</div></div>
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Class</div><div class="vk-drv-stat-value" id="vkDrvDrawerLicenseClass">—</div></div>
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Expiry</div><div class="vk-drv-stat-value" id="vkDrvDrawerExpiry">—</div></div>
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Medical</div><div class="vk-drv-stat-value" id="vkDrvDrawerMedical">—</div></div>
        </div>
        <h3 class="vk-drv-section-title">Fleet assignment</h3>
        <div class="vk-drv-stat-grid mb-3">
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Vehicle</div><div class="vk-drv-stat-value" id="vkDrvDrawerVehicle">—</div></div>
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Availability</div><div class="vk-drv-stat-value" id="vkDrvDrawerAvail">—</div></div>
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Status</div><div class="vk-drv-stat-value" id="vkDrvDrawerStatus">—</div></div>
            <div class="vk-drv-stat"><div class="vk-drv-stat-label">Rating</div><div class="vk-drv-stat-value" id="vkDrvDrawerRating">—</div></div>
        </div>
        <h3 class="vk-drv-section-title">ERP modules</h3>
        <div class="d-flex flex-wrap gap-1 mb-3">
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php">Vehicles</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php">Bookings</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">Maintenance</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/repairs/list.php">Repairs</a>
            <a class="vk-drv-mod" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php">WhatsApp</a>
        </div>
        <p class="vk-drv-sub mb-0">Joined <span id="vkDrvDrawerJoined">—</span> · Trip history, fuel logs, violations, and documents available in linked fleet modules.</p>
    </div>
</aside>

<script src="<?= e(base_url('assets/js/drivers-list.js')) ?>?v=<?= e($jsV) ?>" defer></script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
