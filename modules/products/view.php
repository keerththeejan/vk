<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo '<div class="container py-4"><div class="alert alert-danger">Missing product id</div></div>'; require __DIR__ . '/../../includes/footer.php'; exit; }

try {
  $pdo = db();
  $stmt = $pdo->prepare('SELECT p.*, c.name as category_name, b.name as brand_name FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id WHERE p.id=:id');
  $stmt->execute([':id'=>$id]);
  $p = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$p) { echo '<div class="container py-4"><div class="alert alert-warning">Product not found</div></div>'; require __DIR__ . '/../../includes/footer.php'; exit; }
} catch (Exception $e) { echo '<div class="container py-4"><div class="alert alert-danger">DB error</div></div>'; require __DIR__ . '/../../includes/footer.php'; exit; }
?>

<link rel="stylesheet" href="/vk/assets/css/products.css">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?php echo htmlspecialchars($p['name']); ?></h2>
    <div>
      <a href="/vk/modules/products/form.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-light">Edit</a>
      <button id="btn-delete" class="btn btn-sm btn-danger">Delete</button>
    </div>
  </div>

  <div class="row">
    <div class="col-md-8">
      <div class="card glass-card mb-3">
        <div class="card-body">
          <p><strong>SKU:</strong> <?php echo htmlspecialchars($p['sku']); ?></p>
          <p><strong>Barcode:</strong> <?php echo htmlspecialchars($p['barcode']); ?></p>
          <p><strong>Category:</strong> <?php echo htmlspecialchars($p['category_name']); ?></p>
          <p><strong>Brand:</strong> <?php echo htmlspecialchars($p['brand_name']); ?></p>
          <p><strong>Cost:</strong> $<?php echo number_format($p['cost_price'],2); ?></p>
          <p><strong>Price:</strong> $<?php echo number_format($p['selling_price'],2); ?></p>
          <p><strong>Opening Stock:</strong> <?php echo (int)$p['opening_stock']; ?></p>
          <hr>
          <h5>Description</h5>
          <div><?php echo nl2br(htmlspecialchars($p['description'])); ?></div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card glass-card mb-3">
        <div class="card-body">
          <h6>Inventory</h6>
          <p>Real-time stock and movements coming soon.</p>
        </div>
      </div>
      <div class="card glass-card">
        <div class="card-body">
          <h6>Warranty</h6>
          <p>Warranty module integration coming soon.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('btn-delete').addEventListener('click', function(){
  if (!confirm('Delete this product?')) return;
  fetch('/vk/api/product_delete.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'id='+encodeURIComponent(<?php echo $p['id']; ?>)})
    .then(r=>r.json()).then(j=>{ if (j.success) window.location.href='/vk/modules/products/list.php'; else alert(j.error||'Error'); });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
