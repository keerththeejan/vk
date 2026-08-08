<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$q = vk_quotation_get($pdo, $id);
if (!$q) {
    flash_set('error', 'Quotation not found.');
    redirect('/modules/quotations/list.php');
}

$printUrl = rtrim(BASE_URL, '/') . '/modules/quotations/print.php?id=' . $id;
$defaultEmail = (string) ($q['email'] ?: $q['customer_email_db'] ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/email.php?id=' . $id);
    }
    $to = trim((string) ($_POST['recipient_email'] ?? ''));
    $customSubject = trim((string) ($_POST['subject'] ?? ''));
    $customMessage = trim((string) ($_POST['message'] ?? ''));

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Please enter a valid recipient email.');
        redirect('/modules/quotations/email.php?id=' . $id);
    }

    vk_bootstrap_module('mailer');

    $subjectTpl = $customSubject !== '' ? $customSubject : vk_quotation_setting($pdo, 'email_subject', 'Quotation {quotation_number} from VK Network');
    $subject = str_replace(
        ['{customer_name}', '{quotation_number}', '{grand_total}', '{expiry_date}', '{print_url}'],
        [
            (string) ($q['contact_person'] ?: $q['customer_name']),
            (string) $q['quotation_number'],
            formatCurrency($q['grand_total']),
            (string) ($q['expiry_date'] ?? '—'),
            $printUrl,
        ],
        $subjectTpl
    );

    $token = bin2hex(random_bytes(24));
    $bodyText = "Dear " . ($q['contact_person'] ?: $q['customer_name']) . ",\n\n"
        . "Please find quotation " . $q['quotation_number'] . " for " . formatCurrency($q['grand_total']) . ".\n"
        . "Valid until: " . ($q['expiry_date'] ?? '—') . "\n\n"
        . ($customMessage !== '' ? $customMessage . "\n\n" : '')
        . "View / Print: " . $printUrl . "\n\n— VK Network";

    $htmlBody = '<div style="font-family:Inter,Arial,sans-serif;max-width:640px;margin:0 auto;color:#1a1a1a">'
        . '<h2 style="color:#0B4DBA">Quotation ' . e($q['quotation_number']) . '</h2>'
        . '<p>Dear ' . e((string) ($q['contact_person'] ?: $q['customer_name'])) . ',</p>'
        . '<p>Please find your quotation summary below:</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0">'
        . '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Amount</strong></td><td style="padding:8px;border:1px solid #ddd">' . e(formatCurrency($q['grand_total'])) . '</td></tr>'
        . '<tr><td style="padding:8px;border:1px solid #ddd"><strong>Valid until</strong></td><td style="padding:8px;border:1px solid #ddd">' . e((string) ($q['expiry_date'] ?? '—')) . '</td></tr>'
        . '</table>'
        . ($customMessage !== '' ? '<p>' . nl2br(e($customMessage)) . '</p>' : '')
        . '<p><a href="' . e($printUrl) . '" style="background:#0B4DBA;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:600">View / Print Quotation</a></p>'
        . '<p style="color:#555;font-size:13px">— VK Network</p></div>';

    $res = vk_mailer_send($pdo, $to, $subject, $bodyText, (string) ($q['contact_person'] ?: $q['customer_name']), [
        'html' => true,
        'html_body' => $htmlBody,
        'template_type' => 'quotation',
    ]);

    $trackStatus = !empty($res['ok']) ? 'sent' : 'failed';
    $pdo->prepare(
        'INSERT INTO quotation_email_tracking (quotation_id, tracking_token, recipient_email, sent_at, status, error_message)
         VALUES (?,?,?,NOW(),?,?)'
    )->execute([
        $id,
        $token,
        $to,
        $trackStatus,
        !empty($res['ok']) ? null : (string) ($res['error'] ?? 'Send failed'),
    ]);

    vk_quotation_log($pdo, $id, 'emailed', 'Sent to ' . $to . ($trackStatus === 'failed' ? ' (failed)' : ''));

    if (!empty($res['ok'])) {
        flash_set('success', 'Quotation email sent to ' . $to . '.');
        redirect('/modules/quotations/view.php?id=' . $id);
    }
    flash_set('error', 'Email failed: ' . (string) ($res['error'] ?? 'Unknown error'));
    redirect('/modules/quotations/email.php?id=' . $id);
}

$tracking = $pdo->prepare('SELECT * FROM quotation_email_tracking WHERE quotation_id = ? ORDER BY id DESC LIMIT 10');
$tracking->execute([$id]);
$trackRows = $tracking->fetchAll();

$subjectPreview = str_replace(
    ['{customer_name}', '{quotation_number}', '{grand_total}', '{expiry_date}', '{print_url}'],
    [
        (string) ($q['contact_person'] ?: $q['customer_name']),
        (string) $q['quotation_number'],
        formatCurrency($q['grand_total']),
        (string) ($q['expiry_date'] ?? '—'),
        $printUrl,
    ],
    vk_quotation_setting($pdo, 'email_subject', 'Quotation {quotation_number} from VK Network')
);

$pageTitle = 'Email Quotation';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="mb-3">
        <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <h1 class="h3 mt-2 mb-1">Email Quotation</h1>
        <p class="text-muted mb-0"><?= e($q['quotation_number']) ?> · <?= e($q['customer_name']) ?></p>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <form class="card vk-card" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="card-header bg-transparent fw-semibold">Send email</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient email</label>
                        <input type="email" name="recipient_email" class="form-control" required value="<?= e($defaultEmail) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" value="<?= e($subjectPreview) ?>">
                        <div class="form-text">Placeholders: {customer_name}, {quotation_number}, {grand_total}, {expiry_date}, {print_url}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Personal message (optional)</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Add a note for the customer…"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Send email</button>
                </div>
            </form>
        </div>
        <div class="col-lg-6">
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Email history</div>
                <ul class="list-group list-group-flush">
                    <?php if (!$trackRows): ?>
                        <li class="list-group-item text-muted small">No emails sent yet.</li>
                    <?php else: foreach ($trackRows as $t): ?>
                        <li class="list-group-item small">
                            <div class="d-flex justify-content-between">
                                <span><?= e($t['recipient_email']) ?></span>
                                <span class="badge text-bg-<?= $t['status'] === 'sent' ? 'success' : 'danger' ?>"><?= e($t['status']) ?></span>
                            </div>
                            <div class="text-muted"><?= e((string) ($t['sent_at'] ?? $t['created_at'])) ?></div>
                        </li>
                    <?php endforeach; endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
