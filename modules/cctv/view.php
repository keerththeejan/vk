<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT v.*, c.name AS customer_name, c.phone, c.email, c.address
     FROM cctv_installations v
     JOIN customers c ON c.id = v.customer_id
     WHERE v.id = ?'
);
$st->execute([$id]);
$job = $st->fetch();
if (!$job) {
    flash_set('error', 'Job not found.');
    redirect('/modules/cctv/list.php');
}

$pageTitle = 'CCTV installation';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$adv = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE cctv_job_id = ?');
$adv->execute([$id]);
$advances = (float) $adv->fetchColumn();

$payList = $pdo->prepare('SELECT * FROM payments WHERE cctv_job_id = ? ORDER BY paid_at DESC');
$payList->execute([$id]);
$payments = $payList->fetchAll();

$inv = null;
if (!empty($job['invoice_id'])) {
    $ist = $pdo->prepare('SELECT id, invoice_number, grand_total, paid_amount, status FROM invoices WHERE id = ?');
    $ist->execute([(int) $job['invoice_id']]);
    $inv = $ist->fetch();
}

$waPhone = preg_replace('/\D+/', '', (string) ($job['phone'] ?? ''));
$waLink = $waPhone !== '' ? 'https://wa.me/' . $waPhone : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <a href="<?= e(BASE_URL) ?>/modules/cctv/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Installations</a>
        <h1 class="h3 mt-2 mb-0"><code><?= e($job['job_number']) ?></code></h1>
        <p class="text-muted mb-0"><?= e($job['customer_name']) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" target="_blank" href="<?= e(BASE_URL) ?>/modules/cctv/print.php?id=<?= $id ?>"><i class="bi bi-printer me-1"></i>Print</a>
        <a class="btn btn-outline-warning" href="<?= e(BASE_URL) ?>/modules/warranties/add.php?cctv_installation_id=<?= $id ?>&customer_id=<?= (int) $job['customer_id'] ?>"><i class="bi bi-shield-check me-1"></i>Warranty</a>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/cctv/edit.php?id=<?= $id ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php if (empty($job['invoice_id'])): ?>
            <form method="post" action="<?= e(BASE_URL) ?>/modules/cctv/generate_invoice.php" class="d-inline" onsubmit="return confirm('Create invoice for installation charge <?= e(number_format((float) $job['installation_charge'], 2)) ?>?');">
                <input type="hidden" name="cctv_job_id" value="<?= $id ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-receipt me-1"></i>Generate invoice</button>
            </form>
            <a class="btn btn-success" href="<?= e(BASE_URL) ?>/modules/payments/job_payment.php?kind=cctv&job_id=<?= $id ?>"><i class="bi bi-cash me-1"></i>Advance / pay</a>
        <?php endif; ?>
        <?php if ($waLink): ?>
            <a class="btn btn-outline-success" target="_blank" rel="noopener" href="<?= e($waLink) ?>"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Installation details</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Status</dt>
                    <dd class="col-sm-9"><span class="badge text-bg-secondary"><?= e(str_replace('_', ' ', $job['status'])) ?></span></dd>
                    <dt class="col-sm-3">Location</dt>
                    <dd class="col-sm-9"><?= nl2br(e($job['location'])) ?></dd>
                    <dt class="col-sm-3">Cameras</dt>
                    <dd class="col-sm-9"><?= (int) $job['num_cameras'] ?></dd>
                    <dt class="col-sm-3">Cable</dt>
                    <dd class="col-sm-9"><?= e(number_format((float) $job['cable_length_m'], 2)) ?> m</dd>
                    <dt class="col-sm-3">DVR / NVR</dt>
                    <dd class="col-sm-9"><?= nl2br(e($job['dvr_nvr_details'] ?? '—')) ?></dd>
                    <dt class="col-sm-3">Charge</dt>
                    <dd class="col-sm-9 fw-semibold"><?= e(number_format((float) $job['installation_charge'], 2)) ?></dd>
                    <dt class="col-sm-3">Equipment</dt>
                    <dd class="col-sm-9"><?= nl2br(e($job['equipment_used'] ?? '—')) ?></dd>
                    <dt class="col-sm-3">Technician notes</dt>
                    <dd class="col-sm-9"><?= nl2br(e($job['technician_notes'] ?? '—')) ?></dd>
                    <dt class="col-sm-3">Warranty</dt>
                    <dd class="col-sm-9"><?= !empty($job['warranty_expiry']) ? e($job['warranty_expiry']) : '—' ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card vk-card mb-3">
            <div class="card-header bg-transparent fw-semibold">Billing</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between"><span>Advances / payments</span><strong><?= e(number_format($advances, 2)) ?></strong></div>
                <?php if ($inv): ?>
                    <hr>
                    <div class="fw-semibold">Invoice <?= e($inv['invoice_number']) ?></div>
                    <div>Total <?= e(number_format((float) $inv['grand_total'], 2)) ?> · Paid <?= e(number_format((float) $inv['paid_amount'], 2)) ?></div>
                    <a class="btn btn-sm btn-outline-primary mt-2" href="<?= e(BASE_URL) ?>/modules/invoices/view.php?id=<?= (int) $inv['id'] ?>">Open invoice</a>
                <?php else: ?>
                    <p class="text-muted mb-0 mt-2">No invoice yet — use “Generate invoice” to bill the installation charge.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="card vk-card">
            <div class="card-header bg-transparent fw-semibold">Payment log</div>
            <div class="card-body p-0">
                <?php if (!$payments): ?>
                    <p class="text-muted small p-3 mb-0">No payments on this job.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($payments as $p): ?>
                            <li class="list-group-item">
                                <div class="fw-semibold"><?= e(number_format((float) $p['amount'], 2)) ?> · <?= e($p['method']) ?></div>
                                <div class="text-muted"><?= e($p['paid_at']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
