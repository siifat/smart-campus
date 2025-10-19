<?php
/**
 * Database Backup & Restore System
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once('../config/database.php');

$message = '';
$message_type = '';

// Show success message after delete redirect
if (isset($_SESSION['backup_message'])) {
    $message = $_SESSION['backup_message'];
    $message_type = $_SESSION['backup_message_type'];
    unset($_SESSION['backup_message']);
    unset($_SESSION['backup_message_type']);
}

// Handle Delete Backup
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $filename = basename($_GET['delete']); // Sanitize filename
    $filepath = __DIR__ . '/../backups/' . $filename;
    
    if (file_exists($filepath) && pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
        if (unlink($filepath)) {
            $_SESSION['backup_message'] = "✅ Backup deleted successfully: <strong>$filename</strong>";
            $_SESSION['backup_message_type'] = 'success';
        } else {
            $_SESSION['backup_message'] = "❌ Error deleting backup file.";
            $_SESSION['backup_message_type'] = 'error';
        }
    } else {
        $_SESSION['backup_message'] = "❌ Backup file not found.";
        $_SESSION['backup_message_type'] = 'error';
    }
    
    // Redirect to remove the delete parameter
    header("Location: backup.php");
    exit;
}

// Handle Backup
if (isset($_POST['create_backup'])) {
    $backup_dir = __DIR__ . '/../backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    // Get custom name or use default
    $custom_name = !empty($_POST['backup_name']) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['backup_name']) : '';
    $timestamp = date('Y-m-d_H-i-s');
    $filename = $custom_name ? $custom_name . '_' . $timestamp . '.sql' : 'backup_' . $timestamp . '.sql';
    $filepath = $backup_dir . $filename;
    
    // Get database credentials
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'uiu_smart_campus';
    
    // Use mysqldump with proper error handling
    $command = "mysqldump --host=$host --user=$username";
    if (!empty($password)) {
        $command .= " --password=$password";
    }
    $command .= " $database > \"$filepath\" 2>&1";
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        $size = number_format(filesize($filepath) / 1024 / 1024, 2);
        $message = "✅ Backup created successfully: <strong>$filename</strong> ($size MB)";
        $message_type = 'success';
    } else {
        $error_output = implode("\n", $output);
        $message = "❌ Error creating backup. " . (file_exists($filepath) ? "File created but may be empty." : "File not created.") . " Output: " . htmlspecialchars($error_output);
        $message_type = 'error';
    }
}

// Handle Restore
if (isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $temp_path = $file['tmp_name'];
        
        // Verify it's a valid SQL file
        $file_content = file_get_contents($temp_path, false, null, 0, 1000);
        if (strpos($file_content, 'CREATE TABLE') === false && strpos($file_content, 'INSERT INTO') === false) {
            $message = "❌ Invalid backup file. File doesn't appear to be a valid SQL backup.";
            $message_type = 'error';
        } else {
            $host = 'localhost';
            $username = 'root';
            $password = '';
            $database = 'uiu_smart_campus';
            
            // Use mysql command with proper error handling
            $command = "mysql --host=$host --user=$username";
            if (!empty($password)) {
                $command .= " --password=$password";
            }
            $command .= " $database < \"$temp_path\" 2>&1";
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0) {
                $message = "✅ Database restored successfully! All data has been replaced with the backup.";
                $message_type = 'success';
            } else {
                $error_output = implode("\n", $output);
                $message = "❌ Error restoring database. Output: " . htmlspecialchars($error_output);
                $message_type = 'error';
            }
        }
    } else {
        $message = "❌ Error uploading file. Error code: " . $file['error'];
        $message_type = 'error';
    }
}

// Get existing backups
$backup_dir = __DIR__ . '/../backups/';
$backups = [];
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'name' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => filemtime($backup_dir . $file)
            ];
        }
    }
}

// Get database statistics
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $table_name = $row[0];
    $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table_name`");
    $count = $count_result->fetch_assoc()['count'];
    
    $size_result = $conn->query("SELECT 
        ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = 'uiu_smart_campus' AND TABLE_NAME = '$table_name'");
    $size = $size_result->fetch_assoc()['size_mb'] ?? 0;
    
    $tables[] = [
        'name' => $table_name,
        'rows' => $count,
        'size' => $size
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - Admin</title>
    <link rel="stylesheet" href="assets/css/admin-style.css">
    <link rel="stylesheet" href="assets/css/manage-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="dashboard-container">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-database"></i> Backup & Restore</h1>
                    <p class="subtitle">Database backup management and restoration</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Database Statistics -->
            <div class="table-card" style="margin-bottom: 30px;">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Database Statistics</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <th>Rows</th>
                                <th>Size (MB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $table): ?>
                                <tr>
                                    <td><strong><?php echo $table['name']; ?></strong></td>
                                    <td><?php echo number_format($table['rows']); ?></td>
                                    <td><?php echo $table['size']; ?> MB</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="charts-grid">
                <!-- Create Backup -->
                <div class="table-card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Create New Backup</h3>
                    </div>
                    <div style="padding: 30px;">
                        <p style="margin-bottom: 20px;">Create a complete backup of the database. This will include all tables, data, and structure.</p>
                        <form method="POST">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600;">Backup Name (Optional)</label>
                                <input type="text" name="backup_name" placeholder="e.g., before_update, weekly_backup" 
                                       style="width: 100%; padding: 10px; border: 2px solid #e1e4e8; border-radius: 8px; font-size: 14px;"
                                       pattern="[a-zA-Z0-9_-]*" title="Only letters, numbers, hyphens and underscores allowed">
                                <small style="color: #6c757d; font-size: 12px;">Leave empty for auto-generated name. Timestamp will be added automatically.</small>
                            </div>
                            <button type="submit" name="create_backup" class="btn btn-success" style="width: 100%;">
                                <i class="fas fa-download"></i> Create Backup Now
                            </button>
                        </form>
                        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; border-left: 4px solid #ffc107;">
                            <strong>⚠️ Note:</strong> Large databases may take a few moments to backup.
                        </div>
                    </div>
                </div>

                <!-- Restore Backup -->
                <div class="table-card">
                    <div class="card-header">
                        <h3><i class="fas fa-upload"></i> Restore from Backup</h3>
                    </div>
                    <div style="padding: 30px;">
                        <p style="margin-bottom: 20px;">Upload and restore a previous backup file. This will overwrite all current data!</p>
                        <form method="POST" enctype="multipart/form-data" onsubmit="return confirmRestore()">
                            <div class="form-group">
                                <label>Select Backup File (.sql)</label>
                                <input type="file" name="backup_file" accept=".sql" required 
                                       style="width: 100%; padding: 10px; border: 2px solid #e1e4e8; border-radius: 8px;">
                            </div>
                            <button type="submit" name="restore_backup" class="btn btn-danger" style="width: 100%;">
                                <i class="fas fa-undo"></i> Restore Backup
                            </button>
                        </form>
                        <div style="margin-top: 20px; padding: 15px; background: #f8d7da; border-radius: 8px; border-left: 4px solid #dc3545;">
                            <strong>⚠️ Warning:</strong> This action cannot be undone! All current data will be replaced.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Existing Backups -->
            <div class="table-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Backup History</h3>
                    <span class="badge badge-info"><?php echo count($backups); ?> backups</span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Size</th>
                                <th>Created Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($backups)): ?>
                                <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td><strong><?php echo $backup['name']; ?></strong></td>
                                        <td><?php echo number_format($backup['size'] / 1024 / 1024, 2); ?> MB</td>
                                        <td><?php echo date('M d, Y H:i:s', $backup['date']); ?></td>
                                        <td class="text-center">
                                            <a href="api/download_backup.php?file=<?php echo urlencode($backup['name']); ?>" 
                                               class="btn-action btn-edit" title="Download" style="text-decoration: none;">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button onclick="deleteBackup('<?php echo htmlspecialchars($backup['name'], ENT_QUOTES); ?>')" 
                                                    class="btn-action btn-delete" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">No backups found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmRestore() {
            return confirm('⚠️ WARNING: This will replace ALL current data with the backup file!\n\nAre you absolutely sure you want to continue?\n\nThis action CANNOT be undone!');
        }

        function deleteBackup(filename) {
            if (confirm('⚠️ Delete backup: ' + filename + '?\n\nThis action cannot be undone!')) {
                window.location.href = 'backup.php?delete=' + encodeURIComponent(filename);
            }
        }
    </script>
</body>
</html>
