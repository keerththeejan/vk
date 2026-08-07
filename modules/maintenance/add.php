<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$types = ['computer_amc' => 'Computer AMC', 'cctv_maintenance' => 'CCTV maintenance'];
$freq = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'one_time' => 'One-time'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
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

    if ($customerId <= 0 || $title === '' || $startDate === '') {
        flash_set('error', 'Customer, title, and start date are required.');
    } elseif ($contractType === 'cctv_maintenance' && $cctvId <= 0) {
        flash_set('error', 'Link a CCTV installation for CCTV maintenance contracts.');
    } else {
        try {
            $pdo->beginTransaction();
            $num = next_maintenance_contract_number($pdo);
            $pdo->prepare(
                'INSERT INTO maintenance_contracts (contract_number, customer_id, contract_type, title, start_date, end_date, visit_frequency, next_service_date, status, cctv_installation_id, annual_fee, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $num,
                $customerId,
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
            ]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();
            flash_set('success', 'Contract ' . $num . ' created.');
            redirect('/modules/maintenance/edit.php?id=' . $newId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not save contract.');
        }
    }
}

$pageTitle = 'New maintenance contract';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$prefillCustomerGet = (int) ($_GET['customer_id'] ?? 0);

$customers = $pdo->query('SELECT id, name, phone FROM customers ORDER BY name')->fetchAll();
$cctvJobs = $pdo->query(
    'SELECT v.id, v.job_number, v.location, c.name AS customer_name
     FROM cctv_installations v JOIN customers c ON c.id = v.customer_id
     ORDER BY v.id DESC LIMIT 500'
)->fetchAll();
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/maintenance/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">New maintenance contract</h1>
<div class="card vk-card" style="max-width: 800px;">
    <div class="card-body">
        <form method="post" data-loading id="contractForm">
            <div class="mb-3">
                <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                <select class="form-select" name="customer_id" id="customer_id" required>
                    <option value="">— Select —</option>
                    <?php $selCust = (int) ($_POST['customer_id'] ?? ($prefillCustomerGet ?: 0)); ?>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $selCust === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="contract_type">Contract type</label>
                    <select class="form-select" name="contract_type" id="contract_type">
                        <?php foreach ($types as $k => $lab): ?>
                            <option value="<?= e($k) ?>" <?= ($_POST['contract_type'] ?? 'computer_amc') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="visit_frequency">Visit frequency</label>
                    <select class="form-select" name="visit_frequency" id="visit_frequency">
                        <?php foreach ($freq as $k => $lab): ?>
                            <option value="<?= e($k) ?>" <?= ($_POST['visit_frequency'] ?? 'yearly') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3 mt-3" id="cctvWrap" style="display:none;">
                <label class="form-label" for="cctv_installation_id">CCTV installation <span class="text-danger">*</span></label>
                <select class="form-select" name="cctv_installation_id" id="cctv_installation_id">
                    <option value="">— Select CCTV job —</option>
                    <?php foreach ($cctvJobs as $v): ?>
                        <option value="<?= (int) $v['id'] ?>" <?= (int) ($_POST['cctv_installation_id'] ?? 0) === (int) $v['id'] ? 'selected' : '' ?>>
                            <?php
                            $loc = (string) $v['location'];
                            $locShort = strlen($loc) > 42 ? substr($loc, 0, 39) . '…' : $loc;
                            ?>
                            <?= e($v['job_number']) ?> — <?= e($v['customer_name']) ?> (<?= e($locShort) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="title">Title <span class="text-danger">*</span></label>
                <input class="form-control" name="title" id="title" required maxlength="255" value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g. Office AMC 2026">
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="start_date">Start date <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="start_date" id="start_date" required value="<?= e($_POST['start_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="end_date">End date</label>
                    <input type="date" class="form-control" name="end_date" id="end_date" value="<?= e($_POST['end_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="next_service_date">Next service date</label>
                    <input type="date" class="form-control" name="next_service_date" id="next_service_date" value="<?= e($_POST['next_service_date'] ?? '') ?>">
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-6">
                    <label class="form-label" for="annual_fee">Contract fee / retainer</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="annual_fee" id="annual_fee" value="<?= e($_POST['annual_fee'] ?? '0') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" name="status" id="status">
                        <?php foreach (['active', 'paused', 'expired', 'cancelled'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= ($_POST['status'] ?? 'active') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" name="notes" id="notes" rows="2"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Create contract</button>
        </form>
    </div>
</div>
<script>
(function () {
  var type = document.getElementById('contract_type');
  var wrap = document.getElementById('cctvWrap');
  var sel = document.getElementById('cctv_installation_id');
  function sync() {
    var cctv = type && type.value === 'cctv_maintenance';
    if (wrap) wrap.style.display = cctv ? 'block' : 'none';
    if (sel && !cctv) { sel.value = ''; }
  }
  if (type) type.addEventListener('change', sync);
  sync();
})();
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
