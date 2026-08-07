<?php
declare(strict_types=1);
?>
<aside class="pc-sidebar sticky-top" aria-label="Product wizard sidebar">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header pc-card-header">
            <h2 class="h6 mb-0">Live Preview</h2>
        </div>
        <div class="card-body">
            <div class="pc-preview-card">
                <div class="pc-preview-media ratio ratio-4x3 rounded mb-2" id="pcPreviewMedia">
                    <div class="pc-preview-placeholder d-flex align-items-center justify-content-center">
                        <i class="bi bi-image fs-1 text-secondary" aria-hidden="true"></i>
                    </div>
                </div>
                <h3 class="h6 mb-1" id="pcPreviewName">Untitled Product</h3>
                <p class="small text-secondary mb-1" id="pcPreviewSku">SKU: —</p>
                <p class="pc-preview-price fw-semibold mb-0" id="pcPreviewPrice">$0.00</p>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header pc-card-header d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0">Completion</h2>
            <span class="badge text-bg-primary" id="pcCompletionBadge"><?= (int) $completeness ?>%</span>
        </div>
        <div class="card-body pt-2">
            <div class="progress pc-progress mb-2" role="progressbar" aria-valuenow="<?= (int) $completeness ?>" aria-valuemin="0" aria-valuemax="100" id="pcCompletionBar">
                <div class="progress-bar" style="width: <?= (int) $completeness ?>%"></div>
            </div>
            <ul class="list-unstyled small mb-0 pc-checklist" id="pcChecklist">
                <li data-check="name"><i class="bi bi-circle"></i> Product name</li>
                <li data-check="sku"><i class="bi bi-circle"></i> SKU</li>
                <li data-check="category"><i class="bi bi-circle"></i> Category</li>
                <li data-check="price"><i class="bi bi-circle"></i> Selling price</li>
                <li data-check="stock"><i class="bi bi-circle"></i> Inventory</li>
                <li data-check="seo"><i class="bi bi-circle"></i> SEO meta</li>
            </ul>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header pc-card-header"><h2 class="h6 mb-0">Analytics</h2></div>
        <div class="card-body">
            <dl class="row small mb-0 pc-analytics">
                <dt class="col-7">Revenue Est.</dt>
                <dd class="col-5 text-end" id="pcAnRevenue">—</dd>
                <dt class="col-7">Profit Margin</dt>
                <dd class="col-5 text-end" id="pcAnMargin">—</dd>
                <dt class="col-7">Inventory Health</dt>
                <dd class="col-5 text-end" id="pcAnInventory">—</dd>
                <dt class="col-7">SEO Score</dt>
                <dd class="col-5 text-end" id="pcAnSeo">—</dd>
            </dl>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header pc-card-header"><h2 class="h6 mb-0">AI Assistant</h2></div>
        <div class="card-body d-grid gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary pc-ai-btn" data-ai="description">Generate Description</button>
            <button type="button" class="btn btn-sm btn-outline-secondary pc-ai-btn" data-ai="category">Suggest Category</button>
            <button type="button" class="btn btn-sm btn-outline-secondary pc-ai-btn" data-ai="tags">Suggest Tags</button>
            <button type="button" class="btn btn-sm btn-outline-secondary pc-ai-btn" data-ai="seo">Auto SEO</button>
            <button type="button" class="btn btn-sm btn-outline-secondary pc-ai-btn" data-ai="duplicate">Duplicate Detection</button>
            <button type="button" class="btn btn-sm btn-outline-secondary pc-ai-btn" data-ai="price">Price Recommendation</button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header pc-card-header"><h2 class="h6 mb-0">Quick Actions</h2></div>
        <div class="card-body d-grid gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pcQuickDuplicate">Duplicate Product</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pcQuickClone">Clone Fields</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pcQuickExport">Export PDF</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="pcQuickTemplate">Save Template</button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="pcQuickDeleteDraft">Delete Draft</button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header pc-card-header"><h2 class="h6 mb-0">Recent Activity</h2></div>
        <div class="card-body">
            <ul class="pc-timeline list-unstyled mb-0" id="pcActivityTimeline">
                <li class="pc-timeline-item"><span class="pc-timeline-dot"></span>Wizard opened</li>
            </ul>
        </div>
    </div>
</aside>
