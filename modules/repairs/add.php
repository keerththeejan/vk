<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/service_templates_service.php';

$deviceAllowed = ['computer', 'printer', 'cctv_dvr', 'automobile', 'ac', 'electrical', 'other'];
$printerIssues = [
    '' => '— Select if printer —',
    'cartridge' => 'Cartridge / toner issue',
    'paper_jam' => 'Paper jam',
    'roller' => 'Roller replacement',
    'ink_refill' => 'Ink refill',
    'other' => 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $deviceType = (string) ($_POST['device_type'] ?? 'other');
    $problem = trim((string) ($_POST['problem_description'] ?? ''));
    $accessories = trim((string) ($_POST['accessories_received'] ?? ''));
    $estimated = max(0, (float) ($_POST['estimated_cost'] ?? 0));
    $warranty = trim((string) ($_POST['warranty_expiry'] ?? ''));
    $warrantyDate = ($warranty !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $warranty)) ? $warranty : null;
    $technicianId = (int) ($_POST['technician_id'] ?? 0);
    $printerIssue = trim((string) ($_POST['printer_issue'] ?? ''));
    $templateId = (int) ($_POST['service_template_id'] ?? 0);

    if (!in_array($deviceType, $deviceAllowed, true)) {
        $deviceType = 'other';
    }
    if ($deviceType !== 'printer') {
        $printerIssue = '';
    }
    $allowedPi = ['', 'cartridge', 'paper_jam', 'roller', 'ink_refill', 'other'];
    if (!in_array($printerIssue, $allowedPi, true)) {
        $printerIssue = '';
    }
    if ($templateId > 0) {
        $tst = $pdo->prepare('SELECT default_amount FROM service_templates WHERE id = ?');
        $tst->execute([$templateId]);
        $tplAmt = $tst->fetchColumn();
        if ($tplAmt !== false && $estimated <= 0) {
            $estimated = (float) $tplAmt;
        }
    }

    if ($customerId <= 0) {
        flash_set('error', 'Select a customer.');
    } elseif ($problem === '') {
        flash_set('error', 'Describe the problem.');
    } else {
        try {
            $pdo->beginTransaction();
            $jobNo = next_repair_job_number($pdo);
            $pdo->prepare(
                'INSERT INTO repair_jobs (job_number, customer_id, device_type, problem_description, accessories_received, technician_id, printer_issue, service_template_id, estimated_cost, status, warranty_expiry)
                 VALUES (?,?,?,?,?,?,?,?,?, \'pending\', ?)'
            )->execute([
                $jobNo,
                $customerId,
                $deviceType,
                $problem,
                $accessories ?: null,
                $technicianId > 0 ? $technicianId : null,
                $printerIssue !== '' ? $printerIssue : null,
                $templateId > 0 ? $templateId : null,
                $estimated,
                $warrantyDate,
            ]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();
            flash_set('success', 'Job card ' . $jobNo . ' created.');
            redirect('/modules/repairs/view.php?id=' . $newId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not create job.');
        }
    }
}

$pageTitle = 'New repair job';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$customers = $pdo->query('SELECT id, name, phone FROM customers ORDER BY name')->fetchAll();
$technicians = $pdo->query('SELECT id, name FROM technicians WHERE active = 1 ORDER BY name')->fetchAll();
$templates = vk_st_templates_for_select($pdo);
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/repairs/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">New repair job</h1>
<div class="card vk-card" style="max-width: 800px;">
    <div class="card-body">
        <form method="post" data-loading id="repairForm">
            <div class="mb-3">
                <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                <select class="form-select" name="customer_id" id="customer_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($_POST['customer_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="device_type">Device type</label>
                    <select class="form-select" name="device_type" id="device_type">
                        <?php foreach (['computer' => 'Computer', 'printer' => 'Printer', 'cctv_dvr' => 'CCTV / DVR', 'automobile' => 'Automobile / breakdown', 'ac' => 'AC repair', 'electrical' => 'Electrical (DC)', 'other' => 'Other'] as $k => $lab): ?>
                            <option value="<?= e($k) ?>" <?= ($_POST['device_type'] ?? 'computer') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="technician_id">Technician</label>
                    <select class="form-select" name="technician_id" id="technician_id">
                        <option value="">— Assign later —</option>
                        <?php foreach ($technicians as $t): ?>
                            <option value="<?= (int) $t['id'] ?>" <?= (int) ($_POST['technician_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3 mt-3" id="printerFields" style="display:none;">
                <label class="form-label" for="printer_issue">Printer issue type</label>
                <select class="form-select" name="printer_issue" id="printer_issue">
                    <?php foreach ($printerIssues as $k => $lab): ?>
                        <option value="<?= e($k) ?>" <?= ($_POST['printer_issue'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="service_template_id">Service charge template (optional)</label>
                <select class="form-select" name="service_template_id" id="service_template_id">
                    <option value="">— None —</option>
                    <?php foreach ($templates as $tpl): ?>
                        <option value="<?= (int) $tpl['id'] ?>" data-amount="<?= e((string) $tpl['default_amount']) ?>" <?= (int) ($_POST['service_template_id'] ?? 0) === (int) $tpl['id'] ? 'selected' : '' ?>>
                            [<?= e($tpl['category']) ?>] <?= e($tpl['name']) ?> — <?= e(number_format((float) $tpl['default_amount'], 2)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Selecting a template can fill the estimated charge if left at zero.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="problem_description">Problem description <span class="text-danger">*</span></label>
                <textarea class="form-control" name="problem_description" id="problem_description" rows="3" required maxlength="4000"><?= e($_POST['problem_description'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="accessories_received">Accessories received</label>
                <textarea class="form-control" name="accessories_received" id="accessories_received" rows="2" maxlength="2000"><?= e($_POST['accessories_received'] ?? '') ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="estimated_cost">Estimated cost</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="estimated_cost" id="estimated_cost" value="<?= e($_POST['estimated_cost'] ?? '0') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="warranty_expiry">Repair warranty until (optional)</label>
                    <input type="date" class="form-control" name="warranty_expiry" id="warranty_expiry" value="<?= e($_POST['warranty_expiry'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Create job card</button>
        </form>
    </div>
</div>
<script>
(function () {
  var dev = document.getElementById('device_type');
  var pf = document.getElementById('printerFields');
  var tpl = document.getElementById('service_template_id');
  var est = document.getElementById('estimated_cost');
  function syncPrinter() {
    if (!dev || !pf) return;
    pf.style.display = dev.value === 'printer' ? 'block' : 'none';
  }
  if (dev) dev.addEventListener('change', syncPrinter);
  syncPrinter();
  if (tpl && est) {
    tpl.addEventListener('change', function () {
      var opt = tpl.options[tpl.selectedIndex];
      var a = opt.getAttribute('data-amount');
      if (a && (parseFloat(est.value) === 0 || est.value === '')) {
        est.value = a;
      }
    });
  }
})();
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
