<?php
declare(strict_types=1);

$pageTitle = 'WhatsApp Automation';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
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

$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-7 days'));

$vkWaInitials = static function (string $label): string {
    $parts = preg_split('/\s+/', trim($label)) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr((string) $parts[0], 0, 1) . substr((string) $parts[1], 0, 1));
    }
    $digits = preg_replace('/\D+/', '', $label) ?? '';
    return strtoupper(substr($digits !== '' ? $digits : $label, -2));
};

$vkWaTemplateType = static function (?string $template): array {
    return match ((string) $template) {
        'booking_confirmation', 'ai_auto_reply' => ['key' => 'support', 'label' => 'Support'],
        'marketing_broadcast' => ['key' => 'sales', 'label' => 'Sales'],
        'service_reminder' => ['key' => 'maintenance', 'label' => 'Maintenance'],
        'invoice_notification' => ['key' => 'invoice', 'label' => 'Invoice'],
        'warranty_alert' => ['key' => 'reminder', 'label' => 'Reminder'],
        default => ['key' => 'repair', 'label' => 'Repair'],
    };
};

$vkWaStatusIcon = static function (string $status, string $direction): string {
    if ($status === 'failed') {
        return '<i class="bi bi-exclamation-circle vk-wa-status-icon vk-wa-status-failed" title="Failed"></i>';
    }
    if ($direction === 'inbound') {
        return '';
    }
    return match ($status) {
        'queued' => '<i class="bi bi-clock vk-wa-status-icon" title="Queued"></i>',
        'sent' => '<i class="bi bi-check vk-wa-status-icon" title="Sent"></i>',
        'delivered' => '<i class="bi bi-check-all vk-wa-status-icon" title="Delivered"></i>',
        'read' => '<i class="bi bi-check-all vk-wa-status-icon vk-wa-status-read" title="Read"></i>',
        default => '',
    };
};

$vkWaFormatTime = static function (?string $iso): string {
    if ($iso === null || $iso === '') {
        return '';
    }
    $ts = strtotime($iso);
    if (!$ts) {
        return '';
    }
    $todayTs = strtotime('today');
    if ($ts >= $todayTs) {
        return date('H:i', $ts);
    }
    if ($ts >= strtotime('-7 days')) {
        return date('D', $ts);
    }
    return date('d M', $ts);
};

$vkWaFormatDateSep = static function (?string $iso): string {
    if ($iso === null || $iso === '') {
        return '';
    }
    $ts = strtotime($iso);
    if (!$ts) {
        return '';
    }
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return 'Today';
    }
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday';
    }
    return date('l, d M Y', $ts);
};

$vkWaDisplayName = static function (string $phone): string {
    $d = preg_replace('/\D+/', '', $phone) ?? '';
    if (strlen($d) >= 9) {
        return '+' . $d;
    }
    return $phone !== '' ? $phone : 'Unknown';
};

$conversations = [];
foreach ($rows as $r) {
    $phone = (string) ($r['phone'] ?? '');
    if ($phone === '') {
        continue;
    }
    if (!isset($conversations[$phone])) {
        $conversations[$phone] = [
            'phone' => $phone,
            'messages' => [],
            'customer_id' => $r['customer_id'] ?? null,
            'templates' => [],
        ];
    }
    $conversations[$phone]['messages'][] = $r;
    if (!empty($r['customer_id'])) {
        $conversations[$phone]['customer_id'] = $r['customer_id'];
    }
    $tpl = (string) ($r['template_name'] ?? '');
    if ($tpl !== '' && !in_array($tpl, $conversations[$phone]['templates'], true)) {
        $conversations[$phone]['templates'][] = $tpl;
    }
}

