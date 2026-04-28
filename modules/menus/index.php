<?php
declare(strict_types=1);
$pageTitle = 'Site menus';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" crossorigin="anonymous"></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/site_menus.php';
vk_site_menus_ensure_schema($pdo);

$menuRows = [];
$tableOk = vk_site_menus_table_exists($pdo);
if ($tableOk) {
    $menuRows = $pdo->query(
        'SELECT id, name, slug, url, icon, sort_order, status FROM menus ORDER BY sort_order ASC, id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
}

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<?php if (!$tableOk): ?>
<div class="alert alert-danger">
    <strong>Menus table missing.</strong> Run <code>sql/upgrade_site_menus.sql</code> or reinstall, then reload.
</div>
<?php endif; ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Site navigation</h1>
        <p class="text-muted small mb-0">Public navbar links for <code>index.php</code> and other public pages. Drag to reorder; inactive items are hidden on the site.</p>
    </div>
    <button type="button" class="btn btn-primary" id="vkMenuAddBtn" data-bs-toggle="modal" data-bs-target="#vkMenuModal" <?= $tableOk ? '' : 'disabled' ?>>
        <i class="bi bi-plus-lg me-1"></i>Add menu
    </button>
</div>

<div class="card vk-card">
    <div class="card-body">
        <p class="small text-muted mb-2">Drag the grip icon to change order. Changes save automatically.</p>
        <ul class="list-group list-group-flush" id="vkMenuAdminList">
            <?php foreach ($menuRows as $row): ?>
                <?php
                $mid = (int) ($row['id'] ?? 0);
                $st = (string) ($row['status'] ?? 'active');
                $isActive = $st === 'active';
                ?>
                <li class="list-group-item d-flex flex-wrap align-items-center gap-2 vk-menu-admin-row"
                    data-id="<?= $mid ?>"
                    data-name="<?= e((string) ($row['name'] ?? '')) ?>"
                    data-slug="<?= e((string) ($row['slug'] ?? '')) ?>"
                    data-url="<?= e((string) ($row['url'] ?? '')) ?>"
                    data-icon="<?= e((string) ($row['icon'] ?? '')) ?>"
                    data-status="<?= e($st) ?>">
                    <span class="vk-menu-drag text-muted me-1" style="cursor:grab" title="Drag to reorder" aria-hidden="true"><i class="bi bi-grip-vertical fs-5"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate"><?= e((string) ($row['name'] ?? '')) ?></div>
                        <div class="small text-muted text-truncate"><code class="small"><?= e((string) ($row['slug'] ?? '')) ?></code> · <?= e((string) ($row['url'] ?? '')) ?></div>
                    </div>
                    <span class="badge <?= $isActive ? 'text-bg-success' : 'text-bg-secondary' ?> vk-menu-status-badge"><?= $isActive ? 'Active' : 'Hidden' ?></span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary vk-menu-toggle" data-id="<?= $mid ?>" data-next-status="<?= $isActive ? 'inactive' : 'active' ?>" title="Toggle visibility">
                            <?= $isActive ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>' ?>
                        </button>
                        <button type="button" class="btn btn-outline-primary vk-menu-edit" data-bs-toggle="modal" data-bs-target="#vkMenuModal"
                            data-id="<?= $mid ?>"
                            data-name="<?= e((string) ($row['name'] ?? '')) ?>"
                            data-slug="<?= e((string) ($row['slug'] ?? '')) ?>"
                            data-url="<?= e((string) ($row['url'] ?? '')) ?>"
                            data-icon="<?= e((string) ($row['icon'] ?? '')) ?>"
                            data-status="<?= e($st) ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger vk-menu-delete" data-id="<?= $mid ?>" data-name="<?= e((string) ($row['name'] ?? '')) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($tableOk && !$menuRows): ?>
            <p class="text-muted small mb-0 mt-2">No rows yet. Defaults seed on first load when the table is empty — refresh or add a menu.</p>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-light border small mt-3 mb-0">
    <strong>Icons:</strong> use Lucide names with prefix <code>lucide:home</code> (matches public site), or Bootstrap Icons <code>bi bi-star</code>.
    <strong>URLs:</strong> relative paths only (e.g. <code>book.php</code>, <code>vehicle/index.php</code>); unsafe URLs are rejected server-side.
</div>

<div class="modal fade" id="vkMenuModal" tabindex="-1" aria-labelledby="vkMenuModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="vkMenuModalTitle">Add menu</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="vkMenuFormId" value="0">
                <div class="mb-3">
                    <label class="form-label" for="vkMenuFormName">Name</label>
                    <input type="text" class="form-control" id="vkMenuFormName" maxlength="100" required autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="vkMenuFormSlug">Slug</label>
                    <input type="text" class="form-control" id="vkMenuFormSlug" maxlength="100" pattern="[a-z0-9\-]+" required autocomplete="off">
                    <div class="form-text">Lowercase, numbers, hyphens. Used for “active page” highlighting in code (<code>$navActive</code>).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="vkMenuFormUrl">URL</label>
                    <input type="text" class="form-control" id="vkMenuFormUrl" maxlength="255" required placeholder="index.php" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="vkMenuFormIcon">Icon (optional)</label>
                    <input type="text" class="form-control" id="vkMenuFormIcon" maxlength="100" placeholder="lucide:home" autocomplete="off">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="vkMenuFormStatus">Visibility</label>
                    <select class="form-select" id="vkMenuFormStatus">
                        <option value="active">Active (show on site)</option>
                        <option value="inactive">Inactive (hidden)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="vkMenuFormSave">Save</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/menus-admin.js')) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
