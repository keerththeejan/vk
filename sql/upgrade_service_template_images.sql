-- Service template gallery images (run once on existing databases)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS service_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(512) NOT NULL,
  caption VARCHAR(255) DEFAULT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_service_images_svc (service_id, sort_order),
  CONSTRAINT fk_service_images_template_upg FOREIGN KEY (service_id) REFERENCES service_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
