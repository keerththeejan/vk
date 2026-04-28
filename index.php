<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/init.php';

$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
if (preg_match('#/index\.php/staff/?$#i', $requestPath)) {
    require __DIR__ . '/Staff.php';
    exit;
}

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
        error_log('index.php: database unavailable - ' . $e->getMessage());
    }
}

if (!$services) {
    $services = [
        ['id' => null, 'slug' => 'computer', 'name' => 'Computer repair', 'short_description' => 'Laptop, desktop, OS, upgrade, virus cleanup, and diagnostics.', 'lucide_icon' => 'laptop'],
        ['id' => null, 'slug' => 'printer', 'name' => 'Printer service', 'short_description' => 'Printer jams, refills, cartridges, rollers, and office maintenance.', 'lucide_icon' => 'printer'],
        ['id' => null, 'slug' => 'cctv', 'name' => 'CCTV installation', 'short_description' => 'Camera setup, DVR/NVR configuration, cabling, and remote viewing.', 'lucide_icon' => 'video'],
        ['id' => null, 'slug' => 'maintenance', 'name' => 'Maintenance plans', 'short_description' => 'Scheduled visits, preventive checks, field updates, and reporting.', 'lucide_icon' => 'wrench'],
        ['id' => null, 'slug' => 'automobile', 'name' => 'Vehicle support', 'short_description' => 'Emergency breakdown support, vehicle bookings, and technician dispatch.', 'lucide_icon' => 'car-front'],
        ['id' => null, 'slug' => 'electrical', 'name' => 'Electrical support', 'short_description' => 'DC wiring, safe installations, solar auxiliary circuits, and checks.', 'lucide_icon' => 'zap'],
    ];
}

$teamMembers = [
    [
        'name' => 'Vijay Keerththeejan',
        'role' => 'Founder / Owner',
        'image' => base_url('assets/images/staff/owner.svg'),
        'description' => 'Leads service strategy, networking solutions, AI systems, and customer experience.',
        'skills' => ['Networking', 'AI Systems', 'Web Development'],
    ],
    [
        'name' => 'John Silva',
        'role' => 'Technician',
        'image' => base_url('assets/images/staff/staff1.svg'),
        'description' => 'Handles diagnostics, hardware repairs, printer service, and site visits.',
        'skills' => ['Hardware Repair', 'Printer Service', 'CCTV'],
    ],
    [
        'name' => 'Nimal Perera',
        'role' => 'System Admin',
        'image' => base_url('assets/images/staff/staff2.svg'),
        'description' => 'Maintains servers, network reliability, backup routines, and security checks.',
        'skills' => ['Servers', 'Network Management', 'Security'],
    ],
    [
        'name' => 'Nisha Raj',
        'role' => 'Customer Support',
        'image' => base_url('assets/images/staff/nisha.svg'),
        'description' => 'Coordinates bookings, customer updates, tracking requests, and follow ups.',
        'skills' => ['Support', 'Scheduling', 'Customer Care'],
    ],
];

$stats = [
    ['value' => '7+', 'label' => 'Service categories'],
    ['value' => '24h', 'label' => 'Fast response window'],
    ['value' => '100%', 'label' => 'Local support focus'],
];

$pageTitle = 'Home';
$navActive = 'home';
$seoCanonicalPath = '/index.php';
$seoAuto = vk_app_setting('seo_auto_enabled', '1') !== '0';
$localKeywords = $seoAuto ? vk_local_keyword_pack('Computer repair') : [];
$seoDescription = $seoAuto ? vk_local_meta_description('Computer repair and laptop service') : vk_seo_default_description();
$seoKeywords = vk_seo_default_keywords() . ($localKeywords ? ', ' . implode(', ', $localKeywords) : '');

