# Testing Guide - Application Status Refactoring

This guide provides comprehensive testing steps to verify the refactored status management system where:
- **Applications table** is the single source of truth for per-application status
- **Applicants list** shows computed "Applied"/"Not Applied" based on application count
- **Admin updates** to application status persist via dedicated AJAX endpoints
- **Applicant forfeit** action cancels applications with proper persistence

## Test Setup

1. **Create test data:**
   - Applicant A: Complete profile (name, email, contact)
   - Applicant B: Complete profile
   - Admin account (is_admin = 1)

2. **Ensure database has:**
   - `applications` table with `status` column (values: Pending, Approved, Rejected, Cancelled, Won)
   - `applicants` table with profile fields

## Test Cases

### Test Case 1: Application Creation
**Scenario:** Applicant A creates a new application

**Steps:**
1. Log in as Applicant A
2. Go to applicants.php (Apply for a Vacant House)
3. Fill in application form (Category, House No, Date)
4. Submit application

**Expected Results:**
- ✅ Application created with status='Pending' in `applications` table
- ✅ Application appears in Applicant A's applications list with Status = "Pending"
- ✅ Forfeit button appears for the application
- ✅ In manage_applicants.php / Applicants section, Applicant A shows "Applied" (green)

**Database verification:**
```sql
SELECT * FROM applications WHERE applicant_id = 'APPLICANT_A_ID';
-- Should show: status='Pending'

SELECT COUNT(*) FROM applications WHERE applicant_id = 'APPLICANT_A_ID';
-- Should show: 1 (for "Applied" status in manage_applicants)
```

---

### Test Case 2: Admin Updates Application Status
**Scenario:** Admin changes Applicant A's application from Pending to Approved

**Steps:**
1. Log in as Admin
2. Go to manage_applicants.php
3. Click "Applications" tab
4. Find Applicant A's application
5. Click status dropdown
6. Select "Approved"
7. See success toast notification

**Expected Results:**
- ✅ Dropdown value changes to "Approved"
- ✅ Success toast shows "Status updated to Approved"
- ✅ Status persists after page refresh
- ✅ In applicants.php, Applicant A's application shows Status = "Approved"
- ✅ Forfeit button is hidden (status !== 'Pending')

**Database verification:**
```sql
SELECT status FROM applications WHERE applicant_id = 'APPLICANT_A_ID';
-- Should show: 'Approved'

SELECT status FROM applicants WHERE applicant_id = 'APPLICANT_A_ID';
-- Should NOT change - applicants.status is independent
```

---

### Test Case 3: Admin Updates Status to Multiple Values
**Scenario:** Admin cycles through different status values

**Steps:**
1. In manage_applicants.php / Applications section
2. Update status Approved → Rejected
3. Wait for success notification
4. Update status Rejected → Cancelled
5. Update status Cancelled → Pending
6. Update status Pending → Approved

**Expected Results:**
- ✅ Each update is reflected immediately in the dropdown
- ✅ Status persists after page refresh
- ✅ Toast notification shows for each update
- ✅ No page reload required (pure AJAX)
- ✅ applicants.php always shows current status from `applications.status`

---

### Test Case 4: Applicant Forfeits Pending Application
**Scenario:** Applicant A forfeits their Pending application

**Steps:**
1. Log in as Applicant A
2. Go to applicants.php
3. Find the Pending application
4. Click "Forfeit" button
5. Click "OK" in confirmation dialog
6. Wait for success notification

**Expected Results:**
- ✅ Button changes to "Processing..." during AJAX call
- ✅ Success toast shows "Application forfeited successfully"
- ✅ Button is replaced with "-" (like other non-Pending statuses)
- ✅ After refresh, application still shows status "Cancelled" with "-" in Action column
- ✅ In manage_applicants.php / Applications section, application status shows "Cancelled"

**Database verification:**
```sql
SELECT status FROM applications WHERE application_id = 'APP_ID';
-- Should show: 'Cancelled'
```

---

### Test Case 5: Cannot Forfeit Non-Pending Application
**Scenario:** Applicant tries to forfeit an Approved application

**Steps:**
1. Go to manage_applicants.php
2. Change Applicant A's application to "Approved"
3. Log in as Applicant A
4. Go to applicants.php
5. Application shows "Approved" with "-" instead of Forfeit button
6. (No forfeit possible)

**Expected Results:**
- ✅ Forfeit button only appears for Pending applications
- ✅ For Approved/Rejected/Cancelled/Won, Action column shows "-"

---

### Test Case 6: Applicant Status Independence
**Scenario:** Verify that applicant-level status is not affected by application status changes

**Steps:**
1. In manage_applicants.php / Applicants section, verify Applicant A shows "Applied"
2. In Applications section, change all of Applicant A's applications to "Rejected"
3. Go back to Applicants section
4. Verify Applicant A still shows "Applied" (not affected by application rejections)

**Expected Results:**
- ✅ Applicant status "Applied" is computed from `COUNT(applications) > 0`
- ✅ Applicant status doesn't change based on application status values
- ✅ Only disappears when applicant has ZERO applications in ANY status

**Database verification:**
```sql
SELECT COUNT(*) FROM applications WHERE applicant_id = 'APPLICANT_A_ID';
-- If > 0, applicant shows "Applied" regardless of individual application statuses
```

---

### Test Case 7: Multiple Applications Per Applicant
**Scenario:** Applicant has multiple applications with different statuses

**Steps:**
1. Applicant B creates 3 applications (for different houses)
2. Admin updates statuses: App1→Pending, App2→Approved, App3→Rejected
3. Check Applicant B's applicants.php
4. Check manage_applicants.php / Applications section
5. Check manage_applicants.php / Applicants section

