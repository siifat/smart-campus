<?php
/**
 * Student Logout Handler
 * Destroys the session and redirects to login page
 */
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the remember me cookie if exists
if (isset($_COOKIE['remember_student'])) {
    setcookie('remember_student', '', time() - 3600, '/', '', true, true);
}

// Destroy the session
session_destroy();

// Redirect to login page with success message
header('Location: ../login.html?logout=success');
exit;
?>
