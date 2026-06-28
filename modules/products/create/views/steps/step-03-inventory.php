<?php
declare(strict_types=1);
$warehouses = $lookups['warehouses'] ?? [];
?>
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label" for="opening_stock">Opening Stock</label>
        <input type="number" min="0" class="form-control pc-calc" id="opening_stock" name="opening_stock" value="<?= e(ProductForm::value($form, 'opening_stock', '0')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="current_stock">Current Stock</label>
        <input type="number" min="0" class="form-control" id="current_stock" name="current_stock" value="<?= e(ProductForm::value($form, 'current_stock', '0')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="reorder_level">Reorder Level</label>
        <input type="number" min="0" class="form-control" id="reorder_level" name="reorder_level" value="<?= e(ProductForm::value($form, 'reorder_level', '0')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="minimum_stock">Minimum Stock</label>
        <input type="number" min="0" class="form-control" id="minimum_stock" name="minimum_stock" value="<?= e(ProductForm::value($form, 'minimum_stock', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="warehouse_id">Warehouse</label>
        <select class="form-select pc-searchable" id="warehouse_id" name="warehouse_id">
            <option value="">Select warehouse</option>
            <?php foreach ($warehouses as $row): ?>
            <option value="<?= (int) $row['id'] ?>" <?= ProductForm::selected($form, 'warehouse_id', (string) $row['id']) ?>><?= e((string) $row['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="rack_location">Rack</label>
        <input type="text" class="form-control" id="rack_location" name="rack_location" value="<?= e(ProductForm::value($form, 'rack_location')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="bin_number">Bin</label>
        <input type="text" class="form-control" id="bin_number" name="bin_number" value="<?= e(ProductForm::value($form, 'bin_number')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="batch_number">Batch</label>
        <input type="text" class="form-control" id="batch_number" name="batch_number" value="<?= e(ProductForm::value($form, 'batch_number')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="serial_number">Serial Number</label>
        <input type="text" class="form-control" id="serial_number" name="serial_number" value="<?= e(ProductForm::value($form, 'serial_number')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="expiry_date">Expiry</label>
        <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?= e(ProductForm::value($form, 'expiry_date')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="manufacturing_date">Manufacturing Date</label>
        <input type="date" class="form-control" id="manufacturing_date" name="manufacturing_date" value="<?= e(ProductForm::value($form, 'manufacturing_date')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="inventory_tracking">Inventory Tracking</label>
        <select class="form-select" id="inventory_tracking" name="inventory_tracking">
            <?php foreach (['none' => 'None', 'batch' => 'Batch', 'serial' => 'Serial'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ProductForm::selected($form, 'inventory_tracking', $val) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="multi_warehouse_support" name="multi_warehouse_support" value="1" <?= ProductForm::checked($form, 'multi_warehouse_support') ?>>
            <label class="form-check-label" for="multi_warehouse_support">Multi Warehouse</label>
        </div>
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="low_stock_alert" name="low_stock_alert" value="1" <?= ProductForm::checked($form, 'low_stock_alert', true) ?>>
            <label class="form-check-label" for="low_stock_alert">Stock Alerts</label>
        </div>
    </div>
    <div class="col-12">
        <div class="progress pc-stock-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="pcStockHealthBar">
            <div class="progress-bar" style="width:0%"></div>
        </div>
        <p class="small text-secondary mb-0 mt-1" id="pcStockHealthLabel">Inventory health will update as you enter stock levels.</p>
    </div>
</div>
