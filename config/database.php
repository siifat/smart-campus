<?php
/**
 * Database Configuration
 * UIU Smart Campus - E-Learning Management System
 */

// Database credentials (based on database_info.txt)
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'uiu_smart_campus');

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set charset to utf8mb4 for proper Unicode support
$conn->set_charset('utf8mb4');

// Return the connection
return $conn;
