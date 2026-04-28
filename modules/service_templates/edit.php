<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once __DIR__ . '/service_image_upload.php';
require_once __DIR__ . '/service_template_location.php';

$allowedCat = ['printer', 'computer', 'cctv', 'general'];

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM service_templates WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    flash_set('error', 'Template not found.');
    redirect('/modules/service_templates/list.php');
}

$hasImageCol = db_column_exists($pdo, 'service_templates', 'image');
$hasThumbCol = db_column_exists($pdo, 'service_templates', 'image_thumb');
$hasGalleryTable = db_table_exists($pdo, 'service_images');
$hasLocationCol = db_column_exists($pdo, 'service_templates', 'latitude')
    && db_column_exists($pdo, 'service_templates', 'longitude');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($hasGalleryTable && isset($_POST['delete_gallery_image_id'])) {
        $gid = (int) ($_POST['delete_gallery_image_id'] ?? 0);
        if ($gid > 0) {
            $gst = $pdo->prepare('SELECT image_path, service_id FROM service_images WHERE id = ?');
            $gst->execute([$gid]);
            $g = $gst->fetch();
            if ($g && (int) $g['service_id'] === $id) {
                st_service_image_unlink((string) ($g['image_path'] ?? ''));
                $pdo->prepare('DELETE FROM service_images WHERE id = ?')->execute([$gid]);
                flash_set('success', 'Gallery image removed.');
            }
        }
        redirect('/modules/service_templates/edit.php?id=' . $id);
    }

    if ($hasGalleryTable && isset($_POST['add_gallery_image'])) {
        $hadG = !empty($_FILES['gallery_image']['name'])
            && (int) ($_FILES['gallery_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hadG) {
            $gCnt = st_service_gallery_count($pdo, $id);
            if ($gCnt >= ST_GALLERY_MAX) {
                flash_set('error', 'Maximum ' . ST_GALLERY_MAX . ' gallery images.');
            } else {
                $gres = st_service_gallery_process_upload($_FILES['gallery_image'], $id);
                if ($gres['error']) {
                    flash_set('error', $gres['error']);
                } elseif ($gres['path']) {
                    $ordSt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM service_images WHERE service_id = ?');
                    $ordSt->execute([$id]);
                    $nextOrder = (int) $ordSt->fetchColumn();
                    $pdo->prepare('INSERT INTO service_images (service_id, image_path, caption, is_primary, sort_order) VALUES (?,?,NULL,0,?)')
                        ->execute([$id, $gres['path'], $nextOrder]);
                    flash_set('success', 'Gallery image added.');
                }
            }
        }
        redirect('/modules/service_templates/edit.php?id=' . $id);
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $category = (string) ($_POST['category'] ?? 'general');
    $amount = max(0, (float) ($_POST['default_amount'] ?? 0));
    $desc = trim((string) ($_POST['description'] ?? ''));
    if (!in_array($category, $allowedCat, true)) {
        $category = 'general';
    }

    $locParsed = ['lat' => null, 'lng' => null, 'address' => null, 'error' => null];
    if ($hasLocationCol) {
        $locParsed = st_service_template_parse_location($_POST);
    }

    $imageUploadError = null;
    $newMain = $hasImageCol ? (string) ($row['image'] ?? '') : '';
    $newThumb = ($hasImageCol && $hasThumbCol) ? (string) ($row['image_thumb'] ?? '') : '';
    $imageChanged = false;

    if ($hasImageCol) {
        $remove = isset($_POST['remove_service_image']) && (string) $_POST['remove_service_image'] === '1';
        $hadFile = !empty($_FILES['service_image']['name'])
            && (int) ($_FILES['service_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hadFile) {
            $res = st_service_image_process_upload($_FILES['service_image'], $id);
            if ($res['error']) {
                $imageUploadError = $res['error'];
            } elseif ($res['main'] !== null) {
                st_service_image_unlink_pair($newMain !== '' ? $newMain : null, $newThumb !== '' ? $newThumb : null);
                $newMain = $res['main'];
                $newThumb = $res['thumb'] ?? '';
                $imageChanged = true;
            }
        } elseif ($remove) {
            st_service_image_unlink_pair($newMain !== '' ? $newMain : null, $newThumb !== '' ? $newThumb : null);
            $newMain = '';
            $newThumb = '';
            $imageChanged = true;
        }
    }

    if ($name === '') {
        flash_set('error', 'Name is required.');
    } elseif ($imageUploadError !== null) {
        flash_set('error', $imageUploadError);
    } elseif ($hasLocationCol && $locParsed['error'] !== null) {
        flash_set('error', $locParsed['error']);
    } else {
        $sets = ['name=?', 'category=?', 'default_amount=?', 'description=?'];
        $params = [$name, $category, $amount, $desc ?: null];
        if ($hasImageCol && $hasThumbCol) {
            $sets[] = 'image=?';
            $sets[] = 'image_thumb=?';
            $params[] = $newMain !== '' ? $newMain : null;
            $params[] = $newThumb !== '' ? $newThumb : null;
        } elseif ($hasImageCol) {
            $sets[] = 'image=?';
            $params[] = $newMain !== '' ? $newMain : null;
        }
        if ($hasLocationCol) {
            $sets[] = 'latitude=?';
            $sets[] = 'longitude=?';
            $sets[] = 'address=?';
            $params[] = $locParsed['lat'];
            $params[] = $locParsed['lng'];
            $params[] = $locParsed['address'];
        }
        $params[] = $id;
        $pdo->prepare('UPDATE service_templates SET ' . implode(', ', $sets) . ' WHERE id=?')->execute($params);
        $msg = 'Template updated.';
        if ($hasImageCol && $imageChanged) {
            $msg = ($newMain !== '') ? 'Template updated. Images optimized and saved (hero + thumbnail).' : 'Template updated. Images removed.';
        }
        flash_set('success', $msg);
        redirect('/modules/service_templates/list.php');
    }
    $st->execute([$id]);
    $row = $st->fetch();
}

$galleryRows = [];
if ($hasGalleryTable) {
    $gSt = $pdo->prepare('SELECT id, image_path, caption, sort_order FROM service_images WHERE service_id = ? ORDER BY sort_order ASC, id ASC');
    $gSt->execute([$id]);
    $galleryRows = $gSt->fetchAll(PDO::FETCH_ASSOC);
}

$extraHead = '<link href="' . e(BASE_URL) . '/assets/css/service-templates.css" rel="stylesheet">';
$extraScripts = '<script src="' . e(BASE_URL) . '/assets/js/service-template-form.js" defer></script>';
if ($hasLocationCol) {
    $extraHead .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">';
    $extraHead .= '<link href="' . e(BASE_URL) . '/assets/css/service-location.css" rel="stylesheet">';
    $extraScripts .= '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous" defer></script>';
    $extraScripts .= '<script src="' . e(BASE_URL) . '/assets/js/service-location-admin.js" defer></script>';
}

$pageTitle = 'Edit service template';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$currentImage = $hasImageCol ? (string) ($row['image'] ?? '') : '';
$galleryCount = count($galleryRows);
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/service_templates/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Edit template</h1>
<div class="mx-auto" style="max-width: 800px;">
    <div class="card vk-card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" data-loading data-st-service-form id="st-main-form">
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <div class="mb-3">
                    <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                    <input class="form-control" id="name" name="name" required maxlength="255" value="<?= e($_POST['name'] ?? $row['name']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="category">Category</label>
                    <select class="form-select" name="category" id="category">
                        <?php foreach (['printer' => 'Printer', 'computer' => 'Computer', 'cctv' => 'CCTV', 'general' => 'General'] as $k => $lab): ?>
                            <option value="<?= e($k) ?>" <?= ($_POST['category'] ?? $row['category']) === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="default_amount">Default amount</label>
                    <input type="number" step="0.01" min="0" class="form-control" name="default_amount" id="default_amount" value="<?= e((string) ($_POST['default_amount'] ?? $row['default_amount'])) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" name="description" id="description" rows="3" maxlength="512"><?= e($_POST['description'] ?? ($row['description'] ?? '')) ?></textarea>
                </div>

                <?php if ($hasLocationCol): ?>
                    <?php
                    $pvLat = $_POST['service_latitude'] ?? null;
                    $pvLng = $_POST['service_longitude'] ?? null;
                    $pvAddr = $_POST['service_address'] ?? null;
                    $dispLat = $pvLat !== null ? (string) $pvLat : st_format_coord_display($row['latitude'] ?? null);
                    $dispLng = $pvLng !== null ? (string) $pvLng : st_format_coord_display($row['longitude'] ?? null);
                    $dispAddr = $pvAddr !== null ? (string) $pvAddr : (string) ($row['address'] ?? '');
                    ?>
                    <div class="mb-4 st-loc-card">
                        <div class="px-3 pt-3 pb-0">
                            <label class="form-label fw-semibold mb-1">Service location</label>
                            <p class="small text-muted mb-2">Click the map to place a pin, or search with OpenStreetMap (Nominatim). Coordinates are saved with the template.</p>
                        </div>
                        <div class="st-loc-search-wrap">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                                <input type="text" class="form-control" id="st_loc_search" placeholder="Search location (e.g. Colombo, Sri Lanka)" autocomplete="off" aria-label="Search location">
                                <button type="button" class="btn btn-outline-primary" id="st_loc_search_btn">Search</button>
                            </div>
                            <p class="st-loc-nominatim-note mb-0 mt-1" id="st_loc_search_status">Uses Nominatim — please search responsibly.</p>
                        </div>
                        <div class="st-loc-map-wrap">
                            <div id="map" class="st-loc-map" role="application" aria-label="Map: click to set location"></div>
                        </div>
                        <div class="st-loc-coords row g-2 mx-0">
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="st_loc_lat">Latitude</label>
                                <input type="text" class="form-control form-control-sm" name="service_latitude" id="st_loc_lat" readonly value="<?= e($dispLat) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small mb-0" for="st_loc_lng">Longitude</label>
                                <input type="text" class="form-control form-control-sm" name="service_longitude" id="st_loc_lng" readonly value="<?= e($dispLng) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-0" for="st_loc_address">Address (optional)</label>
                                <textarea class="form-control form-control-sm" name="service_address" id="st_loc_address" rows="2" maxlength="2000" placeholder="Shown on the public page"><?= e($dispAddr) ?></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-2 pb-3">
                                <input type="hidden" name="clear_service_location" id="st_loc_clear_flag" value="0">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="st_loc_clear_btn">Clear map pin</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary small mb-4">
                        <strong>Optional:</strong> run <code>sql/upgrade_service_template_location.sql</code> to enable map-based service location.
                    </div>
                <?php endif; ?>

                <?php if ($hasImageCol): ?>
                    <div class="mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <label class="form-label fw-semibold mb-0">Main service image (hero &amp; cards)</label>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/service-template-detail.php?id=<?= (int) $id ?>" target="_blank" rel="noopener">Preview public page</a>
                        </div>
                        <p class="small text-muted mb-2">Recommended: <strong>1200×675</strong> (16:9). We auto-crop, resize to WebP, and build a <strong>400×300</strong> thumbnail for lists.</p>
                        <div class="alert alert-info py-2 px-3 small mb-3 st-aspect-hint" id="st-aspect-hint" role="status">
                            Please upload a <strong>16:9 image</strong> for the best hero and card display. Other ratios are center-cropped.
                        </div>
                        <div class="st-svc-img-wrap" data-st-service-image data-base-url="<?= e(BASE_URL) ?>" data-current="<?= e($currentImage) ?>" data-st-recommended="16:9">
                            <input type="hidden" name="remove_service_image" id="remove_service_image" value="0">
                            <input type="file" name="service_image" id="service_image_input" class="d-none" accept="image/jpeg,image/png,image/webp,image/*">
                            <div class="st-svc-img-dropzone st-glass-dz" id="stSvcImgDropzone" role="button" tabindex="0" aria-label="Upload main service image">
                                <div class="st-svc-img-spinner d-none" id="stSvcImgSpinner" aria-hidden="true">
                                    <div class="spinner-border text-primary" role="status"></div>
                                    <span class="small text-muted mt-2">Optimizing image…</span>
                                </div>
                                <div class="st-svc-img-placeholder" id="stSvcImgPlaceholder">
                                    <i class="bi bi-cloud-arrow-up st-svc-img-icon" aria-hidden="true"></i>
                                    <p class="st-svc-img-title">Drag &amp; drop or click to upload</p>
                                    <p class="st-svc-img-hint">JPG, PNG, WebP · max 2MB · saved as optimized WebP</p>
                                    <p class="st-svc-img-meta small text-muted mb-0">Output: 1200×675 hero + 400×300 thumbnail</p>
                                </div>
                                <div class="st-svc-img-preview d-none" id="stSvcImgPreview">
                                    <img src="" alt="" id="stSvcImgPreviewImg" width="1200" height="675" loading="lazy">
                                    <div class="st-svc-img-actions">
                                        <button type="button" class="btn btn-sm btn-light st-svc-img-replace" id="stSvcImgReplace" title="Replace image"><i class="bi bi-arrow-repeat me-1"></i>Replace</button>
                                        <button type="button" class="btn btn-sm btn-outline-danger st-svc-img-remove" id="stSvcImgRemove" title="Remove image"><i class="bi bi-trash me-1"></i>Remove</button>
                                    </div>
                                    <p class="st-svc-img-filename small text-muted mb-0 mt-2" id="stSvcImgFilename"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning small mb-4">
                        <strong>Database upgrade:</strong> run <code>sql/upgrade_service_template_image_column.sql</code> and <code>sql/upgrade_service_template_image_thumb.sql</code> for full image support.
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-primary">Save</button>
            </form>

            <?php if ($hasGalleryTable && $hasImageCol): ?>
                <hr class="my-4">
                <h2 class="h6 fw-semibold mb-2">Gallery (optional)</h2>
                <p class="small text-muted mb-3">Up to <?= (int) ST_GALLERY_MAX ?> images · each saved as <strong>800×600</strong> WebP for the public gallery grid.</p>
                <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end mb-4">
                    <input type="hidden" name="add_gallery_image" value="1">
                    <div class="col-12 col-md-8">
                        <label class="form-label small mb-1">Add gallery image</label>
                        <input type="file" name="gallery_image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/*" required>
                    </div>
                    <div class="col-12 col-md-4">
                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Add to gallery</button>
                    </div>
                </form>
                <?php if ($galleryRows): ?>
                    <div class="row g-2">
                        <?php foreach ($galleryRows as $gr): ?>
                            <div class="col-6 col-md-4">
                                <div class="st-gallery-admin-card position-relative border rounded-3 overflow-hidden">
                                    <?php $gp = trim((string) ($gr['image_path'] ?? '')); ?>
                                    <?php if ($gp !== ''): ?>
                                        <div class="ratio ratio-4x3 bg-light">
                                            <img src="<?= e(BASE_URL) ?>/<?= e(ltrim($gp, '/')) ?>" alt="" class="object-fit-cover" loading="lazy">
                                        </div>
                                    <?php endif; ?>
                                    <form method="post" class="position-absolute top-0 end-0 p-1" onsubmit="return confirm('Remove this gallery image?');">
                                        <input type="hidden" name="delete_gallery_image_id" value="<?= (int) $gr['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-muted mt-2 mb-0"><?= (int) $galleryCount ?> / <?= (int) ST_GALLERY_MAX ?> images</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
