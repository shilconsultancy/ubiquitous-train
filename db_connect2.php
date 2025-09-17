<?php
/*
 * File: db_connect.php
 * Description: Establishes a connection to the MySQL database and sets the character set.
 */

// --- DATABASE CREDENTIALS ---
$servername = "localhost";
$username = "u737457905_lms";
$password = "i8IlJg0[";
$dbname = "u737457905_lms";

// --- ESTABLISH THE CONNECTION ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- CHECK FOR CONNECTION ERRORS ---
if ($conn->connect_error) {
  die("Database Connection Failed: " . $conn->connect_error);
}

// --- SET CHARACTER SET ---
if (!$conn->set_charset("utf8mb4")) {
    printf("Error loading character set utf8mb4: %s\n", $conn->error);
}

?>