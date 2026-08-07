<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/layout_init.php';

function studio_options(PDO $pdo, string $sql): array
{
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        return [];
    }
}

function studio_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        $cache[$table] = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function studio_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        $cache[$table] = array_map(static fn(array $col): string => (string) $col['Field'], $cols);
    } catch (Throwable) {
        $cache[$table] = [];
    }

    return $cache[$table];
}

function studio_insert_filtered(PDO $pdo, string $table, array $payload): ?int
{
    $columns = studio_table_columns($pdo, $table);
    if ($columns === []) {
        return null;
    }

    $filtered = array_intersect_key($payload, array_flip($columns));
    if ($filtered === []) {
        return null;
    }

    $names = array_keys($filtered);
    $quoted = implode(', ', array_map(static fn(string $name): string => "`{$name}`", $names));
    $placeholders = implode(', ', array_map(static fn(string $name): string => ':' . $name, $names));

    $stmt = $pdo->prepare("INSERT INTO `{$table}` ({$quoted}) VALUES ({$placeholders})");
    $stmt->execute($filtered);

    return (int) $pdo->lastInsertId();
}

function studio_slug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    return trim($slug, '-') ?: 'product-' . time();
}

function studio_num(array $source, string $key, float $default = 0): float
{
    return is_numeric($source[$key] ?? null) ? (float) $source[$key] : $default;
}

function studio_int(array $source, string $key, int $default = 0): int
{
    return is_numeric($source[$key] ?? null) ? (int) $source[$key] : $default;
}

function studio_nullable_id(array $source, string $key): ?int
{
    return is_numeric($source[$key] ?? null) ? (int) $source[$key] : null;
}

function studio_date(array $source, string $key): ?string
{
    $value = trim((string) ($source[$key] ?? ''));
    return $value !== '' ? $value : null;
}

function studio_text(array $source, string $key): ?string
{
    $value = trim((string) ($source[$key] ?? ''));
    return $value !== '' ? $value : null;
}

function studio_draft_payload(array $source): array
{
    $keys = [
        'name', 'subtitle', 'sku', 'barcode', 'qr_code', 'classification', 'product_type', 'brand_id', 'category_id', 'subcategory_id',
        'supplier_id', 'manufacturer_id', 'unit_type', 'hsn_sac_code', 'country_of_origin', 'product_tags', 'collections',
        'visibility', 'stock_status', 'trending',
        'short_description', 'description', 'seo_url', 'meta_title', 'meta_description', 'meta_keywords',
        'cost_price', 'selling_price', 'wholesale_price', 'dealer_price', 'distributor_price', 'msrp', 'currency',
        'tax_class_id', 'tax_rate', 'vat_gst', 'discount_type', 'discount_value', 'promotional_price',
        'promo_start_date', 'promo_end_date', 'price_valid_from', 'price_valid_to', 'opening_stock', 'current_stock', 'minimum_stock',
        'reorder_level', 'warehouse_id', 'rack_location', 'bin_number', 'batch_number', 'serial_number', 'stock_keeping_type', 'reserved_stock',
        'incoming_stock', 'low_stock_alert', 'inventory_tracking', 'multi_warehouse_support',
        'manufacturing_date', 'expiry_date', 'warranty_enabled', 'warranty_type', 'warranty_period', 'warranty_unit',
        'warranty_start_date', 'warranty_coverage', 'warranty_terms', 'warranty_claim_process', 'warranty_provider', 'warranty_notes',
        'service_center_name', 'service_center_phone', 'service_center_email', 'service_center_address', 'support_contact',
        'replacement_policy', 'amc_support',
        'variant_colors', 'variant_sizes', 'variant_materials', 'variant_storage', 'variant_models',
        'variant_notes', 'shipping_weight', 'shipping_length', 'shipping_width',
        'shipping_height', 'shipping_class', 'delivery_sla', 'return_window', 'fragile', 'free_shipping',
        'cod_support', 'packaging_type', 'featured', 'is_digital', 'requires_shipping', 'allow_backorders', 'status',
        'focus_keyword', 'canonical_url', 'cross_sell', 'upsell', 'related_products', 'campaign_tags'
    ];

    $payload = [];
    foreach ($keys as $key) {
        $payload[$key] = $source[$key] ?? '';
    }

    return $payload;
}

function studio_completeness(array $data): int
{
    $checks = [
        !empty(trim((string) ($data['name'] ?? ''))),
        !empty(trim((string) ($data['sku'] ?? ''))),
        !empty($data['category_id']),
        !empty($data['brand_id']),
        studio_num($data, 'selling_price') > 0,
        studio_int($data, 'opening_stock') >= 0,
        !empty(trim((string) ($data['short_description'] ?? ''))),
        !empty(trim((string) ($data['description'] ?? ''))),
        !empty(trim((string) ($data['meta_title'] ?? ''))),
        !empty(trim((string) ($data['meta_description'] ?? ''))),
    ];

    $complete = count(array_filter($checks));
    return (int) round(($complete / count($checks)) * 100);
}

$draftSessionKey = 'product_studio_add_draft';
$draftState = $_SESSION[$draftSessionKey] ?? ['data' => [], 'saved_at' => null];

