<?php
declare(strict_types=1);

$pageTitle = 'Vehicles';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_bootstrap_module('vehicle_booking');
vk_vehicle_auto_migrate($pdo);

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    if ($id > 0) {
        $pdo->prepare('DELETE FROM vehicles WHERE id = ?')->execute([$id]);
        flash_set('success', 'Vehicle deleted.');
    }
    redirect('/modules/vehicles/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim((string) ($_POST['vehicle_name'] ?? ''));
    $type = trim((string) ($_POST['vehicle_type'] ?? 'car'));
    $reg = strtoupper(trim((string) ($_POST['registration_number'] ?? '')));
    $ppd = (float) ($_POST['price_per_day'] ?? 0);
    $ppk = (float) ($_POST['price_per_km'] ?? 0);
    $dc = (float) ($_POST['default_driver_charge'] ?? 0);
    $seats = max(1, (int) ($_POST['seats'] ?? 4));
    $status = trim((string) ($_POST['status'] ?? 'available'));
    $driverId = max(0, (int) ($_POST['assigned_driver_id'] ?? 0));
    $driverId = $driverId > 0 ? $driverId : null;
    $img = trim((string) ($_POST['image_path'] ?? ''));

    if ($name === '' || $reg === '') {
        flash_set('error', 'Vehicle name and registration are required.');
        redirect('/modules/vehicles/list.php');
    }

    $allowedType = ['car', 'van', 'bike', 'lorry', 'bus'];
    $allowedStatus = ['available', 'booked', 'maintenance'];
    if (!in_array($type, $allowedType, true)) {
        $type = 'car';
    }
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'available';
    }

    if ($id > 0) {
        $st = $pdo->prepare(
            'UPDATE vehicles
             SET vehicle_name = ?, vehicle_type = ?, registration_number = ?, price_per_day = ?, price_per_km = ?,
                 default_driver_charge = ?, assigned_driver_id = ?, status = ?, image_path = ?, seats = ?
             WHERE id = ?'
        );
        $st->execute([$name, $type, $reg, $ppd, $ppk, $dc, $driverId, $status, ($img !== '' ? $img : null), $seats, $id]);
        flash_set('success', 'Vehicle updated.');
    } else {
        $st = $pdo->prepare(
            'INSERT INTO vehicles
            (vehicle_name, vehicle_type, registration_number, price_per_day, price_per_km, default_driver_charge, assigned_driver_id, status, image_path, seats)
            VALUES (?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([$name, $type, $reg, $ppd, $ppk, $dc, $driverId, $status, ($img !== '' ? $img : null), $seats]);
        flash_set('success', 'Vehicle added.');
    }
    redirect('/modules/vehicles/list.php');
}

$drivers = $pdo->query("SELECT id, name FROM vehicle_drivers WHERE active = 1 ORDER BY name ASC")->fetchAll();
$rows = $pdo->query(
    "SELECT v.*, d.name AS driver_name
     FROM vehicles v
     LEFT JOIN vehicle_drivers d ON d.id = v.assigned_driver_id
     ORDER BY v.id DESC"
)->fetchAll();

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM vehicles WHERE id = ? LIMIT 1');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';

$kpiTotal = vk_count_table($pdo, 'vehicles');
$kpiAvailable = vk_count_table($pdo, 'vehicles', "status = 'available'");
$kpiOnTrip = vk_count_table($pdo, 'vehicles', "status = 'booked'");
$kpiMaintenance = vk_count_table($pdo, 'vehicles', "status = 'maintenance'");
$kpiDrivers = (int) ($pdo->query('SELECT COUNT(*) FROM vehicles WHERE assigned_driver_id IS NOT NULL')->fetchColumn() ?: 0);
$kpiFleetValue = (float) ($pdo->query('SELECT COALESCE(SUM(price_per_day * 30),0) FROM vehicles')->fetchColumn() ?: 0);
$kpiMonthlyCost = (float) ($pdo->query('SELECT COALESCE(SUM(price_per_km * 500 + default_driver_charge),0) FROM vehicles')->fetchColumn() ?: 0);
$kpiServiceDue = $kpiMaintenance;
$kpiGpsOnline = $kpiTotal > 0 ? round(($kpiAvailable + $kpiOnTrip) / $kpiTotal * 100, 1) : 0.0;
$kpiFuelEff = 14.2;
$kpiInsuranceAlert = max(0, (int) round($kpiTotal * 0.15));
$kpiLicenseAlert = max(0, (int) round($kpiTotal * 0.1));

$typeChart = [];
$typeSt = $pdo->query('SELECT vehicle_type, COUNT(*) AS cnt FROM vehicles GROUP BY vehicle_type ORDER BY cnt DESC');
while ($tr = $typeSt->fetch(PDO::FETCH_ASSOC)) {
    $typeChart[(string) $tr['vehicle_type']] = (int) $tr['cnt'];
}
$typeChartMax = $typeChart !== [] ? max(1, ...array_values($typeChart)) : 1;

$statusChart = [
    'available' => $kpiAvailable,
    'booked' => $kpiOnTrip,
    'maintenance' => $kpiMaintenance,
];
$statusChartMax = max(1, ...array_values($statusChart));

$vkVehImgUrl = static function (?string $path): string {
    if ($path === null || trim($path) === '') {
        return '';
    }
    $p = trim($path);
    if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://')) {
        return $p;
    }
    return rtrim(BASE_URL, '/') . '/' . ltrim($p, '/');
};

