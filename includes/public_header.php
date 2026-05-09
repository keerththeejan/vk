<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Home';
$navActive = $navActive ?? '';
$extraHead = $extraHead ?? '';
$siteTitle = vk_app_setting('site_title');
$seoBrand = $siteTitle ?: vk_app_setting('site_name') ?: 'VK Network';
$seoTitlePrefix = vk_app_setting('seo_site_title');
$titleBase = ($seoTitlePrefix !== null && $seoTitlePrefix !== '') ? $seoTitlePrefix : $seoBrand;
$htmlTitle = $seoDocumentTitle ?? ($titleBase . ' | ' . $pageTitle);
$GLOBALS['seoFullTitle'] = $htmlTitle;
$siteLogo = vk_app_setting('site_logo');
$siteFavicon = vk_app_setting('site_favicon');

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
<html lang="en" data-bs-theme="light" data-theme="light">
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
            var t = localStorage.getItem('vk-public-theme');
            if (t === 'dark' || t === 'light') {
                document.documentElement.setAttribute('data-bs-theme', t);
                document.documentElement.setAttribute('data-theme', t);
            }
        } catch (e) {}
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" crossorigin="anonymous">
    <link href="<?= e(base_url('assets/css/style.css')) ?>" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/public-premium.css')) ?>" rel="stylesheet">
    <?= $extraHead ?>
    <?php if ($siteFavicon): ?>
    <link rel="icon" type="image/<?= strpos($siteFavicon, '.png') !== false ? 'png' : 'x-icon' ?>" href="<?= e(base_url($siteFavicon)) ?>">
    <link rel="shortcut icon" href="<?= e(base_url($siteFavicon)) ?>">
    <?php endif; ?>
</head>
<body class="vk-public-site d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg sticky-top vk-navbar-premium">
    <div class="container d-flex flex-wrap align-items-center justify-content-between">
        <a class="navbar-brand d-flex align-items-center gap-3 py-2 mb-0 text-decoration-none" href="<?= e(BASE_URL) ?>/index.php">
            <?php if ($siteLogo): ?>
                <img src="<?= e(base_url($siteLogo)) ?>" alt="<?= e($seoBrand) ?>" class="vk-public-logo-img" style="max-height:48px;max-width:160px;width:auto;height:auto;">
            <?php else: ?>
                <span class="vk-public-logo-circle rounded-circle text-white d-inline-flex align-items-center justify-content-center" aria-hidden="true">VK</span>
                <span class="d-flex flex-column align-items-start text-start lh-sm">
                    <span class="vk-public-brand-title"><?= e($seoBrand) ?></span>
                    <span class="vk-public-brand-sub">Multi-Service Solutions</span>
                </span>
            <?php endif; ?>
        </a>
        <div class="d-flex align-items-center gap-2 order-lg-last flex-shrink-0">
            <button type="button" class="btn vk-theme-toggle" data-vk-theme-toggle aria-label="Toggle color theme" aria-pressed="false" title="Light / dark mode">
                <span class="vk-theme-icon-sun d-none align-items-center justify-content-center" aria-hidden="true" style="width:1.35rem;height:1.35rem"><i data-lucide="sun"></i></span>
                <span class="vk-theme-icon-moon d-inline-flex align-items-center justify-content-center" aria-hidden="true" style="width:1.35rem;height:1.35rem"><i data-lucide="moon"></i></span>
            </button>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pubNav" aria-controls="pubNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse flex-grow-1 justify-content-lg-end" id="pubNav">
            <ul class="navbar-nav vk-pub-nav ms-lg-auto mb-2 mb-lg-0 align-items-lg-center gap-lg-2 pt-3 pt-lg-0 border-top border-lg-top-0 mt-2 mt-lg-0" id="vkPubNavMenus">
                <?php foreach ($vkPubNavMenus as $m): ?>
                    <?php
                    $mslug = (string) ($m['slug'] ?? '');
                    $isActive = $navActive !== '' && $navActive === $mslug;
                    $href = vk_site_menus_href((string) ($m['url'] ?? ''));
                    $ic = vk_site_menus_icon_html($m['icon'] ?? null);
                    ?>
                    <li class="nav-item" data-menu-id="<?= (int) ($m['id'] ?? 0) ?>">
                        <a class="nav-link vk-pub-nav-link d-inline-flex align-items-center <?= $isActive ? 'active' : '' ?>" href="<?= e($href) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                            <?= $ic ?><?= e((string) ($m['name'] ?? '')) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item">
                    <a class="nav-link vk-pub-nav-link text-white" href="<?= e(BASE_URL) ?>/landing.php">Landing</a>
                </li>
                <li class="nav-item ms-lg-1 mt-2 mt-lg-0 w-100 w-lg-auto">
                    <a class="btn btn-staff d-inline-flex align-items-center justify-content-center w-100 w-lg-auto" href="<?= e(BASE_URL) ?>/login.php"><span class="vk-lucide-nav me-2" aria-hidden="true"><i data-lucide="shield-check"></i></span>Staff Login</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<main class="flex-grow-1">
