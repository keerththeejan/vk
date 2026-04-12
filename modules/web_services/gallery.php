<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$pageTitle = 'Service gallery';
$extraHead = '<link href="' . e(BASE_URL) . '/assets/css/service-gallery-admin.css" rel="stylesheet">';
$extraHead .= '<style id="hoverfx">#gridcss .vk-sg-admin-card:hover .vk-sg-thumb{transform:scale(1.05);transition:transform .3s ease}</style>';

if (!db_table_exists($pdo, 'web_services')) {
    flash_set('warning', 'web_services table is missing.');
    redirect('/modules/dashboard.php');
}
vk_service_gallery_auto_migrate($pdo);

$serviceId = max(0, (int) ($_GET['service_id'] ?? 0));

$services = $pdo->query('SELECT id, name, slug FROM web_services WHERE active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();
$servicesBrief = [];
foreach ($services as $svc) {
    $servicesBrief[] = [
        'id' => (int) $svc['id'],
        'name' => (string) $svc['name'],
        'slug' => (string) $svc['slug'],
    ];
}
$servicesJson = htmlspecialchars(
    json_encode($servicesBrief, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ENT_QUOTES,
    'UTF-8'
);

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Service gallery</h1>
        <p class="text-muted small mb-0">Responsive grid, lightbox, drag-and-drop uploads, filters. Files live in <code>uploads/services/gallery/</code>.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Public services</a>
        <a class="btn btn-outline-primary btn-sm <?= $serviceId <= 0 ? 'disabled pe-none' : '' ?>" id="vkSgPublicServiceLink" href="<?= $serviceId > 0 ? e(BASE_URL . '/service-details.php?id=' . (int) $serviceId) : '#' ?>" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>Service page</a>
    </div>
</div>

<div
    id="vkSgApp"
    class="vk-sg-admin"
    data-initial-service="<?= (int) $serviceId ?>"
    data-services="<?= $servicesJson ?>"
    data-per-page="12"
>
    <div class="card vk-card mb-3">
        <div class="card-body">
            <div class="row g-3 vk-sg-admin-toolbar align-items-end">
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgFilterService">Service</label>
                    <select class="form-select form-select-sm" id="vkSgFilterService">
                        <option value="0">All services</option>
                        <?php foreach ($services as $svc): ?>
                            <option value="<?= (int) $svc['id'] ?>" <?= (int) $svc['id'] === $serviceId ? 'selected' : '' ?>>
                                <?= e((string) $svc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgSort">Sort</label>
                    <select class="form-select form-select-sm" id="vkSgSort">
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label" for="vkSgSearch">Search</label>
                    <input type="search" class="form-control form-control-sm" id="vkSgSearch" placeholder="Title, file, or service name" autocomplete="off">
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label" for="vkSgDateFrom">From</label>
                    <input type="date" class="form-control form-control-sm" id="vkSgDateFrom">
                </div>
                <div class="col-6 col-md-2 col-lg-2">
                    <label class="form-label" for="vkSgDateTo">To</label>
                    <input type="date" class="form-control form-control-sm" id="vkSgDateTo">
                </div>
                <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-sm" id="vkSgApplyFilters"><i class="bi bi-funnel me-1"></i>Apply</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="vkSgResetFilters">Reset</button>
                    <button type="button" class="btn btn-outline-dark btn-sm" id="vkSgBulkToggle" title="Select multiple images">Bulk</button>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="vkSgBulkDelete"><i class="bi bi-trash me-1"></i>Delete selected</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card vk-card mb-3">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-muted fw-semibold small mb-3">Upload images</h2>
            <p class="small text-muted mb-2" id="vkSgUploadHint">Select a specific service above (not “All services”) to enable uploads.</p>
            <div class="vk-sg-dropzone position-relative mb-3" id="vkSgDropzone" tabindex="0" role="button" aria-label="Drop images here or click to browse">
                <input type="file" id="vkSgFileInput" accept="image/jpeg,image/png,image/webp" multiple>
                <i class="bi bi-cloud-arrow-up fs-1 text-primary d-block mb-2"></i>
                <div class="fw-semibold">Drag &amp; drop images here</div>
                <div class="text-muted small">or click to browse · JPG, PNG, WebP · max 3MB each</div>
            </div>
            <div class="vk-sg-upload-queue small" id="vkSgUploadQueue" hidden></div>
        </div>
    </div>

    <div id="vkSgEmpty" class="alert alert-info d-none mb-3" role="status">No images match your filters.</div>

    <div id="gridcss" class="mb-3" aria-live="polite"></div>

    <div class="text-center mb-4 d-none" id="vkSgLoadMoreWrap">
        <button type="button" class="btn btn-outline-primary" id="vkSgLoadMore">Load more</button>
        <div class="text-muted small mt-2" id="vkSgResultMeta"></div>
    </div>
</div>

<!-- Lightbox -->
<div class="modal fade" id="vkSgLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary py-2">
                <div class="me-auto overflow-hidden">
                    <h2 class="h6 mb-0 text-truncate" id="vkSgLightboxTitle">Preview</h2>
                    <div class="small text-secondary text-truncate" id="vkSgLightboxMeta"></div>
                </div>
                <div class="btn-group btn-group-sm me-2" role="group" aria-label="Zoom">
                    <button type="button" class="btn btn-outline-light" id="vkSgLbZoomOut" title="Zoom out">−</button>
                    <button type="button" class="btn btn-outline-light" id="vkSgLbZoomReset" title="Reset zoom">100%</button>
                    <button type="button" class="btn btn-outline-light" id="vkSgLbZoomIn" title="Zoom in">+</button>
                </div>
                <div class="btn-group btn-group-sm me-2" role="group" aria-label="Navigate">
                    <button type="button" class="btn btn-outline-light" id="vkSgLbPrev" title="Previous"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-light" id="vkSgLbNext" title="Next"><i class="bi bi-chevron-right"></i></button>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 vk-sg-lightbox-img-wrap d-flex align-items-center justify-content-center">
                <img src="" alt="" id="vkSgLightboxImg" class="vk-sg-lazy" loading="lazy">
            </div>
        </div>
    </div>
</div>

<!-- Delete confirm -->
<div class="modal fade" id="vkSgDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title">Delete image?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this image? This cannot be undone.</p>
                <input type="hidden" id="vkSgDeleteTargetId" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="vkSgDeleteConfirmBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit title -->
<div class="modal fade" id="vkSgEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title">Edit caption</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="vkSgEditId" value="">
                <label class="form-label" for="vkSgEditTitle">Title / caption</label>
                <input type="text" class="form-control" id="vkSgEditTitle" maxlength="255">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="vkSgEditSave">Save</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(BASE_URL) . '/assets/js/service-gallery-admin.js" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
