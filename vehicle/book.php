<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/init.php';
vk_bootstrap_module('vehicle_booking');
$pdo = db();
vk_vehicle_auto_migrate($pdo);
$customer = vk_vehicle_require_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookingType = (string) ($_POST['booking_type'] ?? 'rental');
    $pickup = trim((string) ($_POST['pickup_location'] ?? ''));
    $drop = trim((string) ($_POST['drop_location'] ?? ''));
    $pickupAt = trim((string) ($_POST['pickup_at'] ?? ''));
    $returnAt = trim((string) ($_POST['return_at'] ?? ''));
    $vehicleType = (string) ($_POST['vehicle_type'] ?? 'car');
    $passengers = max(1, (int) ($_POST['passengers'] ?? 1));
    $notes = trim((string) ($_POST['special_notes'] ?? ''));
    $distanceKm = max(0, (float) ($_POST['distance_km'] ?? 0));
    $vehicleId = max(0, (int) ($_POST['vehicle_id'] ?? 0));
    $pickupLat = ($_POST['pickup_lat'] ?? '') !== '' ? (float) $_POST['pickup_lat'] : null;
    $pickupLng = ($_POST['pickup_lng'] ?? '') !== '' ? (float) $_POST['pickup_lng'] : null;
    $dropLat = ($_POST['drop_lat'] ?? '') !== '' ? (float) $_POST['drop_lat'] : null;
    $dropLng = ($_POST['drop_lng'] ?? '') !== '' ? (float) $_POST['drop_lng'] : null;

    if ($bookingType !== 'hire') {
        $bookingType = 'rental';
    }
    if (!in_array($vehicleType, ['car', 'van', 'bike', 'lorry', 'bus'], true)) {
        $vehicleType = 'car';
    }
    if ($pickup === '' || $pickupAt === '') {
        flash_set('error', 'Pickup location and pickup date/time are required.');
        redirect('/vehicle/book.php');
    }
    if ($bookingType === 'hire' && $drop === '') {
        flash_set('error', 'Drop location is required for hire bookings.');
        redirect('/vehicle/book.php');
    }

    $vehicle = null;
    if ($vehicleId > 0) {
        $stVeh = $pdo->prepare('SELECT * FROM vehicles WHERE id = ? LIMIT 1');
        $stVeh->execute([$vehicleId]);
        $vehicle = $stVeh->fetch() ?: null;
    }
    if (!$vehicle) {
        $stVeh = $pdo->prepare("SELECT * FROM vehicles WHERE vehicle_type = ? AND status = 'available' ORDER BY id ASC LIMIT 1");
        $stVeh->execute([$vehicleType]);
        $vehicle = $stVeh->fetch() ?: null;
    }
    if (!$vehicle) {
        flash_set('error', 'No available vehicle found for this type.');
        redirect('/vehicle/book.php');
    }

    $unitPrice = 0.0;
    $driverCharge = 0.0;
    $days = vk_vehicle_days($pickupAt, $returnAt !== '' ? $returnAt : null);
    $driverId = null;
    if ($bookingType === 'rental') {
        $unitPrice = (float) ($vehicle['price_per_day'] ?? 0);
        $total = $unitPrice * $days;
    } else {
        $unitPrice = (float) ($vehicle['price_per_km'] ?? 0);
        $driverCharge = (float) ($vehicle['default_driver_charge'] ?? 0);
        $distanceKm = max($distanceKm, 1.0);
        $total = ($distanceKm * $unitPrice) + $driverCharge;
        $driverId = isset($vehicle['assigned_driver_id']) && (int) $vehicle['assigned_driver_id'] > 0 ? (int) $vehicle['assigned_driver_id'] : null;
    }

    $ref = vk_vehicle_booking_ref();
    $pdo->prepare(
        'INSERT INTO vehicle_bookings
        (booking_ref, customer_id, booking_type, vehicle_id, driver_id, status, pickup_location, pickup_lat, pickup_lng, drop_location, drop_lat, drop_lng, pickup_at, return_at, vehicle_type, passengers, distance_km, rental_days, unit_price, driver_charge, total_amount, special_notes)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $ref,
        (int) $customer['id'],
        $bookingType,
        (int) $vehicle['id'],
        $driverId,
        'pending',
        $pickup,
        $pickupLat,
        $pickupLng,
        ($drop !== '' ? $drop : null),
        $dropLat,
        $dropLng,
        $pickupAt,
        ($returnAt !== '' ? $returnAt : null),
        $vehicleType,
        $passengers,
        $distanceKm,
        $days,
        $unitPrice,
        $driverCharge,
        $total,
        ($notes !== '' ? $notes : null),
    ]);
    flash_set('success', 'Booking submitted successfully. Reference: ' . $ref);
    redirect('/vehicle/dashboard.php');
}

