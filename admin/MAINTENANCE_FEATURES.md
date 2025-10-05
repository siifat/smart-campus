# System Maintenance Features - Documentation

## Overview
The Admin Settings page now includes fully functional system maintenance tools that help you monitor and maintain your Smart Campus database.

## Features Implemented

### 1. **Clear Cache** 🧹
**Location:** Settings > System Maintenance > Clear Cache

**What it does:**
- Clears expired admin sessions
- Deletes old activity logs (older than 90 days)
- Removes anonymous resource views (older than 30 days)
- Clears read admin notifications (older than 60 days)
- Removes failed and sent emails from queue
- Optimizes all database tables for better performance

**When to use:**
- System feels slow
- After major updates
- Weekly/monthly maintenance
- To free up database space

**Technical Details:**
- Endpoint: `admin/api/clear_cache.php`
- Method: POST
- Returns: JSON with list of actions performed

---

### 2. **Verify Database** ✅
**Location:** Settings > System Maintenance > Verify Data

**What it does:**
This is the most comprehensive feature that performs multiple checks:

#### 2.1 Referential Integrity Checks
- Orphaned students (invalid program_id)
- Orphaned enrollments (invalid student_id or course_id)
- Orphaned grades (invalid enrollment_id)
- Orphaned notes (invalid student_id)
- All foreign key relationships

#### 2.2 Normalization Checks (3NF/BCNF)
- Verifies database is still in 3rd Normal Form and Boyce-Codd Normal Form
- Checks for transitive dependencies
- Validates derived values (total_completed_credits, total_points)
- Ensures attendance percentages are calculated correctly
- Detects duplicate course codes in departments

#### 2.3 Data Consistency Checks
- Students with negative credits
- Invalid CGPA values (outside 0.00-4.00 range)
- Attendance percentages outside 0-100%
- Courses with invalid credit hours
- Trimesters with end_date before start_date

#### 2.4 Constraint Integrity
- Verifies all foreign keys exist
- Checks unique constraints
- Validates primary keys

**Two Modes:**

1. **Check Only Mode**
   - Button: "Check Only"
   - Only reports issues without fixing them
   - Safe to run anytime

2. **Auto-Fix Mode**
   - Button: "Check & Auto-Fix"
   - Automatically fixes common issues:
     - Synchronizes student credits
     - Synchronizes student points
     - Recalculates attendance percentages
     - Fixes negative values
     - Removes orphaned records

**Output:**
- Health summary with visual indicators
- List of all issues (categorized by severity: critical, high, medium, low)
- List of warnings
- Applied fixes (in auto-fix mode)
- Normalization status (3NF/BCNF Compliant or Issues Detected)
- Database health rating (Excellent, Good, Needs Attention)

**When to use:**
- After bulk data imports
- When data seems inconsistent
- Weekly integrity checks
- Before major updates
- After database migrations

**Technical Details:**
- Endpoint: `admin/api/verify_database.php`
- Method: POST
- Parameters: `auto_fix=true` (optional)
- Returns: Comprehensive JSON report

---

### 3. **Check Duplicates** 🔍
**Location:** Settings > System Maintenance > Check Duplicates

**What it checks:**
- Duplicate enrollments (same student, course, trimester)
- Duplicate admin users (same username)
- Duplicate resource likes (same resource and student)
- Duplicate bookmarks (same resource and student)
- Duplicate student achievements

**Two Modes:**

1. **Find Duplicates**
   - Button: "Find Duplicates"
   - Only identifies duplicates
   - Shows details of each duplicate group

2. **Find & Remove**
   - Button: "Find & Remove"
   - Finds and automatically removes duplicates
   - Keeps the oldest record (first one created)
   - Shows count of removed records

**When to use:**
- After data imports
- If users report weird behavior
- Monthly cleanup
- Before generating reports

**Technical Details:**
- Endpoint: `admin/api/check_duplicates.php`
- Method: POST
- Parameters: `remove_duplicates=true` (optional)
- Returns: JSON with duplicate details and removal status

---

### 4. **Backup Database** 💾
**Location:** Settings > System Maintenance > Backup Database

**What it does:**
- Links to the comprehensive backup page
- Create manual backups
- Download existing backups
- View backup history

**When to use:**
- Before major changes
- Daily/weekly automated backups
- Before system reset or data deletion
- Before semester changes

---

### 5. **Reset System** ⚠️
**Location:** Settings > System Maintenance > Reset System

**What it does:**
- Clears all student data
- Clears all course data
- Clears enrollments, grades, attendance
- Clears resources, notes, solutions
- Clears todos and activities
- Keeps departments, programs, and trimesters
- Keeps admin users and settings
- Resets auto-increment values

**Safety Features:**
- Requires typing "RESET_SYSTEM" to confirm
- Double confirmation dialog
- Logs the action in activity logs

**When to use:**
- Start of new academic year
- Testing purposes
- After pilot/demo period
- System refresh

**⚠️ WARNING:** This action cannot be undone!

**Technical Details:**
- Endpoint: `admin/api/system_operations.php`
- Method: POST
- Parameters: 
  - `action=reset_system`
  - `confirmation=RESET_SYSTEM`

---

### 6. **Danger Zone - Delete All Data** ☠️
**Location:** Settings > System Maintenance > Danger Zone

**What it does:**
- Deletes EVERYTHING except:
  - Admin users
  - Admin sessions
  - System settings
- Truncates all tables
- Complete data wipe

**Safety Features:**
- Requires typing "DELETE_EVERYTHING" to confirm
- Triple confirmation process
- Logs the action before deletion
- Disables foreign key checks safely

**When to use:**
- Complete system reinstall
- Moving to new server
- **EXTREME CAUTION REQUIRED**

**⚠️ EXTREME WARNING:** This is irreversible and catastrophic!

