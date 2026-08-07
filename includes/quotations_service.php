<?php
declare(strict_types=1);

require_once __DIR__ . '/quotations_schema.php';

/** @return list<string> */
function vk_quotation_statuses(): array
{
    return [
        'draft', 'pending_approval', 'approved', 'rejected', 'cancelled',
        'expired', 'accepted', 'converted_so', 'converted_invoice',
    ];
}

function vk_quotation_status_label(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'pending_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'expired' => 'Expired',
        'accepted' => 'Accepted',
        'converted_so' => 'Converted to Sales Order',
        'converted_invoice' => 'Converted to Invoice',
        default => ucwords(str_replace('_', ' ', $status)),
    };
}

function vk_quotation_status_badge(string $status): string
{
    return match ($status) {
        'draft' => 'secondary',
        'pending_approval' => 'warning',
        'approved' => 'primary',
        'rejected' => 'danger',
        'cancelled' => 'dark',
        'expired' => 'secondary',
        'accepted' => 'success',
        'converted_so' => 'info',
        'converted_invoice' => 'success',
        default => 'secondary',
    };
}

/** @return array<string, bool> */
function vk_quotation_permissions(?string $role = null): array
{
    $role = strtolower((string) ($role ?? $_SESSION['user_role'] ?? 'viewer'));
    $isAdmin = in_array($role, ['super_admin', 'admin', 'owner'], true);
    $isManager = $isAdmin || in_array($role, ['manager', 'sales_manager'], true);
    $isFinance = $isAdmin || $role === 'finance';
    $isSales = $isManager || in_array($role, ['staff', 'sales_executive', 'sales'], true);
    $isViewer = true;

    return [
        'view' => $isViewer,
        'create' => $isSales || $isFinance,
        'edit' => $isSales || $isFinance,
        'delete' => $isManager,
        'approve' => $isManager || $isFinance || $role === 'director',
        'export' => $isSales || $isFinance || $isManager,
        'print' => $isViewer,
        'convert' => $isSales || $isFinance || $isManager,
        'settings' => $isAdmin || $isManager,
    ];
}

function vk_quotation_require_perm(string $perm): void
{
    $p = vk_quotation_permissions();
    if (empty($p[$perm])) {
        flash_set('error', 'You do not have permission to perform this action.');
        redirect('/modules/quotations/list.php');
    }
}

