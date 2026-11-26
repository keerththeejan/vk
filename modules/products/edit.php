<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once __DIR__ . '/create/Support/ProductSchema.php';

$id = max(0, (int) ($_GET['id'] ?? 0));
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    flash_set('error', 'Product not found.');
    redirect('/modules/products/list.php');
}

$columns = ProductSchema::columns($pdo, 'products');
$has = static fn(string $name): bool => in_array($name, $columns, true);
$serialNumber = '';
if (ProductSchema::tableExists($pdo, 'product_inventory')) {
    $inventoryColumns = ProductSchema::columns($pdo, 'product_inventory');
    if (in_array('serial_number', $inventoryColumns, true)) {
        $serialStmt = $pdo->prepare('SELECT serial_number FROM product_inventory WHERE product_id = ? LIMIT 1');
        $serialStmt->execute([$id]);
        $serialNumber = (string) ($serialStmt->fetchColumn() ?: '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_verify(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null)) {
            throw new RuntimeException('Your session expired. Refresh and try again.');
        }
        $name = trim((string) ($_POST['name'] ?? ''));
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $purchase = $_POST['cost_price'] ?? null;
        $selling = $_POST['selling_price'] ?? null;
        $currentStock = $_POST['current_stock'] ?? null;
        $minimumStock = $_POST['minimum_stock'] ?? 0;
        $reorderLevel = $_POST['reorder_level'] ?? 0;
        $warrantyDays = $_POST['warranty_period'] ?? 0;
        if ($name === '') throw new RuntimeException('Product name is required.');
        if ($has('sku')) {
            if ($sku === '') throw new RuntimeException('Product code is required.');
            $dupe = $pdo->prepare('SELECT id FROM products WHERE sku = ? AND id <> ? LIMIT 1');
            $dupe->execute([$sku, $id]);
            if ($dupe->fetchColumn()) throw new RuntimeException('That product code is already in use.');
        }
        foreach (['purchase price' => $purchase, 'selling price' => $selling] as $label => $value) {
            if (!is_numeric($value) || (float) $value < 0) throw new RuntimeException('Enter a valid non-negative ' . $label . '.');
        }
        foreach (['current stock' => $currentStock, 'minimum stock' => $minimumStock, 'reorder level' => $reorderLevel, 'warranty period' => $warrantyDays] as $label => $value) {
            if (!is_numeric($value) || (float) $value < 0 || (float) $value !== (float) (int) $value) throw new RuntimeException('Enter a valid whole number for ' . $label . '.');
        }

        $updates = [
            'sku' => $sku, 'name' => $name,
            'description' => $description !== '' ? $description : null,
            'cost_price' => (float) $purchase, 'selling_price' => (float) $selling, 'price' => (float) $selling,
            'opening_stock' => (int) ($_POST['opening_stock'] ?? $currentStock),
            'stock' => (int) $currentStock, 'current_stock' => (int) $currentStock,
            'min_stock' => (int) $minimumStock, 'low_stock_threshold' => (int) $minimumStock,
            'reorder_level' => (int) $reorderLevel,
            'warranty_enabled' => (int) $warrantyDays > 0 ? 1 : 0,
            'warranty_period' => (int) $warrantyDays, 'warranty_duration_days' => (int) $warrantyDays,
            'serial_number' => trim((string) ($_POST['serial_number'] ?? '')) ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $updates = array_intersect_key($updates, array_flip($columns));
        if (!$updates) throw new RuntimeException('The product table has no supported fields to update.');
        $set = implode(', ', array_map(static fn(string $column): string => $column . ' = ?', array_keys($updates)));

        $pdo->beginTransaction();
        $update = $pdo->prepare('UPDATE products SET ' . $set . ' WHERE id = ?');
        $update->execute([...array_values($updates), $id]);

        $serial = trim((string) ($_POST['serial_number'] ?? ''));
        if (ProductSchema::tableExists($pdo, 'product_inventory')) {
            $inventoryColumns = ProductSchema::columns($pdo, 'product_inventory');
            if (in_array('serial_number', $inventoryColumns, true)) {
                $invStmt = $pdo->prepare('SELECT product_id FROM product_inventory WHERE product_id = ? LIMIT 1');
                $invStmt->execute([$id]);
                if ($invStmt->fetchColumn()) {
                    $pdo->prepare('UPDATE product_inventory SET serial_number = ? WHERE product_id = ?')->execute([$serial ?: null, $id]);
                } else {
                    ProductSchema::insertFiltered($pdo, 'product_inventory', ['product_id' => $id, 'current_stock' => (int) $currentStock, 'minimum_stock' => (int) $minimumStock, 'reorder_level' => (int) $reorderLevel, 'serial_number' => $serial ?: null, 'created_at' => date('Y-m-d H:i:s')]);
                }
            }
        }

        if (!empty($_FILES['product_image']['tmp_name']) && is_uploaded_file($_FILES['product_image']['tmp_name'])) {
            if ((int) ($_FILES['product_image']['size'] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('Product images must be 10 MB or smaller.');
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string) $_FILES['product_image']['tmp_name']);
            $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!isset($extensions[$mime])) throw new RuntimeException('Use a JPG, PNG, WebP, or GIF product image.');
            $dir = dirname(__DIR__, 2) . '/uploads/products/' . $id;
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Unable to create the product image folder.');
            $imagePath = 'uploads/products/' . $id . '/' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
            if (!move_uploaded_file((string) $_FILES['product_image']['tmp_name'], dirname(__DIR__, 2) . '/' . $imagePath)) throw new RuntimeException('Unable to save the product image.');
            if (ProductSchema::tableExists($pdo, 'product_images')) {
                ProductSchema::insertFiltered($pdo, 'product_images', ['product_id' => $id, 'path' => $imagePath, 'is_thumbnail' => 1, 'sort_order' => 0, 'created_at' => date('Y-m-d H:i:s')]);
            } elseif ($has('image_path')) {
                $pdo->prepare('UPDATE products SET image_path = ? WHERE id = ?')->execute([$imagePath, $id]);
            }
        }

        $pdo->commit();
        flash_set('success', 'Product updated.');
        redirect('/modules/products/list.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('error', $e->getMessage());
        redirect('/modules/products/edit.php?id=' . $id);
    }
}

$st = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC) ?: $row;
$pageTitle = 'Edit product';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/product-create/product-create.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="pc-root pc-simple">
    <header class="pc-toolbar card border-0 shadow-sm mb-3">
        <div class="card-body py-3 d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div><h1 class="pc-title h4 mb-1">Edit Product</h1><p class="small text-secondary mb-0">Update inventory and pricing details</p></div>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('modules/products/list.php')) ?>">Back to Products</a>
        </div>
    </header>
    <form class="pc-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="pc-simple-card">
            <section class="pc-simple-section"><h2>Basic Information</h2><div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="sku">Product Code</label><input class="form-control" id="sku" name="sku" maxlength="128" value="<?= e((string) ($row['sku'] ?? '')) ?>" <?= $has('sku') ? 'required' : 'readonly' ?>></div>
                <div class="col-md-8"><label class="form-label" for="name">Product Name <span class="text-danger">*</span></label><input class="form-control" id="name" name="name" required maxlength="255" value="<?= e((string) ($row['name'] ?? '')) ?>"></div>
                <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3"><?= e((string) ($row['description'] ?? $row['short_description'] ?? '')) ?></textarea></div>
                <div class="col-md-6"><label class="form-label" for="serial_number">Serial Number</label><input class="form-control" id="serial_number" name="serial_number" value="<?= e($serialNumber) ?>"></div>
                <div class="col-md-6"><label class="form-label" for="warranty_period">Warranty Period (Days)</label><input class="form-control" type="number" id="warranty_period" name="warranty_period" min="0" max="36500" step="1" value="<?= e((string) ($row['warranty_duration_days'] ?? $row['warranty_period'] ?? 0)) ?>"></div>
            </div></section>
            <section class="pc-simple-section"><h2>Pricing</h2><div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="cost_price">Purchase Price</label><input class="form-control" type="number" id="cost_price" name="cost_price" min="0" step="0.01" required value="<?= e((string) ($row['cost_price'] ?? 0)) ?>"></div>
                <div class="col-md-6"><label class="form-label" for="selling_price">Selling Price</label><input class="form-control" type="number" id="selling_price" name="selling_price" min="0" step="0.01" required value="<?= e((string) ($row['selling_price'] ?? $row['price'] ?? 0)) ?>"></div>
            </div></section>
            <section class="pc-simple-section"><h2>Inventory</h2><div class="row g-3">
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="opening_stock">Opening Stock</label><input class="form-control" type="number" id="opening_stock" name="opening_stock" min="0" step="1" value="<?= e((string) ($row['opening_stock'] ?? $row['stock'] ?? 0)) ?>"></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="current_stock">Current Stock</label><input class="form-control" type="number" id="current_stock" name="current_stock" min="0" step="1" required value="<?= e((string) ($row['stock'] ?? $row['current_stock'] ?? $row['opening_stock'] ?? 0)) ?>"></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="minimum_stock">Minimum Stock</label><input class="form-control" type="number" id="minimum_stock" name="minimum_stock" min="0" step="1" value="<?= e((string) ($row['min_stock'] ?? $row['low_stock_threshold'] ?? 0)) ?>"></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="reorder_level">Reorder Level</label><input class="form-control" type="number" id="reorder_level" name="reorder_level" min="0" step="1" value="<?= e((string) ($row['reorder_level'] ?? 0)) ?>"></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label" for="product_image">Product Image</label><input class="form-control" type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/webp,image/gif"></div>
            </div></section>
        </div>
        <div class="pc-simple-actions"><a class="btn btn-outline-secondary" href="<?= e(base_url('modules/products/list.php')) ?>">Cancel</a><button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Save Product</button></div>
    </form>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
