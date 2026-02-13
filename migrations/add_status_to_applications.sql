-- Add status column to applications table if it doesn't exist
SET @schema = DATABASE();

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applications' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE applications ADD COLUMN status VARCHAR(50) DEFAULT "Pending"',
    'SELECT "column status exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- End of migration
