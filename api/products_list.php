<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

/** Build the inventory response against either the legacy or extended products schema. */
try {
    $pdo = db();
    $columns = array_column($pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC), 'Field');
    $has = static fn(string $column): bool => in_array($column, $columns, true);
    $tables = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
    $hasTable = static fn(string $table): bool => in_array($table, $tables, true);
    $select = static function (string $column, string $fallback = 'NULL') use ($has): string {
        return $has($column) ? 'p.`' . $column . '`' : $fallback;
    };

    $joins = [];
    $category = $select('category', 'NULL');
    $brand = $select('brand', 'NULL');
    $supplier = 'NULL';
    $unit = 'NULL';
    $warehouse = 'NULL';
    if ($hasTable('categories') && $has('category_id')) {
        $joins[] = 'LEFT JOIN categories c ON c.id = p.category_id';
        $category = 'COALESCE(c.name, ' . $category . ')';
    }
    if ($hasTable('brands') && $has('brand_id')) {
        $joins[] = 'LEFT JOIN brands b ON b.id = p.brand_id';
        $brand = 'COALESCE(b.name, ' . $brand . ')';
    }
    if ($hasTable('suppliers') && $has('supplier_id')) {
        $joins[] = 'LEFT JOIN suppliers s ON s.id = p.supplier_id';
        $supplier = 's.name';
    }
    if ($hasTable('units') && $has('unit_id')) {
        $joins[] = 'LEFT JOIN units u ON u.id = p.unit_id';
        $unit = 'COALESCE(u.symbol, u.name)';
    }
    if ($hasTable('warehouses') && $has('warehouse_id')) {
        $joins[] = 'LEFT JOIN warehouses w ON w.id = p.warehouse_id';
        $warehouse = 'w.name';
    }

    $q = trim((string) ($_GET['q'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $categoryId = trim((string) ($_GET['category'] ?? ''));
    $brandFilter = trim((string) ($_GET['brand'] ?? ''));
    $stockFilter = trim((string) ($_GET['stock'] ?? ''));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $where = [];
    $params = [];
    if ($q !== '') {
        $searchCols = array_values(array_filter(['name', 'sku', 'barcode', 'category'], $has));
        $searchTerms = [];
        foreach ($searchCols as $index => $column) {
                $key = ':q' . $index;
                $searchTerms[] = 'p.`' . $column . '` LIKE ' . $key;
                $params[$key] = '%' . $q . '%';
        }
        if ($has('category_id') && $hasTable('categories')) {
            $searchTerms[] = 'c.name LIKE :qcategory';
            $params[':qcategory'] = '%' . $q . '%';
        }
        if ($searchTerms) $where[] = '(' . implode(' OR ', $searchTerms) . ')';
    }
    if ($status !== '' && $has('status')) {
        $where[] = 'p.status = :status';
        $params[':status'] = $status;
    }
    if ($categoryId !== '') {
        if ($has('category_id')) {
            $where[] = 'p.category_id = :category';
            $params[':category'] = (int) $categoryId;
        } elseif ($has('category')) {
            if ($hasTable('categories')) {
                $where[] = 'p.category = (SELECT cfilter.name FROM categories cfilter WHERE cfilter.id = :category LIMIT 1)';
                $params[':category'] = (int) $categoryId;
            } else {
                $where[] = 'p.category = :category';
                $params[':category'] = $categoryId;
            }
        }
    }
    if ($brandFilter !== '' && $has('brand')) {
        $where[] = 'p.brand = :brand';
        $params[':brand'] = $brandFilter;
    } elseif ($brandFilter !== '' && $has('brand_id') && $hasTable('brands')) {
        $where[] = 'b.name = :brand';
        $params[':brand'] = $brandFilter;
    } elseif ($brandFilter !== '' && $has('brand_id')) {
        $where[] = 'p.brand_id = :brand';
        $params[':brand'] = (int) $brandFilter;
    }
    $stockExpr = $has('opening_stock') ? 'COALESCE(p.opening_stock, 0)' : ($has('stock') ? 'COALESCE(p.stock, 0)' : '0');
    $minExpr = $has('min_stock') ? 'COALESCE(p.min_stock, 5)' : ($has('low_stock_threshold') ? 'COALESCE(p.low_stock_threshold, 5)' : '5');
    if ($stockFilter === 'out') $where[] = "$stockExpr <= 0";
    elseif ($stockFilter === 'low') $where[] = "$stockExpr > 0 AND $stockExpr <= $minExpr";
    elseif ($stockFilter === 'ok') $where[] = "$stockExpr > $minExpr";
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $from = 'FROM products p ' . implode(' ', $joins) . ' ';
    $count = $pdo->prepare('SELECT COUNT(*) ' . $from . $whereSql);
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $allStock = $has('opening_stock') ? 'COALESCE(opening_stock, 0)' : ($has('stock') ? 'COALESCE(stock, 0)' : '0');
    $allMin = $has('min_stock') ? 'COALESCE(min_stock, 5)' : ($has('low_stock_threshold') ? 'COALESCE(low_stock_threshold, 5)' : '5');
    $allPrice = $has('selling_price') ? 'COALESCE(selling_price, 0)' : ($has('price') ? 'COALESCE(price, 0)' : '0');
    $allStatus = $has('status') ? "COALESCE(status, 'active')" : "'active'";
    $stats = $pdo->query("SELECT COUNT(*) total, SUM($allStatus = 'active') active, SUM($allStock <= 0) out_of_stock, SUM($allStock > 0 AND $allStock <= $allMin) low_stock, SUM($allStock * $allPrice) inventory_value FROM products")->fetch(PDO::FETCH_ASSOC) ?: [];

    $fields = [
        'id' => $select('id', '0'), 'name' => $select('name', "''"), 'sku' => $select('sku'),
        'barcode' => $select('barcode'), 'category_name' => $category, 'brand_name' => $brand,
        'supplier_name' => $supplier, 'unit_name' => $unit, 'unit_symbol' => $unit,
        'warehouse_name' => $warehouse, 'cost_price' => $select('cost_price', $select('price', '0')),
        'selling_price' => $select('selling_price', $select('price', '0')),
        'price' => $select('price', $select('selling_price', '0')), 'opening_stock' => $select('opening_stock', $select('stock', '0')),
        'stock' => $select('stock', $select('opening_stock', '0')), 'min_stock' => $select('min_stock', $select('low_stock_threshold', '5')),
        'low_stock_threshold' => $select('low_stock_threshold', $select('min_stock', '5')),
        'status' => $select('status', "'active'"), 'updated_at' => $select('updated_at', $select('created_at')),
        'image_path' => $select('image_path'), 'thumbnail_path' => $select('thumbnail_path'),
        'short_description' => $select('short_description'),
    ];
    if ($hasTable('product_images')) {
        $fields['thumbnail_path'] = '(SELECT pi.path FROM product_images pi WHERE pi.product_id=p.id ORDER BY pi.is_thumbnail DESC, pi.sort_order ASC, pi.id ASC LIMIT 1)';
    }
    $order = $has('updated_at') ? 'p.updated_at' : ($has('created_at') ? 'p.created_at' : 'p.id');
    $sql = 'SELECT ' . implode(', ', array_map(static fn(string $alias, string $expr): string => $expr . ' AS `' . $alias . '`', array_keys($fields), array_values($fields))) . ' ' . $from . $whereSql . ' ORDER BY ' . $order . ' DESC LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT);
    $stmt->execute();
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'limit' => $limit, 'stats' => $stats], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('products_list: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load products. Check the database connection and product schema.']);
}
