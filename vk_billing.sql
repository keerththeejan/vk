-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 19, 2026 at 05:20 AM
-- Server version: 8.4.7
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vk_billing`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('system','customer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `customer_id` int UNSIGNED DEFAULT NULL,
  `current_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `uq_accounts_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cctv_installations`
--

DROP TABLE IF EXISTS `cctv_installations`;
CREATE TABLE IF NOT EXISTS `cctv_installations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int UNSIGNED NOT NULL,
  `location` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `num_cameras` int UNSIGNED NOT NULL DEFAULT '1',
  `cable_length_m` decimal(10,2) NOT NULL DEFAULT '0.00',
  `dvr_nvr_details` text COLLATE utf8mb4_unicode_ci,
  `installation_charge` decimal(14,2) NOT NULL DEFAULT '0.00',
  `equipment_used` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','in_progress','completed','delivered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `technician_notes` text COLLATE utf8mb4_unicode_ci,
  `warranty_expiry` date DEFAULT NULL,
  `invoice_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_number` (`job_number`),
  KEY `idx_cctv_customer` (`customer_id`),
  KEY `fk_cctv_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_autoresponder_rate`
--

DROP TABLE IF EXISTS `email_autoresponder_rate`;
CREATE TABLE IF NOT EXISTS `email_autoresponder_rate` (
  `sender_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sender_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_inbound`
--

DROP TABLE IF EXISTS `email_inbound`;
CREATE TABLE IF NOT EXISTS `email_inbound` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `imap_folder` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INBOX',
  `imap_uid` int UNSIGNED NOT NULL DEFAULT '0',
  `from_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subject` varchar(998) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body_text` mediumtext COLLATE utf8mb4_unicode_ci,
  `message_date` datetime DEFAULT NULL,
  `autoresponder_sent` tinyint(1) NOT NULL DEFAULT '0',
  `autoresponder_skip_reason` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mailbox_uid` (`imap_folder`,`imap_uid`),
  KEY `idx_from` (`from_email`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_outbound_queue`
--