foreach ($conversations as $phone => &$conv) {
    usort($conv['messages'], static function ($a, $b) {
        return strtotime((string) ($a['created_at'] ?? '')) <=> strtotime((string) ($b['created_at'] ?? ''));
    });
    $last = $conv['messages'][array_key_last($conv['messages'])];
    $conv['last_at'] = (string) ($last['created_at'] ?? '');
    $conv['last_preview'] = (string) ($last['message_preview'] ?? '');
    $conv['last_direction'] = (string) ($last['direction'] ?? 'outbound');
    $conv['last_status'] = (string) ($last['status'] ?? '');
    $conv['display_name'] = $vkWaDisplayName($phone);
    $conv['initials'] = $vkWaInitials($conv['display_name']);
    $conv['unread'] = count(array_filter($conv['messages'], static fn ($m) => ($m['direction'] ?? '') === 'inbound' && ($m['status'] ?? '') !== 'read'));
    $conv['is_open'] = ($conv['last_status'] ?? '') !== 'read' && ($conv['last_status'] ?? '') !== 'failed';
    $conv['is_closed'] = in_array($conv['last_status'], ['read', 'failed'], true);
    $conv['is_today'] = substr($conv['last_at'], 0, 10) === $today;
    $conv['is_week'] = substr($conv['last_at'], 0, 10) >= $weekStart;
    $conv['waiting_reply'] = $conv['last_direction'] === 'inbound';
    $lastType = $vkWaTemplateType((string) ($last['template_name'] ?? ''));
    $conv['last_type'] = $lastType;
    $conv['priority'] = !empty($last['emergency_priority'] ?? null) ? 'critical' : ($conv['waiting_reply'] ? 'high' : 'medium');
}
unset($conv);

uasort($conversations, static function ($a, $b) {
    return strtotime($b['last_at']) <=> strtotime($a['last_at']);
});

$kpiTotalChats = count($conversations);
$kpiUnread = array_sum(array_column($conversations, 'unread'));
$kpiWaiting = count(array_filter($conversations, static fn ($c) => $c['waiting_reply']));
$kpiResolved = count(array_filter($conversations, static fn ($c) => ($c['last_status'] ?? '') === 'read'));
$kpiTodayMsgs = count(array_filter($rows, static fn ($r) => substr((string) ($r['created_at'] ?? ''), 0, 10) === $today));
$avgResponseLabel = $responseRate > 0 ? $responseRate . '% read rate' : 'No data yet';

$selectedPhone = trim((string) ($_GET['chat'] ?? ''));
if ($selectedPhone === '' && $conversations !== []) {
    $selectedPhone = (string) array_key_first($conversations);
}

$quickReplies = [
    ['label' => 'Greeting', 'template' => 'ai_auto_reply', 'text' => 'Hello {{customer_name}}, thank you for contacting VK IT Network. How can we help you today?'],
    ['label' => 'Quotation', 'template' => 'marketing_broadcast', 'text' => 'Your service quotation is ready. Please review and let us know if you have any questions.'],
    ['label' => 'Invoice', 'template' => 'invoice_notification', 'text' => 'Hello {{customer_name}}, your invoice is ready. You can view and pay it using the link we sent.'],
    ['label' => 'Reminder', 'template' => 'service_reminder', 'text' => 'Reminder: your scheduled service appointment is coming up. Reply to confirm or reschedule.'],
    ['label' => 'Delivery', 'template' => 'booking_confirmation', 'text' => 'Good news! Your device is ready for delivery. Please visit us or reply to arrange pickup.'],
    ['label' => 'Warranty', 'template' => 'warranty_alert', 'text' => 'Your warranty period is ending soon. Contact us to extend coverage and stay protected.'],
    ['label' => 'Follow-up', 'template' => 'ai_auto_reply', 'text' => 'We hope your repair went well. Please let us know if you need any further assistance.'],
    ['label' => 'Appointment', 'template' => 'booking_confirmation', 'text' => 'Your service appointment has been confirmed. We look forward to seeing you.'],
];

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/whatsapp-crm.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/whatsapp-crm.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/whatsapp-crm.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkWaApp" class="vk-wa-crm" role="application" aria-label="WhatsApp Business CRM">

