<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT c.*, a.id AS account_id, a.code AS account_code, a.current_balance
     FROM customers c
     JOIN accounts a ON a.customer_id = c.id
     WHERE c.id = ?'
);
$st->execute([$id]);
$c = $st->fetch();
if (!$c) {
    flash_set('error', 'Customer not found.');
    redirect('/modules/customers/list.php');
}

$pageTitle = 'Customer profile';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$repairs = $pdo->prepare(
    'SELECT id, job_number, device_type, status, estimated_cost, invoice_id, created_at
     FROM repair_jobs WHERE customer_id = ? ORDER BY id DESC'
);
$repairs->execute([$id]);
$repairRows = $repairs->fetchAll();

$cctvRows = $pdo->prepare(
    'SELECT id, job_number, location, status, installation_charge, invoice_id, created_at
     FROM cctv_installations WHERE customer_id = ? ORDER BY id DESC'
);
$cctvRows->execute([$id]);
$cctvRows = $cctvRows->fetchAll();

$invRows = $pdo->prepare(
    'SELECT id, invoice_number, invoice_date, grand_total, paid_amount, status
     FROM invoices WHERE customer_id = ? ORDER BY id DESC'
);
$invRows->execute([$id]);
$invRows = $invRows->fetchAll();

$invDue = $pdo->prepare(
    'SELECT COALESCE(SUM(grand_total - paid_amount),0) FROM invoices WHERE customer_id = ? AND (grand_total - paid_amount) > 0.001'
);
$invDue->execute([$id]);
$totalInvoiceDue = (float) $invDue->fetchColumn();

$maintRows = $pdo->prepare(
    'SELECT id, contract_number, title, contract_type, status, next_service_date, annual_fee, start_date
     FROM maintenance_contracts WHERE customer_id = ? ORDER BY id DESC'
);
$maintRows->execute([$id]);
$maintRows = $maintRows->fetchAll();

$warrRows = $pdo->prepare(
    'SELECT id, title, warranty_type, start_date, end_date, repair_job_id, cctv_installation_id
     FROM warranty_records WHERE customer_id = ? ORDER BY end_date ASC, id DESC'
);
$warrRows->execute([$id]);
$warrRows = $warrRows->fetchAll();
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/customers/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Customers</a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($c['name']) ?></h1>
        <p class="text-muted mb-0">
            <?php if ($c['phone']): ?><span class="me-3"><i class="bi bi-telephone me-1"></i><?= e($c['phone']) ?></span><?php endif; ?>
            <?php if ($c['email']): ?><span><i class="bi bi-envelope me-1"></i><?= e($c['email']) ?></span><?php endif; ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary btn-sm" href="<?= e(BASE_URL) ?>/modules/customers/edit.php?id=<?= $id ?>">Edit</a>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/modules/accounts/ledger.php?id=<?= (int) $c['account_id'] ?>">Account ledger</a>
        <a class="btn btn-primary btn-sm" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><i class="bi bi-receipt me-1"></i>New invoice</a>
        <a class="btn btn-outline-success btn-sm" href="<?= e(BASE_URL) ?>/modules/maintenance/add.php?customer_id=<?= $id ?>"><i class="bi bi-calendar-check me-1"></i>Maintenance</a>
        <a class="btn btn-outline-warning btn-sm" href="<?= e(BASE_URL) ?>/modules/warranties/add.php?customer_id=<?= $id ?>"><i class="bi bi-shield-check me-1"></i>Warranty</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card vk-card h-100">
            <div class="card-body">
                <div class="small text-muted">Account</div>
                <div class="fw-semibold"><code><?= e($c['account_code']) ?></code></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card vk-card h-100">
            <div class="card-body">
                <div class="small text-muted">Running balance</div>
                <div class="fs-5 fw-bold"><?= e(number_format((float) $c['current_balance'], 2)) ?></div>
                <div class="small text-muted">+ = amount due on account</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card vk-card h-100">
            <div class="card-body">
                <div class="small text-muted">Open invoice due</div>
                <div class="fs-5 fw-bold text-danger"><?= e(number_format($totalInvoiceDue, 2)) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card vk-card h-100">
            <div class="card-body small">
                <div class="text-muted mb-1">Address</div>
                <div><?= $c['address'] ? nl2br(e($c['address'])) : '—' ?></div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3 flex-wrap" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-rep" type="button">Repairs</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-cctv" type="button">CCTV</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-maint" type="button">Maintenance</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-warr" type="button">Warranties</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-inv" type="button">Invoices</button></li>
