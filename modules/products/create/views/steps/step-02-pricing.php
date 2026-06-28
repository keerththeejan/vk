<?php
declare(strict_types=1);
$taxClasses = $lookups['taxClasses'] ?? [];
?>
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label" for="cost_price">Cost Price</label>
        <input type="number" step="0.01" min="0" class="form-control pc-calc" id="cost_price" name="cost_price" value="<?= e(ProductForm::value($form, 'cost_price', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="selling_price">Selling Price</label>
        <input type="number" step="0.01" min="0" class="form-control pc-calc" id="selling_price" name="selling_price" value="<?= e(ProductForm::value($form, 'selling_price', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="wholesale_price">Wholesale</label>
        <input type="number" step="0.01" min="0" class="form-control" id="wholesale_price" name="wholesale_price" value="<?= e(ProductForm::value($form, 'wholesale_price', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="dealer_price">Dealer</label>
        <input type="number" step="0.01" min="0" class="form-control" id="dealer_price" name="dealer_price" value="<?= e(ProductForm::value($form, 'dealer_price', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="distributor_price">Distributor</label>
        <input type="number" step="0.01" min="0" class="form-control" id="distributor_price" name="distributor_price" value="<?= e(ProductForm::value($form, 'distributor_price', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="msrp">MSRP</label>
        <input type="number" step="0.01" min="0" class="form-control" id="msrp" name="msrp" value="<?= e(ProductForm::value($form, 'msrp', '0')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="currency">Currency</label>
        <select class="form-select" id="currency" name="currency">
            <?php foreach (['USD', 'EUR', 'GBP', 'INR', 'PKR'] as $cur): ?>
            <option value="<?= e($cur) ?>" <?= ProductForm::selected($form, 'currency', $cur) ?>><?= e($cur) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="tax_class_id">Tax Class</label>
        <select class="form-select pc-searchable" id="tax_class_id" name="tax_class_id" data-rate-field="tax_rate">
            <option value="">None</option>
            <?php foreach ($taxClasses as $row): ?>
            <option value="<?= (int) $row['id'] ?>" data-rate="<?= e((string) ($row['rate'] ?? '0')) ?>" <?= ProductForm::selected($form, 'tax_class_id', (string) $row['id']) ?>><?= e((string) $row['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="tax_rate">Tax Rate (%)</label>
        <input type="number" step="0.01" min="0" class="form-control pc-calc" id="tax_rate" name="tax_rate" value="<?= e(ProductForm::value($form, 'tax_rate', '0')) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="vat_gst">VAT/GST (%)</label>
        <input type="number" step="0.01" min="0" class="form-control" id="vat_gst" name="vat_gst" value="<?= e(ProductForm::value($form, 'vat_gst', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="discount_type">Discount Type</label>
        <select class="form-select" id="discount_type" name="discount_type">
            <?php foreach (['none' => 'None', 'percent' => 'Percent', 'fixed' => 'Fixed'] as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= ProductForm::selected($form, 'discount_type', $val) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="discount_value">Discount Value</label>
        <input type="number" step="0.01" min="0" class="form-control pc-calc" id="discount_value" name="discount_value" value="<?= e(ProductForm::value($form, 'discount_value', '0')) ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label" for="promotional_price">Promotion Price</label>
        <input type="number" step="0.01" min="0" class="form-control" id="promotional_price" name="promotional_price" value="<?= e(ProductForm::value($form, 'promotional_price', '0')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="promo_start_date">Promo Start</label>
        <input type="date" class="form-control" id="promo_start_date" name="promo_start_date" value="<?= e(ProductForm::value($form, 'promo_start_date')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="promo_end_date">Promo End</label>
        <input type="date" class="form-control" id="promo_end_date" name="promo_end_date" value="<?= e(ProductForm::value($form, 'promo_end_date')) ?>">
    </div>
    <div class="col-12">
        <div class="card pc-metric-card border-0">
            <div class="card-body">
                <h3 class="h6">Margin Calculator</h3>
                <div class="row g-2 small">
                    <div class="col-sm-4"><span class="text-secondary">Gross Profit</span><div class="fw-semibold" id="pcGrossProfit">—</div></div>
                    <div class="col-sm-4"><span class="text-secondary">Margin %</span><div class="fw-semibold" id="pcMarginPct">—</div></div>
                    <div class="col-sm-4"><span class="text-secondary">Markup %</span><div class="fw-semibold" id="pcMarkupPct">—</div></div>
                </div>
                <input type="hidden" name="profit_margin" id="profit_margin" value="<?= e(ProductForm::value($form, 'profit_margin', '0')) ?>">
            </div>
        </div>
    </div>
</div>
