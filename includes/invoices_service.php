<?php
declare(strict_types=1);

/**
 * Invoice ERP service — calculations, permissions, audit, create/update.
 * Maintains compatibility with ledger, stock, payments, and legacy discount/tax columns.
 */
require_once __DIR__ . '/invoices_schema.php';

/** @return array<string,bool> */
function vk_invoice_permissions(?string $role = null): array
{
    $role = strtolower((string) ($role ?? $_SESSION['user_role'] ?? 'viewer'));
    $isAdmin = in_array($role, ['super_admin', 'admin', 'owner', 'administrator'], true);
    $isManager = $isAdmin || in_array($role, ['manager', 'sales_manager'], true);
    $isSales = $isManager || in_array($role, ['staff', 'sales', 'sales_executive'], true);
    $isCashier = $isAdmin || $role === 'cashier';
    $isViewer = true;

    return [
        'view' => $isViewer,
        'create' => $isSales || $isCashier || $isAdmin,
        'edit' => $isSales || $isManager,
        'edit_draft' => $isSales || $isManager || $isCashier,
        'delete' => $isManager,
        'cancel' => $isManager,
        'approve' => $isManager,
        'print' => $isViewer || $isCashier,
        'email' => $isSales || $isManager,
        'view_cost' => $isManager || $isAdmin,
        'full' => $isAdmin,
    ];
}

function vk_invoice_require_perm(string $perm): void
{
    $p = vk_invoice_permissions();
    if (empty($p[$perm])) {
        flash_set('error', 'You do not have permission to perform this action.');
        redirect('/modules/invoices/list.php');
    }
}

function vk_invoice_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
}

/**
 * Convert amount to English words (LKR default).
 */
function vk_invoice_amount_in_words(float $amount, string $currency = 'LKR'): string
{
    if (function_exists('vk_quotation_amount_in_words')) {
        return vk_quotation_amount_in_words($amount, $currency);
    }
    $amount = round($amount, 2);
    $int = (int) floor($amount);
    $cents = (int) round(($amount - $int) * 100);
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $toWords = static function (int $n) use (&$toWords, $ones, $tens): string {
        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return trim($tens[(int) floor($n / 10)] . ' ' . $ones[$n % 10]);
        }
        if ($n < 1000) {
            return trim($ones[(int) floor($n / 100)] . ' Hundred' . ($n % 100 ? ' and ' . $toWords($n % 100) : ''));
        }
        if ($n < 1000000) {
            return trim($toWords((int) floor($n / 1000)) . ' Thousand' . ($n % 1000 ? ' ' . $toWords($n % 1000) : ''));
        }
        return (string) $n;
    };
    $words = $int === 0 ? 'Zero' : $toWords($int);
    $major = strtoupper($currency) === 'USD' ? 'US Dollars' : 'Sri Lankan Rupees';
    $out = $words . ' ' . $major;
    if ($cents > 0) {
        $out .= ' and ' . $toWords($cents) . ' Cents';
    }
    return $out . ' Only';
}

/**
 * @param array<string,mixed> $line
 * @return array<string,mixed>
 */
function vk_invoice_calc_line(array $line): array
{
    $qty = max(0, (float) ($line['quantity'] ?? 0));
    $unit = max(0, (float) ($line['unit_price'] ?? 0));
    $discType = strtolower((string) ($line['discount_type'] ?? 'percent'));
    if (!in_array($discType, ['percent', 'fixed'], true)) {
        $discType = 'percent';
    }
    $discValue = max(0, (float) ($line['discount_value'] ?? 0));
    $taxPct = max(0, (float) ($line['tax_pct'] ?? $line['tax'] ?? 0));

    $gross = round($qty * $unit, 2);
    if ($discType === 'fixed') {
        $discAmt = round($discValue, 2);
    } else {
        $discAmt = round($gross * min(100, $discValue) / 100, 2);
    }
    if ($discAmt > $gross) {
        $discAmt = $gross;
    }

    $afterDisc = round($gross - $discAmt, 2);
    $netPrice = $qty > 0 ? round($afterDisc / $qty, 4) : 0.0;
    $taxAmt = round($afterDisc * $taxPct / 100, 2);
    $netAmount = round($afterDisc + $taxAmt, 2);

    return array_merge($line, [
        'quantity' => $qty,
        'unit_price' => $unit,
        'discount_type' => $discType,
        'discount_value' => $discValue,
        'discount_amount' => $discAmt,
        'tax_pct' => $taxPct,
        'tax_amount' => $taxAmt,
        'net_price' => round($netPrice, 2),
        'net_amount' => $netAmount,
        'line_total' => $netAmount,
        'line_gross' => $gross,
    ]);
}

/**
 * @param list<array<string,mixed>> $lines
 * @param array<string,mixed> $header
 * @return array{lines:list<array<string,mixed>>,totals:array<string,float>}
 */
