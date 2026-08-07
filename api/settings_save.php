<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/includes/init.php';
require_settings_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_THROW_ON_ERROR);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON'], JSON_THROW_ON_ERROR);
    exit;
}
require_csrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $data['csrf_token'] ?? ''));

$tab = (string) ($data['tab'] ?? '');
$settings = $data['settings'] ?? null;
if (!is_array($settings)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing settings object'], JSON_THROW_ON_ERROR);
    exit;
}

$byTab = [
    'general' => ['company_name', 'site_title', 'site_name', 'company_tagline', 'business_slogan'],
    'navigation' => ['navbar_cta_text', 'navbar_cta_url', 'announcement_enabled', 'announcement_text', 'announcement_url'],
    'contact' => ['contact_phone', 'contact_phone_alt', 'support_email', 'sales_email', 'whatsapp_number', 'business_hours', 'company_address', 'google_maps_embed', 'branches_json', 'whatsapp_default_message'],
    'social' => ['facebook_url', 'instagram_url', 'linkedin_url', 'tiktok_url', 'youtube_url', 'twitter_url'],
    'homepage' => ['hero_title', 'hero_subtitle', 'hero_primary_cta_text', 'hero_primary_cta_url', 'hero_secondary_cta_text', 'hero_secondary_cta_url', 'home_stats_json', 'services_section_title', 'services_section_subtitle', 'testimonials_title'],
    'seo' => ['seo_site_title', 'seo_meta_description', 'seo_meta_keywords', 'seo_og_image', 'seo_twitter_image', 'seo_auto_enabled', 'seo_locations', 'seo_service_slugs', 'seo_canonical_url', 'robots_txt', 'seo_schema_markup'],
    'theme' => ['theme_primary', 'theme_secondary', 'theme_accent', 'theme_gradient_start', 'theme_gradient_end', 'theme_glow', 'button_style', 'card_style'],
    'email' => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_secure', 'email_from', 'from_name', 'email_autoresponder_enabled', 'email_autoresponder_subject', 'email_autoresponder_body'],
    'security' => ['security_maintenance_mode', 'security_readonly_staff'],
    'footer' => ['footer_text', 'footer_bottom_text', 'analytics_domain', 'analytics_script_src'],
    'email_hub' => [
        'imap_host', 'imap_port', 'imap_username', 'imap_password', 'imap_poll_enabled',
        'email_autoresponder_enabled', 'email_autoresponder_subject', 'email_autoresponder_body',
    ],
];
$byTab['all'] = array_values(array_unique(array_merge(...array_values($byTab))));

if (!isset($byTab[$tab])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown tab'], JSON_THROW_ON_ERROR);
    exit;
}

$pdo = db();
vk_settings_seed_defaults($pdo);

$allowed = array_flip($byTab[$tab]);
$meta = vk_settings_defaults();

foreach ($settings as $key => $value) {
    if (!is_string($key) || !isset($allowed[$key])) {
        continue;
    }
    if (!is_string($value) && !is_numeric($value)) {
        continue;
    }
    $str = (string) $value;
    if ($key === 'smtp_password' || $key === 'imap_password') {
        $str = trim($str);
    }
    if ($key === 'smtp_password' && $str === '') {
        continue;
    }
    if ($key === 'imap_password' && $str === '') {
        continue;
    }
    if (str_ends_with($key, '_json')) {
        $decoded = json_decode($str, true);
        if (!is_array($decoded)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $key . ' must contain valid JSON'], JSON_THROW_ON_ERROR);
            exit;
        }
    }
    if (in_array($key, ['theme_primary', 'theme_secondary', 'theme_accent', 'theme_gradient_start', 'theme_gradient_end', 'theme_glow'], true)
        && !preg_match('/^#[0-9a-f]{6}$/i', $str)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => $key . ' must be a hex color'], JSON_THROW_ON_ERROR);
        exit;
    }
    $group = isset($meta[$key]) ? (string) $meta[$key][1] : $tab;
    $type = isset($meta[$key]) ? (string) $meta[$key][2] : 'text';
    vk_settings_set($pdo, $key, $str, $group, $type);
    vk_settings_audit($pdo, 'save', $key, in_array($key, ['smtp_password', 'imap_password'], true) ? '[secret]' : $str);
}

if ($tab === 'email' || $tab === 'all' || $tab === 'email_hub') {
    vk_bootstrap_module('mailer');
    vk_bootstrap_module('email_system');
}

if ($tab === 'email' || $tab === 'all') {
    $smtpIn = [
        'smtp_host' => trim((string) ($settings['smtp_host'] ?? '')),
        'smtp_port' => (int) ($settings['smtp_port'] ?? 587),
        'smtp_user' => trim((string) ($settings['smtp_username'] ?? '')),
        'smtp_pass' => trim((string) ($settings['smtp_password'] ?? '')),
        'smtp_secure' => (string) ($settings['smtp_secure'] ?? 'tls'),
        'from_email' => trim((string) ($settings['email_from'] ?? '')),
        'from_name' => trim((string) ($settings['from_name'] ?? '')),
    ];
    if ($smtpIn['smtp_host'] === '' && $smtpIn['smtp_user'] !== '') {
        $guess = vk_smtp_guess_defaults((string) $smtpIn['smtp_user']);
        $smtpIn['smtp_host'] = (string) $guess['smtp_host'];
        $smtpIn['smtp_port'] = (int) $guess['smtp_port'];
        $smtpIn['smtp_secure'] = (string) $guess['smtp_secure'];
    }
    vk_smtp_settings_save($pdo, $smtpIn);
    vk_autoresponder_settings_save($pdo, [
        'email_autoresponder_enabled' => ((string) ($settings['email_autoresponder_enabled'] ?? '0')) === '1' ? '1' : '0',
        'email_autoresponder_subject' => (string) ($settings['email_autoresponder_subject'] ?? ''),
        'email_autoresponder_body' => (string) ($settings['email_autoresponder_body'] ?? ''),
    ]);
    $cfgAfter = vk_smtp_settings_get($pdo);
    echo json_encode([
        'ok' => true,
        'saved' => $byTab[$tab],
        'smtp_password_configured' => ((string) ($cfgAfter['smtp_user'] ?? '')) === ''
            || trim((string) ($cfgAfter['smtp_pass'] ?? '')) !== '',
    ], JSON_THROW_ON_ERROR);
    if ($tab === 'email') {
        exit;
    }
}

if ($tab === 'email_hub') {
    vk_imap_settings_save($pdo, [
        'imap_host' => trim((string) ($settings['imap_host'] ?? '')),
        'imap_port' => (int) ($settings['imap_port'] ?? 993),
        'imap_username' => trim((string) ($settings['imap_username'] ?? '')),
        'imap_password' => trim((string) ($settings['imap_password'] ?? '')),
        'imap_poll_enabled' => ((string) ($settings['imap_poll_enabled'] ?? '0')) === '1' ? '1' : '0',
    ]);
    vk_autoresponder_settings_save($pdo, [
        'email_autoresponder_enabled' => ((string) ($settings['email_autoresponder_enabled'] ?? '0')) === '1' ? '1' : '0',
        'email_autoresponder_subject' => (string) ($settings['email_autoresponder_subject'] ?? ''),
        'email_autoresponder_body' => (string) ($settings['email_autoresponder_body'] ?? ''),
    ]);
}

echo json_encode(['ok' => true, 'saved' => $byTab[$tab]], JSON_THROW_ON_ERROR);
