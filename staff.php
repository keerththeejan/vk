<?php
declare(strict_types=1);

/**
 * Staff.php — Portfolio controller for /index.php/staff
 * Routed from index.php when request path matches #/index\.php/staff/?#i
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/includes/init_public.php';
}
require_once __DIR__ . '/includes/staff_model.php';

/* ─── Data Layer ─────────────────────────────────────────────────── */
$staffMembers = [];

try {
    $pdo = db();
    if (db_table_exists($pdo, 'staff')) {
        $staffMembers = vk_staff_get_all($pdo, true);
    }
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        error_log('Staff.php: db error — ' . $e->getMessage());
    }
}

/* ─── Fallback demo data ─────────────────────────────────────────── */
if (!$staffMembers) {
    $staffMembers = [
        [
            'id'           => 1,
            'name'         => 'Vijay Keerththeejan',
            'role'         => 'Owner & Lead Engineer',
            'image'        => 'assets/images/staff/owner.svg',
            'description'  => 'Passionate technologist with 10+ years in networking, AI systems, and enterprise IT. Founded VK Tech to deliver honest, high-quality service across Northern Sri Lanka.',
            'skills'       => 'Networking, AI Systems, Cloud Infrastructure, CCTV',
            'joined_year'  => '2014',
            'contact_email'=> '',
        ],
        [
            'id'           => 2,
            'name'         => 'John Silva',
            'role'         => 'Senior Technician',
            'image'        => 'assets/images/staff/staff1.svg',
            'description'  => 'Hardware and software repair specialist with deep expertise in laptop motherboard-level repairs, OS recovery, and field servicing.',
            'skills'       => 'Hardware Repair, OS Recovery, Printer Service, On-site Support',
            'joined_year'  => '2017',
            'contact_email'=> '',
        ],
        [
            'id'           => 3,
            'name'         => 'Nimal Perera',
            'role'         => 'System Administrator',
            'image'        => 'assets/images/staff/staff2.svg',
            'description'  => 'Keeps servers humming and networks secure. Manages AMC clients, handles remote monitoring, and leads CCTV and DC electrical projects.',
            'skills'       => 'Server Management, Network Admin, CCTV, DC Wiring',
            'joined_year'  => '2019',
            'contact_email'=> '',
        ],
    ];
}

/* ─── Page meta ──────────────────────────────────────────────────── */
$pageTitle       = 'Our Team';
$navActive       = 'staff';
$seoCanonicalPath = '/index.php/staff';
$seoDescription  = 'Meet the VK Tech team — experienced technicians and engineers serving Jaffna, Kilinochchi, Vavuniya and surrounding areas.';
$seoKeywords     = vk_seo_default_keywords() . ', team, technicians, staff portfolio';

/* ─── Render ─────────────────────────────────────────────────────── */
require __DIR__ . '/includes/public_header.php';
require __DIR__ . '/application/views/staff_list.php';
require __DIR__ . '/includes/public_footer.php';