function vk_invoice_calc_totals(array $lines, array $header = []): array
{
    $calcLines = [];
    $subtotal = 0.0;
    $itemDisc = 0.0;
    $taxTotal = 0.0;

    foreach ($lines as $ln) {
        $c = vk_invoice_calc_line($ln);
        $calcLines[] = $c;
        $subtotal += (float) ($c['line_gross'] ?? 0);
        $itemDisc += (float) $c['discount_amount'];
        $taxTotal += (float) $c['tax_amount'];
    }

    $subtotal = round($subtotal, 2);
    $itemDisc = round($itemDisc, 2);
    $taxTotal = round($taxTotal, 2);
    $afterItem = round($subtotal - $itemDisc, 2);

    $invDiscType = strtolower((string) ($header['invoice_discount_type'] ?? 'fixed'));
    if (!in_array($invDiscType, ['percent', 'fixed'], true)) {
        $invDiscType = 'fixed';
    }
    $invDiscValue = max(0, (float) ($header['invoice_discount_value'] ?? $header['discount'] ?? 0));

    if ($invDiscType === 'percent') {
        $invDiscAmt = round($afterItem * min(100, $invDiscValue) / 100, 2);
    } else {
        $invDiscAmt = round($invDiscValue, 2);
    }
    if ($invDiscAmt > $afterItem) {
        $invDiscAmt = $afterItem;
    }

    // Optional header tax override (legacy flat tax) when no line taxes
    $headerTax = max(0, (float) ($header['tax'] ?? 0));
    if ($taxTotal <= 0.0001 && $headerTax > 0) {
        $taxTotal = round($headerTax, 2);
    } elseif ($headerTax > 0 && empty($header['tax_from_lines'])) {
        // If caller explicitly posts header tax and lines have tax, prefer sum of lines
        // unless header tax is meant as additional — keep line sum as primary.
    }

    $shipping = round((float) ($header['shipping_amount'] ?? 0), 2);
    $adjustment = round((float) ($header['adjustment_amount'] ?? 0), 2);
    $roundOff = round((float) ($header['round_off'] ?? 0), 2);

    $grand = round($afterItem - $invDiscAmt + $taxTotal + $shipping + $adjustment + $roundOff, 2);
    if ($grand < 0) {
        $grand = 0.0;
    }

    $paid = max(0, (float) ($header['paid_amount'] ?? 0));
    $balance = round($grand - $paid, 2);

    // Legacy columns: discount = invoice-level discount amount; tax = tax total
    return [
        'lines' => $calcLines,
        'totals' => [
            'subtotal' => $subtotal,
            'item_discount_total' => $itemDisc,
            'invoice_discount_type' => $invDiscType,
            'invoice_discount_value' => $invDiscValue,
            'invoice_discount_amount' => $invDiscAmt,
            'discount' => $invDiscAmt, // legacy
            'tax' => $taxTotal,
            'shipping_amount' => $shipping,
            'adjustment_amount' => $adjustment,
            'round_off' => $roundOff,
            'grand_total' => $grand,
            'paid_amount' => $paid,
            'balance' => $balance,
        ],
    ];
}

/**
 * @param array<string,mixed> $post
 * @return array<string,mixed>
 */
function vk_invoice_header_from_post(array $post): array
{
    $discType = strtolower(trim((string) ($post['invoice_discount_type'] ?? 'fixed')));
    if (!in_array($discType, ['percent', 'fixed'], true)) {
        $discType = 'fixed';
    }

    return [
        'customer_id' => (int) ($post['customer_id'] ?? 0),
        'invoice_date' => trim((string) ($post['invoice_date'] ?? '')),
        'due_date' => trim((string) ($post['due_date'] ?? '')) ?: null,
        'branch' => trim((string) ($post['branch'] ?? '')) ?: null,
        'salesperson_id' => (int) ($post['salesperson_id'] ?? 0) ?: null,
        'currency' => strtoupper(trim((string) ($post['currency'] ?? 'LKR'))) ?: 'LKR',
        'reference_number' => trim((string) ($post['reference_number'] ?? '')) ?: null,
        'payment_method' => trim((string) ($post['payment_method'] ?? '')) ?: null,
        'terms' => trim((string) ($post['terms'] ?? '')) ?: null,
        'notes' => trim((string) ($post['notes'] ?? '')) ?: null,
        'internal_notes' => trim((string) ($post['internal_notes'] ?? '')) ?: null,
        'invoice_discount_type' => $discType,
        'invoice_discount_value' => max(0, (float) ($post['invoice_discount_value'] ?? $post['discount'] ?? 0)),
        'shipping_amount' => (float) ($post['shipping_amount'] ?? 0),
        'adjustment_amount' => (float) ($post['adjustment_amount'] ?? 0),
        'round_off' => (float) ($post['round_off'] ?? 0),
        'tax' => max(0, (float) ($post['tax'] ?? 0)),
        'repair_job_id' => (int) ($post['repair_job_id'] ?? 0),
        'cctv_job_id' => (int) ($post['cctv_job_id'] ?? 0),
        'is_draft' => !empty($post['is_draft']) || (($post['form_action'] ?? '') === 'draft'),
        'edit_reason' => trim((string) ($post['edit_reason'] ?? '')) ?: null,
    ];
}

/**
 * @param array<string,mixed> $post
 * @return list<array<string,mixed>>
 */
function vk_invoice_parse_lines_from_post(array $post): array
{
    $types = $post['line_type'] ?? [];
    $productIds = $post['product_id'] ?? [];
    $codes = $post['item_code'] ?? [];
    $descs = $post['line_description'] ?? $post['service_desc'] ?? [];
    $units = $post['unit'] ?? [];
    $qtys = $post['qty'] ?? $post['quantity'] ?? [];
    $prices = $post['unit_price'] ?? $post['service_unit'] ?? [];
    $discTypes = $post['discount_type'] ?? [];
    $discValues = $post['discount_value'] ?? [];
    $taxPcts = $post['tax_pct'] ?? [];
    $costPrices = $post['cost_price'] ?? [];

    $n = is_array($types) ? count($types) : 0;
    $lines = [];
    for ($i = 0; $i < $n; $i++) {
        $t = (string) ($types[$i] ?? 'product');
        $qty = (float) ($qtys[$i] ?? 0);
        $price = max(0, (float) ($prices[$i] ?? 0));
        $desc = trim((string) ($descs[$i] ?? ''));
        $pid = (int) ($productIds[$i] ?? 0);

        // Skip blank empty rows
        if ($qty <= 0 && $pid <= 0 && $desc === '' && $price <= 0) {
            continue;
        }

        $lines[] = [
            'item_type' => $t === 'service' ? 'service' : 'product',
            'product_id' => $pid > 0 ? $pid : null,
            'item_code' => trim((string) ($codes[$i] ?? '')) ?: null,
            'line_description' => $desc !== '' ? $desc : null,
            'unit' => trim((string) ($units[$i] ?? 'pcs')) ?: 'pcs',
            'quantity' => $qty,
            'unit_price' => $price,
            'discount_type' => (string) ($discTypes[$i] ?? 'percent'),
            'discount_value' => (float) ($discValues[$i] ?? 0),
            'tax_pct' => (float) ($taxPcts[$i] ?? 0),
            'cost_price' => (float) ($costPrices[$i] ?? 0),
            'sort_order' => $i,
        ];
    }
    return $lines;
}

