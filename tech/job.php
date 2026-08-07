<?php
declare(strict_types=1);
require_once __DIR__ . '/tech_init.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM repair_jobs WHERE id = ? AND technician_id = ?');
$st->execute([$id, $techStaffId]);
$job = $st->fetch();
if (!$job) {
    flash_set('error', 'Job not found or not assigned to you.');
    redirect('/tech/index.php');
}

$fieldAllowed = ['assigned', 'on_way', 'in_progress', 'completed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'update') {
        $fs = (string) ($_POST['field_status'] ?? '');
        if (!in_array($fs, $fieldAllowed, true)) {
            $fs = 'assigned';
        }
        $notes = trim((string) ($_POST['technician_notes'] ?? ''));
        $pdo->prepare('UPDATE repair_jobs SET field_status=?, technician_notes=? WHERE id=? AND technician_id=?')
            ->execute([$fs, $notes !== '' ? $notes : null, $id, $techStaffId]);
        flash_set('success', 'Updated.');
        redirect('/tech/job.php?id=' . $id);
    }
    if ($action === 'upload' && db_table_exists($pdo, 'job_attachments')) {
        if (!empty($_FILES['photo']['name']) && (int) $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['photo'];
            $kind = in_array(($_POST['kind'] ?? 'other'), ['before', 'after', 'other'], true) ? $_POST['kind'] : 'other';
            $maxBytes = 3 * 1024 * 1024;
            if ((int) $f['size'] <= $maxBytes) {
                $info = @getimagesize($f['tmp_name']);
                if ($info !== false && in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    $ext = match ($info[2]) {
                        IMAGETYPE_JPEG => 'jpg',
                        IMAGETYPE_PNG => 'png',
                        IMAGETYPE_WEBP => 'webp',
                        default => 'bin',
                    };
                    $dir = dirname(__DIR__) . '/uploads/jobs';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $fn = 'j' . $id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    $dest = $dir . '/' . $fn;
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        $uid = (int) $_SESSION['user_id'];
                        $pdo->prepare(
                            'INSERT INTO job_attachments (repair_job_id, file_path, kind, uploaded_by_user_id) VALUES (?,?,?,?)'
                        )->execute([$id, 'uploads/jobs/' . $fn, $kind, $uid]);
                        flash_set('success', 'Photo uploaded.');
                    }
                }
            }
        }
        redirect('/tech/job.php?id=' . $id);
    }
    $st->execute([$id, $techStaffId]);
    $job = $st->fetch();
}

$attachments = [];
if (db_table_exists($pdo, 'job_attachments')) {
    $at = $pdo->prepare('SELECT * FROM job_attachments WHERE repair_job_id = ? ORDER BY id DESC');
    $at->execute([$id]);
    $attachments = $at->fetchAll();
}

$pageTitle = $job['job_number'];
$mapsKey = defined('GOOGLE_MAPS_API_KEY') ? (string) GOOGLE_MAPS_API_KEY : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>body { font-family: system-ui, sans-serif; background: #f0f2f5; padding-bottom: 3rem; }</style>
    <?php if ($mapsKey && $job['latitude'] && $job['longitude']): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?= e($mapsKey) ?>" async defer></script>
    <?php endif; ?>
</head>
<body class="p-3">
    <a href="<?= e(BASE_URL) ?>/tech/index.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-left"></i> Back</a>

    <?php $f = flash_get(); ?>
    <?php if ($f): ?>
        <div class="alert alert-<?= e($f['type'] === 'error' ? 'danger' : 'success') ?> py-2 small"><?= e($f['message']) ?></div>
    <?php endif; ?>

    <h1 class="h5 fw-bold"><?= e($job['job_number']) ?></h1>
    <p class="small text-muted"><?= nl2br(e((string) $job['problem_description'])) ?></p>

    <?php if ($job['latitude'] && $job['longitude']): ?>
        <div id="tmap" class="rounded border mb-3" style="height:180px;"></div>
        <a class="btn btn-sm btn-primary mb-3 w-100" target="_blank" href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode((string) $job['latitude'] . ',' . (string) $job['longitude']) ?>">Navigate</a>
        <?php if ($mapsKey): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
          if (!window.google || !document.getElementById('tmap')) return;
          var p = { lat: <?= json_encode((float) $job['latitude']) ?>, lng: <?= json_encode((float) $job['longitude']) ?> };
          var map = new google.maps.Map(document.getElementById('tmap'), { zoom: 15, center: p });
          new google.maps.Marker({ position: p, map: map });
        });
        </script>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" class="card p-3 mb-3 shadow-sm border-0">
        <input type="hidden" name="action" value="update">
        <label class="form-label small fw-semibold">Field status</label>
        <select class="form-select mb-2" name="field_status">
            <?php foreach ($fieldAllowed as $x): ?>
                <option value="<?= e($x) ?>" <?= ($job['field_status'] ?? '') === $x ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $x)) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="form-label small fw-semibold">Notes</label>
        <textarea class="form-control mb-2" name="technician_notes" rows="3"><?= e($job['technician_notes'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-primary w-100">Save</button>
    </form>

    <?php if (db_table_exists($pdo, 'job_attachments')): ?>
    <div class="card p-3 shadow-sm border-0 mb-3">
        <div class="fw-semibold small mb-2">Upload photo</div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <select class="form-select form-select-sm mb-2" name="kind">
                <option value="before">Before</option>
                <option value="after">After</option>
                <option value="other">Other</option>
            </select>
            <input type="file" class="form-control form-control-sm mb-2" name="photo" accept="image/jpeg,image/png,image/webp" required>
            <button type="submit" class="btn btn-outline-primary btn-sm w-100">Upload</button>
        </form>
    </div>
    <?php if ($attachments): ?>
        <div class="row g-2">
            <?php foreach ($attachments as $a): ?>
                <div class="col-6">
                    <a href="<?= e(BASE_URL) ?>/<?= e($a['file_path']) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(BASE_URL) ?>/<?= e($a['file_path']) ?>" class="img-fluid rounded border" alt="">
                    </a>
                    <div class="small text-muted"><?= e($a['kind']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
