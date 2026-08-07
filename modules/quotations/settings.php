<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_once dirname(__DIR__, 2) . '/includes/quotations_service.php';
vk_ensure_quotations_schema($pdo);
vk_quotation_require_perm('settings');

$knownKeys = [
    'prefix' => ['label' => 'Quotation prefix', 'type' => 'text'],
    'default_validity_days' => ['label' => 'Default validity (days)', 'type' => 'number'],
    'default_currency' => ['label' => 'Default currency', 'type' => 'text'],
    'default_tax_pct' => ['label' => 'Default tax %', 'type' => 'number'],
    'default_tax_method' => ['label' => 'Default tax method', 'type' => 'select', 'options' => ['exclusive', 'inclusive', 'none']],
    'require_approval' => ['label' => 'Require approval', 'type' => 'checkbox'],
    'approval_levels' => ['label' => 'Approval levels (comma-separated)', 'type' => 'text'],
    'auto_expire' => ['label' => 'Auto-expire quotations', 'type' => 'checkbox'],
    'letterhead_path' => ['label' => 'Letterhead image path', 'type' => 'text'],
    'signature_path' => ['label' => 'Digital signature path', 'type' => 'text'],
    'stamp_path' => ['label' => 'Company stamp path', 'type' => 'text'],
    'bank_name' => ['label' => 'Bank name', 'type' => 'text'],
    'bank_account_name' => ['label' => 'Account name', 'type' => 'text'],
    'bank_account_number' => ['label' => 'Account number', 'type' => 'text'],
    'bank_branch' => ['label' => 'Bank branch', 'type' => 'text'],
    'whatsapp_template' => ['label' => 'WhatsApp template', 'type' => 'textarea'],
    'email_subject' => ['label' => 'Email subject template', 'type' => 'text'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        flash_set('error', 'Security token expired.');
        redirect('/modules/quotations/settings.php');
    }
    foreach ($knownKeys as $key => $meta) {
        if ($meta['type'] === 'checkbox') {
            vk_quotation_setting_set($pdo, $key, isset($_POST[$key]) ? '1' : '0');
        } elseif (isset($_POST[$key])) {
            vk_quotation_setting_set($pdo, $key, trim((string) $_POST[$key]));
        }
    }
    foreach ($_POST as $pk => $pv) {
        if (!is_string($pk) || str_starts_with($pk, '_') || isset($knownKeys[$pk])) {
            continue;
        }
        if (is_string($pv)) {
            vk_quotation_setting_set($pdo, $pk, trim($pv));
        }
    }
    vk_quotation_log($pdo, null, 'settings_updated', 'Quotation settings saved');
    flash_set('success', 'Settings saved.');
    redirect('/modules/quotations/settings.php');
}

$settings = [];
$st = $pdo->query('SELECT setting_key, setting_value FROM quotation_settings ORDER BY setting_key');
while ($row = $st->fetch()) {
    $settings[$row['setting_key']] = (string) $row['setting_value'];
}

$pageTitle = 'Quotation Settings';
$extraHead = '<link rel="stylesheet" href="' . e(base_url('assets/css/quotations.css')) . '?v=' . e(vk_asset_mtime_version('assets/css/quotations.css')) . '">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';
?>
<div class="qtn-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <p class="qtn-eyebrow mb-1">Settings</p>
            <h1 class="h3 mb-0">Quotation Settings</h1>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/templates.php">Templates</a>
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/modules/quotations/categories.php">Categories</a>
        </div>
    </div>

    <form method="post" class="card vk-card">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="card-body">
            <?php
            $lh = (string) ($settings['letterhead_path'] ?? 'assets/images/vk-letterhead.png');
            $lhAbs = dirname(__DIR__, 2) . '/' . ltrim(str_replace('\\', '/', $lh), '/');
            if (is_file($lhAbs)):
            ?>
            <div class="mb-4 p-3 rounded border bg-body-tertiary">
                <div class="fw-semibold mb-2">Official VK NETWORK letterhead</div>
                <img src="<?= e(base_url($lh . '?v=' . filemtime($lhAbs))) ?>" alt="VK Letterhead" class="img-fluid rounded border bg-white" style="max-height:280px;object-fit:contain">
                <div class="form-text mt-2">Print/PDF uses this as the full A4 background. Content area: top 38mm · bottom 25mm · sides 15mm.</div>
            </div>
            <?php endif; ?>
            <div class="row g-3">
                <?php foreach ($knownKeys as $key => $meta): ?>
                    <?php $val = $settings[$key] ?? ''; ?>
                    <div class="col-md-6">
                        <label class="form-label"><?= e($meta['label']) ?></label>
                        <?php if ($meta['type'] === 'checkbox'): ?>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="<?= e($key) ?>" id="set_<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="set_<?= e($key) ?>">Enabled</label>
                            </div>
                        <?php elseif ($meta['type'] === 'select'): ?>
                            <select name="<?= e($key) ?>" class="form-select">
                                <?php foreach ($meta['options'] as $opt): ?>
                                    <option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e(ucfirst($opt)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($meta['type'] === 'textarea'): ?>
                            <textarea name="<?= e($key) ?>" class="form-control font-monospace" rows="4"><?= e($val) ?></textarea>
                        <?php else: ?>
                            <input type="<?= e($meta['type']) ?>" name="<?= e($key) ?>" class="form-control" value="<?= e($val) ?>"
                                <?= $meta['type'] === 'number' ? 'step="any"' : '' ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-footer bg-transparent">
            <button type="submit" class="btn btn-primary">Save settings</button>
        </div>
    </form>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
