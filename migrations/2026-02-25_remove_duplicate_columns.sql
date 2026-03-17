-- Migration: Remove duplicate and unused columns from applicants table
-- Date: 2026-02-25
-- Description: Drop duplicate next-of-kin columns (with spaces, containing NULL values)
--              and ballot_number column (unused) from applicants table

-- Safety: This migration only affects the applicants table
-- All three columns being dropped contain only NULL values and are not referenced in the application code

-- Drop the duplicate 'name of next of kin' column (with spaces) - if it exists
ALTER TABLE applicants DROP COLUMN IF EXISTS `name of next of kin`;

-- Drop the duplicate 'next of kin contact' column (with spaces) - if it exists
ALTER TABLE applicants DROP COLUMN IF EXISTS `next of kin contact`;

-- Drop the unused 'ballot_number' column - if it exists
ALTER TABLE applicants DROP COLUMN IF EXISTS `ballot_number`;

-- Confirmation: Migration completed successfully
-- To verify: SELECT * FROM applicants LIMIT 1;
