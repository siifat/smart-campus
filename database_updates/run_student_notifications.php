<?php
/**
 * Run this script to create the student_notifications table
 * Execute: php database_updates/run_student_notifications.php
 */

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=uiu_smart_campus;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Connected to database...\n";
    
    $sql = file_get_contents(__DIR__ . '/add_student_notifications.sql');
    
    $pdo->exec($sql);
    
    echo "✅ student_notifications table created successfully!\n";
    echo "✅ Indexes added for performance\n";
    echo "✅ Foreign key constraint added\n";
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
