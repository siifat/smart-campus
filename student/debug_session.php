<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_data' => $_SESSION,
    'is_logged_in' => isset($_SESSION['student_logged_in']),
    'student_id' => $_SESSION['student_id'] ?? 'not set',
    'session_timeout' => $_SESSION['session_timeout'] ?? 'not set',
    'time_now' => time(),
    'timeout_expired' => isset($_SESSION['session_timeout']) ? (time() > $_SESSION['session_timeout']) : 'n/a'
]);
?>
