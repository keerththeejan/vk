<?php
declare(strict_types=1);
$pageTitle = 'Email & Inbox';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$pdo = db();
vk_email_tables_migrate($pdo);

$imap = vk_imap_settings_get($pdo);
$ar = vk_autoresponder_settings_get($pdo);
$smtp = vk_smtp_settings_get($pdo);

$inbound = [];
$outbound = [];
$queueFailed = [];
if (db_table_exists($pdo, 'email_inbound')) {
    $inbound = $pdo->query('SELECT id, from_email, subject, message_date, autoresponder_sent, autoresponder_skip_reason, created_at FROM email_inbound ORDER BY id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if (db_table_exists($pdo, 'email_send_log')) {
    $outbound = $pdo->query('SELECT id, template_type, to_email, subject, status, attempts, error_message, created_at, sent_at FROM email_send_log ORDER BY id DESC LIMIT 75')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
if (db_table_exists($pdo, 'email_outbound_queue')) {
    $queueFailed = $pdo->query('SELECT id, to_email, subject, attempts, last_error, created_at FROM email_outbound_queue WHERE status = \'failed\' ORDER BY id DESC LIMIT 25')->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$hasSettings = vk_settings_table_ready($pdo);
$cronSecretSet = vk_email_env_str('VK_CRON_SECRET', '') !== '' || (string) (getenv('VK_CRON_SECRET') ?: '') !== '';
$cronUrl = base_url('api/cron_email.php?token=YOUR_VK_CRON_SECRET');
?>
<?php if (!$hasSettings): ?>
<div class="alert alert-danger">Import <code>sql/upgrade_settings.sql</code> first.</div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">Email &amp; Inbox</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/settings/index.php#pane-mail">SMTP settings</a>
</div>

<?php if (!($smtp['configured'] ?? false)): ?>
<div class="alert alert-warning">Outbound SMTP is not fully configured. Configure it under <a href="<?= e(BASE_URL) ?>/modules/settings/index.php#pane-mail">System Settings → Email</a> or set <code>VK_SMTP_*</code> / <code>VK_MAIL_FROM</code> in <code>.env</code>.</div>
<?php endif; ?>

<div class="alert alert-secondary small mb-3">
    <strong>Automated bootstrap:</strong> with <code>VK_SETUP_SECRET</code> and mail vars in <code>.env</code>, call
    <code>GET <?= e(BASE_URL) ?>/setup/email-auto-config.php?token=…</code> (status) then <code>POST</code> JSON with the same token to sync env → DB, probe SMTP/IMAP/POP3, and optionally send a test message. No passwords are returned (masked only).
</div>
<div class="alert alert-info small mb-4">
    <strong>Cron:</strong> schedule <code>GET <?= e(BASE_URL) ?>/api/cron_email.php?token=…</code> every 1–5 minutes.
    Set <code>VK_CRON_SECRET</code> in <code>.env</code> and use the same value as <code>token</code>.
    <?= $cronSecretSet ? '<span class="text-success">Secret is set in the environment.</span>' : '<span class="text-warning">VK_CRON_SECRET is not set.</span>' ?>
    <div class="mt-1 text-break"><code><?= e($cronUrl) ?></code></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card vk-card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Inbound (IMAP / POP3, SSL)</h2>
                <p class="small text-muted">Uses PHP <code>imap</code> extension. <strong>IMAP</strong> <code>vkitnet.info:993</code> (SSL). If IMAP fails, the system can fall back to <strong>POP3</strong> <code>vkitnet.info:995</code> (SSL) during connection checks. Username and password are the same as SMTP (<code>info@vkitnet.info</code> + mailbox password).</p>
                <div class="mb-2 form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="imap_poll_enabled" <?= $imap['imap_enabled'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="imap_poll_enabled">Enable IMAP polling (cron)</label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="imap_host">IMAP host</label>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <input type="text" class="form-control flex-grow-1" id="imap_host" value="<?= e($imap['imap_host']) ?>" placeholder="vkitnet.info" autocomplete="off">
                        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="btnImapPresetVkitnet" title="Common preset">vkitnet.info · 993</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="imap_port">IMAP port</label>
                    <input type="number" class="form-control" id="imap_port" value="<?= e((string) $imap['imap_port']) ?>" min="1" max="65535">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="imap_username">IMAP username</label>
                    <input type="text" class="form-control" id="imap_username" value="<?= e($imap['imap_user']) ?>" autocomplete="username">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="imap_password">IMAP password</label>
                    <input type="password" class="form-control" id="imap_password" value="" placeholder="Leave blank to keep current" autocomplete="new-password">
                </div>
                <button type="button" class="btn btn-primary" id="btnSaveEmailHub">Save inbox settings</button>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card vk-card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Auto-responder</h2>
                <p class="small text-muted">One reply per sender per 24 hours. Bounces, <code>mailer-daemon</code>, <code>postmaster</code>, and auto-submitted mail are skipped.</p>
                <div class="mb-3 form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="email_autoresponder_enabled" <?= $ar['enabled'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="email_autoresponder_enabled">Enable auto-responder</label>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email_autoresponder_subject">Subject</label>
                    <input type="text" class="form-control" id="email_autoresponder_subject" value="<?= e($ar['subject']) ?>" maxlength="998">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email_autoresponder_body">Message</label>
                    <textarea class="form-control" id="email_autoresponder_body" rows="8" maxlength="20000"><?= e($ar['body']) ?></textarea>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="btnArResetDefault">Reset to default template</button>
            </div>
        </div>
    </div>
</div>

<div class="card vk-card mt-4">
    <div class="card-body">
        <h2 class="h6 mb-3">Recent inbound mail</h2>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead><tr><th>From</th><th>Subject</th><th>Date</th><th>Auto-reply</th></tr></thead>
                <tbody>
                <?php if (!$inbound): ?>
                    <tr><td colspan="4" class="text-muted small">No rows yet. Import <code>sql/upgrade_email_system.sql</code> and run cron.</td></tr>
                <?php else: ?>
                    <?php foreach ($inbound as $r): ?>
                    <tr>
                        <td class="small"><?= e((string) ($r['from_email'] ?? '')) ?></td>
                        <td class="small"><?= e((string) ($r['subject'] ?? '')) ?></td>
                        <td class="small text-nowrap"><?= e((string) ($r['message_date'] ?? '')) ?></td>
                        <td class="small"><?= !empty($r['autoresponder_sent']) ? '<span class="text-success">Sent</span>' : e((string) ($r['autoresponder_skip_reason'] ?? '—')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card vk-card mt-4">
    <div class="card-body">
        <h2 class="h6 mb-3">Outbound log</h2>
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
                <thead><tr><th>Status</th><th>Type</th><th>To</th><th>Subject</th><th>When</th><th>Error</th></tr></thead>
                <tbody>
                <?php if (!$outbound): ?>
                    <tr><td colspan="6" class="text-muted small">No sends logged yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($outbound as $r): ?>
                    <tr>
                        <td class="small"><span class="badge text-bg-<?= ($r['status'] ?? '') === 'sent' ? 'success' : (($r['status'] ?? '') === 'failed' ? 'danger' : 'secondary') ?>"><?= e((string) ($r['status'] ?? '')) ?></span></td>
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
        </div>
    </div>
</div>

<?php if ($queueFailed): ?>
<div class="card vk-card mt-4 border-danger">
    <div class="card-body">
        <h2 class="h6 mb-3 text-danger">Queue — failed deliveries</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>To</th><th>Subject</th><th>Attempts</th><th>Error</th></tr></thead>
                <tbody>
                    <?php foreach ($queueFailed as $r): ?>
                    <tr>
                        <td class="small"><?= e((string) ($r['to_email'] ?? '')) ?></td>
                        <td class="small"><?= e((string) ($r['subject'] ?? '')) ?></td>
                        <td class="small"><?= e((string) ($r['attempts'] ?? '')) ?></td>
                        <td class="small"><?= e((string) ($r['last_error'] ?? '')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/email-hub.js')) . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
