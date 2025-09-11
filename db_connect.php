<?php
/*
 * File: db_connect.php
 * Description: Establishes a connection to the MySQL database and sets the character set.
 */

// --- DATABASE CREDENTIALS ---
// IMPORTANT: Replace with your actual database credentials
$servername = "localhost";    // Usually "localhost"
$username = "root";           // Default for XAMPP is "root"
$password = "";               // Default for XAMPP is empty
$dbname = "lms_db"; // The database name you created

// --- ESTABLISH THE CONNECTION ---
// Create a new mysqli object to connect to the database
$conn = new mysqli($servername, $username, $password, $dbname);

// --- CHECK FOR CONNECTION ERRORS ---
// If the connect_error property is not null, it means an error occurred.
if ($conn->connect_error) {
  // The die() function immediately stops the script and prints a message.
  // It's a simple way to handle critical errors.
  die("Database Connection Failed: " . $conn->connect_error);
}

// --- SET CHARACTER SET ---
// It's good practice to set the character set to utf8mb4 to support a wide range of characters.
if (!$conn->set_charset("utf8mb4")) {
    // If setting charset fails, print an error. This is less critical than the connection itself.
    printf("Error loading character set utf8mb4: %s\n", $conn->error);
}

?>