<?php
declare(strict_types=1);
$reviewSections = [
    'Product Information' => ['name', 'sku', 'brand_id', 'category_id'],
    'Pricing' => ['cost_price', 'selling_price', 'currency'],
    'Inventory' => ['opening_stock', 'warehouse_id', 'reorder_level'],
    'Media' => ['images'],
    'Shipping' => ['shipping_weight', 'shipping_class'],
    'SEO' => ['meta_title', 'meta_description', 'seo_url'],
    'Warranty' => ['warranty_enabled', 'warranty_type'],
    'Variants' => ['variant_colors', 'variant_sizes'],
];
?>
<div class="pc-review">
    <p class="text-secondary">Review all sections before publishing. Required fields are validated automatically.</p>
    <ul class="list-group list-group-flush mb-4" id="pcReviewList">
        <?php foreach ($reviewSections as $title => $fields): ?>
        <li class="list-group-item pc-review-item d-flex justify-content-between align-items-center px-0" data-section="<?= e(strtolower(str_replace(' ', '-', $title))) ?>">
            <span><i class="bi bi-check-circle text-success me-2 pc-review-icon" aria-hidden="true"></i><?= e($title) ?></span>
            <span class="badge text-bg-secondary pc-review-badge">Pending</span>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="alert alert-info small" role="status" id="pcValidationSummary">Final validation runs when you publish.</div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" id="pcReviewPublish" data-intent="publish">
            <i class="bi bi-rocket-takeoff me-1" aria-hidden="true"></i>Publish Product
        </button>
        <button type="button" class="btn btn-outline-secondary" id="pcReviewValidate">Run Validation</button>
    </div>
    <input type="hidden" name="status" id="status" value="<?= e(ProductForm::value($form, 'status', 'active')) ?>">
</div>
