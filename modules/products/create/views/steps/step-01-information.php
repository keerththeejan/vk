<?php
declare(strict_types=1);
$brands = $lookups['brands'] ?? [];
$categories = $lookups['categories'] ?? [];
$suppliers = $lookups['suppliers'] ?? [];
$manufacturers = $lookups['manufacturers'] ?? [];
?>
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="name">Product Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" required maxlength="255" value="<?= e(ProductForm::value($form, 'name')) ?>" autocomplete="off">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="sku">SKU</label>
        <div class="input-group">
            <input type="text" class="form-control" id="sku" name="sku" value="<?= e(ProductForm::value($form, 'sku')) ?>" autocomplete="off">
            <button type="button" class="btn btn-outline-secondary" id="pcGenSku" title="Auto generate SKU">Auto</button>
        </div>
        <div class="form-text" id="pcSkuStatus"></div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="barcode">Barcode</label>
        <input type="text" class="form-control" id="barcode" name="barcode" value="<?= e(ProductForm::value($form, 'barcode')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="qr_code">QR Code</label>
        <input type="text" class="form-control" id="qr_code" name="qr_code" value="<?= e(ProductForm::value($form, 'qr_code')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="product_type">Product Type</label>
        <select class="form-select pc-searchable" id="product_type" name="product_type">
            <?php foreach (['simple' => 'Simple', 'variable' => 'Variable', 'digital' => 'Digital', 'bundle' => 'Bundle'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ProductForm::selected($form, 'product_type', $val) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="brand_id">Brand</label>
        <select class="form-select pc-searchable" id="brand_id" name="brand_id">
            <option value="">Select brand</option>
            <?php foreach ($brands as $row): ?>
            <option value="<?= (int) $row['id'] ?>" <?= ProductForm::selected($form, 'brand_id', (string) $row['id']) ?>><?= e((string) $row['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="category_id">Category</label>
        <select class="form-select pc-searchable" id="category_id" name="category_id" data-subtarget="subcategory_id">
            <option value="">Select category</option>
            <?php foreach ($categories as $row): ?>
            <?php if ((int) ($row['parent_id'] ?? 0) === 0): ?>
            <option value="<?= (int) $row['id'] ?>" <?= ProductForm::selected($form, 'category_id', (string) $row['id']) ?>><?= e((string) $row['name']) ?></option>
            <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="subcategory_id">Subcategory</label>
        <select class="form-select pc-searchable" id="subcategory_id" name="subcategory_id">
            <option value="">Select subcategory</option>
            <?php foreach ($categories as $row): ?>
            <?php if ((int) ($row['parent_id'] ?? 0) > 0): ?>
            <option value="<?= (int) $row['id'] ?>" data-parent="<?= (int) $row['parent_id'] ?>" <?= ProductForm::selected($form, 'subcategory_id', (string) $row['id']) ?>><?= e((string) $row['name']) ?></option>
            <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="supplier_id">Supplier</label>
        <select class="form-select pc-searchable" id="supplier_id" name="supplier_id">
            <option value="">Select supplier</option>
            <?php foreach ($suppliers as $row): ?>
            <option value="<?= (int) $row['id'] ?>" <?= ProductForm::selected($form, 'supplier_id', (string) $row['id']) ?>><?= e((string) $row['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="manufacturer_id">Manufacturer</label>
        <select class="form-select pc-searchable" id="manufacturer_id" name="manufacturer_id">
            <option value="">Select manufacturer</option>
            <?php foreach ($manufacturers as $row): ?>
            <option value="<?= (int) $row['id'] ?>" <?= ProductForm::selected($form, 'manufacturer_id', (string) $row['id']) ?>><?= e((string) $row['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="unit_type">Unit</label>
        <select class="form-select" id="unit_type" name="unit_type">
            <?php foreach (['piece' => 'Piece', 'kg' => 'Kilogram', 'liter' => 'Liter', 'box' => 'Box', 'pack' => 'Pack'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ProductForm::selected($form, 'unit_type', $val) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="country_of_origin">Country</label>
        <input type="text" class="form-control" id="country_of_origin" name="country_of_origin" value="<?= e(ProductForm::value($form, 'country_of_origin')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="product_tags">Tags</label>
        <input type="text" class="form-control" id="product_tags" name="product_tags" placeholder="Comma separated" value="<?= e(ProductForm::value($form, 'product_tags')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="short_description">Short Description</label>
        <textarea class="form-control" id="short_description" name="short_description" rows="2" maxlength="500"><?= e(ProductForm::value($form, 'short_description')) ?></textarea>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Full Description</label>
        <textarea class="form-control pc-rich" id="description" name="description" rows="6"><?= e(ProductForm::value($form, 'description')) ?></textarea>
    </div>
</div>
