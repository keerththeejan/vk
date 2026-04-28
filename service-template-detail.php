<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/modules/service_templates/service_template_location.php';

$pdo = db();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect('/index.php');
}

$st = $pdo->prepare('SELECT * FROM service_templates WHERE id = ? LIMIT 1');
$st->execute([$id]);
$tpl = $st->fetch(PDO::FETCH_ASSOC);
if (!$tpl) {
    redirect('/index.php');
}

$gallery = [];
if (db_table_exists($pdo, 'service_images')) {
    $imgSt = $pdo->prepare('SELECT * FROM service_images WHERE service_id = ? ORDER BY sort_order ASC, id ASC');
    $imgSt->execute([$id]);
    $gallery = $imgSt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = (string) $tpl['name'];
$navActive = '';

$cover = trim((string) ($tpl['image'] ?? ''));
$heroClass = 'vk-svc-hero';
$heroStyle = '';
if ($cover !== '' && public_asset_file_exists($cover)) {
    $heroClass .= ' has-cover';
    $u = public_asset_url($cover);
    $heroStyle = 'background-image:url(' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . ');';
}

$shortDesc = trim((string) ($tpl['description'] ?? ''));
if ($shortDesc === '') {
    $shortDesc = ucfirst((string) $tpl['category']) . ' service template.';
}

$hasMap = st_service_template_has_coordinates($tpl);
$mapLat = $hasMap ? (float) $tpl['latitude'] : 0.0;
$mapLng = $hasMap ? (float) $tpl['longitude'] : 0.0;
$locAddress = trim((string) ($tpl['address'] ?? ''));
$directionsUrl = $hasMap
    ? 'https://www.google.com/maps?q=' . rawurlencode((string) $mapLat . ',' . (string) $mapLng)
    : '';

$seoCanonicalPath = BASE_URL . '/service-template-detail.php?id=' . (int) $id;
$seoDescription = $shortDesc;

$extraHead = '';
$extraScripts = '';
if ($hasMap) {
    $extraHead .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">';
    $extraHead .= '<link href="' . e(BASE_URL) . '/assets/css/service-location.css" rel="stylesheet">';
    $extraScripts .= '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous" defer></script>';
    $extraScripts .= '<script src="' . e(BASE_URL) . '/assets/js/service-location-public.js" defer></script>';
}

require __DIR__ . '/includes/public_header.php';
?>
<div class="vk-pub-page">
    <div class="container py-3">
        <nav class="vk-svc-breadcrumb mb-0" aria-label="Breadcrumb" data-aos="fade-down" data-aos-duration="500">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <span class="mx-2 opacity-50">/</span>
            <a href="<?= e(BASE_URL) ?>/index.php#services">Services</a>
            <span class="mx-2 opacity-50">/</span>
            <span class="text-body-secondary"><?= e($tpl['name']) ?></span>
        </nav>
    </div>

    <section class="<?= e($heroClass) ?>" style="<?= $heroStyle ?>">
        <div class="vk-svc-hero-overlay" aria-hidden="true"></div>
        <div class="container vk-svc-hero-inner" data-aos="fade-up" data-aos-duration="700">
            <?php
            $cat = (string) ($tpl['category'] ?? 'general');
            $catBadge = [
                'printer' => 'text-bg-light text-dark',
                'computer' => 'text-bg-info',
                'cctv' => 'text-bg-secondary',
                'general' => 'text-bg-dark',
            ][$cat] ?? 'text-bg-dark';
            ?>
            <span class="badge <?= e($catBadge) ?> mb-2"><?= e($cat) ?></span>
            <h1 class="vk-svc-hero-title mb-3"><?= e($tpl['name']) ?></h1>
            <p class="vk-svc-hero-lead mb-0"><?= e($shortDesc) ?></p>
            <?php $amt = (float) ($tpl['default_amount'] ?? 0); ?>
            <?php if ($amt > 0): ?>
                <div class="mt-4">
                    <span class="vk-svc-price-pill">Default from LKR <?= e(number_format($amt, 0, '.', ',')) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-5 border-bottom border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
        <div class="container">
            <h2 class="vk-section-title mb-4" data-aos="fade-up">Gallery</h2>
            <div class="row g-4 vk-svc-gallery-row">
                <?php
                $gi = 0;
                foreach ($gallery as $row):
                    $path = trim((string) ($row['image_path'] ?? ''));
                    if ($path === '' || !public_asset_file_exists($path)) {
                        continue;
                    }
                    $cap = trim((string) ($row['caption'] ?? ''));
                    $src = public_asset_url($path);
                    ?>
                    <div class="col-12 col-md-6 col-lg-4" data-aos="fade-up" data-aos-duration="600" data-aos-delay="<?= (int) min(180, $gi * 55) ?>">
                        <figure class="vk-svc-gallery-item mb-0">
                            <div class="ratio ratio-4x3">
                                <img src="<?= e($src) ?>" alt="<?= e($cap !== '' ? $cap : $tpl['name']) ?>" loading="lazy" width="800" height="600" decoding="async">
                            </div>
                            <?php if ($cap !== ''): ?>
                                <figcaption class="small text-muted px-2 py-2 mb-0"><?= e($cap) ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    </div>
                <?php
                    ++$gi;
                endforeach; ?>
                <?php if ($gi === 0): ?>
                    <div class="col-12">
                        <p class="text-muted mb-0" data-aos="fade-up">No gallery images yet. Add them from the admin service template editor.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="py-5 vk-pub-section-alt">
        <div class="container">
            <div class="row g-5 align-items-start">
                <div class="col-lg-8 mx-auto text-center" data-aos="fade-up">
                    <h2 class="h4 fw-bold mb-3" style="font-family: Poppins, sans-serif;">About this service</h2>
                    <p class="text-muted lh-lg mb-0"><?= nl2br(e((string) ($tpl['description'] ?? 'No description provided.'))) ?></p>
                </div>
            </div>
        </div>
    </section>

    <?php if ($hasMap): ?>
    <section class="py-5 border-bottom border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
        <div class="container">
            <h2 class="vk-section-title mb-3" data-aos="fade-up">Service location</h2>
            <?php if ($locAddress !== ''): ?>
                <p class="text-muted mb-3" data-aos="fade-up"><?= nl2br(e($locAddress)) ?></p>
            <?php endif; ?>
            <div class="vk-svc-loc-card overflow-hidden mb-3">
                <div class="vk-svc-loc-map-wrap">
                    <div
                        id="map"
                        class="vk-svc-loc-map"
                        data-lat="<?= e((string) $mapLat) ?>"
                        data-lng="<?= e((string) $mapLng) ?>"
                        role="application"
                        aria-label="Service location map"
                    ></div>
                </div>
            </div>
            <div class="vk-svc-loc-actions d-flex flex-column flex-sm-row flex-wrap gap-2 align-items-stretch align-items-sm-center" data-aos="fade-up">
                <a class="btn btn-primary px-4" href="<?= e($directionsUrl) ?>" target="_blank" rel="noopener noreferrer">Get directions</a>
                <button type="button" class="btn btn-outline-secondary px-4" id="vk_svc_loc_geolocate">Use my location &amp; distance</button>
                <span class="vk-svc-loc-distance d-none ms-sm-2" id="vk_svc_loc_distance" aria-live="polite"></span>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="py-5">
        <div class="container">
            <div class="vk-svc-cta-bar p-4 p-lg-5" data-aos="fade-up" data-aos-duration="600">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <h2 class="h4 fw-bold mb-2" style="font-family: Poppins, sans-serif;">Book this service</h2>
                        <p class="text-muted mb-0">Choose a time online. Reference this service when you book.</p>
                    </div>
                    <div class="col-lg-5">
                        <a class="btn btn-primary btn-lg px-4 w-100 w-sm-auto" href="<?= e(BASE_URL) ?>/book.php">Book now</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
