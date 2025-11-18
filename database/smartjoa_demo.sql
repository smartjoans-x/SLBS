-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 18, 2025 at 03:26 AM
-- Server version: 10.6.22-MariaDB
-- PHP Version: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `smartjoa_demo`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `bill_id` int(11) NOT NULL,
  `bill_date` datetime DEFAULT current_timestamp(),
  `pt_id` int(11) DEFAULT NULL,
  `refer_type` enum('Hospital','Doctor','Self') NOT NULL,
  `refer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL,
  `status` enum('Paid','Cancelled') DEFAULT 'Paid'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `billing`
--

INSERT INTO `billing` (`bill_id`, `bill_date`, `pt_id`, `refer_type`, `refer_id`, `total_amount`, `discount`, `net_amount`, `status`) VALUES
(1, '2025-11-16 05:04:39', 1, 'Self', 0, 100.00, 1.00, 99.00, 'Cancelled'),
(2, '2025-11-16 05:06:59', 2, 'Self', 0, 100.00, 1.00, 99.00, 'Paid'),
(3, '2025-11-16 05:08:59', 3, 'Self', 0, 170.00, 0.00, 170.00, 'Paid'),
(4, '2025-11-16 07:57:00', 4, 'Self', 0, 300.00, 0.00, 300.00, 'Paid'),
(5, '2025-11-16 11:06:03', 5, 'Self', 0, 100.00, 75.00, 25.00, 'Paid'),
(6, '2025-11-16 11:21:34', 6, 'Self', 0, 470.00, 0.00, 470.00, 'Paid'),
(7, '2025-11-17 03:23:01', 7, 'Self', 0, 370.00, 0.00, 370.00, 'Paid'),
(8, '2025-11-17 03:25:02', 8, 'Self', 0, 100.00, 0.00, 100.00, 'Cancelled'),
(9, '2025-11-17 03:26:53', 9, 'Self', 0, 100.00, 0.00, 100.00, 'Paid'),
(10, '2025-11-17 03:29:19', 10, 'Self', 0, 100.00, 0.00, 100.00, 'Paid'),
(11, '2025-11-17 03:29:28', 11, 'Self', 0, 100.00, 0.00, 100.00, 'Paid'),
(12, '2025-11-17 03:31:13', 12, 'Self', 0, 100.00, 0.00, 100.00, 'Paid'),
(13, '2025-11-17 03:31:36', 13, 'Self', 0, 100.00, 0.00, 100.00, 'Paid');

-- --------------------------------------------------------

--
-- Table structure for table `bill_tests`
--

CREATE TABLE `bill_tests` (
  `bt_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `test_id` int(11) DEFAULT NULL,
  `test_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `bill_tests`
--

INSERT INTO `bill_tests` (`bt_id`, `bill_id`, `test_id`, `test_price`) VALUES
(1, 1, 1, 100.00),
(2, 2, 1, 100.00),
(3, 3, 1, 100.00),
(4, 3, 2, 70.00),
(5, 4, 3, 300.00),
(6, 5, 1, 100.00),
(7, 6, 2, 70.00),
(8, 6, 3, 300.00),
(9, 6, 1, 100.00),
(10, 7, 3, 300.00),
(11, 7, 2, 70.00),
(12, 8, 5, 100.00),
(13, 9, 5, 100.00),
(14, 10, 5, 100.00),
(15, 11, 5, 100.00),
(16, 12, 5, 100.00),
(17, 13, 5, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `cancelled_bills`
--

CREATE TABLE `cancelled_bills` (
  `cancel_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `cancel_date` datetime DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL,
  `cancelled_by_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `cancelled_bills`
--

INSERT INTO `cancelled_bills` (`cancel_id`, `bill_id`, `cancel_date`, `reason`, `cancelled_by_user_id`) VALUES
(1, 1, '2025-11-16 08:06:40', 'dont want', 2),
(2, 8, '2025-11-17 03:26:17', 'ssss', 1);

-- --------------------------------------------------------

--
-- Table structure for table `company`
--

CREATE TABLE `company` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone_no` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `company`
--

INSERT INTO `company` (`id`, `company_name`, `address`, `phone_no`) VALUES
(1, 'SLBS', '4-119, peddamandadi mandal, manigilla Peddamandadi Mahabubnagar Telangana India, 509103', '7092919895');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL,
  `doctor_name` varchar(100) NOT NULL,
  `degree` varchar(50) DEFAULT NULL,
  `mobile_no` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `hospital_id` int(11) NOT NULL,
  `hospital_name` varchar(150) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `mobile_no` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `licence`
--

