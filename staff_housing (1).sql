-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 08, 2025 at 01:29 PM
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

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `applicant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each applicant',
  `pf_no` varchar(100) NOT NULL COMMENT 'PF number of the applicant',
  `name` varchar(100) NOT NULL COMMENT 'Name of the applicant',
  `email` varchar(100) NOT NULL COMMENT 'Email of the applicant',
  `contact` varchar(100) NOT NULL COMMENT 'Contact of the applicant',
  `name of next of kin` varchar(100) NOT NULL COMMENT 'Name of the next of kin',
  `next of kin contact` varchar(100) NOT NULL COMMENT 'Name of the next of kin',
  `password` varchar(225) NOT NULL COMMENT 'The applicant’s hashed password',
  `username` varchar(100) NOT NULL COMMENT 'username of the applicant',
  `date_created` datetime DEFAULT current_timestamp() COMMENT 'Date the applicant was created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`applicant_id`, `pf_no`, `name`, `email`, `contact`, `name of next of kin`, `next of kin contact`, `password`, `username`, `date_created`) VALUES
('A001', '3040', '', '', '', '', '', '$2y$10$ngbTwPgAc7/U9tKW9SA4.OYhGO8h5L16jUgLBHghRH.XHmP0fZahW', 'Maxwell', '2025-08-04 10:41:26'),
('A002', '3035', '', '', '', '', '', '$2y$10$Tvb4W3THp1DLdWRnXT57.eWi4xQshg/zSpYsAZyKrqFFf4llOuQvy', 'Jack', '2025-08-04 10:41:26'),
('A003', '3099', '', '', '', '', '', '$2y$10$xLNiCLelELTh1zbV4kNwAeghSWxWGOTf42UATx1AWROWe/JnWiQ/O', 'Andrew', '2025-08-04 10:41:26'),
('A004', '3098', '', '', '', '', '', '$2y$10$rYOst2/BF7xdeC.DsjjTgOzrYu6jWThisQXL1s09bmY61IXxuFoLO', 'Naruto', '2025-08-04 10:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each application',
  `applicant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each applicant as foreign key',
  `category` varchar(100) NOT NULL COMMENT 'The type of house they are applying for',
  `house_no` varchar(100) NOT NULL COMMENT 'House number of the house applied for',
  `status` enum('declined','accepted','pending','') NOT NULL COMMENT 'Current status of the application\r\n(declined, accepted)\r\n',
  `date` datetime NOT NULL COMMENT 'Date the application was applied'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `applicant_id`, `category`, `house_no`, `status`, `date`) VALUES
