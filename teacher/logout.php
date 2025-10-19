<?php
/**
 * Teacher Logout
 */
session_start();

// Destroy session
session_unset();
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>
