<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? 'Dashboard';
$extraHead = $extraHead ?? '';
$vkAdminStyleVersion = is_file(__DIR__ . '/../assets/css/style.css') ? (string) filemtime(__DIR__ . '/../assets/css/style.css') : (string) time();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/style.css')) ?>?v=<?= e($vkAdminStyleVersion) ?>" rel="stylesheet">
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
