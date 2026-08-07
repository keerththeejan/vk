<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/init.php';
vk_bootstrap_module('vehicle_booking');
$pdo = db();
vk_vehicle_auto_migrate($pdo);
$customer = vk_vehicle_require_customer();

if (isset($_GET['cancel'])) {
    $id = (int) $_GET['cancel'];
    if ($id > 0) {
        $pdo->prepare("UPDATE vehicle_bookings SET status='cancelled' WHERE id=? AND customer_id=? AND status IN ('pending','confirmed')")
            ->execute([$id, (int) $customer['id']]);
        flash_set('success', 'Booking cancelled.');
    }
    redirect('/vehicle/dashboard.php');
}

$st = $pdo->prepare(
    "SELECT b.*, v.vehicle_name, d.name AS driver_name
     FROM vehicle_bookings b
     LEFT JOIN vehicles v ON v.id = b.vehicle_id
     LEFT JOIN vehicle_drivers d ON d.id = b.driver_id
     WHERE b.customer_id = ?
     ORDER BY b.id DESC"
);
$st->execute([(int) $customer['id']]);
$rows = $st->fetchAll();

$pageTitle = 'My Vehicle Bookings';
$navActive = 'vehicle';
$seoCanonicalPath = BASE_URL . '/vehicle/dashboard.php';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<section class="py-5">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h1 class="vk-section-title mb-0">Hello, <?= e((string) $customer['full_name']) ?></h1>
            <div class="d-flex gap-2">
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/vehicle/book.php">New booking</a>
                <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/vehicle/logout.php">Logout</a>
            </div>
        </div>
        <div class="card vk-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Ref</th><th>Trip</th><th>Schedule</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No bookings yet.</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><code><?= e((string) $r['booking_ref']) ?></code></td>
                            <td class="small"><?= e((string) $r['pickup_location']) ?><br><span class="text-muted"><?= e((string) ($r['drop_location'] ?? '-')) ?></span></td>
                            <td class="small"><?= e((string) $r['pickup_at']) ?><br><span class="text-muted"><?= e((string) ($r['return_at'] ?? '-')) ?></span></td>
                            <td>LKR <?= e(number_format((float) $r['total_amount'], 0)) ?></td>
                            <td><span class="badge text-bg-<?= $r['status'] === 'completed' ? 'success' : ($r['status'] === 'cancelled' ? 'danger' : ($r['status'] === 'ongoing' ? 'warning' : 'secondary')) ?>"><?= e((string) ucfirst((string) $r['status'])) ?></span></td>
                            <td>
                                <?php if (in_array((string) $r['status'], ['pending', 'confirmed'], true)): ?>
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(BASE_URL) ?>/vehicle/dashboard.php?cancel=<?= (int) $r['id'] ?>" onclick="return confirm('Cancel this booking?')">Cancel</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/public_footer.php'; ?>
