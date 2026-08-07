<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$product = null;
try {
    $pdo = db();
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $categories = $pdo->query('SELECT id,name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $brands = $pdo->query('SELECT id,name FROM brands ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $suppliers = $pdo->query('SELECT id,name FROM suppliers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $warehouses = $pdo->query('SELECT id,name FROM warehouses ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">DB error: '.htmlspecialchars($e->getMessage()).'</div>';
}
?>

<link rel="stylesheet" href="/vk/assets/css/products.css">

<div class="container py-4">
  <div class="card glass-card">
    <div class="card-body">
      <h3><?php echo $product ? 'Edit Product' : 'Add Product'; ?></h3>
      <form id="product-form">
        <input type="hidden" name="id" value="<?php echo $product['id'] ?? ''; ?>">
        <div class="row">
          <div class="col-md-8">
            <div class="mb-3">
              <label class="form-label">Product Name</label>
              <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>
            </div>
            <div class="mb-3 d-flex gap-2">
              <input type="text" name="sku" id="sku" class="form-control" placeholder="SKU (auto)" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>">
              <button id="gen-sku" type="button" class="btn btn-outline-secondary">Generate SKU</button>
            </div>
            <div class="mb-3">
              <label class="form-label">Short Description</label>
              <input type="text" name="short_description" class="form-control" value="<?php echo htmlspecialchars($product['short_description'] ?? ''); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="6"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
            </div>
          </div>

          <div class="col-md-4">
            <div class="mb-3">
              <label class="form-label">Category</label>
              <select name="category_id" class="form-select">
                <option value="">-- Select --</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>" <?php if (($product['category_id'] ?? '') == $c['id']) echo 'selected'; ?>><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Brand</label>
              <select name="brand_id" class="form-select">
                <option value="">-- Select --</option>
                <?php foreach ($brands as $b): ?>
                  <option value="<?= $b['id'] ?>" <?php if (($product['brand_id'] ?? '') == $b['id']) echo 'selected'; ?>><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Supplier</label>
              <select name="supplier_id" class="form-select">
                <option value="">-- Select --</option>
                <?php foreach ($suppliers as $s): ?>
                  <option value="<?= $s['id'] ?>" <?php if (($product['supplier_id'] ?? '') == $s['id']) echo 'selected'; ?>><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Cost Price</label>
              <input type="number" step="0.01" name="cost_price" class="form-control" value="<?php echo htmlspecialchars($product['cost_price'] ?? 0); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Selling Price</label>
              <input type="number" step="0.01" name="selling_price" class="form-control" value="<?php echo htmlspecialchars($product['selling_price'] ?? 0); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Opening Stock</label>
              <input type="number" name="opening_stock" class="form-control" value="<?php echo htmlspecialchars($product['opening_stock'] ?? 0); ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active" <?php if (($product['status'] ?? '')=='active') echo 'selected'; ?>>Active</option>
                <option value="inactive" <?php if (($product['status'] ?? '')=='inactive') echo 'selected'; ?>>Inactive</option>
                <option value="archived" <?php if (($product['status'] ?? '')=='archived') echo 'selected'; ?>>Archived</option>
              </select>
            </div>
          </div>
        </div>

        <div class="mt-3">
          <button class="btn btn-primary" type="submit">Save Product</button>
          <a href="/vk/modules/products/list.php" class="btn btn-link">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="/vk/assets/js/product_form.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
