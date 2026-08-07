-- Service detail gallery uploads
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS service_gallery (
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
