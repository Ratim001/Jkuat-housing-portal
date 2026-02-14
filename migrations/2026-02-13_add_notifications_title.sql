-- Migration: add title column to notifications
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title VARCHAR(100) NULL AFTER recipient_type;