**Expected Results:**
- ✅ applicants.php shows all 3 applications with correct statuses
- ✅ Can only forfeit App1 (Pending)
- ✅ manage_applicants.php / Applications shows all 3 with correct statuses (can change via dropdown)
- ✅ manage_applicants.php / Applicants shows Applicant B as "Applied"

---

### Test Case 8: Error Handling
**Scenario:** Test error cases in AJAX requests

**Steps:**

**8a - Update with invalid status:**
1. Open browser DevTools / Network tab
2. Intercept request to update_application_status.php
3. Send invalid status value (not in Pending/Approved/Rejected/Cancelled/Won)

**Expected Results:**
- ✅ Server returns 400 error
- ✅ Toast shows error message
- ✅ Dropdown reverts to original value
- ✅ Button re-enabled

**8b - Non-admin tries to update status:**
1. Log in as Applicant
2. Open DevTools Console
3. Send POST to update_application_status.php manually
4. Include false admin session

**Expected Results:**
- ✅ Server returns 403 Forbidden
- ✅ Error message in toast: "Unauthorized: Only admins can update application status"

**8c - Applicant tries to forfeit others' application:**
1. Get another applicant's application_id (e.g., from manage_applicants.php)
2. Log in as different applicant
3. Open DevTools Console
4. Send POST to forfeit_application.php with other applicant's app_id

**Expected Results:**
- ✅ Server returns 403 Forbidden
- ✅ Error message: "Unauthorized: You do not own this application"

---

### Test Case 9: Page Refresh Persistence
**Scenario:** Verify all changes persist across page refreshes

**Steps:**
1. Admin updates application status to "Approved"
2. Refresh page (F5)
3. Application still shows "Approved"
4. Applicant forfeits application (status becomes "Cancelled")
5. Refresh page
6. Application still shows "Cancelled" with no Forfeit button

**Expected Results:**
- ✅ All changes read directly from database on each page load
- ✅ No reliance on session variables or cached UI state
- ✅ Toast notifications appear correctly after refresh

---

## Endpoints Verification

### Update Application Status Endpoint
**File:** `php/update_application_status.php`

**Verification:**
```bash
curl -X POST http://localhost/jkuat-housing-portal/php/update_application_status.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "application_id=AP001&status=Approved"
```

**Expected Response (success):**
```json
{ "success": true, "application_id": "AP001", "status": "Approved" }
```

**Expected Response (unauthorized):**
```json
{ "success": false, "error": "Unauthorized: Only admins can update application status" }
```

---

### Forfeit Application Endpoint
**File:** `php/forfeit_application.php`

**Verification:**
```bash
curl -X POST http://localhost/jkuat-housing-portal/php/forfeit_application.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "application_id=AP001"
```

**Expected Response (success):**
```json
{ "success": true, "application_id": "AP001", "status": "Cancelled" }
```

**Expected Response (not owner):**
```json
{ "success": false, "error": "Unauthorized: You do not own this application" }
```

---

## SQL Verification Queries

### Verify Database Schema
```sql
-- Check applications table has status column
DESCRIBE applications;
-- Should show: status VARCHAR(50) DEFAULT 'Pending'

-- Check applicants table structure
DESCRIBE applicants;
-- Should show all profile fields + status column
```

### Verify Data Consistency
```sql
-- All applications should have valid status values
SELECT DISTINCT status FROM applications;
-- Should return: 'Pending', 'Approved', 'Rejected', 'Cancelled', 'Won' (subset)

-- No pending applications should be deleted
SELECT COUNT(*) FROM applications WHERE applicant_id = 'TEST_APPLICANT_ID';
-- Should be accurate count

-- Verify applicant status is NOT synced with application status
SELECT a.applicant_id, a.status as applicant_status, COUNT(ap.application_id) as app_count
FROM applicants a
LEFT JOIN applications ap ON a.applicant_id = ap.applicant_id
GROUP BY a.applicant_id;
-- applicant_status should be independent of app_count
```

---

## Performance Checks

1. **Applicants page load time:** Should be < 1 second
2. **Status dropdown change:** AJAX response < 500ms
3. **Forfeit action:** AJAX response < 500ms
4. **manage_applicants.php:** Should load efficiently with COUNT queries

---

## Rollback Checklist

If issues are discovered, ensure these can be reverted:
- ✅ Commit: "backend: add update_application_status and forfeit_application endpoints"
- ✅ Commit: "feat: integrate update_application_status endpoint in manage_applicants Applications section"
- ✅ Commit: "feat: integrate forfeit_application endpoint in applicants section"
- ✅ Commit: "refactor: show computed Applied/Not Applied status in applicants section"
- ✅ Commit: "refactor: remove status sync handlers, decouple applicant-level status from application-level status"

Each commit is independent and can be selectively reverted if needed.

---

## Success Criteria

All of the following must be true for the refactoring to be considered complete:
- [ ] Applications table is the single source of truth for application status
- [ ] Applicants.php shows status directly from applications table
- [ ] manage_applicants.php Applications section uses AJAX endpoints
- [ ] manage_applicants.php Applicants section shows computed "Applied"/"Not Applied"
- [ ] Forfeit button only appears for Pending applications
- [ ] Admin status updates persist via update_application_status.php
- [ ] Applicant forfeit persists via forfeit_application.php
- [ ] No status syncing occurs between applicants and applications tables
- [ ] All changes persist after page refresh
- [ ] Error handling shows appropriate error messages
- [ ] Toast notifications appear for all user actions
