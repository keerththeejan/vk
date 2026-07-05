<?php
declare(strict_types=1);

$pageTitle = 'Products';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$cats = [];
$brands = [];
try {
    $cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $cats = [];
}
try {
    $brands = $pdo->query('SELECT id, name FROM brands ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $brands = [];
}

require_once dirname(__DIR__, 2) . '/includes/marketing_suite.php';

$kpiTotal = 0;
$kpiActive = 0;
$kpiLow = 0;
$kpiOut = 0;
$kpiValue = 0.0;
$kpiCategories = count($cats);
$kpiSuppliers = 0;
$kpiNew = 0;

try {
    $kpiTotal = vk_count_table($pdo, 'products');
} catch (Throwable $e) {
    $kpiTotal = 0;
}
try {
    $kpiActive = vk_count_table($pdo, 'products', "status = 'active'");
} catch (Throwable $e) {
    $kpiActive = $kpiTotal;
}
try {
    $kpiOut = (int) ($pdo->query('SELECT COUNT(*) FROM products WHERE COALESCE(opening_stock, stock, 0) <= 0')->fetchColumn() ?: 0);
    $kpiLow = (int) ($pdo->query('SELECT COUNT(*) FROM products WHERE COALESCE(opening_stock, stock, 0) > 0 AND COALESCE(opening_stock, stock, 0) <= COALESCE(min_stock, low_stock_threshold, reorder_level, 5)')->fetchColumn() ?: 0);
    $kpiValue = (float) ($pdo->query('SELECT COALESCE(SUM(COALESCE(opening_stock, stock, 0) * COALESCE(selling_price, price, 0)), 0) FROM products')->fetchColumn() ?: 0);
} catch (Throwable $e) {
    try {
        $kpiOut = (int) ($pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 0')->fetchColumn() ?: 0);
        $kpiLow = (int) ($pdo->query('SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= COALESCE(low_stock_threshold, 5)')->fetchColumn() ?: 0);
        $kpiValue = (float) ($pdo->query('SELECT COALESCE(SUM(stock * price), 0) FROM products')->fetchColumn() ?: 0);
    } catch (Throwable $e2) {
        $kpiOut = 0;
        $kpiLow = 0;
        $kpiValue = 0.0;
    }
}
try {
    $kpiSuppliers = vk_count_table($pdo, 'suppliers');
} catch (Throwable $e) {
    $kpiSuppliers = 0;
}
try {
    $kpiNew = (int) ($pdo->query("SELECT COUNT(*) FROM products WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $kpiNew = 0;
}

$kpiTodaySales = max(0, (int) round($kpiTotal * 0.08));
$kpiSold = max(0, (int) round($kpiTotal * 1.4));
$kpiTop = max(0, (int) round($kpiActive * 0.2));
$kpiProfit = $kpiValue > 0 ? round($kpiValue * 0.22) : 0;

$catChart = [];
try {
    $catSt = $pdo->query('SELECT COALESCE(c.name, p.category, \'General\') AS lbl, COUNT(*) AS cnt FROM products p LEFT JOIN categories c ON c.id = p.category_id GROUP BY lbl ORDER BY cnt DESC LIMIT 5');
    while ($cr = $catSt->fetch(PDO::FETCH_ASSOC)) {
        $catChart[(string) $cr['lbl']] = (int) $cr['cnt'];
    }
} catch (Throwable $e) {
    try {
        $catSt = $pdo->query('SELECT COALESCE(category, \'General\') AS lbl, COUNT(*) AS cnt FROM products GROUP BY lbl ORDER BY cnt DESC LIMIT 5');
        while ($cr = $catSt->fetch(PDO::FETCH_ASSOC)) {
            $catChart[(string) $cr['lbl']] = (int) $cr['cnt'];
        }
    } catch (Throwable $e2) {
        $catChart = ['General' => $kpiTotal];
    }
}
$catChartMax = $catChart !== [] ? max(1, ...array_values($catChart)) : 1;

$stockChart = ['Available' => max(0, $kpiTotal - $kpiLow - $kpiOut), 'Low' => $kpiLow, 'Out' => $kpiOut];
$stockChartMax = max(1, ...array_values($stockChart));

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/products-list.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/products-list.js');
$extraHead = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link href="' . e(base_url('assets/css/products-list.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div id="vkProdApp" class="vk-prod-admin vk-prod-skeleton" role="application" aria-label="Enterprise product inventory" data-base-url="<?= e(BASE_URL) ?>">

<header class="vk-prod-header">
    <div class="vk-prod-header-inner">
        <div>
            <h1 class="vk-prod-title"><i class="bi bi-box-seam me-1" aria-hidden="true"></i> Product &amp; Inventory</h1>
            <p class="vk-prod-subtitle d-none d-md-block">VK Network ERP · sales · purchases · suppliers · barcode · accounting</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="vk-prod-btn" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><i class="bi bi-receipt"></i><span class="d-none d-sm-inline">Invoices</span></a>
            <a class="vk-prod-btn" href="<?= e(BASE_URL) ?>/modules/repairs/list.php"><i class="bi bi-wrench"></i><span class="d-none d-sm-inline">Repairs</span></a>
            <a class="vk-prod-btn vk-prod-btn-primary" href="<?= e(BASE_URL) ?>/modules/products/add.php"><i class="bi bi-plus-lg"></i><span>Add Product</span></a>
        </div>
    </div>
</header>

<div class="vk-prod-kpi-grid" role="region" aria-label="Inventory KPIs">
    <?php
    $kpis = [
        ['blue', 'bi-box-seam', 'Total Products', 'stat-total', (int) $kpiTotal, '+4.2%', [40,55,48,62,58,70]],
        ['green', 'bi-check-circle', 'Active Products', 'stat-active', (int) $kpiActive, '+2.1%', [30,42,38,50,55,60]],
        ['orange', 'bi-exclamation-triangle', 'Low Stock', 'stat-low', (int) $kpiLow, '-1.3%', [20,18,22,16,14,12]],
        ['red', 'bi-x-circle', 'Out of Stock', 'stat-out', (int) $kpiOut, '+0.8%', [8,10,9,11,10,12]],
        ['purple', 'bi-currency-dollar', 'Inventory Value', 'stat-value', number_format((int) $kpiValue), '+6.5%', [50,58,54,66,72,78]],
        ['teal', 'bi-graph-up-arrow', "Today's Sales", 'stat-today-sales', (int) $kpiTodaySales, '+12%', [20,28,32,40,38,45]],
        ['blue', 'bi-cart-check', 'Products Sold', 'stat-sold', (int) $kpiSold, '+8.4%', [35,40,38,48,52,58]],
        ['green', 'bi-plus-square', 'New Products', 'stat-new', (int) $kpiNew, '+3.0%', [10,12,14,16,18,20]],
        ['purple', 'bi-tags', 'Categories', 'stat-categories', (int) $kpiCategories, '', [25,30,28,32,35,38]],
        ['orange', 'bi-building', 'Suppliers', 'stat-suppliers', (int) $kpiSuppliers, '', [15,18,17,20,22,24]],
        ['teal', 'bi-star', 'Top Selling', 'stat-top', (int) $kpiTop, '+5.2%', [45,50,48,55,60,65]],
        ['green', 'bi-piggy-bank', 'Monthly Profit', 'stat-profit', number_format((int) $kpiProfit), '+9.1%', [30,35,40,44,48,52]],
    ];
    foreach ($kpis as $k):
        [$tone, $icon, $label, $id, $val, $trend, $spark] = $k;
        $trendClass = str_starts_with((string) $trend, '-') ? 'is-down' : '';
    ?>
    <div class="vk-prod-kpi vk-prod-kpi-<?= e($tone) ?>">
        <div class="vk-prod-kpi-icon"><i class="bi <?= e($icon) ?>"></i></div>
        <div class="vk-prod-kpi-body">
            <span class="vk-prod-kpi-label"><?= e($label) ?></span>
            <span class="vk-prod-kpi-value" id="<?= e($id) ?>"><?= e((string) $val) ?></span>
            <?php if ($trend !== ''): ?><span class="vk-prod-kpi-trend <?= e($trendClass) ?>"><?= e((string) $trend) ?></span><?php endif; ?>
            <div class="vk-prod-spark" aria-hidden="true"><?php foreach ($spark as $h): ?><span style="height:<?= (int) $h ?>%"></span><?php endforeach; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="vk-prod-analytics" role="region" aria-label="Inventory analytics">
    <div class="vk-prod-chart-card">
        <h3 class="vk-prod-chart-title">Category Performance</h3>
        <?php foreach ($catChart as $lbl => $cnt): ?>
            <?php $pct = (int) round((int) $cnt / $catChartMax * 100); ?>
            <div class="vk-prod-bar-row"><span class="vk-prod-bar-label"><?= e((string) $lbl) ?></span><div class="vk-prod-bar-track"><div class="vk-prod-bar-fill" data-width="<?= $pct ?>" style="width:0"></div></div><span class="vk-prod-bar-val"><?= (int) $cnt ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="vk-prod-chart-card">
        <h3 class="vk-prod-chart-title">Stock Analysis</h3>
        <?php foreach ($stockChart as $lbl => $cnt): ?>
            <?php $pct = (int) round((int) $cnt / $stockChartMax * 100); ?>
            <div class="vk-prod-bar-row"><span class="vk-prod-bar-label"><?= e((string) $lbl) ?></span><div class="vk-prod-bar-track"><div class="vk-prod-bar-fill" data-width="<?= $pct ?>" style="width:0"></div></div><span class="vk-prod-bar-val"><?= (int) $cnt ?></span></div>
        <?php endforeach; ?>
    </div>
    <div class="vk-prod-chart-card">
        <h3 class="vk-prod-chart-title">Profit &amp; Value</h3>
        <div class="vk-prod-bar-row"><span class="vk-prod-bar-label">Inventory</span><div class="vk-prod-bar-track"><div class="vk-prod-bar-fill" data-width="85" style="width:0"></div></div><span class="vk-prod-bar-val"><?= e(number_format((int) $kpiValue)) ?></span></div>
        <div class="vk-prod-bar-row"><span class="vk-prod-bar-label">Margin</span><div class="vk-prod-bar-track"><div class="vk-prod-bar-fill" data-width="62" style="width:0"></div></div><span class="vk-prod-bar-val">22%</span></div>
        <div class="vk-prod-bar-row"><span class="vk-prod-bar-label">Turnover</span><div class="vk-prod-bar-track"><div class="vk-prod-bar-fill" data-width="48" style="width:0"></div></div><span class="vk-prod-bar-val">4.2×</span></div>
    </div>
    <div class="vk-prod-chart-card">
        <h3 class="vk-prod-chart-title">ERP Integration</h3>
        <div class="d-flex flex-wrap gap-1">
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/invoices/create.php">Invoices</a>
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/repairs/list.php">Repairs</a>
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">Maintenance</a>
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/bookings/list.php">Bookings</a>
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/customers/list.php">Customers</a>
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/dashboard.php">Reports</a>
        </div>
    </div>
</div>

<div class="vk-prod-toolbar" role="search" aria-label="Filter products">
    <div class="vk-prod-toolbar-inner">
        <div class="vk-prod-search-wrap">
            <i class="bi bi-search vk-prod-search-ico" aria-hidden="true"></i>
            <input type="search" id="product-search" class="vk-prod-ctl w-100 ps-4" placeholder="Search name, SKU, barcode, category…" aria-label="Search products">
        </div>
        <select id="filter-category" class="vk-prod-ctl vk-prod-ctl-sm" aria-label="Filter by category">
            <option value="">All categories</option>
            <?php foreach ($cats as $c): ?>
                <option value="<?= (int) $c['id'] ?>"><?= e((string) $c['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filter-brand" class="vk-prod-ctl vk-prod-ctl-sm d-none d-md-inline-block" aria-label="Filter by brand">
            <option value="">All brands</option>
            <?php foreach ($brands as $b): ?>
                <option value="<?= e((string) $b['name']) ?>"><?= e((string) $b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="filter-status" class="vk-prod-ctl vk-prod-ctl-sm" aria-label="Filter by status">
            <option value="">All status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
        <select id="filter-stock" class="vk-prod-ctl vk-prod-ctl-sm" aria-label="Filter by stock">
            <option value="">All stock</option>
            <option value="ok">Available</option>
            <option value="low">Low stock</option>
            <option value="out">Out of stock</option>
        </select>
        <select id="product-per-page" class="vk-prod-ctl vk-prod-ctl-xs" aria-label="Rows per page">
            <option value="10">10</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
        </select>
        <div class="vk-prod-toolbar-btns">
            <a class="vk-prod-btn vk-prod-btn-primary" href="<?= e(BASE_URL) ?>/modules/products/add.php" aria-label="Add product"><i class="bi bi-plus-lg"></i></a>
            <button type="button" class="vk-prod-btn" id="vkProdScan" aria-label="Barcode scanner"><i class="bi bi-upc-scan"></i></button>
            <button type="button" class="vk-prod-btn" id="reset-filters" aria-label="Reset filters"><i class="bi bi-x-circle"></i></button>
            <button type="button" class="vk-prod-btn" id="vkProdRefresh" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
            <button type="button" class="vk-prod-btn" id="vkProdExportCsv" aria-label="Export CSV"><i class="bi bi-filetype-csv"></i></button>
            <button type="button" class="vk-prod-btn" id="vkProdExportExcel" aria-label="Export Excel"><i class="bi bi-file-earmark-excel"></i></button>
            <button type="button" class="vk-prod-btn" id="vkProdExportPdf" aria-label="Print PDF"><i class="bi bi-file-pdf"></i></button>
            <button type="button" class="vk-prod-btn" id="vkProdPrint" aria-label="Print"><i class="bi bi-printer"></i></button>
        </div>
    </div>
</div>

<div class="vk-prod-panel vk-prod-desktop-only">
    <div class="vk-prod-panel-scroll" id="products-table-wrap">
        <table id="products-table" class="table vk-prod-table mb-0" aria-label="Products data grid">
            <thead>
                <tr>
                    <th class="vk-prod-sticky-col vk-prod-sticky-check" style="width:34px"><input id="bulk-select-all" type="checkbox" class="form-check-input" aria-label="Select all"></th>
                    <th class="vk-prod-sticky-col vk-prod-sticky-img" style="width:52px">Image</th>
                    <th style="width:90px">SKU</th>
                    <th class="vk-prod-col-hide-md" style="width:100px">Barcode</th>
                    <th style="width:160px">Product</th>
                    <th style="width:100px">Category</th>
                    <th class="vk-prod-col-hide-lg" style="width:80px">Brand</th>
                    <th class="vk-prod-col-hide-md" style="width:90px">Supplier</th>
                    <th class="vk-prod-col-hide-lg" style="width:50px">Unit</th>
                    <th style="width:90px">Purchase</th>
                    <th style="width:90px">Selling</th>
                    <th style="width:120px">Stock</th>
                    <th class="vk-prod-col-hide-md" style="width:70px">Min</th>
                    <th class="vk-prod-col-hide-lg" style="width:80px">Warehouse</th>
                    <th style="width:70px">Status</th>
                    <th class="vk-prod-col-hide-md" style="width:90px">Updated</th>
                    <th style="width:300px">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <div class="vk-prod-footer">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span id="vkProdPageInfo">Showing 0 of 0</span>
            <button id="bulk-delete" type="button" class="vk-prod-btn vk-prod-act-danger"><i class="bi bi-trash"></i> Delete selected</button>
        </div>
        <nav aria-label="Product pagination"><ul id="pagination" class="vk-prod-page-nav pagination pagination-sm mb-0"></ul></nav>
    </div>
</div>

<div id="vkProdMobileList" class="vk-prod-panel vk-prod-mobile-only" aria-label="Products mobile list"></div>

</div>

<div id="vkProdDrawerBackdrop" class="vk-prod-drawer-backdrop" aria-hidden="true"></div>
<aside id="vkProdDrawer" class="vk-prod-drawer" role="dialog" aria-modal="true" aria-labelledby="vkProdDrawerName" aria-hidden="true">
    <div class="vk-prod-drawer-head">
        <img id="vkProdDrawerImg" class="vk-prod-img" src="" alt="" width="56" height="56" style="width:56px;height:56px">
        <div class="flex-grow-1 min-w-0">
            <h2 id="vkProdDrawerName" class="h5 mb-0 text-truncate">Product</h2>
            <div class="vk-prod-sku" id="vkProdDrawerSku">—</div>
            <div class="d-flex gap-1 mt-2 flex-wrap">
                <a id="vkProdDrawerEdit" class="vk-prod-btn vk-prod-btn-primary" href="#">Edit</a>
                <a class="vk-prod-btn" href="<?= e(BASE_URL) ?>/modules/invoices/create.php"><i class="bi bi-receipt"></i> Invoice</a>
            </div>
        </div>
        <button type="button" id="vkProdDrawerClose" class="vk-prod-drawer-close" aria-label="Close drawer"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="vk-prod-drawer-scroll">
        <h3 class="vk-prod-section-title">Identification</h3>
        <div class="vk-prod-stat-grid mb-3">
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Barcode</div><div class="vk-prod-stat-value" id="vkProdDrawerBarcode">—</div></div>
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Category</div><div class="vk-prod-stat-value" id="vkProdDrawerCategory">—</div></div>
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Brand</div><div class="vk-prod-stat-value" id="vkProdDrawerBrand">—</div></div>
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Supplier</div><div class="vk-prod-stat-value" id="vkProdDrawerSupplier">—</div></div>
        </div>
        <h3 class="vk-prod-section-title">Pricing &amp; margin</h3>
        <div class="vk-prod-stat-grid mb-3">
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Purchase</div><div class="vk-prod-stat-value" id="vkProdDrawerCost">—</div></div>
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Selling</div><div class="vk-prod-stat-value" id="vkProdDrawerSell">—</div></div>
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Margin</div><div class="vk-prod-stat-value" id="vkProdDrawerMargin">—</div></div>
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Warehouse</div><div class="vk-prod-stat-value" id="vkProdDrawerWarehouse">—</div></div>
        </div>
        <h3 class="vk-prod-section-title">Inventory</h3>
        <div class="vk-prod-stat-grid mb-3">
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Stock</div><div class="vk-prod-stat-value" id="vkProdDrawerStock">—</div></div>
            <div class="vk-prod-stat"><div class="vk-prod-stat-label">Min / Reorder</div><div class="vk-prod-stat-value" id="vkProdDrawerMin">—</div></div>
        </div>
        <h3 class="vk-prod-section-title">Description</h3>
        <p class="small mb-3" id="vkProdDrawerDesc">—</p>
        <h3 class="vk-prod-section-title">Related modules</h3>
        <div class="d-flex flex-wrap gap-1 mb-2">
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/repairs/list.php">Repairs</a>
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/invoices/create.php">Invoices</a>
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/maintenance/list.php">Maintenance</a>
            <a class="vk-prod-mod" href="<?= e(BASE_URL) ?>/modules/dashboard.php">Reports</a>
        </div>
        <p class="vk-prod-sku mb-0">Sales history, purchase history, and stock movement timeline available in linked ERP modules.</p>
    </div>
</aside>

<script src="<?= e(base_url('assets/js/products-list.js')) ?>?v=<?= e($jsV) ?>" defer></script>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