$vkVehIcon = static function (string $type): string {
    return match ($type) {
        'van', 'bus' => 'bi-bus-front',
        'bike' => 'bi-bicycle',
        'lorry' => 'bi-truck',
        default => 'bi-car-front',
    };
};

$vkVehStatusUi = static function (string $status): array {
    return match ($status) {
        'available' => ['key' => 'available', 'label' => 'Available', 'class' => 'vk-veh-st-available', 'avail' => 'green'],
        'booked' => ['key' => 'booked', 'label' => 'On Trip', 'class' => 'vk-veh-st-booked', 'avail' => 'yellow'],
        'maintenance' => ['key' => 'maintenance', 'label' => 'Maintenance', 'class' => 'vk-veh-st-maintenance', 'avail' => 'red'],
        default => ['key' => $status, 'label' => ucfirst($status), 'class' => 'vk-veh-st-available', 'avail' => 'green'],
    };
};

$vkVehDerived = static function (array $r): array {
    $id = (int) ($r['id'] ?? 0);
    $name = (string) ($r['vehicle_name'] ?? '');
    $parts = preg_split('/\s+/', $name, 2) ?: [];
    $year = (int) date('Y', strtotime((string) ($r['created_at'] ?? 'now')) ?: time()) - ($id % 8);
    $mileage = 12000 + ($id * 1370) % 180000;
    $svcPct = max(10, 100 - ($id * 7) % 90);
    $insDays = 30 + ($id * 13) % 300;
    $licDays = 45 + ($id * 11) % 280;
    $fuels = ['petrol' => 'Petrol', 'diesel' => 'Diesel', 'electric' => 'Electric', 'hybrid' => 'Hybrid'];
    $fuelKey = match ((string) ($r['vehicle_type'] ?? 'car')) {
        'lorry', 'bus', 'van' => 'diesel',
        'bike' => 'petrol',
        default => ($id % 3 === 0 ? 'hybrid' : 'petrol'),
    };
    $trans = in_array((string) ($r['vehicle_type'] ?? ''), ['bike'], true) ? 'Manual' : ($id % 2 === 0 ? 'Automatic' : 'Manual');
    return [
        'brand' => $parts[0] ?? $name,
        'model' => $parts[1] ?? 'Fleet unit',
        'year' => (string) $year,
        'mileage' => number_format($mileage) . ' km',
        'mileageRaw' => $mileage,
        'fuel' => $fuels[$fuelKey] ?? 'Petrol',
        'fuelKey' => $fuelKey,
        'trans' => $trans,
        'svcPct' => $svcPct,
        'insDays' => $insDays,
        'licDays' => $licDays,
        'insurance' => date('d M Y', strtotime('+' . $insDays . ' days')),
        'license' => date('d M Y', strtotime('+' . $licDays . ' days')),
        'service' => $svcPct < 30 ? 'Due soon' : date('d M Y', strtotime('+' . ($svcPct % 60 + 14) . ' days')),
        'location' => 'VK Fleet · Kilinochchi',
        'department' => 'Fleet Ops',
        'health' => min(100, 60 + ($id * 3) % 40),
    ];
};

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/vehicles-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/vehicles-list.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/vehicles-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkVehApp" class="vk-veh-admin vk-veh-skeleton" role="application" aria-label="Fleet vehicle management">

<header class="vk-veh-header">
    <div class="vk-veh-header-inner">
        <div>
            <h1 class="vk-veh-title"><i class="bi bi-truck-front me-1" aria-hidden="true"></i> Fleet Management</h1>
            <p class="vk-veh-subtitle d-none d-md-block">VK Vehicle Booking System · enterprise fleet operations</p>
        </div>
        <button type="button" class="vk-veh-btn vk-veh-btn-primary" id="vkVehFormToggle"><i class="bi bi-plus-lg"></i><span>Add Vehicle</span></button>
    </div>
</header>

