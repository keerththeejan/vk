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
    $priorityMode = (string) ($_POST['priority_mode'] ?? 'normal');
    if (!in_array($priorityMode, ['normal', 'high', 'emergency'], true)) {
        $priorityMode = 'normal';
    }
    $isEmergency = $priorityMode === 'emergency' || !empty($_POST['is_emergency']) ? 1 : 0;

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
        if ($priorityMode !== 'normal') {
            $priorityLabel = $priorityMode === 'emergency' ? 'EMERGENCY SERVICE 24/7' : 'HIGH PRIORITY SERVICE';
            if (!str_starts_with($problem, '[' . $priorityLabel . ']')) {
                $problem = '[' . $priorityLabel . "]\n" . $problem;
            }
        }

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
}
$extraHead .= '<style>'
        . ':root{--vk-book-night:#06111f;--vk-book-ink:#0b1728;--vk-book-card:rgba(10,23,42,.74);--vk-book-line:rgba(125,211,252,.20);--vk-book-cyan:#22d3ee;--vk-book-blue:#3b82f6;--vk-book-orange:#fb923c;--vk-book-red:#ef4444;}'
        . '.vk-book-page{position:relative;min-height:calc(100vh - 5rem);padding:clamp(1.25rem,3vw,2.5rem) 0 clamp(2rem,4vw,3.5rem);background:linear-gradient(180deg,rgba(6,17,31,.68),rgba(6,17,31,.92));overflow:hidden;}'
        . '.vk-book-page:before{content:"";position:absolute;inset:0;background:linear-gradient(rgba(125,211,252,.055) 1px,transparent 1px),linear-gradient(90deg,rgba(125,211,252,.045) 1px,transparent 1px),radial-gradient(circle at 18% 4%,rgba(59,130,246,.24),transparent 28rem),radial-gradient(circle at 88% 12%,rgba(34,211,238,.14),transparent 24rem);background-size:36px 36px,36px 36px,auto,auto;pointer-events:none;}'
        . '.vk-book-shell{position:relative;z-index:1;width:min(100% - 1.5rem,1320px);margin-inline:auto;}'
        . '.vk-book-hero{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(22rem,.72fr);gap:clamp(1rem,2vw,1.4rem);align-items:stretch;margin-bottom:1.15rem;}'
        . '.vk-book-hero-main,.vk-book-side-panel,.vk-book-form-card,.vk-book-success-card{border:1px solid var(--vk-book-line);border-radius:28px;background:linear-gradient(145deg,rgba(15,23,42,.88),rgba(15,23,42,.66));box-shadow:0 28px 82px rgba(2,8,23,.32),inset 0 1px 0 rgba(255,255,255,.08);backdrop-filter:blur(24px);}'
        . '.vk-book-hero-main{min-height:22rem;padding:clamp(1.35rem,3vw,2.4rem);display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;}'
        . '.vk-book-eyebrow,.vk-book-pill,.vk-book-status-chip{display:inline-flex;align-items:center;gap:.45rem;width:max-content;border:1px solid rgba(125,211,252,.22);border-radius:999px;background:rgba(125,211,252,.10);color:#bff4ff;font-size:.75rem;font-weight:850;letter-spacing:.07em;text-transform:uppercase;}'
        . '.vk-book-eyebrow{padding:.48rem .72rem;} .vk-book-pill,.vk-book-status-chip{padding:.48rem .68rem;text-transform:none;letter-spacing:0;font-size:.78rem;}'
        . '.vk-book-hero h1{max-width:53rem;margin:1.1rem 0 .85rem;color:#fff;font-size:clamp(2.25rem,5vw,5rem);line-height:.96;font-weight:850;letter-spacing:0;}'
        . '.vk-book-hero p{max-width:46rem;color:rgba(226,232,240,.76);font-size:clamp(.98rem,1.2vw,1.12rem);line-height:1.7;}'
        . '.vk-book-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.8rem;margin-top:1.3rem;}'
        . '.vk-book-metric{min-height:5.4rem;padding:.88rem;border:1px solid rgba(255,255,255,.10);border-radius:20px;background:rgba(255,255,255,.055);} .vk-book-metric strong{display:block;color:#fff;font-size:1.35rem;font-weight:850}.vk-book-metric span{color:rgba(226,232,240,.66);font-size:.78rem;font-weight:750;}'
        . '.vk-book-side-panel{padding:1.1rem;display:grid;gap:.85rem;} .vk-book-panel-row{display:flex;gap:.8rem;align-items:flex-start;padding:.9rem;border-radius:20px;background:rgba(255,255,255,.055);border:1px solid rgba(255,255,255,.09);} .vk-book-panel-row i{color:var(--vk-book-cyan);font-size:1.2rem}.vk-book-panel-row strong{display:block;color:#fff}.vk-book-panel-row span{display:block;color:rgba(226,232,240,.65);font-size:.84rem;}'
        . '.vk-book-form-card{padding:clamp(.8rem,1.8vw,1.15rem);}.vk-book-form-inner{display:grid;grid-template-columns:minmax(0,1.02fr) minmax(22rem,.78fr);gap:1rem;}'
        . '.vk-book-section{padding:clamp(1rem,2vw,1.25rem);border:1px solid rgba(255,255,255,.10);border-radius:24px;background:rgba(255,255,255,.055);} .vk-book-section + .vk-book-section{margin-top:1rem;}'
        . '.vk-book-section-title{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem;color:#fff;} .vk-book-section-title h2{margin:0;font-size:1rem;font-weight:850;}.vk-book-section-title span{color:rgba(226,232,240,.62);font-size:.78rem;}'
        . '.vk-book-form-card .form-floating>.form-control,.vk-book-form-card .form-floating>.form-select{min-height:3.65rem;border:1px solid rgba(125,211,252,.16);border-radius:18px;background:rgba(2,8,23,.42);color:#fff;box-shadow:none;}'
        . '.vk-book-form-card textarea.form-control{min-height:8.5rem!important;resize:vertical;}.vk-book-form-card .form-floating>label{color:rgba(226,232,240,.58);}.vk-book-form-card .form-control:focus,.vk-book-form-card .form-select:focus{border-color:rgba(34,211,238,.74);box-shadow:0 0 0 .22rem rgba(34,211,238,.10),0 0 28px rgba(34,211,238,.12);background:rgba(2,8,23,.58);color:#fff;}'
        . '.vk-priority-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;}.vk-priority-card{position:relative;min-height:10.4rem;display:flex;flex-direction:column;justify-content:space-between;padding:1rem;border:1px solid rgba(125,211,252,.16);border-radius:22px;background:linear-gradient(145deg,rgba(15,23,42,.74),rgba(30,41,59,.48));color:#fff;cursor:pointer;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease,background .2s ease;}.vk-priority-card input{position:absolute;opacity:0;pointer-events:none}.vk-priority-card:hover,.vk-priority-card:has(input:checked){transform:translateY(-3px);border-color:rgba(34,211,238,.52);box-shadow:0 18px 46px rgba(34,211,238,.12);}.vk-priority-card strong{font-size:1rem;font-weight:850}.vk-priority-card span,.vk-priority-card small{color:rgba(226,232,240,.68);}.vk-priority-badge{width:max-content;padding:.34rem .52rem;border-radius:999px;background:rgba(59,130,246,.14);color:#bfdbfe;font-size:.68rem;font-weight:850;text-transform:uppercase;letter-spacing:.06em;}'
        . '.vk-priority-card.high{border-color:rgba(251,146,60,.24);}.vk-priority-card.high .vk-priority-badge{background:rgba(251,146,60,.16);color:#fed7aa}.vk-priority-card.emergency{border-color:rgba(239,68,68,.42);background:linear-gradient(145deg,rgba(127,29,29,.62),rgba(15,23,42,.58));box-shadow:0 0 0 1px rgba(239,68,68,.12),0 20px 60px rgba(239,68,68,.12);animation:vkEmergencyBreathe 2.4s ease-in-out infinite;}.vk-priority-card.emergency .vk-priority-badge{background:linear-gradient(135deg,rgba(239,68,68,.34),rgba(251,146,60,.24));color:#fff;}'
        . '.vk-upload-zone{position:relative;display:grid;place-items:center;min-height:8.75rem;border:1px dashed rgba(125,211,252,.35);border-radius:22px;background:rgba(2,8,23,.32);text-align:center;cursor:pointer;transition:border-color .2s ease,background .2s ease;}.vk-upload-zone:hover{border-color:rgba(34,211,238,.72);background:rgba(34,211,238,.06);}.vk-upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer}.vk-upload-zone strong,.vk-upload-zone span{display:block}.vk-upload-zone strong{color:#fff}.vk-upload-zone span{color:rgba(226,232,240,.62);font-size:.84rem;}'
        . '.vk-map-card{overflow:hidden}.vk-map-toolbar{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:.55rem;margin-bottom:.75rem}.vk-map-toolbar .form-control{min-height:2.85rem;border-radius:16px;background:rgba(2,8,23,.42);border-color:rgba(125,211,252,.16);color:#fff}.vk-map-toolbar .btn,.vk-book-form-card .btn-outline-light{border-radius:16px;border-color:rgba(125,211,252,.22);background:rgba(255,255,255,.06);color:#e0f2fe;}'
        . '#bookForm #map{height:clamp(280px,32vw,430px);width:100%;border:1px solid rgba(125,211,252,.22);border-radius:24px;margin-bottom:.75rem;overflow:hidden;background:#0f172a;}'
        . '#bookForm .leaflet-container img.leaflet-tile,#bookForm .leaflet-container img.leaflet-marker-icon,#bookForm .leaflet-container img.leaflet-marker-shadow{max-width:none!important;max-height:none!important}'
        . '.vk-coordinate-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}.vk-coordinate-grid .form-floating>.form-control{min-height:3.2rem}.vk-book-preview{position:sticky;top:6rem;display:grid;gap:.85rem}.vk-book-preview-card{padding:1rem;border:1px solid rgba(255,255,255,.10);border-radius:22px;background:rgba(255,255,255,.055);}.vk-book-preview-card h2{color:#fff;font-size:1rem;font-weight:850}.vk-book-preview-list{display:grid;gap:.55rem}.vk-book-preview-list div{display:flex;justify-content:space-between;gap:1rem;color:rgba(226,232,240,.68);font-size:.86rem}.vk-book-preview-list strong{color:#fff;text-align:right}.vk-hotline{border-color:rgba(239,68,68,.26);background:linear-gradient(145deg,rgba(127,29,29,.36),rgba(15,23,42,.44));}.vk-whatsapp-btn{border:0;border-radius:18px;background:linear-gradient(135deg,#16a34a,#22d3ee);color:#fff;font-weight:850;box-shadow:0 18px 38px rgba(34,197,94,.18);}'
        . '.vk-book-submit{min-height:4rem;border:0;border-radius:20px;background:linear-gradient(135deg,var(--vk-book-blue),var(--vk-book-cyan));box-shadow:0 20px 48px rgba(34,211,238,.20);font-size:1rem;font-weight:900;}.vk-book-submit.is-emergency{background:linear-gradient(135deg,var(--vk-book-red),var(--vk-book-orange));box-shadow:0 22px 52px rgba(239,68,68,.26);animation:vkEmergencyBreathe 2.4s ease-in-out infinite;}'
        . '.vk-book-alert{border-radius:22px;border:1px solid rgba(255,255,255,.14);}.vk-book-success-card{max-width:58rem;margin:0 auto;padding:clamp(1.25rem,3vw,2rem);text-align:center;color:#fff;}.vk-book-success-card code{color:#9aeaff}.vk-book-footer-fix{margin-bottom:0;}'
        . '@keyframes vkEmergencyBreathe{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.28),0 20px 60px rgba(239,68,68,.12)}50%{box-shadow:0 0 0 .32rem rgba(239,68,68,.08),0 26px 72px rgba(251,146,60,.2)}}'
        . '@media (max-width:1199.98px){.vk-book-hero,.vk-book-form-inner{grid-template-columns:1fr}.vk-book-preview{position:static}.vk-priority-grid{grid-template-columns:1fr 1fr 1fr}}'
        . '@media (max-width:767.98px){.vk-book-shell{width:min(100% - 1rem,1320px)}.vk-book-page{padding-top:.85rem}.vk-book-hero-main,.vk-book-side-panel,.vk-book-form-card{border-radius:22px}.vk-book-metrics,.vk-priority-grid,.vk-coordinate-grid{grid-template-columns:1fr}.vk-map-toolbar{grid-template-columns:1fr}.vk-map-toolbar .btn{width:100%}.vk-book-section{padding:.85rem;border-radius:20px}.vk-book-hero h1{font-size:clamp(2rem,12vw,3.15rem)}.vk-book-submit{width:100%}}'
        . '</style>';

