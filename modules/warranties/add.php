<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$prefillCustomer = (int) ($_GET['customer_id'] ?? 0);
$prefillRepair = (int) ($_GET['repair_job_id'] ?? 0);
$prefillCctv = (int) ($_GET['cctv_installation_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $desc = trim((string) ($_POST['description'] ?? ''));
    $wtype = (string) ($_POST['warranty_type'] ?? 'service');
    $start = trim((string) ($_POST['start_date'] ?? ''));
    $end = trim((string) ($_POST['end_date'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $repairId = (int) ($_POST['repair_job_id'] ?? 0);
    $cctvId = (int) ($_POST['cctv_installation_id'] ?? 0);
    $invId = (int) ($_POST['invoice_id'] ?? 0);

    if (!in_array($wtype, ['service', 'product'], true)) {
        $wtype = 'service';
    }
    $startOk = ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) ? $start : '';
    $endOk = ($end !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) ? $end : '';

    if ($customerId <= 0 || $title === '' || $startOk === '' || $endOk === '') {
        flash_set('error', 'Customer, title, start and end dates are required.');
    } elseif ($startOk > $endOk) {
        flash_set('error', 'End date must be on or after start date.');
    } else {
        try {
            $pdo->prepare(
                'INSERT INTO warranty_records (customer_id, title, description, warranty_type, start_date, end_date, notes, repair_job_id, cctv_installation_id, invoice_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $customerId,
                $title,
                $desc ?: null,
                $wtype,
                $startOk,
                $endOk,
                $notes ?: null,
                $repairId > 0 ? $repairId : null,
                $cctvId > 0 ? $cctvId : null,
                $invId > 0 ? $invId : null,
            ]);
            flash_set('success', 'Warranty record saved.');
            redirect('/modules/warranties/list.php');
        } catch (Throwable $e) {
            flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not save warranty.');
        }
    }
} else {
    if ($prefillRepair > 0) {
        $st = $pdo->prepare('SELECT customer_id FROM repair_jobs WHERE id = ?');
        $st->execute([$prefillRepair]);
        $prefillCustomer = (int) $st->fetchColumn() ?: $prefillCustomer;
    }
    if ($prefillCctv > 0) {
        $st = $pdo->prepare('SELECT customer_id FROM cctv_installations WHERE id = ?');
        $st->execute([$prefillCctv]);
        $prefillCustomer = (int) $st->fetchColumn() ?: $prefillCustomer;
    }
}

$pageTitle = 'Add warranty';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$customers = $pdo->query('SELECT id, name, phone FROM customers ORDER BY name')->fetchAll();
$repairs = $pdo->query(
    'SELECT r.id, r.job_number, c.name AS customer_name FROM repair_jobs r JOIN customers c ON c.id = r.customer_id ORDER BY r.id DESC LIMIT 400'
)->fetchAll();
$cctvJobs = $pdo->query(
    'SELECT v.id, v.job_number, c.name AS customer_name FROM cctv_installations v JOIN customers c ON c.id = v.customer_id ORDER BY v.id DESC LIMIT 400'
)->fetchAll();
$invoices = $pdo->query(
    'SELECT id, invoice_number, customer_id FROM invoices ORDER BY id DESC LIMIT 400'
)->fetchAll();

$pc = (int) ($_POST['customer_id'] ?? $prefillCustomer);
$pr = (int) ($_POST['repair_job_id'] ?? $prefillRepair);
$pv = (int) ($_POST['cctv_installation_id'] ?? $prefillCctv);
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/warranties/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Add warranty record</h1>
<div class="card vk-card" style="max-width: 800px;">
    <div class="card-body">
        <form method="post" data-loading>
            <div class="mb-3">
                <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
                <select class="form-select" name="customer_id" id="customer_id" required>
                    <option value="">— Select —</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $pc === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="title">Title <span class="text-danger">*</span></label>
                <input class="form-control" name="title" id="title" required maxlength="255" value="<?= e($_POST['title'] ?? '') ?>" placeholder="e.g. Laptop repair warranty">
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <input class="form-control" name="description" id="description" maxlength="512" value="<?= e($_POST['description'] ?? '') ?>">
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="warranty_type">Warranty type</label>
                    <select class="form-select" name="warranty_type" id="warranty_type">
                        <option value="service" <?= ($_POST['warranty_type'] ?? 'service') === 'service' ? 'selected' : '' ?>>Service</option>
                        <option value="product" <?= ($_POST['warranty_type'] ?? '') === 'product' ? 'selected' : '' ?>>Product</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="start_date">Start <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="start_date" id="start_date" required value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="end_date">End <span class="text-danger">*</span></label>
                    <input type="date" class="form-control" name="end_date" id="end_date" required value="<?= e($_POST['end_date'] ?? '') ?>">
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-4">
                    <label class="form-label" for="repair_job_id">Linked repair job</label>
                    <select class="form-select" name="repair_job_id" id="repair_job_id">
                        <option value="">— None —</option>
                        <?php foreach ($repairs as $r): ?>
                            <option value="<?= (int) $r['id'] ?>" <?= $pr === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['job_number']) ?> · <?= e($r['customer_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="cctv_installation_id">Linked CCTV job</label>
                    <select class="form-select" name="cctv_installation_id" id="cctv_installation_id">
                        <option value="">— None —</option>
                        <?php foreach ($cctvJobs as $v): ?>
                            <option value="<?= (int) $v['id'] ?>" <?= $pv === (int) $v['id'] ? 'selected' : '' ?>><?= e($v['job_number']) ?> · <?= e($v['customer_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="invoice_id">Linked invoice</label>
                    <select class="form-select" name="invoice_id" id="invoice_id">
                        <option value="">— None —</option>
                        <?php foreach ($invoices as $inv): ?>
                            <option value="<?= (int) $inv['id'] ?>" <?= (int) ($_POST['invoice_id'] ?? 0) === (int) $inv['id'] ? 'selected' : '' ?>><?= e($inv['invoice_number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" name="notes" id="notes" rows="2"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save warranty</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
