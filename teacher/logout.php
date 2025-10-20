<?php
/**
 * Teacher Logout
 */
session_start();

// Destroy session
session_unset();
session_destroy();

// Redirect to main login page
header('Location: ../login.html');
exit;
?>
