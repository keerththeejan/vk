<?php
declare(strict_types=1);
$path = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
function nav_active(string $needle): string
{
    global $path;
    return str_contains((string) $path, $needle) ? 'active' : '';
}
?>
<div class="offcanvas-lg offcanvas-start vk-sidebar text-white" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header d-lg-none border-bottom border-light border-opacity-10">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Command menu</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarOffcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <div class="p-3 border-bottom border-light border-opacity-10 d-none d-lg-block vk-sidebar-brand">
            <div class="d-flex align-items-center gap-2">
                <span class="vk-logo-sm rounded-circle d-flex align-items-center justify-content-center fw-bold text-white">VK</span>
                <div class="vk-sidebar-brand-copy">
                    <div class="vk-brand-red fw-bold lh-1">IT Network</div>
                    <small class="text-white-50 text-uppercase">Repair &middot; CCTV &middot; Hardware</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-light ms-auto vk-sidebar-toggle" id="sidebarMiniToggle" aria-label="Collapse sidebar" title="Collapse sidebar">
                    <i class="bi bi-layout-sidebar-inset"></i>
                </button>
            </div>
        </div>
        <nav class="nav flex-column py-2 flex-grow-1">
            <span class="vk-nav-label">Command</span>
            <a class="nav-link px-3 py-2 <?= nav_active('/dashboard.php') ?>" href="<?= e(BASE_URL) ?>/modules/dashboard.php"><i class="bi bi-speedometer2 me-2"></i><span>Dashboard</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/bookings/') ?>" href="<?= e(BASE_URL) ?>/modules/bookings/list.php"><i class="bi bi-calendar2-check me-2"></i><span>Web bookings</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/customers/') ?>" href="<?= e(BASE_URL) ?>/modules/customers/list.php"><i class="bi bi-people me-2"></i><span>Customers</span></a>

            <span class="vk-nav-label">Growth</span>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/seo/') ?>" href="<?= e(BASE_URL) ?>/modules/seo/index.php"><i class="bi bi-search-heart me-2"></i><span>SEO Management</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/marketing/') ?>" href="<?= e(BASE_URL) ?>/modules/marketing/index.php"><i class="bi bi-megaphone me-2"></i><span>Marketing</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/whatsapp/') ?>" href="<?= e(BASE_URL) ?>/modules/whatsapp/index.php"><i class="bi bi-whatsapp me-2"></i><span>WhatsApp Automation</span></a>

            <span class="vk-nav-label">Service ops</span>
            <a class="nav-link px-3 py-2 <?= nav_active('/repairs/') ?>" href="<?= e(BASE_URL) ?>/modules/repairs/list.php"><i class="bi bi-wrench-adjustable me-2"></i><span>Repairs</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/cctv/') ?>" href="<?= e(BASE_URL) ?>/modules/cctv/list.php"><i class="bi bi-camera-video me-2"></i><span>CCTV</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/maintenance/') ?>" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php"><i class="bi bi-calendar-check me-2"></i><span>Maintenance</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/warranties/') ?>" href="<?= e(BASE_URL) ?>/modules/warranties/list.php"><i class="bi bi-shield-check me-2"></i><span>Warranties</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/technicians/') ?>" href="<?= e(BASE_URL) ?>/modules/technicians/list.php"><i class="bi bi-person-badge me-2"></i><span>Technicians</span></a>

            <span class="vk-nav-label">Website</span>
            <a class="nav-link px-3 py-2 <?= nav_active('/portfolio/') ?>" href="<?= e(BASE_URL) ?>/modules/portfolio/list.php"><i class="bi bi-images me-2"></i><span>Portfolio</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/staff/') ?>" href="<?= e(BASE_URL) ?>/modules/staff/list.php"><i class="bi bi-person-lines-fill me-2"></i><span>Staff portfolio</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/service_templates/') ?>" href="<?= e(BASE_URL) ?>/modules/service_templates/list.php"><i class="bi bi-tags me-2"></i><span>Service templates</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/web_services/gallery') ?>" href="<?= e(BASE_URL) ?>/modules/web_services/gallery.php"><i class="bi bi-images me-2"></i><span>Service gallery</span></a>

            <span class="vk-nav-label">Fleet</span>
            <a class="nav-link px-3 py-2 <?= nav_active('/vehicle_bookings/') ?>" href="<?= e(BASE_URL) ?>/modules/vehicle_bookings/list.php"><i class="bi bi-car-front me-2"></i><span>Vehicle bookings</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/vehicles/') ?>" href="<?= e(BASE_URL) ?>/modules/vehicles/list.php"><i class="bi bi-truck me-2"></i><span>Vehicles</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/drivers/') ?>" href="<?= e(BASE_URL) ?>/modules/drivers/list.php"><i class="bi bi-person-vcard me-2"></i><span>Drivers</span></a>

            <span class="vk-nav-label">Finance</span>
            <a class="nav-link px-3 py-2 <?= nav_active('/products/') ?>" href="<?= e(BASE_URL) ?>/modules/products/list.php"><i class="bi bi-cpu me-2"></i><span>Parts &amp; products</span></a>
            <?php $quotationNavOpen = str_contains($path, '/quotations/'); ?>
            <div class="vk-nav-group">
                <button class="nav-link px-3 py-2 w-100 text-start border-0 bg-transparent text-white d-flex align-items-center <?= $quotationNavOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#quotationMgmtNav" aria-expanded="<?= $quotationNavOpen ? 'true' : 'false' ?>">
                    <i class="bi bi-file-earmark-ruled me-2"></i><span>Quotation Management</span><i class="bi bi-chevron-down ms-auto small"></i>
                </button>
                <div class="collapse <?= $quotationNavOpen ? 'show' : '' ?>" id="quotationMgmtNav">
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/dashboard.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/dashboard.php"><span>Dashboard</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/create.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/create.php"><span>Create Quotation</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/list.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/list.php"><span>Quotation List</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/approval.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/approval.php"><span>Quotation Approval</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/templates.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/templates.php"><span>Quotation Templates</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/categories.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/categories.php"><span>Quotation Categories</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/followup.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/followup.php"><span>Quotation Follow-up</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/revisions.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/revisions.php"><span>Quotation Revisions</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/analytics.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/analytics.php"><span>Quotation Analytics</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/reports.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/reports.php"><span>Quotation Reports</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/expired.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/expired.php"><span>Expired Quotations</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/converted.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/converted.php"><span>Converted Sales Orders</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/quotations/settings.php') ?>" href="<?= e(BASE_URL) ?>/modules/quotations/settings.php"><span>Settings</span></a>
                </div>
            </div>
            <?php $invoiceNavOpen = str_contains($path, '/invoices/'); ?>
            <div class="vk-nav-group">
                <button class="nav-link px-3 py-2 w-100 text-start border-0 bg-transparent text-white d-flex align-items-center <?= $invoiceNavOpen ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#invoiceMgmtNav" aria-expanded="<?= $invoiceNavOpen ? 'true' : 'false' ?>">
                    <i class="bi bi-receipt me-2"></i><span>Invoice Management</span><i class="bi bi-chevron-down ms-auto small"></i>
                </button>
                <div class="collapse <?= $invoiceNavOpen ? 'show' : '' ?>" id="invoiceMgmtNav">
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/invoices/list.php') ?>" href="<?= e(BASE_URL) ?>/modules/invoices/list.php"><span>Invoice List</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/invoices/create.php') ?>" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><span>Create Invoice</span></a>
                    <a class="nav-link ps-5 py-2 small <?= nav_active('/invoices/print_settings.php') ?>" href="<?= e(BASE_URL) ?>/modules/invoices/print_settings.php"><span>Invoice Print Settings</span></a>
                    <a class="nav-link ps-5 py-2 small" href="<?= e(BASE_URL) ?>/modules/invoices/print_settings.php#section-signature"><span>Digital Signature</span></a>
                    <a class="nav-link ps-5 py-2 small" href="<?= e(BASE_URL) ?>/modules/invoices/print_settings.php#section-stamp"><span>Company Stamp</span></a>
                    <a class="nav-link ps-5 py-2 small" href="<?= e(BASE_URL) ?>/modules/invoices/print_settings.php#section-logo"><span>Company Logo</span></a>
                </div>
            </div>
            <a class="nav-link px-3 py-2 <?= nav_active('/payments/') ?>" href="<?= e(BASE_URL) ?>/modules/payments/list.php"><i class="bi bi-cash-coin me-2"></i><span>Payments</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/accounts/') ?>" href="<?= e(BASE_URL) ?>/modules/accounts/list.php"><i class="bi bi-wallet2 me-2"></i><span>Accounts</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/accounts/transfer') ?>" href="<?= e(BASE_URL) ?>/modules/accounts/transfer.php"><i class="bi bi-arrow-left-right me-2"></i><span>Transfer</span></a>

            <span class="vk-nav-label">Admin</span>
            <?php
            $navRole = (string) (($currentUser ?? [])['role'] ?? $_SESSION['user_role'] ?? 'viewer');
            $showUsersNav = function_exists('vk_auth_role_can_manage')
                && (vk_auth_role_can_manage($navRole) || in_array($navRole, ['manager', 'staff', 'viewer'], true));
            if ($showUsersNav):
            ?>
            <?php if (vk_auth_role_can_manage($navRole)): ?>
            <a class="nav-link px-3 py-2 <?= nav_active('/approve_users.php') ?>" href="<?= e(BASE_URL) ?>/approve_users.php"><i class="bi bi-person-check me-2"></i><span>Approvals</span></a>
            <?php endif; ?>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/users/') ?>" href="<?= e(BASE_URL) ?>/modules/users/index.php"><i class="bi bi-people me-2"></i><span>Users</span></a>
            <?php endif; ?>
            <?php if (function_exists('vk_auth_role_can_manage') && vk_auth_role_can_manage($navRole)): ?>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/menus/') ?>" href="<?= e(BASE_URL) ?>/modules/menus/index.php"><i class="bi bi-list-nested me-2"></i><span>Site menus</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/settings/index.php') ?>" href="<?= e(BASE_URL) ?>/modules/settings/index.php"><i class="bi bi-gear-wide-connected me-2"></i><span>System Settings</span></a>
            <a class="nav-link px-3 py-2 <?= nav_active('/modules/settings/email.php') ?>" href="<?= e(BASE_URL) ?>/modules/settings/email.php"><i class="bi bi-inbox me-2"></i><span>Email &amp; Inbox</span></a>
            <?php endif; ?>
        </nav>
        <div class="p-3 small text-white-50 border-top border-light border-opacity-10 vk-sidebar-foot">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-geo-alt-fill mt-1"></i>
                <span>26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka</span>
            </div>
        </div>
    </div>
</div>
