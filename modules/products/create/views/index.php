<?php
declare(strict_types=1);
/** @var array<string, mixed> $form */
/** @var array<string, array<int, array<string, mixed>>> $lookups */
/** @var int $completeness */
/** @var int|null $savedAt */
?>
<div class="pc-root" id="productCreateRoot" data-completeness="<?= (int) $completeness ?>">
    <?php require __DIR__ . '/partials/toolbar.php'; ?>

    <div class="pc-layout row g-3 g-xl-4">
        <div class="col-12 col-xl-8 col-xxl-9">
            <form id="productCreateForm" class="pc-form" method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="intent" id="pcIntent" value="publish">
                <div class="pc-wizard accordion" id="pcWizard">
                    <?php
                    $steps = [
                        ['id' => '01', 'file' => 'step-01-information.php', 'title' => 'Product Information', 'icon' => 'bi-info-circle'],
                        ['id' => '02', 'file' => 'step-02-pricing.php', 'title' => 'Pricing', 'icon' => 'bi-currency-dollar'],
                        ['id' => '03', 'file' => 'step-03-inventory.php', 'title' => 'Inventory', 'icon' => 'bi-box-seam'],
                        ['id' => '04', 'file' => 'step-04-media.php', 'title' => 'Media Center', 'icon' => 'bi-images'],
                        ['id' => '05', 'file' => 'step-05-shipping.php', 'title' => 'Shipping', 'icon' => 'bi-truck'],
                        ['id' => '06', 'file' => 'step-06-seo.php', 'title' => 'SEO', 'icon' => 'bi-search'],
                        ['id' => '07', 'file' => 'step-07-warranty.php', 'title' => 'Warranty', 'icon' => 'bi-shield-check'],
                        ['id' => '08', 'file' => 'step-08-variants.php', 'title' => 'Variants', 'icon' => 'bi-layers'],
                        ['id' => '09', 'file' => 'step-09-review.php', 'title' => 'Review & Publish', 'icon' => 'bi-check2-circle'],
                    ];
                    foreach ($steps as $i => $step):
                        $open = $i === 0 ? 'show' : '';
                        $expanded = $i === 0 ? 'true' : 'false';
                        $collapsed = $i === 0 ? '' : 'collapsed';
                    ?>
                    <div class="accordion-item pc-step" data-step="<?= e($step['id']) ?>">
                        <h2 class="accordion-header" id="pcHead<?= e($step['id']) ?>">
                            <button class="accordion-button <?= e($collapsed) ?>" type="button" data-bs-toggle="collapse" data-bs-target="#pcPanel<?= e($step['id']) ?>" aria-expanded="<?= e($expanded) ?>" aria-controls="pcPanel<?= e($step['id']) ?>">
                                <span class="pc-step-badge"><?= e($step['id']) ?></span>
                                <i class="bi <?= e($step['icon']) ?> me-2" aria-hidden="true"></i>
                                <span><?= e($step['title']) ?></span>
                                <span class="pc-step-status ms-auto me-2" aria-hidden="true"></span>
                            </button>
                        </h2>
                        <div id="pcPanel<?= e($step['id']) ?>" class="accordion-collapse collapse <?= e($open) ?>" aria-labelledby="pcHead<?= e($step['id']) ?>" data-bs-parent="#pcWizard">
                            <div class="accordion-body">
                                <?php require __DIR__ . '/steps/' . $step['file']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </form>
        </div>
        <div class="col-12 col-xl-4 col-xxl-3">
            <?php require __DIR__ . '/partials/sidebar.php'; ?>
        </div>
    </div>

    <div class="pc-toast-host position-fixed bottom-0 end-0 p-3" id="pcToastHost" aria-live="polite" aria-atomic="true"></div>
    <div class="pc-loading-bar" id="pcLoadingBar" hidden aria-hidden="true"></div>
</div>
