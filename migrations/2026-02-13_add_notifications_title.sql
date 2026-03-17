-- Migration: add title column to notifications
SET @schema = DATABASE();

SET @sql = (
	SELECT IF(
		(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'title') = 0,
		'ALTER TABLE notifications ADD COLUMN title VARCHAR(100) NULL AFTER recipient_type',
		'SELECT "column notifications.title exists"'
	)
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
