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
        if (!csrf_verify(isset($_POST['_csrf']) ? (string) $_POST['_csrf'] : null)) {
            if ($this->isAjax()) $this->jsonResponse(['error' => 'Your session expired. Refresh the page and try again.'], 419);
            flash_set('error', 'Your session expired. Please try again.');
            redirect('/modules/products/add.php');
        }
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
        $sku = trim((string) ($_POST['sku'] ?? ''));
        $pricesValid = isset($_POST['cost_price'], $_POST['selling_price'])
            && is_numeric($_POST['cost_price']) && is_numeric($_POST['selling_price'])
            && (float) $_POST['cost_price'] >= 0 && (float) $_POST['selling_price'] >= 0;
        $stockFields = ['opening_stock', 'current_stock', 'minimum_stock', 'reorder_level', 'warranty_period'];
        $stockValid = true;
        foreach ($stockFields as $field) {
            if (isset($_POST[$field]) && (!is_numeric($_POST[$field]) || (float) $_POST[$field] < 0 || (float) $_POST[$field] !== (float) (int) $_POST[$field])) {
                $stockValid = false;
                break;
            }
        }
        if ($name === '' || $sku === '' || !$pricesValid || !$stockValid) {
            $_SESSION[self::DRAFT_KEY] = ['data' => $draftPayload, 'saved_at' => time()];
            if ($this->isAjax()) {
                $this->jsonResponse(['error' => 'Enter a product code, product name, valid purchase and selling prices, and non-negative whole stock and warranty values.'], 422);
            }
            flash_set('error', 'Enter a product code, product name, valid purchase and selling prices, and non-negative whole stock and warranty values.');
            redirect('/modules/products/add.php');
        }

        try {
            $productId = $this->persistProduct($_POST, $name);
            unset($_SESSION[self::DRAFT_KEY]);
            if ($this->isAjax()) {
                $redirect = $intent === 'publish_new'
                    ? base_url('modules/products/add.php')
                    : ($intent === 'publish_view'
                        ? base_url('modules/products/view.php?id=' . $productId)
                        : base_url('modules/products/list.php'));
                $this->jsonResponse(['success' => true, 'id' => $productId, 'redirect' => $redirect]);
            }
            flash_set('success', 'Product created.');
            redirect($intent === 'publish_new' ? '/modules/products/add.php' : ($intent === 'publish_view' ? '/modules/products/view.php?id=' . $productId : '/modules/products/list.php'));
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $_SESSION[self::DRAFT_KEY] = ['data' => $draftPayload, 'saved_at' => time()];
            if ($this->isAjax()) {
                $code = str_contains(strtolower($e->getMessage()), 'already in use') ? 409 : 500;
                $this->jsonResponse(['error' => $e->getMessage()], $code);
            }
            flash_set('error', 'Error creating product: ' . $e->getMessage());
            redirect('/modules/products/add.php');
        }
    }

    /** @param array<string, mixed> $post */
    private function persistProduct(array $post, string $name): int
    {
        $sku = trim((string) ($post['sku'] ?? ''));
        $productColumns = ProductSchema::columns($this->pdo, 'products');
        if (!in_array('sku', $productColumns, true)) {
            throw new RuntimeException('Product codes require the products.sku column. No database changes were made.');
        }
        $skuCheck = $this->pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
        $skuCheck->execute([$sku]);
        if ($skuCheck->fetchColumn()) throw new RuntimeException('That product code is already in use.');
        $slug = ProductForm::slug($name) . '-' . ProductForm::slug($sku);
        $warrantyDuration = ProductForm::int($post, 'warranty_period', 0);
        $warrantyUnit = 'days';
        $warrantyStart = $warrantyDuration > 0 ? date('Y-m-d') : null;
        $warrantyEnd = null;
        if ($warrantyStart && $warrantyDuration > 0) {
            $date = new DateTime($warrantyStart);
            $date->modify('+' . $warrantyDuration . ' days');
            $warrantyEnd = $date->format('Y-m-d');
        }
        $openingStock = ProductForm::int($post, 'opening_stock');
        $currentStock = ProductForm::int($post, 'current_stock', $openingStock);
        $minimumStock = ProductForm::int($post, 'minimum_stock');
        $serialNumber = trim((string) ($post['serial_number'] ?? ''));
        $description = trim((string) ($post['description'] ?? ''));
        if ($serialNumber !== '' && !in_array('serial_number', $productColumns, true)) {
            $description = trim($description . ($description !== '' ? "\n" : '') . 'Serial Number: ' . $serialNumber);
        }

        $this->pdo->beginTransaction();

        $productId = ProductSchema::insertFiltered($this->pdo, 'products', [
            'sku' => $sku,
            'name' => $name,
            'slug' => $slug,
            'description' => $description !== '' ? $description : null,
            'serial_number' => $serialNumber !== '' ? $serialNumber : null,
            'status' => 'active',
            'cost_price' => ProductForm::num($post, 'cost_price'),
            'price' => ProductForm::num($post, 'selling_price'),
            'selling_price' => ProductForm::num($post, 'selling_price'),
            'warranty_enabled' => $warrantyDuration > 0 ? 1 : 0,
            'warranty_period' => $warrantyDuration,
            'warranty_duration_days' => $warrantyDuration,
            'opening_stock' => $openingStock,
            'stock' => $currentStock,
            'current_stock' => $currentStock,
            'min_stock' => $minimumStock,
            'minimum_stock' => $minimumStock,
            'low_stock_threshold' => $minimumStock,
            'reorder_level' => ProductForm::int($post, 'reorder_level'),
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
                'current_stock' => ProductForm::int($post, 'current_stock', $openingStock),
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

        if ($warrantyDuration > 0 && ProductSchema::tableExists($this->pdo, 'product_warranty')) {
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

        $imageNames = $_FILES['images']['name'] ?? [];
        $hasImageUploads = is_array($imageNames)
            && array_filter($imageNames, static fn($fileName): bool => trim((string) $fileName) !== '') !== [];
        if ($hasImageUploads) {
            $hasImageTable = ProductSchema::tableExists($this->pdo, 'product_images');
            $hasImageColumn = in_array('image_path', $productColumns, true);
            if (!$hasImageTable && !$hasImageColumn) throw new RuntimeException('This product database has no image field configured.');
            $uploadDir = dirname(__DIR__, 3) . '/uploads/products/' . $productId;
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) throw new RuntimeException('Unable to create the product image folder.');
            }
            $mimeExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            foreach ($_FILES['images']['name'] as $index => $nameFile) {
                $tmp = (string) ($_FILES['images']['tmp_name'][$index] ?? '');
                if ($tmp === '' || !is_uploaded_file($tmp)) continue;
                if ((int) ($_FILES['images']['size'][$index] ?? 0) > 10 * 1024 * 1024) throw new RuntimeException('Product images must be 10 MB or smaller.');
                $mime = $finfo->file($tmp);
                if (!isset($mimeExt[$mime])) throw new RuntimeException('Use a JPG, PNG, WebP, or GIF product image.');
                $filename = bin2hex(random_bytes(12)) . '.' . $mimeExt[$mime];
                $target = $uploadDir . '/' . $filename;
                if (!move_uploaded_file($tmp, $target)) throw new RuntimeException('Unable to save the product image.');
                $path = 'uploads/products/' . $productId . '/' . $filename;
                if ($hasImageTable) {
                    ProductSchema::insertFiltered($this->pdo, 'product_images', [
                        'product_id' => $productId,
                        'path' => $path,
                        'is_thumbnail' => $index === 0 ? 1 : 0,
                        'sort_order' => $index,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } elseif ($index === 0) {
                    $updateImage = $this->pdo->prepare('UPDATE products SET image_path = ? WHERE id = ?');
                    $updateImage->execute([$path, $productId]);
                }
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

        $lookups = [];
        $pageTitle = 'Add Product';
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
