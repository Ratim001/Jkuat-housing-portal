-- Migration: Convert legacy 'Not Successful' / 'not_successful' values to canonical 'Unsuccessful'
-- Date: 2026-02-18
-- Notes: This updates both the `applications` and `balloting` tables where older statuses
-- used either the spaced or underscored variants. The change is idempotent.

START TRANSACTION;

-- Normalize applications.status
UPDATE applications
SET status = 'Unsuccessful'
WHERE LOWER(REPLACE(COALESCE(status, ''), '_', ' ')) = 'not successful';

-- Normalize balloting.status (if the table/column exists)
-- If your environment lacks the `balloting` table this will harmlessly fail; run inside a DB client that reports errors.
UPDATE balloting
SET status = 'Unsuccessful'
WHERE LOWER(REPLACE(COALESCE(status, ''), '_', ' ')) = 'not successful';

COMMIT;

-- Optional verification queries:
-- SELECT status, COUNT(*) FROM applications GROUP BY status;
-- SELECT status, COUNT(*) FROM balloting GROUP BY status;