$cats = studio_options($pdo, 'SELECT id, name FROM categories ORDER BY name');
$catTree = studio_options($pdo, 'SELECT id, name, COALESCE(parent_id, 0) AS parent_id FROM categories ORDER BY name');
$brands = studio_options($pdo, 'SELECT id, name FROM brands ORDER BY name');
$suppliers = studio_options($pdo, 'SELECT id, name FROM suppliers ORDER BY name');
$manufacturers = studio_options($pdo, 'SELECT id, name FROM manufacturers ORDER BY name');
$warehouses = studio_options($pdo, 'SELECT id, name FROM warehouses ORDER BY name');
$taxClasses = studio_options($pdo, 'SELECT id, name FROM tax_classes ORDER BY name');
$taxClassesExtended = studio_options($pdo, 'SELECT id, name, COALESCE(rate, 0) AS rate, COALESCE(tax_type, \'vat\') AS tax_type FROM tax_classes ORDER BY name');
if ($taxClassesExtended !== []) {
    $taxClasses = $taxClassesExtended;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');

    $intent = (string) ($_POST['intent'] ?? 'publish');
    $draftPayload = studio_draft_payload($_POST);

    if ($isAjax && $intent === 'check_sku') {
        header('Content-Type: application/json; charset=utf-8');
        $sku = trim((string) ($_POST['sku'] ?? ''));
        if ($sku === '') {
            echo json_encode(['success' => true, 'exists' => false]);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id, name FROM products WHERE sku = ? LIMIT 1');
        $stmt->execute([$sku]);
        $found = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        echo json_encode(['success' => true, 'exists' => (bool) $found, 'product' => $found]);
        exit;
    }

    if ($isAjax && $intent === 'detect_duplicate') {
        header('Content-Type: application/json; charset=utf-8');
        $name = trim((string) ($_POST['name'] ?? ''));
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $results = [];

        if ($name !== '') {
            $stmt = $pdo->prepare('SELECT id, name, sku FROM products WHERE name LIKE ? ORDER BY id DESC LIMIT 5');
            $stmt->execute(['%' . $name . '%']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($sku !== '') {
            $stmt = $pdo->prepare('SELECT id, name, sku FROM products WHERE sku LIKE ? ORDER BY id DESC LIMIT 5');
            $stmt->execute(['%' . $sku . '%']);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode(['success' => true, 'matches' => $results]);
        exit;
    }

    if (in_array($intent, ['draft', 'autosave'], true)) {
        $_SESSION[$draftSessionKey] = [
            'data' => $draftPayload,
            'saved_at' => time(),
        ];

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'intent' => $intent,
                'saved_at' => date(DATE_ATOM),
                'completeness' => studio_completeness($draftPayload),
            ]);
            exit;
        }

        flash_set('success', 'Draft saved.');
        redirect('/modules/products/add.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        $_SESSION[$draftSessionKey] = ['data' => $draftPayload, 'saved_at' => time()];
        $message = 'Product name is required.';
        if ($isAjax) {
            http_response_code(422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $message]);
            exit;
        }
        flash_set('error', $message);
        redirect('/modules/products/add.php');
    }

    $sku = studio_text($_POST, 'sku');
    $barcode = studio_text($_POST, 'barcode');
    $slug = studio_text($_POST, 'seo_url') ?: studio_slug($name);
    $warrantyStart = studio_date($_POST, 'warranty_start_date');
    $warrantyDuration = studio_int($_POST, 'warranty_period', 12);
    $warrantyUnit = (string) ($_POST['warranty_unit'] ?? 'months');
    $warrantyEnd = null;

    if ($warrantyStart && $warrantyDuration > 0) {
        $date = new DateTime($warrantyStart);
        $modifier = '+' . $warrantyDuration . ' ' . $warrantyUnit;
        $date->modify($modifier);
        $warrantyEnd = $date->format('Y-m-d');
    }

    try {
        $pdo->beginTransaction();

        $productId = studio_insert_filtered($pdo, 'products', [
            'sku' => $sku,
            'barcode' => $barcode,
            'qr_code' => studio_text($_POST, 'qr_code'),
            'name' => $name,
            'subtitle' => studio_text($_POST, 'subtitle'),
            'slug' => $slug,
            'product_type' => studio_text($_POST, 'product_type') ?: 'simple',
            'brand_id' => studio_nullable_id($_POST, 'brand_id'),
            'category_id' => studio_nullable_id($_POST, 'category_id'),
            'subcategory_id' => studio_nullable_id($_POST, 'subcategory_id'),
            'supplier_id' => studio_nullable_id($_POST, 'supplier_id'),
            'manufacturer_id' => studio_nullable_id($_POST, 'manufacturer_id'),
            'unit_type' => studio_text($_POST, 'unit_type') ?: 'piece',
            'hsn_sac_code' => studio_text($_POST, 'hsn_sac_code'),
            'country_of_origin' => studio_text($_POST, 'country_of_origin'),
            'short_description' => studio_text($_POST, 'short_description'),
            'description' => studio_text($_POST, 'description'),
            'meta_title' => studio_text($_POST, 'meta_title'),
            'meta_description' => studio_text($_POST, 'meta_description'),
            'meta_keywords' => studio_text($_POST, 'meta_keywords'),
            'seo_url' => $slug,
            'status' => studio_text($_POST, 'status') ?: 'active',
            'featured' => !empty($_POST['featured']) ? 1 : 0,
            'is_digital' => !empty($_POST['is_digital']) ? 1 : 0,
            'requires_shipping' => !empty($_POST['requires_shipping']) ? 1 : 0,
            'tax_class_id' => studio_nullable_id($_POST, 'tax_class_id'),
            'cost_price' => studio_num($_POST, 'cost_price'),
            'selling_price' => studio_num($_POST, 'selling_price'),
            'wholesale_price' => studio_num($_POST, 'wholesale_price'),
            'currency' => studio_text($_POST, 'currency') ?: 'USD',
            'warranty_enabled' => !empty($_POST['warranty_enabled']) ? 1 : 0,
            'warranty_duration_days' => !empty($_POST['warranty_enabled']) ? max(0, $warrantyDuration * 30) : null,
            'opening_stock' => studio_int($_POST, 'opening_stock'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$productId) {
            throw new RuntimeException('Unable to save the product because no compatible product columns were found.');
        }

        if (studio_table_exists($pdo, 'product_pricing')) {
            studio_insert_filtered($pdo, 'product_pricing', [
                'product_id' => $productId,
                'cost_price' => studio_num($_POST, 'cost_price'),
                'selling_price' => studio_num($_POST, 'selling_price'),
                'wholesale_price' => studio_num($_POST, 'wholesale_price'),
                'dealer_price' => studio_num($_POST, 'dealer_price'),
                'distributor_price' => studio_num($_POST, 'distributor_price'),
                'msrp' => studio_num($_POST, 'msrp'),
                'currency' => studio_text($_POST, 'currency') ?: 'USD',
                'tax_rate' => studio_num($_POST, 'tax_rate'),
                'vat_gst' => studio_num($_POST, 'vat_gst'),
                'profit_margin' => studio_num($_POST, 'profit_margin'),
                'discount_type' => studio_text($_POST, 'discount_type') ?: 'none',
                'discount_value' => studio_num($_POST, 'discount_value'),
                'promotional_price' => studio_num($_POST, 'promotional_price'),
                'promo_start_date' => studio_date($_POST, 'promo_start_date'),
                'promo_end_date' => studio_date($_POST, 'promo_end_date'),
                'price_valid_from' => studio_date($_POST, 'price_valid_from'),
                'price_valid_to' => studio_date($_POST, 'price_valid_to'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (studio_table_exists($pdo, 'product_inventory')) {
            studio_insert_filtered($pdo, 'product_inventory', [
                'product_id' => $productId,
                'warehouse_id' => studio_nullable_id($_POST, 'warehouse_id'),
                'opening_stock' => studio_int($_POST, 'opening_stock'),
                'current_stock' => studio_int($_POST, 'current_stock') ?: studio_int($_POST, 'opening_stock'),
                'minimum_stock' => studio_int($_POST, 'minimum_stock'),
                'reorder_level' => studio_int($_POST, 'reorder_level'),
                'rack_location' => studio_text($_POST, 'rack_location'),
                'bin_number' => studio_text($_POST, 'bin_number'),
                'batch_number' => studio_text($_POST, 'batch_number'),
                'serial_number' => studio_text($_POST, 'serial_number'),
                'expiry_date' => studio_date($_POST, 'expiry_date'),
                'manufacturing_date' => studio_date($_POST, 'manufacturing_date'),
                'last_stock_update' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (!empty($_POST['warranty_enabled']) && studio_table_exists($pdo, 'product_warranty')) {
            $docPath = null;
            if (!empty($_FILES['warranty_document']['name']) && is_uploaded_file($_FILES['warranty_document']['tmp_name'])) {
                $extension = pathinfo((string) $_FILES['warranty_document']['name'], PATHINFO_EXTENSION);
                $directory = __DIR__ . '/../../uploads/warranties';
                if (!is_dir($directory)) {
                    mkdir($directory, 0755, true);
                }
                $filename = uniqid('warranty_', true) . '.' . ($extension ?: 'pdf');
                $destination = $directory . '/' . $filename;
                move_uploaded_file($_FILES['warranty_document']['tmp_name'], $destination);
                $docPath = 'uploads/warranties/' . $filename;
            }

            studio_insert_filtered($pdo, 'product_warranty', [
                'product_id' => $productId,
                'warranty_enabled' => 1,
                'warranty_type' => studio_text($_POST, 'warranty_type') ?: 'manufacturer',
                'warranty_duration' => $warrantyDuration,
                'warranty_unit' => $warrantyUnit,
                'warranty_start_date' => $warrantyStart,
                'warranty_end_date' => $warrantyEnd,
                'warranty_coverage' => studio_text($_POST, 'warranty_coverage'),
                'warranty_terms' => studio_text($_POST, 'warranty_terms'),
                'claim_procedure' => studio_text($_POST, 'warranty_claim_process'),
                'warranty_provider' => studio_text($_POST, 'warranty_provider'),
                'service_center_name' => studio_text($_POST, 'service_center_name'),
                'service_center_phone' => studio_text($_POST, 'service_center_phone'),
                'service_center_email' => studio_text($_POST, 'service_center_email'),
                'warranty_document_path' => $docPath,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (studio_table_exists($pdo, 'product_tags')) {
            $rawTags = explode(',', (string) ($_POST['product_tags'] ?? ''));
            $tags = array_values(array_filter(array_map(static fn(string $tag): string => trim($tag), $rawTags)));
            foreach ($tags as $tag) {
                studio_insert_filtered($pdo, 'product_tags', [
                    'product_id' => $productId,
                    'tag_name' => $tag,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name']) && studio_table_exists($pdo, 'product_images')) {
            $uploadDir = __DIR__ . '/../../uploads/products/' . $productId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            foreach ($_FILES['images']['name'] as $index => $nameFile) {
                if (!is_uploaded_file($_FILES['images']['tmp_name'][$index])) {
                    continue;
                }

                $ext = pathinfo((string) $nameFile, PATHINFO_EXTENSION);
                $target = $uploadDir . '/' . ($index + 1) . '.' . ($ext ?: 'jpg');
                move_uploaded_file($_FILES['images']['tmp_name'][$index], $target);

                studio_insert_filtered($pdo, 'product_images', [
                    'product_id' => $productId,
                    'path' => 'uploads/products/' . $productId . '/' . basename($target),
                    'is_thumbnail' => $index === 0 ? 1 : 0,
                    'sort_order' => $index,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $pdo->commit();
        unset($_SESSION[$draftSessionKey]);

        if ($isAjax) {
            $redirect = $intent === 'publish_new' ? base_url('modules/products/add.php') : base_url('modules/products/list.php');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'id' => $productId, 'redirect' => $redirect]);
            exit;
        }

        flash_set('success', 'Product created.');
        if ($intent === 'publish_new') {
            redirect('/modules/products/add.php');
        }
        redirect('/modules/products/list.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION[$draftSessionKey] = ['data' => $draftPayload, 'saved_at' => time()];

        if ($isAjax) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }

        flash_set('error', 'Error creating product: ' . $e->getMessage());
        redirect('/modules/products/add.php');
    }
}

$form = array_merge($draftState['data'] ?? [], $_POST);
$savedAt = $draftState['saved_at'] ?? null;

function studio_value(array $form, string $key, string $default = ''): string
{
    $value = $form[$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function studio_selected(array $form, string $key, string $value): string
{
    return studio_value($form, $key) === $value ? 'selected' : '';
}

function studio_checked(array $form, string $key, bool $default = false): string
{
    if (!array_key_exists($key, $form)) {
        return $default ? 'checked' : '';
    }
    return !empty($form[$key]) ? 'checked' : '';
}

$pageTitle = 'Add New Product';
$extraHead =
    '<link rel="preconnect" href="https://fonts.googleapis.com">' .
    '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' .
    '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">' .
    '<link href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">' .
    '<link rel="stylesheet" href="' . e(base_url('assets/css/product-admin.css')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/css/product-admin.css')) . '">' .
    '<link rel="stylesheet" href="' . e(base_url('assets/css/product-studio-v2.css')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/css/product-studio-v2.css')) . '">' .
    '<link rel="stylesheet" href="' . e(base_url('assets/css/product-basic-module.css')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/css/product-basic-module.css')) . '">' .
    '<link rel="stylesheet" href="' . e(base_url('assets/css/product-pricing-module.css')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/css/product-pricing-module.css')) . '">' .
    '<link rel="stylesheet" href="' . e(base_url('assets/css/product-inventory-module.css')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/css/product-inventory-module.css')) . '">' .
    '<link rel="stylesheet" href="' . e(base_url('assets/css/product-media-module.css')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/css/product-media-module.css')) . '">' .
    '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>' .
    '<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>' .
    '<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>';
$extraScripts =
    '<script src="' . e(base_url('assets/js/product-media-module.js')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/js/product-media-module.js')) . '"></script>' .
    '<script src="' . e(base_url('assets/js/product-admin.js')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/js/product-admin.js')) . '"></script>' .
    '<script src="' . e(base_url('assets/js/product-studio-v2.js')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/js/product-studio-v2.js')) . '"></script>' .
    '<script src="' . e(base_url('assets/js/product-basic-module.js')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/js/product-basic-module.js')) . '"></script>' .
    '<script src="' . e(base_url('assets/js/product-pricing-module.js')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/js/product-pricing-module.js')) . '"></script>' .
    '<script src="' . e(base_url('assets/js/product-inventory-module.js')) . '?v=' . e((string) @filemtime(dirname(__DIR__, 2) . '/assets/js/product-inventory-module.js')) . '"></script>';

require_once dirname(__DIR__, 2) . '/includes/layout_start.php';

$stepItems = [
    ['id' => 'basic', 'icon' => 'bi-box-seam', 'label' => 'Basic Info'],
    ['id' => 'pricing', 'icon' => 'bi-cash-stack', 'label' => 'Pricing'],
    ['id' => 'inventory', 'icon' => 'bi-stack', 'label' => 'Inventory'],
    ['id' => 'media', 'icon' => 'bi-images', 'label' => 'Media'],
    ['id' => 'shipping', 'icon' => 'bi-truck', 'label' => 'Shipping'],
    ['id' => 'seo', 'icon' => 'bi-search', 'label' => 'SEO'],
    ['id' => 'warranty', 'icon' => 'bi-shield-check', 'label' => 'Warranty'],
    ['id' => 'variants', 'icon' => 'bi-grid-3x3-gap', 'label' => 'Variants'],
    ['id' => 'review', 'icon' => 'bi-check2-square', 'label' => 'Review'],
];

$completeness = studio_completeness($form);
?>
<div class="product-studio" id="productStudio" data-studio-v2="1" data-base-url="<?= e(BASE_URL) ?>" data-autosave-url="<?= e(base_url('modules/products/add.php')) ?>">
    <div class="studio-bg-orb studio-bg-orb-a"></div>
    <div class="studio-bg-orb studio-bg-orb-b"></div>

    <form id="product-form" class="studio-shell" method="post" enctype="multipart/form-data">
        <input type="hidden" name="intent" id="form-intent" value="publish">
        <div class="studio-topbar">
            <div class="studio-topbar-main">
                <nav class="studio-breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= e(base_url('dashboard.php')) ?>">Dashboard</a>
                    <i class="bi bi-chevron-right"></i>
                    <a href="<?= e(base_url('modules/products/list.php')) ?>">Products</a>
                    <i class="bi bi-chevron-right"></i>
                    <span>Add New Product</span>
                </nav>
                <div class="studio-title-row">
                    <div>
                        <div class="studio-kicker">Enterprise Inventory Workspace</div>
                        <h1>Add New Product</h1>
                        <p>Create a premium inventory item with guided ERP workflows, live calculations, media management, and publishing controls.</p>
                    </div>
                    <div class="studio-health">
                        <span class="studio-badge studio-badge-success" id="autosaveStatus">
                            <i class="bi bi-cloud-check"></i>
                            <?= $savedAt ? 'Draft synced ' . e(date('M d, H:i', (int) $savedAt)) : 'Auto-save ready' ?>
                        </span>
                        <span class="studio-badge studio-badge-info" id="workflowBadge">
                            <i class="bi bi-diagram-3"></i>
                            Workflow: Draft
                        </span>
                        <span class="studio-shortcut-hint">
                            <kbd>Ctrl</kbd> + <kbd>S</kbd> save
                            <span class="studio-shortcut-divider"></span>
                            <kbd>Ctrl</kbd> + <kbd>Enter</kbd> publish
                        </span>
                    </div>
                </div>
            </div>

            <div class="studio-actions">
                <a href="<?= e(base_url('modules/products/list.php')) ?>" class="btn studio-btn studio-btn-ghost">
                    <i class="bi bi-arrow-left"></i>
                    Back to Products
                </a>
                <button type="button" class="btn studio-btn studio-btn-ghost" id="resetFormButton">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Reset
                </button>
                <button type="button" class="btn studio-btn studio-btn-ghost" id="previewTrigger">
                    <i class="bi bi-eye"></i>
                    Preview
                </button>
                <button type="button" class="btn studio-btn studio-btn-ghost" id="saveDraftButton">
                    <i class="bi bi-save2"></i>
                    Draft
                </button>
                <div class="dropdown">
                    <button class="btn studio-btn studio-btn-ghost dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots"></i>
                        More
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end studio-dropdown">
                        <li><button type="button" class="dropdown-item" id="duplicateAssistant"><i class="bi bi-copy me-2"></i>Duplicate detection</button></li>
                        <li><button type="button" class="dropdown-item" id="loadSampleData"><i class="bi bi-stars me-2"></i>Load premium sample</button></li>
                        <li><button type="button" class="dropdown-item" id="openMobileDrawer"><i class="bi bi-layout-sidebar-inset me-2"></i>Open insights</button></li>
                    </ul>
                </div>
                <button type="button" class="btn studio-btn studio-btn-secondary" id="saveAndNewButton">
                    <i class="bi bi-plus-square"></i>
                    Save & New
                </button>
                <button type="submit" class="btn studio-btn studio-btn-primary" id="publishButton">
                    <i class="bi bi-rocket-takeoff"></i>
                    Save Product
                </button>
            </div>
        </div>

        <?php if ($savedAt): ?>
            <div class="studio-recovery-banner" role="status">
                <i class="bi bi-arrow-counterclockwise"></i>
                <span>Recovered a saved draft from <?= e(date('M d, Y H:i', (int) $savedAt)) ?> so you can continue where you left off.</span>
            </div>
        <?php endif; ?>

        <div class="studio-stepper-wrap">
            <div class="studio-stepper" id="studioStepper">
                <?php foreach ($stepItems as $index => $step): ?>
                    <button
                        type="button"
                        class="studio-stepper-item<?= $index === 0 ? ' active' : '' ?>"
                        data-step-target="section-<?= e($step['id']) ?>"
                        data-step-key="<?= e($step['id']) ?>"
                        aria-current="<?= $index === 0 ? 'step' : 'false' ?>"
                    >
                        <span class="studio-step-icon"><i class="bi <?= e($step['icon']) ?>"></i></span>
                        <span class="studio-step-label"><?= e($step['label']) ?></span>
                        <span class="studio-step-state" aria-hidden="true"></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <!-- v2: Global wizard progress rail synced with step completion -->
            <div class="studio-progress-rail" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e((string) $completeness) ?>" aria-label="Product wizard completion">
                <span class="studio-progress-rail__fill" id="studioProgressFill" style="width: <?= e((string) $completeness) ?>%"></span>
            </div>
            <div class="studio-progress-meta">
                <span>Product progress tracker</span>
                <strong id="studioProgressLabel"><?= e((string) $completeness) ?>% complete</strong>
            </div>
        </div>

        <div class="studio-layout">
            <main class="studio-main">
                <section class="studio-stat-grid">
                    <article class="studio-stat-card">
                        <span class="studio-stat-label">Go-live readiness</span>
                        <strong id="readinessValue"><?= e((string) $completeness) ?>%</strong>
                        <small>Based on product, pricing, copy and SEO completion.</small>
                    </article>
                    <article class="studio-stat-card">
                        <span class="studio-stat-label">Projected margin</span>
                        <strong id="marginSummary">0%</strong>
                        <small>Live from cost and price inputs.</small>
                    </article>
                    <article class="studio-stat-card">
                        <span class="studio-stat-label">Inventory health</span>
                        <strong id="inventoryStatusText">Healthy</strong>
                        <small>Automatically reacts to reorder thresholds.</small>
                    </article>
                    <article class="studio-stat-card">
                        <span class="studio-stat-label">SEO pulse</span>
                        <strong id="seoPulseValue">42</strong>
                        <small>Title, slug, tags and metadata score.</small>
                    </article>
                </section>

                <?php require __DIR__ . '/partials/basic-information.php'; ?>

                <?php require __DIR__ . '/partials/pricing-intelligence.php'; ?>
                <?php require __DIR__ . '/partials/inventory-management.php'; ?>
                <section id="section-warranty" class="studio-card studio-section" data-step-key="warranty">
                    <button type="button" class="studio-section-toggle" data-section-toggle="section-warranty-body">
                        <div>
                            <span class="studio-section-kicker">Step 7</span>
                            <h2>Warranty Experience</h2>
                            <p>Build a Dell-style support layer with coverage timeline, provider details and claim readiness.</p>
                        </div>
                        <span class="studio-section-tools">
                            <label class="studio-switch">
                                <input type="checkbox" id="enableWarranty" name="warranty_enabled" <?= studio_checked($form, 'warranty_enabled') ?>>
                                <span></span>
                            </label>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                    <div class="studio-section-body" id="section-warranty-body">
                        <div class="studio-warranty-summary">
                            <article class="studio-warranty-tile">
                                <span>Status</span>
                                <strong id="warrantyStatusText">Inactive</strong>
                            </article>
                            <article class="studio-warranty-tile">
                                <span>Coverage Ends</span>
                                <strong id="warrantyExpiryText">Pending</strong>
                            </article>
                            <article class="studio-warranty-tile">
                                <span>Provider</span>
                                <strong id="warrantyProviderText">Unassigned</strong>
                            </article>
                        </div>

                        <div class="studio-warranty-timeline">
                            <article class="studio-timeline-step active">
                                <strong>Activation</strong>
                                <span>Product registered with support profile</span>
                            </article>
                            <article class="studio-timeline-step" id="warrantyTimelineCoverage">
                                <strong>Coverage Window</strong>
                                <span>Start and expiry dates update automatically</span>
                            </article>
                            <article class="studio-timeline-step">
                                <strong>Claims</strong>
                                <span>Standard intake, verification and service routing</span>
                            </article>
                        </div>

                        <div class="studio-grid studio-grid-3 studio-warranty-fields" id="warrantyFields">
                            <div class="form-floating studio-floating">
                                <select class="form-select" id="warranty_type" name="warranty_type">
                                    <?php foreach (['manufacturer' => 'Manufacturer', 'seller' => 'Seller', 'replacement' => 'Replacement', 'extended' => 'Extended', 'amc' => 'AMC'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= studio_selected($form, 'warranty_type', $value) ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="warranty_type">Warranty Type</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" type="number" min="0" id="warranty_period" name="warranty_period" placeholder="Period" value="<?= e(studio_value($form, 'warranty_period', '12')) ?>">
                                <label for="warranty_period">Warranty Duration</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <select class="form-select" id="warranty_unit" name="warranty_unit">
                                    <?php foreach (['days' => 'Days', 'months' => 'Months', 'years' => 'Years'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= studio_selected($form, 'warranty_unit', $value) ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="warranty_unit">Unit</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" type="date" id="warranty_start_date" name="warranty_start_date" placeholder="Start date" value="<?= e(studio_value($form, 'warranty_start_date')) ?>">
                                <label for="warranty_start_date">Start Date</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" type="date" id="warranty_expiry" placeholder="Expiry date" value="" readonly>
                                <label for="warranty_expiry">Expiry Date</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="warranty_provider" name="warranty_provider" placeholder="Warranty provider" value="<?= e(studio_value($form, 'warranty_provider')) ?>">
                                <label for="warranty_provider">Warranty Provider</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="service_center_name" name="service_center_name" placeholder="Service center" value="<?= e(studio_value($form, 'service_center_name')) ?>">
                                <label for="service_center_name">Service Center</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="service_center_phone" name="service_center_phone" placeholder="Phone" value="<?= e(studio_value($form, 'service_center_phone')) ?>">
                                <label for="service_center_phone">Service Phone</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="service_center_email" name="service_center_email" placeholder="Email" value="<?= e(studio_value($form, 'service_center_email')) ?>">
                                <label for="service_center_email">Service Email</label>
                            </div>
                        </div>

                        <div class="studio-grid studio-grid-2">
                            <div class="form-floating studio-floating">
                                <textarea class="form-control studio-textarea-sm" id="warranty_coverage" name="warranty_coverage" placeholder="Coverage"><?= e(studio_value($form, 'warranty_coverage')) ?></textarea>
                                <label for="warranty_coverage">Coverage</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <textarea class="form-control studio-textarea-sm" id="warranty_terms" name="warranty_terms" placeholder="Terms"><?= e(studio_value($form, 'warranty_terms')) ?></textarea>
                                <label for="warranty_terms">Terms & Conditions</label>
                            </div>
                        </div>

                        <div class="studio-grid studio-grid-2">
                            <div class="form-floating studio-floating">
                                <textarea class="form-control studio-textarea-sm" id="warranty_claim_process" name="warranty_claim_process" placeholder="Claim process"><?= e(studio_value($form, 'warranty_claim_process')) ?></textarea>
                                <label for="warranty_claim_process">Claim Process</label>
                            </div>
                            <div class="studio-upload-card">
                                <div class="studio-upload-head">
                                    <strong>Warranty Upload Center</strong>
                                    <button type="button" class="btn studio-mini-btn" id="generateWarrantyCard"><i class="bi bi-qr-code"></i> QR Card</button>
                                </div>
                                <label class="studio-upload-zone studio-upload-zone-compact" for="warranty_document">
                                    <input type="file" id="warranty_document" name="warranty_document" accept="application/pdf,image/*">
                                    <i class="bi bi-file-earmark-arrow-up"></i>
                                    <span>Upload PDFs or image proof</span>
                                </label>
                                <div class="studio-claim-steps">
                                    <span>1. Validate purchase</span>
                                    <span>2. Run diagnostics</span>
                                    <span>3. Approve repair or replacement</span>
                                </div>
                            </div>
                        </div>
                        <div class="studio-grid studio-grid-3">
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="replacement_policy" name="replacement_policy" placeholder="Replacement policy" value="<?= e(studio_value($form, 'replacement_policy')) ?>">
                                <label for="replacement_policy">Replacement Policy</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="warranty_notes" name="warranty_notes" placeholder="Warranty notes" value="<?= e(studio_value($form, 'warranty_notes')) ?>">
                                <label for="warranty_notes">Warranty Notes</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="service_center_address" name="service_center_address" placeholder="Service address" value="<?= e(studio_value($form, 'service_center_address')) ?>">
                                <label for="service_center_address">Service Address</label>
                            </div>
                            <label class="studio-toggle-card compact">
                                <input type="checkbox" name="amc_support" id="amc_support" <?= studio_checked($form, 'amc_support') ?>>
                                <span>
                                    <strong>AMC Support</strong>
                                    <small>Offer annual maintenance extensions</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </section>

                <section id="section-variants" class="studio-card studio-section" data-step-key="variants">
                    <button type="button" class="studio-section-toggle" data-section-toggle="section-variants-body">
                        <div>
                            <span class="studio-section-kicker">Step 8</span>
                            <h2>Variant Matrix</h2>
                            <p>Generate merchandising combinations for color, size and material with instant matrix previews.</p>
                        </div>
                        <span class="studio-section-tools">
                            <span class="studio-chip">Config</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                    <div class="studio-section-body" id="section-variants-body">
                        <div class="studio-helper-strip">
                            <span><i class="bi bi-grid-3x3-gap"></i> Enter comma-separated options to generate a working variant matrix.</span>
                            <span><i class="bi bi-upc-scan"></i> Smart SKU generation and stock notes are prepared below.</span>
                        </div>
                        <div class="studio-grid studio-grid-3">
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="variant_colors" name="variant_colors" placeholder="Colors" value="<?= e(studio_value($form, 'variant_colors')) ?>">
                                <label for="variant_colors">Colors</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="variant_sizes" name="variant_sizes" placeholder="Sizes" value="<?= e(studio_value($form, 'variant_sizes')) ?>">
                                <label for="variant_sizes">Sizes</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="variant_materials" name="variant_materials" placeholder="Materials" value="<?= e(studio_value($form, 'variant_materials')) ?>">
                                <label for="variant_materials">Materials</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="variant_storage" name="variant_storage" placeholder="Storage" value="<?= e(studio_value($form, 'variant_storage')) ?>">
                                <label for="variant_storage">Storage</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="variant_models" name="variant_models" placeholder="Model" value="<?= e(studio_value($form, 'variant_models')) ?>">
                                <label for="variant_models">Model</label>
                            </div>
                        </div>
                        <div class="studio-inline-actions">
                            <button type="button" class="btn studio-mini-btn" id="generateVariantsButton"><i class="bi bi-grid"></i> Generate Variant Matrix</button>
                        </div>
                        <textarea class="form-control studio-textarea-sm" id="variant_notes" name="variant_notes" placeholder="Variant notes"><?= e(studio_value($form, 'variant_notes')) ?></textarea>
                        <div class="studio-variant-grid" id="variantMatrix"></div>
                        <div class="studio-bulk-editor">
                            <div class="studio-bulk-editor-head">
                                <strong>Bulk Variant Controls</strong>
                                <span>Apply price and stock defaults across generated combinations</span>
                            </div>
                            <div class="studio-grid studio-grid-3">
                                <div class="form-floating studio-floating">
                                    <input class="form-control" type="number" min="0" step="0.01" id="variant_bulk_price" placeholder="Variant price delta">
                                    <label for="variant_bulk_price">Price Delta</label>
                                </div>
                                <div class="form-floating studio-floating">
                                    <input class="form-control" type="number" min="0" id="variant_bulk_stock" placeholder="Variant stock">
                                    <label for="variant_bulk_stock">Default Stock</label>
                                </div>
                                <div class="form-floating studio-floating">
                                    <input class="form-control" id="variant_bulk_sku" placeholder="SKU prefix">
                                    <label for="variant_bulk_sku">SKU Prefix</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <?php require __DIR__ . '/partials/media-library.php'; ?>
                <section id="section-shipping" class="studio-card studio-section studio-physical-section" data-step-key="shipping" data-physical-only="true">
                    <button type="button" class="studio-section-toggle" data-section-toggle="section-shipping-body">
                        <div>
                            <span class="studio-section-kicker">Step 5</span>
                            <h2>Shipping & Fulfillment</h2>
                            <p>Configure dispatch readiness, package dimensions, shipping class and service expectations.</p>
                        </div>
                        <span class="studio-section-tools">
                            <span class="studio-chip">Logistics</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                    <div class="studio-section-body" id="section-shipping-body">
                        <div class="studio-grid studio-grid-4">
                            <div class="form-floating studio-floating">
                                <input class="form-control" type="number" min="0" step="0.01" id="shipping_weight" name="shipping_weight" placeholder="Weight" value="<?= e(studio_value($form, 'shipping_weight', '0')) ?>">
                                <label for="shipping_weight">Weight (kg)</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" type="number" min="0" step="0.01" id="shipping_length" name="shipping_length" placeholder="Length" value="<?= e(studio_value($form, 'shipping_length', '0')) ?>">
                                <label for="shipping_length">Length (cm)</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" type="number" min="0" step="0.01" id="shipping_width" name="shipping_width" placeholder="Width" value="<?= e(studio_value($form, 'shipping_width', '0')) ?>">
                                <label for="shipping_width">Width (cm)</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" type="number" min="0" step="0.01" id="shipping_height" name="shipping_height" placeholder="Height" value="<?= e(studio_value($form, 'shipping_height', '0')) ?>">
                                <label for="shipping_height">Height (cm)</label>
                            </div>
                        </div>
                        <div class="studio-grid studio-grid-3">
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="shipping_class" name="shipping_class" placeholder="Shipping class" value="<?= e(studio_value($form, 'shipping_class')) ?>">
                                <label for="shipping_class">Shipping Class</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="delivery_sla" name="delivery_sla" placeholder="Delivery SLA" value="<?= e(studio_value($form, 'delivery_sla', '2-4 business days')) ?>">
                                <label for="delivery_sla">Delivery SLA</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="return_window" name="return_window" placeholder="Return window" value="<?= e(studio_value($form, 'return_window', '14 days')) ?>">
                                <label for="return_window">Return Window</label>
                            </div>
                        </div>

                        <div class="studio-toggle-grid">
                            <label class="studio-toggle-card">
                                <input type="checkbox" name="requires_shipping" id="requires_shipping" <?= studio_checked($form, 'requires_shipping', true) ?>>
                                <span>
                                    <strong>Requires Shipping</strong>
                                    <small>Physical product with dispatch workflow</small>
                                </span>
                            </label>
                            <label class="studio-toggle-card">
                                <input type="checkbox" name="fragile" id="fragile" <?= studio_checked($form, 'fragile') ?>>
                                <span>
                                    <strong>Fragile</strong>
                                    <small>Enable special packaging rules</small>
                                </span>
                            </label>
                            <label class="studio-toggle-card">
                                <input type="checkbox" name="free_shipping" id="free_shipping" <?= studio_checked($form, 'free_shipping') ?>>
                                <span>
                                    <strong>Free Shipping</strong>
                                    <small>Override rate logic for campaigns</small>
                                </span>
                            </label>
                        </div>
                        <div class="studio-grid studio-grid-3">
                            <label class="studio-toggle-card compact">
                                <input type="checkbox" name="cod_support" id="cod_support" <?= studio_checked($form, 'cod_support') ?>>
                                <span>
                                    <strong>COD Support</strong>
                                    <small>Allow cash on delivery workflow</small>
                                </span>
                            </label>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="packaging_type" name="packaging_type" placeholder="Packaging type" value="<?= e(studio_value($form, 'packaging_type', 'Boxed')) ?>">
                                <label for="packaging_type">Packaging Type</label>
                            </div>
                            <label class="studio-toggle-card compact">
                                <input type="checkbox" name="digital_handoff" id="digital_handoff" <?= studio_checked($form, 'digital_handoff') ?>>
                                <span>
                                    <strong>Digital Handoff</strong>
                                    <small>Signals no physical packaging required</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </section>

                <section id="section-seo" class="studio-card studio-section" data-step-key="seo">
                    <button type="button" class="studio-section-toggle" data-section-toggle="section-seo-body">
                        <div>
                            <span class="studio-section-kicker">Step 6</span>
                            <h2>SEO & Discoverability</h2>
                            <p>Polish metadata, structured URL strategy and search intent with assisted copy generation.</p>
                        </div>
                        <span class="studio-section-tools">
                            <span class="studio-chip">Growth</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                    <div class="studio-section-body" id="section-seo-body">
                        <div class="studio-grid studio-grid-2">
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="seo_url" name="seo_url" placeholder="SEO URL" value="<?= e(studio_value($form, 'seo_url')) ?>">
                                <label for="seo_url">SEO URL</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="focus_keyword" name="focus_keyword" placeholder="Focus keyword" value="<?= e(studio_value($form, 'focus_keyword')) ?>">
                                <label for="focus_keyword">Focus Keyword</label>
                            </div>
                        </div>

                        <div class="studio-grid studio-grid-2">
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="meta_title" name="meta_title" placeholder="Meta title" value="<?= e(studio_value($form, 'meta_title')) ?>">
                                <label for="meta_title">Meta Title</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="meta_keywords" name="meta_keywords" placeholder="Meta keywords" value="<?= e(studio_value($form, 'meta_keywords')) ?>">
                                <label for="meta_keywords">Meta Keywords</label>
                            </div>
                        </div>
                        <div class="studio-grid studio-grid-2">
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="canonical_url" name="canonical_url" placeholder="Canonical URL" value="<?= e(studio_value($form, 'canonical_url')) ?>">
                                <label for="canonical_url">Canonical URL</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="campaign_tags" name="campaign_tags" placeholder="Campaign tags" value="<?= e(studio_value($form, 'campaign_tags')) ?>">
                                <label for="campaign_tags">Campaign Tags</label>
                            </div>
                        </div>

                        <div class="form-floating studio-floating">
                            <textarea class="form-control studio-textarea-sm" id="meta_description" name="meta_description" placeholder="Meta description"><?= e(studio_value($form, 'meta_description')) ?></textarea>
                            <label for="meta_description">Meta Description</label>
                        </div>
                        <div class="studio-grid studio-grid-3">
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="cross_sell" name="cross_sell" placeholder="Cross-sell" value="<?= e(studio_value($form, 'cross_sell')) ?>">
                                <label for="cross_sell">Cross Sell</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="upsell" name="upsell" placeholder="Upsell" value="<?= e(studio_value($form, 'upsell')) ?>">
                                <label for="upsell">Upsell</label>
                            </div>
                            <div class="form-floating studio-floating">
                                <input class="form-control" id="related_products" name="related_products" placeholder="Related products" value="<?= e(studio_value($form, 'related_products')) ?>">
                                <label for="related_products">Related Products</label>
                            </div>
                        </div>
                        <div class="studio-search-preview">
                            <div>
                                <span id="googleSnippetUrl">https://example.com/<?= e(studio_value($form, 'seo_url', 'product-slug')) ?></span>
                                <strong id="googleSnippetTitle">SEO title preview</strong>
                                <p id="googleSnippetDescription">Meta description preview appears here as the user types content.</p>
                            </div>
                        </div>

                        <div class="studio-inline-actions">
                            <button type="button" class="btn studio-mini-btn" id="aiSeoButton"><i class="bi bi-graph-up-arrow"></i> Auto SEO</button>
                            <button type="button" class="btn studio-mini-btn" id="aiTagsButton"><i class="bi bi-tags"></i> Suggest Tags</button>
                        </div>
                    </div>
                </section>

                <section id="section-review" class="studio-card studio-section" data-step-key="review">
                    <button type="button" class="studio-section-toggle" data-section-toggle="section-review-body">
                        <div>
                            <span class="studio-section-kicker">Step 9</span>
                            <h2>Review & Launch</h2>
                            <p>Verify launch signals, quality checks, publish readiness and handoff notes before go-live.</p>
                        </div>
                        <span class="studio-section-tools">
                            <span class="studio-chip">Final</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </button>
                    <div class="studio-section-body" id="section-review-body">
                        <div class="studio-review-grid">
                            <article class="studio-review-item"><i class="bi bi-check2-circle"></i><span>Name, SKU and taxonomy aligned</span></article>
                            <article class="studio-review-item"><i class="bi bi-check2-circle"></i><span>Price, tax and margin validated</span></article>
                            <article class="studio-review-item"><i class="bi bi-check2-circle"></i><span>Inventory and warranty configured</span></article>
                            <article class="studio-review-item"><i class="bi bi-check2-circle"></i><span>Media and SEO ready for release</span></article>
                        </div>
                        <div class="studio-submit-strip">
                            <button type="button" class="btn studio-btn studio-btn-ghost" id="reviewDraftButton"><i class="bi bi-save2"></i> Save Draft</button>
                            <button type="submit" class="btn studio-btn studio-btn-primary"><i class="bi bi-send-check"></i> Publish Product</button>
                        </div>
                    </div>
                </section>
            </main>

            <aside class="studio-sidebar d-none d-lg-block">
                <div class="studio-sidebar-stack">
                    <section class="studio-card studio-sidebar-card">
                        <div class="studio-card-head">
                            <h3>Live Preview</h3>
                            <button type="button" class="btn studio-mini-btn" id="refreshPreview"><i class="bi bi-arrow-repeat"></i></button>
                        </div>
                        <div class="studio-product-preview">
                            <div class="studio-preview-media" id="previewMediaPane">
                                <div id="previewMediaFallback"><i class="bi bi-image"></i></div>
                            </div>
                            <div class="studio-preview-body">
                                <div class="studio-preview-status">
                                    <span class="studio-badge studio-badge-info" id="previewStatusBadge">Draft</span>
                                    <span class="studio-badge studio-badge-dark" id="previewTypeBadge">Simple</span>
                                </div>
                                <h4 id="previewName">Untitled product</h4>
                                <p id="previewShortDescription">Add a short summary to see your listing narrative here.</p>
                                <div class="studio-preview-meta">
                                    <span id="previewSku">SKU pending</span>
                                    <strong id="previewPrice">$0.00</strong>
                                </div>
                                <div class="studio-preview-foot">
                                    <span id="previewCategory">Category pending</span>
                                    <span id="previewStock">Stock status pending</span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="studio-card studio-sidebar-card studio-skeleton-wrap" id="analyticsCard">
                        <div class="studio-card-head">
                            <h3>Analytics</h3>
                            <span class="studio-chip">Real-time</span>
                        </div>
                        <div class="studio-skeleton" data-skeleton></div>
                        <div class="studio-skeleton" data-skeleton></div>
                        <div class="studio-sidebar-metrics">
                            <article><span>Profit</span><strong id="sideProfitMetric">0%</strong></article>
                            <article><span>Revenue</span><strong id="sideRevenueMetric">$0</strong></article>
                            <article><span>Stock Risk</span><strong id="sideStockMetric">Low</strong></article>
                            <article><span>SEO Score</span><strong id="sideSeoMetric">42</strong></article>
                            <article><span>Warranty</span><strong id="sideWarrantyMetric">Off</strong></article>
                        </div>
                    </section>

                    <section class="studio-card studio-sidebar-card">
                        <div class="studio-card-head">
                            <h3>Completeness</h3>
                            <span class="studio-chip" id="completionChip"><?= e((string) $completeness) ?>%</span>
                        </div>
                        <div class="studio-completion">
                            <div class="studio-ring" style="--progress: <?= e((string) $completeness) ?>">
                                <strong id="completionRingValue"><?= e((string) $completeness) ?>%</strong>
                            </div>
                            <div class="studio-completion-copy">
                                <p>Ready products tend to have pricing, inventory, media, and metadata completed together.</p>
                                <ul>
                                    <li id="checkCopyStatus">Description pending refinement</li>
                                    <li id="checkSeoStatus">SEO needs attention</li>
                                    <li id="checkMediaStatus">Media not uploaded yet</li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section class="studio-card studio-sidebar-card">
                        <div class="studio-card-head">
                            <h3>Quick Actions</h3>
                            <span class="studio-chip">Ops</span>
                        </div>
                        <div class="studio-ai-stack">
                            <button type="button" class="btn studio-ai-btn" id="quickDuplicate"><i class="bi bi-copy"></i> Duplicate product</button>
                            <button type="button" class="btn studio-ai-btn" id="quickArchive"><i class="bi bi-archive"></i> Archive draft</button>
                            <button type="button" class="btn studio-ai-btn" id="quickExport"><i class="bi bi-box-arrow-up-right"></i> Export brief</button>
                            <button type="button" class="btn studio-ai-btn" id="quickTemplate"><i class="bi bi-file-earmark-richtext"></i> Save as template</button>
                        </div>
                    </section>

                    <section class="studio-card studio-sidebar-card">
                        <div class="studio-card-head">
                            <h3>AI Assistant</h3>
                            <span class="studio-chip">Studio Copilot</span>
                        </div>
                        <div class="studio-ai-stack">
                            <button type="button" class="btn studio-ai-btn" data-ai-action="description"><i class="bi bi-stars"></i> Generate description</button>
                            <button type="button" class="btn studio-ai-btn" data-ai-action="tags"><i class="bi bi-tags"></i> Suggest tags</button>
                            <button type="button" class="btn studio-ai-btn" data-ai-action="seo"><i class="bi bi-search-heart"></i> Auto SEO</button>
                            <button type="button" class="btn studio-ai-btn" data-ai-action="price"><i class="bi bi-cash-coin"></i> Price recommendation</button>
                            <button type="button" class="btn studio-ai-btn" data-ai-action="category"><i class="bi bi-diagram-2"></i> Category suggestion</button>
                            <button type="button" class="btn studio-ai-btn" data-ai-action="duplicate"><i class="bi bi-intersect"></i> Duplicate detection</button>
                        </div>
                    </section>
                    <section class="studio-card studio-sidebar-card">
                        <div class="studio-card-head">
                            <h3>Product Timeline</h3>
                            <span class="studio-chip">Activity</span>
                        </div>
                        <div class="studio-timeline" id="productTimeline">
                            <div class="studio-timeline-item">
                                <i class="bi bi-pencil-square"></i>
                                <span>Draft workspace opened — complete each wizard step to publish.</span>
                            </div>
                            <?php if ($savedAt): ?>
                            <div class="studio-timeline-item">
                                <i class="bi bi-cloud-check"></i>
                                <span>Draft recovered <?= e(date('M d, H:i', (int) $savedAt)) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="studio-timeline-item" id="timelineAutosaveItem" hidden>
                                <i class="bi bi-arrow-repeat"></i>
                                <span id="timelineAutosaveText">Auto-save pending</span>
                            </div>
                        </div>
                    </section>
                </div>
            </aside>
        </div>

        <!-- v2: Sticky desktop save toolbar -->
        <div class="studio-save-rail" aria-label="Quick save actions">
            <div>
                <strong>Draft workspace</strong>
                <div class="studio-field-meta" style="margin-top:0.2rem">
                    <span id="saveRailStatus">Unsaved changes tracked</span>
                </div>
            </div>
            <div class="studio-actions" style="margin:0">
                <button type="button" class="btn studio-btn studio-btn-ghost" id="saveRailDraft">
                    <i class="bi bi-save2"></i> Save draft
                </button>
                <button type="button" class="btn studio-btn studio-btn-primary" id="saveRailPublish">
                    <i class="bi bi-rocket-takeoff"></i> Publish
                </button>
            </div>
        </div>
    </form>

    <!-- v2: Floating action cluster -->
    <div class="studio-fab-cluster" id="studioFabCluster" aria-label="Floating quick actions">
        <div class="studio-fab-menu" role="menu">
            <button type="button" id="fabSave" role="menuitem"><i class="bi bi-save2 me-2"></i>Save draft</button>
            <button type="button" id="fabPublish" role="menuitem"><i class="bi bi-rocket-takeoff me-2"></i>Publish</button>
            <button type="button" id="fabUndo" role="menuitem"><i class="bi bi-arrow-counterclockwise me-2"></i>Undo</button>
            <button type="button" id="fabRedo" role="menuitem"><i class="bi bi-arrow-clockwise me-2"></i>Redo</button>
            <button type="button" id="fabTop" role="menuitem"><i class="bi bi-arrow-up-circle me-2"></i>Back to top</button>
        </div>
        <button type="button" class="studio-fab" id="studioFabToggle" aria-expanded="false" aria-controls="studioFabCluster" title="Quick actions">
            <i class="bi bi-lightning-charge-fill"></i>
        </button>
        <button type="button" class="studio-fab studio-fab--secondary" id="fabTopQuick" title="Scroll to top" aria-label="Scroll to top">
            <i class="bi bi-arrow-up"></i>
        </button>
    </div>

    <div class="studio-mobile-actions d-lg-none">
        <button type="button" class="btn studio-btn studio-btn-ghost" id="mobileInsightsButton" data-bs-toggle="offcanvas" data-bs-target="#mobileStudioDrawer" aria-controls="mobileStudioDrawer">
            <i class="bi bi-layout-sidebar"></i>
            Insights
        </button>
        <button type="button" class="btn studio-btn studio-btn-ghost" id="mobileDraftButton">
            <i class="bi bi-save2"></i>
            Draft
        </button>
        <button type="button" class="btn studio-btn studio-btn-secondary" id="mobileSaveNewButton">
            <i class="bi bi-plus-square"></i>
            Save & New
        </button>
        <button type="submit" class="btn studio-btn studio-btn-primary" form="product-form">
            <i class="bi bi-floppy"></i>
            Save
        </button>
    </div>

    <div class="offcanvas offcanvas-bottom studio-offcanvas" tabindex="-1" id="mobileStudioDrawer" aria-labelledby="mobileStudioDrawerLabel">
        <div class="offcanvas-header">
            <h5 id="mobileStudioDrawerLabel">Product Insights</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="studio-sidebar-stack">
                <section class="studio-card studio-sidebar-card">
                    <div class="studio-card-head"><h3>Preview</h3></div>
                    <div class="studio-product-preview compact">
                        <div class="studio-preview-body">
                            <h4 id="mobilePreviewName">Untitled product</h4>
                            <p id="mobilePreviewPrice">$0.00</p>
                            <span id="mobilePreviewCategory">Category pending</span>
                        </div>
                    </div>
                </section>
                <section class="studio-card studio-sidebar-card">
                    <div class="studio-card-head"><h3>Quick actions</h3></div>
                    <div class="studio-ai-stack">
                        <button type="button" class="btn studio-ai-btn" data-ai-action="description">Generate description</button>
                        <button type="button" class="btn studio-ai-btn" data-ai-action="seo">Auto SEO</button>
                        <button type="button" class="btn studio-ai-btn" data-ai-action="duplicate">Detect duplicates</button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/layout_end.php'; ?>
