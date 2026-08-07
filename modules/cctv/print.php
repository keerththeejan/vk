<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/init.php';
require_admin();
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT v.*, c.name AS customer_name, c.phone, c.address
     FROM cctv_installations v
     JOIN customers c ON c.id = v.customer_id
     WHERE v.id = ?'
);
$st->execute([$id]);
$job = $st->fetch();
if (!$job) {
    http_response_code(404);
    echo 'Job not found.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CCTV <?= e($job['job_number']) ?></title>
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
            <div class="small text-muted text-uppercase" style="letter-spacing:.1em;">CCTV installation sheet</div>
        </div>
        <button type="button" class="btn btn-sm btn-primary no-print" onclick="window.print()">Print</button>
    </div>
    <div class="row mb-3">
        <div class="col-6">
            <div class="small text-muted">Job</div>
            <div class="fw-bold"><?= e($job['job_number']) ?></div>
        </div>
        <div class="col-6 text-end">
            <div class="small text-muted">Created</div>
            <div><?= e(substr((string) $job['created_at'], 0, 10)) ?></div>
        </div>
    </div>
    <div class="mb-3">
        <div class="fw-bold text-uppercase small text-muted">Customer</div>
        <div class="fw-semibold"><?= e($job['customer_name']) ?></div>
        <?php if ($job['phone']): ?><div><?= e($job['phone']) ?></div><?php endif; ?>
    </div>
    <table class="table table-sm table-bordered">
        <tr><th class="w-25">Location</th><td><?= nl2br(e($job['location'])) ?></td></tr>
        <tr><th>Cameras</th><td><?= (int) $job['num_cameras'] ?></td></tr>
        <tr><th>Cable length</th><td><?= e(number_format((float) $job['cable_length_m'], 2)) ?> m</td></tr>
        <tr><th>DVR / NVR</th><td><?= nl2br(e($job['dvr_nvr_details'] ?? '—')) ?></td></tr>
        <tr><th>Installation charge</th><td><?= e(number_format((float) $job['installation_charge'], 2)) ?></td></tr>
        <tr><th>Equipment</th><td><?= nl2br(e($job['equipment_used'] ?? '—')) ?></td></tr>
        <tr><th>Status</th><td><?= e(str_replace('_', ' ', $job['status'])) ?></td></tr>
        <tr><th>Warranty</th><td><?= !empty($job['warranty_expiry']) ? e($job['warranty_expiry']) : '—' ?></td></tr>
    </table>
    <p class="small text-muted border-top pt-3 mt-4 mb-0">26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka · 0778870135</p>
</div>
</body>
</html>
