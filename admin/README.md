# 🛠️ Admin Panel - Reference Data Management

## Overview

The Admin Panel provides a **practical, file-based approach** to managing reference data (Departments, Programs, Trimesters) instead of hardcoding them in the SQL schema file.

## Why This Approach?

❌ **Old Method**: Hardcode sample data in `schema.sql`
- Requires manual SQL editing
- Difficult to update
- Not scalable
- Requires database re-import for changes

✅ **New Method**: Admin panel with file upload
- Upload CSV files with bulk data
- Easy to update
- Scalable for large institutions
- No need to touch database directly
- Download templates for guidance

---

## Features

### 📤 File Upload System
- Upload Departments, Programs, and Trimesters via CSV files
- Drag & drop support
- Automatic duplicate detection
- Bulk insert/update operations

### 📥 Template Downloads
- Pre-formatted CSV templates
- Sample data included
- Proper format guidelines

### 📊 Data Management
- View all existing records
- Real-time statistics
- Status indicators (Required/Optional)
- Current trimester marking

### ✅ Validation
- Checks for required reference data
- Warns if data is missing
- Foreign key relationship validation
- Prevents system from breaking

---

## Access the Admin Panel

**URL**: `http://localhost/smartcampus/admin/`

**Default Credentials**:
- Username: `admin`
- Password: `admin123`

⚠️ **IMPORTANT**: Change these credentials in production!
Edit `admin/login.php` to update the credentials.

---

## Quick Start Guide

### Step 1: Login to Admin Panel
1. Visit: `http://localhost/smartcampus/admin/`
2. Login with credentials above
3. You'll see the dashboard with statistics

### Step 2: Upload Departments
1. Click "📤 Upload Departments File"
2. Download the CSV template (or create your own)
3. Fill in your departments data
4. Upload the file
5. System will insert/update departments

### Step 3: Upload Programs
1. Click "📤 Upload Programs File"
2. Download the CSV template
3. **Important**: Department codes must exist first!
4. Fill in programs data
5. Upload the file

### Step 4: Upload Trimesters
1. Click "📤 Upload Trimesters File"
2. Download the CSV template
3. Mark ONE trimester as current (is_current = 1)
4. Upload the file

### Step 5: Verify
1. Click "✅ Verify Reference Data"
2. Should show all required data present
3. Now students can sync from UCAM!

---

## CSV File Formats

### Departments CSV
```csv
department_code,department_name
CSE,Computer Science and Engineering
EEE,Electrical and Electronic Engineering
BBA,Business Administration
CE,Civil Engineering
```

**Fields:**
- `department_code`: Unique identifier (e.g., CSE, EEE)
- `department_name`: Full department name

---

### Programs CSV
```csv
program_code,program_name,department_code,total_required_credits,duration_years
BSC_CSE,Bachelor of Science in CSE,CSE,140,4.0
BSC_EEE,Bachelor of Science in EEE,EEE,140,4.0
BBA,Bachelor of Business Administration,BBA,120,4.0
```

**Fields:**
- `program_code`: Unique identifier
- `program_name`: Full program name
- `department_code`: Must exist in departments table
- `total_required_credits`: Total credits to graduate
- `duration_years`: Program duration (can be decimal, e.g., 1.5)

⚠️ **Important**: Upload departments BEFORE programs!

---

### Trimesters CSV
```csv
trimester_code,trimester_name,trimester_type,year,start_date,end_date,is_current
251,Spring 2025,trimester,2025,2025-01-01,2025-05-31,0
252,Summer 2025,trimester,2025,2025-06-01,2025-08-31,0
253,Fall 2025,trimester,2025,2025-09-01,2025-12-31,1
```

**Fields:**
- `trimester_code`: Unique code (e.g., 251, 252, 253)
- `trimester_name`: Display name
- `trimester_type`: Either "trimester" or "semester"
- `year`: Academic year
- `start_date`: Format YYYY-MM-DD
- `end_date`: Format YYYY-MM-DD
- `is_current`: 1 for current, 0 for others (only ONE should be 1)

### Courses CSV
```csv
course_code,course_name,credit_hours,department_code,course_type
CSE 1115,Introduction to Computer Science,3,CSE,theory
CSE 1116,Introduction to Computer Science Lab,1,CSE,lab
CSE 3521,Database Management Systems,3,CSE,theory
CSE 3522,Database Management Systems Lab,1,CSE,lab
CSE 4846,Capstone Project,3,CSE,project
```

