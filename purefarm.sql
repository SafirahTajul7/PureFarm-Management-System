-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 23, 2025 at 04:36 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `purefarm`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `created_at`) VALUES
(1, 1, 'update', 'staff', 9, '{\"staff_id\":\" FM-MY001\",\"name\":\"Khairul Anuar\",\"role_id\":\"1\"}', '2025-05-31 00:42:47');

-- --------------------------------------------------------

--
-- Table structure for table `animals`
--

CREATE TABLE `animals` (
  `id` int(11) NOT NULL,
  `animal_id` varchar(10) NOT NULL,
  `species` varchar(50) NOT NULL,
  `breed` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `health_status` varchar(50) NOT NULL,
  `vaccination_details` text DEFAULT NULL,
  `gender` varchar(10) NOT NULL,
  `source` varchar(50) NOT NULL,
  `vaccination_status` enum('vaccinated','not_vaccinated','partially_vaccinated','overdue') DEFAULT 'not_vaccinated',
  `last_vaccination_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `animals`
--

INSERT INTO `animals` (`id`, `animal_id`, `species`, `breed`, `date_of_birth`, `health_status`, `vaccination_details`, `gender`, `source`, `vaccination_status`, `last_vaccination_date`) VALUES
(10, 'A001', 'cattle', 'Jersey', '2023-07-26', 'Sick', NULL, 'female', 'Donation', 'vaccinated', '2025-01-20'),
(11, 'A002', 'goat', 'Boer Goat', '2022-03-31', 'injured', NULL, 'female', 'Birth', 'partially_vaccinated', '2025-01-05'),
(15, 'A003', 'Cattle', 'Holstein', '2023-02-15', 'healthy', NULL, 'female', 'Purchase', 'overdue', '2023-02-20'),
(16, 'A004', 'Cattle', 'Angus', '2022-11-20', 'healthy', NULL, 'male', 'Birth', 'overdue', '2023-01-15'),
(17, 'A005', 'Goat', 'Alpine', '2023-05-10', 'healthy', NULL, 'female', 'Purchase', 'overdue', '2023-05-15'),
(18, 'A006', 'Chicken', 'Leghorn', '2023-08-01', 'healthy', NULL, 'female', 'Purchase', 'vaccinated', '2023-08-05'),
(19, 'A007', 'Buffalo', 'Water Buffalo', '2022-06-15', 'injured', NULL, 'male', 'Donation', 'not_vaccinated', NULL),
(20, 'A008', 'Rabbit', 'New Zealand White', '2023-09-10', 'healthy', NULL, 'male', 'Purchase', 'overdue', '2023-09-15'),
(21, 'A009', 'Duck', 'Pekin', '2023-07-22', 'sick', NULL, 'female', 'Birth', 'not_vaccinated', NULL),
(22, 'A010', 'Goat', 'Saanen', '2023-02-18', 'Quarantine', NULL, 'female', 'Donation', 'vaccinated', NULL),
(23, 'C001', 'cattle', 'Holstein', '2023-06-13', 'healthy', NULL, 'female', 'Purchase', 'vaccinated', '2025-05-30'),
(24, 'A011', 'goat', 'GOAT', '2025-01-16', 'Healthy', NULL, 'male', 'Purchase', 'vaccinated', '2025-06-03');

-- --------------------------------------------------------

--
-- Table structure for table `batch_quality_checks`
--

CREATE TABLE `batch_quality_checks` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `check_date` datetime NOT NULL,
  `performed_by` int(11) NOT NULL,
  `quality_grade` varchar(10) DEFAULT NULL,
  `passed` tinyint(1) NOT NULL DEFAULT 1,
  `temperature` decimal(5,2) DEFAULT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `appearance` varchar(255) DEFAULT NULL,
  `smell` varchar(255) DEFAULT NULL,
  `texture` varchar(255) DEFAULT NULL,
  `contamination` varchar(255) DEFAULT NULL,
  `test_results` text DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `moisture_level` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_quality_checks`
--

INSERT INTO `batch_quality_checks` (`id`, `batch_id`, `check_date`, `performed_by`, `quality_grade`, `passed`, `temperature`, `humidity`, `appearance`, `smell`, `texture`, `contamination`, `test_results`, `additional_notes`, `created_at`, `updated_at`, `moisture_level`) VALUES
(1, 27, '2025-05-05 15:11:00', 1, NULL, 1, 22.50, 45.00, 'Uniform golden-brown pellets with consistent size and shape. No visible mold or foreign materials present.', 'Clean, slightly sweet aroma typical of fresh grain feed. No musty or rancid odors detected.', 'Firm, dry pellets that break cleanly when pressed. Not dusty or crumbly. Appropriate moisture content.', 'No visible signs of contamination. No insect activity, rodent evidence, or foreign objects detected in random sampling.', 'Protein content: 18.2% (within specification of 18-20%)\r\nMoisture content: 11.8% (within acceptable range of 10-12%)\r\nAsh content: 7.5% (acceptable)\r\nAflatoxin test: Negative (<5 ppb)\r\nMicrobial analysis: Total bacterial count below threshold (2.1 × 10^4 CFU/g)', 'Batch appears to meet all quality standards upon visual inspection and preliminary testing. Sample sent to laboratory for complete nutritional analysis. Storage conditions in receiving area appropriate with proper temperature and humidity control. Recommend releasing from quarantine pending final lab results.', '2025-05-05 13:13:02', '2025-05-05 13:13:02', NULL),
(6, 27, '2025-06-01 22:03:35', 1, 'FAIL', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Automated quality check performed at 2025-06-01 16:03:33. Moisture sensor reading captured.', '2025-06-01 14:03:35', '2025-06-01 14:03:35', 21.20),
(7, 27, '2025-06-01 22:13:00', 1, 'C', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Automated quality check performed at 2025-06-01 16:12:58. Moisture sensor reading captured.', '2025-06-01 14:13:00', '2025-06-01 14:13:00', 17.90),
(8, 27, '2025-06-03 11:12:09', 1, 'A', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Automated quality check performed at 2025-06-03 05:12:04. Moisture sensor reading captured.', '2025-06-03 03:12:09', '2025-06-03 03:12:09', 11.60),
(9, 27, '2025-06-03 13:13:16', 1, 'B', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Automated quality check performed at 2025-06-03 07:13:14. Moisture sensor reading captured.', '2025-06-03 05:13:16', '2025-06-03 05:13:16', 14.60),
(10, 27, '2025-06-03 16:01:53', 1, 'FAIL', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Automated quality check performed at 2025-06-03 09:59:15. Moisture sensor reading captured.', '2025-06-03 08:01:53', '2025-06-03 08:01:53', 20.60);

-- --------------------------------------------------------

--
-- Table structure for table `batch_usage`
--

CREATE TABLE `batch_usage` (
  `id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `usage_date` datetime NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `used_by` int(11) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `breeding_history`
--

CREATE TABLE `breeding_history` (
  `id` int(11) NOT NULL,
  `animal_id` varchar(50) NOT NULL,
  `partner_id` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `outcome` enum('pending','successful','failed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `breeding_history`
--

INSERT INTO `breeding_history` (`id`, `animal_id`, `partner_id`, `date`, `outcome`, `notes`, `created_at`) VALUES
(1, 'A001', 'A004', '2025-05-02', 'successful', 'Breeding Holstein female with Angus male for crossbreeding program. Expecting hybrid vigor in offspring with improved meat quality from Angus genetics and potentially better milk production from Holstein line. First-time breeding for A003. Will monitor closely for successful conception in the next 3 weeks. Plan to confirm pregnancy via veterinary check 45 days after breeding.', '2025-05-08 10:31:54'),
(3, 'A003', 'A004', '2025-05-21', 'pending', 'cvz', '2025-05-10 15:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `breeding_history_backup`
--

CREATE TABLE `breeding_history_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `animal_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `partner_id` int(11) NOT NULL,
  `outcome` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crops`
--

CREATE TABLE `crops` (
  `id` int(11) NOT NULL,
  `crop_name` varchar(100) NOT NULL,
  `variety` varchar(100) DEFAULT NULL,
  `field_id` int(11) DEFAULT NULL,
  `planting_date` date NOT NULL,
  `expected_harvest_date` date DEFAULT NULL,
  `actual_harvest_date` date DEFAULT NULL,
  `growth_stage` varchar(50) DEFAULT 'seedling',
  `status` varchar(50) DEFAULT 'active' COMMENT 'active, harvested, failed',
  `next_action` varchar(100) DEFAULT NULL,
  `next_action_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crops`
--

INSERT INTO `crops` (`id`, `crop_name`, `variety`, `field_id`, `planting_date`, `expected_harvest_date`, `actual_harvest_date`, `growth_stage`, `status`, `next_action`, `next_action_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Cili', 'Cili Merah Kulai', 3, '2025-04-11', '2025-07-11', NULL, 'flowering', 'harvested', 'Fertilize, Irrigate', '2025-04-18', 'Monitor for pests (especially aphids & thrips), use organic fertilizer every 2 weeks.', '2025-04-11 15:46:35', '2025-04-24 17:37:37'),
(2, 'Corn', 'Sweet Corn', 1, '2025-03-29', '2025-05-28', NULL, 'flowering', 'harvested', 'Harvested', '2025-06-02', 'Monitor for pests', '2025-04-28 16:17:18', '2025-06-01 19:51:54'),
(3, 'Wheat', 'Winter Wheat', 2, '2025-02-27', '2025-06-12', NULL, 'growing', 'active', 'Irrigate', '2025-05-05', 'Check for disease', '2025-04-28 16:17:18', '2025-04-28 16:17:18'),
(4, 'Soybeans', 'Round-Up Ready', 3, '2025-03-14', '2025-06-27', NULL, 'seedling', 'harvested', 'Harvested', '2025-06-03', 'Monitor growth', '2025-04-28 16:17:18', '2025-06-03 07:54:38'),
(5, 'Cili Merah Kulai', 'Spicy', NULL, '2025-04-13', '2025-07-12', '2025-05-30', 'seedling', 'harvested', 'Growth stage updated to seedling', '2025-06-02', 'Monitor for pests (especially aphids & thrips)', '2025-04-28 16:17:18', '2025-06-01 19:46:02');

-- --------------------------------------------------------

--
-- Table structure for table `crop_activities`
--

CREATE TABLE `crop_activities` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `activity_type` enum('planting','irrigation','fertilization','pesticide','weeding','harvest','other') NOT NULL,
  `activity_date` date NOT NULL,
  `description` text NOT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL COMMENT 'staff ID',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crop_activities`
--

INSERT INTO `crop_activities` (`id`, `crop_id`, `activity_type`, `activity_date`, `description`, `quantity`, `unit`, `performed_by`, `notes`, `created_at`) VALUES
(1, 1, 'planting', '2025-04-11', 'Initial planting of Cili (Cili Merah Kulai)', NULL, NULL, 1, NULL, '2025-04-11 15:46:35'),
(2, 5, 'harvest', '2025-05-30', 'Harvested Cili Merah Kulai (Spicy)', 400.00, 'kg', 1, 'quality is 90% okay', '2025-05-30 16:38:36'),
(3, 5, '', '2025-06-02', 'Reported pest issue: dcd', NULL, NULL, 2, NULL, '2025-06-01 17:28:11'),
(4, 5, '', '2025-06-02', 'Reported pest issue: d', NULL, NULL, 2, NULL, '2025-06-01 17:29:31'),
(5, 5, '', '2025-06-02', 'Reported water issue: fv', NULL, NULL, 2, NULL, '2025-06-01 19:46:13');

-- --------------------------------------------------------

--
-- Table structure for table `crop_expenses`
--

CREATE TABLE `crop_expenses` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crop_expenses`
--

INSERT INTO `crop_expenses` (`id`, `crop_id`, `date`, `amount`, `category`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-03-15', 450.00, 'Seeds', 'Initial seed purchase for Cili Merah Kulai', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(2, 1, '2025-03-25', 320.50, 'Fertilizer', 'Organic fertilizer for initial planting', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(3, 1, '2025-04-08', 275.25, 'Fertilizer', 'NPK fertilizer application', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(4, 1, '2025-04-02', 180.75, 'Pesticides', 'Preventive pest control treatment', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(5, 1, '2025-04-12', 215.00, 'Pesticides', 'Treatment for aphids as noted in crop monitoring', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(6, 1, '2025-03-20', 550.00, 'Labor', 'Land preparation and initial planting labor', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(7, 1, '2025-04-05', 325.00, 'Labor', 'Weeding and maintenance work', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(8, 1, '2025-04-15', 275.00, 'Labor', 'Fertilizer and pesticide application labor . nn', '2025-04-24 18:04:47', '2025-04-29 11:31:59'),
(9, 1, '2025-03-18', 420.00, 'Irrigation', 'Installation of drip irrigation system', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(10, 1, '2025-04-10', 150.00, 'Irrigation', 'Water usage charges', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(11, 1, '2025-03-22', 350.00, 'Equipment', 'Rental of tractor for land preparation', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(12, 1, '2025-04-01', 125.50, 'Other', 'Miscellaneous supplies and tools', '2025-04-24 18:04:47', '2025-04-24 18:04:47'),
(14, 1, '2025-03-15', 450.00, 'Seeds', 'Initial seed purchase for Cili Merah Kulai', '2025-04-24 18:05:18', '2025-04-24 18:05:18'),
(15, 1, '2025-03-25', 320.50, 'Fertilizer', 'Organic fertilizer for initial planting', '2025-04-24 18:05:18', '2025-04-24 18:05:18'),
(16, 1, '2025-04-08', 275.25, 'Fertilizer', 'NPK fertilizer application', '2025-04-24 18:05:18', '2025-04-24 18:05:18'),
(17, 1, '2025-04-02', 180.75, 'Pesticides', 'Preventive pest control treatment', '2025-04-24 18:05:18', '2025-04-24 18:05:18'),
(18, 1, '2025-04-12', 215.00, 'Pesticides', 'Treatment for aphids as noted in crop monitoring', '2025-04-24 18:05:18', '2025-04-24 18:05:18'),
(19, 1, '2025-03-20', 550.00, 'Labor', 'Land preparation and initial planting labor', '2025-04-24 18:05:18', '2025-04-24 18:05:18'),
(20, 1, '2025-04-05', 325.00, 'Labor', 'Weeding and maintenance work', '2025-04-24 18:05:18', '2025-04-24 18:05:18'),
(21, 1, '2025-04-15', 275.00, 'Labor', 'Fertilizer and pesticide application labor', '2025-04-24 18:05:18', '2025-04-24 18:05:18'),
(22, 1, '2025-03-18', 420.00, 'Irrigation', 'Installation of drip irrigation system', '2025-04-24 18:05:19', '2025-04-24 18:05:19'),
(23, 1, '2025-04-10', 150.00, 'Irrigation', 'Water usage charges', '2025-04-24 18:05:19', '2025-04-24 18:05:19'),
(24, 1, '2025-03-22', 350.00, 'Equipment', 'Rental of tractor for land preparation', '2025-04-24 18:05:19', '2025-04-24 18:05:19'),
(25, 1, '2025-04-01', 125.50, 'Other', 'Miscellaneous supplies and tools', '2025-04-24 18:05:19', '2025-04-24 18:05:19');

-- --------------------------------------------------------

--
-- Table structure for table `crop_issues`
--

CREATE TABLE `crop_issues` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `issue_type` enum('pest','disease','nutrient','other') NOT NULL,
  `description` text NOT NULL,
  `date_identified` date NOT NULL,
  `severity` enum('low','medium','high') NOT NULL,
  `affected_area` varchar(100) DEFAULT NULL,
  `treatment_applied` text DEFAULT NULL,
  `resolved` tinyint(1) DEFAULT 0,
  `resolution_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `treatment_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crop_issues`
--

INSERT INTO `crop_issues` (`id`, `crop_id`, `issue_type`, `description`, `date_identified`, `severity`, `affected_area`, `treatment_applied`, `resolved`, `resolution_date`, `notes`, `created_at`, `updated_at`, `treatment_date`) VALUES
(1, 1, 'pest', 'Aphids', '2025-04-16', 'medium', '30% of field', '1', 1, '2025-04-16', 'Pest presence noted during morning inspection. No pesticide applied yet. Weather has been humid, which may have contributed to infestation.\r\n\r\n\nApplied 2 L/acre of pesticide using Spraying. Apply evenly on affected areas.', '2025-04-16 16:26:53', '2025-04-16 16:33:45', NULL),
(2, 1, 'disease', 'Aphids', '2025-04-16', 'low', '30% of field', '1', 0, NULL, 'dedd\nTreatment Applied: Fungicide on 2025-06-03. Quantity: 50. Method: spray. Notes: donr', '2025-04-16 16:34:33', '2025-06-03 07:50:38', '2025-06-03'),
(3, 5, 'pest', 'dcd', '2025-06-02', 'medium', 'sds', '1', 0, NULL, 'dcd\nTreatment Applied: Insecticide (2025-06-01) on 2025-06-01. Quantity: 4. Method: spray. Notes: d', '2025-06-01 17:28:11', '2025-06-01 18:34:38', '2025-06-01'),
(4, 5, 'pest', 'd', '2025-06-02', 'medium', '5', NULL, 0, NULL, 'd', '2025-06-01 17:29:31', '2025-06-01 17:29:31', NULL),
(5, 5, '', 'fv', '2025-06-02', 'medium', '5', '0', 0, NULL, 'vf', '2025-06-01 19:46:13', '2025-06-01 19:46:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `crop_revenue`
--

CREATE TABLE `crop_revenue` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `crop_revenue`
--

INSERT INTO `crop_revenue` (`id`, `crop_id`, `date`, `amount`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-04-05', 850.00, 'Advance purchase contract for first harvest batch.kk', '2025-04-24 18:05:39', '2025-04-29 11:32:24'),
(2, 1, '2025-05-15', 2250.00, 'First partial harvest - 150kg @ $15/kg', '2025-04-24 18:05:39', '2025-04-24 18:05:39'),
(3, 1, '2025-06-01', 3750.00, 'Main harvest - 250kg @ $15/kg', '2025-04-24 18:05:39', '2025-04-24 18:05:39'),
(4, 1, '2025-06-18', 1500.00, 'Final harvest - 100kg @ $15/kg', '2025-04-24 18:05:39', '2025-04-24 18:05:39');

-- --------------------------------------------------------

--
-- Table structure for table `deceased_animals`
--

CREATE TABLE `deceased_animals` (
  `id` int(11) NOT NULL,
  `animal_id` varchar(10) NOT NULL,
  `date_of_death` date NOT NULL,
  `cause` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deceased_animals`
--

INSERT INTO `deceased_animals` (`id`, `animal_id`, `date_of_death`, `cause`, `notes`, `created_at`) VALUES
(1, 'A001', '2025-01-03', 'Age-related natural causes', 'One of our oldest breeding goats, passed away peacefully', '2025-01-05 15:44:25');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_issues`
--

CREATE TABLE `delivery_issues` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `issue_type` enum('damaged','incorrect_item','quantity_mismatch','quality_issue','late_delivery','other') NOT NULL,
  `description` text NOT NULL,
  `reported_by` int(11) NOT NULL,
  `reported_date` datetime NOT NULL DEFAULT current_timestamp(),
  `resolution` text DEFAULT NULL,
  `resolved_date` datetime DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_tracking`
--

CREATE TABLE `delivery_tracking` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `carrier` varchar(100) DEFAULT NULL,
  `status_update` enum('processing','shipped','in_transit','delivered','delayed','cancelled') NOT NULL,
  `status_date` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_tracking`
--

INSERT INTO `delivery_tracking` (`id`, `purchase_id`, `tracking_number`, `carrier`, `status_update`, `status_date`, `location`, `notes`, `created_at`) VALUES
(1, 1, 'TRK123456', 'FastDelivery', 'processing', '2025-01-01 14:30:00', NULL, 'Order processed and ready for shipping', '2025-05-02 00:44:25'),
(2, 2, 'TRK234567', 'Express Logistics', 'shipped', '2025-01-03 09:15:00', NULL, 'Package picked up by carrier', '2025-05-02 00:44:25'),
(3, 2, 'TRK234567', 'Express Logistics', 'in_transit', '2025-01-04 11:30:00', NULL, 'Package in transit to destination', '2025-05-02 00:44:25'),
(4, 3, 'TRK345678', 'Farm Delivery Co.', 'processing', '2025-01-03 10:00:00', NULL, 'Order processed', '2025-05-02 00:44:25'),
(5, 3, 'TRK345678', 'Farm Delivery Co.', 'shipped', '2025-01-05 08:45:00', NULL, 'Package shipped', '2025-05-02 00:44:25'),
(6, 3, 'TRK345678', 'Farm Delivery Co.', 'in_transit', '2025-01-06 16:20:00', NULL, 'Package in transit', '2025-05-02 00:44:25'),
(7, 3, 'TRK345678', 'Farm Delivery Co.', 'delivered', '2025-01-08 13:10:00', NULL, 'Package delivered successfully', '2025-05-02 00:44:25'),
(8, 4, 'TRK456789', 'Agri Logistics', 'processing', '2025-01-04 15:30:00', NULL, 'Order being prepared', '2025-05-02 00:44:25'),
(9, 5, 'TRK567890', 'Rural Express', 'processing', '2025-01-05 09:45:00', NULL, 'Order processed', '2025-05-02 00:44:25'),
(10, 5, 'TRK567890', 'Rural Express', 'delayed', '2025-01-07 14:20:00', NULL, 'Delivery delayed due to weather conditions', '2025-05-02 00:44:25'),
(11, 5, '', '', 'processing', '2025-05-05 18:52:41', NULL, '', '2025-05-05 18:52:41'),
(12, 5, 'TRK567890', 'Rural Express', 'in_transit', '2025-05-05 18:53:20', NULL, 'on the way', '2025-05-05 18:53:20'),
(13, 6, NULL, NULL, 'processing', '2025-05-05 19:09:12', NULL, 'Purchase order created', '2025-05-05 19:09:12'),
(14, 6, NULL, NULL, '', '2025-05-05 19:13:00', NULL, 'Order has been processed and will be shipped soon.', '2025-05-05 19:13:00'),
(15, 6, NULL, NULL, 'processing', '2025-05-04 19:13:01', NULL, 'Order received and is being processed.', '2025-05-05 19:13:01'),
(16, 7, NULL, NULL, 'processing', '2025-05-05 20:11:43', NULL, 'Purchase order created', '2025-05-05 20:11:43'),
(17, 8, NULL, NULL, 'processing', '2025-05-09 02:57:09', NULL, 'Purchase order created', '2025-05-09 02:57:09');

-- --------------------------------------------------------

--
-- Table structure for table `environmental_issues`
--

CREATE TABLE `environmental_issues` (
  `id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `issue_type` varchar(100) NOT NULL,
  `severity` enum('Low','Medium','High','Critical') NOT NULL,
  `issue_date` date NOT NULL,
  `issue_time` time NOT NULL,
  `affected_area` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `immediate_action` text DEFAULT NULL,
  `photos` text DEFAULT NULL,
  `weather_conditions` varchar(255) DEFAULT NULL,
  `estimated_impact` text DEFAULT NULL,
  `admin_notification` tinyint(1) DEFAULT 0,
  `status` enum('Open','In Progress','Resolved','Closed') DEFAULT 'Open',
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `environmental_issues`
--

INSERT INTO `environmental_issues` (`id`, `supervisor_id`, `field_id`, `issue_type`, `severity`, `issue_date`, `issue_time`, `affected_area`, `description`, `immediate_action`, `photos`, `weather_conditions`, `estimated_impact`, `admin_notification`, `status`, `resolution_notes`, `created_at`, `updated_at`, `resolved_at`) VALUES
(1, 2, 0, '', '', '2025-06-02', '00:00:00', '', '', '', '', '', '', 0, 'Open', NULL, '2025-06-01 22:01:14', '2025-06-01 22:01:14', NULL),
(2, 2, 0, '', '', '2025-06-02', '00:00:00', '', '', '', '', '', '', 0, 'Open', NULL, '2025-06-01 22:01:23', '2025-06-01 22:01:23', NULL),
(3, 2, 3, 'Water Contamination', 'Critical', '2025-06-03', '10:16:00', '', 'water continamtion', '', '', '', '', 0, 'Open', NULL, '2025-06-03 08:17:15', '2025-06-03 08:17:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `environmental_readings`
--

CREATE TABLE `environmental_readings` (
  `id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `reading_date` date NOT NULL,
  `reading_time` time NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `wind_speed` decimal(5,2) DEFAULT NULL,
  `wind_direction` varchar(20) DEFAULT NULL,
  `barometric_pressure` decimal(8,2) DEFAULT NULL,
  `rainfall` decimal(6,2) DEFAULT NULL,
  `uv_index` decimal(3,1) DEFAULT NULL,
  `visibility` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `environmental_readings`
--

INSERT INTO `environmental_readings` (`id`, `supervisor_id`, `field_id`, `reading_date`, `reading_time`, `temperature`, `humidity`, `wind_speed`, `wind_direction`, `barometric_pressure`, `rainfall`, `uv_index`, `visibility`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 3, '2025-06-01', '23:37:00', 24.00, 70.00, 6.00, 'W', 1014.00, 3.00, 1.0, 12.00, '222', '2025-06-01 21:38:43', '2025-06-01 21:38:43');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `status` enum('available','in-use','maintenance') NOT NULL DEFAULT 'available',
  `next_available_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_checkup` date DEFAULT NULL,
  `next_checkup` date DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `type`, `status`, `next_available_date`, `notes`, `last_checkup`, `next_checkup`, `updated_at`) VALUES
(1, 'Harvester A', 'harvester', '', NULL, NULL, '2025-05-06', '2025-11-02', '2025-05-06 21:27:44'),
(2, 'Harvester B', 'harvester', 'maintenance', '2025-04-30', NULL, NULL, NULL, '2025-05-06 21:26:51'),
(3, 'Tractor 1', 'tractor', 'available', NULL, NULL, NULL, NULL, '2025-05-06 21:26:51'),
(4, 'Tractor 2', 'tractor', 'in-use', '2025-04-27', NULL, NULL, NULL, '2025-05-06 21:26:51'),
(5, 'Transport Truck', 'transport', 'available', NULL, NULL, NULL, NULL, '2025-05-06 21:26:51');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_checkups`
--

CREATE TABLE `equipment_checkups` (
  `id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `checkup_date` date NOT NULL,
  `condition_status` enum('Good','Fair','Poor','Broken') NOT NULL,
  `technician` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `repair_cost` decimal(10,2) DEFAULT NULL,
  `next_checkup_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_checkups`
--

INSERT INTO `equipment_checkups` (`id`, `equipment_id`, `checkup_date`, `condition_status`, `technician`, `notes`, `repair_cost`, `next_checkup_date`, `created_by`, `created_at`) VALUES
(1, 1, '2025-05-06', 'Good', 'Aiman', 'Regular maintenance, handle is in good condition', 0.00, '2025-11-02', 1, '2025-05-06 21:14:51');

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `description`) VALUES
(1, 'Seeds', 'Expenses related to purchasing seeds'),
(2, 'Fertilizer', 'Costs for all types of fertilizers'),
(3, 'Pesticides', 'Costs for pest control chemicals'),
(4, 'Irrigation', 'Water and irrigation system costs'),
(5, 'Labor', 'Worker wages and contractor fees'),
(6, 'Equipment', 'Costs for farm equipment rental or purchase'),
(7, 'Fuel', 'Fuel for tractors and other equipment'),
(8, 'Transportation', 'Costs for transporting crops'),
(9, 'Storage', 'Storage facility costs'),
(10, 'Other', 'Miscellaneous expenses');

-- --------------------------------------------------------

--
-- Table structure for table `feeding_schedules`
--

CREATE TABLE `feeding_schedules` (
  `id` int(11) NOT NULL,
  `animal_id` int(11) DEFAULT NULL,
  `food_type` varchar(100) DEFAULT NULL,
  `quantity` varchar(50) DEFAULT NULL,
  `frequency` varchar(100) DEFAULT NULL,
  `special_diet` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feeding_schedules`
--

INSERT INTO `feeding_schedules` (`id`, `animal_id`, `food_type`, `quantity`, `frequency`, `special_diet`, `notes`, `created_at`) VALUES
(8, 18, 'vv', '2', 'daily', 6, 'ggg', '2025-05-27 11:19:39'),
(9, 21, 'HAY', '2KG', 'daily', 4, 'FF', '2025-05-27 11:56:58'),
(10, 19, 'FG', '2', 'daily', 2, 'DDD', '2025-05-27 12:09:29'),
(13, 11, 'hay', '2', 'twice_daily', 0, 'dd', '2025-05-27 12:29:20');

-- --------------------------------------------------------

--
-- Table structure for table `fertilizer_logs`
--

CREATE TABLE `fertilizer_logs` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `application_date` date NOT NULL,
  `amount_used` varchar(100) NOT NULL,
  `application_method` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fertilizer_logs`
--

INSERT INTO `fertilizer_logs` (`id`, `schedule_id`, `application_date`, `amount_used`, `application_method`, `notes`, `created_at`) VALUES
(1, 1, '2025-04-16', '5', 'Broadcast', 'Sure thing! Let me fill out the form with an example:\r\n- Field: Green Valley Farm\r\n- Crop: Tomatoes\r\n- Fertilizer Type: Organic Compost Mix\r\n- Recommended Rate: 3 kg/acre\r\n- Application Date: 15/04/2025\r\n- Amount Used: 3.5 kg\r\n- Application Method: Broadcasting\r\n- Next Application Date: 30/04/2025\r\n- Notes: Slightly increased application to account for heavy rainfall and nutrient washout.\r\n\r\nThis is a mock example, but you can adjust it as needed to match the actual details of your case. Let me know if anything else needs tweaking!\r\n', '2025-04-16 03:58:15'),
(2, 1, '2025-05-06', '5', 'Band', '', '2025-05-06 18:46:36');

-- --------------------------------------------------------

--
-- Table structure for table `fertilizer_schedules`
--

CREATE TABLE `fertilizer_schedules` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `fertilizer_type_id` int(11) NOT NULL,
  `schedule_description` varchar(255) NOT NULL,
  `application_rate` varchar(100) NOT NULL,
  `last_application_date` date DEFAULT NULL,
  `next_application_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fertilizer_schedules`
--

INSERT INTO `fertilizer_schedules` (`id`, `crop_id`, `fertilizer_type_id`, `schedule_description`, `application_rate`, `last_application_date`, `next_application_date`, `created_at`) VALUES
(1, 1, 10, 'Every 2 weeks', '5 kg/acre', '2025-05-06', '2025-05-22', '2025-04-16 03:53:45');

-- --------------------------------------------------------

--
-- Table structure for table `fertilizer_types`
--

CREATE TABLE `fertilizer_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `nutrient_composition` varchar(255) NOT NULL,
  `recommended_crops` text NOT NULL,
  `unit_of_measure` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fertilizer_types`
--

INSERT INTO `fertilizer_types` (`id`, `name`, `nutrient_composition`, `recommended_crops`, `unit_of_measure`) VALUES
(1, 'MPOB F1', 'N: 12%, P: 12%, K: 17%, Mg: 2%', 'Oil Palm (young palms)', 'kg'),
(2, 'MPOB F2', 'N: 14%, P: 7%, K: 17%, Mg: 2.5%', 'Oil Palm (mature palms)', 'kg'),
(3, 'Nitrophoska Blue', 'N: 12%, P: 12%, K: 17%, Mg: 2%, S: 6%', 'Rice, Vegetables, Fruits', 'kg'),
(4, 'Urea (Malaysia)', 'N: 46%, P: 0%, K: 0%', 'Rice, Corn, Vegetables, Oil Palm', 'kg'),
(5, 'Malaysian Compound Fertilizer 15', 'N: 15%, P: 15%, K: 15%', 'Vegetables, Fruits, Ornamentals', 'kg'),
(6, 'MOP (Muriate of Potash)', 'K: 60%, Cl: 45%', 'Oil Palm, Rubber, Cocoa, Vegetables', 'kg'),
(7, 'TSP (Triple Super Phosphate)', 'P: 46%, Ca: 15%', 'Legumes, Root crops', 'kg'),
(8, 'Kieserite', 'Mg: 16%, S: 22%', 'Oil Palm, Rubber, Cocoa', 'kg'),
(9, 'RISDA Compound (Rubber)', 'N: 10%, P: 8%, K: 16%, Mg: 2.5%', 'Rubber', 'kg'),
(10, 'Malaysian Palm Special', 'N: 13%, P: 8%, K: 27%, Mg: 2.5%', 'Oil Palm', 'kg'),
(11, 'Malaysian Rice Fertilizer', 'N: 17%, P: 8%, K: 17%', 'Rice', 'kg'),
(12, 'Malaysian Ammonium Sulfate', 'N: 21%, S: 24%', 'Rice, Vegetables', 'kg');

-- --------------------------------------------------------

--
-- Table structure for table `fields`
--

CREATE TABLE `fields` (
  `id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `area` decimal(10,2) NOT NULL COMMENT 'in acres/hectares',
  `soil_type` varchar(100) DEFAULT NULL,
  `last_crop` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` varchar(20) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fields`
--

INSERT INTO `fields` (`id`, `field_name`, `location`, `area`, `soil_type`, `last_crop`, `notes`, `created_at`, `updated_at`, `status`) VALUES
(1, 'North Field', 'Northern Farm Area', 12.50, 'Loamy', 'Corn', 'Good drainage, sunny exposure', '2025-04-08 07:00:35', '2025-04-08 07:00:35', 'active'),
(2, 'South Plot', 'Southern Farm Area', 8.30, 'Clay', 'Soybeans', 'Near water source, partial shade in evening', '2025-04-08 07:00:35', '2025-04-08 07:00:35', 'active'),
(3, 'East Acres', 'Eastern Farm Area', 15.00, 'Sandy', 'Wheat', 'Requires additional irrigation', '2025-04-08 07:00:35', '2025-04-08 07:00:35', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `field_observations`
--

CREATE TABLE `field_observations` (
  `id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `observation_date` date NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `soil_moisture` decimal(5,2) DEFAULT NULL,
  `weather_conditions` varchar(100) DEFAULT NULL,
  `crop_stage` varchar(100) DEFAULT NULL,
  `pest_activity` varchar(255) DEFAULT NULL,
  `irrigation_status` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `field_observations`
--

INSERT INTO `field_observations` (`id`, `supervisor_id`, `field_id`, `observation_date`, `temperature`, `humidity`, `soil_moisture`, `weather_conditions`, `crop_stage`, `pest_activity`, `irrigation_status`, `notes`, `recommendations`, `created_at`, `updated_at`) VALUES
(1, 2, 3, '2025-06-01', 0.00, 0.00, 0.00, 'Cloudy', 'Germination', 'yy', 'Required Soon', 'hy', 'yh', '2025-06-01 20:18:08', '2025-06-01 20:18:08');

-- --------------------------------------------------------

--
-- Table structure for table `financial_data`
--

CREATE TABLE `financial_data` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `type` enum('income','expense') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `financial_data`
--

INSERT INTO `financial_data` (`id`, `category_id`, `source`, `description`, `amount`, `transaction_date`, `notes`, `type`, `status`, `created_at`, `updated_at`) VALUES
(1, 6, NULL, 'Monthly feed purchase for cattle', 2500.00, '2025-05-06', 'Purchasedfr from ABC Feed Suppliers afaf', 'expense', 'active', '2025-05-06 15:56:15', NULL),
(2, NULL, 'Crop Sales', 'Sale of wheat harvest', 7500.00, '2025-05-06', 'Sold to XYZ Grain Processors', 'income', 'active', '2025-05-06 15:57:32', NULL),
(3, NULL, 'Livestock Sales', 'Sale of 5 cattle', 12000.00, '2025-05-06', 'Sold at local livestock auction', 'income', 'inactive', '2025-05-06 15:58:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `get_field_size.php`
--

CREATE TABLE `get_field_size.php` (
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `harvests`
--

CREATE TABLE `harvests` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `actual_harvest_date` date NOT NULL,
  `yield_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quality_rating` decimal(3,1) NOT NULL DEFAULT 0.0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `harvests`
--

INSERT INTO `harvests` (`id`, `crop_id`, `actual_harvest_date`, `yield_amount`, `quality_rating`, `notes`, `created_at`, `updated_at`) VALUES
(6, 1, '2025-03-26', 852.50, 4.5, 'Excellent yield and quality.', '2025-04-24 17:40:30', '2025-04-24 17:40:30'),
(8, 1, '2025-03-26', 852.50, 4.5, 'Excellent yield and quality.', '2025-04-24 17:41:11', '2025-04-24 17:41:11'),
(10, 1, '2025-04-25', 675.25, 3.5, 'Good yield despite pest issues.', '2025-04-24 17:42:01', '2025-04-24 17:42:01'),
(11, 1, '2024-03-26', 780.50, 4.0, 'Previous year harvest data', '2025-04-24 17:43:55', '2025-04-24 17:43:55'),
(12, 5, '2025-05-30', 400.00, 4.5, 'quality is 90% okay', '2025-05-30 16:38:36', '2025-05-30 16:38:36');

-- --------------------------------------------------------

--
-- Table structure for table `harvest_assignments`
--

CREATE TABLE `harvest_assignments` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `equipment_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `planned_date` date NOT NULL,
  `status` enum('scheduled','in-progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `harvest_records`
--

CREATE TABLE `harvest_records` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `harvest_date` date NOT NULL,
  `quantity_harvested` varchar(100) NOT NULL,
  `quality_grade` enum('excellent','good','fair','poor') NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `harvest_records`
--

INSERT INTO `harvest_records` (`id`, `crop_id`, `harvest_date`, `quantity_harvested`, `quality_grade`, `notes`, `recorded_by`, `created_at`, `updated_at`) VALUES
(1, 2, '2025-06-01', '10000', 'excellent', 'dd', 2, '2025-06-01 19:51:54', '2025-06-01 19:51:54'),
(2, 4, '2025-06-03', '200', 'excellent', 'this crop are  harvested early', 2, '2025-06-03 07:54:38', '2025-06-03 07:54:38');

-- --------------------------------------------------------

--
-- Table structure for table `harvest_resources`
--

CREATE TABLE `harvest_resources` (
  `id` int(11) NOT NULL,
  `harvest_id` int(11) NOT NULL,
  `resource_type` enum('staff','equipment','vehicle','other') NOT NULL,
  `resource_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `harvest_schedule`
--

CREATE TABLE `harvest_schedule` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `planned_date` date NOT NULL,
  `estimated_yield` decimal(10,2) DEFAULT NULL,
  `actual_yield` decimal(10,2) DEFAULT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `notes` text DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `health_records`
--

CREATE TABLE `health_records` (
  `id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `condition` varchar(50) NOT NULL,
  `treatment` varchar(255) NOT NULL,
  `vet_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_records`
--

INSERT INTO `health_records` (`id`, `animal_id`, `date`, `condition`, `treatment`, `vet_name`) VALUES
(6, 10, '2025-01-06', 'Normal', 'Vaccination (Annual booster)', 'Dr. Sarah Lee'),
(11, 11, '2025-01-20', 'Normal', 'give vaccination', 'Dr. Ahmed Khan'),
(13, 19, '2023-06-20', 'Normal', 'Bandage and antibiotics', 'Dr. Sarah Lee'),
(14, 21, '2023-09-05', 'Normal', 'Antibiotics', 'Dr. Sarah Lee'),
(15, 22, '2023-03-01', 'Possible parasites', 'Quarantine and observation', 'Dr. Ahmad'),
(16, 17, '2023-05-15', 'Mild dehydration', 'Electrolyte solution', 'Dr. Fatimah'),
(17, 10, '2025-05-09', 'INJURED', 'HAVE TO VACCINE', 'Dr. Sarah Lee'),
(18, 10, '2025-05-26', 'Good', 'JEJEJEJEJE', 'Dr. Sarah Lee'),
(19, 10, '2025-05-26', 'Sick', 'cd', 'Dr. Ahmed Khan'),
(20, 22, '2025-05-26', 'Quarantine', 'cc', 'Dr. Ahmed Khan'),
(21, 23, '2025-05-30', 'Good', 'Routine Devourming', 'Dr. Sarah Lee'),
(22, 24, '2025-06-03', 'Sick', 'Routine deworming', 'Dr. Sarah Lee'),
(23, 24, '2025-06-03', 'Healthy', 'we have monitored for one week', 'Dr. Sarah Lee');

-- --------------------------------------------------------

--
-- Table structure for table `housing_assignments`
--

CREATE TABLE `housing_assignments` (
  `id` int(11) NOT NULL,
  `animal_id` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `assigned_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `housing_assignments`
--

INSERT INTO `housing_assignments` (`id`, `animal_id`, `location`, `assigned_date`, `status`, `notes`, `created_at`) VALUES
(1, 11, 'Goat Shed 1', '2025-01-16', 'active', 'Goat assigned to Goat Shed 1 for monitoring after vaccination. Ensure regular cleaning and temperature checks.', '2025-01-16 14:44:55');

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `date_reported` date DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `reported_by` varchar(100) DEFAULT NULL,
  `affected_area` varchar(100) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolution_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `type`, `description`, `date_reported`, `status`, `severity`, `reported_by`, `affected_area`, `resolution_notes`, `created_at`, `resolution_date`) VALUES
(1, 'equipment', 'The water pump in Goat Shed 1 stopped functioning, causing a disruption in water supply for the animals', '2025-01-16', 'resolved', 'high', '0', 'Goat Shed 1.', 'Temporary water supply provided via manual buckets. Technician scheduled to repair the pump on 17/01/2025.', '2025-01-16 14:51:19', NULL),
(6, 'illness', 'goat sheld 1 need to change', '2025-01-16', 'resolved', 'high', 'Irfan', 'fdf', 'adfa', '2025-01-16 15:45:30', NULL),
(7, 'illness', 'ainaml id 10 is sick right now, I wan \\t admin to ud\\p\\\\\\\\djbdmd', '2025-01-17', 'resolved', 'high', 'Irfan', 'hidungh', 'checkup with doc', '2025-01-17 06:32:21', NULL),
(8, 'injury', 'hdcdh', '2025-01-20', 'resolved', 'high', 'Irfan', 'a001', 'give vaccine', '2025-01-20 04:49:43', NULL),
(9, 'illness', 'sfsfs', '2025-05-14', 'resolved', 'low', 'Supervisor', 'sds', 'dsc', '2025-05-14 13:20:05', '2025-05-26'),
(10, 'other', 'fbffb', '2025-05-25', 'resolved', 'medium', 'Supervisor', 'ddsfd', 'cc', '2025-05-25 11:56:06', NULL),
(11, 'illness', 'C001 have been injured ', '2025-05-30', 'open', 'low', 'Fira', 'C001 at housing 1', 'Dr. Sarah have given the treatment', '2025-05-30 16:36:02', NULL),
(12, 'equipment', 'tractor cant work properly', '2025-05-30', 'resolved', 'high', 'irfan', 'ladang', 'technian arrived and done the repair', '2025-05-30 16:36:54', NULL),
(13, 'equipment', 'tractor cant work properly', '2025-05-30', 'open', 'high', 'irfan', 'ladang', 'need technician', '2025-05-30 16:37:25', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_batches`
--

CREATE TABLE `inventory_batches` (
  `id` int(11) NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `received_date` date NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `purchase_order_id` int(11) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `status` enum('active','quarantine','consumed','expired','discarded') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_batches`
--

INSERT INTO `inventory_batches` (`id`, `batch_number`, `item_id`, `quantity`, `manufacturing_date`, `expiry_date`, `received_date`, `supplier_id`, `purchase_order_id`, `cost_per_unit`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(27, 'BATCH-001', 18, 500.00, '2025-04-01', '2025-10-01', '2025-05-05', 3, 0, 2.50, 'quarantine', 'For new livestock batch, stored in Warehouse A.\n2025-05-05 21:11:04 - Status changed to quarantine - ', '2025-05-05 13:05:40', '2025-06-03 08:01:53'),
(28, 'BATCH-002', 13, 100.00, '0000-00-00', '2025-06-24', '2025-05-05', 4, NULL, 15.00, 'active', 'Tools to be distributed to farm workers.', '2025-05-05 13:06:37', '2025-05-05 13:06:59'),
(29, 'BATCH-003', 1, 200.00, '2025-03-10', '2026-03-10', '2025-05-05', 1, 0, 1.20, 'expired', 'For Q2 planting cycle.\n2025-05-05 23:31:50 - Status changed to expired - ', '2025-05-05 13:08:08', '2025-05-05 15:31:50');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--

CREATE TABLE `inventory_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_categories`
--

INSERT INTO `inventory_categories` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Seeds', 'All types of seeds for planting', 'active', '2025-05-05 11:02:29', NULL),
(2, 'Fertilizers', 'Chemical and organic fertilizers', 'active', '2025-05-05 11:02:29', NULL),
(3, 'Tools', 'Farming tools and equipment', 'active', '2025-05-05 11:02:29', NULL),
(4, 'Pesticides', 'Pest control products', 'active', '2025-05-05 11:02:29', NULL),
(5, 'Feed', 'Animal feed and supplements', 'active', '2025-05-05 11:02:29', NULL),
(6, 'General', 'Miscellaneous items', 'active', '2025-05-05 11:02:29', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `current_quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(10,2) NOT NULL DEFAULT 0.00,
  `maximum_level` decimal(10,2) DEFAULT NULL,
  `unit_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `batch_tracking_enabled` tinyint(1) DEFAULT 0,
  `last_updated` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_name`, `sku`, `category_id`, `description`, `unit_of_measure`, `current_quantity`, `reorder_level`, `maximum_level`, `unit_cost`, `expiry_date`, `batch_number`, `supplier_id`, `status`, `created_at`, `updated_at`, `is_active`, `batch_tracking_enabled`, `last_updated`) VALUES
(1, 'Corn Seeds Premium', 'SEED-001', 1, 'High-yield corn seeds for tropical climates', 'kg', 245.00, 10.00, 100.00, 25.50, '2025-12-31', 'CRNSEED2023-01', 1, 'active', '2025-05-01 09:12:48', '2025-05-06 13:04:45', 1, 1, NULL),
(2, 'Rice Seeds IR64', 'SEED-002', 1, 'IR64 rice variety seeds, drought-resistant', 'kg', 100.00, 20.00, 200.00, 15.75, '2025-06-30', 'RCSD2023-05', 1, 'active', '2025-05-01 09:12:48', '2025-05-05 12:59:44', 1, 1, NULL),
(3, 'Tomato Seeds Hybrid', 'SEED-003', 1, 'Hybrid tomato seeds for greenhouse cultivation', 'g', 1280.00, 100.00, 1000.00, 0.50, '2025-08-15', 'TMTSEED2023-02', 1, 'active', '2025-05-01 09:12:48', '2025-06-03 08:14:31', 1, 1, '2025-06-03 16:14:31'),
(4, 'Cucumber Seeds', 'SEED-004', 1, 'High-yield cucumber seeds', 'g', 304.00, 75.00, 600.00, 0.35, '2025-09-20', 'CCSEED2023-03', 1, 'active', '2025-05-01 09:12:48', '2025-05-30 16:40:45', 1, 1, '2025-05-31 00:40:45'),
(5, 'NPK Fertilizer 15-15-15', 'FERT-001', 2, 'Balanced NPK fertilizer for general use', 'kg', 200.00, 50.00, 500.00, 8.25, '2026-03-15', 'NPK2023-01', 2, 'active', '2025-05-01 09:12:48', '2025-05-06 13:05:32', 1, 1, NULL),
(6, 'Organic Compost', 'FERT-002', 2, 'Premium organic compost for all crops', 'kg', 150.00, 30.00, 300.00, 5.50, '2026-12-31', 'ORGCMP2023-01', 3, 'active', '2025-05-01 09:12:48', '2025-05-05 13:02:40', 1, 1, NULL),
(7, 'Urea Fertilizer', 'FERT-003', 2, 'High-nitrogen fertilizer for leafy crops', 'kg', 75.00, 25.00, 200.00, 7.80, '2025-11-10', 'UREA2023-02', 2, 'active', '2025-05-01 09:12:48', '2025-05-05 13:02:40', 1, 1, NULL),
(8, 'Bone Meal', 'FERT-004', 2, 'Organic phosphorus-rich bone meal fertilizer', 'kg', 40.00, 10.00, 100.00, 12.30, '2026-05-20', 'BNML2023-01', 3, 'active', '2025-05-01 09:12:48', '2025-05-05 13:02:40', 1, 1, NULL),
(9, 'Organic Insecticide', 'PEST-001', 3, 'Natural insecticide for vegetable crops', 'l', 27.00, 5.00, 50.00, 35.75, '2025-05-20', 'ORGINS2023-01', 5, 'active', '2025-05-01 09:12:48', '2025-05-06 13:05:18', 1, 1, NULL),
(10, 'Fungicide Solution', 'PEST-002', 3, 'Broad-spectrum fungicide for crops', 'l', 25.00, 5.00, 40.00, 42.50, '2024-12-15', 'FUNG2023-02', 5, 'active', '2025-05-01 09:12:48', '2025-05-05 13:02:40', 1, 1, NULL),
(11, 'Weed Control Spray', 'PEST-003', 3, 'Selective herbicide for weed control', 'l', 15.00, 3.00, 30.00, 55.00, '2025-02-28', 'WDCNTRL2023-01', 5, 'inactive', '2025-05-01 09:12:48', '2025-05-05 13:02:40', 1, 1, NULL),
(12, 'Ant Bait Granules', 'PEST-004', 3, 'Specialized ant control for farms', 'kg', 9.00, 2.00, 20.00, 28.90, '2025-08-10', 'ANTBAIT2023-01', 5, 'active', '2025-05-01 09:12:48', '2025-05-27 13:39:02', 1, 1, '2025-05-27 21:39:02'),
(13, 'Garden Hoe', 'TOOL-001', 4, 'Durable garden hoe with wooden handle', 'pieces', 115.00, 3.00, 20.00, 45.00, NULL, 'HOES2023-01', 4, 'active', '2025-05-01 09:12:48', '2025-05-05 13:06:37', 1, 1, NULL),
(14, 'Pruning Shears', 'TOOL-002', 4, 'Professional-grade pruning shears', 'pieces', 8.00, 2.00, 15.00, 65.00, NULL, 'SHEARS2023-01', 4, 'active', '2025-05-01 09:12:48', '2025-05-05 13:02:40', 1, 1, NULL),
(15, 'Watering Can', 'TOOL-003', 4, '10L plastic watering can', 'pieces', 13.00, 3.00, 20.00, 25.00, NULL, 'WTCAN2023-01', 4, 'active', '2025-05-01 09:12:48', '2025-05-05 13:02:40', 1, 1, NULL),
(16, 'Hand Trowel Set', 'TOOL-004', 4, 'Set of 3 hand trowels for planting', 'pieces', 7.00, 2.00, 15.00, 35.00, NULL, 'TROWEL2023-01', 4, 'active', '2025-05-01 09:12:48', '2025-05-05 13:02:40', 1, 1, NULL),
(17, 'Chicken Feed', 'FEED-001', 5, 'Balanced nutrition for laying hens', 'kg', 250.00, 50.00, 500.00, 4.75, '2024-06-15', 'CHKFD2023-01', 3, 'active', '2025-05-01 09:12:48', '2025-05-05 12:59:44', 1, 1, NULL),
(18, 'Cattle Feed', 'FEED-002', 5, 'High-protein feed for dairy cattle', 'kg', 850.00, 100.00, 700.00, 5.25, '2024-05-20', 'CTLFD2023-01', 3, 'active', '2025-05-01 09:12:48', '2025-05-05 13:05:40', 1, 1, NULL),
(19, 'Fish Feed Pellets', 'FEED-003', 5, 'Floating pellets for tilapia farming', 'kg', 100.00, 25.00, 200.00, 7.80, '2024-04-10', 'FSHFD2023-01', 3, 'active', '2025-05-01 09:12:48', '2025-05-05 12:59:44', 1, 1, NULL),
(20, 'Goat Feed Mix', 'FEED-004', 5, 'Specialized feed mix for dairy goats', 'kg', 180.00, 40.00, 350.00, 6.50, '2024-07-25', 'GTFD2023-01', 3, 'active', '2025-05-01 09:12:48', '2025-05-05 12:59:44', 1, 1, NULL),
(21, 'Chicken Feed', 'CF001', 5, 'Premium chicken feed for layers', 'kg', 300.00, 100.00, 500.00, 0.95, NULL, NULL, NULL, 'active', '2025-05-05 13:01:38', NULL, 1, 1, NULL),
(22, 'Cattle Feed', 'CF002', 5, 'Standard cattle feed mix', 'kg', 500.00, 200.00, 1000.00, 0.85, NULL, NULL, NULL, 'active', '2025-05-05 13:01:38', NULL, 1, 1, NULL),
(23, 'Corn Seed', 'SD001', 1, 'High-yield corn seed variety', 'kg', 100.00, 50.00, 200.00, 3.25, NULL, NULL, NULL, 'active', '2025-05-05 13:01:38', NULL, 1, 1, NULL),
(24, 'Tomato Seed', 'SD002', 1, 'Heirloom tomato seed variety', 'kg', 50.00, 25.00, 100.00, 5.50, NULL, NULL, NULL, 'active', '2025-05-05 13:01:38', NULL, 1, 1, NULL),
(25, 'Pig Feed', 'PF001', 5, 'Standard pig feed mix', 'kg', 200.00, 75.00, 300.00, 1.10, NULL, NULL, NULL, 'active', '2025-05-05 13:01:38', NULL, 1, 1, NULL),
(26, 'Wheat Seed', 'SD003', 1, 'Standard wheat seed', 'kg', 0.00, 40.00, 150.00, 2.80, NULL, NULL, NULL, 'active', '2025-05-05 13:01:38', NULL, 1, 1, NULL),
(27, 'Fish Feed', 'FF001', 5, 'Special aquaculture feed', 'kg', 12.00, 30.00, 100.00, 2.15, NULL, NULL, NULL, 'active', '2025-05-05 13:01:38', '2025-05-30 16:41:57', 1, 1, '2025-05-31 00:41:57');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_log`
--

CREATE TABLE `inventory_log` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `action_type` enum('initial_add','manual_add','manual_remove','sale','purchase','waste') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `previous_quantity` decimal(10,2) NOT NULL,
  `new_quantity` decimal(10,2) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_log`
--

INSERT INTO `inventory_log` (`id`, `item_id`, `action_type`, `quantity`, `previous_quantity`, `new_quantity`, `user_id`, `created_at`, `notes`, `batch_number`, `expiry_date`, `unit_cost`, `supplier_id`) VALUES
(1, 1, 'initial_add', 50.00, 0.00, 50.00, 1, '2023-11-01 00:30:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(2, 2, 'initial_add', 100.00, 0.00, 100.00, 1, '2023-11-01 00:45:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(3, 3, 'initial_add', 500.00, 0.00, 500.00, 1, '2023-11-01 01:00:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(4, 4, 'initial_add', 300.00, 0.00, 300.00, 1, '2023-11-01 01:15:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(5, 5, 'initial_add', 200.00, 0.00, 200.00, 1, '2023-11-01 01:30:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(6, 6, 'initial_add', 150.00, 0.00, 150.00, 1, '2023-11-01 01:45:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(7, 7, 'initial_add', 75.00, 0.00, 75.00, 1, '2023-11-01 02:00:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(8, 8, 'initial_add', 40.00, 0.00, 40.00, 1, '2023-11-01 02:15:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(9, 9, 'initial_add', 30.00, 0.00, 30.00, 1, '2023-11-01 02:30:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(10, 10, 'initial_add', 25.00, 0.00, 25.00, 1, '2023-11-01 02:45:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(11, 11, 'initial_add', 15.00, 0.00, 15.00, 1, '2023-11-01 03:00:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(12, 12, 'initial_add', 10.00, 0.00, 10.00, 1, '2023-11-01 03:15:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(13, 13, 'initial_add', 15.00, 0.00, 15.00, 1, '2023-11-01 03:30:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(14, 14, 'initial_add', 8.00, 0.00, 8.00, 1, '2023-11-01 03:45:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(15, 15, 'initial_add', 12.00, 0.00, 12.00, 1, '2023-11-01 04:00:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(16, 16, 'initial_add', 7.00, 0.00, 7.00, 1, '2023-11-01 04:15:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(17, 17, 'initial_add', 250.00, 0.00, 250.00, 1, '2023-11-01 04:30:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(18, 18, 'initial_add', 350.00, 0.00, 350.00, 1, '2023-11-01 04:45:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(19, 19, 'initial_add', 100.00, 0.00, 100.00, 1, '2023-11-01 05:00:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(20, 20, 'initial_add', 180.00, 0.00, 180.00, 1, '2023-11-01 05:15:00', 'Initial inventory addition', NULL, NULL, NULL, NULL),
(21, 1, 'manual_remove', 10.00, 50.00, 40.00, 1, '2023-11-15 01:00:00', 'Removed for farm use', NULL, NULL, NULL, NULL),
(22, 2, 'manual_remove', 15.00, 100.00, 85.00, 1, '2023-11-15 01:15:00', 'Removed for farm use', NULL, NULL, NULL, NULL),
(23, 1, 'manual_add', 20.00, 40.00, 60.00, 1, '2023-11-20 02:00:00', 'Restocked from supplier', NULL, NULL, NULL, NULL),
(24, 2, 'manual_add', 30.00, 85.00, 115.00, 1, '2023-11-20 02:15:00', 'Restocked from supplier', NULL, NULL, NULL, NULL),
(25, 10, 'manual_remove', 5.00, 25.00, 20.00, 1, '2023-11-25 06:00:00', 'Used for crop treatment', NULL, NULL, NULL, NULL),
(26, 15, 'manual_add', 2.00, 0.00, 0.00, 1, '2025-05-01 14:31:30', 'nn', NULL, NULL, NULL, NULL),
(27, 15, 'manual_add', 2.00, 0.00, 0.00, 1, '2025-05-01 14:31:52', '', NULL, NULL, NULL, NULL),
(28, 15, 'waste', 3.00, 0.00, 0.00, 1, '2025-05-01 14:32:07', 'damaged', NULL, NULL, NULL, NULL),
(29, 12, 'manual_add', 2.00, 0.00, 0.00, 1, '2025-05-01 16:55:37', '', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_usage`
--

CREATE TABLE `inventory_usage` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `usage_date` date NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_usage`
--

INSERT INTO `inventory_usage` (`id`, `item_id`, `quantity`, `usage_date`, `purpose`, `assigned_to`, `notes`, `created_by`, `created_at`) VALUES
(2, 1, 5.00, '2025-05-06', 'Replanting damaged areas', 'Irfan Danial', 'Used to replant sections damaged by recent flooding', 1, '2025-05-06 21:04:45'),
(3, 9, 3.00, '2025-05-06', 'Aphid control in greenhouse', 'Irfan Danial', 'Applied with backpack sprayer, diluted at 15ml per liter of water', 1, '2025-05-06 21:05:18'),
(5, 12, 1.00, '2025-05-27', 'cc', 'cc', 'cc', 2, '2025-05-27 21:39:02'),
(6, 27, 2.00, '2025-05-30', 'to feed the animals', 'Irfan Danial', '', 2, '2025-05-31 00:41:57'),
(7, 3, 20.00, '2025-06-03', 'crop harvest', 'irfan', '', 2, '2025-06-03 16:14:31');

-- --------------------------------------------------------

--
-- Table structure for table `irrigation_logs`
--

CREATE TABLE `irrigation_logs` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `irrigation_date` date NOT NULL,
  `amount_used` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `irrigation_logs`
--

INSERT INTO `irrigation_logs` (`id`, `schedule_id`, `irrigation_date`, `amount_used`, `notes`, `created_at`) VALUES
(1, 1, '2025-04-14', 5, '', '2025-04-14 14:27:41'),
(2, 1, '2025-04-14', 5, '', '2025-04-14 14:27:52'),
(3, 1, '2025-06-01', 6, 'bggb', '2025-06-01 17:28:40'),
(4, 1, '2025-06-01', 6, '', '2025-06-01 17:28:49'),
(5, 1, '2025-06-01', 6, 'dd', '2025-06-01 18:39:36'),
(6, 1, '2025-06-01', 6, '', '2025-06-01 19:52:42');

-- --------------------------------------------------------

--
-- Table structure for table `irrigation_schedules`
--

CREATE TABLE `irrigation_schedules` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `schedule_description` varchar(255) NOT NULL,
  `water_amount` int(11) NOT NULL,
  `last_irrigation_date` date DEFAULT NULL,
  `next_irrigation_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `irrigation_schedules`
--

INSERT INTO `irrigation_schedules` (`id`, `crop_id`, `schedule_description`, `water_amount`, `last_irrigation_date`, `next_irrigation_date`, `created_at`) VALUES
(1, 4, 'Every Monday, Wednesday, Friday', 6, '2025-06-01', '2025-06-04', '2025-04-14 14:27:26');

-- --------------------------------------------------------

--
-- Table structure for table `item_categories`
--

CREATE TABLE `item_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_categories`
--

INSERT INTO `item_categories` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Seeds', 'All types of seeds for planting', 'active', '2025-05-01 09:12:48', NULL),
(2, 'Fertilizers', 'Organic and chemical fertilizers', 'active', '2025-05-01 09:12:48', NULL),
(3, 'Pesticides', 'Pest control products', 'active', '2025-05-01 09:12:48', NULL),
(4, 'Tools', 'Farming tools and equipment', 'active', '2025-05-01 09:12:48', NULL),
(5, 'Feed', 'Animal feed and supplements', 'active', '2025-05-01 09:12:48', NULL),
(6, 'f', 'bg', 'inactive', '2025-05-01 14:57:42', '2025-05-01 14:57:48');

-- --------------------------------------------------------

--
-- Table structure for table `moisture_thresholds`
--

CREATE TABLE `moisture_thresholds` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `low_threshold` decimal(5,2) DEFAULT 30.00,
  `high_threshold` decimal(5,2) DEFAULT 70.00,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pesticide_applications`
--

CREATE TABLE `pesticide_applications` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `pesticide_type_id` int(11) NOT NULL,
  `application_date` date NOT NULL,
  `quantity_used` varchar(50) NOT NULL,
  `application_method` varchar(100) NOT NULL,
  `target_pest` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `weather_conditions` varchar(255) DEFAULT NULL,
  `operator_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesticide_applications`
--

INSERT INTO `pesticide_applications` (`id`, `crop_id`, `pesticide_type_id`, `application_date`, `quantity_used`, `application_method`, `target_pest`, `notes`, `weather_conditions`, `operator_name`, `created_at`, `updated_at`) VALUES
(1, 1, 19, '2025-04-16', '2 L/acre', 'Spraying', 'Aphids', 'Apply evenly on affected areas.', 'Clear, 25°C, light breeze', 'Irfan', '2025-04-16 16:33:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pesticide_types`
--

CREATE TABLE `pesticide_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `active_ingredients` text DEFAULT NULL,
  `safe_handling` text DEFAULT NULL,
  `withholding_period` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesticide_types`
--

INSERT INTO `pesticide_types` (`id`, `name`, `type`, `active_ingredients`, `safe_handling`, `withholding_period`) VALUES
(1, 'Paraquat', 'Herbicide', 'Paraquat dichloride', 'Use full PPE including respirator, gloves, and goggles. Highly toxic if ingested.', '7 days'),
(2, 'Glyphosate', 'Herbicide', 'Glyphosate', 'Wear gloves and eye protection. Avoid skin contact.', '14 days'),
(3, 'Basta', 'Herbicide', 'Glufosinate-ammonium', 'Use protective clothing and gloves. Avoid inhalation and contact with skin.', '7 days'),
(4, '2,4-D Amine', 'Herbicide', '2,4-Dichlorophenoxyacetic acid', 'Wear full PPE. Avoid drift to non-target plants.', '7 days'),
(5, 'Malathion', 'Insecticide', 'Malathion', 'Use respiratory protection. Avoid inhalation.', '7 days'),
(6, 'Cypermethrin', 'Insecticide', 'Cypermethrin', 'Wear protective clothing and mask. Avoid contact with skin and eyes.', '3 days'),
(7, 'Deltamethrin', 'Insecticide', 'Deltamethrin', 'Use protective clothing and avoid skin contact.', '3 days'),
(8, 'Chlorpyrifos', 'Insecticide', 'Chlorpyrifos', 'Wear full PPE. Highly toxic to aquatic organisms.', '21 days'),
(9, 'Abamectin', 'Insecticide', 'Abamectin', 'Use gloves and protective clothing. Toxic to fish and bees.', '7 days'),
(10, 'Imidacloprid', 'Insecticide', 'Imidacloprid', 'Wear protective clothing. Harmful to bees.', '14 days'),
(11, 'Mancozeb', 'Fungicide', 'Manganese ethylenebis(dithiocarbamate)', 'Wear protective clothing and respirator.', '14 days'),
(12, 'Copper Oxychloride', 'Fungicide', 'Copper oxychloride', 'Use gloves and eye protection. May cause skin and eye irritation.', '7 days'),
(13, 'Difenoconazole', 'Fungicide', 'Difenoconazole', 'Wear protective clothing. Avoid contact with skin.', '7 days'),
(14, 'Hexaconazole', 'Fungicide', 'Hexaconazole', 'Use protective equipment. Avoid skin contact.', '14 days'),
(15, 'Chlorothalonil', 'Fungicide', 'Chlorothalonil', 'Wear respirator and protective clothing.', '7 days'),
(16, 'Carbendazim', 'Fungicide', 'Carbendazim', 'Use protective clothing and gloves. Harmful if swallowed.', '14 days'),
(17, 'Brodifacoum', 'Rodenticide', 'Brodifacoum', 'Handle with care. Keep away from children and pets.', 'Not for crop application'),
(18, 'Bromadiolone', 'Rodenticide', 'Bromadiolone', 'Use gloves when handling. Store in secure location.', 'Not for crop application'),
(19, 'Bacillus thuringiensis', 'Biological Insecticide', 'Bacillus thuringiensis', 'Low toxicity to humans. Safe for most beneficial insects.', '0 days'),
(20, 'Trichoderma', 'Biological Fungicide', 'Trichoderma spp.', 'Safe for handling, minimal protection needed.', '0 days'),
(21, 'Neem Oil', 'Botanical Insecticide', 'Azadirachtin', 'Mild irritant. Wash hands after use.', '1 day');

-- --------------------------------------------------------

--
-- Table structure for table `plantings`
--

CREATE TABLE `plantings` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `planting_date` date DEFAULT NULL,
  `expected_harvest_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plantings`
--

INSERT INTO `plantings` (`id`, `field_id`, `crop_id`, `planting_date`, `expected_harvest_date`, `status`, `created_at`) VALUES
(1, 1, 5, '2025-05-02', '2025-08-30', 'active', '2025-06-01 21:41:52'),
(2, 2, 2, '2025-05-02', '2025-08-30', 'active', '2025-06-01 21:41:52'),
(3, 3, 3, '2025-05-02', '2025-08-30', 'active', '2025-06-01 21:41:52');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `purchase_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` enum('pending','in_transit','delivered','delayed','cancelled') NOT NULL DEFAULT 'pending',
  `reference_number` varchar(100) DEFAULT NULL,
  `invoice_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchases`
--

INSERT INTO `purchases` (`id`, `supplier_id`, `purchase_date`, `expected_delivery_date`, `delivery_date`, `status`, `reference_number`, `invoice_number`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-01-01', '2025-01-10', NULL, 'pending', 'PO-20250101-001', NULL, NULL, 1, '2025-05-02 00:44:25', NULL),
(2, 2, '2025-01-02', '2025-01-12', NULL, 'in_transit', 'PO-20250102-001', NULL, NULL, 1, '2025-05-02 00:44:25', NULL),
(3, 3, '2025-01-03', '2025-01-13', NULL, 'delivered', 'PO-20250103-001', NULL, NULL, 1, '2025-05-02 00:44:25', NULL),
(4, 4, '2025-01-04', '2025-01-14', NULL, '', 'PO-20250104-001', NULL, NULL, 1, '2025-05-02 00:44:25', NULL),
(5, 5, '2025-01-05', '2025-01-15', NULL, 'in_transit', 'PO-20250105-001', NULL, NULL, 1, '2025-05-02 00:44:25', '2025-05-05 18:53:20'),
(6, 5, '2025-05-05', '2025-05-05', NULL, 'pending', '', NULL, 'bfbf', 1, '2025-05-05 19:09:12', NULL),
(7, 2, '2025-05-05', '2025-05-09', NULL, 'pending', '', NULL, 'hi', 1, '2025-05-05 20:11:43', NULL),
(8, 4, '2025-05-08', '2025-05-22', NULL, 'pending', '', NULL, 'dfdsfgsg', 1, '2025-05-09 02:57:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_items`
--

CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `inventory_item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchase_items`
--

INSERT INTO `purchase_items` (`id`, `purchase_id`, `inventory_item_id`, `quantity`, `unit_price`, `batch_number`, `manufacturing_date`, `expiry_date`, `notes`) VALUES
(1, 1, 1, 100.00, 5.00, NULL, NULL, NULL, NULL),
(2, 1, 3, 50.00, 3.50, NULL, NULL, NULL, NULL),
(3, 2, 2, 75.00, 7.00, NULL, NULL, NULL, NULL),
(4, 2, 4, 80.00, 4.25, NULL, NULL, NULL, NULL),
(5, 3, 5, 60.00, 11.50, NULL, NULL, NULL, NULL),
(6, 3, 6, 40.00, 10.00, NULL, NULL, NULL, NULL),
(7, 3, 7, 25.00, 24.00, NULL, NULL, NULL, NULL),
(8, 4, 8, 30.00, 28.50, NULL, NULL, NULL, NULL),
(9, 4, 9, 45.00, 14.00, NULL, NULL, NULL, NULL),
(10, 5, 10, 20.00, 4.75, NULL, NULL, NULL, NULL),
(11, 6, 8, 1.00, 56.00, 'ggdghd', NULL, '2027-10-05', NULL),
(12, 6, 3, 4.00, 43.00, 'dafaf', NULL, '2027-12-13', NULL),
(13, 7, 12, 1.00, 24.00, 'a123', NULL, '2027-10-28', NULL),
(14, 7, 22, 2.00, 12.00, 'c145', NULL, '2027-10-25', NULL),
(15, 8, 13, 1.00, 26.00, 'htntnumutu', NULL, '2028-10-09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purefarm_growth_milestones`
--

CREATE TABLE `purefarm_growth_milestones` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `stage` varchar(50) NOT NULL,
  `date_reached` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purefarm_growth_milestones`
--

INSERT INTO `purefarm_growth_milestones` (`id`, `crop_id`, `stage`, `date_reached`, `notes`, `created_at`) VALUES
(1, 1, 'seedling', '2025-04-24', '', '2025-04-24 17:04:25'),
(2, 1, 'vegetative', '2025-04-24', '', '2025-04-24 17:04:34'),
(3, 1, 'vegetative', '2025-04-24', '', '2025-04-24 17:09:49'),
(4, 1, 'vegetative', '2025-04-24', '', '2025-04-24 17:11:06'),
(5, 1, 'flowering', '2025-04-24', 'good', '2025-04-24 17:11:37'),
(6, 5, 'flowering', '2025-05-06', '', '2025-05-06 18:55:00');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Farm Manager', 'Manages overall farm operations', '2025-04-20 14:34:00', '2025-04-20 14:34:00'),
(2, 'Field Supervisor', 'Supervises field operations and workers', '2025-04-20 14:34:00', '2025-04-20 14:34:00'),
(3, 'Livestock Manager', 'Manages livestock and animal care', '2025-04-20 14:34:00', '2025-04-20 14:34:00'),
(4, 'Crop Specialist', 'Manages crop cultivation and development', '2025-04-20 14:34:00', '2025-04-20 14:34:00'),
(5, 'Equipment Operator', 'Operates farm machinery and equipment', '2025-04-20 14:34:00', '2025-04-20 14:34:00'),
(6, 'General Farmhand', 'Performs various tasks around the farm', '2025-04-20 14:34:00', '2025-04-20 14:34:00'),
(7, 'Administrative Staff', 'Handles office work and documentation', '2025-04-20 14:34:00', '2025-04-20 14:34:00'),
(8, 'Veterinarian', 'Provides animal healthcare services', '2025-04-20 14:34:00', '2025-04-20 14:34:00');

-- --------------------------------------------------------

--
-- Table structure for table `soil_moisture`
--

CREATE TABLE `soil_moisture` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `reading_date` date NOT NULL,
  `moisture_percentage` decimal(5,2) NOT NULL,
  `reading_depth` varchar(20) DEFAULT NULL,
  `reading_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `soil_moisture`
--

INSERT INTO `soil_moisture` (`id`, `field_id`, `reading_date`, `moisture_percentage`, `reading_depth`, `reading_method`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, '2025-04-22', 23.70, '15', 'TDR Probe', 'Consistent with previous readings. Slightly drier than usual for this time of year.', '2025-04-22 05:27:01', '2025-04-22 05:27:01'),
(2, 1, '2025-04-22', 31.20, '10', 'Soil Moisture Sensor', 'Slightly above optimal range. May delay next irrigation.', '2025-04-22 05:28:13', '2025-04-22 05:28:13'),
(3, 2, '2025-04-22', 18.50, '20', 'Manual Check', 'Dry patches observed. Consider checking irrigation system.', '2025-04-22 05:28:46', '2025-04-22 05:28:46'),
(4, 3, '2025-04-09', 29.80, '25', 'Tensiometer', 'Reading indicates good retention. No immediate action needed.', '2025-04-22 05:29:16', '2025-04-22 05:29:16'),
(5, 1, '2025-04-19', 15.00, '30', 'Laboratory Analysis', 'Low moisture at deeper depth. May need deep irrigation soon.', '2025-04-22 05:29:50', '2025-04-22 05:29:50');

-- --------------------------------------------------------

--
-- Table structure for table `soil_nutrients`
--

CREATE TABLE `soil_nutrients` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `test_date` date NOT NULL,
  `nitrogen` decimal(10,2) DEFAULT NULL COMMENT 'in ppm',
  `phosphorus` decimal(10,2) DEFAULT NULL COMMENT 'in ppm',
  `potassium` decimal(10,2) DEFAULT NULL COMMENT 'in ppm',
  `ph_level` decimal(4,2) DEFAULT NULL,
  `organic_matter` decimal(5,2) DEFAULT NULL COMMENT 'in percentage',
  `calcium` decimal(10,2) DEFAULT NULL COMMENT 'in ppm',
  `magnesium` decimal(10,2) DEFAULT NULL COMMENT 'in ppm',
  `sulfur` decimal(10,2) DEFAULT NULL COMMENT 'in ppm',
  `test_method` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `soil_nutrients`
--

INSERT INTO `soil_nutrients` (`id`, `field_id`, `test_date`, `nitrogen`, `phosphorus`, `potassium`, `ph_level`, `organic_matter`, `calcium`, `magnesium`, `sulfur`, `test_method`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-03-28', 18.50, 22.30, 155.80, 6.40, 3.20, 1250.00, 205.00, 14.20, 'Lab Analysis', 'Annual soil test', '2025-04-22 07:03:25', '2025-04-22 07:03:25'),
(2, 2, '2025-04-02', 22.10, 19.80, 198.50, 6.80, 4.10, 1320.00, 220.00, 12.50, 'Lab Analysis', 'Pre-planting assessment', '2025-04-22 07:03:25', '2025-04-22 07:03:25'),
(3, 3, '2025-04-07', 8.40, 12.70, 110.30, 5.20, 2.40, 980.00, 150.00, 10.80, 'Lab Analysis', 'Low nitrogen detected', '2025-04-22 07:03:25', '2025-04-22 07:03:25'),
(4, 1, '2025-04-12', 20.30, 24.50, 162.40, 6.50, 3.30, 1270.00, 210.00, 14.50, 'Lab Analysis', 'Post-fertilizer application test', '2025-04-22 07:03:25', '2025-04-22 07:03:25'),
(5, 2, '2025-04-15', 24.80, 21.30, 205.70, 6.90, 4.20, 1350.00, 225.00, 13.00, 'Lab Analysis', 'Seasonal monitoring', '2025-04-22 07:03:25', '2025-04-22 07:03:25'),
(6, 3, '2025-04-17', 15.20, 18.40, 135.60, 5.70, 2.80, 1050.00, 175.00, 11.50, 'Lab Analysis', 'After lime application', '2025-04-22 07:03:25', '2025-04-22 07:03:25'),
(7, 1, '2025-04-19', 21.50, 25.20, 168.30, 6.60, 3.40, 1290.00, 215.00, 14.80, 'Soil Test Kit', 'Routine monitoring', '2025-04-22 07:03:25', '2025-04-22 07:03:25'),
(8, 2, '2025-04-21', 25.60, 22.10, 210.40, 7.00, 4.30, 1380.00, 230.00, 13.20, 'Soil Test Kit', 'Pre-irrigation check', '2025-04-22 07:03:25', '2025-04-22 07:03:25');

-- --------------------------------------------------------

--
-- Table structure for table `soil_tests`
--

CREATE TABLE `soil_tests` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `test_date` date NOT NULL,
  `ph_level` decimal(3,1) DEFAULT NULL,
  `moisture_percentage` decimal(5,2) DEFAULT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `nitrogen_level` varchar(10) DEFAULT NULL,
  `phosphorus_level` varchar(10) DEFAULT NULL,
  `potassium_level` varchar(10) DEFAULT NULL,
  `organic_matter` decimal(5,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `soil_tests`
--

INSERT INTO `soil_tests` (`id`, `field_id`, `test_date`, `ph_level`, `moisture_percentage`, `temperature`, `nitrogen_level`, `phosphorus_level`, `potassium_level`, `organic_matter`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, '2024-04-20', 6.5, 35.00, 22.50, 'Medium', 'High', 'Medium', 4.20, 'Regular seasonal soil test.', '2025-04-21 04:51:52', '2025-04-21 05:36:26'),
(2, 2, '2024-04-20', 5.8, 28.00, 21.00, 'Low', 'Medium', 'Medium', 3.10, 'Signs of nutrient deficiency observed.', '2025-04-21 04:51:52', '2025-04-21 05:36:26'),
(3, 3, '2024-04-20', 7.2, 42.00, 23.00, 'High', 'Medium', 'High', 5.50, 'Soil shows good overall health.', '2025-04-21 05:03:46', '2025-04-21 05:36:26'),
(4, 1, '2024-04-20', 6.2, 30.00, 18.50, 'Medium', 'Medium', 'Low', 3.80, 'Test after winter season.', '2025-04-21 05:03:46', '2025-04-21 05:36:26'),
(5, 2, '2024-04-20', 5.5, 25.00, 18.00, 'Low', 'Low', 'Medium', 2.90, 'Need lime application to raise pH.', '2025-04-21 05:03:46', '2025-04-21 05:36:26'),
(7, 1, '2025-04-18', 6.5, 40.00, 22.00, 'Medium', 'High', 'Medium', 4.10, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(8, 1, '2025-04-19', 6.4, 39.50, 22.50, 'Medium', 'High', 'Medium', 4.20, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(9, 1, '2025-04-20', 6.6, 39.00, 23.00, 'Medium', 'High', 'Medium', 4.10, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(10, 1, '2025-04-21', 6.5, 38.50, 23.50, 'Medium', 'High', 'Medium', 4.00, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(11, 1, '2025-04-22', 6.7, 38.00, 24.00, 'Medium', 'High', 'Medium', 4.10, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(12, 1, '2025-04-23', 6.6, 37.50, 24.50, 'Medium', 'High', 'Medium', 4.20, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(13, 1, '2025-04-24', 6.5, 37.00, 24.00, 'Medium', 'High', 'Medium', 4.30, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(14, 2, '2025-04-18', 6.8, 38.00, 21.00, 'Low', 'Medium', 'Medium', 3.00, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(15, 2, '2025-04-19', 6.7, 37.50, 21.50, 'Low', 'Medium', 'Medium', 3.10, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(16, 2, '2025-04-20', 6.9, 37.00, 22.00, 'Low', 'Medium', 'Medium', 3.00, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(17, 2, '2025-04-21', 6.8, 36.50, 22.50, 'Low', 'Medium', 'Medium', 3.10, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(18, 2, '2025-04-22', 7.0, 36.00, 23.00, 'Low', 'Medium', 'Medium', 3.20, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(19, 2, '2025-04-23', 6.9, 35.50, 23.50, 'Low', 'Medium', 'Medium', 3.10, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(20, 2, '2025-04-24', 6.8, 35.00, 23.00, 'Low', 'Medium', 'Medium', 3.00, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(21, 3, '2025-04-18', 7.2, 32.00, 23.00, 'Medium', 'Low', 'High', 5.40, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(22, 3, '2025-04-19', 7.1, 31.50, 23.50, 'Medium', 'Low', 'High', 5.50, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(23, 3, '2025-04-20', 7.3, 31.00, 24.00, 'Medium', 'Low', 'High', 5.40, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(24, 3, '2025-04-21', 7.2, 30.50, 24.50, 'Medium', 'Low', 'High', 5.50, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(25, 3, '2025-04-22', 7.4, 30.00, 25.00, 'Medium', 'Low', 'High', 5.60, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(26, 3, '2025-04-23', 7.3, 29.50, 25.50, 'Medium', 'Low', 'High', 5.50, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(27, 3, '2025-04-24', 7.2, 29.00, 25.00, 'Medium', 'Low', 'High', 5.40, 'Regular test', '2025-04-24 16:08:57', '2025-04-24 16:08:57'),
(35, 1, '2025-04-18', 6.5, 40.00, 22.00, 'Medium', 'High', 'Medium', 4.10, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(36, 1, '2025-04-19', 6.4, 39.50, 22.50, 'Medium', 'High', 'Medium', 4.20, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(37, 1, '2025-04-20', 6.6, 39.00, 23.00, 'Medium', 'High', 'Medium', 4.10, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(38, 1, '2025-04-21', 6.5, 38.50, 23.50, 'Medium', 'High', 'Medium', 4.00, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(39, 1, '2025-04-22', 6.7, 38.00, 24.00, 'Medium', 'High', 'Medium', 4.10, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(40, 1, '2025-04-23', 6.6, 37.50, 24.50, 'Medium', 'High', 'Medium', 4.20, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(41, 1, '2025-04-24', 6.5, 37.00, 24.00, 'Medium', 'High', 'Medium', 4.30, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(42, 2, '2025-04-18', 6.8, 38.00, 21.00, 'Low', 'Medium', 'Medium', 3.00, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(43, 2, '2025-04-19', 6.7, 37.50, 21.50, 'Low', 'Medium', 'Medium', 3.10, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(44, 2, '2025-04-20', 6.9, 37.00, 22.00, 'Low', 'Medium', 'Medium', 3.00, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(45, 2, '2025-04-21', 6.8, 36.50, 22.50, 'Low', 'Medium', 'Medium', 3.10, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(46, 2, '2025-04-22', 7.0, 36.00, 23.00, 'Low', 'Medium', 'Medium', 3.20, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(47, 2, '2025-04-23', 6.9, 35.50, 23.50, 'Low', 'Medium', 'Medium', 3.10, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(48, 2, '2025-04-24', 6.8, 35.00, 23.00, 'Low', 'Medium', 'Medium', 3.00, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(49, 3, '2025-04-18', 7.2, 32.00, 23.00, 'Medium', 'Low', 'High', 5.40, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(50, 3, '2025-04-19', 7.1, 31.50, 23.50, 'Medium', 'Low', 'High', 5.50, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(51, 3, '2025-04-20', 7.3, 31.00, 24.00, 'Medium', 'Low', 'High', 5.40, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(52, 3, '2025-04-21', 7.2, 30.50, 24.50, 'Medium', 'Low', 'High', 5.50, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(53, 3, '2025-04-22', 7.4, 30.00, 25.00, 'Medium', 'Low', 'High', 5.60, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(54, 3, '2025-04-23', 7.3, 29.50, 25.50, 'Medium', 'Low', 'High', 5.50, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47'),
(55, 3, '2025-04-24', 7.2, 29.00, 25.00, 'Medium', 'Low', 'High', 5.40, 'Regular test', '2025-04-24 16:10:47', '2025-04-24 16:10:47');

-- --------------------------------------------------------

--
-- Table structure for table `soil_treatments`
--

CREATE TABLE `soil_treatments` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `application_date` date NOT NULL,
  `treatment_type` varchar(50) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `application_rate` varchar(50) NOT NULL,
  `application_method` varchar(50) DEFAULT NULL,
  `target_ph` varchar(20) DEFAULT NULL,
  `target_nutrient` varchar(100) DEFAULT NULL,
  `cost_per_acre` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `weather_conditions` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `soil_treatments`
--

INSERT INTO `soil_treatments` (`id`, `field_id`, `application_date`, `treatment_type`, `product_name`, `application_rate`, `application_method`, `target_ph`, `target_nutrient`, `cost_per_acre`, `total_cost`, `weather_conditions`, `notes`, `created_at`, `updated_at`) VALUES
(16, 1, '2025-04-01', 'lime', 'AgriLime Plus', '2 tons/acre', 'broadcast', '6.5', 'Calcium', 65.00, 1300.00, 'Sunny, light breeze', 'Applied to correct low pH in eastern section. Follow-up soil test scheduled for May 15.', '2025-04-01 10:23:45', '2025-04-24 05:29:40'),
(17, 2, '2025-04-05', 'compost', 'Premium Compost Mix', '4 yards/acre', 'incorporated', NULL, 'Organic Matter', 120.00, 2400.00, 'Cloudy, mild', 'Tilled into soil at 6-inch depth. Goal is to increase organic matter content before planting.', '2025-04-05 09:15:22', '2025-04-24 05:29:40'),
(18, 3, '2025-03-22', 'gypsum', 'CalSul Gypsum', '1000 lbs/acre', 'broadcast', NULL, 'Calcium, Sulfur', 45.00, 675.00, 'Overcast, light wind', 'Applied to improve soil structure and provide calcium without changing pH.', '2025-03-22 14:30:11', '2025-04-24 05:29:40'),
(19, 1, '2025-03-15', 'sulfur', 'Elemental Sulfur 90%', '400 lbs/acre', 'broadcast', '6.0', 'Sulfur', 35.00, 700.00, 'Clear, no wind', 'Applied to northern section to lower pH for blueberry planting.', '2025-03-15 15:45:33', '2025-04-24 05:29:40');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `staff_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `emergency_contact` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','on-leave') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `staff_id`, `first_name`, `last_name`, `email`, `phone`, `address`, `role_id`, `hire_date`, `emergency_contact`, `notes`, `profile_image`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PF-25-0001', 'Irfan', 'danial', 'tvideos617@gmail.com', '01123456789', 'sddv', 2, '2025-04-20', '011525562644', 'hhyhyh', 'staff_68050747da6a4.png', 'active', '2025-04-20 14:40:07', '2025-04-20 14:40:07'),
(2, 'ADM-MY001', 'Aina', 'Rahim', 'aina.rahim@gmail.com', '+60 13-726 3482', 'No. 12, Jalan Damai, Taman Sri Putra, 81200 Johor Bahru, Johor', 7, '2025-05-06', 'Farah Rahim (+60 19-881 2931)', 'Assigned to manage internal staff documentation and scheduling.', 'staff_681a4a4edeebf.jpeg', 'active', '2025-05-06 09:43:42', '2025-05-06 09:43:42'),
(3, 'ADM-MY002', 'Harith', 'Zulkifli', 'harith.zulkifli@gmail.com', '+60 17-452 7811', 'Blok A-3-15, Residensi Lestari, Bandar Baru Bangi, 43650 Selangor', 7, '2025-04-27', 'Zulkifli Ismail (+60 11-2929 1188)', 'Handles procurement records and administrative correspondence.', 'staff_681a4af963f3f.jpeg', 'on-leave', '2025-05-06 09:46:33', '2025-05-30 16:42:36'),
(4, 'CS-MY001', 'Syafiq', 'Hassan', 'syafiqhassan.my@gmail.com', '+60 11-2837 4421', 'Lot 87, Jalan Kebun, Kampung Padang Lalang, 09600 Lunas, Kedah', 4, '2025-03-06', 'Aiman Hassan (+60 13-778 9203)', 'Expert in rice crop disease identification and sustainable farming techniques.', 'staff_681a4bb88237e.jpg', 'active', '2025-05-06 09:49:44', '2025-05-06 09:49:44'),
(5, 'CS-MY002', 'Nurul', 'Azira', 'nurulazira.agro@gmail.com', '+60 12-332 9112', 'No. 3A, Jalan Sentosa, Taman Desa, 47000 Sungai Buloh, Selangor', 4, '2025-02-08', 'Siti Azura (+60 10-725 4412)', 'Focuses on soil nutrient balancing and organic vegetable farming.', 'staff_681a4c4eeb0f7.jpeg', 'on-leave', '2025-05-06 09:52:14', '2025-05-14 05:48:23'),
(6, 'CS-MY003', 'Faizal', 'Mohd Naim', 'faizalcrops.my@gmail.com', '+60 17-872 1190', 'PT 210, Jalan Sultan, 15000 Kota Bharu, Kelantan', 4, '2025-05-09', 'Naim Omar (+60 16-554 1100)', 'Specializes in tropical fruit pest control and greenhouse systems.', 'staff_681a4ccc97c0e.jpeg', 'active', '2025-05-06 09:54:20', '2025-05-06 09:54:20'),
(7, 'EO-MY001', 'Zulkarnain', 'Bakar', 'zulkarnain.operator@gmail.com', '+60 13-910 2244', 'Lot 19, Jalan Mawar, Kampung Sri Aman, 78000 Alor Gajah, Melaka', 5, '2025-05-04', 'Roslan Bakar (+60 12-887 3311)', 'Operates and maintains tractors and seed drills  paddy fields.', 'staff_681a544e45ee7.jpeg', 'active', '2025-05-06 10:26:22', '2025-05-06 10:26:22'),
(8, 'EO-MY002', 'Hafiza', 'Jamal', 'hafizajamal.equip@gmail.com', '+60 17-675 8833', 'No. 8, Jalan Hijrah, Taman Nusa Bestari, 81300 Skudai, Johor', 5, '2025-05-10', 'Jamaludin Omar (+60 14-278 9341)', 'Skilled in handling combine harvesters and irrigation system machinery.', 'staff_681a54c48874f.jpeg', 'active', '2025-05-06 10:28:20', '2025-05-06 10:28:20'),
(9, ' FM-MY001', 'Khairul', 'Anuar', 'khairulanuar.farm@gmail.com', '+60 12-664 7785', 'Lot 55, Jalan Melati, Felda Palong 7, 73400 Gemas, Negeri Sembilan', 1, '2025-05-24', 'Nabila Anuar (+60 13-988 2302)', 'Oversees farm operations including staff scheduling, crop planning, and machinery logistics. fvff', '', 'active', '2025-05-06 10:31:54', '2025-05-30 16:42:47'),
(10, 'FM-MY002', 'Juliana gdgdd', 'Karyadi', 'Juliana.Karyadi@gmail.com', '+60 12-664 8345', 'Lot 55, Jalan Melati, Felda Palong 16, 73400 Gemas, Negeri Sembilan', 2, '2025-04-27', 'Farah Rahim (+60 19-881 2931)', 'Oversees farm operations including staff scheduling, crop planning, and machinery logistics.', 'staff_681a584b2fbba.jpg', 'active', '2025-05-06 10:43:23', '2025-05-14 05:50:36'),
(11, 'PF-25-0002', 'Dr Sarah', 'Lee', 'sarahlee@gmail.com', '01234567898', 'wira, jalan tun perak', 8, '2025-06-01', '', '', '', 'active', '2025-06-01 20:32:06', '2025-06-01 20:32:06'),
(12, 'PF-25-0003', 'Dr Ahmed', 'Khan', 'ahmedkhan@gmail.com', '014785963', 'kuala lumpur', 8, '2025-06-01', '', '', '', 'active', '2025-06-01 20:33:14', '2025-06-01 20:33:14'),
(13, 'PF-25-0004', 'Dr', 'Fatimah', 'fatimah@gmail.com', '016527895', 'taiping', 8, '2025-06-01', '', '', '', 'active', '2025-06-01 20:33:56', '2025-06-01 20:33:56');

-- --------------------------------------------------------

--
-- Table structure for table `staff_documents`
--

CREATE TABLE `staff_documents` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_title` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_documents`
--

INSERT INTO `staff_documents` (`id`, `staff_id`, `document_type`, `document_title`, `file_path`, `upload_date`, `expiry_date`) VALUES
(1, 6, 'other', 'test upload 2', 'uploads/staff_documents/1746557621_lab4_Firdan_IrfanDanial.pdf', '2025-05-07', '2025-05-07'),
(3, 1, 'id_proof', 'test upload 5', 'uploads/staff_documents/1746638009_13fc14ad-c884-4e48-acec-b3d93746a55c.jpeg', '2025-05-08', '2025-06-07');

-- --------------------------------------------------------

--
-- Table structure for table `staff_field_assignments`
--

CREATE TABLE `staff_field_assignments` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `assigned_date` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_field_assignments`
--

INSERT INTO `staff_field_assignments` (`id`, `staff_id`, `field_id`, `status`, `assigned_date`, `created_at`) VALUES
(1, 2, 1, 'active', '2025-05-23', '2025-05-23 00:11:17'),
(2, 2, 2, 'active', '2025-05-23', '2025-05-23 00:11:17'),
(3, 2, 3, 'active', '2025-05-23', '2025-05-23 00:11:17');

-- --------------------------------------------------------

--
-- Table structure for table `staff_schedule`
--

CREATE TABLE `staff_schedule` (
  `id` int(11) NOT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `title` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_schedule`
--

INSERT INTO `staff_schedule` (`id`, `event_date`, `event_time`, `title`, `location`, `created_at`, `updated_at`) VALUES
(2, '2025-05-20', '15:56:00', 'ss', 'ss', '2025-05-26 04:56:42', '2025-05-26 04:57:09'),
(3, '2025-05-22', '16:56:00', 'ss', 'ss', '2025-05-26 04:57:03', '2025-05-26 04:57:03');

-- --------------------------------------------------------

--
-- Table structure for table `staff_tasks`
--

CREATE TABLE `staff_tasks` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `task_title` varchar(255) NOT NULL,
  `task_description` text DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `assigned_date` date NOT NULL,
  `due_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `estimated_hours` decimal(5,2) DEFAULT NULL,
  `actual_hours` decimal(5,2) DEFAULT NULL,
  `completion_percentage` int(3) DEFAULT 0,
  `is_recurring` tinyint(1) DEFAULT 0,
  `recurrence_pattern` varchar(50) DEFAULT NULL,
  `recurrence_end_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `task_name` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_tasks`
--

INSERT INTO `staff_tasks` (`id`, `staff_id`, `task_title`, `task_description`, `priority`, `status`, `assigned_date`, `due_date`, `completion_date`, `estimated_hours`, `actual_hours`, `completion_percentage`, `is_recurring`, `recurrence_pattern`, `recurrence_end_date`, `created_at`, `last_updated`, `task_name`, `start_date`) VALUES
(1, 8, 'as', 'ew', 'medium', 'in_progress', '2025-05-07', '2025-05-06', NULL, NULL, NULL, 100, 0, NULL, NULL, '2025-05-06 19:15:01', '2025-05-08 08:38:42', 'as', '2025-05-07'),
(4, 4, 'tidur je keje', 'apa ni weh', 'high', 'pending', '2025-05-08', '2025-06-01', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:07:23', '2025-05-08 08:56:57', NULL, '2025-05-08'),
(5, 10, 'basuh berak lembu', 'jangan bagi dia busuk', 'high', 'pending', '2025-05-08', '2025-05-10', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:14:28', '2025-05-08 08:50:13', NULL, '2025-05-08'),
(6, 6, 'basuh berak lembu', '', 'medium', 'pending', '2025-05-08', '2025-05-06', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:26:40', '2025-05-08 08:50:21', NULL, '2025-05-08'),
(7, 7, 'ads', '', 'high', 'cancelled', '2025-05-08', '2025-05-09', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:36:28', '2025-05-08 08:55:59', NULL, '2025-05-08'),
(8, 3, 'asdqw', '', 'low', 'completed', '2025-05-08', '2025-05-09', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:36:42', '2025-05-07 17:39:47', NULL, '2025-05-08'),
(9, 5, 'asdqw', '', 'medium', 'pending', '2025-05-08', '2025-05-31', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:37:00', '2025-05-08 08:50:05', NULL, '2025-05-08'),
(11, 9, 'asdqw', '', 'medium', 'pending', '2025-05-08', '2025-05-12', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:37:33', '2025-05-08 08:40:10', NULL, '2025-05-08'),
(12, 8, 'duduk diam je', '', 'medium', 'pending', '2025-05-08', '2025-05-24', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:38:42', '2025-05-08 08:50:24', NULL, '2025-05-08'),
(13, 5, 'weda', 'fds', 'medium', 'pending', '2025-05-08', '2025-05-07', NULL, NULL, NULL, 0, 0, NULL, NULL, '2025-05-07 17:44:23', '2025-05-07 17:44:23', NULL, '2025-05-08');

-- --------------------------------------------------------

--
-- Table structure for table `stock_requests`
--

CREATE TABLE `stock_requests` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_quantity` decimal(10,2) NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','approved','rejected','fulfilled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `requested_date` datetime NOT NULL,
  `approved_date` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_requests`
--

INSERT INTO `stock_requests` (`id`, `item_id`, `requested_by`, `requested_quantity`, `purpose`, `priority`, `status`, `notes`, `admin_notes`, `requested_date`, `approved_date`, `approved_by`) VALUES
(1, 27, 2, 5.00, 'low stok', 'medium', 'rejected', 'need immediately', 'caanot', '2025-05-26 23:20:34', '2025-05-27 21:48:29', 1),
(2, 27, 2, 3.00, 'ff', 'medium', 'fulfilled', 'ff', 'approved later make delivery | Fulfilled on 2025-05-27 21:48:38', '2025-05-27 21:38:44', '2025-05-27 21:48:07', 1),
(3, 4, 2, 2.00, '3re', 'low', 'rejected', 'rfrefg', '', '2025-05-29 18:11:08', '2025-05-29 18:30:57', 1),
(4, 4, 2, 2.00, '3re', 'low', 'fulfilled', 'rfrefg', ' | Fulfilled on 2025-05-31 00:40:45', '2025-05-29 18:16:08', '2025-05-29 18:31:02', 1),
(5, 4, 2, 2.00, '3re', 'low', 'rejected', 'rfrefg', '', '2025-05-29 18:21:09', '2025-05-29 18:34:34', 1),
(6, 4, 2, 2.00, '3re', 'low', 'fulfilled', 'rfrefg', ' | Fulfilled on 2025-05-31 00:40:43', '2025-05-29 18:26:10', '2025-05-29 18:34:26', 1),
(7, 3, 2, 400.00, 'need to do planting', 'high', 'fulfilled', '', 'will do the delivery soon | Fulfilled on 2025-05-31 00:40:34', '2025-05-31 00:40:02', '2025-05-31 00:40:24', 1),
(8, 3, 2, 400.00, 'need to do planting', 'high', 'pending', '', NULL, '2025-05-31 00:40:28', NULL, NULL),
(9, 3, 2, 400.00, 'need to do planting', 'high', 'pending', '', NULL, '2025-05-31 00:40:36', NULL, NULL),
(10, 3, 2, 400.00, 'need to do planting', 'high', 'fulfilled', '', ' | Fulfilled on 2025-06-03 16:10:40', '2025-05-31 00:40:48', '2025-06-01 22:24:53', 1),
(11, 26, 2, 20.00, 'out of stock', 'medium', 'approved', 'can do delivery on mondsay', 'will do 10kg first', '2025-06-03 16:09:10', '2025-06-03 16:10:28', 1),
(12, 26, 2, 20.00, 'out of stock', 'medium', 'pending', 'can do delivery on mondsay', NULL, '2025-06-03 16:10:32', NULL, NULL),
(13, 26, 2, 20.00, 'out of stock', 'medium', 'pending', 'can do delivery on mondsay', NULL, '2025-06-03 16:10:44', NULL, NULL),
(14, 26, 2, 20.00, 'out of stock', 'medium', 'pending', 'can do delivery on mondsay', NULL, '2025-06-03 16:11:32', NULL, NULL),
(15, 26, 2, 20.00, 'out of stock', 'medium', 'pending', 'can do delivery on mondsay', NULL, '2025-06-03 16:11:49', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_teams`
--

CREATE TABLE `supervisor_teams` (
  `id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `status` varchar(20) DEFAULT 'active',
  `assigned_date` date DEFAULT curdate(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `phone`, `email`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Green Seeds Co.', '012-3456789', 'contact@greenseeds.com', '123 Farmer Road, Agricultural District', 'active', '2025-05-01 09:12:48', NULL),
(2, 'Farm Supply Inc.', '012-9876543', 'sales@farmsupply.com', '456 Harvest Avenue, Rural Zone', 'active', '2025-05-01 09:12:48', NULL),
(3, 'Organic Feeds Ltd.', '011-2345678', 'info@organicfeeds.com', '789 Natural Way, Eco Park', 'active', '2025-05-01 09:12:48', NULL),
(4, 'Tools & Equipment Co.', '013-5678901', 'support@toolsequip.com', '321 Workshop Street, Industrial Area', 'active', '2025-05-01 09:12:48', NULL),
(5, 'Agri Chemicals Sdn Bhd', '014-8765432', 'service@agrichems.com', '654 Laboratory Lane, Science Park', 'active', '2025-05-01 09:12:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `supplier_performance`
--

CREATE TABLE `supplier_performance` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `evaluation_date` date NOT NULL,
  `evaluated_by` int(11) NOT NULL,
  `delivery_timeliness_rating` tinyint(1) DEFAULT NULL COMMENT 'Rating 1-5',
  `product_quality_rating` tinyint(1) DEFAULT NULL COMMENT 'Rating 1-5',
  `communication_rating` tinyint(1) DEFAULT NULL COMMENT 'Rating 1-5',
  `price_value_rating` tinyint(1) DEFAULT NULL COMMENT 'Rating 1-5',
  `overall_rating` tinyint(1) DEFAULT NULL COMMENT 'Rating 1-5',
  `comments` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_name`, `value`, `created_at`, `updated_at`) VALUES
(1, 'sku_prefix', '', '2025-05-01 15:15:28', '2025-05-01 15:15:28'),
(2, 'sku_suffix', '', '2025-05-01 15:15:28', '2025-05-01 15:15:28'),
(3, 'sku_format', 'custom', '2025-05-01 15:15:28', '2025-05-01 15:58:30'),
(5, 'push_notifications_enabled', '1', '2025-05-06 16:41:33', '2025-05-06 16:41:33'),
(6, 'vapid_public_key', '', '2025-05-06 16:41:33', '2025-05-06 16:41:33'),
(7, 'vapid_private_key', '', '2025-05-06 16:41:33', '2025-05-06 16:41:33');

-- --------------------------------------------------------

--
-- Table structure for table `treatments`
--

CREATE TABLE `treatments` (
  `id` int(11) NOT NULL,
  `crop_id` int(11) NOT NULL,
  `issue_id` int(11) DEFAULT NULL,
  `treatment_type` varchar(255) NOT NULL,
  `application_date` date NOT NULL,
  `quantity_used` varchar(100) NOT NULL,
  `application_method` enum('spray','dust','drench','injection','broadcast') NOT NULL,
  `notes` text DEFAULT NULL,
  `applied_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `treatments`
--

INSERT INTO `treatments` (`id`, `crop_id`, `issue_id`, `treatment_type`, `application_date`, `quantity_used`, `application_method`, `notes`, `applied_by`, `created_at`, `updated_at`) VALUES
(1, 5, 3, 'Insecticide', '2025-06-01', '5', 'spray', 'gbg', 2, '2025-06-01 17:37:50', '2025-06-01 17:37:50'),
(2, 5, 3, 'Insecticide', '2025-06-01', 'g', 'spray', 'gggg', 2, '2025-06-01 17:38:08', '2025-06-01 17:38:08'),
(3, 5, 3, 'Insecticide', '2025-06-01', '5', 'spray', 'vgg', 2, '2025-06-01 17:39:17', '2025-06-01 17:39:17'),
(4, 5, 3, 'Insecticide', '2025-06-01', '5', 'spray', 'f', 2, '2025-06-01 17:47:24', '2025-06-01 17:47:24'),
(5, 5, 3, 'Insecticide', '2025-06-01', '5', 'spray', 'bb', 2, '2025-06-01 18:08:37', '2025-06-01 18:08:37'),
(6, 5, 3, 'Insecticide', '2025-06-01', '4', 'spray', 'rr', 2, '2025-06-01 18:23:00', '2025-06-01 18:23:00'),
(7, 5, 3, 'Insecticide', '2025-06-01', '4', 'spray', 'd', 2, '2025-06-01 18:34:38', '2025-06-01 18:34:38'),
(8, 1, 2, 'Fungicide', '2025-06-03', '50', 'spray', 'donr', 2, '2025-06-03 07:50:38', '2025-06-03 07:50:38');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$ACWogDLrPCnngNLLJqUh.eVOweamCDjw0Fdvo1BlmuBI3JgT820ZW', 'admin', '2025-01-12 14:36:22'),
(2, 'supervisor', '$2y$10$6ZT0t1OEzNAbw6CIkqgJluNSoq2Q1WG3vVzmEcq7qNquDRzhC6gii', 'supervisor', '2025-01-12 14:36:22');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `theme_preference` enum('light','dark') NOT NULL DEFAULT 'light',
  `email_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `sms_notifications` tinyint(1) NOT NULL DEFAULT 0,
  `language` enum('english','spanish','french','malay') NOT NULL DEFAULT 'english',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_id`, `theme_preference`, `email_notifications`, `sms_notifications`, `language`, `created_at`, `updated_at`) VALUES
(1, 1, 'light', 1, 0, 'english', '2025-04-30 04:24:05', '2025-04-30 04:24:05');

-- --------------------------------------------------------

--
-- Table structure for table `vaccinations`
--

CREATE TABLE `vaccinations` (
  `id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `type` varchar(100) NOT NULL,
  `next_due` date NOT NULL,
  `administered_by` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccinations`
--

INSERT INTO `vaccinations` (`id`, `animal_id`, `date`, `type`, `next_due`, `administered_by`) VALUES
(3, 10, '2025-01-03', 'Foot-and-mouth disease vaccine', '2025-01-16', 'Dr. Sarah Lee'),
(4, 11, '2025-01-05', 'Clostridial vaccine', '2025-03-01', 'Dr. Ahmad Kamal'),
(5, 10, '2025-01-16', 'Rabies vaccine', '2026-01-06', 'Dr. Emily Tan'),
(9, 10, '2025-01-20', ' Rabies vaccine', '2025-01-31', 'Dr. Ahmad Kamal'),
(11, 15, '2023-02-20', 'Foot and Mouth', '2024-02-20', 'Dr. Sarah'),
(12, 16, '2023-01-15', 'Brucellosis', '2024-01-15', 'Dr. Ahmad'),
(13, 17, '2023-05-15', 'Tetanus', '2024-05-15', 'Dr. Lee'),
(14, 18, '2023-08-05', 'Newcastle Disease', '2024-02-05', 'Farm Technician'),
(15, 20, '2023-09-15', 'Myxomatosis', '2024-03-15', 'Dr. Ahmad'),
(16, 23, '2025-05-30', 'FMP', '2025-06-15', 'Dr.Sarah'),
(17, 24, '2025-06-03', 'Foot-and-mouth disease vaccine', '2025-06-19', 'Dr.Sarah Lee');

--
-- Triggers `vaccinations`
--
DELIMITER $$
CREATE TRIGGER `update_animal_vaccination_status` AFTER INSERT ON `vaccinations` FOR EACH ROW BEGIN
    UPDATE animals 
    SET vaccination_status = CASE 
        WHEN NEW.next_due > CURRENT_DATE() THEN 'vaccinated'
        WHEN NEW.next_due <= CURRENT_DATE() THEN 'overdue'
        ELSE 'partially_vaccinated'
    END,
    last_vaccination_date = NEW.date
    WHERE id = NEW.animal_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_animal_vaccination_status_update` AFTER UPDATE ON `vaccinations` FOR EACH ROW BEGIN
    UPDATE animals 
    SET vaccination_status = CASE 
        WHEN NEW.next_due > CURRENT_DATE() THEN 'vaccinated'
        WHEN NEW.next_due <= CURRENT_DATE() THEN 'overdue'
        ELSE 'partially_vaccinated'
    END,
    last_vaccination_date = NEW.date
    WHERE id = NEW.animal_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `waste_analytics_view`
-- (See below for the actual view)
--
CREATE TABLE `waste_analytics_view` (
`waste_type` enum('expired','damaged','spoiled','lost','other')
,`category_name` varchar(100)
,`month` int(2)
,`year` int(4)
,`record_count` bigint(21)
,`total_quantity` decimal(32,2)
,`estimated_loss_value` decimal(42,4)
);

-- --------------------------------------------------------

--
-- Table structure for table `waste_management`
--

CREATE TABLE `waste_management` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `waste_type` enum('expired','damaged','spoiled','lost','other') NOT NULL,
  `reason` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `waste_management`
--

INSERT INTO `waste_management` (`id`, `item_id`, `quantity`, `waste_type`, `reason`, `date`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 12, 2.00, 'expired', 'Pesticide expired before use', '2025-01-10', 'Found during monthly inventory check', 1, '2025-05-06 14:26:50', NULL),
(2, 1, 5.00, 'expired', 'Pesticide expired before use', '2025-01-10', 'Found during monthly inventory check', 1, '2025-05-06 14:27:15', NULL),
(3, 3, 2.50, 'expired', 'Seeds past germination date', '2025-01-15', 'Storage conditions may have been inadequate', 1, '2025-05-06 14:27:15', NULL),
(4, 5, 10.00, 'expired', 'Fertilizer hardened due to moisture', '2024-12-20', 'Packaging was damaged', 2, '2025-05-06 14:27:15', NULL),
(5, 2, 3.00, 'expired', 'Animal supplements past use-by date', '2025-01-05', 'Ordering replacement immediately', 1, '2025-05-06 14:27:15', NULL),
(6, 4, 6.00, 'expired', 'Organic feed spoiled', '2024-12-28', 'Check storage temperature', 2, '2025-05-06 14:27:15', NULL),
(7, 6, 1.00, 'damaged', 'Equipment broken during transport', '2025-01-08', 'Contact supplier for warranty claim', 1, '2025-05-06 14:27:15', NULL),
(8, 8, 4.00, 'damaged', 'Seed bags torn by rodents', '2025-01-03', 'Improve storage to prevent pests', 2, '2025-05-06 14:27:15', NULL),
(9, 10, 7.50, 'damaged', 'Rain damage to stored fertilizer', '2024-12-15', 'Warehouse roof leak', 1, '2025-05-06 14:27:15', NULL),
(10, 7, 2.00, 'damaged', 'Plastic containers cracked', '2025-01-12', 'Extreme temperature changes', 2, '2025-05-06 14:27:15', NULL),
(11, 9, 3.50, 'damaged', 'Equipment parts rusted', '2024-12-22', 'Improper storage in humid area', 1, '2025-05-06 14:27:15', NULL),
(12, 11, 15.00, 'spoiled', 'Corn silage fermentation failed', '2025-01-02', 'pH levels incorrect', 1, '2025-05-06 14:27:15', NULL),
(13, 13, 20.00, 'spoiled', 'Harvested produce spoiled before processing', '2024-12-30', 'Refrigeration unit failure', 2, '2025-05-06 14:27:15', NULL),
(14, 15, 8.00, 'spoiled', 'Milk spoiled due to power outage', '2025-01-07', 'Generator failed to start', 1, '2025-05-06 14:27:15', NULL),
(15, 12, 12.00, 'spoiled', 'Feed mixed with contaminated water', '2024-12-18', 'Water supply issue', 2, '2025-05-06 14:27:15', NULL),
(16, 14, 5.50, 'spoiled', 'Grain moldy from moisture', '2025-01-09', 'Storage bin leak', 1, '2025-05-06 14:27:15', NULL),
(17, 16, 1.00, 'lost', 'Equipment misplaced during field work', '2025-01-04', 'Search conducted', 1, '2025-05-06 14:27:15', NULL),
(18, 18, 2.00, 'lost', 'Tools missing after contractor visit', '2024-12-25', 'Follow up with service company', 2, '2025-05-06 14:27:15', NULL),
(19, 20, 0.50, 'lost', 'Small equipment lost during transport', '2025-01-11', 'Review transportation procedures', 1, '2025-05-06 14:27:15', NULL),
(20, 17, 1.50, 'lost', 'Measuring tools missing from storage', '2024-12-27', 'Possible inventory error', 2, '2025-05-06 14:27:15', NULL),
(21, 19, 0.75, 'lost', 'Portable devices not returned from field', '2025-01-06', 'Staff training needed on equipment tracking', 1, '2025-05-06 14:27:15', NULL),
(22, 21, 4.00, 'other', 'Items donated to agricultural school', '2025-01-13', 'Educational partnership', 1, '2025-05-06 14:27:15', NULL),
(23, 23, 3.25, 'other', 'Equipment retired from service', '2024-12-19', 'Obsolete technology', 2, '2025-05-06 14:27:15', NULL),
(24, 25, 5.00, 'other', 'Production surplus distributed to staff', '2025-01-01', 'Employee benefit program', 1, '2025-05-06 14:27:15', NULL),
(25, 22, 2.75, 'other', 'Test materials consumed in quality control', '2024-12-23', 'Regular testing protocol', 2, '2025-05-06 14:27:15', NULL),
(26, 24, 1.25, 'other', 'Sample products used in demonstration', '2025-01-14', 'Agricultural fair participation', 1, '2025-05-06 14:27:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `weather_data`
--

CREATE TABLE `weather_data` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `recorded_at` datetime NOT NULL,
  `temperature` float NOT NULL,
  `humidity` float NOT NULL,
  `pressure` float DEFAULT NULL,
  `wind_speed` float DEFAULT NULL,
  `wind_direction` int(11) DEFAULT NULL,
  `precipitation` float DEFAULT NULL,
  `conditions` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weather_data`
--

INSERT INTO `weather_data` (`id`, `field_id`, `recorded_at`, `temperature`, `humidity`, `pressure`, `wind_speed`, `wind_direction`, `precipitation`, `conditions`, `created_at`, `updated_at`) VALUES
(1, 3, '2025-04-20 07:50:00', 30, 46, 1005.2, 17.7, 327, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(2, 1, '2025-04-20 07:50:00', 30.1, 74.1, 1000.1, 23.3, 111, 4.6, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(3, 2, '2025-04-20 07:50:00', 25.8, 85, 1017.3, 4.9, 107, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(4, 3, '2025-04-19 07:50:00', 18.7, 47.4, 1026.1, 26.1, 156, 8.8, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(5, 1, '2025-04-19 07:50:00', 26, 70.6, 1017.5, 27.6, 53, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(6, 2, '2025-04-19 07:50:00', 15.6, 75.8, 1016, 30.9, 152, 3.9, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(7, 3, '2025-04-18 07:50:00', 21.5, 64.9, 1007.4, 29.6, 34, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(8, 1, '2025-04-18 07:50:00', 23.3, 54, 1009.4, 24.8, 335, 0, 'Partly Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(9, 2, '2025-04-18 07:50:00', 27.3, 68.9, 1018, 18.1, 80, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(10, 3, '2025-04-17 07:50:00', 19, 75.5, 1008.1, 1.2, 59, 13.8, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(11, 1, '2025-04-17 07:50:00', 28.5, 53.9, 1008.6, 27.4, 212, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(12, 2, '2025-04-17 07:50:00', 30.9, 61.9, 1007, 18.1, 9, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(13, 3, '2025-04-16 07:50:00', 26.7, 75.1, 1031, 2.4, 239, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(14, 1, '2025-04-16 07:50:00', 24.6, 47.2, 1000.6, 11.1, 345, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(15, 2, '2025-04-16 07:50:00', 29.8, 43.1, 1022.5, 30.4, 33, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(16, 3, '2025-04-15 07:50:00', 26, 44.3, 1007.4, 4.2, 54, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(17, 1, '2025-04-15 07:50:00', 20.9, 49, 1030.6, 7.8, 289, 12.1, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(18, 2, '2025-04-15 07:50:00', 25.1, 66.2, 1018.3, 16.7, 186, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(19, 3, '2025-04-14 07:50:00', 21.4, 46.5, 1016.3, 17, 61, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(20, 1, '2025-04-14 07:50:00', 20, 87.7, 1013.2, 14.8, 219, 0.7, 'Partly Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(21, 2, '2025-04-14 07:50:00', 27.6, 69.9, 1028.1, 7.7, 119, 0.9, 'Partly Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(22, 3, '2025-04-13 07:50:00', 29.4, 77.6, 1014.7, 16.6, 189, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(23, 1, '2025-04-13 07:50:00', 24.1, 90, 1006.6, 18, 307, 4.4, 'Partly Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(24, 2, '2025-04-13 07:50:00', 23.6, 88.8, 1021.3, 14, 48, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(25, 3, '2025-04-12 07:50:00', 16.7, 75.5, 1017.5, 2.6, 50, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(26, 1, '2025-04-12 07:50:00', 26.6, 44.5, 1024.6, 19.2, 341, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(27, 2, '2025-04-12 07:50:00', 24.2, 52, 1008, 6.2, 169, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(28, 3, '2025-04-11 07:50:00', 29.2, 44.3, 1017, 13, 32, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(29, 1, '2025-04-11 07:50:00', 15.4, 44.9, 1014, 1.3, 290, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(30, 2, '2025-04-11 07:50:00', 23, 84.6, 1001, 24, 114, 1.5, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(31, 3, '2025-04-10 07:50:00', 19, 44.6, 1010, 3, 18, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(32, 1, '2025-04-10 07:50:00', 15.4, 49.8, 1028.3, 8.3, 226, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(33, 2, '2025-04-10 07:50:00', 20, 78.9, 1020.2, 5.4, 63, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(34, 3, '2025-04-09 07:50:00', 27.3, 66, 1023.9, 25.8, 270, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(35, 1, '2025-04-09 07:50:00', 17.4, 58.6, 1012.9, 27.5, 346, 1, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(36, 2, '2025-04-09 07:50:00', 28, 70.2, 1027, 13, 112, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(37, 3, '2025-04-08 07:50:00', 28.6, 84, 1023.5, 23.6, 347, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(38, 1, '2025-04-08 07:50:00', 29.2, 73.4, 1025.5, 30.8, 95, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(39, 2, '2025-04-08 07:50:00', 20, 42.1, 1030.1, 20.2, 185, 0, 'Partly Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(40, 3, '2025-04-07 07:50:00', 18.2, 51.4, 1019.3, 7.7, 49, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(41, 1, '2025-04-07 07:50:00', 21.2, 90.7, 1013, 9.3, 339, 7.9, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(42, 2, '2025-04-07 07:50:00', 21.2, 63.5, 1019.2, 12.5, 266, 0, 'Partly Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(43, 3, '2025-04-06 07:50:00', 29.5, 83.1, 1015.6, 9.2, 115, 17.3, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(44, 1, '2025-04-06 07:50:00', 30.6, 57.2, 1012, 1.5, 188, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(45, 2, '2025-04-06 07:50:00', 28.2, 66, 1002.2, 17, 284, 9.4, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(46, 3, '2025-04-05 07:50:00', 29.4, 61.1, 1009.4, 10, 40, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(47, 1, '2025-04-05 07:50:00', 16, 43.1, 1002, 6.4, 66, 14.9, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(48, 2, '2025-04-05 07:50:00', 25.6, 65.9, 1029.8, 28.7, 101, 5.3, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(49, 3, '2025-04-04 07:50:00', 28, 61.6, 1020.8, 5, 148, 6, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(50, 1, '2025-04-04 07:50:00', 29, 52.6, 1003.6, 11.9, 73, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(51, 2, '2025-04-04 07:50:00', 17.7, 43.1, 1019.6, 1, 7, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(52, 3, '2025-04-03 07:50:00', 21, 75.8, 1008, 13.6, 161, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(53, 1, '2025-04-03 07:50:00', 20.4, 78, 1007, 17.6, 261, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(54, 2, '2025-04-03 07:50:00', 23.3, 69, 1023.5, 0.3, 304, 15.1, 'Partly Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(55, 3, '2025-04-02 07:50:00', 17.2, 68.4, 1027, 20.2, 92, 6.3, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(56, 1, '2025-04-02 07:50:00', 20.4, 66.7, 1004.7, 2, 22, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(57, 2, '2025-04-02 07:50:00', 16, 44.2, 1028.9, 24.4, 358, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(58, 3, '2025-04-01 07:50:00', 23.8, 48.7, 1019.5, 4, 261, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(59, 1, '2025-04-01 07:50:00', 22.2, 71.1, 1008, 14.9, 311, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(60, 2, '2025-04-01 07:50:00', 22, 81.5, 1024, 6.9, 297, 0, 'Partly Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(61, 3, '2025-03-31 07:50:00', 16.5, 42.1, 1015.2, 6.3, 64, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(62, 1, '2025-03-31 07:50:00', 21, 53, 1030.5, 10.5, 356, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(63, 2, '2025-03-31 07:50:00', 20, 52.7, 1019, 12.8, 137, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(64, 3, '2025-03-30 07:50:00', 21.8, 74, 1004, 5, 265, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(65, 1, '2025-03-30 07:50:00', 27.2, 58.7, 1001.6, 20.3, 190, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(66, 2, '2025-03-30 07:50:00', 25, 72.1, 1019.8, 5.1, 320, 12, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(67, 3, '2025-03-29 07:50:00', 28.3, 48, 1008.1, 10.8, 289, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(68, 1, '2025-03-29 07:50:00', 28.3, 40.6, 1007, 25.9, 8, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(69, 2, '2025-03-29 07:50:00', 20.3, 49.2, 1009.2, 18.4, 70, 0, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(70, 3, '2025-03-28 07:50:00', 22, 63.3, 1009.1, 13.7, 295, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(71, 1, '2025-03-28 07:50:00', 22.1, 64.1, 1029.2, 16.5, 326, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(72, 2, '2025-03-28 07:50:00', 25.8, 57.6, 1011.1, 25.8, 192, 1.6, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(73, 3, '2025-03-27 07:50:00', 28.3, 59.3, 1013.7, 5.6, 217, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(74, 1, '2025-03-27 07:50:00', 30.9, 86.3, 1022.3, 20, 229, 17, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(75, 2, '2025-03-27 07:50:00', 25.4, 67.1, 1028, 19, 256, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(76, 3, '2025-03-26 07:50:00', 21.9, 64, 1019.5, 4.8, 33, 8.5, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(77, 1, '2025-03-26 07:50:00', 18.6, 74.6, 1029.6, 18.7, 85, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(78, 2, '2025-03-26 07:50:00', 19.3, 43.6, 1026.9, 11.8, 31, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(79, 3, '2025-03-25 07:50:00', 19.1, 53.7, 1020.2, 5.1, 155, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(80, 1, '2025-03-25 07:50:00', 29, 82.3, 1002, 5.8, 241, 0.4, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(81, 2, '2025-03-25 07:50:00', 18.2, 84.7, 1006, 14.4, 248, 6.8, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(82, 3, '2025-03-24 07:50:00', 21.7, 42.5, 1002.2, 20.9, 104, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(83, 1, '2025-03-24 07:50:00', 18.6, 72.5, 1017.9, 14.9, 267, 0, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(84, 2, '2025-03-24 07:50:00', 27.1, 69, 1018.2, 17.2, 184, 15.3, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(85, 3, '2025-03-23 07:50:00', 21.7, 76.3, 1024.8, 29.2, 85, 0, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(86, 1, '2025-03-23 07:50:00', 29.9, 48.8, 1026.5, 12.7, 11, 7.7, 'Thunderstorm', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(87, 2, '2025-03-23 07:50:00', 22.3, 54.9, 1015.1, 9.3, 40, 1, 'Clear', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(88, 3, '2025-03-22 07:50:00', 18, 60.4, 1019.9, 29.7, 4, 0, 'Rain', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(89, 1, '2025-03-22 07:50:00', 29, 79.3, 1026.8, 7, 27, 15.5, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00'),
(90, 2, '2025-03-22 07:50:00', 20.7, 44.7, 1026.2, 24.3, 208, 17.2, 'Cloudy', '2025-04-20 13:50:00', '2025-04-20 13:50:00');

-- --------------------------------------------------------

--
-- Table structure for table `weather_forecast`
--

CREATE TABLE `weather_forecast` (
  `id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `forecast_date` date NOT NULL,
  `temperature_high` float NOT NULL,
  `temperature_low` float NOT NULL,
  `humidity` float NOT NULL,
  `precipitation_chance` float NOT NULL,
  `precipitation_amount` float DEFAULT NULL,
  `wind_speed` float DEFAULT NULL,
  `wind_direction` int(11) DEFAULT NULL,
  `conditions` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weather_forecast`
--

INSERT INTO `weather_forecast` (`id`, `field_id`, `forecast_date`, `temperature_high`, `temperature_low`, `humidity`, `precipitation_chance`, `precipitation_amount`, `wind_speed`, `wind_direction`, `conditions`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, '2025-04-21', 24.1694, 11.1694, 78, 3, 0, 27, 30, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(2, 1, '2025-04-21', 26.1694, 12.1694, 82, 99, 2.1, 18, 78, 'Rain', 'Possible thunderstorms in the afternoon.', '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(3, 2, '2025-04-21', 24.1694, 15.1694, 69, 22, 0, 19, 358, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(4, 3, '2025-04-22', 31.9092, 22.9092, 84, 19, 0, 38, 280, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(5, 1, '2025-04-22', 28.9092, 17.9092, 55, 51, 0, 32, 197, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(6, 2, '2025-04-22', 30.9092, 25.9092, 83, 38, 0, 10, 350, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(7, 3, '2025-04-23', 34.8746, 23.8746, 76, 4, 0, 29, 31, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(8, 1, '2025-04-23', 30.8746, 21.8746, 82, 65, 2.8, 28, 143, 'Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(9, 2, '2025-04-23', 23.8746, 17.8746, 61, 78, 0.8, 27, 135, 'Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(10, 3, '2025-04-24', 30.8746, 17.8746, 49, 88, 2.9, 29, 251, 'Rain', 'Possible thunderstorms in the afternoon.', '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(11, 1, '2025-04-24', 27.8746, 17.8746, 47, 92, 0.4, 19, 299, 'Rain', 'Possible thunderstorms in the afternoon.', '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(12, 2, '2025-04-24', 33.8746, 23.8746, 58, 27, 0, 14, 69, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(13, 3, '2025-04-25', 25.9092, 14.9092, 90, 50, 0, 32, 240, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(14, 1, '2025-04-25', 25.9092, 17.9092, 50, 99, 0.9, 13, 130, 'Rain', 'Possible thunderstorms in the afternoon.', '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(15, 2, '2025-04-25', 26.9092, 17.9092, 63, 14, 0, 35, 25, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(16, 3, '2025-04-26', 25.1694, 16.1694, 47, 70, 1.8, 1, 222, 'Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(17, 1, '2025-04-26', 28.1694, 18.1694, 58, 66, 0.8, 33, 157, 'Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(18, 2, '2025-04-26', 28.1694, 20.1694, 80, 58, 0, 29, 335, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(19, 3, '2025-04-27', 23, 16, 85, 85, 2.9, 38, 199, 'Rain', 'Possible thunderstorms in the afternoon.', '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(20, 1, '2025-04-27', 20, 8, 58, 59, 0, 13, 226, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(21, 2, '2025-04-27', 28, 20, 58, 78, 3, 16, 127, 'Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(22, 3, '2025-04-28', 25.8306, 15.8306, 53, 14, 0, 20, 20, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(23, 1, '2025-04-28', 24.8306, 15.8306, 56, 2, 0, 37, 134, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(24, 2, '2025-04-28', 19.8306, 9.83058, 42, 83, 1.3, 35, 304, 'Rain', 'Possible thunderstorms in the afternoon.', '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(25, 3, '2025-04-29', 17.0908, 3.09084, 56, 48, 0, 5, 165, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(26, 1, '2025-04-29', 17.0908, 7.09084, 73, 11, 0, 34, 118, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(27, 2, '2025-04-29', 19.0908, 10.0908, 47, 22, 0, 37, 216, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(28, 3, '2025-04-30', 24.1254, 16.1254, 80, 32, 0, 18, 246, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(29, 1, '2025-04-30', 24.1254, 11.1254, 85, 29, 0, 27, 236, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(30, 2, '2025-04-30', 18.1254, 11.1254, 43, 38, 0, 9, 171, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(31, 3, '2025-05-01', 20.1254, 12.1254, 60, 26, 0, 20, 80, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(32, 1, '2025-05-01', 13.1254, 6.12536, 71, 82, 2.1, 12, 28, 'Rain', 'Possible thunderstorms in the afternoon.', '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(33, 2, '2025-05-01', 18.1254, 7.12536, 83, 26, 0, 15, 265, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(34, 3, '2025-05-02', 21.0908, 12.0908, 70, 42, 0, 30, 215, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(35, 1, '2025-05-02', 15.0908, 6.09084, 40, 9, 0, 15, 63, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(36, 2, '2025-05-02', 18.0908, 8.09084, 74, 93, 3, 40, 272, 'Rain', 'Possible thunderstorms in the afternoon.', '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(37, 3, '2025-05-03', 25.8306, 15.8306, 43, 36, 0, 12, 236, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(38, 1, '2025-05-03', 23.8306, 18.8306, 56, 47, 0, 27, 29, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(39, 2, '2025-05-03', 19.8306, 6.83058, 54, 75, 2.4, 18, 146, 'Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(40, 3, '2025-05-04', 22, 11, 57, 70, 2.2, 31, 316, 'Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(41, 1, '2025-05-04', 26, 17, 82, 0, 0, 25, 113, 'Clear', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30'),
(42, 2, '2025-05-04', 23, 12, 87, 57, 0, 36, 265, 'Partly Cloudy', NULL, '2025-04-20 14:31:30', '2025-04-20 14:31:30');

-- --------------------------------------------------------

--
-- Table structure for table `weather_settings`
--

CREATE TABLE `weather_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weather_settings`
--

INSERT INTO `weather_settings` (`id`, `setting_name`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'default_location', '2', '2025-04-18 05:34:33', '2025-04-18 05:50:20'),
(2, 'temperature_unit', 'celsius', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(3, 'auto_refresh', '30', '2025-04-18 05:34:33', '2025-04-18 05:50:20'),
(4, 'weather_source', 'api', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(5, 'temperature_high', '35', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(6, 'temperature_low', '10', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(7, 'humidity_high', '90', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(8, 'humidity_low', '30', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(9, 'wind_speed_high', '50', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(10, 'precipitation_high', '50', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(11, 'alert_temperature', '1', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(12, 'alert_humidity', '1', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(13, 'alert_precipitation', '1', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(14, 'alert_wind', '1', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(15, 'notification_email', 'safirahtajul@gmail.com', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(16, 'notification_sms', '601163972186', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(17, 'notify_system', '1', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(18, 'notify_forecast_alerts', '1', '2025-04-18 05:34:33', '2025-04-18 05:34:33'),
(19, 'notification_time', '13:51', '2025-04-18 05:34:33', '2025-04-18 05:50:20');

-- --------------------------------------------------------

--
-- Table structure for table `web_push_subscriptions`
--

CREATE TABLE `web_push_subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `endpoint` varchar(512) NOT NULL,
  `p256dh` varchar(256) NOT NULL,
  `auth` varchar(128) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_push` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `weight_records`
--

CREATE TABLE `weight_records` (
  `id` int(11) NOT NULL,
  `animal_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `weight` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weight_records`
--

INSERT INTO `weight_records` (`id`, `animal_id`, `date`, `weight`, `notes`) VALUES
(1, 10, '2025-01-03', 450.00, 'The animal has a steady weight gain trajectory.'),
(2, 11, '2025-01-05', 50.00, 'Appropriate for age and breed.'),
(4, 10, '2025-01-06', 455.00, 'The animal continues to gain weight steadily, indicating good health.'),
(8, 10, '2025-01-20', 500.00, 'overall is okay'),
(11, 15, '2023-02-15', 400.00, 'Initial weight at purchase'),
(12, 15, '2023-05-15', 450.30, 'Weight gain on schedule'),
(13, 16, '2022-11-20', 380.50, 'Birth weight'),
(14, 16, '2023-05-20', 520.75, 'Healthy weight gain'),
(15, 17, '2023-05-10', 35.20, 'Initial weight'),
(16, 17, '2023-08-10', 42.50, 'Good growth rate'),
(17, 19, '2022-06-15', 650.00, 'Initial weight at donation'),
(18, 18, '2023-08-01', 1.85, 'Healthy adult hen'),
(19, 20, '2023-09-10', 4.30, 'Young adult weight'),
(20, 21, '2023-07-22', 2.10, 'Initial weight'),
(21, 22, '2023-02-18', 30.80, 'Slightly underweight at arrival'),
(22, 19, '2025-02-13', 620.00, 'lost some weight'),
(23, 23, '2025-05-30', 485.00, 'Health weight for age, good muscle development'),
(24, 24, '2025-06-03', 495.00, 'Weight is overall good');

-- --------------------------------------------------------

--
-- Structure for view `waste_analytics_view`
--
DROP TABLE IF EXISTS `waste_analytics_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `waste_analytics_view`  AS SELECT `w`.`waste_type` AS `waste_type`, `c`.`name` AS `category_name`, month(`w`.`date`) AS `month`, year(`w`.`date`) AS `year`, count(`w`.`id`) AS `record_count`, sum(`w`.`quantity`) AS `total_quantity`, sum(`w`.`quantity` * `i`.`unit_cost`) AS `estimated_loss_value` FROM ((`waste_management` `w` join `inventory_items` `i` on(`w`.`item_id` = `i`.`id`)) join `item_categories` `c` on(`i`.`category_id` = `c`.`id`)) GROUP BY `w`.`waste_type`, `c`.`name`, month(`w`.`date`), year(`w`.`date`) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `entity_type_entity_id` (`entity_type`,`entity_id`);

--
-- Indexes for table `animals`
--
ALTER TABLE `animals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `animal_id` (`animal_id`);

--
-- Indexes for table `batch_quality_checks`
--
ALTER TABLE `batch_quality_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `batch_usage`
--
ALTER TABLE `batch_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `used_by` (`used_by`);

--
-- Indexes for table `breeding_history`
--
ALTER TABLE `breeding_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `animal_id` (`animal_id`),
  ADD KEY `partner_id` (`partner_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crops`
--
ALTER TABLE `crops`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `crop_activities`
--
ALTER TABLE `crop_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `crop_expenses`
--
ALTER TABLE `crop_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `crop_issues`
--
ALTER TABLE `crop_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `crop_revenue`
--
ALTER TABLE `crop_revenue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `deceased_animals`
--
ALTER TABLE `deceased_animals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_deceased_animal` (`animal_id`);

--
-- Indexes for table `delivery_issues`
--
ALTER TABLE `delivery_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `reported_by` (`reported_by`);

--
-- Indexes for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`);

--
-- Indexes for table `environmental_issues`
--
ALTER TABLE `environmental_issues`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `environmental_readings`
--
ALTER TABLE `environmental_readings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment_checkups`
--
ALTER TABLE `equipment_checkups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `feeding_schedules`
--
ALTER TABLE `feeding_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fertilizer_logs`
--
ALTER TABLE `fertilizer_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `fertilizer_schedules`
--
ALTER TABLE `fertilizer_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`),
  ADD KEY `fertilizer_type_id` (`fertilizer_type_id`);

--
-- Indexes for table `fertilizer_types`
--
ALTER TABLE `fertilizer_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fields`
--
ALTER TABLE `fields`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `field_observations`
--
ALTER TABLE `field_observations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `financial_data`
--
ALTER TABLE `financial_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `transaction_date` (`transaction_date`);

--
-- Indexes for table `get_field_size.php`
--
ALTER TABLE `get_field_size.php`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `harvests`
--
ALTER TABLE `harvests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `harvest_assignments`
--
ALTER TABLE `harvest_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`),
  ADD KEY `equipment_id` (`equipment_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `harvest_records`
--
ALTER TABLE `harvest_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_harvest_records_crop_id` (`crop_id`);

--
-- Indexes for table `harvest_resources`
--
ALTER TABLE `harvest_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `harvest_id` (`harvest_id`);

--
-- Indexes for table `harvest_schedule`
--
ALTER TABLE `harvest_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `health_records`
--
ALTER TABLE `health_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `animal_id` (`animal_id`);

--
-- Indexes for table `housing_assignments`
--
ALTER TABLE `housing_assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_inventory_log_item_id` (`item_id`),
  ADD KEY `idx_inventory_log_action_type` (`action_type`),
  ADD KEY `idx_inventory_log_created_at` (`created_at`);

--
-- Indexes for table `inventory_usage`
--
ALTER TABLE `inventory_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `irrigation_logs`
--
ALTER TABLE `irrigation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `irrigation_schedules`
--
ALTER TABLE `irrigation_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `item_categories`
--
ALTER TABLE `item_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `moisture_thresholds`
--
ALTER TABLE `moisture_thresholds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pesticide_applications`
--
ALTER TABLE `pesticide_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`),
  ADD KEY `pesticide_type_id` (`pesticide_type_id`);

--
-- Indexes for table `pesticide_types`
--
ALTER TABLE `pesticide_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plantings`
--
ALTER TABLE `plantings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_id` (`purchase_id`),
  ADD KEY `inventory_item_id` (`inventory_item_id`);

--
-- Indexes for table `purefarm_growth_milestones`
--
ALTER TABLE `purefarm_growth_milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `soil_moisture`
--
ALTER TABLE `soil_moisture`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `soil_nutrients`
--
ALTER TABLE `soil_nutrients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`),
  ADD KEY `test_date` (`test_date`);

--
-- Indexes for table `soil_tests`
--
ALTER TABLE `soil_tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`);

--
-- Indexes for table `soil_treatments`
--
ALTER TABLE `soil_treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`),
  ADD KEY `application_date` (`application_date`),
  ADD KEY `treatment_type` (`treatment_type`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `staff_field_assignments`
--
ALTER TABLE `staff_field_assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_schedule`
--
ALTER TABLE `staff_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `status_idx` (`status`),
  ADD KEY `due_date_idx` (`due_date`);

--
-- Indexes for table `stock_requests`
--
ALTER TABLE `stock_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `requested_by` (`requested_by`);

--
-- Indexes for table `supervisor_teams`
--
ALTER TABLE `supervisor_teams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supplier_performance`
--
ALTER TABLE `supplier_performance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `evaluated_by` (`evaluated_by`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `treatments`
--
ALTER TABLE `treatments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `crop_id` (`crop_id`),
  ADD KEY `issue_id` (`issue_id`),
  ADD KEY `applied_by` (`applied_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `vaccinations`
--
ALTER TABLE `vaccinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `animal_id` (`animal_id`);

--
-- Indexes for table `waste_management`
--
ALTER TABLE `waste_management`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_waste_date` (`date`),
  ADD KEY `idx_waste_type` (`waste_type`),
  ADD KEY `idx_waste_item` (`item_id`);

--
-- Indexes for table `weather_data`
--
ALTER TABLE `weather_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`),
  ADD KEY `recorded_at` (`recorded_at`);

--
-- Indexes for table `weather_forecast`
--
ALTER TABLE `weather_forecast`
  ADD PRIMARY KEY (`id`),
  ADD KEY `field_id` (`field_id`),
  ADD KEY `forecast_date` (`forecast_date`);

--
-- Indexes for table `weather_settings`
--
ALTER TABLE `weather_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `web_push_subscriptions`
--
ALTER TABLE `web_push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `weight_records`
--
ALTER TABLE `weight_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_weight_animal_id` (`animal_id`),
  ADD KEY `idx_weight_date` (`date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `animals`
--
ALTER TABLE `animals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `batch_quality_checks`
--
ALTER TABLE `batch_quality_checks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `batch_usage`
--
ALTER TABLE `batch_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `breeding_history`
--
ALTER TABLE `breeding_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crops`
--
ALTER TABLE `crops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `crop_activities`
--
ALTER TABLE `crop_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `crop_expenses`
--
ALTER TABLE `crop_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `crop_issues`
--
ALTER TABLE `crop_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `crop_revenue`
--
ALTER TABLE `crop_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `deceased_animals`
--
ALTER TABLE `deceased_animals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `delivery_issues`
--
ALTER TABLE `delivery_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `environmental_issues`
--
ALTER TABLE `environmental_issues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `environmental_readings`
--
ALTER TABLE `environmental_readings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `equipment_checkups`
--
ALTER TABLE `equipment_checkups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `feeding_schedules`
--
ALTER TABLE `feeding_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `fertilizer_logs`
--
ALTER TABLE `fertilizer_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fertilizer_schedules`
--
ALTER TABLE `fertilizer_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `fertilizer_types`
--
ALTER TABLE `fertilizer_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `fields`
--
ALTER TABLE `fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `field_observations`
--
ALTER TABLE `field_observations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `financial_data`
--
ALTER TABLE `financial_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `get_field_size.php`
--
ALTER TABLE `get_field_size.php`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `harvests`
--
ALTER TABLE `harvests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `harvest_assignments`
--
ALTER TABLE `harvest_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `harvest_records`
--
ALTER TABLE `harvest_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `harvest_resources`
--
ALTER TABLE `harvest_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `harvest_schedule`
--
ALTER TABLE `harvest_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `health_records`
--
ALTER TABLE `health_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `housing_assignments`
--
ALTER TABLE `housing_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `inventory_log`
--
ALTER TABLE `inventory_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `inventory_usage`
--
ALTER TABLE `inventory_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `irrigation_logs`
--
ALTER TABLE `irrigation_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `irrigation_schedules`
--
ALTER TABLE `irrigation_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `item_categories`
--
ALTER TABLE `item_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `moisture_thresholds`
--
ALTER TABLE `moisture_thresholds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pesticide_applications`
--
ALTER TABLE `pesticide_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pesticide_types`
--
ALTER TABLE `pesticide_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `plantings`
--
ALTER TABLE `plantings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `purchase_items`
--
ALTER TABLE `purchase_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `purefarm_growth_milestones`
--
ALTER TABLE `purefarm_growth_milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `soil_moisture`
--
ALTER TABLE `soil_moisture`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `soil_nutrients`
--
ALTER TABLE `soil_nutrients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `soil_tests`
--
ALTER TABLE `soil_tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `soil_treatments`
--
ALTER TABLE `soil_treatments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `staff_documents`
--
ALTER TABLE `staff_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_field_assignments`
--
ALTER TABLE `staff_field_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_schedule`
--
ALTER TABLE `staff_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `stock_requests`
--
ALTER TABLE `stock_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `supervisor_teams`
--
ALTER TABLE `supervisor_teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `supplier_performance`
--
ALTER TABLE `supplier_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `treatments`
--
ALTER TABLE `treatments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vaccinations`
--
ALTER TABLE `vaccinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `waste_management`
--
ALTER TABLE `waste_management`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `weather_data`
--
ALTER TABLE `weather_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=91;

--
-- AUTO_INCREMENT for table `weather_forecast`
--
ALTER TABLE `weather_forecast`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `weather_settings`
--
ALTER TABLE `weather_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `web_push_subscriptions`
--
ALTER TABLE `web_push_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weight_records`
--
ALTER TABLE `weight_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `batch_quality_checks`
--
ALTER TABLE `batch_quality_checks`
  ADD CONSTRAINT `batch_quality_checks_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  ADD CONSTRAINT `batch_quality_checks_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `batch_usage`
--
ALTER TABLE `batch_usage`
  ADD CONSTRAINT `batch_usage_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `inventory_batches` (`id`),
  ADD CONSTRAINT `batch_usage_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `crops`
--
ALTER TABLE `crops`
  ADD CONSTRAINT `crops_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `crop_activities`
--
ALTER TABLE `crop_activities`
  ADD CONSTRAINT `crop_activities_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crop_expenses`
--
ALTER TABLE `crop_expenses`
  ADD CONSTRAINT `crop_expenses_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crop_issues`
--
ALTER TABLE `crop_issues`
  ADD CONSTRAINT `crop_issues_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crop_revenue`
--
ALTER TABLE `crop_revenue`
  ADD CONSTRAINT `crop_revenue_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deceased_animals`
--
ALTER TABLE `deceased_animals`
  ADD CONSTRAINT `fk_deceased_animal` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`animal_id`) ON UPDATE CASCADE;

--
-- Constraints for table `delivery_issues`
--
ALTER TABLE `delivery_issues`
  ADD CONSTRAINT `fk_delivery_issues_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_delivery_issues_user` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  ADD CONSTRAINT `fk_delivery_tracking_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `equipment_checkups`
--
ALTER TABLE `equipment_checkups`
  ADD CONSTRAINT `equipment_checkups_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`),
  ADD CONSTRAINT `equipment_checkups_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `fertilizer_logs`
--
ALTER TABLE `fertilizer_logs`
  ADD CONSTRAINT `fertilizer_logs_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `fertilizer_schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fertilizer_schedules`
--
ALTER TABLE `fertilizer_schedules`
  ADD CONSTRAINT `fertilizer_schedules_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fertilizer_schedules_ibfk_2` FOREIGN KEY (`fertilizer_type_id`) REFERENCES `fertilizer_types` (`id`);

--
-- Constraints for table `financial_data`
--
ALTER TABLE `financial_data`
  ADD CONSTRAINT `financial_data_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `harvests`
--
ALTER TABLE `harvests`
  ADD CONSTRAINT `harvests_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `harvest_assignments`
--
ALTER TABLE `harvest_assignments`
  ADD CONSTRAINT `harvest_assignments_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `harvest_assignments_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `harvest_assignments_ibfk_3` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `harvest_resources`
--
ALTER TABLE `harvest_resources`
  ADD CONSTRAINT `harvest_resources_ibfk_1` FOREIGN KEY (`harvest_id`) REFERENCES `harvest_schedule` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `harvest_schedule`
--
ALTER TABLE `harvest_schedule`
  ADD CONSTRAINT `harvest_schedule_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `health_records`
--
ALTER TABLE `health_records`
  ADD CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `inventory_batches`
--
ALTER TABLE `inventory_batches`
  ADD CONSTRAINT `inventory_batches_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `fk_item_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`),
  ADD CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `inventory_log`
--
ALTER TABLE `inventory_log`
  ADD CONSTRAINT `inventory_log_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `inventory_usage`
--
ALTER TABLE `inventory_usage`
  ADD CONSTRAINT `inventory_usage_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_usage_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `irrigation_logs`
--
ALTER TABLE `irrigation_logs`
  ADD CONSTRAINT `irrigation_logs_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `irrigation_schedules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `irrigation_schedules`
--
ALTER TABLE `irrigation_schedules`
  ADD CONSTRAINT `irrigation_schedules_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `moisture_thresholds`
--
ALTER TABLE `moisture_thresholds`
  ADD CONSTRAINT `moisture_thresholds_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plantings`
--
ALTER TABLE `plantings`
  ADD CONSTRAINT `plantings_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`),
  ADD CONSTRAINT `plantings_ibfk_2` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`);

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `fk_purchases_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_purchases_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_items`
--
ALTER TABLE `purchase_items`
  ADD CONSTRAINT `fk_purchase_items_inventory` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_purchase_items_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `soil_moisture`
--
ALTER TABLE `soil_moisture`
  ADD CONSTRAINT `soil_moisture_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `soil_nutrients`
--
ALTER TABLE `soil_nutrients`
  ADD CONSTRAINT `soil_nutrients_field_fk` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `soil_tests`
--
ALTER TABLE `soil_tests`
  ADD CONSTRAINT `soil_tests_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`);

--
-- Constraints for table `soil_treatments`
--
ALTER TABLE `soil_treatments`
  ADD CONSTRAINT `soil_treatments_ibfk_1` FOREIGN KEY (`field_id`) REFERENCES `fields` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `staff_documents`
--
ALTER TABLE `staff_documents`
  ADD CONSTRAINT `staff_documents_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_tasks`
--
ALTER TABLE `staff_tasks`
  ADD CONSTRAINT `staff_tasks_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_requests`
--
ALTER TABLE `stock_requests`
  ADD CONSTRAINT `stock_requests_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `stock_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `supplier_performance`
--
ALTER TABLE `supplier_performance`
  ADD CONSTRAINT `fk_supplier_performance_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_supplier_performance_user` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `treatments`
--
ALTER TABLE `treatments`
  ADD CONSTRAINT `treatments_ibfk_1` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `treatments_ibfk_2` FOREIGN KEY (`applied_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vaccinations`
--
ALTER TABLE `vaccinations`
  ADD CONSTRAINT `vaccinations_ibfk_1` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `waste_management`
--
ALTER TABLE `waste_management`
  ADD CONSTRAINT `waste_management_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `waste_management_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `web_push_subscriptions`
--
ALTER TABLE `web_push_subscriptions`
  ADD CONSTRAINT `web_push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `weight_records`
--
ALTER TABLE `weight_records`
  ADD CONSTRAINT `weight_records_ibfk_1` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
