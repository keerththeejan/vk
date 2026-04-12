-- VK IT Network — Service, Repair, Maintenance & Billing
-- CREATE DATABASE vk_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS account_ledger;
DROP TABLE IF EXISTS account_transfers;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS repair_job_parts;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS warranty_records;
DROP TABLE IF EXISTS maintenance_visits;
DROP TABLE IF EXISTS maintenance_contracts;
DROP TABLE IF EXISTS job_attachments;
DROP TABLE IF EXISTS web_portfolio_images;
DROP TABLE IF EXISTS web_portfolio_posts;
DROP TABLE IF EXISTS web_service_images;
DROP TABLE IF EXISTS web_services;
DROP TABLE IF EXISTS web_bookings;
DROP TABLE IF EXISTS repair_jobs;
DROP TABLE IF EXISTS cctv_installations;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS service_images;
DROP TABLE IF EXISTS service_templates;
DROP TABLE IF EXISTS technicians;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS menus;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customers_name (name),
  INDEX idx_customers_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  account_type ENUM('system','customer') NOT NULL DEFAULT 'customer',
  customer_id INT UNSIGNED DEFAULT NULL,
  current_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_accounts_customer (customer_id),
  CONSTRAINT fk_accounts_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE technicians (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  specialization VARCHAR(128) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  latitude DECIMAL(10,8) DEFAULT NULL,
  longitude DECIMAL(11,8) DEFAULT NULL,
  availability ENUM('available','busy') NOT NULL DEFAULT 'available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_technicians_active_geo (active, latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  email VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(32) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  fullname VARCHAR(128) DEFAULT NULL,
  role ENUM('admin','staff','technician') NOT NULL DEFAULT 'admin',
  technician_id INT UNSIGNED DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  CONSTRAINT fk_users_technician FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(128) NOT NULL,
  value TEXT,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_settings_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (key_name, value) VALUES
('site_name', 'VK Network'),
('seo_site_title', ''),
('seo_meta_description', 'Professional computer, printer, CCTV, maintenance, and field repair services in Kilinochchi and across Sri Lanka — VK Network.'),
('seo_meta_keywords', 'computer repair, laptop service, printer repair, CCTV installation, Sri Lanka, Kilinochchi, VK Network'),
('seo_og_image', ''),
('seo_auto_enabled', '1'),
('seo_locations', 'jaffna,vavuniya,kilinochchi'),
('seo_service_slugs', 'computer-repair,laptop-repair,printer-repair,it-service'),
('whatsapp_number', ''),
('whatsapp_default_message', 'Hello VK Network, I would like to inquire about your services.'),
('analytics_domain', ''),
('analytics_script_src', 'https://plausible.io/js/script.js'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('email_from', '');

CREATE TABLE menus (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  url VARCHAR(255) NOT NULL,
  icon VARCHAR(100) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_menus_slug (slug),
  INDEX idx_menus_status_sort (status, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO menus (name, slug, url, icon, sort_order, status) VALUES
('Home', 'home', 'index.php', 'lucide:home', 10, 'active'),
('Book Service', 'book', 'book.php', 'lucide:calendar-plus', 20, 'active'),
('Vehicle Booking', 'vehicle', 'vehicle/index.php', 'lucide:car-front', 30, 'active'),
('Track Status', 'track', 'track.php', 'lucide:search', 40, 'active'),
('Our Work', 'portfolio', 'portfolio.php', 'lucide:images', 50, 'active');

CREATE TABLE service_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  category ENUM('printer','computer','cctv','general') NOT NULL DEFAULT 'general',
  default_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  description VARCHAR(512) DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  image_thumb VARCHAR(255) DEFAULT NULL,
  latitude DECIMAL(10,8) DEFAULT NULL,
  longitude DECIMAL(11,8) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  caption VARCHAR(255) DEFAULT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_service_images_svc (service_id, sort_order),
  CONSTRAINT fk_service_images_template FOREIGN KEY (service_id) REFERENCES service_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  stock INT NOT NULL DEFAULT 0,
  low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 5,
  category VARCHAR(128) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_products_name (name),
  INDEX idx_products_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repair_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_number VARCHAR(64) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  device_type ENUM('computer','printer','cctv_dvr','automobile','ac','electrical','other') NOT NULL DEFAULT 'other',
  problem_description TEXT,
  accessories_received TEXT,
  technician_id INT UNSIGNED DEFAULT NULL,
  printer_issue VARCHAR(32) DEFAULT NULL,
  service_template_id INT UNSIGNED DEFAULT NULL,
  estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending','diagnosing','in_progress','completed','delivered') NOT NULL DEFAULT 'pending',
  technician_notes TEXT,
  warranty_expiry DATE DEFAULT NULL,
  invoice_id INT UNSIGNED DEFAULT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  emergency_priority TINYINT(1) NOT NULL DEFAULT 0,
  field_status ENUM('assigned','on_way','in_progress','completed') DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_repair_customer (customer_id),
  INDEX idx_repair_status (status),
  INDEX idx_repair_emergency (emergency_priority),
  INDEX idx_repair_field (field_status),
  CONSTRAINT fk_repair_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_repair_tech FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL,
  CONSTRAINT fk_repair_tpl FOREIGN KEY (service_template_id) REFERENCES service_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cctv_installations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_number VARCHAR(64) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  location TEXT NOT NULL,
  num_cameras INT UNSIGNED NOT NULL DEFAULT 1,
  cable_length_m DECIMAL(10,2) NOT NULL DEFAULT 0,
  dvr_nvr_details TEXT,
  installation_charge DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  equipment_used TEXT,
  status ENUM('pending','in_progress','completed','delivered') NOT NULL DEFAULT 'pending',
  technician_notes TEXT,
  warranty_expiry DATE DEFAULT NULL,
  invoice_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_cctv_customer (customer_id),
  CONSTRAINT fk_cctv_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance_contracts (
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
  CONSTRAINT fk_maint_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_maint_cctv FOREIGN KEY (cctv_installation_id) REFERENCES cctv_installations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE maintenance_visits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  visit_date DATE NOT NULL,
  technician_id INT UNSIGNED DEFAULT NULL,
  work_performed TEXT,
  checks_done TEXT,
  charges DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  next_service_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mv_contract FOREIGN KEY (contract_id) REFERENCES maintenance_contracts(id) ON DELETE CASCADE,
  CONSTRAINT fk_mv_technician FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(64) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NOT NULL,
  invoice_date DATE NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  tax DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  notes TEXT,
  source ENUM('manual','repair','cctv') NOT NULL DEFAULT 'manual',
  repair_job_id INT UNSIGNED DEFAULT NULL,
  cctv_job_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_invoices_date (invoice_date),
  INDEX idx_invoices_customer (customer_id),
  CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoices_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_cctv FOREIGN KEY (cctv_job_id) REFERENCES cctv_installations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE repair_jobs
  ADD CONSTRAINT fk_repair_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;

ALTER TABLE cctv_installations
  ADD CONSTRAINT fk_cctv_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;

CREATE TABLE web_bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_number VARCHAR(32) NOT NULL UNIQUE,
  customer_name VARCHAR(255) NOT NULL,
  phone VARCHAR(64) NOT NULL,
  email VARCHAR(255) DEFAULT NULL,
  address TEXT,
  service_type ENUM('computer','printer','cctv','maintenance','automobile','ac','electrical','other') NOT NULL DEFAULT 'other',
  problem_description TEXT NOT NULL,
  preferred_date DATE DEFAULT NULL,
  image_path VARCHAR(512) DEFAULT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  is_emergency TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending','in_progress','completed','delivered','cancelled') NOT NULL DEFAULT 'pending',
  technician_notes TEXT,
  estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  assigned_technician_id INT UNSIGNED DEFAULT NULL,
  assignment_distance_km DECIMAL(10,3) DEFAULT NULL,
  repair_job_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_web_booking_status (status),
  INDEX idx_web_booking_emergency (is_emergency),
  CONSTRAINT fk_web_booking_tech FOREIGN KEY (assigned_technician_id) REFERENCES technicians(id) ON DELETE SET NULL,
  CONSTRAINT fk_web_booking_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE web_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  short_description VARCHAR(512) NOT NULL,
  description TEXT NOT NULL,
  what_we_do TEXT NOT NULL,
  features_json TEXT,
  benefits_text TEXT,
  price_from DECIMAL(10,2) DEFAULT NULL,
  price_note VARCHAR(255) DEFAULT NULL,
  cover_image VARCHAR(512) DEFAULT NULL,
  lucide_icon VARCHAR(64) NOT NULL DEFAULT 'wrench',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_web_services_active (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE web_service_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  caption VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_web_svc_img_service FOREIGN KEY (service_id) REFERENCES web_services(id) ON DELETE CASCADE,
  INDEX idx_web_svc_img_sort (service_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_gallery (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  title VARCHAR(255) DEFAULT NULL,
  original_filename VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_service_gallery_service (service_id, id),
  INDEX idx_service_gallery_created (created_at),
  CONSTRAINT fk_service_gallery_service FOREIGN KEY (service_id) REFERENCES web_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE web_portfolio_posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  published TINYINT(1) NOT NULL DEFAULT 0,
  display_date DATE NOT NULL,
  repair_job_id INT UNSIGNED DEFAULT NULL,
  cctv_job_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_portfolio_pub (published, display_date),
  CONSTRAINT fk_portfolio_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL,
  CONSTRAINT fk_portfolio_cctv FOREIGN KEY (cctv_job_id) REFERENCES cctv_installations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE web_portfolio_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  caption VARCHAR(255) DEFAULT NULL,
  image_role ENUM('before','after','general') NOT NULL DEFAULT 'general',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_portfolio_img_post FOREIGN KEY (post_id) REFERENCES web_portfolio_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE job_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repair_job_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(512) NOT NULL,
  kind ENUM('before','after','other') NOT NULL DEFAULT 'other',
  uploaded_by_user_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_job_att_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
  CONSTRAINT fk_job_att_user FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warranty_records (
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
  CONSTRAINT fk_warranty_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_warranty_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE SET NULL,
  CONSTRAINT fk_warranty_cctv FOREIGN KEY (cctv_installation_id) REFERENCES cctv_installations(id) ON DELETE SET NULL,
  CONSTRAINT fk_warranty_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED NOT NULL,
  item_type ENUM('product','service') NOT NULL DEFAULT 'product',
  product_id INT UNSIGNED DEFAULT NULL,
  line_description VARCHAR(512) DEFAULT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE repair_job_parts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repair_job_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rjp_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE CASCADE,
  CONSTRAINT fk_rjp_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED DEFAULT NULL,
  repair_job_id INT UNSIGNED DEFAULT NULL,
  cctv_job_id INT UNSIGNED DEFAULT NULL,
  customer_account_id INT UNSIGNED NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  method ENUM('cash','card','bank','online') NOT NULL DEFAULT 'cash',
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) DEFAULT NULL,
  INDEX idx_payments_invoice (invoice_id),
  INDEX idx_payments_repair (repair_job_id),
  INDEX idx_payments_cctv (cctv_job_id),
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_repair FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_cctv FOREIGN KEY (cctv_job_id) REFERENCES cctv_installations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_payments_account FOREIGN KEY (customer_account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_transfers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  from_account_id INT UNSIGNED NOT NULL,
  to_account_id INT UNSIGNED NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  note VARCHAR(512) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_xfer_from FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_xfer_to FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE account_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id INT UNSIGNED NOT NULL,
  entry_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  debit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  credit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  balance DECIMAL(14,2) NOT NULL,
  description VARCHAR(512) DEFAULT NULL,
  invoice_id INT UNSIGNED DEFAULT NULL,
  payment_id INT UNSIGNED DEFAULT NULL,
  transfer_id INT UNSIGNED DEFAULT NULL,
  INDEX idx_ledger_account (account_id, entry_datetime),
  CONSTRAINT fk_ledger_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ledger_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
  CONSTRAINT fk_ledger_transfer FOREIGN KEY (transfer_id) REFERENCES account_transfers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO accounts (code, name, account_type, customer_id, current_balance) VALUES
('SYS-MAIN', 'VK IT Network — Main', 'system', NULL, 0.00);

INSERT INTO technicians (name, phone, specialization, active) VALUES
('Lead Technician', '0778870135', 'All systems', 1),
('Field Engineer', NULL, 'CCTV & networking', 1);

INSERT INTO users (username, password_hash, fullname, role, technician_id) VALUES
('admin', '$2y$10$dLw60EEU/LS0xpHi4k0Qgu3f3VHIOQBf/.dg/y/vgMyKE9WM//VPa', 'Administrator', 'admin', NULL),
('tech', '$2y$10$dLw60EEU/LS0xpHi4k0Qgu3f3VHIOQBf/.dg/y/vgMyKE9WM//VPa', 'Field Technician', 'technician', 1);

INSERT INTO service_templates (name, category, default_amount, description) VALUES
('Printer — Cartridge / toner service', 'printer', 2500.00, 'Cartridge check, cleaning, test print'),
('Printer — Paper jam recovery', 'printer', 1500.00, 'Jam clear, roller inspection'),
('Printer — Roller replacement', 'printer', 4500.00, 'Pickup roller / roller kit labour'),
('Printer — Ink refill', 'printer', 2000.00, 'Refill service'),
('Computer — Health check & cleaning', 'computer', 3500.00, 'Dust cleaning, thermal check, OS quick scan'),
('Computer — OS reinstall', 'computer', 5000.00, 'Backup advisory, OS install, drivers'),
('CCTV — Maintenance visit', 'cctv', 4000.00, 'Lens clean, cable check, recording test');

INSERT INTO products (name, price, stock, low_stock_threshold, category) VALUES
('HDD 1TB', 8500.00, 20, 3, 'Storage'),
('DDR4 8GB RAM', 6200.00, 15, 3, 'Memory'),
('CCTV Camera 2MP', 4500.00, 40, 5, 'CCTV'),
('DVR 8CH', 18500.00, 8, 2, 'CCTV'),
('SMPS 12V 5A', 1200.00, 25, 5, 'Power'),
('Printer pickup roller kit', 3200.00, 12, 4, 'Printer parts'),
('Laptop Service Labour', 3500.00, 999, 1, 'Labour');

INSERT INTO web_services (id, slug, name, short_description, description, what_we_do, features_json, benefits_text, price_from, price_note, cover_image, lucide_icon, sort_order, active) VALUES
(1, 'computer', 'Computer repair', 'Laptops, desktops, OS, upgrades, virus cleanup.', 'Professional computer repair for homes and businesses. We fix hardware faults, slow systems, and software issues with clear turnaround times.', 'We diagnose power and board issues, replace failing storage and memory, reinstall or tune operating systems, remove malware, and recover data when possible. Workshop or on-site service depending on the job.', '[{"icon":"laptop","text":"Laptop screen, keyboard, battery & charging repairs"},{"icon":"cpu","text":"Desktop upgrades, cleaning, thermal & stability testing"},{"icon":"shield-check","text":"Virus removal, security basics & backup guidance"}]', 'Transparent estimates before major parts\nWarranty-friendly documentation\nParts sourcing for upgrades', 2500.00, 'Diagnostic visit from LKR 2,500 — final price after assessment', 'assets/images/services/svc-computer.svg', 'laptop', 1, 1),
(2, 'printer', 'Printer service', 'Cartridges, jams, rollers, refills, office printers.', 'Keep your office printing reliably. We service inkjet and laser printers, fix jams, replace worn rollers, and handle refills.', 'We clean paper paths, replace pickup rollers, service cartridges and toners, align print quality, and advise when a printer is beyond economical repair.', '[{"icon":"printer","text":"Laser & inkjet service"},{"icon":"refresh-cw","text":"Cartridge refill & toner support"},{"icon":"wrench","text":"Jam recovery, rollers & mechanical fixes"}]', 'Test prints shown before you pay\nSpare parts where available\nAMC options for busy offices', 1500.00, 'Basic service from LKR 1,500 — parts quoted separately', 'assets/images/services/svc-printer.svg', 'printer', 2, 1),
(3, 'cctv', 'CCTV installation', 'Cameras, DVR/NVR, cabling, remote viewing setup.', 'Design and install CCTV systems that are easy to use and maintain. Ideal for shops, offices, warehouses, and homes.', 'We survey camera positions, run structured cabling where needed, mount cameras, configure DVR/NVR recording, and set up phone or PC viewing.', '[{"icon":"video","text":"HD / IP camera installation"},{"icon":"cable","text":"Cabling, power & neat trunking"},{"icon":"smartphone","text":"Remote viewing on mobile & PC"}]', 'Neat cable routing\nTraining on playback & export\nMaintenance visits available', 8500.00, 'Site survey & starter packages from LKR 8,500', 'assets/images/services/svc-cctv.svg', 'video', 3, 1),
(4, 'maintenance', 'Maintenance', 'AMC, scheduled visits, health checks.', 'Planned maintenance reduces downtime. We offer scheduled visits for IT gear, printers, CCTV, and mixed environments.', 'We agree a visit schedule, run health checks, clean and test equipment, update logs, and flag issues before they become outages.', '[{"icon":"calendar-check","text":"Scheduled AMC visits"},{"icon":"clipboard-list","text":"Checklists & service reports"},{"icon":"headphones","text":"Priority support for contract customers"}]', 'Predictable yearly costs\nFewer emergency call-outs\nPriority booking during peaks', 4000.00, 'AMC plans from LKR 4,000 / year (varies by scope)', 'assets/images/services/svc-maintenance.svg', 'wrench', 4, 1),
(5, 'automobile', 'Automobile breakdown', 'Roadside-style support with emergency priority.', 'When your vehicle lets you down, we prioritise breakdown requests and aim to get you moving or towed safely.', 'We attend common roadside faults where possible, coordinate battery and minor on-site fixes, and escalate to workshop partners for major repairs.', '[{"icon":"car-front","text":"Breakdown & roadside assistance"},{"icon":"zap","text":"Battery & electrical checks"},{"icon":"alert-circle","text":"Emergency flag for fastest dispatch"}]', 'Emergency option for urgent cases\nClear communication on ETA\nLinks to trusted garages when needed', 3500.00, 'Call-out from LKR 3,500 — labour & parts extra', 'assets/images/services/svc-automobile.svg', 'car-front', 5, 1),
(6, 'ac', 'AC repair', 'Split & window units, gas, cleaning, faults.', 'Comfortable cooling with proper gas handling and cleaning. We service split and window air conditioners.', 'We deep-clean filters and coils, check drainage, test compressor and fan health, and handle safe refrigerant top-ups where appropriate.', '[{"icon":"wind","text":"Gas refill & leak checks (where applicable)"},{"icon":"droplets","text":"Drain & mould prevention cleaning"},{"icon":"thermometer","text":"Cooling performance testing"}]', 'Seasonal service reminders\nHonest advice on replace vs repair\nSafer handling of refrigerants', 3000.00, 'General service from LKR 3,000', 'assets/images/services/svc-ac.svg', 'snowflake', 6, 1),
(7, 'electrical', 'Electrical (DC)', 'DC wiring, solar/aux circuits, safe installs.', 'Low-voltage DC wiring for solar auxiliaries, CCTV power runs, and safe installs that follow best practice.', 'We plan cable sizes and protection, terminate professionally, label circuits, and test load conditions for CCTV, lighting, and auxiliary DC systems.', '[{"icon":"zap","text":"DC distribution & fusing"},{"icon":"sun","text":"Solar / battery circuit support"},{"icon":"shield","text":"Neat routing & safety checks"}]', 'Compliance-minded workmanship\nClear diagrams on request\nWorks with your installer plan', 4000.00, 'Site assessment from LKR 4,000', 'assets/images/services/svc-electrical.svg', 'zap', 7, 1);

INSERT INTO web_service_images (service_id, image_path, caption, sort_order) VALUES
(1, 'assets/images/services/svc-computer.svg', 'Laptop repair & diagnostics', 0),
(1, 'assets/images/services/svc-computer.svg', 'Motherboard & upgrade work', 1),
(1, 'assets/images/services/svc-computer.svg', 'Workshop cleaning & assembly', 2),
(2, 'assets/images/services/svc-printer.svg', 'Cartridge & toner service', 0),
(2, 'assets/images/services/svc-printer.svg', 'Printer repair & jam fixes', 1),
(2, 'assets/images/services/svc-printer.svg', 'Office printer maintenance', 2),
(3, 'assets/images/services/svc-cctv.svg', 'Camera mounting & alignment', 0),
(3, 'assets/images/services/svc-cctv.svg', 'DVR / NVR setup', 1),
(3, 'assets/images/services/svc-cctv.svg', 'Cable routing & power', 2),
(4, 'assets/images/services/svc-maintenance.svg', 'Scheduled health checks', 0),
(4, 'assets/images/services/svc-maintenance.svg', 'AMC visit documentation', 1),
(5, 'assets/images/services/svc-automobile.svg', 'Roadside assistance', 0),
(5, 'assets/images/services/svc-automobile.svg', 'Breakdown assessment', 1),
(5, 'assets/images/services/svc-automobile.svg', 'Battery & electrical', 2),
(6, 'assets/images/services/svc-ac.svg', 'AC cleaning & service', 0),
(6, 'assets/images/services/svc-ac.svg', 'Gas & performance check', 1),
(6, 'assets/images/services/svc-ac.svg', 'Split unit maintenance', 2),
(7, 'assets/images/services/svc-electrical.svg', 'DC wiring work', 0),
(7, 'assets/images/services/svc-electrical.svg', 'Safe terminations & routing', 1),
(7, 'assets/images/services/svc-electrical.svg', 'Auxiliary & CCTV power', 2);

ALTER TABLE web_services AUTO_INCREMENT = 8;
