<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/service_templates_service.php';

$deviceAllowed = ['computer', 'printer', 'cctv_dvr', 'automobile', 'ac', 'electrical', 'other'];
$statusAllowed = ['pending', 'diagnosing', 'in_progress', 'completed', 'delivered'];
$printerIssues = [
    '' => '— None —',
    'cartridge' => 'Cartridge / toner issue',
    'paper_jam' => 'Paper jam',
    'roller' => 'Roller replacement',
    'ink_refill' => 'Ink refill',
    'other' => 'Other',
];

$id = (int) ($_GET['id'] ?? 0);
vk_repair_ensure_parts_table($pdo);
$st = $pdo->prepare(
    'SELECT r.*, c.name AS customer_name FROM repair_jobs r JOIN customers c ON c.id = r.customer_id WHERE r.id = ?'
);
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    flash_set('error', 'Job not found.');
    redirect('/modules/repairs/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_job') {
        $deviceType = (string) ($_POST['device_type'] ?? 'other');
        $problem = trim((string) ($_POST['problem_description'] ?? ''));
        $accessories = trim((string) ($_POST['accessories_received'] ?? ''));
        $estimated = max(0, (float) ($_POST['estimated_cost'] ?? 0));
        $status = (string) ($_POST['status'] ?? 'pending');
        $notes = trim((string) ($_POST['technician_notes'] ?? ''));
        $warranty = trim((string) ($_POST['warranty_expiry'] ?? ''));
        $warrantyDate = ($warranty !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $warranty)) ? $warranty : null;
        $technicianId = (int) ($_POST['technician_id'] ?? 0);
        $printerIssue = trim((string) ($_POST['printer_issue'] ?? ''));
        $templateId = (int) ($_POST['service_template_id'] ?? 0);

        if (!in_array($deviceType, $deviceAllowed, true)) {
            $deviceType = 'other';
        }
        if (!in_array($status, $statusAllowed, true)) {
            $status = 'pending';
        }
        if ($deviceType !== 'printer') {
            $printerIssue = '';
        }
        $allowedPi = ['', 'cartridge', 'paper_jam', 'roller', 'ink_refill', 'other'];
        if (!in_array($printerIssue, $allowedPi, true)) {
            $printerIssue = '';
        }

        if ($problem === '') {
            flash_set('error', 'Problem description required.');
        } else {
            $pdo->prepare(
                'UPDATE repair_jobs SET device_type=?, problem_description=?, accessories_received=?, technician_id=?, printer_issue=?, service_template_id=?, estimated_cost=?, status=?, technician_notes=?, warranty_expiry=? WHERE id=?'
            )->execute([
                $deviceType,
                $problem,
                $accessories ?: null,
                $technicianId > 0 ? $technicianId : null,
                $printerIssue !== '' ? $printerIssue : null,
                $templateId > 0 ? $templateId : null,
                $estimated,
                $status,
                $notes ?: null,
                $warrantyDate,
                $id,
            ]);
            flash_set('success', 'Job updated.');
            redirect('/modules/repairs/edit.php?id=' . $id);
        }
    } elseif ($action === 'add_part') {
        $pid = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['part_qty'] ?? 0);
        if ($row['status'] === 'delivered') {
            flash_set('error', 'Cannot add parts after delivery.');
        } elseif ($pid <= 0 || $qty <= 0) {
            flash_set('error', 'Select part and quantity.');
        } else {
            try {
                $pdo->beginTransaction();
                $pst = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE id = ? FOR UPDATE');
                $pst->execute([$pid]);
                $prod = $pst->fetch();
                if (!$prod || (int) $prod['stock'] < $qty) {
                    throw new RuntimeException('Insufficient stock or invalid part.');
                }
                $unit = (float) $prod['price'];
                $line = round($unit * $qty, 2);
                $stU = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
                $stU->execute([$qty, $pid, $qty]);
                if ($stU->rowCount() === 0) {
                    throw new RuntimeException('Stock conflict.');
                }
                $pdo->prepare(
                    'INSERT INTO repair_job_parts (repair_job_id, product_id, quantity, unit_price, line_total) VALUES (?,?,?,?,?)'
                )->execute([$id, $pid, $qty, $unit, $line]);
                $pdo->commit();
                flash_set('success', 'Part added and stock updated.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not add part.');
            }
            redirect('/modules/repairs/edit.php?id=' . $id);
        }
    } elseif ($action === 'remove_part') {
        $partRowId = (int) ($_POST['part_row_id'] ?? 0);
        if ($row['status'] === 'delivered') {
            flash_set('error', 'Cannot remove parts after delivery.');
        } else {
            try {
                $pdo->beginTransaction();
                $pr = $pdo->prepare('SELECT * FROM repair_job_parts WHERE id = ? AND repair_job_id = ? FOR UPDATE');
                $pr->execute([$partRowId, $id]);
                $p = $pr->fetch();
                if ($p) {
                    $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')->execute([(int) $p['quantity'], (int) $p['product_id']]);
                    $pdo->prepare('DELETE FROM repair_job_parts WHERE id = ?')->execute([$partRowId]);
                }
                $pdo->commit();
                flash_set('success', 'Part line removed; stock restored.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                flash_set('error', 'Could not remove part.');
            }
            redirect('/modules/repairs/edit.php?id=' . $id);
        }
    }
    $st->execute([$id]);
    $row = $st->fetch();
}

