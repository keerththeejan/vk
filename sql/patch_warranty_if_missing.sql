-- If the v3 upgrade stopped early, only warranty_records may be missing.
-- Safe to run anytime (CREATE IF NOT EXISTS).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS warranty_records (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description VARCHAR(512) DEFAULT NULL,
  warranty_type ENUM('service','product') NOT NULL DEFAULT 'service',
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  notes TEXT,
  repair_job_id INT UNSIGNED DEFAULT NULL,
  cctv_installation_id INT UNSIGNED DEFAULT NULL,
  invoice_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_warranty_customer (customer_id),
  INDEX idx_warranty_end (end_date),
  CONSTRAINT fk_warranty_customer_patch FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_warranty_repair_patch FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL,
  CONSTRAINT fk_warranty_cctv_patch FOREIGN KEY (cctv_installation_id) REFERENCES cctv_installations(id) ON DELETE SET NULL,
  CONSTRAINT fk_warranty_invoice_patch FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
