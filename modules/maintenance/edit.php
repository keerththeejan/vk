<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT m.*, c.name AS customer_name
     FROM maintenance_contracts m
     JOIN customers c ON c.id = m.customer_id
     WHERE m.id = ?'
);
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    flash_set('error', 'Contract not found.');
    redirect('/modules/maintenance/list.php');
}

$types = ['computer_amc' => 'Computer AMC', 'cctv_maintenance' => 'CCTV maintenance'];
$freq = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'one_time' => 'One-time'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_contract') {
        $contractType = (string) ($_POST['contract_type'] ?? 'computer_amc');
        $title = trim((string) ($_POST['title'] ?? ''));
        $start = trim((string) ($_POST['start_date'] ?? ''));
        $end = trim((string) ($_POST['end_date'] ?? ''));
        $visitFreq = (string) ($_POST['visit_frequency'] ?? 'yearly');
        $nextSvc = trim((string) ($_POST['next_service_date'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'active');
        $cctvId = (int) ($_POST['cctv_installation_id'] ?? 0);
        $fee = max(0, (float) ($_POST['annual_fee'] ?? 0));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if (!isset($types[$contractType])) {
            $contractType = 'computer_amc';
        }
        if (!isset($freq[$visitFreq])) {
            $visitFreq = 'yearly';
        }
        if (!in_array($status, ['active', 'paused', 'expired', 'cancelled'], true)) {
            $status = 'active';
        }
        $endDate = ($end !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) ? $end : null;
        $nextDate = ($nextSvc !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextSvc)) ? $nextSvc : null;
        $startDate = ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) ? $start : '';

        if ($title === '' || $startDate === '') {
            flash_set('error', 'Title and start date are required.');
        } elseif ($contractType === 'cctv_maintenance' && $cctvId <= 0) {
            flash_set('error', 'Select a CCTV installation for this contract type.');
        } else {
            $pdo->prepare(
                'UPDATE maintenance_contracts SET contract_type=?, title=?, start_date=?, end_date=?, visit_frequency=?, next_service_date=?, status=?, cctv_installation_id=?, annual_fee=?, notes=? WHERE id=?'
            )->execute([
                $contractType,
                $title,
                $startDate,
                $endDate,
                $visitFreq,
                $nextDate,
                $status,
                $contractType === 'cctv_maintenance' ? $cctvId : null,
                $fee,
                $notes ?: null,
                $id,
            ]);
            flash_set('success', 'Contract updated.');
            redirect('/modules/maintenance/edit.php?id=' . $id);
        }
    } elseif ($action === 'add_visit') {
        $visitDate = trim((string) ($_POST['visit_date'] ?? ''));
        $techId = (int) ($_POST['technician_id'] ?? 0);
        $work = trim((string) ($_POST['work_performed'] ?? ''));
        $checks = trim((string) ($_POST['checks_done'] ?? ''));
        $charges = max(0, (float) ($_POST['charges'] ?? 0));
        $nextAfter = trim((string) ($_POST['next_service_date_visit'] ?? ''));
        $nextAfterDate = ($nextAfter !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextAfter)) ? $nextAfter : null;

        if ($visitDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate)) {
            flash_set('error', 'Valid visit date required.');
        } else {
            $pdo->prepare(
                'INSERT INTO maintenance_visits (contract_id, visit_date, technician_id, work_performed, checks_done, charges, next_service_date)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $id,
                $visitDate,
                $techId > 0 ? $techId : null,
                $work ?: null,
                $checks ?: null,
                $charges,
                $nextAfterDate,
            ]);
            if ($nextAfterDate) {
                $pdo->prepare('UPDATE maintenance_contracts SET next_service_date = ? WHERE id = ?')->execute([$nextAfterDate, $id]);
            }
            flash_set('success', 'Service visit logged.');
            redirect('/modules/maintenance/edit.php?id=' . $id);
        }
    }

    $st->execute([$id]);
    $row = $st->fetch();
}

$visits = $pdo->prepare(
    'SELECT v.*, t.name AS technician_name
     FROM maintenance_visits v
     LEFT JOIN technicians t ON t.id = v.technician_id
     WHERE v.contract_id = ?
     ORDER BY v.visit_date DESC, v.id DESC'
);
$visits->execute([$id]);
$visitRows = $visits->fetchAll();

$technicians = $pdo->query('SELECT id, name FROM technicians WHERE active = 1 ORDER BY name')->fetchAll();
$cctvJobs = $pdo->query(
    'SELECT v.id, v.job_number, v.location, c.name AS customer_name
     FROM cctv_installations v JOIN customers c ON c.id = v.customer_id
     ORDER BY v.id DESC LIMIT 500'
)->fetchAll();

