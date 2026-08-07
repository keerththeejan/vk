<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/init_public.php';

$svcSlug = trim((string) ($_GET['local_service_slug'] ?? 'computer-repair'));
$locSlug = trim((string) ($_GET['local_location_slug'] ?? 'jaffna'));
$location = ucfirst($locSlug);
$serviceLabel = ucwords(str_replace('-', ' ', $svcSlug));

$pageTitle = $serviceLabel . ' ' . $location;
$navActive = '';
$seoCanonicalPath = BASE_URL . '/service/' . rawurlencode($svcSlug . '-' . strtolower($location));
$seoDescription = vk_local_meta_description($serviceLabel);
$seoKeywords = vk_seo_default_keywords() . ', ' . implode(', ', vk_local_keyword_pack($serviceLabel));
$seoOgImage = vk_seo_og_image_default();

$absUrl = vk_public_absolute_url($seoCanonicalPath);
$waMsg = '🔥 Best ' . strtolower($serviceLabel) . ' in ' . $location . '! Book now: ' . $absUrl;
$shares = vk_social_share_links($absUrl, $pageTitle, $waMsg);

require dirname(__DIR__) . '/includes/public_header.php';
?>
<section class="vk-hero-premium">
    <div class="container vk-hero-inner">
        <h1 class="vk-hero-title mb-3"><?= e($serviceLabel) ?> in <?= e($location) ?></h1>
        <p class="vk-hero-lead mb-3"><?= e(vk_local_meta_description($serviceLabel)) ?></p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/book.php?type=<?= e(rawurlencode($svcSlug)) ?>">Book now</a>
            <a class="btn btn-outline-secondary" href="<?= e($shares['whatsapp']) ?>" target="_blank" rel="noopener noreferrer">Share this service on WhatsApp</a>
            <a class="btn btn-outline-secondary" href="<?= e($shares['facebook']) ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
            <a class="btn btn-outline-secondary" href="<?= e($shares['telegram']) ?>" target="_blank" rel="noopener noreferrer">Telegram</a>
        </div>
    </div>
</section>
<section class="py-5">
    <div class="container">
        <div class="card vk-card p-4">
            <h2 class="h5 mb-3">Local service coverage</h2>
            <p class="mb-2">We serve Jaffna, Kilinochchi, Mullaitivu, and Vavuniya with field-ready technicians.</p>
            <ul class="mb-0">
                <?php foreach (vk_northern_locations() as $loc): ?>
                    <li><?= e($serviceLabel . ' ' . $loc) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/public_footer.php'; ?>