('AP002', 'A001', '4 Bedroom', '401', 'declined', '2025-07-16 00:00:00'),
('AP004', 'A001', '3 Bedroom', '301', '', '2025-07-17 00:00:00'),
('AP007', 'A001', '1 Bedroom', '101', '', '2025-07-21 00:00:00'),
('AP008', 'A001', '4 Bedroom', '409', 'pending', '2025-07-21 00:00:00'),
('AP009', 'A002', '1 Bedroom', '101', '', '2025-07-24 00:00:00'),
('AP010', 'A003', '1 Bedroom', '105', '', '2025-07-28 00:00:00'),
('AP011', 'A004', '2 Bedroom', '208', 'pending', '2025-07-28 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `balloting`
--

CREATE TABLE `balloting` (
  `ballot_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each ballot entry',
  `applicant_id` varchar(100) NOT NULL COMMENT 'References the applicant from the Applicant table',
  `house_id` varchar(100) NOT NULL COMMENT 'References the house from the House table',
  `ballot_no` varchar(100) NOT NULL COMMENT 'A unique ballot number',
  `date_of_ballot` datetime NOT NULL COMMENT 'The date on which the ballot will be conducted',
  `status` enum('declined','accepted','','') NOT NULL COMMENT 'Status of the ballot (declined, accepted)'
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
-- Table structure for table `bills`
--

CREATE TABLE `bills` (
  `bill_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each bill',
  `service_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each service',
  `type_of_bill` varchar(100) NOT NULL COMMENT 'The of bill (from a billable service requested by a tenant)',
  `amount` int(11) NOT NULL COMMENT 'The amount to be paid to settle the bill',
  `date_billed` datetime NOT NULL COMMENT 'The date a tenant is billed',
  `date_settled` date DEFAULT NULL,
  `status` enum('paid','not paid','','') NOT NULL COMMENT 'The status of the bill (paid, not paid)',
  `statuses` enum('active','disputed') DEFAULT 'active' COMMENT 'current status of the bill'
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

CREATE TABLE `bill_update_logs` (
  `bill_update_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each bill updated',
  `user_id` varchar(100) NOT NULL COMMENT 'Unique identifier of the user (the admin involved in the update of the house)',
  `bill_id` varchar(100) NOT NULL COMMENT 'Unique identifier for the bills',
  `device_type` varchar(100) NOT NULL COMMENT 'The identification and type of device involved in the update (computer, laptop, phone)',
  `details` text NOT NULL COMMENT 'The whole details of what the update was all about (if the bill was settled, if the amount to be paid was increased, if the bill to the tenant was revoked)',
  `old_amount` decimal(10,2) NOT NULL COMMENT 'The previous amount before update',
  `new_amount` decimal(10,2) NOT NULL COMMENT 'New amount after updating the bill amount',
  `date_updated` datetime NOT NULL COMMENT 'The date and the exact time the bill was updated'
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

CREATE TABLE `houses` (
  `house_id` varchar(100) NOT NULL COMMENT 'Unique identifier for the houses',
  `house_no` varchar(100) NOT NULL COMMENT 'unique house number for each house',
  `category` varchar(100) NOT NULL COMMENT 'The type of house according to number of bedrooms it has',
  `date` datetime NOT NULL COMMENT 'The date each house was created',
  `creator` varchar(100) NOT NULL COMMENT 'The one responsible for the creation of the house',
  `rent` int(11) NOT NULL COMMENT 'The amount to be paid as rent for each house',
  `status` enum('vacant','occupied','reserved','','') NOT NULL COMMENT 'Current availability status of each house (vacant, occupied, reserved)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `houses`
--

INSERT INTO `houses` (`house_id`, `house_no`, `category`, `date`, `creator`, `rent`, `status`) VALUES
('H1001', '101', '1 Bedroom', '2025-08-05 00:00:00', 'STEPHEN', 18588, 'reserved'),
('H2001', '201', '2 Bedroom', '2025-08-05 00:00:00', 'STEPHEN', 21000, 'reserved'),
('H2002', '202', '2 Bedroom', '2025-07-28 00:00:00', 'STEPHEN', 15700, 'vacant'),
('H3001', '301', '3 Bedroom', '2025-07-28 00:00:00', 'STEPHEN', 17300, 'vacant'),
('H4001', '404', '4 Bedroom', '2025-08-05 00:00:00', 'STEPHEN', 15005, 'reserved'),
('H4002', '401', '4 Bedroom', '2025-08-05 00:00:00', 'STEPHEN', 20511, 'occupied');

-- --------------------------------------------------------

--
-- Table structure for table `house_update_logs`
--

CREATE TABLE `house_update_logs` (
  `house_update_id` varchar(100) NOT NULL,
  `user_id` varchar(100) NOT NULL,
  `house_id` varchar(100) NOT NULL,
  `device_type` varchar(255) DEFAULT NULL,
  `details` text NOT NULL,
  `date_updated` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `house_update_logs`
--

INSERT INTO `house_update_logs` (`house_update_id`, `user_id`, `house_id`, `device_type`, `details`, `date_updated`) VALUES
('HU001', 'user004', 'H1001', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'House updated: Rent: KES 18555 → KES 18588 | Status: occupied → reserved', '2025-08-05 15:58:23'),
('HU002', 'user004', 'H4002', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 'House updated: Rent: KES 20500 → KES 20511 | Status: reserved → Occupied', '2025-08-05 15:59:03');

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

CREATE TABLE `notices` (
  `notice_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each notice sent',
  `tenant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each tenant',
  `details` varchar(500) NOT NULL COMMENT 'The content of the notice sent',
  `date_sent` datetime NOT NULL COMMENT 'The date the notice was sent ',
  `date_received` datetime NOT NULL COMMENT 'The date the notice was received ',
  `notice_end_date` datetime NOT NULL COMMENT 'The date a tenant prefers to vacate',
  `status` enum('active','revoked') NOT NULL COMMENT 'Current status of the notice'
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

CREATE TABLE `notifications` (
  `notification_id` varchar(100) NOT NULL COMMENT 'Unique identifier of each notification',
  `user_id` varchar(100) NOT NULL COMMENT 'Sender’s id as a foreign key',
  `recipient_type` varchar(100) NOT NULL COMMENT 'Type of recipient (tenant or applicant)',
  `recipient_id` varchar(100) NOT NULL COMMENT 'The recipient’s id',
  `message` varchar(300) NOT NULL COMMENT 'The details of the message ',
  `date_sent` datetime NOT NULL COMMENT 'Date notification sent',
  `date_received` datetime NOT NULL COMMENT 'Date notification received',
  `status` enum('unread','read') NOT NULL DEFAULT 'unread' COMMENT 'Status of notification'
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
('NT6893', 'user002', 'tenant', 'T003', 's', '2025-08-06 15:50:48', '2025-08-06 15:50:48', 'unread');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `service_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each service requested',
  `tenant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each tenant',
  `type_of_service` varchar(200) NOT NULL COMMENT 'The type of service requested (a tenant will be able to input the particular service)',
  `bill_amount` int(11) NOT NULL COMMENT 'The amount to be paid if the service requested is deemed payable',
  `date` datetime NOT NULL COMMENT 'The date the service was requested',
  `status` enum('pending','done','','') NOT NULL COMMENT 'The status of the service requested (pending, done)',
  `details` varchar(100) NOT NULL COMMENT 'Details for the service requested'
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

CREATE TABLE `tenants` (
  `tenant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each tenant',
  `applicant_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each applicant',
  `house_no` varchar(100) NOT NULL COMMENT 'A unique number for each house',
  `move_in_date` datetime NOT NULL COMMENT 'The date a tenant moves in to occupy the house',
  `move_out_date` datetime NOT NULL COMMENT 'The date a tenant decides to vacate the house',
  `status` enum('active','terminated') NOT NULL COMMENT 'Current status of the tenant'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`tenant_id`, `applicant_id`, `house_no`, `move_in_date`, `move_out_date`, `status`) VALUES
('T001', 'A002', '101', '2025-07-25 00:00:00', '0000-00-00 00:00:00', 'active'),
('T002', 'A001', '301', '2025-07-25 00:00:00', '0000-00-00 00:00:00', 'active'),
('T003', 'A003', '105', '2025-07-28 00:00:00', '2025-08-06 15:46:24', 'terminated');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(100) NOT NULL COMMENT 'Unique identifier of the user',
  `pf_no` varchar(100) NOT NULL COMMENT 'PF number of the user',
  `username` varchar(225) NOT NULL COMMENT 'The username of the user',
  `name` varchar(225) NOT NULL COMMENT 'The name of the user',
  `email` varchar(255) NOT NULL COMMENT 'The email of the user',
  `role` varchar(100) NOT NULL COMMENT 'Role of the user (ICT admin, CS admin, SUPER admin)',
  `password` varchar(225) NOT NULL COMMENT 'Hashed Password of the user',
  `date_created` datetime NOT NULL COMMENT 'The date a user was created',
  `status` varchar(100) NOT NULL COMMENT 'Current state of the user (active,\r\n  De active)\r\n'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `pf_no`, `username`, `name`, `email`, `role`, `password`, `date_created`, `status`) VALUES
('user001', '3965', 'STEPHEN ', 'stephen kariuki', 'karstephen016@gmail.com', 'ICT Admin', '$2y$10$9veav2fhYjJIjv48DMg1hORvlxQsun2v2qdrdrCNPzClNQKfWcCFK', '2025-07-10 15:22:23', 'Deactivated'),
('user002', '3967', 'JOEL', 'JOEL NGANGA', 'njoroge.stephen2022@students.jkuat.ac.ke', 'CS Admin', '$2y$10$0seCiE7xgOcdXgn.g9ufbuYqtr1BDPOIwLicOX1arx.FDxynANJq2', '2025-07-10 15:22:47', 'Active'),
('user003', '3968', 'steve', 'maina', 'steve@gmail.com', 'ICT Admin', '$2y$10$QShfkmZgh5fO.u/ta/rAoegvs49FJVTddKkRp.QFPrTeL195Mtlu2', '2025-07-11 08:54:25', 'Active'),
('user004', '3969', 'Mark', 'Mark Maina', 'maish@gmail.com', 'CS Admin', '$2y$10$iztdRQoYL2lSex5Z.mC1i.19RnA.nQu5mzdNsuJRnDiEEkyKPxd6C', '2025-07-15 11:36:26', 'Active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`applicant_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `applicationt_id_ibfk_1` (`applicant_id`);

--
-- Indexes for table `balloting`
--
ALTER TABLE `balloting`
  ADD PRIMARY KEY (`ballot_id`),
  ADD UNIQUE KEY `unique ballot no` (`ballot_no`),
  ADD KEY `applicant_id_FK` (`applicant_id`),
  ADD KEY `house_id_FK` (`house_id`);

--
-- Indexes for table `bills`
--
ALTER TABLE `bills`
  ADD PRIMARY KEY (`bill_id`),
  ADD KEY `service_id_FK` (`service_id`);

--
-- Indexes for table `bill_update_logs`
--
ALTER TABLE `bill_update_logs`
  ADD PRIMARY KEY (`bill_update_id`);

--
-- Indexes for table `houses`
--
ALTER TABLE `houses`
  ADD PRIMARY KEY (`house_id`);

--
-- Indexes for table `house_update_logs`
--
ALTER TABLE `house_update_logs`
  ADD PRIMARY KEY (`house_update_id`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`notice_id`),
  ADD KEY `tenant_id_FK2` (`tenant_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id_FK` (`user_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`tenant_id`),
  ADD KEY `applicant_id_FK2` (`applicant_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

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
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `tenant_id_FK` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`tenant_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `applicant_id_FK2` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
