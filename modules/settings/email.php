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

<?php if ($perms['is_admin_readonly']): ?>
<div class="alert alert-info"><i class="bi bi-eye me-1"></i>Read-only mode — only Super Admin can modify SMTP settings.</div>
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

<ul class="nav nav-pills es-tabs mb-4 flex-nowrap overflow-auto" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#esTabSmtp" type="button">SMTP</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabTest" type="button">Test</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabTemplates" type="button">Templates</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabInbox" type="button">Inbox</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#esTabLogs" type="button">Logs</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="esTabSmtp">
        <div class="card vk-card">
            <div class="card-body">
                <form id="esSmtpForm" class="row g-3" novalidate>
                    <div class="col-md-8">
                        <label class="form-label" for="smtp_host">SMTP Host <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-hdd-network"></i></span>
                            <input class="form-control" id="smtp_host" name="smtp_host" value="<?= e($config['smtp_host']) ?>" required <?= $canEdit ? '' : 'readonly' ?>>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_port">Port <span class="text-danger">*</span></label>
                        <input class="form-control" type="number" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?= (int) $config['smtp_port'] ?>" required <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_username">Username</label>
                        <input class="form-control" id="smtp_username" name="smtp_username" value="<?= e($config['smtp_username']) ?>" autocomplete="off" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_password">Password</label>
                        <div class="input-group">
                            <input class="form-control" type="password" id="smtp_password" name="smtp_password" value="" placeholder="<?= $config['password_configured'] ? '•••••••• (unchanged)' : 'Enter password' ?>" autocomplete="new-password" <?= $canEdit ? '' : 'readonly' ?>>
                            <?php if ($canEdit): ?>
                            <button class="btn btn-outline-secondary" type="button" id="esTogglePassword" aria-label="Show password"><i class="bi bi-eye"></i></button>
                            <?php endif; ?>
                        </div>
                        <div class="form-text">Encrypted at rest. Never stored in HTML.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_secure">Encryption</label>
                        <select class="form-select" id="smtp_secure" name="smtp_secure" <?= $canEdit ? '' : 'disabled' ?>>
                            <option value="tls" <?= $config['smtp_secure'] === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
                            <option value="ssl" <?= $config['smtp_secure'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= $config['smtp_secure'] === 'none' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_timeout">Timeout (sec)</label>
                        <input class="form-control" type="number" id="smtp_timeout" name="smtp_timeout" min="5" max="120" value="<?= (int) $config['smtp_timeout'] ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="charset">Charset</label>
                        <input class="form-control" id="charset" name="charset" value="<?= e($config['charset']) ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email_from">Sender Email <span class="text-danger">*</span></label>
                        <input class="form-control" type="email" id="email_from" name="email_from" value="<?= e($config['email_from']) ?>" required <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="from_name">Sender Name</label>
                        <input class="form-control" id="from_name" name="from_name" value="<?= e($config['from_name']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="reply_to_email">Reply-To Email</label>
                        <input class="form-control" type="email" id="reply_to_email" name="reply_to_email" value="<?= e($config['reply_to_email']) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                    </div>
                </form>
            </div>
            <?php if ($canEdit): ?>
            <div class="card-footer es-sticky-actions d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-primary" id="esTestConnectionBtn"><i class="bi bi-plug me-1"></i>Test Connection</button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" id="esSaveSmtpBtn"><i class="bi bi-save me-1"></i>Save SMTP Settings</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="tab-pane fade" id="esTabTest">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card vk-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Send Test Email</h2>
                        <label class="form-label" for="esTestRecipient">Recipient</label>
                        <input type="email" class="form-control mb-3" id="esTestRecipient" placeholder="you@company.com" <?= $canEdit ? '' : 'readonly' ?>>
                        <?php if ($canEdit): ?>
                        <button type="button" class="btn btn-success" id="esSendTestBtn"><i class="bi bi-send me-1"></i>Send Test Email</button>
                        <?php endif; ?>
                        <ul class="list-unstyled small mt-3 mb-0" id="esTestSteps"></ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card vk-card h-100">
                    <div class="card-body">
                        <h2 class="h6">Connection Diagnostics</h2>
                        <div id="esConnectionResult" class="small text-muted">Run a connection test from the SMTP tab or click below.</div>
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

    <div class="tab-pane fade" id="esTabLogs">
        <div class="card vk-card">
            <div class="card-body table-responsive">
                <h2 class="h6">Outbound Email Log</h2>
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light sticky-top"><tr><th>Status</th><th>Type</th><th>To</th><th>Subject</th><th>When</th><th>Error</th></tr></thead>
                    <tbody id="esLogBody">
                    <?php if (!$outbound): ?>
                        <tr><td colspan="6" class="text-muted small">No sends logged yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($outbound as $r): ?>
                        <tr>
                            <td><span class="badge text-bg-<?= ($r['status'] ?? '') === 'sent' ? 'success' : (($r['status'] ?? '') === 'failed' ? 'danger' : 'secondary') ?>"><?= e((string) ($r['status'] ?? '')) ?></span></td>
                            <td class="small"><?= e((string) ($r['template_type'] ?? '')) ?></td>
                            <td class="small"><?= e((string) ($r['to_email'] ?? '')) ?></td>
                            <td class="small"><?= e((string) ($r['subject'] ?? '')) ?></td>
                            <td class="small text-nowrap"><?= e((string) ($r['sent_at'] ?? $r['created_at'] ?? '')) ?></td>
                            <td class="small text-danger"><?= e((string) ($r['error_message'] ?? '')) ?></td>
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
