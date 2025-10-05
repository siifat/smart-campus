<?php
session_start();

// Simulate a logged-in student
$_SESSION['student_logged_in'] = true;
$_SESSION['student_id'] = '0112320240'; // Use the actual student ID from database

// Simulate POST request
$_POST['action'] = 'delete_resource';
$_POST['resource_id'] = '2'; // Use actual resource ID

// Include the API file
require_once('student/api/resources.php');
?>
