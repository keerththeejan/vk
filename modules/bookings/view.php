<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

if (!db_table_exists($pdo, 'web_bookings')) {
    flash_set('error', 'Bookings table missing.');
    redirect('/modules/dashboard.php');
}

$id = (int) ($_GET['id'] ?? 0);
$hasAssignTech = db_column_exists($pdo, 'web_bookings', 'assigned_technician_id');
$hasAssignDist = db_column_exists($pdo, 'web_bookings', 'assignment_distance_km');
$hasRepairJobId = db_column_exists($pdo, 'web_bookings', 'repair_job_id');
$hasIsEmergency = db_column_exists($pdo, 'web_bookings', 'is_emergency');
$st = $pdo->prepare(
    $hasAssignTech
        ? 'SELECT b.*, t.name AS tech_name FROM web_bookings b LEFT JOIN technicians t ON t.id = b.assigned_technician_id WHERE b.id = ?'
        : 'SELECT b.* FROM web_bookings b WHERE b.id = ?'
);
$st->execute([$id]);
$b = $st->fetch();
if (!$b) {
    flash_set('error', 'Booking not found.');
    redirect('/modules/bookings/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update') {
        $status = (string) ($_POST['status'] ?? 'pending');
        $allowed = ['pending', 'in_progress', 'completed', 'delivered', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }
        $techId = (int) ($_POST['assigned_technician_id'] ?? 0);
        $notes = trim((string) ($_POST['technician_notes'] ?? ''));
        $est = max(0, (float) ($_POST['estimated_cost'] ?? 0));
        if ($hasAssignTech) {
            $pdo->prepare(
                'UPDATE web_bookings SET status=?, assigned_technician_id=?, technician_notes=?, estimated_cost=? WHERE id=?'
            )->execute([
                $status,
                $techId > 0 ? $techId : null,
                $notes !== '' ? $notes : null,
                $est,
                $id,
            ]);
        } else {
            $pdo->prepare(
                'UPDATE web_bookings SET status=?, technician_notes=?, estimated_cost=? WHERE id=?'
            )->execute([
                $status,
                $notes !== '' ? $notes : null,
                $est,
                $id,
            ]);
        }
        flash_set('success', 'Booking updated.');
        redirect('/modules/bookings/view.php?id=' . $id);
    }
    $alreadyLinked = $hasRepairJobId && !empty($b['repair_job_id']);
    if ($action === 'convert' && !$alreadyLinked) {
        if (!$hasRepairJobId) {
            flash_set('error', 'Cannot convert bookings until web_bookings.repair_job_id exists. Run sql/upgrade_web_bookings_technician.sql.');
            redirect('/modules/bookings/view.php?id=' . $id);
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO customers (name, phone, email, address) VALUES (?,?,?,?)')->execute([
                $b['customer_name'],
                $b['phone'],
                $b['email'] ?: null,
                $b['address'] ?: null,
            ]);
            $cid = (int) $pdo->lastInsertId();
            $code = next_customer_account_code($pdo);
            $pdo->prepare(
                'INSERT INTO accounts (code, name, account_type, customer_id, current_balance) VALUES (?,?,?,?,0)'
            )->execute([$code, $b['customer_name'] . ' — Account', 'customer', $cid]);

            $dev = booking_service_to_device_type((string) $b['service_type']);
            $prob = (string) $b['problem_description'];
            if (!empty($b['image_path'])) {
                $prob .= "\n\n[Booking photo: " . $b['image_path'] . ']';
            }
            $jobNo = next_repair_job_number($pdo);
            $emerg = $hasIsEmergency && (int) ($b['is_emergency'] ?? 0) ? 1 : 0;
            $tid = $hasAssignTech ? (int) ($b['assigned_technician_id'] ?? 0) : 0;
            $pdo->prepare(
                'INSERT INTO repair_jobs (job_number, customer_id, device_type, problem_description, technician_id, estimated_cost, status, latitude, longitude, emergency_priority, field_status)
                 VALUES (?,?,?,?,?,?, \'pending\', ?, ?, ?, ?)'
            )->execute([
                $jobNo,
                $cid,
                $dev,
                $prob,
                $tid > 0 ? $tid : null,
                (float) $b['estimated_cost'],
                $b['latitude'],
                $b['longitude'],
                $emerg,
                $tid > 0 ? 'assigned' : null,
            ]);
            $jid = (int) $pdo->lastInsertId();
            if ($hasRepairJobId) {
                $pdo->prepare('UPDATE web_bookings SET repair_job_id = ? WHERE id = ?')->execute([$jid, $id]);
            }
            $pdo->commit();
            flash_set('success', 'Converted to repair job ' . $jobNo . '.');
            redirect('/modules/repairs/view.php?id=' . $jid);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not convert booking.');
            redirect('/modules/bookings/view.php?id=' . $id);
        }
    }
    $st->execute([$id]);
    $b = $st->fetch();
}

$technicians = $pdo->query('SELECT id, name FROM technicians WHERE active = 1 ORDER BY name')->fetchAll();
$mapsKey = defined('GOOGLE_MAPS_API_KEY') ? (string) GOOGLE_MAPS_API_KEY : '';

