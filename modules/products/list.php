<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$pageTitle = 'Products';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/products.css')) . '">';
$extraScripts = '<script src="' . e(base_url('assets/js/products.js')) . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$cats = [];
try {
  $cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  // categories table missing or DB error — fallback to empty categories
  $cats = [];
}
?>
<div class="products-page container-fluid py-3">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-3">
    <div>
      <nav aria-label="breadcrumb" class="mb-2 small text-muted"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="<?= e(BASE_URL) ?>">Dashboard</a></li><li class="breadcrumb-item active" aria-current="page">Products</li></ol></nav>
      <h1 class="h3 mb-1">Parts &amp; Products</h1>
      <p class="text-muted small mb-0">Manage your inventory, stock, pricing and product catalog.</p>
    </div>
    <div class="ms-auto d-flex gap-2">
      <a href="<?= e(BASE_URL) ?>/modules/products/add.php" class="btn btn-primary btn-lg"><i class="bi bi-plus-lg me-1"></i> Add product</a>
      <button class="btn btn-outline-secondary">Import</button>
      <button class="btn btn-outline-secondary">Export</button>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-xl-9">
      <div class="row g-3">
        <div class="col-12 col-sm-6 col-md-3">
          <div class="card vk-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="vk-stat-icon bg-gradient-primary"><i class="bi bi-box-seam"></i></div>
              <div>
                <div class="small text-muted">Total Products</div>
                <div class="h5 mb-0" id="stat-total">—</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <div class="card vk-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="vk-stat-icon bg-gradient-warning"><i class="bi bi-exclamation-triangle"></i></div>
              <div>
                <div class="small text-muted">Low Stock</div>
                <div class="h5 mb-0" id="stat-low">—</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <div class="card vk-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="vk-stat-icon bg-gradient-danger"><i class="bi bi-dash-circle"></i></div>
              <div>
                <div class="small text-muted">Out of Stock</div>
                <div class="h5 mb-0" id="stat-out">—</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
          <div class="card vk-stat-card">
            <div class="card-body d-flex align-items-center gap-3">
              <div class="vk-stat-icon bg-gradient-info"><i class="bi bi-currency-dollar"></i></div>
              <div>
                <div class="small text-muted">Inventory Value</div>
                <div class="h5 mb-0" id="stat-value">—</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card glass-card mt-3">
        <div class="card-body">
          <div class="filter-bar d-flex flex-wrap gap-2 align-items-center mb-3">
            <div class="flex-fill">
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input id="product-search" class="form-control" placeholder="Search name, SKU, barcode...">
              </div>
            </div>
            <div class="w-auto">
              <select id="filter-category" class="form-select">
                <option value="">All categories</option>
                <?php foreach ($cats as $c): ?>
                  <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="w-auto">
              <select id="filter-status" class="form-select">
                <option value="">All status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="w-auto ms-auto">
              <button id="reset-filters" class="btn btn-link small">Reset</button>
            </div>
          </div>

          <div class="table-responsive modern-table" id="products-table-wrap">
            <table id="products-table" class="table table-hover align-middle">
              <thead class="table-dark sticky-top">
                <tr>
                  <th class="w-1"><input id="bulk-select-all" type="checkbox" aria-label="Select all"></th>
                  <th>Image</th>
                  <th>Product</th>
                  <th>SKU</th>
                  <th>Category</th>
                  <th>Brand</th>
                  <th class="text-end">Price</th>
                  <th class="text-end">Stock</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
              <button id="bulk-delete" class="btn btn-sm btn-outline-danger">Delete Selected</button>
            </div>
            <nav><ul id="pagination" class="pagination pagination-sm mb-0"></ul></nav>
          </div>
        </div>
      </div>

    </div>
    <div class="col-12 col-xl-3">
      <div class="card glass-card sticky-top" style="top:88px">
        <div class="card-body">
          <h6 class="mb-2">Quick Actions</h6>
          <div class="d-grid gap-2">
            <button class="btn btn-outline-secondary">Import Products</button>
            <button class="btn btn-outline-secondary">Export CSV / Excel</button>
            <button class="btn btn-outline-secondary">Bulk Stock Update</button>
          </div>
          <hr>
          <h6 class="mb-2">Analytics</h6>
          <div class="small text-muted">Stock value, turnover and trending products will appear here.</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
