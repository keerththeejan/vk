<?php
/**
 * Enterprise Product Basic Information module (Add Product — Step 1).
 *
 * Preserves all existing form field names/IDs for backend compatibility.
 *
 * @var array<string, mixed> $form
 * @var array<int, array<string, mixed>> $brands
 * @var array<int, array<string, mixed>> $cats
 * @var array<int, array<string, mixed>> $suppliers
 * @var array<int, array<string, mixed>> $manufacturers
 * @var array<int, array<string, mixed>> $catTree
 */

declare(strict_types=1);

$catParents = [];
$catChildrenByParent = [];
foreach ($catTree as $catRow) {
    $parentId = (int) ($catRow['parent_id'] ?? 0);
    $catId = (int) ($catRow['id'] ?? 0);
    if ($parentId <= 0) {
        $catParents[] = $catRow;
        continue;
    }
    $catChildrenByParent[$parentId][] = $catRow;
}

if ($catParents === [] && $cats !== []) {
    $catParents = $cats;
}

$countries = [
    'India', 'United States', 'United Kingdom', 'United Arab Emirates', 'Sri Lanka',
    'Singapore', 'Germany', 'France', 'China', 'Japan', 'Australia', 'Canada', 'Malaysia',
];

$selectedCategory = studio_value($form, 'category_id');
$selectedSubcategory = studio_value($form, 'subcategory_id');
?>
<section id="section-basic" class="studio-card studio-section" data-step-key="basic">
    <button type="button" class="studio-section-toggle" data-section-toggle="section-basic-body">
        <div>
            <span class="studio-section-kicker">Step 1</span>
            <h2>Basic Information</h2>
            <p>Enterprise identity, catalog structure, and product narrative with live validation.</p>
        </div>
        <span class="studio-section-tools">
            <span class="studio-chip">Core</span>
            <i class="bi bi-chevron-down"></i>
        </span>
    </button>

    <div class="studio-section-body" id="section-basic-body">
        <!-- Live snapshot + publishing controls (unchanged backend fields) -->
        <div class="studio-overview-grid">
            <article class="studio-inline-preview-card">
                <div class="studio-inline-preview-head">
                    <div>
                        <strong>Live Product Snapshot</strong>
                        <span>Listing preview updates as you type.</span>
                    </div>
                    <span class="studio-chip" id="inlinePreviewStatus">Draft</span>
                </div>
                <div class="studio-inline-preview-identity">
                    <div class="studio-inline-preview-avatar" id="inlinePreviewAvatar">P</div>
                    <div>
                        <h3 id="inlinePreviewName">Untitled product</h3>
                        <p id="inlinePreviewCategory">Category pending</p>
                    </div>
                </div>
                <div class="studio-inline-preview-metrics">
                    <article><span>SKU</span><strong id="inlinePreviewSku">Pending</strong></article>
                    <article><span>Stock</span><strong id="inlinePreviewStock">Not set</strong></article>
                    <article><span>Price</span><strong id="inlinePreviewPrice">$0.00</strong></article>
                </div>
                <div class="studio-code-grid">
                    <div class="studio-code-card">
                        <span>Barcode</span>
                        <svg id="barcodePreview" aria-label="Barcode preview"></svg>
                    </div>
                    <div class="studio-code-card">
                        <span>QR Preview</span>
                        <div id="qrPreview" class="studio-qr-card"></div>
                    </div>
                </div>
            </article>

            <article class="studio-management-panel">
                <div class="studio-management-head">
                    <strong>Publishing Controls</strong>
                    <span>Visibility, merchandising, and operational status</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-floating studio-floating basic-field-shell">
                            <select class="form-select" id="visibility" name="visibility">
                                <?php foreach (['private' => 'Private', 'catalog' => 'Catalog only', 'public' => 'Public'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= studio_selected($form, 'visibility', $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="visibility"><i class="bi bi-eye me-1"></i> Visibility</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating studio-floating basic-field-shell">
                            <select class="form-select" id="stock_status" name="stock_status">
                                <?php foreach (['in_stock' => 'In stock', 'preorder' => 'Pre-order', 'backorder' => 'Backorder', 'out_of_stock' => 'Out of stock'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= studio_selected($form, 'stock_status', $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="stock_status"><i class="bi bi-box-seam me-1"></i> Stock Status</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating studio-floating basic-field-shell">
                            <input class="form-control" id="collections" name="collections" placeholder="Collections" value="<?= e(studio_value($form, 'collections')) ?>">
                            <label for="collections"><i class="bi bi-collection me-1"></i> Collections</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating studio-floating basic-field-shell">
                            <select class="form-select" id="status" name="status" required data-required-label="Product Status">
                                <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= studio_selected($form, 'status', $value) ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="status">Product Status <span class="studio-required">*</span></label>
                        </div>
                    </div>
                </div>
                <div class="studio-toggle-grid compact mt-3">
                    <label class="studio-toggle-card">
                        <input type="checkbox" name="featured" id="featured" <?= studio_checked($form, 'featured') ?>>
                        <span><strong>Featured</strong><small>Promote in storefront highlights</small></span>
                    </label>
                    <label class="studio-toggle-card">
                        <input type="checkbox" name="trending" id="trending" <?= studio_checked($form, 'trending') ?>>
                        <span><strong>Trending</strong><small>Boost campaign ranking</small></span>
                    </label>
                    <label class="studio-toggle-card">
                        <input type="checkbox" name="is_digital" id="is_digital" <?= studio_checked($form, 'is_digital') ?>>
                        <span><strong>Digital Product</strong><small>Auto-adjust physical steps</small></span>
                    </label>
                </div>
            </article>
        </div>

        <!-- Enterprise Basic Information module -->
        <div
            id="basicInfoModule"
            class="basic-info-module"
            data-autosave-ms="10000"
            data-autosave-url="<?= e(base_url('modules/products/add.php')) ?>"
        >
            <script type="application/json" id="basicCategoryTree"><?= htmlspecialchars(json_encode($catChildrenByParent, JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8') ?></script>

            <header class="basic-module-header card border-0 shadow-sm">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h3 class="basic-module-title mb-1"><i class="bi bi-box-seam-fill me-2"></i>Product Identity</h3>
                        <p class="basic-module-subtitle mb-0">Two-column enterprise form with floating labels, validation, and draft sync.</p>
                    </div>
                    <div class="basic-module-status" aria-live="polite">
                        <span class="basic-autosave-badge" id="basicAutosaveBadge">
                            <i class="bi bi-cloud-check"></i> Auto-save every 10s
                        </span>
                        <span class="basic-validation-badge" id="basicValidationBadge">
                            <i class="bi bi-shield-check"></i> Validating…
                        </span>
                    </div>
                </div>
                <div class="basic-module-progress">
                    <div class="progress" role="progressbar" aria-label="Basic section completion" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="basicModuleProgress" style="width: 0%"></div>
                    </div>
                </div>
            </header>

            <div class="basic-slug-bar card border-0 shadow-sm" aria-live="polite">
                <div class="card-body d-flex flex-wrap align-items-center gap-2">
                    <i class="bi bi-link-45deg"></i>
                    <span class="text-muted">Slug preview</span>
                    <code id="slugPreviewText">product</code>
                    <button type="button" class="btn btn-sm btn-outline-light ms-auto" id="basicDuplicateCheck">
                        <i class="bi bi-intersect"></i> Check duplicates
                    </button>
                </div>
            </div>

            <!-- Card: Identity & codes -->
            <div class="card basic-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-fingerprint me-2"></i>Identity &amp; Codes</h4>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="name" name="name" placeholder="Product name" value="<?= e(studio_value($form, 'name')) ?>" required data-required-label="Product Name" maxlength="120" aria-describedby="nameCharMeta">
                                <label for="name">Product Name <span class="studio-required">*</span></label>
                            </div>
                            <div class="basic-field-meta" id="nameCharMeta">
                                <span><i class="bi bi-type"></i> Catalog title</span>
                                <span id="nameCharCount">0 / 120</span>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="subtitle" name="subtitle" placeholder="Subtitle" value="<?= e(studio_value($form, 'subtitle')) ?>" maxlength="160" aria-describedby="subtitleCharMeta">
                                <label for="subtitle"><i class="bi bi-card-text"></i> Product Subtitle</label>
                            </div>
                            <div class="basic-field-meta" id="subtitleCharMeta">
                                <span>Supporting headline</span>
                                <span id="subtitleCharCount">0 / 160</span>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="classification" name="classification" placeholder="Product code" value="<?= e(studio_value($form, 'classification')) ?>" maxlength="64" aria-describedby="productCodeMeta">
                                <label for="classification">Product Code</label>
                            </div>
                            <div class="basic-field-meta" id="productCodeMeta">
                                <span>Internal merchandise code</span>
                                <span id="productCodeCharCount">0 / 64</span>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="sku" name="sku" placeholder="SKU" value="<?= e(studio_value($form, 'sku')) ?>" required data-required-label="SKU" maxlength="128" aria-describedby="skuFieldMeta">
                                <label for="sku">SKU <span class="studio-required">*</span></label>
                            </div>
                            <div class="basic-field-meta" id="skuFieldMeta">
                                <span>Stock keeping unit</span>
                                <button type="button" class="btn btn-sm basic-mini-btn" id="generateSkuButton">
                                    <i class="bi bi-upc-scan"></i> Auto Generate SKU
                                </button>
                            </div>
                            <div class="studio-inline-status mt-2" id="skuStatus" aria-live="polite">SKU uniqueness checked automatically</div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="barcode" name="barcode" placeholder="Barcode" value="<?= e(studio_value($form, 'barcode')) ?>" aria-describedby="barcodeMeta">
                                <label for="barcode"><i class="bi bi-upc"></i> Barcode</label>
                            </div>
                            <div class="basic-field-meta" id="barcodeMeta">
                                <span>EAN / UPC identifier</span>
                                <span id="barcodeValidationIcon" class="basic-field-icon" aria-hidden="true"></span>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="qr_code" name="qr_code" placeholder="QR identifier" value="<?= e(studio_value($form, 'qr_code')) ?>">
                                <label for="qr_code"><i class="bi bi-qr-code"></i> QR Code</label>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <select class="form-select" id="product_type" name="product_type">
                                    <?php foreach (['simple' => 'Simple', 'configurable' => 'Configurable', 'bundle' => 'Bundle', 'grouped' => 'Grouped', 'virtual' => 'Virtual', 'downloadable' => 'Downloadable'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= studio_selected($form, 'product_type', $value ?: 'simple') ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="product_type">Product Type</label>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="basic-inline-status" id="titleAssistStatus" aria-live="polite">
                                <i class="bi bi-lightbulb"></i> Use a clear product title for duplicate detection.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Catalog & supply chain -->
            <div class="card basic-form-card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Catalog &amp; Supply</h4>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="studio-search-select basic-field-shell">
                                <label for="brand_id">Brand <span class="studio-required">*</span></label>
                                <input type="search" class="form-control studio-filter-input" data-filter-target="brand_id" placeholder="Search brand" aria-label="Search brand">
                                <select class="form-select" id="brand_id" name="brand_id" required data-required-label="Brand" data-placeholder="Select brand">
                                    <option value="">Select brand</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?= e((string) $brand['id']) ?>" <?= studio_selected($form, 'brand_id', (string) $brand['id']) ?>><?= e((string) $brand['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="studio-search-select basic-field-shell">
                                <label for="category_id">Category <span class="studio-required">*</span></label>
                                <input type="search" class="form-control studio-filter-input" data-filter-target="category_id" placeholder="Search category" aria-label="Search category">
                                <select class="form-select" id="category_id" name="category_id" required data-required-label="Category" data-placeholder="Select category">
                                    <option value="">Select category</option>
                                    <?php foreach ($catParents as $cat): ?>
                                        <option value="<?= e((string) $cat['id']) ?>" <?= studio_selected($form, 'category_id', (string) $cat['id']) ?>><?= e((string) $cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="studio-search-select basic-field-shell">
                                <label for="subcategory_id">Sub Category</label>
                                <select class="form-select" id="subcategory_id" name="subcategory_id" data-placeholder="Select sub category">
                                    <option value="">Select sub category</option>
                                    <?php
                                    if ($selectedCategory !== '' && isset($catChildrenByParent[(int) $selectedCategory])) {
                                        foreach ($catChildrenByParent[(int) $selectedCategory] as $subcat) {
                                            echo '<option value="' . e((string) $subcat['id']) . '" ' . studio_selected($form, 'subcategory_id', (string) $subcat['id']) . '>' . e((string) $subcat['name']) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="studio-search-select basic-field-shell">
                                <label for="supplier_id">Supplier</label>
                                <input type="search" class="form-control studio-filter-input" data-filter-target="supplier_id" placeholder="Search supplier" aria-label="Search supplier">
                                <select class="form-select" id="supplier_id" name="supplier_id" data-placeholder="Select supplier">
                                    <option value="">Select supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= e((string) $supplier['id']) ?>" <?= studio_selected($form, 'supplier_id', (string) $supplier['id']) ?>><?= e((string) $supplier['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="studio-search-select basic-field-shell">
                                <label for="manufacturer_id">Manufacturer</label>
                                <input type="search" class="form-control studio-filter-input" data-filter-target="manufacturer_id" placeholder="Search manufacturer" aria-label="Search manufacturer">
                                <select class="form-select" id="manufacturer_id" name="manufacturer_id" data-placeholder="Select manufacturer">
                                    <option value="">Select manufacturer</option>
                                    <?php foreach ($manufacturers as $manufacturer): ?>
                                        <option value="<?= e((string) $manufacturer['id']) ?>" <?= studio_selected($form, 'manufacturer_id', (string) $manufacturer['id']) ?>><?= e((string) $manufacturer['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <select class="form-select" id="unit_type" name="unit_type" required data-required-label="Unit Type">
                                    <?php foreach (['piece', 'kg', 'gram', 'liter', 'ml', 'meter', 'box', 'pack', 'set'] as $unit): ?>
                                        <option value="<?= e($unit) ?>" <?= studio_selected($form, 'unit_type', $unit) ?>><?= e(strtoupper($unit === 'piece' ? 'pc' : $unit)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="unit_type">Unit Type <span class="studio-required">*</span></label>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <select class="form-select" id="country_of_origin" name="country_of_origin" data-placeholder="Select country">
                                    <option value="">Select country</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?= e($country) ?>" <?= studio_selected($form, 'country_of_origin', $country) ?>><?= e($country) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="country_of_origin">Country of Origin</label>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="hsn_sac_code" name="hsn_sac_code" placeholder="HSN / SAC" value="<?= e(studio_value($form, 'hsn_sac_code')) ?>" maxlength="32" aria-describedby="hsnMeta">
                                <label for="hsn_sac_code">HSN / SAC Code</label>
                            </div>
                            <div class="basic-field-meta" id="hsnMeta">
                                <span>Tax classification code</span>
                                <span id="hsnCharCount">0 / 32</span>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="product_tags" name="product_tags" placeholder="Tags" value="<?= e(studio_value($form, 'product_tags')) ?>" aria-describedby="tagsMeta">
                                <label for="product_tags"><i class="bi bi-tags"></i> Smart Tags</label>
                            </div>
                            <div class="basic-field-meta" id="tagsMeta">
                                <span>Comma-separated merchandising tags</span>
                                <span id="tagsCharCount">0 tags</span>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <input class="form-control" id="support_contact" name="support_contact" placeholder="Support contact" value="<?= e(studio_value($form, 'support_contact')) ?>" maxlength="120" aria-describedby="supportMeta">
                                <label for="support_contact"><i class="bi bi-headset"></i> Support Contact</label>
                            </div>
                            <div class="basic-field-meta" id="supportMeta">
                                <span>Email or phone for product support</span>
                                <span id="supportCharCount">0 / 120</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card: Description studio (Markdown + Rich Text + Preview) -->
            <div class="card basic-form-card border-0 shadow-sm basic-editor-card">
                <div class="card-header bg-transparent border-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h4 class="mb-0"><i class="bi bi-file-earmark-richtext me-2"></i>Description Studio</h4>
                    <div class="basic-editor-actions">
                        <button type="button" class="btn btn-sm basic-mini-btn" id="aiDescriptionButton">
                            <i class="bi bi-stars"></i> AI Generator
                        </button>
                        <button type="button" class="btn btn-sm basic-mini-btn" data-insert-template="description">
                            <i class="bi bi-magic"></i> Template
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <ul class="nav nav-pills basic-editor-tabs mb-3" id="descriptionEditorTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="rich-tab" data-bs-toggle="pill" data-bs-target="#rich-pane" type="button" role="tab" aria-controls="rich-pane" aria-selected="true">
                                <i class="bi bi-type-bold"></i> Rich Text
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="markdown-tab" data-bs-toggle="pill" data-bs-target="#markdown-pane" type="button" role="tab" aria-controls="markdown-pane" aria-selected="false">
                                <i class="bi bi-markdown"></i> Markdown
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="preview-tab" data-bs-toggle="pill" data-bs-target="#preview-pane" type="button" role="tab" aria-controls="preview-pane" aria-selected="false">
                                <i class="bi bi-eye"></i> Live Preview
                            </button>
                        </li>
                    </ul>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="form-floating studio-floating basic-field-shell">
                                <textarea class="form-control studio-textarea-sm" id="short_description" name="short_description" placeholder="Short description" aria-describedby="shortDescMeta"><?= e(studio_value($form, 'short_description')) ?></textarea>
                                <label for="short_description">Short Description</label>
                            </div>
                            <div class="basic-field-meta" id="shortDescMeta">
                                <span>Listing summary</span>
                                <span id="shortDescCharCount">0 / 512</span>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="tab-content basic-editor-panes" id="descriptionEditorContent">
                                <div class="tab-pane fade show active" id="rich-pane" role="tabpanel" aria-labelledby="rich-tab">
                                    <div class="basic-rich-toolbar btn-toolbar mb-2" role="toolbar" aria-label="Rich text formatting">
                                        <button type="button" class="btn btn-sm basic-mini-btn" data-rich-cmd="bold"><i class="bi bi-type-bold"></i></button>
                                        <button type="button" class="btn btn-sm basic-mini-btn" data-rich-cmd="italic"><i class="bi bi-type-italic"></i></button>
                                        <button type="button" class="btn btn-sm basic-mini-btn" data-rich-cmd="insertUnorderedList"><i class="bi bi-list-ul"></i></button>
                                        <button type="button" class="btn btn-sm basic-mini-btn" data-rich-cmd="insertOrderedList"><i class="bi bi-list-ol"></i></button>
                                        <button type="button" class="btn btn-sm basic-mini-btn" data-rich-md="h2"><i class="bi bi-hash"></i></button>
                                        <button type="button" class="btn btn-sm basic-mini-btn" data-rich-md="link"><i class="bi bi-link"></i></button>
                                    </div>
                                    <div
                                        id="descriptionRichEditor"
                                        class="basic-rich-editor form-control"
                                        contenteditable="true"
                                        role="textbox"
                                        aria-multiline="true"
                                        aria-label="Product description rich text editor"
                                        data-placeholder="Write a compelling product description…"
                                    ></div>
                                </div>
                                <div class="tab-pane fade" id="markdown-pane" role="tabpanel" aria-labelledby="markdown-tab">
                                    <textarea id="descriptionMarkdown" class="form-control basic-markdown-editor" rows="8" aria-label="Product description markdown editor"></textarea>
                                </div>
                                <div class="tab-pane fade" id="preview-pane" role="tabpanel" aria-labelledby="preview-tab">
                                    <div id="descriptionPreview" class="basic-markdown-preview" aria-live="polite"></div>
                                </div>
                            </div>
                            <textarea class="visually-hidden" id="description" name="description" required data-required-label="Product Description"><?= e(studio_value($form, 'description')) ?></textarea>
                            <div class="basic-field-meta mt-2">
                                <span>Product Description <span class="studio-required">*</span></span>
                                <span id="descriptionCharCount">0 chars</span>
                            </div>
                        </div>
                    </div>

                    <div class="basic-editor-metrics">
                        <article><span><i class="bi bi-file-text"></i> Words</span><strong id="descriptionWordCount">0</strong></article>
                        <article><span><i class="bi bi-input-cursor-text"></i> Characters</span><strong id="descriptionCharacterCount">0</strong></article>
                        <article><span><i class="bi bi-clock"></i> Reading time</span><strong id="descriptionReadingTime">0 min</strong></article>
                        <article><span><i class="bi bi-graph-up"></i> Readability</span><strong id="descriptionReadability">Needs work</strong></article>
                    </div>

                    <div class="basic-duplicate-panel" id="basicDuplicatePanel" hidden>
                        <div class="basic-duplicate-panel__head">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Possible duplicates</strong>
                        </div>
                        <ul id="basicDuplicateList" class="mb-0"></ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