$pageTitle = 'Booking ' . $b['booking_number'];
$extraHead = ($mapsKey !== '' && $b['latitude'] && $b['longitude'])
    ? '<script src="https://maps.googleapis.com/maps/api/js?key=' . e($mapsKey) . '" async defer></script>'
    : '';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/bookings/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Bookings</a>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><code><?= e($b['booking_number']) ?></code>
            <?php if ($hasIsEmergency && (int) ($b['is_emergency'] ?? 0) === 1): ?><span class="badge text-bg-danger ms-1">Emergency</span><?php endif; ?>
        </h1>
        <p class="text-muted mb-0"><?= e($b['customer_name']) ?> · <?= e($b['phone']) ?></p>
        <?php $waView = vk_whatsapp_me_link((string) $b['phone'], vk_whatsapp_web_booking_message($b)); ?>
        <a class="btn btn-sm btn-success mt-2" href="<?= e($waView) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Send WhatsApp</a>
    </div>
    <?php if ($hasRepairJobId && !empty($b['repair_job_id'])): ?>
        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/repairs/view.php?id=<?= (int) $b['repair_job_id'] ?>">Open repair job</a>
    <?php elseif (!$hasRepairJobId): ?>
        <div class="text-end">
            <button type="button" class="btn btn-success" disabled title="Database upgrade required">Convert to job card</button>
            <div class="small text-muted mt-1" style="max-width: 14rem;">Add <code>repair_job_id</code> to <code>web_bookings</code> (see <code>sql/upgrade_web_bookings_technician.sql</code>) to enable conversion.</div>
        </div>
    <?php else: ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Create customer + repair job from this booking?');">
            <input type="hidden" name="action" value="convert">
            <button type="submit" class="btn btn-success">Convert to job card</button>
        </form>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Details</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Service</dt>
                    <dd class="col-sm-8 text-capitalize"><?= e(str_replace('_', ' ', $b['service_type'])) ?></dd>
                    <dt class="col-sm-4">Submitted</dt>
                    <dd class="col-sm-8"><?= e($b['created_at']) ?></dd>
                    <dt class="col-sm-4">Preferred date</dt>
                    <dd class="col-sm-8"><?= e($b['preferred_date'] ?? '—') ?></dd>
                    <?php if ($hasAssignTech && !empty($b['assigned_technician_id'])): ?>
                        <dt class="col-sm-4">Assigned technician</dt>
                        <dd class="col-sm-8"><?= e($b['tech_name'] ?? '—') ?></dd>
                        <?php if ($hasAssignDist && isset($b['assignment_distance_km']) && $b['assignment_distance_km'] !== null && $b['assignment_distance_km'] !== ''): ?>
                            <dt class="col-sm-4">Assign distance</dt>
                            <dd class="col-sm-8"><?= e(number_format((float) $b['assignment_distance_km'], 2, '.', '')) ?> km (auto)</dd>
                        <?php endif; ?>
                    <?php endif; ?>
                    <dt class="col-sm-4">Problem</dt>
                    <dd class="col-sm-8"><?= nl2br(e($b['problem_description'])) ?></dd>
                    <?php if (!empty($b['image_path'])): ?>
                        <dt class="col-sm-4">Photo</dt>
                        <dd class="col-sm-8"><a target="_blank" href="<?= e(BASE_URL) ?>/<?= e($b['image_path']) ?>">View image</a></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
        <?php if ($b['latitude'] && $b['longitude']): ?>
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Location</div>
                <div class="card-body">
                    <div id="adminMap" class="rounded border" style="height:240px;"></div>
                    <a class="btn btn-sm btn-outline-primary mt-2" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode((string) $b['latitude'] . ',' . (string) $b['longitude']) ?>">Navigate in Google Maps</a>
                </div>
            </div>
            <?php if ($mapsKey !== ''): ?>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
              if (!window.google || !document.getElementById('adminMap')) return;
              var pos = { lat: <?= json_encode((float) $b['latitude']) ?>, lng: <?= json_encode((float) $b['longitude']) ?> };
              var map = new google.maps.Map(document.getElementById('adminMap'), { zoom: 15, center: pos });
              new google.maps.Marker({ position: pos, map: map });
            });
            </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">Manage</div>
            <div class="card-body">
                <?php if (!$hasAssignTech || !$hasRepairJobId): ?>
                    <div class="alert alert-warning small mb-3">
                        Your <code>web_bookings</code> table is missing optional columns. Run
                        <code>sql/upgrade_web_bookings_technician.sql</code> in phpMyAdmin to enable technician assignment and booking→job linking.
                    </div>
                <?php endif; ?>
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php foreach (['pending','in_progress','completed','delivered','cancelled'] as $s): ?>
                                <option value="<?= e($s) ?>" <?= $b['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($hasAssignTech): ?>
                    <div class="mb-3">
                        <label class="form-label">Assign technician</label>
                        <select class="form-select" name="assigned_technician_id">
                            <option value="">— None —</option>
                            <?php foreach ($technicians as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= (int) ($b['assigned_technician_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Technician notes (visible to customer on track page)</label>
                        <textarea class="form-control" name="technician_notes" rows="3"><?= e($b['technician_notes'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estimated cost</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="estimated_cost" value="<?= e((string) $b['estimated_cost']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