**Technical Details:**
- Endpoint: `admin/api/system_operations.php`
- Method: POST
- Parameters: 
  - `action=delete_all_data`
  - `confirmation=DELETE_EVERYTHING`

---

## API Endpoints Summary

### 1. `/admin/api/verify_database.php`
**Purpose:** Comprehensive database verification and integrity checking

**Request:**
```http
POST /admin/api/verify_database.php
Content-Type: application/x-www-form-urlencoded

auto_fix=true  // Optional: enables auto-fix mode
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-03 14:30:00",
  "checks": {
    "referential_integrity": {
      "status": "passed",
      "issues": [],
      "warnings": []
    },
    "normalization_3nf_bcnf": {
      "status": "passed",
      "issues": [],
      "warnings": []
    }
  },
  "issues": [],
  "warnings": [],
  "fixes_applied": [],
  "summary": {
    "total_checks": 5,
    "passed_checks": 5,
    "total_issues": 0,
    "total_warnings": 0,
    "normalization_status": "3NF/BCNF Compliant",
    "database_health": "Excellent"
  }
}
```

### 2. `/admin/api/check_duplicates.php`
**Purpose:** Find and remove duplicate records

**Request:**
```http
POST /admin/api/check_duplicates.php
Content-Type: application/x-www-form-urlencoded

remove_duplicates=true  // Optional: enables removal
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-03 14:30:00",
  "duplicates_found": {
    "enrollments": [
      {
        "student_id": "0112330011",
        "course_id": 5,
        "trimester_id": 2,
        "duplicate_count": 2,
        "enrollment_ids": "123,124"
      }
    ]
  },
  "total_duplicates": 1,
  "removed": {
    "status": "success",
    "total_removed": 1,
    "message": "Successfully removed 1 duplicate record(s)"
  }
}
```

### 3. `/admin/api/clear_cache.php`
**Purpose:** Clear system cache and optimize database

**Request:**
```http
POST /admin/api/clear_cache.php
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-03 14:30:00",
  "actions": [
    "Cleared 5 expired admin session(s)",
    "Deleted 120 old activity log(s) (>90 days)",
    "Optimized 15 database table(s)"
  ],
  "summary": {
    "total_actions": 3,
    "cache_cleared": true,
    "database_optimized": true
  }
}
```

### 4. `/admin/api/system_operations.php`
**Purpose:** Dangerous system operations (reset, delete)

**Request:**
```http
POST /admin/api/system_operations.php
Content-Type: application/x-www-form-urlencoded

action=reset_system
confirmation=RESET_SYSTEM
```

**Response:**
```json
{
  "success": true,
  "timestamp": "2025-10-03 14:30:00",
  "action": "reset_system",
  "message": "System reset successfully. All student and course data cleared."
}
```

---

## User Interface

### Modal Display
All operations display results in a beautiful modal window with:
- Loading animation during processing
- Color-coded results (green for success, red for errors, yellow for warnings)
- Expandable details
- Close button and click-outside-to-close functionality
- Smooth animations

### Visual Feedback
- **Green borders/backgrounds:** Success, health, approved items
- **Blue borders/backgrounds:** Information, verification
- **Yellow borders/backgrounds:** Warnings, caution
- **Red borders/backgrounds:** Errors, danger, critical issues

### Severity Levels
- **Critical:** Database corruption, major integrity issues
- **High:** Referential integrity violations, duplicate keys
- **Medium:** Inconsistent derived values, minor integrity issues
- **Low:** Data quality warnings, unusual values

---

## Security Features

1. **Session Verification**
   - All API endpoints check for active admin session
   - Returns 401 Unauthorized if not logged in

2. **Confirmation Requirements**
   - Destructive operations require typed confirmation
   - Multiple confirmation dialogs for dangerous actions

3. **Activity Logging**
   - All operations logged in activity_logs table
   - Includes admin_id, timestamp, action details

4. **Transaction Safety**
   - Foreign key checks disabled/enabled safely
   - Error handling with rollback capability

---

## Best Practices

### Regular Maintenance Schedule

**Daily:**
- Monitor system health dashboard
- Check for new errors/warnings

**Weekly:**
- Run "Verify Database" in check-only mode
- Review activity logs

**Monthly:**
- Clear cache
- Check for duplicates
- Create backup
- Run auto-fix if needed

**Quarterly:**
- Full database verification
- Archive old logs
- Review and optimize

### Before Major Changes
1. Create backup
2. Run full verification
3. Note current statistics
4. Perform change
5. Verify again
6. Compare results

### After Data Import
1. Check for duplicates
2. Verify referential integrity
3. Run auto-fix if needed
4. Verify normalization
5. Clear cache

---

## Troubleshooting

### "No response from server"
- Check if API files exist in `admin/api/` folder
- Verify database connection in `config/database.php`
- Check PHP error logs

### "Unauthorized" error
- Ensure you're logged in as admin
- Check session timeout settings
- Try logging out and back in

### Auto-fix not working
- Check database user permissions
- Verify tables are not locked
- Review specific error messages in response

### Modal not closing
- Click outside the modal
- Press ESC key
- Refresh page if necessary

---

## Future Enhancements

Potential additions:
- Scheduled automatic verification
- Email notifications for critical issues
- Export verification reports as PDF
- Automated backup before auto-fix
- Rollback capability
- Performance benchmarking
- Database optimization suggestions
- Index analysis and recommendations

---

## Credits

**Developer:** Smart Campus Development Team  
**Version:** 1.0  
**Date:** October 2025  
**Database:** MySQL/MariaDB  
**Framework:** PHP, JavaScript (Vanilla)

---

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review activity logs for error details
3. Contact system administrator
4. Consult database documentation

---

**Remember:** Always backup before performing destructive operations! 🔒

