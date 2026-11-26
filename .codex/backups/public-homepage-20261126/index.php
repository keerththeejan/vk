<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/init_public.php';
require_once __DIR__ . '/includes/staff_model.php';

$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
if (preg_match('#/index\.php/staff/?$#i', $requestPath)) {
    require __DIR__ . '/staff.php';
    exit;
}

$services = [];
$teamMembers = [];
try {
    $pdo = db();
    if (db_table_exists($pdo, 'web_services')) {
        $services = function_exists('vk_cache_remember')
            ? vk_cache_remember('public_home_services_v1', 120, static function () use ($pdo) {
                return $pdo->query(
                    'SELECT id, slug, name, short_description, lucide_icon FROM web_services WHERE active = 1 ORDER BY sort_order ASC, id ASC'
                )->fetchAll();
            })
            : $pdo->query(
                'SELECT id, slug, name, short_description, lucide_icon FROM web_services WHERE active = 1 ORDER BY sort_order ASC, id ASC'
            )->fetchAll();
    }
    if (db_table_exists($pdo, 'staff')) {
        $teamMembers = vk_staff_get_public_list($pdo, 8);
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

$teamFallbackMembers = [
    [
        'name' => 'Vijay Keerththeejan',
        'role' => 'Owner',
        'image' => 'assets/images/staff/owner.svg',
        'description' => 'Leads service strategy, networking solutions, AI systems, and customer experience.',
        'skills' => 'Networking, AI Systems, Web Development',
        'experience' => '10+ years',
        'years_experience' => 10,
        'completed_projects' => 620,
        'specialization' => 'Enterprise networks, AI systems, and service automation',
        'certifications' => 'Network Engineering, AI Automation, Field Service Leadership',
        'status' => 'active',
        'email' => '',
        'phone' => '0778870135',
        'social_links' => 'Profile|' . BASE_URL . '/index.php/staff',
    ],
    [
        'name' => 'John Silva',
        'role' => 'Technician',
        'image' => 'assets/images/staff/staff1.svg',
        'description' => 'Handles diagnostics, hardware repairs, printer service, and site visits.',
        'skills' => 'Hardware Repair, Printer Service, CCTV',
        'experience' => '7+ years',
        'years_experience' => 7,
        'completed_projects' => 410,
        'specialization' => 'Hardware diagnostics and on-site technical support',
        'certifications' => 'Hardware Repair, CCTV Installation, Printer Maintenance',
        'status' => 'active',
        'email' => '',
        'phone' => '0778870135',
        'social_links' => 'Book|' . BASE_URL . '/book.php',
    ],
    [
        'name' => 'Nimal Perera',
        'role' => 'System Admin',
        'image' => 'assets/images/staff/staff2.svg',
        'description' => 'Maintains servers, network reliability, backup routines, and security checks.',
        'skills' => 'Servers, Network Management, Security',
        'experience' => '6+ years',
        'years_experience' => 6,
        'completed_projects' => 280,
        'specialization' => 'Server reliability and secure network operations',
        'certifications' => 'Server Administration, Network Security, Backup Systems',
        'status' => 'active',
        'email' => '',
        'phone' => '0778870135',
        'social_links' => 'Profile|' . BASE_URL . '/index.php/staff',
    ],
    [
        'name' => 'Nisha Raj',
        'role' => 'Customer Support',
        'image' => 'assets/images/staff/nisha.svg',
        'description' => 'Coordinates bookings, customer updates, tracking requests, and follow ups.',
        'skills' => 'Support, Scheduling, Customer Care',
        'experience' => '5+ years',
        'years_experience' => 5,
        'completed_projects' => 350,
        'specialization' => 'Customer care, scheduling, and service coordination',
        'certifications' => 'Customer Support, Service Operations, CRM Coordination',
        'status' => 'active',
        'email' => '',
        'phone' => '0778870135',
        'social_links' => 'Book|' . BASE_URL . '/book.php',
    ],
];


if (!$teamMembers) {
    $teamMembers = $teamFallbackMembers;
}

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
// Swiper CSS/JS are lazy-loaded by public-site.js when testimonials enter the viewport.
$homeHeroTitle = vk_app_setting('hero_title', 'Smart Service Solutions for modern Homes & Businesses');
$homeHeroSubtitle = vk_app_setting('hero_subtitle', 'Book repairs, installations, maintenance, and technical support with real-time tracking and intelligent workflow management.');
$homePrimaryCtaText = vk_app_setting('hero_primary_cta_text', 'Book a Service');
$homePrimaryCtaUrl = vk_setting_url(vk_app_setting('hero_primary_cta_url', '/book.php'), BASE_URL . '/book.php');
$homeSecondaryCtaText = vk_app_setting('hero_secondary_cta_text', 'Track Your Service');
$homeSecondaryCtaUrl = vk_setting_url(vk_app_setting('hero_secondary_cta_url', '/track.php'), BASE_URL . '/track.php');
$servicesSectionTitle = vk_app_setting('services_section_title', 'Expert solutions with fast booking and transparent tracking.');
$servicesSectionSubtitle = vk_app_setting('services_section_subtitle', 'Every service is backed by qualified technicians, instant booking, and a seamless customer experience.');
$testimonialsTitle = vk_app_setting('testimonials_title', 'Trusted by customers across Sri Lanka.');
$homeStats = vk_settings_json('home_stats_json', []);
if (!$homeStats) {
    $homeStats = [
        ['value' => '25K+', 'label' => 'Happy Customers'],
        ['value' => '98.6%', 'label' => 'Success Rate'],
        ['value' => '4.9', 'label' => 'Customer Rating'],
        ['value' => '247', 'label' => 'Online Technicians'],
    ];
}

require __DIR__ . '/includes/public_header.php';
?>
<section class="vk-hero-premium vk-home-hero" id="top">
    <div class="vk-hero-shine"></div>
    <div class="vk-hero-grain"></div>
    <div class="vk-cursor-glow" aria-hidden="true"></div>
    <div class="vk-hero-particles" aria-hidden="true">
        <span></span><span></span><span></span><span></span><span></span>
    </div>
    <div class="container vk-hero-inner">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-7" data-aos="fade-right" data-aos-duration="700">
                <div class="vk-hero-topline d-flex flex-wrap align-items-center gap-3 mb-3">
                    <span class="vk-hero-trust px-3 py-2 rounded-pill">Trusted by 25K+ customers</span>
                    <span class="vk-hero-badge-mini d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill">Live operations &bull; Premium SLA</span>
                </div>
                <span class="vk-hero-eyebrow d-inline-flex align-items-center gap-2">
                    <i data-lucide="sparkles"></i>
                    Premium enterprise service platform
                </span>
                <h1 class="vk-hero-title"><?= e((string) $homeHeroTitle) ?></h1>
                <p class="vk-hero-lead"><?= e((string) $homeHeroSubtitle) ?></p>
                <div class="vk-hero-actions d-flex flex-wrap gap-3">
                    <a class="vk-btn-hero-primary btn btn-lg px-4" href="<?= e($homePrimaryCtaUrl) ?>">
                        <span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="calendar-plus"></i></span>
                        <?= e((string) $homePrimaryCtaText) ?>
                    </a>
                    <a class="vk-btn-hero-secondary btn btn-lg px-4" href="<?= e($homeSecondaryCtaUrl) ?>">
                        <span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="search"></i></span>
                        <?= e((string) $homeSecondaryCtaText) ?>
                    </a>
                </div>
                <div class="vk-hero-badges d-flex flex-wrap gap-2 mt-4" data-aos="fade-up" data-aos-duration="700" data-aos-delay="150">
                    <span class="vk-hero-badge">Fast Response</span>
                    <span class="vk-hero-badge">Certified Experts</span>
                    <span class="vk-hero-badge">Secure &amp; Safe</span>
                    <span class="vk-hero-badge">Satisfaction Guarantee</span>
                </div>
                <div class="row row-cols-1 row-cols-sm-2 g-3 mt-4" data-aos="fade-up" data-aos-duration="700" data-aos-delay="200">
                    <?php foreach (array_slice($homeStats, 0, 4) as $stat): ?>
                        <div class="col">
                            <div class="vk-home-stat">
                                <strong><?= e((string) ($stat['value'] ?? '')) ?></strong>
                                <span><?= e((string) ($stat['label'] ?? '')) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="vk-brand-strip d-flex flex-wrap align-items-center gap-3 gap-sm-4 mt-5" data-aos="fade-up" data-aos-duration="700" data-aos-delay="260">
                    <span class="vk-brand-pill">Dell</span>
                    <span class="vk-brand-pill">HP</span>
                    <span class="vk-brand-pill">Lenovo</span>
                    <span class="vk-brand-pill">ASUS</span>
                    <span class="vk-brand-pill">Acer</span>
                    <span class="vk-brand-pill">Canon</span>
                    <span class="vk-brand-pill">Samsung</span>
                </div>
            </div>
            <div class="col-lg-5" data-aos="fade-left" data-aos-duration="700" data-aos-delay="100">
                <div class="vk-dashboard-card p-4 p-xl-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <span class="small text-uppercase text-light opacity-75">Live operations</span>
                            <h2 class="h5 text-white mb-0">Service tracking dashboard</h2>
                        </div>
                        <span class="badge vk-badge-live">Live</span>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="vk-mini-kpi p-3">
                                <strong>128</strong>
                                <span>Active Bookings</span>
                                <small class="text-success">+12% today</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vk-mini-kpi p-3">
                                <strong>48</strong>
                                <span>In Progress</span>
                                <small class="text-info">Live tracking</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vk-mini-kpi p-3">
                                <strong>2,548</strong>
                                <span>Completed</span>
                                <small class="text-success">+8% today</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vk-mini-kpi p-3">
                                <strong>Rs. 2.48M</strong>
                                <span>Revenue</span>
                                <small class="text-success">+18.6%</small>
                            </div>
                        </div>
                    </div>
                    <div class="vk-track-panel p-3 mb-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="vk-track-avatar"><i data-lucide="user-check"></i></div>
                            <div>
                                <strong>Technician: Asela</strong>
                                <span>En route to site</span>
                            </div>
                        </div>
                        <div class="vk-track-map"></div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <span class="small text-muted">ETA</span>
                                <strong>18 min</strong>
                            </div>
                            <div class="vk-track-progress">
                                <div class="vk-track-progress-bar" style="width: 62%;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="vk-ai-panel p-3 d-flex flex-column gap-3 mb-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="vk-ai-badge">AI Assistant</span>
                            <span class="text-success small">Online</span>
                        </div>
                        <div class="vk-ai-message">"Your service is scheduled and the technician is 18 minutes away. You can reschedule or review the estimate anytime."</div>
                        <a class="btn btn-sm btn-outline-light" href="<?= e(BASE_URL) ?>/track.php">View service details</a>
                    </div>
                    <div class="vk-rating-card p-3 d-flex align-items-center gap-3">
                        <div class="vk-rating-score">
                            <strong>4.9</strong>
                            <span>/5</span>
                        </div>
                        <div>
                            <div class="vk-stars">
                                <i data-lucide="star"></i>
                                <i data-lucide="star"></i>
                                <i data-lucide="star"></i>
                                <i data-lucide="star"></i>
                                <i data-lucide="star"></i>
                            </div>
                            <span class="small text-light-opacity">2.4K reviews</span>
                        </div>
                    </div>
                    <div class="vk-dashboard-footer text-center mt-3">25+ services available</div>
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
        <div class="row justify-content-between align-items-end g-3 mb-5">
            <div class="col-lg-7" data-aos="fade-up">
                <span class="vk-section-kicker">Our Premium Services</span>
                <h2 class="vk-section-title mb-2"><?= e((string) $servicesSectionTitle) ?></h2>
                <p class="vk-section-lead mb-0"><?= e((string) $servicesSectionSubtitle) ?></p>
            </div>
            <div class="col-lg-4 text-lg-end" data-aos="fade-up" data-aos-delay="100">
                <a class="btn btn-outline-primary btn-lg" href="<?= e(BASE_URL) ?>/service.php">View all services</a>
            </div>
        </div>
        <div class="row g-4">
            <?php
            $homeServices = [
                ['name' => 'Computer Repair', 'description' => 'Laptop, desktop, OS, upgrade, virus cleanup, and diagnostics.', 'icon' => 'cpu', 'url' => BASE_URL . '/book.php?type=computer'],
                ['name' => 'CCTV Installation', 'description' => 'Camera setup, DVR/NVR configuration, cabling, and remote access.', 'icon' => 'video', 'url' => BASE_URL . '/book.php?type=cctv'],
                ['name' => 'AC Repair', 'description' => 'Air conditioner diagnostics, cleaning, and fast cooling restoration.', 'icon' => 'wind', 'url' => BASE_URL . '/book.php?type=ac'],
                ['name' => 'Electrical Service', 'description' => 'Residential and commercial electrical troubleshooting, wiring, and safety.', 'icon' => 'zap', 'url' => BASE_URL . '/book.php?type=electrical'],
                ['name' => 'Printer Repair', 'description' => 'Printer jams, refills, cartridges, rollers, and office maintenance.', 'icon' => 'printer', 'url' => BASE_URL . '/book.php?type=printer'],
                ['name' => 'Vehicle Support', 'description' => 'Emergency breakdown support, vehicle bookings, and technician dispatch.', 'icon' => 'truck', 'url' => BASE_URL . '/book.php?type=automobile'],
                ['name' => 'Networking Solutions', 'description' => 'Wi-Fi, switches, routing, and secure network setups for offices.', 'icon' => 'wifi', 'url' => BASE_URL . '/book.php?type=networking'],
                ['name' => 'Smart Home Setup', 'description' => 'Automation, sensors, CCTV control, and connected home device installation.', 'icon' => 'home', 'url' => BASE_URL . '/book.php?type=smart-home'],
            ];
            foreach ($homeServices as $si => $service): ?>
                <div class="col-sm-6 col-xl-3" data-aos="fade-up" data-aos-duration="700" data-aos-delay="<?= (int) min(240, $si * 45) ?>">
                    <article class="vk-service-card p-4 h-100 position-relative">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div class="vk-service-card-icon"><i data-lucide="<?= e($service['icon']) ?>"></i></div>
                            <span class="vk-service-availability">Available</span>
                        </div>
                        <h3 class="h5 mb-2"><?= e($service['name']) ?></h3>
                        <p class="text-muted small mb-3"><?= e($service['description']) ?></p>
                        <div class="vk-service-meta d-flex flex-wrap gap-2 mb-4">
                            <span><i data-lucide="timer"></i> 24h response</span>
                            <span><i data-lucide="trending-up"></i> 98% success</span>
                        </div>
                        <a class="stretched-link text-decoration-none text-primary fw-semibold mt-auto d-inline-flex align-items-center" href="<?= e($service['url']) ?>">Book now <i data-lucide="arrow-right" class="ms-1"></i></a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vk-pub-section-alt py-5" id="about">
    <div class="container py-lg-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <div class="vk-analytics-card p-4 p-xl-5">
                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <span class="vk-section-kicker">Business overview</span>
                            <h2 class="vk-section-title mb-1">Track bookings, completion, and customer satisfaction.</h2>
                        </div>
                        <span class="badge bg-white text-dark text-uppercase px-3 py-2">Global metrics</span>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="vk-analytics-kpi"><strong>5,482</strong><span>Total bookings</span></div>
                        </div>
                        <div class="col-6">
                            <div class="vk-analytics-kpi"><strong>4,312</strong><span>Completed jobs</span></div>
                        </div>
                        <div class="col-6">
                            <div class="vk-analytics-kpi"><strong>854</strong><span>In progress</span></div>
                        </div>
                        <div class="col-6">
                            <div class="vk-analytics-kpi"><strong>96</strong><span>Cancelled</span></div>
                        </div>
                    </div>
                    <div class="vk-analytics-chart mb-4">
                        <div class="vk-chart-line"></div>
                        <div class="vk-chart-axis d-flex justify-content-between text-muted small">
                            <span>Mon</span><span>Wed</span><span>Fri</span><span>Sun</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="vk-radial-progress">
                            <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="50"></circle><circle cx="60" cy="60" r="50"></circle></svg>
                            <span>92%</span>
                            <small>Customer satisfaction</small>
                        </div>
                        <div class="vk-radial-progress vk-radial-progress--secondary">
                            <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="50"></circle><circle cx="60" cy="60" r="50"></circle></svg>
                            <span>87%</span>
                            <small>First-time fix rate</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="row g-3">
                    <?php $whyCards = [
                        ['title' => '24/7 Support', 'icon' => 'headphones', 'colorClass' => 'vk-card-glow-blue'],
                        ['title' => 'Fast Response', 'icon' => 'clock', 'colorClass' => 'vk-card-glow-cyan'],
                        ['title' => 'Verified Technicians', 'icon' => 'badge-check', 'colorClass' => 'vk-card-glow-purple'],
                        ['title' => 'Secure Payments', 'icon' => 'shield-check', 'colorClass' => 'vk-card-glow-green'],
                        ['title' => 'Real-time Updates', 'icon' => 'zap', 'colorClass' => 'vk-card-glow-sky'],
                        ['title' => 'Warranty Protection', 'icon' => 'award', 'colorClass' => 'vk-card-glow-pink'],
                    ];
                    foreach ($whyCards as $card): ?>
                        <div class="col-md-6">
                            <article class="vk-why-card p-4 <?= e($card['colorClass']) ?>">
                                <div class="vk-why-icon mb-3"><i data-lucide="<?= e($card['icon']) ?>"></i></div>
                                <h3 class="h6 mb-2"><?= e($card['title']) ?></h3>
                                <p class="small text-muted mb-0">Premium quality, clear communication, and secure service delivery.</p>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="vk-pub-section py-5" id="pricing">
    <div class="container py-lg-4">
        <div class="row align-items-end justify-content-between g-3 mb-5">
            <div class="col-lg-7" data-aos="fade-up">
                <span class="vk-section-kicker">Transparent pricing</span>
                <h2 class="vk-section-title mb-2">Premium service plans built for homes and growing teams.</h2>
                <p class="vk-section-lead mb-0">Start with a one-time visit or choose an ongoing maintenance plan with priority dispatch.</p>
            </div>
            <div class="col-lg-4 text-lg-end" data-aos="fade-up" data-aos-delay="100">
                <a class="btn btn-outline-primary btn-lg" href="<?= e(BASE_URL) ?>/book.php">Request estimate</a>
            </div>
        </div>
        <div class="row g-4">
            <?php $pricingPlans = [
                ['name' => 'Rapid Visit', 'price' => 'From Rs. 2,500', 'icon' => 'zap', 'items' => ['Diagnosis and estimate', 'Same-day scheduling', 'Live job updates']],
                ['name' => 'Business Care', 'price' => 'Custom SLA', 'icon' => 'building-2', 'items' => ['Priority technicians', 'Monthly maintenance', 'Asset and warranty notes']],
                ['name' => 'Smart Install', 'price' => 'Project quote', 'icon' => 'router', 'items' => ['CCTV and networking', 'Smart home setup', 'Post-install support']],
            ];
            foreach ($pricingPlans as $pi => $plan): ?>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?= (int) ($pi * 80) ?>">
                    <article class="vk-service-card vk-pricing-card p-4 h-100">
                        <div class="vk-service-card-icon mb-3"><i data-lucide="<?= e($plan['icon']) ?>"></i></div>
                        <h3 class="h5 mb-2"><?= e($plan['name']) ?></h3>
                        <strong class="d-block h4 mb-3"><?= e($plan['price']) ?></strong>
                        <ul class="list-unstyled d-grid gap-2 mb-4">
                            <?php foreach ($plan['items'] as $item): ?>
                                <li class="d-flex align-items-center gap-2 text-muted small"><i data-lucide="check-circle"></i><?= e($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a class="btn btn-outline-light w-100" href="<?= e(BASE_URL) ?>/book.php">Choose plan</a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vk-pub-section vk-team-showcase-section py-5" id="team">
    <div class="container py-lg-4">
        <div class="row justify-content-between align-items-end g-3 mb-5">
            <div class="col-lg-7" data-aos="fade-up">
                <span class="vk-section-kicker">Staff &amp; Owner Portfolio</span>
                <h2 class="vk-section-title mb-2">Meet Our Expert Team</h2>
                <p class="vk-section-lead mb-0">Certified professionals dedicated to delivering exceptional service</p>
            </div>
            <div class="col-lg-4 text-lg-end" data-aos="fade-up" data-aos-delay="100">
                <a class="btn btn-outline-primary btn-lg" href="<?= e(BASE_URL) ?>/index.php/staff">View full portfolio</a>
            </div>
        </div>

        <div class="vk-team-showcase-grid">
            <?php foreach ($teamMembers as $ti => $member): ?>
                <?php
                $memberId = (int) ($member['id'] ?? (9000 + $ti));
                $isOwner = vk_staff_is_owner($member);
                $skills = array_slice(vk_staff_skills_list((string) ($member['skills'] ?? '')), 0, 4);
                $certs = array_slice(vk_staff_certifications_list((string) ($member['certifications'] ?? '')), 0, 5);
                $socials = vk_staff_social_links((string) ($member['social_links'] ?? ''));
                $image = vk_staff_display_image($member, true);
                $status = vk_staff_normalize_status((string) ($member['status'] ?? 'active'));
                $phoneDigits = preg_replace('/\D+/', '', (string) ($member['phone'] ?? '0778870135'));
                $whatsapp = $phoneDigits !== '' ? 'https://wa.me/94' . ltrim(preg_replace('/^94/', '', $phoneDigits) ?? '', '0') : $waHref ?? '#';
                $modalId = 'teamProfileModal' . $memberId;
                $years = (int) ($member['years_experience'] ?? 0);
                $projects = (int) ($member['completed_projects'] ?? 0);
                ?>
                <article class="vk-team-showcase-card <?= $isOwner ? 'vk-team-showcase-card--owner' : '' ?>" data-aos="fade-up" data-aos-delay="<?= (int) min(280, $ti * 60) ?>">
                    <div class="vk-team-card-glow"></div>
                    <div class="vk-team-photo-wrap">
                        <img src="<?= e($image) ?>" alt="<?= e((string) ($member['name'] ?? 'Team member')) ?>" loading="lazy" decoding="async" width="420" height="420" onerror="<?= vk_staff_image_onerror_attr() ?>">
                        <span class="vk-team-status vk-team-status-<?= e($status) ?>"><?= e(vk_staff_status_label($status)) ?></span>
                        <?php if ($isOwner): ?>
                            <span class="vk-founder-badge"><i data-lucide="badge-check"></i> Verified Founder</span>
                        <?php endif; ?>
                    </div>
                    <div class="vk-team-card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <h3><?= e((string) ($member['name'] ?? 'Team member')) ?></h3>
                                <p class="vk-team-role"><?= e((string) ($member['role'] ?? 'Service professional')) ?></p>
                            </div>
                            <span class="vk-team-exp"><?= e((string) (($member['experience'] ?? '') ?: ($years ? $years . '+ years' : 'Certified'))) ?></span>
                        </div>
                        <p class="vk-team-desc"><?= e((string) (($member['specialization'] ?? '') ?: ($member['description'] ?? 'Experienced VK Network service professional.'))) ?></p>
                        <div class="vk-team-tags">
                            <?php foreach ($skills ?: ['Service', 'Support'] as $skill): ?>
                                <span><?= e($skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="vk-team-stats">
                            <span><strong><?= $years ?: 5 ?>+</strong> Years</span>
                            <span><strong><?= $projects ?: 120 ?>+</strong> Projects</span>
                        </div>
                        <div class="vk-team-actions">
                            <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="modal" data-bs-target="#<?= e($modalId) ?>">View profile</button>
                            <a class="btn btn-sm btn-primary" href="<?= e(BASE_URL) ?>/book.php">Book</a>
                            <a class="vk-team-icon-btn" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp <?= e((string) ($member['name'] ?? 'team member')) ?>"><i data-lucide="message-circle"></i></a>
                        </div>
                        <?php if ($socials): ?>
                            <div class="vk-team-socials">
                                <?php foreach (array_slice($socials, 0, 3) as $social): ?>
                                    <a href="<?= e($social['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($social['label']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>

                <div class="modal fade vk-team-modal" id="<?= e($modalId) ?>" tabindex="-1" aria-labelledby="<?= e($modalId) ?>Label" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header border-0">
                                <div>
                                    <span class="vk-section-kicker mb-1"><?= $isOwner ? 'Founder Profile' : 'Team Profile' ?></span>
                                    <h2 class="modal-title h4" id="<?= e($modalId) ?>Label"><?= e((string) ($member['name'] ?? 'Team member')) ?></h2>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body pt-0">
                                <div class="row g-4">
                                    <div class="col-md-5">
                                        <img class="vk-team-modal-img" src="<?= e($image) ?>" alt="<?= e((string) ($member['name'] ?? 'Team member')) ?>" loading="lazy" decoding="async" width="420" height="420" onerror="<?= vk_staff_image_onerror_attr() ?>">
                                        <div class="vk-team-modal-metrics">
                                            <span><strong><?= $years ?: 5 ?>+</strong> Years experience</span>
                                            <span><strong><?= $projects ?: 120 ?>+</strong> Completed projects</span>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <p class="vk-team-modal-role"><?= e((string) ($member['role'] ?? 'Service professional')) ?></p>
                                        <p class="vk-team-modal-bio"><?= e((string) ($member['description'] ?? 'Certified VK Network professional focused on reliable, transparent service delivery.')) ?></p>
                                        <h3 class="h6 text-white">Certifications</h3>
                                        <div class="vk-team-tags mb-3">
                                            <?php foreach ($certs ?: ['Service Quality', 'Safety Practices'] as $cert): ?>
                                                <span><?= e($cert) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <h3 class="h6 text-white">Skill strength</h3>
                                        <div class="vk-skill-bars">
                                            <?php foreach (array_slice($skills ?: ['Service', 'Diagnostics', 'Support'], 0, 3) as $si => $skill): ?>
                                                <div>
                                                    <span><?= e($skill) ?></span>
                                                    <div><i style="width: <?= (int) (92 - ($si * 8)) ?>%;"></i></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2 mt-4">
                                            <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/book.php">Book this team</a>
                                            <a class="btn btn-outline-light" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                                            <?php if (!empty($member['email'])): ?>
                                                <a class="btn btn-outline-light" href="mailto:<?= e((string) $member['email']) ?>">Email</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vk-pub-section py-5" id="testimonials">
    <div class="container py-lg-4">
        <div class="vk-testimonials-head text-center mb-5" data-aos="fade-up">
            <span class="vk-section-kicker">Testimonials</span>
            <h2 class="vk-section-title mb-2"><?= e((string) $testimonialsTitle) ?></h2>
            <p class="vk-section-lead mx-auto mb-0">Premium reviews from customers who have experienced fast, transparent service delivery.</p>
        </div>
        <div class="vk-testimonials-shell" data-aos="fade-up" data-aos-duration="700">
            <button class="vk-testimonial-nav vk-testimonial-prev" type="button" aria-label="Previous testimonial">
                <i data-lucide="chevron-left"></i>
            </button>
            <div class="swiper vk-testimonials-swiper">
                <div class="swiper-wrapper">
                    <?php $testimonials = [
                        ['name' => 'Amali Perera', 'role' => 'Homeowner', 'text' => 'VK Network fixed my laptop and installed CCTV in one day. Tracking made every step clear.', 'avatar' => 'assets/images/default-avatar.svg'],
                        ['name' => 'Nalin Fernando', 'role' => 'Business Owner', 'text' => 'Professional, fast, and secure. The technician arrived on time and solved our outage quickly.', 'avatar' => 'assets/images/default-avatar.svg'],
                        ['name' => 'Samantha Jayasekara', 'role' => 'IT Manager', 'text' => 'Great support for our office printer and AC systems with reliable follow-through.', 'avatar' => 'assets/images/default-avatar.svg'],
                        ['name' => 'Dilani Rajapaksha', 'role' => 'Retail Operator', 'text' => 'The booking experience felt premium, and the live updates helped us plan the service window.', 'avatar' => 'assets/images/default-avatar.svg'],
                        ['name' => 'Kavin Suresh', 'role' => 'Startup Founder', 'text' => 'Their networking setup was neat, documented, and completed without disrupting our team.', 'avatar' => 'assets/images/default-avatar.svg'],
                        ['name' => 'Ramesh Kumar', 'role' => 'Facilities Lead', 'text' => 'Clear estimates, fast dispatch, and a polished service experience from booking to review.', 'avatar' => 'assets/images/default-avatar.svg'],
                    ];
                    foreach ($testimonials as $review): ?>
                        <div class="swiper-slide">
                            <article class="vk-testimonial-card">
                                <div class="vk-testimonial-quote" aria-hidden="true"><i data-lucide="quote"></i></div>
                                <div class="vk-testimonial-stars" aria-label="5 out of 5 stars">
                                    <?php for ($s = 0; $s < 5; $s++): ?>
                                        <i data-lucide="star"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="vk-testimonial-copy">"<?= e($review['text']) ?>"</p>
                                <div class="vk-testimonial-person">
                                    <img src="<?= e(base_url($review['avatar'])) ?>" alt="<?= e($review['name']) ?>" class="vk-testimonial-avatar-img" width="44" height="44" loading="lazy" decoding="async">
                                    <div>
                                        <strong><?= e($review['name']) ?> <span class="vk-verified-badge" aria-label="Verified customer"><i data-lucide="badge-check"></i></span></strong>
                                        <span><?= e($review['role']) ?></span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="vk-testimonial-pagination swiper-pagination"></div>
            </div>
            <button class="vk-testimonial-nav vk-testimonial-next" type="button" aria-label="Next testimonial">
                <i data-lucide="chevron-right"></i>
            </button>
        </div>
    </div>
</section>

<section class="vk-pub-section-alt py-5" id="how-it-works">
    <div class="container py-lg-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7" data-aos="fade-right">
                <span class="vk-section-kicker">How it works</span>
                <h2 class="vk-section-title mb-4">Three steps to service success.</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <div class="vk-step-card p-4 d-flex gap-3 align-items-start">
                            <div class="vk-step-icon"><i data-lucide="layers"></i></div>
                            <div>
                                <strong>Select Service</strong>
                                <p class="text-muted mb-0">Choose the right service category and share your location details in a few taps.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="vk-step-card p-4 d-flex gap-3 align-items-start">
                            <div class="vk-step-icon"><i data-lucide="map-pin"></i></div>
                            <div>
                                <strong>Track Technician</strong>
                                <p class="text-muted mb-0">Follow the technician route, ETA, and progress from your dashboard.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="vk-step-card p-4 d-flex gap-3 align-items-start">
                            <div class="vk-step-icon"><i data-lucide="check-circle"></i></div>
                            <div>
                                <strong>Complete &amp; Review</strong>
                                <p class="text-muted mb-0">Accept the finished job, pay securely, and leave feedback instantly.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5" data-aos="fade-left">
                <div class="vk-ready-card p-4 p-lg-5 rounded-4 text-center">
                    <span class="vk-section-kicker">Ready to get started?</span>
                    <h3 class="vk-ready-title mb-3">Launch your service with a premium support experience.</h3>
                    <p class="text-muted mb-4">Book now, track live, and keep everything transparent with real-time status updates.</p>
                    <div class="vk-cta-trust d-flex flex-wrap justify-content-center gap-2 mb-4">
                        <span><i data-lucide="users"></i> 25K+ customers</span>
                        <span><i data-lucide="clock-3"></i> 18 min avg response</span>
                        <span><i data-lucide="shield-check"></i> Verified technicians</span>
                    </div>
                    <a class="btn btn-primary btn-lg px-4" href="<?= e(BASE_URL) ?>/book.php">Book Now</a>
                </div>
            </div>
        </div>
    </div>
</section>

<a href="#top" class="vk-back-to-top" aria-label="Back to top"><i data-lucide="arrow-up"></i></a>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
