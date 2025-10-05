<?php
session_start();
session_destroy();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
</head>
<body>
    <script>
        // Clear sidebar scroll position from sessionStorage
        sessionStorage.removeItem('sidebarScrollPos');
        // Redirect to login
        window.location.href = 'login.php';
    </script>
</body>
</html>
