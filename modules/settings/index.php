<?php
declare(strict_types=1);
$pageTitle = 'System Settings';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$pdo = db();
$s = vk_settings_all($pdo);
$smtp = vk_smtp_settings_get($pdo);
$ar = vk_autoresponder_settings_get($pdo);
$smtpNeedsPassword = ((string) ($smtp['smtp_user'] ?? '')) !== '' && trim((string) ($smtp['smtp_pass'] ?? '')) === '';
$defaults = static function (string $k, string $d = '') use ($s): string {
    return array_key_exists($k, $s) ? (string) $s[$k] : $d;
};

$hasTable = vk_settings_table_ready($pdo);
?>
<?php if (!$hasTable): ?>
<div class="alert alert-danger">
    <strong>Settings table missing.</strong> Import <code>sql/upgrade_settings.sql</code> into your database, then reload this page.
</div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">System Settings</h1>
    <span class="text-muted small">Saves via AJAX — no page reload</span>
</div>
<?php if (!($smtp['configured'] ?? false)): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center">
    <span><i class="bi bi-exclamation-triangle me-2"></i>Email system not configured.</span>
    <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="tab" data-bs-target="#pane-mail">Open Email Settings</button>
</div>
<?php endif; ?>
<div class="alert alert-light border mb-3 small">
    <strong>Inbox, auto-reply, logs:</strong>
    <a href="<?= e(BASE_URL) ?>/modules/settings/email.php">Email &amp; Inbox</a>
    — IMAP polling, templates, delivery log. Schedule <code><?= e(BASE_URL) ?>/api/cron_email.php</code> with <code>VK_CRON_SECRET</code>.
</div>

<ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-general" data-bs-toggle="tab" data-bs-target="#pane-general" type="button" role="tab">General</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-branding" data-bs-toggle="tab" data-bs-target="#pane-branding" type="button" role="tab">Branding</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-seo" data-bs-toggle="tab" data-bs-target="#pane-seo" type="button" role="tab">SEO</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-wa" data-bs-toggle="tab" data-bs-target="#pane-wa" type="button" role="tab">WhatsApp</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-mail" data-bs-toggle="tab" data-bs-target="#pane-mail" type="button" role="tab">Email</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="pane-general" role="tabpanel">
        <div class="card vk-card vk-settings-card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="site_name">Site name</label>
                    <input type="text" class="form-control" id="site_name" value="<?= e($defaults('site_name', 'VK Network')) ?>" maxlength="255" autocomplete="organization">
                    <div class="form-text">Shown in admin, public navbar, and SEO site name.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="analytics_domain">Plausible domain (optional)</label>
                    <input type="text" class="form-control" id="analytics_domain" value="<?= e($defaults('analytics_domain')) ?>" placeholder="example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="analytics_script_src">Analytics script URL</label>
                    <input type="text" class="form-control" id="analytics_script_src" value="<?= e($defaults('analytics_script_src', 'https://plausible.io/js/script.js')) ?>">
                </div>
                <button type="button" class="btn btn-primary btn-save-tab" data-tab="general">Save</button>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pane-branding" role="tabpanel">
        <div class="card vk-card vk-settings-card">
            <div class="card-body">
                <h2 class="h6 mb-3">Site Branding</h2>
                <form id="brandingForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label" for="site_title">Site Title</label>
                        <input type="text" class="form-control" id="site_title" name="site_title" value="<?= e($defaults('site_title', $defaults('site_name', 'VK Network'))) ?>" maxlength="255" placeholder="Your Site Name">
                        <div class="form-text">Displayed in browser tab and header.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="site_logo">Site Logo</label>
                            <input type="file" class="form-control" id="site_logo" name="site_logo" accept="image/png,image/jpeg,image/svg+xml">
                            <div class="form-text">PNG, JPG, SVG (max 2MB). Recommended: 200x60px</div>
                            <?php if ($defaults('site_logo')): ?>
                            <div class="mt-2">
                                <img src="<?= e(base_url($defaults('site_logo'))) ?>" alt="Current Logo" class="img-thumbnail" style="max-height:60px;max-width:200px;">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                                    <label class="form-check-label small text-danger" for="remove_logo">Remove logo</label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="site_favicon">Favicon</label>
                            <input type="file" class="form-control" id="site_favicon" name="site_favicon" accept="image/x-icon,image/png">
                            <div class="form-text">ICO or PNG (max 1MB). Recommended: 32x32px or 64x64px</div>
                            <?php if ($defaults('site_favicon')): ?>
                            <div class="mt-2">
                                <img src="<?= e(base_url($defaults('site_favicon'))) ?>" alt="Current Favicon" class="img-thumbnail" style="max-height:32px;max-width:32px;">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="remove_favicon" name="remove_favicon" value="1">
                                    <label class="form-check-label small text-danger" for="remove_favicon">Remove favicon</label>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="brandingAlert" class="alert d-none" role="alert"></div>
                    <button type="submit" class="btn btn-primary">Save Branding</button>
                </form>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pane-seo" role="tabpanel">
        <div class="card vk-card vk-settings-card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="seo_site_title">Site title (prefix)</label>
                    <input type="text" class="form-control" id="seo_site_title" value="<?= e($defaults('seo_site_title')) ?>" maxlength="255" placeholder="Optional — overrides default brand in page titles">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="seo_meta_description">Meta description</label>
                    <textarea class="form-control" id="seo_meta_description" rows="3" maxlength="1024"><?= e($defaults('seo_meta_description')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="seo_meta_keywords">Meta keywords</label>
                    <input type="text" class="form-control" id="seo_meta_keywords" value="<?= e($defaults('seo_meta_keywords')) ?>" maxlength="512">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="seo_og_image">OG image URL</label>
                    <input type="text" class="form-control" id="seo_og_image" value="<?= e($defaults('seo_og_image')) ?>" maxlength="512" placeholder="/assets/images/... or https://...">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="seo_auto_enabled" <?= $defaults('seo_auto_enabled', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="seo_auto_enabled">Enable SEO auto-config (local keyword booster)</label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="seo_locations">SEO locations (comma separated slugs)</label>
                    <input type="text" class="form-control" id="seo_locations" value="<?= e($defaults('seo_locations', 'jaffna,vavuniya,kilinochchi')) ?>" placeholder="jaffna,vavuniya,kilinochchi">
                    <div class="form-text">Used for auto local landing pages and sitemap.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="seo_service_slugs">SEO service slugs (comma separated)</label>
                    <input type="text" class="form-control" id="seo_service_slugs" value="<?= e($defaults('seo_service_slugs', 'computer-repair,laptop-repair,printer-repair,it-service')) ?>" placeholder="computer-repair,laptop-repair,printer-repair,it-service">
                </div>
                <button type="button" class="btn btn-primary btn-save-tab" data-tab="seo">Save</button>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pane-wa" role="tabpanel">
        <div class="card vk-card vk-settings-card">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="whatsapp_number">WhatsApp number</label>
                    <input type="text" class="form-control" id="whatsapp_number" value="<?= e($defaults('whatsapp_number')) ?>" maxlength="32" placeholder="9477XXXXXXX or 077...">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="whatsapp_default_message">Default message template</label>
                    <textarea class="form-control" id="whatsapp_default_message" rows="4" maxlength="2000"><?= e($defaults('whatsapp_default_message')) ?></textarea>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-save-tab" data-tab="whatsapp">Save</button>
                    <button type="button" class="btn btn-success" id="btnTestWhatsapp"><i class="bi bi-whatsapp me-1"></i>Test WhatsApp</button>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pane-mail" role="tabpanel">
        <div class="card vk-card vk-settings-card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-2">vkitnet.info mail server (reference)</h2>
                <p class="small text-muted mb-2">IMAP, POP3, and SMTP use authentication. SSL/TLS only.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 small">
                        <thead class="table-light"><tr><th>Service</th><th>Host</th><th>Port</th><th>Encryption</th></tr></thead>
                        <tbody>
                            <tr><td>SMTP (outgoing)</td><td><code>vkitnet.info</code></td><td><code>465</code></td><td>SSL</td></tr>
                            <tr><td>IMAP (incoming)</td><td><code>vkitnet.info</code></td><td><code>993</code></td><td>SSL</td></tr>
                            <tr><td>POP3 (incoming, fallback)</td><td><code>vkitnet.info</code></td><td><code>995</code></td><td>SSL</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">Username is the full address (e.g. <code>info@vkitnet.info</code>). Configure IMAP polling and logs under <a href="<?= e(BASE_URL) ?>/modules/settings/email.php">Email &amp; Inbox</a>.</p>
            </div>
        </div>
        <div class="card vk-card vk-settings-card">
            <div class="card-body">
                <h2 class="h6 mb-3">Outgoing (SMTP)</h2>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="smtp_host">SMTP host</label>
                        <input type="text" class="form-control" id="smtp_host" value="<?= e((string) ($smtp['smtp_host'] ?? $defaults('smtp_host'))) ?>" placeholder="smtp.gmail.com" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_port">SMTP port</label>
                        <input type="number" class="form-control" id="smtp_port" value="<?= e((string) ($smtp['smtp_port'] ?? $defaults('smtp_port', '587'))) ?>" min="1" max="65535">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_username">SMTP username</label>
                        <input type="text" class="form-control" id="smtp_username" value="<?= e((string) ($smtp['smtp_user'] ?? $defaults('smtp_username'))) ?>" autocomplete="username">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_password">SMTP password</label>
                        <input type="password" class="form-control" id="smtp_password" value="" placeholder="Leave blank to keep current" autocomplete="new-password">
                        <?php if ($smtpNeedsPassword): ?>
                        <div class="form-text text-warning">No password is stored yet. Enter your mailbox password and click <strong>Save SMTP + auto-reply</strong> before “Send test email”.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_secure">Encryption</label>
                        <select class="form-select" id="smtp_secure">
                            <option value="tls" <?= (($smtp['smtp_secure'] ?? $defaults('smtp_secure', 'tls')) === 'tls') ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= (($smtp['smtp_secure'] ?? $defaults('smtp_secure', 'tls')) === 'ssl') ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="email_from">From email</label>
                        <input type="email" class="form-control" id="email_from" value="<?= e((string) ($smtp['from_email'] ?? $defaults('email_from'))) ?>" placeholder="noreply@yourdomain.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="from_name">From name</label>
                        <input type="text" class="form-control" id="from_name" value="<?= e((string) ($smtp['from_name'] ?? $defaults('from_name', $defaults('site_name', 'VK Network')))) ?>" placeholder="VK Transport Service">
                    </div>
                </div>
                <div class="mt-2 d-flex gap-2 flex-wrap align-items-center">
                    <button type="button" class="btn btn-sm btn-primary" id="btnSmtpPresetVkitnet" title="info@vkitnet.info · SSL 465"><i class="bi bi-envelope-at me-1"></i>VK IT mail (vkitnet.info)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSmtpPresetGmail"><i class="bi bi-google me-1"></i>Use Gmail</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSmtpPresetOutlook"><i class="bi bi-microsoft me-1"></i>Use Outlook</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSmtpPresetCpanel"><i class="bi bi-globe2 me-1"></i>Use cPanel (auto from email)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnSmtpPresetSsl465" title="SSL on port 465">SSL · 465 only</button>
                </div>
                <p class="small text-muted mt-3 mb-0">Requires <code>composer install</code> for PHPMailer. Passwords with special characters: save in the form or use quoted values in <code>.env</code>.</p>
            </div>
        </div>
        <div class="card vk-card vk-settings-card mt-3">
            <div class="card-body">
                <h2 class="h6 mb-2">Auto-reply to incoming mail</h2>
                <p class="small text-muted">Replies when <strong>Email &amp; Inbox</strong> cron fetches new messages. One reply per sender per 24h; bounces and system senders are skipped.</p>
                <div class="mb-3 form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="email_autoresponder_enabled" <?= $ar['enabled'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="email_autoresponder_enabled">Enable auto-responder</label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email_autoresponder_subject">Auto-reply subject</label>
                    <input type="text" class="form-control" id="email_autoresponder_subject" value="<?= e($ar['subject']) ?>" maxlength="998">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email_autoresponder_body">Auto-reply message</label>
                    <textarea class="form-control" id="email_autoresponder_body" rows="6" maxlength="20000"><?= e($ar['body']) ?></textarea>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
                    <button type="button" class="btn btn-primary btn-save-tab" data-tab="email">Save SMTP + auto-reply</button>
                    <div class="flex-grow-1" style="min-width: 200px;">
                        <label class="form-label small mb-0" for="mail_test_to">Send test to</label>
                        <input type="email" class="form-control form-control-sm" id="mail_test_to" value="" placeholder="recipient@example.com" autocomplete="email">
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="btnMailTest"><i class="bi bi-envelope-check me-1"></i>Send test email</button>
                </div>
                <a class="btn btn-link btn-sm ps-0" href="<?= e(BASE_URL) ?>/modules/settings/email.php">Email &amp; Inbox — IMAP 993, POP3 995 fallback, logs, cron</a>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(BASE_URL) . '/assets/js/system-settings.js"></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
