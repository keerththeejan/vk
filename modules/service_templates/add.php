<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once __DIR__ . '/service_image_upload.php';
require_once __DIR__ . '/service_template_location.php';

$allowedCat = ['printer', 'computer', 'cctv', 'general'];
$hasImageCol = db_column_exists($pdo, 'service_templates', 'image');
$hasThumbCol = db_column_exists($pdo, 'service_templates', 'image_thumb');
$hasLocationCol = db_column_exists($pdo, 'service_templates', 'latitude')
    && db_column_exists($pdo, 'service_templates', 'longitude');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $hadFile = $hasImageCol && !empty($_FILES['service_image']['name'])
        && (int) ($_FILES['service_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($name === '') {
        flash_set('error', 'Name is required.');
    } elseif ($hasLocationCol && $locParsed['error'] !== null) {
        flash_set('error', $locParsed['error']);
    } else {
        $cols = ['name', 'category', 'default_amount', 'description'];
        $vals = [$name, $category, $amount, $desc ?: null];
        if ($hasLocationCol) {
            $cols[] = 'latitude';
            $cols[] = 'longitude';
            $cols[] = 'address';
            $vals[] = $locParsed['lat'];
            $vals[] = $locParsed['lng'];
            $vals[] = $locParsed['address'];
        }
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $pdo->prepare('INSERT INTO service_templates (' . implode(',', $cols) . ") VALUES ($ph)")->execute($vals);
        $newId = (int) $pdo->lastInsertId();
        $msg = 'Template saved.';
        if ($hasImageCol && $hadFile) {
            $res = st_service_image_process_upload($_FILES['service_image'], $newId);
            if ($res['error']) {
                flash_set('warning', 'Template saved, but image failed: ' . $res['error']);
                redirect('/modules/service_templates/edit.php?id=' . $newId);
            }
            if ($res['main'] !== null) {
                if ($hasThumbCol) {
                    $pdo->prepare('UPDATE service_templates SET image=?, image_thumb=? WHERE id=?')
                        ->execute([$res['main'], $res['thumb'] ?? null, $newId]);
                } else {
                    $pdo->prepare('UPDATE service_templates SET image=? WHERE id=?')
                        ->execute([$res['main'], $newId]);
                }
                $msg = 'Template saved. Images optimized and uploaded.';
            }
        }
        flash_set('success', $msg);
        redirect('/modules/service_templates/edit.php?id=' . $newId);
    }
}

$extraHead = '<link href="' . e(BASE_URL) . '/assets/css/service-templates.css" rel="stylesheet">';
$extraScripts = '<script src="' . e(BASE_URL) . '/assets/js/service-template-form.js" defer></script>';
if ($hasLocationCol) {
    $extraHead .= '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">';
    $extraHead .= '<link href="' . e(BASE_URL) . '/assets/css/service-location.css" rel="stylesheet">';
    $extraScripts .= '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous" defer></script>';
    $extraScripts .= '<script src="' . e(BASE_URL) . '/assets/js/service-location-admin.js" defer></script>';
}

$pageTitle = 'Add service template';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/service_templates/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Add service template</h1>
<div class="card vk-card" style="max-width: 800px;">
    <div class="card-body">
        <form method="post" enctype="multipart/form-data" data-loading data-st-service-form>
            <div class="mb-3">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input class="form-control" id="name" name="name" required maxlength="255" value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="category">Category</label>
                <select class="form-select" name="category" id="category">
                    <?php foreach (['printer' => 'Printer', 'computer' => 'Computer', 'cctv' => 'CCTV', 'general' => 'General'] as $k => $lab): ?>
                        <option value="<?= e($k) ?>" <?= ($_POST['category'] ?? 'general') === $k ? 'selected' : '' ?>><?= e($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="default_amount">Default amount</label>
                <input type="number" step="0.01" min="0" class="form-control" name="default_amount" id="default_amount" value="<?= e($_POST['default_amount'] ?? '0') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" name="description" id="description" rows="3" maxlength="512"><?= e($_POST['description'] ?? '') ?></textarea>
            </div>

            <?php if ($hasLocationCol): ?>
                <div class="mb-4 st-loc-card">
                    <div class="px-3 pt-3 pb-0">
                        <label class="form-label fw-semibold mb-1">Service location</label>
                        <p class="small text-muted mb-2">Default map: Sri Lanka. Click to set a pin or search below.</p>
                    </div>
                    <div class="st-loc-search-wrap">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                            <input type="text" class="form-control" id="st_loc_search" placeholder="Search location" autocomplete="off" aria-label="Search location">
                            <button type="button" class="btn btn-outline-primary" id="st_loc_search_btn">Search</button>
                        </div>
                        <p class="st-loc-nominatim-note mb-0 mt-1" id="st_loc_search_status">Nominatim search — use sparingly.</p>
                    </div>
                    <div class="st-loc-map-wrap">
                        <div id="map" class="st-loc-map" role="application" aria-label="Map: click to set location"></div>
                    </div>
                    <div class="st-loc-coords row g-2 mx-0">
                        <div class="col-md-6">
                            <label class="form-label small mb-0" for="st_loc_lat">Latitude</label>
                            <input type="text" class="form-control form-control-sm" name="service_latitude" id="st_loc_lat" readonly value="<?= e((string) ($_POST['service_latitude'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-0" for="st_loc_lng">Longitude</label>
                            <input type="text" class="form-control form-control-sm" name="service_longitude" id="st_loc_lng" readonly value="<?= e((string) ($_POST['service_longitude'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-0" for="st_loc_address">Address (optional)</label>
                            <textarea class="form-control form-control-sm" name="service_address" id="st_loc_address" rows="2" maxlength="2000" placeholder="Public display"><?= e((string) ($_POST['service_address'] ?? '')) ?></textarea>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 pb-3">
                            <input type="hidden" name="clear_service_location" id="st_loc_clear_flag" value="0">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="st_loc_clear_btn">Clear map pin</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasImageCol): ?>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Main service image</label>
                    <p class="small text-muted mb-2">Recommended <strong>16:9</strong> (1200×675). We output WebP hero + thumbnail.</p>
                    <div class="st-svc-img-wrap" data-st-service-image data-base-url="<?= e(BASE_URL) ?>" data-current="">
                        <input type="hidden" name="remove_service_image" id="remove_service_image" value="0">
                        <input type="file" name="service_image" id="service_image_input" class="d-none" accept="image/jpeg,image/png,image/webp,image/*">
                        <div class="st-svc-img-dropzone st-glass-dz" id="stSvcImgDropzone" role="button" tabindex="0" aria-label="Upload main service image">
                            <div class="st-svc-img-spinner d-none" id="stSvcImgSpinner" aria-hidden="true">
                                <div class="spinner-border text-primary" role="status"></div>
                            </div>
                            <div class="st-svc-img-placeholder" id="stSvcImgPlaceholder">
                                <i class="bi bi-cloud-arrow-up st-svc-img-icon" aria-hidden="true"></i>
                                <p class="st-svc-img-title">Drag &amp; drop or click to upload</p>
                                <p class="st-svc-img-hint">JPG, PNG, WebP · max 2MB</p>
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
                    Run <code>sql/upgrade_service_template_image_column.sql</code> to enable images.
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">Save</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
