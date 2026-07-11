<?php
// Database configuration for library_management_system
$host = "localhost";
$user = "root";
$pass = "";
$db = "library_management_system";

// Create connection
$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    die("Connection error: " . mysqli_connect_error());
}

// File upload constants
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_DIR', 'assets/uploads/');
?>