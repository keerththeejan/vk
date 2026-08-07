<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('settings');

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM quotation_templates WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/templates.php');
    }
    $action = (string) ($_POST['action'] ?? 'save');
    $tid = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $tid > 0) {
        $pdo->prepare('DELETE FROM quotation_templates WHERE id = ?')->execute([$tid]);
        flash_set('success', 'Template deleted.');
        redirect('/modules/quotations/templates.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        flash_set('error', 'Template name is required.');
        redirect('/modules/quotations/templates.php' . ($tid ? '?edit=' . $tid : ''));
    }

    $fields = [
        $name,
        trim((string) ($_POST['description'] ?? '')),
        trim((string) ($_POST['payment_terms'] ?? '')),
        trim((string) ($_POST['delivery_terms'] ?? '')),
        max(1, (int) ($_POST['validity_days'] ?? 30)),
        in_array(($_POST['tax_method'] ?? ''), ['exclusive', 'inclusive', 'none'], true) ? $_POST['tax_method'] : 'exclusive',
        (float) ($_POST['default_tax_pct'] ?? 0),
        trim((string) ($_POST['terms_html'] ?? '')),
        trim((string) ($_POST['notes'] ?? '')),
        isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($tid > 0) {
        $pdo->prepare(
            'UPDATE quotation_templates SET name=?, description=?, payment_terms=?, delivery_terms=?,
             validity_days=?, tax_method=?, default_tax_pct=?, terms_html=?, notes=?, is_active=? WHERE id=?'
        )->execute([...$fields, $tid]);
        flash_set('success', 'Template updated.');
    } else {
        $pdo->prepare(
            'INSERT INTO quotation_templates (name, description, payment_terms, delivery_terms, validity_days, tax_method, default_tax_pct, terms_html, notes, is_active, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([...$fields, isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null]);
        flash_set('success', 'Template created.');
    }
    redirect('/modules/quotations/templates.php');
}

$rows = $pdo->query('SELECT * FROM quotation_templates ORDER BY is_active DESC, name ASC')->fetchAll();
$h = $edit ?: [];

$pageTitle = 'Quotation Templates';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Settings</p>
            <h1 class="h3 mb-0">Quotation Templates</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/settings.php">Quotation settings</a>
    </div>

    <div class="row g-3">
        <div class="col-xl-5">
            <form class="card vk-card" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($h['id'] ?? 0) ?>">
                <div class="card-header bg-transparent fw-semibold"><?= $edit ? 'Edit template' : 'Add template' ?></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required value="<?= e((string) ($h['name'] ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= e((string) ($h['description'] ?? '')) ?></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Payment terms</label>
                            <input type="text" name="payment_terms" class="form-control" value="<?= e((string) ($h['payment_terms'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Delivery terms</label>
                            <input type="text" name="delivery_terms" class="form-control" value="<?= e((string) ($h['delivery_terms'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4">
                            <label class="form-label">Validity (days)</label>
                            <input type="number" name="validity_days" class="form-control" min="1" value="<?= (int) ($h['validity_days'] ?? 30) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tax method</label>
                            <select name="tax_method" class="form-select">
                                <?php foreach (['exclusive', 'inclusive', 'none'] as $tm): ?>
                                    <option value="<?= e($tm) ?>" <?= ($h['tax_method'] ?? 'exclusive') === $tm ? 'selected' : '' ?>><?= e(ucfirst($tm)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Default tax %</label>
                            <input type="number" step="0.01" name="default_tax_pct" class="form-control" value="<?= e((string) ($h['default_tax_pct'] ?? '0')) ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Terms (HTML/text)</label>
                        <textarea name="terms_html" class="form-control font-monospace" rows="4"><?= e((string) ($h['terms_html'] ?? '')) ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"><?= e((string) ($h['notes'] ?? '')) ?></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="is_active" <?= !isset($h['is_active']) || (int) $h['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <button type="submit" class="btn btn-primary">Save template</button>
                    <?php if ($edit): ?><a href="<?= e(BASE_URL) ?>/modules/quotations/templates.php" class="btn btn-link">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
        <div class="col-xl-7">
            <div class="card vk-card">
                <div class="card-header bg-transparent fw-semibold">Templates</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Validity</th><th>Tax</th><th>Status</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No templates yet.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <strong><?= e($r['name']) ?></strong>
                                    <?php if ($r['description']): ?><div class="small text-muted"><?= e(strlen((string) $r['description']) > 60 ? substr((string) $r['description'], 0, 60) . '…' : (string) $r['description']) ?></div><?php endif; ?>
                                </td>
                                <td><?= (int) $r['validity_days'] ?> days</td>
                                <td class="small"><?= e($r['tax_method']) ?> / <?= e($r['default_tax_pct']) ?>%</td>
                                <td><span class="badge text-bg-<?= (int) $r['is_active'] ? 'success' : 'secondary' ?>"><?= (int) $r['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete this template?')">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
