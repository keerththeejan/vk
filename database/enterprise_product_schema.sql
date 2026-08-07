-- =====================================================
-- ENTERPRISE PRODUCT MANAGEMENT DATABASE SCHEMA
-- Premium ERP + Inventory + Retail + Warehouse System
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =====================================================
-- CORE PRODUCT TABLES
-- =====================================================

-- Products main table
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sku` VARCHAR(100) NULL,
  `barcode` VARCHAR(100) NULL,
  `qr_code` VARCHAR(255) NULL,
  `name` VARCHAR(255) NOT NULL,
  `subtitle` VARCHAR(255) NULL,
  `slug` VARCHAR(255) NULL,
  `product_type` ENUM('simple', 'configurable', 'bundle', 'grouped', 'virtual', 'downloadable') DEFAULT 'simple',
  `brand_id` INT UNSIGNED NULL,
  `category_id` INT UNSIGNED NULL,
  `subcategory_id` INT UNSIGNED NULL,
  `supplier_id` INT UNSIGNED NULL,
  `manufacturer_id` INT UNSIGNED NULL,
  `unit_type` ENUM('piece', 'kg', 'gram', 'liter', 'ml', 'meter', 'box', 'pack', 'set') DEFAULT 'piece',
  `hsn_sac_code` VARCHAR(20) NULL,
  `country_of_origin` VARCHAR(100) NULL,
  `short_description` TEXT NULL,
  `description` LONGTEXT NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_description` TEXT NULL,
  `meta_keywords` TEXT NULL,
  `og_image` VARCHAR(255) NULL,
  `canonical_url` VARCHAR(255) NULL,
  `seo_url` VARCHAR(255) NULL,
  `status` ENUM('draft', 'active', 'inactive', 'archived') DEFAULT 'draft',
  `featured` TINYINT(1) DEFAULT 0,
  `is_digital` TINYINT(1) DEFAULT 0,
  `requires_shipping` TINYINT(1) DEFAULT 1,
  `tax_class_id` INT UNSIGNED NULL,
  `sort_order` INT DEFAULT 0,
  `views` INT UNSIGNED DEFAULT 0,
  `created_by` INT UNSIGNED NULL,
  `updated_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sku` (`sku`),
  UNIQUE KEY `unique_barcode` (`barcode`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `idx_brand` (`brand_id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`),
  KEY `idx_product_type` (`product_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product tags
CREATE TABLE IF NOT EXISTS `product_tags` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `tag_name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_tag` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product pricing
CREATE TABLE IF NOT EXISTS `product_pricing` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `cost_price` DECIMAL(15,2) DEFAULT 0.00,
  `selling_price` DECIMAL(15,2) DEFAULT 0.00,
  `wholesale_price` DECIMAL(15,2) DEFAULT 0.00,
  `dealer_price` DECIMAL(15,2) DEFAULT 0.00,
  `distributor_price` DECIMAL(15,2) DEFAULT 0.00,
  `msrp` DECIMAL(15,2) DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
  `vat_gst` DECIMAL(5,2) DEFAULT 0.00,
  `profit_margin` DECIMAL(5,2) DEFAULT 0.00,
  `discount_type` ENUM('none', 'fixed', 'percentage') DEFAULT 'none',
  `discount_value` DECIMAL(15,2) DEFAULT 0.00,
  `promotional_price` DECIMAL(15,2) DEFAULT 0.00,
  `promo_start_date` DATE NULL,
  `promo_end_date` DATE NULL,
  `price_valid_from` DATE NULL,
  `price_valid_to` DATE NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_pricing` (`product_id`),
  KEY `idx_currency` (`currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INVENTORY MANAGEMENT
-- =====================================================

-- Product inventory
CREATE TABLE IF NOT EXISTS `product_inventory` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NULL,
  `opening_stock` INT DEFAULT 0,
  `current_stock` INT DEFAULT 0,
  `minimum_stock` INT DEFAULT 0,
  `reorder_level` INT DEFAULT 0,
  `rack_location` VARCHAR(100) NULL,
  `bin_number` VARCHAR(50) NULL,
  `batch_number` VARCHAR(100) NULL,
  `serial_number` VARCHAR(100) NULL,
  `expiry_date` DATE NULL,
  `manufacturing_date` DATE NULL,
  `last_stock_update` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_warehouse` (`product_id`, `warehouse_id`),
  KEY `idx_warehouse` (`warehouse_id`),
  KEY `idx_current_stock` (`current_stock`),
  KEY `idx_expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stock movement logs
CREATE TABLE IF NOT EXISTS `stock_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `warehouse_id` INT UNSIGNED NULL,
  `movement_type` ENUM('in', 'out', 'transfer', 'adjustment', 'return', 'damage') NOT NULL,
  `quantity` INT NOT NULL,
  `previous_stock` INT NOT NULL,
  `new_stock` INT NOT NULL,
  `reference_type` VARCHAR(50) NULL,
  `reference_id` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_movement_type` (`movement_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warehouses
CREATE TABLE IF NOT EXISTS `warehouses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `address` TEXT NULL,
  `city` VARCHAR(100) NULL,
  `state` VARCHAR(100) NULL,
  `country` VARCHAR(100) NULL,
  `postal_code` VARCHAR(20) NULL,
  `contact_person` VARCHAR(100) NULL,
  `contact_phone` VARCHAR(50) NULL,
  `contact_email` VARCHAR(100) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- WARRANTY MANAGEMENT (PREMIUM SYSTEM)
-- =====================================================

-- Product warranty
CREATE TABLE IF NOT EXISTS `product_warranty` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `warranty_enabled` TINYINT(1) DEFAULT 0,
  `warranty_type` ENUM('manufacturer', 'seller', 'replacement', 'extended', 'amc') DEFAULT 'manufacturer',
  `warranty_duration` INT UNSIGNED DEFAULT 0,
  `warranty_unit` ENUM('days', 'months', 'years') DEFAULT 'months',
  `warranty_start_date` DATE NULL,
  `warranty_end_date` DATE NULL,
  `warranty_coverage` TEXT NULL,
  `warranty_terms` LONGTEXT NULL,
  `claim_procedure` TEXT NULL,
  `warranty_provider` VARCHAR(255) NULL,
  `service_center_name` VARCHAR(255) NULL,
  `service_center_address` TEXT NULL,
  `service_center_phone` VARCHAR(50) NULL,
  `service_center_email` VARCHAR(100) NULL,
  `replacement_policy` TEXT NULL,
  `amc_support` TINYINT(1) DEFAULT 0,
  `amc_duration` INT UNSIGNED DEFAULT 0,
  `extended_warranty_available` TINYINT(1) DEFAULT 0,
  `extended_warranty_max_months` INT UNSIGNED DEFAULT 0,
  `warranty_document_path` VARCHAR(255) NULL,
  `warranty_qr_code` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_warranty` (`product_id`),
  KEY `idx_warranty_enabled` (`warranty_enabled`),
  KEY `idx_warranty_end_date` (`warranty_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warranty claims
CREATE TABLE IF NOT EXISTS `warranty_claims` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `warranty_id` INT UNSIGNED NOT NULL,
  `claim_number` VARCHAR(100) NOT NULL,
  `customer_name` VARCHAR(255) NOT NULL,
  `customer_email` VARCHAR(100) NULL,
  `customer_phone` VARCHAR(50) NULL,
  `serial_number` VARCHAR(100) NULL,
  `purchase_date` DATE NULL,
  `claim_date` DATE NOT NULL,
  `claim_type` ENUM('repair', 'replacement', 'refund', 'exchange') DEFAULT 'repair',
  `issue_description` TEXT NULL,
  `claim_status` ENUM('pending', 'approved', 'rejected', 'in_progress', 'completed', 'closed') DEFAULT 'pending',
  `resolution_notes` TEXT NULL,
  `resolved_date` DATE NULL,
  `assigned_to` INT UNSIGNED NULL,
  `document_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_claim_number` (`claim_number`),
  KEY `idx_product` (`product_id`),
  KEY `idx_status` (`claim_status`),
  KEY `idx_claim_date` (`claim_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warranty claim timeline
CREATE TABLE IF NOT EXISTS `warranty_claim_timeline` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `claim_id` BIGINT UNSIGNED NOT NULL,
  `status_from` VARCHAR(50) NULL,
  `status_to` VARCHAR(50) NOT NULL,
  `notes` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_claim` (`claim_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PRODUCT VARIANTS
-- =====================================================

-- Product variants
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `variant_sku` VARCHAR(100) NULL,
  `variant_name` VARCHAR(255) NULL,
  `variant_color` VARCHAR(100) NULL,
  `variant_size` VARCHAR(100) NULL,
  `variant_material` VARCHAR(100) NULL,
  `variant_storage` VARCHAR(100) NULL,
  `variant_attributes` JSON NULL,
  `cost_price` DECIMAL(15,2) DEFAULT 0.00,
  `selling_price` DECIMAL(15,2) DEFAULT 0.00,
  `stock_quantity` INT DEFAULT 0,
  `weight` DECIMAL(10,2) NULL,
  `is_default` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_variant_sku` (`variant_sku`),
  KEY `idx_product` (`product_id`),
  KEY `idx_color` (`variant_color`),
  KEY `idx_size` (`variant_size`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Variant images
CREATE TABLE IF NOT EXISTS `variant_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `variant_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_thumbnail` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MEDIA MANAGEMENT
-- =====================================================

-- Product images
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `thumbnail_path` VARCHAR(255) NULL,
  `alt_text` VARCHAR(255) NULL,
  `is_thumbnail` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `image_type` ENUM('main', 'gallery', 'thumbnail', '360') DEFAULT 'gallery',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_is_thumbnail` (`is_thumbnail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product videos
CREATE TABLE IF NOT EXISTS `product_videos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `video_path` VARCHAR(255) NOT NULL,
  `video_type` ENUM('upload', 'youtube', 'vimeo') DEFAULT 'upload',
  `thumbnail_path` VARCHAR(255) NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product documents
CREATE TABLE IF NOT EXISTS `product_documents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `document_path` VARCHAR(255) NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `document_type` ENUM('manual', 'specification', 'warranty', 'certificate', 'other') DEFAULT 'other',
  `file_size` BIGINT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SHIPPING & DIMENSIONS
-- =====================================================

-- Product shipping
CREATE TABLE IF NOT EXISTS `product_shipping` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `weight` DECIMAL(10,2) DEFAULT 0.00,
  `weight_unit` ENUM('kg', 'g', 'lb', 'oz') DEFAULT 'kg',
  `length` DECIMAL(10,2) NULL,
  `width` DECIMAL(10,2) NULL,
  `height` DECIMAL(10,2) NULL,
  `dimension_unit` ENUM('cm', 'm', 'in', 'ft') DEFAULT 'cm',
  `shipping_class_id` INT UNSIGNED NULL,
  `is_fragile` TINYINT(1) DEFAULT 0,
  `packaging_type` ENUM('box', 'envelope', 'crate', 'pallet', 'custom') DEFAULT 'box',
  `requires_special_handling` TINYINT(1) DEFAULT 0,
  `shipping_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_shipping` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SUPPLIERS & MANUFACTURERS
-- =====================================================

-- Suppliers
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `contact_person` VARCHAR(100) NULL,
  `email` VARCHAR(100) NULL,
  `phone` VARCHAR(50) NULL,
  `address` TEXT NULL,
  `city` VARCHAR(100) NULL,
  `state` VARCHAR(100) NULL,
  `country` VARCHAR(100) NULL,
  `postal_code` VARCHAR(20) NULL,
  `tax_id` VARCHAR(50) NULL,
  `payment_terms` VARCHAR(100) NULL,
  `lead_time_days` INT UNSIGNED DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `rating` DECIMAL(3,2) DEFAULT 0.00,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manufacturers
CREATE TABLE IF NOT EXISTS `manufacturers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `contact_person` VARCHAR(100) NULL,
  `email` VARCHAR(100) NULL,
  `phone` VARCHAR(50) NULL,
  `website` VARCHAR(255) NULL,
  `address` TEXT NULL,
  `city` VARCHAR(100) NULL,
  `state` VARCHAR(100) NULL,
  `country` VARCHAR(100) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- BRANDS & CATEGORIES
-- =====================================================

-- Brands
CREATE TABLE IF NOT EXISTS `brands` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NULL,
  `logo` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `website` VARCHAR(255) NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `icon` VARCHAR(50) NULL,
  `meta_title` VARCHAR(255) NULL,
  `meta_description` TEXT NULL,
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slug` (`slug`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ACCESS CONTROL & APPROVALS
-- =====================================================

-- Product approvals
CREATE TABLE IF NOT EXISTS `product_approvals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `approval_status` ENUM('pending', 'approved', 'rejected', 'changes_requested') DEFAULT 'pending',
  `submitted_by` INT UNSIGNED NULL,
  `approved_by` INT UNSIGNED NULL,
  `submitted_at` TIMESTAMP NULL,
  `approved_at` TIMESTAMP NULL,
  `rejection_reason` TEXT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_approval` (`product_id`),
  KEY `idx_status` (`approval_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product activity logs
CREATE TABLE IF NOT EXISTS `product_activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `field_name` VARCHAR(100) NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `user_id` INT UNSIGNED NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Product drafts (auto-save)
CREATE TABLE IF NOT EXISTS `product_drafts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NULL,
  `draft_data` JSON NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- TAX CLASSES
-- =====================================================

-- Tax classes
CREATE TABLE IF NOT EXISTS `tax_classes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `rate` DECIMAL(5,2) DEFAULT 0.00,
  `tax_type` ENUM('vat', 'gst', 'sales_tax', 'service_tax', 'other') DEFAULT 'vat',
  `description` TEXT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SHIPPING CLASSES
-- =====================================================

-- Shipping classes
CREATE TABLE IF NOT EXISTS `shipping_classes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INDEXES AND CONSTRAINTS
-- =====================================================

-- Add foreign key constraints
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_subcategory` FOREIGN KEY (`subcategory_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_manufacturer` FOREIGN KEY (`manufacturer_id`) REFERENCES `manufacturers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_products_tax_class` FOREIGN KEY (`tax_class_id`) REFERENCES `tax_classes` (`id`) ON DELETE SET NULL;

ALTER TABLE `product_tags`
  ADD CONSTRAINT `fk_tags_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_pricing`
  ADD CONSTRAINT `fk_pricing_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_inventory`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_inventory_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL;

ALTER TABLE `stock_logs`
  ADD CONSTRAINT `fk_logs_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_logs_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL;

ALTER TABLE `product_warranty`
  ADD CONSTRAINT `fk_warranty_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `warranty_claims`
  ADD CONSTRAINT `fk_claims_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_claims_warranty` FOREIGN KEY (`warranty_id`) REFERENCES `product_warranty` (`id`) ON DELETE CASCADE;

ALTER TABLE `warranty_claim_timeline`
  ADD CONSTRAINT `fk_timeline_claim` FOREIGN KEY (`claim_id`) REFERENCES `warranty_claims` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_variants`
  ADD CONSTRAINT `fk_variants_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `variant_images`
  ADD CONSTRAINT `fk_variant_images_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_videos`
  ADD CONSTRAINT `fk_videos_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_documents`
  ADD CONSTRAINT `fk_documents_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_shipping`
  ADD CONSTRAINT `fk_shipping_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shipping_class` FOREIGN KEY (`shipping_class_id`) REFERENCES `shipping_classes` (`id`) ON DELETE SET NULL;

ALTER TABLE `categories`
  ADD CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

ALTER TABLE `product_approvals`
  ADD CONSTRAINT `fk_approvals_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_activity_logs`
  ADD CONSTRAINT `fk_logs_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

ALTER TABLE `product_drafts`
  ADD CONSTRAINT `fk_drafts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

COMMIT;
