<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Default XAMPP username
define('DB_PASSWORD', '');     // Default XAMPP password is empty
define('DB_DATABASE', 'lms_db'); // The name of your database

// Create a new database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

// Check the connection
if ($conn->connect_error) {
    // If connection fails, stop the script and display an error
    die("Connection failed: " . $conn->connect_error);
}

// Set character set to utf8mb4 for full Unicode support
if (!$conn->set_charset("utf8mb4")) {
    // If setting charset fails, display an error
    printf("Error loading character set utf8mb4: %s\n", $conn->error);
    exit();
}
?>