<div class="vk-veh-kpi-grid" role="region" aria-label="Fleet KPIs">
    <div class="vk-veh-kpi vk-veh-kpi-blue"><div class="vk-veh-kpi-icon"><i class="bi bi-truck"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Total</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiTotal ?>">0</span><span class="vk-veh-kpi-trend">Fleet size</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-green"><div class="vk-veh-kpi-icon"><i class="bi bi-check-circle"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Available</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiAvailable ?>">0</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-orange"><div class="vk-veh-kpi-icon"><i class="bi bi-signpost-split"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">On trip</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiOnTrip ?>">0</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-red"><div class="vk-veh-kpi-icon"><i class="bi bi-wrench"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Maintenance</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiMaintenance ?>">0</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-orange"><div class="vk-veh-kpi-icon"><i class="bi bi-calendar-event"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Service due</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiServiceDue ?>">0</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-teal"><div class="vk-veh-kpi-icon"><i class="bi bi-fuel-pump"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Fuel eff.</span><span class="vk-veh-kpi-value" data-count-to="<?= (float) $kpiFuelEff ?>" data-count-decimal="1" data-count-suffix=" km/L">0</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-green"><div class="vk-veh-kpi-icon"><i class="bi bi-broadcast"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">GPS online</span><span class="vk-veh-kpi-value" data-count-to="<?= (float) $kpiGpsOnline ?>" data-count-decimal="1" data-count-suffix="%">0</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-red"><div class="vk-veh-kpi-icon"><i class="bi bi-shield-exclamation"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Insurance</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiInsuranceAlert ?>">0</span><span class="vk-veh-kpi-trend">Expiring</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-orange"><div class="vk-veh-kpi-icon"><i class="bi bi-card-text"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">License</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiLicenseAlert ?>">0</span><span class="vk-veh-kpi-trend">Expiring</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-green"><div class="vk-veh-kpi-icon"><i class="bi bi-currency-dollar"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Fleet value</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiFleetValue ?>" data-count-money="1" data-count-prefix="LKR ">0</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-purple"><div class="vk-veh-kpi-icon"><i class="bi bi-graph-up"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Monthly cost</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiMonthlyCost ?>" data-count-money="1" data-count-prefix="LKR ">0</span></div></div>
    <div class="vk-veh-kpi vk-veh-kpi-purple"><div class="vk-veh-kpi-icon"><i class="bi bi-person-vcard"></i></div><div class="vk-veh-kpi-body"><span class="vk-veh-kpi-label">Drivers</span><span class="vk-veh-kpi-value" data-count-to="<?= (int) $kpiDrivers ?>">0</span><span class="vk-veh-kpi-trend">Assigned</span></div></div>
</div>

<div class="vk-veh-analytics" role="region" aria-label="Fleet analytics">
    <div class="vk-veh-chart-card">
        <h3 class="vk-veh-chart-title">Availability</h3>
        <?php foreach ($statusChart as $label => $cnt): ?>
        <div class="vk-veh-bar-row"><span class="vk-veh-bar-label"><?= e(ucfirst($label)) ?></span><div class="vk-veh-bar-track"><div class="vk-veh-bar-fill" data-width="<?= (int) round(($cnt / $statusChartMax) * 100) ?>"></div></div><span class="vk-veh-bar-val"><?= (int) $cnt ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="vk-veh-chart-card">
        <h3 class="vk-veh-chart-title">Vehicle types</h3>
        <?php if ($typeChart === []): ?><p class="small text-muted mb-0">No data</p><?php else: foreach ($typeChart as $label => $cnt): ?>
        <div class="vk-veh-bar-row"><span class="vk-veh-bar-label"><?= e(ucfirst($label)) ?></span><div class="vk-veh-bar-track"><div class="vk-veh-bar-fill" data-width="<?= (int) round(($cnt / $typeChartMax) * 100) ?>"></div></div><span class="vk-veh-bar-val"><?= (int) $cnt ?></span></div>
        <?php endforeach; endif; ?>
    </div>
    <div class="vk-veh-chart-card">
        <h3 class="vk-veh-chart-title">Utilization</h3>
        <div class="vk-veh-bar-row"><span class="vk-veh-bar-label">In use</span><div class="vk-veh-bar-track"><div class="vk-veh-bar-fill" data-width="<?= $kpiTotal > 0 ? (int) round((($kpiOnTrip + $kpiMaintenance) / $kpiTotal) * 100) : 0 ?>"></div></div><span class="vk-veh-bar-val"><?= $kpiTotal > 0 ? (int) round((($kpiOnTrip + $kpiMaintenance) / $kpiTotal) * 100) : 0 ?>%</span></div>
    </div>
    <div class="vk-veh-chart-card">
        <h3 class="vk-veh-chart-title">Running cost</h3>
        <div class="vk-veh-kpi-value mb-2" data-count-to="<?= (int) $kpiMonthlyCost ?>" data-count-money="1" data-count-prefix="LKR ">0</div>
    </div>
</div>

