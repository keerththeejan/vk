<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $location = trim((string) ($_POST['location'] ?? ''));
    $numCam = max(1, (int) ($_POST['num_cameras'] ?? 1));
    $cable = max(0, (float) ($_POST['cable_length_m'] ?? 0));
    $charge = max(0, (float) ($_POST['installation_charge'] ?? 0));
    $equipment = trim((string) ($_POST['equipment_used'] ?? ''));
    $dvrNvr = trim((string) ($_POST['dvr_nvr_details'] ?? ''));
    $warranty = trim((string) ($_POST['warranty_expiry'] ?? ''));
    $warrantyDate = ($warranty !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $warranty)) ? $warranty : null;

    if ($customerId <= 0 || $location === '') {
        flash_set('error', 'Customer and location are required.');
    } else {
        try {
            $pdo->beginTransaction();
            $jobNo = next_cctv_job_number($pdo);
            $pdo->prepare(
                'INSERT INTO cctv_installations (job_number, customer_id, location, num_cameras, cable_length_m, dvr_nvr_details, installation_charge, equipment_used, status, warranty_expiry)
                 VALUES (?,?,?,?,?,?,?,?,\'pending\',?)'
            )->execute([$jobNo, $customerId, $location, $numCam, $cable, $dvrNvr ?: null, $charge, $equipment ?: null, $warrantyDate]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();
            flash_set('success', 'CCTV job ' . $jobNo . ' created.');
            redirect('/modules/cctv/view.php?id=' . $newId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not create job.');
        }
    }
}

$pageTitle = 'New CCTV installation';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$customers = $pdo->query('SELECT id, name, phone FROM customers ORDER BY name')->fetchAll();
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/cctv/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">New CCTV installation</h1>
<div class="card vk-card" style="max-width: 720px;">
    <div class="card-body">
        <form method="post" data-loading>
            <div class="mb-3">
                <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                <select class="form-select" name="customer_id" id="customer_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="location">Site location <span class="text-danger">*</span></label>
                <textarea class="form-control" name="location" id="location" rows="2" required maxlength="2000"><?= e($_POST['location'] ?? '') ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="num_cameras">Cameras</label>
                    <input type="number" min="1" class="form-control" name="num_cameras" id="num_cameras" value="<?= e($_POST['num_cameras'] ?? '1') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cable_length_m">Cable (m)</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="cable_length_m" id="cable_length_m" value="<?= e($_POST['cable_length_m'] ?? '0') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="installation_charge">Installation charge</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="installation_charge" id="installation_charge" value="<?= e($_POST['installation_charge'] ?? '0') ?>">
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label" for="dvr_nvr_details">DVR / NVR details</label>
                <textarea class="form-control" name="dvr_nvr_details" id="dvr_nvr_details" rows="2" maxlength="2000" placeholder="Model, channels, storage…"><?= e($_POST['dvr_nvr_details'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="equipment_used">Equipment used</label>
                <textarea class="form-control" name="equipment_used" id="equipment_used" rows="2" maxlength="2000"><?= e($_POST['equipment_used'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="warranty_expiry">Warranty until (optional)</label>
                <input type="date" class="form-control" name="warranty_expiry" id="warranty_expiry" value="<?= e($_POST['warranty_expiry'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary">Save installation job</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
