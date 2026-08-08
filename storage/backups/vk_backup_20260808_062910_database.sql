-- MySQL dump 10.13  Distrib 8.4.7, for Win64 (x86_64)
--
-- Host: localhost    Database: vk_billing
-- ------------------------------------------------------
-- Server version	8.4.7

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `account_ledger`
--

DROP TABLE IF EXISTS `account_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_ledger` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int unsigned NOT NULL,
  `customer_id` int unsigned DEFAULT NULL,
  `invoice_id` int unsigned DEFAULT NULL,
  `payment_id` int unsigned DEFAULT NULL,
  `transfer_id` int unsigned DEFAULT NULL,
  `entry_type` enum('debit','credit') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `debit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `description` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_ledger`
--

LOCK TABLES `account_ledger` WRITE;
/*!40000 ALTER TABLE `account_ledger` DISABLE KEYS */;
INSERT INTO `account_ledger` VALUES (2,1,1,7,NULL,NULL,'debit',1000.00,1000.00,0.00,2000.00,'Invoice INV-20260429-0002 — amount due','2026-04-29 12:41:38','2026-04-29 07:11:38'),(3,1,1,7,1,NULL,'credit',900.00,0.00,900.00,1100.00,'Invoice INV-20260429-0002 — payment (cash)','2026-04-29 12:41:38','2026-04-29 07:11:38'),(4,2,NULL,7,1,NULL,'debit',900.00,900.00,0.00,900.00,'Receipt — invoice INV-20260429-0002 (cash)','2026-04-29 12:41:38','2026-04-29 07:11:38'),(5,4,3,8,NULL,NULL,'debit',1500.00,1500.00,0.00,1500.00,'Invoice INV-20260705-0001 — amount due','2026-07-05 12:39:21','2026-07-05 07:09:21'),(6,4,3,NULL,2,NULL,'credit',1500.00,0.00,1500.00,0.00,'Payment for invoice INV-20260705-0001 (cash)','2026-07-05 14:43:17','2026-07-05 09:13:17'),(7,2,NULL,NULL,2,NULL,'debit',1500.00,1500.00,0.00,2400.00,'Receipt — invoice INV-20260705-0001 (cash)','2026-07-05 14:43:17','2026-07-05 09:13:17'),(8,4,3,9,NULL,NULL,'debit',620.00,620.00,0.00,620.00,'Invoice INV-20260717-0001 — amount due','2026-07-17 14:34:11','2026-07-17 09:04:11'),(9,4,3,9,3,NULL,'credit',620.00,0.00,620.00,0.00,'Invoice INV-20260717-0001 — payment (cash)','2026-07-17 14:34:11','2026-07-17 09:04:11'),(10,2,NULL,9,3,NULL,'debit',620.00,620.00,0.00,3020.00,'Receipt — invoice INV-20260717-0001 (cash)','2026-07-17 14:34:11','2026-07-17 09:04:11'),(11,5,4,10,NULL,NULL,'debit',235000.00,235000.00,0.00,235000.00,'Invoice INV-20260807-0001 — amount due','2026-08-07 13:23:44','2026-08-07 07:53:44'),(12,5,4,10,4,NULL,'credit',100000.00,0.00,100000.00,135000.00,'Invoice INV-20260807-0001 — payment (cash)','2026-08-07 13:23:44','2026-08-07 07:53:44'),(13,2,NULL,10,4,NULL,'debit',100000.00,100000.00,0.00,103020.00,'Receipt — invoice INV-20260807-0001 (cash)','2026-08-07 13:23:44','2026-08-07 07:53:44'),(14,5,4,10,NULL,NULL,'credit',35000.00,0.00,35000.00,100000.00,'Invoice INV-20260807-0001 — adjustment (decrease)','2026-08-07 13:52:20','2026-08-07 08:22:20');
/*!40000 ALTER TABLE `account_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_type` enum('system','customer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
  `customer_id` int unsigned DEFAULT NULL,
  `current_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `uq_accounts_customer` (`customer_id`),
  CONSTRAINT `fk_accounts_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
INSERT INTO `accounts` VALUES (1,'CUS-00001','vijaykumar keerththeejan — Account','customer',1,1100.00,'2026-04-29 06:11:26'),(2,'SYS-MAIN','System / Cash Account','customer',NULL,103020.00,'2026-04-29 07:11:03'),(3,'CUS-00002','vijaykumar keerththeejan — Account','customer',2,0.00,'2026-05-10 08:50:53'),(4,'CUS-00003','SLGTI — Account','customer',3,0.00,'2026-07-05 07:08:25'),(5,'CUS-00004','TS Transport — Account','customer',4,100000.00,'2026-08-06 08:58:09'),(6,'CUS-00005','vijaykumar keerththeejan — Account','customer',5,0.00,'2026-08-08 06:21:50');
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `actor_id` int unsigned DEFAULT NULL,
  `action` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_actor` (`actor_id`,`created_at`),
  KEY `idx_activity_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,2,NULL,'user_registered','user',2,'127.0.0.1','{\"email\": \"codex.test.1778406104912@example.com\", \"department\": \"Network Operations\"}','2026-05-10 09:41:52'),(2,1,1,'login_success','user',1,'::1',NULL,'2026-05-10 09:44:22'),(4,1,1,'login_success','user',1,'::1',NULL,'2026-05-11 05:30:10'),(5,1,1,'login_success','user',1,'::1',NULL,'2026-05-11 06:07:07'),(6,1,1,'login_success','user',1,'::1',NULL,'2026-05-17 03:36:01'),(7,1,1,'login_success','user',1,'::1',NULL,'2026-05-17 07:14:27'),(8,1,1,'login_success','user',1,'::1',NULL,'2026-06-14 03:57:47'),(9,1,1,'login_success','user',1,'::1',NULL,'2026-06-14 05:52:02'),(10,1,1,'login_success','user',1,'::1',NULL,'2026-06-26 10:09:45'),(11,1,1,'login_success','user',1,'::1',NULL,'2026-06-27 04:38:16'),(12,1,1,'login_success','user',1,'::1',NULL,'2026-06-28 03:52:50'),(13,1,1,'login_success','user',1,'::1',NULL,'2026-07-03 04:31:26'),(14,1,1,'login_success','user',1,'::1',NULL,'2026-07-05 07:07:54'),(15,1,1,'login_success','user',1,'::1',NULL,'2026-07-08 15:00:51'),(16,1,1,'login_success','user',1,'::1',NULL,'2026-07-17 07:54:06'),(17,1,1,'login_success','user',1,'::1',NULL,'2026-08-06 07:48:36'),(18,1,1,'login_success','user',1,'::1',NULL,'2026-08-06 10:07:27'),(19,1,1,'login_success','user',1,'::1',NULL,'2026-08-07 06:38:08'),(20,1,1,'login_success','user',1,'::1',NULL,'2026-08-08 05:32:12');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `approvals`
--

DROP TABLE IF EXISTS `approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `action` enum('registered','approved','rejected','suspended','reactivated','role_changed','password_reset') COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_id` int unsigned DEFAULT NULL,
  `from_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_role` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_role` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_approvals_user` (`user_id`,`created_at`),
  KEY `idx_approvals_actor` (`actor_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `approvals`
--

LOCK TABLES `approvals` WRITE;
/*!40000 ALTER TABLE `approvals` DISABLE KEYS */;
INSERT INTO `approvals` VALUES (1,2,'registered',NULL,NULL,'pending',NULL,'viewer','Self-service registration','2026-05-10 09:41:52');
/*!40000 ALTER TABLE `approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campaign_reports`
--

DROP TABLE IF EXISTS `campaign_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `campaign_id` int NOT NULL,
  `report_period` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `impressions` int unsigned NOT NULL DEFAULT '0',
  `clicks` int unsigned NOT NULL DEFAULT '0',
  `opens` int unsigned NOT NULL DEFAULT '0',
  `leads` int unsigned NOT NULL DEFAULT '0',
  `conversions` int unsigned NOT NULL DEFAULT '0',
  `revenue` decimal(12,2) NOT NULL DEFAULT '0.00',
  `report_json` json DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_reports_campaign` (`campaign_id`),
  CONSTRAINT `fk_reports_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_reports`
--

LOCK TABLES `campaign_reports` WRITE;
/*!40000 ALTER TABLE `campaign_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `campaign_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cctv_installations`
--

DROP TABLE IF EXISTS `cctv_installations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cctv_installations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `job_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `location` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `num_cameras` int unsigned NOT NULL DEFAULT '1',
  `cable_length_m` decimal(10,2) NOT NULL DEFAULT '0.00',
  `dvr_nvr_details` text COLLATE utf8mb4_unicode_ci,
  `installation_charge` decimal(14,2) NOT NULL DEFAULT '0.00',
  `equipment_used` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','in_progress','completed','delivered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `technician_notes` text COLLATE utf8mb4_unicode_ci,
  `warranty_expiry` date DEFAULT NULL,
  `invoice_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_number` (`job_number`),
  KEY `idx_cctv_customer` (`customer_id`),
  KEY `fk_cctv_invoice` (`invoice_id`),
  CONSTRAINT `fk_cctv_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cctv_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cctv_installations`
--

LOCK TABLES `cctv_installations` WRITE;
/*!40000 ALTER TABLE `cctv_installations` DISABLE KEYS */;
/*!40000 ALTER TABLE `cctv_installations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'vijaykumar keerththeejan',NULL,NULL,'Kilinochchi','2026-04-29 06:11:26',NULL),(2,'vijaykumar keerththeejan','+94778870135','keerththeejan@gmail.com','Kilinochchi','2026-05-10 08:50:53',NULL),(3,'SLGTI',NULL,NULL,NULL,'2026-07-05 07:08:25',NULL),(4,'TS Transport','077 247 4128',NULL,'9CX6+J76 TS Transport service, Kilinochchi','2026-08-06 08:58:09',NULL),(5,'vijaykumar keerththeejan','+94778870135','keerththeejan@gmail.com','Kilinochchi','2026-08-08 06:21:50',NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_autoresponder_rate`
--

DROP TABLE IF EXISTS `email_autoresponder_rate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_autoresponder_rate` (
  `sender_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sender_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_autoresponder_rate`
--

LOCK TABLES `email_autoresponder_rate` WRITE;
/*!40000 ALTER TABLE `email_autoresponder_rate` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_autoresponder_rate` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_inbound`
--

DROP TABLE IF EXISTS `email_inbound`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_inbound` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `imap_folder` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INBOX',
  `imap_uid` int unsigned NOT NULL DEFAULT '0',
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_inbound`
--

LOCK TABLES `email_inbound` WRITE;
/*!40000 ALTER TABLE `email_inbound` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_inbound` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `recipient` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template` varchar(96) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('sent','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_user` (`user_id`,`created_at`),
  KEY `idx_email_logs_status` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_outbound_queue`
--

DROP TABLE IF EXISTS `email_outbound_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_outbound_queue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `template_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subject` varchar(998) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body_text` mediumtext COLLATE utf8mb4_unicode_ci,
  `body_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','processing','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `max_attempts` tinyint unsigned NOT NULL DEFAULT '5',
  `next_attempt_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_error` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_poll` (`status`,`next_attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_outbound_queue`
--

LOCK TABLES `email_outbound_queue` WRITE;
/*!40000 ALTER TABLE `email_outbound_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_outbound_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_send_log`
--

DROP TABLE IF EXISTS `email_send_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_send_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` enum('outbound','inbound_note') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outbound',
  `template_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subject` varchar(998) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body_preview` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('queued','sending','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent',
  `attempts` tinyint unsigned NOT NULL DEFAULT '1',
  `error_message` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`created_at`),
  KEY `idx_to` (`to_email`),
  KEY `idx_template` (`template_type`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_send_log`
--

LOCK TABLES `email_send_log` WRITE;
/*!40000 ALTER TABLE `email_send_log` DISABLE KEYS */;
INSERT INTO `email_send_log` VALUES (1,'outbound','mail_test','keerththeejan@gmail.com','','VK Network — test email','This is a test message from your VK admin panel. Sent at 2026-04-12T06:40:28+00:00','failed',3,'SMTP Error: Could not authenticate.',NULL,'2026-04-12 06:40:28',NULL),(2,'outbound','mail_test','keerththeejan@gmail.com','','VK Network — test email','This is a test message from your VK admin panel. Sent at 2026-04-12T06:57:15+00:00','sent',1,NULL,NULL,'2026-04-12 06:57:15','2026-04-12 06:57:19'),(3,'outbound','vehicle_registration','keerththeejan@gmail.com','user','Your VK Vehicle Booking Account Details','Hello user, Your account has been successfully created. Login Details: Email: keerththeejan@gmail.com Password: Js@X8FMnXr Login here: http://localhost/VK/vehicle/login.php Please change your password after first login. Thank you, VK Transport Service','sent',1,NULL,NULL,'2026-04-12 07:18:13','2026-04-12 07:18:18'),(4,'outbound','registration_admin','info@vkitnet.info','VK IT','New User Registration Pending Approval','New user registration pending approval Full Name: Auth Fix Test User Email: authfix.1778407396595@example.com Username: auth.fix.test.user.4764 Department: Support Desk Registration Time: 2026-05-10 10:03:19 Approval URL: /vk/approve_users.php','failed',3,'SMTP Error: Could not authenticate.',NULL,'2026-05-10 10:03:19',NULL);
/*!40000 ALTER TABLE `email_send_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_history`
--

DROP TABLE IF EXISTS `invoice_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `field_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `edited_by` int unsigned DEFAULT NULL,
  `edited_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revision_no` int unsigned NOT NULL DEFAULT '0',
  `reason` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inv_hist_invoice` (`invoice_id`,`edited_at`),
  KEY `idx_inv_hist_rev` (`invoice_id`,`revision_no`),
  CONSTRAINT `fk_inv_hist_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_history`
--

LOCK TABLES `invoice_history` WRITE;
/*!40000 ALTER TABLE `invoice_history` DISABLE KEYS */;
INSERT INTO `invoice_history` VALUES (1,10,'subtotal','235000.00','235000',1,'2026-08-07 13:52:20','::1',1,NULL),(2,10,'discount','0.00','0',1,'2026-08-07 13:52:20','::1',1,NULL),(3,10,'tax','0.00','0',1,'2026-08-07 13:52:20','::1',1,NULL),(4,10,'grand_total','235000.00','200000',1,'2026-08-07 13:52:20','::1',1,NULL),(5,10,'item_discount_total','0.00','35000',1,'2026-08-07 13:52:20','::1',1,NULL),(6,10,'invoice_discount_value','0.00','0',1,'2026-08-07 13:52:20','::1',1,NULL),(7,10,'invoice_discount_amount','0.00','0',1,'2026-08-07 13:52:20','::1',1,NULL),(8,10,'shipping_amount','0.00','0',1,'2026-08-07 13:52:20','::1',1,NULL),(9,10,'adjustment_amount','0.00','0',1,'2026-08-07 13:52:20','::1',1,NULL),(10,10,'round_off','0.00','0',1,'2026-08-07 13:52:20','::1',1,NULL),(11,10,'line_items','[{\"product_id\":null,\"qty\":1,\"price\":\"35000.00\",\"disc\":\"0.00\",\"total\":\"35000.00\"},{\"product_id\":null,\"qty\":1,\"price\":\"200000.00\",\"disc\":\"0.00\",\"total\":\"200000.00\"}]','[{\"product_id\":null,\"qty\":1,\"price\":35000,\"disc\":35000,\"total\":0},{\"product_id\":null,\"qty\":1,\"price\":200000,\"disc\":0,\"total\":200000}]',1,'2026-08-07 13:52:20','::1',1,NULL),(12,10,'subtotal','235000.00','235000',1,'2026-08-07 13:53:28','::1',2,NULL),(13,10,'discount','0.00','0',1,'2026-08-07 13:53:28','::1',2,NULL),(14,10,'tax','0.00','0',1,'2026-08-07 13:53:28','::1',2,NULL),(15,10,'grand_total','200000.00','200000',1,'2026-08-07 13:53:28','::1',2,NULL),(16,10,'item_discount_total','35000.00','35000',1,'2026-08-07 13:53:28','::1',2,NULL),(17,10,'invoice_discount_value','0.00','0',1,'2026-08-07 13:53:28','::1',2,NULL),(18,10,'invoice_discount_amount','0.00','0',1,'2026-08-07 13:53:28','::1',2,NULL),(19,10,'shipping_amount','0.00','0',1,'2026-08-07 13:53:28','::1',2,NULL),(20,10,'adjustment_amount','0.00','0',1,'2026-08-07 13:53:28','::1',2,NULL),(21,10,'round_off','0.00','0',1,'2026-08-07 13:53:28','::1',2,NULL),(22,10,'line_items','[{\"product_id\":null,\"qty\":1,\"price\":\"35000.00\",\"disc\":\"35000.00\",\"total\":\"0.00\"},{\"product_id\":null,\"qty\":1,\"price\":\"200000.00\",\"disc\":\"0.00\",\"total\":\"200000.00\"}]','[{\"product_id\":null,\"qty\":1,\"price\":35000,\"disc\":35000,\"total\":0},{\"product_id\":null,\"qty\":1,\"price\":200000,\"disc\":0,\"total\":200000}]',1,'2026-08-07 13:53:28','::1',2,NULL),(23,10,'subtotal','235000.00','235000',1,'2026-08-07 14:44:56','::1',3,NULL),(24,10,'discount','0.00','0',1,'2026-08-07 14:44:56','::1',3,NULL),(25,10,'tax','0.00','0',1,'2026-08-07 14:44:56','::1',3,NULL),(26,10,'grand_total','200000.00','200000',1,'2026-08-07 14:44:56','::1',3,NULL),(27,10,'item_discount_total','35000.00','35000',1,'2026-08-07 14:44:56','::1',3,NULL),(28,10,'invoice_discount_value','0.00','0',1,'2026-08-07 14:44:56','::1',3,NULL),(29,10,'invoice_discount_amount','0.00','0',1,'2026-08-07 14:44:56','::1',3,NULL),(30,10,'shipping_amount','0.00','0',1,'2026-08-07 14:44:56','::1',3,NULL),(31,10,'adjustment_amount','0.00','0',1,'2026-08-07 14:44:56','::1',3,NULL),(32,10,'round_off','0.00','0',1,'2026-08-07 14:44:56','::1',3,NULL),(33,10,'line_items','[{\"product_id\":null,\"qty\":1,\"price\":\"35000.00\",\"disc\":\"35000.00\",\"total\":\"0.00\"},{\"product_id\":null,\"qty\":1,\"price\":\"200000.00\",\"disc\":\"0.00\",\"total\":\"200000.00\"}]','[{\"product_id\":null,\"qty\":1,\"price\":35000,\"disc\":35000,\"total\":0},{\"product_id\":null,\"qty\":1,\"price\":200000,\"disc\":0,\"total\":200000}]',1,'2026-08-07 14:44:56','::1',3,NULL);
/*!40000 ALTER TABLE `invoice_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `item_type` enum('product','service') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'product',
  `product_id` int unsigned DEFAULT NULL,
  `item_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line_description` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pcs',
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount_type` enum('percent','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'percent',
  `discount_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `sort_order` int NOT NULL DEFAULT '0',
  `cost_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_invoice_items_invoice` (`invoice_id`),
  KEY `idx_invoice_items_product` (`product_id`),
  CONSTRAINT `fk_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
INSERT INTO `invoice_items` VALUES (2,7,'service',NULL,NULL,'cctv','pcs',1,1000.00,'percent',0.00,0.00,0.00,0.00,0.00,0.00,1000.00,0,0.00),(3,8,'service',NULL,NULL,'ID CARD PRINT','pcs',3,400.00,'percent',0.00,0.00,0.00,0.00,0.00,0.00,1200.00,0,0.00),(4,8,'service',NULL,NULL,'ID CARD COVER','pcs',3,100.00,'percent',0.00,0.00,0.00,0.00,0.00,0.00,300.00,0,0.00),(5,9,'service',NULL,NULL,'MAX 485TTL AA119','pcs',1,620.00,'percent',0.00,0.00,0.00,0.00,0.00,0.00,620.00,0,0.00),(12,10,'service',NULL,'SVC','Server Hosting Charge (1 Year)','pcs',1,35000.00,'fixed',35000.00,35000.00,0.00,0.00,0.00,0.00,0.00,0,0.00),(13,10,'service',NULL,'SVC','Software Development Charge','pcs',1,200000.00,'percent',0.00,0.00,0.00,0.00,200000.00,200000.00,200000.00,1,0.00);
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_print_settings`
--

DROP TABLE IF EXISTS `invoice_print_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_print_settings` (
  `id` tinyint unsigned NOT NULL,
  `settings_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `backup_json` longtext COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_invoice_print_settings_single` CHECK ((`id` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_print_settings`
--

LOCK TABLES `invoice_print_settings` WRITE;
/*!40000 ALTER TABLE `invoice_print_settings` DISABLE KEYS */;
INSERT INTO `invoice_print_settings` VALUES (1,'{\"page_size\":\"A4\",\"page_orientation\":\"portrait\",\"margin_top\":\"10\",\"margin_bottom\":\"10\",\"margin_left\":\"10\",\"margin_right\":\"10\",\"logo_enabled\":1,\"logo_path\":\"assets\\/images\\/vk-logo.png\",\"logo_width_mm\":\"42\",\"logo_height_mm\":\"24\",\"logo_x\":\"0\",\"logo_y\":\"0\",\"company_name_font\":\"Arial, Helvetica, sans-serif\",\"company_name_size\":\"32\",\"company_name_weight\":\"700\",\"company_name_color\":\"#0b4dba\",\"company_desc_size\":\"13\",\"company_desc_color\":\"#0b4dba\",\"header_line_enabled\":1,\"header_line_color\":\"#0b4dba\",\"header_line_thickness\":\"5\",\"invoice_title_size\":\"28\",\"invoice_title_weight\":\"700\",\"invoice_title_color\":\"#0b4dba\",\"invoice_title_margin_left\":\"0\",\"invoice_title_margin_top\":\"25\",\"customer_name_size\":\"12\",\"customer_address_size\":\"11\",\"customer_label_size\":\"10\",\"table_header_bg\":\"#0b4dba\",\"table_header_color\":\"#ffffff\",\"table_header_size\":\"9.5\",\"table_body_size\":\"11\",\"table_border_color\":\"#d8dce3\",\"table_row_padding\":\"6\",\"total_font_size\":\"16\",\"total_label_size\":\"13\",\"total_font_weight\":\"700\",\"total_bg\":\"#0b4dba\",\"total_color\":\"#ffffff\",\"signature_enabled\":1,\"signature_path\":\"assets\\/images\\/digital-signature.png\",\"signature_width\":\"130\",\"signature_height\":0,\"signature_opacity\":\"1\",\"signature_x\":0,\"signature_y\":0,\"signature_rotation\":\"0\",\"signature_aspect\":1,\"stamp_enabled\":1,\"stamp_path\":\"assets\\/images\\/company-stamp.png\",\"stamp_width_mm\":\"90\",\"stamp_height_mm\":\"32\",\"stamp_opacity\":\"1\",\"stamp_rotation\":\"0\",\"stamp_x\":0,\"stamp_y\":0,\"stamp_aspect\":1,\"stamp_preset\":\"large\",\"approval_label_size\":\"11\",\"approval_label_weight\":700,\"approval_label_color\":\"#333333\",\"global_font_family\":\"Inter, Segoe UI, Arial, sans-serif\",\"global_font_size\":\"11\",\"footer_font_size\":\"11\",\"footer_height\":\"0\",\"footer_border_enabled\":1,\"footer_qr_enabled\":1,\"watermark_enabled\":1,\"watermark_path\":\"assets\\/images\\/vk-logo.png\",\"watermark_opacity\":\"0.03\",\"watermark_width\":\"380\",\"watermark_height\":0,\"watermark_rotation\":\"0\",\"stamp_contrast\":\"1.5\",\"stamp_saturation\":\"1.4\",\"stamp_brightness\":1.08}',NULL,'2026-07-05 10:12:30');
/*!40000 ALTER TABLE `invoice_print_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_revisions`
--

DROP TABLE IF EXISTS `invoice_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_revisions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `revision_no` int unsigned NOT NULL,
  `snapshot_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_summary` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_rev` (`invoice_id`,`revision_no`),
  CONSTRAINT `fk_inv_rev_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_revisions`
--

LOCK TABLES `invoice_revisions` WRITE;
/*!40000 ALTER TABLE `invoice_revisions` DISABLE KEYS */;
INSERT INTO `invoice_revisions` VALUES (1,10,0,'{\"header\":{\"id\":10,\"invoice_number\":\"INV-20260807-0001\",\"customer_id\":4,\"invoice_date\":\"2026-08-07\",\"due_date\":null,\"branch\":null,\"salesperson_id\":null,\"currency\":\"LKR\",\"reference_number\":null,\"payment_method\":null,\"terms\":null,\"subtotal\":\"235000.00\",\"item_discount_total\":\"0.00\",\"invoice_discount_type\":\"fixed\",\"invoice_discount_value\":\"0.00\",\"invoice_discount_amount\":\"0.00\",\"discount\":\"0.00\",\"tax\":\"0.00\",\"shipping_amount\":\"0.00\",\"adjustment_amount\":\"0.00\",\"round_off\":\"0.00\",\"revision_no\":0,\"created_by\":null,\"updated_by\":null,\"is_draft\":0,\"cancelled_at\":null,\"cancelled_by\":null,\"cancel_reason\":null,\"grand_total\":\"235000.00\",\"paid_amount\":\"100000.00\",\"status\":\"partial\",\"notes\":null,\"internal_notes\":null,\"source\":\"manual\",\"repair_job_id\":null,\"cctv_job_id\":null,\"created_at\":\"2026-08-07 13:23:44\",\"updated_at\":null,\"customer_name\":\"TS Transport\",\"phone\":\"077 247 4128\",\"email\":null,\"address\":\"9CX6+J76 TS Transport service, Kilinochchi\"},\"items\":[{\"id\":6,\"invoice_id\":10,\"item_type\":\"service\",\"product_id\":null,\"item_code\":null,\"line_description\":\"Server Hosting Charge\",\"unit\":\"pcs\",\"quantity\":1,\"unit_price\":\"35000.00\",\"discount_type\":\"percent\",\"discount_value\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"net_price\":\"0.00\",\"net_amount\":\"0.00\",\"line_total\":\"35000.00\",\"sort_order\":0,\"cost_price\":\"0.00\",\"product_name\":null,\"product_stock\":null,\"product_price\":null},{\"id\":7,\"invoice_id\":10,\"item_type\":\"service\",\"product_id\":null,\"item_code\":null,\"line_description\":\"Software Development Charge\",\"unit\":\"pcs\",\"quantity\":1,\"unit_price\":\"200000.00\",\"discount_type\":\"percent\",\"discount_value\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"net_price\":\"0.00\",\"net_amount\":\"0.00\",\"line_total\":\"200000.00\",\"sort_order\":0,\"cost_price\":\"0.00\",\"product_name\":null,\"product_stock\":null,\"product_price\":null}]}','Before update',1,'2026-08-07 08:22:20'),(2,10,1,'{\"header\":{\"id\":10,\"invoice_number\":\"INV-20260807-0001\",\"customer_id\":4,\"invoice_date\":\"2026-08-07\",\"due_date\":null,\"branch\":null,\"salesperson_id\":null,\"currency\":\"LKR\",\"reference_number\":null,\"payment_method\":null,\"terms\":null,\"subtotal\":\"235000.00\",\"item_discount_total\":\"35000.00\",\"invoice_discount_type\":\"fixed\",\"invoice_discount_value\":\"0.00\",\"invoice_discount_amount\":\"0.00\",\"discount\":\"0.00\",\"tax\":\"0.00\",\"shipping_amount\":\"0.00\",\"adjustment_amount\":\"0.00\",\"round_off\":\"0.00\",\"revision_no\":1,\"created_by\":null,\"updated_by\":1,\"is_draft\":0,\"cancelled_at\":null,\"cancelled_by\":null,\"cancel_reason\":null,\"grand_total\":\"200000.00\",\"paid_amount\":\"100000.00\",\"status\":\"partial\",\"notes\":null,\"internal_notes\":null,\"source\":\"manual\",\"repair_job_id\":null,\"cctv_job_id\":null,\"created_at\":\"2026-08-07 13:23:44\",\"updated_at\":\"2026-08-07 13:52:20\",\"customer_name\":\"TS Transport\",\"phone\":\"077 247 4128\",\"email\":null,\"address\":\"9CX6+J76 TS Transport service, Kilinochchi\"},\"items\":[{\"id\":8,\"invoice_id\":10,\"item_type\":\"service\",\"product_id\":null,\"item_code\":\"SVC\",\"line_description\":\"Server Hosting Charge\",\"unit\":\"pcs\",\"quantity\":1,\"unit_price\":\"35000.00\",\"discount_type\":\"percent\",\"discount_value\":\"35000.00\",\"discount_amount\":\"35000.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"net_price\":\"0.00\",\"net_amount\":\"0.00\",\"line_total\":\"0.00\",\"sort_order\":0,\"cost_price\":\"0.00\",\"product_name\":null,\"product_stock\":null,\"product_price\":null},{\"id\":9,\"invoice_id\":10,\"item_type\":\"service\",\"product_id\":null,\"item_code\":\"SVC\",\"line_description\":\"Software Development Charge\",\"unit\":\"pcs\",\"quantity\":1,\"unit_price\":\"200000.00\",\"discount_type\":\"percent\",\"discount_value\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"net_price\":\"200000.00\",\"net_amount\":\"200000.00\",\"line_total\":\"200000.00\",\"sort_order\":1,\"cost_price\":\"0.00\",\"product_name\":null,\"product_stock\":null,\"product_price\":null}]}','Before update',1,'2026-08-07 08:22:20'),(4,10,2,'{\"header\":{\"id\":10,\"invoice_number\":\"INV-20260807-0001\",\"customer_id\":4,\"invoice_date\":\"2026-08-07\",\"due_date\":null,\"branch\":null,\"salesperson_id\":null,\"currency\":\"LKR\",\"reference_number\":null,\"payment_method\":null,\"terms\":null,\"subtotal\":\"235000.00\",\"item_discount_total\":\"35000.00\",\"invoice_discount_type\":\"fixed\",\"invoice_discount_value\":\"0.00\",\"invoice_discount_amount\":\"0.00\",\"discount\":\"0.00\",\"tax\":\"0.00\",\"shipping_amount\":\"0.00\",\"adjustment_amount\":\"0.00\",\"round_off\":\"0.00\",\"revision_no\":2,\"created_by\":null,\"updated_by\":1,\"is_draft\":0,\"cancelled_at\":null,\"cancelled_by\":null,\"cancel_reason\":null,\"grand_total\":\"200000.00\",\"paid_amount\":\"100000.00\",\"status\":\"partial\",\"notes\":null,\"internal_notes\":null,\"source\":\"manual\",\"repair_job_id\":null,\"cctv_job_id\":null,\"created_at\":\"2026-08-07 13:23:44\",\"updated_at\":\"2026-08-07 13:53:28\",\"customer_name\":\"TS Transport\",\"phone\":\"077 247 4128\",\"email\":null,\"address\":\"9CX6+J76 TS Transport service, Kilinochchi\"},\"items\":[{\"id\":10,\"invoice_id\":10,\"item_type\":\"service\",\"product_id\":null,\"item_code\":\"SVC\",\"line_description\":\"Server Hosting Charge (1 Year)\",\"unit\":\"pcs\",\"quantity\":1,\"unit_price\":\"35000.00\",\"discount_type\":\"percent\",\"discount_value\":\"35000.00\",\"discount_amount\":\"35000.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"net_price\":\"0.00\",\"net_amount\":\"0.00\",\"line_total\":\"0.00\",\"sort_order\":0,\"cost_price\":\"0.00\",\"product_name\":null,\"product_stock\":null,\"product_price\":null},{\"id\":11,\"invoice_id\":10,\"item_type\":\"service\",\"product_id\":null,\"item_code\":\"SVC\",\"line_description\":\"Software Development Charge\",\"unit\":\"pcs\",\"quantity\":1,\"unit_price\":\"200000.00\",\"discount_type\":\"percent\",\"discount_value\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"net_price\":\"200000.00\",\"net_amount\":\"200000.00\",\"line_total\":\"200000.00\",\"sort_order\":1,\"cost_price\":\"0.00\",\"product_name\":null,\"product_stock\":null,\"product_price\":null}]}','Before update',1,'2026-08-07 08:23:28'),(6,10,3,'{\"header\":{\"id\":10,\"invoice_number\":\"INV-20260807-0001\",\"customer_id\":4,\"invoice_date\":\"2026-08-07\",\"due_date\":null,\"branch\":null,\"salesperson_id\":null,\"currency\":\"LKR\",\"reference_number\":null,\"payment_method\":null,\"terms\":null,\"subtotal\":\"235000.00\",\"item_discount_total\":\"35000.00\",\"invoice_discount_type\":\"fixed\",\"invoice_discount_value\":\"0.00\",\"invoice_discount_amount\":\"0.00\",\"discount\":\"0.00\",\"tax\":\"0.00\",\"shipping_amount\":\"0.00\",\"adjustment_amount\":\"0.00\",\"round_off\":\"0.00\",\"revision_no\":3,\"created_by\":null,\"updated_by\":1,\"is_draft\":0,\"cancelled_at\":null,\"cancelled_by\":null,\"cancel_reason\":null,\"grand_total\":\"200000.00\",\"paid_amount\":\"100000.00\",\"status\":\"partial\",\"notes\":null,\"internal_notes\":null,\"source\":\"manual\",\"repair_job_id\":null,\"cctv_job_id\":null,\"created_at\":\"2026-08-07 13:23:44\",\"updated_at\":\"2026-08-07 14:44:56\",\"customer_name\":\"TS Transport\",\"phone\":\"077 247 4128\",\"email\":null,\"address\":\"9CX6+J76 TS Transport service, Kilinochchi\"},\"items\":[{\"id\":12,\"invoice_id\":10,\"item_type\":\"service\",\"product_id\":null,\"item_code\":\"SVC\",\"line_description\":\"Server Hosting Charge (1 Year)\",\"unit\":\"pcs\",\"quantity\":1,\"unit_price\":\"35000.00\",\"discount_type\":\"fixed\",\"discount_value\":\"35000.00\",\"discount_amount\":\"35000.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"net_price\":\"0.00\",\"net_amount\":\"0.00\",\"line_total\":\"0.00\",\"sort_order\":0,\"cost_price\":\"0.00\",\"product_name\":null,\"product_stock\":null,\"product_price\":null},{\"id\":13,\"invoice_id\":10,\"item_type\":\"service\",\"product_id\":null,\"item_code\":\"SVC\",\"line_description\":\"Software Development Charge\",\"unit\":\"pcs\",\"quantity\":1,\"unit_price\":\"200000.00\",\"discount_type\":\"percent\",\"discount_value\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"net_price\":\"200000.00\",\"net_amount\":\"200000.00\",\"line_total\":\"200000.00\",\"sort_order\":1,\"cost_price\":\"0.00\",\"product_name\":null,\"product_stock\":null,\"product_price\":null}]}','Invoice updated',1,'2026-08-07 09:14:56');
/*!40000 ALTER TABLE `invoice_revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `branch` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salesperson_id` int unsigned DEFAULT NULL,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LKR',
  `reference_number` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms` text COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `item_discount_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `invoice_discount_type` enum('percent','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `invoice_discount_value` decimal(14,2) NOT NULL DEFAULT '0.00',
  `invoice_discount_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax` decimal(14,2) NOT NULL DEFAULT '0.00',
  `shipping_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `adjustment_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `round_off` decimal(14,2) NOT NULL DEFAULT '0.00',
  `revision_no` int unsigned NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `is_draft` tinyint(1) NOT NULL DEFAULT '0',
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int unsigned DEFAULT NULL,
  `cancel_reason` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grand_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `paid_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','unpaid','partial','paid','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unpaid',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `source` enum('manual','repair','cctv') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `repair_job_id` int unsigned DEFAULT NULL,
  `cctv_job_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_invoices_date` (`invoice_date`),
  KEY `idx_invoices_customer` (`customer_id`),
  KEY `fk_invoices_repair` (`repair_job_id`),
  KEY `fk_invoices_cctv` (`cctv_job_id`),
  CONSTRAINT `fk_invoices_cctv` FOREIGN KEY (`cctv_job_id`) REFERENCES `cctv_installations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoices_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_invoices_repair` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (4,'INV-20260429-0001',1,'2026-04-29',NULL,NULL,NULL,'LKR',NULL,NULL,NULL,1000.00,0.00,'fixed',0.00,0.00,0.00,0.00,0.00,0.00,0.00,0,NULL,NULL,0,NULL,NULL,NULL,1000.00,0.00,'unpaid',NULL,NULL,'manual',NULL,NULL,'2026-04-29 06:26:06',NULL),(7,'INV-20260429-0002',1,'2026-04-29',NULL,NULL,NULL,'LKR',NULL,NULL,NULL,1000.00,0.00,'fixed',0.00,0.00,0.00,0.00,0.00,0.00,0.00,0,NULL,NULL,0,NULL,NULL,NULL,1000.00,900.00,'partial',NULL,NULL,'manual',NULL,NULL,'2026-04-29 07:11:38',NULL),(8,'INV-20260705-0001',3,'2026-07-03',NULL,NULL,NULL,'LKR',NULL,NULL,NULL,1500.00,0.00,'fixed',0.00,0.00,0.00,0.00,0.00,0.00,0.00,0,NULL,NULL,0,NULL,NULL,NULL,1500.00,1500.00,'paid',NULL,NULL,'manual',NULL,NULL,'2026-07-05 07:09:21','2026-07-05 09:13:17'),(9,'INV-20260717-0001',3,'2026-07-17',NULL,NULL,NULL,'LKR',NULL,NULL,NULL,620.00,0.00,'fixed',0.00,0.00,0.00,0.00,0.00,0.00,0.00,0,NULL,NULL,0,NULL,NULL,NULL,620.00,620.00,'paid',NULL,NULL,'manual',NULL,NULL,'2026-07-17 09:04:11',NULL),(10,'INV-20260807-0001',4,'2026-08-07',NULL,NULL,NULL,'LKR',NULL,NULL,NULL,235000.00,35000.00,'fixed',0.00,0.00,0.00,0.00,0.00,0.00,0.00,3,NULL,1,0,NULL,NULL,NULL,200000.00,100000.00,'partial',NULL,NULL,'manual',NULL,NULL,'2026-08-07 07:53:44','2026-08-07 09:14:56');
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `username` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('success','failed','blocked','logout') COLLATE utf8mb4_unicode_ci NOT NULL,
  `failure_reason` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_logs_user` (`user_id`,`created_at`),
  KEY `idx_login_logs_lookup` (`username`,`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_logs`
--

LOCK TABLES `login_logs` WRITE;
/*!40000 ALTER TABLE `login_logs` DISABLE KEYS */;
INSERT INTO `login_logs` VALUES (1,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','success',NULL,'2026-05-10 09:44:22'),(2,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','success',NULL,'2026-05-11 05:30:10'),(3,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','logout',NULL,'2026-05-11 05:30:44'),(4,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','success',NULL,'2026-05-11 06:07:07'),(5,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','success',NULL,'2026-05-17 03:36:01'),(6,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','success',NULL,'2026-05-17 07:14:27'),(7,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','success',NULL,'2026-06-14 03:57:47'),(8,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','success',NULL,'2026-06-14 05:52:02'),(9,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-06-26 09:53:17'),(10,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-06-26 10:08:13'),(11,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-06-26 10:09:37'),(12,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-06-26 10:09:45'),(13,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-06-27 04:38:16'),(14,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-06-28 03:52:50'),(15,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-07-03 04:31:26'),(16,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-07-05 07:07:54'),(17,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-07-08 15:00:27'),(18,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-07-08 15:00:37'),(19,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-07-08 15:00:51'),(20,NULL,'emmentagrossist@gmail.com','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-07-17 07:43:54'),(21,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-07-17 07:53:57'),(22,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-07-17 07:54:06'),(23,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-08-06 07:30:49'),(24,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','failed','invalid_credentials','2026-08-06 07:30:54'),(25,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-08-06 07:48:36'),(26,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-08-06 10:07:27'),(27,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','success',NULL,'2026-08-07 06:38:08'),(28,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','logout',NULL,'2026-08-07 10:27:52'),(29,1,'admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','success',NULL,'2026-08-08 05:32:12');
/*!40000 ALTER TABLE `login_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_contracts`
--

DROP TABLE IF EXISTS `maintenance_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_contracts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `contract_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `contract_type` enum('computer_amc','cctv_maintenance') COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `visit_frequency` enum('monthly','quarterly','yearly','one_time') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yearly',
  `next_service_date` date DEFAULT NULL,
  `status` enum('active','paused','expired','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `cctv_installation_id` int unsigned DEFAULT NULL,
  `annual_fee` decimal(14,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_number` (`contract_number`),
  KEY `idx_maint_customer` (`customer_id`),
  KEY `idx_maint_next` (`next_service_date`),
  KEY `idx_maint_status` (`status`),
  KEY `fk_maint_cctv` (`cctv_installation_id`),
  CONSTRAINT `fk_maint_cctv` FOREIGN KEY (`cctv_installation_id`) REFERENCES `cctv_installations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_maint_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_contracts`
--

LOCK TABLES `maintenance_contracts` WRITE;
/*!40000 ALTER TABLE `maintenance_contracts` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `maintenance_visits`
--

DROP TABLE IF EXISTS `maintenance_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_visits` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` int unsigned NOT NULL,
  `visit_date` date NOT NULL,
  `technician_id` int unsigned DEFAULT NULL,
  `work_performed` text COLLATE utf8mb4_unicode_ci,
  `checks_done` text COLLATE utf8mb4_unicode_ci,
  `charges` decimal(14,2) NOT NULL DEFAULT '0.00',
  `next_service_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_mv_contract` (`contract_id`),
  KEY `fk_mv_technician` (`technician_id`),
  CONSTRAINT `fk_mv_contract` FOREIGN KEY (`contract_id`) REFERENCES `maintenance_contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mv_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_visits`
--

LOCK TABLES `maintenance_visits` WRITE;
/*!40000 ALTER TABLE `maintenance_visits` DISABLE KEYS */;
/*!40000 ALTER TABLE `maintenance_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marketing_analytics`
--

DROP TABLE IF EXISTS `marketing_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_analytics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `metric_date` date NOT NULL,
  `channel` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metric_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metric_value` decimal(14,4) NOT NULL DEFAULT '0.0000',
  `campaign_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_marketing_metric` (`metric_date`,`channel`,`metric_key`,`campaign_id`),
  KEY `idx_marketing_metric_date` (`metric_date`),
  KEY `fk_analytics_campaign` (`campaign_id`),
  CONSTRAINT `fk_analytics_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketing_analytics`
--

LOCK TABLES `marketing_analytics` WRITE;
/*!40000 ALTER TABLE `marketing_analytics` DISABLE KEYS */;
/*!40000 ALTER TABLE `marketing_analytics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marketing_campaigns`
--

DROP TABLE IF EXISTS `marketing_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_campaigns` (
  `id` int NOT NULL AUTO_INCREMENT,
  `campaign_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` enum('email','whatsapp','sms','facebook','instagram','multi_channel') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'multi_channel',
  `objective` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `segment` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'All customers',
  `status` enum('draft','scheduled','active','paused','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `budget` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reach` int unsigned NOT NULL DEFAULT '0',
  `engagement` int unsigned NOT NULL DEFAULT '0',
  `conversions` int unsigned NOT NULL DEFAULT '0',
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_status` (`status`),
  KEY `idx_campaign_channel` (`channel`),
  KEY `idx_campaign_dates` (`starts_at`,`ends_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketing_campaigns`
--

LOCK TABLES `marketing_campaigns` WRITE;
/*!40000 ALTER TABLE `marketing_campaigns` DISABLE KEYS */;
INSERT INTO `marketing_campaigns` VALUES (1,'Warranty Renewal Push','whatsapp','Win expiring warranty renewals','Warranty customers','active',15000.00,1240,318,42,'2026-05-10 07:39:03','2026-05-31 07:39:03','Automated WhatsApp and email renewal journey.',NULL,'2026-05-10 07:39:03','2026-05-10 07:39:03'),(2,'CCTV Maintenance Awareness','multi_channel','Generate maintenance contract leads','CCTV customers','scheduled',25000.00,2800,604,67,'2026-05-12 07:39:03','2026-06-11 07:39:03','Facebook, Instagram, WhatsApp, and email campaign.',NULL,'2026-05-10 07:39:03','2026-05-10 07:39:03');
/*!40000 ALTER TABLE `marketing_campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marketing_email_templates`
--

DROP TABLE IF EXISTS `marketing_email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_email_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('welcome','booking','invoice','completion','warranty','payment','newsletter','campaign') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'campaign',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preheader` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `html_body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_body` mediumtext COLLATE utf8mb4_unicode_ci,
  `variables` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','active','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_key` (`template_key`),
  KEY `idx_email_templates_category` (`category`),
  KEY `idx_email_templates_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketing_email_templates`
--

LOCK TABLES `marketing_email_templates` WRITE;
/*!40000 ALTER TABLE `marketing_email_templates` DISABLE KEYS */;
INSERT INTO `marketing_email_templates` VALUES (1,'booking_confirmation_modern','Premium Booking Confirmation','booking','Your service booking is confirmed','We received your booking and will contact you shortly.','<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"font-family:Inter,Arial,sans-serif;background:#07111f;color:#ffffff;padding:24px\"><tr><td><h1 style=\"margin:0 0 12px\">Hello {{customer_name}}</h1><p style=\"color:#cbd5e1\">Your {{service_name}} update is ready.</p><a href=\"{{cta_url}}\" style=\"display:inline-block;background:#2f7cff;color:#ffffff;padding:12px 18px;border-radius:14px;text-decoration:none;font-weight:700\">View update</a><p style=\"color:#94a3b8;margin-top:24px\">VK IT Network</p></td></tr></table>','Hello {{customer_name}}, your {{service_name}} booking is confirmed.','{{customer_name}}, {{service_name}}, {{cta_url}}','active','2026-05-10 07:39:03','2026-05-10 07:39:03'),(2,'warranty_reminder_modern','Warranty Renewal Reminder','warranty','Warranty renewal reminder','Keep your device protected with VK IT Network.','<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"font-family:Inter,Arial,sans-serif;background:#07111f;color:#ffffff;padding:24px\"><tr><td><h1 style=\"margin:0 0 12px\">Hello {{customer_name}}</h1><p style=\"color:#cbd5e1\">Your {{service_name}} update is ready.</p><a href=\"{{cta_url}}\" style=\"display:inline-block;background:#2f7cff;color:#ffffff;padding:12px 18px;border-radius:14px;text-decoration:none;font-weight:700\">View update</a><p style=\"color:#94a3b8;margin-top:24px\">VK IT Network</p></td></tr></table>','Hello {{customer_name}}, your warranty is nearing expiry.','{{customer_name}}, {{warranty_end}}, {{cta_url}}','active','2026-05-10 07:39:03','2026-05-10 07:39:03');
/*!40000 ALTER TABLE `marketing_email_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marketing_leads`
--

DROP TABLE IF EXISTS `marketing_leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_leads` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lead_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Website',
  `service_interest` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stage` enum('new','contacted','qualified','proposal','won','lost') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `score` tinyint unsigned NOT NULL DEFAULT '50',
  `campaign_id` int DEFAULT NULL,
  `estimated_value` decimal(12,2) NOT NULL DEFAULT '0.00',
  `last_touch_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leads_stage` (`stage`),
  KEY `idx_leads_source` (`source`),
  KEY `idx_leads_campaign` (`campaign_id`),
  CONSTRAINT `fk_leads_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketing_leads`
--

LOCK TABLES `marketing_leads` WRITE;
/*!40000 ALTER TABLE `marketing_leads` DISABLE KEYS */;
INSERT INTO `marketing_leads` VALUES (1,'Prime Office Systems','ops@example.com','+94770000001','Website','CCTV annual maintenance','qualified',82,1,85000.00,'2026-05-10 07:39:03','High intent lead from service page.','2026-05-10 07:39:03','2026-05-10 07:39:03'),(2,'Northern Retail Hub','it@example.com','+94770000002','WhatsApp','Computer repair contract','contacted',68,1,45000.00,'2026-05-09 07:39:03','Asked for SLA options.','2026-05-10 07:39:03','2026-05-10 07:39:03');
/*!40000 ALTER TABLE `marketing_leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menus` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menus`
--

LOCK TABLES `menus` WRITE;
/*!40000 ALTER TABLE `menus` DISABLE KEYS */;
INSERT INTO `menus` VALUES (1,'Home','home','index.php','lucide:home',10,'active','2026-04-12 09:33:33'),(2,'Book Service','book','book.php','lucide:calendar-plus',0,'active','2026-04-12 09:33:33'),(3,'Vehicle Booking','vehicle','vehicle/index.php','lucide:car-front',20,'active','2026-04-12 09:33:33'),(4,'Track Status','track','track.php','lucide:search',30,'active','2026-04-12 09:33:33'),(5,'Our Work','portfolio','portfolio.php','lucide:images',40,'active','2026-04-12 09:33:33');
/*!40000 ALTER TABLE `menus` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_history`
--

DROP TABLE IF EXISTS `notification_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `notification_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` varchar(700) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `severity` enum('info','success','warning','danger') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
  `related_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_read` (`is_read`),
  KEY `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_history`
--

LOCK TABLES `notification_history` WRITE;
/*!40000 ALTER TABLE `notification_history` DISABLE KEYS */;
INSERT INTO `notification_history` VALUES (1,'marketing','AI campaign insight ready','Warranty Renewal Push is outperforming the baseline response rate.','success','/vk/modules/marketing/ai.php',0,'2026-05-10 07:39:03'),(2,'seo','SEO audit queue prepared','Technical SEO checks are ready for review.','info','/vk/modules/seo/analytics.php',0,'2026-05-10 07:39:03');
/*!40000 ALTER TABLE `notification_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` int unsigned DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned DEFAULT NULL,
  `repair_job_id` int unsigned DEFAULT NULL,
  `cctv_job_id` int unsigned DEFAULT NULL,
  `customer_id` int unsigned DEFAULT NULL,
  `customer_account_id` int unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `method` enum('cash','card','bank','online') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `paid_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_repair` (`repair_job_id`),
  KEY `idx_payments_cctv` (`cctv_job_id`),
  KEY `fk_payments_account` (`customer_account_id`),
  KEY `idx_payments_customer` (`customer_id`),
  CONSTRAINT `fk_payments_account` FOREIGN KEY (`customer_account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_cctv` FOREIGN KEY (`cctv_job_id`) REFERENCES `cctv_installations` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_payments_repair` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,7,NULL,NULL,1,1,900.00,'cash','2026-04-29 12:41:38',NULL),(2,8,NULL,NULL,3,4,1500.00,'cash','2026-07-05 14:43:17',NULL),(3,9,NULL,NULL,3,4,620.00,'cash','2026-07-17 14:34:11',NULL),(4,10,NULL,NULL,4,5,100000.00,'cash','2026-08-07 13:23:44',NULL);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `portfolio_posts`
--

DROP TABLE IF EXISTS `portfolio_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portfolio_posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_before` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_after` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `post_date` date NOT NULL,
  `published` tinyint(1) NOT NULL DEFAULT '1',
  `ref_type` enum('repair','cctv','booking','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `ref_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pf4_published_date` (`published`,`post_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `portfolio_posts`
--

LOCK TABLES `portfolio_posts` WRITE;
/*!40000 ALTER TABLE `portfolio_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `portfolio_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `stock` int NOT NULL DEFAULT '0',
  `low_stock_threshold` int unsigned NOT NULL DEFAULT '5',
  `category` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_name` (`name`),
  KEY `idx_products_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_activity_logs`
--

DROP TABLE IF EXISTS `quotation_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qlog_quotation` (`quotation_id`,`created_at`),
  KEY `idx_qlog_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_activity_logs`
--

LOCK TABLES `quotation_activity_logs` WRITE;
/*!40000 ALTER TABLE `quotation_activity_logs` DISABLE KEYS */;
INSERT INTO `quotation_activity_logs` VALUES (1,1,1,'created','Quotation QT-2026-000001 created','::1','2026-08-07 06:51:56'),(2,1,1,'updated','Quotation updated','::1','2026-08-07 10:07:17'),(3,1,1,'updated','Quotation updated','::1','2026-08-07 10:08:05'),(4,2,1,'created','Quotation QT-2026-000002 created','::1','2026-08-08 05:43:59');
/*!40000 ALTER TABLE `quotation_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_approvals`
--

DROP TABLE IF EXISTS `quotation_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_approvals` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int unsigned NOT NULL,
  `level` tinyint unsigned NOT NULL DEFAULT '1',
  `role_label` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_id` int unsigned DEFAULT NULL,
  `action` enum('pending','approved','rejected','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `acted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qappr_quotation` (`quotation_id`,`level`),
  CONSTRAINT `fk_qappr_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_approvals`
--

LOCK TABLES `quotation_approvals` WRITE;
/*!40000 ALTER TABLE `quotation_approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotation_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_attachments`
--

DROP TABLE IF EXISTS `quotation_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int unsigned NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT '0',
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qatt_quotation` (`quotation_id`),
  CONSTRAINT `fk_qatt_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_attachments`
--

LOCK TABLES `quotation_attachments` WRITE;
/*!40000 ALTER TABLE `quotation_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotation_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_categories`
--

DROP TABLE IF EXISTS `quotation_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT '#0B4DBA',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qcat_slug` (`slug`),
  KEY `idx_qcat_active` (`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_categories`
--

LOCK TABLES `quotation_categories` WRITE;
/*!40000 ALTER TABLE `quotation_categories` DISABLE KEYS */;
INSERT INTO `quotation_categories` VALUES (1,'Hardware','hardware','Computers, parts, peripherals','#0B4DBA',1,10,'2026-08-06 08:09:38',NULL),(2,'Software','software','Licenses and development','#0d9488',1,20,'2026-08-06 08:09:38',NULL),(3,'CCTV','cctv','Surveillance packages','#7c3aed',1,30,'2026-08-06 08:09:38',NULL),(4,'Networking','networking','Network infrastructure','#0369a1',1,40,'2026-08-06 08:09:38',NULL),(5,'Service','service','Repair and maintenance services','#b45309',1,50,'2026-08-06 08:09:38',NULL),(6,'AMC','amc','Annual maintenance contracts','#047857',1,60,'2026-08-06 08:09:38',NULL);
/*!40000 ALTER TABLE `quotation_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_email_tracking`
--

DROP TABLE IF EXISTS `quotation_email_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_email_tracking` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int unsigned NOT NULL,
  `tracking_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `download_count` int unsigned NOT NULL DEFAULT '0',
  `last_download_at` datetime DEFAULT NULL,
  `status` enum('queued','sent','failed','bounced') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `error_message` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qemail_token` (`tracking_token`),
  KEY `idx_qemail_quotation` (`quotation_id`),
  CONSTRAINT `fk_qemail_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_email_tracking`
--

LOCK TABLES `quotation_email_tracking` WRITE;
/*!40000 ALTER TABLE `quotation_email_tracking` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotation_email_tracking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_followups`
--

DROP TABLE IF EXISTS `quotation_followups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_followups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int unsigned NOT NULL,
  `reminder_date` date NOT NULL,
  `reminder_status` enum('pending','done','missed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `followup_notes` text COLLATE utf8mb4_unicode_ci,
  `customer_response` text COLLATE utf8mb4_unicode_ci,
  `expected_closing_date` date DEFAULT NULL,
  `assigned_to` int unsigned DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qfu_date` (`reminder_date`,`reminder_status`),
  KEY `idx_qfu_quotation` (`quotation_id`),
  CONSTRAINT `fk_qfu_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_followups`
--

LOCK TABLES `quotation_followups` WRITE;
/*!40000 ALTER TABLE `quotation_followups` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotation_followups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_items`
--

DROP TABLE IF EXISTS `quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int unsigned NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `item_type` enum('product','service','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'product',
  `product_id` int unsigned DEFAULT NULL,
  `product_code` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'pcs',
  `quantity` decimal(14,3) NOT NULL DEFAULT '1.000',
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cost_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `discount_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `tax_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock_available` decimal(14,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qitems_quotation` (`quotation_id`,`sort_order`),
  KEY `idx_qitems_product` (`product_id`),
  CONSTRAINT `fk_qitems_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_items`
--

LOCK TABLES `quotation_items` WRITE;
/*!40000 ALTER TABLE `quotation_items` DISABLE KEYS */;
INSERT INTO `quotation_items` VALUES (6,1,0,'custom',1,'','','test','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(7,1,1,'custom',NULL,'','','d','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(8,1,2,'custom',NULL,'','','d','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(9,1,3,'custom',NULL,'','','ff','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(10,1,4,'custom',NULL,'','','y','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(11,1,5,'custom',NULL,'','','kk','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(12,1,6,'custom',NULL,'','','h','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(13,1,7,'custom',NULL,'','','h','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(14,1,8,'custom',NULL,'','','h','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(15,1,9,'custom',NULL,'','','b','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000),(16,2,0,'custom',NULL,'','','0','','','pcs',1.000,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,NULL,'Main Warehouse',0.000);
/*!40000 ALTER TABLE `quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_notes`
--

DROP TABLE IF EXISTS `quotation_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_notes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int unsigned NOT NULL,
  `note_type` enum('general','internal','customer','system') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qnotes_quotation` (`quotation_id`),
  CONSTRAINT `fk_qnotes_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_notes`
--

LOCK TABLES `quotation_notes` WRITE;
/*!40000 ALTER TABLE `quotation_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotation_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_revisions`
--

DROP TABLE IF EXISTS `quotation_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_revisions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` int unsigned NOT NULL,
  `revision_no` int unsigned NOT NULL,
  `snapshot_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_summary` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qrev` (`quotation_id`,`revision_no`),
  CONSTRAINT `fk_qrev_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_revisions`
--

LOCK TABLES `quotation_revisions` WRITE;
/*!40000 ALTER TABLE `quotation_revisions` DISABLE KEYS */;
INSERT INTO `quotation_revisions` VALUES (1,1,0,'{\"header\":{\"id\":1,\"quotation_number\":\"QT-2026-000001\",\"revision_no\":0,\"parent_quotation_id\":null,\"customer_id\":4,\"customer_code\":\"CUS-00004\",\"company_name\":\"TS Transport\",\"contact_person\":\"TS Transport\",\"phone\":\"077 247 4128\",\"mobile\":\"077 247 4128\",\"email\":\"\",\"tax_number\":\"\",\"credit_limit\":\"0.00\",\"billing_address\":\"9CX6+J76 TS Transport service, Kilinochchi\",\"shipping_address\":\"9CX6+J76 TS Transport service, Kilinochchi\",\"currency\":\"LKR\",\"exchange_rate\":\"1.000000\",\"sales_executive_id\":1,\"category_id\":null,\"template_id\":null,\"reference_number\":\"\",\"customer_po_number\":\"\",\"quotation_date\":\"2026-08-07\",\"expiry_date\":\"2026-09-06\",\"payment_terms\":\"\",\"delivery_terms\":\"\",\"validity_days\":30,\"tax_method\":\"exclusive\",\"status\":\"draft\",\"approval_status\":\"none\",\"approval_level\":0,\"subtotal\":\"0.00\",\"item_discount_total\":\"0.00\",\"overall_discount_pct\":\"0.00\",\"overall_discount_amount\":\"0.00\",\"tax_total\":\"0.00\",\"shipping_amount\":\"0.00\",\"additional_charges\":\"0.00\",\"round_off\":\"0.00\",\"grand_total\":\"0.00\",\"estimated_cost\":\"0.00\",\"net_profit\":\"0.00\",\"profit_margin_pct\":\"0.00\",\"notes\":\"\",\"internal_notes\":\"\",\"terms_html\":\"1. Prices are valid until the quotation expiry date.\\r\\n2. Payment terms as stated on this quotation.\\r\\n3. Delivery timelines commence after confirmation and advance payment (if applicable).\\r\\n4. Goods remain the property of VK Network until full payment is received.\\r\\n5. Warranty terms apply as per product\\/service specifications.\\r\\n6. VK Network reserves the right to revise prices if costs change after expiry.\",\"warranty_terms\":\"\",\"customer_response\":null,\"expected_closing_date\":null,\"converted_invoice_id\":null,\"converted_at\":null,\"branch\":\"Kilinochchi\",\"department\":\"Sales\",\"warehouse\":\"Main Warehouse\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-07 12:21:56\",\"updated_at\":null,\"customer_name\":\"TS Transport\",\"customer_phone_db\":\"077 247 4128\",\"customer_email_db\":null,\"customer_address_db\":\"9CX6+J76 TS Transport service, Kilinochchi\",\"sales_executive_name\":\"Administrator\",\"category_name\":null,\"created_by_name\":\"Administrator\"},\"items\":[{\"id\":1,\"quotation_id\":1,\"sort_order\":0,\"item_type\":\"custom\",\"product_id\":null,\"product_code\":\"\",\"barcode\":\"\",\"product_name\":\"test\",\"category_name\":\"\",\"description\":\"\",\"unit\":\"pcs\",\"quantity\":\"1.000\",\"unit_price\":\"0.00\",\"cost_price\":\"0.00\",\"discount_pct\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"line_subtotal\":\"0.00\",\"line_total\":\"0.00\",\"image_path\":null,\"warehouse\":\"Main Warehouse\",\"stock_available\":\"0.000\"}]}','Auto-save before update',1,'2026-08-07 10:07:17'),(2,1,1,'{\"header\":{\"id\":1,\"quotation_number\":\"QT-2026-000001\",\"revision_no\":1,\"parent_quotation_id\":null,\"customer_id\":4,\"customer_code\":\"CUS-00004\",\"company_name\":\"TS Transport\",\"contact_person\":\"TS Transport\",\"phone\":\"077 247 4128\",\"mobile\":\"077 247 4128\",\"email\":\"\",\"tax_number\":\"\",\"credit_limit\":\"0.00\",\"billing_address\":\"9CX6+J76 TS Transport service, Kilinochchi\",\"shipping_address\":\"9CX6+J76 TS Transport service, Kilinochchi\",\"currency\":\"LKR\",\"exchange_rate\":\"1.000000\",\"sales_executive_id\":1,\"category_id\":null,\"template_id\":null,\"reference_number\":\"\",\"customer_po_number\":\"\",\"quotation_date\":\"2026-08-07\",\"expiry_date\":\"2026-09-06\",\"payment_terms\":\"\",\"delivery_terms\":\"\",\"validity_days\":30,\"tax_method\":\"exclusive\",\"status\":\"draft\",\"approval_status\":\"none\",\"approval_level\":0,\"subtotal\":\"0.00\",\"item_discount_total\":\"0.00\",\"overall_discount_pct\":\"0.00\",\"overall_discount_amount\":\"0.00\",\"tax_total\":\"0.00\",\"shipping_amount\":\"0.00\",\"additional_charges\":\"0.00\",\"round_off\":\"0.00\",\"grand_total\":\"0.00\",\"estimated_cost\":\"0.00\",\"net_profit\":\"0.00\",\"profit_margin_pct\":\"0.00\",\"notes\":\"\",\"internal_notes\":\"\",\"terms_html\":\"1. Prices are valid until the quotation expiry date.\\r\\n2. Payment terms as stated on this quotation.\\r\\n3. Delivery timelines commence after confirmation and advance payment (if applicable).\\r\\n4. Goods remain the property of VK Network until full payment is received.\\r\\n5. Warranty terms apply as per product\\/service specifications.\\r\\n6. VK Network reserves the right to revise prices if costs change after expiry.\",\"warranty_terms\":\"\",\"customer_response\":null,\"expected_closing_date\":null,\"converted_invoice_id\":null,\"converted_at\":null,\"branch\":\"Kilinochchi\",\"department\":\"Sales\",\"warehouse\":\"Main Warehouse\",\"created_by\":1,\"updated_by\":1,\"created_at\":\"2026-08-07 12:21:56\",\"updated_at\":\"2026-08-07 15:37:17\",\"customer_name\":\"TS Transport\",\"customer_phone_db\":\"077 247 4128\",\"customer_email_db\":null,\"customer_address_db\":\"9CX6+J76 TS Transport service, Kilinochchi\",\"sales_executive_name\":\"Administrator\",\"category_name\":null,\"created_by_name\":\"Administrator\"},\"items\":[{\"id\":2,\"quotation_id\":1,\"sort_order\":0,\"item_type\":\"custom\",\"product_id\":1,\"product_code\":\"\",\"barcode\":\"\",\"product_name\":\"test\",\"category_name\":\"\",\"description\":\"\",\"unit\":\"pcs\",\"quantity\":\"1.000\",\"unit_price\":\"0.00\",\"cost_price\":\"0.00\",\"discount_pct\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"line_subtotal\":\"0.00\",\"line_total\":\"0.00\",\"image_path\":null,\"warehouse\":\"Main Warehouse\",\"stock_available\":\"0.000\"},{\"id\":3,\"quotation_id\":1,\"sort_order\":1,\"item_type\":\"custom\",\"product_id\":null,\"product_code\":\"\",\"barcode\":\"\",\"product_name\":\"d\",\"category_name\":\"\",\"description\":\"\",\"unit\":\"pcs\",\"quantity\":\"1.000\",\"unit_price\":\"0.00\",\"cost_price\":\"0.00\",\"discount_pct\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"line_subtotal\":\"0.00\",\"line_total\":\"0.00\",\"image_path\":null,\"warehouse\":\"Main Warehouse\",\"stock_available\":\"0.000\"},{\"id\":4,\"quotation_id\":1,\"sort_order\":2,\"item_type\":\"custom\",\"product_id\":null,\"product_code\":\"\",\"barcode\":\"\",\"product_name\":\"d\",\"category_name\":\"\",\"description\":\"\",\"unit\":\"pcs\",\"quantity\":\"1.000\",\"unit_price\":\"0.00\",\"cost_price\":\"0.00\",\"discount_pct\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"line_subtotal\":\"0.00\",\"line_total\":\"0.00\",\"image_path\":null,\"warehouse\":\"Main Warehouse\",\"stock_available\":\"0.000\"},{\"id\":5,\"quotation_id\":1,\"sort_order\":3,\"item_type\":\"custom\",\"product_id\":null,\"product_code\":\"\",\"barcode\":\"\",\"product_name\":\"ff\",\"category_name\":\"\",\"description\":\"\",\"unit\":\"pcs\",\"quantity\":\"1.000\",\"unit_price\":\"0.00\",\"cost_price\":\"0.00\",\"discount_pct\":\"0.00\",\"discount_amount\":\"0.00\",\"tax_pct\":\"0.00\",\"tax_amount\":\"0.00\",\"line_subtotal\":\"0.00\",\"line_total\":\"0.00\",\"image_path\":null,\"warehouse\":\"Main Warehouse\",\"stock_available\":\"0.000\"}]}','Auto-save before update',1,'2026-08-07 10:08:05');
/*!40000 ALTER TABLE `quotation_revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_settings`
--

DROP TABLE IF EXISTS `quotation_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_qset_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_settings`
--

LOCK TABLES `quotation_settings` WRITE;
/*!40000 ALTER TABLE `quotation_settings` DISABLE KEYS */;
INSERT INTO `quotation_settings` VALUES (1,'prefix','QT','2026-08-06 09:10:56'),(2,'default_validity_days','30',NULL),(3,'default_currency','LKR',NULL),(4,'default_tax_pct','0',NULL),(5,'default_tax_method','exclusive',NULL),(6,'require_approval','1',NULL),(7,'approval_levels','sales_executive,manager,finance,director',NULL),(8,'auto_expire','1',NULL),(9,'bank_name','Commercial Bank',NULL),(10,'bank_account_name','VK Network',NULL),(11,'bank_account_number','',NULL),(12,'bank_branch','Kilinochchi',NULL),(13,'whatsapp_template','Hello {customer_name},\n\nPlease find quotation *{quotation_number}* for LKR {grand_total}.\nValid until: {expiry_date}\n\nView/Print: {print_url}\n\nThank you,\nVK Network',NULL),(14,'email_subject','Quotation {quotation_number} from VK Network',NULL),(16,'letterhead_path','assets/images/vk-letterhead.png',NULL),(17,'signature_path','assets/images/digital-signature.png',NULL),(18,'stamp_path','assets/images/company-stamp.png',NULL);
/*!40000 ALTER TABLE `quotation_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_templates`
--

DROP TABLE IF EXISTS `quotation_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `payment_terms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_terms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validity_days` int unsigned NOT NULL DEFAULT '30',
  `tax_method` enum('exclusive','inclusive','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exclusive',
  `default_tax_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `terms_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_qtmpl_active` (`is_active`,`name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_templates`
--

LOCK TABLES `quotation_templates` WRITE;
/*!40000 ALTER TABLE `quotation_templates` DISABLE KEYS */;
INSERT INTO `quotation_templates` VALUES (1,'vijayakumar Keerththeejan',NULL,'','','',30,'exclusive',0.00,'','',1,1,'2026-08-06 09:03:10',NULL);
/*!40000 ALTER TABLE `quotation_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_terms`
--

DROP TABLE IF EXISTS `quotation_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotation_terms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_terms`
--

LOCK TABLES `quotation_terms` WRITE;
/*!40000 ALTER TABLE `quotation_terms` DISABLE KEYS */;
INSERT INTO `quotation_terms` VALUES (1,'Standard Terms','1. Prices are valid until the quotation expiry date.\n2. Payment terms as stated on this quotation.\n3. Delivery timelines commence after confirmation and advance payment (if applicable).\n4. Goods remain the property of VK Network until full payment is received.\n5. Warranty terms apply as per product/service specifications.\n6. VK Network reserves the right to revise prices if costs change after expiry.',1,1,1,'2026-08-06 08:09:38');
/*!40000 ALTER TABLE `quotation_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revision_no` int unsigned NOT NULL DEFAULT '0',
  `parent_quotation_id` int unsigned DEFAULT NULL,
  `customer_id` int unsigned NOT NULL,
  `customer_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_limit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `billing_address` text COLLATE utf8mb4_unicode_ci,
  `shipping_address` text COLLATE utf8mb4_unicode_ci,
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'LKR',
  `exchange_rate` decimal(18,6) NOT NULL DEFAULT '1.000000',
  `sales_executive_id` int unsigned DEFAULT NULL,
  `category_id` int unsigned DEFAULT NULL,
  `template_id` int unsigned DEFAULT NULL,
  `reference_number` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_po_number` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quotation_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `payment_terms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_terms` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validity_days` int unsigned DEFAULT '30',
  `tax_method` enum('exclusive','inclusive','none') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exclusive',
  `status` enum('draft','pending_approval','approved','rejected','cancelled','expired','accepted','converted_so','converted_invoice') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approval_status` enum('none','pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `approval_level` tinyint unsigned NOT NULL DEFAULT '0',
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `item_discount_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `overall_discount_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `overall_discount_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `shipping_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `additional_charges` decimal(14,2) NOT NULL DEFAULT '0.00',
  `round_off` decimal(14,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `estimated_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `net_profit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `profit_margin_pct` decimal(8,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `terms_html` mediumtext COLLATE utf8mb4_unicode_ci,
  `warranty_terms` text COLLATE utf8mb4_unicode_ci,
  `customer_response` text COLLATE utf8mb4_unicode_ci,
  `expected_closing_date` date DEFAULT NULL,
  `converted_invoice_id` int unsigned DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `branch` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotations_number` (`quotation_number`),
  KEY `idx_quotations_customer` (`customer_id`),
  KEY `idx_quotations_status` (`status`),
  KEY `idx_quotations_date` (`quotation_date`),
  KEY `idx_quotations_expiry` (`expiry_date`),
  KEY `idx_quotations_exec` (`sales_executive_id`),
  KEY `idx_quotations_approval` (`approval_status`),
  KEY `idx_quotations_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
INSERT INTO `quotations` VALUES (1,'QT-2026-000001',2,NULL,4,'CUS-00004','TS Transport','TS Transport','077 247 4128','077 247 4128','','',0.00,'9CX6+J76 TS Transport service, Kilinochchi','9CX6+J76 TS Transport service, Kilinochchi','LKR',1.000000,1,NULL,NULL,'','','2026-08-07','2026-09-06','','',30,'exclusive','draft','none',0,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,'','','1. Prices are valid until the quotation expiry date.\r\n2. Payment terms as stated on this quotation.\r\n3. Delivery timelines commence after confirmation and advance payment (if applicable).\r\n4. Goods remain the property of VK Network until full payment is received.\r\n5. Warranty terms apply as per product/service specifications.\r\n6. VK Network reserves the right to revise prices if costs change after expiry.','',NULL,NULL,NULL,NULL,'Kilinochchi','Sales','Main Warehouse',1,1,'2026-08-07 06:51:56','2026-08-07 10:08:05'),(2,'QT-2026-000002',0,NULL,3,'CUS-00003','SLGTI','SLGTI','','','','',0.00,'','','LKR',1.000000,1,NULL,NULL,'','','2026-08-08','2026-09-07','','',30,'exclusive','draft','none',0,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,0.00,'','','1. Prices are valid until the quotation expiry date.\r\n2. Payment terms as stated on this quotation.\r\n3. Delivery timelines commence after confirmation and advance payment (if applicable).\r\n4. Goods remain the property of VK Network until full payment is received.\r\n5. Warranty terms apply as per product/service specifications.\r\n6. VK Network reserves the right to revise prices if costs change after expiry.','',NULL,NULL,NULL,NULL,'Kilinochchi','Sales','Main Warehouse',1,1,'2026-08-08 05:43:59',NULL);
/*!40000 ALTER TABLE `quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `remember_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `selector` char(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validator_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `idx_remember_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `remember_tokens`
--

LOCK TABLES `remember_tokens` WRITE;
/*!40000 ALTER TABLE `remember_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `remember_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repair_job_parts`
--

DROP TABLE IF EXISTS `repair_job_parts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `repair_job_parts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `repair_job_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rjp_job` (`repair_job_id`),
  KEY `idx_rjp_product` (`product_id`),
  CONSTRAINT `fk_rjp_job` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rjp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repair_job_parts`
--

LOCK TABLES `repair_job_parts` WRITE;
/*!40000 ALTER TABLE `repair_job_parts` DISABLE KEYS */;
/*!40000 ALTER TABLE `repair_job_parts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `repair_jobs`
--

DROP TABLE IF EXISTS `repair_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `repair_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `job_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `device_type` enum('computer','printer','cctv_dvr','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `problem_description` text COLLATE utf8mb4_unicode_ci,
  `accessories_received` text COLLATE utf8mb4_unicode_ci,
  `technician_id` int unsigned DEFAULT NULL,
  `printer_issue` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_template_id` int unsigned DEFAULT NULL,
  `estimated_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','diagnosing','in_progress','completed','delivered') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `technician_notes` text COLLATE utf8mb4_unicode_ci,
  `warranty_expiry` date DEFAULT NULL,
  `invoice_id` int unsigned DEFAULT NULL,
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
  KEY `fk_repair_tpl_v3` (`service_template_id`),
  CONSTRAINT `fk_repair_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_repair_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repair_tech` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repair_tech_v3` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repair_tpl` FOREIGN KEY (`service_template_id`) REFERENCES `service_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_repair_tpl_v3` FOREIGN KEY (`service_template_id`) REFERENCES `service_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repair_jobs`
--

LOCK TABLES `repair_jobs` WRITE;
/*!40000 ALTER TABLE `repair_jobs` DISABLE KEYS */;
INSERT INTO `repair_jobs` VALUES (1,'RJP-20260510-0001',2,'','[EMERGENCY SERVICE 24/7]\nno',NULL,NULL,NULL,NULL,0.00,'pending',NULL,NULL,NULL,9.3252126,80.4053728,1,NULL,'2026-05-10 08:50:53',NULL),(2,'RJP-20260808-0001',5,'','[EMERGENCY SERVICE 24/7]\nno',NULL,NULL,NULL,NULL,0.00,'pending',NULL,NULL,NULL,9.3252126,80.4053728,1,NULL,'2026-08-08 06:21:50',NULL);
/*!40000 ALTER TABLE `repair_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `role_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_name` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` tinyint unsigned NOT NULL DEFAULT '50',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_key` (`role_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7075 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','Super Admin',100,'2026-05-10 09:37:34'),(2,'admin','Admin',90,'2026-05-10 09:37:34'),(3,'manager','Manager',70,'2026-05-10 09:37:34'),(4,'technician','Technician',50,'2026-05-10 09:37:34'),(5,'staff','Staff',40,'2026-05-10 09:37:34'),(6,'viewer','Viewer',10,'2026-05-10 09:37:34');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seo_audit_checks`
--

DROP TABLE IF EXISTS `seo_audit_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_audit_checks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `check_type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pass','warning','fail') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `message` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `score_delta` smallint NOT NULL DEFAULT '0',
  `checked_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_seo_audit_page` (`page_key`),
  KEY `idx_seo_audit_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seo_audit_checks`
--

LOCK TABLES `seo_audit_checks` WRITE;
/*!40000 ALTER TABLE `seo_audit_checks` DISABLE KEYS */;
/*!40000 ALTER TABLE `seo_audit_checks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `seo_settings`
--

DROP TABLE IF EXISTS `seo_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seo_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_key` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meta_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `meta_description` text COLLATE utf8mb4_unicode_ci,
  `meta_keywords` text COLLATE utf8mb4_unicode_ci,
  `canonical_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `og_description` text COLLATE utf8mb4_unicode_ci,
  `og_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `twitter_card` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'summary_large_image',
  `schema_markup` json DEFAULT NULL,
  `robots_directive` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'index,follow',
  `seo_score` tinyint unsigned NOT NULL DEFAULT '0',
  `indexing_status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_key` (`page_key`),
  KEY `idx_seo_page_url` (`page_url`),
  KEY `idx_seo_score` (`seo_score`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `seo_settings`
--

LOCK TABLES `seo_settings` WRITE;
/*!40000 ALTER TABLE `seo_settings` DISABLE KEYS */;
INSERT INTO `seo_settings` VALUES (1,'home','/vk/index.php','VK IT Network | Repair, CCTV & Maintenance Services','Premium repair, CCTV, maintenance, warranty, and service management in Kilinochchi.','computer repair,cctv,maintenance,printer repair','/vk/index.php','VK IT Network','Repair, CCTV and maintenance service experts.',NULL,'summary_large_image','{\"name\": \"VK Network\", \"@type\": \"LocalBusiness\", \"@context\": \"https://schema.org\", \"areaServed\": \"Kilinochchi, Sri Lanka\"}','index,follow',88,'ready','2026-05-10 07:39:03','2026-05-10 07:39:03'),(2,'book','/vk/book.php','Book a Service | VK IT Network','Book repair, CCTV, maintenance, and technical support services online.','service booking,repair booking,cctv service','/vk/book.php','Book VK IT Service','Fast online service booking for customers.',NULL,'summary_large_image','{\"name\": \"VK Network\", \"@type\": \"LocalBusiness\", \"@context\": \"https://schema.org\", \"areaServed\": \"Kilinochchi, Sri Lanka\"}','index,follow',82,'ready','2026-05-10 07:39:03','2026-05-10 07:39:03');
/*!40000 ALTER TABLE `seo_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_gallery`
--

DROP TABLE IF EXISTS `service_gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_gallery` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text COLLATE utf8mb4_unicode_ci,
  `alt_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `seo_keywords` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int unsigned NOT NULL DEFAULT '0',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('published','hidden','draft') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `thumb_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `medium_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `width` int unsigned DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `uploaded_by` int unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `file_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_service_gallery_service` (`service_id`,`id`),
  KEY `idx_service_gallery_created` (`created_at`),
  KEY `idx_sg_status` (`status`,`created_at`),
  KEY `idx_sg_featured` (`is_featured`,`service_id`),
  KEY `idx_sg_hash` (`service_id`,`file_hash`),
  CONSTRAINT `fk_service_gallery_service` FOREIGN KEY (`service_id`) REFERENCES `web_services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_gallery`
--

LOCK TABLES `service_gallery` WRITE;
/*!40000 ALTER TABLE `service_gallery` DISABLE KEYS */;
INSERT INTO `service_gallery` VALUES (2,1,'uploads/services/gallery/service_1_g_1775550645048.webp','114829--01--1623926499',NULL,'2026-04-07 08:30:45',NULL,NULL,'general',NULL,0,0,'published',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `service_gallery` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_images`
--

DROP TABLE IF EXISTS `service_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_images` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_images_svc` (`service_id`,`sort_order`),
  CONSTRAINT `fk_service_images_template` FOREIGN KEY (`service_id`) REFERENCES `service_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_images`
--

LOCK TABLES `service_images` WRITE;
/*!40000 ALTER TABLE `service_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_template_versions`
--

DROP TABLE IF EXISTS `service_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_template_versions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int unsigned NOT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `snapshot_json` json NOT NULL,
  `change_log` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stv_template` (`template_id`,`version`),
  CONSTRAINT `fk_stv_template` FOREIGN KEY (`template_id`) REFERENCES `service_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_template_versions`
--

LOCK TABLES `service_template_versions` WRITE;
/*!40000 ALTER TABLE `service_template_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `service_template_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_templates`
--

DROP TABLE IF EXISTS `service_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
  `template_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive','draft','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `version` int unsigned NOT NULL DEFAULT '1',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_st_template_code` (`template_code`),
  KEY `idx_st_status` (`status`,`deleted_at`),
  KEY `idx_st_category` (`category`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_templates`
--

LOCK TABLES `service_templates` WRITE;
/*!40000 ALTER TABLE `service_templates` DISABLE KEYS */;
INSERT INTO `service_templates` VALUES (1,'Printer ??? Cartridge / toner service','printer',2500.00,'Cartridge check, cleaning, test print','uploads/services/service_1_1777456987112.webp','uploads/services/service_1_1777456987112_thumb.webp',NULL,NULL,NULL,'2026-04-12 07:53:44','PRINTER_CARTRIDGE_TONER_SERVICE',NULL,'active',0,1,NULL,NULL,'2026-06-28 04:27:19',NULL),(2,'Printer ??? Paper jam recovery','printer',1500.00,'Jam clear, roller inspection',NULL,NULL,NULL,NULL,NULL,'2026-04-12 07:53:44','PRINTER_PAPER_JAM_RECOVERY',NULL,'active',0,1,NULL,NULL,'2026-06-28 04:27:19',NULL),(3,'Printer ??? Roller replacement','printer',4500.00,'Pickup roller / roller kit labour',NULL,NULL,NULL,NULL,NULL,'2026-04-12 07:53:44','PRINTER_ROLLER_REPLACEMENT',NULL,'active',0,1,NULL,NULL,'2026-06-28 04:27:19',NULL),(4,'Printer ??? Ink refill','printer',2000.00,'Refill service',NULL,NULL,NULL,NULL,NULL,'2026-04-12 07:53:44','PRINTER_INK_REFILL',NULL,'active',0,1,NULL,NULL,'2026-06-28 04:27:19',NULL),(5,'Computer ??? Health check & cleaning','computer',3500.00,'Dust cleaning, thermal check, OS quick scan',NULL,NULL,NULL,NULL,NULL,'2026-04-12 07:53:44','COMPUTER_HEALTH_CHECK_CLEANING',NULL,'active',0,1,NULL,NULL,'2026-06-28 04:27:19',NULL),(6,'Computer ??? OS reinstall','computer',5000.00,'Backup advisory, OS install, drivers',NULL,NULL,NULL,NULL,NULL,'2026-04-12 07:53:44','COMPUTER_OS_REINSTALL',NULL,'active',0,1,NULL,NULL,'2026-06-28 04:27:19',NULL),(7,'CCTV ??? Maintenance visit','cctv',4000.00,'Lens clean, cable check, recording test',NULL,NULL,NULL,NULL,NULL,'2026-04-12 07:53:44','CCTV_MAINTENANCE_VISIT',NULL,'active',0,1,NULL,NULL,'2026-06-28 04:27:19',NULL),(8,'💻 Computer Repair','computer',0.00,NULL,'uploads/services/service_8_1777457740221.webp','uploads/services/service_8_1777457740221_thumb.webp',NULL,NULL,NULL,'2026-04-29 10:15:39','COMPUTER_REPAIR',NULL,'active',0,1,NULL,NULL,'2026-06-28 04:27:19',NULL);
/*!40000 ALTER TABLE `service_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `key_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `setting_group` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `setting_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key_name`),
  KEY `idx_settings_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=1726 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'site_name','VK Network','general','text','2026-05-10 05:55:01','2026-04-29 09:42:00'),(2,'seo_site_title','','general','text','2026-05-10 05:55:01',NULL),(3,'seo_meta_description','Professional computer, printer, CCTV, maintenance, and field repair services in Kilinochchi and across Sri Lanka — VK Network.','general','text','2026-05-10 05:55:01',NULL),(4,'seo_meta_keywords','computer repair, laptop service, printer repair, CCTV installation, Sri Lanka, Kilinochchi, VK Network','general','text','2026-05-10 05:55:01',NULL),(5,'seo_og_image','','general','text','2026-05-10 05:55:01',NULL),(6,'seo_auto_enabled','1','general','text','2026-05-10 05:55:01',NULL),(7,'seo_locations','jaffna,vavuniya,kilinochchi','general','text','2026-05-10 05:55:01',NULL),(8,'seo_service_slugs','computer-repair,laptop-repair,printer-repair,it-service','general','text','2026-05-10 05:55:01',NULL),(9,'whatsapp_number','','contact','text','2026-05-10 05:55:01','2026-07-05 07:12:33'),(10,'whatsapp_default_message','Hello VK Network, I would like to inquire about your services.','general','text','2026-05-10 05:55:01',NULL),(11,'analytics_domain','www.vkitnet.info','general','text','2026-05-10 05:55:01','2026-04-29 09:42:00'),(12,'analytics_script_src','https://plausible.io/js/script.js','general','text','2026-05-10 05:55:01','2026-04-29 09:42:00'),(13,'smtp_host','','general','text','2026-05-10 05:55:01',NULL),(14,'smtp_port','587','general','text','2026-05-10 05:55:01',NULL),(15,'smtp_username','','general','text','2026-05-10 05:55:01',NULL),(16,'smtp_password','','general','text','2026-05-10 05:55:01',NULL),(17,'email_from','','general','text','2026-05-10 05:55:01',NULL),(22,'company_name','VK Network','general','text','2026-05-10 05:55:01',NULL),(23,'site_title','VK Network','general','text','2026-05-10 05:55:01',NULL),(24,'company_tagline','Multi-Service Solutions','general','text','2026-05-10 05:55:01',NULL),(25,'business_slogan','Premium local service operations for homes and businesses.','general','textarea','2026-05-10 05:55:01',NULL),(26,'site_logo','','branding','image','2026-05-10 05:55:01',NULL),(27,'site_logo_dark','','branding','image','2026-05-10 05:55:01',NULL),(28,'site_logo_light','','branding','image','2026-05-10 05:55:01',NULL),(29,'mobile_logo','','branding','image','2026-05-10 05:55:01',NULL),(30,'site_favicon','','branding','image','2026-05-10 05:55:01',NULL),(31,'navbar_cta_text','Book Service','navigation','text','2026-05-10 05:55:01',NULL),(32,'navbar_cta_url','/book.php','navigation','url','2026-05-10 05:55:01',NULL),(33,'announcement_enabled','0','navigation','boolean','2026-05-10 05:55:01',NULL),(34,'announcement_text','','navigation','text','2026-05-10 05:55:01',NULL),(35,'announcement_url','','navigation','url','2026-05-10 05:55:01',NULL),(36,'contact_phone','0705886782','contact','text','2026-05-10 05:55:01','2026-07-05 07:12:33'),(37,'contact_phone_alt','','contact','text','2026-05-10 05:55:01','2026-07-05 07:12:33'),(38,'support_email','','contact','email','2026-05-10 05:55:01','2026-07-05 07:12:33'),(39,'sales_email','','contact','email','2026-05-10 05:55:01','2026-07-05 07:12:33'),(40,'company_address','Kilinochchi, Sri Lanka','contact','textarea','2026-05-10 05:55:01','2026-07-05 07:12:33'),(41,'business_hours','Mon - Sat: 8:00 AM - 7:00 PM','contact','textarea','2026-05-10 05:55:01','2026-07-05 07:12:33'),(42,'google_maps_embed','','contact','textarea','2026-05-10 05:55:01','2026-07-05 07:12:33'),(43,'branches_json','[]','contact','json','2026-05-10 05:55:01','2026-07-05 07:12:33'),(46,'facebook_url','','social','url','2026-05-10 05:55:01',NULL),(47,'instagram_url','','social','url','2026-05-10 05:55:01',NULL),(48,'linkedin_url','','social','url','2026-05-10 05:55:01',NULL),(49,'tiktok_url','','social','url','2026-05-10 05:55:01',NULL),(50,'youtube_url','','social','url','2026-05-10 05:55:01',NULL),(51,'twitter_url','','social','url','2026-05-10 05:55:01',NULL),(52,'hero_title','Premium Multi-Service Support, Built Around Trust.','homepage','text','2026-05-10 05:55:01',NULL),(53,'hero_subtitle','Book repairs, installations, maintenance, and technical support with real-time tracking and intelligent workflow management.','homepage','textarea','2026-05-10 05:55:01',NULL),(54,'hero_primary_cta_text','Book a Service','homepage','text','2026-05-10 05:55:01',NULL),(55,'hero_primary_cta_url','/book.php','homepage','url','2026-05-10 05:55:01',NULL),(56,'hero_secondary_cta_text','Track My Job','homepage','text','2026-05-10 05:55:01',NULL),(57,'hero_secondary_cta_url','/track.php','homepage','url','2026-05-10 05:55:01',NULL),(58,'home_stats_json','[]','homepage','json','2026-05-10 05:55:01',NULL),(59,'services_section_title','Services designed for modern homes and businesses.','homepage','text','2026-05-10 05:55:01',NULL),(60,'services_section_subtitle','A single trusted team for technology, maintenance, installations, and rapid field support.','homepage','textarea','2026-05-10 05:55:01',NULL),(61,'testimonials_title','What customers say after the job is done.','homepage','text','2026-05-10 05:55:01',NULL),(62,'footer_text','Premium local service operations with transparent booking, tracking, and field support for homes and businesses.','footer','textarea','2026-05-10 05:55:01',NULL),(63,'footer_bottom_text','Made with care in Sri Lanka','footer','text','2026-05-10 05:55:01',NULL),(68,'seo_twitter_image','','seo','image','2026-05-10 05:55:01',NULL),(69,'seo_canonical_url','','seo','url','2026-05-10 05:55:01',NULL),(70,'seo_schema_markup','','seo','textarea','2026-05-10 05:55:01',NULL),(71,'robots_txt','User-agent: *\nAllow: /','seo','textarea','2026-05-10 05:55:01',NULL),(72,'theme_primary','#3b82f6','theme','color','2026-05-10 05:55:01',NULL),(73,'theme_secondary','#14b8a6','theme','color','2026-05-10 05:55:01',NULL),(74,'theme_accent','#a78bfa','theme','color','2026-05-10 05:55:01',NULL),(75,'theme_gradient_start','#1e3a8a','theme','color','2026-05-10 05:55:01',NULL),(76,'theme_gradient_end','#7c3aed','theme','color','2026-05-10 05:55:01',NULL),(77,'theme_glow','#38bdf8','theme','color','2026-05-10 05:55:01',NULL),(78,'button_style','pill','theme','select','2026-05-10 05:55:01',NULL),(79,'card_style','glass','theme','select','2026-05-10 05:55:01',NULL),(80,'security_maintenance_mode','0','security','boolean','2026-05-10 05:55:01',NULL),(81,'security_readonly_staff','1','security','boolean','2026-05-10 05:55:01',NULL),(88,'smtp_secure','tls','email','select','2026-05-10 05:55:01',NULL),(90,'from_name','VK Network','email','text','2026-05-10 05:55:01',NULL),(91,'email_autoresponder_enabled','0','email','boolean','2026-05-10 05:55:01',NULL),(92,'email_autoresponder_subject','Thank you for contacting VK Network','email','text','2026-05-10 05:55:01',NULL),(93,'email_autoresponder_body','Thanks for contacting us. Our team will reply soon.','email','textarea','2026-05-10 05:55:01',NULL),(464,'company_logo','','branding','image','2026-05-10 06:47:23',NULL);
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings_audit_log`
--

DROP TABLE IF EXISTS `settings_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings_audit_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_key` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value_preview` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_settings_audit_created` (`created_at`),
  KEY `idx_settings_audit_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings_audit_log`
--

LOCK TABLES `settings_audit_log` WRITE;
/*!40000 ALTER TABLE `settings_audit_log` DISABLE KEYS */;
INSERT INTO `settings_audit_log` VALUES (1,1,'save','contact_phone','0705886782','::1','2026-07-05 07:12:33'),(2,1,'save','contact_phone_alt','','::1','2026-07-05 07:12:33'),(3,1,'save','support_email','','::1','2026-07-05 07:12:33'),(4,1,'save','sales_email','','::1','2026-07-05 07:12:33'),(5,1,'save','whatsapp_number','','::1','2026-07-05 07:12:33'),(6,1,'save','business_hours','Mon - Sat: 8:00 AM - 7:00 PM','::1','2026-07-05 07:12:33'),(7,1,'save','company_address','Kilinochchi, Sri Lanka','::1','2026-07-05 07:12:33'),(8,1,'save','google_maps_embed','','::1','2026-07-05 07:12:33'),(9,1,'save','branches_json','[]','::1','2026-07-05 07:12:33'),(10,1,'export',NULL,'','::1','2026-08-08 06:23:52');
/*!40000 ALTER TABLE `settings_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `smtp_settings`
--

DROP TABLE IF EXISTS `smtp_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `smtp_settings` (
  `id` tinyint unsigned NOT NULL,
  `smtp_host` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `smtp_port` int unsigned NOT NULL DEFAULT '587',
  `smtp_user` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `smtp_pass` text COLLATE utf8mb4_unicode_ci,
  `smtp_secure` enum('tls','ssl') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tls',
  `from_email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `smtp_settings`
--

LOCK TABLES `smtp_settings` WRITE;
/*!40000 ALTER TABLE `smtp_settings` DISABLE KEYS */;
INSERT INTO `smtp_settings` VALUES (1,'vkitnet.info',465,'info@vkitnet.info','$#eZ9E6VUD,6','ssl','info@vkitnet.info','VK IT Network','2026-04-12 06:57:13');
/*!40000 ALTER TABLE `smtp_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_thumb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `skills` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `experience` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `years_experience` int unsigned DEFAULT NULL,
  `completed_projects` int unsigned DEFAULT NULL,
  `specialization` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certifications` text COLLATE utf8mb4_unicode_ci,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_links` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','on_leave') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_active_sort` (`active`,`sort_order`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff`
--

LOCK TABLES `staff` WRITE;
/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
INSERT INTO `staff` VALUES (1,'vijaykumar keerththeejan','Owner','uploads/staff/staff-20260510044733-c3e51161.jpg',NULL,'Web Development  Networking Hardware Repair CCTVNetwork Management Security',NULL,'7+',NULL,NULL,NULL,NULL,'Keerththeejan@gmail.com','0778870135','https://www.linkedin.com/in/keerththi-keerththeejamn-8a83b717b/','active',1,1,'2026-04-29 07:25:13','2026-05-10 04:47:33');
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `technicians`
--

DROP TABLE IF EXISTS `technicians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `technicians` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `technicians`
--

LOCK TABLES `technicians` WRITE;
/*!40000 ALTER TABLE `technicians` DISABLE KEYS */;
INSERT INTO `technicians` VALUES (1,'Lead Technician','0778870135','All systems',1,NULL,NULL,'available','2026-04-12 07:53:44');
/*!40000 ALTER TABLE `technicians` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fullname` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_uid` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('super_admin','admin','manager','technician','staff','viewer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'viewer',
  `technician_id` int unsigned DEFAULT NULL,
  `status` enum('pending','approved','active','rejected','suspended','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved` tinyint(1) NOT NULL DEFAULT '0',
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `registration_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_uid` (`user_uid`),
  KEY `fk_users_technician` (`technician_id`),
  KEY `idx_users_status_role` (`status`,`role`),
  CONSTRAINT `fk_users_technician` FOREIGN KEY (`technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin',NULL,NULL,'$2y$10$dLw60EEU/LS0xpHi4k0Qgu3f3VHIOQBf/.dg/y/vgMyKE9WM//VPa','Administrator',NULL,NULL,'admin',NULL,'active',1,NULL,NULL,NULL,'2026-08-08 11:02:12',NULL,'2026-04-12 07:52:42','2026-08-08 11:02:12');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_bookings`
--

DROP TABLE IF EXISTS `vehicle_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_bookings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `booking_ref` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `booking_type` enum('rental','hire') COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_id` int unsigned DEFAULT NULL,
  `driver_id` int unsigned DEFAULT NULL,
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
  `passengers` int unsigned NOT NULL DEFAULT '1',
  `distance_km` decimal(10,2) NOT NULL DEFAULT '0.00',
  `rental_days` int unsigned NOT NULL DEFAULT '1',
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
  KEY `fk_vehicle_bookings_driver` (`driver_id`),
  CONSTRAINT `fk_vehicle_bookings_customer` FOREIGN KEY (`customer_id`) REFERENCES `vehicle_customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vehicle_bookings_driver` FOREIGN KEY (`driver_id`) REFERENCES `vehicle_drivers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vehicle_bookings_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_bookings`
--

LOCK TABLES `vehicle_bookings` WRITE;
/*!40000 ALTER TABLE `vehicle_bookings` DISABLE KEYS */;
INSERT INTO `vehicle_bookings` VALUES (1,'VB-SAMPLE-001',1,'rental',1,NULL,'confirmed','Kilinochchi Bus Stand',9.3961000,80.3982000,NULL,NULL,NULL,'2026-04-09 09:00:00','2026-04-11 09:00:00','car',4,0.00,2,12500.00,0.00,25000.00,'Sample rental booking','2026-04-08 10:09:28',NULL),(2,'VB-SAMPLE-002',1,'hire',2,2,'pending','Jaffna Town',9.6615000,80.0255000,'Kilinochchi Central',9.3803000,80.3761000,'2026-04-10 08:30:00','2026-04-10 18:30:00','van',6,62.50,1,260.00,3500.00,19750.00,'Sample hire booking with driver','2026-04-08 10:09:28',NULL);
/*!40000 ALTER TABLE `vehicle_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_customers`
--

DROP TABLE IF EXISTS `vehicle_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_customers`
--

LOCK TABLES `vehicle_customers` WRITE;
/*!40000 ALTER TABLE `vehicle_customers` DISABLE KEYS */;
INSERT INTO `vehicle_customers` VALUES (1,'Demo Customer','0777001122','demo@vkvehicle.local',NULL,'$2y$10$dpoLZqrNk79dZGsWKURaN.YfABuIgFLqkAY.y59kpOHxwqw3UkM7G','2026-04-08 10:09:28'),(2,'Rasenthiram Pavuthira','0798645352','emmentagrossist@gmail.com','Schwandgasse 16','$2y$10$m7e6L1AST23R2ij/G3M2hui2N5lqXNDGQjakqdw8VWVtMo5D56.MK','2026-04-08 10:14:58'),(3,'user','0778870135','keerththeejan@gmail.com','Kilinochchi','$2y$10$WJsDduyDkFhn7oIHcTE4iOWi1hjSOCnLustU7uu4N1JYKt.tuAcU6','2026-04-12 07:18:13');
/*!40000 ALTER TABLE `vehicle_customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_drivers`
--

DROP TABLE IF EXISTS `vehicle_drivers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicle_drivers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `availability` enum('available','on_trip','off_duty') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicle_drivers_license` (`license_number`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_drivers`
--

LOCK TABLES `vehicle_drivers` WRITE;
/*!40000 ALTER TABLE `vehicle_drivers` DISABLE KEYS */;
INSERT INTO `vehicle_drivers` VALUES (1,'K. Suresh','0777123456','B4512268','available',1,'2026-04-08 10:09:28'),(2,'T. Nimalan','0777456789','B4528931','available',1,'2026-04-08 10:09:28'),(3,'R. Arul','0777987654','B4581022','off_duty',1,'2026-04-08 10:09:28');
/*!40000 ALTER TABLE `vehicle_drivers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vehicles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vehicle_type` enum('car','van','bike','lorry','bus') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'car',
  `registration_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_per_day` decimal(12,2) NOT NULL DEFAULT '0.00',
  `price_per_km` decimal(12,2) NOT NULL DEFAULT '0.00',
  `default_driver_charge` decimal(12,2) NOT NULL DEFAULT '0.00',
  `assigned_driver_id` int unsigned DEFAULT NULL,
  `status` enum('available','booked','maintenance') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seats` int unsigned NOT NULL DEFAULT '4',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicles_reg` (`registration_number`),
  KEY `idx_vehicles_type_status` (`vehicle_type`,`status`),
  KEY `fk_vehicles_driver` (`assigned_driver_id`),
  CONSTRAINT `fk_vehicles_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `vehicle_drivers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicles`
--

LOCK TABLES `vehicles` WRITE;
/*!40000 ALTER TABLE `vehicles` DISABLE KEYS */;
INSERT INTO `vehicles` VALUES (1,'Toyota Prius','car','CAA-4389',12500.00,180.00,2500.00,1,'available','assets/images/services/automobile.svg',4,'2026-04-08 10:09:28',NULL),(2,'Nissan Caravan','van','NCB-7721',18500.00,260.00,3500.00,2,'available','assets/images/services/maintenance.svg',12,'2026-04-08 10:09:28',NULL),(3,'Bajaj CT100','bike','BKE-1902',3500.00,65.00,0.00,NULL,'available','assets/images/services/default.svg',2,'2026-04-08 10:09:28',NULL),(4,'Isuzu Lorry','lorry','LRY-5104',26500.00,420.00,5500.00,3,'maintenance','assets/images/services/electrical.svg',3,'2026-04-08 10:09:28',NULL);
/*!40000 ALTER TABLE `vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warranty_records`
--

DROP TABLE IF EXISTS `warranty_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warranty_records` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warranty_type` enum('service','product') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'service',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `repair_job_id` int unsigned DEFAULT NULL,
  `cctv_installation_id` int unsigned DEFAULT NULL,
  `invoice_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_warranty_customer` (`customer_id`),
  KEY `idx_warranty_end` (`end_date`),
  KEY `fk_warranty_repair_v3` (`repair_job_id`),
  KEY `fk_warranty_cctv_v3` (`cctv_installation_id`),
  KEY `fk_warranty_invoice_v3` (`invoice_id`),
  CONSTRAINT `fk_warranty_cctv_v3` FOREIGN KEY (`cctv_installation_id`) REFERENCES `cctv_installations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_warranty_customer_v3` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_warranty_invoice_v3` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_warranty_repair_v3` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warranty_records`
--

LOCK TABLES `warranty_records` WRITE;
/*!40000 ALTER TABLE `warranty_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `warranty_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `web_bookings`
--

DROP TABLE IF EXISTS `web_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_bookings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
  `assigned_technician_id` int unsigned DEFAULT NULL,
  `assignment_distance_km` decimal(10,3) DEFAULT NULL,
  `repair_job_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  KEY `idx_web_booking_status` (`status`),
  KEY `idx_web_booking_emergency` (`is_emergency`),
  KEY `fk_web_booking_tech` (`assigned_technician_id`),
  KEY `fk_web_booking_repair` (`repair_job_id`),
  CONSTRAINT `fk_web_booking_repair` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_web_booking_tech` FOREIGN KEY (`assigned_technician_id`) REFERENCES `technicians` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `web_bookings`
--

LOCK TABLES `web_bookings` WRITE;
/*!40000 ALTER TABLE `web_bookings` DISABLE KEYS */;
INSERT INTO `web_bookings` VALUES (1,'BK-20260510-0001','vijaykumar keerththeejan','0778870135','keerththeejan@gmail.com','Kilinochchi','cctv','notworking','2026-05-10','uploads/bookings/bk_ce5c1f6f3efa2f9d.png',9.3840068,80.4087224,0,'pending',NULL,0.00,NULL,NULL,NULL,'2026-05-10 08:05:17',NULL),(2,'BK-20260510-0002','vijaykumar keerththeejan','+94778870135','keerththeejan@gmail.com','Kilinochchi','electrical','[EMERGENCY SERVICE 24/7]\nno','2026-05-10',NULL,9.3252126,80.4053728,1,'completed',NULL,0.00,NULL,NULL,1,'2026-05-10 08:50:30','2026-08-07 10:18:49'),(3,'BK-20260510-0003','vijaykumar keerththeejan','+94778870135','keerththeejan@gmail.com','Kilinochchi','electrical','[EMERGENCY SERVICE 24/7]\nno','2026-05-10',NULL,9.3252126,80.4053728,1,'completed',NULL,0.00,NULL,NULL,2,'2026-05-10 09:16:29','2026-08-08 06:21:50'),(4,'BK-20260713-0001','vijaykumar keerththeejan','+94778870135',NULL,'Kilinochchi','computer','0778870135','2026-07-13',NULL,9.3284762,80.4063606,0,'pending',NULL,0.00,NULL,NULL,NULL,'2026-07-13 11:54:22',NULL);
/*!40000 ALTER TABLE `web_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `web_portfolio_images`
--

DROP TABLE IF EXISTS `web_portfolio_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_portfolio_images` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int unsigned NOT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_role` enum('before','after','general') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_portfolio_img_post` (`post_id`),
  CONSTRAINT `fk_portfolio_img_post` FOREIGN KEY (`post_id`) REFERENCES `web_portfolio_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `web_portfolio_images`
--

LOCK TABLES `web_portfolio_images` WRITE;
/*!40000 ALTER TABLE `web_portfolio_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `web_portfolio_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `web_portfolio_posts`
--

DROP TABLE IF EXISTS `web_portfolio_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_portfolio_posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `published` tinyint(1) NOT NULL DEFAULT '0',
  `display_date` date NOT NULL,
  `repair_job_id` int unsigned DEFAULT NULL,
  `cctv_job_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_portfolio_pub` (`published`,`display_date`),
  KEY `fk_portfolio_repair` (`repair_job_id`),
  KEY `fk_portfolio_cctv` (`cctv_job_id`),
  CONSTRAINT `fk_portfolio_cctv` FOREIGN KEY (`cctv_job_id`) REFERENCES `cctv_installations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_portfolio_repair` FOREIGN KEY (`repair_job_id`) REFERENCES `repair_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `web_portfolio_posts`
--

LOCK TABLES `web_portfolio_posts` WRITE;
/*!40000 ALTER TABLE `web_portfolio_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `web_portfolio_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `web_service_images`
--

DROP TABLE IF EXISTS `web_service_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_service_images` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `image_path` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_web_svc_img_sort` (`service_id`,`sort_order`),
  CONSTRAINT `fk_web_svc_img_service` FOREIGN KEY (`service_id`) REFERENCES `web_services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `web_service_images`
--

LOCK TABLES `web_service_images` WRITE;
/*!40000 ALTER TABLE `web_service_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `web_service_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `web_services`
--

DROP TABLE IF EXISTS `web_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_services` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `web_services`
--

LOCK TABLES `web_services` WRITE;
/*!40000 ALTER TABLE `web_services` DISABLE KEYS */;
/*!40000 ALTER TABLE `web_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_logs`
--

DROP TABLE IF EXISTS `whatsapp_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `whatsapp_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int DEFAULT NULL,
  `campaign_id` int DEFAULT NULL,
  `phone` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_preview` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direction` enum('outbound','inbound') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'outbound',
  `status` enum('queued','sent','delivered','read','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `provider_message_id` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_whatsapp_status` (`status`),
  KEY `idx_whatsapp_campaign` (`campaign_id`),
  KEY `idx_whatsapp_phone` (`phone`),
  CONSTRAINT `fk_whatsapp_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `marketing_campaigns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_logs`
--

LOCK TABLES `whatsapp_logs` WRITE;
/*!40000 ALTER TABLE `whatsapp_logs` DISABLE KEYS */;
INSERT INTO `whatsapp_logs` VALUES (1,NULL,NULL,'0778870135','booking_confirmation','Hello {{customer_name}}, your VK IT service update is ready.','outbound','queued',NULL,'2026-08-08 11:47:47',NULL,NULL,'2026-08-08 06:17:47');
/*!40000 ALTER TABLE `whatsapp_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'vk_billing'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-08 11:59:12