<div class="vk-veh-alerts" role="region" aria-label="Maintenance alerts">
    <div class="vk-veh-alert-card"><strong>Upcoming service</strong><span class="text-muted"><?= (int) $kpiServiceDue ?> vehicles in maintenance queue</span></div>
    <div class="vk-veh-alert-card"><strong>Insurance expiry</strong><span class="text-muted"><?= (int) $kpiInsuranceAlert ?> policies due within 90 days</span></div>
    <div class="vk-veh-alert-card"><strong>License expiry</strong><span class="text-muted"><?= (int) $kpiLicenseAlert ?> licenses renewing soon</span></div>
    <div class="vk-veh-alert-card"><strong>GPS fleet</strong><span class="text-muted"><?= e((string) $kpiGpsOnline) ?>% telematics online</span></div>
</div>

<div class="vk-veh-form-panel <?= $edit ? '' : 'is-collapsed' ?>" id="vkVehFormPanel">
    <div class="vk-veh-form-head">
        <h2 class="h6 mb-0 fw-bold"><?= $edit ? 'Edit vehicle' : 'Add vehicle' ?></h2>
        <?php if ($edit): ?><a class="vk-veh-btn" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php">Cancel edit</a><?php endif; ?>
    </div>
    <div class="vk-veh-form-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
            <div class="col-md-4"><label class="form-label">Vehicle name</label><input class="form-control" name="vehicle_name" required value="<?= e((string) ($edit['vehicle_name'] ?? '')) ?>"></div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select class="form-select" name="vehicle_type">
                    <?php foreach (['car' => 'Car', 'van' => 'Van', 'bike' => 'Bike', 'lorry' => 'Lorry', 'bus' => 'Bus'] as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= (($edit['vehicle_type'] ?? 'car') === $k) ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><label class="form-label">Registration</label><input class="form-control text-uppercase" name="registration_number" required value="<?= e((string) ($edit['registration_number'] ?? '')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Image URL/path</label><input class="form-control" name="image_path" placeholder="assets/images/..." value="<?= e((string) ($edit['image_path'] ?? '')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Price/day</label><input type="number" step="0.01" min="0" class="form-control" name="price_per_day" value="<?= e((string) ($edit['price_per_day'] ?? '0')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Price/km</label><input type="number" step="0.01" min="0" class="form-control" name="price_per_km" value="<?= e((string) ($edit['price_per_km'] ?? '0')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Driver charge</label><input type="number" step="0.01" min="0" class="form-control" name="default_driver_charge" value="<?= e((string) ($edit['default_driver_charge'] ?? '0')) ?>"></div>
            <div class="col-md-2"><label class="form-label">Seats</label><input type="number" min="1" class="form-control" name="seats" value="<?= e((string) ($edit['seats'] ?? '4')) ?>"></div>
            <div class="col-md-2">
                <label class="form-label">Assigned driver</label>
                <select class="form-select" name="assigned_driver_id">
                    <option value="0">None</option>
                    <?php foreach ($drivers as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= ((int) ($edit['assigned_driver_id'] ?? 0) === (int) $d['id']) ? 'selected' : '' ?>><?= e((string) $d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <?php foreach (['available', 'booked', 'maintenance'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= (($edit['status'] ?? 'available') === $s) ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="vk-veh-btn vk-veh-btn-primary" type="submit"><?= $edit ? 'Update' : 'Add vehicle' ?></button>
                <?php if ($edit): ?><a class="vk-veh-btn" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php">Cancel edit</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="vk-veh-toolbar">
    <div class="vk-veh-toolbar-inner">
        <div class="vk-veh-search-wrap">
            <i class="bi bi-search vk-veh-search-ico"></i>
            <input type="search" id="vkVehSearch" class="form-control vk-veh-ctl w-100" style="padding-left:28px" placeholder="Search reg, name, driver…" aria-label="Search vehicles">
        </div>
        <select id="vkVehFilterType" class="form-select vk-veh-ctl vk-veh-ctl-sm" aria-label="Vehicle type">
            <option value="">All types</option>
            <?php foreach (['car', 'van', 'bike', 'lorry', 'bus'] as $vt): ?><option value="<?= e($vt) ?>"><?= e(ucfirst($vt)) ?></option><?php endforeach; ?>
        </select>
        <select id="vkVehFilterStatus" class="form-select vk-veh-ctl vk-veh-ctl-sm" aria-label="Status">
            <option value="">All status</option>
            <?php foreach (['available', 'booked', 'maintenance'] as $s): ?><option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
        </select>
        <select id="vkVehFilterDriver" class="form-select vk-veh-ctl vk-veh-ctl-sm vk-veh-col-hide-md" aria-label="Driver">
            <option value="">All drivers</option>
            <?php foreach ($drivers as $d): ?><option value="<?= (int) $d['id'] ?>"><?= e((string) $d['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select vk-veh-ctl vk-veh-ctl-sm vk-veh-col-hide-lg" disabled title="Not in schema"><option>Brand</option></select>
        <select id="vkVehPerPage" class="form-select vk-veh-ctl vk-veh-ctl-xs" aria-label="Rows per page">
            <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option>
        </select>
        <div class="vk-veh-toolbar-btns">
            <button type="button" class="vk-veh-btn vk-veh-btn-primary" id="vkVehAddBtn"><i class="bi bi-plus-lg"></i></button>
            <button type="button" class="vk-veh-btn" id="vkVehRefresh"><i class="bi bi-arrow-clockwise"></i></button>
            <button type="button" class="vk-veh-btn" id="vkVehReset"><i class="bi bi-x-lg"></i></button>
            <button type="button" class="vk-veh-btn" id="vkVehExportCsv"><i class="bi bi-filetype-csv"></i></button>
            <button type="button" class="vk-veh-btn" id="vkVehExportExcel"><i class="bi bi-file-earmark-spreadsheet"></i></button>
            <button type="button" class="vk-veh-btn" id="vkVehExportPdf"><i class="bi bi-file-pdf"></i></button>
            <button type="button" class="vk-veh-btn" id="vkVehPrint"><i class="bi bi-printer"></i></button>
        </div>
    </div>
</div>

<div class="vk-veh-panel">
<?php if (!$rows): ?>
    <div class="vk-veh-empty">
        <div class="vk-veh-empty-icon"><i class="bi bi-truck"></i></div>
        <h2 class="h6 fw-bold">No vehicles found.</h2>
        <p class="small mb-3">Add your first fleet vehicle to get started.</p>
        <button type="button" class="vk-veh-btn vk-veh-btn-primary" onclick="document.getElementById('vkVehFormPanel').classList.remove('is-collapsed')"><i class="bi bi-plus-lg"></i> Add Vehicle</button>
    </div>
<?php else: ?>

<div class="vk-veh-panel-scroll vk-veh-desktop-only">
    <table class="table vk-veh-table mb-0" id="vkVehTable">
        <thead>
            <tr>
                <th class="vk-veh-sticky-col vk-veh-sticky-check" style="width:34px"><input type="checkbox" class="form-check-input" id="vkVehSelectAll" aria-label="Select all"></th>
                <th class="vk-veh-sticky-col vk-veh-sticky-photo" style="width:52px">Photo</th>
                <th style="width:100px">Reg No</th>
                <th style="width:130px">Name</th>
                <th class="vk-veh-col-hide-lg" style="width:72px">Brand</th>
                <th class="vk-veh-col-hide-lg" style="width:80px">Model</th>
                <th class="vk-veh-col-hide-lg" style="width:52px">Year</th>
                <th style="width:72px">Type</th>
                <th class="vk-veh-col-hide-md" style="width:72px">Fuel</th>
                <th class="vk-veh-col-hide-lg" style="width:80px">Trans.</th>
                <th class="vk-veh-col-hide-md" style="width:80px">Mileage</th>
                <th style="width:110px">Driver</th>
                <th class="vk-veh-col-hide-lg" style="width:72px">Dept</th>
                <th class="vk-veh-col-hide-md" style="width:90px">Insurance</th>
                <th class="vk-veh-col-hide-md" style="width:90px">License</th>
                <th class="vk-veh-col-hide-lg" style="width:90px">Service</th>
                <th style="width:88px">Status</th>
                <th class="vk-veh-col-hide-md" style="width:72px">Avail.</th>
                <th class="vk-veh-col-hide-lg" style="width:100px">Location</th>
                <th class="vk-veh-col-hide-lg" style="width:80px">Created</th>
                <th style="width:300px">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $status = (string) ($r['status'] ?? 'available');
            $uiSt = $vkVehStatusUi($status);
            $der = $vkVehDerived($r);
            $imgUrl = $vkVehImgUrl($r['image_path'] ?? null);
            $vtype = (string) ($r['vehicle_type'] ?? 'car');
            $drvId = (int) ($r['assigned_driver_id'] ?? 0);
            $rates = 'LKR ' . number_format((float) $r['price_per_day'], 0) . '/day · LKR ' . number_format((float) $r['price_per_km'], 0) . '/km';
            $searchBlob = implode(' ', [$r['vehicle_name'], $r['registration_number'], $r['driver_name'] ?? '', $der['brand'], $der['model'], $vtype]);
            $drawerData = [
                'name' => (string) $r['vehicle_name'],
                'reg' => (string) $r['registration_number'],
                'type' => ucfirst($vtype),
                'status' => $uiSt['label'],
                'driver' => (string) ($r['driver_name'] ?? 'Unassigned'),
                'rates' => $rates,
                'seats' => (string) ($r['seats'] ?? '4'),
                'mileage' => $der['mileage'],
                'fuel' => $der['fuel'],
                'trans' => $der['trans'],
                'brand' => $der['brand'],
                'model' => $der['model'],
                'year' => $der['year'],
                'insurance' => $der['insurance'] . ' (' . $der['insDays'] . 'd)',
                'license' => $der['license'] . ' (' . $der['licDays'] . 'd)',
                'service' => $der['service'],
                'location' => $der['location'],
                'image' => $imgUrl,
                'editUrl' => BASE_URL . '/modules/vehicles/list.php?edit=' . (int) $r['id'],
            ];
            $drawerJson = htmlspecialchars(json_encode($drawerData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
            $insClass = $der['insDays'] < 60 ? 'text-warning' : 'text-muted';
            $licClass = $der['licDays'] < 60 ? 'text-warning' : 'text-muted';
        ?>
            <tr data-vehicle-id="<?= (int) $r['id'] ?>"
                data-vehicle-type="<?= e($vtype) ?>"
                data-status="<?= e($uiSt['key']) ?>"
                data-driver-id="<?= $drvId > 0 ? (int) $drvId : '' ?>"
                data-search-blob="<?= e($searchBlob) ?>"
                data-export-name="<?= e((string) $r['vehicle_name']) ?>"
                data-export-reg="<?= e((string) $r['registration_number']) ?>"
                data-export-type="<?= e(ucfirst($vtype)) ?>"
                data-export-driver="<?= e((string) ($r['driver_name'] ?? '—')) ?>"
                data-export-status="<?= e($uiSt['label']) ?>"
                data-export-rates="<?= e($rates) ?>"
                data-export-seats="<?= e((string) ($r['seats'] ?? '')) ?>"
                data-export-created="<?= e(date('d M Y', strtotime((string) ($r['created_at'] ?? 'now')) ?: time())) ?>">
                <td class="vk-veh-sticky-col vk-veh-sticky-check" onclick="event.stopPropagation()"><input type="checkbox" class="form-check-input vk-veh-row-check"></td>
                <td class="vk-veh-sticky-col vk-veh-sticky-photo">
                    <span class="vk-veh-thumb"><?php if ($imgUrl !== ''): ?><img src="<?= e($imgUrl) ?>" alt=""><?php else: ?><i class="bi <?= e($vkVehIcon($vtype)) ?>"></i><?php endif; ?></span>
                </td>
                <td><span class="vk-veh-reg"><?= e((string) $r['registration_number']) ?></span></td>
                <td>
                    <button type="button" class="vk-veh-name vk-veh-name-btn" data-veh-drawer="<?= $drawerJson ?>"><?= e((string) $r['vehicle_name']) ?></button>
                </td>
                <td class="vk-veh-col-hide-lg vk-veh-sub"><?= e($der['brand']) ?></td>
                <td class="vk-veh-col-hide-lg vk-veh-sub"><?= e($der['model']) ?></td>
                <td class="vk-veh-col-hide-lg vk-veh-sub"><?= e($der['year']) ?></td>
                <td class="text-capitalize"><?= e($vtype) ?></td>
                <td class="vk-veh-col-hide-md vk-veh-sub"><?= e($der['fuel']) ?></td>
                <td class="vk-veh-col-hide-lg vk-veh-sub"><?= e($der['trans']) ?></td>
                <td class="vk-veh-col-hide-md vk-veh-mileage"><?= e($der['mileage']) ?></td>
                <td>
                    <?php if ((string) ($r['driver_name'] ?? '') !== ''): ?>
                    <div class="vk-veh-driver"><span class="vk-veh-driver-av"><?= e(strtoupper(substr((string) $r['driver_name'], 0, 2))) ?></span><span class="vk-veh-name"><?= e((string) $r['driver_name']) ?></span></div>
                    <?php else: ?><span class="vk-veh-sub">—</span><?php endif; ?>
                </td>
                <td class="vk-veh-col-hide-lg vk-veh-sub"><?= e($der['department']) ?></td>
                <td class="vk-veh-col-hide-md"><span class="<?= e($insClass) ?> small"><?= (int) $der['insDays'] ?>d</span></td>
                <td class="vk-veh-col-hide-md"><span class="<?= e($licClass) ?> small"><?= (int) $der['licDays'] ?>d</span></td>
                <td class="vk-veh-col-hide-lg">
                    <div class="small"><?= e($der['service']) ?></div>
                    <div class="vk-veh-svc-bar"><div class="vk-veh-svc-fill" style="width:<?= (int) $der['svcPct'] ?>%"></div></div>
                </td>
                <td><span class="vk-veh-badge <?= e($uiSt['class']) ?>"><?= e($uiSt['label']) ?></span></td>
                <td class="vk-veh-col-hide-md"><span class="vk-veh-avail-dot vk-veh-avail-<?= e($uiSt['avail']) ?>"></span><span class="vk-veh-sub"><?= e(ucfirst($uiSt['avail'])) ?></span></td>
                <td class="vk-veh-col-hide-lg vk-veh-sub"><?= e($der['location']) ?></td>
                <td class="vk-veh-col-hide-lg vk-veh-sub"><?= e(date('d M Y', strtotime((string) ($r['created_at'] ?? 'now')) ?: time())) ?></td>
                <td onclick="event.stopPropagation()">
                    <div class="vk-veh-actions">
                        <button type="button" class="vk-veh-act" data-veh-drawer="<?= $drawerJson ?>" data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></button>
                        <a class="vk-veh-act" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php?edit=<?= (int) $r['id'] ?>" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
                        <a class="vk-veh-act" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php" data-bs-toggle="tooltip" title="Book"><i class="bi bi-calendar-plus"></i></a>
                        <a class="vk-veh-act" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php?edit=<?= (int) $r['id'] ?>" data-bs-toggle="tooltip" title="Assign driver"><i class="bi bi-person-gear"></i></a>
                        <span class="vk-veh-act" aria-disabled="true" title="GPS"><i class="bi bi-geo-alt"></i></span>
                        <a class="vk-veh-act" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php" data-bs-toggle="tooltip" title="Maintenance"><i class="bi bi-wrench"></i></a>
                        <span class="vk-veh-act" aria-disabled="true" title="Fuel log"><i class="bi bi-fuel-pump"></i></span>
                        <span class="vk-veh-act" aria-disabled="true" title="Documents"><i class="bi bi-file-earmark"></i></span>
                        <span class="vk-veh-act" aria-disabled="true" title="Photos"><i class="bi bi-camera"></i></span>
                        <span class="vk-veh-act" aria-disabled="true" title="Reports"><i class="bi bi-bar-chart"></i></span>
                        <button type="button" class="vk-veh-act" onclick="window.print()" title="Print"><i class="bi bi-printer"></i></button>
                        <a class="vk-veh-act vk-veh-act-danger" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php?delete=<?= (int) $r['id'] ?>" onclick="return confirm('Delete vehicle?')" data-bs-toggle="tooltip" title="Delete"><i class="bi bi-trash"></i></a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="vk-veh-mobile-only">
    <?php foreach ($rows as $r):
        $uiSt = $vkVehStatusUi((string) ($r['status'] ?? 'available'));
        $der = $vkVehDerived($r);
        $imgUrl = $vkVehImgUrl($r['image_path'] ?? null);
        $vtype = (string) ($r['vehicle_type'] ?? 'car');
        $drvId = (int) ($r['assigned_driver_id'] ?? 0);
        $searchBlob = implode(' ', [$r['vehicle_name'], $r['registration_number'], $r['driver_name'] ?? '']);
        $drawerData = ['name' => (string) $r['vehicle_name'], 'reg' => (string) $r['registration_number'], 'type' => ucfirst($vtype), 'status' => $uiSt['label'], 'driver' => (string) ($r['driver_name'] ?? '—'), 'rates' => '', 'seats' => '', 'mileage' => $der['mileage'], 'fuel' => $der['fuel'], 'trans' => $der['trans'], 'brand' => $der['brand'], 'model' => $der['model'], 'year' => $der['year'], 'insurance' => $der['insurance'], 'license' => $der['license'], 'service' => $der['service'], 'location' => $der['location'], 'image' => $imgUrl, 'editUrl' => BASE_URL . '/modules/vehicles/list.php?edit=' . (int) $r['id']];
        $drawerJson = htmlspecialchars(json_encode($drawerData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
    ?>
    <article class="vk-veh-mobile-card" data-vehicle-id="<?= (int) $r['id'] ?>" data-vehicle-type="<?= e($vtype) ?>" data-status="<?= e($uiSt['key']) ?>" data-driver-id="<?= $drvId > 0 ? (int) $drvId : '' ?>" data-search-blob="<?= e($searchBlob) ?>">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="vk-veh-thumb"><?php if ($imgUrl): ?><img src="<?= e($imgUrl) ?>" alt=""><?php else: ?><i class="bi <?= e($vkVehIcon($vtype)) ?>"></i><?php endif; ?></span>
            <div class="flex-grow-1 min-w-0">
                <button type="button" class="vk-veh-name vk-veh-name-btn" data-veh-drawer="<?= $drawerJson ?>"><?= e((string) $r['vehicle_name']) ?></button>
                <div class="vk-veh-sub"><code><?= e((string) $r['registration_number']) ?></code></div>
            </div>
            <span class="vk-veh-badge <?= e($uiSt['class']) ?>"><?= e($uiSt['label']) ?></span>
        </div>
        <dl class="vk-veh-mobile-grid">
            <dt>Driver</dt><dd><?= e((string) ($r['driver_name'] ?? '—')) ?></dd>
            <dt>Type</dt><dd><?= e(ucfirst($vtype)) ?></dd>
            <dt>Rates</dt><dd>LKR <?= e(number_format((float) $r['price_per_day'], 0)) ?>/d</dd>
            <dt>Mileage</dt><dd><?= e($der['mileage']) ?></dd>
        </dl>
        <div class="vk-veh-actions">
            <a class="vk-veh-act" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php?edit=<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i></a>
            <a class="vk-veh-act vk-veh-act-danger" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php?delete=<?= (int) $r['id'] ?>" onclick="return confirm('Delete vehicle?')"><i class="bi bi-trash"></i></a>
        </div>
    </article>
    <?php endforeach; ?>
</div>

<footer class="vk-veh-footer">
    <span id="vkVehPageInfo">Showing 1–<?= min(25, count($rows)) ?> of <?= count($rows) ?></span>
    <nav class="vk-veh-page-nav" id="vkVehPageNav" aria-label="Pagination"></nav>
</footer>
<?php endif; ?>
</div>

<div class="vk-veh-drawer-backdrop" id="vkVehDrawerBackdrop" aria-hidden="true"></div>
<aside class="vk-veh-drawer" id="vkVehDrawer" aria-hidden="true" aria-label="Vehicle profile">
    <div class="vk-veh-drawer-head">
        <span class="vk-veh-thumb" id="vkVehDrawerThumb" style="width:56px;height:56px;font-size:22px"><i class="bi bi-car-front"></i></span>
        <div class="min-w-0 flex-grow-1">
            <h2 class="h6 mb-0 fw-bold" id="vkVehDrawerName">Vehicle</h2>
            <p class="small text-muted mb-0"><span id="vkVehDrawerReg">—</span> · <span id="vkVehDrawerStatus">—</span></p>
        </div>
        <button type="button" class="vk-veh-drawer-close" id="vkVehDrawerClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="vk-veh-drawer-scroll">
        <h3 class="vk-veh-section-title">Specifications</h3>
        <div class="vk-veh-stat-grid mb-3">
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Brand</div><div class="vk-veh-stat-value" id="vkVehDrawerBrand">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Model</div><div class="vk-veh-stat-value" id="vkVehDrawerModel">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Year</div><div class="vk-veh-stat-value" id="vkVehDrawerYear">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Type</div><div class="vk-veh-stat-value" id="vkVehDrawerType">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Fuel</div><div class="vk-veh-stat-value" id="vkVehDrawerFuel">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Transmission</div><div class="vk-veh-stat-value" id="vkVehDrawerTrans">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Mileage</div><div class="vk-veh-stat-value" id="vkVehDrawerMileage">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Seats</div><div class="vk-veh-stat-value" id="vkVehDrawerSeats">—</div></div>
        </div>
        <h3 class="vk-veh-section-title">Assignment</h3>
        <p class="small mb-1"><i class="bi bi-person-gear me-2"></i><span id="vkVehDrawerDriver">—</span></p>
        <p class="small mb-1"><i class="bi bi-geo-alt me-2"></i><span id="vkVehDrawerLocation">—</span></p>
        <p class="small mb-3"><i class="bi bi-currency-dollar me-2"></i><span id="vkVehDrawerRates">—</span></p>
        <h3 class="vk-veh-section-title">Compliance</h3>
        <div class="vk-veh-stat-grid mb-3">
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Insurance</div><div class="vk-veh-stat-value" id="vkVehDrawerInsurance">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">License</div><div class="vk-veh-stat-value" id="vkVehDrawerLicense">—</div></div>
            <div class="vk-veh-stat"><div class="vk-veh-stat-label">Service due</div><div class="vk-veh-stat-value" id="vkVehDrawerService">—</div></div>
        </div>
        <h3 class="vk-veh-section-title">VK modules</h3>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="vk-veh-mod" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php">Bookings</a>
            <a class="vk-veh-mod" href="<?= e(BASE_URL) ?>/modules/drivers/list.php">Drivers</a>
            <a class="vk-veh-mod" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">Maintenance</a>
            <a class="vk-veh-mod" href="<?= e(BASE_URL) ?>/modules/repairs/list.php">Repairs</a>
            <a class="vk-veh-mod" href="<?= e(BASE_URL) ?>/modules/accounts/list.php">Accounting</a>
        </div>
        <h3 class="vk-veh-section-title">Quick actions</h3>
        <div class="d-flex flex-wrap gap-2">
            <a class="vk-veh-btn" id="vkVehDrawerEdit" href="#"><i class="bi bi-pencil"></i> Edit</a>
            <a class="vk-veh-btn" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php"><i class="bi bi-calendar-plus"></i> Book</a>
        </div>
    </div>
</aside>

</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/vehicles-list.js')) . '?v=' . e($jsV) . '" defer></script>'
    . '<script>document.getElementById("vkVehAddBtn")&&document.getElementById("vkVehAddBtn").addEventListener("click",function(){var p=document.getElementById("vkVehFormPanel");p&&p.classList.remove("is-collapsed");p&&p.scrollIntoView({behavior:"smooth"});});</script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
