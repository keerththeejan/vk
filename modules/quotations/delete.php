<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('delete');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$q = vk_quotation_get($pdo, $id);
if (!$q) {
    flash_set('error', 'Quotation not found.');
    redirect('/modules/quotations/list.php');
}

$softStatuses = ['draft', 'cancelled', 'rejected'];
$canDelete = in_array($q['status'], $softStatuses, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/delete.php?id=' . $id);
    }
    if ((string) ($_POST['confirm'] ?? '') !== 'yes') {
        flash_set('error', 'Please confirm deletion.');
        redirect('/modules/quotations/delete.php?id=' . $id);
    }
    if (!$canDelete) {
        flash_set('error', 'Only draft, cancelled, or rejected quotations can be deleted.');
        redirect('/modules/quotations/view.php?id=' . $id);
    }
    try {
        vk_quotation_log($pdo, $id, 'deleted', 'Quotation ' . $q['quotation_number'] . ' deleted');
        $pdo->prepare('DELETE FROM quotations WHERE id = ?')->execute([$id]);
        flash_set('success', 'Quotation deleted.');
        redirect('/modules/quotations/list.php');
    } catch (Throwable $e) {
        flash_set('error', 'Could not delete: ' . $e->getMessage());
        redirect('/modules/quotations/delete.php?id=' . $id);
    }
}

$pageTitle = 'Delete Quotation';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="mb-3">
        <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <h1 class="h3 mt-2 mb-1 text-danger">Delete Quotation</h1>
        <p class="text-muted mb-0"><?= e($q['quotation_number']) ?> · <?= e($q['customer_name']) ?></p>
    </div>

    <div class="card vk-card" style="max-width:520px">
        <div class="card-body">
            <?php if (!$canDelete): ?>
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    This quotation has status <strong><?= e(vk_quotation_status_label($q['status'])) ?></strong> and cannot be deleted.
                    Only draft, cancelled, or rejected quotations may be removed.
                </div>
                <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>" class="btn btn-outline-secondary mt-3">Return</a>
            <?php else: ?>
                <p>Permanently delete <strong><?= e($q['quotation_number']) ?></strong>? Related items, approvals, and follow-ups will be removed (CASCADE).</p>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="confirm" value="yes">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete permanently</button>
                    <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
