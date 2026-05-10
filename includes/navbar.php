<?php
declare(strict_types=1);
/** @var array|null $currentUser */
$cu = $currentUser ?? null;
$adminLogo = getLogo('mobile');
$adminCompany = vk_app_setting('company_name', 'VK Network');
$adminTagline = vk_app_setting('company_tagline', 'Service desk');
?>
<nav class="navbar navbar-expand-lg navbar-dark vk-navbar border-bottom border-secondary border-opacity-25 sticky-top">
    <div class="container-fluid vk-navbar-inner">
        <div class="vk-nav-left">
            <button class="btn btn-outline-light d-lg-none vk-icon-btn vk-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Open menu">
                <i class="bi bi-list fs-4"></i>
            </button>
            <a class="navbar-brand vk-brand-block" href="<?= e(BASE_URL) ?>/modules/dashboard.php">
                <span class="vk-brand-logo-shell">
                    <img class="vk-admin-logo-img" src="<?= e($adminLogo) ?>" alt="<?= e((string) $adminCompany) ?> logo" loading="eager" decoding="async">
                </span>
                <span class="vk-brand-copy">
                    <span class="vk-brand-name"><?= e((string) $adminCompany) ?></span>
                    <small><?= e((string) $adminTagline) ?></small>
                </span>
            </a>
        </div>

        <div class="vk-nav-center">
            <form class="vk-top-search" role="search" action="<?= e(BASE_URL) ?>/modules/customers/list.php" method="get">
                <i class="bi bi-search"></i>
                <input type="search" name="q" placeholder="Ask AI or search customers, jobs, invoices..." aria-label="Search customers, jobs, and invoices">
                <button type="submit" aria-label="Run AI search"><i class="bi bi-stars"></i><span>AI</span></button>
            </form>
        </div>

        <div class="vk-nav-right">
            <button class="btn btn-outline-light vk-icon-btn vk-mobile-search-btn d-xl-none" type="button" data-bs-toggle="modal" data-bs-target="#vkSearchModal" aria-label="Open search">
                <i class="bi bi-search"></i>
            </button>
            <a class="btn btn-primary vk-quick-create d-none d-md-inline-flex" href="<?= e(BASE_URL) ?>/modules/repairs/add.php">
                <i class="bi bi-plus-lg"></i><span>Quick action</span>
            </a>
            <div class="dropdown">
                <button class="btn btn-outline-light vk-icon-btn position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-info">3</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow vk-premium-dropdown p-2">
                    <div class="dropdown-header">Notifications</div>
                    <a class="dropdown-item rounded-3" href="<?= e(BASE_URL) ?>/modules/bookings/list.php"><i class="bi bi-calendar2-check me-2 text-info"></i>Review new web bookings</a>
                    <a class="dropdown-item rounded-3" href="<?= e(BASE_URL) ?>/modules/warranties/list.php?filter=expiring"><i class="bi bi-shield-exclamation me-2 text-warning"></i>Warranty alert window</a>
                    <a class="dropdown-item rounded-3" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php?status=active"><i class="bi bi-clock-history me-2 text-primary"></i>Maintenance reminders</a>
                </div>
            </div>
            <button type="button" class="btn btn-outline-light vk-icon-btn" id="themeToggle" title="Toggle theme" aria-label="Toggle dark mode">
                <i class="bi bi-moon-stars-fill" id="themeIconDark"></i>
                <i class="bi bi-sun-fill d-none" id="themeIconLight"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle vk-profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="vk-profile-avatar">
                        <span class="vk-online-dot" aria-hidden="true"></span>
                        <i class="bi bi-person-fill"></i>
                    </span>
                    <span class="vk-profile-copy">
                        <strong><?= e($cu['fullname'] ?? $cu['username'] ?? 'User') ?></strong>
                        <small><?= e($cu['role'] ?? 'Administrator') ?></small>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow vk-premium-dropdown">
                    <li><span class="dropdown-item-text small text-muted"><?= e($cu['username'] ?? '') ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" target="_blank" rel="noopener" href="<?= e(BASE_URL) ?>/index.php"><i class="bi bi-globe2 me-2"></i>Public website</a></li>
                    <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="modal fade" id="vkSearchModal" tabindex="-1" aria-labelledby="vkSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vk-search-modal">
            <div class="modal-header border-0">
                <h2 class="modal-title h6" id="vkSearchModalLabel">AI workspace search</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-0">
                <form class="vk-top-search vk-top-search-modal" role="search" action="<?= e(BASE_URL) ?>/modules/customers/list.php" method="get">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" placeholder="Search customers, jobs, invoices..." aria-label="Search customers, jobs, and invoices">
                    <button type="submit" aria-label="Run AI search"><i class="bi bi-stars"></i><span>AI</span></button>
                </form>
            </div>
        </div>
    </div>
</div>
