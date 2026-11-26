<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="opening_stock">Opening Stock</label>
        <input type="number" min="0" step="1" class="form-control" id="opening_stock" name="opening_stock" value="<?= e(ProductForm::value($form, 'opening_stock', '0')) ?>">
    </div>
    <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="current_stock">Current Stock</label>
        <input type="number" min="0" step="1" class="form-control" id="current_stock" name="current_stock" value="<?= e(ProductForm::value($form, 'current_stock') !== '' ? ProductForm::value($form, 'current_stock') : ProductForm::value($form, 'opening_stock', '0')) ?>">
    </div>
    <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="minimum_stock">Minimum Stock</label>
        <input type="number" min="0" step="1" class="form-control" id="minimum_stock" name="minimum_stock" value="<?= e(ProductForm::value($form, 'minimum_stock', '0')) ?>">
    </div>
    <div class="col-sm-6 col-lg-3">
        <label class="form-label" for="reorder_level">Reorder Level</label>
        <input type="number" min="0" step="1" class="form-control" id="reorder_level" name="reorder_level" value="<?= e(ProductForm::value($form, 'reorder_level', '0')) ?>">
    </div>
</div>
