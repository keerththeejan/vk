<?php
declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/staff_model.php';

$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
if (preg_match('#/index\.php/staff/?$#i', $requestPath)) {
    require __DIR__ . '/staff.php';
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

$teamFallbackMembers = [
    [
        'name' => 'Vijay Keerththeejan',
        'role' => 'Owner',
        'image' => 'assets/images/staff/owner.svg',
        'description' => 'Leads service strategy, networking solutions, AI systems, and customer experience.',
        'skills' => 'Networking, AI Systems, Web Development',
        'social_links' => 'Profile|' . BASE_URL . '/index.php/staff',
    ],
    [
        'name' => 'John Silva',
        'role' => 'Technician',
        'image' => 'assets/images/staff/staff1.svg',
        'description' => 'Handles diagnostics, hardware repairs, printer service, and site visits.',
        'skills' => 'Hardware Repair, Printer Service, CCTV',
        'social_links' => 'Book|' . BASE_URL . '/book.php',
    ],
    [
        'name' => 'Nimal Perera',
        'role' => 'System Admin',
        'image' => 'assets/images/staff/staff2.svg',
        'description' => 'Maintains servers, network reliability, backup routines, and security checks.',
        'skills' => 'Servers, Network Management, Security',
        'social_links' => 'Profile|' . BASE_URL . '/index.php/staff',
    ],
    [
        'name' => 'Nisha Raj',
        'role' => 'Customer Support',
        'image' => 'assets/images/staff/nisha.svg',
        'description' => 'Coordinates bookings, customer updates, tracking requests, and follow ups.',
        'skills' => 'Support, Scheduling, Customer Care',
        'social_links' => 'Book|' . BASE_URL . '/book.php',
    ],
];

$teamMembers = [];
try {
    $pdo = $pdo ?? db();
    if (db_table_exists($pdo, 'staff')) {
        $teamMembers = array_slice(vk_staff_get_all($pdo, true), 0, 4);
    }
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('index.php: staff unavailable - ' . $e->getMessage());
    }
}

