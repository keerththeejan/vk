<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('approve');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$q = vk_quotation_get($pdo, $id);
if (!$q) {
    flash_set('error', 'Quotation not found.');
    redirect('/modules/quotations/list.php');
}
if ($q['status'] !== 'pending_approval') {
    flash_set('error', 'This quotation is not pending approval.');
    redirect('/modules/quotations/view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/approve.php?id=' . $id);
    }
    $action = (string) ($_POST['action'] ?? '');
    $notes = trim((string) ($_POST['notes'] ?? ''));
    try {
        if ($action === 'reject') {
            vk_quotation_approve_level($pdo, $id, $notes, true);
            flash_set('success', 'Quotation rejected.');
        } elseif ($action === 'approve') {
            vk_quotation_approve_level($pdo, $id, $notes, false);
            flash_set('success', 'Approval recorded.');
        } else {
            flash_set('error', 'Invalid action.');
            redirect('/modules/quotations/approve.php?id=' . $id);
        }
        redirect('/modules/quotations/view.php?id=' . $id);
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
        redirect('/modules/quotations/approve.php?id=' . $id);
    }
}

$approvals = $pdo->prepare(
    'SELECT a.*, u.fullname AS approver_name FROM quotation_approvals a
     LEFT JOIN users u ON u.id = a.approver_id
     WHERE a.quotation_id = ? ORDER BY a.level ASC'
);
$approvals->execute([$id]);
$approvalRows = $approvals->fetchAll();

$currentLevel = max(1, (int) $q['approval_level']);
$currentStep = null;
foreach ($approvalRows as $a) {
    if ((int) $a['level'] === $currentLevel) {
        $currentStep = $a;
        break;
    }
}

$pageTitle = 'Approve Quotation';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="mb-3">
        <a href="<?= e(BASE_URL) ?>/modules/quotations/view.php?id=<?= $id ?>" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to quotation</a>
        <h1 class="h3 mt-2 mb-1">Approve / Reject</h1>
        <p class="text-muted mb-0"><?= e($q['quotation_number']) ?> · <?= e($q['customer_name']) ?> · <?= e(formatCurrency($q['grand_total'])) ?></p>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card vk-card mb-3">
                <div class="card-header bg-transparent fw-semibold">Approval history</div>
                <div class="card-body">
                    <?php if (!$approvalRows): ?>
                        <p class="text-muted mb-0">No approval steps configured.</p>
                    <?php else: ?>
                    <div class="qtn-approval-track">
                        <?php foreach ($approvalRows as $a): ?>
                            <div class="qtn-approval-step qtn-approval-step--<?= e($a['action']) ?>">
                                <div class="fw-semibold">Level <?= (int) $a['level'] ?> — <?= e(ucwords(str_replace('_', ' ', $a['role_label']))) ?></div>
                                <div class="small"><?= e(ucfirst($a['action'])) ?><?= $a['approver_name'] ? ' · ' . e($a['approver_name']) : '' ?></div>
                                <?php if ($a['notes']): ?><div class="small text-muted"><?= e($a['notes']) ?></div><?php endif; ?>
                                <?php if ($a['acted_at']): ?><div class="small text-muted"><?= e($a['acted_at']) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Your decision</div>
                <div class="card-body">
                    <?php if ($currentStep): ?>
                        <p class="small text-muted">Current step: <strong><?= e(ucwords(str_replace('_', ' ', $currentStep['role_label']))) ?></strong> (Level <?= $currentLevel ?>)</p>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <div class="mb-3">
                            <label class="form-label">Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Comments for the approval record…"></textarea>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-success"><i class="bi bi-check2 me-1"></i>Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger" onclick="return confirm('Reject this quotation?')"><i class="bi bi-x-lg me-1"></i>Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
