<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="warranty_enabled" name="warranty_enabled" value="1" <?= ProductForm::checked($form, 'warranty_enabled') ?>>
            <label class="form-check-label" for="warranty_enabled">Enable Warranty</label>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="warranty_type">Warranty Type</label>
        <select class="form-select" id="warranty_type" name="warranty_type">
            <?php foreach (['manufacturer' => 'Manufacturer', 'extended' => 'Extended', 'seller' => 'Seller'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ProductForm::selected($form, 'warranty_type', $val) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="warranty_period">Duration</label>
        <input type="number" min="0" class="form-control" id="warranty_period" name="warranty_period" value="<?= e(ProductForm::value($form, 'warranty_period', '12')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="warranty_unit">Unit</label>
        <select class="form-select" id="warranty_unit" name="warranty_unit">
            <?php foreach (['months' => 'Months', 'years' => 'Years', 'days' => 'Days'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ProductForm::selected($form, 'warranty_unit', $val) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="warranty_start_date">Start Date</label>
        <input type="date" class="form-control" id="warranty_start_date" name="warranty_start_date" value="<?= e(ProductForm::value($form, 'warranty_start_date')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="warranty_provider">Provider</label>
        <input type="text" class="form-control" id="warranty_provider" name="warranty_provider" value="<?= e(ProductForm::value($form, 'warranty_provider')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="service_center_name">Service Center</label>
        <input type="text" class="form-control" id="service_center_name" name="service_center_name" value="<?= e(ProductForm::value($form, 'service_center_name')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="service_center_phone">Service Phone</label>
        <input type="tel" class="form-control" id="service_center_phone" name="service_center_phone" value="<?= e(ProductForm::value($form, 'service_center_phone')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="service_center_email">Email</label>
        <input type="email" class="form-control" id="service_center_email" name="service_center_email" value="<?= e(ProductForm::value($form, 'service_center_email')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="warranty_document">Warranty Upload</label>
        <input type="file" class="form-control" id="warranty_document" name="warranty_document" accept="application/pdf,image/*">
    </div>
    <div class="col-12">
        <label class="form-label" for="warranty_claim_process">Claim Process</label>
        <textarea class="form-control" id="warranty_claim_process" name="warranty_claim_process" rows="3"><?= e(ProductForm::value($form, 'warranty_claim_process')) ?></textarea>
    </div>
    <div class="col-12">
        <label class="form-label" for="warranty_terms">Warranty Terms</label>
        <textarea class="form-control" id="warranty_terms" name="warranty_terms" rows="3"><?= e(ProductForm::value($form, 'warranty_terms')) ?></textarea>
    </div>
</div>
