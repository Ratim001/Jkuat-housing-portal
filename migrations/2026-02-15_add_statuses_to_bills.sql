-- Add optional statuses column to bills for admin/applicant UI (active/disputed)
SET @schema = DATABASE();

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'bills' AND COLUMN_NAME = 'statuses') = 0,
    "ALTER TABLE bills ADD COLUMN statuses VARCHAR(50) NOT NULL DEFAULT 'active'",
    'SELECT "column bills.statuses exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