<header class="vk-wa-header">
    <div class="vk-wa-header-inner">
        <div>
            <h1 class="vk-wa-title"><i class="bi bi-whatsapp text-success me-1" aria-hidden="true"></i> WhatsApp CRM</h1>
            <p class="vk-wa-subtitle d-none d-md-block">Customer messaging command center · templates, delivery tracking &amp; support inbox</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="vk-wa-btn vk-wa-btn-ghost" href="<?= e(BASE_URL) ?>/modules/bookings/list.php" data-bs-toggle="tooltip" title="Booking replies">
                <i class="bi bi-calendar2-check" aria-hidden="true"></i><span class="d-none d-sm-inline">Bookings</span>
            </a>
            <a class="vk-wa-btn vk-wa-btn-primary" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php">
                <i class="bi bi-megaphone" aria-hidden="true"></i><span class="d-none d-sm-inline">Campaigns</span>
            </a>
        </div>
    </div>
</header>

<div class="vk-wa-kpi-grid" role="region" aria-label="Messaging statistics">
    <div class="vk-wa-kpi">
        <div class="vk-wa-kpi-icon"><i class="bi bi-chat-dots"></i></div>
        <div class="vk-wa-kpi-body">
            <span class="vk-wa-kpi-label">Total Chats</span>
            <span class="vk-wa-kpi-value"><?= e((string) $kpiTotalChats) ?></span>
            <span class="vk-wa-kpi-trend">Recent logs</span>
        </div>
    </div>
    <div class="vk-wa-kpi vk-wa-kpi-orange">
        <div class="vk-wa-kpi-icon"><i class="bi bi-envelope-exclamation"></i></div>
        <div class="vk-wa-kpi-body">
            <span class="vk-wa-kpi-label">Unread</span>
            <span class="vk-wa-kpi-value"><?= e((string) $kpiUnread) ?></span>
            <span class="vk-wa-kpi-trend">Inbound pending</span>
        </div>
    </div>
    <div class="vk-wa-kpi vk-wa-kpi-teal">
        <div class="vk-wa-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
        <div class="vk-wa-kpi-body">
            <span class="vk-wa-kpi-label">Waiting Reply</span>
            <span class="vk-wa-kpi-value"><?= e((string) $kpiWaiting) ?></span>
            <span class="vk-wa-kpi-trend">Needs agent</span>
        </div>
    </div>
    <div class="vk-wa-kpi vk-wa-kpi-green">
        <div class="vk-wa-kpi-icon"><i class="bi bi-check2-circle"></i></div>
        <div class="vk-wa-kpi-body">
            <span class="vk-wa-kpi-label">Resolved</span>
            <span class="vk-wa-kpi-value"><?= e((string) $kpiResolved) ?></span>
            <span class="vk-wa-kpi-trend">Read status</span>
        </div>
    </div>
    <div class="vk-wa-kpi vk-wa-kpi-purple">
        <div class="vk-wa-kpi-icon"><i class="bi bi-people"></i></div>
        <div class="vk-wa-kpi-body">
            <span class="vk-wa-kpi-label">Active (7d)</span>
            <span class="vk-wa-kpi-value"><?= e((string) $activeChats) ?></span>
            <span class="vk-wa-kpi-trend">All channels</span>
        </div>
    </div>
    <div class="vk-wa-kpi">
        <div class="vk-wa-kpi-icon"><i class="bi bi-chat-left-text"></i></div>
        <div class="vk-wa-kpi-body">
            <span class="vk-wa-kpi-label">Today</span>
            <span class="vk-wa-kpi-value"><?= e((string) $kpiTodayMsgs) ?></span>
            <span class="vk-wa-kpi-trend">Messages</span>
        </div>
    </div>
    <div class="vk-wa-kpi vk-wa-kpi-teal">
        <div class="vk-wa-kpi-icon"><i class="bi bi-speedometer2"></i></div>
        <div class="vk-wa-kpi-body">
            <span class="vk-wa-kpi-label">Delivery</span>
            <span class="vk-wa-kpi-value"><?= e((string) $deliveryRate) ?>%</span>
            <span class="vk-wa-kpi-trend">Sent → delivered</span>
        </div>
    </div>
    <div class="vk-wa-kpi vk-wa-kpi-green">
        <div class="vk-wa-kpi-icon"><i class="bi bi-emoji-smile"></i></div>
        <div class="vk-wa-kpi-body">
            <span class="vk-wa-kpi-label">Satisfaction</span>
            <span class="vk-wa-kpi-value"><?= e((string) $responseRate) ?>%</span>
            <span class="vk-wa-kpi-trend"><?= e($avgResponseLabel) ?></span>
        </div>
    </div>
