<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT r.*, c.name AS customer_name, c.phone, c.email, c.address, t.name AS technician_name
     FROM repair_jobs r
     JOIN customers c ON c.id = r.customer_id
     LEFT JOIN technicians t ON t.id = r.technician_id
     WHERE r.id = ?'
);
$st->execute([$id]);
$job = $st->fetch();
if (!$job) {
    http_response_code(404);
    echo 'Job not found.';
    exit;
}

$parts = $pdo->prepare(
    'SELECT jp.*, p.name AS product_name FROM repair_job_parts jp JOIN products p ON p.id = jp.product_id WHERE jp.repair_job_id = ?'
);
$parts->execute([$id]);
$partRows = $parts->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Job <?= e($job['job_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Inter, system-ui, sans-serif; font-size: 11pt; }
        .vk-head { border-bottom: 3px solid #0a2a5c; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="p-3 p-md-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-start vk-head pb-2 mb-3">
        <div>
            <div class="fw-bold text-danger fs-5">VK IT Network</div>
            <div class="small text-muted text-uppercase" style="letter-spacing:.1em;">Service job card</div>
        </div>
        <button type="button" class="btn btn-sm btn-primary no-print" onclick="window.print()">Print</button>
    </div>
    <div class="row mb-3">
        <div class="col-6">
            <div class="small text-muted">Job number</div>
            <div class="fw-bold"><?= e($job['job_number']) ?></div>
        </div>
        <div class="col-6 text-end">
            <div class="small text-muted">Received</div>
            <div><?= e(substr((string) $job['created_at'], 0, 10)) ?></div>
        </div>
    </div>
    <div class="mb-3">
        <div class="fw-bold text-uppercase small text-muted">Customer</div>
        <div class="fw-semibold"><?= e($job['customer_name']) ?></div>
        <?php if ($job['phone']): ?><div><?= e($job['phone']) ?></div><?php endif; ?>
        <?php if ($job['address']): ?><div class="small"><?= nl2br(e($job['address'])) ?></div><?php endif; ?>
    </div>
    <table class="table table-sm table-bordered">
        <tr><th class="w-25">Device</th><td><?= e(repair_device_type_label((string) $job['device_type'])) ?></td></tr>
        <tr><th>Technician</th><td><?= e($job['technician_name'] ?? '—') ?></td></tr>
        <tr><th>Status</th><td><?= e(str_replace('_', ' ', $job['status'])) ?></td></tr>
        <tr><th>Problem</th><td><?= nl2br(e($job['problem_description'] ?? '')) ?></td></tr>
        <tr><th>Accessories</th><td><?= nl2br(e($job['accessories_received'] ?? '—')) ?></td></tr>
        <tr><th>Estimated</th><td><?= e(number_format((float) $job['estimated_cost'], 2)) ?></td></tr>
        <tr><th>Warranty</th><td><?= !empty($job['warranty_expiry']) ? e($job['warranty_expiry']) : '—' ?></td></tr>
    </table>
    <?php if ($partRows): ?>
        <h6 class="mt-3">Parts</h6>
        <table class="table table-sm">
            <thead><tr><th>Part</th><th class="text-end">Qty</th><th class="text-end">Line</th></tr></thead>
            <tbody>
            <?php foreach ($partRows as $p): ?>
                <tr>
                    <td><?= e($p['product_name']) ?></td>
                    <td class="text-end"><?= (int) $p['quantity'] ?></td>
                    <td class="text-end"><?= e(number_format((float) $p['line_total'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p class="small text-muted border-top pt-3 mt-4 mb-0">26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka · 0778870135 · www.vkitnet.info</p>
</div>
</body>
</html>
