<?php
declare(strict_types=1);
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="seo_url">SEO URL / Slug</label>
        <div class="input-group">
            <input type="text" class="form-control" id="seo_url" name="seo_url" value="<?= e(ProductForm::value($form, 'seo_url')) ?>">
            <button type="button" class="btn btn-outline-secondary" id="pcGenSlug">Auto</button>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="focus_keyword">Focus Keyword</label>
        <input type="text" class="form-control" id="focus_keyword" name="focus_keyword" value="<?= e(ProductForm::value($form, 'focus_keyword')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="meta_title">Meta Title</label>
        <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="70" value="<?= e(ProductForm::value($form, 'meta_title')) ?>">
        <div class="form-text"><span id="pcMetaTitleCount">0</span>/70</div>
    </div>
    <div class="col-12">
        <label class="form-label" for="meta_keywords">Meta Keywords</label>
        <input type="text" class="form-control" id="meta_keywords" name="meta_keywords" value="<?= e(ProductForm::value($form, 'meta_keywords')) ?>">
    </div>
    <div class="col-12">
        <label class="form-label" for="meta_description">Meta Description</label>
        <textarea class="form-control" id="meta_description" name="meta_description" rows="3" maxlength="160"><?= e(ProductForm::value($form, 'meta_description')) ?></textarea>
        <div class="form-text"><span id="pcMetaDescCount">0</span>/160</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="canonical_url">Canonical URL</label>
        <input type="url" class="form-control" id="canonical_url" name="canonical_url" value="<?= e(ProductForm::value($form, 'canonical_url')) ?>">
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <button type="button" class="btn btn-outline-primary" id="pcAutoSeo">Auto SEO Generator</button>
    </div>
    <div class="col-12">
        <div class="card pc-metric-card border-0">
            <div class="card-body">
                <h3 class="h6">Google Preview</h3>
                <div class="pc-serp-preview">
                    <div class="pc-serp-title" id="pcSerpTitle">Product Title</div>
                    <div class="pc-serp-url" id="pcSerpUrl">example.com/product</div>
                    <div class="pc-serp-desc" id="pcSerpDesc">Meta description preview will appear here.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="og_title">Open Graph Title</label>
        <input type="text" class="form-control" id="og_title" name="og_title" value="<?= e(ProductForm::value($form, 'og_title')) ?>">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="twitter_card">Twitter Card</label>
        <select class="form-select" id="twitter_card" name="twitter_card">
            <option value="summary">Summary</option>
            <option value="summary_large_image">Summary Large Image</option>
        </select>
    </div>
</div>
