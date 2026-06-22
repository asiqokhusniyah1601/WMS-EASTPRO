-- MySQL dump 10.13  Distrib 9.6.0, for macos14.8 (arm64)
--
-- Host: localhost    Database: dlms
-- ------------------------------------------------------
-- Server version	9.6.0

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
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

-- SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'b6ebedb0-45c7-11f1-bc83-f8ebafaa465f:1-3214'; --

--
-- Table structure for table `accessories`
--

DROP TABLE IF EXISTS `accessories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accessories` (
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accessories`
--

LOCK TABLES `accessories` WRITE;
/*!40000 ALTER TABLE `accessories` DISABLE KEYS */;
INSERT INTO `accessories` VALUES ('ACC-ANTENNA','GPS External Antenna',85,'2026-06-19 02:51:22','2026-06-20 21:02:17'),('ACC-CABLE','Power Cable Harness',150,'2026-06-19 02:51:22','2026-06-21 13:28:24'),('ACC-MOUNT','Dashcam Windshield Mount',60,'2026-06-19 02:51:22','2026-06-19 02:51:22'),('ACC-RELAY','Relay 24V',500,'2026-06-19 03:27:37','2026-06-19 03:27:37'),('ACC-RFID Mifare','Promag RFID Reader',30,'2026-06-19 03:40:52','2026-06-19 03:40:52'),('ACC-SUHU','Sensor Suhu',100,'2026-06-19 03:39:02','2026-06-19 03:39:02');
/*!40000 ALTER TABLE `accessories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accessory_transactions`
--

DROP TABLE IF EXISTS `accessory_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accessory_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `accessory_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technician_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accessory_transactions_accessory_code_foreign` (`accessory_code`),
  CONSTRAINT `accessory_transactions_accessory_code_foreign` FOREIGN KEY (`accessory_code`) REFERENCES `accessories` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accessory_transactions`
--

LOCK TABLES `accessory_transactions` WRITE;
/*!40000 ALTER TABLE `accessory_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `accessory_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_settings` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `app_settings`
--

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES ('app_favicon','uploads/favicon_1781975885.png','2026-06-20 10:16:24','2026-06-20 10:18:05'),('app_logo','uploads/logo_1781975897.png','2026-06-20 10:16:24','2026-06-20 10:18:17'),('theme_mode','light','2026-06-20 10:16:24','2026-06-21 08:55:11');
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_devices`
--

DROP TABLE IF EXISTS `customer_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  `installed_at` timestamp NULL DEFAULT NULL,
  `uninstalled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_devices_customer_id_foreign` (`customer_id`),
  KEY `customer_devices_device_id_foreign` (`device_id`),
  CONSTRAINT `customer_devices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `customer_devices_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_devices`
--

LOCK TABLES `customer_devices` WRITE;
/*!40000 ALTER TABLE `customer_devices` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `contract_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'PT Pelanggan Test','08123456789','Jalan Test No. 1','KONTRAK-TEST-001','2026-06-20 14:55:52','2026-06-20 14:55:52');
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_order_accessories`
--

DROP TABLE IF EXISTS `delivery_order_accessories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_order_accessories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accessory_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `do_acc_unique` (`delivery_order_id`,`accessory_code`),
  KEY `delivery_order_accessories_accessory_code_foreign` (`accessory_code`),
  CONSTRAINT `delivery_order_accessories_accessory_code_foreign` FOREIGN KEY (`accessory_code`) REFERENCES `accessories` (`code`) ON DELETE CASCADE,
  CONSTRAINT `delivery_order_accessories_delivery_order_id_foreign` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_order_accessories`
--

LOCK TABLES `delivery_order_accessories` WRITE;
/*!40000 ALTER TABLE `delivery_order_accessories` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_order_accessories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_order_devices`
--

