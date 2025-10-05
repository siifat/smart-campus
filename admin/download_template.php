<?php
/**
 * Download CSV Templates
 */
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$type = $_GET['type'] ?? '';

$templates = [
    'departments' => [
        'filename' => 'departments_template.csv',
        'headers' => ['department_code', 'department_name'],
        'sample_data' => [
            ['CSE', 'Computer Science and Engineering'],
            ['EEE', 'Electrical and Electronic Engineering'],
            ['BBA', 'Business Administration'],
            ['CE', 'Civil Engineering'],
            ['ENG', 'English'],
        ]
    ],
    'programs' => [
        'filename' => 'programs_template.csv',
        'headers' => ['program_code', 'program_name', 'department_code', 'total_required_credits', 'duration_years'],
        'sample_data' => [
            ['BSC_CSE', 'Bachelor of Science in Computer Science and Engineering', 'CSE', '140', '4.0'],
            ['BSC_EEE', 'Bachelor of Science in Electrical and Electronic Engineering', 'EEE', '140', '4.0'],
            ['BBA', 'Bachelor of Business Administration', 'BBA', '120', '4.0'],
            ['MBA', 'Master of Business Administration', 'BBA', '48', '1.5'],
        ]
    ],
    'trimesters' => [
        'filename' => 'trimesters_template.csv',
        'headers' => ['trimester_code', 'trimester_name', 'trimester_type', 'year', 'start_date', 'end_date', 'is_current'],
        'sample_data' => [
            ['251', 'Spring 2025', 'trimester', '2025', '2025-01-01', '2025-05-31', '0'],
            ['252', 'Summer 2025', 'trimester', '2025', '2025-06-01', '2025-08-31', '0'],
            ['253', 'Fall 2025', 'trimester', '2025', '2025-09-01', '2025-12-31', '1'],
        ]
    ],
    'courses' => [
        'filename' => 'courses_template.csv',
        'headers' => ['course_code', 'course_name', 'credit_hours', 'department_code', 'course_type'],
        'sample_data' => [
            ['CSE 1115', 'Introduction to Computer Science', '3', 'CSE', 'theory'],
            ['CSE 1116', 'Introduction to Computer Science Lab', '1', 'CSE', 'lab'],
            ['CSE 3521', 'Database Management Systems', '3', 'CSE', 'theory'],
            ['CSE 3522', 'Database Management Systems Lab', '1', 'CSE', 'lab'],
            ['CSE 4846', 'Capstone Project', '3', 'CSE', 'project'],
        ]
    ]
];

if (!isset($templates[$type])) {
    die('Invalid template type');
}

$template = $templates[$type];

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $template['filename'] . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, $template['headers']);

// Write sample data
foreach ($template['sample_data'] as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
?>
