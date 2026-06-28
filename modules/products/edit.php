<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
        flash_set('error', 'Product not found.');
        redirect('/modules/products/list.php');
}

// load warranty if exists
$warranty = $pdo->prepare('SELECT * FROM product_warranties WHERE product_id = ? LIMIT 1');
$warranty->execute([$id]);
$wrow = $warranty->fetch() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $price = (float) ($_POST['price'] ?? 0);
        $stock = (int) ($_POST['stock'] ?? 0);
        $lowTh = max(0, (int) ($_POST['low_stock_threshold'] ?? 5));
        $category = trim((string) ($_POST['category'] ?? ''));

        // warranty inputs
        $w_enabled = isset($_POST['warranty_enabled']) ? 1 : 0;
        $w_type = trim((string) ($_POST['warranty_type'] ?? ''));
        $w_period = (int) ($_POST['warranty_period'] ?? 0);
        $w_start = trim((string) ($_POST['warranty_start_date'] ?? '')) ?: null;

        try {
                if ($name === '') throw new RuntimeException('Name is required.');
                if ($price < 0 || $stock < 0) throw new RuntimeException('Price and stock must be zero or positive.');

                $pdo->beginTransaction();
                $u = $pdo->prepare('UPDATE products SET name=?, price=?, stock=?, low_stock_threshold=?, category=?, has_warranty=? WHERE id=?');
                $u->execute([$name, $price, $stock, $lowTh, $category ?: null, $w_enabled, $id]);

                // handle warranty upload
                $docPath = $wrow['warranty_document'] ?? null;
                if (!empty($_FILES['warranty_document']['name'])) {
                        $up = $_FILES['warranty_document'];
                        $allowed = ['application/pdf','image/jpeg','image/png'];
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $up['tmp_name']);
                        finfo_close($finfo);
                        if (!in_array($mime, $allowed, true) || $up['size'] > 5 * 1024 * 1024) {
                                throw new RuntimeException('Invalid warranty document upload.');
                        }
                        $ext = pathinfo($up['name'], PATHINFO_EXTENSION);
                        $dir = __DIR__ . '/../../uploads/warranties';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $fn = uniqid('wr_') . '.' . $ext;
                        $dest = $dir . '/' . $fn;
                        if (!move_uploaded_file($up['tmp_name'], $dest)) {
                                throw new RuntimeException('Failed to save warranty document.');
                        }
                        $docPath = 'uploads/warranties/' . $fn;
                }

                $expiry = null;
                if ($w_start && $w_period > 0) {
                        $d = new DateTime($w_start);
                        $d->modify('+' . $w_period . ' months');
                        $expiry = $d->format('Y-m-d');
                }

                if ($w_enabled) {
                        if ($wrow) {
                                $upw = $pdo->prepare('UPDATE product_warranties SET warranty_enabled=1, warranty_type=?, warranty_period=?, warranty_period_type=?, warranty_start_date=?, warranty_expiry_date=?, warranty_coverage=?, warranty_terms=?, warranty_claim_process=?, warranty_document=?, warranty_status=? WHERE product_id=?');
                                $upw->execute([$w_type, $w_period, 'months', $w_start, $expiry, $_POST['warranty_coverage'] ?? '', $_POST['warranty_terms'] ?? '', $_POST['warranty_claim_process'] ?? '', $docPath, $expiry && (new DateTime($expiry) > new DateTime()) ? 'Active' : 'Pending', $id]);
                        } else {
                                $ins = $pdo->prepare('INSERT INTO product_warranties (product_id, warranty_enabled, warranty_type, warranty_period, warranty_period_type, warranty_start_date, warranty_expiry_date, warranty_coverage, warranty_terms, warranty_claim_process, warranty_document, warranty_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                                $ins->execute([$id, 1, $w_type, $w_period, 'months', $w_start, $expiry, $_POST['warranty_coverage'] ?? '', $_POST['warranty_terms'] ?? '', $_POST['warranty_claim_process'] ?? '', $docPath, $expiry && (new DateTime($expiry) > new DateTime()) ? 'Active' : 'Pending']);
                        }
                } else {
                        // remove warranty if exists
                        if ($wrow) {
                                $del = $pdo->prepare('DELETE FROM product_warranties WHERE product_id = ?');
                                $del->execute([$id]);
                        }
                }

                $pdo->commit();
                flash_set('success', 'Product updated.');
                redirect('/modules/products/list.php');
        } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash_set('error', $e->getMessage());
        }
}

$pageTitle = 'Edit product';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/product-admin.css')) . '">';
$extraScripts = '<script src="' . e(base_url('assets/js/product-admin.js')) . '"></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
        <a href="<?= e(BASE_URL) ?>/modules/products/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Edit product</h1>
