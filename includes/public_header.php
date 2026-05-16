<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Home';
$navActive = $navActive ?? '';
$extraHead = $extraHead ?? '';
$siteTitle = vk_app_setting('site_title');
$companyName = vk_app_setting('company_name', vk_app_setting('site_name', 'VK Network'));
$companyTagline = vk_app_setting('company_tagline', 'Multi-Service Solutions');
$seoBrand = $siteTitle ?: $companyName ?: 'VK Network';
$seoTitlePrefix = vk_app_setting('seo_site_title');
$titleBase = ($seoTitlePrefix !== null && $seoTitlePrefix !== '') ? $seoTitlePrefix : $seoBrand;
$htmlTitle = $seoDocumentTitle ?? ($titleBase . ' | ' . $pageTitle);
$GLOBALS['seoFullTitle'] = $htmlTitle;
$siteLogoUrl = getLogo('main');
$mobileLogoUrl = getLogo('mobile');
$siteFaviconUrl = getLogo('favicon');
$navCtaText = vk_app_setting('navbar_cta_text', 'Book Service');
$navCtaUrl = vk_setting_url(vk_app_setting('navbar_cta_url', '/book.php'), BASE_URL . '/book.php');
$announcementEnabled = vk_settings_bool('announcement_enabled', false);
$announcementText = vk_app_setting('announcement_text', '');
$announcementUrl = vk_setting_url(vk_app_setting('announcement_url', ''), '#');
$themePrimary = vk_app_setting('theme_primary', '#3b82f6');
$themeSecondary = vk_app_setting('theme_secondary', '#14b8a6');
$themeAccent = vk_app_setting('theme_accent', '#a78bfa');
$themeGlow = vk_app_setting('theme_glow', '#38bdf8');
foreach (['themePrimary' => '#3b82f6', 'themeSecondary' => '#14b8a6', 'themeAccent' => '#a78bfa', 'themeGlow' => '#38bdf8'] as $var => $fallback) {
    if (!preg_match('/^#[0-9a-f]{6}$/i', (string) $$var)) {
        $$var = $fallback;
    }
}
$vkPublicStyleVersion = is_file(__DIR__ . '/../assets/css/style.css') ? (string) filemtime(__DIR__ . '/../assets/css/style.css') : (string) time();
$vkPublicPremiumVersion = is_file(__DIR__ . '/../assets/css/public-premium.css') ? (string) filemtime(__DIR__ . '/../assets/css/public-premium.css') : (string) time();