DROP TABLE IF EXISTS `email_outbound_queue`;
CREATE TABLE IF NOT EXISTS `email_outbound_queue` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subject` varchar(998) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body_text` mediumtext COLLATE utf8mb4_unicode_ci,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','processing','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `max_attempts` tinyint UNSIGNED NOT NULL DEFAULT '5',
  `next_attempt_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_error` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_poll` (`status`,`next_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_send_log`
--

DROP TABLE IF EXISTS `email_send_log`;
CREATE TABLE IF NOT EXISTS `email_send_log` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `direction` enum('outbound','inbound_note') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outbound',
  `template_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subject` varchar(998) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body_preview` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('queued','sending','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `attempts` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `error_message` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`created_at`),
  KEY `idx_to` (`to_email`),
  KEY `idx_template` (`template_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_send_log`
--

INSERT INTO `email_send_log` (`id`, `direction`, `template_type`, `to_email`, `to_name`, `subject`, `body_preview`, `status`, `attempts`, `error_message`, `meta_json`, `created_at`, `sent_at`) VALUES
(1, 'outbound', 'mail_test', 'keerththeejan@gmail.com', '', 'VK Network — test email', 'This is a test message from your VK admin panel. Sent at 2026-04-12T06:40:28+00:00', 'failed', 3, 'SMTP Error: Could not authenticate.', NULL, '2026-04-12 06:40:28', NULL),
(2, 'outbound', 'mail_test', 'keerththeejan@gmail.com', '', 'VK Network — test email', 'This is a test message from your VK admin panel. Sent at 2026-04-12T06:57:15+00:00', 'sent', 1, NULL, NULL, '2026-04-12 06:57:15', '2026-04-12 06:57:19'),
(3, 'outbound', 'vehicle_registration', 'keerththeejan@gmail.com', 'user', 'Your VK Vehicle Booking Account Details', 'Hello user, Your account has been successfully created. Login Details: Email: keerththeejan@gmail.com Password: Js@X8FMnXr Login here: http://localhost/VK/vehicle/login.php Please change your password after first login. Thank you, VK Transport Service', 'sent', 1, NULL, NULL, '2026-04-12 07:18:13', '2026-04-12 07:18:18');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int UNSIGNED NOT NULL,
  `invoice_date` date NOT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(14,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('unpaid','partial','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `source` enum('manual','repair','cctv') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `repair_job_id` int UNSIGNED DEFAULT NULL,
  `cctv_job_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_invoices_date` (`invoice_date`),
  KEY `idx_invoices_customer` (`customer_id`),
  KEY `fk_invoices_repair` (`repair_job_id`),
  KEY `fk_invoices_cctv` (`cctv_job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int UNSIGNED NOT NULL,
  `item_type` enum('product','service') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'product',
  `product_id` int UNSIGNED DEFAULT NULL,
  `line_description` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_invoice_items_invoice` (`invoice_id`),
  KEY `idx_invoice_items_product` (`product_id`),
  CONSTRAINT `fk_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `account_ledger`
--

DROP TABLE IF EXISTS `account_ledger`;
CREATE TABLE IF NOT EXISTS `account_ledger` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_id` int UNSIGNED NOT NULL,
  `customer_id` int UNSIGNED DEFAULT NULL,
  `invoice_id` int UNSIGNED DEFAULT NULL,
  `payment_id` int UNSIGNED DEFAULT NULL,
  `transfer_id` int UNSIGNED DEFAULT NULL,
  `entry_type` enum('debit','credit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `debit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `description` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entry_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ledger_account` (`account_id`,`entry_datetime`),
  KEY `idx_ledger_customer` (`customer_id`,`created_at`),
  KEY `idx_ledger_invoice` (`invoice_id`),
  KEY `idx_ledger_payment` (`payment_id`),
  KEY `idx_ledger_type_date` (`entry_type`,`created_at`),
  CONSTRAINT `fk_ledger_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ledger_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ledger_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_contracts`
--

DROP TABLE IF EXISTS `maintenance_contracts`;
CREATE TABLE IF NOT EXISTS `maintenance_contracts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int UNSIGNED NOT NULL,
  `contract_type` enum('computer_amc','cctv_maintenance') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `visit_frequency` enum('monthly','quarterly','yearly','one_time') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yearly',
  `next_service_date` date DEFAULT NULL,
  `status` enum('active','paused','expired','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `cctv_installation_id` int UNSIGNED DEFAULT NULL,
  `annual_fee` decimal(14,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_number` (`contract_number`),
  KEY `idx_maint_customer` (`customer_id`),
  KEY `idx_maint_next` (`next_service_date`),
  KEY `idx_maint_status` (`status`),
  KEY `fk_maint_cctv` (`cctv_installation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_visits`
--

DROP TABLE IF EXISTS `maintenance_visits`;
CREATE TABLE IF NOT EXISTS `maintenance_visits` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `contract_id` int UNSIGNED NOT NULL,
  `visit_date` date NOT NULL,
  `technician_id` int UNSIGNED DEFAULT NULL,
  `work_performed` text COLLATE utf8mb4_unicode_ci,
  `checks_done` text COLLATE utf8mb4_unicode_ci,
  `charges` decimal(14,2) NOT NULL DEFAULT '0.00',
  `next_service_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mv_contract` (`contract_id`),
  KEY `fk_mv_technician` (`technician_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
CREATE TABLE IF NOT EXISTS `menus` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_menus_slug` (`slug`),
  KEY `idx_menus_status_sort` (`status`,`sort_order`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `name`, `slug`, `url`, `icon`, `sort_order`, `status`, `created_at`) VALUES
(1, 'Home', 'home', 'index.php', 'lucide:home', 10, 'active', '2026-04-12 09:33:33'),
(2, 'Book Service', 'book', 'book.php', 'lucide:calendar-plus', 20, 'active', '2026-04-12 09:33:33'),
(3, 'Vehicle Booking', 'vehicle', 'vehicle/index.php', 'lucide:car-front', 30, 'active', '2026-04-12 09:33:33'),
(4, 'Track Status', 'track', 'track.php', 'lucide:search', 40, 'active', '2026-04-12 09:33:33'),
(5, 'Our Work', 'portfolio', 'portfolio.php', 'lucide:images', 50, 'active', '2026-04-12 09:33:33');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int UNSIGNED DEFAULT NULL,
  `repair_job_id` int UNSIGNED DEFAULT NULL,
  `cctv_job_id` int UNSIGNED DEFAULT NULL,
  `customer_id` int UNSIGNED DEFAULT NULL,
  `customer_account_id` int UNSIGNED NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `method` enum('cash','card','bank','online') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `paid_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_repair` (`repair_job_id`),
  KEY `idx_payments_cctv` (`cctv_job_id`),
  KEY `idx_payments_customer` (`customer_id`),
  KEY `fk_payments_account` (`customer_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portfolio_posts`
--

DROP TABLE IF EXISTS `portfolio_posts`;
CREATE TABLE IF NOT EXISTS `portfolio_posts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_before` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_after` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_date` date NOT NULL,
  `published` tinyint(1) NOT NULL DEFAULT '1',
  `ref_type` enum('repair','cctv','booking','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `ref_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pf4_published_date` (`published`,`post_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `stock` int NOT NULL DEFAULT '0',
  `low_stock_threshold` int UNSIGNED NOT NULL DEFAULT '5',
  `category` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `repair_jobs`
--

DROP TABLE IF EXISTS `repair_jobs`;
CREATE TABLE IF NOT EXISTS `repair_jobs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int UNSIGNED NOT NULL,
  `device_type` enum('computer','printer','cctv_dvr','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `problem_description` text COLLATE utf8mb4_unicode_ci,
  `accessories_received` text COLLATE utf8mb4_unicode_ci,
  `technician_id` int UNSIGNED DEFAULT NULL,
  `printer_issue` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_template_id` int UNSIGNED DEFAULT NULL,
  `estimated_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','diagnosing','in_progress','completed','delivered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `technician_notes` text COLLATE utf8mb4_unicode_ci,
  `warranty_expiry` date DEFAULT NULL,
  `invoice_id` int UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `emergency_priority` tinyint(1) NOT NULL DEFAULT '0',
  `field_status` enum('assigned','on_way','in_progress','completed') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_number` (`job_number`),
  KEY `idx_repair_customer` (`customer_id`),
  KEY `idx_repair_status` (`status`),
  KEY `idx_repair_emergency` (`emergency_priority`),
  KEY `idx_repair_field` (`field_status`),
  KEY `fk_repair_invoice` (`invoice_id`),
  KEY `fk_repair_tech_v3` (`technician_id`),
  KEY `fk_repair_tpl_v3` (`service_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_gallery`
--

DROP TABLE IF EXISTS `service_gallery`;
CREATE TABLE IF NOT EXISTS `service_gallery` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` int UNSIGNED NOT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_gallery_service` (`service_id`,`id`),
  KEY `idx_service_gallery_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_gallery`
--

INSERT INTO `service_gallery` (`id`, `service_id`, `image_path`, `title`, `original_filename`, `created_at`) VALUES
(2, 1, 'uploads/services/gallery/service_1_g_1775550645048.webp', '114829--01--1623926499', NULL, '2026-04-07 08:30:45');

-- --------------------------------------------------------

--
-- Table structure for table `service_images`
--

DROP TABLE IF EXISTS `service_images`;
CREATE TABLE IF NOT EXISTS `service_images` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` int UNSIGNED NOT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_images_svc` (`service_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_templates`
--

DROP TABLE IF EXISTS `service_templates`;
CREATE TABLE IF NOT EXISTS `service_templates` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('printer','computer','cctv','general') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `default_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `description` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_thumb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_templates`
--

INSERT INTO `service_templates` (`id`, `name`, `category`, `default_amount`, `description`, `image`, `image_thumb`, `latitude`, `longitude`, `address`, `created_at`) VALUES
(1, 'Printer ??? Cartridge / toner service', 'printer', 2500.00, 'Cartridge check, cleaning, test print', NULL, NULL, NULL, NULL, NULL, '2026-04-12 07:53:44'),
(2, 'Printer ??? Paper jam recovery', 'printer', 1500.00, 'Jam clear, roller inspection', NULL, NULL, NULL, NULL, NULL, '2026-04-12 07:53:44'),
(3, 'Printer ??? Roller replacement', 'printer', 4500.00, 'Pickup roller / roller kit labour', NULL, NULL, NULL, NULL, NULL, '2026-04-12 07:53:44'),
(4, 'Printer ??? Ink refill', 'printer', 2000.00, 'Refill service', NULL, NULL, NULL, NULL, NULL, '2026-04-12 07:53:44'),
(5, 'Computer ??? Health check & cleaning', 'computer', 3500.00, 'Dust cleaning, thermal check, OS quick scan', NULL, NULL, NULL, NULL, NULL, '2026-04-12 07:53:44'),
(6, 'Computer ??? OS reinstall', 'computer', 5000.00, 'Backup advisory, OS install, drivers', NULL, NULL, NULL, NULL, NULL, '2026-04-12 07:53:44'),
(7, 'CCTV ??? Maintenance visit', 'cctv', 4000.00, 'Lens clean, cable check, recording test', NULL, NULL, NULL, NULL, NULL, '2026-04-12 07:53:44');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key_name`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `value`, `updated_at`) VALUES
(1, 'site_name', 'VK Network', NULL),
(2, 'seo_site_title', '', NULL),
(3, 'seo_meta_description', 'Professional computer, printer, CCTV, maintenance, and field repair services in Kilinochchi and across Sri Lanka — VK Network.', NULL),
(4, 'seo_meta_keywords', 'computer repair, laptop service, printer repair, CCTV installation, Sri Lanka, Kilinochchi, VK Network', NULL),
(5, 'seo_og_image', '', NULL),
(6, 'seo_auto_enabled', '1', NULL),
(7, 'seo_locations', 'jaffna,vavuniya,kilinochchi', NULL),
(8, 'seo_service_slugs', 'computer-repair,laptop-repair,printer-repair,it-service', NULL),
(9, 'whatsapp_number', '', NULL),
(10, 'whatsapp_default_message', 'Hello VK Network, I would like to inquire about your services.', NULL),
(11, 'analytics_domain', '', NULL),
(12, 'analytics_script_src', 'https://plausible.io/js/script.js', NULL),
(13, 'smtp_host', '', NULL),
(14, 'smtp_port', '587', NULL),
(15, 'smtp_username', '', NULL),
(16, 'smtp_password', '', NULL),
(17, 'email_from', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `skills` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experience` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_links` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_active_sort` (`active`,`sort_order`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `smtp_settings`
--

DROP TABLE IF EXISTS `smtp_settings`;
CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` tinyint UNSIGNED NOT NULL,
  `smtp_host` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `smtp_port` int UNSIGNED NOT NULL DEFAULT '587',
  `smtp_user` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `smtp_pass` text COLLATE utf8mb4_unicode_ci,
  `smtp_secure` enum('tls','ssl') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tls',
  `from_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `smtp_settings`
--

INSERT INTO `smtp_settings` (`id`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_secure`, `from_email`, `from_name`, `updated_at`) VALUES
(1, 'vkitnet.info', 465, 'info@vkitnet.info', '$#eZ9E6VUD,6', 'ssl', 'info@vkitnet.info', 'VK IT Network', '2026-04-12 06:57:13');

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

DROP TABLE IF EXISTS `technicians`;
CREATE TABLE IF NOT EXISTS `technicians` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialization` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `availability` enum('available','busy') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_technicians_active_geo` (`active`,`latitude`,`longitude`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `technicians`
--

INSERT INTO `technicians` (`id`, `name`, `phone`, `specialization`, `active`, `latitude`, `longitude`, `availability`, `created_at`) VALUES
(1, 'Lead Technician', '0778870135', 'All systems', 1, NULL, NULL, 'available', '2026-04-12 07:53:44');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullname` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','staff','technician') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `technician_id` int UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `fk_users_technician` (`technician_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `phone`, `password_hash`, `fullname`, `role`, `technician_id`, `status`, `created_at`) VALUES
(1, 'admin', NULL, NULL, '$2y$10$dLw60EEU/LS0xpHi4k0Qgu3f3VHIOQBf/.dg/y/vgMyKE9WM//VPa', 'Administrator', 'admin', NULL, 'active', '2026-04-12 07:52:42');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_type` enum('car','van','bike','lorry','bus') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'car',
  `registration_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_per_day` decimal(12,2) NOT NULL DEFAULT '0.00',
  `price_per_km` decimal(12,2) NOT NULL DEFAULT '0.00',
  `default_driver_charge` decimal(12,2) NOT NULL DEFAULT '0.00',
  `assigned_driver_id` int UNSIGNED DEFAULT NULL,
  `status` enum('available','booked','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seats` int UNSIGNED NOT NULL DEFAULT '4',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicles_reg` (`registration_number`),
  KEY `idx_vehicles_type_status` (`vehicle_type`,`status`),
  KEY `fk_vehicles_driver` (`assigned_driver_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `vehicle_name`, `vehicle_type`, `registration_number`, `price_per_day`, `price_per_km`, `default_driver_charge`, `assigned_driver_id`, `status`, `image_path`, `seats`, `created_at`, `updated_at`) VALUES
(1, 'Toyota Prius', 'car', 'CAA-4389', 12500.00, 180.00, 2500.00, 1, 'available', 'assets/images/services/automobile.svg', 4, '2026-04-08 10:09:28', NULL),
(2, 'Nissan Caravan', 'van', 'NCB-7721', 18500.00, 260.00, 3500.00, 2, 'available', 'assets/images/services/maintenance.svg', 12, '2026-04-08 10:09:28', NULL),
(3, 'Bajaj CT100', 'bike', 'BKE-1902', 3500.00, 65.00, 0.00, NULL, 'available', 'assets/images/services/default.svg', 2, '2026-04-08 10:09:28', NULL),
(4, 'Isuzu Lorry', 'lorry', 'LRY-5104', 26500.00, 420.00, 5500.00, 3, 'maintenance', 'assets/images/services/electrical.svg', 3, '2026-04-08 10:09:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_bookings`
--

DROP TABLE IF EXISTS `vehicle_bookings`;
CREATE TABLE IF NOT EXISTS `vehicle_bookings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_ref` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int UNSIGNED NOT NULL,
  `booking_type` enum('rental','hire') COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_id` int UNSIGNED DEFAULT NULL,
  `driver_id` int UNSIGNED DEFAULT NULL,
  `status` enum('pending','confirmed','ongoing','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `pickup_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_lat` decimal(10,7) DEFAULT NULL,
  `pickup_lng` decimal(10,7) DEFAULT NULL,
  `drop_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `drop_lat` decimal(10,7) DEFAULT NULL,
  `drop_lng` decimal(10,7) DEFAULT NULL,
  `pickup_at` datetime NOT NULL,
  `return_at` datetime DEFAULT NULL,
  `vehicle_type` enum('car','van','bike','lorry','bus') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'car',
  `passengers` int UNSIGNED NOT NULL DEFAULT '1',
  `distance_km` decimal(10,2) NOT NULL DEFAULT '0.00',
  `rental_days` int UNSIGNED NOT NULL DEFAULT '1',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `driver_charge` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `special_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_bookings_ref` (`booking_ref`),
  KEY `idx_vehicle_bookings_customer` (`customer_id`,`created_at`),
  KEY `idx_vehicle_bookings_status` (`status`),
  KEY `idx_vehicle_bookings_type` (`booking_type`),
  KEY `fk_vehicle_bookings_vehicle` (`vehicle_id`),
  KEY `fk_vehicle_bookings_driver` (`driver_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_bookings`
--

INSERT INTO `vehicle_bookings` (`id`, `booking_ref`, `customer_id`, `booking_type`, `vehicle_id`, `driver_id`, `status`, `pickup_location`, `pickup_lat`, `pickup_lng`, `drop_location`, `drop_lat`, `drop_lng`, `pickup_at`, `return_at`, `vehicle_type`, `passengers`, `distance_km`, `rental_days`, `unit_price`, `driver_charge`, `total_amount`, `special_notes`, `created_at`, `updated_at`) VALUES
(1, 'VB-SAMPLE-001', 1, 'rental', 1, NULL, 'confirmed', 'Kilinochchi Bus Stand', 9.3961000, 80.3982000, NULL, NULL, NULL, '2026-04-09 09:00:00', '2026-04-11 09:00:00', 'car', 4, 0.00, 2, 12500.00, 0.00, 25000.00, 'Sample rental booking', '2026-04-08 10:09:28', NULL),
(2, 'VB-SAMPLE-002', 1, 'hire', 2, 2, 'pending', 'Jaffna Town', 9.6615000, 80.0255000, 'Kilinochchi Central', 9.3803000, 80.3761000, '2026-04-10 08:30:00', '2026-04-10 18:30:00', 'van', 6, 62.50, 1, 260.00, 3500.00, 19750.00, 'Sample hire booking with driver', '2026-04-08 10:09:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_customers`
--

DROP TABLE IF EXISTS `vehicle_customers`;
CREATE TABLE IF NOT EXISTS `vehicle_customers` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_customers_email` (`email`),
  KEY `idx_vehicle_customers_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_customers`
--

INSERT INTO `vehicle_customers` (`id`, `full_name`, `phone`, `email`, `address`, `password_hash`, `created_at`) VALUES
(1, 'Demo Customer', '0777001122', 'demo@vkvehicle.local', NULL, '$2y$10$dpoLZqrNk79dZGsWKURaN.YfABuIgFLqkAY.y59kpOHxwqw3UkM7G', '2026-04-08 10:09:28'),
(2, 'Rasenthiram Pavuthira', '0798645352', 'emmentagrossist@gmail.com', 'Schwandgasse 16', '$2y$10$m7e6L1AST23R2ij/G3M2hui2N5lqXNDGQjakqdw8VWVtMo5D56.MK', '2026-04-08 10:14:58'),
(3, 'user', '0778870135', 'keerththeejan@gmail.com', 'Kilinochchi', '$2y$10$WJsDduyDkFhn7oIHcTE4iOWi1hjSOCnLustU7uu4N1JYKt.tuAcU6', '2026-04-12 07:18:13');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_drivers`
--

DROP TABLE IF EXISTS `vehicle_drivers`;
CREATE TABLE IF NOT EXISTS `vehicle_drivers` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `availability` enum('available','on_trip','off_duty') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_drivers_license` (`license_number`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicle_drivers`
--

INSERT INTO `vehicle_drivers` (`id`, `name`, `phone`, `license_number`, `availability`, `active`, `created_at`) VALUES
(1, 'K. Suresh', '0777123456', 'B4512268', 'available', 1, '2026-04-08 10:09:28'),
(2, 'T. Nimalan', '0777456789', 'B4528931', 'available', 1, '2026-04-08 10:09:28'),
(3, 'R. Arul', '0777987654', 'B4581022', 'off_duty', 1, '2026-04-08 10:09:28');

-- --------------------------------------------------------

--
-- Table structure for table `warranty_records`
--

DROP TABLE IF EXISTS `warranty_records`;
CREATE TABLE IF NOT EXISTS `warranty_records` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warranty_type` enum('service','product') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'service',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `repair_job_id` int UNSIGNED DEFAULT NULL,
  `cctv_installation_id` int UNSIGNED DEFAULT NULL,
  `invoice_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_warranty_customer` (`customer_id`),
  KEY `idx_warranty_end` (`end_date`),
  KEY `fk_warranty_repair_v3` (`repair_job_id`),
  KEY `fk_warranty_cctv_v3` (`cctv_installation_id`),
  KEY `fk_warranty_invoice_v3` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `web_bookings`
--

DROP TABLE IF EXISTS `web_bookings`;
CREATE TABLE IF NOT EXISTS `web_bookings` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `service_type` enum('computer','printer','cctv','maintenance','automobile','ac','electrical','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `problem_description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `preferred_date` date DEFAULT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_emergency` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','in_progress','completed','delivered','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `technician_notes` text COLLATE utf8mb4_unicode_ci,
  `estimated_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `assigned_technician_id` int UNSIGNED DEFAULT NULL,
  `assignment_distance_km` decimal(10,3) DEFAULT NULL,
  `repair_job_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  KEY `idx_web_booking_status` (`status`),
  KEY `idx_web_booking_emergency` (`is_emergency`),
  KEY `fk_web_booking_tech` (`assigned_technician_id`),
  KEY `fk_web_booking_repair` (`repair_job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `web_portfolio_images`
--

DROP TABLE IF EXISTS `web_portfolio_images`;
CREATE TABLE IF NOT EXISTS `web_portfolio_images` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` int UNSIGNED NOT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_role` enum('before','after','general') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_portfolio_img_post` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `web_portfolio_posts`
--

DROP TABLE IF EXISTS `web_portfolio_posts`;
CREATE TABLE IF NOT EXISTS `web_portfolio_posts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `display_date` date NOT NULL,
  `repair_job_id` int UNSIGNED DEFAULT NULL,
  `cctv_job_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_portfolio_pub` (`published`,`display_date`),
  KEY `fk_portfolio_repair` (`repair_job_id`),
  KEY `fk_portfolio_cctv` (`cctv_job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `web_services`
--

DROP TABLE IF EXISTS `web_services`;
CREATE TABLE IF NOT EXISTS `web_services` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_description` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `what_we_do` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `features_json` text COLLATE utf8mb4_unicode_ci,
  `benefits_text` text COLLATE utf8mb4_unicode_ci,
  `price_from` decimal(10,2) DEFAULT NULL,
  `price_note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lucide_icon` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'wrench',
  `sort_order` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_web_services_active` (`active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `web_service_images`
--

DROP TABLE IF EXISTS `web_service_images`;
CREATE TABLE IF NOT EXISTS `web_service_images` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` int UNSIGNED NOT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_web_svc_img_sort` (`service_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accounts`
--
ALTER TABLE `accounts`
  ADD CONSTRAINT `fk_accounts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `cctv_installations`
--
ALTER TABLE `cctv_installations`
  ADD CONSTRAINT `fk_cctv_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_cctv_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_cctv` FOREIGN KEY (`cctv_job_id`) REFERENCES `cctv_installations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_invoices_repair` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance_contracts`
--
ALTER TABLE `maintenance_contracts`
  ADD CONSTRAINT `fk_maint_cctv` FOREIGN KEY (`cctv_installation_id`) REFERENCES `cctv_installations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_maint_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `maintenance_visits`
--
ALTER TABLE `maintenance_visits`
  ADD CONSTRAINT `fk_mv_contract` FOREIGN KEY (`contract_id`) REFERENCES `maintenance_contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mv_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_account` FOREIGN KEY (`customer_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_payments_cctv` FOREIGN KEY (`cctv_job_id`) REFERENCES `cctv_installations` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_payments_repair` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `repair_jobs`
--
ALTER TABLE `repair_jobs`
  ADD CONSTRAINT `fk_repair_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_repair_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_repair_tech` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_repair_tech_v3` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_repair_tpl` FOREIGN KEY (`service_template_id`) REFERENCES `service_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_repair_tpl_v3` FOREIGN KEY (`service_template_id`) REFERENCES `service_templates` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `service_gallery`
--
ALTER TABLE `service_gallery`
  ADD CONSTRAINT `fk_service_gallery_service` FOREIGN KEY (`service_id`) REFERENCES `web_services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_images`
--
ALTER TABLE `service_images`
  ADD CONSTRAINT `fk_service_images_template` FOREIGN KEY (`service_id`) REFERENCES `service_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicles_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `vehicle_drivers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vehicle_bookings`
--
ALTER TABLE `vehicle_bookings`
  ADD CONSTRAINT `fk_vehicle_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `vehicle_customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vehicle_bookings_driver` FOREIGN KEY (`driver_id`) REFERENCES `vehicle_drivers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vehicle_bookings_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warranty_records`
--
ALTER TABLE `warranty_records`
  ADD CONSTRAINT `fk_warranty_cctv_v3` FOREIGN KEY (`cctv_installation_id`) REFERENCES `cctv_installations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_warranty_customer_v3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_warranty_invoice_v3` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_warranty_repair_v3` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `web_bookings`
--
ALTER TABLE `web_bookings`
  ADD CONSTRAINT `fk_web_booking_repair` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_web_booking_tech` FOREIGN KEY (`assigned_technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `web_portfolio_images`
--
ALTER TABLE `web_portfolio_images`
  ADD CONSTRAINT `fk_portfolio_img_post` FOREIGN KEY (`post_id`) REFERENCES `web_portfolio_posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `web_portfolio_posts`
--
ALTER TABLE `web_portfolio_posts`
  ADD CONSTRAINT `fk_portfolio_cctv` FOREIGN KEY (`cctv_job_id`) REFERENCES `cctv_installations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_portfolio_repair` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `web_service_images`
--
ALTER TABLE `web_service_images`
  ADD CONSTRAINT `fk_web_svc_img_service` FOREIGN KEY (`service_id`) REFERENCES `web_services` (`id`) ON DELETE CASCADE;

--
-- Data for table `accounts` (system account required for ledger)
--
INSERT INTO `accounts` (`code`, `name`, `current_balance`) VALUES
('SYS-MAIN', 'System / Cash Account', 0.00);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
