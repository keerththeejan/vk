<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

$hasGeo = db_column_exists($pdo, 'technicians', 'latitude')
    && db_column_exists($pdo, 'technicians', 'longitude');
$hasAvail = db_column_exists($pdo, 'technicians', 'availability');

$id = (int) ($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM technicians WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    flash_set('error', 'Technician not found.');
    redirect('/modules/technicians/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $spec = trim((string) ($_POST['specialization'] ?? ''));
    $active = isset($_POST['active']) ? 1 : 0;
    $latS = trim((string) ($_POST['latitude'] ?? ''));
    $lngS = trim((string) ($_POST['longitude'] ?? ''));
    $latOk = ($latS !== '' && is_numeric($latS)) ? round((float) $latS, 8) : null;
    $lngOk = ($lngS !== '' && is_numeric($lngS)) ? round((float) $lngS, 8) : null;
    $avail = (string) ($_POST['availability'] ?? 'available');
    if (!in_array($avail, ['available', 'busy'], true)) {
        $avail = 'available';
    }
    if ($name === '') {
        flash_set('error', 'Name is required.');
    } else {
        if ($hasGeo && $hasAvail) {
            $pdo->prepare(
                'UPDATE technicians SET name=?, phone=?, specialization=?, active=?, latitude=?, longitude=?, availability=? WHERE id=?'
            )->execute([$name, $phone ?: null, $spec ?: null, $active, $latOk, $lngOk, $avail, $id]);
        } elseif ($hasGeo) {
            $pdo->prepare(
                'UPDATE technicians SET name=?, phone=?, specialization=?, active=?, latitude=?, longitude=? WHERE id=?'
            )->execute([$name, $phone ?: null, $spec ?: null, $active, $latOk, $lngOk, $id]);
        } else {
            $pdo->prepare('UPDATE technicians SET name=?, phone=?, specialization=?, active=? WHERE id=?')
                ->execute([$name, $phone ?: null, $spec ?: null, $active, $id]);
        }
        flash_set('success', 'Technician updated.');
        redirect('/modules/technicians/list.php');
    }
    $st->execute([$id]);
    $row = $st->fetch();
}

$activeChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? isset($_POST['active'])
    : ((int) $row['active'] === 1);

$pageTitle = 'Edit technician';
$extraHead = $extraHead ?? '';
$extraScripts = $extraScripts ?? '';
if ($hasGeo) {
    $extraHead .= '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous"/>';
    $extraHead .= '<link href="' . e(BASE_URL) . '/assets/css/technician-map.css" rel="stylesheet">';
    $extraScripts .= '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>';
    $extraScripts .= '<script src="' . e(BASE_URL) . '/assets/js/technician-location-picker.js" defer></script>';
}
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="mb-3">
    <a href="<?= e(BASE_URL) ?>/modules/technicians/list.php" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
<h1 class="h3 mb-3">Edit technician</h1>
<div class="card vk-card" style="max-width: <?= $hasGeo ? '720px' : '560px' ?>;">
    <div class="card-body">
        <form method="post" data-loading>
            <div class="mb-3">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input class="form-control" id="name" name="name" required maxlength="128" value="<?= e($_POST['name'] ?? $row['name']) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="phone">Phone</label>
                <input class="form-control" id="phone" name="phone" maxlength="64" value="<?= e($_POST['phone'] ?? ($row['phone'] ?? '')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="specialization">Specialization</label>
                <input class="form-control" id="specialization" name="specialization" maxlength="128" value="<?= e($_POST['specialization'] ?? ($row['specialization'] ?? '')) ?>">
            </div>
            <?php
            $pvLat = $_POST['latitude'] ?? null;
            $pvLng = $_POST['longitude'] ?? null;
            $dispLat = $pvLat !== null ? (string) $pvLat : (string) ($row['latitude'] ?? '');
            $dispLng = $pvLng !== null ? (string) $pvLng : (string) ($row['longitude'] ?? '');
            $dispAvail = isset($_POST['availability']) ? (string) $_POST['availability'] : (string) ($row['availability'] ?? 'available');
            ?>
            <?php if ($hasGeo): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Base location (for smart booking)</label>
                    <p class="tech-loc-hint mb-2">Click map to set technician location — or search below. Coordinates fill automatically.</p>
                    <label class="visually-hidden" for="techLocationSearch">Search technician location</label>
                    <input type="text" class="form-control mb-2" id="techLocationSearch" placeholder="Search technician location..." autocomplete="off" aria-label="Search technician location">
                    <p class="small text-muted mb-2" id="techLocationSearchStatus">Uses OpenStreetMap search — use sparingly.</p>
                    <div class="tech-loc-map-card mb-3">
                        <div id="techMap" role="application" aria-label="Map: click to set base location"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small" for="latitude">Latitude</label>
                            <input class="form-control form-control-sm" name="latitude" id="latitude" inputmode="decimal" readonly tabindex="-1" value="<?= e($dispLat) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small" for="longitude">Longitude</label>
                            <input class="form-control form-control-sm" name="longitude" id="longitude" inputmode="decimal" readonly tabindex="-1" value="<?= e($dispLng) ?>">
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($hasAvail): ?>
                <div class="mb-3">
                    <label class="form-label" for="availability">Availability for auto-assign</label>
                    <select class="form-select" name="availability" id="availability">
                        <option value="available" <?= $dispAvail === 'available' ? 'selected' : '' ?>>Available</option>
                        <option value="busy" <?= $dispAvail === 'busy' ? 'selected' : '' ?>>Busy (skip auto-assign)</option>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="active" id="active" value="1" <?= $activeChecked ? 'checked' : '' ?>>
                <label class="form-check-label" for="active">Active</label>
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
