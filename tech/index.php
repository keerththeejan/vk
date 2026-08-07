<?php
declare(strict_types=1);
require_once __DIR__ . '/tech_init.php';

$pageTitle = 'My jobs';
$tid = $techStaffId;

$repairs = $pdo->prepare(
    "SELECT id, job_number, status, field_status, emergency_priority, problem_description, created_at, latitude, longitude
     FROM repair_jobs
     WHERE technician_id = ? AND status NOT IN ('delivered')
     ORDER BY emergency_priority DESC, id DESC"
);
$repairs->execute([$tid]);
$repairRows = $repairs->fetchAll();

$bookings = [];
if (db_table_exists($pdo, 'web_bookings')
    && db_column_exists($pdo, 'web_bookings', 'assigned_technician_id')
    && db_column_exists($pdo, 'web_bookings', 'repair_job_id')) {
    $wbEmerg = db_column_exists($pdo, 'web_bookings', 'is_emergency');
    $selEmerg = $wbEmerg ? ', is_emergency' : '';
    $orderBook = $wbEmerg ? 'is_emergency DESC, id DESC' : 'id DESC';
    $bs = $pdo->prepare(
        "SELECT id, booking_number, service_type, problem_description{$selEmerg}, status, created_at
         FROM web_bookings
         WHERE assigned_technician_id = ? AND repair_job_id IS NULL AND status IN ('pending','in_progress')
         ORDER BY {$orderBook}"
    );
    $bs->execute([$tid]);
    $bookings = $bs->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?= e($pageTitle) ?> — Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: system-ui, sans-serif; padding-bottom: 4rem; background: #f0f2f5; }
        .vk-tech-head { background: linear-gradient(90deg,#0a2a5c,#134a9e); color: #fff; }
        .job-card { border-radius: 12px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .emerg { border-left: 4px solid #dc3545 !important; }
    </style>
</head>
<body>
<header class="vk-tech-head sticky-top py-3 px-3 shadow-sm">
    <div class="d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="bi bi-wrench-adjustable me-1"></i> Field jobs</span>
        <a class="btn btn-sm btn-outline-light" href="<?= e(BASE_URL) ?>/logout.php">Logout</a>
    </div>
</header>

<div class="container-fluid px-3 py-3">
    <?php $f = flash_get(); ?>
    <?php if ($f): ?>
        <div class="alert alert-<?= e($f['type'] === 'error' ? 'danger' : ($f['type'] === 'success' ? 'success' : 'info')) ?> py-2 small"><?= e($f['message']) ?></div>
    <?php endif; ?>

    <?php if ($bookings): ?>
        <h2 class="h6 text-muted text-uppercase mb-2">Web bookings (awaiting job card)</h2>
        <?php foreach ($bookings as $b): ?>
            <?php $bkEmerg = !empty($b['is_emergency']); ?>
            <div class="card job-card mb-2 p-3 <?= $bkEmerg ? 'emerg' : '' ?>">
                <div class="fw-semibold"><?= e($b['booking_number']) ?><?= $bkEmerg ? ' <span class="badge bg-danger">911</span>' : '' ?></div>
                <div class="small text-capitalize text-muted"><?= e($b['service_type']) ?></div>
                <div class="small mt-1"><?php $pd = (string) $b['problem_description']; echo e(strlen($pd) > 120 ? substr($pd, 0, 117) . '…' : $pd); ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2 class="h6 text-muted text-uppercase mb-2 mt-3">Assigned repairs</h2>
    <?php if (!$repairRows): ?>
        <p class="text-muted small">No active repair jobs assigned to you.</p>
    <?php else: ?>
        <?php foreach ($repairRows as $r): ?>
            <a href="<?= e(BASE_URL) ?>/tech/job.php?id=<?= (int) $r['id'] ?>" class="text-decoration-none text-dark">
                <div class="card job-card mb-3 p-3 <?= (int) $r['emergency_priority'] ? 'emerg' : '' ?>">
                    <div class="d-flex justify-content-between">
                        <code class="text-primary"><?= e($r['job_number']) ?></code>
                        <?php if ((int) $r['emergency_priority']): ?><span class="badge bg-danger">Priority</span><?php endif; ?>
                    </div>
                    <div class="small mt-2"><?php $pd = (string) $r['problem_description']; echo e(strlen($pd) > 100 ? substr($pd, 0, 97) . '…' : $pd); ?></div>
                    <div class="mt-2 small">
                        <span class="badge bg-secondary"><?= e(str_replace('_', ' ', $r['status'])) ?></span>
                        <?php if (!empty($r['field_status'])): ?>
                            <span class="badge bg-info text-dark"><?= e(str_replace('_', ' ', $r['field_status'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