</div>

<div class="vk-wa-shell vk-wa-skeleton" id="vkWaShell">

    <!-- LEFT: Conversation list -->
    <aside class="vk-wa-panel-left is-mobile-visible" aria-label="Conversations">
        <div class="vk-wa-sidebar-head">
            <div class="vk-wa-search-wrap">
                <i class="bi bi-search vk-wa-search-ico" aria-hidden="true"></i>
                <input type="search" id="vkWaSearch" class="form-control vk-wa-ctl" placeholder="Search conversations… ( / )" autocomplete="off" aria-label="Search conversations">
            </div>
            <div class="vk-wa-filter-tabs" role="tablist" aria-label="Conversation filters">
                <button type="button" class="vk-wa-filter-tab is-active" data-filter="all" role="tab" aria-selected="true">All</button>
                <button type="button" class="vk-wa-filter-tab" data-filter="unread" role="tab">Unread</button>
                <button type="button" class="vk-wa-filter-tab" data-filter="open" role="tab">Open</button>
                <button type="button" class="vk-wa-filter-tab" data-filter="closed" role="tab">Closed</button>
                <button type="button" class="vk-wa-filter-tab" data-filter="today" role="tab">Today</button>
                <button type="button" class="vk-wa-filter-tab" data-filter="week" role="tab">Week</button>
            </div>
        </div>
        <div class="vk-wa-conv-list" id="vkWaConvList" role="list">
            <?php if (!$conversations): ?>
            <div class="vk-wa-empty py-5">
                <p class="small mb-0">No conversations yet. Queue a template log below.</p>
            </div>
            <?php endif; ?>
            <?php foreach ($conversations as $phone => $conv): ?>
            <?php
                $searchBlob = strtolower(implode(' ', [
                    $phone,
                    $conv['display_name'],
                    $conv['last_preview'],
                    implode(' ', $conv['templates']),
                ]));
                $isActive = $phone === $selectedPhone;
            ?>
            <a href="?chat=<?= e(rawurlencode($phone)) ?>"
               class="vk-wa-conv<?= $isActive ? ' is-active' : '' ?>"
               role="listitem"
               data-phone="<?= e($phone) ?>"
               data-search="<?= e($searchBlob) ?>"
               data-unread="<?= $conv['unread'] > 0 ? '1' : '0' ?>"
               data-assigned="0"
               data-open="<?= $conv['is_open'] ? '1' : '0' ?>"
               data-closed="<?= $conv['is_closed'] ? '1' : '0' ?>"
               data-today="<?= $conv['is_today'] ? '1' : '0' ?>"
               data-week="<?= $conv['is_week'] ? '1' : '0' ?>"
               aria-current="<?= $isActive ? 'true' : 'false' ?>">
                <div class="vk-wa-avatar<?= $conv['is_today'] ? ' vk-wa-avatar-online' : '' ?>" aria-hidden="true"><?= e($conv['initials']) ?></div>
                <div class="vk-wa-conv-body">
                    <div class="vk-wa-conv-top">
                        <span class="vk-wa-conv-name vk-wa-highlight-target"><?= e($conv['display_name']) ?></span>
                        <span class="vk-wa-conv-time"><?= e($vkWaFormatTime($conv['last_at'])) ?></span>
                    </div>
                    <div class="vk-wa-conv-preview vk-wa-highlight-target"><?= e(mb_strlen($conv['last_preview']) > 48 ? mb_substr($conv['last_preview'], 0, 45, 'UTF-8') . '…' : $conv['last_preview']) ?></div>
                    <div class="vk-wa-conv-meta">
                        <span class="vk-wa-badge vk-wa-badge-<?= e($conv['last_type']['key']) ?>"><?= e($conv['last_type']['label']) ?></span>
                        <?php if ($conv['priority'] === 'critical'): ?>
                        <span class="vk-wa-badge vk-wa-badge-priority-critical">Critical</span>
                        <?php elseif ($conv['priority'] === 'high'): ?>
                        <span class="vk-wa-badge vk-wa-badge-priority-high">High</span>
                        <?php endif; ?>
                        <?php if ($conv['unread'] > 0): ?>
                        <span class="vk-wa-unread" aria-label="<?= (int) $conv['unread'] ?> unread"><?= (int) $conv['unread'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- MIDDLE: Chat window -->
    <section class="vk-wa-panel-chat is-mobile-visible" aria-label="Chat">
        <?php $hasActiveChat = $selectedPhone !== '' && isset($conversations[$selectedPhone]); ?>
        <?php if (!$hasActiveChat): ?>
        <div class="vk-wa-empty flex-grow-1" id="vkWaChatEmpty">
            <div class="vk-wa-empty-icon" aria-hidden="true"><i class="bi bi-chat-square-text"></i></div>
            <h2>Select a conversation</h2>
            <p>Choose a customer from the list to view message history, or queue a new template log below.</p>
            <button type="button" class="vk-wa-btn vk-wa-btn-primary d-md-none" onclick="document.querySelector('.vk-wa-panel-left')?.classList.add('is-mobile-visible');document.querySelector('.vk-wa-panel-chat')?.classList.remove('is-mobile-visible');">
                <i class="bi bi-list-ul"></i> Browse conversations
            </button>
        </div>
        <?php else: ?>
        <?php $activeConv = $conversations[$selectedPhone]; ?>
        <div class="vk-wa-chat-head" id="vkWaChatHead">
            <button type="button" class="vk-wa-btn vk-wa-btn-icon vk-wa-btn-ghost vk-wa-mobile-back" id="vkWaMobileBack" aria-label="Back to conversations"><i class="bi bi-arrow-left"></i></button>
            <div class="vk-wa-avatar" aria-hidden="true"><?= e($activeConv['initials']) ?></div>
            <div class="vk-wa-chat-head-info">
                <h2 class="vk-wa-chat-head-name"><?= e($activeConv['display_name']) ?></h2>
                <p class="vk-wa-chat-head-sub">
                    <i class="bi bi-telephone me-1" aria-hidden="true"></i><?= e($selectedPhone) ?>
                    · <span class="vk-wa-badge vk-wa-badge-<?= e($activeConv['last_type']['key']) ?>"><?= e($activeConv['last_type']['label']) ?></span>
                    · <?= $activeConv['is_open'] ? 'Open' : 'Closed' ?>
                </p>
            </div>
            <div class="vk-wa-chat-actions">
                <button type="button" class="vk-wa-btn vk-wa-btn-icon" disabled data-bs-toggle="tooltip" title="Call (connect telephony)" aria-label="Call"><i class="bi bi-telephone"></i></button>
                <button type="button" class="vk-wa-btn vk-wa-btn-icon" disabled data-bs-toggle="tooltip" title="Video (coming soon)" aria-label="Video call"><i class="bi bi-camera-video"></i></button>
                <button type="button" class="vk-wa-btn vk-wa-btn-icon" id="vkWaInfoToggle" data-bs-toggle="tooltip" title="Customer info" aria-label="Customer info"><i class="bi bi-info-circle"></i></button>
                <div class="dropdown">
                    <button type="button" class="vk-wa-btn vk-wa-btn-icon" data-bs-toggle="dropdown" aria-label="More actions"><i class="bi bi-three-dots-vertical"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/repairs/list.php?q=<?= e(rawurlencode($selectedPhone)) ?>"><i class="bi bi-tools me-2"></i>Open repairs</a></li>
                        <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><i class="bi bi-receipt me-2"></i>Create invoice</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><span class="dropdown-item-text small text-muted">Broadcast &amp; schedule require Cloud API</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <?php foreach ($conversations as $phone => $conv): ?>
        <div class="vk-wa-thread" data-phone="<?= e($phone) ?>"<?= $phone !== $selectedPhone ? ' hidden' : '' ?>>
            <div class="vk-wa-messages<?= $phone === $selectedPhone ? '' : '' ?>"<?= $phone === $selectedPhone ? ' id="vkWaMessages"' : '' ?> role="log" aria-live="polite" aria-relevant="additions">
                <?php
                $lastDate = '';
                foreach ($conv['messages'] as $msg):
                    $msgDate = substr((string) ($msg['created_at'] ?? ''), 0, 10);
                    if ($msgDate !== $lastDate):
                        $lastDate = $msgDate;
                ?>
                <div class="vk-wa-date-sep"><span><?= e($vkWaFormatDateSep((string) ($msg['created_at'] ?? ''))) ?></span></div>
                <?php endif; ?>
                <?php
                    $dir = (string) ($msg['direction'] ?? 'outbound');
                    $type = $vkWaTemplateType((string) ($msg['template_name'] ?? ''));
                    $status = (string) ($msg['status'] ?? '');
                ?>
                <div class="vk-wa-msg-row <?= e($dir) ?>">
                    <div class="vk-wa-bubble">
                        <?php if (!empty($msg['template_name'])): ?>
                        <div class="vk-wa-bubble-type vk-wa-badge vk-wa-badge-<?= e($type['key']) ?>"><?= e(str_replace('_', ' ', (string) $msg['template_name'])) ?></div>
                        <?php endif; ?>
                        <?= nl2br(e((string) ($msg['message_preview'] ?? ''))) ?>
                        <div class="vk-wa-bubble-meta">
                            <span><?= e($vkWaFormatTime((string) ($msg['created_at'] ?? ''))) ?></span>
                            <?= $vkWaStatusIcon($status, $dir) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php if ($phone === $selectedPhone): ?>
            <div class="vk-wa-typing" id="vkWaTyping" aria-live="polite">
                Agent composing<span class="vk-wa-typing-dots"><span>.</span><span>.</span><span>.</span></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="vk-wa-composer">
            <form method="post" id="vkWaComposerForm" aria-label="Send message">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <?php if ($hasActiveChat): ?>
                <input type="hidden" name="phone" id="vkWaPhoneInput" value="<?= e($selectedPhone) ?>">
                <?php else: ?>
                <div class="mb-2">
                    <label for="vkWaPhoneInput" class="form-label small text-muted mb-1">Phone</label>
                    <input type="text" class="form-control vk-wa-ctl" name="phone" id="vkWaPhoneInput" placeholder="+9477..." required aria-label="Phone number">
                </div>
                <?php endif; ?>

                <div class="vk-wa-quick-replies" role="group" aria-label="Quick replies">
                    <?php foreach ($quickReplies as $qr): ?>
                    <button type="button" class="vk-wa-quick-reply" data-template="<?= e($qr['template']) ?>" data-reply="<?= e($qr['text']) ?>"><?= e($qr['label']) ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="vk-wa-composer-tools">
                    <div class="position-relative">
                        <button type="button" class="vk-wa-btn vk-wa-btn-icon" id="vkWaEmojiToggle" aria-label="Insert emoji"><i class="bi bi-emoji-smile"></i></button>
                        <div class="vk-wa-emoji-popover" id="vkWaEmojiPopover" role="menu"></div>
                    </div>
                    <button type="button" class="vk-wa-btn vk-wa-btn-icon" disabled data-bs-toggle="tooltip" title="Image (Cloud API)" aria-label="Attach image"><i class="bi bi-image"></i></button>
                    <button type="button" class="vk-wa-btn vk-wa-btn-icon" disabled data-bs-toggle="tooltip" title="Document (Cloud API)" aria-label="Attach document"><i class="bi bi-paperclip"></i></button>
                    <button type="button" class="vk-wa-btn vk-wa-btn-icon" disabled data-bs-toggle="tooltip" title="Camera (Cloud API)" aria-label="Camera"><i class="bi bi-camera"></i></button>
                    <button type="button" class="vk-wa-btn vk-wa-btn-icon" disabled data-bs-toggle="tooltip" title="Voice note (Cloud API)" aria-label="Voice note"><i class="bi bi-mic"></i></button>
                    <button type="button" class="vk-wa-btn vk-wa-btn-icon" data-bs-toggle="tooltip" title="WhatsApp template" aria-label="Template" onclick="document.getElementById('vkWaTemplateSelect')?.focus()"><i class="bi bi-file-text"></i></button>
                </div>

                <div class="vk-wa-composer-row">
                    <div class="vk-wa-composer-input-wrap">
                        <textarea class="form-control vk-wa-ctl" name="message_preview" id="vkWaMessageInput" rows="1" placeholder="Type a message or use a quick reply…" aria-label="Message preview"><?= $hasActiveChat ? 'Hello ' . e($activeConv['display_name']) . ', your VK IT service update is ready.' : 'Hello {{customer_name}}, your VK IT service update is ready.' ?></textarea>
                    </div>
                    <button type="submit" class="vk-wa-send-btn" aria-label="Queue template log"><i class="bi bi-send-fill"></i></button>
                </div>

                <div class="vk-wa-composer-advanced">
                    <div>
                        <label for="vkWaTemplateSelect">Template</label>
                        <select class="form-select vk-wa-ctl" name="template_name" id="vkWaTemplateSelect" aria-label="Template name">
                            <option value="booking_confirmation">booking_confirmation</option>
                            <option value="service_reminder">service_reminder</option>
                            <option value="invoice_notification">invoice_notification</option>
                            <option value="warranty_alert">warranty_alert</option>
                            <option value="marketing_broadcast">marketing_broadcast</option>
                            <option value="ai_auto_reply">ai_auto_reply</option>
                        </select>
                    </div>
                    <div>
                        <label for="vkWaStatusSelect">Status</label>
                        <select class="form-select vk-wa-ctl" name="status" id="vkWaStatusSelect" aria-label="Delivery status">
                            <option value="queued">queued</option>
                            <option value="sent">sent</option>
                            <option value="delivered">delivered</option>
                            <option value="read">read</option>
                            <option value="failed">failed</option>
                        </select>
                    </div>
                </div>

                <div class="vk-wa-dropzone mt-2 d-none d-md-block" id="vkWaDropzone" role="button" tabindex="0" aria-label="Drag and drop files">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Drag &amp; drop attachments (requires WhatsApp Cloud API)
                </div>
            </form>
            <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i>Connect official WhatsApp Cloud API credentials in settings to turn these workflows into live provider sends.</p>
        </div>
    </section>

    <!-- RIGHT: Customer details -->
    <aside class="vk-wa-panel-right" aria-label="Customer details">
        <div class="vk-wa-panel-right-head">
            <span class="fw-semibold small">Customer profile</span>
            <button type="button" class="vk-wa-btn vk-wa-btn-icon vk-wa-btn-ghost d-md-none" id="vkWaProfileClose" aria-label="Close profile"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="vk-wa-panel-right-scroll">
            <?php if ($selectedPhone === '' || !isset($conversations[$selectedPhone])): ?>
            <div class="vk-wa-empty py-4">
                <p class="small">Select a conversation to view customer details.</p>
            </div>
            <?php else: ?>
            <?php foreach ($conversations as $phone => $conv): ?>
            <div class="vk-wa-profile-pane" data-phone="<?= e($phone) ?>"<?= $phone !== $selectedPhone ? ' hidden' : '' ?>>
                <div class="vk-wa-profile-card">
                    <div class="vk-wa-avatar mx-auto"><?= e($conv['initials']) ?></div>
                    <h3 class="vk-wa-profile-name"><?= e($conv['display_name']) ?></h3>
                    <p class="vk-wa-profile-phone"><?= e($phone) ?></p>
                    <div class="vk-wa-tags justify-content-center mt-2">
                        <?php foreach ($conv['templates'] as $tpl): ?>
                        <?php $t = $vkWaTemplateType($tpl); ?>
                        <span class="vk-wa-tag vk-wa-badge-<?= e($t['key']) ?>"><?= e(str_replace('_', ' ', $tpl)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="vk-wa-section">
                    <h4 class="vk-wa-section-title">Overview</h4>
                    <div class="vk-wa-stat-grid">
                        <div class="vk-wa-stat">
                            <div class="vk-wa-stat-label">Messages</div>
                            <div class="vk-wa-stat-value"><?= count($conv['messages']) ?></div>
                        </div>
                        <div class="vk-wa-stat">
                            <div class="vk-wa-stat-label">Unread</div>
                            <div class="vk-wa-stat-value"><?= (int) $conv['unread'] ?></div>
                        </div>
                        <div class="vk-wa-stat">
                            <div class="vk-wa-stat-label">Repairs</div>
                            <div class="vk-wa-stat-value">—</div>
                        </div>
                        <div class="vk-wa-stat">
                            <div class="vk-wa-stat-label">Balance</div>
                            <div class="vk-wa-stat-value">—</div>
                        </div>
                    </div>
                </div>

                <div class="vk-wa-section">
                    <h4 class="vk-wa-section-title">Assigned agent</h4>
                    <p class="small mb-0 text-muted"><i class="bi bi-person-badge me-1"></i>Unassigned · assign via Cloud API</p>
                </div>

                <div class="vk-wa-section">
                    <h4 class="vk-wa-section-title">Internal notes</h4>
                    <div class="vk-wa-note">
                        <textarea class="form-control vk-wa-ctl mt-0" rows="2" placeholder="Add internal note (UI only)…" aria-label="Internal note" disabled></textarea>
                    </div>
                </div>

                <div class="vk-wa-section">
                    <h4 class="vk-wa-section-title">Timeline</h4>
                    <ul class="vk-wa-timeline">
                        <?php foreach (array_reverse($conv['messages']) as $msg): ?>
                        <?php $t = $vkWaTemplateType((string) ($msg['template_name'] ?? '')); ?>
                        <li>
                            <strong><?= e($t['label']) ?></strong> · <?= e((string) ($msg['direction'] ?? '')) ?>
                            <div class="text-truncate"><?= e(mb_strlen((string) ($msg['message_preview'] ?? '')) > 60 ? mb_substr((string) $msg['message_preview'], 0, 57, 'UTF-8') . '…' : (string) ($msg['message_preview'] ?? '')) ?></div>
                            <div class="vk-wa-timeline-time"><?= e((string) ($msg['created_at'] ?? '')) ?> · <?= e((string) ($msg['status'] ?? '')) ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="vk-wa-section">
                    <h4 class="vk-wa-section-title">Attachments</h4>
                    <div class="vk-wa-attach-grid">
                        <div class="vk-wa-attach-item" title="No attachments"><i class="bi bi-image"></i></div>
                        <div class="vk-wa-attach-item" title="No documents"><i class="bi bi-file-pdf"></i></div>
                        <div class="vk-wa-attach-item" title="No voice notes"><i class="bi bi-mic"></i></div>
                    </div>
                </div>

                <div class="vk-wa-section">
                    <h4 class="vk-wa-section-title">Quick actions</h4>
                    <div class="vk-wa-quick-actions">
                        <a class="vk-wa-btn" href="<?= e(BASE_URL) ?>/modules/repairs/list.php?q=<?= e(rawurlencode($phone)) ?>"><i class="bi bi-tools"></i> Repairs</a>
                        <a class="vk-wa-btn" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><i class="bi bi-receipt"></i> Invoice</a>
                        <a class="vk-wa-btn" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php"><i class="bi bi-wrench"></i> Maintenance</a>
                        <a class="vk-wa-btn" href="<?= e(BASE_URL) ?>/modules/marketing/campaigns.php"><i class="bi bi-chat-quote"></i> Quote</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

</div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/whatsapp-crm.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
