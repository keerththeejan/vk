<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="sku">Product Code <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="sku" name="sku" required maxlength="128" value="<?= e(ProductForm::value($form, 'sku')) ?>" autocomplete="off" placeholder="e.g. VK-NVR-001">
        <div class="form-text" id="pcSkuStatus" aria-live="polite"></div>
    </div>
    <div class="col-md-8">
        <label class="form-label" for="name">Product Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="name" name="name" required maxlength="255" value="<?= e(ProductForm::value($form, 'name')) ?>" autocomplete="off" placeholder="Enter product name">
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Model, specification, or product details"><?= e(ProductForm::value($form, 'description')) ?></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="serial_number">Serial Number <span class="text-secondary">(optional)</span></label>
        <input type="text" class="form-control" id="serial_number" name="serial_number" maxlength="191" value="<?= e(ProductForm::value($form, 'serial_number')) ?>" placeholder="Enter serial number">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="warranty_period">Warranty Period (Days)</label>
        <input type="number" min="0" max="36500" step="1" class="form-control" id="warranty_period" name="warranty_period" value="<?= e(ProductForm::value($form, 'warranty_period', '0')) ?>">
    </div>
</div>
