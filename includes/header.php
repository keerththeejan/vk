<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Dashboard';
$extraHead = $extraHead ?? '';
$vkAdminStyleVersion = vk_asset_mtime_version('assets/css/style.css');
$vkAdminFavicon = getLogo('favicon');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>
    <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= e($vkAdminStyleVersion) ?>" media="print" onload="this.media='all'">
    <noscript>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= e($vkAdminStyleVersion) ?>">
    </noscript>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" media="print" onload="this.media='all'">
    <link rel="icon" href="<?= e($vkAdminFavicon) ?>">
    <script>
        window.VK_BASE_URL = <?= json_encode(BASE_URL, JSON_THROW_ON_ERROR) ?>;
        window.VK_CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_THROW_ON_ERROR) ?>;
    </script>
    <?= $extraHead ?>
</head>
<body class="vk-app d-flex flex-column min-vh-100">
<div id="pageLoader" class="vk-loader d-none" aria-hidden="true">
    <div class="spinner-border text-light" role="status"><span class="visually-hidden">Loading…</span></div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080" id="toastContainer"></div>
