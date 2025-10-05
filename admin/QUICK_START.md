# 🛠️ System Maintenance Features - Quick Start Guide

## What Was Added?

Your admin settings page now has **6 powerful maintenance tools** to keep your database healthy and normalized.

## 📍 Where to Find It?

1. Login to Admin Panel: `http://localhost/smartcampus/admin/`
2. Navigate to: **Settings** (from sidebar)
3. Scroll to: **System Maintenance** section

## 🎯 Quick Overview

| Feature | Purpose | When to Use |
|---------|---------|-------------|
| **Clear Cache** | Remove old logs, optimize database | Weekly |
| **Verify Data** | Check 3NF/BCNF compliance, find issues | After imports |
| **Check Duplicates** | Find & remove duplicate records | Monthly |
| **Backup Database** | Create safety backups | Before changes |
| **Reset System** | Clear all data (keep structure) | New semester |
| **Danger Zone** | Delete EVERYTHING | Never (unless necessary) |

## 🚀 Quick Start

### 1. First Time Setup
```bash
# Make sure these files exist:
✅ admin/api/verify_database.php
✅ admin/api/check_duplicates.php
✅ admin/api/clear_cache.php
✅ admin/api/system_operations.php
```

### 2. Test Everything
Visit: `http://localhost/smartcampus/admin/test_maintenance.html`

This page lets you test all APIs without risking your data.

### 3. Run Your First Check
1. Go to Settings page
2. Click "Check Only" under **Verify Data**
3. Review the results in the modal

## 📊 What Gets Checked?

### Database Verification Checks:

✅ **Referential Integrity**
- Orphaned students, enrollments, grades
- Foreign key relationships
- Missing references

✅ **Normalization (3NF/BCNF)**
- Transitive dependencies
- Functional dependencies
- Derived value consistency
- Duplicate constraints

✅ **Data Consistency**
- Negative credits
- Invalid CGPA (0.00-4.00)
- Attendance percentages
- Date validations

✅ **Duplicates**
- Duplicate enrollments
- Duplicate likes/bookmarks
- Duplicate achievements

## 🔧 Auto-Fix Features

When you click **"Check & Auto-Fix"**, the system automatically:

1. ✅ Synchronizes student credits from enrollments
2. ✅ Recalculates total points from point history
3. ✅ Fixes attendance percentages
4. ✅ Removes orphaned records
5. ✅ Corrects negative values
6. ✅ Fixes invalid CGPA ranges

## 📋 Files Created

```
admin/
├── api/
│   ├── verify_database.php      (Main verification engine)
│   ├── check_duplicates.php     (Duplicate finder/remover)
│   ├── clear_cache.php          (Cache cleaner)
│   └── system_operations.php    (Dangerous operations)
├── test_maintenance.html        (Testing interface)
├── MAINTENANCE_FEATURES.md      (Full documentation)
└── settings.php                 (Updated with new features)
```

## 🎨 UI Features

- **Beautiful Modal Windows** - All results shown in clean, animated modals
- **Color-Coded Results** - Green (success), Yellow (warning), Red (error)
- **Real-time Processing** - Loading animations while working
- **Detailed Reports** - See exactly what was checked/fixed
- **Safety Confirmations** - Multiple confirmations for dangerous actions

## ⚠️ Safety Features

1. **Session Checks** - All APIs require admin login
2. **Confirmation Dialogs** - Dangerous actions need typed confirmation
3. **Activity Logging** - Everything is logged
4. **Read-Only Mode** - "Check Only" won't modify data
5. **Backup Reminders** - Always backup before big changes

## 📱 How to Use Each Feature

### 1️⃣ Clear Cache (Safe - Use Anytime)
```
Click: "Clear Cache" button
Result: Old logs deleted, tables optimized
Time: ~5 seconds
```

### 2️⃣ Verify Database (Recommended Weekly)
```
Click: "Check Only" (to just check)
   OR: "Check & Auto-Fix" (to fix issues)
Result: Detailed health report
Time: ~10-30 seconds
```

### 3️⃣ Check Duplicates (Monthly)
```
Click: "Find Duplicates" (to just find)
   OR: "Find & Remove" (to clean up)
Result: List of duplicates found/removed
Time: ~5-15 seconds
```

### 4️⃣ Reset System (⚠️ Caution!)
```
Click: "Reset System"
Type: "RESET_SYSTEM"
Confirm: Yes
Result: All student/course data deleted
Backup: REQUIRED FIRST!
```

### 5️⃣ Danger Zone (☠️ EXTREME CAUTION!)
```
Click: "Delete All Data"
Type: "DELETE_EVERYTHING"
Confirm: Yes (twice!)
Result: Everything except admins deleted
Backup: ABSOLUTELY REQUIRED!
Use Case: Complete system reinstall only
```

## 📈 Monitoring Schedule

### Daily
- Check dashboard for errors
- Monitor user reports

### Weekly
- Run "Verify Database" (check only)
- Review activity logs

### Monthly
- Clear cache
- Check duplicates
- Create backup
- Run auto-fix if needed

### Quarterly
- Full system verification
- Archive old logs
- Performance review

## 🐛 Troubleshooting

### "Unauthorized" Error
→ Make sure you're logged in as admin

### API Returns Empty
→ Check `config/database.php` connection

### Auto-Fix Not Working
→ Check database user has write permissions

### Modal Won't Close
→ Click outside or press ESC

## 📞 Need Help?

1. Read full docs: `admin/MAINTENANCE_FEATURES.md`
2. Test APIs: `admin/test_maintenance.html`
3. Check activity logs: Admin Panel → Logs
4. Review error console: Browser DevTools (F12)

## ✅ Verification Checklist

After installation, verify:

- [ ] Can login to admin panel
- [ ] Can see System Maintenance section
- [ ] Clear Cache works (test it)
- [ ] Verify Database works (check only mode)
- [ ] Test page loads (`test_maintenance.html`)
- [ ] Modal displays properly
- [ ] All 4 API files exist in `admin/api/`

## 🎓 Database Normalization Status

Your database was initially designed in **3NF/BCNF**. These tools help you:
- **Verify** it's still normalized
- **Detect** violations
- **Fix** common issues automatically
- **Maintain** data integrity

## 🔐 Security Notes

- All endpoints check admin session
- Dangerous operations require confirmation
- All actions are logged
- No SQL injection vulnerabilities
- Proper error handling

## 📝 What's Logged?

Every maintenance action logs:
- Admin who performed it
- Timestamp
- Action type
- Details/description
- IP address (if available)

View logs at: **Admin Panel → Logs**

## 🎉 You're All Set!

Your system maintenance features are now fully functional. Start with a simple cache clear or database verification to see it in action!

**Recommended First Action:**
1. Go to Settings
2. Click "Check Only" under Verify Data
3. Review your current database health
4. Pat yourself on the back! 🎊

---

**Version:** 1.0  
**Last Updated:** October 3, 2025  
**Compatibility:** PHP 7.4+, MySQL 5.7+

