-- migrations/2026-02-11_add_applicant_profile_fields.sql
-- Purpose: Add profile fields and tokens to applicants table and ensure unique indexes
-- Author: repo automation / commit: migrations: add applicant profile fields

-- Add columns if missing (idempotent)
SET @schema = DATABASE();

-- name
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'name') = 0,
    'ALTER TABLE applicants ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT ""',
    'SELECT "column name exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- email
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'email') = 0,
    'ALTER TABLE applicants ADD COLUMN `email` VARCHAR(255) NOT NULL DEFAULT ""',
    'SELECT "column email exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- contact
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'contact') = 0,
    'ALTER TABLE applicants ADD COLUMN `contact` VARCHAR(50) NOT NULL DEFAULT ""',
    'SELECT "column contact exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- next_of_kin_name
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'next_of_kin_name') = 0,
    'ALTER TABLE applicants ADD COLUMN `next_of_kin_name` VARCHAR(255) NOT NULL DEFAULT ""',
    'SELECT "column next_of_kin_name exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- next_of_kin_contact
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'next_of_kin_contact') = 0,
    'ALTER TABLE applicants ADD COLUMN `next_of_kin_contact` VARCHAR(50) NOT NULL DEFAULT ""',
    'SELECT "column next_of_kin_contact exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- is_email_verified
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'is_email_verified') = 0,
    'ALTER TABLE applicants ADD COLUMN `is_email_verified` TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT "column is_email_verified exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- email_verification_token
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'email_verification_token') = 0,
    'ALTER TABLE applicants ADD COLUMN `email_verification_token` VARCHAR(255) NULL',
    'SELECT "column email_verification_token exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- password_reset_token
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'password_reset_token') = 0,
    'ALTER TABLE applicants ADD COLUMN `password_reset_token` VARCHAR(255) NULL',
    'SELECT "column password_reset_token exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- password_reset_expires
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND COLUMN_NAME = 'password_reset_expires') = 0,
    'ALTER TABLE applicants ADD COLUMN `password_reset_expires` DATETIME NULL',
    'SELECT "column password_reset_expires exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure unique indexes on pf_no and username (create only if missing)
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND INDEX_NAME = 'ux_applicants_pf_no') = 0,
    'ALTER TABLE applicants ADD UNIQUE INDEX ux_applicants_pf_no (pf_no)',
    'SELECT "index ux_applicants_pf_no exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'applicants' AND INDEX_NAME = 'ux_applicants_username') = 0,
    'ALTER TABLE applicants ADD UNIQUE INDEX ux_applicants_username (username)',
    'SELECT "index ux_applicants_username exists"'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- End of migration

-- End of migration
