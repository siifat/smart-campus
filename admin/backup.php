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

// Handle Backup
if (isset($_POST['create_backup'])) {
    $backup_dir = '../backups/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;
    
    // Get database credentials from config
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'uiu_smart_campus';
    
    $command = "mysqldump --host=$host --user=$username --password=$password $database > $filepath";
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        $message = "✅ Backup created successfully: $filename";
        $message_type = 'success';
    } else {
        $message = "❌ Error creating backup";
        $message_type = 'error';
    }
}

// Handle Restore
if (isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $temp_path = $file['tmp_name'];
        
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'uiu_smart_campus';
        
        $command = "mysql --host=$host --user=$username --password=$password $database < $temp_path";
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $message = "✅ Database restored successfully!";
            $message_type = 'success';
        } else {
            $message = "❌ Error restoring database";
            $message_type = 'error';
        }
    }
}

// Get existing backups
$backup_dir = '../backups/';
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
                                            <a href="../backups/<?php echo $backup['name']; ?>" download 
                                               class="btn-action btn-edit" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button onclick="deleteBackup('<?php echo $backup['name']; ?>')" 
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
            if (confirm('Delete backup: ' + filename + '?')) {
                window.location.href = 'delete_backup.php?file=' + encodeURIComponent(filename);
            }
        }
    </script>
</body>
</html>
