<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$prefillRepairId = (int) ($_GET['repair_job_id'] ?? 0);
$prefillCctvId = (int) ($_GET['cctv_job_id'] ?? 0);
$prefillCustomerId = 0;
$prefillLabel = '';
$prefillRepair = null;
$prefillCctv = null;

if ($prefillRepairId > 0) {
    $st = $pdo->prepare(
        'SELECT r.*, c.name AS customer_name FROM repair_jobs r JOIN customers c ON c.id = r.customer_id WHERE r.id = ?'
    );
    $st->execute([$prefillRepairId]);
    $prefillRepair = $st->fetch();
    if ($prefillRepair && empty($prefillRepair['invoice_id'])) {
        $prefillCustomerId = (int) $prefillRepair['customer_id'];
        $prefillLabel = 'Linked repair job ' . $prefillRepair['job_number'] . ' — ' . $prefillRepair['customer_name'];
    } else {
        $prefillRepairId = 0;
        $prefillRepair = null;
    }
}
if ($prefillCctvId > 0) {
    $st = $pdo->prepare(
        'SELECT v.*, c.name AS customer_name FROM cctv_installations v JOIN customers c ON c.id = v.customer_id WHERE v.id = ?'
    );
    $st->execute([$prefillCctvId]);
    $prefillCctv = $st->fetch();
    if ($prefillCctv && empty($prefillCctv['invoice_id'])) {
        $prefillCustomerId = (int) $prefillCctv['customer_id'];
        $prefillLabel = 'Linked CCTV job ' . $prefillCctv['job_number'] . ' — ' . $prefillCctv['customer_name'];
    } else {
        $prefillCctvId = 0;
        $prefillCctv = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $invoiceDate = trim((string) ($_POST['invoice_date'] ?? ''));
    $discount = max(0, (float) ($_POST['discount'] ?? 0));
    $tax = max(0, (float) ($_POST['tax'] ?? 0));
    $notes = trim((string) ($_POST['notes'] ?? ''));
    $repairJobId = (int) ($_POST['repair_job_id'] ?? 0);
    $cctvJobId = (int) ($_POST['cctv_job_id'] ?? 0);

    $types = $_POST['line_type'] ?? [];
    $productIds = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $svcDesc = $_POST['service_desc'] ?? [];
    $svcUnit = $_POST['service_unit'] ?? [];

    if ($customerId <= 0 || $invoiceDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate)) {
        flash_set('error', 'Select customer and valid invoice date.');
        redirect('/modules/invoices/create.php');
    }

    if ($repairJobId > 0 && $cctvJobId > 0) {
        flash_set('error', 'Link either a repair job or a CCTV job, not both.');
        redirect('/modules/invoices/create.php');
    }

    $accSt = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
    $accSt->execute([$customerId]);
    $customerAccountId = $accSt->fetchColumn();
    if (!$customerAccountId) {
        flash_set('error', 'Customer account not found.');
        redirect('/modules/invoices/create.php');
    }
    $customerAccountId = (int) $customerAccountId;

    if ($repairJobId > 0) {
        $chk = $pdo->prepare('SELECT id, customer_id, invoice_id FROM repair_jobs WHERE id = ?');
        $chk->execute([$repairJobId]);
        $rj = $chk->fetch();
        if (!$rj || (int) $rj['customer_id'] !== $customerId || !empty($rj['invoice_id'])) {
            flash_set('error', 'Invalid repair job link or job already invoiced.');
            redirect('/modules/invoices/create.php');
        }
    }
    if ($cctvJobId > 0) {
        $chk = $pdo->prepare('SELECT id, customer_id, invoice_id FROM cctv_installations WHERE id = ?');
        $chk->execute([$cctvJobId]);
        $cj = $chk->fetch();
        if (!$cj || (int) $cj['customer_id'] !== $customerId || !empty($cj['invoice_id'])) {
            flash_set('error', 'Invalid CCTV job link or job already invoiced.');
            redirect('/modules/invoices/create.php');
        }
    }

    $lines = [];
    $subtotal = 0.0;
    $n = is_array($types) ? count($types) : 0;
    if ($n === 0) {
        flash_set('error', 'Add at least one line item.');
        redirect('/modules/invoices/create.php');
    }

    for ($i = 0; $i < $n; $i++) {
        $t = (string) ($types[$i] ?? '');
        $qty = (int) ($qtys[$i] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        if ($t === 'product') {
            $pid = (int) ($productIds[$i] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $pst = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE id = ?');
            $pst->execute([$pid]);
            $prod = $pst->fetch();
            if (!$prod) {
                flash_set('error', 'Invalid product on line ' . ($i + 1) . '.');
                redirect('/modules/invoices/create.php');
            }
            if ((int) $prod['stock'] < $qty) {
                flash_set('error', 'Insufficient stock for ' . $prod['name'] . ' (available ' . $prod['stock'] . ').');
                redirect('/modules/invoices/create.php');
            }
            $unit = (float) $prod['price'];
            $lineTotal = round($unit * $qty, 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'type' => 'product',
                'product_id' => $pid,
                'desc' => null,
                'qty' => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
            ];
        } elseif ($t === 'service') {
            $desc = trim((string) ($svcDesc[$i] ?? ''));
            $unit = max(0, (float) ($svcUnit[$i] ?? 0));
            if ($desc === '') {
                flash_set('error', 'Service description required on line ' . ($i + 1) . '.');
                redirect('/modules/invoices/create.php');
            }
            $lineTotal = round($unit * $qty, 2);
            $subtotal += $lineTotal;
            $lines[] = [
                'type' => 'service',
                'product_id' => null,
                'desc' => $desc,
                'qty' => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
            ];
        }
    }

    if ($lines === []) {
        flash_set('error', 'Add at least one valid line item.');
        redirect('/modules/invoices/create.php');
    }

    $subtotal = round($subtotal, 2);
    $grand = round($subtotal - $discount + $tax, 2);
    if ($grand < 0) {
        flash_set('error', 'Grand total cannot be negative.');
        redirect('/modules/invoices/create.php');
    }

    $source = 'manual';
    if ($repairJobId > 0) {
        $source = 'repair';
    }
    if ($cctvJobId > 0) {
        $source = 'cctv';
    }

    try {
        $pdo->beginTransaction();
        $invNo = next_invoice_number($pdo);
        $stInv = $pdo->prepare(
            'INSERT INTO invoices (invoice_number, customer_id, invoice_date, subtotal, discount, tax, grand_total, paid_amount, status, notes, source, repair_job_id, cctv_job_id)
             VALUES (?,?,?,?,?,?,?,0,\'unpaid\',?,?,?,?)'
        );
        $stInv->execute([
            $invNo,
            $customerId,
            $invoiceDate,
            $subtotal,
            $discount,
            $tax,
            $grand,
            $notes ?: null,
            $source,
            $repairJobId > 0 ? $repairJobId : null,
            $cctvJobId > 0 ? $cctvJobId : null,
        ]);
        $invoiceId = (int) $pdo->lastInsertId();

        $stItem = $pdo->prepare(
            'INSERT INTO invoice_items (invoice_id, item_type, product_id, line_description, quantity, unit_price, line_total)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

        foreach ($lines as $ln) {
            if ($ln['type'] === 'product') {
                $stItem->execute([
                    $invoiceId,
                    'product',
                    $ln['product_id'],
                    null,
                    $ln['qty'],
                    $ln['unit_price'],
                    $ln['line_total'],
                ]);
                $stStock->execute([$ln['qty'], $ln['product_id'], $ln['qty']]);
                if ($stStock->rowCount() === 0) {
                    throw new RuntimeException('Stock conflict for product ID ' . $ln['product_id']);
                }
            } else {
                $stItem->execute([
                    $invoiceId,
                    'service',
                    null,
                    $ln['desc'],
                    $ln['qty'],
                    $ln['unit_price'],
                    $ln['line_total'],
                ]);
            }
        }

        if ($repairJobId > 0) {
            $pdo->prepare('UPDATE repair_jobs SET invoice_id = ? WHERE id = ?')->execute([$invoiceId, $repairJobId]);
        }
        if ($cctvJobId > 0) {
            $pdo->prepare('UPDATE cctv_installations SET invoice_id = ? WHERE id = ?')->execute([$invoiceId, $cctvJobId]);
        }

        ledger_apply(
            $pdo,
            $customerAccountId,
            0,
            $grand,
            'Invoice ' . $invNo . ' — amount due',
            $invoiceId,
            null,
            null
        );

        $pdo->commit();
        flash_set('success', 'Invoice ' . $invNo . ' created.');
        redirect('/modules/invoices/view.php?id=' . $invoiceId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash_set('error', APP_DEBUG ? $e->getMessage() : 'Could not create invoice.');
        redirect('/modules/invoices/create.php');
    }
}

$pageTitle = 'Create invoice';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$products = $pdo->query('SELECT id, name, price, stock, category FROM products ORDER BY name')->fetchAll();
$extraScripts = '<script src="' . e(BASE_URL) . '/assets/js/invoice_create.js"></script>';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/invoices/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Create invoice</h1>

<?php if ($prefillLabel !== ''): ?>
    <div class="alert alert-info"><?= e($prefillLabel) ?></div>
<?php endif; ?>

<form method="post" id="invoiceForm" data-loading class="row g-3">
    <input type="hidden" name="repair_job_id" value="<?= $prefillRepairId > 0 ? (string) $prefillRepairId : '' ?>">
    <input type="hidden" name="cctv_job_id" value="<?= $prefillCctvId > 0 ? (string) $prefillCctvId : '' ?>">

    <div class="col-12 col-lg-4">
        <div class="card vk-card h-100">
            <div class="card-header bg-transparent fw-semibold">Customer</div>
            <div class="card-body position-relative">
                <input type="hidden" name="customer_id" id="customer_id" value="<?= $prefillCustomerId > 0 ? (string) $prefillCustomerId : '' ?>" required>
                <label class="form-label" for="customer_search">Search customer</label>
                <input type="text" class="form-control" id="customer_search" placeholder="Type name, phone, or email" autocomplete="off"
                    value="<?= $prefillRepair ? e($prefillRepair['customer_name']) : ($prefillCctv ? e($prefillCctv['customer_name']) : '') ?>">
                <div class="list-group mt-1 shadow-sm position-absolute w-100 d-none" id="customer_results" style="z-index: 20; max-height: 220px; overflow-y: auto;"></div>
                <div class="mt-2 small text-muted" id="customer_selected">
                    <?= $prefillCustomerId > 0 ? 'Selected from job link.' : 'No customer selected.' ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">Details</div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="invoice_date">Invoice date</label>
                    <input type="date" class="form-control" name="invoice_date" id="invoice_date" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="discount">Discount</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="discount" id="discount" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="tax">Tax</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="tax" id="tax" value="0">
                </div>
                <div class="col-12">
                    <label class="form-label" for="notes">Notes</label>
                    <textarea class="form-control" name="notes" id="notes" rows="2" maxlength="2000"><?= e(
                        $prefillRepair
                            ? 'Repair job: ' . $prefillRepair['job_number'] . ' — Est. ' . number_format((float) $prefillRepair['estimated_cost'], 2)
                            : ($prefillCctv
                                ? 'CCTV job: ' . $prefillCctv['job_number'] . ' @ ' . $prefillCctv['location']
                                : '')
                    ) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card vk-card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="fw-semibold">Line items</span>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addProductLine"><i class="bi bi-plus-lg"></i> Add part / product</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="addServiceLine"><i class="bi bi-wrench-adjustable"></i> Add service / charge</button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle" id="linesTable">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width:220px">Item</th>
                                <th style="width:110px">Qty</th>
                                <th style="width:120px">Unit price</th>
                                <th style="width:120px">Line total</th>
                                <th style="width:60px"></th>
                            </tr>
                        </thead>
                        <tbody id="linesBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <div class="row justify-content-end">
                    <div class="col-12 col-md-5 col-lg-4">
                        <dl class="row small mb-0">
                            <dt class="col-6">Subtotal</dt>
                            <dd class="col-6 text-end" id="disp_subtotal">0.00</dd>
                            <dt class="col-6">Discount</dt>
                            <dd class="col-6 text-end" id="disp_discount">0.00</dd>
                            <dt class="col-6">Tax</dt>
                            <dd class="col-6 text-end" id="disp_tax">0.00</dd>
                            <dt class="col-6 fw-bold">Grand total</dt>
                            <dd class="col-6 text-end fw-bold" id="disp_grand">0.00</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2-circle me-1"></i>Save invoice</button>
    </div>
</form>

<template id="lineTplProduct">
    <tr class="line-row" data-line-kind="product">
        <td>
            <input type="hidden" name="line_type[]" value="product">
            <input type="hidden" name="service_desc[]" value="">
            <input type="hidden" name="service_unit[]" value="0">
            <select class="form-select product-select" name="product_id[]" required>
                <option value="">— Select part / product —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" data-price="<?= e((string) $p['price']) ?>" data-stock="<?= (int) $p['stock'] ?>">
                        <?= e($p['name']) ?> (<?= e(number_format((float) $p['price'], 2)) ?>) — stock <?= (int) $p['stock'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" class="form-control qty-input" name="qty[]" min="1" value="1" required></td>
        <td><span class="unit-price text-muted">0.00</span></td>
        <td><span class="line-total fw-semibold">0.00</span></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger rm-line" title="Remove">&times;</button></td>
    </tr>
</template>

<template id="lineTplService">
    <tr class="line-row" data-line-kind="service">
        <td>
            <input type="hidden" name="line_type[]" value="service">
            <input type="hidden" name="product_id[]" value="">
            <input type="text" class="form-control service-desc" name="service_desc[]" placeholder="e.g. Repair labour, CCTV config" required maxlength="512">
        </td>
        <td><input type="number" class="form-control qty-input" name="qty[]" min="1" value="1" required></td>
        <td><input type="number" step="0.01" min="0" class="form-control service-unit" name="service_unit[]" value="0" required></td>
        <td><span class="line-total fw-semibold">0.00</span></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger rm-line" title="Remove">&times;</button></td>
    </tr>
</template>

<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
