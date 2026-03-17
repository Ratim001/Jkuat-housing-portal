-- Backfill applicants.role based on existing tenants
-- Sets role='tenant' for any applicants referenced by the tenants table.
UPDATE applicants a
JOIN tenants t ON a.applicant_id = t.applicant_id
SET a.role = 'tenant'
WHERE a.role IS NULL OR a.role <> 'tenant';
