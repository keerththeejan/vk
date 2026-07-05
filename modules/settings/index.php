<?php
declare(strict_types=1);
$pageTitle = 'Site Settings';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_settings_admin();
$extraHead = '<link href="' . e(BASE_URL) . '/assets/css/settings-admin.css" rel="stylesheet">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$pdo = db();
vk_settings_seed_defaults($pdo);
$s = vk_settings_all($pdo);
vk_bootstrap_module('mailer');
$smtp = vk_smtp_settings_get($pdo);
$smtpNeedsPassword = ((string) ($smtp['smtp_user'] ?? '')) !== '' && trim((string) ($smtp['smtp_pass'] ?? '')) === '';
$defaults = static function (string $k, string $d = '') use ($s): string {
    return array_key_exists($k, $s) ? (string) $s[$k] : $d;
};
$asset = static function (string $path): string {
    return vk_setting_asset_url($path, 'assets/images/default-logo.svg', true);
};
$field = static function (string $key, string $label, string $type = 'text', string $help = '', array $options = []) use ($defaults): void {
    $value = $defaults($key);
    $id = 'setting_' . preg_replace('/[^a-z0-9_]+/i', '_', $key);
    $attrs = ' data-setting-key="' . e($key) . '" data-setting-type="' . e($type) . '"';
    echo '<div class="vk-setting-field" data-setting-search="' . e(strtolower($label . ' ' . $key . ' ' . $help)) . '">';
    echo '<label class="form-label" for="' . e($id) . '">' . e($label) . '</label>';
    if ($type === 'textarea' || $type === 'json') {
        echo '<textarea class="form-control" id="' . e($id) . '" rows="' . ($type === 'json' ? '5' : '3') . '"' . $attrs . '>' . e($value) . '</textarea>';
    } elseif ($type === 'boolean') {
        echo '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="' . e($id) . '"' . $attrs . ($value === '1' ? ' checked' : '') . '><label class="form-check-label small text-muted" for="' . e($id) . '">Enabled</label></div>';
    } elseif ($type === 'select') {
        echo '<select class="form-select" id="' . e($id) . '"' . $attrs . '>';
        foreach ($options as $ov => $ol) {
            echo '<option value="' . e((string) $ov) . '"' . ((string) $ov === $value ? ' selected' : '') . '>' . e((string) $ol) . '</option>';
        }
        echo '</select>';
    } else {
        $inputType = in_array($type, ['email', 'url', 'color', 'password', 'number'], true) ? $type : 'text';
        echo '<input class="form-control" type="' . e($inputType) . '" id="' . e($id) . '" value="' . e($value) . '"' . $attrs . '>';
    }
    if ($help !== '') {
        echo '<div class="form-text">' . e($help) . '</div>';
    }
    echo '</div>';
};

$tabs = [
    'general' => ['General Settings', 'gear-wide-connected'],
    'branding' => ['Branding', 'stars'],
    'navigation' => ['Header & Navbar', 'menu-button-wide'],
    'contact' => ['Contact Information', 'geo-alt'],
    'social' => ['Social Media', 'share'],
    'homepage' => ['Homepage Settings', 'house-heart'],
    'seo' => ['SEO Settings', 'graph-up-arrow'],
    'theme' => ['Theme Customization', 'palette'],
    'email' => ['Email Settings', 'envelope-at'],
    'security' => ['Security Settings', 'shield-lock'],
    'footer' => ['Footer Settings', 'layout-text-window-reverse'],
    'backup' => ['Backup & Restore', 'database-down'],
];
?>

