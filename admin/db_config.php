<?php
/**
 * Database connection configuration.
 * This file establishes a connection to the MySQL database
 * and assigns the connection object to the global variable $conn.
 *
 * IMPORTANT: Replace 'your_db_username', 'your_db_password', and 'your_database_name'
 * with your actual database credentials.
 */

define('BASE_URL', '/projects/psb/');

$servername = "localhost"; // Database host (usually 'localhost' for local development)
$username = "root";       // Your database username (e.g., 'root' for XAMPP default)
$password = "";           // Your database password (e.g., empty string for XAMPP default)
$dbname = "lms_db";       // The name of your database

// Create a new MySQLi connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check if the connection was successful
if ($conn->connect_error) {
    // If connection fails, terminate the script and display the error
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set character set to UTF-8 for proper encoding handling
$conn->set_charset("utf8mb4");

?>
