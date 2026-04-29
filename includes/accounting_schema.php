<?php
declare(strict_types=1);

/**
 * Ensure account ledger storage exists for partial installs / restored DBs.
 *
 * Convention:
 * - debit increases customer receivable / amount owed
 * - credit decreases customer receivable / amount owed
 */
function vk_ensure_account_ledger_table(PDO $pdo): void
{
    if (!db_table_exists($pdo, 'account_ledger')) {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS account_ledger (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              account_id INT UNSIGNED NOT NULL,
              customer_id INT UNSIGNED DEFAULT NULL,
              invoice_id INT UNSIGNED DEFAULT NULL,
              payment_id INT UNSIGNED DEFAULT NULL,
              transfer_id INT UNSIGNED DEFAULT NULL,
              entry_type ENUM(\'debit\',\'credit\') NOT NULL,
              amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
              debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
              credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
              balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
              description VARCHAR(512) DEFAULT NULL,
              entry_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_ledger_account (account_id, entry_datetime),
              INDEX idx_ledger_customer (customer_id, created_at),
              INDEX idx_ledger_invoice (invoice_id),
              INDEX idx_ledger_payment (payment_id),
              INDEX idx_ledger_type_date (entry_type, created_at),
              CONSTRAINT fk_ledger_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
              CONSTRAINT fk_ledger_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
              CONSTRAINT fk_ledger_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    if (!db_table_exists($pdo, 'account_ledger')) {
        return;
    }

    $columns = [
        'account_id' => 'ALTER TABLE account_ledger ADD COLUMN account_id INT UNSIGNED NOT NULL AFTER id',
        'customer_id' => 'ALTER TABLE account_ledger ADD COLUMN customer_id INT UNSIGNED DEFAULT NULL AFTER account_id',
        'invoice_id' => 'ALTER TABLE account_ledger ADD COLUMN invoice_id INT UNSIGNED DEFAULT NULL AFTER customer_id',
        'payment_id' => 'ALTER TABLE account_ledger ADD COLUMN payment_id INT UNSIGNED DEFAULT NULL AFTER invoice_id',
        'transfer_id' => 'ALTER TABLE account_ledger ADD COLUMN transfer_id INT UNSIGNED DEFAULT NULL AFTER payment_id',
        'entry_type' => "ALTER TABLE account_ledger ADD COLUMN entry_type ENUM('debit','credit') NOT NULL DEFAULT 'debit' AFTER transfer_id",
        'amount' => 'ALTER TABLE account_ledger ADD COLUMN amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER entry_type',
        'debit' => 'ALTER TABLE account_ledger ADD COLUMN debit DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER amount',
        'credit' => 'ALTER TABLE account_ledger ADD COLUMN credit DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER debit',
        'balance' => 'ALTER TABLE account_ledger ADD COLUMN balance DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER credit',
        'description' => 'ALTER TABLE account_ledger ADD COLUMN description VARCHAR(512) DEFAULT NULL AFTER balance',
        'entry_datetime' => 'ALTER TABLE account_ledger ADD COLUMN entry_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER description',
        'created_at' => 'ALTER TABLE account_ledger ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER entry_datetime',
    ];

    foreach ($columns as $column => $sql) {
        if (!db_column_exists($pdo, 'account_ledger', $column)) {
            $pdo->exec($sql);
        }
    }

    foreach ([
        'CREATE INDEX idx_ledger_account ON account_ledger (account_id, entry_datetime)',
        'CREATE INDEX idx_ledger_customer ON account_ledger (customer_id, created_at)',
        'CREATE INDEX idx_ledger_invoice ON account_ledger (invoice_id)',
        'CREATE INDEX idx_ledger_payment ON account_ledger (payment_id)',
        'CREATE INDEX idx_ledger_type_date ON account_ledger (entry_type, created_at)',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
        }
    }
}

function vk_customer_balance(PDO $pdo, int $customerId): float
{
    vk_ensure_account_ledger_table($pdo);
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE -amount END), 0)
         FROM account_ledger
         WHERE customer_id = ?"
    );
    $st->execute([$customerId]);

    return round((float) $st->fetchColumn(), 2);
}
