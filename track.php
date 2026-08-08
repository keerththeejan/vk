<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init_public.php';
$pdo = db();

$pageTitle = 'Track job';
$navActive = 'track';
$seoCanonicalPath = BASE_URL . '/track.php';
$seoDescription = 'Track your VK Network service booking status with your booking ID.';
$booking = null;
$lookup = trim((string) ($_GET['id'] ?? $_POST['booking_id'] ?? ''));

if ($lookup !== '' && db_table_exists($pdo, 'web_bookings')) {
    $st = $pdo->prepare(
        'SELECT booking_number, status, technician_notes, estimated_cost, problem_description, service_type, created_at
         FROM web_bookings WHERE booking_number = ? LIMIT 1'
    );
    $st->execute([$lookup]);
    $booking = $st->fetch() ?: null;
}

require __DIR__ . '/includes/public_header.php';
?>
<div class="vk-pub-page py-5">
    <div class="container" style="max-width: 640px;">
        <div data-aos="fade-up" data-aos-duration="650">
        <h1 class="h3 mb-2">Track your booking</h1>
        <p class="text-muted small mb-4">Enter the booking ID we gave you (for example <code>BK-000001</code>).</p>
        </div>

        <form method="get" action="" class="card shadow-sm border-0 mb-4" data-aos="fade-up" data-aos-duration="600" data-aos-delay="60">
            <div class="card-body p-4">
                <label class="form-label" for="id">Booking ID</label>
                <div class="input-group">
                    <input class="form-control form-control-lg text-uppercase" id="id" name="id" value="<?= e($lookup) ?>" placeholder="BK-000001" maxlength="32" autocomplete="off">
                    <button type="submit" class="btn btn-primary px-4">Look up</button>
                </div>
            </div>
        </form>

        <?php if ($lookup !== '' && !db_table_exists($pdo, 'web_bookings')): ?>
            <div class="alert alert-warning" data-aos="fade-up">Tracking is unavailable until the database is upgraded.</div>
        <?php elseif ($lookup !== '' && !$booking): ?>
            <div class="alert alert-secondary" data-aos="fade-up">No booking found for <code><?= e($lookup) ?></code>. Check the ID and try again.</div>
        <?php elseif ($booking): ?>
            <div class="card vk-track-result shadow-sm" data-aos="fade-up" data-aos-duration="600">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                        <div>
                            <div class="text-muted small">Booking ID</div>
                            <code class="fs-5"><?= e($booking['booking_number']) ?></code>
                        </div>
                        <span class="badge text-bg-primary fs-6"><?= e(booking_public_status_label((string) $booking['status'])) ?></span>
                    </div>
                    <dl class="row small mb-0">
                        <dt class="col-sm-4">Service</dt>
                        <dd class="col-sm-8 text-capitalize"><?= e($booking['service_type']) ?></dd>
                        <dt class="col-sm-4">Submitted</dt>
                        <dd class="col-sm-8"><?= e(substr((string) $booking['created_at'], 0, 16)) ?></dd>
                        <dt class="col-sm-4">Your request</dt>
                        <dd class="col-sm-8"><?= nl2br(e($booking['problem_description'])) ?></dd>
                        <dt class="col-sm-4">Technician notes</dt>
                        <dd class="col-sm-8"><?= $booking['technician_notes'] ? nl2br(e($booking['technician_notes'])) : '<span class="text-muted">—</span>' ?></dd>
                        <dt class="col-sm-4">Estimated cost</dt>
                        <dd class="col-sm-8 fw-semibold"><?= e(formatCurrency($booking['estimated_cost'])) ?></dd>
                    </dl>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
