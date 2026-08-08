<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('convert');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$to = strtolower(trim((string) ($_GET['to'] ?? $_POST['to'] ?? 'invoice')));
if (!in_array($to, ['invoice', 'so'], true)) {
    $to = 'invoice';
}

$q = vk_quotation_get($pdo, $id);
if (!$q) {
    flash_set('error', 'Quotation not found.');
    redirect('/modules/quotations/list.php');
}

$allowedForInvoice = ['approved', 'accepted', 'converted_so'];
if ($to === 'invoice' && !in_array($q['status'], $allowedForInvoice, true)) {
    flash_set('error', 'Only approved or accepted quotations can be converted to invoice.');
    redirect('/modules/quotations/view.php?id=' . $id);
}
if ($to === 'so' && !in_array($q['status'], ['approved', 'accepted'], true)) {
    flash_set('error', 'Only approved or accepted quotations can be converted to sales order.');
    redirect('/modules/quotations/view.php?id=' . $id);
}
if ($to === 'invoice' && !empty($q['converted_invoice_id'])) {
    flash_set('info', 'Already converted to invoice.');
    redirect('/modules/invoices/view.php?id=' . (int) $q['converted_invoice_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/convert.php?id=' . $id . '&to=' . $to);
    }
    if ((string) ($_POST['confirm'] ?? '') !== 'yes') {
        flash_set('error', 'Please confirm conversion.');
        redirect('/modules/quotations/convert.php?id=' . $id . '&to=' . $to);
    }
    try {
        if ($to === 'invoice') {
            $invoiceId = vk_quotation_convert_to_invoice($pdo, $id);
            flash_set('success', 'Quotation converted to invoice successfully.');
            redirect('/modules/invoices/view.php?id=' . $invoiceId);
        }
        $pdo->prepare("UPDATE quotations SET status = 'converted_so', converted_at = NOW() WHERE id = ?")->execute([$id]);
        vk_quotation_log($pdo, $id, 'converted_so', 'Converted to sales order (manual status update)');
        flash_set('success', 'Quotation marked as converted to sales order.');
        redirect('/modules/quotations/view.php?id=' . $id);
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
        redirect('/modules/quotations/convert.php?id=' . $id . '&to=' . $to);
    }
}

$items = vk_quotation_items($pdo, $id);
$targetLabel = $to === 'invoice' ? 'Invoice' : 'Sales Order';

$pageTitle = 'Convert Quotation';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="mb-3">
        <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <h1 class="h3 mt-2 mb-1">Convert to <?= e($targetLabel) ?></h1>
        <p class="text-muted mb-0"><?= e($q['quotation_number']) ?> · <?= e($q['customer_name']) ?></p>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Summary</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Grand total</dt>
                        <dd class="col-sm-8"><?= e(formatCurrency($q['grand_total'])) ?></dd>
                        <dt class="col-sm-4">Line items</dt>
                        <dd class="col-sm-8"><?= count($items) ?></dd>
                        <dt class="col-sm-4">Status</dt>
                        <dd class="col-sm-8"><span class="badge text-bg-<?= e(vk_quotation_status_badge($q['status'])) ?>"><?= e(vk_quotation_status_label($q['status'])) ?></span></dd>
                    </dl>
                </div>
            </div>
            <?php if ($to === 'invoice'): ?>
            <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>This will create a new invoice, apply ledger entries, and mark the quotation as converted.</div>
            <?php else: ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-1"></i>Sales order module is not fully integrated. This will update the quotation status to <strong>Converted to Sales Order</strong> and log the action.</div>
            <?php endif; ?>
        </div>
        <div class="col-lg-4">
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Confirm</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="to" value="<?= e($to) ?>">
                        <input type="hidden" name="confirm" value="yes">
                        <p class="small text-muted">Convert this quotation to a <?= e(strtolower($targetLabel)) ?>?</p>
                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-arrow-right-circle me-1"></i>Convert now</button>
                        <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
