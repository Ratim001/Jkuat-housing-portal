-- Migration: Fix raffle_slots placeholder behavior
-- Date: 2026-02-20
-- Purpose:
-- 1) Make raffle_slots.applicant_id nullable (placeholders have NULL applicant_id)
-- 2) Clear legacy rows where applicant_id was pre-filled while status='available'
--    (this previously caused applicants to see "already picked" and blocked picking).

START TRANSACTION;

-- Only run if raffle_slots exists
SELECT COUNT(*) INTO @__has_slots
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'raffle_slots';

-- Make applicant_id nullable
SET @__sql = IF(@__has_slots = 1,
  'ALTER TABLE raffle_slots MODIFY applicant_id VARCHAR(100) NULL',
  'SELECT "skip raffle_slots"');
PREPARE __stmt FROM @__sql;
EXECUTE __stmt;
DEALLOCATE PREPARE __stmt;

-- Clear legacy placeholder assignments (keep picked/winner/loser intact)
SET @__sql2 = IF(@__has_slots = 1,
  "UPDATE raffle_slots SET applicant_id = NULL WHERE status = 'available'",
  'SELECT "skip update"');
PREPARE __stmt2 FROM @__sql2;
EXECUTE __stmt2;
DEALLOCATE PREPARE __stmt2;

COMMIT;

-- End migration
