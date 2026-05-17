<?php
declare(strict_types=1);
$pageTitle = 'Products';
$extraHead = <<<HTML
<style>
:root {
    color-scheme: dark;
    --vk-bg: #061229;
    --vk-surface: rgba(10, 18, 40, 0.95);
    --vk-surface-strong: rgba(9, 15, 34, 0.98);
    --vk-border: rgba(255, 255, 255, 0.08);
    --vk-text: #eef2ff;
    --vk-muted: rgba(238, 242, 255, 0.68);
    --vk-accent: #5cc8ff;
    --vk-accent-soft: rgba(92, 200, 255, 0.16);
    --vk-shadow: 0 36px 80px rgba(0, 0, 0, 0.25);
}
.vk-dashboard-shell {
    position: relative;
    max-width: 1180px;
    margin: 0 auto;
    padding: 2rem 0 3rem;
}
.vk-dashboard-shell::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.16), transparent 18%),
                radial-gradient(circle at bottom right, rgba(79, 70, 229, 0.14), transparent 20%),
                linear-gradient(180deg, rgba(8, 14, 33, 0.92), rgba(3, 7, 22, 0.98));
    z-index: -1;
}
.vk-breadcrumb {
    font-size: 0.92rem;
}
.vk-breadcrumb .breadcrumb-item a {
    color: rgba(238, 242, 255, 0.72);
}
.vk-breadcrumb .breadcrumb-item a:hover {
    color: #ffffff;
}
.vk-page-header,
.vk-panel-card,
.vk-empty-state {
    background: rgba(7, 18, 40, 0.90);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(18px);
    box-shadow: var(--vk-shadow);
    border-radius: 1.25rem;
}
.vk-page-header {
    padding: 1.9rem 1.8rem;
}
.vk-page-header h1 {
    color: #ffffff;
    font-size: clamp(2.1rem, 2vw, 2.7rem);
    margin-bottom: 0.65rem;
}
.vk-page-header p {
    color: rgba(238, 242, 255, 0.72);
    max-width: 720px;
}
.vk-stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}
.vk-stat-card {
    padding: 1.4rem 1.5rem;
    border-radius: 1.15rem;
    background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02));
    border: 1px solid rgba(255, 255, 255, 0.08);
    color: #f8fbff;
}
.vk-stat-card .badge {
    background: rgba(92, 200, 255, 0.14);
    color: var(--vk-accent);
}
.vk-stat-card h3 {
    font-size: 2rem;
    margin-bottom: 0.25rem;
}
.vk-stat-card p {
    color: rgba(238, 242, 255, 0.72);
    margin-bottom: 0;
}
.vk-controls {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1rem;
    align-items: center;
    margin: 1.6rem 0;
}
.vk-filters {
    display: grid;
    grid-template-columns: 1fr repeat(2, minmax(180px, 240px));
    gap: 1rem;
}
.vk-filters .form-control,
.vk-filters .form-select {
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    color: #eef2ff;
    min-height: 3.8rem;
}
.vk-filters .form-control:focus,
.vk-filters .form-select:focus {
    box-shadow: 0 0 0 0.3rem rgba(92, 200, 255, 0.17);
    border-color: rgba(92, 200, 255, 0.68);
}
.vk-filters .input-group-text {
    background: transparent;
    border: none;
    color: rgba(238, 242, 255, 0.68);
}
.vk-btn-group .btn {
    min-height: 3.8rem;
    border-radius: 1rem;
}
.vk-btn-gradient {
    background-image: linear-gradient(135deg, #4f7dff 0%, #29d2ff 100%);
    border: none;
    color: white;
    box-shadow: 0 18px 40px rgba(45, 111, 255, 0.3);
}
.vk-btn-gradient:hover {
    transform: translateY(-1px);
}
.vk-panel-card {
    padding: 1.5rem;
}
.vk-panel-card table {
    border-collapse: separate;
    border-spacing: 0;
}
.vk-panel-card thead th {
    position: sticky;
    top: 0;
    background: rgba(8, 15, 34, 0.96);
    color: #cbd5e1;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    z-index: 1;
}
.vk-panel-card tbody tr {
    transition: transform 0.2s ease, background-color 0.2s ease;
}
.vk-panel-card tbody tr:hover {
    transform: translateY(-1px);
    background: rgba(92, 200, 255, 0.08);
}
.vk-panel-card tbody tr td {
    vertical-align: middle;
    color: #f8fbff;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}
.vk-panel-card .product-preview {
    display: inline-flex;
    align-items: center;
    gap: 0.85rem;
}
.vk-product-avatar {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    background: rgba(92, 200, 255, 0.16);
    color: #dbe9ff;
    font-weight: 700;
}
.vk-badge-status {
    padding: 0.4rem 0.7rem;
    border-radius: 0.8rem;
    font-size: 0.78rem;
    font-weight: 600;
}
.vk-badge-in-stock {
    background: rgba(52, 211, 153, 0.12);
    color: #8bffdd;
}
.vk-badge-low-stock {
    background: rgba(249, 115, 22, 0.14);
    color: #ffd08a;
}
.vk-badge-out-stock {
    background: rgba(248, 113, 113, 0.16);
    color: #ffb3b3;
}
.vk-action-buttons .btn {
    border-radius: 0.95rem;
    min-width: 2.85rem;
}
.vk-empty-state {
    text-align: center;
    padding: 3rem 2rem;
}
.vk-empty-state h2 {
    color: #ffffff;
    margin-bottom: 0.75rem;
}
.vk-empty-state p {
    color: rgba(238, 242, 255, 0.72);
    margin-bottom: 1.75rem;
}
.vk-empty-illustration {
    width: 82px;
    height: 82px;
    margin: 0 auto 1.5rem;
    display: grid;
    place-items: center;
    border-radius: 1.25rem;
    background: linear-gradient(135deg, rgba(79, 125, 255, 0.22), rgba(44, 214, 255, 0.12));
}
.vk-empty-illustration i {
    font-size: 2.25rem;
    color: #7dd3fc;
}
.vk-pagination-info {
    color: rgba(238, 242, 255, 0.68);
}
.vk-pagination .page-link {
    border-radius: 0.85rem;
}
.vk-pagination .page-item.active .page-link {
    background: #4f7dff;
    border-color: #4f7dff;
}
.vk-pagination .page-link {
    color: #cbd5e1;
    border-color: rgba(255, 255, 255, 0.06);
}
.vk-table-wrap {
    overflow-x: auto;
}
.vk-table-wrap::-webkit-scrollbar {
    height: 8px;
}
.vk-table-wrap::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 999px;
}
@media (max-width: 1199.98px) {
    .vk-stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 767.98px) {
    .vk-dashboard-shell {
        padding: 1.25rem 0 2rem;
    }
    .vk-controls {
        grid-template-columns: 1fr;
    }
    .vk-filters {
        grid-template-columns: 1fr;
    }
    .vk-stat-grid {
        grid-template-columns: 1fr;
    }
    .vk-action-buttons {
        justify-content: stretch;
    }
    .vk-action-buttons .btn {
        width: 100%;
    }
}
</style>
HTML;
$extraScripts = <<<HTML
<script>
(function () {
    const searchInput = document.querySelector('[data-search-input]');
    const categorySelect = document.querySelector('[data-category-filter]');
    const tableBody = document.querySelector('#productTable tbody');
    const rows = tableBody ? Array.from(tableBody.querySelectorAll('tr[data-product-row]')) : [];
    const bulkMaster = document.querySelector('#bulkSelectAll');
    const exportButton = document.querySelector('[data-action="export"]');
    const importButton = document.querySelector('[data-action="import"]');
    const duplicateButtons = Array.from(document.querySelectorAll('[data-action="duplicate"]'));
    const noResultsRow = document.querySelector('#noResultsRow');

    function filterTable() {
        const query = searchInput?.value.trim().toLowerCase() || '';
        const category = categorySelect?.value || '';
        let visibleCount = 0;
        rows.forEach(function (row) {
            const text = row.getAttribute('data-search') || '';
            const categoryValue = row.getAttribute('data-category') || '';
            const matchesQuery = !query || text.includes(query);
            const matchesCategory = !category || categoryValue === category;
            row.classList.toggle('d-none', !(matchesQuery && matchesCategory));
            if (matchesQuery && matchesCategory) visibleCount += 1;
        });
        if (noResultsRow) {
            noResultsRow.classList.toggle('d-none', visibleCount > 0);
        }
    }

    function updateBulkSelection() {
        if (!bulkMaster) return;
        const checked = Array.from(document.querySelectorAll('.bulk-row-checkbox:checked')).length;
        const total = Array.from(document.querySelectorAll('.bulk-row-checkbox')).length;
        bulkMaster.checked = checked === total && total > 0;
        bulkMaster.indeterminate = checked > 0 && checked < total;
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }
    if (categorySelect) {
        categorySelect.addEventListener('change', filterTable);
    }
    if (bulkMaster) {
        bulkMaster.addEventListener('change', function () {
            const checked = bulkMaster.checked;
            document.querySelectorAll('.bulk-row-checkbox').forEach(function (checkbox) {
                checkbox.checked = checked;
            });
            updateBulkSelection();
        });
    }
    document.querySelectorAll('.bulk-row-checkbox').forEach(function (checkbox) {
        checkbox.addEventListener('change', updateBulkSelection);
    });
    if (exportButton) {
        exportButton.addEventListener('click', function () {
            window.showToast('Export feature coming soon.', 'info');
        });
    }
    if (importButton) {
        importButton.addEventListener('click', function () {
            window.showToast('Import feature coming soon.', 'info');
        });
    }
    duplicateButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            window.showToast('Duplicate is not available yet.', 'warning');
        });
    });
})();
</script>
HTML;
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$q = trim((string) ($_GET['q'] ?? ''));
$cat = trim((string) ($_GET['category'] ?? ''));
$page = max(1, (int) ($_GET['p'] ?? 1));
$perPage = 15;
$where = '1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR category LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($cat !== '') {
    $where .= ' AND category = ?';
    $params[] = $cat;
}
$countSt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $where");
$countSt->execute($params);
$total = (int) $countSt->fetchColumn();
$pg = paginate($total, $page, $perPage);

