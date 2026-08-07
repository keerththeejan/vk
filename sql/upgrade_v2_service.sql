-- Upgrade existing vk_billing (v1) to Service & Billing v2
-- Backup first. Run: mysql -u root -p vk_billing < upgrade_v2_service.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

/* --- payments: allow job links and nullable invoice --- */
ALTER TABLE payments DROP FOREIGN KEY fk_payments_invoice;
ALTER TABLE payments MODIFY invoice_id INT UNSIGNED NULL;
ALTER TABLE payments ADD COLUMN repair_job_id INT UNSIGNED NULL AFTER invoice_id;
ALTER TABLE payments ADD COLUMN cctv_job_id INT UNSIGNED NULL AFTER repair_job_id;
ALTER TABLE payments MODIFY method ENUM('cash','card','bank','online') NOT NULL DEFAULT 'cash';

/* --- invoice_items: service lines + nullable product --- */
ALTER TABLE invoice_items DROP FOREIGN KEY fk_items_product;
ALTER TABLE invoice_items MODIFY product_id INT UNSIGNED NULL;
ALTER TABLE invoice_items ADD COLUMN item_type ENUM('product','service') NOT NULL DEFAULT 'product' AFTER invoice_id;
ALTER TABLE invoice_items ADD COLUMN line_description VARCHAR(512) NULL AFTER product_id;
UPDATE invoice_items SET item_type = 'product' WHERE product_id IS NOT NULL;
ALTER TABLE invoice_items ADD CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT;

/* --- invoices: source + job links --- */
ALTER TABLE invoices ADD COLUMN source ENUM('manual','repair','cctv') NOT NULL DEFAULT 'manual' AFTER notes;
ALTER TABLE invoices ADD COLUMN repair_job_id INT UNSIGNED NULL AFTER source;
ALTER TABLE invoices ADD COLUMN cctv_job_id INT UNSIGNED NULL AFTER repair_job_id;

/* --- new job tables (create before adding FK from invoices) --- */
CREATE TABLE IF NOT EXISTS repair_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_number VARCHAR(64) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  device_type ENUM('laptop','desktop','cctv','dvr','printer','other') NOT NULL DEFAULT 'other',
  problem_description TEXT,
  accessories_received TEXT,
  estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending','in_progress','completed','delivered') NOT NULL DEFAULT 'pending',
  technician_notes TEXT,
  warranty_expiry DATE DEFAULT NULL,
  invoice_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_repair_customer (customer_id),
  INDEX idx_repair_status (status),
  CONSTRAINT fk_upg_repair_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cctv_installations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_number VARCHAR(64) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  location TEXT NOT NULL,
  num_cameras INT UNSIGNED NOT NULL DEFAULT 1,
  cable_length_m DECIMAL(10,2) NOT NULL DEFAULT 0,
  installation_charge DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  equipment_used TEXT,
  status ENUM('pending','in_progress','completed','delivered') NOT NULL DEFAULT 'pending',
  technician_notes TEXT,
  warranty_expiry DATE DEFAULT NULL,
  invoice_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cctv_customer (customer_id),
  CONSTRAINT fk_upg_cctv_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS repair_job_parts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repair_job_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_upg_rjp_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
  CONSTRAINT fk_upg_rjp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoices
  ADD CONSTRAINT fk_upg_inv_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_upg_inv_cctv FOREIGN KEY (cctv_job_id) REFERENCES cctv_installations(id) ON DELETE SET NULL;

ALTER TABLE repair_jobs
  ADD CONSTRAINT fk_upg_repair_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;

ALTER TABLE cctv_installations
  ADD CONSTRAINT fk_upg_cctv_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;

ALTER TABLE payments
  ADD CONSTRAINT fk_upg_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_upg_pay_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_upg_pay_cctv FOREIGN KEY (cctv_job_id) REFERENCES cctv_installations(id) ON DELETE RESTRICT;

SET FOREIGN_KEY_CHECKS = 1;
