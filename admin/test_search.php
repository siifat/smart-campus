<?php
session_start();
$_SESSION['admin_logged_in'] = true; // Simulate logged in

require_once('../config/database.php');

echo "<h2>Testing Search API</h2>";

// Test database connection
if ($conn) {
    echo "✓ Database connected<br>";
} else {
    echo "✗ Database connection failed<br>";
    exit;
}

// Test search
$search_term = 'system';
$search_term = $conn->real_escape_string($search_term);

echo "<h3>Searching for: $search_term</h3>";

// Search Courses
$courses = $conn->query("SELECT course_id as id, course_name as name, course_code as detail 
                         FROM courses 
                         WHERE course_name LIKE '%$search_term%' 
                         OR course_code LIKE '%$search_term%'
                         LIMIT 5");

if ($courses) {
    echo "<strong>Courses found: " . $courses->num_rows . "</strong><br>";
    while ($row = $courses->fetch_assoc()) {
        echo "- " . $row['name'] . " (" . $row['detail'] . ")<br>";
    }
} else {
    echo "Query error: " . $conn->error . "<br>";
}

// Now test the actual API
echo "<hr><h3>Testing API endpoint</h3>";
$api_url = "http://localhost/smartcampus/admin/api/search.php?q=system";
echo "Calling: <a href='$api_url' target='_blank'>$api_url</a><br><br>";

$response = file_get_contents($api_url);
echo "<strong>API Response:</strong><br>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

$data = json_decode($response, true);
if ($data) {
    echo "<strong>Parsed JSON:</strong><br>";
    echo "<pre>" . print_r($data, true) . "</pre>";
} else {
    echo "Failed to parse JSON!<br>";
}
?>