require_once __DIR__ . '/site_menus.php';
try {
    $vkPubNavMenus = vk_site_menus_for_public_nav(db());
} catch (Throwable $e) {
    $vkPubNavMenus = vk_site_menus_for_public_nav_fallback();
}
if (!headers_sent() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Cache-Control: public, max-age=600');
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($htmlTitle) ?></title>
    <?php vk_public_seo_head(); ?>
    <?= vk_geo_meta_tags() ?>
    <?= vk_plausible_script() ?>
    <script>
    (function () {
        try {
            var t = localStorage.getItem('vk-public-theme') || 'dark';
            if (t === 'dark' || t === 'light') {
                document.documentElement.setAttribute('data-bs-theme', t);
                document.documentElement.setAttribute('data-theme', t);
            }
        } catch (e) {}
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://unpkg.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" crossorigin="anonymous" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap"></noscript>
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="preload" as="style" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= e($vkPublicStyleVersion) ?>">
    <link rel="preload" as="style" href="<?= e(base_url('assets/css/public-premium.css')) ?>?v=<?= e($vkPublicPremiumVersion) ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= e($vkPublicStyleVersion) ?>" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/public-premium.css')) ?>?v=<?= e($vkPublicPremiumVersion) ?>" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= e($vkPublicStyleVersion) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/public-premium.css')) ?>?v=<?= e($vkPublicPremiumVersion) ?>">
    </noscript>
    <?= $extraHead ?>
    <style>
        :root { color-scheme: dark; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        html { scroll-behavior: smooth; }
        body { margin: 0; min-height: 100vh; background: #020617; color: #f8fafc; }
        .vk-navbar-premium { background: rgba(7, 14, 26, .92); border-bottom: 1px solid rgba(148, 163, 184, .12); }
        .vk-public-logo-img { max-height: 40px; width: auto; height: auto; display: block; }
        .vk-public-site { background: #020617; }
        .vk-home-hero { position: relative; overflow: hidden; background: linear-gradient(180deg, #060b17 0%, #070f1f 100%); color: #fff; min-height: 100vh; }
        .vk-home-hero .vk-hero-inner { padding: clamp(2rem, 5vw, 4.5rem) 0; }
        .vk-hero-title { font-size: clamp(2.5rem, 6vw, 3.8rem); line-height: 1.05; margin: .75rem 0; }
        .vk-hero-lead { max-width: 44rem; font-size: 1.03rem; line-height: 1.7; color: rgba(255, 255, 255, .82); }
        .vk-btn-hero-primary, .vk-btn-hero-secondary, .vk-nav-book-btn { transition: transform .22s ease, opacity .2s ease; }
        .vk-btn-hero-primary:hover, .vk-btn-hero-secondary:hover, .vk-nav-book-btn:hover { transform: translateY(-1px); }
        .vk-reveal { opacity: 0; transform: translateY(16px); will-change: opacity, transform; transition: opacity .34s ease-out, transform .34s ease-out; }
        .vk-reveal.is-visible { opacity: 1; transform: translateY(0); }
        .vk-hero-shine, .vk-hero-grain, .vk-hero-particles { display: none !important; }
        @media (min-width: 992px) { .vk-hero-shine, .vk-hero-grain, .vk-hero-particles { display: block !important; } }
        @media (max-width: 767.98px) { .vk-hero-inner { padding-top: 2rem; padding-bottom: 2.8rem; } .vk-brand-strip { gap: .65rem; } }
        body.vk-public-site {
            --primary-color: <?= e((string) $themePrimary) ?>;
            --vk-pub-primary-mid: <?= e((string) $themePrimary) ?>;
            --vk-pub-accent: <?= e((string) $themeAccent) ?>;
            --vk-public-secondary: <?= e((string) $themeSecondary) ?>;
            --vk-public-glow: <?= e((string) $themeGlow) ?>;
        }
    </style>
    <link rel="icon" href="<?= e($siteFaviconUrl) ?>">
    <link rel="shortcut icon" href="<?= e($siteFaviconUrl) ?>">
</head>
<body class="vk-public-site vk-neo-site d-flex flex-column min-vh-100">
<div class="vk-site-bg" aria-hidden="true">
    <span class="vk-aurora-wash vk-aurora-one"></span>
    <span class="vk-aurora-wash vk-aurora-two"></span>
    <span class="vk-aurora-wash vk-aurora-three"></span>
    <span class="vk-particle-field"></span>
</div>
<?php if ($announcementEnabled && trim((string) $announcementText) !== ''): ?>
<div class="vk-announcement-bar">
    <a href="<?= e($announcementUrl) ?>"><?= e((string) $announcementText) ?></a>
</div>
<?php endif; ?>
<nav class="navbar navbar-expand-lg sticky-top vk-navbar-premium" data-vk-navbar="true" data-vk-scroll-target="true" role="navigation" aria-label="Main navigation">
    <div class="container vk-navbar-shell d-flex flex-wrap align-items-center justify-content-between">
        <a class="navbar-brand d-flex align-items-center gap-3 py-2 mb-0 text-decoration-none" href="<?= e(BASE_URL) ?>/index.php" title="<?= e($seoBrand) ?> - <?= e((string) $companyTagline) ?>">
            <picture>
                <source media="(max-width: 575.98px)" srcset="<?= e($mobileLogoUrl) ?>">
                <img src="<?= e($siteLogoUrl) ?>" alt="<?= e($seoBrand) ?>" class="vk-public-logo-img" loading="eager" decoding="async">
            </picture>
            <span class="d-flex flex-column align-items-start text-start lh-sm">
                <span class="vk-public-brand-title"><?= e($seoBrand) ?></span>
                <span class="vk-public-brand-sub"><?= e((string) $companyTagline) ?></span>
            </span>
        </a>
        <div class="d-flex align-items-center gap-2 order-lg-last flex-shrink-0">
            <a class="btn vk-nav-book-btn d-none d-sm-inline-flex align-items-center justify-content-center" href="<?= e($navCtaUrl) ?>" data-animate="ripple" title="<?= e((string) $navCtaText) ?>">
                <span class="vk-lucide-nav me-2" aria-hidden="true"><i data-lucide="calendar-plus"></i></span>
                <?= e((string) $navCtaText) ?>
            </a>
            <button type="button" class="btn vk-theme-toggle" data-vk-theme-toggle aria-label="Toggle color theme between light and dark mode" aria-pressed="false" title="Light / dark mode">
                <span class="vk-theme-icon-sun d-none align-items-center justify-content-center" aria-hidden="true" style="width:1.35rem;height:1.35rem"><i data-lucide="sun"></i></span>
                <span class="vk-theme-icon-moon d-inline-flex align-items-center justify-content-center" aria-hidden="true" style="width:1.35rem;height:1.35rem"><i data-lucide="moon"></i></span>
            </button>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pubNav" aria-controls="pubNav" aria-expanded="false" aria-label="Toggle navigation menu" title="Open navigation menu">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse flex-grow-1 justify-content-lg-end" id="pubNav">
            <nav class="navbar-nav vk-pub-nav mx-lg-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-1 pt-3 pt-lg-0 border-top border-lg-top-0 mt-2 mt-lg-0" id="vkPubNavMenus">
                <?php
                $premiumNav = [];
                foreach ($vkPubNavMenus ?: [] as $menuRow) {
                    $premiumNav[] = [
                        'name' => (string) ($menuRow['name'] ?? ''),
                        'slug' => (string) ($menuRow['slug'] ?? ''),
                        'href' => vk_site_menus_href((string) ($menuRow['url'] ?? 'index.php')),
                    ];
                }
                if (!$premiumNav) {
                    $premiumNav = [
                        ['name' => 'Home', 'slug' => 'home', 'href' => BASE_URL . '/index.php'],
                        ['name' => 'Services', 'slug' => 'services', 'href' => BASE_URL . '/index.php#services'],
                        ['name' => 'Bookings', 'slug' => 'book', 'href' => BASE_URL . '/book.php'],
                        ['name' => 'Track Service', 'slug' => 'track', 'href' => BASE_URL . '/track.php'],
                        ['name' => 'Our Work', 'slug' => 'portfolio', 'href' => BASE_URL . '/portfolio.php'],
                    ];
                }
                foreach ($premiumNav as $m):
                    $isActive = $navActive !== '' && $navActive === $m['slug'];
                ?>
                    <li class="nav-item">
                        <a class="nav-link vk-pub-nav-link d-inline-flex align-items-center <?= $isActive ? 'active' : '' ?>" href="<?= e($m['href']) ?>" data-nav-link="<?= e($m['slug']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?> title="Navigate to <?= e($m['name']) ?>">
                            <?= e($m['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item ms-lg-1 mt-2 mt-lg-0 w-100 w-lg-auto">
                    <a class="btn btn-staff d-inline-flex align-items-center justify-content-center w-100 w-lg-auto" href="<?= e(BASE_URL) ?>/login.php" data-animate="ripple" title="Staff login portal"><span class="vk-lucide-nav me-2" aria-hidden="true"><i data-lucide="shield-check"></i></span>Staff Login</a>
                </li>
                <li class="nav-item mt-2 d-sm-none w-100">
                    <a class="btn vk-nav-book-btn d-inline-flex align-items-center justify-content-center w-100" href="<?= e($navCtaUrl) ?>" data-animate="ripple" title="<?= e((string) $navCtaText) ?>">
                        <span class="vk-lucide-nav me-2" aria-hidden="true"><i data-lucide="calendar-plus"></i></span>
                        <?= e((string) $navCtaText) ?>
                    </a>
                </li>
            </nav>
        </div>
    </div>
</nav>
<main class="flex-grow-1">
