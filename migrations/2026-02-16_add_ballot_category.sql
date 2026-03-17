-- Migration: add category column to balloting and composite unique index
-- Adds a `category` column and a composite unique index on (category, ballot_no)
-- NOTE: Uses INFORMATION_SCHEMA checks to avoid errors when re-running.

-- Make the column add idempotent for both MySQL and MariaDB
-- (We do this because some MySQL versions do not support ADD COLUMN IF NOT EXISTS.)
SET @schema = DATABASE();
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'balloting' AND COLUMN_NAME = 'category') = 0,
    'ALTER TABLE balloting ADD COLUMN category VARCHAR(80) DEFAULT NULL',
    'SELECT "column balloting.category exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @__idx_exists FROM INFORMATION_SCHEMA.STATISTICS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'balloting' AND INDEX_NAME = 'idx_ballot_category_no';
SET @__sql = IF(@__idx_exists = 0, 'ALTER TABLE balloting ADD UNIQUE INDEX idx_ballot_category_no (category, ballot_no)', 'SELECT "idx_exists"');
PREPARE __stmt FROM @__sql;
EXECUTE __stmt;
DEALLOCATE PREPARE __stmt;

-- End migration
