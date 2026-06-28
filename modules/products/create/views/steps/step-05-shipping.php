<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label" for="shipping_weight">Weight (kg)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="shipping_weight" name="shipping_weight" value="<?= e(ProductForm::value($form, 'shipping_weight', '0')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="shipping_length">Length (cm)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="shipping_length" name="shipping_length" value="<?= e(ProductForm::value($form, 'shipping_length', '0')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="shipping_width">Width (cm)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="shipping_width" name="shipping_width" value="<?= e(ProductForm::value($form, 'shipping_width', '0')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="shipping_height">Height (cm)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="shipping_height" name="shipping_height" value="<?= e(ProductForm::value($form, 'shipping_height', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="shipping_class">Shipping Class</label>
        <select class="form-select" id="shipping_class" name="shipping_class">
            <?php foreach (['standard' => 'Standard', 'express' => 'Express', 'freight' => 'Freight', 'oversized' => 'Oversized'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ProductForm::selected($form, 'shipping_class', $val) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="packaging_type">Packaging</label>
        <input type="text" class="form-control" id="packaging_type" name="packaging_type" value="<?= e(ProductForm::value($form, 'packaging_type')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="delivery_sla">Delivery SLA</label>
        <select class="form-select" id="delivery_sla" name="delivery_sla">
            <?php foreach (['1-2 days', '3-5 days', '5-7 days', '7-14 days'] as $sla): ?>
            <option value="<?= e($sla) ?>" <?= ProductForm::selected($form, 'delivery_sla', $sla) ?>><?= e($sla) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="return_window">Return Policy (days)</label>
        <input type="number" min="0" class="form-control" id="return_window" name="return_window" value="<?= e(ProductForm::value($form, 'return_window', '30')) ?>">
    </div>
    <div class="col-md-8 d-flex flex-wrap gap-4 align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="cod_support" name="cod_support" value="1" <?= ProductForm::checked($form, 'cod_support') ?>>
            <label class="form-check-label" for="cod_support">COD</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="is_digital" name="is_digital" value="1" <?= ProductForm::checked($form, 'is_digital') ?>>
            <label class="form-check-label" for="is_digital">Digital Product</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="fragile" name="fragile" value="1" <?= ProductForm::checked($form, 'fragile') ?>>
            <label class="form-check-label" for="fragile">Fragile</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="free_shipping" name="free_shipping" value="1" <?= ProductForm::checked($form, 'free_shipping') ?>>
            <label class="form-check-label" for="free_shipping">Free Shipping</label>
        </div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="requires_shipping" name="requires_shipping" value="1" <?= ProductForm::checked($form, 'requires_shipping', true) ?>>
            <label class="form-check-label" for="requires_shipping">Requires Shipping</label>
        </div>
    </div>
</div>
