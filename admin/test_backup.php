<?php
/**
 * Backup & Restore Test Script
 * This script tests if mysqldump and mysql commands work correctly
 */

echo "<h2>🧪 Backup & Restore Test</h2>";
echo "<pre>";

// Test 1: Check if mysqldump is available
echo "\n=== Test 1: Check mysqldump availability ===\n";
exec('mysqldump --version 2>&1', $output1, $return1);
if ($return1 === 0) {
    echo "✅ mysqldump is available\n";
    echo "Version: " . implode("\n", $output1) . "\n";
} else {
    echo "❌ mysqldump NOT found\n";
    echo "Output: " . implode("\n", $output1) . "\n";
}

// Test 2: Check if mysql is available
echo "\n=== Test 2: Check mysql availability ===\n";
exec('mysql --version 2>&1', $output2, $return2);
if ($return2 === 0) {
    echo "✅ mysql is available\n";
    echo "Version: " . implode("\n", $output2) . "\n";
} else {
    echo "❌ mysql NOT found\n";
    echo "Output: " . implode("\n", $output2) . "\n";
}

// Test 3: Try to create a test backup
echo "\n=== Test 3: Create test backup ===\n";
$backup_dir = __DIR__ . '/backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
    echo "✅ Created backups directory\n";
}

$test_filename = 'test_backup_' . date('Y-m-d_H-i-s') . '.sql';
$test_filepath = $backup_dir . $test_filename;

$command = 'mysqldump --host=localhost --user=root uiu_smart_campus > "' . $test_filepath . '" 2>&1';
echo "Command: $command\n";

exec($command, $output3, $return3);

if ($return3 === 0 && file_exists($test_filepath)) {
    $filesize = filesize($test_filepath);
    echo "✅ Backup created successfully\n";
    echo "File: $test_filename\n";
    echo "Size: " . number_format($filesize / 1024 / 1024, 2) . " MB\n";
    
    if ($filesize > 0) {
        echo "✅ Backup file has content\n";
        
        // Read first few lines
        $handle = fopen($test_filepath, 'r');
        $first_lines = '';
        for ($i = 0; $i < 10; $i++) {
            $first_lines .= fgets($handle);
        }
        fclose($handle);
        
        echo "\nFirst few lines:\n";
        echo htmlspecialchars($first_lines) . "\n";
    } else {
        echo "❌ Backup file is EMPTY\n";
    }
} else {
    echo "❌ Backup creation FAILED\n";
    echo "Return code: $return3\n";
    echo "Output: " . implode("\n", $output3) . "\n";
}

// Test 4: Check backup directory permissions
echo "\n=== Test 4: Directory permissions ===\n";
if (is_writable($backup_dir)) {
    echo "✅ Backup directory is writable\n";
} else {
    echo "❌ Backup directory is NOT writable\n";
}
echo "Directory: $backup_dir\n";
echo "Permissions: " . substr(sprintf('%o', fileperms($backup_dir)), -4) . "\n";

// Test 5: List existing backups
echo "\n=== Test 5: Existing backups ===\n";
$files = scandir($backup_dir);
$backup_count = 0;
foreach ($files as $file) {
    if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        $backup_count++;
        $size = filesize($backup_dir . $file);
        $date = date('Y-m-d H:i:s', filemtime($backup_dir . $file));
        echo "$file - " . number_format($size / 1024 / 1024, 2) . " MB - $date\n";
    }
}
echo "\nTotal backups found: $backup_count\n";

// Summary
echo "\n=== SUMMARY ===\n";
if ($return1 === 0 && $return2 === 0 && $return3 === 0 && file_exists($test_filepath) && filesize($test_filepath) > 0) {
    echo "✅ ALL TESTS PASSED - Backup system is working correctly!\n";
} else {
    echo "❌ SOME TESTS FAILED - Check the output above for details\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure MySQL/MariaDB is installed\n";
    echo "2. Make sure mysqldump and mysql are in the system PATH\n";
    echo "3. Check that XAMPP is running\n";
    echo "4. Verify database name is 'uiu_smart_campus'\n";
    echo "5. Check file permissions on the backups directory\n";
}

echo "</pre>";
?>
