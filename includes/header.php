<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Dashboard';
$extraHead = $extraHead ?? '';
$vkAdminStyleVersion = vk_asset_mtime_version('assets/css/style.css');
$vkAppStyleVersion = vk_asset_mtime_version('assets/css/vk-app.css');
$vkAdminFavicon = getLogo('favicon');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('vk_billing_theme');
                if (theme === 'dark' || theme === 'light') {
                    document.documentElement.setAttribute('data-bs-theme', theme);
                } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                }
                if (localStorage.getItem('vk_sidebar_mini') === '1') {
                    document.documentElement.classList.add('vk-sidebar-mini');
                }
            } catch (e) {}
            window.VK_BASE_URL = <?= json_encode(BASE_URL, JSON_THROW_ON_ERROR) ?>;
            window.VK_CSRF_TOKEN = <?= json_encode((string) ($GLOBALS['vk_csrf_token'] ?? csrf_token()), JSON_THROW_ON_ERROR) ?>;
        })();
    </script>
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= e($vkAdminStyleVersion) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/vk-app.css')) ?>?v=<?= e($vkAppStyleVersion) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </noscript>
    <link rel="icon" href="<?= e($vkAdminFavicon) ?>">
    <?= $extraHead ?>
</head>
<body class="vk-app d-flex flex-column min-vh-100">
<a class="visually-hidden-focusable btn btn-primary vk-skip-link" href="#vkMainContent">Skip to content</a>
<div id="pageLoader" class="vk-loader d-none" aria-hidden="true">
    <div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading…</span></div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3 vk-toast-host" id="toastContainer"></div>
