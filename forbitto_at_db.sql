-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: forbitto_at_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `account_transactions`
--

DROP TABLE IF EXISTS `account_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('credit','debit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reference_type` varchar(50) NOT NULL,
  `reference_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `account_transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `financial_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_transactions`
--

LOCK TABLES `account_transactions` WRITE;
/*!40000 ALTER TABLE `account_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(10) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_table` (`table_name`,`record_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-04 09:01:48'),(2,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','2026-09-04 09:06:06'),(3,1,'create','products',2,NULL,NULL,'Created product: test (SKU: TES-0F1FA2)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-04 09:18:57'),(4,1,'update_user','Updated user: Cashier User (staff)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-04 09:27:57'),(5,3,'login','auth',3,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-04 09:28:08'),(6,3,'login','auth',3,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-04 09:28:31'),(7,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-04 09:28:45'),(8,1,'create','contacts',5,NULL,NULL,'Created new supplier: Nur Mubin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-04 12:00:29'),(9,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 06:23:09'),(10,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 07:58:09'),(11,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 12:14:41'),(12,1,'create','sales',2,NULL,NULL,'Sale Created: INV-20260905-FC08A1','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 12:52:15'),(13,1,'create','purchases',2,NULL,NULL,'Purchase Created: PO-20260905-5903','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 12:55:42'),(14,1,'create','purchases',3,NULL,NULL,'Purchase Created: PO-20260905-2414','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 12:56:41'),(15,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 17:33:03'),(16,1,'update_user','Updated user: MD Taufik Alam (admin)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 17:41:55'),(17,1,'update_user','Updated user: MD Taufik Alam (admin)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 17:42:38'),(18,1,'update_user','Updated user: MD Taufik Alam (admin)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 17:43:01'),(19,1,'update_settings','settings',NULL,NULL,'{\"company_name\":\"MT Int.\",\"company_email\":\"admin@example.com\",\"company_phone\":\"01723219162\",\"currency_symbol\":\"\\u09f3\",\"timezone\":\"Asia\\/Dhaka\",\"weather_city\":\"Joypurhat|25.1011|89.0262\",\"tax_id\":\"\",\"default_tax_rate\":\"0\",\"company_address\":\"Durgadoho, Bangladesh\"}','Updated system settings','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 18:13:09'),(20,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 19:50:45'),(21,1,'create_user','users',4,NULL,NULL,'Created user: dzdf (staff)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:21:20'),(22,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-05 20:21:56'),(23,1,'update_user','Updated user: dzdf (staff)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:23:36'),(24,4,'login','auth',4,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-05 20:23:42'),(25,1,'update_user','Updated user: dzdf (staff)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:25:26'),(26,1,'update_user','Updated user: dzdf (cashier)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:25:52'),(27,1,'update_user','Updated user: dzdf (sales)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:26:52'),(28,1,'update_user','Updated user: dzdf (manager)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:27:33'),(29,1,'update_user','Updated user: dzdf (admin)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:28:18'),(30,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:30:55'),(31,1,'update_user','Updated user: dzdf (staff)',NULL,NULL,NULL,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-05 20:45:24'),(32,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-05 20:45:36'),(33,4,'login','auth',4,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-05 20:46:10'),(34,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-05 22:13:26'),(35,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-05 23:08:13'),(36,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 00:47:21'),(37,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 09:57:58'),(38,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0','2026-09-06 10:24:54'),(39,1,'update_settings','settings',NULL,NULL,'{\"company_name\":\"Aroshy Traders\",\"company_email\":\"admin@example.com\",\"company_phone\":\"01723219162\",\"currency_symbol\":\"\\u09f3\",\"timezone\":\"Asia\\/Dhaka\",\"weather_city\":\"Joypurhat|25.1011|89.0262\",\"tax_id\":\"\",\"default_tax_rate\":\"0\",\"company_address\":\"Durgadoho, Bangladesh\"}','Updated system settings','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-06 13:15:14'),(40,1,'login','auth',1,NULL,NULL,'User logged in successfully','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-20 11:04:34');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (1,'Head Office','Main Address',NULL,'2026-09-05 09:52:24'),(2,'1','egg','00000000000','2026-09-05 20:20:09');
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `capital_transactions`
--

DROP TABLE IF EXISTS `capital_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `capital_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trans_date` date NOT NULL,
  `investor_id` int(10) unsigned NOT NULL,
  `type` enum('injection','withdrawal') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('cash','bank','mobile') DEFAULT 'cash',
  `reference` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `investor_id` (`investor_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `capital_transactions_ibfk_1` FOREIGN KEY (`investor_id`) REFERENCES `contacts` (`id`),
  CONSTRAINT `capital_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `capital_transactions`
--

LOCK TABLES `capital_transactions` WRITE;
/*!40000 ALTER TABLE `capital_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `capital_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Electronics','Electronic Devices',1,'2026-09-04 11:09:47'),(2,'Accessories','Gadget Accessories',1,'2026-09-04 11:09:47');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chart_of_accounts`
--

DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chart_of_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chart_of_accounts`
--

LOCK TABLES `chart_of_accounts` WRITE;
/*!40000 ALTER TABLE `chart_of_accounts` DISABLE KEYS */;
INSERT INTO `chart_of_accounts` VALUES (1,'1000','Cash','asset',1,'2026-09-05 12:21:52'),(2,'1010','Bank','asset',1,'2026-09-05 12:21:52'),(3,'1200','Accounts Receivable','asset',1,'2026-09-05 12:21:52'),(4,'1300','Inventory','asset',1,'2026-09-05 12:21:52'),(5,'2000','Accounts Payable','liability',1,'2026-09-05 12:21:52'),(6,'3000','Owners Equity','equity',1,'2026-09-05 12:21:52'),(7,'4000','Sales Revenue','revenue',1,'2026-09-05 12:21:52'),(8,'5000','Cost of Goods Sold','expense',1,'2026-09-05 12:21:52'),(9,'5100','General Expenses','expense',1,'2026-09-05 12:21:52'),(10,'5200','Discounts Given','expense',1,'2026-09-05 12:21:52'),(12,'1020','Bank Account','asset',1,'2026-09-05 12:39:22'),(15,'2100','Accounts Payable','liability',1,'2026-09-05 12:39:23'),(16,'2500','Bank Loan','liability',1,'2026-09-05 12:39:23'),(17,'3100','Owners Capital','equity',1,'2026-09-05 12:39:23'),(18,'3200','Retained Earnings','equity',1,'2026-09-05 12:39:23'),(19,'4100','Sales Revenue','revenue',1,'2026-09-05 12:39:23'),(20,'4200','Other Income','revenue',1,'2026-09-05 12:39:23'),(23,'5300','Discount Given','expense',1,'2026-09-05 12:39:23');
/*!40000 ALTER TABLE `chart_of_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('customer','supplier','investor') NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `credit_limit` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `opening_balance` decimal(12,2) DEFAULT 0.00,
  `balance` decimal(10,2) DEFAULT 0.00,
  `opening_balance_date` date DEFAULT NULL,
  `email_opt_in` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES (1,'customer','Walk-in Customer','',NULL,'0000',NULL,'',0.00,'2026-09-04 11:09:47',0.00,0.00,NULL,1),(2,'customer','Acme Corp','acme@example.com',NULL,'01711111111',NULL,'Dhaka',0.00,'2026-09-04 11:09:47',5000.00,130000.00,NULL,1),(3,'supplier','TechWholesale','tech@example.com',NULL,'01811111111',NULL,'Dhaka',0.00,'2026-09-04 11:09:47',2000.00,17000.00,NULL,1),(4,'investor','John Doe','john@example.com',NULL,'01911111111',NULL,'Dhaka',0.00,'2026-09-04 11:09:47',100000.00,100000.00,NULL,1),(5,'supplier','Nur Mubin','nurmubin006@gmail.com',NULL,'','gfxmg','2',0.00,'2026-09-04 12:00:29',0.00,0.00,NULL,1);
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category` varchar(100) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`expense_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,'Rent',15000.00,NULL,'Office Rent','2026-09-04','2026-09-04 11:09:47',1);
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_accounts`
--

DROP TABLE IF EXISTS `financial_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_type` enum('cash','bank','mobile_banking') NOT NULL DEFAULT 'cash',
  `opening_balance` decimal(15,2) DEFAULT 0.00,
  `balance` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_accounts`
--

LOCK TABLES `financial_accounts` WRITE;
/*!40000 ALTER TABLE `financial_accounts` DISABLE KEYS */;
INSERT INTO `financial_accounts` VALUES (1,'Cash','CASH-01','cash',50000.00,366000.00,1,'2026-09-04 11:09:46','2026-09-05 12:55:42'),(2,'Bank (City Bank)','ACC-1234','bank',500000.00,240000.00,1,'2026-09-04 11:09:47','2026-09-04 11:09:47');
/*!40000 ALTER TABLE `financial_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_periods`
--

DROP TABLE IF EXISTS `financial_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_periods`
--

LOCK TABLES `financial_periods` WRITE;
/*!40000 ALTER TABLE `financial_periods` DISABLE KEYS */;
INSERT INTO `financial_periods` VALUES (1,'FY 2026','2026-01-01','2026-12-31',0,'2026-09-05 17:52:03');
/*!40000 ALTER TABLE `financial_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `financial_transactions`
--

DROP TABLE IF EXISTS `financial_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financial_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `financial_account_id` int(11) NOT NULL,
  `transaction_type` enum('credit','debit') NOT NULL COMMENT 'credit = money in, debit = money out',
  `amount` decimal(15,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'sale, purchase, expense, opening_balance, etc',
  `reference_id` int(11) DEFAULT NULL COMMENT 'ID of the related record',
  `description` text DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_account` (`financial_account_id`),
  KEY `idx_date` (`transaction_date`),
  KEY `idx_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_transactions`
--

LOCK TABLES `financial_transactions` WRITE;
/*!40000 ALTER TABLE `financial_transactions` DISABLE KEYS */;
INSERT INTO `financial_transactions` VALUES (1,2,'debit',260000.00,'purchase',1,'Payment for Purchase PO-1001','2026-08-30',1,'2026-09-04 11:09:47'),(2,1,'credit',100000.00,'sale',1,'Payment for Sale INV-2001','2026-09-02',1,'2026-09-04 11:09:47'),(3,1,'debit',15000.00,'expense',1,'Office Rent','2026-09-04',1,'2026-09-04 11:09:47'),(4,1,'debit',500.00,'expense',1,'Test Expense Journal','2026-09-05',1,'2026-09-05 12:24:30'),(5,1,'credit',1000.00,'sale',1,'Test Sale Journal','2026-09-05',1,'2026-09-05 12:24:30'),(6,1,'credit',500.00,'sale',101,'Test Payment for Sale','2026-09-05',1,'2026-09-05 12:42:10'),(7,1,'credit',245000.00,'sale',2,'Sale Payment Ref: INV-20260905-FC08A1','2026-09-05',1,'2026-09-05 12:52:15'),(8,1,'debit',15000.00,'purchase',2,'Purchase Payment Ref: PO-20260905-5903','2026-09-05',1,'2026-09-05 12:55:42');
/*!40000 ALTER TABLE `financial_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `branch_id` int(11) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entries`
--

LOCK TABLES `journal_entries` WRITE;
/*!40000 ALTER TABLE `journal_entries` DISABLE KEYS */;
INSERT INTO `journal_entries` VALUES (1,'2026-09-05','expense',1,'Auto-Journal: Test Expense Journal',1,0,'2026-09-05 12:24:30'),(2,'2026-09-05','sale',1,'Auto-Journal: Test Sale Journal',1,0,'2026-09-05 12:24:30'),(3,'2026-09-05','sale',101,'Test Payment for Sale',1,NULL,'2026-09-05 12:42:10'),(4,'2026-09-05','sale',2,'Sale Payment Ref: INV-20260905-FC08A1',1,NULL,'2026-09-05 12:52:15'),(5,'2026-09-05','purchase',2,'Purchase Payment Ref: PO-20260905-5903',1,NULL,'2026-09-05 12:55:42');
/*!40000 ALTER TABLE `journal_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entry_lines`
--

DROP TABLE IF EXISTS `journal_entry_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entry_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(15,2) DEFAULT 0.00,
  `credit` decimal(15,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `journal_id` (`journal_id`),
  KEY `account_id` (`account_id`),
  CONSTRAINT `journal_entry_lines_ibfk_1` FOREIGN KEY (`journal_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_entry_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `chart_of_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entry_lines`
--

LOCK TABLES `journal_entry_lines` WRITE;
/*!40000 ALTER TABLE `journal_entry_lines` DISABLE KEYS */;
INSERT INTO `journal_entry_lines` VALUES (1,1,9,500.00,0.00),(2,1,1,0.00,500.00),(3,2,1,1000.00,0.00),(4,2,3,0.00,1000.00),(5,3,1,500.00,0.00),(6,3,3,0.00,500.00),(7,4,1,245000.00,0.00),(8,4,3,0.00,245000.00),(9,5,5,15000.00,0.00),(10,5,1,0.00,15000.00);
/*!40000 ALTER TABLE `journal_entry_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `payable_type` varchar(50) NOT NULL,
  `payable_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` enum('cash','card','bank','mobile') DEFAULT 'cash',
  `note` text DEFAULT NULL,
  `paid_at` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payable` (`payable_type`,`payable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,'purchase',1,260000.00,'bank',NULL,'2026-08-30','2026-09-04 11:09:47'),(2,'sale',1,100000.00,'cash',NULL,'2026-09-02','2026-09-04 11:09:47'),(3,'sale',2,245000.00,'cash',NULL,'2026-09-05','2026-09-05 12:52:15'),(4,'purchase',2,15000.00,'cash',NULL,'2026-09-05','2026-09-05 12:55:42');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_stocks`
--

DROP TABLE IF EXISTS `product_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_stocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `branch_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_id` (`product_id`,`branch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_stocks`
--

LOCK TABLES `product_stocks` WRITE;
/*!40000 ALTER TABLE `product_stocks` DISABLE KEYS */;
INSERT INTO `product_stocks` VALUES (1,2,1,29),(2,1,1,50),(3,3,1,100);
/*!40000 ALTER TABLE `product_stocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `barcode_symbology` enum('C128','C39','EAN13','EAN8') DEFAULT 'C128',
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category_id` int(10) unsigned DEFAULT NULL,
  `unit_id` int(10) unsigned DEFAULT NULL,
  `cost_price` decimal(12,2) DEFAULT 0.00,
  `selling_price` decimal(12,2) DEFAULT 0.00,
  `stock_quantity` int(11) DEFAULT 0,
  `alert_quantity` int(11) DEFAULT 10,
  `gallery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `commission_rate` decimal(10,2) DEFAULT 0.00,
  `commission_type` enum('percentage','fixed') DEFAULT 'percentage',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `sku` (`sku`),
  KEY `unit_id` (`unit_id`),
  KEY `idx_sku` (`sku`),
  KEY `idx_stock` (`stock_quantity`),
  KEY `idx_category` (`category_id`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Samsung Galaxy S24','samsung-galaxy-s24','SAM-S24','C128',NULL,NULL,1,1,80000.00,95000.00,50,10,NULL,1,'2026-09-04 11:09:47','2026-09-05 12:52:15',0.00,'percentage'),(2,'Apple iPhone 15','apple-iphone-15','APP-IP15','C128',NULL,NULL,1,1,90000.00,110000.00,29,10,NULL,1,'2026-09-04 11:09:47','2026-09-05 12:52:15',0.00,'percentage'),(3,'AirPods Pro','airpods-pro','APP-AIR','C128',NULL,NULL,2,1,15000.00,20000.00,100,10,NULL,1,'2026-09-04 11:09:47','2026-09-05 12:56:41',0.00,'percentage');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_items`
--

DROP TABLE IF EXISTS `purchase_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_purchase` (`purchase_id`),
  CONSTRAINT `purchase_items_ibfk_1` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_items`
--

LOCK TABLES `purchase_items` WRITE;
/*!40000 ALTER TABLE `purchase_items` DISABLE KEYS */;
INSERT INTO `purchase_items` VALUES (1,1,1,2,80000.00,160000.00),(2,1,2,1,90000.00,90000.00),(3,1,3,1,10000.00,10000.00),(4,2,3,1,15000.00,15000.00),(5,3,3,1,15000.00,15000.00);
/*!40000 ALTER TABLE `purchase_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchases`
--

DROP TABLE IF EXISTS `purchases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchases` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `agent_commission_percent` decimal(5,2) DEFAULT 0.00,
  `agent_commission_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','ordered','received','canceled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `supplier_id` (`supplier_id`),
  KEY `idx_date` (`purchase_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `purchases_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchases`
--

LOCK TABLES `purchases` WRITE;
/*!40000 ALTER TABLE `purchases` DISABLE KEYS */;
INSERT INTO `purchases` VALUES (1,'PO-1001',3,'2026-08-30',260000.00,260000.00,0.00,0.00,'received','2026-09-04 11:09:47',1),(2,'PO-20260905-5903',3,'2026-09-05',15000.00,15000.00,0.00,0.00,'received','2026-09-05 12:55:42',1),(3,'PO-20260905-2414',3,'2026-09-05',15000.00,0.00,0.00,0.00,'received','2026-09-05 12:56:41',1);
/*!40000 ALTER TABLE `purchases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'fixed',
  `discount_value` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `commission_amount` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `idx_sale` (`sale_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_items`
--

LOCK TABLES `sale_items` WRITE;
/*!40000 ALTER TABLE `sale_items` DISABLE KEYS */;
INSERT INTO `sale_items` VALUES (1,1,1,1,95000.00,80000.00,95000.00,'fixed',0.00,0.00,0.00),(2,1,2,1,110000.00,90000.00,110000.00,'fixed',0.00,0.00,0.00),(3,1,3,1,20000.00,15000.00,20000.00,'fixed',0.00,0.00,0.00),(4,2,3,2,20000.00,15000.00,40000.00,'fixed',0.00,0.00,0.00),(5,2,2,1,110000.00,90000.00,110000.00,'fixed',0.00,0.00,0.00),(6,2,1,1,95000.00,80000.00,95000.00,'fixed',0.00,0.00,0.00);
/*!40000 ALTER TABLE `sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_return_items`
--

DROP TABLE IF EXISTS `sale_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_return_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `return_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(12,2) DEFAULT NULL,
  `subtotal` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `return_id` (`return_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `sale_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `sale_returns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_return_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_return_items`
--

LOCK TABLES `sale_return_items` WRITE;
/*!40000 ALTER TABLE `sale_return_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sale_returns`
--

DROP TABLE IF EXISTS `sale_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sale_returns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `return_date` date NOT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `refund_amount` decimal(12,2) DEFAULT NULL,
  `status` enum('pending','completed','rejected') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_sale` (`sale_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `sale_returns_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`),
  CONSTRAINT `sale_returns_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sale_returns`
--

LOCK TABLES `sale_returns` WRITE;
/*!40000 ALTER TABLE `sale_returns` DISABLE KEYS */;
/*!40000 ALTER TABLE `sale_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(50) NOT NULL,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `sale_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `paid_amount` decimal(12,2) DEFAULT 0.00,
  `staff_commission_percent` decimal(5,2) DEFAULT 0.00,
  `staff_commission_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','completed','canceled') DEFAULT 'completed',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `customer_id` (`customer_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_date` (`sale_date`),
  KEY `idx_status` (`status`),
  KEY `idx_reference` (`reference_no`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
INSERT INTO `sales` VALUES (1,'INV-2001',2,'2026-09-02',225000.00,0.00,0.00,100000.00,0.00,0.00,'completed',NULL,'2026-09-04 11:09:47',1),(2,'INV-20260905-FC08A1',NULL,'2026-09-05',245000.00,0.00,0.00,245000.00,0.00,0.00,'completed',1,'2026-09-05 12:52:15',1);
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `category` varchar(50) DEFAULT 'general',
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'company_name','Aroshy Traders','text','general',1,'2026-02-08 11:07:02','2026-09-06 13:15:14'),(2,'company_email','admin@example.com','text','general',1,'2026-02-08 11:07:02','2026-02-10 07:07:15'),(3,'company_phone','01723219162','text','general',1,'2026-02-08 11:07:02','2026-02-15 14:17:55'),(4,'company_address','Durgadoho, Bangladesh','text','general',1,'2026-02-08 11:07:02','2026-02-15 14:17:55'),(5,'currency_symbol','৳','text','general',1,'2026-02-08 11:07:02','2026-02-12 05:30:38'),(6,'timezone','Asia/Dhaka','text','general',1,'2026-02-08 11:07:02','2026-02-12 05:30:38'),(25,'tax_id','','text','general',1,'2026-02-08 11:11:46','2026-02-12 05:30:38'),(26,'default_tax_rate','0','text','general',1,'2026-02-08 11:11:46','2026-02-12 05:30:38'),(35,'company_tagline','Smart Business Management System','text','company',1,'2026-02-10 05:56:55','2026-02-10 07:07:15'),(36,'company_website','www.forbitpos.com','text','company',1,'2026-02-10 05:56:55','2026-02-10 07:07:15'),(37,'company_tax_number','','text','company',1,'2026-02-10 05:56:55','2026-02-10 07:07:15'),(38,'invoice_note','Thank you for your business!','text','print',1,'2026-02-10 05:56:55','2026-02-10 07:07:15'),(39,'invoice_terms','All sales are final. No refunds without receipt...','text','print',1,'2026-02-10 05:56:55','2026-02-10 07:07:15'),(40,'print_logo_enabled','0','boolean','print',1,'2026-02-10 05:56:55','2026-02-10 07:07:15'),(41,'print_show_tax_number','1','boolean','print',1,'2026-02-10 05:56:55','2026-02-10 07:07:15'),(42,'date_format','d M Y','text','general',NULL,'2026-02-10 05:56:55','2026-02-10 05:56:55'),(43,'time_format','H:i','text','general',NULL,'2026-02-10 05:56:55','2026-02-10 05:56:55'),(76,'weather_city','Joypurhat|25.1011|89.0262','text','general',1,'2026-09-03 20:48:02','2026-09-03 20:48:02');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(10) unsigned NOT NULL,
  `type` enum('sale','purchase','return','adjustment') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` int(10) unsigned DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_type` (`type`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (1,1,'purchase',2,'purchase',1,'Received Purchase','2026-09-04 11:09:47'),(2,2,'purchase',1,'purchase',1,'Received Purchase','2026-09-04 11:09:47'),(3,3,'purchase',1,'purchase',1,'Received Purchase','2026-09-04 11:09:47'),(4,1,'sale',-1,'sale',1,'Product Sold','2026-09-04 11:09:47'),(5,2,'sale',-1,'sale',1,'Product Sold','2026-09-04 11:09:47'),(6,3,'sale',-1,'sale',1,'Product Sold','2026-09-04 11:09:47');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_settings`
--

DROP TABLE IF EXISTS `tax_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(50) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_settings`
--

LOCK TABLES `tax_settings` WRITE;
/*!40000 ALTER TABLE `tax_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `units`
--

DROP TABLE IF EXISTS `units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `units` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `short_name` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `units`
--

LOCK TABLES `units` WRITE;
/*!40000 ALTER TABLE `units` DISABLE KEYS */;
INSERT INTO `units` VALUES (1,'Pieces','pcs'),(2,'Box','box');
/*!40000 ALTER TABLE `units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `role` enum('admin','manager','sales','staff','cashier') DEFAULT 'staff',
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'MD Taufik Alam','admin@admin.com','$2y$10$HmotDvG1F7gv5EIbn50c8u3X1F5XcptjDb21iuBVbA9eBmP6bvLHK',NULL,'admin','assets/uploads/users/6a9c54a53881f_161149a1.jpg',1,'2026-02-07 14:02:16','2026-09-05 17:43:01',1),(2,'Manager User','manager@example.com','$2y$10$keBeU4LfHmTbMrf/5eQy1.d1IWSdEN5T6qaaVm5PoB5A9NkEV6Vc2',NULL,'manager',NULL,1,'2026-02-07 14:02:16','2026-02-12 05:52:53',1),(3,'Cashier User','cashier@example.com','$2y$10$/ZA9coXj.3d4nXP2yFXRfeSMsTZ4MDshWCioNeGnds9QbvBBVYEv.',NULL,'staff','assets/uploads/users/6a9a5d016ba85_484e074b.png',1,'2026-02-07 14:02:16','2026-09-04 09:27:57',1),(4,'dzdf','aaa@a.com','$2y$10$2efe2XGI68tyLO7wbaSPaeOfJ.HBCs1Ox9mME09sc7MYrfeZJAGca',NULL,'staff',NULL,1,'2026-09-05 20:21:20','2026-09-05 20:45:24',2);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'forbitto_at_db'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-16 17:33:18
