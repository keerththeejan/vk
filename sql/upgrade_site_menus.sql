-- Public site navigation (admin-managed). Backup first.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS menus (
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

-- Default rows only if table is empty (run app once or insert manually if needed).
