<?php
declare(strict_types=1);

CREATE TABLE IF NOT EXISTS invoice_print_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    settings_json LONGTEXT NOT NULL,
    backup_json LONGTEXT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_invoice_print_settings_single CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO invoice_print_settings (id, settings_json)
SELECT 1, '{}'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM invoice_print_settings WHERE id = 1);