require __DIR__ . '/includes/public_header.php';
?>
<section class="vk-hero-premium vk-home-hero" id="top">
    <div class="container vk-hero-inner">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-7" data-aos="fade-right" data-aos-duration="700">
                <span class="vk-hero-eyebrow d-inline-flex align-items-center gap-2">
                    <i data-lucide="sparkles"></i>
                    Multi-service solutions in Northern Sri Lanka
                </span>
                <h1 class="vk-hero-title">Professional repairs, installations, and maintenance for homes and businesses.</h1>
                <p class="vk-hero-lead">Book computer repair, printer service, CCTV installation, vehicle support, AC repair, and electrical service from one responsive local team.</p>
                <div class="vk-hero-actions d-flex flex-wrap gap-3">
                    <a class="vk-btn-hero-primary btn btn-lg px-4" href="<?= e(BASE_URL) ?>/book.php">
                        <span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="calendar-plus"></i></span>
                        Book service
                    </a>
                    <a class="vk-btn-hero-secondary btn btn-lg px-4" href="<?= e(BASE_URL) ?>/track.php">
                        <span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="search"></i></span>
                        Track job
                    </a>
                </div>
                <div class="row g-3 mt-4">
                    <?php foreach ($stats as $stat): ?>
                        <div class="col-4">
                            <div class="vk-home-stat">
                                <strong><?= e($stat['value']) ?></strong>
                                <span><?= e($stat['label']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-5" data-aos="fade-left" data-aos-duration="700" data-aos-delay="100">
                <div class="vk-home-hero-panel">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
                        <div>
                            <span class="small text-uppercase fw-semibold">Live workflow</span>
                            <h2 class="h4 mb-0">Service operations</h2>
                        </div>
                        <span class="vk-home-pulse" aria-hidden="true"></span>
                    </div>
                    <div class="vk-home-workflow">
                        <div class="vk-home-workflow-item">
                            <span><i data-lucide="clipboard-check"></i></span>
                            <div>
                                <strong>Online booking</strong>
                                <small>Capture service type, location, contact, and notes.</small>
                            </div>
                        </div>
                        <div class="vk-home-workflow-item">
                            <span><i data-lucide="user-check"></i></span>
                            <div>
                                <strong>Assign technician</strong>
                                <small>Route jobs to the right field staff.</small>
                            </div>
                        </div>
                        <div class="vk-home-workflow-item">
                            <span><i data-lucide="receipt"></i></span>
                            <div>
                                <strong>Invoice and track</strong>
                                <small>Keep job history, billing, and customer updates clear.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($localKeywords): ?>
<section class="py-3 border-bottom border-opacity-10" style="border-color: var(--vk-pub-border) !important;">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="small text-muted">Trending local searches:</span>
            <?php foreach ($localKeywords as $kw): ?>
                <span class="badge text-bg-light border rounded-pill px-3 py-2"><?= e($kw) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="vk-pub-section py-5" id="services">
    <div class="container py-lg-4">
        <div class="row align-items-end g-3 mb-4 mb-lg-5">
            <div class="col-lg-7" data-aos="fade-up">
                <span class="vk-section-kicker">Services</span>
                <h2 class="vk-section-title mb-2">Service coverage built for repeat work.</h2>
                <p class="vk-section-lead mb-0">Organized, trackable support across IT, security, comfort systems, vehicles, and electrical maintenance.</p>
            </div>
            <div class="col-lg-5 text-lg-end">
                <a class="btn btn-outline-primary" href="<?= e(BASE_URL) ?>/book.php">Start a booking</a>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach (array_slice($services, 0, 6) as $si => $s):
                $sid = isset($s['id']) && $s['id'] !== null && (int) $s['id'] > 0 ? (int) $s['id'] : 0;
                $svcSlug = isset($s['slug']) ? trim((string) $s['slug']) : '';
                $cardHref = $sid > 0
                    ? ($svcSlug !== '' ? vk_web_service_public_path($svcSlug, $sid) : BASE_URL . '/service-details.php?id=' . $sid)
                    : BASE_URL . '/book.php?type=' . rawurlencode((string) $s['slug']);
                ?>
                <div class="col-md-6 col-xl-4" data-aos="fade-up" data-aos-duration="650" data-aos-delay="<?= (int) min(200, $si * 45) ?>">
                    <article class="vk-pub-service-card vk-modern-card p-4 position-relative">
                        <div class="vk-pub-icon-wrap mb-3">
                            <i data-lucide="<?= e((string) $s['lucide_icon']) ?>"></i>
                        </div>
                        <h3 class="mb-2"><?= e((string) $s['name']) ?></h3>
                        <p class="text-muted small mb-4"><?= e((string) $s['short_description']) ?></p>
                        <a class="btn btn-sm btn-outline-primary stretched-link" href="<?= e($cardHref) ?>"><?= $sid > 0 ? 'View details' : 'Book this' ?></a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vk-pub-section-alt py-5" id="about">
    <div class="container py-lg-4">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="vk-about-visual">
                    <img src="<?= e(base_url('assets/images/gallery/laptop-repair.svg')) ?>" alt="Laptop repair service illustration" loading="lazy">
                    <div class="vk-about-floating">
                        <i data-lucide="shield-check"></i>
                        <div>
                            <strong>Trusted local workflow</strong>
                            <span>Booking, field updates, billing, and history in one place.</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <span class="vk-section-kicker">About VK Network</span>
                <h2 class="vk-section-title mb-3">A practical service company with modern digital operations.</h2>
                <p class="text-muted">We combine field experience with a clear service process, so customers can request help, follow progress, and receive transparent billing without confusion.</p>
                <div class="row g-3 mt-2">
                    <div class="col-sm-6">
                        <div class="vk-feature-mini">
                            <i data-lucide="map-pin"></i>
                            <strong>Local coverage</strong>
                            <span>Jaffna, Kilinochchi, Vavuniya, Mullaitivu, and nearby areas.</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="vk-feature-mini">
                            <i data-lucide="badge-check"></i>
                            <strong>Accountable support</strong>
                            <span>Service tracking, technician notes, estimates, and invoice history.</span>
                        </div>
                    </div>
                </div>
                <a class="btn btn-primary mt-4" href="<?= e(BASE_URL) ?>/portfolio.php">See completed work</a>
            </div>
        </div>
    </div>
</section>

<section class="vk-pub-section py-5" id="team">
    <div class="container py-lg-4">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="vk-section-kicker">Staff &amp; Owner Portfolio</span>
            <h2 class="vk-section-title mb-2">Meet the team behind the service.</h2>
            <p class="vk-section-lead mx-auto mb-0">A sample staff and owner portfolio page for demonstrating the public UI.</p>
        </div>
        <div class="row g-4">
            <?php foreach ($teamMembers as $i => $member): ?>
                <div class="col-md-6 col-xl-3" data-aos="fade-up" data-aos-delay="<?= (int) min(180, $i * 55) ?>">
                    <article class="vk-team-card h-100">
                        <img src="<?= e((string) $member['image']) ?>" alt="<?= e((string) $member['name']) ?>" loading="lazy">
                        <div class="vk-team-card-body">
                            <span class="badge text-bg-primary mb-2"><?= e((string) $member['role']) ?></span>
                            <h3><?= e((string) $member['name']) ?></h3>
                            <p><?= e((string) $member['description']) ?></p>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <?php foreach ($member['skills'] as $skill): ?>
                                    <span class="badge rounded-pill text-bg-light border"><?= e((string) $skill) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex justify-content-center gap-2">
                                <a class="vk-social-btn" href="<?= e(BASE_URL) ?>/index.php/staff" aria-label="View <?= e((string) $member['name']) ?> profile"><i data-lucide="user-round"></i></a>
                                <a class="vk-social-btn" href="<?= e(BASE_URL) ?>/book.php" aria-label="Book service with VK Network"><i data-lucide="calendar-plus"></i></a>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vk-pub-section-alt py-5">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
            <div>
                <span class="vk-section-kicker">Local SEO pages</span>
                <h2 class="vk-section-title mb-0">Popular local pages</h2>
            </div>
            <span class="small text-muted">Auto SEO landing links</span>
        </div>
        <div class="row g-3">
            <?php
            $rawLoc = (string) (vk_app_setting('seo_locations', 'jaffna,vavuniya,kilinochchi') ?? 'jaffna,vavuniya,kilinochchi');
            $rawSvc = (string) (vk_app_setting('seo_service_slugs', 'computer-repair,laptop-repair,printer-repair,it-service') ?? 'computer-repair,laptop-repair,printer-repair,it-service');
            $locs = array_values(array_filter(array_map(static fn(string $v): string => strtolower(trim($v)), explode(',', $rawLoc))));
            $svcs = array_values(array_filter(array_map(static fn(string $v): string => strtolower(trim($v)), explode(',', $rawSvc))));
            $locs = $locs ?: ['jaffna', 'vavuniya', 'kilinochchi'];
            $svcs = $svcs ?: ['computer-repair', 'laptop-repair', 'printer-repair', 'it-service'];
            $localLanding = [];
            foreach ($locs as $ll) {
                foreach ($svcs as $ss) {
                    $localLanding[] = [
                        'slug' => $ss . '-' . $ll,
                        'label' => ucwords(str_replace('-', ' ', $ss . ' ' . $ll)),
                    ];
                }
            }
            foreach (array_slice($localLanding, 0, 8) as $lp): ?>
                <div class="col-12 col-md-6 col-lg-3">
                    <a class="vk-local-link" href="<?= e(BASE_URL . '/service/' . $lp['slug']) ?>">
                        <strong><?= e($lp['label']) ?></strong>
                        <span>View local offer</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vk-home-cta py-5">
    <div class="container text-center" data-aos="fade-up">
        <h2 class="vk-section-title mb-3">Need a technician today?</h2>
        <p class="vk-section-lead mx-auto mb-4">Send a request online and keep every step trackable from booking to invoice.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a class="btn btn-primary btn-lg px-4" href="<?= e(BASE_URL) ?>/book.php">Book online</a>
            <a class="btn btn-outline-secondary btn-lg px-4" href="<?= e(BASE_URL) ?>/track.php">Track status</a>
        </div>
    </div>
</section>

<a href="#top" class="vk-back-to-top" aria-label="Back to top"><i data-lucide="arrow-up"></i></a>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
