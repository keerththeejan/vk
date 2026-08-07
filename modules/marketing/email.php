<?php
declare(strict_types=1);
$pageTitle = 'Email Builder';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/marketing/email.php');
    }
    $key = strtolower(preg_replace('/[^a-z0-9_]+/', '_', trim((string) $_POST['template_key']))) ?: 'template_' . time();
    $st = $pdo->prepare(
        'INSERT INTO marketing_email_templates (template_key, template_name, category, subject, preheader, html_body, text_body, variables, status)
         VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE template_name=VALUES(template_name), category=VALUES(category), subject=VALUES(subject), preheader=VALUES(preheader), html_body=VALUES(html_body), text_body=VALUES(text_body), variables=VALUES(variables), status=VALUES(status)'
    );
    $st->execute([$key, trim((string) $_POST['template_name']), (string) $_POST['category'], trim((string) $_POST['subject']), trim((string) $_POST['preheader']), (string) $_POST['html_body'], (string) $_POST['text_body'], trim((string) $_POST['variables']), (string) $_POST['status']]);
    flash_set('success', 'Responsive email template saved.');
    redirect('/modules/marketing/email.php');
}

$templates = $pdo->query('SELECT * FROM marketing_email_templates ORDER BY updated_at DESC')->fetchAll(PDO::FETCH_ASSOC);
$sent = db_table_exists($pdo, 'email_send_log') ? vk_count_table($pdo, 'email_send_log', "status = 'sent'") : 0;
$queued = db_table_exists($pdo, 'email_outbound_queue') ? vk_count_table($pdo, 'email_outbound_queue', "status = 'pending'") : 0;
$failed = db_table_exists($pdo, 'email_outbound_queue') ? vk_count_table($pdo, 'email_outbound_queue', "status = 'failed'") : 0;
$sampleHtml = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Inter,Arial,sans-serif;background:#07111f;color:#fff;padding:24px"><tr><td style="max-width:640px;margin:auto;border:1px solid rgba(255,255,255,.16);border-radius:24px;padding:28px;background:#0f1f38"><h1 style="margin:0 0 12px">Hello {{customer_name}}</h1><p style="color:#cbd5e1">Your {{service_name}} update is ready.</p><a href="{{cta_url}}" style="background:#2f7cff;color:#fff;padding:12px 18px;border-radius:14px;text-decoration:none;font-weight:700">View update</a><p style="color:#94a3b8;margin-top:24px">VK IT Network · {{company_phone}}</p></td></tr></table>';
?>
<div class="vk-suite-page">
    <section class="vk-suite-hero mb-4">
        <div><span class="vk-suite-kicker"><i class="bi bi-envelope-heart"></i> Auto Responsive Email</span><h1>Email builder dashboard</h1><p>Create branded responsive templates for welcome emails, booking confirmations, invoices, service completion, warranty reminders, payment reminders, newsletters, and marketing journeys.</p></div>
    </section>
    <div class="vk-suite-kpis mb-4">
        <div class="vk-suite-kpi"><i class="bi bi-layout-text-window"></i><span>Templates</span><strong><?= count($templates) ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-send-check"></i><span>Delivered</span><strong><?= $sent ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-hourglass-split"></i><span>Queue</span><strong><?= $queued ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-exclamation-octagon"></i><span>Failed</span><strong><?= $failed ?></strong></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-5">
            <form class="card vk-card h-100" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="card-header bg-transparent fw-semibold">Template manager</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Template key</label><input class="form-control" name="template_key" placeholder="welcome_modern"></div>
                        <div class="col-md-6"><label class="form-label">Category</label><select class="form-select" name="category"><option>welcome</option><option>booking</option><option>invoice</option><option>completion</option><option>warranty</option><option>payment</option><option>newsletter</option><option>campaign</option></select></div>
                    </div>
                    <div class="mt-3"><label class="form-label">Template name</label><input class="form-control" name="template_name" required></div>
                    <div class="mt-3"><label class="form-label">Subject</label><input class="form-control" name="subject" required></div>
                    <div class="mt-3"><label class="form-label">Preheader</label><input class="form-control" name="preheader"></div>
                    <div class="mt-3"><label class="form-label">Responsive HTML body</label><textarea class="form-control font-monospace" rows="9" name="html_body"><?= e($sampleHtml) ?></textarea></div>
                    <div class="mt-3"><label class="form-label">Plain text fallback</label><textarea class="form-control" rows="3" name="text_body">Hello {{customer_name}}, your update is ready.</textarea></div>
                    <div class="mt-3"><label class="form-label">Dynamic variables</label><input class="form-control" name="variables" value="{{customer_name}}, {{service_name}}, {{cta_url}}, {{company_phone}}"></div>
                    <div class="mt-3"><label class="form-label">Status</label><select class="form-select" name="status"><option>active</option><option>draft</option><option>archived</option></select></div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary" type="submit">Save template</button></div>
            </form>
        </div>
        <div class="col-xl-7">
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Delivery status & workflow coverage</div>
                <div class="card-body">
                    <div class="vk-email-workflows">
                        <?php foreach (['Welcome', 'Booking confirmation', 'Invoice', 'Service completion', 'Warranty reminder', 'Payment reminder', 'Newsletter'] as $wf): ?>
                            <div><i class="bi bi-check2-circle"></i><span><?= e($wf) ?></span><small>Responsive · branded · variable-ready</small></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Templates</div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-stack">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Template</th><th>Category</th><th>Status</th><th>Variables</th></tr></thead>
                            <tbody>
                            <?php foreach ($templates as $t): ?>
                                <tr><td><strong><?= e((string) $t['template_name']) ?></strong><div class="small text-muted"><?= e((string) $t['subject']) ?></div></td><td><span class="badge text-bg-info"><?= e((string) $t['category']) ?></span></td><td><span class="badge text-bg-success"><?= e((string) $t['status']) ?></span></td><td class="small"><?= e((string) $t['variables']) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