CREATE TABLE `licence` (
  `id` int(11) NOT NULL,
  `licence_key` varchar(12) NOT NULL,
  `valid_upto` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `licence`
--

INSERT INTO `licence` (`id`, `licence_key`, `valid_upto`) VALUES
(1, 'SM017JO70929', '2026-11-15');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `pt_id` int(11) NOT NULL,
  `pt_name` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `mobile_no` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`pt_id`, `pt_name`, `age`, `sex`, `mobile_no`) VALUES
(1, 'joans', 27, 'Male', '7092919895'),
(2, 'joans', 27, 'Male', '7092919895'),
(3, 'joans', 27, 'Male', '7092919895'),
(4, 'sameem', 25, 'Male', '7092919895'),
(5, 'shirisha', 24, 'Female', '7092919895'),
(6, 'joshi', 27, 'Male', '7092919895'),
(7, 'joanson kanna', 27, 'Male', '7092919895'),
(8, 'joanson ', 27, 'Male', '7092919895'),
(9, 'joanson ', 27, 'Male', '7092919895'),
(10, 'joanson ', 27, 'Male', '7092919895'),
(11, 'joanson ', 27, 'Male', '7092919895'),
(12, 'joanson ', 27, 'Male', '7092919895'),
(13, 'joanson ', 27, 'Male', '7092919895');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `payment_method` enum('Cash','Card','UPI') NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `bill_id`, `payment_date`, `payment_method`, `amount`) VALUES
(1, 1, '2025-11-16 05:04:39', 'Cash', 99.00),
(2, 2, '2025-11-16 05:06:59', 'Cash', 99.00),
(3, 3, '2025-11-16 05:08:59', 'Cash', 170.00),
(4, 4, '2025-11-16 07:57:00', 'Cash', 300.00),
(5, 5, '2025-11-16 11:06:03', 'UPI', 25.00),
(6, 6, '2025-11-16 11:21:34', 'Card', 470.00),
(7, 7, '2025-11-17 03:23:01', 'UPI', 370.00),
(8, 8, '2025-11-17 03:25:02', 'UPI', 100.00),
(9, 9, '2025-11-17 03:26:53', 'UPI', 100.00),
(10, 10, '2025-11-17 03:29:19', 'Cash', 50.00),
(11, 10, '2025-11-17 03:29:19', 'Card', 50.00),
(12, 11, '2025-11-17 03:29:28', 'Cash', 50.00),
(13, 11, '2025-11-17 03:29:28', 'Card', 50.00),
(14, 12, '2025-11-17 03:31:13', 'Cash', 50.00),
(15, 12, '2025-11-17 03:31:13', 'Card', 50.00),
(16, 13, '2025-11-17 03:31:36', 'Cash', 50.00),
(17, 13, '2025-11-17 03:31:36', 'Card', 50.00);

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL,
  `permission_slug` varchar(100) NOT NULL,
  `permission_desc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_slug`, `permission_desc`) VALUES
(1, 'access_billing', 'Can access the Billing page'),
(2, 'access_reports', 'Can enter/view test reports'),
(3, 'access_accounts', 'Can view income and accounts data'),
(4, 'manage_users', 'Can create/edit users and roles'),
(5, 'manage_licence', 'Can access the license page'),
(6, 'manage_tests', 'Can manage Test Definitions, Sub-tests, and Ranges'),
(7, 'access_licence', 'Can access the Licence management page (Superadmin only)');

-- --------------------------------------------------------

--
-- Table structure for table `ref_ranges`
--