$seoCanonicalPath = BASE_URL . '/book.php';
$seoDescription = 'Book computer, printer, CCTV, maintenance, AC, electrical, and automobile services in Sri Lanka. Online form with optional map location.';
$waDigitsBook = defined('VK_PUBLIC_WHATSAPP_NUMBER') ? (string) VK_PUBLIC_WHATSAPP_NUMBER : '94778870135';
$waBookMsg = rawurlencode('Hello, I need to book a service with VK Network.');
$waBookHref = 'https://wa.me/' . preg_replace('/\D+/', '', $waDigitsBook) . '?text=' . $waBookMsg;
$postedPriority = (string) ($_POST['priority_mode'] ?? (!empty($_POST['is_emergency']) ? 'emergency' : 'normal'));
if (!in_array($postedPriority, ['normal', 'high', 'emergency'], true)) {
    $postedPriority = 'normal';
}

require __DIR__ . '/includes/public_header.php';
?>
<div class="vk-book-page vk-book-footer-fix">
    <div class="vk-book-shell">
        <?php if ($successBooking): ?>
            <section class="vk-book-success-card" data-aos="fade-up" data-aos-duration="600">
                <span class="vk-book-eyebrow mx-auto mb-3"><i class="bi bi-check2-circle"></i> Booking confirmed</span>
                <h1 class="display-5 fw-bold mb-3">Your service request is in the system.</h1>
                <p class="mb-3 text-white-50">Booking ID <code class="fs-4"><?= e($successBooking) ?></code></p>
                <?php if ($bookingAutomationAssigned): ?>
                    <p class="mb-3">The nearest available technician has been assigned. You will be contacted to confirm.</p>
                <?php elseif ($bookingAutomationNotice !== null && $bookingAutomationNotice !== ''): ?>
                    <p class="text-white-50 mb-3"><?= e($bookingAutomationNotice) ?></p>
                <?php endif; ?>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                    <a class="btn btn-primary btn-lg rounded-pill px-4" href="<?= e(BASE_URL) ?>/track.php?id=<?= urlencode($successBooking) ?>">Track this job</a>
                    <a class="btn btn-outline-light btn-lg rounded-pill px-4" href="<?= e(BASE_URL) ?>/book.php">Book another service</a>
                </div>
            </section>
        <?php else: ?>
            <section class="vk-book-hero" data-aos="fade-up" data-aos-duration="650">
                <div class="vk-book-hero-main">
                    <div>
                        <span class="vk-book-eyebrow"><i class="bi bi-stars"></i> Enterprise service request platform</span>
                        <h1>Book expert support without the back-and-forth.</h1>
                        <p>Submit repair, maintenance, CCTV, AC, electrical, and breakdown requests with priority routing, map location, image evidence, and fast support handoff.</p>
                    </div>
                    <div class="vk-book-metrics">
                        <div class="vk-book-metric"><strong id="previewResponseHero">24-48h</strong><span>estimated response</span></div>
                        <div class="vk-book-metric"><strong>24/7</strong><span>emergency intake</span></div>
                        <div class="vk-book-metric"><strong>Live</strong><span>WhatsApp support</span></div>
                    </div>
                </div>
                <aside class="vk-book-side-panel" aria-label="Booking support options">
                    <div class="vk-book-panel-row"><i class="bi bi-lightning-charge-fill"></i><div><strong>Emergency-ready dispatch</strong><span>Flag urgent service instantly with a dedicated 24/7 option.</span></div></div>
                    <div class="vk-book-panel-row"><i class="bi bi-geo-alt-fill"></i><div><strong>Location-aware support</strong><span>Pin your exact service point for faster technician coordination.</span></div></div>
                    <div class="vk-book-panel-row"><i class="bi bi-whatsapp"></i><div><strong>Need help now?</strong><span>Use WhatsApp for quick booking assistance.</span></div></div>
                    <a class="btn vk-whatsapp-btn btn-lg w-100" href="<?= e($waBookHref) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-2"></i>WhatsApp quick booking</a>
                </aside>
            </section>

            <?php if ($errorMsg !== ''): ?>
                <div class="alert alert-danger vk-book-alert" data-aos="fade-up" data-aos-duration="500"><?= e($errorMsg) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" id="bookForm" class="vk-book-form-card" data-aos="fade-up" data-aos-duration="700" novalidate>
                <input type="hidden" name="is_emergency" id="is_emergency" value="<?= $postedPriority === 'emergency' ? '1' : '0' ?>">
                <div class="vk-book-form-inner">
                    <div>
                        <section class="vk-book-section" aria-labelledby="priorityTitle">
                            <div class="vk-book-section-title">
                                <div><h2 id="priorityTitle">Service priority</h2><span>Choose how fast this needs attention.</span></div>
                                <span class="vk-book-status-chip" id="priorityStatus">Standard queue</span>
                            </div>
                            <div class="vk-priority-grid">
                                <label class="vk-priority-card normal">
                                    <input type="radio" name="priority_mode" value="normal" <?= $postedPriority === 'normal' ? 'checked' : '' ?>>
                                    <span class="vk-priority-badge">Normal</span><strong>Normal Service</strong><span>Planned support for standard jobs.</span><small>Estimated response: 24-48 hours</small>
                                </label>
                                <label class="vk-priority-card high">
                                    <input type="radio" name="priority_mode" value="high" <?= $postedPriority === 'high' ? 'checked' : '' ?>>
                                    <span class="vk-priority-badge">Fast track</span><strong>High Priority</strong><span>Move urgent work ahead of the queue.</span><small>Estimated response: 4-8 hours</small>
                                </label>
                                <label class="vk-priority-card emergency">
                                    <input type="radio" name="priority_mode" value="emergency" <?= $postedPriority === 'emergency' ? 'checked' : '' ?>>
                                    <span class="vk-priority-badge">24/7 emergency</span><strong>Emergency Service</strong><span>Critical breakdown or service outage.</span><small>Estimated response: immediate triage</small>
                                </label>
                            </div>
                        </section>

                        <section class="vk-book-section" aria-labelledby="contactTitle">
                            <div class="vk-book-section-title"><div><h2 id="contactTitle">Contact and service details</h2><span>Required fields are marked automatically.</span></div></div>
                            <div class="row g-3">
                                <div class="col-md-6"><div class="form-floating"><input class="form-control" name="customer_name" id="customer_name" placeholder="Your name" required maxlength="255" autocomplete="name" value="<?= e($_POST['customer_name'] ?? '') ?>"><label for="customer_name">Your name *</label></div></div>
                                <div class="col-md-6"><div class="form-floating"><input class="form-control" name="phone" id="phone" placeholder="Phone" required maxlength="64" autocomplete="tel" value="<?= e($_POST['phone'] ?? '') ?>"><label for="phone">Phone *</label></div></div>
                                <div class="col-md-6"><div class="form-floating"><input type="email" class="form-control" name="email" id="email" placeholder="Email" maxlength="255" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>"><label for="email">Email</label></div></div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-select" name="service_type" id="service_type" aria-label="Service type">
                                            <?php foreach ($serviceTypes as $k => $lab): ?>
                                                <option value="<?= e($k) ?>" <?= ($_POST['service_type'] ?? $prefillType) === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="service_type">Service type *</label>
                                    </div>
                                </div>
                                <div class="col-12"><div class="form-floating"><textarea class="form-control" name="address" id="address" placeholder="Address" maxlength="2000"><?= e($_POST['address'] ?? '') ?></textarea><label for="address">Address</label></div></div>
                                <div class="col-12"><div class="form-floating"><textarea class="form-control" name="problem_description" id="problem_description" placeholder="Describe the problem" required maxlength="4000"><?= e($_POST['problem_description'] ?? '') ?></textarea><label for="problem_description">Describe the problem *</label></div></div>
                                <div class="col-md-6"><div class="form-floating"><input type="date" class="form-control" name="preferred_date" id="preferred_date" placeholder="Preferred date" value="<?= e($_POST['preferred_date'] ?? '') ?>"><label for="preferred_date">Preferred date</label></div></div>
                                <div class="col-md-6"><label class="vk-upload-zone" for="image"><input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp"><span><strong id="uploadLabel">Upload a service photo</strong><span>JPEG, PNG, or WebP up to 2MB</span></span></label></div>
                            </div>
                        </section>

                        <section class="vk-book-section vk-map-card" aria-labelledby="locationTitle">
                            <div class="vk-book-section-title"><div><h2 id="locationTitle">Map location</h2><span>Optional, but it helps dispatch faster.</span></div></div>
                            <label class="visually-hidden" for="locationSearch">Search location</label>
                            <div class="vk-map-toolbar">
                                <input type="text" class="form-control" id="locationSearch" placeholder="Search location..." autocomplete="off" aria-label="Search location">
                                <button type="button" class="btn btn-outline-light d-inline-flex align-items-center justify-content-center" id="btnGeo"><span class="vk-lucide-inline-sm me-1" aria-hidden="true"><i data-lucide="crosshair"></i></span>Use GPS</button>
                                <button type="button" class="btn btn-outline-light" id="btnClearLoc">Clear pin</button>
                            </div>
                            <div id="map" role="application" aria-label="Click map to set location"></div>
                            <div class="vk-coordinate-grid">
                                <div class="form-floating"><input type="text" class="form-control" name="latitude" id="latitude" inputmode="decimal" placeholder="Latitude" value="<?= e($_POST['latitude'] ?? '') ?>"><label for="latitude">Latitude</label></div>
                                <div class="form-floating"><input type="text" class="form-control" name="longitude" id="longitude" inputmode="decimal" placeholder="Longitude" value="<?= e($_POST['longitude'] ?? '') ?>"><label for="longitude">Longitude</label></div>
                            </div>
                        </section>
                    </div>

                    <aside class="vk-book-preview" aria-label="Booking preview">
                        <div class="vk-book-preview-card">
                            <span class="vk-book-pill mb-3"><i class="bi bi-activity"></i> Live booking preview</span>
                            <h2>Your request summary</h2>
                            <div class="vk-book-preview-list mt-3">
                                <div><span>Priority</span><strong id="previewPriority">Normal Service</strong></div>
                                <div><span>Response</span><strong id="previewResponse">24-48h</strong></div>
                                <div><span>Service</span><strong id="previewService">Computer repair</strong></div>
                                <div><span>Location</span><strong id="previewLocation">Not pinned</strong></div>
                            </div>
                        </div>
                        <div class="vk-book-preview-card vk-hotline">
                            <span class="vk-priority-badge mb-3">Emergency hotline</span>
                            <h2>Critical breakdown?</h2>
                            <p class="text-white-50 mb-3">Submit as Emergency Service or contact WhatsApp support for immediate triage.</p>
                            <a class="btn vk-whatsapp-btn w-100" href="<?= e($waBookHref) ?>" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-2"></i>Open WhatsApp</a>
                        </div>
                        <div class="vk-book-preview-card"><h2>Smart validation</h2><p class="text-white-50 small mb-0" id="validationHint">Add your name, phone, and problem details to unlock a clean handoff.</p></div>
                        <button type="submit" class="btn btn-primary vk-book-submit" id="submitBookingBtn">Submit Booking</button>
                    </aside>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
  var form = document.getElementById('bookForm');
  if (!form) return;

  var serviceType = document.getElementById('service_type');
  var emergencyInput = document.getElementById('is_emergency');
  var priorityStatus = document.getElementById('priorityStatus');
  var previewPriority = document.getElementById('previewPriority');
  var previewResponse = document.getElementById('previewResponse');
  var previewResponseHero = document.getElementById('previewResponseHero');
  var previewService = document.getElementById('previewService');
  var previewLocation = document.getElementById('previewLocation');
  var validationHint = document.getElementById('validationHint');
  var submitBtn = document.getElementById('submitBookingBtn');
  var uploadInput = document.getElementById('image');
  var uploadLabel = document.getElementById('uploadLabel');
  var nameInput = document.getElementById('customer_name');
  var phoneInput = document.getElementById('phone');
  var problemInput = document.getElementById('problem_description');
  var latInput = document.getElementById('latitude');
  var lngInput = document.getElementById('longitude');
  var draftKey = 'vk_booking_draft_v2';

  var priorityMap = {
    normal: { label: 'Normal Service', response: '24-48h', status: 'Standard queue', button: 'Submit Booking' },
    high: { label: 'High Priority', response: '4-8h', status: 'Fast-response queue', button: 'Get Fast Support' },
    emergency: { label: 'Emergency Service 24/7', response: 'Immediate triage', status: 'Emergency dispatch', button: 'Request Emergency Service' }
  };

  function selectedPriority() {
    var checked = form.querySelector('input[name="priority_mode"]:checked');
    return checked ? checked.value : 'normal';
  }

  function setText(el, value) {
    if (el) el.textContent = value;
  }

  function syncPriority() {
    var mode = selectedPriority();
    var data = priorityMap[mode] || priorityMap.normal;
    if (emergencyInput) emergencyInput.value = mode === 'emergency' ? '1' : '0';
    setText(priorityStatus, data.status);
    setText(previewPriority, data.label);
    setText(previewResponse, data.response);
    setText(previewResponseHero, data.response);
    if (submitBtn) {
      submitBtn.textContent = data.button;
      submitBtn.classList.toggle('is-emergency', mode === 'emergency');
    }
  }

  function syncPreview() {
    if (serviceType && previewService) {
      previewService.textContent = serviceType.options[serviceType.selectedIndex]?.text || 'Service';
    }
    var hasLocation = latInput && lngInput && latInput.value.trim() !== '' && lngInput.value.trim() !== '';
    setText(previewLocation, hasLocation ? 'Pinned' : 'Not pinned');
    var ready = nameInput.value.trim() !== '' && phoneInput.value.trim().length >= 7 && problemInput.value.trim() !== '';
    setText(validationHint, ready ? 'Ready to submit. Your request has the core details needed for dispatch.' : 'Add your name, phone, and problem details to unlock a clean handoff.');
  }

  function saveDraft() {
    try {
      var data = {
        customer_name: nameInput.value,
        phone: phoneInput.value,
        email: document.getElementById('email')?.value || '',
        address: document.getElementById('address')?.value || '',
        service_type: serviceType?.value || '',
        problem_description: problemInput.value,
        preferred_date: document.getElementById('preferred_date')?.value || '',
        priority_mode: selectedPriority(),
        latitude: latInput?.value || '',
        longitude: lngInput?.value || ''
      };
      localStorage.setItem(draftKey, JSON.stringify(data));
    } catch (e) {}
  }

  function restoreDraft() {
    if (form.dataset.hasServerValues === '1') return;
    try {
      var raw = localStorage.getItem(draftKey);
      if (!raw) return;
      var data = JSON.parse(raw);
      Object.keys(data).forEach(function (key) {
        if (key === 'priority_mode') {
          var pr = form.querySelector('input[name="priority_mode"][value="' + data[key] + '"]');
          if (pr) pr.checked = true;
          return;
        }
        var el = form.querySelector('[name="' + key + '"]');
        if (el && !el.value) el.value = data[key];
      });
    } catch (e) {}
  }

  if (uploadInput && uploadLabel) {
    uploadInput.addEventListener('change', function () {
      uploadLabel.textContent = uploadInput.files && uploadInput.files[0] ? uploadInput.files[0].name : 'Upload a service photo';
    });
  }

  form.querySelectorAll('input, select, textarea').forEach(function (el) {
    el.addEventListener('input', function () {
      syncPreview();
      saveDraft();
    });
    el.addEventListener('change', function () {
      syncPriority();
      syncPreview();
      saveDraft();
    });
  });

  form.addEventListener('submit', function (event) {
    syncPriority();
    if (!form.checkValidity()) {
      event.preventDefault();
      form.reportValidity();
      return;
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = selectedPriority() === 'emergency' ? 'Routing emergency request...' : 'Submitting booking...';
    }
    try { localStorage.removeItem(draftKey); } catch (e) {}
  });

  if (<?= ($_SERVER['REQUEST_METHOD'] === 'POST') ? 'true' : 'false' ?>) {
    form.dataset.hasServerValues = '1';
  }
  restoreDraft();
  syncPriority();
  syncPreview();
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
