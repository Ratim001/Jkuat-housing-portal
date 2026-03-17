-- Migration: Standardize status casing and store raffle ballot numbers as Slot N
-- Date: 2026-02-23
-- Goal:
-- 1) Make applications.status accept Title Case workflow values (Applied, Pending, Won, Not Successful)
--    by converting to VARCHAR.
-- 2) Normalize existing applications + balloting status values.
-- 3) Convert existing raffle ballot_no values like DRAWxxxx-S2 into Slot 2.
--
-- Notes:
-- - This is intended for MySQL 8+.
-- - If you rely on ENUM constraints for applications.status, this intentionally relaxes it.

START TRANSACTION;

-- 1) applications.status: convert to VARCHAR so Title Case values persist
ALTER TABLE applications
  MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending';

-- Normalize applications.status (existing data)
UPDATE applications
SET status = 'Pending'
WHERE status IS NULL OR TRIM(status) = '';

UPDATE applications SET status = 'Applied' WHERE LOWER(TRIM(status)) = 'applied';
UPDATE applications SET status = 'Pending' WHERE LOWER(TRIM(status)) = 'pending' OR LOWER(TRIM(status)) = 'open';
UPDATE applications SET status = 'Approved' WHERE LOWER(TRIM(status)) = 'approved';
UPDATE applications SET status = 'Rejected' WHERE LOWER(TRIM(status)) = 'rejected';
UPDATE applications SET status = 'Cancelled' WHERE LOWER(TRIM(status)) = 'cancelled';
UPDATE applications SET status = 'Allocated' WHERE LOWER(TRIM(status)) = 'allocated';
UPDATE applications SET status = 'Won' WHERE LOWER(TRIM(status)) = 'won';

-- Map legacy loser values to Not Successful
UPDATE applications
SET status = 'Not Successful'
WHERE LOWER(REPLACE(REPLACE(COALESCE(status,''), '_', ' '), '  ', ' ')) IN ('unsuccessful', 'not successful', 'not_successful');

-- 2) balloting.status: ensure consistent Title Case values
-- (If your schema already uses VARCHAR, this is a safe no-op; if ENUM, this relaxes it.)
ALTER TABLE balloting
  MODIFY COLUMN status VARCHAR(50) DEFAULT 'Pending';

UPDATE balloting
SET status = 'Pending'
WHERE status IS NULL OR TRIM(status) = '' OR LOWER(TRIM(status)) IN ('pending','open');

UPDATE balloting SET status = 'Won' WHERE LOWER(TRIM(status)) = 'won';
UPDATE balloting
SET status = 'Not Successful'
WHERE LOWER(REPLACE(REPLACE(COALESCE(status,''), '_', ' '), '  ', ' ')) IN ('unsuccessful', 'not successful', 'not_successful');

-- 3) Convert existing raffle ballot_no format: DRAWxxxx-S2 -> Slot 2
-- Only touches rows that match the old pattern.
UPDATE balloting
SET ballot_no = CONCAT('Slot ', SUBSTRING_INDEX(ballot_no, 'S', -1))
WHERE ballot_no LIKE '%-S%';

COMMIT;
