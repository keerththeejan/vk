<?php
declare(strict_types=1);

/**
 * Enterprise key/value site settings. Backward-compatible with the existing
 * `settings` table while adding metadata used by the admin CMS.
 */

/** @var array<string, string>|null */
$GLOBALS['_vk_settings_cache'] = null;

function vk_settings_table_ready(PDO $pdo): bool
{
    return db_table_exists($pdo, 'settings');
}

function vk_settings_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            key_name VARCHAR(128) NOT NULL,
            value MEDIUMTEXT NULL,
            setting_group VARCHAR(64) NOT NULL DEFAULT 'general',
            setting_type VARCHAR(32) NOT NULL DEFAULT 'text',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_settings_key (key_name),
            KEY idx_settings_group (setting_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $columns = [
        'setting_group' => "ALTER TABLE settings ADD COLUMN setting_group VARCHAR(64) NOT NULL DEFAULT 'general' AFTER value",
        'setting_type' => "ALTER TABLE settings ADD COLUMN setting_type VARCHAR(32) NOT NULL DEFAULT 'text' AFTER setting_group",
        'created_at' => "ALTER TABLE settings ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER setting_type",
    ];
    foreach ($columns as $column => $sql) {
        if (function_exists('db_column_exists') && !db_column_exists($pdo, 'settings', $column)) {
            $pdo->exec($sql);
        }
    }

    try {
        $pdo->exec('CREATE INDEX idx_settings_group ON settings (setting_group)');
    } catch (Throwable $e) {
        // Index may already exist on older MySQL versions.
    }
}

function vk_settings_invalidate_cache(): void
{
    $GLOBALS['_vk_settings_cache'] = null;
}

/**
 * @return array<string, string>
 */
function vk_settings_all(PDO $pdo): array
{
    if ($GLOBALS['_vk_settings_cache'] !== null) {
        return $GLOBALS['_vk_settings_cache'];
    }
    $out = [];
    if (!vk_settings_table_ready($pdo)) {
        $GLOBALS['_vk_settings_cache'] = $out;

        return $out;
    }
    try {
        $st = $pdo->query('SELECT key_name, `value` FROM settings');
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $k = (string) ($row['key_name'] ?? '');
            if ($k !== '') {
                $out[$k] = (string) ($row['value'] ?? '');
            }
        }
    } catch (Throwable $e) {
        $out = [];
    }
    $GLOBALS['_vk_settings_cache'] = $out;

    return $out;
}

/** Read one setting; empty DB value falls through to $default. */
function vk_settings_get(PDO $pdo, string $key, ?string $default = null): ?string
{
    $all = vk_settings_all($pdo);
    if (!array_key_exists($key, $all) || $all[$key] === '') {
        return $default;
    }

    return $all[$key];
}

function vk_settings_set(PDO $pdo, string $key, string $value, string $group = 'general', string $type = 'text'): void
{
    vk_settings_ensure_schema($pdo);
    $st = $pdo->prepare(
        'INSERT INTO settings (key_name, `value`, setting_group, setting_type) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `value` = VALUES(`value`),
            setting_group = VALUES(setting_group),
            setting_type = VALUES(setting_type),
            updated_at = CURRENT_TIMESTAMP'
    );
    $st->execute([$key, $value, $group, $type]);
    vk_settings_invalidate_cache();
}

function getSetting(string $key, ?string $default = null): ?string
{
    return vk_app_setting($key, $default);
}

function vk_setting_url(?string $path, string $default = ''): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return $default;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    return base_url($path);
}

function vk_setting_relative_path(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '' || preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    $base = trim(BASE_URL, '/');
    if ($base !== '' && str_starts_with(ltrim($path, '/'), $base . '/')) {
        $path = substr(ltrim($path, '/'), strlen($base) + 1);
    }

    return ltrim($path, '/');
}

function vk_setting_asset_url(?string $path, string $fallback = '', bool $cacheBust = true): string
{
    $relative = vk_setting_relative_path($path);
    if ($relative === '') {
        $relative = vk_setting_relative_path($fallback);
    }
    if ($relative === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }
    $fs = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($fs)) {
        $relative = vk_setting_relative_path($fallback);
        $fs = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
    if ($relative === '') {
        return '';
    }
    $url = base_url($relative);
    if ($cacheBust && is_file($fs)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . (string) filemtime($fs);
    }

    return $url;
}

