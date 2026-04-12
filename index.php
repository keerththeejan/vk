<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$services = [];
try {
    $pdo = db();
    if (db_table_exists($pdo, 'web_services')) {
        $services = $pdo->query(
            'SELECT id, slug, name, short_description, lucide_icon FROM web_services WHERE active = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
    }
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('index.php: database unavailable — ' . $e->getMessage());
    }
}
if (!$services) {
    $services = [
        ['id' => null, 'slug' => 'computer', 'name' => 'Computer repair', 'short_description' => 'Laptops, desktops, OS, upgrades, virus cleanup.', 'lucide_icon' => 'laptop'],
        ['id' => null, 'slug' => 'printer', 'name' => 'Printer service', 'short_description' => 'Cartridges, jams, rollers, refills, office printers.', 'lucide_icon' => 'printer'],
        ['id' => null, 'slug' => 'cctv', 'name' => 'CCTV installation', 'short_description' => 'Cameras, DVR/NVR, cabling, remote viewing setup.', 'lucide_icon' => 'video'],
        ['id' => null, 'slug' => 'maintenance', 'name' => 'Maintenance', 'short_description' => 'AMC, scheduled visits, health checks.', 'lucide_icon' => 'wrench'],
        ['id' => null, 'slug' => 'automobile', 'name' => 'Automobile breakdown', 'short_description' => 'Roadside-style support with emergency priority.', 'lucide_icon' => 'car-front'],
        ['id' => null, 'slug' => 'ac', 'name' => 'AC repair', 'short_description' => 'Split & window units, gas, cleaning, faults.', 'lucide_icon' => 'snowflake'],
        ['id' => null, 'slug' => 'electrical', 'name' => 'Electrical (DC)', 'short_description' => 'DC wiring, solar/aux circuits, safe installs.', 'lucide_icon' => 'zap'],
    ];
}

$pageTitle = 'Home';
$navActive = 'home';
$seoCanonicalPath = BASE_URL . '/index.php';
$seoAuto = vk_app_setting('seo_auto_enabled', '1') !== '0';
$localKeywords = $seoAuto ? vk_local_keyword_pack('Computer repair') : [];
$seoDescription = $seoAuto ? vk_local_meta_description('Computer repair and laptop service') : vk_seo_default_description();
$seoKeywords = vk_seo_default_keywords() . ($localKeywords ? ', ' . implode(', ', $localKeywords) : '');

require __DIR__ . '/includes/public_header.php';
?>
<section class="vk-hero-premium">
    <div class="container vk-hero-inner">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-7" data-aos="fade-right" data-aos-duration="700">
                <div>
                    <h1 class="vk-hero-title"><?= $seoAuto ? 'Computer repair Jaffna · Laptop service Kilinochchi · IT repair Vavuniya · Printer repair Mullaitivu' : 'Repairs, installations & maintenance — done right.' ?></h1>
                    <p class="vk-hero-lead">Computer and printer service, CCTV systems, automobile breakdown help, AC repair, electrical (DC wiring), and scheduled maintenance for homes and businesses.</p>
                    <div class="vk-hero-actions d-flex flex-wrap gap-3">
                        <a class="vk-btn-hero-primary btn btn-lg px-4" href="<?= e(BASE_URL) ?>/book.php"><span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="calendar-plus"></i></span>Book service</a>
                        <a class="vk-btn-hero-secondary btn btn-lg px-4" href="<?= e(BASE_URL) ?>/book.php?type=maintenance"><span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="wrench"></i></span>Request maintenance</a>
                        <a class="vk-btn-hero-secondary btn btn-lg px-4" href="<?= e(BASE_URL) ?>/track.php"><span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="search"></i></span>Track job</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-5" data-aos="fade-left" data-aos-duration="700" data-aos-delay="120">
                <div class="vk-glass-card vk-float-soft p-4 p-lg-5">
                    <h2 class="vk-glass-title mb-3">Why choose us</h2>
                    <ul class="list-unstyled vk-glass-list mb-0">
                        <li class="d-flex align-items-start gap-2 mb-3"><span class="d-inline-flex" aria-hidden="true"><i data-lucide="circle-check"></i></span><span>Online booking &amp; real-time job tracking</span></li>
                        <li class="d-flex align-items-start gap-2 mb-3"><span class="d-inline-flex" aria-hidden="true"><i data-lucide="circle-check"></i></span><span>Emergency automobile breakdown priority</span></li>
                        <li class="d-flex align-items-start gap-2 mb-3"><span class="d-inline-flex" aria-hidden="true"><i data-lucide="circle-check"></i></span><span>Field technicians with mobile updates &amp; photos</span></li>
                        <li class="d-flex align-items-start gap-2"><span class="d-inline-flex" aria-hidden="true"><i data-lucide="circle-check"></i></span><span>Clear estimates, invoicing &amp; payment history</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($localKeywords): ?>
