<?php
declare(strict_types=1);
$pageTitle = 'WhatsApp Automation';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';
vk_marketing_suite_seed($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/whatsapp/index.php');
    }
    $st = $pdo->prepare(
        'INSERT INTO whatsapp_logs (phone, template_name, message_preview, direction, status, sent_at)
         VALUES (?,?,?,?,?,NOW())'
    );
    $st->execute([
        trim((string) $_POST['phone']),
        trim((string) $_POST['template_name']),
        mb_substr(trim((string) $_POST['message_preview']), 0, 500, 'UTF-8'),
        'outbound',
        (string) $_POST['status'],
    ]);
    flash_set('success', 'WhatsApp automation log queued.');
    redirect('/modules/whatsapp/index.php');
}

$sent = vk_count_table($pdo, 'whatsapp_logs', "status IN ('sent','delivered','read')");
$delivered = vk_count_table($pdo, 'whatsapp_logs', "status IN ('delivered','read')");
$read = vk_count_table($pdo, 'whatsapp_logs', "status = 'read'");
$activeChats = vk_count_table($pdo, 'whatsapp_logs', "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$deliveryRate = $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0;
$responseRate = $delivered > 0 ? round(($read / $delivered) * 100, 1) : 0;
$rows = $pdo->query('SELECT * FROM whatsapp_logs ORDER BY id DESC LIMIT 40')->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="vk-suite-page">
    <section class="vk-suite-hero mb-4">
        <div><span class="vk-suite-kicker"><i class="bi bi-whatsapp"></i> WhatsApp Automation</span><h1>Customer messaging command center</h1><p>Automate booking confirmations, service reminders, invoice notifications, warranty alerts, marketing broadcasts, delivery tracking, read receipts, and support chat workflows.</p></div>
        <div class="vk-suite-hero-actions"><a class="btn btn-primary" href="<?= e(BASE_URL) ?>/modules/bookings/list.php"><i class="bi bi-calendar2-check me-2"></i>Booking replies</a></div>
    </section>
    <div class="vk-suite-kpis mb-4">
        <div class="vk-suite-kpi"><i class="bi bi-send-check"></i><span>Messages sent</span><strong><?= $sent ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-check2-all"></i><span>Delivery rate</span><strong><?= $deliveryRate ?>%</strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-reply"></i><span>Response rate</span><strong><?= $responseRate ?>%</strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-chat-dots"></i><span>Active chats</span><strong><?= $activeChats ?></strong></div>
        <div class="vk-suite-kpi"><i class="bi bi-graph-up-arrow"></i><span>Campaign performance</span><strong><?= max(0, $read * 3) ?>%</strong></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-4">
            <form class="card vk-card h-100" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <div class="card-header bg-transparent fw-semibold">Automation template test</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" placeholder="+9477..." required></div>
                    <div class="mb-3"><label class="form-label">Template</label><select class="form-select" name="template_name"><option>booking_confirmation</option><option>service_reminder</option><option>invoice_notification</option><option>warranty_alert</option><option>marketing_broadcast</option><option>ai_auto_reply</option></select></div>
                    <div class="mb-3"><label class="form-label">Message preview</label><textarea class="form-control" rows="5" name="message_preview">Hello {{customer_name}}, your VK IT service update is ready.</textarea></div>
                    <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status"><option>queued</option><option>sent</option><option>delivered</option><option>read</option><option>failed</option></select></div>
                    <div class="alert alert-info small">Connect official WhatsApp Cloud API credentials in settings to turn these workflows into live provider sends.</div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary" type="submit">Queue template log</button></div>
            </form>
        </div>
        <div class="col-xl-8">
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Smart chatbot placeholder</div>
                <div class="card-body">
                    <div class="vk-chat-preview">
                        <div class="vk-chat-bubble inbound">Customer asks about repair status</div>
                        <div class="vk-chat-bubble outbound">AI assistant checks booking or job status and replies with next action.</div>
                        <div class="vk-chat-bubble outbound">Escalates to support when confidence is low.</div>
                    </div>
                </div>
            </div>
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Delivery tracking</div>
                <div class="card-body p-0">
                    <div class="table-responsive table-responsive-stack">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Phone</th><th>Template</th><th>Status</th><th>Message</th><th>When</th></tr></thead>
                            <tbody>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td><?= e((string) $r['phone']) ?></td>
                                    <td><span class="badge text-bg-info"><?= e((string) $r['template_name']) ?></span></td>
                                    <td><span class="badge text-bg-<?= ($r['status'] ?? '') === 'failed' ? 'danger' : 'success' ?>"><?= e((string) $r['status']) ?></span></td>
                                    <td class="small"><?= e((string) $r['message_preview']) ?></td>
                                    <td class="small text-nowrap"><?= e((string) $r['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No WhatsApp automation logs yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