<div class="vk-settings-shell">
    <div class="vk-settings-hero">
        <div>
            <span class="vk-settings-kicker">VK Network CMS</span>
            <h1>Site Settings Management</h1>
            <p>Control branding, contact details, SEO, theme tokens, homepage content, integrations, and system behavior from one secure dashboard.</p>
        </div>
        <div class="vk-settings-hero-actions">
            <a class="btn btn-outline-light" href="<?= e(BASE_URL) ?>/index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Public preview</a>
            <button class="btn btn-primary" type="button" data-save-all><i class="bi bi-cloud-check me-1"></i>Save all</button>
        </div>
    </div>

    <div class="vk-settings-layout">
        <aside class="vk-settings-sidebar">
            <div class="vk-settings-search">
                <i class="bi bi-search"></i>
                <input type="search" id="settingsSearch" placeholder="Search settings">
            </div>
            <nav class="nav flex-column vk-settings-tabs" role="tablist">
                <?php $first = true; foreach ($tabs as $id => [$label, $icon]): ?>
                    <button class="nav-link <?= $first ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#pane-<?= e($id) ?>" type="button" role="tab">
                        <i class="bi bi-<?= e($icon) ?>"></i><span><?= e($label) ?></span>
                    </button>
                <?php $first = false; endforeach; ?>
            </nav>
        </aside>

        <section class="tab-content vk-settings-content">
            <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>General Identity</h2><p>Primary company and browser-facing labels.</p></div>
                    <div class="row g-3">
                        <div class="col-lg-6"><?php $field('company_name', 'Company name', 'text', 'Shown across header, footer, SEO, and email sender defaults.'); ?></div>
                        <div class="col-lg-6"><?php $field('site_title', 'Website title', 'text', 'Browser title and site identity.'); ?></div>
                        <div class="col-lg-6"><?php $field('site_name', 'Admin/site name', 'text'); ?></div>
                        <div class="col-lg-6"><?php $field('company_tagline', 'Tagline', 'text'); ?></div>
                        <div class="col-12"><?php $field('business_slogan', 'Business slogan', 'textarea'); ?></div>
                    </div>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="general">Save General</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-branding" role="tabpanel">
                <form class="vk-settings-card" id="brandingForm" enctype="multipart/form-data">
                    <div class="vk-card-head"><h2>Brand Assets</h2><p>Upload production-safe logos and favicons. Files are stored in <code>uploads/settings/</code>.</p></div>
                    <div class="row g-3">
                        <?php
                        $uploads = [
                            'company_logo' => ['Main logo', 'PNG, JPG, WEBP, or SVG. Recommended 320x120.'],
                            'site_logo_dark' => ['Dark logo', 'Used on light surfaces when configured.'],
                            'site_logo_light' => ['Light logo', 'Used on dark surfaces when configured.'],
                            'mobile_logo' => ['Mobile logo', 'Compact mark for small screens.'],
                            'site_favicon' => ['Favicon', 'PNG, ICO, WEBP, or SVG. Recommended square.'],
                            'seo_og_image' => ['Open Graph image', 'Social sharing preview image.'],
                            'seo_twitter_image' => ['Twitter/X card image', 'Optional social image override.'],
                        ];
                        foreach ($uploads as $key => [$label, $help]):
                            $stored = $key === 'company_logo'
                                ? ($defaults('company_logo') ?: $defaults('site_logo'))
                                : $defaults($key);
                            $current = $stored !== '' ? $stored : ($key === 'company_logo' ? getLogoPath('main') : '');
                        ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="vk-upload-tile" data-upload-tile>
                                <label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                                <div class="vk-upload-preview">
                                    <?php if ($current): ?>
                                        <img src="<?= e($asset($current)) ?>" alt="<?= e($label) ?> preview" data-preview-for="<?= e($key) ?>">
                                    <?php else: ?>
                                        <span data-preview-empty="<?= e($key) ?>"><i class="bi bi-image"></i></span>
                                    <?php endif; ?>
                                </div>
                                <input class="form-control" type="file" id="<?= e($key) ?>" name="<?= e($key) ?>" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon">
                                <input type="hidden" data-setting-key="<?= e($key) ?>" data-setting-type="image" value="<?= e($current) ?>">
                                <div class="form-text"><?= e($help) ?></div>
                                <?php if ($stored): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="remove_<?= e($key) ?>" name="remove_<?= e($key) ?>" value="1">
                                        <label class="form-check-label text-danger small" for="remove_<?= e($key) ?>">Remove current file</label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="brandingAlert" class="alert d-none mt-3" role="alert"></div>
                    <button type="submit" class="btn btn-primary mt-3">Save Branding</button>
                </form>
            </div>

            <div class="tab-pane fade" id="pane-navigation" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Header & Navbar</h2><p>Menu items are managed in the Menu module; CTA and announcement content are controlled here.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6"><?php $field('navbar_cta_text', 'CTA button text'); ?></div>
                        <div class="col-md-6"><?php $field('navbar_cta_url', 'CTA link', 'url'); ?></div>
                        <div class="col-md-4"><?php $field('announcement_enabled', 'Announcement bar', 'boolean'); ?></div>
                        <div class="col-md-8"><?php $field('announcement_text', 'Announcement text'); ?></div>
                        <div class="col-12"><?php $field('announcement_url', 'Announcement link', 'url'); ?></div>
                    </div>
                    <a class="btn btn-outline-light mt-3 me-2" href="<?= e(BASE_URL) ?>/modules/menus/index.php">Manage menu order</a>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="navigation">Save Header</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-contact" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Contact & Location</h2><p>Customer-facing contact details, maps, branches, and business hours.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6"><?php $field('contact_phone', 'Primary phone'); ?></div>
                        <div class="col-md-6"><?php $field('contact_phone_alt', 'Secondary phone'); ?></div>
                        <div class="col-md-6"><?php $field('support_email', 'Support email', 'email'); ?></div>
                        <div class="col-md-6"><?php $field('sales_email', 'Sales email', 'email'); ?></div>
                        <div class="col-md-6"><?php $field('whatsapp_number', 'WhatsApp number'); ?></div>
                        <div class="col-md-6"><?php $field('business_hours', 'Business hours', 'textarea'); ?></div>
                        <div class="col-12"><?php $field('company_address', 'Company address', 'textarea'); ?></div>
                        <div class="col-12"><?php $field('google_maps_embed', 'Google Maps embed URL or iframe', 'textarea'); ?></div>
                        <div class="col-12"><?php $field('branches_json', 'Branches JSON', 'json', 'Array of branch objects: name, address, phone, maps_url.'); ?></div>
                    </div>
                    <div class="vk-map-preview mt-3" data-map-preview><span><?= $defaults('google_maps_embed') !== '' ? 'Map embed configured' : 'Map preview appears here' ?></span></div>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="contact">Save Contact</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-social" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Social Media</h2><p>Only populated links render publicly.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6"><?php $field('facebook_url', 'Facebook URL', 'url'); ?></div>
                        <div class="col-md-6"><?php $field('instagram_url', 'Instagram URL', 'url'); ?></div>
                        <div class="col-md-6"><?php $field('linkedin_url', 'LinkedIn URL', 'url'); ?></div>
                        <div class="col-md-6"><?php $field('tiktok_url', 'TikTok URL', 'url'); ?></div>
                        <div class="col-md-6"><?php $field('youtube_url', 'YouTube URL', 'url'); ?></div>
                        <div class="col-md-6"><?php $field('twitter_url', 'X/Twitter URL', 'url'); ?></div>
                    </div>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="social">Save Social</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-homepage" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Homepage Content</h2><p>Hero, CTAs, stats, service headings, and testimonial title.</p></div>
                    <div class="row g-3">
                        <div class="col-12"><?php $field('hero_title', 'Hero title'); ?></div>
                        <div class="col-12"><?php $field('hero_subtitle', 'Hero subtitle', 'textarea'); ?></div>
                        <div class="col-md-6"><?php $field('hero_primary_cta_text', 'Primary CTA text'); ?></div>
                        <div class="col-md-6"><?php $field('hero_primary_cta_url', 'Primary CTA URL', 'url'); ?></div>
                        <div class="col-md-6"><?php $field('hero_secondary_cta_text', 'Secondary CTA text'); ?></div>
                        <div class="col-md-6"><?php $field('hero_secondary_cta_url', 'Secondary CTA URL', 'url'); ?></div>
                        <div class="col-12"><?php $field('home_stats_json', 'Stats counters JSON', 'json', 'Array of objects: label, value.'); ?></div>
                        <div class="col-md-6"><?php $field('services_section_title', 'Services section title'); ?></div>
                        <div class="col-md-6"><?php $field('testimonials_title', 'Testimonials title'); ?></div>
                        <div class="col-12"><?php $field('services_section_subtitle', 'Services section subtitle', 'textarea'); ?></div>
                    </div>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="homepage">Save Homepage</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-seo" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>SEO & Structured Data</h2><p>Meta data, robots, canonical, and schema controls.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6"><?php $field('seo_site_title', 'SEO site title'); ?></div>
                        <div class="col-md-6"><?php $field('seo_canonical_url', 'Canonical URL', 'url'); ?></div>
                        <div class="col-12"><?php $field('seo_meta_description', 'Meta description', 'textarea'); ?></div>
                        <div class="col-12"><?php $field('seo_meta_keywords', 'Meta keywords'); ?></div>
                        <div class="col-md-6"><?php $field('seo_auto_enabled', 'Enable SEO auto pages', 'boolean'); ?></div>
                        <div class="col-md-6"><?php $field('seo_locations', 'SEO locations'); ?></div>
                        <div class="col-12"><?php $field('seo_service_slugs', 'SEO service slugs'); ?></div>
                        <div class="col-12"><?php $field('robots_txt', 'Robots.txt settings', 'textarea'); ?></div>
                        <div class="col-12"><?php $field('seo_schema_markup', 'Custom schema markup JSON-LD', 'textarea'); ?></div>
                    </div>
                    <div class="vk-seo-preview mt-3">
                        <span><?= e($defaults('seo_site_title', $defaults('company_name', 'VK Network'))) ?></span>
                        <strong data-seo-title><?= e($defaults('seo_site_title', $defaults('company_name', 'VK Network'))) ?></strong>
                        <p data-seo-description><?= e($defaults('seo_meta_description')) ?></p>
                    </div>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="seo">Save SEO</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-theme" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Theme Customization</h2><p>CSS variables update live in the preview and render publicly after save.</p></div>
                    <div class="row g-3">
                        <div class="col-md-4"><?php $field('theme_primary', 'Primary color', 'color'); ?></div>
                        <div class="col-md-4"><?php $field('theme_secondary', 'Secondary color', 'color'); ?></div>
                        <div class="col-md-4"><?php $field('theme_accent', 'Accent color', 'color'); ?></div>
                        <div class="col-md-4"><?php $field('theme_gradient_start', 'Gradient start', 'color'); ?></div>
                        <div class="col-md-4"><?php $field('theme_gradient_end', 'Gradient end', 'color'); ?></div>
                        <div class="col-md-4"><?php $field('theme_glow', 'Glow color', 'color'); ?></div>
                        <div class="col-md-6"><?php $field('button_style', 'Button style', 'select', '', ['pill' => 'Pill', 'soft' => 'Soft rounded', 'sharp' => 'Sharper SaaS']); ?></div>
                        <div class="col-md-6"><?php $field('card_style', 'Card style', 'select', '', ['glass' => 'Glass', 'solid' => 'Solid', 'outline' => 'Outline']); ?></div>
                    </div>
                    <div class="vk-theme-preview mt-3" data-theme-preview>
                        <button class="btn btn-primary">Preview button</button>
                        <div class="vk-theme-preview-card">Glass card preview</div>
                    </div>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="theme">Save Theme</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-email" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Email Settings</h2><p>SMTP sender and auto-reply settings. Inbox polling remains in the Email & Inbox hub.</p></div>
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label" for="smtp_host">SMTP host</label><input class="form-control" id="smtp_host" value="<?= e((string) ($smtp['smtp_host'] ?? $defaults('smtp_host'))) ?>" data-setting-key="smtp_host"></div>
                        <div class="col-md-4"><label class="form-label" for="smtp_port">SMTP port</label><input class="form-control" type="number" id="smtp_port" value="<?= e((string) ($smtp['smtp_port'] ?? $defaults('smtp_port', '587'))) ?>" data-setting-key="smtp_port"></div>
                        <div class="col-md-6"><label class="form-label" for="smtp_username">SMTP username</label><input class="form-control" id="smtp_username" value="<?= e((string) ($smtp['smtp_user'] ?? $defaults('smtp_username'))) ?>" data-setting-key="smtp_username"></div>
                        <div class="col-md-6"><label class="form-label" for="smtp_password">SMTP password</label><input class="form-control" type="password" id="smtp_password" value="" placeholder="Leave blank to keep current" data-setting-key="smtp_password"><?php if ($smtpNeedsPassword): ?><div class="form-text text-warning">Password is missing.</div><?php endif; ?></div>
                        <div class="col-md-4"><?php $field('smtp_secure', 'Encryption', 'select', '', ['tls' => 'TLS', 'ssl' => 'SSL']); ?></div>
                        <div class="col-md-4"><label class="form-label" for="email_from">From email</label><input class="form-control" type="email" id="email_from" value="<?= e((string) ($smtp['from_email'] ?? $defaults('email_from'))) ?>" data-setting-key="email_from"></div>
                        <div class="col-md-4"><label class="form-label" for="from_name">From name</label><input class="form-control" id="from_name" value="<?= e((string) ($smtp['from_name'] ?? $defaults('from_name', $defaults('company_name', 'VK Network')))) ?>" data-setting-key="from_name"></div>
                        <div class="col-md-4"><?php $field('email_autoresponder_enabled', 'Auto-responder', 'boolean'); ?></div>
                        <div class="col-md-8"><?php $field('email_autoresponder_subject', 'Auto-reply subject'); ?></div>
                        <div class="col-12"><?php $field('email_autoresponder_body', 'Auto-reply message', 'textarea'); ?></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-primary btn-save-tab" type="button" data-tab="email">Save Email</button>
                        <a class="btn btn-outline-light" href="<?= e(BASE_URL) ?>/modules/settings/email.php">Email & Inbox</a>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-security" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Security Settings</h2><p>Access-control and public-mode switches.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6"><?php $field('security_maintenance_mode', 'Maintenance mode', 'boolean'); ?></div>
                        <div class="col-md-6"><?php $field('security_readonly_staff', 'Staff settings read-only', 'boolean'); ?></div>
                    </div>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="security">Save Security</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-footer" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Footer Content</h2><p>Footer description, copyright line, and app badge links.</p></div>
                    <div class="row g-3">
                        <div class="col-12"><?php $field('footer_text', 'Footer description', 'textarea'); ?></div>
                        <div class="col-md-6"><?php $field('footer_bottom_text', 'Footer bottom text'); ?></div>
                        <div class="col-md-6"><?php $field('analytics_domain', 'Analytics domain'); ?></div>
                        <div class="col-12"><?php $field('analytics_script_src', 'Analytics script URL', 'url'); ?></div>
                    </div>
                    <button class="btn btn-primary mt-3 btn-save-tab" type="button" data-tab="footer">Save Footer</button>
                </div>
            </div>

            <div class="tab-pane fade" id="pane-backup" role="tabpanel">
                <div class="vk-settings-card">
                    <div class="vk-card-head"><h2>Backup & Restore</h2><p>Export settings to JSON, import a prior backup, or restore factory defaults.</p></div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/api/settings_export.php"><i class="bi bi-download me-1"></i>Export JSON</a>
                        <button class="btn btn-outline-warning" type="button" data-restore-defaults><i class="bi bi-arrow-counterclockwise me-1"></i>Restore defaults</button>
                    </div>
                    <form id="settingsImportForm" enctype="multipart/form-data">
                        <label class="form-label" for="settings_import_file">Import settings JSON</label>
                        <input class="form-control" type="file" id="settings_import_file" name="settings_file" accept="application/json,.json">
                        <button class="btn btn-outline-light mt-3" type="submit">Import Backup</button>
                    </form>
                    <div id="backupAlert" class="alert d-none mt-3" role="alert"></div>
                </div>
            </div>
        </section>

        <aside class="vk-settings-preview">
            <div class="vk-live-card">
                <span>Live Preview</span>
                <div class="vk-preview-brand">
                    <img src="<?= e(getLogo('main')) ?>" alt="Logo preview" data-live-logo>
                    <div>
                        <h3 data-live-company><?= e($defaults('company_name', 'VK Network')) ?></h3>
                        <p data-live-tagline><?= e($defaults('company_tagline', 'Multi-Service Solutions')) ?></p>
                    </div>
                </div>
                <div class="vk-preview-hero">
                    <h4 data-live-hero-title><?= e($defaults('hero_title')) ?></h4>
                    <p data-live-hero-subtitle><?= e($defaults('hero_subtitle')) ?></p>
                    <button type="button" data-live-cta><?= e($defaults('hero_primary_cta_text', 'Book a Service')) ?></button>
                </div>
                <div class="vk-preview-footer">
                    <p data-live-footer><?= e($defaults('footer_text')) ?></p>
                    <small data-live-contact><?= e($defaults('contact_phone')) ?> · <?= e($defaults('support_email')) ?></small>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(BASE_URL) . '/assets/js/system-settings.js"></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
