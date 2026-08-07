<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('settings');

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
    $st = $pdo->prepare('SELECT * FROM quotation_categories WHERE id = ?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/categories.php');
    }
    $action = (string) ($_POST['action'] ?? 'save');
    $cid = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $cid > 0) {
        $pdo->prepare('DELETE FROM quotation_categories WHERE id = ?')->execute([$cid]);
        flash_set('success', 'Category deleted.');
        redirect('/modules/quotations/categories.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        flash_set('error', 'Category name is required.');
        redirect('/modules/quotations/categories.php' . ($cid ? '?edit=' . $cid : ''));
    }
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
    if ($slug === '') {
        $slug = 'cat-' . time();
    }

    $fields = [
        $name,
        $slug,
        trim((string) ($_POST['description'] ?? '')),
        trim((string) ($_POST['color'] ?? '#0B4DBA')),
        (int) ($_POST['sort_order'] ?? 0),
        isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($cid > 0) {
        $pdo->prepare(
            'UPDATE quotation_categories SET name=?, slug=?, description=?, color=?, sort_order=?, is_active=? WHERE id=?'
        )->execute([...$fields, $cid]);
        flash_set('success', 'Category updated.');
    } else {
        $pdo->prepare(
            'INSERT INTO quotation_categories (name, slug, description, color, sort_order, is_active) VALUES (?,?,?,?,?,?)'
        )->execute($fields);
        flash_set('success', 'Category created.');
    }
    redirect('/modules/quotations/categories.php');
}

$rows = $pdo->query('SELECT * FROM quotation_categories ORDER BY sort_order ASC, name ASC')->fetchAll();
$h = $edit ?: [];

$pageTitle = 'Quotation Categories';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Settings</p>
            <h1 class="h3 mb-0">Quotation Categories</h1>
        </div>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/templates.php">Templates</a>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <form class="card vk-card" method="post">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) ($h['id'] ?? 0) ?>">
                <div class="card-header bg-transparent fw-semibold"><?= $edit ? 'Edit category' : 'Add category' ?></div>
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
                        <div class="col-6">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="<?= e((string) ($h['color'] ?? '#0B4DBA')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Sort order</label>
                            <input type="number" name="sort_order" class="form-control" value="<?= (int) ($h['sort_order'] ?? 0) ?>">
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input type="checkbox" name="is_active" class="form-check-input" id="cat_active" <?= !isset($h['is_active']) || (int) $h['is_active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="cat_active">Active</label>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <?php if ($edit): ?><a href="<?= e(BASE_URL) ?>/modules/quotations/categories.php" class="btn btn-link">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="card vk-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th></th><th>Name</th><th>Slug</th><th>Order</th><th>Status</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><span class="d-inline-block rounded-circle" style="width:14px;height:14px;background:<?= e($r['color']) ?>"></span></td>
                                <td><?= e($r['name']) ?></td>
                                <td class="small text-muted"><?= e($r['slug']) ?></td>
                                <td><?= (int) $r['sort_order'] ?></td>
                                <td><span class="badge text-bg-<?= (int) $r['is_active'] ? 'success' : 'secondary' ?>"><?= (int) $r['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Delete category?')">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
