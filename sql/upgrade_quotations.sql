-- VK Network ERP — Quotation Management (manual upgrade)
-- Prefer runtime migration via includes/quotations_schema.php
-- Usage: mysql -u root -p vk_billing < sql/upgrade_quotations.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS quotation_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  slug VARCHAR(128) NOT NULL,
  description VARCHAR(512) DEFAULT NULL,
  color VARCHAR(32) DEFAULT '#0B4DBA',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_qcat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  description TEXT,
  payment_terms VARCHAR(255) DEFAULT NULL,
  delivery_terms VARCHAR(255) DEFAULT NULL,
  validity_days INT UNSIGNED NOT NULL DEFAULT 30,
  tax_method ENUM('exclusive','inclusive','none') NOT NULL DEFAULT 'exclusive',
  default_tax_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  terms_html MEDIUMTEXT,
  notes TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_terms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(191) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_number VARCHAR(64) NOT NULL,
  revision_no INT UNSIGNED NOT NULL DEFAULT 0,
  parent_quotation_id INT UNSIGNED DEFAULT NULL,
  customer_id INT UNSIGNED NOT NULL,
  company_name VARCHAR(255) DEFAULT NULL,
  contact_person VARCHAR(191) DEFAULT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  billing_address TEXT,
  shipping_address TEXT,
  currency VARCHAR(8) NOT NULL DEFAULT 'LKR',
  sales_executive_id INT UNSIGNED DEFAULT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  template_id INT UNSIGNED DEFAULT NULL,
  reference_number VARCHAR(128) DEFAULT NULL,
  quotation_date DATE NOT NULL,
  expiry_date DATE DEFAULT NULL,
  payment_terms VARCHAR(255) DEFAULT NULL,
  delivery_terms VARCHAR(255) DEFAULT NULL,
  validity_days INT UNSIGNED DEFAULT 30,
  tax_method ENUM('exclusive','inclusive','none') NOT NULL DEFAULT 'exclusive',
  status ENUM('draft','pending_approval','approved','rejected','cancelled','expired','accepted','converted_so','converted_invoice') NOT NULL DEFAULT 'draft',
  approval_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  approval_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  item_discount_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  overall_discount_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  overall_discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  tax_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  shipping_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  additional_charges DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  round_off DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  estimated_cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  net_profit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  profit_margin_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  notes TEXT,
  internal_notes TEXT,
  terms_html MEDIUMTEXT,
  customer_response TEXT,
  expected_closing_date DATE DEFAULT NULL,
  converted_invoice_id INT UNSIGNED DEFAULT NULL,
  converted_at DATETIME DEFAULT NULL,
  branch VARCHAR(128) DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_quotations_number (quotation_number),
  INDEX idx_quotations_customer (customer_id),
  INDEX idx_quotations_status (status),
  INDEX idx_quotations_date (quotation_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  item_type ENUM('product','service','custom') NOT NULL DEFAULT 'product',
  product_id INT UNSIGNED DEFAULT NULL,
  product_code VARCHAR(128) DEFAULT NULL,
  barcode VARCHAR(128) DEFAULT NULL,
  product_name VARCHAR(255) NOT NULL,
  category_name VARCHAR(128) DEFAULT NULL,
  description TEXT,
  unit VARCHAR(32) DEFAULT 'pcs',
  quantity DECIMAL(14,3) NOT NULL DEFAULT 1.000,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  cost_price DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  discount_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  tax_pct DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  image_path VARCHAR(512) DEFAULT NULL,
  INDEX idx_qitems_quotation (quotation_id, sort_order),
  CONSTRAINT fk_qitems_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_approvals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  role_label VARCHAR(64) NOT NULL,
  approver_id INT UNSIGNED DEFAULT NULL,
  action ENUM('pending','approved','rejected','skipped') NOT NULL DEFAULT 'pending',
  notes TEXT,
  acted_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qappr_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_revisions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  revision_no INT UNSIGNED NOT NULL,
  snapshot_json LONGTEXT NOT NULL,
  change_summary VARCHAR(512) DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_qrev (quotation_id, revision_no),
  CONSTRAINT fk_qrev_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_followups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  reminder_date DATE NOT NULL,
  reminder_status ENUM('pending','done','missed','cancelled') NOT NULL DEFAULT 'pending',
  followup_notes TEXT,
  customer_response TEXT,
  expected_closing_date DATE DEFAULT NULL,
  assigned_to INT UNSIGNED DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_qfu_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  note_type ENUM('general','internal','customer','system') NOT NULL DEFAULT 'general',
  body TEXT NOT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qnotes_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(512) NOT NULL,
  mime_type VARCHAR(128) DEFAULT NULL,
  file_size INT UNSIGNED DEFAULT 0,
  uploaded_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qatt_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED DEFAULT NULL,
  user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(64) NOT NULL,
  details TEXT,
  ip_address VARCHAR(64) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_qlog_quotation (quotation_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_email_tracking (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT UNSIGNED NOT NULL,
  tracking_token VARCHAR(64) NOT NULL,
  recipient_email VARCHAR(255) NOT NULL,
  sent_at DATETIME DEFAULT NULL,
  delivered_at DATETIME DEFAULT NULL,
  opened_at DATETIME DEFAULT NULL,
  download_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_download_at DATETIME DEFAULT NULL,
  status ENUM('queued','sent','failed','bounced') NOT NULL DEFAULT 'queued',
  error_message VARCHAR(512) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_qemail_token (tracking_token),
  CONSTRAINT fk_qemail_quotation FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotation_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(128) NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_qset_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
