<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/booking_save.php';

$pageTitle = 'Book a service';
$navActive = 'book';
$successBooking = null;
$errorMsg = '';
$bookingAutomationNotice = null;
$bookingAutomationAssigned = false;

$serviceTypes = [
    'computer' => 'Computer repair',
    'printer' => 'Printer repair',
    'cctv' => 'CCTV installation',
    'maintenance' => 'Maintenance service',
    'automobile' => 'Automobile breakdown / service',
    'ac' => 'AC repair',
    'electrical' => 'Electrical (DC wiring)',
    'other' => 'Other',
];

$prefillType = trim((string) ($_GET['type'] ?? ''));
if (!isset($serviceTypes[$prefillType])) {
    $prefillType = 'computer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && db_table_exists(db(), 'web_bookings')) {
    $pdo = db();
    $name = trim((string) ($_POST['customer_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $stype = (string) ($_POST['service_type'] ?? 'other');
    $problem = trim((string) ($_POST['problem_description'] ?? ''));
    $prefDate = trim((string) ($_POST['preferred_date'] ?? ''));
    $lat = trim((string) ($_POST['latitude'] ?? ''));
    $lng = trim((string) ($_POST['longitude'] ?? ''));
    $isEmergency = !empty($_POST['is_emergency']) && $stype === 'automobile' ? 1 : 0;

    if (!isset($serviceTypes[$stype])) {
        $stype = 'other';
    }
    $prefDateOk = ($prefDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $prefDate)) ? $prefDate : null;
    $latOk = ($lat !== '' && is_numeric($lat)) ? round((float) $lat, 7) : null;
    $lngOk = ($lng !== '' && is_numeric($lng)) ? round((float) $lng, 7) : null;

    if ($name === '' || $phone === '' || $problem === '') {
        $errorMsg = 'Please fill in your name, phone, and problem description.';
    } elseif (strlen($phone) < 7) {
        $errorMsg = 'Enter a valid phone number.';
    } else {
        $uploadPath = null;
        if (!empty($_FILES['image']['name']) && (int) $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['image'];
            $maxBytes = 2 * 1024 * 1024;
            if ((int) $f['size'] > $maxBytes) {
                $errorMsg = 'Image must be 2MB or smaller.';
            } else {
                $info = @getimagesize($f['tmp_name']);
                if ($info === false || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                    $errorMsg = 'Upload a JPEG, PNG, or WebP image only.';
                } else {
                    $ext = match ($info[2]) {
                        IMAGETYPE_JPEG => 'jpg',
                        IMAGETYPE_PNG => 'png',
                        IMAGETYPE_WEBP => 'webp',
                        default => 'bin',
                    };
                    $dir = ROOT_PATH . '/uploads/bookings';
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $fn = 'bk_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $dir . '/' . $fn;
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        $uploadPath = 'uploads/bookings/' . $fn;
                    } else {
                        $errorMsg = 'Could not save upload.';
                    }
                }
            }
        }

        if ($errorMsg === '') {
            try {
                $pdo->beginTransaction();
                $bk = next_booking_number($pdo);
                $insertRow = [
                    'booking_number' => $bk,
                    'customer_name' => $name,
                    'phone' => $phone,
                    'email' => $email !== '' ? $email : null,
                    'address' => $address !== '' ? $address : null,
                    'service_type' => $stype,
                    'problem_description' => $problem,
                    'preferred_date' => $prefDateOk,
                    'image_path' => $uploadPath,
                    'latitude' => $latOk,
                    'longitude' => $lngOk,
                    'is_emergency' => $isEmergency,
                    'status' => 'pending',
                ];
                $cols = [];
                $vals = [];
                foreach ($insertRow as $col => $val) {
                    if (db_column_exists($pdo, 'web_bookings', $col)) {
                        $cols[] = $col;
                        $vals[] = $val;
                    }
                }
                if ($cols === []) {
                    throw new RuntimeException('web_bookings has no recognized columns.');
                }
                $placeholders = implode(',', array_fill(0, count($cols), '?'));
                $sql = 'INSERT INTO web_bookings (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
                $pdo->prepare($sql)->execute($vals);
                $newBookingId = (int) $pdo->lastInsertId();

                $auto = vk_booking_automation_after_insert(
                    $pdo,
                    $newBookingId,
                    $bk,
                    $name,
                    $phone,
                    $stype,
                    $problem,
                    $prefDateOk,
                    $latOk,
                    $lngOk,
                    $serviceTypes
                );
                if ($auto['user_notice'] !== null) {
                    $bookingAutomationNotice = $auto['user_notice'];
                }
                if ($auto['assign'] !== null) {
                    $bookingAutomationAssigned = true;
                }

                $pdo->commit();
                $successBooking = $bk;

                if ($auto['assign'] !== null) {
                    vk_booking_automation_notify_whatsapp(
                        $bk,
                        $name,
                        $phone,
                        $stype,
                        $problem,
                        $prefDateOk,
                        $latOk,
                        $lngOk,
                        $serviceTypes,
                        $auto['assign']
                    );
                }
            } catch (Throwable $e) {
                $pdo->rollBack();
                $errorMsg = APP_DEBUG ? $e->getMessage() : 'Could not save booking. Please try again.';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !db_table_exists(db(), 'web_bookings')) {
    $errorMsg = 'Online booking is not available until the database is upgraded (see sql/upgrade_v4_public.sql).';
}

$extraHead = $extraHead ?? '';
if (!$successBooking) {
    $extraHead .= '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous"/>';
    $extraHead .= '<style>'
        . '#bookForm #map{height:350px;width:100%;border-radius:12px;margin-bottom:10px;}'
        . '#bookForm .leaflet-container img.leaflet-tile,#bookForm .leaflet-container img.leaflet-marker-icon,#bookForm .leaflet-container img.leaflet-marker-shadow{max-width:none!important;max-height:none!important}'
        . '</style>';
}

$seoCanonicalPath = BASE_URL . '/book.php';
$seoDescription = 'Book computer, printer, CCTV, maintenance, AC, electrical, and automobile services in Sri Lanka. Online form with optional map location.';

require __DIR__ . '/includes/public_header.php';
?>
<div class="vk-pub-page py-4 py-md-5">
    <div class="container" style="max-width: 720px;">
        <div data-aos="fade-up" data-aos-duration="650">
        <h1 class="h3 mb-2">Book a service</h1>
        <p class="text-muted small mb-4">We will contact you to confirm. Save your booking ID to track status.</p>
        </div>

        <?php if ($successBooking): ?>
            <div class="alert alert-success shadow-sm" data-aos="fade-up" data-aos-duration="600">
                <h2 class="h5 alert-heading">Booking received</h2>
                <p class="mb-2">Your booking ID is <code class="fs-5"><?= e($successBooking) ?></code></p>
                <?php if ($bookingAutomationAssigned): ?>
                    <p class="small mb-2 mb-md-3">The nearest available technician has been assigned. You will be contacted to confirm.</p>
                <?php elseif ($bookingAutomationNotice !== null && $bookingAutomationNotice !== ''): ?>
                    <p class="small text-body-secondary mb-2 mb-md-3"><?= e($bookingAutomationNotice) ?></p>
                <?php endif; ?>
                <p class="small mb-0"><a href="<?= e(BASE_URL) ?>/track.php?id=<?= urlencode($successBooking) ?>">Track this job</a> anytime.</p>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg !== ''): ?>
            <div class="alert alert-danger" data-aos="fade-up" data-aos-duration="500"><?= e($errorMsg) ?></div>
        <?php endif; ?>

        <?php if (!$successBooking): ?>
        <form method="post" enctype="multipart/form-data" class="card shadow-sm border-0" id="bookForm">
            <div class="card-body p-4">
                <div class="mb-3">
                    <label class="form-label" for="customer_name">Your name <span class="text-danger">*</span></label>
                    <input class="form-control" name="customer_name" id="customer_name" required maxlength="255" value="<?= e($_POST['customer_name'] ?? '') ?>">
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Phone <span class="text-danger">*</span></label>
                        <input class="form-control" name="phone" id="phone" required maxlength="64" value="<?= e($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" name="email" id="email" maxlength="255" value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3 mt-3">
                    <label class="form-label" for="address">Address</label>
                    <textarea class="form-control" name="address" id="address" rows="2" maxlength="2000"><?= e($_POST['address'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="service_type">Service type <span class="text-danger">*</span></label>
                    <select class="form-select" name="service_type" id="service_type">
                        <?php foreach ($serviceTypes as $k => $lab): ?>
                            <option value="<?= e($k) ?>" <?= ($_POST['service_type'] ?? $prefillType) === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3 p-3 bg-danger bg-opacity-10 rounded border border-danger border-opacity-25" id="emergencyWrap" style="display:none;">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_emergency" id="is_emergency" value="1" <?= !empty($_POST['is_emergency']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold text-danger" for="is_emergency">Emergency breakdown — high priority</label>
                    </div>
                    <div class="form-text">We will flag your request for fastest dispatch when possible.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="problem_description">Describe the problem <span class="text-danger">*</span></label>
                    <textarea class="form-control" name="problem_description" id="problem_description" rows="4" required maxlength="4000"><?= e($_POST['problem_description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="preferred_date">Preferred date</label>
                    <input type="date" class="form-control" name="preferred_date" id="preferred_date" value="<?= e($_POST['preferred_date'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="image">Photo (optional)</label>
                    <input type="file" class="form-control" name="image" id="image" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">Max 2MB. JPEG, PNG, or WebP.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Location on map (optional)</label>
                    <label class="visually-hidden" for="locationSearch">Search location</label>
                    <input type="text" class="form-control mb-2" id="locationSearch" placeholder="Search location..." autocomplete="off" aria-label="Search location">
                    <div id="map" role="application" aria-label="Click map to set location"></div>
                    <div class="row g-2 mt-2">
                        <div class="col-md-6">
                            <label class="form-label small mb-0" for="latitude">Latitude</label>
                            <input type="text" class="form-control form-control-sm" name="latitude" id="latitude" inputmode="decimal" placeholder="e.g. 7.8731" value="<?= e($_POST['latitude'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-0" for="longitude">Longitude</label>
                            <input type="text" class="form-control form-control-sm" name="longitude" id="longitude" inputmode="decimal" placeholder="e.g. 80.7718" value="<?= e($_POST['longitude'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center" id="btnGeo"><span class="vk-lucide-inline-sm me-1" aria-hidden="true"><i data-lucide="crosshair"></i></span>Use my location</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearLoc">Clear pin</button>
                    </div>
                    <p class="form-text small mb-0 mt-2">Click the map or search to set coordinates. You can also type latitude and longitude manually.</p>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">Submit booking</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
  var st = document.getElementById('service_type');
  var ew = document.getElementById('emergencyWrap');
  function syncEmerg() {
    if (!st || !ew) return;
    ew.style.display = st.value === 'automobile' ? 'block' : 'none';
    if (st.value !== 'automobile') {
      var cb = document.getElementById('is_emergency');
      if (cb) cb.checked = false;
    }
  }
  if (st) st.addEventListener('change', syncEmerg);
  syncEmerg();
})();
</script>
<?php
$extraScripts = '';
if (!$successBooking) {
    $extraScripts .= '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>';
    $extraScripts .= '<script src="' . e(BASE_URL) . '/assets/js/book-location-map.js" defer></script>';
}
require __DIR__ . '/includes/public_footer.php';
?>
