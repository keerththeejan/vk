<?php
declare(strict_types=1);
$pageTitle = 'Drivers';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
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
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Driver management</h1>
</div>

<div class="card vk-card mb-4">
    <div class="card-body">
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
                <button class="btn btn-primary" type="submit"><?= $edit ? 'Update' : 'Add driver' ?></button>
                <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/drivers/list.php">Cancel edit</a><?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card vk-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Phone</th><th>License</th><th>Availability</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No drivers yet.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><?= e((string) $r['name']) ?></td>
                    <td><?= e((string) $r['phone']) ?></td>
                    <td><code><?= e((string) $r['license_number']) ?></code></td>
                    <td><?= e((string) ucwords(str_replace('_', ' ', (string) $r['availability']))) ?></td>
                    <td><span class="badge text-bg-<?= ((int) $r['active'] === 1) ? 'success' : 'secondary' ?>"><?= ((int) $r['active'] === 1) ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/drivers/list.php?edit=<?= (int) $r['id'] ?>">Edit</a>
                        <a class="btn btn-sm btn-outline-danger" href="<?= e(BASE_URL) ?>/modules/drivers/list.php?delete=<?= (int) $r['id'] ?>" onclick="return confirm('Delete driver?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