if (!$teamMembers) {
    $teamMembers = $teamFallbackMembers;
} elseif (count($teamMembers) < 4) {
    $teamMembers = array_slice(array_merge($teamMembers, $teamFallbackMembers), 0, 4);
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

require __DIR__ . '/includes/public_header.php';
?>
<section class="vk-hero-premium vk-home-hero" id="top">
    <div class="vk-hero-shine"></div>
    <div class="vk-hero-grain"></div>
    <div class="container vk-hero-inner">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-7" data-aos="fade-right" data-aos-duration="700">
                <div class="vk-hero-topline d-flex flex-wrap align-items-center gap-3 mb-3">
                    <span class="vk-hero-trust px-3 py-2 rounded-pill">Trusted by 25K+ customers</span>
                    <span class="vk-hero-badge-mini d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill">Live operations • Premium SLA</span>
                </div>
                <span class="vk-hero-eyebrow d-inline-flex align-items-center gap-2">
                    <i data-lucide="sparkles"></i>
                    Premium enterprise service platform
                </span>
                <h1 class="vk-hero-title">Smart Service Solutions for modern <span class="vk-gradient-text">Homes &amp; Businesses</span></h1>
                <p class="vk-hero-lead">Book repairs, installations, maintenance, and technical support with real-time tracking and intelligent workflow management.</p>
                <div class="vk-hero-actions d-flex flex-wrap gap-3">
                    <a class="vk-btn-hero-primary btn btn-lg px-4" href="<?= e(BASE_URL) ?>/book.php">
                        <span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="calendar-plus"></i></span>
                        Book a Service
                    </a>
                    <a class="vk-btn-hero-secondary btn btn-lg px-4" href="<?= e(BASE_URL) ?>/track.php">
                        <span class="vk-hero-btn-ic me-2 d-inline-flex align-items-center" aria-hidden="true"><i data-lucide="search"></i></span>
                        Track Your Service
                    </a>
                </div>
                <div class="vk-hero-badges d-flex flex-wrap gap-2 mt-4" data-aos="fade-up" data-aos-duration="700" data-aos-delay="150">
                    <span class="vk-hero-badge">Fast Response</span>
                    <span class="vk-hero-badge">Certified Experts</span>
                    <span class="vk-hero-badge">Secure &amp; Safe</span>
                    <span class="vk-hero-badge">Satisfaction Guarantee</span>
                </div>
                <div class="row row-cols-1 row-cols-sm-2 g-3 mt-4" data-aos="fade-up" data-aos-duration="700" data-aos-delay="200">
                    <div class="col">
                        <div class="vk-home-stat">
                            <strong>25K+</strong>
                            <span>Happy Customers</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="vk-home-stat">
                            <strong>98.6%</strong>
                            <span>Success Rate</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="vk-home-stat">
                            <strong>4.9★</strong>
                            <span>Customer Rating</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="vk-home-stat">
                            <strong>247</strong>
                            <span>Online Technicians</span>
                        </div>
                    </div>
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
                        <div class="vk-ai-message">“Your service is scheduled and the technician is 18 minutes away. You can reschedule or review the estimate anytime.”</div>
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
                <h2 class="vk-section-title mb-2">Expert solutions with fast booking and transparent tracking.</h2>
                <p class="vk-section-lead mb-0">Every service is backed by qualified technicians, instant booking, and a seamless customer experience.</p>
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
                        <div class="vk-service-card-icon mb-3"><i data-lucide="<?= e($service['icon']) ?>"></i></div>
                        <h3 class="h5 mb-2"><?= e($service['name']) ?></h3>
                        <p class="text-muted small mb-4"><?= e($service['description']) ?></p>
                        <a class="stretched-link text-decoration-none text-primary fw-semibold" href="<?= e($service['url']) ?>">Book now <i data-lucide="arrow-right" class="ms-1"></i></a>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vk-pub-section-alt py-5" id="overview">
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
                            <span>92%</span>
                            <small>Customer satisfaction</small>
                        </div>
                        <div class="vk-radial-progress vk-radial-progress--secondary">
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

<section class="vk-pub-section py-5" id="testimonials">
    <div class="container py-lg-4">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="vk-section-kicker">Testimonials</span>
            <h2 class="vk-section-title mb-2">Trusted by customers across Sri Lanka.</h2>
            <p class="vk-section-lead mx-auto mb-0">Premium reviews from customers who have experienced fast, transparent service delivery.</p>
        </div>
        <div id="vkTestimonialsCarousel" class="carousel slide" data-bs-ride="carousel" data-aos="fade-up" data-aos-duration="700">
            <div class="carousel-inner">
                <?php $testimonials = [
                    ['name' => 'Amali Perera', 'role' => 'Homeowner', 'text' => 'VK Network fixed my laptop and installed CCTV in one day. The tracking interface made it easy to follow every step.', 'stars' => 5],
                    ['name' => 'Nalin Fernando', 'role' => 'Business owner', 'text' => 'Professional, fast, and secure. Their technician arrived on time and resolved our network outage quickly.', 'stars' => 5],
                    ['name' => 'Samantha Jayasekara', 'role' => 'IT manager', 'text' => 'Great support for our office printer and AC systems. Clear communication and reliable follow-through.', 'stars' => 5],
                ];
                foreach ($testimonials as $i => $review): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <div class="vk-testimonial-card p-4 p-lg-5 mx-auto" style="max-width: 730px;">
                            <p class="mb-4 text-muted">"<?= e($review['text']) ?>"</p>
                            <div class="d-flex align-items-center gap-3">
                                <div class="vk-testimonial-avatar"><span><?= e(substr($review['name'], 0, 1)) ?></span></div>
                                <div>
                                    <strong><?= e($review['name']) ?></strong>
                                    <div class="small text-muted"><?= e($review['role']) ?></div>
                                </div>
                            </div>
                            <div class="vk-testimonial-stars mt-4">
                                <?php for ($s = 0; $s < 5; $s++): ?>
                                    <i data-lucide="star" class="<?= $s < $review['stars'] ? 'text-warning' : 'text-muted' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#vkTestimonialsCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#vkTestimonialsCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
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
                    <a class="btn btn-primary btn-lg px-4" href="<?= e(BASE_URL) ?>/book.php">Book Now</a>
                </div>
            </div>
        </div>
    </div>
</section>

<a href="#top" class="vk-back-to-top" aria-label="Back to top"><i data-lucide="arrow-up"></i></a>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
