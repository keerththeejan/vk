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
$homeOverview = ['total' => null, 'active' => null, 'in_progress' => null, 'completed' => null, 'cancelled' => null, 'technicians' => null];
$homeBookingTrend = [];
try {
    $pdo = db();
    if (db_table_exists($pdo, 'web_services')) {
        $services = function_exists('vk_cache_remember')
            ? vk_cache_remember('public_home_services_v1', 120, static function () use ($pdo) {
                return $pdo->query(
                    'SELECT id, slug, name, short_description, lucide_icon, price_from FROM web_services WHERE active = 1 ORDER BY sort_order ASC, id ASC'
                )->fetchAll();
            })
            : $pdo->query(
                'SELECT id, slug, name, short_description, lucide_icon, price_from FROM web_services WHERE active = 1 ORDER BY sort_order ASC, id ASC'
            )->fetchAll();
    }
    if (db_table_exists($pdo, 'staff')) {
        $teamMembers = vk_staff_get_public_list($pdo, 8);
    }
    if (db_table_exists($pdo, 'web_bookings')) {
        $bookingCounts = $pdo->query("SELECT COUNT(*) AS total, SUM(status IN ('pending','in_progress')) AS active, SUM(status = 'in_progress') AS in_progress, SUM(status IN ('completed','delivered')) AS completed, SUM(status = 'cancelled') AS cancelled FROM web_bookings")->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['total', 'active', 'in_progress', 'completed', 'cancelled'] as $metric) {
            $homeOverview[$metric] = (int) ($bookingCounts[$metric] ?? 0);
        }
        $trendRows = $pdo->query('SELECT DATE(created_at) AS booking_day, COUNT(*) AS total FROM web_bookings WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)')->fetchAll(PDO::FETCH_ASSOC);
        $trendLookup = [];
        foreach ($trendRows as $trendRow) $trendLookup[(string) $trendRow['booking_day']] = (int) $trendRow['total'];
        for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
            $trendDate = date('Y-m-d', strtotime('-' . $dayOffset . ' days'));
            $homeBookingTrend[] = ['label' => date('D', strtotime($trendDate)), 'value' => $trendLookup[$trendDate] ?? 0];
        }
    }
    if (db_table_exists($pdo, 'technicians')) {
        $homeOverview['technicians'] = (int) $pdo->query('SELECT COUNT(*) FROM technicians WHERE active = 1')->fetchColumn();
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
if (!$homeStats && $homeOverview['total'] !== null) {
    $homeStats = [
        ['value' => number_format($homeOverview['total']), 'label' => 'Bookings received'],
        ['value' => number_format($homeOverview['completed']), 'label' => 'Completed bookings'],
        ['value' => number_format($homeOverview['active']), 'label' => 'Active bookings'],
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
                            <span class="small text-uppercase text-light opacity-75">Service overview</span>
                            <h2 class="h5 text-white mb-0">Service tracking dashboard</h2>
                        </div>
                        <span class="badge vk-badge-live">Updated from records</span>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="vk-mini-kpi p-3">
                                <strong><?= $homeOverview['active'] === null ? '—' : number_format($homeOverview['active']) ?></strong>
                                <span>Active Bookings</span>
                                <small class="text-success">Current status</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vk-mini-kpi p-3">
                                <strong><?= $homeOverview['in_progress'] === null ? '—' : number_format($homeOverview['in_progress']) ?></strong>
                                <span>In Progress</span>
                                <small class="text-info">Current status</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vk-mini-kpi p-3">
                                <strong><?= $homeOverview['completed'] === null ? '—' : number_format($homeOverview['completed']) ?></strong>
                                <span>Completed bookings</span>
                                <small class="text-success">All time</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vk-mini-kpi p-3">
                                <strong><?= $homeOverview['technicians'] === null ? '—' : number_format($homeOverview['technicians']) ?></strong>
                                <span>Active Technicians</span>
                                <small class="text-info">Current team</small>
                            </div>
                        </div>
                    </div>
                    <div class="vk-track-panel p-3 mb-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="vk-track-avatar"><i data-lucide="user-check"></i></div>
                            <div>
                                <strong>Service tracking</strong>
                                <span>Follow your booking status in real time</span>
                            </div>
                        </div>
                        <div class="vk-track-map"></div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div><span class="small text-muted">Updates</span><strong>Booking status</strong></div>
                            <a class="small text-info text-decoration-none" href="<?= e(BASE_URL) ?>/track.php">Track a booking <i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                    <div class="vk-ai-panel p-3 d-flex flex-column gap-3 mb-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="vk-ai-badge">Need an update?</span>
                            <span class="text-success small">Booking support</span>
                        </div>
                        <div class="vk-ai-message">Enter your booking reference to check the latest service status and technician updates.</div>
                        <a class="btn btn-sm btn-outline-light" href="<?= e(BASE_URL) ?>/track.php">View service details</a>
                    </div>
                    <div class="vk-rating-card p-3 d-flex align-items-center gap-3">
                        <div class="vk-track-avatar"><i data-lucide="shield-check"></i></div>
                        <div><strong>Local service, clear updates</strong><span class="d-block small text-light-opacity">Support for homes and businesses across Northern Sri Lanka</span></div>
                    </div>
                    <div class="vk-dashboard-footer text-center mt-3"><?= count($services) ?> configured service<?= count($services) === 1 ? '' : 's' ?> available</div>
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
            <?php foreach ($services as $si => $service):
                $serviceSlug = (string) ($service['slug'] ?? 'other');
                $bookingTypes = ['computer', 'printer', 'cctv', 'maintenance', 'automobile', 'ac', 'electrical', 'other'];
                $bookingType = in_array($serviceSlug, $bookingTypes, true) ? $serviceSlug : 'other';
                $serviceIcon = trim((string) ($service['lucide_icon'] ?? '')) ?: 'wrench';
                $serviceUrl = BASE_URL . '/book.php?type=' . rawurlencode($bookingType);
            ?>
                <div class="col-sm-6 col-xl-3" data-aos="fade-up" data-aos-duration="700" data-aos-delay="<?= (int) min(240, $si * 45) ?>">
                    <article class="vk-service-card p-4 h-100 position-relative">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div class="vk-service-card-icon"><i data-lucide="<?= e($serviceIcon) ?>"></i></div>
                            <span class="vk-service-availability">Book online</span>
                        </div>
                        <h3 class="h5 mb-2"><?= e((string) ($service['name'] ?? 'Service')) ?></h3>
                        <p class="text-muted small mb-3"><?= e((string) ($service['short_description'] ?? 'Contact our team for service details.')) ?></p>
                        <div class="vk-service-meta d-flex flex-wrap gap-2 mb-4">
                            <span><i data-lucide="map-pin"></i> Northern Sri Lanka</span>
                            <?php if (isset($service['price_from']) && (float) $service['price_from'] > 0): ?><span><i data-lucide="tag"></i> From LKR <?= e(number_format((float) $service['price_from'], 2)) ?></span><?php endif; ?>
                        </div>
                        <a class="stretched-link text-decoration-none text-primary fw-semibold mt-auto d-inline-flex align-items-center" href="<?= e($serviceUrl) ?>">Book now <i data-lucide="arrow-right" class="ms-1"></i></a>
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
                        <span class="badge bg-white text-dark text-uppercase px-3 py-2">Live records</span>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="vk-analytics-kpi"><strong><?= $homeOverview['total'] === null ? '—' : number_format($homeOverview['total']) ?></strong><span>Total bookings</span></div>
                        </div>
                        <div class="col-6">
                            <div class="vk-analytics-kpi"><strong><?= $homeOverview['completed'] === null ? '—' : number_format($homeOverview['completed']) ?></strong><span>Completed bookings</span></div>
                        </div>
                        <div class="col-6">
                            <div class="vk-analytics-kpi"><strong><?= $homeOverview['in_progress'] === null ? '—' : number_format($homeOverview['in_progress']) ?></strong><span>In progress</span></div>
                        </div>
                        <div class="col-6">
                            <div class="vk-analytics-kpi"><strong><?= $homeOverview['cancelled'] === null ? '—' : number_format($homeOverview['cancelled']) ?></strong><span>Cancelled</span></div>
                        </div>
                    </div>
                    <div class="vk-analytics-chart mb-4">
                        <div class="vk-live-week-chart" role="img" aria-label="Bookings received over the last seven days">
                            <?php $trendMax = max(1, ...array_column($homeBookingTrend, 'value')); foreach ($homeBookingTrend as $trendDay): ?>
                                <div class="vk-live-week-day"><span class="vk-live-week-bar" style="--bar-height: <?= (int) max(4, round(($trendDay['value'] / $trendMax) * 100)) ?>%" title="<?= (int) $trendDay['value'] ?> bookings"></span><small><?= e($trendDay['label']) ?></small></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="vk-live-week-caption">Bookings received · last 7 days</div>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="vk-radial-progress">
                            <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="50"></circle><circle cx="60" cy="60" r="50"></circle></svg>
                            <span><?= $homeOverview['active'] === null ? '—' : number_format($homeOverview['active']) ?></span>
                            <small>Active bookings</small>
                        </div>
                        <div class="vk-radial-progress vk-radial-progress--secondary">
                            <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="50"></circle><circle cx="60" cy="60" r="50"></circle></svg>
                            <span><?= count($services) ?></span>
                            <small>Configured services</small>
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
                ['name' => 'Rapid Visit', 'price' => 'Request an estimate', 'icon' => 'zap', 'items' => ['Share the issue', 'Review the estimate', 'Book a service visit']],
                ['name' => 'Business Care', 'price' => 'Tailored quote', 'icon' => 'building-2', 'items' => ['Discuss your service needs', 'Confirm the scope', 'Plan ongoing support']],
                ['name' => 'Smart Install', 'price' => 'Project quote', 'icon' => 'router', 'items' => ['Describe your installation', 'Review the proposed scope', 'Schedule with our team']],
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
            <?php if (!$teamMembers): ?><div class="vk-team-empty"><i data-lucide="users"></i><strong>Our team profiles are being updated</strong><span>Contact us to arrange the right technician for your service.</span><a class="btn btn-outline-light btn-sm" href="<?= e(BASE_URL) ?>/book.php">Book a service</a></div><?php endif; ?>
            <?php foreach ($teamMembers as $ti => $member): ?>
                <?php
                $memberId = (int) ($member['id'] ?? (9000 + $ti));
                $isOwner = vk_staff_is_owner($member);
                $skills = array_slice(vk_staff_skills_list((string) ($member['skills'] ?? '')), 0, 4);
                $certs = array_slice(vk_staff_certifications_list((string) ($member['certifications'] ?? '')), 0, 5);
                $socials = vk_staff_social_links((string) ($member['social_links'] ?? ''));
                $image = vk_staff_display_image($member, true);
                $status = vk_staff_normalize_status((string) ($member['status'] ?? 'active'));
                $phoneDigits = preg_replace('/\D+/', '', (string) ($member['phone'] ?? ''));
                $whatsapp = $phoneDigits !== '' ? 'https://wa.me/94' . ltrim(preg_replace('/^94/', '', $phoneDigits) ?? '', '0') : '';
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
                            <span class="vk-team-exp"><?= e((string) (($member['experience'] ?? '') ?: ($years ? $years . '+ years' : ''))) ?></span>
                        </div>
                        <p class="vk-team-desc"><?= e((string) (($member['specialization'] ?? '') ?: ($member['description'] ?? 'Experienced VK Network service professional.'))) ?></p>
                        <div class="vk-team-tags">
                            <?php foreach ($skills as $skill): ?>
                                <span><?= e($skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($years || $projects): ?><div class="vk-team-stats">
                            <?php if ($years): ?><span><strong><?= $years ?>+</strong> Years</span><?php endif; ?>
                            <?php if ($projects): ?><span><strong><?= $projects ?>+</strong> Projects</span><?php endif; ?>
                        </div><?php endif; ?>
                        <div class="vk-team-actions">
                            <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="modal" data-bs-target="#<?= e($modalId) ?>">View profile</button>
                            <a class="btn btn-sm btn-primary" href="<?= e(BASE_URL) ?>/book.php">Book</a>
                            <?php if ($whatsapp !== ''): ?><a class="vk-team-icon-btn" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp <?= e((string) ($member['name'] ?? 'team member')) ?>"><i data-lucide="message-circle"></i></a><?php endif; ?>
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
                <p class="vk-section-lead mx-auto mb-0">We value every customer’s feedback and share verified reviews here.</p>
        </div>
        <div class="vk-testimonials-shell" data-aos="fade-up" data-aos-duration="700">
            <button class="vk-testimonial-nav vk-testimonial-prev" type="button" aria-label="Previous testimonial">
                <i data-lucide="chevron-left"></i>
            </button>
            <div class="swiper vk-testimonials-swiper">
                <div class="swiper-wrapper">
                    <?php $testimonials = [];
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
                    <?php if (!$testimonials): ?><div class="swiper-slide"><article class="vk-testimonial-card vk-testimonial-empty"><div class="vk-testimonial-quote" aria-hidden="true"><i data-lucide="message-square-heart"></i></div><h3 class="h5">Your experience matters</h3><p class="vk-testimonial-copy mb-0">Verified customer reviews will appear here when they are available.</p></article></div><?php endif; ?>
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