</ul>
<div class="tab-content">
    <div class="tab-pane fade show active" id="tab-rep">
        <div class="card vk-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-sm">
                    <thead class="table-light"><tr><th>Job</th><th>Device</th><th>Status</th><th>Est.</th><th>Invoice</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($repairRows as $r): ?>
                        <tr>
                            <td><code><?= e($r['job_number']) ?></code></td>
                            <td><?= e(repair_device_type_label((string) $r['device_type'])) ?></td>
                            <td><span class="badge text-bg-<?= e(repair_status_badge_class((string) $r['status'])) ?>"><?= e(str_replace('_', ' ', $r['status'])) ?></span></td>
                            <td><?= e(number_format((float) $r['estimated_cost'], 2)) ?></td>
                            <td><?= !empty($r['invoice_id']) ? 'Yes' : '—' ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/repairs/view.php?id=<?= (int) $r['id'] ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$repairRows): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No repair jobs.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-cctv">
        <div class="card vk-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-sm">
                    <thead class="table-light"><tr><th>Job</th><th>Location</th><th>Status</th><th>Charge</th><th>Invoice</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($cctvRows as $r): ?>
                        <tr>
                            <td><code><?= e($r['job_number']) ?></code></td>
                            <td class="small"><?= e(strlen((string) $r['location']) > 50 ? substr((string) $r['location'], 0, 47) . '…' : (string) $r['location']) ?></td>
                            <td><?= e(str_replace('_', ' ', $r['status'])) ?></td>
                            <td><?= e(number_format((float) $r['installation_charge'], 2)) ?></td>
                            <td><?= !empty($r['invoice_id']) ? 'Yes' : '—' ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/cctv/view.php?id=<?= (int) $r['id'] ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$cctvRows): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No CCTV jobs.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-maint">
        <div class="card vk-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-sm">
                    <thead class="table-light"><tr><th>Contract</th><th>Title</th><th>Type</th><th>Next</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($maintRows as $r): ?>
                        <tr>
                            <td><code><?= e($r['contract_number']) ?></code></td>
                            <td><?= e($r['title']) ?></td>
                            <td><?= e(str_replace('_', ' ', $r['contract_type'])) ?></td>
                            <td><?= !empty($r['next_service_date']) ? e($r['next_service_date']) : '—' ?></td>
                            <td><span class="badge text-bg-secondary"><?= e($r['status']) ?></span></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/maintenance/edit.php?id=<?= (int) $r['id'] ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$maintRows): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No maintenance contracts.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-warr">
        <div class="card vk-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-sm">
                    <thead class="table-light"><tr><th>Title</th><th>Type</th><th>End</th><th>Links</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($warrRows as $r): ?>
                        <?php $wb = warranty_expiry_badge_class($r['end_date'] ?? null); ?>
                        <tr class="<?= $wb === 'warning' ? 'table-warning' : ($wb === 'dark' ? 'table-secondary' : '') ?>">
                            <td><?= e($r['title']) ?></td>
                            <td><?= e($r['warranty_type']) ?></td>
                            <td><span class="badge text-bg-<?= e($wb) ?>"><?= e($r['end_date']) ?></span></td>
                            <td class="small">
                                <?php if (!empty($r['repair_job_id'])): ?>
                                    <a href="<?= e(BASE_URL) ?>/modules/repairs/view.php?id=<?= (int) $r['repair_job_id'] ?>">Repair</a>
                                <?php endif; ?>
                                <?php if (!empty($r['cctv_installation_id'])): ?>
                                    <?= !empty($r['repair_job_id']) ? ' · ' : '' ?>
                                    <a href="<?= e(BASE_URL) ?>/modules/cctv/view.php?id=<?= (int) $r['cctv_installation_id'] ?>">CCTV</a>
                                <?php endif; ?>
                                <?= empty($r['repair_job_id']) && empty($r['cctv_installation_id']) ? '—' : '' ?>
                            </td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/warranties/edit.php?id=<?= (int) $r['id'] ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$warrRows): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">No warranty records.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="tab-pane fade" id="tab-inv">
        <div class="card vk-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 table-sm">
                    <thead class="table-light"><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Paid</th><th>Due</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($invRows as $r): ?>
                        <?php $d = (float) $r['grand_total'] - (float) $r['paid_amount']; ?>
                        <tr>
                            <td><?= e($r['invoice_number']) ?></td>
                            <td><?= e($r['invoice_date']) ?></td>
                            <td><?= e(number_format((float) $r['grand_total'], 2)) ?></td>
                            <td><?= e(number_format((float) $r['paid_amount'], 2)) ?></td>
                            <td class="<?= $d > 0.001 ? 'text-danger fw-semibold' : '' ?>"><?= e(number_format($d, 2)) ?></td>
                            <td><?= e($r['status']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= (int) $r['id'] ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$invRows): ?>
                        <tr><td colspan="7" class="text-center text-muted py-3">No invoices.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