$prefVehicleId = max(0, (int) ($_GET['vehicle_id'] ?? 0));
$vehicles = $pdo->query("SELECT * FROM vehicles WHERE status = 'available' ORDER BY vehicle_type, vehicle_name")->fetchAll();
$pageTitle = 'Book Vehicle';
$navActive = 'vehicle';
$seoCanonicalPath = BASE_URL . '/vehicle/book.php';
$extraHead = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous"><link rel="stylesheet" href="' . e(BASE_URL) . '/assets/css/vehicle-booking.css">';
$extraScripts = '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script><script src="' . e(BASE_URL) . '/assets/js/vehicle-booking.js" defer></script>';
require dirname(__DIR__) . '/includes/public_header.php';
?>
<section class="py-5">
    <div class="container">
        <div class="mb-4">
            <h1 class="vk-section-title mb-2">Vehicle booking wizard</h1>
            <p class="vk-section-lead mb-0">Rental (self-drive) and hire (with driver) with map route and estimated fare.</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-5">
                <form class="vk-pub-service-card p-4" method="post" data-vk-vehicle-form>
                    <div class="vk-step-badge mb-3">Step 1 · Booking type</div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="booking_type" data-vk-booking-type>
                                <option value="rental">Rental (self-drive)</option>
                                <option value="hire">Hire (with driver)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Vehicle type</label>
                            <select class="form-select" name="vehicle_type" data-vk-vehicle-type>
                                <option value="car">Car</option>
                                <option value="van">Van</option>
                                <option value="bike">Bike</option>
                                <option value="lorry">Lorry</option>
                                <option value="bus">Bus</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Preferred vehicle</label>
                        <select class="form-select" name="vehicle_id" data-vk-vehicle-select>
                            <option value="0">Auto assign best available</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= (int) $v['id'] ?>" data-type="<?= e((string) $v['vehicle_type']) ?>" data-day="<?= e((string) $v['price_per_day']) ?>" data-km="<?= e((string) $v['price_per_km']) ?>" data-driver-charge="<?= e((string) $v['default_driver_charge']) ?>" <?= ((int) $v['id'] === $prefVehicleId) ? 'selected' : '' ?>>
                                    <?= e((string) $v['vehicle_name']) ?> (<?= e((string) $v['registration_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="vk-step-badge mb-3">Step 2 · Locations</div>
                    <div class="mb-3 position-relative">
                        <label class="form-label">Pickup location</label>
                        <input class="form-control" name="pickup_location" autocomplete="off" required data-vk-pickup-input>
                        <div class="vk-location-list d-none" data-vk-pickup-list></div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label class="form-label">Drop location <span class="text-muted">(optional for rental)</span></label>
                        <input class="form-control" name="drop_location" autocomplete="off" data-vk-drop-input>
                        <div class="vk-location-list d-none" data-vk-drop-list></div>
                    </div>

                    <div class="vk-step-badge mb-3">Step 3 · Schedule & details</div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label class="form-label">Pickup date/time</label><input type="datetime-local" class="form-control" name="pickup_at" required></div>
                        <div class="col-md-6"><label class="form-label">Return date/time</label><input type="datetime-local" class="form-control" name="return_at"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label class="form-label">Passengers</label><input type="number" min="1" max="60" class="form-control" name="passengers" value="1"></div>
                        <div class="col-md-6"><label class="form-label">Distance (km)</label><input class="form-control" name="distance_km" value="0" readonly data-vk-distance></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Special notes</label><textarea class="form-control" rows="3" name="special_notes" placeholder="Any extra instructions..."></textarea></div>

                    <input type="hidden" name="pickup_lat" data-vk-pickup-lat>
                    <input type="hidden" name="pickup_lng" data-vk-pickup-lng>
                    <input type="hidden" name="drop_lat" data-vk-drop-lat>
                    <input type="hidden" name="drop_lng" data-vk-drop-lng>

                    <div class="vk-price-box mb-3">
                        <div class="small text-muted">Estimated fare</div>
                        <div class="h4 mb-0" data-vk-total>Rs. 0.00</div>
                    </div>
                    <button class="btn btn-primary w-100 btn-lg" type="submit">Confirm booking</button>
                </form>
            </div>
            <div class="col-lg-7">
                <div class="vk-pub-service-card p-3 h-100">
                    <div class="small text-muted mb-2">Live route map (Leaflet + OpenStreetMap + OSRM)</div>
                    <div id="vkBookingMap" class="vk-booking-map"></div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/public_footer.php'; ?>
