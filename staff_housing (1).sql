-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 14, 2026 at 11:11 AM
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
-- Database: `staff_housing`
--
CREATE DATABASE IF NOT EXISTS `staff_housing` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `staff_housing`;

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

DROP TABLE IF EXISTS `applicants`;
CREATE TABLE IF NOT EXISTS `applicants` (
  `applicant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each applicant',
  `pf_no` varchar(100) NOT NULL COMMENT 'PF number of the applicant',
  `name` varchar(100) NOT NULL COMMENT 'Name of the applicant',
  `email` varchar(100) NOT NULL COMMENT 'Email of the applicant',
  `contact` varchar(100) NOT NULL COMMENT 'Contact of the applicant',
  `password` varchar(225) NOT NULL COMMENT 'The applicant’s hashed password',
  `username` varchar(100) NOT NULL COMMENT 'username of the applicant',
  `date_created` datetime DEFAULT current_timestamp() COMMENT 'Date the applicant was created',
  `next_of_kin_name` varchar(255) NOT NULL DEFAULT '',
  `next_of_kin_contact` varchar(50) NOT NULL DEFAULT '',
  `is_email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `name of next of kin` varchar(255) DEFAULT NULL,
  `next of kin contact` varchar(50) DEFAULT NULL,
  `ballot_number` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`applicant_id`),
  UNIQUE KEY `ux_applicants_pf_no` (`pf_no`),
  UNIQUE KEY `ux_applicants_username` (`username`),
  UNIQUE KEY `idx_ballot_number` (`ballot_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`applicant_id`, `pf_no`, `name`, `email`, `contact`, `password`, `username`, `date_created`, `next_of_kin_name`, `next_of_kin_contact`, `is_email_verified`, `email_verification_token`, `password_reset_token`, `password_reset_expires`, `status`, `name of next of kin`, `next of kin contact`, `ballot_number`) VALUES
('A001', '3040', '', '', '', '$2y$10$ngbTwPgAc7/U9tKW9SA4.OYhGO8h5L16jUgLBHghRH.XHmP0fZahW', 'Maxwell', '2025-08-04 10:41:26', '', '', 0, NULL, NULL, NULL, 'Tenant', NULL, NULL, NULL),
('A002', '3035', '', '', '', '$2y$10$Tvb4W3THp1DLdWRnXT57.eWi4xQshg/zSpYsAZyKrqFFf4llOuQvy', 'Jack', '2025-08-04 10:41:26', '', '', 0, NULL, NULL, NULL, 'Tenant', NULL, NULL, NULL),
('A003', '3099', '', '', '', '$2y$10$xLNiCLelELTh1zbV4kNwAeghSWxWGOTf42UATx1AWROWe/JnWiQ/O', 'Andrew', '2025-08-04 10:41:26', '', '', 0, NULL, NULL, NULL, 'Tenant', NULL, NULL, NULL),
('A004', '3098', '', '', '', '$2y$10$rYOst2/BF7xdeC.DsjjTgOzrYu6jWThisQXL1s09bmY61IXxuFoLO', 'Naruto', '2025-08-04 10:41:26', '', '', 0, NULL, NULL, NULL, 'Tenant', NULL, NULL, NULL),
('A005', '5000', 'Mohamed Isaak Boru', 'ratimboru@gmail.com', '+254700660468', '$2y$10$PYgnXBSBs7hfxBRWdXHV4.PDpja.V3be5DHFvEZu.s5QhqCKXAQsG', 'Ratim', '2026-02-12 11:52:55', 'Isaak Boru', '+254743748453', 0, '0f4acb023a8ee6f76058067d6e18ea7c09546e460071b599', 'b62b1e66b177ebaa1b49c5c4d798f8b66735db928b08484a', '2026-02-13 13:05:51', 'Tenant', NULL, NULL, NULL),
('A006', '7777', 'Isaak Boru', 'isaakboru@gmail.com', '0724759600', '$2y$10$DvG25Ir7l83WVL2sb1vDbuj845IU0Nj/OtXoFHl5rI7ef8ZqFbb5C', 'Boru', '2026-02-13 10:59:44', 'Mohamed Isaak', '0700660468', 0, 'e518190270c05d347ca3c5839743a46cd06e7dc18c6c00f5', '79d1313b96957369bc10f0593a8c9444a0498a185a983f05', '2026-02-13 12:51:26', 'Tenant', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

DROP TABLE IF EXISTS `applications`;
CREATE TABLE IF NOT EXISTS `applications` (
  `application_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each application',
  `applicant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each applicant as foreign key',
  `category` varchar(100) NOT NULL COMMENT 'The type of house they are applying for',
  `house_no` varchar(100) NOT NULL COMMENT 'House number of the house applied for',
  `status` enum('pending','approved','rejected','cancelled','won') DEFAULT 'pending',
  `date` datetime NOT NULL COMMENT 'Date the application was applied',
  PRIMARY KEY (`application_id`),
  KEY `applicationt_id_ibfk_1` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `applicant_id`, `category`, `house_no`, `status`, `date`) VALUES
('AP002', 'A001', '4 Bedroom', '401', 'approved', '2025-07-16 00:00:00'),
('AP004', 'A001', '3 Bedroom', '301', 'won', '2025-07-17 00:00:00'),
('AP007', 'A001', '1 Bedroom', '101', 'won', '2025-07-21 00:00:00'),
('AP008', 'A001', '4 Bedroom', '409', 'won', '2025-07-21 00:00:00'),
('AP009', 'A002', '1 Bedroom', '101', 'won', '2025-07-24 00:00:00'),
('AP010', 'A003', '1 Bedroom', '105', 'won', '2025-07-28 00:00:00'),
('AP011', 'A004', '2 Bedroom', '208', 'won', '2025-07-28 00:00:00'),
('AP012', 'A005', '4 Bedroom', '305', 'won', '2026-02-12 00:00:00'),
('AP013', 'A006', '1 Bedroom', '201', 'won', '2026-02-13 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `balloting`
--

DROP TABLE IF EXISTS `balloting`;
CREATE TABLE IF NOT EXISTS `balloting` (
  `ballot_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each ballot entry',
  `applicant_id` varchar(100) NOT NULL COMMENT 'References the applicant from the Applicant table',
  `house_id` varchar(100) NOT NULL COMMENT 'References the house from the House table',
  `ballot_no` varchar(100) NOT NULL COMMENT 'A unique ballot number',
  `date_of_ballot` datetime NOT NULL COMMENT 'The date on which the ballot will be conducted',
  `status` enum('declined','accepted','','') NOT NULL COMMENT 'Status of the ballot (declined, accepted)',
  PRIMARY KEY (`ballot_id`),
  UNIQUE KEY `unique ballot no` (`ballot_no`),
  KEY `applicant_id_FK` (`applicant_id`),
  KEY `house_id_FK` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `balloting`
--

INSERT INTO `balloting` (`ballot_id`, `applicant_id`, `house_id`, `ballot_no`, `date_of_ballot`, `status`) VALUES
('ballot001', 'A001', 'H1001', '1000', '2025-07-25 00:00:00', ''),
('ballot002', 'A003', 'H1001', '1012', '2025-07-28 00:00:00', ''),
('ballot003', 'A004', 'H4002', '1004', '2025-07-28 00:00:00', '');

-- --------------------------------------------------------

--
-- Table structure for table `ballot_control`
--

DROP TABLE IF EXISTS `ballot_control`;
CREATE TABLE IF NOT EXISTS `ballot_control` (
  `id` int(11) NOT NULL DEFAULT 1,
  `is_open` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ballot_control`
--

INSERT INTO `ballot_control` (`id`, `is_open`, `start_date`, `end_date`) VALUES
(1, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bills`
--

DROP TABLE IF EXISTS `bills`;
CREATE TABLE IF NOT EXISTS `bills` (
  `bill_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each bill',
  `service_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each service',
  `type_of_bill` varchar(100) NOT NULL COMMENT 'The of bill (from a billable service requested by a tenant)',
  `amount` int(11) NOT NULL COMMENT 'The amount to be paid to settle the bill',
  `date_billed` datetime NOT NULL COMMENT 'The date a tenant is billed',
  `date_settled` date DEFAULT NULL,
  `status` enum('paid','not paid','','') NOT NULL COMMENT 'The status of the bill (paid, not paid)',
  `statuses` enum('active','disputed') DEFAULT 'active' COMMENT 'current status of the bill',
  PRIMARY KEY (`bill_id`),
  KEY `service_id_FK` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bills`
--

INSERT INTO `bills` (`bill_id`, `service_id`, `type_of_bill`, `amount`, `date_billed`, `date_settled`, `status`, `statuses`) VALUES
('B001', 'S003', 'repair of door hinge', 700, '2025-07-29 00:00:00', '2025-08-04', 'paid', 'active'),
('B002', 'S002', 'repair of sockets', 2005, '2025-07-29 00:00:00', '2025-08-04', 'paid', 'active'),
('B003', 'S001', 'plumbing', 1000, '2025-07-30 00:00:00', NULL, 'not paid', 'active'),
('B004', 'S003', 'repair', 3001, '2025-07-30 00:00:00', '2025-08-06', 'paid', 'active'),
('B005', 'S004', 'masonry crack in the wall', 5555, '2025-08-04 00:00:00', NULL, 'not paid', 'active'),
('B006', 'S003', 'repair', 9000, '2025-08-04 00:00:00', NULL, 'not paid', 'active'),
('B007', 'S004', 'masonry', 1260, '2025-08-04 00:00:00', NULL, 'not paid', 'active'),
('B008', 'S004', 'masonry', 100, '2025-08-06 00:00:00', '2025-08-07', 'paid', 'active'),
('Bw001', 'S005', 'welding Service', 7880, '2025-08-06 00:00:00', NULL, 'not paid', 'disputed');

-- --------------------------------------------------------

--
-- Table structure for table `bill_update_logs`
--

DROP TABLE IF EXISTS `bill_update_logs`;
CREATE TABLE IF NOT EXISTS `bill_update_logs` (
  `bill_update_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each bill updated',
  `user_id` varchar(100) NOT NULL COMMENT 'Unique identifier of the user (the admin involved in the update of the house)',
  `bill_id` varchar(100) NOT NULL COMMENT 'Unique identifier for the bills',
  `device_type` varchar(100) NOT NULL COMMENT 'The identification and type of device involved in the update (computer, laptop, phone)',
  `details` text NOT NULL COMMENT 'The whole details of what the update was all about (if the bill was settled, if the amount to be paid was increased, if the bill to the tenant was revoked)',
  `old_amount` decimal(10,2) NOT NULL COMMENT 'The previous amount before update',
  `new_amount` decimal(10,2) NOT NULL COMMENT 'New amount after updating the bill amount',
  `date_updated` datetime NOT NULL COMMENT 'The date and the exact time the bill was updated',
  PRIMARY KEY (`bill_update_id`),
  KEY `bill_id_FK` (`bill_id`),
  KEY `user_id_FK2` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bill_update_logs`
--

INSERT INTO `bill_update_logs` (`bill_update_id`, `user_id`, `bill_id`, `device_type`, `details`, `old_amount`, `new_amount`, `date_updated`) VALUES
('BU001', 'user004', 'B005', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[masonry crack in the wall] Bill Update by Mark on 2025-08-05 11:30:42 from KES 5055 to KES 5555.00', 0.00, 5555.00, '0000-00-00 00:00:00'),
('BU002', 'user004', 'B006', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[repair] Amount updated by Mark | Changed from KES 9550 to KES 9000.00 | 2025-08-05 11:42:56', 9550.00, 9000.00, '0000-00-00 00:00:00'),
('BU003', 'user004', 'B007', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[masonry] Amount updated by Naruto | Changed from KES 1260 to KES 1060.00 | 2025-08-06 08:51:27', 1260.00, 1060.00, '0000-00-00 00:00:00'),
('BU004', 'user004', 'B007', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[masonry] Amount updated by Naruto | Changed from KES 1260 to KES 1060.00 | 2025-08-06 08:51:42', 1260.00, 1060.00, '0000-00-00 00:00:00'),
('BU005', 'user004', 'B007', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[masonry] Amount updated by Naruto | Changed from KES 1260 to KES 1060.00 | 2025-08-06 08:52:51', 1260.00, 1060.00, '0000-00-00 00:00:00'),
('BU006', 'user004', 'B006', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[repair] Amount updated by Naruto | Changed from KES 9000 to KES 8000.00 | 2025-08-06 08:53:15', 9000.00, 8000.00, '0000-00-00 00:00:00'),
('BU007', 'user004', 'B004', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[repair] Amount updated by Naruto | Changed from KES 3000 to KES 3001.00 | 2025-08-06 08:59:27', 3000.00, 3001.00, '0000-00-00 00:00:00'),
('BU008', 'user004', 'Bw001', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[welding Service] Amount updated by Naruto | Changed from KES 90000 to KES 10100.00 | 2025-08-06 09:40:14', 90000.00, 10100.00, '0000-00-00 00:00:00'),
('BU009', 'user004', 'Bw001', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[welding Service] Amount updated by Naruto | Changed from KES 9000 to KES 7800.00 | 2025-08-06 10:02:25', 9000.00, 7800.00, '0000-00-00 00:00:00'),
('BU010', 'user004', 'B008', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[masonry] Amount updated by Naruto | Changed from KES 10 to KES 100.00 | 2025-08-06 10:20:50', 10.00, 100.00, '0000-00-00 00:00:00'),
('BU011', 'user004', 'B002', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[repair of sockets] Amount updated by Naruto | Changed from KES 2000 to KES 2005.00 | 2025-08-06 10:21:59', 2000.00, 2005.00, '0000-00-00 00:00:00'),
('BU012', 'user004', 'Bw001', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Sa', '[welding Service] Amount updated by Naruto | Changed from KES 7800 to KES 7880.00 | 2025-08-06 10:23:16', 7800.00, 7880.00, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `houses`
--

DROP TABLE IF EXISTS `houses`;
CREATE TABLE IF NOT EXISTS `houses` (
  `house_id` varchar(100) NOT NULL COMMENT 'Unique identifier for the houses',
  `house_no` varchar(100) NOT NULL COMMENT 'unique house number for each house',
  `category` varchar(100) NOT NULL COMMENT 'The type of house according to number of bedrooms it has',
  `date` datetime NOT NULL COMMENT 'The date each house was created',
  `creator` varchar(100) NOT NULL COMMENT 'The one responsible for the creation of the house',
  `rent` int(11) NOT NULL COMMENT 'The amount to be paid as rent for each house',
  `status` enum('vacant','occupied','reserved','','') NOT NULL COMMENT 'Current availability status of each house (vacant, occupied, reserved)',
  PRIMARY KEY (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `houses`
--

INSERT INTO `houses` (`house_id`, `house_no`, `category`, `date`, `creator`, `rent`, `status`) VALUES
('H1001', '101', '1 Bedroom', '2026-02-14 00:00:00', 'STEPHEN', 18588, 'vacant'),
('H2001', '201', '2 Bedroom', '2026-02-14 00:00:00', 'STEPHEN', 21000, 'vacant'),
('H2002', '202', '2 Bedroom', '2025-07-28 00:00:00', 'STEPHEN', 15700, 'vacant'),
('H3001', '301', '3 Bedroom', '2025-07-28 00:00:00', 'STEPHEN', 17300, 'vacant'),
('H4001', '404', '4 Bedroom', '2025-08-05 00:00:00', 'STEPHEN', 15005, 'reserved'),
('H4002', '401', '4 Bedroom', '2025-08-05 00:00:00', 'STEPHEN', 20511, 'occupied'),
('H4003', '305', '4 Bedroom', '2026-02-12 00:00:00', 'Mohamed', 50000, 'reserved');

-- --------------------------------------------------------

--
-- Table structure for table `house_update_logs`
--

DROP TABLE IF EXISTS `house_update_logs`;
CREATE TABLE IF NOT EXISTS `house_update_logs` (
  `house_update_id` varchar(100) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  `house_id` varchar(100) NOT NULL,
  `device_type` varchar(255) DEFAULT NULL,
  `details` text NOT NULL,
  `date_updated` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`house_update_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `house_update_logs`
--

INSERT INTO `house_update_logs` (`house_update_id`, `user_id`, `house_id`, `device_type`, `details`, `date_updated`) VALUES
('HU001', 'user004', 'H1001', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'House updated: Rent: KES 18555 → KES 18588 | Status: occupied → reserved', '2025-08-05 15:58:23'),
('HU002', 'user004', 'H4002', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'House updated: Rent: KES 20500 → KES 20511 | Status: reserved → Occupied', '2025-08-05 15:59:03'),
('HU003', 'system', 'H4003', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'House created: 305 (4 Bedroom) | Rent: KES 50000 | Status: Vacant', '2026-02-12 10:33:33'),
('HU004', 'system', 'H4003', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'House updated: Status: vacant → reserved', '2026-02-12 10:33:47'),
('HU005', 'U005', 'H1001', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'House updated: Status: reserved → Vacant', '2026-02-14 11:13:16'),
('HU006', 'U005', 'H2001', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', 'House updated: Status: reserved → Vacant', '2026-02-14 11:13:29');

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

DROP TABLE IF EXISTS `notices`;
CREATE TABLE IF NOT EXISTS `notices` (
  `notice_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each notice sent',
  `tenant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each tenant',
  `details` varchar(500) NOT NULL COMMENT 'The content of the notice sent',
  `date_sent` datetime NOT NULL COMMENT 'The date the notice was sent ',
  `date_received` datetime NOT NULL COMMENT 'The date the notice was received ',
  `notice_end_date` datetime NOT NULL COMMENT 'The date a tenant prefers to vacate',
  `status` enum('active','revoked') NOT NULL COMMENT 'Current status of the notice',
  PRIMARY KEY (`notice_id`),
  KEY `tenant_id_FK2` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notices`
--

INSERT INTO `notices` (`notice_id`, `tenant_id`, `details`, `date_sent`, `date_received`, `notice_end_date`, `status`) VALUES
('N002', 'T001', 'This is a formal notice that I will vacate the premises on 30th September 2025.', '2025-08-01 00:00:00', '2025-08-01 00:00:00', '2025-09-30 00:00:00', 'active'),
('N004', 'T001', 'I would like to vacate the premises on this date.', '2025-08-01 00:00:00', '0000-00-00 00:00:00', '2025-08-31 00:00:00', 'active'),
('N005', 'T001', 'i want to vacate', '2025-08-07 00:00:00', '0000-00-00 00:00:00', '2025-09-06 00:00:00', 'revoked'),
('N006', 'T001', 'i would like to give a notice to leave on this day', '2025-08-08 00:00:00', '0000-00-00 00:00:00', '2025-09-07 00:00:00', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` varchar(100) NOT NULL COMMENT 'Unique identifier of each notification',
  `user_id` varchar(100) NOT NULL COMMENT 'Sender’s id as a foreign key',
  `recipient_type` varchar(100) NOT NULL COMMENT 'Type of recipient (tenant or applicant)',
  `recipient_id` varchar(100) NOT NULL COMMENT 'The recipient’s id',
  `message` varchar(300) NOT NULL COMMENT 'The details of the message ',
  `date_sent` datetime NOT NULL COMMENT 'Date notification sent',
  `date_received` datetime NOT NULL COMMENT 'Date notification received',
  `status` enum('unread','read') NOT NULL DEFAULT 'unread' COMMENT 'Status of notification',
  PRIMARY KEY (`notification_id`),
  KEY `user_id_FK` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `recipient_type`, `recipient_id`, `message`, `date_sent`, `date_received`, `status`) VALUES
('NT001', 'user002', 'applicant', 'A001', 'Your application has been approved successfully.', '2025-07-24 10:35:00', '2025-07-24 10:35:00', 'read'),
('NT688734fe61c8a', 'user002', 'applicant', 'A004', 'your application has been received successfully', '2025-07-28 10:29:50', '2025-07-28 10:29:50', 'read'),
('NT688739a291b02', 'user002', 'applicant', 'A004', 'you are now an applicant', '2025-07-28 10:49:38', '2025-07-28 10:49:38', 'read'),
('NT68873eadc2d42', 'user002', 'applicant', 'A004', 'send', '2025-07-28 11:11:09', '2025-07-28 11:11:09', 'read'),
('NT6889bf3443918', 'user002', 'applicant', 'A001', 'all', '2025-07-30 08:44:04', '2025-07-30 08:44:04', 'unread'),
('NT6892', 'user002', 'tenant', 'T001', 'got you', '2025-07-31 11:53:42', '2025-07-31 11:53:42', 'read'),
('NT6893', 'user002', 'tenant', 'T003', 's', '2025-08-06 15:50:48', '2025-08-06 15:50:48', 'unread'),
('NT698d85c32968e', 'user002', 'applicant', 'A005', 'woza', '2026-02-12 08:48:19', '2026-02-12 08:48:19', 'read'),
('NT698d8954430a2', 'user002', 'applicant', 'A005', 'woza', '2026-02-12 09:03:32', '2026-02-12 09:03:32', 'read');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

DROP TABLE IF EXISTS `service_requests`;
CREATE TABLE IF NOT EXISTS `service_requests` (
  `service_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each service requested',
  `tenant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each tenant',
  `type_of_service` varchar(200) NOT NULL COMMENT 'The type of service requested (a tenant will be able to input the particular service)',
  `bill_amount` int(11) NOT NULL COMMENT 'The amount to be paid if the service requested is deemed payable',
  `date` datetime NOT NULL COMMENT 'The date the service was requested',
  `status` enum('pending','done','','') NOT NULL COMMENT 'The status of the service requested (pending, done)',
  `details` varchar(100) NOT NULL COMMENT 'Details for the service requested',
  PRIMARY KEY (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`service_id`, `tenant_id`, `type_of_service`, `bill_amount`, `date`, `status`, `details`) VALUES
('S001', '1', 'plumbing', 0, '2025-07-22 00:00:00', 'done', 'tap'),
('S002', '1', 'electrical', 0, '2025-07-23 00:00:00', 'done', 'sockets'),
('S003', 'T001', 'repair', 0, '2025-07-29 10:45:00', 'pending', 'door hinge'),
('S004', 'T001', 'masonry', 0, '2025-08-01 00:00:00', 'pending', 'a crack in the wall'),
('S005', 'T001', 'welding', 7800, '2025-08-06 00:00:00', 'pending', 'broken metal door '),
('S006', 'T001', 'electrical', 0, '2025-08-07 00:00:00', 'pending', 'my bedrooms socket '),
('S007', 'T001', 'ceiling', 0, '2025-08-08 00:00:00', 'pending', 'roof'),
('S008', 'T001', 'glaziers', 0, '2025-08-08 00:00:00', 'pending', 'window panes ');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
CREATE TABLE IF NOT EXISTS `tenants` (
  `tenant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each tenant',
  `applicant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each applicant',
  `house_no` varchar(100) NOT NULL COMMENT 'A unique number for each house',
  `move_in_date` datetime NOT NULL COMMENT 'The date a tenant moves in to occupy the house',
  `move_out_date` datetime NOT NULL COMMENT 'The date a tenant decides to vacate the house',
  `status` enum('active','terminated') NOT NULL COMMENT 'Current status of the tenant',
  PRIMARY KEY (`tenant_id`),
  KEY `applicant_id_FK2` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`tenant_id`, `applicant_id`, `house_no`, `move_in_date`, `move_out_date`, `status`) VALUES
('T001', 'A002', '101', '2025-07-25 00:00:00', '0000-00-00 00:00:00', 'active'),
('T002', 'A001', '301', '2025-07-25 00:00:00', '0000-00-00 00:00:00', 'active'),
('T003', 'A003', '105', '2025-07-28 00:00:00', '2025-08-06 15:46:24', 'terminated'),
('T004', 'A001', '101', '2026-02-13 00:00:00', '0000-00-00 00:00:00', 'active'),
('T005', 'A003', '105', '2026-02-13 00:00:00', '0000-00-00 00:00:00', 'active'),
('T006', 'A004', '208', '2026-02-13 00:00:00', '0000-00-00 00:00:00', 'active'),
('T007', 'A001', '301', '2026-02-13 00:00:00', '0000-00-00 00:00:00', 'active'),
('T008', 'A005', '305', '2026-02-13 00:00:00', '0000-00-00 00:00:00', 'active'),
('T009', 'A001', '409', '2026-02-13 00:00:00', '0000-00-00 00:00:00', 'active'),
('T010', 'A002', '101', '2026-02-13 00:00:00', '0000-00-00 00:00:00', 'active'),
('T011', 'A006', '201', '2026-02-14 00:00:00', '0000-00-00 00:00:00', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` varchar(100) NOT NULL COMMENT 'Unique identifier of the user',
  `pf_no` varchar(100) NOT NULL COMMENT 'PF number of the user',
  `username` varchar(225) NOT NULL COMMENT 'The username of the user',
  `name` varchar(225) NOT NULL COMMENT 'The name of the user',
  `email` varchar(255) NOT NULL COMMENT 'The email of the user',
  `role` varchar(100) NOT NULL COMMENT 'Role of the user (ICT admin, CS admin, SUPER admin)',
  `password` varchar(225) NOT NULL COMMENT 'Hashed Password of the user',
  `date_created` datetime NOT NULL COMMENT 'The date a user was created',
  `status` varchar(100) NOT NULL COMMENT 'Current state of the user (active,\r\n  De active)\r\n',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `pf_no`, `username`, `name`, `email`, `role`, `password`, `date_created`, `status`) VALUES
('', '', '', '', '', '', '', '2026-02-12 10:41:34', ''),
('U001', '', 'admin', '', 'admin@example.com', 'admin', '$2y$10$b5C58Ftmo.o0tJR8/O93r.V23bUCWdRTpH5PV6HD0GpxBuu9plbRi', '0000-00-00 00:00:00', 'active'),
('U005', '5000', 'Mohamed ', 'Mohamed Isaak ', 'ratimboru@gmail.com', 'CS Admin', '$2y$10$SExhPtrMAtaJQs5JdeY8WOTVzfEvrjw.JwNjyYnO45H5hEIm4dfGu', '2026-02-12 10:41:34', 'Active'),
('user001', '3965', 'STEPHEN ', 'stephen kariuki', 'karstephen016@gmail.com', 'ICT Admin', '$2y$10$9veav2fhYjJIjv48DMg1hORvlxQsun2v2qdrdrCNPzClNQKfWcCFK', '2025-07-10 15:22:23', 'Deactivated'),
('user002', '3967', 'JOEL', 'JOEL NGANGA', 'njoroge.stephen2022@students.jkuat.ac.ke', 'CS Admin', '$2y$10$0seCiE7xgOcdXgn.g9ufbuYqtr1BDPOIwLicOX1arx.FDxynANJq2', '2025-07-10 15:22:47', 'Active'),
('user003', '3968', 'steve', 'maina', 'steve@gmail.com', 'ICT Admin', '$2y$10$QShfkmZgh5fO.u/ta/rAoegvs49FJVTddKkRp.QFPrTeL195Mtlu2', '2025-07-11 08:54:25', 'Active'),
('user004', '3969', 'Mark', 'Mark Maina', 'maish@gmail.com', 'CS Admin', '$2y$10$iztdRQoYL2lSex5Z.mC1i.19RnA.nQu5mzdNsuJRnDiEEkyKPxd6C', '2025-07-15 11:36:26', 'Active');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applicationt_id_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `balloting`
--
ALTER TABLE `balloting`
  ADD CONSTRAINT `applicant_id_FK` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `house_id_FK` FOREIGN KEY (`house_id`) REFERENCES `houses` (`house_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bills`
--
ALTER TABLE `bills`
  ADD CONSTRAINT `service_id_FK` FOREIGN KEY (`service_id`) REFERENCES `service_requests` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bill_update_logs`
--
ALTER TABLE `bill_update_logs`
  ADD CONSTRAINT `bill_id_FK` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`bill_id`),
  ADD CONSTRAINT `user_id_FK2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notices`
--
ALTER TABLE `notices`
  ADD CONSTRAINT `tenant_id_FK2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `user_id_FK` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `applicant_id_FK2` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ---------------------------------------------------------------------------
-- Applied migrations (idempotent statements appended to ensure dump is up-to-date)
-- ---------------------------------------------------------------------------

-- 2026-02-11: Add applicant profile fields (idempotent)
SET @schema = DATABASE();

-- name
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'name') = 0,
    'ALTER TABLE applicants ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT ""',
    'SELECT "column name exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- email
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'email') = 0,
    'ALTER TABLE applicants ADD COLUMN `email` VARCHAR(255) NOT NULL DEFAULT ""',
    'SELECT "column email exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- contact
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'contact') = 0,
    'ALTER TABLE applicants ADD COLUMN `contact` VARCHAR(50) NOT NULL DEFAULT ""',
    'SELECT "column contact exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- next_of_kin_name
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'next_of_kin_name') = 0,
    'ALTER TABLE applicants ADD COLUMN `next_of_kin_name` VARCHAR(255) NOT NULL DEFAULT ""',
    'SELECT "column next_of_kin_name exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- next_of_kin_contact
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'next_of_kin_contact') = 0,
    'ALTER TABLE applicants ADD COLUMN `next_of_kin_contact` VARCHAR(50) NOT NULL DEFAULT ""',
    'SELECT "column next_of_kin_contact exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_email_verified
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'is_email_verified') = 0,
    'ALTER TABLE applicants ADD COLUMN `is_email_verified` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "column is_email_verified exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- email_verification_token
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'email_verification_token') = 0,
    'ALTER TABLE applicants ADD COLUMN `email_verification_token` VARCHAR(255) NULL',
    'SELECT "column email_verification_token exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- password_reset_token
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'password_reset_token') = 0,
    'ALTER TABLE applicants ADD COLUMN `password_reset_token` VARCHAR(255) NULL',
    'SELECT "column password_reset_token exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- password_reset_expires
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'password_reset_expires') = 0,
    'ALTER TABLE applicants ADD COLUMN `password_reset_expires` DATETIME NULL',
    'SELECT "column password_reset_expires exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ensure unique indexes on pf_no and username
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND INDEX_NAME = 'ux_applicants_pf_no') = 0,
    'ALTER TABLE applicants ADD UNIQUE INDEX ux_applicants_pf_no (pf_no)',
    'SELECT "index ux_applicants_pf_no exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND INDEX_NAME = 'ux_applicants_username') = 0,
    'ALTER TABLE applicants ADD UNIQUE INDEX ux_applicants_username (username)',
    'SELECT "index ux_applicants_username exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2026-02-13: create ballot_control if missing
CREATE TABLE IF NOT EXISTS ballot_control (
    id INT PRIMARY KEY DEFAULT 1,
    is_open TINYINT(1) NOT NULL DEFAULT 0,
    start_date DATETIME DEFAULT NULL,
    end_date DATETIME DEFAULT NULL
);
INSERT INTO ballot_control (id, is_open) SELECT 1, 0 WHERE NOT EXISTS (SELECT 1 FROM ballot_control WHERE id = 1);

-- 2026-02-15: add role and photo to applicants (idempotent)
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'role') = 0,
    "ALTER TABLE applicants ADD COLUMN role ENUM('applicant','tenant') NOT NULL DEFAULT 'applicant'",
    'SELECT "column role exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'photo') = 0,
    'ALTER TABLE applicants ADD COLUMN photo VARCHAR(255) DEFAULT NULL',
    'SELECT "column photo exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2026-02-16: add is_disabled and manual_allocations
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'is_disabled') = 0,
    'ALTER TABLE applicants ADD COLUMN is_disabled TINYINT(1) DEFAULT 0',
    'SELECT "column applicants.is_disabled exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS manual_allocations (
  allocation_id VARCHAR(64) NOT NULL,
  admin_id VARCHAR(64) NOT NULL,
  applicant_id VARCHAR(64) NOT NULL,
  house_no VARCHAR(64) NOT NULL,
  date_allocated DATETIME NOT NULL,
  notes TEXT,
  PRIMARY KEY (allocation_id)
);

-- 2026-02-16: add ballot category to balloting (idempotent)
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'balloting' AND COLUMN_NAME = 'category') = 0,
    'ALTER TABLE balloting ADD COLUMN category VARCHAR(80) DEFAULT NULL',
    'SELECT "column balloting.category exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @__idx_exists FROM INFORMATION_SCHEMA.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'balloting' AND INDEX_NAME = 'idx_ballot_category_no';
SET @__sql = IF(@__idx_exists = 0, 'ALTER TABLE balloting ADD UNIQUE INDEX idx_ballot_category_no (category, ballot_no)', 'SELECT "idx_exists"');
PREPARE __stmt FROM @__sql; EXECUTE __stmt; DEALLOCATE PREPARE __stmt;

-- 2026-02-20: add raffle system (tables + raffle columns)
CREATE TABLE IF NOT EXISTS `raffle_draws` (
  `draw_id` varchar(100) NOT NULL,
  `house_id` varchar(100) NOT NULL,
  `category` varchar(100) NOT NULL,
  `total_slots` int(11) NOT NULL,
  `draw_date` datetime DEFAULT NULL,
  `winning_slot` int(11) DEFAULT NULL,
  `winning_applicant_id` varchar(100) DEFAULT NULL,
  `status` enum('open','closed','completed') DEFAULT 'open',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`draw_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `raffle_slots` (
  `slot_id` varchar(100) NOT NULL,
  `draw_id` varchar(100) NOT NULL,
  `applicant_id` varchar(100) NULL,
  `slot_number` int(11) NOT NULL,
  `picked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `status` enum('available','picked','winner','loser') DEFAULT 'available',
  PRIMARY KEY (`slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- add raffle_enabled to houses
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'houses' AND COLUMN_NAME = 'raffle_enabled') = 0,
    "ALTER TABLE `houses` ADD COLUMN `raffle_enabled` tinyint(1) DEFAULT 0",
    'SELECT "column houses.raffle_enabled exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- extend ballot_control for raffle settings
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'ballot_control' AND COLUMN_NAME = 'raffle_mode') = 0,
    "ALTER TABLE `ballot_control` ADD COLUMN `raffle_mode` tinyint(1) DEFAULT 0",
    'SELECT "column ballot_control.raffle_mode exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'ballot_control' AND COLUMN_NAME = 'min_applicants_for_raffle') = 0,
    "ALTER TABLE `ballot_control` ADD COLUMN `min_applicants_for_raffle` int(11) DEFAULT 2",
    'SELECT "column ballot_control.min_applicants_for_raffle exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2026-02-17: add is_billable to service_requests (idempotent)
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'service_requests' AND COLUMN_NAME = 'is_billable') = 0,
    'ALTER TABLE service_requests ADD COLUMN is_billable TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT "column is_billable exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
UPDATE service_requests SET is_billable = 0 WHERE bill_amount IS NULL OR bill_amount = 0;

-- 2026-02-18: normalize legacy status text (idempotent updates)
START TRANSACTION;
UPDATE applications
SET status = 'Unsuccessful'
WHERE LOWER(REPLACE(COALESCE(status, ''), '_', ' ')) = 'not successful';
UPDATE balloting
SET status = 'Unsuccessful'
WHERE LOWER(REPLACE(COALESCE(status, ''), '_', ' ')) = 'not successful';
COMMIT;

-- 2026-02-20_fix_raffle_slots_placeholders: make raffle_slots.applicant_id nullable and clear placeholders
-- Run only if raffle_slots exists
SELECT COUNT(*) INTO @__has_slots
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raffle_slots';
SET @__sql = IF(@__has_slots = 1,
  'ALTER TABLE raffle_slots MODIFY applicant_id VARCHAR(100) NULL',
  'SELECT "skip raffle_slots"');
PREPARE __stmt FROM @__sql; EXECUTE __stmt; DEALLOCATE PREPARE __stmt;
SET @__sql2 = IF(@__has_slots = 1,
  "UPDATE raffle_slots SET applicant_id = NULL WHERE status = 'available'",
  'SELECT "skip update"');
PREPARE __stmt2 FROM @__sql2; EXECUTE __stmt2; DEALLOCATE PREPARE __stmt2;

-- 2026-02-23: standardize status casing and convert ballot_no formats
START TRANSACTION;
ALTER TABLE applications MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending';
UPDATE applications SET status = 'Pending' WHERE status IS NULL OR TRIM(status) = '';
UPDATE applications SET status = 'Applied' WHERE LOWER(TRIM(status)) = 'applied';
UPDATE applications SET status = 'Pending' WHERE LOWER(TRIM(status)) = 'pending' OR LOWER(TRIM(status)) = 'open';
UPDATE applications SET status = 'Approved' WHERE LOWER(TRIM(status)) = 'approved';
UPDATE applications SET status = 'Rejected' WHERE LOWER(TRIM(status)) = 'rejected';
UPDATE applications SET status = 'Cancelled' WHERE LOWER(TRIM(status)) = 'cancelled';
UPDATE applications SET status = 'Allocated' WHERE LOWER(TRIM(status)) = 'allocated';
UPDATE applications SET status = 'Won' WHERE LOWER(TRIM(status)) = 'won';
UPDATE applications SET status = 'Not Successful' WHERE LOWER(REPLACE(REPLACE(COALESCE(status,''), '_', ' '), '  ', ' ')) IN ('unsuccessful', 'not successful', 'not_successful');
ALTER TABLE balloting MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending';
UPDATE balloting SET status = 'Pending' WHERE status IS NULL OR TRIM(status) = '' OR LOWER(TRIM(status)) IN ('pending','open');
UPDATE balloting SET status = 'Won' WHERE LOWER(TRIM(status)) = 'won';
UPDATE balloting SET status = 'Not Successful' WHERE LOWER(REPLACE(REPLACE(COALESCE(status,''), '_', ' '), '  ', ' ')) IN ('unsuccessful', 'not successful', 'not_successful');
UPDATE balloting SET ballot_no = CONCAT('Slot ', SUBSTRING_INDEX(ballot_no, 'S', -1)) WHERE ballot_no LIKE '%-S%';
COMMIT;

-- 2026-02-23: make service_requests.is_billable nullable and recompute (idempotent)
ALTER TABLE service_requests MODIFY COLUMN is_billable TINYINT(1) NULL DEFAULT NULL;
UPDATE service_requests s
LEFT JOIN bills b ON b.service_id = s.service_id
SET s.is_billable = CASE
  WHEN b.bill_id IS NOT NULL THEN 1
  WHEN s.bill_amount IS NOT NULL AND s.bill_amount > 0 THEN 1
  WHEN s.details LIKE '%[Marked not billable by admin]%' THEN 0
  WHEN s.is_billable = 0 THEN 0
  ELSE NULL
END;

-- 2026-02-23_backfill_applications_house_no_from_raffle_ballots (safe update)
START TRANSACTION;
UPDATE applications ap
JOIN (
  SELECT
    b.applicant_id,
    TRIM(LOWER(b.category)) AS cat_key,
    SUBSTRING_INDEX(GROUP_CONCAT(b.house_id ORDER BY b.date_of_ballot DESC), ',', 1) AS latest_house_id
  FROM balloting b
  GROUP BY b.applicant_id, TRIM(LOWER(b.category))
) lb
  ON lb.applicant_id = ap.applicant_id
 AND lb.cat_key = TRIM(LOWER(ap.category))
JOIN houses h
  ON h.house_id = lb.latest_house_id
SET ap.house_no = h.house_no
WHERE (
  ap.house_no LIKE 'Raffle Slot %'
  OR ap.house_no IS NULL
  OR TRIM(ap.house_no) = ''
)
AND h.house_no IS NOT NULL
AND TRIM(h.house_no) <> '';
COMMIT;

-- 2026-02-25_remove_duplicate_columns: drop duplicate columns from applicants
ALTER TABLE applicants DROP COLUMN `name of next of kin`;
ALTER TABLE applicants DROP COLUMN `next of kin contact`;
ALTER TABLE applicants DROP COLUMN `ballot_number`;

-- 2026-03-02: create post_forfeit_requests table
CREATE TABLE IF NOT EXISTS post_forfeit_requests (
  id INT NOT NULL AUTO_INCREMENT,
  request_id VARCHAR(40) NOT NULL UNIQUE,
  applicant_id VARCHAR(32) NOT NULL,
  ballot_id VARCHAR(64) DEFAULT NULL,
  application_id VARCHAR(64) DEFAULT NULL,
  reason TEXT NOT NULL,
  attachment VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_id VARCHAR(32) DEFAULT NULL,
  decision_notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX idx_applicant (applicant_id),
  INDEX idx_status (status)
);

-- 2026-02-15_backfill_tenants_role: set applicants.role='tenant' where referenced by tenants
UPDATE applicants a
JOIN tenants t ON a.applicant_id = t.applicant_id
SET a.role = 'tenant'
WHERE a.role IS NULL OR a.role <> 'tenant';

-- 2026-02-15_add_statuses_to_bills: add statuses column if missing
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'bills' AND COLUMN_NAME = 'statuses') = 0,
    "ALTER TABLE bills ADD COLUMN statuses VARCHAR(50) NOT NULL DEFAULT 'active'",
    'SELECT "column bills.statuses exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of appended migrations