$pageTitle = 'Maintenance contract';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/maintenance/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Contracts</a>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><code><?= e($row['contract_number']) ?></code></h1>
        <p class="text-muted mb-0"><?= e($row['customer_name']) ?> · <?= e($types[$row['contract_type']] ?? $row['contract_type']) ?></p>
    </div>
    <a class="btn btn-outline-danger btn-sm" href="<?= e(BASE_URL) ?>/modules/maintenance/delete.php?id=<?= $id ?>" onclick="return confirm('Delete this contract and all visit logs?');">Delete</a>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Contract details</div>
            <div class="card-body">
                <form method="post" data-loading>
                    <input type="hidden" name="action" value="update_contract">
                    <div class="mb-3">
                        <label class="form-label" for="contract_type">Contract type</label>
                        <select class="form-select" name="contract_type" id="contract_type">
                            <?php foreach ($types as $k => $lab): ?>
                                <option value="<?= e($k) ?>" <?= $row['contract_type'] === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="cctvWrap" style="display:none;">
                        <label class="form-label" for="cctv_installation_id">CCTV installation</label>
                        <select class="form-select" name="cctv_installation_id" id="cctv_installation_id">
                            <option value="">— Select —</option>
                            <?php foreach ($cctvJobs as $v): ?>
                                <?php
                                $loc = (string) $v['location'];
                                $locShort = strlen($loc) > 42 ? substr($loc, 0, 39) . '…' : $loc;
                                ?>
                                <option value="<?= (int) $v['id'] ?>" <?= (int) ($row['cctv_installation_id'] ?? 0) === (int) $v['id'] ? 'selected' : '' ?>>
                                    <?= e($v['job_number']) ?> — <?= e($v['customer_name']) ?> (<?= e($locShort) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="title">Title</label>
                        <input class="form-control" name="title" id="title" required maxlength="255" value="<?= e($row['title']) ?>">
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label" for="start_date">Start</label>
                            <input type="date" class="form-control" name="start_date" id="start_date" required value="<?= e($row['start_date']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="end_date">End</label>
                            <input type="date" class="form-control" name="end_date" id="end_date" value="<?= e($row['end_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="next_service_date">Next service</label>
                            <input type="date" class="form-control" name="next_service_date" id="next_service_date" value="<?= e($row['next_service_date'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="form-label" for="visit_frequency">Frequency</label>
                            <select class="form-select" name="visit_frequency" id="visit_frequency">
                                <?php foreach ($freq as $k => $lab): ?>
                                    <option value="<?= e($k) ?>" <?= $row['visit_frequency'] === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="annual_fee">Fee / retainer</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="annual_fee" id="annual_fee" value="<?= e((string) $row['annual_fee']) ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" name="status" id="status">
                            <?php foreach (['active', 'paused', 'expired', 'cancelled'] as $s): ?>
                                <option value="<?= e($s) ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="notes">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" rows="2"><?= e($row['notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save contract</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Log service visit</div>
            <div class="card-body">
                <form method="post" class="row g-2" data-loading>
                    <input type="hidden" name="action" value="add_visit">
                    <div class="col-12">
                        <label class="form-label" for="visit_date">Visit date</label>
                        <input type="date" class="form-control" name="visit_date" id="visit_date" required value="<?= e(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="technician_id">Technician</label>
                        <select class="form-select" name="technician_id" id="technician_id">
                            <option value="">— Optional —</option>
                            <?php foreach ($technicians as $t): ?>
                                <option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="work_performed">Work performed</label>
                        <textarea class="form-control" name="work_performed" id="work_performed" rows="2" placeholder="Cleaning, repairs, etc."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="checks_done">Checks / tests</label>
                        <textarea class="form-control" name="checks_done" id="checks_done" rows="2" placeholder="Recording, lens, cable check…"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="charges">Charges</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="charges" id="charges" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="next_service_date_visit">Next service (updates contract)</label>
                        <input type="date" class="form-control" name="next_service_date_visit" id="next_service_date_visit">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">Add visit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card vk-card">
    <div class="card-header bg-transparent fw-semibold">Visit history</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Technician</th>
                    <th>Work</th>
                    <th>Checks</th>
                    <th class="text-end">Charges</th>
                    <th>Next</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$visitRows): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No visits logged yet.</td></tr>
            <?php else: ?>
                <?php foreach ($visitRows as $v): ?>
                    <tr>
                        <td><?= e($v['visit_date']) ?></td>
                        <td><?= e($v['technician_name'] ?? '—') ?></td>
                        <td class="small"><?= nl2br(e($v['work_performed'] ?? '—')) ?></td>
                        <td class="small"><?= nl2br(e($v['checks_done'] ?? '—')) ?></td>
                        <td class="text-end"><?= e(number_format((float) $v['charges'], 2)) ?></td>
                        <td><?= !empty($v['next_service_date']) ? e($v['next_service_date']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
  var type = document.getElementById('contract_type');
  var wrap = document.getElementById('cctvWrap');
  function sync() {
    if (!type || !wrap) return;
    wrap.style.display = type.value === 'cctv_maintenance' ? 'block' : 'none';
  }
  if (type) type.addEventListener('change', sync);
  sync();
})();
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