function vk_quotation_setting(PDO $pdo, string $key, string $default = ''): string
{
    vk_ensure_quotations_schema($pdo);
    try {
        $st = $pdo->prepare('SELECT setting_value FROM quotation_settings WHERE setting_key = ? LIMIT 1');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v !== false && $v !== null ? (string) $v : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function vk_quotation_setting_set(PDO $pdo, string $key, string $value): void
{
    vk_ensure_quotations_schema($pdo);
    $pdo->prepare(
        'INSERT INTO quotation_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}

function next_quotation_number(PDO $pdo): string
{
    $prefixBase = vk_quotation_setting($pdo, 'prefix', 'QT');
    if ($prefixBase === '' || strtoupper($prefixBase) === 'QTN') {
        $prefixBase = 'QT';
    }
    // Format: QT-2026-000001
    $prefix = $prefixBase . '-' . date('Y') . '-';
    $st = $pdo->prepare('SELECT quotation_number FROM quotations WHERE quotation_number LIKE ? ORDER BY id DESC LIMIT 1');
    $st->execute([$prefix . '%']);
    $last = $st->fetchColumn();
    $seq = 1;
    if ($last && preg_match('/-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }
    return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
}

/**
 * Convert amount to English words for quotation PDFs.
 */
function vk_quotation_amount_in_words(float $amount, string $currency = 'LKR'): string
{
    $amount = round($amount, 2);
    $int = (int) floor($amount);
    $cents = (int) round(($amount - $int) * 100);

    $toWords = static function (int $n) use (&$toWords): string {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return trim($tens[(int) floor($n / 10)] . ' ' . $ones[$n % 10]);
        }
        if ($n < 1000) {
            return trim($ones[(int) floor($n / 100)] . ' Hundred' . ($n % 100 ? ' and ' . $toWords($n % 100) : ''));
        }
        return (string) $n;
    };

    if ($int === 0) {
        $words = 'Zero';
    } else {
        $parts = [];
        $billions = (int) floor($int / 1000000000);
        $millions = (int) floor(($int % 1000000000) / 1000000);
        $thousands = (int) floor(($int % 1000000) / 1000);
        $rest = $int % 1000;
        if ($billions) {
            $parts[] = $toWords($billions) . ' Billion';
        }
        if ($millions) {
            $parts[] = $toWords($millions) . ' Million';
        }
        if ($thousands) {
            $parts[] = $toWords($thousands) . ' Thousand';
        }
        if ($rest) {
            $parts[] = $toWords($rest);
        }
        $words = implode(' ', $parts);
    }

    $major = match (strtoupper($currency)) {
        'USD' => 'US Dollars',
        'EUR' => 'Euros',
        'GBP' => 'Pounds',
        'INR' => 'Indian Rupees',
        default => 'Sri Lankan Rupees',
    };

    $out = $words . ' ' . $major;
    if ($cents > 0) {
        $out .= ' and ' . $toWords($cents) . ' Cents';
    }
    return $out . ' Only';
}

function vk_quotation_log(PDO $pdo, ?int $quotationId, string $action, string $details = ''): void
{
    try {
        $pdo->prepare(
            'INSERT INTO quotation_activity_logs (quotation_id, user_id, action, details, ip_address)
             VALUES (?,?,?,?,?)'
        )->execute([
            $quotationId,
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            $action,
            $details !== '' ? $details : null,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        ]);
    } catch (Throwable $e) {
        error_log('vk_quotation_log: ' . $e->getMessage());
    }
}

/**
 * @param array<string,mixed> $line
 * @return array<string,mixed>
 */
function vk_quotation_calc_line(array $line): array
{
    $qty = max(0, (float) ($line['quantity'] ?? 0));
    $unit = max(0, (float) ($line['unit_price'] ?? 0));
    $discPct = max(0, min(100, (float) ($line['discount_pct'] ?? 0)));
    $taxPct = max(0, (float) ($line['tax_pct'] ?? 0));
    $gross = round($qty * $unit, 2);
    $discAmt = isset($line['discount_amount']) && (float) $line['discount_amount'] > 0
        ? round((float) $line['discount_amount'], 2)
        : round($gross * $discPct / 100, 2);
    if ($discAmt > $gross) {
        $discAmt = $gross;
    }
    $afterDisc = round($gross - $discAmt, 2);
    $taxAmt = round($afterDisc * $taxPct / 100, 2);
    $total = round($afterDisc + $taxAmt, 2);
    $cost = max(0, (float) ($line['cost_price'] ?? 0));

    return array_merge($line, [
        'quantity' => $qty,
        'unit_price' => $unit,
        'discount_pct' => $discPct,
        'discount_amount' => $discAmt,
        'tax_pct' => $taxPct,
        'tax_amount' => $taxAmt,
        'line_subtotal' => $afterDisc,
        'line_total' => $total,
        'cost_price' => $cost,
    ]);
}

/**
 * @param list<array<string,mixed>> $lines
 * @param array<string,mixed> $header
 * @return array{lines:list<array<string,mixed>>,totals:array<string,float>}
 */
function vk_quotation_calc_totals(array $lines, array $header = []): array
{
    $calcLines = [];
    $subtotal = 0.0;
    $itemDisc = 0.0;
    $taxTotal = 0.0;
    $estCost = 0.0;

    foreach ($lines as $ln) {
        $c = vk_quotation_calc_line($ln);
        $calcLines[] = $c;
        $gross = round((float) $c['quantity'] * (float) $c['unit_price'], 2);
        $subtotal += $gross;
        $itemDisc += (float) $c['discount_amount'];
        $taxTotal += (float) $c['tax_amount'];
        $estCost += round((float) $c['cost_price'] * (float) $c['quantity'], 2);
    }

    $subtotal = round($subtotal, 2);
    $itemDisc = round($itemDisc, 2);
    $afterItemDisc = round($subtotal - $itemDisc, 2);

    $overallPct = max(0, min(100, (float) ($header['overall_discount_pct'] ?? 0)));
    $overallAmt = isset($header['overall_discount_amount']) && (float) $header['overall_discount_amount'] > 0
        ? round((float) $header['overall_discount_amount'], 2)
        : round($afterItemDisc * $overallPct / 100, 2);
    if ($overallAmt > $afterItemDisc) {
        $overallAmt = $afterItemDisc;
    }

    $taxMethod = (string) ($header['tax_method'] ?? 'exclusive');
    if ($taxMethod === 'none') {
        $taxTotal = 0.0;
        foreach ($calcLines as &$cl) {
            $cl['tax_pct'] = 0;
            $cl['tax_amount'] = 0;
            $cl['line_total'] = (float) $cl['line_subtotal'];
        }
        unset($cl);
    }

    $shipping = max(0, (float) ($header['shipping_amount'] ?? 0));
    $additional = max(0, (float) ($header['additional_charges'] ?? 0));
    $beforeRound = round($afterItemDisc - $overallAmt + $taxTotal + $shipping + $additional, 2);
    $roundOff = round((float) ($header['round_off'] ?? 0), 2);
    $grand = round($beforeRound + $roundOff, 2);
    if ($grand < 0) {
        $grand = 0.0;
    }

    $netProfit = round($grand - $estCost - $taxTotal, 2);
    $margin = $grand > 0 ? round(($netProfit / $grand) * 100, 2) : 0.0;

    return [
        'lines' => $calcLines,
        'totals' => [
            'subtotal' => $subtotal,
            'item_discount_total' => $itemDisc,
            'overall_discount_pct' => $overallPct,
            'overall_discount_amount' => $overallAmt,
            'tax_total' => round($taxTotal, 2),
            'shipping_amount' => $shipping,
            'additional_charges' => $additional,
            'round_off' => $roundOff,
            'grand_total' => $grand,
            'estimated_cost' => round($estCost, 2),
            'net_profit' => $netProfit,
            'profit_margin_pct' => $margin,
        ],
    ];
}

/**
 * @return array<string,mixed>|null
 */
function vk_quotation_get(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare(
        'SELECT q.*,
                c.name AS customer_name,
                c.phone AS customer_phone_db,
                c.email AS customer_email_db,
                c.address AS customer_address_db,
                u.fullname AS sales_executive_name,
                cat.name AS category_name,
                cb.fullname AS created_by_name
         FROM quotations q
         JOIN customers c ON c.id = q.customer_id
         LEFT JOIN users u ON u.id = q.sales_executive_id
         LEFT JOIN quotation_categories cat ON cat.id = q.category_id
         LEFT JOIN users cb ON cb.id = q.created_by
         WHERE q.id = ?'
    );
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
}

/** @return list<array<string,mixed>> */
function vk_quotation_items(PDO $pdo, int $quotationId): array
{
    $st = $pdo->prepare('SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY sort_order ASC, id ASC');
    $st->execute([$quotationId]);
    return $st->fetchAll();
}

/**
 * @param array<string,mixed> $data
 * @param list<array<string,mixed>> $lines
 */
function vk_quotation_save(PDO $pdo, array $data, array $lines, ?int $id = null): int
{
    $calc = vk_quotation_calc_totals($lines, $data);
    $t = $calc['totals'];
    $lines = $calc['lines'];
    $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    $fields = [
        'customer_id' => (int) ($data['customer_id'] ?? 0),
        'customer_code' => $data['customer_code'] ?? null,
        'company_name' => $data['company_name'] ?? null,
        'contact_person' => $data['contact_person'] ?? null,
        'phone' => $data['phone'] ?? null,
        'mobile' => $data['mobile'] ?? null,
        'email' => $data['email'] ?? null,
        'tax_number' => $data['tax_number'] ?? null,
        'credit_limit' => (float) ($data['credit_limit'] ?? 0),
        'billing_address' => $data['billing_address'] ?? null,
        'shipping_address' => $data['shipping_address'] ?? null,
        'currency' => $data['currency'] ?? 'LKR',
        'exchange_rate' => max(0.000001, (float) ($data['exchange_rate'] ?? 1)),
        'sales_executive_id' => !empty($data['sales_executive_id']) ? (int) $data['sales_executive_id'] : $uid,
        'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
        'template_id' => !empty($data['template_id']) ? (int) $data['template_id'] : null,
        'reference_number' => $data['reference_number'] ?? null,
        'customer_po_number' => $data['customer_po_number'] ?? null,
        'quotation_date' => $data['quotation_date'] ?? date('Y-m-d'),
        'expiry_date' => $data['expiry_date'] ?? null,
        'payment_terms' => $data['payment_terms'] ?? null,
        'delivery_terms' => $data['delivery_terms'] ?? null,
        'validity_days' => (int) ($data['validity_days'] ?? 30),
        'tax_method' => $data['tax_method'] ?? 'exclusive',
        'status' => $data['status'] ?? 'draft',
        'approval_status' => $data['approval_status'] ?? 'none',
        'subtotal' => $t['subtotal'],
        'item_discount_total' => $t['item_discount_total'],
        'overall_discount_pct' => $t['overall_discount_pct'],
        'overall_discount_amount' => $t['overall_discount_amount'],
        'tax_total' => $t['tax_total'],
        'shipping_amount' => $t['shipping_amount'],
        'additional_charges' => $t['additional_charges'],
        'round_off' => $t['round_off'],
        'grand_total' => $t['grand_total'],
        'estimated_cost' => $t['estimated_cost'],
        'net_profit' => $t['net_profit'],
        'profit_margin_pct' => $t['profit_margin_pct'],
        'notes' => $data['notes'] ?? null,
        'internal_notes' => $data['internal_notes'] ?? null,
        'terms_html' => $data['terms_html'] ?? null,
        'warranty_terms' => $data['warranty_terms'] ?? null,
        'expected_closing_date' => $data['expected_closing_date'] ?? null,
        'branch' => $data['branch'] ?? null,
        'department' => $data['department'] ?? null,
        'warehouse' => $data['warehouse'] ?? null,
        'updated_by' => $uid,
    ];

    // Drop columns that may not exist yet on partial schemas
    foreach (array_keys($fields) as $col) {
        if ($col === 'updated_by' || $col === 'customer_id') {
            continue;
        }
        if (!db_column_exists($pdo, 'quotations', $col) && !in_array($col, [
            'company_name', 'contact_person', 'phone', 'email', 'billing_address', 'shipping_address',
            'currency', 'sales_executive_id', 'category_id', 'template_id', 'reference_number',
            'quotation_date', 'expiry_date', 'payment_terms', 'delivery_terms', 'validity_days',
            'tax_method', 'status', 'approval_status', 'subtotal', 'item_discount_total',
            'overall_discount_pct', 'overall_discount_amount', 'tax_total', 'shipping_amount',
            'additional_charges', 'round_off', 'grand_total', 'estimated_cost', 'net_profit',
            'profit_margin_pct', 'notes', 'internal_notes', 'terms_html', 'expected_closing_date',
            'branch', 'created_by', 'quotation_number',
        ], true)) {
            // Only strip truly optional ERP columns if missing
            if (in_array($col, [
                'customer_code', 'mobile', 'tax_number', 'credit_limit', 'exchange_rate',
                'customer_po_number', 'department', 'warehouse', 'warranty_terms',
            ], true)) {
                unset($fields[$col]);
            }
        }
    }

    $pdo->beginTransaction();
    try {
        if ($id === null || $id <= 0) {
            $fields['quotation_number'] = next_quotation_number($pdo);
            $fields['created_by'] = $uid;
            $cols = array_keys($fields);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sql = 'INSERT INTO quotations (' . implode(',', $cols) . ') VALUES (' . $placeholders . ')';
            $pdo->prepare($sql)->execute(array_values($fields));
            $id = (int) $pdo->lastInsertId();
            vk_quotation_log($pdo, $id, 'created', 'Quotation ' . $fields['quotation_number'] . ' created');
        } else {
            // Snapshot revision before update
            vk_quotation_create_revision($pdo, $id, 'Auto-save before update');
            $sets = [];
            $vals = [];
            foreach ($fields as $k => $v) {
                $sets[] = "$k = ?";
                $vals[] = $v;
            }
            $vals[] = $id;
            $pdo->prepare('UPDATE quotations SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
            $pdo->prepare('DELETE FROM quotation_items WHERE quotation_id = ?')->execute([$id]);
            $pdo->prepare('UPDATE quotations SET revision_no = revision_no + 1 WHERE id = ?')->execute([$id]);
            vk_quotation_log($pdo, $id, 'updated', 'Quotation updated');
        }

        $hasWarehouse = db_column_exists($pdo, 'quotation_items', 'warehouse');
        $hasStockCol = db_column_exists($pdo, 'quotation_items', 'stock_available');
        if ($hasWarehouse && $hasStockCol) {
            $ins = $pdo->prepare(
                'INSERT INTO quotation_items
                    (quotation_id, sort_order, item_type, product_id, product_code, barcode, product_name,
                     category_name, description, unit, quantity, unit_price, cost_price,
                     discount_pct, discount_amount, tax_pct, tax_amount, line_subtotal, line_total, image_path,
                     warehouse, stock_available)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO quotation_items
                    (quotation_id, sort_order, item_type, product_id, product_code, barcode, product_name,
                     category_name, description, unit, quantity, unit_price, cost_price,
                     discount_pct, discount_amount, tax_pct, tax_amount, line_subtotal, line_total, image_path)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
        }
        foreach ($lines as $i => $ln) {
            $base = [
                $id,
                (int) ($ln['sort_order'] ?? $i),
                $ln['item_type'] ?? 'product',
                !empty($ln['product_id']) ? (int) $ln['product_id'] : null,
                $ln['product_code'] ?? null,
                $ln['barcode'] ?? null,
                (string) ($ln['product_name'] ?? 'Item'),
                $ln['category_name'] ?? null,
                $ln['description'] ?? null,
                $ln['unit'] ?? 'pcs',
                $ln['quantity'],
                $ln['unit_price'],
                $ln['cost_price'] ?? 0,
                $ln['discount_pct'],
                $ln['discount_amount'],
                $ln['tax_pct'],
                $ln['tax_amount'],
                $ln['line_subtotal'],
                $ln['line_total'],
                $ln['image_path'] ?? null,
            ];
            if ($hasWarehouse && $hasStockCol) {
                $base[] = $ln['warehouse'] ?? null;
                $base[] = isset($ln['stock_available']) ? (float) $ln['stock_available'] : null;
            }
            $ins->execute($base);
        }

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function vk_quotation_create_revision(PDO $pdo, int $quotationId, string $summary = ''): void
{
    $q = vk_quotation_get($pdo, $quotationId);
    if (!$q) {
        return;
    }
    $items = vk_quotation_items($pdo, $quotationId);
    $revNo = (int) $q['revision_no'];
    $snapshot = json_encode(['header' => $q, 'items' => $items], JSON_THROW_ON_ERROR);
    try {
        $pdo->prepare(
            'INSERT INTO quotation_revisions (quotation_id, revision_no, snapshot_json, change_summary, created_by)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE snapshot_json = VALUES(snapshot_json), change_summary = VALUES(change_summary)'
        )->execute([
            $quotationId,
            $revNo,
            $snapshot,
            $summary !== '' ? $summary : null,
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        ]);
    } catch (Throwable $e) {
        error_log('vk_quotation_create_revision: ' . $e->getMessage());
    }
}

/** @return list<string> */
function vk_quotation_approval_levels(PDO $pdo): array
{
    $raw = vk_quotation_setting($pdo, 'approval_levels', 'sales_executive,manager,finance,director');
    $levels = array_values(array_filter(array_map('trim', explode(',', $raw))));
    return $levels !== [] ? $levels : ['manager'];
}

function vk_quotation_submit_approval(PDO $pdo, int $id): void
{
    $levels = vk_quotation_approval_levels($pdo);
    $pdo->prepare('DELETE FROM quotation_approvals WHERE quotation_id = ?')->execute([$id]);
    $ins = $pdo->prepare(
        'INSERT INTO quotation_approvals (quotation_id, level, role_label, action) VALUES (?,?,?,\'pending\')'
    );
    foreach ($levels as $i => $role) {
        $ins->execute([$id, $i + 1, $role]);
    }
    $pdo->prepare(
        'UPDATE quotations SET status = \'pending_approval\', approval_status = \'pending\', approval_level = 1 WHERE id = ?'
    )->execute([$id]);
    vk_quotation_log($pdo, $id, 'submitted_approval', 'Submitted for multi-level approval');
}

function vk_quotation_approve_level(PDO $pdo, int $id, string $notes = '', bool $reject = false): void
{
    $q = vk_quotation_get($pdo, $id);
    if (!$q) {
        throw new RuntimeException('Quotation not found');
    }
    $level = max(1, (int) $q['approval_level']);
    $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    if ($reject) {
        $pdo->prepare(
            'UPDATE quotation_approvals SET action = \'rejected\', approver_id = ?, notes = ?, acted_at = NOW()
             WHERE quotation_id = ? AND level = ?'
        )->execute([$uid, $notes !== '' ? $notes : null, $id, $level]);
        $pdo->prepare(
            'UPDATE quotations SET status = \'rejected\', approval_status = \'rejected\' WHERE id = ?'
        )->execute([$id]);
        vk_quotation_log($pdo, $id, 'rejected', $notes);
        return;
    }

    $pdo->prepare(
        'UPDATE quotation_approvals SET action = \'approved\', approver_id = ?, notes = ?, acted_at = NOW()
         WHERE quotation_id = ? AND level = ?'
    )->execute([$uid, $notes !== '' ? $notes : null, $id, $level]);

    $st = $pdo->prepare('SELECT MAX(level) FROM quotation_approvals WHERE quotation_id = ?');
    $st->execute([$id]);
    $maxLevel = (int) $st->fetchColumn();

    if ($level >= $maxLevel) {
        $pdo->prepare(
            'UPDATE quotations SET status = \'approved\', approval_status = \'approved\', approval_level = ? WHERE id = ?'
        )->execute([$level, $id]);
        vk_quotation_log($pdo, $id, 'approved', 'Fully approved' . ($notes !== '' ? ': ' . $notes : ''));
    } else {
        $next = $level + 1;
        $pdo->prepare(
            'UPDATE quotations SET approval_level = ?, approval_status = \'pending\', status = \'pending_approval\' WHERE id = ?'
        )->execute([$next, $id]);
        vk_quotation_log($pdo, $id, 'approved_level', 'Level ' . $level . ' approved');
    }
}

function vk_quotation_mark_expired(PDO $pdo): int
{
    if (vk_quotation_setting($pdo, 'auto_expire', '1') !== '1') {
        return 0;
    }
    $st = $pdo->prepare(
        "UPDATE quotations
         SET status = 'expired'
         WHERE expiry_date IS NOT NULL
           AND expiry_date < CURDATE()
           AND status IN ('draft','pending_approval','approved','accepted')"
    );
    $st->execute();
    return $st->rowCount();
}

/**
 * Convert approved/accepted quotation into an invoice.
 */
function vk_quotation_convert_to_invoice(PDO $pdo, int $quotationId): int
{
    vk_ensure_finance_schemas($pdo);
    $q = vk_quotation_get($pdo, $quotationId);
    if (!$q) {
        throw new RuntimeException('Quotation not found');
    }
    if (!empty($q['converted_invoice_id'])) {
        return (int) $q['converted_invoice_id'];
    }
    if (!in_array($q['status'], ['approved', 'accepted', 'converted_so'], true)) {
        throw new RuntimeException('Only approved or accepted quotations can be converted.');
    }

    $items = vk_quotation_items($pdo, $quotationId);
    $accSt = $pdo->prepare('SELECT id FROM accounts WHERE customer_id = ? LIMIT 1');
    $accSt->execute([(int) $q['customer_id']]);
    $accountId = (int) $accSt->fetchColumn();
    if ($accountId <= 0) {
        throw new RuntimeException('Customer account not found.');
    }

    $pdo->beginTransaction();
    try {
        $invNo = next_invoice_number($pdo);
        $discount = (float) $q['item_discount_total'] + (float) $q['overall_discount_amount'];
        $tax = (float) $q['tax_total'];
        $grand = (float) $q['grand_total'];

        $insInv = $pdo->prepare(
            'INSERT INTO invoices
                (invoice_number, customer_id, invoice_date, subtotal, discount, tax, grand_total, paid_amount, status, notes, source)
             VALUES (?,?,?,?,?,?,?,0,?,?,?)'
        );
        $insInv->execute([
            $invNo,
            (int) $q['customer_id'],
            date('Y-m-d'),
            (float) $q['subtotal'],
            $discount,
            $tax,
            $grand,
            'unpaid',
            'Converted from quotation ' . $q['quotation_number'],
            'manual',
        ]);
        $invoiceId = (int) $pdo->lastInsertId();

        $insItem = $pdo->prepare(
            'INSERT INTO invoice_items (invoice_id, item_type, product_id, line_description, quantity, unit_price, line_total)
             VALUES (?,?,?,?,?,?,?)'
        );
        foreach ($items as $ln) {
            $type = ($ln['item_type'] ?? '') === 'product' && !empty($ln['product_id']) ? 'product' : 'service';
            $qty = max(1, (int) round((float) $ln['quantity']));
            $insItem->execute([
                $invoiceId,
                $type,
                $type === 'product' ? (int) $ln['product_id'] : null,
                $type === 'service' ? (string) $ln['product_name'] : null,
                $qty,
                (float) $ln['unit_price'],
                (float) $ln['line_total'],
            ]);
        }

        ledger_apply(
            $pdo,
            $accountId,
            $grand,
            0,
            'Invoice ' . $invNo . ' (from ' . $q['quotation_number'] . ')',
            $invoiceId
        );

        $pdo->prepare(
            'UPDATE quotations
             SET status = \'converted_invoice\', converted_invoice_id = ?, converted_at = NOW()
             WHERE id = ?'
        )->execute([$invoiceId, $quotationId]);

        vk_quotation_log($pdo, $quotationId, 'converted_invoice', 'Converted to invoice ' . $invNo);
        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** @return array<string,mixed> */
function vk_quotation_dashboard_kpis(PDO $pdo): array
{
    vk_quotation_mark_expired($pdo);
    $kpi = [
        'total' => 0, 'today' => 0, 'week' => 0, 'month' => 0, 'draft' => 0, 'pending_approval' => 0, 'approved' => 0,
        'rejected' => 0, 'expired' => 0, 'accepted' => 0, 'converted_invoice' => 0, 'converted' => 0,
        'value' => 0.0, 'forecast' => 0.0, 'month_revenue' => 0.0,
        'customers' => 0, 'products' => 0,
    ];
    try {
        $row = $pdo->query(
            "SELECT
                COUNT(*) AS total,
                SUM(quotation_date = CURDATE()) AS today,
                SUM(quotation_date >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                    AND quotation_date <= CURDATE()) AS week,
                SUM(YEAR(quotation_date)=YEAR(CURDATE()) AND MONTH(quotation_date)=MONTH(CURDATE())) AS month,
                SUM(status = 'draft') AS draft,
                SUM(status = 'pending_approval') AS pending_approval,
                SUM(status = 'approved') AS approved,
                SUM(status = 'rejected') AS rejected,
                SUM(status = 'expired') AS expired,
                SUM(status = 'accepted') AS accepted,
                SUM(status = 'converted_invoice') AS converted_invoice,
                SUM(status IN ('converted_invoice','converted_so','accepted')) AS converted,
                COALESCE(SUM(grand_total),0) AS value,
                COALESCE(SUM(CASE WHEN status IN ('approved','accepted','pending_approval')
                    AND MONTH(quotation_date)=MONTH(CURDATE()) AND YEAR(quotation_date)=YEAR(CURDATE())
                    THEN grand_total ELSE 0 END),0) AS forecast,
                COALESCE(SUM(CASE WHEN status IN ('converted_invoice','accepted','approved')
                    AND MONTH(quotation_date)=MONTH(CURDATE()) AND YEAR(quotation_date)=YEAR(CURDATE())
                    THEN grand_total ELSE 0 END),0) AS month_revenue
             FROM quotations"
        )->fetch();
        if ($row) {
            foreach (['total','today','week','month','draft','pending_approval','approved','rejected','expired','accepted','converted_invoice','converted'] as $k) {
                $kpi[$k] = (int) ($row[$k] ?? 0);
            }
            $kpi['value'] = (float) ($row['value'] ?? 0);
            $kpi['forecast'] = (float) ($row['forecast'] ?? 0);
            $kpi['month_revenue'] = (float) ($row['month_revenue'] ?? 0);
        }
        $kpi['customers'] = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
        if (db_table_exists($pdo, 'products')) {
            $kpi['products'] = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        }
    } catch (Throwable $e) {
        error_log('vk_quotation_dashboard_kpis: ' . $e->getMessage());
    }
    return $kpi;
}

/** @return list<array<string,mixed>> */
function vk_quotation_search_products(PDO $pdo, string $q, int $limit = 20): array
{
    $q = trim($q);
    $limit = max(1, min(50, $limit));
    $hasSku = db_column_exists($pdo, 'products', 'sku');
    $hasBarcode = db_column_exists($pdo, 'products', 'barcode');
    $priceCol = db_column_exists($pdo, 'products', 'selling_price') ? 'selling_price' : 'price';
    $costCol = db_column_exists($pdo, 'products', 'cost_price') ? 'cost_price' : null;
    $catJoin = db_column_exists($pdo, 'products', 'category_id') && db_table_exists($pdo, 'categories');

    $select = "p.id, p.name, p.{$priceCol} AS unit_price";
    if ($costCol) {
        $select .= ", p.{$costCol} AS cost_price";
    } else {
        $select .= ', 0 AS cost_price';
    }
    if ($hasSku) {
        $select .= ', p.sku AS product_code';
    } else {
        $select .= ', CAST(p.id AS CHAR) AS product_code';
    }
    if ($hasBarcode) {
        $select .= ', p.barcode';
    } else {
        $select .= ', NULL AS barcode';
    }
    if (db_column_exists($pdo, 'products', 'stock')) {
        $select .= ', p.stock AS stock_available';
    } elseif (db_column_exists($pdo, 'products', 'opening_stock')) {
        $select .= ', p.opening_stock AS stock_available';
    } else {
        $select .= ', NULL AS stock_available';
    }
    if (db_column_exists($pdo, 'products', 'short_description')) {
        $select .= ', p.short_description AS description';
    } elseif (db_column_exists($pdo, 'products', 'description')) {
        $select .= ', LEFT(p.description, 255) AS description';
    } else {
        $select .= ', NULL AS description';
    }
    if (db_table_exists($pdo, 'units') && db_column_exists($pdo, 'products', 'unit_id')) {
        $select .= ', COALESCE(u.symbol, u.name, \'pcs\') AS unit';
    } else {
        $select .= ', \'pcs\' AS unit';
    }
    if ($catJoin) {
        $select .= ', cat.name AS category_name';
    } elseif (db_column_exists($pdo, 'products', 'category')) {
        $select .= ', p.category AS category_name';
    } else {
        $select .= ', NULL AS category_name';
    }

    $sql = "SELECT {$select} FROM products p";
    if ($catJoin) {
        $sql .= ' LEFT JOIN categories cat ON cat.id = p.category_id';
    }
    if (db_table_exists($pdo, 'units') && db_column_exists($pdo, 'products', 'unit_id')) {
        $sql .= ' LEFT JOIN units u ON u.id = p.unit_id';
    }
    $params = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $w = ['p.name LIKE ?'];
        $params[] = $like;
        if ($hasSku) {
            $w[] = 'p.sku LIKE ?';
            $params[] = $like;
        }
        if ($hasBarcode) {
            $w[] = 'p.barcode LIKE ?';
            $params[] = $like;
        }
        $sql .= ' WHERE (' . implode(' OR ', $w) . ')';
    }
    $sql .= " ORDER BY p.name ASC LIMIT {$limit}";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/**
 * Parse POST line arrays into structured lines.
 * @return list<array<string,mixed>>
 */
function vk_quotation_parse_lines_from_post(array $post): array
{
    $names = $post['product_name'] ?? [];
    if (!is_array($names)) {
        return [];
    }
    $lines = [];
    $n = count($names);
    for ($i = 0; $i < $n; $i++) {
        $name = trim((string) ($names[$i] ?? ''));
        if ($name === '') {
            continue;
        }
        $lines[] = [
            'sort_order' => $i,
            'item_type' => (string) (($post['item_type'][$i] ?? 'custom') ?: 'custom'),
            'product_id' => (int) ($post['product_id'][$i] ?? 0) ?: null,
            'product_code' => trim((string) ($post['product_code'][$i] ?? '')),
            'barcode' => trim((string) ($post['barcode'][$i] ?? '')),
            'product_name' => $name,
            'category_name' => trim((string) ($post['category_name'][$i] ?? '')),
            'description' => trim((string) ($post['description'][$i] ?? '')),
            'unit' => trim((string) ($post['unit'][$i] ?? 'pcs')) ?: 'pcs',
            'quantity' => (float) ($post['quantity'][$i] ?? 1),
            'unit_price' => (float) ($post['unit_price'][$i] ?? 0),
            'cost_price' => (float) ($post['cost_price'][$i] ?? 0),
            'discount_pct' => (float) ($post['discount_pct'][$i] ?? 0),
            'discount_amount' => (float) ($post['discount_amount'][$i] ?? 0),
            'tax_pct' => (float) ($post['tax_pct'][$i] ?? 0),
            'warehouse' => trim((string) ($post['line_warehouse'][$i] ?? $post['warehouse'][$i] ?? '')),
            'stock_available' => isset($post['stock_available'][$i]) ? (float) $post['stock_available'][$i] : null,
        ];
    }
    return $lines;
}

/** @return array<string,mixed> */
function vk_quotation_header_from_post(array $post): array
{
    $validity = max(1, (int) ($post['validity_days'] ?? 30));
    $qDate = trim((string) ($post['quotation_date'] ?? date('Y-m-d')));
    $expiry = trim((string) ($post['expiry_date'] ?? ''));
    if ($expiry === '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $qDate)) {
        try {
            $expiry = (new DateTime($qDate))->modify('+' . $validity . ' days')->format('Y-m-d');
        } catch (Throwable $e) {
            $expiry = date('Y-m-d', strtotime('+' . $validity . ' days'));
        }
    }

    return [
        'customer_id' => (int) ($post['customer_id'] ?? 0),
        'customer_code' => trim((string) ($post['customer_code'] ?? '')),
        'company_name' => trim((string) ($post['company_name'] ?? '')),
        'contact_person' => trim((string) ($post['contact_person'] ?? '')),
        'phone' => trim((string) ($post['phone'] ?? '')),
        'mobile' => trim((string) ($post['mobile'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'tax_number' => trim((string) ($post['tax_number'] ?? '')),
        'credit_limit' => (float) ($post['credit_limit'] ?? 0),
        'billing_address' => trim((string) ($post['billing_address'] ?? '')),
        'shipping_address' => trim((string) ($post['shipping_address'] ?? '')),
        'currency' => trim((string) ($post['currency'] ?? 'LKR')) ?: 'LKR',
        'exchange_rate' => max(0.000001, (float) ($post['exchange_rate'] ?? 1)),
        'sales_executive_id' => (int) ($post['sales_executive_id'] ?? 0) ?: null,
        'category_id' => (int) ($post['category_id'] ?? 0) ?: null,
        'template_id' => (int) ($post['template_id'] ?? 0) ?: null,
        'reference_number' => trim((string) ($post['reference_number'] ?? '')),
        'customer_po_number' => trim((string) ($post['customer_po_number'] ?? '')),
        'quotation_date' => $qDate,
        'expiry_date' => $expiry,
        'payment_terms' => trim((string) ($post['payment_terms'] ?? '')),
        'delivery_terms' => trim((string) ($post['delivery_terms'] ?? '')),
        'validity_days' => $validity,
        'tax_method' => in_array(($post['tax_method'] ?? ''), ['exclusive', 'inclusive', 'none'], true)
            ? $post['tax_method'] : 'exclusive',
        'status' => in_array(($post['status'] ?? ''), vk_quotation_statuses(), true) ? $post['status'] : 'draft',
        'overall_discount_pct' => (float) ($post['overall_discount_pct'] ?? 0),
        'overall_discount_amount' => (float) ($post['overall_discount_amount'] ?? 0),
        'shipping_amount' => (float) ($post['shipping_amount'] ?? 0),
        'additional_charges' => (float) ($post['additional_charges'] ?? 0),
        'round_off' => (float) ($post['round_off'] ?? 0),
        'notes' => trim((string) ($post['notes'] ?? '')),
        'internal_notes' => trim((string) ($post['internal_notes'] ?? '')),
        'terms_html' => trim((string) ($post['terms_html'] ?? '')),
        'warranty_terms' => trim((string) ($post['warranty_terms'] ?? '')),
        'expected_closing_date' => trim((string) ($post['expected_closing_date'] ?? '')) ?: null,
        'branch' => trim((string) ($post['branch'] ?? '')),
        'department' => trim((string) ($post['department'] ?? '')),
        'warehouse' => trim((string) ($post['warehouse'] ?? '')),
    ];
}

function vk_quotation_whatsapp_url(PDO $pdo, array $q): string
{
    $tpl = vk_quotation_setting($pdo, 'whatsapp_template',
        "Hello {customer_name},\n\nQuotation *{quotation_number}* — LKR {grand_total}\nValid until: {expiry_date}\n\n{print_url}\n\n— VK Network");
    $printUrl = rtrim(BASE_URL, '/') . '/modules/quotations/print.php?id=' . (int) $q['id'];
    $msg = str_replace(
        ['{customer_name}', '{quotation_number}', '{grand_total}', '{expiry_date}', '{print_url}'],
        [
            (string) ($q['contact_person'] ?: $q['customer_name'] ?? 'Customer'),
            (string) $q['quotation_number'],
            number_format((float) $q['grand_total'], 2),
            (string) ($q['expiry_date'] ?? '—'),
            $printUrl,
        ],
        $tpl
    );
    $phone = preg_replace('/\D+/', '', (string) ($q['phone'] ?? $q['customer_phone_db'] ?? ''));
    if ($phone !== null && strlen($phone) === 10 && str_starts_with($phone, '0')) {
        $phone = '94' . substr($phone, 1);
    }
    return 'https://wa.me/' . ($phone ?: '') . '?text=' . rawurlencode($msg);
}

/**
 * Store uploaded quotation attachments (PDF, images, Office docs, drawings).
 *
 * @param array<string,mixed> $files typically $_FILES['attachments']
 * @return list<array{name:string,path:string,mime:?string,size:int}>
 */
function vk_quotation_store_attachments(PDO $pdo, int $quotationId, array $files): array
{
    if ($quotationId <= 0 || empty($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $allowedExt = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'xls', 'xlsx', 'doc', 'docx', 'dwg', 'dxf', 'csv'];
    $allowedMime = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/octet-stream', 'text/csv', 'application/csv',
    ];
    $maxBytes = 8 * 1024 * 1024;
    $dir = ROOT_PATH . '/uploads/quotations/' . $quotationId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $saved = [];
    $ins = $pdo->prepare(
        'INSERT INTO quotation_attachments (quotation_id, file_name, file_path, mime_type, file_size, uploaded_by)
         VALUES (?,?,?,?,?,?)'
    );
    $uid = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE || $err !== UPLOAD_ERR_OK) {
            continue;
        }
        $tmp = (string) ($files['tmp_name'][$i] ?? '');
        $orig = (string) ($files['name'][$i] ?? 'file');
        $size = (int) ($files['size'][$i] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0 || $size > $maxBytes) {
            continue;
        }
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($tmp) ?: ($files['type'][$i] ?? ''));
        if ($mime !== '' && !in_array($mime, $allowedMime, true) && !str_starts_with($mime, 'image/')) {
            if (!in_array($ext, ['dwg', 'dxf', 'xlsx', 'docx', 'xls', 'doc'], true)) {
                continue;
            }
        }
        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($orig, PATHINFO_FILENAME)) ?: 'file';
        $filename = $safe . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            continue;
        }
        $rel = 'uploads/quotations/' . $quotationId . '/' . $filename;
        $ins->execute([$quotationId, $orig, $rel, $mime ?: null, $size, $uid]);
        $saved[] = ['name' => $orig, 'path' => $rel, 'mime' => $mime, 'size' => $size];
    }

    return $saved;
}
