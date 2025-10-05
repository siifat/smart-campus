<?php
/**
 * Database migration script to create student_todos and student_activities tables
 * Run this file once from your browser: http://localhost/smartcampus/create_todos_table.php
 * Database: uiu_smart_campus
 */

require_once('config/database.php');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Migration - UIU Smart Campus</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #f68b1f; border-bottom: 3px solid #f68b1f; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; }
        .success { color: #10b981; font-weight: bold; margin: 20px 0; padding: 15px; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 4px; }
        .error { color: #ef4444; font-weight: bold; margin: 20px 0; padding: 15px; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #f68b1f; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn { display: inline-block; padding: 12px 24px; background: #f68b1f; color: white; text-decoration: none; border-radius: 8px; margin: 20px 10px 0 0; font-weight: bold; }
        .btn:hover { background: #e57a0f; }
        .database-info { background: #fef3c7; border-left: 4px solid #fbbf24; padding: 15px; margin: 20px 0; border-radius: 4px; }
    </style>
</head>
<body>
<div class='container'>
<h1>📊 UIU Smart Campus - Database Migration</h1>
<div class='database-info'>
    <strong>🗄️ Target Database:</strong> uiu_smart_campus<br>
    <strong>📅 Migration Date:</strong> " . date('F j, Y g:i A') . "
</div>";

$errors = [];
$success_count = 0;

try {
    // Create student_todos table
    $sql_todos = "CREATE TABLE IF NOT EXISTS student_todos (
        todo_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id VARCHAR(10) NOT NULL,
        task TEXT NOT NULL,
        completed BOOLEAN DEFAULT 0,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        due_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        INDEX idx_student_id (student_id),
        INDEX idx_completed (completed),
        INDEX idx_created_at (created_at),
        INDEX idx_due_date (due_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Student to-do list items'";
    
    if ($conn->query($sql_todos) === TRUE) {
        echo "<div class='success'>✅ Table 'student_todos' created successfully!</div>";
        $success_count++;
        
        // Show table structure
        $structure = $conn->query("DESCRIBE student_todos");
        if ($structure) {
            echo "<h2>📋 Table Structure: student_todos</h2>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $structure->fetch_assoc()) {
                echo "<tr>";
                echo "<td><strong>{$row['Field']}</strong></td>";
                echo "<td>{$row['Type']}</td>";
                echo "<td>{$row['Null']}</td>";
                echo "<td>{$row['Key']}</td>";
                echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
                echo "<td>{$row['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        $errors[] = "student_todos: " . $conn->error;
    }
    
    // Create student_activities table
    $sql_activities = "CREATE TABLE IF NOT EXISTS student_activities (
        activity_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id VARCHAR(10) NOT NULL,
        activity_type ENUM('login', 'course_view', 'assignment_submit', 'note_upload', 
                           'question_post', 'quiz_complete', 'grade_received', 
                           'attendance_marked', 'todo_complete', 'study_session', 'other') NOT NULL,
        activity_title VARCHAR(255) NOT NULL,
        activity_description TEXT NULL,
        related_course_id INT NULL,
        related_id INT NULL COMMENT 'ID of related entity (assignment_id, note_id, etc.)',
        icon_class VARCHAR(50) DEFAULT 'fa-circle' COMMENT 'FontAwesome icon class',
        activity_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        FOREIGN KEY (related_course_id) REFERENCES courses(course_id) ON DELETE SET NULL,
        INDEX idx_student_id (student_id),
        INDEX idx_activity_date (activity_date),
        INDEX idx_activity_type (activity_type),
        INDEX idx_related_course (related_course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Student activity feed/history'";
    
    if ($conn->query($sql_activities) === TRUE) {
        echo "<div class='success'>✅ Table 'student_activities' created successfully!</div>";
        $success_count++;
        
        // Show table structure
        $structure = $conn->query("DESCRIBE student_activities");
        if ($structure) {
            echo "<h2>📋 Table Structure: student_activities</h2>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $structure->fetch_assoc()) {
                echo "<tr>";
                echo "<td><strong>{$row['Field']}</strong></td>";
                echo "<td>{$row['Type']}</td>";
                echo "<td>{$row['Null']}</td>";
                echo "<td>{$row['Key']}</td>";
                echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
                echo "<td>{$row['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        $errors[] = "student_activities: " . $conn->error;
    }
    
    // Display results
    echo "<hr style='margin: 40px 0; border: none; border-top: 2px solid #e5e7eb;'>";
    
    if (count($errors) > 0) {
        echo "<div class='error'>❌ Errors occurred:<br>";
        foreach ($errors as $error) {
            echo "• $error<br>";
        }
        echo "</div>";
    }
    
    if ($success_count > 0) {
        echo "<div class='success'>";
        echo "<h2 style='color: #10b981; margin-top: 0;'>🎉 Migration Completed Successfully!</h2>";
        echo "<p><strong>$success_count</strong> table(s) created in database <strong>uiu_smart_campus</strong></p>";
        echo "<ul style='margin: 15px 0;'>";
        echo "<li>✅ <strong>student_todos</strong> - Stores student to-do list items</li>";
        echo "<li>✅ <strong>student_activities</strong> - Tracks student activity feed/history</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<a href='student/dashboard.php' class='btn'>📊 Go to Student Dashboard</a>";
        echo "<a href='admin/dashboard.php' class='btn'>🔧 Go to Admin Dashboard</a>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Exception: " . $e->getMessage() . "</div>";
}

    // Create resource system tables
    echo "<h2 style='margin-top: 40px; color: #f68b1f;'>🎯 Creating Resource Upload System...</h2>";
    
    // Read and execute the resources system SQL
    $sql_file = file_get_contents('resources_system.sql');
    if ($sql_file) {
        // Split by semicolon
        $statements = explode(';', $sql_file);
        $resource_success = 0;
        $resource_errors = [];
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            // Skip empty statements, comments, and USE statements
            if (!empty($statement) && 
                !preg_match('/^(USE|DELIMITER|--)/i', $statement) &&
                strlen($statement) > 10) {
                
                if ($conn->query($statement) === TRUE) {
                    $resource_success++;
                } else {
                    // Ignore "already exists" errors
                    if ($conn->error && 
                        !strpos($conn->error, 'already exists') && 
                        !strpos($conn->error, 'Duplicate')) {
                        $resource_errors[] = $conn->error;
                    }
                }
            }
        }
        
        // Create triggers separately (mysqli doesn't support DELIMITER)
        echo "<h3>Creating Database Triggers...</h3>";
        
        // Drop existing triggers first
        $conn->query("DROP TRIGGER IF EXISTS after_like_insert");
        $conn->query("DROP TRIGGER IF EXISTS after_like_delete");
        $conn->query("DROP TRIGGER IF EXISTS after_resource_insert");
        
        // Trigger 1: Increment likes count
        $trigger1 = "CREATE TRIGGER after_like_insert
        AFTER INSERT ON resource_likes
        FOR EACH ROW
        UPDATE uploaded_resources 
        SET likes_count = likes_count + 1 
        WHERE resource_id = NEW.resource_id";
        
        if ($conn->query($trigger1) === TRUE) {
            echo "<div class='success'>✅ Trigger: after_like_insert created</div>";
        } else {
            $resource_errors[] = "Trigger 1: " . $conn->error;
        }
        
        // Trigger 2: Decrement likes count
        $trigger2 = "CREATE TRIGGER after_like_delete
        AFTER DELETE ON resource_likes
        FOR EACH ROW
        UPDATE uploaded_resources 
        SET likes_count = likes_count - 1 
        WHERE resource_id = OLD.resource_id";
        
        if ($conn->query($trigger2) === TRUE) {
            echo "<div class='success'>✅ Trigger: after_like_delete created</div>";
        } else {
            $resource_errors[] = "Trigger 2: " . $conn->error;
        }
        
        // Trigger 3: Award points on upload
        $trigger3 = "CREATE TRIGGER after_resource_insert
        AFTER INSERT ON uploaded_resources
        FOR EACH ROW
        BEGIN
            IF NEW.is_approved = 1 THEN
                UPDATE students 
                SET total_points = total_points + NEW.points_awarded 
                WHERE student_id = NEW.student_id;
                
                INSERT INTO student_activities 
                (student_id, activity_type, activity_title, activity_description, related_course_id, related_id, icon_class)
                VALUES 
                (NEW.student_id, 'note_upload', 'Uploaded Resource', CONCAT('Uploaded: ', NEW.title), NEW.course_id, NEW.resource_id, 'fa-upload');
            END IF;
        END";
        
        if ($conn->query($trigger3) === TRUE) {
            echo "<div class='success'>✅ Trigger: after_resource_insert created</div>";
        } else {
            $resource_errors[] = "Trigger 3: " . $conn->error;
        }
        
        // Summary
        if (count($resource_errors) == 0) {
            echo "<div class='success'>";
            echo "<h3 style='color: #10b981; margin-top: 20px;'>✅ Resource System Created Successfully!</h3>";
            echo "<p><strong>$resource_success</strong> tables and operations completed</p>";
            echo "<ul style='margin: 15px 0;'>";
            echo "<li>✅ <strong>resource_categories</strong> - 8 categories with icons</li>";
            echo "<li>✅ <strong>uploaded_resources</strong> - Main resources table</li>";
            echo "<li>✅ <strong>resource_likes</strong> - Like system</li>";
            echo "<li>✅ <strong>resource_bookmarks</strong> - Bookmark system</li>";
            echo "<li>✅ <strong>resource_comments</strong> - Comment system</li>";
            echo "<li>✅ <strong>resource_views</strong> - View tracking</li>";
            echo "<li>✅ <strong>students.total_points</strong> - Points column added</li>";
            echo "<li>✅ <strong>3 Triggers</strong> - Auto-update counts and award points</li>";
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='error'>⚠️ Some operations had errors:<br>";
            foreach ($resource_errors as $err) {
                echo "• " . htmlspecialchars($err) . "<br>";
            }
            echo "</div>";
        }
    }

$conn->close();

echo "</div></body></html>";
?>
