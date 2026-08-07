-- Product warranty schema for enterprise product management
CREATE TABLE IF NOT EXISTS `product_warranties` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `warranty_enabled` TINYINT(1) DEFAULT 0,
  `warranty_type` VARCHAR(100) DEFAULT NULL,
  `warranty_period` INT DEFAULT 0,
  `warranty_period_type` VARCHAR(20) DEFAULT 'months',
  `warranty_start_date` DATE DEFAULT NULL,
  `warranty_expiry_date` DATE DEFAULT NULL,
  `warranty_coverage` TEXT DEFAULT NULL,
  `warranty_terms` LONGTEXT DEFAULT NULL,
  `warranty_claim_process` TEXT DEFAULT NULL,
  `warranty_document` VARCHAR(255) DEFAULT NULL,
  `warranty_status` VARCHAR(50) DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
);

-- add has_warranty flag to products
ALTER TABLE `products` ADD COLUMN IF NOT EXISTS `has_warranty` TINYINT(1) DEFAULT 0;
