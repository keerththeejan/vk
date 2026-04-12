<?php
declare(strict_types=1);
$pageTitle = 'Vehicles';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
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

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Vehicle management</h1>
    <span class="text-muted small">VK Vehicle Booking System</span>
</div>

<div class="card vk-card mb-4">
    <div class="card-body">
        <h2 class="h6 mb-3"><?= $edit ? 'Edit vehicle' : 'Add vehicle' ?></h2>
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
                <button class="btn btn-primary" type="submit"><?= $edit ? 'Update' : 'Add vehicle' ?></button>
                <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php">Cancel edit</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card vk-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Type</th><th>Reg</th><th>Rates</th><th>Driver</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No vehicles yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= e((string) $r['vehicle_name']) ?></td>
                    <td class="text-capitalize"><?= e((string) $r['vehicle_type']) ?></td>
                    <td><code><?= e((string) $r['registration_number']) ?></code></td>
                    <td class="small">LKR <?= e(number_format((float) $r['price_per_day'], 0)) ?>/day<br>LKR <?= e(number_format((float) $r['price_per_km'], 0)) ?>/km</td>
                    <td><?= e((string) ($r['driver_name'] ?? '-')) ?></td>
                    <td><span class="badge text-bg-<?= $r['status'] === 'available' ? 'success' : ($r['status'] === 'booked' ? 'warning' : 'secondary') ?>"><?= e((string) ucfirst((string) $r['status'])) ?></span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php?edit=<?= (int) $r['id'] ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php?delete=<?= (int) $r['id'] ?>" onclick="return confirm('Delete vehicle?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
