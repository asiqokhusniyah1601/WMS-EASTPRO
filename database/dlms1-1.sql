-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 25, 2026 at 10:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.5.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dlms`
--

-- --------------------------------------------------------

--
-- Table structure for table `accessories`
--

CREATE TABLE `accessories` (
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accessories`
--

INSERT INTO `accessories` (`code`, `name`, `qty`, `created_at`, `updated_at`) VALUES
('ACC-ANTENNA', 'GPS External Antenna', 85, '2026-06-19 02:51:22', '2026-06-20 21:02:17'),
('ACC-CABLE', 'Power Cable Harness', 150, '2026-06-19 02:51:22', '2026-06-21 13:28:24'),
('ACC-MOUNT', 'Dashcam Windshield Mount', 60, '2026-06-19 02:51:22', '2026-06-19 02:51:22'),
('ACC-RELAY', 'Relay 24V', 500, '2026-06-19 03:27:37', '2026-06-19 03:27:37'),
('ACC-RFID Mifare', 'Promag RFID Reader', 30, '2026-06-19 03:40:52', '2026-06-19 03:40:52'),
('ACC-SUHU', 'Sensor Suhu', 100, '2026-06-19 03:39:02', '2026-06-19 03:39:02');

-- --------------------------------------------------------

--
-- Table structure for table `accessory_transactions`
--

CREATE TABLE `accessory_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `accessory_code` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `technician_code` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

CREATE TABLE `app_settings` (
  `key` varchar(255) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`key`, `value`, `created_at`, `updated_at`) VALUES
('app_favicon', 'uploads/favicon_1782107468.png', '2026-06-20 10:16:24', '2026-06-21 22:51:08'),
('app_logo', 'uploads/logo_1782107462.png', '2026-06-20 10:16:24', '2026-06-21 22:51:02'),
('theme_mode', 'light', '2026-06-20 10:16:24', '2026-06-21 22:52:29');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contract_no` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `contract_no`, `created_at`, `updated_at`) VALUES
(1, 'PT Pelanggan Test', '08123456789', 'Jalan Test No. 1', 'KONTRAK-TEST-001', '2026-06-20 14:55:52', '2026-06-20 14:55:52');

-- --------------------------------------------------------

--
-- Table structure for table `customer_devices`
--

CREATE TABLE `customer_devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `installed_at` timestamp NULL DEFAULT NULL,
  `uninstalled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_orders`
--

CREATE TABLE `delivery_orders` (
  `id` varchar(255) NOT NULL,
  `from_warehouse_code` varchar(255) NOT NULL,
  `to_warehouse_code` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_order_accessories`
--

CREATE TABLE `delivery_order_accessories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `delivery_order_id` varchar(255) NOT NULL,
  `accessory_code` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_order_devices`
--

