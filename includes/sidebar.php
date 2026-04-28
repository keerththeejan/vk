<?php
declare(strict_types=1);
$path = $_SERVER['SCRIPT_NAME'] ?? '';
function nav_active(string $needle): string
{
    global $path;
    return str_contains($path, $needle) ? 'active' : '';
}
?>
<div class="offcanvas-lg offcanvas-start vk-sidebar text-white" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header d-lg-none border-bottom border-light border-opacity-10">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div class="p-3 border-bottom border-light border-opacity-10 d-none d-lg-block">
            <div class="d-flex align-items-center gap-2">
                <span class="vk-logo-sm rounded-circle d-flex align-items-center justify-content-center fw-bold text-white">VK</span>
                <div>
                    <div class="vk-brand-red fw-bold lh-1">IT Network</div>
                    <small class="text-white-50 text-uppercase" style="font-size: 0.6rem; letter-spacing: 0.1em;">Repair · CCTV · Hardware</small>
                </div>
            </div>
        </div>
        <nav class="nav flex-column py-2 flex-grow-1">
            <a class="nav-link px-3 py-2 <?= nav_active('/dashboard.php') ?>" href="<?= e(BASE_URL) ?>/modules/dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/bookings/') ?>" href="<?= e(BASE_URL) ?>/modules/bookings/list.php"><i class="bi bi-calendar2-check me-2"></i>Web bookings</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/portfolio/') ?>" href="<?= e(BASE_URL) ?>/modules/portfolio/list.php"><i class="bi bi-images me-2"></i>Portfolio</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/customers/') ?>" href="<?= e(BASE_URL) ?>/modules/customers/list.php"><i class="bi bi-people me-2"></i>Customers</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/repairs/') ?>" href="<?= e(BASE_URL) ?>/modules/repairs/list.php"><i class="bi bi-wrench-adjustable me-2"></i>Repairs</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/cctv/') ?>" href="<?= e(BASE_URL) ?>/modules/cctv/list.php"><i class="bi bi-camera-video me-2"></i>CCTV</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/maintenance/') ?>" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php"><i class="bi bi-calendar-check me-2"></i>Maintenance</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/warranties/') ?>" href="<?= e(BASE_URL) ?>/modules/warranties/list.php"><i class="bi bi-shield-check me-2"></i>Warranties</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/technicians/') ?>" href="<?= e(BASE_URL) ?>/modules/technicians/list.php"><i class="bi bi-person-badge me-2"></i>Technicians</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/staff/') ?>" href="<?= e(BASE_URL) ?>/modules/staff/list.php"><i class="bi bi-person-lines-fill me-2"></i>Staff portfolio</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/service_templates/') ?>" href="<?= e(BASE_URL) ?>/modules/service_templates/list.php"><i class="bi bi-tags me-2"></i>Service templates</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/web_services/gallery') ?>" href="<?= e(BASE_URL) ?>/modules/web_services/gallery.php"><i class="bi bi-images me-2"></i>Service gallery</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/vehicle_bookings/') ?>" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php"><i class="bi bi-car-front me-2"></i>Vehicle bookings</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/vehicles/') ?>" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php"><i class="bi bi-truck me-2"></i>Vehicles</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/drivers/') ?>" href="<?= e(BASE_URL) ?>/modules/drivers/list.php"><i class="bi bi-person-vcard me-2"></i>Drivers</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/products/') ?>" href="<?= e(BASE_URL) ?>/modules/products/list.php"><i class="bi bi-cpu me-2"></i>Parts &amp; products</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/invoices/') ?>" href="<?= e(BASE_URL) ?>/modules/invoices/list.php"><i class="bi bi-receipt me-2"></i>Invoices</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/payments/') ?>" href="<?= e(BASE_URL) ?>/modules/payments/list.php"><i class="bi bi-cash-coin me-2"></i>Payments</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/accounts/') ?>" href="<?= e(BASE_URL) ?>/modules/accounts/list.php"><i class="bi bi-wallet2 me-2"></i>Accounts</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/accounts/transfer') ?>" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php"><i class="bi bi-arrow-left-right me-2"></i>Transfer</a>
            <?php if ((($currentUser ?? [])['role'] ?? 'admin') === 'admin'): ?>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/users/') ?>" href="<?= e(BASE_URL) ?>/modules/users/index.php"><i class="bi bi-people me-2"></i>Users</a>
            <?php endif; ?>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/menus/') ?>" href="<?= e(BASE_URL) ?>/modules/menus/index.php"><i class="bi bi-list-nested me-2"></i>Site menus</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/settings/index.php') ?>" href="<?= e(BASE_URL) ?>/modules/settings/index.php"><i class="bi bi-gear-wide-connected me-2"></i>System Settings</a>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/settings/email.php') ?>" href="<?= e(BASE_URL) ?>/modules/settings/email.php"><i class="bi bi-inbox me-2"></i>Email &amp; Inbox</a>
        </nav>
        <div class="p-3 small text-white-50 border-top border-light border-opacity-10">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-geo-alt-fill mt-1"></i>
                <span>26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka</span>
            </div>
        </div>
    </div>
</div>