/**
 * Validate parsed lines + header. Returns error string or null.
 *
 * @param list<array<string,mixed>> $lines
 * @param array<string,mixed> $header
 */
function vk_invoice_validate(array $lines, array $header, bool $requireProducts = true): ?string
{
    if ((int) ($header['customer_id'] ?? 0) <= 0) {
        return 'Please select a customer.';
    }
    $date = (string) ($header['invoice_date'] ?? '');
    if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return 'Select a valid invoice date.';
    }
    if ($requireProducts && $lines === []) {
        return 'Add at least one product or service line.';
    }

    $valid = [];
    foreach ($lines as $i => $ln) {
        $qty = (float) ($ln['quantity'] ?? 0);
        $price = (float) ($ln['unit_price'] ?? 0);
        if ($qty <= 0) {
            return 'Quantity cannot be zero on line ' . ($i + 1) . '.';
        }
        if ($price < 0) {
            return 'Price cannot be negative on line ' . ($i + 1) . '.';
        }
        $type = (string) ($ln['item_type'] ?? 'product');
        if ($type === 'product' && empty($ln['product_id'])) {
            return 'Select a product on line ' . ($i + 1) . '.';
        }
        if ($type === 'service' && trim((string) ($ln['line_description'] ?? '')) === '') {
            return 'Service description required on line ' . ($i + 1) . '.';
        }
        $calc = vk_invoice_calc_line($ln);
        $gross = (float) $calc['line_gross'];
        if ((float) $calc['discount_amount'] > $gross + 0.001) {
            return 'Discount cannot exceed line total on line ' . ($i + 1) . '.';
        }
        if ((float) $calc['tax_pct'] < 0 || (float) $calc['tax_pct'] > 100) {
            return 'Invalid tax percentage on line ' . ($i + 1) . '.';
        }
        $valid[] = $calc;
    }

    if ($requireProducts && $valid === []) {
        return 'Add at least one valid line item.';
    }

    $totals = vk_invoice_calc_totals($valid, $header);
    if ($totals['totals']['grand_total'] < 0) {
        return 'Grand total cannot be negative.';
    }
    return null;
}

