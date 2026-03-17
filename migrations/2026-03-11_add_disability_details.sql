-- Migration: add disability_details to applicants table

SET @schema = DATABASE();
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'disability_details') = 0,
    'ALTER TABLE applicants ADD COLUMN disability_details LONGTEXT DEFAULT NULL',
    'SELECT "column applicants.disability_details exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- End migration
