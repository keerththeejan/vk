<?php
declare(strict_types=1);
/** @var array<string, mixed> $form */
/** @var array<string, array<int, array<string, mixed>>> $lookups */
/** @var int $completeness */
/** @var int|null $savedAt */
?>
<div class="pc-root pc-simple" id="productCreateRoot" data-completeness="<?= (int) $completeness ?>">
    <?php require __DIR__ . '/partials/toolbar.php'; ?>
    <form id="productCreateForm" class="pc-form" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="intent" id="pcIntent" value="publish">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="pc-simple-card">
            <section class="pc-simple-section">
                <h2>Basic information</h2>
                <?php require __DIR__ . '/steps/step-01-information.php'; ?>
            </section>
            <section class="pc-simple-section">
                <h2>Pricing</h2>
                <?php require __DIR__ . '/steps/step-02-pricing.php'; ?>
            </section>
            <section class="pc-simple-section">
                <h2>Inventory</h2>
                <?php require __DIR__ . '/steps/step-03-inventory.php'; ?>
            </section>
            <section class="pc-simple-section">
                <h2>Product image</h2>
                <?php require __DIR__ . '/steps/step-04-media.php'; ?>
            </section>
            <details class="pc-simple-optional">
                <summary>Additional information (optional)</summary>
                <div class="pc-simple-section">
                    <?php require __DIR__ . '/steps/step-05-shipping.php'; ?>
                    <?php require __DIR__ . '/steps/step-06-seo.php'; ?>
                    <?php require __DIR__ . '/steps/step-07-warranty.php'; ?>
                    <?php require __DIR__ . '/steps/step-08-variants.php'; ?>
                </div>
            </details>
        </div>
        <div class="pc-simple-actions">
            <a class="btn btn-outline-secondary" href="<?= e(base_url('modules/products/list.php')) ?>">Cancel</a>
            <button type="button" class="btn btn-outline-primary" data-intent="publish_new">Save &amp; Add Another</button>
            <button type="button" class="btn btn-outline-primary" data-intent="publish_view">Save &amp; View Product</button>
            <button type="button" class="btn btn-primary" data-intent="publish"><i class="bi bi-check2-circle me-1"></i>Save Product</button>
        </div>
    </form>
    <div class="pc-toast-host position-fixed bottom-0 end-0 p-3" id="pcToastHost" aria-live="polite" aria-atomic="true"></div>
    <div class="pc-loading-bar" id="pcLoadingBar" hidden aria-hidden="true"></div>
</div>
