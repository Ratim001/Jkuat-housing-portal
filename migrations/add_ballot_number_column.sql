-- Migration: add ballot_number column to applicants
-- Adds a ballot_number column and a unique index to prevent duplicates.
-- NOTE: Uses INFORMATION_SCHEMA checks so it is safe to re-run.

-- Add column if missing
SET @schema = DATABASE();

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'ballot_number') = 0,
    'ALTER TABLE applicants ADD COLUMN ballot_number VARCHAR(20) DEFAULT NULL',
    'SELECT "column ballot_number exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create unique index only if it does not already exist.
-- Use INFORMATION_SCHEMA to check then run dynamic SQL to avoid duplicate-key errors.
SELECT COUNT(*) INTO @__idx_exists FROM INFORMATION_SCHEMA.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applicants' AND INDEX_NAME = 'idx_ballot_number';
SET @__sql = IF(@__idx_exists = 0, 'ALTER TABLE applicants ADD UNIQUE INDEX idx_ballot_number (ballot_number)', 'SELECT "idx_exists"');
PREPARE __stmt FROM @__sql;
EXECUTE __stmt;
DEALLOCATE PREPARE __stmt;

-- End migration
