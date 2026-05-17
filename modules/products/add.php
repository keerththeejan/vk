<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? 0);
    $lowTh = max(0, (int) ($_POST['low_stock_threshold'] ?? 5));
    $category = trim((string) ($_POST['category'] ?? ''));
    if ($name === '') {
        flash_set('error', 'Name is required.');
    } elseif ($price < 0 || $stock < 0) {
        flash_set('error', 'Price and stock must be zero or positive.');
    } else {
        $pdo->prepare('INSERT INTO products (name, price, stock, low_stock_threshold, category) VALUES (?,?,?,?,?)')
            ->execute([$name, $price, $stock, $lowTh, $category ?: null]);
        flash_set('success', 'Product added.');
        redirect('/modules/products/list.php');
    }
}

$pageTitle = 'Add product';
$extraHead = <<<HTML
<style>
:root {
    color-scheme: dark;
    --vk-bg: #041127;
    --vk-surface: rgba(7, 18, 40, 0.94);
    --vk-surface-strong: rgba(12, 26, 55, 0.95);
    --vk-border: rgba(255, 255, 255, 0.08);
    --vk-text: #f7f9ff;
    --vk-muted: rgba(255, 255, 255, 0.68);
    --vk-accent: #5cc8ff;
    --vk-accent-strong: #3aa7ff;
    --vk-shadow: 0 32px 100px rgba(0, 0, 0, 0.24);
}
.vk-product-shell {
    position: relative;
    max-width: 1180px;
    margin: 0 auto;
    padding: 2rem 0 3rem;
}
.vk-product-shell::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top left, rgba(96, 165, 250, 0.18), transparent 24%),
                radial-gradient(circle at bottom right, rgba(59, 130, 246, 0.14), transparent 18%),
                linear-gradient(180deg, rgba(4, 17, 39, 0.96), rgba(7, 20, 49, 0.92));
    z-index: -1;
}
.vk-breadcrumb {
    font-size: 0.92rem;
}
.vk-breadcrumb .breadcrumb-item a {
    color: rgba(255, 255, 255, 0.78);
}
.vk-breadcrumb .breadcrumb-item a:hover {
    color: #ffffff;
}
.vk-page-header {
    background: rgba(5, 11, 30, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(18px);
    box-shadow: var(--vk-shadow);
}
.vk-page-header .vk-page-title {
    color: #ffffff;
}
.vk-page-header .vk-page-copy {
    color: rgba(255, 255, 255, 0.72);
}
.vk-card-panel,
.vk-card-panel-secondary {
    background: var(--vk-surface);
    border: 1px solid var(--vk-border);
    box-shadow: var(--vk-shadow);
    border-radius: 1.25rem;
}
.vk-card-panel-secondary {
    background: rgba(9, 18, 40, 0.88);
}
.vk-card-panel .card-body,
.vk-card-panel-secondary .card-body {
    padding: 2rem;
}
.vk-card-section {
    border-radius: 1.15rem;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.02);
    padding: 1.6rem;
    margin-bottom: 1.5rem;
}
.vk-card-section h2 {
    font-size: 1.03rem;
    margin-bottom: 0.75rem;
    color: #ffffff;
}
.vk-card-section p {
    margin-bottom: 1.25rem;
    color: rgba(255, 255, 255, 0.65);
}
.vk-floating-input {
    position: relative;
}
.vk-floating-input input,
.vk-floating-input textarea,
.vk-floating-input select {
    width: 100%;
    border-radius: 1.05rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.04);
    color: var(--vk-text);
    padding: 1.35rem 1rem 0.75rem 3.7rem;
    transition: border-color 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
    min-height: 4.25rem;
}
.vk-floating-input textarea {
    min-height: 8rem;
    padding-top: 1.4rem;
}
.vk-floating-input select {
    appearance: none;
    background-image: linear-gradient(45deg, transparent 50%, rgba(255, 255, 255, 0.22) 50%),
                      linear-gradient(135deg, rgba(255, 255, 255, 0.22) 50%, transparent 50%);
    background-position: calc(100% - 1.1rem) calc(1.1rem + 0.15rem), calc(100% - 0.7rem) calc(1.1rem + 0.15rem);
    background-size: 8px 8px, 8px 8px;
    background-repeat: no-repeat;
    padding-right: 3.3rem;
}
.vk-floating-input input::placeholder,
.vk-floating-input textarea::placeholder {
    color: transparent;
}
.vk-floating-input label {
    position: absolute;
    top: 1.1rem;
    left: 3.7rem;
    color: rgba(255, 255, 255, 0.64);
    font-size: 0.92rem;
    pointer-events: none;
    transform-origin: left top;
    transition: transform 0.2s ease, color 0.2s ease, top 0.2s ease;
}
.vk-floating-input.active label {
    transform: translateY(-0.82rem) scale(0.86);
    color: rgba(92, 200, 255, 0.92);
}
.vk-floating-input input:focus,
.vk-floating-input textarea:focus,
.vk-floating-input select:focus {
    outline: none;
    border-color: rgba(92, 200, 255, 0.85);
    box-shadow: 0 0 0 0.35rem rgba(56, 189, 248, 0.16);
    background: rgba(255, 255, 255, 0.08);
}
.vk-input-icon {
    position: absolute;
    left: 1.15rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(92, 200, 255, 0.88);
    font-size: 1.12rem;
}
.vk-input-help {
    margin-top: 0.45rem;
    font-size: 0.88rem;
    color: rgba(255, 255, 255, 0.55);
}
.vk-upload-card {
    overflow: hidden;
}
.vk-upload-dropzone {
    position: relative;
    display: grid;
    place-items: center;
    gap: 1rem;
    min-height: 250px;
    border: 1px dashed rgba(92, 200, 255, 0.32);
    border-radius: 1.1rem;
    background: linear-gradient(180deg, rgba(12, 27, 60, 0.55), rgba(7, 15, 33, 0.95));
    color: rgba(255, 255, 255, 0.72);
    text-align: center;
    padding: 1.7rem 1.25rem;
    transition: border-color 0.25s ease, background 0.25s ease, transform 0.25s ease;
}
.vk-upload-dropzone:hover,
.vk-upload-dropzone.is-dragover {
    border-color: rgba(92, 200, 255, 0.9);
    background: rgba(12, 27, 60, 0.9);
    transform: translateY(-1px);
}
.vk-upload-dropzone .vk-upload-state {
    pointer-events: none;
}
.vk-upload-dropzone.has-preview .vk-upload-state {
    opacity: 0;
    visibility: hidden;
}
.vk-upload-preview {
    width: 100%;
    height: 260px;
    object-fit: cover;
    border-radius: 1rem;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05);
}
.vk-upload-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: center;
}
.vk-progress {
    width: 100%;
    height: 0.35rem;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 999px;
    overflow: hidden;
}
.vk-progress-bar {
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, rgba(59, 130, 246, 0.95), rgba(96, 165, 250, 0.95));
    transition: width 0.5s ease;
}
.vk-tag-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.65rem;
    margin-top: 0.75rem;
}
.vk-tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.8rem;
    border-radius: 999px;
    background: rgba(10, 24, 58, 0.92);
    border: 1px solid rgba(92, 200, 255, 0.18);
    color: #dbe9ff;
    font-size: 0.9rem;
}
.vk-tag-chip button {
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.65);
    padding: 0;
    line-height: 1;
}
.vk-action-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 0.95rem;
    justify-content: flex-end;
    align-items: center;
}
.btn-vk-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    background-image: linear-gradient(135deg, #4f7dff 0%, #29d2ff 100%);
    color: #fff;
    border: none;
    border-radius: 1rem;
    box-shadow: 0 18px 35px rgba(45, 111, 255, 0.25);
    transition: transform 0.22s ease, box-shadow 0.22s ease, opacity 0.22s ease;
}
.btn-vk-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 22px 45px rgba(45, 111, 255, 0.34);
}
.btn-vk-primary:disabled {
    opacity: 0.65;
    pointer-events: none;
    box-shadow: none;
}
.vk-sticky-panel {
    position: sticky;
    top: 1.75rem;
}
@media (max-width: 991.98px) {
    .vk-sticky-panel {
        position: static;
        top: auto;
    }
}
@media (max-width: 575.98px) {
    .vk-product-shell {
        padding: 1.25rem 0 2rem;
    }
}
</style>
HTML;
$extraScripts = <<<HTML
<script>
(function () {
    const form = document.querySelector('.vk-product-form');
    const submitButton = form?.querySelector('[data-submit-button]');
    const skuInput = form?.querySelector('#sku');
    const nameInput = form?.querySelector('#name');
    const tagInput = form?.querySelector('#tag_input');
    const tagList = form?.querySelector('#tag_chips');
    const hiddenTags = form?.querySelector('#tags');
    const floatWrappers = Array.from(form?.querySelectorAll('.vk-floating-input') || []);

    function formatSku(value) {
        return value
            .trim()
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '')
            .slice(0, 18);
    }

    function updateFloating(el) {
        const wrapper = el.closest('.vk-floating-input');
        if (!wrapper) return;
        wrapper.classList.toggle('active', el.value.trim() !== '');
    }

    floatWrappers.forEach(function (wrapper) {
        const field = wrapper.querySelector('input, textarea, select');
        if (!field) return;
        updateFloating(field);
        field.addEventListener('input', function () {
            updateFloating(field);
        });
        field.addEventListener('change', function () {
            updateFloating(field);
        });
    });

    let manualSku = false;
    if (nameInput && skuInput) {
        nameInput.addEventListener('input', function () {
            if (manualSku) return;
            const generated = formatSku(nameInput.value);
            skuInput.value = generated ? 'PRD-' + generated : '';
            updateFloating(skuInput);
        });
        skuInput.addEventListener('input', function () {
            if (skuInput.value.trim() !== '' && skuInput.value !== 'PRD-' + formatSku(nameInput.value)) {
                manualSku = true;
            }
            updateFloating(skuInput);
        });
    }

    function syncTags() {
        if (!hiddenTags) return;
        const values = Array.from(tagList.querySelectorAll('[data-tag-value]')).map(function (item) {
            return item.getAttribute('data-tag-value');
        });
        hiddenTags.value = values.join(',');
    }

    function addTag(tag) {
        if (!tag || !tag.trim() || !tagList) return;
        const normalized = tag.trim();
        const existing = tagList.querySelector('[data-tag-value="' + normalized.replace(/"/g, '&quot;') + '"]');
        if (existing) return;
        const chip = document.createElement('span');
        chip.className = 'vk-tag-chip';
        chip.setAttribute('data-tag-value', normalized);
        chip.innerHTML = '<span>' + normalized + '</span><button type="button" aria-label="Remove tag">×</button>';
        tagList.appendChild(chip);
        syncTags();
    }

    if (tagInput) {
        tagInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                addTag(tagInput.value.replace(',', ''));
                tagInput.value = '';
            }
        });
    }

    if (tagList) {
        tagList.addEventListener('click', function (event) {
            const button = event.target.closest('button');
            if (!button) return;
            const chip = button.closest('[data-tag-value]');
            chip?.remove();
            syncTags();
        });
    }

    form?.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add('was-validated');
            window.showToast('Please review the highlighted fields and try again.', 'warning');
            return;
        }
        if (submitButton) {
            submitButton.setAttribute('disabled', 'true');
            submitButton.querySelector('.spinner-border')?.classList.remove('d-none');
        }
    });
})();
</script>
HTML;
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="vk-product-shell">
    <nav aria-label="breadcrumb" class="vk-breadcrumb mb-4">
        <ol class="breadcrumb breadcrumb-transparent px-0 mb-0">
            <li class="breadcrumb-item"><a href="<?= e(BASE_URL) ?>/modules/products/list.php"><i class="bi bi-box-seam me-1"></i> Products</a></li>
            <li class="breadcrumb-item active" aria-current="page">Add product</li>
        </ol>
    </nav>

    <section class="vk-page-header card vk-card-panel mb-4 p-4">
        <div class="d-flex flex-column flex-lg-row gap-3 align-items-start justify-content-between">
            <div>
                <span class="badge bg-primary bg-opacity-15 text-primary rounded-pill mb-2">Inventory</span>
                <h1 class="vk-page-title display-6 mb-2">Create premium inventory item</h1>
                <p class="vk-page-copy mb-0">Build your product profile with price, stock controls, image assets, barcode and supplier details — optimized for modern POS and ERP workflows.</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <a href="<?= e(BASE_URL) ?>/modules/products/list.php" class="btn btn-outline-light btn-sm">View catalog</a>
            </div>
        </div>
    </section>

    <form class="vk-product-form" method="post" enctype="multipart/form-data" data-loading novalidate data-staff-form>
        <div class="row gx-4 gy-4">
            <div class="col-xl-7">
                <div class="card vk-card-panel">
                    <div class="card-body">
                        <div class="vk-card-section">
                            <h2>Product details</h2>
                            <p>Capture the core product information and use SKU, barcode, and category settings to make search and inventory management effortless.</p>
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-tag-fill"></i></span>
                                        <input id="name" name="name" type="text" class="form-control" placeholder=" " required maxlength="255" value="<?= e($_POST['name'] ?? '') ?>">
                                        <label for="name">Product name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-upc-scan"></i></span>
                                        <input id="sku" name="sku" type="text" class="form-control" placeholder=" " maxlength="32" value="<?= e($_POST['sku'] ?? '') ?>">
                                        <label for="sku">SKU</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-upc"></i></span>
                                        <input id="barcode" name="barcode" type="text" class="form-control" placeholder=" " maxlength="64" value="<?= e($_POST['barcode'] ?? '') ?>">
                                        <label for="barcode">Barcode</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-list-ul"></i></span>
                                        <input id="category" name="category" list="categoryOptions" type="text" class="form-control" placeholder=" " maxlength="128" value="<?= e($_POST['category'] ?? '') ?>">
                                        <label for="category">Category</label>
                                        <datalist id="categoryOptions">
                                            <option value="Electronics"></option>
                                            <option value="Office supplies"></option>
                                            <option value="Accessories"></option>
                                            <option value="Hardware"></option>
                                            <option value="Services"></option>
                                        </datalist>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-people-fill"></i></span>
                                        <input id="supplier" name="supplier" list="supplierOptions" type="text" class="form-control" placeholder=" " maxlength="128" value="<?= e($_POST['supplier'] ?? '') ?>">
                                        <label for="supplier">Supplier</label>
                                        <datalist id="supplierOptions">
                                            <option value="Northstar Supplies"></option>
                                            <option value="Bluewave Logistics"></option>
                                            <option value="Greenline Distributors"></option>
                                            <option value="Prime Wholesale"></option>
                                        </datalist>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="vk-card-section">
                            <h2>Product image</h2>
                            <p>Upload a polished product image for your catalog. Drag & drop or browse from your device.</p>
                            <div class="vk-upload-dropzone" data-staff-dropzone tabindex="0">
                                <div class="vk-upload-state">
                                    <div class="fs-1 text-primary"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                                    <strong class="d-block mb-1">Drag & drop your image</strong>
                                    <span class="d-block">JPG, PNG or WebP up to 5MB</span>
                                </div>
                                <img class="vk-upload-preview d-none" data-staff-preview src="" alt="Product preview">
                                <input type="file" name="product_image" data-staff-file accept="image/png,image/jpeg,image/webp" aria-label="Product image upload">
                                <input type="hidden" name="remove_image" data-staff-remove-input value="0">
                            </div>
                            <div class="vk-upload-buttons mt-3">
                                <button type="button" class="btn btn-outline-light btn-sm" data-staff-change><i class="bi bi-folder2-open me-1"></i> Browse image</button>
                                <button type="button" class="btn btn-link text-danger btn-sm" data-staff-remove>Remove image</button>
                            </div>
                        </div>

                        <div class="vk-card-section">
                            <h2>Description & tags</h2>
                            <p>Use an optional product summary and tags to make inventory search, filtering and reporting faster.</p>
                            <div class="mb-3 vk-floating-input">
                                <span class="vk-input-icon"><i class="bi bi-card-text"></i></span>
                                <textarea id="description" name="description" class="form-control" placeholder=" " maxlength="1024"><?= e($_POST['description'] ?? '') ?></textarea>
                                <label for="description">Product description</label>
                            </div>
                            <div class="vk-floating-input">
                                <span class="vk-input-icon"><i class="bi bi-tags-fill"></i></span>
                                <input id="tag_input" type="text" class="form-control" placeholder=" " autocomplete="off">
                                <label for="tag_input">Add tag</label>
                                <div class="vk-input-help">Press Enter or comma to create a tag.</div>
                            </div>
                            <div id="tag_chips" class="vk-tag-list"></div>
                            <input type="hidden" id="tags" name="tags" value="<?= e($_POST['tags'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card vk-card-panel-secondary vk-sticky-panel">
                    <div class="card-body">
                        <div class="vk-card-section">
                            <h2>Pricing & inventory</h2>
                            <p>Set retail and cost pricing, track inventory levels, and configure low-stock alerts for restocking.</p>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-currency-dollar"></i></span>
                                        <input id="price" name="price" type="number" step="0.01" min="0" class="form-control" placeholder=" " required value="<?= e($_POST['price'] ?? '0.00') ?>">
                                        <label for="price">Selling price</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-cash-stack"></i></span>
                                        <input id="cost_price" name="cost_price" type="number" step="0.01" min="0" class="form-control" placeholder=" " value="<?= e($_POST['cost_price'] ?? '0.00') ?>">
                                        <label for="cost_price">Cost price</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-box-seam"></i></span>
                                        <input id="stock" name="stock" type="number" min="0" class="form-control" placeholder=" " required value="<?= e($_POST['stock'] ?? '0') ?>">
                                        <label for="stock">Stock quantity</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-exclamation-circle"></i></span>
                                        <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" class="form-control" placeholder=" " value="<?= e($_POST['low_stock_threshold'] ?? '5') ?>">
                                        <label for="low_stock_threshold">Low stock alert</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-rulers"></i></span>
                                        <select id="unit" name="unit" class="form-select" aria-label="Unit selection">
                                            <option value="" selected hidden></option>
                                            <option value="pcs">pcs</option>
                                            <option value="box">box</option>
                                            <option value="kg">kg</option>
                                            <option value="litre">litre</option>
                                        </select>
                                        <label for="unit">Unit</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="vk-floating-input">
                                        <span class="vk-input-icon"><i class="bi bi-percent"></i></span>
                                        <select id="tax" name="tax" class="form-select" aria-label="Tax selection">
                                            <option value="" selected hidden></option>
                                            <option value="0">0% VAT</option>
                                            <option value="5">5% VAT</option>
                                            <option value="12">12% VAT</option>
                                            <option value="18">18% VAT</option>
                                        </select>
                                        <label for="tax">Tax rate</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="vk-card-section">
                            <h2>Classification & status</h2>
                            <p>Keep your product catalogue organized and control whether this item is active in your POS system.</p>
                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" id="status" name="status" checked>
                                <label class="form-check-label text-white" for="status">Active product</label>
                            </div>
                            <div class="vk-floating-input mb-3">
                                <span class="vk-input-icon"><i class="bi bi-tags"></i></span>
                                <input id="department" name="department" type="text" class="form-control" placeholder=" " maxlength="128" value="<?= e($_POST['department'] ?? '') ?>">
                                <label for="department">Department</label>
                            </div>
                            <div class="vk-floating-input mb-3">
                                <span class="vk-input-icon"><i class="bi bi-boxes"></i></span>
                                <input id="location" name="location" type="text" class="form-control" placeholder=" " maxlength="128" value="<?= e($_POST['location'] ?? '') ?>">
                                <label for="location">Storage location</label>
                            </div>
                        </div>

                        <div class="vk-card-section">
                            <h2>Save product</h2>
                            <p>When you save, the item will be added to the product list and ready for sale or stock management.</p>
                            <div class="vk-action-footer">
                                <button type="submit" class="btn btn-vk-primary btn-lg w-100" data-submit-button>
                                    <span class="spinner-border spinner-border-sm text-white d-none" role="status" aria-hidden="true"></span>
                                    Save product
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
