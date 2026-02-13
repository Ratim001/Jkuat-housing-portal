# Refactoring Summary - Application Status Management

## Overview

This refactoring decoupled applicant-level status from per-application status, establishing a clean separation of concerns:

- **Applications table** → Single source of truth for per-application status (Pending, Approved, Rejected, Cancelled, Won)
- **Applicants list** → Shows computed "Applied"/"Not Applied" based on application count
- **Admin interfaces** → Use dedicated AJAX endpoints for all status updates
- **Applicant dashboards** → Read status directly from applications table

## Files Modified

### 1. `php/update_application_status.php` (NEW)
**Purpose:** Admin endpoint for updating application status

**Features:**
- ✅ Validates admin authentication (403 for non-admins)
- ✅ Validates application_id format (AP###)
- ✅ Validates status values (Pending, Approved, Rejected, Cancelled, Won)
- ✅ Uses prepared statements for SQL injection prevention
- ✅ Returns JSON response with status codes
- ✅ Logs all update attempts

**Endpoint Details:**
```php
POST /php/update_application_status.php
Content-Type: application/x-www-form-urlencoded

Request: application_id=AP001&status=Approved
Response: { "success": true, "application_id": "AP001", "status": "Approved" }
```

**HTTP Status Codes:**
- 200: Success
- 400: Invalid input
- 403: Unauthorized (not admin)
- 404: Application not found
- 500: Server error

**Database Operation:**
```sql
UPDATE applications SET status = ? WHERE application_id = ?
```

---

### 2. `php/forfeit_application.php` (NEW)
**Purpose:** Applicant endpoint for cancelling their pending applications

**Features:**
- ✅ Validates applicant authentication (403 for unauthenticated)
- ✅ Verifies applicant owns the application
- ✅ Only allows forfeiting Pending applications
- ✅ Uses prepared statements for SQL injection prevention
- ✅ Returns JSON response with status codes
- ✅ Logs all forfeit attempts

**Endpoint Details:**
```php
POST /php/forfeit_application.php
Content-Type: application/x-www-form-urlencoded

Request: application_id=AP001
Response: { "success": true, "application_id": "AP001", "status": "Cancelled" }
```

**HTTP Status Codes:**
- 200: Success
- 400: Invalid input
- 403: Unauthorized (not owner or not applicant)
- 404: Application not found
- 422: Cannot forfeit (not Pending status)
- 500: Server error

**Database Operation:**
```sql
UPDATE applications SET status = 'Cancelled' WHERE application_id = ? AND applicant_id = ?
```

---

### 3. `php/manage_applicants.php` (MODIFIED)
**Changes:**

#### A. Applications Section (AJAX Integration)
- ✅ Replaced form-based status update with AJAX dropdowns
- ✅ Added event listeners to status dropdowns
- ✅ Calls `update_application_status.php` endpoint on change
- ✅ Added error handling with informative messages
- ✅ Added toast notifications (success/error)
- ✅ Disabled button during processing
- ✅ Reverts dropdown on error

**Status Options:** Pending, Approved, Rejected, Cancelled (was: Pending, Approved only)

**Javascript Code:**
```javascript
fetch('update_application_status.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `application_id=${appId}&status=${newStatus}`
})
```

#### B. Applicants Section (Computed Status)
- ✅ Replaced editable applicant status with computed status
- ✅ Added COUNT query to determine "Applied"/"Not Applied"
- ✅ "Applied" (green): If applicant has >= 1 application
- ✅ "Not Applied" (gray): If applicant has 0 applications
- ✅ Status is read-only display (no edit controls)

**Computation:**
```php
$countStmt = $conn->prepare("SELECT COUNT(*) as app_count FROM applications WHERE applicant_id = ?");
$hasApplications = $countResult['app_count'] > 0;
$statusLabel = $hasApplications ? 'Applied' : 'Not Applied';
```

#### C. Removed POST Handlers
- ❌ Removed `update_applicant_status` handler (old applicant status sync)
- ❌ Removed `update_application_status` handler (old application status sync)
- ℹ️ Kept `choose_winner` handler (sets applicants.status = 'Tenant' for balloting)

**Impact:** Status updates now exclusively through AJAX endpoints

---

### 4. `php/applicants.php` (MODIFIED)
**Changes:**

#### A. Removed Old Forfeit Handler
- ❌ Removed POST handler `if (isset($_POST['forfeit_ajax']))`
- ✅ Now uses dedicated `forfeit_application.php` endpoint

#### B. Updated Forfeit Button
- ✅ Integrated AJAX call to `forfeit_application.php`
- ✅ Shows "Processing..." during request
- ✅ Handles error responses (403, 404, 422, 500)
- ✅ Replaces button with "-" on success
- ✅ Reverts on error with informative message
- ✅ Added toast notifications

**Javascript Code:**
```javascript
fetch('forfeit_application.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `application_id=${appId}`
})
```

#### C. Database Reads
- ✅ Still reads from `applications` table (unchanged)
- ✅ Shows current status from database (not cached in session)
- ✅ Forfeit button only shows if `status === 'Pending'`

**Query:**
```sql
SELECT * FROM applications WHERE applicant_id = ? ORDER BY date DESC
```

#### D. Added Toast Notifications
- ✅ Success toast: "Application forfeited successfully"
- ✅ Error toasts: Specific error messages from server
- ✅ Auto-hide after 3 seconds

---

## Database Schema

No schema changes required. Required columns already ensured by migrations:

- `applications.status` → VARCHAR(50), DEFAULT 'Pending'
- `applicants.status` → VARCHAR(50), DEFAULT 'Pending' (still exists but unused for per-app tracking)

**Note:** `applicants.status` is now only used for special statuses (Tenant, etc.) set by balloting system, not synchronized with application status.

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    APPLICANT DASHBOARD                       │
│                   (applicants.php)                           │
├─────────────────────────────────────────────────────────────┤
│  Applications Table                                          │
│  ├─ Application 1: Status=Pending → [Forfeit Button]        │
│  ├─ Application 2: Status=Approved → [-]                    │
│  └─ Application 3: Status=Rejected → [-]                    │
│                                                              │
│  Forfeit Button → forfeit_application.php → applications DB  │
│                   Updates status to 'Cancelled'              │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                    ADMIN DASHBOARD                           │
│                (manage_applicants.php)                       │
├─────────────────────────────────────────────────────────────┤
│ Applications Section                                         │
│ ├─ App Dropdown 1 → update_application_status.php           │
│ ├─ App Dropdown 2 → update_application_status.php           │
│ └─ App Dropdown 3 → update_application_status.php           │
│                     Updates applications DB                  │
├─────────────────────────────────────────────────────────────┤
│ Applicants Section (Computed Status)                        │
│ ├─ Applicant A: Applied (COUNT apps > 0) [Notify]           │
│ └─ Applicant B: Not Applied (COUNT apps = 0) [Notify]       │
│   Status is read-only, computed from applications count     │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│               SINGLE SOURCE OF TRUTH                         │
│            applications TABLE (status column)               │
│  - Pending: Not yet reviewed by admin                       │
│  - Approved: Applicant eligible for balloting               │
│  - Rejected: Application not accepted                       │
│  - Cancelled: Applicant forfeited                           │
│  - Won: Applicant won balloting                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Data Flow Examples

### Example 1: Admin Updates Status
```
User Action: Admin changes App001 from Pending → Approved in dropdown
  ↓
AJAX Request: POST update_application_status.php (app_id=AP001, status=Approved)
  ↓
Endpoint Validates: Admin? ✓ App exists? ✓ Status valid? ✓
  ↓
Database Update: UPDATE applications SET status='Approved' WHERE application_id='AP001'
  ↓
Response: {success: true, application_id: 'AP001', status: 'Approved'}
  ↓
Frontend: Toast shows "Status updated to Approved"
  ↓
Persistence: Applicant sees status change in applicants.php on next load
```

### Example 2: Applicant Forfeits Application
```
User Action: Applicant clicks Forfeit button on Pending application
  ↓
Confirmation: Dialog asks "Are you sure?"
  ↓
AJAX Request: POST forfeit_application.php (application_id=AP001)
  ↓
Endpoint Validates: Owner? ✓ Status=Pending? ✓
  ↓
Database Update: UPDATE applications SET status='Cancelled' WHERE application_id='AP001' AND applicant_id='APPL001'
  ↓
Response: {success: true, application_id: 'AP001', status: 'Cancelled'}
  ↓
Frontend: Replace button with "-", show "Application forfeited successfully" toast
  ↓
Persistence: Button is gone after refresh (status !== 'Pending')
```

### Example 3: Applicant Status Computation
```
Admin View: manage_applicants.php / Applicants section
  ↓
For each applicant row:
  SQL: SELECT COUNT(*) FROM applications WHERE applicant_id = 'APPL001'
  ↓
  If count > 0: Show "Applied" (green) - regardless of individual app statuses
  If count = 0: Show "Not Applied" (gray)
  ↓
Result: Applicant A shows "Applied" even if all her apps are Rejected
         (Status indicates she has APPLIED, not that apps are approved)
```

---

## Breaking Changes

### For Admin Users:
- **Status dropdown in Applicants section removed** - Was showing applicant.status which is no longer synced
- **Must use Applications section** to view/manage per-application status (unchanged location, just AJAX-based now)

### For API Users:
- **Old form-based submissions no longer work** - Must use AJAX endpoints instead
- `/update_application_status.php` is now the official endpoint (replaces old POST handler)
- `/forfeit_application.php` is new endpoint (replaces old DELETE operation)

### For Database:
- **No breaking changes** - All existing columns remain, just used differently
- Applicant status no longer automatically synced with application status

---

## Commits Made

1. **"backend: add update_application_status and forfeit_application endpoints"**
   - Added 2 new PHP files
   - 203 lines of production-ready code
   - Full auth validation, error handling, logging

2. **"feat: integrate update_application_status endpoint in manage_applicants Applications section"**
   - AJAX integration for status dropdown
   - Toast notifications
   - Error handling with dropdown revert
   - 81 insertions, 8 deletions

3. **"feat: integrate forfeit_application endpoint in applicants section"**
   - AJAX integration for Forfeit button
   - Loading state and error handling
   - Toast notifications
   - 58 insertions, 17 deletions

4. **"refactor: show computed Applied/Not Applied status in applicants section"**
   - Replaced editable applicant status with computed label
   - Added COUNT query logic
   - 11 insertions, 2 deletions

5. **"refactor: remove status sync handlers, decouple applicant-level status from application-level status"**
   - Removed old POST handlers that were syncing statuses
   - 48 deletions (cleanup)

6. **"docs: add comprehensive testing guide for status refactoring"**
   - Created TESTING_GUIDE.md with 350+ lines
   - 9 detailed test cases
   - SQL verification queries
   - Success criteria

---

## Verification Checklist

Before considering this complete, verify:

- [ ] New endpoints `/php/update_application_status.php` and `/php/forfeit_application.php` exist
- [ ] Admin can change application status via dropdown in manage_applicants.php
- [ ] Changes persist after page refresh
- [ ] Applicant can forfeit Pending applications in applicants.php
- [ ] Forfeit button only shows for Pending status
- [ ] manage_applicants.php / Applicants section shows "Applied"/"Not Applied" (not editable)
- [ ] No status synchronization between applicants and applications tables
- [ ] Error messages show appropriately for invalid operations
- [ ] Toast notifications work for all user actions
- [ ] All 6 commits are present in git history

---

## Rollback Instructions

If issues are discovered, commits can be reverted in reverse order (most recent first):

```bash
git revert b36b017  # Remove testing guide
git revert cb97409  # Re-enable sync handlers
git revert cf93d27  # Remove computed status
git revert 617fe8d  # Remove forfeit endpoint integration
git revert c312dc4  # Remove update_application_status integration
git revert 903d7e5  # Remove new endpoints
```

Each commit is self-contained and can be reverted independently.

---

## Next Steps

1. **User Testing:** Verify all test cases in TESTING_GUIDE.md pass
2. **Performance Testing:** Check page load times with large datasets
3. **Security Audit:** Verify all endpoints properly validate input
4. **Documentation:** Update API documentation if applicable
5. **Monitoring:** Add prometheus/monitoring for endpoint usage

---

## Related Files

- `TESTING_GUIDE.md` - Complete testing procedures
- `PRODUCTION_CHECKLIST.md` - Pre-deployment checklist
- `SECURITY.md` - Security best practices
- Migration files in `/migrations/` - Database schema

---

**Last Updated:** 2026-02-11
**Refactoring Scope:** Complete application status management system overhaul
**Breaking Changes:** Yes - Status update mechanisms changed from form-based to AJAX endpoints
