-- Migration: Backfill applications.house_no for raffle-mode ballots
-- Date: 2026-02-23
-- Goal:
-- - Replace applications.house_no values like "Raffle Slot N" (or blank/NULL) with the actual house_no
--   by joining through the balloting table (which stores house_id for the raffle draw).
--
-- Notes:
-- - Safe to run multiple times.
-- - Only updates rows where applications.house_no starts with "Raffle Slot".

START TRANSACTION;

UPDATE applications ap
JOIN (
  /* latest ballot per applicant+category (category normalized for whitespace/case) */
  SELECT
    b.applicant_id,
    TRIM(LOWER(b.category)) AS cat_key,
    SUBSTRING_INDEX(GROUP_CONCAT(b.house_id ORDER BY b.date_of_ballot DESC), ',', 1) AS latest_house_id
  FROM balloting b
  GROUP BY b.applicant_id, TRIM(LOWER(b.category))
) lb
  ON lb.applicant_id = ap.applicant_id
 AND lb.cat_key = TRIM(LOWER(ap.category))
JOIN houses h
  ON h.house_id = lb.latest_house_id
SET ap.house_no = h.house_no
WHERE (
  ap.house_no LIKE 'Raffle Slot %'
  OR ap.house_no IS NULL
  OR TRIM(ap.house_no) = ''
)
AND h.house_no IS NOT NULL
AND TRIM(h.house_no) <> '';

COMMIT;
