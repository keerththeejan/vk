<?php
declare(strict_types=1);

/**
 * Ensure invoice line-item storage exists for partial installs / restored DBs.
 * Requires invoices and products when foreign keys are created on a new table.
 */
function vk_ensure_invoice_items_table(PDO $pdo): void
{
    if (!db_table_exists($pdo, 'invoice_items')) {
        try {
            $pdo->exec('SET NAMES utf8mb4');
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS invoice_items (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  invoice_id INT UNSIGNED NOT NULL,
                  item_type ENUM(\'product\',\'service\') NOT NULL DEFAULT \'product\',
                  product_id INT UNSIGNED DEFAULT NULL,
                  line_description VARCHAR(512) DEFAULT NULL,
                  quantity INT UNSIGNED NOT NULL DEFAULT 1,
                  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                  line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                  INDEX idx_invoice_items_invoice (invoice_id),
                  INDEX idx_invoice_items_product (product_id),
                  CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
                  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            error_log('vk_ensure_invoice_items_table create: ' . $e->getMessage());
        }
    }

    if (!db_table_exists($pdo, 'invoice_items')) {
        return;
    }

    $columns = [
        'item_type' => "ALTER TABLE invoice_items ADD COLUMN item_type ENUM('product','service') NOT NULL DEFAULT 'product' AFTER invoice_id",
        'product_id' => 'ALTER TABLE invoice_items ADD COLUMN product_id INT UNSIGNED DEFAULT NULL AFTER item_type',
        'line_description' => 'ALTER TABLE invoice_items ADD COLUMN line_description VARCHAR(512) DEFAULT NULL AFTER product_id',
        'quantity' => 'ALTER TABLE invoice_items ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER line_description',
        'unit_price' => 'ALTER TABLE invoice_items ADD COLUMN unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER quantity',
        'line_total' => 'ALTER TABLE invoice_items ADD COLUMN line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER unit_price',
    ];

    foreach ($columns as $column => $sql) {
        if (!db_column_exists($pdo, 'invoice_items', $column)) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                error_log('vk_ensure_invoice_items_table add ' . $column . ': ' . $e->getMessage());
            }
        }
    }

    try {
        $pdo->exec('ALTER TABLE invoice_items MODIFY product_id INT UNSIGNED DEFAULT NULL');
    } catch (Throwable $e) {
        error_log('vk_ensure_invoice_items_table product nullable: ' . $e->getMessage());
    }

    try {
        $pdo->exec('CREATE INDEX idx_invoice_items_invoice ON invoice_items (invoice_id)');
    } catch (Throwable $e) {
    }

    try {
        $pdo->exec('CREATE INDEX idx_invoice_items_product ON invoice_items (product_id)');
    } catch (Throwable $e) {
    }
}
