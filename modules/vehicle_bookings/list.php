<?php
declare(strict_types=1);
$pageTitle = 'Vehicle bookings';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
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

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Vehicle bookings</h1>
</div>

<form class="row g-2 mb-3" method="get">
    <div class="col-md-4"><input class="form-control" name="q" placeholder="Booking ref, name, phone" value="<?= e($q) ?>"></div>
    <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
</form>

<div class="card vk-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-sm align-middle">
            <thead><tr><th>Booking</th><th>Customer</th><th>Trip</th><th>Pricing</th><th>Assign</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No bookings found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><code><?= e((string) $r['booking_ref']) ?></code><div class="small text-muted"><?= e((string) $r['booking_type']) ?> • <?= e((string) $r['vehicle_type']) ?></div></td>
                    <td><?= e((string) $r['full_name']) ?><div class="small text-muted"><?= e((string) $r['phone']) ?></div></td>
                    <td class="small"><?= e((string) $r['pickup_location']) ?><br><span class="text-muted"><?= e((string) ($r['drop_location'] ?? '-')) ?></span></td>
                    <td class="small"><?= e(number_format((float) $r['distance_km'], 1)) ?> km<br>LKR <?= e(number_format((float) $r['total_amount'], 0)) ?></td>
                    <td style="min-width: 280px;">
                        <form method="post" class="row g-1">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <div class="col-6">
                                <select class="form-select form-select-sm" name="vehicle_id">
                                    <option value="0">Vehicle</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= (int) $v['id'] ?>" <?= ((int) $r['vehicle_id'] === (int) $v['id']) ? 'selected' : '' ?>><?= e((string) $v['vehicle_name']) ?> (<?= e((string) $v['registration_number']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <select class="form-select form-select-sm" name="driver_id">
                                    <option value="0">Driver</option>
                                    <?php foreach ($drivers as $d): ?>
                                        <option value="<?= (int) $d['id'] ?>" <?= ((int) $r['driver_id'] === (int) $d['id']) ? 'selected' : '' ?>><?= e((string) $d['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-8">
                                <select class="form-select form-select-sm" name="status">
                                    <?php foreach (['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'] as $s): ?>
                                        <option value="<?= e($s) ?>" <?= ((string) $r['status'] === $s) ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-4 d-grid">
                                <button class="btn btn-sm btn-primary" type="submit">Save</button>
                            </div>
                        </form>
                    </td>
                    <td><span class="badge text-bg-<?= $r['status'] === 'completed' ? 'success' : ($r['status'] === 'cancelled' ? 'danger' : ($r['status'] === 'ongoing' ? 'warning' : 'secondary')) ?>"><?= e((string) ucfirst((string) $r['status'])) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
