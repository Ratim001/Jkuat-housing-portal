-- Add explicit is_billable flag to service_requests
SET @schema = DATABASE();

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'service_requests' AND COLUMN_NAME = 'is_billable') = 0,
    'ALTER TABLE service_requests ADD COLUMN is_billable TINYINT(1) NOT NULL DEFAULT 1',
    'SELECT "column is_billable exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill existing rows: treat zero amount as not billable
UPDATE service_requests SET is_billable = 0 WHERE bill_amount IS NULL OR bill_amount = 0;
