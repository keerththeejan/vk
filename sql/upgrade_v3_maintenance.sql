-- VK Billing upgrade v2 → v3 (maintenance, warranties, technicians, repair/CCTV extensions)
-- Backup first. Run after upgrade_v2_service.sql on existing databases.
-- If a statement fails with "Duplicate column", skip that line and continue.
-- Partial installs: run `mysql --force ... < upgrade_v3_maintenance.sql` so later statements
-- (e.g. warranty_records) still apply. Or import only sql/patch_warranty_if_missing.sql if that table alone is missing.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS technicians (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  specialization VARCHAR(128) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category ENUM('printer','computer','cctv','general') NOT NULL DEFAULT 'general',
  default_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  description VARCHAR(512) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO technicians (name, phone, specialization, active)
SELECT 'Lead Technician', '0778870135', 'All systems', 1
WHERE NOT EXISTS (SELECT 1 FROM technicians LIMIT 1);

INSERT INTO service_templates (name, category, default_amount, description)
SELECT 'Printer — Cartridge / toner service', 'printer', 2500.00, 'Cartridge check, cleaning, test print'
WHERE NOT EXISTS (SELECT 1 FROM service_templates WHERE name = 'Printer — Cartridge / toner service' LIMIT 1);

INSERT INTO service_templates (name, category, default_amount, description)
SELECT 'Printer — Paper jam recovery', 'printer', 1500.00, 'Jam clear, roller inspection'
WHERE NOT EXISTS (SELECT 1 FROM service_templates WHERE name = 'Printer — Paper jam recovery' LIMIT 1);

INSERT INTO service_templates (name, category, default_amount, description)
SELECT 'Printer — Roller replacement', 'printer', 4500.00, 'Pickup roller / roller kit labour'
WHERE NOT EXISTS (SELECT 1 FROM service_templates WHERE name = 'Printer — Roller replacement' LIMIT 1);

INSERT INTO service_templates (name, category, default_amount, description)
SELECT 'Printer — Ink refill', 'printer', 2000.00, 'Refill service'
WHERE NOT EXISTS (SELECT 1 FROM service_templates WHERE name = 'Printer — Ink refill' LIMIT 1);

INSERT INTO service_templates (name, category, default_amount, description)
SELECT 'Computer — Health check & cleaning', 'computer', 3500.00, 'Dust cleaning, thermal check, OS quick scan'
WHERE NOT EXISTS (SELECT 1 FROM service_templates WHERE name = 'Computer — Health check & cleaning' LIMIT 1);

INSERT INTO service_templates (name, category, default_amount, description)
SELECT 'Computer — OS reinstall', 'computer', 5000.00, 'Backup advisory, OS install, drivers'
WHERE NOT EXISTS (SELECT 1 FROM service_templates WHERE name = 'Computer — OS reinstall' LIMIT 1);

INSERT INTO service_templates (name, category, default_amount, description)
SELECT 'CCTV — Maintenance visit', 'cctv', 4000.00, 'Lens clean, cable check, recording test'
WHERE NOT EXISTS (SELECT 1 FROM service_templates WHERE name = 'CCTV — Maintenance visit' LIMIT 1);

ALTER TABLE products ADD COLUMN low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5 AFTER stock;

ALTER TABLE repair_jobs MODIFY COLUMN device_type VARCHAR(32) NOT NULL DEFAULT 'other';
UPDATE repair_jobs SET device_type = 'computer' WHERE device_type IN ('laptop','desktop');
UPDATE repair_jobs SET device_type = 'cctv_dvr' WHERE device_type IN ('cctv','dvr');
UPDATE repair_jobs SET device_type = 'other' WHERE device_type NOT IN ('computer','printer','cctv_dvr','other');
ALTER TABLE repair_jobs MODIFY COLUMN device_type ENUM('computer','printer','cctv_dvr','other') NOT NULL DEFAULT 'other';

ALTER TABLE repair_jobs MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending';
ALTER TABLE repair_jobs MODIFY COLUMN status ENUM('pending','diagnosing','in_progress','completed','delivered') NOT NULL DEFAULT 'pending';

ALTER TABLE repair_jobs ADD COLUMN technician_id INT UNSIGNED NULL AFTER accessories_received;
ALTER TABLE repair_jobs ADD COLUMN printer_issue VARCHAR(32) NULL AFTER technician_id;
ALTER TABLE repair_jobs ADD COLUMN service_template_id INT UNSIGNED NULL AFTER printer_issue;

ALTER TABLE repair_jobs
  ADD CONSTRAINT fk_repair_tech_v3 FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_repair_tpl_v3 FOREIGN KEY (service_template_id) REFERENCES service_templates(id) ON DELETE SET NULL;

ALTER TABLE cctv_installations ADD COLUMN dvr_nvr_details TEXT NULL AFTER cable_length_m;

CREATE TABLE IF NOT EXISTS maintenance_contracts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_number VARCHAR(64) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  contract_type ENUM('computer_amc','cctv_maintenance') NOT NULL,
  title VARCHAR(255) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE DEFAULT NULL,
  visit_frequency ENUM('monthly','quarterly','yearly','one_time') NOT NULL DEFAULT 'yearly',
  next_service_date DATE DEFAULT NULL,
  status ENUM('active','paused','expired','cancelled') NOT NULL DEFAULT 'active',
  cctv_installation_id INT UNSIGNED DEFAULT NULL,
  annual_fee DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_maint_customer (customer_id),
  INDEX idx_maint_next (next_service_date),
  INDEX idx_maint_status (status),
  CONSTRAINT fk_maint_customer_v3 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_maint_cctv_v3 FOREIGN KEY (cctv_installation_id) REFERENCES cctv_installations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS maintenance_visits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  visit_date DATE NOT NULL,
  technician_id INT UNSIGNED DEFAULT NULL,
  work_performed TEXT,
  checks_done TEXT,
  charges DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  next_service_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mv_contract_v3 FOREIGN KEY (contract_id) REFERENCES maintenance_contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_mv_technician_v3 FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  CONSTRAINT fk_warranty_customer_v3 FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_warranty_repair_v3 FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL,
  CONSTRAINT fk_warranty_cctv_v3 FOREIGN KEY (cctv_installation_id) REFERENCES cctv_installations(id) ON DELETE SET NULL,
  CONSTRAINT fk_warranty_invoice_v3 FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