$partRows = [];
if (db_table_exists($pdo, 'repair_job_parts') && db_table_exists($pdo, 'products')) {
    $parts = $pdo->prepare(
        'SELECT jp.*, COALESCE(p.name, \'Deleted part\') AS product_name
         FROM repair_job_parts jp
         LEFT JOIN products p ON p.id = jp.product_id
         WHERE jp.repair_job_id = ?
         ORDER BY jp.id'
    );
    $parts->execute([$id]);
    $partRows = $parts->fetchAll();
}

$products = db_table_exists($pdo, 'products')
    ? $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll()
    : [];
$technicians = $pdo->query('SELECT id, name FROM technicians WHERE active = 1 ORDER BY name')->fetchAll();
$templates = vk_st_templates_for_select($pdo);

$pageTitle = 'Edit repair job';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$devType = (string) ($row['device_type'] ?? 'other');
if (!in_array($devType, $deviceAllowed, true)) {
    $devType = 'other';
}
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/repairs/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>View job</a>
</div>
<h1 class="h3 mb-1">Edit <?= e($row['job_number']) ?></h1>
<p class="text-muted small">Customer: <?= e($row['customer_name']) ?></p>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Job details</div>
            <div class="card-body">
                <form method="post" data-loading id="repairEditForm">
                    <input type="hidden" name="action" value="update_job">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="device_type">Device type</label>
                            <select class="form-select" name="device_type" id="device_type">
                                <?php foreach (['computer' => 'Computer', 'printer' => 'Printer', 'cctv_dvr' => 'CCTV / DVR', 'automobile' => 'Automobile / breakdown', 'ac' => 'AC repair', 'electrical' => 'Electrical (DC)', 'other' => 'Other'] as $k => $lab): ?>
                                    <option value="<?= e($k) ?>" <?= $devType === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="technician_id">Technician</label>
                            <select class="form-select" name="technician_id" id="technician_id">
                                <option value="">— Unassigned —</option>
                                <?php foreach ($technicians as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>" <?= (int) ($row['technician_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3" id="printerFields" style="display:<?= $devType === 'printer' ? 'block' : 'none' ?>;">
                        <label class="form-label" for="printer_issue">Printer issue</label>
                        <select class="form-select" name="printer_issue" id="printer_issue">
                            <?php foreach ($printerIssues as $k => $lab): ?>
                                <option value="<?= e($k) ?>" <?= (string) ($row['printer_issue'] ?? '') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="service_template_id">Service template</label>
                        <select class="form-select" name="service_template_id" id="service_template_id">
                            <option value="">— None —</option>
                            <?php foreach ($templates as $tpl): ?>
                                <option value="<?= (int) $tpl['id'] ?>" <?= (int) ($row['service_template_id'] ?? 0) === (int) $tpl['id'] ? 'selected' : '' ?>>
                                    [<?= e($tpl['category']) ?>] <?= e($tpl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="problem_description">Problem</label>
                        <textarea class="form-control" name="problem_description" id="problem_description" rows="3" required><?= e($row['problem_description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="accessories_received">Accessories received</label>
                        <textarea class="form-control" name="accessories_received" id="accessories_received" rows="2"><?= e($row['accessories_received'] ?? '') ?></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label" for="estimated_cost">Estimated cost</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="estimated_cost" id="estimated_cost" value="<?= e((string) $row['estimated_cost']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" name="status" id="status">
                                <?php foreach ($statusAllowed as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $row['status'] === $s ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="warranty_expiry">Warranty until</label>
                            <input type="date" class="form-control" name="warranty_expiry" id="warranty_expiry" value="<?= e($row['warranty_expiry'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label" for="technician_notes">Technician notes</label>
                        <textarea class="form-control" name="technician_notes" id="technician_notes" rows="2"><?= e($row['technician_notes'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-5">
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">Parts used (stock deducted)</div>
            <div class="card-body">
                <?php if (!$partRows): ?>
                    <p class="text-muted small mb-3">No parts logged yet.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush mb-3 small">
                        <?php foreach ($partRows as $pr): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold"><?= e($pr['product_name']) ?> × <?= (int) $pr['quantity'] ?></div>
                                    <div class="text-muted"><?= e(number_format((float) $pr['line_total'], 2)) ?></div>
                                </div>
                                <?php if ($row['status'] !== 'delivered'): ?>
                                    <form method="post" class="ms-2" onsubmit="return confirm('Remove this part line and restore stock?');">
                                        <input type="hidden" name="action" value="remove_part">
                                        <input type="hidden" name="part_row_id" value="<?= (int) $pr['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">&times;</button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if ($row['status'] !== 'delivered'): ?>
                    <form method="post" class="row g-2 align-items-end" data-loading>
                        <input type="hidden" name="action" value="add_part">
                        <div class="col-12">
                            <label class="form-label small">Add part</label>
                            <select class="form-select" name="product_id" required>
                                <option value="">— Select —</option>
                                <?php foreach ($products as $p): ?>
                                    <?php
                                    $low = isset($p['low_stock_threshold']) ? (int) $p['low_stock_threshold'] : 5;
                                    $isLow = (int) $p['stock'] <= $low;
                                    ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?> (<?= e(number_format((float) $p['price'], 2)) ?>) — stock <?= (int) $p['stock'] ?><?= $isLow ? ' ⚠ low' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <input type="number" min="1" class="form-control" name="part_qty" value="1" required>
                        </div>
                        <div class="col-7">
                            <button type="submit" class="btn btn-outline-primary w-100">Add part</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="small text-muted mb-0">Job delivered — parts are locked.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  var dev = document.getElementById('device_type');
  var pf = document.getElementById('printerFields');
  function syncPrinter() {
    if (!dev || !pf) return;
    pf.style.display = dev.value === 'printer' ? 'block' : 'none';
  }
  if (dev) dev.addEventListener('change', syncPrinter);
})();
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
