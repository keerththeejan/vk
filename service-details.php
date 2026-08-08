<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init_public.php';
vk_bootstrap_module('service_gallery');

$pdo = db();
if (!db_table_exists($pdo, 'web_services')) {
    redirect('/index.php');
}

$slugParam = trim((string) ($_GET['slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);

$service = null;
if ($slugParam !== '') {
    $st = $pdo->prepare('SELECT * FROM web_services WHERE slug = ? AND active = 1 LIMIT 1');
    $st->execute([$slugParam]);
    $service = $st->fetch();
} elseif ($id > 0) {
    $st = $pdo->prepare('SELECT * FROM web_services WHERE id = ? AND active = 1 LIMIT 1');
    $st->execute([$id]);
    $service = $st->fetch();
}

if (!$service) {
    redirect('/index.php');
}

$id = (int) $service['id'];

vk_service_gallery_auto_migrate($pdo);
$gallery = vk_service_gallery_fetch($pdo, $id, $service);

$relSt = $pdo->prepare(
    'SELECT id, slug, name, short_description, lucide_icon FROM web_services WHERE active = 1 AND id != ? ORDER BY sort_order ASC, id ASC LIMIT 4'
);
$relSt->execute([$id]);
$related = $relSt->fetchAll();

$features = web_service_features_decode($service['features_json'] ?? null);
$benefitsRaw = trim((string) ($service['benefits_text'] ?? ''));
$benefits = $benefitsRaw !== '' ? preg_split('/\r\n|\r|\n/', $benefitsRaw) : [];
$benefits = array_values(array_filter(array_map('trim', $benefits)));

$pageTitle = (string) $service['name'];
$navActive = '';
$seoAuto = vk_app_setting('seo_auto_enabled', '1') !== '0';
$localPack = $seoAuto ? vk_local_keyword_pack((string) $service['name']) : [];

$svcSlug = trim((string) ($service['slug'] ?? ''));
$seoDescription = trim(strip_tags((string) ($service['short_description'] ?? '')));
if ($seoDescription === '') {
    $seoDescription = vk_seo_default_description();
}
$seoKeywords = vk_seo_default_keywords() . ($localPack ? ', ' . implode(', ', $localPack) : '');
$seoCanonicalPath = $svcSlug !== ''
    ? vk_web_service_public_path($svcSlug, $id)
    : BASE_URL . '/service-details.php?id=' . $id;
$coverOg = trim((string) ($service['cover_image'] ?? ''));
if ($coverOg !== '' && public_asset_file_exists($coverOg)) {
    $seoOgImage = vk_site_origin() . public_asset_url($coverOg);
} else {
    $seoOgImage = vk_seo_og_image_default();
}
$absUrl = vk_site_origin() . $seoCanonicalPath;
$waViralMsg = '🔥 Best ' . strtolower((string) $service['name']) . ' in Jaffna! Book now: ' . $absUrl;
$shareLinks = vk_social_share_links($absUrl, (string) $service['name'], $waViralMsg);

$cover = trim((string) ($service['cover_image'] ?? ''));
$heroClass = 'vk-svc-hero';
$heroStyle = '';
if ($cover !== '' && public_asset_file_exists($cover)) {
    $heroClass .= ' has-cover';
    $u = public_asset_url($cover);
    $heroStyle = 'background-image:url(' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . ');';
}

require __DIR__ . '/includes/public_header.php';
?>
<div class="vk-pub-page" data-vk-service-slug="<?= e($svcSlug !== '' ? $svcSlug : ('id-' . $id)) ?>">
    <div class="container py-3">
        <nav class="vk-svc-breadcrumb mb-0" aria-label="Breadcrumb" data-aos="fade-down" data-aos-duration="500">
            <a href="<?= e(BASE_URL) ?>/index.php">Home</a>
            <span class="mx-2 opacity-50">/</span>
            <a href="<?= e(BASE_URL) ?>/index.php#services">Services</a>
            <span class="mx-2 opacity-50">/</span>
            <span class="text-body-secondary"><?= e($service['name']) ?></span>
        </nav>
    </div>

    <section class="<?= e($heroClass) ?>" style="<?= $heroStyle ?>">
        <div class="vk-svc-hero-overlay" aria-hidden="true"></div>
        <div class="container vk-svc-hero-inner" data-aos="fade-up" data-aos-duration="700">
            <h1 class="vk-svc-hero-title mb-3"><?= e($service['name']) ?></h1>
            <p class="vk-svc-hero-lead mb-0"><?= e($service['short_description']) ?></p>
            <?php if ($localPack): ?><p class="small mt-2 mb-0 vk-svc-hero-meta"><?= e(implode(' · ', $localPack)) ?></p><?php endif; ?>
            <?php
            $pf = $service['price_from'] ?? null;
            $pn = trim((string) ($service['price_note'] ?? ''));
            if ($pf !== null && (float) $pf > 0): ?>
                <div class="mt-4 d-flex flex-wrap align-items-center gap-2">
                    <span class="vk-svc-price-pill">From <?= e(formatCurrency($pf)) ?></span>
                    <?php if ($pn !== ''): ?>
                        <span class="small vk-svc-hero-note"><?= e($pn) ?></span>
                    <?php endif; ?>
                </div>
            <?php elseif ($pn !== ''): ?>
                <p class="mt-4 mb-0 small text-white-75"><?= e($pn) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="py-4 border-bottom border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
        <div class="container d-flex flex-wrap align-items-center gap-2">
            <span class="small text-muted me-2">Share:</span>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e($shareLinks['facebook']) ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= e($shareLinks['telegram']) ?>" target="_blank" rel="noopener noreferrer">Telegram</a>
            <a class="btn btn-sm btn-success" href="<?= e($shareLinks['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">Share this service on WhatsApp</a>
            <span class="small text-muted ms-auto">Views: <strong data-vk-view-count>0</strong></span>
        </div>
    </section>

    <section class="py-5 border-bottom border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
        <div class="container">
            <h2 class="vk-section-title mb-4" data-aos="fade-up">Gallery</h2>
            <div class="row g-4">
                <?php
                $gi = 0;
                foreach ($gallery as $row):
                    $path = trim((string) ($row['image_path'] ?? ''));
                    if ($path === '' || !public_asset_file_exists($path)) {
                        continue;
                    }
                    $cap = trim((string) ($row['title'] ?? ''));
                    $src = public_asset_url($path);
                    ?>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-duration="600" data-aos-delay="<?= (int) min(180, $gi * 55) ?>">
                        <figure class="vk-svc-gallery-item mb-0">
                            <div class="ratio ratio-4x3">
                                <button class="vk-svc-gallery-btn p-0 border-0 bg-transparent w-100 h-100" type="button" data-bs-toggle="modal" data-bs-target="#galleryPreviewModal" data-vk-gallery-src="<?= e($src) ?>" data-vk-gallery-title="<?= e($cap !== '' ? $cap : (string) $service['name']) ?>">
                                    <img src="<?= e($src) ?>" alt="<?= e($cap !== '' ? $cap : $service['name']) ?>" loading="lazy" width="800" height="600">
                                </button>
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
                    <p class="text-muted mb-0" data-aos="fade-up">Gallery images will appear here once assets are available.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <div class="modal fade" id="galleryPreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="h6 mb-0" id="galleryPreviewTitle"><?= e((string) $service['name']) ?></h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <img id="galleryPreviewImage" src="" alt="<?= e((string) $service['name']) ?>" class="w-100 vk-gallery-preview-img" width="1200" height="675" style="max-height:80vh;object-fit:contain" loading="lazy" decoding="async">
                </div>
            </div>
        </div>
    </div>

    <section class="py-5 vk-pub-section-alt">
        <div class="container">
            <div class="row g-5 align-items-start">
                <div class="col-lg-6" data-aos="fade-up" data-aos-duration="650">
                    <h2 class="h4 fw-bold mb-3" style="font-family: Poppins, sans-serif;">Overview</h2>
                    <p class="text-muted lh-lg mb-4"><?= nl2br(e((string) $service['description'])) ?></p>
                    <h3 class="h5 fw-bold mb-3" style="font-family: Poppins, sans-serif;">What we do</h3>
                    <p class="text-muted lh-lg mb-0"><?= nl2br(e((string) $service['what_we_do'])) ?></p>
                </div>
                <div class="col-lg-6" data-aos="fade-up" data-aos-duration="650" data-aos-delay="80">
                    <h2 class="h4 fw-bold mb-3" style="font-family: Poppins, sans-serif;">Features</h2>
                    <?php if ($features): ?>
                        <ul class="list-unstyled mb-4">
                            <?php foreach ($features as $f): ?>
                                <li class="d-flex gap-3 mb-3">
                                    <span class="vk-svc-feature-icon" aria-hidden="true"><i data-lucide="<?= e($f['icon']) ?>"></i></span>
                                    <span class="text-muted pt-1"><?= e($f['text']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if ($benefits): ?>
                        <h3 class="h6 text-uppercase fw-semibold mb-3" style="letter-spacing: 0.08em; color: var(--vk-pub-text-muted);">Benefits</h3>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($benefits as $b): ?>
                                <li class="d-flex align-items-start gap-2 mb-2 text-muted">
                                    <span class="text-success flex-shrink-0 mt-1" aria-hidden="true">✓</span>
                                    <span><?= e($b) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="vk-svc-cta-bar p-4 p-lg-5" data-aos="fade-up" data-aos-duration="600">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <h2 class="h4 fw-bold mb-2" style="font-family: Poppins, sans-serif;">Ready to book?</h2>
                        <p class="text-muted mb-0">Choose a time that works for you. We will confirm and share your booking ID for tracking.</p>
                    </div>
                    <div class="col-lg-5">
                        <div class="d-flex flex-column flex-sm-row flex-lg-column flex-xl-row gap-3 justify-content-lg-end">
                            <a class="btn btn-primary btn-lg px-4 flex-grow-1 flex-sm-grow-0" href="<?= e(BASE_URL) ?>/book.php?type=<?= e(urlencode((string) $service['slug'])) ?>">Book now</a>
                            <a class="btn btn-outline-secondary btn-lg px-4 flex-grow-1 flex-sm-grow-0" href="<?= e(BASE_URL) ?>/book.php?type=maintenance">Request maintenance</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php if ($related): ?>
    <section class="py-5 border-top border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
        <div class="container">
            <h2 class="vk-section-title mb-4" data-aos="fade-up">Related services</h2>
            <div class="row g-4">
                <?php foreach ($related as $ri => $r): ?>
                    <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="<?= (int) min(160, $ri * 45) ?>">
                        <a href="<?= e(vk_web_service_public_path((string) ($r['slug'] ?? ''), (int) $r['id'])) ?>" class="text-decoration-none text-body d-block h-100">
                            <div class="vk-svc-related-card p-3 h-100 position-relative">
                                <div class="vk-pub-icon-wrap mb-2" style="width:2.75rem;height:2.75rem;">
                                    <i data-lucide="<?= e((string) $r['lucide_icon']) ?>"></i>
                                </div>
                                <h3 class="h6 fw-bold mb-1"><?= e($r['name']) ?></h3>
                                <p class="small text-muted mb-0"><?= e($r['short_description']) ?></p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="py-5 border-top border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
        <div class="container">
            <h2 class="vk-section-title mb-3">Rate this service</h2>
            <div class="d-flex flex-wrap gap-2 mb-2" data-vk-rating-stars>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="btn btn-sm btn-outline-warning" data-vk-star="<?= $i ?>">★ <?= $i ?></button>
                <?php endfor; ?>
            </div>
            <p class="small text-muted mb-3">Average rating: <strong data-vk-rating-avg>0.0</strong> (<span data-vk-rating-count>0</span> votes)</p>
            <label class="form-label" for="vkReviewInput">Leave a quick review</label>
            <div class="d-flex gap-2">
                <input id="vkReviewInput" class="form-control" maxlength="220" placeholder="Your comment">
                <button class="btn btn-primary" type="button" data-vk-review-submit>Post</button>
            </div>
            <ul class="list-group mt-3" data-vk-review-list></ul>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
