<?php
declare(strict_types=1);
if (!defined('VK_LAYOUT_BOOTSTRAPPED')) {
    require_once __DIR__ . '/layout_init.php';
}
$pageTitle = $pageTitle ?? 'Dashboard';
$extraHead = $extraHead ?? '';
require __DIR__ . '/header.php';
?>
<div class="d-flex flex-grow-1">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="flex-grow-1 d-flex flex-column min-vh-100 vk-main">
        <?php require __DIR__ . '/navbar.php'; ?>
        <main class="container-fluid py-3 px-3 px-lg-4 flex-grow-1">
