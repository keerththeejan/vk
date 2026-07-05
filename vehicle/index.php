<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/init.php';
vk_bootstrap_module('vehicle_booking');
$pdo = db();
vk_vehicle_auto_migrate($pdo);

$pageTitle = 'Vehicle Booking';
$navActive = 'vehicle';
$seoDescription = 'VK Vehicle Booking System - rental and hire with live map route, fare estimate, and fast online confirmation.';
$seoCanonicalPath = BASE_URL . '/vehicle/index.php';

$customer = vk_vehicle_customer();
$rows = $pdo->query("SELECT * FROM vehicles WHERE status = 'available' ORDER BY vehicle_type, vehicle_name")->fetchAll();
require dirname(__DIR__) . '/includes/public_header.php';
?>
<section class="vk-hero-premium">
    <div class="container vk-hero-inner py-5">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <h1 class="vk-hero-title mb-3">VK Vehicle Booking System</h1>
                <p class="vk-hero-lead mb-4">Book self-drive rentals or hire with driver. Route-based fare estimate, live map preview, and easy status tracking.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn vk-btn-hero-primary btn-lg" href="<?= e(BASE_URL) ?>/vehicle/book.php">Start booking</a>
                    <?php if ($customer): ?>
                        <a class="btn vk-btn-hero-secondary btn-lg" href="<?= e(BASE_URL) ?>/vehicle/dashboard.php">My bookings</a>
                    <?php else: ?>
                        <a class="btn vk-btn-hero-secondary btn-lg" href="<?= e(BASE_URL) ?>/vehicle/login.php">Customer login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="vk-section-title mb-0">Available vehicles</h2>
            <span class="small text-muted"><?= count($rows) ?> vehicle(s)</span>
        </div>
        <div class="row g-4">
            <?php if (!$rows): ?>
                <div class="col-12"><div class="alert alert-info">No vehicles currently available.</div></div>
            <?php else: foreach ($rows as $v): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="vk-pub-service-card p-3 h-100">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h3 class="mb-0"><?= e((string) $v['vehicle_name']) ?></h3>
                            <span class="badge text-bg-light border text-uppercase"><?= e((string) $v['vehicle_type']) ?></span>
                        </div>
                        <div class="small text-muted mb-3"><code><?= e((string) $v['registration_number']) ?></code> • Seats <?= (int) $v['seats'] ?></div>
                        <p class="mb-3 small">Rental: <strong>LKR <?= e(number_format((float) $v['price_per_day'], 0)) ?>/day</strong><br>Hire: <strong>LKR <?= e(number_format((float) $v['price_per_km'], 0)) ?>/km</strong></p>
                        <a class="btn btn-outline-primary btn-sm" href="<?= e(BASE_URL) ?>/vehicle/book.php?vehicle_id=<?= (int) $v['id'] ?>">Book this vehicle</a>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/public_footer.php'; ?>
