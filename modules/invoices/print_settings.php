<?php
declare(strict_types=1);
$pageTitle = 'Invoice Print Settings';
require_once dirname(__DIR__, 2) . '/includes/layout_init.php';
require_settings_admin();
require_once dirname(__DIR__, 2) . '/includes/invoice_print_settings.php';

vk_invoice_print_settings_ensure_schema($pdo);
$settings = vk_invoice_print_settings_get($pdo);
$presets = vk_invoice_print_stamp_presets();
$previewId = (int) ($pdo->query('SELECT id FROM invoices ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);

$extraHead = '<link href="' . e(BASE_URL) . '/assets/css/invoice-print-settings.css" rel="stylesheet">';
require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

function ips_field(string $key, string $label, string $type = 'text', array $opts = []): void
{
    global $settings;
    $val = $settings[$key] ?? '';
    $id = 'ips_' . $key;
    echo '<div class="mb-3">';
    echo '<label class="form-label" for="' . e($id) . '">' . e($label) . '</label>';
    if ($type === 'checkbox') {
        $checked = !empty($val) && (string) $val !== '0' ? ' checked' : '';
        echo '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="' . e($id) . '" data-setting-key="' . e($key) . '"' . $checked . '></div>';
    } elseif ($type === 'select') {
        echo '<select class="form-select" id="' . e($id) . '" data-setting-key="' . e($key) . '">';
        foreach ($opts as $ov => $ol) {
            echo '<option value="' . e((string) $ov) . '"' . ((string) $val === (string) $ov ? ' selected' : '') . '>' . e($ol) . '</option>';
        }
        echo '</select>';
    } elseif ($type === 'color') {
        echo '<input type="color" class="form-control form-control-color" id="' . e($id) . '" data-setting-key="' . e($key) . '" value="' . e((string) $val) . '">';
    } elseif ($type === 'range') {
        $min = $opts['min'] ?? 0;
        $max = $opts['max'] ?? 100;
        $step = $opts['step'] ?? 1;
        echo '<input type="range" class="form-range" id="' . e($id) . '" data-setting-key="' . e($key) . '" min="' . e((string) $min) . '" max="' . e((string) $max) . '" step="' . e((string) $step) . '" value="' . e((string) $val) . '">';
        echo '<div class="small text-muted ips-range-val" data-for="' . e($id) . '">' . e((string) $val) . '</div>';
    } else {
        $inputType = $type === 'number' ? 'number' : 'text';
        $step = isset($opts['step']) ? ' step="' . e((string) $opts['step']) . '"' : '';
        echo '<input type="' . e($inputType) . '" class="form-control" id="' . e($id) . '" data-setting-key="' . e($key) . '" value="' . e((string) $val) . '"' . $step . '>';
    }
    echo '</div>';
}

function ips_asset_block(string $field, string $pathKey, string $title): void
{
    global $settings;
    $path = (string) ($settings[$pathKey] ?? '');
    $url = vk_invoice_print_asset_url($path);
    echo '<div class="ips-asset-card mb-3" data-asset-field="' . e($field) . '">';
    echo '<div class="d-flex justify-content-between align-items-center mb-2"><strong>' . e($title) . '</strong>';
    echo '<div class="btn-group btn-group-sm"><label class="btn btn-outline-primary mb-0"><i class="bi bi-upload"></i> Upload<input type="file" class="d-none ips-upload" data-field="' . e($field) . '" accept="image/png,image/jpeg,image/webp,image/svg+xml"></label>';
    echo '<button type="button" class="btn btn-outline-danger ips-delete-asset" data-field="' . e($field) . '"><i class="bi bi-trash"></i></button></div></div>';
    echo '<div class="ips-asset-preview border rounded bg-light p-2 text-center"><img src="' . e($url) . '" alt="" class="img-fluid ips-preview-img" data-path-key="' . e($pathKey) . '"></div>';
    echo '<input type="hidden" data-setting-key="' . e($pathKey) . '" value="' . e($path) . '">';
    echo '</div>';
}
?>
<div class="ips-page" id="invoicePrintSettingsApp">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-printer me-2 text-primary"></i>Invoice Print Settings</h1>
            <p class="text-muted mb-0">Configure A4 layout, branding assets, fonts, stamp, signature, and live print preview.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" id="ipsSaveBtn"><i class="bi bi-check2-circle me-1"></i>Save Settings</button>
            <button type="button" class="btn btn-outline-secondary" id="ipsResetBtn">Reset to Default</button>
            <button type="button" class="btn btn-outline-secondary" id="ipsBackupBtn">Backup</button>
            <button type="button" class="btn btn-outline-secondary" id="ipsRestoreBtn">Restore</button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="accordion ips-accordion" id="ipsAccordion">
                <div class="accordion-item" id="section-page">
                    <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#ipsPage">① Page Settings</button></h2>
                    <div id="ipsPage" class="accordion-collapse collapse show" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php
                        ips_field('page_size', 'Paper Size', 'select', ['A4' => 'A4', 'Letter' => 'Letter', 'Legal' => 'Legal']);
                        ips_field('page_orientation', 'Orientation', 'select', ['portrait' => 'Portrait', 'landscape' => 'Landscape']);
                        ips_field('margin_top', 'Margin Top (mm)', 'number', ['step' => '0.5']);
                        ips_field('margin_bottom', 'Margin Bottom (mm)', 'number', ['step' => '0.5']);
                        ips_field('margin_left', 'Margin Left (mm)', 'number', ['step' => '0.5']);
                        ips_field('margin_right', 'Margin Right (mm)', 'number', ['step' => '0.5']);
                        ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-header">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsHeader">② Header Settings</button></h2>
                    <div id="ipsHeader" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_asset_block('logo', 'logo_path', 'Company Logo'); ips_field('logo_enabled', 'Enable Logo', 'checkbox'); ?>
                        <?php ips_field('logo_width_mm', 'Logo Width (mm)', 'number'); ips_field('logo_height_mm', 'Logo Height (mm)', 'number'); ?>
                        <?php ips_field('company_name_font', 'Company Name Font'); ips_field('company_name_size', 'Company Name Size (px)', 'number'); ?>
                        <?php ips_field('company_name_weight', 'Company Name Weight', 'number'); ips_field('company_name_color', 'Company Name Color', 'color'); ?>
                        <?php ips_field('company_desc_size', 'Description Font Size (px)', 'number'); ips_field('company_desc_color', 'Description Color', 'color'); ?>
                        <?php ips_field('header_line_enabled', 'Header Line Enable', 'checkbox'); ips_field('header_line_color', 'Header Line Color', 'color'); ips_field('header_line_thickness', 'Header Line Thickness (px)', 'number'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-title">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsTitle">③ Invoice Title</button></h2>
                    <div id="ipsTitle" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_field('invoice_title_size', 'Font Size (px)', 'number'); ips_field('invoice_title_weight', 'Font Weight', 'number'); ?>
                        <?php ips_field('invoice_title_color', 'Color', 'color'); ips_field('invoice_title_margin_left', 'Left Margin (px)', 'number'); ips_field('invoice_title_margin_top', 'Top Margin (px)', 'number'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-customer">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsCustomer">④ Customer Details</button></h2>
                    <div id="ipsCustomer" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_field('customer_name_size', 'Customer Name Size (pt)', 'number'); ips_field('customer_address_size', 'Address Size (pt)', 'number'); ips_field('customer_label_size', 'Label Size (pt)', 'number'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-table">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsTable">⑤ Table Settings</button></h2>
                    <div id="ipsTable" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_field('table_header_bg', 'Header Background', 'color'); ips_field('table_header_color', 'Header Font Color', 'color'); ips_field('table_header_size', 'Header Font Size (pt)', 'number', ['step' => '0.5']); ?>
                        <?php ips_field('table_body_size', 'Body Font Size (pt)', 'number', ['step' => '0.5']); ips_field('table_border_color', 'Border Color', 'color'); ips_field('table_row_padding', 'Row Padding (px)', 'number'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-total">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsTotal">⑥ Total Settings</button></h2>
                    <div id="ipsTotal" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_field('total_font_size', 'Amount Font Size (px)', 'number'); ips_field('total_label_size', 'Label Font Size (px)', 'number'); ?>
                        <?php ips_field('total_font_weight', 'Font Weight', 'number'); ips_field('total_bg', 'Background', 'color'); ips_field('total_color', 'Font Color', 'color'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-signature">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsSignature">⑦ Digital Signature</button></h2>
                    <div id="ipsSignature" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_asset_block('signature', 'signature_path', 'Digital Signature'); ips_field('signature_enabled', 'Enable', 'checkbox'); ?>
                        <?php ips_field('signature_width', 'Width (px)', 'number'); ips_field('signature_opacity', 'Opacity', 'range', ['min' => 0, 'max' => 1, 'step' => 0.05]); ?>
                        <?php ips_field('signature_rotation', 'Rotation (deg)', 'number'); ips_field('signature_aspect', 'Maintain Aspect Ratio', 'checkbox'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-stamp">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsStamp">⑧ Company Stamp</button></h2>
                    <div id="ipsStamp" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_asset_block('stamp', 'stamp_path', 'Company Stamp'); ips_field('stamp_enabled', 'Enable', 'checkbox'); ?>
                        <?php ips_field('stamp_preset', 'Print Size Preset', 'select', array_combine(array_keys($presets), array_column($presets, 'label'))); ?>
                        <?php ips_field('stamp_width_mm', 'Stamp Width (mm)', 'number', ['step' => '0.5']); ips_field('stamp_height_mm', 'Stamp Height (mm)', 'number', ['step' => '0.5']); ?>
                        <?php ips_field('stamp_opacity', 'Opacity', 'range', ['min' => 0, 'max' => 1, 'step' => 0.05]); ips_field('stamp_rotation', 'Rotation (deg)', 'number'); ?>
                        <?php ips_field('stamp_contrast', 'Contrast', 'range', ['min' => 1, 'max' => 2, 'step' => 0.05]); ?>
                        <?php ips_field('stamp_saturation', 'Saturation', 'range', ['min' => 1, 'max' => 2, 'step' => 0.05]); ?>
                        <?php ips_field('approval_label_size', 'Authorized By Font (px)', 'number'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-logo">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsLogo">⑨ Logo Management</button></h2>
                    <div id="ipsLogo" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <p class="small text-muted">Logo upload is shared with Header Settings. Adjust width/height under Header.</p>
                        <?php ips_field('logo_x', 'Position X', 'number'); ips_field('logo_y', 'Position Y', 'number'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-fonts">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsFonts">⑩ Font Management</button></h2>
                    <div id="ipsFonts" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_field('global_font_family', 'Global Font Family'); ips_field('global_font_size', 'Global Font Size (pt)', 'number', ['step' => '0.5']); ips_field('footer_font_size', 'Footer Font Size (px)', 'number'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-footer">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsFooter">⑪ Footer Settings</button></h2>
                    <div id="ipsFooter" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_field('footer_font_size', 'Footer Font Size (px)', 'number'); ips_field('footer_qr_enabled', 'QR Code Enable', 'checkbox'); ?>
                        <?php ips_field('footer_border_enabled', 'Footer Border Enable', 'checkbox'); ips_field('footer_height', 'Footer Height (px)', 'number'); ?>
                    </div></div>
                </div>

                <div class="accordion-item" id="section-watermark">
                    <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipsWatermark">⑫ Watermark</button></h2>
                    <div id="ipsWatermark" class="accordion-collapse collapse" data-bs-parent="#ipsAccordion"><div class="accordion-body">
                        <?php ips_asset_block('watermark', 'watermark_path', 'Watermark Image'); ips_field('watermark_enabled', 'Enable', 'checkbox'); ?>
                        <?php ips_field('watermark_opacity', 'Opacity', 'range', ['min' => 0, 'max' => 0.2, 'step' => 0.005]); ips_field('watermark_width', 'Width (px)', 'number'); ips_field('watermark_rotation', 'Rotation (deg)', 'number'); ?>
                    </div></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="ips-preview-sticky card vk-card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="bi bi-eye me-1"></i>Live Preview</strong>
                    <?php if ($previewId > 0): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/modules/invoices/print.php?id=<?= $previewId ?>" target="_blank" rel="noopener">Open Print</a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($previewId > 0): ?>
                    <iframe id="ipsPreviewFrame" class="ips-preview-frame" title="Invoice preview" src="<?= e(BASE_URL) ?>/modules/invoices/print.php?id=<?= $previewId ?>&settings_preview=1"></iframe>
                    <?php else: ?>
                    <div class="p-4 text-center text-muted">Create an invoice first to enable live preview.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$extraScripts = '<script>window.IPS_PREVIEW_ID=' . json_encode($previewId) . ';window.IPS_STAMP_PRESETS=' . json_encode($presets) . ';</script>'
    . '<script src="' . e(BASE_URL) . '/assets/js/invoice-print-settings.js"></script>'
    . '<script>document.addEventListener("DOMContentLoaded",function(){var h=location.hash;if(!h)return;var el=document.querySelector(h);if(!el)return;var btn=el.querySelector(".accordion-button");if(btn&&!btn.classList.contains("collapsed"))return;if(btn){btn.click();}el.scrollIntoView({behavior:"smooth",block:"start"});});</script>';
require_once dirname(__DIR__, 2) . '/includes/layout_end.php';
