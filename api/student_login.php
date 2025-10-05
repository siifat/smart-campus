<?php
// api/student_login.php - Student Authentication Handler
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Database connection
require_once('../config/database.php');

// Function to send JSON response
function sendResponse($success, $message, $data = [], $redirect = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];
    if ($redirect) {
        $response['redirect'] = $redirect;
    }
    echo json_encode($response);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method. Only POST is allowed.');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input) {
    sendResponse(false, 'Invalid JSON data received.');
}

$student_id = trim($input['student_id'] ?? '');
$password = $input['password'] ?? '';
$remember = $input['remember'] ?? false;

// Validation
$errors = [];

if (empty($student_id)) {
    $errors[] = 'Student ID is required';
}

if (empty($password)) {
    $errors[] = 'Password is required';
}

// Validate student ID format (10 digits)
if (!empty($student_id) && !preg_match('/^\d{10}$/', $student_id)) {
    $errors[] = 'Student ID must be exactly 10 digits';
}

if (!empty($errors)) {
    sendResponse(false, implode(', ', $errors));
}

try {
    // Check database connection
    if (!isset($conn) || !$conn) {
        sendResponse(false, 'Database connection failed. Please contact system administrator.');
    }

    // Prepare statement to fetch student
    $stmt = $conn->prepare("
        SELECT 
            s.student_id,
            s.password_hash,
            s.full_name,
            s.email,
            s.program_id,
            s.current_trimester_number,
            s.current_cgpa,
            s.total_points,
            s.status,
            s.profile_picture,
            p.program_name,
            p.program_code,
            d.department_name
        FROM students s
        JOIN programs p ON s.program_id = p.program_id
        JOIN departments d ON p.department_id = d.department_id
        WHERE s.student_id = ?
    ");

    if (!$stmt) {
        sendResponse(false, 'Database query preparation failed.');
    }

    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if student exists
    if ($result->num_rows === 0) {
        sendResponse(false, 'Invalid Student ID or Password. Please check your credentials.');
    }

    $student = $result->fetch_assoc();

    // Check if account is active
    if ($student['status'] !== 'active') {
        $status_messages = [
            'inactive' => 'Your account is currently inactive. Please contact administration.',
            'graduated' => 'Your account has been marked as graduated. Please contact administration for access.',
            'withdrawn' => 'Your account has been withdrawn. Please contact administration.'
        ];
        $message = $status_messages[$student['status']] ?? 'Your account is not active. Please contact administration.';
        sendResponse(false, $message);
    }

    // Verify password
    if (!password_verify($password, $student['password_hash'])) {
        sendResponse(false, 'Invalid Student ID or Password. Please check your credentials.');
    }

    // Password verified - Create session
    session_regenerate_id(true); // Prevent session fixation

    $_SESSION['student_logged_in'] = true;
    $_SESSION['student_id'] = $student['student_id'];
    $_SESSION['student_name'] = $student['full_name'];
    $_SESSION['student_email'] = $student['email'];
    $_SESSION['program_id'] = $student['program_id'];
    $_SESSION['program_name'] = $student['program_name'];
    $_SESSION['program_code'] = $student['program_code'];
    $_SESSION['department_name'] = $student['department_name'];
    $_SESSION['current_cgpa'] = $student['current_cgpa'];
    $_SESSION['total_points'] = $student['total_points'];
    $_SESSION['profile_picture'] = $student['profile_picture'];
    $_SESSION['login_time'] = time();

    // Set session timeout (2 hours)
    $_SESSION['session_timeout'] = time() + (2 * 60 * 60);

    // Handle "Remember Me"
    if ($remember) {
        // Set cookie for 30 days
        $cookie_value = base64_encode($student_id . ':' . bin2hex(random_bytes(16)));
        setcookie('remember_student', $cookie_value, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }

    // Log the login activity (optional)
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    // Log the login activity
    $activity_stmt = $conn->prepare("
        INSERT INTO student_activities 
        (student_id, activity_type, activity_title, activity_description, icon_class)
        VALUES (?, 'login', 'Logged into Smart Campus', 'Successfully logged in from UCAM credentials', 'fa-sign-in-alt')
    ");
    $activity_stmt->bind_param("s", $student_id);
    $activity_stmt->execute();

    // Get enrolled courses count
    $course_stmt = $conn->prepare("
        SELECT COUNT(*) as course_count 
        FROM enrollments 
        WHERE student_id = ? AND status = 'enrolled'
    ");
    $course_stmt->bind_param("s", $student_id);
    $course_stmt->execute();
    $course_result = $course_stmt->get_result();
    $course_data = $course_result->fetch_assoc();

    // Send success response
    sendResponse(
        true,
        'Login successful! Welcome back, ' . explode(' ', $student['full_name'])[0] . '!',
        [
            'student_name' => $student['full_name'],
            'student_id' => $student['student_id'],
            'program' => $student['program_name'],
            'cgpa' => number_format($student['current_cgpa'], 2),
            'points' => $student['total_points'],
            'courses' => $course_data['course_count'] ?? 0
        ],
        './student/dashboard.php'
    );

} catch (Exception $e) {
    // Log error for debugging (in production, don't expose error details)
    error_log('Login Error: ' . $e->getMessage());
    sendResponse(false, 'An error occurred during login. Please try again later.');
}
?>