<form method="post" enctype="multipart/form-data" id="product-form">
<div class="row">
    <div class="col-lg-8">
        <div class="card vk-card mb-3">
            <div class="card-body">
                <h5 class="mb-3">Basic Product Information</h5>
                <div class="mb-3">
                        <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                        <input class="form-control" id="name" name="name" required maxlength="255" value="<?= e($_POST['name'] ?? $row['name']) ?>">
                </div>
                <div class="row g-3">
                        <div class="col-md-6">
                                <label class="form-label" for="price">Price</label>
                                <input class="form-control" type="number" step="0.01" min="0" id="price" name="price" required value="<?= e((string) ($_POST['price'] ?? $row['price'])) ?>">
                        </div>
                        <div class="col-md-6">
                                <label class="form-label" for="stock">Stock</label>
                                <input class="form-control" type="number" min="0" id="stock" name="stock" required value="<?= e((string) ($_POST['stock'] ?? $row['stock'])) ?>">
                        </div>
                </div>
                <div class="mb-3 mt-3">
                                <label class="form-label" for="low_stock_threshold">Low stock alert at or below</label>
                                <input class="form-control" type="number" min="0" id="low_stock_threshold" name="low_stock_threshold" value="<?= e((string) ($_POST['low_stock_threshold'] ?? ($row['low_stock_threshold'] ?? 5))) ?>">
                </div>
                <div class="mb-3">
                                <label class="form-label" for="category">Category</label>
                                <input class="form-control" id="category" name="category" maxlength="128" value="<?= e($_POST['category'] ?? ($row['category'] ?? '')) ?>">
                </div>
            </div>
        </div>

        <!-- Inventory section placeholder -->
        <div class="card vk-card mb-3">
            <div class="card-body">
                <h5 class="mb-3">Inventory Management</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Warehouse</label>
                        <select class="form-select" name="warehouse_id">
                            <option value="">Default Warehouse</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Reorder Level</label>
                        <input class="form-control" name="reorder_level" type="number" min="0" value="<?= e($row['reorder_level'] ?? 0) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Warranty Management -->
        <div class="card vk-card mb-3 warranty-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">Warranty Management</h5>
                        <div class="small text-muted">Configure warranty details and documents for this product.</div>
                    </div>
                    <div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="warranty-toggle" name="warranty_enabled" value="1" <?= $wrow && $wrow['warranty_enabled'] ? 'checked' : '' ?>>
                        </div>
                    </div>
                </div>

                <div id="warranty-fields" class="mt-3 <?= $wrow && $wrow['warranty_enabled'] ? '' : 'd-none' ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Warranty Type</label>
                            <select class="form-select" name="warranty_type">
                                <?php $types = ['Manufacturer Warranty','Seller Warranty','Replacement Warranty','Service Warranty','Extended Warranty']; foreach ($types as $t): ?>
                                    <option <?= ($wrow && $wrow['warranty_type'] === $t) ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Period (months)</label>
                            <input class="form-control" type="number" name="warranty_period" id="warranty-period" min="0" value="<?= e($wrow['warranty_period'] ?? 12) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input class="form-control" type="date" name="warranty_start_date" id="warranty-start" value="<?= e($wrow['warranty_start_date'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row g-3 mt-3">
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input class="form-control" type="date" id="warranty-expiry" readonly value="<?= e($wrow['warranty_expiry_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Warranty Status</label>
                            <select class="form-select" name="warranty_status">
                                <option <?= ($wrow && $wrow['warranty_status'] === 'Pending') ? 'selected' : '' ?>>Pending</option>
                                <option <?= ($wrow && $wrow['warranty_status'] === 'Active') ? 'selected' : '' ?>>Active</option>
                                <option <?= ($wrow && $wrow['warranty_status'] === 'Expired') ? 'selected' : '' ?>>Expired</option>
                                <option <?= ($wrow && $wrow['warranty_status'] === 'Lifetime') ? 'selected' : '' ?>>Lifetime</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Warranty Coverage</label>
                        <textarea class="form-control" name="warranty_coverage" rows="3"><?= e($wrow['warranty_coverage'] ?? '') ?></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Warranty Terms &amp; Conditions</label>
                        <textarea class="form-control" name="warranty_terms" rows="5"><?= e($wrow['warranty_terms'] ?? '') ?></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Warranty Claim Process</label>
                        <textarea class="form-control" name="warranty_claim_process" rows="3"><?= e($wrow['warranty_claim_process'] ?? '') ?></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Upload Warranty Document (PDF, JPG, PNG)</label>
                        <input class="form-control" type="file" name="warranty_document" accept="application/pdf,image/*">
                        <?php if (!empty($wrow['warranty_document'])): ?>
                            <div class="mt-2 small">Current document: <a href="<?= e(base_url($wrow['warranty_document'])) ?>" target="_blank">View</a></div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3">
                        <button type="button" id="generate-warranty-card" class="btn btn-outline-primary">Generate Warranty Card</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="col-lg-4">
        <div class="card vk-card sticky-top" style="top:100px">
            <div class="card-body d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg" id="save-product">Save product</button>
                <a class="btn btn-link" href="<?= e(BASE_URL) ?>/modules/products/list.php">Cancel</a>
            </div>
        </div>
    </div>
</div>
</form>

<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
