<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="cost_price">Purchase Price <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" class="form-control" id="cost_price" name="cost_price" required inputmode="decimal" value="<?= e(ProductForm::value($form, 'cost_price', '0')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="selling_price">Selling Price <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" class="form-control" id="selling_price" name="selling_price" required inputmode="decimal" value="<?= e(ProductForm::value($form, 'selling_price', '0')) ?>">
    </div>
</div>
