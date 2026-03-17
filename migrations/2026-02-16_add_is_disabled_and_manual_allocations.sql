-- Migration: add is_disabled to applicants and manual_allocations table

SET @schema = DATABASE();
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'is_disabled') = 0,
    'ALTER TABLE applicants ADD COLUMN is_disabled TINYINT(1) DEFAULT 0',
    'SELECT "column applicants.is_disabled exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS manual_allocations (
  allocation_id VARCHAR(64) NOT NULL,
  admin_id VARCHAR(64) NOT NULL,
  applicant_id VARCHAR(64) NOT NULL,
  house_no VARCHAR(64) NOT NULL,
  date_allocated DATETIME NOT NULL,
  notes TEXT,
  PRIMARY KEY (allocation_id)
);

-- End migration
