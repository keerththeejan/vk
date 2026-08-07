<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init_public.php';

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
if ($slug === '' && !empty($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/service/([^/?#]+)#', (string) $_SERVER['REQUEST_URI'], $m)) {
        $slug = rawurldecode($m[1]);
    }
}

// Local SEO dynamic pages: {service}-{location} controlled by settings.
$rawLocations = (string) (vk_app_setting('seo_locations', 'jaffna,vavuniya,kilinochchi') ?? 'jaffna,vavuniya,kilinochchi');
$locationSlugs = array_values(array_filter(array_map(
    static fn(string $v): string => strtolower(trim($v)),
    explode(',', $rawLocations)
)));
if (!$locationSlugs) {
    $locationSlugs = ['jaffna', 'vavuniya', 'kilinochchi'];
}
$locationMap = [];
foreach ($locationSlugs as $loc) {
    $locationMap[$loc] = ucwords(str_replace('-', ' ', $loc));
}
$locationRegex = implode('|', array_map(static fn(string $l): string => preg_quote($l, '/'), array_keys($locationMap)));

if ($slug !== '' && preg_match('/^([a-z0-9-]+)-(' . $locationRegex . ')$/i', $slug, $m)) {
    $serviceSlug = strtolower((string) $m[1]);
    $locationSlug = strtolower((string) $m[2]);
    $location = $locationMap[$locationSlug] ?? ucfirst($locationSlug);
    $serviceHuman = ucwords(str_replace('-', ' ', $serviceSlug));
    if ($serviceHuman === 'Computer Repair') {
        $titleService = 'Computer Repair';
    } else {
        $titleService = $serviceHuman;
    }

    $pageTitle = $titleService . ' in ' . $location;
    $navActive = '';
    $seoDocumentTitle = $titleService . ' in ' . $location . ' | VK Network';
    $seoDescription = 'Best computer and laptop repair service in ' . $location . '. Fast and affordable IT support for local homes and businesses.';
    $seoKeywords = implode(', ', [
        strtolower($titleService) . ' ' . strtolower($location),
        'laptop repair ' . strtolower($location),
        'desktop repair ' . strtolower($location),
        'IT support ' . strtolower($location),
    ]);
    $seoCanonicalPath = BASE_URL . '/service/' . rawurlencode($serviceSlug . '-' . $locationSlug);
    $seoOgImage = vk_seo_og_image_default();

    $absUrl = vk_public_absolute_url($seoCanonicalPath);
    $waDigits = preg_replace('/\D+/', '', (string) (vk_app_setting('whatsapp_number', '94778870135') ?? '94778870135'));
    if ($waDigits === '') {
        $waDigits = '94778870135';
    }
    $waTemplate = (string) (vk_app_setting('whatsapp_default_message', '') ?? '');
    $waText = $waTemplate !== ''
        ? str_replace(['{location}', '{service}'], [$location, $titleService], $waTemplate)
        : ('I need computer repair in ' . $location);
    $waLink = 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($waText);

    require __DIR__ . '/includes/public_header.php';
    ?>
    <section class="vk-hero-premium">
        <div class="container vk-hero-inner">
            <h1 class="vk-hero-title mb-3">Best <?= e($titleService) ?> in <?= e($location) ?></h1>
            <p class="vk-hero-lead mb-4">Need reliable laptop repair, desktop repair, or IT support in <?= e($location) ?>? VK Network provides fast diagnostics, affordable service, and clear updates.</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-success btn-lg" href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer">WhatsApp Now</a>
                <a class="btn btn-outline-secondary btn-lg" href="<?= e(BASE_URL) ?>/book.php?type=<?= e(rawurlencode($serviceSlug)) ?>">Book Service</a>
            </div>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="card vk-card p-4">
                <h2 class="h4 mb-3"><?= e($titleService) ?> in <?= e($location) ?> - Local Team</h2>
                <p class="mb-0">We handle laptop repair, desktop repair, and IT support for homes and businesses in <?= e($location) ?>. Same-day response available for urgent issues, with WhatsApp updates and transparent pricing.</p>
            </div>
        </div>
    </section>

    <section class="py-4 border-top border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
        <div class="container">
            <h3 class="h6 text-uppercase text-muted mb-3">Other local pages</h3>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($locationMap as $locSlug => $locName): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL . '/service/' . $serviceSlug . '-' . $locSlug) ?>"><?= e($titleService . ' ' . $locName) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script type="application/ld+json">
    <?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        'name' => 'VK Network',
        'url' => $absUrl,
        'areaServed' => $location,
        'serviceType' => 'Computer Repair',
        'telephone' => '+' . $waDigits,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
    <?php
    require __DIR__ . '/includes/public_footer.php';
    exit;
}

// Fallback to existing dynamic service detail behavior.
if ($slug !== '') {
    $_GET['slug'] = $slug;
}
require __DIR__ . '/service-details.php';
