<?php
declare(strict_types=1);

/**
 * Invoice print template settings (single-row JSON store).
 * Backward-compatible: print.php uses defaults when no row exists.
 */

function vk_invoice_print_settings_defaults(): array
{
    return [
        'page_size' => 'A4',
        'page_orientation' => 'portrait',
        'margin_top' => 10,
        'margin_bottom' => 10,
        'margin_left' => 10,
        'margin_right' => 10,
        'logo_enabled' => 1,
        'logo_path' => 'assets/images/vk-logo.png',
        'logo_width_mm' => 42,
        'logo_height_mm' => 24,
        'logo_x' => 0,
        'logo_y' => 0,
        'company_name_font' => 'Arial, Helvetica, sans-serif',
        'company_name_size' => 32,
        'company_name_weight' => 700,
        'company_name_color' => '#0B4DBA',
        'company_desc_size' => 13,
        'company_desc_color' => '#222222',
        'header_line_enabled' => 1,
        'header_line_color' => '#0B4DBA',
        'header_line_thickness' => 3,
        'invoice_title_size' => 28,
        'invoice_title_weight' => 700,
        'invoice_title_color' => '#0B4DBA',
        'invoice_title_margin_left' => 0,
        'invoice_title_margin_top' => 25,
        'customer_name_size' => 12,
        'customer_address_size' => 11,
        'customer_label_size' => 10,
        'table_header_bg' => '#0B4DBA',
        'table_header_color' => '#FFFFFF',
        'table_header_size' => 9.5,
        'table_body_size' => 11,
        'table_border_color' => '#d8dce3',
        'table_row_padding' => 6,
        'total_font_size' => 16,
        'total_label_size' => 13,
        'total_font_weight' => 700,
        'total_bg' => '#0B4DBA',
        'total_color' => '#FFFFFF',
        'signature_enabled' => 1,
        'signature_path' => 'assets/images/digital-signature.png',
        'signature_width' => 130,
        'signature_height' => 0,
        'signature_opacity' => 1,
        'signature_x' => 0,
        'signature_y' => 0,
        'signature_rotation' => 0,
        'signature_aspect' => 1,
        'stamp_enabled' => 1,
        'stamp_path' => 'assets/images/company-stamp.png',
        'stamp_width_mm' => 85,
        'stamp_height_mm' => 30,
        'stamp_opacity' => 1,
        'stamp_rotation' => 0,
        'stamp_x' => 0,
        'stamp_y' => 0,
        'stamp_aspect' => 1,
        'stamp_preset' => 'professional',
        'approval_label_size' => 11,
        'approval_label_weight' => 700,
        'approval_label_color' => '#333333',
        'global_font_family' => 'Inter, Segoe UI, Arial, sans-serif',
        'global_font_size' => 11,
        'footer_font_size' => 11,
        'footer_height' => 0,
        'footer_border_enabled' => 1,
        'footer_qr_enabled' => 1,
        'watermark_enabled' => 1,
        'watermark_path' => 'assets/images/vk-logo.png',
        'watermark_opacity' => 0.03,
        'watermark_width' => 380,
        'watermark_height' => 0,
        'watermark_rotation' => 0,
        'stamp_contrast' => 1.5,
        'stamp_saturation' => 1.4,
        'stamp_brightness' => 1.08,
    ];
}

function vk_invoice_print_stamp_presets(): array
{
    return [
        'standard' => ['label' => 'Standard (69 × 25 mm)', 'width' => 69, 'height' => 25],
        'medium' => ['label' => 'Medium (80 × 25 mm)', 'width' => 80, 'height' => 25],
        'professional' => ['label' => 'Professional (85 × 30 mm)', 'width' => 85, 'height' => 30],
        'large' => ['label' => 'Large (90 × 32 mm)', 'width' => 90, 'height' => 32],
        'executive' => ['label' => 'Executive (100 × 35 mm)', 'width' => 100, 'height' => 35],
    ];
}

