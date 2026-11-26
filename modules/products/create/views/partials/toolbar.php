<?php
declare(strict_types=1);
?>
<header class="pc-toolbar card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
            <div class="flex-grow-1">
                <h1 class="pc-title h4 mb-1">Add Product</h1>
                <nav aria-label="Breadcrumb">
                    <ol class="breadcrumb pc-breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?= e(base_url('index.php')) ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?= e(base_url('modules/products/list.php')) ?>">Products</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Create Product</li>
                    </ol>
                </nav>
            </div>
            <div class="pc-toolbar-actions d-flex flex-wrap gap-2">
                <span class="small text-secondary">Create a product for your inventory</span>
            </div>
        </div>
        <?php if (!empty($savedAt)): ?>
        <p class="pc-draft-meta small text-secondary mb-0 mt-2">
            Draft saved <?= e(date('M j, Y g:i A', (int) $savedAt)) ?>
        </p>
        <?php endif; ?>
    </div>
</header>
