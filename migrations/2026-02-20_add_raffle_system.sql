-- Migration: Add Raffle/Draw Ballot System
-- Purpose: Support raffle-style balloting where multiple applicants compete for one house
-- Date: 2026-02-20

-- Table to store raffle draws for houses
CREATE TABLE IF NOT EXISTS `raffle_draws` (
  `draw_id` varchar(100) NOT NULL COMMENT 'Unique identifier for each raffle draw',
  `house_id` varchar(100) NOT NULL COMMENT 'House being raffled',
  `category` varchar(100) NOT NULL COMMENT 'Category of the house',
  `total_slots` int(11) NOT NULL COMMENT 'Total number of applicants competing',
  `draw_date` datetime DEFAULT NULL COMMENT 'When the draw takes place',
  `winning_slot` int(11) DEFAULT NULL COMMENT 'The winning slot number',
  `winning_applicant_id` varchar(100) DEFAULT NULL COMMENT 'The applicant who won',
  `status` enum('open','closed','completed') DEFAULT 'open' COMMENT 'Status of the raffle',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(100) DEFAULT NULL COMMENT 'Admin who initiated the raffle',
  PRIMARY KEY (`draw_id`),
  KEY `house_id_FK` (`house_id`),
  KEY `winning_applicant_FK` (`winning_applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table to store applicant slots in a raffle draw
CREATE TABLE IF NOT EXISTS `raffle_slots` (
  `slot_id` varchar(100) NOT NULL COMMENT 'Unique identifier for the slot',
  `draw_id` varchar(100) NOT NULL COMMENT 'References the raffle draw',
  `applicant_id` varchar(100) NULL COMMENT 'References the applicant (NULL until picked)',
  `slot_number` int(11) NOT NULL COMMENT 'The slot number chosen by applicant (1 onwards)',
  `picked_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'When the applicant picked their slot',
  `status` enum('available','picked','winner','loser') DEFAULT 'available' COMMENT 'Status of the slot',
  PRIMARY KEY (`slot_id`),
  UNIQUE KEY `unique_draw_applicant` (`draw_id`, `applicant_id`),
  UNIQUE KEY `unique_draw_slot` (`draw_id`, `slot_number`),
  KEY `draw_fk` (`draw_id`),
  KEY `applicant_fk` (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Alter houses table to support raffle mode
SET @schema = DATABASE();

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'houses' AND COLUMN_NAME = 'raffle_enabled') = 0,
    "ALTER TABLE `houses` ADD COLUMN `raffle_enabled` tinyint(1) DEFAULT 0 COMMENT 'Whether this house uses raffle (1) or direct ballot (0)'",
    'SELECT "column houses.raffle_enabled exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Alter ballot_control to support raffle draw settings
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'ballot_control' AND COLUMN_NAME = 'raffle_mode') = 0,
    "ALTER TABLE `ballot_control` ADD COLUMN `raffle_mode` tinyint(1) DEFAULT 0 COMMENT 'Enable raffle draws for competing applicants'",
    'SELECT "column ballot_control.raffle_mode exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'ballot_control' AND COLUMN_NAME = 'min_applicants_for_raffle') = 0,
    "ALTER TABLE `ballot_control` ADD COLUMN `min_applicants_for_raffle` int(11) DEFAULT 2 COMMENT 'Minimum applicants to trigger auto-raffle'",
    'SELECT "column ballot_control.min_applicants_for_raffle exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