function vk_invoice_print_settings_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS invoice_print_settings (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            settings_json LONGTEXT NOT NULL,
            backup_json LONGTEXT NULL,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT chk_invoice_print_settings_single CHECK (id = 1)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $st = $pdo->query('SELECT COUNT(*) FROM invoice_print_settings WHERE id = 1');
    if ((int) $st->fetchColumn() === 0) {
        $json = json_encode(vk_invoice_print_settings_defaults(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $ins = $pdo->prepare('INSERT INTO invoice_print_settings (id, settings_json) VALUES (1, ?)');
        $ins->execute([$json]);
    }
}

function vk_invoice_print_settings_raw(PDO $pdo): array
{
    vk_invoice_print_settings_ensure_schema($pdo);
    $st = $pdo->query('SELECT settings_json FROM invoice_print_settings WHERE id = 1 LIMIT 1');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return vk_invoice_print_settings_defaults();
    }
    $decoded = json_decode((string) ($row['settings_json'] ?? ''), true);

    return is_array($decoded) ? $decoded : vk_invoice_print_settings_defaults();
}

function vk_invoice_print_settings_get(PDO $pdo): array
{
    return array_merge(vk_invoice_print_settings_defaults(), vk_invoice_print_settings_raw($pdo));
}

function vk_invoice_print_settings_save(PDO $pdo, array $settings): void
{
    vk_invoice_print_settings_ensure_schema($pdo);
    $merged = array_merge(vk_invoice_print_settings_defaults(), $settings);
    $json = json_encode($merged, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $st = $pdo->prepare(
        'INSERT INTO invoice_print_settings (id, settings_json) VALUES (1, ?)
         ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json), updated_at = CURRENT_TIMESTAMP'
    );
    $st->execute([$json]);
}

function vk_invoice_print_settings_reset(PDO $pdo): void
{
    vk_invoice_print_settings_save($pdo, vk_invoice_print_settings_defaults());
}

function vk_invoice_print_settings_backup(PDO $pdo): void
{
    vk_invoice_print_settings_ensure_schema($pdo);
    $current = vk_invoice_print_settings_raw($pdo);
    $json = json_encode($current, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $st = $pdo->prepare('UPDATE invoice_print_settings SET backup_json = ? WHERE id = 1');
    $st->execute([$json]);
}

function vk_invoice_print_settings_restore(PDO $pdo): bool
{
    vk_invoice_print_settings_ensure_schema($pdo);
    $st = $pdo->query('SELECT backup_json FROM invoice_print_settings WHERE id = 1 LIMIT 1');
    $row = $st->fetch(PDO::FETCH_ASSOC);
    $backup = json_decode((string) ($row['backup_json'] ?? ''), true);
    if (!is_array($backup) || $backup === []) {
        return false;
    }
    vk_invoice_print_settings_save($pdo, $backup);

    return true;
}

function vk_invoice_print_asset_url(string $relativePath, string $fallback = ''): string
{
    $rel = ltrim(str_replace('\\', '/', $relativePath), '/');
    if ($rel === '') {
        return $fallback !== '' ? base_url($fallback) : '';
    }
    $abs = dirname(__DIR__) . '/' . $rel;
    if (!is_file($abs) && $fallback !== '') {
        $rel = ltrim($fallback, '/');
        $abs = dirname(__DIR__) . '/' . $rel;
    }
    $ver = is_file($abs) ? (string) @filemtime($abs) : '1';

    return base_url($rel . '?v=' . $ver);
}

function vk_invoice_print_settings_css_vars(array $s): string
{
    $mm = static fn ($v): string => is_numeric($v) ? ((float) $v) . 'mm' : (string) $v;
    $px = static fn ($v): string => is_numeric($v) ? ((float) $v) . 'px' : (string) $v;
    $pt = static fn ($v): string => is_numeric($v) ? ((float) $v) . 'pt' : (string) $v;

    $lines = [
        '--vk-brand:' . ($s['company_name_color'] ?? '#0B4DBA'),
        '--vk-table-head:' . ($s['table_header_bg'] ?? '#0B4DBA'),
        '--stamp-width:' . $mm($s['stamp_width_mm'] ?? 85),
        '--stamp-height:' . $mm($s['stamp_height_mm'] ?? 30),
        '--stamp-opacity:' . (float) ($s['stamp_opacity'] ?? 1),
        '--stamp-rotation:' . ((float) ($s['stamp_rotation'] ?? 0)) . 'deg',
        '--stamp-contrast:' . (float) ($s['stamp_contrast'] ?? 1.5),
        '--stamp-saturation:' . (float) ($s['stamp_saturation'] ?? 1.4),
        '--stamp-brightness:' . (float) ($s['stamp_brightness'] ?? 1.08),
        '--ips-page-margin-top:' . $mm($s['margin_top'] ?? 10),
        '--ips-page-margin-bottom:' . $mm($s['margin_bottom'] ?? 10),
        '--ips-page-margin-left:' . $mm($s['margin_left'] ?? 10),
        '--ips-page-margin-right:' . $mm($s['margin_right'] ?? 10),
        '--ips-logo-width:' . $mm($s['logo_width_mm'] ?? 42),
        '--ips-logo-height:' . $mm($s['logo_height_mm'] ?? 24),
        '--ips-company-name-size:' . $px($s['company_name_size'] ?? 32),
        '--ips-company-name-weight:' . (int) ($s['company_name_weight'] ?? 700),
        '--ips-company-name-color:' . ($s['company_name_color'] ?? '#0B4DBA'),
        '--ips-company-desc-size:' . $px($s['company_desc_size'] ?? 13),
        '--ips-company-desc-color:' . ($s['company_desc_color'] ?? '#222222'),
        '--ips-header-line-color:' . ($s['header_line_color'] ?? '#0B4DBA'),
        '--ips-header-line-thickness:' . $px($s['header_line_thickness'] ?? 3),
        '--ips-invoice-title-size:' . $px($s['invoice_title_size'] ?? 28),
        '--ips-invoice-title-weight:' . (int) ($s['invoice_title_weight'] ?? 700),
        '--ips-invoice-title-color:' . ($s['invoice_title_color'] ?? '#0B4DBA'),
        '--ips-invoice-title-margin-left:' . $px($s['invoice_title_margin_left'] ?? 0),
        '--ips-invoice-title-margin-top:' . $px($s['invoice_title_margin_top'] ?? 25),
        '--ips-customer-name-size:' . $pt($s['customer_name_size'] ?? 12),
        '--ips-customer-address-size:' . $pt($s['customer_address_size'] ?? 11),
        '--ips-customer-label-size:' . $pt($s['customer_label_size'] ?? 10),
        '--ips-table-header-bg:' . ($s['table_header_bg'] ?? '#0B4DBA'),
        '--ips-table-header-color:' . ($s['table_header_color'] ?? '#FFFFFF'),
        '--ips-table-header-size:' . $pt($s['table_header_size'] ?? 9.5),
        '--ips-table-body-size:' . $pt($s['table_body_size'] ?? 11),
        '--ips-table-border:' . ($s['table_border_color'] ?? '#d8dce3'),
        '--ips-table-row-padding:' . $px($s['table_row_padding'] ?? 6),
        '--ips-total-bg:' . ($s['total_bg'] ?? '#0B4DBA'),
        '--ips-total-color:' . ($s['total_color'] ?? '#FFFFFF'),
        '--ips-total-size:' . $px($s['total_font_size'] ?? 16),
        '--ips-total-label-size:' . $px($s['total_label_size'] ?? 13),
        '--ips-total-weight:' . (int) ($s['total_font_weight'] ?? 700),
        '--ips-signature-width:' . $px($s['signature_width'] ?? 130),
        '--ips-signature-opacity:' . (float) ($s['signature_opacity'] ?? 1),
        '--ips-signature-rotation:' . ((float) ($s['signature_rotation'] ?? 0)) . 'deg',
        '--ips-approval-label-size:' . $px($s['approval_label_size'] ?? 11),
        '--ips-global-font:' . ($s['global_font_family'] ?? 'Inter, Segoe UI, Arial, sans-serif'),
        '--ips-global-font-size:' . $pt($s['global_font_size'] ?? 11),
        '--ips-footer-font-size:' . $px($s['footer_font_size'] ?? 11),
        '--ips-watermark-opacity:' . (float) ($s['watermark_opacity'] ?? 0.03),
        '--ips-watermark-width:' . $px($s['watermark_width'] ?? 380),
        '--ips-watermark-rotation:' . ((float) ($s['watermark_rotation'] ?? 0)) . 'deg',
    ];

    return implode("\n            ", $lines);
}