function vk_setting_asset_exists(?string $path): bool
{
    $relative = vk_setting_relative_path($path);
    if ($relative === '' || preg_match('#^https?://#i', $relative)) {
        return $relative !== '';
    }

    return is_file(ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
}

function getLogo(string $type = 'main'): string
{
    $type = strtolower(trim($type));
    $fallback = 'assets/images/default-logo.svg';
    $keys = [
        'main' => ['company_logo', 'site_logo'],
        'dark' => ['site_logo_dark', 'company_logo', 'site_logo'],
        'light' => ['site_logo_light', 'company_logo', 'site_logo'],
        'mobile' => ['mobile_logo', 'company_logo', 'site_logo'],
        'favicon' => ['site_favicon', 'company_logo', 'site_logo'],
    ][$type] ?? ['company_logo', 'site_logo'];

    foreach ($keys as $key) {
        $path = vk_app_setting($key, '');
        if (is_string($path) && $path !== '' && vk_setting_asset_exists($path)) {
            return vk_setting_asset_url($path, $fallback, true);
        }
        if (is_string($path) && $path !== '') {
            error_log('getLogo: missing or invalid logo asset for ' . $key . ': ' . $path);
        }
    }

    return vk_setting_asset_url($fallback, '', true);
}

function getLogoPath(string $type = 'main'): string
{
    $type = strtolower(trim($type));
    $keys = [
        'main' => ['company_logo', 'site_logo'],
        'dark' => ['site_logo_dark', 'company_logo', 'site_logo'],
        'light' => ['site_logo_light', 'company_logo', 'site_logo'],
        'mobile' => ['mobile_logo', 'company_logo', 'site_logo'],
        'favicon' => ['site_favicon', 'company_logo', 'site_logo'],
    ][$type] ?? ['company_logo', 'site_logo'];
    foreach ($keys as $key) {
        $path = vk_setting_relative_path(vk_app_setting($key, ''));
        if ($path !== '' && vk_setting_asset_exists($path)) {
            return $path;
        }
    }

    return 'assets/images/default-logo.svg';
}

function vk_settings_bool(string $key, bool $default = false): bool
{
    $v = vk_app_setting($key, $default ? '1' : '0');
    return in_array((string) $v, ['1', 'true', 'yes', 'on'], true);
}

function vk_settings_json(string $key, array $default = []): array
{
    $raw = vk_app_setting($key, '');
    if (!is_string($raw) || trim($raw) === '') {
        return $default;
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : $default;
}

function vk_settings_defaults(): array
{
    return [
        'site_name' => ['VK Network', 'general', 'text'],
        'company_name' => ['VK Network', 'general', 'text'],
        'site_title' => ['VK Network', 'general', 'text'],
        'company_tagline' => ['Multi-Service Solutions', 'general', 'text'],
        'business_slogan' => ['Premium local service operations for homes and businesses.', 'general', 'textarea'],
        'company_logo' => ['', 'branding', 'image'],
        'site_logo' => ['', 'branding', 'image'],
        'site_logo_dark' => ['', 'branding', 'image'],
        'site_logo_light' => ['', 'branding', 'image'],
        'mobile_logo' => ['', 'branding', 'image'],
        'site_favicon' => ['', 'branding', 'image'],
        'navbar_cta_text' => ['Book Service', 'navigation', 'text'],
        'navbar_cta_url' => ['/book.php', 'navigation', 'url'],
        'announcement_enabled' => ['0', 'navigation', 'boolean'],
        'announcement_text' => ['', 'navigation', 'text'],
        'announcement_url' => ['', 'navigation', 'url'],
        'contact_phone' => ['077 887 0135', 'contact', 'text'],
        'contact_phone_alt' => ['', 'contact', 'text'],
        'support_email' => ['', 'contact', 'email'],
        'sales_email' => ['', 'contact', 'email'],
        'company_address' => ['26/3 Thiruvaiyaru, Kilinochchi, Sri Lanka', 'contact', 'textarea'],
        'business_hours' => ['Mon - Sat: 8:00 AM - 7:00 PM', 'contact', 'textarea'],
        'google_maps_embed' => ['', 'contact', 'textarea'],
        'branches_json' => ['[]', 'contact', 'json'],
        'whatsapp_number' => ['94778870135', 'contact', 'text'],
        'whatsapp_default_message' => ['Hello VK Network, I would like to inquire about your services.', 'contact', 'textarea'],
        'facebook_url' => ['', 'social', 'url'],
        'instagram_url' => ['', 'social', 'url'],
        'linkedin_url' => ['', 'social', 'url'],
        'tiktok_url' => ['', 'social', 'url'],
        'youtube_url' => ['', 'social', 'url'],
        'twitter_url' => ['', 'social', 'url'],
        'hero_title' => ['Premium Multi-Service Support, Built Around Trust.', 'homepage', 'text'],
        'hero_subtitle' => ['Book repairs, installations, maintenance, and technical support with real-time tracking and intelligent workflow management.', 'homepage', 'textarea'],
        'hero_primary_cta_text' => ['Book a Service', 'homepage', 'text'],
        'hero_primary_cta_url' => ['/book.php', 'homepage', 'url'],
        'hero_secondary_cta_text' => ['Track My Job', 'homepage', 'text'],
        'hero_secondary_cta_url' => ['/track.php', 'homepage', 'url'],
        'home_stats_json' => ['[]', 'homepage', 'json'],
        'services_section_title' => ['Services designed for modern homes and businesses.', 'homepage', 'text'],
        'services_section_subtitle' => ['A single trusted team for technology, maintenance, installations, and rapid field support.', 'homepage', 'textarea'],
        'testimonials_title' => ['What customers say after the job is done.', 'homepage', 'text'],
        'footer_text' => ['Premium local service operations with transparent booking, tracking, and field support for homes and businesses.', 'footer', 'textarea'],
        'footer_bottom_text' => ['Made with care in Sri Lanka', 'footer', 'text'],
        'seo_site_title' => ['', 'seo', 'text'],
        'seo_meta_description' => ['Professional computer, printer, CCTV, maintenance, and field repair services in Kilinochchi and across Sri Lanka - VK Network.', 'seo', 'textarea'],
        'seo_meta_keywords' => ['computer repair, laptop service, printer repair, CCTV installation, Sri Lanka, Kilinochchi, VK Network', 'seo', 'text'],
        'seo_og_image' => ['', 'seo', 'image'],
        'seo_twitter_image' => ['', 'seo', 'image'],
        'seo_canonical_url' => ['', 'seo', 'url'],
        'seo_schema_markup' => ['', 'seo', 'textarea'],
        'robots_txt' => ["User-agent: *\nAllow: /", 'seo', 'textarea'],
        'theme_primary' => ['#3b82f6', 'theme', 'color'],
        'theme_secondary' => ['#14b8a6', 'theme', 'color'],
        'theme_accent' => ['#a78bfa', 'theme', 'color'],
        'theme_gradient_start' => ['#1e3a8a', 'theme', 'color'],
        'theme_gradient_end' => ['#7c3aed', 'theme', 'color'],
        'theme_glow' => ['#38bdf8', 'theme', 'color'],
        'button_style' => ['pill', 'theme', 'select'],
        'card_style' => ['glass', 'theme', 'select'],
        'security_maintenance_mode' => ['0', 'security', 'boolean'],
        'security_readonly_staff' => ['1', 'security', 'boolean'],
        'analytics_domain' => ['', 'integrations', 'text'],
        'analytics_script_src' => ['https://plausible.io/js/script.js', 'integrations', 'url'],
        'smtp_host' => ['', 'email', 'text'],
        'smtp_port' => ['587', 'email', 'number'],
        'smtp_username' => ['', 'email', 'text'],
        'smtp_password' => ['', 'email', 'password'],
        'smtp_secure' => ['tls', 'email', 'select'],
        'email_from' => ['', 'email', 'email'],
        'from_name' => ['VK Network', 'email', 'text'],
        'email_autoresponder_enabled' => ['0', 'email', 'boolean'],
        'email_autoresponder_subject' => ['Thank you for contacting VK Network', 'email', 'text'],
        'email_autoresponder_body' => ['Thanks for contacting us. Our team will reply soon.', 'email', 'textarea'],
    ];
}

function vk_settings_seed_defaults(PDO $pdo): void
{
    vk_settings_ensure_schema($pdo);
    $st = $pdo->prepare(
        'INSERT IGNORE INTO settings (key_name, `value`, setting_group, setting_type) VALUES (?, ?, ?, ?)'
    );
    foreach (vk_settings_defaults() as $key => $meta) {
        $st->execute([$key, (string) $meta[0], (string) $meta[1], (string) $meta[2]]);
    }
    vk_settings_invalidate_cache();
}

function vk_settings_export(PDO $pdo): array
{
    vk_settings_ensure_schema($pdo);
    $rows = $pdo->query('SELECT key_name, `value`, setting_group, setting_type, updated_at FROM settings ORDER BY setting_group, key_name')
        ->fetchAll(PDO::FETCH_ASSOC);
    return [
        'exported_at' => date(DATE_ATOM),
        'app' => 'VK Network',
        'settings' => $rows ?: [],
    ];
}

function vk_settings_audit(PDO $pdo, string $action, string $key = '', string $value = ''): void
{
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS settings_audit_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                action VARCHAR(64) NOT NULL,
                setting_key VARCHAR(128) NULL,
                value_preview VARCHAR(255) NULL,
                ip_address VARCHAR(64) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_settings_audit_created (created_at),
                KEY idx_settings_audit_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $preview = mb_substr($value, 0, 255);
        $st = $pdo->prepare('INSERT INTO settings_audit_log (user_id, action, setting_key, value_preview, ip_address) VALUES (?, ?, ?, ?, ?)');
        $st->execute([
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            $action,
            $key !== '' ? $key : null,
            $preview,
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable $e) {
        // Audit logging should not block settings saves.
    }
}

/**
 * Public / front controller: uses db() when available.
 * Safe if DB is down — returns $default.
 */
function vk_app_setting(string $key, ?string $default = null): ?string
{
    try {
        if (!function_exists('db')) {
            return $default;
        }
        $pdo = db();

        return vk_settings_get($pdo, $key, $default);
    } catch (Throwable $e) {
        return $default;
    }
}