<section class="py-4 border-bottom border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted">Trending local searches:</span>
            <?php foreach ($localKeywords as $kw): ?>
                <span class="badge text-bg-light border"><?= e($kw) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="vk-pub-section-alt py-5" id="services">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up" data-aos-duration="650">
            <h2 class="vk-section-title mb-2">Our services</h2>
            <p class="vk-section-lead mx-auto mb-0">End-to-end support across IT, security, comfort systems, vehicles, and power.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($services as $si => $s):
                $sid = isset($s['id']) && $s['id'] !== null && (int) $s['id'] > 0 ? (int) $s['id'] : 0;
                $svcSlug = isset($s['slug']) ? trim((string) $s['slug']) : '';
                $cardHref = $sid > 0
                    ? ($svcSlug !== '' ? vk_web_service_public_path($svcSlug, $sid) : BASE_URL . '/service-details.php?id=' . $sid)
                    : BASE_URL . '/book.php?type=' . rawurlencode((string) $s['slug']);
                ?>
                <div class="col-md-6 col-xl-4" data-aos="fade-up" data-aos-duration="650" data-aos-delay="<?= (int) min(220, $si * 55) ?>">
                    <div class="vk-pub-service-card p-4 position-relative">
                        <div class="vk-pub-icon-wrap mb-3">
                            <i data-lucide="<?= e((string) $s['lucide_icon']) ?>"></i>
                        </div>
                        <h3 class="mb-2"><?= e((string) $s['name']) ?></h3>
                        <p class="text-muted small mb-3"><?= e((string) $s['short_description']) ?></p>
                        <a class="btn btn-sm btn-outline-primary stretched-link" href="<?= e($cardHref) ?>"><?= $sid > 0 ? 'View details &amp; book' : 'Book this' ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <h2 class="vk-section-title mb-0">Popular local pages</h2>
            <span class="small text-muted">Auto SEO landing links</span>
        </div>
        <div class="row g-3">
            <?php
            $rawLoc = (string) (vk_app_setting('seo_locations', 'jaffna,vavuniya,kilinochchi') ?? 'jaffna,vavuniya,kilinochchi');
            $rawSvc = (string) (vk_app_setting('seo_service_slugs', 'computer-repair,laptop-repair,printer-repair,it-service') ?? 'computer-repair,laptop-repair,printer-repair,it-service');
            $locs = array_values(array_filter(array_map(static fn(string $v): string => strtolower(trim($v)), explode(',', $rawLoc))));
            $svcs = array_values(array_filter(array_map(static fn(string $v): string => strtolower(trim($v)), explode(',', $rawSvc))));
            if (!$locs) {
                $locs = ['jaffna', 'vavuniya', 'kilinochchi'];
            }
            if (!$svcs) {
                $svcs = ['computer-repair', 'laptop-repair', 'printer-repair', 'it-service'];
            }
            $localLanding = [];
            foreach ($locs as $ll) {
                foreach ($svcs as $ss) {
                    $localLanding[] = [
                        'slug' => $ss . '-' . $ll,
                        'label' => ucwords(str_replace('-', ' ', $ss . ' ' . $ll)),
                    ];
                }
            }
            $localLanding = array_slice($localLanding, 0, 8);
            foreach ($localLanding as $lp): ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="text-decoration-none" href="<?= e(BASE_URL . '/service/' . $lp['slug']) ?>">
                        <div class="card vk-card h-100 p-3">
                            <strong><?= e($lp['label']) ?></strong>
                            <span class="small text-muted">View local offer</span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vk-pub-section-contact py-5">
    <div class="container text-center" data-aos="fade-up" data-aos-duration="650">
        <h2 class="vk-section-title mb-3">Get in touch</h2>
        <p class="vk-section-lead mx-auto mb-3 d-flex align-items-center justify-content-center flex-wrap gap-2">
            <span class="vk-contact-ic d-inline-flex" aria-hidden="true"><i data-lucide="phone"></i></span>
            <a href="tel:+94778870135" class="text-decoration-none fw-semibold" style="color: var(--vk-pub-text);">077 887 0135</a>
        </p>
        <p class="vk-section-lead mx-auto mb-4 d-flex align-items-center justify-content-center flex-wrap gap-2 text-muted">
            <span class="vk-contact-ic d-inline-flex" aria-hidden="true"><i data-lucide="map-pin"></i></span>
            <span>26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka</span>
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a class="btn btn-primary btn-lg px-4" href="<?= e(BASE_URL) ?>/book.php">Book online</a>
            <a class="btn btn-outline-secondary btn-lg px-4" href="<?= e(BASE_URL) ?>/portfolio.php">See completed work</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
