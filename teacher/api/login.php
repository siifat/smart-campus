<?php
/**
 * Teacher Login API
 */
session_start();
header('Content-Type: application/json');

require_once('../../config/database.php');

// Get JSON input
$json_data = json_decode(file_get_contents('php://input'), true);
$username = trim($json_data['username'] ?? '');
$password = $json_data['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    // Check by username or initial
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE (username = ? OR initial = ?) AND status = 'active'");
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    $teacher = $result->fetch_assoc();

    // Verify password
    if (!password_verify($password, $teacher['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        exit;
    }

    // Create session
    $_SESSION['teacher_logged_in'] = true;
    $_SESSION['teacher_id'] = $teacher['teacher_id'];
    $_SESSION['teacher_name'] = $teacher['full_name'];
    $_SESSION['teacher_initial'] = $teacher['initial'];
    $_SESSION['teacher_email'] = $teacher['email'];
    $_SESSION['department_id'] = $teacher['department_id'];
    $_SESSION['session_timeout'] = time() + (2 * 60 * 60); // 2 hours

    // Log session in database
    $session_token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', time() + 7200);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $session_stmt = $conn->prepare("INSERT INTO teacher_sessions (teacher_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
    $session_stmt->bind_param('issss', $teacher['teacher_id'], $session_token, $ip_address, $user_agent, $expires_at);
    $session_stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'teacher' => [
            'name' => $teacher['full_name'],
            'initial' => $teacher['initial'],
            'email' => $teacher['email']
        ]
    ]);

} catch (Exception $e) {
    error_log('Teacher login error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