/** @return array<string,mixed>|null */
function vk_invoice_get(PDO $pdo, int $id): ?array
{
    vk_ensure_invoices_schema($pdo);
    $st = $pdo->prepare(
        'SELECT i.*, c.name AS customer_name, c.phone, c.email, c.address
         FROM invoices i
         JOIN customers c ON c.id = i.customer_id
         WHERE i.id = ?'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

/** @return list<array<string,mixed>> */
function vk_invoice_items(PDO $pdo, int $invoiceId): array
{
    vk_ensure_invoices_schema($pdo);
    $hasSku = db_column_exists($pdo, 'products', 'sku');
    $select = 'ii.*, p.name AS product_name, p.stock AS product_stock';
    if ($hasSku) {
        $select .= ', p.sku AS product_sku';
    }
    if (db_column_exists($pdo, 'products', 'price')) {
        $select .= ', p.price AS product_price';
    }
    $st = $pdo->prepare(
        "SELECT {$select}
         FROM invoice_items ii
         LEFT JOIN products p ON p.id = ii.product_id
         WHERE ii.invoice_id = ?
         ORDER BY ii.sort_order ASC, ii.id ASC"
    );
    $st->execute([$invoiceId]);
    return $st->fetchAll() ?: [];
}

/**
 * Write field-level audit rows.
 *
 * @param array<string,array{0:mixed,1:mixed}> $changes field => [old, new]
 */
function vk_invoice_history_log(
    PDO $pdo,
    int $invoiceId,
    array $changes,
    int $revisionNo,
    ?string $reason = null,
    ?int $userId = null
): void {
    if ($changes === []) {
        return;
    }
    $uid = $userId ?? (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null);
    $ip = vk_invoice_client_ip();
    $st = $pdo->prepare(
        'INSERT INTO invoice_history
         (invoice_id, field_name, old_value, new_value, edited_by, edited_at, ip_address, revision_no, reason)
         VALUES (?,?,?,?,?,NOW(),?,?,?)'
    );
    foreach ($changes as $field => $pair) {
        $old = $pair[0] ?? null;
        $new = $pair[1] ?? null;
        if ((string) $old === (string) $new) {
            continue;
        }
        $st->execute([
            $invoiceId,
            substr((string) $field, 0, 128),
            $old === null ? null : (is_scalar($old) ? (string) $old : json_encode($old)),
            $new === null ? null : (is_scalar($new) ? (string) $new : json_encode($new)),
            $uid,
            $ip,
            $revisionNo,
            $reason,
        ]);
    }
}

function vk_invoice_create_revision(PDO $pdo, int $invoiceId, int $revisionNo, string $summary = ''): void
{
    $inv = vk_invoice_get($pdo, $invoiceId);
    if (!$inv) {
        return;
    }
    $items = vk_invoice_items($pdo, $invoiceId);
    $snapshot = json_encode(['header' => $inv, 'items' => $items], JSON_UNESCAPED_UNICODE);
    $pdo->prepare(
        'INSERT INTO invoice_revisions (invoice_id, revision_no, snapshot_json, change_summary, created_by)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE snapshot_json = VALUES(snapshot_json), change_summary = VALUES(change_summary)'
    )->execute([
        $invoiceId,
        $revisionNo,
        $snapshot,
        $summary !== '' ? $summary : null,
        isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
    ]);
}

/**
 * Enrich product lines from DB (price/stock/code) when needed.
 *
 * @param list<array<string,mixed>> $lines
 * @return list<array<string,mixed>>
 */
function vk_invoice_enrich_lines(PDO $pdo, array $lines, bool $lockPriceFromProduct = false): array
{
    $out = [];
    $hasSku = db_column_exists($pdo, 'products', 'sku');
    $priceCol = db_column_exists($pdo, 'products', 'selling_price') ? 'selling_price' : 'price';
    $costCol = db_column_exists($pdo, 'products', 'cost_price') ? 'cost_price' : null;

    foreach ($lines as $ln) {
        if (($ln['item_type'] ?? '') === 'product' && !empty($ln['product_id'])) {
            $cols = "id, name, {$priceCol} AS sell_price, stock";
            if ($hasSku) {
                $cols .= ', sku';
            }
            if ($costCol) {
                $cols .= ", {$costCol} AS cost_price";
            }
            $st = $pdo->prepare("SELECT {$cols} FROM products WHERE id = ?");
            $st->execute([(int) $ln['product_id']]);
            $prod = $st->fetch();
            if (!$prod) {
                throw new RuntimeException('Invalid product ID ' . (int) $ln['product_id']);
            }
            if ($lockPriceFromProduct || !isset($ln['unit_price']) || (float) $ln['unit_price'] <= 0) {
                $ln['unit_price'] = (float) $prod['sell_price'];
            }
            if (empty($ln['item_code']) && $hasSku) {
                $ln['item_code'] = $prod['sku'] ?? null;
            }
            if (empty($ln['line_description'])) {
                $ln['line_description'] = $prod['name'];
            }
            if ($costCol && empty($ln['cost_price'])) {
                $ln['cost_price'] = (float) ($prod['cost_price'] ?? 0);
            }
            $ln['_stock'] = (int) $prod['stock'];
            $ln['_product_name'] = $prod['name'];
        }
        $out[] = $ln;
    }
    return $out;
}

/**
 * Create invoice with optional payments. Returns invoice id.
 *
 * @param array<string,mixed> $header
 * @param list<array<string,mixed>> $lines
 * @param list<array{amount:float,method:string,note?:string}> $paymentRows
 */
function vk_invoice_create(PDO $pdo, array $header, array $lines, array $paymentRows = []): int
{
    vk_ensure_invoices_schema($pdo);
    $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $isDraft = !empty($header['is_draft']);

    $lines = vk_invoice_enrich_lines($pdo, $lines, false);
    $err = vk_invoice_validate($lines, $header, true);
    if ($err) {
        throw new InvalidArgumentException($err);
    }

    // Stock check (skip for draft)
    if (!$isDraft) {
        foreach ($lines as $ln) {
            if (($ln['item_type'] ?? '') === 'product' && !empty($ln['product_id'])) {
                $need = (int) round((float) $ln['quantity']);
                if ($need > (int) ($ln['_stock'] ?? 0)) {
                    throw new RuntimeException(
                        'Insufficient stock for ' . ($ln['_product_name'] ?? 'product') .
                        ' (available ' . (int) ($ln['_stock'] ?? 0) . ').'
                    );
                }
            }
        }
    }

    $calc = vk_invoice_calc_totals($lines, $header);
    $totals = $calc['totals'];
    $calcLines = $calc['lines'];

    $customerId = (int) $header['customer_id'];
    $accSt = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
    $accSt->execute([$customerId]);
    $customerAccountId = (int) $accSt->fetchColumn();
    if ($customerAccountId <= 0) {
        throw new RuntimeException('Customer account not found.');
    }

    $repairJobId = (int) ($header['repair_job_id'] ?? 0);
    $cctvJobId = (int) ($header['cctv_job_id'] ?? 0);
    if ($repairJobId > 0 && $cctvJobId > 0) {
        throw new InvalidArgumentException('Link either a repair job or a CCTV job, not both.');
    }

    $totalPaid = 0.0;
    foreach ($paymentRows as $pr) {
        $totalPaid += (float) $pr['amount'];
    }
    $totalPaid = round($totalPaid, 2);
    $grand = (float) $totals['grand_total'];
    if (!$isDraft && $totalPaid > $grand + 0.01) {
        throw new InvalidArgumentException('Total payment exceeds grand total.');
    }

    $source = 'manual';
    if ($repairJobId > 0) {
        $source = 'repair';
    }
    if ($cctvJobId > 0) {
        $source = 'cctv';
    }

    $status = 'unpaid';
    if ($isDraft) {
        $status = 'draft';
        $totalPaid = 0.0;
        $paymentRows = [];
    } elseif ($totalPaid > 0 && $totalPaid >= $grand - 0.01) {
        $status = 'paid';
    } elseif ($totalPaid > 0) {
        $status = 'partial';
    }

    $pdo->beginTransaction();
    try {
        $invNo = next_invoice_number($pdo);
        $cols = [
            'invoice_number', 'customer_id', 'invoice_date', 'subtotal', 'discount', 'tax', 'grand_total',
            'paid_amount', 'status', 'notes', 'source', 'repair_job_id', 'cctv_job_id',
        ];
        $vals = [
            $invNo, $customerId, $header['invoice_date'], $totals['subtotal'], $totals['discount'],
            $totals['tax'], $grand, $totalPaid, $status, $header['notes'] ?? null, $source,
            $repairJobId > 0 ? $repairJobId : null, $cctvJobId > 0 ? $cctvJobId : null,
        ];

        $optional = [
            'due_date' => $header['due_date'] ?? null,
            'branch' => $header['branch'] ?? null,
            'salesperson_id' => $header['salesperson_id'] ?? null,
            'currency' => $header['currency'] ?? 'LKR',
            'reference_number' => $header['reference_number'] ?? null,
            'payment_method' => $header['payment_method'] ?? null,
            'terms' => $header['terms'] ?? null,
            'internal_notes' => $header['internal_notes'] ?? null,
            'item_discount_total' => $totals['item_discount_total'],
            'invoice_discount_type' => $totals['invoice_discount_type'],
            'invoice_discount_value' => $totals['invoice_discount_value'],
            'invoice_discount_amount' => $totals['invoice_discount_amount'],
            'shipping_amount' => $totals['shipping_amount'],
            'adjustment_amount' => $totals['adjustment_amount'],
            'round_off' => $totals['round_off'],
            'revision_no' => 0,
            'created_by' => $uid,
            'updated_by' => $uid,
            'is_draft' => $isDraft ? 1 : 0,
        ];
        foreach ($optional as $col => $val) {
            if (db_column_exists($pdo, 'invoices', $col)) {
                $cols[] = $col;
                $vals[] = $val;
            }
        }

        $ph = implode(',', array_fill(0, count($cols), '?'));
        $pdo->prepare('INSERT INTO invoices (' . implode(',', $cols) . ') VALUES (' . $ph . ')')->execute($vals);
        $invoiceId = (int) $pdo->lastInsertId();

        vk_invoice_insert_items($pdo, $invoiceId, $calcLines, !$isDraft);

        if ($repairJobId > 0) {
            $pdo->prepare('UPDATE repair_jobs SET invoice_id = ? WHERE id = ?')->execute([$invoiceId, $repairJobId]);
        }
        if ($cctvJobId > 0) {
            $pdo->prepare('UPDATE cctv_installations SET invoice_id = ? WHERE id = ?')->execute([$invoiceId, $cctvJobId]);
        }

        if (!$isDraft) {
            ledger_apply(
                $pdo,
                $customerAccountId,
                $grand,
                0,
                'Invoice ' . $invNo . ' — amount due',
                $invoiceId,
                null,
                null
            );

            if ($paymentRows) {
                $sysId = system_account_id($pdo);
                $stPay = $pdo->prepare(
                    'INSERT INTO payments (invoice_id, repair_job_id, cctv_job_id, customer_id, customer_account_id, amount, method, note)
                     VALUES (?,?,?,?,?,?,?,?)'
                );
                foreach ($paymentRows as $pr) {
                    $stPay->execute([
                        $invoiceId,
                        $repairJobId > 0 ? $repairJobId : null,
                        $cctvJobId > 0 ? $cctvJobId : null,
                        $customerId,
                        $customerAccountId,
                        $pr['amount'],
                        $pr['method'],
                        $pr['note'] ?? null,
                    ]);
                    $paymentId = (int) $pdo->lastInsertId();
                    ledger_apply(
                        $pdo,
                        $customerAccountId,
                        0,
                        (float) $pr['amount'],
                        'Invoice ' . $invNo . ' — payment (' . $pr['method'] . ')',
                        $invoiceId,
                        $paymentId,
                        null
                    );
                    ledger_apply(
                        $pdo,
                        $sysId,
                        (float) $pr['amount'],
                        0,
                        'Receipt — invoice ' . $invNo . ' (' . $pr['method'] . ')',
                        $invoiceId,
                        $paymentId,
                        null
                    );
                }
            }
        }

        vk_invoice_create_revision($pdo, $invoiceId, 0, $isDraft ? 'Draft created' : 'Invoice created');
        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * @param list<array<string,mixed>> $calcLines
 */
function vk_invoice_insert_items(PDO $pdo, int $invoiceId, array $calcLines, bool $decrementStock): void
{
    $hasDiscType = db_column_exists($pdo, 'invoice_items', 'discount_type');
    $stStock = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');

    if ($hasDiscType) {
        $stItem = $pdo->prepare(
            'INSERT INTO invoice_items
             (invoice_id, item_type, product_id, item_code, line_description, unit, quantity, unit_price,
              discount_type, discount_value, discount_amount, tax_pct, tax_amount, net_price, net_amount,
              line_total, sort_order, cost_price)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
    } else {
        $stItem = $pdo->prepare(
            'INSERT INTO invoice_items (invoice_id, item_type, product_id, line_description, quantity, unit_price, line_total)
             VALUES (?,?,?,?,?,?,?)'
        );
    }

    foreach ($calcLines as $idx => $ln) {
        $qty = (int) round((float) $ln['quantity']);
        if ($hasDiscType) {
            $stItem->execute([
                $invoiceId,
                $ln['item_type'] ?? 'product',
                $ln['product_id'] ?? null,
                $ln['item_code'] ?? null,
                $ln['line_description'] ?? null,
                $ln['unit'] ?? 'pcs',
                $qty,
                $ln['unit_price'],
                $ln['discount_type'] ?? 'percent',
                $ln['discount_value'] ?? 0,
                $ln['discount_amount'] ?? 0,
                $ln['tax_pct'] ?? 0,
                $ln['tax_amount'] ?? 0,
                $ln['net_price'] ?? 0,
                $ln['net_amount'] ?? $ln['line_total'],
                $ln['line_total'],
                $ln['sort_order'] ?? $idx,
                $ln['cost_price'] ?? 0,
            ]);
        } else {
            $stItem->execute([
                $invoiceId,
                $ln['item_type'] ?? 'product',
                $ln['product_id'] ?? null,
                $ln['line_description'] ?? null,
                $qty,
                $ln['unit_price'],
                $ln['line_total'],
            ]);
        }

        if (
            $decrementStock
            && ($ln['item_type'] ?? '') === 'product'
            && !empty($ln['product_id'])
            && $qty > 0
        ) {
            $stStock->execute([$qty, (int) $ln['product_id'], $qty]);
            if ($stStock->rowCount() === 0) {
                throw new RuntimeException('Stock conflict for product ID ' . (int) $ln['product_id']);
            }
        }
    }
}

/**
 * Update existing invoice (transactional). Adjusts stock + ledger for grand_total delta.
 *
 * @param array<string,mixed> $header
 * @param list<array<string,mixed>> $lines
 */
function vk_invoice_update(PDO $pdo, int $invoiceId, array $header, array $lines): void
{
    vk_ensure_invoices_schema($pdo);
    $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    $old = vk_invoice_get($pdo, $invoiceId);
    if (!$old) {
        throw new RuntimeException('Invoice not found.');
    }
    if (($old['status'] ?? '') === 'cancelled') {
        throw new RuntimeException('Cancelled invoices cannot be edited.');
    }

    // Invoice number / id are immutable
    unset($header['invoice_number'], $header['id']);

    $lines = vk_invoice_enrich_lines($pdo, $lines, false);
    $err = vk_invoice_validate($lines, $header, true);
    if ($err) {
        throw new InvalidArgumentException($err);
    }

    $wasDraft = !empty($old['is_draft']) || ($old['status'] ?? '') === 'draft';
    $isDraft = !empty($header['is_draft']) && $wasDraft;
    // Publishing draft
    $publishing = $wasDraft && empty($header['is_draft']);

    $calc = vk_invoice_calc_totals($lines, array_merge($header, [
        'paid_amount' => (float) $old['paid_amount'],
    ]));
    $totals = $calc['totals'];
    $calcLines = $calc['lines'];
    $grand = (float) $totals['grand_total'];
    $paid = (float) $old['paid_amount'];
    if ($paid > $grand + 0.01 && !$isDraft) {
        throw new InvalidArgumentException(
            'Grand total (' . formatCurrency($grand) . ') cannot be less than paid amount (' . formatCurrency($paid) . ').'
        );
    }

    $oldItems = vk_invoice_items($pdo, $invoiceId);
    $reason = $header['edit_reason'] ?? null;

    $pdo->beginTransaction();
    try {
        // Snapshot current state as previous revision before mutating
        $prevRev = (int) ($old['revision_no'] ?? 0);
        vk_invoice_create_revision($pdo, $invoiceId, $prevRev, 'Before update');
        $newRev = $prevRev + 1;

        // Reverse stock for previous product lines (if not draft)
        if (!$wasDraft) {
            $stRestore = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            foreach ($oldItems as $oi) {
                if (($oi['item_type'] ?? '') === 'product' && !empty($oi['product_id'])) {
                    $stRestore->execute([(int) $oi['quantity'], (int) $oi['product_id']]);
                }
            }
        }

        // Stock check for new lines
        if (!$isDraft) {
            foreach ($calcLines as $ln) {
                if (($ln['item_type'] ?? '') === 'product' && !empty($ln['product_id'])) {
                    $need = (int) round((float) $ln['quantity']);
                    $st = $pdo->prepare('SELECT name, stock FROM products WHERE id = ? FOR UPDATE');
                    $st->execute([(int) $ln['product_id']]);
                    $prod = $st->fetch();
                    if (!$prod || (int) $prod['stock'] < $need) {
                        throw new RuntimeException(
                            'Insufficient stock for ' . ($prod['name'] ?? 'product') .
                            ' (available ' . (int) ($prod['stock'] ?? 0) . ').'
                        );
                    }
                }
            }
        }

        $customerId = (int) $header['customer_id'];
        $status = (string) $old['status'];
        if ($isDraft) {
            $status = 'draft';
        } elseif ($publishing || !$wasDraft) {
            if ($paid <= 0.0001) {
                $status = 'unpaid';
            } elseif ($paid >= $grand - 0.01) {
                $status = 'paid';
            } else {
                $status = 'partial';
            }
        }

        $set = [
            'customer_id' => $customerId,
            'invoice_date' => $header['invoice_date'],
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'tax' => $totals['tax'],
            'grand_total' => $grand,
            'status' => $status,
            'notes' => $header['notes'] ?? null,
        ];
        $optional = [
            'due_date' => $header['due_date'] ?? null,
            'branch' => $header['branch'] ?? null,
            'salesperson_id' => $header['salesperson_id'] ?? null,
            'currency' => $header['currency'] ?? 'LKR',
            'reference_number' => $header['reference_number'] ?? null,
            'payment_method' => $header['payment_method'] ?? null,
            'terms' => $header['terms'] ?? null,
            'internal_notes' => $header['internal_notes'] ?? null,
            'item_discount_total' => $totals['item_discount_total'],
            'invoice_discount_type' => $totals['invoice_discount_type'],
            'invoice_discount_value' => $totals['invoice_discount_value'],
            'invoice_discount_amount' => $totals['invoice_discount_amount'],
            'shipping_amount' => $totals['shipping_amount'],
            'adjustment_amount' => $totals['adjustment_amount'],
            'round_off' => $totals['round_off'],
            'revision_no' => $newRev,
            'updated_by' => $uid,
            'is_draft' => $isDraft ? 1 : 0,
        ];
        foreach ($optional as $col => $val) {
            if (db_column_exists($pdo, 'invoices', $col)) {
                $set[$col] = $val;
            }
        }

        $parts = [];
        $params = [];
        foreach ($set as $col => $val) {
            $parts[] = "{$col} = ?";
            $params[] = $val;
        }
        $params[] = $invoiceId;
        $pdo->prepare('UPDATE invoices SET ' . implode(', ', $parts) . ' WHERE id = ?')->execute($params);

        // Replace line items
        $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = ?')->execute([$invoiceId]);
        vk_invoice_insert_items($pdo, $invoiceId, $calcLines, !$isDraft);

        // Ledger adjustment for grand_total change
        $oldGrand = (float) $old['grand_total'];
        $delta = round($grand - $oldGrand, 2);
        $accSt = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
        $accSt->execute([$customerId]);
        $accountId = (int) $accSt->fetchColumn();

        if ($wasDraft && $publishing && $accountId > 0) {
            ledger_apply(
                $pdo,
                $accountId,
                $grand,
                0,
                'Invoice ' . $old['invoice_number'] . ' — amount due (published)',
                $invoiceId,
                null,
                null
            );
        } elseif (!$wasDraft && !$isDraft && abs($delta) > 0.001 && $accountId > 0) {
            if ($delta > 0) {
                ledger_apply(
                    $pdo,
                    $accountId,
                    $delta,
                    0,
                    'Invoice ' . $old['invoice_number'] . ' — adjustment (increase)',
                    $invoiceId,
                    null,
                    null
                );
            } else {
                ledger_apply(
                    $pdo,
                    $accountId,
                    0,
                    abs($delta),
                    'Invoice ' . $old['invoice_number'] . ' — adjustment (decrease)',
                    $invoiceId,
                    null,
                    null
                );
            }
        }

        // Customer change ledger: reverse old customer debit, apply to new (complex) —
        // If customer changed and not draft, move the receivable.
        $oldCustomer = (int) $old['customer_id'];
        if (!$wasDraft && !$isDraft && $oldCustomer !== $customerId && $accountId > 0) {
            $oldAccSt = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
            $oldAccSt->execute([$oldCustomer]);
            $oldAccId = (int) $oldAccSt->fetchColumn();
            if ($oldAccId > 0) {
                // Credit old customer for previous grand (remove receivable)
                ledger_apply(
                    $pdo,
                    $oldAccId,
                    0,
                    $oldGrand,
                    'Invoice ' . $old['invoice_number'] . ' — customer change (reverse)',
                    $invoiceId,
                    null,
                    null
                );
                // Debit already applied via delta path used oldGrand→new; fix by applying full new grand
                // and reversing the delta we may have applied above on the NEW account incorrectly.
                // Safer approach: reverse delta on new account if applied, then debit full grand.
                if (abs($delta) > 0.001) {
                    if ($delta > 0) {
                        ledger_apply($pdo, $accountId, 0, $delta, 'Invoice ' . $old['invoice_number'] . ' — undo prior adj', $invoiceId, null, null);
                    } else {
                        ledger_apply($pdo, $accountId, abs($delta), 0, 'Invoice ' . $old['invoice_number'] . ' — undo prior adj', $invoiceId, null, null);
                    }
                }
                ledger_apply(
                    $pdo,
                    $accountId,
                    $grand,
                    0,
                    'Invoice ' . $old['invoice_number'] . ' — customer change (new)',
                    $invoiceId,
                    null,
                    null
                );
            }
        }

        // Field-level history
        $trackFields = [
            'customer_id', 'invoice_date', 'due_date', 'branch', 'salesperson_id', 'currency',
            'reference_number', 'payment_method', 'terms', 'notes', 'internal_notes',
            'subtotal', 'discount', 'tax', 'grand_total', 'item_discount_total',
            'invoice_discount_type', 'invoice_discount_value', 'invoice_discount_amount',
            'shipping_amount', 'adjustment_amount', 'round_off', 'status',
        ];
        $changes = [];
        foreach ($trackFields as $f) {
            $ov = $old[$f] ?? null;
            $nv = $set[$f] ?? ($old[$f] ?? null);
            if ((string) $ov !== (string) $nv) {
                $changes[$f] = [$ov, $nv];
            }
        }
        $changes['line_items'] = [
            json_encode(array_map(static fn ($r) => [
                'product_id' => $r['product_id'] ?? null,
                'qty' => $r['quantity'] ?? null,
                'price' => $r['unit_price'] ?? null,
                'disc' => $r['discount_amount'] ?? null,
                'total' => $r['line_total'] ?? null,
            ], $oldItems)),
            json_encode(array_map(static fn ($r) => [
                'product_id' => $r['product_id'] ?? null,
                'qty' => $r['quantity'] ?? null,
                'price' => $r['unit_price'] ?? null,
                'disc' => $r['discount_amount'] ?? null,
                'total' => $r['line_total'] ?? null,
            ], $calcLines)),
        ];
        vk_invoice_history_log($pdo, $invoiceId, $changes, $newRev, $reason, $uid);
        vk_invoice_create_revision($pdo, $invoiceId, $newRev, $reason ?: 'Invoice updated');

        if (function_exists('invoice_recalc_status') && !$isDraft) {
            invoice_recalc_status($pdo, $invoiceId);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function vk_invoice_cancel(PDO $pdo, int $invoiceId, string $reason = ''): void
{
    vk_ensure_invoices_schema($pdo);
    $inv = vk_invoice_get($pdo, $invoiceId);
    if (!$inv) {
        throw new RuntimeException('Invoice not found.');
    }
    if (($inv['status'] ?? '') === 'cancelled') {
        return;
    }
    if ((float) $inv['paid_amount'] > 0.01) {
        throw new RuntimeException('Cannot cancel an invoice with payments. Refund payments first.');
    }

    $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $pdo->beginTransaction();
    try {
        $prevRev = (int) ($inv['revision_no'] ?? 0);
        vk_invoice_create_revision($pdo, $invoiceId, $prevRev, 'Before cancel');
        $newRev = $prevRev + 1;

        // Restore stock
        $items = vk_invoice_items($pdo, $invoiceId);
        $stRestore = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        foreach ($items as $oi) {
            if (($oi['item_type'] ?? '') === 'product' && !empty($oi['product_id']) && empty($inv['is_draft'])) {
                $stRestore->execute([(int) $oi['quantity'], (int) $oi['product_id']]);
            }
        }

        // Reverse ledger debit
        if (empty($inv['is_draft']) && ($inv['status'] ?? '') !== 'draft') {
            $accSt = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
            $accSt->execute([(int) $inv['customer_id']]);
            $accountId = (int) $accSt->fetchColumn();
            if ($accountId > 0 && (float) $inv['grand_total'] > 0) {
                ledger_apply(
                    $pdo,
                    $accountId,
                    0,
                    (float) $inv['grand_total'],
                    'Invoice ' . $inv['invoice_number'] . ' — cancelled',
                    $invoiceId,
                    null,
                    null
                );
            }
        }

        $pdo->prepare(
            "UPDATE invoices SET status='cancelled', cancelled_at=NOW(), cancelled_by=?, cancel_reason=?, revision_no=?, updated_by=? WHERE id=?"
        )->execute([$uid, $reason ?: null, $newRev, $uid, $invoiceId]);

        vk_invoice_history_log($pdo, $invoiceId, [
            'status' => [$inv['status'], 'cancelled'],
        ], $newRev, $reason ?: 'Cancelled', $uid);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Soft-delete or hard-delete draft only.
 */
function vk_invoice_delete(PDO $pdo, int $invoiceId): void
{
    $inv = vk_invoice_get($pdo, $invoiceId);
    if (!$inv) {
        throw new RuntimeException('Invoice not found.');
    }
    $isDraft = !empty($inv['is_draft']) || ($inv['status'] ?? '') === 'draft';
    if (!$isDraft && ($inv['status'] ?? '') !== 'cancelled') {
        throw new RuntimeException('Only draft or cancelled invoices can be deleted. Cancel first.');
    }
    if ((float) $inv['paid_amount'] > 0.01) {
        throw new RuntimeException('Cannot delete invoice with payments.');
    }

    $pdo->beginTransaction();
    try {
        // Unlink jobs
        $pdo->prepare('UPDATE repair_jobs SET invoice_id = NULL WHERE invoice_id = ?')->execute([$invoiceId]);
        $pdo->prepare('UPDATE cctv_installations SET invoice_id = NULL WHERE invoice_id = ?')->execute([$invoiceId]);
        $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = ?')->execute([$invoiceId]);
        $pdo->prepare('DELETE FROM invoices WHERE id = ?')->execute([$invoiceId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Duplicate invoice as new draft.
 */
function vk_invoice_duplicate(PDO $pdo, int $invoiceId): int
{
    $inv = vk_invoice_get($pdo, $invoiceId);
    if (!$inv) {
        throw new RuntimeException('Invoice not found.');
    }
    $items = vk_invoice_items($pdo, $invoiceId);
    $header = [
        'customer_id' => (int) $inv['customer_id'],
        'invoice_date' => date('Y-m-d'),
        'due_date' => $inv['due_date'] ?? null,
        'branch' => $inv['branch'] ?? null,
        'salesperson_id' => $inv['salesperson_id'] ?? null,
        'currency' => $inv['currency'] ?? 'LKR',
        'reference_number' => null,
        'payment_method' => $inv['payment_method'] ?? null,
        'terms' => $inv['terms'] ?? null,
        'notes' => $inv['notes'] ?? null,
        'internal_notes' => $inv['internal_notes'] ?? null,
        'invoice_discount_type' => $inv['invoice_discount_type'] ?? 'fixed',
        'invoice_discount_value' => (float) ($inv['invoice_discount_value'] ?? $inv['discount'] ?? 0),
        'shipping_amount' => (float) ($inv['shipping_amount'] ?? 0),
        'adjustment_amount' => (float) ($inv['adjustment_amount'] ?? 0),
        'round_off' => (float) ($inv['round_off'] ?? 0),
        'tax' => (float) ($inv['tax'] ?? 0),
        'is_draft' => true,
        'repair_job_id' => 0,
        'cctv_job_id' => 0,
    ];
    $lines = [];
    foreach ($items as $it) {
        $lines[] = [
            'item_type' => $it['item_type'] ?? 'product',
            'product_id' => $it['product_id'] ?? null,
            'item_code' => $it['item_code'] ?? null,
            'line_description' => $it['line_description'] ?? ($it['product_name'] ?? null),
            'unit' => $it['unit'] ?? 'pcs',
            'quantity' => (float) $it['quantity'],
            'unit_price' => (float) $it['unit_price'],
            'discount_type' => $it['discount_type'] ?? 'percent',
            'discount_value' => (float) ($it['discount_value'] ?? 0),
            'tax_pct' => (float) ($it['tax_pct'] ?? 0),
            'cost_price' => (float) ($it['cost_price'] ?? 0),
        ];
    }
    return vk_invoice_create($pdo, $header, $lines, []);
}

/**
 * Product search for invoice grid (barcode / SKU / name).
 *
 * @return list<array<string,mixed>>
 */
function vk_invoice_search_products(PDO $pdo, string $q, int $limit = 20): array
{
    if (is_file(__DIR__ . '/quotations_service.php')) {
        require_once __DIR__ . '/quotations_service.php';
        if (function_exists('vk_quotation_search_products')) {
            return vk_quotation_search_products($pdo, $q, $limit);
        }
    }

    $q = trim($q);
    $limit = max(1, min(50, $limit));
    $priceCol = db_column_exists($pdo, 'products', 'selling_price') ? 'selling_price' : 'price';
    $sql = "SELECT id, name, {$priceCol} AS unit_price, stock AS stock_available,
                   CAST(id AS CHAR) AS product_code, NULL AS barcode, 0 AS cost_price, 'pcs' AS unit
            FROM products WHERE name LIKE ? ORDER BY name LIMIT {$limit}";
    $st = $pdo->prepare($sql);
    $st->execute(['%' . $q . '%']);
    return $st->fetchAll() ?: [];
}

/** @return list<array<string,mixed>> */
function vk_invoice_recent_products(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(30, $limit));
    $priceCol = db_column_exists($pdo, 'products', 'selling_price') ? 'selling_price' : 'price';
    try {
        $sql = "SELECT p.id, p.name, p.{$priceCol} AS unit_price, p.stock AS stock_available,
                       MAX(ii.id) AS last_used
                FROM invoice_items ii
                JOIN products p ON p.id = ii.product_id
                WHERE ii.item_type = 'product' AND ii.product_id IS NOT NULL
                GROUP BY p.id, p.name, p.{$priceCol}, p.stock
                ORDER BY last_used DESC
                LIMIT {$limit}";
        return $pdo->query($sql)->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}