DROP TABLE IF EXISTS `delivery_order_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_order_devices` (
  `delivery_order_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`delivery_order_id`,`device_id`),
  KEY `delivery_order_devices_device_id_foreign` (`device_id`),
  CONSTRAINT `delivery_order_devices_delivery_order_id_foreign` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `delivery_order_devices_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_order_devices`
--

LOCK TABLES `delivery_order_devices` WRITE;
/*!40000 ALTER TABLE `delivery_order_devices` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_order_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_order_simcards`
--

DROP TABLE IF EXISTS `delivery_order_simcards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_order_simcards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_order_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gsm_simcard_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `do_sim_unique` (`delivery_order_id`,`gsm_simcard_id`),
  KEY `delivery_order_simcards_gsm_simcard_id_index` (`gsm_simcard_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_order_simcards`
--

LOCK TABLES `delivery_order_simcards` WRITE;
/*!40000 ALTER TABLE `delivery_order_simcards` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_order_simcards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `delivery_orders`
--

DROP TABLE IF EXISTS `delivery_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `delivery_orders` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_warehouse_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_warehouse_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_orders_from_warehouse_code_foreign` (`from_warehouse_code`),
  KEY `delivery_orders_to_warehouse_code_foreign` (`to_warehouse_code`),
  CONSTRAINT `delivery_orders_from_warehouse_code_foreign` FOREIGN KEY (`from_warehouse_code`) REFERENCES `warehouses` (`code`) ON DELETE CASCADE,
  CONSTRAINT `delivery_orders_to_warehouse_code_foreign` FOREIGN KEY (`to_warehouse_code`) REFERENCES `warehouses` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `delivery_orders`
--

LOCK TABLES `delivery_orders` WRITE;
/*!40000 ALTER TABLE `delivery_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `delivery_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device_inspections`
--

DROP TABLE IF EXISTS `device_inspections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_inspections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `device_id` bigint unsigned NOT NULL,
  `condition` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `qc_result` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `operator` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `device_inspections_device_id_foreign` (`device_id`),
  CONSTRAINT `device_inspections_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device_inspections`
--

LOCK TABLES `device_inspections` WRITE;
/*!40000 ALTER TABLE `device_inspections` DISABLE KEYS */;
/*!40000 ALTER TABLE `device_inspections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device_models`
--

DROP TABLE IF EXISTS `device_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_models` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_models_model_unique` (`model`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device_models`
--

LOCK TABLES `device_models` WRITE;
/*!40000 ALTER TABLE `device_models` DISABLE KEYS */;
INSERT INTO `device_models` VALUES (1,'Teltonika','GPS Tracker','FMC130','2026-06-20 10:42:00','2026-06-20 10:42:00'),(2,'Teltonika','GPS Tracker','FMC920','2026-06-20 10:42:00','2026-06-20 10:42:00'),(3,'Teltonika','GPS Tracker','FMB120','2026-06-20 10:42:00','2026-06-20 10:42:00'),(4,'Ruptela','GPS Tracker','Trace5','2026-06-20 10:42:00','2026-06-20 10:42:00'),(5,'Ruptela','GPS Tracker','HCV5','2026-06-20 10:42:00','2026-06-20 10:42:00'),(6,'Concox','GPS Tracker','GT06N','2026-06-20 10:42:00','2026-06-20 10:42:00'),(7,'Concox','Dashcam','JC400','2026-06-20 10:42:00','2026-06-20 10:42:00'),(8,'Howen','MDVR','Hero-ME41-04','2026-06-20 10:42:00','2026-06-20 10:42:00'),(9,'Streamax','MDVR','X3-H04','2026-06-20 10:42:00','2026-06-20 10:42:00'),(10,'Atelematics','MDVR','AT-525','2026-06-21 16:26:29','2026-06-21 16:26:29'),(11,'Atelematics','E-SEAL','AT-16','2026-06-21 20:00:13','2026-06-21 20:00:13'),(12,'Atelematics','E-SEAL','AT-10','2026-06-21 20:00:25','2026-06-21 20:00:25');
/*!40000 ALTER TABLE `device_models` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `device_transactions`
--

DROP TABLE IF EXISTS `device_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `device_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `device_id` bigint unsigned NOT NULL,
  `device_sn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `operator` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scanned_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `via_web` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `device_transactions_device_id_index` (`device_id`),
  KEY `device_transactions_device_sn_index` (`device_sn`),
  KEY `device_transactions_device_id_created_at_index` (`device_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `device_transactions`
--

LOCK TABLES `device_transactions` WRITE;
/*!40000 ALTER TABLE `device_transactions` DISABLE KEYS */;
INSERT INTO `device_transactions` VALUES (1,1,'GPS-982173812','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-06-17 02:51:22','2026-06-19 02:51:22'),(2,2,'MDVR-88291','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-06-18 02:51:22','2026-06-19 02:51:22'),(3,4,'DEMO-GPS-0001','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-06-14 19:44:37','2026-06-14 19:44:37'),(4,5,'DEMO-GPS-0002','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-06-08 19:44:37','2026-06-08 19:44:37'),(5,6,'DEMO-GPS-0003','RECEIVING','Supplier','WH-REG-WEST','Super Admin','Scanner-HID-01',1,NULL,'2026-06-18 19:44:37','2026-06-18 19:44:37'),(6,7,'DEMO-GPS-0004','RECEIVING','Supplier','WH-REG-EAST','Super Admin','Scanner-HID-01',1,NULL,'2026-05-28 19:44:37','2026-05-28 19:44:37'),(7,8,'DEMO-MDR-0005','RECEIVING','Supplier','WH-AREA-SUB','Super Admin','Scanner-HID-01',1,NULL,'2026-06-10 19:44:37','2026-06-10 19:44:37'),(8,9,'DEMO-MDR-0006','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-06-13 19:44:37','2026-06-13 19:44:37'),(9,10,'DEMO-CAM-0007','RECEIVING','Supplier','WH-AREA-MLG','Super Admin','Scanner-HID-01',1,NULL,'2026-05-26 19:44:37','2026-05-26 19:44:37'),(10,11,'DEMO-GPS-0008','RECEIVING','Supplier','WH-AREA-SDA','Super Admin','Scanner-HID-01',1,NULL,'2026-06-01 19:44:37','2026-06-01 19:44:37'),(11,12,'DEMO-GPS-0009','RECEIVING','Supplier','WH-REG-WEST','Super Admin','Scanner-HID-01',1,NULL,'2026-06-09 19:44:37','2026-06-09 19:44:37'),(12,13,'DEMO-GPS-0010','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-06-05 19:44:37','2026-06-05 19:44:37'),(13,13,'DEMO-GPS-0010','ISSUED','WH-PUSAT','Technician: Budi Santoso','Super Admin','Scanner-HID-01',1,NULL,'2026-06-08 19:44:37','2026-06-08 19:44:37'),(14,14,'DEMO-GPS-0011','RECEIVING','Supplier','WH-REG-WEST','Super Admin','Scanner-HID-01',1,NULL,'2026-06-08 19:44:37','2026-06-08 19:44:37'),(15,14,'DEMO-GPS-0011','ISSUED','WH-REG-WEST','Technician: John Doe','Super Admin','Scanner-HID-01',1,NULL,'2026-06-11 19:44:37','2026-06-11 19:44:37'),(16,15,'DEMO-MDR-0012','RECEIVING','Supplier','WH-AREA-SUB','Super Admin','Scanner-HID-01',1,NULL,'2026-06-14 19:44:37','2026-06-14 19:44:37'),(17,15,'DEMO-MDR-0012','ISSUED','WH-AREA-SUB','Technician: Jane Smith','Super Admin','Scanner-HID-01',1,NULL,'2026-06-17 19:44:37','2026-06-17 19:44:37'),(18,16,'DEMO-CAM-0013','RECEIVING','Supplier','WH-AREA-MLG','Super Admin','Scanner-HID-01',1,NULL,'2026-06-15 19:44:37','2026-06-15 19:44:37'),(19,16,'DEMO-CAM-0013','ISSUED','WH-AREA-MLG','Technician: Ahmad Rian','Super Admin','Scanner-HID-01',1,NULL,'2026-06-18 19:44:37','2026-06-18 19:44:37'),(20,17,'DEMO-GPS-0014','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-06-12 19:44:37','2026-06-12 19:44:37'),(21,17,'DEMO-GPS-0014','ISSUED','WH-PUSAT','Technician: Budi Santoso','Super Admin','Scanner-HID-01',1,NULL,'2026-06-15 19:44:37','2026-06-15 19:44:37'),(22,18,'DEMO-GPS-0015','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-05-28 19:44:37','2026-05-28 19:44:37'),(23,18,'DEMO-GPS-0015','ISSUED','WH-PUSAT','Plat L 1234 AB','Super Admin','Scanner-HID-01',1,NULL,'2026-05-31 19:44:37','2026-05-31 19:44:37'),(24,19,'DEMO-MDR-0016','RECEIVING','Supplier','WH-AREA-SUB','Super Admin','Scanner-HID-01',1,NULL,'2026-05-30 19:44:37','2026-05-30 19:44:37'),(25,19,'DEMO-MDR-0016','ISSUED','WH-AREA-SUB','Plat W 5678 CD','Super Admin','Scanner-HID-01',1,NULL,'2026-06-02 19:44:37','2026-06-02 19:44:37'),(26,20,'DEMO-CAM-0017','RECEIVING','Supplier','WH-AREA-MLG','Super Admin','Scanner-HID-01',1,NULL,'2026-06-02 19:44:37','2026-06-02 19:44:37'),(27,20,'DEMO-CAM-0017','ISSUED','WH-AREA-MLG','Plat N 9012 EF','Super Admin','Scanner-HID-01',1,NULL,'2026-06-05 19:44:37','2026-06-05 19:44:37'),(28,21,'DEMO-GPS-0018','RECEIVING','Supplier','WH-REG-EAST','Super Admin','Scanner-HID-01',1,NULL,'2026-06-11 19:44:37','2026-06-11 19:44:37'),(29,21,'DEMO-GPS-0018','TRANSFER_OUT','WH-REG-EAST','In Transit to WH-AREA-MLG','Super Admin','Scanner-HID-01',1,NULL,'2026-06-14 19:44:37','2026-06-14 19:44:37'),(30,22,'DEMO-GPS-0019','RECEIVING','Supplier','WH-PUSAT','Super Admin','Scanner-HID-01',1,NULL,'2026-06-13 19:44:37','2026-06-13 19:44:37'),(31,22,'DEMO-GPS-0019','TRANSFER_OUT','WH-PUSAT','In Transit to WH-REG-WEST','Super Admin','Scanner-HID-01',1,NULL,'2026-06-16 19:44:37','2026-06-16 19:44:37'),(32,23,'DEMO-MDR-0020','RECEIVING','Supplier','WH-AREA-SUB','Super Admin','Scanner-HID-01',1,NULL,'2026-06-09 19:44:37','2026-06-09 19:44:37'),(33,23,'DEMO-MDR-0020','ISSUED','WH-AREA-SUB','Warehouse WH-AREA-SUB','Super Admin','Scanner-HID-01',1,NULL,'2026-06-12 19:44:37','2026-06-12 19:44:37'),(34,4,'DEMO-GPS-0001','ISSUED','WH-PUSAT','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-05-23 19:44:37','2026-05-23 19:44:37'),(35,5,'DEMO-GPS-0002','ISSUED','WH-PUSAT','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-05-25 19:44:37','2026-05-25 19:44:37'),(36,6,'DEMO-GPS-0003','TRANSFER_OUT','WH-REG-WEST','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-05-27 19:44:37','2026-05-27 19:44:37'),(37,8,'DEMO-MDR-0005','ISSUED','WH-AREA-SUB','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-05-29 19:44:37','2026-05-29 19:44:37'),(38,7,'DEMO-GPS-0004','ISSUED','WH-REG-EAST','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-05-30 19:44:37','2026-05-30 19:44:37'),(39,10,'DEMO-CAM-0007','ISSUED','WH-AREA-MLG','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-01 19:44:37','2026-06-01 19:44:37'),(40,11,'DEMO-GPS-0008','TRANSFER_OUT','WH-AREA-SDA','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-03 19:44:37','2026-06-03 19:44:37'),(41,9,'DEMO-MDR-0006','ISSUED','WH-PUSAT','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-06 19:44:37','2026-06-06 19:44:37'),(42,12,'DEMO-GPS-0009','ISSUED','WH-REG-WEST','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-07 19:44:37','2026-06-07 19:44:37'),(43,4,'DEMO-GPS-0001','ISSUED','WH-PUSAT','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-09 19:44:37','2026-06-09 19:44:37'),(44,5,'DEMO-GPS-0002','TRANSFER_OUT','WH-PUSAT','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-10 19:44:37','2026-06-10 19:44:37'),(45,10,'DEMO-CAM-0007','ISSUED','WH-AREA-MLG','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-13 19:44:37','2026-06-13 19:44:37'),(46,8,'DEMO-MDR-0005','ISSUED','WH-AREA-SUB','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-14 19:44:37','2026-06-14 19:44:37'),(47,6,'DEMO-GPS-0003','ISSUED','WH-REG-WEST','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-19 19:44:37','2026-06-19 19:44:37'),(48,12,'DEMO-GPS-0009','ISSUED','WH-REG-WEST','Field Operation','Super Admin','Scanner-HID-01',1,NULL,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(50,1,'GPS-982173812','ISSUED','WH-PUSAT','Technician: Budi Santoso','Warehouse Operator','Scanner-HID-01',1,'TT-260621-044846-A50B','2026-06-20 21:48:46','2026-06-20 21:48:46'),(51,2,'MDVR-88291','ISSUED','WH-PUSAT','Technician: Budi Santoso','Warehouse Operator','Scanner-HID-01',1,'TT-260621-044927-2F25','2026-06-20 21:49:27','2026-06-20 21:49:27'),(52,4,'DEMO-GPS-0001','ISSUED','WH-PUSAT','Technician: Budi Santoso','Warehouse Operator','Scanner-HID-01',1,'TT-260621-045028-68EB','2026-06-20 21:50:28','2026-06-20 21:50:28'),(53,5,'DEMO-GPS-0002','ISSUED','WH-PUSAT','Technician: Budi Santoso','Warehouse Operator','Scanner-HID-01',1,'TT-260621-045327-AA48','2026-06-20 21:53:27','2026-06-20 21:53:27');
/*!40000 ALTER TABLE `device_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `devices`
--

DROP TABLE IF EXISTS `devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `serial_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `imei` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_condition` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'BARU',
  `qc_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qc_at` timestamp NULL DEFAULT NULL,
  `qc_notes` text COLLATE utf8mb4_unicode_ci,
  `current_holder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gsm_simcard_id` bigint unsigned DEFAULT NULL,
  `vehicle_plate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `devices_serial_number_unique` (`serial_number`),
  UNIQUE KEY `devices_imei_unique` (`imei`),
  KEY `devices_serial_number_index` (`serial_number`),
  KEY `devices_imei_index` (`imei`),
  KEY `devices_status_index` (`status`),
  KEY `devices_warehouse_code_foreign` (`warehouse_code`),
  KEY `devices_gsm_simcard_id_foreign` (`gsm_simcard_id`),
  KEY `devices_vehicle_plate_index` (`vehicle_plate`),
  KEY `devices_unit_condition_index` (`unit_condition`),
  CONSTRAINT `devices_gsm_simcard_id_foreign` FOREIGN KEY (`gsm_simcard_id`) REFERENCES `gsm_simcards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `devices_warehouse_code_foreign` FOREIGN KEY (`warehouse_code`) REFERENCES `warehouses` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `devices`
--

LOCK TABLES `devices` WRITE;
/*!40000 ALTER TABLE `devices` DISABLE KEYS */;
INSERT INTO `devices` VALUES (1,'GPS-982173812','358291039821738','GPS Tracker','SuperSpring VT-90E','ISSUED','BARU',NULL,NULL,NULL,'Technician: Budi Santoso','WH-PUSAT',NULL,NULL,'2026-06-19 02:51:22','2026-06-21 12:43:46'),(2,'MDVR-88291','351122334455667','MDVR','Hikvision 4-CH Mobile DVR','ISSUED','BARU',NULL,NULL,NULL,'Technician: Budi Santoso','WH-PUSAT',NULL,NULL,'2026-06-19 02:51:22','2026-06-20 21:49:27'),(4,'DEMO-GPS-0001','350000000000001','GPS Tracker','FMC130','ISSUED','BARU',NULL,NULL,NULL,'Technician: Budi Santoso','WH-PUSAT',NULL,NULL,'2026-06-20 19:44:37','2026-06-20 21:50:28'),(5,'DEMO-GPS-0002','350000000000002','GPS Tracker','FMC920','ISSUED','BARU',NULL,NULL,NULL,'Technician: Budi Santoso','WH-PUSAT',NULL,NULL,'2026-06-20 19:44:37','2026-06-20 21:53:27'),(6,'DEMO-GPS-0003','350000000000003','GPS Tracker','Trace5','IN_STOCK','BARU',NULL,NULL,NULL,'Regional Warehouse West','WH-REG-WEST',NULL,NULL,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(7,'DEMO-GPS-0004','350000000000004','GPS Tracker','GT06N','IN_STOCK','BARU',NULL,NULL,NULL,'Regional Warehouse East','WH-REG-EAST',NULL,NULL,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(9,'DEMO-MDR-0006','350000000000006','MDVR','X3-H04','IN_STOCK','BARU',NULL,NULL,NULL,'Warehouse Pusat','WH-PUSAT',NULL,NULL,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(10,'DEMO-CAM-0007','350000000000007','Dashcam','JC400','IN_STOCK','BARU',NULL,NULL,NULL,'Area Warehouse Malang','WH-AREA-MLG',NULL,NULL,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(12,'DEMO-GPS-0009','350000000000009','GPS Tracker','HCV5','IN_STOCK','BARU',NULL,NULL,NULL,'Regional Warehouse West','WH-REG-WEST',NULL,NULL,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(13,'DEMO-GPS-0010','350000000000010','GPS Tracker','FMC130','ISSUED','BARU',NULL,NULL,NULL,'Technician: Budi Santoso','WH-PUSAT',NULL,NULL,'2026-06-20 19:44:37','2026-06-08 19:44:37'),(14,'DEMO-GPS-0011','350000000000011','GPS Tracker','GT06N','ISSUED','BARU',NULL,NULL,NULL,'Technician: John Doe','WH-REG-WEST',NULL,NULL,'2026-06-20 19:44:37','2026-06-11 19:44:37'),(16,'DEMO-CAM-0013','350000000000013','Dashcam','JC400','ISSUED','BARU',NULL,NULL,NULL,'Technician: Ahmad Rian','WH-AREA-MLG',NULL,NULL,'2026-06-20 19:44:37','2026-06-18 19:44:37'),(17,'DEMO-GPS-0014','350000000000014','GPS Tracker','FMC920','ISSUED','BARU',NULL,NULL,NULL,'Technician: Budi Santoso','WH-PUSAT',NULL,NULL,'2026-06-20 19:44:37','2026-06-15 19:44:37'),(18,'DEMO-GPS-0015','350000000000015','GPS Tracker','FMC130','INSTALLED','BARU',NULL,NULL,NULL,'Plat L 1234 AB','WH-PUSAT',NULL,NULL,'2026-06-20 19:44:37','2026-05-31 19:44:37'),(20,'DEMO-CAM-0017','350000000000017','Dashcam','JC400','INSTALLED','BARU',NULL,NULL,NULL,'Plat N 9012 EF','WH-AREA-MLG',NULL,NULL,'2026-06-20 19:44:37','2026-06-05 19:44:37'),(21,'DEMO-GPS-0018','350000000000018','GPS Tracker','FMB120','IN_TRANSIT','BARU',NULL,NULL,NULL,'In Transit to WH-AREA-MLG','WH-REG-EAST',NULL,NULL,'2026-06-20 19:44:37','2026-06-14 19:44:37'),(22,'DEMO-GPS-0019','350000000000019','GPS Tracker','Trace5','IN_TRANSIT','BARU',NULL,NULL,NULL,'In Transit to WH-REG-WEST','WH-PUSAT',NULL,NULL,'2026-06-20 19:44:37','2026-06-16 19:44:37');
/*!40000 ALTER TABLE `devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gsm_simcards`
--

DROP TABLE IF EXISTS `gsm_simcards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gsm_simcards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `msisdn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'IN_STOCK',
  `warehouse_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gsm_simcards_msisdn_unique` (`msisdn`),
  KEY `gsm_simcards_warehouse_code_index` (`warehouse_code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gsm_simcards`
--

LOCK TABLES `gsm_simcards` WRITE;
/*!40000 ALTER TABLE `gsm_simcards` DISABLE KEYS */;
INSERT INTO `gsm_simcards` VALUES (1,'6281122334455','Telkomsel','Halo','IN_STOCK',NULL,'2026-06-19 02:51:22','2026-06-20 10:01:50'),(2,'6281223344556','Indosat Ooredoo','B2B','IN_STOCK','WH-REG-EAST','2026-06-19 02:51:22','2026-06-21 11:02:51'),(3,'6281323344557','XL Axiata','XL Biz','IN_STOCK','WH-REG-EAST','2026-06-19 02:51:22','2026-06-21 11:02:51'),(5,'081315507667','Telkomsel','Data','IN_STOCK','WH-REG-EAST','2026-06-21 09:50:24','2026-06-21 10:41:12');
/*!40000 ALTER TABLE `gsm_simcards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holder_accessories`
--

DROP TABLE IF EXISTS `holder_accessories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `holder_accessories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `holder_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `holder_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `holder_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accessory_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `holder_acc_unique` (`holder_type`,`holder_code`,`accessory_code`),
  KEY `holder_accessories_holder_type_holder_code_index` (`holder_type`,`holder_code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holder_accessories`
--

LOCK TABLES `holder_accessories` WRITE;
/*!40000 ALTER TABLE `holder_accessories` DISABLE KEYS */;
/*!40000 ALTER TABLE `holder_accessories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_06_19_000001_create_dlms_schema',1),(5,'2026_06_19_000002_create_gsm_simcards_and_installations',1),(6,'2026_06_19_071111_create_personal_access_tokens_table',1),(7,'2026_06_21_000001_create_app_settings_table',2),(8,'2026_06_21_000002_create_device_models_table',3),(9,'2026_06_20_183326_apply_gap_analysis_changes',4),(10,'2026_06_20_210810_create_delivery_order_accessories_table',5),(11,'2026_06_20_210810_create_warehouse_accessories_table',6),(12,'2026_06_21_021417_create_stock_alert_thresholds_table',7),(13,'2026_06_21_110000_add_notes_to_transactions',8),(14,'2026_06_21_120000_add_role_to_users',9),(15,'2026_06_21_120000_create_holder_accessories_table',10),(16,'2026_06_21_130000_add_warehouse_to_simcards_and_create_simcard_transactions',11),(17,'2026_06_21_140000_create_delivery_order_simcards_table',12),(18,'2026_06_22_010000_add_condition_to_devices',13),(19,'2026_06_22_000000_add_area_to_technicians_table',14),(20,'2026_06_22_020000_add_qc_fields_to_devices',15);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('IJhyBi3TCrYUvElXdBraVxNP53ZUZLA1fH8LXu29',NULL,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','eyJfdG9rZW4iOiJsTVVJY3ZpY1U4Yld3MkZWYXRPMFZtZ2lXTXhPaG0zQ3h1dGZKTWl2IiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTI3LjAuMC4xOjgwMDFcL2xvZ2luIiwicm91dGUiOiJsb2dpbiJ9fQ==',1782105124);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `simcard_transactions`
--

DROP TABLE IF EXISTS `simcard_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simcard_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gsm_simcard_id` bigint unsigned DEFAULT NULL,
  `msisdn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warehouse_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operator` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `simcard_transactions_gsm_simcard_id_foreign` (`gsm_simcard_id`),
  KEY `simcard_transactions_warehouse_code_action_index` (`warehouse_code`,`action`),
  CONSTRAINT `simcard_transactions_gsm_simcard_id_foreign` FOREIGN KEY (`gsm_simcard_id`) REFERENCES `gsm_simcards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `simcard_transactions`
--

LOCK TABLES `simcard_transactions` WRITE;
/*!40000 ALTER TABLE `simcard_transactions` DISABLE KEYS */;
INSERT INTO `simcard_transactions` VALUES (2,5,'081315507667','RECEIVING','Supplier','WH-REG-EAST','WH-REG-EAST','Super Administrator',NULL,'2026-06-21 09:50:24','2026-06-21 09:50:24'),(3,5,'081315507667','RECEIVING','Supplier','WH-REG-EAST','WH-REG-EAST','Super Administrator',NULL,'2026-06-21 09:50:48','2026-06-21 09:50:48'),(6,2,'6281223344556','RECEIVING','Pool','WH-REG-EAST','WH-REG-EAST','Super Administrator',NULL,'2026-06-21 11:02:51','2026-06-21 11:02:51'),(7,3,'6281323344557','RECEIVING','Pool','WH-REG-EAST','WH-REG-EAST','Super Administrator',NULL,'2026-06-21 11:02:51','2026-06-21 11:02:51');
/*!40000 ALTER TABLE `simcard_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_alert_thresholds`
--

DROP TABLE IF EXISTS `stock_alert_thresholds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_alert_thresholds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_identifier` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_stock_level` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wh_item_unique` (`warehouse_code`,`item_type`,`item_identifier`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_alert_thresholds`
--

LOCK TABLES `stock_alert_thresholds` WRITE;
/*!40000 ALTER TABLE `stock_alert_thresholds` DISABLE KEYS */;
INSERT INTO `stock_alert_thresholds` VALUES (1,'WH-REG-EAST','DEVICE','GT06N',3,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(2,'WH-AREA-MLG','DEVICE','FMC130',2,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(3,'WH-PUSAT','ACCESSORY','ACC-ANTENNA',5,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(4,'WH-REG-WEST','ACCESSORY','ACC-MOUNT',5,'2026-06-20 19:44:37','2026-06-20 19:44:37');
/*!40000 ALTER TABLE `stock_alert_thresholds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `technicians`
--

DROP TABLE IF EXISTS `technicians`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `technicians` (
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `technicians`
--

LOCK TABLES `technicians` WRITE;
/*!40000 ALTER TABLE `technicians` DISABLE KEYS */;
INSERT INTO `technicians` VALUES ('TECH-001','Budi Santoso','Malang','2026-06-19 02:51:22','2026-06-21 15:58:31'),('TECH-002','John Doe','Surabaya','2026-06-19 02:51:22','2026-06-21 15:58:37'),('TECH-003','Jane Smith','Tuban','2026-06-19 02:51:22','2026-06-21 15:58:45'),('TECH-004','Ahmad Rian','Semarang','2026-06-19 02:51:22','2026-06-21 15:58:50');
/*!40000 ALTER TABLE `technicians` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warehouse_admin',
  `warehouse_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Test User','test@example.com','2026-06-19 02:51:21','$2y$12$cRrIVkKfFb28SxrNvxpeCenYHsewBVhxE9X8eOIE36PBJX7LhRaXi','super_admin',NULL,1,'KbXR2QKccb','2026-06-19 02:51:22','2026-06-20 21:10:33'),(2,'Super Administrator','super@dlms.test',NULL,'$2y$12$LVpk.HSx7xqFpMRlTtJuJev4.hehqs.5PscT4UVR6rCEU4MgZReGC','super_admin',NULL,1,NULL,'2026-06-20 21:10:33','2026-06-20 21:10:33'),(3,'Admin Gudang','gudang@dlms.test',NULL,'$2y$12$LTFHR9C1gR2MdFb9.W7iw.0hQgV18h85toheZ4r9QrP3IJUJEiH/.','warehouse_admin','WH-AREA-MLG',1,NULL,'2026-06-20 21:10:33','2026-06-20 21:10:33');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouse_accessories`
--

DROP TABLE IF EXISTS `warehouse_accessories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouse_accessories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `accessory_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `warehouse_accessories_warehouse_code_accessory_code_unique` (`warehouse_code`,`accessory_code`),
  KEY `warehouse_accessories_accessory_code_foreign` (`accessory_code`),
  CONSTRAINT `warehouse_accessories_accessory_code_foreign` FOREIGN KEY (`accessory_code`) REFERENCES `accessories` (`code`) ON DELETE CASCADE,
  CONSTRAINT `warehouse_accessories_warehouse_code_foreign` FOREIGN KEY (`warehouse_code`) REFERENCES `warehouses` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouse_accessories`
--

LOCK TABLES `warehouse_accessories` WRITE;
/*!40000 ALTER TABLE `warehouse_accessories` DISABLE KEYS */;
INSERT INTO `warehouse_accessories` VALUES (1,'WH-PUSAT','ACC-ANTENNA',85,'2026-06-20 19:44:37','2026-06-21 13:27:11'),(2,'WH-REG-WEST','ACC-CABLE',40,'2026-06-20 19:44:37','2026-06-20 19:44:37'),(3,'WH-REG-EAST','ACC-ANTENNA',0,'2026-06-20 21:02:17','2026-06-20 21:02:17'),(4,'WH-PUSAT','ACC-CABLE',110,'2026-06-21 13:27:11','2026-06-21 13:28:24'),(5,'WH-PUSAT','ACC-MOUNT',60,'2026-06-21 13:27:11','2026-06-21 13:27:11'),(6,'WH-PUSAT','ACC-RELAY',500,'2026-06-21 13:27:11','2026-06-21 13:27:11'),(7,'WH-PUSAT','ACC-RFID Mifare',30,'2026-06-21 13:27:11','2026-06-21 13:27:11'),(8,'WH-PUSAT','ACC-SUHU',100,'2026-06-21 13:27:11','2026-06-21 13:27:11');
/*!40000 ALTER TABLE `warehouse_accessories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouses`
--

DROP TABLE IF EXISTS `warehouses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warehouses` (
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CABANG',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouses`
--

LOCK TABLES `warehouses` WRITE;
/*!40000 ALTER TABLE `warehouses` DISABLE KEYS */;
INSERT INTO `warehouses` VALUES ('WH-AREA-MKS','Makassar','CABANG','2026-06-21 16:22:56','2026-06-21 16:22:56'),('WH-AREA-MLG','Malang','CABANG','2026-06-19 02:51:22','2026-06-21 16:22:19'),('WH-AREA-SMG','Semarang','CABANG','2026-06-21 16:22:11','2026-06-21 16:22:11'),('WH-AREA-SWK','Sorowako','CABANG','2026-06-21 16:23:19','2026-06-21 16:23:19'),('WH-AREA-TBN','Tuban','CABANG','2026-06-21 16:22:43','2026-06-21 16:22:43'),('WH-AREA-YGK','Yogjakarta','CABANG','2026-06-21 16:24:18','2026-06-21 16:24:18'),('WH-PUSAT','Warehouse Pusat','PUSAT','2026-06-19 02:51:22','2026-06-21 16:24:37'),('WH-REG-EAST','East Area','REGIONAL','2026-06-19 02:51:22','2026-06-21 16:24:52'),('WH-REG-WEST','West Area','REGIONAL','2026-06-19 02:51:22','2026-06-21 16:25:07');
/*!40000 ALTER TABLE `warehouses` ENABLE KEYS */;
UNLOCK TABLES;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-22 12:43:58
