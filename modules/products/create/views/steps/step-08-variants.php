<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="variant_colors">Color (comma separated)</label>
        <input type="text" class="form-control pc-variant-src" id="variant_colors" name="variant_colors" value="<?= e(ProductForm::value($form, 'variant_colors')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="variant_sizes">Size</label>
        <input type="text" class="form-control pc-variant-src" id="variant_sizes" name="variant_sizes" value="<?= e(ProductForm::value($form, 'variant_sizes')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="variant_materials">Material</label>
        <input type="text" class="form-control pc-variant-src" id="variant_materials" name="variant_materials" value="<?= e(ProductForm::value($form, 'variant_materials')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="variant_storage">Storage</label>
        <input type="text" class="form-control pc-variant-src" id="variant_storage" name="variant_storage" value="<?= e(ProductForm::value($form, 'variant_storage')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="variant_models">Model</label>
        <input type="text" class="form-control pc-variant-src" id="variant_models" name="variant_models" value="<?= e(ProductForm::value($form, 'variant_models')) ?>">
    </div>
    <div class="col-md-6 d-flex align-items-end gap-2">
        <button type="button" class="btn btn-outline-primary" id="pcGenVariants">Auto Variant Generator</button>
    </div>
    <div class="col-12">
        <div class="table-responsive">
            <table class="table table-sm pc-variant-table align-middle" id="pcVariantTable">
                <thead>
                    <tr>
                        <th>Variant</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody id="pcVariantBody">
                    <tr class="text-secondary"><td colspan="4">Generate variants to preview combinations.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-12">
        <label class="form-label" for="variant_notes">Variant Notes</label>
        <textarea class="form-control" id="variant_notes" name="variant_notes" rows="2"><?= e(ProductForm::value($form, 'variant_notes')) ?></textarea>
    </div>
</div>
