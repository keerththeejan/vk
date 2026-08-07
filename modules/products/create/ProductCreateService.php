<?php
declare(strict_types=1);

require_once __DIR__ . '/Support/ProductSchema.php';
require_once __DIR__ . '/Support/ProductForm.php';

final class ProductCreateService
{
    private const DRAFT_KEY = 'product_create_draft_v2';

    public function __construct(private PDO $pdo)
    {
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            return;
        }
        $this->renderPage();
    }

    private function isAjax(): bool
    {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    private function handlePost(): void
    {
        $intent = (string) ($_POST['intent'] ?? 'publish');
        $draftPayload = ProductForm::draftPayload($_POST);

        if ($this->isAjax() && $intent === 'check_sku') {
            $sku = trim((string) ($_POST['sku'] ?? ''));
            if ($sku === '') {
                $this->jsonResponse(['success' => true, 'exists' => false]);
            }
            $stmt = $this->pdo->prepare('SELECT id, name FROM products WHERE sku = ? LIMIT 1');
            $stmt->execute([$sku]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $this->jsonResponse(['success' => true, 'exists' => (bool) $found, 'product' => $found]);
        }

        if ($this->isAjax() && $intent === 'detect_duplicate') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $sku = trim((string) ($_POST['sku'] ?? ''));
            $results = [];
            if ($name !== '') {
                $stmt = $this->pdo->prepare('SELECT id, name, sku FROM products WHERE name LIKE ? ORDER BY id DESC LIMIT 5');
                $stmt->execute(['%' . $name . '%']);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif ($sku !== '') {
                $stmt = $this->pdo->prepare('SELECT id, name, sku FROM products WHERE sku LIKE ? ORDER BY id DESC LIMIT 5');
                $stmt->execute(['%' . $sku . '%']);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $this->jsonResponse(['success' => true, 'matches' => $results]);
        }

        if (in_array($intent, ['draft', 'autosave'], true)) {
            $_SESSION[self::DRAFT_KEY] = ['data' => $draftPayload, 'saved_at' => time()];
            if ($this->isAjax()) {
                $this->jsonResponse([
                    'success' => true,
                    'intent' => $intent,
                    'saved_at' => date(DATE_ATOM),
                    'completeness' => ProductForm::completeness($draftPayload),
                ]);
            }
            flash_set('success', 'Draft saved.');
            redirect('/modules/products/add.php');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION[self::DRAFT_KEY] = ['data' => $draftPayload, 'saved_at' => time()];
            if ($this->isAjax()) {
                $this->jsonResponse(['error' => 'Product name is required.'], 422);
            }
            flash_set('error', 'Product name is required.');
            redirect('/modules/products/add.php');
        }

        try {
            $productId = $this->persistProduct($_POST, $name);
            unset($_SESSION[self::DRAFT_KEY]);
            if ($this->isAjax()) {
                $redirect = $intent === 'publish_new'
                    ? base_url('modules/products/add.php')
                    : base_url('modules/products/list.php');
                $this->jsonResponse(['success' => true, 'id' => $productId, 'redirect' => $redirect]);
            }
            flash_set('success', 'Product created.');
            redirect($intent === 'publish_new' ? '/modules/products/add.php' : '/modules/products/list.php');
        } catch (Throwable $e) {
            $_SESSION[self::DRAFT_KEY] = ['data' => $draftPayload, 'saved_at' => time()];
            if ($this->isAjax()) {
                $this->jsonResponse(['error' => $e->getMessage()], 500);
            }
            flash_set('error', 'Error creating product: ' . $e->getMessage());
            redirect('/modules/products/add.php');
        }
    }

    /** @param array<string, mixed> $post */
    private function persistProduct(array $post, string $name): int
    {
        $sku = ProductForm::text($post, 'sku');
        $slug = ProductForm::text($post, 'seo_url') ?: ProductForm::slug($name);
        $warrantyStart = ProductForm::date($post, 'warranty_start_date');
        $warrantyDuration = ProductForm::int($post, 'warranty_period', 12);
        $warrantyUnit = (string) ($post['warranty_unit'] ?? 'months');
        $warrantyEnd = null;
        if ($warrantyStart && $warrantyDuration > 0) {
            $date = new DateTime($warrantyStart);
            $date->modify('+' . $warrantyDuration . ' ' . $warrantyUnit);
            $warrantyEnd = $date->format('Y-m-d');
        }

        $this->pdo->beginTransaction();

        $productId = ProductSchema::insertFiltered($this->pdo, 'products', [
            'sku' => $sku,
            'barcode' => ProductForm::text($post, 'barcode'),
            'qr_code' => ProductForm::text($post, 'qr_code'),
            'name' => $name,
            'subtitle' => ProductForm::text($post, 'subtitle'),
            'slug' => $slug,
            'product_type' => ProductForm::text($post, 'product_type') ?: 'simple',
            'brand_id' => ProductForm::nullableId($post, 'brand_id'),
            'category_id' => ProductForm::nullableId($post, 'category_id'),
            'subcategory_id' => ProductForm::nullableId($post, 'subcategory_id'),
            'supplier_id' => ProductForm::nullableId($post, 'supplier_id'),
            'manufacturer_id' => ProductForm::nullableId($post, 'manufacturer_id'),
            'unit_type' => ProductForm::text($post, 'unit_type') ?: 'piece',
            'hsn_sac_code' => ProductForm::text($post, 'hsn_sac_code'),
            'country_of_origin' => ProductForm::text($post, 'country_of_origin'),
            'short_description' => ProductForm::text($post, 'short_description'),
            'description' => ProductForm::text($post, 'description'),
            'meta_title' => ProductForm::text($post, 'meta_title'),
            'meta_description' => ProductForm::text($post, 'meta_description'),
            'meta_keywords' => ProductForm::text($post, 'meta_keywords'),
            'seo_url' => $slug,
            'status' => ProductForm::text($post, 'status') ?: 'active',
            'featured' => !empty($post['featured']) ? 1 : 0,
            'is_digital' => !empty($post['is_digital']) ? 1 : 0,
            'requires_shipping' => !empty($post['requires_shipping']) ? 1 : 0,
            'tax_class_id' => ProductForm::nullableId($post, 'tax_class_id'),
            'cost_price' => ProductForm::num($post, 'cost_price'),
            'selling_price' => ProductForm::num($post, 'selling_price'),
            'wholesale_price' => ProductForm::num($post, 'wholesale_price'),
            'currency' => ProductForm::text($post, 'currency') ?: 'USD',
            'warranty_enabled' => !empty($post['warranty_enabled']) ? 1 : 0,
            'warranty_duration_days' => !empty($post['warranty_enabled']) ? max(0, $warrantyDuration * 30) : null,
            'opening_stock' => ProductForm::int($post, 'opening_stock'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$productId) {
            throw new RuntimeException('Unable to save product — no compatible columns found.');
        }

        if (ProductSchema::tableExists($this->pdo, 'product_pricing')) {
            ProductSchema::insertFiltered($this->pdo, 'product_pricing', [
                'product_id' => $productId,
                'cost_price' => ProductForm::num($post, 'cost_price'),
                'selling_price' => ProductForm::num($post, 'selling_price'),
                'wholesale_price' => ProductForm::num($post, 'wholesale_price'),
                'dealer_price' => ProductForm::num($post, 'dealer_price'),
                'distributor_price' => ProductForm::num($post, 'distributor_price'),
                'msrp' => ProductForm::num($post, 'msrp'),
                'currency' => ProductForm::text($post, 'currency') ?: 'USD',
                'tax_rate' => ProductForm::num($post, 'tax_rate'),
                'vat_gst' => ProductForm::num($post, 'vat_gst'),
                'profit_margin' => ProductForm::num($post, 'profit_margin'),
                'discount_type' => ProductForm::text($post, 'discount_type') ?: 'none',
                'discount_value' => ProductForm::num($post, 'discount_value'),
                'promotional_price' => ProductForm::num($post, 'promotional_price'),
                'promo_start_date' => ProductForm::date($post, 'promo_start_date'),
                'promo_end_date' => ProductForm::date($post, 'promo_end_date'),
                'price_valid_from' => ProductForm::date($post, 'price_valid_from'),
                'price_valid_to' => ProductForm::date($post, 'price_valid_to'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (ProductSchema::tableExists($this->pdo, 'product_inventory')) {
            ProductSchema::insertFiltered($this->pdo, 'product_inventory', [
                'product_id' => $productId,
                'warehouse_id' => ProductForm::nullableId($post, 'warehouse_id'),
                'opening_stock' => ProductForm::int($post, 'opening_stock'),
                'current_stock' => ProductForm::int($post, 'current_stock') ?: ProductForm::int($post, 'opening_stock'),
                'minimum_stock' => ProductForm::int($post, 'minimum_stock'),
                'reorder_level' => ProductForm::int($post, 'reorder_level'),
                'rack_location' => ProductForm::text($post, 'rack_location'),
                'bin_number' => ProductForm::text($post, 'bin_number'),
                'batch_number' => ProductForm::text($post, 'batch_number'),
                'serial_number' => ProductForm::text($post, 'serial_number'),
                'expiry_date' => ProductForm::date($post, 'expiry_date'),
                'manufacturing_date' => ProductForm::date($post, 'manufacturing_date'),
                'last_stock_update' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (!empty($post['warranty_enabled']) && ProductSchema::tableExists($this->pdo, 'product_warranty')) {
            $docPath = null;
            if (!empty($_FILES['warranty_document']['name']) && is_uploaded_file($_FILES['warranty_document']['tmp_name'])) {
                $ext = pathinfo((string) $_FILES['warranty_document']['name'], PATHINFO_EXTENSION);
                $dir = dirname(__DIR__, 3) . '/uploads/warranties';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $filename = uniqid('warranty_', true) . '.' . ($ext ?: 'pdf');
                move_uploaded_file($_FILES['warranty_document']['tmp_name'], $dir . '/' . $filename);
                $docPath = 'uploads/warranties/' . $filename;
            }
            ProductSchema::insertFiltered($this->pdo, 'product_warranty', [
                'product_id' => $productId,
                'warranty_enabled' => 1,
                'warranty_type' => ProductForm::text($post, 'warranty_type') ?: 'manufacturer',
                'warranty_duration' => $warrantyDuration,
                'warranty_unit' => $warrantyUnit,
                'warranty_start_date' => $warrantyStart,
                'warranty_end_date' => $warrantyEnd,
                'warranty_coverage' => ProductForm::text($post, 'warranty_coverage'),
                'warranty_terms' => ProductForm::text($post, 'warranty_terms'),
                'claim_procedure' => ProductForm::text($post, 'warranty_claim_process'),
                'warranty_provider' => ProductForm::text($post, 'warranty_provider'),
                'service_center_name' => ProductForm::text($post, 'service_center_name'),
                'service_center_phone' => ProductForm::text($post, 'service_center_phone'),
                'service_center_email' => ProductForm::text($post, 'service_center_email'),
                'warranty_document_path' => $docPath,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if (ProductSchema::tableExists($this->pdo, 'product_tags')) {
            $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($post['product_tags'] ?? '')))));
            foreach ($tags as $tag) {
                if ($tag === '') {
                    continue;
                }
                ProductSchema::insertFiltered($this->pdo, 'product_tags', [
                    'product_id' => $productId,
                    'tag_name' => $tag,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if (!empty($_FILES['images']['name']) && is_array($_FILES['images']['name']) && ProductSchema::tableExists($this->pdo, 'product_images')) {
            $uploadDir = dirname(__DIR__, 3) . '/uploads/products/' . $productId;
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
                ProductSchema::insertFiltered($this->pdo, 'product_images', [
                    'product_id' => $productId,
                    'path' => 'uploads/products/' . $productId . '/' . basename($target),
                    'is_thumbnail' => $index === 0 ? 1 : 0,
                    'sort_order' => $index,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->pdo->commit();
        return $productId;
    }

    private function renderPage(): void
    {
        $draft = $_SESSION[self::DRAFT_KEY] ?? ['data' => [], 'saved_at' => null];
        $form = array_merge($draft['data'] ?? [], $_POST);
        $savedAt = $draft['saved_at'] ?? null;
        $completeness = ProductForm::completeness($form);

        $lookups = [
            'brands' => ProductSchema::queryOptions($this->pdo, 'SELECT id, name FROM brands ORDER BY name'),
            'categories' => ProductSchema::queryOptions($this->pdo, 'SELECT id, name, COALESCE(parent_id, 0) AS parent_id FROM categories ORDER BY name'),
            'suppliers' => ProductSchema::queryOptions($this->pdo, 'SELECT id, name FROM suppliers ORDER BY name'),
            'manufacturers' => ProductSchema::queryOptions($this->pdo, 'SELECT id, name FROM manufacturers ORDER BY name'),
            'warehouses' => ProductSchema::queryOptions($this->pdo, 'SELECT id, name FROM warehouses ORDER BY name'),
            'taxClasses' => ProductSchema::queryOptions($this->pdo, 'SELECT id, name, COALESCE(rate, 0) AS rate FROM tax_classes ORDER BY name'),
        ];

        $pageTitle = 'Product Wizard — Create Product';
        $pcBase = base_url('modules/products/add.php');
        $pcAsset = static fn(string $path): string => base_url('assets/product-create/' . $path) . '?v=' . (string) @filemtime(dirname(__DIR__, 3) . '/assets/product-create/' . $path);

        $extraHead =
            '<link rel="stylesheet" href="' . e($pcAsset('product-create.css')) . '">' .
            '<meta name="pc-api-url" content="' . e($pcBase) . '">' .
            '<meta name="pc-upload-url" content="' . e(base_url('api/product_media_upload.php')) . '">';

        $extraScripts =
            '<script src="' . e($pcAsset('js/product-create-api.js')) . '" defer></script>' .
            '<script src="' . e($pcAsset('js/product-create-app.js')) . '" defer></script>';

        require_once dirname(__DIR__, 3) . '/includes/layout_start.php';
        require __DIR__ . '/views/index.php';
        require_once dirname(__DIR__, 3) . '/includes/layout_end.php';
    }
}