CREATE TABLE `delivery_order_devices` (
  `delivery_order_id` varchar(255) NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_order_simcards`
--

CREATE TABLE `delivery_order_simcards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `delivery_order_id` varchar(255) NOT NULL,
  `gsm_simcard_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `serial_number` varchar(255) NOT NULL,
  `imei` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL,
  `unit_condition` varchar(255) NOT NULL DEFAULT 'BARU',
  `qc_by` varchar(255) DEFAULT NULL,
  `qc_at` timestamp NULL DEFAULT NULL,
  `qc_notes` text DEFAULT NULL,
  `current_holder` varchar(255) NOT NULL,
  `warehouse_code` varchar(255) NOT NULL,
  `gsm_simcard_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vehicle_plate` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `serial_number`, `imei`, `type`, `model`, `status`, `unit_condition`, `qc_by`, `qc_at`, `qc_notes`, `current_holder`, `warehouse_code`, `gsm_simcard_id`, `vehicle_plate`, `created_at`, `updated_at`) VALUES
(1, 'GPS-982173812', '358291039821738', 'GPS Tracker', 'SuperSpring VT-90E', 'ISSUED', 'BARU', NULL, NULL, NULL, 'Technician: Budi Santoso', 'WH-PUSAT', NULL, NULL, '2026-06-19 02:51:22', '2026-06-21 12:43:46'),
(2, 'MDVR-88291', '351122334455667', 'MDVR', 'Hikvision 4-CH Mobile DVR', 'ISSUED', 'BARU', NULL, NULL, NULL, 'Technician: Budi Santoso', 'WH-PUSAT', NULL, NULL, '2026-06-19 02:51:22', '2026-06-20 21:49:27'),
(4, 'DEMO-GPS-0001', '350000000000001', 'GPS Tracker', 'FMC130', 'ISSUED', 'BARU', NULL, NULL, NULL, 'Technician: Budi Santoso', 'WH-PUSAT', NULL, NULL, '2026-06-20 19:44:37', '2026-06-20 21:50:28'),
(5, 'DEMO-GPS-0002', '350000000000002', 'GPS Tracker', 'FMC920', 'ISSUED', 'BARU', NULL, NULL, NULL, 'Technician: Budi Santoso', 'WH-PUSAT', NULL, NULL, '2026-06-20 19:44:37', '2026-06-20 21:53:27'),
(6, 'DEMO-GPS-0003', '350000000000003', 'GPS Tracker', 'Trace5', 'IN_STOCK', 'BARU', NULL, NULL, NULL, 'Regional Warehouse West', 'WH-REG-WEST', NULL, NULL, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(7, 'DEMO-GPS-0004', '350000000000004', 'GPS Tracker', 'GT06N', 'IN_STOCK', 'BARU', NULL, NULL, NULL, 'Regional Warehouse East', 'WH-REG-EAST', NULL, NULL, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(9, 'DEMO-MDR-0006', '350000000000006', 'MDVR', 'X3-H04', 'IN_STOCK', 'BARU', NULL, NULL, NULL, 'Warehouse Pusat', 'WH-PUSAT', NULL, NULL, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(10, 'DEMO-CAM-0007', '350000000000007', 'Dashcam', 'JC400', 'IN_STOCK', 'BARU', NULL, NULL, NULL, 'Area Warehouse Malang', 'WH-AREA-MLG', NULL, NULL, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(12, 'DEMO-GPS-0009', '350000000000009', 'GPS Tracker', 'HCV5', 'IN_STOCK', 'BARU', NULL, NULL, NULL, 'Regional Warehouse West', 'WH-REG-WEST', NULL, NULL, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(13, 'DEMO-GPS-0010', '350000000000010', 'GPS Tracker', 'FMC130', 'ISSUED', 'BARU', NULL, NULL, NULL, 'Technician: Budi Santoso', 'WH-PUSAT', NULL, NULL, '2026-06-20 19:44:37', '2026-06-08 19:44:37'),
(14, 'DEMO-GPS-0011', '350000000000011', 'GPS Tracker', 'GT06N', 'ISSUED', 'BARU', NULL, NULL, NULL, 'Technician: John Doe', 'WH-REG-WEST', NULL, NULL, '2026-06-20 19:44:37', '2026-06-11 19:44:37'),
(16, 'DEMO-CAM-0013', '350000000000013', 'Dashcam', 'JC400', 'ISSUED', 'BARU', NULL, NULL, NULL, 'Technician: Ahmad Rian', 'WH-AREA-MLG', NULL, NULL, '2026-06-20 19:44:37', '2026-06-18 19:44:37'),
(17, 'DEMO-GPS-0014', '350000000000014', 'GPS Tracker', 'FMC920', 'ISSUED', 'BARU', NULL, NULL, NULL, 'Technician: Budi Santoso', 'WH-PUSAT', NULL, NULL, '2026-06-20 19:44:37', '2026-06-15 19:44:37'),
(18, 'DEMO-GPS-0015', '350000000000015', 'GPS Tracker', 'FMC130', 'INSTALLED', 'BARU', NULL, NULL, NULL, 'Plat L 1234 AB', 'WH-PUSAT', NULL, NULL, '2026-06-20 19:44:37', '2026-05-31 19:44:37'),
(20, 'DEMO-CAM-0017', '350000000000017', 'Dashcam', 'JC400', 'INSTALLED', 'BARU', NULL, NULL, NULL, 'Plat N 9012 EF', 'WH-AREA-MLG', NULL, NULL, '2026-06-20 19:44:37', '2026-06-05 19:44:37'),
(21, 'DEMO-GPS-0018', '350000000000018', 'GPS Tracker', 'FMB120', 'IN_TRANSIT', 'BARU', NULL, NULL, NULL, 'In Transit to WH-AREA-MLG', 'WH-REG-EAST', NULL, NULL, '2026-06-20 19:44:37', '2026-06-14 19:44:37'),
(22, 'DEMO-GPS-0019', '350000000000019', 'GPS Tracker', 'Trace5', 'IN_TRANSIT', 'BARU', NULL, NULL, NULL, 'In Transit to WH-REG-WEST', 'WH-PUSAT', NULL, NULL, '2026-06-20 19:44:37', '2026-06-16 19:44:37'),
(25, '862129083377238', '358496780291286', 'GPS Tracker', 'FMC920', 'IN_STOCK', 'BARU', 'Test User', '2026-06-21 22:57:23', NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:57:23'),
(26, '862129083377105', '352624002183662', 'GPS Tracker', 'FMC920', 'FLAGGED', 'BARU', 'Test User', '2026-06-21 23:14:39', NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-22 00:41:49'),
(27, '862129082868757', '359020157369221', 'GPS Tracker', 'FMC920', 'IN_STOCK', 'BARU', 'Test User', '2026-06-22 00:47:24', NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-22 00:47:24'),
(28, '862129082868138', '353140715510272', 'GPS Tracker', 'FMC920', 'IN_STOCK', 'BARU', 'Test User', '2026-06-22 00:47:28', NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-22 00:47:28'),
(29, '862129083377162', '358247329262826', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(30, '862129083378475', '354195345345285', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(31, '862129083377014', '351735148930873', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(32, '862129083378392', '352347438190652', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(33, '862129085157117', '358502234922671', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(34, '862129082868831', '353282474903869', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(35, '862129083378145', '359082098209666', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(36, '862129082868724', '354691151121787', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(37, '862129083377121', '354242435410357', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(38, '862129083333199', '353952166239091', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(39, '862129085761355', '354895795509455', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(40, '862129083378426', '356772710965117', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(41, '862129082868286', '354115922031379', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(42, '862129083378335', '356017668446808', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(43, '862129083375026', '356660604697197', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(44, '862129082868195', '354682926092170', 'GPS Tracker', 'FMC920', 'PENDING_QC', 'BARU', NULL, NULL, NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(45, '864022082143016', '358410021807940', 'GPS Tracker', 'FMC920', 'IN_STOCK', 'BARU', 'Test User', '2026-06-22 02:05:06', NULL, 'Warehouse WH-REG-EAST', 'WH-REG-EAST', NULL, NULL, '2026-06-22 02:01:46', '2026-06-22 02:05:06'),
(46, '123123123123', '355475717460507', 'GPS Tracker', 'FMC130', 'IN_STOCK', 'BARU', 'Test User', '2026-06-22 02:39:29', NULL, 'Warehouse WH-PUSAT', 'WH-PUSAT', NULL, NULL, '2026-06-22 02:39:18', '2026-06-22 02:39:29'),
(47, '121231231231111111', '356867729971999', 'GPS Tracker', 'GT06N', 'IN_STOCK', 'BARU', 'Handi', '2026-06-22 02:42:16', NULL, 'Warehouse WH-AREA-SWK', 'WH-AREA-SWK', NULL, NULL, '2026-06-22 02:42:08', '2026-06-22 02:42:16');

-- --------------------------------------------------------

--
-- Table structure for table `device_inspections`
--

CREATE TABLE `device_inspections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `condition` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `qc_result` varchar(255) NOT NULL,
  `operator` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `device_inspections`
--

INSERT INTO `device_inspections` (`id`, `device_id`, `condition`, `notes`, `qc_result`, `operator`, `created_at`, `updated_at`) VALUES
(1, 26, 'UNKNOWN', 'Mati total', 'FAILED', 'QC Officer', '2026-06-22 00:41:49', '2026-06-22 00:41:49');

-- --------------------------------------------------------

--
-- Table structure for table `device_models`
--

CREATE TABLE `device_models` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `brand` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `min_stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `device_models`
--

INSERT INTO `device_models` (`id`, `brand`, `type`, `model`, `min_stock`, `created_at`, `updated_at`) VALUES
(1, 'Teltonika', 'GPS Tracker', 'FMC130', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(2, 'Teltonika', 'GPS Tracker', 'FMC920', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(3, 'Teltonika', 'GPS Tracker', 'FMB120', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(4, 'Ruptela', 'GPS Tracker', 'Trace5', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(5, 'Ruptela', 'GPS Tracker', 'HCV5', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(6, 'Concox', 'GPS Tracker', 'GT06N', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(7, 'Concox', 'Dashcam', 'JC400', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(8, 'Howen', 'MDVR', 'Hero-ME41-04', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(9, 'Streamax', 'MDVR', 'X3-H04', 0, '2026-06-20 10:42:00', '2026-06-20 10:42:00'),
(10, 'Atelematics', 'MDVR', 'AT-525', 0, '2026-06-21 16:26:29', '2026-06-21 16:26:29'),
(11, 'Atelematics', 'E-SEAL', 'AT-16', 0, '2026-06-21 20:00:13', '2026-06-21 20:00:13'),
(12, 'Atelematics', 'E-SEAL', 'AT-10', 0, '2026-06-21 20:00:25', '2026-06-21 20:00:25');

-- --------------------------------------------------------

--
-- Table structure for table `device_transactions`
--

CREATE TABLE `device_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `device_sn` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `from_location` varchar(255) NOT NULL,
  `to_location` varchar(255) NOT NULL,
  `operator` varchar(255) NOT NULL,
  `scanned_by` varchar(255) NOT NULL,
  `via_web` tinyint(1) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `device_transactions`
--

INSERT INTO `device_transactions` (`id`, `device_id`, `device_sn`, `action`, `from_location`, `to_location`, `operator`, `scanned_by`, `via_web`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'GPS-982173812', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-17 02:51:22', '2026-06-19 02:51:22'),
(2, 2, 'MDVR-88291', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-18 02:51:22', '2026-06-19 02:51:22'),
(3, 4, 'DEMO-GPS-0001', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-14 19:44:37', '2026-06-14 19:44:37'),
(4, 5, 'DEMO-GPS-0002', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-08 19:44:37', '2026-06-08 19:44:37'),
(5, 6, 'DEMO-GPS-0003', 'RECEIVING', 'Supplier', 'WH-REG-WEST', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-18 19:44:37', '2026-06-18 19:44:37'),
(6, 7, 'DEMO-GPS-0004', 'RECEIVING', 'Supplier', 'WH-REG-EAST', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-28 19:44:37', '2026-05-28 19:44:37'),
(7, 8, 'DEMO-MDR-0005', 'RECEIVING', 'Supplier', 'WH-AREA-SUB', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-10 19:44:37', '2026-06-10 19:44:37'),
(8, 9, 'DEMO-MDR-0006', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-13 19:44:37', '2026-06-13 19:44:37'),
(9, 10, 'DEMO-CAM-0007', 'RECEIVING', 'Supplier', 'WH-AREA-MLG', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-26 19:44:37', '2026-05-26 19:44:37'),
(10, 11, 'DEMO-GPS-0008', 'RECEIVING', 'Supplier', 'WH-AREA-SDA', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-01 19:44:37', '2026-06-01 19:44:37'),
(11, 12, 'DEMO-GPS-0009', 'RECEIVING', 'Supplier', 'WH-REG-WEST', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-09 19:44:37', '2026-06-09 19:44:37'),
(12, 13, 'DEMO-GPS-0010', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-05 19:44:37', '2026-06-05 19:44:37'),
(13, 13, 'DEMO-GPS-0010', 'ISSUED', 'WH-PUSAT', 'Technician: Budi Santoso', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-08 19:44:37', '2026-06-08 19:44:37'),
(14, 14, 'DEMO-GPS-0011', 'RECEIVING', 'Supplier', 'WH-REG-WEST', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-08 19:44:37', '2026-06-08 19:44:37'),
(15, 14, 'DEMO-GPS-0011', 'ISSUED', 'WH-REG-WEST', 'Technician: John Doe', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-11 19:44:37', '2026-06-11 19:44:37'),
(16, 15, 'DEMO-MDR-0012', 'RECEIVING', 'Supplier', 'WH-AREA-SUB', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-14 19:44:37', '2026-06-14 19:44:37'),
(17, 15, 'DEMO-MDR-0012', 'ISSUED', 'WH-AREA-SUB', 'Technician: Jane Smith', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-17 19:44:37', '2026-06-17 19:44:37'),
(18, 16, 'DEMO-CAM-0013', 'RECEIVING', 'Supplier', 'WH-AREA-MLG', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-15 19:44:37', '2026-06-15 19:44:37'),
(19, 16, 'DEMO-CAM-0013', 'ISSUED', 'WH-AREA-MLG', 'Technician: Ahmad Rian', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-18 19:44:37', '2026-06-18 19:44:37'),
(20, 17, 'DEMO-GPS-0014', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-12 19:44:37', '2026-06-12 19:44:37'),
(21, 17, 'DEMO-GPS-0014', 'ISSUED', 'WH-PUSAT', 'Technician: Budi Santoso', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-15 19:44:37', '2026-06-15 19:44:37'),
(22, 18, 'DEMO-GPS-0015', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-28 19:44:37', '2026-05-28 19:44:37'),
(23, 18, 'DEMO-GPS-0015', 'ISSUED', 'WH-PUSAT', 'Plat L 1234 AB', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-31 19:44:37', '2026-05-31 19:44:37'),
(24, 19, 'DEMO-MDR-0016', 'RECEIVING', 'Supplier', 'WH-AREA-SUB', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-30 19:44:37', '2026-05-30 19:44:37'),
(25, 19, 'DEMO-MDR-0016', 'ISSUED', 'WH-AREA-SUB', 'Plat W 5678 CD', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-02 19:44:37', '2026-06-02 19:44:37'),
(26, 20, 'DEMO-CAM-0017', 'RECEIVING', 'Supplier', 'WH-AREA-MLG', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-02 19:44:37', '2026-06-02 19:44:37'),
(27, 20, 'DEMO-CAM-0017', 'ISSUED', 'WH-AREA-MLG', 'Plat N 9012 EF', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-05 19:44:37', '2026-06-05 19:44:37'),
(28, 21, 'DEMO-GPS-0018', 'RECEIVING', 'Supplier', 'WH-REG-EAST', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-11 19:44:37', '2026-06-11 19:44:37'),
(29, 21, 'DEMO-GPS-0018', 'TRANSFER_OUT', 'WH-REG-EAST', 'In Transit to WH-AREA-MLG', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-14 19:44:37', '2026-06-14 19:44:37'),
(30, 22, 'DEMO-GPS-0019', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-13 19:44:37', '2026-06-13 19:44:37'),
(31, 22, 'DEMO-GPS-0019', 'TRANSFER_OUT', 'WH-PUSAT', 'In Transit to WH-REG-WEST', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-16 19:44:37', '2026-06-16 19:44:37'),
(32, 23, 'DEMO-MDR-0020', 'RECEIVING', 'Supplier', 'WH-AREA-SUB', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-09 19:44:37', '2026-06-09 19:44:37'),
(33, 23, 'DEMO-MDR-0020', 'ISSUED', 'WH-AREA-SUB', 'Warehouse WH-AREA-SUB', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-12 19:44:37', '2026-06-12 19:44:37'),
(34, 4, 'DEMO-GPS-0001', 'ISSUED', 'WH-PUSAT', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-23 19:44:37', '2026-05-23 19:44:37'),
(35, 5, 'DEMO-GPS-0002', 'ISSUED', 'WH-PUSAT', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-25 19:44:37', '2026-05-25 19:44:37'),
(36, 6, 'DEMO-GPS-0003', 'TRANSFER_OUT', 'WH-REG-WEST', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-27 19:44:37', '2026-05-27 19:44:37'),
(37, 8, 'DEMO-MDR-0005', 'ISSUED', 'WH-AREA-SUB', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-29 19:44:37', '2026-05-29 19:44:37'),
(38, 7, 'DEMO-GPS-0004', 'ISSUED', 'WH-REG-EAST', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-05-30 19:44:37', '2026-05-30 19:44:37'),
(39, 10, 'DEMO-CAM-0007', 'ISSUED', 'WH-AREA-MLG', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-01 19:44:37', '2026-06-01 19:44:37'),
(40, 11, 'DEMO-GPS-0008', 'TRANSFER_OUT', 'WH-AREA-SDA', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-03 19:44:37', '2026-06-03 19:44:37'),
(41, 9, 'DEMO-MDR-0006', 'ISSUED', 'WH-PUSAT', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-06 19:44:37', '2026-06-06 19:44:37'),
(42, 12, 'DEMO-GPS-0009', 'ISSUED', 'WH-REG-WEST', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-07 19:44:37', '2026-06-07 19:44:37'),
(43, 4, 'DEMO-GPS-0001', 'ISSUED', 'WH-PUSAT', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-09 19:44:37', '2026-06-09 19:44:37'),
(44, 5, 'DEMO-GPS-0002', 'TRANSFER_OUT', 'WH-PUSAT', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-10 19:44:37', '2026-06-10 19:44:37'),
(45, 10, 'DEMO-CAM-0007', 'ISSUED', 'WH-AREA-MLG', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-13 19:44:37', '2026-06-13 19:44:37'),
(46, 8, 'DEMO-MDR-0005', 'ISSUED', 'WH-AREA-SUB', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-14 19:44:37', '2026-06-14 19:44:37'),
(47, 6, 'DEMO-GPS-0003', 'ISSUED', 'WH-REG-WEST', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-19 19:44:37', '2026-06-19 19:44:37'),
(48, 12, 'DEMO-GPS-0009', 'ISSUED', 'WH-REG-WEST', 'Field Operation', 'Super Admin', 'Scanner-HID-01', 1, NULL, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(50, 1, 'GPS-982173812', 'ISSUED', 'WH-PUSAT', 'Technician: Budi Santoso', 'Warehouse Operator', 'Scanner-HID-01', 1, 'TT-260621-044846-A50B', '2026-06-20 21:48:46', '2026-06-20 21:48:46'),
(51, 2, 'MDVR-88291', 'ISSUED', 'WH-PUSAT', 'Technician: Budi Santoso', 'Warehouse Operator', 'Scanner-HID-01', 1, 'TT-260621-044927-2F25', '2026-06-20 21:49:27', '2026-06-20 21:49:27'),
(52, 4, 'DEMO-GPS-0001', 'ISSUED', 'WH-PUSAT', 'Technician: Budi Santoso', 'Warehouse Operator', 'Scanner-HID-01', 1, 'TT-260621-045028-68EB', '2026-06-20 21:50:28', '2026-06-20 21:50:28'),
(53, 5, 'DEMO-GPS-0002', 'ISSUED', 'WH-PUSAT', 'Technician: Budi Santoso', 'Warehouse Operator', 'Scanner-HID-01', 1, 'TT-260621-045327-AA48', '2026-06-20 21:53:27', '2026-06-20 21:53:27'),
(54, 25, '862129083377238', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(55, 26, '862129083377105', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(56, 27, '862129082868757', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(57, 28, '862129082868138', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(58, 29, '862129083377162', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(59, 30, '862129083378475', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(60, 31, '862129083377014', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(61, 32, '862129083378392', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(62, 33, '862129085157117', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(63, 34, '862129082868831', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(64, 35, '862129083378145', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(65, 36, '862129082868724', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(66, 37, '862129083377121', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(67, 38, '862129083333199', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(68, 39, '862129085761355', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(69, 40, '862129083378426', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(70, 41, '862129082868286', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(71, 42, '862129083378335', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(72, 43, '862129083375026', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(73, 44, '862129082868195', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-21 22:56:18', '2026-06-21 22:56:18'),
(74, 25, '862129083377238', 'QC_PASSED', 'QC Penerimaan', 'WH-PUSAT', 'Test User', 'System', 1, 'QC OK', '2026-06-21 22:57:23', '2026-06-21 22:57:23'),
(75, 26, '862129083377105', 'QC_PASSED', 'QC Penerimaan', 'WH-PUSAT', 'Test User', 'System', 1, 'QC OK', '2026-06-21 23:14:39', '2026-06-21 23:14:39'),
(76, 26, '862129083377105', 'ADJUSTMENT', 'IN_STOCK @ WH-PUSAT (Warehouse WH-PUSAT)', 'FLAGGED @ WH-PUSAT (Warehouse WH-PUSAT)', 'Admin Gudang', 'Manual', 1, 'Rusak bolo ini alatnya', '2026-06-21 23:16:37', '2026-06-21 23:16:37'),
(77, 26, '862129083377105', 'RETURNED', 'Warehouse WH-PUSAT', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, NULL, '2026-06-22 00:41:20', '2026-06-22 00:41:20'),
(78, 26, '862129083377105', 'QC_FAILED', 'QC Room', 'Flagged Storage', 'QC Officer', 'System', 1, NULL, '2026-06-22 00:41:49', '2026-06-22 00:41:49'),
(79, 27, '862129082868757', 'QC_PASSED', 'QC Penerimaan', 'WH-PUSAT', 'Test User', 'System', 1, 'QC OK', '2026-06-22 00:47:24', '2026-06-22 00:47:24'),
(80, 28, '862129082868138', 'QC_PASSED', 'QC Penerimaan', 'WH-PUSAT', 'Test User', 'System', 1, 'QC OK', '2026-06-22 00:47:28', '2026-06-22 00:47:28'),
(81, 45, '864022082143016', 'RECEIVING', 'Supplier', 'WH-REG-EAST', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-22 02:01:46', '2026-06-22 02:01:46'),
(82, 45, '864022082143016', 'QC_PASSED', 'QC Penerimaan', 'WH-REG-EAST', 'Test User', 'System', 1, 'QC OK', '2026-06-22 02:05:06', '2026-06-22 02:05:06'),
(83, 46, '123123123123', 'RECEIVING', 'Supplier', 'WH-PUSAT', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-22 02:39:18', '2026-06-22 02:39:18'),
(84, 46, '123123123123', 'QC_PASSED', 'QC Penerimaan', 'WH-PUSAT', 'Test User', 'System', 1, 'QC OK', '2026-06-22 02:39:29', '2026-06-22 02:39:29'),
(85, 47, '121231231231111111', 'RECEIVING', 'Supplier', 'WH-AREA-SWK', 'Warehouse Operator', 'Scanner-HID-01', 1, 'Kondisi: BARU | Menunggu QC', '2026-06-22 02:42:08', '2026-06-22 02:42:08'),
(86, 47, '121231231231111111', 'QC_PASSED', 'QC Penerimaan', 'WH-AREA-SWK', 'Handi', 'System', 1, 'QC OK', '2026-06-22 02:42:16', '2026-06-22 02:42:16');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gsm_simcards`
--

CREATE TABLE `gsm_simcards` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `msisdn` varchar(255) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'IN_STOCK',
  `warehouse_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gsm_simcards`
--

INSERT INTO `gsm_simcards` (`id`, `msisdn`, `provider`, `category`, `status`, `warehouse_code`, `created_at`, `updated_at`) VALUES
(1, '6281122334455', 'Telkomsel', 'Halo', 'IN_STOCK', NULL, '2026-06-19 02:51:22', '2026-06-20 10:01:50'),
(2, '6281223344556', 'Indosat Ooredoo', 'B2B', 'IN_STOCK', 'WH-REG-EAST', '2026-06-19 02:51:22', '2026-06-21 11:02:51'),
(3, '6281323344557', 'XL Axiata', 'XL Biz', 'IN_STOCK', 'WH-REG-EAST', '2026-06-19 02:51:22', '2026-06-21 11:02:51'),
(5, '081315507667', 'Telkomsel', 'Data', 'IN_STOCK', 'WH-REG-EAST', '2026-06-21 09:50:24', '2026-06-21 10:41:12');

-- --------------------------------------------------------

--
-- Table structure for table `holder_accessories`
--

CREATE TABLE `holder_accessories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `holder_type` varchar(255) NOT NULL,
  `holder_code` varchar(255) NOT NULL,
  `holder_name` varchar(255) DEFAULT NULL,
  `accessory_code` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_06_19_000001_create_dlms_schema', 1),
(5, '2026_06_19_000002_create_gsm_simcards_and_installations', 1),
(6, '2026_06_19_071111_create_personal_access_tokens_table', 1),
(7, '2026_06_21_000001_create_app_settings_table', 2),
(8, '2026_06_21_000002_create_device_models_table', 3),
(9, '2026_06_20_183326_apply_gap_analysis_changes', 4),
(10, '2026_06_20_210810_create_delivery_order_accessories_table', 5),
(11, '2026_06_20_210810_create_warehouse_accessories_table', 6),
(12, '2026_06_21_021417_create_stock_alert_thresholds_table', 7),
(13, '2026_06_21_110000_add_notes_to_transactions', 8),
(14, '2026_06_21_120000_add_role_to_users', 9),
(15, '2026_06_21_120000_create_holder_accessories_table', 10),
(16, '2026_06_21_130000_add_warehouse_to_simcards_and_create_simcard_transactions', 11),
(17, '2026_06_21_140000_create_delivery_order_simcards_table', 12),
(18, '2026_06_22_010000_add_condition_to_devices', 13),
(19, '2026_06_22_000000_add_area_to_technicians_table', 14),
(20, '2026_06_22_020000_add_qc_fields_to_devices', 15),
(21, '2026_06_22_095838_add_min_stock_to_device_models_table', 16);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('247JPUwESwIqXaJWJo7qoZTAT9e40f5QP5iMpkWK', 5, '192.168.1.21', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 'eyJfdG9rZW4iOiJySHJ6ZjhpeERrM0ZmTlVhaTcyMkEzZWVtYXpOejBVSG1ON0VpazY4IiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzE5Mi4xNjguMS4yMTo4MDgwXC91c2VycyIsInJvdXRlIjoidXNlcnMuaW5kZXgifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6NSwiYWN0aXZlX3dhcmVob3VzZV9jb2RlIjoiV0gtQVJFQS1NTEciLCJhY3RpdmVfd2FyZWhvdXNlX25hbWUiOiJNYWxhbmciLCJhY3RpdmVfd2FyZWhvdXNlX3R5cGUiOiJDQUJBTkcifQ==', 1782375114),
('7xqLmGVeo44REwfbwbkeS8rVDm31NJLqm0TOeqnR', 1, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', 'eyJfdG9rZW4iOiJkRENwZTJnYUZhVURnVjZJWklzYkVVOXRlcVVqUXRCUXU2TVRtUEVSIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwXC90cmFuc2ZlciIsInJvdXRlIjoidHJhbnNmZXIifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MSwiYWN0aXZlX3dhcmVob3VzZV9jb2RlIjoiV0gtUFVTQVQiLCJhY3RpdmVfd2FyZWhvdXNlX25hbWUiOiJXYXJlaG91c2UgUHVzYXQiLCJhY3RpdmVfd2FyZWhvdXNlX3R5cGUiOiJQVVNBVCJ9', 1782370664),
('anIrCsLAmlPO5M2fobKCmDAXOF29gf0I2Ogv3sGg', 5, '192.168.1.19', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJBSFRRR3dWUHdYN3VnSE9teGVRbDU2Q3ZrNzVsSlZOZER2Qjd1QmNIIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzE5Mi4xNjguMS4xOTo4MDgwXC90cmFuc2ZlciIsInJvdXRlIjoidHJhbnNmZXIifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6NSwiYWN0aXZlX3dhcmVob3VzZV9jb2RlIjoiV0gtQVJFQS1NTEciLCJhY3RpdmVfd2FyZWhvdXNlX25hbWUiOiJNYWxhbmciLCJhY3RpdmVfd2FyZWhvdXNlX3R5cGUiOiJDQUJBTkcifQ==', 1782375792),
('NErBqI8ygWSeA52De2lt1TsXEYtMigZ9grUW1bAm', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.8.23 Chrome/144.0.7559.236 Electron/40.10.3 Safari/537.36', 'eyJfdG9rZW4iOiI2Qmg2a0ZBUnQxSE9FZkZNd2RKOU1rRjVhMUxUQlFIR2RXcjI4N1ozIiwidXJsIjpbXSwiX3ByZXZpb3VzIjp7InVybCI6Imh0dHA6XC9cLzEyNy4wLjAuMTo4MDAwIiwicm91dGUiOiJkYXNoYm9hcmQifSwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI6MSwiYWN0aXZlX3dhcmVob3VzZV9jb2RlIjoiV0gtUFVTQVQiLCJhY3RpdmVfd2FyZWhvdXNlX25hbWUiOiJXYXJlaG91c2UgUHVzYXQiLCJhY3RpdmVfd2FyZWhvdXNlX3R5cGUiOiJQVVNBVCJ9', 1782369977),
('yi0xkQMX0U8QKHrn996D4AtNn92lQCAkUw9Oydwi', 1, '192.168.1.21', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'eyJfdG9rZW4iOiJRdlZHNWh2SWhGZm5zYzhDWExMaEo5UXBRd3FqT0VJT1NxeG9GUGRzIiwiX2ZsYXNoIjp7Im9sZCI6W10sIm5ldyI6W119LCJfcHJldmlvdXMiOnsidXJsIjoiaHR0cDpcL1wvMTkyLjE2OC4xLjIxOjgwODBcLz92aWV3PVdILUFSRUEtTUxHIiwicm91dGUiOiJkYXNoYm9hcmQifSwibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiOjEsImFjdGl2ZV93YXJlaG91c2VfY29kZSI6IldILVBVU0FUIiwiYWN0aXZlX3dhcmVob3VzZV9uYW1lIjoiV2FyZWhvdXNlIFB1c2F0IiwiYWN0aXZlX3dhcmVob3VzZV90eXBlIjoiUFVTQVQifQ==', 1782375072);

-- --------------------------------------------------------

--
-- Table structure for table `simcard_transactions`
--

CREATE TABLE `simcard_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `gsm_simcard_id` bigint(20) UNSIGNED DEFAULT NULL,
  `msisdn` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `warehouse_code` varchar(255) DEFAULT NULL,
  `operator` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `simcard_transactions`
--

INSERT INTO `simcard_transactions` (`id`, `gsm_simcard_id`, `msisdn`, `action`, `from_location`, `to_location`, `warehouse_code`, `operator`, `notes`, `created_at`, `updated_at`) VALUES
(2, 5, '081315507667', 'RECEIVING', 'Supplier', 'WH-REG-EAST', 'WH-REG-EAST', 'Super Administrator', NULL, '2026-06-21 09:50:24', '2026-06-21 09:50:24'),
(3, 5, '081315507667', 'RECEIVING', 'Supplier', 'WH-REG-EAST', 'WH-REG-EAST', 'Super Administrator', NULL, '2026-06-21 09:50:48', '2026-06-21 09:50:48'),
(6, 2, '6281223344556', 'RECEIVING', 'Pool', 'WH-REG-EAST', 'WH-REG-EAST', 'Super Administrator', NULL, '2026-06-21 11:02:51', '2026-06-21 11:02:51'),
(7, 3, '6281323344557', 'RECEIVING', 'Pool', 'WH-REG-EAST', 'WH-REG-EAST', 'Super Administrator', NULL, '2026-06-21 11:02:51', '2026-06-21 11:02:51');

-- --------------------------------------------------------

--
-- Table structure for table `stock_alert_thresholds`
--

CREATE TABLE `stock_alert_thresholds` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_code` varchar(255) NOT NULL,
  `item_type` varchar(255) NOT NULL,
  `item_identifier` varchar(255) NOT NULL,
  `min_stock_level` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_alert_thresholds`
--

INSERT INTO `stock_alert_thresholds` (`id`, `warehouse_code`, `item_type`, `item_identifier`, `min_stock_level`, `created_at`, `updated_at`) VALUES
(1, 'WH-REG-EAST', 'DEVICE', 'GT06N', 3, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(2, 'WH-AREA-MLG', 'DEVICE', 'FMC130', 2, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(3, 'WH-PUSAT', 'ACCESSORY', 'ACC-ANTENNA', 5, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(4, 'WH-REG-WEST', 'ACCESSORY', 'ACC-MOUNT', 5, '2026-06-20 19:44:37', '2026-06-20 19:44:37');

-- --------------------------------------------------------

--
-- Table structure for table `technicians`
--

CREATE TABLE `technicians` (
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `area` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `technicians`
--

INSERT INTO `technicians` (`code`, `name`, `area`, `created_at`, `updated_at`) VALUES
('TECH-001', 'Budi Santoso', 'Malang', '2026-06-19 02:51:22', '2026-06-21 15:58:31'),
('TECH-002', 'John Doe', 'Surabaya', '2026-06-19 02:51:22', '2026-06-21 15:58:37'),
('TECH-003', 'Jane Smith', 'Tuban', '2026-06-19 02:51:22', '2026-06-21 15:58:45'),
('TECH-004', 'Ahmad Rian', 'Semarang', '2026-06-19 02:51:22', '2026-06-21 15:58:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'warehouse_admin',
  `warehouse_code` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `warehouse_code`, `is_active`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Test User', 'test@example.com', '2026-06-19 02:51:21', '$2y$12$cRrIVkKfFb28SxrNvxpeCenYHsewBVhxE9X8eOIE36PBJX7LhRaXi', 'super_admin', NULL, 1, 'TawWfnybzbBAu1RuFD7AQlSWHAkFvJibEdiX4VOos9oR0R4vAMAul3xqD8Sp', '2026-06-19 02:51:22', '2026-06-20 21:10:33'),
(2, 'Super Administrator', 'super@dlms.test', NULL, '$2y$12$LVpk.HSx7xqFpMRlTtJuJev4.hehqs.5PscT4UVR6rCEU4MgZReGC', 'super_admin', NULL, 1, NULL, '2026-06-20 21:10:33', '2026-06-20 21:10:33'),
(3, 'Admin Gudang', 'gudang@dlms.test', NULL, '$2y$12$LTFHR9C1gR2MdFb9.W7iw.0hQgV18h85toheZ4r9QrP3IJUJEiH/.', 'warehouse_admin', 'WH-AREA-MLG', 1, NULL, '2026-06-20 21:10:33', '2026-06-20 21:10:33'),
(4, 'Handi', 'Handi@gmail.com', NULL, '$2y$12$hid0ft5VNgE4KoRgZCK9Je7D2Ht3QuAB4IZWcG9Fh4H46aD3mpp0O', 'warehouse_admin', 'WH-AREA-MKS', 1, NULL, '2026-06-22 02:41:27', '2026-06-22 02:41:27'),
(5, 'Fajar', 'fajar@gmail.com', NULL, '$2y$12$TXxBKCcEOPQjSrvAP.A9DOI/ArRTzRmSTKYJ/I/tCzc0HZA/fqD2.', 'warehouse_admin', 'WH-AREA-MLG', 1, NULL, '2026-06-25 01:09:53', '2026-06-25 01:09:53');

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'CABANG',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`code`, `name`, `type`, `created_at`, `updated_at`) VALUES
('WH-AREA-MKS', 'Makassar', 'CABANG', '2026-06-21 16:22:56', '2026-06-21 16:22:56'),
('WH-AREA-MLG', 'Malang', 'CABANG', '2026-06-19 02:51:22', '2026-06-21 16:22:19'),
('WH-AREA-SMG', 'Semarang', 'CABANG', '2026-06-21 16:22:11', '2026-06-21 16:22:11'),
('WH-AREA-SWK', 'Sorowako', 'CABANG', '2026-06-21 16:23:19', '2026-06-21 16:23:19'),
('WH-AREA-TBN', 'Tuban', 'CABANG', '2026-06-21 16:22:43', '2026-06-21 16:22:43'),
('WH-AREA-YGK', 'Yogjakarta', 'CABANG', '2026-06-21 16:24:18', '2026-06-21 16:24:18'),
('WH-PUSAT', 'Warehouse Pusat', 'PUSAT', '2026-06-19 02:51:22', '2026-06-21 16:24:37'),
('WH-REG-EAST', 'East Area', 'REGIONAL', '2026-06-19 02:51:22', '2026-06-21 16:24:52'),
('WH-REG-WEST', 'West Area', 'REGIONAL', '2026-06-19 02:51:22', '2026-06-21 16:25:07');

-- --------------------------------------------------------

--
-- Table structure for table `warehouse_accessories`
--

CREATE TABLE `warehouse_accessories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `warehouse_code` varchar(255) NOT NULL,
  `accessory_code` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `warehouse_accessories`
--

INSERT INTO `warehouse_accessories` (`id`, `warehouse_code`, `accessory_code`, `qty`, `created_at`, `updated_at`) VALUES
(1, 'WH-PUSAT', 'ACC-ANTENNA', 85, '2026-06-20 19:44:37', '2026-06-21 13:27:11'),
(2, 'WH-REG-WEST', 'ACC-CABLE', 40, '2026-06-20 19:44:37', '2026-06-20 19:44:37'),
(3, 'WH-REG-EAST', 'ACC-ANTENNA', 0, '2026-06-20 21:02:17', '2026-06-20 21:02:17'),
(4, 'WH-PUSAT', 'ACC-CABLE', 110, '2026-06-21 13:27:11', '2026-06-21 13:28:24'),
(5, 'WH-PUSAT', 'ACC-MOUNT', 60, '2026-06-21 13:27:11', '2026-06-21 13:27:11'),
(6, 'WH-PUSAT', 'ACC-RELAY', 500, '2026-06-21 13:27:11', '2026-06-21 13:27:11'),
(7, 'WH-PUSAT', 'ACC-RFID Mifare', 30, '2026-06-21 13:27:11', '2026-06-21 13:27:11'),
(8, 'WH-PUSAT', 'ACC-SUHU', 100, '2026-06-21 13:27:11', '2026-06-21 13:27:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accessories`
--
ALTER TABLE `accessories`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `accessory_transactions`
--
ALTER TABLE `accessory_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `accessory_transactions_accessory_code_foreign` (`accessory_code`);

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customer_devices`
--
ALTER TABLE `customer_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_devices_customer_id_foreign` (`customer_id`),
  ADD KEY `customer_devices_device_id_foreign` (`device_id`);

--
-- Indexes for table `delivery_orders`
--
ALTER TABLE `delivery_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_orders_from_warehouse_code_foreign` (`from_warehouse_code`),
  ADD KEY `delivery_orders_to_warehouse_code_foreign` (`to_warehouse_code`);

--
-- Indexes for table `delivery_order_accessories`
--
ALTER TABLE `delivery_order_accessories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `do_acc_unique` (`delivery_order_id`,`accessory_code`),
  ADD KEY `delivery_order_accessories_accessory_code_foreign` (`accessory_code`);

--
-- Indexes for table `delivery_order_devices`
--
ALTER TABLE `delivery_order_devices`
  ADD PRIMARY KEY (`delivery_order_id`,`device_id`),
  ADD KEY `delivery_order_devices_device_id_foreign` (`device_id`);

--
-- Indexes for table `delivery_order_simcards`
--
ALTER TABLE `delivery_order_simcards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `do_sim_unique` (`delivery_order_id`,`gsm_simcard_id`),
  ADD KEY `delivery_order_simcards_gsm_simcard_id_index` (`gsm_simcard_id`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `devices_serial_number_unique` (`serial_number`),
  ADD UNIQUE KEY `devices_imei_unique` (`imei`),
  ADD KEY `devices_serial_number_index` (`serial_number`),
  ADD KEY `devices_imei_index` (`imei`),
  ADD KEY `devices_status_index` (`status`),
  ADD KEY `devices_warehouse_code_foreign` (`warehouse_code`),
  ADD KEY `devices_gsm_simcard_id_foreign` (`gsm_simcard_id`),
  ADD KEY `devices_vehicle_plate_index` (`vehicle_plate`),
  ADD KEY `devices_unit_condition_index` (`unit_condition`);

--
-- Indexes for table `device_inspections`
--
ALTER TABLE `device_inspections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_inspections_device_id_foreign` (`device_id`);

--
-- Indexes for table `device_models`
--
ALTER TABLE `device_models`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_models_model_unique` (`model`);

--
-- Indexes for table `device_transactions`
--
ALTER TABLE `device_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_transactions_device_id_index` (`device_id`),
  ADD KEY `device_transactions_device_sn_index` (`device_sn`),
  ADD KEY `device_transactions_device_id_created_at_index` (`device_id`,`created_at`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  ADD KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`);

--
-- Indexes for table `gsm_simcards`
--
ALTER TABLE `gsm_simcards`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gsm_simcards_msisdn_unique` (`msisdn`),
  ADD KEY `gsm_simcards_warehouse_code_index` (`warehouse_code`);

--
-- Indexes for table `holder_accessories`
--
ALTER TABLE `holder_accessories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `holder_acc_unique` (`holder_type`,`holder_code`,`accessory_code`),
  ADD KEY `holder_accessories_holder_type_holder_code_index` (`holder_type`,`holder_code`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  ADD KEY `personal_access_tokens_expires_at_index` (`expires_at`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `simcard_transactions`
--
ALTER TABLE `simcard_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `simcard_transactions_gsm_simcard_id_foreign` (`gsm_simcard_id`),
  ADD KEY `simcard_transactions_warehouse_code_action_index` (`warehouse_code`,`action`);

--
-- Indexes for table `stock_alert_thresholds`
--
ALTER TABLE `stock_alert_thresholds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wh_item_unique` (`warehouse_code`,`item_type`,`item_identifier`);

--
-- Indexes for table `technicians`
--
ALTER TABLE `technicians`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `warehouse_accessories`
--
ALTER TABLE `warehouse_accessories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `warehouse_accessories_warehouse_code_accessory_code_unique` (`warehouse_code`,`accessory_code`),
  ADD KEY `warehouse_accessories_accessory_code_foreign` (`accessory_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accessory_transactions`
--
ALTER TABLE `accessory_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_devices`
--
ALTER TABLE `customer_devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_order_accessories`
--
ALTER TABLE `delivery_order_accessories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_order_simcards`
--
ALTER TABLE `delivery_order_simcards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `device_inspections`
--
ALTER TABLE `device_inspections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `device_models`
--
ALTER TABLE `device_models`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `device_transactions`
--
ALTER TABLE `device_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gsm_simcards`
--
ALTER TABLE `gsm_simcards`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `holder_accessories`
--
ALTER TABLE `holder_accessories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `simcard_transactions`
--
ALTER TABLE `simcard_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `stock_alert_thresholds`
--
ALTER TABLE `stock_alert_thresholds`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `warehouse_accessories`
--
ALTER TABLE `warehouse_accessories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `accessory_transactions`
--
ALTER TABLE `accessory_transactions`
  ADD CONSTRAINT `accessory_transactions_accessory_code_foreign` FOREIGN KEY (`accessory_code`) REFERENCES `accessories` (`code`) ON DELETE CASCADE;

--
-- Constraints for table `customer_devices`
--
ALTER TABLE `customer_devices`
  ADD CONSTRAINT `customer_devices_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_devices_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_orders`
--
ALTER TABLE `delivery_orders`
  ADD CONSTRAINT `delivery_orders_from_warehouse_code_foreign` FOREIGN KEY (`from_warehouse_code`) REFERENCES `warehouses` (`code`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_orders_to_warehouse_code_foreign` FOREIGN KEY (`to_warehouse_code`) REFERENCES `warehouses` (`code`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_order_accessories`
--
ALTER TABLE `delivery_order_accessories`
  ADD CONSTRAINT `delivery_order_accessories_accessory_code_foreign` FOREIGN KEY (`accessory_code`) REFERENCES `accessories` (`code`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_order_accessories_delivery_order_id_foreign` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_order_devices`
--
ALTER TABLE `delivery_order_devices`
  ADD CONSTRAINT `delivery_order_devices_delivery_order_id_foreign` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_order_devices_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `devices_gsm_simcard_id_foreign` FOREIGN KEY (`gsm_simcard_id`) REFERENCES `gsm_simcards` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `devices_warehouse_code_foreign` FOREIGN KEY (`warehouse_code`) REFERENCES `warehouses` (`code`) ON DELETE CASCADE;

--
-- Constraints for table `device_inspections`
--
ALTER TABLE `device_inspections`
  ADD CONSTRAINT `device_inspections_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `simcard_transactions`
--
ALTER TABLE `simcard_transactions`
  ADD CONSTRAINT `simcard_transactions_gsm_simcard_id_foreign` FOREIGN KEY (`gsm_simcard_id`) REFERENCES `gsm_simcards` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `warehouse_accessories`
--
ALTER TABLE `warehouse_accessories`
  ADD CONSTRAINT `warehouse_accessories_accessory_code_foreign` FOREIGN KEY (`accessory_code`) REFERENCES `accessories` (`code`) ON DELETE CASCADE,
  ADD CONSTRAINT `warehouse_accessories_warehouse_code_foreign` FOREIGN KEY (`warehouse_code`) REFERENCES `warehouses` (`code`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
