<?php
declare(strict_types=1);

/**
 * Invoice ERP schema — auto-migrates on finance bootstrap.
 * Extends existing invoices / invoice_items without breaking legacy data.
 */
function vk_ensure_invoice_items_table(PDO $pdo): void
{
    vk_ensure_invoices_schema($pdo);
}

/**
 * Full invoice schema ensure (header columns, line columns, history, revisions).
 */
function vk_ensure_invoices_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $pdo->exec('SET NAMES utf8mb4');
    } catch (Throwable $e) {
        // ignore
    }

    // ── invoice_items base table ─────────────────────────────────────
    if (!db_table_exists($pdo, 'invoice_items')) {
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS invoice_items (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  invoice_id INT UNSIGNED NOT NULL,
                  item_type ENUM('product','service') NOT NULL DEFAULT 'product',
                  product_id INT UNSIGNED DEFAULT NULL,
                  line_description VARCHAR(512) DEFAULT NULL,
                  quantity INT UNSIGNED NOT NULL DEFAULT 1,
                  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                  line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                  INDEX idx_invoice_items_invoice (invoice_id),
                  INDEX idx_invoice_items_product (product_id),
                  CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('vk_ensure_invoices_schema create invoice_items: ' . $e->getMessage());
        }
    }

    if (!db_table_exists($pdo, 'invoice_items')) {
        return;
    }

    // Legacy + new line columns
    $itemColumns = [
        'item_type' => "ALTER TABLE invoice_items ADD COLUMN item_type ENUM('product','service') NOT NULL DEFAULT 'product' AFTER invoice_id",
        'product_id' => 'ALTER TABLE invoice_items ADD COLUMN product_id INT UNSIGNED DEFAULT NULL AFTER item_type',
        'item_code' => 'ALTER TABLE invoice_items ADD COLUMN item_code VARCHAR(128) DEFAULT NULL AFTER product_id',
        'line_description' => 'ALTER TABLE invoice_items ADD COLUMN line_description VARCHAR(512) DEFAULT NULL AFTER item_code',
        'unit' => "ALTER TABLE invoice_items ADD COLUMN unit VARCHAR(32) NOT NULL DEFAULT 'pcs' AFTER line_description",
        'quantity' => 'ALTER TABLE invoice_items ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER unit',
        'unit_price' => 'ALTER TABLE invoice_items ADD COLUMN unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER quantity',
        'discount_type' => "ALTER TABLE invoice_items ADD COLUMN discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent' AFTER unit_price",
        'discount_value' => 'ALTER TABLE invoice_items ADD COLUMN discount_value DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER discount_type',
        'discount_amount' => 'ALTER TABLE invoice_items ADD COLUMN discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER discount_value',
        'tax_pct' => 'ALTER TABLE invoice_items ADD COLUMN tax_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER discount_amount',
        'tax_amount' => 'ALTER TABLE invoice_items ADD COLUMN tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER tax_pct',
        'net_price' => 'ALTER TABLE invoice_items ADD COLUMN net_price DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER tax_amount',
        'net_amount' => 'ALTER TABLE invoice_items ADD COLUMN net_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER net_price',
        'line_total' => 'ALTER TABLE invoice_items ADD COLUMN line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER net_amount',
        'sort_order' => 'ALTER TABLE invoice_items ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER line_total',
        'cost_price' => 'ALTER TABLE invoice_items ADD COLUMN cost_price DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER sort_order',
    ];

    foreach ($itemColumns as $column => $sql) {
        if (!db_column_exists($pdo, 'invoice_items', $column)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                error_log('vk_ensure_invoices_schema invoice_items.' . $column . ': ' . $e->getMessage());
            }
        }
    }

    try {
        $pdo->exec('ALTER TABLE invoice_items MODIFY product_id INT UNSIGNED DEFAULT NULL');
    } catch (Throwable $e) {
        // ignore
    }

    // ── invoices header extensions ───────────────────────────────────
    if (db_table_exists($pdo, 'invoices')) {
        $invColumns = [
            'due_date' => 'ALTER TABLE invoices ADD COLUMN due_date DATE DEFAULT NULL AFTER invoice_date',
            'branch' => 'ALTER TABLE invoices ADD COLUMN branch VARCHAR(128) DEFAULT NULL AFTER due_date',
            'salesperson_id' => 'ALTER TABLE invoices ADD COLUMN salesperson_id INT UNSIGNED DEFAULT NULL AFTER branch',
            'currency' => "ALTER TABLE invoices ADD COLUMN currency VARCHAR(8) NOT NULL DEFAULT 'LKR' AFTER salesperson_id",
            'reference_number' => 'ALTER TABLE invoices ADD COLUMN reference_number VARCHAR(128) DEFAULT NULL AFTER currency',
            'payment_method' => 'ALTER TABLE invoices ADD COLUMN payment_method VARCHAR(64) DEFAULT NULL AFTER reference_number',
            'terms' => 'ALTER TABLE invoices ADD COLUMN terms TEXT AFTER payment_method',
            'internal_notes' => 'ALTER TABLE invoices ADD COLUMN internal_notes TEXT AFTER notes',
            'item_discount_total' => 'ALTER TABLE invoices ADD COLUMN item_discount_total DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER subtotal',
            'invoice_discount_type' => "ALTER TABLE invoices ADD COLUMN invoice_discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'fixed' AFTER item_discount_total",
            'invoice_discount_value' => 'ALTER TABLE invoices ADD COLUMN invoice_discount_value DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER invoice_discount_type',
            'invoice_discount_amount' => 'ALTER TABLE invoices ADD COLUMN invoice_discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER invoice_discount_value',
            'shipping_amount' => 'ALTER TABLE invoices ADD COLUMN shipping_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER tax',
            'adjustment_amount' => 'ALTER TABLE invoices ADD COLUMN adjustment_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER shipping_amount',
            'round_off' => 'ALTER TABLE invoices ADD COLUMN round_off DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER adjustment_amount',
            'revision_no' => 'ALTER TABLE invoices ADD COLUMN revision_no INT UNSIGNED NOT NULL DEFAULT 0 AFTER round_off',
            'created_by' => 'ALTER TABLE invoices ADD COLUMN created_by INT UNSIGNED DEFAULT NULL AFTER revision_no',
            'updated_by' => 'ALTER TABLE invoices ADD COLUMN updated_by INT UNSIGNED DEFAULT NULL AFTER created_by',
            'is_draft' => 'ALTER TABLE invoices ADD COLUMN is_draft TINYINT(1) NOT NULL DEFAULT 0 AFTER updated_by',
            'cancelled_at' => 'ALTER TABLE invoices ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER is_draft',
            'cancelled_by' => 'ALTER TABLE invoices ADD COLUMN cancelled_by INT UNSIGNED DEFAULT NULL AFTER cancelled_at',
            'cancel_reason' => 'ALTER TABLE invoices ADD COLUMN cancel_reason VARCHAR(512) DEFAULT NULL AFTER cancelled_by',
        ];

        foreach ($invColumns as $column => $sql) {
            if (!db_column_exists($pdo, 'invoices', $column)) {
                try {
                    $pdo->exec($sql);
                } catch (Throwable $e) {
                    error_log('vk_ensure_invoices_schema invoices.' . $column . ': ' . $e->getMessage());
                }
            }
        }

        // Expand status enum for draft / cancelled (keep unpaid/partial/paid)
        try {
            $pdo->exec(
                "ALTER TABLE invoices MODIFY COLUMN status ENUM('draft','unpaid','partial','paid','cancelled') NOT NULL DEFAULT 'unpaid'"
            );
        } catch (Throwable $e) {
            error_log('vk_ensure_invoices_schema status enum: ' . $e->getMessage());
        }

        // Backfill: map legacy flat discount into invoice_discount_* when empty
        try {
            if (db_column_exists($pdo, 'invoices', 'invoice_discount_amount')) {
                $pdo->exec(
                    "UPDATE invoices
                     SET invoice_discount_type = 'fixed',
                         invoice_discount_value = discount,
                         invoice_discount_amount = discount
                     WHERE (invoice_discount_amount IS NULL OR invoice_discount_amount = 0)
                       AND discount > 0"
                );
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    // ── invoice_history (field-level audit) ──────────────────────────
    if (!db_table_exists($pdo, 'invoice_history')) {
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS invoice_history (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  invoice_id INT UNSIGNED NOT NULL,
                  field_name VARCHAR(128) NOT NULL,
                  old_value TEXT,
                  new_value TEXT,
                  edited_by INT UNSIGNED DEFAULT NULL,
                  edited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  ip_address VARCHAR(64) DEFAULT NULL,
                  revision_no INT UNSIGNED NOT NULL DEFAULT 0,
                  reason VARCHAR(512) DEFAULT NULL,
                  INDEX idx_inv_hist_invoice (invoice_id, edited_at),
                  INDEX idx_inv_hist_rev (invoice_id, revision_no),
                  CONSTRAINT fk_inv_hist_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('vk_ensure_invoices_schema invoice_history: ' . $e->getMessage());
        }
    }

    // ── invoice_revisions (full snapshots) ───────────────────────────
    if (!db_table_exists($pdo, 'invoice_revisions')) {
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS invoice_revisions (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  invoice_id INT UNSIGNED NOT NULL,
                  revision_no INT UNSIGNED NOT NULL,
                  snapshot_json LONGTEXT NOT NULL,
                  change_summary VARCHAR(512) DEFAULT NULL,
                  created_by INT UNSIGNED DEFAULT NULL,
                  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  UNIQUE KEY uq_inv_rev (invoice_id, revision_no),
                  CONSTRAINT fk_inv_rev_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('vk_ensure_invoices_schema invoice_revisions: ' . $e->getMessage());
        }
    }

    foreach (['idx_invoice_items_invoice' => 'invoice_id', 'idx_invoice_items_product' => 'product_id'] as $idx => $col) {
        try {
            $pdo->exec("CREATE INDEX {$idx} ON invoice_items ({$col})");
        } catch (Throwable $e) {
            // exists
        }
    }
}
