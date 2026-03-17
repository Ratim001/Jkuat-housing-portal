-- Add role and photo columns to applicants
SET @schema = DATABASE();

-- role
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'role') = 0,
    "ALTER TABLE applicants ADD COLUMN role ENUM('applicant','tenant') NOT NULL DEFAULT 'applicant'",
    'SELECT "column role exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- photo
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'photo') = 0,
    'ALTER TABLE applicants ADD COLUMN photo VARCHAR(255) DEFAULT NULL',
    'SELECT "column photo exists"'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
