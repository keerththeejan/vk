<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/service_gallery_admin_service.php';

$pageTitle = 'Gallery Management';
$perms = vk_sg_admin_require($pdo);

if (!db_table_exists($pdo, 'web_services')) {
    flash_set('warning', 'web_services table is missing.');
    redirect('/modules/dashboard.php');
}
vk_service_gallery_auto_migrate($pdo);

$serviceId = max(0, (int) ($_GET['service_id'] ?? 0));
$albums = vk_sg_admin_albums($pdo);
$categories = vk_service_gallery_categories();
$csrf = csrf_token();
$apiUrl = base_url('api/service_gallery.php');
$permsJson = htmlspecialchars(json_encode($perms, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
$albumsJson = htmlspecialchars(json_encode($albums, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');

$cssV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/css/service-gallery-admin.css');
$jsV = (string) @filemtime(dirname(__DIR__, 2) . '/assets/js/service-gallery-admin.js');
$extraHead = '<link href="' . e(base_url('assets/css/service-gallery-admin.css')) . '?v=' . e($cssV) . '" rel="stylesheet">';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div
    id="vkSgApp"
    class="vk-sg-admin"
    data-initial-service="<?= (int) $serviceId ?>"
    data-api-url="<?= e($apiUrl) ?>"
    data-csrf="<?= e($csrf) ?>"
    data-permissions="<?= $permsJson ?>"
    data-albums="<?= $albumsJson ?>"
    data-per-page="20"
>
    <section class="vk-sg-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-primary fw-semibold small text-uppercase">Media infrastructure</div>
                <h1 class="h2 mb-1">Gallery Management</h1>
                <p class="text-muted mb-0">Secure uploads, albums, optimization, bulk tools, and lightbox preview.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= e(BASE_URL) ?>/index.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Public site</a>
                <a class="btn btn-outline-primary btn-sm <?= $serviceId <= 0 ? 'disabled pe-none' : '' ?>" id="vkSgPublicServiceLink" href="<?= $serviceId > 0 ? e(BASE_URL . '/service-details.php?id=' . (int) $serviceId) : '#' ?>" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>Album page</a>
                <?php if ($perms['can_export']): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="vkSgExportCsv"><i class="bi bi-download me-1"></i>Export CSV</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="vkSgExportJson"><i class="bi bi-braces me-1"></i>Export JSON</button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="card vk-card mb-3 vk-sg-toolbar-sticky">
        <div class="card-body py-3">
            <div class="row g-2 g-md-3 vk-sg-admin-toolbar align-items-end">
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgFilterService">Album / Service</label>
                    <select class="form-select form-select-sm" id="vkSgFilterService">
                        <option value="0">All albums</option>
                        <?php foreach ($albums as $alb): ?>
                        <option value="<?= (int) $alb['id'] ?>" <?= (int) $alb['id'] === $serviceId ? 'selected' : '' ?>>
                            <?= e((string) $alb['name']) ?> (<?= (int) $alb['image_count'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgFilterCategory">Category</label>
                    <select class="form-select form-select-sm" id="vkSgFilterCategory">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>"><?= e(ucwords(str_replace('-', ' ', $cat))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgFilterStatus">Status</label>
                    <select class="form-select form-select-sm" id="vkSgFilterStatus">
                        <option value="">All statuses</option>
                        <option value="published">Published</option>
                        <option value="hidden">Hidden</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgFilterFeatured">Featured</label>
                    <select class="form-select form-select-sm" id="vkSgFilterFeatured">
                        <option value="">Any</option>
                        <option value="1">Featured only</option>
                        <option value="0">Not featured</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgSort">Sort</label>
                    <select class="form-select form-select-sm" id="vkSgSort">
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgPerPage">Per page</label>
                    <select class="form-select form-select-sm" id="vkSgPerPage">
                        <option value="10">10</option>
                        <option value="20" selected>20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label" for="vkSgSearch">Search</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control" id="vkSgSearch" placeholder="Title, filename, uploader, description…" autocomplete="off">
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgDateFrom">From</label>
                    <input type="date" class="form-control form-control-sm" id="vkSgDateFrom">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label" for="vkSgDateTo">To</label>
                    <input type="date" class="form-control form-control-sm" id="vkSgDateTo">
                </div>
                <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-sm" id="vkSgApplyFilters"><i class="bi bi-funnel me-1"></i>Apply</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="vkSgResetFilters">Reset</button>
                    <?php if ($perms['can_bulk']): ?>
                    <button type="button" class="btn btn-outline-dark btn-sm" id="vkSgBulkToggle"><i class="bi bi-check2-square me-1"></i>Bulk</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($perms['can_bulk']): ?>
            <div class="d-flex flex-wrap gap-2 mt-3 d-none" id="vkSgBulkBar">
                <select class="form-select form-select-sm w-auto" id="vkSgBulkAction">
                    <option value="">Bulk action…</option>
                    <?php if ($perms['can_delete']): ?><option value="delete">Delete</option><?php endif; ?>
                    <option value="publish">Publish</option>
                    <option value="hide">Hide</option>
                    <option value="draft">Mark draft</option>
                    <option value="feature">Feature</option>
                    <option value="unfeature">Unfeature</option>
                    <option value="move">Move album</option>
                    <option value="zip">Download ZIP</option>
                </select>
                <select class="form-select form-select-sm w-auto d-none" id="vkSgBulkMoveTarget">
                    <option value="">Move to album…</option>
                    <?php foreach ($albums as $alb): ?>
                    <option value="<?= (int) $alb['id'] ?>"><?= e((string) $alb['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-sm btn-primary" id="vkSgBulkRun">Apply to selected</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($perms['can_upload']): ?>
    <div class="card vk-card mb-3">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-muted fw-semibold small mb-3">Upload images</h2>
            <p class="small text-muted mb-2" id="vkSgUploadHint">Select an album to enable uploads.</p>
            <div class="vk-sg-dropzone position-relative mb-3" id="vkSgDropzone" tabindex="0" role="button" aria-label="Drop images here or click to browse">
                <input type="file" id="vkSgFileInput" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" multiple>
                <i class="bi bi-cloud-arrow-up fs-1 text-primary d-block mb-2"></i>
                <div class="fw-semibold">Drag &amp; drop images here</div>
                <div class="text-muted small">JPG, PNG, WebP, GIF, SVG · max 3MB · auto-resize &amp; thumbnails</div>
            </div>
            <div id="vkSgPreviewStrip" class="vk-sg-preview-strip d-none mb-3"></div>
            <div class="vk-sg-upload-queue small" id="vkSgUploadQueue" hidden></div>
        </div>
    </div>
    <?php endif; ?>

    <div id="vkSgEmpty" class="alert alert-info d-none mb-3" role="status">No images match your filters.</div>
    <div id="gridcss" class="mb-3" aria-live="polite"></div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div class="text-muted small" id="vkSgResultMeta"></div>
        <div class="btn-group btn-group-sm d-none" id="vkSgPager">
            <button type="button" class="btn btn-outline-secondary" id="vkSgPagePrev">Previous</button>
            <span class="btn btn-outline-secondary disabled" id="vkSgPageLabel">Page 1</span>
            <button type="button" class="btn btn-outline-secondary" id="vkSgPageNext">Next</button>
        </div>
    </div>
</div>

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
                <a class="btn btn-outline-light btn-sm me-2" id="vkSgLbDownload" href="#" download><i class="bi bi-download"></i></a>
                <div class="btn-group btn-group-sm me-2" role="group" aria-label="Navigate">
                    <button type="button" class="btn btn-outline-light" id="vkSgLbPrev"><i class="bi bi-chevron-left"></i></button>
                    <button type="button" class="btn btn-outline-light" id="vkSgLbLbFullscreen" title="Fullscreen"><i class="bi bi-arrows-fullscreen"></i></button>
                    <button type="button" class="btn btn-outline-light" id="vkSgLbNext"><i class="bi bi-chevron-right"></i></button>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 vk-sg-lightbox-img-wrap d-flex align-items-center justify-content-center" id="vkSgLbTouchArea">
                <img src="" alt="" id="vkSgLightboxImg" class="vk-sg-lazy" loading="lazy">
            </div>
            <div class="modal-footer border-secondary py-2 small" id="vkSgLightboxInfo"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="vkSgDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title">Delete image?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><p class="mb-0">This will remove the image and its optimized variants. Continue?</p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="vkSgDeleteConfirmBtn">Delete</button>
            </div>
            <input type="hidden" id="vkSgDeleteTargetId" value="">
        </div>
    </div>
</div>

<div class="modal fade" id="vkSgEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title">Edit image</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="vkSgEditId" value="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="vkSgEditTitle">Title</label>
                        <input type="text" class="form-control" id="vkSgEditTitle" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vkSgEditCategory">Category</label>
                        <select class="form-select" id="vkSgEditCategory">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= e($cat) ?>"><?= e(ucwords(str_replace('-', ' ', $cat))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="vkSgEditDescription">Description</label>
                        <textarea class="form-control" id="vkSgEditDescription" rows="2" maxlength="5000"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vkSgEditAlt">Alt text</label>
                        <input type="text" class="form-control" id="vkSgEditAlt" maxlength="255">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="vkSgEditSeo">SEO keywords</label>
                        <input type="text" class="form-control" id="vkSgEditSeo" maxlength="500">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="vkSgEditOrder">Display order</label>
                        <input type="number" class="form-control" id="vkSgEditOrder" min="0" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="vkSgEditStatus">Visibility</label>
                        <select class="form-select" id="vkSgEditStatus">
                            <option value="published">Published</option>
                            <option value="hidden">Hidden</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="vkSgEditFeatured">
                            <label class="form-check-label" for="vkSgEditFeatured">Featured</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="vkSgEditSave">Save changes</button>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script src="' . e(base_url('assets/js/service-gallery-admin.js')) . '?v=' . e($jsV) . '" defer></script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