**Fields:**
- `course_code`: Unique course identifier (e.g., CSE 1115)
- `course_name`: Full course name
- `credit_hours`: Number of credits (usually 1-4)
- `department_code`: Must exist in departments table
- `course_type`: Either "theory", "lab", or "project" (optional, defaults to "theory")

⚠️ **Important**: Upload departments BEFORE courses! Courses can also be populated automatically from UCAM sync.

---

## File Upload Features

### Drag & Drop
- Simply drag CSV files onto the upload area
- No need to click "Browse"

### Duplicate Handling
- Automatically updates existing records
- Uses `INSERT ... ON DUPLICATE KEY UPDATE`
- Shows count of inserted vs skipped records

### Error Handling
- Validates CSV format
- Checks for required fields
- Reports missing foreign key references
- Shows detailed error messages

---

## Dashboard Statistics

The admin dashboard shows:

| Statistic | Description | Status |
|-----------|-------------|--------|
| 🏢 Departments | Number of departments | Required ✅ |
| 🎓 Programs | Number of programs | Required ✅ |
| 📅 Trimesters | Number of trimesters | Required ✅ |
| 👨‍🎓 Students | Number of students | Optional (from UCAM) |
| 📚 Courses | Number of courses | Optional (from UCAM) |

**Color Coding:**
- 🟢 Green badge: Required data present
- 🔴 Red badge: Required data missing (system won't work!)
- 🔵 Blue badge: Optional data (populated from UCAM sync)

---

## System Tools

### ✅ Verify Reference Data
- Checks if all required data exists
- Shows detailed counts and sample records
- Identifies missing data
- Provides fix instructions

### 🔍 Check Duplicates
- Scans for duplicate entries
- One-click duplicate removal
- Ensures data integrity

### 💾 Backup Database
- Export current database state
- Restore in case of errors

---

## Danger Zone ⚠️

### 🗑️ Clear All Data
- Deletes ALL data from database
- Cannot be undone!
- Use only for testing

### 🔄 Reset Database
- Drops and recreates database
- Removes all tables and data
- Requires re-import of schema

---

## Security Considerations

### Change Default Password
Edit `admin/login.php`:
```php
$admin_username = 'your_admin_username';
$admin_password = 'your_secure_password';
```

### Use Password Hashing (Recommended)
```php
$admin_password_hash = password_hash('your_password', PASSWORD_DEFAULT);

// Then in login check:
if (password_verify($password, $admin_password_hash)) {
    // Login successful
}
```

### Restrict Access
Add IP whitelist or use `.htaccess`:
```apache
Order Deny,Allow
Deny from all
Allow from 127.0.0.1
Allow from YOUR_IP_ADDRESS
```

---

## Troubleshooting

### Error: "Department code 'XXX' not found"
**Solution**: Upload departments first, then programs

### Error: "No current trimester marked"
**Solution**: Set `is_current = 1` for exactly ONE trimester in CSV

### Error: "Cannot add or update a child row"
**Solution**: This means reference data is missing. Check admin dashboard for red badges.

### CSV File Not Parsing
**Solution**: 
- Ensure CSV is UTF-8 encoded
- Check for proper comma separation
- Verify no extra spaces in data
- Download and use provided templates

---

## Benefits of This Approach

✅ **Practical**: Upload files instead of editing SQL  
✅ **Scalable**: Handle hundreds of departments/programs easily  
✅ **Maintainable**: Update data without touching code  
✅ **User-Friendly**: No SQL knowledge required  
✅ **Flexible**: Supports CSV (Excel-compatible)  
✅ **Safe**: Duplicate detection prevents errors  
✅ **Verifiable**: Built-in validation and checking tools  

---

## Future Enhancements

Planned features:
- [ ] Excel (.xlsx) direct support
- [ ] PDF parsing for official documents
- [ ] Batch edit interface
- [ ] Data export to CSV
- [ ] Audit log for changes
- [ ] Multi-admin user support
- [ ] Role-based access control

---

## Support

For issues or questions:
1. Check the dashboard for warning messages
2. Use "✅ Verify Reference Data" tool
3. Review CSV format guides
4. Check troubleshooting section above

---

**Remember**: The admin panel is the **foundation** of your Smart Campus system. Ensure all required reference data is uploaded before students attempt to sync from UCAM!