$summary = $pdo->query('SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
    SUM(CASE WHEN stock > 0 AND stock <= COALESCE(low_stock_threshold, 5) THEN 1 ELSE 0 END) AS low_stock,
    COUNT(DISTINCT category) AS categories
FROM products')->fetch(PDO::FETCH_ASSOC);
$statsTotal = (int) ($summary['total'] ?? 0);
$statsLow = (int) ($summary['low_stock'] ?? 0);
$statsOut = (int) ($summary['out_of_stock'] ?? 0);
$statsCategories = (int) ($summary['categories'] ?? 0);

$sql = "SELECT * FROM products WHERE $where ORDER BY id DESC LIMIT {$pg['perPage']} OFFSET {$pg['offset']}";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$cats = $pdo->query('SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> "" ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
$productNames = $pdo->query('SELECT DISTINCT name FROM products ORDER BY name LIMIT 100')->fetchAll(PDO::FETCH_COLUMN);
?>
<div class="vk-dashboard-shell">
    <nav aria-label="breadcrumb" class="vk-breadcrumb mb-3">
        <ol class="breadcrumb breadcrumb-transparent px-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= e(BASE_URL) ?>/modules/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Products</li>
        </ol>
    </nav>

    <section class="vk-page-header mb-4">
        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-start">
            <div>
                <h1>Inventory management</h1>
                <p>Manage your product catalogue from one premium dashboard. Search, sort, and edit items with a polished enterprise-grade interface.</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <a href="<?= e(BASE_URL) ?>/modules/products/add.php" class="btn vk-btn-gradient btn-lg"> <i class="bi bi-plus-lg me-2"></i> Add product</a>
            </div>
        </div>
        <div class="vk-stat-grid mt-4">
            <div class="vk-stat-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge">Total products</span>
                    <i class="bi bi-box-seam fs-4 text-primary"></i>
                </div>
                <h3><?= number_format($statsTotal) ?></h3>
                <p><?= $statsTotal === 1 ? 'item in inventory' : 'items in inventory' ?></p>
            </div>
            <div class="vk-stat-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge">Low stock</span>
                    <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
                </div>
                <h3><?= number_format($statsLow) ?></h3>
                <p><?= $statsLow === 1 ? 'product nearing restock' : 'products nearing restock' ?></p>
            </div>
            <div class="vk-stat-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge">Out of stock</span>
                    <i class="bi bi-x-octagon-fill fs-4 text-danger"></i>
                </div>
                <h3><?= number_format($statsOut) ?></h3>
                <p><?= $statsOut === 1 ? 'out of stock item' : 'out of stock items' ?></p>
            </div>
            <div class="vk-stat-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge">Categories</span>
                    <i class="bi bi-tags-fill fs-4 text-info"></i>
                </div>
                <h3><?= number_format($statsCategories) ?></h3>
                <p><?= $statsCategories === 1 ? 'category in use' : 'categories in use' ?></p>
            </div>
        </div>
    </section>

    <section class="vk-controls">
        <div class="vk-filters">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" placeholder="Search products, categories..." value="<?= e($q) ?>" data-search-input list="searchSuggestions" aria-label="Search products">
                <datalist id="searchSuggestions">
                    <?php foreach ($productNames as $prodName): ?>
                        <option value="<?= e($prodName) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <select class="form-select" name="category" data-category-filter aria-label="Filter by category">
                <option value="">All categories</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= e((string) $c) ?>" <?= $cat === (string) $c ? 'selected' : '' ?>><?= e((string) $c) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="d-flex gap-2 align-items-center vk-btn-group">
                <button type="button" class="btn btn-outline-light" data-action="import"><i class="bi bi-file-arrow-up me-1"></i> Import</button>
                <button type="button" class="btn btn-outline-light" data-action="export"><i class="bi bi-file-arrow-down me-1"></i> Export</button>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center vk-action-buttons">
            <button type="button" class="btn btn-outline-light" onclick="window.location.href='<?= e(BASE_URL) ?>/modules/products/add.php'">Quick add</button>
            <button type="button" class="btn vk-btn-gradient" onclick="window.location.href='<?= e(BASE_URL) ?>/modules/products/add.php'">New product</button>
        </div>
    </section>

    <section class="vk-panel-card">
        <?php if (!$rows): ?>
            <div class="vk-empty-state">
                <div class="vk-empty-illustration"><i class="bi bi-box-seam"></i></div>
                <h2>No products in inventory yet</h2>
                <p>Create your first product and start tracking stock, pricing, and product details from a beautiful dashboard.</p>
                <a href="<?= e(BASE_URL) ?>/modules/products/add.php" class="btn vk-btn-gradient btn-lg">Add your first product</a>
            </div>
        <?php else: ?>
            <div class="vk-table-wrap">
                <table id="productTable" class="table table-borderless align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center"><input id="bulkSelectAll" type="checkbox" aria-label="Select all products"></th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <?php
                            $stock = (int) ($r['stock'] ?? 0);
                            $lowAt = isset($r['low_stock_threshold']) ? (int) $r['low_stock_threshold'] : 5;
                            if ($stock <= 0) {
                                $statusLabel = 'Out of stock';
                                $statusClass = 'vk-badge-out-stock';
                            } elseif ($stock <= $lowAt) {
                                $statusLabel = 'Low stock';
                                $statusClass = 'vk-badge-low-stock';
                            } else {
                                $statusLabel = 'In stock';
                                $statusClass = 'vk-badge-in-stock';
                            }
                            $lastUpdated = $r['updated_at'] ?? $r['created_at'] ?? '—';
                            $productName = $r['name'] ? e($r['name']) : 'Untitled';
                            $initial = mb_substr($r['name'] ?? 'P', 0, 1);
                            ?>
                            <tr data-product-row data-search="<?= e(strtolower($r['name'] . ' ' . ($r['category'] ?? '') . ' ' . ($r['sku'] ?? ''))) ?>" data-category="<?= e($r['category'] ?? '') ?>">
                                <td class="text-center"><input class="form-check-input bulk-row-checkbox" type="checkbox" aria-label="Select product"></td>
                                <td>
                                    <div class="product-preview">
                                        <span class="vk-product-avatar"><?= e($initial) ?></span>
                                        <div>
                                            <div class="fw-semibold"><?= $productName ?></div>
                                            <div class="text-muted small"><?= e($r['category'] ?? 'Uncategorized') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($r['sku'] ?? '—') ?></td>
                                <td><?= e($r['category'] ?? '—') ?></td>
                                <td><?= e(number_format((float) $r['price'], 2)) ?></td>
                                <td><?= $stock ?></td>
                                <td><span class="vk-badge-status <?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                <td><?= e($lastUpdated) ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="<?= e(BASE_URL) ?>/modules/products/edit.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-light" aria-label="Edit <?= $productName ?>"><i class="bi bi-pencil-fill"></i></a>
                                        <a href="<?= e(BASE_URL) ?>/modules/products/delete.php?id=<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this product?');" aria-label="Delete <?= $productName ?>"><i class="bi bi-trash-fill"></i></a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="duplicate" aria-label="Duplicate <?= $productName ?>"><i class="bi bi-files"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="noResultsRow" class="d-none">
                            <td colspan="9" class="text-center py-5 text-muted">No products match your current filters. Try updating the search or category.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
            <div class="vk-pagination-info">Showing <?= count($rows) ?> of <?= number_format($total) ?> products</div>
            <?php if ($pg['pages'] > 1): ?>
                <nav class="vk-pagination" aria-label="Product list pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
                            <li class="page-item <?= $i === $pg['page'] ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= e(http_build_query(['q' => $q, 'category' => $cat, 'p' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