CREATE TABLE `ref_ranges` (
  `range_id` int(11) NOT NULL,
  `test_id` int(11) DEFAULT NULL,
  `sub_test_id` int(11) DEFAULT NULL,
  `min_age` int(11) DEFAULT 0,
  `max_age` int(11) DEFAULT 150,
  `sex` enum('Male','Female','Any') DEFAULT 'Any',
  `normal_value` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `ref_ranges`
--

INSERT INTO `ref_ranges` (`range_id`, `test_id`, `sub_test_id`, `min_age`, `max_age`, `sex`, `normal_value`) VALUES
(19, 1, NULL, 10, 27, 'Any', '1.2-1.5'),
(21, 2, NULL, 0, 10, 'Any', '70-105'),
(25, 5, NULL, 1, 60, 'Any', '150000'),
(30, 4, NULL, 0, 15, 'Any', '15000'),
(40, 3, 196, 0, 150, 'Any', 'Children (1-6 years): \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\(9.5-14\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\) g/dL       Children (6-12 years): \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\(11.5-15.5\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\) g/dL     Adolescents (12-18 years):Females: \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\(12-16\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\) g/dL    Males: \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\'),
(41, 3, 196, 0, 150, 'Any', 'Children (1-6 years): \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\(9.5-14\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\) g/dL       Children (6-12 years): \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\(11.5-15.5\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\) g/dL     Adolescents (12-18 years):Females: \\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\(12-16\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\) g/dL   '),
(42, 3, 197, 0, 150, 'Any', 'check');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `bill_id` int(11) DEFAULT NULL,
  `test_id` int(11) DEFAULT NULL,
  `sub_test_id` int(11) DEFAULT NULL,
  `result_value` varchar(255) DEFAULT NULL,
  `range_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`report_id`, `bill_id`, `test_id`, `sub_test_id`, `result_value`, `range_id`) VALUES
(10, 1, 1, NULL, '0', NULL),
(22, 2, 1, NULL, '0.96', NULL),
(30, 3, 1, NULL, '1.2', NULL),
(31, 3, 2, NULL, '110.00', NULL),
(32, 5, 1, NULL, '0.70', 19),
(33, 4, 3, NULL, '111', NULL),
(34, 4, 3, NULL, '15', NULL),
(35, 4, 3, NULL, '12', NULL),
(37, 13, 5, NULL, '160', 25),
(40, 6, 3, 196, '111.00', 40),
(41, 6, 3, 197, '11.00', 42);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(3, 'accounts'),
(2, 'admin'),
(5, 'lab'),
(4, 'reception'),
(1, 'superadmin');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 3),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 6),
(3, 1),
(3, 3),
(4, 1),
(4, 3),
(5, 2);

-- --------------------------------------------------------

--
-- Table structure for table `sub_tests`
--

CREATE TABLE `sub_tests` (
  `sub_test_id` int(11) NOT NULL,
  `test_id` int(11) DEFAULT NULL,
  `sub_test_name` varchar(150) NOT NULL,
  `specimen` varchar(50) DEFAULT NULL,
  `container` varchar(50) DEFAULT NULL,
  `tat_time` varchar(50) DEFAULT NULL,
  `report_type` enum('text','numeric') DEFAULT 'text',
  `decimal_places` tinyint(4) DEFAULT 0,
  `unit` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `sub_tests`
--

INSERT INTO `sub_tests` (`sub_test_id`, `test_id`, `sub_test_name`, `specimen`, `container`, `tat_time`, `report_type`, `decimal_places`, `unit`) VALUES
(52, 4, 'TOTAL WBC COUNT', 'BLOOD', 'EDTA', '3', 'numeric', 0, 'Count'),
(53, 4, 'NEUTROPHILS', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(54, 4, 'LYMPHOCYTES', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(55, 4, 'MONOCYTES', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(56, 4, 'EOSINOPHILS', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(57, 4, 'BASOPHILS', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(196, 3, 'HAEMOGLOBIN', 'BLOOD', 'EDTA', '3', 'numeric', 2, 'gms%'),
(197, 3, 'RBC COUNT', 'Blood', 'EDTA', '3', 'numeric', 2, 'Million/cmm'),
(198, 3, 'PCV', 'Blood', 'EDTA', '3', 'numeric', 2, '%'),
(199, 3, 'MCV', 'BLOOD', 'EDTA', '3', 'numeric', 1, 'fL'),
(200, 3, 'MCH', 'BLOOD', 'EDTA', '3', 'numeric', 1, 'pg'),
(201, 3, 'MCHC', 'BLOOD', 'EDTA', '3', 'numeric', 1, 'gm/dL'),
(202, 3, 'RED CELL DISTRIBUTION WIDTH (RDW)', '', 'EDTA', '3', 'numeric', 1, '%'),
(203, 3, 'RDW-SD', 'BLOOD', 'EDTA', '3', 'numeric', 1, '*'),
(204, 3, 'MPV', 'BLOOD', 'EDTA', '3', 'numeric', 1, 'fL'),
(205, 3, 'TOTAL WBC COUNT', 'BLOOD', 'EDTA', '3', 'numeric', 0, 'Count'),
(206, 3, 'NEUTROPHILS', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(207, 3, 'LYMPHOCYTES', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(208, 3, 'MONOCYTES', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(209, 3, 'EOSINOPHILS', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%'),
(210, 3, 'BASOPHILS', 'BLOOD', 'EDTA', '3', 'numeric', 0, '%');

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `test_id` int(11) NOT NULL,
  `test_name` varchar(150) NOT NULL,
  `test_department` varchar(100) DEFAULT NULL,
  `specimen` varchar(50) DEFAULT NULL,
  `container` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `tat_time` varchar(50) DEFAULT NULL,
  `report_type` enum('text','numeric') DEFAULT 'text',
  `decimal_places` tinyint(4) DEFAULT 0,
  `unit` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `tests`
--

INSERT INTO `tests` (`test_id`, `test_name`, `test_department`, `specimen`, `container`, `price`, `tat_time`, `report_type`, `decimal_places`, `unit`) VALUES
(1, 'Creatinine', 'Bio', 'Blood', '0', 100.00, '24', 'text', 0, 'mg/dL'),
(2, 'FBS', 'Bio', 'Blood', '0', 70.00, '24', 'numeric', 2, 'mg/dL'),
(3, 'CBP (HAEMOGRAM)', 'Heamotology', 'Blood', 'EDTA', 300.00, '3', 'text', 0, ''),
(4, 'DIFFERENTIAL COUNT', 'Heamotology', 'Blood', 'EDTA', 300.00, '3', 'text', 0, 'mg/dL'),
(5, 'RBC', 'Heamotology', 'Blood', 'EDTA', 100.00, '3', 'text', 0, 'mg/dL');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password_hash`, `role_id`, `is_active`) VALUES
(1, 'superadmin', '$2y$12$XRC9.A7bnyw8s.5sgtfGGORHobtK6L4qae6nc.Wy7TNhbmYbtzlk.', 1, 1),
(2, 'admin', '$2y$10$OUNnAld2fqcxYSmV9SUmbuL19sGcOFDWsF4fkKokGVAYJfry0RdBu', 2, 1),
(3, 'recp', '$2y$10$OA3/CezumzVIa0gliLYcu.SOUn8Iun6AuX8GvkxGbCDBtb2o2Xms2', 4, 1),
(4, 'lab', '$2y$10$Qx7I3cPPCjfZgpFRShuWZud8SsXG4F3K/uc0a.ZL5YvU22StXtr9C', 5, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`bill_id`),
  ADD KEY `pt_id` (`pt_id`);

--
-- Indexes for table `bill_tests`
--
ALTER TABLE `bill_tests`
  ADD PRIMARY KEY (`bt_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `cancelled_bills`
--
ALTER TABLE `cancelled_bills`
  ADD PRIMARY KEY (`cancel_id`),
  ADD UNIQUE KEY `bill_id` (`bill_id`);

--
-- Indexes for table `company`
--
ALTER TABLE `company`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`hospital_id`);

--
-- Indexes for table `licence`
--
ALTER TABLE `licence`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `licence_key` (`licence_key`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`pt_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `bill_id` (`bill_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_slug` (`permission_slug`);

--
-- Indexes for table `ref_ranges`
--
ALTER TABLE `ref_ranges`
  ADD PRIMARY KEY (`range_id`),
  ADD KEY `ref_ranges_ibfk_1` (`test_id`),
  ADD KEY `ref_ranges_ibfk_2` (`sub_test_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `bill_id` (`bill_id`),
  ADD KEY `test_id` (`test_id`),
  ADD KEY `reports_ibfk_4` (`range_id`),
  ADD KEY `reports_ibfk_3` (`sub_test_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `sub_tests`
--
ALTER TABLE `sub_tests`
  ADD PRIMARY KEY (`sub_test_id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`test_id`),
  ADD UNIQUE KEY `test_name` (`test_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `role_id` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `bill_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `bill_tests`
--
ALTER TABLE `bill_tests`
  MODIFY `bt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `cancelled_bills`
--
ALTER TABLE `cancelled_bills`
  MODIFY `cancel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `company`
--
ALTER TABLE `company`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `hospital_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `licence`
--
ALTER TABLE `licence`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `pt_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `ref_ranges`
--
ALTER TABLE `ref_ranges`
  MODIFY `range_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sub_tests`
--
ALTER TABLE `sub_tests`
  MODIFY `sub_test_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=211;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `test_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `billing_ibfk_1` FOREIGN KEY (`pt_id`) REFERENCES `patients` (`pt_id`);

--
-- Constraints for table `bill_tests`
--
ALTER TABLE `bill_tests`
  ADD CONSTRAINT `bill_tests_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`bill_id`),
  ADD CONSTRAINT `bill_tests_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`);

--
-- Constraints for table `cancelled_bills`
--
ALTER TABLE `cancelled_bills`
  ADD CONSTRAINT `cancelled_bills_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`bill_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`bill_id`);

--
-- Constraints for table `ref_ranges`
--
ALTER TABLE `ref_ranges`
  ADD CONSTRAINT `ref_ranges_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ref_ranges_ibfk_2` FOREIGN KEY (`sub_test_id`) REFERENCES `sub_tests` (`sub_test_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `billing` (`bill_id`),
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`),
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`sub_test_id`) REFERENCES `sub_tests` (`sub_test_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_4` FOREIGN KEY (`range_id`) REFERENCES `ref_ranges` (`range_id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`permission_id`);

--
-- Constraints for table `sub_tests`
--
ALTER TABLE `sub_tests`
  ADD CONSTRAINT `sub_tests_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
