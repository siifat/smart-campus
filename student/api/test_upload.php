<?php
/**
 * Test Upload API Endpoint
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'API is accessible',
    'session_status' => isset($_SESSION['student_logged_in']),
    'student_id' => $_SESSION['student_id'] ?? 'Not set',
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'files' => isset($_FILES) ? array_keys($_FILES) : [],
    'post' => array_keys($_POST)
]);
