<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/email_settings_service.php';

$perms = vk_email_settings_require();
$pdo = db();
vk_email_tables_migrate($pdo);

$config = vk_email_settings_form_data($pdo);
$imap = vk_imap_settings_get($pdo);
$ar = vk_autoresponder_settings_get($pdo);
$templates = vk_email_settings_templates();
$outbound = vk_email_settings_send_log($pdo, 75);
$queue = vk_email_settings_queue_list($pdo, 40);
$presets = vk_email_settings_presets();
$sysInfo = vk_email_settings_system_info();
$health = vk_email_settings_health($pdo);

$inbound = [];
if (db_table_exists($pdo, 'email_inbound')) {
    $inbound = $pdo->query('SELECT id, from_email, subject, message_date, autoresponder_sent, autoresponder_skip_reason, created_at FROM email_inbound ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$hasSettings = vk_settings_table_ready($pdo);
$cronSecretSet = vk_email_env_str('VK_CRON_SECRET', '') !== '' || (string) (getenv('VK_CRON_SECRET') ?: '') !== '';
$cronUrl = rtrim(vk_site_origin(), '/') . BASE_URL . '/api/cron_email.php?token=YOUR_VK_CRON_SECRET';
$apiUrl = base_url('api/email_settings.php');
$csrf = csrf_token();
$canEdit = $perms['can_edit'];
$pageTitle = 'Email Configuration Center';

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/email-settings.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/email-settings.js');
$extraHead = '<link href="' . e(base_url('assets/css/email-settings.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="emailSettingsApp" class="es-root"
     data-api-url="<?= e($apiUrl) ?>"
     data-csrf="<?= e($csrf) ?>"
     data-can-edit="<?= $canEdit ? '1' : '0' ?>">

<?php if (!$hasSettings): ?>
<div class="alert alert-danger">Import <code>sql/upgrade_settings.sql</code> first.</div>
<?php endif; ?>

<?php if (!$canEdit): ?>
<div class="alert alert-info"><i class="bi bi-eye me-1"></i>Read-only mode — you can view configuration but cannot modify SMTP settings.</div>
<?php endif; ?>

<section class="es-hero mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="text-primary fw-semibold small text-uppercase">Enterprise mail infrastructure</div>
            <h1 class="h2 mb-1">Email Configuration Center</h1>
            <p class="text-muted mb-0">Secure SMTP, templates, diagnostics, inbox polling, and delivery logs.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge rounded-pill <?= $config['configured'] ? 'text-bg-success' : 'text-bg-warning' ?>">
                <?= $config['configured'] ? 'SMTP Configured' : 'SMTP Incomplete' ?>
            </span>
            <?php if ($canEdit): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="esExportBtn"><i class="bi bi-download me-1"></i>Export</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="esImportBtn"><i class="bi bi-upload me-1"></i>Import</button>
            <input type="file" id="esImportFile" class="d-none" accept="application/json,.json">
            <button type="button" class="btn btn-sm btn-outline-warning" id="esRestoreBtn"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore defaults</button>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php
$badgeMap = static function (string $status): string {
    return match ($status) {
        'ok', 'configured', 'success' => 'success',
        'failed', 'incomplete' => 'danger',
        'disabled' => 'secondary',
        default => 'warning',
    };
};
?>
<div class="row g-3 mb-4" id="esHealthCards">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card vk-card h-100"><div class="card-body py-3">
            <div class="small text-muted">SMTP Status</div>
            <span class="badge text-bg-<?= e($badgeMap((string) $health['smtp_status'])) ?>"><?= e(ucfirst((string) $health['smtp_status'])) ?></span>
            <div class="small mt-1 text-truncate" title="<?= e((string) $health['host']) ?>"><?= e((string) $health['host']) ?>:<?= (int) $health['port'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card vk-card h-100"><div class="card-body py-3">
            <div class="small text-muted">DNS Status</div>
            <span class="badge text-bg-<?= e($badgeMap((string) $health['dns_status'])) ?>"><?= e(strtoupper((string) $health['dns_status'])) ?></span>
            <div class="small mt-1"><?= e((string) ($health['dns_ip'] ?: '—')) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card vk-card h-100"><div class="card-body py-3">
            <div class="small text-muted">Connection</div>
            <span class="badge text-bg-<?= e($badgeMap((string) $health['connection_status'])) ?>"><?= e(strtoupper((string) $health['connection_status'])) ?></span>
            <div class="small mt-1"><?= e((string) ($health['last_test_host'] ?: 'Run a test')) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card vk-card h-100"><div class="card-body py-3">
            <div class="small text-muted">Authentication</div>
            <span class="badge text-bg-<?= e($badgeMap((string) $health['authentication_status'])) ?>"><?= e(strtoupper((string) $health['authentication_status'])) ?></span>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card vk-card h-100"><div class="card-body py-3">
            <div class="small text-muted">SSL / TLS</div>
            <span class="badge text-bg-<?= e($badgeMap((string) $health['ssl_tls_status'])) ?>"><?= e(strtoupper((string) ($health['encryption'] ?: $health['ssl_tls_status']))) ?></span>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card vk-card h-100"><div class="card-body py-3">
            <div class="small text-muted">Last Test / Sent</div>
            <div class="small fw-semibold"><?= e((string) ($health['last_test_at'] ?: 'Never')) ?></div>
            <div class="small text-muted text-truncate"><?php
                $ls = $health['last_successful_email'] ?? null;
                echo $ls ? e('Sent: ' . ($ls['at'] ?? '') . ' → ' . ($ls['to'] ?? '')) : 'No successful send yet';
            ?></div>
        </div></div>
    </div>
</div>

<ul class="nav nav-pills es-tabs mb-4 flex-nowrap overflow-auto" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#esTabSmtp" type="button">SMTP</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabTest" type="button">Test &amp; Diagnose</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabTemplates" type="button">Templates</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabInbox" type="button">Inbox</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabQueue" type="button">Queue</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabLogs" type="button">Logs</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="esTabSmtp">
        <div class="card vk-card mb-3">
            <div class="card-body">
                <h2 class="h6 mb-2">SMTP Presets</h2>
                <p class="small text-muted mb-3">One-click host/port/encryption defaults. You still enter username and password.</p>
                <div class="d-flex flex-wrap gap-2" id="esPresetButtons">
                    <?php foreach ($presets as $p): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary es-preset"
                                data-host="<?= e($p['host']) ?>"
                                data-port="<?= (int) $p['port'] ?>"
                                data-secure="<?= e($p['secure']) ?>"
                                data-auth="<?= !empty($p['auth']) ? '1' : '0' ?>"
                                data-hint="<?= e($p['hint']) ?>"
                                <?= $canEdit ? '' : 'disabled' ?>>
                            <?= e($p['label']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="form-text mt-2" id="esPresetHint">Select a provider to autofill SMTP defaults.</div>
            </div>
        </div>
        <div class="card vk-card">
            <div class="card-body">
                <form id="esSmtpForm" class="row g-3" novalidate>
                    <div class="col-md-4">
                        <label class="form-label" for="company_name">Company Name</label>
                        <input class="form-control" id="company_name" name="company_name" value="<?= e($config['company_name']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="from_name">Sender Name</label>
                        <input class="form-control" id="from_name" name="from_name" value="<?= e($config['from_name']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email_from">Sender Email <span class="text-danger">*</span></label>
                        <input class="form-control" type="email" id="email_from" name="email_from" value="<?= e($config['email_from']) ?>" required <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="reply_to_email">Reply-To Email</label>
                        <input class="form-control" type="email" id="reply_to_email" name="reply_to_email" value="<?= e($config['reply_to_email']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="smtp_host">SMTP Host <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hdd-network"></i></span>
                            <input class="form-control" id="smtp_host" name="smtp_host" value="<?= e($config['smtp_host']) ?>" required <?= $canEdit ? '' : 'readonly' ?>>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="smtp_port">SMTP Port <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?= (int) $config['smtp_port'] ?>" required <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_username">SMTP Username</label>
                        <input class="form-control" id="smtp_username" name="smtp_username" value="<?= e($config['smtp_username']) ?>" autocomplete="off" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_password">SMTP Password</label>
                        <div class="input-group">
                            <input class="form-control" type="password" id="smtp_password" name="smtp_password" value="" placeholder="<?= $config['password_configured'] ? '•••••••• (unchanged)' : 'Enter password / App Password' ?>" autocomplete="new-password" <?= $canEdit ? '' : 'readonly' ?>>
                            <?php if ($canEdit): ?>
                            <button class="btn btn-outline-secondary" type="button" id="esTogglePassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
                            <?php endif; ?>
                        </div>
                        <div class="form-text">Encrypted at rest. Gmail requires an App Password.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_secure">Encryption</label>
                        <select class="form-select" id="smtp_secure" name="smtp_secure" <?= $canEdit ? '' : 'disabled' ?>>
                            <option value="tls" <?= $config['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (port 587)</option>
                            <option value="ssl" <?= $config['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL / SMTPS (port 465)</option>
                            <option value="none" <?= $config['smtp_secure'] === 'none' ? 'selected' : '' ?>>None (not recommended)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_timeout">Timeout (sec)</label>
                        <input class="form-control" type="number" id="smtp_timeout" name="smtp_timeout" min="5" max="120" value="<?= (int) $config['smtp_timeout'] ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="charset">Character Set</label>
                        <input class="form-control" id="charset" name="charset" value="<?= e($config['charset']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-block">Authentication</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="smtp_auth" name="smtp_auth" value="1" <?= !empty($config['smtp_auth']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="smtp_auth">Enabled</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label d-block">Debug Mode</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="smtp_debug" name="smtp_debug" value="1" <?= !empty($config['smtp_debug']) ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="smtp_debug">Show SMTP conversation</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="queue_max_retries">Queue Max Retries</label>
                        <input class="form-control" type="number" id="queue_max_retries" name="queue_max_retries" min="1" max="10" value="<?= (int) $config['queue_max_retries'] ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="queue_retry_interval">Retry Interval (sec)</label>
                        <input class="form-control" type="number" id="queue_retry_interval" name="queue_retry_interval" min="30" max="3600" value="<?= (int) $config['queue_retry_interval'] ?>" <?= $canEdit ? '' : 'readonly' ?>>
                        <div class="form-text">Base delay before exponential backoff.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="email_signature">Default Signature</label>
                        <textarea class="form-control" id="email_signature" name="email_signature" rows="3" <?= $canEdit ? '' : 'readonly' ?>><?= e($config['email_signature']) ?></textarea>
                    </div>
                </form>
            </div>
            <?php if ($canEdit): ?>
            <div class="card-footer es-sticky-actions d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-primary" id="esTestConnectionBtn"><i class="bi bi-plug me-1"></i>Test SMTP Connection</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="esSaveSmtpBtn"><i class="bi bi-save me-1"></i>Save SMTP Settings</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="card vk-card mt-3">
            <div class="card-body">
                <h2 class="h6">System Information</h2>
                <div class="row g-2 small">
                    <div class="col-md-4"><span class="text-muted">PHP</span><div class="fw-semibold"><?= e($sysInfo['php_version']) ?></div></div>
                    <div class="col-md-4"><span class="text-muted">PHPMailer</span><div class="fw-semibold"><?= e($sysInfo['phpmailer_version']) ?></div></div>
                    <div class="col-md-4"><span class="text-muted">OpenSSL</span><div class="fw-semibold"><?= e($sysInfo['openssl']) ?></div></div>
                    <div class="col-md-4"><span class="text-muted">SMTP Extension</span><div class="fw-semibold"><?= e($sysInfo['smtp_extension']) ?></div></div>
                    <div class="col-md-4"><span class="text-muted">Server Time</span><div class="fw-semibold"><?= e($sysInfo['server_time']) ?></div></div>
                    <div class="col-md-4"><span class="text-muted">Timezone</span><div class="fw-semibold"><?= e($sysInfo['timezone']) ?></div></div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="esTabTest">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card vk-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Send Test Email</h2>
                        <label class="form-label" for="esTestRecipient">Recipient Email</label>
                        <input type="email" class="form-control mb-2" id="esTestRecipient" placeholder="you@company.com" <?= $canEdit ? '' : 'readonly' ?>>
                        <label class="form-label" for="esTestSubject">Subject</label>
                        <input type="text" class="form-control mb-2" id="esTestSubject" value="VK Network — SMTP Test" <?= $canEdit ? '' : 'readonly' ?>>
                        <label class="form-label" for="esTestMessage">Message</label>
                        <textarea class="form-control mb-2" id="esTestMessage" rows="4" <?= $canEdit ? '' : 'readonly' ?>>SMTP authentication and delivery test from VK Network Email Configuration Center.</textarea>
                        <label class="form-label" for="esTestAttachment">Attachment (optional)</label>
                        <input type="file" class="form-control mb-3" id="esTestAttachment" <?= $canEdit ? '' : 'disabled' ?>>
                        <?php if ($canEdit): ?>
                        <button type="button" class="btn btn-success" id="esSendTestBtn"><i class="bi bi-send me-1"></i>Send Test Email</button>
                        <?php endif; ?>
                        <ul class="list-unstyled small mt-3 mb-0" id="esTestSteps"></ul>
                        <pre class="es-debug mt-3 d-none" id="esDebugTranscript"></pre>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card vk-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Connection Diagnostics</h2>
                        <p class="small text-muted">DNS · TCP · Greeting · Encryption · Authentication · Response time</p>
                        <div id="esConnectionResult" class="small text-muted">Run a connection test from the SMTP tab or click below.</div>
                        <div id="esReasonsBox" class="alert alert-warning small mt-3 d-none"></div>
                        <?php if ($canEdit): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="esTestConnectionBtn2">Run Connection Test</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="esTabTemplates">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="list-group" id="esTemplateList">
                    <?php foreach ($templates as $i => $tpl): ?>
                    <button type="button" class="list-group-item list-group-item-action es-template-item <?= $i === 0 ? 'active' : '' ?>" data-key="<?= e($tpl['key']) ?>">
                        <strong><?= e($tpl['name']) ?></strong><br><span class="small text-muted"><?= e($tpl['subject']) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card vk-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>HTML Preview</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary active" data-preview="desktop">Desktop</button>
                            <button type="button" class="btn btn-outline-secondary" data-preview="tablet">Tablet</button>
                            <button type="button" class="btn btn-outline-secondary" data-preview="mobile">Mobile</button>
                        </div>
                    </div>
                    <div class="card-body es-preview-wrap">
                        <iframe id="esTemplatePreview" class="es-preview-frame es-preview-desktop" title="Email template preview"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="esTabInbox">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card vk-card">
                    <div class="card-body">
                        <h2 class="h6">Inbound (IMAP)</h2>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="imap_poll_enabled" <?= $imap['imap_enabled'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="imap_poll_enabled">Enable polling (cron)</label>
                        </div>
                        <div class="mb-2"><label class="form-label" for="imap_host">Host</label><input class="form-control" id="imap_host" value="<?= e($imap['imap_host']) ?>" <?= $canEdit ? '' : 'readonly' ?>></div>
                        <div class="mb-2"><label class="form-label" for="imap_port">Port</label><input class="form-control" type="number" id="imap_port" value="<?= e((string) $imap['imap_port']) ?>" <?= $canEdit ? '' : 'readonly' ?>></div>
                        <div class="mb-2"><label class="form-label" for="imap_username">Username</label><input class="form-control" id="imap_username" value="<?= e($imap['imap_user']) ?>" <?= $canEdit ? '' : 'readonly' ?>></div>
                        <div class="mb-3"><label class="form-label" for="imap_password">Password</label><input class="form-control" type="password" id="imap_password" placeholder="Leave blank to keep" <?= $canEdit ? '' : 'readonly' ?>></div>
                        <?php if ($canEdit): ?><button type="button" class="btn btn-primary" id="esSaveInboxBtn">Save Inbox Settings</button><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card vk-card">
                    <div class="card-body">
                        <h2 class="h6">Auto-responder</h2>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="email_autoresponder_enabled" <?= $ar['enabled'] ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="email_autoresponder_enabled">Enabled</label>
                        </div>
                        <div class="mb-2"><label class="form-label" for="email_autoresponder_subject">Subject</label><input class="form-control" id="email_autoresponder_subject" value="<?= e($ar['subject']) ?>" <?= $canEdit ? '' : 'readonly' ?>></div>
                        <div class="mb-2"><label class="form-label" for="email_autoresponder_body">Message</label><textarea class="form-control" id="email_autoresponder_body" rows="6" <?= $canEdit ? '' : 'readonly' ?>><?= e($ar['body']) ?></textarea></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="alert alert-info small mt-3">
            Cron: <code><?= e($cronUrl) ?></code>
            <?= $cronSecretSet ? ' · Secret configured' : ' · Set VK_CRON_SECRET in .env' ?>
        </div>
    </div>

    <div class="tab-pane fade" id="esTabQueue">
        <div class="card vk-card">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="h6 mb-0">Email Queue</h2>
                        <p class="small text-muted mb-0">Automatic retries for failed/pending outbound mail.</p>
                    </div>
                    <?php if ($canEdit): ?>
                    <button type="button" class="btn btn-sm btn-primary" id="esProcessQueueBtn"><i class="bi bi-play-fill me-1"></i>Process Queue Now</button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Status</th><th>Recipient</th><th>Subject</th><th>Attempts</th><th>Next Try</th><th>Error</th><th></th></tr></thead>
                        <tbody id="esQueueBody">
                        <?php if (!$queue): ?>
                            <tr><td colspan="7" class="text-muted small">Queue is empty.</td></tr>
                        <?php else: ?>
                            <?php foreach ($queue as $q): ?>
                                <?php
                                $qs = (string) ($q['status'] ?? '');
                                $badge = match ($qs) {
                                    'sent' => 'success',
                                    'pending' => 'warning',
                                    'processing' => 'primary',
                                    'failed' => 'danger',
                                    default => 'secondary',
                                };
                                ?>
                                <tr>
                                    <td><span class="badge text-bg-<?= e($badge) ?>"><?= e($qs) ?></span></td>
                                    <td class="small"><?= e((string) ($q['to_email'] ?? '')) ?></td>
                                    <td class="small"><?= e((string) ($q['subject'] ?? '')) ?></td>
                                    <td class="small"><?= (int) ($q['attempts'] ?? 0) ?>/<?= (int) ($q['max_attempts'] ?? 0) ?></td>
                                    <td class="small"><?= e((string) ($q['next_attempt_at'] ?? '—')) ?></td>
                                    <td class="small text-danger"><?= e((string) ($q['last_error'] ?? '')) ?></td>
                                    <td>
                                        <?php if ($canEdit && in_array($qs, ['failed', 'pending'], true)): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary es-queue-retry" data-id="<?= (int) $q['id'] ?>">Retry</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="esTabLogs">
        <div class="card vk-card">
            <div class="card-body table-responsive">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h6 mb-0">Outbound Email Log</h2>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('api/email_settings.php?action=logs')) ?>" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i>Download Log</a>
                </div>
                <table class="table table-sm table-hover align-middle mb-0 es-log-table">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Sent By</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Delivery</th>
                            <th>SMTP Server</th>
                            <th>Response</th>
                            <th>Message ID</th>
                            <th>Retries</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="esLogBody">
                    <?php if (!$outbound): ?>
                        <tr><td colspan="13" class="text-muted small">No sends logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($outbound as $r): ?>
                            <?php
                            $st = (string) ($r['status'] ?? '');
                            $badge = match ($st) {
                                'sent' => 'success',
                                'queued' => 'warning',
                                'sending', 'processing' => 'primary',
                                'failed' => 'danger',
                                'cancelled' => 'secondary',
                                default => 'secondary',
                            };
                            $created = (string) ($r['created_at'] ?? '');
                            $date = $created !== '' ? substr($created, 0, 10) : '—';
                            $time = strlen($created) >= 16 ? substr($created, 11, 8) : '—';
                            $delivery = isset($r['delivery_ms']) && $r['delivery_ms'] !== null && $r['delivery_ms'] !== ''
                                ? ((int) $r['delivery_ms']) . ' ms'
                                : '—';
                            $response = (string) ($r['smtp_response'] ?? $r['error_message'] ?? '—');
                            if ($response === '') {
                                $response = '—';
                            }
                            ?>
                        <tr data-log-id="<?= (int) ($r['id'] ?? 0) ?>"
                            data-to="<?= e((string) ($r['to_email'] ?? '')) ?>"
                            data-subject="<?= e((string) ($r['subject'] ?? '')) ?>"
                            data-body="<?= e((string) ($r['body_preview'] ?? '')) ?>"
                            data-error="<?= e((string) ($r['error_message'] ?? '')) ?>"
                            data-status="<?= e($st) ?>">
                            <td><span class="badge text-bg-<?= e($badge) ?>"><?= e($st !== '' ? ucfirst($st) : '—') ?></span></td>
                            <td class="small"><?= e((string) ($r['template_type'] ?? '')) ?></td>
                            <td class="small"><?= e((string) ($r['to_email'] ?? '')) ?></td>
                            <td class="small"><?= e((string) ($r['subject'] ?? '')) ?></td>
                            <td class="small"><?= e((string) ($r['sent_by'] ?? '—')) ?></td>
                            <td class="small text-nowrap"><?= e($date) ?></td>
                            <td class="small text-nowrap"><?= e($time) ?></td>
                            <td class="small text-nowrap"><?= e($delivery) ?></td>
                            <td class="small"><?= e((string) ($r['smtp_server'] ?? ($config['smtp_host'] !== '' ? $config['smtp_host'] : '—'))) ?></td>
                            <td class="small text-truncate" style="max-width:140px" title="<?= e($response) ?>"><?= e(mb_substr($response, 0, 80)) ?></td>
                            <td class="small text-truncate" style="max-width:120px" title="<?= e((string) ($r['message_id'] ?? '')) ?>"><?= e((string) ($r['message_id'] ?? '—')) ?></td>
                            <td class="small"><?= (int) ($r['attempts'] ?? 0) ?></td>
                            <td class="text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-secondary es-log-view" data-id="<?= (int) ($r['id'] ?? 0) ?>">View</button>
                                <?php if ($canEdit): ?>
                                <?php if ($st === 'failed'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning es-log-edit" data-id="<?= (int) ($r['id'] ?? 0) ?>">Edit &amp; Retry</button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-primary es-log-resend" data-id="<?= (int) ($r['id'] ?? 0) ?>">Resend</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-danger es-log-delete" data-id="<?= (int) ($r['id'] ?? 0) ?>">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($inbound): ?>
                <h2 class="h6 mt-4">Recent Inbound (IMAP)</h2>
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>From</th><th>Subject</th><th>Date</th><th>Auto-reply</th></tr></thead>
                    <tbody>
                    <?php foreach ($inbound as $r): ?>
                        <tr>
                            <td class="small"><?= e((string) ($r['from_email'] ?? '')) ?></td>
                            <td class="small"><?= e((string) ($r['subject'] ?? '')) ?></td>
                            <td class="small text-nowrap"><?= e((string) ($r['message_date'] ?? $r['created_at'] ?? '')) ?></td>
                            <td class="small"><?= !empty($r['autoresponder_sent']) ? 'Sent' : e((string) ($r['autoresponder_skip_reason'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="esLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="esLogModalTitle">Email details</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="esLogViewPane" class="d-none">
                    <dl class="row small mb-0">
                        <dt class="col-sm-3">Status</dt><dd class="col-sm-9" id="esLogViewStatus"></dd>
                        <dt class="col-sm-3">Recipient</dt><dd class="col-sm-9" id="esLogViewTo"></dd>
                        <dt class="col-sm-3">Subject</dt><dd class="col-sm-9" id="esLogViewSubject"></dd>
                        <dt class="col-sm-3">Message</dt><dd class="col-sm-9"><pre class="es-debug mb-0" id="esLogViewBody"></pre></dd>
                        <dt class="col-sm-3">Error</dt><dd class="col-sm-9 text-danger" id="esLogViewError"></dd>
                    </dl>
                </div>
                <div id="esLogEditPane" class="d-none">
                    <div class="mb-2">
                        <label class="form-label" for="esEditTo">Recipient Email</label>
                        <input type="email" class="form-control" id="esEditTo">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="esEditSubject">Subject</label>
                        <input type="text" class="form-control" id="esEditSubject">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="esEditMessage">Message</label>
                        <textarea class="form-control" id="esEditMessage" rows="6"></textarea>
                    </div>
                    <div class="alert alert-warning small d-none" id="esEditErrorBox"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning d-none" id="esLogRetryBtn">Retry Sending</button>
            </div>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3 es-loading-toast d-none" id="esLoadingToast">
    <div class="d-flex align-items-center gap-2 bg-body border rounded shadow px-3 py-2">
        <div class="spinner-border spinner-border-sm text-primary"></div>
        <span id="esLoadingText">Processing…</span>
    </div>
</div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/email-settings.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
