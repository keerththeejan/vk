-- Additive migration for the simplified VK Network product entry form.
-- Preserves all existing product columns and data; existing records keep a NULL code.
ALTER TABLE products
  ADD COLUMN sku VARCHAR(128) NULL,
  ADD COLUMN description TEXT NULL,
  ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN warranty_duration_days INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN opening_stock INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN min_stock INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN serial_number VARCHAR(191) NULL,
  ADD UNIQUE KEY uq_products_sku (sku);
