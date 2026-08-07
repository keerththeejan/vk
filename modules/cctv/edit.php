<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT v.*, c.name AS customer_name FROM cctv_installations v JOIN customers c ON c.id = v.customer_id WHERE v.id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    flash_set('error', 'Job not found.');
    redirect('/modules/cctv/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim((string) ($_POST['location'] ?? ''));
    $numCam = max(1, (int) ($_POST['num_cameras'] ?? 1));
    $cable = max(0, (float) ($_POST['cable_length_m'] ?? 0));
    $charge = max(0, (float) ($_POST['installation_charge'] ?? 0));
    $equipment = trim((string) ($_POST['equipment_used'] ?? ''));
    $dvrNvr = trim((string) ($_POST['dvr_nvr_details'] ?? ''));
    $status = (string) ($_POST['status'] ?? 'pending');
    $notes = trim((string) ($_POST['technician_notes'] ?? ''));
    $warranty = trim((string) ($_POST['warranty_expiry'] ?? ''));
    $warrantyDate = ($warranty !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $warranty)) ? $warranty : null;

    $allowedSt = ['pending', 'in_progress', 'completed', 'delivered'];
    if (!in_array($status, $allowedSt, true)) {
        $status = 'pending';
    }
    if ($location === '') {
        flash_set('error', 'Location is required.');
    } else {
        $pdo->prepare(
            'UPDATE cctv_installations SET location=?, num_cameras=?, cable_length_m=?, dvr_nvr_details=?, installation_charge=?, equipment_used=?, status=?, technician_notes=?, warranty_expiry=? WHERE id=?'
        )->execute([$location, $numCam, $cable, $dvrNvr ?: null, $charge, $equipment ?: null, $status, $notes ?: null, $warrantyDate, $id]);
        flash_set('success', 'Installation job updated.');
        redirect('/modules/cctv/view.php?id=' . $id);
    }
    $st->execute([$id]);
    $row = $st->fetch();
}

$pageTitle = 'Edit CCTV job';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/cctv/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>View</a>
</div>
<h1 class="h3 mb-1">Edit <?= e($row['job_number']) ?></h1>
<p class="text-muted small"><?= e($row['customer_name']) ?></p>
<div class="card vk-card" style="max-width: 720px;">
    <div class="card-body">
        <form method="post" data-loading>
            <div class="mb-3">
                <label class="form-label" for="location">Location</label>
                <textarea class="form-control" name="location" id="location" rows="2" required><?= e($row['location'] ?? '') ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="num_cameras">Cameras</label>
                    <input type="number" min="1" class="form-control" name="num_cameras" id="num_cameras" value="<?= e((string) $row['num_cameras']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cable_length_m">Cable (m)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="cable_length_m" id="cable_length_m" value="<?= e((string) $row['cable_length_m']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="installation_charge">Charge</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="installation_charge" id="installation_charge" value="<?= e((string) $row['installation_charge']) ?>">
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label" for="dvr_nvr_details">DVR / NVR details</label>
                <textarea class="form-control" name="dvr_nvr_details" id="dvr_nvr_details" rows="2" maxlength="2000"><?= e($row['dvr_nvr_details'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="equipment_used">Equipment used</label>
                <textarea class="form-control" name="equipment_used" id="equipment_used" rows="2"><?= e($row['equipment_used'] ?? '') ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" name="status" id="status">
                        <?php foreach (['pending', 'in_progress', 'completed', 'delivered'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="warranty_expiry">Warranty until</label>
                    <input type="date" class="form-control" name="warranty_expiry" id="warranty_expiry" value="<?= e($row['warranty_expiry'] ?? '') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="technician_notes">Technician notes</label>
                <textarea class="form-control" name="technician_notes" id="technician_notes" rows="2"><?= e($row['technician_notes'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
