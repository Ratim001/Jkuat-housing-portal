-- Make service_requests.is_billable support an "undecided" (NULL) state.
-- UX requirement:
--  - Blank until admin decides
--  - "Not Billable" => 0
--  - Billing => 1

-- 1) Allow NULL and default NULL
ALTER TABLE service_requests
  MODIFY COLUMN is_billable TINYINT(1) NULL DEFAULT NULL;

-- 2) Recompute billable decision for existing rows:
--    - If a bill exists (or bill_amount > 0), mark billable = 1
--    - If admin explicitly marked not billable (note appended by UI), mark billable = 0
--    - Else leave undecided (NULL)
UPDATE service_requests s
LEFT JOIN bills b ON b.service_id = s.service_id
SET s.is_billable = CASE
  WHEN b.bill_id IS NOT NULL THEN 1
  WHEN s.bill_amount IS NOT NULL AND s.bill_amount > 0 THEN 1
  WHEN s.details LIKE '%[Marked not billable by admin]%' THEN 0
  WHEN s.is_billable = 0 THEN 0
  ELSE NULL
END;
