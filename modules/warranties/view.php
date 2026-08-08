<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
vk_bootstrap_module('warranty_service');

$id = (int) ($_GET['id'] ?? 0);
$row = vk_warranty_fetch($pdo, $id);
if (!$row) {
    flash_set('error', 'Warranty not found.');
    redirect('/modules/warranties/list.php');
}

$status = vk_warranty_status($row);
$wrNo = vk_warranty_number($id);
$period = vk_warranty_period_label((string) $row['start_date'], (string) $row['end_date']);
$qrPayload = rawurlencode($wrNo . '|' . (string) $row['title'] . '|' . (string) $row['end_date']);
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . $qrPayload;
$barcodeUrl = 'https://barcode.tec-it.com/barcode.ashx?data=' . rawurlencode($wrNo) . '&code=Code128&translate-esc=false';

$pageTitle = 'Warranty ' . $wrNo;
$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/warranties-list.css');
$extraHead = '<link href="' . e(base_url('assets/css/warranties-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$cleanNotes = preg_replace('/^\[CANCELLED\]\s*/i', '', (string) ($row['notes'] ?? '')) ?? '';
?>
<div class="vk-war-admin">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <a href="<?= e(BASE_URL) ?>/modules/warranties/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to list</a>
            <h1 class="vk-war-title mt-2"><?= e($wrNo) ?> · <?= e((string) $row['title']) ?></h1>
            <p class="vk-war-subtitle mb-0">
                <span class="vk-war-badge vk-war-badge-<?= e($status['class']) ?>"><?= e($status['label']) ?></span>
                <span class="ms-2"><?= e($period) ?> coverage</span>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/edit.php?id=<?= $id ?>"><i class="bi bi-pencil"></i> Edit</a>
            <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/print.php?id=<?= $id ?>" target="_blank"><i class="bi bi-printer"></i> Print</a>
            <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/print.php?id=<?= $id ?>&pdf=1" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <a class="vk-war-btn vk-war-btn-primary" href="<?= e(BASE_URL) ?>/modules/warranties/actions.php" onclick="return false;" id="vkWarEmailOne"><i class="bi bi-envelope"></i> Email</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="vk-war-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-person me-1"></i> Customer Information</h2>
                <div class="row g-2">
                    <div class="col-md-6"><div class="text-muted small">Name</div><div class="fw-semibold"><?= e((string) $row['customer_name']) ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Phone</div><div><?= e((string) ($row['customer_phone'] ?: '—')) ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Email</div><div><?= e((string) ($row['customer_email'] ?: '—')) ?></div></div>
                    <div class="col-12"><div class="text-muted small">Address</div><div><?= e((string) ($row['customer_address'] ?: '—')) ?></div></div>
                </div>
            </div>

            <div class="vk-war-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-receipt me-1"></i> Invoice Information</h2>
                <div class="row g-2">
                    <div class="col-md-4"><div class="text-muted small">Invoice No</div><div><?= e((string) ($row['invoice_number'] ?: '—')) ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Invoice Date</div><div><?= e((string) ($row['invoice_date'] ?: '—')) ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Invoice Total</div><div><?= isset($row['invoice_total']) && $row['invoice_total'] !== null ? e(number_format((float) $row['invoice_total'], 2)) : '—' ?></div></div>
                </div>
            </div>

            <div class="vk-war-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-box-seam me-1"></i> Product Details</h2>
                <div class="row g-2">
                    <div class="col-md-6"><div class="text-muted small">Product / Service</div><div class="fw-semibold"><?= e((string) $row['title']) ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Type</div><div class="text-capitalize"><?= e((string) $row['warranty_type']) ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Serial Number</div><div>—</div></div>
                    <div class="col-md-4"><div class="text-muted small">Brand</div><div>—</div></div>
                    <div class="col-md-4"><div class="text-muted small">Model</div><div>—</div></div>
                    <div class="col-md-4"><div class="text-muted small">Linked Job</div><div><?= e((string) ($row['repair_job_number'] ?: $row['cctv_job_number'] ?: '—')) ?></div></div>
                    <div class="col-12"><div class="text-muted small">Description</div><div><?= e((string) ($row['description'] ?: '—')) ?></div></div>
                </div>
            </div>

            <div class="vk-war-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-shield-lock me-1"></i> Coverage &amp; Terms</h2>
                <div class="row g-2 mb-3">
                    <div class="col-md-3"><div class="text-muted small">Purchase / Start</div><div><?= e((string) $row['start_date']) ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Expiry</div><div><?= e((string) $row['end_date']) ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Period</div><div><?= e($period) ?></div></div>
                    <div class="col-md-3"><div class="text-muted small">Days Remaining</div><div><?= $status['days'] === null ? '—' : (int) $status['days'] ?></div></div>
                </div>
                <div class="text-muted small mb-1">Terms &amp; Conditions</div>
                <p class="mb-0">This warranty covers manufacturing defects and service workmanship for the stated period. Physical damage, misuse, unauthorized repairs, and consumables are excluded unless otherwise agreed in writing.</p>
            </div>

            <div class="vk-war-card p-3 mb-3" id="history">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-clock-history me-1"></i> Warranty History</h2>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><span class="badge text-bg-light me-2">Created</span> <?= e((string) ($row['created_at'] ?? '')) ?></li>
                    <li class="mb-2"><span class="badge text-bg-light me-2">Coverage</span> <?= e((string) $row['start_date']) ?> → <?= e((string) $row['end_date']) ?></li>
                    <?php if (vk_warranty_is_cancelled((string) ($row['notes'] ?? ''))): ?>
                        <li class="mb-2"><span class="badge text-bg-secondary me-2">Cancelled</span> Marked inactive via warranty controls</li>
                    <?php endif; ?>
                    <?php if ($status['key'] === 'expired'): ?>
                        <li><span class="badge text-bg-danger me-2">Expired</span> Coverage ended on <?= e((string) $row['end_date']) ?></li>
                    <?php elseif ($status['key'] === 'expiring'): ?>
                        <li><span class="badge text-bg-warning me-2">Alert</span> Renewal reminder within <?= (int) vk_warranty_alert_days() ?> days</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="vk-war-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-paperclip me-1"></i> Documents &amp; Attachments</h2>
                <p class="text-muted mb-0">No uploaded documents on this record. Attachments can be referenced in notes until a dedicated uploads column is added.</p>
            </div>

            <div class="vk-war-card p-3 mb-3">
                <h2 class="h6 fw-bold mb-3"><i class="bi bi-journal-text me-1"></i> Notes</h2>
                <p class="mb-0"><?= nl2br(e($cleanNotes !== '' ? $cleanNotes : '—')) ?></p>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="vk-war-card p-3 mb-3 text-center">
                <h2 class="h6 fw-bold mb-3">QR Code</h2>
                <img src="<?= e($qrUrl) ?>" alt="Warranty QR" width="160" height="160" class="img-fluid mb-2" loading="lazy">
                <div class="small text-muted"><?= e($wrNo) ?></div>
            </div>
            <div class="vk-war-card p-3 mb-3 text-center">
                <h2 class="h6 fw-bold mb-3">Barcode</h2>
                <img src="<?= e($barcodeUrl) ?>" alt="Warranty barcode" class="img-fluid" style="max-height:70px" loading="lazy">
            </div>
            <div class="vk-war-card p-3">
                <h2 class="h6 fw-bold mb-3">Quick Actions</h2>
                <div class="d-grid gap-2">
                    <a class="vk-war-btn" href="<?= e(BASE_URL) ?>/modules/warranties/edit.php?id=<?= $id ?>"><i class="bi bi-pencil"></i> Edit Warranty</a>
                    <button type="button" class="vk-war-btn" id="vkWarRenewOne"><i class="bi bi-arrow-repeat"></i> Renew Warranty</button>
                    <button type="button" class="vk-war-btn" id="vkWarDeactivateOne"><i class="bi bi-slash-circle"></i> Deactivate</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var csrf = window.VK_CSRF_TOKEN || '';
    var id = <?= (int) $id ?>;
    var base = <?= json_encode(rtrim(BASE_URL, '/'), JSON_THROW_ON_ERROR) ?>;
    function act(action) {
        var fd = new FormData();
        fd.append('_csrf', csrf);
        fd.append('action', action);
        fd.append('ids[]', String(id));
        fetch(base + '/modules/warranties/actions.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert((data && data.message) || 'Done');
                if (action !== 'email') location.reload();
            })
            .catch(function () { alert('Action failed'); });
    }
    var emailBtn = document.getElementById('vkWarEmailOne');
    if (emailBtn) emailBtn.addEventListener('click', function () { act('email'); });
    var renewBtn = document.getElementById('vkWarRenewOne');
    if (renewBtn) renewBtn.addEventListener('click', function () { if (confirm('Renew this warranty by its original period?')) act('renew'); });
    var deactBtn = document.getElementById('vkWarDeactivateOne');
    if (deactBtn) deactBtn.addEventListener('click', function () { if (confirm('Deactivate this warranty?')) act('deactivate'); });
})();
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
